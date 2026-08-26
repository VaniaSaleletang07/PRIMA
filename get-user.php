<?php
/**
 * Get Single User Data
 * PRIMA (Pertamina Checklist Mobil Tangki)
 */

require_once 'auth.php';
requireAdmin();

require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID tidak ditemukan']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    $userId = (int)$_GET['id'];
    
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User tidak ditemukan']);
        exit;
    }
    
    // Remove sensitive data
    unset($user['password']);
    
    echo json_encode(['success' => true, 'data' => $user]);
    
} catch (Exception $e) {
    error_log("Get User Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
