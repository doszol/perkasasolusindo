<?php
/**
 * Perkasa Solusindo — Process Order WiFi (Backend)
 * Path: /public_html/order/process_order.php
 *
 * Mode A — logged_in : user sudah punya akun, hanya INSERT tblorders
 *                       + UPDATE NIK/foto_ktp di tblclients jika berubah
 * Mode B — guest      : buat akun baru (INSERT tblclients) + INSERT tblorders
 *
 * Alur umum:
 *  1. Validasi CSRF + method POST
 *  2. Deteksi mode (logged_in / guest)
 *  3. Validasi input sesuai mode
 *  4. Validasi & simpan file KTP
 *  5. Validasi paket
 *  6. Transaksi DB:
 *       Mode A → UPDATE tblclients, INSERT tblorders, log, notif
 *       Mode B → INSERT tblclients, INSERT tblorders, log, notif
 *  7. Redirect ke order_sukses.php
 */

// ── Bootstrap ─────────────────────────────────────────────────────
require_once dirname(__DIR__) . '/auth_check.php';   // session_start() di sini
require_once dirname(__DIR__) . '/config.php';        // $conn = mysqli object
require_once dirname(__DIR__) . '/mailer.php';        // PHPMailer wrapper + template email

// Hanya terima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: order_wifi.php');
    exit;
}

// ═══════════════════════════════════════════════════════════════════
// HELPER FUNCTIONS
// ═══════════════════════════════════════════════════════════════════

function redirect_err(string $msg, array $old, int $paket_id): void {
    $_SESSION['order_error'] = $msg;
    $_SESSION['order_old']   = $old;
    header("Location: order_wifi.php?paket_id={$paket_id}");
    exit;
}

/** Kirim notifikasi ke semua admin & owner (level 1 & 2) */
function notif_admins(mysqli $conn, int $order_id, string $judul, string $pesan): void {
    $stmt = $conn->prepare(
        "SELECT id FROM tblclients WHERE level IN (1,2) AND status = 1"
    );
    $stmt->execute();
    $admins = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($admins)) return;

    $ins = $conn->prepare(
        "INSERT INTO tblnotifikasi (userid, order_id, judul, pesan, tipe) VALUES (?,?,?,?,'info')"
    );
    foreach ($admins as $a) {
        $ins->bind_param('iiss', $a['id'], $order_id, $judul, $pesan);
        $ins->execute();
    }
    $ins->close();
}

// ═══════════════════════════════════════════════════════════════════
// 1. VALIDASI CSRF
// ═══════════════════════════════════════════════════════════════════

$paket_id   = (int)($_POST['paket_id'] ?? 0);
$mode       = $_POST['mode'] ?? 'guest';   // 'logged_in' atau 'guest'
$csrf_token = $_POST['csrf_token'] ?? '';

if (
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $csrf_token)
) {
    redirect_err('Request tidak valid (CSRF). Silakan muat ulang halaman dan coba lagi.', [], $paket_id);
}

// Token sekali pakai — regenerate setelah validasi
unset($_SESSION['csrf_token']);

// ═══════════════════════════════════════════════════════════════════
// 2. DETEKSI MODE & AMBIL INPUT
// ═══════════════════════════════════════════════════════════════════

$is_logged_in = !empty($_SESSION['user_id']) && $mode === 'logged_in';

// Field yang dibutuhkan di kedua mode
$raw = [
    // Data KTP / diri
    'nik'           => trim($_POST['nik']           ?? ''),
    'jenis_kelamin' => trim($_POST['jenis_kelamin']  ?? ''),
    'tanggal_lahir' => trim($_POST['tanggal_lahir']  ?? ''),
    'tempat_lahir'  => trim($_POST['tempat_lahir']   ?? ''),
    // Alamat pemasangan
    'alamat_pasang' => trim($_POST['alamat_pasang']  ?? ''),
    'rt'            => trim($_POST['rt']             ?? ''),
    'rw'            => trim($_POST['rw']             ?? ''),
    'kelurahan'     => trim($_POST['kelurahan']      ?? ''),
    'kecamatan'     => trim($_POST['kecamatan']      ?? ''),
    'kota'          => trim($_POST['kota']           ?? ''),
    'provinsi'      => trim($_POST['provinsi']       ?? ''),
    'kodepos'       => trim($_POST['kodepos']        ?? ''),
    'patokan'       => trim($_POST['patokan']        ?? ''),
    // Persetujuan
    'accepttos'     => isset($_POST['accepttos'])     ? 1 : 0,
    'marketingoptin'=> isset($_POST['marketingoptin'])? 1 : 0,
];

