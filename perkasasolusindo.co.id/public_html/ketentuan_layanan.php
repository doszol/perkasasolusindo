<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="description" content="Ketentuan Layanan dan Service Level Agreement (SLA) — Perkasa Tech Solusindo. Aturan penggunaan layanan, jaminan uptime, kebijakan refund, dan hak & kewajiban pelanggan.">
  <title>Ketentuan Layanan & SLA — PERKASA SOLUSINDO</title>
  <link rel="icon" type="image/png" href="/assets/images/CDR LOGO PERKASA Putih with border.png">

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,300&display=swap" rel="stylesheet">

  <!-- Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">

  <style>
/* ============================================================
   PERKASA TECH SOLUSINDO — style_index.css
   Aesthetic: Dark Tech / Industrial Futurism
   Fonts: Syne (display) + DM Sans (body)
   ============================================================ */

@import url('https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,300&display=swap');

/* ── CSS Variables ─────────────────────────────────────── */
:root {
  /* Dark blue → magenta background palette */
  --bg:         #06050f;        /* deepest navy-black */
  --bg2:        #0a0818;        /* dark blue-violet */
  --bg3:        #0e0c20;
  --surface:    #120f28;        /* navy surface */
  --surface2:   #1a1535;        /* slightly lighter navy */
  --border:     rgba(180,120,255,0.08);
  --border2:    rgba(200,140,255,0.15);

  /* Gradient stops — used throughout */
  --grad-from:  #0d0a2e;        /* deep navy */
  --grad-mid:   #1a0a3d;        /* indigo */
  --grad-to:    #2d0a3a;        /* dark magenta */

  /* Magenta/violet accent for glow effects */
  --magenta:       #c026d3;
  --magenta-light: #e040fb;
  --magenta-glow:  rgba(192,38,211,0.22);
  --violet:        #7c3aed;
  --violet-glow:   rgba(124,58,237,0.2);

  /* Orange stays as CTA / primary action color */
  --accent:     #f97316;
  --accent2:    #fb923c;
  --accent-glow:rgba(249,115,22,0.35);

  --blue:       #6366f1;
  --blue-glow:  rgba(99,102,241,0.25);
  --teal:       #14b8a6;

  --text:       #f1f5f9;
  --text2:      #b4aed4;        /* slightly violet-tinted */
  --text3:      #6b6490;

  --font-display: 'Syne', sans-serif;
  --font-body:    'DM Sans', sans-serif;

  --radius:     14px;
  --radius-lg:  22px;
  --transition: 0.35s cubic-bezier(0.4,0,0.2,1);
}

/* ── Reset & Base ───────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

html { scroll-behavior: smooth; }

body {
  background:
    radial-gradient(ellipse 120% 60% at 0% 0%,   #12083a 0%, transparent 55%),
    radial-gradient(ellipse 80%  70% at 100% 20%, #2a0640 0%, transparent 50%),
    radial-gradient(ellipse 100% 80% at 50% 100%, #1a073a 0%, transparent 60%),
    linear-gradient(160deg, #06040e 0%, #0c0820 40%, #1a0630 70%, #0c0418 100%);
  background-attachment: fixed; /* Note: overridden on mobile below */
  color: var(--text);
  font-family: var(--font-body);
  font-size: 16px;
  line-height: 1.65;
  overflow-x: hidden;
}

a { color: inherit; text-decoration: none; }
img { max-width: 100%; display: block; }
ul { list-style: none; }

/* ── Scrollbar ──────────────────────────────────────────── */
::-webkit-scrollbar { width: 5px; }
::-webkit-scrollbar-track { background: var(--bg); }
::-webkit-scrollbar-thumb { background: linear-gradient(var(--magenta), var(--violet)); border-radius: 10px; }

/* ── Utility ────────────────────────────────────────────── */
.container { max-width: 1240px; margin: 0 auto; padding: 0 24px; }
.section { padding: 100px 0; }
.tag-pill {
  display: inline-flex; align-items: center; gap: 6px;
  background: rgba(249,115,22,0.12);
  border: 1px solid rgba(249,115,22,0.25);
  color: var(--accent);
  font-family: var(--font-display);
  font-size: 11px; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase;
  padding: 5px 14px; border-radius: 100px;
  margin-bottom: 16px;
}
.section-label {
  font-family: var(--font-display);
  font-size: 11px; font-weight: 700; letter-spacing: 0.15em; text-transform: uppercase;
  color: var(--accent); margin-bottom: 12px;
}
.section-title {
  font-family: var(--font-display);
  font-size: clamp(28px, 4vw, 48px);
  font-weight: 800;
  line-height: 1.15;
  color: var(--text);
}
.section-title em { color: var(--accent); font-style: normal; }
.section-desc { color: var(--text2); max-width: 560px; margin-top: 14px; font-size: 15px; }

/* ── Preloader ──────────────────────────────────────────── */
#preloader {
  position: fixed; inset: 0; z-index: 9999;
  background: linear-gradient(135deg, #06040e, #0e0630, #180840);
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: 24px;
  /* FIX: padding horizontal cegah teks terpotong di layar sempit,
          safe-area-inset agar center visual di iPhone notch / Dynamic Island */
  padding: env(safe-area-inset-top, 0px) 24px env(safe-area-inset-bottom, 0px) 24px;
  text-align: center; /* FIX: pastikan teks selalu center */
  transition: opacity 0.5s, visibility 0.5s;
}
#preloader.hidden { opacity: 0; visibility: hidden; }
.preloader-logo {
  font-family: var(--font-display);
  font-size: clamp(16px, 5vw, 22px); /* FIX: skala aman di semua lebar layar */
  font-weight: 800;
  letter-spacing: 0.04em;
  white-space: nowrap; /* FIX: cegah teks wrap/pecah jadi 2 baris */
}
.preloader-logo span { color: var(--accent); }
.preloader-bar {
  width: min(180px, 50vw); /* FIX: menyesuaikan lebar layar, tidak overflow di HP kecil */
  height: 3px;
  background: var(--surface2);
  border-radius: 10px; overflow: hidden;
}
.preloader-bar::after {
  content: '';
  display: block; height: 100%;
  background: linear-gradient(90deg, var(--accent), var(--accent2));
  border-radius: 10px;
  animation: loadBar 1.4s ease forwards;
}
@keyframes loadBar { from { width: 0; } to { width: 100%; } }

/* ── Pre-Header ─────────────────────────────────────────── */
.pre-header {
  position: fixed; top: 0; left: 0; right: 0;
  z-index: 910;                        /* above .site-header */
  background: rgba(10,6,28,0.95);
  border-bottom: 1px solid rgba(180,120,255,0.12);
  padding: 9px 0;
  font-size: 12.5px;
  color: var(--text3);
  height: 38px;                        /* fixed height for offset calc */
  overflow: hidden;
  transition: transform 0.35s cubic-bezier(0.4,0,0.2,1),
              opacity   0.35s ease;
}
.pre-header.hidden {
  transform: translateY(-100%);
  opacity: 0;
  pointer-events: none;
}
.pre-header ul { display: flex; gap: 28px; flex-wrap: wrap; }
.pre-header a { display: flex; align-items: center; gap: 7px; color: var(--text3); transition: color var(--transition); }
.pre-header a:hover { color: var(--accent); }
.pre-header i { color: var(--accent); font-size: 12px; }

/* ── Navigation ─────────────────────────────────────────── */
.site-header {
  position: fixed;
  top: 38px;                           /* sits directly below pre-header */
  left: 0; right: 0;
  z-index: 900;
  background: transparent;
  transition: background var(--transition), box-shadow var(--transition), top var(--transition);
}
.site-header.scrolled {
  background: rgba(8,5,22,0.92);
  backdrop-filter: blur(20px);
  box-shadow: 0 1px 0 var(--border);
  top: 0;
}
.nav-inner {
  display: flex; align-items: center; justify-content: space-between;
  height: 72px;
}
.nav-logo { display: flex; align-items: center; gap: 12px; }
.nav-logo img { width: 52px; filter: drop-shadow(0 0 12px var(--accent-glow)); }
.nav-logo-text { font-family: var(--font-display); font-size: 16px; font-weight: 800; line-height: 1.1; }
.nav-logo-text span { color: var(--accent); }
.nav-logo-text small { display: block; font-size: 9px; font-weight: 500; color: var(--text3); letter-spacing: 0.12em; text-transform: uppercase; }

.nav-links { display: flex; align-items: center; gap: 8px; }
.nav-links a {
  font-family: var(--font-display);
  font-size: 13px; font-weight: 600;
  color: var(--text2);
  padding: 8px 14px; border-radius: 8px;
  transition: color var(--transition), background var(--transition);
  letter-spacing: 0.02em;
}
.nav-links a:hover, .nav-links a.active { color: var(--text); background: var(--surface); }
.nav-links .nav-cta {
  background: var(--accent);
  color: #fff;
  padding: 8px 20px;
  border-radius: 8px;
}
.nav-links .nav-cta:hover { background: var(--accent2); }

.nav-toggle {
  display: none;
  flex-direction: column; gap: 5px;
  background: none; border: none; cursor: pointer; padding: 8px;
}
.nav-toggle span {
  display: block; width: 24px; height: 2px;
  background: var(--text);
  border-radius: 2px;
  transition: transform 0.32s cubic-bezier(0.4,0,0.2,1),
              opacity   0.22s ease,
              background 0.2s ease;
}

/* ── Hero ────────────────────────────────────────────────── */
.hero {
  min-height: 100vh;
  display: flex; align-items: center;
  position: relative;
  overflow: hidden;
  padding-top: 130px; /* 38px pre-header + 72px nav + 20px buffer */
}
.hero-bg {
  position: absolute; inset: 0;
  background:
    radial-gradient(ellipse 70% 55% at 70% 30%,  rgba(192,38,211,0.18) 0%, transparent 65%),
    radial-gradient(ellipse 60% 50% at 15% 70%,  rgba(124,58,237,0.15) 0%, transparent 60%),
    radial-gradient(ellipse 50% 40% at 90% 80%,  rgba(249,115,22,0.06) 0%, transparent 50%);
}
.hero-grid {
  position: absolute; inset: 0;
  background-image: linear-gradient(var(--border) 1px, transparent 1px),
                    linear-gradient(90deg, var(--border) 1px, transparent 1px);
  background-size: 60px 60px;
  opacity: 0.4;
  mask-image: radial-gradient(ellipse at 50% 50%, black 30%, transparent 80%);
}
.hero-content {
  position: relative; z-index: 2;
  display: grid; grid-template-columns: 1fr 1fr; gap: 60px; align-items: center;
  width: 100%;
}
.hero-badge {
  display: inline-flex; align-items: center; gap: 8px;
  background: rgba(249,115,22,0.1); border: 1px solid rgba(249,115,22,0.2);
  color: var(--accent); border-radius: 100px;
  font-family: var(--font-display); font-size: 11px; font-weight: 700;
  letter-spacing: 0.12em; text-transform: uppercase;
  padding: 6px 14px; margin-bottom: 24px;
}
.hero-badge .dot { width: 6px; height: 6px; background: var(--accent); border-radius: 50%; animation: pulse 2s infinite; }
@keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.4; } }

.hero-title {
  font-family: var(--font-display);
  font-size: clamp(36px, 5.5vw, 68px);
  font-weight: 800; line-height: 1.05;
  margin-bottom: 20px;
}
.hero-title .line { display: block; overflow: hidden; }
.hero-title .accent { color: var(--accent); }
.hero-title .outlined {
  -webkit-text-stroke: 2px rgba(192,38,211,0.6);
  color: transparent;
}

.hero-desc { color: var(--text2); font-size: 15px; line-height: 1.75; max-width: 480px; margin-bottom: 36px; }

