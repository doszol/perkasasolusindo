<?php
// ============================================================
// cron/mailer_test.php
// Letakkan di: /public_html/cron/mailer_test.php
//
// FUNGSI:
//   File mailer KHUSUS UNTUK TESTING — berdiri sendiri, TIDAK
//   bergantung pada /public_html/config.php atau /public_html/mailer.php.
//
//   Tujuannya: kalau cron_test.php gagal kirim email, kita bisa
//   pastikan apakah masalahnya ada di KONFIGURASI SMTP itu sendiri
//   (file ini) atau di mailer.php utama / config.php (kemungkinan
//   ada error lain yang mengganggu).
//
//   File ini menyediakan:
//     - Konstanta SMTP sendiri (silakan samakan dengan mailer.php utama)
//     - Fungsi perkasa_test_send_mail()  → kirim email via PHPMailer
//     - Fungsi render_email_cron_test()  → template email hasil tes
//
// PENTING:
//   Path PHPMailer di sini mengikuti struktur:
//     /public_html/phpmailer/src/PHPMailer.php
//   karena file ini di /public_html/cron/, naik 1 level ke /public_html/.
// ============================================================

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as MailerException;

require_once __DIR__ . '/../phpmailer/src/Exception.php';
require_once __DIR__ . '/../phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../phpmailer/src/SMTP.php';

// ── Konfigurasi SMTP (samakan dengan /public_html/mailer.php) ──────────────
// Gunakan define() dengan pengecekan agar tidak bentrok jika file ini
// di-include bersamaan dengan mailer.php utama (yang juga define konstanta ini).
if (!defined('MAIL_HOST')) {
    define('MAIL_HOST',       'dolce.id.rapidwhm.com');
    define('MAIL_PORT',       587);
    define('MAIL_ENCRYPTION', PHPMailer::ENCRYPTION_STARTTLS);
    define('MAIL_USERNAME',   'noreply@perkasasolusindo.co.id');
    define('MAIL_PASSWORD',   'noreply@969699');
    define('MAIL_FROM',       'noreply@perkasasolusindo.co.id');
    define('MAIL_FROM_NAME',  'Perkasa Solusindo');
    define('MAIL_ADMIN',      'amrlfuad0906@gmail.com');
}

// ─────────────────────────────────────────────────────────────────────────────
/**
 * Kirim email test via SMTP (standalone, tanpa mailer.php utama).
 *
 * @param string $to       Alamat email penerima
 * @param string $toName   Nama penerima
 * @param string $subject  Subject email
 * @param string $htmlBody Body HTML
 * @param bool   $debug    Jika true, aktifkan SMTP debug verbose (dicetak ke output)
 * @return array ['ok' => bool, 'error' => string|null, 'debug' => string]
 */
function perkasa_test_send_mail(
    string $to,
    string $toName,
    string $subject,
    string $htmlBody,
    bool   $debug = false
): array {
    $debugLog = '';

    try {
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->Port       = MAIL_PORT;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_ENCRYPTION;
        $mail->CharSet    = 'UTF-8';

        if ($debug) {
            $mail->SMTPDebug  = SMTP::DEBUG_SERVER;
            $mail->Debugoutput = function ($str, $level) use (&$debugLog) {
                $debugLog .= "[$level] $str\n";
            };
        } else {
            $mail->SMTPDebug = SMTP::DEBUG_OFF;
        }

        // Toleransi sertifikat SSL shared hosting (CN cert ≠ MAIL_HOST)
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ];

        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addReplyTo(MAIL_ADMIN, MAIL_FROM_NAME);
        $mail->addAddress($to, $toName);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));

        $mail->send();

        return ['ok' => true, 'error' => null, 'debug' => $debugLog];

    } catch (MailerException $e) {
        return ['ok' => false, 'error' => $e->getMessage(), 'debug' => $debugLog];
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'Unexpected: ' . $e->getMessage(), 'debug' => $debugLog];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
/**
 * Render email sederhana untuk konfirmasi cron test.
 *
 * Keys array $d: waktu, source, host
 */
function render_email_cron_test(array $d): string
{
    $year = date('Y');
    return '<!DOCTYPE html>
<html lang="id">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Cron Test — Perkasa Solusindo</title></head>
<body style="margin:0;padding:0;background:#0b0f1a;font-family:Arial,sans-serif;color:#f1f5f9">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#0b0f1a;padding:40px 20px">
<tr><td align="center">
<table width="100%" style="max-width:520px" cellpadding="0" cellspacing="0">

  <!-- Header -->
  <tr><td style="background:linear-gradient(135deg,#22c55e,#16a34a);border-radius:14px 14px 0 0;padding:28px 32px;text-align:center">
    <div style="font-size:32px;margin-bottom:8px">✅</div>
    <div style="font-size:22px;font-weight:900;color:#fff;letter-spacing:.5px">PERKASA <span style="color:#dcfce7">SOLUSINDO</span></div>
    <div style="font-size:12px;color:rgba(255,255,255,.75);margin-top:4px">Cron Job Test Berhasil</div>
  </td></tr>

  <!-- Body -->
  <tr><td style="background:#111827;padding:28px 32px;">
    <p style="font-size:14px;color:#94a3b8;line-height:1.8;margin:0 0 16px;">
      Email ini dikirim otomatis oleh <strong style="color:#f1f5f9">cron_test.php</strong>
      (via <code style="background:#1f2937;padding:2px 6px;border-radius:4px;color:#22c55e;">mailer_test.php</code>)
      untuk memverifikasi bahwa cron job di server berjalan dengan benar
      dan koneksi SMTP berfungsi.
    </p>
    <table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;line-height:2;color:#e2e8f0;background:rgba(34,197,94,.07);border:1px solid rgba(34,197,94,.25);border-left:4px solid #22c55e;border-radius:0 10px 10px 0;padding:14px 16px;">
      <tr><td style="color:#94a3b8;width:40%;">Waktu Eksekusi</td><td style="font-weight:700;text-align:right;font-family:\'Courier New\',monospace;">' . htmlspecialchars($d['waktu']) . '</td></tr>
      <tr><td style="color:#94a3b8;">Dijalankan Via</td><td style="font-weight:700;text-align:right;">' . htmlspecialchars($d['source']) . '</td></tr>
      <tr><td style="color:#94a3b8;">SMTP Host</td><td style="font-weight:700;text-align:right;font-family:\'Courier New\',monospace;">' . htmlspecialchars($d['host']) . '</td></tr>
    </table>
    <p style="font-size:12px;color:#64748b;line-height:1.8;margin:16px 0 0;">
      <i>Jika email ini Anda terima sesuai jadwal cron yang diset di cPanel,
      berarti cron_reminder.php dan cron_suspend.php juga akan berjalan normal
      pada tanggal yang dijadwalkan.</i>
    </p>
  </td></tr>

  <!-- Footer -->
  <tr><td style="background:#0b0f1a;border-radius:0 0 14px 14px;padding:20px 32px;text-align:center;border-top:1px solid rgba(255,255,255,.06)">
    <div style="font-size:11px;color:#334155;">
      Email ini dikirim otomatis oleh sistem (cron_test.php / mailer_test.php). Mohon tidak membalas email ini.<br>
      &copy; ' . $year . ' Perkasa Tech Solusindo. All rights reserved.
    </div>
  </td></tr>

</table></td></tr></table>
</body></html>';
}
