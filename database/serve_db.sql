-- ============================================================================
-- DATABASE SCHEMA: SERVE (Service Marketplace & Management System)
-- Engine: InnoDB | Charset: utf8mb4_unicode_ci
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `serve_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `serve_db`;

SET NAMES utf8mb4;
SET time_zone = '+07:00';

-- ----------------------------------------------------------------------------
-- 1. Tabel users (Admin, Penyedia Jasa, Pelanggan)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama_lengkap` VARCHAR(120) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'penyedia', 'pelanggan') NOT NULL DEFAULT 'pelanggan',
  `no_telepon` VARCHAR(25) DEFAULT NULL,
  `alamat` TEXT DEFAULT NULL,
  `foto_profil` VARCHAR(255) DEFAULT NULL,
  `bio_penyedia` TEXT DEFAULT NULL,
  `pengalaman_tahun` SMALLINT UNSIGNED DEFAULT 0,
  `status_akun` ENUM('aktif', 'nonaktif') NOT NULL DEFAULT 'aktif',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_email` (`email`),
  KEY `idx_users_role_status` (`role`, `status_akun`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2. Tabel kategori_jasa
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `kategori_jasa` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama_kategori` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(120) NOT NULL,
  `deskripsi` TEXT DEFAULT NULL,
  `ikon` VARCHAR(100) DEFAULT 'bi-grid',
  `status_kategori` ENUM('aktif', 'nonaktif') NOT NULL DEFAULT 'aktif',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_kategori_nama` (`nama_kategori`),
  UNIQUE KEY `uk_kategori_slug` (`slug`),
  KEY `idx_kategori_status` (`status_kategori`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 3. Tabel layanan
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `layanan` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `penyedia_id` INT UNSIGNED NOT NULL,
  `kategori_id` INT UNSIGNED NOT NULL,
  `judul_layanan` VARCHAR(150) NOT NULL,
  `deskripsi` TEXT NOT NULL,
  `harga` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `estimasi_hari` INT UNSIGNED NOT NULL DEFAULT 1,
  `gambar_layanan` VARCHAR(255) DEFAULT NULL,
  `status_layanan` ENUM('aktif', 'nonaktif') NOT NULL DEFAULT 'aktif',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_layanan_penyedia` (`penyedia_id`),
  KEY `idx_layanan_kategori` (`kategori_id`),
  KEY `idx_layanan_status` (`status_layanan`),
  CONSTRAINT `fk_layanan_penyedia`
    FOREIGN KEY (`penyedia_id`) REFERENCES `users` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_layanan_kategori`
    FOREIGN KEY (`kategori_id`) REFERENCES `kategori_jasa` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 4. Tabel portofolio
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `portofolio` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `penyedia_id` INT UNSIGNED NOT NULL,
  `kategori_id` INT UNSIGNED DEFAULT NULL,
  `judul_karya` VARCHAR(150) NOT NULL,
  `deskripsi` TEXT DEFAULT NULL,
  `file_gambar` VARCHAR(255) NOT NULL,
  `tautan_eksternal` VARCHAR(255) DEFAULT NULL,
  `tanggal_selesai` DATE DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_portofolio_penyedia` (`penyedia_id`),
  KEY `idx_portofolio_kategori` (`kategori_id`),
  CONSTRAINT `fk_portofolio_penyedia`
    FOREIGN KEY (`penyedia_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_portofolio_kategori`
    FOREIGN KEY (`kategori_id`) REFERENCES `kategori_jasa` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 5. Tabel pemesanan
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pemesanan` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode_pesanan` VARCHAR(30) NOT NULL,
  `pelanggan_id` INT UNSIGNED NOT NULL,
  `penyedia_id` INT UNSIGNED NOT NULL,
  `layanan_id` INT UNSIGNED NOT NULL,
  `deskripsi_kebutuhan` TEXT NOT NULL,
  `tanggal_pengerjaan` DATE NOT NULL,
  `alamat_lokasi` TEXT DEFAULT NULL,
  `total_biaya` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `status_terkini` ENUM(
    'menunggu_konfirmasi',
    'diterima',
    'ditolak',
    'menunggu_pembayaran',
    'sedang_dikerjakan',
    'selesai',
    'dibatalkan'
  ) NOT NULL DEFAULT 'menunggu_konfirmasi',
  `catatan_penyedia` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pemesanan_kode` (`kode_pesanan`),
  KEY `idx_pemesanan_pelanggan` (`pelanggan_id`),
  KEY `idx_pemesanan_penyedia` (`penyedia_id`),
  KEY `idx_pemesanan_layanan` (`layanan_id`),
  KEY `idx_pemesanan_status` (`status_terkini`),
  KEY `idx_pemesanan_created` (`created_at`),
  CONSTRAINT `fk_pemesanan_pelanggan`
    FOREIGN KEY (`pelanggan_id`) REFERENCES `users` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_pemesanan_penyedia`
    FOREIGN KEY (`penyedia_id`) REFERENCES `users` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_pemesanan_layanan`
    FOREIGN KEY (`layanan_id`) REFERENCES `layanan` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 6. Tabel pembayaran
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pembayaran` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pemesanan_id` INT UNSIGNED NOT NULL,
  `pelanggan_id` INT UNSIGNED NOT NULL,
  `metode_pembayaran` ENUM('transfer_bank', 'e_wallet', 'tunai') NOT NULL DEFAULT 'transfer_bank',
  `nama_pengirim` VARCHAR(120) DEFAULT NULL,
  `nominal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `tanggal_bayar` DATETIME NOT NULL,
  `bukti_pembayaran` VARCHAR(255) NOT NULL,
  `status_pembayaran` ENUM('menunggu_verifikasi', 'valid', 'tidak_valid') NOT NULL DEFAULT 'menunggu_verifikasi',
  `catatan_admin` TEXT DEFAULT NULL,
  `diverifikasi_oleh` INT UNSIGNED DEFAULT NULL,
  `tanggal_verifikasi` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pembayaran_pemesanan` (`pemesanan_id`),
  KEY `idx_pembayaran_pelanggan` (`pelanggan_id`),
  KEY `idx_pembayaran_status` (`status_pembayaran`),
  KEY `idx_pembayaran_verifier` (`diverifikasi_oleh`),
  CONSTRAINT `fk_pembayaran_pemesanan`
    FOREIGN KEY (`pemesanan_id`) REFERENCES `pemesanan` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_pembayaran_pelanggan`
    FOREIGN KEY (`pelanggan_id`) REFERENCES `users` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_pembayaran_verifier`
    FOREIGN KEY (`diverifikasi_oleh`) REFERENCES `users` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 7. Tabel ulasan
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ulasan` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pemesanan_id` INT UNSIGNED NOT NULL,
  `pelanggan_id` INT UNSIGNED NOT NULL,
  `penyedia_id` INT UNSIGNED NOT NULL,
  `layanan_id` INT UNSIGNED NOT NULL,
  `rating` TINYINT UNSIGNED NOT NULL,
  `komentar` TEXT NOT NULL,
  `status_ulasan` ENUM('tampil', 'disembunyikan') NOT NULL DEFAULT 'tampil',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ulasan_pemesanan` (`pemesanan_id`),
  KEY `idx_ulasan_pelanggan` (`pelanggan_id`),
  KEY `idx_ulasan_penyedia` (`penyedia_id`),
  KEY `idx_ulasan_layanan` (`layanan_id`),
  KEY `idx_ulasan_status` (`status_ulasan`),
  CONSTRAINT `chk_ulasan_rating` CHECK (`rating` BETWEEN 1 AND 5),
  CONSTRAINT `fk_ulasan_pemesanan`
    FOREIGN KEY (`pemesanan_id`) REFERENCES `pemesanan` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_ulasan_pelanggan`
    FOREIGN KEY (`pelanggan_id`) REFERENCES `users` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_ulasan_penyedia`
    FOREIGN KEY (`penyedia_id`) REFERENCES `users` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_ulasan_layanan`
    FOREIGN KEY (`layanan_id`) REFERENCES `layanan` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 8. Tabel status_pemesanan (Riwayat perubahan status)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `status_pemesanan` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pemesanan_id` INT UNSIGNED NOT NULL,
  `status_lama` ENUM(
    'menunggu_konfirmasi',
    'diterima',
    'ditolak',
    'menunggu_pembayaran',
    'sedang_dikerjakan',
    'selesai',
    'dibatalkan'
  ) DEFAULT NULL,
  `status_baru` ENUM(
    'menunggu_konfirmasi',
    'diterima',
    'ditolak',
    'menunggu_pembayaran',
    'sedang_dikerjakan',
    'selesai',
    'dibatalkan'
  ) NOT NULL,
  `diubah_oleh` INT UNSIGNED NOT NULL,
  `keterangan` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status_pemesanan_order` (`pemesanan_id`),
  KEY `idx_status_pemesanan_user` (`diubah_oleh`),
  CONSTRAINT `fk_status_pemesanan_order`
    FOREIGN KEY (`pemesanan_id`) REFERENCES `pemesanan` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_status_pemesanan_user`
    FOREIGN KEY (`diubah_oleh`) REFERENCES `users` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 9. Tabel notifikasi
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifikasi` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `pemesanan_id` INT UNSIGNED DEFAULT NULL,
  `judul` VARCHAR(150) NOT NULL,
  `pesan` TEXT NOT NULL,
  `tautan` VARCHAR(255) DEFAULT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notifikasi_user_read` (`user_id`, `is_read`),
  KEY `idx_notifikasi_pemesanan` (`pemesanan_id`),
  CONSTRAINT `fk_notifikasi_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_notifikasi_pemesanan`
    FOREIGN KEY (`pemesanan_id`) REFERENCES `pemesanan` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- DATA AWAL: Akun Admin & Kategori Jasa
-- Akun Admin Uji Coba:
--   Email    : admin@serve.local
--   Password : AdminServe#2026
-- ============================================================================
INSERT INTO `users` (
  `nama_lengkap`, `email`, `password`, `role`, `no_telepon`, `alamat`, `status_akun`
) VALUES (
  'Administrator SERVE',
  'admin@serve.local',
  '$2y$10$w2490xV9K2g7818hG.D.k.v88aI/eH/O166yS3137/R.T37e7k2W6',
  'admin',
  '081200000000',
  'Kantor Pusat SERVE',
  'aktif'
) ON DUPLICATE KEY UPDATE `nama_lengkap` = VALUES(`nama_lengkap`);

INSERT INTO `kategori_jasa` (`nama_kategori`, `slug`, `deskripsi`, `ikon`, `status_kategori`) VALUES
  ('Desain Grafis', 'desain-grafis', 'Pembuatan logo, identitas merek, poster, banner, kemasan produk, dan konten media sosial.', 'bi-palette', 'aktif'),
  ('Editing Video', 'editing-video', 'Penyuntingan video YouTube, iklan promosi, reels/TikTok, color grading, dan motion graphic.', 'bi-film', 'aktif'),
  ('Fotografi', 'fotografi', 'Jasa foto produk, acara/event, potret profil, dan dokumentasi kebutuhan bisnis.', 'bi-camera', 'aktif'),
  ('Servis Komputer', 'servis-komputer', 'Perbaikan laptop dan PC, instalasi OS, pembersihan hardware, upgrade komponen, dan pemulihan data.', 'bi-pc-display', 'aktif'),
  ('Pembuatan Website', 'pembuatan-website', 'Pengembangan landing page, website profil usaha, sistem informasi, dan toko online responsif.', 'bi-code-slash', 'aktif')
ON DUPLICATE KEY UPDATE
  `deskripsi` = VALUES(`deskripsi`),
  `ikon` = VALUES(`ikon`),
  `status_kategori` = VALUES(`status_kategori`);