<?php
// ============================================================
// cron/cron_hosting_expired.php
// Letakkan di: /public_html/cron/cron_hosting_expired.php
//
// CRONTAB (cPanel → Cron Jobs):
//   */15 * * * * php /home/perkasas/public_html/cron/cron_hosting_expired.php >> /home/perkasas/logs/cron_hosting_expired.log 2>&1
//   (jalan tiap 15 menit agar penghapusan cukup presisi terhadap batas 24 jam)
//
// LOGIKA BISNIS:
//   • Saat client order hosting, payment_deadline = created_at + 24 jam
//     (diset di order/process_order_hosting.php).
//   • Cron ini mencari order hosting yang:
//       - order_type = 'hosting'
//       - payment_status != 'lunas'   (belum dikonfirmasi admin)
//       - payment_deadline IS NOT NULL DAN payment_deadline <= NOW()
//   • Order yang ditemukan akan DIHAPUS PERMANEN beserta baris tblhosting
//     terkait (akun DA belum pernah dibuat di tahap ini, jadi aman dihapus).
//   • Setiap eksekusi cron — baik ada yang dihapus atau tidak — DICATAT
//     ke tbl_cron_logs supaya admin & owner bisa audit kapan cron jalan
//     dan apa saja yang terjadi.
//   • Sebelum dihapus, client dikirim notifikasi in-app + email bahwa
//     order dibatalkan otomatis karena tidak ada pembayaran dalam 24 jam.
//   • Admin & Owner (level 1,2) mendapat notifikasi in-app ringkasan.
// ============================================================

if (php_sapi_name() !== 'cli') {
    $token = $_GET['cron_token'] ?? '';
    if ($token !== 'perkasa_cron_2025_s3cr3t') {
        http_response_code(403);
        die('Forbidden');
    }
}

define('CRON_RUNNING', true);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../mailer.php';

$now      = date('Y-m-d H:i:s');
$cronName = 'cron_hosting_expired';

echo "[" . date('d M Y H:i:s') . "] === CRON HOSTING EXPIRED (auto-cancel order belum bayar > 24 jam) ===\n";
echo "    Waktu sekarang : $now\n\n";

// ── Ambil order hosting yang sudah lewat deadline & belum lunas ────────────
$sql = "SELECT o.id            AS order_id,
               o.order_number,
               o.userid,
               o.productid,
               o.payment_status,
               o.payment_deadline,
               o.created_at    AS order_created_at,
               h.id            AS hosting_id,
               h.domain,
               p.name          AS paket_name,
               CONCAT(c.firstname, ' ', c.lastname) AS client_name,
               c.email,
               c.firstname
        FROM   tblorders o
        JOIN   tblclients  c ON c.id = o.userid
        JOIN   tblproducts p ON p.id = o.productid
        LEFT JOIN tblhosting h ON h.userid = o.userid AND h.packageid = o.productid
        WHERE  o.order_type        = 'hosting'
          AND  o.payment_status   != 'lunas'
          AND  o.payment_deadline IS NOT NULL
          AND  o.payment_deadline <= ?";

$stmtMain = $conn->prepare($sql);
$stmtMain->bind_param('s', $now);
$stmtMain->execute();
$result = $stmtMain->get_result();
$stmtMain->close();

if (!$result) {
    echo "[ERROR] Query gagal: " . $conn->error . "\n";
    // Tetap catat log error agar admin tahu cron gagal jalan
    $detailErr = json_encode(['error' => $conn->error]);
    $logErr = $conn->prepare("INSERT INTO tbl_cron_logs (cron_name, run_at, total_found, total_deleted, total_errors, detail) VALUES (?, ?, 0, 0, 1, ?)");
    $logErr->bind_param('sss', $cronName, $now, $detailErr);
    $logErr->execute();
    $logErr->close();
    exit(1);
}

$totalFound   = $result->num_rows;
echo "  Ditemukan $totalFound order hosting yang melewati batas 24 jam tanpa pembayaran.\n\n";

