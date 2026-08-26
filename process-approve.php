<?php
/**
 * Process Registration Approval/Rejection
 * PRIMA (Pertamina Checklist Mobil Tangki)
 */

require_once 'auth.php';
requireAdmin();

require_once 'config.php';

header('Content-Type: application/json');

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['id']) || !isset($input['action'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$registrationId = (int)$input['id'];
$action = $input['action'];
$adminId = $_SESSION['user_id'];

try {
    $db = Database::getInstance()->getConnection();
    
    // Get registration details
    $stmt = $db->prepare("SELECT * FROM user_registrations WHERE id = ?");
    $stmt->execute([$registrationId]);
    $registration = $stmt->fetch();
    
    if (!$registration) {
        echo json_encode(['success' => false, 'message' => 'Registration not found']);
        exit;
    }
    
    if ($registration['status'] !== 'pending') {
        echo json_encode(['success' => false, 'message' => 'Registration already processed']);
        exit;
    }

    $db->beginTransaction();
    
    if ($action === 'approve') {
        // Use the requested_role from registration (default 'user' for safety)
        $assignRole = in_array($registration['requested_role'] ?? '', ['user', 'pengurus', 'manager_hsse'], true)
            ? $registration['requested_role']
            : 'user';

        // Create new user account
        $insertUser = $db->prepare("
            INSERT INTO users (
                username, password, full_name, email, phone, 
                department, position, role, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ");
        
        $insertUser->execute([
            $registration['username'],
            $registration['password'],
            $registration['full_name'],
            $registration['email'],
            $registration['phone'],
            $registration['department'],
            $registration['position'],
            $assignRole
        ]);
        
        // Update registration status
        $updateReg = $db->prepare("
            UPDATE user_registrations 
            SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() 
            WHERE id = ?
        ");
        $updateReg->execute([$adminId, $registrationId]);
        
        // Log audit
        logAudit(
            null, 
            'APPROVE', 
            $_SESSION['username'], 
            "Registration approved: {$registration['full_name']} ({$registration['email']})"
        );
        
        $db->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'User approved and account created'
        ]);
        
    } elseif ($action === 'reject') {
        $reason = $input['reason'] ?? '';
        
        // Update registration status
        $updateReg = $db->prepare("
            UPDATE user_registrations 
            SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW(), rejection_reason = ?
            WHERE id = ?
        ");
        $updateReg->execute([$adminId, $reason, $registrationId]);
        
        // Log audit
        logAudit(
            null, 
            'REJECT', 
            $_SESSION['username'], 
            "Registration rejected: {$registration['full_name']} ({$registration['email']}) - Reason: {$reason}"
        );
        
        $db->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Registration rejected'
        ]);
        
    } else {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Process Approve Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
