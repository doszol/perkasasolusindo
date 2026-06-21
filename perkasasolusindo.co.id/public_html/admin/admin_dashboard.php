<?php
// ============================================================
// admin/admin_dashboard.php – Perkasa Solusindo Admin Panel
// ============================================================
require_once '../config.php';
require_once '../auth_check.php';
requireLevel([1, 2]); // Hanya Owner & Admin

// ── Helper ──────────────────────────────────────────────────
function countQuery($conn, $sql) {
    $r = $conn->query($sql);
    return $r ? (int)$r->fetch_row()[0] : 0;
}

$adminId   = $_SESSION['user_id'] ?? 0;
$adminName = $_SESSION['user_firstname'] ?? 'Admin';

// ── Proses AJAX: Update status order ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');

    // Tandai semua notif admin sebagai dibaca
    if ($_POST['ajax_action'] === 'mark_notif_read') {
        $conn->query("UPDATE tblnotifikasi SET sudah_dibaca=1 WHERE userid=$adminId");
        echo json_encode(['ok' => true]);
        exit;
    }

    // Update status order
    if ($_POST['ajax_action'] === 'update_order') {
        $orderId      = (int)($_POST['order_id'] ?? 0);
        $newStatus    = $_POST['wifi_status'] ?? '';
        $jadwal       = $_POST['jadwal_instalasi'] ?? '';
        $catatan      = $_POST['catatan'] ?? '';
        $teknisiId    = (int)($_POST['teknisi_id'] ?? 0);

        $allowed = ['pending','verified','scheduled','installed','active','cancelled'];
        if (!$orderId || !in_array($newStatus, $allowed)) {
            echo json_encode(['ok' => false, 'msg' => 'Parameter tidak valid.']);
            exit;
        }

        // Ambil data lama untuk log
        $old = $conn->query("SELECT wifi_status, userid, productid FROM tblorders WHERE id=$orderId")->fetch_assoc();
        if (!$old) { echo json_encode(['ok' => false, 'msg' => 'Order tidak ditemukan.']); exit; }

        $oldStatus  = $old['wifi_status'];
        $clientId   = (int)$old['userid'];

        // Susun SET clause
        $setClauses = ["wifi_status = ?"];
        $types      = 's';
        $params     = [$newStatus];

        if ($jadwal) {
            $setClauses[] = "jadwal_instalasi = ?";
            $types .= 's';
            $params[] = $jadwal;
        }
        if ($teknisiId) {
            $setClauses[] = "teknisi_id = ?";
            $types .= 'i';
            $params[] = $teknisiId;
        }
        if ($catatan !== '') {
            $setClauses[] = "note = ?";
            $types .= 's';
            $params[] = $catatan;
        }

        $setStr = implode(', ', $setClauses);
        $stmt = $conn->prepare("UPDATE tblorders SET $setStr WHERE id = ?");
        $types .= 'i';
        $params[] = $orderId;
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->close();

        // Log perubahan status
        $stmt2 = $conn->prepare(
            "INSERT INTO tblorder_status_logs (order_id,old_status,new_status,changed_by,role,catatan) VALUES (?,?,?,?,?,?)"
        );
        $stmt2->bind_param('issiis', $orderId, $oldStatus, $newStatus, $adminId, $role, $catatan);
        $role = 'admin';
        $stmt2->execute();
        $stmt2->close();

        // Buat notifikasi untuk klien
        $labelMap = [
            'verified'  => ['Pesanan Anda Diverifikasi ✅',       'Admin telah memverifikasi pesanan Anda. Tim kami akan segera menghubungi Anda.'],
            'scheduled' => ['Jadwal Instalasi Ditetapkan 📅',     'Jadwal pemasangan WiFi Anda sudah ditetapkan. Cek detail di dashboard Anda.'],
            'installed' => ['Instalasi Selesai 🎉',               'Pemasangan WiFi Anda telah berhasil diselesaikan oleh teknisi kami.'],
            'active'    => ['Layanan WiFi Aktif 🚀',              'Layanan internet Anda sudah aktif. Selamat menikmati!'],
            'cancelled' => ['Pesanan Dibatalkan ❌',              'Pesanan WiFi Anda telah dibatalkan. Hubungi kami jika ada pertanyaan.'],
        ];
        if (isset($labelMap[$newStatus])) {
            [$judul, $pesan] = $labelMap[$newStatus];
            $tipe = in_array($newStatus, ['active','installed','verified']) ? 'sukses' : ($newStatus === 'cancelled' ? 'error' : 'info');
            $stmt3 = $conn->prepare(
                "INSERT INTO tblnotifikasi (userid,order_id,judul,pesan,tipe) VALUES (?,?,?,?,?)"
            );
            $stmt3->bind_param('iisss', $clientId, $orderId, $judul, $pesan, $tipe);
            $stmt3->execute();
            $stmt3->close();
        }

        echo json_encode(['ok' => true, 'new_status' => $newStatus]);
        exit;
    }
    echo json_encode(['ok' => false, 'msg' => 'Unknown action.']);
    exit;
}

