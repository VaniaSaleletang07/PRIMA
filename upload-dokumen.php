<?php
/**
 * Upload Dokumen Kendaraan — Pengurus Mobil Tangki
 * PRIMA (Pertamina Checklist Mobil Tangki)
 */
require_once 'auth.php';
require_once 'config.php';
requirePengurusOrAdmin();

ensurePengurusTablesExist();

$user     = getCurrentUser();
$nopol    = trim($_GET['nopol'] ?? '');
$msg      = '';
$msgType  = '';

// Validate nopol and access
if (empty($nopol)) {
    header('Location: home.php');
    exit;
}

// Pengurus can only upload for vehicles assigned to them
// Admin can upload for any vehicle
if (isPengurus()) {
    $db = Database::getInstance()->getConnection();
    $chk = $db->prepare("SELECT id FROM pengurus_kendaraan WHERE user_id = :uid AND nomor_polisi = :nopol LIMIT 1");
    $chk->execute([':uid' => $user['id'], ':nopol' => $nopol]);
    if (!$chk->fetch()) {
        header('Location: home.php?error=unauthorized');
        exit;
    }
}

// Ensure upload directory exists
$uploadDir = __DIR__ . '/uploads/dokumen/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// ── Handle POST: upload new document ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $db = Database::getInstance()->getConnection();

    if ($_POST['action'] === 'upload') {
        $jenis          = $_POST['jenis_dokumen']   ?? '';
        $tanggal_berlaku = $_POST['tanggal_berlaku'] ?? null;
        $keterangan      = sanitizeInput($_POST['keterangan'] ?? '');
        $nama_transport  = sanitizeInput($_POST['nama_transport'] ?? '');

        $allowed_jenis = ['STNK','PAJAK','SIM','SURAT_KEUR','SURAT_TERA','KIM','LAINNYA'];
        if (!in_array($jenis, $allowed_jenis)) {
            $msg = 'Jenis dokumen tidak valid.';
            $msgType = 'error';
        } elseif (empty($_FILES['file']['name'])) {
            $msg = 'File dokumen wajib dipilih.';
            $msgType = 'error';
        } else {
            $file      = $_FILES['file'];
            $allowedExt = ['pdf', 'jpg', 'jpeg', 'png'];
            $ext        = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $maxSize    = 5 * 1024 * 1024; // 5 MB

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $msg = 'Upload gagal (kode error: ' . $file['error'] . ').';
                $msgType = 'error';
            } elseif (!in_array($ext, $allowedExt)) {
                $msg = 'Format file tidak didukung. Gunakan PDF, JPG, atau PNG.';
                $msgType = 'error';
            } elseif ($file['size'] > $maxSize) {
                $msg = 'Ukuran file maksimal 5 MB.';
                $msgType = 'error';
            } else {
                // Generate safe filename
                $safeName  = bin2hex(random_bytes(12)) . '.' . $ext;
                $destPath  = $uploadDir . $safeName;

                if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                    $msg = 'Gagal menyimpan file. Hubungi administrator.';
                    $msgType = 'error';
                } else {
                    $relativePath = 'uploads/dokumen/' . $safeName;
                    $stmt = $db->prepare("
                        INSERT INTO dokumen_kendaraan
                            (nomor_polisi, nama_transport, jenis_dokumen, nama_file_asli, file_path, tanggal_berlaku, keterangan, uploaded_by)
                        VALUES
                            (:nopol, :transport, :jenis, :nama_asli, :fpath, :tgl, :ket, :uid)
                    ");
                    $stmt->execute([
                        ':nopol'     => $nopol,
                        ':transport' => $nama_transport ?: null,
                        ':jenis'     => $jenis,
                        ':nama_asli' => $file['name'],
                        ':fpath'     => $relativePath,
                        ':tgl'       => $tanggal_berlaku ?: null,
                        ':ket'       => $keterangan ?: null,
                        ':uid'       => $user['id'],
                    ]);

                    logAudit($user['id'], 'UPLOAD_DOKUMEN', $user['username'],
                        "Upload dokumen $jenis untuk $nopol: " . $file['name']);

                    $msg     = 'Dokumen berhasil diupload dan sedang menunggu verifikasi admin.';
                    $msgType = 'success';
                }
            }
        }
    }

    if ($_POST['action'] === 'delete' && isset($_POST['doc_id'])) {
        $docId = (int)$_POST['doc_id'];
        $chk2  = $db->prepare("SELECT id, file_path, status FROM dokumen_kendaraan WHERE id = :id AND uploaded_by = :uid");
        $chk2->execute([':id' => $docId, ':uid' => $user['id']]);
        $doc = $chk2->fetch();
        if ($doc && $doc['status'] === 'PENDING') {
            // Delete file
            $fp = __DIR__ . '/' . $doc['file_path'];
            if (is_file($fp)) unlink($fp);
            $db->prepare("DELETE FROM dokumen_kendaraan WHERE id = :id")->execute([':id' => $docId]);
            $msg = 'Dokumen berhasil dihapus.';
            $msgType = 'success';
        } else {
            $msg = 'Dokumen tidak dapat dihapus (hanya dokumen PENDING yang bisa dihapus).';
            $msgType = 'error';
        }
    }
}

