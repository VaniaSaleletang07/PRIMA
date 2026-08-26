<?php
/**
 * API - Get All License Plates
 * Mengambil semua nomor polisi untuk datalist autocomplete
 */

require_once 'auth.php';
requireLogin();

require_once 'config.php';

// Auto-create vehicle table if not exists
ensureVehicleTableExists();

header('Content-Type: application/json');

// Ambil jenis kendaraan dari query string (optional)
$jenis = isset($_GET['jenis']) ? $_GET['jenis'] : null;

try {
    $db = Database::getInstance()->getConnection();
    
    if ($jenis && in_array($jenis, ['SPBU', 'INDUSTRI'])) {
        $stmt = $db->prepare("
            SELECT nomor_polisi 
            FROM kendaraan 
            WHERE status = 'AKTIF' 
            AND jenis = :jenis
            ORDER BY nomor_polisi ASC
        ");
        $stmt->execute([':jenis' => $jenis]);
    } else {
        $stmt = $db->prepare("
            SELECT nomor_polisi 
            FROM kendaraan 
            WHERE status = 'AKTIF'
            ORDER BY nomor_polisi ASC
        ");
        $stmt->execute();
    }
    
    $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode([
        'success' => true,
        'data' => $results
    ]);
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
