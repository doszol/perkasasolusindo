<?php
// =====================================================
//  order/process_order_hosting.php
//  Proses order hosting — dipanggil dari order_hosting.php
//  Mode: 'guest' (registrasi baru + order) | 'login' (sudah login)
// =====================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth_check.php';
require_once __DIR__ . '/../mailer.php';
require_once __DIR__ . '/../directadmin_api.php';

// Hanya POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: order_hosting.php');
    exit;
}

// ── Ambil & sanitasi input ──
$mode          = $_POST['mode']           ?? '';
$paket_id      = (int)($_POST['paket_id'] ?? 0);
$domain_type   = trim($_POST['domain_type']   ?? 'subdomain');
$domain_raw    = trim($_POST['domain']        ?? '');
$domain_custom = trim($_POST['domain_custom'] ?? '');
$domain_tld    = trim($_POST['domain_tld']    ?? '');
$periode       = (int)($_POST['periode_bulan'] ?? 1);
$catatan       = trim($_POST['catatan'] ?? '');
$tos           = !empty($_POST['tos']);

// ── Validasi dasar ──
if (!$tos)          redirectBack('Harap setujui Ketentuan Layanan.', $paket_id);
if ($paket_id <= 0) redirectBack('Paket tidak valid.', $paket_id);

// $domain_price TIDAK PERNAH dipercaya dari input client ($_POST) — itu rawan
// dimanipulasi via DevTools/curl (client bisa kirim harga berapa saja).
// Untuk domain_type='beli', harga SELALU dihitung ulang di server berdasarkan
// tbldomain_pricing (sumber kebenaran tunggal, sama dengan check_domain.php).
$domain_price = 0;

if ($domain_type === 'subdomain') {
    if (!preg_match('/^[a-zA-Z0-9\-]{3,80}$/', $domain_raw))
        redirectBack('Nama subdomain tidak valid. Gunakan huruf, angka, dan tanda hubung saja (min. 3 karakter).', $paket_id);
} elseif ($domain_type === 'beli') {
    if (empty($domain_custom))
        redirectBack('Silakan pilih domain yang ingin didaftarkan.', $paket_id);

    $stTld = $conn->prepare("SELECT harga_jual FROM tbldomain_pricing WHERE tld = ? AND aktif = 1 LIMIT 1");
    $stTld->bind_param('s', $domain_tld);
    $stTld->execute();
    $tldRow = $stTld->get_result()->fetch_assoc();
    $stTld->close();

    if (!$tldRow) {
        redirectBack('Ekstensi domain tidak valid atau sedang tidak tersedia.', $paket_id);
    }
    $domain_price = (float)$tldRow['harga_jual'];
} else {
    redirectBack('Tipe domain tidak dikenali.', $paket_id);
}

$periode = max(1, min(12, $periode));

// ── Cek produk ──
$stmt = $conn->prepare("SELECT * FROM tblproducts WHERE id=? AND category='hosting' AND status=1 AND ready_to_sell=1 LIMIT 1");
$stmt->bind_param('i', $paket_id);
$stmt->execute();
$produk = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$produk) redirectBack('Paket hosting tidak ditemukan atau tidak tersedia.', $paket_id);

// ── Hitung total harga ──
$harga_satuan = (float)$produk['price'];
$diskon = 1.0;
if ($periode >= 12)     $diskon = 0.90;
elseif ($periode >= 6)  $diskon = 0.95;
$total = floor($harga_satuan * $periode * $diskon) + $domain_price;

// ── Domain final ──
if ($domain_type === 'beli') {
    $domain_final = strtolower(trim($domain_custom));
} else {
    $domain_final = strtolower($domain_raw) . '.perkasasolusindo.co.id';
}