// ── Statistik ringkas ────────────────────────────────────────
$stats = [];
$stats['clients']  = countQuery($conn, "SELECT COUNT(*) FROM tblclients WHERE level = 3 AND status = 1");
$stats['products'] = countQuery($conn, "SELECT COUNT(*) FROM tblproducts WHERE status = 1");
$stats['unpaid']   = countQuery($conn, "SELECT COUNT(*) FROM tblinvoices WHERE status = 'Unpaid'");
$stats['tickets']  = countQuery($conn, "SELECT COUNT(*) FROM tbltickets WHERE status = 'Open'");
$stats['hosting']  = countQuery($conn, "SELECT COUNT(*) FROM tblhosting WHERE domainstatus = 'Active'");

$r = $conn->query("SELECT COALESCE(SUM(total),0) FROM tblinvoices WHERE status = 'Paid'");
$stats['revenue'] = $r ? $r->fetch_row()[0] : 0;

// ── Notifikasi admin yang belum dibaca ───────────────────────
$adminNotifs = [];
$r = $conn->query(
    "SELECT n.*, o.order_number FROM tblnotifikasi n
     LEFT JOIN tblorders o ON o.id = n.order_id
     WHERE n.userid = $adminId AND n.sudah_dibaca = 0
     ORDER BY n.created_at DESC LIMIT 15"
);
if ($r) { while ($row = $r->fetch_assoc()) $adminNotifs[] = $row; }
$unreadCount = count($adminNotifs);

// ── Order masuk (pending) dari SEMUA jenis layanan ───────────
$pendingOrders = [];
$r = $conn->query(
    "SELECT o.*,
            c.firstname, c.lastname, c.email, c.phonenumber,
            p.name AS product_name, p.price, p.speed, p.category,
            CONCAT(c.firstname,' ',c.lastname) AS client_name
     FROM tblorders o
     JOIN tblclients c  ON c.id = o.userid
     JOIN tblproducts p ON p.id = o.productid
     WHERE o.wifi_status IN ('pending','verified','scheduled')
     ORDER BY o.created_at DESC LIMIT 5"
);
if ($r) { while ($row = $r->fetch_assoc()) $pendingOrders[] = $row; }
$pendingCount = (int)$conn->query("SELECT COUNT(*) FROM tblorders WHERE wifi_status IN ('pending','verified','scheduled')")->fetch_row()[0];

// ── Hitung pending per kategori (untuk banner detail) ────────
$pendingByType = [];
$rpt = $conn->query("SELECT p.category, COUNT(*) AS cnt FROM tblorders o JOIN tblproducts p ON p.id=o.productid WHERE o.wifi_status IN ('pending','verified','scheduled') GROUP BY p.category");
if ($rpt) { while($row=$rpt->fetch_assoc()) $pendingByType[$row['category']] = (int)$row['cnt']; }

// ── Akun client baru (7 hari terakhir) ──────────────────────
$newAccountCount = countQuery($conn,
    "SELECT COUNT(*) FROM tblclients WHERE level=3 AND datecreated >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
);

// ── 5 klien terbaru ──────────────────────────────────────────
$recentClients = [];
$r = $conn->query(
    "SELECT id, firstname, lastname, email, companyname, datecreated, status
     FROM tblclients WHERE level = 3 ORDER BY datecreated DESC LIMIT 5"
);
if ($r) { while ($row = $r->fetch_assoc()) $recentClients[] = $row; }

// ── 5 invoice terbaru ───────────────────────────────────────
$recentInvoices = [];
$r = $conn->query(
    "SELECT i.id, i.status, i.total, i.duedate, i.created_at,
            c.firstname, c.lastname
     FROM tblinvoices i
     JOIN tblclients c ON c.id = i.userid
     ORDER BY i.created_at DESC LIMIT 5"
);
if ($r) { while ($row = $r->fetch_assoc()) $recentInvoices[] = $row; }

// ── Daftar teknisi aktif (untuk dropdown assign) ─────────────
$teknisiList = [];
$r = $conn->query(
    "SELECT id, firstname, lastname FROM tblclients WHERE level=4 AND status=1 ORDER BY firstname"
);
if ($r) { while ($row = $r->fetch_assoc()) $teknisiList[] = $row; }

// ── Label & warna wifi_status ────────────────────────────────
function wifiStatusBadge($status) {
    $map = [
        'pending'   => ['badge-yellow', 'fa-hourglass-half',    'Menunggu Verifikasi'],
        'verified'  => ['badge-blue',   'fa-circle-check',      'Diverifikasi'],
        'scheduled' => ['badge-blue',   'fa-calendar-check',    'Dijadwalkan'],
        'installed' => ['badge-green',  'fa-screwdriver-wrench','Terpasang'],
        'active'    => ['badge-green',  'fa-wifi',              'Aktif'],
        'cancelled' => ['badge-red',    'fa-ban',               'Dibatalkan'],
    ];
    $d = $map[$status] ?? ['badge-gray','fa-circle','Unknown'];
    return ['class' => $d[0], 'icon' => $d[1], 'label' => $d[2]];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard – Perkasa Solusindo</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/admin/style_admin.css">
<style>
/* ── Order Panel ──────────────────────────────────────────── */
.order-panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
  flex-wrap: wrap;
  gap: 12px;
}
.order-count-badge {
  background: var(--danger);
  color: #fff;
  font-size: 11px;
  font-weight: 800;
  padding: 2px 9px;
  border-radius: 20px;
  margin-left: 8px;
  animation: pulse-badge 1.5s ease-in-out infinite;
}
@keyframes pulse-badge {
  0%,100% { box-shadow: 0 0 0 0 rgba(239,68,68,.5); }
  50%      { box-shadow: 0 0 0 6px rgba(239,68,68,0); }
}

