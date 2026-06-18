<?php
// =====================================================
//  config.php  –  /config.php  (public_html root)
//  Database connection. Included by process files.
//  Never include session_start() here — that lives
//  exclusively in auth_check.php.
//
//  ⚠️  SECURITY: Pastikan file ini tidak bisa diakses
//  langsung dari browser. Tambahkan di .htaccess root:
//    <Files "config.php">
//      Order Allow,Deny
//      Deny from all
//    </Files>
// =====================================================

$conn = new mysqli("localhost", "perkasas_maindata", "Px4qPQf6NqTv6EUf3xAa", "perkasas_maindata");

if ($conn->connect_error) {
    error_log('[config] DB connection failed: ' . $conn->connect_error);
    http_response_code(503);
    die("Layanan sementara tidak tersedia. Silakan coba lagi nanti.");
}

$conn->set_charset('utf8mb4');

// =====================================================
//  DirectAdmin API Config
//  Login Key: Advanced Features → Login Keys → perkasaapi
// =====================================================
define('DA_HOST',    'dolce.id.rapidwhm.com');
define('DA_PORT',    '2222');
define('DA_USER',    'perkasasolusindo');
define('DA_API_KEY', 'perkasa969699');
define('DA_DOMAIN',  'perkasasolusindo.co.id');
define('DA_PACKAGE', 'starterpaket');
define('DA_IP',      '103.112.163.50');
