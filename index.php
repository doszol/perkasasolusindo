<?php
// config.php HARUS di-include sebelum output HTML apapun.
// Posisi lama (di dalam <html>) salah — bisa menyebabkan header already sent
// dan output buffering issues.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_check.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="description" content="Perkasa Tech Solusindo — Provider Wifi Internet, Sewa Hosting, Pembuatan Website, Jual & Install Komputer, Pemasangan CCTV di Sidoarjo, Jawa Timur.">
  <title>PERKASA SOLUSINDO — Solusi Teknologi Terpadu</title>
  <link rel="icon" type="image/png" href="assets/images/CDR LOGO PERKASA Putih with border.png">

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,300&display=swap" rel="stylesheet">

  <!-- Icons -->
  <link rel="stylesheet" href="assets/css/fontawesome.css">

  <!-- Custom CSS only -->
  <link rel="stylesheet" href="style_index_barubanget.css">
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
      <li><a href="#contact"><i class="fa fa-map-marker"></i>Jln. KedungRejo, Wedoroklurak, Candi, Jawa Timur 61271</a></li>
    </ul>
  </div>
</div>

<!-- ══ Header / Nav ════════════════════════════════════════ -->
<header class="site-header" id="siteHeader">
  <div class="container">
    <div class="nav-inner">

      <a href="index.php" class="nav-logo">
        <img src="assets/images/CDR LOGO PERKASA Putih with border.png" alt="Perkasa Logo">
        <div class="nav-logo-text">
          PERKASA <span>TECH</span>
          <small>Solusindo</small>
        </div>
      </a>

      <nav>
        <ul class="nav-links" id="navLinks">
          <li><a href="#hero"     class="active">Home</a></li>
          <li><a href="#services">Layanan</a></li>
          <li><a href="#projects">Proyek</a></li>
          <li><a href="#why">Tentang</a></li>
          <li><a href="#contact">Kontak</a></li>
          <li><a href="https://perkasasolusindo.co.id/login/login.php" class="nav-cta"><i class="fa fa-user"></i>&nbsp;Login</a></li>
        </ul>
      </nav>

      <button class="nav-toggle" id="navToggle" aria-label="Menu">
        <span></span><span></span><span></span>
      </button>

    </div>
  </div>
</header>

