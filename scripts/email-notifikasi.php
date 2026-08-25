<?php
/**
 * Notifikasi Email KIM — PRIMA PT Pertamina Patra Niaga
 * Mengirim email ke pengurus/kontraktor mobil tangki ketika KIM akan atau sudah habis masa berlaku.
 *
 * MODE OTOMATIS (berjalan sendiri, tanpa login & tanpa klik manual):
 *  - Windows Task Scheduler / CLI : php email-notifikasi.php --cron
 *  - Cron job hosting via URL     : email-notifikasi.php?cron_key=<secret, lihat kartu "Notifikasi Otomatis">
 * Surat/dokumen yang bermasalah (kadaluarsa, ditolak admin, atau belum diupload) selalu
 * ditentukan OTOMATIS berdasarkan tanggal masa berlaku masing-masing dokumen — lihat
 * getSuratBermasalah() di config.php. Tidak perlu dipilih manual pada mode otomatis ini.
 */
require_once '../config/config.php';

// ─── MODE CRON (tanpa login, dipicu scheduler/otomatis) ──────────────────────
$is_cli_cron = (PHP_SAPI === 'cli') && in_array('--cron', $argv ?? [], true);
$is_web_cron = !$is_cli_cron && !empty($_GET['cron_key']) && hash_equals(getCronSecret(), (string)$_GET['cron_key']);

if ($is_cli_cron || $is_web_cron) {
    if (!$is_cli_cron) header('Content-Type: text/plain; charset=utf-8');
    ensureNotifTablesExist();
    $result = runKimAutoNotifications(null);
    $doc_result = runDocumentExpiryNotifications();
    setSystemSetting('cron_last_run', date('Y-m-d H:i:s'));
    echo "[" . date('Y-m-d H:i:s') . "] Auto-notifikasi KIM selesai.\n";
    echo "Terkirim   : {$result['sent']}\n";
    echo "Dilewati   : {$result['skip']} (sudah dinotifikasi dalam 7 hari terakhir)\n";
    echo "Tanpa email: {$result['no_email']}\n";
    echo "Gagal      : {$result['fail']}\n";
    echo "\n[" . date('Y-m-d H:i:s') . "] Auto-notifikasi Dokumen (STNK/Pajak/SIMFIT/Tera/Keur) selesai.\n";
    echo "Terkirim   : {$doc_result['sent']}\n";
    echo "Dilewati   : {$doc_result['skip']} (sudah dinotifikasi dalam 7 hari terakhir)\n";
    echo "Tanpa email: {$doc_result['no_email']}\n";
    echo "Gagal      : {$doc_result['fail']}\n";
    exit(0);
}

require_once '../auth/auth.php';
requireAdmin();

$user = getCurrentUser();
ensureNotifTablesExist();

// ─── SMTP MAILER ─────────────────────────────────────────────────────────────
class KimMailer {
    private $cfg;
    public function __construct($cfg) { $this->cfg = $cfg; }

    private function getSocket() {
        $host = $this->cfg['smtp_host'];
        $port = (int)($this->cfg['smtp_port'] ?? 587);
        $enc  = $this->cfg['smtp_encryption'] ?? 'tls';
        $timeout = 20;

        if ($enc === 'ssl') {
            $sock = @stream_socket_client("ssl://{$host}:{$port}", $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT,
                stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]));
        } else {
            $sock = @fsockopen($host, $port, $errno, $errstr, $timeout);
        }
        if (!$sock) throw new Exception("Tidak dapat terhubung ke SMTP {$host}:{$port} — {$errstr} ({$errno})");
        stream_set_timeout($sock, $timeout);
        return $sock;
    }

    private function read($sock) {
        $res = '';
        while ($line = fgets($sock, 515)) {
            $res .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $res;
    }

    private function cmd($sock, $cmd, $expect) {
        fwrite($sock, $cmd . "\r\n");
        $res = $this->read($sock);
        if (substr($res, 0, 3) !== (string)$expect) {
            throw new Exception("SMTP error setelah '{$cmd}': " . trim($res));
        }
        return $res;
    }

    public function send($to_email, $to_name, $subject, $html) {
        $cfg  = $this->cfg;
        $enc  = $cfg['smtp_encryption'] ?? 'tls';
        $sock = $this->getSocket();
        $this->read($sock); // greeting

        $local = gethostname() ?: 'localhost';
        $this->cmd($sock, "EHLO {$local}", 250);

        if ($enc === 'tls') {
            $this->cmd($sock, "STARTTLS", 220);
            stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $this->cmd($sock, "EHLO {$local}", 250);
        }

        if (!empty($cfg['smtp_username'])) {
            $this->cmd($sock, "AUTH LOGIN", 334);
            $this->cmd($sock, base64_encode($cfg['smtp_username']), 334);
            $this->cmd($sock, base64_encode($cfg['smtp_password']), 235);
        }

        $from  = $cfg['smtp_from_email'];
        $fname = $cfg['smtp_from_name'] ?? 'PRIMA KIM Pertamina';

        $this->cmd($sock, "MAIL FROM:<{$from}>", 250);
        $this->cmd($sock, "RCPT TO:<{$to_email}>", 250);
        $this->cmd($sock, "DATA", 354);

        $plain    = wordwrap(html_entity_decode(strip_tags(str_replace(['<br>','<br/>','<br />','</p>'], "\n", $html)), ENT_QUOTES, 'UTF-8'), 76, "\n", true);
        $bound    = 'kim_' . md5(uniqid('', true));
        $msgid    = uniqid('kim') . '@pertamina-prima.local';

        $msg  = "From: =?UTF-8?B?" . base64_encode($fname) . "?= <{$from}>\r\n";
        $msg .= "To: =?UTF-8?B?" . base64_encode($to_name ?: $to_email) . "?= <{$to_email}>\r\n";
        $msg .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $msg .= "MIME-Version: 1.0\r\n";
        $msg .= "Content-Type: multipart/alternative; boundary=\"{$bound}\"\r\n";
        $msg .= "Date: " . date('r') . "\r\n";
        $msg .= "Message-ID: <{$msgid}>\r\n";
        $msg .= "X-Mailer: PRIMA-KIM-Pertamina/1.0\r\n";
        $msg .= "\r\n";
        $msg .= "--{$bound}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
        $msg .= chunk_split(base64_encode($plain)) . "\r\n";
        $msg .= "--{$bound}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
        $msg .= chunk_split(base64_encode($html)) . "\r\n";
        $msg .= "--{$bound}--\r\n.\r\n";

        fwrite($sock, $msg);
        $res = $this->read($sock);
        if (substr($res, 0, 3) !== '250') throw new Exception("Pesan ditolak server: " . trim($res));
        fwrite($sock, "QUIT\r\n");
        fclose($sock);
        return true;
    }
}

