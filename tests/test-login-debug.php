<?php
/**
 * DEBUG LOGIN - Detail Error Diagnosis
 */

// Enable ALL error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'login_errors.log');

echo "<!DOCTYPE html><html><head><title>Login Debug</title>";
echo "<style>body{font-family:Arial;padding:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} pre{background:#f5f5f5;padding:10px;border-radius:5px;}</style>";
echo "</head><body>";
echo "<h1>🔍 DEBUG LOGIN PROCESS</h1>";
echo "<hr>";

// Simulate POST data
$_POST['username'] = 'admin';
$_POST['password'] = 'admin123';
$_SERVER['REQUEST_METHOD'] = 'POST';

echo "<h2>Step 1: Input Data</h2>";
echo "Username: <strong>{$_POST['username']}</strong><br>";
echo "Password: <strong>" . str_repeat('*', strlen($_POST['password'])) . "</strong> (length: " . strlen($_POST['password']) . ")<br><br>";

// Load config
echo "<h2>Step 2: Load Config</h2>";
try {
    require_once '../config/config.php';
    echo "<p class='success'>✅ config.php loaded</p>";
    echo "DB_NAME: " . DB_NAME . "<br>";
    echo "DB_HOST: " . DB_HOST . "<br><br>";
} catch (Exception $e) {
    echo "<p class='error'>❌ Error loading config: {$e->getMessage()}</p>";
    exit;
}

// Sanitize input
echo "<h2>Step 3: Sanitize Input</h2>";
$username = sanitizeInput($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
echo "Sanitized username: <strong>$username</strong><br>";
echo "Password length: " . strlen($password) . "<br><br>";

// Connect to database
echo "<h2>Step 4: Database Connection</h2>";
try {
    $db = Database::getInstance()->getConnection();
    echo "<p class='success'>✅ Database connected</p><br>";
} catch (Exception $e) {
    echo "<p class='error'>❌ Database connection failed: {$e->getMessage()}</p>";
    exit;
}

// Get user from database
echo "<h2>Step 5: Query User</h2>";
try {
    $stmt = $db->prepare("
        SELECT id, username, password, full_name, email, role, status, login_attempts, locked_until
        FROM users 
        WHERE username = :username
    ");
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo "<p class='error'>❌ User not found in database!</p>";
        
        // Check if users table has any records
        $count = $db->query("SELECT COUNT(*) as total FROM users")->fetch();
        echo "<p class='info'>Total users in database: {$count['total']}</p>";
        
        // List all usernames
        $users = $db->query("SELECT username FROM users")->fetchAll();
        echo "<p class='info'>Available usernames:</p><ul>";
        foreach ($users as $u) {
            echo "<li>{$u['username']}</li>";
        }
        echo "</ul>";
        exit;
    }
    
    echo "<p class='success'>✅ User found!</p>";
    echo "<table border='1' cellpadding='10' style='border-collapse:collapse;'>";
    echo "<tr><th>Field</th><th>Value</th></tr>";
    echo "<tr><td>ID</td><td>{$user['id']}</td></tr>";
    echo "<tr><td>Username</td><td>{$user['username']}</td></tr>";
    echo "<tr><td>Full Name</td><td>{$user['full_name']}</td></tr>";
    echo "<tr><td>Email</td><td>{$user['email']}</td></tr>";
    echo "<tr><td>Role</td><td>{$user['role']}</td></tr>";
    echo "<tr><td>Status</td><td>{$user['status']}</td></tr>";
    echo "<tr><td>Login Attempts</td><td>{$user['login_attempts']}</td></tr>";
    echo "<tr><td>Locked Until</td><td>" . ($user['locked_until'] ?: 'Not locked') . "</td></tr>";
    echo "<tr><td>Password Hash</td><td><small>{$user['password']}</small></td></tr>";
    echo "</table><br>";
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Query error: {$e->getMessage()}</p>";
    exit;
}

// Check account lock
echo "<h2>Step 6: Check Account Lock</h2>";
if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
    echo "<p class='error'>❌ Account is LOCKED until {$user['locked_until']}</p>";
    exit;
} else {
    echo "<p class='success'>✅ Account is NOT locked</p><br>";
}

// Verify password
echo "<h2>Step 7: Verify Password</h2>";
$isValid = password_verify($password, $user['password']);

echo "<table border='1' cellpadding='10' style='border-collapse:collapse;'>";
echo "<tr><th>Detail</th><th>Value</th></tr>";
echo "<tr><td>Input Password</td><td>$password</td></tr>";
echo "<tr><td>Stored Hash</td><td><small>{$user['password']}</small></td></tr>";
echo "<tr><td>Verification Result</td><td><strong>" . ($isValid ? '✅ VALID' : '❌ INVALID') . "</strong></td></tr>";
echo "</table><br>";

if (!$isValid) {
    echo "<div style='background:#f8d7da; padding:20px; border-radius:5px;'>";
    echo "<h3>❌ PASSWORD MISMATCH!</h3>";
    echo "<p>Password yang Anda masukkan tidak cocok dengan hash di database.</p>";
    echo "<p><strong>Solusi:</strong></p>";
    echo "<ol>";
    echo "<li>Akses <a href="../auth/fix-password.php">fix-password.php</a> untuk reset password</li>";
    echo "<li>Atau jalankan command: <code>php fix-password.php</code></li>";
    echo "</ol>";
    echo "</div>";
    exit;
}

// Check user status
echo "<h2>Step 8: Check User Status</h2>";
if ($user['status'] !== 'active') {
    echo "<p class='error'>❌ User status is: {$user['status']}</p>";
    echo "<p>User must be 'active' to login.</p>";
    exit;
} else {
    echo "<p class='success'>✅ User status is ACTIVE</p><br>";
}

// Start session
echo "<h2>Step 9: Session Management</h2>";
try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['last_activity'] = time();
    
    echo "<p class='success'>✅ Session created successfully!</p>";
    echo "<pre>" . print_r($_SESSION, true) . "</pre>";
    
} catch (Exception $e) {
    echo "<p class='error'>❌ Session error: {$e->getMessage()}</p>";
    exit;
}

// Update last login
echo "<h2>Step 10: Update Last Login</h2>";
try {
    $stmt = $db->prepare("UPDATE users SET last_login = NOW(), login_attempts = 0 WHERE id = :id");
    $stmt->execute([':id' => $user['id']]);
    echo "<p class='success'>✅ Last login updated</p><br>";
} catch (Exception $e) {
    echo "<p class='error'>❌ Update failed: {$e->getMessage()}</p>";
}

echo "<hr>";
echo "<div style='background:#d4edda; padding:20px; border-radius:5px; margin:20px 0;'>";
echo "<h2 style='color:#155724;'>✅ LOGIN SUCCESSFUL!</h2>";
echo "<p>All checks passed. You should be able to login now.</p>";
echo "<p><a href="../auth/login.php" style='display:inline-block; background:#28a745; color:white; padding:10px 20px; text-decoration:none; border-radius:5px;'>Try Login Again</a></p>";
echo "</div>";

echo "</body></html>";
?>
