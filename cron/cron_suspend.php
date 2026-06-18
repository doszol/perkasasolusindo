<?php
// ============================================================
// cron/cron_suspend.php
// Letakkan di: /public_html/cron/cron_suspend.php
//
// CRONTAB (cPanel → Cron Jobs):
//   0 0 21 * * php /home/perkasas/public_html/cron/cron_suspend.php >> /home/perkasas/logs/cron_suspend.log 2>&1
//
// LOGIKA BISNIS:
//   • Cron ini jalan setiap tanggal 21 tepat tengah malam (00:00).
//   • Cek tblpayment_monthly di mana suspend_date = hari ini (tgl 21)
//     dan status masih 'unpaid' atau 'waiting_confirm'.
//   • Jika ditemukan → suspend order:
//       - tblorders: wifi_status = 'cancelled', status = 'Suspended'
//       - tblpayment_monthly: status = 'suspended'
//       - tblorder_status_logs: catat perubahan oleh system
//       - Kirim email pemberitahuan ke client
//       - Notifikasi in-app ke client & admin
//
// LOGIKA EXPIRE:
//   • Aktivasi kapanpun di bulan X → expire selalu tgl 20 bulan X+1.
//   • Jika tgl 20 tengah malam belum ada pembayaran → suspend tgl 21 pagi.
//   • Jika client membayar setelah suspend, admin konfirmasi manual →
//     order direaktivasi dan tanggal_expire diupdate ke new_expire
//     yang tersimpan di tblpayment_monthly.
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

$today    = date('Y-m-d');
$todayDay = (int)date('j');
$systemId = 0;

// Validasi: cron hanya boleh jalan di tgl 21
if ($todayDay !== 21 && php_sapi_name() === 'cli') {
    echo "[INFO] Hari ini tgl $todayDay — bukan jadwal cron suspend (tgl 21). Keluar.\n";
    exit(0);
}

echo "[" . date('d M Y H:i:s') . "] === CRON SUSPEND LAYANAN ===\n";
echo "    Tanggal hari ini : $today\n\n";

// ── Ambil tagihan yang suspend_date = hari ini & belum bayar ────────────────
$sql = "SELECT pm.id              AS pm_id,
               pm.order_id,
               pm.userid,
               pm.tagihan_bulan,
               pm.due_date,
               pm.suspend_date,
               pm.new_expire,
               pm.status          AS pm_status,
               o.order_number,
               o.tanggal_expire,
               o.wifi_status,
               o.id_pelanggan,
               p.name             AS paket_name,
               p.price            AS paket_price,
               CONCAT(c.firstname, ' ', c.lastname) AS client_name,
               c.email,
               c.firstname
        FROM   tblpayment_monthly pm
        JOIN   tblorders   o ON o.id  = pm.order_id
        JOIN   tblclients  c ON c.id  = pm.userid
        JOIN   tblproducts p ON p.id  = o.productid
        WHERE  pm.suspend_date = ?
          AND  pm.status       IN ('unpaid', 'waiting_confirm')
          AND  o.wifi_status   = 'active'
          AND  o.order_type    = 'wifi'";

$stmtMain = $conn->prepare($sql);
$stmtMain->bind_param('s', $today);
$stmtMain->execute();
$result = $stmtMain->get_result();
$stmtMain->close();

if (!$result) {
    echo "[ERROR] Query gagal: " . $conn->error . "\n";
    exit(1);
}

$totalFound = $result->num_rows;
echo "  Ditemukan $totalFound order yang akan disuspend hari ini.\n\n";

$suspended = 0;
$errors    = 0;

