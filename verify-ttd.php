<?php
/**
 * Verifikasi Tanda Tangan Digital — Halaman Publik
 * PRIMA (Pertamina Checklist Mobil Tangki)
 *
 * Dapat diakses via QR Code tanpa login.
 * Memverifikasi keaslian dan integritas formulir checklist kendaraan.
 */

require_once 'config.php';

// Tidak memerlukan login — halaman publik untuk verifikasi

$token     = trim($_GET['token'] ?? '');
$uuid      = trim($_GET['uuid'] ?? '');
$formulir  = null;
$items     = [];
$result    = null;
$log_error = null;

if ($uuid !== '' || $token !== '') {
    if ($uuid !== '' && !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid)) {
        $log_error = 'UUID verifikasi tidak valid';
    } elseif ($uuid === '' && !preg_match('/^[0-9a-f]{64}$/i', $token)) {
        $log_error = 'Token tidak valid';
    } else {
        try {
            $db = Database::getInstance()->getConnection();

            $stmt = $db->prepare("
                SELECT fc.*,
                       u.full_name  AS creator_name,
                       u.username   AS creator_username
                FROM   formulir_checklist fc
                LEFT JOIN users u ON fc.created_by = u.id
                WHERE " . ($uuid !== '' ? 'fc.verification_uuid' : 'fc.qr_token') . " = ?
                LIMIT  1
            ");
            $stmt->execute([$uuid !== '' ? $uuid : $token]);
            $formulir = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if ($formulir) {
                // Ambil item checklist
                $stmt2 = $db->prepare("
                    SELECT * FROM checklist_items
                    WHERE formulir_id = ?
                    ORDER BY item_number ASC
                ");
                $stmt2->execute([$formulir['id']]);
                $items = $stmt2->fetchAll(PDO::FETCH_ASSOC);

                // ── Verifikasi ───────────────────────────────────────
                $formulir_id  = (int)$formulir['id'];
                $stored_hash  = $formulir['dokumen_hash'];
                $live_hash    = computeDocumentHash($formulir_id, $db);

                $hash_match       = ($stored_hash !== null) && ($live_hash === $stored_hash);
                $verification_hash = $formulir['verification_hash_sha512'] ?? null;
                $live_verification_hash = computeDocumentHash($formulir_id, $db, 'sha512');
                $verification_hash_match = ($verification_hash !== null)
                    && hash_equals($verification_hash, $live_verification_hash);
                $verification_signature_valid = false;
                $mgr_pub = getPublicKey('manager_hsse');
                if ($verification_hash_match && $mgr_pub && !empty($formulir['verification_signature'])) {
                    $verification_signature_valid = verifySignature(
                        $verification_hash,
                        $formulir['verification_signature'],
                        $mgr_pub,
                        OPENSSL_ALGO_SHA512
                    );
                }
                $hsse_sig_valid   = false;
                $manajer_sig_valid = false;

                if ($hash_match) {
                    $hsse_pub = getPublicKey('hsse');
                    if ($hsse_pub && $formulir['ttd_hsse_signature']) {
                        $hsse_sig_valid = verifySignature($stored_hash, $formulir['ttd_hsse_signature'], $hsse_pub);
                    }

                    if ($mgr_pub && $formulir['ttd_manajer_signature']) {
                        $manajer_sig_valid = verifySignature($stored_hash, $formulir['ttd_manajer_signature'], $mgr_pub);
                    }
                }

                $status = $formulir['status_approval'];
                $overall_valid = ($status === 'approved')
                    && $hash_match
                    && $hsse_sig_valid
                    && $manajer_sig_valid
                    && $verification_hash_match
                    && $verification_signature_valid;

                $result = [
                    'hash_match'        => $hash_match,
                    'live_hash'         => $live_hash,
                    'stored_hash'       => $stored_hash,
                    'hsse_sig_valid'    => $hsse_sig_valid,
                    'manajer_sig_valid' => $manajer_sig_valid,
                    'verification_hash_match' => $verification_hash_match,
                    'verification_signature_valid' => $verification_signature_valid,
                    'verification_hash' => $verification_hash,
                    'live_verification_hash' => $live_verification_hash,
                    'overall_valid'     => $overall_valid,
                    'status'            => $status,
                ];

                // Catat aksi verifikasi
                logSignatureAction($formulir_id, 'VERIFY',
                    ['id' => null, 'full_name' => 'Publik (QR Scan)', 'username' => 'public', 'role' => 'public'],
                    $live_hash, null,
                    'QR Code scan dari IP: ' . ($_SERVER['REMOTE_ADDR'] ?? '-')
                );
            }
        } catch (Exception $e) {
            error_log("verify-ttd.php error: " . $e->getMessage());
            $log_error = 'Terjadi kesalahan saat memverifikasi. Silakan coba lagi.';
        }
    }
}

$verify_url  = $formulir['verification_url'] ?? ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
             . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
             . ($_SERVER['PHP_SELF'] ?? '/verify-ttd.php')
             . '?token=' . urlencode($token));