<!-- ══ Hero ════════════════════════════════════════════════ -->
<section class="hero section" id="hero">
  <div class="hero-bg"></div>
  <div class="hero-grid"></div>

  <div class="container">
    <div class="hero-content">

      <!-- Left Text -->
      <div class="hero-text fade-up">
        <div class="hero-badge">
          <span class="dot"></span> Solusi Teknologi Terpadu Sejawa - Bali
        </div>

        <h1 class="hero-title">
          <span class="line">Semua</span>
          <span class="line accent">Solusi Teknologi</span>
          <span class="line outlined">Ada Di Kami</span>
        </h1>

        <p class="hero-desc">
          Wujudkan transformasi digital perusahaan Anda bersama Perkasa Tech Solusindo — mulai dari Internet, Hosting, Website, Komputer, hingga CCTV. Terpercaya, cepat, dan andal.
        </p>

        <div class="hero-actions">
          <a href="#services" class="btn-primary">
            <i class="fa fa-th-large"></i> Lihat Layanan
          </a>
          <a href="#contact" class="btn-outline">
            <i class="fa fa-comments"></i> Konsultasi Gratis
          </a>
        </div>

        <div class="hero-stats">
          <div class="stat-item">
            <div class="stat-num count-up" data-target="<?php
              $r = $conn->query("SELECT COUNT(*) as c FROM tblclients WHERE level=3 AND status=1");
              echo ($r && $row = $r->fetch_assoc()) ? max($row['c'], 50) : 50;
            ?>"><span>0</span></div>
            <div class="stat-label">Klien Aktif</div>
          </div>
          <div class="stat-item">
            <div class="stat-num count-up" data-target="<?php
              $r = $conn->query("SELECT COUNT(*) as c FROM projects");
              echo ($r && $row = $r->fetch_assoc()) ? max($row['c'], 20) : 20;
            ?>"><span>0</span></div>
            <div class="stat-label">Proyek Selesai</div>
          </div>
          <div class="stat-item">
            <div class="stat-num"><span>5</span></div>
            <div class="stat-label">Kategori Layanan</div>
          </div>
          <div class="stat-item">
            <div class="stat-num"><span>99</span><span style="color:var(--accent)">%</span></div>
            <div class="stat-label">Uptime SLA</div>
          </div>
        </div>
      </div>

      <!-- Right Visual -->
      <div class="hero-visual fade-up">
        <div class="hero-card-stack">
          <div class="hero-main-card">
            <div class="hero-card-icon">🌐</div>
            <div class="hero-card-title">Koneksi Internet Stabil</div>
            <div class="hero-card-sub">Didukung jaringan fiber optik berkecepatan tinggi</div>
            <div class="speed-bar">
              <div class="speed-row">
                <label>Download</label>
                <div class="speed-track"><div class="speed-fill" style="width:90%"></div></div>
                <span class="speed-val">90 Mbps</span>
              </div>
              <div class="speed-row">
                <label>Upload</label>
                <div class="speed-track"><div class="speed-fill" style="width:75%"></div></div>
                <span class="speed-val">75 Mbps</span>
              </div>
              <div class="speed-row">
                <label>Latency</label>
                <div class="speed-track"><div class="speed-fill" style="width:20%;background:linear-gradient(90deg,#22c55e,#16a34a)"></div></div>
                <span class="speed-val" style="color:#22c55e">8 ms</span>
              </div>
            </div>
          </div>

          <div class="hero-float-badge top-right">
            <span class="badge-icon">🔒</span>
            <div>
              <div style="font-weight:700;font-size:13px;">SSL Gratis</div>
              <div style="color:var(--text3);font-size:11px;">Setiap paket hosting</div>
            </div>
          </div>

          <div class="hero-float-badge bottom-left">
            <span class="badge-icon">⚡</span>
            <div>
              <div style="font-weight:700;font-size:13px;">Setup 24 Jam</div>
              <div style="color:var(--text3);font-size:11px;">Instalasi cepat & profesional</div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</section>


<!-- ══ Services Section ═══════════════════════════════════ -->
<?php
/* Pre-fetch all active products grouped by category */
$allProducts = [];
$pResult = $conn->query("SELECT * FROM tblproducts WHERE status=1 ORDER BY price ASC");
if ($pResult) {
  while ($pRow = $pResult->fetch_assoc()) {
    $allProducts[$pRow['category']][] = $pRow;
  }
}

