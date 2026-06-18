<?php
// ============================================================
// admin/clients.php – Daftar Klien Perkasa Solusindo
// ============================================================
require_once '../config.php';
require_once '../auth_check.php';
requireLevel([1, 2]);

$adminName = $_SESSION['user_firstname'] ?? 'Admin';

// ── Statistik untuk sidebar badge ────────────────────────────
function countQC($conn, $sql) { $r = $conn->query($sql); return $r ? (int)$r->fetch_row()[0] : 0; }
$stats = [];
$stats['unpaid']  = countQC($conn, "SELECT COUNT(*) FROM tblinvoices WHERE status='Unpaid'");
$stats['tickets'] = countQC($conn, "SELECT COUNT(*) FROM tbltickets WHERE status='Open'");
$totalOrdersPending = countQC($conn, "SELECT COUNT(*) FROM tblorders WHERE wifi_status IN ('pending','verified','scheduled')");

// ── Pastikan kolom & tabel tambahan ada (idempotent) ─────────
$conn->query("ALTER TABLE tblclients ADD COLUMN IF NOT EXISTS notes text DEFAULT NULL AFTER lastupdated");
$conn->query("CREATE TABLE IF NOT EXISTS tblorders (
  id int(11) NOT NULL AUTO_INCREMENT,
  userid int(11) NOT NULL,
  productid int(11) NOT NULL,
  status varchar(30) NOT NULL DEFAULT 'Active',
  note text DEFAULT NULL,
  created_at datetime NOT NULL DEFAULT current_timestamp(),
  updated_at datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (id),
  KEY idx_userid (userid),
  KEY idx_productid (productid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// ── Filter & Search ──────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
$filter = $_GET['status'] ?? 'all'; // all | active | inactive

$sql   = "SELECT * FROM tblclients WHERE level = 3";
$types = '';
$prms  = [];

if ($filter === 'active')   { $sql .= " AND status = 1"; }
if ($filter === 'inactive') { $sql .= " AND status = 0"; }

if ($search) {
    $like   = "%$search%";
    $sql   .= " AND (firstname LIKE ? OR lastname LIKE ? OR email LIKE ? OR companyname LIKE ? OR phonenumber LIKE ?)";
    $types .= 'sssss';
    $prms   = [$like,$like,$like,$like,$like];
}
$sql .= " ORDER BY datecreated DESC";

$clients = [];
$stmt = $conn->prepare($sql);
if ($stmt) {
    if ($types) $stmt->bind_param($types, ...$prms);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $clients[] = $row;
    $stmt->close();
}

// Hitung tiket open per klien
$ticketCounts = [];
$tr = $conn->query("SELECT userid, COUNT(*) AS c FROM tbltickets WHERE status != 'Closed' GROUP BY userid");
if ($tr) { while ($r = $tr->fetch_assoc()) $ticketCounts[$r['userid']] = $r['c']; }

// Hitung invoice unpaid per klien
$invoiceCounts = [];
$ir = $conn->query("SELECT userid, COUNT(*) AS c FROM tblinvoices WHERE status='Unpaid' GROUP BY userid");
if ($ir) { while ($r = $ir->fetch_assoc()) $invoiceCounts[$r['userid']] = $r['c']; }

// Hitung total klien per status
$totalAll      = (int)$conn->query("SELECT COUNT(*) FROM tblclients WHERE level=3")->fetch_row()[0];
$totalActive   = (int)$conn->query("SELECT COUNT(*) FROM tblclients WHERE level=3 AND status=1")->fetch_row()[0];
$totalInactive = $totalAll - $totalActive;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Data Klien – Perkasa Solusindo</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/admin/style_admin.css">
<style>
/* ── Clients-specific styles ─────────────────────────── */
.filter-tabs {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}
.filter-tab {
  padding: 7px 16px;
  border-radius: 8px;
  font-size: 12px;
  font-weight: 600;
  border: 1px solid var(--border);
  background: var(--surface2);
  color: var(--muted);
  text-decoration: none;
  transition: all .2s;
  display: flex;
  align-items: center;
  gap: 6px;
}
.filter-tab:hover { border-color: var(--accent2); color: var(--text); }
.filter-tab.active { background: rgba(59,130,246,.12); border-color: var(--accent2); color: var(--accent2); }
.filter-tab .count-pill {
  background: var(--border);
  border-radius: 20px;
  padding: 0 7px;
  font-size: 10px;
}

.clients-table-wrap {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 14px;
  overflow: hidden;
}
.clients-table-wrap table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}
.clients-table-wrap thead tr {
  background: var(--surface2);
  border-bottom: 1px solid var(--border);
}
.clients-table-wrap th {
  padding: 12px 18px;
  text-align: left;
  font-size: 11px;
  font-weight: 700;
  color: var(--muted);
  letter-spacing: .5px;
  text-transform: uppercase;
  white-space: nowrap;
}
.clients-table-wrap td {
  padding: 14px 18px;
  border-bottom: 1px solid var(--border);
  vertical-align: middle;
}
.clients-table-wrap tr:last-child td { border-bottom: none; }
.clients-table-wrap tbody tr:hover { background: rgba(255,255,255,.02); }

.client-name-cell { display: flex; align-items: center; gap: 10px; }
.client-avatar {
  width: 38px; height: 38px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--accent2), #8b5cf6);
  display: grid; place-items: center;
  font-size: 14px; font-weight: 700; color: #fff;
  flex-shrink: 0;
}
.client-meta { line-height: 1.4; }
.client-fullname { font-weight: 600; font-size: 13px; }
.client-company { font-size: 11px; color: var(--muted); }