$status_labels = [
    'draft'        => ['label' => 'Draft',                     'color' => '#6b7280', 'bg' => '#f3f4f6'],
    'pending_hsse' => ['label' => 'Menunggu TTD HSSE',         'color' => '#d97706', 'bg' => '#fef3c7'],
    'signed_hsse'  => ['label' => 'Sudah TTD HSSE',            'color' => '#2563eb', 'bg' => '#eff6ff'],
    'approved'     => ['label' => 'Disetujui',                  'color' => '#059669', 'bg' => '#dcfce7'],
    'rejected'     => ['label' => 'Ditolak',                   'color' => '#dc2626', 'bg' => '#fee2e2'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Tanda Tangan Digital — E-KIM Pertamina</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f4f8;
            color: #1a2332;
            min-height: 100vh;
        }
        .page-wrap { max-width: 860px; margin: 0 auto; padding: 24px 16px 48px; }

        /* ── Header ── */
        .page-header {
            background: linear-gradient(135deg, #0d2137 0%, #10334d 60%, #1a4a6e 100%);
            color: white;
            padding: 28px 32px;
            border-radius: 16px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .header-logo { width: 52px; height: 52px; background: #ffd700; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 26px; flex-shrink: 0; }
        .header-text h1 { font-size: 20px; margin-bottom: 4px; }
        .header-text p  { font-size: 13px; color: #a7c8dc; }

        /* ── Verdict Banner ── */
        .verdict {
            padding: 22px 28px;
            border-radius: 14px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 18px;
            box-shadow: 0 4px 20px rgba(0,0,0,.08);
        }
        .verdict.valid   { background: #f0fdf4; border: 2px solid #22c55e; }
        .verdict.invalid { background: #fef2f2; border: 2px solid #ef4444; }
        .verdict.pending { background: #fffbeb; border: 2px solid #f59e0b; }
        .verdict-icon { font-size: 48px; flex-shrink: 0; }
        .verdict-title { font-size: 20px; font-weight: 700; margin-bottom: 4px; }
        .verdict.valid   .verdict-title { color: #166534; }
        .verdict.invalid .verdict-title { color: #991b1b; }
        .verdict.pending .verdict-title { color: #92400e; }
        .verdict-sub { font-size: 13px; color: #6b7280; }

        /* ── Card ── */
        .card { background: white; border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,.06); padding: 24px 28px; margin-bottom: 20px; }
        .card h2 { font-size: 15px; color: #1a2332; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid #f3f4f6; display: flex; align-items: center; gap: 8px; }
        .card h2 .icon { font-size: 18px; }

        /* ── Info Grid ── */
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        @media(max-width:580px){ .info-grid{ grid-template-columns:1fr; } }
        .info-item label { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .6px; color: #9ca3af; margin-bottom: 3px; }
        .info-item .val  { font-size: 14px; color: #1a2332; font-weight: 600; }

        /* ── Signature Rows ── */
        .sig-row {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            padding: 16px;
            border-radius: 10px;
            background: #f9fafb;
            margin-bottom: 12px;
        }
        .sig-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0; }
        .sig-icon.ok  { background: #dcfce7; }
        .sig-icon.err { background: #fee2e2; }
        .sig-icon.na  { background: #f3f4f6; }
        .sig-info { flex: 1; }
        .sig-info .role  { font-size: 13px; font-weight: 700; color: #374151; margin-bottom: 3px; }
        .sig-info .signer { font-size: 13px; color: #6b7280; margin-bottom: 4px; }
        .sig-info .ts    { font-size: 11px; color: #9ca3af; }
        .sig-badge { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px; flex-shrink: 0; align-self: flex-start; margin-top: 4px; }
        .sig-badge.ok  { background: #dcfce7; color: #166534; }
        .sig-badge.err { background: #fee2e2; color: #991b1b; }
        .sig-badge.na  { background: #f3f4f6; color: #9ca3af; }

        /* ── Hash block ── */
        .hash-block { font-family: monospace; font-size: 11.5px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; word-break: break-all; color: #334155; line-height: 1.8; }
        .hash-match { color: #166534; font-weight: 600; }
        .hash-miss  { color: #991b1b; font-weight: 600; }

        /* ── Checklist mini table ── */
        .cl-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .cl-table th { background: #f9fafb; padding: 8px 10px; text-align: left; color: #6b7280; font-weight: 600; font-size: 11px; text-transform: uppercase; letter-spacing: .4px; border-bottom: 2px solid #f3f4f6; }
        .cl-table td { padding: 8px 10px; border-bottom: 1px solid #f9fafb; }
        .cl-table tr:last-child td { border-bottom: none; }
        .badge-baik  { background: #dcfce7; color: #166534; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 700; }
        .badge-tidak { background: #fee2e2; color: #991b1b; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 700; }

        /* ── QR Section ── */
        .qr-section { text-align: center; padding: 20px; }
        #qrcode canvas, #qrcode img { border-radius: 8px; }
        .qr-url { font-size: 11px; color: #9ca3af; word-break: break-all; margin-top: 10px; font-family: monospace; }

        /* ── Not found / Error ── */
        .not-found { text-align: center; padding: 60px 20px; }
        .not-found .emoji { font-size: 64px; margin-bottom: 16px; }
        .not-found h2 { font-size: 20px; color: #374151; margin-bottom: 8px; }
        .not-found p  { color: #9ca3af; font-size: 14px; }

        /* ── Status badge ── */
        .status-chip { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; }

        @media print {
            .no-print { display: none !important; }
            body { background: white; }
            .page-header { background: #10334d !important; -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>
<div class="page-wrap">

    <!-- Header -->
    <div class="page-header">
        <div class="header-logo" style="font-size:18px;font-weight:800;color:#10334d;">TTD</div>
        <div class="header-text">
            <h1>Verifikasi Tanda Tangan Digital</h1>
            <p>PRIMA (Pertamina Checklist Mobil Tangki) · PT Pertamina Patra Niaga · RSA-2048 / SHA-256</p>
        </div>
    </div>

    <?php if ($log_error): ?>
    <!-- Error -->
    <div class="not-found">
        <h2>Terjadi Kesalahan</h2>
        <p><?= htmlspecialchars($log_error) ?></p>
    </div>

    <?php elseif ($formulir === null): ?>
    <!-- Token kosong atau tidak ditemukan -->
    <div class="not-found">
        <?php if ($token !== '' || $uuid !== ''): ?>
            <h2>Dokumen Tidak Ditemukan</h2>
            <p>Token QR Code tidak valid atau dokumen sudah dihapus dari sistem.</p>
        <?php else: ?>
            <h2>Halaman Verifikasi Tanda Tangan Digital</h2>
            <p>Silakan scan QR Code pada formulir checklist untuk memverifikasi keasliannya.</p>
        <?php endif; ?>
    </div>

    <?php else:
        $st = $result['status'];
        $sl = $status_labels[$st] ?? ['label' => $st, 'color' => '#6b7280', 'bg' => '#f3f4f6'];

        // Tentukan kelas verdict
        if ($result['overall_valid']) {
            $verdict_class = 'valid';
            $verdict_icon  = '✓';
            $verdict_title = 'Dokumen Valid & Disetujui';
            $verdict_sub   = 'Seluruh tanda tangan terverifikasi. Integritas data terjamin.';
        } elseif ($st === 'rejected') {
            $verdict_class = 'invalid';
            $verdict_icon  = '✗';
            $verdict_title = 'Dokumen Ditolak';
            $verdict_sub   = 'Formulir ini telah ditolak oleh Manajer HSSE.';
        } elseif ((!$result['hash_match'] && $stored_hash !== null)
            || ($st === 'approved' && (!$result['verification_hash_match'] || !$result['verification_signature_valid']))) {
            $verdict_class = 'invalid';
            $verdict_icon  = '!';
            $verdict_title = 'PERINGATAN: Integritas Dokumen Gagal!';
            $verdict_sub   = 'Data dokumen telah berubah setelah ditandatangani. Kemungkinan telah dimanipulasi.';
        } else {
            $verdict_class = 'pending';
            $verdict_icon  = '…';
            $verdict_title = 'Dokumen Belum Selesai Disetujui';
            $verdict_sub   = 'Formulir masih dalam proses penandatanganan.';
        }
    ?>

    <!-- Verdict Banner -->
    <div class="verdict <?= $verdict_class ?>">
        <div class="verdict-icon"><?= $verdict_icon ?></div>
        <div>
            <div class="verdict-title"><?= $verdict_title ?></div>
            <div class="verdict-sub"><?= $verdict_sub ?></div>
        </div>
    </div>

    <!-- Informasi Formulir -->
    <div class="card">
        <h2>Informasi Formulir Checklist</h2>
        <div class="info-grid">
            <div class="info-item">
                <label>Nomor Polisi</label>
                <div class="val"><?= htmlspecialchars($formulir['nomor_polisi'] ?? '-') ?></div>
            </div>
            <div class="info-item">
                <label>Jenis Kendaraan</label>
                <div class="val"><?= htmlspecialchars($formulir['jenis_kendaraan'] ?? '-') ?></div>
            </div>
            <div class="info-item">
                <label>Nama Transport / Perusahaan</label>
                <div class="val"><?= htmlspecialchars($formulir['nama_transport'] ?? '-') ?></div>
            </div>
            <div class="info-item">
                <label>Merk / Tipe Kendaraan</label>
                <div class="val"><?= htmlspecialchars($formulir['merk_mobil'] ?? '-') ?></div>
            </div>
            <div class="info-item">
                <label>Tanggal Pemeriksaan</label>
                <div class="val">
                    <?php
                    $tgl = $formulir['tanggal_pemeriksaan'] ?? '';
                    echo $tgl ? date('d/m/Y', strtotime($tgl)) : '-';
                    ?>
                </div>
            </div>
            <div class="info-item">
                <label>E-KIM Valid Sampai</label>
                <div class="val">
                    <?php
                    $ekv = $formulir['ekim_valid_until'] ?? '';
                    echo $ekv && $ekv !== '0000-00-00' ? date('d/m/Y', strtotime($ekv)) : '-';
                    ?>
                </div>
            </div>
            <div class="info-item">
                <label>Nama Pemeriksa</label>
                <div class="val"><?= htmlspecialchars($formulir['nama_pemeriksa'] ?? '-') ?></div>
            </div>
            <div class="info-item">
                <label>Status Approval</label>
                <div class="val">
                    <span class="status-chip" style="background:<?= $sl['bg'] ?>;color:<?= $sl['color'] ?>;">
                        <?= $sl['label'] ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Tanda Tangan -->
    <div class="card">
        <h2>Status Tanda Tangan Digital</h2>

        <!-- TTD HSSE -->
        <?php
        $hsse_ok = $result['hsse_sig_valid'];
        $hsse_has = !empty($formulir['ttd_hsse_signature']);
        ?>
        <div class="sig-row">
            <div class="sig-icon <?= $hsse_has ? ($hsse_ok ? 'ok' : 'err') : 'na' ?>">
                <?= $hsse_has ? ($hsse_ok ? '✓' : '✗') : '–' ?>
            </div>
            <div class="sig-info">
                <div class="role">Tanda Tangan — Tim HSSE</div>
                <?php if ($hsse_has): ?>
                    <div class="signer"><?= htmlspecialchars($formulir['ttd_hsse_nama'] ?? 'N/A') ?></div>
                    <div class="ts"><?= $formulir['ttd_hsse_timestamp'] ? date('d/m/Y H:i:s', strtotime($formulir['ttd_hsse_timestamp'])) : '-' ?></div>
                <?php else: ?>
                    <div class="signer" style="color:#9ca3af;font-style:italic;">Belum ditandatangani</div>
                <?php endif; ?>
            </div>
            <div>
                <?php if ($hsse_has): ?>
                    <span class="sig-badge <?= $hsse_ok ? 'ok' : 'err' ?>">
                        <?= $hsse_ok ? '✓ VALID' : '✗ INVALID' ?>
                    </span>
                <?php else: ?>
                    <span class="sig-badge na">— BELUM —</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- TTD Manajer -->
        <?php
        $mgr_ok  = $result['manajer_sig_valid'];
        $mgr_has = !empty($formulir['ttd_manajer_signature']);
        ?>
        <div class="sig-row">
            <div class="sig-icon <?= $mgr_has ? ($mgr_ok ? 'ok' : 'err') : 'na' ?>">
                <?= $mgr_has ? ($mgr_ok ? '✓' : '✗') : '–' ?>
            </div>
            <div class="sig-info">
                <div class="role">Tanda Tangan — Manajer HSSE</div>
                <?php if ($mgr_has): ?>
                    <div class="signer"><?= htmlspecialchars($formulir['ttd_manajer_nama'] ?? 'N/A') ?></div>
                    <div class="ts"><?= $formulir['ttd_manajer_timestamp'] ? date('d/m/Y H:i:s', strtotime($formulir['ttd_manajer_timestamp'])) : '-' ?></div>
                <?php else: ?>
                    <div class="signer" style="color:#9ca3af;font-style:italic;">Belum ditandatangani</div>
                <?php endif; ?>
            </div>
            <div>
                <?php if ($mgr_has): ?>
                    <span class="sig-badge <?= $mgr_ok ? 'ok' : 'err' ?>">
                        <?= $mgr_ok ? '✓ VALID' : '✗ INVALID' ?>
                    </span>
                <?php else: ?>
                    <span class="sig-badge na">— BELUM —</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Verifikasi Integritas Hash -->
    <div class="card">
        <h2>Verifikasi Integritas Dokumen (SHA-256)</h2>
        <?php if ($result['stored_hash']): ?>
        <p style="font-size:13px;color:#6b7280;margin-bottom:12px;">
            Hash SHA-256 dihitung dari seluruh data formulir saat dokumen dikunci.
            Perubahan sekecil apapun akan menghasilkan hash yang berbeda.
        </p>
        <div class="hash-block">
            <div style="margin-bottom:8px;">
                <span style="font-size:11px;color:#9ca3af;text-transform:uppercase;letter-spacing:.4px;">Hash Tersimpan (saat submit):</span><br>
                <span><?= htmlspecialchars($result['stored_hash']) ?></span>
            </div>
            <div>
                <span style="font-size:11px;color:#9ca3af;text-transform:uppercase;letter-spacing:.4px;">Hash Saat Ini (dihitung ulang):</span><br>
                <span class="<?= $result['hash_match'] ? 'hash-match' : 'hash-miss' ?>">
                    <?= htmlspecialchars($result['live_hash'] ?? 'Gagal menghitung') ?>
                </span>
            </div>
        </div>
        <p style="margin-top:12px;font-size:13px;font-weight:600;color:<?= $result['hash_match'] ? '#166534' : '#991b1b' ?>;">
            <?= $result['hash_match']
                ? 'Hash cocok — Data dokumen tidak berubah sejak ditandatangani.'
                : 'Hash TIDAK cocok — Data dokumen telah dimodifikasi setelah ditandatangani!' ?>
        </p>
        <?php else: ?>
        <p style="color:#9ca3af;font-style:italic;font-size:13px;">
            Dokumen belum dikunci (masih dalam status Draft). Hash belum tersedia.
        </p>
        <?php endif; ?>
    </div>

    <!-- Proof verifikasi final SHA-512 -->
    <div class="card">
        <h2>Proof Verifikasi Final (SHA-512)</h2>
        <?php if (!empty($result['verification_hash'])): ?>
        <p style="font-size:13px;color:#6b7280;margin-bottom:12px;">
            Hash SHA-512 dan signature RSA dibuat otomatis saat Manajer menyetujui dokumen.
        </p>
        <div class="hash-block">
            <span class="<?= $result['verification_hash_match'] ? 'hash-match' : 'hash-miss' ?>">
                <?= htmlspecialchars($result['verification_hash']) ?>
            </span>
        </div>
        <p style="margin-top:12px;font-size:13px;font-weight:600;color:<?= $result['verification_hash_match'] && $result['verification_signature_valid'] ? '#166534' : '#991b1b' ?>;">
            <?= $result['verification_hash_match'] && $result['verification_signature_valid']
                ? 'Hash SHA-512 VALID · Digital Signature Manager VALID · Integritas Dokumen VALID'
                : 'Integrity Check Failed — Dokumen telah diubah atau proof digital tidak valid.' ?>
        </p>
        <?php if (!empty($formulir['verification_created_at'])): ?>
        <p style="font-size:12px;color:#6b7280;">QR Code dibuat pada: <?= date('d/m/Y H:i:s', strtotime($formulir['verification_created_at'])) ?> WIB</p>
        <?php endif; ?>
        <?php else: ?>
        <p style="color:#9ca3af;font-style:italic;font-size:13px;">QR Code belum tersedia. Proof dibuat setelah approval Manager selesai.</p>
        <?php endif; ?>
    </div>

    <!-- Detail Item Checklist -->
    <?php if (!empty($items)): ?>
    <div class="card">
        <h2>Detail Item Pemeriksaan (<?= count($items) ?> item)</h2>
        <div style="overflow-x:auto;">
        <table class="cl-table">
            <thead>
                <tr>
                    <th style="width:40px;">#</th>
                    <th>Item Pemeriksaan</th>
                    <th style="width:80px;text-align:center;">Status</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
            <tr>
                <td style="color:#9ca3af;"><?= (int)$item['item_number'] ?></td>
                <td><?= htmlspecialchars($item['item_name']) ?></td>
                <td style="text-align:center;">
                    <?php if ($item['is_baik']): ?>
                        <span class="badge-baik">BAIK</span>
                    <?php elseif ($item['is_tidak']): ?>
                        <span class="badge-tidak">TIDAK</span>
                    <?php else: ?>
                        <span style="color:#9ca3af;font-size:11px;">—</span>
                    <?php endif; ?>
                </td>
                <td style="color:#6b7280;font-size:12px;"><?= htmlspecialchars($item['keterangan'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- QR Code -->
    <?php if ($formulir['verification_url'] ?? false): ?>
    <div class="card">
        <h2>QR Code Dokumen Ini</h2>
        <div class="qr-section">
            <div id="qrcode"></div>
            <div class="qr-url"><?= htmlspecialchars($verify_url) ?></div>
            <p style="font-size:12px;color:#9ca3af;margin-top:8px;">
                Scan QR Code ini untuk memverifikasi ulang dokumen kapan saja.
            </p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Footer -->
    <div style="text-align:center;color:#9ca3af;font-size:12px;margin-top:8px;" class="no-print">
        <p>Halaman ini dibuat secara otomatis oleh PRIMA · PT Pertamina Patra Niaga</p>
        <p>Diverifikasi pada: <?= date('d/m/Y H:i:s') ?> WIB</p>
    </div>

    <?php endif; // end formulir found ?>

</div>

<!-- QR Code Library (qrcode.js) -->
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
<script>
<?php if (($formulir['verification_url'] ?? false) && $formulir): ?>
(function () {
    var url = <?= json_encode($verify_url) ?>;
    var container = document.getElementById('qrcode');
    if (!container) return;

    QRCode.toCanvas(url, {
        width: 200,
        margin: 2,
        color: { dark: '#0d2137', light: '#ffffff' }
    }, function (err, canvas) {
        if (!err) {
            canvas.style.borderRadius = '8px';
            container.appendChild(canvas);
        } else {
            // Fallback: link saja
            container.innerHTML = '<p style="color:#9ca3af;font-size:12px;">QR Code tidak dapat dimuat (butuh koneksi internet).</p>';
        }
    });
}());
<?php endif; ?>
</script>
</body>
</html>
