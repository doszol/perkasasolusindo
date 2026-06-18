<?php
require_once __DIR__ . '/../auth_check.php';  // auth_check.php lives in root
redirectIfLoggedIn();                          // already logged in? → their dashboard

$errors  = $_SESSION['reg_errors'] ?? [];
$old     = $_SESSION['reg_old']    ?? [];
unset($_SESSION['reg_errors'], $_SESSION['reg_old']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>PERKASA SOLUSINDO – Registrasi</title>
  <link rel="stylesheet" href="style_newly.css" />
  <link href="https://fonts.googleapis.com/css2?family=Source+Sans+Pro:wght@300;400;600;700&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <link rel="icon" type="image/png" href="/../assets/images/CDR LOGO PERKASA Putih with border.png">
  <style>
    /* ── Required asterisk ── */
    label .req { color: #e53e3e; margin-left: 2px; }
    /* ── Error / success banners ── */
    .alert { border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; font-size: .9rem; }
    .alert-danger  { background: #fef2f2; border: 1px solid #fca5a5; color: #b91c1c; }
    .alert ul { margin: 6px 0 0 18px; padding: 0; }
    /* ── TOS error highlight ── */
    .tos-error { color: #e53e3e; font-size: .82rem; margin-top: 4px; display: none; }
    .tos-error.show { display: block; }
    /* ── KTP optional badge ── */
    .optional-badge {
      font-size: .72rem; color: rgba(255,255,255,.35); font-weight: 400;
      background: rgba(255,255,255,.06); border-radius: 4px;
      padding: 1px 7px; margin-left: 6px; vertical-align: middle;
    }
    /* ── NIK digit hint ── */
    .nik-hint { font-size: .78rem; color: rgba(255,255,255,.3); margin-top: 4px; }
    /* ── Gender radio ── */
    .gender-row { display: flex; gap: 14px; margin-top: 4px; }
    .gender-opt { display: flex; align-items: center; gap: 7px; cursor: pointer; }
    .gender-opt input[type=radio] { accent-color: #ff4dce; width: 16px; height: 16px; cursor: pointer; }
    .gender-opt span { font-size: .9rem; color: rgba(255,255,255,.75); }

    /* ── KTP drop-zone ── */
    .ktp-dropzone {
      border: 2px dashed rgba(255,77,206,0.35);
      border-radius: 10px;
      background: rgba(255,77,206,0.04);
      padding: 28px 20px;
      text-align: center;
      cursor: pointer;
      transition: border-color .2s, background .2s;
      position: relative;
      overflow: hidden;
    }
    .ktp-dropzone:hover,
    .ktp-dropzone.drag-over {
      border-color: #ff4dce;
      background: rgba(255,77,206,0.09);
    }
    .ktp-icon { font-size: 2rem; color: rgba(255,77,206,0.55); margin-bottom: 8px; }
    .ktp-label { font-size: .92rem; color: rgba(255,255,255,.65); margin: 0 0 4px; }
    .ktp-hint  { font-size: .78rem; color: rgba(255,255,255,.3); margin: 0; }

    /* Preview */
    .ktp-preview-wrap { position: relative; display: inline-block; }
    .ktp-preview-wrap img {
      max-height: 160px;
      max-width: 100%;
      border-radius: 8px;
      border: 2px solid rgba(255,77,206,0.4);
      display: block;
      margin: 0 auto;
    }
    .ktp-remove {
      position: absolute; top: -8px; right: -8px;
      width: 26px; height: 26px;
      background: #e53e3e; color: #fff;
      border: none; border-radius: 50%; cursor: pointer;
      font-size: .75rem; display: flex; align-items: center; justify-content: center;
      box-shadow: 0 2px 6px rgba(0,0,0,.4);
      transition: background .15s;
    }
    .ktp-remove:hover { background: #c53030; }
    .ktp-filename {
      font-size: .78rem; color: rgba(255,255,255,.4);
      margin: 8px 0 0; word-break: break-all;
    }
  </style>
</head>
<body>

  <!-- ── Page Loader ── -->
  <div id="page-loader">
    <div class="loader-ring"></div>
    <div class="loader-brand">PERKASA <span>SOLUSINDO</span></div>
    <div class="loader-dots">
      <i></i><i></i><i></i>
    </div>
  </div>

  <div class="page-wrapper" id="page-wrapper">

    <!-- Left branding panel -->
    <div class="brand-panel brand-panel--signup">
      <div class="brand-inner">
        <div class="brand-logo">
          <a href="../index.php" class="logo">
            <img src="../assets/images/CDR LOGO PERKASA Putih with border.png" alt="Perkasa Logo">
          </a>
          <span>PERKASA SOLUSINDO</span>
        </div>
        <p class="brand-tagline">
          Create your account and start managing your services with us.
        </p>
      </div>
    </div>

    <!-- Right sign-up panel -->
    <div class="signup-panel">
      <div class="signup-scroll">

        <?php if (!empty($errors)): ?>
          <div class="alert alert-danger">
            <strong>Pendaftaran gagal:</strong>
            <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
          </div>
        <?php endif; ?>

        <form method="post" action="/login/register_process.php" name="orderfrm" id="frmCheckout" novalidate enctype="multipart/form-data">
          <!-- level is always 3 for public registration -->
          <input type="hidden" name="level" value="3"/>
          <input type="hidden" name="register" value="true"/>

          <div class="form-header">
            <h2>Buat Akun</h2>
            <p>Sudah punya akun? <a href="/login/login.php">Masuk di sini</a></p>
          </div>

          <!-- ── Personal Information ── -->
          <div class="section-card">
            <div class="section-title">
              <i class="fa-solid fa-user-circle"></i>
              Personal Information
            </div>
            <div class="form-grid col-2">
              <div class="form-group">
                <label for="inputFirstName">First Name <span class="req">*</span></label>
                <div class="input-wrap">
                  <i class="fas fa-user"></i>
                  <input type="text" name="firstname" id="inputFirstName"
                         placeholder="First Name" required autofocus
                         value="<?= htmlspecialchars($old['firstname'] ?? '') ?>"/>
                </div>
              </div>
              <div class="form-group">
                <label for="inputLastName">Last Name <span class="req">*</span></label>
                <div class="input-wrap">
                  <i class="fas fa-user"></i>
                  <input type="text" name="lastname" id="inputLastName"
                         placeholder="Last Name" required
                         value="<?= htmlspecialchars($old['lastname'] ?? '') ?>"/>
                </div>
              </div>
              <div class="form-group">
                <label for="inputEmail">Email Address <span class="req">*</span></label>
                <div class="input-wrap">
                  <i class="fas fa-envelope"></i>
                  <input type="email" name="email" id="inputEmail"
                         placeholder="Email Address" required
                         value="<?= htmlspecialchars($old['email'] ?? '') ?>"/>
                </div>
              </div>
              <div class="form-group">
                <label for="inputPhone">Phone Number <span class="req">*</span></label>
                <div class="input-wrap">
                  <i class="fas fa-phone"></i>
                  <input type="tel" name="phonenumber" id="inputPhone"
                         placeholder="Phone Number" required
                         value="<?= htmlspecialchars($old['phonenumber'] ?? '') ?>"/>
                </div>
              </div>
            </div>
          </div>

          <!-- ── Billing Address ── -->
          <div class="section-card">
            <div class="section-title">
              <i class="fa-solid fa-map-location-dot"></i>
              Billing Address
            </div>
            <div class="form-grid col-1">
              <div class="form-group">
                <label for="inputCompanyName">Company Name <span style="font-size:.75rem;color:rgba(255,255,255,.3);font-weight:400;">(Optional)</span></label>
                <div class="input-wrap">
                  <i class="fas fa-building"></i>
                  <input type="text" name="companyname" id="inputCompanyName"
                         placeholder="Company Name (Optional)"
                         value="<?= htmlspecialchars($old['companyname'] ?? '') ?>"/>
                </div>
              </div>
              <div class="form-group">
                <label for="inputAddress1">Street Address <span class="req">*</span></label>
                <div class="input-wrap">
                  <i class="far fa-building"></i>
                  <input type="text" name="address1" id="inputAddress1"
                         placeholder="Street Address" required
                         value="<?= htmlspecialchars($old['address1'] ?? '') ?>"/>
                </div>
              </div>
              <div class="form-group">
                <label for="inputAddress2">Street Address 2</label>
                <div class="input-wrap">
                  <i class="fas fa-map-marker-alt"></i>
                  <input type="text" name="address2" id="inputAddress2"
                         placeholder="Street Address 2"
                         value="<?= htmlspecialchars($old['address2'] ?? '') ?>"/>
                </div>
              </div>
            </div>
            <div class="form-grid col-3">
              <div class="form-group">
                <label for="inputCity">City <span class="req">*</span></label>
                <div class="input-wrap">
                  <i class="far fa-building"></i>
                  <input type="text" name="city" id="inputCity"
                         placeholder="City" required
                         value="<?= htmlspecialchars($old['city'] ?? '') ?>"/>
                </div>
              </div>
              <div class="form-group">
                <label for="state">State / Province <span class="req">*</span></label>
                <div class="input-wrap">
                  <i class="fas fa-map-signs"></i>
                  <input type="text" name="state" id="state"
                         placeholder="State" required
                         value="<?= htmlspecialchars($old['state'] ?? '') ?>"/>
                </div>
              </div>
              <div class="form-group">
                <label for="inputPostcode">Postcode <span class="req">*</span></label>
                <div class="input-wrap">
                  <i class="fas fa-certificate"></i>
                  <input type="text" name="postcode" id="inputPostcode"
                         placeholder="Postcode" required
                         value="<?= htmlspecialchars($old['postcode'] ?? '') ?>"/>
                </div>
              </div>
            </div>
            <div class="form-grid col-1">
              <div class="form-group">
                <label for="inputCountry">Country <span class="req">*</span></label>
                <div class="input-wrap select-wrap">
                  <i class="fas fa-globe"></i>
                  <?php $selCountry = $old['country'] ?? 'ID'; ?>
                  <select name="country" id="inputCountry" required>
                    <option value="AF"<?= $selCountry=='AF'?' selected':'' ?>>Afghanistan</option>
                    <option value="AX"<?= $selCountry=='AX'?' selected':'' ?>>Aland Islands</option>
                    <option value="AL"<?= $selCountry=='AL'?' selected':'' ?>>Albania</option>
                    <option value="DZ"<?= $selCountry=='DZ'?' selected':'' ?>>Algeria</option>
                    <option value="AS"<?= $selCountry=='AS'?' selected':'' ?>>American Samoa</option>
                    <option value="AD"<?= $selCountry=='AD'?' selected':'' ?>>Andorra</option>
                    <option value="AO"<?= $selCountry=='AO'?' selected':'' ?>>Angola</option>
                    <option value="AI"<?= $selCountry=='AI'?' selected':'' ?>>Anguilla</option>
                    <option value="AQ"<?= $selCountry=='AQ'?' selected':'' ?>>Antarctica</option>
                    <option value="AG"<?= $selCountry=='AG'?' selected':'' ?>>Antigua And Barbuda</option>
                    <option value="AR"<?= $selCountry=='AR'?' selected':'' ?>>Argentina</option>
                    <option value="AM"<?= $selCountry=='AM'?' selected':'' ?>>Armenia</option>
                    <option value="AW"<?= $selCountry=='AW'?' selected':'' ?>>Aruba</option>
                    <option value="AU"<?= $selCountry=='AU'?' selected':'' ?>>Australia</option>
                    <option value="AT"<?= $selCountry=='AT'?' selected':'' ?>>Austria</option>
                    <option value="AZ"<?= $selCountry=='AZ'?' selected':'' ?>>Azerbaijan</option>
                    <option value="BS"<?= $selCountry=='BS'?' selected':'' ?>>Bahamas</option>
                    <option value="BH"<?= $selCountry=='BH'?' selected':'' ?>>Bahrain</option>
                    <option value="BD"<?= $selCountry=='BD'?' selected':'' ?>>Bangladesh</option>
                    <option value="BB"<?= $selCountry=='BB'?' selected':'' ?>>Barbados</option>
                    <option value="BY"<?= $selCountry=='BY'?' selected':'' ?>>Belarus</option>
                    <option value="BE"<?= $selCountry=='BE'?' selected':'' ?>>Belgium</option>
                    <option value="BZ"<?= $selCountry=='BZ'?' selected':'' ?>>Belize</option>
                    <option value="BJ"<?= $selCountry=='BJ'?' selected':'' ?>>Benin</option>
                    <option value="BM"<?= $selCountry=='BM'?' selected':'' ?>>Bermuda</option>
                    <option value="BT"<?= $selCountry=='BT'?' selected':'' ?>>Bhutan</option>
                    <option value="BO"<?= $selCountry=='BO'?' selected':'' ?>>Bolivia</option>
                    <option value="BA"<?= $selCountry=='BA'?' selected':'' ?>>Bosnia And Herzegovina</option>
                    <option value="BW"<?= $selCountry=='BW'?' selected':'' ?>>Botswana</option>
                    <option value="BR"<?= $selCountry=='BR'?' selected':'' ?>>Brazil</option>
                    <option value="IO"<?= $selCountry=='IO'?' selected':'' ?>>British Indian Ocean Territory</option>
                    <option value="BN"<?= $selCountry=='BN'?' selected':'' ?>>Brunei Darussalam</option>
                    <option value="BG"<?= $selCountry=='BG'?' selected':'' ?>>Bulgaria</option>
                    <option value="BF"<?= $selCountry=='BF'?' selected':'' ?>>Burkina Faso</option>
                    <option value="BI"<?= $selCountry=='BI'?' selected':'' ?>>Burundi</option>
                    <option value="KH"<?= $selCountry=='KH'?' selected':'' ?>>Cambodia</option>
                    <option value="CM"<?= $selCountry=='CM'?' selected':'' ?>>Cameroon</option>
                    <option value="CA"<?= $selCountry=='CA'?' selected':'' ?>>Canada</option>
                    <option value="IC"<?= $selCountry=='IC'?' selected':'' ?>>Canary Islands</option>
                    <option value="CV"<?= $selCountry=='CV'?' selected':'' ?>>Cape Verde</option>
                    <option value="KY"<?= $selCountry=='KY'?' selected':'' ?>>Cayman Islands</option>
                    <option value="CF"<?= $selCountry=='CF'?' selected':'' ?>>Central African Republic</option>
                    <option value="TD"<?= $selCountry=='TD'?' selected':'' ?>>Chad</option>
                    <option value="CL"<?= $selCountry=='CL'?' selected':'' ?>>Chile</option>
                    <option value="CN"<?= $selCountry=='CN'?' selected':'' ?>>China</option>
                    <option value="CX"<?= $selCountry=='CX'?' selected':'' ?>>Christmas Island</option>
                    <option value="CC"<?= $selCountry=='CC'?' selected':'' ?>>Cocos (Keeling) Islands</option>
                    <option value="CO"<?= $selCountry=='CO'?' selected':'' ?>>Colombia</option>
                    <option value="KM"<?= $selCountry=='KM'?' selected':'' ?>>Comoros</option>
                    <option value="CG"<?= $selCountry=='CG'?' selected':'' ?>>Congo</option>
                    <option value="CD"<?= $selCountry=='CD'?' selected':'' ?>>Congo, Democratic Republic</option>
                    <option value="CK"<?= $selCountry=='CK'?' selected':'' ?>>Cook Islands</option>
                    <option value="CR"<?= $selCountry=='CR'?' selected':'' ?>>Costa Rica</option>
                    <option value="CI"<?= $selCountry=='CI'?' selected':'' ?>>Cote D'Ivoire</option>
                    <option value="HR"<?= $selCountry=='HR'?' selected':'' ?>>Croatia</option>
                    <option value="CU"<?= $selCountry=='CU'?' selected':'' ?>>Cuba</option>
                    <option value="CW"<?= $selCountry=='CW'?' selected':'' ?>>Curacao</option>
                    <option value="CY"<?= $selCountry=='CY'?' selected':'' ?>>Cyprus</option>
                    <option value="CZ"<?= $selCountry=='CZ'?' selected':'' ?>>Czech Republic</option>
                    <option value="DK"<?= $selCountry=='DK'?' selected':'' ?>>Denmark</option>
                    <option value="DJ"<?= $selCountry=='DJ'?' selected':'' ?>>Djibouti</option>
                    <option value="DM"<?= $selCountry=='DM'?' selected':'' ?>>Dominica</option>
                    <option value="DO"<?= $selCountry=='DO'?' selected':'' ?>>Dominican Republic</option>
                    <option value="EC"<?= $selCountry=='EC'?' selected':'' ?>>Ecuador</option>
                    <option value="EG"<?= $selCountry=='EG'?' selected':'' ?>>Egypt</option>
                    <option value="SV"<?= $selCountry=='SV'?' selected':'' ?>>El Salvador</option>
                    <option value="GQ"<?= $selCountry=='GQ'?' selected':'' ?>>Equatorial Guinea</option>
                    <option value="ER"<?= $selCountry=='ER'?' selected':'' ?>>Eritrea</option>
                    <option value="EE"<?= $selCountry=='EE'?' selected':'' ?>>Estonia</option>
                    <option value="ET"<?= $selCountry=='ET'?' selected':'' ?>>Ethiopia</option>
                    <option value="FK"<?= $selCountry=='FK'?' selected':'' ?>>Falkland Islands (Malvinas)</option>
                    <option value="FO"<?= $selCountry=='FO'?' selected':'' ?>>Faroe Islands</option>
                    <option value="FJ"<?= $selCountry=='FJ'?' selected':'' ?>>Fiji</option>
                    <option value="FI"<?= $selCountry=='FI'?' selected':'' ?>>Finland</option>
                    <option value="FR"<?= $selCountry=='FR'?' selected':'' ?>>France</option>
                    <option value="GF"<?= $selCountry=='GF'?' selected':'' ?>>French Guiana</option>
                    <option value="PF"<?= $selCountry=='PF'?' selected':'' ?>>French Polynesia</option>
                    <option value="TF"<?= $selCountry=='TF'?' selected':'' ?>>French Southern Territories</option>
                    <option value="GA"<?= $selCountry=='GA'?' selected':'' ?>>Gabon</option>
                    <option value="GM"<?= $selCountry=='GM'?' selected':'' ?>>Gambia</option>
                    <option value="GE"<?= $selCountry=='GE'?' selected':'' ?>>Georgia</option>
                    <option value="DE"<?= $selCountry=='DE'?' selected':'' ?>>Germany</option>
                    <option value="GH"<?= $selCountry=='GH'?' selected':'' ?>>Ghana</option>
                    <option value="GI"<?= $selCountry=='GI'?' selected':'' ?>>Gibraltar</option>
                    <option value="GR"<?= $selCountry=='GR'?' selected':'' ?>>Greece</option>
                    <option value="GL"<?= $selCountry=='GL'?' selected':'' ?>>Greenland</option>
                    <option value="GD"<?= $selCountry=='GD'?' selected':'' ?>>Grenada</option>
                    <option value="GP"<?= $selCountry=='GP'?' selected':'' ?>>Guadeloupe</option>
                    <option value="GU"<?= $selCountry=='GU'?' selected':'' ?>>Guam</option>
                    <option value="GT"<?= $selCountry=='GT'?' selected':'' ?>>Guatemala</option>
                    <option value="GG"<?= $selCountry=='GG'?' selected':'' ?>>Guernsey</option>
                    <option value="GN"<?= $selCountry=='GN'?' selected':'' ?>>Guinea</option>
                    <option value="GW"<?= $selCountry=='GW'?' selected':'' ?>>Guinea-Bissau</option>
                    <option value="GY"<?= $selCountry=='GY'?' selected':'' ?>>Guyana</option>
                    <option value="HT"<?= $selCountry=='HT'?' selected':'' ?>>Haiti</option>
                    <option value="HM"<?= $selCountry=='HM'?' selected':'' ?>>Heard Island &amp; Mcdonald Islands</option>
                    <option value="VA"<?= $selCountry=='VA'?' selected':'' ?>>Holy See (Vatican City State)</option>
                    <option value="HN"<?= $selCountry=='HN'?' selected':'' ?>>Honduras</option>
                    <option value="HK"<?= $selCountry=='HK'?' selected':'' ?>>Hong Kong</option>
                    <option value="HU"<?= $selCountry=='HU'?' selected':'' ?>>Hungary</option>
                    <option value="IS"<?= $selCountry=='IS'?' selected':'' ?>>Iceland</option>
                    <option value="IN"<?= $selCountry=='IN'?' selected':'' ?>>India</option>
                    <option value="ID"<?= $selCountry=='ID'?' selected':'' ?>>Indonesia</option>
                    <option value="IR"<?= $selCountry=='IR'?' selected':'' ?>>Iran</option>
                    <option value="IQ"<?= $selCountry=='IQ'?' selected':'' ?>>Iraq</option>
                    <option value="IE"<?= $selCountry=='IE'?' selected':'' ?>>Ireland</option>
                    <option value="IM"<?= $selCountry=='IM'?' selected':'' ?>>Isle Of Man</option>
                    <option value="IL"<?= $selCountry=='IL'?' selected':'' ?>>Israel</option>
                    <option value="IT"<?= $selCountry=='IT'?' selected':'' ?>>Italy</option>
                    <option value="JM"<?= $selCountry=='JM'?' selected':'' ?>>Jamaica</option>
                    <option value="JP"<?= $selCountry=='JP'?' selected':'' ?>>Japan</option>
                    <option value="JE"<?= $selCountry=='JE'?' selected':'' ?>>Jersey</option>
                    <option value="JO"<?= $selCountry=='JO'?' selected':'' ?>>Jordan</option>
                    <option value="KZ"<?= $selCountry=='KZ'?' selected':'' ?>>Kazakhstan</option>
                    <option value="KE"<?= $selCountry=='KE'?' selected':'' ?>>Kenya</option>
                    <option value="KI"<?= $selCountry=='KI'?' selected':'' ?>>Kiribati</option>
                    <option value="KR"<?= $selCountry=='KR'?' selected':'' ?>>Korea</option>
                    <option value="KW"<?= $selCountry=='KW'?' selected':'' ?>>Kuwait</option>
                    <option value="KG"<?= $selCountry=='KG'?' selected':'' ?>>Kyrgyzstan</option>
                    <option value="LA"<?= $selCountry=='LA'?' selected':'' ?>>Lao People's Democratic Republic</option>
                    <option value="LV"<?= $selCountry=='LV'?' selected':'' ?>>Latvia</option>
                    <option value="LB"<?= $selCountry=='LB'?' selected':'' ?>>Lebanon</option>
                    <option value="LS"<?= $selCountry=='LS'?' selected':'' ?>>Lesotho</option>
                    <option value="LR"<?= $selCountry=='LR'?' selected':'' ?>>Liberia</option>
                    <option value="LY"<?= $selCountry=='LY'?' selected':'' ?>>Libya</option>
                    <option value="LI"<?= $selCountry=='LI'?' selected':'' ?>>Liechtenstein</option>
                    <option value="LT"<?= $selCountry=='LT'?' selected':'' ?>>Lithuania</option>
                    <option value="LU"<?= $selCountry=='LU'?' selected':'' ?>>Luxembourg</option>
                    <option value="MO"<?= $selCountry=='MO'?' selected':'' ?>>Macao</option>
                    <option value="MK"<?= $selCountry=='MK'?' selected':'' ?>>Macedonia</option>
                    <option value="MG"<?= $selCountry=='MG'?' selected':'' ?>>Madagascar</option>
                    <option value="MW"<?= $selCountry=='MW'?' selected':'' ?>>Malawi</option>
                    <option value="MY"<?= $selCountry=='MY'?' selected':'' ?>>Malaysia</option>
                    <option value="MV"<?= $selCountry=='MV'?' selected':'' ?>>Maldives</option>
                    <option value="ML"<?= $selCountry=='ML'?' selected':'' ?>>Mali</option>
                    <option value="MT"<?= $selCountry=='MT'?' selected':'' ?>>Malta</option>
                    <option value="MH"<?= $selCountry=='MH'?' selected':'' ?>>Marshall Islands</option>
                    <option value="MQ"<?= $selCountry=='MQ'?' selected':'' ?>>Martinique</option>
                    <option value="MR"<?= $selCountry=='MR'?' selected':'' ?>>Mauritania</option>
                    <option value="MU"<?= $selCountry=='MU'?' selected':'' ?>>Mauritius</option>
                    <option value="YT"<?= $selCountry=='YT'?' selected':'' ?>>Mayotte</option>
                    <option value="MX"<?= $selCountry=='MX'?' selected':'' ?>>Mexico</option>
                    <option value="FM"<?= $selCountry=='FM'?' selected':'' ?>>Micronesia</option>
                    <option value="MD"<?= $selCountry=='MD'?' selected':'' ?>>Moldova</option>
                    <option value="MC"<?= $selCountry=='MC'?' selected':'' ?>>Monaco</option>
                    <option value="MN"<?= $selCountry=='MN'?' selected':'' ?>>Mongolia</option>
                    <option value="ME"<?= $selCountry=='ME'?' selected':'' ?>>Montenegro</option>
                    <option value="MS"<?= $selCountry=='MS'?' selected':'' ?>>Montserrat</option>
                    <option value="MA"<?= $selCountry=='MA'?' selected':'' ?>>Morocco</option>
                    <option value="MZ"<?= $selCountry=='MZ'?' selected':'' ?>>Mozambique</option>
                    <option value="MM"<?= $selCountry=='MM'?' selected':'' ?>>Myanmar</option>
                    <option value="NA"<?= $selCountry=='NA'?' selected':'' ?>>Namibia</option>
                    <option value="NR"<?= $selCountry=='NR'?' selected':'' ?>>Nauru</option>
                    <option value="NP"<?= $selCountry=='NP'?' selected':'' ?>>Nepal</option>
                    <option value="NL"<?= $selCountry=='NL'?' selected':'' ?>>Netherlands</option>
                    <option value="AN"<?= $selCountry=='AN'?' selected':'' ?>>Netherlands Antilles</option>
                    <option value="NC"<?= $selCountry=='NC'?' selected':'' ?>>New Caledonia</option>
                    <option value="NZ"<?= $selCountry=='NZ'?' selected':'' ?>>New Zealand</option>
                    <option value="NI"<?= $selCountry=='NI'?' selected':'' ?>>Nicaragua</option>
                    <option value="NE"<?= $selCountry=='NE'?' selected':'' ?>>Niger</option>
                    <option value="NG"<?= $selCountry=='NG'?' selected':'' ?>>Nigeria</option>
                    <option value="NU"<?= $selCountry=='NU'?' selected':'' ?>>Niue</option>
                    <option value="NF"<?= $selCountry=='NF'?' selected':'' ?>>Norfolk Island</option>
                    <option value="MP"<?= $selCountry=='MP'?' selected':'' ?>>Northern Mariana Islands</option>
                    <option value="NO"<?= $selCountry=='NO'?' selected':'' ?>>Norway</option>
                    <option value="OM"<?= $selCountry=='OM'?' selected':'' ?>>Oman</option>
                    <option value="PK"<?= $selCountry=='PK'?' selected':'' ?>>Pakistan</option>
                    <option value="PW"<?= $selCountry=='PW'?' selected':'' ?>>Palau</option>
                    <option value="PS"<?= $selCountry=='PS'?' selected':'' ?>>Palestinian Territory, Occupied</option>
                    <option value="PA"<?= $selCountry=='PA'?' selected':'' ?>>Panama</option>
                    <option value="PG"<?= $selCountry=='PG'?' selected':'' ?>>Papua New Guinea</option>
                    <option value="PY"<?= $selCountry=='PY'?' selected':'' ?>>Paraguay</option>
                    <option value="PE"<?= $selCountry=='PE'?' selected':'' ?>>Peru</option>
                    <option value="PH"<?= $selCountry=='PH'?' selected':'' ?>>Philippines</option>
                    <option value="PN"<?= $selCountry=='PN'?' selected':'' ?>>Pitcairn</option>
                    <option value="PL"<?= $selCountry=='PL'?' selected':'' ?>>Poland</option>
                    <option value="PT"<?= $selCountry=='PT'?' selected':'' ?>>Portugal</option>
                    <option value="PR"<?= $selCountry=='PR'?' selected':'' ?>>Puerto Rico</option>
                    <option value="QA"<?= $selCountry=='QA'?' selected':'' ?>>Qatar</option>
                    <option value="RE"<?= $selCountry=='RE'?' selected':'' ?>>Reunion</option>
                    <option value="RO"<?= $selCountry=='RO'?' selected':'' ?>>Romania</option>
                    <option value="RU"<?= $selCountry=='RU'?' selected':'' ?>>Russia</option>
                    <option value="RW"<?= $selCountry=='RW'?' selected':'' ?>>Rwanda</option>
                    <option value="BL"<?= $selCountry=='BL'?' selected':'' ?>>Saint Barthelemy</option>
                    <option value="SH"<?= $selCountry=='SH'?' selected':'' ?>>Saint Helena</option>
                    <option value="KN"<?= $selCountry=='KN'?' selected':'' ?>>Saint Kitts And Nevis</option>
                    <option value="LC"<?= $selCountry=='LC'?' selected':'' ?>>Saint Lucia</option>
                    <option value="MF"<?= $selCountry=='MF'?' selected':'' ?>>Saint Martin</option>
                    <option value="PM"<?= $selCountry=='PM'?' selected':'' ?>>Saint Pierre And Miquelon</option>
                    <option value="VC"<?= $selCountry=='VC'?' selected':'' ?>>Saint Vincent And Grenadines</option>
                    <option value="WS"<?= $selCountry=='WS'?' selected':'' ?>>Samoa</option>
                    <option value="SM"<?= $selCountry=='SM'?' selected':'' ?>>San Marino</option>
                    <option value="ST"<?= $selCountry=='ST'?' selected':'' ?>>Sao Tome And Principe</option>
                    <option value="SA"<?= $selCountry=='SA'?' selected':'' ?>>Saudi Arabia</option>
                    <option value="SN"<?= $selCountry=='SN'?' selected':'' ?>>Senegal</option>
                    <option value="RS"<?= $selCountry=='RS'?' selected':'' ?>>Serbia</option>
                    <option value="SC"<?= $selCountry=='SC'?' selected':'' ?>>Seychelles</option>
                    <option value="SL"<?= $selCountry=='SL'?' selected':'' ?>>Sierra Leone</option>
                    <option value="SG"<?= $selCountry=='SG'?' selected':'' ?>>Singapore</option>
                    <option value="SK"<?= $selCountry=='SK'?' selected':'' ?>>Slovakia</option>
                    <option value="SI"<?= $selCountry=='SI'?' selected':'' ?>>Slovenia</option>
                    <option value="SB"<?= $selCountry=='SB'?' selected':'' ?>>Solomon Islands</option>
                    <option value="SO"<?= $selCountry=='SO'?' selected':'' ?>>Somalia</option>
                    <option value="ZA"<?= $selCountry=='ZA'?' selected':'' ?>>South Africa</option>
                    <option value="GS"<?= $selCountry=='GS'?' selected':'' ?>>South Georgia And Sandwich Isl.</option>
                    <option value="ES"<?= $selCountry=='ES'?' selected':'' ?>>Spain</option>
                    <option value="LK"<?= $selCountry=='LK'?' selected':'' ?>>Sri Lanka</option>
                    <option value="SD"<?= $selCountry=='SD'?' selected':'' ?>>Sudan</option>
                    <option value="SS"<?= $selCountry=='SS'?' selected':'' ?>>South Sudan</option>
                    <option value="SR"<?= $selCountry=='SR'?' selected':'' ?>>Suriname</option>
                    <option value="SJ"<?= $selCountry=='SJ'?' selected':'' ?>>Svalbard And Jan Mayen</option>
                    <option value="SZ"<?= $selCountry=='SZ'?' selected':'' ?>>Swaziland</option>
                    <option value="SE"<?= $selCountry=='SE'?' selected':'' ?>>Sweden</option>
                    <option value="CH"<?= $selCountry=='CH'?' selected':'' ?>>Switzerland</option>
                    <option value="SY"<?= $selCountry=='SY'?' selected':'' ?>>Syrian Arab Republic</option>
                    <option value="TW"<?= $selCountry=='TW'?' selected':'' ?>>Taiwan</option>
                    <option value="TJ"<?= $selCountry=='TJ'?' selected':'' ?>>Tajikistan</option>
                    <option value="TZ"<?= $selCountry=='TZ'?' selected':'' ?>>Tanzania</option>
                    <option value="TH"<?= $selCountry=='TH'?' selected':'' ?>>Thailand</option>
                    <option value="TL"<?= $selCountry=='TL'?' selected':'' ?>>Timor-Leste</option>
                    <option value="TG"<?= $selCountry=='TG'?' selected':'' ?>>Togo</option>
                    <option value="TK"<?= $selCountry=='TK'?' selected':'' ?>>Tokelau</option>
                    <option value="TO"<?= $selCountry=='TO'?' selected':'' ?>>Tonga</option>
                    <option value="TT"<?= $selCountry=='TT'?' selected':'' ?>>Trinidad And Tobago</option>
                    <option value="TN"<?= $selCountry=='TN'?' selected':'' ?>>Tunisia</option>
                    <option value="TR"<?= $selCountry=='TR'?' selected':'' ?>>Turkey</option>
                    <option value="TM"<?= $selCountry=='TM'?' selected':'' ?>>Turkmenistan</option>
                    <option value="TC"<?= $selCountry=='TC'?' selected':'' ?>>Turks And Caicos Islands</option>
                    <option value="TV"<?= $selCountry=='TV'?' selected':'' ?>>Tuvalu</option>
                    <option value="UG"<?= $selCountry=='UG'?' selected':'' ?>>Uganda</option>
                    <option value="UA"<?= $selCountry=='UA'?' selected':'' ?>>Ukraine</option>
                    <option value="AE"<?= $selCountry=='AE'?' selected':'' ?>>United Arab Emirates</option>
                    <option value="GB"<?= $selCountry=='GB'?' selected':'' ?>>United Kingdom</option>
                    <option value="US"<?= $selCountry=='US'?' selected':'' ?>>United States</option>
                    <option value="UM"<?= $selCountry=='UM'?' selected':'' ?>>United States Outlying Islands</option>
                    <option value="UY"<?= $selCountry=='UY'?' selected':'' ?>>Uruguay</option>
                    <option value="UZ"<?= $selCountry=='UZ'?' selected':'' ?>>Uzbekistan</option>
                    <option value="VU"<?= $selCountry=='VU'?' selected':'' ?>>Vanuatu</option>
                    <option value="VE"<?= $selCountry=='VE'?' selected':'' ?>>Venezuela</option>
                    <option value="VN"<?= $selCountry=='VN'?' selected':'' ?>>Viet Nam</option>
                    <option value="VG"<?= $selCountry=='VG'?' selected':'' ?>>Virgin Islands, British</option>
                    <option value="VI"<?= $selCountry=='VI'?' selected':'' ?>>Virgin Islands, U.S.</option>
                    <option value="WF"<?= $selCountry=='WF'?' selected':'' ?>>Wallis And Futuna</option>
                    <option value="EH"<?= $selCountry=='EH'?' selected':'' ?>>Western Sahara</option>
                    <option value="YE"<?= $selCountry=='YE'?' selected':'' ?>>Yemen</option>
                    <option value="ZM"<?= $selCountry=='ZM'?' selected':'' ?>>Zambia</option>
                    <option value="ZW"<?= $selCountry=='ZW'?' selected':'' ?>>Zimbabwe</option>
                  </select>
                  <i class="fas fa-chevron-down select-arrow"></i>
                </div>
              </div>
            </div>
          </div>

          <!-- ── Informasi Tambahan ── -->
          <div class="section-card">
            <div class="section-title">
              <i class="fa-solid fa-circle-info"></i>
              Informasi Tambahan
              <small>(required fields are marked with *)</small>
            </div>
            <div class="form-grid col-2">
              <div class="form-group">
                <label for="inputCurrency">Currency <span class="req">*</span></label>
                <div class="input-wrap select-wrap">
                  <i class="far fa-money-bill-alt"></i>
                  <?php $selCur = $old['currency'] ?? '1'; ?>
                  <select id="inputCurrency" name="currency" required>
                    <option value="1"<?= $selCur=='1'?' selected':'' ?>>IDR</option>
                    <option value="3"<?= $selCur=='3'?' selected':'' ?>>USD</option>
                  </select>
                  <i class="fas fa-chevron-down select-arrow"></i>
                </div>
              </div>
            </div>
          </div>

          <!-- ── Data Identitas (KTP) ── -->
          <div class="section-card">
            <div class="section-title">
              <i class="fa-solid fa-id-card"></i>
              Data Identitas
              <span class="optional-badge">Opsional — bisa diisi nanti</span>
            </div>
            <div class="form-grid col-2">
              <div class="form-group" style="grid-column: 1 / -1;">
                <label for="inputNIK">NIK (Nomor Induk Kependudukan)</label>
                <div class="input-wrap">
                  <i class="fas fa-fingerprint"></i>
                  <input type="text" name="nik" id="inputNIK"
                         placeholder="16 digit NIK sesuai KTP"
                         maxlength="16" inputmode="numeric"
                         pattern="[0-9]{16}"
                         value="<?= htmlspecialchars($old['nik'] ?? '') ?>"/>
                </div>
                <p class="nik-hint"><i class="fas fa-circle-info"></i> Harus 16 digit angka sesuai KTP Anda.</p>
              </div>
              <div class="form-group">
                <label for="inputTempatLahir">Tempat Lahir</label>
                <div class="input-wrap">
                  <i class="fas fa-map-pin"></i>
                  <input type="text" name="tempat_lahir" id="inputTempatLahir"
                         placeholder="Kota sesuai KTP"
                         value="<?= htmlspecialchars($old['tempat_lahir'] ?? '') ?>"/>
                </div>
              </div>
              <div class="form-group">
                <label for="inputTanggalLahir">Tanggal Lahir</label>
                <div class="input-wrap">
                  <i class="fas fa-calendar-days"></i>
                  <input type="date" name="tanggal_lahir" id="inputTanggalLahir"
                         max="<?= date('Y-m-d') ?>"
                         value="<?= htmlspecialchars($old['tanggal_lahir'] ?? '') ?>"/>
                </div>
              </div>
              <div class="form-group" style="grid-column: 1 / -1;">
                <label>Jenis Kelamin</label>
                <div class="gender-row">
                  <label class="gender-opt">
                    <input type="radio" name="jenis_kelamin" value="L"
                           <?= (($old['jenis_kelamin'] ?? '') === 'L') ? 'checked' : '' ?>/>
                    <span><i class="fas fa-mars"></i> Laki-laki</span>
                  </label>
                  <label class="gender-opt">
                    <input type="radio" name="jenis_kelamin" value="P"
                           <?= (($old['jenis_kelamin'] ?? '') === 'P') ? 'checked' : '' ?>/>
                    <span><i class="fas fa-venus"></i> Perempuan</span>
                  </label>
                </div>
              </div>

              <!-- ── Foto KTP upload ── -->
              <div class="form-group" style="grid-column: 1 / -1;">
                <label for="inputFotoKTP">
                  Foto KTP
                  <span class="optional-badge">Opsional</span>
                </label>

                <!-- Drop-zone -->
                <div class="ktp-dropzone" id="ktpDropzone" onclick="document.getElementById('inputFotoKTP').click()">
                  <div class="ktp-dropzone-inner" id="ktpDropzoneInner">
                    <i class="fas fa-id-card ktp-icon"></i>
                    <p class="ktp-label">Klik atau seret foto KTP ke sini</p>
                    <p class="ktp-hint">JPG, PNG, WEBP — maks. 2 MB</p>
                  </div>
                  <!-- Preview (hidden until file chosen) -->
                  <div class="ktp-preview-wrap" id="ktpPreviewWrap" style="display:none">
                    <img id="ktpPreviewImg" src="" alt="Preview KTP"/>
                    <button type="button" class="ktp-remove" id="ktpRemoveBtn"
                            onclick="removeKTP(event)" title="Hapus foto">
                      <i class="fas fa-times"></i>
                    </button>
                    <p class="ktp-filename" id="ktpFilename"></p>
                  </div>
                </div>

                <input type="file" name="foto_ktp" id="inputFotoKTP"
                       accept="image/jpeg,image/png,image/webp"
                       style="display:none"/>
                <p class="nik-hint" id="ktpError" style="color:#e53e3e;display:none">
                  <i class="fas fa-circle-exclamation"></i> <span id="ktpErrorMsg"></span>
                </p>
              </div>

            </div>
          </div>

          <!-- ── Account Security ── -->
          <div class="section-card">
            <div class="section-title">
              <i class="fa-solid fa-shield-halved"></i>
              Account Security
            </div>
            <div class="form-grid col-2">
              <div class="form-group">
                <label for="inputNewPassword1">Kata Sandi <span class="req">*</span></label>
                <div class="input-wrap">
                  <i class="fas fa-lock"></i>
                  <input type="password" name="password" id="inputNewPassword1"
                         placeholder="Kata Sandi" autocomplete="off" required
                         oninput="checkStrength(this.value)"/>
                  <button type="button" class="btn-eye" onclick="toggleEye('inputNewPassword1','eyeIcon1')" tabindex="-1">
                    <i class="fas fa-eye" id="eyeIcon1"></i>
                  </button>
                </div>
              </div>
              <div class="form-group">
                <label for="inputNewPassword2">Konfirmasi Kata Sandi <span class="req">*</span></label>
                <div class="input-wrap">
                  <i class="fas fa-lock"></i>
                  <input type="password" name="password2" id="inputNewPassword2"
                         placeholder="Konfirmasi Kata Sandi" autocomplete="off" required/>
                  <button type="button" class="btn-eye" onclick="toggleEye('inputNewPassword2','eyeIcon2')" tabindex="-1">
                    <i class="fas fa-eye" id="eyeIcon2"></i>
                  </button>
                </div>
              </div>
            </div>
            <div class="password-tools">
              <button type="button" class="btn-generate" onclick="generatePassword()">
                <i class="fas fa-wand-magic-sparkles"></i> Generate Password
              </button>
              <div class="strength-meter">
                <div class="strength-bar">
                  <div class="strength-fill" id="strengthFill"></div>
                </div>
                <p class="strength-label" id="strengthLabel">Kekuatan Kata Sandi: Masukan Kata Sandi</p>
              </div>
            </div>
          </div>

          <!-- ── Mailing List ── -->
          <div class="section-card">
            <div class="section-title">
              <i class="fa-solid fa-envelope-open-text"></i>
              Join our mailing list
            </div>
            <p class="mailing-text">
              We would like to send you occasional news, information and special offers by email.
              To join our mailing list, simply tick the box below. You can unsubscribe at any time.
            </p>
            <?php $mailingChecked = isset($old['marketingoptin']) || !$old; ?>
            <label class="toggle-label">
              <input type="checkbox" name="marketingoptin" value="1"
                     <?= $mailingChecked ? 'checked' : '' ?> id="mailingToggle"
                     onchange="updateToggle(this)"/>
              <div class="toggle-switch">
                <div class="toggle-knob"></div>
              </div>
              <span class="toggle-text" id="toggleText"><?= $mailingChecked ? 'Yes' : 'No' ?></span>
            </label>
          </div>

          <!-- ── TOS + Submit ── -->
          <div class="section-footer">
            <label class="tos-check">
              <input type="checkbox" name="accepttos" class="tos-input" id="tosCheck"/>
              <span class="checkmark"></span>
              Saya telah membaca dan setuju dengan
              <a href="/../ketentuan_layanan.php/" target="_blank">Ketentuan Layanan</a>
              <span class="req">*</span>
            </label>
            <p class="tos-error" id="tosError">Anda harus menyetujui Ketentuan Layanan untuk melanjutkan.</p>

            <button type="submit" class="btn-register" id="btnRegister">
              <i class="fas fa-user-plus"></i> Pendaftaran
            </button>

            <p class="signin-link">Sudah punya akun? <a href="/login/login.php">Masuk di sini</a></p>
            <p class="signin-link"><a href="../index.php">Home</a></p>
          </div>

        </form>
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

    function toggleEye(inputId, iconId) {
      const input = document.getElementById(inputId);
      const icon  = document.getElementById(iconId);
      if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
      } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
      }
    }

    function checkStrength(val) {
      const fill  = document.getElementById('strengthFill');
      const label = document.getElementById('strengthLabel');
      if (!val) {
        fill.style.width = '0%';
        fill.className = 'strength-fill';
        label.textContent = 'Kekuatan Kata Sandi: Masukan Kata Sandi';
        return;
      }
      let score = 0;
      if (val.length >= 8)           score++;
      if (/[A-Z]/.test(val))         score++;
      if (/[0-9]/.test(val))         score++;
      if (/[^A-Za-z0-9]/.test(val))  score++;
      const levels = [
        { w: '25%',  cls: 'weak',   text: 'Lemah' },
        { w: '50%',  cls: 'fair',   text: 'Cukup' },
        { w: '75%',  cls: 'good',   text: 'Baik' },
        { w: '100%', cls: 'strong', text: 'Sangat Kuat' },
      ];
      const l = levels[score - 1] || levels[0];
      fill.style.width = l.w;
      fill.className = 'strength-fill ' + l.cls;
      label.textContent = 'Kekuatan Kata Sandi: ' + l.text;
    }

    function generatePassword() {
      const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
      let pwd = '';
      for (let i = 0; i < 14; i++) pwd += chars[Math.floor(Math.random() * chars.length)];
      document.getElementById('inputNewPassword1').value = pwd;
      document.getElementById('inputNewPassword2').value = pwd;
      ['inputNewPassword1','inputNewPassword2'].forEach(function(id, idx) {
        document.getElementById(id).type = 'text';
        document.getElementById('eyeIcon' + (idx+1)).classList.replace('fa-eye','fa-eye-slash');
      });
      checkStrength(pwd);
      setTimeout(function() {
        ['inputNewPassword1','inputNewPassword2'].forEach(function(id, idx) {
          document.getElementById(id).type = 'password';
          document.getElementById('eyeIcon' + (idx+1)).classList.replace('fa-eye-slash','fa-eye');
        });
      }, 3000);
    }

    function updateToggle(cb) {
      document.getElementById('toggleText').textContent = cb.checked ? 'Yes' : 'No';
    }

    document.getElementById('frmCheckout').addEventListener('submit', function(e) {
      const tos      = document.getElementById('tosCheck');
      const tosError = document.getElementById('tosError');
      let valid = true;

      if (!tos.checked) {
        tosError.classList.add('show');
        tos.closest('.tos-check').style.color = '#e53e3e';
        valid = false;
      } else {
        tosError.classList.remove('show');
        tos.closest('.tos-check').style.color = '';
      }

      const p1 = document.getElementById('inputNewPassword1').value;
      const p2 = document.getElementById('inputNewPassword2').value;
      if (p1 && p2 && p1 !== p2) {
        document.getElementById('inputNewPassword2').setCustomValidity('Kata sandi tidak cocok.');
        valid = false;
      } else {
        document.getElementById('inputNewPassword2').setCustomValidity('');
      }

      // NIK validation: if filled, must be exactly 16 digits
      const nikEl = document.getElementById('inputNIK');
      if (nikEl && nikEl.value.trim() !== '') {
        if (!/^\d{16}$/.test(nikEl.value.trim())) {
          nikEl.setCustomValidity('NIK harus 16 digit angka.');
          valid = false;
        } else {
          nikEl.setCustomValidity('');
        }
      } else if (nikEl) {
        nikEl.setCustomValidity('');
      }

      if (!valid) e.preventDefault();
    });

    // Live NIK enforcement: digits only, max 16
    const nikInput = document.getElementById('inputNIK');
    if (nikInput) {
      nikInput.addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 16);
        if (this.value.length > 0 && this.value.length < 16) {
          this.setCustomValidity('NIK harus 16 digit.');
        } else {
          this.setCustomValidity('');
        }
      });
    }

    document.getElementById('tosCheck').addEventListener('change', function() {
      if (this.checked) {
        document.getElementById('tosError').classList.remove('show');
        this.closest('.tos-check').style.color = '';
      }
    });

    // ── KTP File Upload ──────────────────────────────────────────
    const ktpInput    = document.getElementById('inputFotoKTP');
    const ktpDropzone = document.getElementById('ktpDropzone');
    const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    const MAX_BYTES     = 2 * 1024 * 1024; // 2 MB

    function showKTPError(msg) {
      document.getElementById('ktpError').style.display = 'block';
      document.getElementById('ktpErrorMsg').textContent = msg;
    }
    function clearKTPError() {
      document.getElementById('ktpError').style.display = 'none';
    }

    function handleKTPFile(file) {
      clearKTPError();
      if (!file) return;
      if (!ALLOWED_TYPES.includes(file.type)) {
        showKTPError('Format tidak didukung. Gunakan JPG, PNG, atau WEBP.');
        ktpInput.value = '';
        return;
      }
      if (file.size > MAX_BYTES) {
        showKTPError('Ukuran file terlalu besar. Maksimum 2 MB.');
        ktpInput.value = '';
        return;
      }
      const reader = new FileReader();
      reader.onload = function(e) {
        document.getElementById('ktpPreviewImg').src = e.target.result;
        document.getElementById('ktpFilename').textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
        document.getElementById('ktpDropzoneInner').style.display = 'none';
        document.getElementById('ktpPreviewWrap').style.display   = 'block';
      };
      reader.readAsDataURL(file);
    }

    ktpInput.addEventListener('change', function() {
      if (this.files && this.files[0]) handleKTPFile(this.files[0]);
    });

    // Drag & drop
    ['dragenter','dragover'].forEach(function(evt) {
      ktpDropzone.addEventListener(evt, function(e) {
        e.preventDefault();
        ktpDropzone.classList.add('drag-over');
      });
    });
    ['dragleave','drop'].forEach(function(evt) {
      ktpDropzone.addEventListener(evt, function(e) {
        e.preventDefault();
        ktpDropzone.classList.remove('drag-over');
      });
    });
    ktpDropzone.addEventListener('drop', function(e) {
      const file = e.dataTransfer.files[0];
      if (file) {
        // Assign to file input via DataTransfer
        try {
          const dt = new DataTransfer();
          dt.items.add(file);
          ktpInput.files = dt.files;
        } catch(_) {}
        handleKTPFile(file);
      }
    });

    function removeKTP(e) {
      e.stopPropagation();
      ktpInput.value = '';
      document.getElementById('ktpPreviewImg').src         = '';
      document.getElementById('ktpFilename').textContent   = '';
      document.getElementById('ktpPreviewWrap').style.display   = 'none';
      document.getElementById('ktpDropzoneInner').style.display = 'block';
      clearKTPError();
    }
  </script>

</body>
</html>
