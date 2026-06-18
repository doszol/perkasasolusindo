<?php
// ============================================================
// admin/order_detail.php – Detail & Aksi Order Perkasa Solusindo
// Mendukung semua jenis layanan: wifi, hosting, website, cctv, komputer
// ============================================================
require_once '../config.php';
require_once '../auth_check.php';
requireLevel([1, 2]);

$adminId   = (int)($_SESSION['user_id'] ?? 0);
$adminName = $_SESSION['user_firstname'] ?? 'Admin';

// ── Validate order ────────────────────────────────────────────
$orderId = (int)($_GET['id'] ?? 0);
if (!$orderId) { header('Location: /admin/orders.php'); exit; }

// ── Statistik sidebar ─────────────────────────────────────────
function cntQ($conn, $sql) { $r=$conn->query($sql); return $r?(int)$r->fetch_row()[0]:0; }
$stats['unpaid']        = cntQ($conn,"SELECT COUNT(*) FROM tblinvoices WHERE status='Unpaid'");
$stats['tickets']       = cntQ($conn,"SELECT COUNT(*) FROM tbltickets WHERE status='Open'");
$totalOrdersPending     = cntQ($conn,"SELECT COUNT(*) FROM tblorders WHERE wifi_status IN ('pending','verified','scheduled')");

// ── AJAX / POST actions ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');

    // ── Update status order ────────────────────────────────────
    if ($_POST['ajax_action'] === 'update_order') {
        $newStatus   = $_POST['wifi_status']      ?? '';
        $jadwal      = $_POST['jadwal_instalasi'] ?? '';
        $catatan     = $_POST['catatan']          ?? '';
        $teknisiId   = (int)($_POST['teknisi_id']   ?? 0);
        $teknisiId2  = (int)($_POST['teknisi_id_2'] ?? 0);
        $payStatus   = $_POST['payment_status']   ?? '';

        $allowed = ['pending','verified','scheduled','installed','active','cancelled'];
        if (!in_array($newStatus, $allowed)) {
            echo json_encode(['ok'=>false,'msg'=>'Status tidak valid.']); exit;
        }

        if ($teknisiId && $teknisiId2 && $teknisiId === $teknisiId2) {
            echo json_encode(['ok'=>false,'msg'=>'Teknisi 1 dan Teknisi 2 tidak boleh sama.']); exit;
        }

        $old = $conn->query("SELECT wifi_status, userid, order_type, teknisi_id, teknisi_id_2, order_number FROM tblorders WHERE id=$orderId")->fetch_assoc();
        if (!$old) { echo json_encode(['ok'=>false,'msg'=>'Order tidak ditemukan.']); exit; }

        $oldStatus     = $old['wifi_status'];
        $clientId      = (int)$old['userid'];
        $orderType     = $old['order_type'];
        // Fallback ke nilai DB jika admin tidak mengubah teknisi di form
        $notifTeknisi1 = $teknisiId  ?: (int)$old['teknisi_id'];
        $notifTeknisi2 = $teknisiId2 ?: (int)$old['teknisi_id_2'];

        $setClauses = ["wifi_status=?"];
        $types = 's'; $params = [$newStatus];

        if ($jadwal)     { $setClauses[]="jadwal_instalasi=?"; $types.='s'; $params[]=$jadwal; }
        if ($teknisiId)  { $setClauses[]="teknisi_id=?";       $types.='i'; $params[]=$teknisiId; }
        if ($teknisiId2) { $setClauses[]="teknisi_id_2=?";     $types.='i'; $params[]=$teknisiId2; }
        if ($catatan!=='') { $setClauses[]="note=?";           $types.='s'; $params[]=$catatan; }
        if ($payStatus)  { $setClauses[]="payment_status=?";   $types.='s'; $params[]=$payStatus; }
        if ($newStatus === 'active') { $setClauses[]="tgl_aktif=CURDATE()"; }

        $setStr = implode(', ', $setClauses);
        $stmt = $conn->prepare("UPDATE tblorders SET $setStr WHERE id=?");
        $types .= 'i'; $params[] = $orderId;
        $stmt->bind_param($types, ...$params);
        $stmt->execute(); $stmt->close();

        // Log perubahan
        $role = 'admin';
        $stmt2 = $conn->prepare("INSERT INTO tblorder_status_logs (order_id,old_status,new_status,changed_by,role,catatan) VALUES (?,?,?,?,?,?)");
        $stmt2->bind_param('ississ', $orderId, $oldStatus, $newStatus, $adminId, $role, $catatan);
        $stmt2->execute(); $stmt2->close();

        // Notifikasi klien
        $labelMap = [
            'verified'  => ['Pesanan Anda Diverifikasi ✅','Admin telah memverifikasi pesanan. Tim akan segera menghubungi Anda.','sukses'],
            'scheduled' => ['Jadwal Instalasi Ditetapkan 📅','Jadwal pemasangan sudah ditetapkan. Cek detail di dashboard.','info'],
            'installed' => ['Instalasi Selesai 🎉','Pemasangan layanan Anda telah berhasil diselesaikan.','sukses'],
            'active'    => ['Layanan Aktif 🚀','Layanan Anda sudah aktif. Selamat menikmati!','sukses'],
            'cancelled' => ['Pesanan Dibatalkan ❌','Pesanan Anda telah dibatalkan. Hubungi kami jika ada pertanyaan.','error'],
        ];
        if (isset($labelMap[$newStatus])) {
            [$j,$p,$t] = $labelMap[$newStatus];
            $stmt3 = $conn->prepare("INSERT INTO tblnotifikasi (userid,order_id,judul,pesan,tipe) VALUES (?,?,?,?,?)");
            $stmt3->bind_param('iisss', $clientId, $orderId, $j, $p, $t);
            $stmt3->execute(); $stmt3->close();
        }

        // Notifikasi teknisi 1 & 2
        $labelMapTeknisi = [
            'scheduled' => ['Anda Mendapat Tugas Instalasi 📅', "Order #{$old['order_number']} telah dijadwalkan. Cek detail order untuk info jadwal dan lokasi.", 'info'],
            'installed' => ['Order Ditandai Terpasang 🔧',      "Order #{$old['order_number']} telah ditandai selesai dipasang oleh admin.", 'sukses'],
            'active'    => ['Order Aktif ✅',                    "Order #{$old['order_number']} kini berstatus Aktif.", 'sukses'],
            'cancelled' => ['Order Dibatalkan ❌',               "Order #{$old['order_number']} telah dibatalkan oleh admin.", 'error'],
        ];
        if (isset($labelMapTeknisi[$newStatus])) {
            [$jt, $pt, $tt] = $labelMapTeknisi[$newStatus];
            $stmtTek = $conn->prepare("INSERT INTO tblnotifikasi (userid,order_id,judul,pesan,tipe) VALUES (?,?,?,?,?)");
            if ($notifTeknisi1) {
                $stmtTek->bind_param('iisss', $notifTeknisi1, $orderId, $jt, $pt, $tt);
                $stmtTek->execute();
            }
            if ($notifTeknisi2 && $notifTeknisi2 !== $notifTeknisi1) {
                $stmtTek->bind_param('iisss', $notifTeknisi2, $orderId, $jt, $pt, $tt);
                $stmtTek->execute();
            }
            $stmtTek->close();
        }

        // Jika hosting diaktifkan → simpan ke tblhosting
        if ($orderType === 'hosting' && $newStatus === 'active') {
            $domain  = $_POST['hosting_domain'] ?? '';
            $duedate = $_POST['hosting_duedate'] ?? null;
            $pkgId   = (int)$_POST['hosting_pkgid'];

            if ($domain) {
                // Cek apakah sudah ada entry untuk order ini
                $chk = $conn->query("SELECT id FROM tblhosting WHERE userid=$clientId AND packageid=$pkgId LIMIT 1");
                if ($chk && $chk->num_rows === 0) {
                    $st4 = $conn->prepare("INSERT INTO tblhosting (userid, packageid, domain, domainstatus, nextduedate) VALUES (?,?,?,?,?)");
                    $st4->bind_param('iisss', $clientId, $pkgId, $domain, $newStatus === 'active' ? 'Active' : 'Pending', $duedate);
                    $st4->execute(); $st4->close();
                } else {
                    // Update existing
                    $conn->query("UPDATE tblhosting SET domainstatus='Active', nextduedate=".($duedate?"'$duedate'":'NULL')." WHERE userid=$clientId AND packageid=$pkgId LIMIT 1");
                }
            }
        }

        echo json_encode(['ok'=>true,'new_status'=>$newStatus]); exit;
    }

    // ── Konfirmasi bukti bayar + tandai invoice Paid ──────────
    if ($_POST['ajax_action'] === 'confirm_payment') {
        $invoiceId = (int)($_POST['invoice_id'] ?? 0);
        if (!$invoiceId) { echo json_encode(['ok'=>false,'msg'=>'Invoice ID tidak valid.']); exit; }

        // Update invoice → Paid
        $conn->query("UPDATE tblinvoices SET status='Paid', datepaid=NOW() WHERE id=$invoiceId AND userid=(SELECT userid FROM tblorders WHERE id=$orderId) LIMIT 1");
        // Update payment_status order → lunas
        $conn->query("UPDATE tblorders SET payment_status='lunas' WHERE id=$orderId LIMIT 1");

        // Notifikasi klien
        $ord2 = $conn->query("SELECT userid, order_number FROM tblorders WHERE id=$orderId")->fetch_assoc();
        if ($ord2) {
            $cid = (int)$ord2['userid'];
            $onum = $ord2['order_number'];
            $j = "Pembayaran Dikonfirmasi ✅";
            $p = "Pembayaran untuk order $onum telah dikonfirmasi. Layanan Anda akan segera diproses.";
            $t = 'sukses';
            $stn = $conn->prepare("INSERT INTO tblnotifikasi (userid,order_id,judul,pesan,tipe) VALUES (?,?,?,?,?)");
            $stn->bind_param('iisss', $cid, $orderId, $j, $p, $t);
            $stn->execute(); $stn->close();
        }

        echo json_encode(['ok'=>true]); exit;
    }

    // ── Konfirmasi pembayaran bulanan + perpanjang layanan ─────
    if ($_POST['ajax_action'] === 'confirm_monthly_payment') {
        $monthlyId = (int)($_POST['monthly_id'] ?? 0);
        if (!$monthlyId) { echo json_encode(['ok'=>false,'msg'=>'ID tagihan tidak valid.']); exit; }

        // Ambil data tagihan bulanan
        $pm = $conn->query("SELECT * FROM tblpayment_monthly WHERE id=$monthlyId AND order_id=$orderId LIMIT 1")->fetch_assoc();
        if (!$pm) { echo json_encode(['ok'=>false,'msg'=>'Data tagihan tidak ditemukan.']); exit; }
        if ($pm['status'] === 'paid') { echo json_encode(['ok'=>false,'msg'=>'Tagihan ini sudah dikonfirmasi sebelumnya.']); exit; }

        // Ambil data order terkait
        $ordRow = $conn->query("SELECT wifi_status, userid, order_number FROM tblorders WHERE id=$orderId LIMIT 1")->fetch_assoc();

        $oldExpire   = $pm['tagihan_bulan']; // tgl 20 bulan tagihan (batas bayar)
        $oldWifiStat = $ordRow['wifi_status'];

        // Gunakan new_expire dari DB jika sudah diisi cron, fallback hitung manual
        if (!empty($pm['new_expire'])) {
            $newExpire = $pm['new_expire']; // sudah diisi oleh cron_reminder (tgl 20 bulan depan)
        } else {
            $expTs     = strtotime($oldExpire . ' +1 month');
            $newExpire = date('Y-m', $expTs) . '-20';
        }

        // Apakah order sedang disuspend karena belum bayar?
        $wasSuspended = ($oldWifiStat === 'cancelled' && $pm['status'] === 'suspended');

        // 1. Update tblpayment_monthly → paid
        $stmtPM = $conn->prepare(
            "UPDATE tblpayment_monthly
             SET status='paid', confirmed_at=NOW(), confirmed_by=?, new_expire=?
             WHERE id=?"
        );
        $stmtPM->bind_param('isi', $adminId, $newExpire, $monthlyId);
        $stmtPM->execute(); $stmtPM->close();

        // 2. Update tblorders: perpanjang expire + reset reminder + reaktivasi jika suspended
        if ($wasSuspended) {
            // Aktifkan kembali layanan yang disuspend
            $stmtOrd = $conn->prepare(
                "UPDATE tblorders
                 SET tanggal_expire=?, wifi_status='active', status='Active',
                     payment_status='lunas', reminder_sent_at=NULL
                 WHERE id=? LIMIT 1"
            );
        } else {
            // Order masih aktif — perpanjang expire saja
            $stmtOrd = $conn->prepare(
                "UPDATE tblorders
                 SET tanggal_expire=?, payment_status='lunas', reminder_sent_at=NULL
                 WHERE id=? LIMIT 1"
            );
        }
        $stmtOrd->bind_param('si', $newExpire, $orderId);
        $stmtOrd->execute(); $stmtOrd->close();

        // 3. Log perubahan
        $roleStr    = 'admin';
        $newWifiStat = $wasSuspended ? 'active' : $oldWifiStat;
        $catatanLog = "Pembayaran tagihan bulan " . date('M Y', strtotime($oldExpire))
                    . " dikonfirmasi admin. Expire diperpanjang ke " . date('d M Y', strtotime($newExpire)) . "."
                    . ($wasSuspended ? " Layanan diaktifkan kembali." : "");
        $stmtLog = $conn->prepare(
            "INSERT INTO tblorder_status_logs (order_id,old_status,new_status,changed_by,role,catatan)
             VALUES (?,?,?,?,?,?)"
        );
        $stmtLog->bind_param('ississ', $orderId, $oldWifiStat, $newWifiStat, $adminId, $roleStr, $catatanLog);
        $stmtLog->execute(); $stmtLog->close();

        // 4. Notifikasi client
        $cid = (int)$ordRow['userid'];
        $j   = '✅ Pembayaran Dikonfirmasi – Layanan Diperpanjang';
        $p   = "Pembayaran tagihan bulan " . date('M Y', strtotime($oldExpire))
             . " untuk order {$ordRow['order_number']} telah dikonfirmasi. "
             . "Layanan WiFi Anda aktif hingga " . date('d M Y', strtotime($newExpire)) . "."
             . ($wasSuspended ? " Layanan WiFi Anda kini aktif kembali." : "");
        $t   = 'sukses';
        $stmtN = $conn->prepare("INSERT INTO tblnotifikasi (userid,order_id,judul,pesan,tipe) VALUES (?,?,?,?,?)");
        $stmtN->bind_param('iisss', $cid, $orderId, $j, $p, $t);
        $stmtN->execute(); $stmtN->close();

        echo json_encode([
            'ok'             => true,
            'new_expire'     => date('d M Y', strtotime($newExpire)),
            'new_expire_raw' => $newExpire,
            'reaktivasi'     => $wasSuspended,
        ]);
        exit;
    }

    // ── Tolak bukti bayar tagihan bulanan ──────────────────────
    if ($_POST['ajax_action'] === 'reject_monthly_payment') {
        $monthlyId = (int)($_POST['monthly_id'] ?? 0);
        $alasan    = trim($_POST['alasan'] ?? 'Bukti tidak valid.');

        if ($monthlyId) {
            $conn->query(
                "UPDATE tblpayment_monthly
                 SET status='unpaid', payment_proof=NULL
                 WHERE id=$monthlyId AND order_id=$orderId LIMIT 1"
            );
            $ordRow = $conn->query("SELECT userid, order_number FROM tblorders WHERE id=$orderId LIMIT 1")->fetch_assoc();
            $cid = (int)$ordRow['userid'];
            $j   = "❌ Bukti Pembayaran Tagihan Ditolak";
            $p   = "Bukti pembayaran tagihan bulan ini untuk order {$ordRow['order_number']} ditolak. Alasan: $alasan. Silakan upload ulang.";
            $t   = 'error';
            $stmtN = $conn->prepare("INSERT INTO tblnotifikasi (userid,order_id,judul,pesan,tipe) VALUES (?,?,?,?,?)");
            $stmtN->bind_param('iisss', $cid, $orderId, $j, $p, $t);
            $stmtN->execute(); $stmtN->close();
        }
        echo json_encode(['ok'=>true]); exit;
    }

    // ── Tolak bukti bayar ─────────────────────────────────────
    if ($_POST['ajax_action'] === 'reject_payment') {
        $invoiceId = (int)($_POST['invoice_id'] ?? 0);
        $alasan    = $_POST['alasan'] ?? 'Bukti pembayaran tidak valid.';
        if ($invoiceId) {
            $conn->query("UPDATE tblorders SET payment_status='belum_bayar' WHERE id=$orderId LIMIT 1");
            $ord2 = $conn->query("SELECT userid, order_number FROM tblorders WHERE id=$orderId")->fetch_assoc();
            if ($ord2) {
                $cid = (int)$ord2['userid'];
                $onum = $ord2['order_number'];
                $j = "Bukti Bayar Ditolak ❌";
                $p = "Bukti pembayaran untuk order $onum ditolak. Alasan: $alasan. Harap upload ulang.";
                $t = 'error';
                $stn = $conn->prepare("INSERT INTO tblnotifikasi (userid,order_id,judul,pesan,tipe) VALUES (?,?,?,?,?)");
                $stn->bind_param('iisss', $cid, $orderId, $j, $p, $t);
                $stn->execute(); $stn->close();
            }
        }
        echo json_encode(['ok'=>true]); exit;
    }

    // ── Update catatan saja ───────────────────────────────────
    if ($_POST['ajax_action'] === 'add_note') {
        $note = trim($_POST['note'] ?? '');
        $stmt = $conn->prepare("UPDATE tblorders SET note=? WHERE id=?");
        $stmt->bind_param('si', $note, $orderId);
        $stmt->execute(); $stmt->close();
        echo json_encode(['ok'=>true]); exit;
    }

    // ── Simpan ID Pelanggan + aktifkan WiFi + kirim email ─────
    if ($_POST['ajax_action'] === 'save_id_pelanggan') {
        $idPelanggan = trim($_POST['id_pelanggan'] ?? '');
        if (!$idPelanggan) {
            echo json_encode(['ok'=>false,'msg'=>'ID Pelanggan tidak boleh kosong.']); exit;
        }

        // Pastikan kolom id_pelanggan & tanggal_expire sudah ada (idempotent)
        $conn->query("ALTER TABLE tblorders ADD COLUMN IF NOT EXISTS id_pelanggan varchar(50) DEFAULT NULL COMMENT 'ID pelanggan dari aplikasi e-billing'");
        $conn->query("ALTER TABLE tblorders ADD COLUMN IF NOT EXISTS tanggal_expire date DEFAULT NULL COMMENT 'Tanggal jatuh tempo layanan WiFi'");

        // Hitung tanggal expire: SELALU tgl 20 bulan depan dari tanggal aktivasi.
        // Berapapun tanggal admin aktifkan (misal 25 Juni), expire tetap 20 Juli.
        // Logika bisnis: pembayaran instalasi mencakup layanan hingga tgl 20 bulan depan.
        $thisYear = (int)date('Y');
        $thisMon  = (int)date('n');
        $expMonth = $thisMon + 1;
        $expYear  = $thisYear;
        if ($expMonth > 12) { $expMonth = 1; $expYear++; }
        $tanggalExpire = sprintf('%04d-%02d-20', $expYear, $expMonth);

        // Update order: id_pelanggan, tanggal_expire, installation_paid_until, wifi_status → active
        // installation_paid_until diisi sekali saat aktivasi, tidak berubah pada perpanjangan berikutnya
        $stmt = $conn->prepare(
            "UPDATE tblorders SET
               id_pelanggan            = ?,
               tanggal_expire          = ?,
               installation_paid_until = ?,
               wifi_status             = 'active',
               tgl_aktif               = CURDATE(),
               payment_status          = 'lunas'
             WHERE id = ?"
        );
        $stmt->bind_param('sssi', $idPelanggan, $tanggalExpire, $tanggalExpire, $orderId);
        $stmt->execute(); $stmt->close();

        // Log perubahan status
        $oldOrd = $conn->query("SELECT wifi_status, userid, order_number, productid FROM tblorders WHERE id=$orderId")->fetch_assoc();
        // Ambil ulang sesudah update untuk data terbaru
        $updOrd = $conn->query("SELECT o.*, CONCAT(c.firstname,' ',c.lastname) AS client_name, c.firstname, c.lastname, c.email, c.phonenumber, p.name AS product_name, p.price, p.period FROM tblorders o JOIN tblclients c ON c.id=o.userid JOIN tblproducts p ON p.id=o.productid WHERE o.id=$orderId LIMIT 1")->fetch_assoc();

        $roleStr  = 'admin';
        $oldStat  = 'installed';
        $newStat  = 'active';
        $catatanLog = "ID Pelanggan: $idPelanggan. Layanan WiFi diaktifkan.";
        $stmt2 = $conn->prepare("INSERT INTO tblorder_status_logs (order_id,old_status,new_status,changed_by,role,catatan) VALUES (?,?,?,?,?,?)");
        $stmt2->bind_param('ississ', $orderId, $oldStat, $newStat, $adminId, $roleStr, $catatanLog);
        $stmt2->execute(); $stmt2->close();

        // Notifikasi in-app untuk klien
        $clientId = (int)$updOrd['userid'];
        $j = 'Layanan WiFi Anda Aktif 🚀';
        $p = "ID Pelanggan Anda: $idPelanggan. Layanan internet sudah dapat digunakan. Selamat menikmati!";
        $t = 'sukses';
        $stmtN = $conn->prepare("INSERT INTO tblnotifikasi (userid,order_id,judul,pesan,tipe) VALUES (?,?,?,?,?)");
        $stmtN->bind_param('iisss', $clientId, $orderId, $j, $p, $t);
        $stmtN->execute(); $stmtN->close();

        // Kirim email aktivasi ke klien
        require_once '../mailer.php';
        perkasa_send_mail(
            $updOrd['email'],
            trim($updOrd['firstname'] . ' ' . $updOrd['lastname']),
            '🚀 Layanan WiFi Anda Telah Aktif – Perkasa Solusindo',
            render_email_aktivasi_wifi([
                'id_pelanggan'   => $idPelanggan,
                'client_name'    => $updOrd['client_name'],
                'order_number'   => $updOrd['order_number'],
                'paket_name'     => $updOrd['product_name'],
                'paket_price'    => $updOrd['price'],
                'tanggal_expire' => $tanggalExpire,
                'tgl_aktif'      => date('Y-m-d'),
            ])
        );

        echo json_encode([
            'ok'             => true,
            'tanggal_expire' => date('d M Y', strtotime($tanggalExpire)),
        ]);
        exit;
    }

    echo json_encode(['ok'=>false,'msg'=>'Unknown action.']); exit;
}

