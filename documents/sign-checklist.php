<?php
/**
 * API: Tanda Tangan Digital Formulir Checklist
 * PRIMA (Pertamina Checklist Mobil Tangki)
 *
 * Aksi yang didukung (POST):
 *   submit       — Kunci dokumen dan kirim ke antrian HSSE (Admin/HSSE)
 *   sign_hsse    — Tanda tangan pertama oleh HSSE (Admin/HSSE)
 *   sign_manajer — Tanda tangan kedua oleh Manajer HSSE (Manager SAJA)
 *   reject       — Tolak dokumen (Admin/Manager)
 *   reset_draft  — Reset ke Draft agar bisa diedit ulang (Admin saja)
 */

require_once '../auth/auth.php';
requireLogin();
require_once '../config/config.php';

// Parse input: support JSON body atau form-data
$ct = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($ct, 'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
} else {
    $input = $_POST;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method tidak diizinkan', null, 405);
}

$user        = getCurrentUser();
$formulir_id = (int)($input['formulir_id'] ?? 0);
$action      = trim($input['action'] ?? '');
$generated_qrcode_path = null;

if (!$formulir_id || !$action) {
    jsonResponse(false, 'Parameter tidak lengkap: formulir_id dan action wajib diisi');
}

// ── Validasi aksi ──────────────────────────────────────────────────────────
$allowed = ['submit', 'sign_hsse', 'sign_manajer', 'reject', 'reset_draft'];
if (!in_array($action, $allowed, true)) {
    jsonResponse(false, 'Aksi tidak dikenali: ' . htmlspecialchars($action));
}

// ── Kontrol akses per aksi ─────────────────────────────────────────────────
if (in_array($action, ['submit', 'sign_hsse'], true)) {
    if (!canSignHSSE()) {
        jsonResponse(false, 'Akses ditolak: hanya Tim HSSE atau Admin yang dapat melakukan aksi ini', null, 403);
    }
} elseif ($action === 'sign_manajer') {
    // SECURITY: Digital Signature Manager HANYA untuk role Manager.
    if (!canSignManager()) {
        jsonResponse(false, 'Akses ditolak: hanya Manager yang dapat melakukan Digital Signature Manager', null, 403);
    }
} elseif ($action === 'reject') {
    if (!canRejectChecklist()) {
        jsonResponse(false, 'Akses ditolak: hanya Manager atau Admin yang dapat menolak formulir', null, 403);
    }
} elseif ($action === 'reset_draft') {
    if (!isAdmin()) {
        jsonResponse(false, 'Akses ditolak: hanya Admin yang dapat mereset dokumen ke Draft', null, 403);
    }
}

/**
 * Cek item checklist berstatus "Tidak Baik" dan langsung beri tahu akun
 * transportir SEDINI mungkin (saat formulir dikunci/disubmit — sebelum
 * proses tanda tangan HSSE maupun Manajer berjalan), bukan menunggu sampai
 * Manajer mencoba menyetujui. Notifikasi block final di tahap sign_manajer
 * tetap dipertahankan sebagai pengingat + penegakan keamanan di server.
 */
