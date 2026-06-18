<?php
// ============================================================
// cron/cron_reminder.php
// Letakkan di: /public_html/cron/cron_reminder.php
//
// CRONTAB (cPanel → Cron Jobs):
//   0 8 1,5,10,15 * * php /home/perkasas/public_html/cron/cron_reminder.php >> /home/perkasas/logs/cron_reminder.log 2>&1
//
// LOGIKA BISNIS:
//   • Cron ini jalan setiap tanggal 1, 5, 10, dan 15 tiap bulan.
//   • Memproses order WiFi aktif yang tanggal_expire = tgl 20 bulan BERJALAN
//     dan tagihan bulan ini belum lunas (status != 'paid').
//   • Semakin dekat tgl 20, semakin sering client diingatkan (4x sebulan).
//   • Tagihan yang dibuat adalah untuk PERPANJANGAN ke bulan berikutnya:
//     bayar sebelum tgl 20 → layanan lanjut hingga tgl 20 bulan depan.
//
// LOGIKA EXPIRE:
//   • Aktivasi kapanpun di bulan X → expire selalu tgl 20 bulan X+1.
//   • Contoh: aktivasi 1–31 Juli → expire 20 Agustus.
//
// ALUR PER ORDER:
//   1. Cari order WiFi aktif yang tanggal_expire = tgl 20 bulan ini
//      dan tagihan bulan ini belum lunas
//   2. Buat baris tblpayment_monthly jika belum ada:
//        tagihan_bulan  = tgl 20 bulan ini (batas bayar)
//        due_date       = tgl 20 bulan ini
//        suspend_date   = tgl 21 bulan ini
//        new_expire     = tgl 20 bulan depan (jika bayar)
//   3. Kirim email pengingat ke client (cek sudah dikirim hari ini atau belum)
//   4. Kirim notifikasi in-app ke client & admin
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

$today     = date('Y-m-d');
$todayDay  = (int)date('j');
$thisMonth = (int)date('n');
$thisYear  = (int)date('Y');

// Validasi: cron hanya boleh jalan di tgl 1, 5, 10, 15
$allowedDays = [1, 5, 10, 15];
if (!in_array($todayDay, $allowedDays) && php_sapi_name() === 'cli') {
    echo "[INFO] Hari ini tgl $todayDay — bukan jadwal cron reminder. Keluar.\n";
    exit(0);
}

echo "[" . date('d M Y H:i:s') . "] === CRON REMINDER TAGIHAN BULANAN ===\n";
echo "    Tanggal hari ini : $today (hari ke-$todayDay)\n";

// ── Hitung tanggal_expire yang diproses bulan ini ───────────────────────────
// Expire bulan ini selalu = tgl 20 bulan berjalan
$expireBulanIni = sprintf('%04d-%02d-20', $thisYear, $thisMonth);

// Bulan & tahun untuk new_expire (tgl 20 bulan depan)
$newExpireMonth = $thisMonth + 1;
$newExpireYear  = $thisYear;
if ($newExpireMonth > 12) { $newExpireMonth = 1; $newExpireYear++; }
$newExpireDate = sprintf('%04d-%02d-20', $newExpireYear, $newExpireMonth);

// suspend_date = tgl 21 bulan berjalan
$suspendDate = sprintf('%04d-%02d-21', $thisYear, $thisMonth);

// Hitung sisa hari hingga expire
$sisaHari = (int)ceil((strtotime($expireBulanIni) - strtotime($today)) / 86400);

echo "    Expire diproses  : $expireBulanIni\n";
echo "    New expire       : $newExpireDate (jika bayar sebelum tgl 20)\n";
echo "    Suspend date     : $suspendDate\n";
echo "    Sisa hari        : $sisaHari hari\n\n";