// ══════════════════════════════════════════════════════
//  MODE A: GUEST → Registrasi + Order
// ══════════════════════════════════════════════════════
if ($mode === 'guest') {

    if (!empty($_SESSION['user_id'])) {
        header('Location: process_order_hosting.php');
        exit;
    }

    $firstname = trim($_POST['firstname'] ?? '');
    $lastname  = trim($_POST['lastname']  ?? '');
    $email     = strtolower(trim($_POST['email'] ?? ''));
    $phone     = trim($_POST['phonenumber'] ?? '');
    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password_confirm'] ?? '';

    if (empty($firstname))                          redirectBack('Nama depan wajib diisi.', $paket_id);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) redirectBack('Format email tidak valid.', $paket_id);
    if (empty($phone))                              redirectBack('Nomor HP wajib diisi.', $paket_id);
    if (strlen($password) < 8)                      redirectBack('Password minimal 8 karakter.', $paket_id);
    if ($password !== $password2)                   redirectBack('Password dan konfirmasi tidak sama.', $paket_id);

    $chk = $conn->prepare("SELECT id FROM tblclients WHERE email=? LIMIT 1");
    $chk->bind_param('s', $email);
    $chk->execute();
    $chk->store_result();
    if ($chk->num_rows > 0) {
        $chk->close();
        redirectBack('Email sudah terdaftar. Silakan login terlebih dahulu.', $paket_id);
    }
    $chk->close();

    $hash = password_hash($password, PASSWORD_BCRYPT);

    // KTP upload
    $ktp_filename = null;
    if (!empty($_FILES['ktp_file']['tmp_name'])) {
        $ktp_allowed = ['image/jpeg', 'image/png', 'image/webp'];
        $ktp_mime    = mime_content_type($_FILES['ktp_file']['tmp_name']);
        $ktp_size    = $_FILES['ktp_file']['size'];

        if (!in_array($ktp_mime, $ktp_allowed))  redirectBack('Format foto KTP tidak valid.', $paket_id);
        if ($ktp_size > 3 * 1024 * 1024)         redirectBack('Ukuran foto KTP maksimal 3 MB.', $paket_id);

        $ext          = ($ktp_mime === 'image/png') ? 'png' : (($ktp_mime === 'image/webp') ? 'webp' : 'jpeg');
        $ktp_filename = 'ktp_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $ktp_dir      = __DIR__ . '/../order/order_asset/ktp/';

        if (!is_dir($ktp_dir)) mkdir($ktp_dir, 0755, true);
        if (!move_uploaded_file($_FILES['ktp_file']['tmp_name'], $ktp_dir . $ktp_filename))
            redirectBack('Gagal mengupload foto KTP.', $paket_id);
    } else {
        redirectBack('Foto KTP wajib diupload.', $paket_id);
    }

    $ins = $conn->prepare("
        INSERT INTO tblclients
          (firstname, lastname, email, phonenumber, password, accepttos, email_verified, level, status, foto_ktp, datecreated)
        VALUES (?, ?, ?, ?, ?, 1, 1, 3, 1, ?, NOW())
    ");
    $ins->bind_param('ssssss', $firstname, $lastname, $email, $phone, $hash, $ktp_filename);
    $ins->execute();
    $user_id = $conn->insert_id;
    $ins->close();

    if (!$user_id) redirectBack('Terjadi kesalahan saat membuat akun.', $paket_id);

    $_SESSION['user_id']        = $user_id;
    $_SESSION['user_firstname'] = $firstname;
    $_SESSION['user_lastname']  = $lastname;
    $_SESSION['user_email']     = $email;
    $_SESSION['user_level']     = 3;

// ══════════════════════════════════════════════════════
//  MODE B: SUDAH LOGIN
// ══════════════════════════════════════════════════════
} elseif ($mode === 'login') {

    if (empty($_SESSION['user_id']) || (int)$_SESSION['user_level'] !== 3) {
        header('Location: /login/login.php');
        exit;
    }
    $user_id = (int)$_SESSION['user_id'];

} else {
    redirectBack('Mode tidak dikenali.', $paket_id);
}

// ══════════════════════════════════════════════════════
//  Buat Order
// ══════════════════════════════════════════════════════
$order_number = 'HST-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

$dup = $conn->prepare("SELECT id FROM tblorders WHERE order_number=? LIMIT 1");
$dup->bind_param('s', $order_number);
$dup->execute();
$dup->store_result();
if ($dup->num_rows > 0) $order_number .= '-' . mt_rand(10, 99);
$dup->close();

$domain_info = ($domain_type === 'beli')
    ? "Domain Beli: {$domain_final} | Harga Domain: Rp " . number_format($domain_price, 0, ',', '.')
    : "Domain Gratis: {$domain_final}";
$note_full = "{$domain_info} | Periode: $periode bulan | Diskon: " . round((1-$diskon)*100) . "% | Total: Rp " . number_format($total,0,',','.');
if (!empty($catatan)) $note_full .= " | Catatan: $catatan";

$nextdue = (new DateTime())->modify("+{$periode} month")->format('Y-m-d');

// ── Deadline pembayaran: 24 jam sejak order dibuat ──
// Jika hingga waktu ini client belum upload bukti & lunas (admin verifikasi),
// order akan dihapus otomatis oleh cron/cron_hosting_expired.php.
$payment_deadline = (new DateTime())->modify('+24 hours')->format('Y-m-d H:i:s');

