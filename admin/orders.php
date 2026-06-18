<?php
// ============================================================
// admin/orders.php – Semua Order Layanan Perkasa Solusindo
// ============================================================
require_once '../config.php';
require_once '../auth_check.php';
requireLevel([1, 2]);

$adminId   = (int)($_SESSION['user_id'] ?? 0);
$adminName = $_SESSION['user_firstname'] ?? 'Admin';

// ── Statistik notifikasi untuk sidebar badge ─────────────────
function countQ($conn, $sql) {
    $r = $conn->query($sql); return $r ? (int)$r->fetch_row()[0] : 0;
}
$stats = [];
$stats['unpaid']  = countQ($conn, "SELECT COUNT(*) FROM tblinvoices WHERE status='Unpaid'");
$stats['tickets'] = countQ($conn, "SELECT COUNT(*) FROM tbltickets WHERE status='Open'");
$totalOrdersPending = countQ($conn, "SELECT COUNT(*) FROM tblorders WHERE wifi_status IN ('pending','verified','scheduled')");

// ── Filter ────────────────────────────────────────────────────
$filterType   = $_GET['type']   ?? '';
$filterStatus = $_GET['status'] ?? '';
$filterSearch = trim($_GET['q'] ?? '');
$page  = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

// ── Build WHERE ───────────────────────────────────────────────
$where  = ['1=1'];
$types  = '';
$params = [];

if ($filterType) {
    $where[] = "o.order_type = ?";
    $types .= 's'; $params[] = $filterType;
}
if ($filterStatus) {
    $where[] = "o.wifi_status = ?";
    $types .= 's'; $params[] = $filterStatus;
}
if ($filterSearch) {
    $like = "%$filterSearch%";
    $where[] = "(c.firstname LIKE ? OR c.lastname LIKE ? OR o.order_number LIKE ? OR p.name LIKE ?)";
    $types .= 'ssss'; $params = array_merge($params, [$like,$like,$like,$like]);
}

$whereStr = implode(' AND ', $where);

// ── Count total ───────────────────────────────────────────────
$stmtCount = $conn->prepare("SELECT COUNT(*) FROM tblorders o JOIN tblclients c ON c.id=o.userid JOIN tblproducts p ON p.id=o.productid WHERE $whereStr");
if ($types) $stmtCount->bind_param($types, ...$params);
$stmtCount->execute();
$totalRows = (int)$stmtCount->get_result()->fetch_row()[0];
$stmtCount->close();
$totalPages = max(1, ceil($totalRows / $perPage));
$offset     = ($page - 1) * $perPage;

// ── Fetch orders ──────────────────────────────────────────────
$orders = [];
$sql = "SELECT o.*,
               CONCAT(c.firstname,' ',c.lastname) AS client_name,
               c.phonenumber, c.email,
               p.name AS product_name, p.category, p.price, p.speed
        FROM tblorders o
        JOIN tblclients c ON c.id = o.userid
        JOIN tblproducts p ON p.id = o.productid
        WHERE $whereStr
        ORDER BY o.created_at DESC
        LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$allTypes  = $types . 'ii';
$allParams = array_merge($params, [$perPage, $offset]);
$stmt->bind_param($allTypes, ...$allParams);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $orders[] = $row;
$stmt->close();

// ── Order type counts untuk tab ───────────────────────────────
$typeCounts = [];
$rc = $conn->query("SELECT order_type, COUNT(*) AS cnt FROM tblorders GROUP BY order_type");
if ($rc) { while ($row = $rc->fetch_assoc()) $typeCounts[$row['order_type']] = $row['cnt']; }
$totalAll = array_sum($typeCounts);

