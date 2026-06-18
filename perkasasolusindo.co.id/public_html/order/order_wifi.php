<?php
/**
 * Perkasa Solusindo — Halaman Order WiFi
 * Path  : /public_html/order/order_wifi.php
 *
 * Mode A — User SUDAH LOGIN  : Langsung tampilkan form KTP + alamat saja.
 *                               Akun sudah ada, tidak perlu registrasi ulang.
 * Mode B — User BELUM LOGIN  : Form lengkap (registrasi + data diri + alamat).
 */

// ── Auth & Config ─────────────────────────────────────────────────────────────
require_once dirname(__DIR__) . '/auth_check.php';   // session_start() di sini
require_once dirname(__DIR__) . '/config.php';        // $conn = mysqli object

// Halaman ini hanya untuk client (level 3) atau guest (belum login).
// Owner (1), Admin (2), Teknisi (4) diarahkan ke dashboard masing-masing.
$is_logged_in = !empty($_SESSION['user_id']);

// Level 1=Owner, 2=Admin, 4=Teknisi → tidak boleh akses halaman order.
// Fungsi dashboardUrl() tersedia dari auth_check.php.
if ($is_logged_in && (int)($_SESSION['user_level'] ?? 0) !== 3) {
    header('Location: ' . dashboardUrl($_SESSION['user_level'] ?? 1));
    exit;
}

$logged_user = null;

if ($is_logged_in) {
    // Ambil data akun yang sudah login untuk ditampilkan di halaman
    $st = $conn->prepare(
        "SELECT id, firstname, lastname, email, phonenumber,
                nik, foto_ktp, address1, city, state, postcode
         FROM tblclients WHERE id = ? AND status = 1 LIMIT 1"
    );
    $st->bind_param('i', $_SESSION['user_id']);
    $st->execute();
    $logged_user = $st->get_result()->fetch_assoc();
    $st->close();

    // Kalau akun tidak aktif / tidak ditemukan, paksa logout
    if (!$logged_user) {
        session_destroy();
        header('Location: order_wifi.php?paket_id=' . (int)($_GET['paket_id'] ?? 0));
        exit;
    }
}

// ── Ambil paket yang dipilih dari GET ?paket_id=X ────────────────────────────
$paket_id = isset($_GET['paket_id']) ? (int)$_GET['paket_id'] : 0;
$paket    = null;

if ($paket_id) {
    $stmt = $conn->prepare(
        "SELECT * FROM tblproducts
         WHERE id = ? AND status = 1 AND category = 'wifi' AND ready_to_sell = 1
         LIMIT 1"
    );
    $stmt->bind_param('i', $paket_id);
    $stmt->execute();
    $paket = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$paket) {
    header('Location: /index.php#services');
    exit;
}

// ── Semua paket wifi lain (tombol "Ganti Paket") ─────────────────────────────
$semua_paket = [];
$sp = $conn->query(
    "SELECT id, name, speed, price FROM tblproducts
     WHERE status=1 AND category='wifi' AND ready_to_sell=1
     ORDER BY price ASC"
);
while ($row = $sp->fetch_assoc()) $semua_paket[] = $row;

// ── Flash error & old input dari process_order.php ───────────────────────────
$err = $_SESSION['order_error'] ?? null;
$old = $_SESSION['order_old']   ?? [];
unset($_SESSION['order_error'], $_SESSION['order_old']);

// ── Helper ───────────────────────────────────────────────────────────────────
function rupiah(float $n): string {
    return 'Rp ' . number_format($n, 0, ',', '.');
}
function old(string $key, array $old, string $default = ''): string {
    return htmlspecialchars($old[$key] ?? $default, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Order WiFi — Perkasa Solusindo</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,300&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" crossorigin="anonymous">

<style>
/* ═══════════════════════════════════════════════════════
   PERKASA — WiFi Order Page  (Dark Tech Aesthetic)
═══════════════════════════════════════════════════════ */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}

:root{
  --bg:        #0b0f1a;
  --bg2:       #111827;
  --bg3:       #1a2235;
  --border:    rgba(255,255,255,.08);
  --accent:    #f97316;
  --accent2:   #fb923c;
  --blue:      #3b82f6;
  --green:     #22c55e;
  --purple:    #a78bfa;
  --text1:     #f1f5f9;
  --text2:     #94a3b8;
  --text3:     #64748b;
  --font-head: 'Syne', sans-serif;
  --font-body: 'DM Sans', sans-serif;
  --radius:    14px;
  --radius-sm: 8px;
}

html{scroll-behavior:smooth}
body{background:var(--bg);color:var(--text1);font-family:var(--font-body);font-size:15px;line-height:1.6;min-height:100vh}

/* ── HEADER ── */
.top-bar{background:var(--bg2);border-bottom:1px solid var(--border);padding:14px 0}
.top-bar .inner{max-width:1180px;margin:0 auto;padding:0 24px;display:flex;align-items:center;justify-content:space-between;gap:16px}
.logo-wrap{display:flex;align-items:center;gap:10px;text-decoration:none}
.logo-wrap img{height:36px;width:auto}
.logo-text{font-family:var(--font-head);font-weight:800;font-size:17px;color:var(--text1)}
.logo-text span{color:var(--accent)}
.logo-sub{font-size:11px;color:var(--text3);display:block;margin-top:-3px}
.top-bar-right{display:flex;align-items:center;gap:10px}
.btn-login{font-size:13px;color:var(--text2);text-decoration:none;border:1px solid var(--border);padding:7px 16px;border-radius:99px;transition:.2s}
.btn-login:hover{color:var(--text1);border-color:var(--accent)}

/* User badge (mode login) */
.user-badge{display:flex;align-items:center;gap:8px;background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.2);border-radius:99px;padding:5px 14px 5px 8px}
.user-badge-avatar{width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--purple));display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#fff;flex-shrink:0}
.user-badge-name{font-size:13px;font-weight:500;color:var(--green)}
.user-badge-sep{color:var(--border);margin:0 4px}
.user-badge-logout{font-size:12px;color:var(--text3);text-decoration:none;transition:.2s}
.user-badge-logout:hover{color:#f87171}

/* ── LAYOUT ── */
.page-wrap{max-width:1180px;margin:0 auto;padding:40px 24px 80px}

/* Steps */
.steps-nav{display:flex;align-items:center;gap:6px;margin-bottom:36px;flex-wrap:wrap}
.step-item{display:flex;align-items:center;gap:6px;font-size:13px;color:var(--text3)}
.step-item.active{color:var(--accent)}
.step-item.done{color:var(--green)}
.step-num{width:26px;height:26px;border-radius:50%;border:1px solid currentColor;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700}
.step-item.done .step-num{background:var(--green);border-color:var(--green);color:#000}
.step-item.active .step-num{background:var(--accent);border-color:var(--accent);color:#fff}
.step-arrow{color:var(--text3);font-size:11px;margin:0 4px}

/* ── MAIN GRID ── */
.main-grid{display:grid;grid-template-columns:1fr 360px;gap:32px;align-items:start}
@media(max-width:900px){.main-grid{grid-template-columns:1fr}}

/* ── PANEL ── */
.panel{background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius);padding:28px;margin-bottom:28px}
.panel-title{font-family:var(--font-head);font-size:16px;font-weight:700;margin-bottom:20px;display:flex;align-items:center;gap:10px}
.panel-title i{color:var(--accent);font-size:15px}

/* ── LOGGED-IN ACCOUNT PANEL ── */
.account-panel{background:var(--bg2);border:1px solid rgba(34,197,94,.2);border-radius:var(--radius);padding:20px 24px;margin-bottom:28px;display:flex;align-items:center;gap:18px;flex-wrap:wrap}
.account-avatar{width:52px;height:52px;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--purple));display:flex;align-items:center;justify-content:center;font-family:var(--font-head);font-size:20px;font-weight:800;color:#fff;flex-shrink:0}
.account-info{flex:1;min-width:0}
.account-name{font-family:var(--font-head);font-size:17px;font-weight:700;color:var(--text1)}
.account-email{font-size:13px;color:var(--text3);margin-top:1px}
.account-status{display:inline-flex;align-items:center;gap:5px;background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.25);border-radius:99px;padding:2px 10px;font-size:11px;font-weight:600;color:var(--green);margin-top:5px}
.account-status i{font-size:9px}
.account-actions{display:flex;gap:8px;flex-shrink:0}
.btn-ghost{font-size:12px;color:var(--text3);text-decoration:none;border:1px solid var(--border);padding:6px 13px;border-radius:var(--radius-sm);transition:.2s;background:transparent;cursor:pointer}
.btn-ghost:hover{color:var(--text1);border-color:var(--text3)}