// ─── EMAIL TEMPLATE ──────────────────────────────────────────────────────────
// $selected_surat_jenis: null = sertakan semua surat bermasalah yang terdeteksi otomatis (default/cron).
//                        array = hanya sertakan surat dengan 'jenis' yang ada di dalam array ini (dipilih manual oleh admin).
function buildKimEmail($v, $app_url = '', $selected_surat_jenis = null) {
    $sisa       = (int)$v['hari_tersisa'];
    $expired    = $v['status_alert'] === 'SUDAH_EXPIRED';
    $status_txt = $expired ? 'SUDAH HABIS' : "sisa {$sisa} hari";
    $accent     = $expired ? '#c0392b' : '#d97706';
    $tanggal    = !empty($v['ekim_valid_until']) ? date('d F Y', strtotime($v['ekim_valid_until'])) : '-';
    $nomor      = htmlspecialchars($v['nomor_polisi']);
    $merk       = htmlspecialchars($v['merk_mobil'] ?? '-');
    $transport  = htmlspecialchars($v['nama_transport'] ?? '-');
    $jenis      = htmlspecialchars(getJenisKendaraanLabel($v['jenis'] ?? 'SPBU'));

    $headline = $expired
        ? "KIM Mobil Tangki <strong>{$nomor}</strong> telah habis masa berlakunya."
        : "KIM Mobil Tangki <strong>{$nomor}</strong> akan habis dalam <strong>{$sisa} hari</strong>.";

    $body_text = $expired
        ? "Masa berlaku KIM sudah habis pada {$tanggal}."
        : "Masa berlaku KIM akan habis pada {$tanggal} (sisa {$sisa} hari).";

    // Ambil item checklist yang bermasalah (is_tidak = 1) dari formulir terbaru
    $items_tidak = [];
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT ci.item_name, ci.keterangan
            FROM checklist_items ci
            WHERE ci.formulir_id = :fid AND ci.is_tidak = 1
            ORDER BY ci.item_number ASC
        ");
        $stmt->execute([':fid' => (int)$v['id']]);
        $items_tidak = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Abaikan error DB, fallback ke teks generik
    }

    // Ambil surat/dokumen kendaraan yang sudah mati, ditolak, atau belum diupload
    $surat_bermasalah = getSuratBermasalah($v['nomor_polisi']);
    // Jika admin memilih manual surat mana yang bermasalah (lewat modal), batasi ke pilihan tersebut
    if (is_array($selected_surat_jenis)) {
        $surat_bermasalah = array_values(array_filter(
            $surat_bermasalah,
            fn($s) => in_array($s['jenis'], $selected_surat_jenis, true)
        ));
    }

    // Bangun blok "Surat yang Perlu Diurus Kembali" (ditampilkan terpisah agar jelas)
    $surat_html = '';
    if (!empty($surat_bermasalah)) {
        $surat_rows = '';
        foreach ($surat_bermasalah as $s) {
            $nm     = htmlspecialchars($s['label']);
            $reason = htmlspecialchars($s['reason']);
            $surat_rows .= "
                <tr>
                    <td style=\"padding:8px 14px;border-bottom:1px solid #fde68a;font-size:13px;font-weight:600;color:#0d1f35;\">{$nm}</td>
                    <td style=\"padding:8px 14px;border-bottom:1px solid #fde68a;font-size:12.5px;color:#92400e;\">{$reason}</td>
                </tr>";
        }
        $surat_html = "
      <table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"background:#fffbeb;border:1px solid #fde68a;border-left:4px solid #d97706;border-radius:4px;margin-bottom:24px;\">
        <tr><td style=\"padding:14px 18px 4px;\">
          <p style=\"margin:0 0 8px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.7px;color:#92400e;\">&#128220; Surat/Dokumen yang Harus Diurus &amp; Diupload Kembali</p>
        </td></tr>
        <tr><td style=\"padding:0 18px 14px;\">
          <table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\">
            <tr>
              <td style=\"padding:6px 14px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#92400e;border-bottom:1px solid #fde68a;\">Nama Surat</td>
              <td style=\"padding:6px 14px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#92400e;border-bottom:1px solid #fde68a;\">Status</td>
            </tr>
            {$surat_rows}
          </table>
          <p style=\"margin:10px 0 0;font-size:12.5px;color:#92400e;line-height:1.6;\">
            Mohon segera upload ulang surat di atas melalui menu <strong>Upload Dokumen</strong> pada Sistem PRIMA agar proses inspeksi dan perpanjangan KIM tidak terhambat.
          </p>
        </td></tr>
      </table>";
    }

    // Bangun baris tindakan berdasarkan item yang bermasalah
    if (!empty($items_tidak)) {
        $item_rows = '';
        foreach ($items_tidak as $item) {
            $nm  = htmlspecialchars($item['item_name']);
            $ket = !empty($item['keterangan']) ? ' <span style="color:#7a8ba0;font-size:12px;">(' . htmlspecialchars($item['keterangan']) . ')</span>' : '';
            $item_rows .= "<li style=\"margin-bottom:4px;\"><strong>{$nm}</strong>{$ket}</li>";
        }
        $action_html = "
            1. Perbarui dokumen/surat berikut yang <strong>tidak memenuhi syarat</strong>:<br>
            <ul style=\"margin:6px 0 8px 18px;padding:0;\">{$item_rows}</ul>
            2. Lakukan <strong>inspeksi checklist kendaraan</strong> ulang melalui Sistem PRIMA.<br>
            3. Ajukan perpanjangan KIM kepada administrator setelah inspeksi selesai.";
    } else {
        $action_html = "
            1. Lakukan <strong>inspeksi checklist kendaraan</strong> melalui Sistem PRIMA.<br>
            2. Ajukan perpanjangan KIM kepada administrator setelah inspeksi selesai.";
    }

    return <<<HTML
<!DOCTYPE html>
<html lang="id">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Pemberitahuan KIM</title></head>
<body style="margin:0;padding:0;background:#f1f4f8;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f4f8;padding:32px 16px;">
<tr><td align="center">
  <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:6px;overflow:hidden;border:1px solid #dde3ec;">
    <!-- Header -->
    <tr><td style="background:#0d1f35;padding:24px 32px;border-bottom:4px solid {$accent};">
      <p style="margin:0;font-size:11px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:rgba(255,255,255,0.5);">PT PERTAMINA PATRA NIAGA</p>
      <p style="margin:6px 0 0;font-size:18px;font-weight:700;color:#ffffff;letter-spacing:-0.3px;">PRIMA — Kartu Izin Masuk (KIM)</p>
    </td></tr>
    <!-- Alert banner -->
    <tr><td style="background:{$accent};padding:12px 32px;">
      <p style="margin:0;color:#fff;font-size:13px;font-weight:700;letter-spacing:0.5px;text-transform:uppercase;">
        &#9888; PEMBERITAHUAN MASA BERLAKU KIM &mdash; {$status_txt}
      </p>
    </td></tr>
    <!-- Body -->
    <tr><td style="padding:28px 32px;">
      <p style="margin:0 0 16px;font-size:15px;color:#1a2332;line-height:1.6;">{$headline}</p>
      <p style="margin:0 0 24px;font-size:13.5px;color:#4a5568;line-height:1.7;">
        Kartu Izin Masuk kendaraan berikut memerlukan tindak lanjut berupa <strong>inspeksi checklist kendaraan</strong> sesuai prosedur HSSE sebelum dapat beroperasi kembali di area PT Pertamina Patra Niaga.
      </p>
      <!-- Vehicle info table -->
      <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #dde3ec;border-radius:4px;overflow:hidden;margin-bottom:24px;">
        <tr style="background:#f8fafc;">
          <td style="padding:10px 16px;border-bottom:1px solid #dde3ec;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.7px;color:#7a8ba0;width:45%;">Nomor Polisi</td>
          <td style="padding:10px 16px;border-bottom:1px solid #dde3ec;font-size:13px;font-weight:700;color:#0d1f35;">{$nomor}</td>
        </tr>
        <tr>
          <td style="padding:10px 16px;border-bottom:1px solid #dde3ec;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.7px;color:#7a8ba0;">Nama Transport</td>
          <td style="padding:10px 16px;border-bottom:1px solid #dde3ec;font-size:13px;color:#1a2332;">{$transport}</td>
        </tr>
        <tr style="background:#f8fafc;">
          <td style="padding:10px 16px;border-bottom:1px solid #dde3ec;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.7px;color:#7a8ba0;">Merk Kendaraan</td>
          <td style="padding:10px 16px;border-bottom:1px solid #dde3ec;font-size:13px;color:#1a2332;">{$merk}</td>
        </tr>
        <tr>
          <td style="padding:10px 16px;border-bottom:1px solid #dde3ec;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.7px;color:#7a8ba0;">Jenis</td>
          <td style="padding:10px 16px;border-bottom:1px solid #dde3ec;font-size:13px;color:#1a2332;">{$jenis}</td>
        </tr>
        <tr style="background:#f8fafc;">
          <td style="padding:10px 16px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.7px;color:#7a8ba0;">KIM Berlaku Sampai</td>
          <td style="padding:10px 16px;font-size:13px;font-weight:700;color:{$accent};">{$tanggal}</td>
        </tr>
      </table>
      {$surat_html}
      <!-- Action box -->
      <table width="100%" cellpadding="0" cellspacing="0" style="background:#fef2f2;border:1px solid #fecaca;border-left:4px solid {$accent};border-radius:4px;margin-bottom:28px;">
        <tr><td style="padding:14px 18px;">
          <p style="margin:0 0 6px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.7px;color:{$accent};">Tindakan yang Diperlukan</p>
          <p style="margin:0;font-size:13px;color:#374151;line-height:1.8;">
            {$action_html}
          </p>
        </td></tr>
      </table>
      <p style="margin:0;font-size:12.5px;color:#7a8ba0;line-height:1.7;">
        Email ini dikirim secara otomatis oleh Sistem PRIMA PT Pertamina Patra Niaga.<br>
        Untuk informasi lebih lanjut, hubungi bagian HSSE setempat.
      </p>
    </td></tr>
    <!-- Footer -->
    <tr><td style="background:#f8fafc;padding:16px 32px;border-top:1px solid #dde3ec;">
      <p style="margin:0;font-size:11px;color:#b0bec8;text-align:center;">
        &copy; <?php echo date('Y'); ?> PT Pertamina Patra Niaga &mdash; PRIMA (Pertamina Checklist Mobil Tangki)<br>
        PRIMA | Dokumen ini bersifat resmi dan rahasia.
      </p>
    </td></tr>
  </table>
