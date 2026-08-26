<?php
require_once 'auth.php';
requireAdmin();

require_once 'config.php';

$user = getCurrentUser();
$alerts = getVehicleAlerts(30); // Get alerts for next 30 days

// Count by status
$count_expired = 0;
$count_inspection_needed = 0;

foreach ($alerts as $alert) {
    if ($alert['status_alert'] === 'SUDAH_EXPIRED') {
        $count_expired++;
    } elseif ($alert['status_alert'] === 'PERLU_INSPEKSI') {
        $count_inspection_needed++;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifikasi Inspeksi Kendaraan — PRIMA</title>
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
            max-width: 1280px;
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

        /* ── STAT CARDS ── */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: #fff;
            border: 1px solid #dde3ec;
            border-radius: 6px;
            padding: 18px 22px;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .stat-icon {
            width: 44px; height: 44px;
            border-radius: 6px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .stat-icon.red   { background:#fef2f2; }
        .stat-icon.amber { background:#fffbeb; }
        .stat-icon.blue  { background:#eff6ff; }
        .stat-body {}
        .stat-num {
            font-size: 28px;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 3px;
        }
        .stat-num.red   { color:#c8102e; }
        .stat-num.amber { color:#d97706; }
        .stat-num.blue  { color:#1d4ed8; }
        .stat-lbl {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .6px;
            color: #6b7a8f;
        }

        /* ── CARD ── */
        .card {
            background: #fff;
            border: 1px solid #dde3ec;
            border-radius: 6px;
            overflow: hidden;
        }
        .card-header {
            padding: 14px 20px;
            border-bottom: 1px solid #dde3ec;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            background: #fafbfc;
        }
        .card-header-title {
            font-size: 13px;
            font-weight: 700;
            color: #1a2332;
            text-transform: uppercase;
            letter-spacing: .5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .card-header-count {
            font-size: 11px;
            background: #f0f2f5;
            color: #6b7a8f;
            border: 1px solid #dde3ec;
            border-radius: 20px;
            padding: 2px 10px;
            font-weight: 600;
        }

        /* ── TABLE ── */
        .tbl-wrap { overflow-x: auto; }
        table { width:100%; border-collapse:collapse; }
        thead tr {
            background: #1e2a3b;
        }
        th {
            padding: 11px 14px;
            text-align: left;
            font-size: 11px;
            font-weight: 600;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: .6px;
            white-space: nowrap;
        }
        td {
            padding: 12px 14px;
            border-bottom: 1px solid #edf0f4;
            font-size: 13px;
            color: #1a2332;
            vertical-align: middle;
        }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover td { background: #f8fafc; }
        tbody tr.row-expired td { background: #fff8f8; }
        tbody tr.row-expired:hover td { background: #fff0f0; }

        /* ── BADGES ── */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .3px;
            white-space: nowrap;
        }
        .badge-dot { width:6px; height:6px; border-radius:50%; flex-shrink:0; }

        .badge-expired  { background:#fef2f2; color:#991b1b; border:1px solid #fecaca; }
        .badge-expired .badge-dot { background:#dc2626; }
        .badge-urgent   { background:#fff7ed; color:#9a3412; border:1px solid #fed7aa; }
        .badge-urgent .badge-dot { background:#ea580c; }
        .badge-warning  { background:#fffbeb; color:#92400e; border:1px solid #fde68a; }
        .badge-warning .badge-dot { background:#d97706; }

        .badge-jenis-spbu     { background:#eff6ff; color:#1e40af; border:1px solid #bfdbfe; }
        .badge-jenis-industri { background:#f5f3ff; color:#4c1d95; border:1px solid #ddd6fe; }

        /* ── DAYS CELL ── */
        .days-expired { color:#c8102e; font-weight:700; }
        .days-critical { color:#d97706; font-weight:700; }
        .days-ok { color:#374151; }

        /* ── EMAIL CELL ── */
        .email-cell a { color:#1d4ed8; text-decoration:none; font-size:12px; }
        .email-cell a:hover { text-decoration:underline; }
        .email-none { color:#b0bec8; font-size:12px; font-style:italic; }

        /* ── NOPOL ── */
        .nopol { font-weight:700; font-size:13px; letter-spacing:.3px; color:#1a2332; }
        .merk-cell { color:#374151; }
        .transport-cell { color:#374151; }

        /* ── EMPTY STATE ── */
        .empty-wrap {
            padding: 56px 20px;
            text-align: center;
        }
        .empty-wrap svg { color:#9ca3af; margin-bottom:14px; }
        .empty-title { font-size:15px; font-weight:700; color:#374151; margin-bottom:6px; }
        .empty-sub { font-size:13px; color:#9ca3af; }

        /* ── RESPONSIVE ── */
        @media (max-width: 768px) {
            .stats-row { grid-template-columns: 1fr; }
            .page-header { flex-direction:column; align-items:flex-start; }
            th, td { padding:10px 12px; font-size:12px; }
        }
    </style>
</head>
<body>
<div class="page-wrap">

    <!-- Header -->
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-header-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#c8102e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            </div>
            <div>
                <div class="page-title">Notifikasi Inspeksi Kendaraan</div>
                <div class="page-subtitle">Pemantauan status KIM &amp; jadwal inspeksi kendaraan tangki</div>
            </div>
        </div>
        <a href="home.php" class="btn-back">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            Kembali ke Dashboard
        </a>
    </div>

    <!-- Stats -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon red">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#c8102e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            </div>
            <div class="stat-body">
                <div class="stat-num red"><?php echo $count_expired; ?></div>
                <div class="stat-lbl">KIM Kedaluwarsa</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon amber">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </div>
            <div class="stat-body">
                <div class="stat-num amber"><?php echo $count_inspection_needed; ?></div>
                <div class="stat-lbl">Perlu Inspeksi (&le;30 hari)</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#1d4ed8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13" rx="1"/><polygon points="16,8 20,8 23,11 23,16 16,16 16,8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
            </div>
            <div class="stat-body">
                <div class="stat-num blue"><?php echo count($alerts); ?></div>
                <div class="stat-lbl">Total Kendaraan Alert</div>
            </div>
        </div>
    </div>

    <!-- Table Card -->
    <div class="card">
        <div class="card-header">
            <span class="card-header-title">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6b7a8f" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                Daftar Kendaraan yang Perlu Inspeksi
            </span>
            <span class="card-header-count"><?php echo count($alerts); ?> kendaraan</span>
        </div>

        <?php if (count($alerts) > 0): ?>
        <div class="tbl-wrap">
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nomor Polisi</th>
                        <th>Merk / Tipe</th>
                        <th>Jenis</th>
                        <th>Nama Kontraktor</th>
                        <th>Email Kontraktor</th>
                        <th>KIM Valid Hingga</th>
                        <th>Sisa Hari</th>
                        <th>Status KIM</th>
                    </tr>
                </thead>
                <tbody>
                <?php $no = 1; foreach ($alerts as $alert):
                    $isExp = $alert['status_alert'] === 'SUDAH_EXPIRED';
                    $hari  = (int)$alert['hari_tersisa'];
                    $rowCls = $isExp ? 'row-expired' : '';
                    if ($isExp) {
                        $daysCls = 'days-expired'; $daysText = abs($hari) . ' hari lalu';
                        $badge   = '<span class="badge badge-expired"><span class="badge-dot"></span>Kedaluwarsa</span>';
                    } elseif ($hari <= 14) {
                        $daysCls = 'days-critical'; $daysText = $hari . ' hari';
                        $badge   = '<span class="badge badge-urgent"><span class="badge-dot"></span>Segera Habis</span>';
                    } else {
                        $daysCls = 'days-ok'; $daysText = $hari . ' hari';
                        $badge   = '<span class="badge badge-warning"><span class="badge-dot"></span>Perlu Perhatian</span>';
                    }
                    $jenisBadge = strtoupper($alert['jenis'] ?? 'SPBU') === 'SPBU'
                        ? '<span class="badge badge-jenis-spbu">SPBU</span>'
                        : '<span class="badge badge-jenis-industri">INDUSTRI</span>';
                    $tglValid = !empty($alert['ekim_valid_until']) ? date('d M Y', strtotime($alert['ekim_valid_until'])) : '-';
                ?>
                <tr class="<?php echo $rowCls; ?>">
                    <td style="color:#9aacbb;font-size:12px;"><?php echo $no++; ?></td>
                    <td><span class="nopol"><?php echo htmlspecialchars($alert['nomor_polisi']); ?></span></td>
                    <td class="merk-cell"><?php echo htmlspecialchars($alert['merk_mobil'] ?? '-'); ?></td>
                    <td><?php echo $jenisBadge; ?></td>
                    <td class="transport-cell"><?php echo htmlspecialchars($alert['nama_transport'] ?? '-'); ?></td>
                    <td class="email-cell">
                        <?php if (!empty($alert['email_kontraktor'])): ?>
                            <a href="mailto:<?php echo htmlspecialchars($alert['email_kontraktor']); ?>"><?php echo htmlspecialchars($alert['email_kontraktor']); ?></a>
                        <?php else: ?>
                            <span class="email-none">Belum diisi</span>
                        <?php endif; ?>
                    </td>
                    <td style="white-space:nowrap;font-size:13px;"><?php echo $tglValid; ?></td>
                    <td><span class="<?php echo $daysCls; ?>"><?php echo $daysText; ?></span></td>
                    <td><?php echo $badge; ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-wrap">
            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <div class="empty-title">Tidak Ada Kendaraan yang Perlu Perhatian</div>
            <div class="empty-sub">Semua KIM kendaraan masih valid lebih dari 30 hari ke depan.</div>
        </div>
        <?php endif; ?>
    </div>

</div>
</body>
</html>