/* ── FORM ── */
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px}
@media(max-width:600px){.form-row{grid-template-columns:1fr}}
.fg{margin-bottom:16px}
.fg label{display:block;font-size:12px;font-weight:600;color:var(--text2);text-transform:uppercase;letter-spacing:.6px;margin-bottom:6px}
.fg label span.req{color:var(--accent);margin-left:2px}
.fg input,.fg select,.fg textarea{
  width:100%;background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius-sm);
  padding:11px 14px;color:var(--text1);font-family:var(--font-body);font-size:14px;
  transition:border-color .2s,box-shadow .2s;outline:none;
}
.fg input[readonly]{opacity:.6;cursor:default}
.fg input::placeholder,.fg textarea::placeholder{color:var(--text3)}
.fg input:focus:not([readonly]),.fg select:focus,.fg textarea:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(249,115,22,.12)}
.fg select option{background:var(--bg3)}
.fg textarea{resize:vertical;min-height:80px}
.fg-hint{font-size:11px;color:var(--text3);margin-top:4px}
.fg-inline{display:flex;gap:10px}
.fg-inline .fg{flex:1}

/* ── KTP Upload ── */
.ktp-upload-area{border:2px dashed var(--border);border-radius:var(--radius-sm);padding:24px;text-align:center;cursor:pointer;transition:border-color .2s,background .2s;position:relative}
.ktp-upload-area:hover,.ktp-upload-area.dragover{border-color:var(--accent);background:rgba(249,115,22,.04)}
.ktp-upload-area input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%}
.ktp-icon{font-size:32px;margin-bottom:8px}
.ktp-label{font-size:14px;color:var(--text2)}
.ktp-sub{font-size:12px;color:var(--text3);margin-top:4px}
.ktp-preview{display:none;margin-top:12px;position:relative}
.ktp-preview img{width:100%;max-height:180px;object-fit:cover;border-radius:var(--radius-sm);border:1px solid var(--border)}
.ktp-remove{position:absolute;top:6px;right:6px;background:rgba(0,0,0,.7);border:none;border-radius:50%;width:26px;height:26px;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:12px}
/* KTP already uploaded indicator */
.ktp-already{background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.2);border-radius:var(--radius-sm);padding:12px 14px;font-size:13px;color:var(--green);display:flex;align-items:center;gap:8px;margin-bottom:12px}
.ktp-already i{font-size:15px;flex-shrink:0}

/* ── Checkbox ── */
.checkbox-row{display:flex;align-items:flex-start;gap:10px;margin-bottom:12px}
.checkbox-row input[type=checkbox]{width:18px;height:18px;accent-color:var(--accent);margin-top:2px;flex-shrink:0;cursor:pointer}
.checkbox-row label{font-size:13px;color:var(--text2);cursor:pointer}
.checkbox-row a{color:var(--accent)}

/* ── Password ── */
.pw-toggle{position:relative}
.pw-toggle input{padding-right:42px}
.pw-toggle button{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text3);cursor:pointer;padding:4px;font-size:14px}
.pw-toggle button:hover{color:var(--text1)}
.strength-bar{height:4px;border-radius:2px;background:var(--bg3);margin-top:6px;overflow:hidden}
.strength-fill{height:100%;width:0;border-radius:2px;transition:width .3s,background .3s}

/* ── Error banner ── */
.alert-err{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);border-radius:var(--radius-sm);padding:14px 18px;margin-bottom:24px;color:#fca5a5;font-size:14px}
.alert-err i{margin-right:8px}

