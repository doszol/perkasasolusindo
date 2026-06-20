<?php
// =====================================================
//  order/check_domain.php
//  Endpoint AJAX: cek ketersediaan domain via RDAP
//  Dipanggil dari order_hosting.php (JavaScript)
//
//  Method : GET
//  Params : domain=tokosaya&tld=.com
//  Returns: JSON { tld, available, price, error? }
// =====================================================

// ── Keamanan: hanya boleh dari request internal (AJAX/same-origin) ──
header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex');

// Batasi hanya POST atau GET dengan referer yang sama (CSRF ringan)
// Untuk keamanan lebih: tambahkan nonce/token di sisi klien jika perlu
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
$_SESSION['domain_check_times'] = array_filter(
    $_SESSION['domain_check_times'],
    fn($t) => ($now - $t) < 60
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

// TLD yang diizinkan (sesuai dengan order_hosting.php)
$allowed_tlds = [
    '.id'     => ['rdap' => 'https://rdap.pandi.or.id/domain/',          'price' => 245000, 'sale' => false],
    '.com'    => ['rdap' => 'https://rdap.verisign.com/com/v1/domain/',  'price' => 210000, 'sale' => false],
    '.net'    => ['rdap' => 'https://rdap.verisign.com/net/v1/domain/',  'price' => 240000, 'sale' => false],
    '.xyz'    => ['rdap' => 'https://rdap.nic.xyz/rdap/domain/',         'price' => 50000,  'sale' => true ],
    '.co.id'  => ['rdap' => 'https://rdap.pandi.or.id/domain/',          'price' => 350000, 'sale' => false],
    '.my.id'  => ['rdap' => 'https://rdap.pandi.or.id/domain/',          'price' => 25000,  'sale' => true ],
    '.web.id' => ['rdap' => 'https://rdap.pandi.or.id/domain/',          'price' => 35000,  'sale' => false],
];

if (!array_key_exists($tld_raw, $allowed_tlds)) {
    echo json_encode(['error' => 'Ekstensi domain tidak didukung.']);
    exit;
}

$tld_config  = $allowed_tlds[$tld_raw];
$full_domain = $domain_raw . $tld_raw;  // e.g. "tokosaya.com"
$rdap_url    = $tld_config['rdap'] . urlencode($full_domain);

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
    'price'     => $tld_config['price'],
    'sale'      => $tld_config['sale'],
];

if ($uncertain) {
    $response['available'] = null; // null = tidak pasti
    $response['notice']    = 'Tidak dapat memeriksa ketersediaan saat ini. Coba lagi nanti.';
}

echo json_encode($response);