// Field tambahan khusus Mode B (guest)
if (!$is_logged_in) {
    $raw['firstname']        = trim($_POST['firstname']        ?? '');
    $raw['lastname']         = trim($_POST['lastname']         ?? '');
    $raw['email']            = strtolower(trim($_POST['email'] ?? ''));
    $raw['phonenumber']      = trim($_POST['phonenumber']      ?? '');
    $raw['companyname']      = trim($_POST['companyname']      ?? '');
    $raw['password']         = $_POST['password']              ?? '';
    $raw['password_confirm'] = $_POST['password_confirm']      ?? '';
}

// ═══════════════════════════════════════════════════════════════════
// 3. VALIDASI INPUT
// ═══════════════════════════════════════════════════════════════════

// Field wajib yang sama di kedua mode
$required_shared = [
    'nik'           => 'NIK',
    'jenis_kelamin' => 'Jenis Kelamin',
    'tanggal_lahir' => 'Tanggal Lahir',
    'tempat_lahir'  => 'Tempat Lahir',
    'alamat_pasang' => 'Alamat Pemasangan',
    'rt'            => 'RT',
    'rw'            => 'RW',
    'kelurahan'     => 'Kelurahan',
    'kecamatan'     => 'Kecamatan',
    'kota'          => 'Kota',
    'provinsi'      => 'Provinsi',
    'kodepos'       => 'Kode Pos',
];

foreach ($required_shared as $field => $label) {
    if ($raw[$field] === '') {
        redirect_err("Field '{$label}' wajib diisi.", $raw, $paket_id);
    }
}

if (!$raw['accepttos']) {
    redirect_err('Anda harus menyetujui Syarat & Ketentuan.', $raw, $paket_id);
}

// NIK 16 digit angka
if (!preg_match('/^\d{16}$/', $raw['nik'])) {
    redirect_err('NIK harus terdiri dari 16 digit angka.', $raw, $paket_id);
}

// Jenis kelamin
if (!in_array($raw['jenis_kelamin'], ['L','P'], true)) {
    redirect_err('Pilih jenis kelamin dengan benar.', $raw, $paket_id);
}

// Tanggal lahir valid & minimal 17 tahun
$tgl_lahir = DateTime::createFromFormat('Y-m-d', $raw['tanggal_lahir']);
if (!$tgl_lahir || $tgl_lahir->diff(new DateTime())->y < 17) {
    redirect_err('Tanggal lahir tidak valid atau usia kurang dari 17 tahun.', $raw, $paket_id);
}

// Validasi tambahan mode guest
if (!$is_logged_in) {
    if ($raw['firstname'] === '') {
        redirect_err("Field 'Nama Depan' wajib diisi.", $raw, $paket_id);
    }
    if ($raw['email'] === '' || !filter_var($raw['email'], FILTER_VALIDATE_EMAIL)) {
        redirect_err('Format email tidak valid.', $raw, $paket_id);
    }
    if ($raw['phonenumber'] === '') {
        redirect_err("Field 'Nomor HP' wajib diisi.", $raw, $paket_id);
    }
    if (strlen($raw['password']) < 8) {
        redirect_err('Password minimal 8 karakter.', $raw, $paket_id);
    }
    if ($raw['password'] !== $raw['password_confirm']) {
        redirect_err('Password dan konfirmasi password tidak cocok.', $raw, $paket_id);
    }
}

// ═══════════════════════════════════════════════════════════════════
// 4. VALIDASI PAKET WIFI
// ═══════════════════════════════════════════════════════════════════

$stmt = $conn->prepare(
    "SELECT * FROM tblproducts
     WHERE id = ? AND status = 1 AND category = 'wifi' AND ready_to_sell = 1
     LIMIT 1"
);
$stmt->bind_param('i', $paket_id);
$stmt->execute();
$paket = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$paket) {
    redirect_err('Paket WiFi tidak ditemukan atau tidak tersedia.', $raw, $paket_id);
}

