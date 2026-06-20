<?php
// =====================================================
//  auth_check.php  –  Place in ROOT (public_html/)
//  Include at the TOP of every protected page.
// =====================================================
//
//  Usage in CLIENT pages  (/client/client_dashboard.php):
//    require_once __DIR__ . '/../auth_check.php';
//    requireLevel(3);
//
//  Usage in ADMIN pages  (/admin/admin_dashboard.php):
//    require_once __DIR__ . '/../auth_check.php';
//    requireLevel([1, 2]);
//
//  Usage in login/ pages (login.php, registrasi.php, etc.):
//    require_once __DIR__ . '/../auth_check.php';
//    redirectIfLoggedIn();
// =====================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Restore session from Remember Me cookie ──────────────────
if (empty($_SESSION['user_id']) && !empty($_COOKIE['perkasa_remember'])) {
    $raw   = base64_decode($_COOKIE['perkasa_remember']);
    $parts = explode('|', $raw, 3);            // uid|level|hmac

    if (count($parts) === 3) {
        [$uid, $lvl, $cookieHmac] = $parts;
        $uid = (int)$uid;

        // auth_check.php is in root (public_html/), config.php is next to it
        require_once __DIR__ . '/config.php';
        $st = $conn->prepare("SELECT id,firstname,lastname,email,level,status FROM tblclients WHERE id=? LIMIT 1");
        $st->bind_param('i', $uid);
        $st->execute();
        $user = $st->get_result()->fetch_assoc();
        $st->close();

        if ($user && (int)$user['status'] === 1) {
        // ⚠️  SECURITY: Ganti secret key ini dengan string acak yang panjang.
        // Generate dengan: php -r "echo bin2hex(random_bytes(32));"
        // Simpan di luar public_html jika memungkinkan (misal: /home/perkasas/.env)
        $expected = hash_hmac('sha256', $uid . '|' . $user['email'], 'perkasa_2025_secret');
            if (hash_equals($expected, $cookieHmac)) {
                $_SESSION['user_id']        = $user['id'];
                $_SESSION['user_firstname'] = $user['firstname'];
                $_SESSION['user_lastname']  = $user['lastname'];
                $_SESSION['user_email']     = $user['email'];
                $_SESSION['user_level']     = (int)$user['level'];

                // Rolling refresh: extend the cookie 30 days from now on every visit.
                // FIX: secure=true enforces HTTPS-only transmission of the auth cookie.
                setcookie(
                    'perkasa_remember',
                    $_COOKIE['perkasa_remember'],
                    time() + 30 * 24 * 3600,
                    '/', '', true, true
                );
            } else {
                // Tampered cookie — clear it immediately
                setcookie('perkasa_remember', '', time() - 3600, '/');
            }
        } else {
            // User deleted or inactive — clear the stale cookie
            setcookie('perkasa_remember', '', time() - 3600, '/');
        }
    }
}

// ── Helpers ──────────────────────────────────────────────────

function dashboardUrl($level) {
    switch ((int)$level) {
        case 1: // Owner
        case 2: // Admin
            return '/admin/admin_dashboard.php';
        case 3: // Client
            return '/client/client_dashboard.php';
        case 4: // Teknisi
            return '/teknisi/teknisi_dashboard.php';
        default:
            return '/login/login.php';
    }
}

/** Redirect to login if not authenticated */
function requireLogin() {
    if (empty($_SESSION['user_id'])) {
        header('Location: /login/login.php');
        exit;
    }
}

/** Require specific level(s). Wrong level → sent to own dashboard */
function requireLevel($levels) {
    requireLogin();
    $levels = (array)$levels;
    if (!in_array((int)$_SESSION['user_level'], $levels, true)) {
        header('Location: ' . dashboardUrl($_SESSION['user_level']));
        exit;
    }
}

/** If already logged in, send to correct dashboard (for login/register pages) */
function redirectIfLoggedIn() {
    if (!empty($_SESSION['user_id'])) {
        header('Location: ' . dashboardUrl($_SESSION['user_level']));
        exit;
    }
}
