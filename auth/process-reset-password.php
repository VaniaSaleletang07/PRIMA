<?php
/**
 * Process Reset User Password
 * PRIMA (Pertamina Checklist Mobil Tangki)
 */

require_once '../auth/auth.php';
requireAdmin();

require_once '../config/config.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['user_id']) || !isset($input['new_password'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$userId = (int)$input['user_id'];
$newPassword = $input['new_password'];

// Validate password length
if (strlen($newPassword) < 6) {
    echo json_encode(['success' => false, 'message' => 'Password minimal 6 karakter']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Check if user exists
    $stmt = $db->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User tidak ditemukan']);
        exit;
    }
    
    // Hash new password
    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
    
    // Update password and reset login attempts
    $stmt = $db->prepare("
        UPDATE users 
        SET password = ?, login_attempts = 0, locked_until = NULL 
        WHERE id = ?
    ");
    $stmt->execute([$hashedPassword, $userId]);
    
    echo json_encode(['success' => true, 'message' => 'Password berhasil direset']);
    
} catch (Exception $e) {
    error_log("Reset Password Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