$services = [
  'wifi' => [
    'label'    => 'Provider Wifi Internet',
    'icon'     => '📡',
    'color'    => 'blue',
    'tag'      => 'Internet & Jaringan',
    'title'    => 'Internet Cepat,<br>Stabil, & Terpercaya',
    'desc'     => 'Nikmati koneksi internet fiber optik berkecepatan tinggi untuk rumah dan bisnis Anda. Coverage luas di area Sidoarjo & Surabaya dengan garansi uptime 99%.',
    'features' => ['Fiber Optik Dedicated','Uptime 99% SLA','Support 24/7','Instalasi Gratis'],
    'cta'      => 'Daftar Sekarang',
  ],
  'hosting' => [
    'label'    => 'Sewa Hosting',
    'icon'     => '☁️',
    'color'    => 'hosting',
    'tag'      => 'Hosting & Domain',
    'title'    => 'Hosting Andal,<br>Website Selalu Online',
    'desc'     => 'Hosting dengan server lokal Indonesia, panel cPanel yang mudah digunakan, SSL gratis di semua paket, dan backup harian otomatis untuk ketenangan pikiran Anda.',
    'features' => ['cPanel Mudah','SSL Gratis','Backup Harian','Bandwidth Unlimited'],
    'cta'      => 'Pilih Paket Hosting',
  ],
  'website' => [
    'label'    => 'Pembuatan Website',
    'icon'     => '💻',
    'color'    => 'website',
    'tag'      => 'Web Development',
    'title'    => 'Website Profesional<br>Siap Pakai',
    'desc'     => 'Dari company profile hingga toko online. Kami membangun website modern, responsif, dan SEO-friendly yang mencerminkan identitas bisnis Anda secara profesional.',
    'features' => ['Desain Modern','Mobile Responsive','SEO Friendly','Domain .com Gratis'],
    'cta'      => 'Diskusi Proyek',
  ],
  'komputer' => [
    'label'    => 'Jual & Install Komputer',
    'icon'     => '🖥️',
    'color'    => 'komputer',
    'tag'      => 'Hardware & IT',
    'title'    => 'Komputer Custom<br>Sesuai Kebutuhan',
    'desc'     => 'Perakitan PC workstation, servis laptop & komputer, upgrade hardware, hingga instalasi software. Konsultasi spesifikasi gratis dengan teknisi berpengalaman.',
    'features' => ['Konsultasi Gratis','Garansi Jasa','Teknisi Berpengalaman','Upgrade & Servis'],
    'cta'      => 'Konsultasi Spesifikasi',
  ],
  'cctv' => [
    'label'    => 'Pemasangan CCTV',
    'icon'     => '📷',
    'color'    => 'cctv',
    'tag'      => 'Keamanan & Surveilans',
    'title'    => 'CCTV Full HD<br>Keamanan Terjamin',
    'desc'     => 'Sistem pengawasan CCTV Full HD 2MP dengan DVR multi-channel, monitoring remote via smartphone, dan garansi alat 1 tahun. Cocok untuk rumah, kantor, dan gudang.',
    'features' => ['Full HD 2MP','Remote Monitoring','Garansi 1 Tahun','Instalasi Profesional'],
    'cta'      => 'Pasang Sekarang',
  ],
];
?>

