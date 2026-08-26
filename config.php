<?php
/**
 * Database Configuration
 * PRIMA (Pertamina Checklist Mobil Tangki)
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');  // Password default XAMPP biasanya kosong
define('DB_NAME', 'u960614929_tnsali'); // Pastikan database ini ada di localhost Anda
define('DB_CHARSET', 'utf8mb4');

// Application Configuration
define('APP_NAME', 'PRIMA (Pertamina Checklist Mobil Tangki)');
define('APP_VERSION', '1.0.0');
define('TIMEZONE', 'Asia/Jakarta');

// Security Configuration
define('SESSION_TIMEOUT', 3600); // 1 hour
define('ENABLE_AUDIT_LOG', true);

// Set timezone
date_default_timezone_set(TIMEZONE);

// Database Connection Class
class Database {
    private $conn;
    private static $instance = null;

    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];
            
            $this->conn = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch(PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            die(json_encode([
                'success' => false,
                'message' => 'Koneksi database gagal. Silakan hubungi administrator.'
            ]));
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->conn;
    }

    // Prevent cloning
    private function __clone() {}

    // Prevent unserialization
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

// Helper Functions
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Auto-create kendaraan table if not exists
function ensureVehicleTableExists() {
    try {
        $db = Database::getInstance()->getConnection();
        
        // Check if table exists
        $stmt = $db->prepare("SHOW TABLES LIKE 'kendaraan'");
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // Table exists, check if email_kontraktor column exists
            $stmt = $db->prepare("SHOW COLUMNS FROM kendaraan LIKE 'email_kontraktor'");
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                // Add email_kontraktor column if not exists
                $db->exec("ALTER TABLE kendaraan ADD COLUMN email_kontraktor varchar(100) AFTER nama_transport");
            }
            
            return true; // Table already exists
        }
        
        // Create table if not exists
        $sql = "
        CREATE TABLE `kendaraan` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `jenis` enum('SPBU','INDUSTRI') NOT NULL DEFAULT 'SPBU',
          `nomor_polisi` varchar(20) NOT NULL UNIQUE,
          `merk_mobil` varchar(100) NOT NULL,
          `tahun_kendaraan` int(4),
          `nama_transport` varchar(100),
          `email_kontraktor` varchar(100),
          `produk_kapasitas` varchar(100),
          `tanggal_pemeriksaan_terakhir` date,
          `ekim_valid_until` date,
          `status` enum('AKTIF','TIDAK_AKTIF') DEFAULT 'AKTIF',
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          `created_by` int(11),
          PRIMARY KEY (`id`),
          UNIQUE KEY `unique_nomor_polisi` (`nomor_polisi`),
          KEY `idx_jenis` (`jenis`),
          KEY `idx_status` (`status`),
          KEY `idx_created_by` (`created_by`),
          CONSTRAINT `kendaraan_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        
        $db->exec($sql);
        return true;
        
    } catch(Exception $e) {
        error_log("Vehicle Table Creation Error: " . $e->getMessage());
        return false;
    }
}

// Auto-create tanggal_expire column on checklist_items if not exists
// (menyimpan tanggal masa berlaku habis untuk STNK/Pajak/SIMFIT/Tera/Keur
// yang diisi langsung pada form checklist).
function ensureChecklistItemsExpiryColumnExists() {
    try {
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare("SHOW COLUMNS FROM checklist_items LIKE 'tanggal_expire'");
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            $db->exec("ALTER TABLE checklist_items ADD COLUMN tanggal_expire DATE NULL AFTER keterangan");
        }

        return true;
    } catch(Exception $e) {
        error_log("ensureChecklistItemsExpiryColumnExists Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Nama item checklist (kolom item_name di tabel checklist_items) yang merupakan
 * dokumen kendaraan dengan masa berlaku (dilengkapi date picker "Tgl Masa Berlaku Habis").
 * SIMFIT (Industri) hanya relevan untuk kendaraan jenis INDUSTRI.
 */
function getDocumentExpiryItemNames(): array {
    return [
        'STNK',
        'PAJAK',
        'SIMFIT (Industri)',
        'Surat Tera Metrologi',
        'Surat Keur DLLAAJR',
    ];
}

/** Label ramah-pengguna untuk tiap item_name dokumen (dipakai di email & badge). */
function getDocumentExpiryItemLabels(): array {
    return [
        'STNK'                 => 'STNK',
        'PAJAK'                => 'Pajak Kendaraan',
        'SIMFIT (Industri)'    => 'SIMFIT (Industri)',
        'Surat Tera Metrologi' => 'Surat Tera Metrologi',
        'Surat Keur DLLAAJR'   => 'Surat Keur DLLAAJR',
    ];
}

/**
 * Ambil daftar dokumen (STNK/Pajak/SIMFIT/Tera/Keur) yang sudah kadaluarsa atau
 * akan kadaluarsa dalam $days_threshold hari, diambil dari tanggal_expire pada
 * checklist_items milik formulir_checklist TERBARU tiap kendaraan (nomor_polisi).
 * SIMFIT (Industri) dikecualikan untuk kendaraan yang bukan jenis INDUSTRI.
 */
