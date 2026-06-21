<?php
// =====================================================
//  order/check_domain.php
//  Endpoint AJAX: cek ketersediaan domain via RDAP
//  Harga diambil dari tabel tbldomain_pricing (dikelola admin
//  di /admin/domain_pricing.php), bukan hardcode.
//  Dipanggil dari order_hosting.php (JavaScript)
//
//  Method : GET
//  Params : domain=tokosaya&tld=.com
//  Returns: JSON { tld, available, price, error? }
// =====================================================

require_once __DIR__ . '/../config.php';

// ── Keamanan: hanya boleh dari request internal (AJAX/same-origin) ──
header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex');

if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// ── Rate limiting sederhana via session ──
session_start();
$now = time();
if (!isset($_SESSION['domain_check_times'])) {
    $_SESSION['domain_check_times'] = [];
}
// Hapus entry yang lebih dari 60 detik
// NB: pakai closure biasa (bukan arrow function fn()), karena arrow function
// butuh PHP 7.4+ dan pernah menyebabkan fatal error di server ini.
$_SESSION['domain_check_times'] = array_filter(
    $_SESSION['domain_check_times'],
    function($t) use ($now) { return ($now - $t) < 60; }
);
// Batasi: max 20 cek per 60 detik
if (count($_SESSION['domain_check_times']) >= 20) {
    http_response_code(429);
    echo json_encode(['error' => 'Terlalu banyak permintaan. Coba lagi dalam 1 menit.']);
    exit;
}
$_SESSION['domain_check_times'][] = $now;

// ── Ambil & validasi input ──
$domain_raw = strtolower(trim($_GET['domain'] ?? $_POST['domain'] ?? ''));
$tld_raw    = strtolower(trim($_GET['tld']    ?? $_POST['tld']    ?? ''));

// Hanya huruf, angka, dan tanda hubung untuk nama domain
if (!preg_match('/^[a-z0-9][a-z0-9\-]{0,62}[a-z0-9]$|^[a-z0-9]{1,63}$/', $domain_raw)) {
    echo json_encode(['error' => 'Nama domain tidak valid.']);
    exit;
}

// ── Konfigurasi endpoint RDAP per TLD (teknis, bukan harga) ──
// RDAP adalah protokol standar untuk cek ketersediaan domain langsung ke
// registry — gratis, tidak butuh API key. Daftar TLD di sini HARUS sinkron
// dengan TLD yang aktif (aktif=1) di tabel tbldomain_pricing.
$rdap_endpoints = [
    '.id'      => 'https://rdap.pandi.or.id/domain/',
    '.co.id'   => 'https://rdap.pandi.or.id/domain/',
    '.web.id'  => 'https://rdap.pandi.or.id/domain/',
    '.or.id'   => 'https://rdap.pandi.or.id/domain/',
    '.ac.id'   => 'https://rdap.pandi.or.id/domain/',
    '.sch.id'  => 'https://rdap.pandi.or.id/domain/',
    '.biz.id'  => 'https://rdap.pandi.or.id/domain/',
    '.my.id'   => 'https://rdap.pandi.or.id/domain/',
    '.com'     => 'https://rdap.verisign.com/com/v1/domain/',
    '.net'     => 'https://rdap.verisign.com/net/v1/domain/',
    '.org'     => 'https://rdap.publicinterestregistry.org/rdap/domain/',
    '.xyz'     => 'https://rdap.nic.xyz/rdap/domain/',
];

if (!array_key_exists($tld_raw, $rdap_endpoints)) {
    echo json_encode(['error' => 'Ekstensi domain tidak didukung.']);
    exit;
}

// ── Ambil harga jual dari database (sumber kebenaran tunggal) ──
// TLD yang tidak ada di tabel atau non-aktif (aktif=0) dianggap tidak dijual.
$stPrice = $conn->prepare("SELECT harga_jual FROM tbldomain_pricing WHERE tld = ? AND aktif = 1 LIMIT 1");
$stPrice->bind_param('s', $tld_raw);
$stPrice->execute();
$priceRow = $stPrice->get_result()->fetch_assoc();
$stPrice->close();

if (!$priceRow) {
    echo json_encode(['error' => 'Ekstensi domain ini sedang tidak tersedia untuk dijual.']);
    exit;
}

$harga_jual  = (float)$priceRow['harga_jual'];
$rdap_base   = $rdap_endpoints[$tld_raw];
$full_domain = $domain_raw . $tld_raw;  // e.g. "tokosaya.com"
$rdap_url    = $rdap_base . urlencode($full_domain);

// ── Panggil RDAP ──
$ctx = stream_context_create([
    'http' => [
        'method'          => 'GET',
        'timeout'         => 8,        // max 8 detik per TLD
        'ignore_errors'   => true,     // ambil body meski non-200
        'user_agent'      => 'Perkasa-Domain-Checker/1.0',
        'header'          => "Accept: application/rdap+json\r\n",
    ],
    'ssl'  => [
        'verify_peer'     => true,
        'verify_peer_name'=> true,
    ],
]);

$body        = @file_get_contents($rdap_url, false, $ctx);
$http_status = 0;

// Ambil HTTP status dari header respons
if (isset($http_response_header)) {
    foreach ($http_response_header as $h) {
        if (preg_match('#HTTP/\d+\.?\d*\s+(\d+)#', $h, $m)) {
            $http_status = (int)$m[1];
        }
    }
}

// ── Interpretasi hasil RDAP ──
//
//  200 → domain TERDAFTAR (tidak tersedia)
//  404 → domain TIDAK TERDAFTAR (tersedia)
//  Lainnya → tidak pasti (anggap tersedia, tampilkan warning)

$available = false;
$uncertain = false;

if ($http_status === 404) {
    // Domain tidak ditemukan di registry → tersedia untuk didaftarkan
    $available = true;
} elseif ($http_status === 200) {
    // Domain sudah ada di registry → tidak tersedia
    $available = false;
} else {
    // Timeout, error jaringan, atau respons tak terduga
    // Tampilkan sebagai "tidak dapat dicek" agar tidak menyesatkan
    $uncertain = true;
    error_log("[check_domain] RDAP status=$http_status url=$rdap_url");
}

// ── Respons ──
$response = [
    'tld'       => $tld_raw,
    'domain'    => $full_domain,
    'available' => $available,
    'price'     => $harga_jual,
    'sale'      => false, // promo/diskon dikelola lewat admin/domain_pricing.php jika diperlukan nanti
];

if ($uncertain) {
    $response['available'] = null; // null = tidak pasti
    $response['notice']    = 'Tidak dapat memeriksa ketersediaan saat ini. Coba lagi nanti.';
}

echo json_encode($response);
