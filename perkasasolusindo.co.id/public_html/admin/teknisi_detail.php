<?php
// ============================================================
// admin/teknisi_detail.php – Detail Teknisi Perkasa Solusindo
// ============================================================
require_once '../config.php';
require_once '../auth_check.php';
requireLevel([1, 2]);

$adminId   = (int)($_SESSION['user_id'] ?? 0);
$adminName = $_SESSION['user_firstname'] ?? 'Admin';

// ── Sidebar badges ────────────────────────────────────────────
function cntTD($conn, $sql) { $r=$conn->query($sql); return $r?(int)$r->fetch_row()[0]:0; }
$sideStats = [];
$sideStats['unpaid']  = cntTD($conn, "SELECT COUNT(*) FROM tblinvoices WHERE status='Unpaid'");
$sideStats['tickets'] = cntTD($conn, "SELECT COUNT(*) FROM tbltickets WHERE status='Open'");
$totalOrdersPending   = cntTD($conn, "SELECT COUNT(*) FROM tblorders WHERE wifi_status IN ('pending','verified','scheduled')");

// ── Ambil data teknisi ────────────────────────────────────────
$tekId = (int)($_GET['id'] ?? 0);
if (!$tekId) { header('Location: /admin/teknisi.php'); exit; }

// Pastikan kolom foto_ktp ada (sesuai nama kolom di DB)
$conn->query("ALTER TABLE tblclients ADD COLUMN IF NOT EXISTS foto_ktp varchar(255) DEFAULT NULL");

$stmt = $conn->prepare("SELECT * FROM tblclients WHERE id=? AND level=4 LIMIT 1");
$stmt->bind_param('i', $tekId); $stmt->execute();
$tek = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$tek) { header('Location: /admin/teknisi.php'); exit; }

