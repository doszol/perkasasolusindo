<?php
// =====================================================
//  mark_notif_read.php  –  /client/mark_notif_read.php
//  Dipanggil AJAX dari client_dashboard.php
//  Menandai notifikasi sebagai sudah dibaca.
// =====================================================
require_once __DIR__ . '/../auth_check.php';
requireLevel(3);
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

$userId = (int)$_SESSION['user_id'];

if (!empty($_POST['all'])) {
    // Tandai semua notifikasi milik user ini sebagai dibaca
    $st = $conn->prepare(
        "UPDATE tblnotifikasi SET sudah_dibaca = 1 WHERE userid = ? AND sudah_dibaca = 0"
    );
    $st->bind_param('i', $userId);
    $st->execute();
    $st->close();
    echo json_encode(['ok' => true]);
    exit;
}

$notifId = (int)($_POST['notif_id'] ?? 0);
if (!$notifId) {
    echo json_encode(['ok' => false]);
    exit;
}

// Hanya boleh menandai notifikasi milik user yang sedang login
$st = $conn->prepare(
    "UPDATE tblnotifikasi SET sudah_dibaca = 1 WHERE id = ? AND userid = ? LIMIT 1"
);
$st->bind_param('ii', $notifId, $userId);
$st->execute();
$st->close();

echo json_encode(['ok' => true]);
