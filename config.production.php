<?php
/**
 * Production Configuration File
 * PRIMA (Pertamina Checklist Mobil Tangki)
 * 
 * PENTING: File ini untuk PRODUCTION deployment
 * Copy file ini ke config.php setelah setup hosting
 */

// ============================================
// DATABASE CONFIGURATION - PRODUCTION
// ============================================
// ⚠️ GANTI nilai ini dengan credentials production Anda
define('DB_HOST', 'localhost');              // atau IP server database
define('DB_USER', 'ekim_user');              // JANGAN gunakan 'root'!
define('DB_PASS', 'YOUR_SECURE_PASSWORD');   // ⚠️ WAJIB GANTI dengan password kuat!
define('DB_NAME', 'checklist_ekim');
define('DB_CHARSET', 'utf8mb4');

// ============================================
// APPLICATION CONFIGURATION
// ============================================
define('APP_NAME', 'PRIMA (Pertamina Checklist Mobil Tangki)');
define('APP_VERSION', '1.0.0');
define('TIMEZONE', 'Asia/Jakarta');
define('APP_ENV', 'production');  // production / development

// ============================================
// SECURITY CONFIGURATION
// ============================================
define('SESSION_TIMEOUT', 3600);        // 1 hour (3600 seconds)
define('ENABLE_AUDIT_LOG', true);       // Wajib ON untuk production
define('ENABLE_IP_WHITELIST', false);   // Set TRUE untuk batasi IP
define('MAX_LOGIN_ATTEMPTS', 5);        // Anti brute force
define('LOGIN_LOCKOUT_TIME', 900);      // 15 minutes lockout

// IP Whitelist (jika ENABLE_IP_WHITELIST = true)
// Tambahkan IP Pertamina yang diizinkan
define('ALLOWED_IPS', [
    '192.168.1.0/24',     // Contoh: Local network Pertamina
    '10.0.0.0/8',         // Contoh: Internal network
    // Tambahkan IP publik Pertamina jika perlu
]);

// ============================================
// FILE & UPLOAD CONFIGURATION
// ============================================
define('MAX_UPLOAD_SIZE', 10485760);    // 10MB in bytes
define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'pdf']);

// ============================================
// CSRF PROTECTION
// ============================================
define('CSRF_TOKEN_NAME', 'csrf_token');
define('CSRF_TOKEN_EXPIRY', 7200);      // 2 hours

// ============================================
// Set timezone
// ============================================
date_default_timezone_set(TIMEZONE);

// ============================================
// Error Handling - PRODUCTION MODE
// ============================================
if (APP_ENV === 'production') {
    error_reporting(0);                  // NO error display in production
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/logs/php_errors.log');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
}

// ============================================
// Session Security - PRODUCTION
// ============================================
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1);         // ⚠️ SET to 1 setelah HTTPS aktif!
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
ini_set('session.use_strict_mode', 1);

// ============================================
// Security Headers
// ============================================
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

// Content Security Policy (adjust sesuai kebutuhan)
$csp = "default-src 'self'; ";
$csp .= "script-src 'self' 'unsafe-inline'; ";
$csp .= "style-src 'self' 'unsafe-inline'; ";
$csp .= "img-src 'self' data:; ";
$csp .= "font-src 'self'; ";
$csp .= "connect-src 'self'; ";
header("Content-Security-Policy: " . $csp);

// HSTS Header (hanya jika menggunakan HTTPS)
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
}

// ============================================
// Database Connection Class
// ============================================
class Database {
    private $conn;
    private static $instance = null;
    private $connectionAttempts = 0;
    private $maxAttempts = 3;

    private function __construct() {
        $this->connect();
    }

    private function connect() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET,
                PDO::ATTR_PERSISTENT         => false,  // Connection pooling
                PDO::ATTR_TIMEOUT            => 5       // 5 seconds timeout
            ];
            
            $this->conn = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            // Set additional MySQL settings
            $this->conn->exec("SET time_zone = '+07:00'");  // Asia/Jakarta
            
        } catch(PDOException $e) {
            $this->connectionAttempts++;
            
            // Log error
            error_log("Database Connection Error [Attempt {$this->connectionAttempts}]: " . $e->getMessage());
            
            // Retry connection
            if ($this->connectionAttempts < $this->maxAttempts) {
                sleep(1);  // Wait 1 second before retry
                $this->connect();
            } else {
                // Send email to admin (optional)
                // mail('admin@pertamina.com', 'DB Connection Failed', $e->getMessage());
                
                die(json_encode([
                    'success' => false,
                    'message' => 'Sistem sedang dalam maintenance. Silakan coba beberapa saat lagi.'
                ]));
            }
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        // Check if connection is still alive
        try {
            $this->conn->query("SELECT 1");
        } catch (PDOException $e) {
            // Reconnect if connection lost
            $this->connectionAttempts = 0;
            $this->connect();
        }
        return $this->conn;
    }

    // Prevent cloning
    private function __clone() {}

    // Prevent unserialization
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

// ============================================
// Security Helper Functions
// ============================================

/**
 * Sanitize user input
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Validate input length
 */
function validateLength($data, $min = 0, $max = 255) {
    $length = mb_strlen($data, 'UTF-8');
    return ($length >= $min && $length <= $max);
}

/**
 * Validate date format
 */
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * Validate Indonesian plate number format
 */
