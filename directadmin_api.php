<?php
// =====================================================
//  directadmin_api.php  –  /public_html/directadmin_api.php
//  Helper: semua fungsi komunikasi DirectAdmin API
//  Kompatibel PHP 7.0+
//  Require config.php sebelum include file ini.
// =====================================================

/**
 * Request ke DA API sebagai RESELLER
 */
function da_request($endpoint, $data)
{
    $url = 'https://' . DA_HOST . ':' . DA_PORT . '/' . ltrim($endpoint, '/');

    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_USERPWD        => DA_USER . ':' . DA_API_KEY,
        CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => array('Accept: text/plain'),
    ));

    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    if ($curl_err) {
        error_log('[DA_API] cURL error: ' . $curl_err . ' | url=' . $url);
        return array('success' => false, 'message' => 'Koneksi ke DirectAdmin gagal: ' . $curl_err, 'raw' => array());
    }

    parse_str($response ? $response : '', $result);
    $success = ($http_code === 200 && isset($result['error']) && (string)$result['error'] === '0');
    $message = isset($result['details']) ? $result['details'] : (isset($result['text']) ? $result['text'] : ($response ? $response : 'Tidak ada respons.'));

    if (!$success) {
        error_log('[DA_API] Gagal | code=' . $http_code . ' | msg=' . $message . ' | url=' . $url);
    }

    return array('success' => $success, 'message' => $message, 'raw' => $result);
}

/**
 * Request ke DA API sebagai USER (impersonate)
 * Digunakan untuk buat database di dalam akun client
 * Format auth DA: reseller|username:apikey
 */
function da_request_as_user($da_username, $endpoint, $data)
{
    $url     = 'https://' . DA_HOST . ':' . DA_PORT . '/' . ltrim($endpoint, '/');
    $userpwd = DA_USER . '|' . $da_username . ':' . DA_API_KEY;

    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_USERPWD        => $userpwd,
        CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER     => array('Accept: text/plain'),
    ));

    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    if ($curl_err) {
        error_log('[DA_AS_USER] cURL error: ' . $curl_err);
        return array('success' => false, 'message' => 'Koneksi gagal: ' . $curl_err, 'raw' => array());
    }

    parse_str($response ? $response : '', $result);
    $success = ($http_code === 200 && isset($result['error']) && (string)$result['error'] === '0');
    $message = isset($result['details']) ? $result['details'] : (isset($result['text']) ? $result['text'] : ($response ? $response : 'Tidak ada respons.'));

    if (!$success) {
        error_log('[DA_AS_USER] Gagal | user=' . $da_username . ' | code=' . $http_code . ' | msg=' . $message);
    }

    return array('success' => $success, 'message' => $message, 'raw' => $result);
}

// ══════════════════════════════════════════════════════
//  MANAJEMEN AKUN USER
// ══════════════════════════════════════════════════════

/**
 * Tentukan DA package berdasarkan harga produk.
 * Rp 100.000 → starterpaket
 * Rp 200.000 → bisnispaket
 * Selain itu  → DA_PACKAGE (default dari config)
 */
function da_package_from_price($price)
{
    $price = (int)$price;
    if ($price <= 100000) {
        return 'starterpaket';
    } elseif ($price <= 200000) {
        return 'bisnispaket';
    }
    return DA_PACKAGE;
}

/**
 * Buat akun hosting baru di DirectAdmin.
 * DA otomatis membuat:
 *   /home/{username}/
 *   /home/{username}/domains/{domain}/public_html/   <- document root
 *   DNS zone untuk domain/subdomain
 *
 * @param string $username
 * @param string $email
 * @param string $password
 * @param string $domain     misal: toko.perkasasolusindo.co.id
 * @param string $package    starterpaket / bisnispaket
 * @return array
 */
function da_create_user($username, $email, $password, $domain, $package = '')
{
    if (empty($package)) $package = DA_PACKAGE;

    return da_request('CMD_API_ACCOUNT_USER', array(
        'action'  => 'create',
        'add'     => 'Submit',
        'username'=> $username,
        'email'   => $email,
        'passwd'  => $password,
        'passwd2' => $password,
        'domain'  => $domain,
        'package' => $package,
        'ip'      => DA_IP,
        'notify'  => 'no',
    ));
}

/**
 * Suspend akun hosting.
 */
