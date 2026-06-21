<?php
// ============================================================
// admin/teknisi.php – Manajemen Teknisi Perkasa Solusindo
// ============================================================
require_once '../config.php';
require_once '../auth_check.php';
require_once '../mailer.php';
requireLevel([1, 2]);

$adminId   = (int)($_SESSION['user_id'] ?? 0);
$adminName = $_SESSION['user_firstname'] ?? 'Admin';

// ── Sidebar badges ────────────────────────────────────────────
function cntTek($conn, $sql) { $r = $conn->query($sql); return $r ? (int)$r->fetch_row()[0] : 0; }
$sideStats = [];
$sideStats['unpaid']  = cntTek($conn, "SELECT COUNT(*) FROM tblinvoices WHERE status='Unpaid'");
$sideStats['tickets'] = cntTek($conn, "SELECT COUNT(*) FROM tbltickets WHERE status='Open'");
$totalOrdersPending   = cntTek($conn, "SELECT COUNT(*) FROM tblorders WHERE wifi_status IN ('pending','verified','scheduled')");

// ── Pastikan kolom tambahan ada (idempotent) ──────────────────
$conn->query("ALTER TABLE tblclients ADD COLUMN IF NOT EXISTS nik varchar(20) DEFAULT NULL AFTER notes");
$conn->query("ALTER TABLE tblclients ADD COLUMN IF NOT EXISTS tempat_lahir varchar(100) DEFAULT NULL AFTER nik");
$conn->query("ALTER TABLE tblclients ADD COLUMN IF NOT EXISTS tanggal_lahir date DEFAULT NULL AFTER tempat_lahir");
$conn->query("ALTER TABLE tblclients ADD COLUMN IF NOT EXISTS jenis_kelamin enum('L','P') DEFAULT NULL AFTER tanggal_lahir");
$conn->query("ALTER TABLE tblclients ADD COLUMN IF NOT EXISTS notes text DEFAULT NULL AFTER lastupdated");
$conn->query("ALTER TABLE tblclients ADD COLUMN IF NOT EXISTS foto_ktp varchar(255) DEFAULT NULL AFTER jenis_kelamin");

// ── POST: Tambah teknisi baru ─────────────────────────────────
$msg = $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_teknisi') {
        $firstname    = trim($_POST['firstname'] ?? '');
        $lastname     = trim($_POST['lastname'] ?? '');
        $email        = trim($_POST['email'] ?? '');
        $phone        = trim($_POST['phonenumber'] ?? '');
        $address      = trim($_POST['address1'] ?? '');
        $city         = trim($_POST['city'] ?? '');
        $state        = trim($_POST['state'] ?? '');
        $postcode     = trim($_POST['postcode'] ?? '');
        $nik          = trim($_POST['nik'] ?? '');
        $tempat_lahir = trim($_POST['tempat_lahir'] ?? '');
        $tgl_lahir    = trim($_POST['tanggal_lahir'] ?? '') ?: null;
        $jenis_kel    = in_array($_POST['jenis_kelamin'] ?? '', ['L','P']) ? $_POST['jenis_kelamin'] : null;
        $notes        = trim($_POST['notes'] ?? '');
        $rawPass      = trim($_POST['password'] ?? '');

        if (!$firstname || !$email || !$rawPass) {
            $err = 'Nama depan, email, dan password wajib diisi.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $err = 'Format email tidak valid.';
        } elseif (strlen($rawPass) < 8) {
            $err = 'Password minimal 8 karakter.';
        } else {
            // Cek email duplikat
            $chk = $conn->prepare("SELECT id FROM tblclients WHERE email = ? LIMIT 1");
            $chk->bind_param('s', $email); $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $err = "Email <strong>$email</strong> sudah terdaftar.";
            } else {
                $hash = password_hash($rawPass, PASSWORD_BCRYPT);

                // Generate token verifikasi email — pakai kolom reset_token yang sudah ada di DB
                $verifyToken = bin2hex(random_bytes(32));
                $verifyExp   = date('Y-m-d H:i:s', time() + 86400); // berlaku 24 jam

                $ins = $conn->prepare(
                    "INSERT INTO tblclients
                     (firstname,lastname,email,phonenumber,address1,city,state,postcode,
                      nik,tempat_lahir,tanggal_lahir,jenis_kelamin,notes,
                      password,level,status,email_verified,accepttos,
                      reset_token,reset_token_expires,datecreated)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,4,1,0,1,?,?,NOW())"
                );
                $ins->bind_param(
                    'ssssssssssssssss',
                    $firstname,$lastname,$email,$phone,$address,$city,$state,$postcode,
                    $nik,$tempat_lahir,$tgl_lahir,$jenis_kel,$notes,$hash,
                    $verifyToken,$verifyExp
                );
                if ($ins->execute()) {
                    $newId = $ins->insert_id;
                    $ins->close();

                    // ── Upload KTP jika ada ───────────────────────────────
                    $ktpFile = null;
                    if (!empty($_FILES['ktp_file']['name'])) {
                        $allowed = ['image/jpeg','image/png','image/webp','application/pdf'];
                        $ftype   = mime_content_type($_FILES['ktp_file']['tmp_name']);
                        $fsize   = $_FILES['ktp_file']['size'];
                        if (!in_array($ftype, $allowed)) {
                            $err = 'Format file KTP tidak didukung. Gunakan JPG, PNG, WEBP, atau PDF.';
                        } elseif ($fsize > 5 * 1024 * 1024) {
                            $err = 'Ukuran file KTP maksimal 5 MB.';
                        } else {
                            $ext      = pathinfo($_FILES['ktp_file']['name'], PATHINFO_EXTENSION);
                            $ktpName  = 'teknisi_' . $newId . '_' . time() . '.' . strtolower($ext);
                            $ktpDir   = $_SERVER['DOCUMENT_ROOT'] . '/admin/asset/ktp_staff/';
                            if (!is_dir($ktpDir)) mkdir($ktpDir, 0755, true);
                            if (move_uploaded_file($_FILES['ktp_file']['tmp_name'], $ktpDir . $ktpName)) {
                                $ktpFile = $ktpName;
                                $upd = $conn->prepare("UPDATE tblclients SET foto_ktp=? WHERE id=?");
                                $upd->bind_param('si', $ktpFile, $newId);
                                $upd->execute(); $upd->close();
                            }
                        }
                    }

                    if (!$err) {
                        // ── Kirim email selamat datang + verifikasi ───────
                        $verifyUrl = 'https://perkasasolusindo.co.id/login/verify_email_process.php'
                                   . '?token=' . urlencode($verifyToken)
                                   . '&email=' . urlencode($email);

                        $mailSent = perkasa_send_mail(
                            $email,
                            trim($firstname . ' ' . $lastname),
                            '🔧 Akun Teknisi Anda di Perkasa Solusindo – Verifikasi Email',
                            render_email_welcome_teknisi([
                                'firstname'  => $firstname,
                                'lastname'   => $lastname,
                                'email'      => $email,
                                'password'   => $rawPass,
                                'verify_url' => $verifyUrl,
                                'admin_name' => $adminName,
                            ])
                        );

                        $redirectParam = $mailSent ? 'added' : 'added_nomail';
                        header("Location: /admin/teknisi.php?msg={$redirectParam}&new=$newId");
                        exit;
                    }
                } else {
                    $err = 'Gagal menyimpan data: ' . $conn->error;
                }
                $ins->close();
            }
            $chk->close();
        }
    }

    if ($action === 'toggle_status') {
        $tid = (int)($_POST['teknisi_id'] ?? 0);
        if ($tid) {
            $conn->query("UPDATE tblclients SET status = 1 - status WHERE id = $tid AND level = 4");
            header("Location: /admin/teknisi.php?msg=status_updated"); exit;
        }
    }
}

