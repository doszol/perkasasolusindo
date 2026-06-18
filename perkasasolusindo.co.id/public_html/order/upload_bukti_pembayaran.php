<?php
// =====================================================
//  upload_bukti_pembayaran.php  –  /order/upload_bukti_pembayaran.php
//  Proses upload bukti pembayaran oleh client.
// =====================================================
require_once __DIR__ . '/../auth_check.php';
requireLevel(3);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../mailer.php';

$userId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /client/client_dashboard.php?view=layanan_wifi');
    exit;
}

$orderId      = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
// redirect_view: dikirim oleh form (layanan_wifi/detail untuk WiFi, layanan_hosting untuk hosting)
// agar client diarahkan balik ke halaman yang sesuai setelah upload.
$redirectView = $_POST['redirect_view'] ?? 'detail';
$allowedViews = ['detail', 'layanan_wifi', 'layanan_hosting'];
if (!in_array($redirectView, $allowedViews, true)) {
    $redirectView = 'detail';
}

function backToDetail($orderId, $errorMsg = null, $redirectView = 'detail') {
    if ($errorMsg !== null) {
        if ($redirectView === 'layanan_hosting') {
            // Error khusus hosting disimpan terpisah + tag order_id, supaya hanya
            // kartu hosting terkait yang menampilkan pesan error (lihat client_dashboard.php).
            $_SESSION['upload_bukti_hosting_error']     = $errorMsg;
            $_SESSION['upload_bukti_hosting_error_oid'] = $orderId;
        } else {
            $_SESSION['upload_bukti_error'] = $errorMsg;
        }
    }
    if ($redirectView === 'layanan_hosting') {
        header('Location: /client/client_dashboard.php?view=layanan_hosting');
    } else {
        header('Location: /client/client_dashboard.php?view=detail&id=' . $orderId);
    }
    exit;
}

if ($orderId <= 0) {
    backToDetail($orderId, 'Order tidak valid.', $redirectView);
}

// ── Pastikan order milik client ini, order_type wifi ATAU hosting, dan belum bayar ──
$st = $conn->prepare("
    SELECT o.id, o.order_number, o.order_type, o.payment_status, p.name AS paket_name, p.speed AS paket_speed
    FROM tblorders o
    LEFT JOIN tblproducts p ON p.id = o.productid
    WHERE o.id = ? AND o.userid = ? AND o.order_type IN ('wifi','hosting') LIMIT 1
");
$st->bind_param('ii', $orderId, $userId);
$st->execute();
$order = $st->get_result()->fetch_assoc();
$st->close();

if (!$order) {
    backToDetail($orderId, 'Order tidak ditemukan.', $redirectView);
}

if ($order['payment_status'] !== 'belum_bayar') {
    backToDetail($orderId, 'Pembayaran untuk order ini sudah diproses sebelumnya.', $redirectView);
}

// ── Validasi file upload ──
if (!isset($_FILES['bukti_pembayaran']) || $_FILES['bukti_pembayaran']['error'] === UPLOAD_ERR_NO_FILE) {
    backToDetail($orderId, 'Silakan pilih file bukti pembayaran terlebih dahulu.', $redirectView);
}

$file = $_FILES['bukti_pembayaran'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    backToDetail($orderId, 'Terjadi kesalahan saat upload file. Silakan coba lagi.', $redirectView);
}

$maxSize = 5 * 1024 * 1024; // 5MB
if ($file['size'] > $maxSize) {
    backToDetail($orderId, 'Ukuran file terlalu besar. Maksimal 5MB.', $redirectView);
}

$allowedExt  = ['jpg', 'jpeg', 'png', 'pdf'];
$allowedMime = [
    'image/jpeg'      => ['jpg', 'jpeg'],
    'image/png'       => ['png'],
    'application/pdf' => ['pdf'],
];

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowedExt, true)) {
    backToDetail($orderId, 'Format file tidak didukung. Gunakan JPG, JPEG, PNG, atau PDF.', $redirectView);
}

