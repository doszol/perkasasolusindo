<?php
// =====================================================
//  client/da_autologin.php
//  Auto-login client ke panel DirectAdmin
//  Menggunakan password asli client (da_password dari DB)
//  POST form auto-submit ke /CMD_LOGIN (legacy endpoint)
// =====================================================

require_once __DIR__ . '/../auth_check.php';
requireLevel([3]);
require_once __DIR__ . '/../config.php';

$userId     = (int)$_SESSION['user_id'];
$hosting_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($hosting_id <= 0) { http_response_code(400); die('Parameter tidak valid.'); }

$st = $conn->prepare("
    SELECT da_username, da_password, domainstatus
    FROM tblhosting
    WHERE id = ? AND userid = ?
    LIMIT 1
");
$st->bind_param('ii', $hosting_id, $userId);
$st->execute();
$row = $st->get_result()->fetch_assoc();
$st->close();

if (!$row)                             { http_response_code(403); die('Akun hosting tidak ditemukan.'); }
if ($row['domainstatus'] !== 'Active') { http_response_code(403); die('Akun hosting belum aktif.'); }
if (empty($row['da_username']))        { http_response_code(500); die('Username DA belum tersedia. Hubungi support.'); }
if (empty($row['da_password']))        { http_response_code(500); die('Password DA belum tersedia. Hubungi support.'); }

$da_username = $row['da_username'];
$da_password = $row['da_password'];
$action_url  = 'https://' . DA_HOST . ':' . DA_PORT . '/CMD_LOGIN';

// URL fallback jika login gagal (mis. password di DB tidak sinkron dengan
// password asli di DA — bisa terjadi kalau client pernah ganti password
// langsung lewat panel) atau saat logout dari DA.
$fail_url   = SITE_URL . '/client/client_dashboard.php?view=layanan_hosting&da_login=gagal';
$logout_url = SITE_URL . '/client/client_dashboard.php?view=layanan_hosting';

?><!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Mengalihkan ke DirectAdmin...</title>
  <style>
    body { font-family:sans-serif; display:flex; align-items:center;
           justify-content:center; height:100vh; margin:0;
           background:#0f172a; color:#94a3b8; }
    .box { text-align:center; }
    .spinner { width:40px; height:40px; border:4px solid #334155;
               border-top-color:#f97316; border-radius:50%;
               animation:spin .8s linear infinite; margin:0 auto 16px; }
    @keyframes spin { to { transform:rotate(360deg); } }
  </style>
</head>
<body>
  <div class="box">
    <div class="spinner"></div>
    <p>Mengalihkan ke DirectAdmin...</p>
  </div>
  <form id="daForm" method="POST" action="<?= htmlspecialchars($action_url) ?>">
    <input type="hidden" name="referer" value="/">
    <input type="hidden" name="FAIL_URL" value="<?= htmlspecialchars($fail_url) ?>">
    <input type="hidden" name="LOGOUT_URL" value="<?= htmlspecialchars($logout_url) ?>">
    <input type="hidden" name="username" value="<?= htmlspecialchars($da_username) ?>">
    <input type="hidden" name="password" value="<?= htmlspecialchars($da_password) ?>">
  </form>
  <script>document.getElementById('daForm').submit();</script>
</body>
</html>
