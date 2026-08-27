<?php
/**
 * Kelola Kunci RSA — Admin Tool
 * Generate dan kelola RSA-2048 key pairs untuk tanda tangan digital
 * PRIMA (Pertamina Checklist Mobil Tangki)
 */

require_once '../auth/auth.php';
requireAdmin();
require_once '../config/config.php';

$user = getCurrentUser();
$message = '';
$message_type = '';

// Fungsi bantu: baca status kunci
function getKeyStatus(string $role): array {
    $priv = RSA_KEY_DIR . $role . '_private.pem';
    $pub  = RSA_KEY_DIR . $role . '_public.pem';
    $priv_ok = is_readable($priv);
    $pub_ok  = is_readable($pub);

    $fingerprint = null;
    if ($pub_ok) {
        $pem = file_get_contents($pub);
        $key = openssl_pkey_get_public($pem);
        if ($key) {
            $det = openssl_pkey_get_details($key);
            $fingerprint = strtoupper(
                implode(':', str_split(hash('sha256', $det['key']), 4))
            );
        }
    }

    $created_at = null;
    if ($priv_ok) {
        $created_at = date('d/m/Y H:i:s', filemtime($priv));
    }

    return [
        'priv_ok'     => $priv_ok,
        'pub_ok'      => $pub_ok,
        'fingerprint' => $fingerprint,
        'created_at'  => $created_at,
        'bits'        => 2048,
    ];
}

// Proses generate keys
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_role'])) {
    $role = $_POST['generate_role'];
    $allowed_roles = ['hsse', 'manager_hsse'];

    if (!in_array($role, $allowed_roles, true)) {
        $message = 'Role tidak valid.';
        $message_type = 'error';
    } else {
        // Konfirmasi: jika sudah ada kunci, pastikan ada flag konfirmasi
        $priv_path = RSA_KEY_DIR . $role . '_private.pem';
        if (file_exists($priv_path) && empty($_POST['confirm_regenerate'])) {
            $message = "Kunci untuk role '{$role}' sudah ada. Centang konfirmasi untuk menimpa.";
            $message_type = 'warning';
        } else {
            // Generate RSA-2048 key pair
            $config = [
                'digest_alg'       => 'sha256',
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ];
            $res = openssl_pkey_new($config);
            if (!$res) {
                $message = 'Gagal membuat kunci RSA. Pastikan OpenSSL tersedia di server.';
                $message_type = 'error';
            } else {
                openssl_pkey_export($res, $private_pem);
                $details    = openssl_pkey_get_details($res);
                $public_pem = $details['key'];

                // Pastikan direktori bisa ditulis
                if (!is_dir(RSA_KEY_DIR)) {
                    mkdir(RSA_KEY_DIR, 0700, true);
                }

                file_put_contents($priv_path, $private_pem, LOCK_EX);
                file_put_contents(RSA_KEY_DIR . $role . '_public.pem', $public_pem, LOCK_EX);
                chmod($priv_path, 0600); // hanya owner yang bisa baca

                // Catat di audit log
                logAudit(null, 'UPDATE', $user['username'],
                    "RSA-2048 key pair di-generate untuk role: {$role}");

                // Jika ada formulir yang sudah signed dengan kunci lama, tandai mereka
                // (kunci baru = signature lama tidak valid → warning)
                $had_old = !empty($_POST['confirm_regenerate']);

                $message = "Kunci RSA-2048 untuk role '{$role}' berhasil di-generate!"
                    . ($had_old ? " PERHATIAN: Tanda tangan sebelumnya yang menggunakan kunci lama tidak akan dapat diverifikasi." : '');
                $message_type = $had_old ? 'warning' : 'success';
            }
        }
    }
}

$hsse_status    = getKeyStatus('hsse');
$manager_status = getKeyStatus('manager_hsse');

