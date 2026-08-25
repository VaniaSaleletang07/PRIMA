-- ============================================================
-- MIGRASI: Sistem Tanda Tangan Digital RSA-2048 / SHA-256
-- PRIMA (Pertamina Checklist Mobil Tangki)
-- ============================================================
-- PENTING: Jalankan script ini SATU KALI pada database lokal
-- maupun hosting. Pastikan database yang dipilih sudah benar.
-- ============================================================

-- 1. Tambah role baru: hsse dan manager_hsse
ALTER TABLE `users`
  MODIFY COLUMN `role`
  ENUM('admin','user','pengurus','hsse','manager_hsse')
  NOT NULL DEFAULT 'user';

-- 2. Tambah kolom public_key ke tabel users (arsip fingerprint)
ALTER TABLE `users`
  ADD COLUMN `public_key` TEXT NULL
  COMMENT 'RSA-2048 public key PEM (arsip, digunakan untuk verifikasi)'
  AFTER `position`;

-- 3. Tambah kolom tanda tangan digital ke formulir_checklist
ALTER TABLE `formulir_checklist`
  ADD COLUMN `dokumen_hash`          VARCHAR(64)  NULL
    COMMENT 'SHA-256 hash dari isi dokumen (canonical JSON)'
    AFTER `updated_at`,

  ADD COLUMN `status_approval`
    ENUM('draft','pending_hsse','signed_hsse','approved','rejected')
    NOT NULL DEFAULT 'draft'
    COMMENT 'Status workflow approval tanda tangan digital'
    AFTER `dokumen_hash`,

  ADD COLUMN `ttd_hsse_user_id`      INT          NULL
    COMMENT 'FK ke users.id — penandatangan HSSE'
    AFTER `status_approval`,

  ADD COLUMN `ttd_hsse_nama`         VARCHAR(100) NULL
    COMMENT 'Nama lengkap penandatangan HSSE'
    AFTER `ttd_hsse_user_id`,

  ADD COLUMN `ttd_hsse_signature`    TEXT         NULL
    COMMENT 'Tanda tangan base64(RSA-2048-PKCS1v15-SHA256) oleh HSSE'
    AFTER `ttd_hsse_nama`,

  ADD COLUMN `ttd_hsse_timestamp`    DATETIME     NULL
    COMMENT 'Waktu tanda tangan HSSE'
    AFTER `ttd_hsse_signature`,

  ADD COLUMN `ttd_manajer_user_id`   INT          NULL
    COMMENT 'FK ke users.id — penandatangan Manajer HSSE'
    AFTER `ttd_hsse_timestamp`,

  ADD COLUMN `ttd_manajer_nama`      VARCHAR(100) NULL
    COMMENT 'Nama lengkap penandatangan Manajer HSSE'
    AFTER `ttd_manajer_user_id`,

  ADD COLUMN `ttd_manajer_signature` TEXT         NULL
    COMMENT 'Tanda tangan base64(RSA-2048-PKCS1v15-SHA256) oleh Manajer'
    AFTER `ttd_manajer_nama`,

  ADD COLUMN `ttd_manajer_timestamp` DATETIME     NULL
    COMMENT 'Waktu tanda tangan Manajer'
    AFTER `ttd_manajer_signature`,

  ADD COLUMN `qr_token`              VARCHAR(64)  NULL
    COMMENT 'Token unik 32-byte hex untuk verifikasi QR Code'
    AFTER `ttd_manajer_timestamp`,

  ADD COLUMN `verification_uuid`     CHAR(36)     NULL
    COMMENT 'UUID v4 unik untuk URL verifikasi publik'
    AFTER `qr_token`,

  ADD COLUMN `verification_hash_sha512` CHAR(128) NULL
    COMMENT 'SHA-512 hash kanonik dokumen setelah approval final'
    AFTER `verification_uuid`,

  ADD COLUMN `verification_signature` TEXT         NULL
    COMMENT 'RSA-2048/SHA-512 signature dokumen final oleh Manajer'
    AFTER `verification_hash_sha512`,

  ADD COLUMN `verification_url`      VARCHAR(512) NULL
    COMMENT 'URL verifikasi yang dimuat dalam QR Code'
    AFTER `verification_signature`,

  ADD COLUMN `verification_qrcode_path` VARCHAR(255) NULL
    COMMENT 'Path PNG QR Code di storage/app/public/qrcode'
    AFTER `verification_url`,

  ADD COLUMN `verification_created_at` DATETIME    NULL
    COMMENT 'Waktu QR Code verifikasi dibuat'
    AFTER `verification_qrcode_path`,

  ADD UNIQUE KEY `uq_qr_token`         (`qr_token`),
  ADD UNIQUE KEY `uq_verification_uuid` (`verification_uuid`),
  ADD INDEX     `idx_status_approval`  (`status_approval`);

-- 4. Foreign Keys untuk kolom tanda tangan
ALTER TABLE `formulir_checklist`
  ADD CONSTRAINT `fk_fc_ttd_hsse`
    FOREIGN KEY (`ttd_hsse_user_id`)    REFERENCES `users`(`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_fc_ttd_manajer`
    FOREIGN KEY (`ttd_manajer_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL;

-- 5. Tabel audit trail tanda tangan digital
CREATE TABLE IF NOT EXISTS `digital_signature_log` (
  `id`                INT          NOT NULL AUTO_INCREMENT,
  `formulir_id`       INT          NOT NULL,
  `action`            ENUM('SUBMIT','SIGN_HSSE','SIGN_MANAJER','VERIFY','REJECT','RESET_DRAFT') NOT NULL,
  `user_id`           INT          NULL,
  `user_name`         VARCHAR(100) NULL,
  `role_signer`       VARCHAR(50)  NULL,
  `dokumen_hash`      VARCHAR(128) NULL  COMMENT 'Hash dokumen saat aksi dilakukan',
  `signature_snippet` VARCHAR(50)  NULL  COMMENT '50 karakter pertama signature',
  `ip_address`        VARCHAR(45)  NULL,
  `notes`             TEXT         NULL,
  `created_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ds_formulir`   (`formulir_id`),
  KEY `idx_ds_action`     (`action`),
  KEY `idx_ds_user`       (`user_id`),
  KEY `idx_ds_created_at` (`created_at`),
  CONSTRAINT `fk_dslog_formulir`
    FOREIGN KEY (`formulir_id`) REFERENCES `formulir_checklist`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Audit trail aktivitas tanda tangan digital';

-- 6. Set status 'draft' untuk data existing
UPDATE `formulir_checklist`
SET `status_approval` = 'draft'
WHERE `status_approval` IS NULL;

-- QR verification UUID dibuat oleh aplikasi hanya setelah approval Manager.

-- ============================================================
-- Contoh membuat user HSSE dan Manajer HSSE (sesuaikan):
-- INSERT INTO users (username, password, full_name, email, role, status)
-- VALUES
--   ('hsse01', '$2y$10$...', 'Nama Tim HSSE', 'hsse@pertamina.com', 'hsse', 'active'),
--   ('manajer_hsse', '$2y$10$...', 'Nama Manajer HSSE', 'manager@pertamina.com', 'manager_hsse', 'active');
-- ============================================================

SELECT 'Migrasi digital signature berhasil. Jalankan generate-keys.php untuk membuat RSA key pairs.' AS status;
