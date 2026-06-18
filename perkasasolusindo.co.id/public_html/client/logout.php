<?php
// =====================================================
//  logout.php  –  /logout.php
// =====================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Hapus cookie Remember Me jika ada
if (!empty($_COOKIE['perkasa_remember'])) {
    setcookie('perkasa_remember', '', time() - 3600, '/', '', true, true);
}

// Hancurkan session
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '',
        time() - 3600,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}
session_destroy();

// Redirect ke halaman login
header('Location: /login/login.php');
exit;