<section class="services-section section" id="services">
  <div class="container">
    <div class="services-header fade-up">
      <div class="section-label">Layanan Kami</div>
      <h2 class="section-title">Pilih <em>Layanan</em> yang Anda Butuhkan</h2>
      <p class="section-desc">Klik kategori layanan di bawah untuk melihat paket harga terbaik yang sesuai dengan kebutuhan Anda.</p>
    </div>

    <!-- Tab Buttons -->
    <div class="services-tabs fade-up" role="tablist">
      <?php $first = true; foreach ($services as $key => $svc): ?>
      <button
        class="svc-tab <?php echo $first ? 'active' : ''; ?>"
        data-tab="<?php echo $key; ?>"
        role="tab"
        aria-selected="<?php echo $first ? 'true' : 'false'; ?>"
      >
        <span class="tab-icon"><?php echo $svc['icon']; ?></span>
        <?php echo $svc['label']; ?>
      </button>
      <?php $first = false; endforeach; ?>
    </div>

    <!-- Tab Panels -->
    <?php $first = true; foreach ($services as $key => $svc): ?>
    <div class="svc-panel <?php echo $first ? 'active' : ''; ?>" id="panel-<?php echo $key; ?>" role="tabpanel">

      <!-- Banner -->
      <div class="svc-banner <?php echo $key; ?>">
        <div class="svc-banner-glow"></div>
        <div class="svc-banner-inner">
          <div class="svc-banner-left">
            <div class="svc-banner-icon"><?php echo $svc['icon']; ?></div>
            <div class="svc-banner-tag"><?php echo $svc['tag']; ?></div>
            <h3 class="svc-banner-title"><?php echo $svc['title']; ?></h3>
            <p class="svc-banner-desc"><?php echo $svc['desc']; ?></p>
            <div class="svc-banner-cta">
              <?php
                if ($key === 'wifi' && !empty($allProducts['wifi'])) {
                  // Wifi: langsung ke halaman order dengan paket pertama (termurah)
                  $firstPaket = $allProducts['wifi'][0];
                  echo '<a href="order/order_wifi.php?paket_id=' . (int)$firstPaket['id'] . '" class="btn-primary">'
                     . '<i class="fa fa-rocket"></i> ' . htmlspecialchars($svc['cta'])
                     . '</a>';
                } elseif ($key === 'hosting' && !empty($allProducts['hosting'])) {
                  // Hosting: ke halaman order hosting dengan paket pertama (termurah)
                  $firstHosting = $allProducts['hosting'][0];
                  echo '<a href="order/order_hosting.php?paket_id=' . (int)$firstHosting['id'] . '" class="btn-primary">'
                     . '<i class="fa fa-cloud"></i> ' . htmlspecialchars($svc['cta'])
                     . '</a>';
                } else {
                  echo '<a href="#contact" class="btn-primary">'
                     . '<i class="fa fa-arrow-right"></i> ' . htmlspecialchars($svc['cta'])
                     . '</a>';
                }
              ?>
            </div>
          </div>
          <div class="svc-banner-features">
            <?php foreach ($svc['features'] as $feat): ?>
            <div class="feat-item">
              <i class="fa fa-check"></i> <?php echo $feat; ?>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Paket Cards -->
      <?php if (!empty($allProducts[$key])): ?>
      <div class="products-grid">
        <?php
        $cards = $allProducts[$key];
        $maxPrice = max(array_column($cards, 'price'));
        foreach ($cards as $p):
          $featured = ($p['price'] == $maxPrice && count($cards) > 1) ? false : ($p['ready_to_sell'] ? true : false);
          // Mark middle-price as featured for multi-card
          if (count($cards) >= 3) {
            $prices = array_column($cards, 'price');
            sort($prices);
            $midPrice = $prices[floor(count($prices)/2)];
            $featured = ($p['price'] == $midPrice);
          } elseif (count($cards) == 2) {
            $prices = array_column($cards, 'price');
            $featured = ($p['price'] == max($prices));
          }
        ?>
        <div class="product-card <?php echo $featured ? 'featured' : ''; ?> fade-up">
          <?php if ($featured): ?>
            <div class="badge-popular">⭐ Populer</div>
          <?php endif; ?>

          <div class="product-category-tag"><?php echo strtoupper($svc['tag']); ?></div>
          <?php if (!empty($p['speed'])): ?>
          <div class="product-speed"><i class="fa fa-bolt"></i> <?php echo htmlspecialchars($p['speed']); ?></div>
          <?php endif; ?>
          <div class="product-name"><?php echo htmlspecialchars($p['name']); ?></div>
          <div class="product-desc"><?php echo htmlspecialchars($p['description']); ?></div>

          <div class="product-price-row">
            <div class="product-price">Rp <?php echo number_format($p['price'], 0, ',', '.'); ?></div>
            <div class="product-price-unit">/<?php echo htmlspecialchars($p['period']); ?></div>
          </div>

          <?php if ($key === 'wifi' && !empty($p['id']) && $p['ready_to_sell']): ?>
          <a href="order/order_wifi.php?paket_id=<?= (int)$p['id'] ?>" class="product-cta">
            Pilih Paket Ini <i class="fa fa-arrow-right"></i>
          </a>
          <?php elseif ($key === 'hosting' && !empty($p['id']) && $p['ready_to_sell']): ?>
          <a href="order/order_hosting.php?paket_id=<?= (int)$p['id'] ?>" class="product-cta">
            Pilih Paket Ini <i class="fa fa-arrow-right"></i>
          </a>
          <?php else: ?>
          <a href="#contact" class="product-cta">
            <?php echo htmlspecialchars($svc['cta']); ?> <i class="fa fa-arrow-right"></i>
          </a>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div style="text-align:center;padding:40px;color:var(--text3);">
        <p>Paket untuk layanan ini sedang disiapkan. <a href="#contact" style="color:var(--accent)">Hubungi kami</a> untuk info lebih lanjut.</p>
      </div>
      <?php endif; ?>

    </div>
    <?php $first = false; endforeach; ?>

  </div>
</section>


