-- ===========================================
-- 05/03/2026
-- ===========================================

-- 1. Tabel Saldo Awal Kas
CREATE TABLE IF NOT EXISTS `tkas_saldo_awal` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `bulan` INT(2) NOT NULL,
  `tahun` INT(4) NOT NULL,
  `saldo_awal` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `keterangan` VARCHAR(255) NULL,
  `iduser_input` VARCHAR(20) NOT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unik_bulan_tahun` (`bulan`, `tahun`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. Subtask: Tambah kolom id_parent di tlog untuk relasi parent-child
ALTER TABLE tlog ADD COLUMN IF NOT EXISTS `id_parent` INT DEFAULT NULL;


-- ===========================================
-- [TAMBAH TANGGAL BARU]
-- ===========================================