.badge-pill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 2px 9px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 600;
}

.search-bar {
  display: flex;
  gap: 8px;
  align-items: center;
  flex: 1;
  max-width: 380px;
}
.search-input {
  flex: 1;
  background: var(--surface2);
  border: 1px solid var(--border);
  color: var(--text);
  padding: 8px 14px;
  border-radius: 8px;
  font-size: 13px;
  font-family: inherit;
  outline: none;
  transition: border-color .2s;
}
.search-input:focus { border-color: var(--accent2); }
.search-input::placeholder { color: var(--muted); }

.toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}

.action-btns { display: flex; gap: 6px; }
</style>
</head>
<body>

<!-- ═══════════ SIDEBAR ═══════════ -->
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
    <a href="/admin/orders.php" class="nav-item has-sub" onclick="toggleSubMenu(event,'subOrders')">
      <i class="fa fa-list-check"></i> Semua Order
      <?php if($totalOrdersPending > 0): ?>
        <span class="nav-badge"><?= $totalOrdersPending ?></span>
      <?php endif; ?>
      <i class="fa fa-chevron-right nav-arrow"></i>
    </a>
    <div class="nav-sub-group" id="subOrders">
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
    <a href="/admin/clients.php" class="nav-item active">
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

<!-- ═══════════ MAIN ═══════════ -->
<main class="main">
  <div class="topbar">
    <div class="page-title"><i class="fa fa-users" style="color:var(--accent2);margin-right:8px;"></i>Data Klien</div>
    <div class="topbar-right">
      <span class="date-badge"><i class="fa fa-calendar-days" style="margin-right:6px;"></i><?= date('d M Y') ?></span>
      <a href="#" class="topbar-btn" onclick="confirmLogout(event)" title="Logout"><i class="fa fa-right-from-bracket"></i></a>
    </div>
  </div>

  <div class="content">

    <!-- Toolbar -->
    <div class="toolbar">
      <div class="filter-tabs">
        <a href="/admin/clients.php" class="filter-tab <?= $filter==='all'?'active':'' ?>">
          Semua <span class="count-pill"><?= $totalAll ?></span>
        </a>
        <a href="/admin/clients.php?status=active" class="filter-tab <?= $filter==='active'?'active':'' ?>">
          <i class="fa fa-circle-check" style="color:#34d399;font-size:10px;"></i> Aktif
          <span class="count-pill"><?= $totalActive ?></span>
        </a>
        <a href="/admin/clients.php?status=inactive" class="filter-tab <?= $filter==='inactive'?'active':'' ?>">
          <i class="fa fa-circle-xmark" style="color:#f87171;font-size:10px;"></i> Nonaktif
          <span class="count-pill"><?= $totalInactive ?></span>
        </a>
      </div>
      <form method="GET" action="" class="search-bar">
        <?php if($filter !== 'all'): ?>
          <input type="hidden" name="status" value="<?= htmlspecialchars($filter) ?>">
        <?php endif; ?>
        <input type="text" name="q" class="search-input" placeholder="Cari nama, email, perusahaan…"
               value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn btn-secondary btn-sm"><i class="fa fa-search"></i></button>
        <?php if($search): ?>
          <a href="/admin/clients.php<?= $filter!=='all'?'?status='.$filter:'' ?>" class="btn btn-secondary btn-sm"><i class="fa fa-xmark"></i></a>
        <?php endif; ?>
      </form>
    </div>

    <!-- Table -->
    <?php if(empty($clients)): ?>
      <div class="clients-table-wrap">
        <div class="empty-state" style="padding:60px 20px;">
          <i class="fa fa-users"></i>
          <p><?= $search ? 'Tidak ditemukan klien dengan kata kunci "'.$search.'".' : 'Belum ada klien terdaftar.' ?></p>
        </div>
      </div>
    <?php else: ?>
    <div class="clients-table-wrap">
      <table>
        <thead>
          <tr>
            <th>Klien</th>
            <th>Kontak</th>
            <th>Kota</th>
            <th>Tiket</th>
            <th>Invoice</th>
            <th>Status</th>
            <th>Terdaftar</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($clients as $c):
            $initials = strtoupper(substr($c['firstname'],0,1).substr($c['lastname'],0,1));
            $openTix  = $ticketCounts[$c['id']] ?? 0;
            $unpaidInv = $invoiceCounts[$c['id']] ?? 0;
          ?>
          <tr>
            <td>
              <div class="client-name-cell">
                <div class="client-avatar"><?= $initials ?></div>
                <div class="client-meta">
                  <div class="client-fullname"><?= htmlspecialchars($c['firstname'].' '.$c['lastname']) ?></div>
                  <?php if($c['companyname']): ?>
                    <div class="client-company"><i class="fa fa-building" style="font-size:10px;margin-right:3px;"></i><?= htmlspecialchars($c['companyname']) ?></div>
                  <?php endif; ?>
                </div>
              </div>
            </td>
            <td>
              <div style="font-size:13px;"><?= htmlspecialchars($c['email']) ?></div>
              <div style="font-size:11px;color:var(--muted);margin-top:2px;"><i class="fa fa-phone" style="font-size:10px;margin-right:3px;"></i><?= htmlspecialchars($c['phonenumber']) ?></div>
            </td>
            <td style="color:var(--muted);font-size:12px;"><?= htmlspecialchars($c['city']) ?></td>
            <td>
              <?php if($openTix > 0): ?>
                <span class="badge badge-yellow"><i class="fa fa-ticket" style="font-size:10px;"></i> <?= $openTix ?> Open</span>
              <?php else: ?>
                <span style="color:var(--muted);font-size:12px;">–</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if($unpaidInv > 0): ?>
                <span class="badge badge-red"><i class="fa fa-file-invoice-dollar" style="font-size:10px;"></i> <?= $unpaidInv ?> Unpaid</span>
              <?php else: ?>
                <span style="color:var(--muted);font-size:12px;">–</span>
              <?php endif; ?>
            </td>
            <td>
              <span class="badge <?= $c['status'] ? 'badge-green' : 'badge-red' ?>">
                <?= $c['status'] ? 'Aktif' : 'Nonaktif' ?>
              </span>
            </td>
            <td style="font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--muted);white-space:nowrap;">
              <?= date('d M Y', strtotime($c['datecreated'])) ?>
            </td>
            <td>
              <a href="/admin/client_detail.php?id=<?= $c['id'] ?>" class="btn btn-blue btn-sm">
                <i class="fa fa-eye"></i> Detail
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div style="margin-top:12px;font-size:12px;color:var(--muted);">
      Menampilkan <?= count($clients) ?> klien<?= $search ? ' untuk "'.$search.'"' : '' ?>.
    </div>
    <?php endif; ?>

  </div>