// Verifikasi MIME type sebenarnya (anti spoofing ekstensi)
$finfo    = finfo_open(FILEINFO_MIME_TYPE);
$realMime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!isset($allowedMime[$realMime]) || !in_array($ext, $allowedMime[$realMime], true)) {
    backToDetail($orderId, 'File tidak valid atau rusak. Pastikan file benar-benar berformat JPG, PNG, atau PDF.', $redirectView);
}

// ── Simpan file ──
$uploadDir = __DIR__ . '/order_asset/bukti_pembayaran/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$safeName = 'bukti_' . $order['order_number'] . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$destPath = $uploadDir . $safeName;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    backToDetail($orderId, 'Gagal menyimpan file. Silakan coba lagi.', $redirectView);
}

// ── Update database: payment_status & payment_proof ──
$conn->begin_transaction();
try {
    $stUpd = $conn->prepare("UPDATE tblorders SET payment_status = 'sudah_bayar', payment_proof = ? WHERE id = ? AND userid = ?");
    $stUpd->bind_param('sii', $safeName, $orderId, $userId);
    $stUpd->execute();
    $stUpd->close();

    // ── Notifikasi ke admin & owner (level 1 & 2) ──
    $clientName  = $_SESSION['user_firstname'] . ' ' . $_SESSION['user_lastname'];
    $jenisLayanan = ($order['order_type'] === 'hosting') ? 'hosting' : 'WiFi';
    $judul       = 'Konfirmasi Pembayaran — #' . $order['order_number'];
    $pesan       = 'Client ' . $clientName . ' telah mengupload bukti pembayaran ' . $jenisLayanan
                 . ' untuk order #' . $order['order_number'] . '. Silakan periksa dan verifikasi pembayaran.';

    $stAdmins = $conn->prepare("SELECT id FROM tblclients WHERE level IN (1,2) AND status = 1");
    $stAdmins->execute();
    $admins = $stAdmins->get_result()->fetch_all(MYSQLI_ASSOC);
    $stAdmins->close();

    $stNotif = $conn->prepare("INSERT INTO tblnotifikasi (userid, order_id, judul, pesan, tipe, sudah_dibaca, created_at) VALUES (?, ?, ?, ?, 'info', 0, NOW())");
    foreach ($admins as $admin) {
        $adminId = (int)$admin['id'];
        $stNotif->bind_param('iiss', $adminId, $orderId, $judul, $pesan);
        $stNotif->execute();
    }
    $stNotif->close();

    // ── Log perubahan status pembayaran ──
    $stLog = $conn->prepare("INSERT INTO tblorder_status_logs (order_id, old_status, new_status, changed_by, role, catatan, created_at) VALUES (?, ?, ?, ?, 'client', ?, NOW())");
    $oldStatus = 'belum_bayar';
    $newStatus = 'sudah_bayar';
    $catatan   = 'Client mengupload bukti pembayaran.';
    $stLog->bind_param('issis', $orderId, $oldStatus, $newStatus, $userId, $catatan);
    $stLog->execute();
    $stLog->close();

    $conn->commit();

    // ── Kirim email konfirmasi pembayaran ke client ──
    perkasa_send_mail(
        $_SESSION['user_email'],
        $clientName,
        'Pembayaran Diterima — ' . $order['order_number'],
        render_email_payment_received([
            'order_number' => $order['order_number'],
            'client_name'  => $clientName,
            'email'        => $_SESSION['user_email'],
            'paket_name'   => $order['paket_name'] ?? '-',
            'paket_speed'  => $order['paket_speed'] ?? '-',
        ])
    );
} catch (Exception $e) {
    $conn->rollback();
    // Hapus file yang sudah terupload jika DB gagal
    if (file_exists($destPath)) {
        unlink($destPath);
    }
    error_log('[upload_bukti_pembayaran] ' . $e->getMessage());
    backToDetail($orderId, 'Gagal menyimpan data pembayaran. Silakan coba lagi.', $redirectView);
}

// ── Redirect sukses: kembali ke view yang sesuai (hosting atau wifi/detail) ──
if ($redirectView === 'layanan_hosting') {
    header('Location: /client/client_dashboard.php?view=layanan_hosting');
} else {
    header('Location: /client/client_dashboard.php?view=detail&id=' . $orderId);
}
exit;
