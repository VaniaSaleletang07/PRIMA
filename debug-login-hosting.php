<?php
/**
 * DEBUG LOGIN PROCESS - HOSTING
 * File ini mensimulasikan proses login untuk melihat error detail
 * Upload ke hosting dan akses via browser
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 DEBUG LOGIN PROCESS</h1>";
echo "<p>Simulasi proses login untuk melihat error detail...</p><hr>";

// Simulate POST data
$_POST['username'] = 'admin';
$_POST['password'] = 'admin123';
$_SERVER['REQUEST_METHOD'] = 'POST';

echo "<h2>Step 1: Load Config</h2>";
try {
    require_once 'config.php';
    echo "✅ config.php loaded<br>";
    echo "DB_HOST: " . DB_HOST . "<br>";
    echo "DB_NAME: " . DB_NAME . "<br><br>";
} catch(Exception $e) {
    echo "❌ Error loading config: " . $e->getMessage() . "<br><br>";
    exit;
}

echo "<h2>Step 2: Sanitize Input</h2>";
$username = sanitizeInput($_POST['username']);
$password = $_POST['password'];
echo "Username: {$username}<br>";
echo "Password length: " . strlen($password) . "<br><br>";

echo "<h2>Step 3: Connect to Database</h2>";
try {
    $db = Database::getInstance()->getConnection();
    echo "✅ Database connected<br><br>";
} catch(Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "<br><br>";
    exit;
}

echo "<h2>Step 4: Query User from Database</h2>";
try {
    $stmt = $db->prepare("
        SELECT id, username, password, full_name, email, role, status, 
               login_attempts, locked_until 
        FROM users 
        WHERE username = :username
    ");
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo "❌ User tidak ditemukan di database<br>";
        echo "<strong>SOLUSI:</strong> Pastikan admin user sudah dibuat<br><br>";
        exit;
    }
    
    echo "✅ User ditemukan<br>";
    echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
    echo "<tr><th>Field</th><th>Value</th></tr>";
    echo "<tr><td>ID</td><td>{$user['id']}</td></tr>";
    echo "<tr><td>Username</td><td>{$user['username']}</td></tr>";
    echo "<tr><td>Full Name</td><td>{$user['full_name']}</td></tr>";
    echo "<tr><td>Role</td><td>{$user['role']}</td></tr>";
    echo "<tr><td>Status</td><td>{$user['status']}</td></tr>";
    echo "<tr><td>Login Attempts</td><td>{$user['login_attempts']}</td></tr>";
    echo "<tr><td>Locked Until</td><td>" . ($user['locked_until'] ?: 'NULL') . "</td></tr>";
    echo "</table><br>";
    
} catch(Exception $e) {
    echo "❌ Query error: " . $e->getMessage() . "<br><br>";
    exit;
}

echo "<h2>Step 5: Check Account Lock</h2>";
if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
    $lockTime = date('Y-m-d H:i:s', strtotime($user['locked_until']));
    echo "❌ Account is LOCKED until {$lockTime}<br>";
    echo "<strong>SOLUSI:</strong> Jalankan SQL ini di phpMyAdmin:<br>";
    echo "<pre>UPDATE users SET locked_until = NULL, login_attempts = 0 WHERE username = 'admin';</pre><br>";
    exit;
} else {
    echo "✅ Account is NOT locked<br><br>";
}

echo "<h2>Step 6: Verify Password</h2>";
$passwordMatch = password_verify($password, $user['password']);

if (!$passwordMatch) {
    echo "❌ Password TIDAK COCOK<br>";
    echo "Password yang dicoba: admin123<br>";
    echo "Hash di database: " . substr($user['password'], 0, 60) . "...<br><br>";
    
    echo "<strong>SOLUSI:</strong> Update password dengan SQL ini:<br>";
    $newHash = password_hash('admin123', PASSWORD_DEFAULT);
    echo "<pre>UPDATE users SET password = '{$newHash}' WHERE username = 'admin';</pre><br>";
    exit;
} else {
    echo "✅ Password COCOK<br><br>";
}

echo "<h2>Step 7: Check User Status</h2>";
if ($user['status'] !== 'active') {
    echo "❌ User status is: {$user['status']}<br>";
    echo "<strong>SOLUSI:</strong> Aktifkan user dengan SQL ini:<br>";
    echo "<pre>UPDATE users SET status = 'active' WHERE username = 'admin';</pre><br>";
    exit;
} else {
    echo "✅ User status is ACTIVE<br><br>";
}

echo "<h2>Step 8: Test Session Start</h2>";
try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    echo "✅ Session started successfully<br>";
    echo "Session ID: " . session_id() . "<br><br>";
} catch(Exception $e) {
    echo "❌ Session start FAILED: " . $e->getMessage() . "<br>";
    echo "<strong>MASALAH:</strong> Hosting tidak support session atau folder session tidak writable<br><br>";
    exit;
}

echo "<h2>Step 9: Test Session Write</h2>";
try {
    $_SESSION['test'] = 'value';
    echo "✅ Session write OK<br>";
    echo "Test value: " . $_SESSION['test'] . "<br><br>";
} catch(Exception $e) {
    echo "❌ Session write FAILED: " . $e->getMessage() . "<br><br>";
    exit;
}

echo "<h2>Step 10: Reset Login Attempts</h2>";
try {
    $stmt = $db->prepare("
        UPDATE users 
        SET login_attempts = 0, 
            locked_until = NULL,
            last_login = NOW()
        WHERE id = :id
    ");
    $stmt->execute([':id' => $user['id']]);
    echo "✅ Login attempts reset<br><br>";
} catch(Exception $e) {
    echo "❌ Update failed: " . $e->getMessage() . "<br><br>";
    exit;
}

echo "<h2>Step 11: Create User Session</h2>";
try {
    $sessionId = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + 3600);
    
    $stmt = $db->prepare("
        INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent, expires_at)
        VALUES (:user_id, :session_id, :ip_address, :user_agent, :expires_at)
    ");
    
    $stmt->execute([
        ':user_id' => $user['id'],
        ':session_id' => $sessionId,
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        ':expires_at' => $expiresAt
    ]);
    
    echo "✅ User session created in database<br>";
    echo "Session ID: {$sessionId}<br><br>";
    
} catch(Exception $e) {
    echo "❌ Create session failed: " . $e->getMessage() . "<br>";
    echo "Error Code: " . $e->getCode() . "<br>";
    
    // Check if table exists
    echo "<br><strong>Checking if user_sessions table exists...</strong><br>";
    try {
        $stmt = $db->query("SHOW TABLES LIKE 'user_sessions'");
        $exists = $stmt->fetch();
        if (!$exists) {
            echo "❌ Tabel 'user_sessions' TIDAK ADA<br>";
            echo "<strong>SOLUSI:</strong> Jalankan database.sql lengkap di phpMyAdmin hosting<br><br>";
        } else {
            echo "✅ Tabel user_sessions ada<br>";
            echo "Error mungkin karena: " . $e->getMessage() . "<br><br>";
        }
    } catch(Exception $e2) {
        echo "Error checking table: " . $e2->getMessage() . "<br><br>";
    }
    exit;
}

echo "<hr>";
echo "<h2>🎉 SEMUA TEST BERHASIL!</h2>";
echo "<p style='color:green;font-weight:bold;'>Jika sampai sini semua OK, berarti proses login seharusnya BISA.</p>";
echo "<p>Coba langkah berikut:</p>";
echo "<ol>";
echo "<li>Clear browser cache & cookies</li>";
echo "<li>Close browser completely</li>";
echo "<li>Buka browser baru (atau incognito)</li>";
echo "<li>Login di: <a href='login.php'>login.php</a></li>";
echo "<li>Username: <strong>admin</strong></li>";
echo "<li>Password: <strong>admin123</strong></li>";
echo "</ol>";

echo "<hr>";
echo "<p style='color:#999;'><em>Setelah login berhasil, HAPUS file ini untuk keamanan.</em></p>";
?>
