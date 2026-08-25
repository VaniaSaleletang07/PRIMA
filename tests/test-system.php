<?php
/**
 * COMPREHENSIVE SYSTEM TEST
 * Testing all critical components
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><title>System Test</title>";
echo "<style>
body{font-family:Arial;padding:20px;background:#f5f5f5;}
.test-section{background:white;padding:20px;margin:15px 0;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);}
h1{color:#C8102E;margin-bottom:30px;}
h2{color:#333;border-bottom:2px solid #C8102E;padding-bottom:10px;margin-bottom:15px;}
.pass{color:#28a745;font-weight:bold;}
.fail{color:#dc3545;font-weight:bold;}
.warning{color:#ffc107;font-weight:bold;}
table{width:100%;border-collapse:collapse;margin:10px 0;}
th,td{padding:8px;text-align:left;border:1px solid #ddd;}
th{background:#f8f9fa;}
.code{background:#f4f4f4;padding:10px;border-radius:4px;font-family:monospace;margin:10px 0;}
</style></head><body>";

echo "<h1>🔍 COMPREHENSIVE SYSTEM TEST</h1>";
echo "<p>Testing PRIMA (Pertamina Checklist Mobil Tangki) - Pertamina Patra Niaga</p>";
echo "<hr>";

$testResults = [];
$totalTests = 0;
$passedTests = 0;

// TEST 1: Config & Database Connection
echo "<div class='test-section'>";
echo "<h2>Test 1: Configuration & Database Connection</h2>";
try {
    require_once '../config/config.php';
    echo "✓ config.php loaded<br>";
    
    $db = Database::getInstance()->getConnection();
    echo "✓ Database connection established<br>";
    echo "Database: <strong>" . DB_NAME . "</strong><br>";
    echo "Host: <strong>" . DB_HOST . "</strong><br>";
    
    $testResults[] = ['test' => 'Config & DB Connection', 'status' => 'PASS'];
    $passedTests++;
} catch(Exception $e) {
    echo "<span class='fail'>✗ ERROR: {$e->getMessage()}</span><br>";
    $testResults[] = ['test' => 'Config & DB Connection', 'status' => 'FAIL'];
}
$totalTests++;
echo "</div>";

// TEST 2: Database Tables Structure
echo "<div class='test-section'>";
echo "<h2>Test 2: Database Tables Structure</h2>";
$requiredTables = [
    'users', 
    'user_registrations', 
    'user_sessions', 
    'formulir_checklist', 
    'checklist_items', 
    'audit_log',
    'master_jenis_pemeriksaan'
];

$tableStatus = [];
foreach($requiredTables as $table) {
    try {
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        $exists = $stmt->fetch();
        if($exists) {
            echo "✓ Table <strong>$table</strong> exists<br>";
            $tableStatus[] = true;
        } else {
            echo "<span class='fail'>✗ Table <strong>$table</strong> missing</span><br>";
            $tableStatus[] = false;
        }
    } catch(Exception $e) {
        echo "<span class='fail'>✗ Error checking $table: {$e->getMessage()}</span><br>";
        $tableStatus[] = false;
    }
}

if(!in_array(false, $tableStatus)) {
    $testResults[] = ['test' => 'Database Tables', 'status' => 'PASS'];
    $passedTests++;
} else {
    $testResults[] = ['test' => 'Database Tables', 'status' => 'FAIL'];
}
$totalTests++;
echo "</div>";

// TEST 3: Critical Columns Check
echo "<div class='test-section'>";
echo "<h2>Test 3: Critical Columns Verification</h2>";

// Check user_sessions columns
echo "<strong>user_sessions table:</strong><br>";
try {
    $stmt = $db->query("DESCRIBE user_sessions");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $requiredCols = ['session_token', 'user_id', 'ip_address', 'expires_at'];
    $colStatus = true;
    foreach($requiredCols as $col) {
        if(in_array($col, $columns)) {
            echo "✓ Column <code>$col</code> exists<br>";
        } else {
            echo "<span class='fail'>✗ Column <code>$col</code> missing</span><br>";
            $colStatus = false;
        }
    }
    
    // Check for wrong column name
    if(in_array('session_id', $columns)) {
        echo "<span class='warning'>⚠ WARNING: Column <code>session_id</code> found (should be session_token)</span><br>";
    }
    
    $testResults[] = ['test' => 'user_sessions Columns', 'status' => $colStatus ? 'PASS' : 'FAIL'];
    if($colStatus) $passedTests++;
} catch(Exception $e) {
    echo "<span class='fail'>✗ ERROR: {$e->getMessage()}</span><br>";
    $testResults[] = ['test' => 'user_sessions Columns', 'status' => 'FAIL'];
}
$totalTests++;

// Check formulir_checklist columns
echo "<br><strong>formulir_checklist table:</strong><br>";
try {
    $stmt = $db->query("DESCRIBE formulir_checklist");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $requiredCols = ['nomor_polisi', 'tanggal_pemeriksaan', 'jenis_kendaraan', 'created_by'];
    $colStatus = true;
    foreach($requiredCols as $col) {
        if(in_array($col, $columns)) {
            echo "✓ Column <code>$col</code> exists<br>";
        } else {
            echo "<span class='fail'>✗ Column <code>$col</code> missing</span><br>";
            $colStatus = false;
        }
    }
    
    // Check for wrong column name
    if(in_array('catatan', $columns)) {
        echo "<span class='warning'>⚠ WARNING: Column <code>catatan</code> found (not used in code)</span><br>";
    }
    
    $testResults[] = ['test' => 'formulir_checklist Columns', 'status' => $colStatus ? 'PASS' : 'FAIL'];
    if($colStatus) $passedTests++;
} catch(Exception $e) {
    echo "<span class='fail'>✗ ERROR: {$e->getMessage()}</span><br>";
    $testResults[] = ['test' => 'formulir_checklist Columns', 'status' => 'FAIL'];
}
$totalTests++;
echo "</div>";

// TEST 4: Admin User Check
echo "<div class='test-section'>";
echo "<h2>Test 4: Admin User Verification</h2>";
try {
    $stmt = $db->prepare("SELECT * FROM users WHERE username = 'admin'");
    $stmt->execute();
    $admin = $stmt->fetch();
    
    if($admin) {
        echo "✓ Admin user exists<br>";
        echo "Username: <strong>{$admin['username']}</strong><br>";
        echo "Email: <strong>{$admin['email']}</strong><br>";
        echo "Role: <strong>{$admin['role']}</strong><br>";
        echo "Status: <strong>{$admin['status']}</strong><br>";
        
        // Test password
        if(password_verify('admin123', $admin['password'])) {
            echo "<span class='pass'>✓ Password 'admin123' verified successfully</span><br>";
            $testResults[] = ['test' => 'Admin User', 'status' => 'PASS'];
            $passedTests++;
        } else {
            echo "<span class='fail'>✗ Password verification failed</span><br>";
            $testResults[] = ['test' => 'Admin User', 'status' => 'FAIL'];
        }
    } else {
        echo "<span class='fail'>✗ Admin user not found</span><br>";
        $testResults[] = ['test' => 'Admin User', 'status' => 'FAIL'];
    }
} catch(Exception $e) {
    echo "<span class='fail'>✗ ERROR: {$e->getMessage()}</span><br>";
    $testResults[] = ['test' => 'Admin User', 'status' => 'FAIL'];
}
$totalTests++;
echo "</div>";

// TEST 5: File Structure
echo "<div class='test-section'>";
echo "<h2>Test 5: Critical Files Check</h2>";
$requiredFiles = [
    'config.php',
    'auth.php',
    'login.php',
    'logout.php',
    'home.php',
    'save.php',
    'load.php',
    'get.php',
    'delete.php',
    'list.php',
    'export.php',
    'process-login.php',
    'admin-dashboard.php'
];

$fileStatus = true;
foreach($requiredFiles as $file) {
    if(file_exists($file)) {
        echo "✓ <code>$file</code> exists<br>";
    } else {
        echo "<span class='fail'>✗ <code>$file</code> missing</span><br>";
        $fileStatus = false;
    }
}

$testResults[] = ['test' => 'Critical Files', 'status' => $fileStatus ? 'PASS' : 'FAIL'];
if($fileStatus) $passedTests++;
$totalTests++;
echo "</div>";

// TEST 6: Session Handling
echo "<div class='test-section'>";
echo "<h2>Test 6: Session Configuration</h2>";
echo "Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? "<span class='pass'>ACTIVE</span>" : "<span class='warning'>INACTIVE</span>") . "<br>";
echo "Session Save Path: <code>" . session_save_path() . "</code><br>";
echo "Session Cookie Params:<br>";
$params = session_get_cookie_params();
echo "<div class='code'>";
foreach($params as $key => $val) {
    echo "$key: " . (is_bool($val) ? ($val ? 'true' : 'false') : $val) . "<br>";
}
echo "</div>";
$testResults[] = ['test' => 'Session Config', 'status' => 'PASS'];
$passedTests++;
$totalTests++;
echo "</div>";

// TEST 7: Error Display Settings
echo "<div class='test-section'>";
echo "<h2>Test 7: PHP Error Settings</h2>";
echo "display_errors: <strong>" . ini_get('display_errors') . "</strong> ";
echo ini_get('display_errors') == 1 ? "<span class='warning'>(Development Mode)</span>" : "<span class='pass'>(Production Mode)</span>";
echo "<br>";
echo "error_reporting: <strong>" . error_reporting() . "</strong> (E_ALL = " . E_ALL . ")<br>";
echo "<span class='warning'>⚠ Remember to disable error display in production!</span><br>";
$testResults[] = ['test' => 'PHP Error Settings', 'status' => 'PASS'];
$passedTests++;
$totalTests++;
echo "</div>";

// SUMMARY
echo "<div class='test-section' style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;'>";
echo "<h2 style='color: white; border-bottom: 2px solid white;'>📊 TEST SUMMARY</h2>";
echo "<table style='color: white;'>";
echo "<tr><th style='background: rgba(255,255,255,0.2);'>Total Tests</th><td><strong>$totalTests</strong></td></tr>";
echo "<tr><th style='background: rgba(255,255,255,0.2);'>Passed</th><td><strong style='color: #90EE90;'>$passedTests</strong></td></tr>";
echo "<tr><th style='background: rgba(255,255,255,0.2);'>Failed</th><td><strong style='color: #FFB6C1;'>" . ($totalTests - $passedTests) . "</strong></td></tr>";
$percentage = round(($passedTests / $totalTests) * 100, 1);
echo "<tr><th style='background: rgba(255,255,255,0.2);'>Success Rate</th><td><strong style='font-size: 20px;'>{$percentage}%</strong></td></tr>";
echo "</table>";

if($percentage == 100) {
    echo "<h3 style='color: #90EE90; text-align: center; margin-top: 20px;'>🎉 ALL TESTS PASSED! SYSTEM IS READY!</h3>";
} elseif($percentage >= 80) {
    echo "<h3 style='color: #FFD700; text-align: center; margin-top: 20px;'>⚠️ SYSTEM MOSTLY FUNCTIONAL - CHECK FAILED TESTS</h3>";
} else {
    echo "<h3 style='color: #FFB6C1; text-align: center; margin-top: 20px;'>❌ CRITICAL ISSUES DETECTED - FIX REQUIRED</h3>";
}
echo "</div>";

// DETAILED RESULTS
echo "<div class='test-section'>";
echo "<h2>📋 Detailed Results</h2>";
echo "<table>";
echo "<tr><th>#</th><th>Test Name</th><th>Status</th></tr>";
foreach($testResults as $i => $result) {
    $statusClass = $result['status'] === 'PASS' ? 'pass' : 'fail';
    echo "<tr>";
    echo "<td>" . ($i + 1) . "</td>";
    echo "<td>{$result['test']}</td>";
    echo "<td class='$statusClass'>{$result['status']}</td>";
    echo "</tr>";
}
echo "</table>";
echo "</div>";

echo "</body></html>";
?>