$deleted    = 0;
$errors     = 0;
$detailList = [];

while ($row = $result->fetch_assoc()) {
    $orderId     = (int)$row['order_id'];
    $hostingId   = !empty($row['hosting_id']) ? (int)$row['hosting_id'] : null;
    $userId      = (int)$row['userid'];
    $orderNumber = $row['order_number'];
    $clientName  = $row['client_name'];
    $clientEmail = $row['email'];
    $domain      = $row['domain'] ?? '-';
    $paketName   = $row['paket_name'];

    echo "  → Order #{$orderNumber} | {$clientName} | domain: {$domain} | deadline: {$row['payment_deadline']}\n";

    $conn->begin_transaction();
    try {
        // ── 1. Hapus baris tblhosting terkait (jika ada) ──
        if ($hostingId) {
            $conn->query("DELETE FROM tblhosting WHERE id = {$hostingId} LIMIT 1");
            echo "    [DB] tblhosting#$hostingId dihapus.\n";
        }

        // ── 2. Tandai invoice terkait sebagai Cancelled (bukan dihapus, agar tetap
        //      jadi jejak audit keuangan meski order induknya sudah tidak ada) ──
        $conn->query("UPDATE tblinvoices SET status = 'Cancelled' WHERE order_id = {$orderId} AND status != 'Paid'");
        echo "    [DB] Invoice terkait order#$orderId ditandai Cancelled.\n";

        // ── 3. Log status sebelum order dihapus (riwayat tetap ada walau order hilang) ──
        $catatanLog = "Order hosting dibatalkan & dihapus otomatis oleh sistem karena tidak ada "
                    . "konfirmasi pembayaran dalam 24 jam (deadline: "
                    . date('d M Y H:i', strtotime($row['payment_deadline'])) . ").";
        $stLog = $conn->prepare("
            INSERT INTO tblorder_status_logs (order_id, old_status, new_status, changed_by, role, catatan, created_at)
            VALUES (?, ?, 'cancelled', 0, 'system', ?, NOW())
        ");
        $oldStatus = $row['payment_status'];
        $stLog->bind_param('iss', $orderId, $oldStatus, $catatanLog);
        $stLog->execute();
        $stLog->close();
        echo "    [DB] Log status perubahan dicatat (system).\n";

        // ── 4. Hapus order ──
        $conn->query("DELETE FROM tblorders WHERE id = {$orderId} LIMIT 1");
        echo "    [DB] tblorders#$orderId dihapus.\n";

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        error_log('[cron_hosting_expired] Gagal hapus order #' . $orderNumber . ': ' . $e->getMessage());
        echo "    [ERROR] Gagal memproses order #$orderNumber: " . $e->getMessage() . "\n\n";
        $errors++;
        $detailList[] = [
            'order_number' => $orderNumber,
            'client_name'  => $clientName,
            'domain'       => $domain,
            'status'       => 'error',
            'message'      => $e->getMessage(),
        ];
        continue;
    }

    // ── 4. Notifikasi in-app ke client ──
    // NB: order sudah dihapus, jadi order_id pada notifikasi diisi NULL agar tidak FK ke order yang sudah tidak ada.
    $judulC = '⛔ Order Hosting Dibatalkan Otomatis';
    $pesanC = "Order hosting Anda (#{$orderNumber} - {$paketName}) telah dibatalkan otomatis oleh sistem "
            . "karena tidak ada konfirmasi pembayaran dalam 24 jam sejak order dibuat. "
            . "Jika Anda masih ingin berlangganan, silakan lakukan order kembali.";
    $stNC = $conn->prepare("INSERT INTO tblnotifikasi (userid, order_id, judul, pesan, tipe) VALUES (?, NULL, ?, ?, 'error')");
    $stNC->bind_param('iss', $userId, $judulC, $pesanC);
    $stNC->execute();
    $stNC->close();
    echo "    [OK] Notifikasi in-app dikirim ke client #$userId.\n";

    // ── 5. Email ke client ──
    $emailSent = false;
    if ($clientEmail) {
        $emailSent = perkasa_send_mail(
            $clientEmail,
            $clientName,
            "⛔ Order Hosting #{$orderNumber} Dibatalkan Otomatis — Perkasa Solusindo",
            render_email_hosting_order_cancelled([
                'client_name'  => $clientName,
                'order_number' => $orderNumber,
                'paket_name'   => $paketName,
                'domain'       => $domain,
            ])
        );
    }
    echo $emailSent ? "    [OK] Email pembatalan terkirim ke {$clientEmail}.\n" : "    [WARN] Email pembatalan tidak terkirim / email kosong.\n";

    // ── 6. Notifikasi in-app ke Admin & Owner (level 1,2) ──
    $judulA = "🗑️ Order Hosting Dihapus Otomatis — {$orderNumber}";
    $pesanA = "Order hosting {$clientName} ({$orderNumber}, domain: {$domain}) telah dihapus otomatis oleh sistem "
            . "karena melewati batas 24 jam tanpa konfirmasi pembayaran.";
    $admins = $conn->query("SELECT id FROM tblclients WHERE level IN (1,2) AND status = 1");
    if ($admins) {
        $stNA = $conn->prepare("INSERT INTO tblnotifikasi (userid, order_id, judul, pesan, tipe) VALUES (?, NULL, ?, ?, 'error')");
        while ($adm = $admins->fetch_assoc()) {
            $admId = (int)$adm['id'];
            $stNA->bind_param('iss', $admId, $judulA, $pesanA);
            $stNA->execute();
        }
        $stNA->close();
        echo "    [OK] Notifikasi admin/owner dikirim.\n";
    }

    $deleted++;
    $detailList[] = [
        'order_number' => $orderNumber,
        'client_name'  => $clientName,
        'domain'       => $domain,
        'status'       => 'deleted',
        'deadline'     => $row['payment_deadline'],
    ];
    echo "\n";
}

// ── 7. Catat ringkasan eksekusi cron ke tbl_cron_logs (selalu, walau 0 hasil) ──
$detailJson = json_encode($detailList, JSON_UNESCAPED_UNICODE);
$logIns = $conn->prepare("
    INSERT INTO tbl_cron_logs (cron_name, run_at, total_found, total_deleted, total_errors, detail)
    VALUES (?, ?, ?, ?, ?, ?)
");
$logIns->bind_param('ssiiis', $cronName, $now, $totalFound, $deleted, $errors, $detailJson);
$logIns->execute();
$logIns->close();

// ── 8. Jika ada yang dihapus, kirim 1 notifikasi ringkasan ke admin/owner ──
if ($deleted > 0) {
    $judulSum = "📋 Ringkasan Cron Hosting Expired — {$deleted} order dihapus";
    $pesanSum = "Cron otomatis baru saja menghapus {$deleted} order hosting yang melewati batas 24 jam tanpa "
              . "pembayaran (waktu eksekusi: " . date('d M Y H:i', strtotime($now)) . "). "
              . ($errors > 0 ? "Terdapat {$errors} error saat proses, cek log server." : "Semua berhasil diproses tanpa error.");
    $adminsSum = $conn->query("SELECT id FROM tblclients WHERE level IN (1,2) AND status = 1");
    if ($adminsSum) {
        $stSum = $conn->prepare("INSERT INTO tblnotifikasi (userid, order_id, judul, pesan, tipe) VALUES (?, NULL, ?, ?, 'info')");
        while ($adm = $adminsSum->fetch_assoc()) {
            $admId = (int)$adm['id'];
            $stSum->bind_param('iss', $admId, $judulSum, $pesanSum);
            $stSum->execute();
        }
        $stSum->close();
    }
}

echo "[SELESAI] Ditemukan: $totalFound | Dihapus: $deleted | Error: $errors\n";
echo "[" . date('d M Y H:i:s') . "] === CRON HOSTING EXPIRED SELESAI ===\n";
