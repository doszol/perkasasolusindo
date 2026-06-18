<?php
// ============================================================
// admin/products.php – CRUD Produk Layanan Perkasa Solusindo
// ============================================================
// session_start() dihandle oleh auth_check.php
require_once '../config.php';
require_once '../auth_check.php';
requireLevel([1, 2]); // Hanya Owner & Admin

$adminName = $_SESSION['user_firstname'] ?? 'Admin';

// ── Statistik untuk sidebar badge ────────────────────────────
function cntProd($conn, $sql) { $r=$conn->query($sql); return $r?(int)$r->fetch_row()[0]:0; }
$sideStats = [];
$sideStats['unpaid']  = cntProd($conn, "SELECT COUNT(*) FROM tblinvoices WHERE status='Unpaid'");
$sideStats['tickets'] = cntProd($conn, "SELECT COUNT(*) FROM tbltickets WHERE status='Open'");
$totalOrdersPending   = cntProd($conn, "SELECT COUNT(*) FROM tblorders WHERE wifi_status IN ('pending','verified','scheduled')");

$msg = $err = '';

// ── Kategori Layanan (untuk dropdown & badge warna) ──────────
$categories = [
  'wifi'     => ['label' => 'Provider WiFi Internet', 'icon' => 'fa-wifi',        'color' => '#3b82f6'],
  'hosting'  => ['label' => 'Sewa Hosting',           'icon' => 'fa-server',      'color' => '#10b981'],
  'website'  => ['label' => 'Pembuatan Website',      'icon' => 'fa-code',        'color' => '#8b5cf6'],
  'komputer' => ['label' => 'Jual & Pasang Komputer', 'icon' => 'fa-desktop',     'color' => '#f59e0b'],
  'cctv'     => ['label' => 'Pemasangan CCTV',        'icon' => 'fa-camera',      'color' => '#ef4444'],
  'other'    => ['label' => 'Lainnya',                'icon' => 'fa-box-open',    'color' => '#7d8590'],
];

// Nilai "ready" (sudah siap jual) — hanya wifi
// Kolom `ready_to_sell` ditambahkan lewat ALTER TABLE di bawah.
// Jika kolom belum ada, handler akan fallback gracefully.

