<?php
// ============================================================
// admin/orders_hosting.php – Order Layanan Hosting Perkasa Solusindo
// ============================================================
require_once '../config.php';
require_once '../auth_check.php';
requireLevel([1, 2]);

$adminId   = (int)($_SESSION['user_id'] ?? 0);
$adminName = $_SESSION['user_firstname'] ?? 'Admin';

// ── Statistik sidebar badge ───────────────────────────────────
function countQ($conn, $sql) {
    $r = $conn->query($sql); return $r ? (int)$r->fetch_row()[0] : 0;
}
$stats = [];
$stats['unpaid']  = countQ($conn, "SELECT COUNT(*) FROM tblinvoices WHERE status='Unpaid'");
$stats['tickets'] = countQ($conn, "SELECT COUNT(*) FROM tbltickets WHERE status='Open'");
$totalOrdersPending = countQ($conn,
    "SELECT COUNT(*) FROM tblorders WHERE wifi_status IN ('pending','verified','scheduled')");

// ── Hitung order WiFi pending untuk badge sub-menu ────────────
$wifiPendingNav = countQ($conn,
    "SELECT COUNT(*) FROM tblorders o JOIN tblproducts p ON p.id=o.productid
     WHERE (p.category='wifi' OR o.order_type='wifi') AND o.wifi_status IN ('pending','verified','scheduled')");

// ── Hitung order Hosting per status (untuk kartu ringkasan) ───
$hostingStatusCounts = [];
$rc = $conn->query(
    "SELECT o.wifi_status, COUNT(*) AS cnt
     FROM tblorders o
     JOIN tblproducts p ON p.id = o.productid
     WHERE p.category = 'hosting' OR o.order_type = 'hosting'
     GROUP BY o.wifi_status"
);
if ($rc) { while ($row = $rc->fetch_assoc()) $hostingStatusCounts[$row['wifi_status']] = (int)$row['cnt']; }
$totalHosting = array_sum($hostingStatusCounts);

// ── Filter dari GET ───────────────────────────────────────────
$filterStatus = $_GET['status'] ?? '';
$filterSearch = trim($_GET['q'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

// ── Tambahan filter: rentang tanggal ─────────────────────────
$filterDateFrom = $_GET['from'] ?? '';
$filterDateTo   = $_GET['to']   ?? '';

// ── Build WHERE ───────────────────────────────────────────────
$where  = ["(p.category = 'hosting' OR o.order_type = 'hosting')"];
$types  = '';
$params = [];

if ($filterStatus) {
    $where[] = "o.wifi_status = ?";
    $types .= 's'; $params[] = $filterStatus;
}
if ($filterSearch) {
    $like = "%$filterSearch%";
    $where[] = "(c.firstname LIKE ? OR c.lastname LIKE ? OR o.order_number LIKE ? OR p.name LIKE ? OR c.phonenumber LIKE ? OR h.domain LIKE ?)";
    $types .= 'ssssss'; $params = array_merge($params, [$like,$like,$like,$like,$like,$like]);
}
if ($filterDateFrom) {
    $where[] = "DATE(o.created_at) >= ?";
    $types .= 's'; $params[] = $filterDateFrom;
}
if ($filterDateTo) {
    $where[] = "DATE(o.created_at) <= ?";
    $types .= 's'; $params[] = $filterDateTo;
}

$whereStr = implode(' AND ', $where);

// ── Count total ───────────────────────────────────────────────
$stmtCount = $conn->prepare(
    "SELECT COUNT(*) FROM tblorders o
     JOIN tblclients c  ON c.id  = o.userid
     JOIN tblproducts p ON p.id  = o.productid
     LEFT JOIN tblhosting h ON h.userid = o.userid AND h.packageid = o.productid
     WHERE $whereStr"
);
if ($types) $stmtCount->bind_param($types, ...$params);
$stmtCount->execute();
$totalRows  = (int)$stmtCount->get_result()->fetch_row()[0];
$stmtCount->close();
$totalPages = max(1, ceil($totalRows / $perPage));
$offset     = ($page - 1) * $perPage;

// ── Fetch orders Hosting ───────────────────────────────────────
$orders = [];
$sql = "SELECT o.*,
               CONCAT(c.firstname,' ',c.lastname) AS client_name,
               c.phonenumber, c.email,
               p.name AS product_name, p.price, p.category, p.period,
               h.domain AS hosting_domain, h.domainstatus, h.nextduedate
        FROM tblorders o
        JOIN tblclients c        ON c.id   = o.userid
        JOIN tblproducts p       ON p.id   = o.productid
        LEFT JOIN tblhosting h    ON h.userid = o.userid AND h.packageid = o.productid
        WHERE $whereStr
        ORDER BY
            FIELD(o.wifi_status,'pending','verified','active','cancelled'),
            o.created_at DESC
        LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$allTypes  = $types . 'ii';
$allParams = array_merge($params, [$perPage, $offset]);
$stmt->bind_param($allTypes, ...$allParams);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $orders[] = $row;
$stmt->close();

// ── Helpers ───────────────────────────────────────────────────
// Status khusus order Hosting (berbeda dari status order WiFi)
function hostingBadge($status) {
    $map = [
        'pending'   => ['badge-yellow', 'fa-hourglass-half', 'Menunggu Verifikasi', '#fbbf24'],
        'verified'  => ['badge-blue',   'fa-circle-check',   'Diverifikasi',        '#60a5fa'],
        'active'    => ['badge-green',  'fa-server',         'Hosting Aktif',       '#34d399'],
        'cancelled' => ['badge-red',    'fa-ban',             'Dibatalkan',          '#f87171'],
    ];
    // Status lain (mis. dari alur wifi: scheduled/installed) ditampilkan sebagai fallback netral
    $d = $map[$status] ?? ['badge-gray','fa-circle','–','#7d8590'];
    return ['class'=>$d[0],'icon'=>$d[1],'label'=>$d[2],'color'=>$d[3]];
}
function payBadge($ps) {
    $m = [
        'belum_bayar' => ['#fbbf24','fa-clock',         'Belum Bayar'],
        'sudah_bayar' => ['#60a5fa','fa-upload',        'Bukti Dikirim'],
        'lunas'       => ['#34d399','fa-circle-check',  'Lunas'],
    ];
    return $m[$ps] ?? ['#7d8590','fa-minus','–'];
}
// Ambil info "Periode: ..." dari kolom note (diisi saat checkout)
function extractPeriode($note) {
    if ($note && preg_match('/Periode\s*:\s*([^|]+)/i', $note, $m)) {
        return trim($m[1]);
    }
    return null;
}

// ── Kartu ringkasan status (untuk header panel) ───────────────
// Hanya 4 status yang relevan untuk order Hosting
$statusDefs = [
    'pending'   => ['fa-hourglass-half', '#fbbf24', 'Menunggu Verifikasi', 'rgba(245,158,11,.12)'],
    'verified'  => ['fa-circle-check',   '#60a5fa', 'Diverifikasi',        'rgba(59,130,246,.12)'],
    'active'    => ['fa-server',         '#34d399', 'Hosting Aktif',       'rgba(16,185,129,.12)'],
    'cancelled' => ['fa-ban',            '#f87171', 'Dibatalkan',          'rgba(239,68,68,.12)'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Order Hosting – Perkasa Solusindo Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/admin/style_admin.css">
<style>
/* ── Status Summary Cards ─── */
.status-summary {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 10px;
  margin-bottom: 24px;
}
@media (max-width: 1100px) { .status-summary { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 640px)  { .status-summary { grid-template-columns: repeat(2, 1fr); } }

.status-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 14px 16px;
  text-decoration: none;
  color: var(--text);
  display: flex;
  flex-direction: column;
  gap: 6px;
  transition: all .2s;
  position: relative;
  overflow: hidden;
}
.status-card:hover { transform: translateY(-2px); }
.status-card.active-filter { border-width: 2px; }
.status-card::before {
  content: '';
  position: absolute;
  inset: 0;
  opacity: 0;
  transition: opacity .2s;
}
.status-card:hover::before { opacity: 1; }
.status-card-icon {
  width: 32px; height: 32px;
  border-radius: 8px;
  display: grid; place-items: center;
  font-size: 13px;
  margin-bottom: 4px;
}
.status-card-count {
  font-size: 22px;
  font-weight: 800;
  font-family: 'JetBrains Mono', monospace;
  line-height: 1;
}
.status-card-label {
  font-size: 11px;
  color: var(--muted);
  font-weight: 500;
}

/* ── Filter Bar ─── */
.filter-bar {
  display: flex;
  gap: 10px;
  align-items: center;
  flex-wrap: wrap;
  margin-bottom: 20px;
  padding: 14px 18px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 12px;
}
.filter-bar input[type=text],
.filter-bar input[type=date],
.filter-bar select {
  background: var(--surface2);
  border: 1px solid var(--border);
  color: var(--text);
  padding: 8px 12px;
  border-radius: 8px;
  font-size: 13px;
  font-family: inherit;
  outline: none;
  transition: border-color .2s;
}
.filter-bar input[type=text] { flex: 1; min-width: 200px; }
.filter-bar input[type=date] { min-width: 140px; }
.filter-bar input:focus,
.filter-bar select:focus { border-color: var(--accent2); }
.filter-bar select { min-width: 160px; }
.filter-bar .filter-sep { color: var(--muted); font-size: 11px; white-space: nowrap; }

/* ── Status filter tabs ─── */
.status-tabs {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
  margin-bottom: 18px;
}
.status-tab {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 700;
  text-decoration: none;
  border: 1px solid var(--border);
  color: var(--muted);
  background: var(--surface);
  transition: all .2s;
}
.status-tab:hover { color: var(--text); border-color: var(--accent2); }
.status-tab.active { color: #fff; border-color: transparent; }
.status-tab .cnt {
  background: rgba(255,255,255,.18);
  padding: 1px 7px;
  border-radius: 10px;
  font-size: 10px;
}

/* ── Table ─── */
.hosting-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}
.hosting-table th {
  padding: 11px 14px;
  text-align: left;
  font-size: 11px;
  font-weight: 700;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: .5px;
  border-bottom: 2px solid var(--border);
  background: var(--surface2);
  white-space: nowrap;
}
.hosting-table td {
  padding: 13px 14px;
  border-bottom: 1px solid var(--border);
  vertical-align: middle;
}
.hosting-table tr:last-child td { border-bottom: none; }
.hosting-table tbody tr { transition: background .15s; cursor: pointer; }
.hosting-table tbody tr:hover { background: rgba(16,185,129,.04); }

.order-no {
  font-family: 'JetBrains Mono', monospace;
  font-size: 12px;
  color: var(--accent2);
  font-weight: 600;
}

/* ── Domain pill ─── */
.domain-pill {
  display: inline-flex; align-items: center; gap: 5px;
  background: rgba(16,185,129,.12); color: #34d399;
  padding: 2px 9px; border-radius: 20px;
  font-size: 11px; font-weight: 700;
  font-family: 'JetBrains Mono', monospace;
  max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}

/* ── Periode pill ─── */
.periode-pill {
  display: inline-flex; align-items: center; gap: 5px;
  background: rgba(139,92,246,.12); color: #a78bfa;
  padding: 2px 9px; border-radius: 20px;
  font-size: 11px; font-weight: 600;
}

/* ── Priority indicator (pending is highlighted) ─── */
.hosting-table tbody tr.status-pending  { border-left: 3px solid #fbbf24; }
.hosting-table tbody tr.status-verified { border-left: 3px solid #60a5fa; }

/* ── Pagination ─── */
.pagination {
  display: flex; gap: 6px; align-items: center;
  justify-content: center; padding: 16px 0;
}
.page-btn {
  display: inline-flex; align-items: center; justify-content: center;
  width: 34px; height: 34px; border-radius: 8px;
  font-size: 13px; font-weight: 600; text-decoration: none;
  border: 1px solid var(--border); color: var(--muted);
  background: var(--surface); transition: all .2s;
}
.page-btn:hover { color: var(--text); border-color: var(--accent2); }
.page-btn.active { background: var(--accent2); border-color: var(--accent2); color: #fff; }
.page-btn.disabled { opacity: .3; pointer-events: none; }

/* ── Empty ─── */
.orders-empty { text-align: center; padding: 60px 20px; color: var(--muted); }
.orders-empty i { font-size: 40px; margin-bottom: 14px; display: block; opacity: .4; }
.orders-empty p { font-size: 14px; }

/* ── Breadcrumb ─── */
.breadcrumb { display: flex; align-items: center; gap: 6px; font-size: 13px; color: var(--muted); }
.breadcrumb a { color: var(--muted); text-decoration: none; font-weight: 500; }
.breadcrumb a:hover { color: var(--accent2); }
.breadcrumb i { font-size: 10px; }
.breadcrumb span { color: var(--text); font-weight: 600; }
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
    <a href="/admin/admin_dashboard.php" class="nav-item">
      <i class="fa fa-gauge-high"></i> Dashboard
    </a>

    <div class="nav-label">Transaksi</div>
    <!-- Parent: Semua Order -->
    <a href="/admin/orders.php" class="nav-item has-sub expanded" onclick="toggleSubMenu(event,'subOrders')">
      <i class="fa fa-list-check"></i> Semua Order
      <?php if($totalOrdersPending > 0): ?>
        <span class="nav-badge"><?= $totalOrdersPending ?></span>
      <?php endif; ?>
      <i class="fa fa-chevron-right nav-arrow"></i>
    </a>
    <!-- Sub-menu: Order WiFi & Hosting -->
    <div class="nav-sub-group open" id="subOrders">
      <a href="/admin/orders_wifi.php" class="nav-item nav-sub">
        <i class="fa fa-wifi"></i> Order Layanan WiFi
        <?php if($wifiPendingNav > 0): ?>
          <span class="nav-badge"><?= $wifiPendingNav ?></span>
        <?php endif; ?>
      </a>

      <!-- Order Hosting (aktif / expanded) -->
      <a href="/admin/orders_hosting.php" class="nav-item nav-sub active">
        <i class="fa fa-server"></i> Order Hosting
        <?php
        $hostingPending = ($hostingStatusCounts['pending'] ?? 0) + ($hostingStatusCounts['verified'] ?? 0);
        if ($hostingPending > 0): ?>
          <span class="nav-badge"><?= $hostingPending ?></span>
        <?php endif; ?>
      </a>
      <!-- Filter shortcut links -->
      <a href="/admin/orders_hosting.php?status=pending"
         class="nav-item nav-sub-child <?= ($filterStatus==='pending' ? 'active' : '') ?>">
        <i class="fa fa-hourglass-half" style="color:#fbbf24;"></i> Menunggu
        <?php if($hostingStatusCounts['pending'] ?? 0): ?>
          <span class="nav-badge-soft" style="margin-left:auto;font-size:10px;color:#fbbf24;font-weight:700;"><?= $hostingStatusCounts['pending'] ?></span>
        <?php endif; ?>
      </a>
      <a href="/admin/orders_hosting.php?status=verified"
         class="nav-item nav-sub-child <?= ($filterStatus==='verified' ? 'active' : '') ?>">
        <i class="fa fa-circle-check" style="color:#60a5fa;"></i> Diverifikasi
        <?php if($hostingStatusCounts['verified'] ?? 0): ?>
          <span class="nav-badge-soft" style="margin-left:auto;font-size:10px;color:#60a5fa;font-weight:700;"><?= $hostingStatusCounts['verified'] ?></span>
        <?php endif; ?>
      </a>
      <a href="/admin/orders_hosting.php?status=active"
         class="nav-item nav-sub-child <?= ($filterStatus==='active' ? 'active' : '') ?>">
        <i class="fa fa-server" style="color:#34d399;"></i> Aktif
        <?php if($hostingStatusCounts['active'] ?? 0): ?>
          <span class="nav-badge-soft" style="margin-left:auto;font-size:10px;color:#34d399;font-weight:700;"><?= $hostingStatusCounts['active'] ?></span>
        <?php endif; ?>
      </a>
      <a href="/admin/orders_hosting.php?status=cancelled"
         class="nav-item nav-sub-child <?= ($filterStatus==='cancelled' ? 'active' : '') ?>">
        <i class="fa fa-ban" style="color:#f87171;"></i> Dibatalkan
        <?php if($hostingStatusCounts['cancelled'] ?? 0): ?>
          <span class="nav-badge-soft" style="margin-left:auto;font-size:10px;color:#f87171;font-weight:700;"><?= $hostingStatusCounts['cancelled'] ?></span>
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
    <a href="/admin/products.php"  class="nav-item"><i class="fa fa-box-open"></i> Produk Layanan</a>
    <a href="/admin/clients.php"   class="nav-item"><i class="fa fa-users"></i> Data Klien</a>
    <a href="/admin/teknisi.php"   class="nav-item"><i class="fa fa-screwdriver-wrench"></i> Teknisi</a>
    <a href="/admin/hosting.php"   class="nav-item"><i class="fa fa-server"></i> Hosting</a>
    <a href="/admin/domains.php"   class="nav-item"><i class="fa fa-globe"></i> Domain</a>

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
    <a href="/admin/settings.php"  class="nav-item"><i class="fa fa-gear"></i> Pengaturan</a>
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
    <div class="breadcrumb">
      <a href="/admin/admin_dashboard.php">Dashboard</a>
      <i class="fa fa-chevron-right"></i>
      <a href="/admin/orders.php">Semua Order</a>
      <i class="fa fa-chevron-right"></i>
      <span>Order Hosting</span>
    </div>
    <div class="topbar-right">
      <span class="date-badge">
        <i class="fa fa-calendar-days" style="margin-right:6px;"></i><?= date('d M Y') ?>
      </span>
      <a href="#" class="topbar-btn" onclick="confirmLogout(event)" title="Logout">
        <i class="fa fa-right-from-bracket"></i>
      </a>
    </div>
  </div>

  <div class="content">

    <!-- Page Header -->
    <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:14px;">
      <div>
        <h1 style="font-size:22px;font-weight:800;margin-bottom:6px;display:flex;align-items:center;gap:10px;">
          <span style="width:40px;height:40px;background:rgba(16,185,129,.12);border-radius:10px;display:inline-grid;place-items:center;">
            <i class="fa fa-server" style="color:#34d399;font-size:17px;"></i>
          </span>
          Order Hosting
        </h1>
        <p style="font-size:13px;color:var(--muted);">
          Seluruh order layanan hosting dari semua akun klien.
          <?php if($filterStatus): ?>
            Menampilkan: <strong style="color:var(--text);"><?= $statusDefs[$filterStatus][2] ?? $filterStatus ?></strong>
          <?php endif; ?>
        </p>
      </div>
      <div style="display:flex;align-items:center;gap:10px;">
        <div style="font-family:'JetBrains Mono',monospace;font-size:12px;color:var(--muted);background:var(--surface);border:1px solid var(--border);padding:8px 14px;border-radius:8px;">
          Total Hosting: <strong style="color:#34d399;"><?= number_format($totalHosting) ?></strong> order
        </div>
        <?php if($filterStatus || $filterSearch || $filterDateFrom || $filterDateTo): ?>
        <a href="/admin/orders_hosting.php" class="btn btn-secondary" style="padding:8px 14px;">
          <i class="fa fa-xmark"></i> Reset Filter
        </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Status Summary Cards -->
    <div class="status-summary">
      <?php foreach($statusDefs as $sval => [$sicon,$scolor,$slabel,$sbg]): ?>
      <?php
        $cnt = $hostingStatusCounts[$sval] ?? 0;
        $isActive = ($filterStatus === $sval);
        $href = '/admin/orders_hosting.php?' . ($isActive ? '' : 'status=' . $sval);
      ?>
      <a href="<?= $href ?>"
         class="status-card <?= $isActive ? 'active-filter' : '' ?>"
         style="border-color:<?= $isActive ? $scolor : 'var(--border)' ?>;background:<?= $isActive ? $sbg : 'var(--surface)' ?>;">
        <div class="status-card-icon" style="background:<?= $sbg ?>;color:<?= $scolor ?>;">
          <i class="fa <?= $sicon ?>"></i>
        </div>
        <div class="status-card-count" style="color:<?= $isActive ? $scolor : 'var(--text)' ?>;">
          <?= number_format($cnt) ?>
        </div>
        <div class="status-card-label"><?= $slabel ?></div>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- Status Filter Tabs -->
    <div class="status-tabs">
      <?php
      $allHref = '/admin/orders_hosting.php?' . http_build_query(array_filter(['q'=>$filterSearch,'from'=>$filterDateFrom,'to'=>$filterDateTo]));
      ?>
      <a href="<?= $allHref ?>"
         class="status-tab <?= $filterStatus === '' ? 'active' : '' ?>"
         style="<?= $filterStatus === '' ? 'background:#10b981;border-color:#10b981;' : '' ?>">
        <i class="fa fa-th-large"></i> Semua
        <span class="cnt"><?= number_format($totalHosting) ?></span>
      </a>
      <?php foreach($statusDefs as $sval => [$sicon,$scolor,$slabel,$sbg]):
        $cnt  = $hostingStatusCounts[$sval] ?? 0;
        $isAc = ($filterStatus === $sval);
        $href = '/admin/orders_hosting.php?' . http_build_query(array_filter(['status'=>$sval,'q'=>$filterSearch,'from'=>$filterDateFrom,'to'=>$filterDateTo]));
      ?>
      <a href="<?= $href ?>"
         class="status-tab <?= $isAc ? 'active' : '' ?>"
         style="<?= $isAc ? "background:$scolor;border-color:$scolor;" : '' ?>">
        <i class="fa <?= $sicon ?>" style="<?= $isAc ? '' : "color:$scolor;" ?>"></i>
        <?= $slabel ?>
        <span class="cnt"><?= $cnt ?></span>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- Filter Bar -->
    <form method="GET" action="/admin/orders_hosting.php" class="filter-bar">
      <?php if($filterStatus): ?>
        <input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus) ?>">
      <?php endif; ?>
      <i class="fa fa-magnifying-glass" style="color:var(--muted);flex-shrink:0;"></i>
      <input type="text" name="q" placeholder="Cari nama klien, no. order, domain, produk, no. HP…"
             value="<?= htmlspecialchars($filterSearch) ?>">
      <span class="filter-sep">Dari</span>
      <input type="date" name="from" value="<?= htmlspecialchars($filterDateFrom) ?>" title="Tanggal mulai">
      <span class="filter-sep">–</span>
      <input type="date" name="to"   value="<?= htmlspecialchars($filterDateTo) ?>"   title="Tanggal akhir">
      <button type="submit" class="btn btn-primary" style="padding:8px 18px;flex-shrink:0;">
        <i class="fa fa-filter"></i> Filter
      </button>
      <?php if($filterSearch || $filterDateFrom || $filterDateTo): ?>
      <a href="/admin/orders_hosting.php<?= $filterStatus ? '?status='.$filterStatus : '' ?>"
         class="btn btn-secondary" style="padding:8px 14px;flex-shrink:0;">
        <i class="fa fa-xmark"></i>
      </a>
      <?php endif; ?>
    </form>

    <!-- Result Info -->
    <div style="font-size:12px;color:var(--muted);margin-bottom:12px;">
      Menampilkan <strong style="color:var(--text);"><?= number_format(min($offset+1, $totalRows)) ?>–<?= number_format(min($offset+$perPage, $totalRows)) ?></strong>
      dari <strong style="color:var(--text);"><?= number_format($totalRows) ?></strong> order Hosting
      <?= ($filterStatus || $filterSearch || $filterDateFrom || $filterDateTo) ? ' (terfilter)' : '' ?>
    </div>

    <!-- Table Card -->
    <div class="card" style="padding:0;overflow:hidden;">
      <?php if(empty($orders)): ?>
        <div class="orders-empty">
          <i class="fa fa-server"></i>
          <p>Tidak ada order Hosting<?= ($filterSearch||$filterStatus||$filterDateFrom||$filterDateTo) ? ' untuk filter ini.' : ' ditemukan.' ?></p>
          <?php if($filterStatus || $filterSearch || $filterDateFrom || $filterDateTo): ?>
          <a href="/admin/orders_hosting.php" class="btn btn-secondary" style="margin-top:14px;display:inline-flex;">
            <i class="fa fa-xmark"></i> Reset Filter
          </a>
          <?php endif; ?>
        </div>
      <?php else: ?>
      <div style="overflow-x:auto;">
        <table class="hosting-table">
          <thead>
            <tr>
              <th>#</th>
              <th>No. Order</th>
              <th>Klien</th>
              <th>Paket Hosting</th>
              <th>Domain</th>
              <th>Periode</th>
              <th>Harga</th>
              <th>Status</th>
              <th>Pembayaran</th>
              <th>Tgl Order</th>
              <th style="text-align:center;">Aksi</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach($orders as $idx => $ord):
            $badge   = hostingBadge($ord['wifi_status']);
            $pay     = payBadge($ord['payment_status']);
            $rowNum  = $offset + $idx + 1;
            $periode = extractPeriode($ord['note']) ?: ($ord['period'] ?: '–');
            $domain  = $ord['hosting_domain'] ?? null;
          ?>
            <tr class="status-<?= $ord['wifi_status'] ?>"
                onclick="window.location='/admin/order_detail.php?id=<?= $ord['id'] ?>'"
                onmouseover="this.style.background='rgba(16,185,129,.05)'"
                onmouseout="this.style.background=''">

              <!-- Nomor urut -->
              <td style="color:var(--muted);font-size:12px;width:36px;"><?= $rowNum ?></td>

              <!-- No. Order -->
              <td>
                <div class="order-no"><?= htmlspecialchars($ord['order_number'] ?? '#'.$ord['id']) ?></div>
                <div style="font-size:10px;color:var(--muted);margin-top:2px;font-family:'JetBrains Mono',monospace;">ID #<?= $ord['id'] ?></div>
              </td>

              <!-- Klien -->
              <td style="min-width:160px;">
                <div style="font-weight:700;font-size:13px;"><?= htmlspecialchars($ord['client_name']) ?></div>
                <div style="font-size:11px;color:var(--muted);">
                  <i class="fa fa-phone" style="font-size:10px;margin-right:4px;"></i><?= htmlspecialchars($ord['phonenumber']) ?>
                </div>
                <div style="font-size:11px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:180px;">
                  <i class="fa fa-envelope" style="font-size:10px;margin-right:4px;"></i><?= htmlspecialchars($ord['email']) ?>
                </div>
              </td>

              <!-- Paket Hosting -->
              <td>
                <div style="font-size:13px;font-weight:600;"><?= htmlspecialchars($ord['product_name']) ?></div>
              </td>

              <!-- Domain -->
              <td>
                <?php if($domain): ?>
                  <span class="domain-pill" title="<?= htmlspecialchars($domain) ?>">
                    <i class="fa fa-globe"></i> <?= htmlspecialchars($domain) ?>
                  </span>
                <?php else: ?>
                  <span style="font-size:11px;color:var(--muted);">— belum diisi</span>
                <?php endif; ?>
              </td>

              <!-- Periode -->
              <td>
                <span class="periode-pill"><i class="fa fa-calendar"></i> <?= htmlspecialchars($periode) ?></span>
              </td>

              <!-- Harga -->
              <td style="font-family:'JetBrains Mono',monospace;font-size:12px;white-space:nowrap;color:var(--accent);">
                Rp <?= number_format($ord['price'],0,',','.') ?>
              </td>

              <!-- Status -->
              <td>
                <span class="badge <?= $badge['class'] ?>">
                  <i class="fa <?= $badge['icon'] ?>" style="margin-right:4px;font-size:10px;"></i>
                  <?= $badge['label'] ?>
                </span>
              </td>

              <!-- Pembayaran -->
              <td>
                <span style="font-size:11px;font-weight:700;color:<?= $pay[0] ?>;display:inline-flex;align-items:center;gap:5px;">
                  <i class="fa <?= $pay[1] ?>"></i>
                  <?= $pay[2] ?>
                </span>
                <?php if($ord['payment_status'] === 'sudah_bayar'): ?>
                  <div style="font-size:10px;color:#60a5fa;margin-top:2px;">⚡ Perlu konfirmasi</div>
                <?php endif; ?>
                <?php if($ord['payment_status'] !== 'lunas' && !empty($ord['payment_deadline'])):
                    $dlTs   = strtotime($ord['payment_deadline']);
                    $sisaMn = (int)floor(($dlTs - time()) / 60);
                ?>
                  <div style="font-size:10px;margin-top:3px;font-weight:600;color:<?= $sisaMn <= 0 ? '#f87171' : ($sisaMn <= 120 ? '#fbbf24' : '#7d8590') ?>;">
                    <i class="fa fa-hourglass-half" style="margin-right:3px;"></i>
                    <?php if ($sisaMn <= 0): ?>
                      Lewat batas — akan dihapus cron
                    <?php else: ?>
                      Sisa <?= $sisaMn >= 60 ? floor($sisaMn/60).'j '.($sisaMn%60).'m' : $sisaMn.'m' ?>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
              </td>

              <!-- Tgl Order -->
              <td style="font-size:11px;color:var(--muted);white-space:nowrap;font-family:'JetBrains Mono',monospace;">
                <?= date('d M Y', strtotime($ord['created_at'])) ?>
                <div style="font-size:10px;"><?= date('H:i', strtotime($ord['created_at'])) ?></div>
              </td>

              <!-- Aksi -->
              <td style="text-align:center;white-space:nowrap;">
                <a href="/admin/order_detail.php?id=<?= $ord['id'] ?>"
                   onclick="event.stopPropagation();"
                   class="btn btn-primary"
                   style="padding:6px 14px;font-size:12px;">
                  <i class="fa fa-pen-to-square"></i> Proses
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if($totalPages > 1): ?>
      <div class="pagination">
        <?php
        $buildUrl = function($p) use ($filterStatus,$filterSearch,$filterDateFrom,$filterDateTo) {
            return '/admin/orders_hosting.php?' . http_build_query(array_filter([
                'status'=>$filterStatus,'q'=>$filterSearch,
                'from'=>$filterDateFrom,'to'=>$filterDateTo,'page'=>$p
            ]));
        };
        ?>
        <a href="<?= $buildUrl($page-1) ?>" class="page-btn <?= $page<=1 ? 'disabled' : '' ?>">
          <i class="fa fa-chevron-left"></i>
        </a>
        <?php
        $start = max(1, $page-2);
        $end   = min($totalPages, $page+2);
        if ($start > 1): ?>
          <a href="<?= $buildUrl(1) ?>" class="page-btn">1</a>
          <?php if($start > 2): ?><span style="color:var(--muted);padding:0 4px;">…</span><?php endif; ?>
        <?php endif; ?>
        <?php for($p=$start; $p<=$end; $p++): ?>
          <a href="<?= $buildUrl($p) ?>" class="page-btn <?= $p===$page ? 'active' : '' ?>"><?= $p ?></a>
        <?php endfor; ?>
        <?php if ($end < $totalPages): ?>
          <?php if($end < $totalPages-1): ?><span style="color:var(--muted);padding:0 4px;">…</span><?php endif; ?>
          <a href="<?= $buildUrl($totalPages) ?>" class="page-btn"><?= $totalPages ?></a>
        <?php endif; ?>
        <a href="<?= $buildUrl($page+1) ?>" class="page-btn <?= $page>=$totalPages ? 'disabled' : '' ?>">
          <i class="fa fa-chevron-right"></i>
        </a>
        <span style="font-size:12px;color:var(--muted);margin-left:6px;">
          Hal <?= $page ?> / <?= $totalPages ?>
        </span>
      </div>
      <?php endif; ?>

      <?php endif; ?>
    </div><!-- /card -->

  </div><!-- /content -->
</main>

<!-- ═══════════ LOGOUT MODAL ═══════════ -->
<div id="logoutModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
  <div style="background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:32px;max-width:400px;width:90%;text-align:center;box-shadow:0 24px 60px rgba(0,0,0,.5);">
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

<script>
function toggleSubMenu(e, groupId) {
  const group = document.getElementById(groupId);
  if (!group) return;
  const isOpen = group.classList.contains('open');
  if (isOpen) { e.preventDefault(); group.classList.remove('open'); e.currentTarget.classList.remove('expanded'); }
  else { group.classList.add('open'); e.currentTarget.classList.add('expanded'); }
}
function confirmLogout(e) { e.preventDefault(); document.getElementById('logoutModal').style.display='flex'; }
function closeLogoutModal() { document.getElementById('logoutModal').style.display='none'; }
document.getElementById('logoutModal').addEventListener('click', function(e){ if(e.target===this) closeLogoutModal(); });
document.addEventListener('keydown', e=>{ if(e.key==='Escape') closeLogoutModal(); });
</script>
</body>
</html>
