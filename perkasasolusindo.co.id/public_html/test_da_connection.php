<?php
// =====================================================
//  test_da_connection.php — Skrip diagnostik DirectAdmin API
//
//  CARA PAKAI:
//  1. Upload file ini ke /public_html/ (root, sejajar dengan config.php)
//  2. Akses via browser: https://perkasasolusindo.co.id/test_da_connection.php
//     ATAU jalankan via SSH: php test_da_connection.php
//  3. PENTING: HAPUS file ini setelah selesai testing!
//     (file ini menampilkan info konfigurasi yang sensitif)
// =====================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/directadmin_api.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=====================================================\n";
echo " DIAGNOSTIK KONEKSI DIRECTADMIN API\n";
echo " Waktu: " . date('Y-m-d H:i:s') . "\n";
echo "=====================================================\n\n";

echo "Konfigurasi yang dipakai:\n";
echo "  DA_HOST    = " . DA_HOST . "\n";
echo "  DA_PORT    = " . DA_PORT . "\n";
echo "  DA_USER    = " . DA_USER . "\n";
echo "  DA_API_KEY = " . substr(DA_API_KEY, 0, 3) . str_repeat('*', max(0, strlen(DA_API_KEY) - 3)) . " (disensor)\n";
echo "  DA_IP      = " . DA_IP . "\n";
echo "  DA_PACKAGE = " . DA_PACKAGE . "\n\n";

// ── TEST 1: DNS resolve ──
echo "── TEST 1: DNS Resolve " . DA_HOST . " ──\n";
$ip = gethostbyname(DA_HOST);
if ($ip === DA_HOST) {
    echo "❌ GAGAL — tidak bisa resolve hostname.\n\n";
} else {
    echo "✅ Berhasil — resolved ke IP: $ip\n\n";
}

// ── TEST 2: Koneksi TCP ke port DA ──
echo "── TEST 2: Koneksi TCP ke " . DA_HOST . ":" . DA_PORT . " ──\n";
$start = microtime(true);
$fp = @fsockopen(DA_HOST, (int)DA_PORT, $errno, $errstr, 8);
$elapsed = round((microtime(true) - $start) * 1000);
if ($fp) {
    echo "✅ Berhasil connect dalam {$elapsed}ms.\n\n";
    fclose($fp);
} else {
    echo "❌ GAGAL — errno=$errno, errstr=$errstr (setelah {$elapsed}ms)\n";
    echo "   Kemungkinan penyebab: firewall server, port DA tidak listen, atau DA_HOST/PORT salah.\n\n";
}

// ── TEST 3: cURL HTTPS request dasar (tanpa auth) ──
echo "── TEST 3: cURL HTTPS request dasar ──\n";
$url = 'https://' . DA_HOST . ':' . DA_PORT . '/CMD_API_SHOW_USERS';
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_USERPWD        => DA_USER . ':' . DA_API_KEY,
    CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
]);
$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err  = curl_error($ch);
curl_close($ch);

if ($curl_err) {
    echo "❌ GAGAL — cURL error: $curl_err\n\n";
} else {
    echo "✅ Request terkirim. HTTP code: $http_code\n";
    echo "   Response (1000 char pertama):\n";
    echo "   " . substr($response, 0, 1000) . "\n\n";
}

// ── TEST 4: Validasi kredensial via CMD_API_SHOW_USER_CONFIG (read-only, aman) ──
echo "── TEST 4: Validasi kredensial (read-only) ──\n";
$result = da_request('CMD_API_SHOW_USER_CONFIG', ['user' => DA_USER]);
if ($result['success']) {
    echo "✅ Kredensial VALID — API key diterima oleh server DirectAdmin.\n";
} else {
    echo "❌ GAGAL — " . $result['message'] . "\n";
    echo "   Kemungkinan: API key salah/expired, DA_USER salah, atau reseller tidak punya izin akses endpoint ini.\n";
}

echo "\n=====================================================\n";
echo " SELESAI. Jangan lupa HAPUS file ini dari server!\n";
echo "=====================================================\n";
