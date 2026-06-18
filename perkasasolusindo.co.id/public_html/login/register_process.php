<?php
// =====================================================
//  register_process.php  –  /login/register_process.php
// =====================================================
require_once __DIR__ . '/../auth_check.php';
redirectIfLoggedIn();
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /login/registrasi.php');
    exit;
}

// ── Core fields ──────────────────────────────────────
$firstname      = trim($_POST['firstname']     ?? '');
$lastname       = trim($_POST['lastname']      ?? '');
$email          = trim($_POST['email']         ?? '');
$phonenumber    = trim($_POST['phonenumber']   ?? '');
$companyname    = trim($_POST['companyname']   ?? '');
$address1       = trim($_POST['address1']      ?? '');
$address2       = trim($_POST['address2']      ?? '');
$city           = trim($_POST['city']          ?? '');
$state          = trim($_POST['state']         ?? '');
$postcode       = trim($_POST['postcode']      ?? '');
$country        = trim($_POST['country']       ?? '');
$currency       = (int)($_POST['currency']     ?? 1);
$password       = $_POST['password']           ?? '';
$password2      = $_POST['password2']          ?? '';
$marketingoptin = isset($_POST['marketingoptin']) ? 1 : 0;
$accepttos      = isset($_POST['accepttos'])      ? 1 : 0;

// ── KTP / Identity fields ─────────────────────────────
$nik            = trim($_POST['nik']            ?? '');
$tempat_lahir   = trim($_POST['tempat_lahir']  ?? '');
$tanggal_lahir  = trim($_POST['tanggal_lahir'] ?? '');
$jenis_kelamin  = trim($_POST['jenis_kelamin'] ?? '');

$level         = 3;    // public registration always creates Client
$emailVerified = 0;

// ── Validation ───────────────────────────────────────
$errors = [];

if (!$firstname)
    $errors[] = 'First name is required.';
if (!$lastname)
    $errors[] = 'Last name is required.';
if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL))
    $errors[] = 'A valid email address is required.';
if (!$phonenumber)
    $errors[] = 'Phone number is required.';
if (!$address1)
    $errors[] = 'Street address is required.';
if (!$city)
    $errors[] = 'City is required.';
if (!$state)
    $errors[] = 'State / Province is required.';
if (!$postcode)
    $errors[] = 'Postcode is required.';
if (!$country)
    $errors[] = 'Country is required.';
if (!$password)
    $errors[] = 'Password is required.';
if (strlen($password) < 8)
    $errors[] = 'Password must be at least 8 characters.';
if ($password !== $password2)
    $errors[] = 'Passwords do not match.';
if (!$accepttos)
    $errors[] = 'You must accept the Terms of Service.';

// ── KTP validation ────────────────────────────────────
if ($nik && (!ctype_digit($nik) || strlen($nik) !== 16))
    $errors[] = 'NIK harus 16 digit angka.';
if ($tanggal_lahir && !strtotime($tanggal_lahir))
    $errors[] = 'Format tanggal lahir tidak valid.';
if ($jenis_kelamin && !in_array($jenis_kelamin, ['L', 'P'], true))
    $errors[] = 'Jenis kelamin tidak valid.';

// ── Foto KTP validation (optional) ───────────────────
$fotoKtpVal = null;
$ktpUploaded = isset($_FILES['foto_ktp']) && $_FILES['foto_ktp']['error'] !== UPLOAD_ERR_NO_FILE;

