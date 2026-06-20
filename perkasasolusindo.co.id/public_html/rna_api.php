<?php
// =====================================================
//  rna_api.php  –  /public_html/rna_api.php
//  Helper: semua fungsi komunikasi RNA/RDASH Domain Reseller API
//  Kompatibel PHP 7.0+
//  Require config.php sebelum include file ini.
//
//  Dokumentasi resmi : https://docs.rdash.id/developer/api
//  Dashboard reseller : https://perkasasolusindo.rdash.id
//  Live tester        : https://api.rdash.id/swagger (perlu IP whitelist)
//
//  ⚠️ STATUS VERIFIKASI ENDPOINT:
//  Endpoint di bawah ini DIKONFIRMASI dari dokumentasi resmi RDASH:
//    - Base URL & auth (Basic Auth reseller_id:api_key)       ✅ terkonfirmasi
//    - GET  /account/profile                                  ✅ terkonfirmasi (contoh resmi)
//
//  Endpoint berikut BELUM diverifikasi langsung ke Swagger (asumsi pola REST
//  umum berdasarkan struktur RDASH dashboard: Domains > All Domains / Prices /
//  Register). Nama field request/response BISA BERBEDA dari implementasi asli.
//  WAJIB cross-check ke https://api.rdash.id/swagger setelah API Key digenerate
//  dan IP server di-whitelist, sebelum dipakai di production:
//    - GET  /domain/check         (cek ketersediaan domain)
//    - GET  /domain/price         (cek harga modal per TLD)
//    - POST /domain/register      (registrasi domain baru)
//    - PUT  /domain/nameserver    (set nameserver domain)
//
//  Cara verifikasi: buka test_rna_connection.php (lihat di repo) setelah
//  config RNA_RESELLER_ID & RNA_API_KEY diisi, lalu sesuaikan nama endpoint/
//  field di bawah berdasarkan response Swagger yang sebenarnya.
// =====================================================

/**
 * Request dasar ke RNA/RDASH API.
 * Auth: Basic Auth (reseller_id:api_key), response format JSON.
 *
 * @param string $method   GET / POST / PUT / DELETE
 * @param string $endpoint contoh: '/account/profile', '/domain/check'
 * @param array  $params   untuk GET: query string. Untuk POST/PUT: JSON body.
 * @return array ['success' => bool, 'data' => array|null, 'message' => string, 'http_code' => int]
 */
function rna_request($method, $endpoint, $params = array())
{
    $url = rtrim(RNA_API_BASE, '/') . '/' . ltrim($endpoint, '/');
    $method = strtoupper($method);

    if ($method === 'GET' && !empty($params)) {
        $url .= '?' . http_build_query($params);
    }

    $ch = curl_init();
    $opts = array(
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_USERPWD        => RNA_RESELLER_ID . ':' . RNA_API_KEY,
        CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => array('Accept: application/json', 'Content-Type: application/json'),
    );

    if (in_array($method, array('POST', 'PUT', 'PATCH'), true) && !empty($params)) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($params);
    }

    curl_setopt_array($ch, $opts);

    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    if ($curl_err) {
        error_log('[RNA_API] cURL error: ' . $curl_err . ' | url=' . $url);
        return array(
            'success'   => false,
            'data'      => null,
            'message'   => 'Koneksi ke RNA API gagal: ' . $curl_err,
            'http_code' => 0,
        );
    }

    $decoded = json_decode($response, true);
    $success = ($http_code >= 200 && $http_code < 300);

    if (!$success) {
        $msg = is_array($decoded) && isset($decoded['message']) ? $decoded['message'] : ('HTTP ' . $http_code);
        error_log('[RNA_API] Gagal | code=' . $http_code . ' | msg=' . $msg . ' | url=' . $url);
        return array(
            'success'   => false,
            'data'      => $decoded,
            'message'   => $msg,
            'http_code' => $http_code,
        );
    }

    return array(
        'success'   => true,
        'data'      => $decoded,
        'message'   => 'OK',
        'http_code' => $http_code,
    );
}

// ══════════════════════════════════════════════════════
//  AKUN / KONEKSI
// ══════════════════════════════════════════════════════

/**
 * Cek kredensial valid + ambil info profil reseller (termasuk saldo).
 * Endpoint ini DIKONFIRMASI dari dokumentasi resmi.
 */
function rna_get_profile()
{
    return rna_request('GET', '/account/profile');
}

// ══════════════════════════════════════════════════════
//  DOMAIN — ⚠️ endpoint di bawah perlu verifikasi Swagger
// ══════════════════════════════════════════════════════

/**
 * Cek ketersediaan domain.
 * ⚠️ Endpoint & nama parameter ASUMSI, perlu verifikasi ke Swagger.
 *
 * @param string $domain nama domain lengkap, contoh: tokosaya.com
 * @return array
 */
function rna_check_domain($domain)
{
    return rna_request('GET', '/domain/check', array('domain' => $domain));
}

/**
 * Ambil harga modal (registrasi/perpanjangan/transfer) untuk satu TLD.
 * ⚠️ Endpoint & nama parameter ASUMSI, perlu verifikasi ke Swagger.
 * Dipakai oleh admin/domain_pricing.php untuk fitur "Refresh Harga Modal".
 *
 * @param string $tld contoh: .com, .co.id (dengan atau tanpa titik, disesuaikan saat verifikasi)
 * @return array
 */
function rna_get_price($tld)
{
    return rna_request('GET', '/domain/price', array('extension' => ltrim($tld, '.')));
}

/**
 * Registrasi domain baru.
 * ⚠️ Endpoint & nama parameter ASUMSI, perlu verifikasi ke Swagger.
 * BELUM DIPANGGIL otomatis di alur approve_hosting.php — akan diaktifkan
 * setelah endpoint terverifikasi dan ditest manual minimal sekali.
 *
 * @param array $data harus berisi: domain, customer_id/contact, period (tahun),
 *                     nameserver1, nameserver2, dst (sesuai field asli RDASH)
 * @return array
 */
function rna_register_domain($data)
{
    return rna_request('POST', '/domain/register', $data);
}

/**
 * Set nameserver domain yang sudah teregistrasi.
 * ⚠️ Endpoint & nama parameter ASUMSI, perlu verifikasi ke Swagger.
 *
 * @param string $domain
 * @param array  $nameservers contoh: ['ns1.example.com', 'ns2.example.com']
 * @return array
 */
function rna_set_nameserver($domain, $nameservers)
{
    return rna_request('PUT', '/domain/nameserver', array(
        'domain'      => $domain,
        'nameservers' => $nameservers,
    ));
}
