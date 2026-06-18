<?php
// =====================================================
//  login_verify_resend.php  –  /login/login_verify_resend.php
//  Resends the email verification OTP during login.
//  Regenerates OTP, updates DB, sends email, redirects back.
// =====================================================

require_once __DIR__ . '/../auth_check.php';
redirectIfLoggedIn();
require_once __DIR__ . '/../config.php';

$pendingId = $_SESSION['login_verify_pending_id'] ?? null;

if (!$pendingId) {
    header('Location: /login/login.php');
    exit;
}

// ── Fetch user ───────────────────────────────────────
$st = $conn->prepare("SELECT id, firstname, email FROM tblclients WHERE id = ? LIMIT 1");
$st->bind_param('i', $pendingId);
$st->execute();
$user = $st->get_result()->fetch_assoc();
$st->close();

if (!$user) {
    header('Location: /login/login.php');
    exit;
}

// ── Generate new OTP ─────────────────────────────────
$otp       = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expires   = date('Y-m-d H:i:s', time() + 15 * 60);
$hashedOtp = hash('sha256', $otp);

$upd = $conn->prepare(
    "UPDATE tblclients SET reset_token = ?, reset_token_expires = ? WHERE id = ?"
);
if ($upd) {
    $upd->bind_param('ssi', $hashedOtp, $expires, $user['id']);
    $upd->execute();
    $upd->close();
}
$conn->close();

// ── Send email ───────────────────────────────────────
$phpmailerBase = __DIR__ . '/../phpmailer/src/';
if (file_exists($phpmailerBase . 'PHPMailer.php')) {
    require_once $phpmailerBase . 'Exception.php';
    require_once $phpmailerBase . 'PHPMailer.php';
    require_once $phpmailerBase . 'SMTP.php';

    $mail      = new PHPMailer\PHPMailer\PHPMailer(true);
    $firstname = htmlspecialchars($user['firstname']);

    $otpBlock  = '
      <div style="font-size:2.2rem;font-weight:700;letter-spacing:10px;color:#ff4dce;
                  background:rgba(255,255,255,0.05);padding:18px 24px;border-radius:8px;
                  text-align:center;margin:20px 0;">' . $otp . '</div>';
    $footer    = '<hr style="border-color:rgba(255,255,255,0.1);margin:24px 0"/>
                 <p style="font-size:0.8rem;color:rgba(255,255,255,0.3);margin:0">&copy; Perkasa Solusindo</p>';
    $wrapStyle = 'font-family:sans-serif;max-width:480px;margin:auto;background:#0d0d2b;color:#fff;border-radius:12px;padding:32px;';

    try {
        $mail->isSMTP();
        $mail->Host       = 'mail.perkasasolusindo.co.id';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'verification@perkasasolusindo.co.id';
        $mail->Password   = 'VerifyPerkasa@969699';
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ];
        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);
        $mail->setFrom('verification@perkasasolusindo.co.id', 'Perkasa Solusindo – Verifikasi');
        $mail->addAddress($user['email'], $user['firstname']);
        $mail->Subject = 'Kode Verifikasi Baru – Perkasa Solusindo';
        $mail->Body    = '
          <div style="' . $wrapStyle . '">
            <h2 style="color:#ff4dce;margin-top:0">Kode Verifikasi Baru</h2>
            <p>Halo <strong>' . $firstname . '</strong>,</p>
            <p>Anda meminta kode verifikasi baru. Gunakan kode berikut untuk menyelesaikan login (berlaku 15 menit):</p>'
            . $otpBlock .
            '<p style="font-size:0.85rem;color:rgba(255,255,255,0.45);">Jika Anda tidak meminta ini, abaikan email ini.</p>'
            . $footer . '
          </div>';
        $mail->send();
        $_SESSION['login_verify_info'] = 'Kode baru telah dikirim ke ' . $user['email'] . '. Berlaku 15 menit.';
    } catch (\Exception $e) {
        error_log('[login_verify_resend] Mail failed | user_id=' . $user['id'] . ' | ' . $mail->ErrorInfo);
        $_SESSION['login_verify_info'] = 'Gagal kirim ulang. Coba lagi atau hubungi administrator.';
    }
}

header('Location: /login/login_verify.php');
exit;
