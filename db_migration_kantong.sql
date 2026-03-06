-- Migration untuk fitur Kantong di Keuangan
-- Tanggal: 2026-03-06

-- Buat tabel kantong
CREATE TABLE IF NOT EXISTS `tkantong` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `id_kantong` VARCHAR(20) NOT NULL UNIQUE,
  `nama_kantong` VARCHAR(100) NOT NULL,
  `deskripsi` TEXT,
  `history_kantong` TEXT,
  `created_by` VARCHAR(15) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_id_kantong` (`id_kantong`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tambah kolom id_kantong ke tabel tkas
ALTER TABLE `tkas` ADD COLUMN `id_kantong` VARCHAR(20) DEFAULT NULL AFTER `notransaksi`;

-- Insert data kantong default
INSERT IGNORE INTO `tkantong` (`id_kantong`, `nama_kantong`, `deskripsi`, `history_kantong`, `created_by`) VALUES
('KANTONG001', 'Kantong Utama', 'Kantong utama untuk transaksi umum', 'Dibuat sebagai kantong default', 'admin'),
('KANTONG002', 'Kantong Operasional', 'Kantong untuk biaya operasional', 'Dibuat untuk keperluan operasional', 'admin');