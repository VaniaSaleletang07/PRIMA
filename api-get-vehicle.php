<?php
/**
 * API - Get Vehicle Data by Nomor Polisi
 * Mengambil data kendaraan berdasarkan nomor polisi
 */

require_once 'auth.php';
requireLogin();

require_once 'config.php';

// Auto-create vehicle table if not exists
ensureVehicleTableExists();

header('Content-Type: application/json');

$nomorPolisi = isset($_GET['nomor_polisi']) ? trim($_GET['nomor_polisi']) : '';

if (empty($nomorPolisi)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nomor polisi tidak boleh kosong']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Ambil data kendaraan
    $stmt = $db->prepare("
        SELECT 
            id, 
            nomor_polisi,
            merk_mobil,
            tahun_kendaraan,
            nama_transport,
            produk_kapasitas,
            tanggal_pemeriksaan_terakhir,
            ekim_valid_until,
            jenis
        FROM kendaraan 
        WHERE nomor_polisi = :nomor_polisi 
        AND status = 'AKTIF'
        LIMIT 1
    ");
    
    $stmt->execute([':nomor_polisi' => strtoupper($nomorPolisi)]);
    
    if ($stmt->rowCount() > 0) {
        $kendaraan = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode([
            'success' => true,
            'data' => $kendaraan
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Data kendaraan tidak ditemukan'
        ]);
    }
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
