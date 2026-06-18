<?php
// ============================================================
// admin/client_detail.php – Detail Klien Perkasa Solusindo
// ============================================================
require_once '../config.php';
require_once '../auth_check.php';
requireLevel([1, 2]);

$adminId   = (int)($_SESSION['user_id'] ?? 0);
$adminName = $_SESSION['user_firstname'] ?? 'Admin';

// ── Statistik untuk sidebar badge ────────────────────────────
function cntSide($conn, $sql) { $r=$conn->query($sql); return $r?(int)$r->fetch_row()[0]:0; }
$sideStats = [];
$sideStats['unpaid']  = cntSide($conn, "SELECT COUNT(*) FROM tblinvoices WHERE status='Unpaid'");
$sideStats['tickets'] = cntSide($conn, "SELECT COUNT(*) FROM tbltickets WHERE status='Open'");
$totalOrdersPending   = cntSide($conn, "SELECT COUNT(*) FROM tblorders WHERE wifi_status IN ('pending','verified','scheduled')");

// ── Ambil data klien ─────────────────────────────────────────
$clientId = (int)($_GET['id'] ?? 0);
if (!$clientId) { header('Location: /admin/clients.php'); exit; }

$stmt = $conn->prepare("SELECT * FROM tblclients WHERE id = ? AND level = 3 LIMIT 1");
$stmt->bind_param('i', $clientId); $stmt->execute();
$client = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$client) { header('Location: /admin/clients.php'); exit; }

// ── Helper exec ──────────────────────────────────────────────
function mqExec($conn, $sql, $types, ...$vals) {
    $s = $conn->prepare($sql);
    if (!$s) return false;
    if ($types) $s->bind_param($types, ...$vals);
    $ok = $s->execute(); $s->close(); return $ok;
}

