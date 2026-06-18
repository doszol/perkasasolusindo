-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jun 18, 2026 at 06:25 PM
-- Server version: 10.3.39-MariaDB-cll-lve
-- PHP Version: 7.3.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `perkasas_maindata`
--

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `image` varchar(255) NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `title`, `image`, `link`, `created_at`) VALUES
(1, 'Digital Agency HTML Templates', 'projects-01.jpg', '#', '2026-06-03 05:16:49'),
(2, 'Admin Dashboard CSS Templates', 'projects-02.jpg', '#', '2026-06-03 05:16:49'),
(3, 'Best Responsive Website Layouts', 'projects-03.jpg', '#', '2026-06-03 05:16:49');

-- --------------------------------------------------------

--
-- Table structure for table `tblannouncements`
--

CREATE TABLE `tblannouncements` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL DEFAULT '',
  `announcement` text NOT NULL,
  `date` datetime NOT NULL DEFAULT current_timestamp(),
  `published` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0=Draft, 1=Published'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pengumuman / berita untuk klien';

-- --------------------------------------------------------

--
-- Table structure for table `tblclients`
--

CREATE TABLE `tblclients` (
  `id` int(11) NOT NULL,
  `firstname` varchar(100) NOT NULL DEFAULT '',
  `lastname` varchar(100) NOT NULL DEFAULT '',
  `email` varchar(255) NOT NULL DEFAULT '',
  `phonenumber` varchar(30) NOT NULL DEFAULT '',
  `companyname` varchar(255) DEFAULT NULL,
  `address1` varchar(255) NOT NULL DEFAULT '',
  `address2` varchar(255) DEFAULT NULL,
  `city` varchar(100) NOT NULL DEFAULT '',
  `state` varchar(100) NOT NULL DEFAULT '',
  `postcode` varchar(20) NOT NULL DEFAULT '',
  `country` char(2) NOT NULL DEFAULT 'ID' COMMENT 'ISO 3166-1 alpha-2 country code',
  `currency` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=IDR, 3=USD',
  `password` varchar(255) NOT NULL DEFAULT '' COMMENT 'bcrypt hash via password_hash()',
  `marketingoptin` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=No, 1=Yes',
  `accepttos` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=Not accepted, 1=Accepted',
  `email_verified` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=Not verified, 1=Verified',
  `level` tinyint(1) NOT NULL DEFAULT 3 COMMENT '1=Owner, 2=Admin, 3=Client, 4=Teknisi',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
  `datecreated` datetime NOT NULL DEFAULT current_timestamp(),
  `lastupdated` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `notes` text DEFAULT NULL COMMENT 'Catatan internal admin tentang klien ini',
  `nik` varchar(20) DEFAULT NULL COMMENT 'Nomor Induk Kependudukan (KTP)',
  `tempat_lahir` varchar(100) DEFAULT NULL COMMENT 'Tempat lahir sesuai KTP',
  `tanggal_lahir` date DEFAULT NULL COMMENT 'Tanggal lahir sesuai KTP',
  `jenis_kelamin` enum('L','P') DEFAULT NULL COMMENT 'L=Laki-laki, P=Perempuan',
  `ktp_file` varchar(255) DEFAULT NULL,
  `foto_ktp` varchar(255) DEFAULT NULL COMMENT 'Path file foto KTP',
  `reset_token` varchar(64) DEFAULT NULL COMMENT 'SHA-256 hash of 6-digit OTP for password reset / email verify',
  `reset_token_expires` datetime DEFAULT NULL COMMENT 'Expiry timestamp for reset_token'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registered client accounts – level: 1=Owner, 2=Admin, 3=Client';

--
-- Dumping data for table `tblclients`
--

INSERT INTO `tblclients` (`id`, `firstname`, `lastname`, `email`, `phonenumber`, `companyname`, `address1`, `address2`, `city`, `state`, `postcode`, `country`, `currency`, `password`, `marketingoptin`, `accepttos`, `email_verified`, `level`, `status`, `datecreated`, `lastupdated`, `notes`, `nik`, `tempat_lahir`, `tanggal_lahir`, `jenis_kelamin`, `ktp_file`, `foto_ktp`, `reset_token`, `reset_token_expires`) VALUES
(1, 'Amirul', 'Fuad', 'amrlfuad0906@gmail.com', '085851460867', 'Semar Jagatech', 'Jl. Bima No.36', 'RT06 RW01 Kebonsari, Candi', 'Sidoarjo', 'Jawa Timur', '61271', 'ID', 1, '$2y$10$kmrBL12uAkmgAvRf7MYfQuus/i5IHIAYxyndtQYx1ooVa7FNpjCD6', 1, 1, 1, 2, 1, '2026-06-04 15:18:35', '2026-06-06 08:55:42', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(2, 'Raja', 'Athena', 'raja@gmail.com', '081000111000', 'Raja Tech', 'Jl. Bima No.36', 'RT06 RW01 Kebonsari, Candi', 'Sidoarjo', 'Jawa Timur', '61271', 'ID', 1, '$2y$10$6EumiPcIKLPZvtqM0chmkO4RIb2OaZeDh2MOoY2bwLeUKvaHKs/P.', 1, 1, 1, 3, 1, '2026-06-04 15:38:59', '2026-06-05 10:20:33', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(3, 'BIMA', 'x', 'bimapamungkas0000@gmail.com', '081246684665', 'CCTV', 'Bligo RT.14 RW.06 Candi-Sidoarjo', '', 'Kabupaten Sidoarjo', 'Jawa Timur', '61271', 'ID', 1, '$2y$10$c0AUpQXlKxIfJfQuTdSXHOapfXbYlDp..xSw5qxrDezuX/9DcCD4G', 1, 1, 1, 1, 1, '2026-06-04 15:44:52', '2026-06-06 12:38:23', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(4, 'Don', 'Doszol', 'amrlfuad9616@gmail.com', '085851460867', 'mbuh', 'Jl. Bima No.36', '', 'Sidoarjo', 'Jawa Timur', '61271', 'ID', 1, '$2y$10$bhCQeiAxXavd58rt1dsFpOTVWKnWlZZwdJekZknsBRRnCqpth3iB2', 1, 1, 1, 3, 1, '2026-06-06 08:53:29', '2026-06-12 13:29:03', NULL, '3515072204990003', 'Jl. Bima No. 36 Kebonsari Candi Sidoarjo', '1999-04-22', 'L', NULL, 'ktp_1780988557_393106454a27.jpeg', NULL, NULL),
(5, 'Amirul', 'Athena', 'sangmaestro123@gmail.com', '085851460867', 'sang maestro', 'Perum Permata Candiloka, Blok B14 RT.04/RW.04', 'Balonggabus, Candi', 'Sidoarjo', 'Jawa Timur', '61271', 'ID', 1, '$2y$10$WhXbRr3qFyz2Am31wuRs/eNmoVtIcuenks.Ejyo00cX0XKC/sbciu', 1, 1, 1, 3, 1, '2026-06-10 15:13:33', '2026-06-12 07:50:21', NULL, '3515072204990004', 'Jl. Bima No. 36 Kebonsari Candi Sidoarjo', '1999-01-10', 'L', NULL, 'ktp_1781079213_fc50746466d7.jpeg', NULL, NULL),
(6, 'dosol', 'mantab', 'se.marjagatech@gmail.com', '085851460867', NULL, 'Jl. Bima No.36', NULL, 'Sidoarjo', 'Jawa Timur', '61271', 'ID', 1, '$2y$10$iL.02xmxHgN/XCA0ihekjOmzOJJ2UnKxiwemiM0FYJKIKkqiQHNSW', 0, 1, 1, 4, 1, '2026-06-11 11:16:45', '2026-06-12 13:31:45', 'it', '3515072204990007', 'Jl. Bima No. 36 Kebonsari Candi Sidoarjo', '1999-02-12', 'L', NULL, 'teknisi_6_1781151405.jpeg', NULL, NULL),
(7, 'teknisi', 'fuad', 'se.marjagatechstudio@gmail.com', '085851460867', NULL, 'Jl. Bima No.36', NULL, 'Sidoarjo', 'Jawa Timur', '61271', 'ID', 1, '$2y$10$z5nkPkUleORqh85dEMREw./SNM4O7i8vwYod2fl2SNLpMW7C3SXUm', 0, 1, 1, 4, 1, '2026-06-11 11:27:38', '2026-06-11 13:30:15', 'it', '3515072204990009', 'Jl. Bima No. 36 Kebonsari Candi Sidoarjo', '1999-05-01', 'L', NULL, 'teknisi_7_1781152058.jpeg', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tbldomains`
--

CREATE TABLE `tbldomains` (
  `id` int(11) NOT NULL,
  `userid` int(11) NOT NULL,
  `domain` varchar(255) NOT NULL DEFAULT '',
  `status` varchar(50) NOT NULL DEFAULT '' COMMENT 'Active, Expired, Cancelled, Transferred Away, Fraud',
  `expirydate` date DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Domain terdaftar per klien';

-- --------------------------------------------------------

--
-- Table structure for table `tblhosting`
--

CREATE TABLE `tblhosting` (
  `id` int(11) NOT NULL,
  `userid` int(11) NOT NULL,
  `packageid` int(11) DEFAULT NULL COMMENT 'FK ke tblproducts.id',
  `domain` varchar(255) NOT NULL DEFAULT '',
  `domain_type` varchar(20) NOT NULL DEFAULT 'subdomain' COMMENT 'subdomain = gratis, beli = domain berbayar',
  `domain_tld` varchar(20) DEFAULT NULL COMMENT 'Ekstensi domain yang dibeli, e.g. .com .id .xyz',
  `domain_price` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Harga domain per tahun (IDR), 0 jika subdomain gratis',
  `da_username` varchar(32) DEFAULT NULL,
  `da_password` varchar(64) DEFAULT NULL,
  `da_status` varchar(20) NOT NULL DEFAULT 'pending',
  `da_db_name` varchar(80) DEFAULT NULL,
  `da_db_user` varchar(80) DEFAULT NULL,
  `da_db_pass` varchar(64) DEFAULT NULL,
  `da_db_host` varchar(64) DEFAULT 'localhost',
  `da_docroot` varchar(255) DEFAULT NULL,
  `domainstatus` varchar(50) NOT NULL DEFAULT 'Active' COMMENT 'Active, Suspended, Cancelled, Terminated, Fraud',
  `nextduedate` date DEFAULT NULL,
  `payment_deadline` datetime DEFAULT NULL COMMENT 'Sinkron dengan tblorders.payment_deadline pada order hosting terkait',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `payment_verified_at` datetime DEFAULT NULL COMMENT 'Timestamp saat admin konfirmasi pembayaran hosting (sebelum approve)',
  `payment_verified_by` int(11) DEFAULT NULL COMMENT 'FK tblclients.id — admin yang konfirmasi pembayaran hosting'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Layanan hosting aktif per klien';

-- --------------------------------------------------------

--
-- Table structure for table `tblinvoices`
--

CREATE TABLE `tblinvoices` (
  `id` int(11) NOT NULL,
  `userid` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL COMMENT 'FK tblorders.id — order yang menghasilkan invoice ini',
  `status` varchar(50) NOT NULL DEFAULT 'Unpaid' COMMENT 'Unpaid, Paid, Cancelled, Refunded, Collections, Draft',
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `duedate` date DEFAULT NULL,
  `datepaid` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Invoice tagihan klien';

-- --------------------------------------------------------

--
-- Table structure for table `tblnotifikasi`
--

CREATE TABLE `tblnotifikasi` (
  `id` int(11) NOT NULL,
  `userid` int(11) NOT NULL COMMENT 'Penerima notifikasi (FK tblclients.id)',
  `order_id` int(11) DEFAULT NULL COMMENT 'FK tblorders.id',
  `judul` varchar(255) NOT NULL DEFAULT '',
  `pesan` text NOT NULL,
  `tipe` varchar(30) NOT NULL DEFAULT 'info' COMMENT 'info | sukses | peringatan | error',
  `sudah_dibaca` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Notifikasi untuk admin, teknisi, dan client';

--
-- Dumping data for table `tblnotifikasi`
--

INSERT INTO `tblnotifikasi` (`id`, `userid`, `order_id`, `judul`, `pesan`, `tipe`, `sudah_dibaca`, `created_at`) VALUES
(1, 4, 1, 'Order WiFi Anda Berhasil Dikirim', 'Terima kasih Don! Order WiFi paket Nextstar Home 15 Mbps (#ORD-20260609-0004) berhasil diterima. Tim kami akan segera memverifikasi data Anda dan menghubungi untuk jadwal instalasi.', 'sukses', 1, '2026-06-09 14:02:37'),
(2, 1, 1, 'Order WiFi Baru — ORD-20260609-0004', 'Order baru dari Don Doszol untuk paket Nextstar Home 15 Mbps. Alamat: Perum Permata Candiloka, Blok B14, Balonggabus, Candi, Sidoarjo. Status: Menunggu Verifikasi.', 'info', 1, '2026-06-09 14:02:37'),
(3, 3, 1, 'Order WiFi Baru — ORD-20260609-0004', 'Order baru dari Don Doszol untuk paket Nextstar Home 15 Mbps. Alamat: Perum Permata Candiloka, Blok B14, Balonggabus, Candi, Sidoarjo. Status: Menunggu Verifikasi.', 'info', 0, '2026-06-09 14:02:37'),
(4, 4, 1, 'Pesanan Anda Diverifikasi ✅', 'Admin telah memverifikasi pesanan Anda. Tim kami akan segera menghubungi Anda.', 'sukses', 1, '2026-06-09 14:48:12'),
(6, 1, 2, 'Order WiFi Baru — ORD-20260610-0000', 'Order baru dari Amirul Fuad untuk paket Nextstar Home 15 Mbps. Alamat: Perum Permata Candiloka, Blok B14, Balonggabus, Candi, Sidoarjo. Status: Menunggu Verifikasi.', 'info', 1, '2026-06-10 14:47:21'),
(7, 3, 2, 'Order WiFi Baru — ORD-20260610-0000', 'Order baru dari Amirul Fuad untuk paket Nextstar Home 15 Mbps. Alamat: Perum Permata Candiloka, Blok B14, Balonggabus, Candi, Sidoarjo. Status: Menunggu Verifikasi.', 'info', 0, '2026-06-10 14:47:21'),
(8, 5, 3, 'Order WiFi Anda Berhasil Dikirim', 'Terima kasih Amirul! Order WiFi paket Nextstar Home 15 Mbps (#ORD-20260610-0003) berhasil diterima. Tim kami akan segera memverifikasi data Anda dan menghubungi untuk jadwal instalasi.', 'sukses', 1, '2026-06-10 15:13:33'),
(9, 1, 3, 'Order WiFi Baru — ORD-20260610-0003', 'Order baru dari Amirul Athena untuk paket Nextstar Home 15 Mbps. Alamat: Perum Permata Candiloka, Blok B14, Balonggabus, Candi, Sidoarjo. Status: Menunggu Verifikasi.', 'info', 0, '2026-06-10 15:13:33'),
(10, 3, 3, 'Order WiFi Baru — ORD-20260610-0003', 'Order baru dari Amirul Athena untuk paket Nextstar Home 15 Mbps. Alamat: Perum Permata Candiloka, Blok B14, Balonggabus, Candi, Sidoarjo. Status: Menunggu Verifikasi.', 'info', 0, '2026-06-10 15:13:33'),
(11, 5, 3, 'Pesanan Anda Diverifikasi ✅', 'Admin telah memverifikasi pesanan. Tim akan segera menghubungi Anda.', 'sukses', 1, '2026-06-10 15:17:25'),
(12, 4, 1, 'Pesanan Anda Diverifikasi ✅', 'Admin telah memverifikasi pesanan. Tim akan segera menghubungi Anda.', 'sukses', 1, '2026-06-11 11:22:14'),
(13, 4, 1, 'Jadwal Instalasi Ditetapkan 📅', 'Jadwal pemasangan sudah ditetapkan. Cek detail di dashboard.', 'info', 1, '2026-06-11 11:22:26'),
(14, 4, 1, 'Jadwal Instalasi Ditetapkan 📅', 'Jadwal pemasangan sudah ditetapkan. Cek detail di dashboard.', 'info', 1, '2026-06-11 11:30:40'),
(15, 5, 3, 'Jadwal Instalasi Ditetapkan 📅', 'Jadwal pemasangan sudah ditetapkan. Cek detail di dashboard.', 'info', 1, '2026-06-11 11:33:44'),
(16, 1, 1, '✅ Instalasi Selesai — ORD-20260609-0004', 'Teknisi teknisi fuad melaporkan instalasi selesai untuk order ORD-20260609-0004 (Don Doszol, Nextstar Home 15 Mbps, Candi, Sidoarjo). Silakan review dan aktifkan layanan.', 'sukses', 0, '2026-06-11 14:01:55'),
(17, 3, 1, '✅ Instalasi Selesai — ORD-20260609-0004', 'Teknisi teknisi fuad melaporkan instalasi selesai untuk order ORD-20260609-0004 (Don Doszol, Nextstar Home 15 Mbps, Candi, Sidoarjo). Silakan review dan aktifkan layanan.', 'sukses', 0, '2026-06-11 14:01:55'),
(18, 4, 1, '📡 WiFi Anda Telah Terpasang! — ORD-20260609-0004', 'Selamat! Instalasi WiFi paket Nextstar Home 15 Mbps di lokasi Anda telah selesai dilakukan. Silakan login ke dashboard dan upload bukti pembayaran untuk mengaktifkan layanan.', 'sukses', 1, '2026-06-11 14:01:55'),
(19, 1, 1, 'Konfirmasi Pembayaran — #ORD-20260609-0004', 'Client Don Doszol telah mengupload bukti pembayaran untuk order #ORD-20260609-0004. Silakan periksa dan verifikasi pembayaran.', 'info', 0, '2026-06-12 07:43:58'),
(20, 3, 1, 'Konfirmasi Pembayaran — #ORD-20260609-0004', 'Client Don Doszol telah mengupload bukti pembayaran untuk order #ORD-20260609-0004. Silakan periksa dan verifikasi pembayaran.', 'info', 0, '2026-06-12 07:43:58'),
(21, 1, 3, '✅ Instalasi Selesai — ORD-20260610-0003', 'Teknisi dosol mantab melaporkan instalasi selesai untuk order ORD-20260610-0003 (Amirul Athena, Nextstar Home 15 Mbps, Candi, Sidoarjo). Silakan review dan aktifkan layanan.', 'sukses', 0, '2026-06-12 08:14:45'),
(22, 3, 3, '✅ Instalasi Selesai — ORD-20260610-0003', 'Teknisi dosol mantab melaporkan instalasi selesai untuk order ORD-20260610-0003 (Amirul Athena, Nextstar Home 15 Mbps, Candi, Sidoarjo). Silakan review dan aktifkan layanan.', 'sukses', 0, '2026-06-12 08:14:45'),
(23, 5, 3, '📡 WiFi Anda Telah Terpasang! — ORD-20260610-0003', 'Selamat! Instalasi WiFi paket Nextstar Home 15 Mbps di lokasi Anda telah selesai dilakukan. Silakan login ke dashboard dan upload bukti pembayaran untuk mengaktifkan layanan.', 'sukses', 1, '2026-06-12 08:14:45'),
(24, 4, 1, 'Layanan WiFi Anda Aktif 🚀', 'ID Pelanggan Anda: 5501261417. Layanan internet sudah dapat digunakan. Selamat menikmati!', 'sukses', 1, '2026-06-12 10:25:39'),
(25, 1, 3, 'Konfirmasi Pembayaran — #ORD-20260610-0003', 'Client Amirul Athena telah mengupload bukti pembayaran untuk order #ORD-20260610-0003. Silakan periksa dan verifikasi pembayaran.', 'info', 0, '2026-06-12 14:04:36'),
(26, 3, 3, 'Konfirmasi Pembayaran — #ORD-20260610-0003', 'Client Amirul Athena telah mengupload bukti pembayaran untuk order #ORD-20260610-0003. Silakan periksa dan verifikasi pembayaran.', 'info', 0, '2026-06-12 14:04:36'),
(27, 5, 3, 'Layanan WiFi Anda Aktif 🚀', 'ID Pelanggan Anda: 5501261418. Layanan internet sudah dapat digunakan. Selamat menikmati!', 'sukses', 1, '2026-06-12 14:06:18');

-- --------------------------------------------------------

--
-- Table structure for table `tblorders`
--

CREATE TABLE `tblorders` (
  `id` int(11) NOT NULL,
  `order_number` varchar(30) DEFAULT NULL COMMENT 'Nomor order unik e.g. ORD-20260608-0001',
  `order_type` varchar(30) NOT NULL DEFAULT 'wifi' COMMENT 'wifi, hosting, website, etc',
  `wifi_status` varchar(30) NOT NULL DEFAULT 'pending' COMMENT 'pending=Menunggu verifikasi | verified=Diverifikasi admin | scheduled=Dijadwalkan instalasi | installed=Instalasi selesai | active=Aktif | cancelled=Dibatalkan',
  `order_status` varchar(30) NOT NULL DEFAULT 'pending' COMMENT 'pending|active|cancelled - status generik untuk order non-wifi (hosting, dll)',
  `alamat_pasang` text DEFAULT NULL COMMENT 'Alamat pemasangan wifi',
  `rt` varchar(5) DEFAULT NULL,
  `rw` varchar(5) DEFAULT NULL,
  `kelurahan` varchar(100) DEFAULT NULL,
  `kecamatan` varchar(100) DEFAULT NULL,
  `kota` varchar(100) DEFAULT NULL,
  `provinsi` varchar(100) DEFAULT NULL,
  `kodepos` varchar(10) DEFAULT NULL,
  `koordinat_lat` decimal(10,7) DEFAULT NULL,
  `koordinat_lng` decimal(10,7) DEFAULT NULL,
  `teknisi_id` int(11) DEFAULT NULL COMMENT 'FK tblclients.id — teknisi yang ditugaskan',
  `teknisi_id_2` int(11) DEFAULT NULL COMMENT 'FK tblclients.id — teknisi kedua yang ditugaskan',
  `jadwal_instalasi` datetime DEFAULT NULL COMMENT 'Jadwal pemasangan oleh teknisi',
  `tgl_aktif` date DEFAULT NULL COMMENT 'Tanggal layanan aktif setelah instalasi',
  `payment_status` varchar(20) NOT NULL DEFAULT 'belum_bayar' COMMENT 'belum_bayar | sudah_bayar | lunas',
  `payment_proof` varchar(255) DEFAULT NULL COMMENT 'Path file bukti pembayaran yang diupload client',
  `tagihan_bulan` date DEFAULT NULL COMMENT 'Jatuh tempo tagihan bulan ini',
  `payment_deadline` datetime DEFAULT NULL COMMENT 'Batas waktu upload+konfirmasi pembayaran (created_at + 24 jam). Lewat batas & belum lunas -> dihapus otomatis oleh cron.',
  `userid` int(11) NOT NULL COMMENT 'FK ke tblclients.id',
  `productid` int(11) NOT NULL COMMENT 'FK ke tblproducts.id',
  `periode_bulan` int(2) DEFAULT NULL COMMENT 'Periode hosting dalam bulan (1-12), dipilih client saat order',
  `status` varchar(30) NOT NULL DEFAULT 'Active' COMMENT 'Active, Suspended, Cancelled, Completed',
  `note` text DEFAULT NULL COMMENT 'Catatan admin tentang pesanan ini',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `id_pelanggan` varchar(50) DEFAULT NULL COMMENT 'ID pelanggan dari aplikasi e-billing',
  `tanggal_expire` date DEFAULT NULL COMMENT 'Tanggal jatuh tempo layanan WiFi',
  `installation_paid_until` date DEFAULT NULL COMMENT 'Batas layanan dari pembayaran instalasi awal (set saat aktivasi, tidak berubah)',
  `reminder_sent_at` date DEFAULT NULL COMMENT 'Tanggal terakhir email pengingat tagihan dikirim (cron tgl 10)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Produk yang dipesan/digunakan per klien';

--
-- Dumping data for table `tblorders`
--

INSERT INTO `tblorders` (`id`, `order_number`, `order_type`, `wifi_status`, `order_status`, `alamat_pasang`, `rt`, `rw`, `kelurahan`, `kecamatan`, `kota`, `provinsi`, `kodepos`, `koordinat_lat`, `koordinat_lng`, `teknisi_id`, `teknisi_id_2`, `jadwal_instalasi`, `tgl_aktif`, `payment_status`, `payment_proof`, `tagihan_bulan`, `payment_deadline`, `userid`, `productid`, `periode_bulan`, `status`, `note`, `created_at`, `updated_at`, `id_pelanggan`, `tanggal_expire`, `installation_paid_until`, `reminder_sent_at`) VALUES
(1, 'ORD-20260609-0004', 'wifi', 'active', 'pending', 'Perum Permata Candiloka, Blok B14', '04', '04', 'Balonggabus', 'Candi', 'Sidoarjo', 'Jawa Timur', '61271', NULL, NULL, 6, 7, '2026-06-12 11:21:00', '2026-06-12', 'lunas', 'bukti_ORD-20260609-0004_1781225038_77e02e51.png', NULL, NULL, 4, 1, NULL, 'Active', NULL, '2026-06-09 14:02:37', '2026-06-13 11:23:57', '5501261417', '2026-07-20', '2026-07-20', NULL),
(3, 'ORD-20260610-0003', 'wifi', 'active', 'pending', 'Perum Permata Candiloka, Blok B14', '04', '04', 'Balonggabus', 'Candi', 'Sidoarjo', 'Jawa Timur', '61271', NULL, NULL, 6, 7, '2026-06-12 15:32:00', '2026-06-12', 'lunas', 'bukti_ORD-20260610-0003_1781247873_bd54c20a.png', NULL, NULL, 5, 1, NULL, 'Active', 'ok baik, besok meluncur', '2026-06-10 15:13:33', '2026-06-13 11:23:57', '5501261418', '2026-07-20', '2026-07-20', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tblorder_status_logs`
--

CREATE TABLE `tblorder_status_logs` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL COMMENT 'FK tblorders.id',
  `old_status` varchar(30) DEFAULT NULL,
  `new_status` varchar(30) NOT NULL,
  `changed_by` int(11) NOT NULL COMMENT 'FK tblclients.id (admin/teknisi/system)',
  `role` varchar(20) NOT NULL DEFAULT 'admin' COMMENT 'admin | teknisi | client | system',
  `catatan` text DEFAULT NULL COMMENT 'Catatan perubahan status',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Riwayat perubahan status order WiFi';

--
-- Dumping data for table `tblorder_status_logs`
--

INSERT INTO `tblorder_status_logs` (`id`, `order_id`, `old_status`, `new_status`, `changed_by`, `role`, `catatan`, `created_at`) VALUES
(1, 1, NULL, 'pending', 4, 'system', 'Order baru dibuat oleh client yang sudah terdaftar', '2026-06-09 14:02:37'),
(2, 1, 'pending', 'verified', 1, 'admin', '', '2026-06-09 14:48:12'),
(4, 3, NULL, 'pending', 5, 'system', 'Order baru dibuat oleh client baru (registrasi sekaligus)', '2026-06-10 15:13:33'),
(5, 3, 'pending', 'verified', 1, 'admin', '', '2026-06-10 15:17:25'),
(6, 1, 'verified', 'verified', 1, 'admin', '', '2026-06-11 11:22:14'),
(7, 1, 'verified', 'scheduled', 1, 'admin', '', '2026-06-11 11:22:26'),
(8, 1, 'scheduled', 'scheduled', 1, 'admin', '', '2026-06-11 11:30:40'),
(9, 3, 'verified', 'scheduled', 1, 'admin', '', '2026-06-11 11:33:44'),
(10, 3, 'scheduled', 'scheduled', 6, 'teknisi', 'ok baik, besok meluncur', '2026-06-11 12:16:51'),
(11, 1, 'scheduled', 'installed', 7, 'teknisi', 'Instalasi selesai dilaporkan oleh teknisi.', '2026-06-11 14:01:55'),
(12, 1, 'belum_bayar', 'sudah_bayar', 4, 'client', 'Client mengupload bukti pembayaran.', '2026-06-12 07:43:58'),
(13, 3, 'scheduled', 'installed', 6, 'teknisi', 'Instalasi selesai dilaporkan oleh teknisi.', '2026-06-12 08:14:45'),
(14, 1, 'installed', 'active', 1, 'admin', 'ID Pelanggan: 5501261417. Layanan WiFi diaktifkan.', '2026-06-12 10:25:39'),
(15, 3, 'belum_bayar', 'sudah_bayar', 5, 'client', 'Client mengupload bukti pembayaran.', '2026-06-12 14:04:36'),
(16, 3, 'installed', 'active', 1, 'admin', 'ID Pelanggan: 5501261418. Layanan WiFi diaktifkan.', '2026-06-12 14:06:18');

-- --------------------------------------------------------

--
-- Table structure for table `tblpayment_monthly`
--

CREATE TABLE `tblpayment_monthly` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL COMMENT 'FK tblorders.id',
  `userid` int(11) NOT NULL COMMENT 'FK tblclients.id',
  `tagihan_bulan` date NOT NULL COMMENT 'Tanggal 20 bulan tagihan (expire lama)',
  `due_date` date NOT NULL COMMENT 'Batas bayar = tanggal_expire lama (tgl 20)',
  `suspend_date` date NOT NULL COMMENT 'Tanggal nonaktif jika belum bayar (tgl 21)',
  `payment_proof` varchar(255) DEFAULT NULL COMMENT 'Path file bukti bayar bulan ini',
  `status` varchar(20) NOT NULL DEFAULT 'unpaid' COMMENT 'unpaid | waiting_confirm | paid | suspended',
  `confirmed_at` datetime DEFAULT NULL COMMENT 'Waktu admin mengkonfirmasi pembayaran',
  `confirmed_by` int(11) DEFAULT NULL COMMENT 'FK tblclients.id (admin)',
  `new_expire` date DEFAULT NULL COMMENT 'tanggal_expire baru setelah diperpanjang',
  `reminder_sent_at` datetime DEFAULT NULL COMMENT 'Waktu terakhir email reminder dikirim untuk tagihan ini',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tagihan bulanan WiFi — dibuat cron tgl 10, dikonfirmasi admin sebelum tgl 21';

-- --------------------------------------------------------

--
-- Table structure for table `tblproducts`
--

CREATE TABLE `tblproducts` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL DEFAULT '',
  `description` text DEFAULT NULL,
  `da_package` varchar(64) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
  `category` varchar(50) NOT NULL DEFAULT 'other',
  `speed` varchar(50) DEFAULT NULL,
  `period` varchar(20) NOT NULL DEFAULT 'bulan',
  `ready_to_sell` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Daftar produk/paket layanan';

--
-- Dumping data for table `tblproducts`
--

INSERT INTO `tblproducts` (`id`, `name`, `description`, `da_package`, `price`, `status`, `category`, `speed`, `period`, `ready_to_sell`, `created_at`, `updated_at`) VALUES
(1, 'Nextstar Home 15 Mbps', 'Paket internet rumahan stabil untuk kebutuhan browsing, streaming, dan WFH. Cocok untuk 2–4 pengguna bersamaan.', NULL, 185000.00, 1, 'wifi', '15 Mbps', 'bulan', 1, '2026-06-06 10:25:41', '2026-06-06 10:25:41'),
(2, 'Nextstar Home 25 Mbps', 'Paket internet rumahan kecepatan tinggi. Ideal untuk keluarga dengan 4–6 pengguna dan aktivitas streaming HD.', NULL, 255000.00, 1, 'wifi', '25 Mbps', 'bulan', 1, '2026-06-06 10:25:41', '2026-06-06 10:25:41'),
(3, 'Nextstar Bisnis 50 Mbps', 'Paket internet dedicated untuk usaha kecil dan menengah. Stabil, prioritas jaringan, dengan SLA uptime 99%.', NULL, 450000.00, 1, 'wifi', '50 Mbps', 'bulan', 1, '2026-06-06 10:25:41', '2026-06-06 10:25:41'),
(4, 'Nextstar Bisnis 100 Mbps', 'Paket internet bisnis enterprise. Bandwidth penuh, cocok untuk kantor, warnet, atau kafe dengan banyak perangkat.', NULL, 750000.00, 1, 'wifi', '100 Mbps', 'bulan', 0, '2026-06-06 10:25:41', '2026-06-06 10:25:41'),
(5, 'Hosting Starter', 'Hosting website dasar dengan kapasitas Unlimited NVMe, Free Email Hosting, 1 database MySQL, SSL gratis, dan bandwidth unlimited.', NULL, 100000.00, 1, 'hosting', '', 'bulan', 1, '2026-06-06 10:25:41', '2026-06-10 10:27:02'),
(6, 'Hosting Business', 'Hosting performa tinggi dengan Unlimeted NVMe, Unlimited database MySQL, SSL gratis, Free Email Hosting, dan backup harian.', NULL, 200000.00, 1, 'hosting', '', 'bulan', 1, '2026-06-06 10:25:41', '2026-06-10 10:26:48'),
(7, 'Website Company Profile', 'Website profesional 5–7 halaman untuk profil perusahaan. Desain modern, responsif, SEO-friendly, domain .com gratis 1 tahun.', NULL, 2500000.00, 1, 'website', NULL, 'project', 1, '2026-06-06 10:25:41', '2026-06-06 10:25:41'),
(8, 'Website Toko Online (E-Commerce)', 'Website toko online lengkap dengan keranjang belanja, manajemen produk, dan integrasi payment gateway.', NULL, 5000000.00, 1, 'website', NULL, 'project', 0, '2026-06-06 10:25:41', '2026-06-06 10:25:41'),
(9, 'Perakitan PC Workstation', 'Jasa perakitan PC workstation sesuai kebutuhan. Konsultasi spesifikasi, perakitan, instalasi OS & software, garansi jasa.', NULL, 350000.00, 1, 'komputer', NULL, 'unit', 1, '2026-06-06 10:25:41', '2026-06-06 10:25:41'),
(10, 'Servis & Upgrade Laptop/PC', 'Jasa servis, bersih-bersih, upgrade RAM/SSD, dan reinstall sistem operasi. Estimasi pengerjaan 1–2 hari kerja.', NULL, 150000.00, 1, 'komputer', NULL, 'unit', 1, '2026-06-06 10:25:41', '2026-06-06 10:25:41'),
(11, 'Paket CCTV 4 Kamera', 'Pemasangan 4 unit kamera CCTV 2MP Full HD dengan DVR 4 channel, kabel, dan monitor. Garansi alat 1 tahun.', NULL, 3500000.00, 1, 'cctv', NULL, 'unit', 1, '2026-06-06 10:25:41', '2026-06-06 10:25:41'),
(12, 'Paket CCTV 8 Kamera', 'Pemasangan 8 unit kamera CCTV 2MP Full HD dengan DVR 8 channel, HDD 1TB, kabel instalasi lengkap. Garansi 1 tahun.', NULL, 6500000.00, 1, 'cctv', NULL, 'unit', 0, '2026-06-06 10:25:41', '2026-06-06 10:25:41');

-- --------------------------------------------------------

--
-- Table structure for table `tbltickets`
--

CREATE TABLE `tbltickets` (
  `id` int(11) NOT NULL,
  `tid` varchar(10) NOT NULL DEFAULT '' COMMENT 'Ticket ID publik (alphanumeric)',
  `c` varchar(10) NOT NULL DEFAULT '' COMMENT 'Kode departemen/kategori',
  `userid` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL DEFAULT '',
  `body` text DEFAULT NULL COMMENT 'Pesan awal tiket dari klien',
  `status` varchar(50) NOT NULL DEFAULT 'Open' COMMENT 'Open, Answered, Customer-Reply, Closed',
  `lastreply` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tiket dukungan pelanggan';

-- --------------------------------------------------------

--
-- Table structure for table `tblticket_replies`
--

CREATE TABLE `tblticket_replies` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL COMMENT 'FK ke tbltickets.id',
  `userid` int(11) NOT NULL COMMENT 'FK ke tblclients.id (admin atau klien)',
  `sender` enum('admin','client') NOT NULL DEFAULT 'client',
  `message` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Balasan percakapan tiket support';

-- --------------------------------------------------------

--
-- Table structure for table `tbl_cron_logs`
--

CREATE TABLE `tbl_cron_logs` (
  `id` int(11) NOT NULL,
  `cron_name` varchar(100) NOT NULL COMMENT 'Nama cron, misal: cron_hosting_expired',
  `run_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Waktu eksekusi cron',
  `total_found` int(11) NOT NULL DEFAULT 0 COMMENT 'Jumlah order yang memenuhi kriteria',
  `total_deleted` int(11) NOT NULL DEFAULT 0 COMMENT 'Jumlah order yang berhasil dihapus',
  `total_errors` int(11) NOT NULL DEFAULT 0 COMMENT 'Jumlah order yang gagal diproses',
  `detail` text DEFAULT NULL COMMENT 'Detail JSON: daftar order_number, client, alasan, dll'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit log eksekusi cron job (otomatisasi sistem)';

-- --------------------------------------------------------

--
-- Table structure for table `tbl_product_logs`
--

CREATE TABLE `tbl_product_logs` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL COMMENT 'ID produk saat aksi (bisa sudah dihapus)',
  `product_name` varchar(255) NOT NULL DEFAULT '' COMMENT 'Snapshot nama produk saat aksi',
  `action_type` varchar(20) NOT NULL DEFAULT '' COMMENT 'tambah | edit | hapus | aktifkan | nonaktifkan',
  `admin_id` int(11) NOT NULL COMMENT 'FK ke tblclients.id',
  `admin_name` varchar(255) NOT NULL DEFAULT '' COMMENT 'Snapshot nama admin saat aksi',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Riwayat aksi CRUD produk oleh admin';

--
-- Dumping data for table `tbl_product_logs`
--

INSERT INTO `tbl_product_logs` (`id`, `product_id`, `product_name`, `action_type`, `admin_id`, `admin_name`, `created_at`) VALUES
(1, 5, 'Hosting Starter', 'edit', 1, 'Amirul Fuad', '2026-06-10 10:24:35'),
(2, 5, 'Hosting Starter', 'edit', 1, 'Amirul Fuad', '2026-06-10 10:25:26'),
(3, 5, 'Hosting Starter', 'edit', 1, 'Amirul Fuad', '2026-06-10 10:25:35'),
(4, 6, 'Hosting Business', 'edit', 1, 'Amirul Fuad', '2026-06-10 10:26:20'),
(5, 6, 'Hosting Business', 'edit', 1, 'Amirul Fuad', '2026-06-10 10:26:48'),
(6, 5, 'Hosting Starter', 'edit', 1, 'Amirul Fuad', '2026-06-10 10:27:02');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tblannouncements`
--
ALTER TABLE `tblannouncements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_date` (`date`),
  ADD KEY `idx_published` (`published`);

--
-- Indexes for table `tblclients`
--
ALTER TABLE `tblclients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_email` (`email`),
  ADD KEY `idx_level` (`level`),
  ADD KEY `idx_country` (`country`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `tbldomains`
--
ALTER TABLE `tbldomains`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_userid` (`userid`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `tblhosting`
--
ALTER TABLE `tblhosting`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_userid` (`userid`),
  ADD KEY `idx_domainstatus` (`domainstatus`),
  ADD KEY `idx_nextduedate` (`nextduedate`),
  ADD KEY `idx_packageid` (`packageid`),
  ADD KEY `idx_payment_deadline` (`payment_deadline`);

--
-- Indexes for table `tblinvoices`
--
ALTER TABLE `tblinvoices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_userid` (`userid`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_order_id` (`order_id`);

--
-- Indexes for table `tblnotifikasi`
--
ALTER TABLE `tblnotifikasi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_userid` (`userid`),
  ADD KEY `idx_order_id` (`order_id`),
  ADD KEY `idx_dibaca` (`sudah_dibaca`);

--
-- Indexes for table `tblorders`
--
ALTER TABLE `tblorders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_order_number` (`order_number`),
  ADD KEY `idx_userid` (`userid`),
  ADD KEY `idx_productid` (`productid`),
  ADD KEY `idx_wifi_status` (`wifi_status`),
  ADD KEY `idx_teknisi_id` (`teknisi_id`),
  ADD KEY `idx_order_type` (`order_type`),
  ADD KEY `idx_wifi_expire` (`wifi_status`,`order_type`,`tanggal_expire`),
  ADD KEY `idx_payment_deadline` (`payment_deadline`);

--
-- Indexes for table `tblorder_status_logs`
--
ALTER TABLE `tblorder_status_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_id` (`order_id`),
  ADD KEY `idx_changed_by` (`changed_by`);

--
-- Indexes for table `tblpayment_monthly`
--
ALTER TABLE `tblpayment_monthly`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_order_bulan` (`order_id`,`tagihan_bulan`),
  ADD KEY `idx_userid` (`userid`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_due_date` (`due_date`),
  ADD KEY `idx_suspend_date` (`suspend_date`),
  ADD KEY `idx_reminder_sent` (`reminder_sent_at`);

--
-- Indexes for table `tblproducts`
--
ALTER TABLE `tblproducts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tbltickets`
--
ALTER TABLE `tbltickets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_tid` (`tid`),
  ADD KEY `idx_userid` (`userid`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_lastreply` (`lastreply`);

--
-- Indexes for table `tblticket_replies`
--
ALTER TABLE `tblticket_replies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ticket_id` (`ticket_id`),
  ADD KEY `idx_userid` (`userid`);

--
-- Indexes for table `tbl_cron_logs`
--
ALTER TABLE `tbl_cron_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cron_name` (`cron_name`),
  ADD KEY `idx_run_at` (`run_at`);

--
-- Indexes for table `tbl_product_logs`
--
ALTER TABLE `tbl_product_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_admin_id` (`admin_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tblannouncements`
--
ALTER TABLE `tblannouncements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblclients`
--
ALTER TABLE `tblclients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `tbldomains`
--
ALTER TABLE `tbldomains`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblhosting`
--
ALTER TABLE `tblhosting`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tblinvoices`
--
ALTER TABLE `tblinvoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblnotifikasi`
--
ALTER TABLE `tblnotifikasi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `tblorders`
--
ALTER TABLE `tblorders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `tblorder_status_logs`
--
ALTER TABLE `tblorder_status_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `tblpayment_monthly`
--
ALTER TABLE `tblpayment_monthly`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblproducts`
--
ALTER TABLE `tblproducts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `tbltickets`
--
ALTER TABLE `tbltickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tblticket_replies`
--
ALTER TABLE `tblticket_replies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_cron_logs`
--
ALTER TABLE `tbl_cron_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_product_logs`
--
ALTER TABLE `tbl_product_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
