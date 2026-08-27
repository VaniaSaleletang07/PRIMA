<?php
/**
 * Antrian Persetujuan Tanda Tangan Digital
 * PRIMA (Pertamina Checklist Mobil Tangki)
 *
 * HSSE: melihat antrian 'pending_hsse' dan 'signed_hsse'
 * Manajer HSSE: melihat antrian 'signed_hsse'
 * Admin: melihat semua
 */

require_once '../auth/auth.php';
requireLogin();
require_once '../config/config.php';

if (!canAccessApproval()) {
    forbiddenPage('Halaman Antrian Persetujuan hanya dapat diakses oleh HSSE, Manager, atau Admin.');
}

$user = getCurrentUser();
$role = $user['role'];

// Filter berdasarkan role
if (isAdmin()) {
    $filter_statuses = ['pending_hsse', 'signed_hsse', 'rejected'];
} elseif (isManagerHSSE()) {
    $filter_statuses = ['signed_hsse'];
} else {
    // HSSE
    $filter_statuses = ['pending_hsse', 'signed_hsse'];
}

$active_tab = $_GET['tab'] ?? $filter_statuses[0];
if (!in_array($active_tab, $filter_statuses, true)) {
    $active_tab = $filter_statuses[0];
}

try {
    $db = Database::getInstance()->getConnection();

    // Hitung jumlah per status
    $counts = [];
    foreach ($filter_statuses as $s) {
        $st = $db->prepare("SELECT COUNT(*) FROM formulir_checklist WHERE status_approval = ?");
        $st->execute([$s]);
        $counts[$s] = (int)$st->fetchColumn();
    }

    // Ambil data untuk tab aktif
    $stmt = $db->prepare("
        SELECT fc.*,
               u.full_name AS creator_name
        FROM   formulir_checklist fc
        LEFT JOIN users u ON fc.created_by = u.id
        WHERE  fc.status_approval = ?
        ORDER  BY fc.updated_at DESC
    ");
    $stmt->execute([$active_tab]);
    $formulirs = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("pending-approval.php error: " . $e->getMessage());
    $formulirs = [];
    $counts    = [];
}

$status_info = [
    'pending_hsse' => ['label' => 'Menunggu TTD HSSE',    'color' => '#d97706', 'bg' => '#fef3c7'],
    'signed_hsse'  => ['label' => 'Menunggu TTD Manajer', 'color' => '#2563eb', 'bg' => '#eff6ff'],
    'rejected'     => ['label' => 'Ditolak',              'color' => '#dc2626', 'bg' => '#fee2e2'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Antrian Persetujuan — E-KIM</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        body { background: #eef2f7; font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; }
        .page-wrap { max-width: 1200px; margin: 0 auto; padding: 24px 20px; }

        .page-header {
            background: #10334d;
            color: white;
            padding: 24px 30px;
            border-radius: 16px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }
        .page-header h1 { margin: 0; font-size: 20px; }
        .page-header .sub { color: #a7c8dc; font-size: 13px; margin-top: 4px; }
        .header-actions a {
            color: #a7c8dc; text-decoration: none; font-size: 13px;
            padding: 7px 14px; border: 1px solid #2a5a7a; border-radius: 8px;
            transition: all .2s;
        }
        .header-actions a:hover { background: rgba(255,255,255,.1); color: white; }

        /* Tabs */
        .tabs { display: flex; gap: 6px; margin-bottom: 20px; flex-wrap: wrap; }
        .tab-btn {
            padding: 9px 18px; border-radius: 10px; font-size: 14px; font-weight: 600;
            text-decoration: none; color: #6b7280; background: white;
            border: 1.5px solid #e5e7eb; transition: all .2s; display: flex; align-items: center; gap: 6px;
        }
        .tab-btn:hover { border-color: #10334d; color: #10334d; }
        .tab-btn.active { background: #10334d; color: white; border-color: #10334d; }
        .count-badge {
            display: inline-block; min-width: 20px; text-align: center;
            background: rgba(255,255,255,.25); border-radius: 20px;
            padding: 1px 6px; font-size: 11px;
        }
        .tab-btn:not(.active) .count-badge {
            background: #f3f4f6; color: #374151;
        }

        /* Cards */
        .formulir-grid { display: grid; gap: 14px; }
        .fc-card {
            background: white; border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,.06);
            overflow: hidden;
            border-left: 4px solid #e5e7eb;
        }
        .fc-card.pending_hsse { border-left-color: #f59e0b; }
        .fc-card.signed_hsse  { border-left-color: #3b82f6; }
        .fc-card.rejected     { border-left-color: #ef4444; }

        .fc-card-body { padding: 18px 20px; }
        .fc-card-top  { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
        .fc-nopol     { font-size: 18px; font-weight: 700; color: #1a2332; }
        .fc-meta      { font-size: 13px; color: #6b7280; margin-top: 4px; display: flex; flex-wrap: wrap; gap: 12px; }
        .fc-meta span { display: flex; align-items: center; gap: 4px; }

        .status-chip {
            display: inline-block; padding: 4px 12px; border-radius: 20px;
            font-size: 12px; font-weight: 700;
        }

        .sig-summary {
            display: flex; gap: 10px; margin-top: 14px; flex-wrap: wrap;
        }
        .sig-pill {
            display: flex; align-items: center; gap: 6px;
            padding: 6px 12px; border-radius: 8px; font-size: 12px; font-weight: 600;
        }
        .sig-pill.done   { background: #dcfce7; color: #166534; }
        .sig-pill.wait   { background: #fef3c7; color: #92400e; }
        .sig-pill.empty  { background: #f3f4f6; color: #9ca3af; }

        .fc-actions {
            display: flex; gap: 8px; flex-wrap: wrap;
            padding: 12px 20px; background: #f9fafb; border-top: 1px solid #f3f4f6;
        }
        .btn-action {
            padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 600;
            border: none; cursor: pointer; transition: all .2s; display: inline-flex; align-items: center; gap: 5px;
        }
        .btn-sign-hsse   { background: #059669; color: white; }
        .btn-sign-hsse:hover { background: #047857; }
        .btn-sign-mgr    { background: #7c3aed; color: white; }
        .btn-sign-mgr:hover { background: #6d28d9; }
        .btn-reject      { background: transparent; color: #dc2626; border: 1.5px solid #dc2626; }
        .btn-reject:hover { background: #fee2e2; }
        .btn-reset       { background: transparent; color: #d97706; border: 1.5px solid #d97706; }
        .btn-reset:hover { background: #fef9c3; }
        .btn-verify      { background: transparent; color: #2563eb; border: 1.5px solid #2563eb; }
        .btn-verify:hover { background: #eff6ff; }

        .empty-state { text-align: center; padding: 60px 20px; color: #9ca3af; }
        .empty-state .icon { font-size: 48px; margin-bottom: 12px; }
        .empty-state p { font-size: 14px; }

        /* Modal */
        .modal-overlay {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5);
            z-index: 1000; align-items: center; justify-content: center;
        }
        .modal-overlay.open { display: flex; }
        .modal {
            background: white; border-radius: 16px; padding: 28px;
            max-width: 460px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,.2);
        }
        .modal h3 { font-size: 17px; margin-bottom: 8px; }
        .modal p  { font-size: 13px; color: #6b7280; margin-bottom: 16px; line-height: 1.6; }
        .modal textarea {
            width: 100%; padding: 10px; border: 1.5px solid #e5e7eb; border-radius: 8px;
            font-family: inherit; font-size: 13px; resize: vertical; min-height: 80px;
            margin-bottom: 16px;
        }
        .modal-actions { display: flex; gap: 8px; justify-content: flex-end; }
        .btn-modal-cancel { padding: 9px 18px; border: 1.5px solid #e5e7eb; border-radius: 8px; background: white; cursor: pointer; font-size: 13px; }
        .btn-modal-confirm { padding: 9px 18px; border: none; border-radius: 8px; color: white; cursor: pointer; font-size: 13px; font-weight: 600; }
        .btn-modal-confirm.sign   { background: #059669; }
        .btn-modal-confirm.reject { background: #dc2626; }

        /* Toast */
        #toast {
            position: fixed; bottom: 24px; right: 24px; z-index: 2000;
            background: #1a2332; color: white; padding: 14px 20px; border-radius: 10px;
            font-size: 14px; transform: translateY(80px); opacity: 0;
            transition: all .3s ease; max-width: 360px; box-shadow: 0 8px 24px rgba(0,0,0,.2);
        }
        #toast.show { transform: translateY(0); opacity: 1; }
        #toast.success { background: #059669; }
        #toast.error   { background: #dc2626; }
    </style>
</head>
<body>
<div class="page-wrap">

    <!-- Header -->
    <div class="page-header">
        <div>
            <h1>Antrian Tanda Tangan Digital</h1>
            <div class="sub">
                Login sebagai: <strong><?= htmlspecialchars($user['full_name'] ?: $user['username']) ?></strong>
                · Role: <strong><?= strtoupper(str_replace('_', ' ', $role)) ?></strong>
            </div>
        </div>
        <div class="header-actions">
            <a href="<?= isAdmin() ? 'admin-dashboard.php' : 'home.php' ?>">← Kembali</a>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tabs">
        <?php foreach ($filter_statuses as $s):
            $si = $status_info[$s];
        ?>
        <a href="?tab=<?= $s ?>"
           class="tab-btn <?= $active_tab === $s ? 'active' : '' ?>">
            <?= $si['label'] ?>
            <span class="count-badge"><?= $counts[$s] ?? 0 ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Content -->
    <?php if (empty($formulirs)): ?>
    <div class="empty-state">
        <p><strong>Tidak ada dokumen</strong> dalam antrian ini.</p>
    </div>
    <?php else: ?>
    <?php if (isManager()): ?>
    <div style="overflow-x:auto;background:#fff;border:1px solid #dbe3ec;border-radius:6px;">
        <table style="width:100%;border-collapse:collapse;min-width:900px;font-size:13px;">
            <thead>
                <tr style="background:#f4f7fa;color:#334155;text-align:left;">
                    <th style="padding:12px;">Nomor Checklist</th><th style="padding:12px;">Nomor Polisi</th><th style="padding:12px;">Vendor</th><th style="padding:12px;">Petugas HSSE</th><th style="padding:12px;">Tanggal</th><th style="padding:12px;">Status</th><th style="padding:12px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($formulirs as $fc): ?>
                <tr style="border-top:1px solid #e7edf3;">
                    <td style="padding:12px;">#<?= (int)$fc['id'] ?><?= !empty($fc['nomor_urut']) ? ' / ' . htmlspecialchars($fc['nomor_urut']) : '' ?></td>
                    <td style="padding:12px;font-weight:700;"><?= htmlspecialchars($fc['nomor_polisi'] ?? '-') ?></td>
                    <td style="padding:12px;"><?= htmlspecialchars($fc['nama_transport'] ?? '-') ?></td>
                    <td style="padding:12px;"><?= htmlspecialchars($fc['ttd_hsse_nama'] ?: ($fc['creator_name'] ?? '-')) ?></td>
                    <td style="padding:12px;"><?= $fc['tanggal_pemeriksaan'] ? date('d/m/Y', strtotime($fc['tanggal_pemeriksaan'])) : '-' ?></td>
                    <td style="padding:12px;"><span class="status-chip" style="background:#eff6ff;color:#2563eb;">Menunggu Persetujuan Manager</span></td>
                    <td style="padding:12px;white-space:nowrap;">
                        <a class="btn-action btn-verify" href="<?= ($fc['jenis_kendaraan'] ?? '') === 'INDUSTRI' ? 'index-industri.html' : 'index.html' ?>?id=<?= (int)$fc['id'] ?>&mode=view">Detail</a>
                        <?php if ($fc['status_approval'] === 'signed_hsse' && canSignManager()): ?>
                        <button class="btn-action btn-sign-mgr"
                                onclick="confirmSign(<?= $fc['id'] ?>, 'sign_manajer', '<?= htmlspecialchars(addslashes($fc['nomor_polisi'])) ?>')">
                            Approve &amp; TTD
                        </button>
                        <?php endif; ?>
                        <?php if ($fc['status_approval'] === 'signed_hsse' && canRejectChecklist()): ?>
                        <button class="btn-action btn-reject"
                                onclick="openRejectModal(<?= $fc['id'] ?>, '<?= htmlspecialchars(addslashes($fc['nomor_polisi'])) ?>')">
                            Tolak
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="formulir-grid">
        <?php foreach ($formulirs as $fc):
            $si = $status_info[$fc['status_approval']] ?? ['color' => '#6b7280', 'bg' => '#f3f4f6', 'label' => $fc['status_approval']];
        ?>
        <div class="fc-card <?= htmlspecialchars($fc['status_approval']) ?>">
            <div class="fc-card-body">
                <div class="fc-card-top">
                    <div>
                        <div class="fc-nopol"><?= htmlspecialchars($fc['nomor_polisi'] ?? '-') ?></div>
                        <div class="fc-meta">
                            <span><?= htmlspecialchars($fc['jenis_kendaraan'] ?? '-') ?></span>
                            <span><?= htmlspecialchars($fc['nama_transport'] ?? '-') ?></span>
                            <span><?= $fc['tanggal_pemeriksaan'] ? date('d/m/Y', strtotime($fc['tanggal_pemeriksaan'])) : '-' ?></span>
                            <span><?= htmlspecialchars($fc['creator_name'] ?? '-') ?></span>
                        </div>
                    </div>
                    <span class="status-chip" style="background:<?= $si['bg'] ?>;color:<?= $si['color'] ?>;">
                        <?= $si['label'] ?>
                    </span>
                </div>

                <!-- Ringkasan tanda tangan -->
                <div class="sig-summary">
                    <div class="sig-pill <?= $fc['ttd_hsse_signature'] ? 'done' : 'wait' ?>">
                        <?= $fc['ttd_hsse_signature'] ? '✓' : '–' ?>
                        TTD HSSE
                        <?php if ($fc['ttd_hsse_nama']): ?>
                            · <?= htmlspecialchars($fc['ttd_hsse_nama']) ?>
                        <?php endif; ?>
                    </div>
                    <div class="sig-pill <?= $fc['ttd_manajer_signature'] ? 'done' : ($fc['ttd_hsse_signature'] ? 'wait' : 'empty') ?>">
                        <?= $fc['ttd_manajer_signature'] ? '✓' : '–' ?>
                        TTD Manajer
                        <?php if ($fc['ttd_manajer_nama']): ?>
                            · <?= htmlspecialchars($fc['ttd_manajer_nama']) ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="fc-actions">
                <a class="btn-action btn-verify" href="<?= ($fc['jenis_kendaraan'] ?? '') === 'INDUSTRI' ? 'index-industri.html' : 'index.html' ?>?id=<?= (int)$fc['id'] ?>&mode=view">
                    Lihat Detail
                </a>
                <!-- Tombol TTD HSSE -->
                <?php if ($fc['status_approval'] === 'pending_hsse' && canSignHSSE()): ?>
                <button class="btn-action btn-sign-hsse"
                        onclick="confirmSign(<?= $fc['id'] ?>, 'sign_hsse', '<?= htmlspecialchars(addslashes($fc['nomor_polisi'])) ?>')">
                    Tanda Tangan HSSE
                </button>
                <?php endif; ?>

                <!-- Tombol TTD Manajer -->
                <?php if ($fc['status_approval'] === 'signed_hsse' && canSignManager()): ?>
                <button class="btn-action btn-sign-mgr"
                        onclick="confirmSign(<?= $fc['id'] ?>, 'sign_manajer', '<?= htmlspecialchars(addslashes($fc['nomor_polisi'])) ?>')">
                    Approve & Tanda Tangan Manajer
                </button>
                <?php endif; ?>

                <!-- Tombol Reject -->
                <?php if (in_array($fc['status_approval'], ['pending_hsse', 'signed_hsse'], true) && canRejectChecklist()): ?>
                <button class="btn-action btn-reject"
                        onclick="openRejectModal(<?= $fc['id'] ?>, '<?= htmlspecialchars(addslashes($fc['nomor_polisi'])) ?>')">
                    Tolak
                </button>
                <?php endif; ?>

                <!-- Reset ke Draft (Admin) -->
                <?php if (isAdmin()): ?>
                <button class="btn-action btn-reset"
                        onclick="confirmReset(<?= $fc['id'] ?>, '<?= htmlspecialchars(addslashes($fc['nomor_polisi'])) ?>')">
                    Reset Draft
                </button>
                <?php endif; ?>

                <!-- Verifikasi -->
                     <?php if ($fc['verification_url']): ?>
                     <a href="<?= htmlspecialchars($fc['verification_url']) ?>" target="_blank"
                   class="btn-action btn-verify">
                    Verifikasi
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Modal: Konfirmasi Tanda Tangan -->
<div class="modal-overlay" id="signModal">
    <div class="modal">
        <h3 id="signModalTitle">Konfirmasi Tanda Tangan Digital</h3>
        <p id="signModalBody">Anda akan menandatangani secara digital formulir kendaraan <strong id="signModalNopol"></strong>. Tindakan ini tidak dapat dibatalkan.</p>
        <div class="modal-actions">
            <button class="btn-modal-cancel" onclick="closeModal('signModal')">Batal</button>
            <button class="btn-modal-confirm sign" id="btnConfirmSign" onclick="executeSign()">Tanda Tangan</button>
        </div>
    </div>
</div>

<!-- Modal: Reject -->
<div class="modal-overlay" id="rejectModal">
    <div class="modal">
        <h3>Tolak Formulir</h3>
        <p>Formulir kendaraan <strong id="rejectNopol"></strong> akan ditolak. Berikan alasan penolakan:</p>
        <textarea id="rejectNotes" placeholder="Alasan penolakan (wajib diisi)..."></textarea>
        <div class="modal-actions">
            <button class="btn-modal-cancel" onclick="closeModal('rejectModal')">Batal</button>
            <button class="btn-modal-confirm reject" onclick="executeReject()">Tolak Formulir</button>
        </div>
    </div>
</div>

<!-- Toast -->
<div id="toast"></div>

<script>
var pendingId     = null;
var pendingAction = null;
var pendingNopol  = null;

function confirmSign(id, action, nopol) {
    pendingId     = id;
    pendingAction = action;
    pendingNopol  = nopol;

    var isManager = (action === 'sign_manajer');
    document.getElementById('signModalTitle').textContent =
        isManager ? 'Konfirmasi Approval & Tanda Tangan Manajer' : 'Konfirmasi Tanda Tangan HSSE';
    document.getElementById('signModalNopol').textContent = nopol;
    document.getElementById('btnConfirmSign').textContent =
        isManager ? 'Approve & Tanda Tangan' : 'Tanda Tangan HSSE';
    document.getElementById('signModal').classList.add('open');
}

function openRejectModal(id, nopol) {
    pendingId    = id;
    pendingNopol = nopol;
    document.getElementById('rejectNopol').textContent = nopol;
    document.getElementById('rejectNotes').value = '';
    document.getElementById('rejectModal').classList.add('open');
}

function confirmReset(id, nopol) {
    if (!confirm('Reset formulir ' + nopol + ' ke Draft?\n\nSeluruh tanda tangan akan dihapus dan data dapat diedit ulang.\n\nLanjutkan?')) return;
    callApi(id, 'reset_draft', {});
}

function closeModal(id) {
    document.getElementById(id).classList.remove('open');
    pendingId = pendingAction = pendingNopol = null;
}

function executeSign() {
    var id = pendingId, action = pendingAction;
    closeModal('signModal');
    callApi(id, action, {});
}

function executeReject() {
    var notes = document.getElementById('rejectNotes').value.trim();
    if (!notes) { alert('Alasan penolakan wajib diisi.'); return; }
    var id = pendingId;
    closeModal('rejectModal');
    callApi(id, 'reject', { notes: notes });
}

function callApi(formulir_id, action, extra) {
    showToast('Memproses...', '');

    var body = Object.assign({ formulir_id: formulir_id, action: action }, extra);

    fetch('sign-checklist.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            showToast(data.message, 'success');
            // Setelah TTD HSSE atau Manajer berhasil (tanpa gambar/upload),
            // langsung arahkan ke halaman detail agar QR Code verifikasi
            // tampil otomatis di kotak TTD yang baru saja ditandatangani.
            if ((action === 'sign_manajer' || action === 'sign_hsse') && data.data && data.data.formulir_id) {
                var detailPage = data.data.jenis_kendaraan === 'INDUSTRI' ? 'index-industri.html' : 'index.html';
                window.location.href = detailPage + '?id=' + data.data.formulir_id + '&mode=view';
                return;
            }
            setTimeout(function() { location.reload(); }, 1800);
        } else {
            showToast(data.message || 'Terjadi kesalahan.', 'error');
        }
    })
    .catch(function(e) {
        showToast('Gagal menghubungi server: ' + e.message, 'error');
    });
}

function showToast(msg, type) {
    var t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'show' + (type ? ' ' + type : '');
    clearTimeout(t._timer);
    t._timer = setTimeout(function() { t.className = ''; }, 3500);
}

// Close modal on overlay click
document.querySelectorAll('.modal-overlay').forEach(function(el) {
    el.addEventListener('click', function(e) {
        if (e.target === el) el.classList.remove('open');
    });
});
</script>
</body>
</html>
