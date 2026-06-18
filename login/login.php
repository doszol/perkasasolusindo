<?php
// =====================================================
//  login.php  –  /login/login.php
// =====================================================
require_once __DIR__ . '/../auth_check.php';   // auth_check.php lives in root
redirectIfLoggedIn();                           // already logged in? → dashboard

$showSuccess  = !empty($_SESSION['reg_success']);
$showResetSuccess = !empty($_SESSION['reset_success']);
$loginSuccess = $_SESSION['login_success'] ?? '';
$loginError   = $_SESSION['login_error']   ?? '';
unset($_SESSION['reg_success'], $_SESSION['login_error'], $_SESSION['login_success'], $_SESSION['reset_success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>PERKASA – Login</title>
  <link rel="stylesheet" href="style_newly.css"/>
  <link rel="icon" type="image/png" href="/../assets/images/CDR LOGO PERKASA Putih with border.png">
  <link href="https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@300;400;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <style>
    /* ── Registration success popup ── */
    .popup-overlay{position:fixed;inset:0;background:rgba(0,0,0,.55);display:flex;align-items:center;justify-content:center;z-index:9999;opacity:0;animation:fadeInOverlay .35s ease forwards}
    @keyframes fadeInOverlay{to{opacity:1}}
    .popup-card{background:#fff;border-radius:16px;padding:40px 36px 32px;max-width:380px;width:90%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.2);transform:translateY(30px);animation:slideUpCard .4s ease .1s forwards}
    @keyframes slideUpCard{to{transform:translateY(0)}}
    .popup-icon{width:72px;height:72px;background:linear-gradient(135deg,#22c55e,#16a34a);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;animation:popIn .5s ease .3s both}
    @keyframes popIn{0%{transform:scale(0)}70%{transform:scale(1.15)}100%{transform:scale(1)}}
    .popup-icon i{font-size:2rem;color:#fff}
    .popup-card h3{font-size:1.25rem;font-weight:700;color:#111827;margin:0 0 8px}
    .popup-card p{font-size:.95rem;color:#6b7280;margin:0 0 28px}
    .popup-btn{display:inline-block;padding:11px 36px;background:linear-gradient(135deg,#22c55e,#16a34a);color:#fff;font-size:.95rem;font-weight:600;border:none;border-radius:8px;cursor:pointer;transition:opacity .2s}
    .popup-btn:hover{opacity:.88}
    .popup-overlay.hide{animation:fadeOutOverlay .3s ease forwards}
    @keyframes fadeOutOverlay{to{opacity:0;pointer-events:none}}
    /* ── Reset password success popup ── */
    .popup-reset-icon { background: linear-gradient(135deg,#5a0060,#c0007a) !important; }
  </style>
</head>
<body>

  <?php if ($showSuccess): ?>
  <div class="popup-overlay" id="successPopup">
    <div class="popup-card">
      <div class="popup-icon"><i class="fas fa-check"></i></div>
      <h3>Registrasi Berhasil!</h3>
      <p>Silahkan Login</p>
      <button class="popup-btn" onclick="closePopup('successPopup')">Login Sekarang</button>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($showResetSuccess): ?>
  <div class="popup-overlay" id="resetPopup">
    <div class="popup-card">
      <div class="popup-icon popup-reset-icon"><i class="fas fa-key"></i></div>
      <h3>Reset Kata Sandi Berhasil!</h3>
      <p>Silahkan Login Kembali</p>
      <button class="popup-btn" onclick="closePopup('resetPopup')" style="background:linear-gradient(135deg,#5a0060,#c0007a)">Login Sekarang</button>
    </div>
  </div>
  <?php endif; ?>

  <div id="page-loader">
    <div class="loader-ring"></div>
    <div class="loader-brand">PERKASA <span>SOLUSINDO</span></div>
    <div class="loader-dots"><i></i><i></i><i></i></div>
  </div>

  <div class="page-wrapper" id="page-wrapper">

    <div class="brand-panel brand-panel--login">
      <div class="brand-inner">
        <div class="brand-logo">
          <a href="../index.php" class="logo">
            <img src="../assets/images/CDR LOGO PERKASA Putih with border.png" alt="Perkasa Logo">
          </a>
          <span>PERKASA SOLUSINDO</span>
        </div>
        <p class="brand-tagline">A professional Technology Solution For Your Company</p>
        <div class="brand-dots">
          <span class="dot active"></span><span class="dot"></span><span class="dot"></span>
        </div>
      </div>
    </div>

    <div class="signin-panel">
      <div class="signin-card">

        <div class="card-header">
          <h2>Login</h2>
          <p>Login untuk melanjutkan.</p>
        </div>

        <?php if ($loginError): ?>
        <div class="login-alert error">
          <i class="fas fa-circle-exclamation"></i>
          <?= htmlspecialchars($loginError) ?>
        </div>
        <?php endif; ?>

        <?php if ($loginSuccess): ?>
        <div class="login-alert success">
          <i class="fas fa-circle-check"></i>
          <?= htmlspecialchars($loginSuccess) ?>
        </div>
        <?php endif; ?>

        <form method="post" action="/login/login_process.php">
          <div class="form-group">
            <label for="email">Alamat Email</label>
            <div class="input-wrap">
              <i class="fa-regular fa-envelope"></i>
              <input type="email" id="email" name="email"
                     placeholder="Masukkan email Anda" autocomplete="email" required/>
            </div>
          </div>

          <div class="form-group">
            <label for="password">Kata Sandi</label>
            <div class="input-wrap">
              <i class="fa-solid fa-lock"></i>
              <input type="password" id="password" name="password"
                     placeholder="Masukkan kata sandi" required/>
              <button type="button" class="btn-eye" onclick="toggleEye()" tabindex="-1">
                <i class="fas fa-eye" id="eyeIcon"></i>
              </button>
            </div>
          </div>

          <div class="form-options">
            <label class="remember-me">
              <input type="checkbox" id="remember" name="remember"/>
              <span class="checkmark"></span>
              Ingat Saya
            </label>
            <a href="/login/lupa_password.php" class="forgot-link">Lupa Kata Sandi?</a>
          </div>

          <button type="submit" class="btn-signin">Login</button>
        </form>

        <p class="signup-text">Belum punya akun? <a href="/login/registrasi.php">Buat Sekarang</a></p>
        <p class="signup-text"><a href="../index.php">Home</a></p>

      </div>
    </div>
  </div>

  <script>
    window.addEventListener('load', function () {
      setTimeout(function () {
        document.getElementById('page-loader').classList.add('hidden');
        document.getElementById('page-wrapper').classList.add('visible');
      }, 800);
    });

    function closePopup(id) {
      const o = document.getElementById(id);
      if (o) { o.classList.add('hide'); setTimeout(() => o.remove(), 320); }
    }
    ['successPopup','resetPopup'].forEach(function(id) {
      const o = document.getElementById(id);
      if (o) o.addEventListener('click', function(e) { if (e.target === o) closePopup(id); });
    });

    function toggleEye() {
      const inp  = document.getElementById('password');
      const icon = document.getElementById('eyeIcon');
      if (inp.type === 'password') {
        inp.type = 'text';
        icon.classList.replace('fa-eye','fa-eye-slash');
      } else {
        inp.type = 'password';
        icon.classList.replace('fa-eye-slash','fa-eye');
      }
    }
  </script>
</body>
</html>
