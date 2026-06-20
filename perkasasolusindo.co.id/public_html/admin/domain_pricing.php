<?php
// ============================================================
// admin/domain_pricing.php – Kelola Harga Jual Domain
// ============================================================
require_once '../config.php';
require_once '../auth_check.php';
require_once '../rna_api.php';
requireLevel([1, 2]);

$adminId   = (int)($_SESSION['user_id'] ?? 0);
$adminName = $_SESSION['user_firstname'] ?? 'Admin';

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

$flashMsg  = '';
$flashType = 'success'; // success | error

// ── Handle AJAX: refresh harga modal dari RNA untuk satu TLD ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax_action'] ?? '') === 'refresh_modal') {
    header('Content-Type: application/json');
    $id = (int)($_POST['id'] ?? 0);
    $row = $conn->query("SELECT tld FROM tbldomain_pricing WHERE id=$id")->fetch_assoc();
    if (!$row) { echo json_encode(['ok' => false, 'msg' => 'TLD tidak ditemukan.']); exit; }

    $result = rna_get_price($row['tld']);
    if (!$result['success']) {
        echo json_encode(['ok' => false, 'msg' => 'Gagal ambil harga dari RNA: ' . $result['message']]);
        exit;
    }

    // ⚠️ Struktur response RNA belum terverifikasi — sesuaikan key di bawah
    // ('register'/'price'/dst) setelah endpoint dicek di Swagger.
    $modal = $result['data']['register'] ?? $result['data']['price'] ?? null;
    if ($modal === null) {
        echo json_encode(['ok' => false, 'msg' => 'Response RNA tidak mengandung field harga yang dikenali. Cek format response asli di Swagger lalu sesuaikan rna_api.php.']);
        exit;
    }

    $st = $conn->prepare("UPDATE tbldomain_pricing SET harga_modal=?, modal_updated_at=NOW() WHERE id=?");
    $st->bind_param('di', $modal, $id);
    $st->execute();
    $st->close();

    echo json_encode(['ok' => true, 'harga_modal' => (float)$modal]);
    exit;
}

// ── Handle form: simpan perubahan harga jual / aktif / urutan ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_pricing') {
    $id         = (int)($_POST['id'] ?? 0);
    $hargaJual  = (float)str_replace(['.', ','], ['', '.'], $_POST['harga_jual'] ?? '0');
    $aktif      = isset($_POST['aktif']) ? 1 : 0;
    $urutan     = (int)($_POST['urutan'] ?? 0);

    if ($id > 0 && $hargaJual >= 0) {
        $st = $conn->prepare("UPDATE tbldomain_pricing SET harga_jual=?, aktif=?, urutan=? WHERE id=?");
        $st->bind_param('diii', $hargaJual, $aktif, $urutan, $id);
        $st->execute();
        $st->close();
        $flashMsg = 'Harga domain berhasil diperbarui.';
    } else {
        $flashMsg  = 'Data tidak valid.';
        $flashType = 'error';
    }
    header('Location: /admin/domain_pricing.php?msg=' . urlencode($flashMsg) . '&type=' . $flashType);
    exit;
}

// ── Handle form: tambah TLD baru ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_tld') {
    $tld       = trim($_POST['tld'] ?? '');
    $hargaJual = (float)str_replace(['.', ','], ['', '.'], $_POST['harga_jual_new'] ?? '0');

    if ($tld !== '' && $tld[0] !== '.') $tld = '.' . $tld;
    $tld = strtolower($tld);

    if (preg_match('/^\.[a-z0-9.]+$/', $tld) && $hargaJual >= 0) {
        $maxUrutan = (int)($conn->query("SELECT COALESCE(MAX(urutan),0) FROM tbldomain_pricing")->fetch_row()[0]);
        $st = $conn->prepare("INSERT INTO tbldomain_pricing (tld, harga_jual, aktif, urutan) VALUES (?, ?, 1, ?) ON DUPLICATE KEY UPDATE harga_jual=VALUES(harga_jual)");
        $newUrutan = $maxUrutan + 10;
        $st->bind_param('sdi', $tld, $hargaJual, $newUrutan);
        $st->execute();
        $st->close();
        $flashMsg = "TLD {$tld} berhasil ditambahkan.";
    } else {
        $flashMsg  = 'Format TLD atau harga tidak valid.';
        $flashType = 'error';
    }
    header('Location: /admin/domain_pricing.php?msg=' . urlencode($flashMsg) . '&type=' . $flashType);
    exit;
}

if (isset($_GET['msg'])) {
    $flashMsg  = $_GET['msg'];
    $flashType = ($_GET['type'] ?? 'success') === 'error' ? 'error' : 'success';
}