/* ── Submit ── */
.btn-submit{
  width:100%;padding:15px;
  background:linear-gradient(135deg,var(--accent),#ea580c);
  border:none;border-radius:var(--radius-sm);color:#fff;
  font-family:var(--font-head);font-size:16px;font-weight:700;
  cursor:pointer;transition:transform .15s,box-shadow .15s;letter-spacing:.5px;
  display:flex;align-items:center;justify-content:center;gap:8px;margin-top:8px;
}
.btn-submit:hover{transform:translateY(-1px);box-shadow:0 8px 30px rgba(249,115,22,.35)}
.btn-submit:active{transform:translateY(0)}
.btn-submit:disabled{opacity:.5;cursor:not-allowed;transform:none}

/* ── RIGHT SIDEBAR ── */
/* Wrapper kolom kanan yang sticky — bukan tiap card.
   Ini mencegah card kedua (alur proses) scroll menimpa card pertama. */
.sidebar-col{position:sticky;top:24px;display:flex;flex-direction:column;gap:20px;
  /* Batas tinggi = viewport dikurangi top offset, lalu scroll di dalam kolom jika konten terlalu panjang */
  max-height:calc(100vh - 48px);overflow-y:auto;
  /* Sembunyikan scrollbar tapi tetap bisa scroll */
  scrollbar-width:none}
.sidebar-col::-webkit-scrollbar{display:none}
.sidebar-card{background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius);padding:24px}
.pkg-badge{display:inline-block;background:rgba(249,115,22,.12);color:var(--accent);font-size:11px;font-weight:700;padding:3px 10px;border-radius:99px;letter-spacing:.5px;margin-bottom:14px}
.pkg-name{font-family:var(--font-head);font-size:20px;font-weight:800;margin-bottom:4px}
.pkg-speed{font-size:13px;color:var(--text2);margin-bottom:16px;display:flex;align-items:center;gap:6px}
.pkg-speed i{color:var(--blue)}
.pkg-price-big{font-family:var(--font-head);font-size:28px;font-weight:800;color:var(--accent)}
.pkg-price-big span{font-size:14px;font-weight:400;color:var(--text2)}
.pkg-divider{border:none;border-top:1px solid var(--border);margin:16px 0}
.pkg-feature{display:flex;align-items:center;gap:10px;font-size:13px;color:var(--text2);margin-bottom:10px}
.pkg-feature i{color:var(--green);width:16px}
.pkg-note{background:rgba(59,130,246,.08);border:1px solid rgba(59,130,246,.2);border-radius:var(--radius-sm);padding:12px 14px;font-size:12px;color:var(--blue);margin-top:14px;line-height:1.5}
.pkg-note i{margin-right:6px}
.change-pkg{display:block;text-align:center;font-size:12px;color:var(--text3);text-decoration:none;margin-top:14px;transition:.2s}
.change-pkg:hover{color:var(--accent)}
.pkg-list{display:none;margin-top:14px;border-top:1px solid var(--border);padding-top:14px}
.pkg-list.open{display:block}
.pkg-item{display:flex;align-items:center;justify-content:space-between;padding:9px 12px;border-radius:var(--radius-sm);border:1px solid var(--border);margin-bottom:8px;text-decoration:none;transition:.2s}
.pkg-item:hover{border-color:var(--accent);background:rgba(249,115,22,.06)}
.pkg-item-name{font-size:13px;font-weight:600;color:var(--text1)}
.pkg-item-price{font-size:12px;color:var(--accent)}
.pkg-item.current{border-color:var(--accent);background:rgba(249,115,22,.06)}