function da_suspend_user($username)
{
    return da_request('CMD_API_SELECT_USERS', array(
        'action'  => 'suspend',
        'select0' => $username,
    ));
}

/**
 * Unsuspend akun hosting.
 */
function da_unsuspend_user($username)
{
    return da_request('CMD_API_SELECT_USERS', array(
        'action'  => 'unsuspend',
        'select0' => $username,
    ));
}

/**
 * Hapus akun hosting.
 */
function da_delete_user($username)
{
    return da_request('CMD_API_SELECT_USERS', array(
        'action'    => 'delete',
        'select0'   => $username,
        'confirmed' => 'Confirm',
    ));
}

// ══════════════════════════════════════════════════════
//  MANAJEMEN DATABASE
//  Dijalankan sebagai user (impersonate)
//  Format nama DA: {da_username}_{nama}
// ══════════════════════════════════════════════════════

/**
 * Buat database + DB user + assign privileges sekaligus.
 * Hasil:
 *   database : {da_username}_db
 *   db user  : {da_username}_usr
 *
 * @param string $da_username
 * @param string $db_password
 * @return array
 */
function da_create_database($da_username, $db_password)
{
    $db_name_short = 'db';
    $db_user_short = 'usr';
    $db_name_full  = $da_username . '_' . $db_name_short;
    $db_user_full  = $da_username . '_' . $db_user_short;

    // Langkah 1: Buat database
    $r1 = da_request_as_user($da_username, 'CMD_API_DATABASES', array(
        'action' => 'create',
        'name'   => $db_name_short,
    ));

    if (!$r1['success']) {
        return array(
            'success'      => false,
            'message'      => 'Gagal buat database: ' . $r1['message'],
            'db_name_full' => '',
            'db_user_full' => '',
        );
    }

    // Langkah 2: Buat DB user
    $r2 = da_request_as_user($da_username, 'CMD_API_DATABASES', array(
        'action'  => 'createuser',
        'name'    => $db_user_short,
        'passwd'  => $db_password,
        'passwd2' => $db_password,
    ));

    if (!$r2['success']) {
        return array(
            'success'      => false,
            'message'      => 'Database dibuat tapi gagal buat DB user: ' . $r2['message'],
            'db_name_full' => $db_name_full,
            'db_user_full' => '',
        );
    }

    // Langkah 3: Assign user ke database (grant privileges)
    $r3 = da_request_as_user($da_username, 'CMD_API_DATABASES', array(
        'action'   => 'assignuser',
        'name'     => $db_name_full,
        'userlist' => $db_user_full,
        'access'   => 'yes',
    ));

    if (!$r3['success']) {
        // Tidak fatal, DB & user sudah ada
        error_log('[DA_DB] Assign user gagal (minor): ' . $r3['message']);
    }

    return array(
        'success'      => true,
        'message'      => 'Database & user berhasil dibuat.',
        'db_name_full' => $db_name_full,
        'db_user_full' => $db_user_full,
    );
}

// ══════════════════════════════════════════════════════
//  HELPER
// ══════════════════════════════════════════════════════

/**
 * Generate username DA unik dari nama client.
 * DA: maks 8 karakter, hanya huruf kecil & angka.
 *
 * @param string $firstname
 * @param object $conn  mysqli connection
 * @return string
 */
function da_generate_username($firstname, $conn)
{
    $base     = strtolower(preg_replace('/[^a-z0-9]/', '', $firstname));
    $base     = substr($base, 0, 6);
    if (empty($base)) $base = 'user';
    $username = $base . rand(10, 99);

    for ($i = 0; $i < 10; $i++) {
        $chk = $conn->prepare("SELECT id FROM tblhosting WHERE da_username = ? LIMIT 1");
        $chk->bind_param('s', $username);
        $chk->execute();
        $chk->store_result();
        $exists = ($chk->num_rows > 0);
        $chk->close();
        if (!$exists) break;
        $username = $base . rand(10, 99);
    }

    return $username;
}

/**
 * Generate password acak kuat (kompatibel PHP 7.0+).
 * Hindari karakter konflik DA: & = + % # spasi
 *
 * @param int $length
 * @return string
 */
function da_generate_password($length = 14)
{
    $chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $pass  = '';
    $max   = strlen($chars) - 1;
    for ($i = 0; $i < $length; $i++) {
        $pass .= $chars[random_int(0, $max)];
    }
    return $pass;
}
