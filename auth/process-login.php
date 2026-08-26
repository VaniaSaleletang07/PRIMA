<?php
/**
 * Process Login
 * PRIMA (Pertamina Checklist Mobil Tangki)
 */

require_once '../config/config.php';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../auth/login.php");
    exit;
}

$username = sanitizeInput($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    header('Location: login.php?error=invalid');
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Get user from database
    $stmt = $db->prepare("
        SELECT id, username, password, full_name, email, role, status, login_attempts, locked_until
        FROM users 
        WHERE username = :username
    ");
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();
    
    if (!$user) {
        // User not found in users table — check if they have a pending/rejected registration
        $stmt2 = $db->prepare("SELECT status FROM user_registrations WHERE username = :username OR email = :email_search ORDER BY created_at DESC LIMIT 1");
        $stmt2->execute([':username' => $username, ':email_search' => $username]);
        $reg = $stmt2->fetch();

        if ($reg) {
            if ($reg['status'] === 'pending') {
                header('Location: login.php?error=pending');
            } elseif ($reg['status'] === 'rejected') {
                header('Location: login.php?error=rejected');
            } else {
                header('Location: login.php?error=invalid');
            }
        } else {
            header('Location: login.php?error=invalid');
        }
        exit;
    }
    
    // Check if account is locked
    if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
        header('Location: login.php?error=locked');
        exit;
    }
    
    // Verify password
    if (!password_verify($password, $user['password'])) {
        // Increment login attempts
        $attempts = $user['login_attempts'] + 1;
        $locked_until = null;
        
        // Lock account after 5 failed attempts for 15 minutes
        if ($attempts >= 5) {
            $locked_until = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        }
        
        $stmt = $db->prepare("
            UPDATE users 
            SET login_attempts = :attempts, 
                locked_until = :locked_until 
            WHERE id = :id
        ");
        $stmt->execute([
            ':attempts' => $attempts,
            ':locked_until' => $locked_until,
            ':id' => $user['id']
        ]);
        
        if ($locked_until) {
            header('Location: login.php?error=locked');
        } else {
            header('Location: login.php?error=invalid');
        }
        exit;
    }
    
    // Check if user is active
    if ($user['status'] !== 'active') {
        header('Location: login.php?error=inactive');
        exit;
    }
    
    // Login successful - Reset login attempts
    $stmt = $db->prepare("
        UPDATE users 
        SET login_attempts = 0, 
            locked_until = NULL,
            last_login = NOW() 
        WHERE id = :id
    ");
    $stmt->execute([':id' => $user['id']]);
    
    // Create session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['status'] = $user['status'];
    $_SESSION['last_activity'] = time();
    
    // Store session in database
    $session_token = session_id();
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $expires_at = date('Y-m-d H:i:s', time() + 3600); // 1 hour
    
    // Delete old session if exists (prevent duplicate key error)
    $stmt = $db->prepare("DELETE FROM user_sessions WHERE session_token = :session_token OR user_id = :user_id");
    $stmt->execute([':session_token' => $session_token, ':user_id' => $user['id']]);
    
    // Insert new session
    $stmt = $db->prepare("
        INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent, expires_at)
        VALUES (:user_id, :session_token, :ip_address, :user_agent, :expires_at)
    ");
    $stmt->execute([
        ':user_id' => $user['id'],
        ':session_token' => $session_token,
        ':ip_address' => $ip_address,
        ':user_agent' => $user_agent,
        ':expires_at' => $expires_at
    ]);
    
    // Log audit
    logAudit(null, 'LOGIN', $user['username'], "User login dari IP: {$ip_address}");
    
    // Redirect setelah login
    header("Location: ../home.php");
    exit;
        
} catch(Exception $e) {
    error_log("LOGIN ERROR: " . $e->getMessage());
    error_log("LOGIN ERROR FILE: " . $e->getFile());
    error_log("LOGIN ERROR LINE: " . $e->getLine());
    header('Location: login.php?error=system');
    exit;
}