// ── POST handlers ─────────────────────────────────────────────
$msg = $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_status') {
        $conn->query("UPDATE tblclients SET status = 1-status WHERE id=$tekId AND level=4");
        header("Location: /admin/teknisi_detail.php?id=$tekId&msg=status_updated"); exit;
    }

    if ($action === 'save_info') {
        $firstname    = trim($_POST['firstname'] ?? '');
        $lastname     = trim($_POST['lastname'] ?? '');
        $phone        = trim($_POST['phonenumber'] ?? '');
        $address      = trim($_POST['address1'] ?? '');
        $city         = trim($_POST['city'] ?? '');
        $state        = trim($_POST['state'] ?? '');
        $postcode     = trim($_POST['postcode'] ?? '');
        $nik          = trim($_POST['nik'] ?? '');
        $tempat_lahir = trim($_POST['tempat_lahir'] ?? '');
        $tgl_lahir    = trim($_POST['tanggal_lahir'] ?? '') ?: null;
        $jenis_kel    = in_array($_POST['jenis_kelamin']??'',['L','P']) ? $_POST['jenis_kelamin'] : null;
        $notes        = trim($_POST['notes'] ?? '');

        if (!$firstname) { $err = 'Nama depan tidak boleh kosong.'; }
        else {
            $upd = $conn->prepare(
                "UPDATE tblclients SET
                 firstname=?,lastname=?,phonenumber=?,address1=?,city=?,state=?,postcode=?,
                 nik=?,tempat_lahir=?,tanggal_lahir=?,jenis_kelamin=?,notes=?
                 WHERE id=? AND level=4"
            );
            $upd->bind_param(
                'ssssssssssssi',
                $firstname,$lastname,$phone,$address,$city,$state,$postcode,
                $nik,$tempat_lahir,$tgl_lahir,$jenis_kel,$notes,$tekId
            );
            $upd->execute(); $upd->close();
            header("Location: /admin/teknisi_detail.php?id=$tekId&msg=info_saved"); exit;
        }
    }

    if ($action === 'reset_password') {
        $newPass = trim($_POST['new_password'] ?? '');
        if (strlen($newPass) < 8) {
            $err = 'Password baru minimal 8 karakter.';
        } else {
            $hash = password_hash($newPass, PASSWORD_BCRYPT);
            $conn->prepare("UPDATE tblclients SET password=? WHERE id=? AND level=4")
                 ->execute() || null;
            $upd = $conn->prepare("UPDATE tblclients SET password=? WHERE id=? AND level=4");
            $upd->bind_param('si', $hash, $tekId);
            $upd->execute(); $upd->close();
            header("Location: /admin/teknisi_detail.php?id=$tekId&msg=password_reset"); exit;
        }
    }

    if ($action === 'upload_ktp') {
        if (empty($_FILES['ktp_file']['name'])) {
            $err = 'Pilih file KTP terlebih dahulu.';
        } else {
            $allowed = ['image/jpeg','image/png','image/webp','application/pdf'];
            $ftype   = mime_content_type($_FILES['ktp_file']['tmp_name']);
            $fsize   = $_FILES['ktp_file']['size'];
            if (!in_array($ftype, $allowed)) {
                $err = 'Format tidak didukung. Gunakan JPG, PNG, WEBP, atau PDF.';
            } elseif ($fsize > 5 * 1024 * 1024) {
                $err = 'Ukuran file maksimal 5 MB.';
            } else {
                // Hapus file lama jika ada
                if (!empty($tek['foto_ktp'])) {
                    $oldPath = $_SERVER['DOCUMENT_ROOT'] . '/admin/asset/ktp_staff/' . $tek['foto_ktp'];
                    if (file_exists($oldPath)) @unlink($oldPath);
                }
                $ext     = pathinfo($_FILES['ktp_file']['name'], PATHINFO_EXTENSION);
                $ktpName = 'teknisi_' . $tekId . '_' . time() . '.' . strtolower($ext);
                $ktpDir  = $_SERVER['DOCUMENT_ROOT'] . '/admin/asset/ktp_staff/';
                if (!is_dir($ktpDir)) mkdir($ktpDir, 0755, true);
                if (move_uploaded_file($_FILES['ktp_file']['tmp_name'], $ktpDir . $ktpName)) {
                    $upd = $conn->prepare("UPDATE tblclients SET foto_ktp=? WHERE id=? AND level=4");
                    $upd->bind_param('si', $ktpName, $tekId);
                    $upd->execute(); $upd->close();
                    header("Location: /admin/teknisi_detail.php?id=$tekId&msg=ktp_uploaded"); exit;
                } else {
                    $err = 'Gagal memindahkan file. Periksa permission direktori.';
                }
            }
        }
    }
}

if (isset($_GET['msg'])) {
    $msgMap = [
        'info_saved'     => '<i class="fa fa-circle-check"></i> Data teknisi berhasil disimpan.',
        'status_updated' => '<i class="fa fa-circle-check"></i> Status teknisi diperbarui.',
        'password_reset' => '<i class="fa fa-circle-check"></i> Password berhasil direset.',
        'ktp_uploaded'   => '<i class="fa fa-circle-check"></i> Foto KTP berhasil diunggah.',
    ];
    $msg = $msgMap[$_GET['msg']] ?? '';
}

// Reload setelah POST
if (!$err) {
    $stmt = $conn->prepare("SELECT * FROM tblclients WHERE id=? AND level=4 LIMIT 1");
    $stmt->bind_param('i', $tekId); $stmt->execute();
    $tek = $stmt->get_result()->fetch_assoc(); $stmt->close();
}

// ── Riwayat order yang ditugaskan ─────────────────────────────
$orders = [];
$oRes = $conn->query(
    "SELECT o.*, c.firstname AS cli_first, c.lastname AS cli_last,
            c.phonenumber AS cli_phone, p.name AS product_name, p.category
     FROM tblorders o
     JOIN tblclients c ON c.id = o.userid
     JOIN tblproducts p ON p.id = o.productid
     WHERE o.teknisi_id = $tekId OR o.teknisi_id_2 = $tekId
     ORDER BY o.created_at DESC LIMIT 50"
);
if ($oRes) { while($r=$oRes->fetch_assoc()) $orders[] = $r; }