</td></tr>
</table>
</body></html>
HTML;
}

/**
 * Jalankan pengiriman notifikasi KIM untuk SEMUA kendaraan yang memenuhi ambang batas.
 * Surat/dokumen yang bermasalah dideteksi OTOMATIS oleh getSuratBermasalah() berdasarkan
 * tanggal masa berlaku masing-masing dokumen — tidak perlu dipilih manual di sini.
 * Dipanggil oleh mode cron (otomatis) — halaman ini tidak lagi memiliki tombol kirim manual.
 *
 * @param int|null $sent_by ID user yang memicu; null jika dipicu otomatis oleh scheduler/cron.
 * @return array{sent:int,skip:int,fail:int,no_email:int}
 */
function runKimAutoNotifications(?int $sent_by = null): array {
    $result = ['sent' => 0, 'skip' => 0, 'fail' => 0, 'no_email' => 0];
    $cfg    = getSmtpConfig();
    if (empty($cfg['smtp_username'])) {
        return $result;
    }
    $threshold = (int)($cfg['notif_days_threshold'] ?? 30);
    $alerts    = getVehicleAlerts($threshold + 30);
    $mailer    = new KimMailer($cfg);

    foreach ($alerts as $v) {
        if (empty($v['email_kontraktor'])) { $result['no_email']++; continue; }
        if (wasRecentlyNotified($v['nomor_polisi'], 7)) { $result['skip']++; continue; }
        try {
            $subject = 'Pemberitahuan: KIM Mobil Tangki ' . $v['nomor_polisi'] . ' — Perlu Inspeksi';
            $html    = buildKimEmail($v); // surat bermasalah otomatis, tanpa seleksi manual
            $mailer->send($v['email_kontraktor'], $v['nama_transport'] ?? $v['nomor_polisi'], $subject, $html);
            logKimNotification($v['nomor_polisi'], $v['nama_transport'] ?? '', $v['email_kontraktor'], $v['ekim_valid_until'], $v['hari_tersisa'], 'sent', null, $sent_by);
            $result['sent']++;
        } catch (Exception $e) {
            logKimNotification($v['nomor_polisi'], $v['nama_transport'] ?? '', $v['email_kontraktor'], $v['ekim_valid_until'], $v['hari_tersisa'], 'failed', $e->getMessage(), $sent_by);
            $result['fail']++;
        }
    }
    return $result;
}

/**
 * Bangun isi email untuk pemberitahuan dokumen (STNK/Pajak/SIMFIT/Tera/Keur)
 * yang akan atau sudah kadaluarsa, berdasarkan satu baris hasil getDocumentExpiryAlerts().
 */
