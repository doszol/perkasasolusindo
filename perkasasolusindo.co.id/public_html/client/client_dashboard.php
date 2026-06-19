<?php
// =====================================================
//  client_dashboard.php  –  /client/client_dashboard.php
// =====================================================
require_once __DIR__ . '/../auth_check.php';
requireLevel(3);
require_once __DIR__ . '/../config.php';

$userId = (int)$_SESSION['user_id'];

// ── Pastikan email sudah terverifikasi ──────────────────────
$stVerify = $conn->prepare("SELECT email_verified FROM tblclients WHERE id = ? LIMIT 1");
$stVerify->bind_param('i', $userId);
$stVerify->execute();
$verifyRow = $stVerify->get_result()->fetch_assoc();
$stVerify->close();

if (!$verifyRow || (int)$verifyRow['email_verified'] !== 1) {
    header('Location: /login/verifikasi_email.php');
    exit;
}

// ── Tentukan view ──
$view = isset($_GET['view']) ? $_GET['view'] : 'dashboard';

$pageTitle    = 'Dashboard — Perkasa Solusindo';
$detailOrder  = null;
$statusLog    = [];
$tagihanAktif = null;
$tagihanFallback = false;

// ── Data Hosting ──────────────────────────────────────────
$hostingList    = [];
$invoiceHosting = [];

// ── Data Dashboard (ringkasan) ─────────────────────────────
$wifiSummary      = null;
$hostingSummary   = [];
$announcements    = [];
$tagihanDashboard = null;

