<?php
// =====================================================
//  login_process.php  –  /login/login_process.php
// =====================================================

require_once __DIR__ . '/../auth_check.php';
redirectIfLoggedIn();
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /login/login.php');
    exit;
}

$email    = trim($_POST['email']    ?? '');
$password =      $_POST['password'] ?? '';
$remember = isset($_POST['remember']);

// ── Brute-force protection (max 10 attempts per IP per 15 min) ──
$ip         = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$lockoutKey  = 'login_lockout_'  . md5($ip);
$attemptsKey = 'login_attempts_' . md5($ip);

if (!empty($_SESSION[$lockoutKey]) && $_SESSION[$lockoutKey] > time()) {
    $wait = ceil(($_SESSION[$lockoutKey] - time()) / 60);
    $_SESSION['login_error'] = "Terlalu banyak percobaan login. Coba lagi dalam {$wait} menit.";
    header('Location: /login/login.php');
    exit;
}

// ── Basic blank check ────────────────────────────────
if (!$email || !$password) {
    $_SESSION['login_error'] = 'Email dan kata sandi wajib diisi.';
    header('Location: /login/login.php');
    exit;
}

// ── Fetch user ───────────────────────────────────────
$st = $conn->prepare(
    "SELECT id, firstname, lastname, email, password, level, status, email_verified
     FROM tblclients WHERE email = ? LIMIT 1"
);
$st->bind_param('s', $email);
$st->execute();
$user = $st->get_result()->fetch_assoc();
$st->close();

// ── Verify password ──────────────────────────────────
if (!$user || !password_verify($password, $user['password'])) {
    $_SESSION[$attemptsKey] = ($_SESSION[$attemptsKey] ?? 0) + 1;
    if ($_SESSION[$attemptsKey] >= 10) {
        $_SESSION[$lockoutKey]   = time() + 15 * 60;
        $_SESSION['login_error'] = 'Terlalu banyak percobaan. Akun dikunci 15 menit.';
    } else {
        $_SESSION['login_error'] = 'Email atau kata sandi salah.';
    }
    header('Location: /login/login.php');
    exit;
}

// ── Check active ─────────────────────────────────────
if ((int)$user['status'] === 0) {
    $_SESSION['login_error'] = 'Akun Anda tidak aktif. Hubungi administrator.';
    header('Location: /login/login.php');
    exit;
}

// ── Reset brute-force counter on success ─────────────
unset($_SESSION[$attemptsKey], $_SESSION[$lockoutKey]);

// ── Email verification gate (level 3 = Client, level 4 = Teknisi) ──
// Level 1 (Owner) and 2 (Admin) bypass verification.
if (in_array((int)$user['level'], [3, 4], true) && (int)$user['email_verified'] === 0) {

    // Generate OTP & store in DB
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

    // Store pending login session (NOT yet fully logged in)
    $_SESSION['login_verify_pending_id']    = $user['id'];
    $_SESSION['login_verify_pending_email'] = $user['email'];
    $_SESSION['login_verify_firstname']     = $user['firstname'];
    $_SESSION['login_verify_remember']      = $remember;
    $_SESSION['login_verify_level']         = (int)$user['level'];

    // Send verification email via PHPMailer
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
            $mail->Subject = 'Verifikasi Email Anda – Perkasa Solusindo';
            $mail->Body    = '
              <div style="' . $wrapStyle . '">
                <h2 style="color:#ff4dce;margin-top:0">Verifikasi Email Anda</h2>
                <p>Halo <strong>' . $firstname . '</strong>,</p>
                <p>Sebelum Anda bisa login, kami perlu memverifikasi email Anda. Masukkan kode berikut (berlaku 15 menit):</p>'
                . $otpBlock .
                '<p style="font-size:0.85rem;color:rgba(255,255,255,0.45);">Jika Anda tidak mencoba login, abaikan email ini.</p>'
                . $footer . '
              </div>';
            $mail->send();
            $_SESSION['login_verify_info'] = 'Kode verifikasi telah dikirim ke ' . $user['email'] . '. Berlaku 15 menit.';
        } catch (\Exception $e) {
            error_log('[login_process] Verification mail failed | user_id=' . $user['id'] . ' | ' . $mail->ErrorInfo);
            $_SESSION['login_verify_info'] = 'Kode verifikasi dikirim ke ' . $user['email'] . '. Jika tidak masuk, cek folder Spam.';
        }
    }

    header('Location: /login/login_verify.php');
    exit;
}

$conn->close();

// ── Store session ────────────────────────────────────
$_SESSION['user_id']        = $user['id'];
$_SESSION['user_firstname'] = $user['firstname'];
$_SESSION['user_lastname']  = $user['lastname'];
$_SESSION['user_email']     = $user['email'];
$_SESSION['user_level']     = (int)$user['level'];

// ── Remember Me cookie ───────────────────────────────
if ($remember) {
    $hmac  = hash_hmac('sha256', $user['id'] . '|' . $user['email'], 'perkasa_2025_secret');
    $token = base64_encode($user['id'] . '|' . $user['level'] . '|' . $hmac);
    setcookie('perkasa_remember', $token, time() + 30 * 24 * 3600, '/', '', true, true);
} else {
    setcookie('perkasa_remember', '', time() - 3600, '/');
}

// ── Route by level ───────────────────────────────────
$level = (int)$user['level'];
if ($level === 3) {
    header('Location: /client/client_dashboard.php');
} elseif ($level === 4) {
    header('Location: /teknisi/teknisi_dashboard.php');
} else {
    header('Location: /admin/admin_dashboard.php');
}
exit;