$totalOrders  = count($orders);
$activeOrders = array_filter($orders, function($o) { return in_array($o['wifi_status'],['scheduled','installed']); });
$doneOrders   = array_filter($orders, function($o) { return in_array($o['wifi_status'],['active']); });

function wifiLabel($s) {
    $m = ['pending'=>['badge-yellow','Menunggu'],'verified'=>['badge-blue','Diverifikasi'],
          'scheduled'=>['badge-blue','Dijadwalkan'],'installed'=>['badge-green','Terpasang'],
          'active'=>['badge-green','Aktif'],'cancelled'=>['badge-red','Dibatalkan']];
    return $m[$s] ?? ['badge-gray',$s];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($tek['firstname'].' '.$tek['lastname']) ?> – Detail Teknisi</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/admin/style_admin.css">
<style>
.detail-header {
  background:var(--surface); border:1px solid var(--border); border-radius:16px;
  padding:28px; display:flex; align-items:center; gap:24px; margin-bottom:24px;
  flex-wrap:wrap;
}
.big-avatar {
  width:72px; height:72px; border-radius:50%;
  background:linear-gradient(135deg,#f59e0b,#ef4444);
  display:grid; place-items:center;
  font-size:28px; font-weight:800; color:#fff; flex-shrink:0;
}
.detail-header-info { flex:1; min-width:180px; }
.detail-header-info h2 { font-size:22px; font-weight:800; margin:0 0 4px; }
.detail-header-meta { font-size:13px; color:var(--muted); display:flex; flex-wrap:wrap; gap:12px; margin-top:8px; }
.detail-header-meta span { display:flex; align-items:center; gap:6px; }
.detail-header-actions { display:flex; gap:10px; flex-wrap:wrap; }

.tab-nav { display:flex; gap:4px; margin-bottom:24px; border-bottom:1px solid var(--border); }
.tab-btn {
  padding:10px 18px; font-size:13px; font-weight:600; background:none;
  border:none; border-bottom:2px solid transparent; color:var(--muted);
  cursor:pointer; font-family:inherit; margin-bottom:-1px; transition:all .2s;
}
.tab-btn.active { color:var(--accent2); border-bottom-color:var(--accent2); }
.tab-btn:hover { color:var(--text); }
.tab-pane { display:none; }
.tab-pane.active { display:block; }

.info-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(240px,1fr)); gap:16px; margin-bottom:20px; }
.info-field {
  background:var(--surface2); border:1px solid var(--border); border-radius:10px; padding:14px 16px;
}
.info-field label { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.6px; color:var(--muted); display:block; margin-bottom:5px; }
.info-field .val { font-size:13px; font-weight:500; }

.form-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
@media(max-width:600px){ .form-grid{grid-template-columns:1fr} }
.form-group { display:flex; flex-direction:column; gap:5px; }
.form-group.full { grid-column:1/-1; }
.form-group label { font-size:11px; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:.5px; }
.form-group input,
.form-group select,
.form-group textarea {
  background:var(--surface2); border:1px solid var(--border); color:var(--text);
  padding:9px 12px; border-radius:8px; font-size:13px; font-family:inherit; outline:none; transition:border-color .2s;
}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus { border-color:var(--accent2); }
.form-group textarea { resize:vertical; min-height:72px; }
.form-divider { grid-column:1/-1; border:none; border-top:1px solid var(--border); margin:4px 0; }

