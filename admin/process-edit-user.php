<?php
/**
 * Process Edit User
 * PRIMA (Pertamina Checklist Mobil Tangki)
 */

require_once '../auth/auth.php';
requireAdmin();

require_once '../config/config.php';

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$userId = (int)$input['user_id'];
$fullName = sanitizeInput($input['full_name'] ?? '');
$email = sanitizeInput($input['email'] ?? '');
$phone = sanitizeInput($input['phone'] ?? '');
$department = sanitizeInput($input['department'] ?? '');
$position = sanitizeInput($input['position'] ?? '');
$role = sanitizeInput($input['role'] ?? 'user');

// Validate required fields
if (empty($fullName) || empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Nama dan email wajib diisi']);
    exit;
}

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Format email tidak valid']);
    exit;
}

// Validate role
if (!in_array($role, ['user', 'admin', 'pengurus', 'hsse', 'manager_hsse'], true)) {
    echo json_encode(['success' => false, 'message' => 'Role tidak valid']);
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
    
    // Check if email is already used by another user
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$email, $userId]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Email sudah digunakan oleh user lain']);
        exit;
    }
    
    // Update user
    $stmt = $db->prepare("
        UPDATE users 
        SET full_name = ?, email = ?, phone = ?, department = ?, position = ?, role = ?
        WHERE id = ?
    ");
    
    $stmt->execute([
        $fullName,
        $email,
        $phone,
        $department,
        $position,
        $role,
        $userId
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Data user berhasil diupdate']);
    
} catch (Exception $e) {
    error_log("Edit User Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
