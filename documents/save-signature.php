<?php
/**
 * API: Simpan Tanda Tangan Digital (Canvas + RSA-2048/SHA-256)
 * PRIMA (Pertamina Checklist Mobil Tangki)
 *
 * Dipanggil dari form utama (index.html / index-industri.html) saat user
 * membubuhkan tanda tangan gambar pada canvas HSSE atau Manajer.
 * Selain menyimpan gambar, endpoint ini juga membuat tanda tangan digital
 * RSA-2048 atas hash dokumen (konsisten dengan sign-checklist.php).
 */

require_once '../auth/auth.php';
requireLogin();
require_once '../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method tidak diizinkan', null, 405);
}

// Parse input: support JSON body atau form-data
$ct = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($ct, 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
} else {
    $input = $_POST;
}

$formulir_id  = (int)($input['formulir_id'] ?? 0);
$role         = trim($input['role'] ?? '');
$canvas_image = trim($input['canvas_image'] ?? '');
$signer_name  = trim($input['signer_name'] ?? '');

if (!$formulir_id || !$role || !$canvas_image || !$signer_name) {
    jsonResponse(false, 'Parameter tidak lengkap: formulir_id, role, canvas_image, dan signer_name wajib diisi');
}

if (!in_array($role, ['hsse', 'manajer'], true)) {
    jsonResponse(false, 'Role tidak dikenali: ' . htmlspecialchars($role));
}

// Validasi format gambar (data URI PNG) dan batasi ukurannya (maks ~3MB base64)
if (!preg_match('#^data:image/png;base64,[A-Za-z0-9+/=]+$#', $canvas_image)) {
    jsonResponse(false, 'Format gambar tanda tangan tidak valid');
}
if (strlen($canvas_image) > 3 * 1024 * 1024) {
    jsonResponse(false, 'Ukuran gambar tanda tangan terlalu besar');
}

// ── Kontrol akses per role ──────────────────────────────────────────────────
if ($role === 'hsse') {
    if (!canSignHSSE()) {
        jsonResponse(false, 'Akses ditolak: hanya Tim HSSE atau Admin yang dapat menandatangani sebagai HSSE', null, 403);
    }
} else {
    // SECURITY: Digital Signature Manager HANYA untuk role Manager.
    if (!canSignManager()) {
        jsonResponse(false, 'Akses ditolak: hanya Manager yang dapat menandatangani sebagai Manajer', null, 403);
    }
}

$user = getCurrentUser();
$generated_qrcode_path = null;