$ord = $conn->prepare("
    INSERT INTO tblorders
      (order_number, order_type, wifi_status, periode_bulan, payment_deadline, userid, productid, status, note, created_at, updated_at)
    VALUES (?, 'hosting', 'pending', ?, ?, ?, ?, 'Active', ?, NOW(), NOW())
");
$ord->bind_param('sisiis', $order_number, $periode, $payment_deadline, $user_id, $paket_id, $note_full);
$ord->execute();
$order_id = $conn->insert_id;
$ord->close();

// ── Generate DA credentials (disimpan, akun dibuat saat admin approve) ──
$da_username = da_generate_username($_SESSION['user_firstname'], $conn);
$da_password = da_generate_password(14);

// Insert tblhosting dengan da_username & da_password (da_status = pending)
// domainstatus = 'Pending' karena akun DA belum dibuat & pembayaran belum dikonfirmasi.
// Akan berubah ke 'Active' di approve_hosting.php setelah admin konfirmasi bayar & approve.
$hst = $conn->prepare("
    INSERT INTO tblhosting
      (userid, packageid, domain, domain_type, domain_tld, domain_price,
       domainstatus, nextduedate, payment_deadline, da_username, da_password, da_status, created_at)
    VALUES (?, ?, ?, ?, ?, ?, 'Pending', ?, ?, ?, ?, 'pending', NOW())
");
$hst->bind_param('iisssissss',
    $user_id, $paket_id, $domain_final, $domain_type, $domain_tld, $domain_price,
    $nextdue, $payment_deadline, $da_username, $da_password
);
$hst->execute();
$hst->close();

// ── Buat invoice (status Unpaid) untuk order ini ──
// duedate invoice disamakan dengan payment_deadline (24 jam dari sekarang).
$inv = $conn->prepare("
    INSERT INTO tblinvoices (userid, order_id, status, total, duedate, created_at, updated_at)
    VALUES (?, ?, 'Unpaid', ?, ?, NOW(), NOW())
");
$inv->bind_param('iids', $user_id, $order_id, $total, $payment_deadline);
$inv->execute();
$invoice_id = $conn->insert_id;
$inv->close();

// ── Notifikasi client ──
$judul_client = "Order Hosting Anda Berhasil Dikirim ☁️";
$pesan_client = "Terima kasih! Order hosting paket {$produk['name']} (#{$order_number}) berhasil diterima. "
              . "Domain: {$domain_final}. Tim kami akan memproses dalam 24 jam kerja.";
$notif_c = $conn->prepare("INSERT INTO tblnotifikasi (userid, order_id, judul, pesan, tipe) VALUES (?, ?, ?, ?, 'sukses')");
$notif_c->bind_param('iiss', $user_id, $order_id, $judul_client, $pesan_client);
$notif_c->execute();
$notif_c->close();

// ── Notifikasi admin ──
$admin_res = $conn->query("SELECT id FROM tblclients WHERE level IN (1,2) AND status=1");
if ($admin_res) {
    $judul_admin = "Order Hosting Baru — {$order_number}";
    $name_client = trim(($_SESSION['user_firstname'] ?? '') . ' ' . ($_SESSION['user_lastname'] ?? ''));
    $pesan_admin = "Order hosting baru dari {$name_client} untuk paket {$produk['name']}. Domain: {$domain_final}. Periode: {$periode} bulan.";
    while ($adm = $admin_res->fetch_assoc()) {
        $nf = $conn->prepare("INSERT INTO tblnotifikasi (userid, order_id, judul, pesan, tipe) VALUES (?, ?, ?, ?, 'info')");
        $nf->bind_param('iiss', $adm['id'], $order_id, $judul_admin, $pesan_admin);
        $nf->execute();
        $nf->close();
    }
}

// ── Data client untuk email ──
$client_name  = trim(($_SESSION['user_firstname'] ?? '') . ' ' . ($_SESSION['user_lastname'] ?? ''));
$client_email = $_SESSION['user_email'] ?? '';
$client_phone = '';
if ($client_email) {
    $ce = $conn->prepare("SELECT phonenumber FROM tblclients WHERE id=? LIMIT 1");
    $ce->bind_param('i', $user_id);
    $ce->execute();
    $ceRow = $ce->get_result()->fetch_assoc();
    $ce->close();
    $client_phone = $ceRow['phonenumber'] ?? '';
}

// ── Email client ──
if ($client_email) {
    perkasa_send_mail(
        $client_email, $client_name,
        "☁️ Order Hosting #{$order_number} Berhasil Dikirim — Perkasa Solusindo",
        render_email_order_hosting_client([
            'order_number'     => $order_number,
            'client_name'      => $client_name,
            'email'            => $client_email,
            'paket_name'       => $produk['name'],
            'domain'           => $domain_final,
            'domain_type'      => $domain_type,
            'periode'          => $periode,
            'total'            => $total,
            'is_new_user'      => ($mode === 'guest'),
            'payment_deadline' => $payment_deadline,
            'invoice_id'       => $invoice_id,
        ])
    );
}

// ── Email admin ──
$admin_res_email = $conn->query("SELECT firstname, lastname, email FROM tblclients WHERE level IN (1,2) AND status=1 AND email != ''");
if ($admin_res_email) {
    while ($adm_mail = $admin_res_email->fetch_assoc()) {
        perkasa_send_mail(
            $adm_mail['email'],
            trim($adm_mail['firstname'] . ' ' . $adm_mail['lastname']),
            "📋 Order Hosting Baru — {$order_number} ({$produk['name']})",
            render_email_order_hosting_admin([
                'order_number' => $order_number,
                'client_name'  => $client_name,
                'email'        => $client_email,
                'phonenumber'  => $client_phone,
                'paket_name'   => $produk['name'],
                'domain'       => $domain_final,
                'domain_type'  => $domain_type,
                'periode'      => $periode,
                'total'        => $total,
                'is_new_user'  => ($mode === 'guest'),
            ])
        );
    }
}

header("Location: order_sukses_hosting.php?order=" . urlencode($order_number) . "&paket=" . urlencode($produk['name']));
exit;

function redirectBack($pesan, $paket_id) {
    $_SESSION['order_error'] = $pesan;
    header("Location: order_hosting.php?paket_id={$paket_id}&error=1");
    exit;
}
