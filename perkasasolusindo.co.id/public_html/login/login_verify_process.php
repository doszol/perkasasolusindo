<?php
// =====================================================
//  login_verify_process.php  –  /login/login_verify_process.php
//  Validates the email OTP during login flow.
//  On success:
//    1. Marks email_verified = 1
//    2. Stores full login session
//    3. Sets Remember Me cookie if requested
//    4. Redirects to client dashboard
// =====================================================

require_once __DIR__ . '/../auth_check.php';
redirectIfLoggedIn();
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /login/login_verify.php');
    exit;
}

$pendingId = $_SESSION['login_verify_pending_id'] ?? null;

if (!$pendingId) {
    header('Location: /login/login.php');
    exit;
}

$code = trim($_POST['verify_code'] ?? '');

if (!$code || strlen($code) !== 6 || !ctype_digit($code)) {
    $_SESSION['login_verify_errors'] = ['Kode verifikasi harus 6 digit angka.'];
    header('Location: /login/login_verify.php');
    exit;
}

// ── Fetch stored token & user data ───────────────────
$st = $conn->prepare(
    "SELECT id, firstname, lastname, email, level, status, reset_token, reset_token_expires
     FROM tblclients WHERE id = ? LIMIT 1"
);
if (!$st) {
    error_log('[login_verify_process] prepare SELECT failed: ' . $conn->error);
    $_SESSION['login_verify_errors'] = ['Database error. Coba lagi nanti.'];
    header('Location: /login/login_verify.php');
    exit;
}
$st->bind_param('i', $pendingId);
$st->execute();
$user = $st->get_result()->fetch_assoc();
$st->close();

if (!$user) {
    $_SESSION['login_verify_errors'] = ['Sesi tidak valid. Silakan login ulang.'];
    unset(
        $_SESSION['login_verify_pending_id'],
        $_SESSION['login_verify_pending_email'],
        $_SESSION['login_verify_firstname'],
        $_SESSION['login_verify_remember']
    );
    header('Location: /login/login.php');
    exit;
}

// ── Check if account is still active ─────────────────
if ((int)$user['status'] === 0) {
    $_SESSION['login_error'] = 'Akun Anda tidak aktif. Hubungi administrator.';
    header('Location: /login/login.php');
    exit;
}

// ── Validate OTP ─────────────────────────────────────
if (empty($user['reset_token']) || hash('sha256', $code) !== $user['reset_token']) {
    $_SESSION['login_verify_errors'] = ['Kode verifikasi salah. Periksa kembali email Anda.'];
    header('Location: /login/login_verify.php');
    exit;
}

if (strtotime($user['reset_token_expires']) < time()) {
    $_SESSION['login_verify_errors'] = ['Kode sudah kedaluwarsa. Silakan login ulang untuk mendapatkan kode baru.'];
    unset(
        $_SESSION['login_verify_pending_id'],
        $_SESSION['login_verify_pending_email'],
        $_SESSION['login_verify_firstname'],
        $_SESSION['login_verify_remember']
    );
    header('Location: /login/login.php');
    exit;
}

// ── Mark email as verified & clear OTP ───────────────
$upd = $conn->prepare(
    "UPDATE tblclients
     SET email_verified = 1, reset_token = NULL, reset_token_expires = NULL
     WHERE id = ?"
);
if (!$upd) {
    error_log('[login_verify_process] prepare UPDATE failed: ' . $conn->error);
    $_SESSION['login_verify_errors'] = ['Database error. Coba lagi nanti.'];
    header('Location: /login/login_verify.php');
    exit;
}
$upd->bind_param('i', $pendingId);
$upd->execute();
$upd->close();
$conn->close();

// ── Store full login session ──────────────────────────
$remember = $_SESSION['login_verify_remember'] ?? false;
$level    = $_SESSION['login_verify_level']    ?? (int)$user['level'];

unset(
    $_SESSION['login_verify_pending_id'],
    $_SESSION['login_verify_pending_email'],
    $_SESSION['login_verify_firstname'],
    $_SESSION['login_verify_remember'],
    $_SESSION['login_verify_level'],
    $_SESSION['login_verify_info'],
    $_SESSION['login_verify_errors']
);

$_SESSION['user_id']        = $user['id'];
$_SESSION['user_firstname'] = $user['firstname'];
$_SESSION['user_lastname']  = $user['lastname'];
$_SESSION['user_email']     = $user['email'];
$_SESSION['user_level']     = $level;

// ── Remember Me cookie ───────────────────────────────
if ($remember) {
    $hmac  = hash_hmac('sha256', $user['id'] . '|' . $user['email'], 'perkasa_2025_secret');
    $token = base64_encode($user['id'] . '|' . $level . '|' . $hmac);
    setcookie('perkasa_remember', $token, time() + 30 * 24 * 3600, '/', '', true, true);
}

// ── Redirect to correct dashboard by level ───────────
if ($level === 3) {
    header('Location: /client/client_dashboard.php');
} elseif ($level === 4) {
    header('Location: /teknisi/teknisi_dashboard.php');
} else {
    header('Location: /admin/admin_dashboard.php');
}
exit;