function buildDocumentExpiryEmail($d) {
    $labels     = getDocumentExpiryItemLabels();
    $doc_label  = $labels[$d['item_name']] ?? $d['item_name'];
    $sisa       = (int)$d['hari_tersisa'];
    $expired    = $d['status_alert'] === 'SUDAH_EXPIRED';
    $status_txt = $expired ? 'SUDAH HABIS' : "sisa {$sisa} hari";
    $accent     = $expired ? '#c0392b' : '#d97706';
    $tanggal    = !empty($d['tanggal_expire']) ? date('d F Y', strtotime($d['tanggal_expire'])) : '-';
    $nomor      = htmlspecialchars($d['nomor_polisi']);
    $merk       = htmlspecialchars($d['merk_mobil'] ?? '-');
    $transport  = htmlspecialchars($d['nama_transport'] ?? '-');
    $jenis      = htmlspecialchars(getJenisKendaraanLabel($d['jenis_kendaraan'] ?? 'SPBU'));
    $doc_label_html = htmlspecialchars($doc_label);

    $headline = $expired
        ? "{$doc_label_html} untuk Mobil Tangki <strong>{$nomor}</strong> telah habis masa berlakunya."
        : "{$doc_label_html} untuk Mobil Tangki <strong>{$nomor}</strong> akan habis dalam <strong>{$sisa} hari</strong>.";

    $body_text = $expired
        ? "Masa berlaku {$doc_label_html} sudah habis pada {$tanggal}. Kendaraan ini <strong>tidak dapat beroperasi</strong> sampai dokumen diperbarui."
        : "Masa berlaku {$doc_label_html} akan habis pada {$tanggal} (sisa {$sisa} hari). Mohon segera diperbarui sebelum jatuh tempo.";

    return <<<HTML
<!DOCTYPE html>
<html lang="id">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Pemberitahuan Masa Berlaku Dokumen</title></head>
<body style="margin:0;padding:0;background:#f1f4f8;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f4f8;padding:32px 16px;">
<tr><td align="center">
  <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:6px;overflow:hidden;border:1px solid #dde3ec;">
    <tr><td style="background:#0d1f35;padding:24px 32px;border-bottom:4px solid {$accent};">
      <p style="margin:0;font-size:11px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:rgba(255,255,255,0.5);">PT PERTAMINA PATRA NIAGA</p>
      <p style="margin:6px 0 0;font-size:18px;font-weight:700;color:#ffffff;letter-spacing:-0.3px;">PRIMA — Masa Berlaku Dokumen Kendaraan</p>
    </td></tr>
    <tr><td style="background:{$accent};padding:12px 32px;">
      <p style="margin:0;color:#fff;font-size:13px;font-weight:700;letter-spacing:0.5px;text-transform:uppercase;">
        &#9888; PEMBERITAHUAN {$doc_label_html} &mdash; {$status_txt}
      </p>
    </td></tr>
    <tr><td style="padding:28px 32px;">
      <p style="margin:0 0 16px;font-size:15px;color:#1a2332;line-height:1.6;">{$headline}</p>
      <p style="margin:0 0 24px;font-size:13.5px;color:#4a5568;line-height:1.7;">{$body_text}</p>
      <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #dde3ec;border-radius:4px;overflow:hidden;margin-bottom:24px;">
        <tr style="background:#f8fafc;">
          <td style="padding:10px 16px;border-bottom:1px solid #dde3ec;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.7px;color:#7a8ba0;width:45%;">Nomor Polisi</td>
          <td style="padding:10px 16px;border-bottom:1px solid #dde3ec;font-size:13px;font-weight:700;color:#0d1f35;">{$nomor}</td>
        </tr>
        <tr>
          <td style="padding:10px 16px;border-bottom:1px solid #dde3ec;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.7px;color:#7a8ba0;">Nama Transport</td>
          <td style="padding:10px 16px;border-bottom:1px solid #dde3ec;font-size:13px;color:#1a2332;">{$transport}</td>
        </tr>
        <tr style="background:#f8fafc;">
          <td style="padding:10px 16px;border-bottom:1px solid #dde3ec;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.7px;color:#7a8ba0;">Merk Kendaraan</td>
          <td style="padding:10px 16px;border-bottom:1px solid #dde3ec;font-size:13px;color:#1a2332;">{$merk}</td>
        </tr>
        <tr>
          <td style="padding:10px 16px;border-bottom:1px solid #dde3ec;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.7px;color:#7a8ba0;">Jenis</td>
          <td style="padding:10px 16px;border-bottom:1px solid #dde3ec;font-size:13px;color:#1a2332;">{$jenis}</td>
        </tr>
        <tr style="background:#f8fafc;">
          <td style="padding:10px 16px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.7px;color:#7a8ba0;">{$doc_label_html} Berlaku Sampai</td>
          <td style="padding:10px 16px;font-size:13px;font-weight:700;color:{$accent};">{$tanggal}</td>
        </tr>
      </table>
      <table width="100%" cellpadding="0" cellspacing="0" style="background:#fef2f2;border:1px solid #fecaca;border-left:4px solid {$accent};border-radius:4px;margin-bottom:28px;">
        <tr><td style="padding:14px 18px;">
          <p style="margin:0 0 6px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.7px;color:{$accent};">Tindakan yang Diperlukan</p>
          <p style="margin:0;font-size:13px;color:#374151;line-height:1.8;">
            Mohon segera perbarui {$doc_label_html} kendaraan {$nomor} dan isi ulang tanggal masa berlaku terbaru pada form checklist Sistem PRIMA.
          </p>
        </td></tr>
      </table>
      <p style="margin:0;font-size:12.5px;color:#7a8ba0;line-height:1.7;">
        Email ini dikirim secara otomatis oleh Sistem PRIMA PT Pertamina Patra Niaga.<br>
        Untuk informasi lebih lanjut, hubungi bagian HSSE setempat.
      </p>
    </td></tr>
    <tr><td style="background:#f8fafc;padding:16px 32px;border-top:1px solid #dde3ec;">
      <p style="margin:0;font-size:11px;color:#b0bec8;text-align:center;">
        &copy; PT Pertamina Patra Niaga &mdash; PRIMA (Pertamina Checklist Mobil Tangki)<br>
        PRIMA | Dokumen ini bersifat resmi dan rahasia.
      </p>
    </td></tr>
  </table>
</td></tr>
</table>
</body></html>
HTML;
}

/**
 * Jalankan pengiriman notifikasi untuk dokumen (STNK/Pajak/SIMFIT/Tera/Keur) yang
 * akan (H-3) atau sudah kadaluarsa. Menggunakan konfigurasi SMTP & mailer yang sama
 * dengan notifikasi KIM, dipanggil dari titik cron yang sama.
 *
 * @return array{sent:int,skip:int,fail:int,no_email:int}
 */
function runDocumentExpiryNotifications(): array {
    $result = ['sent' => 0, 'skip' => 0, 'fail' => 0, 'no_email' => 0];
    $cfg    = getSmtpConfig();
    if (empty($cfg['smtp_username'])) {
        return $result;
    }
    $alerts = getDocumentExpiryAlerts(3);
    $mailer = new KimMailer($cfg);

    foreach ($alerts as $d) {
        if (empty($d['email_kontraktor'])) { $result['no_email']++; continue; }
        if (wasRecentlyNotifiedDocExpiry($d['nomor_polisi'], $d['item_name'], 7)) { $result['skip']++; continue; }
        $labels    = getDocumentExpiryItemLabels();
        $doc_label = $labels[$d['item_name']] ?? $d['item_name'];
        try {
            $subject = 'Pemberitahuan: ' . $doc_label . ' Mobil Tangki ' . $d['nomor_polisi'] . ' Akan/Sudah Habis Berlaku';
            $html    = buildDocumentExpiryEmail($d);
            $mailer->send($d['email_kontraktor'], $d['nama_transport'] ?? $d['nomor_polisi'], $subject, $html);
            logDocExpireNotification($d['nomor_polisi'], $d['item_name'], $d['nama_transport'] ?? '', $d['email_kontraktor'], $d['tanggal_expire'], $d['hari_tersisa'], 'sent', null);
            $result['sent']++;
        } catch (Exception $e) {
            logDocExpireNotification($d['nomor_polisi'], $d['item_name'], $d['nama_transport'] ?? '', $d['email_kontraktor'], $d['tanggal_expire'], $d['hari_tersisa'], 'failed', $e->getMessage());
            $result['fail']++;
        }
    }
    return $result;
}

// ─── HANDLE POST ACTIONS ─────────────────────────────────────────────────────
$flash_msg  = '';
$flash_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Save SMTP config
    if ($action === 'save_smtp') {
        $cfg = [
            'smtp_host'            => 'smtp.gmail.com',
            'smtp_port'            => '587',
            'smtp_encryption'      => 'tls',
            'smtp_username'        => trim($_POST['smtp_username'] ?? ''),
            'smtp_password'        => $_POST['smtp_password'] ?? '',
            'smtp_from_email'      => trim($_POST['smtp_from_email'] ?? ''),
            'smtp_from_name'       => trim($_POST['smtp_from_name'] ?? 'PRIMA KIM — PT Pertamina Patra Niaga'),
            'notif_days_threshold' => (int)($_POST['notif_days_threshold'] ?? 30),
        ];
        if (empty($cfg['smtp_password']) && ($old = getSmtpConfig())['smtp_password']) {
            $cfg['smtp_password'] = $old['smtp_password']; // keep old password if blank
        }
        if (saveSmtpConfig($cfg)) {
            $flash_msg  = 'Konfigurasi SMTP berhasil disimpan.';
            $flash_type = 'ok';
        } else {
            $flash_msg  = 'Gagal menyimpan konfigurasi.';
            $flash_type = 'err';
        }
    }

    // Test email
    if ($action === 'test_email') {
        $cfg    = getSmtpConfig();
        $to     = trim($_POST['test_email_to'] ?? $user['email'] ?? '');
        if (empty($cfg['smtp_username']) || empty($to)) {
            $flash_msg  = 'Isi konfigurasi SMTP (username & password) dan alamat email tujuan uji coba terlebih dahulu.';
            $flash_type = 'err';
        } else {
            try {
                $mailer  = new KimMailer($cfg);
                $subject = '[TEST] PRIMA KIM — Uji Coba Konfigurasi Email';
                $html    = '<p style="font-family:sans-serif;font-size:14px;">Ini adalah email uji coba dari <strong>Sistem PRIMA KIM PT Pertamina Patra Niaga</strong>.<br>Konfigurasi SMTP Anda berhasil.</p>';
                $mailer->send($to, $to, $subject, $html);
                $flash_msg  = "Email uji coba berhasil dikirim ke <strong>{$to}</strong>.";
                $flash_type = 'ok';
            } catch(Exception $e) {
                $flash_msg  = 'Gagal kirim uji coba: ' . htmlspecialchars($e->getMessage());
                $flash_type = 'err';
            }
        }
    }

    // Set / update email kontraktor
    if ($action === 'set_email') {
        $nomor = trim($_POST['nomor_polisi'] ?? '');
        $email = trim($_POST['email_kontraktor'] ?? '');
        if (empty($nomor) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $flash_msg  = 'Nomor polisi atau alamat email tidak valid.';
            $flash_type = 'err';
        } else {
            try {
                $db = Database::getInstance()->getConnection();
                // Update if exists, insert minimal row if not
                $check = $db->prepare('SELECT id FROM kendaraan WHERE nomor_polisi = :nopol LIMIT 1');
                $check->execute([':nopol' => $nomor]);
                if ($check->fetch()) {
                    $db->prepare('UPDATE kendaraan SET email_kontraktor = :email WHERE nomor_polisi = :nopol')
                       ->execute([':email' => $email, ':nopol' => $nomor]);
                } else {
                    $db->prepare('INSERT INTO kendaraan (nomor_polisi, email_kontraktor, status, created_by) VALUES (:nopol, :email, :status, :uid)')
                       ->execute([':nopol' => $nomor, ':email' => $email, ':status' => 'AKTIF', ':uid' => $user['id']]);
                }
                $flash_msg  = "Email kontraktor untuk <strong>{$nomor}</strong> berhasil disimpan.";
                $flash_type = 'ok';
            } catch (Exception $e) {
                $flash_msg  = 'Gagal menyimpan email: ' . htmlspecialchars($e->getMessage());
                $flash_type = 'err';
            }
        }
    }

    // Regenerate cron secret key (memutus akses URL cron lama)
    if ($action === 'regenerate_cron_key') {
        $new_key    = regenerateCronSecret();
        $flash_msg  = 'Kunci cron otomatis berhasil dibuat ulang. Perbarui URL cron di layanan hosting/scheduler Anda.';
        $flash_type = 'ok';
    }
}

