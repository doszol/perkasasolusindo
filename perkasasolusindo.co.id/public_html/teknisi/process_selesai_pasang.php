<?php
// =====================================================
//  teknisi/process_selesai_pasang.php
//  Handler: Teknisi tandai order "Selesai Dipasang"
//  → wifi_status: scheduled → installed
//  → Notifikasi ke semua admin/owner + ke client
//  → Catat di tblorder_status_logs
// =====================================================
require_once __DIR__ . '/../auth_check.php';
requireLevel(4);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../mailer.php';   // perkasa_send_mail($to, $toName, $subject, $htmlBody)

// ── Validasi input ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

$order_id   = isset($_POST['order_id'])  ? (int)$_POST['order_id']  : 0;
$catatan    = isset($_POST['catatan'])   ? trim($_POST['catatan'])   : '';
$teknisi_id = (int)$_SESSION['user_id'];

if ($order_id <= 0) {
    header('Location: teknisi_dashboard.php?err=invalid');
    exit();
}

// ── Ambil order — pastikan teknisi ini memang ditugaskan ──────────
$stmt = $conn->prepare("
    SELECT o.id, o.order_number, o.wifi_status, o.userid,
           o.kecamatan, o.kota,
           c.firstname AS client_first, c.lastname AS client_last, c.email AS client_email,
           p.name AS nama_paket
    FROM tblorders o
    LEFT JOIN tblclients c ON o.userid   = c.id
    LEFT JOIN tblproducts p ON o.productid = p.id
    WHERE o.id = ? AND (o.teknisi_id = ? OR o.teknisi_id_2 = ?)
    LIMIT 1
");
$stmt->bind_param("iii", $order_id, $teknisi_id, $teknisi_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    // Bukan order miliknya atau tidak ada
    header('Location: teknisi_dashboard.php?err=forbidden');
    exit();
}

// ── Hanya boleh update jika status masih scheduled ───────────────
if ($order['wifi_status'] !== 'scheduled') {
    header("Location: teknisi_dashboard.php?order_id={$order_id}&err=wrong_status");
    exit();
}

$order_number = $order['order_number'];
$nama_client  = trim($order['client_first'] . ' ' . $order['client_last']);
$email_client = $order['client_email'];
$nama_paket   = $order['nama_paket'];
$lokasi       = trim($order['kecamatan'] . ', ' . $order['kota']);

// ── Ambil nama teknisi ───────────────────────────────────────────
$stmt = $conn->prepare("SELECT firstname, lastname FROM tblclients WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $teknisi_id);
$stmt->execute();
$tek = $stmt->get_result()->fetch_assoc();
$stmt->close();
$nama_teknisi = trim($tek['firstname'] . ' ' . $tek['lastname']);

// ── Mulai transaksi ───────────────────────────────────────────────
$conn->begin_transaction();

try {
    // 1. Update wifi_status → installed
    $stmt = $conn->prepare("
        UPDATE tblorders
        SET wifi_status = 'installed', updated_at = NOW()
        WHERE id = ? AND (teknisi_id = ? OR teknisi_id_2 = ?) AND wifi_status = 'scheduled'
    ");
    $stmt->bind_param("iii", $order_id, $teknisi_id, $teknisi_id);
    $stmt->execute();
    if ($stmt->affected_rows < 1) {
        // Race-condition: sudah diubah orang lain
        throw new Exception('Status sudah berubah, muat ulang halaman.');
    }
    $stmt->close();

    // 2. Simpan log status
    $log_catatan = $catatan ?: 'Instalasi selesai dilaporkan oleh teknisi.';
    $stmt = $conn->prepare("
        INSERT INTO tblorder_status_logs
            (order_id, old_status, new_status, changed_by, role, catatan)
        VALUES (?, 'scheduled', 'installed', ?, 'teknisi', ?)
    ");
    $stmt->bind_param("iis", $order_id, $teknisi_id, $log_catatan);
    $stmt->execute();
    $stmt->close();

    // 3. Update catatan order jika ada catatan baru
    if ($catatan !== '') {
        $stmt = $conn->prepare("
            UPDATE tblorders SET note = ?, updated_at = NOW() WHERE id = ?
        ");
        $stmt->bind_param("si", $catatan, $order_id);
        $stmt->execute();
        $stmt->close();
    }

    // ── Ambil semua admin & owner untuk notifikasi ────────────────
    $stmt = $conn->prepare("
        SELECT id, email, firstname FROM tblclients
        WHERE level IN (1, 2) AND status = 1
    ");
    $stmt->execute();
    $admins = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // 4. Notifikasi in-app ke semua admin/owner
    $judul_admin = "✅ Instalasi Selesai — {$order_number}";
    $pesan_admin = "Teknisi {$nama_teknisi} melaporkan instalasi selesai untuk order {$order_number} "
                 . "({$nama_client}, {$nama_paket}, {$lokasi}). "
                 . "Silakan review dan aktifkan layanan.";

    $stmt = $conn->prepare("
        INSERT INTO tblnotifikasi (userid, order_id, judul, pesan, tipe)
        VALUES (?, ?, ?, ?, 'sukses')
    ");
    foreach ($admins as $admin) {
        $stmt->bind_param("iiss", $admin['id'], $order_id, $judul_admin, $pesan_admin);
        $stmt->execute();
    }
    $stmt->close();

    // 5. Notifikasi in-app ke client — minta upload bukti pembayaran
    $judul_client = "📡 WiFi Anda Telah Terpasang! — {$order_number}";
    $pesan_client = "Selamat! Instalasi WiFi paket {$nama_paket} di lokasi Anda telah selesai dilakukan. "
                  . "Silakan login ke dashboard dan upload bukti pembayaran untuk mengaktifkan layanan.";

    $stmt = $conn->prepare("
        INSERT INTO tblnotifikasi (userid, order_id, judul, pesan, tipe)
        VALUES (?, ?, ?, ?, 'sukses')
    ");
    $stmt->bind_param("iiss", $order['userid'], $order_id, $judul_client, $pesan_client);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    // ── Kirim email (setelah commit, agar tidak blocking transaksi) ──

    $year     = date('Y');
    $time_now = date('d M Y, H:i') . ' WIB';

    // ── Template email ke admin/owner ────────────────────────────────
    $subject_admin = "[Perkasa Solusindo] ✅ Instalasi Selesai — {$order_number}";

    $catatan_html = $catatan
        ? '<tr>
            <td style="color:#64748b;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.05)">Catatan Teknisi</td>
            <td style="color:#f1f5f9;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.05);text-align:right">'
            . htmlspecialchars($catatan) . '</td>
           </tr>'
        : '';

    $body_admin = '<!DOCTYPE html>
<html lang="id">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#0b0f1a;font-family:Arial,sans-serif;color:#f1f5f9">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#0b0f1a;padding:40px 20px">
<tr><td align="center">
<table width="100%" style="max-width:580px" cellpadding="0" cellspacing="0">

  <tr>
    <td style="background:#1a2235;border:1px solid rgba(255,255,255,.08);border-radius:14px 14px 0 0;padding:22px 28px">
      <table width="100%" cellpadding="0" cellspacing="0"><tr>
        <td>
          <div style="font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.8px;margin-bottom:5px">🔔 Notifikasi Admin</div>
          <div style="font-size:20px;font-weight:900;color:#f1f5f9">Instalasi WiFi Selesai</div>
        </td>
        <td align="right" valign="top" style="white-space:nowrap">
          <div style="font-size:12px;color:#64748b;margin-top:4px">' . $time_now . '</div>
        </td>
      </tr></table>
    </td>
  </tr>

  <tr>
    <td style="background:#111827;padding:24px 28px;border:1px solid rgba(255,255,255,.06);border-top:none">

      <div style="font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.6px;margin-bottom:12px">Detail Order</div>
      <table width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;margin-bottom:22px">
        <tr>
          <td style="color:#64748b;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.05);width:42%">Nomor Order</td>
          <td style="color:#f97316;font-weight:800;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.05);text-align:right;font-family:\'Courier New\',monospace;font-size:15px">'
            . htmlspecialchars($order_number) . '</td>
        </tr>
        <tr>
          <td style="color:#64748b;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.05)">Pelanggan</td>
          <td style="color:#f1f5f9;font-weight:600;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.05);text-align:right">'
            . htmlspecialchars($nama_client) . '</td>
        </tr>
        <tr>
          <td style="color:#64748b;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.05)">Paket</td>
          <td style="color:#f1f5f9;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.05);text-align:right">'
            . htmlspecialchars($nama_paket) . '</td>
        </tr>
        <tr>
          <td style="color:#64748b;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.05)">Lokasi</td>
          <td style="color:#f1f5f9;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.05);text-align:right">'
            . htmlspecialchars($lokasi) . '</td>
        </tr>
        <tr>
          <td style="color:#64748b;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.05)">Dilaporkan oleh</td>
          <td style="color:#22c55e;font-weight:600;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.05);text-align:right">Teknisi '
            . htmlspecialchars($nama_teknisi) . '</td>
        </tr>
        ' . $catatan_html . '
      </table>

      <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:22px">
        <tr>
          <td style="background:rgba(16,185,129,.07);border:1px solid rgba(16,185,129,.22);border-left:4px solid #10b981;border-radius:0 8px 8px 0;padding:13px 16px;font-size:13px;color:#6ee7b7;line-height:1.6">
            ✅ <strong>Instalasi Selesai — Perlu Review Admin</strong><br>
            <span style="color:#94a3b8">Silakan review di panel admin, verifikasi hasil instalasi, dan aktifkan layanan pelanggan.</span>
          </td>
        </tr>
      </table>

      <table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">
        <a href="' . ADMIN_ORDERS_URL . '"
           style="display:inline-block;background:linear-gradient(135deg,#f97316,#ea580c);color:#fff;font-size:14px;font-weight:700;text-decoration:none;padding:13px 28px;border-radius:8px">
          📋 &nbsp;Lihat Order di Admin
        </a>
      </td></tr></table>
    </td>
  </tr>

  <tr>
    <td style="background:#0b0f1a;border-radius:0 0 14px 14px;padding:16px 28px;text-align:center;border:1px solid rgba(255,255,255,.06);border-top:none">
      <div style="font-size:11px;color:#334155;line-height:1.7">
        Notifikasi internal sistem Perkasa Solusindo &nbsp;·&nbsp; Jangan dibalas.<br>
        &copy; ' . $year . ' Perkasa Tech Solusindo.
      </div>
    </td>
  </tr>

</table></td></tr></table>
</body></html>';

    foreach ($admins as $admin) {
        $nama_admin = trim($admin['firstname']);
        perkasa_send_mail($admin['email'], $nama_admin, $subject_admin, $body_admin);
    }

    // ── Ambil harga paket untuk invoice ─────────────────────────────
    $stmt = $conn->prepare("SELECT price FROM tblproducts WHERE id = (SELECT productid FROM tblorders WHERE id = ?) LIMIT 1");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $prod_row   = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $harga_paket = $prod_row ? (float)$prod_row['price'] : 0;
    $harga_fmt   = 'Rp ' . number_format($harga_paket, 0, ',', '.');

    // Nomor invoice: INV-<order_number>
    $invoice_number = 'INV-' . $order_number;
    // Jatuh tempo 3 hari sejak sekarang
    $due_date = date('d M Y', strtotime('+3 days'));

    // ── Template email invoice ke client ────────────────────────────
    $subject_client = "[Perkasa Solusindo] 🧾 Invoice Tagihan WiFi — {$invoice_number}";

    $body_client = '<!DOCTYPE html>
<html lang="id">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#0b0f1a;font-family:Arial,sans-serif;color:#f1f5f9">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#0b0f1a;padding:40px 20px">
<tr><td align="center">
<table width="100%" style="max-width:600px" cellpadding="0" cellspacing="0">

  <!-- Header -->
  <tr>
    <td style="background:linear-gradient(135deg,#1d4ed8,#1e40af);border-radius:14px 14px 0 0;padding:28px 32px">
      <table width="100%" cellpadding="0" cellspacing="0"><tr>
        <td>
          <div style="font-size:11px;color:rgba(255,255,255,.6);text-transform:uppercase;letter-spacing:1px;margin-bottom:4px">Perkasa Tech Solusindo</div>
          <div style="font-size:24px;font-weight:900;color:#fff">🧾 Invoice Tagihan</div>
          <div style="font-size:13px;color:rgba(255,255,255,.75);margin-top:4px">WiFi Berhasil Terpasang — Mohon segera lakukan pembayaran</div>
        </td>
        <td align="right" valign="top">
          <div style="background:rgba(255,255,255,.12);border-radius:8px;padding:8px 14px;display:inline-block">
            <div style="font-size:10px;color:rgba(255,255,255,.6);margin-bottom:2px">No. Invoice</div>
            <div style="font-size:13px;font-weight:800;color:#fff;font-family:\'Courier New\',monospace">' . htmlspecialchars($invoice_number) . '</div>
          </div>
        </td>
      </tr></table>
    </td>
  </tr>

  <!-- Sukses instalasi -->
  <tr>
    <td style="background:#0d1f12;border:1px solid rgba(16,185,129,.2);border-top:none;padding:16px 32px">
      <table width="100%" cellpadding="0" cellspacing="0"><tr>
        <td style="font-size:13px;color:#6ee7b7">
          ✅ &nbsp;Instalasi WiFi paket <strong>' . htmlspecialchars($nama_paket) . '</strong>
          di lokasi Anda telah <strong>selesai</strong> dilakukan oleh teknisi kami.
        </td>
      </tr></table>
    </td>
  </tr>

  <!-- Body -->
  <tr>
    <td style="background:#111827;padding:28px 32px;border:1px solid rgba(255,255,255,.06);border-top:none">

      <p style="font-size:15px;color:#e2e8f0;margin:0 0 6px">
        Yth. <strong style="color:#60a5fa">' . htmlspecialchars($nama_client) . '</strong>,
      </p>
      <p style="font-size:13px;color:#94a3b8;line-height:1.7;margin:0 0 24px">
        Berikut adalah invoice tagihan pemasangan WiFi Anda. Mohon segera lakukan
        pembayaran sebelum jatuh tempo agar layanan dapat segera diaktifkan.
      </p>

      <!-- Rincian invoice -->
      <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.7px;margin-bottom:10px">Rincian Tagihan</div>
      <table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;margin-bottom:24px;border:1px solid rgba(255,255,255,.08);border-radius:10px;overflow:hidden">
        <thead>
          <tr style="background:rgba(255,255,255,.05)">
            <th style="padding:10px 14px;text-align:left;color:#94a3b8;font-weight:600">Deskripsi</th>
            <th style="padding:10px 14px;text-align:right;color:#94a3b8;font-weight:600">Jumlah</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td style="padding:12px 14px;color:#e2e8f0;border-top:1px solid rgba(255,255,255,.06)">
              Biaya Instalasi &amp; Bulan Pertama<br>
              <span style="font-size:11px;color:#64748b">Paket ' . htmlspecialchars($nama_paket) . ' · ' . htmlspecialchars($order_number) . '</span>
            </td>
            <td style="padding:12px 14px;text-align:right;color:#f1f5f9;font-weight:700;border-top:1px solid rgba(255,255,255,.06)">' . $harga_fmt . '</td>
          </tr>
          <tr style="background:rgba(255,255,255,.03)">
            <td style="padding:12px 14px;color:#94a3b8;font-size:12px;border-top:1px solid rgba(255,255,255,.06)">Jatuh Tempo</td>
            <td style="padding:12px 14px;text-align:right;color:#f87171;font-weight:700;font-size:13px;border-top:1px solid rgba(255,255,255,.06)">' . $due_date . '</td>
          </tr>
          <tr style="background:rgba(59,130,246,.08)">
            <td style="padding:14px;color:#93c5fd;font-weight:700;border-top:2px solid rgba(59,130,246,.3)">TOTAL TAGIHAN</td>
            <td style="padding:14px;text-align:right;color:#60a5fa;font-weight:900;font-size:18px;border-top:2px solid rgba(59,130,246,.3)">' . $harga_fmt . '</td>
          </tr>
        </tbody>
      </table>

      <!-- Info transfer -->
      <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.7px;margin-bottom:10px">Informasi Pembayaran</div>
      <table width="100%" cellpadding="0" cellspacing="0" style="background:rgba(30,64,175,.12);border:1px solid rgba(59,130,246,.25);border-radius:12px;margin-bottom:24px">
        <tr>
          <td style="padding:20px 24px">
            <table width="100%" cellpadding="0" cellspacing="0" style="font-size:13px">
              <tr>
                <td style="color:#94a3b8;padding:7px 0;width:42%;border-bottom:1px solid rgba(255,255,255,.05)">Bank</td>
                <td style="color:#f1f5f9;font-weight:700;padding:7px 0;text-align:right;border-bottom:1px solid rgba(255,255,255,.05)">
                  <span style="background:#003087;color:#fff;padding:2px 8px;border-radius:4px;font-size:12px;font-weight:800">BCA</span>
                </td>
              </tr>
              <tr>
                <td style="color:#94a3b8;padding:7px 0;border-bottom:1px solid rgba(255,255,255,.05)">No. Rekening</td>
                <td style="color:#60a5fa;font-weight:900;padding:7px 0;text-align:right;font-family:\'Courier New\',monospace;font-size:16px;border-bottom:1px solid rgba(255,255,255,.05)">0184246283</td>
              </tr>
              <tr>
                <td style="color:#94a3b8;padding:7px 0;border-bottom:1px solid rgba(255,255,255,.05)">Atas Nama</td>
                <td style="color:#f1f5f9;font-weight:700;padding:7px 0;text-align:right;border-bottom:1px solid rgba(255,255,255,.05)">TECH PERKASA SOLUSINDO</td>
              </tr>
              <tr>
                <td style="color:#94a3b8;padding:7px 0">Nominal Transfer</td>
                <td style="color:#34d399;font-weight:900;padding:7px 0;text-align:right;font-size:16px">' . $harga_fmt . '</td>
              </tr>
            </table>
          </td>
        </tr>
      </table>

      <!-- Peringatan jatuh tempo -->
      <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px">
        <tr>
          <td style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);border-left:4px solid #ef4444;border-radius:0 8px 8px 0;padding:13px 16px;font-size:13px;color:#fca5a5;line-height:1.7">
            ⏰ <strong>Harap bayar sebelum ' . $due_date . '</strong><br>
            <span style="color:#94a3b8">Layanan WiFi akan diaktifkan setelah pembayaran diverifikasi oleh admin kami.</span>
          </td>
        </tr>
      </table>

      <!-- Langkah selanjutnya -->
      <div style="font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.7px;margin-bottom:10px">Langkah Selanjutnya</div>
      <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px">
        <tr>
          <td style="background:rgba(234,179,8,.07);border:1px solid rgba(234,179,8,.2);border-radius:10px;padding:16px">
            <table cellpadding="0" cellspacing="0" style="font-size:13px;color:#fde68a">
              <tr valign="top">
                <td style="padding:4px 12px 4px 0;color:#fbbf24;font-weight:800;white-space:nowrap">1.</td>
                <td style="padding:4px 0;color:#94a3b8">Transfer sejumlah <strong style="color:#fde68a">' . $harga_fmt . '</strong> ke rekening BCA di atas.</td>
              </tr>
              <tr valign="top">
                <td style="padding:4px 12px 4px 0;color:#fbbf24;font-weight:800;white-space:nowrap">2.</td>
                <td style="padding:4px 0;color:#94a3b8">Simpan bukti transfer (screenshot / foto struk).</td>
              </tr>
              <tr valign="top">
                <td style="padding:4px 12px 4px 0;color:#fbbf24;font-weight:800;white-space:nowrap">3.</td>
                <td style="padding:4px 0;color:#94a3b8">Upload bukti pembayaran melalui tombol di bawah atau di <strong style="color:#fde68a">Client Dashboard</strong> Anda.</td>
              </tr>
              <tr valign="top">
                <td style="padding:4px 12px 4px 0;color:#fbbf24;font-weight:800;white-space:nowrap">4.</td>
                <td style="padding:4px 0;color:#94a3b8">Admin akan memverifikasi dan mengaktifkan layanan WiFi Anda.</td>
              </tr>
            </table>
          </td>
        </tr>
      </table>

      <!-- CTA -->
      <table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">
        <a href="' . DASHBOARD_URL . '"
           style="display:inline-block;background:linear-gradient(135deg,#1d4ed8,#2563eb);color:#fff;font-size:14px;font-weight:700;text-decoration:none;padding:14px 36px;border-radius:8px;letter-spacing:.3px">
          📤 &nbsp;Upload Bukti Pembayaran Sekarang
        </a>
        <div style="margin-top:10px;font-size:11px;color:#475569">atau buka: ' . DASHBOARD_URL . '</div>
      </td></tr></table>

    </td>
  </tr>

  <!-- Footer -->
  <tr>
    <td style="background:#0b0f1a;border-radius:0 0 14px 14px;padding:18px 28px;text-align:center;border:1px solid rgba(255,255,255,.06);border-top:none">
      <div style="font-size:11px;color:#334155;line-height:1.8">
        Butuh bantuan? Hubungi kami atau teknisi yang memasang WiFi Anda.<br>
        Email ini dikirim otomatis — mohon tidak membalas langsung ke email ini.<br>
        &copy; ' . $year . ' Perkasa Tech Solusindo. All rights reserved.
      </div>
    </td>
  </tr>

</table></td></tr></table>
</body></html>';

    perkasa_send_mail($email_client, $nama_client, $subject_client, $body_client);

} catch (Exception $e) {
    $conn->rollback();
    $msg = urlencode($e->getMessage());
    header("Location: teknisi_dashboard.php?order_id={$order_id}&err=failed&msg={$msg}");
    exit();
}

// ── Redirect sukses ───────────────────────────────────────────────
header("Location: teknisi_dashboard.php?order_id={$order_id}&selesai=1");
exit();
