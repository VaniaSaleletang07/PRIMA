<?php
/**
 * Audit Logs Viewer
 * PRIMA (Pertamina Checklist Mobil Tangki)
 */

require_once 'auth.php';
requireAdmin();

require_once 'config.php';

$user = getCurrentUser();

// Get filter parameters
$dateFrom = isset($_GET['dateFrom']) ? sanitizeInput($_GET['dateFrom']) : '';
$dateTo = isset($_GET['dateTo']) ? sanitizeInput($_GET['dateTo']) : '';
$action = isset($_GET['action']) ? sanitizeInput($_GET['action']) : '';
$userName = isset($_GET['userName']) ? sanitizeInput($_GET['userName']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$where = "WHERE 1=1";
$params = [];

if (!empty($dateFrom)) {
    $where .= " AND DATE(created_at) >= :dateFrom";
    $params[':dateFrom'] = $dateFrom;
}

if (!empty($dateTo)) {
    $where .= " AND DATE(created_at) <= :dateTo";
    $params[':dateTo'] = $dateTo;
}

if (!empty($action)) {
    $where .= " AND action = :action";
    $params[':action'] = $action;
}

if (!empty($userName)) {
    $where .= " AND user_name LIKE :userName";
    $params[':userName'] = "%$userName%";
}

// Get audit logs
try {
    $db = Database::getInstance()->getConnection();
    
    // Get total count
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM audit_log $where");
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];
    $totalPages = ceil($total / $limit);
    
    // Get logs
    $stmt = $db->prepare("
        SELECT 
            al.*,
            f.nomor_polisi,
            f.nama_transport
        FROM audit_log al
        LEFT JOIN formulir_checklist f ON al.formulir_id = f.id
        $where
        ORDER BY created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $logs = $stmt->fetchAll();
    
    // Get unique users for filter
    $stmt = $db->query("SELECT DISTINCT user_name FROM audit_log ORDER BY user_name");
    $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch(Exception $e) {
    error_log("Audit Logs Error: " . $e->getMessage());
    $logs = [];
    $users = [];
    $total = 0;
    $totalPages = 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs - Admin Dashboard</title>
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

        .filters-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
            font-size: 14px;
        }

        .filter-group input,
        .filter-group select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .filter-actions {
            display: flex;
            gap: 10px;
        }

        .btn-filter {
            padding: 8px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-filter:hover {
            background: #0056b3;
        }

        .btn-reset {
            padding: 8px 20px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-reset:hover {
            background: #5a6268;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .stat-card h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #666;
        }

        .stat-card .number {
            font-size: 32px;
            font-weight: bold;
            color: #333;
        }

        .logs-table {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
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

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-create {
            background: #d4edda;
            color: #155724;
        }

        .badge-update {
            background: #fff3cd;
            color: #856404;
        }

        .badge-delete {
            background: #f8d7da;
            color: #721c24;
        }

        .badge-login {
            background: #d1ecf1;
            color: #0c5460;
        }

        .badge-logout {
            background: #e2e3e5;
            color: #383d41;
        }

        .badge-approve {
            background: #d4edda;
            color: #155724;
        }

        .badge-reject {
            background: #f8d7da;
            color: #721c24;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state svg {
            width: 80px;
            height: 80px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            padding: 20px;
            background: white;
        }

        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
            font-size: 14px;
        }

        .pagination a:hover {
            background: #f8f9fa;
        }

        .pagination .active {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }

        .pagination .disabled {
            opacity: 0.5;
            pointer-events: none;
        }

        .timestamp {
            color: #666;
            font-size: 13px;
        }

        .ip-address {
            font-family: 'Courier New', monospace;
            color: #666;
            font-size: 13px;
        }

        .description {
            color: #555;
            max-width: 400px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    </style>
    <style>
    /* ── CORPORATE OVERRIDE ── */
    body { background:#f0f2f5; font-family:"Segoe UI",-apple-system,BlinkMacSystemFont,Arial,sans-serif; color:#1a2332; font-size:14px; }
    .user-info-bar { background:#fff!important; border-bottom:1px solid #dde3ec!important; padding:10px 20px; box-shadow:none!important; }
    .user-info-text { font-size:13px; color:#6b7a8f; }
    .user-info-text strong { color:#1a2332; }
    .btn-logout { background:#c8102e!important; border-radius:5px!important; box-shadow:none!important; font-size:13px; padding:7px 16px; }
    .btn-logout:hover { background:#a80d26!important; transform:none; }
    .page-container { max-width:1280px; margin:0 auto; padding:28px 20px 48px; }
    .page-header { background:#fff; border:1px solid #dde3ec; border-top:3px solid #c8102e; border-radius:6px; padding:20px 26px; margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; box-shadow:none; }
    .page-header h1 { color:#1a2332; font-size:18px; font-weight:700; margin:0; }
    .btn-back { background:#fff; color:#374151; padding:8px 16px; border-radius:5px; border:1px solid #d1d5db; font-size:13px; font-weight:500; }
    .btn-back:hover { background:#f9fafb; }
    .filters-card { background:#fff; border:1px solid #dde3ec; border-radius:6px; padding:20px; margin-bottom:16px; box-shadow:none; }
    .stats-grid { gap:14px; margin-bottom:16px; }
    .stat-card { background:#fff; border:1px solid #dde3ec; border-radius:6px; padding:18px 22px; box-shadow:none; }
    .stat-card h3 { color:#6b7a8f; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; margin-bottom:6px; }
    .stat-card .number { font-size:28px; font-weight:700; color:#1a2332; }
    .logs-table { background:#fff; border:1px solid #dde3ec; border-radius:6px; box-shadow:none; }
    thead { background:#1e2a3b!important; }
    th { background:#1e2a3b!important; color:#94a3b8!important; font-size:11px; padding:11px 14px; border-bottom:none; }
    td { font-size:13px; padding:11px 14px; }
    tr:hover td { background:#f8fafc; }
    .btn-filter { background:#c8102e; border-radius:5px; padding:8px 18px; font-size:13px; }
    .btn-filter:hover { background:#a80d26; }
    .btn-reset { background:#fff; color:#374151; border:1px solid #d1d5db; border-radius:5px; padding:8px 18px; font-size:13px; }
    .btn-reset:hover { background:#f9fafb; }
    .pagination a,.pagination span { border-radius:4px; font-size:13px; }
    .pagination .active { background:#c8102e; border-color:#c8102e; }
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
            <h1>Audit Logs</h1>
            <a href="home.php" class="btn-back">← Kembali ke Dashboard</a>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Records</h3>
                <div class="number"><?php echo number_format($total); ?></div>
            </div>
        </div>

        <div class="filters-card">
            <form method="GET" action="">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label>Dari Tanggal</label>
                        <input type="date" name="dateFrom" value="<?php echo htmlspecialchars($dateFrom); ?>">
                    </div>
                    <div class="filter-group">
                        <label>Sampai Tanggal</label>
                        <input type="date" name="dateTo" value="<?php echo htmlspecialchars($dateTo); ?>">
                    </div>
                    <div class="filter-group">
                        <label>Action</label>
                        <select name="action">
                            <option value="">Semua Action</option>
                            <option value="CREATE" <?php echo $action === 'CREATE' ? 'selected' : ''; ?>>CREATE</option>
                            <option value="UPDATE" <?php echo $action === 'UPDATE' ? 'selected' : ''; ?>>UPDATE</option>
                            <option value="DELETE" <?php echo $action === 'DELETE' ? 'selected' : ''; ?>>DELETE</option>
                            <option value="LOGIN" <?php echo $action === 'LOGIN' ? 'selected' : ''; ?>>LOGIN</option>
                            <option value="LOGOUT" <?php echo $action === 'LOGOUT' ? 'selected' : ''; ?>>LOGOUT</option>
                            <option value="APPROVE" <?php echo $action === 'APPROVE' ? 'selected' : ''; ?>>APPROVE</option>
                            <option value="REJECT" <?php echo $action === 'REJECT' ? 'selected' : ''; ?>>REJECT</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>User</label>
                        <select name="userName">
                            <option value="">Semua User</option>
                            <?php foreach($users as $u): ?>
                                <option value="<?php echo htmlspecialchars($u); ?>" <?php echo $userName === $u ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($u); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn-filter">Terapkan Filter</button>
                    <button type="button" class="btn-reset" onclick="location.href='audit-logs.php'">Reset</button>
                </div>
            </form>
        </div>

        <div class="logs-table">
            <?php if (empty($logs)): ?>
                <div class="empty-state">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <h3>Tidak ada log yang ditemukan</h3>
                    <p>Belum ada aktivitas yang tercatat atau sesuai dengan filter yang dipilih.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Waktu</th>
                            <th>Action</th>
                            <th>User</th>
                            <th>Formulir ID</th>
                            <th>Nomor Polisi</th>
                            <th>Deskripsi</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($logs as $log): ?>
                            <tr>
                                <td>
                                    <div class="timestamp">
                                        <?php 
                                            $time = strtotime($log['created_at']);
                                            echo date('d/m/Y', $time) . '<br>' . date('H:i:s', $time);
                                        ?>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                        $actionClass = 'badge-' . strtolower($log['action']);
                                        echo "<span class='badge $actionClass'>{$log['action']}</span>";
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($log['user_name']); ?></td>
                                <td><?php echo $log['formulir_id'] ? '#' . $log['formulir_id'] : '-'; ?></td>
                                <td><?php echo $log['nomor_polisi'] ? htmlspecialchars($log['nomor_polisi']) : '-'; ?></td>
                                <td>
                                    <div class="description" title="<?php echo htmlspecialchars($log['description']); ?>">
                                        <?php echo htmlspecialchars($log['description']); ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="ip-address"><?php echo htmlspecialchars($log['ip_address']); ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php
                            // Build query string for pagination
                            $queryParams = $_GET;
                            unset($queryParams['page']);
                            $queryString = http_build_query($queryParams);
                            $queryString = $queryString ? '&' . $queryString : '';
                        ?>
                        
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo ($page - 1) . $queryString; ?>">← Previous</a>
                        <?php else: ?>
                            <span class="disabled">← Previous</span>
                        <?php endif; ?>

                        <?php
                            $start = max(1, $page - 2);
                            $end = min($totalPages, $page + 2);
                            
                            for ($i = $start; $i <= $end; $i++):
                                if ($i == $page):
                        ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i . $queryString; ?>"><?php echo $i; ?></a>
                        <?php 
                                endif;
                            endfor; 
                        ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo ($page + 1) . $queryString; ?>">Next →</a>
                        <?php else: ?>
                            <span class="disabled">Next →</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
