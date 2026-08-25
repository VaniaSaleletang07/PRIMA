<?php
/**
 * Test Registration System
 * Diagnostic untuk cek kenapa user tidak muncul di review pendaftaran
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><title>Test Registration System</title>";
echo "<style>
body{font-family:Arial;padding:20px;background:#f5f5f5;}
.success{color:#28a745;font-weight:bold;background:#d4edda;padding:10px;border-radius:5px;margin:10px 0;}
.error{color:#dc3545;font-weight:bold;background:#f8d7da;padding:10px;border-radius:5px;margin:10px 0;}
.info{color:#0066cc;font-weight:bold;background:#cce5ff;padding:10px;border-radius:5px;margin:10px 0;}
.warning{color:#856404;font-weight:bold;background:#fff3cd;padding:10px;border-radius:5px;margin:10px 0;}
.code{background:#f4f4f4;padding:15px;border-radius:5px;margin:10px 0;border-left:4px solid #0066cc;font-family:monospace;overflow-x:auto;}
h1{color:#C8102E;}
h2{color:#333;border-bottom:2px solid #ddd;padding-bottom:10px;margin-top:30px;}
table{width:100%;border-collapse:collapse;margin:15px 0;background:white;}
th,td{padding:12px;text-align:left;border:1px solid #ddd;}
th{background:#f8f9fa;font-weight:bold;}
</style></head><body>";

echo "<h1>🔍 TEST REGISTRATION SYSTEM</h1>";
echo "<hr>";

require_once '../config/config.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // =====================================================
    // STEP 1: Check if table exists
    // =====================================================
    echo "<h2>Step 1: Check if user_registrations table exists</h2>";
    
    $stmt = $db->query("SHOW TABLES LIKE 'user_registrations'");
    $tableExists = $stmt->fetch();
    
    if ($tableExists) {
        echo "<div class='success'>✅ Table 'user_registrations' EXISTS</div>";
    } else {
        echo "<div class='error'>❌ Table 'user_registrations' NOT FOUND!</div>";
        echo "<div class='warning'>";
        echo "<strong>SOLUSI:</strong> Jalankan SQL ini di phpMyAdmin:<br>";
        echo "<div class='code'>";
        echo "CREATE TABLE user_registrations (<br>";
        echo "&nbsp;&nbsp;id INT(11) AUTO_INCREMENT PRIMARY KEY,<br>";
        echo "&nbsp;&nbsp;username VARCHAR(50) UNIQUE NOT NULL,<br>";
        echo "&nbsp;&nbsp;password VARCHAR(255) NOT NULL,<br>";
        echo "&nbsp;&nbsp;full_name VARCHAR(100) NOT NULL,<br>";
        echo "&nbsp;&nbsp;email VARCHAR(100) UNIQUE NOT NULL,<br>";
        echo "&nbsp;&nbsp;phone VARCHAR(20),<br>";
        echo "&nbsp;&nbsp;department VARCHAR(100),<br>";
        echo "&nbsp;&nbsp;position VARCHAR(100),<br>";
        echo "&nbsp;&nbsp;reason TEXT,<br>";
        echo "&nbsp;&nbsp;status ENUM('pending','approved','rejected') DEFAULT 'pending',<br>";
        echo "&nbsp;&nbsp;reviewed_by INT(11),<br>";
        echo "&nbsp;&nbsp;reviewed_at DATETIME,<br>";
        echo "&nbsp;&nbsp;rejection_reason TEXT,<br>";
        echo "&nbsp;&nbsp;created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP<br>";
        echo ");";
        echo "</div>";
        echo "</div>";
        echo "</body></html>";
        exit;
    }
    
    // =====================================================
    // STEP 2: Check table structure
    // =====================================================
    echo "<h2>Step 2: Check Table Structure</h2>";
    
    $stmt = $db->query("DESCRIBE user_registrations");
    $columns = $stmt->fetchAll();
    
    echo "<table>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    
    $requiredColumns = ['id', 'username', 'password', 'full_name', 'email', 'phone', 
                        'department', 'position', 'reason', 'status', 'created_at'];
    $existingColumns = [];
    
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>{$col['Field']}</td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>{$col['Default']}</td>";
        echo "</tr>";
        $existingColumns[] = $col['Field'];
    }
    echo "</table>";
    
    // Check missing columns
    $missingColumns = array_diff($requiredColumns, $existingColumns);
    
    if (count($missingColumns) > 0) {
        echo "<div class='error'>❌ Missing columns: " . implode(', ', $missingColumns) . "</div>";
    } else {
        echo "<div class='success'>✅ All required columns exist</div>";
    }
    
    // =====================================================
    // STEP 3: Count registrations
    // =====================================================
    echo "<h2>Step 3: Count Registration Records</h2>";
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM user_registrations");
    $total = $stmt->fetch()['total'];
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM user_registrations WHERE status = 'pending'");
    $pending = $stmt->fetch()['total'];
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM user_registrations WHERE status = 'approved'");
    $approved = $stmt->fetch()['total'];
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM user_registrations WHERE status = 'rejected'");
    $rejected = $stmt->fetch()['total'];
    
    echo "<table>";
    echo "<tr><th>Status</th><th>Count</th></tr>";
    echo "<tr><td>Total</td><td><strong>$total</strong></td></tr>";
    echo "<tr><td>Pending</td><td><strong>$pending</strong></td></tr>";
    echo "<tr><td>Approved</td><td><strong>$approved</strong></td></tr>";
    echo "<tr><td>Rejected</td><td><strong>$rejected</strong></td></tr>";
    echo "</table>";
    
    if ($total == 0) {
        echo "<div class='warning'>⚠️ NO REGISTRATIONS FOUND! Coba daftar user baru di register.php</div>";
    } elseif ($pending == 0) {
        echo "<div class='info'>ℹ️ No pending registrations. Semua sudah di-review.</div>";
    } else {
        echo "<div class='success'>✅ Ada $pending registrasi pending yang menunggu review</div>";
    }
    
    // =====================================================
    // STEP 4: Show all registrations
    // =====================================================
    echo "<h2>Step 4: All Registration Records</h2>";
    
    $stmt = $db->query("
        SELECT 
            id, username, full_name, email, phone, 
            department, position, status, created_at
        FROM user_registrations 
        ORDER BY created_at DESC 
        LIMIT 20
    ");
    $registrations = $stmt->fetchAll();
    
    if (count($registrations) > 0) {
        echo "<table>";
        echo "<tr>";
        echo "<th>ID</th><th>Username</th><th>Full Name</th><th>Email</th>";
        echo "<th>Department</th><th>Status</th><th>Created</th>";
        echo "</tr>";
        
        foreach ($registrations as $reg) {
            $statusColor = $reg['status'] === 'pending' ? '#ffc107' : 
                          ($reg['status'] === 'approved' ? '#28a745' : '#dc3545');
            echo "<tr>";
            echo "<td>{$reg['id']}</td>";
            echo "<td>{$reg['username']}</td>";
            echo "<td>{$reg['full_name']}</td>";
            echo "<td>{$reg['email']}</td>";
            echo "<td>{$reg['department']}</td>";
            echo "<td style='color:{$statusColor};font-weight:bold;'>{$reg['status']}</td>";
            echo "<td>{$reg['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='warning'>⚠️ No records found</div>";
    }
    
    // =====================================================
    // STEP 5: Test INSERT
    // =====================================================
    echo "<h2>Step 5: Test INSERT (Dry Run)</h2>";
    
    echo "<div class='info'>";
    echo "Query yang digunakan oleh process-register.php:<br>";
    echo "<div class='code'>";
    echo "INSERT INTO user_registrations<br>";
    echo "(username, password, full_name, email, phone, department, position, reason)<br>";
    echo "VALUES<br>";
    echo "(:username, :password, :full_name, :email, :phone, :department, :position, :reason)";
    echo "</div>";
    echo "</div>";
    
    // Try to prepare the statement
    try {
        $stmt = $db->prepare("
            INSERT INTO user_registrations 
            (username, password, full_name, email, phone, department, position, reason)
            VALUES 
            (:username, :password, :full_name, :email, :phone, :department, :position, :reason)
        ");
        echo "<div class='success'>✅ INSERT statement is valid (prepared successfully)</div>";
    } catch (Exception $e) {
        echo "<div class='error'>❌ INSERT statement ERROR: " . $e->getMessage() . "</div>";
    }
    
    // =====================================================
    // STEP 6: Check foreign keys
    // =====================================================
    echo "<h2>Step 6: Check Foreign Keys</h2>";
    
    $stmt = $db->query("
        SELECT 
            CONSTRAINT_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'user_registrations'
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    $foreignKeys = $stmt->fetchAll();
    
    if (count($foreignKeys) > 0) {
        echo "<table>";
        echo "<tr><th>Constraint</th><th>Column</th><th>References</th></tr>";
        foreach ($foreignKeys as $fk) {
            echo "<tr>";
            echo "<td>{$fk['CONSTRAINT_NAME']}</td>";
            echo "<td>{$fk['COLUMN_NAME']}</td>";
            echo "<td>{$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='info'>ℹ️ No foreign keys found</div>";
    }
    
    // =====================================================
    // CONCLUSION
    // =====================================================
    echo "<h2>📋 Summary</h2>";
    
    echo "<div class='info'>";
    echo "<strong>Quick Actions:</strong><br><br>";
    
    echo "1. <a href="../auth/register.php" target='_blank'>Buka Halaman Register</a> - Daftar user baru<br>";
    echo "2. <a href="../admin/approve-registrations.php" target='_blank'>Buka Review Pendaftaran</a> - Lihat pending registrations<br>";
    echo "3. <a href="../tests/test-registration.php">Refresh halaman ini</a> - Cek ulang<br>";
    echo "</div>";
    
    if ($pending > 0) {
        echo "<div class='success'>";
        echo "<h3>✅ SISTEM BERFUNGSI NORMAL</h3>";
        echo "<p>Ada $pending registrasi pending. Silakan buka <a href="../admin/approve-registrations.php">Review Pendaftaran</a> untuk approve/reject.</p>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h3>❌ DATABASE ERROR</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}

echo "</body></html>";
?>