.alert { padding:12px 16px; border-radius:10px; font-size:13px; font-weight:600; margin-bottom:18px; display:flex; align-items:center; gap:10px; }
.alert-success { background:rgba(16,185,129,.1); border:1px solid rgba(16,185,129,.25); color:#34d399; }
.alert-danger  { background:rgba(239,68,68,.1);  border:1px solid rgba(239,68,68,.25);  color:#f87171; }

.stat-row { display:grid; grid-template-columns:repeat(auto-fill,minmax(150px,1fr)); gap:12px; margin-bottom:22px; }
.mini-stat { background:var(--surface2); border:1px solid var(--border); border-radius:10px; padding:14px 16px; text-align:center; }
.mini-stat-val { font-size:26px; font-weight:800; }
.mini-stat-lbl { font-size:11px; color:var(--muted); margin-top:3px; }

.order-table-wrap { background:var(--surface); border:1px solid var(--border); border-radius:12px; overflow:hidden; }
.order-table-wrap table { width:100%; border-collapse:collapse; font-size:13px; }
.order-table-wrap thead tr { background:var(--surface2); }
.order-table-wrap th { padding:10px 14px; text-align:left; font-size:11px; font-weight:700; color:var(--muted); letter-spacing:.5px; text-transform:uppercase; }
.order-table-wrap td { padding:12px 14px; border-bottom:1px solid var(--border); vertical-align:middle; }
.order-table-wrap tr:last-child td { border-bottom:none; }
.order-table-wrap tbody tr:hover { background:rgba(255,255,255,.02); cursor:pointer; }

.section-card { background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:22px; margin-bottom:20px; }
.section-card h4 { font-size:14px; font-weight:800; margin:0 0 16px; display:flex; align-items:center; gap:8px; }
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
    <a href="/admin/products.php" class="nav-item"><i class="fa fa-box-open"></i> Produk Layanan</a>
    <a href="/admin/clients.php" class="nav-item"><i class="fa fa-users"></i> Data Klien</a>
    <a href="/admin/teknisi.php" class="nav-item active"><i class="fa fa-screwdriver-wrench"></i> Teknisi</a>
    <a href="/admin/hosting.php" class="nav-item"><i class="fa fa-server"></i> Hosting</a>
    <a href="/admin/domains.php" class="nav-item"><i class="fa fa-globe"></i> Domain</a>

    <div class="nav-label">Support</div>
    <a href="/admin/tickets.php" class="nav-item">
      <i class="fa fa-ticket"></i> Tiket Support
      <?php if($sideStats['tickets'] > 0): ?>
        <span class="nav-badge"><?= $sideStats['tickets'] ?></span>
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
  <div class="topbar">
    <div class="page-title" style="display:flex;align-items:center;gap:10px;">
      <a href="/admin/teknisi.php" style="color:var(--muted);text-decoration:none;font-size:13px;font-weight:600;display:flex;align-items:center;gap:5px;">
        <i class="fa fa-arrow-left"></i> Teknisi
      </a>
      <span style="color:var(--border);">/</span>
      <span><?= htmlspecialchars($tek['firstname'].' '.$tek['lastname']) ?></span>
    </div>
    <div class="topbar-right">
      <span class="date-badge"><i class="fa fa-calendar-days" style="margin-right:6px;"></i><?= date('d M Y') ?></span>
      <a href="#" class="topbar-btn" onclick="confirmLogout(event)" title="Logout">
        <i class="fa fa-right-from-bracket"></i>
      </a>
    </div>
  </div>

  <div class="content">

    <?php if($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
    <?php if($err): ?><div class="alert alert-danger"><i class="fa fa-triangle-exclamation"></i> <?= $err ?></div><?php endif; ?>

    <!-- Header Card -->
    <div class="detail-header">
      <div class="big-avatar"><?= strtoupper(substr($tek['firstname'],0,1)) ?></div>
      <div class="detail-header-info">
        <h2><?= htmlspecialchars($tek['firstname'].' '.$tek['lastname']) ?></h2>
        <span class="badge <?= $tek['status'] ? 'badge-green' : 'badge-red' ?>" style="font-size:12px;">
          <i class="fa fa-screwdriver-wrench" style="margin-right:4px;"></i>
          Teknisi · <?= $tek['status'] ? 'Aktif' : 'Nonaktif' ?>
        </span>
        <div class="detail-header-meta">
          <?php if($tek['email']): ?>
            <span><i class="fa fa-envelope"></i><?= htmlspecialchars($tek['email']) ?></span>
          <?php endif; ?>
          <?php if($tek['phonenumber']): ?>
            <span><i class="fa fa-phone"></i><?= htmlspecialchars($tek['phonenumber']) ?></span>
          <?php endif; ?>
          <?php if($tek['city']): ?>
            <span><i class="fa fa-location-dot"></i><?= htmlspecialchars($tek['city'].($tek['state']?', '.$tek['state']:'')) ?></span>
          <?php endif; ?>
          <span style="color:var(--muted);"><i class="fa fa-calendar"></i>Terdaftar <?= date('d M Y', strtotime($tek['datecreated'])) ?></span>
        </div>
      </div>
      <div class="detail-header-actions">
        <form method="POST" onsubmit="return confirm('Ubah status akun teknisi ini?');">
          <input type="hidden" name="action" value="toggle_status">
          <button type="submit" class="btn <?= $tek['status'] ? 'btn-secondary' : 'btn-primary' ?>"
                  style="color:<?= $tek['status'] ? '#f87171' : '' ?>;">
            <i class="fa <?= $tek['status'] ? 'fa-ban' : 'fa-circle-check' ?>"></i>
            <?= $tek['status'] ? 'Nonaktifkan' : 'Aktifkan' ?>
          </button>
        </form>
        <a href="/admin/teknisi.php" class="btn btn-secondary">
          <i class="fa fa-arrow-left"></i> Kembali
        </a>
      </div>
    </div>

    <!-- Stats row -->
    <div class="stat-row">
      <div class="mini-stat">
        <div class="mini-stat-val" style="color:var(--accent);"><?= $totalOrders ?></div>
        <div class="mini-stat-lbl">Total Order Ditugaskan</div>
      </div>
      <div class="mini-stat">
        <div class="mini-stat-val" style="color:#fbbf24;"><?= count($activeOrders) ?></div>
        <div class="mini-stat-lbl">Sedang Dikerjakan</div>
      </div>
      <div class="mini-stat">
        <div class="mini-stat-val" style="color:#34d399;"><?= count($doneOrders) ?></div>
        <div class="mini-stat-lbl">Order Selesai (Aktif)</div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="tab-nav">
      <button class="tab-btn active" onclick="switchTab('info',this)"><i class="fa fa-id-card" style="margin-right:6px;"></i>Informasi</button>
      <button class="tab-btn" onclick="switchTab('orders',this)">
        <i class="fa fa-list-check" style="margin-right:6px;"></i>Riwayat Order
        <?php if($totalOrders > 0): ?>
          <span style="background:var(--accent2);color:#fff;font-size:10px;font-weight:700;padding:1px 7px;border-radius:20px;margin-left:4px;"><?= $totalOrders ?></span>
        <?php endif; ?>
      </button>
      <button class="tab-btn" onclick="switchTab('security',this)"><i class="fa fa-lock" style="margin-right:6px;"></i>Keamanan</button>
    </div>

    <!-- TAB: INFO -->
    <div id="tab-info" class="tab-pane active">
      <form method="POST">
        <input type="hidden" name="action" value="save_info">
        <div class="section-card">
          <h4><i class="fa fa-user" style="color:var(--accent2);"></i>Data Diri</h4>
          <div class="form-grid">
            <div class="form-group">
              <label>Nama Depan *</label>
              <input type="text" name="firstname" required value="<?= htmlspecialchars($tek['firstname']) ?>">
            </div>
            <div class="form-group">
              <label>Nama Belakang</label>
              <input type="text" name="lastname" value="<?= htmlspecialchars($tek['lastname']) ?>">
            </div>
            <div class="form-group">
              <label>Email</label>
              <input type="email" value="<?= htmlspecialchars($tek['email']) ?>" disabled
                     style="opacity:.5;cursor:not-allowed;" title="Email tidak dapat diubah di sini">
            </div>
            <div class="form-group">
              <label>No. Telepon</label>
              <input type="text" name="phonenumber" value="<?= htmlspecialchars($tek['phonenumber']) ?>">
            </div>
            <div class="form-group">
              <label>NIK</label>
              <input type="text" name="nik" maxlength="20" value="<?= htmlspecialchars($tek['nik'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label>Jenis Kelamin</label>
              <select name="jenis_kelamin">
                <option value="">-- Pilih --</option>
                <option value="L" <?= ($tek['jenis_kelamin']??'') === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                <option value="P" <?= ($tek['jenis_kelamin']??'') === 'P' ? 'selected' : '' ?>>Perempuan</option>
              </select>
            </div>
            <div class="form-group">
              <label>Tempat Lahir</label>
              <input type="text" name="tempat_lahir" value="<?= htmlspecialchars($tek['tempat_lahir'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label>Tanggal Lahir</label>
              <input type="date" name="tanggal_lahir" value="<?= htmlspecialchars($tek['tanggal_lahir'] ?? '') ?>">
            </div>

            <hr class="form-divider">

            <div class="form-group full">
              <label>Alamat</label>
              <input type="text" name="address1" value="<?= htmlspecialchars($tek['address1'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label>Kota</label>
              <input type="text" name="city" value="<?= htmlspecialchars($tek['city'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label>Provinsi</label>
              <input type="text" name="state" value="<?= htmlspecialchars($tek['state'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label>Kode Pos</label>
              <input type="text" name="postcode" value="<?= htmlspecialchars($tek['postcode'] ?? '') ?>">
            </div>

            <hr class="form-divider">

            <div class="form-group full">
              <label>Catatan Internal</label>
              <textarea name="notes" placeholder="Keahlian, catatan, dsb."><?= htmlspecialchars($tek['notes'] ?? '') ?></textarea>
            </div>
          </div>
          <div style="margin-top:16px;display:flex;justify-content:flex-end;">
            <button type="submit" class="btn btn-primary">
              <i class="fa fa-floppy-disk"></i> Simpan Perubahan
            </button>
          </div>
        </div>
      </form>

      <!-- ── Foto KTP ── -->
      <div class="section-card">
        <h4><i class="fa fa-id-card" style="color:#fbbf24;"></i>Foto KTP Teknisi</h4>
        <?php
          $ktpPath = !empty($tek['foto_ktp'])
            ? '/admin/asset/ktp_staff/' . htmlspecialchars($tek['foto_ktp'])
            : null;
          $ktpExt  = $ktpPath ? strtolower(pathinfo($tek['foto_ktp'], PATHINFO_EXTENSION)) : '';
        ?>
        <?php if ($ktpPath): ?>
          <div style="margin-bottom:16px;">
            <?php if ($ktpExt === 'pdf'): ?>
              <div style="padding:14px 18px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;display:flex;align-items:center;gap:12px;">
                <i class="fa fa-file-pdf" style="font-size:28px;color:#f87171;"></i>
                <div>
                  <div style="font-weight:600;font-size:13px;">Dokumen PDF</div>
                  <div style="font-size:11px;color:var(--muted);"><?= htmlspecialchars($tek['foto_ktp']) ?></div>
                </div>
                <a href="<?= $ktpPath ?>" target="_blank" class="btn btn-secondary btn-sm" style="margin-left:auto;">
                  <i class="fa fa-external-link"></i> Buka
                </a>
              </div>
            <?php else: ?>
              <a href="<?= $ktpPath ?>" target="_blank">
                <img src="<?= $ktpPath ?>" alt="KTP <?= htmlspecialchars($tek['firstname']) ?>"
                     style="max-width:100%;max-height:260px;border-radius:10px;border:1px solid var(--border);object-fit:contain;display:block;cursor:zoom-in;">
              </a>
              <div style="font-size:11px;color:var(--muted);margin-top:6px;">Klik gambar untuk memperbesar</div>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <div style="text-align:center;padding:24px 0;color:var(--muted);">
            <i class="fa fa-id-card" style="font-size:36px;opacity:.25;display:block;margin-bottom:10px;"></i>
            <span style="font-size:13px;">Belum ada foto KTP yang diunggah.</span>
          </div>
        <?php endif; ?>

        <!-- Form upload -->
        <form method="POST" enctype="multipart/form-data" style="margin-top:12px;">
          <input type="hidden" name="action" value="upload_ktp">
          <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
            <div style="flex:1;min-width:220px;">
              <label style="font-size:11px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:6px;">
                <?= $ktpPath ? 'Ganti Foto KTP' : 'Upload Foto KTP' ?>
              </label>
              <input type="file" name="ktp_file" id="tekKtpInput" accept=".jpg,.jpeg,.png,.webp,.pdf"
                     style="width:100%;background:var(--surface2);border:1px solid var(--border);color:var(--text);padding:8px 12px;border-radius:8px;font-size:12px;"
                     onchange="previewTekKtp(this)">
            </div>
            <button type="submit" class="btn btn-primary" style="white-space:nowrap;">
              <i class="fa fa-upload"></i> <?= $ktpPath ? 'Ganti KTP' : 'Upload KTP' ?>
            </button>
          </div>
          <div id="tekKtpPreview" style="margin-top:10px;display:none;">
            <img id="tekKtpPreviewImg" src="" alt="Preview" style="max-width:100%;max-height:120px;border-radius:8px;border:1px solid var(--border);object-fit:contain;">
            <div id="tekKtpPreviewPdf" style="display:none;font-size:12px;color:var(--muted);padding:8px 12px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;margin-top:6px;">
              <i class="fa fa-file-pdf" style="color:#f87171;margin-right:6px;"></i><span id="tekKtpPdfName"></span>
            </div>
          </div>
          <p style="font-size:11px;color:var(--muted);margin-top:8px;">Format: JPG, PNG, WEBP, PDF · Maks. 5 MB</p>
        </form>
      </div>
    </div>

    <!-- TAB: ORDERS -->
    <div id="tab-orders" class="tab-pane">
      <?php if(empty($orders)): ?>
        <div class="section-card">
          <div class="empty-state" style="padding:40px 0;">
            <i class="fa fa-list-check"></i>
            <p style="margin-top:12px;font-weight:600;color:var(--text);">Belum ada order yang ditugaskan.</p>
          </div>
        </div>
      <?php else: ?>
      <div class="order-table-wrap">
        <table>
          <thead>
            <tr>
              <th>No. Order</th>
              <th>Klien</th>
              <th>Produk</th>
              <th>Status</th>
              <th>Jadwal</th>
              <th>Tanggal</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach($orders as $o):
            [$badgeClass, $badgeLabel] = wifiLabel($o['wifi_status']);
          ?>
            <tr onclick="window.location='/admin/order_detail.php?id=<?= $o['id'] ?>'">
              <td>
                <div style="font-family:'JetBrains Mono',monospace;font-size:12px;color:var(--accent2);font-weight:600;">
                  <?= htmlspecialchars($o['order_number'] ?? '#'.$o['id']) ?>
                </div>
              </td>
              <td>
                <div style="font-weight:600;"><?= htmlspecialchars($o['cli_first'].' '.$o['cli_last']) ?></div>
                <div style="font-size:11px;color:var(--muted);"><?= htmlspecialchars($o['cli_phone']) ?></div>
              </td>
              <td>
                <div style="font-size:13px;"><?= htmlspecialchars($o['product_name']) ?></div>
                <div style="font-size:11px;color:var(--muted);"><?= ucfirst($o['category']) ?></div>
              </td>
              <td><span class="badge <?= $badgeClass ?>"><?= $badgeLabel ?></span></td>
              <td style="font-size:12px;color:var(--muted);">
                <?= $o['jadwal_instalasi'] ? date('d M Y', strtotime($o['jadwal_instalasi'])) : '–' ?>
              </td>
              <td style="font-size:12px;color:var(--muted);font-family:'JetBrains Mono',monospace;">
                <?= date('d M Y', strtotime($o['created_at'])) ?>
              </td>
              <td onclick="event.stopPropagation();">
                <a href="/admin/order_detail.php?id=<?= $o['id'] ?>" class="btn btn-primary" style="padding:5px 11px;font-size:12px;">
                  <i class="fa fa-eye"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <!-- TAB: SECURITY -->
    <div id="tab-security" class="tab-pane">
      <div class="section-card">
        <h4><i class="fa fa-key" style="color:#fbbf24;"></i>Reset Password</h4>
        <p style="font-size:13px;color:var(--muted);margin-bottom:18px;line-height:1.7;">
          Masukkan password baru untuk teknisi <strong><?= htmlspecialchars($tek['firstname']) ?></strong>.
          Password akan langsung aktif setelah disimpan.
        </p>
        <form method="POST" style="max-width:400px;">
          <input type="hidden" name="action" value="reset_password">
          <div class="form-group" style="margin-bottom:14px;">
            <label>Password Baru</label>
            <input type="password" name="new_password" minlength="8" required placeholder="Min. 8 karakter">
          </div>
          <button type="submit" class="btn btn-primary" onclick="return confirm('Reset password teknisi ini?')">
            <i class="fa fa-key"></i> Reset Password
          </button>
        </form>
      </div>

      <div class="section-card" style="border-color:rgba(239,68,68,.25);">
        <h4><i class="fa fa-triangle-exclamation" style="color:#f87171;"></i>Zona Berbahaya</h4>
        <p style="font-size:13px;color:var(--muted);margin-bottom:14px;line-height:1.7;">
          Menonaktifkan akun akan mencegah teknisi login, namun riwayat order tetap tersimpan.
        </p>
        <form method="POST" onsubmit="return confirm('Yakin ingin mengubah status akun ini?');">
          <input type="hidden" name="action" value="toggle_status">
          <button type="submit" class="btn" style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#f87171;padding:9px 18px;">
            <i class="fa <?= $tek['status'] ? 'fa-ban' : 'fa-circle-check' ?>"></i>
            <?= $tek['status'] ? 'Nonaktifkan Akun Teknisi' : 'Aktifkan Kembali Akun' ?>
          </button>
        </form>
      </div>
    </div>

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

<script>
function switchTab(name, btn) {
  document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('tab-' + name).classList.add('active');
  btn.classList.add('active');
}
function toggleSubMenu(e, groupId) {
  const group = document.getElementById(groupId);
  if (!group) return;
  const isOpen = group.classList.contains('open');
  if (isOpen) { e.preventDefault(); group.classList.remove('open'); e.currentTarget.classList.remove('expanded'); }
  else { group.classList.add('open'); e.currentTarget.classList.add('expanded'); }
}
function confirmLogout(e) { e.preventDefault(); document.getElementById('logoutModal').style.display = 'flex'; }
function closeLogoutModal() { document.getElementById('logoutModal').style.display = 'none'; }
document.getElementById('logoutModal').addEventListener('click', function(e) { if(e.target===this) closeLogoutModal(); });
document.addEventListener('keydown', e => { if(e.key==='Escape') closeLogoutModal(); });

function previewTekKtp(input) {
  const file = input.files[0];
  if (!file) return;
  const wrap = document.getElementById('tekKtpPreview');
  const img  = document.getElementById('tekKtpPreviewImg');
  const pdf  = document.getElementById('tekKtpPreviewPdf');
  const pdfN = document.getElementById('tekKtpPdfName');
  wrap.style.display = 'block';
  if (file.type === 'application/pdf') {
    img.style.display = 'none';
    pdf.style.display = 'block';
    pdfN.textContent  = file.name;
  } else {
    img.style.display = 'block';
    pdf.style.display = 'none';
    const reader = new FileReader();
    reader.onload = e => { img.src = e.target.result; };
    reader.readAsDataURL(file);
  }
}
</script>
</body>
</html>