function validateNomorPolisi($nopol) {
    // Format: B 1234 ABC atau B 1234 A
    $pattern = '/^[A-Z]{1,2}\s?\d{1,4}\s?[A-Z]{1,3}$/i';
    return preg_match($pattern, $nopol);
}

/**
 * Generate CSRF Token
 */
function generateCSRFToken() {
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        $_SESSION[CSRF_TOKEN_NAME . '_time'] = time();
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Verify CSRF Token
 */
function verifyCSRFToken($token) {
    if (!isset($_SESSION[CSRF_TOKEN_NAME]) || !isset($_SESSION[CSRF_TOKEN_NAME . '_time'])) {
        return false;
    }
    
    // Check if token expired
    if (time() - $_SESSION[CSRF_TOKEN_NAME . '_time'] > CSRF_TOKEN_EXPIRY) {
        unset($_SESSION[CSRF_TOKEN_NAME]);
        unset($_SESSION[CSRF_TOKEN_NAME . '_time']);
        return false;
    }
    
    return hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

/**
 * Check IP Whitelist
 */
function checkIPWhitelist() {
    if (!ENABLE_IP_WHITELIST) {
        return true;
    }
    
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? '';
    
    foreach (ALLOWED_IPS as $allowedRange) {
        if (ipInRange($clientIP, $allowedRange)) {
            return true;
        }
    }
    
    // Log unauthorized access attempt
    logAudit(null, 'UNAUTHORIZED_IP', 'System', "Blocked access from IP: {$clientIP}");
    
    http_response_code(403);
    die(json_encode([
        'success' => false,
        'message' => 'Akses ditolak. IP Anda tidak terdaftar.'
    ]));
}

/**
 * Check if IP is in range (CIDR notation support)
 */
function ipInRange($ip, $range) {
    if (strpos($range, '/') === false) {
        return $ip === $range;
    }
    
    list($subnet, $mask) = explode('/', $range);
    $ipLong = ip2long($ip);
    $subnetLong = ip2long($subnet);
    $maskLong = -1 << (32 - (int)$mask);
    
    return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
}

/**
 * Rate Limiting
 */
function checkRateLimit($action, $limit = 10, $window = 60) {
    $key = 'rate_limit_' . $action . '_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'start_time' => time()];
    }
    
    $data = $_SESSION[$key];
    
    // Reset if window expired
    if (time() - $data['start_time'] > $window) {
        $_SESSION[$key] = ['count' => 1, 'start_time' => time()];
        return true;
    }
    
    // Check limit
    if ($data['count'] >= $limit) {
        logAudit(null, 'RATE_LIMIT_EXCEEDED', 'System', "Action: {$action}, IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        
        http_response_code(429);
        die(json_encode([
            'success' => false,
            'message' => 'Terlalu banyak request. Silakan tunggu beberapa saat.'
        ]));
    }
    
    $_SESSION[$key]['count']++;
    return true;
}

/**
 * Audit Logging
 */
function logAudit($formulir_id, $action, $user_name, $description = '') {
    if (!ENABLE_AUDIT_LOG) return;
    
    try {
        $db = Database::getInstance()->getConnection();
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        $stmt = $db->prepare("
            INSERT INTO audit_log (formulir_id, action, user_name, description, ip_address, user_agent) 
            VALUES (:formulir_id, :action, :user_name, :description, :ip_address, :user_agent)
        ");
        
        $stmt->execute([
            ':formulir_id' => $formulir_id,
            ':action' => $action,
            ':user_name' => $user_name,
            ':description' => $description,
            ':ip_address' => $ip_address,
            ':user_agent' => substr($user_agent, 0, 255)
        ]);
    } catch(PDOException $e) {
        error_log("Audit Log Error: " . $e->getMessage());
    }
}

/**
 * JSON Response
 */
function jsonResponse($success, $message, $data = null) {
    header('Content-Type: application/json; charset=utf-8');
    
    $response = [
        'success' => $success,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    // Add CSRF token to response if available
    if (isset($_SESSION[CSRF_TOKEN_NAME])) {
        $response['csrf_token'] = $_SESSION[CSRF_TOKEN_NAME];
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Secure file download
 */
function secureDownload($filepath, $filename) {
    if (!file_exists($filepath)) {
        http_response_code(404);
        die('File not found');
    }
    
    // Prevent directory traversal
    $realpath = realpath($filepath);
    $basepath = realpath(__DIR__);
    
    if (strpos($realpath, $basepath) !== 0) {
        http_response_code(403);
        die('Access denied');
    }
    
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    readfile($filepath);
    exit;
}

// ============================================
// Initialize Security
// ============================================

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    
    // Session hijacking protection
    if (!isset($_SESSION['user_ip'])) {
        $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    } else {
        // Verify session
        if ($_SESSION['user_ip'] !== ($_SERVER['REMOTE_ADDR'] ?? 'unknown') ||
            $_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown')) {
            // Session hijacking detected
            session_destroy();
            session_start();
            
            logAudit(null, 'SESSION_HIJACK_ATTEMPT', 'System', 
                'IP Mismatch: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            
            die(json_encode([
                'success' => false,
                'message' => 'Sesi tidak valid. Silakan login kembali.'
            ]));
        }
    }
    
    // Session timeout check
    if (isset($_SESSION['last_activity']) && 
        (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_destroy();
        session_start();
    }
    
    $_SESSION['last_activity'] = time();
}

// Check IP whitelist
checkIPWhitelist();

// Regenerate session ID periodically
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
} else if (time() - $_SESSION['created'] > 1800) {  // 30 minutes
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}