// Fetch existing documents for this vehicle
$documents   = getDokumenKendaraan($nopol, isPengurus() ? $user['id'] : null);
$nama_transport_kendaraan = '';

// Try to get nama_transport from pengurus_kendaraan
try {
    $db = Database::getInstance()->getConnection();
    $s  = $db->prepare("SELECT nama_transport FROM pengurus_kendaraan WHERE nomor_polisi = :nopol LIMIT 1");
    $s->execute([':nopol' => $nopol]);
    $r  = $s->fetch();
    $nama_transport_kendaraan = $r['nama_transport'] ?? '';
    if (empty($nama_transport_kendaraan)) {
        // fallback: from formulir_checklist
        $s2 = $db->prepare("SELECT nama_transport FROM formulir_checklist WHERE nomor_polisi = :nopol ORDER BY created_at DESC LIMIT 1");
        $s2->execute([':nopol' => $nopol]);
        $r2 = $s2->fetch();
        $nama_transport_kendaraan = $r2['nama_transport'] ?? '';
    }
} catch(Exception $e) {}

// Group documents by jenis
$docByJenis = [];
foreach ($documents as $doc) {
    $docByJenis[$doc['jenis_dokumen']][] = $doc;
}

$jenisList = [
    'STNK'      => 'STNK (Surat Tanda Nomor Kendaraan)',
    'PAJAK'     => 'Pajak Kendaraan',
    'SIM'       => 'SIM Pengemudi (SIMFIT)',
    'SURAT_KEUR'=> 'Surat Keur DLLAAJR',
    'SURAT_TERA'=> 'Surat Tera Metrologi',
    'KIM'       => 'KIM (Kartu Izin Masuk)',
    'LAINNYA'   => 'Dokumen Lainnya',
];

