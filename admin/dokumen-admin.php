<?php
/**
 * Manajemen Dokumen Kendaraan — Admin
 * Review dokumen yang diupload pengurus, dan assign kendaraan ke pengurus
 */
require_once '../auth/auth.php';
require_once '../config/config.php';
requireAdmin();

ensurePengurusTablesExist();

$user = getCurrentUser();
$db   = Database::getInstance()->getConnection();
$msg  = '';
$msgType = '';

// ── Handle AJAX/POST actions ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $action = $input['action'] ?? '';

    if ($action === 'review_doc') {
        $docId  = (int)($input['doc_id'] ?? 0);
        $status = $input['status'] ?? '';  // DISETUJUI or DITOLAK
        $catatan = sanitizeInput($input['catatan'] ?? '');

        if (!in_array($status, ['DISETUJUI', 'DITOLAK']) || !$docId) {
            echo json_encode(['success' => false, 'message' => 'Input tidak valid']);
            exit;
        }
        $stmt = $db->prepare("UPDATE dokumen_kendaraan SET status=:s, catatan_admin=:c, reviewed_by=:r, reviewed_at=NOW() WHERE id=:id");
        $stmt->execute([':s' => $status, ':c' => $catatan ?: null, ':r' => $user['id'], ':id' => $docId]);
        logAudit($user['id'], 'REVIEW_DOKUMEN', $user['username'], "Doc $docId → $status");
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'assign_vehicle') {
        $pengurusId    = (int)($input['user_id']       ?? 0);
        $nopol         = sanitizeInput($input['nomor_polisi'] ?? '');
        $namaTransport = sanitizeInput($input['nama_transport'] ?? '');

        if (!$pengurusId || empty($nopol)) {
            echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
            exit;
        }
        // Verify target is a pengurus
        $chk = $db->prepare("SELECT id FROM users WHERE id=:id AND role='pengurus'");
        $chk->execute([':id' => $pengurusId]);
        if (!$chk->fetch()) {
            echo json_encode(['success' => false, 'message' => 'User bukan pengurus']);
            exit;
        }
        $ins = $db->prepare("INSERT IGNORE INTO pengurus_kendaraan (user_id, nomor_polisi, nama_transport) VALUES (:uid,:nopol,:nt)");
        $ins->execute([':uid' => $pengurusId, ':nopol' => $nopol, ':nt' => $namaTransport ?: null]);
        logAudit($user['id'], 'ASSIGN_VEHICLE', $user['username'], "Assign $nopol → user $pengurusId");
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'unassign_vehicle') {
        $pkId = (int)($input['pk_id'] ?? 0);
        if ($pkId) {
            $db->prepare("DELETE FROM pengurus_kendaraan WHERE id=:id")->execute([':id' => $pkId]);
        }
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

// ── Fetch data for display ────────────────────────────────────────────────────
// All pending documents
$pendingDocs = $db->query("
    SELECT dk.*, u.full_name as uploader_name, u.email as uploader_email
    FROM dokumen_kendaraan dk
    LEFT JOIN users u ON dk.uploaded_by = u.id
    WHERE dk.status = 'PENDING'
    ORDER BY dk.created_at ASC
")->fetchAll();

// All documents (recent 100)
$allDocs = $db->query("
    SELECT dk.*, u.full_name as uploader_name, r.full_name as reviewer_name
    FROM dokumen_kendaraan dk
    LEFT JOIN users u ON dk.uploaded_by = u.id
    LEFT JOIN users r ON dk.reviewed_by = r.id
    ORDER BY dk.created_at DESC
    LIMIT 100
")->fetchAll();

// All pengurus users
$pengurusList = $db->query("
    SELECT u.id, u.full_name, u.username, u.email,
           COUNT(pk.id) as vehicle_count
    FROM users u
    LEFT JOIN pengurus_kendaraan pk ON pk.user_id = u.id
    WHERE u.role = 'pengurus' AND u.status = 'active'
    GROUP BY u.id
    ORDER BY u.full_name
")->fetchAll();

// All assignments
$assignments = $db->query("
    SELECT pk.*, u.full_name as pengurus_name, u.username
    FROM pengurus_kendaraan pk
    JOIN users u ON pk.user_id = u.id
    ORDER BY u.full_name, pk.nomor_polisi
")->fetchAll();

// Vehicle list from kendaraan table (for assign dropdown)
$vehicleOptions = $db->query("SELECT nomor_polisi, nama_transport FROM kendaraan WHERE status='AKTIF' ORDER BY nomor_polisi")->fetchAll();
if (empty($vehicleOptions)) {
    // fallback to formulir_checklist
    $vehicleOptions = $db->query("SELECT DISTINCT nomor_polisi, nama_transport FROM formulir_checklist ORDER BY nomor_polisi")->fetchAll();
}

$jenisList = [
    'STNK'=>'STNK', 'PAJAK'=>'Pajak', 'SIM'=>'SIM', 'SURAT_KEUR'=>'Surat Keur',
    'SURAT_TERA'=>'Surat Tera', 'KIM'=>'KIM', 'LAINNYA'=>'Lainnya'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Dokumen Pengurus | Admin E-KIM</title>
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Segoe UI", Arial, sans-serif; background: #f1f4f8; font-size: 14px; color: #1a2332; line-height: 1.5; }
        .page-wrap { max-width: 1200px; margin: 0 auto; padding: 28px 20px; }

        .page-header { background: white; border: 1px solid #dde3ec; border-top: 3px solid #c8102e; border-radius: 6px; padding: 18px 24px; margin-bottom: 20px; display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
        .page-header-logo { height: 34px; object-fit: contain; }
        .page-header-divider { width: 1px; height: 26px; background: #dde3ec; }
        .header-text h1 { font-size: 17px; font-weight: 700; color: #0d1f35; margin: 0; }
        .header-text p  { font-size: 12px; color: #7a8ba0; margin: 2px 0 0; }
        .header-actions { margin-left: auto; display: flex; gap: 8px; }

        .btn { display: inline-flex; align-items: center; gap: 5px; padding: 7px 14px; border-radius: 4px; font-size: 13px; font-weight: 600; cursor: pointer; border: 1px solid #d1d9e0; text-decoration: none; font-family: inherit; transition: all .15s; background: transparent; color: #4a5568; }
        .btn:hover { background: #f8fafc; }
        .btn-primary { background: #c8102e; color: white; border-color: #c8102e; }
        .btn-primary:hover { background: #a80e27; border-color: #a80e27; }
        .btn-success { background: #15803d; color: white; border-color: #15803d; }
        .btn-success:hover { background: #166534; }
        .btn-danger  { background: #dc2626; color: white; border-color: #dc2626; }
        .btn-danger:hover  { background: #b91c1c; }
        .btn-sm { padding: 5px 10px; font-size: 12px; }

        /* Tabs */
        .tabs { display: flex; border-bottom: 2px solid #dde3ec; margin-bottom: 20px; }
        .tab-btn { padding: 10px 20px; font-size: 13px; font-weight: 600; cursor: pointer; border: none; background: transparent; color: #7a8ba0; font-family: inherit; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: color .15s; }
        .tab-btn.active { color: #c8102e; border-bottom-color: #c8102e; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }

        /* Cards */
        .card { background: white; border: 1px solid #dde3ec; border-radius: 6px; margin-bottom: 18px; overflow: hidden; }
        .card-header { padding: 14px 20px; border-bottom: 1px solid #f0f3f7; background: #f8fafc; display: flex; align-items: center; justify-content: space-between; gap: 10px; }
        .card-header h2 { font-size: 14px; font-weight: 700; color: #0d1f35; margin: 0; }
        .card-body { padding: 20px; }

        /* Table */
        table { width: 100%; border-collapse: collapse; }
        th { background: #0d1f35; color: rgba(255,255,255,.85); padding: 10px 12px; text-align: left; font-weight: 600; font-size: 11px; text-transform: uppercase; letter-spacing: .5px; }
        td { padding: 10px 12px; border-bottom: 1px solid #f0f3f7; font-size: 13px; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f8fafc; }

        /* Badges */
        .badge { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .3px; }
        .badge-pending   { background: #fef9c3; color: #92400e; }
        .badge-disetujui { background: #dcfce7; color: #15803d; }
        .badge-ditolak   { background: #fee2e2; color: #991b1b; }

        /* Form */
        .form-row { display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end; }
        .form-group { flex: 1; min-width: 160px; }
        .form-group label { display: block; font-size: 11px; font-weight: 700; color: #4a5568; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 4px; }
        .form-control { width: 100%; padding: 8px 11px; border: 1px solid #d1d9e0; border-radius: 4px; font-size: 13px; font-family: inherit; }
        .form-control:focus { outline: none; border-color: #0d1f35; box-shadow: 0 0 0 3px rgba(13,31,53,.08); }

        /* Empty */
        .empty-state { padding: 36px; text-align: center; color: #9aacbb; font-size: 13px; }

        /* Modal overlay */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal-overlay.open { display: flex; }
        .modal { background: white; border-radius: 8px; padding: 28px; width: 440px; max-width: 95vw; box-shadow: 0 20px 60px rgba(0,0,0,.3); }
        .modal h3 { font-size: 16px; font-weight: 700; color: #0d1f35; margin-bottom: 16px; }
        .modal-actions { display: flex; gap: 10px; margin-top: 20px; justify-content: flex-end; }

        .alert { padding: 12px 16px; border-radius: 5px; margin-bottom: 16px; font-weight: 500; font-size: 13px; }
        .alert-success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .alert-error   { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    </style>
</head>
<body>
<div class="page-wrap">

    <div class="page-header">
        <img src="foto/PT_Pertamina_Patra_Niaga.png" alt="Pertamina" class="page-header-logo">
        <div class="page-header-divider"></div>
        <div class="header-text">
            <h1>Manajemen Dokumen Pengurus</h1>
            <p>Review dokumen kendaraan &amp; kelola akses pengurus mobil tangki</p>
        </div>
        <div class="header-actions">
            <a href="../home.php" class="btn">&#8592; Dashboard</a>
        </div>
    </div>

    <div id="alertBox" class="alert" style="display:none;"></div>

    <!-- Tabs -->
    <div class="tabs">
        <button class="tab-btn active" onclick="switchTab('pending')">
            Dokumen Pending <span id="pendingBadge" style="background:#c8102e;color:white;border-radius:10px;padding:1px 7px;font-size:11px;margin-left:5px;"><?php echo count($pendingDocs); ?></span>
        </button>
        <button class="tab-btn" onclick="switchTab('all')">Semua Dokumen</button>
        <button class="tab-btn" onclick="switchTab('pengurus')">Pengurus &amp; Kendaraan</button>
    </div>

    <!-- Tab: Pending Documents -->
    <div id="tab-pending" class="tab-content active">
        <div class="card">
            <div class="card-header">
                <h2>Dokumen Menunggu Verifikasi (<?php echo count($pendingDocs); ?>)</h2>
            </div>
            <?php if (empty($pendingDocs)): ?>
            <div class="empty-state">&#10003; Tidak ada dokumen yang menunggu verifikasi.</div>
            <?php else: ?>
            <table>
                <thead><tr>
                    <th>Kendaraan</th>
                    <th>Jenis Dokumen</th>
                    <th>File</th>
                    <th>Berlaku s.d.</th>
                    <th>Pengurus</th>
                    <th>Waktu Upload</th>
                    <th style="width:140px">Aksi</th>
                </tr></thead>
                <tbody>
                <?php foreach ($pendingDocs as $doc): ?>
                <tr id="docRow<?php echo $doc['id']; ?>">
                    <td><strong><?php echo htmlspecialchars($doc['nomor_polisi']); ?></strong><br>
                        <small style="color:#7a8ba0"><?php echo htmlspecialchars($doc['nama_transport'] ?? ''); ?></small></td>
                    <td><span class="badge badge-pending"><?php echo $jenisList[$doc['jenis_dokumen']] ?? $doc['jenis_dokumen']; ?></span></td>
                    <td><a href="<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank" class="btn btn-sm">Lihat</a></td>
                    <td><?php echo $doc['tanggal_berlaku'] ? date('d/m/Y', strtotime($doc['tanggal_berlaku'])) : '-'; ?></td>
                    <td><?php echo htmlspecialchars($doc['uploader_name'] ?? '-'); ?><br>
                        <small style="color:#7a8ba0"><?php echo htmlspecialchars($doc['uploader_email'] ?? ''); ?></small></td>
                    <td><?php echo date('d/m/Y H:i', strtotime($doc['created_at'])); ?></td>
                    <td>
                        <button class="btn btn-success btn-sm" onclick="reviewDoc(<?php echo $doc['id']; ?>, 'DISETUJUI')">&#10003; Setujui</button>
                        <button class="btn btn-danger  btn-sm" onclick="openRejectModal(<?php echo $doc['id']; ?>)">&#10007; Tolak</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tab: All Documents -->
    <div id="tab-all" class="tab-content">
        <div class="card">
            <div class="card-header">
                <h2>Semua Dokumen (<?php echo count($allDocs); ?> terbaru)</h2>
            </div>
            <?php if (empty($allDocs)): ?>
            <div class="empty-state">Belum ada dokumen yang diupload.</div>
            <?php else: ?>
            <div style="overflow-x:auto;">
            <table>
                <thead><tr>
                    <th>Kendaraan</th>
                    <th>Jenis</th>
                    <th>File</th>
                    <th>Berlaku s.d.</th>
                    <th>Status</th>
                    <th>Pengurus</th>
                    <th>Reviewer</th>
                    <th>Waktu</th>
                </tr></thead>
                <tbody>
                <?php foreach ($allDocs as $doc):
                    $stClass = strtolower($doc['status']);
                ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($doc['nomor_polisi']); ?></strong></td>
                    <td><?php echo $jenisList[$doc['jenis_dokumen']] ?? $doc['jenis_dokumen']; ?></td>
                    <td><a href="<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank" class="btn btn-sm"><?php echo htmlspecialchars(substr($doc['nama_file_asli'], 0, 20)); ?></a></td>
                    <td><?php echo $doc['tanggal_berlaku'] ? date('d/m/Y', strtotime($doc['tanggal_berlaku'])) : '-'; ?></td>
                    <td><span class="badge badge-<?php echo $stClass; ?>"><?php echo $doc['status']; ?></span>
                        <?php if ($doc['status'] === 'DITOLAK' && $doc['catatan_admin']): ?>
                        <div style="font-size:11px;color:#991b1b;margin-top:3px;"><?php echo htmlspecialchars(substr($doc['catatan_admin'],0,40)); ?>...</div>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($doc['uploader_name'] ?? '-'); ?></td>
                    <td><?php echo htmlspecialchars($doc['reviewer_name'] ?? '-'); ?></td>
                    <td style="white-space:nowrap"><?php echo date('d/m/Y', strtotime($doc['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tab: Pengurus & Kendaraan -->
    <div id="tab-pengurus" class="tab-content">

        <!-- Assign vehicle form -->
        <div class="card">
            <div class="card-header"><h2>Tugaskan Kendaraan ke Pengurus</h2></div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>Pengurus</label>
                        <select id="assignUserId" class="form-control">
                            <option value="">— Pilih Pengurus —</option>
                            <?php foreach ($pengurusList as $p): ?>
                            <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['full_name']); ?> (<?php echo htmlspecialchars($p['username']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Nomor Polisi</label>
                        <select id="assignNopol" class="form-control" onchange="autoFillTransport(this)">
                            <option value="">— Pilih atau Ketik —</option>
                            <?php foreach ($vehicleOptions as $v): ?>
                            <option value="<?php echo htmlspecialchars($v['nomor_polisi']); ?>" data-transport="<?php echo htmlspecialchars($v['nama_transport'] ?? ''); ?>">
                                <?php echo htmlspecialchars($v['nomor_polisi']); ?><?php if ($v['nama_transport']): ?> — <?php echo htmlspecialchars($v['nama_transport']); ?><?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Nama Transport <span style="font-weight:400">(opsional)</span></label>
                        <input type="text" id="assignTransport" class="form-control" placeholder="Nama perusahaan">
                    </div>
                    <div class="form-group" style="flex:0 0 auto;">
                        <label>&nbsp;</label>
                        <button class="btn btn-primary" onclick="assignVehicle()">+ Tugaskan</button>
                    </div>
                </div>
                <?php if (empty($pengurusList)): ?>
                <p style="margin-top:12px;font-size:12px;color:#b45309;background:#fef3c7;padding:8px 12px;border-radius:4px;">
                    Belum ada user dengan role <strong>Pengurus Mobil Tangki</strong>. Approve pendaftaran pengurus terlebih dahulu.
                </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Current assignments -->
        <div class="card">
            <div class="card-header"><h2>Daftar Penugasan Saat Ini (<?php echo count($assignments); ?>)</h2></div>
            <?php if (empty($assignments)): ?>
            <div class="empty-state">Belum ada kendaraan yang ditugaskan ke pengurus manapun.</div>
            <?php else: ?>
            <table>
                <thead><tr>
                    <th>Pengurus</th>
                    <th>Username</th>
                    <th>Nomor Polisi</th>
                    <th>Nama Transport</th>
                    <th>Ditugaskan</th>
                    <th style="width:80px">Hapus</th>
                </tr></thead>
                <tbody id="assignmentTableBody">
                <?php foreach ($assignments as $a): ?>
                <tr id="pkRow<?php echo $a['id']; ?>">
                    <td><?php echo htmlspecialchars($a['pengurus_name']); ?></td>
                    <td><?php echo htmlspecialchars($a['username']); ?></td>
                    <td><strong><?php echo htmlspecialchars($a['nomor_polisi']); ?></strong></td>
                    <td><?php echo htmlspecialchars($a['nama_transport'] ?? '-'); ?></td>
                    <td><?php echo date('d/m/Y', strtotime($a['created_at'])); ?></td>
                    <td>
                        <button class="btn btn-danger btn-sm" onclick="unassignVehicle(<?php echo $a['id']; ?>)">Hapus</button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Pengurus list -->
        <div class="card">
            <div class="card-header"><h2>Daftar Akun Pengurus (<?php echo count($pengurusList); ?>)</h2></div>
            <?php if (empty($pengurusList)): ?>
            <div class="empty-state">Belum ada akun pengurus aktif.</div>
            <?php else: ?>
            <table>
                <thead><tr>
                    <th>Nama</th><th>Username</th><th>Email</th><th>Jumlah Kendaraan</th>
                </tr></thead>
                <tbody>
                <?php foreach ($pengurusList as $p): ?>
                <tr>
                    <td><?php echo htmlspecialchars($p['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($p['username']); ?></td>
                    <td><?php echo htmlspecialchars($p['email']); ?></td>
                    <td><?php echo $p['vehicle_count']; ?> kendaraan</td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

</div><!-- /page-wrap -->

<!-- Reject Modal -->
<div id="rejectModal" class="modal-overlay">
    <div class="modal">
        <h3>Tolak Dokumen</h3>
        <p style="font-size:13px;color:#7a8ba0;margin-bottom:14px;">Berikan catatan kepada pengurus alasan dokumen ditolak.</p>
        <input type="hidden" id="rejectDocId">
        <div class="form-group">
            <label>Catatan (wajib)</label>
            <textarea id="rejectCatatan" class="form-control" rows="3" placeholder="Misal: File tidak jelas / dokumen sudah kadaluarsa / salah jenis dokumen"></textarea>
        </div>
        <div class="modal-actions">
            <button class="btn" onclick="closeRejectModal()">Batal</button>
            <button class="btn btn-danger" onclick="submitReject()">Tolak Dokumen</button>
        </div>
    </div>
</div>

<script>
function showAlert(msg, type) {
    const el = document.getElementById('alertBox');
    el.className = 'alert alert-' + type;
    el.textContent = msg;
    el.style.display = 'block';
    setTimeout(() => el.style.display = 'none', 5000);
}

function switchTab(name) {
    document.querySelectorAll('.tab-btn').forEach((b, i) => {
        b.classList.toggle('active', ['pending','all','pengurus'][i] === name);
    });
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
}

// Review doc: approve
async function reviewDoc(id, status, catatan = '') {
    const res  = await fetch('', {method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({action:'review_doc', doc_id:id, status, catatan})});
    const data = await res.json();
    if (data.success) {
        const row = document.getElementById('docRow' + id);
        if (row) row.remove();
        // Update badge
        const badge = document.getElementById('pendingBadge');
        if (badge) badge.textContent = Math.max(0, parseInt(badge.textContent) - 1);
        showAlert(status === 'DISETUJUI' ? 'Dokumen disetujui.' : 'Dokumen ditolak.', status === 'DISETUJUI' ? 'success' : 'error');
    } else {
        showAlert('Gagal: ' + data.message, 'error');
    }
}

// Reject modal
function openRejectModal(id) {
    document.getElementById('rejectDocId').value = id;
    document.getElementById('rejectCatatan').value = '';
    document.getElementById('rejectModal').classList.add('open');
}
function closeRejectModal() { document.getElementById('rejectModal').classList.remove('open'); }
async function submitReject() {
    const id      = document.getElementById('rejectDocId').value;
    const catatan = document.getElementById('rejectCatatan').value.trim();
    if (!catatan) { alert('Catatan wajib diisi.'); return; }
    closeRejectModal();
    await reviewDoc(parseInt(id), 'DITOLAK', catatan);
}

// Assign vehicle
function autoFillTransport(sel) {
    const opt = sel.options[sel.selectedIndex];
    document.getElementById('assignTransport').value = opt.dataset.transport || '';
}

async function assignVehicle() {
    const userId    = document.getElementById('assignUserId').value;
    const nopol     = document.getElementById('assignNopol').value.trim();
    const transport = document.getElementById('assignTransport').value.trim();
    if (!userId || !nopol) { showAlert('Pilih pengurus dan nomor polisi terlebih dahulu.', 'error'); return; }
    const res  = await fetch('', {method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({action:'assign_vehicle', user_id:parseInt(userId), nomor_polisi:nopol, nama_transport:transport})});
    const data = await res.json();
    if (data.success) { showAlert('Kendaraan berhasil ditugaskan. Muat ulang halaman untuk melihat perubahan.', 'success'); }
    else { showAlert('Gagal: ' + data.message, 'error'); }
}

async function unassignVehicle(pkId) {
    if (!confirm('Hapus penugasan kendaraan ini?')) return;
    const res  = await fetch('', {method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({action:'unassign_vehicle', pk_id: pkId})});
    const data = await res.json();
    if (data.success) {
        const row = document.getElementById('pkRow' + pkId);
        if (row) row.remove();
        showAlert('Penugasan dihapus.', 'success');
    }
}
</script>
</body>
</html>
