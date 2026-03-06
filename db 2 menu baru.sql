CREATE TABLE IF NOT EXISTS `tlembur` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `deskripsi` text NOT NULL,
  `latarbelakang` text NOT NULL,
  `iduser_pengaju` varchar(15) NOT NULL,
  `tgl_lembur` date NOT NULL,
  `jam_mulai` time NOT NULL,
  `jam_selesai` time NOT NULL,
  `tgl_pengajuan` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `iduser_pengaju` (`iduser_pengaju`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tdtllembur` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idlembur` int(11) NOT NULL,
  `iduser_pegawai` varchar(15) NOT NULL,
  `tugas` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idlembur` (`idlembur`),
  KEY `iduser_pegawai` (`iduser_pegawai`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ttanggungjawab_lembur` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idlembur` int(11) NOT NULL,
  `status_lembur` int(1) NOT NULL,
  `kesimpulan` text NOT NULL,
  `foto` text NULL,
  `iduser_pelapor` varchar(15) NOT NULL,
  `tgl_lapor` datetime NOT NULL,
  `tgl_update` datetime NULL,
  PRIMARY KEY (`id`),
  KEY `idlembur` (`idlembur`),
  KEY `iduser_pelapor` (`iduser_pelapor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `tgaji` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `iduser_pegawai` varchar(15) NOT NULL COMMENT 'FK ke ruser.iduser',
  `periode` varchar(7) NOT NULL COMMENT 'Format: YYYY-MM',
  `gaji_pokok` decimal(15,2) DEFAULT 0.00,
  `tunj_jabatan` decimal(15,2) DEFAULT 0.00,
  `tunj_perjalanan` decimal(15,2) DEFAULT 0.00,
  `lembur` decimal(15,2) DEFAULT 0.00,
  `bonus` decimal(15,2) DEFAULT 0.00,
  `bpjs_tk` decimal(15,2) DEFAULT 0.00,
  `bpjs_kesehatan` decimal(15,2) DEFAULT 0.00,
  `pot_pinjaman` decimal(15,2) DEFAULT 0.00,
  `pot_lain` decimal(15,2) DEFAULT 0.00,
  `total_terima` decimal(15,2) DEFAULT 0.00,
  `status_gaji` enum('Draft','Generated','Paid') DEFAULT 'Draft',
  `tgl_input` datetime DEFAULT NULL,
  `tgl_update` datetime DEFAULT NULL,
  `tgl_generate` datetime DEFAULT NULL,
  `tgl_bayar` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pegawai` (`iduser_pegawai`),
  KEY `idx_periode` (`periode`),
  KEY `idx_status` (`status_gaji`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- cascade delete
ALTER TABLE tdtllembur
ADD CONSTRAINT fk_tdtllembur_lembur
FOREIGN KEY (idlembur)
REFERENCES tlembur(id)
ON DELETE CASCADE;