/* ── Order Card ──────────────────────────────────────────── */
.order-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 14px;
  margin-bottom: 16px;
  overflow: hidden;
  transition: border-color .2s;
}
.order-card.status-pending   { border-left: 3px solid #fbbf24; }
.order-card.status-verified  { border-left: 3px solid #60a5fa; }
.order-card.status-scheduled { border-left: 3px solid #818cf8; }

.order-card-head {
  padding: 14px 18px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  background: var(--surface2);
  cursor: pointer;
  user-select: none;
}
.order-card-head:hover { background: rgba(255,255,255,.03); }

.order-number {
  font-family: 'JetBrains Mono', monospace;
  font-size: 12px;
  color: var(--accent);
  font-weight: 600;
}
.order-client-name {
  font-size: 14px;
  font-weight: 700;
}
.order-product-name {
  font-size: 12px;
  color: var(--muted);
  margin-top: 2px;
}
.order-time {
  font-size: 11px;
  color: var(--muted);
  font-family: 'JetBrains Mono', monospace;
  white-space: nowrap;
}

.order-card-body {
  padding: 18px;
  border-top: 1px solid var(--border);
  display: none;
}
.order-card-body.open { display: block; }

/* ── Detail Grid ─────────────────────────────────────────── */
.detail-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 12px;
  margin-bottom: 18px;
}
.detail-item label {
  display: block;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .6px;
  color: var(--muted);
  margin-bottom: 4px;
}
.detail-item span {
  font-size: 13px;
  font-weight: 500;
}

/* ── Update Form ─────────────────────────────────────────── */
.update-form {
  background: var(--surface2);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 16px;
  margin-top: 14px;
}
.update-form .form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
  margin-bottom: 12px;
}
@media (max-width: 640px) { .update-form .form-row { grid-template-columns: 1fr; } }
.update-form label {
  display: block;
  font-size: 11px;
  font-weight: 700;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: .5px;
  margin-bottom: 5px;
}
.update-form select,
.update-form input,
.update-form textarea {
  width: 100%;
  background: var(--surface);
  border: 1px solid var(--border);
  color: var(--text);
  padding: 8px 12px;
  border-radius: 8px;
  font-size: 13px;
  font-family: inherit;
  outline: none;
  transition: border-color .2s;
}
.update-form select:focus,
.update-form input:focus,
.update-form textarea:focus { border-color: var(--accent2); }
.update-form textarea { resize: vertical; min-height: 68px; }

/* ── Notif Bell ──────────────────────────────────────────── */
.notif-btn {
  position: relative;
}
.notif-dot {
  position: absolute;
  top: 4px; right: 4px;
  width: 8px; height: 8px;
  background: var(--danger);
  border-radius: 50%;
  border: 2px solid var(--surface);
  animation: pulse-badge 1.5s ease-in-out infinite;
}
.notif-dropdown {
  display: none;
  position: absolute;
  top: calc(100% + 10px);
  right: 0;
  width: 360px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 14px;
  box-shadow: 0 20px 50px rgba(0,0,0,.6);
  z-index: 200;
  overflow: hidden;
  animation: fadeInDown .2s ease;
}
@keyframes fadeInDown {
  from { opacity: 0; transform: translateY(-8px); }
  to   { opacity: 1; transform: translateY(0); }
}
.notif-dropdown.open { display: block; }
.notif-header {
  padding: 14px 18px;
  border-bottom: 1px solid var(--border);
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.notif-header span { font-size: 13px; font-weight: 700; }
.notif-read-all {
  font-size: 11px;
  color: var(--accent2);
  cursor: pointer;
  font-weight: 600;
  background: none;
  border: none;
  font-family: inherit;
}
.notif-read-all:hover { text-decoration: underline; }
.notif-list { max-height: 340px; overflow-y: auto; }
.notif-item {
  padding: 12px 18px;
  border-bottom: 1px solid rgba(48,54,61,.5);
  display: flex;
  gap: 10px;
  align-items: flex-start;
}
.notif-item:last-child { border-bottom: none; }
.notif-icon {
  width: 32px; height: 32px;
  border-radius: 50%;
  display: grid; place-items: center;
  font-size: 13px;
  flex-shrink: 0;
}
.notif-icon.info    { background: rgba(59,130,246,.12); color: #60a5fa; }
.notif-icon.sukses  { background: rgba(16,185,129,.12); color: #34d399; }
.notif-icon.error   { background: rgba(239,68,68,.12);  color: #f87171; }
.notif-icon.peringatan { background: rgba(245,158,11,.12); color: #fbbf24; }
.notif-text .notif-title { font-size: 12px; font-weight: 700; margin-bottom: 2px; }
.notif-text .notif-msg   { font-size: 11px; color: var(--muted); line-height: 1.5; }
.notif-text .notif-time  { font-size: 10px; color: var(--muted); margin-top: 4px; font-family: 'JetBrains Mono', monospace; }
.notif-wrapper { position: relative; }

/* ── Alert Banner (New Accounts / New Orders) ─────────────── */
.alert-banner {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 18px;
  border-radius: 10px;
  margin-bottom: 18px;
  font-size: 13px;
  font-weight: 600;
}
.alert-banner.order  { background: rgba(245,158,11,.1); border: 1px solid rgba(245,158,11,.25); color: #fbbf24; }
.alert-banner.newreg { background: rgba(59,130,246,.1); border: 1px solid rgba(59,130,246,.25); color: #60a5fa; }
.alert-banner a { color: inherit; font-weight: 700; text-decoration: underline; }

/* ── Status stepper (mini) ───────────────────────────────── */
.status-steps {
  display: flex;
  gap: 0;
  margin-bottom: 16px;
  overflow-x: auto;
}
.status-step {
  flex: 1;
  min-width: 80px;
  text-align: center;
  position: relative;
  font-size: 10px;
  font-weight: 600;
  color: var(--muted);
  padding: 8px 4px;
}
.status-step::before {
  content: '';
  display: block;
  width: 24px; height: 24px;
  border-radius: 50%;
  background: var(--surface2);
  border: 2px solid var(--border);
  margin: 0 auto 6px;
}
.status-step.done::before  { background: var(--accent2); border-color: var(--accent2); }
.status-step.done          { color: var(--text); }
.status-step.active::before{ background: var(--accent); border-color: var(--accent); }
.status-step.active        { color: var(--accent); }
.status-step + .status-step::after {
  content: '';
  position: absolute;
  top: 19px;
  left: -50%;
  width: 100%;
  height: 2px;
  background: var(--border);
  z-index: -1;
}
.status-step.done + .status-step::after,
.status-step.active + .status-step::after { background: var(--accent2); }

/* ── Revenue value font fix ───────────────────────────────── */
.revenue-value { font-size: 18px; }
</style>
</head>
<body>

<!-- ═══════════════ SIDEBAR ═══════════════ -->
  <!-- Mobile Overlay -->
  <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>
<aside class="sidebar">
  <div class="sidebar-brand">
    <div class="logo-mark">
      <div class="logo-icon">P</div>
      <div class="logo-text">
        Perkasa Solusindo
        <span>Admin Panel</span>
      </div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-label">Main</div>
    <a href="/admin/admin_dashboard.php" class="nav-item active">
      <i class="fa fa-gauge-high"></i> Dashboard
    </a>

    <div class="nav-label">Transaksi</div>
    <a href="/admin/orders.php" class="nav-item has-sub expanded" onclick="toggleSubMenu(event,'subOrders')">
      <i class="fa fa-list-check"></i> Semua Order
      <?php if($pendingCount > 0): ?>
        <span class="nav-badge"><?= $pendingCount ?></span>
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
    <a href="/admin/products.php" class="nav-item">
      <i class="fa fa-box-open"></i> Produk Layanan
    </a>
    <a href="/admin/clients.php" class="nav-item">
      <i class="fa fa-users"></i> Data Klien
    </a>
    <a href="/admin/teknisi.php" class="nav-item">
      <i class="fa fa-screwdriver-wrench"></i> Teknisi
    </a>
    <a href="/admin/hosting.php" class="nav-item">
      <i class="fa fa-server"></i> Hosting
    </a>
    <a href="/admin/domains.php" class="nav-item">
      <i class="fa fa-globe"></i> Domain
    </a>

    <div class="nav-label">Support</div>
    <a href="/admin/tickets.php" class="nav-item">
      <i class="fa fa-ticket"></i> Tiket Support
      <?php if($stats['tickets'] > 0): ?>
        <span class="nav-badge"><?= $stats['tickets'] ?></span>
      <?php endif; ?>
    </a>
    <a href="/admin/announcements.php" class="nav-item">
      <i class="fa fa-bullhorn"></i> Pengumuman
    </a>

    <div class="nav-label">Sistem</div>
    <a href="../index.php" target="_blank" class="nav-item">
      <i class="fa fa-globe"></i> Lihat Website
    </a>
    <a href="/admin/settings.php" class="nav-item">
      <i class="fa fa-gear"></i> Pengaturan
    </a>
  </nav>

  <div class="sidebar-footer">
    <div class="admin-profile">
      <div class="avatar"><?= strtoupper(substr($adminName, 0, 1)) ?></div>
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
    <button class="hamburger" id="hamburgerBtn" onclick="toggleSidebar()" aria-label="Toggle Menu"><span></span><span></span><span></span></button>
      Dashboard</div>
    <div class="topbar-right">
      <span class="date-badge">
        <i class="fa fa-calendar-days" style="margin-right:6px;"></i>
        <?= date('d M Y') ?>
      </span>

      <!-- Notifikasi Bell -->
      <div class="notif-wrapper">
        <button class="topbar-btn notif-btn" id="notifBtn" title="Notifikasi" onclick="toggleNotif(event)">
          <i class="fa fa-bell"></i>
          <?php if($unreadCount > 0): ?>
            <span class="notif-dot"></span>
          <?php endif; ?>
        </button>
        <div class="notif-dropdown" id="notifDropdown">
          <div class="notif-header">
            <span><i class="fa fa-bell" style="margin-right:6px;color:var(--accent);"></i>Notifikasi
              <?php if($unreadCount > 0): ?>
                <span class="order-count-badge"><?= $unreadCount ?></span>
              <?php endif; ?>
            </span>
            <?php if($unreadCount > 0): ?>
              <button class="notif-read-all" onclick="markAllRead()">Tandai semua dibaca</button>
            <?php endif; ?>
          </div>
          <div class="notif-list">
            <?php if(empty($adminNotifs)): ?>
              <div class="empty-state" style="padding:24px 16px;">
                <i class="fa fa-bell-slash"></i>
                <p>Tidak ada notifikasi baru.</p>
              </div>
            <?php else: ?>
              <?php foreach($adminNotifs as $n):
                $iconClass = $n['tipe'];
                $iconMap   = ['info'=>'fa-info','sukses'=>'fa-check','error'=>'fa-xmark','peringatan'=>'fa-triangle-exclamation'];
                $icon      = $iconMap[$n['tipe']] ?? 'fa-bell';
              ?>
              <div class="notif-item">
                <div class="notif-icon <?= $n['tipe'] ?>"><i class="fa <?= $icon ?>"></i></div>
                <div class="notif-text">
                  <div class="notif-title"><?= htmlspecialchars($n['judul']) ?></div>
                  <div class="notif-msg"><?= htmlspecialchars(mb_substr($n['pesan'],0,90)).(mb_strlen($n['pesan'])>90?'…':'') ?></div>
                  <div class="notif-time"><?= date('d M Y H:i', strtotime($n['created_at'])) ?>
                    <?php if($n['order_number']): ?>
                      · <a href="#order-<?= $n['order_id'] ?>" style="color:var(--accent2);text-decoration:none;"
                           href="/admin/order_detail.php?id=<?= $n['order_id'] ?>" style="color:var(--accent2);text-decoration:none;font-weight:700;"><?= $n['order_number'] ?></a>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div><!-- /notif-wrapper -->

      <a href="/admin/products.php" class="topbar-btn" title="Tambah Produk">
        <i class="fa fa-plus"></i>
      </a>
      <a href="#" class="topbar-btn" onclick="confirmLogout(event)" title="Logout">
        <i class="fa fa-right-from-bracket"></i>
      </a>
    </div>
  </div>

  <div class="content">

    <!-- Welcome Banner -->
    <div class="welcome-banner">
      <h1>Selamat datang, <span><?= htmlspecialchars($adminName) ?></span> 👋</h1>
      <p>Berikut ringkasan operasional Perkasa Solusindo hari ini.</p>
    </div>

    <!-- Alert Banner: order baru (generik semua layanan) -->
    <?php if($pendingCount > 0):
      // Bangun deskripsi ringkas per kategori
      $catMeta = [
          'wifi'     => ['fa-wifi',    '#fbbf24', 'WiFi'],
          'hosting'  => ['fa-server',  '#34d399', 'Hosting'],
          'website'  => ['fa-code',    '#818cf8', 'Website'],
          'komputer' => ['fa-desktop', '#f59e0b', 'Komputer'],
          'cctv'     => ['fa-video',   '#f87171', 'CCTV'],
          'other'    => ['fa-box-open','#9ca3af', 'Lainnya'],
      ];
      $parts = [];
      foreach($pendingByType as $cat => $cnt) {
          $m = $catMeta[$cat] ?? $catMeta['other'];
          $parts[] = '<strong>'.$cnt.' '.$m[2].'</strong>';
      }
      $summary = implode(', ', $parts);
    ?>
    <a href="/admin/orders.php?status=pending" style="text-decoration:none;display:block;margin-bottom:18px;">
      <div class="alert-banner order" style="cursor:pointer;transition:background .2s;position:relative;padding-right:48px;">
        <div style="width:38px;height:38px;border-radius:10px;background:rgba(245,158,11,.15);display:grid;place-items:center;flex-shrink:0;">
          <i class="fa fa-bell" style="font-size:16px;animation:pulse-badge 1.5s ease-in-out infinite;"></i>
        </div>
        <div style="flex:1;">
          <div style="font-size:14px;font-weight:800;margin-bottom:3px;">
            <?= $pendingCount ?> Order Menunggu Tindakan
          </div>
          <div style="font-size:12px;font-weight:400;opacity:.85;"><?= $summary ?> perlu diproses segera.</div>
        </div>
        <div style="position:absolute;right:16px;top:50%;transform:translateY(-50%);font-size:11px;font-weight:700;background:rgba(245,158,11,.2);padding:4px 10px;border-radius:20px;white-space:nowrap;">
          Lihat Semua <i class="fa fa-arrow-right" style="margin-left:4px;font-size:10px;"></i>
        </div>
      </div>
    </a>
    <?php endif; ?>

    <!-- Alert Banner: akun baru -->
    <?php if($newAccountCount > 0): ?>
    <div class="alert-banner newreg">
      <i class="fa fa-user-plus" style="font-size:18px;"></i>
      <div>
        <strong><?= $newAccountCount ?> akun klien baru</strong> terdaftar dalam 7 hari terakhir.
        <a href="/admin/clients.php">Lihat Data Klien →</a>
      </div>
    </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="quick-actions">
      <a href="/admin/products.php?action=add" class="btn btn-primary">
        <i class="fa fa-plus"></i> Tambah Produk
      </a>
      <a href="/admin/clients.php" class="btn btn-secondary">
        <i class="fa fa-users"></i> Kelola Klien
      </a>
      <a href="/admin/invoices.php" class="btn btn-secondary">
        <i class="fa fa-file-invoice"></i> Invoice
      </a>
      <a href="/admin/tickets.php" class="btn btn-blue">
        <i class="fa fa-ticket"></i> Tiket Support
      </a>
    </div>

    <!-- Stat Cards -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon"><i class="fa fa-users"></i></div>
        <div class="stat-label">Total Klien Aktif</div>
        <div class="stat-value"><?= number_format($stats['clients']) ?></div>
        <div class="stat-sub">Pengguna terdaftar</div>
      </div>

      <div class="stat-card blue">
        <div class="stat-icon"><i class="fa fa-box-open"></i></div>
        <div class="stat-label">Produk Aktif</div>
        <div class="stat-value"><?= number_format($stats['products']) ?></div>
        <div class="stat-sub">Layanan tersedia</div>
      </div>

      <div class="stat-card red">
        <div class="stat-icon"><i class="fa fa-file-invoice-dollar"></i></div>
        <div class="stat-label">Invoice Belum Dibayar</div>
        <div class="stat-value"><?= number_format($stats['unpaid']) ?></div>
        <div class="stat-sub">Menunggu pembayaran</div>
      </div>

      <div class="stat-card green">
        <div class="stat-icon"><i class="fa fa-server"></i></div>
        <div class="stat-label">Hosting Aktif</div>
        <div class="stat-value"><?= number_format($stats['hosting']) ?></div>
        <div class="stat-sub">Layanan berjalan</div>
      </div>

      <div class="stat-card purple">
        <div class="stat-icon"><i class="fa fa-ticket"></i></div>
        <div class="stat-label">Tiket Open</div>
        <div class="stat-value"><?= number_format($stats['tickets']) ?></div>
        <div class="stat-sub">Perlu ditangani</div>
      </div>

      <div class="stat-card green">
        <div class="stat-icon"><i class="fa fa-money-bill-trend-up"></i></div>
        <div class="stat-label">Total Pendapatan</div>
        <div class="stat-value revenue-value">
          Rp <?= number_format($stats['revenue'], 0, ',', '.') ?>
        </div>
        <div class="stat-sub">Dari invoice terbayar</div>
      </div>
    </div>

    <!-- ═══ SECTION: ORDER TERBARU ═══ -->
    <div id="section-orders" style="margin-bottom:28px;">
      <div class="order-panel-header">
        <h2 style="font-size:16px;font-weight:800;">
          <i class="fa fa-list-check" style="color:var(--accent);margin-right:8px;"></i>
          Order Terbaru
          <?php if($pendingCount > 0): ?>
            <span class="order-count-badge"><?= $pendingCount ?></span>
          <?php endif; ?>
        </h2>
        <a href="/admin/orders.php" class="btn btn-primary btn-sm">
          <i class="fa fa-arrow-right"></i> Kelola Semua Order
        </a>
      </div>

      <?php
      $catIconMap = [
          'wifi'     => ['fa-wifi',    '#3b82f6'],
          'hosting'  => ['fa-server',  '#10b981'],
          'website'  => ['fa-code',    '#8b5cf6'],
          'komputer' => ['fa-desktop', '#f59e0b'],
          'cctv'     => ['fa-video',   '#ef4444'],
          'other'    => ['fa-box-open','#7d8590'],
      ];
      ?>
      <?php if(empty($pendingOrders)): ?>
        <div class="card">
          <div class="empty-state" style="padding:40px 20px;">
            <i class="fa fa-check-circle" style="color:#34d399;opacity:1;"></i>
            <p style="margin-top:10px;font-weight:600;color:var(--text);">Semua order sudah ditangani.</p>
            <p>Tidak ada order yang menunggu tindakan saat ini.</p>
          </div>
        </div>
      <?php else: ?>
      <div class="card" style="padding:0;overflow:hidden;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
          <thead>
            <tr>
              <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid var(--border);background:var(--surface2);">No. Order</th>
              <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid var(--border);background:var(--surface2);">Layanan</th>
              <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid var(--border);background:var(--surface2);">Klien</th>
              <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid var(--border);background:var(--surface2);">Produk</th>
              <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid var(--border);background:var(--surface2);">Status</th>
              <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid var(--border);background:var(--surface2);">Bayar</th>
              <th style="padding:10px 14px;text-align:left;font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid var(--border);background:var(--surface2);">Aksi</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach($pendingOrders as $ord):
            $badge = wifiStatusBadge($ord['wifi_status']);
            $ci    = $catIconMap[$ord['category'] ?? $ord['order_type'] ?? 'other'] ?? $catIconMap['other'];
          ?>
            <tr style="border-bottom:1px solid var(--border);transition:background .15s;cursor:pointer;" onclick="window.location='/admin/order_detail.php?id=<?= $ord['id'] ?>'" onmouseover="this.style.background='rgba(59,130,246,.04)'" onmouseout="this.style.background=''">
              <td style="padding:12px 14px;">
                <div style="font-family:'JetBrains Mono',monospace;font-size:12px;color:var(--accent2);font-weight:600;"><?= htmlspecialchars($ord['order_number'] ?? '#'.$ord['id']) ?></div>
                <div style="font-size:10px;color:var(--muted);"><?= date('d M Y', strtotime($ord['created_at'])) ?></div>
              </td>
              <td style="padding:12px 14px;">
                <span style="display:inline-flex;align-items:center;gap:5px;background:<?= $ci[1] ?>18;color:<?= $ci[1] ?>;border:1px solid <?= $ci[1] ?>33;padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700;">
                  <i class="fa <?= $ci[0] ?>"></i> <?= ucfirst($ord['order_type'] ?? $ord['category'] ?? 'other') ?>
                </span>
              </td>
              <td style="padding:12px 14px;">
                <div style="font-weight:700;font-size:13px;"><?= htmlspecialchars($ord['client_name']) ?></div>
                <div style="font-size:11px;color:var(--muted);"><?= htmlspecialchars($ord['phonenumber']) ?></div>
              </td>
              <td style="padding:12px 14px;">
                <div style="font-size:13px;"><?= htmlspecialchars($ord['product_name']) ?></div>
                <div style="font-size:11px;color:var(--muted);font-family:'JetBrains Mono',monospace;">Rp <?= number_format($ord['price'],0,',','.') ?></div>
              </td>
              <td style="padding:12px 14px;">
                <span class="badge <?= $badge['class'] ?>">
                  <i class="fa <?= $badge['icon'] ?>" style="margin-right:4px;font-size:10px;"></i>
                  <?= $badge['label'] ?>
                </span>
              </td>
              <td style="padding:12px 14px;">
                <?php
                $pmMap=['belum_bayar'=>['#fbbf24','💳'],'sudah_bayar'=>['#60a5fa','📤'],'lunas'=>['#34d399','✅']];
                $pm=$pmMap[$ord['payment_status']]??['#7d8590','–'];
                ?>
                <span style="font-size:11px;font-weight:700;color:<?= $pm[0] ?>;"><?= $pm[1] ?> <?= ucfirst(str_replace('_',' ',$ord['payment_status'])) ?></span>
              </td>
              <td style="padding:12px 14px;">
                <a href="/admin/order_detail.php?id=<?= $ord['id'] ?>" class="btn btn-primary" style="padding:6px 12px;font-size:12px;">
                  <i class="fa fa-pen-to-square"></i> Proses
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php if($pendingCount > 5): ?>
        <div style="padding:12px 18px;text-align:center;border-top:1px solid var(--border);background:var(--surface2);">
          <a href="/admin/orders.php?status=pending" style="font-size:12px;color:var(--accent2);font-weight:700;text-decoration:none;">
            Lihat <?= $pendingCount - 5 ?> order lainnya →
          </a>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div><!-- /section-orders -->

    <!-- Tables row -->
    <div class="grid-2">

      <!-- Klien Terbaru -->
      <div class="card">
        <div class="card-header">
          <span class="card-title"><i class="fa fa-user-plus" style="color:var(--accent2);margin-right:8px;"></i>Klien Terbaru</span>
          <a href="/admin/clients.php" class="card-link">Lihat semua →</a>
        </div>
        <?php if($recentClients): ?>
        <table>
          <thead><tr><th>Nama</th><th>Email</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach($recentClients as $c): ?>
            <tr>
              <td>
                <strong><?= htmlspecialchars($c['firstname'].' '.$c['lastname']) ?></strong>
                <?php if($c['companyname']): ?>
                  <div style="font-size:11px;color:var(--muted);"><?= htmlspecialchars($c['companyname']) ?></div>
                <?php endif; ?>
              </td>
              <td style="color:var(--muted);font-size:12px;"><?= htmlspecialchars($c['email']) ?></td>
              <td>
                <span class="badge <?= $c['status'] ? 'badge-green' : 'badge-gray' ?>">
                  <?= $c['status'] ? 'Aktif' : 'Nonaktif' ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php else: ?>
          <div class="empty-state"><i class="fa fa-users"></i><p>Belum ada klien terdaftar.</p></div>
        <?php endif; ?>
      </div>

      <!-- Invoice Terbaru -->
      <div class="card">
        <div class="card-header">
          <span class="card-title"><i class="fa fa-file-invoice" style="color:var(--accent);margin-right:8px;"></i>Invoice Terbaru</span>
          <a href="/admin/invoices.php" class="card-link">Lihat semua →</a>
        </div>
        <?php if($recentInvoices): ?>
        <table>
          <thead><tr><th>Klien</th><th>Total</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach($recentInvoices as $inv):
            $badgeMap   = ['Paid'=>'badge-green','Unpaid'=>'badge-yellow','Cancelled'=>'badge-red'];
            $badgeClass = $badgeMap[$inv['status']] ?? 'badge-gray';
          ?>
            <tr>
              <td><?= htmlspecialchars($inv['firstname'].' '.$inv['lastname']) ?></td>
              <td style="font-family:'JetBrains Mono',monospace;font-size:12px;">
                Rp <?= number_format($inv['total'],0,',','.') ?>
              </td>
              <td><span class="badge <?= $badgeClass ?>"><?= $inv['status'] ?></span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php else: ?>
          <div class="empty-state"><i class="fa fa-file-invoice"></i><p>Belum ada invoice.</p></div>
        <?php endif; ?>
      </div>

    </div><!-- /grid-2 -->
  </div><!-- /content -->
</main>

<!-- ═══════════ LOGOUT MODAL ═══════════ -->
<div id="logoutModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
  <div style="background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:32px;max-width:400px;width:90%;text-align:center;box-shadow:0 24px 60px rgba(0,0,0,.5);animation:fadeInUp .25s ease;">
    <div style="width:60px;height:60px;border-radius:50%;background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.25);display:grid;place-items:center;margin:0 auto 18px;font-size:24px;color:#f87171;">
      <i class="fa fa-right-from-bracket"></i>
    </div>
    <h3 style="font-size:18px;font-weight:800;margin-bottom:8px;">Konfirmasi Logout</h3>
    <p style="font-size:13px;color:var(--muted);margin-bottom:26px;line-height:1.7;">
      Anda akan keluar dari sesi admin panel.<br>Pastikan semua pekerjaan sudah tersimpan.
    </p>
    <div style="display:flex;gap:12px;justify-content:center;">
      <button onclick="closeLogoutModal()" class="btn btn-secondary" style="min-width:120px;padding:10px 18px;">
        <i class="fa fa-xmark"></i> Batal
      </button>
      <a href="/admin/logout.php" class="btn btn-danger" style="min-width:120px;padding:10px 18px;">
        <i class="fa fa-right-from-bracket"></i> Ya, Logout
      </a>
    </div>
  </div>
</div>

<style>
@keyframes fadeInUp {
  from { opacity:0; transform:translateY(16px); }
  to   { opacity:1; transform:translateY(0); }
}
</style>

<script>
// ── Logout modal ─────────────────────────────────────────────
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
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLogoutModal(); });

// ── Sub-menu toggle ───────────────────────────────────────────
function toggleSubMenu(e, groupId) {
  const group = document.getElementById(groupId);
  if (!group) return;
  const isOpen = group.classList.contains('open');
  if (isOpen) {
    e.preventDefault();
    group.classList.remove('open');
    e.currentTarget.classList.remove('expanded');
  } else {
    group.classList.add('open');
    e.currentTarget.classList.add('expanded');
  }
}

// ── Notifikasi dropdown ───────────────────────────────────────
function toggleNotif(e) {
  e.stopPropagation();
  document.getElementById('notifDropdown').classList.toggle('open');
}
document.addEventListener('click', e => {
  const dd = document.getElementById('notifDropdown');
  if (dd && !dd.contains(e.target) && !document.getElementById('notifBtn').contains(e.target)) {
    dd.classList.remove('open');
  }
});
function markAllRead() {
  fetch('', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: 'ajax_action=mark_notif_read'
  })
  .then(() => location.reload());
}
</script>

<script>
/* ── Mobile Sidebar Toggle ── */
function toggleSidebar() {
  var sidebar   = document.querySelector('.sidebar');
  var overlay   = document.getElementById('sidebarOverlay');
  var hamburger = document.getElementById('hamburgerBtn');
  sidebar.classList.toggle('open');
  overlay.classList.toggle('open');
  if (hamburger) hamburger.classList.toggle('open');
}
function closeSidebar() {
  var sidebar   = document.querySelector('.sidebar');
  var overlay   = document.getElementById('sidebarOverlay');
  var hamburger = document.getElementById('hamburgerBtn');
  if (sidebar)   sidebar.classList.remove('open');
  if (overlay)   overlay.classList.remove('open');
  if (hamburger) hamburger.classList.remove('open');
}
document.addEventListener('keydown', function(e){
  if (e.key === 'Escape') closeSidebar();
});
</script>

</body>
</html>
