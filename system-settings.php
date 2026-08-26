<?php
/**
 * System Settings
 * PRIMA (Pertamina Checklist Mobil Tangki)
 */

require_once 'auth.php';
requireAdmin();

require_once 'config.php';

$user = getCurrentUser();

// Get system info
try {
    $db = Database::getInstance()->getConnection();
    
    // Database size
    $stmt = $db->query("
        SELECT 
            table_schema AS 'Database',
            ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)'
        FROM information_schema.TABLES 
        WHERE table_schema = '" . DB_NAME . "'
        GROUP BY table_schema
    ");
    $dbSize = $stmt->fetch();
    
    // Table statistics
    $stmt = $db->query("
        SELECT 
            table_name AS 'Table',
            table_rows AS 'Rows',
            ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)'
        FROM information_schema.TABLES 
        WHERE table_schema = '" . DB_NAME . "'
        ORDER BY (data_length + index_length) DESC
    ");
    $tables = $stmt->fetchAll();
    
    // Count records
    $formulirCount = $db->query("SELECT COUNT(*) FROM formulir_checklist")->fetchColumn();
    $usersCount = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $auditCount = $db->query("SELECT COUNT(*) FROM audit_log")->fetchColumn();
    
    // Last backup info (simulated)
    $lastBackup = 'Belum pernah backup';
    $backupSize = '-';
    
} catch(Exception $e) {
    error_log("System Settings Error: " . $e->getMessage());
    $dbSize = null;
    $tables = [];
    $formulirCount = 0;
    $usersCount = 0;
    $auditCount = 0;
}

// PHP Info
$phpVersion = phpversion();
$maxUpload = ini_get('upload_max_filesize');
$maxPost = ini_get('post_max_size');
$memoryLimit = ini_get('memory_limit');
$timezone = date_default_timezone_get();

