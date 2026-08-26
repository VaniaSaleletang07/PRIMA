<?php
/**
 * Get Vehicles Data - Untuk Autocomplete Nomor Polisi
 * PRIMA (Pertamina Checklist Mobil Tangki)
 * Updated: Ambil dari tabel kendaraan yang baru
 */

require_once 'auth.php';
requireLogin();

require_once 'config.php';

// Auto-create vehicle table if not exists
ensureVehicleTableExists();

header('Content-Type: application/json');

try {
    $db = Database::getInstance()->getConnection();
    
    // Get jenis kendaraan filter (SPBU atau INDUSTRI)
    $jenisKendaraan = isset($_GET['jenis']) ? sanitizeInput($_GET['jenis']) : '';
    
    $where = "WHERE k.status = 'AKTIF'";
    $params = [];
    
    if (!empty($jenisKendaraan) && ($jenisKendaraan === 'SPBU' || $jenisKendaraan === 'INDUSTRI')) {
        $where .= " AND k.jenis = :jenis";
        $params[':jenis'] = $jenisKendaraan;
    }
    
    // Ambil data kendaraan dari tabel kendaraan (master data)
    $stmt = $db->prepare("
        SELECT 
            k.nomor_polisi,
            k.merk_mobil,
            k.tahun_kendaraan,
            k.nama_transport,
            k.produk_kapasitas,
            k.tanggal_pemeriksaan_terakhir as tanggal_terakhir,
            k.ekim_valid_until,
            k.jenis as jenis_kendaraan
        FROM kendaraan k
        $where
        ORDER BY k.nomor_polisi ASC
    ");
    
    $stmt->execute($params);
    $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If kendaraan table is empty or doesn't exist, fall back to formulir_checklist
    if (count($vehicles) === 0) {
        $stmt = $db->prepare("
            SELECT 
                nomor_polisi,
                merk_mobil,
                nama_transport,
                produk_kapasitas,
                tanggal_terakhir,
                jenis_kendaraan
            FROM formulir_checklist
            WHERE nomor_polisi IS NOT NULL AND nomor_polisi != ''
            " . (!empty($jenisKendaraan) ? "AND jenis_kendaraan = :jenis" : "") . "
            GROUP BY nomor_polisi
            ORDER BY nomor_polisi ASC
        ");
        
        if (!empty($jenisKendaraan)) {
            $stmt->execute([':jenis' => $jenisKendaraan]);
        } else {
            $stmt->execute();
        }
        
        $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode([
        'success' => true,
        'data' => $vehicles
    ], JSON_UNESCAPED_UNICODE);
    
} catch(Exception $e) {
    error_log("Get Vehicles Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Gagal memuat data kendaraan: ' . $e->getMessage()
    ]);
}
