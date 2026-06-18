<?php
// ============================================================
// order/upload_bukti_tagihan_bulanan.php
// Letakkan di: /public_html/order/upload_bukti_tagihan_bulanan.php
//
// Dipanggil via AJAX dari client_dashboard.php
// Menerima upload bukti bayar tagihan bulanan WiFi,
// update tblpayment_monthly → waiting_confirm,
// kirim notifikasi ke admin.
// ============================================================
require_once __DIR__ . '/../auth_check.php';
requireLevel(3);
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

$userId    = (int)$_SESSION['user_id'];
$monthlyId = (int)($_POST['monthly_id'] ?? 0);
$orderId   = (int)($_POST['order_id']   ?? 0);

if (!$monthlyId || !$orderId) {
    echo json_encode(['ok' => false, 'msg' => 'Data tidak valid.']);
    exit;
}

// Validasi: tagihan milik user ini & statusnya unpaid
$stChk = $conn->prepare(
    "SELECT pm.id, pm.status, o.userid
     FROM tblpayment_monthly pm
     JOIN tblorders o ON o.id = pm.order_id
     WHERE pm.id = ? AND pm.order_id = ? AND o.userid = ?
     LIMIT 1"
);
$stChk->bind_param('iii', $monthlyId, $orderId, $userId);
$stChk->execute();
$pm = $stChk->get_result()->fetch_assoc();
$stChk->close();

if (!$pm) {
    echo json_encode(['ok' => false, 'msg' => 'Tagihan tidak ditemukan.']);
    exit;
}
if ($pm['status'] === 'paid') {
    echo json_encode(['ok' => false, 'msg' => 'Tagihan ini sudah lunas.']);
    exit;
}
if ($pm['status'] === 'waiting_confirm') {
    echo json_encode(['ok' => false, 'msg' => 'Bukti sudah diupload, menunggu konfirmasi admin.']);
    exit;
}

// Validasi file
if (empty($_FILES['bukti_tagihan']) || $_FILES['bukti_tagihan']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['ok' => false, 'msg' => 'File tidak valid atau gagal diupload.']);
    exit;
}

$file     = $_FILES['bukti_tagihan'];
$allowed  = ['image/jpeg', 'image/png', 'application/pdf'];
$finfo    = finfo_open(FILEINFO_MIME_TYPE);
$mime     = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, $allowed)) {
    echo json_encode(['ok' => false, 'msg' => 'Format file tidak didukung. Gunakan JPG, PNG, atau PDF.']);
    exit;
}
if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['ok' => false, 'msg' => 'Ukuran file maksimal 5MB.']);
    exit;
}

// Simpan file
$uploadDir = __DIR__ . '/order_asset/bukti_pembayaran/';
if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }

$ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'tagihan_' . $orderId . '_' . $monthlyId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$dest     = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    echo json_encode(['ok' => false, 'msg' => 'Gagal menyimpan file. Coba lagi.']);
    exit;
}

// Update tblpayment_monthly → waiting_confirm
$stUpd = $conn->prepare(
    "UPDATE tblpayment_monthly
     SET status = 'waiting_confirm', payment_proof = ?, updated_at = NOW()
     WHERE id = ? LIMIT 1"
);
$stUpd->bind_param('si', $filename, $monthlyId);
$stUpd->execute();
$stUpd->close();

// Ambil info order untuk notifikasi
$ordInfo = $conn->query(
    "SELECT o.order_number, CONCAT(c.firstname,' ',c.lastname) AS client_name
     FROM tblorders o JOIN tblclients c ON c.id = o.userid
     WHERE o.id = $orderId LIMIT 1"
)->fetch_assoc();

// ── Notifikasi in-app ke CLIENT (diri sendiri) ───────────────────────────────
$bulanLabel   = date('M Y', strtotime($tagihanBulan ?? 'now'));

// Ambil tagihan_bulan dari tblpayment_monthly yang baru diupdate
$pmRow = $conn->query(
    "SELECT tagihan_bulan, new_expire FROM tblpayment_monthly WHERE id = $monthlyId LIMIT 1"
)->fetch_assoc();
$bulanLabel  = !empty($pmRow['tagihan_bulan']) ? date('M Y', strtotime($pmRow['tagihan_bulan'])) : date('M Y');
$newExpireLabel = !empty($pmRow['new_expire'])  ? date('d M Y', strtotime($pmRow['new_expire']))  : '-';

$judulClient = '✅ Bukti Pembayaran Dikirim';
$pesanClient = 'Bukti pembayaran tagihan WiFi bulan ' . $bulanLabel
             . ' untuk order ' . ($ordInfo['order_number'] ?? '')
             . ' berhasil diupload. Admin akan memverifikasi dan layanan Anda akan diperpanjang hingga '
             . $newExpireLabel . ' setelah konfirmasi.';
$tipeClient  = 'sukses';

$stNC = $conn->prepare(
    "INSERT INTO tblnotifikasi (userid, order_id, judul, pesan, tipe) VALUES (?,?,?,?,?)"
);
$stNC->bind_param('iisss', $userId, $orderId, $judulClient, $pesanClient, $tipeClient);
$stNC->execute();
$newNotifId = (int)$conn->insert_id;
$stNC->close();

// ── Notifikasi in-app ke admin (level 1 & 2) ─────────────────────────────────
$judulAdmin = '💳 Bukti Bayar Tagihan — ' . ($ordInfo['order_number'] ?? '');
$pesanAdmin = 'Client ' . ($ordInfo['client_name'] ?? '') . ' telah mengupload bukti pembayaran tagihan bulanan untuk order '
            . ($ordInfo['order_number'] ?? '') . '. Silakan periksa dan konfirmasi di halaman detail order.';
$tipeAdmin  = 'info';

$admins = $conn->query("SELECT id FROM tblclients WHERE level IN (1,2) AND status = 1");
if ($admins) {
    $stNotif = $conn->prepare(
        "INSERT INTO tblnotifikasi (userid, order_id, judul, pesan, tipe) VALUES (?,?,?,?,?)"
    );
    while ($adm = $admins->fetch_assoc()) {
        $admId = (int)$adm['id'];
        $stNotif->bind_param('iisss', $admId, $orderId, $judulAdmin, $pesanAdmin, $tipeAdmin);
        $stNotif->execute();
    }
    $stNotif->close();
}

// Kembalikan data notifikasi agar frontend bisa langsung render tanpa reload
echo json_encode([
    'ok'          => true,
    'notif'       => [
        'id'    => $newNotifId,
        'judul' => $judulClient,
        'pesan' => $pesanClient,
        'tipe'  => $tipeClient,
    ],
]);
