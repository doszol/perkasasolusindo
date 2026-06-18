<?php
// =====================================================
//  reset_password.php  –  /login/reset_password.php
//  Step 2: User enters reset OTP + new password.
//  Only reachable if email_verified = 1.
// =====================================================

require_once __DIR__ . '/../auth_check.php';
redirectIfLoggedIn();

if (empty($_SESSION['forgot_pending_id'])) {
    header('Location: /login/lupa_password.php');
    exit;
}

// If somehow still in verify_email mode, push back
if (($_SESSION['forgot_mode'] ?? '') === 'verify_email') {
    header('Location: /login/verifikasi_email.php');
    exit;
}

$email  = $_SESSION['forgot_pending_email'] ?? '';
$info   = $_SESSION['forgot_success']       ?? '';
$errors = $_SESSION['reset_errors']         ?? [];
unset($_SESSION['reset_errors'], $_SESSION['forgot_success']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>PERKASA – Reset Kata Sandi</title>
  <link rel="stylesheet" href="style_newly.css"/>
  <link href="https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@300;400;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <link rel="icon" type="image/png" href="/../assets/images/CDR LOGO PERKASA Putih with border.png">
  <style>
    /* ── Step indicator bar ── */
    .step-bar {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0;
      margin-bottom: 28px;
    }
    .step-item {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 6px;
    }
    .step-circle {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      border: 2px solid rgba(255,255,255,0.15);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.85rem;
      font-weight: 700;
      color: rgba(255,255,255,0.3);
      background: rgba(255,255,255,0.05);
    }
    .step-circle.done   { background: #16a34a; border-color: #16a34a; color: #fff; }
    .step-circle.active { background: linear-gradient(135deg,#5a0060,#c0007a); border-color:#c0007a; color:#fff; box-shadow:0 0 12px rgba(192,0,122,0.5); }
    .step-label {
      font-size: 0.68rem;
      color: rgba(255,255,255,0.3);
      text-align: center;
      font-weight: 600;
      letter-spacing: 0.3px;
      max-width: 64px;
      line-height: 1.3;
    }
    .step-label.done   { color: #4ade80; }
    .step-label.active { color: #ff4dce; }
    .step-connector {
      width: 40px;
      height: 2px;
      background: rgba(255,255,255,0.1);
      margin: 0 4px;
      margin-bottom: 22px;
    }
    .step-connector.done { background: #16a34a; }

    /* ── Resend link ── */
    .resend-wrap { text-align: center; margin-top: 14px; }
    .resend-wrap a, .resend-wrap span { font-size: 0.87rem; }
    .resend-wrap a { color: #ff4dce; font-weight: 600; text-decoration: none; }
    .resend-wrap a:hover { text-decoration: underline; }
    .resend-wrap .cooldown { color: rgba(255,255,255,0.35); }
    #countdown { font-weight: 700; color: #ff4dce; }
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
        <p class="brand-tagline">Buat kata sandi baru yang kuat untuk akun Anda.</p>
        <div class="brand-dots">
          <span class="dot active"></span>
          <span class="dot"></span>
          <span class="dot"></span>
        </div>
      </div>
    </div>

    <div class="signin-panel">
      <div class="aux-card">

        <!-- Step indicator: Step 2 of 2 (or standalone if already verified) -->
        <div class="step-bar">
          <div class="step-item">
            <div class="step-circle done"><i class="fas fa-check"></i></div>
            <div class="step-label done">Verifikasi<br>Email</div>
          </div>
          <div class="step-connector done"></div>
          <div class="step-item">
            <div class="step-circle active"><i class="fas fa-key"></i></div>
            <div class="step-label active">Reset<br>Sandi</div>
          </div>
        </div>

        <div class="aux-icon">
          <i class="fas fa-shield-halved"></i>
        </div>
        <h2 class="aux-title">Reset Kata Sandi</h2>
        <p class="aux-sub">Kode reset telah dikirim ke:</p>
        <div class="email-badge">
          <i class="fas fa-envelope"></i>
          <?= htmlspecialchars($email) ?>
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

        <form method="post" action="/login/reset_process.php" id="resetForm">

          <!-- OTP boxes -->
          <div class="otp-row">
            <input type="text" maxlength="1" class="otp-digit" inputmode="numeric" pattern="[0-9]" autocomplete="one-time-code"/>
            <input type="text" maxlength="1" class="otp-digit" inputmode="numeric" pattern="[0-9]"/>
            <input type="text" maxlength="1" class="otp-digit" inputmode="numeric" pattern="[0-9]"/>
            <input type="text" maxlength="1" class="otp-digit" inputmode="numeric" pattern="[0-9]"/>
            <input type="text" maxlength="1" class="otp-digit" inputmode="numeric" pattern="[0-9]"/>
            <input type="text" maxlength="1" class="otp-digit" inputmode="numeric" pattern="[0-9]"/>
          </div>
          <input type="hidden" name="reset_code" id="reset_code"/>

          <hr class="form-divider"/>

          <div class="form-group" style="margin-bottom:18px">
            <label for="new_password">Kata Sandi Baru</label>
            <div class="input-wrap">
              <i class="fas fa-lock"></i>
              <input type="password" id="new_password" name="new_password"
                     placeholder="Minimal 8 karakter" required/>
              <button type="button" class="btn-eye" onclick="toggleEye('new_password','eye1')" tabindex="-1">
                <i class="fas fa-eye" id="eye1"></i>
              </button>
            </div>
          </div>

          <div class="form-group" style="margin-bottom:24px">
            <label for="new_password2">Konfirmasi Kata Sandi Baru</label>
            <div class="input-wrap">
              <i class="fas fa-lock"></i>
              <input type="password" id="new_password2" name="new_password2"
                     placeholder="Ulangi kata sandi baru" required/>
              <button type="button" class="btn-eye" onclick="toggleEye('new_password2','eye2')" tabindex="-1">
                <i class="fas fa-eye" id="eye2"></i>
              </button>
            </div>
          </div>

          <button type="submit" class="btn-signin">
            <i class="fas fa-key"></i> Simpan Kata Sandi Baru
          </button>
        </form>

        <div class="resend-wrap" style="margin-top:14px">
          <span class="cooldown" id="cooldownMsg">Kirim ulang kode dalam <span id="countdown">60</span>s</span>
          <a href="/login/lupa_password.php" id="resendLink" style="display:none">
            <i class="fas fa-rotate-right"></i> Kirim Ulang Kode
          </a>
        </div>

        <p class="signup-text" style="margin-top:10px">
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
      document.getElementById('reset_code').value = [...digits].map(d => d.value).join('');
    }

    document.getElementById('resetForm').addEventListener('submit', function (e) {
      assembleCode();
      const code = document.getElementById('reset_code').value;
      if (code.length < 6) {
        e.preventDefault();
        alert('Masukkan 6 digit kode reset.');
        digits[0].focus();
        return;
      }
      const p1 = document.getElementById('new_password').value;
      const p2 = document.getElementById('new_password2').value;
      if (p1 !== p2) {
        e.preventDefault();
        document.getElementById('new_password2').setCustomValidity('Kata sandi tidak cocok.');
        document.getElementById('new_password2').reportValidity();
        return;
      }
      document.getElementById('new_password2').setCustomValidity('');
    });

    function toggleEye(inputId, iconId) {
      const inp  = document.getElementById(inputId);
      const icon = document.getElementById(iconId);
      if (inp.type === 'password') {
        inp.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
      } else {
        inp.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
      }
    }

    // ── Resend countdown ──
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
