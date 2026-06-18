<?php
// =====================================================
//  login_verify.php  –  /login/login_verify.php
//  Shown when a client (level 3) tries to log in but
//  email_verified = 0. User enters OTP sent to their
//  email. On success → email_verified = 1 → login.
// =====================================================

require_once __DIR__ . '/../auth_check.php';
redirectIfLoggedIn();

// Must have arrived here through login_process.php
if (empty($_SESSION['login_verify_pending_id'])) {
    header('Location: /login/login.php');
    exit;
}

$email     = $_SESSION['login_verify_pending_email'] ?? '';
$firstname = $_SESSION['login_verify_firstname']     ?? '';
$info      = $_SESSION['login_verify_info']          ?? '';
$errors    = $_SESSION['login_verify_errors']        ?? [];
unset($_SESSION['login_verify_errors'], $_SESSION['login_verify_info']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>PERKASA – Verifikasi Email</title>
  <link rel="stylesheet" href="style_newly.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@300;400;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <link rel="icon" type="image/png" href="/../assets/images/CDR LOGO PERKASA Putih with border.png">
  <style>
    /* ── Resend cooldown ── */
    .resend-wrap { text-align: center; margin-top: 14px; }
    .resend-wrap a, .resend-wrap span { font-size: 0.87rem; }
    .resend-wrap a { color: #ff4dce; font-weight: 600; text-decoration: none; }
    .resend-wrap a:hover { text-decoration: underline; }
    .resend-wrap .cooldown { color: rgba(255,255,255,0.35); }
    #countdown { font-weight: 700; color: #ff4dce; }

    /* ── Info notice ── */
    .verify-notice {
      background: rgba(255,77,206,0.07);
      border: 1px solid rgba(255,77,206,0.2);
      border-left: 4px solid #ff4dce;
      border-radius: 0 8px 8px 0;
      padding: 13px 16px;
      font-size: 0.87rem;
      color: rgba(255,255,255,0.7);
      line-height: 1.6;
      margin-bottom: 20px;
    }
    .verify-notice strong { color: #ff4dce; }
  </style>
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
        <p class="brand-tagline">Verifikasi email Anda untuk mengaktifkan akun.</p>
        <div class="brand-dots">
          <span class="dot active"></span>
          <span class="dot"></span>
          <span class="dot"></span>
        </div>
      </div>
    </div>

    <div class="signin-panel">
      <div class="aux-card">

        <div class="aux-icon">
          <i class="fas fa-envelope-circle-check"></i>
        </div>
        <h2 class="aux-title">Verifikasi Email</h2>
        <p class="aux-sub">Halo <strong><?= htmlspecialchars($firstname) ?></strong>! Sebelum melanjutkan, masukkan kode 6 digit yang dikirim ke:</p>

        <div class="email-badge">
          <i class="fas fa-envelope"></i>
          <?= htmlspecialchars($email) ?>
        </div>

        <div class="verify-notice">
          <strong><i class="fas fa-info-circle"></i> Akun belum diverifikasi</strong><br>
          Email Anda perlu diverifikasi satu kali sebelum bisa login. Setelah ini, Anda bisa login langsung tanpa verifikasi ulang.
        </div>

        <?php if ($info): ?>
        <div class="login-alert success">
          <i class="fas fa-circle-check"></i>
          <?= htmlspecialchars($info) ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
        <div class="login-alert error">
          <i class="fas fa-circle-exclamation" style="margin-top:2px"></i>
          <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
        </div>
        <?php endif; ?>

        <form method="post" action="/login/login_verify_process.php" id="verifyForm">
          <div class="otp-row">
            <input type="text" maxlength="1" class="otp-digit" inputmode="numeric" pattern="[0-9]" autocomplete="one-time-code"/>
            <input type="text" maxlength="1" class="otp-digit" inputmode="numeric" pattern="[0-9]"/>
            <input type="text" maxlength="1" class="otp-digit" inputmode="numeric" pattern="[0-9]"/>
            <input type="text" maxlength="1" class="otp-digit" inputmode="numeric" pattern="[0-9]"/>
            <input type="text" maxlength="1" class="otp-digit" inputmode="numeric" pattern="[0-9]"/>
            <input type="text" maxlength="1" class="otp-digit" inputmode="numeric" pattern="[0-9]"/>
          </div>
          <input type="hidden" name="verify_code" id="verify_code"/>

          <button type="submit" class="btn-signin" style="margin-top:8px">
            <i class="fas fa-circle-check"></i> Verifikasi &amp; Login
          </button>
        </form>

        <div class="resend-wrap">
          <span class="cooldown" id="cooldownMsg">Kirim ulang kode dalam <span id="countdown">60</span>s</span>
          <a href="/login/login_verify_resend.php" id="resendLink" style="display:none">
            <i class="fas fa-rotate-right"></i> Kirim Ulang Kode
          </a>
        </div>

        <p class="signup-text" style="margin-top:14px">
          <a href="/login/login.php"><i class="fas fa-arrow-left"></i> Kembali ke Login</a>
        </p>

      </div>
    </div>
  </div>

  <script>
    window.addEventListener('load', function () {
      setTimeout(function () {
        document.getElementById('page-loader').classList.add('hidden');
        document.getElementById('page-wrapper').classList.add('visible');
        digits[0].focus();
      }, 800);
    });

    const digits = document.querySelectorAll('.otp-digit');
    digits.forEach((inp, idx) => {
      inp.addEventListener('input', function () {
        this.value = this.value.replace(/[^0-9]/g, '');
        if (this.value && idx < digits.length - 1) digits[idx + 1].focus();
        assembleCode();
      });
      inp.addEventListener('keydown', function (e) {
        if (e.key === 'Backspace' && !this.value && idx > 0) {
          digits[idx - 1].focus();
          digits[idx - 1].value = '';
          assembleCode();
        }
      });
      inp.addEventListener('paste', function (e) {
        e.preventDefault();
        const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
        [...pasted].forEach((ch, i) => { if (digits[idx + i]) digits[idx + i].value = ch; });
        const next = idx + pasted.length;
        if (digits[next]) digits[next].focus();
        assembleCode();
      });
    });

    function assembleCode() {
      document.getElementById('verify_code').value = [...digits].map(d => d.value).join('');
    }

    document.getElementById('verifyForm').addEventListener('submit', function (e) {
      assembleCode();
      const code = document.getElementById('verify_code').value;
      if (code.length < 6) {
        e.preventDefault();
        alert('Masukkan 6 digit kode verifikasi.');
        digits[0].focus();
      }
    });

    // ── Resend countdown (60s) ──
    let secs = 60;
    const countdownEl = document.getElementById('countdown');
    const cooldownMsg = document.getElementById('cooldownMsg');
    const resendLink  = document.getElementById('resendLink');
    const timer = setInterval(function () {
      secs--;
      countdownEl.textContent = secs;
      if (secs <= 0) {
        clearInterval(timer);
        cooldownMsg.style.display = 'none';
        resendLink.style.display  = 'inline';
      }
    }, 1000);
  </script>
</body>
</html>