// ═══════════════════════════════════════════════════════════════════
// 5. PROSES FILE KTP
// ═══════════════════════════════════════════════════════════════════

$upload_dir   = dirname(__DIR__) . '/order/order_asset/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

$filename_ktp    = null;   // nama file baru (jika diupload)
$ktp_existing    = trim($_POST['foto_ktp_existing'] ?? '');  // hanya mode A

$file_uploaded = !empty($_FILES['foto_ktp']['name']) && $_FILES['foto_ktp']['error'] !== UPLOAD_ERR_NO_FILE;

if ($file_uploaded) {
    // Ada file baru diupload — proses di kedua mode
    $file       = $_FILES['foto_ktp'];
    $ext        = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed    = ['jpg','jpeg','png'];
    $max_size   = 5 * 1024 * 1024;

    if (!in_array($ext, $allowed, true)) {
        redirect_err('Format foto KTP harus JPG atau PNG.', $raw, $paket_id);
    }
    if ($file['size'] > $max_size) {
        redirect_err('Ukuran foto KTP maksimal 5 MB.', $raw, $paket_id);
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        redirect_err('Gagal mengunggah foto KTP. Silakan coba lagi.', $raw, $paket_id);
    }

    // Validasi magic bytes
    $fh    = fopen($file['tmp_name'], 'rb');
    $magic = bin2hex(fread($fh, 4));
    fclose($fh);
    if ($ext === 'png' && strpos($magic, '89504e47') !== 0) {
        redirect_err('File tidak valid: bukan PNG asli.', $raw, $paket_id);
    }
    if (in_array($ext, ['jpg','jpeg']) && strpos($magic, 'ffd8ff') !== 0) {
        redirect_err('File tidak valid: bukan JPEG asli.', $raw, $paket_id);
    }

    $filename_ktp = 'ktp_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $save_path    = $upload_dir . $filename_ktp;

    if (!move_uploaded_file($file['tmp_name'], $save_path)) {
        redirect_err('Gagal menyimpan foto KTP. Hubungi admin.', $raw, $paket_id);
    }

} elseif ($is_logged_in && $ktp_existing !== '') {
    // Mode A: tidak ada upload baru — pakai KTP yang sudah ada
    $filename_ktp = $ktp_existing;

} else {
    // Mode B (guest) atau mode A tanpa KTP lama: foto wajib
    redirect_err('Foto KTP wajib diunggah.', $raw, $paket_id);
}

// ═══════════════════════════════════════════════════════════════════
// 6. CEK DUPLIKAT (hanya mode guest)
// ═══════════════════════════════════════════════════════════════════

if (!$is_logged_in) {
    $stmt = $conn->prepare("SELECT id FROM tblclients WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $raw['email']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $stmt->close();
        // Hapus file KTP yang sudah terlanjur tersimpan
        if ($file_uploaded && isset($save_path) && file_exists($save_path)) @unlink($save_path);
        redirect_err(
            'Email sudah terdaftar. Silakan <a href="/login/login.php?redirect=' .
            urlencode('/order/order_wifi.php?paket_id=' . $paket_id) . '">login</a> atau gunakan email lain.',
            $raw, $paket_id
        );
    }
    $stmt->close();

    $stmt = $conn->prepare("SELECT id FROM tblclients WHERE nik = ? LIMIT 1");
    $stmt->bind_param('s', $raw['nik']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $stmt->close();
        if ($file_uploaded && isset($save_path) && file_exists($save_path)) @unlink($save_path);
        redirect_err('NIK sudah terdaftar. Hubungi kami jika ada pertanyaan.', $raw, $paket_id);
    }
    $stmt->close();
}

// ═══════════════════════════════════════════════════════════════════
// 7. CEK DUPLIKAT NIK (mode logged_in — beda user)
// ═══════════════════════════════════════════════════════════════════

if ($is_logged_in) {
    $stmt = $conn->prepare(
        "SELECT id FROM tblclients WHERE nik = ? AND id != ? LIMIT 1"
    );
    $stmt->bind_param('si', $raw['nik'], $_SESSION['user_id']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $stmt->close();
        if ($file_uploaded && isset($save_path) && file_exists($save_path)) @unlink($save_path);
        redirect_err('NIK sudah terdaftar pada akun lain. Hubungi kami jika ada pertanyaan.', $raw, $paket_id);
    }
    $stmt->close();
}

