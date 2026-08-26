<?php
/**
 * DIAGNOSE HOSTING - Check Database & Admin User
 * Upload file ini ke hosting dan akses via browser
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 DIAGNOSE HOSTING DATABASE</h1>";
echo "<p>Memeriksa database dan admin user...</p><hr>";

require_once 'config.php';

// Test 1: Database Connection
echo "<h2>Test 1: Koneksi Database</h2>";
try {
    $db = Database::getInstance()->getConnection();
    echo "✅ <strong>BERHASIL</strong> terhubung ke database<br>";
    echo "Database: " . DB_NAME . "<br>";
    echo "Host: " . DB_HOST . "<br><br>";
} catch(Exception $e) {
    echo "❌ <strong>GAGAL</strong> koneksi database<br>";
    echo "Error: " . $e->getMessage() . "<br><br>";
    exit;
}

// Test 2: Check if users table exists
echo "<h2>Test 2: Cek Tabel Users</h2>";
try {
    $stmt = $db->query("SHOW TABLES LIKE 'users'");
    $exists = $stmt->fetch();
    
    if ($exists) {
        echo "✅ Tabel 'users' <strong>ADA</strong><br><br>";
    } else {
        echo "❌ Tabel 'users' <strong>TIDAK ADA</strong><br>";
        echo "<strong>SOLUSI:</strong> Jalankan database.sql di phpMyAdmin hosting<br><br>";
        exit;
    }
} catch(Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br><br>";
    exit;
}

// Test 3: Count total users
echo "<h2>Test 3: Jumlah User</h2>";
try {
    $stmt = $db->query("SELECT COUNT(*) as total FROM users");
    $result = $stmt->fetch();
    $totalUsers = $result['total'];
    
    echo "Total users: <strong>{$totalUsers}</strong><br><br>";
    
    if ($totalUsers == 0) {
        echo "⚠️ <strong>DATABASE KOSONG</strong> - Tidak ada user sama sekali<br>";
        echo "<strong>SOLUSI:</strong> Jalankan SQL berikut di phpMyAdmin:<br>";
        echo "<pre style='background:#f5f5f5;padding:10px;'>";
        echo "INSERT INTO users (username, password, full_name, email, role, status) VALUES\n";
        echo "('admin', '\$2y\$10\$YourPasswordHashHere', 'Administrator', 'admin@pertamina.com', 'admin', 'active');\n";
        echo "</pre><br>";
    }
} catch(Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br><br>";
}

// Test 4: Check admin user
echo "<h2>Test 4: Cek Admin User</h2>";
try {
    $stmt = $db->prepare("SELECT * FROM users WHERE username = 'admin'");
    $stmt->execute();
    $admin = $stmt->fetch();
    
    if (!$admin) {
        echo "❌ <strong>ADMIN USER TIDAK ADA</strong><br><br>";
        echo "<h3>🔧 SOLUSI: Buat Admin User</h3>";
        echo "<p>Copy SQL berikut dan jalankan di phpMyAdmin hosting:</p>";
        
        $password = 'admin123';
        $hash = password_hash($password, PASSWORD_DEFAULT);
        
        echo "<pre style='background:#f5f5f5;padding:15px;border:1px solid #ddd;'>";
        echo "-- Buat admin user dengan password: admin123\n";
        echo "INSERT INTO users (username, password, full_name, email, role, status) VALUES\n";
        echo "('admin', '{$hash}', 'Administrator', 'admin@pertamina.com', 'admin', 'active');\n";
        echo "</pre>";
        echo "<p><strong>Setelah jalankan SQL di atas, coba login lagi dengan:</strong></p>";
        echo "<ul>";
        echo "<li>Username: <strong>admin</strong></li>";
        echo "<li>Password: <strong>admin123</strong></li>";
        echo "</ul>";
        exit;
    }
    
    echo "✅ Admin user <strong>ADA</strong><br>";
    echo "<table border='1' cellpadding='8' style='border-collapse:collapse;margin-top:10px;'>";
    echo "<tr><th>Field</th><th>Value</th></tr>";
    echo "<tr><td>ID</td><td>{$admin['id']}</td></tr>";
    echo "<tr><td>Username</td><td>{$admin['username']}</td></tr>";
    echo "<tr><td>Full Name</td><td>{$admin['full_name']}</td></tr>";
    echo "<tr><td>Email</td><td>{$admin['email']}</td></tr>";
    echo "<tr><td>Role</td><td>{$admin['role']}</td></tr>";
    echo "<tr><td>Status</td><td>{$admin['status']}</td></tr>";
    echo "<tr><td>Password Hash</td><td>" . substr($admin['password'], 0, 50) . "...</td></tr>";
    echo "</table><br>";
    
} catch(Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br><br>";
    exit;
}

// Test 5: Verify password hash
echo "<h2>Test 5: Test Password Verification</h2>";
$testPassword = 'admin123';
$isValid = password_verify($testPassword, $admin['password']);

if ($isValid) {
    echo "✅ <strong>PASSWORD COCOK</strong><br>";
    echo "Password 'admin123' bisa verify dengan hash di database<br><br>";
    
    echo "<h3>✅ DIAGNOSIS SELESAI - SEHARUSNYA BISA LOGIN</h3>";
    echo "<p>Semua pengecekan OK. Jika masih tidak bisa login:</p>";
    echo "<ol>";
    echo "<li>Clear cache browser (Ctrl+Shift+Delete)</li>";
    echo "<li>Coba browser lain atau mode incognito</li>";
    echo "<li>Pastikan URL: <strong>http://teschecklistekim.infinityfreeapp.com/login.php</strong></li>";
    echo "<li>Username: <strong>admin</strong> (huruf kecil semua)</li>";
    echo "<li>Password: <strong>admin123</strong></li>";
    echo "</ol>";
    
} else {
    echo "❌ <strong>PASSWORD HASH TIDAK COCOK</strong><br>";
    echo "Hash di database tidak cocok dengan 'admin123'<br><br>";
    
    echo "<h3>🔧 SOLUSI: Update Password Hash</h3>";
    echo "<p>Copy SQL berikut dan jalankan di phpMyAdmin hosting:</p>";
    
    $newHash = password_hash('admin123', PASSWORD_DEFAULT);
    
    echo "<pre style='background:#f5f5f5;padding:15px;border:1px solid #ddd;'>";
    echo "-- Update admin password menjadi: admin123\n";
    echo "UPDATE users SET password = '{$newHash}' WHERE username = 'admin';\n";
    echo "</pre>";
    echo "<p><strong>Setelah jalankan SQL di atas, coba login dengan:</strong></p>";
    echo "<ul>";
    echo "<li>Username: <strong>admin</strong></li>";
    echo "<li>Password: <strong>admin123</strong></li>";
    echo "</ul>";
}

echo "<hr>";
echo "<p style='color:#666;'><em>Setelah selesai diagnose, HAPUS file ini dari hosting untuk keamanan.</em></p>";
?>