<!-- ══ Why Perkasa Section ════════════════════════════════ -->
<section class="why-section section" id="why">
  <div class="container">
    <div class="why-inner">

      <div class="why-left fade-up">
        <div class="section-label">Mengapa Kami?</div>
        <h2 class="section-title">Mengapa Harus Pilih<br><em>Perkasa Solusindo?</em></h2>
        <p class="section-desc">Kami bukan sekadar vendor teknologi — kami adalah mitra digital jangka panjang yang berkomitmen pada kualitas, kecepatan, dan kepuasan klien.</p>

        <div class="why-testimonial" style="margin-top:36px;">
          <p>"Tim Perkasa sangat responsif dan profesional. Internet bisnis kami jarang sekali down, dan setiap ada masalah langsung ditangani hari itu juga."</p>
          <div class="testimonial-author">
            <div class="author-avatar">A</div>
            <div>
              <div class="author-name">Amirul F.</div>
              <div class="author-role">Pemilik, Semar Jagatech</div>
            </div>
          </div>
        </div>
      </div>

      <div class="why-right fade-up">
        <div class="why-card">
          <div class="why-card-icon">⚡</div>
          <div class="why-card-title">Instalasi Cepat</div>
          <div class="why-card-desc">Teknisi kami siap on-site dalam 24 jam. Tidak perlu menunggu berhari-hari untuk menikmati layanan.</div>
        </div>
        <div class="why-card">
          <div class="why-card-icon">🛡️</div>
          <div class="why-card-title">SLA 99% Uptime</div>
          <div class="why-card-desc">Kami berkomitmen dengan jaminan uptime 99% untuk layanan internet bisnis kami.</div>
        </div>
        <div class="why-card">
          <div class="why-card-icon">📞</div>
          <div class="why-card-title">Support 24/7</div>
          <div class="why-card-desc">Tim support kami siap membantu kapan saja — via WhatsApp, telepon, dan email.</div>
        </div>
        <div class="why-card">
          <div class="why-card-icon">💰</div>
          <div class="why-card-title">Harga Transparan</div>
          <div class="why-card-desc">Tidak ada biaya tersembunyi. Semua paket sudah termasuk instalasi dan garansi layanan.</div>
        </div>
        <div class="why-card">
          <div class="why-card-icon">🔧</div>
          <div class="why-card-title">Teknisi Bersertifikat</div>
          <div class="why-card-desc">Semua teknisi kami terlatih dan berpengalaman di bidang jaringan, komputer, dan keamanan.</div>
        </div>
        <div class="why-card">
          <div class="why-card-icon">🌍</div>
          <div class="why-card-title">Lokal & Tepercaya</div>
          <div class="why-card-desc">Berdomisili di Sidoarjo, kami memahami kebutuhan bisnis lokal Anda lebih dari siapapun.</div>
        </div>
        <div class="why-card highlight">
          <div style="font-size:36px;flex-shrink:0;">🏆</div>
          <div>
            <div class="why-card-title" style="font-size:15px;margin-bottom:8px;">Solusi Lengkap Satu Atap</div>
            <div class="why-card-desc">Dari internet, hosting, website, komputer, hingga CCTV — semua bisa ditangani oleh satu tim. Tidak perlu repot menghubungi banyak vendor berbeda.</div>
          </div>
        </div>
      </div>

    </div>
  </div>
</section>


<!-- ══ Projects ══════════════════════════════════════════════ -->
<section class="projects-section section" id="projects">
  <div class="container">
    <div class="projects-header fade-up">
      <div class="section-label">Portofolio</div>
      <h2 class="section-title">Hasil Kerja &amp; <em>Proyek</em> Kami</h2>
      <p class="section-desc">Beberapa proyek yang telah kami selesaikan dengan standar kualitas tertinggi.</p>
    </div>

    <div class="projects-grid">
      <?php
      $qProj = "SELECT * FROM projects ORDER BY id DESC";
      $rProj = $conn->query($qProj);
      if ($rProj && $rProj->num_rows > 0):
        while ($proj = $rProj->fetch_assoc()):
      ?>
      <div class="project-card fade-up">
        <div class="project-img-wrap">
          <img
            src="assets/images/<?php echo htmlspecialchars($proj['image']); ?>"
            alt="<?php echo htmlspecialchars($proj['title']); ?>"
            class="project-img"
            loading="lazy"
            onerror="this.src='assets/images/default-project.jpg'"
          >
          <div class="project-overlay">
            <a href="<?php echo htmlspecialchars($proj['link'] ?? '#'); ?>" target="_blank" class="project-link-btn">
              <i class="fa fa-external-link"></i>
            </a>
          </div>
        </div>
        <div class="project-info">
          <div class="project-title"><?php echo htmlspecialchars($proj['title']); ?></div>
        </div>
      </div>
      <?php
        endwhile;
      else:
      ?>
      <p style="color:var(--text3);grid-column:1/-1;text-align:center;padding:40px 0;">Belum ada proyek yang ditampilkan.</p>
      <?php endif; ?>
    </div>
  </div>
