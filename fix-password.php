<?php
/**
 * Fix Admin Password
 */

// Password yang akan diset
$new_password = 'admin123';

// Generate hash baru
$new_hash = password_hash($new_password, PASSWORD_BCRYPT);

echo "=== FIX ADMIN PASSWORD ===\n\n";

echo "Password baru: admin123\n";
echo "Hash baru: $new_hash\n\n";

// Test hash lama dari database
$old_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
$is_valid = password_verify('admin123', $old_hash);

echo "Test hash lama:\n";
echo "Hash: $old_hash\n";
echo "Valid untuk 'admin123': " . ($is_valid ? 'YES ✅' : 'NO ❌') . "\n\n";

// Update database
require_once 'config.php';

try {
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("UPDATE users SET password = :password WHERE username = 'admin'");
    $stmt->execute([':password' => $new_hash]);
    
    echo "✅ Password admin berhasil diupdate!\n";
    echo "\nSilakan login dengan:\n";
    echo "Username: admin\n";
    echo "Password: admin123\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