// ── Pastikan kolom id_pelanggan & tanggal_expire ada (idempotent) ───────────
$conn->query("ALTER TABLE tblorders ADD COLUMN IF NOT EXISTS id_pelanggan varchar(50) DEFAULT NULL COMMENT 'ID pelanggan dari aplikasi e-billing'");
$conn->query("ALTER TABLE tblorders ADD COLUMN IF NOT EXISTS tanggal_expire date DEFAULT NULL COMMENT 'Tanggal jatuh tempo layanan WiFi'");

// ── Fetch order detail ────────────────────────────────────────
$stmt = $conn->prepare(
    "SELECT o.*,
            CONCAT(c.firstname,' ',c.lastname) AS client_name,
            c.firstname, c.lastname, c.email, c.phonenumber,
            c.address1, c.city, c.state, c.companyname, c.nik,
            p.name AS product_name, p.category, p.price, p.speed, p.description AS product_desc,
            p.period, p.id AS product_id,
            t1.firstname AS tek1_firstname, t1.lastname AS tek1_lastname, t1.phonenumber AS tek1_phone,
            t2.firstname AS tek2_firstname, t2.lastname AS tek2_lastname, t2.phonenumber AS tek2_phone
     FROM tblorders o
     JOIN tblclients c ON c.id=o.userid
     JOIN tblproducts p ON p.id=o.productid
     LEFT JOIN tblclients t1 ON t1.id=o.teknisi_id
     LEFT JOIN tblclients t2 ON t2.id=o.teknisi_id_2
     WHERE o.id=? LIMIT 1"
);
$stmt->bind_param('i', $orderId); $stmt->execute();
$ord = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$ord) { header('Location: /admin/orders.php'); exit; }

// ── Tentukan jenis layanan ────────────────────────────────────
$orderType = $ord['order_type'] ?: ($ord['category'] ?? 'other');

// ── Invoice terkait order ini ─────────────────────────────────
// tblinvoices difilter by userid. Setelah migration_fix_v3.sql dijalankan
// dan kolom order_id terisi, query ini bisa diperketat lebih lanjut.
$invoices = [];
$ri = $conn->prepare("SELECT * FROM tblinvoices WHERE userid=? ORDER BY created_at DESC LIMIT 5");
$ri->bind_param('i', $ord['userid']); $ri->execute();
$rir = $ri->get_result(); while($row=$rir->fetch_assoc()) $invoices[]=$row; $ri->close();

// Invoice paling relevan (Unpaid dulu, lalu terbaru)
$linkedInvoice = null;
foreach ($invoices as $inv) {
    if ($inv['status'] === 'Unpaid') { $linkedInvoice = $inv; break; }
}
if (!$linkedInvoice && !empty($invoices)) $linkedInvoice = $invoices[0];

// ── Hosting aktif klien ini (jika hosting order) ──────────────
$hostingRow = null;
if ($orderType === 'hosting') {
    $rh = $conn->prepare("SELECT * FROM tblhosting WHERE userid=? AND packageid=? LIMIT 1");
    $rh->bind_param('ii', $ord['userid'], $ord['product_id']); $rh->execute();
    $hostingRow = $rh->get_result()->fetch_assoc(); $rh->close();
}

// ── Bukti pembayaran (dari tblorders.note atau kolom terpisah) ─
// Asumsi: bukti disimpan di kolom payment_proof di tblorders (atau via note)
// Kita cek apakah kolom payment_proof ada
$proofPath = $ord['payment_proof'] ?? null;