</section>


<!-- ══ Contact ══════════════════════════════════════════════ -->
<section class="contact-section section" id="contact">
  <div class="container">
    <div class="projects-header fade-up" style="margin-bottom:48px;">
      <div class="section-label">Hubungi Kami</div>
      <h2 class="section-title">Konsultasi <em>Gratis</em> Sekarang</h2>
      <p class="section-desc">Tim kami siap membantu Anda menemukan solusi teknologi yang tepat. Hubungi kami hari ini.</p>
    </div>

    <div class="contact-grid">
      <!-- Left Info -->
      <div class="fade-up">
        <div class="contact-info-title">Informasi Kontak</div>
        <div class="contact-info-items">
          <div class="contact-info-item">
            <div class="contact-info-icon"><i class="fa fa-phone"></i></div>
            <div>
              <div class="contact-info-label">Telepon / WhatsApp</div>
              <div class="contact-info-val">+62 812-4668-4665</div>
            </div>
          </div>
          <div class="contact-info-item">
            <div class="contact-info-icon"><i class="fa fa-envelope"></i></div>
            <div>
              <div class="contact-info-label">Email</div>
              <div class="contact-info-val">info-perkasa@perkasasolusindo.co.id</div>
            </div>
          </div>
          <div class="contact-info-item">
            <div class="contact-info-icon"><i class="fa fa-map-marker"></i></div>
            <div>
              <div class="contact-info-label">Alamat</div>
              <div class="contact-info-val">Jln. KedungRejo, Wedoroklurak, Candi, Jawa Timur 61271</div>
            </div>
          </div>
          <div class="contact-info-item">
            <div class="contact-info-icon"><i class="fa fa-clock-o"></i></div>
            <div>
              <div class="contact-info-label">Jam Operasional</div>
              <div class="contact-info-val">Senin – Sabtu, 08.00 – 17.00 WIB</div>
            </div>
          </div>
        </div>

        <div class="contact-map" style="margin-top:24px;">
          <iframe
            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3955.920641674412!2d112.7302933!3d-7.474015799999999!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2dd7e77e5aee4e33%3A0x85edc9909bf749eb!2sPT.TECH%20PERKASA%20SOLUSINDO!5e0!3m2!1sid!2sid!4v1780540038005!5m2!1sid!2sid"
            width="100%" height="220" frameborder="0" style="border:0;" allowfullscreen="" loading="lazy">
          </iframe>
        </div>
      </div>

      <!-- Right Form -->
      <div class="contact-form-wrap fade-up">
        <div class="contact-form-title">Kirim Pesan</div>
        <div class="contact-form-sub">Isi formulir di bawah dan kami akan segera menghubungi Anda.</div>

        <div id="formMsg" style="display:none;"></div>

        <div class="form-row">
          <div class="form-group">
            <label for="fname">Nama Depan</label>
            <input type="text" id="fname" name="fname" placeholder="Budi" required>
          </div>
          <div class="form-group">
            <label for="lname">Nama Belakang</label>
            <input type="text" id="lname" name="lname" placeholder="Santoso">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" placeholder="budi@perusahaan.co.id" required>
          </div>
          <div class="form-group">
            <label for="phone">No. HP / WhatsApp</label>
            <input type="tel" id="phone" name="phone" placeholder="08xxxxxxxxxx">
          </div>
        </div>
        <div class="form-group">
          <label for="service">Layanan yang Diminati</label>
          <select id="service" name="service">
            <option value="">— Pilih Layanan —</option>
            <option value="wifi">Provider Wifi Internet</option>
            <option value="hosting">Sewa Hosting</option>
            <option value="website">Pembuatan Website</option>
            <option value="komputer">Jual & Install Komputer</option>
            <option value="cctv">Pemasangan CCTV</option>
            <option value="other">Lainnya</option>
          </select>
        </div>
        <div class="form-group">
          <label for="message">Pesan</label>
          <textarea id="message" name="message" placeholder="Ceritakan kebutuhan Anda…"></textarea>
        </div>

        <button class="form-submit" id="formSubmit" type="button">
          <i class="fa fa-paper-plane"></i> Kirim Pesan
        </button>
      </div>
    </div>
  </div>