/* ── Loading Overlay ── */
.overlay{display:none;position:fixed;inset:0;background:rgba(11,15,26,.85);z-index:999;align-items:center;justify-content:center;flex-direction:column;gap:16px}
.overlay.show{display:flex}
.spinner{width:48px;height:48px;border:4px solid var(--border);border-top-color:var(--accent);border-radius:50%;animation:spin .8s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
.overlay-text{color:var(--text2);font-size:14px}

/* ── Footer ── */
.page-footer{text-align:center;font-size:12px;color:var(--text3);margin-top:48px}
</style>
</head>
<body>

<!-- Loading Overlay -->
<div class="overlay" id="loadingOverlay">
  <div class="spinner"></div>
  <div class="overlay-text" id="overlayText">Memproses order Anda…</div>
</div>

<!-- ── Top Bar ─────────────────────────────────────────────────── -->
<div class="top-bar">
  <div class="inner">
    <a href="/index.php" class="logo-wrap">
      <img src="/assets/images/CDR LOGO PERKASA Putih with border.png" alt="Perkasa" onerror="this.style.display='none'">
      <div>
        <div class="logo-text">PERKASA <span>TECH</span></div>
        <span class="logo-sub">Solusindo</span>
      </div>
    </a>

    <div class="top-bar-right">
      <?php if ($is_logged_in && $logged_user): ?>
        <!-- User sudah login: tampilkan avatar + nama -->
        <div class="user-badge">
          <div class="user-badge-avatar">
            <?= strtoupper(mb_substr($logged_user['firstname'], 0, 1)) ?>
          </div>
          <span class="user-badge-name"><?= htmlspecialchars($logged_user['firstname']) ?></span>
          <span class="user-badge-sep">·</span>
          <a href="/client/client_dashboard.php" class="user-badge-logout">Dashboard</a>
          <span class="user-badge-sep">·</span>
          <a href="/login/logout.php" class="user-badge-logout" style="color:#f87171">Keluar</a>
        </div>
      <?php else: ?>
        <!-- Guest: tampilkan tombol login -->
        <span style="font-size:13px;color:var(--text3)">Sudah punya akun?</span>
        <a href="/login/login.php?redirect=<?= urlencode('/order/order_wifi.php?paket_id=' . $paket_id) ?>" class="btn-login">
          <i class="fa fa-sign-in-alt"></i> Login
        </a>
      <?php endif ?>
    </div>
  </div>
</div>

<!-- ── Page Wrap ───────────────────────────────────────────────── -->
<div class="page-wrap">

  <!-- Steps breadcrumb -->
  <div class="steps-nav">
    <div class="step-item done">
      <div class="step-num"><i class="fa fa-check" style="font-size:9px"></i></div>
      Pilih Paket
    </div>
    <div class="step-arrow"><i class="fa fa-chevron-right"></i></div>
    <div class="step-item active">
      <div class="step-num">2</div>
      <?= $is_logged_in ? 'Data KTP &amp; Alamat' : 'Registrasi &amp; Data Diri' ?>
    </div>
    <div class="step-arrow"><i class="fa fa-chevron-right"></i></div>
    <div class="step-item">
      <div class="step-num">3</div>
      Konfirmasi Order
    </div>
    <div class="step-arrow"><i class="fa fa-chevron-right"></i></div>
    <div class="step-item">
      <div class="step-num">4</div>
      Instalasi &amp; Aktivasi
    </div>
  </div>

  <!-- Flash error -->
  <?php if ($err): ?>
  <div class="alert-err"><i class="fa fa-exclamation-circle"></i><?= htmlspecialchars($err) ?></div>
  <?php endif ?>

  <!-- ── MODE A: USER SUDAH LOGIN ─────────────────────────────── -->
  <?php if ($is_logged_in && $logged_user): ?>

  <!-- Kartu info akun aktif -->
  <div class="account-panel">
    <div class="account-avatar">
      <?= strtoupper(mb_substr($logged_user['firstname'], 0, 1)) ?>
    </div>
    <div class="account-info">
      <div class="account-name">
        <?= htmlspecialchars(trim($logged_user['firstname'] . ' ' . $logged_user['lastname'])) ?>
      </div>
      <div class="account-email"><?= htmlspecialchars($logged_user['email']) ?></div>
      <div class="account-status"><i class="fa fa-circle"></i> Akun Aktif — order akan dikirim ke akun ini</div>
    </div>
    <div class="account-actions">
      <a href="/client/client_dashboard.php" class="btn-ghost">Dashboard</a>
    </div>
  </div>

  <div class="main-grid">
    <div>
      <form id="orderForm" method="POST" action="process_order.php" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="paket_id"   value="<?= $paket_id ?>">
        <input type="hidden" name="mode"        value="logged_in">
        <!-- CSRF token sederhana berbasis session -->
        <?php
          if (empty($_SESSION['csrf_token'])) {
              $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
          }
        ?>
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

        <!-- 1. Upload Foto KTP ──────────────────────────────────── -->
        <div class="panel">
          <div class="panel-title"><i class="fa fa-id-card"></i> Foto KTP</div>

          <?php if (!empty($logged_user['foto_ktp'])): ?>
          <!-- KTP sudah pernah diupload sebelumnya -->
          <div class="ktp-already">
            <i class="fa fa-check-circle"></i>
            <span>KTP Anda sudah tersimpan di sistem kami. Upload ulang hanya jika ingin memperbaruinya.</span>
          </div>
          <input type="hidden" name="foto_ktp_existing" value="<?= htmlspecialchars($logged_user['foto_ktp']) ?>">
          <?php endif ?>

          <div class="ktp-upload-area" id="ktpArea">
            <input type="file" name="foto_ktp" id="fotoKtp" accept=".jpg,.jpeg,.png"
                   <?= empty($logged_user['foto_ktp']) ? 'required' : '' ?>>
            <div class="ktp-icon">🪪</div>
            <div class="ktp-label">
              <?= empty($logged_user['foto_ktp']) ? 'Klik atau seret foto KTP ke sini' : 'Klik untuk upload KTP baru (opsional)' ?>
            </div>
            <div class="ktp-sub">Format JPG / PNG · Maks 5 MB · Pastikan semua teks terbaca jelas</div>
          </div>
          <div class="ktp-preview" id="ktpPreview">
            <img src="" id="ktpImg" alt="Preview KTP">
            <button type="button" class="ktp-remove" id="ktpRemove"><i class="fa fa-times"></i></button>
          </div>
          <div class="fg-hint" style="margin-top:8px">
            <i class="fa fa-lock" style="color:var(--green)"></i>
            Data KTP hanya digunakan untuk verifikasi identitas.
          </div>
        </div>

        <!-- 2. Data KTP (bisa prefill dari akun) ───────────────── -->
        <div class="panel">
          <div class="panel-title"><i class="fa fa-user"></i> Data Diri KTP</div>
          <div class="fg-hint" style="margin-bottom:16px;font-size:13px;color:var(--text2)">
            <i class="fa fa-info-circle" style="color:var(--blue)"></i>
            Isi sesuai KTP yang Anda upload. Data yang sudah ada bisa diperbarui jika diperlukan.
          </div>

          <div class="fg">
            <label>NIK (16 digit)<span class="req">*</span></label>
            <input type="text" name="nik" id="nik" maxlength="16"
                   placeholder="Nomor Induk Kependudukan"
                   value="<?= old('nik', $old, $logged_user['nik'] ?? '') ?>"
                   required pattern="[0-9]{16}">
            <div class="fg-hint">NIK terdiri dari 16 digit angka sesuai KTP</div>
          </div>

          <!-- Nama & nomor HP dari akun (readonly, tidak dikirim ulang) -->
          <div class="form-row">
            <div class="fg">
              <label>Nama Lengkap</label>
              <input type="text" value="<?= htmlspecialchars(trim($logged_user['firstname'] . ' ' . $logged_user['lastname'])) ?>" readonly>
              <div class="fg-hint">Dari akun Anda — <a href="/client/client_dashboard.php" style="color:var(--accent)">ubah di profil</a></div>
            </div>
            <div class="fg">
              <label>Nomor HP / WhatsApp</label>
              <input type="text" value="<?= htmlspecialchars($logged_user['phonenumber'] ?? '') ?>" readonly>
              <div class="fg-hint">Dari akun Anda</div>
            </div>
          </div>

          <div class="form-row">
            <div class="fg">
              <label>Jenis Kelamin<span class="req">*</span></label>
              <select name="jenis_kelamin" required>
                <option value="">— Pilih —</option>
                <option value="L" <?= old('jenis_kelamin', $old) === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                <option value="P" <?= old('jenis_kelamin', $old) === 'P' ? 'selected' : '' ?>>Perempuan</option>
              </select>
            </div>
            <div class="fg">
              <label>Tanggal Lahir<span class="req">*</span></label>
              <input type="date" name="tanggal_lahir"
                     value="<?= old('tanggal_lahir', $old) ?>"
                     required max="<?= date('Y-m-d', strtotime('-17 years')) ?>">
            </div>
          </div>

          <div class="fg">
            <label>Tempat Lahir<span class="req">*</span></label>
            <input type="text" name="tempat_lahir"
                   placeholder="Kota/Kabupaten sesuai KTP"
                   value="<?= old('tempat_lahir', $old) ?>" required>
          </div>
        </div>

        <!-- 3. Alamat Pemasangan ────────────────────────────────── -->
        <div class="panel">
          <div class="panel-title"><i class="fa fa-map-marker-alt"></i> Alamat Pemasangan WiFi</div>
          <div class="fg-hint" style="margin-bottom:16px;font-size:13px;color:var(--text2)">
            <i class="fa fa-info-circle" style="color:var(--blue)"></i>
            Masukkan alamat lengkap lokasi WiFi akan dipasang. Boleh berbeda dengan alamat KTP.
          </div>

          <div class="fg">
            <label>Alamat Jalan<span class="req">*</span></label>
            <input type="text" name="alamat_pasang" placeholder="Contoh: Jl. Mawar No. 12"
                   value="<?= old('alamat_pasang', $old) ?>" required>
          </div>

          <div class="fg-inline">
            <div class="fg">
              <label>RT<span class="req">*</span></label>
              <input type="text" name="rt" placeholder="01" maxlength="4"
                     value="<?= old('rt', $old) ?>" required>
            </div>
            <div class="fg">
              <label>RW<span class="req">*</span></label>
              <input type="text" name="rw" placeholder="01" maxlength="4"
                     value="<?= old('rw', $old) ?>" required>
            </div>
          </div>

          <div class="form-row">
            <div class="fg">
              <label>Kelurahan / Desa<span class="req">*</span></label>
              <input type="text" name="kelurahan" placeholder="Nama kelurahan"
                     value="<?= old('kelurahan', $old) ?>" required>
            </div>
            <div class="fg">
              <label>Kecamatan<span class="req">*</span></label>
              <input type="text" name="kecamatan" placeholder="Nama kecamatan"
                     value="<?= old('kecamatan', $old) ?>" required>
            </div>
          </div>

          <div class="form-row">
            <div class="fg">
              <label>Kota / Kabupaten<span class="req">*</span></label>
              <input type="text" name="kota" placeholder="Sidoarjo"
                     value="<?= old('kota', $old) ?>" required>
            </div>
            <div class="fg">
              <label>Provinsi<span class="req">*</span></label>
              <input type="text" name="provinsi" placeholder="Jawa Timur"
                     value="<?= old('provinsi', $old) ?>" required>
            </div>
          </div>

          <div class="form-row">
            <div class="fg">
              <label>Kode Pos<span class="req">*</span></label>
              <input type="text" name="kodepos" placeholder="61271" maxlength="5"
                     value="<?= old('kodepos', $old) ?>" required>
            </div>
            <div class="fg">
              <label>Patokan / Keterangan Lokasi</label>
              <input type="text" name="patokan" placeholder="Dekat masjid / sebelah alfamart"
                     value="<?= old('patokan', $old) ?>">
            </div>
          </div>
        </div>

        <!-- 4. Persetujuan ──────────────────────────────────────── -->
        <div class="panel" style="padding:20px 28px">
          <div class="checkbox-row">
            <input type="checkbox" name="accepttos" id="tos" required>
            <label for="tos">Saya menyetujui <a href="/syarat-ketentuan.php" target="_blank">Syarat &amp; Ketentuan</a> dan <a href="/kebijakan-privasi.php" target="_blank">Kebijakan Privasi</a> Perkasa Solusindo.</label>
          </div>
          <div class="checkbox-row">
            <input type="checkbox" name="marketingoptin" id="mkt">
            <label for="mkt">Saya ingin menerima info promo dan penawaran menarik via email/WhatsApp.</label>
          </div>

          <button type="submit" class="btn-submit" id="submitBtn">
            <i class="fa fa-rocket"></i> Kirim Order Sekarang
          </button>
          <div class="fg-hint" style="text-align:center;margin-top:10px">
            <i class="fa fa-lock" style="color:var(--green)"></i>
            Pembayaran dilakukan setelah instalasi WiFi selesai.
          </div>
        </div>

      </form>
    </div>

    <!-- ── Sidebar Paket (Mode A) ───────────────────────────── -->
    <div class="sidebar-col">

      <div class="sidebar-card">
        <div class="pkg-badge">📡 Paket WiFi Dipilih</div>
        <div class="pkg-name"><?= htmlspecialchars($paket['name']) ?></div>
        <div class="pkg-speed"><i class="fa fa-bolt"></i> <?= htmlspecialchars($paket['speed'] ?? '-') ?></div>
        <div class="pkg-price-big"><?= rupiah((float)$paket['price']) ?><span>/<?= htmlspecialchars($paket['period'] ?? 'bulan') ?></span></div>
        <hr class="pkg-divider">
        <div class="pkg-feature"><i class="fa fa-check-circle"></i> Fiber Optik Dedicated</div>
        <div class="pkg-feature"><i class="fa fa-check-circle"></i> Uptime 99% SLA</div>
        <div class="pkg-feature"><i class="fa fa-check-circle"></i> Support 24/7 via WhatsApp</div>
        <div class="pkg-feature"><i class="fa fa-check-circle"></i> Instalasi Gratis oleh Teknisi</div>
        <div class="pkg-feature"><i class="fa fa-check-circle"></i> Tidak ada biaya pendaftaran</div>
        <div class="pkg-note">
          <i class="fa fa-info-circle"></i>
          <strong>Bayar setelah instalasi.</strong> Tagihan pertama mulai berjalan setelah WiFi aktif di lokasi Anda.
        </div>
        <a href="#" class="change-pkg" id="togglePkgList">
          <i class="fa fa-exchange-alt"></i> Ganti paket lain
        </a>
        <div class="pkg-list" id="pkgList">
          <?php foreach ($semua_paket as $p): ?>
          <a href="order_wifi.php?paket_id=<?= $p['id'] ?>"
             class="pkg-item <?= (int)$p['id'] === $paket_id ? 'current' : '' ?>">
            <div>
              <div class="pkg-item-name"><?= htmlspecialchars($p['name']) ?></div>
              <div style="font-size:11px;color:var(--text3)"><?= htmlspecialchars($p['speed']) ?></div>
            </div>
            <div class="pkg-item-price"><?= rupiah((float)$p['price']) ?>/bln</div>
          </a>
          <?php endforeach ?>
        </div>
      </div>

      <div class="sidebar-card">
        <div class="panel-title" style="margin-bottom:16px"><i class="fa fa-route" style="color:var(--blue)"></i> Alur Proses</div>
        <?php
        $steps_a = [
          ['icon'=>'fa-file-alt',     'color'=>'var(--accent)', 'title'=>'Order Dikirim',     'desc'=>'Formulir masuk ke sistem kami'],
          ['icon'=>'fa-search',       'color'=>'var(--blue)',   'title'=>'Verifikasi Admin',   'desc'=>'Tim kami verifikasi data & cek coverage'],
          ['icon'=>'fa-calendar-alt', 'color'=>'#a78bfa',      'title'=>'Jadwal Instalasi',   'desc'=>'Teknisi hubungi Anda untuk jadwal'],
          ['icon'=>'fa-tools',        'color'=>'var(--green)',  'title'=>'Instalasi WiFi',     'desc'=>'Teknisi datang & pasang perangkat'],
          ['icon'=>'fa-wifi',         'color'=>'var(--green)',  'title'=>'WiFi Aktif & Bayar', 'desc'=>'Tagihan pertama mulai bulan ini'],
        ];
        foreach ($steps_a as $i => $s): ?>
        <div style="display:flex;gap:12px;margin-bottom:<?= $i < count($steps_a)-1 ? '14' : '0' ?>px;align-items:flex-start">
          <div style="width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,.05);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <i class="fa <?= $s['icon'] ?>" style="font-size:12px;color:<?= $s['color'] ?>"></i>
          </div>
          <div>
            <div style="font-size:13px;font-weight:600;color:var(--text1)"><?= $s['title'] ?></div>
            <div style="font-size:11px;color:var(--text3)"><?= $s['desc'] ?></div>
          </div>
        </div>
        <?php endforeach ?>
      </div>

    </div><!-- /sidebar-col Mode A -->

  </div><!-- /main-grid -->

  <?php else: ?>
  <!-- ══════════════════════════════════════════════════════════ -->
  <!-- MODE B: GUEST — form lengkap (registrasi + order)         -->
  <!-- ══════════════════════════════════════════════════════════ -->

  <div class="main-grid">
    <div>
      <form id="orderForm" method="POST" action="process_order.php" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="paket_id" value="<?= $paket_id ?>">
        <input type="hidden" name="mode"     value="guest">
        <?php
          if (empty($_SESSION['csrf_token'])) {
              $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
          }
        ?>
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

        <!-- 1. Upload KTP ──────────────────────────────────────── -->
        <div class="panel">
          <div class="panel-title"><i class="fa fa-id-card"></i> Upload Foto KTP</div>
          <div class="ktp-upload-area" id="ktpArea">
            <input type="file" name="foto_ktp" id="fotoKtp" accept=".jpg,.jpeg,.png" required>
            <div class="ktp-icon">🪪</div>
            <div class="ktp-label">Klik atau seret foto KTP ke sini</div>
            <div class="ktp-sub">Format JPG / PNG · Maks 5 MB · Pastikan semua teks terbaca jelas</div>
          </div>
          <div class="ktp-preview" id="ktpPreview">
            <img src="" id="ktpImg" alt="Preview KTP">
            <button type="button" class="ktp-remove" id="ktpRemove"><i class="fa fa-times"></i></button>
          </div>
          <div class="fg-hint" style="margin-top:8px">
            <i class="fa fa-lock" style="color:var(--green)"></i> Data KTP hanya digunakan untuk verifikasi identitas.
          </div>
        </div>

        <!-- 2. Data Diri ────────────────────────────────────────── -->
        <div class="panel">
          <div class="panel-title"><i class="fa fa-user"></i> Data Diri</div>

          <div class="fg">
            <label>NIK (Nomor Induk Kependudukan)<span class="req">*</span></label>
            <input type="text" name="nik" id="nik" maxlength="16"
                   placeholder="16 digit NIK sesuai KTP"
                   value="<?= old('nik', $old) ?>" required pattern="[0-9]{16}">
            <div class="fg-hint">NIK terdiri dari 16 digit angka</div>
          </div>

          <div class="form-row">
            <div class="fg">
              <label>Nama Depan<span class="req">*</span></label>
              <input type="text" name="firstname" placeholder="Nama depan"
                     value="<?= old('firstname', $old) ?>" required>
            </div>
            <div class="fg">
              <label>Nama Belakang</label>
              <input type="text" name="lastname" placeholder="Nama belakang"
                     value="<?= old('lastname', $old) ?>">
            </div>
          </div>

          <div class="form-row">
            <div class="fg">
              <label>Jenis Kelamin<span class="req">*</span></label>
              <select name="jenis_kelamin" required>
                <option value="">— Pilih —</option>
                <option value="L" <?= old('jenis_kelamin', $old) === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                <option value="P" <?= old('jenis_kelamin', $old) === 'P' ? 'selected' : '' ?>>Perempuan</option>
              </select>
            </div>
            <div class="fg">
              <label>Tanggal Lahir<span class="req">*</span></label>
              <input type="date" name="tanggal_lahir"
                     value="<?= old('tanggal_lahir', $old) ?>"
                     required max="<?= date('Y-m-d', strtotime('-17 years')) ?>">
            </div>
          </div>

          <div class="fg">
            <label>Tempat Lahir<span class="req">*</span></label>
            <input type="text" name="tempat_lahir" placeholder="Kota/Kabupaten sesuai KTP"
                   value="<?= old('tempat_lahir', $old) ?>" required>
          </div>
        </div>

        <!-- 3. Buat Akun Login ─────────────────────────────────── -->
        <div class="panel">
          <div class="panel-title"><i class="fa fa-shield-alt"></i> Buat Akun Login</div>
          <div class="fg-hint" style="margin-bottom:16px;font-size:13px;color:var(--text2)">
            <i class="fa fa-info-circle" style="color:var(--blue)"></i>
            Akun ini digunakan untuk memantau status order, tagihan, dan riwayat layanan Anda.
            Sudah punya akun? <a href="/login/login.php?redirect=<?= urlencode('/order/order_wifi.php?paket_id=' . $paket_id) ?>" style="color:var(--accent)">Login dulu →</a>
          </div>

          <div class="fg">
            <label>Email Aktif<span class="req">*</span></label>
            <input type="email" name="email" placeholder="email@example.com"
                   value="<?= old('email', $old) ?>" required>
            <div class="fg-hint">Email ini untuk login dan notifikasi status order</div>
          </div>

          <div class="form-row">
            <div class="fg">
              <label>Nomor HP / WhatsApp<span class="req">*</span></label>
              <input type="tel" name="phonenumber" placeholder="08xx-xxxx-xxxx"
                     value="<?= old('phonenumber', $old) ?>" required>
            </div>
            <div class="fg">
              <label>Nama Perusahaan / Instansi</label>
              <input type="text" name="companyname" placeholder="Opsional"
                     value="<?= old('companyname', $old) ?>">
            </div>
          </div>

          <div class="form-row">
            <div class="fg">
              <label>Password<span class="req">*</span></label>
              <div class="pw-toggle">
                <input type="password" name="password" id="pw1"
                       placeholder="Min. 8 karakter" required minlength="8">
                <button type="button" onclick="togglePw('pw1',this)"><i class="fa fa-eye-slash"></i></button>
              </div>
              <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
              <div class="fg-hint" id="strengthLabel">Masukkan password</div>
            </div>
            <div class="fg">
              <label>Ulangi Password<span class="req">*</span></label>
              <div class="pw-toggle">
                <input type="password" name="password_confirm" id="pw2"
                       placeholder="Ulangi password" required>
                <button type="button" onclick="togglePw('pw2',this)"><i class="fa fa-eye-slash"></i></button>
              </div>
            </div>
          </div>
        </div>

        <!-- 4. Alamat Pemasangan ────────────────────────────────── -->
        <div class="panel">
          <div class="panel-title"><i class="fa fa-map-marker-alt"></i> Alamat Pemasangan WiFi</div>
          <div class="fg-hint" style="margin-bottom:16px;font-size:13px;color:var(--text2)">
            <i class="fa fa-info-circle" style="color:var(--blue)"></i>
            Masukkan alamat lengkap tempat WiFi akan dipasang.
          </div>

          <div class="fg">
            <label>Alamat Jalan<span class="req">*</span></label>
            <input type="text" name="alamat_pasang" placeholder="Contoh: Jl. Mawar No. 12"
                   value="<?= old('alamat_pasang', $old) ?>" required>
          </div>

          <div class="fg-inline">
            <div class="fg">
              <label>RT<span class="req">*</span></label>
              <input type="text" name="rt" placeholder="01" maxlength="4"
                     value="<?= old('rt', $old) ?>" required>
            </div>
            <div class="fg">
              <label>RW<span class="req">*</span></label>
              <input type="text" name="rw" placeholder="01" maxlength="4"
                     value="<?= old('rw', $old) ?>" required>
            </div>
          </div>

          <div class="form-row">
            <div class="fg">
              <label>Kelurahan / Desa<span class="req">*</span></label>
              <input type="text" name="kelurahan" placeholder="Nama kelurahan"
                     value="<?= old('kelurahan', $old) ?>" required>
            </div>
            <div class="fg">
              <label>Kecamatan<span class="req">*</span></label>
              <input type="text" name="kecamatan" placeholder="Nama kecamatan"
                     value="<?= old('kecamatan', $old) ?>" required>
            </div>
          </div>

          <div class="form-row">
            <div class="fg">
              <label>Kota / Kabupaten<span class="req">*</span></label>
              <input type="text" name="kota" placeholder="Sidoarjo"
                     value="<?= old('kota', $old) ?>" required>
            </div>
            <div class="fg">
              <label>Provinsi<span class="req">*</span></label>
              <input type="text" name="provinsi" placeholder="Jawa Timur"
                     value="<?= old('provinsi', $old) ?>" required>
            </div>
          </div>

          <div class="form-row">
            <div class="fg">
              <label>Kode Pos<span class="req">*</span></label>
              <input type="text" name="kodepos" placeholder="61271" maxlength="5"
                     value="<?= old('kodepos', $old) ?>" required>
            </div>
            <div class="fg">
              <label>Patokan / Keterangan Lokasi</label>
              <input type="text" name="patokan" placeholder="Dekat masjid / sebelah alfamart"
                     value="<?= old('patokan', $old) ?>">
            </div>
          </div>
        </div>

        <!-- 5. Persetujuan ──────────────────────────────────────── -->
        <div class="panel" style="padding:20px 28px">
          <div class="checkbox-row">
            <input type="checkbox" name="accepttos" id="tos" required>
            <label for="tos">Saya menyetujui <a href="/syarat-ketentuan.php" target="_blank">Syarat &amp; Ketentuan</a> dan <a href="/kebijakan-privasi.php" target="_blank">Kebijakan Privasi</a> Perkasa Solusindo.</label>
          </div>
          <div class="checkbox-row">
            <input type="checkbox" name="marketingoptin" id="mkt">
            <label for="mkt">Saya ingin menerima info promo dan penawaran menarik via email/WhatsApp.</label>
          </div>

          <button type="submit" class="btn-submit" id="submitBtn">
            <i class="fa fa-rocket"></i> Daftarkan Order Sekarang
          </button>
          <div class="fg-hint" style="text-align:center;margin-top:10px">
            <i class="fa fa-lock" style="color:var(--green)"></i>
            Data Anda aman &amp; terenkripsi. Pembayaran dilakukan setelah instalasi WiFi selesai.
          </div>
        </div>

      </form>
    </div>

    <!-- Sidebar paket (shared) ─────────────────────────────────── -->
    <div class="sidebar-col">
      <div class="sidebar-card">
        <div class="pkg-badge">📡 Paket WiFi Dipilih</div>
        <div class="pkg-name"><?= htmlspecialchars($paket['name']) ?></div>
        <div class="pkg-speed"><i class="fa fa-bolt"></i> <?= htmlspecialchars($paket['speed'] ?? '-') ?></div>
        <div class="pkg-price-big"><?= rupiah((float)$paket['price']) ?><span>/<?= htmlspecialchars($paket['period'] ?? 'bulan') ?></span></div>
        <hr class="pkg-divider">
        <div class="pkg-feature"><i class="fa fa-check-circle"></i> Fiber Optik Dedicated</div>
        <div class="pkg-feature"><i class="fa fa-check-circle"></i> Uptime 99% SLA</div>
        <div class="pkg-feature"><i class="fa fa-check-circle"></i> Support 24/7 via WhatsApp</div>
        <div class="pkg-feature"><i class="fa fa-check-circle"></i> Instalasi Gratis oleh Teknisi</div>
        <div class="pkg-feature"><i class="fa fa-check-circle"></i> Tidak ada biaya pendaftaran</div>
        <div class="pkg-note">
          <i class="fa fa-info-circle"></i>
          <strong>Bayar setelah instalasi.</strong> Tagihan pertama mulai berjalan setelah WiFi aktif di lokasi Anda.
        </div>
        <a href="#" class="change-pkg" id="togglePkgList">
          <i class="fa fa-exchange-alt"></i> Ganti paket lain
        </a>
        <div class="pkg-list" id="pkgList">
          <?php foreach ($semua_paket as $p): ?>
          <a href="order_wifi.php?paket_id=<?= $p['id'] ?>"
             class="pkg-item <?= (int)$p['id'] === $paket_id ? 'current' : '' ?>">
            <div>
              <div class="pkg-item-name"><?= htmlspecialchars($p['name']) ?></div>
              <div style="font-size:11px;color:var(--text3)"><?= htmlspecialchars($p['speed']) ?></div>
            </div>
            <div class="pkg-item-price"><?= rupiah((float)$p['price']) ?>/bln</div>
          </a>
          <?php endforeach ?>
        </div>
      </div>

      <!-- Alur proses -->
      <div class="sidebar-card">
        <div class="panel-title" style="margin-bottom:16px"><i class="fa fa-route" style="color:var(--blue)"></i> Alur Proses</div>
        <?php
        $steps = [
          ['icon'=>'fa-file-alt',     'color'=>'var(--accent)', 'title'=>'Order Dikirim',     'desc'=>'Formulir masuk ke sistem kami'],
          ['icon'=>'fa-search',       'color'=>'var(--blue)',   'title'=>'Verifikasi Admin',   'desc'=>'Tim kami verifikasi data & cek coverage'],
          ['icon'=>'fa-calendar-alt', 'color'=>'#a78bfa',      'title'=>'Jadwal Instalasi',   'desc'=>'Teknisi hubungi Anda untuk jadwal'],
          ['icon'=>'fa-tools',        'color'=>'var(--green)',  'title'=>'Instalasi WiFi',     'desc'=>'Teknisi datang & pasang perangkat'],
          ['icon'=>'fa-wifi',         'color'=>'var(--green)',  'title'=>'WiFi Aktif & Bayar', 'desc'=>'Tagihan pertama mulai bulan ini'],
        ];
        foreach ($steps as $i => $s): ?>
        <div style="display:flex;gap:12px;margin-bottom:<?= $i < count($steps)-1 ? '14' : '0' ?>px;align-items:flex-start">
          <div style="width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,.05);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <i class="fa <?= $s['icon'] ?>" style="font-size:12px;color:<?= $s['color'] ?>"></i>
          </div>
          <div>
            <div style="font-size:13px;font-weight:600;color:var(--text1)"><?= $s['title'] ?></div>
            <div style="font-size:11px;color:var(--text3)"><?= $s['desc'] ?></div>
          </div>
        </div>
        <?php endforeach ?>
      </div>
    </div><!-- /sidebar-col Mode B -->

  </div><!-- /main-grid (mode B) -->
  <?php endif; // end mode B ?>

</div><!-- /page-wrap -->

<div class="page-footer">
  &copy; <?= date('Y') ?> Perkasa Solusindo · Sidoarjo, Jawa Timur
  &nbsp;·&nbsp; <a href="/index.php" style="color:var(--accent)">Kembali ke Beranda</a>
</div>

<script>
// ── Toggle package list ───────────────────────────────────────────
const toggleBtn = document.getElementById('togglePkgList');
if (toggleBtn) {
  toggleBtn.addEventListener('click', function(e){
    e.preventDefault();
    const list = document.getElementById('pkgList');
    list.classList.toggle('open');
    this.innerHTML = list.classList.contains('open')
      ? '<i class="fa fa-times"></i> Tutup'
      : '<i class="fa fa-exchange-alt"></i> Ganti paket lain';
  });
}

// ── KTP Upload Preview ────────────────────────────────────────────
const fotoKtp   = document.getElementById('fotoKtp');
const ktpArea   = document.getElementById('ktpArea');
const preview   = document.getElementById('ktpPreview');
const ktpImg    = document.getElementById('ktpImg');
const ktpRemove = document.getElementById('ktpRemove');

if (fotoKtp) {
  fotoKtp.addEventListener('change', function(){
    const file = this.files[0];
    if (!file) return;
    if (file.size > 5 * 1024 * 1024) { alert('Ukuran file maksimal 5 MB'); this.value=''; return; }
    const reader = new FileReader();
    reader.onload = e => {
      ktpImg.src = e.target.result;
      preview.style.display = 'block';
      ktpArea.querySelector('.ktp-icon').style.display   = 'none';
      ktpArea.querySelector('.ktp-label').style.display  = 'none';
      ktpArea.querySelector('.ktp-sub').style.display    = 'none';
    };
    reader.readAsDataURL(file);
  });

  ktpRemove.addEventListener('click', ()=>{
    fotoKtp.value = '';
    ktpImg.src    = '';
    preview.style.display                              = 'none';
    ktpArea.querySelector('.ktp-icon').style.display   = '';
    ktpArea.querySelector('.ktp-label').style.display  = '';
    ktpArea.querySelector('.ktp-sub').style.display    = '';
  });

  ktpArea.addEventListener('dragover', e => { e.preventDefault(); ktpArea.classList.add('dragover'); });
  ktpArea.addEventListener('dragleave', ()=> ktpArea.classList.remove('dragover'));
  ktpArea.addEventListener('drop', e => {
    e.preventDefault(); ktpArea.classList.remove('dragover');
    if (e.dataTransfer.files[0]) { fotoKtp.files = e.dataTransfer.files; fotoKtp.dispatchEvent(new Event('change')); }
  });
}

// ── Password toggle & strength (Mode B only) ──────────────────────
function togglePw(id, btn){
  const inp = document.getElementById(id);
  if (!inp) return;
  const isHidden = inp.type === 'password';
  inp.type = isHidden ? 'text' : 'password';
  btn.innerHTML = isHidden ? '<i class="fa fa-eye"></i>' : '<i class="fa fa-eye-slash"></i>';
}

const pw1 = document.getElementById('pw1');
const sf  = document.getElementById('strengthFill');
const sl  = document.getElementById('strengthLabel');
if (pw1 && sf && sl) {
  pw1.addEventListener('input', function(){
    const v = this.value;
    let score = 0;
    if (v.length >= 8) score++;
    if (/[A-Z]/.test(v)) score++;
    if (/[0-9]/.test(v)) score++;
    if (/[^A-Za-z0-9]/.test(v)) score++;
    const map = {
      0:['0%','transparent',''],
      1:['25%','#ef4444','Lemah'],
      2:['50%','#f97316','Sedang'],
      3:['75%','#eab308','Kuat'],
      4:['100%','#22c55e','Sangat Kuat']
    };
    sf.style.width      = map[score][0];
    sf.style.background = map[score][1];
    sl.textContent      = map[score][2];
  });
}

// ── Form submit ───────────────────────────────────────────────────
document.getElementById('orderForm').addEventListener('submit', function(e){
  // Validasi password hanya untuk mode guest
  const pw1v = document.getElementById('pw1');
  const pw2v = document.getElementById('pw2');
  if (pw1v && pw2v) {
    if (pw1v.value !== pw2v.value) {
      e.preventDefault();
      alert('Password tidak cocok!');
      return;
    }
  }
  if (!document.getElementById('tos').checked) {
    e.preventDefault();
    alert('Anda harus menyetujui Syarat & Ketentuan.');
    return;
  }
  document.getElementById('loadingOverlay').classList.add('show');
  document.getElementById('submitBtn').disabled = true;
});
</script>
</body>
</html>
