<?php
/** Lightweight notification counter for the Manager dashboard. */
require_once 'auth.php';
requireLogin();
require_once 'config.php';

if (!isManager()) {
    jsonResponse(false, 'Akses ditolak', null, 403);
}

try {
    $db = Database::getInstance()->getConnection();
    $count = (int)$db->query("SELECT COUNT(*) FROM formulir_checklist WHERE status_approval = 'signed_hsse'")->fetchColumn();
    jsonResponse(true, 'OK', ['count' => $count]);
} catch (Exception $e) {
    error_log('api-manager-pending-count.php: ' . $e->getMessage());
    jsonResponse(false, 'Gagal mengambil notifikasi');
}