if ($view === 'dashboard') {
    $pageTitle = 'Dashboard';

    // Ringkasan WiFi
    $stWS = $conn->prepare("
        SELECT o.*, p.name AS product_name, p.speed, p.price, p.period
        FROM tblorders o
        LEFT JOIN tblproducts p ON p.id = o.productid
        WHERE o.userid = ? AND o.order_type = 'wifi'
        ORDER BY o.created_at DESC LIMIT 1
    ");
    $stWS->bind_param('i', $userId);
    $stWS->execute();
    $wifiSummary = $stWS->get_result()->fetch_assoc();
    $stWS->close();

    // Cek tagihan wifi aktif untuk dashboard
    if ($wifiSummary) {
        $stTD = $conn->prepare(
            "SELECT * FROM tblpayment_monthly
             WHERE order_id = ? AND status IN ('unpaid','waiting_confirm')
             ORDER BY tagihan_bulan DESC LIMIT 1"
        );
        $wifiOrderId = (int)$wifiSummary['id'];
        $stTD->bind_param('i', $wifiOrderId);
        $stTD->execute();
        $tagihanDashboard = $stTD->get_result()->fetch_assoc();
        $stTD->close();
    }

    // Ringkasan Hosting
    $stHS = $conn->prepare("
        SELECT h.*, p.name AS package_name, p.price AS package_price, p.period AS package_period
        FROM tblhosting h
        LEFT JOIN tblproducts p ON p.id = h.packageid
        WHERE h.userid = ?
        ORDER BY h.created_at DESC LIMIT 5
    ");
    $stHS->bind_param('i', $userId);
    $stHS->execute();
    $hostingSummary = $stHS->get_result()->fetch_all(MYSQLI_ASSOC);
    $stHS->close();

    // Pengumuman terbaru (published)
    $stAnn = $conn->prepare(
        "SELECT * FROM tblannouncements WHERE published = 1 ORDER BY date DESC LIMIT 5"
    );
    $stAnn->execute();
    $announcements = $stAnn->get_result()->fetch_all(MYSQLI_ASSOC);
    $stAnn->close();
}

if ($view === 'layanan_hosting') {
    $pageTitle = 'Layanan Saya — Hosting';

    // Ambil semua layanan hosting milik user beserta detail paket
    // + payment_status & payment_deadline dari order terkait (untuk countdown bayar)
    $stH = $conn->prepare("
        SELECT h.*, p.name AS package_name, p.description AS package_desc,
               p.price AS package_price, p.period AS package_period,
               o.id AS order_id, o.order_number, o.payment_status, o.payment_deadline AS order_payment_deadline
        FROM tblhosting h
        LEFT JOIN tblproducts p ON p.id = h.packageid
        LEFT JOIN tblorders o ON o.userid = h.userid AND o.productid = h.packageid AND o.order_type = 'hosting'
        WHERE h.userid = ?
        ORDER BY h.created_at DESC
    ");
    $stH->bind_param('i', $userId);
    $stH->execute();
    $hostingList = $stH->get_result()->fetch_all(MYSQLI_ASSOC);
    $stH->close();

    // Ambil invoice hosting yang belum lunas (order_type hosting)
    $stInv = $conn->prepare("
        SELECT inv.*, o.order_number
        FROM tblinvoices inv
        LEFT JOIN tblorders o ON o.id = inv.order_id
        WHERE inv.userid = ? AND inv.status != 'Paid'
        ORDER BY inv.duedate ASC
        LIMIT 10
    ");
    $stInv->bind_param('i', $userId);
    $stInv->execute();
    $invoiceHosting = $stInv->get_result()->fetch_all(MYSQLI_ASSOC);
    $stInv->close();
}

// Hitung jumlah hosting aktif untuk badge sidebar
$stHCount = $conn->prepare("SELECT COUNT(*) FROM tblhosting WHERE userid = ? AND domainstatus = 'Active'");
$stHCount->bind_param('i', $userId);
$stHCount->execute();
$stHCount->bind_result($hostingActiveCount);
$stHCount->fetch();
$stHCount->close();

if ($view === 'layanan_wifi' || $view === 'detail') {
    $pageTitle = 'Layanan Saya — Layanan WiFi';
    $st = $conn->prepare("
        SELECT o.*, p.name AS product_name, p.speed, p.price, p.period
        FROM tblorders o
        LEFT JOIN tblproducts p ON p.id = o.productid
        WHERE o.userid = ? AND o.order_type = 'wifi'
        ORDER BY o.created_at DESC
        LIMIT 1
    ");
    $st->bind_param('i', $userId);
    $st->execute();
    $detailOrder = $st->get_result()->fetch_assoc();
    $st->close();

    if ($detailOrder) {
        $orderId   = (int)$detailOrder['id'];
        $pageTitle = 'Layanan WiFi — ' . $detailOrder['order_number'];

        // Riwayat status
        $st2 = $conn->prepare("SELECT * FROM tblorder_status_logs WHERE order_id = ? ORDER BY created_at ASC");
        $st2->bind_param('i', $orderId);
        $st2->execute();
        $statusLog = $st2->get_result()->fetch_all(MYSQLI_ASSOC);
        $st2->close();

        // Tagihan bulanan aktif (unpaid / waiting_confirm)
        $stPm = $conn->prepare(
            "SELECT * FROM tblpayment_monthly
             WHERE order_id = ? AND status IN ('unpaid','waiting_confirm')
             ORDER BY tagihan_bulan DESC LIMIT 1"
        );
        $stPm->bind_param('i', $orderId);
        $stPm->execute();
        $tagihanAktif = $stPm->get_result()->fetch_assoc();
        $stPm->close();

        // ── FALLBACK: jika wifi aktif, hari ini ≥ tgl 1, dan expire = tgl 20 bulan ini
        // tapi cron belum sempat buat baris tblpayment_monthly, tampilkan alert tagihan.
        if (!$tagihanAktif && $detailOrder['wifi_status'] === 'active' && !empty($detailOrder['tanggal_expire'])) {
            $todayDay     = (int)date('j');
            $thisYear     = (int)date('Y');
            $thisMonth    = (int)date('n');
            $expireBulanIni = sprintf('%04d-%02d-20', $thisYear, $thisMonth);

            if ($todayDay >= 1 && $detailOrder['tanggal_expire'] === $expireBulanIni) {
                // Cron belum buat baris, tapi sudah waktunya tagihan — tampilkan fallback
                $tagihanFallback = true;

                // Hitung new_expire & suspend_date sama dengan logika cron
                $newExpireMonth = $thisMonth + 1;
                $newExpireYear  = $thisYear;
                if ($newExpireMonth > 12) { $newExpireMonth = 1; $newExpireYear++; }
                $newExpireDate = sprintf('%04d-%02d-20', $newExpireYear, $newExpireMonth);
                $suspendDate   = sprintf('%04d-%02d-21', $thisYear, $thisMonth);

                $tagihanAktif = [
                    'id'           => 0, // belum ada di DB
                    'status'       => 'unpaid',
                    'tagihan_bulan'=> $expireBulanIni,
                    'due_date'     => $expireBulanIni,
                    'suspend_date' => $suspendDate,
                    'new_expire'   => $newExpireDate,
                    'payment_proof'=> null,
                ];
            }
        }
    }
}

// ── Halaman Invoice & Tagihan ───────────────────────────────
$invoiceList = [];
if ($view === 'invoices') {
    $pageTitle = 'Invoice & Tagihan';
    $stInvList = $conn->prepare("
        SELECT i.*, o.order_number, o.order_type, p.name AS product_name
        FROM tblinvoices i
        LEFT JOIN tblorders o   ON o.id = i.order_id
        LEFT JOIN tblproducts p ON p.id = o.productid
        WHERE i.userid = ?
        ORDER BY i.created_at DESC
    ");
    $stInvList->bind_param('i', $userId);
    $stInvList->execute();
    $invoiceList = $stInvList->get_result()->fetch_all(MYSQLI_ASSOC);
    $stInvList->close();
}

// ── Notifikasi client ──────────────────────────────────────
$stNotif = $conn->prepare(
    "SELECT * FROM tblnotifikasi WHERE userid = ? ORDER BY created_at DESC LIMIT 30"
);
$stNotif->bind_param('i', $userId);
$stNotif->execute();
$notifList = $stNotif->get_result()->fetch_all(MYSQLI_ASSOC);
$stNotif->close();

$notifUnread = 0;
foreach ($notifList as $n) {
    if (!(int)$n['sudah_dibaca']) $notifUnread++;
}

// ── Helper label & badge status WiFi ──
function hostingStatusLabel($status) {
    $map = [
        'Active'           => 'Aktif',
        'Suspended'        => 'Ditangguhkan',
        'Cancelled'        => 'Dibatalkan',
        'Terminated'       => 'Dihentikan',
        'Fraud'            => 'Fraud',
        'Pending'          => 'Menunggu Aktivasi',
    ];
    return $map[$status] ?? ucfirst($status);
}
function hostingStatusBadgeClass($status) {
    $map = [
        'Active'    => 'badge-active',
        'Suspended' => 'badge-suspend',
        'Cancelled' => 'badge-suspend',
        'Terminated'=> 'badge-suspend',
        'Fraud'     => 'badge-suspend',
        'Pending'   => 'badge-pending',
    ];
    return $map[$status] ?? 'badge-pending';
}
function invoiceStatusLabel($status) {
    $map = [
        'Unpaid'      => 'Belum Bayar',
        'Paid'        => 'Lunas',
        'Cancelled'   => 'Dibatalkan',
        'Refunded'    => 'Dikembalikan',
        'Collections' => 'Penagihan',
        'Draft'       => 'Draft',
    ];
    return $map[$status] ?? ucfirst($status);
}
function invoiceStatusBadgeClass($status) {
    $map = [
        'Unpaid'     => 'badge-suspend',
        'Paid'       => 'badge-active',
        'Cancelled'  => 'badge-suspend',
        'Draft'      => 'badge-pending',
        'Collections'=> 'badge-suspend',
    ];
    return $map[$status] ?? 'badge-pending';
}

function wifiStatusLabel($status) {
    $map = [
        'pending'   => 'Menunggu Verifikasi',
        'verified'  => 'Diverifikasi',
        'scheduled' => 'Dijadwalkan Instalasi',
        'installed' => 'Instalasi Selesai',
        'active'    => 'Aktif',
        'cancelled' => 'Dibatalkan',
    ];
    return $map[$status] ?? ucfirst($status);
}
function wifiStatusBadgeClass($status) {
    $map = [
        'pending'   => 'badge-pending',
        'verified'  => 'badge-pending',
        'scheduled' => 'badge-pending',
        'installed' => 'badge-active',
        'active'    => 'badge-active',
        'cancelled' => 'badge-suspend',
    ];
    return $map[$status] ?? 'badge-pending';
}
function paymentStatusLabel($status) {
    $map = [
        'belum_bayar' => 'Belum Bayar',
        'sudah_bayar' => 'Sudah Bayar',
        'lunas'       => 'Lunas',
    ];
    return $map[$status] ?? ucfirst($status);
}
function paymentStatusBadgeClass($status) {
    $map = [
        'belum_bayar' => 'badge-suspend',
        'sudah_bayar' => 'badge-pending',
        'lunas'       => 'badge-active',
    ];
    return $map[$status] ?? 'badge-pending';
}
function fmtRupiah($num) {
    return 'Rp ' . number_format((float)$num, 0, ',', '.');
}
function fmtTanggal($val, $withTime = false) {
    if (empty($val) || $val === '0000-00-00' || $val === '0000-00-00 00:00:00') return '-';
    $ts = strtotime($val);
    return $withTime ? date('d M Y, H:i', $ts) . ' WIB' : date('d M Y', $ts);
}
function notifIcon($tipe) {
    $map = [
        'info'       => 'fa-solid fa-circle-info',
        'sukses'     => 'fa-solid fa-circle-check',
        'peringatan' => 'fa-solid fa-triangle-exclamation',
        'error'      => 'fa-solid fa-circle-xmark',
    ];
    return $map[$tipe] ?? 'fa-solid fa-bell';
}
function timeAgo($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)   return 'Baru saja';
    if ($diff < 3600) return (int)($diff/60) . ' menit lalu';
    if ($diff < 86400) return (int)($diff/3600) . ' jam lalu';
    if ($diff < 604800) return (int)($diff/86400) . ' hari lalu';
    return date('d M Y', strtotime($datetime));
}

$firstname = htmlspecialchars($_SESSION['user_firstname']);
$lastname  = htmlspecialchars($_SESSION['user_lastname']);
$initial   = strtoupper(substr($_SESSION['user_firstname'], 0, 1));

$uploadError = $_SESSION['upload_bukti_error'] ?? null;
unset($_SESSION['upload_bukti_error']);

// Error upload bukti pembayaran khusus hosting (per order_id, karena bisa ada >1 layanan hosting)
$hostingUploadErrorOid = $_SESSION['upload_bukti_hosting_error_oid'] ?? null;
$hostingUploadErrorMsg = $_SESSION['upload_bukti_hosting_error'] ?? null;
unset($_SESSION['upload_bukti_hosting_error'], $_SESSION['upload_bukti_hosting_error_oid']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?> — Perkasa Solusindo</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="/client/style_client_barunih.css">
</head>
<body>

<div id="page-loader">
  <div class="loader-ring"></div>
  <div class="loader-brand">PERKASA <span>SOLUSINDO</span></div>
  <div class="loader-dots"><i></i><i></i><i></i></div>
</div>

<div class="page-wrapper" id="pageWrapper">

  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <img src="/assets/logo.png" alt="Logo" onerror="this.style.display='none'">
      <div class="brand-text">PERKASA <span>SOLUSINDO</span></div>
    </div>

    <div class="sidebar-profile">
      <div class="avatar"><?= $initial ?></div>
      <div class="name"><?= $firstname . ' ' . $lastname ?></div>
      <div class="email"><?= htmlspecialchars($_SESSION['user_email']) ?></div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section-label">Menu</div>
      <a href="/client/client_dashboard.php" class="nav-item <?= $view === 'dashboard' ? 'active' : '' ?>">
        <i class="fa-solid fa-house"></i> Dashboard
      </a>

      <div class="nav-section-label">Layanan Saya</div>
      <a href="/client/client_dashboard.php?view=layanan_wifi"
         class="nav-item <?= ($view === 'layanan_wifi' || $view === 'detail') ? 'active' : '' ?>">
        <i class="fa-solid fa-wifi"></i> Layanan WiFi
        <?php if ($tagihanAktif && $tagihanAktif['status'] === 'unpaid'): ?>
          <span class="badge-count">!</span>
        <?php endif; ?>
      </a>
      <a href="/client/client_dashboard.php?view=layanan_hosting"
         class="nav-item <?= $view === 'layanan_hosting' ? 'active' : '' ?>">
        <i class="fa-solid fa-server"></i> Layanan Hosting
        <?php if ($hostingActiveCount > 0): ?>
          <span class="badge-count" style="background:var(--pink);color:#fff;font-size:.65rem;min-width:18px;text-align:center;"><?= $hostingActiveCount ?></span>
        <?php endif; ?>
      </a>

      <div class="nav-section-label">Keuangan</div>
      <a href="/client/client_dashboard.php?view=invoices" class="nav-item <?= $view === 'invoices' ? 'active' : '' ?>">
        <i class="fa-solid fa-file-invoice"></i> Invoice &amp; Tagihan
      </a>
    </nav>

    <div class="sidebar-footer">
      <button class="logout-btn" onclick="document.getElementById('logoutModal').classList.add('active')">
        <i class="fa-solid fa-right-from-bracket"></i> Logout
      </button>
    </div>
  </aside>

  <div class="sidebar-overlay" id="sidebarOverlay"></div>

  <!-- MAIN CONTENT -->
  <div class="main-content">

    <!-- TOPBAR -->
    <header class="topbar">
      <div class="topbar-left">
        <button class="menu-toggle" id="menuToggle"><i class="fa-solid fa-bars"></i></button>
        <div class="page-title"><?php
          if ($view === 'dashboard') echo 'Dashboard';
          elseif ($view === 'layanan_hosting') echo 'Layanan Hosting';
          elseif ($view === 'invoices') echo 'Invoice & Tagihan';
          else echo 'Layanan WiFi';
        ?></div>
      </div>
      <div class="topbar-right">
        <span class="topbar-greeting">Halo, <span><?= $firstname ?></span> 👋</span>

        <!-- NOTIFICATION BELL -->
        <div class="notif-wrap" id="notifWrap">
          <button class="notif-bell-btn" id="notifBellBtn" title="Notifikasi" onclick="toggleNotifPanel(event)">
            <i class="fa-solid fa-bell"></i>
            <span class="notif-count <?= $notifUnread === 0 ? 'hidden' : '' ?>" id="notifCount">
              <?= $notifUnread > 9 ? '9+' : $notifUnread ?>
            </span>
          </button>

          <div class="notif-panel" id="notifPanel">
            <div class="notif-panel-header">
              <h4><i class="fa-solid fa-bell" style="color:var(--pink-light);margin-right:7px;font-size:.85rem;"></i> Notifikasi</h4>
              <?php if ($notifUnread > 0): ?>
              <button class="notif-mark-all" onclick="markAllRead()">Tandai semua dibaca</button>
              <?php endif; ?>
            </div>
            <div class="notif-list" id="notifList">
              <?php if (empty($notifList)): ?>
                <div class="notif-empty">
                  <i class="fa-solid fa-bell-slash"></i>
                  Belum ada notifikasi.
                </div>
              <?php else: ?>
                <?php foreach ($notifList as $notif): ?>
                  <?php
                    $isUnread = !(int)$notif['sudah_dibaca'];
                    $tipe     = $notif['tipe'] ?? 'info';
                  ?>
                  <div class="notif-item <?= $isUnread ? 'unread' : '' ?>"
                       data-id="<?= (int)$notif['id'] ?>"
                       onclick="markRead(this, <?= (int)$notif['id'] ?>)">
                    <div class="notif-dot-wrap <?= htmlspecialchars($tipe) ?>">
                      <i class="<?= notifIcon($tipe) ?>"></i>
                    </div>
                    <div class="notif-text">
                      <div class="notif-title"><?= htmlspecialchars($notif['judul']) ?></div>
                      <div class="notif-body"><?= htmlspecialchars($notif['pesan']) ?></div>
                      <div class="notif-time"><?= timeAgo($notif['created_at']) ?></div>
                    </div>
                    <?php if ($isUnread): ?>
                      <div class="unread-dot"></div>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <!-- /NOTIFICATION BELL -->
      </div>
    </header>

    <div class="dashboard-body">

      <?php if ($view === 'dashboard'): ?>
      <!-- ================= DASHBOARD UTAMA ================= -->

      <?php
        // Hitung sisa hari wifi untuk dashboard
        $wifiExpireTs    = !empty($wifiSummary['tanggal_expire']) ? strtotime($wifiSummary['tanggal_expire']) : null;
        $wifiSisaHari    = $wifiExpireTs ? (int)ceil(($wifiExpireTs - strtotime(date('Y-m-d'))) / 86400) : null;
        $wifiExpireLabel = $wifiExpireTs ? date('d M Y', $wifiExpireTs) : '-';
        $wifiStatus      = $wifiSummary['wifi_status'] ?? null;

        // Hitung hosting aktif & warning
        $hostingAktifCount   = 0;
        $hostingWarningCount = 0;
        foreach ($hostingSummary as $hs) {
            if ($hs['domainstatus'] === 'Active') $hostingAktifCount++;
            if (!empty($hs['nextduedate'])) {
                $nd = (int)ceil((strtotime($hs['nextduedate']) - strtotime(date('Y-m-d'))) / 86400);
                if ($nd <= 14) $hostingWarningCount++;
            }
        }
      ?>

      <!-- Welcome Banner -->
      <div class="welcome-banner" style="background:linear-gradient(135deg,rgba(192,0,122,.18),rgba(90,0,96,.28));border:1px solid rgba(255,77,206,.15);">
        <div>
          <h2>Selamat Datang, <span><?= $firstname ?></span>! 👋</h2>
          <p style="margin-top:6px;color:var(--text-muted);font-size:.9rem;">
            <?= date('l, d F Y') ?> &mdash; Ringkasan akun dan layanan Anda.
          </p>
        </div>
      </div>

      <!-- STAT CARDS -->
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:24px;">

        <!-- WiFi Status -->
        <?php
          $wsBg  = !$wifiSummary ? 'rgba(255,255,255,.04)' : ($wifiStatus === 'active' ? 'rgba(34,197,94,.08)' : 'rgba(251,191,36,.07)');
          $wsBd  = !$wifiSummary ? 'var(--card-border)'    : ($wifiStatus === 'active' ? 'rgba(74,222,128,.25)' : 'rgba(251,191,36,.25)');
          $wsIcn = !$wifiSummary ? '#888'                  : ($wifiStatus === 'active' ? '#86efac' : '#fde68a');
        ?>
        <div style="background:<?= $wsBg ?>;border:1px solid <?= $wsBd ?>;border-radius:var(--radius);padding:20px 22px;display:flex;flex-direction:column;gap:10px;">
          <div style="display:flex;align-items:center;justify-content:space-between;">
            <span style="font-size:.75rem;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);">Layanan WiFi</span>
            <i class="fa-solid fa-wifi" style="font-size:1.1rem;color:<?= $wsIcn ?>;"></i>
          </div>
          <?php if ($wifiSummary): ?>
            <div style="font-size:1.15rem;font-weight:700;color:var(--text-main);"><?= htmlspecialchars($wifiSummary['product_name'] ?? '-') ?></div>
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
              <span class="badge <?= wifiStatusBadgeClass($wifiStatus) ?>"><?= wifiStatusLabel($wifiStatus) ?></span>
              <?php if ($wifiSisaHari !== null && $wifiStatus === 'active'): ?>
                <span style="font-size:.76rem;color:<?= $wifiSisaHari <= 7 ? '#fde68a' : 'var(--text-muted)' ?>;">
                  Exp. <?= $wifiExpireLabel ?>
                </span>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <div style="font-size:.88rem;color:var(--text-muted);">Belum ada layanan</div>
            <a href="/order/order_wifi.php" style="font-size:.78rem;color:var(--pink-light);text-decoration:underline;">Order sekarang →</a>
          <?php endif; ?>
        </div>

        <!-- Hosting Status -->
        <?php
          $hsBgC = $hostingAktifCount === 0 ? 'rgba(255,255,255,.04)' : ($hostingWarningCount > 0 ? 'rgba(251,191,36,.07)' : 'rgba(99,102,241,.08)');
          $hsBdC = $hostingAktifCount === 0 ? 'var(--card-border)'    : ($hostingWarningCount > 0 ? 'rgba(251,191,36,.25)' : 'rgba(129,140,248,.25)');
          $hsIcC = $hostingAktifCount === 0 ? '#888'                  : ($hostingWarningCount > 0 ? '#fde68a' : '#c7d2fe');
        ?>
        <div style="background:<?= $hsBgC ?>;border:1px solid <?= $hsBdC ?>;border-radius:var(--radius);padding:20px 22px;display:flex;flex-direction:column;gap:10px;">
          <div style="display:flex;align-items:center;justify-content:space-between;">
            <span style="font-size:.75rem;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);">Hosting Aktif</span>
            <i class="fa-solid fa-server" style="font-size:1.1rem;color:<?= $hsIcC ?>;"></i>
          </div>
          <div style="font-size:2rem;font-weight:800;color:var(--text-main);line-height:1;"><?= $hostingAktifCount ?></div>
          <div style="font-size:.82rem;color:var(--text-muted);">
            <?php if ($hostingAktifCount === 0): ?>
              Belum ada hosting &mdash; <a href="/order/order_hosting.php" style="color:var(--pink-light);text-decoration:underline;">Order →</a>
            <?php elseif ($hostingWarningCount > 0): ?>
              <span style="color:#fde68a;"><i class="fa-solid fa-triangle-exclamation"></i> <?= $hostingWarningCount ?> segera jatuh tempo</span>
            <?php else: ?>
              Semua layanan berjalan normal
            <?php endif; ?>
          </div>
        </div>

        <!-- Tagihan WiFi -->
        <?php
          $tgBg = !$tagihanDashboard ? 'rgba(255,255,255,.04)' : ($tagihanDashboard['status'] === 'unpaid' ? 'rgba(239,68,68,.08)' : 'rgba(251,191,36,.07)');
          $tgBd = !$tagihanDashboard ? 'var(--card-border)'    : ($tagihanDashboard['status'] === 'unpaid' ? 'rgba(248,113,113,.25)' : 'rgba(251,191,36,.25)');
          $tgIc = !$tagihanDashboard ? '#888'                  : ($tagihanDashboard['status'] === 'unpaid' ? '#fca5a5' : '#fde68a');
        ?>
        <div style="background:<?= $tgBg ?>;border:1px solid <?= $tgBd ?>;border-radius:var(--radius);padding:20px 22px;display:flex;flex-direction:column;gap:10px;">
          <div style="display:flex;align-items:center;justify-content:space-between;">
            <span style="font-size:.75rem;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);">Tagihan WiFi</span>
            <i class="fa-solid fa-file-invoice-dollar" style="font-size:1.1rem;color:<?= $tgIc ?>;"></i>
          </div>
          <?php if ($tagihanDashboard): ?>
            <div style="font-size:1rem;font-weight:700;color:var(--text-main);">
              <?= $tagihanDashboard['status'] === 'unpaid' ? '⚠️ Ada Tagihan' : '🕐 Menunggu Konfirmasi' ?>
            </div>
            <div style="font-size:.82rem;color:var(--text-muted);">
              Jatuh tempo: <?= fmtTanggal($tagihanDashboard['due_date']) ?>
            </div>
            <a href="/client/client_dashboard.php?view=layanan_wifi" style="font-size:.78rem;color:var(--pink-light);text-decoration:underline;">Bayar sekarang →</a>
          <?php elseif ($wifiSummary && $wifiStatus === 'active'): ?>
            <div style="font-size:1rem;font-weight:700;color:#86efac;">✅ Lunas</div>
            <div style="font-size:.82rem;color:var(--text-muted);">Tidak ada tagihan aktif</div>
          <?php else: ?>
            <div style="font-size:.88rem;color:var(--text-muted);">Tidak ada tagihan</div>
          <?php endif; ?>
        </div>

        <!-- Notifikasi -->
        <div style="background:rgba(255,255,255,.04);border:1px solid var(--card-border);border-radius:var(--radius);padding:20px 22px;display:flex;flex-direction:column;gap:10px;">
          <div style="display:flex;align-items:center;justify-content:space-between;">
            <span style="font-size:.75rem;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);">Notifikasi</span>
            <i class="fa-solid fa-bell" style="font-size:1.1rem;color:<?= $notifUnread > 0 ? 'var(--pink-light)' : '#888' ?>;"></i>
          </div>
          <div style="font-size:2rem;font-weight:800;color:var(--text-main);line-height:1;"><?= $notifUnread ?></div>
          <div style="font-size:.82rem;color:var(--text-muted);">
            <?= $notifUnread > 0 ? 'Notifikasi belum dibaca' : 'Semua sudah dibaca' ?>
          </div>
        </div>

      </div>
      <!-- /STAT CARDS -->

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start;">
        <!-- Kiri: Ringkasan Layanan -->
        <div style="display:flex;flex-direction:column;gap:16px;">

          <!-- Ringkasan WiFi -->
          <div class="card" style="margin-bottom:0;">
            <div class="card-header">
              <h3><i class="fa-solid fa-wifi" style="color:var(--pink-light);margin-right:6px;"></i> Layanan WiFi</h3>
              <a href="/client/client_dashboard.php?view=layanan_wifi" style="font-size:.78rem;color:var(--pink-light);">Lihat Detail →</a>
            </div>
            <div class="card-body" style="padding:18px 20px;">
              <?php if ($wifiSummary): ?>
                <div class="profile-grid" style="grid-template-columns:1fr 1fr;gap:12px 20px;">
                  <div>
                    <div class="label">Paket</div>
                    <div class="value" style="font-size:.9rem;"><?= htmlspecialchars($wifiSummary['product_name'] ?? '-') ?></div>
                  </div>
                  <div>
                    <div class="label">Kecepatan</div>
                    <div class="value" style="font-size:.9rem;"><?= htmlspecialchars($wifiSummary['speed'] ?? '-') ?></div>
                  </div>
                  <div>
                    <div class="label">Status</div>
                    <div class="value"><span class="badge <?= wifiStatusBadgeClass($wifiStatus) ?>"><?= wifiStatusLabel($wifiStatus) ?></span></div>
                  </div>
                  <div>
                    <div class="label">Aktif Sampai</div>
                    <div class="value" style="font-size:.9rem;<?= ($wifiSisaHari !== null && $wifiSisaHari <= 7) ? 'color:#fde68a;font-weight:700;' : '' ?>">
                      <?= $wifiExpireLabel ?>
                    </div>
                  </div>
                  <?php if (!empty($wifiSummary['id_pelanggan'])): ?>
                  <div>
                    <div class="label">ID Pelanggan</div>
                    <div class="value" style="font-size:.9rem;font-family:monospace;color:var(--pink-light);"><?= htmlspecialchars($wifiSummary['id_pelanggan']) ?></div>
                  </div>
                  <?php endif; ?>
                  <div>
                    <div class="label">Harga</div>
                    <div class="value" style="font-size:.9rem;"><?= fmtRupiah($wifiSummary['price'] ?? 0) ?>/<?= htmlspecialchars($wifiSummary['period'] ?? 'bulan') ?></div>
                  </div>
                </div>
              <?php else: ?>
                <div style="text-align:center;padding:20px 0;color:var(--text-muted);font-size:.88rem;">
                  <i class="fa-solid fa-wifi" style="font-size:1.6rem;margin-bottom:10px;display:block;opacity:.3;"></i>
                  Belum ada layanan WiFi.<br>
                  <a href="/order/order_wifi.php" style="color:var(--pink-light);text-decoration:underline;font-size:.82rem;">Order WiFi →</a>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Ringkasan Hosting -->
          <div class="card" style="margin-bottom:0;">
            <div class="card-header">
              <h3><i class="fa-solid fa-server" style="color:#c7d2fe;margin-right:6px;"></i> Hosting</h3>
              <a href="/client/client_dashboard.php?view=layanan_hosting" style="font-size:.78rem;color:var(--pink-light);">Lihat Semua →</a>
            </div>
            <div class="card-body" style="padding:0;">
              <?php if (empty($hostingSummary)): ?>
                <div style="text-align:center;padding:24px;color:var(--text-muted);font-size:.88rem;">
                  <i class="fa-solid fa-server" style="font-size:1.6rem;margin-bottom:10px;display:block;opacity:.3;"></i>
                  Belum ada layanan hosting.<br>
                  <a href="/order/order_hosting.php" style="color:var(--pink-light);text-decoration:underline;font-size:.82rem;">Order Hosting →</a>
                </div>
              <?php else: ?>
                <?php foreach ($hostingSummary as $hs): ?>
                  <?php
                    $hsSisa = null;
                    if (!empty($hs['nextduedate'])) {
                        $hsSisa = (int)ceil((strtotime($hs['nextduedate']) - strtotime(date('Y-m-d'))) / 86400);
                    }
                  ?>
                  <div class="list-item" style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                    <div style="flex:1;min-width:0;">
                      <div style="font-size:.86rem;font-weight:600;color:var(--text-main);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                        <?= htmlspecialchars($hs['domain']) ?>
                      </div>
                      <div style="font-size:.76rem;color:var(--text-muted);"><?= htmlspecialchars($hs['package_name'] ?? '-') ?></div>
                    </div>
                    <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;">
                      <span class="badge <?= hostingStatusBadgeClass($hs['domainstatus']) ?>"><?= hostingStatusLabel($hs['domainstatus']) ?></span>
                      <?php if ($hsSisa !== null): ?>
                        <span style="font-size:.72rem;color:<?= $hsSisa <= 14 ? '#fde68a' : 'var(--text-muted)' ?>;">
                          <?= $hsSisa > 0 ? $hsSisa . ' hari lagi' : 'Lewat jatuh tempo' ?>
                        </span>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>

        </div>
        <!-- /Kiri -->

        <!-- Kanan: Pengumuman -->
        <div class="card" style="margin-bottom:0;">
          <div class="card-header">
            <h3><i class="fa-solid fa-bullhorn" style="color:#fde68a;margin-right:6px;"></i> Pengumuman</h3>
          </div>
          <div class="card-body" style="padding:0;">
            <?php if (empty($announcements)): ?>
              <div style="text-align:center;padding:36px 20px;color:var(--text-muted);">
                <i class="fa-solid fa-bullhorn" style="font-size:1.8rem;opacity:.25;display:block;margin-bottom:12px;"></i>
                <div style="font-size:.86rem;">Tidak ada pengumuman saat ini.</div>
              </div>
            <?php else: ?>
              <?php foreach ($announcements as $ann): ?>
                <div style="padding:18px 20px;border-bottom:1px solid var(--card-border);">
                  <div style="display:flex;align-items:flex-start;gap:12px;">
                    <div style="width:36px;height:36px;flex-shrink:0;border-radius:10px;
                                background:rgba(251,191,36,.12);border:1px solid rgba(251,191,36,.25);
                                display:flex;align-items:center;justify-content:center;
                                color:#fde68a;font-size:.9rem;margin-top:2px;">
                      <i class="fa-solid fa-bullhorn"></i>
                    </div>
                    <div style="flex:1;min-width:0;">
                      <div style="font-size:.9rem;font-weight:700;color:var(--text-main);margin-bottom:5px;line-height:1.4;">
                        <?= htmlspecialchars($ann['title']) ?>
                      </div>
                      <div style="font-size:.82rem;color:var(--text-muted);line-height:1.6;margin-bottom:8px;"
                           id="ann-body-<?= $ann['id'] ?>-preview">
                        <?php
                          $stripped = strip_tags($ann['announcement']);
                          $preview  = mb_strlen($stripped) > 150 ? mb_substr($stripped, 0, 150) . '…' : $stripped;
                          echo htmlspecialchars($preview);
                        ?>
                      </div>
                      <?php if (mb_strlen(strip_tags($ann['announcement'])) > 150): ?>
                        <button onclick="toggleAnn(<?= $ann['id'] ?>)"
                                id="ann-toggle-<?= $ann['id'] ?>"
                                style="background:none;border:none;color:var(--pink-light);font-size:.78rem;cursor:pointer;padding:0;text-decoration:underline;">
                          Baca selengkapnya
                        </button>
                        <div id="ann-full-<?= $ann['id'] ?>" style="display:none;font-size:.82rem;color:var(--text-muted);line-height:1.6;margin-top:6px;">
                          <?= nl2br(htmlspecialchars(strip_tags($ann['announcement']))) ?>
                        </div>
                      <?php endif; ?>
                      <div style="font-size:.72rem;color:var(--text-muted);margin-top:8px;">
                        <i class="fa-regular fa-clock" style="margin-right:4px;"></i><?= fmtTanggal($ann['date'], false) ?>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
        <!-- /Kanan -->

      </div>
      <!-- /Grid 2 kolom -->

      <?php elseif ($view !== 'layanan_hosting'): ?>
      <?php if ($detailOrder): ?>
      <!-- ================= LAYANAN WIFI ================= -->

      <div class="welcome-banner">
        <div>
          <h2><?= htmlspecialchars($detailOrder['product_name'] ?? '-') ?></h2>
          <p>Order #<?= htmlspecialchars($detailOrder['order_number']) ?> &middot; Dibuat <?= fmtTanggal($detailOrder['created_at'], true) ?></p>
        </div>
      </div>

      <div class="cards-grid">

        <!-- Info Paket -->
        <div class="card">
          <div class="card-header">
            <h3><i class="fa-solid fa-wifi"></i> Informasi Paket</h3>
            <span class="badge <?= wifiStatusBadgeClass($detailOrder['wifi_status']) ?>">
              <?= wifiStatusLabel($detailOrder['wifi_status']) ?>
            </span>
          </div>
          <div class="card-body" style="padding:16px 20px;">

            <?php if ($detailOrder['wifi_status'] === 'active' && !empty($detailOrder['id_pelanggan'])): ?>
            <!-- ID Pelanggan banner -->
            <div class="id-pelanggan-banner">
              <div class="idp-icon"><i class="fa-solid fa-id-card"></i></div>
              <div>
                <div class="idp-label">ID Pelanggan</div>
                <div class="idp-value" id="idPelanggan"><?= htmlspecialchars($detailOrder['id_pelanggan']) ?></div>
              </div>
              <button class="idp-copy" onclick="copyText('idPelanggan', this)" title="Salin ID">
                <i class="fa-regular fa-copy"></i> Salin
              </button>
            </div>
            <?php endif; ?>

            <div class="profile-grid">
              <div>
                <div class="label">Nama Paket</div>
                <div class="value"><?= htmlspecialchars($detailOrder['product_name'] ?? '-') ?></div>
              </div>
              <div>
                <div class="label">Kecepatan</div>
                <div class="value"><?= htmlspecialchars($detailOrder['speed'] ?? '-') ?></div>
              </div>
              <div>
                <div class="label">Harga</div>
                <div class="value"><?= fmtRupiah($detailOrder['price'] ?? 0) ?> / <?= htmlspecialchars($detailOrder['period'] ?? 'bulan') ?></div>
              </div>
              <div>
                <div class="label">Status Pembayaran</div>
                <div class="value">
                  <span class="badge <?= paymentStatusBadgeClass($detailOrder['payment_status']) ?>">
                    <?= paymentStatusLabel($detailOrder['payment_status']) ?>
                  </span>
                </div>
              </div>
              <?php if (!empty($detailOrder['payment_proof'])): ?>
              <div>
                <div class="label">Bukti Pembayaran</div>
                <div class="value">
                  <a href="/order/order_asset/bukti_pembayaran/<?= htmlspecialchars($detailOrder['payment_proof']) ?>" target="_blank" style="color: var(--pink-light); text-decoration: underline;">
                    <i class="fa-solid fa-file-image"></i> Lihat File
                  </a>
                </div>
              </div>
              <?php endif; ?>
              <div>
                <div class="label">Jadwal Instalasi</div>
                <div class="value"><?= fmtTanggal($detailOrder['jadwal_instalasi'], true) ?></div>
              </div>
              <div>
                <div class="label">Tanggal Aktif</div>
                <div class="value"><?= fmtTanggal($detailOrder['tgl_aktif']) ?></div>
              </div>
            </div>
          </div>
        </div>

        <?php if ($detailOrder['wifi_status'] === 'active'): ?>
        <!-- ── Masa Aktif WiFi ── -->
        <div class="card">
          <div class="card-header">
            <h3><i class="fa-solid fa-calendar-check"></i> Masa Aktif Layanan</h3>
          </div>
          <div class="card-body" style="padding:20px 22px;">
            <?php
              $expireTs   = !empty($detailOrder['tanggal_expire']) ? strtotime($detailOrder['tanggal_expire']) : null;
              $todayTs    = strtotime(date('Y-m-d'));
              $sisaHari   = $expireTs ? (int)ceil(($expireTs - $todayTs) / 86400) : null;
              $expireLabel = $expireTs ? date('d M Y', $expireTs) : '-';
              $isWarning  = $sisaHari !== null && $sisaHari <= 20 && $sisaHari > 0;
              $isExpired  = $sisaHari !== null && $sisaHari <= 0;
            ?>
            <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
              <div style="flex:1;min-width:160px;">
                <div class="label">Aktif sampai</div>
                <div class="value" style="font-size:1.2rem;font-weight:700;
                  color:<?= $isExpired ? '#f87171' : ($isWarning ? '#fbbf24' : '#4ade80') ?>">
                  <i class="fa-solid fa-circle" style="font-size:.6rem;vertical-align:middle;margin-right:6px;"></i>
                  <?= $expireLabel ?>
                </div>
              </div>
              <?php if ($sisaHari !== null): ?>
              <div style="text-align:center;min-width:80px;">
                <div style="font-size:2rem;font-weight:800;line-height:1;
                  color:<?= $isExpired ? '#f87171' : ($isWarning ? '#fbbf24' : '#4ade80') ?>">
                  <?= $isExpired ? '0' : $sisaHari ?>
                </div>
                <div class="label" style="margin-top:4px;">
                  <?= $isExpired ? 'Layanan berakhir' : 'hari lagi' ?>
                </div>
              </div>
              <?php endif; ?>
            </div>
            <?php if ($isWarning && !$tagihanAktif): ?>
            <div style="margin-top:14px;padding:10px 14px;background:rgba(251,191,36,.08);border:1px solid rgba(251,191,36,.25);border-radius:8px;font-size:.83rem;color:#fde68a;line-height:1.6;">
              <i class="fa-solid fa-triangle-exclamation"></i>
              Layanan Anda akan berakhir dalam <strong><?= $sisaHari ?> hari</strong>. Tagihan perpanjangan akan muncul di sini setelah tanggal 1.
            </div>
            <?php elseif ($isExpired): ?>
            <div style="margin-top:14px;padding:10px 14px;background:rgba(248,113,113,.08);border:1px solid rgba(248,113,113,.25);border-radius:8px;font-size:.83rem;color:#fca5a5;line-height:1.6;">
              <i class="fa-solid fa-circle-xmark"></i>
              Masa aktif layanan Anda telah berakhir. Segera bayar tagihan perpanjangan atau hubungi admin.
            </div>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($tagihanAktif): ?>
        <!-- ── Tagihan Perpanjangan Bulanan ── -->
        <div class="card card-full">
          <div class="card-header">
            <h3><i class="fa-solid fa-file-invoice-dollar"></i> Tagihan Perpanjangan WiFi</h3>
            <span class="badge <?= $tagihanAktif['status'] === 'waiting_confirm' ? 'badge-pending' : 'badge-suspend' ?>">
              <?= $tagihanAktif['status'] === 'waiting_confirm' ? 'Menunggu Konfirmasi' : 'Belum Dibayar' ?>
            </span>
          </div>
          <div class="card-body" style="padding:20px 22px;">

            <?php if ($tagihanFallback): ?>
            <div class="tagihan-info-alert">
              <i class="fa-solid fa-circle-info"></i>
              Tagihan perpanjangan bulan ini sudah tersedia. Silakan transfer sesuai nominal di bawah dan upload bukti pembayaran.
            </div>
            <?php endif; ?>

            <!-- Info tagihan -->
            <div class="profile-grid" style="margin-bottom:20px;">
              <div>
                <div class="label">Batas Pembayaran</div>
                <div class="value" style="color:#fbbf24;font-weight:700;"><?= fmtTanggal($tagihanAktif['due_date']) ?></div>
              </div>
              <div>
                <div class="label">Akan Disuspend Jika Belum Bayar</div>
                <div class="value" style="color:#f87171;"><?= fmtTanggal($tagihanAktif['suspend_date']) ?></div>
              </div>
              <div>
                <div class="label">Layanan Aktif Hingga (jika bayar)</div>
                <div class="value" style="color:#4ade80;font-weight:700;"><?= fmtTanggal($tagihanAktif['new_expire']) ?></div>
              </div>
              <div>
                <div class="label">Total Tagihan</div>
                <div class="value" style="font-size:1.15rem;font-weight:800;color:#fbbf24;"><?= fmtRupiah($detailOrder['price']) ?></div>
              </div>
            </div>

            <!-- Rekening pembayaran -->
            <div class="rekening-box">
              <div class="rek-title"><i class="fa-solid fa-building-columns" style="margin-right:6px;"></i>Rekening Pembayaran</div>
              <div class="rek-row">
                <span class="rek-bank">BCA</span>
                <span class="rek-norek" id="norekBCA">0184246283</span>
                <span class="rek-an">a.n. <strong style="color:#c7d2fe;">TECH PERKASA SOLUSINDO</strong></span>
                <button class="rek-copy" onclick="copyText('norekBCA', this)" title="Salin nomor rekening">
                  <i class="fa-regular fa-copy"></i> Salin
                </button>
              </div>
            </div>

            <?php if ($tagihanAktif['status'] === 'waiting_confirm'): ?>
            <!-- Sudah upload, tunggu konfirmasi -->
            <div style="padding:14px 16px;background:rgba(74,222,128,.07);border:1px solid rgba(74,222,128,.2);border-radius:10px;font-size:.84rem;color:#86efac;line-height:1.6;">
              <i class="fa-solid fa-circle-check"></i>
              <strong>Bukti pembayaran berhasil diupload.</strong> Admin sedang memverifikasi. Layanan akan diperpanjang otomatis setelah konfirmasi.
            </div>
            <?php else: ?>
            <!-- Form upload bukti tagihan bulanan -->
            <div id="upload-tagihan-msg" style="display:none;margin-bottom:12px;"></div>
            <p style="font-size:.83rem;color:var(--text-muted);margin-bottom:12px;line-height:1.6;">
              Upload bukti transfer sesuai nominal tagihan di atas (JPG, PNG, atau PDF · maks. 5MB). Setelah dikonfirmasi admin, masa aktif WiFi Anda akan diperpanjang otomatis.
            </p>
            <form id="form-upload-tagihan" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
              <input type="hidden" name="monthly_id" value="<?= (int)$tagihanAktif['id'] ?>">
              <input type="hidden" name="order_id"   value="<?= (int)$detailOrder['id'] ?>">
              <input type="file" name="bukti_tagihan" id="bukti-tagihan-file"
                     accept=".jpg,.jpeg,.png,.pdf" required
                     style="flex:1;min-width:180px;background:rgba(255,255,255,.07);border:1px solid var(--card-border);border-radius:8px;padding:9px 14px;color:var(--text-main);font-size:.83rem;font-family:inherit;">
              <button type="submit" class="btn-check" id="btn-upload-tagihan">
                <i class="fa-solid fa-upload"></i> Upload Bukti
              </button>
            </form>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>
        <?php endif; /* end wifi_status === active */ ?>

        <!-- Alamat Pemasangan -->
        <div class="card">
          <div class="card-header">
            <h3><i class="fa-solid fa-location-dot"></i> Alamat Pemasangan</h3>
          </div>
          <div class="profile-grid">
            <div style="grid-column: 1 / -1;">
              <div class="label">Alamat</div>
              <div class="value"><?= htmlspecialchars($detailOrder['alamat_pasang'] ?? '-') ?></div>
            </div>
            <div>
              <div class="label">RT / RW</div>
              <div class="value"><?= htmlspecialchars(($detailOrder['rt'] ?: '-') . ' / ' . ($detailOrder['rw'] ?: '-')) ?></div>
            </div>
            <div>
              <div class="label">Kode Pos</div>
              <div class="value"><?= htmlspecialchars($detailOrder['kodepos'] ?? '-') ?></div>
            </div>
            <div>
              <div class="label">Kelurahan</div>
              <div class="value"><?= htmlspecialchars($detailOrder['kelurahan'] ?? '-') ?></div>
            </div>
            <div>
              <div class="label">Kecamatan</div>
              <div class="value"><?= htmlspecialchars($detailOrder['kecamatan'] ?? '-') ?></div>
            </div>
            <div>
              <div class="label">Kota / Kabupaten</div>
              <div class="value"><?= htmlspecialchars($detailOrder['kota'] ?? '-') ?></div>
            </div>
            <div>
              <div class="label">Provinsi</div>
              <div class="value"><?= htmlspecialchars($detailOrder['provinsi'] ?? '-') ?></div>
            </div>
          </div>
        </div>

        <!-- Riwayat Status -->
        <div class="card card-full">
          <div class="card-header">
            <h3><i class="fa-solid fa-clock-rotate-left"></i> Riwayat Status</h3>
          </div>
          <div class="card-body" style="padding:0;">
            <?php if (empty($statusLog)): ?>
              <div class="empty-state">
                <i class="fa-solid fa-inbox"></i>
                Belum ada riwayat status.
              </div>
            <?php else: ?>
              <?php foreach (array_reverse($statusLog) as $log): ?>
                <div class="list-item">
                  <div class="item-info">
                    <div class="item-title"><?= wifiStatusLabel($log['new_status']) ?></div>
                    <?php if (!empty($log['catatan'])): ?>
                      <div class="item-sub"><?= htmlspecialchars($log['catatan']) ?></div>
                    <?php endif; ?>
                  </div>
                  <div class="item-sub" style="white-space:nowrap;"><?= fmtTanggal($log['created_at'], true) ?></div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($detailOrder['payment_status'] === 'belum_bayar'): ?>
        <!-- Upload Bukti Pembayaran (instalasi awal) -->
        <div class="card card-full">
          <div class="card-header">
            <h3><i class="fa-solid fa-receipt"></i> Upload Bukti Pembayaran Instalasi</h3>
          </div>
          <div class="card-body">
            <?php if (!empty($uploadError)): ?>
              <p style="font-size:.85rem; color:#f87171; margin-bottom:12px;">
                <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($uploadError) ?>
              </p>
            <?php endif; ?>

            <!-- Rekening instalasi -->
            <div class="rekening-box" style="margin-bottom:16px;">
              <div class="rek-title"><i class="fa-solid fa-building-columns" style="margin-right:6px;"></i>Rekening Pembayaran</div>
              <div class="rek-row">
                <span class="rek-bank">BCA</span>
                <span class="rek-norek" id="norekBCA2">0184246283</span>
                <span class="rek-an">a.n. <strong style="color:#c7d2fe;">TECH PERKASA SOLUSINDO</strong></span>
                <button class="rek-copy" onclick="copyText('norekBCA2', this)" title="Salin nomor rekening">
                  <i class="fa-regular fa-copy"></i> Salin
                </button>
              </div>
            </div>

            <p style="font-size:.85rem; color: var(--text-muted); margin-bottom:14px; line-height:1.6;">
              Silakan upload bukti transfer / pembayaran instalasi Anda (format JPG, JPEG, PNG, atau PDF, maks. 5MB).
              Admin akan menerima notifikasi dan memverifikasi pembayaran Anda.
            </p>
            <form action="/order/upload_bukti_pembayaran.php" method="POST" enctype="multipart/form-data" class="domain-form">
              <input type="hidden" name="order_id" value="<?= (int)$detailOrder['id'] ?>">
              <input type="file" name="bukti_pembayaran" accept=".jpg,.jpeg,.png,.pdf" required
                     style="flex:1; min-width:160px; background: rgba(255,255,255,.07); border: 1px solid var(--card-border); border-radius: 8px; padding: 9px 14px; color: var(--text-main); font-size: .85rem; font-family: inherit;">
              <button type="submit" class="btn-check"><i class="fa-solid fa-upload"></i> Upload</button>
            </form>
          </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($detailOrder['note'])): ?>
        <!-- Catatan Admin -->
        <div class="card card-full">
          <div class="card-header">
            <h3><i class="fa-solid fa-note-sticky"></i> Catatan</h3>
          </div>
          <div class="card-body">
            <p style="font-size:.86rem; color: var(--text-muted); line-height:1.6;">
              <?= nl2br(htmlspecialchars($detailOrder['note'])) ?>
            </p>
          </div>
        </div>
        <?php endif; ?>

      </div>

      <?php else: ?>
      <!-- ================= BELUM PUNYA LAYANAN WIFI ================= -->

      <div class="welcome-banner">
        <div>
          <h2>Layanan <span>WiFi</span> Saya</h2>
          <p>Anda belum memiliki layanan WiFi yang terdaftar.</p>
        </div>
      </div>

      <div class="cards-grid">
        <div class="card card-full">
          <div class="card-body" style="padding:0;">
            <div class="empty-state">
              <i class="fa-solid fa-wifi"></i>
              Anda belum memiliki layanan WiFi.<br>
              Silakan ajukan pesanan melalui menu Order WiFi.
            </div>
          </div>
        </div>
      </div>

      <?php endif; // if ($detailOrder) ... else — wifi section ?>

    <?php endif; // view !== layanan_hosting (wifi block) ?>

    <?php if ($view === 'layanan_hosting'): ?>
    <!-- ================= LAYANAN HOSTING ================= -->

    <div class="welcome-banner">
      <div>
        <h2>Layanan <span>Hosting</span> Saya</h2>
        <p>Kelola hosting, domain, dan tagihan layanan hosting Anda.</p>
      </div>
    </div>

    <div class="cards-grid">

    <?php if (empty($hostingList)): ?>
      <!-- Belum punya hosting -->
      <div class="card card-full">
        <div class="card-body" style="padding:40px 24px;">
          <div class="empty-state" style="gap:20px;">
            <div style="width:72px;height:72px;border-radius:50%;
                        background:linear-gradient(135deg,rgba(192,0,122,.25),rgba(90,0,96,.35));
                        border:1px solid rgba(255,77,206,.2);
                        display:flex;align-items:center;justify-content:center;
                        font-size:1.8rem;color:var(--pink-light);">
              <i class="fa-solid fa-server"></i>
            </div>
            <div style="text-align:center;">
              <div style="font-size:1.05rem;font-weight:700;color:var(--text-main);margin-bottom:8px;">
                Anda Belum Memiliki Layanan Hosting
              </div>
              <div style="font-size:.86rem;color:var(--text-muted);line-height:1.6;max-width:380px;">
                Dapatkan hosting cepat & handal dengan SSL gratis, email hosting, dan control panel DirectAdmin mudah digunakan.
              </div>
            </div>
            <a href="/order/order_hosting.php"
               style="display:inline-flex;align-items:center;gap:10px;
                      padding:12px 28px;
                      background:linear-gradient(135deg,var(--pink),var(--bg-accent));
                      color:#fff;font-size:.9rem;font-weight:700;
                      border-radius:12px;text-decoration:none;
                      box-shadow:0 4px 20px rgba(192,0,122,.35);
                      transition:opacity .2s,transform .2s;"
               onmouseover="this.style.opacity='.85';this.style.transform='translateY(-2px)'"
               onmouseout="this.style.opacity='1';this.style.transform='translateY(0)'">
              <i class="fa-solid fa-cart-plus"></i> Order Hosting Sekarang
            </a>
          </div>
        </div>
      </div>

    <?php else: ?>
      <?php foreach ($hostingList as $h): ?>
      <?php
        $isDomainBeli   = $h['domain_type'] === 'beli';
        $sisaHariHost   = null;
        $expireLabelHost = '-';
        $isWarningHost  = false;
        $isExpiredHost  = false;
        if (!empty($h['nextduedate']) && $h['nextduedate'] !== '0000-00-00') {
            $expTs         = strtotime($h['nextduedate']);
            $todayTs       = strtotime(date('Y-m-d'));
            $sisaHariHost  = (int)ceil(($expTs - $todayTs) / 86400);
            $expireLabelHost = date('d M Y', $expTs);
            $isWarningHost = $sisaHariHost <= 14 && $sisaHariHost > 0;
            $isExpiredHost = $sisaHariHost <= 0;
        }

        // ── Countdown deadline pembayaran (hanya relevan saat hosting masih Pending & belum lunas) ──
        $isPendingPayment   = ($h['domainstatus'] === 'Pending') && !empty($h['order_payment_deadline']) && ($h['payment_status'] ?? '') !== 'lunas';
        $deadlineTs         = $isPendingPayment ? strtotime($h['order_payment_deadline']) : null;
        $sisaMenitDeadline  = $deadlineTs ? (int)floor(($deadlineTs - time()) / 60) : null;
        $deadlineLewat      = $sisaMenitDeadline !== null && $sisaMenitDeadline <= 0;
        $deadlineLabel      = $deadlineTs ? date('d M Y H:i', $deadlineTs) : '-';

        // Cek apakah ada invoice unpaid untuk hosting ini
        $hasUnpaidInvoice = false;
        foreach ($invoiceHosting as $inv) {
            if (!empty($inv['order_number']) && strpos($h['domain'], '') !== false) {
                $hasUnpaidInvoice = true; break;
            }
        }
        // URL DirectAdmin (sesuaikan dengan server DirectAdmin Anda)
        $daUrl = 'https://perkasasolusindo.co.id:2222';
        $siteUrl = 'http://' . $h['domain'];
      ?>

      <!-- Kartu Hosting: <?= htmlspecialchars($h['domain']) ?> -->
      <div class="card card-full" style="margin-bottom:0;">
        <div class="card-header" style="flex-wrap:wrap;gap:10px;">
          <h3 style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <i class="fa-solid fa-server" style="color:var(--pink-light);"></i>
            <?= htmlspecialchars($h['package_name'] ?? 'Paket Hosting') ?>
            <span style="font-size:.78rem;font-weight:400;color:var(--text-muted);">
              — <?= htmlspecialchars($h['domain']) ?>
            </span>
          </h3>
          <span class="badge <?= hostingStatusBadgeClass($h['domainstatus']) ?>">
            <?= hostingStatusLabel($h['domainstatus']) ?>
          </span>
        </div>

        <div class="card-body" style="padding:20px;display:flex;flex-direction:column;gap:20px;">

          <!-- Status Bar: Hosting & Domain -->
          <div style="display:flex;flex-wrap:wrap;gap:12px;">

            <!-- Status Hosting -->
            <?php
              $hsBg    = ['Active'=>'rgba(34,197,94,.1)','Suspended'=>'rgba(239,68,68,.1)','Cancelled'=>'rgba(239,68,68,.1)','Terminated'=>'rgba(239,68,68,.1)','Pending'=>'rgba(251,191,36,.08)'];
              $hsBorder= ['Active'=>'rgba(74,222,128,.3)','Suspended'=>'rgba(248,113,113,.3)','Cancelled'=>'rgba(248,113,113,.3)','Terminated'=>'rgba(248,113,113,.3)','Pending'=>'rgba(251,191,36,.3)'];
              $hsColor = ['Active'=>'#86efac','Suspended'=>'#fca5a5','Cancelled'=>'#fca5a5','Terminated'=>'#fca5a5','Pending'=>'#fde68a'];
              $hsIcon  = ['Active'=>'fa-circle-check','Suspended'=>'fa-circle-pause','Cancelled'=>'fa-circle-xmark','Terminated'=>'fa-circle-xmark','Pending'=>'fa-clock'];
              $hs = $h['domainstatus'];
              $sbg = $hsBg[$hs]    ?? 'rgba(99,102,241,.1)';
              $sbd = $hsBorder[$hs]?? 'rgba(129,140,248,.3)';
              $scl = $hsColor[$hs] ?? '#c7d2fe';
              $sic = $hsIcon[$hs]  ?? 'fa-circle-info';
            ?>
            <div style="display:flex;align-items:center;gap:10px;
                        padding:12px 18px;border-radius:12px;
                        background:<?= $sbg ?>;border:1px solid <?= $sbd ?>;
                        flex:1;min-width:180px;">
              <div style="width:36px;height:36px;border-radius:50%;
                          background:<?= $sbd ?>;flex-shrink:0;
                          display:flex;align-items:center;justify-content:center;
                          color:<?= $scl ?>;font-size:.95rem;">
                <i class="fa-solid <?= $sic ?>"></i>
              </div>
              <div>
                <div style="font-size:.72rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px;">Status Hosting</div>
                <div style="font-size:.92rem;font-weight:700;color:<?= $scl ?>;"><?= hostingStatusLabel($hs) ?></div>
              </div>
            </div>

            <!-- Status Domain -->
            <?php
              $isDomainActive = ($h['domainstatus'] === 'Active');
              $dmbg  = $isDomainActive ? 'rgba(99,102,241,.1)'  : 'rgba(239,68,68,.1)';
              $dmbd  = $isDomainActive ? 'rgba(129,140,248,.35)': 'rgba(248,113,113,.3)';
              $dmcl  = $isDomainActive ? '#c7d2fe'              : '#fca5a5';
              $dmic  = $isDomainActive ? 'fa-globe'             : 'fa-globe';
              $dmLabel = $isDomainBeli ? 'Domain Berbayar'      : 'Subdomain Gratis';
              $dmVal   = $isDomainActive ? 'Terhubung'          : 'Tidak Aktif';
            ?>
            <div style="display:flex;align-items:center;gap:10px;
                        padding:12px 18px;border-radius:12px;
                        background:<?= $dmbg ?>;border:1px solid <?= $dmbd ?>;
                        flex:1;min-width:180px;">
              <div style="width:36px;height:36px;border-radius:50%;
                          background:<?= $dmbd ?>;flex-shrink:0;
                          display:flex;align-items:center;justify-content:center;
                          color:<?= $dmcl ?>;font-size:.95rem;">
                <i class="fa-solid <?= $dmic ?>"></i>
              </div>
              <div>
                <div style="font-size:.72rem;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px;">
                  <?= $dmLabel ?>
                </div>
                <div style="font-size:.88rem;font-weight:700;color:<?= $dmcl ?>;margin-bottom:2px;"><?= $dmVal ?></div>
                <div style="font-size:.76rem;color:var(--text-muted);word-break:break-all;"><?= htmlspecialchars($h['domain']) ?></div>
              </div>
            </div>

          </div>
          <!-- /Status Bar -->

          <?php if ($isPendingPayment): ?>
            <?php if ($deadlineLewat): ?>
            <div style="background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.35);border-radius:10px;padding:14px 18px;font-size:.85rem;color:#fca5a5;display:flex;align-items:center;gap:10px;">
              <i class="fa-solid fa-circle-exclamation"></i>
              <span>Batas waktu pembayaran <strong>sudah terlewat</strong>. Order ini akan segera dihapus otomatis oleh sistem. Hubungi admin jika Anda sudah membayar.</span>
            </div>
            <?php else: ?>
            <div style="background:rgba(251,191,36,.1);border:1px solid rgba(251,191,36,.35);border-radius:10px;padding:14px 18px;font-size:.85rem;color:#fde68a;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
              <i class="fa-solid fa-hourglass-half"></i>
              <span>
                Segera lakukan pembayaran sebelum <strong><?= $deadlineLabel ?></strong>
                (<strong><?= $sisaMenitDeadline >= 60 ? floor($sisaMenitDeadline/60) . ' jam ' . ($sisaMenitDeadline % 60) . ' menit' : $sisaMenitDeadline . ' menit' ?> lagi</strong>).
                Jika melewati batas waktu ini, order akan <strong>dihapus otomatis</strong> dari sistem.
              </span>
            </div>
            <?php endif; ?>
          <?php elseif ($isExpiredHost): ?>
          <div style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);border-radius:10px;padding:14px 18px;font-size:.85rem;color:#fca5a5;display:flex;align-items:center;gap:10px;">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span>Layanan hosting Anda <strong>sudah melewati tanggal jatuh tempo</strong>. Segera lakukan pembayaran perpanjangan untuk menghindari penangguhan.</span>
          </div>
          <?php elseif ($isWarningHost): ?>
          <div style="background:rgba(251,191,36,.08);border:1px solid rgba(251,191,36,.3);border-radius:10px;padding:14px 18px;font-size:.85rem;color:#fde68a;display:flex;align-items:center;gap:10px;">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <span>Layanan hosting akan <strong>jatuh tempo dalam <?= $sisaHariHost ?> hari</strong> (<?= $expireLabelHost ?>). Segera perpanjang untuk menghindari gangguan.</span>
          </div>
          <?php endif; ?>

          <?php if ($isPendingPayment): ?>
            <?php
              $hostingUploadError = ($hostingUploadErrorOid !== null && (int)$hostingUploadErrorOid === (int)($h['order_id'] ?? 0))
                  ? $hostingUploadErrorMsg : null;
            ?>
            <?php if (($h['payment_status'] ?? '') === 'sudah_bayar'): ?>
            <!-- Sudah upload, menunggu konfirmasi admin -->
            <div style="background:rgba(96,165,250,.1);border:1px solid rgba(96,165,250,.3);border-radius:12px;padding:16px 18px;font-size:.85rem;color:#93c5fd;display:flex;align-items:center;gap:10px;">
              <i class="fa-solid fa-hourglass-half"></i>
              <span><strong>Bukti pembayaran berhasil diupload.</strong> Admin sedang memverifikasi pembayaran Anda. Hosting akan diaktifkan setelah dikonfirmasi.</span>
            </div>
            <?php else: ?>
            <!-- Form upload bukti pembayaran hosting -->
            <div style="background:rgba(255,255,255,.03);border:1px solid var(--card-border);border-radius:12px;padding:18px 20px;">
              <div style="font-size:.85rem;font-weight:700;color:var(--text-main);margin-bottom:10px;display:flex;align-items:center;gap:8px;">
                <i class="fa-solid fa-receipt" style="color:var(--pink-light);"></i> Upload Bukti Pembayaran Hosting
              </div>

              <?php if (!empty($hostingUploadError)): ?>
                <p style="font-size:.82rem;color:#f87171;margin-bottom:10px;">
                  <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($hostingUploadError) ?>
                </p>
              <?php endif; ?>

              <div class="rekening-box" style="margin-bottom:14px;">
                <div class="rek-title"><i class="fa-solid fa-building-columns" style="margin-right:6px;"></i>Rekening Pembayaran</div>
                <div class="rek-row">
                  <span class="rek-bank">BCA</span>
                  <span class="rek-norek" id="norekBCAHost<?= (int)$h['id'] ?>">0184246283</span>
                  <span class="rek-an">a.n. <strong style="color:#c7d2fe;">TECH PERKASA SOLUSINDO</strong></span>
                  <button class="rek-copy" onclick="copyText('norekBCAHost<?= (int)$h['id'] ?>', this)" title="Salin nomor rekening">
                    <i class="fa-regular fa-copy"></i> Salin
                  </button>
                </div>
              </div>

              <p style="font-size:.82rem;color:var(--text-muted);margin-bottom:12px;line-height:1.6;">
                Upload bukti transfer pembayaran hosting Anda (format JPG, JPEG, PNG, atau PDF, maks. 5MB).
                Admin akan menerima notifikasi dan memverifikasi pembayaran Anda.
              </p>
              <form action="/order/upload_bukti_pembayaran.php" method="POST" enctype="multipart/form-data" class="domain-form">
                <input type="hidden" name="order_id" value="<?= (int)($h['order_id'] ?? 0) ?>">
                <input type="hidden" name="redirect_view" value="layanan_hosting">
                <input type="file" name="bukti_pembayaran" accept=".jpg,.jpeg,.png,.pdf" required
                       style="flex:1;min-width:160px;background:rgba(255,255,255,.07);border:1px solid var(--card-border);border-radius:8px;padding:9px 14px;color:var(--text-main);font-size:.85rem;font-family:inherit;">
                <button type="submit" class="btn-check"><i class="fa-solid fa-upload"></i> Upload</button>
              </form>
            </div>
            <?php endif; ?>
          <?php endif; ?>

          <!-- Baris info utama -->
          <div class="profile-grid">
            <div>
              <div class="label">Paket</div>
              <div class="value"><?= htmlspecialchars($h['package_name'] ?? '-') ?></div>
            </div>
            <div>
              <div class="label">Harga</div>
              <div class="value"><?= fmtRupiah($h['package_price'] ?? 0) ?> / <?= htmlspecialchars($h['package_period'] ?? 'bulan') ?></div>
            </div>
            <div>
              <div class="label">Domain / Subdomain</div>
              <div class="value" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <span><?= htmlspecialchars($h['domain']) ?></span>
                <span style="font-size:.72rem;padding:2px 8px;border-radius:20px;background:rgba(255,255,255,.07);color:var(--text-muted);">
                  <?= $isDomainBeli ? '<i class="fa-solid fa-globe"></i> Domain Berbayar' : '<i class="fa-solid fa-link"></i> Subdomain Gratis' ?>
                </span>
              </div>
            </div>
            <?php if ($isDomainBeli && !empty($h['domain_tld'])): ?>
            <div>
              <div class="label">Ekstensi Domain</div>
              <div class="value"><?= htmlspecialchars($h['domain_tld']) ?></div>
            </div>
            <?php endif; ?>
            <div>
              <div class="label">Jatuh Tempo Berikutnya</div>
              <div class="value" style="<?= $isExpiredHost ? 'color:#f87171;' : ($isWarningHost ? 'color:#fde68a;' : '') ?>">
                <?= $expireLabelHost ?>
                <?php if ($sisaHariHost !== null): ?>
                  <span style="margin-left:8px;font-size:.78rem;font-weight:700;
                    color:<?= $isExpiredHost ? '#f87171' : ($isWarningHost ? '#fde68a' : '#86efac') ?>;">
                    (<?= $isExpiredHost ? 'Lewat jatuh tempo' : $sisaHariHost . ' hari lagi' ?>)
                  </span>
                <?php endif; ?>
              </div>
            </div>
            <div>
              <div class="label">Terdaftar Sejak</div>
              <div class="value"><?= fmtTanggal($h['created_at']) ?></div>
            </div>
            <?php if (!empty($h['package_desc'])): ?>
            <div style="grid-column:1/-1;">
              <div class="label">Deskripsi Paket</div>
              <div class="value" style="font-size:.83rem;color:var(--text-muted);line-height:1.6;">
                <?= htmlspecialchars($h['package_desc']) ?>
              </div>
            </div>
            <?php endif; ?>
          </div>

          <!-- Tombol Aksi -->
          <div style="display:flex;flex-wrap:wrap;gap:12px;padding-top:4px;">
            <a href="<?= htmlspecialchars($daUrl) ?>" target="_blank" rel="noopener noreferrer"
               style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;
                      background:linear-gradient(135deg,var(--pink),var(--bg-accent));
                      color:#fff;font-size:.86rem;font-weight:700;border-radius:10px;
                      text-decoration:none;transition:opacity .2s;border:none;cursor:pointer;"
               onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
              <i class="fa-solid fa-gear"></i> Masuk DirectAdmin
            </a>
            <a href="<?= htmlspecialchars($siteUrl) ?>" target="_blank" rel="noopener noreferrer"
               style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;
                      background:rgba(255,255,255,.08);
                      color:var(--text-main);font-size:.86rem;font-weight:600;border-radius:10px;
                      text-decoration:none;transition:background .2s;border:1px solid var(--card-border);cursor:pointer;"
               onmouseover="this.style.background='rgba(255,255,255,.14)'" onmouseout="this.style.background='rgba(255,255,255,.08)'">
              <i class="fa-solid fa-arrow-up-right-from-square"></i> Lihat Website
            </a>
          </div>

        </div>
      </div>
      <?php endforeach; ?>

      <!-- ── Tagihan / Invoice Hosting ── -->
      <?php if (!empty($invoiceHosting)): ?>
      <div class="card card-full">
        <div class="card-header">
          <h3><i class="fa-solid fa-file-invoice"></i> Tagihan Hosting</h3>
        </div>
        <div class="card-body" style="padding:0;">
          <?php foreach ($invoiceHosting as $inv): ?>
          <div class="list-item" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
            <div class="item-info">
              <div class="item-title">
                Invoice <?= !empty($inv['order_number']) ? '#' . htmlspecialchars($inv['order_number']) : '#' . $inv['id'] ?>
              </div>
              <div class="item-sub">
                Jatuh tempo: <?= fmtTanggal($inv['duedate']) ?>
              </div>
            </div>
            <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
              <span style="font-size:1rem;font-weight:700;color:var(--text-main);">
                <?= fmtRupiah($inv['total']) ?>
              </span>
              <span class="badge <?= invoiceStatusBadgeClass($inv['status']) ?>">
                <?= invoiceStatusLabel($inv['status']) ?>
              </span>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

    <?php endif; // empty hostingList ?>

    </div>
    <!-- /LAYANAN HOSTING -->

    <?php endif; // view === layanan_hosting ?>

    <?php if ($view === 'invoices'): ?>
    <!-- ================= INVOICE & TAGIHAN ================= -->
    <div class="cards-grid">

      <div class="card card-full">
        <div class="card-header">
          <h3><i class="fa-solid fa-file-invoice"></i> Riwayat Invoice</h3>
        </div>
        <div class="card-body" style="padding:0;">
          <?php if (empty($invoiceList)): ?>
            <div class="empty-state">
              <i class="fa-solid fa-inbox"></i>
              Belum ada invoice. Invoice akan muncul otomatis setiap kali Anda membuat order baru.
            </div>
          <?php else: ?>
            <?php foreach ($invoiceList as $inv): ?>
              <?php
                $jenisLabelMap = ['wifi' => 'WiFi', 'hosting' => 'Hosting'];
                $jenisLabel    = $jenisLabelMap[$inv['order_type'] ?? ''] ?? 'Layanan';
              ?>
              <div class="list-item">
                <div class="item-info">
                  <div class="item-title">
                    Invoice #<?= (int)$inv['id'] ?>
                    <?php if (!empty($inv['order_number'])): ?>
                      &middot; <?= htmlspecialchars($inv['order_number']) ?>
                    <?php endif; ?>
                  </div>
                  <div class="item-sub">
                    <?= $jenisLabel ?><?= !empty($inv['product_name']) ? ' — ' . htmlspecialchars($inv['product_name']) : '' ?>
                    &middot; Dibuat <?= fmtTanggal($inv['created_at']) ?>
                    <?php if ($inv['status'] === 'Paid' && !empty($inv['datepaid'])): ?>
                      &middot; Dibayar <?= fmtTanggal($inv['datepaid'], true) ?>
                    <?php elseif ($inv['status'] === 'Unpaid' && !empty($inv['duedate'])): ?>
                      &middot; Jatuh tempo <?= fmtTanggal($inv['duedate'], true) ?>
                    <?php endif; ?>
                  </div>
                </div>
                <div style="text-align:right;white-space:nowrap;">
                  <div style="font-size:.92rem;font-weight:700;margin-bottom:4px;"><?= fmtRupiah($inv['total']) ?></div>
                  <span class="badge <?= invoiceStatusBadgeClass($inv['status']) ?>"><?= invoiceStatusLabel($inv['status']) ?></span>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

    </div>
    <!-- /INVOICE & TAGIHAN -->
    <?php endif; // view === invoices ?>

    </div>

    <!-- FOOTER -->
    <footer class="dashboard-footer">
      <span>&copy; <?= date('Y') ?> Perkasa Solusindo. All rights reserved.</span>
      <span>Butuh bantuan? <a href="/client/tickets.php">Hubungi Support</a></span>
    </footer>

  </div>
</div>

<!-- LOGOUT MODAL -->
<div class="modal-backdrop" id="logoutModal">
  <div class="modal-box">
    <div class="modal-icon"><i class="fa-solid fa-right-from-bracket"></i></div>
    <h3>Keluar dari Akun?</h3>
    <p>Anda akan keluar dari dashboard client Perkasa Solusindo. Pastikan semua perubahan telah disimpan.</p>
    <div class="modal-actions">
      <a href="/client/logout.php" class="btn-confirm-logout"><i class="fa-solid fa-check"></i> Ya, Logout</a>
      <button class="btn-cancel-logout" onclick="document.getElementById('logoutModal').classList.remove('active')">
        <i class="fa-solid fa-xmark"></i> Batal
      </button>
    </div>
  </div>
</div>

<script>
// Page loader
window.addEventListener('load', function() {
  document.getElementById('page-loader').classList.add('hidden');
  document.getElementById('pageWrapper').classList.add('visible');
});

// Sidebar toggle (mobile)
const menuToggle = document.getElementById('menuToggle');
const sidebar    = document.getElementById('sidebar');
const overlay    = document.getElementById('sidebarOverlay');
if (menuToggle) {
  menuToggle.addEventListener('click', function() {
    sidebar.classList.toggle('open');
    overlay.classList.toggle('active');
  });
  overlay.addEventListener('click', function() {
    sidebar.classList.remove('open');
    overlay.classList.remove('active');
  });
}

// Close modal on backdrop click
document.getElementById('logoutModal').addEventListener('click', function(e) {
  if (e.target === this) this.classList.remove('active');
});

// ── NOTIFICATION PANEL ───────────────────────────────────
var unreadCount = <?= $notifUnread ?>;

function toggleNotifPanel(e) {
  e.stopPropagation();
  var panel = document.getElementById('notifPanel');
  panel.classList.toggle('open');
}

document.addEventListener('click', function(e) {
  var wrap = document.getElementById('notifWrap');
  if (wrap && !wrap.contains(e.target)) {
    document.getElementById('notifPanel').classList.remove('open');
  }
});

function updateBadge() {
  var badge = document.getElementById('notifCount');
  if (!badge) return;
  if (unreadCount <= 0) {
    badge.classList.add('hidden');
  } else {
    badge.classList.remove('hidden');
    badge.textContent = unreadCount > 9 ? '9+' : unreadCount;
  }
}

function markRead(el, notifId) {
  if (!el.classList.contains('unread')) return;
  el.classList.remove('unread');
  var dot = el.querySelector('.unread-dot');
  if (dot) dot.remove();
  unreadCount = Math.max(0, unreadCount - 1);
  updateBadge();

  fetch('/client/mark_notif_read.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'notif_id=' + notifId
  });
}

function markAllRead() {
  var items = document.querySelectorAll('.notif-item.unread');
  items.forEach(function(item) {
    item.classList.remove('unread');
    var dot = item.querySelector('.unread-dot');
    if (dot) dot.remove();
  });
  unreadCount = 0;
  updateBadge();
  // Hapus tombol "tandai semua"
  var btn = document.querySelector('.notif-mark-all');
  if (btn) btn.remove();

  fetch('/client/mark_notif_read.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'all=1'
  });
}

// ── COPY TEXT ────────────────────────────────────────────
function copyText(elementId, btn) {
  var el = document.getElementById(elementId);
  if (!el) return;
  var text = el.textContent.trim();
  navigator.clipboard.writeText(text).then(function() {
    var orig = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-check"></i> Tersalin!';
    setTimeout(function() { btn.innerHTML = orig; }, 1800);
  }).catch(function() {
    // Fallback
    var ta = document.createElement('textarea');
    ta.value = text;
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    document.body.removeChild(ta);
    var orig = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-check"></i> Tersalin!';
    setTimeout(function() { btn.innerHTML = orig; }, 1800);
  });
}

// ── Upload bukti tagihan bulanan ─────────────────────────
var formTagihan = document.getElementById('form-upload-tagihan');
if (formTagihan) {
  formTagihan.addEventListener('submit', function(e) {
    e.preventDefault();
    var fileInput = document.getElementById('bukti-tagihan-file');
    var btn       = document.getElementById('btn-upload-tagihan');
    var msgEl     = document.getElementById('upload-tagihan-msg');

    if (!fileInput.files.length) {
      msgEl.style.display = 'block';
      msgEl.innerHTML = '<div style="padding:10px 14px;background:rgba(248,113,113,.1);border:1px solid rgba(248,113,113,.3);border-radius:8px;font-size:.83rem;color:#fca5a5;"><i class="fa-solid fa-circle-exclamation"></i> Pilih file bukti pembayaran terlebih dahulu.</div>';
      return;
    }

    var formData = new FormData(this);
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Mengupload...';

    fetch('/order/upload_bukti_tagihan_bulanan.php', {
      method: 'POST',
      body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
      btn.disabled = false;
      btn.innerHTML = '<i class="fa-solid fa-upload"></i> Upload Bukti';
      if (data.ok) {
        // 1. Tampilkan toast sukses
        showToast('sukses', '✅ Pembayaran Dikirim', 'Bukti berhasil diupload. Admin akan segera memverifikasi dan memperpanjang layanan Anda.');

        // 2. Prepend notifikasi baru ke panel notif (real-time tanpa reload)
        if (data.notif) {
          prependNotif(data.notif);
        }

        // 3. Reload setelah 2.5 detik supaya kartu tagihan berubah ke "waiting_confirm"
        setTimeout(function() { location.reload(); }, 2500);
      } else {
        showToast('error', 'Upload Gagal', data.msg || 'Terjadi kesalahan. Coba lagi.');
      }
    })
    .catch(function() {
      btn.disabled = false;
      btn.innerHTML = '<i class="fa-solid fa-upload"></i> Upload Bukti';
      showToast('error', 'Koneksi Gagal', 'Periksa koneksi internet Anda dan coba lagi.');
    });
  });
} // end if (formTagihan)

// ── TOAST NOTIFICATION ───────────────────────────────────
function showToast(tipe, judul, pesan) {
  var colors = {
    sukses : { bg: 'rgba(34,197,94,.12)',  border: 'rgba(74,222,128,.3)',  text: '#86efac', icon: 'fa-circle-check'          },
    error  : { bg: 'rgba(239,68,68,.12)',  border: 'rgba(248,113,113,.3)', text: '#fca5a5', icon: 'fa-circle-xmark'          },
    info   : { bg: 'rgba(99,102,241,.12)', border: 'rgba(129,140,248,.3)', text: '#c7d2fe', icon: 'fa-circle-info'           },
    peringatan:{ bg:'rgba(251,191,36,.10)',border:'rgba(251,191,36,.3)',   text: '#fde68a', icon: 'fa-triangle-exclamation'  },
  };
  var c = colors[tipe] || colors.info;

  // Container
  var wrap = document.getElementById('toast-container');
  if (!wrap) {
    wrap = document.createElement('div');
    wrap.id = 'toast-container';
    wrap.style.cssText = 'position:fixed;bottom:28px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:10px;pointer-events:none;';
    document.body.appendChild(wrap);
  }

  var toast = document.createElement('div');
  toast.style.cssText = [
    'display:flex;align-items:flex-start;gap:12px;',
    'background:' + c.bg + ';',
    'border:1px solid ' + c.border + ';',
    'border-radius:12px;',
    'padding:14px 16px;',
    'min-width:280px;max-width:360px;',
    'box-shadow:0 12px 40px rgba(0,0,0,.5);',
    'backdrop-filter:blur(12px);',
    'pointer-events:auto;',
    'animation:toastIn .3s ease;',
    'cursor:pointer;',
  ].join('');

  toast.innerHTML =
    '<div style="flex-shrink:0;width:34px;height:34px;border-radius:50%;background:' + c.border + ';' +
    'display:flex;align-items:center;justify-content:center;color:' + c.text + ';font-size:.95rem;">' +
    '<i class="fa-solid ' + c.icon + '"></i></div>' +
    '<div style="flex:1;min-width:0;">' +
    '<div style="font-size:.85rem;font-weight:700;color:' + c.text + ';margin-bottom:3px;">' + judul + '</div>' +
    '<div style="font-size:.78rem;color:rgba(255,255,255,.55);line-height:1.45;">' + pesan + '</div>' +
    '</div>' +
    '<button onclick="this.parentElement.remove()" style="background:none;border:none;color:rgba(255,255,255,.3);cursor:pointer;font-size:.85rem;padding:0;flex-shrink:0;"><i class="fa-solid fa-xmark"></i></button>';

  wrap.appendChild(toast);

  // Auto dismiss setelah 5 detik
  setTimeout(function() {
    toast.style.animation = 'toastOut .35s ease forwards';
    setTimeout(function() { toast.remove(); }, 350);
  }, 5000);

  // Klik untuk dismiss
  toast.addEventListener('click', function(e) {
    if (e.target.closest('button')) return;
    toast.style.animation = 'toastOut .35s ease forwards';
    setTimeout(function() { toast.remove(); }, 350);
  });
}

// ── PREPEND NOTIFIKASI BARU KE PANEL ────────────────────
function prependNotif(notif) {
  var list = document.getElementById('notifList');
  if (!list) return;

  // Hapus empty state jika ada
  var empty = list.querySelector('.notif-empty');
  if (empty) empty.remove();

  var icons = {
    sukses: 'fa-solid fa-circle-check',
    info:   'fa-solid fa-circle-info',
    peringatan: 'fa-solid fa-triangle-exclamation',
    error:  'fa-solid fa-circle-xmark',
  };

  var el = document.createElement('div');
  el.className = 'notif-item unread';
  el.dataset.id = notif.id || 0;
  el.innerHTML =
    '<div class="notif-dot-wrap ' + (notif.tipe || 'sukses') + '">' +
    '<i class="' + (icons[notif.tipe] || icons.sukses) + '"></i></div>' +
    '<div class="notif-text">' +
    '<div class="notif-title">' + notif.judul + '</div>' +
    '<div class="notif-body">'  + notif.pesan + '</div>' +
    '<div class="notif-time">Baru saja</div>' +
    '</div>' +
    '<div class="unread-dot"></div>';

  list.insertBefore(el, list.firstChild);

  // Update badge
  unreadCount++;
  updateBadge();

  // Munculkan tombol "tandai semua dibaca" jika belum ada
  var header = document.querySelector('.notif-panel-header');
  if (header && !header.querySelector('.notif-mark-all')) {
    var btn = document.createElement('button');
    btn.className = 'notif-mark-all';
    btn.textContent = 'Tandai semua dibaca';
    btn.onclick = markAllRead;
    header.appendChild(btn);
  }
}

// ── TOGGLE PENGUMUMAN ────────────────────────────────────
function toggleAnn(id) {
  var preview = document.getElementById('ann-body-' + id + '-preview');
  var full    = document.getElementById('ann-full-' + id);
  var btn     = document.getElementById('ann-toggle-' + id);
  if (!full) return;
  var isOpen = full.style.display !== 'none';
  full.style.display    = isOpen ? 'none'  : 'block';
  if (preview) preview.style.display = isOpen ? 'block' : 'none';
  if (btn)     btn.textContent        = isOpen ? 'Baca selengkapnya' : 'Sembunyikan';
}
</script>

</body>
</html>
