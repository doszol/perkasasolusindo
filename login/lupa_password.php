<?php
// =====================================================
//  lupa_password.php  -  /login/lupa_password.php
// =====================================================
require_once __DIR__ . '/../auth_check.php';
redirectIfLoggedIn();

$error   = $_SESSION['forgot_error']   ?? '';
$success = $_SESSION['forgot_success'] ?? '';
unset($_SESSION['forgot_error'], $_SESSION['forgot_success']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>PERKASA - Lupa Kata Sandi</title>
  <link rel="stylesheet" href="style_newly.css"/>
  <link rel="icon" type="image/png" href="/../assets/images/CDR LOGO PERKASA Putih with border.png">
  <link href="https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@300;400;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
</head>
<body>

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
        <p class="brand-tagline">Kami akan bantu Anda mengakses kembali akun Anda.</p>
        <div class="brand-dots">
          <span class="dot active"></span>
          <span class="dot"></span>
          <span class="dot"></span>
        </div>
      </div>
    </div>

    <div class="signin-panel">
      <div class="aux-card">

        <div class="aux-icon"><i class="fas fa-key"></i></div>
        <h2 class="aux-title">Lupa Kata Sandi?</h2>
        <p class="aux-sub">Masukkan email yang terdaftar. Kami akan mengirimkan kode verifikasi ke email Anda.</p>

        <?php if ($error): ?>
        <div class="login-alert error">
          <i class="fas fa-circle-exclamation"></i>
          <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="login-alert success">
          <i class="fas fa-circle-check"></i>
          <?= htmlspecialchars($success) ?>
        </div>
        <?php endif; ?>

        <form method="post" action="/login/forgot_process.php">
          <div class="form-group">
            <label for="email">Alamat Email</label>
            <div class="input-wrap">
              <i class="fa-regular fa-envelope"></i>
              <input type="email" id="email" name="email"
                     placeholder="Masukkan email terdaftar"
                     autocomplete="email" required/>
            </div>
          </div>
          <button type="submit" class="btn-signin" style="margin-top:8px">
            <i class="fas fa-paper-plane"></i> Kirim Kode Verifikasi
          </button>
        </form>

        <p class="signup-text" style="margin-top:18px">
          <a href="/login/login.php"><i class="fas fa-arrow-left"></i> Kembali ke Login</a>
        </p>

      </div>
    </div>
  </div>

  <script>
    window.addEventListener('load', function () {
      setTimeout(function () {
        var loader  = document.getElementById('page-loader');
        var wrapper = document.getElementById('page-wrapper');
        if (loader)  loader.classList.add('hidden');
        if (wrapper) wrapper.classList.add('visible');
      }, 800);
    });
  </script>
</body>
</html>