function notifyIfTidakBaikFound(PDO $db, array $formulir, int $formulir_id): void
{
    if (empty($formulir['nomor_polisi'])) {
        return;
    }
    $tidakStmt = $db->prepare("
        SELECT item_name FROM checklist_items
        WHERE formulir_id = ? AND is_tidak = 1
        ORDER BY item_number
    ");
    $tidakStmt->execute([$formulir_id]);
    $tidakItems = $tidakStmt->fetchAll(PDO::FETCH_COLUMN);
    if (empty($tidakItems)) {
        return;
    }
    $msg = 'PERHATIAN: EKIM kendaraan ' . $formulir['nomor_polisi'] . ' belum dapat diterbitkan — ditemukan ' .
        count($tidakItems) . ' item pemeriksaan berstatus TIDAK BAIK — ' . implode('; ', $tidakItems) . '. ' .
        'Perbaiki kendaraan pada item tersebut, lalu ajukan ulang checklist agar EKIM dapat disetujui.';
    createEkimNotifikasi($formulir['nomor_polisi'], $formulir_id, 'blocked', $msg);
}

try {
    $db = Database::getInstance()->getConnection();

    // Ambil formulir
    $stmt = $db->prepare("SELECT * FROM formulir_checklist WHERE id = ? LIMIT 1");
    $stmt->execute([$formulir_id]);
    $formulir = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$formulir) {
        jsonResponse(false, 'Formulir tidak ditemukan');
    }

    $cur_status = $formulir['status_approval'];
    $stored_hash = $formulir['dokumen_hash'];

    // ════════════════════════════════════════════════════════
    //  SUBMIT — Kunci dokumen dan hitung hash kanonik
    // ════════════════════════════════════════════════════════
    if ($action === 'submit') {
        if ($cur_status !== 'draft') {
            jsonResponse(false, "Formulir sudah dalam status '{$cur_status}', tidak dapat di-submit ulang");
        }

        $hash      = computeDocumentHash($formulir_id, $db);
        if (!$hash) jsonResponse(false, 'Gagal menghitung hash dokumen');

        $stmt = $db->prepare("
            UPDATE formulir_checklist
            SET status_approval = 'pending_hsse',
                dokumen_hash    = ?
            WHERE id = ?
        ");
        $stmt->execute([$hash, $formulir_id]);

        logSignatureAction($formulir_id, 'SUBMIT', $user, $hash, null);
        logAudit($formulir_id, 'UPDATE', $user['username'],
            "Formulir dikunci & disubmit ke antrian tanda tangan HSSE oleh {$user['full_name']}");

        notifyIfTidakBaikFound($db, $formulir, $formulir_id);

        jsonResponse(true, 'Formulir berhasil dikunci. Data tidak dapat diubah lagi. Menunggu tanda tangan HSSE.', [
            'new_status' => 'pending_hsse',
            'hash'       => $hash,
        ]);
    }

    // ════════════════════════════════════════════════════════
    //  SIGN_HSSE — Tanda tangan pertama oleh HSSE
    // ════════════════════════════════════════════════════════
    if ($action === 'sign_hsse') {
        if (!in_array($cur_status, ['draft', 'pending_hsse'], true)) {
            jsonResponse(false, "Status harus 'Draft' atau 'Pending HSSE' untuk ditandatangani. Status saat ini: '{$cur_status}'");
        }

        // Formulir masih Draft: kunci & hitung hash dulu (gabungan submit+sign
        // supaya HSSE bisa langsung tanda tangan tanpa langkah terpisah).
        if ($cur_status === 'draft') {
            $stored_hash = computeDocumentHash($formulir_id, $db);
            if (!$stored_hash) jsonResponse(false, 'Gagal menghitung hash dokumen');
            $db->prepare("UPDATE formulir_checklist SET dokumen_hash = ? WHERE id = ?")
               ->execute([$stored_hash, $formulir_id]);
            logAudit($formulir_id, 'UPDATE', $user['username'],
                "Formulir dikunci & disubmit ke antrian tanda tangan HSSE oleh {$user['full_name']}");

            notifyIfTidakBaikFound($db, $formulir, $formulir_id);
        }

        // Verifikasi integritas dokumen
        $live_hash = computeDocumentHash($formulir_id, $db);
        if ($live_hash !== $stored_hash) {
            jsonResponse(false,
                'PERINGATAN KEAMANAN: Integritas dokumen gagal! ' .
                'Isi dokumen telah berubah setelah disubmit. Proses penandatanganan ditolak.',
                ['detail' => 'Hash tidak cocok. Laporkan ke Administrator.']
            );
        }

        // Load kunci privat HSSE
        $priv_pem = getPrivateKey('hsse');
        if (!$priv_pem) {
            jsonResponse(false,
                'Kunci privat HSSE belum dikonfigurasi. ' .
                'Silakan buka menu Pengaturan Sistem → Kelola Kunci RSA dan generate key pairs terlebih dahulu.'
            );
        }

        $sig = signData($stored_hash, $priv_pem);
        if (!$sig) {
            jsonResponse(false, 'Gagal membuat tanda tangan digital. Periksa konfigurasi OpenSSL server.');
        }

        $stmt = $db->prepare("
            UPDATE formulir_checklist
            SET status_approval    = 'signed_hsse',
                ttd_hsse_user_id   = ?,
                ttd_hsse_nama      = ?,
                ttd_hsse_signature = ?,
                ttd_hsse_hash      = ?,
                ttd_hsse_timestamp = NOW()
            WHERE id = ?
        ");
        $signer_name = $user['full_name'] ?: $user['username'];
        $stmt->execute([$user['id'], $signer_name, $sig, $stored_hash, $formulir_id]);

        logSignatureAction($formulir_id, 'SIGN_HSSE', $user, $stored_hash, $sig);
        logAudit($formulir_id, 'UPDATE', $user['username'],
            "TTD Digital HSSE dibubuhkan oleh {$signer_name}");

        // Buat QR Code verifikasi AWAL agar bisa langsung tampil setelah TTD HSSE
        // (belum final — hash SHA-512 & signature final baru dibuat saat Manajer approve).
        $prelim_qr = createPreliminaryVerificationQr($formulir_id, $db, $formulir['verification_uuid'] ?? null);

        jsonResponse(true,
            "Tanda tangan digital HSSE berhasil dibubuhkan oleh {$signer_name}. " .
            "Formulir sekarang menunggu tanda tangan Manajer HSSE.",
            [
                'new_status' => 'signed_hsse',
                'signer' => $signer_name,
                'nama' => $signer_name,
                'timestamp' => date('d/m/Y H:i:s'),
                'waktu_display' => date('d/m/Y H:i:s'),
                'hash_preview' => substr($stored_hash, 0, 16),
                'verification_url'        => $prelim_qr['url'] ?? null,
                'verification_qrcode_url' => $prelim_qr ? getVerificationQrPublicUrl($prelim_qr['qrcode_path']) : null,
                'formulir_id'    => $formulir_id,
                'jenis_kendaraan' => $formulir['jenis_kendaraan'] ?? 'SPBU',
            ]
        );
    }

    // ════════════════════════════════════════════════════════
    //  SIGN_MANAJER — Tanda tangan kedua oleh Manajer HSSE
    // ════════════════════════════════════════════════════════
    if ($action === 'sign_manajer') {
        if ($cur_status !== 'signed_hsse') {
            jsonResponse(false,
                "Formulir harus sudah ditandatangani HSSE terlebih dahulu. Status saat ini: '{$cur_status}'"
            );
        }

        // ── HARD BLOCK: EKIM tidak boleh diterbitkan jika ada item checklist
        // yang berstatus TIDAK BAIK. Kendaraan harus diperbaiki & checklist
        // diperbarui (reset ke Draft) terlebih dahulu sebelum Manajer dapat
        // menyetujui/menerbitkan EKIM. Dicek di server agar tidak bisa
        // dilewati lewat manipulasi request langsung ke endpoint ini.
        $tidakStmt = $db->prepare("
            SELECT item_name FROM checklist_items
            WHERE formulir_id = ? AND is_tidak = 1
            ORDER BY item_number
        ");
        $tidakStmt->execute([$formulir_id]);
        $tidakItems = $tidakStmt->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($tidakItems)) {
            $blockMsg = 'EKIM tidak dapat diterbitkan: terdapat ' . count($tidakItems) .
                ' item pemeriksaan berstatus TIDAK BAIK — ' . implode('; ', $tidakItems) . '. ' .
                'Perbaiki kendaraan pada item tersebut, perbarui checklist (Admin dapat mereset ke Draft), ' .
                'lalu ajukan ulang sebelum EKIM dapat disetujui Manajer.';
            if (!empty($formulir['nomor_polisi'])) {
                createEkimNotifikasi($formulir['nomor_polisi'], $formulir_id, 'blocked', $blockMsg);
            }
            jsonResponse(false, $blockMsg, ['tidak_items' => $tidakItems]);
        }

        // Verifikasi integritas dokumen
        $live_hash = computeDocumentHash($formulir_id, $db);
        if ($live_hash !== $stored_hash) {
            jsonResponse(false,
                'PERINGATAN KEAMANAN: Integritas dokumen gagal sebelum penandatanganan Manajer! ' .
                'Data berubah setelah tanda tangan HSSE. Formulir harus disubmit ulang dari awal.'
            );
        }

        // Verifikasi ulang tanda tangan HSSE
        $hsse_pub = getPublicKey('hsse');
        if ($hsse_pub && $formulir['ttd_hsse_signature']) {
            if (!verifySignature($stored_hash, $formulir['ttd_hsse_signature'], $hsse_pub)) {
                jsonResponse(false,
                    'PERINGATAN KEAMANAN: Verifikasi tanda tangan HSSE gagal! ' .
                    'Tanda tangan HSSE tidak valid. Proses penandatanganan Manajer ditolak.'
                );
            }
        }

        // Load kunci privat Manajer
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
                ttd_manajer_signature = ?,
                ttd_manajer_hash      = ?,
                ttd_manajer_timestamp = NOW(),
                verification_uuid     = ?,
                verification_hash_sha512 = ?,
                verification_signature = ?,
                verification_url      = ?,
                verification_qrcode_path = ?,
                verification_created_at = NOW()
            WHERE id = ?
        ");
        $signer_name = $user['full_name'] ?: $user['username'];
        $stmt->execute([
            $user['id'], $signer_name, $sig, $stored_hash,
            $verification_proof['uuid'], $verification_proof['hash'], $verification_proof['signature'],
            $verification_proof['url'], $verification_proof['qrcode_path'],
            $formulir_id,
        ]);
        $db->commit();

        logSignatureAction($formulir_id, 'SIGN_MANAJER', $user, $stored_hash, $sig);
        logAudit($formulir_id, 'UPDATE', $user['username'],
            "TTD Digital Manajer HSSE dibubuhkan oleh {$signer_name}. Formulir DISETUJUI.");

        if (!empty($formulir['nomor_polisi'])) {
            createEkimNotifikasi(
                $formulir['nomor_polisi'],
                $formulir_id,
                'issued',
                "EKIM kendaraan {$formulir['nomor_polisi']} telah DITERBITKAN. Disetujui secara digital oleh {$signer_name}."
            );
        }

        jsonResponse(true,
            "Tanda tangan Manajer berhasil dibubuhkan! Formulir checklist telah DISETUJUI secara digital oleh {$signer_name}.",
            [
                'new_status' => 'approved',
                'signer' => $signer_name,
                'nama' => $signer_name,
                'timestamp' => date('d/m/Y H:i:s'),
                'waktu_display' => date('d/m/Y H:i:s'),
                'hash_preview' => substr($stored_hash, 0, 16),
                'verification_url' => $verification_proof['url'],
                'verification_qrcode_url' => getVerificationQrPublicUrl($verification_proof['qrcode_path']),
                'formulir_id' => $formulir_id,
                'jenis_kendaraan' => $formulir['jenis_kendaraan'] ?? 'SPBU',
            ]
        );
    }

    // ════════════════════════════════════════════════════════
    //  REJECT — Tolak formulir
    // ════════════════════════════════════════════════════════
    if ($action === 'reject') {
        if (!in_array($cur_status, ['pending_hsse', 'signed_hsse'], true)) {
            jsonResponse(false, "Formulir dengan status '{$cur_status}' tidak dapat ditolak");
        }

        $notes = trim($input['notes'] ?? '');

        $stmt = $db->prepare("UPDATE formulir_checklist SET status_approval = 'rejected' WHERE id = ?");
        $stmt->execute([$formulir_id]);

        logSignatureAction($formulir_id, 'REJECT', $user, $stored_hash, null, $notes);
        logAudit($formulir_id, 'UPDATE', $user['username'],
            "Formulir ditolak oleh {$user['full_name']}. Alasan: {$notes}");

        if (!empty($formulir['nomor_polisi'])) {
            createEkimNotifikasi(
                $formulir['nomor_polisi'],
                $formulir_id,
                'rejected',
                "EKIM kendaraan {$formulir['nomor_polisi']} DITOLAK oleh {$user['full_name']}. Alasan: {$notes}"
            );
        }

        jsonResponse(true,
            'Formulir telah ditolak. Admin dapat me-reset ke Draft jika diperlukan perbaikan.',
            ['new_status' => 'rejected']
        );
    }

    // ════════════════════════════════════════════════════════
    //  RESET_DRAFT — Hapus semua tanda tangan, kembali ke draft
    // ════════════════════════════════════════════════════════
    if ($action === 'reset_draft') {
        if ($cur_status === 'approved') {
            jsonResponse(false, 'Formulir yang sudah disetujui tidak dapat di-reset ke Draft');
        }

        $stmt = $db->prepare("
            UPDATE formulir_checklist
            SET status_approval       = 'draft',
                dokumen_hash          = NULL,
                ttd_hsse_user_id      = NULL,
                ttd_hsse_nama         = NULL,
                ttd_hsse_signature    = NULL,
                ttd_hsse_timestamp    = NULL,
                ttd_manajer_user_id   = NULL,
                ttd_manajer_nama      = NULL,
                ttd_manajer_signature = NULL,
                ttd_manajer_timestamp = NULL,
                verification_uuid     = NULL,
                verification_hash_sha512 = NULL,
                verification_signature = NULL,
                verification_url      = NULL,
                verification_created_at = NULL
            WHERE id = ?
        ");
        $stmt->execute([$formulir_id]);

        logSignatureAction($formulir_id, 'RESET_DRAFT', $user, null, null,
            'Reset ke Draft oleh Admin — seluruh tanda tangan dihapus');
        logAudit($formulir_id, 'UPDATE', $user['username'],
            "Formulir di-reset ke Draft. Semua tanda tangan dihapus.");

        jsonResponse(true,
            'Formulir berhasil di-reset ke Draft. Data dapat diubah dan disubmit ulang.',
            ['new_status' => 'draft']
        );
    }

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    if ($generated_qrcode_path) {
        @unlink(__DIR__ . '/storage/app/public/' . $generated_qrcode_path);
    }
    error_log("sign-checklist.php Error [{$action}|{$formulir_id}]: " . $e->getMessage());
    jsonResponse(false, 'Terjadi kesalahan sistem. Silakan coba lagi atau hubungi administrator.');
}