// ── Pastikan tabel tblpayment_monthly & kolom reminder_sent_at ada (idempotent) ─
$conn->query("
    CREATE TABLE IF NOT EXISTS `tblpayment_monthly` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `order_id` int(11) NOT NULL,
      `userid` int(11) NOT NULL,
      `tagihan_bulan` date NOT NULL,
      `due_date` date NOT NULL,
      `suspend_date` date NOT NULL,
      `payment_proof` varchar(255) DEFAULT NULL,
      `status` varchar(20) NOT NULL DEFAULT 'unpaid',
      `confirmed_at` datetime DEFAULT NULL,
      `confirmed_by` int(11) DEFAULT NULL,
      `new_expire` date DEFAULT NULL,
      `created_at` datetime NOT NULL DEFAULT current_timestamp(),
      `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `uq_order_bulan` (`order_id`,`tagihan_bulan`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
$conn->query("ALTER TABLE tblorders ADD COLUMN IF NOT EXISTS reminder_sent_at date DEFAULT NULL");

// ── Ambil tagihan bulanan bulan ini (jika ada) ──────────────────
$tagiBulanIni = null;
if ($orderType === 'wifi' && !empty($ord['tanggal_expire'])) {
    $currentExpire = $ord['tanggal_expire'];
    $rtb = $conn->prepare("SELECT * FROM tblpayment_monthly WHERE order_id=? AND tagihan_bulan=? LIMIT 1");
    $rtb->bind_param('is', $orderId, $currentExpire);
    $rtb->execute();
    $tagiBulanIni = $rtb->get_result()->fetch_assoc();
    $rtb->close();
}

// ── Ambil riwayat tagihan bulanan (5 terakhir) ──────────────────
$riwayatTagihan = [];
if ($orderType === 'wifi') {
    $rrt = $conn->prepare("SELECT * FROM tblpayment_monthly WHERE order_id=? ORDER BY tagihan_bulan DESC LIMIT 5");
    $rrt->bind_param('i', $orderId);
    $rrt->execute();
    $resRrt = $rrt->get_result();
    while ($rr = $resRrt->fetch_assoc()) $riwayatTagihan[] = $rr;
    $rrt->close();
}

// ── Hitung hari ini untuk logika tampilan panel tagihan ─────────
// Panel muncul mulai tgl 10 (sesuai jadwal cron reminder).
// EXCEPTION: jika ada tagihan aktif (waiting_confirm / unpaid / paid),
// panel tetap ditampilkan berapapun tanggalnya — agar admin bisa konfirmasi
// bukti yang sudah diupload client sebelum tgl 10.
$hariIni    = (int)date('j');
$adaTagihanAktif = $tagiBulanIni && in_array($tagiBulanIni['status'], ['waiting_confirm', 'unpaid', 'paid']);
$showPanelTagihan = ($orderType === 'wifi'
                  && $ord['wifi_status'] === 'active'
                  && !empty($ord['tanggal_expire'])
                  && ($hariIni >= 10 || $adaTagihanAktif));

// ── Status log ────────────────────────────────────────────────
$logs = [];
$rl = $conn->prepare(
    "SELECT l.*, CONCAT(c.firstname,' ',c.lastname) AS actor_name, c.level AS actor_level
     FROM tblorder_status_logs l
     LEFT JOIN tblclients c ON c.id=l.changed_by
     WHERE l.order_id=? ORDER BY l.created_at DESC"
);
$rl->bind_param('i',$orderId); $rl->execute();
$res=$rl->get_result(); while($row=$res->fetch_assoc()) $logs[]=$row; $rl->close();

// ── Teknisi list ──────────────────────────────────────────────
$teknisiList = [];
$rt = $conn->query("SELECT id, firstname, lastname FROM tblclients WHERE level=4 AND status=1 ORDER BY firstname");
if ($rt) { while($row=$rt->fetch_assoc()) $teknisiList[]=$row; }

// ── Helpers ───────────────────────────────────────────────────
function statusBadge($s) {
    $m = [
        'pending'   => ['badge-yellow','fa-hourglass-half',    'Menunggu Verifikasi'],
        'verified'  => ['badge-blue',  'fa-circle-check',      'Diverifikasi'],
        'scheduled' => ['badge-indigo','fa-calendar-check',    'Dijadwalkan'],
        'installed' => ['badge-green', 'fa-screwdriver-wrench','Terpasang'],
        'active'    => ['badge-green', 'fa-wifi',              'Aktif'],
        'cancelled' => ['badge-red',   'fa-ban',               'Dibatalkan'],
    ];
    return $m[$s] ?? ['badge-gray','fa-circle','–'];
}
function catInfo($cat) {
    $m = [
        'wifi'     => ['fa-wifi',    '#3b82f6','Provider WiFi'],
        'hosting'  => ['fa-server',  '#10b981','Hosting'],
        'website'  => ['fa-code',    '#8b5cf6','Website'],
        'komputer' => ['fa-desktop', '#f59e0b','Komputer'],
        'cctv'     => ['fa-video',   '#ef4444','CCTV'],
        'other'    => ['fa-box-open','#7d8590','Lainnya'],
    ];
    return $m[$cat] ?? $m['other'];
}

// Step progression per jenis layanan
$stepsMap = [
    'wifi'     => ['pending','verified','scheduled','installed','active'],
    'hosting'  => ['pending','verified','active'],
    'website'  => ['pending','verified','scheduled','active'],
    'komputer' => ['pending','verified','scheduled','active'],
    'cctv'     => ['pending','verified','scheduled','installed','active'],
    'other'    => ['pending','verified','active'],
];
$stepLabelsMap = [
    'wifi'     => ['Pending','Verifikasi','Dijadwalkan','Dipasang','Aktif'],
    'hosting'  => ['Pending','Verifikasi','Aktif'],
    'website'  => ['Pending','Verifikasi','Pengerjaan','Selesai'],
    'komputer' => ['Pending','Verifikasi','Servis','Selesai'],
    'cctv'     => ['Pending','Verifikasi','Dijadwalkan','Dipasang','Aktif'],
    'other'    => ['Pending','Verifikasi','Aktif'],
];
$steps      = $stepsMap[$orderType]      ?? $stepsMap['other'];
$stepLabels = $stepLabelsMap[$orderType] ?? $stepLabelsMap['other'];

$badge   = statusBadge($ord['wifi_status']);
$catI    = catInfo($orderType);
$curStep = array_search($ord['wifi_status'], $steps);
if ($curStep === false) $curStep = -1;

// Status options per type
$statusOptsMap = [
    'wifi'    => ['pending'=>'⏳ Menunggu Verifikasi','verified'=>'✅ Diverifikasi','scheduled'=>'📅 Dijadwalkan Instalasi','installed'=>'🔧 Instalasi Selesai','active'=>'🚀 Aktif','cancelled'=>'❌ Dibatalkan'],
    'hosting' => ['pending'=>'⏳ Menunggu Verifikasi','verified'=>'✅ Diverifikasi','active'=>'🚀 Hosting Aktif','cancelled'=>'❌ Dibatalkan'],
    'website' => ['pending'=>'⏳ Menunggu Verifikasi','verified'=>'✅ Diverifikasi','scheduled'=>'🛠️ Dalam Pengerjaan','active'=>'✅ Selesai & Diserahkan','cancelled'=>'❌ Dibatalkan'],
    'komputer'=> ['pending'=>'⏳ Menunggu Verifikasi','verified'=>'✅ Diterima','scheduled'=>'🔧 Dalam Servis','active'=>'✅ Selesai','cancelled'=>'❌ Dibatalkan'],
    'cctv'   => ['pending'=>'⏳ Menunggu Verifikasi','verified'=>'✅ Diverifikasi','scheduled'=>'📅 Dijadwalkan','installed'=>'🔧 Terpasang','active'=>'🚀 Aktif','cancelled'=>'❌ Dibatalkan'],
];
$statusOpts = $statusOptsMap[$orderType] ?? $statusOptsMap['wifi'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Detail Order <?= htmlspecialchars($ord['order_number'] ?? '#'.$orderId) ?> – Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/admin/style_admin.css">
<style>
.od-grid { display:grid; grid-template-columns:1fr 340px; gap:24px; align-items:start; }
@media(max-width:1080px){ .od-grid { grid-template-columns:1fr; } }

.section-block { background:var(--surface); border:1px solid var(--border); border-radius:14px; margin-bottom:20px; overflow:hidden; }
.section-header { display:flex; align-items:center; justify-content:space-between; padding:13px 18px; background:var(--surface2); border-bottom:1px solid var(--border); font-size:12px; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:.7px; }
.section-body { padding:18px; }

.info-row { display:flex; align-items:flex-start; gap:12px; padding:10px 0; border-bottom:1px solid var(--border); font-size:13px; }
.info-row:last-child { border-bottom:none; }
.info-row-icon { width:28px; color:var(--muted); text-align:center; flex-shrink:0; padding-top:2px; }
.info-row-lbl { font-size:10px; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:.4px; margin-bottom:2px; }

/* Stepper */
.steps { display:flex; align-items:center; margin-bottom:20px; overflow-x:auto; }
.step-item { flex:1; min-width:80px; text-align:center; position:relative; font-size:10px; font-weight:600; color:var(--muted); padding:10px 4px; }
.step-dot { width:28px; height:28px; border-radius:50%; background:var(--surface2); border:2px solid var(--border); display:flex; align-items:center; justify-content:center; margin:0 auto 6px; font-size:11px; transition:all .3s; }
.step-item.done .step-dot   { background:var(--accent2); border-color:var(--accent2); color:#fff; }
.step-item.active .step-dot { background:var(--accent); border-color:var(--accent); color:#fff; box-shadow:0 0 0 4px rgba(99,102,241,.2); }
.step-item.done   { color:var(--text); }
.step-item.active { color:var(--accent); }
.step-line { flex:1; height:2px; background:var(--border); margin-top:-20px; transition:background .3s; }
.step-line.done { background:var(--accent2); }

/* Forms */
.update-form .form-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px; }
@media(max-width:640px){ .update-form .form-row { grid-template-columns:1fr; } }
.form-lbl { display:block; font-size:11px; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:.5px; margin-bottom:6px; }
.form-ctrl { width:100%; background:var(--surface2); border:1px solid var(--border); color:var(--text); padding:9px 12px; border-radius:8px; font-size:13px; font-family:inherit; outline:none; transition:border-color .2s; box-sizing:border-box; }
.form-ctrl:focus { border-color:var(--accent2); }
textarea.form-ctrl { resize:vertical; min-height:80px; }
.action-row { display:flex; gap:8px; flex-wrap:wrap; margin-top:16px; }

/* Log */
.log-list { display:flex; flex-direction:column; gap:0; }
.log-item { display:flex; gap:12px; padding:12px 0; border-bottom:1px solid var(--border); font-size:12px; }
.log-item:last-child { border-bottom:none; }
.log-dot { width:10px; height:10px; border-radius:50%; margin-top:4px; flex-shrink:0; }
.log-status { font-weight:700; color:var(--text); }
.log-meta   { font-size:11px; color:var(--muted); margin-top:2px; }
.log-note   { font-size:11px; color:var(--muted); font-style:italic; margin-top:2px; }

/* Hero */
.order-hero { padding:20px 18px; background:linear-gradient(135deg,rgba(99,102,241,.1),rgba(59,130,246,.05)); border-bottom:1px solid var(--border); }

/* Badges */
.badge-indigo { background:rgba(99,102,241,.15);color:#818cf8;border:1px solid rgba(99,102,241,.25); }
.badge-teal   { background:rgba(20,184,166,.15);color:#2dd4bf;border:1px solid rgba(20,184,166,.25); }

/* Alert */
.alert { padding:10px 14px; border-radius:8px; font-size:13px; display:flex; align-items:center; gap:8px; margin-bottom:12px; }
.alert-success { background:rgba(16,185,129,.1); border:1px solid rgba(16,185,129,.25); color:#34d399; }
.alert-danger  { background:rgba(239,68,68,.1);  border:1px solid rgba(239,68,68,.25);  color:#f87171; }
.alert-info    { background:rgba(59,130,246,.1); border:1px solid rgba(59,130,246,.25); color:#60a5fa; }
.alert-warning { background:rgba(245,158,11,.1); border:1px solid rgba(245,158,11,.25); color:#fbbf24; }

/* Payment proof */
.proof-box { border:2px dashed var(--border); border-radius:12px; padding:20px; text-align:center; }
.proof-img { width:100%; max-height:300px; object-fit:contain; border-radius:8px; cursor:zoom-in; }

/* Hosting provision panel */
.provision-field { margin-bottom:14px; }
.provision-field .form-lbl { font-size:11px; }

/* Contact card */
.contact-actions { display:flex; flex-direction:column; gap:8px; }
.contact-btn { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:8px; text-decoration:none; color:var(--text); font-size:13px; font-weight:600; transition:background .2s; border:1px solid var(--border); }
.contact-btn:hover { opacity:.85; }

/* Type tag */
.type-tag { display:inline-flex; align-items:center; gap:6px; padding:4px 12px; border-radius:20px; font-size:12px; font-weight:700; margin-bottom:10px; }

/* Invoice chip */
.inv-chip { display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:8px; font-size:12px; font-weight:700; font-family:'JetBrains Mono',monospace; }
.inv-unpaid { background:rgba(245,158,11,.15); color:#fbbf24; border:1px solid rgba(245,158,11,.3); }
.inv-paid   { background:rgba(16,185,129,.15);  color:#34d399; border:1px solid rgba(16,185,129,.3); }
.inv-other  { background:var(--surface2); color:var(--muted); border:1px solid var(--border); }
</style>
</head>
<body>

<!-- ═══════════════ SIDEBAR ═══════════════ -->
<aside class="sidebar">
  <div class="sidebar-brand">
    <div class="logo-mark">
      <div class="logo-icon">P</div>
      <div class="logo-text">Perkasa Solusindo<span>Admin Panel</span></div>
    </div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-label">Main</div>
    <a href="/admin/admin_dashboard.php" class="nav-item"><i class="fa fa-gauge-high"></i> Dashboard</a>

    <div class="nav-label">Transaksi</div>
    <a href="/admin/orders.php" class="nav-item active has-sub expanded" onclick="toggleSubMenu(event,'subOrders')">
      <i class="fa fa-list-check"></i> Semua Order
      <?php if($totalOrdersPending > 0): ?>
        <span class="nav-badge"><?= $totalOrdersPending ?></span>
      <?php endif; ?>
      <i class="fa fa-chevron-right nav-arrow"></i>
    </a>
    <div class="nav-sub-group open" id="subOrders">
      <?php
      $wifiPendingNav = (int)$conn->query(
          "SELECT COUNT(*) FROM tblorders o JOIN tblproducts p ON p.id=o.productid
           WHERE (p.category='wifi' OR o.order_type='wifi') AND o.wifi_status IN ('pending','verified','scheduled')"
      )->fetch_row()[0];
      ?>
      <a href="/admin/orders_wifi.php" class="nav-item nav-sub">
        <i class="fa fa-wifi"></i> Order Layanan WiFi
        <?php if($wifiPendingNav > 0): ?>
          <span class="nav-badge"><?= $wifiPendingNav ?></span>
        <?php endif; ?>
      </a>
      <?php
      $hostingPendingNav = (int)$conn->query(
          "SELECT COUNT(*) FROM tblorders o JOIN tblproducts p ON p.id=o.productid
           WHERE (p.category='hosting' OR o.order_type='hosting') AND o.wifi_status IN ('pending','verified')"
      )->fetch_row()[0];
      ?>
      <a href="/admin/orders_hosting.php" class="nav-item nav-sub">
        <i class="fa fa-server"></i> Order Hosting
        <?php if($hostingPendingNav > 0): ?>
          <span class="nav-badge"><?= $hostingPendingNav ?></span>
        <?php endif; ?>
      </a>
    </div>
    <a href="/admin/invoices.php" class="nav-item">
      <i class="fa fa-file-invoice-dollar"></i> Invoice
      <?php if($stats['unpaid'] > 0): ?>
        <span class="nav-badge"><?= $stats['unpaid'] ?></span>
      <?php endif; ?>
    </a>

    <div class="nav-label">Manajemen</div>
    <a href="/admin/products.php" class="nav-item"><i class="fa fa-box-open"></i> Produk Layanan</a>
    <a href="/admin/clients.php" class="nav-item"><i class="fa fa-users"></i> Data Klien</a>
    <a href="/admin/teknisi.php" class="nav-item"><i class="fa fa-screwdriver-wrench"></i> Teknisi</a>
    <a href="/admin/hosting.php" class="nav-item"><i class="fa fa-server"></i> Hosting</a>
    <a href="/admin/domains.php" class="nav-item"><i class="fa fa-globe"></i> Domain</a>

    <div class="nav-label">Support</div>
    <a href="/admin/tickets.php" class="nav-item">
      <i class="fa fa-ticket"></i> Tiket Support
      <?php if($stats['tickets'] > 0): ?>
        <span class="nav-badge"><?= $stats['tickets'] ?></span>
      <?php endif; ?>
    </a>
    <a href="/admin/announcements.php" class="nav-item"><i class="fa fa-bullhorn"></i> Pengumuman</a>

    <div class="nav-label">Sistem</div>
    <a href="../index.php" target="_blank" class="nav-item"><i class="fa fa-globe"></i> Lihat Website</a>
    <a href="/admin/settings.php" class="nav-item"><i class="fa fa-gear"></i> Pengaturan</a>
  </nav>
  <div class="sidebar-footer">
    <div class="admin-profile">
      <div class="avatar"><?= strtoupper(substr($adminName,0,1)) ?></div>
      <div class="admin-info">
        <div class="admin-name"><?= htmlspecialchars($adminName) ?></div>
        <div class="admin-role">Administrator</div>
      </div>
      <a href="#" class="btn-logout" onclick="confirmLogout(event)" title="Logout">
        <i class="fa fa-right-from-bracket"></i>
      </a>
    </div>
  </div>
</aside>

<!-- ═══════════════ MAIN ═══════════════ -->
<main class="main">

  <!-- Topbar -->
  <div class="topbar">
    <div class="page-title" style="font-size:13px;display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
      <a href="/admin/admin_dashboard.php" style="color:var(--muted);text-decoration:none;">Dashboard</a>
      <span style="color:var(--muted);">/</span>
      <a href="/admin/orders.php" style="color:var(--muted);text-decoration:none;">Semua Order</a>
      <span style="color:var(--muted);">/</span>
      <span style="color:<?= $catI[1] ?>;font-weight:700;"><?= htmlspecialchars($ord['order_number'] ?? '#'.$orderId) ?></span>
    </div>
    <div class="topbar-right">
      <span class="date-badge"><i class="fa fa-calendar-days" style="margin-right:6px;"></i><?= date('d M Y') ?></span>
      <a href="/admin/orders.php" class="topbar-btn" title="Kembali ke daftar order"><i class="fa fa-arrow-left"></i></a>
      <a href="#" class="topbar-btn" onclick="confirmLogout(event)" title="Logout"><i class="fa fa-right-from-bracket"></i></a>
    </div>
  </div>

  <div class="content">

    <div id="flash-msg"></div>

    <!-- ═════ HERO ═══════════════════════════════════════════ -->
    <div class="section-block" style="margin-bottom:24px;">
      <div class="order-hero">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:14px;">
          <div>
            <!-- Type pill -->
            <span class="type-tag" style="background:<?= $catI[1] ?>18;color:<?= $catI[1] ?>;border:1px solid <?= $catI[1] ?>33;">
              <i class="fa <?= $catI[0] ?>"></i> <?= $catI[2] ?>
            </span>
            <!-- Order number -->
            <div style="font-family:'JetBrains Mono',monospace;font-size:13px;color:var(--accent2);font-weight:600;margin-bottom:6px;">
              <?= htmlspecialchars($ord['order_number'] ?? '#'.$orderId) ?>
            </div>
            <div style="font-size:20px;font-weight:800;margin-bottom:4px;"><?= htmlspecialchars($ord['client_name']) ?></div>
            <div style="font-size:13px;color:var(--muted);">
              <?= htmlspecialchars($ord['product_name']) ?>
              <?php if($ord['speed']): ?>
                <span class="badge badge-blue" style="margin-left:6px;"><?= $ord['speed'] ?></span>
              <?php endif; ?>
              &nbsp;·&nbsp;
              <span style="font-family:'JetBrains Mono',monospace;">Rp <?= number_format($ord['price'],0,',','.') ?>/<?= $ord['period'] ?></span>
            </div>
          </div>
          <div style="text-align:right;">
            <span class="badge <?= $badge[0] ?>" style="font-size:13px;padding:6px 16px;">
              <i class="fa <?= $badge[1] ?>" style="margin-right:6px;"></i><?= $badge[2] ?>
            </span>
            <?php
            $payMap = ['belum_bayar'=>['inv-unpaid','💳 Belum Bayar'],'sudah_bayar'=>['inv-unpaid','📤 Bukti Dikirim'],'lunas'=>['inv-paid','✅ Lunas']];
            $payChip = $payMap[$ord['payment_status']] ?? ['inv-other','–'];
            ?>
            <div style="margin-top:8px;">
              <span class="inv-chip <?= $payChip[0] ?>"><?= $payChip[1] ?></span>
            </div>
            <div style="font-size:11px;color:var(--muted);margin-top:8px;font-family:'JetBrains Mono',monospace;">
              Dibuat: <?= date('d M Y H:i', strtotime($ord['created_at'])) ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Status Stepper -->
      <?php if($ord['wifi_status'] !== 'cancelled'): ?>
      <div style="padding:18px 18px 8px;">
        <div class="steps">
          <?php foreach($steps as $i => $s):
            $cls = '';
            if ($i < $curStep) $cls = 'done';
            elseif ($i === $curStep) $cls = 'active';
          ?>
            <?php if($i > 0): ?>
              <div class="step-line <?= $i <= $curStep ? 'done' : '' ?>"></div>
            <?php endif; ?>
            <div class="step-item <?= $cls ?>">
              <div class="step-dot">
                <?= ($i < $curStep) ? '<i class="fa fa-check" style="font-size:10px;"></i>' : ($i+1) ?>
              </div>
              <?= $stepLabels[$i] ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php else: ?>
      <div style="padding:14px 18px;">
        <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);border-radius:8px;padding:10px 14px;font-size:13px;color:#f87171;display:flex;align-items:center;gap:8px;">
          <i class="fa fa-ban"></i> Order ini telah <strong>dibatalkan</strong>.
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- ═════ TWO COLUMN GRID ════════════════════════════════ -->
    <div class="od-grid">

      <!-- ════ LEFT COLUMN ════ -->
      <div>

        <!-- ── Info Pesanan Umum ─────────────────────────── -->
        <div class="section-block">
          <div class="section-header">
            <span><i class="fa fa-info-circle" style="margin-right:8px;color:var(--accent);"></i>Informasi Pesanan</span>
          </div>
          <div class="section-body">
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:14px;">

              <div>
                <div class="info-row-lbl">Klien</div>
                <div style="font-size:14px;font-weight:700;"><?= htmlspecialchars($ord['client_name']) ?></div>
                <?php if($ord['companyname']): ?><div style="font-size:12px;color:var(--muted);"><?= htmlspecialchars($ord['companyname']) ?></div><?php endif; ?>
              </div>

              <div>
                <div class="info-row-lbl">No. HP</div>
                <div style="font-size:13px;">
                  <?= htmlspecialchars($ord['phonenumber']) ?>
                  <a href="https://wa.me/<?= preg_replace('/\D/','',$ord['phonenumber']) ?>" target="_blank"
                     style="margin-left:6px;background:rgba(37,211,102,.12);color:#25d366;padding:2px 8px;border-radius:6px;font-size:11px;text-decoration:none;">
                    <i class="fab fa-whatsapp"></i> WA
                  </a>
                </div>
              </div>

              <div>
                <div class="info-row-lbl">Email</div>
                <div style="font-size:13px;"><?= htmlspecialchars($ord['email']) ?></div>
              </div>

              <div>
                <div class="info-row-lbl">Produk</div>
                <div style="font-size:13px;font-weight:600;"><?= htmlspecialchars($ord['product_name']) ?></div>
                <?php if($ord['speed']): ?><span class="badge badge-blue" style="font-size:10px;"><?= $ord['speed'] ?></span><?php endif; ?>
              </div>

              <div>
                <div class="info-row-lbl">Harga</div>
                <div style="font-size:14px;font-weight:700;font-family:'JetBrains Mono',monospace;">
                  Rp <?= number_format($ord['price'],0,',','.') ?>
                  <span style="font-size:11px;font-weight:400;color:var(--muted);">/ <?= $ord['period'] ?></span>
                </div>
              </div>

              <div>
                <div class="info-row-lbl">Status Pembayaran</div>
                <span class="inv-chip <?= $payChip[0] ?>"><?= $payChip[1] ?></span>
              </div>

              <?php if($ord['jadwal_instalasi']): ?>
              <div>
                <div class="info-row-lbl">Jadwal <?= $orderType === 'hosting' ? 'Aktivasi' : 'Instalasi' ?></div>
                <div style="font-size:13px;font-weight:600;">
                  <i class="fa fa-calendar-check" style="color:var(--accent2);margin-right:6px;"></i>
                  <?= date('d M Y, H:i', strtotime($ord['jadwal_instalasi'])) ?>
                </div>
              </div>
              <?php endif; ?>

              <?php if($ord['tgl_aktif']): ?>
              <div>
                <div class="info-row-lbl">Tanggal Aktif</div>
                <div style="font-size:13px;"><?= date('d M Y', strtotime($ord['tgl_aktif'])) ?></div>
              </div>
              <?php endif; ?>

            </div>

            <!-- Alamat (WiFi & CCTV) -->
            <?php if($ord['alamat_pasang'] && in_array($orderType,['wifi','cctv'])): ?>
            <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border);">
              <div class="info-row-lbl" style="margin-bottom:6px;"><i class="fa fa-location-dot" style="margin-right:6px;color:var(--accent);"></i>Alamat Pemasangan</div>
              <div style="font-size:13px;line-height:1.7;">
                <?= htmlspecialchars($ord['alamat_pasang']) ?>
                <?php if($ord['rt'] && $ord['rw']): ?>, RT <?= $ord['rt'] ?>/RW <?= $ord['rw'] ?><?php endif; ?>
                <?php $loc=array_filter([$ord['kelurahan'],$ord['kecamatan'],$ord['kota'],$ord['provinsi']]); if($loc): ?>, <?= htmlspecialchars(implode(', ',$loc)) ?><?php endif; ?>
                <?php if($ord['kodepos']): ?> – <?= $ord['kodepos'] ?><?php endif; ?>
              </div>
              <?php if($ord['koordinat_lat'] && $ord['koordinat_lng']): ?>
              <a href="https://maps.google.com/?q=<?= $ord['koordinat_lat'] ?>,<?= $ord['koordinat_lng'] ?>" target="_blank"
                 style="display:inline-flex;align-items:center;gap:6px;margin-top:8px;background:rgba(59,130,246,.1);color:#60a5fa;padding:4px 12px;border-radius:8px;font-size:12px;text-decoration:none;border:1px solid rgba(59,130,246,.2);">
                <i class="fa fa-map-location-dot"></i> Lihat di Google Maps
              </a>
              <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Domain (Hosting) -->
            <?php if($orderType === 'hosting' && $hostingRow): ?>
            <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border);">
              <div class="info-row-lbl" style="margin-bottom:6px;"><i class="fa fa-globe" style="margin-right:6px;color:#10b981;"></i>Hosting Aktif</div>
              <div style="font-size:14px;font-weight:700;font-family:'JetBrains Mono',monospace;color:#10b981;"><?= htmlspecialchars($hostingRow['domain']) ?></div>
              <div style="font-size:12px;color:var(--muted);margin-top:4px;">
                Status: <strong><?= $hostingRow['domainstatus'] ?></strong>
                <?php if($hostingRow['nextduedate']): ?> · Jatuh tempo: <?= date('d M Y', strtotime($hostingRow['nextduedate'])) ?><?php endif; ?>
              </div>
            </div>
            <?php endif; ?>

            <!-- Catatan Admin -->
            <?php if($ord['note']): ?>
            <div style="margin-top:14px;padding:12px;background:rgba(245,158,11,.06);border:1px solid rgba(245,158,11,.2);border-radius:8px;">
              <div style="font-size:11px;font-weight:700;color:#fbbf24;margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px;">
                <i class="fa fa-note-sticky" style="margin-right:6px;"></i>Catatan Admin
              </div>
              <div style="font-size:13px;line-height:1.6;"><?= nl2br(htmlspecialchars($ord['note'])) ?></div>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- ── PANEL KHUSUS HOSTING: Bukti Bayar + Provisioning ── -->
        <?php if($orderType === 'hosting'): ?>

        <!-- Bukti Pembayaran -->
        <div class="section-block">
          <div class="section-header">
            <span><i class="fa fa-receipt" style="margin-right:8px;color:#10b981;"></i>Bukti Pembayaran Client</span>
            <?php if($linkedInvoice): ?>
              <span class="inv-chip <?= $linkedInvoice['status']==='Paid'?'inv-paid':'inv-unpaid' ?>">
                Invoice #<?= $linkedInvoice['id'] ?> · <?= $linkedInvoice['status'] ?>
              </span>
            <?php endif; ?>
          </div>
          <div class="section-body">

            <?php if($ord['payment_status'] === 'sudah_bayar'): ?>
              <div class="alert alert-warning" style="margin-bottom:16px;">
                <i class="fa fa-hourglass-half"></i>
                <div><strong>Menunggu Konfirmasi Admin</strong><br>Client telah mengirim bukti pembayaran. Periksa dan konfirmasi atau tolak.</div>
              </div>
            <?php elseif($ord['payment_status'] === 'lunas'): ?>
              <div class="alert alert-success" style="margin-bottom:16px;">
                <i class="fa fa-check-circle"></i> Pembayaran telah dikonfirmasi. Invoice sudah berstatus <strong>Paid</strong>.
              </div>
            <?php else: ?>
              <div class="alert alert-info" style="margin-bottom:16px;">
                <i class="fa fa-info-circle"></i> Menunggu client mengirim bukti pembayaran.
              </div>
            <?php endif; ?>

            <!-- Preview bukti -->
            <?php if($proofPath): ?>
            <div class="proof-box" style="margin-bottom:16px;">
              <?php
              $ext = strtolower(pathinfo($proofPath, PATHINFO_EXTENSION));
              if(in_array($ext,['jpg','jpeg','png','gif','webp'])): ?>
                <img src="/<?= htmlspecialchars($proofPath) ?>" class="proof-img"
                     onclick="window.open(this.src,'_blank')" title="Klik untuk perbesar">
              <?php else: ?>
                <a href="/<?= htmlspecialchars($proofPath) ?>" target="_blank" class="btn btn-secondary">
                  <i class="fa fa-file-arrow-down"></i> Unduh Bukti Bayar
                </a>
              <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="proof-box" style="margin-bottom:16px;opacity:.5;">
              <i class="fa fa-image" style="font-size:32px;color:var(--muted);margin-bottom:8px;display:block;"></i>
              <div style="font-size:13px;color:var(--muted);">Belum ada bukti pembayaran dikirim.</div>
            </div>
            <?php endif; ?>

            <!-- Tombol konfirmasi / tolak -->
            <?php if($linkedInvoice && $linkedInvoice['status'] !== 'Paid'): ?>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
              <button class="btn btn-primary" onclick="confirmPayment(<?= $linkedInvoice['id'] ?>)">
                <i class="fa fa-check-circle"></i> Konfirmasi Pembayaran
              </button>
              <button class="btn btn-danger" onclick="rejectPayment(<?= $linkedInvoice['id'] ?>)">
                <i class="fa fa-xmark-circle"></i> Tolak & Minta Ulang
              </button>
            </div>
            <?php elseif($linkedInvoice && $linkedInvoice['status'] === 'Paid'): ?>
            <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:rgba(16,185,129,.06);border:1px solid rgba(16,185,129,.2);border-radius:8px;">
              <i class="fa fa-circle-check" style="color:#34d399;font-size:18px;"></i>
              <div>
                <div style="font-size:13px;font-weight:700;">Pembayaran Telah Dikonfirmasi</div>
                <div style="font-size:12px;color:var(--muted);">
                  Dibayar: <?= $linkedInvoice['datepaid'] ? date('d M Y H:i', strtotime($linkedInvoice['datepaid'])) : '–' ?>
                  · Total: Rp <?= number_format($linkedInvoice['total'],0,',','.') ?>
                </div>
              </div>
            </div>
            <?php endif; ?>

          </div>
        </div>

        <!-- Provisioning Hosting -->
        <?php if($ord['payment_status'] === 'lunas' || ($linkedInvoice && $linkedInvoice['status'] === 'Paid')): ?>
        <div class="section-block" style="border-color:rgba(16,184,129,.3);">
          <div class="section-header" style="background:rgba(16,184,129,.05);">
            <span><i class="fa fa-server" style="margin-right:8px;color:#10b981;"></i>Provisioning Hosting</span>
            <?php if($hostingRow): ?>
              <span class="badge badge-teal">✓ Sudah Aktif</span>
            <?php else: ?>
              <span style="font-size:11px;color:#fbbf24;font-weight:600;">⚠ Belum diaktifkan</span>
            <?php endif; ?>
          </div>
          <div class="section-body">

            <?php if(!$hostingRow): ?>
            <div class="alert alert-info" style="margin-bottom:18px;">
              <i class="fa fa-triangle-exclamation" style="color:#fbbf24;"></i>
              <div>Pembayaran sudah dikonfirmasi. <strong>Isi detail hosting</strong> di bawah lalu aktifkan layanan client.</div>
            </div>

            <div class="provision-field">
              <label class="form-lbl">Domain / Subdomain Client</label>
              <input type="text" id="prov-domain" class="form-ctrl" placeholder="contoh: namaclient.perkasasolusindo.com atau namaclient.com">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
              <div class="provision-field">
                <label class="form-lbl">Username cPanel</label>
                <input type="text" id="prov-cpanel-user" class="form-ctrl" placeholder="cpanel_username">
              </div>
              <div class="provision-field">
                <label class="form-lbl">Password cPanel</label>
                <input type="text" id="prov-cpanel-pass" class="form-ctrl" placeholder="Password sementara">
              </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
              <div class="provision-field">
                <label class="form-lbl">Nameserver 1</label>
                <input type="text" id="prov-ns1" class="form-ctrl" placeholder="ns1.perkasasolusindo.com" value="ns1.perkasasolusindo.com">
              </div>
              <div class="provision-field">
                <label class="form-lbl">Nameserver 2</label>
                <input type="text" id="prov-ns2" class="form-ctrl" placeholder="ns2.perkasasolusindo.com" value="ns2.perkasasolusindo.com">
              </div>
            </div>
            <div class="provision-field">
              <label class="form-lbl">Jatuh Tempo Hosting (Bulan Depan)</label>
              <input type="date" id="prov-duedate" class="form-ctrl" value="<?= date('Y-m-d', strtotime('+1 month')) ?>">
            </div>
            <div class="provision-field">
              <label class="form-lbl">Catatan ke Client (opsional)</label>
              <textarea id="prov-note" class="form-ctrl" placeholder="Hosting Anda telah aktif. Akses cPanel melalui domain/cpanel ..."></textarea>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:8px;">
              <button class="btn btn-primary" onclick="activateHosting()" style="background:linear-gradient(135deg,#10b981,#059669);">
                <i class="fa fa-server"></i> Aktifkan Hosting & Kirim Notifikasi
              </button>
              <button class="btn btn-secondary" onclick="sendCredentials()">
                <i class="fa fa-envelope"></i> Kirim Kredensial via WA
              </button>
            </div>

            <?php else: ?>
            <!-- Hosting sudah aktif → tampilkan info -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
              <div>
                <div class="info-row-lbl">Domain</div>
                <div style="font-size:14px;font-weight:700;font-family:'JetBrains Mono',monospace;color:#10b981;"><?= htmlspecialchars($hostingRow['domain']) ?></div>
              </div>
              <div>
                <div class="info-row-lbl">Status</div>
                <span class="badge badge-teal"><?= $hostingRow['domainstatus'] ?></span>
              </div>
              <div>
                <div class="info-row-lbl">Jatuh Tempo</div>
                <div style="font-size:13px;"><?= $hostingRow['nextduedate'] ? date('d M Y', strtotime($hostingRow['nextduedate'])) : '–' ?></div>
              </div>
              <div>
                <div class="info-row-lbl">Paket</div>
                <div style="font-size:13px;font-weight:600;"><?= htmlspecialchars($ord['product_name']) ?></div>
              </div>
            </div>
            <div style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap;">
              <button class="btn btn-secondary" onclick="sendCredentials()">
                <i class="fa fa-paper-plane"></i> Kirim Ulang Kredensial via WA
              </button>
              <button class="btn btn-secondary" onclick="suspendHosting()">
                <i class="fa fa-ban"></i> Suspend Hosting
              </button>
            </div>
            <?php endif; ?>

          </div>
        </div>
        <?php endif; /* payment confirmed */ ?>

        <?php endif; /* orderType === hosting */ ?>

        <!-- ── Panel khusus WiFi: Teknisi & Jadwal ─────────── -->
        <?php if($orderType === 'wifi'): ?>
        <div class="section-block">
          <div class="section-header">
            <span><i class="fa fa-wifi" style="margin-right:8px;color:#3b82f6;"></i>Detail Instalasi WiFi</span>
          </div>
          <div class="section-body">
            <?php if($ord['wifi_status'] === 'pending'): ?>
            <div class="alert alert-warning">
              <i class="fa fa-hourglass-half"></i>
              <div>Order baru masuk. Verifikasi data klien, lalu tetapkan teknisi dan jadwal instalasi di panel aksi di bawah.</div>
            </div>
            <?php elseif($ord['wifi_status'] === 'verified'): ?>
            <div class="alert alert-info">
              <i class="fa fa-circle-check"></i>
              <div>Data sudah diverifikasi. Tetapkan jadwal instalasi dan assign teknisi.</div>
            </div>
            <?php elseif($ord['wifi_status'] === 'active'): ?>
            <div class="alert alert-success">
              <i class="fa fa-wifi"></i>
              <div>WiFi sudah aktif sejak <?= $ord['tgl_aktif'] ? date('d M Y', strtotime($ord['tgl_aktif'])) : '–' ?>.</div>
            </div>
            <?php
            // ── Banner tagihan terlambat ──────────────────────────────
            if (!empty($ord['tanggal_expire']) && strtotime($ord['tanggal_expire']) < strtotime(date('Y-m-d'))):
            ?>
            <div style="display:flex;align-items:flex-start;gap:12px;padding:14px 18px;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);border-radius:10px;margin-top:12px;">
              <i class="fa fa-triangle-exclamation" style="color:#f87171;font-size:18px;margin-top:2px;flex-shrink:0;"></i>
              <div>
                <div style="font-size:13px;font-weight:800;color:#f87171;margin-bottom:4px;">Pelanggan Belum Bayar Tagihan Berikut</div>
                <div style="font-size:12px;color:var(--muted);line-height:1.6;">
                  Tagihan jatuh tempo pada <strong style="color:#fbbf24;"><?= date('d M Y', strtotime($ord['tanggal_expire'])) ?></strong>
                  belum dibayar. Layanan WiFi dapat dinonaktifkan.
                  <?php if(!empty($ord['id_pelanggan'])): ?>
                  <br>ID Pelanggan: <span style="font-family:'JetBrains Mono',monospace;font-weight:700;color:#fbbf24;"><?= htmlspecialchars($ord['id_pelanggan']) ?></span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
            <?php
            // Tampilkan detail teknisi yang sudah di-assign
            $anyTeknisi = $ord['tek1_firstname'] || $ord['tek2_firstname'];
            if ($anyTeknisi): ?>
            <div style="display:flex;flex-direction:column;gap:10px;margin-top:12px;">

              <?php if ($ord['tek1_firstname']): ?>
              <div style="display:flex;align-items:center;gap:12px;padding:12px;background:var(--surface2);border-radius:8px;">
                <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#f59e0b,#d97706);display:grid;place-items:center;font-size:16px;font-weight:800;color:#fff;flex-shrink:0;">
                  <?= strtoupper(substr($ord['tek1_firstname'],0,1)) ?>
                </div>
                <div>
                  <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px;">Teknisi 1</div>
                  <div style="font-size:13px;font-weight:700;"><?= htmlspecialchars($ord['tek1_firstname'].' '.$ord['tek1_lastname']) ?></div>
                  <?php if($ord['tek1_phone']): ?>
                  <a href="https://wa.me/<?= preg_replace('/\D/','',$ord['tek1_phone']) ?>" target="_blank"
                     style="font-size:12px;color:#25d366;text-decoration:none;"><i class="fab fa-whatsapp"></i> <?= htmlspecialchars($ord['tek1_phone']) ?></a>
                  <?php endif; ?>
                </div>
              </div>
              <?php endif; ?>

              <?php if ($ord['tek2_firstname']): ?>
              <div style="display:flex;align-items:center;gap:12px;padding:12px;background:var(--surface2);border-radius:8px;">
                <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,#8b5cf6,#7c3aed);display:grid;place-items:center;font-size:16px;font-weight:800;color:#fff;flex-shrink:0;">
                  <?= strtoupper(substr($ord['tek2_firstname'],0,1)) ?>
                </div>
                <div>
                  <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px;">Teknisi 2</div>
                  <div style="font-size:13px;font-weight:700;"><?= htmlspecialchars($ord['tek2_firstname'].' '.$ord['tek2_lastname']) ?></div>
                  <?php if($ord['tek2_phone']): ?>
                  <a href="https://wa.me/<?= preg_replace('/\D/','',$ord['tek2_phone']) ?>" target="_blank"
                     style="font-size:12px;color:#25d366;text-decoration:none;"><i class="fab fa-whatsapp"></i> <?= htmlspecialchars($ord['tek2_phone']) ?></a>
                  <?php endif; ?>
                </div>
              </div>
              <?php endif; ?>

            </div>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- ── Panel khusus Website ──────────────────────── -->
        <?php if($orderType === 'website'): ?>
        <div class="section-block">
          <div class="section-header">
            <span><i class="fa fa-code" style="margin-right:8px;color:#8b5cf6;"></i>Detail Project Website</span>
          </div>
          <div class="section-body">
            <?php
            $wsStatusMsg = [
                'pending'   => ['alert-info',    'fa-hourglass-half', 'Order baru masuk. Hubungi klien untuk briefing dan spesifikasi website.'],
                'verified'  => ['alert-info',    'fa-circle-check',   'Brief sudah diterima. Pengerjaan akan segera dimulai.'],
                'scheduled' => ['alert-warning', 'fa-code',           'Dalam pengerjaan. Update progress secara berkala ke klien.'],
                'active'    => ['alert-success', 'fa-circle-check',   'Website selesai dikerjakan dan telah diserahkan ke klien.'],
            ];
            $wsMsg = $wsStatusMsg[$ord['wifi_status']] ?? null;
            if($wsMsg): ?>
            <div class="alert <?= $wsMsg[0] ?>">
              <i class="fa <?= $wsMsg[1] ?>"></i> <?= $wsMsg[2] ?>
            </div>
            <?php endif; ?>
            <?php if($ord['product_desc']): ?>
            <div style="font-size:13px;color:var(--muted);line-height:1.7;"><?= nl2br(htmlspecialchars($ord['product_desc'])) ?></div>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- ── Panel khusus Komputer ────────────────────── -->
        <?php if($orderType === 'komputer'): ?>
        <div class="section-block">
          <div class="section-header">
            <span><i class="fa fa-desktop" style="margin-right:8px;color:#f59e0b;"></i>Detail Servis Komputer</span>
          </div>
          <div class="section-body">
            <?php
            $kmMsg = [
                'pending'   => ['alert-info',    'Perangkat belum diterima. Konfirmasi pengiriman atau drop-off dari client.'],
                'verified'  => ['alert-info',    'Perangkat diterima. Akan segera masuk antrian servis.'],
                'scheduled' => ['alert-warning', 'Sedang dalam proses servis. Estimasi selesai diinformasikan ke client.'],
                'active'    => ['alert-success', 'Servis selesai. Perangkat siap diambil atau dikirim balik.'],
            ][$ord['wifi_status']] ?? null;
            if($kmMsg): ?>
            <div class="alert <?= $kmMsg[0] ?>"><i class="fa fa-desktop"></i> <?= $kmMsg[1] ?></div>
            <?php endif; ?>
            <?php if($ord['note']): ?>
            <div style="font-size:13px;color:var(--muted);line-height:1.7;margin-top:10px;"><?= nl2br(htmlspecialchars($ord['note'])) ?></div>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- ═══ [PATCH C.2] Panel Tagihan Bulan Ini (muncul tgl 10–21) ════════ -->
        <?php if($showPanelTagihan): ?>
        <div class="section-block" style="border-color:rgba(251,191,36,.35);">
          <div class="section-header" style="background:rgba(251,191,36,.06);">
            <span>
              <i class="fa fa-file-invoice-dollar" style="margin-right:8px;color:#fbbf24;"></i>
              Tagihan Bulan Ini
              <?php if($tagiBulanIni && $tagiBulanIni['status'] === 'waiting_confirm'): ?>
                <span class="badge badge-yellow" style="margin-left:8px;font-size:10px;">⏳ Menunggu Konfirmasi</span>
              <?php elseif($tagiBulanIni && $tagiBulanIni['status'] === 'paid'): ?>
                <span class="badge badge-green" style="margin-left:8px;font-size:10px;">✅ Lunas</span>
              <?php endif; ?>
            </span>
            <span style="font-size:11px;color:var(--muted);">
              Jatuh tempo: <strong style="color:#fbbf24;"><?= date('d M Y', strtotime($ord['tanggal_expire'])) ?></strong>
              &nbsp;·&nbsp;Suspend:
              <strong style="color:#f87171;">
                <?= date('d M Y', strtotime($ord['tanggal_expire'] . ' +1 day')) ?>
              </strong>
            </span>
          </div>
          <div class="section-body">

            <?php
            // ── Alert status tagihan ─────────────────────────────────────
            if (!$tagiBulanIni || $tagiBulanIni['status'] === 'unpaid'):
            ?>
              <div class="alert alert-warning">
                <i class="fa fa-hourglass-half"></i>
                <div>
                  <strong>Menunggu Pembayaran</strong><br>
                  Tagihan bulan ini belum dibayar. Batas pembayaran
                  <strong><?= date('d M Y', strtotime($ord['tanggal_expire'])) ?></strong>.
                  Layanan akan dinonaktifkan otomatis tanggal
                  <strong><?= date('d M Y', strtotime($ord['tanggal_expire'] . ' +1 day')) ?></strong>
                  jika belum dikonfirmasi.
                </div>
              </div>
            <?php elseif($tagiBulanIni['status'] === 'waiting_confirm'): ?>
              <div class="alert alert-warning" style="border-color:rgba(245,158,11,.4);background:rgba(245,158,11,.1);">
                <i class="fa fa-hourglass-half" style="color:#fbbf24;"></i>
                <div>
                  <strong style="color:#fbbf24;">Client Sudah Kirim Bukti Bayar</strong><br>
                  Periksa bukti di bawah lalu konfirmasi atau tolak. Setelah dikonfirmasi, expire akan diperpanjang otomatis.
                </div>
              </div>
            <?php elseif($tagiBulanIni['status'] === 'paid'): ?>
              <div class="alert alert-success">
                <i class="fa fa-check-circle"></i>
                Pembayaran bulan ini sudah dikonfirmasi.
                Expire diperpanjang ke <strong><?= $tagiBulanIni['new_expire'] ? date('d M Y', strtotime($tagiBulanIni['new_expire'])) : '–' ?></strong>.
              </div>
            <?php endif; ?>

            <!-- ── Preview Bukti Pembayaran Tagihan Bulan Ini ─────────────── -->
            <?php
            $proofBulanIni = $tagiBulanIni['payment_proof'] ?? null;
            if (!$proofBulanIni && $ord['payment_status'] === 'sudah_bayar') {
                $proofBulanIni = $ord['payment_proof'];
            }
            ?>
            <?php if($proofBulanIni): ?>
            <div style="margin-bottom:16px;">
              <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px;">
                <i class="fa fa-receipt" style="margin-right:6px;color:#fbbf24;"></i>Bukti Pembayaran Dikirim Client
              </div>
              <div class="proof-box">
                <?php $extPf = strtolower(pathinfo($proofBulanIni, PATHINFO_EXTENSION)); ?>
                <?php if(in_array($extPf, ['jpg','jpeg','png','gif','webp'])): ?>
                  <img src="/<?= htmlspecialchars($proofBulanIni) ?>" class="proof-img"
                       onclick="window.open(this.src,'_blank')" title="Klik untuk perbesar"
                       style="max-height:350px;width:100%;object-fit:contain;border-radius:8px;cursor:zoom-in;">
                  <div style="font-size:11px;color:var(--muted);margin-top:8px;">
                    <i class="fa fa-magnifying-glass-plus"></i> Klik gambar untuk zoom
                  </div>
                <?php else: ?>
                  <a href="/<?= htmlspecialchars($proofBulanIni) ?>" target="_blank" class="btn btn-secondary">
                    <i class="fa fa-file-arrow-down"></i> Unduh Bukti Bayar
                  </a>
                <?php endif; ?>
              </div>
            </div>
            <?php else: ?>
            <div class="proof-box" style="margin-bottom:16px;opacity:.55;">
              <i class="fa fa-image" style="font-size:32px;color:var(--muted);margin-bottom:8px;display:block;"></i>
              <div style="font-size:13px;color:var(--muted);">
                Belum ada bukti pembayaran untuk tagihan bulan ini.<br>
                <span style="font-size:11px;">Client akan upload melalui dashboard mereka.</span>
              </div>
            </div>
            <?php endif; ?>

            <!-- ── Tombol Aksi Admin ────────────────────────────────────── -->
            <?php if($tagiBulanIni && $tagiBulanIni['status'] === 'waiting_confirm'): ?>
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:4px;">
              <button class="btn btn-primary"
                      onclick="konfirmasiTagihanBulanan(<?= $tagiBulanIni['id'] ?>)"
                      style="background:linear-gradient(135deg,#22c55e,#16a34a);">
                <i class="fa fa-circle-check"></i> Konfirmasi & Perpanjang Layanan
              </button>
              <button class="btn btn-danger"
                      onclick="tolakTagihanBulanan(<?= $tagiBulanIni['id'] ?>)">
                <i class="fa fa-xmark-circle"></i> Tolak Bukti
              </button>
            </div>
            <?php
              // Hitung new_expire secara eksplisit: tgl 20 bulan depan dari tanggal_expire saat ini.
              // Gunakan new_expire dari DB jika sudah diisi cron, fallback hitung manual.
              if (!empty($tagiBulanIni['new_expire'])) {
                  $hintNewExpire = $tagiBulanIni['new_expire'];
              } else {
                  $hintExpTs    = strtotime($ord['tanggal_expire']);
                  $hintMon      = (int)date('n', $hintExpTs) + 1;
                  $hintYr       = (int)date('Y', $hintExpTs);
                  if ($hintMon > 12) { $hintMon = 1; $hintYr++; }
                  $hintNewExpire = sprintf('%04d-%02d-20', $hintYr, $hintMon);
              }
            ?>
            <div style="font-size:11px;color:var(--muted);margin-top:8px;">
              <i class="fa fa-circle-info" style="color:#60a5fa;"></i>
              Klik "Konfirmasi & Perpanjang" → expire otomatis dipindah ke
              <strong style="color:#fbbf24;">
                <?= date('d M Y', strtotime($hintNewExpire)) ?>
              </strong>
            </div>
            <div id="tagihan-bulanan-msg" style="display:none;margin-top:12px;"></div>

            <?php elseif(!$tagiBulanIni || $tagiBulanIni['status'] === 'unpaid'): ?>
            <div style="font-size:12px;color:var(--muted);padding:10px;background:var(--surface2);border-radius:8px;border:1px solid var(--border);">
              <i class="fa fa-clock" style="color:#fbbf24;"></i>
              Menunggu client upload bukti pembayaran. Setelah client upload, tombol konfirmasi akan muncul di sini.
            </div>
            <?php endif; ?>

          </div>
        </div>

        <!-- ═══ [PATCH C.3] Riwayat Tagihan Bulanan ═══════════════════════════ -->
        <?php if(!empty($riwayatTagihan)): ?>
        <div class="section-block">
          <div class="section-header">
            <span><i class="fa fa-clock-rotate-left" style="margin-right:8px;color:#fbbf24;"></i>Riwayat Tagihan Bulanan</span>
            <span style="font-size:11px;font-weight:600;color:var(--muted);"><?= count($riwayatTagihan) ?> entri</span>
          </div>
          <div class="section-body" style="padding:0;">
            <table style="width:100%;border-collapse:collapse;font-size:12px;">
              <thead>
                <tr style="border-bottom:1px solid var(--border);">
                  <th style="padding:10px 16px;text-align:left;font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;">Bulan Tagihan</th>
                  <th style="padding:10px 16px;text-align:left;font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;">Batas Bayar</th>
                  <th style="padding:10px 16px;text-align:left;font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;">Status</th>
                  <th style="padding:10px 16px;text-align:left;font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;">Expire Baru</th>
                  <th style="padding:10px 16px;text-align:left;font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;">Dikonfirmasi</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($riwayatTagihan as $rTag):
                  $statusTag = $rTag['status'];
                  $badgeTagMap = [
                    'unpaid'          => ['inv-unpaid','💳 Belum Bayar'],
                    'waiting_confirm' => ['inv-unpaid','⏳ Menunggu Konfirmasi'],
                    'paid'            => ['inv-paid',  '✅ Lunas'],
                    'suspended'       => ['badge-red', '🚫 Disuspend'],
                  ];
                  $badgeTag = $badgeTagMap[$statusTag] ?? ['inv-other','–'];
                ?>
                <tr style="border-bottom:1px solid var(--border);">
                  <td style="padding:10px 16px;font-weight:700;font-family:'JetBrains Mono',monospace;color:var(--text);">
                    <?= date('M Y', strtotime($rTag['tagihan_bulan'])) ?>
                  </td>
                  <td style="padding:10px 16px;color:var(--muted);">
                    <?= date('d M Y', strtotime($rTag['due_date'])) ?>
                  </td>
                  <td style="padding:10px 16px;">
                    <span class="inv-chip <?= $badgeTag[0] ?>"><?= $badgeTag[1] ?></span>
                  </td>
                  <td style="padding:10px 16px;font-family:'JetBrains Mono',monospace;font-size:11px;color:#fbbf24;">
                    <?= $rTag['new_expire'] ? date('d M Y', strtotime($rTag['new_expire'])) : '–' ?>
                  </td>
                  <td style="padding:10px 16px;color:var(--muted);font-size:11px;">
                    <?= $rTag['confirmed_at'] ? date('d M Y H:i', strtotime($rTag['confirmed_at'])) : '–' ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php endif; ?>

        <?php endif; /* $showPanelTagihan */ ?>

        <!-- ── Update Status & Aksi Admin (semua jenis) ───── -->
        <div class="section-block">
          <div class="section-header">
            <span><i class="fa fa-pen-to-square" style="margin-right:8px;color:var(--accent2);"></i>Aksi Admin</span>
          </div>
          <div class="section-body update-form">

            <div class="form-row">
              <div>
                <label class="form-lbl">Status Order</label>
                <select id="upd-status" class="form-ctrl">
                  <?php foreach($statusOpts as $sv => $sl): ?>
                    <option value="<?= $sv ?>" <?= $ord['wifi_status']===$sv?'selected':'' ?>><?= $sl ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div>
                <label class="form-lbl">Status Pembayaran</label>
                <select id="upd-payment" class="form-ctrl">
                  <option value="belum_bayar" <?= $ord['payment_status']==='belum_bayar'?'selected':'' ?>>💳 Belum Bayar</option>
                  <option value="sudah_bayar" <?= $ord['payment_status']==='sudah_bayar'?'selected':'' ?>>📤 Bukti Dikirim</option>
                  <option value="lunas"       <?= $ord['payment_status']==='lunas'      ?'selected':'' ?>>✅ Lunas</option>
                </select>
              </div>
            </div>

            <?php if(in_array($orderType,['wifi','cctv','website','komputer'])): ?>
            <div class="form-row">
              <div>
                <label class="form-lbl">
                  <?= ['wifi'=>'Jadwal Instalasi','cctv'=>'Jadwal Pemasangan','website'=>'Estimasi Selesai','komputer'=>'Estimasi Selesai Servis'][$orderType] ?? 'Jadwal' ?>
                </label>
                <input type="datetime-local" id="upd-jadwal" class="form-ctrl"
                  value="<?= $ord['jadwal_instalasi'] ? date('Y-m-d\TH:i', strtotime($ord['jadwal_instalasi'])) : '' ?>">
              </div>
              <div>
                <label class="form-lbl">Teknisi 1<?= $orderType==='website'?'/Developer':'' ?></label>
                <?php if(!empty($teknisiList)): ?>
                <select id="upd-teknisi" class="form-ctrl" onchange="checkTeknisiDuplicate()">
                  <option value="">– Pilih –</option>
                  <?php foreach($teknisiList as $tek): ?>
                    <option value="<?= $tek['id'] ?>" <?= $ord['teknisi_id']==$tek['id']?'selected':'' ?>>
                      <?= htmlspecialchars($tek['firstname'].' '.$tek['lastname']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <?php else: ?>
                <input type="text" class="form-ctrl" value="Belum ada teknisi" disabled style="opacity:.5;">
                <input type="hidden" id="upd-teknisi" value="">
                <?php endif; ?>
              </div>
            </div>
            <div class="form-row">
              <div></div><!-- spacer kiri -->
              <div>
                <label class="form-lbl">Teknisi 2 <span style="font-weight:400;color:var(--muted);">(opsional)</span></label>
                <?php if(!empty($teknisiList)): ?>
                <select id="upd-teknisi-2" class="form-ctrl" onchange="checkTeknisiDuplicate()">
                  <option value="">– Tidak ada –</option>
                  <?php foreach($teknisiList as $tek): ?>
                    <option value="<?= $tek['id'] ?>" <?= ($ord['teknisi_id_2'] ?? 0)==$tek['id']?'selected':'' ?>>
                      <?= htmlspecialchars($tek['firstname'].' '.$tek['lastname']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <?php else: ?>
                <input type="text" class="form-ctrl" value="Belum ada teknisi" disabled style="opacity:.5;">
                <input type="hidden" id="upd-teknisi-2" value="">
                <?php endif; ?>
              </div>
            </div>
            <?php else: ?>
            <input type="hidden" id="upd-jadwal" value="">
            <input type="hidden" id="upd-teknisi" value="">
            <input type="hidden" id="upd-teknisi-2" value="">
            <?php endif; ?>

            <div style="margin-bottom:14px;">
              <label class="form-lbl">Catatan Admin</label>
              <textarea id="upd-catatan" class="form-ctrl" placeholder="Tulis catatan untuk order ini…"><?= htmlspecialchars($ord['note'] ?? '') ?></textarea>
            </div>

            <?php if($orderType === 'wifi' && $ord['payment_status'] === 'sudah_bayar'): ?>
            <!-- ── Panel ID Pelanggan (muncul saat bukti sudah dikirim) ── -->
            <div id="panel-id-pelanggan" style="background:rgba(34,197,94,.06);border:1px solid rgba(34,197,94,.25);border-radius:10px;padding:18px;margin-bottom:16px;">
              <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px;">
                <div style="width:32px;height:32px;border-radius:50%;background:rgba(34,197,94,.15);border:1px solid rgba(34,197,94,.3);display:grid;place-items:center;color:#22c55e;font-size:13px;flex-shrink:0;">
                  <i class="fa fa-id-card"></i>
                </div>
                <div>
                  <div style="font-size:13px;font-weight:800;color:#22c55e;">Aktivasi Layanan WiFi</div>
                  <div style="font-size:11px;color:var(--muted);">Bukti pembayaran telah dikirim. Masukkan ID Pelanggan dari aplikasi e-billing untuk mengaktifkan layanan.</div>
                </div>
              </div>
              <div style="margin-bottom:12px;">
                <label class="form-lbl" style="color:#22c55e;">ID Pelanggan <span style="color:#f87171;font-size:12px;font-weight:400;">(wajib — dari aplikasi e-billing)</span></label>
                <div style="display:flex;gap:8px;align-items:center;">
                  <input type="text" id="input-id-pelanggan" class="form-ctrl"
                    placeholder="Contoh: 5501261417"
                    value="<?= htmlspecialchars($ord['id_pelanggan'] ?? '') ?>"
                    style="font-family:'JetBrains Mono',monospace;font-size:14px;font-weight:700;letter-spacing:.5px;border-color:rgba(34,197,94,.4);max-width:280px;">
                  <button class="btn btn-primary" onclick="saveIdPelanggan()"
                    style="background:linear-gradient(135deg,#22c55e,#16a34a);white-space:nowrap;">
                    <i class="fa fa-wifi"></i> Aktifkan & Kirim Email
                  </button>
                </div>
                <div style="font-size:11px;color:var(--muted);margin-top:6px;">
                  <i class="fa fa-circle-info" style="color:#60a5fa;margin-right:4px;"></i>
                  Setelah disimpan: status WiFi → <strong style="color:#22c55e;">Aktif</strong>, pembayaran → <strong>Lunas</strong>, dan email informasi akun dikirim otomatis ke klien.
                </div>
              </div>
              <div id="id-pelanggan-msg" style="display:none;margin-top:8px;"></div>
            </div>
            <?php elseif($orderType === 'wifi' && !empty($ord['id_pelanggan'])): ?>
            <!-- ── Tampilkan ID pelanggan jika sudah tersimpan ── -->
            <div style="background:rgba(34,197,94,.06);border:1px solid rgba(34,197,94,.2);border-radius:10px;padding:14px 18px;margin-bottom:16px;display:flex;align-items:center;gap:12px;">
              <i class="fa fa-id-card" style="color:#22c55e;font-size:20px;"></i>
              <div>
                <div style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px;">ID Pelanggan</div>
                <div style="font-family:'JetBrains Mono',monospace;font-size:16px;font-weight:800;color:#22c55e;letter-spacing:1px;"><?= htmlspecialchars($ord['id_pelanggan']) ?></div>
                <?php if(!empty($ord['tanggal_expire'])): ?>
                <div style="font-size:11px;color:var(--muted);margin-top:3px;">
                  Expire: <strong style="color:#fbbf24;"><?= date('d M Y', strtotime($ord['tanggal_expire'])) ?></strong>
                </div>
                <?php endif; ?>
              </div>
            </div>
            <?php endif; ?>

            <div class="action-row">
              <button class="btn btn-primary" onclick="updateOrder()">
                <i class="fa fa-floppy-disk"></i> Simpan Perubahan
              </button>
              <a href="https://wa.me/<?= preg_replace('/\D/','',$ord['phonenumber']) ?>?text=<?= urlencode('Halo '.$ord['client_name'].', kami dari Perkasa Solusindo mengenai order ('.$ord['order_number'].').') ?>"
                 target="_blank" class="btn btn-secondary">
                <i class="fab fa-whatsapp" style="color:#25d366;"></i> Chat WA
              </a>
              <a href="mailto:<?= htmlspecialchars($ord['email']) ?>?subject=Order+<?= $ord['order_number'] ?>" class="btn btn-secondary">
                <i class="fa fa-envelope"></i> Email
              </a>
              <a href="/admin/client_detail.php?id=<?= $ord['userid'] ?>" class="btn btn-secondary">
                <i class="fa fa-user"></i> Profil Klien
              </a>
            </div>

            <div id="upd-msg" style="display:none;margin-top:12px;"></div>
          </div>
        </div>

        <!-- ── Riwayat Status ──────────────────────────────── -->
        <div class="section-block">
          <div class="section-header">
            <span><i class="fa fa-clock-rotate-left" style="margin-right:8px;color:var(--accent);"></i>Riwayat Status</span>
            <span style="font-size:11px;font-weight:600;color:var(--muted);"><?= count($logs) ?> entri</span>
          </div>
          <div class="section-body">
            <?php if(empty($logs)): ?>
              <div style="padding:24px;text-align:center;color:var(--muted);font-size:13px;">Belum ada riwayat.</div>
            <?php else: ?>
            <div class="log-list">
              <?php
              $logColors = ['pending'=>'#fbbf24','verified'=>'#60a5fa','scheduled'=>'#818cf8','installed'=>'#34d399','active'=>'#34d399','cancelled'=>'#f87171'];
              foreach($logs as $log):
                $lc = $logColors[$log['new_status']] ?? '#7d8590';
              ?>
              <div class="log-item">
                <div class="log-dot" style="background:<?= $lc ?>;"></div>
                <div style="flex:1;">
                  <div class="log-status">
                    <?php if($log['old_status']): ?>
                      <span style="color:var(--muted);font-weight:400;"><?= htmlspecialchars(ucfirst($log['old_status'])) ?></span>
                      <i class="fa fa-arrow-right" style="font-size:9px;color:var(--muted);margin:0 6px;"></i>
                    <?php endif; ?>
                    <span style="color:<?= $lc ?>;"><?= htmlspecialchars(ucfirst($log['new_status'])) ?></span>
                  </div>
                  <div class="log-meta">
                    <?php $roleLabel=['admin'=>'Admin','owner'=>'Owner','teknisi'=>'Teknisi','client'=>'Klien','system'=>'System'][$log['role']]??$log['role']; ?>
                    oleh <strong><?= $log['actor_name'] ? htmlspecialchars($log['actor_name']) : '–' ?></strong>
                    (<?= $roleLabel ?>) · <?= date('d M Y H:i', strtotime($log['created_at'])) ?>
                  </div>
                  <?php if($log['catatan']): ?><div class="log-note">"<?= htmlspecialchars($log['catatan']) ?>"</div><?php endif; ?>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
        </div>

      </div><!-- /LEFT COLUMN -->

      <!-- ════ RIGHT COLUMN ════ -->
      <div>

        <!-- Kontak Klien -->
        <div class="section-block">
          <div class="section-header">
            <span><i class="fa fa-address-card" style="margin-right:8px;color:var(--accent2);"></i>Kontak Klien</span>
          </div>
          <div class="section-body">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
              <div style="width:48px;height:48px;border-radius:50%;background:linear-gradient(135deg,var(--accent2),#8b5cf6);display:grid;place-items:center;font-size:18px;font-weight:800;color:#fff;flex-shrink:0;">
                <?= strtoupper(substr($ord['firstname'],0,1)) ?>
              </div>
              <div>
                <div style="font-size:15px;font-weight:800;"><?= htmlspecialchars($ord['client_name']) ?></div>
                <?php if($ord['companyname']): ?><div style="font-size:12px;color:var(--muted);"><?= htmlspecialchars($ord['companyname']) ?></div><?php endif; ?>
              </div>
            </div>
            <div class="contact-actions">
              <a href="https://wa.me/<?= preg_replace('/\D/','',$ord['phonenumber']) ?>?text=<?= urlencode('Halo '.$ord['client_name'].', kami dari Perkasa Solusindo ingin menindaklanjuti order '.$ord['order_number'].'.') ?>"
                 target="_blank" class="contact-btn" style="background:rgba(37,211,102,.08);border-color:rgba(37,211,102,.2);">
                <i class="fab fa-whatsapp" style="color:#25d366;font-size:18px;"></i>
                <div><div>WhatsApp</div><div style="font-size:11px;color:var(--muted);font-weight:400;"><?= htmlspecialchars($ord['phonenumber']) ?></div></div>
              </a>
              <a href="mailto:<?= htmlspecialchars($ord['email']) ?>?subject=Perkasa+Solusindo+–+Order+<?= $ord['order_number'] ?>"
                 class="contact-btn" style="background:rgba(59,130,246,.08);border-color:rgba(59,130,246,.2);">
                <i class="fa fa-envelope" style="color:#60a5fa;font-size:18px;"></i>
                <div><div>Kirim Email</div><div style="font-size:11px;color:var(--muted);font-weight:400;"><?= htmlspecialchars($ord['email']) ?></div></div>
              </a>
              <a href="/admin/client_detail.php?id=<?= $ord['userid'] ?>"
                 class="contact-btn">
                <i class="fa fa-user-circle" style="color:var(--accent2);font-size:18px;"></i>
                <div><div>Lihat Profil Klien</div><div style="font-size:11px;color:var(--muted);font-weight:400;">ID #<?= $ord['userid'] ?></div></div>
              </a>
            </div>
          </div>
        </div>

        <!-- Ringkasan Order -->
        <div class="section-block">
          <div class="section-header">
            <span><i class="fa fa-receipt" style="margin-right:8px;color:var(--accent);"></i>Ringkasan Order</span>
          </div>
          <div class="section-body">
            <div class="info-row">
              <div class="info-row-icon"><i class="fa fa-hashtag"></i></div>
              <div>
                <div class="info-row-lbl">No. Order</div>
                <div style="font-family:'JetBrains Mono',monospace;font-size:13px;color:var(--accent2);"><?= htmlspecialchars($ord['order_number'] ?? '–') ?></div>
              </div>
            </div>
            <div class="info-row">
              <div class="info-row-icon"><i class="fa <?= $catI[0] ?>" style="color:<?= $catI[1] ?>;"></i></div>
              <div><div class="info-row-lbl">Jenis Layanan</div><div style="font-size:13px;"><?= $catI[2] ?></div></div>
            </div>
            <div class="info-row">
              <div class="info-row-icon"><i class="fa fa-box"></i></div>
              <div><div class="info-row-lbl">Produk</div><div style="font-size:13px;font-weight:600;"><?= htmlspecialchars($ord['product_name']) ?></div></div>
            </div>
            <div class="info-row">
              <div class="info-row-icon"><i class="fa fa-money-bill"></i></div>
              <div>
                <div class="info-row-lbl">Harga</div>
                <div style="font-size:14px;font-weight:700;font-family:'JetBrains Mono',monospace;">Rp <?= number_format($ord['price'],0,',','.') ?></div>
              </div>
            </div>
            <?php if($linkedInvoice): ?>
            <div class="info-row">
              <div class="info-row-icon"><i class="fa fa-file-invoice"></i></div>
              <div>
                <div class="info-row-lbl">Invoice</div>
                <span class="inv-chip <?= $linkedInvoice['status']==='Paid'?'inv-paid':'inv-unpaid' ?>">
                  #<?= $linkedInvoice['id'] ?> · <?= $linkedInvoice['status'] ?>
                </span>
                <div style="font-size:11px;color:var(--muted);margin-top:3px;">Rp <?= number_format($linkedInvoice['total'],0,',','.') ?></div>
              </div>
            </div>
            <?php endif; ?>
            <?php if($ord['tanggal_expire'] && $orderType === 'wifi'): ?>
            <div class="info-row">
              <div class="info-row-icon"><i class="fa fa-calendar-xmark" style="color:<?= strtotime($ord['tanggal_expire']) < strtotime(date('Y-m-d')) ? '#f87171' : '#fbbf24' ?>;"></i></div>
              <div>
                <div class="info-row-lbl">Tanggal Expire / Jatuh Tempo</div>
                <div style="font-size:14px;font-weight:800;font-family:'JetBrains Mono',monospace;
                            color:<?= strtotime($ord['tanggal_expire']) < strtotime(date('Y-m-d')) ? '#f87171' : '#fbbf24' ?>;">
                  <?= date('d M Y', strtotime($ord['tanggal_expire'])) ?>
                </div>
                <?php
                  $sisaHariDisplay = (int)ceil((strtotime($ord['tanggal_expire']) - time()) / 86400);
                  if ($sisaHariDisplay > 0): ?>
                  <div style="font-size:11px;color:var(--muted);margin-top:2px;">
                    <i class="fa fa-hourglass-half" style="color:#fbbf24;"></i>
                    <?= $sisaHariDisplay ?> hari lagi
                  </div>
                <?php elseif ($sisaHariDisplay === 0): ?>
                  <div style="font-size:11px;color:#f87171;font-weight:700;margin-top:2px;">
                    <i class="fa fa-triangle-exclamation"></i> Jatuh tempo HARI INI
                  </div>
                <?php else: ?>
                  <div style="font-size:11px;color:#f87171;font-weight:700;margin-top:2px;">
                    <i class="fa fa-triangle-exclamation"></i> Lewat <?= abs($sisaHariDisplay) ?> hari
                  </div>
                <?php endif; ?>
              </div>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Alur Kerja Admin (checklist) -->
        <div class="section-block">
          <div class="section-header">
            <span><i class="fa fa-list-check" style="margin-right:8px;color:var(--accent2);"></i>Panduan Aksi Admin</span>
          </div>
          <div class="section-body">
            <?php
            // Checklist sesuai jenis layanan & status
            $checklists = [];
            if ($orderType === 'hosting') {
                $checklists = [
                    ['done' => true,                                              'text' => 'Order masuk dari client'],
                    ['done' => $linkedInvoice !== null,                           'text' => 'Invoice diterbitkan'],
                    ['done' => $linkedInvoice && $linkedInvoice['status']==='Paid','text' => 'Pembayaran dikonfirmasi'],
                    ['done' => $hostingRow !== null,                              'text' => 'Hosting di-provisioning & aktif'],
                    ['done' => $ord['wifi_status'] === 'active',                  'text' => 'Kredensial dikirim ke client'],
                ];
            } elseif ($orderType === 'wifi') {
                $s = $ord['wifi_status'];
                $checklists = [
                    ['done' => true,                                        'text' => 'Order masuk dari client'],
                    ['done' => in_array($s,['verified','scheduled','installed','active']), 'text' => 'Data diverifikasi'],
                    ['done' => in_array($s,['scheduled','installed','active']),            'text' => 'Jadwal & teknisi ditetapkan'],
                    ['done' => in_array($s,['installed','active']),                        'text' => 'Instalasi selesai'],
                    ['done' => $s === 'active',                             'text' => 'Layanan aktif & client diberitahu'],
                ];
            } elseif ($orderType === 'website') {
                $s = $ord['wifi_status'];
                $checklists = [
                    ['done' => true,                                       'text' => 'Order & brief diterima'],
                    ['done' => in_array($s,['verified','scheduled','active']), 'text' => 'Brief disetujui & DP diterima'],
                    ['done' => in_array($s,['scheduled','active']),            'text' => 'Pengerjaan dimulai'],
                    ['done' => $s === 'active',                            'text' => 'Website selesai & diserahkan'],
                ];
            } else {
                $s = $ord['wifi_status'];
                $checklists = [
                    ['done' => true,                                              'text' => 'Order masuk'],
                    ['done' => in_array($s,['verified','scheduled','active']),    'text' => 'Diverifikasi'],
                    ['done' => in_array($s,['scheduled','active']),               'text' => 'Dalam proses'],
                    ['done' => $s === 'active',                                   'text' => 'Selesai & diserahkan'],
                ];
            }
            ?>
            <?php foreach($checklists as $ck): ?>
            <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--border);font-size:13px;">
              <div style="width:22px;height:22px;border-radius:50%;<?= $ck['done'] ? 'background:#10b981;' : 'background:var(--surface2);border:2px solid var(--border);' ?>;display:grid;place-items:center;flex-shrink:0;">
                <?php if($ck['done']): ?><i class="fa fa-check" style="color:#fff;font-size:10px;"></i><?php endif; ?>
              </div>
              <span style="<?= $ck['done'] ? 'color:var(--text);' : 'color:var(--muted);' ?>"><?= $ck['text'] ?></span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- ── Approve Hosting ─────────────────────────────── -->
        <?php if (($ord['order_type'] ?? '') === 'hosting' && ($hostingRow['da_status'] ?? '') !== 'active'): ?>
        <?php
            $payStatus = $ord['payment_status'] ?? 'belum_bayar';
            $approveReady = ($payStatus === 'lunas');
            $borderColor  = $approveReady ? 'rgba(34,197,94,.25)' : 'rgba(251,191,36,.3)';
            $headerColor  = $approveReady ? '#22c55e' : '#fbbf24';
        ?>
        <div class="section-block" style="border-color:<?= $borderColor ?>;">
          <div class="section-header" style="color:<?= $headerColor ?>;">
            <span><i class="fa fa-server" style="margin-right:8px;"></i>Aktivasi Hosting</span>
          </div>
          <div class="section-body">

            <?php if ($payStatus === 'belum_bayar'): ?>
            <!-- Blokir: belum ada bukti bayar -->
            <div class="alert alert-warning" style="margin-bottom:14px;">
              <i class="fa fa-triangle-exclamation"></i>
              <div>
                <strong>Menunggu Pembayaran Client</strong><br>
                Client belum mengupload bukti pembayaran. Tombol approve aktif setelah pembayaran dikonfirmasi.
              </div>
            </div>
            <button disabled
              style="background:#374151;color:#6b7280;border:none;padding:10px 20px;border-radius:8px;cursor:not-allowed;font-weight:700;width:100%;font-size:14px;">
              <i class="fa fa-lock"></i> Tunggu Pembayaran Client
            </button>

            <?php elseif ($payStatus === 'sudah_bayar'): ?>
            <!-- Blokir: bukti dikirim tapi belum dikonfirmasi admin -->
            <div class="alert alert-warning" style="border-color:rgba(96,165,250,.3);background:rgba(59,130,246,.07);margin-bottom:14px;">
              <i class="fa fa-hourglass-half" style="color:#60a5fa;"></i>
              <div>
                <strong style="color:#60a5fa;">Bukti Bayar Menunggu Konfirmasi</strong><br>
                Client sudah upload bukti. Konfirmasi pembayaran terlebih dahulu di panel <em>Bukti Pembayaran</em> sebelum mengaktifkan hosting.
              </div>
            </div>
            <button disabled
              style="background:#374151;color:#6b7280;border:none;padding:10px 20px;border-radius:8px;cursor:not-allowed;font-weight:700;width:100%;font-size:14px;">
              <i class="fa fa-lock"></i> Konfirmasi Pembayaran Dulu
            </button>

            <?php else: /* lunas — boleh approve */ ?>
            <!-- Aktif: pembayaran sudah lunas -->
            <div class="alert alert-success" style="margin-bottom:14px;">
              <i class="fa fa-circle-check"></i>
              <div>
                <strong>Pembayaran Terkonfirmasi</strong><br>
                Hosting siap diaktifkan. Klik tombol di bawah untuk membuat akun DirectAdmin, database MySQL, dan mengirimkan credential ke email client secara otomatis.
              </div>
            </div>
            <button id="btnApproveHosting" onclick="approveHosting(<?= (int)$orderId ?>)"
              style="background:linear-gradient(135deg,#22c55e,#16a34a);color:#fff;border:none;padding:10px 20px;border-radius:8px;cursor:pointer;font-weight:700;width:100%;font-size:14px;">
              <i class="fa fa-check"></i> Approve & Aktifkan Hosting
            </button>
            <div id="approveLog" style="margin-top:10px;font-size:13px;color:#94a3b8;line-height:1.8;"></div>
            <?php endif; ?>

          </div>
        </div>

        <script>
        function approveHosting(orderId) {
          if (!confirm(
            'Approve order ini?\n\n' +
            'Sistem akan otomatis:\n' +
            '✅ Buat akun di DirectAdmin\n' +
            '✅ Buat database MySQL\n' +
            '✅ Kirim credential ke email client\n\n' +
            '⚠️ Pastikan pembayaran sudah dikonfirmasi!'
          )) return;

          const btn = document.getElementById('btnApproveHosting');
          const log = document.getElementById('approveLog');
          btn.disabled = true;
          btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Memproses... (maks 30 detik)';

          fetch('/admin/approve_hosting.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'order_id=' + orderId
          })
          .then(r => r.json())
          .then(data => {
            if (data.ok) {
              btn.innerHTML = '✅ Hosting Aktif';
              btn.style.background = '#16a34a';
              if (data.log) log.innerHTML = data.log.join('<br>');
              alert('✅ ' + data.msg);
              setTimeout(() => location.reload(), 2000);
            } else {
              alert('❌ ' + data.msg);
              btn.disabled = false;
              btn.innerHTML = '<i class="fa fa-check"></i> Approve & Aktifkan Hosting';
            }
          })
          .catch(() => {
            alert('Koneksi gagal. Coba lagi.');
            btn.disabled = false;
            btn.innerHTML = '<i class="fa fa-check"></i> Approve & Aktifkan Hosting';
          });
        }
        </script>
        <?php endif; ?>

        <!-- Zona Bahaya -->
        <div class="section-block" style="border-color:rgba(239,68,68,.2);">
          <div class="section-header" style="color:#f87171;">
            <span><i class="fa fa-triangle-exclamation" style="margin-right:8px;"></i>Zona Bahaya</span>
          </div>
          <div class="section-body">
            <div style="display:flex;flex-direction:column;gap:8px;">
              <?php if($ord['wifi_status'] !== 'cancelled'): ?>
              <button class="btn btn-danger" style="width:100%;justify-content:center;" onclick="cancelOrder()">
                <i class="fa fa-ban"></i> Batalkan Order Ini
              </button>
              <?php else: ?>
              <button class="btn btn-secondary" style="width:100%;justify-content:center;" onclick="reopenOrder()">
                <i class="fa fa-rotate-left"></i> Buka Ulang Order
              </button>
              <?php endif; ?>
              <?php $ordType = $ord['order_type'] ?: ($ord['category'] ?? 'other'); ?>
              <a href="/admin/orders.php?type=<?= $ordType ?>" class="btn btn-secondary" style="width:100%;justify-content:center;">
                <i class="fa fa-list"></i> Semua Order <?= ucfirst($catI[2]) ?>
              </a>
            </div>
          </div>
        </div>

      </div><!-- /RIGHT COLUMN -->
    </div><!-- /od-grid -->

  </div><!-- /content -->
</main>

<!-- ═══════════ LOGOUT MODAL ═══════════ -->
<div id="logoutModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
  <div style="background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:32px;max-width:400px;width:90%;text-align:center;box-shadow:0 24px 60px rgba(0,0,0,.5);">
    <div style="width:60px;height:60px;border-radius:50%;background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.25);display:grid;place-items:center;margin:0 auto 18px;font-size:24px;color:#f87171;">
      <i class="fa fa-right-from-bracket"></i>
    </div>
    <h3 style="font-size:18px;font-weight:800;margin-bottom:8px;">Konfirmasi Logout</h3>
    <p style="font-size:13px;color:var(--muted);margin-bottom:26px;line-height:1.7;">Anda akan keluar dari sesi admin panel.</p>
    <div style="display:flex;gap:12px;justify-content:center;">
      <button onclick="closeLogoutModal()" class="btn btn-secondary" style="min-width:120px;padding:10px 18px;"><i class="fa fa-xmark"></i> Batal</button>
      <a href="/admin/logout.php" class="btn btn-danger" style="min-width:120px;padding:10px 18px;"><i class="fa fa-right-from-bracket"></i> Ya, Logout</a>
    </div>
  </div>
</div>

<!-- ═══════════ REJECT MODAL ═══════════ -->
<div id="rejectModal" style="display:none;position:fixed;inset:0;z-index:9998;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
  <div style="background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:28px;max-width:420px;width:90%;box-shadow:0 24px 60px rgba(0,0,0,.5);">
    <h3 style="font-size:16px;font-weight:800;margin-bottom:12px;color:#f87171;"><i class="fa fa-xmark-circle"></i> Tolak Bukti Pembayaran</h3>
    <p style="font-size:13px;color:var(--muted);margin-bottom:14px;">Berikan alasan penolakan agar client dapat memperbaikinya:</p>
    <textarea id="reject-reason" class="form-ctrl" style="margin-bottom:16px;" placeholder="Contoh: Bukti tidak jelas, nominal tidak sesuai, transfer ke rekening yang salah..."></textarea>
    <div style="display:flex;gap:10px;">
      <button onclick="closeRejectModal()" class="btn btn-secondary" style="flex:1;"><i class="fa fa-xmark"></i> Batal</button>
      <button onclick="submitReject()" class="btn btn-danger" style="flex:1;"><i class="fa fa-paper-plane"></i> Kirim Penolakan</button>
    </div>
  </div>
</div>

<script>
const ORDER_ID  = <?= $orderId ?>;
const ORDER_NUM = '<?= htmlspecialchars($ord['order_number'] ?? '') ?>';
const ORDER_TYPE = '<?= $orderType ?>';
const CLIENT_PHONE = '<?= preg_replace('/\D/','',$ord['phonenumber']) ?>';
const CLIENT_NAME  = '<?= addslashes($ord['client_name']) ?>';
let _rejectInvoiceId = null;

// ── Flash helper ─────────────────────────────────────────────
function showFlash(html) {
  const el = document.getElementById('flash-msg');
  el.innerHTML = html;
  el.style.display = 'block';
  window.scrollTo({top:0, behavior:'smooth'});
  setTimeout(() => el.style.display='none', 4000);
}
function showMsg(id, html) {
  const el = document.getElementById(id);
  el.innerHTML = html;
  el.style.display = 'block';
}

// ── Logout ───────────────────────────────────────────────────
function toggleSubMenu(e, groupId) {
  const group = document.getElementById(groupId);
  if (!group) return;
  const isOpen = group.classList.contains('open');
  if (isOpen) { e.preventDefault(); group.classList.remove('open'); e.currentTarget.classList.remove('expanded'); }
  else { group.classList.add('open'); e.currentTarget.classList.add('expanded'); }
}
function confirmLogout(e) { e.preventDefault(); document.getElementById('logoutModal').style.display='flex'; }
function closeLogoutModal() { document.getElementById('logoutModal').style.display='none'; }
document.getElementById('logoutModal').addEventListener('click', e=>{ if(e.target===document.getElementById('logoutModal')) closeLogoutModal(); });

// ── Update Order ─────────────────────────────────────────────
function updateOrder() {
  const tek1El = document.getElementById('upd-teknisi');
  const tek2El = document.getElementById('upd-teknisi-2');
  const tek1   = tek1El ? tek1El.value : '';
  const tek2   = tek2El ? tek2El.value : '';

  if (tek1 && tek2 && tek1 === tek2) {
    showMsg('upd-msg','<div class="alert alert-danger"><i class="fa fa-triangle-exclamation"></i> Teknisi 1 dan Teknisi 2 tidak boleh sama. Pilih teknisi yang berbeda.</div>');
    if(tek2El) tek2El.focus();
    return;
  }

  const body = new URLSearchParams({
    ajax_action:      'update_order',
    wifi_status:      document.getElementById('upd-status').value,
    payment_status:   document.getElementById('upd-payment').value,
    jadwal_instalasi: document.getElementById('upd-jadwal')?.value || '',
    teknisi_id:       document.getElementById('upd-teknisi')?.value || '',
    teknisi_id_2:     document.getElementById('upd-teknisi-2')?.value || '',
    catatan:          document.getElementById('upd-catatan').value,
  });
  const msgEl = document.getElementById('upd-msg');
  msgEl.style.display = 'none';

  fetch('', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body})
    .then(r=>r.json())
    .then(data=>{
      if(data.ok) {
        showMsg('upd-msg','<div class="alert alert-success"><i class="fa fa-check-circle"></i> Status berhasil diperbarui. Notifikasi dikirim ke klien.</div>');
        setTimeout(()=>location.reload(), 1600);
      } else {
        showMsg('upd-msg','<div class="alert alert-danger"><i class="fa fa-xmark-circle"></i> '+(data.msg||'Terjadi kesalahan.')+'</div>');
      }
    })
    .catch(()=>showMsg('upd-msg','<div class="alert alert-danger">Koneksi gagal.</div>'));
}

// ── Cancel / Reopen ──────────────────────────────────────────
function cancelOrder() {
  if(!confirm('Yakin ingin MEMBATALKAN order ini? Klien akan menerima notifikasi.')) return;
  document.getElementById('upd-status').value  = 'cancelled';
  document.getElementById('upd-catatan').value = 'Dibatalkan oleh admin.';
  updateOrder();
}
function reopenOrder() {
  if(!confirm('Buka ulang order ini?')) return;
  document.getElementById('upd-status').value = 'pending';
  updateOrder();
}

// ── Konfirmasi Pembayaran ────────────────────────────────────
function confirmPayment(invoiceId) {
  if(!confirm('Konfirmasi pembayaran client? Invoice akan ditandai PAID dan order diupdate ke LUNAS.')) return;
  fetch('', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({ajax_action:'confirm_payment', invoice_id: invoiceId})
  })
  .then(r=>r.json())
  .then(data=>{
    if(data.ok) {
      showFlash('<div class="alert alert-success"><i class="fa fa-check-circle"></i> Pembayaran dikonfirmasi. Invoice sudah PAID. Silakan lanjutkan provisioning hosting.</div>');
      setTimeout(()=>location.reload(), 2000);
    } else {
      showFlash('<div class="alert alert-danger">Gagal: '+(data.msg||'Error')+'</div>');
    }
  });
}

// ── Tolak Bukti Bayar ─────────────────────────────────────────
function rejectPayment(invoiceId) {
  _rejectInvoiceId = invoiceId;
  document.getElementById('rejectModal').style.display = 'flex';
}
function closeRejectModal() {
  document.getElementById('rejectModal').style.display = 'none';
  document.getElementById('reject-reason').value = '';
  _rejectInvoiceId = null;
}
function submitReject() {
  const alasan = document.getElementById('reject-reason').value.trim();
  if(!alasan) { alert('Isi alasan penolakan terlebih dahulu.'); return; }
  fetch('', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({ajax_action:'reject_payment', invoice_id:_rejectInvoiceId, alasan})
  })
  .then(r=>r.json())
  .then(data=>{
    closeRejectModal();
    if(data.ok) {
      showFlash('<div class="alert alert-danger"><i class="fa fa-xmark-circle"></i> Bukti pembayaran ditolak. Client sudah diberitahu.</div>');
      setTimeout(()=>location.reload(), 2000);
    }
  });
}
document.getElementById('rejectModal').addEventListener('click', e=>{ if(e.target===document.getElementById('rejectModal')) closeRejectModal(); });

// ── Aktivasi Hosting ─────────────────────────────────────────
function activateHosting() {
  const domain  = document.getElementById('prov-domain')?.value.trim();
  const duedate = document.getElementById('prov-duedate')?.value;
  const cpUser  = document.getElementById('prov-cpanel-user')?.value.trim();
  const cpPass  = document.getElementById('prov-cpanel-pass')?.value.trim();
  const ns1     = document.getElementById('prov-ns1')?.value.trim();
  const ns2     = document.getElementById('prov-ns2')?.value.trim();
  const note    = document.getElementById('prov-note')?.value.trim();

  if(!domain) { alert('Domain wajib diisi untuk aktivasi hosting.'); return; }
  if(!confirm(`Aktifkan hosting untuk domain "${domain}"? Status order akan berubah menjadi AKTIF.`)) return;

  const body = new URLSearchParams({
    ajax_action:     'update_order',
    wifi_status:     'active',
    payment_status:  'lunas',
    catatan:         note || `Hosting aktif. Domain: ${domain}. cPanel: ${cpUser}`,
    hosting_domain:  domain,
    hosting_duedate: duedate,
    hosting_pkgid:   '<?= $ord["product_id"] ?>',
  });

  fetch('', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body})
    .then(r=>r.json())
    .then(data=>{
      if(data.ok) {
        showFlash('<div class="alert alert-success"><i class="fa fa-server"></i> Hosting berhasil diaktifkan! Kirim kredensial ke client.</div>');
        sendCredentialsSilent(domain, cpUser, cpPass, ns1, ns2);
        setTimeout(()=>location.reload(), 2500);
      } else {
        showFlash('<div class="alert alert-danger">Gagal: '+(data.msg||'Error')+'</div>');
      }
    });
}

// ── Kirim Kredensial via WA ───────────────────────────────────
function sendCredentials() {
  const domain = document.getElementById('prov-domain')?.value.trim() || '<?= $hostingRow ? htmlspecialchars($hostingRow["domain"]) : "" ?>';
  const cpUser = document.getElementById('prov-cpanel-user')?.value.trim() || '';
  const cpPass = document.getElementById('prov-cpanel-pass')?.value.trim() || '';
  sendCredentialsSilent(domain, cpUser, cpPass);
}
function sendCredentialsSilent(domain, cpUser, cpPass, ns1, ns2) {
  if(!CLIENT_PHONE) return;
  const ns1txt = ns1 || 'ns1.perkasasolusindo.com';
  const ns2txt = ns2 || 'ns2.perkasasolusindo.com';
  const msg = `Halo ${CLIENT_NAME} 👋\n\n` +
    `Hosting Anda untuk order *${ORDER_NUM}* sudah aktif!\n\n` +
    `🌐 *Domain:* ${domain}\n` +
    (cpUser ? `👤 *cPanel Username:* ${cpUser}\n` : '') +
    (cpPass ? `🔑 *cPanel Password:* ${cpPass}\n` : '') +
    `📡 *Nameserver 1:* ${ns1txt}\n` +
    `📡 *Nameserver 2:* ${ns2txt}\n\n` +
    `Akses cPanel: http://${domain}/cpanel\n\n` +
    `Segera ganti password setelah login pertama.\n` +
    `Hubungi kami jika ada pertanyaan. Terima kasih 🙏\n` +
    `— Tim Perkasa Solusindo`;
  window.open('https://wa.me/' + CLIENT_PHONE + '?text=' + encodeURIComponent(msg), '_blank');
}