// ── Ambil semua data harga domain ──
$pricingList = $conn->query("SELECT * FROM tbldomain_pricing ORDER BY urutan ASC, tld ASC")->fetch_all(MYSQLI_ASSOC);

// ── Cek apakah kredensial RNA sudah diisi (bukan placeholder) ──
$rnaConfigured = (RNA_RESELLER_ID !== 'ISI_RESELLER_ID_DISINI' && RNA_API_KEY !== 'ISI_API_KEY_DISINI');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Harga Domain – Perkasa Solusindo Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/admin/style_admin.css">
<style>
.pricing-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.pricing-table th {
  padding: 11px 14px; text-align: left; font-size: 11px; font-weight: 700; color: var(--muted);
  text-transform: uppercase; letter-spacing: .5px; border-bottom: 2px solid var(--border);
  background: var(--surface2); white-space: nowrap;
}
.pricing-table td { padding: 11px 14px; border-bottom: 1px solid var(--border); vertical-align: middle; }
.pricing-table tr:last-child td { border-bottom: none; }
.pricing-table tbody tr:hover { background: rgba(16,185,129,.04); }
.pricing-table input[type=text] {
  background: var(--surface2); border: 1px solid var(--border); color: var(--text);
  padding: 7px 10px; border-radius: 6px; font-size: 13px; font-family: 'JetBrains Mono', monospace;
  width: 130px; outline: none;
}
.pricing-table input[type=text]:focus { border-color: var(--accent2); }
.tld-badge {
  display: inline-block; font-family: 'JetBrains Mono', monospace; font-weight: 700;
  color: #c084fc; background: rgba(124,58,237,.1); padding: 3px 10px; border-radius: 6px; font-size: 12.5px;
}
.modal-price { font-family: 'JetBrains Mono', monospace; color: var(--muted); font-size: 12.5px; }
.modal-price.stale { color: #fbbf24; }
.margin-badge {
  display: inline-block; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 10px;
}
.margin-badge.positive { background: rgba(34,197,94,.12); color: #4ade80; }
.margin-badge.negative { background: rgba(239,68,68,.12); color: #f87171; }
.margin-badge.unknown  { background: rgba(148,163,184,.1); color: #94a3b8; }
.btn-refresh {
  background: rgba(59,130,246,.1); border: 1px solid rgba(59,130,246,.3); color: #60a5fa;
  padding: 5px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; cursor: pointer; white-space: nowrap;
}
.btn-refresh:hover { background: rgba(59,130,246,.18); }
.btn-refresh:disabled { opacity: .5; cursor: not-allowed; }
.toggle-aktif { width: 18px; height: 18px; cursor: pointer; }
.flash-msg { padding: 12px 18px; border-radius: 10px; font-size: 13px; margin-bottom: 18px; display: flex; align-items: center; gap: 10px; }
.flash-msg.success { background: rgba(34,197,94,.1); border: 1px solid rgba(34,197,94,.3); color: #4ade80; }
.flash-msg.error   { background: rgba(239,68,68,.1); border: 1px solid rgba(239,68,68,.3); color: #f87171; }
.rna-status-banner {
  display: flex; align-items: center; gap: 12px; padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 13px;
}
.rna-status-banner.warn { background: rgba(251,191,36,.08); border: 1px solid rgba(251,191,36,.25); color: #fbbf24; }
.add-tld-form { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; padding: 16px 18px; background: var(--surface); border: 1px solid var(--border); border-radius: 12px; margin-bottom: 20px; }
.add-tld-form input { background: var(--surface2); border: 1px solid var(--border); color: var(--text); padding: 8px 12px; border-radius: 8px; font-size: 13px; outline: none; }
</style>
</head>
<body>

<aside class="sidebar">
  <div class="sidebar-brand">
    <div class="logo-mark">
      <div class="logo-icon">P</div>
      <div class="logo-text">Perkasa Solusindo<span>Admin Panel</span></div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <a href="/admin/admin_dashboard.php" class="nav-item"><i class="fa fa-gauge"></i> Dashboard</a>

    <div class="nav-label">Order</div>
    <a href="/admin/orders.php" class="nav-item"><i class="fa fa-list-check"></i> Semua Order</a>
    <a href="/admin/orders_wifi.php" class="nav-item">
      <i class="fa fa-wifi"></i> Order WiFi
      <?php if($wifiPendingNav > 0): ?><span class="nav-badge"><?= $wifiPendingNav ?></span><?php endif; ?>
    </a>
    <a href="/admin/orders_hosting.php" class="nav-item">
      <i class="fa fa-server"></i> Order Hosting
      <?php if($hostingPendingNav > 0): ?><span class="nav-badge"><?= $hostingPendingNav ?></span><?php endif; ?>
    </a>
    <a href="/admin/invoices.php" class="nav-item">
      <i class="fa fa-file-invoice-dollar"></i> Invoice
      <?php if($stats['unpaid'] > 0): ?><span class="nav-badge"><?= $stats['unpaid'] ?></span><?php endif; ?>
    </a>

    <div class="nav-label">Manajemen</div>
    <a href="/admin/products.php" class="nav-item"><i class="fa fa-box-open"></i> Produk Layanan</a>
    <a href="/admin/domain_pricing.php" class="nav-item active"><i class="fa fa-globe"></i> Harga Domain</a>
    <a href="/admin/clients.php" class="nav-item"><i class="fa fa-users"></i> Data Klien</a>
    <a href="/admin/teknisi.php" class="nav-item"><i class="fa fa-screwdriver-wrench"></i> Teknisi</a>

    <div class="nav-label">Support</div>
    <a href="/admin/tickets.php" class="nav-item">
      <i class="fa fa-ticket"></i> Tiket Support
      <?php if($stats['tickets'] > 0): ?><span class="nav-badge"><?= $stats['tickets'] ?></span><?php endif; ?>
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
      <a href="#" class="btn-logout" onclick="confirmLogout(event)" title="Logout"><i class="fa fa-right-from-bracket"></i></a>
    </div>
  </div>
</aside>

<main class="main">

  <div class="topbar">
    <div class="breadcrumb">
      <a href="/admin/admin_dashboard.php">Dashboard</a>
      <i class="fa fa-chevron-right"></i>
      <span>Harga Domain</span>
    </div>
    <div class="topbar-right">
      <span class="date-badge"><i class="fa fa-calendar-days" style="margin-right:6px;"></i><?= date('d M Y') ?></span>
      <a href="#" class="topbar-btn" onclick="confirmLogout(event)" title="Logout"><i class="fa fa-right-from-bracket"></i></a>
    </div>
  </div>

  <div class="content">

    <div style="margin-bottom:24px;">
      <h1 style="font-size:22px;font-weight:800;margin-bottom:6px;display:flex;align-items:center;gap:10px;">
        <span style="width:40px;height:40px;background:rgba(124,58,237,.12);border-radius:10px;display:inline-grid;place-items:center;">
          <i class="fa fa-globe" style="color:#c084fc;font-size:17px;"></i>
        </span>
        Harga Jual Domain
      </h1>
      <p style="font-size:13px;color:var(--muted);">
        Atur harga jual domain ke client. Harga modal disinkronkan dari RNA (RDASH) secara manual lewat tombol Refresh.
      </p>
    </div>

    <?php if ($flashMsg): ?>
    <div class="flash-msg <?= $flashType ?>">
      <i class="fa <?= $flashType === 'error' ? 'fa-circle-exclamation' : 'fa-circle-check' ?>"></i>
      <?= htmlspecialchars($flashMsg) ?>
    </div>
    <?php endif; ?>

    <?php if (!$rnaConfigured): ?>
    <div class="rna-status-banner warn">
      <i class="fa fa-triangle-exclamation"></i>
      <div>
        <strong>Kredensial RNA belum diisi.</strong>
        Harga jual tetap bisa diatur manual di bawah, tapi tombol "Refresh Modal" tidak akan berfungsi sampai
        <code>RNA_RESELLER_ID</code> dan <code>RNA_API_KEY</code> diisi di <code>config.php</code>.
        Generate API Key di Settings → API & Modules pada dashboard RDASH Anda.
      </div>
    </div>
    <?php endif; ?>

    <!-- Form tambah TLD baru -->
    <form method="POST" class="add-tld-form">
      <input type="hidden" name="action" value="add_tld">
      <span style="font-size:13px;font-weight:700;color:var(--text);"><i class="fa fa-plus"></i> Tambah TLD:</span>
      <input type="text" name="tld" placeholder=".com" required style="width:100px;">
      <input type="text" name="harga_jual_new" placeholder="Harga jual, mis. 210000" required style="width:160px;">
      <button type="submit" class="btn btn-primary" style="padding:8px 18px;">Tambah</button>
    </form>

    <div class="card" style="padding:0;overflow:hidden;">
      <div style="overflow-x:auto;">
        <table class="pricing-table">
          <thead>
            <tr>
              <th>TLD</th>
              <th>Harga Modal (RNA)</th>
              <th>Harga Jual</th>
              <th>Margin</th>
              <th>Aktif</th>
              <th>Urutan</th>
              <th style="text-align:center;">Aksi</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($pricingList as $row):
              $modal     = $row['harga_modal'];
              $jual      = (float)$row['harga_jual'];
              $marginPct = ($modal !== null && (float)$modal > 0) ? round((($jual - $modal) / $modal) * 100, 1) : null;
              $isStale   = $row['modal_updated_at'] && strtotime($row['modal_updated_at']) < strtotime('-7 days');
          ?>
            <tr data-id="<?= (int)$row['id'] ?>">
              <td><span class="tld-badge"><?= htmlspecialchars($row['tld']) ?></span></td>
              <td>
                <span class="modal-price <?= $isStale ? 'stale' : '' ?>" id="modal-<?= (int)$row['id'] ?>">
                  <?= $modal !== null ? 'Rp ' . number_format($modal, 0, ',', '.') : '—' ?>
                </span>
                <?php if ($row['modal_updated_at']): ?>
                  <div style="font-size:10px;color:var(--muted);margin-top:2px;">
                    sync <?= date('d M H:i', strtotime($row['modal_updated_at'])) ?>
                  </div>
                <?php endif; ?>
              </td>
              <td>
                <!-- Form tersembunyi di luar <tr>; semua input di baris ini nyambung lewat atribut form="..." (HTML5) -->
                <input type="text" name="harga_jual" form="frm-<?= (int)$row['id'] ?>" value="<?= number_format($jual, 0, ',', '.') ?>">
              </td>
              <td>
                <span id="margin-<?= (int)$row['id'] ?>" class="margin-badge <?= $marginPct === null ? 'unknown' : ($marginPct >= 0 ? 'positive' : 'negative') ?>">
                  <?= $marginPct !== null ? ($marginPct >= 0 ? '+' : '') . $marginPct . '%' : 'belum sync' ?>
                </span>
              </td>
              <td>
                <input type="checkbox" name="aktif" form="frm-<?= (int)$row['id'] ?>" class="toggle-aktif" <?= $row['aktif'] ? 'checked' : '' ?>>
              </td>
              <td>
                <input type="text" name="urutan" form="frm-<?= (int)$row['id'] ?>" value="<?= (int)$row['urutan'] ?>" style="width:60px;">
              </td>
              <td style="text-align:center;white-space:nowrap;">
                <form id="frm-<?= (int)$row['id'] ?>" method="POST" style="display:inline;">
                  <input type="hidden" name="action" value="save_pricing">
                  <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                  <button type="submit" class="btn btn-secondary" style="padding:6px 12px;font-size:12px;">
                    <i class="fa fa-save"></i> Simpan
                  </button>
                </form>
                <button type="button" class="btn-refresh" onclick="refreshModal(<?= (int)$row['id'] ?>, this)" <?= !$rnaConfigured ? 'disabled title="Kredensial RNA belum diisi"' : '' ?>>
                  <i class="fa fa-rotate"></i> Refresh
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</main>

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
      <button onclick="closeLogoutModal()" class="btn btn-secondary" style="min-width:120px;padding:10px 18px;"><i class="fa fa-xmark"></i> Batal</button>
      <a href="/admin/logout.php" class="btn btn-danger" style="min-width:120px;padding:10px 18px;"><i class="fa fa-right-from-bracket"></i> Ya, Logout</a>
    </div>
  </div>
</div>

<script>
function confirmLogout(e) { e.preventDefault(); document.getElementById('logoutModal').style.display='flex'; }
function closeLogoutModal() { document.getElementById('logoutModal').style.display='none'; }
document.getElementById('logoutModal').addEventListener('click', function(e){ if(e.target===this) closeLogoutModal(); });
document.addEventListener('keydown', e=>{ if(e.key==='Escape') closeLogoutModal(); });

function refreshModal(id, btn) {
  btn.disabled = true;
  var originalHtml = btn.innerHTML;
  btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> ...';

  fetch('/admin/domain_pricing.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'ajax_action=refresh_modal&id=' + id
  })
  .then(function(r) { return r.json(); })
  .then(function(data) {
    btn.disabled = false;
    btn.innerHTML = originalHtml;
    if (data.ok) {
      var fmt = new Intl.NumberFormat('id-ID').format(data.harga_modal);
      document.getElementById('modal-' + id).textContent = 'Rp ' + fmt;
      location.reload(); // reload supaya margin & label sync time ikut update
    } else {
      alert('Gagal refresh: ' + data.msg);
    }
  })
  .catch(function(err) {
    btn.disabled = false;
    btn.innerHTML = originalHtml;
    alert('Terjadi kesalahan jaringan: ' + err);
  });
}
</script>
</body>
</html>