$statusLabel = ['PENDING' => 'Menunggu Verifikasi', 'DISETUJUI' => 'Disetujui', 'DITOLAK' => 'Ditolak'];
$statusColor = ['PENDING' => '#92400e:#fef9c3', 'DISETUJUI' => '#15803d:#dcfce7', 'DITOLAK' => '#991b1b:#fee2e2'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Dokumen — <?php echo htmlspecialchars($nopol); ?> | E-KIM Pertamina</title>
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: "Segoe UI", -apple-system, BlinkMacSystemFont, Arial, sans-serif;
            background: #f1f4f8;
            font-size: 14px;
            color: #1a2332;
            line-height: 1.5;
        }
        .page-wrap { max-width: 960px; margin: 0 auto; padding: 28px 20px; }

        /* Header */
        .page-header {
            background: white;
            border: 1px solid #dde3ec;
            border-top: 3px solid #c8102e;
            border-radius: 6px;
            padding: 20px 24px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        .page-header-logo { height: 36px; object-fit: contain; }
        .page-header-divider { width: 1px; height: 28px; background: #dde3ec; flex-shrink: 0; }
        .header-text h1 { font-size: 18px; font-weight: 700; color: #0d1f35; margin: 0; }
        .header-text p { font-size: 12px; color: #7a8ba0; margin: 2px 0 0; }
        .header-actions { margin-left: auto; display: flex; gap: 8px; }
        .btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 14px; border-radius: 4px; font-size: 13px; font-weight: 600;
            cursor: pointer; border: 1px solid #d1d9e0; text-decoration: none;
            font-family: inherit; transition: all 0.15s; background: transparent; color: #4a5568;
        }
        .btn:hover { background: #f8fafc; border-color: #b0bec8; }
        .btn-primary { background: #c8102e; color: white; border-color: #c8102e; }
        .btn-primary:hover { background: #a80e27; border-color: #a80e27; }
        .btn-sm { padding: 5px 10px; font-size: 12px; }

        /* Alert */
        .alert { padding: 12px 16px; border-radius: 5px; margin-bottom: 18px; font-weight: 500; font-size: 13px; }
        .alert-success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .alert-error   { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        /* Upload form card */
        .card {
            background: white; border: 1px solid #dde3ec; border-radius: 6px;
            margin-bottom: 20px; overflow: hidden;
        }
        .card-header {
            padding: 14px 20px; border-bottom: 1px solid #f0f3f7;
            background: #f8fafc; display: flex; align-items: center; gap: 10px;
        }
        .card-header h2 { font-size: 15px; font-weight: 700; color: #0d1f35; margin: 0; }
        .card-body { padding: 20px; }

        /* Form */
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-group { margin-bottom: 14px; }
        .form-group label { display: block; font-size: 12px; font-weight: 700; color: #4a5568; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px; }
        .form-control {
            width: 100%; padding: 9px 12px; border: 1px solid #d1d9e0;
            border-radius: 4px; font-size: 13px; font-family: inherit; color: #1a2332;
            transition: border-color 0.15s, box-shadow 0.15s; background: white;
        }
        .form-control:focus { outline: none; border-color: #0d1f35; box-shadow: 0 0 0 3px rgba(13,31,53,.08); }
        .form-control-file {
            width: 100%; padding: 8px 12px; border: 1px dashed #d1d9e0;
            border-radius: 4px; font-size: 13px; font-family: inherit; cursor: pointer;
            background: #f8fafc;
        }
        .form-hint { font-size: 11px; color: #9aacbb; margin-top: 4px; }
        .form-full { grid-column: 1 / -1; }

        /* Document list */
        .doc-section { margin-bottom: 16px; }
        .doc-section-title {
            font-size: 12px; font-weight: 700; color: #7a8ba0; text-transform: uppercase;
            letter-spacing: 0.6px; padding: 10px 20px; background: #f8fafc;
            border-bottom: 1px solid #f0f3f7; border-top: 1px solid #f0f3f7;
        }
        .doc-row {
            display: flex; align-items: center; gap: 12px; padding: 12px 20px;
            border-bottom: 1px solid #f0f3f7; flex-wrap: wrap;
        }
        .doc-row:last-child { border-bottom: none; }
        .doc-icon { font-size: 20px; flex-shrink: 0; }
        .doc-info { flex: 1; min-width: 180px; }
        .doc-name { font-size: 13px; font-weight: 600; color: #1a2332; }
        .doc-meta { font-size: 11px; color: #9aacbb; margin-top: 2px; }
        .doc-actions { display: flex; gap: 6px; align-items: center; flex-shrink: 0; }
        .badge-status {
            display: inline-block; padding: 3px 9px; border-radius: 3px;
            font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px;
        }
        .badge-pending   { background: #fef9c3; color: #92400e; }
        .badge-disetujui { background: #dcfce7; color: #15803d; }
        .badge-ditolak   { background: #fee2e2; color: #991b1b; }
        .catatan-ditolak {
            width: 100%; background: #fff5f5; border: 1px solid #fecaca;
            border-radius: 4px; padding: 8px 12px; font-size: 12px; color: #991b1b; margin-top: 6px;
        }
        .empty-type {
            padding: 12px 20px; font-size: 12px; color: #9aacbb; font-style: italic;
        }

        /* Summary bar */
        .summary-bar {
            display: flex; gap: 12px; flex-wrap: wrap;
            background: #0d1f35; border-radius: 6px; padding: 14px 20px; margin-bottom: 20px;
        }
        .summary-item { text-align: center; flex: 1; min-width: 80px; }
        .summary-num { font-size: 22px; font-weight: 700; color: white; line-height: 1; }
        .summary-lbl { font-size: 10px; color: rgba(255,255,255,.6); text-transform: uppercase; letter-spacing: 0.5px; margin-top: 3px; }

        @media (max-width: 640px) {
            .form-grid { grid-template-columns: 1fr; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .header-actions { margin-left: 0; }
        }

        /* ── Camera feature ─────────────────────────────────────────── */
        .tab-bar { display: flex; gap: 6px; margin-bottom: 14px; }
        .tab-btn {
            flex: 1; padding: 9px 14px; border: 1px solid #d1d9e0; background: #f8fafc;
            font-size: 13px; font-weight: 600; cursor: pointer; font-family: inherit;
            color: #4a5568; border-radius: 4px; display: inline-flex; align-items: center;
            justify-content: center; gap: 6px; transition: all 0.15s;
        }
        .tab-btn.active { background: #0d1f35; color: white; border-color: #0d1f35; }
        .tab-btn:hover:not(.active) { background: #edf2f7; }
        .camera-wrap {
            position: relative; background: #000; border-radius: 6px;
            overflow: hidden; width: 100%;
        }
        #camera-video {
            width: 100%; display: block; max-height: 320px;
            object-fit: cover; background: #000;
        }
        #capture-canvas { display: none; }
        #captured-img {
            width: 100%; display: block; max-height: 320px;
            object-fit: contain; background: #0d1f35; border-radius: 6px;
        }
        .camera-btn-bar { display: flex; gap: 10px; margin-top: 12px; flex-wrap: wrap; align-items: center; }
        .camera-status {
            font-size: 12px; font-weight: 600; margin-top: 8px;
            padding: 6px 10px; border-radius: 4px;
        }
        .camera-status.ok { background: #dcfce7; color: #15803d; }
        .camera-status.waiting { background: #fef9c3; color: #92400e; }
    </style>
</head>
<body>
<div class="page-wrap">

    <!-- Header -->
    <div class="page-header">
        <img src="foto/PT_Pertamina_Patra_Niaga.png" alt="Pertamina" class="page-header-logo">
        <div class="page-header-divider"></div>
        <div class="header-text">
            <h1>Upload Dokumen Kendaraan</h1>
            <p><?php echo htmlspecialchars($nopol); ?>
               <?php if ($nama_transport_kendaraan): ?>&mdash; <?php echo htmlspecialchars($nama_transport_kendaraan); ?><?php endif; ?>
            </p>
        </div>
        <div class="header-actions">
            <a href="home.php" class="btn">&#8592; Kembali</a>
        </div>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-<?php echo $msgType; ?>"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <!-- Summary bar -->
    <?php
    $totAll  = count($documents);
    $totPend = count(array_filter($documents, fn($d) => $d['status'] === 'PENDING'));
    $totOk   = count(array_filter($documents, fn($d) => $d['status'] === 'DISETUJUI'));
    $totTolak= count(array_filter($documents, fn($d) => $d['status'] === 'DITOLAK'));
    ?>
    <div class="summary-bar">
        <div class="summary-item">
            <div class="summary-num"><?php echo $totAll; ?></div>
            <div class="summary-lbl">Total Dokumen</div>
        </div>
        <div class="summary-item">
            <div class="summary-num" style="color:#fef08a"><?php echo $totPend; ?></div>
            <div class="summary-lbl">Menunggu</div>
        </div>
        <div class="summary-item">
            <div class="summary-num" style="color:#86efac"><?php echo $totOk; ?></div>
            <div class="summary-lbl">Disetujui</div>
        </div>
        <div class="summary-item">
            <div class="summary-num" style="color:#fca5a5"><?php echo $totTolak; ?></div>
            <div class="summary-lbl">Ditolak</div>
        </div>
        <div class="summary-item" style="flex:2;text-align:left;">
            <div style="font-size:12px;color:rgba(255,255,255,.7);">Inspeksi Semester Berikutnya</div>
            <div style="font-size:14px;color:white;font-weight:700;margin-top:4px;" id="nextSemesterDate">—</div>
        </div>
    </div>

    <!-- Upload Form -->
    <div class="card">
        <div class="card-header">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#c8102e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            <h2>Upload Dokumen Baru</h2>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data" id="upload-form">
                <input type="hidden" name="action" value="upload">
                <input type="hidden" name="nama_transport" value="<?php echo htmlspecialchars($nama_transport_kendaraan); ?>">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Jenis Dokumen *</label>
                        <select name="jenis_dokumen" class="form-control" required>
                            <option value="">— Pilih Jenis —</option>
                            <?php foreach ($jenisList as $k => $v): ?>
                            <option value="<?php echo $k; ?>"><?php echo $v; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Berlaku Sampai</label>
                        <input type="date" name="tanggal_berlaku" class="form-control">
                        <div class="form-hint">Opsional — isi jika dokumen memiliki masa berlaku</div>
                    </div>
                    <div class="form-group form-full">
                        <label>Sumber Dokumen *</label>
                        <!-- Tab toggle: File vs Camera -->
                        <div class="tab-bar">
                            <button type="button" class="tab-btn active" id="tab-upload" onclick="showUploadMode()">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                Upload File
                            </button>
                            <button type="button" class="tab-btn" id="tab-camera" onclick="showCameraMode()">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                                Pakai Kamera
                            </button>
                        </div>

                        <!-- Mode: Upload File -->
                        <div id="upload-mode">
                            <input type="file" name="file" id="file-input" class="form-control-file"
                                   accept=".pdf,.jpg,.jpeg,.png" required>
                            <div class="form-hint">PDF, JPG, PNG &mdash; maks. 5 MB</div>
                        </div>

                        <!-- Mode: Kamera -->
                        <div id="camera-mode" style="display:none;">
                            <div class="camera-wrap" id="camera-preview">
                                <video id="camera-video" autoplay playsinline></video>
                            </div>
                            <div id="capture-result" style="display:none;">
                                <img id="captured-img" alt="Foto dokumen">
                            </div>
                            <canvas id="capture-canvas"></canvas>
                            <div class="camera-btn-bar">
                                <button type="button" class="btn btn-primary" id="btn-capture" onclick="capturePhoto()">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="13" r="4"/><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/></svg>
                                    Ambil Foto
                                </button>
                                <button type="button" class="btn" id="btn-retake" onclick="retakePhoto()" style="display:none;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-4.13"/></svg>
                                    Ambil Ulang
                                </button>
                            </div>
                            <div id="camera-status" class="camera-status waiting" style="display:none;"></div>
                            <div class="form-hint" style="margin-top:6px;">Arahkan kamera ke dokumen, pastikan tulisan terlihat jelas, lalu klik <strong>Ambil Foto</strong>.</div>
                        </div>
                    </div>
                    <div class="form-group form-full">
                        <label>Keterangan <span style="font-weight:400;text-transform:none;">(opsional)</span></label>
                        <textarea name="keterangan" class="form-control" rows="2" placeholder="Misal: Perpanjangan STNK 2026, berlaku s.d. Desember 2027"></textarea>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    Upload Dokumen
                </button>
            </form>
        </div>
    </div>

    <!-- Existing Documents -->
    <div class="card">
        <div class="card-header">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#0d1f35" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            <h2>Dokumen yang Sudah Diupload</h2>
        </div>

        <?php if (empty($documents)): ?>
        <div style="padding:32px;text-align:center;color:#9aacbb;font-size:13px;">Belum ada dokumen yang diupload untuk kendaraan ini.</div>
        <?php else: ?>

        <?php foreach ($jenisList as $jenisKey => $jenisLabel):
            if (!isset($docByJenis[$jenisKey])) continue;
        ?>
        <div class="doc-section-title"><?php echo $jenisLabel; ?></div>
        <?php foreach ($docByJenis[$jenisKey] as $doc):
            $stClass = strtolower($doc['status']);
        ?>
        <div class="doc-row">
            <div class="doc-icon">
                <?php echo ($doc['status'] === 'DISETUJUI') ? '✓' : (($doc['status'] === 'DITOLAK') ? '✗' : '…'); ?>
            </div>
            <div class="doc-info">
                <div class="doc-name"><?php echo htmlspecialchars($doc['nama_file_asli']); ?></div>
                <div class="doc-meta">
                    Upload: <?php echo date('d/m/Y H:i', strtotime($doc['created_at'])); ?>
                    <?php if ($doc['tanggal_berlaku']): ?>
                    &bull; Berlaku s.d. <?php echo date('d/m/Y', strtotime($doc['tanggal_berlaku'])); ?>
                    <?php endif; ?>
                    <?php if ($doc['keterangan']): ?>
                    &bull; <?php echo htmlspecialchars($doc['keterangan']); ?>
                    <?php endif; ?>
                    <?php if ($doc['reviewer_name'] && $doc['status'] !== 'PENDING'): ?>
                    &bull; Direview oleh: <?php echo htmlspecialchars($doc['reviewer_name']); ?>
                    <?php endif; ?>
                </div>
                <?php if ($doc['status'] === 'DITOLAK' && $doc['catatan_admin']): ?>
                <div class="catatan-ditolak">
                    <strong>Catatan Admin:</strong> <?php echo htmlspecialchars($doc['catatan_admin']); ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="doc-actions">
                <span class="badge-status badge-<?php echo $stClass; ?>"><?php echo $statusLabel[$doc['status']]; ?></span>
                <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank" class="btn btn-sm" title="Lihat file">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    Lihat
                </a>
                <?php if ($doc['status'] === 'PENDING'): ?>
                <form method="POST" style="display:inline;" onsubmit="return confirm('Yakin ingin menghapus dokumen ini?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="doc_id" value="<?php echo $doc['id']; ?>">
                    <button type="submit" class="btn btn-sm" style="border-color:#dc2626;color:#dc2626;" title="Hapus dokumen pending">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                        Hapus
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endforeach; ?>

        <?php endif; ?>
    </div><!-- /card -->

    <p style="font-size:12px;color:#9aacbb;text-align:center;padding-top:8px;">
        Dokumen yang diupload akan diverifikasi oleh administrator. Status akan diperbarui setelah verifikasi selesai.
    </p>

</div><!-- /page-wrap -->
<script>
    // ── Semester countdown ───────────────────────────────────────────
    (function() {
        const now  = new Date(); now.setHours(0,0,0,0);
        const year = now.getFullYear();
        const cands = [new Date(year,1,25), new Date(year,7,25), new Date(year+1,1,25)];
        const next  = cands.find(d => d >= now) || cands[cands.length-1];
        const diff  = Math.round((next - now)/(1000*60*60*24));
        const opts  = {day:'numeric',month:'long',year:'numeric'};
        const el    = document.getElementById('nextSemesterDate');
        if (el) el.textContent = next.toLocaleDateString('id-ID', opts) + (diff === 0 ? ' (HARI INI)' : ' (' + diff + ' hari lagi)');
    })();

    // ── Camera feature ───────────────────────────────────────────────
    let camStream     = null;
    let capturedFile  = null;

    function showUploadMode() {
        document.getElementById('upload-mode').style.display   = 'block';
        document.getElementById('camera-mode').style.display   = 'none';
        document.getElementById('file-input').required         = true;
        document.getElementById('tab-upload').classList.add('active');
        document.getElementById('tab-camera').classList.remove('active');
        stopCamera();
        capturedFile = null;
    }

    function showCameraMode() {
        document.getElementById('upload-mode').style.display   = 'none';
        document.getElementById('camera-mode').style.display   = 'block';
        document.getElementById('file-input').required         = false;
        document.getElementById('file-input').value            = '';
        document.getElementById('tab-upload').classList.remove('active');
        document.getElementById('tab-camera').classList.add('active');
        startCamera();
    }

    async function startCamera() {
        // Reset UI
        document.getElementById('camera-preview').style.display  = 'block';
        document.getElementById('capture-result').style.display  = 'none';
        document.getElementById('btn-capture').style.display     = 'inline-flex';
        document.getElementById('btn-retake').style.display      = 'none';
        document.getElementById('camera-status').style.display   = 'none';
        capturedFile = null;

        try {
            // Prefer rear camera on mobile
            camStream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } },
                audio: false
            });
            const vid = document.getElementById('camera-video');
            vid.srcObject = camStream;
            vid.play();
        } catch (err) {
            const status = document.getElementById('camera-status');
            status.style.display = 'block';
            status.className     = 'camera-status';
            status.style.background = '#fee2e2';
            status.style.color      = '#991b1b';
            if (err.name === 'NotAllowedError') {
                status.textContent = 'Akses kamera ditolak. Izinkan akses kamera di pengaturan browser Anda.';
            } else if (err.name === 'NotFoundError') {
                status.textContent = 'Kamera tidak ditemukan. Gunakan mode Upload File.';
            } else {
                status.textContent = 'Tidak dapat membuka kamera: ' + err.message;
            }
        }
    }

    function stopCamera() {
        if (camStream) {
            camStream.getTracks().forEach(t => t.stop());
            camStream = null;
        }
    }

    function capturePhoto() {
        const video  = document.getElementById('camera-video');
        const canvas = document.getElementById('capture-canvas');
        canvas.width  = video.videoWidth  || 1280;
        canvas.height = video.videoHeight || 720;
        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);

        stopCamera();
        document.getElementById('camera-preview').style.display = 'none';
        document.getElementById('capture-result').style.display = 'block';
        document.getElementById('captured-img').src             = canvas.toDataURL('image/jpeg', 0.92);
        document.getElementById('btn-capture').style.display    = 'none';
        document.getElementById('btn-retake').style.display     = 'inline-flex';

        // Convert canvas → File object
        canvas.toBlob(function(blob) {
            const ts   = new Date().toISOString().replace(/[:.]/g,'-').slice(0,19);
            capturedFile = new File([blob], 'foto_dokumen_' + ts + '.jpg', { type: 'image/jpeg' });
            const status  = document.getElementById('camera-status');
            status.style.display   = 'block';
            status.className       = 'camera-status ok';
            status.style.background = '';
            status.style.color      = '';
            status.textContent      = '✓ Foto siap diupload — ' + capturedFile.name;
        }, 'image/jpeg', 0.92);
    }

    function retakePhoto() {
        capturedFile = null;
        document.getElementById('capture-result').style.display  = 'none';
        document.getElementById('camera-status').style.display   = 'none';
        document.getElementById('btn-retake').style.display      = 'none';
        startCamera();
    }

    // Intercept form submit — attach camera file to the named file input
    document.getElementById('upload-form').addEventListener('submit', function(e) {
        const cameraMode = document.getElementById('camera-mode');
        if (cameraMode.style.display !== 'none') {
            if (!capturedFile) {
                e.preventDefault();
                alert('Belum ada foto yang diambil. Klik "Ambil Foto" terlebih dahulu.');
                return;
            }
            try {
                const dt = new DataTransfer();
                dt.items.add(capturedFile);
                document.getElementById('file-input').files    = dt.files;
                document.getElementById('file-input').required = false; // file set via JS
            } catch (ex) {
                e.preventDefault();
                alert('Browser Anda tidak mendukung fitur ini. Gunakan mode Upload File.');
            }
        }
    });

    // Clean up camera when leaving page
    window.addEventListener('beforeunload', stopCamera);
</script>
</body>
</html>
