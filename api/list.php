<?php
require_once '../auth/auth.php';
requireLogin();

$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Data Checklist E-KIM - Pertamina Patra Niaga</title>
    <link rel="stylesheet" href="../assets/style.css" />
    <style>
        body {
            background: #f1f4f8;
            font-family: "Segoe UI", -apple-system, BlinkMacSystemFont, "Helvetica Neue", Arial, sans-serif;
            font-size: 14px;
            color: #1a2332;
            line-height: 1.5;
        }

        /* ── BUTTONS ── */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 7px 14px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid transparent;
            transition: background 0.15s, border-color 0.15s, color 0.15s;
            text-decoration: none;
            white-space: nowrap;
            font-family: inherit;
            background: transparent;
            color: #4a5568;
        }

        .btn-save {
            background: #c8102e;
            color: white;
            border-color: #c8102e;
        }
        .btn-save:hover { background: #a80e27; border-color: #a80e27; }

        .btn-print {
            background: transparent;
            color: #4a5568;
            border-color: #d1d9e0;
        }
        .btn-print:hover { background: #f8fafc; border-color: #b0bec8; color: #1a2332; }

        .btn-reset {
            background: transparent;
            color: #4a5568;
            border: 1px solid #d1d9e0;
        }
        .btn-reset:hover { background: #f8fafc; border-color: #b0bec8; }

        .list-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 24px 28px;
        }

        /* ── TOOLBAR ── */
        .toolbar {
            background: white;
            padding: 22px 26px;
            border-radius: 6px;
            margin-bottom: 18px;
            border: 1px solid #dde3ec;
            border-top: 3px solid #c8102e;
        }

        .filter-group {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto auto;
            gap: 12px;
            margin-bottom: 20px;
            align-items: center;
        }
        
        .filter-input {
            padding: 8px 12px;
            border: 1px solid #d1d9e0;
            border-radius: 4px;
            font-size: 13px;
            font-family: inherit;
            color: #1a2332;
            transition: border-color 0.15s, box-shadow 0.15s;
            background: white;
        }

        .filter-input:focus {
            outline: none;
            border-color: #0d1f35;
            box-shadow: 0 0 0 3px rgba(13,31,53,0.08);
        }

        .btn-group {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        /* ── JENIS TABS ── */
        .jenis-tabs {
            display: flex;
            border: 1px solid #dde3ec;
            border-radius: 4px;
            overflow: hidden;
        }

        .jenis-tab {
            flex: 1;
            padding: 8px 14px;
            background: white;
            border: none;
            border-right: 1px solid #dde3ec;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            color: #4a5568;
            font-family: inherit;
            transition: background 0.15s, color 0.15s;
        }
        .jenis-tab:last-child { border-right: none; }
        .jenis-tab.active { background: #0d1f35; color: white; }
        .jenis-tab:hover:not(.active) { background: #f1f4f8; }
        
        .data-table {
            background: white;
            border-radius: 6px;
            overflow: hidden;
            border: 1px solid #dde3ec;
            border-top: 3px solid #c8102e;
        }
        
        .data-table table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th {
            background: #0d1f35;
            color: rgba(255,255,255,0.85);
            padding: 11px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 11.5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .data-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #f0f3f7;
            font-size: 13px;
            color: #1a2332;
        }

        .data-table tr:last-child td { border-bottom: none; }
        .data-table tr:hover td { background: #f8fafc; }

        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .badge-success { background: #dcfce7; color: #15803d; }
        .badge-warning { background: #fef9c3; color: #92400e; }
        .badge-danger  { background: #fee2e2; color: #991b1b; }
        .badge-info    { background: #dbeafe; color: #1d4ed8; }
        
        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            margin: 1px;
            border: 1px solid transparent;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.15s, border-color 0.15s, box-shadow 0.15s;
            background: transparent;
            flex-shrink: 0;
        }
        .btn-action svg {
            display: block;
            pointer-events: none;
        }
        .btn-view {
            border-color: #2563eb;
            color: #2563eb;
        }
        .btn-view:hover {
            background: #2563eb;
            color: white;
            box-shadow: 0 1px 4px rgba(37,99,235,0.3);
        }
        .btn-edit {
            border-color: #b45309;
            color: #b45309;
        }
        .btn-edit:hover {
            background: #b45309;
            color: white;
            box-shadow: 0 1px 4px rgba(180,83,9,0.3);
        }
        .btn-delete {
            border-color: #dc2626;
            color: #dc2626;
        }
        .btn-delete:hover {
            background: #dc2626;
            color: white;
            box-shadow: 0 1px 4px rgba(220,38,38,0.3);
        }
        .btn-verify {
            border-color: #059669;
            color: #059669;
        }
        .btn-verify:hover {
            background: #059669;
            color: white;
            box-shadow: 0 1px 4px rgba(5,150,105,0.3);
        }

        /* TTD status badges in list */
        .ttd-badge-ok {
            display: inline-flex;
            align-items: center;
            padding: 2px 6px;
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #6ee7b7;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
            cursor: default;
        }
        .ttd-badge-no {
            display: inline-flex;
            align-items: center;
            padding: 2px 6px;
            background: #f1f5f9;
            color: #94a3b8;
            border: 1px solid #cbd5e1;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
            white-space: nowrap;
            cursor: default;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            padding: 16px 28px;
            background: white;
            border-radius: 6px;
            margin-top: 14px;
            border: 1px solid #dde3ec;
        }
        
        .pagination button {
            padding: 7px 14px;
            border: 1px solid #d1d9e0;
            background: white;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            color: #4a5568;
            transition: all 0.15s;
            font-family: inherit;
        }
        
        .pagination button:hover:not(:disabled) {
            background: #0d1f35;
            color: white;
            border-color: #0d1f35;
        }
        
        .pagination button:disabled { opacity: 0.35; cursor: not-allowed; }

        .pagination .page-info {
            font-size: 13px;
            color: #4a5568;
            font-weight: 500;
            padding: 0 10px;
        }
        
        .loading { text-align: center; padding: 48px; font-size: 14px; color: #7a8ba0; }
        .no-data  { text-align: center; padding: 48px; color: #7a8ba0; font-size: 14px; }

        /* Corporate Header */
        .page-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .page-header-logo {
            height: 40px;
            object-fit: contain;
            flex-shrink: 0;
        }

        .page-header-divider {
            width: 1px;
            height: 32px;
            background: #dde3ec;
            flex-shrink: 0;
        }

        .header-text h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
            color: #0d1f35;
            letter-spacing: -0.3px;
            line-height: 1.2;
        }

        .header-text p {
            margin: 3px 0 0;
            color: #7a8ba0;
            font-size: 12.5px;
        }

        .header-actions {
            margin-left: auto;
            display: flex;
            gap: 8px;
            flex-shrink: 0;
            flex-wrap: wrap;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 14px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            padding: 16px 18px;
            border-radius: 6px;
            border: 1px solid #dde3ec;
            border-top: 3px solid #0d1f35;
        }

        .stat-card h3 {
            font-size: 26px;
            font-weight: 700;
            color: #0d1f35;
            margin: 0 0 4px;
            line-height: 1;
            font-variant-numeric: tabular-nums;
        }

        .stat-card p {
            margin: 0;
            color: #7a8ba0;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.7px;
        }

        .stat-card.stat-danger {
            border-top: 3px solid #c8102e;
        }
        .stat-card.stat-danger h3 { color: #c8102e; }

        .stat-card.stat-semester {
            border-top: 3px solid #d97706;
        }
        .stat-card.stat-semester h3 { color: #d97706; font-size: 15px; line-height: 1.3; }
        
        /* Expired row highlighting */
        .tr-expired td {
            background: #fff5f5 !important;
        }
        .tr-expired {
            border-left: 3px solid #c8102e;
        }
        .tr-expired td:first-child {
            border-left: 3px solid #c8102e;
        }
        .badge-expired {
            background: #c8102e;
            color: white;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
        }
        .ekim-expired-cell {
            color: #c8102e;
            font-weight: 700;
        }
        
        /* (stats-row + stat-card defined above in header section) */

        /* User Info Bar */
        .user-info-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 16px;
            margin-bottom: 18px;
            border-bottom: 1px solid #e8ecf2;
            flex-wrap: wrap;
            gap: 10px;
        }

        .user-info-text {
            font-size: 13px;
            color: #4a5568;
            font-weight: 500;
        }

        .user-info-text strong {
            color: #0d1f35;
            font-weight: 700;
        }

        .user-bar-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .btn-logout {
            display: inline-flex;
            align-items: center;
            padding: 7px 14px;
            background: transparent;
            color: #c8102e;
            border: 1px solid #c8102e;
            border-radius: 4px;
            font-weight: 600;
            font-size: 13px;
            text-decoration: none;
            transition: all 0.15s;
            font-family: inherit;
            cursor: pointer;
        }
        .btn-logout:hover { background: #c8102e; color: white; }

        /* ========================================
           RESPONSIVE MOBILE DESIGN
           ======================================== */
        
        @media (max-width: 1280px) {
            .stats-row {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 1024px) {
            .filter-group {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .stats-row {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .list-container {
                padding: 15px;
            }

            .toolbar {
                padding: 20px;
            }

            .user-info-bar {
                flex-direction: column;
                gap: 12px;
                align-items: stretch;
            }

            .btn-logout {
                width: 100%;
                text-align: center;
            }

            .toolbar > div:first-child {
                flex-direction: column;
                align-items: flex-start !important;
            }

            .page-header {
                flex-direction: column;
                align-items: center;
                text-align: center;
                gap: 15px;
            }

            .header-text h1 {
                font-size: 22px !important;
            }

            .header-text p {
                font-size: 13px !important;
            }

            .btn-group {
                flex-direction: column;
                width: 100%;
                gap: 10px;
            }

            .btn-group .btn {
                width: 100%;
                font-size: 13px;
                padding: 10px 15px;
            }

            .stats-row {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }

            .stat-card {
                padding: 16px;
            }

            .stat-card h3 {
                font-size: 28px;
            }

            .stat-card p {
                font-size: 11px;
            }

            .filter-group {
                grid-template-columns: 1fr;
            }

            .filter-input {
                font-size: 14px;
                padding: 10px 12px;
            }

            /* Table scroll on mobile */
            .data-table {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .data-table table {
                min-width: 800px;
                font-size: 12px;
            }

            .data-table th,
            .data-table td {
                padding: 8px;
                font-size: 12px;
            }

            .badge {
                font-size: 10px;
                padding: 4px 8px;
            }

            .action-buttons {
                flex-direction: column;
                gap: 5px;
            }

            .action-btn {
                width: 100%;
                font-size: 11px;
                padding: 6px 10px;
            }

            /* Pagination mobile */
            .pagination {
                flex-wrap: wrap;
                gap: 6px;
            }

            .page-btn {
                min-width: 32px;
                height: 32px;
                font-size: 12px;
            }

            .page-info {
                width: 100%;
                text-align: center;
                order: -1;
                margin-bottom: 10px;
            }
        }

        @media (max-width: 480px) {
            .list-container {
                padding: 10px;
            }

            .toolbar {
                padding: 15px;
            }

            .header-text h1 {
                font-size: 18px !important;
            }

            .stats-row {
                grid-template-columns: 1fr;
            }

            .stat-card h3 {
                font-size: 24px;
            }

            .data-table table {
                min-width: 700px;
                font-size: 11px;
            }

            .toolbar h1 {
                font-size: 20px;
            }
        }
    </style>
    <?php include __DIR__ . '/includes/sidebar-styles.php'; ?>
</head>
<body>
<div class="app-shell">
<?php $activeNav = (isset($_GET['status']) && $_GET['status'] === 'approved') ? 'approved' : 'checklist'; include __DIR__ . '/includes/sidebar-nav.php'; ?>
<div class="main-wrapper">
    <header class="top-bar">
        <div class="top-bar-left">
            <span class="top-bar-accent"></span>
            <span class="top-bar-title">Data Checklist</span>
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
    <div class="list-container">
        <!-- Toolbar -->
        <div class="toolbar">
            <div class="user-info-bar">
                <span class="user-info-text">Selamat datang, <strong><?php echo htmlspecialchars($user['full_name']); ?></strong></span>
                <div class="user-bar-actions">
                    <?php if ($user['role'] === 'admin'): ?>
                        <a href="../home.php" class="btn btn-print">&#9881;&#65039; Dashboard Admin</a>
                    <?php endif; ?>
                    <a href="../auth/logout.php" class="btn-logout">Keluar</a>
                </div>
            </div>
            <div class="page-header">
                <img src="foto/PT_Pertamina_Patra_Niaga.png" alt="Pertamina Patra Niaga" class="page-header-logo">
                <div class="page-header-divider"></div>
                <div class="header-text">
                    <h1 id="pageTitle">Data Checklist E-KIM Pertamina</h1>
                    <p>Sistem Manajemen Inspeksi Perpanjangan Kartu Izin Masuk</p>
                </div>
                <div class="header-actions">
                    <button class="btn btn-print" onclick="window.location.href="../home.php"">← Kembali</button>
                    <?php if (!isManager()): ?>
                    <button class="btn btn-save" onclick="window.location.href="../index.html"">Input SPBU</button>
                    <button class="btn btn-print" onclick="window.location.href="../index-industri.html"">Input Industri</button>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Statistics -->
            <div class="stats-row" id="statsRow">
                <div class="stat-card">
                    <h3 id="statTotal">0</h3>
                    <p>Total Data</p>
                </div>
                <div class="stat-card">
                    <h3 id="statSpbu">0</h3>
                    <p>SPBU</p>
                </div>
                <div class="stat-card">
                    <h3 id="statIndustri">0</h3>
                    <p>Industri</p>
                </div>
                <div class="stat-card">
                    <h3 id="statBulanIni">0</h3>
                    <p>Bulan Ini</p>
                </div>
                <div class="stat-card stat-danger">
                    <h3 id="statKimExpired">0</h3>
                    <p>KIM Kedaluwarsa</p>
                </div>
            </div>
            <!-- Semester Inspection Notice -->
            <div id="semesterNotice" style="background:#fffbeb;border:1px solid #fcd34d;border-left:4px solid #d97706;border-radius:5px;padding:10px 16px;margin-bottom:14px;font-size:13px;color:#78350f;display:flex;align-items:center;gap:10px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <span><strong>Jadwal Inspeksi Semester:</strong> Setiap <strong>25 Februari</strong> dan <strong>25 Agustus</strong> &mdash; Inspeksi semester berikutnya: <strong id="semesterNextDate">-</strong> <span id="semesterCountdown" style="margin-left:6px;background:#d97706;color:white;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700;"></span></span>
            </div>
            
            <!-- Filters -->
            <div class="filter-group">
                <input type="text" id="searchInput" class="filter-input" placeholder="Cari Nomor Polisi, Nama Transport, atau Nomor Urut...">
                <input type="date" id="dateFrom" class="filter-input" placeholder="Dari Tanggal">
                <input type="date" id="dateTo" class="filter-input" placeholder="Sampai Tanggal">
                <div class="btn-group">
                    <button class="btn btn-print" onclick="filterData()">Filter</button>
                    <button class="btn btn-reset" onclick="clearFilters()">&#128260; Reset</button>
                </div>
                <button class="btn btn-save" onclick="exportToExcel()">&#128229; Export Excel</button>
            </div>
            
            <!-- Jenis Filter Tabs -->
            <div class="jenis-tabs">
                <button class="jenis-tab active" id="btnAll" onclick="filterByJenis('')">Semua Kendaraan</button>
                <button class="jenis-tab" id="btnSpbu" onclick="filterByJenis('SPBU')">SPBU</button>
                <button class="jenis-tab" id="btnIndustri" onclick="filterByJenis('INDUSTRI')">Industri</button>
            </div>
        </div>
        
        <!-- Data Table -->
        <div class="data-table">
            <table>
                <thead>
                    <tr>
                        <th style="width: 40px">No</th>
                        <th style="width: 80px">Jenis</th>
                        <th style="width: 100px">Nomor Polisi</th>
                        <th>Nama Transport</th>
                        <th style="width: 120px">Merk Mobil</th>
                        <th style="width: 100px">Tgl Periksa</th>
                        <th style="width: 100px">EKIM Valid</th>
                        <th style="width: 80px">Status</th>
                        <th style="width: 80px">Progress</th>
                        <th style="width: 90px">TTD</th>
                        <th style="width: 200px">Aksi</th>
                    </tr>
                </thead>
                <tbody id="dataTableBody">
                    <tr>
                        <td colspan="11" class="loading">Memuat data...</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <div class="pagination" id="pagination">
            <button id="btnFirst" onclick="goToPage(1)">First</button>
            <button id="btnPrev" onclick="goToPage(currentPage - 1)">Prev</button>
            <span class="page-info" id="pageInfo">Page 1 of 1</span>
            <button id="btnNext" onclick="goToPage(currentPage + 1)">Next</button>
            <button id="btnLast" onclick="goToPage(totalPages)">Last</button>
        </div>
    </div>
    </div><!-- /page-content -->
</div><!-- /main-wrapper -->
</div><!-- /app-shell -->

    <script>
        const canEditChecklist = <?php echo isManager() ? 'false' : 'true'; ?>;
        let currentPage = 1;
        let totalPages = 1;
        let allData = [];
        let currentJenisFilter = ''; // New: Track current filter
        let currentApprovalStatus = '';
        let isFilterLocked = false; // Prevent changing filter when locked
        
        // Load data on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Check for jenis filter from URL
            const urlParams = new URLSearchParams(window.location.search);
            const jenisFilter = urlParams.get('jenis');
            currentApprovalStatus = urlParams.get('status') || '';
            if (currentApprovalStatus === 'approved') {
                document.getElementById('pageTitle').innerHTML = 'Checklist Approved';
            }
            if (jenisFilter) {
                currentJenisFilter = jenisFilter.toUpperCase();
                isFilterLocked = true; // Lock filter when coming from URL
                
                // Hide buttons that are not relevant
                if (currentJenisFilter === 'SPBU') {
                    document.getElementById('btnAll').style.display = 'none';
                    document.getElementById('btnIndustri').style.display = 'none';
                    document.getElementById('btnSpbu').style.flex = '1';
                } else if (currentJenisFilter === 'INDUSTRI') {
                    document.getElementById('btnAll').style.display = 'none';
                    document.getElementById('btnSpbu').style.display = 'none';
                    document.getElementById('btnIndustri').style.flex = '1';
                }
                
                filterByJenis(currentJenisFilter);
                
                // Update page title
                if (currentJenisFilter === 'SPBU') {
                    document.getElementById('pageTitle').innerHTML = 'Data Checklist SPBU';
                } else if (currentJenisFilter === 'INDUSTRI') {
                    document.getElementById('pageTitle').innerHTML = 'Data Checklist Industri';
                }
            } else {
                loadData();
            }
        });
        
        // Filter by jenis kendaraan
        function filterByJenis(jenis) {
            // Prevent changing filter if locked from URL
            if (isFilterLocked && jenis !== currentJenisFilter) {
                return;
            }
            
            currentJenisFilter = jenis;
            currentPage = 1;
            
            // Update tab active state
            document.getElementById('btnAll').classList.toggle('active', jenis === '');
            document.getElementById('btnSpbu').classList.toggle('active', jenis === 'SPBU');
            document.getElementById('btnIndustri').classList.toggle('active', jenis === 'INDUSTRI');
            
            loadData();
        }
        
        // Load data from server
        async function loadData(page = 1) {
            try {
                console.log('=== LOADING DATA ===');
                const search = document.getElementById('searchInput').value;
                const dateFrom = document.getElementById('dateFrom').value;
                const dateTo = document.getElementById('dateTo').value;
                
                const params = new URLSearchParams({
                    page: page,
                    limit: 50,
                    search: search,
                    dateFrom: dateFrom,
                    dateTo: dateTo,
                    _t: Date.now() // Cache buster
                });
                
                // Add jenis filter if active
                if (currentJenisFilter) {
                    params.append('jenis', currentJenisFilter);
                }
                if (currentApprovalStatus) {
                    params.append('status', currentApprovalStatus);
                }
                
                console.log('Request URL:', `load.php?${params}`);
                const response = await fetch(`load.php?${params}`, {
                    cache: 'no-cache',
                    headers: {
                        'Cache-Control': 'no-cache'
                    }
                });
                
                console.log('Response status:', response.status);
                const result = await response.json();
                
                console.log('API Response:', result);
                console.log('Data array:', result.data);
                console.log('Data length:', result.data ? result.data.length : 'undefined');
                
                if (result.success) {
                    allData = result.data;
                    currentPage = result.pagination ? result.pagination.page : 1;
                    totalPages = result.pagination ? result.pagination.totalPages : 1;
                    
                    console.log('All data loaded:', allData.length, 'records');
                    
                    renderTable(allData);
                    updatePagination();
                    updateStatistics();
                } else {
                    console.error('API returned success=false:', result.message);
                    showError(result.message);
                }
            } catch (error) {
                console.error('=== ERROR DETAILS ===');
                console.error('Error type:', error.constructor.name);
                console.error('Error message:', error.message);
                console.error('Error stack:', error.stack);
                showError('Gagal memuat data: ' + error.message);
            }
        }
        
        // Render table
        function jenisLabel(jenis) {
            return (jenis || 'SPBU').toUpperCase() === 'SPBU' ? 'SPBU' : 'Industri';
        }
        function renderTable(data) {
            const tbody = document.getElementById('dataTableBody');
            
            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="11" class="no-data">Tidak ada data</td></tr>';
                return;
            }
            
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            tbody.innerHTML = data.map((row, index) => {
                const no = ((currentPage - 1) * 50) + index + 1;
                const progressPct = parseFloat(row.persentase_baik) || 0;
                const statusBadge = progressPct >= 80 ? 'badge-success' : progressPct >= 50 ? 'badge-warning' : 'badge-danger';
                const statusText = row.status_gate || 'Pending';
                const jenisBadge = row.jenis_kendaraan === 'SPBU' ? 'badge-danger' : 'badge-info';

                // KIM expiry check
                let kimExpired = false;
                let ekimCellHtml = formatDate(row.ekim_valid_until);
                if (row.ekim_valid_until) {
                    const ekimDate = new Date(row.ekim_valid_until);
                    ekimDate.setHours(0, 0, 0, 0);
                    if (ekimDate < today) {
                        kimExpired = true;
                        ekimCellHtml = `<span class="ekim-expired-cell">${formatDate(row.ekim_valid_until)}</span><br><span class="badge-expired">KEDALUWARSA</span>`;
                    }
                }

                const trClass = kimExpired ? ' class="tr-expired"' : '';
                const totalTidak = parseInt(row.total_tidak) || 0;
                const totalDokExpired = parseInt(row.total_dokumen_expired) || 0;
                const dokExpiredList = row.dokumen_expired_list || '';
                const ekimBlocked = !kimExpired && totalTidak > 0 && row.status_approval !== 'approved';
                const operasiCell = kimExpired
                    ? `<span class="badge badge-danger" title="KIM habis masa berlaku — kendaraan tidak dapat beroperasi">&#10006; Tidak Beroperasi</span>`
                    : totalDokExpired > 0
                        ? `<span class="badge badge-danger" title="Dokumen kadaluarsa: ${dokExpiredList} — kendaraan tidak dapat beroperasi">&#10006; Dokumen Kadaluarsa</span>`
                        : ekimBlocked
                            ? `<span class="badge badge-danger" title="Ada ${totalTidak} item pemeriksaan berstatus TIDAK BAIK — EKIM tidak dapat diterbitkan sampai diperbaiki">&#9888; EKIM Diblokir</span>`
                            : `<span class="badge ${statusBadge}">${statusText}</span>`;
                
                return `
                    <tr${trClass}>
                        <td>${no}</td>
                        <td><span class="badge ${jenisBadge}">${jenisLabel(row.jenis_kendaraan)}</span></td>
                        <td><strong>${row.nomor_polisi}</strong></td>
                        <td>${row.nama_transport || '-'}</td>
                        <td>${row.merk_mobil || '-'}</td>
                        <td>${formatDate(row.tanggal_pemeriksaan)}</td>
                        <td>${ekimCellHtml}</td>
                        <td>${operasiCell}</td>
                        <td><span class="badge ${statusBadge}">${progressPct.toFixed(0)}%</span></td>
                        <td style="text-align:center;">${buildTTDCell(row)}</td>
                        <td style="white-space: nowrap;">
                            <button class="btn-action btn-view" title="Lihat Detail" onclick="viewDetail(${row.id}, '${row.jenis_kendaraan || 'SPBU'}')">
                                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                            <button class="btn-action btn-edit" title="Edit Data" onclick="editData(${row.id}, '${row.jenis_kendaraan || 'SPBU'}')"${canEditChecklist ? '' : ' style="display:none"'}>
                                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </button>
                            <button class="btn-action btn-delete" title="Hapus Data" onclick="deleteData(${row.id}, '${row.nomor_polisi}')"${canEditChecklist ? '' : ' style="display:none"'}>
                                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                            </button>
                            <button class="btn-action btn-verify" title="Verifikasi TTD Digital" onclick="openVerifyTtd('${row.verification_url || ''}')">
                                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');
        }
        
        // Update pagination
        function updatePagination() {
            document.getElementById('pageInfo').textContent = `Halaman ${currentPage} dari ${totalPages}`;
            document.getElementById('btnFirst').disabled = currentPage === 1;
            document.getElementById('btnPrev').disabled = currentPage === 1;
            document.getElementById('btnNext').disabled = currentPage === totalPages;
            document.getElementById('btnLast').disabled = currentPage === totalPages;
        }
        
        // Update statistics
        async function updateStatistics() {
            try {
                // Get statistics for all data (without filters)
                const response = await fetch('load.php?limit=10000&_t=' + Date.now(), {
                    cache: 'no-cache'
                });
                const result = await response.json();
                
                if (result.success) {
                    const allRecords = result.data;
                    const total = allRecords.length;
                    
                    // Count by jenis
                    const spbuCount = allRecords.filter(row => row.jenis_kendaraan === 'SPBU').length;
                    const industriCount = allRecords.filter(row => row.jenis_kendaraan === 'INDUSTRI').length;
                    
                    // Count this month
                    const today = new Date(); today.setHours(0,0,0,0);
                    const thisMonth = allRecords.filter(row => {
                        const date = new Date(row.tanggal_pemeriksaan);
                        const now = new Date();
                        return date.getMonth() === now.getMonth() && date.getFullYear() === now.getFullYear();
                    }).length;

                    // Count KIM expired
                    const kimExpiredCount = allRecords.filter(row => {
                        if (!row.ekim_valid_until) return false;
                        const d = new Date(row.ekim_valid_until); d.setHours(0,0,0,0);
                        return d < today;
                    }).length;
                    
                    document.getElementById('statTotal').textContent = total;
                    document.getElementById('statSpbu').textContent = spbuCount;
                    document.getElementById('statIndustri').textContent = industriCount;
                    document.getElementById('statBulanIni').textContent = thisMonth;
                    document.getElementById('statKimExpired').textContent = kimExpiredCount;

                    // Semester inspection countdown
                    const now = new Date(); now.setHours(0,0,0,0);
                    const year = now.getFullYear();
                    const candidates = [
                        new Date(year, 1, 25),    // Feb 25 this year
                        new Date(year, 7, 25),    // Aug 25 this year
                        new Date(year + 1, 1, 25) // Feb 25 next year
                    ];
                    // Pick the next upcoming date (strictly after today or today itself)
                    const nextSemester = candidates.find(d => d >= now) || candidates[candidates.length - 1];
                    const diffDays = Math.round((nextSemester - now) / (1000 * 60 * 60 * 24));
                    const opts = { day: 'numeric', month: 'long', year: 'numeric' };
                    document.getElementById('semesterNextDate').textContent = nextSemester.toLocaleDateString('id-ID', opts);
                    const cdEl = document.getElementById('semesterCountdown');
                    if (diffDays === 0) {
                        cdEl.textContent = 'HARI INI';
                        cdEl.style.background = '#c8102e';
                    } else {
                        cdEl.textContent = diffDays + ' hari lagi';
                    }
                }
            } catch (error) {
                console.error('Error updating statistics:', error);
            }
        }
        
        // Go to page
        function goToPage(page) {
            if (page < 1 || page > totalPages) return;
            loadData(page);
        }
        
        // Filter data
        function filterData() {
            loadData(1);
        }
        
        // Clear filters
        function clearFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('dateFrom').value = '';
            document.getElementById('dateTo').value = '';
            loadData(1);
        }
        
        // View detail
        function viewDetail(id, jenisKendaraan) {
            const formPage = jenisKendaraan === 'INDUSTRI' ? 'index-industri.html' : 'index.html';
            window.open(`${formPage}?id=${id}&mode=view`, '_blank');
        }
        
        // Edit data
        function editData(id, jenisKendaraan) {
            const formPage = jenisKendaraan === 'INDUSTRI' ? 'index-industri.html' : 'index.html';
            window.location.href = `${formPage}?id=${id}`;
        }
        
        // Delete data
        async function deleteData(id, nomorPolisi) {
            if (!confirm(`Yakin ingin menghapus data dengan Nomor Polisi ${nomorPolisi}?`)) {
                return;
            }
            
            try {
                const response = await fetch('delete.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({id: id})
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Data berhasil dihapus');
                    loadData(currentPage);
                } else {
                    alert('Gagal menghapus data: ' + result.message);
                }
            } catch (error) {
                console.error('Error deleting data:', error);
                alert('Gagal menghapus data');
            }
        }
        
        // Export to Excel
        function exportToExcel() {
            const search = document.getElementById('searchInput').value;
            const dateFrom = document.getElementById('dateFrom').value;
            const dateTo = document.getElementById('dateTo').value;
            
            const params = new URLSearchParams({
                search: search,
                dateFrom: dateFrom,
                dateTo: dateTo
            });
            
            // Add jenis filter if active
            if (currentJenisFilter) {
                params.append('jenis', currentJenisFilter);
            }
            
            window.location.href = `export.php?${params}`;
        }
        
        // Format date
        function formatDate(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            const options = {day: '2-digit', month: 'short', year: 'numeric'};
            return date.toLocaleDateString('id-ID', options);
        }

        // Buka halaman verifikasi TTD Digital berdasarkan QR token
        function openVerifyTtd(verificationUrl) {
            if (!verificationUrl) {
                alert('Formulir ini belum di-submit untuk tanda tangan digital, sehingga QR Code verifikasi belum tersedia.');
                return;
            }
            window.open(verificationUrl, '_blank');
        }

        // Build TTD status cell
        function buildTTDCell(row) {
            const hsseOk    = !!(row.ttd_hsse_nama    && row.ttd_hsse_timestamp);
            const manajerOk = !!(row.ttd_manajer_nama && row.ttd_manajer_timestamp);

            const hsseTitle    = hsseOk    ? `HSSE: ${row.ttd_hsse_nama}`    : 'Belum ditandatangani (HSSE)';
            const manajerTitle = manajerOk ? `Manajer: ${row.ttd_manajer_nama}` : 'Belum ditandatangani (Manajer)';

            const hsseIcon    = hsseOk    ? `<span class="ttd-badge-ok"  title="${hsseTitle}">HSSE ✓</span>`
                                          : `<span class="ttd-badge-no"  title="${hsseTitle}">HSSE –</span>`;
            const manajerIcon = manajerOk ? `<span class="ttd-badge-ok"  title="${manajerTitle}">MGR ✓</span>`
                                          : `<span class="ttd-badge-no"  title="${manajerTitle}">MGR –</span>`;

            return `<span style="display:inline-flex;gap:4px;">${hsseIcon}${manajerIcon}</span>`;
        }
        
        // Show error
        function showError(message) {
            document.getElementById('dataTableBody').innerHTML = 
                `<tr><td colspan="11" class="no-data" style="color: red;">${message}</td></tr>`;
        }
    </script>
</body>
</html>
