<?php
// =====================================================
//  test_rna_connection.php — Skrip diagnostik RNA/RDASH API
//
//  CARA PAKAI:
//  1. Isi dulu RNA_RESELLER_ID & RNA_API_KEY di config.php
//     (Settings → API & Modules → + ADD di dashboard RDASH,
//      dan pastikan IP server ini di-whitelist saat generate key)
//  2. Upload file ini ke /public_html/ (root, sejajar dengan config.php)
//  3. Akses via browser: https://perkasasolusindo.co.id/test_rna_connection.php
//  4. PENTING: HAPUS file ini setelah selesai testing!
// =====================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/rna_api.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=====================================================\n";
echo " DIAGNOSTIK KONEKSI RNA / RDASH API\n";
echo " Waktu: " . date('Y-m-d H:i:s') . "\n";
echo "=====================================================\n\n";

echo "Konfigurasi yang dipakai:\n";
echo "  RNA_API_BASE    = " . RNA_API_BASE . "\n";
echo "  RNA_RESELLER_ID = " . RNA_RESELLER_ID . "\n";
echo "  RNA_API_KEY     = " . substr(RNA_API_KEY, 0, 4) . str_repeat('*', max(0, strlen(RNA_API_KEY) - 4)) . " (disensor)\n\n";

if (RNA_RESELLER_ID === 'ISI_RESELLER_ID_DISINI' || RNA_API_KEY === 'ISI_API_KEY_DISINI') {
    echo "❌ STOP: Kredensial RNA belum diisi di config.php. Isi dulu sebelum lanjut testing.\n";
    exit;
}

// ── TEST 1: DNS resolve api.rdash.id ──
echo "── TEST 1: DNS Resolve api.rdash.id ──\n";
$ip = gethostbyname('api.rdash.id');
if ($ip === 'api.rdash.id') {
    echo "❌ GAGAL — tidak bisa resolve hostname.\n\n";
} else {
    echo "✅ Berhasil — resolved ke IP: $ip\n\n";
}

// ── TEST 2: Cek IP server ini (untuk keperluan whitelist) ──
echo "── TEST 2: IP Server Ini (untuk whitelist di RDASH) ──\n";
echo "  SERVER_ADDR : " . ($_SERVER['SERVER_ADDR'] ?? 'tidak terdeteksi (jalankan via browser, bukan CLI)') . "\n";
$ipCheck = @file_get_contents('https://api.ipify.org');
echo "  Outbound IP : " . ($ipCheck ?: 'gagal deteksi') . "\n";
echo "  ⚠️ Pastikan salah satu IP di atas (biasanya Outbound IP) sudah di-whitelist saat generate API Key.\n\n";

// ── TEST 3: Panggil endpoint resmi /account/profile ──
echo "── TEST 3: GET /account/profile (endpoint terkonfirmasi dokumentasi) ──\n";
$profile = rna_get_profile();
if ($profile['success']) {
    echo "✅ Berhasil! Kredensial VALID.\n";
    echo "   Response:\n";
    echo "   " . json_encode($profile['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";
} else {
    echo "❌ GAGAL — " . $profile['message'] . " (HTTP " . $profile['http_code'] . ")\n";
    echo "   Kemungkinan: API key salah, IP belum di-whitelist, atau reseller ID salah.\n\n";
}

// ── TEST 4: Coba endpoint cek domain (BELUM TERVERIFIKASI, untuk eksplorasi) ──
echo "── TEST 4: GET /domain/check?domain=test-cek-domain-xyz123.com (⚠️ ASUMSI endpoint) ──\n";
$check = rna_check_domain('test-cek-domain-xyz123.com');
if ($check['success']) {
    echo "✅ Endpoint merespons sukses!\n";
    echo "   Response:\n";
    echo "   " . json_encode($check['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    echo "   → SALIN response ini dan kirim ke developer untuk update rna_api.php agar field-nya akurat.\n\n";
} else {
    echo "❌ Endpoint tidak merespons sukses — " . $check['message'] . " (HTTP " . $check['http_code'] . ")\n";
    echo "   Ini WAJAR jika endpoint /domain/check belum sesuai dengan API asli.\n";
    echo "   → Buka https://api.rdash.id/swagger (pastikan IP sudah di-whitelist), cari endpoint\n";
    echo "     cek ketersediaan domain yang sebenarnya, lalu kirim nama endpoint & contoh response-nya.\n\n";
}

// ── TEST 5: Coba endpoint harga domain (BELUM TERVERIFIKASI, untuk eksplorasi) ──
echo "── TEST 5: GET /domain/price?extension=com (⚠️ ASUMSI endpoint) ──\n";
$price = rna_get_price('.com');
if ($price['success']) {
    echo "✅ Endpoint merespons sukses!\n";
    echo "   Response:\n";
    echo "   " . json_encode($price['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    echo "   → SALIN response ini dan kirim ke developer untuk update rna_api.php agar field-nya akurat.\n\n";
} else {
    echo "❌ Endpoint tidak merespons sukses — " . $price['message'] . " (HTTP " . $price['http_code'] . ")\n";
    echo "   Sama seperti Test 4 — cek endpoint harga yang sebenarnya di Swagger.\n\n";
}

echo "=====================================================\n";
echo " SELESAI.\n";
echo " - Jika Test 3 sukses, kredensial sudah benar.\n";
echo " - Jika Test 4/5 gagal, itu wajar — kirim screenshot Swagger endpoint\n";
echo "   domain check & domain price ke developer untuk finalisasi rna_api.php.\n";
echo " - Jangan lupa HAPUS file ini dari server setelah selesai!\n";
echo "=====================================================\n";
