<?php
// ============================================================
// admin/invoices.php – Daftar Invoice (Tagihan) Perkasa Solusindo
// ============================================================
require_once '../config.php';
require_once '../auth_check.php';
requireLevel([1, 2]);

$adminId   = (int)($_SESSION['user_id'] ?? 0);
$adminName = $_SESSION['user_firstname'] ?? 'Admin';

// ── Statistik sidebar badge (sama dengan pola di orders_hosting.php) ──
function countQ($conn, $sql) {
    $r = $conn->query($sql); return $r ? (int)$r->fetch_row()[0] : 0;
}
$stats = [];
$stats['unpaid']  = countQ($conn, "SELECT COUNT(*) FROM tblinvoices WHERE status='Unpaid'");
$stats['tickets'] = countQ($conn, "SELECT COUNT(*) FROM tbltickets WHERE status='Open'");

$wifiPendingNav = countQ($conn,
    "SELECT COUNT(*) FROM tblorders o JOIN tblproducts p ON p.id=o.productid
     WHERE (p.category='wifi' OR o.order_type='wifi') AND o.wifi_status IN ('pending','verified','scheduled')");
$hostingPendingNav = countQ($conn,
    "SELECT COUNT(*) FROM tblorders o JOIN tblproducts p ON p.id=o.productid
     WHERE (p.category='hosting' OR o.order_type='hosting') AND o.wifi_status IN ('pending','verified')");

// ── Hitung invoice per status (untuk kartu ringkasan) ──────────
$invoiceStatusCounts = [];
$rc = $conn->query("SELECT status, COUNT(*) AS cnt FROM tblinvoices GROUP BY status");
if ($rc) { while ($row = $rc->fetch_assoc()) $invoiceStatusCounts[$row['status']] = (int)$row['cnt']; }
$totalInvoices = array_sum($invoiceStatusCounts);

// ── Total nominal Unpaid (untuk kartu ringkasan tambahan) ──────
$totalUnpaidAmount = (float)$conn->query("SELECT COALESCE(SUM(total),0) FROM tblinvoices WHERE status='Unpaid'")->fetch_row()[0];

// ── Filter dari GET ─────────────────────────────────────────────
$filterStatus   = $_GET['status'] ?? '';
$filterSearch   = trim($_GET['q'] ?? '');
$filterJenis    = $_GET['jenis'] ?? ''; // wifi / hosting
$filterDateFrom = $_GET['from'] ?? '';
$filterDateTo   = $_GET['to']   ?? '';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

// ── Build WHERE ───────────────────────────────────────────────
$where  = ['1=1'];
$types  = '';
$params = [];

if ($filterStatus) {
    $where[] = 'i.status = ?';
    $types .= 's'; $params[] = $filterStatus;
}
if ($filterJenis) {
    $where[] = 'o.order_type = ?';
    $types .= 's'; $params[] = $filterJenis;
}
if ($filterSearch) {
    $like = "%$filterSearch%";
    $where[] = "(c.firstname LIKE ? OR c.lastname LIKE ? OR o.order_number LIKE ? OR p.name LIKE ?)";
    $types .= 'ssss'; $params = array_merge($params, [$like,$like,$like,$like]);
}
if ($filterDateFrom) {
    $where[] = 'DATE(i.created_at) >= ?';
    $types .= 's'; $params[] = $filterDateFrom;
}
if ($filterDateTo) {
    $where[] = 'DATE(i.created_at) <= ?';
    $types .= 's'; $params[] = $filterDateTo;
}

$whereStr = implode(' AND ', $where);

// ── Count total ───────────────────────────────────────────────
$stmtCount = $conn->prepare(
    "SELECT COUNT(*) FROM tblinvoices i
     LEFT JOIN tblorders   o ON o.id = i.order_id
     LEFT JOIN tblclients  c ON c.id = i.userid
     LEFT JOIN tblproducts p ON p.id = o.productid
     WHERE $whereStr"
);
if ($types) $stmtCount->bind_param($types, ...$params);
$stmtCount->execute();
$totalRows  = (int)$stmtCount->get_result()->fetch_row()[0];
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;
$stmtCount->close();

// ── Ambil data invoice (halaman ini) ────────────────────────────
$sql = "
    SELECT i.*, o.order_number, o.order_type, o.wifi_status,
           c.firstname, c.lastname, c.phonenumber, c.email,
           p.name AS product_name
    FROM tblinvoices i
    LEFT JOIN tblorders   o ON o.id = i.order_id
    LEFT JOIN tblclients  c ON c.id = i.userid
    LEFT JOIN tblproducts p ON p.id = o.productid
    WHERE $whereStr
    ORDER BY i.created_at DESC
    LIMIT ? OFFSET ?
";
$typesFull  = $types . 'ii';
$paramsFull = array_merge($params, [$perPage, $offset]);
$stmt = $conn->prepare($sql);
$stmt->bind_param($typesFull, ...$paramsFull);
$stmt->execute();
$invoices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Helper badge status invoice ─────────────────────────────────
function invBadge($status) {
    $map = [
        'Unpaid'      => ['fa-hourglass-half', '#fbbf24', 'Belum Bayar',  'rgba(245,158,11,.12)'],
        'Paid'        => ['fa-circle-check',   '#34d399', 'Lunas',        'rgba(16,185,129,.12)'],
        'Cancelled'   => ['fa-ban',            '#f87171', 'Dibatalkan',   'rgba(239,68,68,.12)'],
        'Refunded'    => ['fa-rotate-left',    '#a78bfa', 'Dikembalikan', 'rgba(139,92,246,.12)'],
        'Collections' => ['fa-triangle-exclamation', '#fb923c', 'Penagihan', 'rgba(251,146,60,.12)'],
        'Draft'       => ['fa-file',           '#94a3b8', 'Draft',        'rgba(148,163,184,.12)'],
    ];
    return $map[$status] ?? ['fa-circle', '#94a3b8', $status, 'rgba(148,163,184,.12)'];
}

$statusDefs = [
    'Unpaid'    => ['fa-hourglass-half', '#fbbf24', 'Belum Bayar', 'rgba(245,158,11,.12)'],
    'Paid'      => ['fa-circle-check',   '#34d399', 'Lunas',       'rgba(16,185,129,.12)'],
    'Cancelled' => ['fa-ban',            '#f87171', 'Dibatalkan',  'rgba(239,68,68,.12)'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Invoice & Tagihan – Perkasa Solusindo Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/admin/style_admin.css">
<style>
.status-summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 24px; }
@media (max-width: 1100px) { .status-summary { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 640px)  { .status-summary { grid-template-columns: repeat(2, 1fr); } }
.status-card {
  background: var(--surface); border: 1px solid var(--border); border-radius: 12px;
  padding: 14px 16px; text-decoration: none; color: var(--text);
  display: flex; flex-direction: column; gap: 6px; transition: all .2s; position: relative; overflow: hidden;
}
.status-card:hover { transform: translateY(-2px); }
.status-card.active-filter { border-width: 2px; }
.status-card-icon { width: 32px; height: 32px; border-radius: 8px; display: grid; place-items: center; font-size: 13px; margin-bottom: 4px; }
.status-card-count { font-size: 22px; font-weight: 800; font-family: 'JetBrains Mono', monospace; line-height: 1; }
.status-card-label { font-size: 11px; color: var(--muted); font-weight: 500; }

.filter-bar {
  display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 20px;
  padding: 14px 18px; background: var(--surface); border: 1px solid var(--border); border-radius: 12px;
}
.filter-bar input[type=text], .filter-bar input[type=date], .filter-bar select {
  background: var(--surface2); border: 1px solid var(--border); color: var(--text);
  padding: 8px 12px; border-radius: 8px; font-size: 13px; font-family: inherit; outline: none; transition: border-color .2s;
}
.filter-bar input[type=text] { flex: 1; min-width: 200px; }
.filter-bar input[type=date] { min-width: 140px; }
.filter-bar input:focus, .filter-bar select:focus { border-color: var(--accent2); }
.filter-bar select { min-width: 140px; }
.filter-bar .filter-sep { color: var(--muted); font-size: 11px; white-space: nowrap; }

.status-tabs { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 18px; }
.status-tab {
  display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 20px;
  font-size: 12px; font-weight: 700; text-decoration: none; border: 1px solid var(--border);
  color: var(--muted); background: var(--surface); transition: all .2s;
}
.status-tab:hover { color: var(--text); border-color: var(--accent2); }
.status-tab.active { color: #fff; border-color: transparent; }
.status-tab .cnt { background: rgba(255,255,255,.18); padding: 1px 7px; border-radius: 10px; font-size: 10px; }

.inv-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.inv-table th {
  padding: 11px 14px; text-align: left; font-size: 11px; font-weight: 700; color: var(--muted);
  text-transform: uppercase; letter-spacing: .5px; border-bottom: 2px solid var(--border);
  background: var(--surface2); white-space: nowrap;
}
.inv-table td { padding: 13px 14px; border-bottom: 1px solid var(--border); vertical-align: middle; }
.inv-table tr:last-child td { border-bottom: none; }
.inv-table tbody tr { transition: background .15s; }
.inv-table tbody tr:hover { background: rgba(16,185,129,.04); }

.order-no { font-family: 'JetBrains Mono', monospace; font-size: 12px; color: var(--accent2); font-weight: 600; }
.jenis-pill {
  display: inline-flex; align-items: center; gap: 5px; padding: 2px 9px; border-radius: 20px;
  font-size: 11px; font-weight: 700;
}
.jenis-pill.wifi    { background: rgba(59,130,246,.12); color: #60a5fa; }
.jenis-pill.hosting { background: rgba(16,185,129,.12); color: #34d399; }

.pagination { display: flex; gap: 6px; align-items: center; justify-content: center; padding: 16px 0; }
.page-btn {
  display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 8px;
  font-size: 13px; font-weight: 600; text-decoration: none; border: 1px solid var(--border); color: var(--muted);
  background: var(--surface); transition: all .2s;
}
.page-btn:hover { color: var(--text); border-color: var(--accent2); }
.page-btn.active { background: var(--accent2); border-color: var(--accent2); color: #fff; }
.page-btn.disabled { opacity: .3; pointer-events: none; }

.orders-empty { text-align: center; padding: 60px 20px; color: var(--muted); }
.orders-empty i { font-size: 40px; margin-bottom: 14px; display: block; opacity: .4; }
.orders-empty p { font-size: 14px; }

.breadcrumb { display: flex; align-items: center; gap: 6px; font-size: 13px; color: var(--muted); }
.breadcrumb a { color: var(--muted); text-decoration: none; font-weight: 500; }
.breadcrumb a:hover { color: var(--accent2); }
.breadcrumb i { font-size: 10px; }
.breadcrumb span { color: var(--text); font-weight: 600; }
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
      <div class="logo-text">Perkasa Solusindo<span>Admin Panel</span></div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <a href="/admin/admin_dashboard.php" class="nav-item">
      <i class="fa fa-gauge"></i> Dashboard
    </a>

    <div class="nav-label">Order</div>
    <a href="/admin/orders.php" class="nav-item">
      <i class="fa fa-list-check"></i> Semua Order
    </a>
    <a href="/admin/orders_wifi.php" class="nav-item">
      <i class="fa fa-wifi"></i> Order WiFi
      <?php if($wifiPendingNav > 0): ?><span class="nav-badge"><?= $wifiPendingNav ?></span><?php endif; ?>
    </a>
    <a href="/admin/orders_hosting.php" class="nav-item">
      <i class="fa fa-server"></i> Order Hosting
      <?php if($hostingPendingNav > 0): ?><span class="nav-badge"><?= $hostingPendingNav ?></span><?php endif; ?>
    </a>

    <a href="/admin/invoices.php" class="nav-item active">
      <i class="fa fa-file-invoice-dollar"></i> Invoice
      <?php if($stats['unpaid'] > 0): ?>
        <span class="nav-badge"><?= $stats['unpaid'] ?></span>
      <?php endif; ?>
    </a>

    <div class="nav-label">Manajemen</div>
    <a href="/admin/products.php"  class="nav-item"><i class="fa fa-box-open"></i> Produk Layanan</a>
    <a href="/admin/clients.php"   class="nav-item"><i class="fa fa-users"></i> Data Klien</a>
    <a href="/admin/teknisi.php"   class="nav-item"><i class="fa fa-screwdriver-wrench"></i> Teknisi</a>

    <div class="nav-label">Support</div>
    <a href="/admin/tickets.php" class="nav-item">
      <i class="fa fa-ticket"></i> Tiket Support
      <?php if($stats['tickets'] > 0): ?>
        <span class="nav-badge"><?= $stats['tickets'] ?></span>
      <?php endif; ?>
    </a>

    <div class="nav-label">Sistem</div>
    <a href="../index.php" target="_blank" class="nav-item"><i class="fa fa-globe"></i> Lihat Website</a>
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

  <div class="topbar">
    <button class="hamburger" id="hamburgerBtn" onclick="toggleSidebar()" aria-label="Toggle Menu"><span></span><span></span><span></span></button>
    <div class="breadcrumb">
      <a href="/admin/admin_dashboard.php">Dashboard</a>
      <i class="fa fa-chevron-right"></i>
      <span>Invoice &amp; Tagihan</span>
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
          <span style="width:40px;height:40px;background:rgba(245,158,11,.12);border-radius:10px;display:inline-grid;place-items:center;">
            <i class="fa fa-file-invoice-dollar" style="color:#fbbf24;font-size:17px;"></i>
          </span>
          Invoice &amp; Tagihan
        </h1>
        <p style="font-size:13px;color:var(--muted);">
          Seluruh invoice yang dihasilkan dari order WiFi dan Hosting.
          <?php if($filterStatus): ?>
            Menampilkan: <strong style="color:var(--text);"><?= $statusDefs[$filterStatus][2] ?? $filterStatus ?></strong>
          <?php endif; ?>
        </p>
      </div>
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
        <div style="font-family:'JetBrains Mono',monospace;font-size:12px;color:var(--muted);background:var(--surface);border:1px solid var(--border);padding:8px 14px;border-radius:8px;">
          Belum Bayar: <strong style="color:#fbbf24;">Rp <?= number_format($totalUnpaidAmount,0,',','.') ?></strong>
        </div>
        <?php if($filterStatus || $filterSearch || $filterDateFrom || $filterDateTo || $filterJenis): ?>
        <a href="/admin/invoices.php" class="btn btn-secondary" style="padding:8px 14px;">
          <i class="fa fa-xmark"></i> Reset Filter
        </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Status Summary Cards -->
    <div class="status-summary">
      <?php foreach($statusDefs as $sval => [$sicon,$scolor,$slabel,$sbg]): ?>
      <?php
        $cnt = $invoiceStatusCounts[$sval] ?? 0;
        $isActive = ($filterStatus === $sval);
        $href = '/admin/invoices.php?' . ($isActive ? '' : 'status=' . $sval);
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
      <div class="status-card" style="cursor:default;">
        <div class="status-card-icon" style="background:rgba(167,139,250,.12);color:#a78bfa;">
          <i class="fa fa-layer-group"></i>
        </div>
        <div class="status-card-count" style="color:var(--text);"><?= number_format($totalInvoices) ?></div>
        <div class="status-card-label">Total Invoice</div>
      </div>
    </div>

    <!-- Status Filter Tabs -->
    <div class="status-tabs">
      <?php
      $allHref = '/admin/invoices.php?' . http_build_query(array_filter(['q'=>$filterSearch,'jenis'=>$filterJenis,'from'=>$filterDateFrom,'to'=>$filterDateTo]));
      ?>
      <a href="<?= $allHref ?>"
         class="status-tab <?= $filterStatus === '' ? 'active' : '' ?>"
         style="<?= $filterStatus === '' ? 'background:#10b981;border-color:#10b981;' : '' ?>">
        <i class="fa fa-th-large"></i> Semua
        <span class="cnt"><?= number_format($totalInvoices) ?></span>
      </a>
      <?php foreach($statusDefs as $sval => [$sicon,$scolor,$slabel,$sbg]):
        $cnt  = $invoiceStatusCounts[$sval] ?? 0;
        $isAc = ($filterStatus === $sval);
        $href = '/admin/invoices.php?' . http_build_query(array_filter(['status'=>$sval,'q'=>$filterSearch,'jenis'=>$filterJenis,'from'=>$filterDateFrom,'to'=>$filterDateTo]));
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
    <form method="GET" action="/admin/invoices.php" class="filter-bar">
      <?php if($filterStatus): ?>
        <input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus) ?>">
      <?php endif; ?>
      <i class="fa fa-magnifying-glass" style="color:var(--muted);flex-shrink:0;"></i>
      <input type="text" name="q" placeholder="Cari nama klien, no. order, produk…"
             value="<?= htmlspecialchars($filterSearch) ?>">
      <select name="jenis">
        <option value="">Semua Jenis</option>
        <option value="wifi"    <?= $filterJenis==='wifi'    ? 'selected':'' ?>>WiFi</option>
        <option value="hosting" <?= $filterJenis==='hosting' ? 'selected':'' ?>>Hosting</option>
      </select>
      <span class="filter-sep">Dari</span>
      <input type="date" name="from" value="<?= htmlspecialchars($filterDateFrom) ?>" title="Tanggal mulai">
      <span class="filter-sep">–</span>
      <input type="date" name="to"   value="<?= htmlspecialchars($filterDateTo) ?>"   title="Tanggal akhir">
      <button type="submit" class="btn btn-primary" style="padding:8px 18px;flex-shrink:0;">
        <i class="fa fa-filter"></i> Filter
      </button>
      <?php if($filterSearch || $filterDateFrom || $filterDateTo || $filterJenis): ?>
      <a href="/admin/invoices.php<?= $filterStatus ? '?status='.$filterStatus : '' ?>"
         class="btn btn-secondary" style="padding:8px 14px;flex-shrink:0;">
        <i class="fa fa-xmark"></i>
      </a>
      <?php endif; ?>
    </form>

    <!-- Result Info -->
    <div style="font-size:12px;color:var(--muted);margin-bottom:12px;">
      Menampilkan <strong style="color:var(--text);"><?= number_format(min($offset+1, $totalRows)) ?>–<?= number_format(min($offset+$perPage, $totalRows)) ?></strong>
      dari <strong style="color:var(--text);"><?= number_format($totalRows) ?></strong> invoice
      <?= ($filterStatus || $filterSearch || $filterDateFrom || $filterDateTo || $filterJenis) ? ' (terfilter)' : '' ?>
    </div>

    <!-- Table Card -->
    <div class="card" style="padding:0;overflow:hidden;">
      <?php if(empty($invoices)): ?>
        <div class="orders-empty">
          <i class="fa fa-file-invoice"></i>
          <p>Tidak ada invoice<?= ($filterSearch||$filterStatus||$filterDateFrom||$filterDateTo||$filterJenis) ? ' untuk filter ini.' : ' ditemukan.' ?></p>
          <?php if($filterStatus || $filterSearch || $filterDateFrom || $filterDateTo || $filterJenis): ?>
          <a href="/admin/invoices.php" class="btn btn-secondary" style="margin-top:14px;display:inline-flex;">
            <i class="fa fa-xmark"></i> Reset Filter
          </a>
          <?php endif; ?>
        </div>
      <?php else: ?>
      <div style="overflow-x:auto;">
        <table class="inv-table">
          <thead>
            <tr>
              <th>#</th>
              <th>No. Order</th>
              <th>Klien</th>
              <th>Jenis</th>
              <th>Produk</th>
              <th>Total</th>
              <th>Status</th>
              <th>Jatuh Tempo</th>
              <th>Dibuat</th>
              <th style="text-align:center;">Aksi</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach($invoices as $idx => $inv):
            [$bicon,$bcolor,$blabel,$bbg] = invBadge($inv['status']);
            $rowNum    = $offset + $idx + 1;
            $namaKlien = trim(($inv['firstname'] ?? '') . ' ' . ($inv['lastname'] ?? '')) ?: '–';
            $jenis     = $inv['order_type'] ?? null;
          ?>
            <tr>
              <td><?= $rowNum ?></td>
              <td>
                <?php if($inv['order_id']): ?>
                  <a href="/admin/order_detail.php?id=<?= (int)$inv['order_id'] ?>" class="order-no">
                    <?= htmlspecialchars($inv['order_number'] ?? ('#'.$inv['order_id'])) ?>
                  </a>
                <?php else: ?>
                  <span class="order-no" style="color:var(--muted);">–</span>
                <?php endif; ?>
              </td>
              <td>
                <div style="font-weight:600;"><?= htmlspecialchars($namaKlien) ?></div>
                <?php if(!empty($inv['phonenumber'])): ?>
                  <div style="font-size:11px;color:var(--muted);"><?= htmlspecialchars($inv['phonenumber']) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <?php if($jenis === 'wifi'): ?>
                  <span class="jenis-pill wifi"><i class="fa fa-wifi"></i> WiFi</span>
                <?php elseif($jenis === 'hosting'): ?>
                  <span class="jenis-pill hosting"><i class="fa fa-server"></i> Hosting</span>
                <?php else: ?>
                  <span style="color:var(--muted);font-size:12px;">–</span>
                <?php endif; ?>
              </td>
              <td style="font-size:12px;color:var(--muted);max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                <?= htmlspecialchars($inv['product_name'] ?? '–') ?>
              </td>
              <td style="font-weight:700;font-family:'JetBrains Mono',monospace;">
                Rp <?= number_format((float)$inv['total'],0,',','.') ?>
              </td>
              <td>
                <span style="display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:700;color:<?= $bcolor ?>;background:<?= $bbg ?>;padding:3px 10px;border-radius:20px;">
                  <i class="fa <?= $bicon ?>"></i> <?= $blabel ?>
                </span>
              </td>
              <td style="font-size:12px;color:var(--muted);white-space:nowrap;">
                <?= !empty($inv['duedate']) ? date('d M Y', strtotime($inv['duedate'])) : '–' ?>
              </td>
              <td style="font-size:12px;color:var(--muted);white-space:nowrap;">
                <?= date('d M Y', strtotime($inv['created_at'])) ?>
              </td>
              <td style="text-align:center;">
                <?php if($inv['order_id']): ?>
                  <a href="/admin/order_detail.php?id=<?= (int)$inv['order_id'] ?>"
                     class="btn btn-secondary" style="padding:6px 12px;font-size:12px;white-space:nowrap;">
                    <i class="fa fa-eye"></i> Detail
                  </a>
                <?php else: ?>
                  <span style="color:var(--muted);font-size:11px;">–</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php if($totalPages > 1): ?>
      <div class="pagination">
        <?php
        $buildUrl = function($p) use ($filterStatus,$filterSearch,$filterJenis,$filterDateFrom,$filterDateTo) {
            return '/admin/invoices.php?' . http_build_query(array_filter([
                'status'=>$filterStatus,'q'=>$filterSearch,'jenis'=>$filterJenis,
                'from'=>$filterDateFrom,'to'=>$filterDateTo,'page'=>$p,
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
function confirmLogout(e) { e.preventDefault(); document.getElementById('logoutModal').style.display='flex'; }
function closeLogoutModal() { document.getElementById('logoutModal').style.display='none'; }
document.getElementById('logoutModal').addEventListener('click', function(e){ if(e.target===this) closeLogoutModal(); });
document.addEventListener('keydown', e=>{ if(e.key==='Escape') closeLogoutModal(); });
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
