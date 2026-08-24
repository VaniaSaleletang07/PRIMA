<?php
/**
 * Load All Data Formulir Checklist
 * PRIMA (Pertamina Checklist Mobil Tangki)
 */

require_once '../auth/auth.php';
requireLogin();

require_once '../config/config.php';

$user = getCurrentUser();

try {
    $db = Database::getInstance()->getConnection();
    
    // Get pagination and filter parameters
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $offset = ($page - 1) * $limit;
    
    $search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
    $dateFrom = isset($_GET['dateFrom']) ? sanitizeInput($_GET['dateFrom']) : '';
    $dateTo = isset($_GET['dateTo']) ? sanitizeInput($_GET['dateTo']) : '';
    $jenisKendaraan = isset($_GET['jenis']) ? sanitizeInput($_GET['jenis']) : '';
    $approvalStatus = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
    
    // Build WHERE clause
    $where = "WHERE 1=1";
    $params = [];
    
    if (!empty($search)) {
        $where .= " AND (f.nomor_polisi LIKE :search1 OR f.nama_transport LIKE :search2 OR f.nomor_urut LIKE :search3)";
        $params[':search1'] = "%{$search}%";
        $params[':search2'] = "%{$search}%";
        $params[':search3'] = "%{$search}%";
    }
    
    if (!empty($dateFrom)) {
        $where .= " AND f.tanggal_pemeriksaan >= :dateFrom";
        $params[':dateFrom'] = $dateFrom;
    }
    
    if (!empty($dateTo)) {
        $where .= " AND f.tanggal_pemeriksaan <= :dateTo";
        $params[':dateTo'] = $dateTo;
    }
    
    if (!empty($jenisKendaraan) && ($jenisKendaraan === 'SPBU' || $jenisKendaraan === 'INDUSTRI')) {
        $where .= " AND f.jenis_kendaraan = :jenis";
        $params[':jenis'] = $jenisKendaraan;
    }

    if (in_array($approvalStatus, ['draft', 'pending_hsse', 'signed_hsse', 'approved', 'rejected'], true)) {
        $where .= " AND f.status_approval = :approval_status";
        $params[':approval_status'] = $approvalStatus;
    }
    
    // Get total count
    $countStmt = $db->prepare("
        SELECT COUNT(DISTINCT f.id) as total 
        FROM formulir_checklist f 
        LEFT JOIN checklist_items ci ON f.id = ci.formulir_id 
        $where
    ");
    $countStmt->execute($params);
    $total = $countStmt->fetch()['total'];
    
    // Get data with pagination
    $stmt = $db->prepare("
        SELECT 
            f.id, f.jenis_kendaraan, f.nomor_urut, f.merk_mobil,
            f.nama_transport, f.nomor_polisi, f.tanggal_terakhir,
            f.produk_kapasitas, f.tanggal_pemeriksaan, f.ekim_valid_until,
            f.status_gate, f.status_upload, f.nama_pemeriksa,
            f.tanggal_pemeriksa, f.created_at, f.updated_at, f.created_by,
            f.ttd_hsse_nama, f.ttd_hsse_timestamp,
            f.ttd_manajer_nama, f.ttd_manajer_timestamp,
            f.status_approval, f.qr_token, f.dokumen_hash, f.verification_url,
            COUNT(ci.id) as total_items,
            SUM(CASE WHEN ci.is_baik = TRUE THEN 1 ELSE 0 END) as total_baik,
            SUM(CASE WHEN ci.is_tidak = TRUE THEN 1 ELSE 0 END) as total_tidak,
            ROUND((SUM(CASE WHEN ci.is_baik = TRUE THEN 1 ELSE 0 END) / COUNT(ci.id) * 100), 2) as persentase_baik,
            SUM(CASE
                    WHEN ci.item_name IN ('STNK','PAJAK','SIMFIT (Industri)','Surat Tera Metrologi','Surat Keur DLLAAJR')
                     AND ci.tanggal_expire IS NOT NULL AND ci.tanggal_expire != '0000-00-00'
                     AND ci.tanggal_expire < CURDATE()
                     AND (ci.item_name != 'SIMFIT (Industri)' OR UPPER(f.jenis_kendaraan) = 'INDUSTRI')
                    THEN 1 ELSE 0 END) as total_dokumen_expired,
            GROUP_CONCAT(
                CASE
                    WHEN ci.item_name IN ('STNK','PAJAK','SIMFIT (Industri)','Surat Tera Metrologi','Surat Keur DLLAAJR')
                     AND ci.tanggal_expire IS NOT NULL AND ci.tanggal_expire != '0000-00-00'
                     AND ci.tanggal_expire < CURDATE()
                     AND (ci.item_name != 'SIMFIT (Industri)' OR UPPER(f.jenis_kendaraan) = 'INDUSTRI')
                    THEN ci.item_name END SEPARATOR ', '
            ) as dokumen_expired_list
        FROM formulir_checklist f
        LEFT JOIN checklist_items ci ON f.id = ci.formulir_id
        $where 
        GROUP BY f.id
        ORDER BY f.tanggal_pemeriksaan DESC, f.created_at DESC 
        LIMIT :limit OFFSET :offset
    ");
    
    // Merge filter params with pagination params
    $allParams = $params;
    $allParams[':limit'] = $limit;
    $allParams[':offset'] = $offset;
    
    // Bind all parameters
    foreach ($allParams as $key => $value) {
        if ($key === ':limit' || $key === ':offset') {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value);
        }
    }
    
    $stmt->execute();
    $data = $stmt->fetchAll();
    
    // Send response with proper structure
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'message' => 'Data berhasil dimuat',
        'data' => $data,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => (int)$total,
            'totalPages' => (int)ceil($total / $limit)
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
    
} catch(Exception $e) {
    error_log("Load Error: " . $e->getMessage());
    jsonResponse(false, 'Gagal memuat data: ' . $e->getMessage());
}