// ── Pastikan tabel tambahan ada ──────────────────────────────
$conn->query("ALTER TABLE tblclients ADD COLUMN IF NOT EXISTS notes text DEFAULT NULL AFTER lastupdated");
$conn->query("ALTER TABLE tbltickets ADD COLUMN IF NOT EXISTS body text DEFAULT NULL AFTER subject");
$conn->query("CREATE TABLE IF NOT EXISTS tblticket_replies (
  id int(11) NOT NULL AUTO_INCREMENT,
  ticket_id int(11) NOT NULL,
  userid int(11) NOT NULL,
  sender enum('admin','client') NOT NULL DEFAULT 'client',
  message text NOT NULL,
  created_at datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (id),
  KEY idx_ticket_id (ticket_id),
  KEY idx_userid (userid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
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

// ── POST handlers ────────────────────────────────────────────
$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Toggle status akun klien
    if ($action === 'toggle_status') {
        $newStatus = $client['status'] ? 0 : 1;
        mqExec($conn, "UPDATE tblclients SET status=? WHERE id=?", 'ii', $newStatus, $clientId);
        header("Location: /admin/client_detail.php?id=$clientId&msg=status_updated"); exit;
    }

    // Simpan catatan admin
    if ($action === 'save_notes') {
        $notes = trim($_POST['notes'] ?? '');
        mqExec($conn, "UPDATE tblclients SET notes=? WHERE id=?", 'si', $notes, $clientId);
        header("Location: /admin/client_detail.php?id=$clientId&msg=notes_saved"); exit;
    }

    // Tambah pesanan produk
    if ($action === 'add_order') {
        $productId = (int)($_POST['productid'] ?? 0);
        $note      = trim($_POST['note'] ?? '');
        if ($productId) {
            mqExec($conn, "INSERT INTO tblorders (userid, productid, note) VALUES (?,?,?)", 'iis', $clientId, $productId, $note);
            header("Location: /admin/client_detail.php?id=$clientId&msg=order_added"); exit;
        }
        $err = 'Pilih produk terlebih dahulu.';
    }

    // Update status pesanan
    if ($action === 'update_order') {
        $orderId     = (int)($_POST['order_id'] ?? 0);
        $orderStatus = $_POST['order_status'] ?? 'Active';
        if ($orderId) {
            mqExec($conn, "UPDATE tblorders SET status=? WHERE id=? AND userid=?", 'sii', $orderStatus, $orderId, $clientId);
            header("Location: /admin/client_detail.php?id=$clientId&msg=order_updated"); exit;
        }
    }

    // Hapus pesanan
    if ($action === 'delete_order') {
        $orderId = (int)($_POST['order_id'] ?? 0);
        if ($orderId) {
            mqExec($conn, "DELETE FROM tblorders WHERE id=? AND userid=?", 'ii', $orderId, $clientId);
            header("Location: /admin/client_detail.php?id=$clientId&msg=order_deleted"); exit;
        }
    }

    // Buat tiket baru (oleh admin)
    if ($action === 'create_ticket') {
        $subject = trim($_POST['subject'] ?? '');
        $body    = trim($_POST['body'] ?? '');
        $dept    = trim($_POST['dept'] ?? 'support');
        if ($subject && $body) {
            $tid = strtoupper(substr(md5(uniqid()),0,8));
            mqExec($conn,
                "INSERT INTO tbltickets (tid, c, userid, subject, body, status) VALUES (?,?,?,?,?,'Open')",
                'ssiss', $tid, $dept, $clientId, $subject, $body
            );
            $ticketId = (int)$conn->insert_id;
            // Simpan pesan pertama sebagai reply dari admin
            mqExec($conn,
                "INSERT INTO tblticket_replies (ticket_id, userid, sender, message) VALUES (?,?,?,?)",
                'iiss', $ticketId, $adminId, 'admin', $body
            );
            header("Location: /admin/client_detail.php?id=$clientId&ticket=$ticketId#tickets"); exit;
        }
        $err = 'Subjek dan pesan tiket tidak boleh kosong.';
    }

    // Balas tiket
    if ($action === 'reply_ticket') {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $message  = trim($_POST['message'] ?? '');
        if ($ticketId && $message) {
            mqExec($conn,
                "INSERT INTO tblticket_replies (ticket_id, userid, sender, message) VALUES (?,?,?,?)",
                'iiss', $ticketId, $adminId, 'admin', $message
            );
            // Update status tiket → Answered
            mqExec($conn, "UPDATE tbltickets SET status='Answered', lastreply=NOW() WHERE id=?", 'i', $ticketId);
            header("Location: /admin/client_detail.php?id=$clientId&ticket=$ticketId#tickets"); exit;
        }
        $err = 'Pesan balasan tidak boleh kosong.';
    }

    // Tutup tiket
    if ($action === 'close_ticket') {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        if ($ticketId) {
            mqExec($conn, "UPDATE tbltickets SET status='Closed', lastreply=NOW() WHERE id=? AND userid=?", 'ii', $ticketId, $clientId);
            header("Location: /admin/client_detail.php?id=$clientId#tickets"); exit;
        }
    }

    // Buka ulang tiket
    if ($action === 'reopen_ticket') {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        if ($ticketId) {
            mqExec($conn, "UPDATE tbltickets SET status='Open', lastreply=NOW() WHERE id=? AND userid=?", 'ii', $ticketId, $clientId);
            header("Location: /admin/client_detail.php?id=$clientId&ticket=$ticketId#tickets"); exit;
        }
    }
}

// ── Flash messages ───────────────────────────────────────────
$msgMap = [
    'status_updated' => ['Berhasil mengubah status akun klien.', 'success'],
    'notes_saved'    => ['Catatan berhasil disimpan.', 'success'],
    'order_added'    => ['Pesanan produk berhasil ditambahkan.', 'success'],
    'order_updated'  => ['Status pesanan diperbarui.', 'success'],
    'order_deleted'  => ['Pesanan dihapus.', 'success'],
];
$flashKey  = $_GET['msg'] ?? '';
$flashData = $msgMap[$flashKey] ?? null;

// ── Refresh client data ──────────────────────────────────────
$stmt = $conn->prepare("SELECT * FROM tblclients WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $clientId); $stmt->execute();
$client = $stmt->get_result()->fetch_assoc(); $stmt->close();

// ── Orders (produk dipesan) ──────────────────────────────────
$orders = [];
$r = $conn->prepare(
    "SELECT o.*, p.name AS product_name, p.category, p.price, p.period
     FROM tblorders o
     JOIN tblproducts p ON p.id = o.productid
     WHERE o.userid = ? ORDER BY o.created_at DESC"
);
// Note: tblorders also has order_number and wifi_status columns
$r->bind_param('i', $clientId); $r->execute();
$res = $r->get_result(); while ($row = $res->fetch_assoc()) $orders[] = $row; $r->close();

// ── Semua produk aktif untuk dropdown ───────────────────────
$allProducts = [];
$rp = $conn->query("SELECT id, name, category, price, period FROM tblproducts WHERE status=1 ORDER BY category, name");
if ($rp) { while ($row = $rp->fetch_assoc()) $allProducts[] = $row; }

// ── Invoices ─────────────────────────────────────────────────
$invoices = [];
$ri = $conn->prepare("SELECT * FROM tblinvoices WHERE userid=? ORDER BY created_at DESC");
$ri->bind_param('i', $clientId); $ri->execute();
$res = $ri->get_result(); while ($row = $res->fetch_assoc()) $invoices[] = $row; $ri->close();

// ── Tiket & Balasan ──────────────────────────────────────────
$tickets = [];
$rt = $conn->prepare("SELECT * FROM tbltickets WHERE userid=? ORDER BY lastreply DESC");
$rt->bind_param('i', $clientId); $rt->execute();
$res = $rt->get_result(); while ($row = $res->fetch_assoc()) $tickets[] = $row; $rt->close();

// Ambil balasan per tiket — sertakan level & email untuk info pengirim admin
$replies = [];
foreach ($tickets as $t) {
    $rr = $conn->prepare(
        "SELECT r.*,
                c.firstname, c.lastname, c.email,
                c.level AS sender_level
         FROM tblticket_replies r
         JOIN tblclients c ON c.id = r.userid
         WHERE r.ticket_id = ? ORDER BY r.created_at ASC"
    );
    $rr->bind_param('i', $t['id']); $rr->execute();
    $res = $rr->get_result();
    $replies[$t['id']] = [];
    while ($row = $res->fetch_assoc()) $replies[$t['id']][] = $row;
    $rr->close();
}

// Label role berdasarkan level
$levelLabel = [1 => 'Owner', 2 => 'Admin', 3 => 'Klien'];

// Tiket yang sedang dibuka dari GET
$activeTicket = (int)($_GET['ticket'] ?? (isset($tickets[0]) ? $tickets[0]['id'] : 0));

// ── Category helpers ─────────────────────────────────────────
$catLabels = [
    'wifi'     => 'Provider WiFi',
    'hosting'  => 'Sewa Hosting',
    'website'  => 'Pembuatan Website',
    'komputer' => 'Jual & Pasang Komputer',
    'cctv'     => 'Pemasangan CCTV',
    'other'    => 'Lainnya',
];
$catColors = [
    'wifi'     => '#3b82f6',
    'hosting'  => '#10b981',
    'website'  => '#8b5cf6',
    'komputer' => '#f59e0b',
    'cctv'     => '#ef4444',
    'other'    => '#7d8590',
];
$statusColors = [
    'Open'           => 'badge-yellow',
    'Answered'       => 'badge-blue',
    'Customer-Reply' => 'badge-yellow',
    'Closed'         => 'badge-gray',
];
$orderStatusColors = [
    'Active'    => 'badge-green',
    'Suspended' => 'badge-yellow',
    'Cancelled' => 'badge-red',
    'Completed' => 'badge-blue',
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Detail Klien – <?= htmlspecialchars($client['firstname'].' '.$client['lastname']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="/admin/style_admin.css">
<style>
/* ── Layout ─── */
.detail-grid {
  display: grid;
  grid-template-columns: 300px 1fr;
  gap: 24px;
  align-items: start;
}
@media(max-width:960px){ .detail-grid { grid-template-columns:1fr; } }

.info-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 14px;
  overflow: hidden;
  margin-bottom: 20px;
}
.info-card-header {
  background: var(--surface2);
  padding: 14px 18px;
  border-bottom: 1px solid var(--border);
  font-size: 12px;
  font-weight: 700;
  color: var(--muted);
  letter-spacing: .8px;
  text-transform: uppercase;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.info-card-body { padding: 18px; }

.client-hero {
  text-align: center;
  padding: 28px 20px 20px;
}
.client-hero-avatar {
  width: 64px; height: 64px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--accent2), #8b5cf6);
  display: grid; place-items: center;
  font-size: 22px; font-weight: 800; color: #fff;
  margin: 0 auto 14px;
}
.client-hero-name { font-size: 18px; font-weight: 800; margin-bottom: 4px; }
.client-hero-company { font-size: 12px; color: var(--muted); }

.info-row {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 10px 0;
  border-bottom: 1px solid var(--border);
  font-size: 13px;
}
.info-row:last-child { border-bottom: none; }
.info-row-icon { width: 28px; color: var(--muted); flex-shrink: 0; text-align: center; padding-top: 2px; }
.info-row-label { font-size: 10px; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 2px; }
.info-row-val { font-size: 13px; }

/* ── Tabs ─── */
.tabs-nav {
  display: flex;
  gap: 4px;
  border-bottom: 1px solid var(--border);
  margin-bottom: 20px;
  padding-bottom: 0;
}
.tab-btn {
  padding: 10px 18px;
  font-size: 13px;
  font-weight: 600;
  color: var(--muted);
  border: none;
  background: transparent;
  cursor: pointer;
  border-bottom: 2px solid transparent;
  transition: all .2s;
  font-family: inherit;
  display: flex;
  align-items: center;
  gap: 7px;
}
.tab-btn:hover { color: var(--text); }
.tab-btn.active { color: var(--accent2); border-bottom-color: var(--accent2); }
.tab-content { display: none; }
.tab-content.active { display: block; }

/* ── Orders table ─── */
.table-plain {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}
.table-plain th {
  padding: 10px 14px;
  text-align: left;
  font-size: 11px;
  font-weight: 700;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: .5px;
  border-bottom: 1px solid var(--border);
  background: var(--surface2);
}
.table-plain td {
  padding: 12px 14px;
  border-bottom: 1px solid var(--border);
  vertical-align: middle;
}
.table-plain tr:last-child td { border-bottom: none; }
.table-plain tbody tr:hover { background: rgba(255,255,255,.02); }

/* ── Ticket chat ─── */
.ticket-list { display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px; }
.ticket-item {
  background: var(--surface2);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 14px 16px;
  cursor: pointer;
  transition: all .2s;
}
.ticket-item:hover, .ticket-item.active { border-color: var(--accent2); }
.ticket-item.active { background: rgba(59,130,246,.06); }
.ticket-meta { display: flex; align-items: center; gap: 8px; margin-top: 6px; font-size: 11px; color: var(--muted); }

.chat-window {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 14px;
  display: flex;
  flex-direction: column;
  min-height: 500px;
}
.chat-header {
  padding: 16px 18px;
  border-bottom: 1px solid var(--border);
  background: var(--surface2);
  border-radius: 14px 14px 0 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.chat-messages {
  flex: 1;
  padding: 18px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 14px;
  max-height: 420px;
}
.chat-bubble {
  max-width: 75%;
  padding: 12px 16px;
  border-radius: 12px;
  font-size: 13px;
  line-height: 1.6;
}
.bubble-admin {
  align-self: flex-end;
  background: rgba(59,130,246,.15);
  border: 1px solid rgba(59,130,246,.25);
  border-radius: 12px 12px 2px 12px;
}
.bubble-client {
  align-self: flex-start;
  background: var(--surface2);
  border: 1px solid var(--border);
  border-radius: 12px 12px 12px 2px;
}
.bubble-sender {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .5px;
  margin-bottom: 5px;
  color: var(--muted);
}
.bubble-admin .bubble-sender { color: var(--accent2); text-align: right; }
.bubble-time { font-size: 10px; color: var(--muted); margin-top: 5px; }
.bubble-admin .bubble-time { text-align: right; }

.chat-footer { padding: 14px 18px; border-top: 1px solid var(--border); }
.chat-textarea {
  width: 100%;
  background: var(--surface2);
  border: 1px solid var(--border);
  color: var(--text);
  padding: 12px 14px;
  border-radius: 8px;
  font-size: 13px;
  font-family: inherit;
  resize: vertical;
  min-height: 80px;
  outline: none;
  transition: border-color .2s;
  margin-bottom: 10px;
}
.chat-textarea:focus { border-color: var(--accent2); }

.ticket-chat-layout {
  display: grid;
  grid-template-columns: 280px 1fr;
  gap: 18px;
}
@media(max-width:800px){ .ticket-chat-layout { grid-template-columns:1fr; } }

.form-ctrl {
  background: var(--surface2);
  border: 1px solid var(--border);
  color: var(--text);
  padding: 8px 12px;
  border-radius: 8px;
  font-size: 13px;
  font-family: inherit;
  outline: none;
  width: 100%;
  transition: border-color .2s;
}
.form-ctrl:focus { border-color: var(--accent2); }

.form-grp { margin-bottom: 14px; }
.form-grp label { display: block; font-size: 11px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 6px; }

.section-block {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 14px;
  margin-bottom: 20px;
}
.section-block-header {
  padding: 14px 20px;
  border-bottom: 1px solid var(--border);
  background: var(--surface2);
  border-radius: 14px 14px 0 0;
  font-size: 13px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.section-block-body { padding: 20px; }

.empty-chat {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: var(--muted);
  padding: 40px;
  text-align: center;
}
.empty-chat i { font-size: 40px; opacity: .3; margin-bottom: 12px; }
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
      <?php if($sideStats['unpaid'] > 0): ?>
        <span class="nav-badge"><?= $sideStats['unpaid'] ?></span>
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
      <a href="#" class="btn-logout" onclick="confirmLogout(event)" title="Logout">
        <i class="fa fa-right-from-bracket"></i>
      </a>
    </div>
  </div>
</aside>

<!-- ═══════════ MAIN ═══════════ -->
<main class="main">
  <div class="topbar">
    <div class="page-title" style="display:flex;align-items:center;gap:10px;">
      <a href="/admin/clients.php" class="btn btn-secondary btn-sm" style="font-size:12px;"><i class="fa fa-arrow-left"></i></a>
      <span>Detail Klien</span>
    </div>
    <div class="topbar-right">
      <span class="date-badge"><i class="fa fa-calendar-days" style="margin-right:6px;"></i><?= date('d M Y') ?></span>
      <a href="#" class="topbar-btn" onclick="confirmLogout(event)" title="Logout"><i class="fa fa-right-from-bracket"></i></a>
    </div>
  </div>

  <div class="content">

    <?php if($flashData): ?>
      <div class="alert alert-<?= $flashData[1]==='success'?'success':'danger' ?>" style="margin-bottom:20px;">
        <i class="fa <?= $flashData[1]==='success'?'fa-circle-check':'fa-circle-xmark' ?>"></i>
        <?= $flashData[0] ?>
      </div>
    <?php endif; ?>

    <?php if($err): ?>
      <div class="alert alert-danger" style="margin-bottom:20px;"><i class="fa fa-circle-xmark"></i><?= $err ?></div>
    <?php endif; ?>

    <div class="detail-grid">

      <!-- ══ SIDEBAR KIRI: INFO KLIEN ══ -->
      <div>
        <!-- Avatar & Nama -->
        <div class="info-card">
          <div class="client-hero">
            <div class="client-hero-avatar">
              <?= strtoupper(substr($client['firstname'],0,1).substr($client['lastname'],0,1)) ?>
            </div>
            <div class="client-hero-name"><?= htmlspecialchars($client['firstname'].' '.$client['lastname']) ?></div>
            <?php if($client['companyname']): ?>
              <div class="client-hero-company"><i class="fa fa-building" style="margin-right:4px;"></i><?= htmlspecialchars($client['companyname']) ?></div>
            <?php endif; ?>
            <div style="margin-top:12px;display:flex;justify-content:center;gap:8px;flex-wrap:wrap;">
              <span class="badge <?= $client['status']?'badge-green':'badge-red' ?>" style="font-size:12px;padding:4px 14px;">
                <i class="fa fa-circle" style="font-size:8px;margin-right:5px;"></i>
                <?= $client['status']?'Aktif':'Nonaktif' ?>
              </span>
              <?php if(!$client['email_verified']): ?>
                <span class="badge badge-yellow" style="font-size:12px;padding:4px 14px;"><i class="fa fa-envelope" style="font-size:10px;margin-right:4px;"></i>Belum Verif</span>
              <?php endif; ?>
            </div>
          </div>

          <!-- Info rows -->
          <div class="info-card-body" style="padding-top:0;">
            <div class="info-row">
              <div class="info-row-icon"><i class="fa fa-envelope"></i></div>
              <div><div class="info-row-label">Email</div><div class="info-row-val"><?= htmlspecialchars($client['email']) ?></div></div>
            </div>
            <div class="info-row">
              <div class="info-row-icon"><i class="fa fa-phone"></i></div>
              <div><div class="info-row-label">Telepon</div><div class="info-row-val"><?= htmlspecialchars($client['phonenumber']) ?></div></div>
            </div>
            <div class="info-row">
              <div class="info-row-icon"><i class="fa fa-location-dot"></i></div>
              <div>
                <div class="info-row-label">Alamat</div>
                <div class="info-row-val"><?= htmlspecialchars($client['address1']) ?><?= $client['address2']?', '.htmlspecialchars($client['address2']):'' ?></div>
                <div style="font-size:12px;color:var(--muted);"><?= htmlspecialchars($client['city'].', '.$client['state'].' '.$client['postcode']) ?></div>
              </div>
            </div>
            <div class="info-row">
              <div class="info-row-icon"><i class="fa fa-calendar"></i></div>
              <div><div class="info-row-label">Terdaftar</div><div class="info-row-val"><?= date('d M Y', strtotime($client['datecreated'])) ?></div></div>
            </div>
          </div>

          <!-- Aksi akun -->
          <div style="padding:0 18px 18px;display:flex;flex-direction:column;gap:8px;">
            <form method="POST">
              <input type="hidden" name="action" value="toggle_status">
              <button type="submit" class="btn <?= $client['status']?'btn-danger':'btn-blue' ?> btn-full"
                      onclick="return confirm('<?= $client['status']?'Nonaktifkan akun klien ini?':'Aktifkan kembali akun klien ini?' ?>')">
                <i class="fa <?= $client['status']?'fa-user-slash':'fa-user-check' ?>"></i>
                <?= $client['status']?'Nonaktifkan Akun':'Aktifkan Akun' ?>
              </button>
            </form>
          </div>
        </div>

        <!-- Catatan Admin -->
        <div class="info-card">
          <div class="info-card-header"><i class="fa fa-note-sticky" style="margin-right:6px;color:var(--accent);"></i>Catatan Internal Admin</div>
          <div class="info-card-body">
            <form method="POST">
              <input type="hidden" name="action" value="save_notes">
              <textarea name="notes" class="form-ctrl" rows="5"
                placeholder="Catatan pribadi tentang klien ini (tidak terlihat oleh klien)…"><?= htmlspecialchars($client['notes'] ?? '') ?></textarea>
              <button type="submit" class="btn btn-primary btn-full" style="margin-top:10px;">
                <i class="fa fa-floppy-disk"></i> Simpan Catatan
              </button>
            </form>
          </div>
        </div>

        <!-- Ringkasan Statistik -->
        <div class="info-card">
          <div class="info-card-header"><i class="fa fa-chart-pie" style="margin-right:6px;color:var(--accent3);"></i>Ringkasan</div>
          <div class="info-card-body">
            <div class="info-row">
              <div class="info-row-icon"><i class="fa fa-box-open" style="color:var(--accent2);"></i></div>
              <div><div class="info-row-label">Produk Dipesan</div><div class="info-row-val"><?= count($orders) ?> item</div></div>
            </div>
            <div class="info-row">
              <div class="info-row-icon"><i class="fa fa-file-invoice" style="color:var(--accent);"></i></div>
              <div><div class="info-row-label">Total Invoice</div><div class="info-row-val"><?= count($invoices) ?> invoice</div></div>
            </div>
            <div class="info-row">
              <div class="info-row-icon"><i class="fa fa-ticket" style="color:var(--accent3);"></i></div>
              <div><div class="info-row-label">Tiket Support</div><div class="info-row-val"><?= count($tickets) ?> tiket</div></div>
            </div>
            <?php
              $totalPaid = array_sum(array_column(array_filter($invoices, function($i){ return $i['status']==='Paid'; }), 'total'));
            ?>
            <div class="info-row">
              <div class="info-row-icon"><i class="fa fa-money-bill" style="color:#34d399;"></i></div>
              <div>
                <div class="info-row-label">Total Dibayar</div>
                <div class="info-row-val" style="font-family:'JetBrains Mono',monospace;">Rp <?= number_format($totalPaid,0,',','.') ?></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Foto KTP Client -->
        <?php
          // Foto KTP client tersimpan di kolom `foto_ktp` di tblclients
          // File disimpan di /order/order_asset/ktp/ saat client submit order WiFi
          $clientKtp    = $client['foto_ktp'] ?? null;
          $clientKtpUrl = $clientKtp
              ? '/order/order_asset/ktp/' . htmlspecialchars($clientKtp)
              : null;
          $clientKtpExt = $clientKtp ? strtolower(pathinfo($clientKtp, PATHINFO_EXTENSION)) : '';
        ?>
        <div class="info-card">
          <div class="info-card-header">
            <span><i class="fa fa-id-card" style="margin-right:6px;color:#fbbf24;"></i>Foto KTP Klien</span>
          </div>
          <div class="info-card-body">
            <?php if ($clientKtpUrl): ?>
              <?php if ($clientKtpExt === 'pdf'): ?>
                <div style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;">
                  <i class="fa fa-file-pdf" style="font-size:24px;color:#f87171;"></i>
                  <div style="flex:1;min-width:0;">
                    <div style="font-weight:600;font-size:12px;">Dokumen PDF</div>
                    <div style="font-size:10px;color:var(--muted);word-break:break-all;"><?= htmlspecialchars($clientKtp) ?></div>
                  </div>
                  <a href="<?= $clientKtpUrl ?>" target="_blank" class="btn btn-secondary btn-sm">
                    <i class="fa fa-external-link"></i>
                  </a>
                </div>
              <?php else: ?>
                <a href="<?= $clientKtpUrl ?>" target="_blank">
                  <img src="<?= $clientKtpUrl ?>" alt="KTP <?= htmlspecialchars($client['firstname']) ?>"
                       style="width:100%;max-height:200px;object-fit:contain;border-radius:8px;border:1px solid var(--border);display:block;cursor:zoom-in;"
                       onerror="this.parentElement.innerHTML='<div style=\'text-align:center;padding:20px;color:var(--muted);font-size:12px;\'><i class=\'fa fa-triangle-exclamation\' style=\'display:block;font-size:24px;opacity:.4;margin-bottom:8px;\'></i>File tidak ditemukan</div>'">
                </a>
                <div style="font-size:10px;color:var(--muted);margin-top:6px;text-align:center;">Klik untuk memperbesar</div>
              <?php endif; ?>
            <?php else: ?>
              <div style="text-align:center;padding:20px 0;color:var(--muted);">
                <i class="fa fa-id-card" style="font-size:32px;opacity:.2;display:block;margin-bottom:8px;"></i>
                <span style="font-size:12px;">Belum ada foto KTP.</span>
              </div>
            <?php endif; ?>
          </div>
        </div><!-- /Foto KTP Client info-card -->
      </div><!-- /sidebar kiri -->
      <div>
        <div class="tabs-nav">
          <button class="tab-btn active" onclick="switchTab(event, 'orders')">
            <i class="fa fa-box-open"></i> Produk & Layanan
            <?php if(count($orders)>0): ?><span class="badge badge-blue" style="font-size:10px;padding:1px 7px;"><?= count($orders) ?></span><?php endif; ?>
          </button>
          <button class="tab-btn" onclick="switchTab(event, 'invoices')">
            <i class="fa fa-file-invoice"></i> Invoice
            <?php $unpaidCount = count(array_filter($invoices, function($i){ return $i['status']==='Unpaid'; })); ?>
            <?php if($unpaidCount>0): ?><span class="badge badge-red" style="font-size:10px;padding:1px 7px;"><?= $unpaidCount ?></span><?php endif; ?>
          </button>
          <button class="tab-btn" id="ticketTabBtn" onclick="switchTab(event, 'tickets')">
            <i class="fa fa-ticket"></i> Tiket Support
            <?php $openCount = count(array_filter($tickets, function($t){ return $t['status']!=='Closed'; })); ?>
            <?php if($openCount>0): ?><span class="badge badge-yellow" style="font-size:10px;padding:1px 7px;"><?= $openCount ?></span><?php endif; ?>
          </button>
        </div>

        <!-- ── TAB: ORDERS ── -->
        <div id="tab-orders" class="tab-content active">
          <!-- Form tambah order -->
          <div class="section-block" style="margin-bottom:20px;">
            <div class="section-block-header">
              <span><i class="fa fa-plus" style="color:var(--accent3);margin-right:8px;"></i>Tambah Pesanan Produk</span>
            </div>
            <div class="section-block-body">
              <form method="POST" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
                <input type="hidden" name="action" value="add_order">
                <div class="form-grp" style="flex:1;min-width:220px;">
                  <label>Pilih Produk</label>
                  <select name="productid" class="form-ctrl" required>
                    <option value="">— Pilih Produk —</option>
                    <?php
                      $grouped = [];
                      foreach($allProducts as $p) $grouped[$p['category']][] = $p;
                      foreach($grouped as $cat => $plist):
                    ?>
                      <optgroup label="<?= htmlspecialchars($catLabels[$cat]??$cat) ?>">
                        <?php foreach($plist as $p): ?>
                          <option value="<?= $p['id'] ?>">
                            <?= htmlspecialchars($p['name']) ?> – Rp <?= number_format($p['price'],0,',','.') ?>/<?= $p['period'] ?>
                          </option>
                        <?php endforeach; ?>
                      </optgroup>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-grp" style="flex:1;min-width:180px;">
                  <label>Catatan (opsional)</label>
                  <input type="text" name="note" class="form-ctrl" placeholder="Cth: mulai bulan ini">
                </div>
                <div class="form-grp">
                  <button type="submit" class="btn btn-primary"><i class="fa fa-plus"></i> Tambah</button>
                </div>
              </form>
            </div>
          </div>

          <!-- Daftar orders -->
          <div class="section-block">
            <div class="section-block-header">
              <span><i class="fa fa-box-open" style="color:var(--accent2);margin-right:8px;"></i>Riwayat Pesanan</span>
              <span style="font-size:12px;color:var(--muted);"><?= count($orders) ?> item</span>
            </div>
            <?php if(empty($orders)): ?>
              <div class="empty-state" style="padding:40px;"><i class="fa fa-box-open"></i><p>Belum ada pesanan produk.</p></div>
            <?php else: ?>
              <div style="overflow-x:auto;">
                <table class="table-plain">
                  <thead>
                    <tr>
                      <th>Produk / No. Order</th>
                      <th>Kategori</th>
                      <th>Harga</th>
                      <th>Status Layanan</th>
                      <th>Sejak</th>
                      <th>Aksi</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                    $wifiStatusMap = [
                        'pending'   => ['badge-yellow', 'fa-hourglass-half',    'Menunggu'],
                        'verified'  => ['badge-blue',   'fa-circle-check',      'Diverifikasi'],
                        'scheduled' => ['badge-indigo', 'fa-calendar-check',    'Dijadwalkan'],
                        'installed' => ['badge-green',  'fa-screwdriver-wrench','Terpasang'],
                        'active'    => ['badge-green',  'fa-wifi',              'Aktif'],
                        'cancelled' => ['badge-red',    'fa-ban',               'Dibatalkan'],
                    ];
                    foreach($orders as $ord):
                        $wb = $wifiStatusMap[$ord['wifi_status'] ?? ''] ?? ['badge-gray','fa-circle','–'];
                    ?>
                    <tr style="cursor:pointer;transition:background .15s;"
                        onclick="window.location='/admin/order_detail.php?id=<?= $ord['id'] ?>'"
                        onmouseover="this.style.background='rgba(59,130,246,.04)'"
                        onmouseout="this.style.background=''">
                      <td>
                        <?php if($ord['order_number'] ?? ''): ?>
                          <div style="font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--accent2);font-weight:600;margin-bottom:3px;"><?= htmlspecialchars($ord['order_number']) ?></div>
                        <?php endif; ?>
                        <div style="font-weight:600;"><?= htmlspecialchars($ord['product_name']) ?></div>
                        <?php if($ord['note']): ?><div style="font-size:11px;color:var(--muted);"><?= htmlspecialchars($ord['note']) ?></div><?php endif; ?>
                      </td>
                      <td>
                        <span style="font-size:12px;color:<?= $catColors[$ord['category']] ?? '#7d8590' ?>;">
                          <?= $catLabels[$ord['category']] ?? $ord['category'] ?>
                        </span>
                      </td>
                      <td style="font-family:'JetBrains Mono',monospace;font-size:12px;">
                        Rp <?= number_format($ord['price'],0,',','.') ?>/<?= $ord['period'] ?>
                      </td>
                      <td>
                        <span class="badge <?= $wb[0] ?>">
                          <i class="fa <?= $wb[1] ?>" style="margin-right:4px;font-size:10px;"></i>
                          <?= $wb[2] ?>
                        </span>
                      </td>
                      <td style="font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--muted);">
                        <?= date('d M Y', strtotime($ord['created_at'])) ?>
                      </td>
                      <td onclick="event.stopPropagation();">
                        <a href="/admin/order_detail.php?id=<?= $ord['id'] ?>" class="btn btn-primary btn-sm">
                          <i class="fa fa-eye"></i> Detail
                        </a>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- ── TAB: INVOICES ── -->
        <div id="tab-invoices" class="tab-content">
          <div class="section-block">
            <div class="section-block-header">
              <span><i class="fa fa-file-invoice" style="color:var(--accent);margin-right:8px;"></i>Invoice</span>
              <span style="font-size:12px;color:var(--muted);"><?= count($invoices) ?> total</span>
            </div>
            <?php if(empty($invoices)): ?>
              <div class="empty-state" style="padding:40px;"><i class="fa fa-file-invoice"></i><p>Belum ada invoice.</p></div>
            <?php else: ?>
              <div style="overflow-x:auto;">
                <table class="table-plain">
                  <thead><tr><th>#</th><th>Total</th><th>Status</th><th>Jatuh Tempo</th><th>Dibuat</th></tr></thead>
                  <tbody>
                    <?php foreach($invoices as $inv):
                      $bc = ['Paid'=>'badge-green','Unpaid'=>'badge-yellow','Cancelled'=>'badge-red'][$inv['status']] ?? 'badge-gray';
                    ?>
                    <tr>
                      <td style="font-family:'JetBrains Mono',monospace;font-size:12px;color:var(--muted);">#<?= $inv['id'] ?></td>
                      <td style="font-family:'JetBrains Mono',monospace;font-size:13px;font-weight:600;">Rp <?= number_format($inv['total'],0,',','.') ?></td>
                      <td><span class="badge <?= $bc ?>"><?= $inv['status'] ?></span></td>
                      <td style="font-size:12px;color:var(--muted);"><?= $inv['duedate'] ? date('d M Y',strtotime($inv['duedate'])) : '–' ?></td>
                      <td style="font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--muted);"><?= date('d M Y',strtotime($inv['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- ── TAB: TICKETS ── -->
        <div id="tab-tickets" class="tab-content">

          <!-- Form buat tiket baru -->
          <div class="section-block" style="margin-bottom:20px;">
            <div class="section-block-header">
              <span><i class="fa fa-plus" style="color:var(--accent3);margin-right:8px;"></i>Buat Tiket Baru</span>
              <button class="btn btn-secondary btn-sm" onclick="toggleNewTicketForm()"><i class="fa fa-chevron-down" id="newTicketChevron"></i></button>
            </div>
            <div id="newTicketForm" style="display:none;">
              <div class="section-block-body">
                <form method="POST">
                  <input type="hidden" name="action" value="create_ticket">
                  <div style="display:flex;gap:12px;flex-wrap:wrap;">
                    <div class="form-grp" style="flex:1;min-width:200px;">
                      <label>Subjek Tiket</label>
                      <input type="text" name="subject" class="form-ctrl" placeholder="Cth: Koneksi internet tidak stabil" required>
                    </div>
                    <div class="form-grp" style="min-width:140px;">
                      <label>Departemen</label>
                      <select name="dept" class="form-ctrl">
                        <option value="support">Support</option>
                        <option value="billing">Billing</option>
                        <option value="sales">Sales</option>
                        <option value="technical">Technical</option>
                      </select>
                    </div>
                  </div>
                  <div class="form-grp">
                    <label>Pesan</label>
                    <textarea name="body" class="form-ctrl" rows="4" placeholder="Tuliskan pesan atau laporan…" required></textarea>
                  </div>
                  <button type="submit" class="btn btn-primary"><i class="fa fa-paper-plane"></i> Kirim Tiket</button>
                </form>
              </div>
            </div>
          </div>

          <!-- Layout: daftar tiket + chat -->
          <?php if(empty($tickets)): ?>
            <div class="section-block">
              <div class="empty-state" style="padding:60px;"><i class="fa fa-ticket"></i><p>Belum ada tiket support dari klien ini.</p></div>
            </div>
          <?php else: ?>
          <div class="ticket-chat-layout">
            <!-- Daftar tiket -->
            <div>
              <div class="ticket-list">
                <?php foreach($tickets as $t): ?>
                <div class="ticket-item <?= $t['id']==$activeTicket?'active':'' ?>"
                     onclick="window.location='/admin/client_detail.php?id=<?= $clientId ?>&ticket=<?= $t['id'] ?>#tickets'">
                  <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                    <span style="font-size:13px;font-weight:600;flex:1;line-height:1.4;"><?= htmlspecialchars($t['subject']) ?></span>
                    <span class="badge <?= $statusColors[$t['status']]??'badge-gray' ?>" style="white-space:nowrap;font-size:10px;">
                      <?= $t['status'] ?>
                    </span>
                  </div>
                  <div class="ticket-meta">
                    <i class="fa fa-clock"></i>
                    <?= date('d M Y H:i',strtotime($t['lastreply'])) ?>
                    &nbsp;·&nbsp;
                    <span style="font-family:'JetBrains Mono',monospace;font-size:10px;"><?= $t['tid'] ?></span>
                    <?php $repCount = count($replies[$t['id']]??[]); ?>
                    &nbsp;·&nbsp; <i class="fa fa-comment"></i> <?= $repCount ?>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- Chat window -->
            <div>
              <?php
                $selectedTicket = null;
                foreach($tickets as $t) if($t['id']==$activeTicket) { $selectedTicket=$t; break; }
              ?>
              <?php if($selectedTicket): ?>
              <div class="chat-window">
                <div class="chat-header">
                  <div>
                    <div style="font-weight:700;font-size:14px;"><?= htmlspecialchars($selectedTicket['subject']) ?></div>
                    <div style="font-size:11px;color:var(--muted);margin-top:3px;">
                      <span style="font-family:'JetBrains Mono',monospace;"><?= $selectedTicket['tid'] ?></span>
                      &nbsp;·&nbsp; <?= strtoupper($selectedTicket['c'] ?: 'support') ?>
                      &nbsp;·&nbsp; Dibuat: <?= date('d M Y',strtotime($selectedTicket['created_at'])) ?>
                    </div>
                  </div>
                  <div style="display:flex;gap:8px;align-items:center;">
                    <span class="badge <?= $statusColors[$selectedTicket['status']]??'badge-gray' ?>" style="font-size:12px;padding:4px 12px;">
                      <?= $selectedTicket['status'] ?>
                    </span>
                    <?php if($selectedTicket['status'] !== 'Closed'): ?>
                      <form method="POST">
                        <input type="hidden" name="action" value="close_ticket">
                        <input type="hidden" name="ticket_id" value="<?= $selectedTicket['id'] ?>">
                        <button type="submit" class="btn btn-secondary btn-sm"
                                onclick="return confirm('Tutup tiket ini? Klien tidak dapat membalas setelah ditutup.')"
                                title="Tutup Tiket">
                          <i class="fa fa-lock"></i> Tutup Kasus
                        </button>
                      </form>
                    <?php else: ?>
                      <form method="POST">
                        <input type="hidden" name="action" value="reopen_ticket">
                        <input type="hidden" name="ticket_id" value="<?= $selectedTicket['id'] ?>">
                        <button type="submit" class="btn btn-blue btn-sm" title="Buka Ulang Tiket">
                          <i class="fa fa-lock-open"></i> Buka Ulang
                        </button>
                      </form>
                    <?php endif; ?>
                  </div>
                </div>

                <!-- Pesan -->
                <div class="chat-messages" id="chatMessages">
                  <?php
                    $ticketReplies = $replies[$selectedTicket['id']] ?? [];
                    // Tampilkan body tiket pertama jika ada dan replies kosong
                    if(empty($ticketReplies) && !empty($selectedTicket['body'])):
                  ?>
                    <div class="chat-bubble bubble-client">
                      <div class="bubble-sender"><?= htmlspecialchars($client['firstname']) ?> (Klien)</div>
                      <?= nl2br(htmlspecialchars($selectedTicket['body'])) ?>
                      <div class="bubble-time"><?= date('d M Y H:i',strtotime($selectedTicket['created_at'])) ?></div>
                    </div>
                  <?php elseif(empty($ticketReplies)): ?>
                    <div class="empty-chat">
                      <i class="fa fa-comment-dots"></i>
                      <p>Belum ada percakapan.</p>
                    </div>
                  <?php else: ?>
                    <?php foreach($ticketReplies as $rep):
                      $isAdmin    = $rep['sender'] === 'admin';
                      $senderLvl  = (int)($rep['sender_level'] ?? 3);
                      $roleLabel  = $levelLabel[$senderLvl] ?? 'Staff';
                      $fullName   = htmlspecialchars($rep['firstname'].' '.$rep['lastname']);
                      $senderId   = (int)$rep['userid'];
                      // Warna badge role (kompatibel PHP 7)
                      $roleBadgeMap = [
                          1 => 'background:rgba(245,158,11,.18);color:#fbbf24;',
                          2 => 'background:rgba(59,130,246,.18);color:#60a5fa;',
                          3 => 'background:rgba(125,133,144,.14);color:#9ca3af;',
                      ];
                      $roleBadgeColor = isset($roleBadgeMap[$senderLvl])
                          ? $roleBadgeMap[$senderLvl]
                          : 'background:rgba(125,133,144,.14);color:#9ca3af;';
                    ?>
                    <div class="chat-bubble <?= $isAdmin?'bubble-admin':'bubble-client' ?>" style="position:relative;">
                      <!-- Baris pengirim: nama + role badge + ID -->
                      <div class="bubble-sender" style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;<?= $isAdmin?'justify-content:flex-end;':'' ?>">
                        <?php if($isAdmin): ?>
                          <!-- Admin: tampilkan ID dulu, lalu badge role, lalu nama -->
                          <span style="font-family:'JetBrains Mono',monospace;font-size:9px;color:var(--muted);">ID#<?= $senderId ?></span>
                          <span style="display:inline-flex;align-items:center;gap:3px;padding:1px 8px;border-radius:20px;font-size:9px;font-weight:700;letter-spacing:.4px;<?= $roleBadgeColor ?>">
                            <i class="fa fa-shield-halved" style="font-size:8px;"></i> <?= $roleLabel ?>
                          </span>
                          <span style="font-weight:700;"><?= $fullName ?></span>
                        <?php else: ?>
                          <!-- Klien: nama, badge Klien, ID -->
                          <span style="font-weight:700;"><?= $fullName ?></span>
                          <span style="display:inline-flex;align-items:center;gap:3px;padding:1px 8px;border-radius:20px;font-size:9px;font-weight:700;letter-spacing:.4px;<?= $roleBadgeColor ?>">
                            <i class="fa fa-user" style="font-size:8px;"></i> <?= $roleLabel ?>
                          </span>
                          <span style="font-family:'JetBrains Mono',monospace;font-size:9px;color:var(--muted);">ID#<?= $senderId ?></span>
                        <?php endif; ?>
                      </div>
                      <?= nl2br(htmlspecialchars($rep['message'])) ?>
                      <div class="bubble-time"><?= date('d M Y H:i', strtotime($rep['created_at'])) ?></div>
                    </div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>

                <!-- Form balas -->
                <?php if($selectedTicket['status'] !== 'Closed'): ?>
                <div class="chat-footer">
                  <!-- Info pengirim aktif -->
                  <?php
                    $myLevel = (int)($_SESSION['user_level'] ?? 2);
                    $myRoleLabel = [1=>'Owner',2=>'Admin'][$myLevel] ?? 'Admin';
                    $myRoleColor = $myLevel===1
                      ? 'background:rgba(245,158,11,.18);color:#fbbf24;'
                      : 'background:rgba(59,130,246,.18);color:#60a5fa;';
                  ?>
                  <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;padding:8px 12px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;font-size:12px;">
                    <div style="width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,var(--accent2),#8b5cf6);display:grid;place-items:center;font-size:11px;font-weight:700;color:#fff;flex-shrink:0;">
                      <?= strtoupper(substr($adminName,0,1)) ?>
                    </div>
                    <span style="color:var(--muted);">Membalas sebagai</span>
                    <strong><?= htmlspecialchars($adminName) ?></strong>
                    <span style="display:inline-flex;align-items:center;gap:3px;padding:1px 8px;border-radius:20px;font-size:10px;font-weight:700;<?= $myRoleColor ?>">
                      <i class="fa fa-shield-halved" style="font-size:9px;"></i> <?= $myRoleLabel ?>
                    </span>
                    <span style="font-family:'JetBrains Mono',monospace;font-size:10px;color:var(--muted);">ID#<?= $adminId ?></span>
                  </div>
                  <form method="POST">
                    <input type="hidden" name="action" value="reply_ticket">
                    <input type="hidden" name="ticket_id" value="<?= $selectedTicket['id'] ?>">
                    <textarea name="message" class="chat-textarea" placeholder="Tulis balasan…" required></textarea>
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                      <span style="font-size:11px;color:var(--muted);">Status akan berubah ke <strong>Answered</strong> setelah dikirim.</span>
                      <button type="submit" class="btn btn-blue">
                        <i class="fa fa-paper-plane"></i> Kirim Balasan
                      </button>
                    </div>
                  </form>
                </div>
                <?php else: ?>
                <div class="chat-footer" style="text-align:center;padding:20px;">
                  <span style="font-size:13px;color:var(--muted);"><i class="fa fa-lock" style="margin-right:6px;"></i>Tiket ini sudah ditutup. Buka ulang untuk membalas.</span>
                </div>
                <?php endif; ?>
              </div>
              <?php else: ?>
                <div class="chat-window">
                  <div class="empty-chat">
                    <i class="fa fa-comment-dots"></i>
                    <p>Pilih tiket untuk melihat percakapan.</p>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          </div>
          <?php endif; ?>

        </div><!-- /tab-tickets -->

      </div><!-- /right column -->
    </div><!-- /detail-grid -->
  </div><!-- /content -->
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
      <button onclick="closeLogoutModal()" class="btn btn-secondary" style="min-width:110px;"><i class="fa fa-xmark"></i> Batal</button>
      <a href="/admin/logout.php" class="btn btn-danger" style="min-width:110px;"><i class="fa fa-right-from-bracket"></i> Ya, Logout</a>
    </div>
  </div>
</div>

<script>
// ── Tab switching ────────────────────────────────────────────
function switchTab(e, name) {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
  document.getElementById('tab-'+name).classList.add('active');
  e.currentTarget.classList.add('active');
}

// ── Auto-open ticket tab from URL ────────────────────────────
(function(){
  const params = new URLSearchParams(window.location.search);
  if (params.has('ticket') || window.location.hash === '#tickets') {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.getElementById('tab-tickets').classList.add('active');
    document.getElementById('ticketTabBtn').classList.add('active');
  }
})();

// ── Auto-scroll chat to bottom ───────────────────────────────
(function(){
  const cm = document.getElementById('chatMessages');
  if (cm) cm.scrollTop = cm.scrollHeight;
})();

// ── Toggle new ticket form ───────────────────────────────────
function toggleNewTicketForm() {
  const f = document.getElementById('newTicketForm');
  const chevron = document.getElementById('newTicketChevron');
  const isOpen = f.style.display !== 'none';
  f.style.display = isOpen ? 'none' : 'block';
  chevron.className = isOpen ? 'fa fa-chevron-down' : 'fa fa-chevron-up';
}

// ── Logout modal ─────────────────────────────────────────────
function toggleSubMenu(e, groupId) {
  const group = document.getElementById(groupId);
  if (!group) return;
  const isOpen = group.classList.contains('open');
  if (isOpen) { e.preventDefault(); group.classList.remove('open'); e.currentTarget.classList.remove('expanded'); }
  else { group.classList.add('open'); e.currentTarget.classList.add('expanded'); }
}
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
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closeLogoutModal();
});
</script>
</body>
</html>