// ── Ambil semua order WiFi aktif yang expire bulan ini & belum lunas ─────────
// Hanya kirim ke client yang BELUM bayar agar tidak spam client yang sudah lunas
$sql = "SELECT o.id AS order_id,
               o.order_number,
               o.userid,
               o.tanggal_expire,
               o.id_pelanggan,
               o.payment_status,
               p.name  AS paket_name,
               p.price AS paket_price,
               CONCAT(c.firstname, ' ', c.lastname) AS client_name,
               c.email,
               c.firstname,
               c.phonenumber,
               pm.id     AS pm_id,
               pm.status AS pm_status
        FROM   tblorders o
        JOIN   tblclients  c ON c.id  = o.userid
        JOIN   tblproducts p ON p.id  = o.productid
        LEFT JOIN tblpayment_monthly pm
               ON pm.order_id     = o.id
              AND pm.tagihan_bulan = ?
        WHERE  o.order_type    = 'wifi'
          AND  o.wifi_status   = 'active'
          AND  o.tanggal_expire = ?
          AND  o.status        != 'Cancelled'
          AND  (pm.id IS NULL OR pm.status NOT IN ('paid'))";

$stmtMain = $conn->prepare($sql);
$stmtMain->bind_param('ss', $expireBulanIni, $expireBulanIni);
$stmtMain->execute();
$result = $stmtMain->get_result();
$stmtMain->close();

if (!$result) {
    echo "[ERROR] Query gagal: " . $conn->error . "\n";
    exit(1);
}

$totalFound = $result->num_rows;
echo "  Ditemukan $totalFound order belum bayar yang expire $expireBulanIni.\n\n";

$processed = 0;
$skipped   = 0;
$errors    = 0;