// ── Pastikan kolom tambahan ada (silent, jalankan sekali) ────
$conn->query("ALTER TABLE tblproducts
    ADD COLUMN IF NOT EXISTS category      varchar(50)  NOT NULL DEFAULT 'other' AFTER status,
    ADD COLUMN IF NOT EXISTS speed         varchar(50)  DEFAULT NULL              AFTER category,
    ADD COLUMN IF NOT EXISTS period        varchar(20)  NOT NULL DEFAULT 'bulan'  AFTER speed,
    ADD COLUMN IF NOT EXISTS ready_to_sell tinyint(1)   NOT NULL DEFAULT 0        AFTER period
");

// ── Helper: mysqli prepared statement ────────────────────────
function mqExec($conn, $sql, $types, ...$values) {
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;
    if ($types) $stmt->bind_param($types, ...$values);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

// ── Helper: tulis audit log ───────────────────────────────────
// action_type : 'tambah' | 'edit' | 'hapus' | 'aktifkan' | 'nonaktifkan'
function writeLog($conn, $productId, $productName, $actionType) {
    $adminId   = (int)($_SESSION['user_id'] ?? 0);
    $adminName = trim(($_SESSION['user_firstname'] ?? '') . ' ' . ($_SESSION['user_lastname'] ?? ''));
    mqExec($conn,
        "INSERT INTO tbl_product_logs (product_id, product_name, action_type, admin_id, admin_name)
         VALUES (?,?,?,?,?)",
        'issis',
        $productId, $productName, $actionType, $adminId, $adminName
    );
}

// ── Handler POST ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  // ---------- TAMBAH ----------
  if ($action === 'add') {
    $name        = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price       = (float)($_POST['price'] ?? 0);
    $status      = (int)($_POST['status'] ?? 1);
    $category    = $_POST['category'] ?? 'other';
    $speed       = trim($_POST['speed'] ?? '');
    $period      = trim($_POST['period'] ?? 'bulan');
    $ready       = isset($_POST['ready_to_sell']) ? 1 : 0;

    if (!$name) {
      $err = 'Nama produk tidak boleh kosong.';
    } else {
      $ok = mqExec($conn,
        "INSERT INTO tblproducts (name, description, price, status, category, speed, period, ready_to_sell)
         VALUES (?,?,?,?,?,?,?,?)",
        'ssdisssi',
        $name, $description, $price, $status, $category, $speed, $period, $ready
      );
      if ($ok) {
        writeLog($conn, (int)$conn->insert_id, $name, 'tambah');
        $msg = 'Produk berhasil ditambahkan!';
      } else {
        $err = 'Gagal menyimpan: ' . $conn->error;
      }
    }
  }

  // ---------- EDIT ----------
  elseif ($action === 'edit') {
    $id          = (int)($_POST['id'] ?? 0);
    $name        = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price       = (float)($_POST['price'] ?? 0);
    $status      = (int)($_POST['status'] ?? 1);
    $category    = $_POST['category'] ?? 'other';
    $speed       = trim($_POST['speed'] ?? '');
    $period      = trim($_POST['period'] ?? 'bulan');
    $ready       = isset($_POST['ready_to_sell']) ? 1 : 0;

    if (!$id || !$name) {
      $err = 'Data tidak lengkap.';
    } else {
      $ok = mqExec($conn,
        "UPDATE tblproducts SET name=?, description=?, price=?, status=?,
         category=?, speed=?, period=?, ready_to_sell=? WHERE id=?",
        'ssdisssii',
        $name, $description, $price, $status, $category, $speed, $period, $ready, $id
      );
      if ($ok) {
        writeLog($conn, $id, $name, 'edit');
        $msg = 'Produk berhasil diperbarui!';
      } else {
        $err = 'Gagal update: ' . $conn->error;
      }
    }
  }

  // ---------- HAPUS ----------
  elseif ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
      // Ambil nama produk sebelum dihapus untuk log
      $sn = $conn->prepare("SELECT name FROM tblproducts WHERE id = ? LIMIT 1");
      $sn->bind_param('i', $id); $sn->execute();
      $deletedName = $sn->get_result()->fetch_row()[0] ?? "ID#$id";
      $sn->close();

      mqExec($conn, "DELETE FROM tblproducts WHERE id = ?", 'i', $id);
      writeLog($conn, $id, $deletedName, 'hapus');
      $msg = 'Produk berhasil dihapus.';
    }
  }

  // ---------- TOGGLE STATUS ----------
  elseif ($action === 'toggle') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
      // Baca status saat ini untuk tahu arah toggle-nya
      $sn = $conn->prepare("SELECT name, status FROM tblproducts WHERE id = ? LIMIT 1");
      $sn->bind_param('i', $id); $sn->execute();
      $row = $sn->get_result()->fetch_assoc(); $sn->close();

      mqExec($conn, "UPDATE tblproducts SET status = 1 - status WHERE id = ?", 'i', $id);

      if ($row) {
        $newAction = $row['status'] ? 'nonaktifkan' : 'aktifkan';
        writeLog($conn, $id, $row['name'], $newAction);
      }
    }
    header('Location: /admin/products.php');
    exit;
  }
}

// ── Ambil semua produk ───────────────────────────────────────
$filter  = $_GET['cat'] ?? 'all';
$search  = trim($_GET['q'] ?? '');

$sql     = "SELECT * FROM tblproducts WHERE 1";
$types   = '';
$params  = [];

if ($filter !== 'all') {
  $sql    .= " AND category = ?";
  $types  .= 's';
  $params[] = $filter;
}
if ($search) {
  $like     = "%$search%";
  $sql     .= " AND (name LIKE ? OR description LIKE ?)";
  $types   .= 'ss';
  $params[] = $like;
  $params[] = $like;
}
$sql .= " ORDER BY ready_to_sell DESC, status DESC, created_at DESC";

$products = [];
$stmt = $conn->prepare($sql);
if ($stmt) {
  if ($types) $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) $products[] = $row;
  $stmt->close();
}

// ── Ambil 15 log CRUD terbaru ────────────────────────────────
$logs = [];
$logRes = $conn->query(
    "SELECT action_type, admin_name, product_name, created_at
     FROM tbl_product_logs
     ORDER BY created_at DESC LIMIT 15"
);
if ($logRes) { while ($row = $logRes->fetch_assoc()) $logs[] = $row; }