try {
    $db = Database::getInstance()->getConnection();

    $stmt = $db->prepare("SELECT * FROM formulir_checklist WHERE id = ? LIMIT 1");
    $stmt->execute([$formulir_id]);
    $formulir = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$formulir) {
        jsonResponse(false, 'Formulir tidak ditemukan');
    }

    $cur_status = $formulir['status_approval'];

    // ════════════════════════════════════════════════════════
    //  TANDA TANGAN HSSE (pertama)
    // ════════════════════════════════════════════════════════
    if ($role === 'hsse') {
        if (!in_array($cur_status, ['draft', 'pending_hsse'], true)) {
            jsonResponse(false, "Formulir dengan status '{$cur_status}' tidak dapat ditandatangani ulang oleh HSSE");
        }

        $hash = computeDocumentHash($formulir_id, $db);
        if (!$hash) {
            jsonResponse(false, 'Gagal menghitung hash dokumen');
        }

        $priv_pem = getPrivateKey('hsse');
        if (!$priv_pem) {
            jsonResponse(false,
                'Kunci privat HSSE belum dikonfigurasi. ' .
                'Silakan buka menu Pengaturan Sistem → Kelola Kunci RSA dan generate key pairs terlebih dahulu.'
            );
        }

        $sig = signData($hash, $priv_pem);
        if (!$sig) {
            jsonResponse(false, 'Gagal membuat tanda tangan digital. Periksa konfigurasi OpenSSL server.');
        }

        $stmt = $db->prepare("
            UPDATE formulir_checklist
            SET dokumen_hash       = ?,
                status_approval    = 'signed_hsse',
                ttd_hsse_user_id   = ?,
                ttd_hsse_nama      = ?,
                ttd_hsse_timestamp = NOW(),
                ttd_hsse_signature = ?,
                ttd_hsse_hash      = ?,
                ttd_hsse_gambar    = ?
            WHERE id = ?
        ");
        $signer_name_final = $signer_name ?: ($user['full_name'] ?: $user['username']);
        $stmt->execute([
            $hash,
            $user['id'], $signer_name_final, $sig, $hash, $canvas_image,
            $formulir_id,
        ]);

        if ($cur_status === 'draft') {
            logSignatureAction($formulir_id, 'SUBMIT', $user, $hash, null);
        }
        logSignatureAction($formulir_id, 'SIGN_HSSE', $user, $hash, $sig);
        logAudit($formulir_id, 'UPDATE', $user['username'],
            "TTD Digital HSSE dibubuhkan oleh {$signer_name_final}");

        // Buat QR Code verifikasi AWAL agar bisa langsung tampil setelah TTD HSSE
        // (belum final — hash SHA-512 & signature final baru dibuat saat Manajer approve).
        $prelim_qr = createPreliminaryVerificationQr($formulir_id, $db, $formulir['verification_uuid'] ?? null);

        jsonResponse(true, "Tanda tangan digital HSSE berhasil disimpan oleh {$signer_name_final}.", [
            'nama'         => $signer_name_final,
            'waktu'        => date('Y-m-d H:i:s'),
            'waktu_display'=> date('d/m/Y H:i:s'),
            'hash_preview' => substr($hash, 0, 16),
            'new_status'   => 'signed_hsse',
            'verification_url'        => $prelim_qr['url'] ?? null,
            'verification_qrcode_url' => $prelim_qr ? getVerificationQrPublicUrl($prelim_qr['qrcode_path']) : null,
        ]);
    }

    // ════════════════════════════════════════════════════════
    //  TANDA TANGAN MANAJER (kedua)
    // ════════════════════════════════════════════════════════
    if ($role === 'manajer') {
        if ($cur_status !== 'signed_hsse') {
            $msg = $cur_status === 'approved'
                ? 'Formulir ini sudah disetujui sepenuhnya.'
                : 'Formulir harus ditandatangani HSSE terlebih dahulu sebelum Manajer dapat menandatangani.';
            jsonResponse(false, $msg);
        }

        $stored_hash = $formulir['dokumen_hash'];

        // Verifikasi integritas dokumen sejak ditandatangani HSSE
        $live_hash = computeDocumentHash($formulir_id, $db);
        if ($live_hash !== $stored_hash) {
            jsonResponse(false,
                'PERINGATAN KEAMANAN: Integritas dokumen gagal! ' .
                'Isi dokumen telah berubah setelah ditandatangani HSSE. Proses penandatanganan ditolak.'
            );
        }

        // Verifikasi ulang tanda tangan HSSE
        $hsse_pub = getPublicKey('hsse');
        if ($hsse_pub && $formulir['ttd_hsse_signature']) {
            if (!verifySignature($stored_hash, $formulir['ttd_hsse_signature'], $hsse_pub)) {
                jsonResponse(false, 'PERINGATAN KEAMANAN: Verifikasi tanda tangan HSSE gagal! Proses penandatanganan Manajer ditolak.');
            }
        }

        $priv_pem = getPrivateKey('manager_hsse');
        if (!$priv_pem) {
            jsonResponse(false,
                'Kunci privat Manajer HSSE belum dikonfigurasi. ' .
                'Silakan buka menu Pengaturan Sistem → Kelola Kunci RSA.'
            );
        }

        $sig = signData($stored_hash, $priv_pem);
        if (!$sig) {
            jsonResponse(false, 'Gagal membuat tanda tangan digital Manajer.');
        }

        $db->beginTransaction();
        $verification_proof = createFinalVerificationProof(
            $formulir_id,
            $db,
            $priv_pem,
            $formulir['verification_uuid'] ?? null
        );
        if (!$verification_proof) {
            throw new RuntimeException('Gagal membuat proof SHA-512 atau file QR Code.');
        }
        $generated_qrcode_path = $verification_proof['qrcode_path'];

        $stmt = $db->prepare("
            UPDATE formulir_checklist
            SET status_approval       = 'approved',
                ttd_manajer_user_id   = ?,
                ttd_manajer_nama      = ?,
                ttd_manajer_timestamp = NOW(),
                ttd_manajer_signature = ?,
                ttd_manajer_hash      = ?,
                ttd_manajer_gambar    = ?,
                verification_uuid     = ?,
                verification_hash_sha512 = ?,
                verification_signature = ?,
                verification_url      = ?,
                verification_qrcode_path = ?,
                verification_created_at = NOW()
            WHERE id = ?
        ");
        $signer_name_final = $signer_name ?: ($user['full_name'] ?: $user['username']);
        $stmt->execute([
            $user['id'], $signer_name_final, $sig, $stored_hash, $canvas_image,
            $verification_proof['uuid'], $verification_proof['hash'], $verification_proof['signature'],
            $verification_proof['url'], $verification_proof['qrcode_path'],
            $formulir_id,
        ]);
        $db->commit();

        logSignatureAction($formulir_id, 'SIGN_MANAJER', $user, $stored_hash, $sig);
        logAudit($formulir_id, 'UPDATE', $user['username'],
            "TTD Digital Manajer HSSE dibubuhkan oleh {$signer_name_final}. Formulir DISETUJUI.");

        jsonResponse(true, "Tanda tangan Manajer berhasil disimpan oleh {$signer_name_final}. Formulir telah DISETUJUI.", [
            'nama'         => $signer_name_final,
            'waktu'        => date('Y-m-d H:i:s'),
            'waktu_display'=> date('d/m/Y H:i:s'),
            'hash_preview' => substr($stored_hash, 0, 16),
            'new_status'   => 'approved',
            'verification_url' => $verification_proof['url'],
            'verification_qrcode_url' => getVerificationQrPublicUrl($verification_proof['qrcode_path']),
        ]);
    }

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    if ($generated_qrcode_path) {
        @unlink(__DIR__ . '/storage/app/public/' . $generated_qrcode_path);
    }
    error_log("save-signature.php Error: " . $e->getMessage());
    jsonResponse(false, 'Terjadi kesalahan server: ' . $e->getMessage());
}
