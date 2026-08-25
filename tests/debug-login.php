<?php
/**
 * DEBUG LOGIN - Untuk troubleshooting error login
 * HAPUS FILE INI SETELAH MASALAH TERATASI!
 */

// Enable error display
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>DEBUG Login System</h1>";
echo "<p>Testing database dan authentication...</p><hr>";

require_once '../config/config.php';

// Test 1: Database Connection
echo "<h2>Test 1: Database Connection</h2>";
try {
    $db = Database::getInstance()->getConnection();
    echo "✅ <strong>SUKSES</strong> - Database connected<br>";
    echo "Database: " . DB_NAME . "<br><br>";
} catch(Exception $e) {
    echo "❌ <strong>ERROR</strong> - " . $e->getMessage() . "<br><br>";
    exit;
}

// Test 2: Check if users table exists
echo "<h2>Test 2: Check Users Table</h2>";
try {
    $stmt = $db->query("SHOW TABLES LIKE 'users'");
    $result = $stmt->fetch();
    if ($result) {
        echo "✅ <strong>SUKSES</strong> - Table 'users' exists<br><br>";
    } else {
        echo "❌ <strong>ERROR</strong> - Table 'users' NOT FOUND!<br>";
        echo "Solusi: Import database.sql terlebih dahulu<br><br>";
        exit;
    }
} catch(Exception $e) {
    echo "❌ <strong>ERROR</strong> - " . $e->getMessage() . "<br><br>";
    exit;
}

// Test 3: Check users table structure
echo "<h2>Test 3: Check Table Structure</h2>";
try {
    $stmt = $db->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $required_columns = ['status', 'approved_by', 'approved_at', 'last_login', 'login_attempts', 'locked_until'];
    $missing_columns = [];
    
    foreach ($required_columns as $col) {
        if (!in_array($col, $columns)) {
            $missing_columns[] = $col;
        }
    }
    
    if (empty($missing_columns)) {
        echo "✅ <strong>SUKSES</strong> - All required columns exist<br>";
        echo "Columns: " . implode(', ', $columns) . "<br><br>";
    } else {
        echo "❌ <strong>ERROR</strong> - Missing columns: " . implode(', ', $missing_columns) . "<br>";
        echo "Solusi: Jalankan database_auth.sql<br><br>";
        exit;
    }
} catch(Exception $e) {
    echo "❌ <strong>ERROR</strong> - " . $e->getMessage() . "<br><br>";
    exit;
}

// Test 4: Check if admin user exists
echo "<h2>Test 4: Check Admin User</h2>";
try {
    $stmt = $db->query("SELECT * FROM users WHERE username = 'admin'");
    $admin = $stmt->fetch();
    
    if ($admin) {
        echo "✅ <strong>SUKSES</strong> - Admin user found<br>";
        echo "Username: " . $admin['username'] . "<br>";
        echo "Full Name: " . $admin['full_name'] . "<br>";
        echo "Email: " . $admin['email'] . "<br>";
        echo "Role: " . $admin['role'] . "<br>";
        echo "Status: " . ($admin['status'] ?? 'N/A') . "<br>";
        echo "Password Hash: " . substr($admin['password'], 0, 20) . "...<br><br>";
    } else {
        echo "❌ <strong>ERROR</strong> - Admin user NOT FOUND!<br>";
        echo "Solusi: Jalankan query INSERT admin user<br><br>";
        exit;
    }
} catch(Exception $e) {
    echo "❌ <strong>ERROR</strong> - " . $e->getMessage() . "<br><br>";
    exit;
}

// Test 5: Test password verification
echo "<h2>Test 5: Password Verification</h2>";
$test_password = 'admin123';
$is_valid = password_verify($test_password, $admin['password']);

if ($is_valid) {
    echo "✅ <strong>SUKSES</strong> - Password 'admin123' is correct<br><br>";
} else {
    echo "❌ <strong>ERROR</strong> - Password verification failed!<br>";
    echo "Password hash di database tidak match dengan 'admin123'<br>";
    echo "Solusi: Reset password admin<br><br>";
}

// Test 6: Check other required tables
echo "<h2>Test 6: Check Auth Tables</h2>";
$auth_tables = ['user_registrations', 'user_sessions'];
foreach ($auth_tables as $table) {
    try {
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        $result = $stmt->fetch();
        if ($result) {
            echo "✅ Table '$table' exists<br>";
        } else {
            echo "❌ Table '$table' NOT FOUND - Jalankan database_auth.sql<br>";
        }
    } catch(Exception $e) {
        echo "❌ Error checking $table: " . $e->getMessage() . "<br>";
    }
}

echo "<br><hr>";
echo "<h2>🎯 KESIMPULAN</h2>";

if ($is_valid && $admin && empty($missing_columns)) {
    echo "<p style='color: green; font-size: 18px;'><strong>✅ SEMUA TEST PASSED!</strong></p>";
    echo "<p>Sistem autentikasi sudah siap. Coba login dengan:</p>";
    echo "<ul>";
    echo "<li>Username: <strong>admin</strong></li>";
    echo "<li>Password: <strong>admin123</strong></li>";
    echo "</ul>";
    echo "<p><a href="../auth/login.php" style='background: #c8102e; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Login Sekarang</a></p>";
} else {
    echo "<p style='color: red; font-size: 18px;'><strong>❌ ADA MASALAH!</strong></p>";
    echo "<p>Lihat error di atas dan ikuti solusi yang diberikan.</p>";
}

echo "<hr>";
echo "<p style='color: #999;'><strong>PENTING:</strong> Hapus file debug-login.php ini setelah masalah selesai!</p>";
?>
