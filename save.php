<?php
/**
 * Save Formulir Checklist ke Database
 * PRIMA (Pertamina Checklist Mobil Tangki)
 */

require_once 'auth.php';
requireNonManager();

require_once 'config.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed');
}

// Get current user
$user = getCurrentUser();
$userId = $user['id'];

// Get JSON data
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    jsonResponse(false, 'Data tidak valid');
}

try {
    $db = Database::getInstance()->getConnection();
    ensureChecklistItemsExpiryColumnExists();
    $db->beginTransaction();
    
    // Validate required fields
    if (empty($data['nomorPolisi']) || empty($data['tanggalPemeriksaan'])) {
        throw new Exception('Nomor Polisi dan Tanggal Pemeriksaan harus diisi');
    }
    
    // Check if this is an update or new entry
    $formulir_id = $data['id'] ?? null;
    $is_update = !empty($formulir_id);

    if ($is_update) {
        // LOCK CHECK: Dokumen yang sudah disubmit/signed/approved tidak dapat diubah
        $lockStmt = $db->prepare("SELECT status_approval FROM formulir_checklist WHERE id = ? LIMIT 1");
        $lockStmt->execute([$formulir_id]);
        $lockRow = $lockStmt->fetch(PDO::FETCH_ASSOC);
        if ($lockRow && $lockRow['status_approval'] !== 'draft') {
            $statusLabel = [
                'pending_hsse' => 'Menunggu Tanda Tangan HSSE',
                'signed_hsse'  => 'Sudah Ditandatangani HSSE',
                'approved'     => 'Sudah Disetujui',
                'rejected'     => 'Ditolak',
            ][$lockRow['status_approval']] ?? $lockRow['status_approval'];
            throw new Exception("Dokumen tidak dapat diubah karena statusnya: {$statusLabel}. Hubungi Admin untuk mereset ke Draft jika diperlukan perbaikan.");
        }
    }

    if ($is_update) {
        // Update existing record
        $stmt = $db->prepare("
            UPDATE formulir_checklist SET
                jenis_kendaraan = :jenis_kendaraan,
                nomor_urut = :nomor_urut,
                merk_mobil = :merk_mobil,
                nama_transport = :nama_transport,
                nomor_polisi = :nomor_polisi,
                tanggal_terakhir = :tanggal_terakhir,
                produk_kapasitas = :produk_kapasitas,
                tanggal_pemeriksaan = :tanggal_pemeriksaan,
                ekim_valid_until = :ekim_valid_until,
                status_gate = :status_gate,
                status_upload = :status_upload,
                nama_pemeriksa = :nama_pemeriksa,
                tanggal_pemeriksa = :tanggal_pemeriksa
            WHERE id = :id
        ");
        
        $stmt->execute([
            ':id' => $formulir_id,
            ':jenis_kendaraan' => $data['jenisKendaraan'] ?? 'SPBU',
            ':nomor_urut' => $data['nomorUrut'] ?? null,
            ':merk_mobil' => $data['merkMobil'] ?? null,
            ':nama_transport' => $data['namaTransport'] ?? null,
            ':nomor_polisi' => $data['nomorPolisi'],
            ':tanggal_terakhir' => $data['tanggalTerakhir'] ?? null,
            ':produk_kapasitas' => $data['produkKapasitas'] ?? null,
            ':tanggal_pemeriksaan' => $data['tanggalPemeriksaan'],
            ':ekim_valid_until' => $data['ekimValidUntil'] ?? null,
            ':status_gate' => $data['statusGate'] ?? null,
            ':status_upload' => $data['statusUpload'] ?? null,
            ':nama_pemeriksa' => $data['namaPemeriksaBagian'] ?? null,
            ':tanggal_pemeriksa' => $data['tanggalPemeriksaBagian'] ?? null
        ]);
        
        // Delete existing checklist items
        $stmt = $db->prepare("DELETE FROM checklist_items WHERE formulir_id = :formulir_id");
        $stmt->execute([':formulir_id' => $formulir_id]);
        
        $action = 'UPDATE';
        $message = 'Data berhasil diupdate';
        
    } else {
        // Insert new record
        $stmt = $db->prepare("
            INSERT INTO formulir_checklist (
                jenis_kendaraan, nomor_urut, merk_mobil, nama_transport, nomor_polisi,
                tanggal_terakhir, produk_kapasitas, tanggal_pemeriksaan,
                ekim_valid_until, status_gate, status_upload,
                nama_pemeriksa, tanggal_pemeriksa, created_by,
                status_approval
            ) VALUES (
                :jenis_kendaraan, :nomor_urut, :merk_mobil, :nama_transport, :nomor_polisi,
                :tanggal_terakhir, :produk_kapasitas, :tanggal_pemeriksaan,
                :ekim_valid_until, :status_gate, :status_upload,
                :nama_pemeriksa, :tanggal_pemeriksa, :created_by,
                'draft'
            )
        ");
        
        $stmt->execute([
            ':jenis_kendaraan' => $data['jenisKendaraan'] ?? 'SPBU',
            ':nomor_urut' => $data['nomorUrut'] ?? null,
            ':merk_mobil' => $data['merkMobil'] ?? null,
            ':nama_transport' => $data['namaTransport'] ?? null,
            ':nomor_polisi' => $data['nomorPolisi'],
            ':tanggal_terakhir' => $data['tanggalTerakhir'] ?? null,
            ':produk_kapasitas' => $data['produkKapasitas'] ?? null,
            ':tanggal_pemeriksaan' => $data['tanggalPemeriksaan'],
            ':ekim_valid_until' => $data['ekimValidUntil'] ?? null,
            ':status_gate' => $data['statusGate'] ?? null,
            ':status_upload' => $data['statusUpload'] ?? null,
            ':nama_pemeriksa' => $data['namaPemeriksaBagian'] ?? null,
            ':tanggal_pemeriksa' => $data['tanggalPemeriksaBagian'] ?? null,
            ':created_by' => $userId,
        ]);
        
        $formulir_id = $db->lastInsertId();
        $action = 'CREATE';
        $message = 'Data berhasil disimpan';
    }
    
    // Insert checklist items
    if (!empty($data['checklist']) && is_array($data['checklist'])) {
        $stmt = $db->prepare("
            INSERT INTO checklist_items (
                formulir_id, item_number, item_name, is_baik, is_tidak, keterangan, tanggal_expire
            ) VALUES (
                :formulir_id, :item_number, :item_name, :is_baik, :is_tidak, :keterangan, :tanggal_expire
            )
        ");
        
        foreach ($data['checklist'] as $index => $item) {
            $item_name = $item['nama'] ?? "Item Pemeriksaan " . ($index + 1);
            
            $stmt->execute([
                ':formulir_id' => $formulir_id,
                ':item_number' => $index + 1,
                ':item_name' => $item_name,
                ':is_baik' => !empty($item['baik']) ? 1 : 0,
                ':is_tidak' => !empty($item['tidak']) ? 1 : 0,
                ':keterangan' => $item['keterangan'] ?? null,
                ':tanggal_expire' => !empty($item['tanggal_expire']) ? $item['tanggal_expire'] : null
            ]);
        }
    }
    
    // Log audit
    $user_name = $_SESSION['username'] ?? 'System';
    logAudit($formulir_id, $action, $user_name, "Formulir checklist {$data['nomorPolisi']}");
    
    $db->commit();
    
    jsonResponse(true, $message, ['id' => $formulir_id]);
    
} catch(Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Save Error: " . $e->getMessage());
    jsonResponse(false, $e->getMessage());
}