.hero-actions { display: flex; gap: 14px; flex-wrap: wrap; }
.btn-primary {
  display: inline-flex; align-items: center; gap: 8px;
  background: var(--accent);
  color: #fff;
  font-family: var(--font-display); font-size: 13px; font-weight: 700;
  padding: 14px 28px; border-radius: 10px;
  border: none; cursor: pointer;
  transition: background var(--transition), transform var(--transition), box-shadow var(--transition);
  text-decoration: none;
}
.btn-primary:hover { background: var(--accent2); transform: translateY(-2px); box-shadow: 0 8px 24px var(--accent-glow); }
.btn-outline {
  display: inline-flex; align-items: center; gap: 8px;
  background: transparent;
  color: var(--text);
  font-family: var(--font-display); font-size: 13px; font-weight: 700;
  padding: 14px 28px; border-radius: 10px;
  border: 1px solid var(--border2); cursor: pointer;
  transition: border-color var(--transition), background var(--transition);
  text-decoration: none;
}
.btn-outline:hover { border-color: var(--accent); background: rgba(249,115,22,0.05); }

.hero-stats {
  display: flex; gap: 32px; margin-top: 48px; padding-top: 36px;
  border-top: 1px solid var(--border);
}
.stat-item { }
.stat-num {
  font-family: var(--font-display);
  font-size: 32px; font-weight: 800;
  color: var(--text); line-height: 1;
}
.stat-num span { color: var(--accent); }
.stat-label { font-size: 12px; color: var(--text3); margin-top: 4px; letter-spacing: 0.04em; }

.hero-visual {
  position: relative;
  display: flex; align-items: center; justify-content: center;
}
.hero-card-stack { position: relative; width: 100%; max-width: 440px; margin: 0 auto; }
.hero-main-card {
  background: rgba(18,15,40,0.9);
  border: 1px solid var(--border2);
  border-radius: var(--radius-lg);
  padding: 28px;
  position: relative; overflow: hidden;
  box-shadow: 0 24px 60px rgba(0,0,0,0.4);
}
.hero-main-card::before {
  content: '';
  position: absolute; top: 0; left: 0; right: 0; height: 2px;
  background: linear-gradient(90deg, transparent, var(--accent), transparent);
}
.hero-card-icon {
  width: 52px; height: 52px;
  background: rgba(249,115,22,0.12);
  border: 1px solid rgba(249,115,22,0.2);
  border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  font-size: 24px; margin-bottom: 18px;
}
.hero-card-title { font-family: var(--font-display); font-size: 18px; font-weight: 700; margin-bottom: 8px; }
.hero-card-sub { color: var(--text2); font-size: 13px; margin-bottom: 20px; }
.speed-bar { display: flex; flex-direction: column; gap: 10px; }
.speed-row { display: flex; align-items: center; gap: 12px; font-size: 12px; }
.speed-row label { color: var(--text2); width: 80px; }
.speed-track { flex: 1; height: 6px; background: rgba(255,255,255,0.06); border-radius: 10px; overflow: hidden; }
.speed-fill { height: 100%; border-radius: 10px; background: linear-gradient(90deg, var(--accent), var(--accent2)); animation: fillIn 1.5s ease forwards; }
@keyframes fillIn { from { width: 0; } }
.speed-val { color: var(--accent); font-weight: 700; font-size: 11px; width: 55px; text-align: right; }

.hero-float-badge {
  position: absolute;
  background: rgba(26,21,53,0.95); border: 1px solid rgba(180,120,255,0.2);
  border-radius: 12px; padding: 10px 16px;
  font-size: 12px; font-weight: 600;
  box-shadow: 0 8px 24px rgba(0,0,0,0.3);
  animation: floatY 3s ease-in-out infinite;
  display: flex; align-items: center; gap: 8px;
}
.hero-float-badge.top-right { top: -20px; right: -20px; animation-delay: 0.4s; }
.hero-float-badge.bottom-left { bottom: -20px; left: -20px; }
.hero-float-badge .badge-icon { font-size: 16px; }
@keyframes floatY { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }

/* ── Services / Kategori ────────────────────────────────── */
.services-section { background: transparent; }
.services-header { text-align: center; margin-bottom: 56px; }
.services-header .section-desc { margin: 14px auto 0; }

.services-tabs {
  display: flex; gap: 12px; flex-wrap: wrap;
  justify-content: center; margin-bottom: 48px;
}
.svc-tab {
  display: flex; align-items: center; gap: 10px;
  background: rgba(18,15,40,0.7);
  border: 1px solid rgba(180,120,255,0.1);
  color: var(--text2);
  font-family: var(--font-display); font-size: 13px; font-weight: 600;
  padding: 12px 22px; border-radius: 12px;
  cursor: pointer;
  transition: all var(--transition);
  position: relative;
}
.svc-tab .tab-icon { font-size: 18px; transition: transform var(--transition); }
.svc-tab:hover { border-color: var(--border2); color: var(--text); }
.svc-tab.active {
  background: linear-gradient(135deg, rgba(192,38,211,0.15), rgba(124,58,237,0.08));
  border-color: rgba(192,38,211,0.45);
  color: var(--accent);
  box-shadow: 0 0 24px rgba(192,38,211,0.15);
}
.svc-tab.active .tab-icon { transform: scale(1.15); }

/* Service Panel */
.svc-panel { display: none; animation: panelIn 0.4s ease; }
.svc-panel.active { display: block; }
@keyframes panelIn { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }

