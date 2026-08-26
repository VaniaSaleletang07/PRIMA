<?php
/**
 * API: Izin Tanda Tangan Untuk User Saat Ini
 * PRIMA (Pertamina Checklist Mobil Tangki)
 *
 * Dipanggil dari index.html/index-industri.html saat membuat FORMULIR BARU
 * (belum punya id/formulir_id), karena get.php (yang biasanya membawa flag
 * viewer_*) baru bisa dipanggil untuk formulir yang sudah ada di database.
 * Tanpa endpoint ini, kartu Tanda Tangan HSSE/Manajer pada formulir baru
 * tidak pernah di-role-gate oleh applyRoleBasedSignatureUI() di script.js.
 */

require_once '../auth/auth.php';
requireLogin();
require_once '../config/config.php';

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'success' => true,
    'data' => [
        'viewer_role'          => $_SESSION['role'] ?? '',
        'viewer_is_manager'    => isManager(),
        'viewer_can_sign_hsse' => canSignHSSE(),
    ],
], JSON_UNESCAPED_UNICODE);