if (isset($_GET['msg'])) {
    $msgMap = [
        'added'          => '<i class="fa fa-circle-check"></i> Teknisi baru berhasil didaftarkan &amp; email verifikasi telah dikirim.',
        'added_nomail'   => '<i class="fa fa-triangle-exclamation"></i> Teknisi berhasil didaftarkan, namun email verifikasi <strong>gagal dikirim</strong>. Hubungi teknisi secara manual.',
        'status_updated' => '<i class="fa fa-circle-check"></i> Status teknisi diperbarui.',
    ];
    $msg        = $msgMap[$_GET['msg']] ?? '';
    $msgIsWarn  = ($_GET['msg'] === 'added_nomail');
}

// ── Filter & Search ───────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
$filter = $_GET['status'] ?? 'all';

$sql   = "SELECT * FROM tblclients WHERE level = 4";
$types = '';
$prms  = [];

if ($filter === 'active')   { $sql .= " AND status = 1"; }
if ($filter === 'inactive') { $sql .= " AND status = 0"; }
if ($search) {
    $like   = "%$search%";
    $sql   .= " AND (firstname LIKE ? OR lastname LIKE ? OR email LIKE ? OR phonenumber LIKE ? OR nik LIKE ?)";
    $types .= 'sssss';
    $prms   = [$like,$like,$like,$like,$like];
}
$sql .= " ORDER BY datecreated DESC";

$teknisiList = [];
$stmt = $conn->prepare($sql);
if ($stmt) {
    if ($types) $stmt->bind_param($types, ...$prms);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $teknisiList[] = $row;
    $stmt->close();
}

// Hitung order yang ditugaskan per teknisi (teknisi 1 DAN teknisi 2)
$orderCounts = [];
$oc = $conn->query(
    "SELECT tid, COUNT(*) AS c FROM (" .
    "SELECT teknisi_id AS tid FROM tblorders WHERE teknisi_id IS NOT NULL " .
    "UNION ALL " .
    "SELECT teknisi_id_2 AS tid FROM tblorders WHERE teknisi_id_2 IS NOT NULL" .
    ") t GROUP BY tid"
);
if ($oc) { while ($r = $oc->fetch_assoc()) $orderCounts[$r['tid']] = $r['c']; }

