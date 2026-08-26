<?php
/**
 * Process Registration
 * PRIMA (Pertamina Checklist Mobil Tangki)
 */

require_once '../config/config.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../auth/register.php");
    exit;
}

// Get and sanitize input
$username = sanitizeInput($_POST['username'] ?? '');
$email = sanitizeInput($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';
$full_name = sanitizeInput($_POST['full_name'] ?? '');
$phone = sanitizeInput($_POST['phone'] ?? '');
$department = sanitizeInput($_POST['department'] ?? '');
$position = sanitizeInput($_POST['position'] ?? '');
$reason = sanitizeInput($_POST['reason'] ?? '');
$requested_role = in_array($_POST['requested_role'] ?? '', ['user', 'pengurus', 'manager_hsse'], true) ? $_POST['requested_role'] : 'user';

// Validate required fields
if (empty($username) || empty($email) || empty($password) || empty($full_name) || 
    empty($phone) || empty($department) || empty($position) || empty($reason)) {
    header('Location: register.php?error=invalid');
    exit;
}

// Validate password match
if ($password !== $confirm_password) {
    header('Location: register.php?error=password');
    exit;
}

// Validate username format (alphanumeric and underscore only)
if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
    header('Location: register.php?error=invalid');
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: register.php?error=invalid');
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Check if username or email already exists in users table
    $stmt = $db->prepare("
        SELECT id FROM users 
        WHERE username = :username OR email = :email
    ");
    $stmt->execute([
        ':username' => $username,
        ':email' => $email
    ]);
    
    if ($stmt->fetch()) {
        header('Location: register.php?error=exists');
        exit;
    }
    
    // Check if username or email already exists in registrations table
    $stmt = $db->prepare("
        SELECT id, status FROM user_registrations 
        WHERE (username = :username OR email = :email)
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([
        ':username' => $username,
        ':email' => $email
    ]);
    $existing_reg = $stmt->fetch();

    if ($existing_reg) {
        if ($existing_reg['status'] === 'pending') {
            // Still waiting approval
            header('Location: register.php?error=pending');
            exit;
        } elseif ($existing_reg['status'] === 'approved') {
            // Already approved, account exists
            header('Location: register.php?error=exists');
            exit;
        } else {
            // Rejected — delete old record so user can re-register
            $stmt = $db->prepare("DELETE FROM user_registrations WHERE username = :username OR email = :email");
            $stmt->execute([':username' => $username, ':email' => $email]);
        }
    }
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Ensure pengurus tables exist (safe to run every time)
    ensurePengurusTablesExist();

    // Insert registration request
    $stmt = $db->prepare("
        INSERT INTO user_registrations 
        (username, password, full_name, email, phone, department, position, reason, requested_role)
        VALUES 
        (:username, :password, :full_name, :email, :phone, :department, :position, :reason, :requested_role)
    ");
    
    $stmt->execute([
        ':username' => $username,
        ':password' => $hashed_password,
        ':full_name' => $full_name,
        ':email' => $email,
        ':phone' => $phone,
        ':department' => $department,
        ':position' => $position,
        ':reason' => $reason,
        ':requested_role' => $requested_role
    ]);
    
    // Log audit
    logAudit(null, 'REGISTRATION', $username, "Pendaftaran user baru: {$full_name} ({$email})");
    
    // Redirect to login with success message
    header('Location: login.php?success=registered');
    exit;
    
} catch(Exception $e) {
    error_log("Registration Error: " . $e->getMessage());
    header('Location: register.php?error=system');
    exit;
}
