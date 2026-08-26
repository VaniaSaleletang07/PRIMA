<?php
/**
 * Kelola Kendaraan — Admin
 * PRIMA (Pertamina Checklist Mobil Tangki)
 */
require_once 'auth.php';
requireAdmin();
require_once 'config.php';

ensureVehicleTableExists();
ensurePengurusTablesExist();
$user  = getCurrentUser();
$today = date('Y-m-d');
$pengurusList = getPengurusUsersList();

// ── AJAX handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $json   = file_get_contents('php://input');
    $data   = json_decode($json, true) ?? [];
    $action = $data['action'] ?? '';
    try {
        $db = Database::getInstance()->getConnection();

        if ($action === 'save') {
            $id     = !empty($data['id']) ? (int)$data['id'] : null;
            $jenis  = in_array($data['jenis'] ?? '', ['SPBU','INDUSTRI']) ? $data['jenis'] : 'SPBU';
            $nopol  = strtoupper(trim($data['nomor_polisi'] ?? ''));
            $merk   = trim($data['merk_mobil'] ?? '');
            $tahun  = !empty($data['tahun_kendaraan']) ? (int)$data['tahun_kendaraan'] : null;
            $produk = trim($data['produk_kapasitas'] ?? '');
            $trans  = trim($data['nama_transport'] ?? '');
            $email  = trim($data['email_kontraktor'] ?? '');
            $tglPmrk = !empty($data['tanggal_pemeriksaan_terakhir']) ? $data['tanggal_pemeriksaan_terakhir'] : null;
            $ekim   = !empty($data['ekim_valid_until']) ? $data['ekim_valid_until'] : null;
            $status = in_array($data['status'] ?? '', ['AKTIF','TIDAK_AKTIF']) ? $data['status'] : 'AKTIF';
            $usernameTransportir = trim($data['username_transportir'] ?? '');

            if (empty($nopol)) { echo json_encode(['success'=>false,'message'=>'Nomor polisi tidak boleh kosong.']); exit; }
            if (empty($merk))  { echo json_encode(['success'=>false,'message'=>'Merk / Tipe Mobil tidak boleh kosong.']); exit; }
            if (empty($email)) { echo json_encode(['success'=>false,'message'=>'Email Kontraktor / PJ wajib diisi.']); exit; }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { echo json_encode(['success'=>false,'message'=>'Format Email Kontraktor / PJ tidak valid.']); exit; }
            if (empty($usernameTransportir)) { echo json_encode(['success'=>false,'message'=>'Username Akun Transportir wajib dipilih.']); exit; }
            $transportirUser = null;
            foreach ($pengurusList as $pu) {
                if ($pu['username'] === $usernameTransportir) { $transportirUser = $pu; break; }
            }
            if (!$transportirUser) { echo json_encode(['success'=>false,'message'=>'Username Akun Transportir tidak valid atau bukan akun Pengurus Kendaraan aktif.']); exit; }

            if ($id) {
                $chk = $db->prepare("SELECT id FROM kendaraan WHERE nomor_polisi = :np AND id != :id");
                $chk->execute([':np'=>$nopol,':id'=>$id]);
                if ($chk->fetch()) { echo json_encode(['success'=>false,'message'=>'Nomor polisi '.$nopol.' sudah digunakan kendaraan lain.']); exit; }
                $stmt = $db->prepare("UPDATE kendaraan SET jenis=:jenis,nomor_polisi=:np,merk_mobil=:merk,tahun_kendaraan=:tahun,produk_kapasitas=:produk,nama_transport=:trans,email_kontraktor=:email,tanggal_pemeriksaan_terakhir=:tglpmrk,ekim_valid_until=:ekim,status=:status WHERE id=:id");
                $stmt->execute([':jenis'=>$jenis,':np'=>$nopol,':merk'=>$merk,':tahun'=>$tahun,':produk'=>$produk?:null,':trans'=>$trans?:null,':email'=>$email?:null,':tglpmrk'=>$tglPmrk,':ekim'=>$ekim,':status'=>$status,':id'=>$id]);
                linkVehicleToTransportir($nopol, (int)$transportirUser['id'], $trans ?: null);
                logAudit($id,'UPDATE',$user['username'],"Edit kendaraan: $nopol");
                echo json_encode(['success'=>true,'message'=>'Data kendaraan '.$nopol.' berhasil diperbarui.']);
            } else {
                $chk = $db->prepare("SELECT id FROM kendaraan WHERE nomor_polisi = :np");
                $chk->execute([':np'=>$nopol]);
                if ($chk->fetch()) { echo json_encode(['success'=>false,'message'=>'Nomor polisi '.$nopol.' sudah terdaftar.']); exit; }
                $stmt = $db->prepare("INSERT INTO kendaraan (jenis,nomor_polisi,merk_mobil,tahun_kendaraan,produk_kapasitas,nama_transport,email_kontraktor,tanggal_pemeriksaan_terakhir,ekim_valid_until,status,created_by) VALUES (:jenis,:np,:merk,:tahun,:produk,:trans,:email,:tglpmrk,:ekim,:status,:uid)");
                $stmt->execute([':jenis'=>$jenis,':np'=>$nopol,':merk'=>$merk,':tahun'=>$tahun,':produk'=>$produk?:null,':trans'=>$trans?:null,':email'=>$email?:null,':tglpmrk'=>$tglPmrk,':ekim'=>$ekim,':status'=>$status,':uid'=>$user['id']]);
                linkVehicleToTransportir($nopol, (int)$transportirUser['id'], $trans ?: null);
                logAudit(null,'CREATE',$user['username'],"Tambah kendaraan: $nopol ($merk)");
                echo json_encode(['success'=>true,'message'=>'Kendaraan '.$nopol.' berhasil ditambahkan.']);
            }
            exit;
        }

        if ($action === 'delete') {
            $id = (int)($data['id'] ?? 0);
            $chk = $db->prepare("SELECT nomor_polisi FROM kendaraan WHERE id=:id");
            $chk->execute([':id'=>$id]);
            $row = $chk->fetch();
            if (!$row) { echo json_encode(['success'=>false,'message'=>'Data tidak ditemukan.']); exit; }
            $db->prepare("DELETE FROM kendaraan WHERE id=:id")->execute([':id'=>$id]);
            logAudit($id,'DELETE',$user['username'],"Hapus kendaraan: ".$row['nomor_polisi']);
            echo json_encode(['success'=>true,'message'=>'Kendaraan '.$row['nomor_polisi'].' berhasil dihapus.']);
            exit;
        }

    } catch(Exception $e) {
        error_log("Kelola Kendaraan Error: ".$e->getMessage());
        echo json_encode(['success'=>false,'message'=>'Terjadi kesalahan sistem.']);
        exit;
    }
    echo json_encode(['success'=>false,'message'=>'Aksi tidak dikenal.']);
    exit;
}

