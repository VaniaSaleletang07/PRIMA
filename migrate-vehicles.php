<?php
/**
 * Migration Script - Buat tabel kendaraan
 */

require_once 'config.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Cek apakah tabel sudah ada
    $stmt = $db->prepare("SHOW TABLES LIKE 'kendaraan'");
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Tabel kendaraan sudah ada']);
        exit;
    }
    
    // Buat tabel kendaraan
    $sql = "
    CREATE TABLE `kendaraan` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `jenis` enum('SPBU','INDUSTRI') NOT NULL DEFAULT 'SPBU',
      `nomor_polisi` varchar(20) NOT NULL UNIQUE,
      `merk_mobil` varchar(100) NOT NULL,
      `tahun_kendaraan` int(4),
      `nama_transport` varchar(100),
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
    
    echo json_encode(['success' => true, 'message' => 'Tabel kendaraan berhasil dibuat']);
    
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
