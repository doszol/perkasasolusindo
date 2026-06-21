<?php
// =====================================================
//  test_rdap_connection.php — Diagnostik konektivitas RDAP
//
//  CARA PAKAI:
//  1. Upload ke /public_html/ (root, sejajar config.php)
//  2. Akses via browser: https://perkasasolusindo.co.id/test_rdap_connection.php
//  3. PENTING: HAPUS file ini setelah selesai testing!
// =====================================================

header('Content-Type: text/plain; charset=utf-8');

echo "=====================================================\n";
echo " DIAGNOSTIK KONEKTIVITAS RDAP\n";
echo " Waktu: " . date('Y-m-d H:i:s') . "\n";
echo "=====================================================\n\n";

$targets = [
    '.id / .co.id / dst' => 'rdap.pandi.or.id',
    '.com / .net'        => 'rdap.verisign.com',
    '.org'               => 'rdap.publicinterestregistry.org',
    '.org (alt)'         => 'rdap.publicinterestregistry.net',
    '.xyz'                => 'rdap.nic.xyz',
];

foreach ($targets as $label => $host) {
    echo "── $label ($host) ──\n";

    // Test 1: DNS resolve
    $ip = gethostbyname($host);
    if ($ip === $host) {
        echo "  ❌ DNS GAGAL resolve.\n\n";
        continue;
    }
    echo "  ✅ DNS resolve OK -> $ip\n";

    // Test 2: Koneksi TCP port 443
    $start = microtime(true);
    $fp = @fsockopen('ssl://' . $host, 443, $errno, $errstr, 6);
    $elapsed = round((microtime(true) - $start) * 1000);
    if ($fp) {
        echo "  ✅ Koneksi TCP:443 OK (${elapsed}ms)\n";
        fclose($fp);
    } else {
        echo "  ❌ Koneksi TCP:443 GAGAL — errno=$errno, errstr=$errstr (${elapsed}ms)\n";
        echo "     -> Kemungkinan firewall outbound server memblokir host ini.\n\n";
        continue;
    }

    // Test 3: Request HTTPS sungguhan via cURL (paling akurat, dipakai juga oleh kode asli)
    $testDomain = 'google.com'; // domain yang pasti terdaftar, untuk uji status 200
    $url = 'https://' . $host . (
        strpos($host, 'pandi') !== false ? '/domain/test-cek-xyz-999.id' :
        (strpos($host, 'verisign') !== false ? '/com/v1/domain/google.com' :
        (strpos($host, 'nic.xyz') !== false ? '/rdap/domain/test-cek-xyz-999.xyz' :
        '/rdap/domain/google.org'))
    );

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => ['Accept: application/rdap+json'],
        CURLOPT_USERAGENT      => 'Perkasa-Domain-Checker-Diagnostic/1.0',
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err) {
        echo "  ❌ cURL request GAGAL: $err\n\n";
    } else {
        echo "  ✅ cURL request berhasil terkirim. HTTP code: $code\n";
        echo "     URL diuji: $url\n";
        echo "     Response (300 char pertama): " . substr($resp, 0, 300) . "\n\n";
    }
}

echo "=====================================================\n";
echo " SELESAI.\n";
echo " - Host dengan DNS/TCP GAGAL = firewall server memblokir, hubungi\n";
echo "   provider hosting untuk minta akses outbound ke host tersebut.\n";
echo " - Host dengan HTTP code 200/404 = JALAN NORMAL.\n";
echo " - Jangan lupa HAPUS file ini dari server setelah selesai!\n";
echo "=====================================================\n";