// ─── DATA ────────────────────────────────────────────────────────────────────
$smtp_cfg      = getSmtpConfig();
$threshold     = (int)($smtp_cfg['notif_days_threshold'] ?? 30);
$alerts        = getVehicleAlerts($threshold + 30);
$history       = getNotifHistory(30);
$cron_secret   = getCronSecret();
$cron_last_run = getSystemSetting('cron_last_run');
$cron_url      = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://')
                . ($_SERVER['HTTP_HOST'] ?? 'localhost')
                . rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'] ?? '/email-notifikasi.php')), '/')
                . '/email-notifikasi.php?cron_key=' . $cron_secret;
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Notifikasi Email KIM — PRIMA Pertamina</title>
  <style>
    *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: "Segoe UI", -apple-system, BlinkMacSystemFont, "Helvetica Neue", Arial, sans-serif;
      background: #f1f4f8;
      font-size: 14px;
      color: #1a2332;
      line-height: 1.5;
    }

    .page-wrap {
      max-width: 1280px;
      margin: 0 auto;
      padding: 28px;
    }

    /* ── TOP BAR ── */
    .top-bar {
      background: white;
      border: 1px solid #dde3ec;
      border-top: 3px solid #c8102e;
      border-radius: 6px;
      padding: 16px 24px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 20px;
      flex-wrap: wrap;
      gap: 12px;
    }

    .top-bar-left { display: flex; align-items: center; gap: 14px; }

    .top-bar-accent {
      width: 3px; height: 28px;
      background: #c8102e;
      border-radius: 2px;
      flex-shrink: 0;
    }

    .top-bar-title { font-size: 16px; font-weight: 700; color: #0d1f35; }

    .top-bar-sub {
      font-size: 12px; color: #7a8ba0;
      padding-left: 12px; margin-left: 4px;
      border-left: 1px solid #dde3ec;
    }

    .btn {
      display: inline-flex; align-items: center; gap: 5px;
      padding: 7px 14px; border-radius: 4px; font-size: 13px;
      font-weight: 600; cursor: pointer; border: 1px solid transparent;
      transition: all 0.15s; text-decoration: none; white-space: nowrap;
      font-family: inherit;
    }

    .btn-primary { background: #c8102e; color: white; border-color: #c8102e; }
    .btn-primary:hover { background: #a80e27; }

    .btn-secondary { background: transparent; color: #4a5568; border-color: #d1d9e0; }
    .btn-secondary:hover { background: #f8fafc; border-color: #b0bec8; color: #1a2332; }

    .btn-danger { background: transparent; color: #c8102e; border-color: #c8102e; }
    .btn-danger:hover { background: #c8102e; color: white; }

    .btn-sm { padding: 5px 10px; font-size: 12px; }

    /* ── FLASH MESSAGE ── */
    .flash {
      padding: 13px 18px;
      border-radius: 4px;
      border-left: 4px solid;
      margin-bottom: 18px;
      font-size: 13.5px;
    }
    .flash-ok  { background: #f0fdf4; border-color: #16a34a; color: #15803d; }
    .flash-err { background: #fef2f2; border-color: #c8102e; color: #991b1b; }
    .flash-warn{ background: #fffbeb; border-color: #d97706; color: #92400e; }

    /* ── SECTION CARD ── */
    .card {
      background: white;
      border: 1px solid #dde3ec;
      border-radius: 6px;
      margin-bottom: 20px;
    }

    .card-header {
      padding: 14px 22px;
      border-bottom: 1px solid #e8ecf2;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      flex-wrap: wrap;
    }

    .card-header-title {
      font-size: 13px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      color: #0d1f35;
    }

    .card-body { padding: 22px; }

    /* ── FORM ── */
    .form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
    }

    .form-grid-3 { grid-template-columns: 1fr 1fr 1fr; }

    .form-group { display: flex; flex-direction: column; gap: 5px; }

    .form-label {
      font-size: 11.5px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.6px;
      color: #7a8ba0;
    }

    .form-control {
      padding: 8px 12px;
      border: 1px solid #d1d9e0;
      border-radius: 4px;
      font-size: 13px;
      font-family: inherit;
      color: #1a2332;
      transition: border-color 0.15s, box-shadow 0.15s;
      background: white;
    }

    .form-control:focus {
      outline: none;
      border-color: #0d1f35;
      box-shadow: 0 0 0 3px rgba(13,31,53,0.08);
    }

    .form-actions {
      display: flex;
      gap: 10px;
      align-items: center;
      margin-top: 18px;
      padding-top: 16px;
      border-top: 1px solid #e8ecf2;
      flex-wrap: wrap;
    }

    .smtp-note {
      font-size: 12px;
      color: #7a8ba0;
      line-height: 1.6;
      background: #f8fafc;
      border: 1px solid #e8ecf2;
      border-radius: 4px;
      padding: 12px 14px;
      margin-top: 14px;
    }

    .smtp-note strong { color: #0d1f35; }

    /* ── STATS ROW ── */
    .stats-row {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 14px;
      margin-bottom: 20px;
    }

    .stat-card {
      background: white;
      border: 1px solid #dde3ec;
      border-top: 3px solid #0d1f35;
      border-radius: 6px;
      padding: 16px 18px;
    }

    .stat-card.red   { border-top-color: #c8102e; }
    .stat-card.amber { border-top-color: #d97706; }
    .stat-card.green { border-top-color: #059669; }

    .stat-val {
      font-size: 28px;
      font-weight: 700;
      color: #0d1f35;
      line-height: 1;
      font-variant-numeric: tabular-nums;
      margin-bottom: 4px;
    }

    .stat-label {
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.7px;
      color: #7a8ba0;
    }

    /* ── TABLE ── */
    .tbl { width: 100%; border-collapse: collapse; }

    .tbl th {
      background: #0d1f35;
      color: rgba(255,255,255,0.85);
      padding: 10px 12px;
      text-align: left;
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .tbl td {
      padding: 10px 12px;
      border-bottom: 1px solid #f0f3f7;
      font-size: 13px;
      vertical-align: middle;
    }

    .tbl tr:last-child td { border-bottom: none; }
    .tbl tr:hover td { background: #f8fafc; }

    .badge {
      display: inline-block;
      padding: 3px 8px;
      border-radius: 3px;
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }

    .badge-expired { background: #fee2e2; color: #991b1b; }
    .badge-urgent  { background: #fff7ed; color: #c2410c; }
    .badge-warning { background: #fef9c3; color: #92400e; }
    .badge-ok      { background: #dcfce7; color: #15803d; }
    .badge-sent    { background: #dcfce7; color: #15803d; }

    .badge-btn {
      border: none;
      margin: 0;
      outline: none;
      font-family: inherit;
      letter-spacing: 0.3px;
      cursor: pointer;
      transition: filter 0.15s ease;
    }
    .badge-btn:hover  { filter: brightness(0.93); }
    .badge-btn:active { filter: brightness(0.85); }
    .badge-failed  { background: #fee2e2; color: #991b1b; }
    .badge-nomail  { background: #f1f5f9; color: #94a3b8; }

    .pill-sent {
      display: inline-block;
      font-size: 11px;
      color: #7a8ba0;
      background: #f1f4f8;
      border: 1px solid #dde3ec;
      border-radius: 3px;
      padding: 2px 7px;
      font-weight: 500;
    }

    .empty-state {
      text-align: center;
      padding: 48px 20px;
      color: #7a8ba0;
    }

    .empty-state-icon { font-size: 36px; margin-bottom: 12px; }
    .empty-state-msg  { font-size: 14px; }

    /* ── TOGGLE ── */
    .collapse-toggle { cursor: pointer; user-select: none; }
    .collapse-toggle::after { content: ' ▾'; font-size: 10px; opacity: 0.5; }
    .collapse-toggle.collapsed::after { content: ' ▸'; }

    /* ── RESPONSIVE ── */
    @media (max-width: 900px) {
      .form-grid, .form-grid-3, .stats-row { grid-template-columns: 1fr 1fr; }
    }

    @media (max-width: 600px) {
      .page-wrap { padding: 16px; }
      .form-grid, .form-grid-3, .stats-row { grid-template-columns: 1fr; }
      .top-bar-sub { display: none; }
    }
  </style>
</head>
<body>
<div class="page-wrap">

  <!-- TOP BAR -->
  <div class="top-bar">
    <div class="top-bar-left">
      <span class="top-bar-accent"></span>
      <span class="top-bar-title">Notifikasi Email KIM</span>
      <span class="top-bar-sub">Masa Berlaku Kartu Izin Masuk</span>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <a href="../vehicles/vehicle-alerts.php" class="btn btn-secondary">&#8592; Notifikasi Inspeksi</a>
      <a href="../home.php" class="btn btn-secondary">&#8962; Dashboard</a>
    </div>
  </div>

  <?php if ($flash_msg): ?>
  <div class="flash flash-<?php echo $flash_type; ?>">
    <?php echo $flash_msg; ?>
  </div>
  <?php endif; ?>

  <!-- SUMMARY STATS -->
  <?php
    $exp     = 0; $urgent = 0; $warn = 0; $no_mail = 0;
    foreach ($alerts as $a) {
        if ($a['status_alert'] === 'SUDAH_EXPIRED') $exp++;
        elseif ((int)$a['hari_tersisa'] <= 14) $urgent++;
        else $warn++;
        if (empty($a['email_kontraktor'])) $no_mail++;
    }
  ?>
  <div class="stats-row">
    <div class="stat-card red">
      <div class="stat-val"><?php echo $exp; ?></div>
      <div class="stat-label">KIM Sudah Expired</div>
    </div>
    <div class="stat-card amber">
      <div class="stat-val"><?php echo $urgent; ?></div>
      <div class="stat-label">Segera Habis (&le;14 hari)</div>
    </div>
    <div class="stat-card">
      <div class="stat-val"><?php echo $warn; ?></div>
      <div class="stat-label">Perlu Perhatian</div>
    </div>
    <div class="stat-card">
      <div class="stat-val"><?php echo $no_mail; ?></div>
      <div class="stat-label">Tanpa Email Kontraktor</div>
    </div>
  </div>

  <!-- SMTP CONFIGURATION -->
  <div class="card">
    <div class="card-header">
      <span class="card-header-title collapse-toggle" id="smtpToggle"
            onclick="toggleSection('smtpBody','smtpToggle')">
        Pengaturan SMTP
      </span>
      <span class="badge <?php echo !empty($smtp_cfg['smtp_username']) ? 'badge-ok' : 'badge-failed'; ?>">
        <?php echo !empty($smtp_cfg['smtp_username']) ? 'Terkonfigurasi' : 'Belum Dikonfigurasi'; ?>
      </span>
    </div>
    <div class="card-body" id="smtpBody">
      <div style="font-size:12px;color:#6b7280;background:#f0f9ff;border:1px solid #bae6fd;border-radius:5px;padding:10px 14px;margin-bottom:14px;">
        <strong>Server:</strong> smtp.gmail.com &bull; <strong>Port:</strong> 587 &bull; <strong>Enkripsi:</strong> TLS/STARTTLS &mdash; sudah dikonfigurasi otomatis.
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="save_smtp">
        <div class="form-grid form-grid-3">
          <div class="form-group">
            <label class="form-label">Username (Email Pengirim) *</label>
            <input type="email" name="smtp_username" class="form-control"
                   placeholder="prima@pertamina.com"
                   value="<?php echo htmlspecialchars($smtp_cfg['smtp_username'] ?? ''); ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Password / App Password</label>
            <input type="password" name="smtp_password" class="form-control"
                   placeholder="<?php echo !empty($smtp_cfg['smtp_password']) ? '(disimpan — kosongkan untuk tidak ubah)' : 'Password SMTP'; ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Batas Peringatan (hari)</label>
            <input type="number" name="notif_days_threshold" class="form-control"
                   min="1" max="90"
                   value="<?php echo (int)($smtp_cfg['notif_days_threshold'] ?? 30); ?>">
          </div>
          <div class="form-group" style="grid-column: span 2;">
            <label class="form-label">Nama Pengirim</label>
            <input type="text" name="smtp_from_name" class="form-control"
                   placeholder="PRIMA KIM — PT Pertamina Patra Niaga"
                   value="<?php echo htmlspecialchars($smtp_cfg['smtp_from_name'] ?? ''); ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Email Pengirim (From)</label>
            <input type="email" name="smtp_from_email" class="form-control"
                   placeholder="prima@pertamina.com"
                   value="<?php echo htmlspecialchars($smtp_cfg['smtp_from_email'] ?? ''); ?>">
          </div>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn btn-primary">Simpan Konfigurasi</button>
          <button type="button" class="btn btn-secondary" onclick="document.getElementById('testForm').style.display='flex'">Kirim Email Uji Coba</button>
        </div>
      </form>
      <!-- Test email sub-form -->
      <form method="POST" id="testForm" style="display:none;align-items:center;gap:8px;margin-top:12px;flex-wrap:wrap;">
        <input type="hidden" name="action" value="test_email">
        <input type="email" name="test_email_to" class="form-control" style="width:280px;flex-shrink:0;"
               placeholder="Alamat email tujuan uji coba"
               value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
        <button type="submit" class="btn btn-secondary">Kirim Uji Coba</button>
      </form>
      <div class="smtp-note">
        <strong>Contoh konfigurasi Gmail:</strong> Host: smtp.gmail.com &bull; Port: 587 &bull; Enkripsi: TLS &bull; Gunakan <em>App Password</em> (bukan password Gmail biasa) &mdash; aktifkan 2FA di akun Google terlebih dahulu.
      </div>
    </div>
  </div>

  <!-- AUTO NOTIFICATION (CRON) -->
  <div class="card">
    <div class="card-header">
      <span class="card-header-title collapse-toggle" id="cronToggle"
            onclick="toggleSection('cronBody','cronToggle')">
        Notifikasi Otomatis (Tanpa Kirim Manual)
      </span>
      <span class="badge <?php echo $cron_last_run ? 'badge-ok' : 'badge-failed'; ?>">
        <?php echo $cron_last_run ? 'Pernah Berjalan Otomatis' : 'Belum Pernah Berjalan Otomatis'; ?>
      </span>
    </div>
    <div class="card-body" id="cronBody">
      <div style="font-size:12px;color:#6b7280;background:#f0f9ff;border:1px solid #bae6fd;border-radius:5px;padding:10px 14px;margin-bottom:14px;line-height:1.7;">
        Setelah dijadwalkan (lihat cara di bawah), sistem akan mengecek KIM yang akan/sudah habis dan mengirim email
        <strong>sendiri setiap hari tanpa perlu diklik manual</strong>. Surat/dokumen yang bermasalah (kadaluarsa,
        ditolak, atau belum diupload) juga <strong>dideteksi otomatis</strong> dari tanggal masa berlakunya masing-masing
        surat — tidak perlu dipilih satu per satu.
      </div>
      <p style="font-size:12px;color:#7a8ba0;margin-bottom:10px;">
        Terakhir berjalan otomatis:
        <strong style="color:#1a2332;">
          <?php echo $cron_last_run ? date('d M Y H:i:s', strtotime($cron_last_run)) : 'Belum pernah'; ?>
        </strong>
      </p>
      <div class="smtp-note" style="margin-bottom:12px;">
        <strong>1) Lokal / Windows (XAMPP) — Task Scheduler:</strong> Buat scheduled task harian yang menjalankan:<br>
        <code>"C:\xampp\php\php.exe" "<?php echo str_replace('\\', '\\\\', __DIR__); ?>\\email-notifikasi.php" --cron</code>
      </div>
      <div class="smtp-note">
        <strong>2) Hosting (mis. Hostinger cPanel) — Cron Job berbasis URL:</strong> Jadwalkan cron job (harian) yang memanggil URL berikut:<br>
        <code id="cronUrlText" style="word-break:break-all;"><?php echo htmlspecialchars($cron_url); ?></code>
        <button type="button" class="btn btn-secondary btn-sm" style="margin-left:8px;"
                onclick="navigator.clipboard.writeText(document.getElementById('cronUrlText').textContent)">Salin URL</button>
        <br><span style="color:#b45309;">&#9888; Jaga kerahasiaan kunci ini — siapa pun yang memilikinya bisa memicu pengiriman notifikasi.</span>
      </div>
      <form method="POST" style="margin-top:12px;" onsubmit="return confirm('Buat ulang kunci cron? URL cron lama tidak akan berfungsi lagi dan harus diperbarui di scheduler/hosting Anda.')">
        <input type="hidden" name="action" value="regenerate_cron_key">
        <button type="submit" class="btn btn-secondary btn-sm">&#8635; Buat Ulang Kunci Cron</button>
      </form>
    </div>
  </div>

  <!-- VEHICLE ALERT LIST -->
  <div class="card">
    <div class="card-header">
      <span class="card-header-title">
        Kendaraan — KIM Habis / Segera Habis
        <span style="font-weight:400;text-transform:none;letter-spacing:0;color:#7a8ba0;">
          (dalam <?php echo $threshold + 30; ?> hari ke depan)
        </span>
      </span>
    </div>
    <div>
      <?php if (empty($alerts)): ?>
      <div class="empty-state">
        <div class="empty-state-icon">&#10003;</div>
        <div class="empty-state-msg">Tidak ada kendaraan dengan KIM yang akan habis dalam <?php echo $threshold + 30; ?> hari ke depan.</div>
      </div>
      <?php else: ?>
      <div style="overflow-x:auto;">
      <table class="tbl">
        <thead>
          <tr>
            <th>Nomor Polisi</th>
            <th>Jenis</th>
            <th>Nama Transport</th>
            <th>Email Kontraktor</th>
            <th>KIM Berlaku</th>
            <th>Sisa Hari</th>
            <th>Status</th>
            <th>Surat Bermasalah</th>
            <th>Terakhir Notif</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($alerts as $v):
            $hari      = (int)$v['hari_tersisa'];
            $expired   = $v['status_alert'] === 'SUDAH_EXPIRED';
            $badge_cls = $expired ? 'badge-expired' : ($hari <= 14 ? 'badge-urgent' : 'badge-warning');
            $badge_txt = $expired ? 'Expired' : ($hari <= 14 ? 'Segera Habis' : 'Perlu Perhatian');
            $has_email = !empty($v['email_kontraktor']);
            $recently  = wasRecentlyNotified($v['nomor_polisi'], 7);
            $tanggal   = !empty($v['ekim_valid_until']) ? date('d M Y', strtotime($v['ekim_valid_until'])) : '-';
            $surat_bermasalah = getSuratBermasalah($v['nomor_polisi']);
          ?>
          <tr>
            <td><strong><?php echo htmlspecialchars($v['nomor_polisi']); ?></strong></td>
            <td><?php echo htmlspecialchars($v['jenis'] ?? '-'); ?></td>
            <td><?php echo htmlspecialchars($v['nama_transport'] ?? '-'); ?></td>
            <td>
              <?php if ($has_email): ?>
                <span style="font-size:12px;"><?php echo htmlspecialchars($v['email_kontraktor']); ?></span>
              <?php else: ?>
                <span class="badge badge-nomail">Belum ada</span>
              <?php endif; ?>
            </td>
            <td style="white-space:nowrap;"><?php echo $tanggal; ?></td>
            <td>
              <?php if ($expired): ?>
                <span style="color:#c8102e;font-weight:700;">+<?php echo abs($hari); ?> hari</span>
              <?php else: ?>
                <?php echo $hari; ?> hari
              <?php endif; ?>
            </td>
            <td><span class="badge <?php echo $badge_cls; ?>"><?php echo $badge_txt; ?></span></td>
            <td>
              <?php if (!empty($surat_bermasalah)): ?>
                <button type="button" class="badge badge-expired badge-btn"
                        title="Klik untuk lihat detail surat bermasalah"
                        onclick='openSuratModal(<?php echo json_encode($v["nomor_polisi"]); ?>, <?php echo json_encode($surat_bermasalah); ?>, <?php echo json_encode($has_email); ?>)'>
                  <?php echo count($surat_bermasalah); ?> Surat &#8250;
                </button>
              <?php else: ?>
                <span class="badge badge-ok">Lengkap</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($recently): ?>
                <span class="pill-sent">&#10003; &lt;7 hari lalu</span>
              <?php else: ?>
                <span style="color:#b0bec8;font-size:12px;">—</span>
              <?php endif; ?>
            </td>
            <td style="white-space:nowrap;">
              <?php if (!$has_email): ?>
                <button type="button" class="btn btn-secondary btn-sm"
                  onclick="openEmailModal('<?php echo htmlspecialchars($v['nomor_polisi'], ENT_QUOTES); ?>')">&#9998; Isi Email</button>
              <?php else: ?>
                <span style="color:#b0bec8;font-size:12px;">—</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- NOTIFICATION HISTORY -->
  <div class="card">
    <div class="card-header">
      <span class="card-header-title">Riwayat Notifikasi</span>
      <span style="font-size:12px;color:#7a8ba0;"><?php echo count($history); ?> entri terakhir</span>
    </div>
    <div>
      <?php if (empty($history)): ?>
      <div class="empty-state">
        <div class="empty-state-msg">Belum ada riwayat pengiriman notifikasi.</div>
      </div>
      <?php else: ?>
      <div style="overflow-x:auto;">
      <table class="tbl">
        <thead>
          <tr>
            <th>Dikirim</th>
            <th>Nomor Polisi</th>
            <th>Nama Transport</th>
            <th>Email Tujuan</th>
            <th>KIM Berlaku</th>
            <th>Sisa Hari</th>
            <th>Status</th>
            <th>Dikirim Oleh</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($history as $h): ?>
          <tr>
            <td style="white-space:nowrap;font-size:12px;"><?php echo date('d M Y H:i', strtotime($h['sent_at'])); ?></td>
            <td><strong><?php echo htmlspecialchars($h['nomor_polisi']); ?></strong></td>
            <td><?php echo htmlspecialchars($h['nama_transport'] ?? '-'); ?></td>
            <td style="font-size:12px;"><?php echo htmlspecialchars($h['email_to']); ?></td>
            <td style="white-space:nowrap;">
              <?php echo !empty($h['ekim_valid_until']) ? date('d M Y', strtotime($h['ekim_valid_until'])) : '-'; ?>
            </td>
            <td><?php echo $h['hari_tersisa'] !== null ? $h['hari_tersisa'] : '-'; ?></td>
            <td>
              <span class="badge badge-<?php echo $h['status'] === 'sent' ? 'sent' : 'failed'; ?>">
                <?php echo $h['status'] === 'sent' ? 'Terkirim' : 'Gagal'; ?>
              </span>
              <?php if (!empty($h['error_message'])): ?>
                <span title="<?php echo htmlspecialchars($h['error_message']); ?>"
                      style="cursor:help;font-size:11px;color:#b45309;margin-left:4px;">&#9432;</span>
              <?php endif; ?>
            </td>
            <td style="font-size:12px;"><?php echo htmlspecialchars($h['sender_name'] ?? 'Sistem'); ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <div style="font-size:12px;color:#b0bec8;text-align:center;padding:8px 0 4px;">
    PRIMA — Kartu Izin Masuk &nbsp;&mdash;&nbsp; &copy; <?php echo date('Y'); ?> PT Pertamina Patra Niaga
  </div>
</div>

<!-- Modal Isi Email Kontraktor -->
<div id="emailModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:8px;padding:28px 28px 22px;width:100%;max-width:420px;box-shadow:0 8px 32px rgba(0,0,0,.2);">
    <h3 style="margin:0 0 4px;font-size:15px;color:#1a2332;">Isi Email Kontraktor</h3>
    <p id="emailModalNopol" style="margin:0 0 16px;font-size:12px;color:#7a8ba0;"></p>
    <form method="POST">
      <input type="hidden" name="action" value="set_email">
      <input type="hidden" name="nomor_polisi" id="emailModalInput">
      <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:4px;">Alamat Email Kontraktor</label>
      <input type="email" name="email_kontraktor" id="emailModalEmail" required
             style="width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:5px;font-size:14px;box-sizing:border-box;margin-bottom:16px;"
             placeholder="contoh@perusahaan.com">
      <div style="display:flex;gap:8px;justify-content:flex-end;">
        <button type="button" onclick="closeEmailModal()"
                style="padding:7px 16px;border:1px solid #d1d5db;border-radius:5px;background:#fff;cursor:pointer;font-size:13px;">Batal</button>
        <button type="submit"
                style="padding:7px 16px;border:none;border-radius:5px;background:#c8102e;color:#fff;cursor:pointer;font-size:13px;font-weight:600;">Simpan Email</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Detail Surat Bermasalah (info saja — pengiriman notifikasi sepenuhnya otomatis) -->
<div id="suratModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:9999;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:8px;padding:24px 24px 20px;width:100%;max-width:460px;max-height:82vh;overflow-y:auto;box-shadow:0 8px 32px rgba(0,0,0,.2);">
    <h3 style="margin:0 0 4px;font-size:15px;color:#1a2332;">Surat/Dokumen Bermasalah</h3>
    <p id="suratModalNopol" style="margin:0 0 6px;font-size:12px;color:#7a8ba0;"></p>
    <p style="margin:0 0 14px;font-size:12px;color:#7a8ba0;line-height:1.5;">Surat berikut akan otomatis disertakan pada email notifikasi berikutnya.</p>
    <div id="suratModalList" style="display:flex;flex-direction:column;gap:8px;margin-bottom:12px;"></div>
    <p id="suratModalNoEmailWarning" style="display:none;margin:0 0 14px;font-size:12px;color:#92400e;background:#fffbeb;border:1px solid #fde68a;border-radius:5px;padding:8px 10px;line-height:1.5;">
      Kendaraan ini belum memiliki email kontraktor, sehingga notifikasi otomatis belum bisa terkirim. Isi email kontraktor terlebih dahulu.
    </p>
    <div style="display:flex;gap:8px;justify-content:flex-end;">
      <button type="button" onclick="closeSuratModal()"
              style="padding:7px 16px;border:1px solid #d1d5db;border-radius:5px;background:#fff;cursor:pointer;font-size:13px;">Tutup</button>
    </div>
  </div>
</div>

<script>
function toggleSection(bodyId, toggleId) {
  const body   = document.getElementById(bodyId);
  const toggle = document.getElementById(toggleId);
  const hidden = body.style.display === 'none';
  body.style.display = hidden ? '' : 'none';
  toggle.classList.toggle('collapsed', !hidden);
}
function openEmailModal(nopol) {
  document.getElementById('emailModalInput').value = nopol;
  document.getElementById('emailModalNopol').textContent = 'Kendaraan: ' + nopol;
  document.getElementById('emailModalEmail').value = '';
  const m = document.getElementById('emailModal');
  m.style.display = 'flex';
  setTimeout(() => document.getElementById('emailModalEmail').focus(), 50);
}
function closeEmailModal() {
  document.getElementById('emailModal').style.display = 'none';
}
document.getElementById('emailModal').addEventListener('click', function(e) {
  if (e.target === this) closeEmailModal();
});
function openSuratModal(nopol, items, hasEmail) {
  document.getElementById('suratModalNopol').textContent = 'Kendaraan: ' + nopol;
  const list = document.getElementById('suratModalList');
  list.innerHTML = items.map(it => `
    <div style="border:1px solid #fecaca;background:#fef2f2;border-radius:6px;padding:8px 12px;">
      <span style="display:block;font-weight:700;font-size:13px;color:#991b1b;">${escapeHtml(it.label)}</span>
      <span style="display:block;font-size:12px;color:#7a2323;margin-top:2px;">${escapeHtml(it.reason)}</span>
    </div>
  `).join('');
  document.getElementById('suratModalNoEmailWarning').style.display = hasEmail ? 'none' : 'block';
  document.getElementById('suratModal').style.display = 'flex';
}
function closeSuratModal() {
  document.getElementById('suratModal').style.display = 'none';
}
document.getElementById('suratModal').addEventListener('click', function(e) {
  if (e.target === this) closeSuratModal();
});
function escapeHtml(str) {
  const d = document.createElement('div');
  d.textContent = str ?? '';
  return d.innerHTML;
}
</script>
</body>
</html>
