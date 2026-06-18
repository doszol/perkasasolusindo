<?php
// ============================================================
// cron/cron_test.php
// Letakkan di: /public_html/cron/cron_test.php
//
// FUNGSI:
//   Script untuk MENGETES apakah cron job di server berjalan
//   dengan benar, dan apakah koneksi SMTP (mailer) berfungsi.
//
//   Berbeda dari cron_reminder.php / cron_suspend.php, script ini
//   TIDAK memerlukan config.php (tidak konek ke database) dan
//   TIDAK memerlukan mailer.php utama — semua kebutuhan SMTP
//   diambil dari mailer_test.php (standalone) yang ada di folder
//   yang sama, supaya tes ini tidak terganggu masalah lain di
//   config.php / mailer.php.
//
//   Setiap kali script ini dijalankan, ia akan:
//     1. Mencatat waktu eksekusi ke file marker (cron_test_last_run.txt)
//     2. Mencoba kirim email test ke MAIL_ADMIN via mailer_test.php
//     3. Menampilkan hasilnya (sukses/gagal) di output/log
//
// CRONTAB (cPanel → Cron Jobs) — contoh tes setiap menit:
//   * * * * * php /home/perkasas/public_html/cron/cron_test.php >> /home/perkasas/logs/cron_test.log 2>&1
//
// Setelah dipastikan cron berjalan & email diterima,
// HAPUS atau NONAKTIFKAN cron job ini agar tidak spam email.
//
// TEST MANUAL (lewat browser):
//   https://perkasasolusindo.co.id/cron/cron_test.php?cron_token=perkasa_cron_2025_s3cr3t
//
// TEST MANUAL DENGAN SMTP DEBUG VERBOSE (jika email gagal terkirim):
//   https://perkasasolusindo.co.id/cron/cron_test.php?cron_token=perkasa_cron_2025_s3cr3t&debug=1
// ============================================================

// Pastikan hanya bisa dipanggil dari CLI atau dengan token rahasia
if (php_sapi_name() !== 'cli') {
    $token = $_GET['cron_token'] ?? '';
    if ($token !== 'perkasa_cron_2025_s3cr3t') {
        http_response_code(403);
        die('Forbidden');
    }
}

define('CRON_RUNNING', true);

// Gunakan mailer test standalone (tidak butuh config.php / mailer.php utama)
require_once __DIR__ . '/mailer_test.php';

$now    = date('Y-m-d H:i:s');
$source = (php_sapi_name() === 'cli') ? 'CLI (cron job)' : 'Browser (manual test)';
$debug  = isset($_GET['debug']) && $_GET['debug'] === '1';

echo "[$now] === CRON TEST DIMULAI ===\n";
echo "  Dijalankan via : $source\n";
echo "  PHP version    : " . PHP_VERSION . "\n";
echo "  Server time    : $now\n";

// ── 1. Catat ke file marker (membuktikan cron benar2 jalan) ─────
$markerFile = __DIR__ . '/cron_test_last_run.txt';
$writeOk = @file_put_contents(
    $markerFile,
    "Cron test terakhir berjalan: $now\nVia: $source\n"
);
if ($writeOk !== false) {
    echo "\n  → Marker file ditulis ke: $markerFile\n";
    echo "    [OK] Penanda waktu eksekusi berhasil disimpan.\n";
} else {
    echo "\n  → Marker file ditulis ke: $markerFile\n";
    echo "    [ERROR] Gagal menulis marker file (cek permission folder cron/).\n";
}

// ── 2. Tes kirim email via mailer_test.php ───────────────────────
echo "\n  → Mengirim email test ke admin (" . MAIL_ADMIN . ")...\n";
if ($debug) {
    echo "    [INFO] Mode debug aktif — SMTP log akan ditampilkan.\n";
}

$subject = '✅ Cron Test Berhasil – ' . date('d M Y H:i:s');
$body    = render_email_cron_test([
    'waktu'  => $now,
    'source' => $source,
    'host'   => MAIL_HOST,
]);

$result = perkasa_test_send_mail(MAIL_ADMIN, 'Admin Perkasa Solusindo', $subject, $body, $debug);

if ($result['ok']) {
    echo "    [OK] Email test berhasil dikirim ke " . MAIL_ADMIN . ".\n";
} else {
    echo "    [ERROR] Gagal mengirim email test: {$result['error']}\n";
}

if ($debug && $result['debug']) {
    echo "\n  ── SMTP DEBUG LOG ──────────────────────────────────\n";
    echo $result['debug'];
    echo "  ────────────────────────────────────────────────────\n";
}

echo "\n[" . date('d M Y H:i:s') . "] === CRON TEST SELESAI ===\n";
echo "==================================================\n";
echo "Jika kamu melihat baris ini di log/output, berarti:\n";
echo "  - PHP CLI di server berfungsi\n";
echo "  - File ini bisa diakses & dieksekusi oleh cron\n";
echo "  - Status pengiriman email tertulis di atas\n";
echo "==================================================\n";