</section>


<!-- ══ Footer ═══════════════════════════════════════════════ -->
<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div>
        <div class="nav-logo">
          <img src="assets/images/CDR LOGO PERKASA Putih with border.png" alt="Logo" style="width:44px;">
          <div class="nav-logo-text">PERKASA <span>TECH</span><small>Solusindo</small></div>
        </div>
        <p class="footer-brand-desc">Solusi teknologi terpadu untuk bisnis dan rumahan Anda — internet, hosting, website, komputer, dan CCTV dalam satu tangan.</p>
        <div class="footer-socials">
          <a href="#" class="social-btn"><i class="fa fa-facebook"></i></a>
          <a href="#" class="social-btn"><i class="fa fa-instagram"></i></a>
          <a href="https://wa.me/6281246684665" class="social-btn"><i class="fa fa-whatsapp"></i></a>
          <a href="mailto:info-perkasa@perkasasolusindo.co.id" class="social-btn"><i class="fa fa-envelope"></i></a>
        </div>
      </div>

      <div>
        <div class="footer-col-title">Layanan</div>
        <div class="footer-links">
          <a href="#services" onclick="activateTab('wifi')">📡 Provider Wifi Internet</a>
          <a href="#services" onclick="activateTab('hosting')">☁️ Sewa Hosting</a>
          <a href="#services" onclick="activateTab('website')">💻 Pembuatan Website</a>
          <a href="#services" onclick="activateTab('komputer')">🖥️ Jual & Install Komputer</a>
          <a href="#services" onclick="activateTab('cctv')">📷 Pemasangan CCTV</a>
        </div>
      </div>

      <div>
        <div class="footer-col-title">Tautan</div>
        <div class="footer-links">
          <a href="#hero">Beranda</a>
          <a href="#services">Layanan</a>
          <a href="#projects">Portofolio</a>
          <a href="#why">Tentang Kami</a>
          <a href="#contact">Kontak</a>
          <a href="https://perkasasolusindo.co.id/login/login.php">Login Client Area</a>
        </div>
      </div>
    </div>

    <div class="footer-bottom">
      <span>Copyright © 2026 <span>PERKASA TECH SOLUSINDO</span>. All rights reserved.</span>
      <span>Dibuat dengan ❤️ di Sidoarjo, Jawa Timur</span>
    </div>
  </div>
</footer>

<!-- Toast -->
<div class="toast" id="toast">
  <span class="toast-icon" id="toastIcon">✅</span>
  <span class="toast-text" id="toastText"></span>
</div>

<!-- ══ Scripts ══════════════════════════════════════════════ -->
<script>
/* ── Preloader ──────────────────────────────────────────── */
window.addEventListener('load', () => {
  setTimeout(() => {
    document.getElementById('preloader').classList.add('hidden');
  }, 1400);
});

/* ── Sticky header + pre-header slide ──────────────────── */
const header = document.getElementById('siteHeader');
const preH   = document.querySelector('.pre-header');
const PRE_H  = 38; // px — matches .pre-header height in CSS

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

// Close when any link is clicked
navLinks?.querySelectorAll('a').forEach(a => {
  a.addEventListener('click', closeNav);
});