// ── Load data ─────────────────────────────────────────────────────────────────
$today_load = $today;
try {
    $db  = Database::getInstance()->getConnection();
    $all = $db->query("
        SELECT k.id, k.jenis, k.nomor_polisi, k.merk_mobil, k.tahun_kendaraan, k.produk_kapasitas,
               k.nama_transport, k.email_kontraktor, k.tanggal_pemeriksaan_terakhir,
               k.ekim_valid_until, k.status, k.created_at,
               (SELECT u.username FROM pengurus_kendaraan pk
                JOIN users u ON u.id = pk.user_id
                WHERE pk.nomor_polisi = k.nomor_polisi
                ORDER BY pk.created_at DESC LIMIT 1) AS username_transportir
        FROM kendaraan k
        ORDER BY k.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    $all = [];
}

$total   = count($all);
$aktif   = count(array_filter($all, fn($r) => $r['status'] === 'AKTIF'));
$expired = count(array_filter($all, fn($r) => !empty($r['ekim_valid_until']) && $r['ekim_valid_until'] < $today));

// Dokumen (STNK/Pajak/SIMFIT/Tera/Keur) yang sudah kadaluarsa, per nomor_polisi
$dokExpiredByNopol = [];
try {
    $docLabels = getDocumentExpiryItemLabels();
    foreach (getDocumentExpiryAlerts(3) as $d) {
        if ($d['status_alert'] !== 'SUDAH_EXPIRED') continue;
        $lbl = $docLabels[$d['item_name']] ?? $d['item_name'];
        $dokExpiredByNopol[$d['nomor_polisi']][] = $lbl;
    }
} catch (Exception $e) {
    $dokExpiredByNopol = [];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kendaraan — Admin | E-KIM Pertamina</title>
    <style>
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:"Segoe UI",-apple-system,BlinkMacSystemFont,Arial,sans-serif; background:#f1f4f8; font-size:14px; color:#1a2332; line-height:1.5; }
        .page-wrap { max-width:1280px; margin:0 auto; padding:24px 18px; }
        .page-header { background:white; border:1px solid #dde3ec; border-top:3px solid #c8102e; border-radius:6px; padding:18px 22px; margin-bottom:16px; display:flex; align-items:center; gap:16px; flex-wrap:wrap; }
        .page-header img { height:32px; object-fit:contain; }
        .ph-div { width:1px; height:26px; background:#dde3ec; }
        .ph-text h1 { font-size:17px; font-weight:700; color:#0d1f35; }
        .ph-text p  { font-size:12px; color:#7a8ba0; margin-top:1px; }
        .header-actions { margin-left:auto; display:flex; gap:8px; }
        .stats-bar { display:flex; gap:12px; margin-bottom:16px; flex-wrap:wrap; }
        .stat-card { background:white; border:1px solid #dde3ec; border-radius:6px; padding:14px 20px; flex:1; min-width:120px; }
        .stat-num { font-size:26px; font-weight:700; color:#0d1f35; line-height:1; }
        .stat-lbl { font-size:11px; color:#7a8ba0; text-transform:uppercase; letter-spacing:.5px; margin-top:3px; }
        .stat-card.green .stat-num { color:#15803d; } .stat-card.red .stat-num { color:#c8102e; }
        .toolbar { display:flex; gap:10px; margin-bottom:14px; flex-wrap:wrap; align-items:center; }
        .search-input { flex:1; min-width:200px; padding:8px 12px; border:1px solid #d1d9e0; border-radius:4px; font-size:13px; font-family:inherit; }
        .search-input:focus { outline:none; border-color:#0d1f35; }
        .filter-select { padding:8px 12px; border:1px solid #d1d9e0; border-radius:4px; font-size:13px; font-family:inherit; background:white; }
        #rowCount { font-size:12px; color:#7a8ba0; white-space:nowrap; }
        .btn { display:inline-flex; align-items:center; gap:5px; padding:8px 14px; border-radius:4px; font-size:13px; font-weight:600; cursor:pointer; border:1px solid #d1d9e0; text-decoration:none; font-family:inherit; background:transparent; color:#4a5568; transition:all .15s; white-space:nowrap; }
        .btn:hover { background:#f8fafc; }
        .btn-primary { background:#c8102e; color:white; border-color:#c8102e; }
        .btn-primary:hover { background:#a80e27; border-color:#a80e27; }
        .btn-sm { padding:5px 9px; font-size:12px; } .btn-icon { padding:5px 8px; }
        .table-card { background:white; border:1px solid #dde3ec; border-radius:6px; overflow:hidden; }
        .table-scroll { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; }
        thead th { background:#0d1f35; color:white; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; padding:10px 12px; text-align:left; white-space:nowrap; }
        tbody tr { border-bottom:1px solid #f0f3f7; transition:background .1s; }
        tbody tr:last-child { border-bottom:none; }
        tbody tr:hover { background:#f8fafc; }
        tbody tr.tr-expired { background:#fff5f5; } tbody tr.tr-expired:hover { background:#fee2e2; }
        tbody tr.tr-nonaktif { opacity:.5; }
        td { padding:10px 12px; font-size:13px; vertical-align:middle; }
        .td-nopol { font-weight:700; color:#0d1f35; white-space:nowrap; }
        .td-muted { color:#7a8ba0; font-size:12px; }
        .badge { display:inline-block; padding:2px 8px; border-radius:3px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.3px; }
        .badge-spbu { background:#dbeafe; color:#1d4ed8; } .badge-industri { background:#ede9fe; color:#6d28d9; }
        .badge-ok { background:#dcfce7; color:#15803d; } .badge-expired { background:#fee2e2; color:#991b1b; }
        .badge-aktif { background:#dcfce7; color:#15803d; } .badge-nonaktif { background:#f1f4f8; color:#7a8ba0; }
        .actions-td { white-space:nowrap; display:flex; gap:4px; }
        .empty-row td { text-align:center; padding:40px; color:#9aacbb; }
        .tbl-footer { padding:10px 16px; font-size:12px; color:#9aacbb; border-top:1px solid #f0f3f7; }
        /* Modal */
        .modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.45); display:none; align-items:center; justify-content:center; z-index:1000; padding:16px; }
        .modal-overlay.open { display:flex; }
        .modal { background:white; border-radius:8px; width:100%; max-width:600px; max-height:92vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,.25); }
        .modal-header { padding:16px 20px; border-bottom:1px solid #f0f3f7; display:flex; align-items:center; gap:10px; position:sticky; top:0; background:white; z-index:1; }
        .modal-header h3 { font-size:15px; font-weight:700; color:#0d1f35; }
        .modal-close { margin-left:auto; background:none; border:none; cursor:pointer; font-size:22px; color:#7a8ba0; line-height:1; padding:0 4px; }
        .modal-close:hover { color:#c8102e; }
        .modal-body { padding:22px; }
        .modal-footer { padding:14px 20px; border-top:1px solid #f0f3f7; display:flex; justify-content:flex-end; gap:8px; position:sticky; bottom:0; background:white; }
        /* Form */
        .form-group { margin-bottom:18px; }
        .form-row { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
        .form-label { display:block; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#4a5568; margin-bottom:7px; }
        .form-control { width:100%; padding:11px 13px; border:1px solid #d1d9e0; border-radius:5px; font-size:14px; font-family:inherit; color:#1a2332; background:white; transition:border-color .15s; }
        .form-control:focus { outline:none; border-color:#0d1f35; box-shadow:0 0 0 3px rgba(13,31,53,.07); }
        .form-hint { font-size:11px; color:#9aacbb; margin-top:4px; }
        .form-divider { font-size:11px; font-weight:700; color:#9aacbb; text-transform:uppercase; letter-spacing:.6px; border-bottom:1px solid #f0f3f7; padding-bottom:6px; margin:20px 0 16px; }
        /* Toast */
        #toast { position:fixed; bottom:24px; right:24px; z-index:2000; padding:12px 18px; border-radius:6px; font-size:13px; font-weight:600; display:none; max-width:380px; box-shadow:0 4px 16px rgba(0,0,0,.18); }
        #toast.ok  { background:#dcfce7; color:#15803d; border:1px solid #bbf7d0; }
        #toast.err { background:#fee2e2; color:#991b1b; border:1px solid #fecaca; }
        @media(max-width:600px) { .form-row { grid-template-columns:1fr; } }
    </style>
    <?php include __DIR__ . '/includes/sidebar-styles.php'; ?>
</head>
<body>
<div class="app-shell">
<?php $activeNav = 'kelola'; include __DIR__ . '/includes/sidebar-nav.php'; ?>
<div class="main-wrapper">
    <header class="top-bar">
        <div class="top-bar-left">
            <span class="top-bar-accent"></span>
            <span class="top-bar-title">Kelola Kendaraan</span>
            <span class="top-bar-subtitle">PT Pertamina Patra Niaga</span>
        </div>
        <div class="top-bar-right">
            <div class="top-bar-user-info">
                <span class="top-bar-user-name"><?php echo htmlspecialchars($user['full_name'] ?: $user['username']); ?></span>
                <span class="top-bar-user-role"><?php echo getRoleLabel(); ?></span>
            </div>
            <a href="logout.php" class="btn-topbar-danger">Keluar</a>
        </div>
    </header>
    <div class="page-content">
<div class="page-wrap">

    <div class="page-header">
        <img src="foto/PT_Pertamina_Patra_Niaga.png" alt="Pertamina">
        <div class="ph-div"></div>
        <div class="ph-text">
            <h1>Kelola Kendaraan</h1>
            <p>Tambah, ubah, dan hapus data kendaraan terdaftar dalam sistem</p>
        </div>
        <div class="header-actions">
            <button class="btn btn-primary" onclick="openAdd()">
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Tambah Kendaraan
            </button>
        </div>
    </div>

    <div class="stats-bar">
        <div class="stat-card">
            <div class="stat-num"><?php echo $total; ?></div>
            <div class="stat-lbl">Total Terdaftar</div>
        </div>
        <div class="stat-card green">
            <div class="stat-num"><?php echo $aktif; ?></div>
            <div class="stat-lbl">Aktif</div>
        </div>
        <div class="stat-card red">
            <div class="stat-num"><?php echo $expired; ?></div>
            <div class="stat-lbl">KIM Kedaluwarsa</div>
        </div>
    </div>

    <div class="toolbar">
        <input type="text" id="searchInput" class="search-input" placeholder="Cari nomor polisi, merk, atau nama transport...">
        <select id="filterJenis" class="filter-select">
            <option value="">Semua Jenis</option>
            <option value="SPBU">SPBU</option>
            <option value="INDUSTRI">Industri</option>
        </select>
        <select id="filterStatus" class="filter-select">
            <option value="">Semua Status</option>
            <option value="AKTIF">Aktif</option>
            <option value="TIDAK_AKTIF">Tidak Aktif</option>
        </select>
        <span id="rowCount"></span>
    </div>

    <div class="table-card">
        <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th style="width:40px;">No</th>
                    <th>Jenis</th>
                    <th>Nomor Polisi</th>
                    <th>Merk / Tipe</th>
                    <th>Tahun</th>
                    <th>Nama Transport</th>
                    <th>Produk / Kapasitas</th>
                    <th>Tgl Inspeksi</th>
                    <th>KIM Valid Until</th>
                    <th>Status</th>
                    <th style="width:90px;">Aksi</th>
                </tr>
            </thead>
            <tbody id="tableBody">
            <?php if (empty($all)): ?>
            <tr class="empty-row"><td colspan="11">Belum ada data kendaraan terdaftar.</td></tr>
            <?php else: $no=1; foreach ($all as $row):
                $isExp   = !empty($row['ekim_valid_until']) && $row['ekim_valid_until'] < $today;
                $isNonAkt= $row['status'] === 'TIDAK_AKTIF';
                $jenisKey= strtoupper($row['jenis'] ?? 'SPBU');
                $jenisLbl= getJenisKendaraanLabel($jenisKey);
                $tglI = !empty($row['tanggal_pemeriksaan_terakhir']) ? date('d/m/Y',strtotime($row['tanggal_pemeriksaan_terakhir'])) : '-';
                $tglV = !empty($row['ekim_valid_until'])             ? date('d/m/Y',strtotime($row['ekim_valid_until']))             : '-';
                $dokExpiredList = $dokExpiredByNopol[$row['nomor_polisi']] ?? [];
                $hasDokExpired  = !empty($dokExpiredList);
            ?>
            <tr class="<?php echo $isNonAkt?'tr-nonaktif':(($isExp||$hasDokExpired)?'tr-expired':''); ?>"
                data-id="<?php echo $row['id']; ?>"
                data-jenis="<?php echo htmlspecialchars($jenisKey); ?>"
                data-status="<?php echo htmlspecialchars($row['status']); ?>"
                data-q="<?php echo htmlspecialchars(strtolower(($row['nomor_polisi']??'').' '.($row['merk_mobil']??'').' '.($row['nama_transport']??''))); ?>">
                <td style="color:#9aacbb;font-size:12px;"><?php echo $no++; ?></td>
                <td><span class="badge badge-<?php echo strtolower($jenisKey); ?>"><?php echo htmlspecialchars($jenisLbl); ?></span></td>
                <td class="td-nopol"><?php echo htmlspecialchars($row['nomor_polisi']); ?></td>
                <td><?php echo htmlspecialchars($row['merk_mobil']); ?></td>
                <td class="td-muted"><?php echo $row['tahun_kendaraan'] ?: '-'; ?></td>
                <td><?php echo htmlspecialchars($row['nama_transport'] ?: '-'); ?></td>
                <td class="td-muted"><?php echo htmlspecialchars($row['produk_kapasitas'] ?: '-'); ?></td>
                <td class="td-muted"><?php echo $tglI; ?></td>
                <td style="<?php echo $isExp?'color:#c8102e;font-weight:700;':''; ?>"><?php echo $tglV; ?></td>
                <td><span class="badge badge-<?php echo $isNonAkt?'nonaktif':'aktif'; ?>"><?php echo $isNonAkt?'Tidak Aktif':'Aktif'; ?></span>
                    <?php if ($hasDokExpired): ?>
                    <br><span class="badge badge-expired" title="Dokumen kadaluarsa: <?php echo htmlspecialchars(implode(', ', $dokExpiredList)); ?> — kendaraan tidak dapat beroperasi">&#10006; Dokumen Kadaluarsa</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="actions-td">
                        <button class="btn btn-sm btn-icon" title="Edit" style="color:#1d4ed8;border-color:#bfdbfe;"
                            onclick="openEdit(<?php echo htmlspecialchars(json_encode([
                                'id'                          =>(int)$row['id'],
                                'jenis'                       =>$row['jenis']??'SPBU',
                                'nomor_polisi'                =>$row['nomor_polisi']??'',
                                'merk_mobil'                  =>$row['merk_mobil']??'',
                                'tahun_kendaraan'             =>$row['tahun_kendaraan']??'',
                                'produk_kapasitas'            =>$row['produk_kapasitas']??'',
                                'nama_transport'              =>$row['nama_transport']??'',
                                'email_kontraktor'            =>$row['email_kontraktor']??'',
                                'tanggal_pemeriksaan_terakhir'=>$row['tanggal_pemeriksaan_terakhir']??'',
                                'ekim_valid_until'            =>$row['ekim_valid_until']??'',
                                'status'                      =>$row['status']??'AKTIF',
                                'username_transportir'        =>$row['username_transportir']??'',
                            ]),ENT_QUOTES); ?>)">
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </button>
                        <button class="btn btn-sm btn-icon" title="Hapus" style="color:#c8102e;border-color:#fecaca;"
                            onclick="doDelete(<?php echo (int)$row['id']; ?>,'<?php echo htmlspecialchars($row['nomor_polisi'],ENT_QUOTES); ?>')">
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                        </button>
                    </div>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
        <div class="tbl-footer" id="tblFooter">Menampilkan <?php echo $total; ?> dari <?php echo $total; ?> kendaraan</div>
    </div>

</div>
    </div><!-- /page-content -->
</div><!-- /main-wrapper -->
</div><!-- /app-shell -->

<!-- MODAL -->
<div class="modal-overlay" id="modalOverlay">
  <div class="modal" role="dialog">
    <div class="modal-header">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#c8102e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13" rx="1"/><polygon points="16,8 20,8 23,11 23,16 16,16 16,8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
        <h3 id="modalTitle">Tambah Kendaraan</h3>
        <button class="modal-close" onclick="closeModal()">&times;</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="fId">

      <div class="form-group">
        <label class="form-label">Jenis Kendaraan *</label>
        <select id="fJenis" class="form-control" required>
          <option value="">-- Pilih Jenis --</option>
          <option value="SPBU">SPBU</option>
          <option value="INDUSTRI">Industri</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Nomor Polisi *</label>
        <input type="text" id="fNopol" class="form-control" placeholder="CONTOH: DB 8232 CK" required style="text-transform:uppercase;">
      </div>

      <div class="form-group">
        <label class="form-label">Merk / Tipe Mobil *</label>
        <input type="text" id="fMerk" class="form-control" placeholder="Contoh: Hino 2023" required>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Tahun Kendaraan</label>
          <input type="number" id="fTahun" class="form-control" placeholder="<?php echo date('Y'); ?>" min="1990" max="<?php echo date('Y')+1; ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Produk / Kapasitas</label>
          <input type="text" id="fProduk" class="form-control" placeholder="Contoh: 16 KL">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Nama Transport / Kontraktor</label>
        <input type="text" id="fTransport" class="form-control" placeholder="Nama perusahaan transportasi">
      </div>

      <div class="form-group">
        <label class="form-label">Email Kontraktor / PJ *</label>
        <input type="email" id="fEmail" class="form-control" placeholder="email@perusahaan.com" required>
        <div class="form-hint">Wajib diisi &mdash; digunakan untuk pengiriman notifikasi KIM otomatis</div>
      </div>

      <div class="form-group">
        <label class="form-label">Username Akun Transportir (Pengurus Kendaraan) *</label>
        <select id="fUsernameTransportir" class="form-control" required>
          <option value="">-- Pilih akun pemilik kendaraan --</option>
          <?php foreach ($pengurusList as $pu): ?>
          <option value="<?php echo htmlspecialchars($pu['username']); ?>">
            <?php echo htmlspecialchars($pu['full_name']); ?> (<?php echo htmlspecialchars($pu['username']); ?>)
          </option>
          <?php endforeach; ?>
        </select>
        <div class="form-hint">Wajib dipilih &mdash; notifikasi status penerbitan EKIM kendaraan ini akan tampil di dashboard akun tersebut</div>
      </div>

      <div class="form-divider">Inspeksi &amp; KIM</div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Tanggal Pemeriksaan Terakhir</label>
          <input type="date" id="fTglPmrk" class="form-control">
        </div>
        <div class="form-group">
          <label class="form-label">KIM Valid Until (Berlaku s.d.)</label>
          <input type="date" id="fEkim" class="form-control">
        </div>
      </div>

      <div class="form-group" id="statusGroup" style="display:none;">
        <label class="form-label">Status Kendaraan</label>
        <select id="fStatus" class="form-control">
          <option value="AKTIF">Aktif</option>
          <option value="TIDAK_AKTIF">Tidak Aktif</option>
        </select>
      </div>
    </div>
    <div class="modal-footer">
        <button class="btn" onclick="closeModal()">Batal</button>
        <button class="btn btn-primary" id="btnSave" onclick="submitForm()">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            Simpan
        </button>
    </div>
  </div>
</div>

<div id="toast"></div>
<script>
function applyFilter() {
    const q=document.getElementById('searchInput').value.toLowerCase();
    const jenis=document.getElementById('filterJenis').value;
    const status=document.getElementById('filterStatus').value;
    const rows=document.querySelectorAll('#tableBody tr[data-id]');
    let vis=0,total=rows.length;
    rows.forEach(r=>{
        const show=(!q||r.dataset.q.includes(q))&&(!jenis||r.dataset.jenis===jenis)&&(!status||r.dataset.status===status);
        r.style.display=show?'':'none'; if(show)vis++;
    });
    document.getElementById('tblFooter').textContent=`Menampilkan ${vis} dari ${total} kendaraan`;
    document.getElementById('rowCount').textContent=vis<total?`(${total-vis} disembunyikan)`:'';
}
document.getElementById('searchInput').addEventListener('input',applyFilter);
document.getElementById('filterJenis').addEventListener('change',applyFilter);
document.getElementById('filterStatus').addEventListener('change',applyFilter);
applyFilter();

function resetForm(){
    ['fId','fNopol','fMerk','fTahun','fProduk','fTransport','fEmail','fTglPmrk','fEkim'].forEach(id=>document.getElementById(id).value='');
    document.getElementById('fJenis').value='';
    document.getElementById('fStatus').value='AKTIF';
    document.getElementById('fUsernameTransportir').value='';
}
function openAdd(){
    resetForm();
    document.getElementById('modalTitle').textContent='Tambah Kendaraan Baru';
    document.getElementById('statusGroup').style.display='none';
    document.getElementById('modalOverlay').classList.add('open');
    document.getElementById('fJenis').focus();
}
function openEdit(d){
    resetForm();
    document.getElementById('modalTitle').textContent='Edit Kendaraan — '+d.nomor_polisi;
    document.getElementById('fId').value=d.id;
    document.getElementById('fJenis').value=d.jenis||'SPBU';
    document.getElementById('fNopol').value=d.nomor_polisi||'';
    document.getElementById('fMerk').value=d.merk_mobil||'';
    document.getElementById('fTahun').value=d.tahun_kendaraan||'';
    document.getElementById('fProduk').value=d.produk_kapasitas||'';
    document.getElementById('fTransport').value=d.nama_transport||'';
    document.getElementById('fEmail').value=d.email_kontraktor||'';
    document.getElementById('fTglPmrk').value=d.tanggal_pemeriksaan_terakhir||'';
    document.getElementById('fEkim').value=d.ekim_valid_until||'';
    document.getElementById('fStatus').value=d.status||'AKTIF';
    document.getElementById('fUsernameTransportir').value=d.username_transportir||'';
    document.getElementById('statusGroup').style.display='block';
    document.getElementById('modalOverlay').classList.add('open');
}
function closeModal(){document.getElementById('modalOverlay').classList.remove('open');}
document.getElementById('modalOverlay').addEventListener('click',e=>{if(e.target===e.currentTarget)closeModal();});
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeModal();});

async function submitForm(){
    const jenis=document.getElementById('fJenis').value;
    const nopol=document.getElementById('fNopol').value.toUpperCase().trim();
    const merk=document.getElementById('fMerk').value.trim();
    const email=document.getElementById('fEmail').value.trim();
    const usernameTransportir=document.getElementById('fUsernameTransportir').value;
    if(!jenis){showToast('Pilih Jenis Kendaraan terlebih dahulu.','err');return;}
    if(!nopol){showToast('Nomor Polisi wajib diisi.','err');return;}
    if(!merk){showToast('Merk / Tipe Mobil wajib diisi.','err');return;}
    if(!email){showToast('Email Kontraktor / PJ wajib diisi.','err');return;}
    if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)){showToast('Format Email Kontraktor / PJ tidak valid.','err');return;}
    if(!usernameTransportir){showToast('Username Akun Transportir wajib dipilih.','err');return;}
    const idVal=document.getElementById('fId').value;
    const payload={
        action:'save', id:idVal?parseInt(idVal):null,
        jenis, nomor_polisi:nopol, merk_mobil:merk,
        tahun_kendaraan:document.getElementById('fTahun').value||null,
        produk_kapasitas:document.getElementById('fProduk').value,
        nama_transport:document.getElementById('fTransport').value,
        email_kontraktor:document.getElementById('fEmail').value,
        tanggal_pemeriksaan_terakhir:document.getElementById('fTglPmrk').value||null,
        ekim_valid_until:document.getElementById('fEkim').value||null,
        status:document.getElementById('fStatus').value,
        username_transportir:usernameTransportir,
    };
    const btn=document.getElementById('btnSave');
    btn.disabled=true; btn.textContent='Menyimpan...';
    try{
        const res=await fetch(location.pathname,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
        const json=await res.json();
        if(json.success){showToast(json.message,'ok');closeModal();setTimeout(()=>location.reload(),900);}
        else showToast(json.message||'Gagal menyimpan.','err');
    }catch(err){showToast('Terjadi kesalahan jaringan.','err');}
    finally{btn.disabled=false;btn.innerHTML='<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> Simpan';}
}

async function doDelete(id,nopol){
    if(!confirm(`Yakin ingin menghapus kendaraan "${nopol}"?\nData yang dihapus tidak dapat dikembalikan.`))return;
    try{
        const res=await fetch(location.pathname,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'delete',id})});
        const json=await res.json();
        if(json.success){showToast(json.message,'ok');const row=document.querySelector(`tr[data-id="${id}"]`);if(row)row.remove();applyFilter();}
        else showToast(json.message||'Gagal menghapus.','err');
    }catch(err){showToast('Terjadi kesalahan jaringan.','err');}
}

let toastTimer;
function showToast(msg,type){
    const el=document.getElementById('toast');
    el.textContent=msg;el.className=type;el.style.display='block';
    clearTimeout(toastTimer);toastTimer=setTimeout(()=>{el.style.display='none';},3500);
}
</script>
</body>
</html>

