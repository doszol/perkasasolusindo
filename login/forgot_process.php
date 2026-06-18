<?php
// =====================================================
//  forgot_process.php  –  /login/forgot_process.php
//  Flow:
//    email not verified → OTP via verification@ → verifikasi_email.php
//    email verified     → OTP via reset-pass@   → reset_password.php
// =====================================================

require_once __DIR__ . '/../auth_check.php';
redirectIfLoggedIn();

// BUG FIX 4: Always load config.php explicitly.
// The old `if (!isset($conn))` guard was fragile — if auth_check.php happened
// to include config.php, $conn existed and the guard skipped re-loading (fine),
// but if it didn't, $conn was missing. Always requiring it is safe because
// config.php should use `isset($conn)` internally or you simply require it once.
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /login/lupa_password.php');
    exit;
}

$email = trim($_POST['email'] ?? '');

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['forgot_error'] = 'Masukkan alamat email yang valid.';
    header('Location: /login/lupa_password.php');
    exit;
}

// ── Find user ────────────────────────────────────────
$st = $conn->prepare(
    "SELECT id, firstname, email, email_verified FROM tblclients WHERE email = ? LIMIT 1"
);
if (!$st) {
    error_log('[forgot_process] prepare SELECT failed: ' . $conn->error);
    $_SESSION['forgot_error'] = 'Database error. Coba lagi nanti.';
    header('Location: /login/lupa_password.php');
    exit;
}
$st->bind_param('s', $email);
$st->execute();
$user = $st->get_result()->fetch_assoc();
$st->close();

if (!$user) {
    // FIX: Generic message to prevent email enumeration by attackers.
    $_SESSION['forgot_success'] = 'Jika email terdaftar, kode telah dikirim. Periksa kotak masuk Anda.';
    header('Location: /login/lupa_password.php');
    exit;
}

// ── Generate OTP ─────────────────────────────────────
$otp       = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expires   = date('Y-m-d H:i:s', time() + 15 * 60);
$hashedOtp = hash('sha256', $otp);

$upd = $conn->prepare(
    "UPDATE tblclients SET reset_token = ?, reset_token_expires = ? WHERE id = ?"
);
if (!$upd) {
    error_log('[forgot_process] prepare UPDATE failed: ' . $conn->error);
    $_SESSION['forgot_error'] = 'Database error. Coba lagi nanti.';
    header('Location: /login/lupa_password.php');
    exit;
}
$upd->bind_param('ssi', $hashedOtp, $expires, $user['id']);
$upd->execute();
$upd->close();
$conn->close();

// ── Determine routing ────────────────────────────────
$emailVerified = (int)$user['email_verified'] === 1;

// Store session BEFORE mail attempt
$_SESSION['forgot_pending_id']    = $user['id'];
$_SESSION['forgot_pending_email'] = $user['email'];
$_SESSION['forgot_mode']          = $emailVerified ? 'reset_password' : 'verify_email';

// ── Load PHPMailer ────────────────────────────────────
$phpmailerBase = __DIR__ . '/../phpmailer/src/';

if (!file_exists($phpmailerBase . 'PHPMailer.php')) {
    error_log('[forgot_process] PHPMailer not found at: ' . $phpmailerBase);
    $_SESSION['forgot_error'] = 'Server error: PHPMailer tidak ditemukan. Hubungi administrator.';
    header('Location: /login/lupa_password.php');
    exit;
}

require_once $phpmailerBase . 'Exception.php';
require_once $phpmailerBase . 'PHPMailer.php';
require_once $phpmailerBase . 'SMTP.php';

$mail      = new PHPMailer\PHPMailer\PHPMailer(true);
$firstname = htmlspecialchars($user['firstname']);

// ── Email templates ───────────────────────────────────
$otpBlock = '
  <div style="font-size:2.2rem;font-weight:700;letter-spacing:10px;color:#ff4dce;
              background:rgba(255,255,255,0.05);padding:18px 24px;border-radius:8px;
              text-align:center;margin:20px 0;">' . $otp . '</div>';
$footer   = '<hr style="border-color:rgba(255,255,255,0.1);margin:24px 0"/>
             <p style="font-size:0.8rem;color:rgba(255,255,255,0.3);margin:0">&copy; Perkasa Solusindo</p>';
$wrapStyle = 'font-family:sans-serif;max-width:480px;margin:auto;background:#0d0d2b;color:#fff;border-radius:12px;padding:32px;';

try {
    $mail->isSMTP();
    $mail->Host       = 'mail.perkasasolusindo.co.id';
    $mail->SMTPAuth   = true;
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
    $mail->addAddress($user['email'], $user['firstname']);

    if (!$emailVerified) {
        // ── Not verified: send via verification@ ─────────
        $mail->Username = 'verification@perkasasolusindo.co.id';
        $mail->Password = 'VerifyPerkasa@969699';
        $mail->setFrom('verification@perkasasolusindo.co.id', 'Perkasa Solusindo – Verifikasi');
        $mail->Subject  = 'Verifikasi Email Anda – Perkasa Solusindo';
        $mail->Body     = '
          <div style="' . $wrapStyle . '">
            <h2 style="color:#ff4dce;margin-top:0">Verifikasi Email Anda</h2>
            <p>Halo <strong>' . $firstname . '</strong>,</p>
            <p>Akun Anda belum terverifikasi. Masukkan kode di bawah ini untuk memverifikasi email Anda (berlaku 15 menit):</p>'
            . $otpBlock .
            '<p style="font-size:0.85rem;color:rgba(255,255,255,0.45);">Jika Anda tidak meminta ini, abaikan email ini.</p>'
            . $footer . '
          </div>';

        $mail->send();
        $_SESSION['forgot_success'] = 'Kode verifikasi telah dikirim ke ' . $user['email'] . '. Berlaku 15 menit.';
        header('Location: /login/verifikasi_email.php');

    } else {
        // ── Verified: send via reset-pass@ ───────────────
        $mail->Username = 'reset-pass@perkasasolusindo.co.id';
        $mail->Password = 'ResetPerkasa@969699';
        $mail->setFrom('reset-pass@perkasasolusindo.co.id', 'Perkasa Solusindo – Reset Sandi');
        $mail->Subject  = 'Reset Kata Sandi – Perkasa Solusindo';
        $mail->Body     = '
          <div style="' . $wrapStyle . '">
            <h2 style="color:#ff4dce;margin-top:0">Reset Kata Sandi</h2>
            <p>Halo <strong>' . $firstname . '</strong>,</p>
            <p>Kami menerima permintaan untuk mereset kata sandi akun Anda. Gunakan kode berikut (berlaku 15 menit):</p>'
            . $otpBlock .
            '<p style="font-size:0.85rem;color:rgba(255,255,255,0.45);">Jika Anda tidak meminta ini, abaikan email ini.</p>'
            . $footer . '
          </div>';

        $mail->send();
        $_SESSION['forgot_success'] = 'Kode reset kata sandi telah dikirim ke ' . $user['email'] . '. Berlaku 15 menit.';
        header('Location: /login/reset_password.php');
    }
    exit;

} catch (\Exception $e) {
    error_log('[forgot_process] Mail failed | user_id=' . $user['id']
        . ' | email=' . $user['email']
        . ' | verified=' . ($emailVerified ? 'yes' : 'no')
        . ' | PHPMailer: ' . $mail->ErrorInfo
        . ' | Exception: ' . $e->getMessage());

    $_SESSION['forgot_error'] = 'Gagal mengirim email: ' . $mail->ErrorInfo
        . '. Coba lagi atau hubungi administrator.';
    header('Location: /login/lupa_password.php');
    exit;
}