// Close on outside tap (overlay area)
document.addEventListener('click', e => {
  if (
    navLinks.classList.contains('open') &&
    !navLinks.contains(e.target) &&
    !navToggle.contains(e.target)
  ) closeNav();
});

/* ── Active nav link on scroll ──────────────────────────── */
const sections = document.querySelectorAll('section[id]');
const navAs    = document.querySelectorAll('.nav-links a');
const observer = new IntersectionObserver(entries => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      navAs.forEach(a => a.classList.remove('active'));
      const link = document.querySelector(`.nav-links a[href="#${e.target.id}"]`);
      if (link) link.classList.add('active');
    }
  });
}, { threshold: 0.4 });
sections.forEach(s => observer.observe(s));

/* ── Service Tabs ───────────────────────────────────────── */
function activateTab(key) {
  document.querySelectorAll('.svc-tab').forEach(t => {
    t.classList.toggle('active', t.dataset.tab === key);
    t.setAttribute('aria-selected', t.dataset.tab === key ? 'true' : 'false');
  });
  document.querySelectorAll('.svc-panel').forEach(p => {
    p.classList.toggle('active', p.id === 'panel-' + key);
  });
}
document.querySelectorAll('.svc-tab').forEach(tab => {
  tab.addEventListener('click', () => activateTab(tab.dataset.tab));
});

/* ── Scroll animations ──────────────────────────────────── */
const fadeEls = document.querySelectorAll('.fade-up');
const fadeObserver = new IntersectionObserver(entries => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      e.target.classList.add('visible');
      fadeObserver.unobserve(e.target);
    }
  });
}, { threshold: 0.12 });
fadeEls.forEach(el => fadeObserver.observe(el));

/* ── Counter animation ──────────────────────────────────── */
function animateCount(el, target, duration = 1500) {
  let start = 0;
  const step = timestamp => {
    if (!start) start = timestamp;
    const progress = Math.min((timestamp - start) / duration, 1);
    el.querySelector('span').textContent = Math.floor(progress * target);
    if (progress < 1) requestAnimationFrame(step);
    else el.querySelector('span').textContent = target;
  };
  requestAnimationFrame(step);
}
const countObs = new IntersectionObserver(entries => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      animateCount(e.target, parseInt(e.target.dataset.target));
      countObs.unobserve(e.target);
    }
  });
}, { threshold: 0.5 });
document.querySelectorAll('.count-up').forEach(el => countObs.observe(el));

/* ── Toast helper ───────────────────────────────────────── */
function showToast(icon, text) {
  const t = document.getElementById('toast');
  document.getElementById('toastIcon').textContent = icon;
  document.getElementById('toastText').textContent = text;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 4000);
}

/* ── Contact form ───────────────────────────────────────── */
document.getElementById('formSubmit')?.addEventListener('click', () => {
  const fname   = document.getElementById('fname').value.trim();
  const email   = document.getElementById('email').value.trim();
  const message = document.getElementById('message').value.trim();

  if (!fname || !email || !message) {
    showToast('⚠️', 'Mohon isi nama, email, dan pesan Anda.');
    return;
  }

  // Redirect to WhatsApp with prefilled message
  const svcEl = document.getElementById('service');
  const svcLabel = svcEl.options[svcEl.selectedIndex]?.text || '';
  const lname = document.getElementById('lname').value.trim();
  const phone = document.getElementById('phone').value.trim();

  const waMsg = encodeURIComponent(
    `Halo Perkasa Solusindo 👋\n\n` +
    `Nama: ${fname} ${lname}\n` +
    `Email: ${email}\n` +
    `No. HP: ${phone || '-'}\n` +
    `Layanan: ${svcLabel || '-'}\n\n` +
    `Pesan:\n${message}`
  );
  showToast('✅', 'Mengarahkan ke WhatsApp…');
  setTimeout(() => {
    window.open(`https://wa.me/6281246684665?text=${waMsg}`, '_blank');
  }, 600);
});

/* ── Smooth scroll for anchor links ─────────────────────── */
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
