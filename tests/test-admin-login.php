<?php
/**
 * Test Admin Login - Diagnostic Tool
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Test Admin Login</h1>";
echo "<hr>";

require_once '../config/config.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Get admin user
    $stmt = $db->prepare("SELECT * FROM users WHERE username = 'admin'");
    $stmt->execute();
    $admin = $stmt->fetch();
    
    if (!$admin) {
        echo "<p style='color: red;'>❌ Admin user tidak ditemukan!</p>";
        exit;
    }
    
    echo "<h2>📊 Data Admin User:</h2>";
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Value</th></tr>";
    echo "<tr><td>ID</td><td>{$admin['id']}</td></tr>";
    echo "<tr><td>Username</td><td>{$admin['username']}</td></tr>";
    echo "<tr><td>Full Name</td><td>{$admin['full_name']}</td></tr>";
    echo "<tr><td>Email</td><td>{$admin['email']}</td></tr>";
    echo "<tr><td>Role</td><td>{$admin['role']}</td></tr>";
    echo "<tr><td>Status</td><td><strong style='color: " . ($admin['status'] === 'active' ? 'green' : 'red') . ";'>{$admin['status']}</strong></td></tr>";
    echo "<tr><td>Login Attempts</td><td>{$admin['login_attempts']}</td></tr>";
    echo "<tr><td>Locked Until</td><td>" . ($admin['locked_until'] ?? 'NULL') . "</td></tr>";
    echo "<tr><td>Password Hash</td><td style='font-family: monospace; font-size: 11px;'>" . substr($admin['password'], 0, 30) . "...</td></tr>";
    echo "</table>";
    
    echo "<hr>";
    echo "<h2>🔐 Test Password Verification:</h2>";
    
    // Test password variations
    $test_passwords = [
        'admin123',
        'Admin123',
        'ADMIN123',
        'admin',
        'password'
    ];
    
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr><th>Password</th><th>Result</th></tr>";
    
    foreach ($test_passwords as $test_pass) {
        $is_valid = password_verify($test_pass, $admin['password']);
        $result_color = $is_valid ? 'green' : 'red';
        $result_text = $is_valid ? '✅ VALID' : '❌ INVALID';
        
        echo "<tr>";
        echo "<td><code>{$test_pass}</code></td>";
        echo "<td style='color: {$result_color}; font-weight: bold;'>{$result_text}</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    echo "<hr>";
    echo "<h2>🔧 Solusi:</h2>";
    
    $correct_password = null;
    foreach ($test_passwords as $test_pass) {
        if (password_verify($test_pass, $admin['password'])) {
            $correct_password = $test_pass;
            break;
        }
    }
    
    if ($correct_password) {
        echo "<div style='background: #d4edda; padding: 20px; border-radius: 5px; border: 1px solid #c3e6cb;'>";
        echo "<h3 style='color: #155724; margin-top: 0;'>✅ Password Ditemukan!</h3>";
        echo "<p style='color: #155724;'><strong>Username:</strong> admin</p>";
        echo "<p style='color: #155724;'><strong>Password:</strong> <code style='background: #fff; padding: 5px;'>{$correct_password}</code></p>";
        echo "<p style='color: #155724;'>Silakan login dengan kredensial di atas.</p>";
        echo "</div>";
    } else {
        echo "<div style='background: #f8d7da; padding: 20px; border-radius: 5px; border: 1px solid #f5c6cb;'>";
        echo "<h3 style='color: #721c24; margin-top: 0;'>❌ Password Tidak Valid!</h3>";
        echo "<p style='color: #721c24;'>Hash password di database tidak cocok dengan password manapun yang di-test.</p>";
        echo "<p style='color: #721c24;'><strong>Solusi:</strong> Perlu regenerate password hash.</p>";
        echo "<a href="../auth/fix-password.php" style='display: inline-block; margin-top: 10px; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px;'>Fix Password Sekarang</a>";
        echo "</div>";
    }
    
    echo "<hr>";
    echo "<h2>📝 Session Info:</h2>";
    echo "<pre>";
    echo "Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Not Active') . "\n";
    echo "Session ID: " . (session_id() ?: 'None') . "\n";
    echo "</pre>";
    
    echo "<hr>";
    echo "<h2>🔗 Links:</h2>";
    echo "<a href="../auth/login.php" style='display: inline-block; margin: 5px; padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 4px;'>Go to Login</a>";
    echo "<a href="../tests/debug-login.php" style='display: inline-block; margin: 5px; padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px;'>Debug Login System</a>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 20px; border-radius: 5px; border: 1px solid #f5c6cb;'>";
    echo "<h3 style='color: #721c24;'>❌ Database Error!</h3>";
    echo "<p style='color: #721c24;'>" . $e->getMessage() . "</p>";
    echo "</div>";
}
?>