if ($ktpUploaded) {
    $ktpFile    = $_FILES['foto_ktp'];
    $ktpAllowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $ktpMaxSize = 2 * 1024 * 1024; // 2 MB

    if ($ktpFile['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Gagal mengupload foto KTP. Kode error: ' . $ktpFile['error'];
    } elseif ($ktpFile['size'] > $ktpMaxSize) {
        $errors[] = 'Ukuran foto KTP terlalu besar. Maksimum 2 MB.';
    } else {
        // Validate MIME via finfo (don't trust $_FILES['type'])
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($ktpFile['tmp_name']);
        if (!array_key_exists($mimeType, $ktpAllowed)) {
            $errors[] = 'Format foto KTP tidak valid. Gunakan JPG, PNG, atau WEBP.';
        }
    }
}

// ── Duplicate email check ─────────────────────────────
if (!$errors) {
    $chk = $conn->prepare("SELECT id FROM tblclients WHERE email = ? LIMIT 1");
    $chk->bind_param('s', $email);
    $chk->execute();
    $chk->store_result();
    if ($chk->num_rows > 0) {
        $errors[] = 'An account with this email already exists.';
    }
    $chk->close();
}

// ── Duplicate NIK check ───────────────────────────────
if (!$errors && $nik) {
    $nikChk = $conn->prepare("SELECT id FROM tblclients WHERE nik = ? LIMIT 1");
    $nikChk->bind_param('s', $nik);
    $nikChk->execute();
    $nikChk->store_result();
    if ($nikChk->num_rows > 0) {
        $errors[] = 'NIK ini sudah terdaftar di sistem.';
    }
    $nikChk->close();
}

if ($errors) {
    $_SESSION['reg_errors'] = $errors;
    $_SESSION['reg_old']    = $_POST;
    header('Location: /login/registrasi.php');
    exit;
}

// ── Move uploaded KTP file ────────────────────────────
if ($ktpUploaded && !$errors) {
    $ktpFile    = $_FILES['foto_ktp'];
    $finfo      = new finfo(FILEINFO_MIME_TYPE);
    $mimeType   = $finfo->file($ktpFile['tmp_name']);
    $ktpAllowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $ext        = $ktpAllowed[$mimeType];

    $uploadDir  = $_SERVER['DOCUMENT_ROOT'] . '/order/order_asset/ktp/';

    // Ensure directory exists
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = 'ktp_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $destPath = $uploadDir . $filename;

    if (!move_uploaded_file($ktpFile['tmp_name'], $destPath)) {
        error_log('[register_process] Failed to move KTP upload to: ' . $destPath);
        // Non-fatal: proceed without the photo, log the failure
        $fotoKtpVal = null;
    } else {
        $fotoKtpVal = $filename;
    }
}

// ── Sanitize optional KTP fields ─────────────────────
$nikVal           = $nik           ?: null;
$tempatLahirVal   = $tempat_lahir  ?: null;
$tanggalLahirVal  = ($tanggal_lahir && strtotime($tanggal_lahir))
                    ? date('Y-m-d', strtotime($tanggal_lahir))
                    : null;
$jenisKelaminVal  = ($jenis_kelamin === 'L' || $jenis_kelamin === 'P') ? $jenis_kelamin : null;

$hashed = password_hash($password, PASSWORD_BCRYPT);

// ── INSERT ────────────────────────────────────────────
$stmt = $conn->prepare("
    INSERT INTO tblclients
        (firstname, lastname, email, phonenumber, companyname,
         address1, address2, city, state, postcode, country,
         currency, password, marketingoptin, accepttos,
         email_verified, level,
         nik, tempat_lahir, tanggal_lahir, jenis_kelamin, foto_ktp,
         datecreated)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
");

if (!$stmt) {
    $_SESSION['reg_errors'] = ['Prepare failed: ' . $conn->error];
    $_SESSION['reg_old']    = $_POST;
    header('Location: /login/registrasi.php');
    exit;
}

// 22 params: s×11 + i(currency) + s(password) + i×4 + s×4(identity) + s(foto_ktp)
$stmt->bind_param(
    'sssssssssssisisiiissss',
    $firstname, $lastname, $email, $phonenumber, $companyname,
    $address1, $address2, $city, $state, $postcode, $country,
    $currency, $hashed, $marketingoptin, $accepttos,
    $emailVerified, $level,
    $nikVal, $tempatLahirVal, $tanggalLahirVal, $jenisKelaminVal, $fotoKtpVal
);

if ($stmt->execute()) {
    $stmt->close();
    $conn->close();
    $_SESSION['reg_success'] = 'Akun berhasil dibuat! Silakan login.';
    header('Location: /login/login.php');
} else {
    $dbError = $stmt->error;
    $stmt->close();
    $conn->close();
    $_SESSION['reg_errors'] = ['Database error: ' . $dbError];
    $_SESSION['reg_old']    = $_POST;
    header('Location: /login/registrasi.php');
}
exit;
