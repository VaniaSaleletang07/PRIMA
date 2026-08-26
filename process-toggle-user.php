<?php
/**
 * Process Toggle User Status
 * PRIMA (Pertamina Checklist Mobil Tangki)
 */

require_once 'auth.php';
requireAdmin();

require_once 'config.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['user_id']) || !isset($input['status'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$userId = (int)$input['user_id'];
$status = $input['status'];

// Validate status
if (!in_array($status, ['active', 'inactive'])) {
    echo json_encode(['success' => false, 'message' => 'Status tidak valid']);
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
    
    // Update status
    $stmt = $db->prepare("UPDATE users SET status = ? WHERE id = ?");
    $stmt->execute([$status, $userId]);
    
    $action = $status === 'active' ? 'diaktifkan' : 'dinonaktifkan';
    echo json_encode(['success' => true, 'message' => "User berhasil {$action}"]);
    
} catch (Exception $e) {
    error_log("Toggle User Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
