<?php
/** Rute publik /verify/{uuid} untuk QR Code verifikasi dokumen. */

$path = trim($_SERVER['PATH_INFO'] ?? '', '/');
if (!isset($_GET['uuid']) && $path !== '') {
    $_GET['uuid'] = $path;
}

require __DIR__ . '/../verify-ttd.php';
