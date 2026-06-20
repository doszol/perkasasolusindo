<?php
// =====================================================
//  admin/approve_hosting.php
//  Endpoint POST: Admin approve order hosting
//
//  ALUR WAJIB sebelum approve:
//  ✅ payment_status = 'lunas' (sudah dikonfirmasi admin)
//
//  Otomatis:
//  1. Validasi pembayaran sudah lunas
//  2. Buat akun user di DirectAdmin
//     → DA auto-buat document root & DNS zone
//  3. Buat database MySQL di akun user tersebut
//  4. Buat DB user & assign privileges
//  5. Simpan semua credential ke tblhosting
//  6. Update status order → active, domainstatus → Active
//  7. Notif in-app + email credential ke client
//
//  Dipanggil via fetch/AJAX dari admin/order_detail.php
// =====================================================
require_once __DIR__ . '/../auth_check.php';
requireLevel([1, 2]);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../mailer.php';
require_once __DIR__ . '/../directadmin_api.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Method tidak diizinkan.']);
    exit;
}

$order_id = (int)($_POST['order_id'] ?? 0);
$admin_id = (int)$_SESSION['user_id'];

if ($order_id <= 0) {
    echo json_encode(['ok' => false, 'msg' => 'Order ID tidak valid.']);
    exit;
}