// Produk yang mau diedit (dari GET ?edit=ID)
$editing = null;
if (isset($_GET['edit'])) {
  $editId = (int)$_GET['edit'];
  $stmt   = $conn->prepare("SELECT * FROM tblproducts WHERE id = ? LIMIT 1");
  if ($stmt) {
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $editing = $stmt->get_result()->fetch_assoc();
    $stmt->close();
  }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Produk Layanan – Perkasa Solusindo Admin</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/admin/style_admin.css">
</head>
<body>

<!-- SIDEBAR -->
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
      <?php if($sideStats['unpaid'] > 0): ?>
        <span class="nav-badge"><?= $sideStats['unpaid'] ?></span>
      <?php endif; ?>
    </a>

    <div class="nav-label">Manajemen</div>
    <a href="/admin/products.php" class="nav-item active">
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
      <?php if($sideStats['tickets'] > 0): ?>
        <span class="nav-badge"><?= $sideStats['tickets'] ?></span>
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
      <a href="#" class="btn-logout" onclick="confirmLogout(event)" title="Logout"><i class="fa fa-right-from-bracket"></i></a>
    </div>
  </div>
</aside>

<!-- MAIN -->
<main class="main">
  <div class="topbar">
    <div class="page-title"><i class="fa fa-box-open" style="color:var(--accent);margin-right:10px;"></i>Produk Layanan</div>
    <div class="topbar-right">
      <span class="date-badge"><i class="fa fa-calendar-days" style="margin-right:6px;"></i><?= date('d M Y') ?></span>
      <a href="/admin/admin_dashboard.php" class="topbar-btn" title="Dashboard"><i class="fa fa-gauge-high"></i></a>
      <a href="/admin/products.php" class="topbar-btn" title="Refresh"><i class="fa fa-rotate-right"></i></a>
      <a href="#" class="topbar-btn" onclick="confirmLogout(event)" title="Logout"><i class="fa fa-right-from-bracket"></i></a>
    </div>
  </div>

  <div class="content">

    <?php if($msg): ?>
      <div class="alert alert-success"><i class="fa fa-circle-check"></i> <?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
    <?php if($err): ?>
      <div class="alert alert-danger"><i class="fa fa-triangle-exclamation"></i> <?= htmlspecialchars($err) ?></div>
    <?php endif; ?>

    <!-- Filter -->
    <form method="GET" action="/admin/products.php">
      <div class="filter-bar">
        <a href="/admin/products.php" class="filter-btn <?= $filter==='all'?'active':'' ?>">Semua</a>
        <?php foreach($categories as $key=>$cat): ?>
          <a href="/admin/products.php?cat=<?= $key ?>" class="filter-btn <?= $filter===$key?'active':'' ?>">
            <i class="fa <?= $cat['icon'] ?>"></i> <?= $cat['label'] ?>
          </a>
        <?php endforeach; ?>
        <div class="search-wrap">
          <i class="fa fa-search search-icon"></i>
          <input type="text" name="q" class="search-input" placeholder="Cari produk…" value="<?= htmlspecialchars($search) ?>" onchange="this.form.submit()">
        </div>
      </div>
    </form>

    <div class="page-layout">

      <!-- ── PRODUCT LIST ── -->
      <div>
        <?php if(empty($products)): ?>
          <div class="empty-state">
            <i class="fa fa-box-open"></i>
            <p>Belum ada produk<?= $filter!=='all' ? ' di kategori ini' : '' ?>. Tambahkan di form sebelah kanan.</p>
          </div>
        <?php else: ?>
        <div class="products-grid">
        <?php foreach($products as $p):
          $cat  = $categories[$p['category'] ?? 'other'] ?? $categories['other'];
          $col  = $cat['color'];
          $ready = (int)($p['ready_to_sell'] ?? 0);
        ?>
          <div class="product-card <?= !$p['status'] ? 'inactive' : '' ?>">

            <?php if($ready): ?>
              <div class="ready-star" title="Siap Jual"><i class="fa fa-star"></i> Siap Jual</div>
            <?php endif; ?>

            <div class="product-card-top">
              <div class="cat-icon" style="background:<?= $col ?>22;color:<?= $col ?>;">
                <i class="fa <?= $cat['icon'] ?>"></i>
              </div>
              <div>
                <div class="product-title"><?= htmlspecialchars($p['name']) ?></div>
                <div class="product-cat"><?= $cat['label'] ?></div>
              </div>
            </div>

            <?php if(!empty($p['speed'])): ?>
              <div class="product-speed"><i class="fa fa-bolt"></i> <?= htmlspecialchars($p['speed']) ?></div>
            <?php endif; ?>

            <div class="product-desc">
              <?= nl2br(htmlspecialchars(mb_strimwidth($p['description'] ?? '', 0, 120, '…'))) ?>
            </div>

            <div class="product-price">
              Rp <?= number_format($p['price'],0,',','.') ?>
              <span class="period">/ <?= htmlspecialchars($p['period'] ?? 'bulan') ?></span>
            </div>

            <div class="product-badges">
              <?php if($p['status']): ?>
                <span class="badge badge-green"><i class="fa fa-circle-check"></i> Aktif</span>
              <?php else: ?>
                <span class="badge badge-gray">Nonaktif</span>
              <?php endif; ?>
              <?php if($ready): ?>
                <span class="badge badge-yellow"><i class="fa fa-star"></i> Siap Jual</span>
              <?php else: ?>
                <span class="badge badge-blue">Proses Pematangan</span>
              <?php endif; ?>
            </div>

            <div class="product-actions">
              <a href="/admin/products.php?edit=<?= $p['id'] ?>" class="btn btn-secondary btn-sm">
                <i class="fa fa-pen"></i> Edit
              </a>
              <!-- Toggle status -->
              <form method="POST" style="display:inline;">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                <button type="submit" class="btn btn-sm <?= $p['status'] ? 'btn-secondary' : 'btn-primary' ?>">
                  <i class="fa fa-power-off"></i> <?= $p['status'] ? 'Nonaktifkan' : 'Aktifkan' ?>
                </button>
              </form>
              <!-- Hapus -->
              <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus produk ini?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm">
                  <i class="fa fa-trash"></i>
                </button>
              </form>
            </div>

          </div>
        <?php endforeach; ?>
        </div><!-- /products-grid -->
        <?php endif; ?>
      </div>

      <!-- ── FORM TAMBAH / EDIT ── -->
      <div class="form-card">
        <div class="form-card-header">
          <span class="form-card-title">
            <i class="fa <?= $editing ? 'fa-pen' : 'fa-plus-circle' ?>" style="color:var(--accent);margin-right:6px;"></i>
            <?= $editing ? 'Edit Produk' : 'Tambah Produk Baru' ?>
          </span>
          <?php if($editing): ?>
            <a href="/admin/products.php" class="btn btn-sm btn-secondary"><i class="fa fa-xmark"></i> Batal</a>
          <?php endif; ?>
        </div>
        <div class="form-body">
          <form method="POST" action="/admin/products.php">
            <input type="hidden" name="action" value="<?= $editing ? 'edit' : 'add' ?>">
            <?php if($editing): ?>
              <input type="hidden" name="id" value="<?= $editing['id'] ?>">
            <?php endif; ?>

            <div class="form-group">
              <label>Nama Produk *</label>
              <input type="text" name="name" class="form-control" required
                     placeholder="Cth: Nextstar Home 15 Mbps"
                     value="<?= htmlspecialchars($editing['name'] ?? '') ?>">
            </div>

            <div class="form-group">
              <label>Kategori Layanan *</label>
              <select name="category" class="form-control" id="catSelect" onchange="toggleSpeedField()">
                <?php foreach($categories as $k=>$c): ?>
                  <option value="<?= $k ?>" <?= ($editing['category'] ?? 'other')===$k?'selected':'' ?>>
                    <?= $c['label'] ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group" id="speedGroup" style="display:none;">
              <label>Kecepatan Internet</label>
              <input type="text" name="speed" class="form-control"
                     placeholder="Cth: 15 Mbps, Up To 30 Mbps"
                     value="<?= htmlspecialchars($editing['speed'] ?? '') ?>">
            </div>

            <div class="form-group">
              <label>Deskripsi</label>
              <textarea name="description" class="form-control"
                        placeholder="Keterangan singkat produk/keunggulan layanan…"><?= htmlspecialchars($editing['description'] ?? '') ?></textarea>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label>Harga (Rp) *</label>
                <input type="number" name="price" class="form-control" min="0" step="1000" required
                       placeholder="185000"
                       value="<?= $editing['price'] ?? '' ?>">
              </div>
              <div class="form-group">
                <label>Periode</label>
                <select name="period" class="form-control">
                  <?php foreach(['bulan'=>'Per Bulan','tahun'=>'Per Tahun','unit'=>'Per Unit','project'=>'Per Proyek'] as $v=>$l): ?>
                    <option value="<?= $v ?>" <?= ($editing['period'] ?? 'bulan')===$v?'selected':'' ?>><?= $l ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="form-group">
              <label>Status & Visibilitas</label>
              <div style="display:flex;flex-direction:column;gap:12px;padding:14px;background:var(--surface2);border-radius:8px;border:1px solid var(--border);">
                <div class="toggle-wrap">
                  <label class="toggle">
                    <input type="checkbox" name="status" value="1" <?= ($editing['status'] ?? 1) ? 'checked' : '' ?>>
                    <span class="toggle-slider"></span>
                  </label>
                  <span class="toggle-label">Produk Aktif (tampil di website)</span>
                </div>
                <div class="toggle-wrap">
                  <label class="toggle">
                    <input type="checkbox" name="ready_to_sell" value="1" <?= ($editing['ready_to_sell'] ?? 0) ? 'checked' : '' ?>>
                    <span class="toggle-slider"></span>
                  </label>
                  <span class="toggle-label"><i class="fa fa-star" style="color:var(--accent);"></i> Siap Jual (bisa dipesan klien)</span>
                </div>
              </div>
            </div>

            <button type="submit" class="btn btn-primary btn-full">
              <i class="fa <?= $editing ? 'fa-floppy-disk' : 'fa-plus' ?>"></i>
              <?= $editing ? 'Simpan Perubahan' : 'Tambah Produk' ?>
            </button>

          </form>
        </div>
      </div>

    </div><!-- /page-layout -->

    <!-- ── ACTIVITY LOG ── -->
    <?php if (!empty($logs)): ?>
    <div class="log-section">
      <div class="log-header">
        <span class="log-title"><i class="fa fa-clock-rotate-left"></i> Riwayat Aktivitas Produk</span>
        <span class="log-subtitle">15 aksi terakhir</span>
      </div>
      <div class="log-table-wrap">
        <table>
          <thead>
            <tr>
              <th>Waktu</th>
              <th>Admin</th>
              <th>Aksi</th>
              <th>Produk</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach($logs as $log):
            $actionMeta = [
              'tambah'      => ['label'=>'Tambah',      'badge'=>'badge-green',  'icon'=>'fa-plus'],
              'edit'        => ['label'=>'Edit',         'badge'=>'badge-blue',   'icon'=>'fa-pen'],
              'hapus'       => ['label'=>'Hapus',        'badge'=>'badge-red',    'icon'=>'fa-trash'],
              'aktifkan'    => ['label'=>'Aktifkan',     'badge'=>'badge-green',  'icon'=>'fa-circle-check'],
              'nonaktifkan' => ['label'=>'Nonaktifkan',  'badge'=>'badge-gray',   'icon'=>'fa-circle-xmark'],
            ];
            $meta = $actionMeta[$log['action_type']] ?? ['label'=>$log['action_type'],'badge'=>'badge-gray','icon'=>'fa-question'];
          ?>
            <tr>
              <td style="font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--muted);white-space:nowrap;">
                <?= date('d M Y H:i', strtotime($log['created_at'])) ?>
              </td>
              <td>
                <div class="log-admin">
                  <div class="log-avatar"><?= strtoupper(substr($log['admin_name'],0,1)) ?></div>
                  <?= htmlspecialchars($log['admin_name']) ?>
                </div>
              </td>
              <td>
                <span class="badge <?= $meta['badge'] ?>">
                  <i class="fa <?= $meta['icon'] ?>"></i>&nbsp;<?= $meta['label'] ?>
                </span>
              </td>
              <td style="font-size:13px;"><?= htmlspecialchars($log['product_name']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

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
function toggleSubMenu(e, groupId) {
  const group = document.getElementById(groupId);
  if (!group) return;
  const isOpen = group.classList.contains('open');
  if (isOpen) { e.preventDefault(); group.classList.remove('open'); e.currentTarget.classList.remove('expanded'); }
  else { group.classList.add('open'); e.currentTarget.classList.add('expanded'); }
}
function confirmLogout(e) { e.preventDefault(); document.getElementById('logoutModal').style.display='flex'; }
function closeLogoutModal() { document.getElementById('logoutModal').style.display='none'; }
document.getElementById('logoutModal').addEventListener('click',function(e){ if(e.target===this) closeLogoutModal(); });
document.addEventListener('keydown',e=>{ if(e.key==='Escape') closeLogoutModal(); });

function toggleSpeedField() {
  const cat = document.getElementById('catSelect').value;
  document.getElementById('speedGroup').style.display = cat === 'wifi' ? 'block' : 'none';
}
// Run on load
toggleSpeedField();

// Auto-submit search on Enter
document.querySelector('.search-input')?.addEventListener('keydown', e => {
  if (e.key === 'Enter') e.target.closest('form').submit();
});
</script>

</body>
</html>
