<?php
// =====================================================
//  order/order_sukses_hosting.php
//  Halaman konfirmasi setelah order hosting berhasil
// =====================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth_check.php';

// Harus sudah login
requireLevel(3);

$order_number = htmlspecialchars($_GET['order'] ?? '');
$paket_name   = htmlspecialchars($_GET['paket']  ?? 'Hosting');

// Ambil detail order
$order = null;
if ($order_number) {
    $st = $conn->prepare("
        SELECT o.*, p.name as product_name, p.price, p.description
        FROM tblorders o
        JOIN tblproducts p ON o.productid = p.id
        WHERE o.order_number = ? AND o.userid = ? AND o.order_type = 'hosting'
        LIMIT 1
    ");
    $st->bind_param('si', $order_number, $_SESSION['user_id']);
    $st->execute();
    $order = $st->get_result()->fetch_assoc();
    $st->close();
}

// Ambil domain dari tblhosting
$domain_aktif = '';
if ($order) {
    $hst = $conn->prepare("SELECT domain FROM tblhosting WHERE userid=? AND packageid=? ORDER BY created_at DESC LIMIT 1");
    $hst->bind_param('ii', $_SESSION['user_id'], $order['productid']);
    $hst->execute();
    $hrow = $hst->get_result()->fetch_assoc();
    $hst->close();
    $domain_aktif = $hrow['domain'] ?? '';
}

// Ambil invoice dan deadline pembayaran
$invoice  = null;
$deadline = null;
if ($order) {
    $si = $conn->prepare("SELECT id, total, duedate FROM tblinvoices WHERE order_id=? AND userid=? LIMIT 1");
    $si->bind_param('ii', $order['id'], $_SESSION['user_id']);
    $si->execute();
    $invoice = $si->get_result()->fetch_assoc();
    $si->close();
    $deadline = $order['payment_deadline'] ?? ($invoice['duedate'] ?? null);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Order Berhasil! — PERKASA SOLUSINDO</title>
  <link rel="icon" type="image/png" href="../assets/images/CDR LOGO PERKASA Putih with border.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/fontawesome.css">
  <style>
    :root {
      --bg: #06050f; --surface: #120f28; --surface2: #1a1535;
      --border2: rgba(200,140,255,0.15);
      --accent: #f97316; --accent-glow: rgba(249,115,22,0.35);
      --teal: #14b8a6; --violet: #7c3aed;
      --magenta: #c026d3; --magenta-light: #e040fb;
      --text: #f1f5f9; --text2: #b4aed4; --text3: #6b6490;
      --font-display: 'Syne', sans-serif;
      --font-body: 'DM Sans', sans-serif;
      --radius: 14px; --radius-lg: 22px;
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      background: radial-gradient(ellipse 120% 60% at 0% 0%, #12083a 0%, transparent 55%),
                  linear-gradient(160deg, #06040e 0%, #0c0820 40%, #1a0630 70%);
      background-attachment: fixed;
      color: var(--text);
      font-family: var(--font-body);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 40px 20px;
    }
    .card {
      background: rgba(18,15,40,0.9);
      border: 1px solid var(--border2);
      border-radius: var(--radius-lg);
      max-width: 560px;
      width: 100%;
      padding: 48px 40px;
      text-align: center;
      position: relative;
      overflow: hidden;
    }
    .card::before {
      content: '';
      position: absolute;
      top: -60px; left: 50%;
      transform: translateX(-50%);
      width: 220px; height: 220px;
      background: radial-gradient(circle, rgba(20,184,166,0.25), transparent 70%);
      border-radius: 50%;
    }
    .success-icon {
      font-size: 72px;
      margin-bottom: 20px;
      display: block;
      animation: popIn 0.5s cubic-bezier(0.34,1.56,0.64,1) both;
    }
    @keyframes popIn {
      from { transform: scale(0.5); opacity: 0; }
      to   { transform: scale(1);   opacity: 1; }
    }
    h1 {
      font-family: var(--font-display);
      font-size: clamp(22px, 4vw, 30px);
      font-weight: 800;
      margin-bottom: 8px;
    }
    h1 em { font-style: normal; color: var(--teal); }
    .subtitle { color: var(--text2); font-size: 15px; margin-bottom: 32px; }
    .info-rows { text-align: left; margin-bottom: 28px; }
    .info-row {
      display: flex;
      justify-content: space-between;
      padding: 12px 0;
      border-bottom: 1px solid rgba(200,140,255,0.08);
      font-size: 14px;
      gap: 12px;
    }
    .info-row:last-child { border-bottom: none; }
    .info-label { color: var(--text3); flex-shrink: 0; }
    .info-val { font-weight: 600; text-align: right; color: var(--text); }
    .info-val.accent { color: var(--accent); }
    .info-val.teal { color: var(--teal); }

    .steps-next {
      background: rgba(249,115,22,0.07);
      border: 1px solid rgba(249,115,22,0.2);
      border-radius: var(--radius);
      padding: 20px;
      text-align: left;
      margin-bottom: 24px;
    }
    .steps-next-title {
      font-family: var(--font-display);
      font-size: 13px;
      font-weight: 700;
      color: var(--accent);
      margin-bottom: 14px;
      text-transform: uppercase;
      letter-spacing: 0.07em;
    }
    .next-step {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      margin-bottom: 12px;
    }
    .next-step:last-child { margin-bottom: 0; }
    .next-num {
      width: 24px; height: 24px;
      border-radius: 50%;
      background: var(--accent);
      display: flex; align-items: center; justify-content: center;
      font-size: 11px; font-weight: 700;
      flex-shrink: 0;
    }
    .next-text { font-size: 13px; color: var(--text2); }

    .btn-primary {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      width: 100%;
      padding: 14px;
      background: linear-gradient(135deg, var(--accent), #fb923c);
      border: none;
      border-radius: 12px;
      color: #fff;
      font-family: var(--font-display);
      font-size: 15px;
      font-weight: 700;
      cursor: pointer;
      text-decoration: none;
      margin-bottom: 12px;
      box-shadow: 0 6px 20px var(--accent-glow);
      transition: 0.3s;
    }
    .btn-primary:hover { transform: translateY(-2px); }
    .btn-outline {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      width: 100%;
      padding: 13px;
      border: 1.5px solid var(--border2);
      border-radius: 12px;
      color: var(--text2);
      font-size: 14px;
      font-weight: 600;
      text-decoration: none;
      transition: 0.3s;
    }
    .btn-outline:hover { border-color: var(--violet); color: var(--text); }

    .confetti { font-size: 22px; letter-spacing: 4px; margin-bottom: 8px; }
  </style>
</head>
<body>
<div class="card">
  <span class="success-icon">✅</span>
  <div class="confetti">☁️ 🎉 ☁️</div>
  <h1>Order <em>Berhasil</em> Dikirim!</h1>
  <p class="subtitle">
    Pesanan hosting Anda telah kami terima.<br>
    Tim kami akan memproses dalam <strong>24 jam kerja</strong>.
  </p>

  <?php if ($order): ?>
  <div class="info-rows">
    <div class="info-row">
      <span class="info-label">Nomor Order</span>
      <span class="info-val accent"><?php echo htmlspecialchars($order['order_number']); ?></span>
    </div>
    <div class="info-row">
      <span class="info-label">Paket</span>
      <span class="info-val"><?php echo htmlspecialchars($order['product_name']); ?></span>
    </div>
    <?php if ($domain_aktif): ?>
    <div class="info-row">
      <span class="info-label">Domain</span>
      <span class="info-val teal"><?php echo htmlspecialchars($domain_aktif); ?></span>
    </div>
    <?php endif; ?>
    <?php if ($invoice): ?>
    <div class="info-row">
      <span class="info-label">Total Tagihan</span>
      <span class="info-val accent" style="font-size:16px;font-weight:800;">
        Rp <?php echo number_format((float)$invoice['total'], 0, ',', '.'); ?>
      </span>
    </div>
    <?php endif; ?>
    <?php if ($deadline): ?>
    <div class="info-row">
      <span class="info-label">Batas Bayar</span>
      <span class="info-val" style="color:#f87171;font-weight:700;">
        ⏰ <?php echo date('d M Y H:i', strtotime($deadline)); ?> WIB
      </span>
    </div>
    <?php endif; ?>
    <div class="info-row">
      <span class="info-label">Status</span>
      <span class="info-val" style="color:var(--magenta-light);">⏳ Menunggu Pembayaran</span>
    </div>
  </div>

  <!-- Info Rekening Pembayaran -->
  <div style="background:rgba(249,115,22,0.07);border:1.5px solid rgba(249,115,22,0.25);border-radius:14px;padding:20px;text-align:left;margin-bottom:22px;">
    <div style="font-size:12px;font-weight:700;color:var(--accent);text-transform:uppercase;letter-spacing:.07em;margin-bottom:14px;">
      💳 Rekening Pembayaran
    </div>
    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:10px;">
      <div>
        <div style="font-size:12px;color:var(--text3);margin-bottom:2px;">Bank BCA</div>
        <div style="font-size:20px;font-weight:800;color:var(--text);letter-spacing:2px;font-family:var(--font-display);">0184246283</div>
        <div style="font-size:13px;color:var(--text2);margin-top:2px;">a.n. <strong>TECH PERKASA SOLUSINDO</strong></div>
      </div>
      <button onclick="copyRek()" id="btnCopyRek"
        style="background:rgba(249,115,22,0.15);border:1px solid rgba(249,115,22,0.4);color:var(--accent);font-size:12px;font-weight:700;padding:8px 16px;border-radius:8px;cursor:pointer;transition:.2s;">
        📋 Salin
      </button>
    </div>
    <div style="background:rgba(248,113,113,0.08);border:1px solid rgba(248,113,113,0.2);border-radius:8px;padding:10px 14px;font-size:12px;color:#fca5a5;line-height:1.7;margin-top:8px;">
      ⚠️ <strong>Penting:</strong> Lakukan pembayaran dan upload bukti transfer di
      <strong>Dashboard → Layanan Hosting</strong> sebelum batas waktu.
      Order yang melewati batas waktu akan <strong>dihapus otomatis</strong>.
    </div>
  </div>
  <?php endif; ?>

  <div class="steps-next">
    <div class="steps-next-title">Langkah Selanjutnya</div>
    <div class="next-step">
      <div class="next-num">1</div>
      <div class="next-text">Transfer pembayaran ke rekening BCA <strong style="color:var(--text);">0184246283</strong> a.n. Tech Perkasa Solusindo.</div>
    </div>
    <div class="next-step">
      <div class="next-num">2</div>
      <div class="next-text">Upload bukti transfer di <strong style="color:var(--teal);">Dashboard → Layanan Hosting</strong>. Admin akan segera memverifikasi.</div>
    </div>
    <div class="next-step">
      <div class="next-num">3</div>
      <div class="next-text">Setelah pembayaran dikonfirmasi, akun hosting & credential cPanel langsung dikirim ke email Anda.</div>
    </div>
  </div>

  <a href="/client/client_dashboard.php?view=layanan_hosting" class="btn-primary">
    <i class="fa fa-cloud"></i> Lihat & Upload Bukti Pembayaran
  </a>
  <a href="https://wa.me/6281246684665?text=Halo+Perkasa,+saya+baru+order+hosting+<?php echo urlencode($order_number); ?>" target="_blank" class="btn-outline">
    <i class="fa fa-whatsapp"></i> Chat Admin via WhatsApp
  </a>

<script>
function copyRek() {
  navigator.clipboard.writeText('0184246283').then(function() {
    var btn = document.getElementById('btnCopyRek');
    btn.textContent = '✅ Tersalin!';
    setTimeout(function() { btn.textContent = '📋 Salin'; }, 2000);
  });
}
</script>
</div>
</body>
</html>