/* Service Hero Banner */
.svc-banner {
  border-radius: var(--radius-lg);
  padding: 52px 48px;
  margin-bottom: 40px;
  position: relative; overflow: hidden;
  border: 1px solid var(--border2);
}
.svc-banner.wifi    { background: linear-gradient(135deg, #0c1040 0%, #0d0830 50%, #100625 100%); }
.svc-banner.hosting { background: linear-gradient(135deg, #071428 0%, #090d30 50%, #0a0625 100%); }
.svc-banner.website { background: linear-gradient(135deg, #1a0730 0%, #150530 50%, #0e0425 100%); }
.svc-banner.komputer{ background: linear-gradient(135deg, #1a1206 0%, #15110a 50%, #0d0820 100%); }
.svc-banner.cctv    { background: linear-gradient(135deg, #200810 0%, #200510 50%, #0d0525 100%); }

.svc-banner-glow {
  position: absolute; top: -40px; right: -40px;
  width: 280px; height: 280px;
  border-radius: 50%; filter: blur(80px); opacity: 0.5;
}
.wifi    .svc-banner-glow { background: var(--blue); }
.hosting .svc-banner-glow { background: var(--teal); }
.website .svc-banner-glow { background: #a855f7; }
.komputer .svc-banner-glow { background: #eab308; }
.cctv    .svc-banner-glow { background: #ef4444; }

.svc-banner-inner { position: relative; z-index: 2; display: grid; grid-template-columns: 1fr auto; gap: 40px; align-items: center; }
.svc-banner-icon {
  font-size: 56px; margin-bottom: 16px;
  filter: drop-shadow(0 0 20px currentColor);
}
.wifi     .svc-banner-icon { color: var(--blue); }
.hosting  .svc-banner-icon { color: var(--teal); }
.website  .svc-banner-icon { color: #a855f7; }
.komputer .svc-banner-icon { color: #eab308; }
.cctv     .svc-banner-icon { color: #ef4444; }

.svc-banner-tag {
  display: inline-flex; align-items: center; gap: 6px;
  border-radius: 100px; font-family: var(--font-display);
  font-size: 10px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase;
  padding: 4px 12px; margin-bottom: 14px;
  border: 1px solid currentColor; opacity: 0.8;
}
.wifi     .svc-banner-tag { color: var(--blue); background: rgba(59,130,246,0.1); }
.hosting  .svc-banner-tag { color: var(--teal); background: rgba(20,184,166,0.1); }
.website  .svc-banner-tag { color: #a855f7;     background: rgba(168,85,247,0.1); }
.komputer .svc-banner-tag { color: #eab308;     background: rgba(234,179,8,0.1); }
.cctv     .svc-banner-tag { color: #ef4444;     background: rgba(239,68,68,0.1); }

.svc-banner-title { font-family: var(--font-display); font-size: clamp(26px, 3.5vw, 42px); font-weight: 800; line-height: 1.1; margin-bottom: 12px; }
.svc-banner-desc  { color: var(--text2); font-size: 14px; max-width: 520px; line-height: 1.7; }
.svc-banner-cta   { margin-top: 24px; }

.svc-banner-features {
  display: flex; flex-direction: column; gap: 10px;
  min-width: 200px;
}
.feat-item {
  display: flex; align-items: center; gap: 10px;
  background: rgba(180,120,255,0.06); border: 1px solid rgba(180,120,255,0.12);
  border-radius: 10px; padding: 10px 14px;
  font-size: 13px; color: var(--text2);
}
.feat-item i { color: #22c55e; font-size: 13px; }

/* Product Cards */
.products-grid {
  display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px;
}
.product-card {
  background: rgba(14,11,32,0.8);
  border: 1px solid rgba(160,100,255,0.1);
  border-radius: var(--radius);
  padding: 28px;
  position: relative; overflow: hidden;
  transition: border-color var(--transition), transform var(--transition), box-shadow var(--transition);
  display: flex; flex-direction: column;
}
.product-card:hover {
  border-color: var(--border2);
  transform: translateY(-4px);
  box-shadow: 0 16px 40px rgba(0,0,0,0.3);
}
.product-card.featured {
  border-color: rgba(249,115,22,0.45);
  background: linear-gradient(135deg, rgba(249,115,22,0.08), rgba(192,38,211,0.05), rgba(14,11,32,0.9));
}
.product-card.featured::before {
  content: '';
  position: absolute; top: 0; left: 0; right: 0; height: 2px;
  background: linear-gradient(90deg, var(--accent), var(--accent2));
}
.badge-popular {
  position: absolute; top: 16px; right: 16px;
  background: var(--accent);
  color: #fff; font-family: var(--font-display);
  font-size: 9px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase;
  padding: 3px 10px; border-radius: 100px;
}
.product-category-tag {
  font-size: 10px; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase;
  color: var(--text3); margin-bottom: 12px;
}
.product-name { font-family: var(--font-display); font-size: 17px; font-weight: 700; margin-bottom: 8px; }
.product-desc { color: var(--text2); font-size: 13px; line-height: 1.65; flex: 1; margin-bottom: 20px; }
.product-speed {
  display: inline-flex; align-items: center; gap: 6px;
  background: rgba(59,130,246,0.1); border: 1px solid rgba(59,130,246,0.2);
  color: var(--blue); border-radius: 8px; padding: 4px 12px;
  font-size: 12px; font-weight: 600; margin-bottom: 16px;
}
.product-price-row { display: flex; align-items: flex-end; gap: 6px; margin-bottom: 20px; }
.product-price {
  font-family: var(--font-display);
  font-size: 28px; font-weight: 800; color: var(--text);
}
.product-price-unit { color: var(--text3); font-size: 13px; margin-bottom: 5px; }
.product-cta {
  display: flex; align-items: center; justify-content: center; gap: 8px;
  background: var(--surface2);
  border: 1px solid var(--border2);
  color: var(--text); font-family: var(--font-display); font-size: 13px; font-weight: 600;
  padding: 12px; border-radius: 10px;
  cursor: pointer; transition: all var(--transition);
  width: 100%;
}
.product-cta:hover { background: var(--accent); border-color: var(--accent); color: #fff; }
.product-card.featured .product-cta { background: var(--accent); border-color: var(--accent); color: #fff; }
.product-card.featured .product-cta:hover { background: var(--accent2); }

/* ── Why Perkasa ─────────────────────────────────────────── */
.why-section { background: transparent; position: relative; overflow: hidden; }
.why-section::before {
  content: '';
  position: absolute; top: -100px; left: 50%; transform: translateX(-50%);
  width: 700px; height: 700px;
  background: radial-gradient(circle, rgba(192,38,211,0.07) 0%, rgba(124,58,237,0.04) 40%, transparent 70%);
  pointer-events: none;
}
.why-inner {
  display: grid; grid-template-columns: 1fr 1fr; gap: 80px; align-items: center;
}
.why-left { }
.why-right { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

.why-card {
  background: rgba(14,11,32,0.75);
  border: 1px solid rgba(160,100,255,0.1);
  border-radius: var(--radius);
  padding: 24px 20px;
  transition: border-color var(--transition), transform var(--transition);
}
.why-card:hover { border-color: var(--border2); transform: translateY(-3px); }
.why-card-icon {
  font-size: 28px; margin-bottom: 14px;
}
.why-card-title { font-family: var(--font-display); font-size: 14px; font-weight: 700; margin-bottom: 6px; }
.why-card-desc { font-size: 12.5px; color: var(--text2); line-height: 1.6; }
.why-card.highlight {
  grid-column: span 2;
  background: linear-gradient(135deg, rgba(249,115,22,0.1), rgba(249,115,22,0.03));
  border-color: rgba(249,115,22,0.3);
  display: flex; gap: 20px; align-items: flex-start;
}

.why-testimonial {
  margin-top: 36px;
  background: rgba(14,11,32,0.75);
  border: 1px solid rgba(160,100,255,0.1);
  border-radius: var(--radius);
  padding: 24px;
  position: relative;
}
.why-testimonial::before {
  content: '"';
  position: absolute; top: -14px; left: 20px;
  font-size: 60px; font-family: var(--font-display); color: var(--accent);
  line-height: 1;
}
.why-testimonial p { color: var(--text2); font-size: 14px; line-height: 1.7; font-style: italic; margin-bottom: 16px; }
.testimonial-author { display: flex; align-items: center; gap: 12px; }
.author-avatar {
  width: 40px; height: 40px; border-radius: 50%;
  background: linear-gradient(135deg, var(--accent), var(--accent2));
  display: flex; align-items: center; justify-content: center;
  font-family: var(--font-display); font-size: 15px; font-weight: 700; color: #fff;
}
.author-name { font-family: var(--font-display); font-size: 13px; font-weight: 700; }
.author-role { font-size: 11px; color: var(--text3); }

/* ── Projects ────────────────────────────────────────────── */
.projects-section { background: transparent; }
.projects-header { margin-bottom: 48px; }
.projects-grid {
  display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;
}
.project-card {
  background: rgba(14,11,32,0.8); border: 1px solid rgba(160,100,255,0.1);
  border-radius: var(--radius); overflow: hidden;
  transition: transform var(--transition), box-shadow var(--transition), border-color var(--transition);
}
.project-card:hover { transform: translateY(-5px); box-shadow: 0 20px 50px rgba(0,0,0,0.4); border-color: var(--border2); }
.project-img {
  width: 100%; height: 200px; object-fit: cover;
  display: block; transition: transform 0.5s ease;
}
.project-card:hover .project-img { transform: scale(1.04); }
.project-img-wrap { overflow: hidden; position: relative; }
.project-overlay {
  position: absolute; inset: 0;
  background: rgba(0,0,0,0.6);
  display: flex; align-items: center; justify-content: center;
  opacity: 0; transition: opacity var(--transition);
}
.project-card:hover .project-overlay { opacity: 1; }
.project-link-btn {
  width: 48px; height: 48px; border-radius: 50%;
  background: var(--accent);
  display: flex; align-items: center; justify-content: center;
  color: #fff; font-size: 16px;
  transform: scale(0.7); transition: transform var(--transition);
}
.project-card:hover .project-link-btn { transform: scale(1); }
.project-info { padding: 20px; }
.project-title { font-family: var(--font-display); font-size: 15px; font-weight: 700; }

/* ── Contact ─────────────────────────────────────────────── */
.contact-section { background: transparent; }
.contact-grid { display: grid; grid-template-columns: 1fr 1.5fr; gap: 48px; }
.contact-info-title { font-family: var(--font-display); font-size: 22px; font-weight: 700; margin-bottom: 24px; }
.contact-info-items { display: flex; flex-direction: column; gap: 16px; }
.contact-info-item {
  display: flex; align-items: flex-start; gap: 14px;
  background: rgba(14,11,32,0.75); border: 1px solid rgba(160,100,255,0.1);
  border-radius: var(--radius); padding: 16px 18px;
  transition: border-color var(--transition);
}
.contact-info-item:hover { border-color: var(--border2); }
.contact-info-icon {
  width: 40px; height: 40px; border-radius: 10px;
  background: rgba(249,115,22,0.12); border: 1px solid rgba(249,115,22,0.2);
  display: flex; align-items: center; justify-content: center;
  color: var(--accent); font-size: 15px; flex-shrink: 0;
}
.contact-info-label { font-size: 11px; color: var(--text3); letter-spacing: 0.04em; text-transform: uppercase; margin-bottom: 3px; }
.contact-info-val { font-size: 14px; font-weight: 500; }

.contact-map {
  border-radius: var(--radius); overflow: hidden; margin-top: 24px;
  border: 1px solid var(--border);
}
.contact-map iframe { display: block; }

.contact-form-wrap {
  background: rgba(14,11,32,0.8); border: 1px solid rgba(160,100,255,0.12);
  border-radius: var(--radius-lg); padding: 40px;
}
.contact-form-title { font-family: var(--font-display); font-size: 22px; font-weight: 700; margin-bottom: 6px; }
.contact-form-sub { color: var(--text2); font-size: 14px; margin-bottom: 28px; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 12px; font-weight: 600; color: var(--text2); letter-spacing: 0.05em; margin-bottom: 7px; }
.form-group input, .form-group textarea, .form-group select {
  width: 100%; background: rgba(8,5,22,0.7);
  border: 1px solid var(--border2); border-radius: 10px;
  color: var(--text); font-family: var(--font-body); font-size: 14px;
  padding: 12px 16px;
  outline: none; transition: border-color var(--transition), box-shadow var(--transition);
}
.form-group input:focus, .form-group textarea:focus, .form-group select:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px var(--accent-glow);
}
.form-group textarea { resize: vertical; min-height: 100px; }
.form-submit {
  width: 100%; padding: 14px;
  background: var(--accent); color: #fff;
  border: none; border-radius: 10px; cursor: pointer;
  font-family: var(--font-display); font-size: 14px; font-weight: 700;
  transition: background var(--transition), transform var(--transition);
  display: flex; align-items: center; justify-content: center; gap: 8px;
}
.form-submit:hover { background: var(--accent2); transform: translateY(-1px); }

/* ── Footer ──────────────────────────────────────────────── */
.site-footer {
  background: rgba(6,4,18,0.85);
  backdrop-filter: blur(10px);
  border-top: 1px solid var(--border);
  padding: 60px 0 32px;
}
.footer-grid {
  display: grid; grid-template-columns: 1.5fr 1fr 1fr; gap: 48px;
  margin-bottom: 48px;
}
.footer-brand-desc { color: var(--text2); font-size: 14px; line-height: 1.7; margin: 16px 0 24px; max-width: 280px; }
.footer-socials { display: flex; gap: 10px; }
.social-btn {
  width: 38px; height: 38px; border-radius: 10px;
  background: rgba(18,15,40,0.8); border: 1px solid rgba(160,100,255,0.15);
  display: flex; align-items: center; justify-content: center;
  color: var(--text2); font-size: 15px;
  transition: all var(--transition);
}
.social-btn:hover { background: var(--accent); border-color: var(--accent); color: #fff; }
.footer-col-title { font-family: var(--font-display); font-size: 13px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: var(--text); margin-bottom: 18px; }
.footer-links { display: flex; flex-direction: column; gap: 10px; }
.footer-links a { color: var(--text2); font-size: 13.5px; transition: color var(--transition); }
.footer-links a:hover { color: var(--accent); }
.footer-bottom {
  border-top: 1px solid var(--border); padding-top: 24px;
  display: flex; align-items: center; justify-content: space-between;
  font-size: 13px; color: var(--text3);
  flex-wrap: wrap; gap: 12px;
}
.footer-bottom span { color: var(--accent); }

/* ── Notification Toast ─────────────────────────────────── */
.toast {
  position: fixed; bottom: 28px; right: 28px; z-index: 9000;
  background: var(--surface2); border: 1px solid var(--border2);
  border-radius: 12px; padding: 16px 20px;
  display: flex; align-items: center; gap: 12px;
  box-shadow: 0 10px 30px rgba(0,0,0,0.4);
  transform: translateX(calc(100% + 40px));
  transition: transform 0.4s cubic-bezier(0.4,0,0.2,1);
  max-width: 340px;
}
.toast.show { transform: translateX(0); }
.toast-icon { font-size: 20px; }
.toast-text { font-size: 13.5px; font-weight: 500; }

/* ── Animations ─────────────────────────────────────────── */
.fade-up {
  opacity: 0; transform: translateY(30px);
  transition: opacity 0.6s ease, transform 0.6s ease;
}
.fade-up.visible { opacity: 1; transform: translateY(0); }
.fade-up:nth-child(1) { transition-delay: 0.05s; }
.fade-up:nth-child(2) { transition-delay: 0.1s; }
.fade-up:nth-child(3) { transition-delay: 0.15s; }
.fade-up:nth-child(4) { transition-delay: 0.2s; }
.fade-up:nth-child(5) { transition-delay: 0.25s; }

/* ── Responsive ─────────────────────────────────────────── */
@media (max-width: 1024px) {
  .hero-content { grid-template-columns: 1fr; }
  .hero-visual { display: none; }
  .why-inner { grid-template-columns: 1fr; gap: 48px; }
  .contact-grid { grid-template-columns: 1fr; }
  .footer-grid { grid-template-columns: 1fr 1fr; }
  .svc-banner-inner { grid-template-columns: 1fr; }
  .svc-banner-features { flex-direction: row; flex-wrap: wrap; }
}
@media (max-width: 768px) {
  .section { padding: 72px 0; }

  /* ── Mobile Nav Drawer ── */
  .nav-links {
    display: block;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: #07041a;
    z-index: 1100; /* FIX: harus > site-header (900) agar drawer tampil di atas */
    padding: 88px 32px 40px; /* FIX: 72px nav-height + 16px buffer (lebih presisi dari 100px) */
    overflow-y: auto;
    -webkit-overflow-scrolling: touch; /* FIX: smooth scroll di iOS */
    overscroll-behavior: contain;      /* FIX: cegah body ikut scroll saat drawer dibuka */
    /* hidden off-screen to the right by default */
    transform: translateX(100%);
    transition: transform 0.38s cubic-bezier(0.4,0,0.2,1);
    flex-direction: column;
    align-items: flex-start;
    gap: 0;
    pointer-events: none;
  }
  .nav-links.open {
    transform: translateX(0);
    pointer-events: all;
  }
  .nav-links li {
    width: 100%;
    border-bottom: 1px solid rgba(255,255,255,0.06);
  }
  .nav-links li:first-child {
    border-top: 1px solid rgba(255,255,255,0.06);
  }
  .nav-links a {
    display: block;
    font-size: 18px;
    font-weight: 700;
    padding: 18px 0;
    width: 100%;
    background: transparent !important;
    border-radius: 0 !important;
    color: var(--text2);
    letter-spacing: 0.02em;
  }
  .nav-links a:hover,
  .nav-links a.active { color: var(--accent); }
  .nav-links .nav-cta {
    margin-top: 24px;
    display: inline-flex;
    background: var(--accent) !important;
    color: #fff !important;
    border-radius: 10px !important;
    padding: 14px 28px !important;
    font-size: 15px;
    width: auto;
  }
  /* Hamburger → X animation */
  .nav-toggle.open span:nth-child(1) { transform: translateY(7px) rotate(45deg); }
  .nav-toggle.open span:nth-child(2) { opacity: 0; transform: scaleX(0); }
  .nav-toggle.open span:nth-child(3) { transform: translateY(-7px) rotate(-45deg); }

  .nav-toggle { display: flex; z-index: 1200; position: relative; } /* FIX: di atas drawer agar tetap bisa diklik saat open */

  /* FIX: background-attachment: fixed tidak didukung baik di iOS Safari */
  body { background-attachment: scroll; }

  /* FIX: iOS auto-zoom — input font-size harus >= 16px */
  .form-group input,
  .form-group textarea,
  .form-group select { font-size: 16px; }

  /* FIX: Service tabs scroll horizontal 1 baris, tidak wrap berantakan */
  .services-tabs {
    flex-wrap: nowrap;
    overflow-x: auto;
    justify-content: flex-start;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
    padding-bottom: 4px;
  }
  .services-tabs::-webkit-scrollbar { display: none; }
  .svc-tab { font-size: 11px; padding: 10px 14px; flex-shrink: 0; }
  .svc-tab .tab-icon { font-size: 15px; }

  .pre-header { display: none !important; }
  .site-header { top: 0 !important; }
  .hero { padding-top: 110px; }
  .hero-stats { gap: 20px; }
  .form-row { grid-template-columns: 1fr; }
  .footer-grid { grid-template-columns: 1fr; }
  .why-right { grid-template-columns: 1fr; }
  .why-card.highlight { grid-column: span 1; }
  .svc-banner { padding: 32px 24px; }
  .products-grid { grid-template-columns: 1fr; }
}

/* ── number counter ─────────────────────────────────────── */
.count-up { font-variant-numeric: tabular-nums; }


/* ══════════════════════════════════════════════════════════
   KETENTUAN LAYANAN & SLA — Page-specific styles
   ══════════════════════════════════════════════════════════ */

/* ── Page Hero / Banner ──────────────────────────────────── */
.tos-hero {
  padding: 140px 0 80px;
  position: relative;
  overflow: hidden;
}
.tos-hero::before {
  content: '';
  position: absolute; inset: 0;
  background:
    radial-gradient(ellipse 70% 80% at 80% 50%, rgba(192,38,211,0.18) 0%, transparent 60%),
    radial-gradient(ellipse 50% 60% at 10% 30%, rgba(124,58,237,0.15) 0%, transparent 55%);
  pointer-events: none;
}
.tos-hero-tag {
  display: inline-flex; align-items: center; gap: 8px;
  background: rgba(249,115,22,0.12);
  border: 1px solid rgba(249,115,22,0.3);
  color: var(--accent);
  font-family: var(--font-display);
  font-size: 11px; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase;
  padding: 5px 16px; border-radius: 100px;
  margin-bottom: 20px;
}
.tos-hero h1 {
  font-family: var(--font-display);
  font-size: clamp(2rem, 5vw, 3.8rem);
  font-weight: 800; line-height: 1.1;
  margin-bottom: 16px;
}
.tos-hero h1 span { color: var(--accent); }
.tos-hero-sub {
  color: var(--text2); max-width: 620px; font-size: 1rem; line-height: 1.7;
  margin-bottom: 28px;
}
.tos-meta {
  display: flex; align-items: center; gap: 24px; flex-wrap: wrap;
}
.tos-meta-item {
  display: flex; align-items: center; gap: 8px;
  color: var(--text3); font-size: 13px;
}
.tos-meta-item i { color: var(--accent); }
.tos-meta-item strong { color: var(--text2); }

/* ── TOC (Table of Contents) sidebar ───────────────────── */
.tos-layout {
  display: grid;
  grid-template-columns: 260px 1fr;
  gap: 40px;
  align-items: start;
  padding-bottom: 80px;
}
.tos-toc {
  position: sticky; top: 90px;
  background: var(--surface);
  border: 1px solid var(--border2);
  border-radius: var(--radius-lg);
  padding: 28px 24px;
}
.tos-toc-title {
  font-family: var(--font-display);
  font-size: 11px; font-weight: 700; letter-spacing: 0.15em; text-transform: uppercase;
  color: var(--accent); margin-bottom: 18px;
}
.tos-toc ul { list-style: none; padding: 0; margin: 0; }
.tos-toc ul li { margin-bottom: 4px; }
.tos-toc ul li a {
  display: flex; align-items: center; gap: 8px;
  color: var(--text3); font-size: 13px; line-height: 1.4;
  padding: 6px 10px; border-radius: 8px;
  transition: var(--transition);
  text-decoration: none;
}
.tos-toc ul li a:hover,
.tos-toc ul li a.active {
  color: var(--text);
  background: rgba(249,115,22,0.1);
}
.tos-toc ul li a::before {
  content: '';
  display: inline-block; width: 4px; height: 4px;
  background: var(--accent); border-radius: 50%; flex-shrink: 0;
  opacity: 0.5;
}
.tos-toc ul li a:hover::before,
.tos-toc ul li a.active::before { opacity: 1; }

/* ── Content ────────────────────────────────────────────── */
.tos-content {}

/* Section card */
.tos-section {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 40px;
  margin-bottom: 24px;
  transition: border-color 0.3s;
  scroll-margin-top: 100px;
}
.tos-section:hover { border-color: var(--border2); }

.tos-section-header {
  display: flex; align-items: flex-start; gap: 16px;
  margin-bottom: 24px; padding-bottom: 20px;
  border-bottom: 1px solid var(--border);
}
.tos-section-icon {
  width: 44px; height: 44px; flex-shrink: 0;
  background: linear-gradient(135deg, rgba(249,115,22,0.15), rgba(192,38,211,0.15));
  border: 1px solid rgba(249,115,22,0.2);
  border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  font-size: 20px;
}
.tos-section-meta {}
.tos-section-num {
  font-family: var(--font-display); font-size: 11px; font-weight: 700;
  letter-spacing: 0.14em; text-transform: uppercase;
  color: var(--accent); margin-bottom: 4px;
}
.tos-section-title {
  font-family: var(--font-display);
  font-size: 1.2rem; font-weight: 700; color: var(--text);
  line-height: 1.2;
}

/* Prose inside tos-section */
.tos-section p {
  color: var(--text2); font-size: 0.95rem; line-height: 1.75;
  margin-bottom: 14px;
}
.tos-section p:last-child { margin-bottom: 0; }
.tos-section strong { color: var(--text); }
.tos-section h3 {
  font-family: var(--font-display); font-size: 1rem; font-weight: 700;
  color: var(--text); margin: 22px 0 10px;
}
.tos-section h3:first-child { margin-top: 0; }

/* Lists */
.tos-list {
  list-style: none; padding: 0; margin: 12px 0 18px;
}
.tos-list li {
  display: flex; align-items: flex-start; gap: 10px;
  color: var(--text2); font-size: 0.92rem; line-height: 1.7;
  padding: 6px 0;
  border-bottom: 1px solid var(--border);
}
.tos-list li:last-child { border-bottom: none; }
.tos-list li::before {
  content: '›'; color: var(--accent); font-size: 16px;
  font-weight: 700; flex-shrink: 0; margin-top: 1px;
}

/* Numbered list */
.tos-list-num {
  list-style: none; padding: 0; margin: 12px 0 18px;
  counter-reset: tos-counter;
}
.tos-list-num li {
  display: flex; align-items: flex-start; gap: 12px;
  color: var(--text2); font-size: 0.92rem; line-height: 1.7;
  padding: 8px 0;
  border-bottom: 1px solid var(--border);
  counter-increment: tos-counter;
}
.tos-list-num li:last-child { border-bottom: none; }
.tos-list-num li::before {
  content: counter(tos-counter, decimal-leading-zero);
  color: var(--accent); font-family: var(--font-display);
  font-size: 12px; font-weight: 700; flex-shrink: 0;
  min-width: 22px; margin-top: 2px;
}

/* Forbidden items — red tinted */
.tos-list-forbidden li::before { content: '✕'; color: #ef4444; font-size: 13px; }
.tos-list-forbidden li { border-color: rgba(239,68,68,0.08); }

/* Info box */
.tos-info-box {
  background: rgba(249,115,22,0.06);
  border: 1px solid rgba(249,115,22,0.2);
  border-left: 3px solid var(--accent);
  border-radius: 10px; padding: 16px 20px;
  margin: 16px 0;
}
.tos-info-box p { margin-bottom: 0; color: var(--text2); font-size: 0.9rem; }
.tos-info-box strong { color: var(--accent); }

/* Warning box */
.tos-warn-box {
  background: rgba(239,68,68,0.05);
  border: 1px solid rgba(239,68,68,0.2);
  border-left: 3px solid #ef4444;
  border-radius: 10px; padding: 16px 20px;
  margin: 16px 0;
}
.tos-warn-box p { margin-bottom: 0; color: var(--text2); font-size: 0.9rem; }
.tos-warn-box strong { color: #ef4444; }

/* SLA table */
.tos-table {
  width: 100%; border-collapse: collapse; margin: 16px 0;
  font-size: 0.9rem;
}
.tos-table thead tr {
  background: linear-gradient(90deg, rgba(249,115,22,0.15), rgba(192,38,211,0.1));
}
.tos-table th {
  font-family: var(--font-display); font-size: 11px; font-weight: 700;
  letter-spacing: 0.1em; text-transform: uppercase; color: var(--accent);
  padding: 12px 16px; text-align: left;
  border-bottom: 1px solid var(--border2);
}
.tos-table td {
  padding: 11px 16px; color: var(--text2);
  border-bottom: 1px solid var(--border);
  line-height: 1.5;
}
.tos-table tr:last-child td { border-bottom: none; }
.tos-table tr:hover td { background: rgba(249,115,22,0.04); color: var(--text); }
.tos-table .badge-green {
  display: inline-block; padding: 2px 10px; border-radius: 100px;
  background: rgba(34,197,94,0.12); color: #22c55e;
  font-size: 12px; font-weight: 600;
}
.tos-table .badge-yellow {
  display: inline-block; padding: 2px 10px; border-radius: 100px;
  background: rgba(234,179,8,0.12); color: #eab308;
  font-size: 12px; font-weight: 600;
}
.tos-table .badge-red {
  display: inline-block; padding: 2px 10px; border-radius: 100px;
  background: rgba(239,68,68,0.12); color: #ef4444;
  font-size: 12px; font-weight: 600;
}

/* SLA Uptime highlight cards */
.tos-sla-grid {
  display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px;
  margin: 20px 0;
}
.tos-sla-card {
  background: var(--surface2);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 20px; text-align: center;
  transition: var(--transition);
}
.tos-sla-card:hover { border-color: var(--border2); }
.tos-sla-card-num {
  font-family: var(--font-display); font-size: 2rem; font-weight: 800;
  color: var(--accent); line-height: 1;
  margin-bottom: 6px;
}
.tos-sla-card-label {
  font-size: 12px; color: var(--text3); line-height: 1.4;
}

/* Print / last-update strip */
.tos-footer-strip {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 32px 40px;
  margin-top: 8px;
  display: flex; align-items: center; justify-content: space-between; gap: 24px;
  flex-wrap: wrap;
}
.tos-footer-strip-left {}
.tos-footer-strip-left h3 {
  font-family: var(--font-display); font-size: 1rem; font-weight: 700;
  color: var(--text); margin-bottom: 6px;
}
.tos-footer-strip-left p { color: var(--text3); font-size: 13px; margin: 0; }
.tos-footer-strip-actions { display: flex; gap: 12px; flex-wrap: wrap; }

/* ── Responsive ─────────────────────────────────────────── */
@media (max-width: 900px) {
  .tos-layout {
    grid-template-columns: 1fr;
  }
  .tos-toc { display: none; }
  .tos-section { padding: 28px 20px; }
  .tos-sla-grid { grid-template-columns: 1fr 1fr; }
  .tos-footer-strip { flex-direction: column; }
}
@media (max-width: 480px) {
  .tos-sla-grid { grid-template-columns: 1fr; }
  .tos-hero h1 { font-size: 2rem; }
  .tos-table { font-size: 0.82rem; }
  .tos-table th, .tos-table td { padding: 9px 10px; }
}
  </style>
</head>

<body>

<!-- ══ Preloader ════════════════════════════════════════════ -->
<div id="preloader">
  <div class="preloader-logo">PERKASA <span>SOLUSINDO</span></div>
  <div class="preloader-bar"></div>
</div>

<!-- ══ Pre-Header ══════════════════════════════════════════ -->
<div class="pre-header">
  <div class="container">
    <ul>
      <li><a href="tel:+6281246684665"><i class="fa fa-phone"></i>+62 812-4668-4665</a></li>
      <li><a href="mailto:info-perkasa@perkasasolusindo.co.id"><i class="fa fa-envelope"></i>info-perkasa@perkasasolusindo.co.id</a></li>
      <li><a href="#"><i class="fa fa-map-marker"></i>Jln. KedungRejo, Wedoroklurak, Candi, Jawa Timur 61271</a></li>
    </ul>
  </div>
</div>

<!-- ══ Header / Nav ════════════════════════════════════════ -->
<header class="site-header" id="siteHeader">
  <div class="container">
    <div class="nav-inner">

      <a href="index.php" class="nav-logo">
        <img src="/assets/images/CDR LOGO PERKASA Putih with border.png" alt="Perkasa Logo">
        <div class="nav-logo-text">
          PERKASA <span>TECH</span>
          <small>Solusindo</small>
        </div>
      </a>

      <nav>
        <ul class="nav-links" id="navLinks">
          <li><a href="index.php">Home</a></li>
          <li><a href="index.php#services">Layanan</a></li>
          <li><a href="index.php#projects">Proyek</a></li>
          <li><a href="index.php#why">Tentang</a></li>
          <li><a href="index.php#contact">Kontak</a></li>
          <li><a href="https://perkasasolusindo.co.id/login/login.php" class="nav-cta"><i class="fa fa-user"></i>&nbsp;Login</a></li>
        </ul>
      </nav>

      <button class="nav-toggle" id="navToggle" aria-label="Menu">
        <span></span><span></span><span></span>
      </button>

    </div>
  </div>
</header>

<!-- ══ Page Hero ════════════════════════════════════════════ -->
<section class="tos-hero">
  <div class="container">
    <div class="tos-hero-tag">
      <i class="fa fa-file-text"></i> Dokumen Resmi
    </div>
    <h1>Ketentuan Layanan<br>&amp; <span>SLA</span></h1>
    <p class="tos-hero-sub">
      Dokumen ini mengatur hak, kewajiban, dan jaminan layanan antara PT. Perkasa Tech Solusindo dan pelanggan. Dengan menggunakan layanan kami, Anda dianggap telah membaca dan menyetujui seluruh ketentuan berikut.
    </p>
    <div class="tos-meta">
      <div class="tos-meta-item">
        <i class="fa fa-calendar"></i>
        <span>Berlaku sejak: <strong>1 Januari 2025</strong></span>
      </div>
      <div class="tos-meta-item">
        <i class="fa fa-refresh"></i>
        <span>Terakhir diperbarui: <strong>Juni 2026</strong></span>
      </div>
      <div class="tos-meta-item">
        <i class="fa fa-language"></i>
        <span>Bahasa: <strong>Bahasa Indonesia</strong></span>
      </div>
    </div>
  </div>
</section>

<!-- ══ Main Layout ══════════════════════════════════════════ -->
<div class="container">
  <div class="tos-layout">

    <!-- ── TOC Sidebar ──────────────────────────────────── -->
    <aside class="tos-toc" id="tos-toc">
      <div class="tos-toc-title">Daftar Isi</div>
      <ul>
        <li><a href="#tos-umum">Ketentuan Umum</a></li>
        <li><a href="#tos-layanan">Ruang Lingkup Layanan</a></li>
        <li><a href="#tos-hak">Hak &amp; Kewajiban Pelanggan</a></li>
        <li><a href="#tos-larangan">Penggunaan Terlarang</a></li>
        <li><a href="#tos-privasi">Privasi &amp; Data</a></li>
        <li><a href="#tos-pembayaran">Pembayaran &amp; Tagihan</a></li>
        <li><a href="#tos-penghentian">Penghentian Layanan</a></li>
        <li><a href="#tos-refund">Kebijakan Refund</a></li>
        <li><a href="#tos-sla">SLA &amp; Jaminan Uptime</a></li>
        <li><a href="#tos-wifi">SLA — Internet WiFi</a></li>
        <li><a href="#tos-hosting">SLA — Hosting</a></li>
        <li><a href="#tos-website">SLA — Pembuatan Website</a></li>
        <li><a href="#tos-komputer">SLA — Komputer &amp; IT</a></li>
        <li><a href="#tos-cctv">SLA — CCTV</a></li>
        <li><a href="#tos-hukum">Hukum &amp; Yurisdiksi</a></li>
        <li><a href="#tos-kontak">Kontak Kami</a></li>
      </ul>
    </aside>

    <!-- ── Content ──────────────────────────────────────── -->
    <main class="tos-content">

      <!-- 1. KETENTUAN UMUM -->
      <div class="tos-section" id="tos-umum">
        <div class="tos-section-header">
          <div class="tos-section-icon">📋</div>
          <div class="tos-section-meta">
            <div class="tos-section-num">Pasal 1</div>
            <div class="tos-section-title">Ketentuan Umum</div>
          </div>
        </div>

        <p>Semua layanan yang disediakan oleh <strong>PT. Perkasa Tech Solusindo</strong> ("Perkasa Solusindo") hanya dapat digunakan untuk keperluan yang tidak melanggar hukum Republik Indonesia. Dengan mengakses atau menggunakan layanan kami, Anda ("Pelanggan") menyatakan telah membaca, memahami, dan menyetujui seluruh Ketentuan Layanan ini.</p>

        <p>Perkasa Solusindo berhak mengubah ketentuan ini sewaktu-waktu. Perubahan akan diinformasikan melalui website resmi, email terdaftar, atau media komunikasi lainnya. Penggunaan layanan setelah perubahan diterbitkan dianggap sebagai persetujuan atas ketentuan baru.</p>

        <div class="tos-info-box">
          <p><strong>Penting:</strong> Ketentuan ini berlaku untuk seluruh layanan Perkasa Solusindo, termasuk Wifi Internet, Hosting, Pembuatan Website, Komputer &amp; IT, dan CCTV.</p>
        </div>

        <p>Pelanggan setuju untuk membebaskan Perkasa Solusindo dari segala tuntutan dan klaim yang timbul akibat penggunaan layanan yang melanggar ketentuan ini, baik dari pihak pelanggan maupun pihak ketiga.</p>

        <p>Seluruh hak cipta, merek dagang, dan kekayaan intelektual yang berkaitan dengan layanan Perkasa Solusindo adalah milik PT. Perkasa Tech Solusindo dan/atau mitra yang berwenang. Penggunaan tanpa izin tertulis adalah terlarang.</p>
      </div>

      <!-- 2. RUANG LINGKUP LAYANAN -->
      <div class="tos-section" id="tos-layanan">
        <div class="tos-section-header">
          <div class="tos-section-icon">🌐</div>
          <div class="tos-section-meta">
            <div class="tos-section-num">Pasal 2</div>
            <div class="tos-section-title">Ruang Lingkup Layanan</div>
          </div>
        </div>

        <p>Perkasa Solusindo menyediakan solusi teknologi terpadu yang mencakup lima kategori layanan utama:</p>

        <ul class="tos-list">
          <li><strong>Provider Wifi Internet</strong> — Layanan koneksi internet fiber optik untuk rumah dan bisnis di area Sidoarjo, Surabaya, dan sekitarnya.</li>
          <li><strong>Sewa Hosting</strong> — Layanan web hosting dengan server lokal Indonesia, termasuk Cloud Hosting cPanel, Hosting Direct Admin, Unlimited Hosting, dan VPS.</li>
          <li><strong>Pembuatan Website</strong> — Jasa desain dan pengembangan website, landing page, toko online, dan sistem manajemen konten.</li>
          <li><strong>Jual &amp; Install Komputer</strong> — Penjualan hardware, perakitan, instalasi sistem operasi &amp; software, serta layanan teknis IT on-site.</li>
          <li><strong>Pemasangan CCTV</strong> — Instalasi sistem kamera pengawas untuk rumah, kantor, toko, dan fasilitas komersial.</li>
        </ul>

        <p>Layanan bersifat sesuai dengan paket yang telah disepakati saat pendaftaran. Penambahan fitur atau kapasitas dapat dilakukan melalui upgrade paket yang tersedia.</p>
      </div>

      <!-- 3. HAK & KEWAJIBAN PELANGGAN -->
      <div class="tos-section" id="tos-hak">
        <div class="tos-section-header">
          <div class="tos-section-icon">⚖️</div>
          <div class="tos-section-meta">
            <div class="tos-section-num">Pasal 3</div>
            <div class="tos-section-title">Hak &amp; Kewajiban Pelanggan</div>
          </div>
        </div>

        <h3>Hak Pelanggan</h3>
        <ul class="tos-list">
          <li>Mendapatkan layanan sesuai spesifikasi dan paket yang telah dibeli.</li>
          <li>Mendapatkan dukungan teknis (support) sesuai dengan tingkat layanan yang berlaku.</li>
          <li>Mendapatkan notifikasi atas gangguan layanan yang direncanakan (planned maintenance) minimal 24 jam sebelumnya.</li>
          <li>Mengajukan klaim SLA apabila terdapat downtime yang melampaui batas jaminan yang ditetapkan.</li>
          <li>Mendapatkan kerahasiaan data pribadi sesuai kebijakan privasi Perkasa Solusindo.</li>
        </ul>

        <h3>Kewajiban Pelanggan</h3>
        <ul class="tos-list">
          <li>Memberikan data identitas yang akurat dan valid saat pendaftaran.</li>
          <li>Menjaga kerahasiaan kredensial akun (username, password, PIN) dan bertanggung jawab atas seluruh aktivitas dalam akun.</li>
          <li>Membayar tagihan tepat waktu sesuai siklus penagihan yang disepakati.</li>
          <li>Menggunakan layanan hanya untuk keperluan yang sah dan tidak melanggar hukum.</li>
          <li>Melaporkan gangguan atau insiden keamanan segera kepada tim support Perkasa Solusindo.</li>
          <li>Bertanggung jawab penuh atas konten yang disimpan, dipublikasikan, atau ditransmisikan melalui layanan Perkasa Solusindo.</li>
        </ul>
      </div>

      <!-- 4. PENGGUNAAN TERLARANG -->
      <div class="tos-section" id="tos-larangan">
        <div class="tos-section-header">
          <div class="tos-section-icon">🚫</div>
          <div class="tos-section-meta">
            <div class="tos-section-num">Pasal 4</div>
            <div class="tos-section-title">Penggunaan Terlarang</div>
          </div>
        </div>

        <p>Perkasa Solusindo melarang keras penggunaan seluruh layanannya untuk kegiatan yang melanggar hukum atau merugikan pihak lain. Berikut adalah konten, aktivitas, dan muatan yang <strong>dilarang</strong> di semua layanan Perkasa Solusindo:</p>

        <ul class="tos-list tos-list-forbidden">
          <li>Piranti lunak bajakan, program hacker/cracker, situs warez</li>
          <li>Malware, trojan horse, backdoor, spyware, rootkit</li>
          <li>Situs phishing, penipuan (fraud), scam, money game, ponzi/pyramid scheme</li>
          <li>Konten pornografi, eksploitasi anak, dan konten ilegal lainnya</li>
          <li>Konten bermuatan SARA, ujaran kebencian, dan provokasi</li>
          <li>Perjudian online dan sejenisnya</li>
          <li>Bitcoin miner / cryptocurrency mining yang membebani server</li>
          <li>Torrent seeding/leeching, TeamSpeak server ilegal</li>
          <li>Penggunaan VPN untuk phishing, hacking, atau cracking</li>
          <li>Tunneling / Proxy / v2ray / vmess dan sejenisnya (kecuali paket khusus yang diizinkan)</li>
          <li>SPAM, email phishing, atau pengiriman massal tanpa izin</li>
          <li>DDoS, sniffing, exploit, spoofing, trolling, SEO cloaking</li>
          <li>Shell script berbahaya (PHP shell, web shell, dan sejenisnya)</li>
          <li>Private game server tanpa izin</li>
          <li>Berkas audio/video bajakan (pelanggaran HAKI/DMCA)</li>
          <li>IRC daemon dan bot jaringan (botnet)</li>
          <li>Brute force script, web proxy, mail proxy</li>
        </ul>

        <div class="tos-warn-box">
          <p><strong>Sanksi:</strong> Pelanggaran terhadap ketentuan di atas mengakibatkan penangguhan (suspend) atau penghentian permanen (terminate) layanan tanpa pengembalian dana. Perkasa Solusindo berhak melaporkan pelanggaran kepada pihak berwenang.</p>
        </div>

        <h3>Kebijakan Penggunaan Resource Secara Wajar</h3>
        <p>Semua layanan diberikan dengan batasan penggunaan sumber daya secara wajar (<em>fair use policy</em>). Pelanggan yang terindikasi melakukan abuse terhadap CPU, memori, atau bandwidth — akibat malware, CPU hogging, atau sebab lainnya — sehingga mengganggu pelanggan lain, akan dikenai suspend hingga penyalahgunaan tersebut dihentikan.</p>

        <p>Script yang menggunakan resource CPU atau memori di atas 50% secara terus-menerus akan ditegur. Cron Job minimal dijadwalkan setiap 5 menit.</p>
      </div>

      <!-- 5. PRIVASI & DATA -->
      <div class="tos-section" id="tos-privasi">
        <div class="tos-section-header">
          <div class="tos-section-icon">🔒</div>
          <div class="tos-section-meta">
            <div class="tos-section-num">Pasal 5</div>
            <div class="tos-section-title">Privasi &amp; Perlindungan Data</div>
          </div>
        </div>

        <p>Perkasa Solusindo menghargai dan melindungi privasi pelanggan dengan serius. Data pribadi yang kami kumpulkan meliputi: nama, alamat, nomor telepon, email, dan informasi pembayaran.</p>

        <h3>Penggunaan Data Pribadi</h3>
        <ul class="tos-list">
          <li>Penyediaan, pengelolaan, dan penagihan layanan yang Anda gunakan.</li>
          <li>Komunikasi terkait layanan, pembaruan, dan notifikasi penting.</li>
          <li>Peningkatan kualitas layanan dan pengalaman pelanggan.</li>
          <li>Pemenuhan kewajiban hukum dan regulasi yang berlaku.</li>
          <li>Pencegahan penipuan dan keamanan jaringan.</li>
        </ul>

        <p>Perkasa Solusindo <strong>tidak akan menjual</strong> data pribadi pelanggan kepada pihak ketiga untuk kepentingan komersial. Data hanya dibagikan kepada mitra teknis terpercaya dalam rangka penyediaan layanan atau apabila diwajibkan oleh hukum.</p>

        <div class="tos-info-box">
          <p><strong>Keamanan Transaksi:</strong> Seluruh transaksi pembayaran dan data sensitif dilindungi dengan enkripsi SSL. Informasi Anda dienkripsi sebelum dikirimkan ke server kami.</p>
        </div>

        <h3>Salinan &amp; Backup Data</h3>
        <ul class="tos-list">
          <li>Pelanggan bertanggung jawab penuh untuk melakukan backup mandiri atas seluruh data dan file yang tersimpan di layanan Perkasa Solusindo.</li>
          <li>Perkasa Solusindo tidak bertanggung jawab atas kehilangan data akibat kelalaian pelanggan.</li>
          <li>Layanan yang telah kedaluwarsa (expired) tidak dapat dipulihkan datanya.</li>
          <li>Pelanggan disarankan melakukan backup secara berkala ke media penyimpanan lokal.</li>
        </ul>
      </div>

      <!-- 6. PEMBAYARAN & TAGIHAN -->
      <div class="tos-section" id="tos-pembayaran">
        <div class="tos-section-header">
          <div class="tos-section-icon">💳</div>
          <div class="tos-section-meta">
            <div class="tos-section-num">Pasal 6</div>
            <div class="tos-section-title">Pembayaran &amp; Tagihan</div>
          </div>
        </div>

        <p>Pembayaran layanan dilakukan sesuai siklus penagihan yang disepakati saat berlangganan (bulanan atau tahunan). Tagihan dikirimkan melalui email atau sistem client area Perkasa Solusindo sebelum tanggal jatuh tempo.</p>

        <ul class="tos-list">
          <li>Metode pembayaran yang tersedia: Transfer Bank, QRIS, dan metode lain yang tersedia di client area.</li>
          <li>Pembayaran harus dikonfirmasi melalui sistem client area atau menghubungi tim billing kami.</li>
          <li>Perkasa Solusindo tidak bertanggung jawab atas konsekuensi yang timbul dari pembayaran yang tidak dikonfirmasikan.</li>
          <li>Konsekuensi pembayaran tidak dikonfirmasi: domain expired/hilang, hosting tersuspend, hilangnya data — sepenuhnya menjadi tanggung jawab pelanggan.</li>
          <li>Harga layanan dapat berubah sewaktu-waktu dengan pemberitahuan minimal 30 hari sebelumnya.</li>
        </ul>
      </div>

      <!-- 7. PENGHENTIAN LAYANAN -->
      <div class="tos-section" id="tos-penghentian">
        <div class="tos-section-header">
          <div class="tos-section-icon">⏹️</div>
          <div class="tos-section-meta">
            <div class="tos-section-num">Pasal 7</div>
            <div class="tos-section-title">Penghentian Layanan</div>
          </div>
        </div>

        <h3>Karena Non-Pembayaran</h3>
        <p>Perkasa Solusindo memberikan masa tenggang <strong>2 (dua) hari</strong> setelah tanggal jatuh tempo sebelum layanan ditangguhkan:</p>

        <table class="tos-table">
          <thead>
            <tr>
              <th>Hari</th>
              <th>Status Layanan</th>
              <th>Keterangan</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>H+0 s/d H+1</td>
              <td><span class="badge-green">Aktif</span></td>
              <td>Layanan berjalan normal, notifikasi tagihan dikirim.</td>
            </tr>
            <tr>
              <td>H+2</td>
              <td><span class="badge-yellow">Suspend</span></td>
              <td>Layanan ditangguhkan, data masih tersimpan.</td>
            </tr>
            <tr>
              <td>H+16 (14 hari setelah suspend)</td>
              <td><span class="badge-red">Terminate</span></td>
              <td>Layanan dihentikan permanen, data dihapus.</td>
            </tr>
          </tbody>
        </table>

        <h3>Karena Pelanggaran Ketentuan</h3>
        <p>Perkasa Solusindo berhak menolak, membatalkan, atau menghentikan layanan secara sepihak tanpa pengembalian data apabila pelanggan terbukti melanggar Ketentuan Layanan ini, termasuk namun tidak terbatas pada penyalahgunaan layanan, penggunaan konten terlarang, atau aktivitas ilegal.</p>

        <div class="tos-warn-box">
          <p><strong>Penghentian oleh Pelanggan:</strong> Pelanggan dapat menghentikan langganan kapan saja melalui client area. Data akan dihapus sesuai periode layanan yang tersisa. Penghentian yang diprakarsai pelanggan tidak otomatis menghasilkan pengembalian dana.</p>
        </div>
      </div>

      <!-- 8. KEBIJAKAN REFUND -->
      <div class="tos-section" id="tos-refund">
        <div class="tos-section-header">
          <div class="tos-section-icon">🐀𼀼/div>
          <div class="tos-section-meta">
            <div class="tos-section-num">Pasal 8</div>
            <div class="tos-section-title">Kebijakan Pengembalian Dana (Refund)</div>
          </div>
        </div>

        <h3>Kondisi yang Memungkinkan Refund</h3>
        <ul class="tos-list">
          <li>Kesalahan nominal pengiriman oleh pelanggan.</li>
          <li>Double payment / transaksi ganda.</li>
          <li>Transaksi yang dilakukan melalui Transfer Rekening Bank BCA.</li>
        </ul>

        <div class="tos-warn-box">
          <p><strong>Perhatian:</strong> Transaksi melalui metode selain Transfer Bank BCA <strong>tidak dapat di-refund</strong> ke rekening asal. Dana akan dikembalikan ke saldo akun pelanggan.</p>
        </div>

        <h3>Layanan yang Dapat Di-Refund</h3>
        <ul class="tos-list">
          <li>Cloud Hosting cPanel (apabila terdapat domain, dikurangi biaya domain)</li>
          <li>Cloud Hosting Direct Admin (apabila terdapat domain, dikurangi biaya domain)</li>
          <li>Unlimited Hosting (apabila terdapat domain, dikurangi biaya domain)</li>
        </ul>

        <h3>Periode Pengajuan Refund</h3>
        <p>Refund dapat diajukan apabila berlangganan <strong>kurang dari 3 hari</strong> (siklus bulanan) atau <strong>kurang dari 30 hari</strong> (siklus tahunan). Lebih dari periode tersebut, refund dihitung berdasarkan sisa pemakaian (prorate).</p>

        <h3>Ketentuan Tambahan Refund</h3>
        <ul class="tos-list">
          <li>Refund hanya dapat dilakukan dengan mengajukan tiket support ke Divisi Billing.</li>
          <li>Refund tidak dapat dilakukan apabila pelanggan mengajukan cancellation secara mandiri.</li>
          <li>Transfer refund ke rekening bank pengirim; biaya transfer dikurangi dari nilai refund.</li>
          <li>Minimal refund ke rekening bank adalah Rp 50.000. Di bawah nominal tersebut, refund dikreditkan ke saldo akun.</li>
          <li>Perkasa Solusindo berhak menolak refund bagi pelanggan yang melanggar Kebijakan Layanan.</li>
        </ul>
      </div>

      <!-- 9. SLA UMUM -->
      <div class="tos-section" id="tos-sla">
        <div class="tos-section-header">
          <div class="tos-section-icon">📊</div>
          <div class="tos-section-meta">
            <div class="tos-section-num">Pasal 9</div>
            <div class="tos-section-title">Service Level Agreement (SLA) — Umum</div>
          </div>
        </div>

        <p>Perkasa Solusindo berkomitmen untuk memberikan layanan dengan ketersediaan (<em>availability</em>) dan kualitas tinggi. SLA ini mendefinisikan standar minimum yang dijamin kepada seluruh pelanggan aktif.</p>

        <div class="tos-sla-grid">
          <div class="tos-sla-card">
            <div class="tos-sla-card-num">99%</div>
            <div class="tos-sla-card-label">Jaminan Uptime Bulanan untuk semua layanan aktif</div>
          </div>
          <div class="tos-sla-card">
            <div class="tos-sla-card-num">24 Jam</div>
            <div class="tos-sla-card-label">Notifikasi sebelum maintenance terjadwal</div>
          </div>
          <div class="tos-sla-card">
            <div class="tos-sla-card-num">4 Jam</div>
            <div class="tos-sla-card-label">Respon pertama tiket support pada jam kerja</div>
          </div>
        </div>

        <h3>Pengecualian SLA</h3>
        <p>Jaminan uptime tidak berlaku untuk downtime yang disebabkan oleh:</p>
        <ul class="tos-list">
          <li>Kesalahan konfigurasi, human error, atau kelalaian dari pihak pelanggan.</li>
          <li>Kegagalan setup atau tidak memperbarui lisensi perangkat lunak pelanggan.</li>
          <li>Force majeure: bencana alam, gangguan listrik PLN, pemadaman jaringan nasional.</li>
          <li>Serangan DDoS berskala besar atau serangan siber dari luar.</li>
          <li>Maintenance terjadwal yang telah dinotifikasikan minimal 24 jam sebelumnya.</li>
          <li>Pelanggaran ketentuan layanan oleh pelanggan sendiri.</li>
        </ul>

        <div class="tos-info-box">
          <p><strong>Prosedur Klaim SLA:</strong> Klaim dapat diajukan melalui tiket support apabila downtime dalam satu bulan melebihi 1% (sekitar 7,2 jam/bulan). Klaim dihitung atas downtime aktual, bukan akumulasi gangguan kecil. Kompensasi berupa kredit layanan sesuai proporsi downtime yang terjadi.</p>
        </div>
      </div>

      <!-- 10. SLA WIFI INTERNET -->
      <div class="tos-section" id="tos-wifi">
        <div class="tos-section-header">
          <div class="tos-section-icon">📡</div>
          <div class="tos-section-meta">
            <div class="tos-section-num">Pasal 10</div>
            <div class="tos-section-title">SLA — Provider Wifi Internet</div>
          </div>
        </div>

        <p>Layanan Internet Perkasa Solusindo menggunakan infrastruktur fiber optik dedicated untuk memberikan koneksi yang stabil dan berkualitas tinggi di area Sidoarjo, Surabaya, dan sekitarnya.</p>

        <table class="tos-table">
          <thead>
            <tr>
              <th>Parameter</th>
              <th>Standar Layanan</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>Uptime Jaringan</td>
              <td>Minimum 99% per bulan</td>
              <td><span class="badge-green">Dijamin</span></td>
            </tr>
            <tr>
              <td>Kecepatan</td>
              <td>Sesuai paket yang dibeli (shared/dedicated)</td>
              <td><span class="badge-green">Dijamin</span></td>
            </tr>
            <tr>
              <td>Respon Gangguan</td>
              <td>Maksimal 4 jam pada hari kerja</td>
              <td><span class="badge-green">Dijamin</span></td>
            </tr>
            <tr>
              <td>Instalasi Baru</td>
              <td>Maksimal 3 hari kerja setelah pembayaran</td>
              <td><span class="badge-green">Dijamin</span></td>
            </tr>
            <tr>
              <td>Support Teknis</td>
              <td>Chat/telepon Senin–Sabtu 08.00–17.00 WIB</td>
              <td><span class="badge-green">Tersedia</span></td>
            </tr>
            <tr>
              <td>Planned Maintenance</td>
              <td>Notifikasi minimal 24 jam sebelumnya</td>
              <td><span class="badge-green">Diterapkan</span></td>
            </tr>
          </tbody>
        </table>

        <h3>Ketentuan Khusus Internet</h3>
        <ul class="tos-list">
          <li>Layanan internet digunakan sesuai paket yang dipilih. Upgrade/downgrade dapat dilakukan melalui client area.</li>
          <li>Perkasa Solusindo berhak membatasi atau menangguhkan koneksi yang terbukti digunakan untuk aktivitas ilegal atau menyebabkan gangguan pada jaringan.</li>
          <li>Gangguan yang disebabkan oleh kerusakan perangkat pelanggan sendiri tidak termasuk dalam cakupan SLA.</li>
          <li>Koneksi internet pada paket shared bersifat best-effort; kecepatan dapat bervariasi berdasarkan kondisi jaringan.</li>
        </ul>
      </div>

      <!-- 11. SLA HOSTING -->
      <div class="tos-section" id="tos-hosting">
        <div class="tos-section-header">
          <div class="tos-section-icon">☁️</div>
          <div class="tos-section-meta">
            <div class="tos-section-num">Pasal 11</div>
            <div class="tos-section-title">SLA — Layanan Hosting</div>
          </div>
        </div>

        <p>Layanan hosting Perkasa Solusindo menggunakan server lokal Indonesia dengan infrastruktur redundan untuk memastikan website pelanggan selalu online dan berperforma tinggi.</p>

        <table class="tos-table">
          <thead>
            <tr>
              <th>Parameter</th>
              <th>Standar Layanan</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>Uptime Server</td>
              <td>Minimum 99% per bulan</td>
              <td><span class="badge-green">Dijamin</span></td>
            </tr>
            <tr>
              <td>Backup Otomatis</td>
              <td>Mingguan (untuk akun dengan inode &lt; 75.000 &amp; disk &lt; 5GB)</td>
              <td><span class="badge-green">Tersedia</span></td>
            </tr>
            <tr>
              <td>SSL Gratis</td>
              <td>Let's Encrypt SSL di semua paket</td>
              <td><span class="badge-green">Disertakan</span></td>
            </tr>
            <tr>
              <td>Respon Support</td>
              <td>Maksimal 4 jam pada hari kerja</td>
              <td><span class="badge-green">Dijamin</span></td>
            </tr>
            <tr>
              <td>Migrasi Hosting</td>
              <td>Gratis untuk pelanggan baru (1x)</td>
              <td><span class="badge-green">Tersedia</span></td>
            </tr>
          </tbody>
        </table>

        <h3>Kebijakan Khusus Unlimited Hosting</h3>
        <p>Layanan Unlimited Hosting memiliki batasan penggunaan resource sebagai berikut:</p>
        <ol class="tos-list-num">
          <li>Tidak diperbolehkan digunakan untuk Video Streaming, File Sharing, Mail Server lebih dari 10GB, atau Online Storage lebih dari 10GB.</li>
          <li>Ukuran maksimal satu file yang disimpan adalah <strong>1GB</strong>. File melebihi batas ini dapat dihapus tanpa backup.</li>
          <li>Batasan Inodes (jumlah file) per akun adalah <strong>250.000</strong>.</li>
          <li>Backup otomatis hanya berjalan pada akun dengan inode kurang dari 75.000 atau disk space kurang dari 5GB.</li>
          <li>Penggunaan email dalam satu akun tidak boleh melebihi <strong>10GB</strong>. Notifikasi akan dikirim bila mendekati batas.</li>
          <li>Pelanggaran ketentuan ini akan mengakibatkan suspend atau terminate dengan perhitungan refund prorate.</li>
        </ol>

        <h3>Kebijakan VPS</h3>
        <ul class="tos-list">
          <li>VPS Linux/Windows dilarang digunakan untuk kegiatan Tunneling, Proxy, v2ray, vmess, dan sejenisnya. Pelanggaran: Terminate tanpa Refund.</li>
          <li>Bandwidth internasional pada setiap VPS dibatasi 1 Mbps kecuali paket khusus.</li>
          <li>Perkasa Solusindo dapat memindahkan layanan VPS ke node lain dalam kondisi darurat tanpa pemberitahuan terlebih dahulu.</li>
        </ul>
      </div>

      <!-- 12. SLA WEBSITE -->
      <div class="tos-section" id="tos-website">
        <div class="tos-section-header">
          <div class="tos-section-icon">💻</div>
          <div class="tos-section-meta">
            <div class="tos-section-num">Pasal 12</div>
            <div class="tos-section-title">SLA — Pembuatan Website</div>
          </div>
        </div>

        <p>Layanan pembuatan website Perkasa Solusindo mencakup perencanaan, desain, pengembangan, dan penerbitan website sesuai kebutuhan pelanggan.</p>

        <table class="tos-table">
          <thead>
            <tr>
              <th>Parameter</th>
              <th>Standar Layanan</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>Estimasi Pengerjaan</td>
              <td>Sesuai perjanjian di kontrak kerja</td>
              <td><span class="badge-green">Dijamin</span></td>
            </tr>
            <tr>
              <td>Revisi Desain</td>
              <td>Maksimal sesuai paket (minimal 2x revisi)</td>
              <td><span class="badge-green">Disertakan</span></td>
            </tr>
            <tr>
              <td>Garansi Bug / Error</td>
              <td>30 hari setelah serah terima website</td>
              <td><span class="badge-green">Dijamin</span></td>
            </tr>
            <tr>
              <td>Serah Terima Aset</td>
              <td>Source code, akun hosting/domain diserahkan setelah pelunasan</td>
              <td><span class="badge-green">Diterapkan</span></td>
            </tr>
            <tr>
              <td>Perubahan Mayor Pasca Serah Terima</td>
              <td>Dikenakan biaya pengembangan tambahan</td>
              <td><span class="badge-yellow">Berbayar</span></td>
            </tr>
          </tbody>
        </table>

        <h3>Ketentuan Khusus Pembuatan Website</h3>
        <ul class="tos-list">
          <li>Pelanggan wajib menyediakan materi konten (teks, gambar, logo) dalam waktu yang disepakati. Keterlambatan penyerahan konten dapat memengaruhi jadwal pengerjaan.</li>
          <li>Hak cipta desain dan kode program menjadi milik pelanggan setelah pelunasan pembayaran.</li>
          <li>Perkasa Solusindo tidak bertanggung jawab atas konten yang melanggar hak cipta pihak ketiga yang disediakan oleh pelanggan.</li>
          <li>Garansi tidak mencakup kerusakan akibat modifikasi yang dilakukan sendiri oleh pelanggan setelah serah terima.</li>
        </ul>
      </div>

      <!-- 13. SLA KOMPUTER & IT -->
      <div class="tos-section" id="tos-komputer">
        <div class="tos-section-header">
          <div class="tos-section-icon">🖥️</div>
          <div class="tos-section-meta">
            <div class="tos-section-num">Pasal 13</div>
            <div class="tos-section-title">SLA — Komputer &amp; Layanan IT</div>
          </div>
        </div>

        <p>Layanan jual, perakitan, instalasi, dan teknis komputer Perkasa Solusindo dilaksanakan oleh teknisi berpengalaman dengan menggunakan komponen berkualitas.</p>

        <table class="tos-table">
          <thead>
            <tr>
              <th>Parameter</th>
              <th>Standar Layanan</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>Garansi Perangkat Baru</td>
              <td>Sesuai garansi resmi produsen/distributor</td>
              <td><span class="badge-green">Berlaku</span></td>
            </tr>
            <tr>
              <td>Garansi Instalasi &amp; Setup</td>
              <td>30 hari untuk kesalahan teknis dari pihak kami</td>
              <td><span class="badge-green">Dijamin</span></td>
            </tr>
            <tr>
              <td>Respon Teknisi On-site</td>
              <td>Maksimal 1 hari kerja (area Sidoarjo &amp; Surabaya)</td>
              <td><span class="badge-green">Dijamin</span></td>
            </tr>
            <tr>
              <td>Estimasi Perbaikan</td>
              <td>Diberikan sebelum pengerjaan dimulai</td>
              <td><span class="badge-green">Diterapkan</span></td>
            </tr>
            <tr>
              <td>Kerusakan di Luar Garansi</td>
              <td>Dikenakan biaya service &amp; suku cadang</td>
              <td><span class="badge-yellow">Berbayar</span></td>
            </tr>
          </tbody>
        </table>

        <h3>Ketentuan Khusus Layanan IT</h3>
        <ul class="tos-list">
          <li>Perkasa Solusindo tidak bertanggung jawab atas kehilangan data akibat kerusakan hardware. Pelanggan disarankan melakukan backup sebelum diserahkan ke teknisi.</li>
          <li>Garansi tidak berlaku untuk kerusakan akibat cairan, fisik (jatuh/benturan), atau penyalahgunaan di luar kondisi normal.</li>
          <li>Suku cadang yang digunakan adalah komponen original atau berkualitas setara, dikomunikasikan kepada pelanggan sebelum pemasangan.</li>
        </ul>
      </div>

      <!-- 14. SLA CCTV -->
      <div class="tos-section" id="tos-cctv">
        <div class="tos-section-header">
          <div class="tos-section-icon">📷</div>
          <div class="tos-section-meta">
            <div class="tos-section-num">Pasal 14</div>
            <div class="tos-section-title">SLA — Pemasangan CCTV</div>
          </div>
        </div>

        <p>Layanan pemasangan CCTV Perkasa Solusindo mencakup survei lokasi, instalasi kamera dan DVR/NVR, konfigurasi, pengujian, dan pelatihan penggunaan dasar.</p>

        <table class="tos-table">
          <thead>
            <tr>
              <th>Parameter</th>
              <th>Standar Layanan</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>Survei Lokasi</td>
              <td>Gratis sebelum pengerjaan</td>
              <td><span class="badge-green">Disertakan</span></td>
            </tr>
            <tr>
              <td>Garansi Instalasi</td>
              <td>90 hari untuk pekerjaan instalasi (kabel, mounting)</td>
              <td><span class="badge-green">Dijamin</span></td>
            </tr>
            <tr>
              <td>Garansi Perangkat CCTV</td>
              <td>Sesuai garansi resmi produsen (min. 1 tahun)</td>
              <td><span class="badge-green">Berlaku</span></td>
            </tr>
            <tr>
              <td>Respon Keluhan Pasca Instalasi</td>
              <td>Maksimal 2 hari kerja</td>
              <td><span class="badge-green">Dijamin</span></td>
            </tr>
            <tr>
              <td>Pelatihan Penggunaan</td>
              <td>Diberikan setelah instalasi selesai</td>
              <td><span class="badge-green">Disertakan</span></td>
            </tr>
          </tbody>
        </table>

        <h3>Ketentuan Khusus CCTV</h3>
        <ul class="tos-list">
          <li>Pemasangan CCTV harus digunakan sesuai hukum yang berlaku. Pelanggan bertanggung jawab atas penggunaan rekaman CCTV dan tidak boleh digunakan untuk kegiatan ilegal atau melanggar privasi pihak lain.</li>
          <li>Garansi tidak mencakup kerusakan akibat bencana alam, vandalism, atau kondisi force majeure.</li>
          <li>Layanan berlangganan maintenance CCTV (jika dipilih) mencakup pengecekan berkala dan pembersihan kamera setiap 3 bulan.</li>
        </ul>
      </div>

      <!-- 15. HUKUM & YURISDIKSI -->
      <div class="tos-section" id="tos-hukum">
        <div class="tos-section-header">
          <div class="tos-section-icon">🏛️</div>
          <div class="tos-section-meta">
            <div class="tos-section-num">Pasal 15</div>
            <div class="tos-section-title">Hukum, Yurisdiksi &amp; Penyelesaian Sengketa</div>
          </div>
        </div>

        <p>Ketentuan Layanan ini diatur dan ditafsirkan sesuai dengan <strong>hukum Negara Republik Indonesia</strong>. Dengan menggunakan layanan Perkasa Solusindo, Anda setuju untuk tunduk pada yurisdiksi eksklusif pengadilan Indonesia.</p>

        <h3>Penyelesaian Sengketa</h3>
        <ol class="tos-list-num">
          <li><strong>Musyawarah:</strong> Kedua pihak mengutamakan penyelesaian secara musyawarah dan mufakat dalam waktu 14 hari kalender.</li>
          <li><strong>Mediasi:</strong> Apabila musyawarah tidak menghasilkan kesepakatan, sengketa diselesaikan melalui mediasi dengan mediator yang disepakati bersama.</li>
          <li><strong>Pengadilan:</strong> Sebagai upaya terakhir, sengketa diselesaikan melalui Pengadilan Negeri yang berwenang di wilayah Sidoarjo, Jawa Timur.</li>
        </ol>

        <h3>Ketentuan Umum Akhir</h3>
        <ul class="tos-list">
          <li>Apabila terdapat konflik antara ketentuan ini dengan perjanjian khusus yang ditandatangani, maka perjanjian khusus tersebut yang berlaku.</li>
          <li>Kegagalan Perkasa Solusindo menjalankan hak tertentu tidak berarti pengabaian hak tersebut secara permanen.</li>
          <li>Apabila satu klausul dinyatakan tidak sah secara hukum, klausul lainnya tetap berlaku penuh.</li>
          <li>Perkasa Solusindo menghormati hak kekayaan intelektual pihak lain. Laporan pelanggaran HAKI dapat dikirim ke email resmi kami.</li>
        </ul>
      </div>

      <!-- 16. KONTAK -->
      <div class="tos-section" id="tos-kontak">
        <div class="tos-section-header">
          <div class="tos-section-icon">📞</div>
          <div class="tos-section-meta">
            <div class="tos-section-num">Pasal 16</div>
            <div class="tos-section-title">Kontak &amp; Pengaduan</div>
          </div>
        </div>

        <p>Untuk pertanyaan, pengaduan, atau klaim terkait layanan dan ketentuan ini, silakan hubungi kami melalui:</p>

        <ul class="tos-list">
          <li><strong>Email:</strong> info-perkasa@perkasasolusindo.co.id</li>
          <li><strong>WhatsApp / Telepon:</strong> +62 812-4668-4665</li>
          <li><strong>Client Area:</strong> <a href="https://perkasasolusindo.co.id/login/login.php" style="color:var(--accent);">perkasasolusindo.co.id/login</a></li>
          <li><strong>Alamat:</strong> Jln. KedungRejo, Wedoroklurak, Candi, Sidoarjo, Jawa Timur 61271</li>
          <li><strong>Jam Layanan:</strong> Senin–Sabtu, 08.00–17.00 WIB</li>
        </ul>

        <div class="tos-info-box">
          <p><strong>Tiket Support:</strong> Untuk klaim SLA dan pengaduan teknis, disarankan mengajukan tiket melalui Client Area agar tercatat dan ditangani secara terstruktur oleh tim yang berwenang.</p>
        </div>
      </div>

      <!-- Footer strip -->
      <div class="tos-footer-strip">
        <div class="tos-footer-strip-left">
          <h3>PT. Perkasa Tech Solusindo</h3>
          <p>Dokumen ini berlaku sejak 1 Januari 2025 &middot; Terakhir diperbarui Juni 2026</p>
        </div>
        <div class="tos-footer-strip-actions">
          <a href="index.php" class="btn-outline"><i class="fa fa-home"></i> Kembali ke Beranda</a>
          <a href="index.php#contact" class="btn-primary"><i class="fa fa-comments"></i> Hubungi Kami</a>
        </div>
      </div>

    </main>
  </div><!-- /tos-layout -->
</div><!-- /container -->


<!-- ══ Footer ═══════════════════════════════════════════════ -->
<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div>
        <a href="index.php" class="nav-logo" style="margin-bottom:16px;display:inline-flex;">
          <img src="assets/images/CDR LOGO PERKASA Putih with border.png" alt="Perkasa Logo" style="width:38px;height:38px;">
          <div class="nav-logo-text">PERKASA <span>TECH</span><small>Solusindo</small></div>
        </a>
        <p style="color:var(--text3);font-size:13.5px;line-height:1.7;max-width:280px;margin-bottom:20px;">
          Solusi teknologi terpadu untuk rumah dan bisnis Anda — dari internet, hosting, website, komputer, hingga CCTV.
        </p>
        <div class="social-links">
          <a href="https://wa.me/6281246684665" class="social-btn"><i class="fa fa-whatsapp"></i></a>
          <a href="mailto:info-perkasa@perkasasolusindo.co.id" class="social-btn"><i class="fa fa-envelope"></i></a>
        </div>
      </div>

      <div>
        <div class="footer-col-title">Layanan</div>
        <div class="footer-links">
          <a href="index.php#services">📡 Provider Wifi Internet</a>
          <a href="index.php#services">☁️ Sewa Hosting</a>
          <a href="index.php#services">💻 Pembuatan Website</a>
          <a href="index.php#services">🖥️ Jual &amp; Install Komputer</a>
          <a href="index.php#services">📷 Pemasangan CCTV</a>
        </div>
      </div>

      <div>
        <div class="footer-col-title">Tautan</div>
        <div class="footer-links">
          <a href="index.php">Beranda</a>
          <a href="index.php#services">Layanan</a>
          <a href="index.php#projects">Portofolio</a>
          <a href="index.php#why">Tentang Kami</a>
          <a href="index.php#contact">Kontak</a>
          <a href="ketentuan_layanan.php" style="color:var(--accent);">Ketentuan &amp; SLA</a>
          <a href="https://perkasasolusindo.co.id/login/login.php">Login Client Area</a>
        </div>
      </div>
    </div>

    <div class="footer-bottom">
      <span>Copyright &copy; 2026 <span>PERKASA TECH SOLUSINDO</span>. All rights reserved.</span>
      <span>Dibuat dengan ❤️ di Sidoarjo, Jawa Timur</span>
    </div>
  </div>
</footer>

<!-- ══ Scripts ══════════════════════════════════════════════ -->
<script>
/* ── Preloader ──────────────────────────────────────────── */
window.addEventListener('load', () => {
  setTimeout(() => {
    document.getElementById('preloader').classList.add('hidden');
  }, 1000);
});

/* ── Sticky header ──────────────────────────────────────── */
const header = document.getElementById('siteHeader');
const preH   = document.querySelector('.pre-header');
const PRE_H  = 38;

window.addEventListener('scroll', () => {
  const y = window.scrollY;
  if (y > PRE_H) {
    preH?.classList.add('hidden');
    header.classList.add('scrolled');
  } else {
    preH?.classList.remove('hidden');
    header.classList.remove('scrolled');
  }
}, { passive: true });

/* ── Nav toggle (mobile) ────────────────────────────────── */
const navToggle = document.getElementById('navToggle');
const navLinks  = document.getElementById('navLinks');

function openNav() {
  navLinks.classList.add('open');
  navToggle.classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeNav() {
  navLinks.classList.remove('open');
  navToggle.classList.remove('open');
  document.body.style.overflow = '';
}

navToggle?.addEventListener('click', () => {
  navLinks.classList.contains('open') ? closeNav() : openNav();
});
navLinks?.querySelectorAll('a').forEach(a => a.addEventListener('click', closeNav));
document.addEventListener('click', e => {
  if (navLinks.classList.contains('open') && !navLinks.contains(e.target) && !navToggle.contains(e.target)) closeNav();
});

/* ── TOC active state on scroll ─────────────────────────── */
const sections  = document.querySelectorAll('.tos-section[id]');
const tocLinks  = document.querySelectorAll('.tos-toc a');

const tocObserver = new IntersectionObserver(entries => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      tocLinks.forEach(a => a.classList.remove('active'));
      const link = document.querySelector(`.tos-toc a[href="#${e.target.id}"]`);
      if (link) link.classList.add('active');
    }
  });
}, { rootMargin: '-80px 0px -60% 0px', threshold: 0 });

sections.forEach(s => tocObserver.observe(s));

/* ── Smooth scroll ──────────────────────────────────────── */
document.querySelectorAll('a[href^="#"]').forEach(a => {
  a.addEventListener('click', e => {
    const target = document.querySelector(a.getAttribute('href'));
    if (target) {
      e.preventDefault();
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  });
});
</script>

</body>
</html>