// Hitung order aktif (non-completed) per teknisi (teknisi 1 DAN teknisi 2)
$activeOrderCounts = [];
$ac = $conn->query(
    "SELECT tid, COUNT(*) AS c FROM (" .
    "SELECT teknisi_id AS tid FROM tblorders WHERE teknisi_id IS NOT NULL AND wifi_status IN ('scheduled','installed') " .
    "UNION ALL " .
    "SELECT teknisi_id_2 AS tid FROM tblorders WHERE teknisi_id_2 IS NOT NULL AND wifi_status IN ('scheduled','installed')" .
    ") t GROUP BY tid"
);
if ($ac) { while ($r = $ac->fetch_assoc()) $activeOrderCounts[$r['tid']] = $r['c']; }

// Total counts
$totalAll      = cntTek($conn, "SELECT COUNT(*) FROM tblclients WHERE level=4");
$totalActive   = cntTek($conn, "SELECT COUNT(*) FROM tblclients WHERE level=4 AND status=1");
$totalInactive = $totalAll - $totalActive;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manajemen Teknisi – Perkasa Solusindo</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/admin/style_admin.css">
<style>
.filter-tabs { display:flex; gap:6px; flex-wrap:wrap; }
.filter-tab {
  padding:7px 16px; border-radius:8px; font-size:12px; font-weight:600;
  border:1px solid var(--border); background:var(--surface2); color:var(--muted);
  text-decoration:none; transition:all .2s; display:flex; align-items:center; gap:6px;
}
.filter-tab:hover { border-color:var(--accent2); color:var(--text); }
.filter-tab.active { background:rgba(59,130,246,.12); border-color:var(--accent2); color:var(--accent2); }
.filter-tab .count-pill { background:var(--border); border-radius:20px; padding:0 7px; font-size:10px; }

.tek-table-wrap {
  background:var(--surface); border:1px solid var(--border); border-radius:14px; overflow:hidden;
}
.tek-table-wrap table { width:100%; border-collapse:collapse; font-size:13px; }
.tek-table-wrap thead tr { background:var(--surface2); border-bottom:1px solid var(--border); }
.tek-table-wrap th {
  padding:12px 18px; text-align:left; font-size:11px; font-weight:700;
  color:var(--muted); letter-spacing:.5px; text-transform:uppercase; white-space:nowrap;
}
.tek-table-wrap td { padding:14px 18px; border-bottom:1px solid var(--border); vertical-align:middle; }
.tek-table-wrap tr:last-child td { border-bottom:none; }
.tek-table-wrap tbody tr:hover { background:rgba(255,255,255,.02); cursor:pointer; }

.tek-avatar {
  width:40px; height:40px; border-radius:50%;
  background:linear-gradient(135deg,#f59e0b,#ef4444);
  display:grid; place-items:center;
  font-size:15px; font-weight:700; color:#fff; flex-shrink:0;
}
.tek-name-cell { display:flex; align-items:center; gap:12px; }

.search-bar { display:flex; gap:8px; align-items:center; flex:1; max-width:360px; }
.search-input {
  flex:1; background:var(--surface2); border:1px solid var(--border); color:var(--text);
  padding:8px 14px; border-radius:8px; font-size:13px; font-family:inherit; outline:none; transition:border-color .2s;
}
.search-input:focus { border-color:var(--accent2); }
.search-input::placeholder { color:var(--muted); }
.toolbar { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:20px; flex-wrap:wrap; }

/* Modal */
.modal-overlay {
  display:none; position:fixed; inset:0; z-index:500;
  background:rgba(0,0,0,.65); backdrop-filter:blur(4px);
  align-items:center; justify-content:center; padding:20px;
}
.modal-overlay.open { display:flex; }
.modal-box {
  background:var(--surface); border:1px solid var(--border); border-radius:16px;
  width:100%; max-width:640px; max-height:90vh; overflow-y:auto;
  box-shadow:0 24px 60px rgba(0,0,0,.5); animation:fadeInUp .22s ease;
}
@keyframes fadeInUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }
.modal-header {
  padding:20px 24px 16px; border-bottom:1px solid var(--border);
  display:flex; justify-content:space-between; align-items:center; position:sticky; top:0;
  background:var(--surface); z-index:1;
}
.modal-header h3 { font-size:16px; font-weight:800; margin:0; }
.modal-close {
  width:32px; height:32px; border-radius:8px; background:var(--surface2);
  border:1px solid var(--border); color:var(--muted); cursor:pointer;
  display:grid; place-items:center; transition:all .2s;
}
.modal-close:hover { background:rgba(239,68,68,.1); border-color:#f87171; color:#f87171; }
.modal-body { padding:24px; }
.form-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
@media(max-width:560px){ .form-grid { grid-template-columns:1fr; } }
.form-group { display:flex; flex-direction:column; gap:6px; }
.form-group.full { grid-column:1/-1; }
.form-group label { font-size:11px; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:.5px; }
.form-group input,
.form-group select,
.form-group textarea {
  background:var(--surface2); border:1px solid var(--border); color:var(--text);
  padding:9px 12px; border-radius:8px; font-size:13px; font-family:inherit; outline:none; transition:border-color .2s;
}
.form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color:var(--accent2); }
.form-group textarea { resize:vertical; min-height:72px; }
.form-divider { grid-column:1/-1; border:none; border-top:1px solid var(--border); margin:4px 0; }
.modal-footer { padding:16px 24px; border-top:1px solid var(--border); display:flex; justify-content:flex-end; gap:10px; }

