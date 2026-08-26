<?php
/**
 * Get Single Formulir by ID
 * PRIMA (Pertamina Checklist Mobil Tangki)
 */

require_once '../auth/auth.php';
requireLogin();

require_once '../config/config.php';

$user = getCurrentUser();

if (!isset($_GET['id'])) {
    jsonResponse(false, 'ID tidak ditemukan');
}

try {
    $db = Database::getInstance()->getConnection();
    $id = (int)$_GET['id'];
    
    // Get formulir data
    $stmt = $db->prepare("SELECT * FROM formulir_checklist WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $formulir = $stmt->fetch();
    
    if (!$formulir) {
        jsonResponse(false, 'Data tidak ditemukan');
    }

    $formulir['verification_qrcode_url'] = getVerificationQrPublicUrl(
        $formulir['verification_qrcode_path'] ?? null
    );
    // Flags untuk kontrol UI berbasis role di sisi client (defense-in-depth;
    // akses sesungguhnya tetap ditegakkan di endpoint sign-checklist.php /
    // save-signature.php).
    $formulir['viewer_role']            = $user['role'];
    $formulir['viewer_is_manager']      = isManager();
    $formulir['viewer_can_sign_hsse']   = canSignHSSE();
    $formulir['viewer_can_approve'] = canSignManager()
        && ($formulir['status_approval'] ?? '') === 'signed_hsse';
    
    // Get checklist items
    $stmt = $db->prepare("
        SELECT * FROM checklist_items 
        WHERE formulir_id = :formulir_id 
        ORDER BY item_number
    ");
    $stmt->execute([':formulir_id' => $id]);
    $items = $stmt->fetchAll();
    
    $formulir['checklist_items'] = $items;
    
    jsonResponse(true, 'Data berhasil dimuat', $formulir);
    
} catch(Exception $e) {
    error_log("Get Error: " . $e->getMessage());
    jsonResponse(false, 'Gagal memuat data: ' . $e->getMessage());
}
