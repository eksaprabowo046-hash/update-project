SET FOREIGN_KEY_CHECKS = 0;

-- =============================================
-- TABEL BARU
-- =============================================

-- TABEL: tbl_jabatan
CREATE TABLE IF NOT EXISTS `tbl_jabatan` (
  `kodjab` INT(11) NOT NULL,
  `nama_jabatan` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`kodjab`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT IGNORE INTO `tbl_jabatan` (`kodjab`, `nama_jabatan`) VALUES
(1, 'Direktur'),
(2, 'General Manager'),
(3, 'Manager Marketing dan Bisnis'),
(4, 'Manager Produksi'),
(5, 'Staff');


-- TABEL: tpinjaman
CREATE TABLE IF NOT EXISTS `tpinjaman` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `iduser_pemohon` VARCHAR(20) NOT NULL,
  `alamat` VARCHAR(255) NULL,
  `jabatan_pemohon` VARCHAR(100) NULL,
  `no_telp` VARCHAR(20) NULL,
  `nominal` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `keperluan` TEXT NOT NULL,
  `tenor` INT(11) NOT NULL DEFAULT 1,
  `cicilan_perbulan` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `tgl_pengajuan` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `status_approval` ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `catatan_approval` VARCHAR(255) NULL,
  `tgl_approval` DATETIME NULL,
  `iduser_approval` VARCHAR(20) NULL,
  `status_lunas` ENUM('Belum','Lunas') NOT NULL DEFAULT 'Belum',
  PRIMARY KEY (`id`),
  INDEX `idx_pemohon` (`iduser_pemohon`),
  INDEX `idx_status` (`status_approval`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- TABEL: tagenda
CREATE TABLE IF NOT EXISTS `tagenda` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tanggal` date NOT NULL,
  `jam` time DEFAULT NULL COMMENT 'Jam kegiatan (opsional)',
  `tempat_kunjungan` varchar(255) NOT NULL,
  `agenda` text NOT NULL,
  `peserta` text NOT NULL COMMENT 'Nama-nama yang terlibat, dipisahkan koma',
  `dokumen` varchar(255) DEFAULT NULL COMMENT 'Nama file dokumen yang diupload',
  `created_by` varchar(50) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tanggal` (`tanggal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- TABEL: tdokumen_about
CREATE TABLE IF NOT EXISTS `tdokumen_about` (
  `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nama_dokumen` VARCHAR(255) NOT NULL,
  `nama_file` VARCHAR(255) NOT NULL,
  `divisi` VARCHAR(100) NOT NULL DEFAULT '',
  `uploaded_by` VARCHAR(50),
  `tgl_upload` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


-- TABEL: tbl_hak_akses
CREATE TABLE IF NOT EXISTS `tbl_hak_akses` (
  `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `iduser` VARCHAR(50) NOT NULL,
  `menu_nama` VARCHAR(100) NOT NULL,
  `aktif` TINYINT(1) DEFAULT 1 COMMENT '1=boleh akses, 0=tidak',
  UNIQUE KEY `unik_user_menu` (`iduser`, `menu_nama`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- TABEL: setting_hak_akses (master daftar menu)
CREATE TABLE IF NOT EXISTS `setting_hak_akses` (
  `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `menu_nama` VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- tambah UNIQUE KEY jika belum ada
DROP PROCEDURE IF EXISTS add_uq_setting_hak_akses;
DELIMITER //
CREATE PROCEDURE add_uq_setting_hak_akses()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'setting_hak_akses'
      AND INDEX_NAME = 'unik_menu'
  ) THEN
    ALTER TABLE `setting_hak_akses` ADD UNIQUE KEY `unik_menu` (`menu_nama`);
  END IF;
END //
DELIMITER ;
CALL add_uq_setting_hak_akses();
DROP PROCEDURE IF EXISTS add_uq_setting_hak_akses;

-- isi daftar menu
INSERT IGNORE INTO `setting_hak_akses` (menu_nama) VALUES
('Log'), ('Input User'), ('Implementasi'), ('Invoice'),
('Laporan Log'), ('Laporan Invoice'), ('Laporan Kehadiran'),
('Perizinan'), ('Lembur'), ('Penggajian'), ('Keuangan'),
('About'), ('Kalender'), ('Pinjaman'), ('Customer'), ('Dashboard');

DELETE FROM `setting_hak_akses` WHERE `menu_nama` = 'Hak Akses';

-- default hak akses Admin: semua menu aktif
INSERT INTO `tbl_hak_akses` (iduser, menu_nama, aktif)
SELECT r.iduser, s.menu_nama, 1
FROM ruser r CROSS JOIN setting_hak_akses s
WHERE r.kodjab = 1
ON DUPLICATE KEY UPDATE aktif = aktif;

-- default hak akses non-Admin: beberapa menu keuangan dinonaktifkan
INSERT INTO `tbl_hak_akses` (iduser, menu_nama, aktif)
SELECT r.iduser, s.menu_nama,
    CASE
        WHEN s.menu_nama IN ('Invoice', 'Laporan Invoice', 'Penggajian', 'Keuangan') THEN 0
        ELSE 1
    END
FROM ruser r CROSS JOIN setting_hak_akses s
WHERE r.kodjab != 1 AND s.menu_nama != 'Input User'
ON DUPLICATE KEY UPDATE aktif = aktif;


-- =============================================
-- ALTER TABLE: tambah kolom baru ke tabel existing
-- =============================================

-- ruser: kolom divisi
DROP PROCEDURE IF EXISTS add_col_ruser_divisi;
DELIMITER //
CREATE PROCEDURE add_col_ruser_divisi()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ruser' AND COLUMN_NAME = 'divisi'
  ) THEN
    ALTER TABLE `ruser` ADD COLUMN `divisi` VARCHAR(100) AFTER `kodjab`;
  END IF;
END //
DELIMITER ;
CALL add_col_ruser_divisi();
DROP PROCEDURE IF EXISTS add_col_ruser_divisi;

-- ruser: kolom tgl_masuk (setelah divisi)
DROP PROCEDURE IF EXISTS add_col_tgl_masuk;
DELIMITER //
CREATE PROCEDURE add_col_tgl_masuk()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ruser' AND COLUMN_NAME = 'tgl_masuk'
  ) THEN
    ALTER TABLE `ruser` ADD COLUMN `tgl_masuk` DATE NULL AFTER `divisi`;
  END IF;
END //
DELIMITER ;
CALL add_col_tgl_masuk();
DROP PROCEDURE IF EXISTS add_col_tgl_masuk;

-- ruser: kolom nik
DROP PROCEDURE IF EXISTS add_col_ruser_nik;
DELIMITER //
CREATE PROCEDURE add_col_ruser_nik()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ruser' AND COLUMN_NAME = 'nik'
  ) THEN
    ALTER TABLE `ruser` ADD COLUMN `nik` VARCHAR(50) NULL AFTER `nama`;
  END IF;
END //
DELIMITER ;
CALL add_col_ruser_nik();
DROP PROCEDURE IF EXISTS add_col_ruser_nik;

-- ruser: kolom bank
DROP PROCEDURE IF EXISTS add_col_ruser_bank;
DELIMITER //
CREATE PROCEDURE add_col_ruser_bank()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ruser' AND COLUMN_NAME = 'bank'
  ) THEN
    ALTER TABLE `ruser` ADD COLUMN `bank` VARCHAR(100) NULL AFTER `nik`;
  END IF;
END //
DELIMITER ;
CALL add_col_ruser_bank();
DROP PROCEDURE IF EXISTS add_col_ruser_bank;

-- tpinjaman: kolom alamat, jabatan_pemohon, no_telp (jika tabel sudah ada tapi kolom belum)
DROP PROCEDURE IF EXISTS add_col_tpinjaman_alamat;
DELIMITER //
CREATE PROCEDURE add_col_tpinjaman_alamat()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tpinjaman' AND COLUMN_NAME = 'alamat'
  ) THEN
    ALTER TABLE `tpinjaman`
      ADD COLUMN `alamat` VARCHAR(255) NULL AFTER `iduser_pemohon`,
      ADD COLUMN `jabatan_pemohon` VARCHAR(100) NULL AFTER `alamat`,
      ADD COLUMN `no_telp` VARCHAR(20) NULL AFTER `jabatan_pemohon`;
  END IF;
END //
DELIMITER ;
CALL add_col_tpinjaman_alamat();
DROP PROCEDURE IF EXISTS add_col_tpinjaman_alamat;

-- tdokumen_about: migrasi ENUM ke VARCHAR (jika masih ENUM)
DROP PROCEDURE IF EXISTS migrate_tdokumen_divisi;
DELIMITER //
CREATE PROCEDURE migrate_tdokumen_divisi()
BEGIN
  DECLARE col_type VARCHAR(200);
  SELECT COLUMN_TYPE INTO col_type
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tdokumen_about' AND COLUMN_NAME = 'divisi';

  IF col_type LIKE 'enum%' THEN
    ALTER TABLE `tdokumen_about` MODIFY COLUMN `divisi` VARCHAR(100) NOT NULL DEFAULT '';
    UPDATE `tdokumen_about` SET divisi = 'Dokumen Internal' WHERE divisi = 'Internal';
    UPDATE `tdokumen_about` SET divisi = 'Dokumen Marketing' WHERE divisi = 'Marketing';
    UPDATE `tdokumen_about` SET divisi = 'Dokumen Produksi'  WHERE divisi = 'Produksi';
  END IF;

  UPDATE `tdokumen_about` SET divisi = 'Dokumen Internal' WHERE divisi IS NULL OR TRIM(divisi) = '';
  UPDATE `tdokumen_about` SET divisi = REPLACE(divisi, 'SOP ', 'Dokumen ') WHERE divisi LIKE 'SOP %';
END //
DELIMITER ;
CALL migrate_tdokumen_divisi();
DROP PROCEDURE IF EXISTS migrate_tdokumen_divisi;

SET FOREIGN_KEY_CHECKS = 1;