// Cek apakah ada formulir yang sudah signed tapi kunci diperbarui (signature akan invalid)
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT COUNT(*) FROM formulir_checklist WHERE status_approval IN ('signed_hsse','approved')");
    $signed_count = (int)$stmt->fetchColumn();
} catch (Exception $e) {
    $signed_count = 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Kunci RSA — Admin</title>
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        body { background: #eef2f7; font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; }
        .page-wrap { max-width: 960px; margin: 0 auto; padding: 28px 20px; }
        .page-header { background: #10334d; color: white; padding: 24px 30px; border-radius: 16px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; }
        .page-header h1 { margin: 0; font-size: 22px; }
        .page-header .back-link { color: #a7c8dc; text-decoration: none; font-size: 14px; }
        .page-header .back-link:hover { color: white; }
        .card { background: white; border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,.08); padding: 28px; margin-bottom: 20px; }
        .card h2 { margin: 0 0 6px; font-size: 17px; color: #1a2332; }
        .card .sub { color: #6b7280; font-size: 13px; margin-bottom: 20px; }
        .key-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
        @media(max-width:640px){ .key-grid{ grid-template-columns:1fr; } }
        .key-card { border: 1.5px solid #e5e7eb; border-radius: 12px; padding: 20px; }
        .key-card .role-badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 700; background: #e8f4fd; color: #1565c0; margin-bottom: 12px; }
        .key-card .role-badge.manager { background: #fef3c7; color: #92400e; }
        .key-status { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; font-size: 14px; font-weight: 600; }
        .dot-ok { width: 10px; height: 10px; border-radius: 50%; background: #22c55e; flex-shrink: 0; }
        .dot-err { width: 10px; height: 10px; border-radius: 50%; background: #ef4444; flex-shrink: 0; }
        .key-meta { font-size: 12px; color: #6b7280; line-height: 2; }
        .key-meta strong { color: #374151; }
        .fingerprint { font-family: monospace; font-size: 10px; background: #f3f4f6; padding: 6px 8px; border-radius: 6px; word-break: break-all; margin-top: 8px; color: #374151; }
        .btn-gen { display: inline-flex; align-items: center; gap: 6px; margin-top: 14px; padding: 9px 18px; background: #10334d; color: white; border: none; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; transition: background .2s; }
        .btn-gen:hover { background: #1a4a6e; }
        .btn-gen.danger { background: #dc2626; }
        .btn-gen.danger:hover { background: #b91c1c; }
        .confirm-row { margin-top: 10px; font-size: 13px; color: #dc2626; display: flex; align-items: center; gap: 6px; }
        .alert { padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; font-weight: 500; }
        .alert-success { background: #dcfce7; color: #166534; border-left: 4px solid #22c55e; }
        .alert-warning { background: #fef9c3; color: #854d0e; border-left: 4px solid #eab308; }
        .alert-error   { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }
        .info-box { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px; padding: 16px 18px; font-size: 13px; color: #1e40af; line-height: 1.8; }
        .info-box ul { margin: 8px 0 0 16px; padding: 0; }
        .section-title { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: .8px; color: #9ca3af; margin: 24px 0 12px; }
    </style>
</head>
<body>
<div class="page-wrap">
    <div class="page-header">
        <div>
            <h1>Kelola Kunci RSA Digital Signature</h1>
            <div style="color:#a7c8dc;font-size:13px;margin-top:4px;">RSA-2048 · SHA-256 · PKCS#1 v1.5</div>
        </div>
        <a href="../config/system-settings.php" class="back-link">← Kembali ke Pengaturan</a>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $message_type ?>">
        <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>

    <div class="info-box" style="margin-bottom:20px;">
        <strong>Cara Kerja Tanda Tangan Digital:</strong>
        <ul>
            <li>Setiap role (HSSE dan Manajer HSSE) memiliki pasangan kunci RSA-2048 unik.</li>
            <li><strong>Kunci Privat</strong> — disimpan aman di server, digunakan untuk membuat tanda tangan.</li>
            <li><strong>Kunci Publik</strong> — digunakan untuk memverifikasi keaslian tanda tangan.</li>
            <li>Kunci hanya perlu di-generate <strong>satu kali</strong>. Jangan regenerate kecuali kunci bocor.</li>
            <?php if ($signed_count > 0): ?>
            <li style="color:#dc2626;font-weight:600;">Ada <strong><?= $signed_count ?></strong> formulir yang sudah ditandatangani. Meregen­erate kunci akan membuat tanda tangan tersebut tidak dapat diverifikasi.</li>
            <?php endif; ?>
        </ul>
    </div>

    <div class="card">
        <h2>Status Kunci RSA</h2>
        <p class="sub">Kunci disimpan di direktori <code>rsa-keys/</code> yang dilindungi dari akses publik.</p>

        <div class="key-grid">
            <!-- HSSE Key Card -->
            <div class="key-card">
                <div class="role-badge">Tim HSSE</div>
                <div class="key-status">
                    <?php if ($hsse_status['priv_ok'] && $hsse_status['pub_ok']): ?>
                        <span class="dot-ok"></span> Kunci Aktif
                    <?php else: ?>
                        <span class="dot-err"></span> Belum Ada Kunci
                    <?php endif; ?>
                </div>
                <div class="key-meta">
                    <strong>Algoritma:</strong> RSA-2048 / SHA-256<br>
                    <strong>Private Key:</strong> <?= $hsse_status['priv_ok'] ? '✓ Ada' : '✗ Belum ada' ?><br>
                    <strong>Public Key:</strong>  <?= $hsse_status['pub_ok']  ? '✓ Ada' : '✗ Belum ada' ?><br>
                    <?php if ($hsse_status['created_at']): ?>
                    <strong>Dibuat:</strong> <?= $hsse_status['created_at'] ?>
                    <?php endif; ?>
                </div>
                <?php if ($hsse_status['fingerprint']): ?>
                <div class="fingerprint">SHA-256 Fingerprint:<br><?= $hsse_status['fingerprint'] ?></div>
                <?php endif; ?>
                <form method="POST">
                    <input type="hidden" name="generate_role" value="hsse">
                    <?php if ($hsse_status['priv_ok']): ?>
                        <div class="confirm-row">
                            <input type="checkbox" name="confirm_regenerate" id="conf_hsse" value="1">
                            <label for="conf_hsse">Saya mengerti regenerate akan membatalkan tanda tangan lama</label>
                        </div>
                        <button type="submit" class="btn-gen danger">Regenerate Kunci HSSE</button>
                    <?php else: ?>
                        <button type="submit" class="btn-gen">Generate Kunci HSSE</button>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Manager Key Card -->
            <div class="key-card">
                <div class="role-badge manager">Manajer HSSE</div>
                <div class="key-status">
                    <?php if ($manager_status['priv_ok'] && $manager_status['pub_ok']): ?>
                        <span class="dot-ok"></span> Kunci Aktif
                    <?php else: ?>
                        <span class="dot-err"></span> Belum Ada Kunci
                    <?php endif; ?>
                </div>
                <div class="key-meta">
                    <strong>Algoritma:</strong> RSA-2048 / SHA-256<br>
                    <strong>Private Key:</strong> <?= $manager_status['priv_ok'] ? '✓ Ada' : '✗ Belum ada' ?><br>
                    <strong>Public Key:</strong>  <?= $manager_status['pub_ok']  ? '✓ Ada' : '✗ Belum ada' ?><br>
                    <?php if ($manager_status['created_at']): ?>
                    <strong>Dibuat:</strong> <?= $manager_status['created_at'] ?>
                    <?php endif; ?>
                </div>
                <?php if ($manager_status['fingerprint']): ?>
                <div class="fingerprint">SHA-256 Fingerprint:<br><?= $manager_status['fingerprint'] ?></div>
                <?php endif; ?>
                <form method="POST">
                    <input type="hidden" name="generate_role" value="manager_hsse">
                    <?php if ($manager_status['priv_ok']): ?>
                        <div class="confirm-row">
                            <input type="checkbox" name="confirm_regenerate" id="conf_mgr" value="1">
                            <label for="conf_mgr">Saya mengerti regenerate akan membatalkan tanda tangan lama</label>
                        </div>
                        <button type="submit" class="btn-gen danger">Regenerate Kunci Manajer</button>
                    <?php else: ?>
                        <button type="submit" class="btn-gen">Generate Kunci Manajer</button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <div class="card">
        <h2>Log Aktivitas Tanda Tangan Digital</h2>
        <p class="sub">50 aktivitas terbaru</p>
        <?php
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->query("
                SELECT dsl.*, fc.nomor_polisi
                FROM digital_signature_log dsl
                LEFT JOIN formulir_checklist fc ON dsl.formulir_id = fc.id
                ORDER BY dsl.created_at DESC
                LIMIT 50
            ");
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $logs = [];
        }

        $action_labels = [
            'SUBMIT'      => ['label' => 'Submit',          'color' => '#2563eb'],
            'SIGN_HSSE'   => ['label' => 'TTD HSSE',        'color' => '#059669'],
            'SIGN_MANAJER'=> ['label' => 'TTD Manajer',     'color' => '#7c3aed'],
            'VERIFY'      => ['label' => 'Verifikasi',      'color' => '#0891b2'],
            'REJECT'      => ['label' => 'Ditolak',         'color' => '#dc2626'],
            'RESET_DRAFT' => ['label' => 'Reset Draft',     'color' => '#d97706'],
        ];
        ?>
        <?php if (empty($logs)): ?>
        <p style="color:#9ca3af;font-style:italic;">Belum ada aktivitas tanda tangan.</p>
        <?php else: ?>
        <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="background:#f9fafb;border-bottom:2px solid #e5e7eb;">
                    <th style="padding:10px 12px;text-align:left;color:#6b7280;font-weight:600;">Waktu</th>
                    <th style="padding:10px 12px;text-align:left;color:#6b7280;font-weight:600;">Formulir</th>
                    <th style="padding:10px 12px;text-align:left;color:#6b7280;font-weight:600;">Aksi</th>
                    <th style="padding:10px 12px;text-align:left;color:#6b7280;font-weight:600;">Oleh</th>
                    <th style="padding:10px 12px;text-align:left;color:#6b7280;font-weight:600;">IP Address</th>
                    <th style="padding:10px 12px;text-align:left;color:#6b7280;font-weight:600;">Hash (awal)</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($logs as $log):
                $al = $action_labels[$log['action']] ?? ['label' => $log['action'], 'color' => '#6b7280'];
            ?>
            <tr style="border-bottom:1px solid #f3f4f6;">
                <td style="padding:9px 12px;color:#6b7280;"><?= date('d/m/Y H:i', strtotime($log['created_at'])) ?></td>
                <td style="padding:9px 12px;font-weight:600;"><?= htmlspecialchars($log['nomor_polisi'] ?? "ID:{$log['formulir_id']}") ?></td>
                <td style="padding:9px 12px;">
                    <span style="background:<?= $al['color'] ?>20;color:<?= $al['color'] ?>;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:700;">
                        <?= $al['label'] ?>
                    </span>
                </td>
                <td style="padding:9px 12px;"><?= htmlspecialchars($log['user_name'] ?? '-') ?></td>
                <td style="padding:9px 12px;font-family:monospace;font-size:11px;color:#6b7280;"><?= htmlspecialchars($log['ip_address'] ?? '-') ?></td>
                <td style="padding:9px 12px;font-family:monospace;font-size:10px;color:#9ca3af;"><?= $log['dokumen_hash'] ? substr($log['dokumen_hash'], 0, 16) . '…' : '-' ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