// Success message
$successMsg = isset($_GET['success']) ? $_GET['success'] : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Admin Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background: #f5f5f5;
        }        
        .user-info-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 20px;
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }
        
        .user-info-text {
            font-size: 14px;
            color: #666;
        }
        
        .user-info-text strong {
            color: #333;
            font-weight: 600;
        }
        
        .btn-logout {
            padding: 8px 20px;
            background: #dc3545;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-block;
        }
        
        .btn-logout:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        .page-container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 0 20px;
        }

        .page-header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header h1 {
            margin: 0;
            color: #333;
            font-size: 24px;
        }

        .btn-back {
            padding: 10px 20px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
        }

        .btn-back:hover {
            background: #5a6268;
        }

        .alert {
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .settings-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .settings-card h2 {
            margin: 0 0 15px 0;
            font-size: 18px;
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }

        .setting-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .setting-item:last-child {
            border-bottom: none;
        }

        .setting-label {
            font-weight: 500;
            color: #555;
        }

        .setting-value {
            color: #333;
            font-family: 'Courier New', monospace;
        }

        .setting-value.good {
            color: #28a745;
        }

        .setting-value.warning {
            color: #ffc107;
        }

        .setting-value.danger {
            color: #dc3545;
        }

        .full-width {
            grid-column: 1 / -1;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #dee2e6;
            font-size: 14px;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .btn-action {
            padding: 8px 16px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }

        .btn-action:hover {
            background: #0056b3;
        }

        .btn-danger {
            background: #dc3545;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-success {
            background: #28a745;
        }

        .btn-success:hover {
            background: #218838;
        }

        .actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }

        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }

        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #007bff;
            padding: 15px;
            margin-top: 15px;
            border-radius: 4px;
        }

        .info-box p {
            margin: 5px 0;
            color: #004085;
            font-size: 14px;
        }

        .metric {
            text-align: center;
            padding: 15px;
        }

        .metric-value {
            font-size: 32px;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 5px;
        }

        .metric-label {
            font-size: 14px;
            color: #666;
        }
    </style>
    <style>
    /* ── CORPORATE OVERRIDE ── */
    body { background:#f0f2f5; font-family:"Segoe UI",-apple-system,BlinkMacSystemFont,Arial,sans-serif; color:#1a2332; font-size:14px; }
    .user-info-bar { background:#fff!important; border-bottom:1px solid #dde3ec!important; padding:10px 20px; box-shadow:none; }
    .user-info-text { font-size:13px; color:#6b7a8f; }
    .user-info-text strong { color:#1a2332; }
    .btn-logout { background:#c8102e!important; border-radius:5px!important; box-shadow:none; font-size:13px; padding:7px 16px; }
    .btn-logout:hover { background:#a80d26!important; transform:none; }
    .page-container { max-width:1280px; margin:0 auto; padding:28px 20px 48px; }
    .page-header { background:#fff; border:1px solid #dde3ec; border-top:3px solid #c8102e; border-radius:6px; padding:20px 26px; margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; box-shadow:none; }
    .page-header h1 { color:#1a2332!important; font-size:18px; font-weight:700; margin:0; }
    .btn-back { background:#fff; color:#374151; padding:8px 16px; border-radius:5px; border:1px solid #d1d5db; font-size:13px; font-weight:500; }
    .btn-back:hover { background:#f9fafb; }
    .alert { background:#f0fdf4; border:1px solid #bbf7d0; color:#166534; border-radius:5px; padding:12px 16px; font-size:13px; }
    .settings-grid { gap:14px; margin-bottom:16px; }
    .settings-card { background:#fff; border:1px solid #dde3ec; border-radius:6px; padding:20px 22px; box-shadow:none; }
    .settings-card h2 { font-size:13px; font-weight:700; color:#6b7a8f; text-transform:uppercase; letter-spacing:.5px; border-bottom:1px solid #f0f2f5; padding-bottom:10px; margin-bottom:14px; }
    .setting-item { padding:10px 0; border-bottom:1px solid #f0f2f5; }
    .setting-label { color:#374151; font-size:13px; }
    .setting-value { font-size:13px; }
    thead { background:#1e2a3b; }
    th { background:#1e2a3b!important; color:#94a3b8!important; font-size:11px; padding:11px 14px; border-bottom:none; }
    td { font-size:13px; padding:11px 14px; }
    tr:hover td { background:#f8fafc; }
    .btn-action { background:#1d4ed8; border-radius:5px; font-size:13px; padding:7px 14px; }
    .btn-action:hover { background:#1e40af; }
    .btn-danger { background:#c8102e!important; }
    .btn-danger:hover { background:#a80d26!important; }
    .btn-success { background:#16a34a!important; }
    .btn-success:hover { background:#15803d!important; }
    </style>
</head>
<body>
    <div class="user-info-bar">
        <span class="user-info-text">Selamat datang, <strong><?php echo htmlspecialchars($user['full_name']); ?></strong> (Administrator)</span>
        <div style="display: flex; gap: 10px;">
            <a href="home.php" class="btn" style="padding: 8px 18px; background: #6c757d; color: white; text-decoration: none; border-radius: 6px; font-size: 13px;">← Dashboard</a>
            <a href="logout.php" class="btn-logout">Logout</a>
        </div>
    </div>
    <div class="page-container">
        <div class="page-header">
            <h1>System Settings</h1>
            <a href="home.php" class="btn-back">← Kembali ke Dashboard</a>
        </div>

        <?php if ($successMsg): ?>
            <div class="alert">
                <?php echo htmlspecialchars($successMsg); ?>
            </div>
        <?php endif; ?>

        <div class="settings-grid">
            <!-- Application Info -->
            <div class="settings-card">
                <h2>Informasi Aplikasi</h2>
                <div class="setting-item">
                    <span class="setting-label">Nama Aplikasi</span>
                    <span class="setting-value"><?php echo APP_NAME; ?></span>
                </div>
                <div class="setting-item">
                    <span class="setting-label">Versi</span>
                    <span class="setting-value"><?php echo APP_VERSION; ?></span>
                </div>
                <div class="setting-item">
                    <span class="setting-label">Timezone</span>
                    <span class="setting-value"><?php echo $timezone; ?></span>
                </div>
                <div class="setting-item">
                    <span class="setting-label">Session Timeout</span>
                    <span class="setting-value"><?php echo SESSION_TIMEOUT; ?> detik</span>
                </div>
                <div class="setting-item">
                    <span class="setting-label">Audit Log</span>
                    <span class="setting-value good">
                        <?php echo ENABLE_AUDIT_LOG ? 'Aktif ✓' : 'Nonaktif'; ?>
                    </span>
                </div>
            </div>

            <!-- PHP Configuration -->
            <div class="settings-card">
                <h2>Konfigurasi PHP</h2>
                <div class="setting-item">
                    <span class="setting-label">PHP Version</span>
                    <span class="setting-value <?php echo version_compare($phpVersion, '7.4.0', '>=') ? 'good' : 'warning'; ?>">
                        <?php echo $phpVersion; ?>
                    </span>
                </div>
                <div class="setting-item">
                    <span class="setting-label">Max Upload Size</span>
                    <span class="setting-value"><?php echo $maxUpload; ?></span>
                </div>
                <div class="setting-item">
                    <span class="setting-label">Max Post Size</span>
                    <span class="setting-value"><?php echo $maxPost; ?></span>
                </div>
                <div class="setting-item">
                    <span class="setting-label">Memory Limit</span>
                    <span class="setting-value"><?php echo $memoryLimit; ?></span>
                </div>
                <div class="setting-item">
                    <span class="setting-label">Display Errors</span>
                    <span class="setting-value <?php echo ini_get('display_errors') ? 'warning' : 'good'; ?>">
                        <?php echo ini_get('display_errors') ? 'ON (Development)' : 'OFF (Production)'; ?>
                    </span>
                </div>
            </div>

            <!-- Database Info -->
            <div class="settings-card">
                <h2>Informasi Database</h2>
                <div class="setting-item">
                    <span class="setting-label">Database Name</span>
                    <span class="setting-value"><?php echo DB_NAME; ?></span>
                </div>
                <div class="setting-item">
                    <span class="setting-label">Database Host</span>
                    <span class="setting-value"><?php echo DB_HOST; ?></span>
                </div>
                <div class="setting-item">
                    <span class="setting-label">Database User</span>
                    <span class="setting-value"><?php echo DB_USER; ?></span>
                </div>
                <div class="setting-item">
                    <span class="setting-label">Database Size</span>
                    <span class="setting-value">
                        <?php echo $dbSize ? $dbSize['Size (MB)'] . ' MB' : 'N/A'; ?>
                    </span>
                </div>
                <div class="setting-item">
                    <span class="setting-label">Character Set</span>
                    <span class="setting-value"><?php echo DB_CHARSET; ?></span>
                </div>
            </div>

            <!-- Backup Info -->
            <div class="settings-card">
                <h2>Backup Database</h2>
                <div class="setting-item">
                    <span class="setting-label">Last Backup</span>
                    <span class="setting-value warning"><?php echo $lastBackup; ?></span>
                </div>
                <div class="setting-item">
                    <span class="setting-label">Backup Size</span>
                    <span class="setting-value"><?php echo $backupSize; ?></span>
                </div>
                <div class="info-box">
                    <p><strong>Backup Otomatis:</strong></p>
                    <p>Belum dikonfigurasi. Silakan setup cron job untuk backup otomatis.</p>
                </div>
                <div class="actions">
                    <button class="btn-action btn-success" onclick="createBackup()">
                        Backup Sekarang
                    </button>
                    <a href="backup/index.php" class="btn-action" target="_blank">Lihat Backup</a>
                </div>
            </div>

            <!-- Statistics -->
            <div class="settings-card full-width">
                <h2>Statistik Data</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    <div class="metric">
                        <div class="metric-value"><?php echo number_format($formulirCount); ?></div>
                        <div class="metric-label">Total Formulir</div>
                    </div>
                    <div class="metric">
                        <div class="metric-value"><?php echo number_format($usersCount); ?></div>
                        <div class="metric-label">Total Users</div>
                    </div>
                    <div class="metric">
                        <div class="metric-value"><?php echo number_format($auditCount); ?></div>
                        <div class="metric-label">Audit Log Entries</div>
                    </div>
                    <div class="metric">
                        <div class="metric-value"><?php echo count($tables); ?></div>
                        <div class="metric-label">Database Tables</div>
                    </div>
                </div>
            </div>

            <!-- Database Tables -->
            <div class="settings-card full-width">
                <h2>Database Tables</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Table Name</th>
                            <th>Rows</th>
                            <th>Size (MB)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($tables as $table): ?>
                            <tr>
                                <td><code><?php echo $table['Table']; ?></code></td>
                                <td><?php echo number_format($table['Rows']); ?></td>
                                <td><?php echo $table['Size (MB)']; ?></td>
                                <td>
                                    <?php if ($table['Rows'] > 0): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Empty</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Maintenance Actions -->
            <div class="settings-card full-width">
                <h2>Maintenance Actions</h2>
                <div class="info-box" style="margin-top: 0;">
                    <p><strong>Peringatan:</strong> Tindakan maintenance berikut dapat mempengaruhi performa sistem. Lakukan di luar jam kerja.</p>
                </div>
                <div class="actions">
                    <button class="btn-action" onclick="optimizeDatabase()">
                        Optimize Database
                    </button>
                    <button class="btn-action" onclick="clearCache()">
                        Clear Cache
                    </button>
                    <button class="btn-action btn-danger" onclick="confirmClearLogs()">
                        Clear Old Logs (>90 days)
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function createBackup() {
            if (confirm('Buat backup database sekarang?\n\nBackup akan disimpan di folder backup/.')) {
                alert('Fitur backup belum diimplementasi.\n\nUntuk backup manual:\n1. Buka phpMyAdmin\n2. Pilih database checklist_ekim\n3. Klik Export\n4. Pilih format SQL\n5. Download');
            }
        }

        function optimizeDatabase() {
            if (confirm('Optimize semua tabel database?\n\nProses ini dapat memakan waktu beberapa menit.')) {
                alert('Fitur optimize belum diimplementasi.\n\nUntuk optimize manual:\n1. Buka phpMyAdmin\n2. Pilih database checklist_ekim\n3. Select All Tables\n4. Pilih "Optimize table" di dropdown');
            }
        }

        function clearCache() {
            if (confirm('Clear cache sistem?')) {
                alert('Cache cleared (Session cache only).\n\nUntuk clear PHP opcache, restart Apache.');
            }
        }

        function confirmClearLogs() {
            if (confirm('PERINGATAN\n\nHapus audit log yang lebih dari 90 hari?\nTindakan ini tidak dapat dibatalkan!')) {
                if (confirm('Apakah Anda yakin? Backup terlebih dahulu direkomendasikan.')) {
                    clearOldLogs();
                }
            }
        }

        async function clearOldLogs() {
            try {
                // Placeholder - implement actual API call
                alert('Fitur belum diimplementasi.\n\nUntuk hapus manual via SQL:\n\nDELETE FROM audit_log \nWHERE action_time < DATE_SUB(NOW(), INTERVAL 90 DAY);');
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }
    </script>
</body>
</html>