</main>

<!-- ═══════════ LOGOUT MODAL ═══════════ -->
<div id="logoutModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
  <div style="background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:32px;max-width:380px;width:90%;text-align:center;box-shadow:0 24px 60px rgba(0,0,0,.5);">
    <div style="width:56px;height:56px;border-radius:50%;background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.25);display:grid;place-items:center;margin:0 auto 16px;font-size:22px;color:#f87171;">
      <i class="fa fa-right-from-bracket"></i>
    </div>
    <h3 style="font-size:17px;font-weight:700;margin-bottom:8px;">Konfirmasi Logout</h3>
    <p style="font-size:13px;color:var(--muted);margin-bottom:24px;line-height:1.6;">Anda akan keluar dari sesi admin.<br>Pastikan semua pekerjaan sudah disimpan.</p>
    <div style="display:flex;gap:10px;justify-content:center;">
      <button onclick="closeLogoutModal()" class="btn btn-secondary" style="min-width:110px;">
        <i class="fa fa-xmark"></i> Batal
      </button>
      <a href="/admin/logout.php" class="btn btn-danger" style="min-width:110px;">
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
  if (isOpen) {
    e.preventDefault();
    group.classList.remove('open');
    e.currentTarget.classList.remove('expanded');
  } else {
    group.classList.add('open');
    e.currentTarget.classList.add('expanded');
  }
}
function confirmLogout(e) {
  e.preventDefault();
  const m = document.getElementById('logoutModal');
  m.style.display = 'flex';
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
</script>
</body>
</html>
