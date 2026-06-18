<?php
// =====================================================
//  reset_process.php  –  /login/reset_process.php
//  Validates reset OTP + saves new password.
//  On success → clears session → login.php with popup.
// =====================================================

require_once __DIR__ . '/../auth_check.php';
redirectIfLoggedIn();
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /login/reset_password.php');
    exit;
}

$pendingId = $_SESSION['forgot_pending_id'] ?? null;
$mode      = $_SESSION['forgot_mode']       ?? null;

if (!$pendingId || $mode !== 'reset_password') {
    header('Location: /login/lupa_password.php');
    exit;
}

$code     = trim($_POST['reset_code']  ?? '');
$newPass  = $_POST['new_password']     ?? '';
$newPass2 = $_POST['new_password2']    ?? '';

// ── Validate inputs ───────────────────────────────────
$errors = [];
if (!$code || strlen($code) !== 6 || !ctype_digit($code))
    $errors[] = 'Kode verifikasi harus 6 digit angka.';
if (!$newPass)
    $errors[] = 'Kata sandi baru wajib diisi.';
if (strlen($newPass) < 8)
    $errors[] = 'Kata sandi minimal 8 karakter.';
if ($newPass !== $newPass2)
    $errors[] = 'Konfirmasi kata sandi tidak cocok.';

if ($errors) {
    $_SESSION['reset_errors'] = $errors;
    header('Location: /login/reset_password.php');
    exit;
}

// ── Fetch stored token ───────────────────────────────
$st = $conn->prepare(
    "SELECT id, reset_token, reset_token_expires FROM tblclients WHERE id = ? LIMIT 1"
);
if (!$st) {
    error_log('[reset_process] prepare SELECT failed: ' . $conn->error);
    $_SESSION['reset_errors'] = ['Database error. Coba lagi nanti.'];
    header('Location: /login/reset_password.php');
    exit;
}
$st->bind_param('i', $pendingId);
$st->execute();
$user = $st->get_result()->fetch_assoc();
$st->close();

if (!$user) {
    $_SESSION['reset_errors'] = ['Sesi tidak valid. Mulai ulang proses.'];
    header('Location: /login/lupa_password.php');
    exit;
}

// ── Validate OTP ─────────────────────────────────────
if (hash('sha256', $code) !== $user['reset_token']) {
    $_SESSION['reset_errors'] = ['Kode reset salah. Periksa kembali email Anda.'];
    header('Location: /login/reset_password.php');
    exit;
}

if (strtotime($user['reset_token_expires']) < time()) {
    $_SESSION['reset_errors'] = ['Kode sudah kedaluwarsa. Silakan minta kode baru.'];
    header('Location: /login/lupa_password.php');
    exit;
}

// ── Save new password ────────────────────────────────
$hashed = password_hash($newPass, PASSWORD_BCRYPT);

$upd = $conn->prepare(
    "UPDATE tblclients
     SET password = ?, reset_token = NULL, reset_token_expires = NULL
     WHERE id = ?"
);
if (!$upd) {
    error_log('[reset_process] prepare UPDATE failed: ' . $conn->error);
    $_SESSION['reset_errors'] = ['Database error. Coba lagi nanti.'];
    header('Location: /login/reset_password.php');
    exit;
}
$upd->bind_param('si', $hashed, $pendingId);
$upd->execute();
$upd->close();
$conn->close();

// ── Clear all forgot-flow session keys ───────────────
unset(
    $_SESSION['forgot_pending_id'],
    $_SESSION['forgot_pending_email'],
    $_SESSION['forgot_mode'],
    $_SESSION['forgot_success'],
    $_SESSION['forgot_error'],
    $_SESSION['reset_errors'],
    $_SESSION['verify_errors']
);

// ── Signal login.php to show success popup ───────────
$_SESSION['reset_success'] = true;

header('Location: /login/login.php');
exit;