// ── Helpers ───────────────────────────────────────────────────
function orderStatusBadge($status) {
    $map = [
        'pending'   => ['badge-yellow', 'fa-hourglass-half',     'Menunggu'],
        'verified'  => ['badge-blue',   'fa-circle-check',       'Diverifikasi'],
        'scheduled' => ['badge-indigo', 'fa-calendar-check',     'Dijadwalkan'],
        'installed' => ['badge-green',  'fa-screwdriver-wrench', 'Terpasang'],
        'active'    => ['badge-green',  'fa-wifi',               'Aktif'],
        'cancelled' => ['badge-red',    'fa-ban',                'Dibatalkan'],
    ];
    $d = $map[$status] ?? ['badge-gray','fa-circle','–'];
    return $d;
}
function categoryIcon($cat) {
    $map = [
        'wifi'     => ['fa-wifi',         '#3b82f6', 'Provider WiFi'],
        'hosting'  => ['fa-server',       '#10b981', 'Hosting'],
        'website'  => ['fa-code',         '#8b5cf6', 'Website'],
        'komputer' => ['fa-desktop',      '#f59e0b', 'Komputer'],
        'cctv'     => ['fa-video',        '#ef4444', 'CCTV'],
        'other'    => ['fa-box-open',     '#7d8590', 'Lainnya'],
    ];
    return $map[$cat] ?? $map['other'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Semua Order – Perkasa Solusindo Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/admin/style_admin.css">
<style>
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
.filter-bar input:focus,
.filter-bar select:focus { border-color: var(--accent2); }
.filter-bar select { min-width: 160px; }

/* ── Type Tabs ─── */
.type-tabs {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
  margin-bottom: 20px;
}
.type-tab {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 7px 16px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 700;
  text-decoration: none;
  border: 1px solid var(--border);
  color: var(--muted);
  background: var(--surface);
  transition: all .2s;
}
.type-tab:hover { color: var(--text); border-color: var(--accent2); }
.type-tab.active { color: #fff; border-color: transparent; }
.type-tab .cnt {
  background: rgba(255,255,255,.15);
  padding: 1px 7px;
  border-radius: 10px;
  font-size: 11px;
}

/* ── Orders Table ─── */
.orders-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}
.orders-table th {
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
.orders-table td {
  padding: 13px 14px;
  border-bottom: 1px solid var(--border);
  vertical-align: middle;
}
.orders-table tr:last-child td { border-bottom: none; }
.orders-table tbody tr {
  transition: background .15s;
  cursor: pointer;
}
.orders-table tbody tr:hover { background: rgba(255,255,255,.025); }

.order-no {
  font-family: 'JetBrains Mono', monospace;
  font-size: 12px;
  color: var(--accent2);
  font-weight: 600;
}
.service-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 3px 10px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 700;
}
.client-cell strong { font-size: 13px; display: block; }
.client-cell span   { font-size: 11px; color: var(--muted); }

/* ── Pagination ─── */
.pagination {
  display: flex;
  gap: 6px;
  align-items: center;
  justify-content: center;
  margin-top: 24px;
}
.page-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 34px; height: 34px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  text-decoration: none;
  border: 1px solid var(--border);
  color: var(--muted);
  background: var(--surface);
  transition: all .2s;
}
.page-btn:hover { color: var(--text); border-color: var(--accent2); }
.page-btn.active { background: var(--accent2); border-color: var(--accent2); color: #fff; }
.page-btn.disabled { opacity: .3; pointer-events: none; }

/* ── Empty ─── */
.orders-empty {
  text-align: center;
  padding: 60px 20px;
  color: var(--muted);
}
.orders-empty i { font-size: 40px; margin-bottom: 14px; display: block; opacity: .4; }
.orders-empty p { font-size: 14px; }

/* badge-indigo */
.badge-indigo {
  background: rgba(99,102,241,.15);
  color: #818cf8;
  border: 1px solid rgba(99,102,241,.25);
}
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
    <a href="/admin/orders.php" class="nav-item active has-sub expanded" onclick="toggleSubMenu(event,'subOrders')">
      <i class="fa fa-list-check"></i> Semua Order
      <?php if($totalOrdersPending > 0): ?>
        <span class="nav-badge"><?= $totalOrdersPending ?></span>
      <?php endif; ?>
      <i class="fa fa-chevron-right nav-arrow"></i>
    </a>
    <!-- Sub-menu Order -->
    <div class="nav-sub-group open" id="subOrders">
      <?php
      $wifiPendingCount = countQ($conn,
          "SELECT COUNT(*) FROM tblorders o JOIN tblproducts p ON p.id=o.productid
           WHERE (p.category='wifi' OR o.order_type='wifi') AND o.wifi_status IN ('pending','verified','scheduled')"
      );
      ?>
      <a href="/admin/orders_wifi.php" class="nav-item nav-sub">
        <i class="fa fa-wifi"></i> Order Layanan WiFi
        <?php if($wifiPendingCount > 0): ?>
          <span class="nav-badge"><?= $wifiPendingCount ?></span>
        <?php endif; ?>
      </a>
      <?php
      $hostingPendingCount = countQ($conn,
          "SELECT COUNT(*) FROM tblorders o JOIN tblproducts p ON p.id=o.productid
           WHERE (p.category='hosting' OR o.order_type='hosting') AND o.wifi_status IN ('pending','verified')"
      );
      ?>
      <a href="/admin/orders_hosting.php" class="nav-item nav-sub">
        <i class="fa fa-server"></i> Order Hosting
        <?php if($hostingPendingCount > 0): ?>
          <span class="nav-badge"><?= $hostingPendingCount ?></span>
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
  <div class="topbar">
    <div class="page-title">
      <a href="/admin/admin_dashboard.php" style="color:var(--muted);text-decoration:none;font-weight:500;font-size:13px;">Dashboard</a>
      <span style="color:var(--muted);margin:0 6px;">/</span>
      Semua Order
    </div>
    <div class="topbar-right">
      <span class="date-badge"><i class="fa fa-calendar-days" style="margin-right:6px;"></i><?= date('d M Y') ?></span>
      <a href="#" class="topbar-btn" onclick="confirmLogout(event)" title="Logout">
        <i class="fa fa-right-from-bracket"></i>
      </a>
    </div>
  </div>

  <div class="content">

    <!-- Page header -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
      <div>
        <h1 style="font-size:22px;font-weight:800;margin-bottom:4px;">
          <i class="fa fa-list-check" style="color:var(--accent2);margin-right:10px;"></i>Semua Order
        </h1>
        <p style="font-size:13px;color:var(--muted);">Seluruh orderan layanan Perkasa Solusindo dari semua kategori produk.</p>
      </div>
      <div style="font-family:'JetBrains Mono',monospace;font-size:12px;color:var(--muted);background:var(--surface);border:1px solid var(--border);padding:6px 14px;border-radius:8px;">
        Total: <strong style="color:var(--text);"><?= number_format($totalRows) ?></strong> order
      </div>
    </div>

    <!-- Type Tabs -->
    <?php
    $tabDefs = [
        ''         => ['fa-th-large',    '#7d8590', 'Semua',    $totalAll],
        'wifi'     => ['fa-wifi',        '#3b82f6', 'WiFi',     $typeCounts['wifi']     ?? 0],
        'hosting'  => ['fa-server',      '#10b981', 'Hosting',  $typeCounts['hosting']  ?? 0],
        'website'  => ['fa-code',        '#8b5cf6', 'Website',  $typeCounts['website']  ?? 0],
        'komputer' => ['fa-desktop',     '#f59e0b', 'Komputer', $typeCounts['komputer'] ?? 0],
        'cctv'     => ['fa-video',       '#ef4444', 'CCTV',     $typeCounts['cctv']     ?? 0],
        'other'    => ['fa-box-open',    '#7d8590', 'Lainnya',  $typeCounts['other']    ?? 0],
    ];
    ?>
    <div class="type-tabs">
      <?php foreach($tabDefs as $tval => [$ticon,$tcolor,$tlabel,$tcnt]):
        if ($tval !== '' && $tcnt === 0) continue; // Sembunyikan tab kosong kecuali "Semua"
        $isActive = ($filterType === $tval);
        $href = '/admin/orders.php?' . http_build_query(array_filter(['type'=>$tval,'status'=>$filterStatus,'q'=>$filterSearch]));
      ?>
      <a href="<?= $href ?>"
         class="type-tab <?= $isActive ? 'active' : '' ?>"
         style="<?= $isActive ? "background:$tcolor;" : '' ?>">
        <i class="fa <?= $ticon ?>" style="<?= $isActive ? '' : "color:$tcolor;" ?>"></i>
        <?= $tlabel ?>
        <span class="cnt"><?= number_format($tcnt) ?></span>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- Filter Bar -->
    <form method="GET" action="/admin/orders.php" class="filter-bar">
      <?php if($filterType): ?>
        <input type="hidden" name="type" value="<?= htmlspecialchars($filterType) ?>">
      <?php endif; ?>
      <i class="fa fa-magnifying-glass" style="color:var(--muted);"></i>
      <input type="text" name="q" placeholder="Cari nama klien, nomor order, produk…"
             value="<?= htmlspecialchars($filterSearch) ?>">
      <select name="status">
        <option value="">Semua Status</option>
        <?php
        $statusOpts = [
            'pending'   => 'Menunggu Verifikasi',
            'verified'  => 'Diverifikasi',
            'scheduled' => 'Dijadwalkan',
            'installed' => 'Terpasang',
            'active'    => 'Aktif',
            'cancelled' => 'Dibatalkan',
        ];
        foreach ($statusOpts as $sv => $sl):
        ?>
          <option value="<?= $sv ?>" <?= $filterStatus === $sv ? 'selected' : '' ?>><?= $sl ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-primary" style="padding:8px 18px;">
        <i class="fa fa-filter"></i> Filter
      </button>
      <?php if($filterType || $filterStatus || $filterSearch): ?>
        <a href="/admin/orders.php" class="btn btn-secondary" style="padding:8px 14px;">
          <i class="fa fa-xmark"></i> Reset
        </a>
      <?php endif; ?>
    </form>

    <!-- Table Card -->
    <div class="card" style="padding:0;overflow:hidden;">
      <?php if(empty($orders)): ?>
        <div class="orders-empty">
          <i class="fa fa-inbox"></i>
          <p>Tidak ada order yang ditemukan<?= ($filterSearch || $filterType || $filterStatus) ? ' untuk filter ini.' : '.' ?></p>
        </div>
      <?php else: ?>
      <div style="overflow-x:auto;">
        <table class="orders-table">
          <thead>
            <tr>
              <th>No. Order</th>
              <th>Layanan</th>
              <th>Klien</th>
              <th>Produk</th>
              <th>Harga</th>
              <th>Status</th>
              <th>Tanggal</th>
              <th style="text-align:center;">Aksi</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach($orders as $ord):
            $badge    = orderStatusBadge($ord['wifi_status']);
            $catInfo  = categoryIcon($ord['order_type'] ?: $ord['category'] ?? 'other');
          ?>
            <tr style="cursor:pointer;transition:background .15s;" onclick="window.location='/admin/order_detail.php?id=<?= $ord['id'] ?>'" onmouseover="this.style.background='rgba(59,130,246,.04)'" onmouseout="this.style.background=''">
              <td>
                <div class="order-no"><?= htmlspecialchars($ord['order_number'] ?? '#'.$ord['id']) ?></div>
                <div style="font-size:10px;color:var(--muted);margin-top:2px;">ID #<?= $ord['id'] ?></div>
              </td>
              <td>
                <span class="service-pill" style="background:<?= $catInfo[1] ?>18;color:<?= $catInfo[1] ?>;border:1px solid <?= $catInfo[1] ?>33;">
                  <i class="fa <?= $catInfo[0] ?>"></i> <?= $catInfo[2] ?>
                </span>
              </td>
              <td class="client-cell">
                <strong><?= htmlspecialchars($ord['client_name']) ?></strong>
                <span><?= htmlspecialchars($ord['email']) ?></span>
              </td>
              <td>
                <div style="font-size:13px;font-weight:600;"><?= htmlspecialchars($ord['product_name']) ?></div>
                <?php if($ord['speed']): ?>
                  <span class="badge badge-blue" style="font-size:10px;"><?= $ord['speed'] ?></span>
                <?php endif; ?>
              </td>
              <td style="font-family:'JetBrains Mono',monospace;font-size:12px;white-space:nowrap;">
                Rp <?= number_format($ord['price'],0,',','.') ?>
              </td>
              <td>
                <span class="badge <?= $badge[0] ?>">
                  <i class="fa <?= $badge[1] ?>" style="margin-right:4px;font-size:10px;"></i>
                  <?= $badge[2] ?>
                </span>
              </td>
              <td style="font-size:12px;color:var(--muted);white-space:nowrap;font-family:'JetBrains Mono',monospace;">
                <?= date('d M Y', strtotime($ord['created_at'])) ?>
                <div style="font-size:10px;"><?= date('H:i', strtotime($ord['created_at'])) ?></div>
              </td>
              <td style="text-align:center;">
                <a href="/admin/order_detail.php?id=<?= $ord['id'] ?>"
                   onclick="event.stopPropagation();"
                   class="btn btn-secondary"
                   style="padding:6px 12px;font-size:12px;">
                  <i class="fa fa-eye"></i> Detail
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if($totalPages > 1): ?>
      <div class="pagination" style="padding: 16px 0;">
        <?php
        $buildUrl = function($p) use ($filterType,$filterStatus,$filterSearch) {
            return '/admin/orders.php?' . http_build_query(array_filter(['type'=>$filterType,'status'=>$filterStatus,'q'=>$filterSearch,'page'=>$p]));
        };
        ?>
        <a href="<?= $buildUrl($page-1) ?>" class="page-btn <?= $page<=1 ? 'disabled' : '' ?>">
          <i class="fa fa-chevron-left"></i>
        </a>
        <?php for($p=max(1,$page-2); $p<=min($totalPages,$page+2); $p++): ?>
          <a href="<?= $buildUrl($p) ?>" class="page-btn <?= $p===$page ? 'active' : '' ?>"><?= $p ?></a>
        <?php endfor; ?>
        <a href="<?= $buildUrl($page+1) ?>" class="page-btn <?= $page>=$totalPages ? 'disabled' : '' ?>">
          <i class="fa fa-chevron-right"></i>
        </a>
        <span style="font-size:12px;color:var(--muted);margin-left:6px;">Hal <?= $page ?> / <?= $totalPages ?></span>
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
    <p style="font-size:13px;color:var(--muted);margin-bottom:26px;line-height:1.7;">Anda akan keluar dari sesi admin panel.<br>Pastikan semua pekerjaan sudah tersimpan.</p>
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
document.getElementById('logoutModal').addEventListener('click',function(e){ if(e.target===this) closeLogoutModal(); });
document.addEventListener('keydown',e=>{ if(e.key==='Escape') closeLogoutModal(); });

// ── Sub-menu toggle ───────────────────────────────────────────
function toggleSubMenu(e, groupId) {
  // Hanya toggle jika klik pada item parent (bukan navigasi ke URL)
  const group = document.getElementById(groupId);
  if (!group) return;
  const isOpen = group.classList.contains('open');
  // Jika sudah open & klik = collapse; jika tertutup = expand
  if (isOpen) {
    e.preventDefault();
    group.classList.remove('open');
    e.currentTarget.classList.remove('expanded');
  } else {
    group.classList.add('open');
    e.currentTarget.classList.add('expanded');
    // Biarkan navigasi berjalan ke orders.php
  }
}
</script>
</body>
</html>
