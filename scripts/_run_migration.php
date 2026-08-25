<?php
require_once '../config/config.php';
$db = Database::getInstance()->getConnection();

$steps = [
    'Rename ttd_hsse_waktu -> ttd_hsse_timestamp'    => "ALTER TABLE formulir_checklist CHANGE COLUMN ttd_hsse_waktu ttd_hsse_timestamp DATETIME NULL",
    'Rename ttd_manajer_waktu -> ttd_manajer_timestamp' => "ALTER TABLE formulir_checklist CHANGE COLUMN ttd_manajer_waktu ttd_manajer_timestamp DATETIME NULL",
    'Add dokumen_hash'      => "ALTER TABLE formulir_checklist ADD COLUMN dokumen_hash VARCHAR(64) NULL COMMENT 'SHA-256 hash' AFTER updated_at",
    'Add status_approval'   => "ALTER TABLE formulir_checklist ADD COLUMN status_approval ENUM('draft','pending_hsse','signed_hsse','approved','rejected') NOT NULL DEFAULT 'draft' AFTER dokumen_hash",
    'Add ttd_hsse_user_id'  => "ALTER TABLE formulir_checklist ADD COLUMN ttd_hsse_user_id INT NULL AFTER status_approval",
    'Add ttd_manajer_user_id' => "ALTER TABLE formulir_checklist ADD COLUMN ttd_manajer_user_id INT NULL AFTER ttd_hsse_timestamp",
    'Add qr_token'          => "ALTER TABLE formulir_checklist ADD COLUMN qr_token VARCHAR(64) NULL UNIQUE COMMENT 'Token QR Code'",
    'Add verification_uuid' => "ALTER TABLE formulir_checklist ADD COLUMN verification_uuid CHAR(36) NULL UNIQUE COMMENT 'UUID URL verifikasi publik' AFTER qr_token",
    'Add verification_hash_sha512' => "ALTER TABLE formulir_checklist ADD COLUMN verification_hash_sha512 CHAR(128) NULL COMMENT 'Hash SHA-512 dokumen final' AFTER verification_uuid",
    'Add verification_signature' => "ALTER TABLE formulir_checklist ADD COLUMN verification_signature TEXT NULL COMMENT 'RSA-SHA-512 signature dokumen final' AFTER verification_hash_sha512",
    'Add verification_url'  => "ALTER TABLE formulir_checklist ADD COLUMN verification_url VARCHAR(512) NULL COMMENT 'URL yang dimuat QR Code' AFTER verification_signature",
    'Add verification_qrcode_path' => "ALTER TABLE formulir_checklist ADD COLUMN verification_qrcode_path VARCHAR(255) NULL COMMENT 'Path PNG QR Code di storage' AFTER verification_url",
    'Add verification_created_at' => "ALTER TABLE formulir_checklist ADD COLUMN verification_created_at DATETIME NULL COMMENT 'Waktu QR verification dibuat' AFTER verification_url",
    'Expand signature log hash' => "ALTER TABLE digital_signature_log MODIFY COLUMN dokumen_hash VARCHAR(128) NULL COMMENT 'Hash dokumen saat aksi dilakukan'",
    'Add idx_status_approval' => "ALTER TABLE formulir_checklist ADD INDEX idx_status_approval (status_approval)",
    'Update role enum'      => "ALTER TABLE users MODIFY COLUMN role ENUM('admin','user','pengurus','hsse','manager_hsse') NOT NULL DEFAULT 'user'",
    'Add public_key users'  => "ALTER TABLE users ADD COLUMN public_key TEXT NULL AFTER position",
    'Create digital_signature_log' => "CREATE TABLE IF NOT EXISTS digital_signature_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        formulir_id INT NOT NULL,
        action ENUM('SUBMIT','SIGN_HSSE','SIGN_MANAJER','VERIFY','REJECT','RESET_DRAFT') NOT NULL,
        user_id INT NULL, user_name VARCHAR(100) NULL, role_signer VARCHAR(50) NULL,
        dokumen_hash VARCHAR(64) NULL, signature_snippet VARCHAR(50) NULL,
        ip_address VARCHAR(45) NULL, notes TEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_ds_formulir (formulir_id),
        KEY idx_ds_action (action),
        CONSTRAINT fk_dslog_formulir FOREIGN KEY (formulir_id)
          REFERENCES formulir_checklist(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    'Set draft existing'    => "UPDATE formulir_checklist SET status_approval='draft' WHERE status_approval IS NULL OR status_approval=''",
];

foreach ($steps as $name => $sql) {
    try {
        $db->exec($sql);
        echo "[OK]   $name\n";
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        $skip = strpos($msg,'1060') !== false // duplicate column
             || strpos($msg,'1061') !== false // duplicate key
               || strpos($msg,'1054') !== false // source column already renamed
             || strpos($msg,'already exists') !== false
             || strpos($msg,'Duplicate') !== false;
        if ($skip) {
            echo "[SKIP] $name (sudah ada)\n";
        } else {
            echo "[ERR]  $name: $msg\n";
        }
    }
}

echo "\nKolom sekarang:\n";
$cols = $db->query('SHOW COLUMNS FROM formulir_checklist')->fetchAll(PDO::FETCH_COLUMN);
echo implode(', ', $cols) . "\n";
echo "\nMigrasi selesai.\n";