while ($ord = $result->fetch_assoc()) {
    $orderId = (int)$ord['order_id'];

    echo "  → Order #{$ord['order_number']} | {$ord['client_name']} | expire: {$ord['tanggal_expire']}\n";

    // ── A. Buat baris tblpayment_monthly jika belum ada ─────────────────────
    if (!$ord['pm_id']) {
        $stmtIns = $conn->prepare(
            "INSERT INTO tblpayment_monthly
               (order_id, userid, tagihan_bulan, due_date, suspend_date, new_expire, status)
             VALUES (?, ?, ?, ?, ?, ?, 'unpaid')"
        );
        $stmtIns->bind_param(
            'iissss',
            $orderId, $ord['userid'],
            $expireBulanIni,  // tagihan_bulan = tgl 20 bulan ini
            $expireBulanIni,  // due_date      = tgl 20 bulan ini
            $suspendDate,     // suspend_date  = tgl 21 bulan ini
            $newExpireDate    // new_expire    = tgl 20 bulan depan
        );
        $stmtIns->execute();
        $stmtIns->close();
        echo "    [DB] Tagihan baru dibuat → tagihan=$expireBulanIni | suspend=$suspendDate | new_expire=$newExpireDate\n";
    } else {
        echo "    [INFO] Baris tagihan sudah ada, status={$ord['pm_status']}.\n";
    }

    // ── B. Cek apakah email reminder sudah dikirim HARI INI ─────────────────
    // Cek per hari agar tidak double-send jika cron dijalankan manual
    $chkSent = $conn->prepare(
        "SELECT id FROM tblpayment_monthly
         WHERE order_id = ? AND tagihan_bulan = ? AND DATE(reminder_sent_at) = ? LIMIT 1"
    );
    $chkSent->bind_param('iss', $orderId, $expireBulanIni, $today);
    $chkSent->execute();
    $alreadySentToday = $chkSent->get_result()->num_rows > 0;
    $chkSent->close();

    if ($alreadySentToday) {
        echo "    [SKIP] Email reminder sudah dikirim hari ini ($today).\n";
        $skipped++;
        continue;
    }

    // ── C. Kirim email reminder ke client ───────────────────────────────────
    $emailSent = perkasa_send_mail(
        $ord['email'],
        $ord['client_name'],
        '⚠️ Pengingat Tagihan WiFi – Bayar Sebelum ' . date('d M Y', strtotime($expireBulanIni)),
        render_email_pengingat_tagihan([
            'client_name'    => $ord['client_name'],
            'order_number'   => $ord['order_number'],
            'paket_name'     => $ord['paket_name'],
            'paket_price'    => $ord['paket_price'],
            'id_pelanggan'   => $ord['id_pelanggan'],
            'tanggal_expire' => $expireBulanIni,
            'new_expire'     => $newExpireDate,
            'suspend_date'   => $suspendDate,
            'sisa_hari'      => $sisaHari,
        ])
    );

    if ($emailSent) {
        // Update reminder_sent_at di tblorders
        $conn->query(
            "UPDATE tblorders
             SET reminder_sent_at = '$today'
             WHERE id = $orderId LIMIT 1"
        );
        // Update reminder_sent_at di tblpayment_monthly
        $conn->query(
            "UPDATE tblpayment_monthly
             SET reminder_sent_at = NOW()
             WHERE order_id = $orderId AND tagihan_bulan = '$expireBulanIni' LIMIT 1"
        );
        echo "    [OK] Email reminder terkirim ke {$ord['email']}.\n";
        $processed++;
    } else {
        echo "    [ERROR] Gagal kirim email ke {$ord['email']}.\n";
        $errors++;
    }

    // ── D. Notifikasi in-app → CLIENT ────────────────────────────────────────
    $uid    = (int)$ord['userid'];
    $jClient = "⚠️ Tagihan WiFi Jatuh Tempo " . date('d M Y', strtotime($expireBulanIni));
    $pClient = "Tagihan WiFi paket {$ord['paket_name']} untuk order {$ord['order_number']} "
             . "jatuh tempo pada " . date('d M Y', strtotime($expireBulanIni)) . ". "
             . "Bayar sebelum tanggal tersebut agar layanan diperpanjang hingga "
             . date('d M Y', strtotime($newExpireDate)) . ". "
             . "Jika belum dibayar hingga " . date('d M Y', strtotime($suspendDate)) . ", layanan WiFi akan dinonaktifkan otomatis.";

    $stmtNC = $conn->prepare(
        "INSERT INTO tblnotifikasi (userid, order_id, judul, pesan, tipe) VALUES (?,?,?,?,?)"
    );
    $tClient = 'peringatan';
    $stmtNC->bind_param('iisss', $uid, $orderId, $jClient, $pClient, $tClient);
    $stmtNC->execute();
    $stmtNC->close();
    echo "    [OK] Notifikasi in-app dikirim ke client #$uid.\n";

    // ── E. Notifikasi in-app → ADMIN (level 1 & 2) ───────────────────────────
    $jAdmin = "📋 Tagihan Belum Bayar – {$ord['client_name']} ({$ord['order_number']})";
    $pAdmin = "Tagihan WiFi {$ord['client_name']} untuk order {$ord['order_number']} "
            . "jatuh tempo " . date('d M Y', strtotime($expireBulanIni)) . ". "
            . "Jika tidak dibayar, layanan akan disuspend otomatis pada " . date('d M Y', strtotime($suspendDate)) . ". "
            . "Pantau di halaman detail order.";
    $tAdmin = 'info';

    $admins = $conn->query(
        "SELECT id FROM tblclients WHERE level IN (1,2) AND status = 1"
    );
    if ($admins) {
        $stmtNA = $conn->prepare(
            "INSERT INTO tblnotifikasi (userid, order_id, judul, pesan, tipe) VALUES (?,?,?,?,?)"
        );
        while ($adm = $admins->fetch_assoc()) {
            $admId = (int)$adm['id'];
            $stmtNA->bind_param('iisss', $admId, $orderId, $jAdmin, $pAdmin, $tAdmin);
            $stmtNA->execute();
        }
        $stmtNA->close();
        echo "    [OK] Notifikasi admin dikirim.\n";
    }

    echo "\n";
}

echo "[SELESAI] Diproses: $processed | Dilewati: $skipped | Error: $errors\n";
echo "[" . date('d M Y H:i:s') . "] === CRON REMINDER SELESAI ===\n";