// ── Suspend Hosting ───────────────────────────────────────────
function suspendHosting() {
  if(!confirm('Suspend hosting client ini?')) return;
  document.getElementById('upd-status').value  = 'cancelled';
  document.getElementById('upd-catatan').value = 'Hosting disuspend oleh admin.';
  updateOrder();
}

// ── Cegah teknisi 1 & 2 sama ─────────────────────────────────
function checkTeknisiDuplicate() {
  const tek1El  = document.getElementById('upd-teknisi');
  const tek2El  = document.getElementById('upd-teknisi-2');
  const msgEl   = document.getElementById('upd-msg');
  if (!tek1El || !tek2El) return;

  const tek1 = tek1El.value;
  const tek2 = tek2El.value;

  if (tek1 && tek2 && tek1 === tek2) {
    tek2El.style.borderColor = 'rgba(239,68,68,.6)';
    showMsg('upd-msg','<div class="alert alert-danger"><i class="fa fa-triangle-exclamation"></i> Teknisi 1 dan Teknisi 2 tidak boleh sama.</div>');
  } else {
    tek2El.style.borderColor = '';
    if (msgEl.innerHTML.includes('tidak boleh sama')) msgEl.style.display = 'none';
  }
}

document.addEventListener('keydown', e=>{ if(e.key==='Escape'){ closeLogoutModal(); closeRejectModal(); closeRejectMonthlyModal(); } });

