<?php
/**
 * Admin Dashboard
 * PRIMA (Pertamina Checklist Mobil Tangki)
 */

require_once 'auth.php';
requireAdmin();

require_once 'config.php';

$user = getCurrentUser();

// Get statistics
try {
    $db = Database::getInstance()->getConnection();
    
    // Count pending registrations
    $stmt = $db->query("SELECT COUNT(*) as count FROM user_registrations WHERE status = 'pending'");
    $pending_count = $stmt->fetch()['count'];
    
    // Count total users
    $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE username != 'admin'");
    $total_users = $stmt->fetch()['count'];
    
    // Count active users
    $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE status = 'active' AND username != 'admin'");
    $active_users = $stmt->fetch()['count'];
    
    // Count total checklists
    $stmt = $db->query("SELECT COUNT(*) as count FROM formulir_checklist");
    
    $total_checklists = $stmt->fetch()['count'];
    
    // Get vehicle alerts
    $vehicle_alerts = getVehicleAlerts(30);
    $alert_count = count($vehicle_alerts);

    // Count pending digital signatures (formulir menunggu tanda tangan)
    $stmt = $db->query("SELECT COUNT(*) as count FROM formulir_checklist WHERE status_approval IN ('pending_hsse','signed_hsse')");
    $ttd_pending_count = $stmt->fetch()['count'];
    
} catch(Exception $e) {
    error_log("Dashboard Error: " . $e->getMessage());
    $pending_count = 0;
    $total_users = 0;
    $active_users = 0;
    $total_checklists = 0;
    $alert_count = 0;
    $ttd_pending_count = 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - PRIMA</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background: #eef2f7;
            min-height: 100vh;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        }

        .app-shell {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 24px;
        }

        .nav-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 18px;
        }


        .nav-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 18px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.04);
            color: #e7f3ff;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.25s ease, transform 0.25s ease;
        }

        .nav-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 18px;
        }

        .nav-link:hover,
        .nav-link.active {
            background: rgba(255, 255, 255, 0.14);
            color: #ffffff;
            transform: translateX(2px);
        }

        
        .nav-group .nav-link {
            font-size: 15px;
            position: relative;
        }

        .nav-group .nav-link .arrow {
            margin-left: 8px;
            font-size: 12px;
        }

        .nav-submenu.hidden {
            display: none;
        }

        .nav-submenu a {
            padding: 12px 16px;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.05);
            color: #d4e7f9;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.25s ease;
        }

        .nav-submenu a:hover,
        .nav-submenu a.active {
            background: rgba(255, 255, 255, 0.12);
            color: #ffffff;
        }


        .sidebar {
            background: #10334d;
            border-radius: 28px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.14);
            color: #f4f7fb;
            padding: 32px 24px;
            display: flex;
            flex-direction: column;
            min-height: calc(100vh - 40px);
        }

        .sidebar .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 32px;
        }

        .sidebar .brand h2 {
            font-size: 20px;
            letter-spacing: 0.5px;
            margin: 0;
            color: #ffffff;
        }

        .sidebar nav {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 36px;
        }

        .sidebar .nav-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 18px;
            border-radius: 16px;
            background: transparent;
            color: #dbe9f2;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.25s ease;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255, 255, 255, 0.08);
            color: #ffffff;
            transform: translateX(2px);
        }

        .sidebar .nav-title {
            margin-top: 18px;
            margin-bottom: 12px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: #8fb8d4;
        }

        .sidebar .logo-dot {
            width: 12px;
            height: 12px;
            background: #ffd700;
            border-radius: 50%;
        }

        .sidebar .sidebar-footer {
            margin-top: auto;
            font-size: 13px;
            line-height: 1.7;
            color: #a7c8dc;
        }

        .dashboard-container {
            background: transparent;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .dashboard-header {
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.08);
            margin-bottom: 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .dashboard-header h1 {
            color: #333;
            font-size: 28px;
            margin: 0;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-name {
            text-align: right;
        }

        .user-name strong {
            display: block;
            color: #333;
            font-size: 16px;
        }

        .user-name span {
            display: block;
            color: #666;
            font-size: 13px;
        }

        .btn-logout {
            padding: 10px 24px;
            background: #dc3545;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-logout:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 4px solid #c8102e;
        }

        .stat-card.warning {
            border-left-color: #ffc107;
        }

        .stat-card.success {
            border-left-color: #28a745;
        }

        .stat-card.info {
            border-left-color: #17a2b8;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .stat-value {
            color: #333;
            font-size: 32px;
            font-weight: 700;
        }

        .stat-badge {
            display: inline-block;
            padding: 4px 12px;
            background: #fee;
            color: #c00;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 8px;
        }

        .stat-badge.success {
            background: #efe;
            color: #060;
        }

        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .menu-card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-decoration: none;
            transition: all 0.3s;
            border-top: 4px solid #c8102e;
        }

        .menu-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }

        .menu-card h3 {
            color: #333;
            font-size: 20px;
            margin-bottom: 10px;
        }

        .menu-card p {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
        }

        .menu-badge {
            display: inline-block;
            padding: 4px 12px;
            background: #c8102e;
            color: white;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 12px;
        }

        /* ========================================
           RESPONSIVE MOBILE DESIGN
           ======================================== */

        @media (max-width: 968px) {
            .app-shell {
                grid-template-columns: 1fr;
            }

            .sidebar {
                min-height: auto;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .menu-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .dashboard-container {
                padding: 15px;
            }

            .dashboard-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
                padding: 20px;
            }

            .dashboard-header h1 {
                font-size: 22px;
            }

            .user-info {
                flex-direction: column;
                gap: 10px;
            }

            .user-name {
                text-align: center;
                font-size: 13px;
            }

            .btn-logout {
                width: 100%;
                text-align: center;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .stat-card h3 {
                font-size: 32px;
            }

            .stat-card p {
                font-size: 12px;
            }

            .menu-grid {
                gap: 15px;
            }

            .menu-item h3 {
                font-size: 18px;
            }

            .menu-item p {
                font-size: 13px;
            }
        }

        @media (max-width: 480px) {
            .dashboard-header h1 {
                font-size: 20px;
            }

            .stat-card {
                padding: 16px;
            }

            .stat-card h3 {
                font-size: 28px;
            }

            .menu-item {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="app-shell">
        <aside class="sidebar">
            <div class="brand">
                <div class="logo-dot"></div>
                <h2>PRIMA Admin</h2>
            </div>

            <div class="nav-title">Menu</div>
            <nav>
                <a href="admin-dashboard.php" class="nav-link active">Dashboard</a>
            <div class="nav-group">
                <a href="#" class="nav-link nav-link-toggle" id="toggleChecklist">
                Input Checklist
                <span class="arrow">▼</span>
                </a>
                <div class="nav-submenu hidden" id="checklistSubmenu">
                <a href="index.html?jenis=spbu" class="submenu-link">Checklist SPBU</a>
                <a href="index-industri.html?jenis=industri" class="submenu-link">Checklist Industri</a>
                <a href="#" class="submenu-link active" data-target="section-data">Data & Database KIM</a>
                </div>
            </div>
                <a href="list.php" class="nav-link">Data Checklist</a>
                <a href="register-vehicle.php" class="nav-link">Registrasi Kendaraan</a>
            </nav>

            <div class="sidebar-footer">
                <strong>Akun:</strong> <?php echo htmlspecialchars($user['username']); ?><br>
                <strong>Status:</strong> Administrator
            </div>
        </aside>

        <div class="dashboard-container">
            <div class="dashboard-header">
                <div>
                    <h1>Admin Dashboard</h1>
                    <p style="color:#6b7280; margin:8px 0 0;">Selamat datang, <?php echo htmlspecialchars($user['full_name']); ?>. Gunakan menu di samping untuk akses cepat.</p>
                </div>
                <div class="user-info">
                    <div class="user-name">
                        <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                        <span>Administrator</span>
                    </div>
                    <a href="logout.php" class="btn-logout">Logout</a>
                </div>
            </div>

        <div class="stats-grid">
            <div class="stat-card warning">
                <div class="stat-label">Pendaftaran Pending</div>
                <div class="stat-value"><?php echo $pending_count; ?></div>
                <?php if ($pending_count > 0): ?>
                    <span class="stat-badge">Perlu Review</span>
                <?php endif; ?>
            </div>

            <div class="stat-card success">
                <div class="stat-label">User Aktif</div>
                <div class="stat-value"><?php echo $active_users; ?></div>
                <span class="stat-badge success">Online</span>
            </div>

            <div class="stat-card">
                <div class="stat-label">Total User</div>
                <div class="stat-value"><?php echo $total_users; ?></div>
            </div>

            <div class="stat-card info">
                <div class="stat-label">Total Checklist</div>
                <div class="stat-value"><?php echo $total_checklists; ?></div>
            </div>
        </div>

        <div class="menu-grid">
            <a href="approve-registrations.php" class="menu-card">
                <h3>Review Pendaftaran</h3>
                <p>Lihat dan approve/reject pendaftaran user baru yang masuk</p>
                <?php if ($pending_count > 0): ?>
                    <span class="menu-badge"><?php echo $pending_count; ?> Pending</span>
                <?php endif; ?>
            </a>

            <a href="vehicle-alerts.php" class="menu-card" style="border-top: 5px solid #ff6b6b;">
                <h3>Notifikasi Inspeksi Kendaraan</h3>
                <p>Pantau kendaraan yang akan/sudah expired KIM, perlu inspeksi ulang</p>
                <?php if ($alert_count > 0): ?>
                    <span class="menu-badge" style="background: #ff6b6b; color: white;"><?php echo $alert_count; ?> Alert</span>
                <?php else: ?>
                    <span class="menu-badge" style="background: #28a745; color: white;">✓ Semua OK</span>
                <?php endif; ?>
            </a>

            <a href="manage-users.php" class="menu-card">
                <h3>Kelola User</h3>
                <p>Manage user yang sudah terdaftar, aktifkan/nonaktifkan akun</p>
            </a>

            <a href="list.php" class="menu-card">
                <h3>Data Checklist</h3>
                <p>Lihat semua data checklist yang telah diinput user</p>
            </a>

            <a href="audit-logs.php" class="menu-card">
                <h3>Audit Logs</h3>
                <p>Monitoring aktivitas user dan perubahan data sistem</p>
            </a>

            <a href="home.php" class="menu-card">
                <h3>Input Checklist</h3>
                <p>Input data checklist baru untuk SPBU atau Industri</p>
            </a>

            <a href="https://docs.google.com/forms/d/e/1FAIpQLSdwfxfEst5eZ0fNQW_Gc4mvG-bPCwJZBcS-IqwYu4HZJNQLCg/viewform" target="_blank" rel="noopener noreferrer" class="menu-card">
                <h3>Isi Formulir Penerbitan KIM</h3>
                <p>Form penerbitan KIM untuk mobil tangki (Khusus Admin)</p>
                <span class="menu-badge">Google Form →</span>
            </a>

            <a href="system-settings.php" class="menu-card">
                <h3>Pengaturan Sistem</h3>
                <p>Konfigurasi sistem, backup database, dan maintenance</p>
            </a>

            <a href="pending-approval.php" class="menu-card" style="border-top: 4px solid #7c3aed;">
                <h3>Antrian Tanda Tangan Digital</h3>
                <p>Kelola formulir yang menunggu tanda tangan HSSE dan Manajer HSSE</p>
                <?php if ($ttd_pending_count > 0): ?>
                    <span class="menu-badge" style="background: #7c3aed; color: white;"><?php echo $ttd_pending_count; ?> Menunggu TTD</span>
                <?php else: ?>
                    <span class="menu-badge" style="background: #059669; color: white;">✓ Semua Selesai</span>
                <?php endif; ?>
            </a>

            <a href="verify-ttd.php" class="menu-card" style="border-top: 4px solid #059669;">
                <h3>Verifikasi TTD Digital</h3>
                <p>Verifikasi keaslian tanda tangan digital RSA-2048/SHA-256 pada formulir checklist</p>
                <span class="menu-badge" style="background: #059669;">Kriptografi</span>
            </a>

            <a href="generate-keys.php" class="menu-card" style="border-top: 4px solid #0891b2;">
                <h3>Kelola Kunci RSA</h3>
                <p>Generate dan kelola pasangan kunci RSA-2048 untuk sistem tanda tangan digital</p>
                <span class="menu-badge" style="background: #0891b2;">Admin Only</span>
            </a>
        </div>
    </div>
</body>
</html>
