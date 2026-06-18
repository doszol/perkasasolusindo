<?php
// =====================================================
//  teknisi/teknisi_dashboard.php
//  Hanya untuk level 4 (Teknisi)
// =====================================================
require_once __DIR__ . '/../auth_check.php';
requireLevel(4);

require_once __DIR__ . '/../config.php';

$teknisi_id = (int)$_SESSION['user_id'];

// ── Ambil data teknisi ────────────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT id, firstname, lastname, email, phonenumber, address1, city, level, foto_ktp
    FROM tblclients WHERE id = ? AND level = 4 AND status = 1
");
$stmt->bind_param("i", $teknisi_id);
$stmt->execute();
$teknisi = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$teknisi) {
    session_destroy();
    header('Location: ../login/login.php');
    exit();
}
$nama_lengkap = htmlspecialchars(trim($teknisi['firstname'] . ' ' . $teknisi['lastname']));

// ── Ambil notifikasi belum dibaca milik teknisi ini ───────────────────────
$stmt = $conn->prepare("
    SELECT n.*, o.order_number
    FROM tblnotifikasi n
    LEFT JOIN tblorders o ON n.order_id = o.id
    WHERE n.userid = ?
    ORDER BY n.created_at DESC
    LIMIT 20
");
$stmt->bind_param("i", $teknisi_id);
$stmt->execute();
$notifikasi_all = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$notif_belum_dibaca = array_filter($notifikasi_all, function($n) { return $n['sudah_dibaca'] == 0; });
$unread_count = count($notif_belum_dibaca);

// ── Mark notif sebagai dibaca (AJAX) ──────────────────────────────────────
if (isset($_GET['mark_read']) && isset($_GET['notif_id'])) {
    $nid = (int)$_GET['notif_id'];
    $stmt = $conn->prepare("UPDATE tblnotifikasi SET sudah_dibaca=1 WHERE id=? AND userid=?");
    $stmt->bind_param("ii", $nid, $teknisi_id);
    $stmt->execute();
    $stmt->close();
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit();
}

// ── Mark semua notif sebagai dibaca ──────────────────────────────────────
if (isset($_POST['mark_all_read'])) {
    $stmt = $conn->prepare("UPDATE tblnotifikasi SET sudah_dibaca=1 WHERE userid=?");
    $stmt->bind_param("i", $teknisi_id);
    $stmt->execute();
    $stmt->close();
    header('Location: teknisi_dashboard.php');
    exit();
}

// ── Ambil semua orders yang ditugaskan ke teknisi ini ─────────────────────
$stmt = $conn->prepare("
    SELECT
        o.id, o.order_number, o.wifi_status, o.payment_status,
        o.alamat_pasang, o.rt, o.rw, o.kelurahan, o.kecamatan,
        o.kota, o.provinsi, o.jadwal_instalasi, o.tgl_aktif,
        o.note, o.created_at, o.updated_at,
        o.teknisi_id, o.teknisi_id_2,
        p.name  AS nama_paket, p.speed AS kecepatan, p.price AS harga,
        c.firstname AS client_first, c.lastname AS client_last,
        c.email AS client_email, c.phonenumber AS client_hp,
        t1.firstname AS teknisi1_first, t1.lastname AS teknisi1_last,
        t2.firstname AS teknisi2_first, t2.lastname AS teknisi2_last
    FROM tblorders o
    LEFT JOIN tblproducts p  ON o.productid    = p.id
    LEFT JOIN tblclients  c  ON o.userid        = c.id
    LEFT JOIN tblclients  t1 ON o.teknisi_id    = t1.id
    LEFT JOIN tblclients  t2 ON o.teknisi_id_2  = t2.id
    WHERE o.teknisi_id = ? OR o.teknisi_id_2 = ?
    ORDER BY
        FIELD(o.wifi_status,'scheduled','verified','pending','installed','active','cancelled'),
        o.jadwal_instalasi ASC,
        o.created_at DESC
");
$stmt->bind_param("ii", $teknisi_id, $teknisi_id);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Statistik ─────────────────────────────────────────────────────────────
$stat = ['total' => count($orders), 'scheduled' => 0, 'installed' => 0, 'pending' => 0, 'active' => 0];
foreach ($orders as $o) {
    $s = $o['wifi_status'];
    if (isset($stat[$s])) $stat[$s]++;
}

// ── Ambil riwayat status log untuk detail order ───────────────────────────
$detail_order = null;
$status_logs  = [];
if (isset($_GET['order_id'])) {
    $oid = (int)$_GET['order_id'];
    $stmt = $conn->prepare("
        SELECT
            o.*, p.name AS nama_paket, p.speed AS kecepatan,
            p.price AS harga, p.description AS deskripsi_paket,
            c.firstname AS client_first, c.lastname AS client_last,
            c.email AS client_email, c.phonenumber AS client_hp,
            c.address1 AS client_addr,
            t1.firstname AS teknisi1_first, t1.lastname AS teknisi1_last, t1.phonenumber AS hp_t1,
            t2.firstname AS teknisi2_first, t2.lastname AS teknisi2_last, t2.phonenumber AS hp_t2
        FROM tblorders o
        LEFT JOIN tblproducts p  ON o.productid   = p.id
        LEFT JOIN tblclients  c  ON o.userid       = c.id
        LEFT JOIN tblclients  t1 ON o.teknisi_id   = t1.id
        LEFT JOIN tblclients  t2 ON o.teknisi_id_2 = t2.id
        WHERE o.id = ? AND (o.teknisi_id = ? OR o.teknisi_id_2 = ?)
    ");
    $stmt->bind_param("iii", $oid, $teknisi_id, $teknisi_id);
    $stmt->execute();
    $detail_order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($detail_order) {
        $stmt = $conn->prepare("
            SELECT l.*, c.firstname, c.lastname, c.level
            FROM tblorder_status_logs l
            LEFT JOIN tblclients c ON l.changed_by = c.id
            WHERE l.order_id = ?
            ORDER BY l.created_at ASC
        ");
        $stmt->bind_param("i", $oid);
        $stmt->execute();
        $status_logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

// ── Update catatan teknisi (POST) ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_catatan'])) {
    $oid     = (int)$_POST['order_id'];
    $catatan = trim($_POST['catatan']);
    $stmt = $conn->prepare("
        UPDATE tblorders SET note = ?, updated_at = NOW()
        WHERE id = ? AND (teknisi_id = ? OR teknisi_id_2 = ?)
    ");
    $stmt->bind_param("siii", $catatan, $oid, $teknisi_id, $teknisi_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("
        INSERT INTO tblorder_status_logs (order_id, old_status, new_status, changed_by, role, catatan)
        SELECT id, wifi_status, wifi_status, ?, 'teknisi', ?
        FROM tblorders WHERE id = ?
    ");
    $stmt->bind_param("isi", $teknisi_id, $catatan, $oid);
    $stmt->execute();
    $stmt->close();

    header("Location: teknisi_dashboard.php?order_id=$oid&saved=1");
    exit();
}

// ── Upload bukti pembayaran oleh teknisi (POST) ────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_bukti_teknisi'])) {
    $oid = (int)$_POST['order_id'];

    // Pastikan order ini milik teknisi ini
    $stmt = $conn->prepare("
        SELECT o.id, o.order_number, o.payment_status, o.userid,
               c.firstname AS client_first, c.lastname AS client_last, c.email AS client_email
        FROM tblorders o
        LEFT JOIN tblclients c ON o.userid = c.id
        WHERE o.id = ? AND (o.teknisi_id = ? OR o.teknisi_id_2 = ?)
        LIMIT 1
    ");
    $stmt->bind_param("iii", $oid, $teknisi_id, $teknisi_id);
    $stmt->execute();
    $chk = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($chk && isset($_FILES['bukti_file']) && $_FILES['bukti_file']['error'] === UPLOAD_ERR_OK) {
        $allowed  = ['image/jpeg','image/png','image/webp','application/pdf'];
        $ftype    = mime_content_type($_FILES['bukti_file']['tmp_name']);
        $fsize    = $_FILES['bukti_file']['size'];

        if (in_array($ftype, $allowed) && $fsize <= 5 * 1024 * 1024) {
            $ext      = pathinfo($_FILES['bukti_file']['name'], PATHINFO_EXTENSION);
            $filename = 'bukti_' . $chk['order_number'] . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $dest_dir = __DIR__ . '/../order/order_asset/bukti_pembayaran/';
            if (!is_dir($dest_dir)) mkdir($dest_dir, 0755, true);
            $dest = $dest_dir . $filename;

            if (move_uploaded_file($_FILES['bukti_file']['tmp_name'], $dest)) {
                // Update order
                $stmt = $conn->prepare("
                    UPDATE tblorders
                    SET payment_status = 'sudah_bayar', payment_proof = ?, updated_at = NOW()
                    WHERE id = ? AND (teknisi_id = ? OR teknisi_id_2 = ?)
                ");
                $stmt->bind_param("siii", $filename, $oid, $teknisi_id, $teknisi_id);
                $stmt->execute();
                $stmt->close();

                // Log
                $stmt = $conn->prepare("
                    INSERT INTO tblorder_status_logs
                        (order_id, old_status, new_status, changed_by, role, catatan)
                    SELECT id, wifi_status, wifi_status, ?, 'teknisi', 'Teknisi mengupload bukti pembayaran atas nama client.'
                    FROM tblorders WHERE id = ?
                ");
                $stmt->bind_param("ii", $teknisi_id, $oid);
                $stmt->execute();
                $stmt->close();

                // Notif in-app ke admin/owner
                require_once __DIR__ . '/../mailer.php';
                $stmt = $conn->prepare("SELECT id, email, firstname FROM tblclients WHERE level IN (1,2) AND status = 1");
                $stmt->execute();
                $admins2 = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();

                $nama_cl    = trim($chk['client_first'] . ' ' . $chk['client_last']);
                $judul_adm2 = "Konfirmasi Pembayaran (via Teknisi) — #{$chk['order_number']}";
                $pesan_adm2 = "Teknisi mengupload bukti pembayaran untuk order #{$chk['order_number']} atas nama client {$nama_cl}. Silakan periksa dan verifikasi.";

                $stmt = $conn->prepare("INSERT INTO tblnotifikasi (userid, order_id, judul, pesan, tipe) VALUES (?, ?, ?, ?, 'info')");
                foreach ($admins2 as $adm) {
                    $stmt->bind_param("iiss", $adm['id'], $oid, $judul_adm2, $pesan_adm2);
                    $stmt->execute();
                }
                $stmt->close();

                // Notif ke client juga
                $judul_cl2 = "✅ Bukti Pembayaran Diupload — #{$chk['order_number']}";
                $pesan_cl2 = "Teknisi telah mengupload bukti pembayaran untuk order #{$chk['order_number']} atas nama Anda. Admin akan segera memverifikasi.";
                $stmt = $conn->prepare("INSERT INTO tblnotifikasi (userid, order_id, judul, pesan, tipe) VALUES (?, ?, ?, ?, 'sukses')");
                $stmt->bind_param("iiss", $chk['userid'], $oid, $judul_cl2, $pesan_cl2);
                $stmt->execute();
                $stmt->close();

                header("Location: teknisi_dashboard.php?order_id={$oid}&bukti_ok=1");
                exit();
            }
        }
        header("Location: teknisi_dashboard.php?order_id={$oid}&err=upload_failed");
        exit();
    }
    header("Location: teknisi_dashboard.php?order_id={$oid}&err=upload_failed");
    exit();
}

// ── Flag redirect selesai / error ─────────────────────────────────────────
$selesai_ok  = isset($_GET['selesai']);
$err_code    = $_GET['err']  ?? '';
$err_msg_raw = $_GET['msg']  ?? '';

// ── Helpers ───────────────────────────────────────────────────────────────
function wifiStatusBadge($s) {
    $map = [
        'pending'   => ['Menunggu Verif', 'badge-pending'],
        'verified'  => ['Diverifikasi',   'badge-verified'],
        'scheduled' => ['Terjadwal',      'badge-scheduled'],
        'installed' => ['Terpasang',      'badge-installed'],
        'active'    => ['Aktif',          'badge-active'],
        'cancelled' => ['Dibatalkan',     'badge-cancel'],
    ];
    [$label, $cls] = $map[$s] ?? [ucfirst($s), 'badge-pending'];
    return "<span class=\"badge $cls\">$label</span>";
}
function payBadge($s) {
    $map = [
        'belum_bayar' => ['Belum Bayar', 'badge-pending'],
        'sudah_bayar' => ['Sudah Bayar', 'badge-verified'],
        'lunas'       => ['Lunas',       'badge-active'],
    ];
    [$label, $cls] = $map[$s] ?? [ucfirst($s), 'badge-pending'];
    return "<span class=\"badge $cls\">$label</span>";
}
function rp($n)  { return 'Rp ' . number_format((float)$n, 0, ',', '.'); }
function tgl($d) { return $d ? date('d M Y, H:i', strtotime($d)) : '—'; }
function tglShort($d) { return $d ? date('d M Y', strtotime($d)) : '—'; }
function namaLengkap($first, $last) { return htmlspecialchars(trim($first . ' ' . $last)); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Portal Teknisi — <?= $nama_lengkap ?></title>
<link rel="icon" type="image/png" href="/../assets/images/CDR LOGO PERKASA Putih with border.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/teknisi/style_teknisi.css">
</head>
<body>

<div class="layout">

<!-- ═══════════════ SIDEBAR ═══════════════ -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="logo-mark">
      <div class="logo-icon">P</div>
      <div class="logo-text">
        Perkasa Solusindo
        <span>Portal Teknisi</span>
      </div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-label">Menu</div>
    <a href="teknisi_dashboard.php" class="nav-item <?= !isset($_GET['order_id']) ? 'active' : '' ?>">
      <i class="fa fa-gauge-high"></i> Dashboard
    </a>
    <button class="nav-item" onclick="scrollToOrders()">
      <i class="fa fa-list-check"></i> Daftar Order
      <?php if ($stat['scheduled'] > 0): ?>
        <span class="nav-badge"><?= $stat['scheduled'] ?></span>
      <?php endif; ?>
    </button>

    <div class="nav-label">Jadwal</div>
    <a href="teknisi_dashboard.php" class="nav-item">
      <i class="fa fa-calendar-days"></i> Jadwal Hari Ini
      <?php
        $today_count = count(array_filter($orders, function($o) {
            return $o['jadwal_instalasi'] && date('Y-m-d', strtotime($o['jadwal_instalasi'])) === date('Y-m-d');
        }));
        if ($today_count > 0):
      ?>
        <span class="nav-badge"><?= $today_count ?></span>
      <?php endif; ?>
    </a>
  </nav>

  <div class="sidebar-footer">
    <div class="sidebar-profile">
      <div class="avatar"><?= strtoupper(substr($teknisi['firstname'], 0, 1)) ?></div>
      <div class="profile-info">
        <div class="profile-name"><?= $nama_lengkap ?></div>
        <div class="profile-role">Teknisi</div>
      </div>
      <a href="#" class="btn-logout" onclick="confirmLogout(event)" title="Logout">
        <i class="fa fa-right-from-bracket"></i>
      </a>
    </div>
  </div>
</aside>

<!-- Sidebar overlay (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- ═══════════════ MAIN ═══════════════ -->
<div class="main">

  <!-- Topbar -->
  <header class="topbar">
    <button class="topbar-menu-btn" onclick="toggleSidebar()" title="Menu">
      <i class="fa fa-bars"></i>
    </button>
    <div class="page-title">
      <?= isset($_GET['order_id']) ? 'Detail Order' : 'Dashboard Teknisi' ?>
    </div>
    <div class="topbar-right">
      <span class="date-badge">
        <i class="fa fa-calendar-days" style="margin-right:5px;"></i>
        <?= date('d M Y') ?>
      </span>

      <!-- Notif bell -->
      <div class="notif-wrapper">
        <button class="topbar-btn" id="notifBtn" onclick="toggleNotif(event)" title="Notifikasi">
          <i class="fa fa-bell"></i>
          <?php if ($unread_count > 0): ?>
            <span class="notif-dot"></span>
          <?php endif; ?>
        </button>
        <div class="notif-dropdown" id="notifDropdown">
          <div class="notif-hdr">
            <span class="notif-hdr-title">
              <i class="fa fa-bell" style="color:var(--accent2);margin-right:6px;"></i>
              Notifikasi
              <?php if ($unread_count > 0): ?>
                <span class="notif-count-badge"><?= $unread_count ?></span>
              <?php endif; ?>
            </span>
            <?php if ($unread_count > 0): ?>
              <form method="POST" style="display:inline">
                <button type="submit" name="mark_all_read" class="notif-read-all">Tandai semua dibaca</button>
              </form>
            <?php endif; ?>
          </div>
          <div class="notif-list">
            <?php if (empty($notifikasi_all)): ?>
              <div class="notif-empty"><i class="fa fa-bell-slash" style="display:block;font-size:22px;margin-bottom:8px;"></i>Tidak ada notifikasi.</div>
            <?php else: foreach ($notifikasi_all as $n):
              $icons = ['info' => 'fa-info', 'sukses' => 'fa-check', 'peringatan' => 'fa-triangle-exclamation', 'error' => 'fa-xmark'];
              $icon  = $icons[$n['tipe']] ?? 'fa-bell';
              $diffSec = time() - strtotime($n['created_at']);
              if ($diffSec < 3600)      $timeStr = round($diffSec/60).' mnt lalu';
              elseif ($diffSec < 86400) $timeStr = round($diffSec/3600).' jam lalu';
              else                      $timeStr = tgl($n['created_at']);
            ?>
            <div class="notif-item <?= $n['sudah_dibaca'] == 0 ? 'unread' : '' ?>"
                 onclick="markRead(<?= $n['id'] ?>, <?= $n['order_id'] ?? 'null' ?>, this)">
              <div class="notif-icon <?= htmlspecialchars($n['tipe']) ?>">
                <i class="fa <?= $icon ?>"></i>
              </div>
              <div>
                <div class="notif-title"><?= htmlspecialchars($n['judul']) ?></div>
                <div class="notif-msg"><?= htmlspecialchars(mb_substr($n['pesan'], 0, 80)) ?>...</div>
                <div class="notif-time"><?= $timeStr ?><?= $n['order_number'] ? ' · '.$n['order_number'] : '' ?></div>
              </div>
            </div>
            <?php endforeach; endif; ?>
          </div>
        </div>
      </div><!-- /notif-wrapper -->

      <a href="#" class="topbar-btn" onclick="confirmLogout(event)" title="Logout">
        <i class="fa fa-right-from-bracket"></i>
      </a>
    </div>
  </header>

  <!-- Content -->
  <div class="content">

    <!-- Alert bukti pembayaran berhasil diupload -->
    <?php if (isset($_GET['bukti_ok'])): ?>
    <div class="alert-banner saved" style="margin-bottom:18px;">
      <i class="fa fa-circle-check"></i>
      Bukti pembayaran berhasil diupload. Admin akan segera memverifikasi.
    </div>
    <?php endif; ?>

    <!-- Alert upload gagal -->
    <?php if (isset($_GET['err']) && $_GET['err'] === 'upload_failed'): ?>
    <div class="alert-banner error" style="margin-bottom:18px;">
      <i class="fa fa-triangle-exclamation"></i>
      Upload gagal. Pastikan file berupa gambar/PDF dan ukurannya tidak melebihi 5 MB.
    </div>
    <?php endif; ?>

    <!-- Alert saved -->
    <?php if (isset($_GET['saved'])): ?>
    <div class="alert-banner saved" style="margin-bottom:18px;">
      <i class="fa fa-circle-check"></i> Catatan berhasil disimpan.
    </div>
    <?php endif; ?>

    <!-- Alert selesai dipasang -->
    <?php if ($selesai_ok): ?>
    <div class="alert-banner saved" style="margin-bottom:18px;">
      <i class="fa fa-circle-check"></i>
      Instalasi berhasil dilaporkan! Notifikasi telah dikirim ke admin dan pelanggan.
    </div>
    <?php elseif ($err_code === 'wrong_status'): ?>
    <div class="alert-banner error" style="margin-bottom:18px;">
      <i class="fa fa-triangle-exclamation"></i>
      Status order sudah berubah. Muat ulang halaman untuk melihat status terbaru.
    </div>
    <?php elseif ($err_code === 'failed'): ?>
    <div class="alert-banner error" style="margin-bottom:18px;">
      <i class="fa fa-circle-xmark"></i>
      Gagal memperbarui status: <?= htmlspecialchars($err_msg_raw) ?>
    </div>
    <?php endif; ?>

    <!-- Welcome banner -->
    <?php if (!isset($_GET['order_id'])): ?>
    <div class="welcome-banner">
      <h1>Halo, <span><?= $nama_lengkap ?></span> 👷</h1>
      <p>Berikut ringkasan pekerjaan Anda hari ini.</p>
    </div>
    <?php endif; ?>

    <!-- Stat cards -->
    <div class="stats-grid">
      <div class="stat-card blue">
        <div class="stat-icon"><i class="fa fa-list-check"></i></div>
        <div class="stat-label">Total Order</div>
        <div class="stat-value"><?= $stat['total'] ?></div>
        <div class="stat-sub">Semua status</div>
      </div>
      <div class="stat-card yellow">
        <div class="stat-icon"><i class="fa fa-calendar-check"></i></div>
        <div class="stat-label">Terjadwal</div>
        <div class="stat-value"><?= $stat['scheduled'] ?></div>
        <div class="stat-sub">Perlu dikerjakan</div>
      </div>
      <div class="stat-card green">
        <div class="stat-icon"><i class="fa fa-screwdriver-wrench"></i></div>
        <div class="stat-label">Terpasang</div>
        <div class="stat-value"><?= $stat['installed'] ?></div>
        <div class="stat-sub">Instalasi selesai</div>
      </div>
      <div class="stat-card purple">
        <div class="stat-icon"><i class="fa fa-wifi"></i></div>
        <div class="stat-label">Aktif</div>
        <div class="stat-value"><?= $stat['active'] ?></div>
        <div class="stat-sub">Layanan berjalan</div>
      </div>
    </div>

    <!-- Jadwal hari ini -->
    <?php
    $today_orders = array_filter($orders, function($o) {
        return $o['jadwal_instalasi'] && date('Y-m-d', strtotime($o['jadwal_instalasi'])) === date('Y-m-d');
    });
    if (!empty($today_orders)):
    ?>
    <div class="jadwal-card">
      <div class="jadwal-icon">📅</div>
      <div class="jadwal-text">
        <div class="jadwal-title">Jadwal Hari Ini — <?= date('d M Y') ?></div>
        <div class="jadwal-sub"><?= count($today_orders) ?> instalasi dijadwalkan hari ini</div>
        <div class="jadwal-list">
          <?php foreach ($today_orders as $tj): ?>
          <div class="jadwal-row">
            <span class="jadwal-time"><?= date('H:i', strtotime($tj['jadwal_instalasi'])) ?></span>
            <span class="jadwal-client"><?= namaLengkap($tj['client_first'], $tj['client_last']) ?></span>
            <span class="jadwal-loc">— <?= htmlspecialchars($tj['kecamatan'].', '.$tj['kota']) ?></span>
            <a href="?order_id=<?= $tj['id'] ?>" class="jadwal-link">Lihat →</a>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- ── Detail Order ────────────────────────────────────────── -->
    <?php if ($detail_order): ?>
    <div class="detail-panel" id="detail-panel">
      <div class="dp-header">
        <h2>
          <?= wifiStatusBadge($detail_order['wifi_status']) ?>
          <?= htmlspecialchars($detail_order['order_number']) ?>
          <span style="color:var(--muted);font-weight:400;font-size:13px;">
            — <?= namaLengkap($detail_order['client_first'], $detail_order['client_last']) ?>
          </span>
        </h2>
        <a href="teknisi_dashboard.php">
          <button class="dp-close" title="Tutup">×</button>
        </a>
      </div>
      <div class="dp-body">

        <!-- Info grid -->
        <div class="dp-grid">
          <div class="dp-section">
            <h3>Pelanggan</h3>
            <div class="dp-row">
              <span class="key">Nama</span>
              <span class="val"><?= namaLengkap($detail_order['client_first'], $detail_order['client_last']) ?></span>
            </div>
            <div class="dp-row">
              <span class="key">Telepon</span>
              <span class="val">
                <a href="tel:<?= htmlspecialchars($detail_order['client_hp']) ?>" style="color:var(--accent2);">
                  <?= htmlspecialchars($detail_order['client_hp']) ?>
                </a>
              </span>
            </div>
            <div class="dp-row">
              <span class="key">Email</span>
              <span class="val" style="word-break:break-all;font-size:11px;"><?= htmlspecialchars($detail_order['client_email']) ?></span>
            </div>
          </div>

          <div class="dp-section">
            <h3>Paket</h3>
            <div class="dp-row">
              <span class="key">Nama</span>
              <span class="val" style="color:var(--accent2);"><?= htmlspecialchars($detail_order['nama_paket']) ?></span>
            </div>
            <div class="dp-row">
              <span class="key">Kecepatan</span>
              <span class="val"><?= htmlspecialchars($detail_order['kecepatan'] ?? '—') ?></span>
            </div>
            <div class="dp-row">
              <span class="key">Harga</span>
              <span class="val"><?= rp($detail_order['harga']) ?>/bln</span>
            </div>
            <div class="dp-row">
              <span class="key">Pembayaran</span>
              <span class="val"><?= payBadge($detail_order['payment_status']) ?></span>
            </div>
          </div>

          <div class="dp-section">
            <h3>Jadwal & Waktu</h3>
            <div class="dp-row">
              <span class="key">Jadwal Pasang</span>
              <span class="val" style="color:var(--warning);"><?= tgl($detail_order['jadwal_instalasi']) ?></span>
            </div>
            <div class="dp-row">
              <span class="key">Tgl Order</span>
              <span class="val"><?= tglShort($detail_order['created_at']) ?></span>
            </div>
            <div class="dp-row">
              <span class="key">Tgl Aktif</span>
              <span class="val"><?= tglShort($detail_order['tgl_aktif']) ?></span>
            </div>
            <div class="dp-row">
              <span class="key">Aktif Sampai</span>
              <span class="val">
                <?php if ($detail_order['tanggal_expire']): ?>
                  <?php
                    $exp     = new DateTime($detail_order['tanggal_expire']);
                    $now     = new DateTime();
                    $selisih = (int)$now->diff($exp)->format('%r%a'); // negatif jika sudah lewat
                  ?>
                  <?php if ($selisih < 0): ?>
                    <span style="color:#f87171;font-weight:700;">
                      <?= tglShort($detail_order['tanggal_expire']) ?>
                      <span style="font-size:11px;font-weight:400;">(Sudah berakhir)</span>
                    </span>
                  <?php elseif ($selisih <= 7): ?>
                    <span style="color:#fbbf24;font-weight:700;">
                      <?= tglShort($detail_order['tanggal_expire']) ?>
                      <span style="font-size:11px;font-weight:400;">(<?= $selisih ?> hari lagi)</span>
                    </span>
                  <?php else: ?>
                    <span style="color:#34d399;font-weight:600;">
                      <?= tglShort($detail_order['tanggal_expire']) ?>
                      <span style="font-size:11px;font-weight:400;color:var(--muted);">(<?= $selisih ?> hari lagi)</span>
                    </span>
                  <?php endif; ?>
                <?php else: ?>
                  <span style="color:var(--muted);">—</span>
                <?php endif; ?>
              </span>
            </div>
          </div>

          <div class="dp-section">
            <h3>Tim Teknisi</h3>
            <div class="dp-row">
              <span class="key">Teknisi 1</span>
              <span class="val">
                <?php if ($detail_order['teknisi1_first']): ?>
                  <?= namaLengkap($detail_order['teknisi1_first'], $detail_order['teknisi1_last']) ?>
                  <?php if ($detail_order['teknisi_id'] == $teknisi_id): ?>
                    <span class="me-badge">Saya</span>
                  <?php endif; ?>
                <?php else: ?><span style="color:var(--muted)">—</span><?php endif; ?>
              </span>
            </div>
            <div class="dp-row">
              <span class="key">Teknisi 2</span>
              <span class="val">
                <?php if ($detail_order['teknisi2_first']): ?>
                  <?= namaLengkap($detail_order['teknisi2_first'], $detail_order['teknisi2_last']) ?>
                  <?php if ($detail_order['teknisi_id_2'] == $teknisi_id): ?>
                    <span class="me-badge">Saya</span>
                  <?php endif; ?>
                <?php else: ?><span style="color:var(--muted)">—</span><?php endif; ?>
              </span>
            </div>
          </div>
        </div>

        <!-- Alamat Pasang -->
        <div class="addr-block">
          <span class="addr-label"><i class="fa fa-location-dot" style="margin-right:4px;"></i> Alamat Pemasangan</span>
          <?= htmlspecialchars($detail_order['alamat_pasang']) ?>
          <?php if ($detail_order['rt'] || $detail_order['rw']): ?>
            , RT <?= $detail_order['rt'] ?>/RW <?= $detail_order['rw'] ?>
          <?php endif; ?>
          <?php if ($detail_order['kelurahan']): ?>
            , <?= htmlspecialchars($detail_order['kelurahan']) ?>
          <?php endif; ?>
          <?php if ($detail_order['kecamatan']): ?>
            , <?= htmlspecialchars($detail_order['kecamatan']) ?>
          <?php endif; ?>
          , <?= htmlspecialchars($detail_order['kota']) ?>, <?= htmlspecialchars($detail_order['provinsi']) ?>
          <?php if ($detail_order['kodepos']): ?> <?= htmlspecialchars($detail_order['kodepos']) ?><?php endif; ?>
          <?php if ($detail_order['koordinat_lat'] && $detail_order['koordinat_lng']): ?>
          <br>
          <a class="map-btn"
             href="https://www.google.com/maps?q=<?= $detail_order['koordinat_lat'] ?>,<?= $detail_order['koordinat_lng'] ?>"
             target="_blank" rel="noopener">
            <i class="fa fa-map-location-dot"></i> Buka di Google Maps
          </a>
          <?php endif; ?>
        </div>

        <!-- Timeline -->
        <?php if (!empty($status_logs)): ?>
        <div class="sec-title" style="margin-bottom:10px;">
          <i class="fa fa-clock-rotate-left"></i> Riwayat Status
        </div>
        <div class="timeline">
          <?php foreach ($status_logs as $log):
            $role_dot   = in_array($log['role'], ['admin','teknisi','client','system']) ? $log['role'] : 'system';
            $role_label = ['admin'=>'Admin','teknisi'=>'Teknisi','client'=>'Client','system'=>'Sistem'][$log['role']] ?? ucfirst($log['role']);
          ?>
          <div class="tl-item">
            <div class="tl-dot <?= $role_dot ?>"></div>
            <div>
              <div class="tl-head">
                <?= $role_label ?>:
                <?php if ($log['old_status'] && $log['old_status'] !== $log['new_status']): ?>
                  <span style="color:var(--muted)"><?= ucfirst($log['old_status']) ?></span>
                  → <strong><?= ucfirst($log['new_status']) ?></strong>
                <?php else: ?>
                  <?= wifiStatusBadge($log['new_status']) ?>
                <?php endif; ?>
              </div>
              <div class="tl-sub"><?= tgl($log['created_at']) ?>
                <?php if ($log['firstname']): ?> · <?= namaLengkap($log['firstname'], $log['lastname'] ?? '') ?><?php endif; ?>
              </div>
              <?php if (!empty($log['catatan'])): ?>
                <div class="tl-note"><?= nl2br(htmlspecialchars($log['catatan'])) ?></div>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Form Catatan -->
        <hr class="divider">
        <div class="sec-title" style="margin-bottom:10px;">
          <i class="fa fa-pen-to-square"></i> Catatan Teknisi
        </div>
        <form method="POST">
          <input type="hidden" name="order_id" value="<?= $detail_order['id'] ?>">
          <div class="form-group">
            <label class="form-label">Tambah / Perbarui Catatan</label>
            <textarea name="catatan" placeholder="Tulis catatan, kendala teknis, atau hasil pekerjaan di lapangan..."><?= htmlspecialchars($detail_order['note'] ?? '') ?></textarea>
          </div>
          <button type="submit" name="update_catatan" class="btn btn-primary">
            <i class="fa fa-floppy-disk"></i> Simpan Catatan
          </button>
        </form>

        <!-- ── Tombol Selesai Dipasang ──────────────────────── -->
        <?php if ($detail_order['wifi_status'] === 'scheduled'): ?>
        <hr class="divider">
        <div class="selesai-pasang-wrap">
          <div class="selesai-pasang-info">
            <i class="fa fa-screwdriver-wrench" style="color:var(--success);font-size:18px;margin-top:2px;"></i>
            <div>
              <div style="font-weight:600;margin-bottom:2px;">Tandai Instalasi Selesai</div>
              <div style="font-size:12px;color:var(--muted);">
                Klik tombol ini jika pemasangan sudah selesai. Sistem akan otomatis
                notifikasi admin untuk review dan pelanggan untuk upload bukti pembayaran.
              </div>
            </div>
          </div>
          <button class="btn btn-selesai"
                  onclick="openSelesaiModal(<?= $detail_order['id'] ?>, '<?= htmlspecialchars($detail_order['order_number'], ENT_QUOTES) ?>')">
            <i class="fa fa-circle-check"></i> Selesai Dipasang
          </button>
        </div>
        <?php elseif ($detail_order['wifi_status'] === 'installed'): ?>
        <div class="selesai-pasang-done">
          <i class="fa fa-circle-check" style="font-size:18px;"></i>
          Instalasi sudah ditandai selesai. Menunggu review admin.
        </div>
        <?php endif; ?>

        <!-- ── Upload Bukti Pembayaran (scheduled atau installed) ── -->
        <?php
        $sudah_ada_bukti = !empty($detail_order['payment_proof']) ||
                           in_array($detail_order['payment_status'], ['sudah_bayar', 'lunas']);
        $status_aktif    = in_array($detail_order['wifi_status'], ['scheduled', 'installed']);
        ?>
        <?php if ($status_aktif && !$sudah_ada_bukti): ?>
        <hr class="divider">
        <div class="sec-title" style="margin-bottom:10px;">
          <i class="fa fa-receipt" style="color:var(--accent2);"></i> Upload Bukti Pembayaran
          <span style="font-size:11px;color:var(--muted);font-weight:400;margin-left:6px;">(Bantu client upload)</span>
        </div>
        <div style="background:rgba(234,179,8,.07);border:1px solid rgba(234,179,8,.2);border-left:4px solid #eab308;border-radius:0 8px 8px 0;padding:13px 16px;font-size:13px;color:#fde68a;line-height:1.7;margin-bottom:18px;">
          💳 <strong>Info Transfer:</strong><br>
          <span style="color:#94a3b8;">
            Bank <strong style="color:#f1f5f9;">BCA</strong> &nbsp;·&nbsp;
            No. Rek <strong style="color:#60a5fa;font-family:'Courier New',monospace;font-size:15px;">0184246283</strong> &nbsp;·&nbsp;
            a/n <strong style="color:#f1f5f9;">TECH PERKASA SOLUSINDO</strong><br>
            Nominal: <strong style="color:#34d399;"><?= rp($detail_order['harga']) ?>/bln</strong>
          </span>
        </div>
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="order_id" value="<?= $detail_order['id'] ?>">
          <div class="form-group">
            <label class="form-label">File Bukti Pembayaran
              <span style="color:var(--muted);font-weight:400;">(JPG, PNG, PDF — maks 5 MB)</span>
            </label>
            <input type="file" name="bukti_file" accept="image/jpeg,image/png,image/webp,application/pdf"
                   required
                   style="width:100%;background:var(--surface);border:1px solid var(--border);color:var(--text);border-radius:8px;padding:10px 12px;font-size:13px;font-family:var(--sans);">
          </div>
          <button type="submit" name="upload_bukti_teknisi" class="btn btn-primary">
            <i class="fa fa-upload"></i> Upload Bukti Pembayaran
          </button>
        </form>
        <?php elseif ($status_aktif && $sudah_ada_bukti): ?>
        <hr class="divider">
        <div style="display:flex;align-items:center;gap:10px;background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.2);border-radius:10px;padding:14px 18px;margin-top:4px;font-size:13px;color:var(--success);font-weight:600;">
          <i class="fa fa-circle-check" style="font-size:16px;"></i>
          Bukti pembayaran sudah diupload. Menunggu verifikasi admin.
          <?php if ($detail_order['payment_proof']): ?>
            &nbsp;·&nbsp;
            <a href="/../order/order_asset/bukti_pembayaran/<?= htmlspecialchars($detail_order['payment_proof']) ?>"
               target="_blank" rel="noopener"
               style="color:var(--accent2);font-size:12px;">Lihat Bukti →</a>
          <?php endif; ?>
        </div>
        <?php endif; ?>

      </div>
    </div>
    <?php endif; ?>

    <!-- ── Tabel Order ─────────────────────────────────────────── -->
    <div id="orders-section">
      <div class="table-wrap">
        <div class="table-head">
          <span class="table-head-title">
            <i class="fa fa-list-check" style="color:var(--accent2);margin-right:7px;"></i>
            Semua Order Ditugaskan
            <?php if ($stat['total'] > 0): ?>
              <span style="color:var(--muted);font-size:12px;font-weight:500;margin-left:6px;">(<?= $stat['total'] ?>)</span>
            <?php endif; ?>
          </span>
          <input class="search-box" id="srch" type="text"
                 placeholder="🔍 Cari nama / nomor…"
                 oninput="filterList()">
        </div>

        <!-- Desktop table -->
        <div class="tbl-scroll">
          <table id="ordTbl">
            <thead>
              <tr>
                <th>No. Order</th>
                <th>Pelanggan</th>
                <th>Paket</th>
                <th>Jadwal Pasang</th>
                <th>Peran</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($orders)): ?>
              <tr class="empty-row">
                <td colspan="7">
                  <i class="fa fa-inbox" style="font-size:24px;display:block;margin-bottom:8px;color:var(--muted);"></i>
                  Belum ada order yang ditugaskan ke kamu.
                </td>
              </tr>
              <?php else: foreach ($orders as $o):
                $peran    = ($o['teknisi_id'] == $teknisi_id) ? 1 : 2;
                $is_today = $o['jadwal_instalasi'] && date('Y-m-d', strtotime($o['jadwal_instalasi'])) === date('Y-m-d');
              ?>
              <tr onclick="go('<?= $o['id'] ?>')"
                  data-s="<?= strtolower($o['order_number'].' '.htmlspecialchars($o['client_first'].' '.$o['client_last'])) ?>">
                <td>
                  <div class="col-order"><?= htmlspecialchars($o['order_number']) ?></div>
                  <div style="font-size:10px;color:var(--muted);"><?= date('d M Y', strtotime($o['created_at'])) ?></div>
                </td>
                <td class="col-client"><?= namaLengkap($o['client_first'], $o['client_last']) ?></td>
                <td class="col-paket"><?= htmlspecialchars($o['nama_paket']) ?></td>
                <td class="col-time <?= $is_today ? 'today' : '' ?>">
                  <?= $o['jadwal_instalasi'] ? date('d M Y H:i', strtotime($o['jadwal_instalasi'])) : '—' ?>
                  <?= $is_today ? ' ⏰' : '' ?>
                </td>
                <td><span class="role-badge role-<?= $peran ?>">Teknisi <?= $peran ?></span></td>
                <td><?= wifiStatusBadge($o['wifi_status']) ?></td>
                <td onclick="event.stopPropagation()">
                  <a href="?order_id=<?= $o['id'] ?>" class="view-btn">Detail →</a>
                </td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Mobile card list -->
        <div class="mobile-order-list" id="mobileList">
          <?php if (empty($orders)): ?>
          <div style="padding:32px;text-align:center;color:var(--muted);">
            <i class="fa fa-inbox" style="font-size:28px;display:block;margin-bottom:10px;"></i>
            Belum ada order yang ditugaskan.
          </div>
          <?php else: foreach ($orders as $o):
            $peran    = ($o['teknisi_id'] == $teknisi_id) ? 1 : 2;
            $is_today = $o['jadwal_instalasi'] && date('Y-m-d', strtotime($o['jadwal_instalasi'])) === date('Y-m-d');
          ?>
          <a href="?order_id=<?= $o['id'] ?>"
             class="mob-order-card status-<?= $o['wifi_status'] ?>"
             data-s="<?= strtolower($o['order_number'].' '.htmlspecialchars($o['client_first'].' '.$o['client_last'])) ?>">
            <div class="mob-card-top">
              <span class="mob-order-num"><?= htmlspecialchars($o['order_number']) ?></span>
              <?= wifiStatusBadge($o['wifi_status']) ?>
            </div>
            <div class="mob-client"><?= namaLengkap($o['client_first'], $o['client_last']) ?></div>
            <div class="mob-paket"><i class="fa fa-wifi" style="color:var(--accent2);margin-right:5px;"></i><?= htmlspecialchars($o['nama_paket']) ?></div>
            <div class="mob-card-foot">
              <span class="mob-time <?= $is_today ? 'today' : '' ?>">
                <i class="fa fa-calendar"></i>
                <?= $o['jadwal_instalasi'] ? date('d M Y H:i', strtotime($o['jadwal_instalasi'])) : 'Belum dijadwalkan' ?>
                <?= $is_today ? '⏰' : '' ?>
              </span>
              <span class="mob-role">
                <span class="role-badge role-<?= $peran ?>">Teknisi <?= $peran ?></span>
              </span>
            </div>
          </a>
          <?php endforeach; endif; ?>
        </div>

      </div>
    </div>

  </div><!-- /content -->
</div><!-- /main -->
</div><!-- /layout -->

<!-- ═══ BOTTOM NAV (mobile only) ═══ -->
<nav class="bottom-nav">
  <a href="teknisi_dashboard.php" class="bn-item <?= !isset($_GET['order_id']) ? 'active' : '' ?>">
    <i class="fa fa-gauge-high"></i>
    Dashboard
  </a>
  <button class="bn-item" onclick="scrollToOrders()">
    <i class="fa fa-list-check"></i>
    <?php if ($stat['scheduled'] > 0): ?>
      <span class="bn-badge"><?= $stat['scheduled'] ?></span>
    <?php endif; ?>
    Order
  </button>
  <button class="bn-item" onclick="toggleNotif(event)">
    <i class="fa fa-bell"></i>
    <?php if ($unread_count > 0): ?>
      <span class="bn-badge"><?= $unread_count ?></span>
    <?php endif; ?>
    Notifikasi
  </button>
  <a href="#" class="bn-item" onclick="confirmLogout(event)">
    <i class="fa fa-right-from-bracket"></i>
    Keluar
  </a>
</nav>

<!-- ═══ MODAL SELESAI DIPASANG ═══ -->
<div id="selesaiModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.65);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
  <div class="selesai-modal-inner">
    <div class="selesai-modal-icon">
      <i class="fa fa-screwdriver-wrench"></i>
    </div>
    <div class="selesai-modal-title">Konfirmasi Selesai Dipasang</div>
    <div class="selesai-modal-sub">
      Anda akan menandai order <strong id="selesaiOrderNum"></strong> sebagai
      <em>Selesai Dipasang</em>.<br>
      Admin akan dinotifikasi untuk review, dan pelanggan akan diminta upload bukti pembayaran.
    </div>
    <form method="POST" action="process_selesai_pasang.php">
      <input type="hidden" name="order_id" id="selesaiOrderId">
      <div class="form-group" style="margin-bottom:16px;text-align:left;">
        <label class="form-label" style="font-size:13px;">
          Catatan Lapangan <span style="color:var(--muted);font-weight:400;">(opsional)</span>
        </label>
        <textarea id="selesaiCatatan" name="catatan" rows="3"
                  placeholder="Contoh: Kabel ditarik dari tiang depan, ODP sudah terhubung. Sinyal -18 dBm."
                  style="width:100%;background:var(--surface);border:1px solid var(--border);color:var(--text);border-radius:8px;padding:10px 12px;font-size:13px;resize:vertical;font-family:var(--sans);outline:none;"></textarea>
      </div>
      <div class="selesai-modal-btns">
        <button type="button" onclick="closeSelesaiModal()" class="btn btn-secondary" style="min-width:110px;">
          <i class="fa fa-xmark"></i> Batal
        </button>
        <button type="submit" class="btn btn-selesai" style="min-width:160px;">
          <i class="fa fa-circle-check"></i> Ya, Selesai Dipasang
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ═══ LOGOUT MODAL ═══ -->
<div id="logoutModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
  <div class="logout-modal-inner">
    <div class="logout-modal-icon">
      <i class="fa fa-right-from-bracket"></i>
    </div>
    <div class="logout-modal-title">Konfirmasi Logout</div>
    <div class="logout-modal-sub">
      Anda akan keluar dari Portal Teknisi.<br>Pastikan semua catatan sudah tersimpan.
    </div>
    <div class="logout-modal-btns">
      <button onclick="closeLogoutModal()" class="btn btn-secondary" style="min-width:110px;">
        <i class="fa fa-xmark"></i> Batal
      </button>
      <a href="../admin/logout.php" class="btn btn-danger" style="min-width:110px;">
        <i class="fa fa-right-from-bracket"></i> Ya, Keluar
      </a>
    </div>
  </div>
</div>

<script>
// ── Sidebar toggle (mobile) ────────────────────────────────────────
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('open');
  document.getElementById('sidebarOverlay').classList.toggle('open');
}
function closeSidebar() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebarOverlay').classList.remove('open');
}

// ── Notif dropdown ─────────────────────────────────────────────────
function toggleNotif(e) {
  e.stopPropagation();
  document.getElementById('notifDropdown').classList.toggle('open');
}
document.addEventListener('click', function(e) {
  const dd = document.getElementById('notifDropdown');
  const btn = document.getElementById('notifBtn');
  if (dd && !dd.contains(e.target) && btn && !btn.contains(e.target)) {
    dd.classList.remove('open');
  }
});

function markRead(id, orderId, el) {
  el.classList.remove('unread');
  fetch('?mark_read=1&notif_id=' + id).catch(function(){});
  if (orderId) window.location = '?order_id=' + orderId;
}

// ── Search / filter ────────────────────────────────────────────────
function filterList() {
  var q = document.getElementById('srch').value.toLowerCase();
  // Desktop table rows
  document.querySelectorAll('#ordTbl tbody tr[data-s]').forEach(function(tr) {
    tr.style.display = tr.dataset.s.includes(q) ? '' : 'none';
  });
  // Mobile cards
  document.querySelectorAll('#mobileList .mob-order-card[data-s]').forEach(function(card) {
    card.style.display = card.dataset.s.includes(q) ? '' : 'none';
  });
}

// ── Row / card click ───────────────────────────────────────────────
function go(id) { window.location = '?order_id=' + id; }

// ── Scroll to orders ───────────────────────────────────────────────
function scrollToOrders() {
  closeSidebar();
  var el = document.getElementById('orders-section');
  if (el) el.scrollIntoView({behavior: 'smooth', block: 'start'});
}

// ── Logout modal ───────────────────────────────────────────────────
function confirmLogout(e) {
  e.preventDefault();
  document.getElementById('logoutModal').style.display = 'flex';
}
function closeLogoutModal() {
  document.getElementById('logoutModal').style.display = 'none';
}
document.getElementById('logoutModal').addEventListener('click', function(e) {
  if (e.target === this) closeLogoutModal();
});
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closeLogoutModal();
});

// ── Auto scroll to detail if order_id in URL ──────────────────────
if (window.location.search.includes('order_id') || window.location.search.includes('saved') || window.location.search.includes('selesai')) {
  setTimeout(function() {
    var el = document.getElementById('detail-panel');
    if (el) el.scrollIntoView({behavior: 'smooth', block: 'start'});
  }, 150);
}

// ── Auto refresh every 90s (dashboard only) ───────────────────────
<?php if (!isset($_GET['order_id'])): ?>
setTimeout(function() { location.reload(); }, 90000);
<?php endif; ?>

// ── Modal Selesai Dipasang ─────────────────────────────────────────
function openSelesaiModal(orderId, orderNum) {
  document.getElementById('selesaiOrderId').value        = orderId;
  document.getElementById('selesaiOrderNum').textContent = orderNum;
  document.getElementById('selesaiModal').style.display  = 'flex';
  setTimeout(function(){ document.getElementById('selesaiCatatan').focus(); }, 60);
}
function closeSelesaiModal() {
  document.getElementById('selesaiModal').style.display = 'none';
  document.getElementById('selesaiCatatan').value       = '';
}
document.getElementById('selesaiModal').addEventListener('click', function(e) {
  if (e.target === this) closeSelesaiModal();
});
// Escape sudah handled oleh listener global closeLogoutModal — tambahkan selesai
var _origKeydown = document.onkeydown;
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closeSelesaiModal();
});
</script>

<style>
/* ── Tombol Selesai Dipasang ──────────────────────────────────── */
.btn-selesai {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 10px 20px;
  background: linear-gradient(135deg, #10b981, #059669);
  color: #fff;
  font-weight: 700;
  font-size: 14px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  transition: opacity .15s, transform .1s;
  text-decoration: none;
  white-space: nowrap;
}
.btn-selesai:hover  { opacity: .88; transform: translateY(-1px); }
.btn-selesai:active { transform: translateY(0); }

/* ── Selesai wrap block ──────────────────────────────────────── */
.selesai-pasang-wrap {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  background: rgba(16,185,129,.08);
  border: 1px solid rgba(16,185,129,.25);
  border-radius: 10px;
  padding: 16px 18px;
  margin-top: 18px;
  flex-wrap: wrap;
}
.selesai-pasang-info {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  flex: 1;
  min-width: 200px;
}
.selesai-pasang-done {
  display: flex;
  align-items: center;
  gap: 10px;
  background: rgba(16,185,129,.08);
  border: 1px solid rgba(16,185,129,.2);
  border-radius: 10px;
  padding: 14px 18px;
  margin-top: 18px;
  color: var(--success);
  font-weight: 600;
  font-size: 14px;
}

/* ── Alert error ─────────────────────────────────────────────── */
.alert-banner.error {
  background: rgba(239,68,68,.12);
  border: 1px solid rgba(239,68,68,.3);
  color: #fca5a5;
  padding: 12px 16px;
  border-radius: 10px;
  font-size: 14px;
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 18px;
}

/* ── Modal Selesai Dipasang ──────────────────────────────────── */
.selesai-modal-inner {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 16px;
  padding: 32px 28px 28px;
  max-width: 440px;
  width: 92%;
  text-align: center;
  box-shadow: 0 20px 60px rgba(0,0,0,.5);
}
.selesai-modal-icon {
  width: 56px; height: 56px;
  background: rgba(16,185,129,.12);
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  margin: 0 auto 16px;
  font-size: 22px;
  color: var(--success);
}
.selesai-modal-title {
  font-size: 17px;
  font-weight: 700;
  color: var(--text);
  margin-bottom: 8px;
}
.selesai-modal-sub {
  font-size: 13px;
  color: var(--muted);
  line-height: 1.6;
  margin-bottom: 20px;
}
.selesai-modal-btns {
  display: flex;
  gap: 10px;
  justify-content: center;
  margin-top: 8px;
  flex-wrap: wrap;
}
</style>
</body>
</html>
