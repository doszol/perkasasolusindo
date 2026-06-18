<?php
// =====================================================
//  logout.php – /logout.php (public_html root)
//  Bisa dipanggil dari mana saja: admin/ maupun client/
// =====================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Hapus semua data session
$_SESSION = [];

// Hancurkan session cookie
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $p['path'], $p['domain'], $p['secure'], $p['httponly']
    );
}

session_destroy();

// Hapus remember-me cookie jika ada
if (!empty($_COOKIE['perkasa_remember'])) {
    setcookie('perkasa_remember', '', time() - 3600, '/', '', true, true);
}

// Redirect ke halaman login
header('Location: /login/login.php');
exit;
