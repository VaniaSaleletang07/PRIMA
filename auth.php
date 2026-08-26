<?php
/**
 * Authentication Middleware
 * PRIMA (Pertamina Checklist Mobil Tangki)
 */

require_once __DIR__ . '/config.php';

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

// Function to check if user is admin
function isAdmin() {
    return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}
  
// Function to check if user is pengurus (tank truck manager / contractor)
function isPengurus() {
    return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'pengurus';
}

// Function to check if user is HSSE team
function isHSSE() {
    return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'hsse';
}

// Function to check if user is Manager HSSE
function isManagerHSSE() {
    return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'manager_hsse';
}

// Alias bisnis: Manager adalah pemberi approval akhir checklist.
function isManager() {
    return isManagerHSSE();
}

function getRoleLabel(): string {
    if (isAdmin()) return 'Administrator';
    if (isManager()) return 'Manager';
    if (isHSSE()) return 'Petugas HSSE';
    if (isPengurus()) return 'Pengurus Kendaraan';
    return 'User';
}

/** Tolak akses Manager pada endpoint yang memodifikasi data operasional. */
function requireNonManager(): void {
    requireLogin();
    if (isManager()) {
        jsonResponse(false, 'Akses ditolak: Manager hanya dapat melihat data serta memberikan approval dan tanda tangan digital.', null, 403);
    }
}

// Can submit/sign as HSSE (first signature)
function canSignHSSE() {
    return isAdmin() || isHSSE();
}

// Can sign / approve as Manager (final signature). SECURITY: hanya role
// Manager yang boleh melakukan Digital Signature Manager — Admin dan role
// lain TIDAK diberi bypass di sini, meskipun Admin punya hak administratif
// lain (reset draft, dsb) lewat fungsi terpisah.
function canSignManager() {
    return isManager();
}

// Can reject a checklist (administrative action, berbeda dari TTD Manager).
// Admin tetap boleh menolak untuk keperluan administratif, tapi TIDAK boleh
// membubuhkan Digital Signature Manager (lihat canSignManager()).
function canRejectChecklist() {
    return isAdmin() || isManager();
}

// Can access approval queue (halaman bersama: HSSE melihat antrian TTD HSSE,
// Manager melihat antrian "Checklist Menunggu Persetujuan", Admin mengawasi
// semuanya). Aksi yang benar-benar sensitif (TTD Manager) tetap dibatasi lewat
// canSignManager() di dalam halaman/endpoint terkait.
function canAccessApproval() {
    return isAdmin() || isHSSE() || isManager();
}

/**
 * Hentikan eksekusi dengan HTTP 403 Forbidden untuk request halaman (bukan API
 * JSON). Dipakai ketika user SUDAH login tapi role-nya tidak diizinkan
 * mengakses route/halaman tertentu (mis. non-Manager memaksa buka halaman
 * approval Manager lewat URL).
 */
function forbiddenPage(string $message = 'Akses ditolak. Anda tidak memiliki izin untuk mengakses halaman ini.'): void {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8">'
       . '<title>403 Forbidden</title>'
       . '<style>body{font-family:"Segoe UI",Tahoma,Geneva,Verdana,sans-serif;background:#eef2f7;'
       . 'display:flex;align-items:center;justify-content:center;height:100vh;margin:0}'
       . '.box{background:#fff;padding:36px 40px;border-radius:14px;box-shadow:0 8px 30px rgba(0,0,0,.1);'
       . 'max-width:420px;text-align:center}h1{color:#dc2626;font-size:56px;margin:0 0 8px}'
       . 'h2{color:#1a2332;margin:0 0 12px}p{color:#6b7280;font-size:14px;line-height:1.6}'
       . 'a{display:inline-block;margin-top:16px;color:#10334d;font-weight:600;text-decoration:none}</style>'
       . '</head><body><div class="box"><h1>403</h1><h2>Forbidden</h2>'
       . '<p>' . htmlspecialchars($message) . '</p>'
       . '<a href="home.php">&larr; Kembali ke Home</a></div></body></html>';
    exit;
}

// Function to require pengurus or admin access
function requirePengurusOrAdmin() {
    requireLogin();
    if (!isAdmin() && !isPengurus()) {
        header('Location: login.php?error=unauthorized');
        exit;
    }
}

// Function to check if user is active
function isActiveUser() {
    return isLoggedIn() && isset($_SESSION['status']) && $_SESSION['status'] === 'active';
}

// Function to require login
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
    
    if (!isActiveUser()) {
        session_destroy();
        header('Location: login.php?error=inactive');
        exit;
    }
}

// Function to require admin access
function requireAdmin() {
    requireLogin();
    
    if (!isAdmin()) {
        header('Location: login.php?error=unauthorized');
        exit;
    }
}

// Function to get current user info
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'full_name' => $_SESSION['full_name'] ?? '',
        'email' => $_SESSION['email'] ?? '',
        'role' => $_SESSION['role'] ?? 'user',
        'status' => $_SESSION['status'] ?? 'pending'
    ];
}

// Function to update last activity
function updateLastActivity() {
    if (!isLoggedIn()) {
        return;
    }
    
    $_SESSION['last_activity'] = time();
    
    // Update session in database
    require_once 'config.php';
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            UPDATE user_sessions 
            SET last_activity = NOW() 
            WHERE session_token = :session_token
        ");
        $stmt->execute([':session_token' => session_id()]);
    } catch(Exception $e) {
        error_log("Update activity error: " . $e->getMessage());
    }
}

// Function to check session timeout
function checkSessionTimeout() {
    if (!isLoggedIn()) {
        return;
    }
    
    $timeout = 3600; // 1 hour
    
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
        session_destroy();
        header('Location: login.php?error=timeout');
        exit;
    }
    
    updateLastActivity();
}

// Function to logout
function logout() {
    if (isLoggedIn()) {
        // Remove session from database
        require_once 'config.php';
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("DELETE FROM user_sessions WHERE session_id = :session_id");
            $stmt->execute([':session_id' => session_id()]);
        } catch(Exception $e) {
            error_log("Logout error: " . $e->getMessage());
        }
    }
    
    session_destroy();
    header('Location: login.php');
    exit;
}

// Auto-check session timeout on every request
checkSessionTimeout();
