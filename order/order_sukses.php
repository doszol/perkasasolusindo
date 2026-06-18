<?php
/**
 * Perkasa Solusindo — Order WiFi Sukses
 * Path: /public_html/order/order_sukses.php
 */

session_start();

// Guard: hanya boleh diakses setelah proses order berhasil
if (empty($_SESSION['order_success'])) {
    header('Location: ../../index.php');
    exit;
}

$d = $_SESSION['order_success'];
unset($_SESSION['order_success']);  // one-time show

function rupiah(float $n): string {
    return 'Rp ' . number_format($n, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Order Berhasil — Perkasa Solusindo</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" crossorigin="anonymous">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#0b0f1a;--bg2:#111827;--bg3:#1a2235;
  --border:rgba(255,255,255,.08);--accent:#f97316;
  --green:#22c55e;--blue:#3b82f6;
  --text1:#f1f5f9;--text2:#94a3b8;--text3:#64748b;
  --font-head:'Syne',sans-serif;--font-body:'DM Sans',sans-serif;
  --radius:14px;
}
body{background:var(--bg);color:var(--text1);font-family:var(--font-body);min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:flex-start;padding:40px 20px 80px}

/* Confetti canvas */
#confetti-canvas{position:fixed;inset:0;pointer-events:none;z-index:0}

.card{background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius);padding:40px 36px;max-width:560px;width:100%;position:relative;z-index:1}

/* Success icon */
.icon-circle{width:72px;height:72px;border-radius:50%;background:rgba(34,197,94,.12);border:2px solid var(--green);display:flex;align-items:center;justify-content:center;margin:0 auto 20px;animation:popIn .5s cubic-bezier(.68,-.55,.27,1.55)}
@keyframes popIn{0%{transform:scale(0);opacity:0}100%{transform:scale(1);opacity:1}}
.icon-circle i{font-size:30px;color:var(--green)}

.title{font-family:var(--font-head);font-size:26px;font-weight:800;text-align:center;margin-bottom:6px}
.subtitle{text-align:center;color:var(--text2);font-size:14px;margin-bottom:28px}

/* Order number badge */
.order-badge{background:rgba(249,115,22,.1);border:1px solid rgba(249,115,22,.25);border-radius:8px;padding:12px 18px;text-align:center;margin-bottom:24px}
.order-badge-label{font-size:11px;color:var(--text3);text-transform:uppercase;letter-spacing:.6px;margin-bottom:4px}
.order-badge-num{font-family:var(--font-head);font-size:20px;font-weight:800;color:var(--accent);letter-spacing:1px}

/* Detail rows */
.detail-row{display:flex;justify-content:space-between;align-items:flex-start;padding:10px 0;border-bottom:1px solid var(--border);gap:12px;font-size:14px}
.detail-row:last-child{border-bottom:none}
.detail-label{color:var(--text3);flex-shrink:0;width:160px}
.detail-val{color:var(--text1);text-align:right;font-weight:500}

/* Status timeline */
.timeline{margin:24px 0}
.tl-item{display:flex;align-items:flex-start;gap:12px;margin-bottom:20px;position:relative}
.tl-item::before{content:'';position:absolute;left:15px;top:32px;width:2px;height:calc(100% - 8px);background:var(--border)}
.tl-item:last-child::before{display:none}
.tl-dot{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:12px}
.tl-dot.active{background:rgba(34,197,94,.15);border:2px solid var(--green);color:var(--green)}
.tl-dot.wait{background:var(--bg3);border:2px solid var(--border);color:var(--text3)}
.tl-body{padding-top:4px}
.tl-title{font-size:13px;font-weight:600;color:var(--text1)}
.tl-desc{font-size:12px;color:var(--text3);margin-top:2px}

