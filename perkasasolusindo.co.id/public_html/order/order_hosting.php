<?php
// =====================================================
//  order/order_hosting.php
//  Halaman order paket Hosting
//  Mode A: Guest (belum login) — tampil form registrasi + order
//  Mode B: Logged in (level 3 = client) — langsung form order
// =====================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth_check.php';

// Cek paket dari URL
$paket_id = isset($_GET['paket_id']) ? (int)$_GET['paket_id'] : 0;

// Ambil semua paket hosting aktif
$hosting_products = [];
$res = $conn->query("SELECT * FROM tblproducts WHERE category='hosting' AND status=1 AND ready_to_sell=1 ORDER BY price ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $hosting_products[] = $row;
    }
}

// Kalau paket_id valid, set sebagai selected; kalau tidak ada, default paket pertama
$selected_product = null;
foreach ($hosting_products as $p) {
    if ($p['id'] === $paket_id) {
        $selected_product = $p;
        break;
    }
}
if (!$selected_product && !empty($hosting_products)) {
    $selected_product = $hosting_products[0];
    $paket_id = (int)$selected_product['id'];
}

// Apakah user sudah login?
$is_logged_in = !empty($_SESSION['user_id']) && (int)$_SESSION['user_level'] === 3;

// Kalau admin/teknisi yang akses, redirect ke dashboard masing-masing
if (!empty($_SESSION['user_id']) && (int)$_SESSION['user_level'] !== 3) {
    header('Location: ' . dashboardUrl($_SESSION['user_level']));
    exit;
}

// Fitur-fitur paket per nama
$fitur_hosting = [
    'Hosting Starter' => [
        '☁️ Storage Unlimited NVMe',
        '🔒 SSL Gratis (Let\'s Encrypt)',
        '📧 Free Email Hosting',
        '🗄️ 1 Database MySQL',
        '🚀 Bandwidth Unlimited',
        '⚙️ cPanel Control Panel',
        '🔄 Uptime 99% SLA',
        '💬 Support via WhatsApp',
    ],
    'Hosting Business' => [
        '☁️ Storage Unlimited NVMe',
        '🔒 SSL Gratis (Let\'s Encrypt)',
        '📧 Free Email Hosting (Unlimited)',
        '🗄️ Database MySQL Unlimited',
        '🚀 Bandwidth Unlimited',
        '⚙️ cPanel Control Panel',
        '🔁 Backup Harian Otomatis',
        '🔄 Uptime 99% SLA',
        '📊 Resource Usage Monitor',
        '⚡ Prioritas Server Performance',
        '💬 Support Prioritas 24/7',
    ],
];