// ── Ambil data lengkap ──
$st = $conn->prepare("
    SELECT
        o.id              AS order_id,
        o.order_number,
        o.userid          AS client_id,
        o.productid,
        o.payment_status,
        o.wifi_status     AS order_status,
        o.periode_bulan,
        h.id              AS hosting_id,
        h.domain,
        h.domain_type,
        h.da_username,
        h.da_password,
        h.da_status,
        p.name            AS paket_name,
        p.price           AS paket_price,
        p.da_package      AS da_package_override,
        c.firstname,
        c.lastname,
        c.email           AS client_email
    FROM tblorders o
    JOIN tblhosting h  ON h.userid = o.userid AND h.packageid = o.productid
    JOIN tblproducts p ON p.id = o.productid
    JOIN tblclients  c ON c.id = o.userid
    WHERE o.id = ? AND o.order_type = 'hosting'
    ORDER BY h.id DESC
    LIMIT 1
");
$st->bind_param('i', $order_id);
$st->execute();
$row = $st->get_result()->fetch_assoc();
$st->close();

if (!$row) {
    echo json_encode(['ok' => false, 'msg' => 'Order tidak ditemukan.']);
    exit;
}

// ══════════════════════════════════════════════════════
//  GATE 1: Cek apakah pembayaran sudah dikonfirmasi
//  Admin WAJIB konfirmasi bukti bayar terlebih dahulu
// ══════════════════════════════════════════════════════
if ($row['payment_status'] !== 'lunas') {
    $statusLabel = [
        'belum_bayar' => 'Belum ada bukti pembayaran dari client.',
        'sudah_bayar' => 'Client sudah upload bukti, tapi admin belum konfirmasi pembayaran.',
    ];
    $hint = $statusLabel[$row['payment_status']] ?? 'Status pembayaran tidak diketahui.';
    echo json_encode([
        'ok'  => false,
        'msg' => "❌ Hosting tidak dapat diaktifkan. {$hint} Konfirmasi pembayaran terlebih dahulu sebelum approve hosting.",
    ]);
    exit;
}

// ══════════════════════════════════════════════════════
//  GATE 2: Cek apakah sudah aktif sebelumnya
// ══════════════════════════════════════════════════════
if ($row['da_status'] === 'active') {
    echo json_encode(['ok' => false, 'msg' => 'Akun hosting sudah aktif sebelumnya.']);
    exit;
}

$da_username = $row['da_username'];
$da_password = $row['da_password'];

// Prioritas package:
// 1. Kolom da_package di tblproducts (jika diisi manual)
// 2. Otomatis dari harga produk: ≤100rb → starterpaket, ≤200rb → bisnispaket
// 3. Fallback ke DA_PACKAGE di config
if (!empty($row['da_package_override'])) {
    $da_package = $row['da_package_override'];
} else {
    $da_package = da_package_from_price($row['paket_price']);
}
$step_log = [];

// ══════════════════════════════════════════════════════
//  LANGKAH 1: Buat akun user di DirectAdmin
//  DA otomatis membuat:
//  - /home/{da_username}/
//  - /home/{da_username}/domains/{domain}/public_html/
//  - DNS zone untuk domain/subdomain
// ══════════════════════════════════════════════════════
$result_user = da_create_user(
    $da_username,
    $row['client_email'],
    $da_password,
    $row['domain'],
    $da_package
);

if (!$result_user['success']) {
    error_log('[approve_hosting] Buat akun DA gagal | order=' . $row['order_number'] . ' | ' . $result_user['message']);
    $conn->query("UPDATE tblhosting SET da_status='failed' WHERE id={$row['hosting_id']}");
    echo json_encode(['ok' => false, 'msg' => '❌ Gagal membuat akun DirectAdmin: ' . $result_user['message']]);
    exit;
}

$step_log[] = '✅ Akun DA dibuat: ' . $da_username;
$step_log[] = '📁 Doc root: /home/' . $da_username . '/domains/' . $row['domain'] . '/public_html/';

// ══════════════════════════════════════════════════════
//  LANGKAH 2: Buat database + DB user + assign
//  Hasil: {da_username}_db & {da_username}_usr
// ══════════════════════════════════════════════════════
$db_password = da_generate_password(14);
$result_db   = da_create_database($da_username, $db_password);

$db_name_full = $result_db['db_name_full'];
$db_user_full = $result_db['db_user_full'];
$db_host      = 'localhost';

if ($result_db['success']) {
    $step_log[] = '✅ Database: ' . $db_name_full;
    $step_log[] = '✅ DB User : ' . $db_user_full;
} else {
    error_log('[approve_hosting] DB gagal | order=' . $row['order_number'] . ' | ' . $result_db['message']);
    $step_log[] = '⚠️ Database gagal: ' . $result_db['message'];
    $db_name_full = '';
    $db_user_full = '';
    $db_password  = '';
}

// ══════════════════════════════════════════════════════
//  LANGKAH 3: Update database & status
// ══════════════════════════════════════════════════════
$da_docroot = '/home/' . $da_username . '/domains/' . $row['domain'] . '/public_html';
$da_panel   = 'https://' . DA_HOST . ':' . DA_PORT;

// ══════════════════════════════════════════════════════
//  Hitung tanggal expire (jatuh tempo) hosting
//  berdasarkan periode_bulan yang dipilih client saat order
//  (default 1 bulan jika kosong/tidak valid, untuk jaga-jaga
//  data lama sebelum kolom periode_bulan ditambahkan).
// ══════════════════════════════════════════════════════
$periodeBulan = (int)($row['periode_bulan'] ?? 1);
if ($periodeBulan < 1)  $periodeBulan = 1;
if ($periodeBulan > 12) $periodeBulan = 12;
$expireDate = (new DateTime())->modify("+{$periodeBulan} month")->format('Y-m-d');

// Nama client (dipakai untuk notifikasi & email di bawah)
$client_name = trim($row['firstname'] . ' ' . $row['lastname']);

$conn->begin_transaction();
try {
    // Simpan semua credential ke tblhosting + set domainstatus → Active
    // + set nextduedate = expire date sesuai periode order, dan kosongkan payment_deadline
    //   (sudah lunas, deadline pembayaran 24 jam tidak relevan lagi)
    $upd = $conn->prepare("
        UPDATE tblhosting SET
            da_status        = 'active',
            domainstatus     = 'Active',
            nextduedate      = ?,
            payment_deadline = NULL,
            da_db_name       = ?,
            da_db_user       = ?,
            da_db_pass       = ?,
            da_db_host       = ?,
            da_docroot       = ?,
            updated_at       = NOW()
        WHERE id = ?
    ");
    $upd->bind_param('ssssssi', $expireDate, $db_name_full, $db_user_full, $db_password, $db_host, $da_docroot, $row['hosting_id']);
    $upd->execute();
    $upd->close();

    // Update status order → active + set tanggal_expire & kosongkan payment_deadline
    $upd2 = $conn->prepare("
        UPDATE tblorders SET
            wifi_status      = 'active',
            tanggal_expire   = ?,
            payment_deadline = NULL,
            updated_at       = NOW()
        WHERE id = ?
    ");
    $upd2->bind_param('si', $expireDate, $order_id);
    $upd2->execute();
    $upd2->close();

    // Safety net: pastikan invoice terkait order ini berstatus Paid.
    // Normalnya sudah diset Paid saat admin klik "Konfirmasi Pembayaran" (order_detail.php),
    // tapi baris ini menjaga konsistensi jika ada alur lama yang melewati langkah itu.
    $updInv = $conn->prepare("
        UPDATE tblinvoices SET status='Paid', datepaid=COALESCE(datepaid, NOW())
        WHERE order_id = ? AND status != 'Paid'
    ");
    $updInv->bind_param('i', $order_id);
    $updInv->execute();
    $updInv->close();

    // Log
    $catatan_log = implode(' | ', $step_log);
    $stLog = $conn->prepare("
        INSERT INTO tblorder_status_logs (order_id, old_status, new_status, changed_by, role, catatan, created_at)
        VALUES (?, ?, 'active', ?, 'admin', ?, NOW())
    ");
    $old_status = $row['order_status'];
    $stLog->bind_param('isis', $order_id, $old_status, $admin_id, $catatan_log);
    $stLog->execute();
    $stLog->close();

    // Notifikasi in-app ke client
    $expireLabel = date('d M Y', strtotime($expireDate));
    $judul_notif = '✅ Hosting Anda Sudah Aktif!';
    $pesan_notif = "Pembayaran Anda telah diterima. Akun hosting untuk {$row['domain']} sudah aktif. Berlaku hingga {$expireLabel}. Credential login dikirim ke email Anda.";
    $stN = $conn->prepare("INSERT INTO tblnotifikasi (userid, order_id, judul, pesan, tipe) VALUES (?, ?, ?, ?, 'sukses')");
    $stN->bind_param('iiss', $row['client_id'], $order_id, $judul_notif, $pesan_notif);
    $stN->execute();
    $stN->close();

    // Notifikasi in-app ke admin/owner lain (selain yang sedang approve) — konfirmasi pembayaran diterima & hosting aktif
    $judul_notif_admin = "💰 Pembayaran Diterima — Hosting #{$row['order_number']} Aktif";
    $pesan_notif_admin = "Pembayaran {$client_name} untuk order #{$row['order_number']} ({$row['domain']}) telah dikonfirmasi dan hosting sudah diaktifkan oleh admin.";
    $stAdminList = $conn->query("SELECT id FROM tblclients WHERE level IN (1,2) AND status = 1 AND id != {$admin_id}");
    if ($stAdminList) {
        $stNAdmin = $conn->prepare("INSERT INTO tblnotifikasi (userid, order_id, judul, pesan, tipe) VALUES (?, ?, ?, ?, 'sukses')");
        while ($admRow = $stAdminList->fetch_assoc()) {
            $admIdLoop = (int)$admRow['id'];
            $stNAdmin->bind_param('iiss', $admIdLoop, $order_id, $judul_notif_admin, $pesan_notif_admin);
            $stNAdmin->execute();
        }
        $stNAdmin->close();
    }

    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    error_log('[approve_hosting] DB error: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'msg' => 'Akun DA berhasil dibuat tapi gagal update sistem. Hubungi developer.']);
    exit;
}

// ══════════════════════════════════════════════════════
//  LANGKAH 4: Kirim email credential ke client
// ══════════════════════════════════════════════════════
perkasa_send_mail(
    $row['client_email'],
    $client_name,
    "☁️ Hosting Aktif — Credential Login #{$row['order_number']}",
    render_email_hosting_aktif([
        'client_name'  => $client_name,
        'order_number' => $row['order_number'],
        'paket_name'   => $row['paket_name'],
        'domain'       => $row['domain'],
        'expire_date'  => $expireDate,
        'da_panel'     => $da_panel,
        'da_username'  => $da_username,
        'da_password'  => $da_password,
        'da_docroot'   => $da_docroot,
        'db_name'      => $db_name_full,
        'db_user'      => $db_user_full,
        'db_password'  => $db_password,
        'db_host'      => $db_host,
        'db_ok'        => $result_db['success'],
    ])
);

echo json_encode([
    'ok'  => true,
    'msg' => "Berhasil! Akun hosting & database aktif. Email dikirim ke {$row['client_email']}.",
    'log' => $step_log,
]);