// ── Simpan ID Pelanggan & aktifkan WiFi ───────────────────────
function saveIdPelanggan() {
  const idPel = document.getElementById('input-id-pelanggan')?.value.trim();
  if (!idPel) {
    showMsg('id-pelanggan-msg','<div class="alert alert-danger"><i class="fa fa-triangle-exclamation"></i> ID Pelanggan wajib diisi sebelum mengaktifkan layanan.</div>');
    return;
  }
  if (!confirm(`Aktifkan layanan WiFi dengan ID Pelanggan "${idPel}"?\n\nStatus WiFi akan berubah menjadi AKTIF dan email informasi akun akan dikirim ke klien.`)) return;

  const btn = event.currentTarget;
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Memproses…';

  fetch('', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({ajax_action:'save_id_pelanggan', id_pelanggan: idPel})
  })
  .then(r => r.json())
  .then(data => {
    btn.disabled = false;
    btn.innerHTML = '<i class="fa fa-wifi"></i> Aktifkan & Kirim Email';
    if (data.ok) {
      showMsg('id-pelanggan-msg',
        `<div class="alert alert-success"><i class="fa fa-circle-check"></i> Layanan WiFi berhasil diaktifkan! Email informasi akun dikirim ke klien. Expire: <strong>${data.tanggal_expire}</strong></div>`
      );
      document.getElementById('id-pelanggan-msg').style.display = 'block';
      setTimeout(() => location.reload(), 2200);
    } else {
      showMsg('id-pelanggan-msg',
        `<div class="alert alert-danger"><i class="fa fa-xmark-circle"></i> ${data.msg || 'Terjadi kesalahan.'}</div>`
      );
    }
  })
  .catch(() => {
    btn.disabled = false;
    btn.innerHTML = '<i class="fa fa-wifi"></i> Aktifkan & Kirim Email';
    showMsg('id-pelanggan-msg','<div class="alert alert-danger">Koneksi gagal. Coba lagi.</div>');
  });
}
</script>