/* Buttons */
.btn-row{display:flex;gap:12px;margin-top:28px;flex-wrap:wrap}
.btn-primary{flex:1;min-width:140px;padding:13px;background:linear-gradient(135deg,var(--accent),#ea580c);border:none;border-radius:8px;color:#fff;font-family:var(--font-head);font-size:14px;font-weight:700;cursor:pointer;text-decoration:none;display:flex;align-items:center;justify-content:center;gap:8px;transition:.2s}
.btn-primary:hover{opacity:.9}
.btn-outline{flex:1;min-width:140px;padding:13px;background:transparent;border:1px solid var(--border);border-radius:8px;color:var(--text2);font-family:var(--font-head);font-size:14px;font-weight:600;cursor:pointer;text-decoration:none;display:flex;align-items:center;justify-content:center;gap:8px;transition:.2s}
.btn-outline:hover{border-color:var(--accent);color:var(--accent)}

.note-box{background:rgba(59,130,246,.08);border:1px solid rgba(59,130,246,.2);border-radius:8px;padding:14px 16px;font-size:13px;color:var(--blue);line-height:1.6;margin-top:20px}
.note-box i{margin-right:6px}
</style>
</head>
<body>
<canvas id="confetti-canvas"></canvas>

<div class="card">

  <div class="icon-circle">
    <i class="fa fa-check"></i>
  </div>

  <div class="title">Order Berhasil Dikirim! 🎉</div>
  <div class="subtitle">Selamat datang, <strong><?= htmlspecialchars($d['client_name']) ?></strong>!<br>Order WiFi Anda sudah kami terima.</div>

  <div class="order-badge">
    <div class="order-badge-label">Nomor Order Anda</div>
    <div class="order-badge-num"><?= htmlspecialchars($d['order_number']) ?></div>
  </div>

  <!-- Detail -->
  <div class="detail-row"><span class="detail-label">Paket</span><span class="detail-val"><?= htmlspecialchars($d['paket_name']) ?></span></div>
  <div class="detail-row"><span class="detail-label">Kecepatan</span><span class="detail-val"><?= htmlspecialchars($d['paket_speed']) ?></span></div>
  <div class="detail-row"><span class="detail-label">Tarif Bulanan</span><span class="detail-val" style="color:var(--accent)"><?= rupiah((float)$d['paket_price']) ?>/bulan</span></div>
  <div class="detail-row"><span class="detail-label">Alamat Pasang</span><span class="detail-val"><?= htmlspecialchars($d['alamat']) ?></span></div>
  <div class="detail-row"><span class="detail-label">Email Akun</span><span class="detail-val"><?= htmlspecialchars($d['email']) ?></span></div>
  <div class="detail-row"><span class="detail-label">Status Pembayaran</span><span class="detail-val" style="color:var(--blue)">Bayar Setelah Instalasi</span></div>

  <!-- Timeline -->
  <div class="timeline">
    <div class="tl-item">
      <div class="tl-dot active"><i class="fa fa-file-alt"></i></div>
      <div class="tl-body">
        <div class="tl-title">Order Diterima ✓</div>
        <div class="tl-desc">Formulir Anda berhasil masuk ke sistem kami</div>
      </div>
    </div>
    <div class="tl-item">
      <div class="tl-dot wait"><i class="fa fa-search"></i></div>
      <div class="tl-body">
        <div class="tl-title">Verifikasi Admin</div>
        <div class="tl-desc">Tim kami memverifikasi data KTP & cek coverage area (1–2 hari kerja)</div>
      </div>
    </div>
    <div class="tl-item">
      <div class="tl-dot wait"><i class="fa fa-calendar-alt"></i></div>
      <div class="tl-body">
        <div class="tl-title">Penjadwalan Instalasi</div>
        <div class="tl-desc">Teknisi kami menghubungi Anda via WhatsApp untuk konfirmasi jadwal</div>
      </div>
    </div>
    <div class="tl-item">
      <div class="tl-dot wait"><i class="fa fa-tools"></i></div>
      <div class="tl-body">
        <div class="tl-title">Instalasi WiFi</div>
        <div class="tl-desc">Teknisi datang ke lokasi & memasang perangkat</div>
      </div>
    </div>
    <div class="tl-item">
      <div class="tl-dot wait"><i class="fa fa-wifi"></i></div>
      <div class="tl-body">
        <div class="tl-title">WiFi Aktif & Tagihan Pertama</div>
        <div class="tl-desc">Layanan aktif — pembayaran tagihan pertama setelah instalasi selesai</div>
      </div>
    </div>
  </div>

  <div class="note-box">
    <i class="fa fa-envelope"></i>
    Notifikasi status order akan dikirim ke <strong><?= htmlspecialchars($d['email']) ?></strong>. Pantau juga di dashboard klien Anda setelah login.
  </div>

  <div class="btn-row">
    <a href="/login/login.php" class="btn-primary">
      <i class="fa fa-sign-in-alt"></i> Login ke Dashboard
    </a>
    <a href="/index.php" class="btn-outline">
      <i class="fa fa-home"></i> Kembali ke Beranda
    </a>
  </div>
</div>

<!-- Simple confetti -->
<script>
(function(){
  const canvas = document.getElementById('confetti-canvas');
  const ctx = canvas.getContext('2d');
  canvas.width = window.innerWidth;
  canvas.height = window.innerHeight;
  window.addEventListener('resize', ()=>{ canvas.width=window.innerWidth; canvas.height=window.innerHeight; });

  const colors = ['#f97316','#3b82f6','#22c55e','#a78bfa','#f43f5e','#facc15'];
  const pieces = Array.from({length:120}, ()=>({
    x: Math.random()*canvas.width,
    y: Math.random()*canvas.height - canvas.height,
    w: Math.random()*8+4,
    h: Math.random()*14+6,
    color: colors[Math.floor(Math.random()*colors.length)],
    speed: Math.random()*2+1,
    tilt: Math.random()*10-5,
    tiltSpeed: Math.random()*.1+.05,
    angle: 0,
    opacity: 1,
  }));

  let frame = 0;
  function draw(){
    ctx.clearRect(0,0,canvas.width,canvas.height);
    pieces.forEach(p=>{
      p.y += p.speed;
      p.angle += p.tiltSpeed;
      p.tilt = Math.sin(p.angle) * 10;
      if(p.y > canvas.height + 20){ p.y = -20; p.x = Math.random()*canvas.width; }
      ctx.save();
      ctx.translate(p.x, p.y);
      ctx.rotate(p.tilt * Math.PI/180);
      ctx.globalAlpha = Math.max(0, 1 - frame/300);
      ctx.fillStyle = p.color;
      ctx.fillRect(-p.w/2, -p.h/2, p.w, p.h);
      ctx.restore();
    });
    frame++;
    if(frame < 300) requestAnimationFrame(draw);
    else ctx.clearRect(0,0,canvas.width,canvas.height);
  }
  draw();
})();
</script>
</body>
</html>