// ═══════════════════════════════════════════════════════════════════
// 8. TRANSAKSI DATABASE
// ═══════════════════════════════════════════════════════════════════

$conn->begin_transaction();

try {

    // ── Tentukan client_id & data client ─────────────────────────
    if ($is_logged_in) {

        // ── MODE A: Update data KTP di akun yang sudah ada ───────
        $client_id = (int)$_SESSION['user_id'];

        // Ambil data akun saat ini (untuk data sukses & order)
        $st = $conn->prepare(
            "SELECT firstname, lastname, email, phonenumber FROM tblclients
             WHERE id = ? LIMIT 1"
        );
        $st->bind_param('i', $client_id);
        $st->execute();
        $client_data = $st->get_result()->fetch_assoc();
        $st->close();

        if (!$client_data) {
            throw new RuntimeException('Akun tidak ditemukan.');
        }

        // Update kolom KTP di tblclients
        $st = $conn->prepare("
            UPDATE tblclients
               SET nik            = ?,
                   tempat_lahir   = ?,
                   tanggal_lahir  = ?,
                   jenis_kelamin  = ?,
                   foto_ktp       = ?
             WHERE id = ?
        ");
        $st->bind_param(
            'sssssi',
            $raw['nik'], $raw['tempat_lahir'], $raw['tanggal_lahir'],
            $raw['jenis_kelamin'], $filename_ktp,
            $client_id
        );
        $st->execute();
        $st->close();

        $firstname = $client_data['firstname'];
        $lastname  = $client_data['lastname'];
        $email     = $client_data['email'];

    } else {

        // ── MODE B: Buat akun baru ────────────────────────────────
        $pw_hash  = password_hash($raw['password'], PASSWORD_BCRYPT);
        $address1 = $raw['alamat_pasang'] . ' RT.' . $raw['rt'] . '/RW.' . $raw['rw'];
        $address2 = ($raw['patokan'] ? 'Patokan: ' . $raw['patokan'] . ' — ' : '')
                  . $raw['kelurahan'] . ', ' . $raw['kecamatan'];

        $currency       = 1;
        $email_verified = 0;
        $level          = 3;
        $status         = 1;
        $country        = 'ID';

        $st = $conn->prepare("
            INSERT INTO tblclients
              (firstname, lastname, email, phonenumber, companyname,
               address1, address2, city, state, postcode, country,
               currency, password, marketingoptin, accepttos, email_verified,
               level, status,
               nik, tempat_lahir, tanggal_lahir, jenis_kelamin, foto_ktp)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        // Type string breakdown (23 kolom):
        //  s s s s s  → firstname lastname email phonenumber companyname
        //  s s s s s s → address1 address2 city state postcode country
        //  i s        → currency password
        //  i i i      → marketingoptin accepttos email_verified
        //  i i        → level status
        //  s s s s s  → nik tempat_lahir tanggal_lahir jenis_kelamin foto_ktp
        $st->bind_param(
            'sssssssssssisiiiiisssss',
            $raw['firstname'], $raw['lastname'], $raw['email'],
            $raw['phonenumber'], $raw['companyname'],
            $address1, $address2, $raw['kota'], $raw['provinsi'],
            $raw['kodepos'], $country,
            $currency, $pw_hash,
            $raw['marketingoptin'], $raw['accepttos'], $email_verified,
            $level, $status,
            $raw['nik'], $raw['tempat_lahir'], $raw['tanggal_lahir'],
            $raw['jenis_kelamin'], $filename_ktp
        );
        $st->execute();
        $client_id = $conn->insert_id;
        if (!$client_id) {
            throw new RuntimeException('Gagal membuat akun client baru — insert_id kosong.');
        }
        $st->close();

        $firstname = $raw['firstname'];
        $lastname  = $raw['lastname'];
        $email     = $raw['email'];
    }

    // ── Generate nomor order unik ─────────────────────────────────
    // CATATAN: order_number dibuat setelah INSERT tblorders agar pakai order_id,
    // bukan client_id yang bisa bentrok jika satu client order berkali-kali.
    // Sementara pakai timestamp + client_id sebagai placeholder, lalu UPDATE setelah dapat order_id.
    $order_number_tmp = 'ORD-' . date('Ymd') . '-TMP-' . $client_id . '-' . time();

    // ── INSERT tblorders ──────────────────────────────────────────
    $wifi_status    = 'pending';
    $payment_status = 'belum_bayar';
    $order_type     = 'wifi';
    $whmcs_status   = 'Pending';
    $note           = $raw['patokan'] ? 'Patokan: ' . $raw['patokan'] : null;

    $st = $conn->prepare("
        INSERT INTO tblorders
          (order_number, order_type, userid, productid, status, wifi_status,
           alamat_pasang, rt, rw, kelurahan, kecamatan, kota, provinsi, kodepos,
           payment_status, note)
        VALUES (?,?,?,?,?,?,  ?,?,?,?,?,?,?,?,  ?,?)
    ");
    $st->bind_param(
        'ssiissssssssssss',
        $order_number_tmp, $order_type, $client_id, $paket_id,
        $whmcs_status, $wifi_status,
        $raw['alamat_pasang'], $raw['rt'], $raw['rw'],
        $raw['kelurahan'], $raw['kecamatan'], $raw['kota'],
        $raw['provinsi'], $raw['kodepos'],
        $payment_status, $note
    );
    $st->execute();
    $order_id = $conn->insert_id;
    $st->close();

    // Sekarang kita punya order_id — generate nomor order final dan UPDATE
    $order_number = 'ORD-' . date('Ymd') . '-' . str_pad($order_id, 4, '0', STR_PAD_LEFT);
    $st = $conn->prepare("UPDATE tblorders SET order_number = ? WHERE id = ?");
    $st->bind_param('si', $order_number, $order_id);
    $st->execute();
    $st->close();

    // ── Buat invoice (status Unpaid) untuk order ini ──
    // duedate diset 24 jam dari sekarang (instalasi WiFi mensyaratkan pembayaran awal
    // sebelum dijadwalkan; admin yang mengonfirmasi lunas secara manual).
    $invoice_duedate = (new DateTime())->modify('+24 hours')->format('Y-m-d H:i:s');
    $st = $conn->prepare("
        INSERT INTO tblinvoices (userid, order_id, status, total, duedate, created_at, updated_at)
        VALUES (?, ?, 'Unpaid', ?, ?, NOW(), NOW())
    ");
    $invoice_total = (float)$paket['price'];
    $st->bind_param('iids', $client_id, $order_id, $invoice_total, $invoice_duedate);
    $st->execute();
    $st->close();

    // ── Log status awal ───────────────────────────────────────────
    $catatan_log = $is_logged_in
        ? 'Order baru dibuat oleh client yang sudah terdaftar'
        : 'Order baru dibuat oleh client baru (registrasi sekaligus)';

    $st = $conn->prepare("
        INSERT INTO tblorder_status_logs
          (order_id, old_status, new_status, changed_by, role, catatan)
        VALUES (?, NULL, 'pending', ?, 'system', ?)
    ");
    $st->bind_param('iis', $order_id, $client_id, $catatan_log);
    $st->execute();
    $st->close();

    // ── Notifikasi client ─────────────────────────────────────────
    $judul_c = "Order WiFi Anda Berhasil Dikirim";
    $pesan_c = "Terima kasih {$firstname}! Order WiFi paket {$paket['name']} (#{$order_number}) berhasil diterima. "
             . "Tim kami akan segera memverifikasi data Anda dan menghubungi untuk jadwal instalasi.";
    $st = $conn->prepare(
        "INSERT INTO tblnotifikasi (userid, order_id, judul, pesan, tipe) VALUES (?,?,?,?,'sukses')"
    );
    $st->bind_param('iiss', $client_id, $order_id, $judul_c, $pesan_c);
    $st->execute();
    $st->close();

    // ── Notifikasi semua admin ────────────────────────────────────
    $judul_a = "Order WiFi Baru — {$order_number}";
    $pesan_a = "Order baru dari {$firstname} {$lastname} untuk paket {$paket['name']}. "
             . "Alamat: {$raw['alamat_pasang']}, {$raw['kelurahan']}, {$raw['kecamatan']}, {$raw['kota']}. "
             . "Status: Menunggu Verifikasi.";
    notif_admins($conn, $order_id, $judul_a, $pesan_a);

    $conn->commit();

    // ── Ambil nomor HP untuk email admin ─────────────────────────
    $phonenumber_for_mail = '';
    if ($is_logged_in) {
        $phonenumber_for_mail = $client_data['phonenumber'] ?? '';
    } else {
        $phonenumber_for_mail = $raw['phonenumber'] ?? '';
    }

    $alamat_lengkap = $raw['alamat_pasang'] . ', RT.' . $raw['rt'] . '/RW.' . $raw['rw']
                    . ', ' . $raw['kelurahan'] . ', ' . $raw['kecamatan'] . ', ' . $raw['kota'];

    // ── Email ke CLIENT ───────────────────────────────────────────
    $client_email_data = [
        'order_number' => $order_number,
        'client_name'  => trim($firstname . ' ' . $lastname),
        'email'        => $email,
        'paket_name'   => $paket['name'],
        'paket_speed'  => $paket['speed']  ?? '-',
        'paket_price'  => $paket['price'],
        'alamat'       => $alamat_lengkap,
        'is_new_user'  => !$is_logged_in,
        'login_url'    => 'https://perkasasolusindo.co.id/login/login.php',
    ];

    $mail_client_ok = perkasa_send_mail(
        $email,
        trim($firstname . ' ' . $lastname),
        '✅ Order WiFi Anda Berhasil — ' . $order_number,
        render_email_order_wifi($client_email_data)
    );

    if (!$mail_client_ok) {
        // Gagal kirim email bukan alasan rollback — order sudah masuk DB
        error_log('[PERKASA MAIL] Gagal kirim email konfirmasi ke client: ' . $email . ' | Order: ' . $order_number);
    }

    // ── Email ke ADMIN / OWNER (semua yang level 1 & 2) ──────────
    $admin_email_data = [
        'order_number' => $order_number,
        'client_name'  => trim($firstname . ' ' . $lastname),
        'email'        => $email,
        'phonenumber'  => $phonenumber_for_mail,
        'paket_name'   => $paket['name'],
        'paket_speed'  => $paket['speed'] ?? '-',
        'alamat'       => $alamat_lengkap,
        'is_new_user'  => !$is_logged_in,
        'admin_url'    => 'https://perkasasolusindo.co.id/admin/admin_dashboard.php',
    ];

    // Ambil semua email admin aktif
    $st_adm = $conn->prepare(
        "SELECT firstname, lastname, email FROM tblclients
         WHERE level IN (1,2) AND status = 1"
    );
    $st_adm->execute();
    $admin_rows = $st_adm->get_result()->fetch_all(MYSQLI_ASSOC);
    $st_adm->close();

    foreach ($admin_rows as $adm) {
        $mail_admin_ok = perkasa_send_mail(
            $adm['email'],
            trim($adm['firstname'] . ' ' . $adm['lastname']),
            '🔔 Order WiFi Baru — ' . $order_number,
            render_email_order_admin($admin_email_data)
        );
        if (!$mail_admin_ok) {
            error_log('[PERKASA MAIL] Gagal kirim notif order ke admin: ' . $adm['email'] . ' | Order: ' . $order_number);
        }
    }

    // ── Session sukses untuk order_sukses.php ─────────────────────
    $_SESSION['order_success'] = [
        'order_number' => $order_number,
        'order_id'     => $order_id,
        'client_id'    => $client_id,
        'client_name'  => trim($firstname . ' ' . $lastname),
        'email'        => $email,
        'paket_name'   => $paket['name'],
        'paket_speed'  => $paket['speed']  ?? '-',
        'paket_price'  => $paket['price'],
        'alamat'       => $raw['alamat_pasang'] . ', RT.' . $raw['rt'] . '/RW.' . $raw['rw']
                        . ', ' . $raw['kelurahan'] . ', ' . $raw['kecamatan'] . ', ' . $raw['kota'],
        'is_new_user'  => !$is_logged_in,   // untuk order_sukses.php jika ingin beda pesan
    ];

    header('Location: order_sukses.php');
    exit;

} catch (Throwable $e) {
    $conn->rollback();

    // Hapus file KTP yang sudah terupload jika transaksi gagal
    if ($file_uploaded && isset($save_path) && file_exists($save_path)) {
        @unlink($save_path);
    }

    error_log('[PERKASA ORDER ERROR] ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
    redirect_err('Terjadi kesalahan sistem. Silakan coba lagi atau hubungi admin.', $raw, $paket_id);
}