function getDocumentExpiryAlerts($days_threshold = 3) {
    try {
        $db = Database::getInstance()->getConnection();
        ensureChecklistItemsExpiryColumnExists();

        $today          = date('Y-m-d');
        $threshold_date = date('Y-m-d', strtotime("+$days_threshold days"));

        $item_names = getDocumentExpiryItemNames();
        $placeholders = implode(',', array_fill(0, count($item_names), '?'));

        $sql = "
            SELECT
                fc.id                                     AS formulir_id,
                fc.nomor_polisi,
                fc.merk_mobil,
                fc.nama_transport,
                fc.jenis_kendaraan,
                k.email_kontraktor,
                ci.item_name,
                ci.tanggal_expire,
                DATEDIFF(ci.tanggal_expire, ?)            AS hari_tersisa,
                CASE
                    WHEN ci.tanggal_expire < ?             THEN 'SUDAH_EXPIRED'
                    WHEN ci.tanggal_expire <= ?            THEN 'PERLU_PERHATIAN'
                    ELSE 'NORMAL'
                END AS status_alert
            FROM formulir_checklist fc
            INNER JOIN (
                SELECT nomor_polisi, MAX(id) AS latest_id
                FROM formulir_checklist
                GROUP BY nomor_polisi
            ) latest ON fc.nomor_polisi = latest.nomor_polisi AND fc.id = latest.latest_id
            INNER JOIN checklist_items ci ON ci.formulir_id = fc.id
            LEFT JOIN kendaraan k ON k.nomor_polisi = fc.nomor_polisi
            WHERE ci.item_name IN ($placeholders)
              AND ci.tanggal_expire IS NOT NULL
              AND ci.tanggal_expire != '0000-00-00'
              AND ci.tanggal_expire <= ?
              AND (ci.item_name != 'SIMFIT (Industri)' OR UPPER(fc.jenis_kendaraan) = 'INDUSTRI')
            ORDER BY ci.tanggal_expire ASC
        ";

        $stmt = $db->prepare($sql);
        $params = array_merge([$today, $today, $threshold_date], $item_names, [$threshold_date]);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch(Exception $e) {
        error_log("Get Document Expiry Alerts Error: " . $e->getMessage());
        return [];
    }
}

/**
 * Label tampilan untuk jenis kendaraan mobil tangki.
 * Nilai internal/enum tetap 'SPBU' & 'INDUSTRI' (dipakai di database, filter, dan kode),
 * dan juga ditampilkan apa adanya ke pengguna: "SPBU" & "Industri".
 */
function getJenisKendaraanLabel(?string $jenis): string {
    $jenis = strtoupper(trim((string)$jenis));
    if ($jenis === 'SPBU') return 'SPBU';
    if ($jenis === 'INDUSTRI') return 'Industri';
    return $jenis !== '' ? $jenis : '-';
}

// Get vehicle inspection alerts (vehicles that need inspection within 30 days)
function getVehicleAlerts($days_threshold = 30) {
    try {
        $db = Database::getInstance()->getConnection();
        
        $today          = date('Y-m-d');
        $threshold_date = date('Y-m-d', strtotime("+$days_threshold days"));
        
        // Query from formulir_checklist (same source as list.php).
        // Per nomor_polisi, pick the row with the latest tanggal_pemeriksaan
        // (or latest created_at as fallback), then filter by ekim_valid_until.
        $stmt = $db->prepare("
            SELECT
                fc.id,
                fc.nomor_polisi,
                fc.merk_mobil,
                fc.nama_transport,
                k.email_kontraktor,
                fc.jenis_kendaraan                        AS jenis,
                fc.ekim_valid_until,
                DATEDIFF(fc.ekim_valid_until, :today1)    AS hari_tersisa,
                CASE
                    WHEN fc.ekim_valid_until < :today2     THEN 'SUDAH_EXPIRED'
                    WHEN fc.ekim_valid_until <= :threshold1 THEN 'PERLU_INSPEKSI'
                    ELSE 'NORMAL'
                END AS status_alert
            FROM formulir_checklist fc
            LEFT JOIN kendaraan k ON k.nomor_polisi = fc.nomor_polisi
            INNER JOIN (
                SELECT nomor_polisi,
                       MAX(COALESCE(tanggal_pemeriksaan, created_at)) AS latest_tgl
                FROM   formulir_checklist
                WHERE  ekim_valid_until IS NOT NULL
                  AND  ekim_valid_until != '0000-00-00'
                GROUP  BY nomor_polisi
            ) latest
              ON  fc.nomor_polisi = latest.nomor_polisi
              AND COALESCE(fc.tanggal_pemeriksaan, fc.created_at) = latest.latest_tgl
            WHERE fc.ekim_valid_until IS NOT NULL
              AND fc.ekim_valid_until != '0000-00-00'
              AND fc.ekim_valid_until <= :threshold2
            ORDER BY fc.ekim_valid_until ASC
        ");
        
        $stmt->execute([
            ':today1'     => $today,
            ':today2'     => $today,
            ':threshold1' => $threshold_date,
            ':threshold2' => $threshold_date,
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch(Exception $e) {
        error_log("Get Vehicle Alerts Error: " . $e->getMessage());
        return [];
    }
}

function logAudit($formulir_id, $action, $user_name, $description = '') {
    if (!ENABLE_AUDIT_LOG) return;
    
    try {
        $db = Database::getInstance()->getConnection();
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        
        $stmt = $db->prepare("
            INSERT INTO audit_log (formulir_id, action, user_name, description, ip_address) 
            VALUES (:formulir_id, :action, :user_name, :description, :ip_address)
        "); 
        
        $stmt->execute([
            ':formulir_id' => $formulir_id,
            ':action' => $action,
            ':user_name' => $user_name,
            ':description' => $description,
            ':ip_address' => $ip_address
        ]);
    } catch(PDOException $e) {
        error_log("Audit Log Error: " . $e->getMessage());
    }
}

function jsonResponse($success, $message, $data = null, $httpCode = null) {
    if ($httpCode === null) {
        // Sensible default: failed responses that aren't explicitly coded
        // still return 200 for backward compatibility, UNLESS the caller
        // passes an explicit code (e.g. 403 for authorization failures).
        $httpCode = 200;
    }
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    $response = [
        'success' => $success,
        'message' => $message
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── NOTIFICATION TABLES ──────────────────────────────────────────────────────

function ensureNotifTablesExist() {
    try {
        $db = Database::getInstance()->getConnection();

        // system_settings (key-value store for SMTP config etc.)
        $db->exec("
            CREATE TABLE IF NOT EXISTS `system_settings` (
              `setting_key`   varchar(100) NOT NULL,
              `setting_value` text,
              `updated_at`    timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
              PRIMARY KEY (`setting_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // kim_notifications (log of sent notification emails)
        $db->exec("
            CREATE TABLE IF NOT EXISTS `kim_notifications` (
              `id`              int(11) NOT NULL AUTO_INCREMENT,
              `nomor_polisi`    varchar(20) NOT NULL,
              `nama_transport`  varchar(100),
              `email_to`        varchar(255) NOT NULL,
              `ekim_valid_until` date,
              `hari_tersisa`    int(11),
              `status`          varchar(20) DEFAULT 'sent',
              `error_message`   text,
              `sent_by`         int(11),
              `sent_at`         datetime NOT NULL DEFAULT current_timestamp(),
              PRIMARY KEY (`id`),
              KEY `idx_nomor_polisi` (`nomor_polisi`),
              KEY `idx_sent_at` (`sent_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // dokumen_expire_notifications (log of sent STNK/Pajak/SIMFIT/Tera/Keur expiry emails)
        $db->exec("
            CREATE TABLE IF NOT EXISTS `dokumen_expire_notifications` (
              `id`              int(11) NOT NULL AUTO_INCREMENT,
              `nomor_polisi`    varchar(20) NOT NULL,
              `item_name`       varchar(100) NOT NULL,
              `nama_transport`  varchar(100),
              `email_to`        varchar(255) NOT NULL,
              `tanggal_expire`  date,
              `hari_tersisa`    int(11),
              `status`          varchar(20) DEFAULT 'sent',
              `error_message`   text,
              `sent_at`         datetime NOT NULL DEFAULT current_timestamp(),
              PRIMARY KEY (`id`),
              KEY `idx_nomor_polisi_item` (`nomor_polisi`, `item_name`),
              KEY `idx_sent_at` (`sent_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch(Exception $e) {
        error_log("ensureNotifTablesExist Error: " . $e->getMessage());
    }
}

function logDocExpireNotification($nomor_polisi, $item_name, $nama_transport, $email_to, $tanggal_expire, $hari_tersisa, $status, $error_msg) {
    try {
        $db = Database::getInstance()->getConnection();
        ensureNotifTablesExist();
        $stmt = $db->prepare("INSERT INTO dokumen_expire_notifications (nomor_polisi, item_name, nama_transport, email_to, tanggal_expire, hari_tersisa, status, error_message) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([$nomor_polisi, $item_name, $nama_transport, $email_to, $tanggal_expire, $hari_tersisa, $status, $error_msg]);
        return true;
    } catch(Exception $e) {
        error_log("logDocExpireNotification Error: " . $e->getMessage());
        return false;
    }
}

function wasRecentlyNotifiedDocExpiry($nomor_polisi, $item_name, $days = 7) {
    try {
        $db = Database::getInstance()->getConnection();
        ensureNotifTablesExist();
        $stmt = $db->prepare("SELECT COUNT(*) FROM dokumen_expire_notifications WHERE nomor_polisi = ? AND item_name = ? AND status = 'sent' AND sent_at >= DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([$nomor_polisi, $item_name, $days]);
        return (int)$stmt->fetchColumn() > 0;
    } catch(Exception $e) {
        return false;
    }
}

function getSmtpConfig() {
    try {
        $db = Database::getInstance()->getConnection();
        ensureNotifTablesExist();
        $keys = ['smtp_host','smtp_port','smtp_encryption','smtp_username','smtp_password','smtp_from_email','smtp_from_name','notif_days_threshold'];
        $stmt = $db->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN (" . implode(',', array_fill(0, count($keys), '?')) . ")");
        $stmt->execute($keys);
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        return array_merge([
            'smtp_host'           => 'smtp.gmail.com',
            'smtp_port'           => '587',
            'smtp_encryption'     => 'tls',
            'smtp_username'       => '',
            'smtp_password'       => '',
            'smtp_from_email'     => '',
            'smtp_from_name'      => 'PRIMA KIM — PT Pertamina Patra Niaga',
            'notif_days_threshold'=> '30',
        ], $rows);
    } catch(Exception $e) {
        error_log("getSmtpConfig Error: " . $e->getMessage());
        return [];
    }
}

function saveSmtpConfig($cfg) {
    try {
        $db = Database::getInstance()->getConnection();
        ensureNotifTablesExist();
        $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        foreach ($cfg as $k => $v) {
            $stmt->execute([$k, $v]);
        }
        return true;
    } catch(Exception $e) {
        error_log("saveSmtpConfig Error: " . $e->getMessage());
        return false;
    }
}

function logKimNotification($nomor_polisi, $nama_transport, $email_to, $ekim_valid, $hari_tersisa, $status, $error_msg, $sent_by) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("INSERT INTO kim_notifications (nomor_polisi, nama_transport, email_to, ekim_valid_until, hari_tersisa, status, error_message, sent_by) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([$nomor_polisi, $nama_transport, $email_to, $ekim_valid, $hari_tersisa, $status, $error_msg, $sent_by]);
        return true;
    } catch(Exception $e) {
        error_log("logKimNotification Error: " . $e->getMessage());
        return false;
    }
}

function wasRecentlyNotified($nomor_polisi, $days = 7) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT COUNT(*) FROM kim_notifications WHERE nomor_polisi = ? AND status = 'sent' AND sent_at >= DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([$nomor_polisi, $days]);
        return (int)$stmt->fetchColumn() > 0;
    } catch(Exception $e) {
        return false;
    }
}

function getNotifHistory($limit = 30) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT n.*, u.full_name as sender_name FROM kim_notifications n LEFT JOIN users u ON n.sent_by = u.id ORDER BY n.sent_at DESC LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(Exception $e) {
        return [];
    }
}

/** Ambil satu nilai dari system_settings (key-value store), dengan nilai default jika belum ada. */
function getSystemSetting(string $key, $default = null) {
    try {
        $db = Database::getInstance()->getConnection();
        ensureNotifTablesExist();
        $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return $val !== false ? $val : $default;
    } catch(Exception $e) {
        return $default;
    }
}

/** Simpan satu nilai ke system_settings (key-value store). */
function setSystemSetting(string $key, $value): bool {
    try {
        $db = Database::getInstance()->getConnection();
        ensureNotifTablesExist();
        $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        return $stmt->execute([$key, $value]);
    } catch(Exception $e) {
        error_log("setSystemSetting Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Secret key untuk memicu notifikasi KIM otomatis lewat URL (cron hosting via curl/wget),
 * tanpa perlu login admin. Dibuat otomatis sekali dan disimpan permanen di system_settings.
 */
function getCronSecret(): string {
    $secret = getSystemSetting('cron_secret');
    if (!empty($secret)) return $secret;
    return regenerateCronSecret();
}

/** Buat ulang secret key cron — otomatis menonaktifkan URL cron lama yang mungkin bocor. */
function regenerateCronSecret(): string {
    $secret = bin2hex(random_bytes(24));
    setSystemSetting('cron_secret', $secret);
    return $secret;
}

// ── PENGURUS ROLE MIGRATION ──────────────────────────────────────────────────
function ensurePengurusTablesExist() {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $db = Database::getInstance()->getConnection();

        // BUG FIX: this used to re-narrow the enum to ('admin','user','pengurus') on
        // every request (called from home.php on every page load), silently wiping
        // out any 'hsse'/'manager_hsse' role values to '' because they were no longer
        // valid enum members. Must always include the FULL role set here.
        $db->exec("ALTER TABLE users MODIFY COLUMN role ENUM('admin','user','pengurus','hsse','manager_hsse') NOT NULL DEFAULT 'user'");
    } catch(Exception $e) { /* already modified */ }

    try {
        $db = Database::getInstance()->getConnection();
        // Add requested_role column to user_registrations if missing
        $db->exec("ALTER TABLE user_registrations ADD COLUMN requested_role ENUM('user','pengurus','hsse','manager_hsse') NOT NULL DEFAULT 'user'");
    } catch(Exception $e) { /* already exists */ }

    try {
        $db = Database::getInstance()->getConnection();
        // BUG FIX: if the column already existed with the old narrow enum
        // ('user','pengurus'), the ADD COLUMN above throws (caught above) and never
        // widens it. Explicitly (re)widen it every time so 'hsse'/'manager_hsse'
        // requests are never silently corrupted to ''.
        $db->exec("ALTER TABLE user_registrations MODIFY COLUMN requested_role ENUM('user','pengurus','hsse','manager_hsse') NOT NULL DEFAULT 'user'");
    } catch(Exception $e) { /* already modified */ }

    try {
        $db = Database::getInstance()->getConnection();
        // Table: dokumen_kendaraan — stores uploaded document files
        $db->exec("CREATE TABLE IF NOT EXISTS dokumen_kendaraan (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            nomor_polisi  VARCHAR(20) NOT NULL,
            nama_transport VARCHAR(100) DEFAULT NULL,
            jenis_dokumen ENUM('STNK','PAJAK','SIM','SURAT_KEUR','SURAT_TERA','KIM','LAINNYA') NOT NULL,
            nama_file_asli VARCHAR(255) DEFAULT NULL,
            file_path     VARCHAR(500) DEFAULT NULL,
            tanggal_berlaku DATE DEFAULT NULL,
            keterangan    TEXT DEFAULT NULL,
            status        ENUM('PENDING','DISETUJUI','DITOLAK') NOT NULL DEFAULT 'PENDING',
            catatan_admin TEXT DEFAULT NULL,
            uploaded_by   INT DEFAULT NULL,
            reviewed_by   INT DEFAULT NULL,
            reviewed_at   TIMESTAMP NULL DEFAULT NULL,
            created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_nomor_polisi (nomor_polisi),
            KEY idx_uploaded_by (uploaded_by),
            CONSTRAINT fk_dk_uploaded FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // BUG FIX: if the table already existed with 'ASURANSI' in the enum (old version),
        // the CREATE TABLE IF NOT EXISTS above is a no-op. Explicitly narrow it here so
        // 'Asuransi' can no longer be selected/stored since this system doesn't use it.
        try {
            $db->exec("ALTER TABLE dokumen_kendaraan MODIFY COLUMN jenis_dokumen ENUM('STNK','PAJAK','SIM','SURAT_KEUR','SURAT_TERA','KIM','LAINNYA') NOT NULL");
        } catch (Exception $e) { /* ada data lama ber-jenis ASURANSI, biarkan enum lama agar tidak error */ }

        // Table: pengurus_kendaraan — maps pengurus users to their vehicles
        $db->exec("CREATE TABLE IF NOT EXISTS pengurus_kendaraan (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            user_id      INT NOT NULL,
            nomor_polisi VARCHAR(20) NOT NULL,
            nama_transport VARCHAR(100) DEFAULT NULL,
            created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_pk (user_id, nomor_polisi),
            CONSTRAINT fk_pk_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch(Exception $e) {
        error_log("ensurePengurusTablesExist error: " . $e->getMessage());
    }
}

// Helpers for pengurus feature
function getPengurusVehicles($user_id) {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT pk.nomor_polisi, pk.nama_transport,
                COUNT(dk.id)                                                    AS total_dokumen,
                SUM(CASE WHEN dk.status = 'PENDING'   THEN 1 ELSE 0 END)       AS pending_dokumen,
                SUM(CASE WHEN dk.status = 'DISETUJUI' THEN 1 ELSE 0 END)       AS disetujui_dokumen,
                SUM(CASE WHEN dk.status = 'DITOLAK'   THEN 1 ELSE 0 END)       AS ditolak_dokumen,
                MAX(dk.created_at)                                              AS last_upload
            FROM pengurus_kendaraan pk
            LEFT JOIN dokumen_kendaraan dk ON dk.nomor_polisi = pk.nomor_polisi AND dk.uploaded_by = :uid
            WHERE pk.user_id = :uid2
            GROUP BY pk.nomor_polisi, pk.nama_transport
        ");
        $stmt->execute([':uid' => $user_id, ':uid2' => $user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(Exception $e) {
        return [];
    }
}

function getDokumenKendaraan($nomor_polisi, $uploaded_by = null) {
    try {
        $db = Database::getInstance()->getConnection();
        $where = 'WHERE dk.nomor_polisi = :nopol';
        $params = [':nopol' => $nomor_polisi];
        if ($uploaded_by !== null) {
            $where .= ' AND dk.uploaded_by = :uid';
            $params[':uid'] = $uploaded_by;
        }
        $stmt = $db->prepare("
            SELECT dk.*, u.full_name as uploader_name, r.full_name as reviewer_name
            FROM dokumen_kendaraan dk
            LEFT JOIN users u ON dk.uploaded_by = u.id
            LEFT JOIN users r ON dk.reviewed_by = r.id
            $where
            ORDER BY dk.jenis_dokumen, dk.created_at DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(Exception $e) {
        return [];
    }
}

/**
 * Daftar akun pengurus/transportir aktif untuk dropdown "Username Akun
 * Transportir" pada pendaftaran kendaraan (register-vehicle.php /
 * kelola-kendaraan.php).
 */
function getPengurusUsersList(): array {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->query("
            SELECT id, username, full_name FROM users
            WHERE role = 'pengurus' AND status = 'active'
            ORDER BY full_name ASC, username ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Hubungkan sebuah kendaraan (nomor polisi) ke akun pengurus/transportir
 * pemiliknya, agar notifikasi status EKIM kendaraan tersebut (diterbitkan /
 * tidak dapat diterbitkan) tampil di dashboard (POV) akun transportir itu.
 * Dipanggil saat pendaftaran/edit kendaraan di register-vehicle.php dan
 * kelola-kendaraan.php.
 */
function linkVehicleToTransportir(string $nomor_polisi, int $user_id, ?string $nama_transport = null): void {
    try {
        ensurePengurusTablesExist();
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            INSERT INTO pengurus_kendaraan (user_id, nomor_polisi, nama_transport)
            VALUES (:uid, :nopol, :nt)
            ON DUPLICATE KEY UPDATE nama_transport = VALUES(nama_transport)
        ");
        $stmt->execute([':uid' => $user_id, ':nopol' => strtoupper($nomor_polisi), ':nt' => $nama_transport ?: null]);
    } catch (Exception $e) {
        error_log("linkVehicleToTransportir error: " . $e->getMessage());
    }
}

/** Cari username akun transportir yang saat ini terhubung ke sebuah nomor polisi (jika ada). */
function getTransportirUsernameForVehicle(string $nomor_polisi): ?string {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT u.username FROM pengurus_kendaraan pk
            JOIN users u ON u.id = pk.user_id
            WHERE pk.nomor_polisi = ?
            ORDER BY pk.created_at DESC LIMIT 1
        ");
        $stmt->execute([strtoupper($nomor_polisi)]);
        $username = $stmt->fetchColumn();
        return $username !== false ? $username : null;
    } catch (Exception $e) {
        return null;
    }
}

// ── NOTIFIKASI EKIM UNTUK AKUN TRANSPORTIR ──────────────────────────────────
/**
 * Buat tabel ekim_notifikasi jika belum ada. Menyimpan notifikasi status
 * penerbitan EKIM (diterbitkan / diblokir / ditolak) yang ditujukan ke akun
 * pengurus/transportir pemilik kendaraan (lihat pengurus_kendaraan), agar
 * mereka melihatnya di dashboard sendiri (POV transportir) tanpa harus buka
 * formulir checklist secara langsung.
 */
function ensureEkimNotifikasiTableExists(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $db = Database::getInstance()->getConnection();
        $db->exec("CREATE TABLE IF NOT EXISTS ekim_notifikasi (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            user_id      INT NOT NULL,
            formulir_id  INT DEFAULT NULL,
            nomor_polisi VARCHAR(20) NOT NULL,
            status       ENUM('issued','blocked','rejected') NOT NULL,
            pesan        TEXT NOT NULL,
            is_read      TINYINT(1) NOT NULL DEFAULT 0,
            created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_en_user (user_id),
            KEY idx_en_formulir (formulir_id),
            CONSTRAINT fk_en_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_en_formulir FOREIGN KEY (formulir_id) REFERENCES formulir_checklist(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Exception $e) {
        error_log("ensureEkimNotifikasiTableExists error: " . $e->getMessage());
    }
}

/**
 * Kirim notifikasi status EKIM (issued/blocked/rejected) ke seluruh akun
 * transportir yang terhubung ke nomor polisi tersebut lewat pengurus_kendaraan.
 * Tidak melempar error jika kendaraan belum terhubung ke akun manapun — cukup
 * dilewati (best-effort, tidak boleh menggagalkan proses tanda tangan).
 */
function createEkimNotifikasi(string $nomor_polisi, ?int $formulir_id, string $status, string $pesan): void {
    if (!in_array($status, ['issued', 'blocked', 'rejected'], true)) return;
    try {
        ensureEkimNotifikasiTableExists();
        $db = Database::getInstance()->getConnection();
        $owners = $db->prepare("SELECT DISTINCT user_id FROM pengurus_kendaraan WHERE nomor_polisi = ?");
        $owners->execute([strtoupper($nomor_polisi)]);
        $userIds = $owners->fetchAll(PDO::FETCH_COLUMN);
        if (empty($userIds)) return;

        $stmt = $db->prepare("
            INSERT INTO ekim_notifikasi (user_id, formulir_id, nomor_polisi, status, pesan)
            VALUES (:uid, :fid, :nopol, :status, :pesan)
        ");
        foreach ($userIds as $uid) {
            $stmt->execute([
                ':uid'    => $uid,
                ':fid'    => $formulir_id,
                ':nopol'  => strtoupper($nomor_polisi),
                ':status' => $status,
                ':pesan'  => $pesan,
            ]);
        }
    } catch (Exception $e) {
        error_log("createEkimNotifikasi error: " . $e->getMessage());
    }
}

/**
 * Ambil notifikasi EKIM milik sebuah akun transportir untuk ditampilkan di
 * dashboard (home.php). Notifikasi yang belum dibaca ditandai lewat kunci
 * 'is_new' pada hasil SEBELUM ditandai sudah dibaca di database.
 */
function getEkimNotifikasiForUser(int $user_id, int $limit = 20): array {
    try {
        ensureEkimNotifikasiTableExists();
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT * FROM ekim_notifikasi
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT " . (int)$limit
        );
        $stmt->execute([$user_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['is_new'] = !$row['is_read'];
        }
        unset($row);

        $db->prepare("UPDATE ekim_notifikasi SET is_read = 1 WHERE user_id = ? AND is_read = 0")
           ->execute([$user_id]);

        return $rows;
    } catch (Exception $e) {
        return [];
    }
}

/** Label jenis dokumen (surat) — dipakai di halaman upload, admin, dan email notifikasi. */
function getJenisDokumenLabels(): array {
    return [
        'STNK'       => 'STNK (Surat Tanda Nomor Kendaraan)',
        'PAJAK'      => 'Pajak Kendaraan',
        'SIM'        => 'SIM Pengemudi (SIMFIT)',
        'SURAT_KEUR' => 'Surat Keur DLLAAJR',
        'SURAT_TERA' => 'Surat Tera Metrologi',
        'KIM'        => 'KIM (Kartu Izin Masuk)',
        'LAINNYA'    => 'Dokumen Lainnya',
    ];
}

/**
 * Mengecek status surat/dokumen kendaraan (STNK, Pajak, SIM, dst, + KIM) untuk satu nomor
 * polisi dan mengembalikan daftar surat yang BERMASALAH: sudah kadaluarsa atau akan
 * kadaluarsa dalam $days_threshold hari ke depan. Sebuah kendaraan yang sudah pernah
 * di-checklist dianggap sudah punya semua suratnya — jadi status diambil dari tanggal
 * masa berlaku (tanggal_expire) yang diisi pada checklist_items formulir TERBARU
 * kendaraan tsb (untuk KIM diambil dari formulir_checklist.ekim_valid_until).
 * Surat yang belum diisi tanggal masa berlakunya TIDAK dianggap bermasalah.
 *
 * @return array List of ['jenis' => string, 'label' => string, 'reason' => string, 'tanggal_berlaku' => ?string]
 */
function getSuratBermasalah(string $nomor_polisi, int $days_threshold = 3): array {
    $labels = getJenisDokumenLabels();
    // Pemetaan kode jenis surat (sama seperti getJenisDokumenLabels) ke item_name pada checklist_items
    $item_name_map = [
        'STNK'       => 'STNK',
        'PAJAK'      => 'PAJAK',
        'SIM'        => 'SIMFIT (Industri)',
        'SURAT_KEUR' => 'Surat Keur DLLAAJR',
        'SURAT_TERA' => 'Surat Tera Metrologi',
    ];
    $hasil = [];

    try {
        $db = Database::getInstance()->getConnection();
        ensureChecklistItemsExpiryColumnExists();

        // Formulir checklist TERBARU untuk kendaraan ini
        $stmt = $db->prepare("
            SELECT id, jenis_kendaraan, ekim_valid_until
            FROM formulir_checklist
            WHERE nomor_polisi = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute([$nomor_polisi]);
        $formulir = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$formulir) return $hasil;

        $today          = date('Y-m-d');
        $threshold_date = date('Y-m-d', strtotime("+{$days_threshold} days"));
        $jenis_kendaraan = strtoupper((string)($formulir['jenis_kendaraan'] ?? ''));

        // KIM — dari kolom ekim_valid_until formulir_checklist
        if (!empty($formulir['ekim_valid_until']) && $formulir['ekim_valid_until'] !== '0000-00-00') {
            if ($formulir['ekim_valid_until'] < $today) {
                $hasil[] = [
                    'jenis'           => 'KIM',
                    'label'           => $labels['KIM'],
                    'reason'          => 'Sudah kadaluarsa sejak ' . date('d/m/Y', strtotime($formulir['ekim_valid_until'])),
                    'tanggal_berlaku' => $formulir['ekim_valid_until'],
                ];
            } elseif ($formulir['ekim_valid_until'] <= $threshold_date) {
                $sisa = (int)round((strtotime($formulir['ekim_valid_until']) - strtotime($today)) / 86400);
                $hasil[] = [
                    'jenis'           => 'KIM',
                    'label'           => $labels['KIM'],
                    'reason'          => "Akan habis dalam {$sisa} hari (" . date('d/m/Y', strtotime($formulir['ekim_valid_until'])) . ')',
                    'tanggal_berlaku' => $formulir['ekim_valid_until'],
                ];
            }
        }

        // Surat lain — dari checklist_items.tanggal_expire pada formulir terbaru
        $item_names   = array_values($item_name_map);
        $placeholders = implode(',', array_fill(0, count($item_names), '?'));
        $stmt2 = $db->prepare("
            SELECT item_name, tanggal_expire
            FROM checklist_items
            WHERE formulir_id = ? AND item_name IN ($placeholders)
        ");
        $stmt2->execute(array_merge([$formulir['id']], $item_names));
        $by_item = [];
        foreach ($stmt2->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $by_item[$r['item_name']] = $r['tanggal_expire'];
        }

        foreach ($item_name_map as $jenis => $item_name) {
            // SIMFIT hanya relevan untuk kendaraan jenis INDUSTRI
            if ($jenis === 'SIM' && $jenis_kendaraan !== 'INDUSTRI') continue;

            $tgl = $by_item[$item_name] ?? null;
            if (empty($tgl) || $tgl === '0000-00-00') continue; // belum diisi -> tidak dianggap bermasalah

            $label = $labels[$jenis] ?? $item_name;
            if ($tgl < $today) {
                $hasil[] = [
                    'jenis'           => $jenis,
                    'label'           => $label,
                    'reason'          => 'Sudah kadaluarsa sejak ' . date('d/m/Y', strtotime($tgl)),
                    'tanggal_berlaku' => $tgl,
                ];
            } elseif ($tgl <= $threshold_date) {
                $sisa = (int)round((strtotime($tgl) - strtotime($today)) / 86400);
                $hasil[] = [
                    'jenis'           => $jenis,
                    'label'           => $label,
                    'reason'          => "Akan habis dalam {$sisa} hari (" . date('d/m/Y', strtotime($tgl)) . ')',
                    'tanggal_berlaku' => $tgl,
                ];
            }
        }
    } catch (Exception $e) {
        error_log("getSuratBermasalah error: " . $e->getMessage());
    }

    return $hasil;
}

// ═══════════════════════════════════════════════════════════════
// DIGITAL SIGNATURE — RSA-2048 / SHA-256
// ═══════════════════════════════════════════════════════════════

define('RSA_KEY_DIR', __DIR__ . '/rsa-keys/');

/**
 * Menghitung SHA-256 hash kanonik dari seluruh isi dokumen formulir.
 * Hash ini yang akan ditandatangani secara digital.
 * Perubahan data apapun akan menghasilkan hash yang berbeda.
 */
function computeDocumentHash(int $formulir_id, $db, string $algorithm = 'sha256'): ?string {
    if (!in_array($algorithm, hash_algos(), true)) return null;

    $stmt = $db->prepare("SELECT * FROM formulir_checklist WHERE id = ? LIMIT 1");
    $stmt->execute([$formulir_id]);
    $f = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$f) return null;

    $stmt2 = $db->prepare("
        SELECT item_number, item_name, is_baik, is_tidak, keterangan
        FROM checklist_items
        WHERE formulir_id = ?
        ORDER BY item_number ASC
    ");
    $stmt2->execute([$formulir_id]);
    $items = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    $canonical = [
        'formulir_id'         => (int)$f['id'],
        'jenis_kendaraan'     => (string)($f['jenis_kendaraan']     ?? ''),
        'nomor_urut'          => (string)($f['nomor_urut']          ?? ''),
        'merk_mobil'          => (string)($f['merk_mobil']          ?? ''),
        'nama_transport'      => (string)($f['nama_transport']      ?? ''),
        'nomor_polisi'        => (string)($f['nomor_polisi']        ?? ''),
        'tanggal_terakhir'    => (string)($f['tanggal_terakhir']    ?? ''),
        'produk_kapasitas'    => (string)($f['produk_kapasitas']    ?? ''),
        'tanggal_pemeriksaan' => (string)($f['tanggal_pemeriksaan'] ?? ''),
        'ekim_valid_until'    => (string)($f['ekim_valid_until']    ?? ''),
        'status_gate'         => (string)($f['status_gate']         ?? ''),
        'status_upload'       => (string)($f['status_upload']       ?? ''),
        'nama_pemeriksa'      => (string)($f['nama_pemeriksa']      ?? ''),
        'tanggal_pemeriksa'   => (string)($f['tanggal_pemeriksa']   ?? ''),
        'created_by'          => (int)($f['created_by']             ?? 0),
        'created_at'          => (string)($f['created_at']          ?? ''),
        'checklist_items'     => array_map(fn($i) => [
            'no'    => (int)$i['item_number'],
            'nama'  => (string)$i['item_name'],
            'baik'  => (int)$i['is_baik'],
            'tidak' => (int)$i['is_tidak'],
            'ket'   => (string)($i['keterangan'] ?? ''),
        ], $items),
    ];

    return hash($algorithm,
        json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION)
    );
}

/** Membuat token unik 32-byte (64 hex karakter) untuk QR Code. */
function generateQrToken(): string {
    return bin2hex(random_bytes(32));
}

/** Membuat UUID v4 unik sebagai identitas URL verifikasi publik. */
function generateVerificationUuid(): string {
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

/**
 * Buat PNG QR Code untuk URL verifikasi lalu simpan di storage publik.
 * @return string|null Path relatif database, misalnya qrcode/{uuid}.png.
 */
function generateVerificationQrPng(string $uuid, string $verification_url): ?string {
    if (!preg_match('/^[0-9a-f-]{36}$/i', $uuid) || $verification_url === '') {
        return null;
    }

    $storage_dir = __DIR__ . '/storage/app/public/qrcode';
    if (!is_dir($storage_dir) && !mkdir($storage_dir, 0755, true) && !is_dir($storage_dir)) {
        error_log('QR Code: gagal membuat direktori storage: ' . $storage_dir);
        return null;
    }

    $filename = strtolower($uuid) . '.png';
    $absolute_path = $storage_dir . '/' . $filename;
    $request_url = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&format=png&data='
        . rawurlencode($verification_url);
    $context = stream_context_create(['http' => ['timeout' => 20], 'https' => ['timeout' => 20]]);
    $png = @file_get_contents($request_url, false, $context);

    if ($png === false || substr($png, 0, 8) !== "\x89PNG\r\n\x1a\n") {
        error_log('QR Code: generator tidak mengembalikan PNG yang valid');
        return null;
    }

    $temporary_path = $absolute_path . '.tmp';
    if (file_put_contents($temporary_path, $png, LOCK_EX) === false || !rename($temporary_path, $absolute_path)) {
        @unlink($temporary_path);
        error_log('QR Code: gagal menyimpan file: ' . $absolute_path);
        return null;
    }

    return 'qrcode/' . $filename;
}

/** Konversi path QR relatif database menjadi URL yang dapat ditampilkan browser. */
function getVerificationQrPublicUrl(?string $qrcode_path): ?string {
    if (!$qrcode_path || !preg_match('#^qrcode/[0-9a-f-]{36}\.png$#i', $qrcode_path)) {
        return null;
    }
    return getAppBaseUrl() . 'storage/app/public/' . $qrcode_path;
}

/**
 * Buat QR Code verifikasi AWAL (preliminary) segera setelah TTD HSSE dibubuhkan,
 * supaya QR sudah bisa langsung ditampilkan/discan meskipun dokumen belum
 * disetujui Manajer. Hash SHA-512 & tanda tangan FINAL (verification_signature)
 * sengaja TIDAK diisi di sini — itu hanya dibuat oleh createFinalVerificationProof()
 * setelah Manajer approve. verify-ttd.php sudah menangani status non-approved
 * sebagai "Dokumen Belum Selesai Disetujui" (bukan "tidak valid"), jadi aman
 * untuk menampilkan QR ini lebih awal. Jika $existing_uuid diisi, UUID (dan
 * karenanya URL/QR) yang sama akan tetap dipakai sampai approval final.
 */
function createPreliminaryVerificationQr(int $formulir_id, $db, ?string $existing_uuid = null): ?array {
    $verification_uuid = $existing_uuid ?: generateVerificationUuid();
    $verification_url  = getAppBaseUrl() . 'verify/' . $verification_uuid;
    $qrcode_path        = generateVerificationQrPng($verification_uuid, $verification_url);
    if (!$qrcode_path) return null;

    $stmt = $db->prepare("
        UPDATE formulir_checklist
        SET verification_uuid = ?, verification_url = ?, verification_qrcode_path = ?
        WHERE id = ?
    ");
    $stmt->execute([$verification_uuid, $verification_url, $qrcode_path, $formulir_id]);

    return [
        'uuid'         => $verification_uuid,
        'url'          => $verification_url,
        'qrcode_path'  => $qrcode_path,
    ];
}

/** Buat seluruh proof verifikasi final yang diperlukan setelah approval Manager. */
function createFinalVerificationProof(int $formulir_id, $db, string $private_key_pem, ?string $existing_uuid = null): ?array {
    $verification_uuid = $existing_uuid ?: generateVerificationUuid();
    $verification_hash = computeDocumentHash($formulir_id, $db, 'sha512');
    if (!$verification_hash) return null;

    $verification_signature = signData($verification_hash, $private_key_pem, OPENSSL_ALGO_SHA512);
    if (!$verification_signature) return null;

    $verification_url = getAppBaseUrl() . 'verify/' . $verification_uuid;
    $qrcode_path = generateVerificationQrPng($verification_uuid, $verification_url);
    if (!$qrcode_path) return null;

    return [
        'uuid' => $verification_uuid,
        'hash' => $verification_hash,
        'signature' => $verification_signature,
        'url' => $verification_url,
        'qrcode_path' => $qrcode_path,
    ];
}

/** Membaca private key PEM dari direktori rsa-keys. */
function getPrivateKey(string $role): ?string {
    $path = RSA_KEY_DIR . preg_replace('/[^a-z0-9_]/', '', $role) . '_private.pem';
    if (!is_readable($path)) return null;
    return file_get_contents($path) ?: null;
}

/** Membaca public key PEM dari direktori rsa-keys. */
function getPublicKey(string $role): ?string {
    $path = RSA_KEY_DIR . preg_replace('/[^a-z0-9_]/', '', $role) . '_public.pem';
    if (!is_readable($path)) return null;
    return file_get_contents($path) ?: null;
}

/**
 * Membuat tanda tangan digital RSA-2048-PKCS1v15-SHA256.
 * @return string|null Base64-encoded signature, atau null jika gagal.
 */
function signData(string $data, string $private_key_pem, int $algorithm = OPENSSL_ALGO_SHA256): ?string {
    $key = openssl_pkey_get_private($private_key_pem);
    if (!$key) return null;
    $raw = '';
    if (!openssl_sign($data, $raw, $key, $algorithm)) return null;
    return base64_encode($raw);
}

/**
 * Memverifikasi tanda tangan digital RSA-2048.
 * @return bool true jika signature valid dan data tidak berubah.
 */
function verifySignature(string $data, string $signature_b64, string $public_key_pem, int $algorithm = OPENSSL_ALGO_SHA256): bool {
    $key = openssl_pkey_get_public($public_key_pem);
    if (!$key) return false;
    $raw = base64_decode($signature_b64, true);
    if ($raw === false) return false;
    return openssl_verify($data, $raw, $key, $algorithm) === 1;
}

/**
 * Mencatat aksi tanda tangan ke tabel digital_signature_log.
 */
function logSignatureAction(int $formulir_id, string $action, array $user, ?string $hash, ?string $signature, string $notes = ''): void {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            INSERT INTO digital_signature_log
              (formulir_id, action, user_id, user_name, role_signer, dokumen_hash, signature_snippet, ip_address, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $formulir_id,
            $action,
            $user['id']        ?? null,
            $user['full_name'] ?? ($user['username'] ?? 'System'),
            $user['role']      ?? null,
            $hash,
            $signature ? substr($signature, 0, 50) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $notes ?: null,
        ]);
    } catch (Exception $e) {
        error_log("logSignatureAction error: " . $e->getMessage());
    }
}

/** Mengambil URL basis aplikasi secara otomatis. */
function getAppBaseUrl(): string {
    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir    = rtrim(dirname($_SERVER['PHP_SELF'] ?? '/'), '/\\');
    return "{$scheme}://{$host}{$dir}/";
}

// Production: errors only go to log, never shown to users
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Session configuration - MUST be set BEFORE session_start()
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // Ubah ke 1 jika server pakai HTTPS
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

// Security headers
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}
