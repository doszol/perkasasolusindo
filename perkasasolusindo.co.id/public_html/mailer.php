<?php
/**
 * Perkasa Solusindo — Central Mailer Helper
 * Path : /public_html/mailer.php
 *
 * Cara pakai di file manapun:
 *   require_once dirname(__DIR__) . '/mailer.php';   // dari subfolder
 *   require_once __DIR__ . '/mailer.php';             // dari root
 *
 *   $ok = perkasa_send_mail(
 *       'client@email.com',
 *       'Budi Santoso',
 *       'Order Berhasil',
 *       render_email_order_wifi([...])
 *   );
 */

// ── Load PHPMailer ────────────────────────────────────────────────────────────
// Struktur: /public_html/phpmailer/src/PHPMailer.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as MailerException;

require_once __DIR__ . '/phpmailer/src/Exception.php';
require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/phpmailer/src/SMTP.php';

// ── Konfigurasi SMTP ──────────────────────────────────────────────────────────
// PENTING: Gunakan hostname server aktual dari cPanel (bukan domain email).
// Cek di cPanel → Email Accounts → Connect Devices → Incoming Server.
// Hostname ini harus cocok dengan sertifikat SSL server shared hosting Anda.
define('MAIL_HOST',       'dolce.id.rapidwhm.com');   // ← hostname server cPanel Anda
define('MAIL_PORT',       587);
define('MAIL_ENCRYPTION', PHPMailer::ENCRYPTION_STARTTLS);
define('MAIL_USERNAME',   'noreply@perkasasolusindo.co.id');
define('MAIL_PASSWORD',   'noreply@969699');
define('MAIL_FROM',       'noreply@perkasasolusindo.co.id');
define('MAIL_FROM_NAME',  'Perkasa Solusindo');
define('MAIL_ADMIN',      'info-perkasa@perkasasolusindo.co.id');

// ── URL Situs ─────────────────────────────────────────────────────────────────
define('SITE_URL',         'https://perkasasolusindo.co.id');
define('LOGIN_URL',        'https://perkasasolusindo.co.id/login/login.php');
define('DASHBOARD_URL',    'https://perkasasolusindo.co.id/client/client_dashboard.php');
define('ADMIN_URL',        'https://perkasasolusindo.co.id/admin/admin_dashboard.php');
define('ADMIN_ORDERS_URL', 'https://perkasasolusindo.co.id/admin/orders.php');

// ─────────────────────────────────────────────────────────────────────────────
/**
 * Kirim satu email.
 *
 * @param string      $to       Alamat email penerima
 * @param string      $toName   Nama penerima
 * @param string      $subject  Subject email
 * @param string      $htmlBody Body HTML
 * @param string|null $altBody  Plain-text fallback (auto-generate jika null)
 * @param array       $cc       ['email' => 'nama'] untuk CC (opsional)
 * @return bool true = berhasil, false = gagal (error di-log)
 */