/* Alert */
.alert { padding:12px 16px; border-radius:10px; font-size:13px; font-weight:600; margin-bottom:18px; display:flex; align-items:center; gap:10px; }
.alert-success { background:rgba(16,185,129,.1); border:1px solid rgba(16,185,129,.25); color:#34d399; }
.alert-danger  { background:rgba(239,68,68,.1);  border:1px solid rgba(239,68,68,.25);  color:#f87171; }

/* Stats cards */
.tek-stats { display:grid; grid-template-columns:repeat(auto-fill,minmax(180px,1fr)); gap:14px; margin-bottom:24px; }
.tek-stat {
  background:var(--surface); border:1px solid var(--border); border-radius:12px;
  padding:16px 18px; display:flex; align-items:center; gap:14px;
}
.tek-stat-icon {
  width:40px; height:40px; border-radius:10px;
  display:grid; place-items:center; font-size:16px; flex-shrink:0;
}
.tek-stat-icon.orange { background:rgba(245,158,11,.12); color:#fbbf24; }
.tek-stat-icon.green  { background:rgba(16,185,129,.12);  color:#34d399; }
.tek-stat-icon.red    { background:rgba(239,68,68,.12);   color:#f87171; }
.tek-stat-val  { font-size:22px; font-weight:800; line-height:1; }
.tek-stat-lbl  { font-size:11px; color:var(--muted); margin-top:3px; }

.workload-bar { height:5px; background:var(--border); border-radius:3px; width:80px; overflow:hidden; }
.workload-fill { height:100%; background:var(--accent2); border-radius:3px; transition:width .3s; }
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
    <button class="hamburger" id="hamburgerBtn" onclick="toggleSidebar()" aria-label="Toggle Menu"><span></span><span></span><span></span></button>
      
      <i class="fa fa-screwdriver-wrench" style="color:var(--accent);margin-right:8px;"></i>
      Manajemen Teknisi
    </div>
    <div class="topbar-right">
      <span class="date-badge"><i class="fa fa-calendar-days" style="margin-right:6px;"></i><?= date('d M Y') ?></span>
      <button class="btn btn-primary" onclick="openModal()" style="display:flex;align-items:center;gap:7px;">
        <i class="fa fa-user-plus"></i> Daftarkan Teknisi
      </button>
      <a href="#" class="topbar-btn" onclick="confirmLogout(event)" title="Logout">
        <i class="fa fa-right-from-bracket"></i>
      </a>
    </div>
  </div>

  <div class="content">

    <!-- Alerts -->
    <?php if($msg): ?>
      <div class="alert <?= !empty($msgIsWarn) ? 'alert-warning' : 'alert-success' ?>"
           style="<?= !empty($msgIsWarn) ? 'background:rgba(234,179,8,.1);border:1px solid rgba(234,179,8,.3);color:#fde68a;' : '' ?>">
        <?= $msg ?>
      </div>
    <?php endif; ?>
    <?php if($err): ?><div class="alert alert-danger"><i class="fa fa-triangle-exclamation"></i> <?= $err ?></div><?php endif; ?>

    <!-- Stat Cards -->
    <div class="tek-stats">
      <div class="tek-stat">
        <div class="tek-stat-icon orange"><i class="fa fa-screwdriver-wrench"></i></div>
        <div>
          <div class="tek-stat-val"><?= $totalAll ?></div>
          <div class="tek-stat-lbl">Total Teknisi</div>
        </div>
      </div>
      <div class="tek-stat">
        <div class="tek-stat-icon green"><i class="fa fa-circle-check"></i></div>
        <div>
          <div class="tek-stat-val"><?= $totalActive ?></div>
          <div class="tek-stat-lbl">Aktif</div>
        </div>
      </div>
      <div class="tek-stat">
        <div class="tek-stat-icon red"><i class="fa fa-circle-xmark"></i></div>
        <div>
          <div class="tek-stat-val"><?= $totalInactive ?></div>
          <div class="tek-stat-lbl">Nonaktif</div>
        </div>
      </div>
      <div class="tek-stat">
        <div class="tek-stat-icon orange"><i class="fa fa-list-check"></i></div>
        <div>
          <div class="tek-stat-val"><?= array_sum($activeOrderCounts) ?></div>
          <div class="tek-stat-lbl">Order Ditugaskan</div>
        </div>
      </div>
    </div>

    <!-- Toolbar -->
    <div class="toolbar">
      <div class="filter-tabs">
        <?php
        $tabs = ['all'=>"Semua <span class='count-pill'>$totalAll</span>",
                 'active'=>"Aktif <span class='count-pill'>$totalActive</span>",
                 'inactive'=>"Nonaktif <span class='count-pill'>$totalInactive</span>"];
        foreach ($tabs as $k => $lbl):
          $active = ($filter === $k) ? 'active' : '';
          $qs = http_build_query(array_merge($_GET,['status'=>$k,'q'=>$search]));
        ?>
          <a href="?<?= $qs ?>" class="filter-tab <?= $active ?>"><?= $lbl ?></a>
        <?php endforeach; ?>
      </div>
      <form method="GET" class="search-bar">
        <input type="hidden" name="status" value="<?= htmlspecialchars($filter) ?>">
        <input type="text" name="q" placeholder="Cari nama, email, NIK..." class="search-input"
               value="<?= htmlspecialchars($search) ?>" autocomplete="off">
        <button type="submit" class="btn btn-primary" style="padding:8px 14px;"><i class="fa fa-magnifying-glass"></i></button>
        <?php if($search): ?>
          <a href="?status=<?= $filter ?>" class="btn btn-secondary" style="padding:8px 14px;"><i class="fa fa-xmark"></i></a>
        <?php endif; ?>
      </form>
    </div>

    <!-- Table -->
    <div class="tek-table-wrap">
      <?php if(empty($teknisiList)): ?>
        <div class="empty-state" style="padding:60px 20px;">
          <i class="fa fa-screwdriver-wrench"></i>
          <p style="margin-top:10px;font-weight:600;color:var(--text);">
            <?= $search ? 'Tidak ada teknisi yang cocok dengan pencarian "' . htmlspecialchars($search) . '".' : 'Belum ada teknisi terdaftar.' ?>
          </p>
          <?php if(!$search): ?>
          <button onclick="openModal()" class="btn btn-primary" style="margin-top:16px;">
            <i class="fa fa-user-plus"></i> Daftarkan Teknisi Pertama
          </button>
          <?php endif; ?>
        </div>
      <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Teknisi</th>
            <th>Kontak</th>
            <th>NIK</th>
            <th>Beban Kerja</th>
            <th>Total Order</th>
            <th>Status</th>
            <th>Terdaftar</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach($teknisiList as $t):
          $initial = strtoupper(substr($t['firstname'],0,1));
          $fullname = htmlspecialchars($t['firstname'].' '.$t['lastname']);
          $totalOrd = $orderCounts[$t['id']] ?? 0;
          $activeOrd= $activeOrderCounts[$t['id']] ?? 0;
          $workloadPct = min(100, $activeOrd * 20); // maks 5 order = 100%
        ?>
          <tr onclick="window.location='/admin/teknisi_detail.php?id=<?= $t['id'] ?>'"
              title="Lihat detail <?= $fullname ?>">
            <td>
              <div class="tek-name-cell">
                <div class="tek-avatar"><?= $initial ?></div>
                <div>
                  <div style="font-weight:700;font-size:13px;"><?= $fullname ?></div>
                  <div style="font-size:11px;color:var(--muted);"><?= htmlspecialchars($t['email']) ?></div>
                </div>
              </div>
            </td>
            <td>
              <div style="font-size:13px;"><?= htmlspecialchars($t['phonenumber'] ?: '–') ?></div>
              <?php if($t['city']): ?>
                <div style="font-size:11px;color:var(--muted);"><?= htmlspecialchars($t['city']) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <span style="font-family:'JetBrains Mono',monospace;font-size:12px;color:var(--muted);">
                <?= $t['nik'] ? htmlspecialchars($t['nik']) : '–' ?>
              </span>
            </td>
            <td>
              <div style="display:flex;align-items:center;gap:8px;">
                <div class="workload-bar">
                  <div class="workload-fill" style="width:<?= $workloadPct ?>%;
                    background:<?= $workloadPct>=80?'#f87171':($workloadPct>=40?'#fbbf24':'var(--accent2)') ?>;"></div>
                </div>
                <span style="font-size:11px;color:var(--muted);"><?= $activeOrd ?> aktif</span>
              </div>
            </td>
            <td>
              <span style="font-family:'JetBrains Mono',monospace;font-weight:700;font-size:13px;">
                <?= $totalOrd ?>
              </span>
              <span style="font-size:11px;color:var(--muted);"> order</span>
            </td>
            <td>
              <span class="badge <?= $t['status'] ? 'badge-green' : 'badge-red' ?>">
                <?= $t['status'] ? 'Aktif' : 'Nonaktif' ?>
              </span>
            </td>
            <td style="font-size:12px;color:var(--muted);font-family:'JetBrains Mono',monospace;">
              <?= date('d M Y', strtotime($t['datecreated'])) ?>
            </td>
            <td onclick="event.stopPropagation();">
              <div style="display:flex;gap:6px;flex-wrap:wrap;">
                <a href="/admin/teknisi_detail.php?id=<?= $t['id'] ?>" class="btn btn-primary" style="padding:6px 12px;font-size:12px;">
                  <i class="fa fa-eye"></i> Detail
                </a>
                <form method="POST" onsubmit="return confirm('Ubah status teknisi ini?');" style="margin:0;">
                  <input type="hidden" name="action" value="toggle_status">
                  <input type="hidden" name="teknisi_id" value="<?= $t['id'] ?>">
                  <button type="submit" class="btn <?= $t['status'] ? 'btn-secondary' : 'btn-secondary' ?>"
                          style="padding:6px 12px;font-size:12px;color:<?= $t['status'] ? '#f87171' : '#34d399' ?>;">
                    <i class="fa <?= $t['status'] ? 'fa-ban' : 'fa-circle-check' ?>"></i>
                    <?= $t['status'] ? 'Nonaktifkan' : 'Aktifkan' ?>
                  </button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>

  </div><!-- /content -->
</main>

<!-- ═══════════════ MODAL: TAMBAH TEKNISI ═══════════════ -->
<div class="modal-overlay" id="addModal">
  <div class="modal-box">
    <div class="modal-header">
      <h3><i class="fa fa-user-plus" style="color:var(--accent);margin-right:8px;"></i>Daftarkan Teknisi Baru</h3>
      <button class="modal-close" onclick="closeModal()"><i class="fa fa-xmark"></i></button>
    </div>
    <form method="POST" id="addForm" enctype="multipart/form-data">
      <input type="hidden" name="action" value="add_teknisi">
      <div class="modal-body">
        <?php if($err): ?><div class="alert alert-danger"><i class="fa fa-triangle-exclamation"></i> <?= $err ?></div><?php endif; ?>

        <div class="form-grid">
          <div class="form-group">
            <label>Nama Depan <span style="color:#f87171;">*</span></label>
            <input type="text" name="firstname" required placeholder="contoh: Budi"
                   value="<?= htmlspecialchars($_POST['firstname'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Nama Belakang</label>
            <input type="text" name="lastname" placeholder="contoh: Santoso"
                   value="<?= htmlspecialchars($_POST['lastname'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Email <span style="color:#f87171;">*</span></label>
            <input type="email" name="email" required placeholder="teknisi@email.com"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>No. Telepon</label>
            <input type="text" name="phonenumber" placeholder="08xxxxxxxx"
                   value="<?= htmlspecialchars($_POST['phonenumber'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Password <span style="color:#f87171;">*</span></label>
            <div style="position:relative;">
              <input type="password" name="password" id="pwInput" required minlength="8" placeholder="Min. 8 karakter"
                     oninput="checkPw(this.value)" style="padding-right:40px;">
              <button type="button" onclick="togglePw('pwInput','eyeBtn1')" id="eyeBtn1"
                      style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--muted);cursor:pointer;padding:0;font-size:14px;">
                <i class="fa fa-eye"></i>
              </button>
            </div>
            <!-- Password strength bar -->
            <div style="margin-top:6px;">
              <div style="height:4px;border-radius:2px;background:var(--border);overflow:hidden;">
                <div id="pwBar" style="height:100%;width:0%;transition:width .3s,background .3s;border-radius:2px;"></div>
              </div>
              <div id="pwLabel" style="font-size:10px;margin-top:4px;color:var(--muted);"></div>
            </div>
            <!-- Checklist -->
            <div id="pwChecks" style="margin-top:8px;display:flex;flex-wrap:wrap;gap:6px;">
              <span id="ck-len"  class="pw-ck"><i class="fa fa-circle-xmark"></i> Min. 8 karakter</span>
              <span id="ck-up"   class="pw-ck"><i class="fa fa-circle-xmark"></i> Huruf kapital</span>
              <span id="ck-num"  class="pw-ck"><i class="fa fa-circle-xmark"></i> Angka</span>
              <span id="ck-sym"  class="pw-ck"><i class="fa fa-circle-xmark"></i> Simbol</span>
            </div>
          </div>
          <div class="form-group">
            <label>Konfirmasi Password <span style="color:#f87171;">*</span></label>
            <div style="position:relative;">
              <input type="password" name="password_confirm" id="pwConfirm" required placeholder="Ulangi password"
                     oninput="checkConfirm()" style="padding-right:40px;">
              <button type="button" onclick="togglePw('pwConfirm','eyeBtn2')" id="eyeBtn2"
                      style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--muted);cursor:pointer;padding:0;font-size:14px;">
                <i class="fa fa-eye"></i>
              </button>
            </div>
            <div id="pwMatchMsg" style="font-size:11px;margin-top:5px;display:none;"></div>
          </div>
          <div class="form-group">
            <label>NIK</label>
            <input type="text" name="nik" placeholder="16 digit NIK" maxlength="20"
                   value="<?= htmlspecialchars($_POST['nik'] ?? '') ?>">
          </div>

          <hr class="form-divider">

          <div class="form-group">
            <label>Tempat Lahir</label>
            <input type="text" name="tempat_lahir" placeholder="Kota lahir"
                   value="<?= htmlspecialchars($_POST['tempat_lahir'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Tanggal Lahir</label>
            <input type="date" name="tanggal_lahir"
                   value="<?= htmlspecialchars($_POST['tanggal_lahir'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Jenis Kelamin</label>
            <select name="jenis_kelamin">
              <option value="">-- Pilih --</option>
              <option value="L" <?= (($_POST['jenis_kelamin'] ?? '') === 'L') ? 'selected' : '' ?>>Laki-laki</option>
              <option value="P" <?= (($_POST['jenis_kelamin'] ?? '') === 'P') ? 'selected' : '' ?>>Perempuan</option>
            </select>
          </div>
          <div class="form-group">
            <label>Kota</label>
            <input type="text" name="city" placeholder="Kota domisili"
                   value="<?= htmlspecialchars($_POST['city'] ?? '') ?>">
          </div>

          <hr class="form-divider">

          <div class="form-group full">
            <label>Alamat</label>
            <input type="text" name="address1" placeholder="Jl. Contoh No. 1"
                   value="<?= htmlspecialchars($_POST['address1'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Provinsi / State</label>
            <input type="text" name="state" placeholder="Jawa Timur"
                   value="<?= htmlspecialchars($_POST['state'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Kode Pos</label>
            <input type="text" name="postcode" placeholder="61271"
                   value="<?= htmlspecialchars($_POST['postcode'] ?? '') ?>">
          </div>

          <hr class="form-divider">

          <div class="form-group full">
            <label>Foto KTP <span style="font-weight:400;color:var(--muted);">(opsional, maks. 5 MB)</span></label>
            <div id="ktpDropzone" style="border:2px dashed var(--border);border-radius:10px;padding:18px;text-align:center;cursor:pointer;transition:border-color .2s;background:var(--surface2);"
                 onclick="document.getElementById('ktpInput').click()"
                 ondragover="event.preventDefault();this.style.borderColor='var(--accent2)'"
                 ondragleave="this.style.borderColor='var(--border)'"
                 ondrop="handleKtpDrop(event)">
              <i class="fa fa-id-card" style="font-size:28px;color:var(--muted);margin-bottom:8px;display:block;"></i>
              <span id="ktpDropLabel" style="font-size:12px;color:var(--muted);">Klik atau seret file KTP ke sini<br><span style="font-size:11px;">JPG, PNG, WEBP, PDF</span></span>
            </div>
            <input type="file" name="ktp_file" id="ktpInput" accept=".jpg,.jpeg,.png,.webp,.pdf" style="display:none;"
                   onchange="previewKtp(this)">
            <div id="ktpPreview" style="margin-top:10px;display:none;">
              <img id="ktpPreviewImg" src="" alt="Preview KTP"
                   style="max-width:100%;max-height:160px;border-radius:8px;border:1px solid var(--border);object-fit:contain;">
              <div id="ktpPreviewPdf" style="display:none;padding:10px 14px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;font-size:12px;color:var(--muted);">
                <i class="fa fa-file-pdf" style="color:#f87171;margin-right:6px;"></i><span id="ktpPdfName"></span>
              </div>
              <button type="button" onclick="clearKtp()" style="margin-top:6px;background:none;border:none;color:#f87171;font-size:12px;cursor:pointer;font-family:inherit;">
                <i class="fa fa-xmark"></i> Hapus
              </button>
            </div>
          </div>

          <hr class="form-divider">

          <div class="form-group full">
            <label>Catatan Internal</label>
            <textarea name="notes" placeholder="Keahlian, keterangan tambahan, dsb."><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal()">
          <i class="fa fa-xmark"></i> Batal
        </button>
        <button type="submit" class="btn btn-primary">
          <i class="fa fa-user-plus"></i> Daftarkan Teknisi
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ═══════════ LOGOUT MODAL ═══════════ -->
<div id="logoutModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);align-items:center;justify-content:center;">
  <div style="background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:32px;max-width:400px;width:90%;text-align:center;box-shadow:0 24px 60px rgba(0,0,0,.5);animation:fadeInUp .25s ease;">
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

<style>@keyframes fadeInUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}</style>

<style>
.pw-ck {
  display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:600;
  padding:2px 8px;border-radius:20px;
  background:rgba(239,68,68,.1);color:#f87171;border:1px solid rgba(239,68,68,.2);
  transition:all .25s;
}
.pw-ck.ok {
  background:rgba(16,185,129,.1);color:#34d399;border-color:rgba(16,185,129,.25);
}
</style>

<script>
// ── Modal ──────────────────────────────────────────────────────
function openModal() {
  document.getElementById('addModal').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeModal() {
  document.getElementById('addModal').classList.remove('open');
  document.body.style.overflow = '';
}
document.getElementById('addModal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') { closeModal(); closeLogoutModal(); }
});
<?php if($err): ?>
document.addEventListener('DOMContentLoaded', () => openModal());
<?php endif; ?>

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
// ── Password visibility toggle ─────────────────────────────────
function togglePw(inputId, btnId) {
  const inp = document.getElementById(inputId);
  const ico = document.querySelector('#'+btnId+' i');
  if (inp.type === 'password') {
    inp.type = 'text';
    ico.className = 'fa fa-eye-slash';
  } else {
    inp.type = 'password';
    ico.className = 'fa fa-eye';
  }
}

// ── Password strength checker ──────────────────────────────────
function checkPw(val) {
  const bar   = document.getElementById('pwBar');
  const label = document.getElementById('pwLabel');
  const ckLen = document.getElementById('ck-len');
  const ckUp  = document.getElementById('ck-up');
  const ckNum = document.getElementById('ck-num');
  const ckSym = document.getElementById('ck-sym');

  const hasLen = val.length >= 8;
  const hasUp  = /[A-Z]/.test(val);
  const hasNum = /[0-9]/.test(val);
  const hasSym = /[^A-Za-z0-9]/.test(val);

  setCheck(ckLen, hasLen, 'Min. 8 karakter');
  setCheck(ckUp,  hasUp,  'Huruf kapital');
  setCheck(ckNum, hasNum, 'Angka');
  setCheck(ckSym, hasSym, 'Simbol');

  const score = [hasLen, hasUp, hasNum, hasSym].filter(Boolean).length;
  const colors = ['#f87171','#fbbf24','#fbbf24','#34d399','#22d3ee'];
  const labels = ['','Lemah','Sedang','Kuat','Sangat Kuat'];
  bar.style.width  = (score * 25) + '%';
  bar.style.background = colors[score] || '#f87171';
  label.textContent = val.length ? labels[score] : '';
  label.style.color = colors[score] || 'var(--muted)';

  checkConfirm();
}

function setCheck(el, ok, text) {
  if (ok) {
    el.classList.add('ok');
    el.innerHTML = '<i class="fa fa-circle-check"></i> ' + text;
  } else {
    el.classList.remove('ok');
    el.innerHTML = '<i class="fa fa-circle-xmark"></i> ' + text;
  }
}

function checkConfirm() {
  const pw  = document.getElementById('pwInput').value;
  const con = document.getElementById('pwConfirm').value;
  const msg = document.getElementById('pwMatchMsg');
  if (!con.length) { msg.style.display = 'none'; return; }
  msg.style.display = 'block';
  if (pw === con) {
    msg.innerHTML = '<i class="fa fa-circle-check" style="color:#34d399;margin-right:4px;"></i><span style="color:#34d399;">Password cocok</span>';
    document.getElementById('pwConfirm').style.borderColor = 'rgba(52,211,153,.5)';
  } else {
    msg.innerHTML = '<i class="fa fa-circle-xmark" style="color:#f87171;margin-right:4px;"></i><span style="color:#f87171;">Password tidak cocok</span>';
    document.getElementById('pwConfirm').style.borderColor = 'rgba(248,113,113,.5)';
  }
}

// ── KTP preview ────────────────────────────────────────────────
function previewKtp(input) {
  const file = input.files[0];
  if (!file) return;
  const preview    = document.getElementById('ktpPreview');
  const previewImg = document.getElementById('ktpPreviewImg');
  const previewPdf = document.getElementById('ktpPreviewPdf');
  const pdfName    = document.getElementById('ktpPdfName');
  const dropLabel  = document.getElementById('ktpDropLabel');

  preview.style.display = 'block';
  dropLabel.innerHTML = '<i class="fa fa-check" style="color:#34d399;"></i> File dipilih';

  if (file.type === 'application/pdf') {
    previewImg.style.display = 'none';
    previewPdf.style.display = 'block';
    pdfName.textContent = file.name;
  } else {
    previewImg.style.display = 'block';
    previewPdf.style.display = 'none';
    const reader = new FileReader();
    reader.onload = e => { previewImg.src = e.target.result; };
    reader.readAsDataURL(file);
  }
}

function handleKtpDrop(e) {
  e.preventDefault();
  document.getElementById('ktpDropzone').style.borderColor = 'var(--border)';
  const dt = e.dataTransfer;
  if (dt.files.length) {
    const input = document.getElementById('ktpInput');
    // Transfer files to the input
    const dtTrans = new DataTransfer();
    dtTrans.items.add(dt.files[0]);
    input.files = dtTrans.files;
    previewKtp(input);
  }
}

function clearKtp() {
  document.getElementById('ktpInput').value = '';
  document.getElementById('ktpPreview').style.display = 'none';
  document.getElementById('ktpPreviewImg').src = '';
  document.getElementById('ktpDropLabel').innerHTML = 'Klik atau seret file KTP ke sini<br><span style="font-size:11px;">JPG, PNG, WEBP, PDF</span>';
}

// ── Validate sebelum submit ────────────────────────────────────
document.getElementById('addForm').addEventListener('submit', function(e) {
  const pw  = document.getElementById('pwInput').value;
  const con = document.getElementById('pwConfirm').value;
  if (pw !== con) {
    e.preventDefault();
    alert('Password dan konfirmasi password tidak cocok.');
    document.getElementById('pwConfirm').focus();
    return false;
  }
});
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