<!-- ═══ [PATCH C.4] Modal Tolak Tagihan Bulanan ═══════════════════════════ -->
<div id="rejectMonthlyModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:9999;align-items:center;justify-content:center;">
  <div style="background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:28px;width:100%;max-width:440px;margin:20px;">
    <div style="font-size:15px;font-weight:800;margin-bottom:16px;color:#f87171;">
      <i class="fa fa-xmark-circle" style="margin-right:8px;"></i>Tolak Bukti Pembayaran Tagihan
    </div>
    <div style="margin-bottom:14px;">
      <label class="form-lbl">Alasan Penolakan</label>
      <textarea id="reject-monthly-reason" class="form-ctrl" rows="3" placeholder="Contoh: Bukti tidak terbaca, nominal tidak sesuai, dll."></textarea>
    </div>
    <div style="display:flex;gap:10px;justify-content:flex-end;">
      <button class="btn btn-secondary" onclick="closeRejectMonthlyModal()">Batal</button>
      <button class="btn btn-danger" onclick="submitRejectMonthly()">
        <i class="fa fa-xmark-circle"></i> Tolak
      </button>
    </div>
  </div>
</div>

<!-- ═══ [PATCH C.5] JavaScript: Tagihan Bulanan ═══════════════════════════ -->
<script>
// ── Konfirmasi tagihan bulanan ──────────────────────────────────
function konfirmasiTagihanBulanan(monthlyId) {
  if(!confirm('Konfirmasi pembayaran?\n\nSetelah dikonfirmasi, expire layanan akan diperpanjang otomatis +1 bulan dan status tagihan berubah ke LUNAS.')) return;

  const btn = event.currentTarget;
  btn.disabled = true;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Memproses…';

  fetch('', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({ajax_action:'confirm_monthly_payment', monthly_id: monthlyId})
  })
  .then(r => r.json())
  .then(data => {
    btn.disabled = false;
    btn.innerHTML = '<i class="fa fa-circle-check"></i> Konfirmasi & Perpanjang Layanan';
    const msgEl = document.getElementById('tagihan-bulanan-msg');
    if (data.ok) {
      msgEl.style.display = 'block';
      msgEl.innerHTML = `<div class="alert alert-success"><i class="fa fa-circle-check"></i>
        Pembayaran dikonfirmasi! Layanan diperpanjang hingga <strong>${data.new_expire}</strong>.</div>`;
      setTimeout(() => location.reload(), 2200);
    } else {
      msgEl.style.display = 'block';
      msgEl.innerHTML = `<div class="alert alert-danger"><i class="fa fa-xmark-circle"></i> ${data.msg || 'Terjadi kesalahan.'}</div>`;
    }
  })
  .catch(() => {
    btn.disabled = false;
    btn.innerHTML = '<i class="fa fa-circle-check"></i> Konfirmasi & Perpanjang Layanan';
  });
}