function perkasa_send_mail(
    string  $to,
    string  $toName,
    string  $subject,
    string  $htmlBody,
    ?string $altBody = null,
    array   $cc      = []
): bool {
    try {
        $mail = new PHPMailer(true);

        // Server
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->Port       = MAIL_PORT;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_ENCRYPTION;
        $mail->CharSet    = 'UTF-8';
        // Nonaktifkan debug di produksi — ubah ke SMTP::DEBUG_SERVER untuk troubleshoot
        $mail->SMTPDebug  = SMTP::DEBUG_OFF;

        // FIX: Shared hosting cPanel sering pakai SSL cert atas nama server (bukan domain email).
        // Ini membolehkan koneksi tetap berjalan meski CN cert tidak cocok dengan MAIL_HOST.
        // Hapus baris ini jika Anda sudah install SSL dedicated untuk mail.perkasasolusindo.co.id.
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ];

        // Pengirim & Reply-To
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addReplyTo(MAIL_ADMIN, MAIL_FROM_NAME); // balas → CS, bukan noreply

        // Penerima
        $mail->addAddress($to, $toName);
        foreach ($cc as $ccEmail => $ccName) {
            $mail->addCC($ccEmail, $ccName);
        }

        // Konten
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $altBody
            ?? strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));

        $mail->send();
        return true;

    } catch (MailerException $e) {
        error_log('[PERKASA MAILER] ' . $e->getMessage());
        return false;
    } catch (Throwable $e) {
        error_log('[PERKASA MAILER] Unexpected: ' . $e->getMessage());
        return false;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
/**
 * Render HTML email konfirmasi order WiFi untuk CLIENT.
 *
 * Keys array $d yang dibutuhkan:
 *   order_number, client_name, email,
 *   paket_name, paket_speed, paket_price,
 *   alamat, is_new_user, login_url (opsional — default LOGIN_URL)
 */
function render_email_order_wifi(array $d): string
{
    $rupiah = function($n) { return 'Rp ' . number_format((float)$n, 0, ',', '.'); };

    $login_url = $d['login_url'] ?? LOGIN_URL;

    // Blok khusus client baru vs client lama
    $account_note = $d['is_new_user']
        ? '<p style="margin:0 0 6px">
             🆕 <strong style="color:#a78bfa">Akun baru</strong> sudah dibuat otomatis dengan email ini.
             Gunakan password yang Anda daftarkan untuk masuk.
           </p>'
        : '';

    // Timeline 5 langkah
    $steps = [
        ['✅', 'Order Diterima',        'Formulir Anda sudah masuk ke sistem kami',                               true],
        ['🔍', 'Verifikasi Admin',       'Tim kami verifikasi data KTP & cek coverage area (1–2 hari kerja)',      false],
        ['📅', 'Penjadwalan Instalasi',  'Teknisi menghubungi Anda via WhatsApp untuk konfirmasi jadwal',          false],
        ['🔧', 'Instalasi WiFi',         'Teknisi datang ke lokasi & memasang perangkat',                          false],
        ['📶', 'WiFi Aktif & Tagihan',   'Layanan aktif — tagihan pertama mulai setelah instalasi selesai',        false],
    ];

    $steps_html = '';
    $last = count($steps) - 1;
    foreach ($steps as $i => [$icon, $title, $desc, $active]) {
        $title_color  = $active ? '#22c55e' : '#e2e8f0';
        $border_style = ($i < $last) ? 'border-bottom:1px solid rgba(255,255,255,.06)' : 'border-bottom:none';
        $steps_html  .= '
        <tr>
          <td style="width:36px;padding:0 12px 20px 0;vertical-align:top;font-size:20px;line-height:1">' . $icon . '</td>
          <td style="padding-bottom:20px;' . $border_style . '">
            <div style="font-size:14px;font-weight:700;color:' . $title_color . ';margin:0 0 3px">'
                . $title . ($active ? ' ✓' : '') .
            '</div>
            <div style="font-size:12px;color:#64748b;margin:0">' . $desc . '</div>
          </td>
        </tr>';
    }

    $year = date('Y');

    return '<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Order WiFi Berhasil — Perkasa Solusindo</title>
</head>
<body style="margin:0;padding:0;background:#0b0f1a;font-family:Arial,sans-serif;color:#f1f5f9">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#0b0f1a;padding:40px 20px">
<tr><td align="center">
<table width="100%" style="max-width:580px" cellpadding="0" cellspacing="0">

  <!-- ── Header brand ── -->
  <tr>
    <td style="background:linear-gradient(135deg,#f97316,#ea580c);
               border-radius:14px 14px 0 0;padding:28px 32px;text-align:center">
      <div style="font-size:30px;margin-bottom:8px">📡</div>
      <div style="font-size:22px;font-weight:900;color:#fff;letter-spacing:.5px">
        PERKASA <span style="color:#fed7aa">SOLUSINDO</span>
      </div>
      <div style="font-size:12px;color:rgba(255,255,255,.75);margin-top:4px">
        Solusi Teknologi Terpadu
      </div>
    </td>
  </tr>

  <!-- ── Success hero ── -->
  <tr>
    <td style="background:#111827;padding:32px 32px 0;text-align:center">
      <div style="width:68px;height:68px;
                  background:rgba(34,197,94,.12);border:2px solid #22c55e;
                  border-radius:50%;display:inline-block;
                  font-size:30px;line-height:64px;margin-bottom:16px">✓</div>
      <h1 style="font-size:24px;font-weight:900;color:#f1f5f9;margin:0 0 10px">
        Order WiFi Berhasil Dikirim! 🎉
      </h1>
      <p style="font-size:15px;color:#94a3b8;margin:0;line-height:1.6">
        Halo, <strong style="color:#f1f5f9">' . htmlspecialchars($d['client_name']) . '</strong>!<br>
        Order WiFi Anda dengan paket <strong style="color:#f97316">' . htmlspecialchars($d['paket_name']) . '</strong>
        sudah kami terima dengan sukses.
      </p>
    </td>
  </tr>

  <!-- ── Nomor order badge ── -->
  <tr>
    <td style="background:#111827;padding:20px 32px 0">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td style="background:rgba(249,115,22,.08);
                     border:1px solid rgba(249,115,22,.25);
                     border-radius:10px;padding:16px 20px;text-align:center">
            <div style="font-size:11px;color:#64748b;text-transform:uppercase;
                        letter-spacing:.8px;margin-bottom:6px">Nomor Order Anda</div>
            <div style="font-size:24px;font-weight:900;color:#f97316;letter-spacing:2px;
                        font-family:\'Courier New\',monospace">
              ' . htmlspecialchars($d['order_number']) . '
            </div>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- ── Detail order ── -->
  <tr>
    <td style="background:#111827;padding:24px 32px 0">
      <div style="font-size:12px;font-weight:700;color:#64748b;
                  text-transform:uppercase;letter-spacing:.8px;margin-bottom:14px">
        Detail Pesanan
      </div>
      <table width="100%" cellpadding="0" cellspacing="0" style="font-size:14px">
        <tr>
          <td style="color:#64748b;padding:9px 0;border-bottom:1px solid rgba(255,255,255,.06);width:46%">Paket</td>
          <td style="color:#f1f5f9;font-weight:600;padding:9px 0;border-bottom:1px solid rgba(255,255,255,.06);text-align:right">
            ' . htmlspecialchars($d['paket_name']) . '
          </td>
        </tr>
        <tr>
          <td style="color:#64748b;padding:9px 0;border-bottom:1px solid rgba(255,255,255,.06)">Kecepatan</td>
          <td style="color:#3b82f6;font-weight:600;padding:9px 0;border-bottom:1px solid rgba(255,255,255,.06);text-align:right">
            ⚡ ' . htmlspecialchars($d['paket_speed']) . '
          </td>
        </tr>
        <tr>
          <td style="color:#64748b;padding:9px 0;border-bottom:1px solid rgba(255,255,255,.06)">Tarif Bulanan</td>
          <td style="color:#f97316;font-weight:700;padding:9px 0;border-bottom:1px solid rgba(255,255,255,.06);text-align:right">
            ' . $rupiah((float)$d['paket_price']) . '/bulan
          </td>
        </tr>
        <tr>
          <td style="color:#64748b;padding:9px 0;border-bottom:1px solid rgba(255,255,255,.06)">Alamat Pasang</td>
          <td style="color:#f1f5f9;font-weight:500;padding:9px 0;border-bottom:1px solid rgba(255,255,255,.06);text-align:right">
            ' . htmlspecialchars($d['alamat']) . '
          </td>
        </tr>
        <tr>
          <td style="color:#64748b;padding:9px 0">Status Pembayaran</td>
          <td style="color:#3b82f6;font-weight:600;padding:9px 0;text-align:right">
            💳 Bayar Setelah Instalasi
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- ── Pesan utama: pantau dashboard ── -->
  <tr>
    <td style="background:#111827;padding:20px 32px 0">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td style="background:rgba(249,115,22,.07);
                     border:1px solid rgba(249,115,22,.22);
                     border-left:4px solid #f97316;
                     border-radius:0 10px 10px 0;
                     padding:16px 18px;font-size:14px;line-height:1.7;color:#e2e8f0">
            <p style="margin:0 0 8px;font-weight:700;color:#f97316;font-size:15px">
              📋 Apa yang terjadi selanjutnya?
            </p>
            <p style="margin:0 0 8px">
              Tim kami akan <strong>memverifikasi data KTP</strong> dan memeriksa
              <strong>ketersediaan coverage area</strong> Anda dalam 1–2 hari kerja.
              Setelah verifikasi selesai, teknisi kami akan menghubungi Anda via
              <strong>WhatsApp</strong> untuk menjadwalkan instalasi.
            </p>
            <p style="margin:0;color:#fb923c;font-weight:600">
              🔔 Mohon pantau <strong>dashboard klien</strong> Anda secara berkala
              untuk mendapatkan update jadwal instalasi oleh teknisi.
            </p>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- ── Timeline proses ── -->
  <tr>
    <td style="background:#111827;padding:24px 32px 0">
      <div style="font-size:12px;font-weight:700;color:#64748b;
                  text-transform:uppercase;letter-spacing:.8px;margin-bottom:16px">
        Status Proses
      </div>
      <table width="100%" cellpadding="0" cellspacing="0">
        ' . $steps_html . '
      </table>
    </td>
  </tr>

  <!-- ── Info email & akun ── -->
  <tr>
    <td style="background:#111827;padding:20px 32px 0">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td style="background:rgba(59,130,246,.07);
                     border:1px solid rgba(59,130,246,.2);
                     border-radius:10px;padding:14px 16px;
                     font-size:13px;color:#94a3b8;line-height:1.7">
            ' . $account_note . '
            <p style="margin:0">
              📧 Update status order akan dikirimkan ke
              <strong style="color:#f1f5f9">' . htmlspecialchars($d['email']) . '</strong>.
              Pastikan email ini aktif.
            </p>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- ── CTA tombol dashboard ── -->
  <tr>
    <td style="background:#111827;padding:28px 32px">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td align="center">
            <a href="' . htmlspecialchars($login_url) . '"
               style="display:inline-block;
                      background:linear-gradient(135deg,#f97316,#ea580c);
                      color:#fff;font-size:15px;font-weight:700;
                      text-decoration:none;padding:14px 36px;
                      border-radius:8px;letter-spacing:.5px">
              🚀 &nbsp;Login ke Dashboard Klien
            </a>
          </td>
        </tr>
        <tr>
          <td style="text-align:center;padding-top:10px;font-size:12px;color:#475569">
            atau kunjungi:
            <a href="' . SITE_URL . '"
               style="color:#f97316;text-decoration:none">' . SITE_URL . '</a>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- ── Footer ── -->
  <tr>
    <td style="background:#0b0f1a;border-radius:0 0 14px 14px;
               padding:22px 32px;text-align:center;
               border-top:1px solid rgba(255,255,255,.06)">
      <div style="font-size:13px;color:#64748b;line-height:1.9">
        <strong style="color:#94a3b8">PT. Perkasa Tech Solusindo</strong><br>
        Jln. KedungRejo, Wedoroklurak, Candi, Sidoarjo, Jawa Timur 61271<br>
        📞 <a href="tel:+6281246684665"
              style="color:#f97316;text-decoration:none">+62 812-4668-4665</a>
        &nbsp;·&nbsp;
        ✉️ <a href="mailto:info-perkasa@perkasasolusindo.co.id"
              style="color:#f97316;text-decoration:none">info-perkasa@perkasasolusindo.co.id</a>
      </div>
      <div style="margin-top:14px;font-size:11px;color:#334155">
        Email ini dikirim otomatis oleh sistem. Mohon tidak membalas email ini.<br>
        &copy; ' . $year . ' Perkasa Tech Solusindo. All rights reserved.
      </div>
    </td>
  </tr>

</table>
</td></tr>
</table>

</body>
</html>';
}

// ─────────────────────────────────────────────────────────────────────────────
/**
 * Render HTML email selamat datang untuk TEKNISI BARU yang baru didaftarkan admin.
 *
 * Keys array $d yang dibutuhkan:
 *   firstname      – Nama depan teknisi
 *   lastname       – Nama belakang teknisi (opsional)
 *   email          – Email login teknisi
 *   password       – Password plain-text (sebelum di-hash)
 *   verify_url     – URL verifikasi email (wajib)
 *   admin_name     – Nama admin yang mendaftarkan
 */
function render_email_welcome_teknisi(array $d): string
{
    $year       = date('Y');
    $nama       = htmlspecialchars(trim($d['firstname'] . ' ' . ($d['lastname'] ?? '')));
    $email      = htmlspecialchars($d['email']);
    $password   = htmlspecialchars($d['password']);
    $verifyUrl  = htmlspecialchars($d['verify_url']);
    $adminName  = htmlspecialchars($d['admin_name'] ?? 'Admin');
    $loginUrl   = LOGIN_URL;

    return '<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Selamat Datang di Perkasa Solusindo — Akun Teknisi Anda</title>
</head>
<body style="margin:0;padding:0;background:#0b0f1a;font-family:Arial,sans-serif;color:#f1f5f9">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#0b0f1a;padding:40px 20px">
<tr><td align="center">
<table width="100%" style="max-width:580px" cellpadding="0" cellspacing="0">

  <!-- ── Header brand ── -->
  <tr>
    <td style="background:linear-gradient(135deg,#f59e0b,#ef4444);
               border-radius:14px 14px 0 0;padding:28px 32px;text-align:center">
      <div style="font-size:34px;margin-bottom:8px">🔧</div>
      <div style="font-size:22px;font-weight:900;color:#fff;letter-spacing:.5px">
        PERKASA <span style="color:#fde68a">SOLUSINDO</span>
      </div>
      <div style="font-size:12px;color:rgba(255,255,255,.80);margin-top:4px;letter-spacing:.3px">
        Portal Teknisi
      </div>
    </td>
  </tr>

  <!-- ── Body utama ── -->
  <tr>
    <td style="background:#111827;padding:30px 32px;
               border-left:1px solid rgba(255,255,255,.07);
               border-right:1px solid rgba(255,255,255,.07)">

      <!-- Salam -->
      <p style="margin:0 0 6px;font-size:13px;color:#94a3b8">Halo,</p>
      <h2 style="margin:0 0 18px;font-size:20px;font-weight:900;color:#f1f5f9">
        Selamat datang, ' . $nama . '! 👋
      </h2>
      <p style="margin:0 0 22px;font-size:14px;color:#cbd5e1;line-height:1.7">
        Akun teknisi Anda di <strong style="color:#fbbf24">Perkasa Solusindo</strong>
        telah berhasil didaftarkan oleh <strong>' . $adminName . '</strong>.
        Gunakan informasi di bawah ini untuk masuk ke sistem.
      </p>

      <!-- Kotak kredensial -->
      <table width="100%" cellpadding="0" cellspacing="0"
             style="background:#0b0f1a;border:1px solid rgba(251,191,36,.25);
                    border-radius:10px;margin-bottom:24px">
        <tr>
          <td style="padding:20px 24px">
            <div style="font-size:11px;font-weight:700;color:#fbbf24;
                        text-transform:uppercase;letter-spacing:.8px;margin-bottom:14px">
              🔑 &nbsp;Kredensial Login Anda
            </div>

            <table width="100%" cellpadding="0" cellspacing="0" style="font-size:14px">
              <tr>
                <td style="color:#64748b;padding:7px 0;
                           border-bottom:1px solid rgba(255,255,255,.05);width:40%">
                  Email
                </td>
                <td style="color:#60a5fa;font-weight:700;padding:7px 0;
                           border-bottom:1px solid rgba(255,255,255,.05);
                           text-align:right;font-family:\'Courier New\',monospace">
                  ' . $email . '
                </td>
              </tr>
              <tr>
                <td style="color:#64748b;padding:7px 0">Password</td>
                <td style="color:#34d399;font-weight:700;padding:7px 0;
                           text-align:right;font-family:\'Courier New\',monospace;
                           font-size:15px;letter-spacing:.5px">
                  ' . $password . '
                </td>
              </tr>
            </table>

            <div style="margin-top:14px;padding:10px 14px;
                        background:rgba(239,68,68,.08);
                        border:1px solid rgba(239,68,68,.2);
                        border-radius:7px;font-size:12px;color:#fca5a5;line-height:1.6">
              ⚠️ <strong>Segera ganti password</strong> setelah login pertama kali
              melalui menu Pengaturan Akun untuk menjaga keamanan.
            </div>
          </td>
        </tr>
      </table>

      <!-- Langkah selanjutnya -->
      <div style="font-size:12px;font-weight:700;color:#64748b;
                  text-transform:uppercase;letter-spacing:.6px;margin-bottom:14px">
        📋 &nbsp;Langkah Selanjutnya
      </div>
      <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:26px">
        <tr>
          <td style="padding:0 0 14px 0">
            <table cellpadding="0" cellspacing="0">
              <tr>
                <td style="width:28px;font-size:18px;vertical-align:top;padding-top:1px">✅</td>
                <td style="font-size:13px;color:#cbd5e1;line-height:1.6">
                  <strong style="color:#22c55e">Verifikasi Email Anda</strong> — Klik tombol
                  di bawah ini untuk mengaktifkan akun. Link verifikasi berlaku
                  <strong>24 jam</strong>.
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td style="padding:0 0 14px 0">
            <table cellpadding="0" cellspacing="0">
              <tr>
                <td style="width:28px;font-size:18px;vertical-align:top;padding-top:1px">🔐</td>
                <td style="font-size:13px;color:#cbd5e1;line-height:1.6">
                  <strong style="color:#f1f5f9">Login</strong> menggunakan email &amp; password di atas.
                </td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td>
            <table cellpadding="0" cellspacing="0">
              <tr>
                <td style="width:28px;font-size:18px;vertical-align:top;padding-top:1px">🔧</td>
                <td style="font-size:13px;color:#cbd5e1;line-height:1.6">
                  <strong style="color:#f1f5f9">Ganti password</strong> segera setelah login
                  pertama kali.
                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>

      <!-- Tombol verifikasi email -->
      <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px">
        <tr>
          <td align="center">
            <a href="' . $verifyUrl . '"
               style="display:inline-block;
                      background:linear-gradient(135deg,#22c55e,#16a34a);
                      color:#fff;font-size:15px;font-weight:800;
                      text-decoration:none;padding:14px 36px;
                      border-radius:9px;letter-spacing:.3px">
              ✅ &nbsp;Verifikasi Email Sekarang
            </a>
          </td>
        </tr>
      </table>

      <!-- Tombol login -->
      <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:10px">
        <tr>
          <td align="center">
            <a href="' . $loginUrl . '"
               style="display:inline-block;
                      background:transparent;
                      border:1px solid rgba(255,255,255,.18);
                      color:#94a3b8;font-size:13px;font-weight:600;
                      text-decoration:none;padding:11px 28px;
                      border-radius:8px">
              🔐 &nbsp;Halaman Login
            </a>
          </td>
        </tr>
      </table>

      <!-- Fallback link verifikasi -->
      <p style="font-size:11px;color:#475569;text-align:center;line-height:1.7;margin:18px 0 0">
        Jika tombol tidak berfungsi, salin &amp; tempel link ini di browser Anda:<br>
        <a href="' . $verifyUrl . '"
           style="color:#60a5fa;word-break:break-all;font-size:11px">
          ' . $verifyUrl . '
        </a>
      </p>

    </td>
  </tr>

  <!-- ── Footer ── -->
  <tr>
    <td style="background:#0b0f1a;border-radius:0 0 14px 14px;
               padding:18px 28px;text-align:center;
               border:1px solid rgba(255,255,255,.06);border-top:none">
      <div style="font-size:12px;color:#475569;margin-bottom:6px">
        <a href="' . SITE_URL . '"
           style="color:#64748b;text-decoration:none;font-weight:600">
          perkasasolusindo.co.id
        </a>
        &nbsp;·&nbsp;
        <a href="mailto:' . MAIL_ADMIN . '"
           style="color:#64748b;text-decoration:none">
          ' . MAIL_ADMIN . '
        </a>
      </div>
      <div style="font-size:11px;color:#334155;line-height:1.6">
        Email ini dikirim otomatis oleh sistem. Mohon tidak membalas email ini.<br>
        &copy; ' . $year . ' Perkasa Tech Solusindo. All rights reserved.
      </div>
    </td>
  </tr>

</table>
</td></tr>
</table>

</body>
</html>';
}

// ─────────────────────────────────────────────────────────────────────────────
/**
 * Render HTML email notifikasi ORDER BARU untuk ADMIN / OWNER.
 *
 * Keys array $d yang dibutuhkan:
 *   order_number, client_name, email, phonenumber,
 *   paket_name, paket_speed, alamat,
 *   is_new_user, admin_url (opsional — default ADMIN_URL)
 */
function render_email_order_admin(array $d): string
{
    $admin_url  = $d['admin_url'] ?? ADMIN_URL;
    $orders_url = ADMIN_ORDERS_URL;
    $year       = date('Y');
    $time_now   = date('d M Y, H:i') . ' WIB';

    $badge_new = $d['is_new_user']
        ? '<span style="background:#a78bfa;color:#1e1b4b;font-size:10px;font-weight:700;
                        padding:2px 9px;border-radius:99px;margin-left:8px;
                        vertical-align:middle">AKUN BARU</span>'
        : '<span style="background:#3b82f6;color:#fff;font-size:10px;font-weight:700;
                        padding:2px 9px;border-radius:99px;margin-left:8px;
                        vertical-align:middle">CLIENT LAMA</span>';

    return '<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Order WiFi Baru — ' . htmlspecialchars($d['order_number']) . '</title>
</head>
<body style="margin:0;padding:0;background:#0b0f1a;font-family:Arial,sans-serif;color:#f1f5f9">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#0b0f1a;padding:40px 20px">
<tr><td align="center">
<table width="100%" style="max-width:580px" cellpadding="0" cellspacing="0">

  <!-- ── Header admin notif ── -->
  <tr>
    <td style="background:#1a2235;border:1px solid rgba(255,255,255,.08);
               border-radius:14px 14px 0 0;padding:22px 28px">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td>
            <div style="font-size:11px;color:#64748b;text-transform:uppercase;
                        letter-spacing:.8px;margin-bottom:5px">🔔 Notifikasi Admin</div>
            <div style="font-size:20px;font-weight:900;color:#f1f5f9">
              Order WiFi Baru Masuk ' . $badge_new . '
            </div>
          </td>
          <td align="right" valign="top" style="white-space:nowrap">
            <div style="font-size:12px;color:#64748b;margin-top:4px">' . $time_now . '</div>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- ── Body ── -->
  <tr>
    <td style="background:#111827;padding:24px 28px;
               border:1px solid rgba(255,255,255,.06);border-top:none">

      <!-- Tabel detail -->
      <div style="font-size:12px;font-weight:700;color:#64748b;
                  text-transform:uppercase;letter-spacing:.6px;margin-bottom:12px">
        Detail Order &amp; Client
      </div>
      <table width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;margin-bottom:22px">
        <tr>
          <td style="color:#64748b;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.05);width:42%">Nomor Order</td>
          <td style="color:#f97316;font-weight:800;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.05);
                     text-align:right;font-family:\'Courier New\',monospace;font-size:15px">
            ' . htmlspecialchars($d['order_number']) . '
          </td>
        </tr>
        <tr>
          <td style="color:#64748b;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.05)">Paket</td>
          <td style="color:#f1f5f9;font-weight:600;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.05);text-align:right">
            ' . htmlspecialchars($d['paket_name']) . ' &nbsp;—&nbsp; ⚡ ' . htmlspecialchars($d['paket_speed']) . '
          </td>
        </tr>
        <tr>
          <td style="color:#64748b;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.05)">Nama Client</td>
          <td style="color:#f1f5f9;font-weight:600;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.05);text-align:right">
            ' . htmlspecialchars($d['client_name']) . '
          </td>
        </tr>
        <tr>
          <td style="color:#64748b;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.05)">Email</td>
          <td style="color:#3b82f6;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.05);text-align:right">
            <a href="mailto:' . htmlspecialchars($d['email']) . '"
               style="color:#3b82f6;text-decoration:none">
              ' . htmlspecialchars($d['email']) . '
            </a>
          </td>
        </tr>
        <tr>
          <td style="color:#64748b;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.05)">No. HP / WhatsApp</td>
          <td style="color:#22c55e;font-weight:600;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.05);text-align:right">
            <a href="https://wa.me/' . preg_replace('/[^0-9]/', '', $d['phonenumber']) . '"
               style="color:#22c55e;text-decoration:none">
              ' . htmlspecialchars($d['phonenumber']) . '
            </a>
          </td>
        </tr>
        <tr>
          <td style="color:#64748b;padding:8px 0">Alamat Pasang</td>
          <td style="color:#f1f5f9;padding:8px 0;text-align:right">
            ' . htmlspecialchars($d['alamat']) . '
          </td>
        </tr>
      </table>

      <!-- Status banner -->
      <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:22px">
        <tr>
          <td style="background:rgba(234,179,8,.07);
                     border:1px solid rgba(234,179,8,.22);
                     border-left:4px solid #eab308;
                     border-radius:0 8px 8px 0;
                     padding:13px 16px;font-size:13px;color:#fde68a;line-height:1.6">
            ⏳ <strong>Status: Menunggu Verifikasi</strong><br>
            <span style="color:#94a3b8">
              Silakan buka dashboard admin untuk memverifikasi data KTP dan
              mengecek coverage area. Verifikasi segera agar jadwal instalasi
              dapat dijadwalkan tepat waktu.
            </span>
          </td>
        </tr>
      </table>

      <!-- CTA buttons -->
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td align="center" style="padding-bottom:10px">
            <a href="' . htmlspecialchars($orders_url) . '"
               style="display:inline-block;
                      background:linear-gradient(135deg,#f97316,#ea580c);
                      color:#fff;font-size:14px;font-weight:700;
                      text-decoration:none;padding:13px 28px;
                      border-radius:8px;margin-right:8px">
              📋 &nbsp;Lihat Semua Order
            </a>
            <a href="' . htmlspecialchars($admin_url) . '"
               style="display:inline-block;
                      background:transparent;
                      border:1px solid rgba(255,255,255,.18);
                      color:#94a3b8;font-size:14px;font-weight:600;
                      text-decoration:none;padding:13px 28px;
                      border-radius:8px">
              🖥️ &nbsp;Dashboard Admin
            </a>
          </td>
        </tr>
      </table>

    </td>
  </tr>

  <!-- ── Footer ── -->
  <tr>
    <td style="background:#0b0f1a;border-radius:0 0 14px 14px;
               padding:16px 28px;text-align:center;
               border:1px solid rgba(255,255,255,.06);border-top:none">
      <div style="font-size:11px;color:#334155;line-height:1.7">
        Notifikasi internal sistem Perkasa Solusindo &nbsp;·&nbsp; Jangan dibalas.<br>
        &copy; ' . $year . ' Perkasa Tech Solusindo.
      </div>
    </td>
  </tr>

</table>
</td></tr>
</table>
</body>
</html>';
}

// ─────────────────────────────────────────────────────────────────────────────
/**
 * Render HTML email konfirmasi PEMBAYARAN DITERIMA untuk CLIENT.
 *
 * Keys array $d yang dibutuhkan:
 *   order_number, client_name, email,
 *   paket_name, paket_speed,
 *   login_url (opsional — default LOGIN_URL)
 */
function render_email_payment_received(array $d): string
{
    $login_url = $d['login_url'] ?? LOGIN_URL;
    $year      = date('Y');

    return '<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Pembayaran Diterima — Perkasa Solusindo</title>
</head>
<body style="margin:0;padding:0;background:#0b0f1a;font-family:Arial,sans-serif;color:#f1f5f9">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#0b0f1a;padding:40px 20px">
<tr><td align="center">
<table width="100%" style="max-width:580px" cellpadding="0" cellspacing="0">

  <!-- ── Header brand ── -->
  <tr>
    <td style="background:linear-gradient(135deg,#f97316,#ea580c);
               border-radius:14px 14px 0 0;padding:28px 32px;text-align:center">
      <div style="font-size:30px;margin-bottom:8px">📡</div>
      <div style="font-size:22px;font-weight:900;color:#fff;letter-spacing:.5px">
        PERKASA <span style="color:#fed7aa">SOLUSINDO</span>
      </div>
      <div style="font-size:12px;color:rgba(255,255,255,.75);margin-top:4px">
        Solusi Teknologi Terpadu
      </div>
    </td>
  </tr>

  <!-- ── Success hero ── -->
  <tr>
    <td style="background:#111827;padding:32px 32px 0;text-align:center">
      <div style="width:68px;height:68px;
                  background:rgba(34,197,94,.12);border:2px solid #22c55e;
                  border-radius:50%;display:inline-block;
                  font-size:30px;line-height:64px;margin-bottom:16px">✓</div>
      <h1 style="font-size:24px;font-weight:900;color:#f1f5f9;margin:0 0 10px">
        Pembayaran Berhasil Diterima! 🎉
      </h1>
      <p style="font-size:15px;color:#94a3b8;margin:0;line-height:1.6">
        Halo, <strong style="color:#f1f5f9">' . htmlspecialchars($d['client_name']) . '</strong>!<br>
        Terima kasih telah melakukan pelunasan untuk pesanan WiFi Anda.
      </p>
    </td>
  </tr>

  <!-- ── Nomor order badge ── -->
  <tr>
    <td style="background:#111827;padding:20px 32px 0">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td style="background:rgba(249,115,22,.08);
                     border:1px solid rgba(249,115,22,.25);
                     border-radius:10px;padding:16px 20px;text-align:center">
            <div style="font-size:11px;color:#64748b;text-transform:uppercase;
                        letter-spacing:.8px;margin-bottom:6px">Nomor Order Anda</div>
            <div style="font-size:24px;font-weight:900;color:#f97316;letter-spacing:2px;
                        font-family:\'Courier New\',monospace">
              ' . htmlspecialchars($d['order_number']) . '
            </div>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- ── Detail paket ── -->
  <tr>
    <td style="background:#111827;padding:24px 32px 0">
      <div style="font-size:12px;font-weight:700;color:#64748b;
                  text-transform:uppercase;letter-spacing:.8px;margin-bottom:14px">
        Detail Pesanan
      </div>
      <table width="100%" cellpadding="0" cellspacing="0" style="font-size:14px">
        <tr>
          <td style="color:#64748b;padding:9px 0;border-bottom:1px solid rgba(255,255,255,.06);width:46%">Paket</td>
          <td style="color:#f1f5f9;font-weight:600;padding:9px 0;border-bottom:1px solid rgba(255,255,255,.06);text-align:right">
            ' . htmlspecialchars($d['paket_name']) . '
          </td>
        </tr>
        <tr>
          <td style="color:#64748b;padding:9px 0;border-bottom:1px solid rgba(255,255,255,.06)">Kecepatan</td>
          <td style="color:#3b82f6;font-weight:600;padding:9px 0;border-bottom:1px solid rgba(255,255,255,.06);text-align:right">
            ⚡ ' . htmlspecialchars($d['paket_speed']) . '
          </td>
        </tr>
        <tr>
          <td style="color:#64748b;padding:9px 0">Status Pembayaran</td>
          <td style="color:#22c55e;font-weight:700;padding:9px 0;text-align:right">
            ✅ Sudah Bayar — Menunggu Verifikasi
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- ── Pesan utama ── -->
  <tr>
    <td style="background:#111827;padding:20px 32px 0">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td style="background:rgba(34,197,94,.07);
                     border:1px solid rgba(34,197,94,.22);
                     border-left:4px solid #22c55e;
                     border-radius:0 10px 10px 0;
                     padding:16px 18px;font-size:14px;line-height:1.7;color:#e2e8f0">
            <p style="margin:0 0 8px;font-weight:700;color:#22c55e;font-size:15px">
              🙏 Terima kasih atas pelunasan Anda
            </p>
            <p style="margin:0 0 8px">
              Bukti pembayaran Anda telah kami terima dan saat ini sedang dalam proses
              <strong>verifikasi oleh tim kami</strong>. Mohon ditunggu sebentar — kami akan
              segera mengirimkan <strong>detail pesanan</strong> dan melakukan
              <strong>aktivasi layanan</strong> agar WiFi Anda dapat segera Anda nikmati.
            </p>
            <p style="margin:0;color:#fb923c;font-weight:600">
              🔔 Pantau <strong>dashboard klien</strong> Anda secara berkala untuk
              mendapatkan update status pesanan terbaru.
            </p>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- ── Info email ── -->
  <tr>
    <td style="background:#111827;padding:20px 32px 0">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td style="background:rgba(59,130,246,.07);
                     border:1px solid rgba(59,130,246,.2);
                     border-radius:10px;padding:14px 16px;
                     font-size:13px;color:#94a3b8;line-height:1.7">
            <p style="margin:0">
              📧 Update status order akan dikirimkan ke
              <strong style="color:#f1f5f9">' . htmlspecialchars($d['email']) . '</strong>.
              Pastikan email ini aktif.
            </p>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- ── CTA tombol dashboard ── -->
  <tr>
    <td style="background:#111827;padding:28px 32px">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td align="center">
            <a href="' . htmlspecialchars($login_url) . '"
               style="display:inline-block;
                      background:linear-gradient(135deg,#f97316,#ea580c);
                      color:#fff;font-size:15px;font-weight:700;
                      text-decoration:none;padding:14px 36px;
                      border-radius:8px;letter-spacing:.5px">
              🚀 &nbsp;Login ke Dashboard Klien
            </a>
          </td>
        </tr>
        <tr>
          <td style="text-align:center;padding-top:10px;font-size:12px;color:#475569">
            atau kunjungi:
            <a href="' . SITE_URL . '"
               style="color:#f97316;text-decoration:none">' . SITE_URL . '</a>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- ── Footer ── -->
  <tr>
    <td style="background:#0b0f1a;border-radius:0 0 14px 14px;
               padding:22px 32px;text-align:center;
               border-top:1px solid rgba(255,255,255,.06)">
      <div style="font-size:13px;color:#64748b;line-height:1.9">
        <strong style="color:#94a3b8">PT. Perkasa Tech Solusindo</strong><br>
        Jln. KedungRejo, Wedoroklurak, Candi, Sidoarjo, Jawa Timur 61271<br>
        📞 <a href="tel:+6281246684665"
              style="color:#f97316;text-decoration:none">+62 812-4668-4665</a>
        &nbsp;·&nbsp;
        ✉️ <a href="mailto:info-perkasa@perkasasolusindo.co.id"
              style="color:#f97316;text-decoration:none">info-perkasa@perkasasolusindo.co.id</a>
      </div>
      <div style="margin-top:14px;font-size:11px;color:#334155">
        Email ini dikirim otomatis oleh sistem. Mohon tidak membalas email ini.<br>
        &copy; ' . $year . ' Perkasa Tech Solusindo. All rights reserved.
      </div>
    </td>
  </tr>

</table>
</td></tr>
</table>

</body>
</html>';
}

// ─────────────────────────────────────────────────────────────────────────────
/**
 * Render email aktivasi WiFi — dikirim saat admin memasukkan ID Pelanggan.
 *
 * Keys array $d:
 *   id_pelanggan, client_name, order_number,
 *   paket_name, paket_price, tanggal_expire, tgl_aktif
 */
function render_email_aktivasi_wifi(array $d): string
{
    $rupiah = function($n) { return 'Rp ' . number_format((float)$n, 0, ',', '.'); };
    $year   = date('Y');

    $tglAktif   = isset($d['tgl_aktif'])       ? date('d M Y', strtotime($d['tgl_aktif']))       : date('d M Y');
    $tglExpire  = isset($d['tanggal_expire'])   ? date('d M Y', strtotime($d['tanggal_expire']))  : '–';
    $tglTagihan = $tglExpire; // tanggal pembayaran berikutnya = tanggal expire

    // Hitung tanggal jatuh tempo OTOMATIS nonaktif (tanggal 21 setelah expire)
    $tglNonaktif = isset($d['tanggal_expire'])
        ? date('d M Y', strtotime('+1 day', strtotime($d['tanggal_expire'])))
        : '–';

    $rows = [
        ['ID Pelanggan',   '<span style="font-family:\'Courier New\',monospace;font-size:16px;font-weight:900;color:#22c55e;letter-spacing:1.5px">' . htmlspecialchars($d['id_pelanggan']) . '</span>'],
        ['Nama Lengkap',   htmlspecialchars($d['client_name'])],
        ['Paket Layanan',  htmlspecialchars($d['paket_name'])],
        ['Nomer Order',    '<span style="font-family:\'Courier New\',monospace;font-weight:700;color:#f97316">' . htmlspecialchars($d['order_number']) . '</span>'],
        ['Tanggal Expire', '<strong style="color:#fbbf24">' . $tglExpire . '</strong>'],
        ['Total Tagihan',  '<strong>' . $rupiah($d['paket_price']) . '</strong> / bulan'],
    ];

    $rows_html = '';
    foreach ($rows as [$label, $value]) {
        $rows_html .= '
        <tr>
          <td style="padding:11px 0;color:#94a3b8;font-size:14px;border-bottom:1px solid rgba(255,255,255,.06);vertical-align:top;width:44%">'
              . $label . '</td>
          <td style="padding:11px 0 11px 12px;font-size:14px;color:#f1f5f9;border-bottom:1px solid rgba(255,255,255,.06);font-weight:600;text-align:right">'
              . $value . '</td>
        </tr>';
    }

    return '<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Layanan WiFi Aktif — Perkasa Solusindo</title>
</head>
<body style="margin:0;padding:0;background:#0b0f1a;font-family:Arial,sans-serif;color:#f1f5f9">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#0b0f1a;padding:40px 20px">
<tr><td align="center">
<table width="100%" style="max-width:580px" cellpadding="0" cellspacing="0">

  <!-- Header brand -->
  <tr>
    <td style="background:linear-gradient(135deg,#22c55e,#16a34a);
               border-radius:14px 14px 0 0;padding:28px 32px;text-align:center">
      <div style="font-size:32px;margin-bottom:8px">📶</div>
      <div style="font-size:22px;font-weight:900;color:#fff;letter-spacing:.5px">
        PERKASA <span style="color:#bbf7d0">SOLUSINDO</span>
      </div>
      <div style="font-size:12px;color:rgba(255,255,255,.75);margin-top:4px">
        Solusi Teknologi Terpadu
      </div>
    </td>
  </tr>

  <!-- Hero aktivasi -->
  <tr>
    <td style="background:#111827;padding:32px 32px 24px;text-align:center">
      <div style="width:72px;height:72px;
                  background:rgba(34,197,94,.12);border:2px solid #22c55e;
                  border-radius:50%;display:inline-block;
                  font-size:32px;line-height:68px;margin-bottom:16px">🚀</div>
      <h1 style="font-size:24px;font-weight:900;color:#f1f5f9;margin:0 0 10px">
        Layanan WiFi Anda Sudah Aktif!
      </h1>
      <p style="font-size:15px;color:#94a3b8;margin:0;line-height:1.7">
        Halo, <strong style="color:#f1f5f9">' . htmlspecialchars($d['client_name']) . '</strong>!<br>
        Selamat — internet Anda telah aktif sejak <strong style="color:#22c55e">' . $tglAktif . '</strong>.
      </p>
    </td>
  </tr>

  <!-- Tabel informasi akun -->
  <tr>
    <td style="background:#111827;padding:0 32px 24px">
      <div style="font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;
                  letter-spacing:.8px;margin-bottom:14px">Informasi Akun</div>
      <table width="100%" cellpadding="0" cellspacing="0">
        ' . $rows_html . '
      </table>
    </td>
  </tr>

  <!-- Info tagihan & perpanjangan -->
  <tr>
    <td style="background:#111827;padding:0 32px 24px">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td style="background:rgba(251,191,36,.07);
                     border:1px solid rgba(251,191,36,.25);
                     border-left:4px solid #fbbf24;
                     border-radius:0 10px 10px 0;
                     padding:16px 18px;font-size:14px;line-height:1.8;color:#e2e8f0">
            <p style="margin:0 0 8px;font-weight:800;color:#fbbf24;font-size:15px">
              📅 Info Tagihan &amp; Perpanjangan
            </p>
            <p style="margin:0 0 6px">
              Tagihan berikutnya jatuh tempo pada
              <strong style="color:#fbbf24">' . $tglTagihan . '</strong>.
            </p>
            <p style="margin:0 0 6px;color:#94a3b8;font-size:13px">
              Lakukan pembayaran sebelum tanggal tersebut untuk memastikan layanan tidak terputus.
            </p>
            <p style="margin:0;color:#f87171;font-size:13px;font-weight:600">
              ⚠️ Jika belum dibayar hingga <strong>' . $tglTagihan . '</strong>, layanan WiFi akan otomatis
              dinonaktifkan mulai <strong>' . $tglNonaktif . '</strong>.
            </p>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- CTA dashboard -->
  <tr>
    <td style="background:#111827;padding:10px 32px 32px">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td align="center">
            <a href="' . DASHBOARD_URL . '"
               style="display:inline-block;
                      background:linear-gradient(135deg,#22c55e,#16a34a);
                      color:#fff;font-size:15px;font-weight:700;
                      text-decoration:none;padding:14px 36px;
                      border-radius:8px;letter-spacing:.5px">
              📊 &nbsp;Pantau di Dashboard Klien
            </a>
          </td>
        </tr>
        <tr>
          <td style="text-align:center;padding-top:10px;font-size:12px;color:#475569">
            atau kunjungi:
            <a href="' . SITE_URL . '" style="color:#22c55e;text-decoration:none">' . SITE_URL . '</a>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- Footer -->
  <tr>
    <td style="background:#0b0f1a;border-radius:0 0 14px 14px;
               padding:22px 32px;text-align:center;
               border-top:1px solid rgba(255,255,255,.06)">
      <div style="font-size:13px;color:#64748b;line-height:1.9">
        <strong style="color:#94a3b8">PT. Perkasa Tech Solusindo</strong><br>
        Jln. KedungRejo, Wedoroklurak, Candi, Sidoarjo, Jawa Timur 61271<br>
        📞 <a href="tel:+6281246684665" style="color:#22c55e;text-decoration:none">+62 812-4668-4665</a>
        &nbsp;·&nbsp;
        ✉️ <a href="mailto:info-perkasa@perkasasolusindo.co.id"
              style="color:#22c55e;text-decoration:none">info-perkasa@perkasasolusindo.co.id</a>
      </div>
      <div style="margin-top:14px;font-size:11px;color:#334155">
        Email ini dikirim otomatis oleh sistem. Mohon tidak membalas email ini.<br>
        &copy; ' . $year . ' Perkasa Tech Solusindo. All rights reserved.
      </div>
    </td>
  </tr>

</table>
</td></tr>
</table>

</body>
</html>';
}


// ─────────────────────────────────────────────────────────────────────────────
/**
 * Render HTML email konfirmasi ORDER HOSTING untuk CLIENT.
 *
 * Keys array $d:
 *   order_number, client_name, email,
 *   paket_name, domain, periode, total, is_new_user
 */
function render_email_order_hosting_client(array $d): string
{
    $rupiah     = function($n) { return 'Rp ' . number_format((float)$n, 0, ',', '.'); };
    $year       = date('Y');
    $login_url  = LOGIN_URL;

    $account_note = $d['is_new_user']
        ? '<p style="margin:0 0 6px">🆕 <strong style="color:#a78bfa">Akun baru</strong> sudah dibuat otomatis. Gunakan password yang Anda daftarkan untuk login.</p>'
        : '';

    $diskon_pct = 0;
    if ($d['periode'] >= 12) $diskon_pct = 10;
    elseif ($d['periode'] >= 6) $diskon_pct = 5;

    $diskon_txt = $diskon_pct ? " (hemat {$diskon_pct}%)" : '';

    // Deadline bayar
    $deadline_str = '';
    if (!empty($d['payment_deadline'])) {
        $deadline_str = date('d M Y H:i', strtotime($d['payment_deadline'])) . ' WIB';
    }

    // Invoice ID
    $invoice_no = !empty($d['invoice_id']) ? '#INV-' . str_pad((int)$d['invoice_id'], 5, '0', STR_PAD_LEFT) : '';

    $steps_data = [
        ['☁️', 'Order Diterima',          'Formulir hosting Anda sudah masuk ke sistem kami',                                    true],
        ['💳', 'Lakukan Pembayaran',       'Transfer ke rekening BCA 0184246283 a.n. Tech Perkasa Solusindo, lalu upload bukti di dashboard', false],
        ['🔍', 'Verifikasi Admin',          'Tim kami memverifikasi bukti pembayaran Anda (biasanya dalam 1 jam kerja)',            false],
        ['⚙️', 'Aktivasi cPanel',          'Akun cPanel & credential dikirim ke email Anda secara otomatis',                      false],
        ['🌐', 'Website Online',            'Upload file Anda dan website siap diakses!',                                          false],
    ];

    $steps_html = '';
    foreach ($steps_data as $i => [$icon, $title, $desc, $active]) {
        $title_color = $active ? '#22c55e' : '#e2e8f0';
        $border      = ($i < count($steps_data) - 1) ? 'border-bottom:1px solid rgba(255,255,255,.06)' : '';
        $steps_html .= "
        <tr>
          <td style='width:36px;padding:0 12px 20px 0;vertical-align:top;font-size:20px;line-height:1'>{$icon}</td>
          <td style='padding-bottom:20px;{$border}'>
            <div style='font-size:14px;font-weight:700;color:{$title_color};margin:0 0 3px'>{$title}" . ($active ? ' ✓' : '') . "</div>
            <div style='font-size:12px;color:#64748b;margin:0'>{$desc}</div>
          </td>
        </tr>";
    }

    return '<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Order Hosting Berhasil — Perkasa Solusindo</title>
</head>
<body style="margin:0;padding:0;background:#0b0f1a;font-family:Arial,sans-serif;color:#f1f5f9">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#0b0f1a;padding:40px 20px">
<tr><td align="center">
<table width="100%" style="max-width:580px" cellpadding="0" cellspacing="0">

  <!-- Header brand -->
  <tr>
    <td style="background:linear-gradient(135deg,#7c3aed,#c026d3);border-radius:14px 14px 0 0;padding:28px 32px;text-align:center">
      <div style="font-size:30px;margin-bottom:8px">☁️</div>
      <div style="font-size:22px;font-weight:900;color:#fff;letter-spacing:.5px">PERKASA <span style="color:#e9d5ff">SOLUSINDO</span></div>
      <div style="font-size:12px;color:rgba(255,255,255,.75);margin-top:4px">Layanan Hosting & Domain</div>
    </td>
  </tr>

  <!-- Hero success -->
  <tr>
    <td style="background:#111827;padding:32px 32px 0;text-align:center">
      <div style="width:68px;height:68px;background:rgba(34,197,94,.12);border:2px solid #22c55e;border-radius:50%;display:inline-block;font-size:30px;line-height:64px;margin-bottom:16px">✓</div>
      <h1 style="font-size:24px;font-weight:900;color:#f1f5f9;margin:0 0 10px">Order Hosting Berhasil! 🎉</h1>
      <p style="font-size:15px;color:#94a3b8;margin:0;line-height:1.6">
        Halo, <strong style="color:#f1f5f9">' . htmlspecialchars($d['client_name']) . '</strong>!<br>
        Order hosting paket <strong style="color:#c084fc">' . htmlspecialchars($d['paket_name']) . '</strong> sudah kami terima.
      </p>
    </td>
  </tr>

  <!-- Nomor order badge -->
  <tr>
    <td style="background:#111827;padding:20px 32px 0">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td style="background:rgba(124,58,237,.1);border:1px solid rgba(124,58,237,.3);border-radius:10px;padding:16px 20px;text-align:center">
            <div style="font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.8px;margin-bottom:6px">Nomor Order Anda</div>
            <div style="font-size:22px;font-weight:900;color:#a78bfa;letter-spacing:2px;font-family:\'Courier New\',monospace">' . htmlspecialchars($d['order_number']) . '</div>
            ' . ($invoice_no ? '<div style="font-size:11px;color:#64748b;margin-top:4px">Invoice: <strong style="color:#94a3b8">' . $invoice_no . '</strong></div>' : '') . '
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- Detail order -->
  <tr>
    <td style="background:#111827;padding:24px 32px 0">
      <div style="font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.8px;margin-bottom:14px">Detail Pesanan</div>
      <table width="100%" cellpadding="0" cellspacing="0" style="font-size:14px">
        <tr>
          <td style="color:#64748b;padding:9px 0;border-bottom:1px solid rgba(255,255,255,.06);width:46%">Paket</td>
          <td style="color:#f1f5f9;font-weight:600;padding:9px 0;border-bottom:1px solid rgba(255,255,255,.06);text-align:right">' . htmlspecialchars($d['paket_name']) . '</td>
        </tr>
        <tr>
          <td style="color:#64748b;padding:9px 0;border-bottom:1px solid rgba(255,255,255,.06)">Domain</td>
          <td style="color:#14b8a6;font-weight:600;padding:9px 0;border-bottom:1px solid rgba(255,255,255,.06);text-align:right">🌐 ' . htmlspecialchars($d['domain']) . '</td>
        </tr>
        <tr>
          <td style="color:#64748b;padding:9px 0;border-bottom:1px solid rgba(255,255,255,.06)">Periode</td>
          <td style="color:#f1f5f9;font-weight:600;padding:9px 0;border-bottom:1px solid rgba(255,255,255,.06);text-align:right">' . (int)$d['periode'] . ' Bulan' . $diskon_txt . '</td>
        </tr>
        <tr>
          <td style="color:#64748b;padding:9px 0;border-bottom:1px solid rgba(255,255,255,.06)">Total Tagihan</td>
          <td style="color:#f97316;font-weight:800;font-size:17px;padding:9px 0;border-bottom:1px solid rgba(255,255,255,.06);text-align:right">' . $rupiah($d['total']) . '</td>
        </tr>
        ' . ($deadline_str ? '
        <tr>
          <td style="color:#64748b;padding:9px 0">⏰ Batas Bayar</td>
          <td style="color:#f87171;font-weight:700;padding:9px 0;text-align:right">' . $deadline_str . '</td>
        </tr>' : '') . '
      </table>
    </td>
  </tr>

  <!-- Info Rekening Pembayaran -->
  <tr>
    <td style="background:#111827;padding:20px 32px 0">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td style="background:rgba(249,115,22,.07);border:1.5px solid rgba(249,115,22,.3);border-radius:12px;padding:18px 20px">
            <div style="font-size:12px;font-weight:700;color:#f97316;text-transform:uppercase;letter-spacing:.07em;margin-bottom:12px">
              💳 Rekening Pembayaran
            </div>
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td style="font-size:13px;color:#94a3b8;padding-bottom:4px">Bank</td>
                <td style="font-size:13px;font-weight:700;color:#f1f5f9;text-align:right">BCA</td>
              </tr>
              <tr>
                <td style="font-size:13px;color:#94a3b8;padding-bottom:4px">Nomor Rekening</td>
                <td style="font-size:20px;font-weight:900;color:#fbbf24;text-align:right;letter-spacing:2px;font-family:\'Courier New\',monospace">0184246283</td>
              </tr>
              <tr>
                <td style="font-size:13px;color:#94a3b8">Atas Nama</td>
                <td style="font-size:13px;font-weight:700;color:#f1f5f9;text-align:right">TECH PERKASA SOLUSINDO</td>
              </tr>
            </table>
            <div style="margin-top:12px;background:rgba(248,113,113,.1);border:1px solid rgba(248,113,113,.25);border-radius:8px;padding:10px 14px;font-size:12px;color:#fca5a5;line-height:1.7">
              ⚠️ Setelah transfer, <strong>upload bukti pembayaran</strong> di Dashboard → Layanan Hosting.<br>
              Order yang melewati batas waktu 24 jam akan <strong>dihapus otomatis</strong>.
            </div>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- Timeline -->
  <tr>
    <td style="background:#111827;padding:24px 32px 0">
      <div style="font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.8px;margin-bottom:16px">Status Proses</div>
      <table width="100%" cellpadding="0" cellspacing="0">' . $steps_html . '</table>
    </td>
  </tr>

  <!-- Info akun -->
  <tr>
    <td style="background:#111827;padding:20px 32px 0">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td style="background:rgba(59,130,246,.07);border:1px solid rgba(59,130,246,.2);border-radius:10px;padding:14px 16px;font-size:13px;color:#94a3b8;line-height:1.7">
            ' . $account_note . '
            <p style="margin:0">📧 Update order akan dikirimkan ke <strong style="color:#f1f5f9">' . htmlspecialchars($d['email']) . '</strong>.</p>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- CTA -->
  <tr>
    <td style="background:#111827;padding:28px 32px">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td align="center" style="padding-bottom:10px">
            <a href="' . SITE_URL . '/client/client_dashboard.php?view=layanan_hosting"
               style="display:inline-block;background:linear-gradient(135deg,#7c3aed,#c026d3);color:#fff;font-size:15px;font-weight:700;text-decoration:none;padding:14px 36px;border-radius:8px;letter-spacing:.5px">
              ☁️ &nbsp;Upload Bukti & Pantau Status
            </a>
          </td>
        </tr>
        <tr>
          <td align="center" style="padding-top:4px">
            <a href="https://wa.me/6281246684665?text=Halo+Perkasa,+saya+baru+order+hosting+' . urlencode($d['order_number']) . '"
               style="display:inline-block;background:rgba(37,211,102,.12);border:1px solid rgba(37,211,102,.3);color:#25d366;font-size:13px;font-weight:700;text-decoration:none;padding:10px 24px;border-radius:8px">
              💬 &nbsp;Chat Admin via WhatsApp
            </a>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- Footer -->
  <tr>
    <td style="background:#0b0f1a;border-radius:0 0 14px 14px;padding:22px 32px;text-align:center;border-top:1px solid rgba(255,255,255,.06)">
      <div style="font-size:13px;color:#64748b;line-height:1.9">
        <strong style="color:#94a3b8">PT. Perkasa Tech Solusindo</strong><br>
        Jln. KedungRejo, Wedoroklurak, Candi, Sidoarjo, Jawa Timur 61271<br>
        📞 <a href="tel:+6281246684665" style="color:#7c3aed;text-decoration:none">+62 812-4668-4665</a>
        &nbsp;·&nbsp;
        ✉️ <a href="mailto:info-perkasa@perkasasolusindo.co.id" style="color:#7c3aed;text-decoration:none">info-perkasa@perkasasolusindo.co.id</a>
      </div>
      <div style="margin-top:14px;font-size:11px;color:#334155">
        Email ini dikirim otomatis oleh sistem. Mohon tidak membalas email ini.<br>
        &copy; ' . $year . ' Perkasa Tech Solusindo. All rights reserved.
      </div>
    </td>
  </tr>

</table>
</td></tr>
</table>
</body>
</html>';
}



// ─────────────────────────────────────────────────────────────────────────────
/**
 * Render HTML email notifikasi ORDER HOSTING BARU untuk ADMIN / OWNER.
 *
 * Keys array $d:
 *   order_number, client_name, email, phonenumber,
 *   paket_name, domain, periode, total, is_new_user
 */
function render_email_order_hosting_admin(array $d): string
{
    $rupiah    = function($n) { return 'Rp ' . number_format((float)$n, 0, ',', '.'); };
    $year      = date('Y');
    $time_now  = date('d M Y, H:i') . ' WIB';
    $phone_num = preg_replace('/[^0-9]/', '', $d['phonenumber'] ?? '');

    $badge = $d['is_new_user']
        ? '<span style="background:#a78bfa;color:#1e1b4b;font-size:10px;font-weight:700;padding:2px 9px;border-radius:99px;margin-left:8px;vertical-align:middle">AKUN BARU</span>'
        : '<span style="background:#3b82f6;color:#fff;font-size:10px;font-weight:700;padding:2px 9px;border-radius:99px;margin-left:8px;vertical-align:middle">CLIENT LAMA</span>';

    $diskon_pct = 0;
    if ($d['periode'] >= 12) $diskon_pct = 10;
    elseif ($d['periode'] >= 6) $diskon_pct = 5;

    return '<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Order Hosting Baru — ' . htmlspecialchars($d['order_number']) . '</title>
</head>
<body style="margin:0;padding:0;background:#0b0f1a;font-family:Arial,sans-serif;color:#f1f5f9">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#0b0f1a;padding:40px 20px">
<tr><td align="center">
<table width="100%" style="max-width:580px" cellpadding="0" cellspacing="0">

  <!-- Header admin -->
  <tr>
    <td style="background:#1a2235;border:1px solid rgba(255,255,255,.08);border-radius:14px 14px 0 0;padding:22px 28px">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td>
            <div style="font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.8px;margin-bottom:5px">☁️ Notifikasi Admin — Hosting</div>
            <div style="font-size:20px;font-weight:900;color:#f1f5f9">Order Hosting Baru Masuk ' . $badge . '</div>
          </td>
          <td align="right" valign="top">
            <div style="font-size:12px;color:#64748b;margin-top:4px;white-space:nowrap">' . $time_now . '</div>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- Body -->
  <tr>
    <td style="background:#111827;padding:24px 28px;border:1px solid rgba(255,255,255,.06);border-top:none">

      <div style="font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.6px;margin-bottom:12px">Detail Order & Client</div>
      <table width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;margin-bottom:22px">
        <tr>
          <td style="color:#64748b;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.05);width:42%">Nomor Order</td>
          <td style="color:#a78bfa;font-weight:800;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.05);text-align:right;font-family:\'Courier New\',monospace;font-size:15px">' . htmlspecialchars($d['order_number']) . '</td>
        </tr>
        <tr>
          <td style="color:#64748b;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.05)">Paket</td>
          <td style="color:#f1f5f9;font-weight:600;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.05);text-align:right">' . htmlspecialchars($d['paket_name']) . '</td>
        </tr>
        <tr>
          <td style="color:#64748b;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.05)">Domain</td>
          <td style="color:#14b8a6;font-weight:600;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.05);text-align:right">🌐 ' . htmlspecialchars($d['domain']) . '</td>
        </tr>
        <tr>
          <td style="color:#64748b;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.05)">Periode</td>
          <td style="color:#f1f5f9;font-weight:600;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.05);text-align:right">' . (int)$d['periode'] . ' bulan' . ($diskon_pct ? " (diskon {$diskon_pct}%)" : '') . '</td>
        </tr>
        <tr>
          <td style="color:#64748b;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.05)">Total</td>
          <td style="color:#f97316;font-weight:800;font-size:15px;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.05);text-align:right">' . $rupiah($d['total']) . '</td>
        </tr>
        <tr>
          <td style="color:#64748b;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.05)">Nama Client</td>
          <td style="color:#f1f5f9;font-weight:600;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.05);text-align:right">' . htmlspecialchars($d['client_name']) . '</td>
        </tr>
        <tr>
          <td style="color:#64748b;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.05)">Email</td>
          <td style="color:#3b82f6;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.05);text-align:right">
            <a href="mailto:' . htmlspecialchars($d['email']) . '" style="color:#3b82f6;text-decoration:none">' . htmlspecialchars($d['email']) . '</a>
          </td>
        </tr>
        <tr>
          <td style="color:#64748b;padding:8px 0">No. HP / WhatsApp</td>
          <td style="color:#22c55e;font-weight:600;padding:8px 0;text-align:right">
            <a href="https://wa.me/' . $phone_num . '" style="color:#22c55e;text-decoration:none">' . htmlspecialchars($d['phonenumber'] ?? '-') . '</a>
          </td>
        </tr>
      </table>

      <!-- Status banner -->
      <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:22px">
        <tr>
          <td style="background:rgba(124,58,237,.08);border:1px solid rgba(124,58,237,.25);border-left:4px solid #7c3aed;border-radius:0 8px 8px 0;padding:13px 16px;font-size:13px;color:#c4b5fd;line-height:1.6">
            ⏳ <strong>Status: Menunggu Aktivasi</strong><br>
            <span style="color:#94a3b8">Siapkan server hosting, lalu hubungi client via WhatsApp untuk instruksi pembayaran.</span>
          </td>
        </tr>
      </table>

      <!-- CTA -->
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td align="center">
            <a href="' . ADMIN_ORDERS_URL . '" style="display:inline-block;background:linear-gradient(135deg,#7c3aed,#c026d3);color:#fff;font-size:14px;font-weight:700;text-decoration:none;padding:13px 28px;border-radius:8px;margin-right:8px">
              📋 &nbsp;Lihat Semua Order
            </a>
            <a href="https://wa.me/' . $phone_num . '" style="display:inline-block;background:rgba(37,211,102,.12);border:1px solid rgba(37,211,102,.3);color:#25d366;font-size:14px;font-weight:600;text-decoration:none;padding:13px 28px;border-radius:8px">
              💬 &nbsp;WA Client
            </a>
          </td>
        </tr>
      </table>

    </td>
  </tr>

  <!-- Footer -->
  <tr>
    <td style="background:#0b0f1a;border-radius:0 0 14px 14px;padding:16px 28px;text-align:center;border:1px solid rgba(255,255,255,.06);border-top:none">
      <div style="font-size:11px;color:#334155;line-height:1.7">
        Notifikasi internal sistem Perkasa Solusindo &nbsp;·&nbsp; Jangan dibalas.<br>
        &copy; ' . $year . ' Perkasa Tech Solusindo.
      </div>
    </td>
  </tr>

</table>
</td></tr>
</table>
</body>
</html>';
}

// ─────────────────────────────────────────────────────────────────────────────
/**
 * Render email PENGINGAT tagihan bulanan WiFi (dikirim cron tgl 10).
 *
 * Keys array $d:
 *   client_name, order_number, paket_name, paket_price,
 *   id_pelanggan, tanggal_expire, suspend_date, sisa_hari
 */
function render_email_pengingat_tagihan(array $d): string
{
    $rupiah  = function($n) { return 'Rp ' . number_format((float)$n, 0, ',', '.'); };
    $year    = date('Y');
    $tglExp  = date('d M Y', strtotime($d['tanggal_expire']));
    $tglSus  = date('d M Y', strtotime($d['suspend_date']));
    $sisa    = (int)$d['sisa_hari'];
    $sisaTxt = $sisa > 0 ? "$sisa hari lagi" : 'Hari ini!';
    $harga   = $rupiah($d['paket_price']);

    return '<!DOCTYPE html>
<html lang="id">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Pengingat Tagihan WiFi — Perkasa Solusindo</title></head>
<body style="margin:0;padding:0;background:#0b0f1a;font-family:Arial,sans-serif;color:#f1f5f9">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#0b0f1a;padding:40px 20px">
<tr><td align="center">
<table width="100%" style="max-width:580px" cellpadding="0" cellspacing="0">

  <!-- Header -->
  <tr><td style="background:linear-gradient(135deg,#f59e0b,#d97706);border-radius:14px 14px 0 0;padding:28px 32px;text-align:center">
    <div style="font-size:32px;margin-bottom:8px">⚠️</div>
    <div style="font-size:22px;font-weight:900;color:#fff;letter-spacing:.5px">PERKASA <span style="color:#fef3c7">SOLUSINDO</span></div>
    <div style="font-size:12px;color:rgba(255,255,255,.75);margin-top:4px">Pengingat Tagihan Bulanan</div>
  </td></tr>

  <!-- Hero -->
  <tr><td style="background:#111827;padding:32px 32px 24px;text-align:center">
    <h1 style="font-size:22px;font-weight:900;color:#fbbf24;margin:0 0 10px">Tagihan WiFi Segera Jatuh Tempo</h1>
    <p style="font-size:15px;color:#94a3b8;margin:0;line-height:1.7">
      Halo, <strong style="color:#f1f5f9">' . htmlspecialchars($d['client_name']) . '</strong>!<br>
      Tagihan internet Anda jatuh tempo dalam <strong style="color:#fbbf24">' . $sisaTxt . '</strong>.
    </p>
  </td></tr>

  <!-- Info tagihan -->
  <tr><td style="background:#111827;padding:0 32px 24px">
    <table width="100%" cellpadding="0" cellspacing="0">
      <tr><td style="background:rgba(251,191,36,.07);border:1px solid rgba(251,191,36,.25);border-left:4px solid #fbbf24;border-radius:0 10px 10px 0;padding:16px 18px;font-size:14px;line-height:1.9;color:#e2e8f0">
        <table width="100%" cellpadding="0" cellspacing="0">
          <tr>
            <td style="color:#94a3b8;font-size:13px;padding:5px 0;width:45%">ID Pelanggan</td>
            <td style="font-weight:700;font-family:\'Courier New\',monospace;color:#22c55e;font-size:15px;letter-spacing:1px;text-align:right">' . htmlspecialchars($d['id_pelanggan'] ?? '–') . '</td>
          </tr>
          <tr>
            <td style="color:#94a3b8;font-size:13px;padding:5px 0">Paket Layanan</td>
            <td style="font-weight:600;color:#f1f5f9;text-align:right">' . htmlspecialchars($d['paket_name']) . '</td>
          </tr>
          <tr>
            <td style="color:#94a3b8;font-size:13px;padding:5px 0">No. Order</td>
            <td style="font-weight:600;color:#f97316;font-family:\'Courier New\',monospace;text-align:right">' . htmlspecialchars($d['order_number']) . '</td>
          </tr>
          <tr>
            <td style="color:#94a3b8;font-size:13px;padding:5px 0">Total Tagihan</td>
            <td style="font-weight:900;font-size:17px;color:#fbbf24;text-align:right">' . $harga . '<span style="font-size:11px;font-weight:400;color:#64748b"> / bulan</span></td>
          </tr>
          <tr>
            <td style="color:#94a3b8;font-size:13px;padding:5px 0">Jatuh Tempo</td>
            <td style="font-weight:800;color:#f87171;text-align:right">' . $tglExp . '</td>
          </tr>
          <tr>
            <td style="color:#94a3b8;font-size:13px;padding:5px 0">Nonaktif Jika Belum Bayar</td>
            <td style="font-weight:800;color:#ef4444;text-align:right">' . $tglSus . '</td>
          </tr>
        </table>
      </td></tr>
    </table>
  </td></tr>

  <!-- Cara bayar -->
  <tr><td style="background:#111827;padding:0 32px 24px">
    <div style="font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.8px;margin-bottom:12px">Cara Pembayaran</div>
    <table width="100%" cellpadding="0" cellspacing="0">
      <tr><td style="background:rgba(99,102,241,.07);border:1px solid rgba(99,102,241,.2);border-radius:10px;padding:16px 18px;font-size:13px;line-height:1.9;color:#c7d2fe">
        <strong style="display:block;color:#818cf8;margin-bottom:8px">Bank Transfer:</strong>
        BRI · 1234-5678-9012-3456 · a.n. PT Perkasa Tech Solusindo<br>
        <strong style="display:block;color:#818cf8;margin-top:8px;margin-bottom:6px">Setelah transfer:</strong>
        Login ke dashboard &rarr; Upload bukti pembayaran &rarr; Admin akan mengkonfirmasi
      </td></tr>
    </table>
  </td></tr>

  <!-- CTA -->
  <tr><td style="background:#111827;padding:10px 32px 32px;text-align:center">
    <a href="' . DASHBOARD_URL . '"
       style="display:inline-block;background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;font-size:15px;font-weight:700;text-decoration:none;padding:14px 36px;border-radius:8px;letter-spacing:.5px">
      💳 &nbsp;Bayar &amp; Upload Bukti Sekarang
    </a>
  </td></tr>

  <!-- Footer -->
  <tr><td style="background:#0b0f1a;border-radius:0 0 14px 14px;padding:22px 32px;text-align:center;border-top:1px solid rgba(255,255,255,.06)">
    <div style="font-size:13px;color:#64748b;line-height:1.9">
      <strong style="color:#94a3b8">PT. Perkasa Tech Solusindo</strong><br>
      Jln. KedungRejo, Wedoroklurak, Candi, Sidoarjo, Jawa Timur 61271<br>
      📞 <a href="tel:+6281246684665" style="color:#f59e0b;text-decoration:none">+62 812-4668-4665</a>
      &nbsp;·&nbsp;
      ✉️ <a href="mailto:info-perkasa@perkasasolusindo.co.id" style="color:#f59e0b;text-decoration:none">info-perkasa@perkasasolusindo.co.id</a>
    </div>
    <div style="margin-top:12px;font-size:11px;color:#334155">
      Email ini dikirim otomatis oleh sistem. Mohon tidak membalas email ini.<br>
      &copy; ' . $year . ' Perkasa Tech Solusindo. All rights reserved.
    </div>
  </td></tr>

</table></td></tr></table>
</body></html>';
}

// ─────────────────────────────────────────────────────────────────────────────
/**
 * Render email SUSPEND layanan WiFi (dikirim cron tgl 21).
 *
 * Keys array $d:
 *   client_name, order_number, paket_name, paket_price,
 *   id_pelanggan, tagihan_bulan, suspend_date
 */
// =====================================================
//  Template email: Hosting Aktif + Credential
//  Dipanggil dari admin/approve_hosting.php
// =====================================================
function render_email_hosting_aktif(array $d): string {
    $name    = htmlspecialchars($d['client_name']);
    $order   = htmlspecialchars($d['order_number']);
    $paket   = htmlspecialchars($d['paket_name']);
    $domain  = htmlspecialchars($d['domain']);
    $panel   = htmlspecialchars($d['da_panel']);
    $user    = htmlspecialchars($d['da_username']);
    $pass    = htmlspecialchars($d['da_password']);
    $root    = htmlspecialchars($d['da_docroot']);
    $db_name = htmlspecialchars($d['db_name'] ?? '');
    $db_user = htmlspecialchars($d['db_user'] ?? '');
    $db_pass = htmlspecialchars($d['db_password'] ?? '');
    $db_host = htmlspecialchars($d['db_host'] ?? 'localhost');
    $db_ok   = $d['db_ok'] ?? false;
    $expire  = !empty($d['expire_date']) ? date('d M Y', strtotime($d['expire_date'])) : '-';

    $db_section = $db_ok ? "
        <tr><td style='padding:8px;border:1px solid #ddd;color:#666'>Database</td>
            <td style='padding:8px;border:1px solid #ddd'><strong>{$db_name}</strong></td></tr>
        <tr><td style='padding:8px;border:1px solid #ddd;color:#666'>DB Username</td>
            <td style='padding:8px;border:1px solid #ddd'><strong>{$db_user}</strong></td></tr>
        <tr><td style='padding:8px;border:1px solid #ddd;color:#666'>DB Password</td>
            <td style='padding:8px;border:1px solid #ddd'><strong>{$db_pass}</strong></td></tr>
        <tr><td style='padding:8px;border:1px solid #ddd;color:#666'>DB Host</td>
            <td style='padding:8px;border:1px solid #ddd'><strong>{$db_host}</strong></td></tr>"
    : "<tr><td colspan='2' style='padding:8px;border:1px solid #ddd;color:orange'>
        ⚠️ Database belum berhasil dibuat otomatis — hubungi support kami.</td></tr>";

    return "
    <div style='font-family:sans-serif;max-width:600px;margin:0 auto'>
      <h2 style='color:#f97316'>☁️ Hosting Anda Sudah Aktif!</h2>
      <p>Halo <strong>{$name}</strong>,</p>
      <p>Order <strong>#{$order}</strong> paket <strong>{$paket}</strong> sudah aktif. Berikut credential lengkap Anda:</p>

      <div style='background:#fff7ed;border:1px solid #fdba74;border-radius:8px;padding:14px 16px;margin-bottom:20px'>
        <strong style='color:#c2410c'>📅 Aktif hingga: {$expire}</strong><br>
        <span style='color:#666;font-size:13px'>Pastikan melakukan perpanjangan sebelum tanggal tersebut agar layanan tidak terputus.</span>
      </div>

      <h3 style='color:#333'>🔐 Akses Panel DirectAdmin</h3>
      <table style='width:100%;border-collapse:collapse;margin-bottom:20px'>
        <tr><td style='padding:8px;border:1px solid #ddd;color:#666'>Domain</td>
            <td style='padding:8px;border:1px solid #ddd'><strong>{$domain}</strong></td></tr>
        <tr><td style='padding:8px;border:1px solid #ddd;color:#666'>Panel Login</td>
            <td style='padding:8px;border:1px solid #ddd'><a href='{$panel}'>{$panel}</a></td></tr>
        <tr><td style='padding:8px;border:1px solid #ddd;color:#666'>Username</td>
            <td style='padding:8px;border:1px solid #ddd'><strong>{$user}</strong></td></tr>
        <tr><td style='padding:8px;border:1px solid #ddd;color:#666'>Password</td>
            <td style='padding:8px;border:1px solid #ddd'><strong>{$pass}</strong></td></tr>
        <tr><td style='padding:8px;border:1px solid #ddd;color:#666'>Document Root</td>
            <td style='padding:8px;border:1px solid #ddd'><code>{$root}</code></td></tr>
      </table>

      <h3 style='color:#333'>🗄️ Credential Database MySQL</h3>
      <table style='width:100%;border-collapse:collapse;margin-bottom:20px'>
        {$db_section}
      </table>

      <p style='color:red'><strong>⚠️ Segera ganti password setelah login pertama kali!</strong></p>
      <hr>
      <p style='color:#666;font-size:13px'>
        Butuh bantuan? Hubungi kami via WhatsApp atau balas email ini.<br>
        — Tim Perkasa Solusindo
      </p>
    </div>";
}

// ─────────────────────────────────────────────────────────────────────────────
/**
 * Render email SUSPEND layanan WiFi (dikirim cron tgl 21).
 *
 * Keys array $d:
 *   client_name, order_number, paket_name, paket_price,
 *   id_pelanggan, tagihan_bulan, suspend_date
 */
function render_email_suspend_wifi(array $d): string
{
    $rupiah      = function($n) { return 'Rp ' . number_format((float)$n, 0, ',', '.'); };
    $year        = date('Y');
    $tglTagihan  = date('d M Y', strtotime($d['tagihan_bulan']));
    $tglSuspend  = date('d M Y', strtotime($d['suspend_date']));
    $tglNewExp   = !empty($d['new_expire']) ? date('d M Y', strtotime($d['new_expire'])) : '–';

    return '<!DOCTYPE html>
<html lang="id">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Layanan WiFi Dinonaktifkan — Perkasa Solusindo</title></head>
<body style="margin:0;padding:0;background:#0b0f1a;font-family:Arial,sans-serif;color:#f1f5f9">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#0b0f1a;padding:40px 20px">
<tr><td align="center">
<table width="100%" style="max-width:580px" cellpadding="0" cellspacing="0">

  <!-- Header -->
  <tr><td style="background:linear-gradient(135deg,#dc2626,#b91c1c);border-radius:14px 14px 0 0;padding:28px 32px;text-align:center">
    <div style="font-size:32px;margin-bottom:8px">❌</div>
    <div style="font-size:22px;font-weight:900;color:#fff;letter-spacing:.5px">PERKASA <span style="color:#fecaca">SOLUSINDO</span></div>
    <div style="font-size:12px;color:rgba(255,255,255,.75);margin-top:4px">Pemberitahuan Nonaktifasi Layanan</div>
  </td></tr>

  <!-- Hero -->
  <tr><td style="background:#111827;padding:32px 32px 24px;text-align:center">
    <div style="width:72px;height:72px;background:rgba(239,68,68,.12);border:2px solid #ef4444;border-radius:50%;display:inline-block;font-size:32px;line-height:68px;margin-bottom:16px">🔴</div>
    <h1 style="font-size:22px;font-weight:900;color:#f87171;margin:0 0 10px">Layanan WiFi Dinonaktifkan</h1>
    <p style="font-size:15px;color:#94a3b8;margin:0;line-height:1.7">
      Yth. <strong style="color:#f1f5f9">' . htmlspecialchars($d['client_name']) . '</strong>,<br>
      Layanan WiFi Anda telah kami nonaktifkan sementara karena tagihan belum dibayar hingga batas waktu yang ditentukan.
    </p>
  </td></tr>

  <!-- Info detail -->
  <tr><td style="background:#111827;padding:0 32px 24px">
    <table width="100%" cellpadding="0" cellspacing="0">
      <tr><td style="background:rgba(239,68,68,.07);border:1px solid rgba(239,68,68,.25);border-left:4px solid #ef4444;border-radius:0 10px 10px 0;padding:16px 18px;font-size:14px;line-height:1.9;color:#fca5a5">
        <table width="100%" cellpadding="0" cellspacing="0">
          <tr>
            <td style="color:#94a3b8;font-size:13px;padding:5px 0;width:45%">ID Pelanggan</td>
            <td style="font-weight:700;font-family:\'Courier New\',monospace;color:#22c55e;font-size:15px;letter-spacing:1px;text-align:right">' . htmlspecialchars($d['id_pelanggan'] ?? '–') . '</td>
          </tr>
          <tr>
            <td style="color:#94a3b8;font-size:13px;padding:5px 0">Paket Layanan</td>
            <td style="font-weight:600;color:#f1f5f9;text-align:right">' . htmlspecialchars($d['paket_name']) . '</td>
          </tr>
          <tr>
            <td style="color:#94a3b8;font-size:13px;padding:5px 0">No. Order</td>
            <td style="font-weight:600;color:#f97316;font-family:\'Courier New\',monospace;text-align:right">' . htmlspecialchars($d['order_number']) . '</td>
          </tr>
          <tr>
            <td style="color:#94a3b8;font-size:13px;padding:5px 0">Tagihan Bulan</td>
            <td style="font-weight:700;color:#f87171;text-align:right">' . $tglTagihan . '</td>
          </tr>
          <tr>
            <td style="color:#94a3b8;font-size:13px;padding:5px 0">Total Tagihan</td>
            <td style="font-weight:900;font-size:16px;color:#fbbf24;text-align:right">' . $rupiah($d['paket_price']) . '</td>
          </tr>
          <tr>
            <td style="color:#94a3b8;font-size:13px;padding:5px 0">Dinonaktifkan Pada</td>
            <td style="font-weight:800;color:#ef4444;text-align:right">' . $tglSuspend . '</td>
          </tr>
          <tr>
            <td style="color:#94a3b8;font-size:13px;padding:5px 0">Aktif kembali s/d</td>
            <td style="font-weight:800;color:#22c55e;text-align:right">' . $tglNewExp . ' (jika segera bayar)</td>
          </tr>
        </table>
      </td></tr>
    </table>
  </td></tr>

  <!-- Cara reaktivasi -->
  <tr><td style="background:#111827;padding:0 32px 24px">
    <table width="100%" cellpadding="0" cellspacing="0">
      <tr><td style="background:rgba(16,185,129,.07);border:1px solid rgba(16,185,129,.2);border-radius:10px;padding:16px 18px;font-size:13px;line-height:1.9;color:#6ee7b7">
        <strong style="display:block;color:#34d399;margin-bottom:8px;font-size:14px">✅ Cara Mengaktifkan Kembali Layanan:</strong>
        1. Login ke dashboard Anda di <a href="' . SITE_URL . '" style="color:#22c55e">' . SITE_URL . '</a><br>
        2. Upload bukti pembayaran tagihan yang tertunggak<br>
        3. Admin kami akan mengkonfirmasi dan mengaktifkan kembali layanan Anda<br><br>
        Atau hubungi kami langsung di WhatsApp untuk bantuan lebih cepat.
      </td></tr>
    </table>
  </td></tr>

  <!-- CTA -->
  <tr><td style="background:#111827;padding:10px 32px 32px;text-align:center">
    <a href="' . DASHBOARD_URL . '"
       style="display:inline-block;background:linear-gradient(135deg,#22c55e,#16a34a);color:#fff;font-size:15px;font-weight:700;text-decoration:none;padding:14px 36px;border-radius:8px;letter-spacing:.5px;margin-bottom:12px">
      📊 &nbsp;Login &amp; Upload Bukti Bayar
    </a>
    <br>
    <a href="https://wa.me/6281246684665"
       style="display:inline-block;background:rgba(37,211,102,.15);border:1px solid rgba(37,211,102,.3);color:#25d366;font-size:13px;font-weight:700;text-decoration:none;padding:10px 24px;border-radius:8px;letter-spacing:.3px">
      💬 &nbsp;Hubungi Kami via WhatsApp
    </a>
  </td></tr>

  <!-- Footer -->
  <tr><td style="background:#0b0f1a;border-radius:0 0 14px 14px;padding:22px 32px;text-align:center;border-top:1px solid rgba(255,255,255,.06)">
    <div style="font-size:13px;color:#64748b;line-height:1.9">
      <strong style="color:#94a3b8">PT. Perkasa Tech Solusindo</strong><br>
      Jln. KedungRejo, Wedoroklurak, Candi, Sidoarjo, Jawa Timur 61271<br>
      📞 <a href="tel:+6281246684665" style="color:#ef4444;text-decoration:none">+62 812-4668-4665</a>
      &nbsp;·&nbsp;
      ✉️ <a href="mailto:info-perkasa@perkasasolusindo.co.id" style="color:#ef4444;text-decoration:none">info-perkasa@perkasasolusindo.co.id</a>
    </div>
    <div style="margin-top:12px;font-size:11px;color:#334155">
      Email ini dikirim otomatis oleh sistem. Mohon tidak membalas email ini.<br>
      &copy; ' . $year . ' Perkasa Tech Solusindo. All rights reserved.
    </div>
  </td></tr>

</table></td></tr></table>
</body></html>';
}


// ─────────────────────────────────────────────────────────────────────────────
/**
 * Render HTML email pemberitahuan ORDER HOSTING DIBATALKAN OTOMATIS
 * karena melewati batas 24 jam tanpa konfirmasi pembayaran.
 *
 * Keys array $d:
 *   client_name, order_number, paket_name, domain
 */
function render_email_hosting_order_cancelled(array $d): string
{
    $year = date('Y');

    return '<!DOCTYPE html>
<html lang="id">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Order Hosting Dibatalkan — Perkasa Solusindo</title></head>
<body style="margin:0;padding:0;background:#0b0f1a;font-family:Arial,sans-serif;color:#f1f5f9">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#0b0f1a;padding:40px 20px">
<tr><td align="center">
<table width="100%" style="max-width:580px" cellpadding="0" cellspacing="0">

  <!-- Header -->
  <tr><td style="background:linear-gradient(135deg,#dc2626,#b91c1c);border-radius:14px 14px 0 0;padding:28px 32px;text-align:center">
    <div style="font-size:32px;margin-bottom:8px">⛔</div>
    <div style="font-size:22px;font-weight:900;color:#fff;letter-spacing:.5px">PERKASA <span style="color:#fecaca">SOLUSINDO</span></div>
    <div style="font-size:12px;color:rgba(255,255,255,.75);margin-top:4px">Pemberitahuan Pembatalan Order</div>
  </td></tr>

  <!-- Hero -->
  <tr><td style="background:#111827;padding:32px 32px 24px;text-align:center">
    <div style="width:72px;height:72px;background:rgba(239,68,68,.12);border:2px solid #ef4444;border-radius:50%;display:inline-block;font-size:32px;line-height:68px;margin-bottom:16px">🗑️</div>
    <h1 style="font-size:22px;font-weight:900;color:#f87171;margin:0 0 10px">Order Hosting Dibatalkan Otomatis</h1>
    <p style="font-size:15px;color:#94a3b8;margin:0;line-height:1.7">
      Yth. <strong style="color:#f1f5f9">' . htmlspecialchars($d['client_name']) . '</strong>,<br>
      Order hosting Anda telah dibatalkan otomatis oleh sistem karena tidak ada konfirmasi pembayaran
      dalam waktu <strong style="color:#fca5a5">24 jam</strong> sejak order dibuat.
    </p>
  </td></tr>

  <!-- Info detail -->
  <tr><td style="background:#111827;padding:0 32px 24px">
    <table width="100%" cellpadding="0" cellspacing="0">
      <tr><td style="background:rgba(239,68,68,.07);border:1px solid rgba(239,68,68,.25);border-left:4px solid #ef4444;border-radius:0 10px 10px 0;padding:16px 18px;font-size:14px;line-height:1.9;color:#fca5a5">
        <table width="100%" cellpadding="0" cellspacing="0">
          <tr>
            <td style="color:#94a3b8;font-size:13px;padding:5px 0;width:45%">No. Order</td>
            <td style="font-weight:700;font-family:\'Courier New\',monospace;color:#f97316;text-align:right">' . htmlspecialchars($d['order_number']) . '</td>
          </tr>
          <tr>
            <td style="color:#94a3b8;font-size:13px;padding:5px 0">Paket Hosting</td>
            <td style="font-weight:600;color:#f1f5f9;text-align:right">' . htmlspecialchars($d['paket_name']) . '</td>
          </tr>
          <tr>
            <td style="color:#94a3b8;font-size:13px;padding:5px 0">Domain</td>
            <td style="font-weight:600;color:#f1f5f9;text-align:right">' . htmlspecialchars($d['domain']) . '</td>
          </tr>
          <tr>
            <td style="color:#94a3b8;font-size:13px;padding:5px 0">Status</td>
            <td style="font-weight:800;color:#ef4444;text-align:right">Dibatalkan &amp; dihapus dari sistem</td>
          </tr>
        </table>
      </td></tr>
    </table>
  </td></tr>

  <!-- Info lanjutan -->
  <tr><td style="background:#111827;padding:0 32px 24px">
    <table width="100%" cellpadding="0" cellspacing="0">
      <tr><td style="background:rgba(124,58,237,.07);border:1px solid rgba(124,58,237,.2);border-radius:10px;padding:16px 18px;font-size:13px;line-height:1.9;color:#e2e8f0">
        <strong style="display:block;color:#c084fc;margin-bottom:8px;font-size:14px">💡 Masih ingin berlangganan?</strong>
        Anda dapat melakukan order hosting kembali kapan saja melalui website kami.
        Pastikan untuk segera mengupload bukti pembayaran setelah order dibuat agar tidak terulang.
      </td></tr>
    </table>
  </td></tr>

  <!-- CTA -->
  <tr><td style="background:#111827;padding:10px 32px 32px;text-align:center">
    <a href="' . SITE_URL . '/order/order_hosting.php"
       style="display:inline-block;background:linear-gradient(135deg,#7c3aed,#c026d3);color:#fff;font-size:15px;font-weight:700;text-decoration:none;padding:14px 36px;border-radius:8px;letter-spacing:.5px;margin-bottom:12px">
      ☁️ &nbsp;Order Hosting Lagi
    </a>
    <br>
    <a href="https://wa.me/6281246684665"
       style="display:inline-block;background:rgba(37,211,102,.15);border:1px solid rgba(37,211,102,.3);color:#25d366;font-size:13px;font-weight:700;text-decoration:none;padding:10px 24px;border-radius:8px;letter-spacing:.3px">
      💬 &nbsp;Hubungi Kami via WhatsApp
    </a>
  </td></tr>

  <!-- Footer -->
  <tr><td style="background:#0b0f1a;border-radius:0 0 14px 14px;padding:22px 32px;text-align:center;border-top:1px solid rgba(255,255,255,.06)">
    <div style="font-size:13px;color:#64748b;line-height:1.9">
      <strong style="color:#94a3b8">PT. Perkasa Tech Solusindo</strong><br>
      Jln. KedungRejo, Wedoroklurak, Candi, Sidoarjo, Jawa Timur 61271<br>
      📞 <a href="tel:+6281246684665" style="color:#ef4444;text-decoration:none">+62 812-4668-4665</a>
      &nbsp;·&nbsp;
      ✉️ <a href="mailto:info-perkasa@perkasasolusindo.co.id" style="color:#ef4444;text-decoration:none">info-perkasa@perkasasolusindo.co.id</a>
    </div>
    <div style="margin-top:12px;font-size:11px;color:#334155">
      Email ini dikirim otomatis oleh sistem. Mohon tidak membalas email ini.<br>
      &copy; ' . $year . ' Perkasa Tech Solusindo. All rights reserved.
    </div>
  </td></tr>

</table></td></tr></table>
</body></html>';
}