$default_fitur = [
    '☁️ Storage Unlimited NVMe',
    '🔒 SSL Gratis',
    '📧 Email Hosting',
    '🗄️ MySQL Database',
    '🚀 Bandwidth Unlimited',
    '⚙️ cPanel',
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Order Hosting — PERKASA SOLUSINDO</title>
  <link rel="icon" type="image/png" href="../assets/images/CDR LOGO PERKASA Putih with border.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,300&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/fontawesome.css">
  <style>
    /* ── Base (sama dengan style utama) ── */
    :root {
      --bg:         #06050f;
      --bg2:        #0a0818;
      --bg3:        #0e0c20;
      --surface:    #120f28;
      --surface2:   #1a1535;
      --border:     rgba(180,120,255,0.08);
      --border2:    rgba(200,140,255,0.15);
      --magenta:       #c026d3;
      --magenta-light: #e040fb;
      --magenta-glow:  rgba(192,38,211,0.22);
      --violet:        #7c3aed;
      --violet-glow:   rgba(124,58,237,0.2);
      --accent:     #f97316;
      --accent2:    #fb923c;
      --accent-glow:rgba(249,115,22,0.35);
      --blue:       #6366f1;
      --teal:       #14b8a6;
      --text:       #f1f5f9;
      --text2:      #b4aed4;
      --text3:      #6b6490;
      --font-display: 'Syne', sans-serif;
      --font-body:    'DM Sans', sans-serif;
      --radius:     14px;
      --radius-lg:  22px;
      --transition: 0.35s cubic-bezier(0.4,0,0.2,1);
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; }
    body {
      background:
        radial-gradient(ellipse 120% 60% at 0% 0%,   #12083a 0%, transparent 55%),
        radial-gradient(ellipse 80%  70% at 100% 20%, #2a0640 0%, transparent 50%),
        radial-gradient(ellipse 100% 80% at 50% 100%, #1a073a 0%, transparent 60%),
        linear-gradient(160deg, #06040e 0%, #0c0820 40%, #1a0630 70%, #0c0418 100%);
      background-attachment: fixed;
      color: var(--text);
      font-family: var(--font-body);
      font-size: 16px;
      line-height: 1.65;
      min-height: 100vh;
    }
    a { color: inherit; text-decoration: none; }
    img { max-width: 100%; display: block; }

    /* ── Header kecil ── */
    .mini-header {
      background: rgba(10,8,24,0.85);
      backdrop-filter: blur(20px);
      border-bottom: 1px solid var(--border2);
      padding: 14px 0;
      position: sticky;
      top: 0;
      z-index: 100;
    }
    .mini-header .container {
      max-width: 1100px;
      margin: 0 auto;
      padding: 0 24px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
    }
    .header-logo {
      display: flex;
      align-items: center;
      gap: 12px;
      font-family: var(--font-display);
      font-weight: 700;
      font-size: 18px;
    }
    .header-logo img { width: 36px; height: 36px; object-fit: contain; }
    .header-logo span { color: var(--accent); }
    .header-logo small { display: block; font-size: 10px; font-weight: 400; color: var(--text3); }
    .header-back {
      display: flex;
      align-items: center;
      gap: 8px;
      color: var(--text2);
      font-size: 14px;
      padding: 8px 16px;
      border: 1px solid var(--border2);
      border-radius: 8px;
      transition: var(--transition);
    }
    .header-back:hover { color: var(--text); border-color: var(--accent); }

    /* ── Container ── */
    .container { max-width: 1100px; margin: 0 auto; padding: 0 24px; }

    /* ── Steps indicator ── */
    .steps-bar {
      padding: 28px 0 20px;
      display: flex;
      justify-content: center;
    }
    .steps {
      display: flex;
      align-items: center;
      gap: 0;
    }
    .step {
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .step-num {
      width: 34px;
      height: 34px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: var(--font-display);
      font-weight: 700;
      font-size: 14px;
      border: 2px solid var(--border2);
      color: var(--text3);
      background: var(--surface);
      transition: var(--transition);
    }
    .step.active .step-num {
      border-color: var(--accent);
      color: var(--accent);
      background: rgba(249,115,22,0.12);
      box-shadow: 0 0 16px var(--accent-glow);
    }
    .step.done .step-num {
      border-color: var(--teal);
      color: var(--teal);
      background: rgba(20,184,166,0.12);
    }
    .step-label {
      font-size: 13px;
      color: var(--text3);
      font-weight: 500;
    }
    .step.active .step-label { color: var(--text); }
    .step-line {
      width: 48px;
      height: 2px;
      background: var(--border2);
      margin: 0 8px;
    }

    /* ── Hero paket terpilih ── */
    .paket-hero {
      background: linear-gradient(135deg, rgba(18,15,40,0.9) 0%, rgba(26,21,53,0.95) 100%);
      border: 1px solid var(--border2);
      border-radius: var(--radius-lg);
      padding: 32px;
      margin-bottom: 32px;
      position: relative;
      overflow: hidden;
    }
    .paket-hero::before {
      content: '';
      position: absolute;
      top: -40px;
      right: -40px;
      width: 180px;
      height: 180px;
      background: var(--magenta-glow);
      filter: blur(60px);
      border-radius: 50%;
    }
    .paket-hero-inner {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 24px;
      flex-wrap: wrap;
    }
    .paket-hero-left { flex: 1; min-width: 200px; }
    .paket-tag {
      display: inline-block;
      background: rgba(192,38,211,0.15);
      border: 1px solid rgba(192,38,211,0.3);
      color: var(--magenta-light);
      font-size: 12px;
      font-weight: 600;
      padding: 4px 12px;
      border-radius: 20px;
      margin-bottom: 12px;
      letter-spacing: 0.05em;
      text-transform: uppercase;
    }
    .paket-name {
      font-family: var(--font-display);
      font-size: clamp(22px, 4vw, 30px);
      font-weight: 700;
      margin-bottom: 8px;
    }
    .paket-desc { color: var(--text2); font-size: 14px; }
    .paket-hero-right { text-align: right; }
    .paket-price-label { font-size: 12px; color: var(--text3); margin-bottom: 4px; }
    .paket-price {
      font-family: var(--font-display);
      font-size: clamp(28px, 5vw, 42px);
      font-weight: 800;
      background: linear-gradient(90deg, var(--accent), var(--magenta-light));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    .paket-period { font-size: 13px; color: var(--text3); }

    /* Paket switcher */
    .paket-switcher {
      display: flex;
      gap: 12px;
      margin-bottom: 32px;
      flex-wrap: wrap;
    }
    .paket-btn {
      flex: 1;
      min-width: 180px;
      padding: 16px 20px;
      background: var(--surface);
      border: 2px solid var(--border2);
      border-radius: var(--radius);
      cursor: pointer;
      transition: var(--transition);
      text-align: left;
    }
    .paket-btn:hover { border-color: var(--violet); }
    .paket-btn.active {
      border-color: var(--accent);
      background: rgba(249,115,22,0.08);
    }
    .paket-btn-name {
      font-family: var(--font-display);
      font-weight: 700;
      font-size: 15px;
      margin-bottom: 4px;
    }
    .paket-btn.active .paket-btn-name { color: var(--accent); }
    .paket-btn-price { font-size: 13px; color: var(--text3); }

    /* ── Layout utama (2 kolom) ── */
    .order-layout {
      display: grid;
      grid-template-columns: 1fr 340px;
      gap: 28px;
      align-items: start;
      padding-bottom: 60px;
    }
    /* ══════════════════════════════════════════
       RESPONSIVE — tablet & mobile
    ══════════════════════════════════════════ */

    /* Tablet ≤ 860px: sidebar goes below form */
    @media (max-width: 860px) {
      .order-layout {
        grid-template-columns: 1fr;
      }
      .sidebar-sticky {
        position: static; /* disable sticky on mobile/tablet */
      }
      /* Sidebar reorder: ringkasan show AFTER form on mobile */
      .sidebar-col { order: 2; }
      .order-layout > div:first-child { order: 1; }

      /* Paket switcher wraps cleanly */
      .paket-switcher { gap: 8px; }
      .paket-btn { min-width: 140px; }

      /* Periode grid 2x2 on tablet */
      .period-options { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }

      /* Domain TLD grid single col on tablet too */
      .domain-tld-grid { grid-template-columns: 1fr 1fr; }

      /* Steps bar compact */
      .step-label { display: none; }
      .step-line { width: 28px; }
    }

    /* Mobile ≤ 600px */
    @media (max-width: 600px) {
      /* Tighter page padding */
      .container { padding: 0 14px; }

      /* Header */
      .mini-header .container { padding: 0 14px; }
      .header-logo small { display: none; }
      .header-back span { display: none; } /* show icon only */
      .header-back { padding: 8px 12px; }

      /* Hero paket */
      .paket-hero { padding: 20px 18px; }
      .paket-hero-inner { flex-direction: column; gap: 12px; }
      .paket-hero-right { text-align: left; }
      .paket-price { font-size: 28px; }

      /* Paket switcher: full width stacked */
      .paket-switcher { flex-direction: column; }
      .paket-btn { min-width: unset; width: 100%; padding: 14px 16px; }

      /* Section head */
      .section-head { padding: 24px 0 16px; }
      .section-head h1 { font-size: 22px; }
      .section-head p { font-size: 14px; }

      /* Form card */
      .form-card { padding: 20px 16px; border-radius: 14px; }
      .form-card-title { font-size: 16px; }

      /* Form rows: always single column */
      .form-row { grid-template-columns: 1fr !important; gap: 0; }

      /* Domain type toggle: compact */
      .domain-type-opt { padding: 11px 12px; gap: 10px; }
      .domain-type-icon { font-size: 18px; }
      .domain-type-label { font-size: 13px; }
      .domain-type-sub { font-size: 11px; }
      .domain-type-badge { font-size: 9px; padding: 2px 7px; }

      /* Domain group - suffix wraps nicely */
      .domain-group { flex-wrap: nowrap; }
      .domain-group input { min-width: 0; font-size: 13px; }
      .domain-group .domain-tld { font-size: 12px; padding: 12px 10px; }

      /* Domain search */
      .domain-search-wrap { flex-direction: column; gap: 8px; }
      .domain-search-input { border-radius: 10px !important; border-right: 1.5px solid var(--border2) !important; font-size: 14px; }
      .domain-search-btn { border-radius: 10px !important; justify-content: center; padding: 12px; }

      /* TLD grid: 1 column on mobile */
      .domain-tld-grid { grid-template-columns: 1fr; gap: 8px; }
      .domain-tld-card { padding: 10px 12px; }
      .tld-name-full { font-size: 13px; }
      .tld-price { font-size: 13px; }

      /* Periode: 2x2 grid */
      .period-options { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
      .period-opt { min-width: unset; padding: 10px 8px; }
      .period-opt-label { font-size: 12px; }
      .period-opt-price { font-size: 10px; }
      .period-opt-badge { font-size: 9px; padding: 1px 4px; margin-left: 2px; }

      /* Steps: show numbers only */
      .steps-bar { padding: 16px 0 12px; }
      .step-num { width: 28px; height: 28px; font-size: 12px; }
      .step-line { width: 20px; margin: 0 4px; }

      /* Sidebar */
      .sidebar-card { padding: 18px 16px; border-radius: 14px; }
      .sidebar-title { font-size: 13px; margin-bottom: 14px; }
      .summary-paket-name { font-size: 16px; }
      .fitur-list li { font-size: 12px; padding: 6px 0; }
      .summary-total-val { font-size: 20px; }
      .guarantee-badges { gap: 8px; margin-top: 14px; }
      .guarantee-badge { font-size: 11px; }

      /* User info bar */
      .user-info-bar { padding: 12px 14px; gap: 10px; }
      .user-avatar { width: 34px; height: 34px; font-size: 14px; }
      .user-info-name { font-size: 13px; }
      .user-info-email { font-size: 11px; }

      /* KTP upload */
      .ktp-upload-area { padding: 16px; }
      .ktp-upload-icon { font-size: 26px; }

      /* Breadcrumb */
      .breadcrumb { font-size: 12px; padding: 10px 0 0; }

      /* Toast */
      .toast { bottom: 16px; right: 14px; left: 14px; max-width: unset; font-size: 13px; }

      /* Submit button */
      .btn-submit { padding: 14px; font-size: 15px; }

      /* Mode tabs */
      .mode-tab { font-size: 13px; padding: 9px 12px; }

      /* Alert boxes */
      .alert-info, .alert-success { font-size: 13px; padding: 12px 14px; }
    }

    /* Extra small ≤ 380px */
    @media (max-width: 380px) {
      .domain-group .domain-tld { font-size: 11px; padding: 12px 8px; }
      .paket-price { font-size: 24px; }
    }

    /* ── Form card ── */
    .form-card {
      background: rgba(18,15,40,0.8);
      border: 1px solid var(--border2);
      border-radius: var(--radius-lg);
      padding: 32px;
    }
    .form-card-title {
      font-family: var(--font-display);
      font-size: 18px;
      font-weight: 700;
      margin-bottom: 24px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .form-card-title i { color: var(--accent); }

    /* Mode tabs */
    .mode-tabs {
      display: flex;
      gap: 0;
      background: var(--bg3);
      border-radius: 10px;
      padding: 4px;
      margin-bottom: 28px;
      border: 1px solid var(--border2);
    }
    .mode-tab {
      flex: 1;
      padding: 10px 16px;
      border-radius: 8px;
      text-align: center;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      color: var(--text3);
      transition: var(--transition);
    }
    .mode-tab.active {
      background: var(--accent);
      color: #fff;
      box-shadow: 0 4px 14px var(--accent-glow);
    }
    .mode-panel { display: none; }
    .mode-panel.active { display: block; }

    /* Alert info */
    .alert-info {
      background: rgba(99,102,241,0.1);
      border: 1px solid rgba(99,102,241,0.3);
      border-radius: 10px;
      padding: 14px 18px;
      font-size: 14px;
      color: var(--text2);
      margin-bottom: 20px;
      display: flex;
      gap: 10px;
      align-items: flex-start;
    }
    .alert-info i { color: var(--blue); margin-top: 2px; flex-shrink: 0; }

    .alert-success {
      background: rgba(20,184,166,0.1);
      border: 1px solid rgba(20,184,166,0.3);
      border-radius: 10px;
      padding: 14px 18px;
      font-size: 14px;
      color: var(--text2);
      margin-bottom: 20px;
      display: flex;
      gap: 10px;
      align-items: flex-start;
    }
    .alert-success i { color: var(--teal); margin-top: 2px; }

    /* Form fields */
    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
    }
    .form-row.single { grid-template-columns: 1fr; }
    

    .form-group {
      margin-bottom: 18px;
    }
    .form-group label {
      display: block;
      font-size: 13px;
      font-weight: 600;
      color: var(--text2);
      margin-bottom: 8px;
      letter-spacing: 0.03em;
    }
    .form-group label .req { color: var(--accent); margin-left: 2px; }
    .form-group input,
    .form-group select,
    .form-group textarea {
      width: 100%;
      background: var(--bg3);
      border: 1.5px solid var(--border2);
      border-radius: 10px;
      padding: 12px 16px;
      color: var(--text);
      font-family: var(--font-body);
      font-size: 14px;
      transition: var(--transition);
      outline: none;
    }
    .form-group textarea { resize: vertical; min-height: 90px; }
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(249,115,22,0.15);
    }
    .form-group input::placeholder,
    .form-group textarea::placeholder { color: var(--text3); }
    .form-hint { font-size: 12px; color: var(--text3); margin-top: 6px; }

    /* Checkbox */
    .checkbox-group {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      margin-bottom: 18px;
    }
    .checkbox-group input[type=checkbox] {
      width: 18px;
      height: 18px;
      margin-top: 3px;
      accent-color: var(--accent);
      flex-shrink: 0;
    }
    .checkbox-group label {
      font-size: 13px;
      color: var(--text2);
      line-height: 1.5;
    }
    .checkbox-group label a { color: var(--accent); }

    /* Divider */
    .divider {
      display: flex;
      align-items: center;
      gap: 12px;
      margin: 22px 0;
    }
    .divider::before, .divider::after {
      content: '';
      flex: 1;
      height: 1px;
      background: var(--border2);
    }
    .divider span { font-size: 12px; color: var(--text3); white-space: nowrap; }

    /* Password strength */
    .pwd-strength {
      height: 4px;
      border-radius: 2px;
      margin-top: 8px;
      background: var(--border2);
      overflow: hidden;
    }
    .pwd-strength-bar {
      height: 100%;
      border-radius: 2px;
      transition: var(--transition);
      width: 0%;
    }

    /* Submit button */
    .btn-submit {
      width: 100%;
      padding: 16px;
      background: linear-gradient(135deg, var(--accent), var(--accent2));
      border: none;
      border-radius: 12px;
      color: #fff;
      font-family: var(--font-display);
      font-size: 16px;
      font-weight: 700;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      transition: var(--transition);
      box-shadow: 0 6px 24px var(--accent-glow);
      letter-spacing: 0.02em;
    }
    .btn-submit:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 32px var(--accent-glow);
    }
    .btn-submit:disabled {
      opacity: 0.5;
      cursor: not-allowed;
      transform: none;
    }

    /* Login link button */
    .btn-login-link {
      display: block;
      width: 100%;
      padding: 14px;
      border: 2px solid var(--border2);
      border-radius: 12px;
      text-align: center;
      font-size: 15px;
      font-weight: 600;
      color: var(--text2);
      margin-top: 12px;
      transition: var(--transition);
    }
    .btn-login-link:hover {
      border-color: var(--violet);
      color: var(--text);
    }

    /* ── Sidebar ringkasan ── */
    .sidebar-col {
      display: flex;
      flex-direction: column;
      gap: 16px;
    }
    .sidebar-card {
      background: rgba(18,15,40,0.8);
      border: 1px solid var(--border2);
      border-radius: var(--radius-lg);
      padding: 24px;
    }
    .sidebar-sticky {
      position: sticky;
      top: 80px;
      display: flex;
      flex-direction: column;
      gap: 16px;
    }
    .sidebar-title {
      font-family: var(--font-display);
      font-size: 15px;
      font-weight: 700;
      margin-bottom: 20px;
      color: var(--text2);
      text-transform: uppercase;
      letter-spacing: 0.08em;
    }
    .summary-paket-name {
      font-family: var(--font-display);
      font-size: 18px;
      font-weight: 700;
      margin-bottom: 4px;
    }
    .summary-paket-cat {
      font-size: 12px;
      color: var(--magenta-light);
      background: rgba(192,38,211,0.12);
      border: 1px solid rgba(192,38,211,0.25);
      display: inline-block;
      padding: 2px 10px;
      border-radius: 20px;
      margin-bottom: 20px;
      font-weight: 600;
    }
    .fitur-list { list-style: none; margin-bottom: 24px; }
    .fitur-list li {
      font-size: 13px;
      color: var(--text2);
      padding: 8px 0;
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .fitur-list li:last-child { border-bottom: none; }

    .summary-total-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 16px 0;
      border-top: 1px solid var(--border2);
      margin-top: 4px;
    }
    .summary-total-label { font-size: 13px; color: var(--text3); }
    .summary-total-val {
      font-family: var(--font-display);
      font-size: 22px;
      font-weight: 800;
      color: var(--accent);
    }
    .summary-period { font-size: 11px; color: var(--text3); text-align: right; }

    .guarantee-badges {
      display: flex;
      flex-direction: column;
      gap: 10px;
      margin-top: 20px;
    }
    .guarantee-badge {
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 12px;
      color: var(--text3);
    }
    .guarantee-badge i { color: var(--teal); }

    /* ── Help card ── */
    .help-card {
      background: rgba(249,115,22,0.05) !important;
      border-color: rgba(249,115,22,0.2) !important;
    }
    .help-card-title {
      font-size: 14px;
      font-weight: 700;
      margin-bottom: 10px;
      color: var(--text);
    }
    .help-card-desc {
      font-size: 13px;
      color: var(--text2);
      margin-bottom: 14px;
      line-height: 1.55;
    }
    .help-wa-btn {
      display: flex;
      align-items: center;
      gap: 8px;
      color: var(--accent);
      font-size: 14px;
      font-weight: 700;
      text-decoration: none;
      transition: var(--transition);
    }
    .help-wa-btn:hover { color: var(--accent2); }

    /* ── Toast ── */
    .toast {
      position: fixed;
      bottom: 24px;
      right: 24px;
      background: var(--surface2);
      border: 1px solid var(--border2);
      border-radius: 12px;
      padding: 14px 20px;
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 14px;
      z-index: 9999;
      transform: translateY(100px);
      opacity: 0;
      transition: var(--transition);
      max-width: 340px;
    }
    .toast.show { transform: translateY(0); opacity: 1; }
    .toast.error { border-color: rgba(239,68,68,0.4); }

    /* ── Loading spinner ── */
    .spinner {
      width: 18px; height: 18px;
      border: 2px solid rgba(255,255,255,0.3);
      border-top-color: #fff;
      border-radius: 50%;
      animation: spin 0.7s linear infinite;
      display: none;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* ── Logged-in user info bar ── */
    .user-info-bar {
      background: rgba(20,184,166,0.07);
      border: 1px solid rgba(20,184,166,0.2);
      border-radius: 12px;
      padding: 14px 18px;
      display: flex;
      align-items: center;
      gap: 14px;
      margin-bottom: 24px;
    }
    .user-avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--violet), var(--magenta));
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: var(--font-display);
      font-weight: 700;
      font-size: 16px;
      flex-shrink: 0;
    }
    .user-info-name { font-size: 14px; font-weight: 600; }
    .user-info-email { font-size: 12px; color: var(--text3); }
    .user-info-badge {
      margin-left: auto;
      background: rgba(20,184,166,0.15);
      border: 1px solid rgba(20,184,166,0.3);
      color: var(--teal);
      font-size: 11px;
      font-weight: 700;
      padding: 3px 10px;
      border-radius: 20px;
    }

    /* ── Section judul ── */
    .section-head {
      text-align: center;
      padding: 40px 0 28px;
    }
    .section-head h1 {
      font-family: var(--font-display);
      font-size: clamp(24px, 5vw, 36px);
      font-weight: 800;
      margin-bottom: 8px;
    }
    .section-head h1 em { font-style: normal; color: var(--accent); }
    .section-head p { color: var(--text2); font-size: 15px; }

    /* ── Breadcrumb ── */
    .breadcrumb {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 13px;
      color: var(--text3);
      padding: 16px 0 0;
    }
    .breadcrumb a { color: var(--text3); }
    .breadcrumb a:hover { color: var(--accent); }
    .breadcrumb i { font-size: 10px; }

    /* ── KTP Upload ── */
    .ktp-upload-area {
      border: 2px dashed var(--border2);
      border-radius: 12px;
      padding: 20px;
      text-align: center;
      cursor: pointer;
      transition: var(--transition);
      position: relative;
      background: var(--bg3);
    }
    .ktp-upload-area:hover, .ktp-upload-area.drag-over {
      border-color: var(--accent);
      background: rgba(249,115,22,0.05);
    }
    .ktp-upload-area input[type=file] {
      position: absolute;
      inset: 0;
      opacity: 0;
      cursor: pointer;
      width: 100%;
      height: 100%;
      border: none !important;
      padding: 0 !important;
      background: transparent !important;
    }
    .ktp-upload-icon { font-size: 32px; margin-bottom: 8px; }
    .ktp-upload-label { font-size: 14px; font-weight: 600; color: var(--text2); margin-bottom: 4px; }
    .ktp-upload-hint { font-size: 12px; color: var(--text3); }
    .ktp-preview {
      display: none;
      margin-top: 12px;
      border-radius: 8px;
      overflow: hidden;
      position: relative;
    }
    .ktp-preview img {
      width: 100%;
      max-height: 180px;
      object-fit: cover;
      border-radius: 8px;
    }
    .ktp-preview-name {
      font-size: 12px;
      color: var(--teal);
      margin-top: 6px;
      display: flex;
      align-items: center;
      gap: 6px;
      justify-content: center;
    }
    .ktp-clear {
      display: inline-block;
      margin-top: 6px;
      font-size: 12px;
      color: var(--text3);
      cursor: pointer;
      text-decoration: underline;
    }
    .ktp-clear:hover { color: #ef4444; }

    /* ── Domain input group ── */
    .domain-group {
      display: flex;
      gap: 0;
    }
    .domain-group input {
      border-radius: 10px 0 0 10px !important;
      flex: 1;
    }
    .domain-group .domain-tld {
      background: var(--surface2);
      border: 1.5px solid var(--border2);
      border-left: none;
      border-radius: 0 10px 10px 0;
      padding: 12px 16px;
      font-size: 14px;
      color: var(--text3);
      display: flex;
      align-items: center;
      white-space: nowrap;
    }

    /* ── Periode pilihan ── */
    .period-options {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
    }
    .period-opt {
      flex: 1;
      min-width: 100px;
      padding: 12px 14px;
      border: 2px solid var(--border2);
      border-radius: 10px;
      cursor: pointer;
      transition: var(--transition);
      text-align: center;
    }
    .period-opt:hover { border-color: var(--violet); }
    .period-opt.selected {
      border-color: var(--accent);
      background: rgba(249,115,22,0.08);
    }
    .period-opt input { display: none; }
    .period-opt-label { font-size: 13px; font-weight: 600; display: block; }
    .period-opt-price { font-size: 11px; color: var(--text3); margin-top: 2px; }
    .period-opt.selected .period-opt-label { color: var(--accent); }
    .period-opt-badge {
      display: inline-block;
      background: var(--teal);
      color: #fff;
      font-size: 10px;
      font-weight: 700;
      padding: 1px 6px;
      border-radius: 8px;
      margin-left: 4px;
    }

    /* ── Domain type toggle ── */
    .domain-type-toggle {
      display: flex;
      flex-direction: column;
      gap: 10px;
      margin-bottom: 6px;
    }
    .domain-type-opt {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 14px 16px;
      border: 2px solid var(--border2);
      border-radius: 12px;
      cursor: pointer;
      transition: var(--transition);
      background: var(--bg3);
    }
    .domain-type-opt:hover { border-color: var(--violet); }
    .domain-type-opt.selected {
      border-color: var(--accent);
      background: rgba(249,115,22,0.07);
    }
    .domain-type-opt input[type=radio] { display: none; }
    .domain-type-icon { font-size: 22px; flex-shrink: 0; }
    .domain-type-opt > div { flex: 1; }
    .domain-type-label { font-size: 14px; font-weight: 600; color: var(--text); }
    .domain-type-sub { font-size: 12px; color: var(--text3); margin-top: 1px; font-family: monospace; }
    .domain-type-badge {
      font-size: 10px;
      font-weight: 700;
      padding: 3px 9px;
      border-radius: 20px;
      white-space: nowrap;
      flex-shrink: 0;
    }
    .domain-type-badge.free { background: rgba(20,184,166,0.15); border: 1px solid rgba(20,184,166,0.3); color: var(--teal); }
    .domain-type-badge.paid { background: rgba(249,115,22,0.15); border: 1px solid rgba(249,115,22,0.3); color: var(--accent); }

    .domain-panel { display: none; }
    .domain-panel.active { display: block; }

    /* ── Domain search ── */
    .domain-search-wrap {
      display: flex;
      gap: 0;
    }
    .domain-search-input {
      flex: 1;
      background: var(--bg3);
      border: 1.5px solid var(--border2);
      border-right: none;
      border-radius: 10px 0 0 10px !important;
      padding: 12px 16px;
      color: var(--text);
      font-family: var(--font-body);
      font-size: 14px;
      outline: none;
      transition: var(--transition);
    }
    .domain-search-input:focus { border-color: var(--accent); }
    .domain-search-btn {
      background: var(--accent);
      border: none;
      border-radius: 0 10px 10px 0;
      padding: 12px 20px;
      color: #fff;
      font-family: var(--font-display);
      font-size: 13px;
      font-weight: 700;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 8px;
      white-space: nowrap;
      transition: var(--transition);
    }
    .domain-search-btn:hover { background: var(--accent2); }

    .domain-result-area {
      margin-top: 4px;
      margin-bottom: 16px;
    }
    .domain-result-label {
      font-size: 12px;
      color: var(--text3);
      margin-bottom: 10px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }
    .domain-tld-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 10px;
    }
    @media (max-width: 480px) { .domain-tld-grid { grid-template-columns: 1fr; } }

    .domain-tld-card {
      border: 2px solid var(--border2);
      border-radius: 10px;
      padding: 12px 14px;
      cursor: pointer;
      transition: var(--transition);
      background: var(--bg3);
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 10px;
    }
    .domain-tld-card:hover { border-color: var(--violet); }
    .domain-tld-card.selected-tld {
      border-color: var(--accent);
      background: rgba(249,115,22,0.08);
    }
    .domain-tld-card.unavailable { opacity: 0.4; cursor: not-allowed; pointer-events: none; }
    .tld-left {}
    .tld-name-full { font-size: 13px; font-weight: 700; color: var(--text); }
    .tld-name-full span { color: var(--accent); }
    .tld-avail {
      font-size: 11px;
      margin-top: 2px;
      color: var(--teal);
    }
    .tld-avail.na { color: #ef4444; }
    .tld-right { text-align: right; flex-shrink: 0; }
    .tld-price { font-size: 14px; font-weight: 700; color: var(--text); }
    .tld-price-label { font-size: 10px; color: var(--text3); margin-top: 1px; }
    .tld-sale-badge {
      display: inline-block;
      background: var(--accent);
      color: #fff;
      font-size: 9px;
      font-weight: 700;
      padding: 1px 6px;
      border-radius: 8px;
      margin-left: 4px;
      vertical-align: middle;
    }

    .domain-selected-info {
      background: rgba(20,184,166,0.08);
      border: 1px solid rgba(20,184,166,0.25);
      border-radius: 10px;
      padding: 12px 16px;
      margin-top: 12px;
      font-size: 13px;
      color: var(--teal);
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .domain-selected-info i { flex-shrink: 0; }
  </style>
</head>
<body>

<!-- ══ Header Kecil ══════════════════════════════════════ -->
<header class="mini-header">
  <div class="container">
    <a href="../index.php" class="header-logo">
      <img src="../assets/images/CDR LOGO PERKASA Putih with border.png" alt="Logo">
      <div>
        PERKASA <span>TECH</span>
        <small>Solusindo</small>
      </div>
    </a>
    <a href="../index.php#services" class="header-back">
      <i class="fa fa-arrow-left"></i> Kembali ke Layanan
    </a>
  </div>
</header>

<!-- ══ Main Content ══════════════════════════════════════ -->
<div class="container">

  <!-- Breadcrumb -->
  <div class="breadcrumb">
    <a href="../index.php">Beranda</a>
    <i class="fa fa-chevron-right"></i>
    <a href="../index.php#services">Layanan</a>
    <i class="fa fa-chevron-right"></i>
    <span>Order Hosting</span>
  </div>

  <!-- Section Title -->
  <div class="section-head">
    <h1>☁️ Order <em>Paket Hosting</em></h1>
    <p>Pilih paket, lengkapi data, dan website Anda aktif dalam 24 jam.</p>
  </div>

  <!-- Steps -->
  <div class="steps-bar">
    <div class="steps">
      <div class="step active">
        <div class="step-num">1</div>
        <div class="step-label">Pilih Paket & Data</div>
      </div>
      <div class="step-line"></div>
      <div class="step">
        <div class="step-num">2</div>
        <div class="step-label">Konfirmasi Admin</div>
      </div>
      <div class="step-line"></div>
      <div class="step">
        <div class="step-num">3</div>
        <div class="step-label">Hosting Aktif</div>
      </div>
    </div>
  </div>

  <!-- Paket Switcher -->
  <?php if (count($hosting_products) > 1): ?>
  <div class="paket-switcher">
    <?php foreach ($hosting_products as $p): ?>
    <div
      class="paket-btn <?php echo $p['id'] === $paket_id ? 'active' : ''; ?>"
      onclick="switchPaket(<?php echo $p['id']; ?>, '<?php echo addslashes($p['name']); ?>', <?php echo $p['price']; ?>, '<?php echo addslashes($p['description']); ?>')"
    >
      <div class="paket-btn-name"><?php echo htmlspecialchars($p['name']); ?></div>
      <div class="paket-btn-price">Rp <?php echo number_format($p['price'], 0, ',', '.'); ?>/bulan</div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Paket Hero -->
  <?php if ($selected_product): ?>
  <div class="paket-hero">
    <div class="paket-hero-inner">
      <div class="paket-hero-left">
        <div class="paket-tag">☁️ Hosting &amp; Domain</div>
        <div class="paket-name" id="heroName"><?php echo htmlspecialchars($selected_product['name']); ?></div>
        <div class="paket-desc" id="heroDesc"><?php echo htmlspecialchars($selected_product['description']); ?></div>
      </div>
      <div class="paket-hero-right">
        <div class="paket-price-label">Mulai dari</div>
        <div class="paket-price" id="heroPrice">Rp <?php echo number_format($selected_product['price'], 0, ',', '.'); ?></div>
        <div class="paket-period">/bulan</div>
      </div>
    </div>
  </div>
  <?php else: ?>
  <div class="paket-hero">
    <div class="paket-hero-inner">
      <div class="paket-hero-left">
        <div class="paket-tag">☁️ Hosting &amp; Domain</div>
        <div class="paket-name">Paket Hosting</div>
        <div class="paket-desc">Paket hosting tidak tersedia saat ini. Hubungi kami untuk info lebih lanjut.</div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Layout 2 kolom -->
  <div class="order-layout">

    <!-- ── Kolom kiri: Form ── -->
    <div>
      <div class="form-card">

        <?php if ($is_logged_in): ?>
        <!-- ════ MODE B: SUDAH LOGIN ════ -->
        <div class="form-card-title">
          <i class="fa fa-cloud-upload"></i> Detail Order Hosting
        </div>

        <!-- User info bar -->
        <div class="user-info-bar">
          <div class="user-avatar">
            <?php echo strtoupper(substr($_SESSION['user_firstname'], 0, 1)); ?>
          </div>
          <div>
            <div class="user-info-name"><?php echo htmlspecialchars($_SESSION['user_firstname'] . ' ' . $_SESSION['user_lastname']); ?></div>
            <div class="user-info-email"><?php echo htmlspecialchars($_SESSION['user_email']); ?></div>
          </div>
          <div class="user-info-badge">✓ Login</div>
        </div>

        <div class="alert-success">
          <i class="fa fa-check-circle"></i>
          <span>Anda sudah login. Data akun Anda akan digunakan untuk order ini. Cukup isi detail hosting di bawah.</span>
        </div>

        <form id="formOrderLogin" method="POST" action="process_order_hosting.php" novalidate>
          <input type="hidden" name="mode" value="login">
          <input type="hidden" name="paket_id" id="hiddenPaketLogin" value="<?php echo $paket_id; ?>">
          <input type="hidden" name="csrf_token" value="<?php echo bin2hex(random_bytes(16)); ?>">

          <!-- Domain Section -->
          <div class="form-group">
            <label>Pilihan Domain <span class="req">*</span></label>
            <div class="domain-type-toggle" id="domainTypeToggleLogin">
              <label class="domain-type-opt selected" onclick="switchDomainType('login','subdomain')">
                <input type="radio" name="domain_type" value="subdomain" checked>
                <span class="domain-type-icon">🎁</span>
                <div>
                  <div class="domain-type-label">Subdomain Gratis</div>
                  <div class="domain-type-sub">namadomain.perkasasolusindo.co.id</div>
                </div>
                <span class="domain-type-badge free">GRATIS</span>
              </label>
              <label class="domain-type-opt" onclick="switchDomainType('login','beli')">
                <input type="radio" name="domain_type" value="beli">
                <span class="domain-type-icon">🌐</span>
                <div>
                  <div class="domain-type-label">Beli Domain Baru</div>
                  <div class="domain-type-sub">Daftarkan domain pilihan Anda</div>
                </div>
                <span class="domain-type-badge paid">BERBAYAR</span>
              </label>
            </div>
          </div>

          <!-- Panel: Subdomain Gratis -->
          <div id="panelSubdomainLogin" class="domain-panel active">
            <div class="form-group">
              <label>Nama Subdomain <span class="req">*</span></label>
              <div class="domain-group">
                <input type="text" name="domain" id="domainLogin" placeholder="namawebsiteanda" autocomplete="off" required>
                <div class="domain-tld">.perkasasolusindo.co.id</div>
              </div>
              <div class="form-hint">Hanya huruf kecil, angka, dan tanda hubung (-). Contoh: <strong>tokosaya</strong>.perkasasolusindo.co.id</div>
            </div>
          </div>

          <!-- Panel: Beli Domain -->
          <div id="panelBeliLogin" class="domain-panel">
            <input type="hidden" name="domain_custom" id="domainCustomLogin" value="">
            <input type="hidden" name="domain_tld" id="domainTldLogin" value="">
            <input type="hidden" name="domain_price" id="domainPriceLogin" value="0">
            <div class="form-group">
              <label>Cari Nama Domain <span class="req">*</span></label>
              <div class="domain-search-wrap">
                <input type="text" id="domainSearchLogin" class="domain-search-input" placeholder="Masukkan nama domain (contoh: tokosaya)" autocomplete="off" oninput="liveSearchDomain('login', this.value)">
                <button type="button" class="domain-search-btn" onclick="searchDomain('login')"><i class="fa fa-search"></i> Cari</button>
              </div>
              <div class="form-hint">Masukkan nama domain tanpa ekstensi (.com, .id, dll)</div>
            </div>
            <div id="domainResultLogin" class="domain-result-area" style="display:none;">
              <div class="domain-result-label">Pilih ekstensi domain:</div>
              <div class="domain-tld-grid" id="domainTldGridLogin"></div>
              <div id="domainSelectedInfoLogin" class="domain-selected-info" style="display:none;"></div>
            </div>
          </div>

          <!-- Periode -->
          <div class="form-group">
            <label>Periode Sewa <span class="req">*</span></label>
            <div class="period-options" id="periodeOptsLogin">
              <label class="period-opt selected" onclick="selectPeriode(this, 1)">
                <input type="radio" name="periode_bulan" value="1" checked>
                <span class="period-opt-label">1 Bulan</span>
                <span class="period-opt-price" id="p1login">Rp <?php echo $selected_product ? number_format($selected_product['price'],0,',','.') : '0'; ?></span>
              </label>
              <label class="period-opt" onclick="selectPeriode(this, 3)">
                <input type="radio" name="periode_bulan" value="3">
                <span class="period-opt-label">3 Bulan</span>
                <span class="period-opt-price" id="p3login">Rp <?php echo $selected_product ? number_format($selected_product['price']*3,0,',','.') : '0'; ?></span>
              </label>
              <label class="period-opt" onclick="selectPeriode(this, 6)">
                <input type="radio" name="periode_bulan" value="6">
                <span class="period-opt-label">6 Bulan <span class="period-opt-badge">Hemat 5%</span></span>
                <span class="period-opt-price" id="p6login">Rp <?php echo $selected_product ? number_format(floor($selected_product['price']*6*0.95),0,',','.') : '0'; ?></span>
              </label>
              <label class="period-opt" onclick="selectPeriode(this, 12)">
                <input type="radio" name="periode_bulan" value="12">
                <span class="period-opt-label">12 Bulan <span class="period-opt-badge">Hemat 10%</span></span>
                <span class="period-opt-price" id="p12login">Rp <?php echo $selected_product ? number_format(floor($selected_product['price']*12*0.90),0,',','.') : '0'; ?></span>
              </label>
            </div>
          </div>

          <!-- Catatan -->
          <div class="form-group">
            <label>Catatan untuk Admin (Opsional)</label>
            <textarea name="catatan" placeholder="Contoh: install WordPress, tema bisnis, dsb..."></textarea>
          </div>

          <!-- Syarat -->
          <div class="checkbox-group">
            <input type="checkbox" id="tosLogin" name="tos" required>
            <label for="tosLogin">
              Saya menyetujui <a href="../ketentuan_layanan.php" target="_blank">Ketentuan Layanan</a> dan kebijakan privasi Perkasa Solusindo.
            </label>
          </div>

          <button type="submit" class="btn-submit" id="btnSubmitLogin">
            <i class="fa fa-paper-plane"></i>
            <span>Kirim Order Sekarang</span>
            <div class="spinner" id="spinnerLogin"></div>
          </button>
        </form>

        <?php else: ?>
        <!-- ════ MODE A: BELUM LOGIN / GUEST ════ -->
        <div class="form-card-title">
          <i class="fa fa-user-plus"></i> Data Pemesan
        </div>

        <!-- Tab pilihan: Guest atau Login -->
        <div class="mode-tabs">
          <div class="mode-tab active" id="tabGuest" onclick="switchMode('guest')">
            <i class="fa fa-user"></i> Daftar & Order
          </div>
          <div class="mode-tab" id="tabLogin" onclick="switchMode('login_redirect')">
            <i class="fa fa-sign-in"></i> Sudah Punya Akun
          </div>
        </div>

        <!-- Panel A: Guest / Registrasi -->
        <div class="mode-panel active" id="panelGuest">

          <div class="alert-info">
            <i class="fa fa-info-circle"></i>
            <span>Isi form di bawah untuk membuat akun sekaligus memesan hosting. Anda akan mendapat akun client area untuk memantau layanan.</span>
          </div>

          <form id="formOrderGuest" method="POST" action="process_order_hosting.php" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="mode" value="guest">
            <input type="hidden" name="paket_id" id="hiddenPaketGuest" value="<?php echo $paket_id; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo bin2hex(random_bytes(16)); ?>">

            <!-- Nama -->
            <div class="form-row">
              <div class="form-group">
                <label>Nama Depan <span class="req">*</span></label>
                <input type="text" name="firstname" placeholder="Budi" required autocomplete="given-name">
              </div>
              <div class="form-group">
                <label>Nama Belakang</label>
                <input type="text" name="lastname" placeholder="Santoso" autocomplete="family-name">
              </div>
            </div>

            <!-- Email & HP -->
            <div class="form-row">
              <div class="form-group">
                <label>Email <span class="req">*</span></label>
                <input type="email" name="email" placeholder="budi@email.com" required autocomplete="email">
              </div>
              <div class="form-group">
                <label>No. HP / WA <span class="req">*</span></label>
                <input type="tel" name="phonenumber" placeholder="08xxxxxxxxxx" required autocomplete="tel">
              </div>
            </div>

            <!-- Password -->
            <div class="form-row">
              <div class="form-group">
                <label>Password Akun <span class="req">*</span></label>
                <input type="password" name="password" id="guestPassword" placeholder="Min. 8 karakter" required autocomplete="new-password" oninput="checkPwd(this.value)">
                <div class="pwd-strength"><div class="pwd-strength-bar" id="pwdBar"></div></div>
                <div class="form-hint" id="pwdHint">Minimal 8 karakter</div>
              </div>
              <div class="form-group">
                <label>Konfirmasi Password <span class="req">*</span></label>
                <input type="password" name="password_confirm" id="guestPasswordConfirm" placeholder="Ulangi password" required autocomplete="new-password">
              </div>
            </div>

            <!-- KTP Upload -->
            <div class="form-group" style="margin-bottom:22px;">
              <label>Foto KTP <span class="req">*</span></label>
              <div class="ktp-upload-area" id="ktpUploadArea">
                <input type="file" name="ktp_file" id="ktpFileInput" accept="image/jpeg,image/png,image/webp" required>
                <div id="ktpPlaceholder">
                  <div class="ktp-upload-icon">🪪</div>
                  <div class="ktp-upload-label">Klik atau seret foto KTP ke sini</div>
                  <div class="ktp-upload-hint">Format JPG / PNG / WEBP · Maks. 3 MB</div>
                </div>
                <div class="ktp-preview" id="ktpPreview">
                  <img id="ktpPreviewImg" src="" alt="Preview KTP">
                  <div class="ktp-preview-name" id="ktpPreviewName"></div>
                </div>
              </div>
              <div class="form-hint">Diperlukan untuk verifikasi identitas. Data Anda aman dan terenkripsi.</div>
            </div>

            <div class="divider"><span>Detail Hosting</span></div>

            <!-- Domain Section -->
            <div class="form-group">
              <label>Pilihan Domain <span class="req">*</span></label>
              <div class="domain-type-toggle" id="domainTypeToggleGuest">
                <label class="domain-type-opt selected" onclick="switchDomainType('guest','subdomain')">
                  <input type="radio" name="domain_type" value="subdomain" checked>
                  <span class="domain-type-icon">🎁</span>
                  <div>
                    <div class="domain-type-label">Subdomain Gratis</div>
                    <div class="domain-type-sub">namadomain.perkasasolusindo.co.id</div>
                  </div>
                  <span class="domain-type-badge free">GRATIS</span>
                </label>
                <label class="domain-type-opt" onclick="switchDomainType('guest','beli')">
                  <input type="radio" name="domain_type" value="beli">
                  <span class="domain-type-icon">🌐</span>
                  <div>
                    <div class="domain-type-label">Beli Domain Baru</div>
                    <div class="domain-type-sub">Daftarkan domain pilihan Anda</div>
                  </div>
                  <span class="domain-type-badge paid">BERBAYAR</span>
                </label>
              </div>
            </div>

            <!-- Panel: Subdomain Gratis -->
            <div id="panelSubdomainGuest" class="domain-panel active">
              <div class="form-group">
                <label>Nama Subdomain <span class="req">*</span></label>
                <div class="domain-group">
                  <input type="text" name="domain" id="domainGuest" placeholder="namawebsiteanda" autocomplete="off" required>
                  <div class="domain-tld">.perkasasolusindo.co.id</div>
                </div>
                <div class="form-hint">Hanya huruf kecil, angka, dan tanda hubung (-). Contoh: <strong>tokosaya</strong>.perkasasolusindo.co.id</div>
              </div>
            </div>

            <!-- Panel: Beli Domain -->
            <div id="panelBeliGuest" class="domain-panel">
              <input type="hidden" name="domain_custom" id="domainCustomGuest" value="">
              <input type="hidden" name="domain_tld" id="domainTldGuest" value="">
              <input type="hidden" name="domain_price" id="domainPriceGuest" value="0">
              <div class="form-group">
                <label>Cari Nama Domain <span class="req">*</span></label>
                <div class="domain-search-wrap">
                  <input type="text" id="domainSearchGuest" class="domain-search-input" placeholder="Masukkan nama domain (contoh: tokosaya)" autocomplete="off" oninput="liveSearchDomain('guest', this.value)">
                  <button type="button" class="domain-search-btn" onclick="searchDomain('guest')"><i class="fa fa-search"></i> Cari</button>
                </div>
                <div class="form-hint">Masukkan nama domain tanpa ekstensi (.com, .id, dll)</div>
              </div>
              <div id="domainResultGuest" class="domain-result-area" style="display:none;">
                <div class="domain-result-label">Pilih ekstensi domain:</div>
                <div class="domain-tld-grid" id="domainTldGridGuest"></div>
                <div id="domainSelectedInfoGuest" class="domain-selected-info" style="display:none;"></div>
              </div>
            </div>

            <!-- Periode -->
            <div class="form-group">
              <label>Periode Sewa <span class="req">*</span></label>
              <div class="period-options" id="periodeOptsGuest">
                <label class="period-opt selected" onclick="selectPeriode(this, 1)">
                  <input type="radio" name="periode_bulan" value="1" checked>
                  <span class="period-opt-label">1 Bulan</span>
                  <span class="period-opt-price">Rp <?php echo $selected_product ? number_format($selected_product['price'],0,',','.') : '0'; ?></span>
                </label>
                <label class="period-opt" onclick="selectPeriode(this, 3)">
                  <input type="radio" name="periode_bulan" value="3">
                  <span class="period-opt-label">3 Bulan</span>
                  <span class="period-opt-price">Rp <?php echo $selected_product ? number_format($selected_product['price']*3,0,',','.') : '0'; ?></span>
                </label>
                <label class="period-opt" onclick="selectPeriode(this, 6)">
                  <input type="radio" name="periode_bulan" value="6">
                  <span class="period-opt-label">6 Bulan <span class="period-opt-badge">Hemat 5%</span></span>
                  <span class="period-opt-price">Rp <?php echo $selected_product ? number_format(floor($selected_product['price']*6*0.95),0,',','.') : '0'; ?></span>
                </label>
                <label class="period-opt" onclick="selectPeriode(this, 12)">
                  <input type="radio" name="periode_bulan" value="12">
                  <span class="period-opt-label">12 Bulan <span class="period-opt-badge">Hemat 10%</span></span>
                  <span class="period-opt-price">Rp <?php echo $selected_product ? number_format(floor($selected_product['price']*12*0.90),0,',','.') : '0'; ?></span>
                </label>
              </div>
            </div>

            <!-- Catatan -->
            <div class="form-group">
              <label>Catatan (Opsional)</label>
              <textarea name="catatan" placeholder="Contoh: install WordPress, tema bisnis, dsb..."></textarea>
            </div>

            <!-- TOS -->
            <div class="checkbox-group">
              <input type="checkbox" id="tosGuest" name="tos" required>
              <label for="tosGuest">
                Saya menyetujui <a href="../ketentuan_layanan.php" target="_blank">Ketentuan Layanan</a> dan kebijakan privasi Perkasa Solusindo.
              </label>
            </div>

            <button type="submit" class="btn-submit" id="btnSubmitGuest">
              <i class="fa fa-paper-plane"></i>
              <span>Buat Akun & Kirim Order</span>
              <div class="spinner" id="spinnerGuest"></div>
            </button>

          </form>
        </div>

        <!-- Panel B: Sudah punya akun → redirect login -->
        <div class="mode-panel" id="panelLoginRedirect">
          <div style="text-align:center; padding: 20px 0;">
            <div style="font-size: 48px; margin-bottom: 16px;">🔐</div>
            <div style="font-family: var(--font-display); font-size: 20px; font-weight: 700; margin-bottom: 10px;">
              Masuk ke Akun Anda
            </div>
            <p style="color: var(--text2); font-size: 14px; margin-bottom: 24px;">
              Login ke akun Perkasa Solusindo untuk memesan hosting lebih cepat. Data Anda sudah tersimpan di sistem kami.
            </p>
            <a
              href="../login/login.php?redirect=<?php echo urlencode('/order/order_hosting.php?paket_id=' . $paket_id); ?>"
              class="btn-submit"
              style="display:flex; text-decoration:none;"
            >
              <i class="fa fa-sign-in"></i> Login Sekarang
            </a>
            <a href="../login/registrasi.php" class="btn-login-link" style="margin-top: 12px;">
              <i class="fa fa-user-plus"></i> Belum punya akun? Daftar dulu
            </a>
          </div>
        </div>

        <?php endif; ?>

      </div><!-- /form-card -->
    </div><!-- /kolom kiri -->

    <!-- ── Kolom kanan: Ringkasan ── -->
    <div class="sidebar-col">
      <div class="sidebar-sticky">

      <div class="sidebar-card">
        <div class="sidebar-title">Ringkasan Order</div>

        <?php if ($selected_product): ?>
        <div class="summary-paket-name" id="sideName"><?php echo htmlspecialchars($selected_product['name']); ?></div>
        <div class="summary-paket-cat">☁️ Hosting & Domain</div>

        <!-- Fitur -->
        <ul class="fitur-list" id="sideFeatures">
          <?php
          $fitur = $fitur_hosting[$selected_product['name']] ?? $default_fitur;
          foreach ($fitur as $f): ?>
          <li><?php echo $f; ?></li>
          <?php endforeach; ?>
        </ul>

        <!-- Periode info -->
        <div style="font-size:12px; color:var(--text3); margin-bottom: 4px;">Periode: <span id="sidePeriode" style="color:var(--text2); font-weight:600;">1 Bulan</span></div>

        <!-- Baris domain (tampil hanya jika beli domain) -->
        <div id="sideDomainRow" style="display:none; justify-content:space-between; align-items:center; font-size:12px; color:var(--text3); margin-bottom:8px;">
          <span>+ Domain</span>
          <span id="sideDomainPrice" style="color:var(--accent); font-weight:600;"></span>
        </div>

        <div class="summary-total-row">
          <span class="summary-total-label">Total Bayar</span>
          <div>
            <div class="summary-total-val" id="sideTotal">Rp <?php echo number_format($selected_product['price'], 0, ',', '.'); ?></div>
            <div class="summary-period" id="sidePeriodLabel">untuk 1 bulan</div>
          </div>
        </div>

        <?php else: ?>
        <div style="color:var(--text3); font-size:14px;">Pilih paket hosting untuk melihat ringkasan.</div>
        <?php endif; ?>

        <div class="guarantee-badges">
          <div class="guarantee-badge"><i class="fa fa-check-circle"></i> Aktif dalam 24 jam kerja</div>
          <div class="guarantee-badge"><i class="fa fa-lock"></i> SSL Gratis di semua paket</div>
          <div class="guarantee-badge"><i class="fa fa-shield"></i> Uptime 99% SLA Guarantee</div>
          <div class="guarantee-badge"><i class="fa fa-headphones"></i> Support via WhatsApp</div>
        </div>
      </div>

      <!-- Butuh bantuan? -->
      <div class="sidebar-card help-card">
        <div class="help-card-title">💬 Butuh Bantuan?</div>
        <p class="help-card-desc">
          Tim kami siap membantu memilih paket yang tepat dan menjawab pertanyaan teknis.
        </p>
        <a href="https://wa.me/6281246684665?text=Halo+Perkasa,+saya+ingin+tanya+tentang+paket+hosting" target="_blank" class="help-wa-btn">
          <i class="fa fa-whatsapp"></i> Chat via WhatsApp
        </a>
      </div>

      </div><!-- /sidebar-sticky -->
    </div><!-- /sidebar-col -->

  </div><!-- /order-layout -->
</div><!-- /container -->

<!-- Toast -->
<div class="toast" id="toast">
  <span id="toastIcon">ℹ️</span>
  <span id="toastText"></span>
</div>

<!-- ══ Scripts ═══════════════════════════════════════════ -->
<script>
/* ── Data produk dari PHP ── */
const products = <?php
  $prod_js = [];
  foreach ($hosting_products as $p) {
      $prod_js[] = [
          'id'    => $p['id'],
          'name'  => $p['name'],
          'price' => (float)$p['price'],
          'desc'  => $p['description'],
      ];
  }
  echo json_encode($prod_js);
?>;

const fiturMap = <?php echo json_encode($fitur_hosting); ?>;
const defaultFitur = <?php echo json_encode($default_fitur); ?>;

let currentPrice = <?php echo $selected_product ? (float)$selected_product['price'] : 0; ?>;
let currentPeriode = 1;
let currentPaketId = <?php echo $paket_id; ?>;
let currentDomainPrice = 0; // harga domain beli, 0 jika subdomain gratis

/* ── KTP Upload Preview ── */
(function() {
  const input   = document.getElementById('ktpFileInput');
  const area    = document.getElementById('ktpUploadArea');
  const preview = document.getElementById('ktpPreview');
  const ph      = document.getElementById('ktpPlaceholder');
  const img     = document.getElementById('ktpPreviewImg');
  const name    = document.getElementById('ktpPreviewName');

  if (!input) return;

  function showPreview(file) {
    if (!file || !file.type.startsWith('image/')) return;
    if (file.size > 3 * 1024 * 1024) {
      showToast('⚠️', 'Ukuran file KTP maksimal 3 MB.', true);
      input.value = '';
      return;
    }
    const reader = new FileReader();
    reader.onload = e => {
      img.src = e.target.result;
      name.innerHTML = `✅ ${file.name} <span class="ktp-clear" onclick="clearKtp()">Ganti</span>`;
      preview.style.display = 'block';
      ph.style.display = 'none';
    };
    reader.readAsDataURL(file);
  }

  input.addEventListener('change', () => { if (input.files[0]) showPreview(input.files[0]); });

  area.addEventListener('dragover', e => { e.preventDefault(); area.classList.add('drag-over'); });
  area.addEventListener('dragleave', () => area.classList.remove('drag-over'));
  area.addEventListener('drop', e => {
    e.preventDefault();
    area.classList.remove('drag-over');
    if (e.dataTransfer.files[0]) {
      // Manually assign to input via DataTransfer
      const dt = new DataTransfer();
      dt.items.add(e.dataTransfer.files[0]);
      input.files = dt.files;
      showPreview(e.dataTransfer.files[0]);
    }
  });
})();

function clearKtp() {
  const input   = document.getElementById('ktpFileInput');
  const preview = document.getElementById('ktpPreview');
  const ph      = document.getElementById('ktpPlaceholder');
  if (input) input.value = '';
  if (preview) preview.style.display = 'none';
  if (ph) ph.style.display = 'block';
}

/* ── Format Rupiah ── */
function formatRp(n) {
  return 'Rp ' + Math.floor(n).toLocaleString('id-ID');
}

/* ── Ganti paket ── */
function switchPaket(id, name, price, desc) {
  currentPrice = price;
  currentPaketId = id;

  // Update hero
  document.getElementById('heroName').textContent = name;
  document.getElementById('heroDesc').textContent = desc;
  document.getElementById('heroPrice').textContent = formatRp(price);

  // Update sidebar
  document.getElementById('sideName').textContent = name;

  // Update fitur sidebar
  const fiturList = document.getElementById('sideFeatures');
  if (fiturList) {
    const fiturs = fiturMap[name] || defaultFitur;
    fiturList.innerHTML = fiturs.map(f => `<li>${f}</li>`).join('');
  }

  // Update semua hidden input paket_id
  document.querySelectorAll('input[name="paket_id"]').forEach(el => el.value = id);

  // Update periode prices
  updateTotalDisplay();

  // Update tombol aktif
  document.querySelectorAll('.paket-btn').forEach(btn => btn.classList.remove('active'));
  event.currentTarget.classList.add('active');
}

/* ── Pilih periode ── */
function selectPeriode(el, bulan) {
  // Remove selected dari semua sibling
  el.closest('.period-options').querySelectorAll('.period-opt').forEach(opt => {
    opt.classList.remove('selected');
  });
  el.classList.add('selected');
  el.querySelector('input[type=radio]').checked = true;
  currentPeriode = bulan;
  updateTotalDisplay();
}

/* ── Update ringkasan total ── */
function updateTotalDisplay() {
  let diskon = 1;
  if (currentPeriode >= 12) diskon = 0.90;
  else if (currentPeriode >= 6) diskon = 0.95;

  const total = currentPrice * currentPeriode * diskon + currentDomainPrice;

  const sideTotal      = document.getElementById('sideTotal');
  const sidePeriode    = document.getElementById('sidePeriode');
  const sidePeriodLabel = document.getElementById('sidePeriodLabel');
  const sideDomainRow  = document.getElementById('sideDomainRow');

  if (sideTotal) sideTotal.textContent = formatRp(total);
  if (sidePeriode) sidePeriode.textContent = currentPeriode + ' Bulan' + (diskon < 1 ? ' (hemat ' + Math.round((1 - diskon) * 100) + '%)' : '');
  if (sidePeriodLabel) sidePeriodLabel.textContent = 'untuk ' + currentPeriode + ' bulan';

  // Tampilkan baris biaya domain di sidebar jika ada
  if (sideDomainRow) {
    if (currentDomainPrice > 0) {
      sideDomainRow.style.display = 'flex';
      const valEl = document.getElementById('sideDomainPrice');
      if (valEl) valEl.textContent = '+ ' + formatRp(currentDomainPrice) + '/thn';
    } else {
      sideDomainRow.style.display = 'none';
    }
  }
}

/* ── Switch mode guest/login ── */
function switchMode(mode) {
  const tabGuest = document.getElementById('tabGuest');
  const tabLogin = document.getElementById('tabLogin');
  const panelGuest = document.getElementById('panelGuest');
  const panelLoginRedirect = document.getElementById('panelLoginRedirect');

  if (!tabGuest) return; // mode B sudah login, tidak ada tabs

  if (mode === 'guest') {
    tabGuest.classList.add('active');
    tabLogin.classList.remove('active');
    panelGuest.classList.add('active');
    panelLoginRedirect.classList.remove('active');
  } else {
    tabLogin.classList.add('active');
    tabGuest.classList.remove('active');
    panelLoginRedirect.classList.add('active');
    panelGuest.classList.remove('active');
  }
}

/* ── Password strength ── */
function checkPwd(val) {
  const bar = document.getElementById('pwdBar');
  const hint = document.getElementById('pwdHint');
  if (!bar) return;

  let strength = 0;
  if (val.length >= 8) strength++;
  if (/[A-Z]/.test(val)) strength++;
  if (/[0-9]/.test(val)) strength++;
  if (/[^A-Za-z0-9]/.test(val)) strength++;

  const colors = ['#ef4444','#f97316','#eab308','#22c55e'];
  const labels = ['Sangat lemah','Lemah','Cukup kuat','Kuat'];
  const w = [25, 50, 75, 100];

  if (val.length === 0) {
    bar.style.width = '0%';
    hint.textContent = 'Minimal 8 karakter';
    hint.style.color = 'var(--text3)';
    return;
  }
  const idx = Math.min(strength - 1, 3);
  bar.style.width = w[idx] + '%';
  bar.style.background = colors[idx];
  hint.textContent = labels[idx];
  hint.style.color = colors[idx];
}

/* ── Toast ── */
function showToast(icon, text, isError = false) {
  const t = document.getElementById('toast');
  document.getElementById('toastIcon').textContent = icon;
  document.getElementById('toastText').textContent = text;
  t.className = 'toast show' + (isError ? ' error' : '');
  setTimeout(() => t.className = 'toast', 4000);
}

/* ── Form validation & submit ── */
function attachFormSubmit(formId, btnId, spinnerId) {
  const form = document.getElementById(formId);
  if (!form) return;

  form.addEventListener('submit', function(e) {
    e.preventDefault();

    // Validasi dasar
    const required = form.querySelectorAll('[required]');
    let valid = true;
    required.forEach(el => {
      if (el.type === 'checkbox' && !el.checked) {
        valid = false;
        showToast('⚠️', 'Harap setujui ketentuan layanan.', true);
      } else if (el.type !== 'checkbox' && !el.value.trim()) {
        valid = false;
        el.style.borderColor = '#ef4444';
        el.addEventListener('input', () => el.style.borderColor = '', { once: true });
      }
    });

    if (!valid) {
      if (document.querySelector('[required]:invalid') || !document.querySelector('[required][type=checkbox]:checked')) {
        // Sudah ditampilkan di atas
        return;
      }
      showToast('⚠️', 'Mohon lengkapi semua field yang wajib diisi.', true);
      return;
    }

    // Cek KTP upload (mode guest)
    const ktpInput = form.querySelector('[name="ktp_file"]');
    if (ktpInput && ktpInput.required && (!ktpInput.files || !ktpInput.files[0])) {
      showToast('⚠️', 'Mohon upload foto KTP Anda untuk verifikasi.', true);
      document.getElementById('ktpUploadArea').style.borderColor = '#ef4444';
      setTimeout(() => { document.getElementById('ktpUploadArea').style.borderColor = ''; }, 3000);
      return;
    }

    // Cek domain
    const domainTypeRadios = form.querySelectorAll('[name="domain_type"]');
    let domainType = 'subdomain';
    domainTypeRadios.forEach(r => { if (r.checked) domainType = r.value; });

    if (domainType === 'subdomain') {
      const domainField = form.querySelector('[name="domain"]');
      if (domainField && !/^[a-z0-9-]{3,}$/i.test(domainField.value.trim())) {
        showToast('⚠️', 'Nama subdomain hanya boleh huruf, angka, dan tanda hubung (-), minimal 3 karakter.', true);
        domainField.focus();
        return;
      }
    } else {
      // Beli domain — pastikan sudah pilih TLD
      const customField = form.querySelector('[name="domain_custom"]');
      const tldField    = form.querySelector('[name="domain_tld"]');
      if (!customField || !customField.value.trim() || !tldField || !tldField.value.trim()) {
        showToast('⚠️', 'Silakan cari dan pilih ekstensi domain terlebih dahulu.', true);
        return;
      }
    }

    // Cek password konfirmasi (mode guest)
    const pwd = form.querySelector('#guestPassword');
    const pwd2 = form.querySelector('#guestPasswordConfirm');
    if (pwd && pwd2 && pwd.value !== pwd2.value) {
      showToast('❌', 'Password dan konfirmasi password tidak sama.', true);
      pwd2.focus();
      return;
    }

    if (pwd && pwd.value.length < 8) {
      showToast('❌', 'Password minimal 8 karakter.', true);
      pwd.focus();
      return;
    }

    // Tampilkan loading
    const btn = document.getElementById(btnId);
    const spinner = document.getElementById(spinnerId);
    if (btn) btn.disabled = true;
    if (spinner) spinner.style.display = 'block';

    // Submit form
    form.submit();
  });
}

// Inisialisasi
attachFormSubmit('formOrderGuest', 'btnSubmitGuest', 'spinnerGuest');
attachFormSubmit('formOrderLogin', 'btnSubmitLogin', 'spinnerLogin');

/* ── Domain type switch ── */
function switchDomainType(form, type) {
  const suffix = form === 'login' ? 'Login' : 'Guest';

  // Update radio UI
  const toggle = document.getElementById('domainTypeToggle' + suffix);
  if (toggle) {
    toggle.querySelectorAll('.domain-type-opt').forEach(opt => {
      opt.classList.toggle('selected', opt.querySelector('input').value === type);
    });
  }

  // Show/hide panels
  const panelSub  = document.getElementById('panelSubdomain' + suffix);
  const panelBeli = document.getElementById('panelBeli' + suffix);
  if (panelSub)  panelSub.classList.toggle('active', type === 'subdomain');
  if (panelBeli) panelBeli.classList.toggle('active', type === 'beli');

  // Required toggling
  const subdomainInput = document.getElementById('domain' + suffix);
  if (subdomainInput) subdomainInput.required = (type === 'subdomain');

  // FIX: reset harga domain saat balik ke subdomain gratis
  if (type === 'subdomain') {
    const custEl  = document.getElementById('domainCustom' + suffix);
    const tldEl   = document.getElementById('domainTld'    + suffix);
    const priceEl = document.getElementById('domainPrice'  + suffix);
    if (custEl)  custEl.value  = '';
    if (tldEl)   tldEl.value   = '';
    if (priceEl) priceEl.value = '0';
    const resultArea = document.getElementById('domainResult' + suffix);
    if (resultArea) resultArea.style.display = 'none';
    currentDomainPrice = 0;
    updateTotalDisplay();
  }
}

/* ── TLD pricing table (harga & konfigurasi lokal) ── */
/* ── TLD pricing table (fallback lokal — sumber utama tetap dari check_domain.php) ──
   Harga di sini HARUS disinkronkan manual jika admin mengubah harga di
   /admin/domain_pricing.php, karena ini hanya dipakai sebagai fallback saat
   request ke check_domain.php gagal mengembalikan field price. */
const TLD_LIST = [
  { tld: '.id',     price: 345900, sale: false },
  { tld: '.co.id',  price: 398990, sale: false },
  { tld: '.web.id', price: 68390,  sale: false },
  { tld: '.or.id',  price: 68390,  sale: false },
  { tld: '.ac.id',  price: 68390,  sale: false },
  { tld: '.sch.id', price: 68390,  sale: false },
  { tld: '.biz.id', price: 65190,  sale: false },
  { tld: '.my.id',  price: 27990,  sale: false },
  { tld: '.com',    price: 210000, sale: false },
  { tld: '.net',    price: 232900, sale: false },
  { tld: '.org',    price: 203590, sale: false },
  { tld: '.xyz',    price: 37090,  sale: false },
];

// Cache hasil cek agar tidak double-request
const domainCheckCache = {};
let domainSearchTimer = {};

/* ── Live search: debounce 700ms ── */
function liveSearchDomain(form, val) {
  clearTimeout(domainSearchTimer[form]);
  const keyword = val.trim().toLowerCase().replace(/[^a-z0-9-]/g, '');
  if (keyword.length < 2) return;
  domainSearchTimer[form] = setTimeout(() => searchDomain(form), 700);
}

/* ── Cek satu TLD via check_domain.php (async) ── */
async function checkOneTld(keyword, tld) {
  const cacheKey = keyword + tld;
  if (domainCheckCache[cacheKey] !== undefined) return domainCheckCache[cacheKey];
  try {
    const res  = await fetch(`check_domain.php?domain=${encodeURIComponent(keyword)}&tld=${encodeURIComponent(tld)}`, {
      method: 'GET', headers: { 'Accept': 'application/json' }
    });
    const data = await res.json();
    domainCheckCache[cacheKey] = data;
    return data;
  } catch (err) {
    console.warn('[domain-check] Gagal cek ' + keyword + tld, err);
    return { tld, available: null, price: 0 };
  }
}

/* ── Render satu card setelah hasil RDAP masuk ── */
function renderTldCard(form, keyword, result, cardEl) {
  const { tld, available, price, sale } = result;
  const localTld   = TLD_LIST.find(t => t.tld === tld) || {};
  const finalPrice = price  || localTld.price || 0;
  const finalSale  = sale   ?? localTld.sale  ?? false;

  let availHtml, clickable;
  if (available === true) {
    availHtml = `<div class="tld-avail">✓ Tersedia</div>`;
    clickable  = true;
  } else if (available === false) {
    availHtml = `<div class="tld-avail na">✗ Sudah terdaftar</div>`;
    clickable  = false;
  } else {
    availHtml = `<div class="tld-avail" style="color:var(--accent)">⚠ Tidak dapat dicek</div>`;
    clickable  = false;
  }

  cardEl.innerHTML = `
    <div class="tld-left">
      <div class="tld-name-full">${keyword}<span>${tld}</span></div>
      ${availHtml}
    </div>
    <div class="tld-right">
      <div class="tld-price">Rp ${finalPrice.toLocaleString('id-ID')}${finalSale ? '<span class="tld-sale-badge">SALE</span>' : ''}</div>
      <div class="tld-price-label">/tahun</div>
    </div>
  `;

  if (clickable) {
    cardEl.classList.remove('unavailable');
    cardEl.style.pointerEvents = '';
    cardEl.style.opacity       = '';
    cardEl.style.animation     = '';
    cardEl.onclick = () => selectTld(form, keyword, tld, finalPrice);
  } else {
    cardEl.classList.add('unavailable');
    cardEl.style.pointerEvents = 'none';
    cardEl.style.opacity       = '0.38';
    cardEl.style.animation     = '';
    cardEl.onclick             = null;
  }
}

/* ── Cari domain: skeleton → cek semua TLD paralel ── */
async function searchDomain(form) {
  const suffix      = form === 'login' ? 'Login' : 'Guest';
  const searchInput = document.getElementById('domainSearch' + suffix);
  const keyword     = (searchInput ? searchInput.value : '').trim().toLowerCase().replace(/[^a-z0-9-]/g, '');

  if (keyword.length < 2) {
    showToast('⚠️', 'Nama domain minimal 2 karakter.', true);
    return;
  }

  // Reset pilihan sebelumnya
  document.getElementById('domainCustom' + suffix).value = '';
  document.getElementById('domainTld'    + suffix).value = '';
  document.getElementById('domainPrice'  + suffix).value = '0';
  currentDomainPrice = 0;
  updateTotalDisplay();

  const selInfo = document.getElementById('domainSelectedInfo' + suffix);
  if (selInfo) selInfo.style.display = 'none';

  const resultArea = document.getElementById('domainResult' + suffix);
  const grid       = document.getElementById('domainTldGrid' + suffix);
  if (!resultArea || !grid) return;

  // Buat card placeholder "memeriksa..." untuk setiap TLD
  const cards = TLD_LIST.map(t => {
    const card = document.createElement('div');
    card.className = 'domain-tld-card';
    card.style.pointerEvents = 'none';
    card.style.opacity       = '0.45';
    card.innerHTML = `
      <div class="tld-left">
        <div class="tld-name-full">${keyword}<span>${t.tld}</span></div>
        <div class="tld-avail" style="color:var(--text3)">⏳ Memeriksa...</div>
      </div>
      <div class="tld-right">
        <div class="tld-price">Rp ${t.price.toLocaleString('id-ID')}${t.sale ? '<span class="tld-sale-badge">SALE</span>' : ''}</div>
        <div class="tld-price-label">/tahun</div>
      </div>
    `;
    return card;
  });

  grid.innerHTML = '';
  cards.forEach(c => grid.appendChild(c));
  resultArea.style.display = 'block';

  // Cek semua TLD paralel, update tiap card saat hasil masuk
  await Promise.allSettled(
    TLD_LIST.map((t, i) =>
      checkOneTld(keyword, t.tld).then(result => renderTldCard(form, keyword, result, cards[i]))
    )
  );
}

/* ── Pilih TLD yang tersedia ── */
function selectTld(form, keyword, tld, price) {
  const suffix = form === 'login' ? 'Login' : 'Guest';

  document.getElementById('domainCustom' + suffix).value = keyword + tld;
  document.getElementById('domainTld'    + suffix).value = tld;
  document.getElementById('domainPrice'  + suffix).value = price;

  // Tandai card terpilih
  const grid = document.getElementById('domainTldGrid' + suffix);
  if (grid) {
    grid.querySelectorAll('.domain-tld-card').forEach(c => c.classList.remove('selected-tld'));
    grid.querySelectorAll('.domain-tld-card').forEach(c => {
      const span = c.querySelector('.tld-name-full span');
      if (span && span.textContent === tld) c.classList.add('selected-tld');
    });
  }

  // Info box konfirmasi
  const selInfo = document.getElementById('domainSelectedInfo' + suffix);
  if (selInfo) {
    selInfo.innerHTML = `<i class="fa fa-check-circle"></i> <strong>${keyword + tld}</strong> dipilih — Rp ${price.toLocaleString('id-ID')}/tahun. Harga domain ditambahkan ke total.`;
    selInfo.style.display = 'flex';
  }

  currentDomainPrice = price;
  updateTotalDisplay();
}

// Highlight field saat user mulai isi
document.querySelectorAll('input, select, textarea').forEach(el => {
  el.addEventListener('focus', function() {
    this.style.borderColor = '';
  });
});
</script>

</body>
</html>
