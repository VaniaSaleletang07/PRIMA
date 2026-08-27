<?php
require_once '../auth/auth.php';
requireNonManager();

require_once '../config/config.php';

// Auto-create vehicle table if not exists
ensureVehicleTableExists();
ensurePengurusTablesExist();

$user = getCurrentUser();
$errorMsg = '';
$successMsg = '';
$pengurusList = getPengurusUsersList();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = Database::getInstance()->getConnection();
        
        $nomorPolisi = strtoupper(trim($_POST['nomor_polisi'] ?? ''));
        $merkMobil = trim($_POST['merk_mobil'] ?? '');
        $tahunKendaraan = $_POST['tahun_kendaraan'] ?? null;
        $namaTransport = trim($_POST['nama_transport'] ?? '');
        $emailKontraktor = trim($_POST['email_kontraktor'] ?? '');
        $usernameTransportir = trim($_POST['username_transportir'] ?? '');
        $produkKapasitas = trim($_POST['produk_kapasitas'] ?? '');
        $jenis = $_POST['jenis'] ?? 'SPBU';
        
        // Validasi
        if (empty($nomorPolisi)) {
            throw new Exception('Nomor polisi tidak boleh kosong');
        }
        if (empty($merkMobil)) {
            throw new Exception('Merk mobil tidak boleh kosong');
        }
        if (empty($emailKontraktor)) {
            throw new Exception('Email kontraktor / PJ wajib diisi agar notifikasi KIM otomatis dapat dikirim');
        }
        if (!filter_var($emailKontraktor, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Format email kontraktor tidak valid');
        }
        if (empty($usernameTransportir)) {
            throw new Exception('Username akun transportir wajib dipilih agar notifikasi status EKIM masuk ke akun pemilik kendaraan');
        }
        $transportirUser = null;
        foreach ($pengurusList as $pu) {
            if ($pu['username'] === $usernameTransportir) { $transportirUser = $pu; break; }
        }
        if (!$transportirUser) {
            throw new Exception('Username akun transportir tidak valid atau bukan akun Pengurus Kendaraan aktif');
        }
        if (!in_array($jenis, ['SPBU', 'INDUSTRI'])) {
            throw new Exception('Jenis kendaraan tidak valid');
        }
        
        // Cek apakah nomor polisi sudah ada
        $stmt = $db->prepare("SELECT id FROM kendaraan WHERE nomor_polisi = :nomor_polisi");
        $stmt->execute([':nomor_polisi' => $nomorPolisi]);
        
        if ($stmt->rowCount() > 0) {
            throw new Exception('Nomor polisi ' . $nomorPolisi . ' sudah terdaftar');
        }
        
        // Insert data kendaraan
        $stmt = $db->prepare("
            INSERT INTO kendaraan (
                jenis,
                nomor_polisi,
                merk_mobil,
                tahun_kendaraan,
                nama_transport,
                email_kontraktor,
                produk_kapasitas,
                status,
                created_by
            ) VALUES (
                :jenis,
                :nomor_polisi,
                :merk_mobil,
                :tahun_kendaraan,
                :nama_transport,
                :email_kontraktor,
                :produk_kapasitas,
                'AKTIF',
                :created_by
            )
        ");
        
        $stmt->execute([
            ':jenis' => $jenis,
            ':nomor_polisi' => $nomorPolisi,
            ':merk_mobil' => $merkMobil,
            ':tahun_kendaraan' => !empty($tahunKendaraan) ? $tahunKendaraan : null,
            ':nama_transport' => $namaTransport,
            ':email_kontraktor' => !empty($emailKontraktor) ? $emailKontraktor : null,
            ':produk_kapasitas' => $produkKapasitas,
            ':created_by' => $user['id']
        ]);
        
        $vehicleId = $db->lastInsertId();
        
        // Hubungkan kendaraan ke akun transportir yang dipilih, agar notifikasi
        // status EKIM (diterbitkan / tidak dapat diterbitkan) muncul di dashboard
        // akun tersebut.
        linkVehicleToTransportir($nomorPolisi, (int)$transportirUser['id'], $namaTransport ?: null);
        
        // Log audit
        logAudit(null, 'CREATE', $user['username'], "Registrasi kendaraan baru: $nomorPolisi ($merkMobil)");
        
        $successMsg = "Kendaraan $nomorPolisi berhasil terdaftar!";
        
    } catch(Exception $e) {
        $errorMsg = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Registrasi Kendaraan — PRIMA</title>
    <style>
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

        body {
            font-family: "Segoe UI", -apple-system, BlinkMacSystemFont, Arial, sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
            color: #1a2332;
            font-size: 14px;
            line-height: 1.5;
        }

        .page-wrap {
            max-width: 780px;
            margin: 0 auto;
            padding: 28px 20px 48px;
        }

        /* ── PAGE HEADER ── */
        .page-header {
            background: #fff;
            border: 1px solid #dde3ec;
            border-top: 3px solid #c8102e;
            border-radius: 6px;
            padding: 20px 26px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }
        .page-header-left { display:flex; align-items:center; gap:14px; }
        .page-header-icon {
            width: 40px; height: 40px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 6px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .page-title { font-size:18px; font-weight:700; color:#1a2332; letter-spacing:-0.2px; }
        .page-subtitle { font-size:12px; color:#6b7a8f; margin-top:2px; }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #fff;
            color: #374151;
            padding: 8px 16px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            border: 1px solid #d1d5db;
            transition: background .15s, border-color .15s;
            white-space: nowrap;
        }
        .btn-back:hover { background:#f9fafb; border-color:#9ca3af; }

        /* ── ALERT MESSAGES ── */
        .alert {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px 16px;
            border-radius: 5px;
            font-size: 13px;
            margin-bottom: 18px;
            border: 1px solid;
        }
        .alert-success { background:#f0fdf4; border-color:#bbf7d0; color:#166534; }
        .alert-error   { background:#fef2f2; border-color:#fecaca; color:#991b1b; }
        .alert-info    { background:#eff6ff; border-color:#bfdbfe; color:#1e40af; }

        /* ── FORM CARD ── */
        .form-card {
            background: #fff;
            border: 1px solid #dde3ec;
            border-radius: 6px;
            overflow: hidden;
        }
        .form-card-header {
            padding: 14px 22px;
            border-bottom: 1px solid #dde3ec;
            background: #fafbfc;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .6px;
            color: #6b7a8f;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .form-body { padding: 24px 22px; }

        /* ── FORM ELEMENTS ── */
        .form-section-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .6px;
            color: #9aacbb;
            margin: 22px 0 14px;
            padding-bottom: 8px;
            border-bottom: 1px solid #f0f2f5;
        }
        .form-section-label:first-child { margin-top: 0; }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .form-group { margin-bottom: 16px; }
        .form-group:last-child { margin-bottom: 0; }

        .form-label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: #374151;
            margin-bottom: 6px;
        }
        .form-label .req { color: #c8102e; margin-left: 2px; }

        .form-control {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid #d1d9e0;
            border-radius: 5px;
            font-size: 13px;
            font-family: inherit;
            color: #1a2332;
            background: #fff;
            transition: border-color .15s, box-shadow .15s;
        }
        .form-control:focus {
            outline: none;
            border-color: #c8102e;
            box-shadow: 0 0 0 3px rgba(200,16,46,.08);
        }
        .form-control::placeholder { color: #b0bec8; }
        select.form-control { cursor: pointer; }

        /* ── FORM FOOTER ── */
        .form-footer {
            padding: 16px 22px;
            border-top: 1px solid #dde3ec;
            background: #fafbfc;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 20px;
            border-radius: 5px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid;
            text-decoration: none;
            transition: background .15s, border-color .15s;
        }
        .btn-primary {
            background: #c8102e;
            border-color: #c8102e;
            color: #fff;
        }
        .btn-primary:hover { background: #a80d26; border-color: #a80d26; }
        .btn-secondary {
            background: #fff;
            border-color: #d1d5db;
            color: #374151;
        }
        .btn-secondary:hover { background: #f9fafb; border-color: #9ca3af; }

        @media (max-width: 600px) {
            .form-row { grid-template-columns: 1fr; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .form-footer { flex-direction: column-reverse; }
            .btn { width: 100%; justify-content: center; }
        }
    </style>
    <?php include __DIR__ . '/includes/sidebar-styles.php'; ?>
</head>
<body>
<div class="app-shell">
<?php $activeNav = 'register'; include __DIR__ . '/includes/sidebar-nav.php'; ?>
<div class="main-wrapper">
    <header class="top-bar">
        <div class="top-bar-left">
            <span class="top-bar-accent"></span>
            <span class="top-bar-title">Registrasi Kendaraan</span>
            <span class="top-bar-subtitle">PT Pertamina Patra Niaga</span>
        </div>
        <div class="top-bar-right">
            <div class="top-bar-user-info">
                <span class="top-bar-user-name"><?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?></span>
                <span class="top-bar-user-role"><?php echo getRoleLabel(); ?></span>
            </div>
            <a href="../auth/logout.php" class="btn-topbar-danger">Keluar</a>
        </div>
    </header>
    <div class="page-content">
<div class="page-wrap">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-header-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#c8102e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13" rx="1"/><polygon points="16,8 20,8 23,11 23,16 16,16 16,8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
            </div>
            <div>
                <div class="page-title">Registrasi Kendaraan Baru</div>
                <div class="page-subtitle">Daftarkan kendaraan tangki baru untuk checklist inspeksi</div>
            </div>
        </div>
        <a href="../home.php" class="btn-back">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            Kembali
        </a>
    </div>

    <?php if (!empty($successMsg)): ?>
    <div class="alert alert-success">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        <?php echo htmlspecialchars($successMsg); ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($errorMsg)): ?>
    <div class="alert alert-error">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <?php echo htmlspecialchars($errorMsg); ?>
    </div>
    <?php endif; ?>

    <div class="alert alert-info" style="margin-bottom:18px;">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
        Isi form di bawah untuk mendaftarkan kendaraan tangki baru. Data yang didaftarkan akan tersedia saat melakukan checklist inspeksi.
    </div>

    <!-- Form Card -->
    <div class="form-card">
        <div class="form-card-header">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Data Kendaraan
        </div>
        <form method="POST">
        <div class="form-body">

            <div class="form-section-label">Identitas Kendaraan</div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="jenis">Jenis Kendaraan <span class="req">*</span></label>
                    <select id="jenis" name="jenis" class="form-control" required>
                        <option value="">-- Pilih Jenis --</option>
                        <option value="SPBU">SPBU</option>
                        <option value="INDUSTRI">INDUSTRI</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="nomor_polisi">Nomor Polisi <span class="req">*</span></label>
                    <input type="text" id="nomor_polisi" name="nomor_polisi" class="form-control"
                           placeholder="CONTOH: DB 8232 CK" required style="text-transform:uppercase;" />
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="merk_mobil">Merk / Tipe Mobil <span class="req">*</span></label>
                <input type="text" id="merk_mobil" name="merk_mobil" class="form-control"
                       placeholder="Contoh: Hino 2023" required />
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="tahun_kendaraan">Tahun Kendaraan</label>
                    <input type="number" id="tahun_kendaraan" name="tahun_kendaraan" class="form-control"
                           min="2000" max="2099" placeholder="2024" />
                </div>
                <div class="form-group">
                    <label class="form-label" for="produk_kapasitas">Produk / Kapasitas</label>
                    <input type="text" id="produk_kapasitas" name="produk_kapasitas" class="form-control"
                           placeholder="Contoh: 16 KL" />
                </div>
            </div>

            <div class="form-section-label">Data Kontraktor</div>

            <div class="form-group">
                <label class="form-label" for="nama_transport">Nama Transport / Kontraktor</label>
                <input type="text" id="nama_transport" name="nama_transport" class="form-control"
                       placeholder="Nama perusahaan transportasi" />
            </div>

            <div class="form-group">
                <label class="form-label" for="email_kontraktor">Email Kontraktor / PJ <span class="req">*</span></label>
                <input type="email" id="email_kontraktor" name="email_kontraktor" class="form-control"
                       placeholder="email@perusahaan.com" required />
                <div class="form-hint" style="margin-top:4px;font-size:12px;color:#7a8ba0;">Wajib diisi &mdash; dipakai untuk pengiriman notifikasi KIM otomatis.</div>
            </div>

            <div class="form-group">
                <label class="form-label" for="username_transportir">Username Akun Transportir (Pengurus Kendaraan) <span class="req">*</span></label>
                <select id="username_transportir" name="username_transportir" class="form-control" required>
                    <option value="">-- Pilih akun pemilik kendaraan --</option>
                    <?php foreach ($pengurusList as $pu): ?>
                    <option value="<?= htmlspecialchars($pu['username']) ?>">
                        <?= htmlspecialchars($pu['full_name']) ?> (<?= htmlspecialchars($pu['username']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-hint" style="margin-top:4px;font-size:12px;color:#7a8ba0;">Wajib dipilih &mdash; notifikasi status penerbitan EKIM kendaraan ini akan tampil di dashboard akun tersebut.</div>
                <?php if (empty($pengurusList)): ?>
                <div class="form-hint" style="margin-top:4px;font-size:12px;color:#c8102e;">Belum ada akun Pengurus Kendaraan aktif. Buat/aktifkan akun dengan role Pengurus Kendaraan terlebih dahulu.</div>
                <?php endif; ?>
            </div>

        </div>
        <div class="form-footer">
            <a href="../home.php" class="btn btn-secondary">Batal</a>
            <button type="submit" class="btn btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                Daftarkan Kendaraan
            </button>
        </div>
        </form>
    </div>

</div>
    </div><!-- /page-content -->
</div><!-- /main-wrapper -->
</div><!-- /app-shell -->
</body>
</html>
