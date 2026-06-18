<?php
// =====================================================
//  verify_email_process.php  –  /login/verify_email_process.php
//  Validates the email-verification OTP.
//  On success:
//    1. Marks email_verified = 1
//    2. Generates a NEW OTP for password reset
//    3. Sends it via reset-pass@...
//    4. Redirects to reset_password.php
// =====================================================

require_once __DIR__ . '/../auth_check.php';
redirectIfLoggedIn();
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /login/verifikasi_email.php');
    exit;
}

$pendingId = $_SESSION['forgot_pending_id'] ?? null;
$mode      = $_SESSION['forgot_mode']       ?? null;

if (!$pendingId || $mode !== 'verify_email') {
    header('Location: /login/lupa_password.php');
    exit;
}

$code = trim($_POST['verify_code'] ?? '');

if (!$code || strlen($code) !== 6 || !ctype_digit($code)) {
    $_SESSION['verify_errors'] = ['Kode verifikasi harus 6 digit angka.'];
    header('Location: /login/verifikasi_email.php');
    exit;
}

// ── Fetch stored token ───────────────────────────────
$st = $conn->prepare(
    "SELECT id, firstname, email, reset_token, reset_token_expires
     FROM tblclients WHERE id = ? LIMIT 1"
);
if (!$st) {
    error_log('[verify_email_process] prepare SELECT failed: ' . $conn->error);
    $_SESSION['verify_errors'] = ['Database error. Coba lagi nanti.'];
    header('Location: /login/verifikasi_email.php');
    exit;
}
$st->bind_param('i', $pendingId);
$st->execute();
$user = $st->get_result()->fetch_assoc();
$st->close();

if (!$user) {
    $_SESSION['verify_errors'] = ['Sesi tidak valid. Mulai ulang proses.'];
    header('Location: /login/lupa_password.php');
    exit;
}

// ── Validate OTP ─────────────────────────────────────
if (hash('sha256', $code) !== $user['reset_token']) {
    $_SESSION['verify_errors'] = ['Kode verifikasi salah. Periksa kembali email Anda.'];
    header('Location: /login/verifikasi_email.php');
    exit;
}

if (strtotime($user['reset_token_expires']) < time()) {
    $_SESSION['verify_errors'] = ['Kode sudah kedaluwarsa. Silakan minta kode baru.'];
    header('Location: /login/lupa_password.php');
    exit;
}

// ── Mark email as verified ───────────────────────────
$upd = $conn->prepare(
    "UPDATE tblclients SET email_verified = 1, reset_token = NULL, reset_token_expires = NULL WHERE id = ?"
);
if (!$upd) {
    error_log('[verify_email_process] prepare UPDATE verify failed: ' . $conn->error);
    $_SESSION['verify_errors'] = ['Database error. Coba lagi nanti.'];
    header('Location: /login/verifikasi_email.php');
    exit;
}
$upd->bind_param('i', $pendingId);
$upd->execute();
$upd->close();

// ── Generate a new OTP for password reset ────────────
$resetOtp     = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$resetExpires = date('Y-m-d H:i:s', time() + 15 * 60);
$resetHashed  = hash('sha256', $resetOtp);

$upd2 = $conn->prepare(
    "UPDATE tblclients SET reset_token = ?, reset_token_expires = ? WHERE id = ?"
);
if (!$upd2) {
    error_log('[verify_email_process] prepare UPDATE reset_token failed: ' . $conn->error);
    $_SESSION['verify_errors'] = ['Database error. Coba lagi nanti.'];
    header('Location: /login/verifikasi_email.php');
    exit;
}
$upd2->bind_param('ssi', $resetHashed, $resetExpires, $pendingId);
$upd2->execute();
$upd2->close();
$conn->close();

// ── Update session mode to reset_password ────────────
$_SESSION['forgot_mode'] = 'reset_password';

// ── Load PHPMailer ────────────────────────────────────
$phpmailerBase = __DIR__ . '/../phpmailer/src/';

if (!file_exists($phpmailerBase . 'PHPMailer.php')) {
    error_log('[verify_email_process] PHPMailer not found at: ' . $phpmailerBase);
    $_SESSION['forgot_error'] = 'Server error: PHPMailer tidak ditemukan.';
    header('Location: /login/lupa_password.php');
    exit;
}

require_once $phpmailerBase . 'Exception.php';
require_once $phpmailerBase . 'PHPMailer.php';
require_once $phpmailerBase . 'SMTP.php';

$mail      = new PHPMailer\PHPMailer\PHPMailer(true);
$firstname = htmlspecialchars($user['firstname']);

$otpBlock = '
  <div style="font-size:2.2rem;font-weight:700;letter-spacing:10px;color:#ff4dce;
              background:rgba(255,255,255,0.05);padding:18px 24px;border-radius:8px;
              text-align:center;margin:20px 0;">' . $resetOtp . '</div>';
$footer   = '<hr style="border-color:rgba(255,255,255,0.1);margin:24px 0"/>
             <p style="font-size:0.8rem;color:rgba(255,255,255,0.3);margin:0">&copy; Perkasa Solusindo</p>';
$wrapStyle = 'font-family:sans-serif;max-width:480px;margin:auto;background:#0d0d2b;color:#fff;border-radius:12px;padding:32px;';

try {
    $mail->isSMTP();
    $mail->Host       = 'mail.perkasasolusindo.co.id';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'reset-pass@perkasasolusindo.co.id';
    $mail->Password   = 'ResetPerkasa@969699';
    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    // FIX: Shared cPanel server cert CN is 'dolce.id.rapidwhm.com', not our domain.
    // Disable peer verification to prevent SMTP connection failure.
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ],
    ];
    $mail->CharSet    = 'UTF-8';
    $mail->isHTML(true);

    $mail->setFrom('reset-pass@perkasasolusindo.co.id', 'Perkasa Solusindo – Reset Sandi');
    $mail->addAddress($user['email'], $user['firstname']);
    $mail->Subject = 'Reset Kata Sandi – Perkasa Solusindo';
    $mail->Body    = '
      <div style="' . $wrapStyle . '">
        <h2 style="color:#ff4dce;margin-top:0">Reset Kata Sandi</h2>
        <p>Halo <strong>' . $firstname . '</strong>,</p>
        <p>Email Anda telah berhasil diverifikasi! Gunakan kode berikut untuk mengatur ulang kata sandi Anda (berlaku 15 menit):</p>'
        . $otpBlock .
        '<p style="font-size:0.85rem;color:rgba(255,255,255,0.45);">Jika Anda tidak meminta ini, abaikan email ini.</p>'
        . $footer . '
      </div>';

    $mail->send();

    $_SESSION['forgot_success'] = 'Email terverifikasi! Kode reset kata sandi telah dikirim ke ' . $user['email'] . '. Berlaku 15 menit.';
    header('Location: /login/reset_password.php');
    exit;

} catch (\Exception $e) {
    error_log('[verify_email_process] Reset mail failed | user_id=' . $pendingId
        . ' | PHPMailer: ' . $mail->ErrorInfo
        . ' | Exception: ' . $e->getMessage());

    // Email verified successfully, still let them proceed — OTP is in DB
    $_SESSION['forgot_success'] = 'Email berhasil diverifikasi! Silakan masukkan kode reset yang dikirim ke ' . $user['email'] . '. Jika tidak masuk, periksa folder Spam.';
    header('Location: /login/reset_password.php');
    exit;
}
