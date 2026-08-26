<?php
/**
 * DEBUG USER REGISTRATIONS
 * Cek kenapa data pending tidak muncul di Review Pendaftaran
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 DEBUG USER REGISTRATIONS</h1>";
echo "<hr>";

require_once 'config.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Test 1: Count pending registrations
    echo "<h2>Test 1: Count Pending Registrations</h2>";
    $stmt = $db->query("SELECT COUNT(*) as count FROM user_registrations WHERE status = 'pending'");
    $pending = $stmt->fetch();
    echo "Pending count: <strong>{$pending['count']}</strong><br><br>";
    
    // Test 2: Get ALL registrations
    echo "<h2>Test 2: All Registrations (Raw Data)</h2>";
    $stmt = $db->query("SELECT * FROM user_registrations ORDER BY created_at DESC");
    $all = $stmt->fetchAll();
    
    echo "Total registrations: <strong>" . count($all) . "</strong><br><br>";
    
    if (count($all) > 0) {
        echo "<table border='1' cellpadding='8' style='border-collapse:collapse;width:100%;'>";
        echo "<tr>";
        echo "<th>ID</th>";
        echo "<th>Username</th>";
        echo "<th>Full Name</th>";
        echo "<th>Email</th>";
        echo "<th>Status</th>";
        echo "<th>Created At</th>";
        echo "</tr>";
        
        foreach ($all as $reg) {
            $statusColor = $reg['status'] === 'pending' ? 'orange' : ($reg['status'] === 'approved' ? 'green' : 'red');
            echo "<tr>";
            echo "<td>{$reg['id']}</td>";
            echo "<td>{$reg['username']}</td>";
            echo "<td>{$reg['full_name']}</td>";
            echo "<td>{$reg['email']}</td>";
            echo "<td style='color:{$statusColor};font-weight:bold;'>{$reg['status']}</td>";
            echo "<td>{$reg['created_at']}</td>";
            echo "</tr>";
        }
        
        echo "</table><br>";
    } else {
        echo "❌ <strong>TIDAK ADA DATA</strong> di tabel user_registrations<br><br>";
    }
    
    // Test 3: Query yang sama dengan approve-registrations.php
    echo "<h2>Test 3: Query dari approve-registrations.php</h2>";
    $stmt = $db->query("
        SELECT 
            ur.id,
            ur.username,
            ur.email,
            ur.full_name,
            ur.phone,
            ur.department,
            ur.position,
            ur.reason,
            ur.status,
            ur.created_at,
            ur.rejected_reason,
            ur.processed_at,
            u.full_name as processed_by_name
        FROM user_registrations ur
        LEFT JOIN users u ON ur.processed_by = u.id
        ORDER BY ur.created_at DESC
    ");
    $registrations = $stmt->fetchAll();
    
    echo "Total dari JOIN query: <strong>" . count($registrations) . "</strong><br><br>";
    
    if (count($registrations) > 0) {
        echo "<h3>Detail Data (JSON):</h3>";
        echo "<pre style='background:#f5f5f5;padding:10px;overflow:auto;'>";
        echo json_encode($registrations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        echo "</pre>";
    }
    
    // Test 4: Check if table structure is correct
    echo "<h2>Test 4: Table Structure</h2>";
    $stmt = $db->query("DESCRIBE user_registrations");
    $columns = $stmt->fetchAll();
    
    echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>" . ($col['Default'] ?: 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table><br>";
    
    echo "<hr>";
    echo "<h2>✅ DIAGNOSIS SELESAI</h2>";
    echo "<p>Jika data ada di Test 1 & 2 tapi tidak muncul di approve-registrations.php:</p>";
    echo "<ul>";
    echo "<li>Cek JavaScript console di browser (F12)</li>";
    echo "<li>Pastikan tidak ada error JavaScript yang block rendering</li>";
    echo "<li>Coba clear browser cache</li>";
    echo "<li>Pastikan query di approve-registrations.php sama dengan Test 3</li>";
    echo "</ul>";
    
} catch(Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    echo "Error Code: " . $e->getCode() . "<br>";
}
?>
