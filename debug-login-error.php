<?php
/**
 * Debug Login Error - HTTP 500 Troubleshooting
 * TEMPORARY FILE - Hapus setelah selesai debug
 */

// Enable full error display
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "<h1>🔍 Debug Login Error</h1>";
echo "<p>Checking what causes HTTP 500...</p><hr>";

// Test 1: PHP Version
echo "<h2>Test 1: PHP Version</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Status: ✅ OK<br><br>";

// Test 2: Config File
echo "<h2>Test 2: Load Config File</h2>";
try {
    require_once 'config.php';
    echo "✅ config.php loaded successfully<br>";
    echo "DB_HOST: " . DB_HOST . "<br>";
    echo "DB_USER: " . DB_USER . "<br>";
    echo "DB_NAME: " . DB_NAME . "<br>";
    echo "DB_PASS length: " . strlen(DB_PASS) . " characters<br>";
    
    // Check for trailing space
    if (DB_PASS !== trim(DB_PASS)) {
        echo "<strong style='color:red;'>⚠️ WARNING: DB_PASS has trailing/leading spaces!</strong><br>";
        echo "Actual: '" . DB_PASS . "'<br>";
        echo "Trimmed: '" . trim(DB_PASS) . "'<br>";
    } else {
        echo "✅ DB_PASS has no extra spaces<br>";
    }
    echo "<br>";
} catch (Exception $e) {
    echo "❌ ERROR loading config.php: " . $e->getMessage() . "<br><br>";
    exit;
}

// Test 3: Database Connection
echo "<h2>Test 3: Database Connection</h2>";
try {
    $db = Database::getInstance()->getConnection();
    echo "✅ Database connected successfully!<br>";
    echo "Status: Connection OK<br><br>";
} catch (Exception $e) {
    echo "❌ Database connection FAILED<br>";
    echo "Error: " . $e->getMessage() . "<br>";
    echo "<strong>Kemungkinan penyebab:</strong><br>";
    echo "1. Password salah (ada spasi?)<br>";
    echo "2. Database belum dibuat<br>";
    echo "3. Database user tidak punya akses<br><br>";
    exit;
}

// Test 4: Check users table
echo "<h2>Test 4: Check Users Table</h2>";
try {
    $stmt = $db->query("SELECT COUNT(*) as count FROM users");
    $count = $stmt->fetch()['count'];
    echo "✅ Users table exists<br>";
    echo "Total users: " . $count . "<br><br>";
} catch (Exception $e) {
    echo "❌ Cannot access users table<br>";
    echo "Error: " . $e->getMessage() . "<br><br>";
    exit;
}

// Test 5: Check admin user
echo "<h2>Test 5: Check Admin User</h2>";
try {
    $stmt = $db->prepare("SELECT * FROM users WHERE username = 'admin'");
    $stmt->execute();
    $admin = $stmt->fetch();
    
    if ($admin) {
        echo "✅ Admin user found<br>";
        echo "Username: " . $admin['username'] . "<br>";
        echo "Email: " . $admin['email'] . "<br>";
        echo "Role: " . $admin['role'] . "<br>";
        echo "Status: " . $admin['status'] . "<br>";
        echo "Password hash length: " . strlen($admin['password']) . " chars<br><br>";
    } else {
        echo "❌ Admin user not found in database!<br><br>";
    }
} catch (Exception $e) {
    echo "❌ Error checking admin user: " . $e->getMessage() . "<br><br>";
}

// Test 6: Test auth.php
echo "<h2>Test 6: Load auth.php</h2>";
try {
    // Don't actually require it, just check if file exists
    if (file_exists('auth.php')) {
        echo "✅ auth.php file exists<br>";
        echo "File size: " . filesize('auth.php') . " bytes<br><br>";
    } else {
        echo "❌ auth.php file not found!<br><br>";
    }
} catch (Exception $e) {
    echo "❌ Error with auth.php: " . $e->getMessage() . "<br><br>";
}

// Test 7: Session test
echo "<h2>Test 7: Session Test</h2>";
try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
        echo "✅ Session started successfully<br>";
        echo "Session ID: " . session_id() . "<br><br>";
    } else {
        echo "✅ Session already active<br><br>";
    }
} catch (Exception $e) {
    echo "❌ Session error: " . $e->getMessage() . "<br><br>";
}

echo "<hr>";
echo "<h2>✅ Diagnosis Complete</h2>";
echo "<p>Jika semua test di atas PASS, maka masalahnya ada di process-login.php atau login.php</p>";
echo "<p><a href='login.php'>Back to Login Page</a></p>";
?>
