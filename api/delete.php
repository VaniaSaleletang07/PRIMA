<?php
/**
 * Delete Formulir Checklist
 * PRIMA (Pertamina Checklist Mobil Tangki)
 */

require_once '../auth/auth.php';
requireNonManager();

require_once '../config/config.php';

$user = getCurrentUser();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed');
}

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!isset($data['id'])) {
    jsonResponse(false, 'ID tidak ditemukan');
}

try {
    $db = Database::getInstance()->getConnection();
    $id = (int)$data['id'];
    
    // Check if record exists
    $stmt = $db->prepare("SELECT nomor_polisi FROM formulir_checklist WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $record = $stmt->fetch();
    
    if (!$record) {
        jsonResponse(false, 'Data tidak ditemukan');
    }
    
    // Delete record (checklist_items will be deleted automatically due to CASCADE)
    $stmt = $db->prepare("DELETE FROM formulir_checklist WHERE id = :id");
    $stmt->execute([':id' => $id]);
    
    // Log audit
    $user_name = $_SESSION['username'] ?? 'System';
    logAudit($id, 'DELETE', $user_name, "Formulir checklist {$record['nomor_polisi']} dihapus");
    
    jsonResponse(true, 'Data berhasil dihapus');
    
} catch(Exception $e) {
    error_log("Delete Error: " . $e->getMessage());
    jsonResponse(false, 'Gagal menghapus data: ' . $e->getMessage());
}