while ($row = $result->fetch_assoc()) {
    $orderId   = (int)$row['order_id'];
    $pmId      = (int)$row['pm_id'];
    $oldStatus = $row['wifi_status'];

    echo "  → Order #{$row['order_number']} | {$row['client_name']} | tagihan: {$row['tagihan_bulan']}\n";
    echo "    Status tagihan : {$row['pm_status']}\n";

    // ── 1. Suspend order di tblorders ────────────────────────────────────────
    $conn->query(
        "UPDATE tblorders
         SET wifi_status = 'cancelled',
             status      = 'Suspended'
         WHERE id = $orderId LIMIT 1"
    );
    echo "    [DB] tblorders → wifi_status='cancelled', status='Suspended'\n";

    // ── 2. Update tblpayment_monthly → suspended ─────────────────────────────
    $conn->query(
        "UPDATE tblpayment_monthly
         SET status = 'suspended'
         WHERE id = $pmId LIMIT 1"
    );
    echo "    [DB] tblpayment_monthly#$pmId → status='suspended'\n";

    // ── 3. Log ke tblorder_status_logs (role=system) ─────────────────────────
    $bulanTagihan = date('d M Y', strtotime($row['tagihan_bulan']));
    $catatanLog   = "Layanan dinonaktifkan otomatis oleh sistem karena tagihan "
                  . $bulanTagihan
                  . " (jatuh tempo " . date('d M Y', strtotime($row['due_date'])) . ")"
                  . " belum dibayar hingga batas suspend (" . date('d M Y', strtotime($today)) . ").";

    $stmtLog = $conn->prepare(
        "INSERT INTO tblorder_status_logs
           (order_id, old_status, new_status, changed_by, role, catatan)
         VALUES (?, ?, 'cancelled', ?, 'system', ?)"
    );
    $stmtLog->bind_param('isis', $orderId, $oldStatus, $systemId, $catatanLog);
    $stmtLog->execute();
    $stmtLog->close();
    echo "    [DB] Log status perubahan dicatat (system).\n";

    // ── 4. Kirim email suspend ke client ─────────────────────────────────────
    $emailSent = perkasa_send_mail(
        $row['email'],
        $row['client_name'],
        '❌ Layanan WiFi Anda Telah Dinonaktifkan – ' . $row['order_number'],
        render_email_suspend_wifi([
            'client_name'    => $row['client_name'],
            'order_number'   => $row['order_number'],
            'paket_name'     => $row['paket_name'],
            'paket_price'    => $row['paket_price'],
            'id_pelanggan'   => $row['id_pelanggan'],
            'tagihan_bulan'  => $row['tagihan_bulan'],
            'due_date'       => $row['due_date'],
            'suspend_date'   => $today,
            'new_expire'     => $row['new_expire'],
        ])
    );

    if ($emailSent) {
        echo "    [OK] Email suspend terkirim ke {$row['email']}.\n";
    } else {
        echo "    [ERROR] Gagal kirim email suspend ke {$row['email']}.\n";
        $errors++;
    }

    // ── 5. Notifikasi in-app → CLIENT ────────────────────────────────────────
    $uid  = (int)$row['userid'];
    $jC   = '❌ Layanan WiFi Dinonaktifkan';
    $bulanLabel = date('M Y', strtotime($row['tagihan_bulan']));
    $pC   = "Layanan WiFi Anda ({$row['order_number']}) telah dinonaktifkan otomatis "
          . "karena tagihan bulan $bulanLabel belum dibayar hingga tgl 20. "
          . "Segera hubungi admin Perkasa Solusindo atau upload bukti pembayaran untuk reaktivasi. "
          . "Layanan dapat diaktifkan kembali hingga " . date('d M Y', strtotime($row['new_expire'])) . " jika segera dibayar.";
    $tC   = 'error';

    $stmtNC = $conn->prepare(
        "INSERT INTO tblnotifikasi (userid, order_id, judul, pesan, tipe) VALUES (?,?,?,?,?)"
    );
    $stmtNC->bind_param('iisss', $uid, $orderId, $jC, $pC, $tC);
    $stmtNC->execute();
    $stmtNC->close();
    echo "    [OK] Notifikasi in-app dikirim ke client #$uid.\n";

    // ── 6. Notifikasi in-app → ADMIN (level 1 & 2) ───────────────────────────
    $jA = "🚫 Layanan Disuspend – {$row['client_name']} ({$row['order_number']})";
    $pA = "Layanan WiFi {$row['client_name']} untuk order {$row['order_number']} "
        . "telah dinonaktifkan otomatis pada $today "
        . "karena tagihan bulan $bulanLabel belum dibayar hingga tgl 20. "
        . "Tunggu client upload bukti bayar, lalu konfirmasi untuk reaktivasi.";
    $tA = 'error';

    $admins = $conn->query(
        "SELECT id FROM tblclients WHERE level IN (1,2) AND status = 1"
    );
    if ($admins) {
        $stmtNA = $conn->prepare(
            "INSERT INTO tblnotifikasi (userid, order_id, judul, pesan, tipe) VALUES (?,?,?,?,?)"
        );
        while ($adm = $admins->fetch_assoc()) {
            $admId = (int)$adm['id'];
            $stmtNA->bind_param('iisss', $admId, $orderId, $jA, $pA, $tA);
            $stmtNA->execute();
        }
        $stmtNA->close();
        echo "    [OK] Notifikasi admin dikirim.\n";
    }

    $suspended++;
    echo "\n";
}

echo "[SELESAI] Disuspend: $suspended | Error: $errors\n";
echo "[" . date('d M Y H:i:s') . "] === CRON SUSPEND SELESAI ===\n";