// ── Tolak bukti tagihan bulanan ────────────────────────────────
let _rejectMonthlyId = null;
function tolakTagihanBulanan(monthlyId) {
  _rejectMonthlyId = monthlyId;
  document.getElementById('rejectMonthlyModal').style.display = 'flex';
}
function closeRejectMonthlyModal() {
  document.getElementById('rejectMonthlyModal').style.display = 'none';
  document.getElementById('reject-monthly-reason').value = '';
  _rejectMonthlyId = null;
}
function submitRejectMonthly() {
  const alasan = document.getElementById('reject-monthly-reason').value.trim();
  if(!alasan) { alert('Isi alasan penolakan terlebih dahulu.'); return; }
  fetch('', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({ajax_action:'reject_monthly_payment', monthly_id:_rejectMonthlyId, alasan})
  })
  .then(r => r.json())
  .then(data => {
    closeRejectMonthlyModal();
    if(data.ok) {
      showFlash('<div class="alert alert-danger"><i class="fa fa-xmark-circle"></i> Bukti tagihan ditolak. Client sudah diberitahu untuk upload ulang.</div>');
      setTimeout(() => location.reload(), 2000);
    }
  });
}
document.getElementById('rejectMonthlyModal').addEventListener('click', e => {
  if(e.target === document.getElementById('rejectMonthlyModal')) closeRejectMonthlyModal();
});
</script>
</body>
</html>
