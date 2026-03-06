SET sql_mode = '';
/*
Navicat MySQL Data Transfer

Source Server         : LOCALHOST
Source Server Version : 50505
Source Host           : localhost:3306
Source Database       : logklikdsikosong

Target Server Type    : MYSQL
Target Server Version : 50505
File Encoding         : 65001

Date: 2026-03-06 09:38:02
*/

SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------
-- Table structure for `rcustomer`
-- ----------------------------
DROP TABLE IF EXISTS `rcustomer`;
CREATE TABLE `rcustomer` (
  `kodcustomer` varchar(20) NOT NULL DEFAULT '',
  `nmcustomer` varchar(75) NOT NULL DEFAULT '',
  `almtcustomer` varchar(200) NOT NULL DEFAULT '',
  `piutang` double NOT NULL DEFAULT '0',
  `cp` varchar(50) NOT NULL DEFAULT '',
  `telp` varchar(40) NOT NULL,
  `fax` varchar(30) NOT NULL DEFAULT '',
  `email` varchar(100) NOT NULL,
  `npwp` varchar(50) NOT NULL,
  `status` tinyint(4) NOT NULL DEFAULT '0',
  `iduser` varchar(15) NOT NULL,
  `nmwpajak` varchar(50) NOT NULL,
  PRIMARY KEY (`kodcustomer`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- ----------------------------
-- Records of rcustomer
-- ----------------------------

-- ----------------------------
-- Table structure for `ruser`
-- ----------------------------
DROP TABLE IF EXISTS `ruser`;
CREATE TABLE `ruser` (
  `iduser` varchar(15) NOT NULL DEFAULT '',
  `passwd` varchar(15) NOT NULL DEFAULT '',
  `nama` varchar(50) NOT NULL DEFAULT '',
  `nik` varchar(50) DEFAULT NULL,
  `bank` varchar(100) DEFAULT NULL,
  `inisial` varchar(4) NOT NULL,
  `stsaktif` tinyint(1) NOT NULL DEFAULT '0',
  `isLogin` tinyint(1) NOT NULL DEFAULT '0',
  `kodjab` smallint(6) NOT NULL DEFAULT '0',
  `divisi` varchar(100) DEFAULT NULL,
  `tgl_masuk` date DEFAULT NULL,
  `file_kontrak` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`iduser`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- ----------------------------
-- Records of ruser
-- ----------------------------

-- ----------------------------
-- Table structure for `setting_hak_akses`
-- ----------------------------
DROP TABLE IF EXISTS `setting_hak_akses`;
CREATE TABLE `setting_hak_akses` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `menu_nama` varchar(100) NOT NULL,
  `akses_admin` tinyint(1) DEFAULT '1',
  `akses_staff` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unik_menu` (`menu_nama`)
) ENGINE=InnoDB AUTO_INCREMENT=2077 DEFAULT CHARSET=latin1;

-- ----------------------------
-- Records of setting_hak_akses
-- ----------------------------

-- ----------------------------
-- Table structure for `tagenda`
-- ----------------------------
DROP TABLE IF EXISTS `tagenda`;
CREATE TABLE `tagenda` (
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
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Records of tagenda
-- ----------------------------

-- ----------------------------
-- Table structure for `takun`
-- ----------------------------
DROP TABLE IF EXISTS `takun`;
CREATE TABLE `takun` (
  `kodakun` varchar(20) NOT NULL,
  `kodgroup` int(11) NOT NULL,
  `nmakun` varchar(100) NOT NULL,
  `tipe` enum('H','D') NOT NULL DEFAULT 'D',
  PRIMARY KEY (`kodakun`),
  KEY `fk_takun_tgroup_idx` (`kodgroup`),
  CONSTRAINT `takun_ibfk_1` FOREIGN KEY (`kodgroup`) REFERENCES `tgroup_akun` (`kodgroup`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Records of takun
-- ----------------------------

-- ----------------------------
-- Table structure for `tbl_divisi`
-- ----------------------------
DROP TABLE IF EXISTS `tbl_divisi`;
CREATE TABLE `tbl_divisi` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `nama_divisi` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Records of tbl_divisi
-- ----------------------------

-- ----------------------------
-- Table structure for `tbl_hak_akses`
-- ----------------------------
DROP TABLE IF EXISTS `tbl_hak_akses`;
CREATE TABLE `tbl_hak_akses` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `iduser` varchar(50) NOT NULL,
  `menu_nama` varchar(100) NOT NULL,
  `aktif` tinyint(1) DEFAULT '1' COMMENT '1=boleh akses, 0=tidak',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unik_user_menu` (`iduser`,`menu_nama`)
) ENGINE=InnoDB AUTO_INCREMENT=739 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of tbl_hak_akses
-- ----------------------------

-- ----------------------------
-- Table structure for `tbl_jabatan`
-- ----------------------------
DROP TABLE IF EXISTS `tbl_jabatan`;
CREATE TABLE `tbl_jabatan` (
  `kodjab` int(11) NOT NULL,
  `nama_jabatan` varchar(100) NOT NULL,
  PRIMARY KEY (`kodjab`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of tbl_jabatan
-- ----------------------------

-- ----------------------------
-- Table structure for `tdokumen_about`
-- ----------------------------
DROP TABLE IF EXISTS `tdokumen_about`;
CREATE TABLE `tdokumen_about` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `nama_dokumen` varchar(255) NOT NULL,
  `nama_file` varchar(255) NOT NULL,
  `divisi` varchar(100) NOT NULL DEFAULT '',
  `uploaded_by` varchar(50) DEFAULT NULL,
  `tgl_upload` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of tdokumen_about
-- ----------------------------

-- ----------------------------
-- Table structure for `tdtllembur`
-- ----------------------------
DROP TABLE IF EXISTS `tdtllembur`;
CREATE TABLE `tdtllembur` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idlembur` int(11) NOT NULL,
  `iduser_pegawai` varchar(15) NOT NULL,
  `tugas` text NOT NULL,
  `target` text,
  `kodcustomer` varchar(15) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idlembur` (`idlembur`),
  KEY `iduser_pegawai` (`iduser_pegawai`),
  CONSTRAINT `tdtllembur_ibfk_1` FOREIGN KEY (`idlembur`) REFERENCES `tlembur` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=77 DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Records of tdtllembur
-- ----------------------------

-- ----------------------------
-- Table structure for `tgaji`
-- ----------------------------
DROP TABLE IF EXISTS `tgaji`;
CREATE TABLE `tgaji` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `iduser_pegawai` varchar(15) NOT NULL COMMENT 'FK ke ruser.iduser',
  `periode` varchar(7) NOT NULL COMMENT 'Format: YYYY-MM',
  `gaji_pokok` decimal(15,2) DEFAULT '0.00',
  `tunj_jabatan` decimal(15,2) DEFAULT '0.00',
  `tunj_perjalanan` decimal(15,2) DEFAULT '0.00',
  `lembur` decimal(15,2) DEFAULT '0.00',
  `bonus` decimal(15,2) DEFAULT '0.00',
  `bpjs_tk` decimal(15,2) DEFAULT '0.00',
  `bpjs_kesehatan` decimal(15,2) DEFAULT '0.00',
  `pot_pinjaman` decimal(15,2) DEFAULT '0.00',
  `pot_lain` decimal(15,2) DEFAULT '0.00',
  `sync_pinjaman` tinyint(1) DEFAULT '0',
  `total_terima` decimal(15,2) DEFAULT '0.00',
  `status_gaji` enum('Draft','Generated','Paid') DEFAULT 'Draft',
  `tgl_input` datetime DEFAULT NULL,
  `tgl_update` datetime DEFAULT NULL,
  `tgl_generate` datetime DEFAULT NULL,
  `tgl_bayar` datetime DEFAULT NULL,
  `bukti_tf` varchar(255) DEFAULT NULL,
  `keterangan` text,
  PRIMARY KEY (`id`),
  KEY `idx_pegawai` (`iduser_pegawai`),
  KEY `idx_periode` (`periode`),
  KEY `idx_status` (`status_gaji`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Records of tgaji
-- ----------------------------

-- ----------------------------
-- Table structure for `tgroup_akun`
-- ----------------------------
DROP TABLE IF EXISTS `tgroup_akun`;
CREATE TABLE `tgroup_akun` (
  `kodgroup` int(11) NOT NULL AUTO_INCREMENT,
  `nmgroup` varchar(100) NOT NULL,
  PRIMARY KEY (`kodgroup`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Records of tgroup_akun
-- ----------------------------

-- ----------------------------
-- Table structure for `timplementasi`
-- ----------------------------
DROP TABLE IF EXISTS `timplementasi`;
CREATE TABLE `timplementasi` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `iduser` varchar(15) NOT NULL,
  `kodcustomer` varchar(20) NOT NULL,
  `aktivitas` text NOT NULL,
  `tglmulai` date NOT NULL,
  `tglselesai` date NOT NULL,
  `userorder` varchar(15) NOT NULL,
  `userpj` varchar(15) NOT NULL,
  `stsdel` int(1) DEFAULT '0',
  `isselesai` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0=belum selesai, 1=sudah selesai',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=87 DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Records of timplementasi
-- ----------------------------

-- ----------------------------
-- Table structure for `tinvoice`
-- ----------------------------
DROP TABLE IF EXISTS `tinvoice`;
CREATE TABLE `tinvoice` (
  `noinvoice` varchar(30) NOT NULL,
  `kodcustomer` varchar(20) NOT NULL,
  `tglinvoice` date DEFAULT '2000-01-01',
  `bsuinvoice` double DEFAULT '0',
  `isiinvoice` varchar(100) DEFAULT '-',
  `validinvoice` date DEFAULT '2000-01-01',
  `iduserinvoice` varchar(15) DEFAULT '-',
  `isbayarinvoice` tinyint(1) DEFAULT '0',
  `tglbayarinvoice` date DEFAULT '2000-01-01',
  `bsubayarinvoice` double DEFAULT '0',
  `ketbayarinvoice` varchar(100) DEFAULT '-',
  `iduserbayarinvoice` varchar(15) DEFAULT '-',
  `noefaktur` varchar(30) DEFAULT '-',
  `tglefaktur` date DEFAULT '2000-01-01',
  `bsuefaktur` double DEFAULT '0',
  `isbayarefaktur` tinyint(1) DEFAULT '0',
  `tglbayarefaktur` date DEFAULT '2000-01-01',
  `iduserefaktur` varchar(15) DEFAULT '-',
  `nopph` varchar(50) DEFAULT NULL,
  `tglpph` date DEFAULT '2000-01-01',
  `bsupph` double DEFAULT '0',
  `isbayarpph` tinyint(1) DEFAULT NULL,
  `tglbayarpph` date DEFAULT '2000-01-01',
  `iduserpph` varchar(15) DEFAULT '-',
  PRIMARY KEY (`noinvoice`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- ----------------------------
-- Records of tinvoice
-- ----------------------------

-- ----------------------------
-- Table structure for `tipkantor`
-- ----------------------------
DROP TABLE IF EXISTS `tipkantor`;
CREATE TABLE `tipkantor` (
  `noip` varchar(15) NOT NULL DEFAULT '-',
  `nmkantor` varchar(20) NOT NULL DEFAULT '-',
  PRIMARY KEY (`noip`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- ----------------------------
-- Records of tipkantor
-- ----------------------------

-- ----------------------------
-- Table structure for `tizin`
-- ----------------------------
DROP TABLE IF EXISTS `tizin`;
CREATE TABLE `tizin` (
  `idizin` bigint(20) NOT NULL AUTO_INCREMENT,
  `iduser` varchar(15) NOT NULL,
  `kategori` varchar(20) DEFAULT NULL,
  `lamanya` varchar(20) NOT NULL,
  `keperluan` text NOT NULL,
  `tglizin` date NOT NULL DEFAULT '0000-00-00',
  `isapprove` int(1) NOT NULL DEFAULT '0' COMMENT '1=setuju, 2=ditolak',
  `tglapprove` date NOT NULL DEFAULT '0000-00-00',
  `tglentri` date NOT NULL DEFAULT '0000-00-00',
  `stsdel` int(1) NOT NULL DEFAULT '0',
  `keterangan` text NOT NULL,
  PRIMARY KEY (`idizin`)
) ENGINE=InnoDB AUTO_INCREMENT=124 DEFAULT CHARSET=latin1;

-- ----------------------------
-- Records of tizin
-- ----------------------------

-- ----------------------------
-- Table structure for `tkas`
-- ----------------------------
DROP TABLE IF EXISTS `tkas`;
CREATE TABLE `tkas` (
  `idkas` int(11) NOT NULL AUTO_INCREMENT,
  `notransaksi` varchar(20) NOT NULL,
  `tgltransaksi` date NOT NULL,
  `kodakun` varchar(20) NOT NULL,
  `deskripsi` varchar(255) DEFAULT NULL,
  `satuan` varchar(50) DEFAULT NULL,
  `plusmin` enum('+','-') NOT NULL DEFAULT '-',
  `jumlah` decimal(10,2) NOT NULL DEFAULT '1.00',
  `hargaunit` decimal(15,2) NOT NULL DEFAULT '0.00',
  `totalharga` decimal(15,2) NOT NULL DEFAULT '0.00',
  `iduser_proses` varchar(20) NOT NULL,
  `sts_approve` enum('B','Y') NOT NULL DEFAULT 'B',
  `keterangan` varchar(255) DEFAULT NULL,
  `bukti_kas` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`idkas`),
  UNIQUE KEY `notransaksi_UNIQUE` (`notransaksi`,`kodakun`,`deskripsi`(100)),
  KEY `fk_tkas_takun_idx` (`kodakun`),
  KEY `fk_tkas_ruser_idx` (`iduser_proses`),
  KEY `idx_tgltransaksi` (`tgltransaksi`),
  CONSTRAINT `tkas_ibfk_1` FOREIGN KEY (`kodakun`) REFERENCES `takun` (`kodakun`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Records of tkas
-- ----------------------------

-- ----------------------------
-- Table structure for `tkas_saldo_awal`
-- ----------------------------
DROP TABLE IF EXISTS `tkas_saldo_awal`;
CREATE TABLE `tkas_saldo_awal` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bulan` int(2) NOT NULL,
  `tahun` int(4) NOT NULL,
  `saldo_awal` decimal(15,2) NOT NULL DEFAULT '0.00',
  `keterangan` varchar(255) DEFAULT NULL,
  `iduser_input` varchar(20) NOT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unik_bulan_tahun` (`bulan`,`tahun`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Records of tkas_saldo_awal
-- ----------------------------

-- ----------------------------
-- Table structure for `tkehadiran`
-- ----------------------------
DROP TABLE IF EXISTS `tkehadiran`;
CREATE TABLE `tkehadiran` (
  `iduser` varchar(20) NOT NULL,
  `tanggal` date NOT NULL,
  `hadir` varchar(10) DEFAULT '',
  `pulang` varchar(10) DEFAULT '',
  PRIMARY KEY (`iduser`,`tanggal`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- ----------------------------
-- Records of tkehadiran
-- ----------------------------

-- ----------------------------
-- Table structure for `tlembur`
-- ----------------------------
DROP TABLE IF EXISTS `tlembur`;
CREATE TABLE `tlembur` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `deskripsi` text NOT NULL,
  `latarbelakang` text NOT NULL,
  `iduser_pengaju` varchar(15) NOT NULL,
  `tgl_lembur` date NOT NULL,
  `jam_mulai` time NOT NULL,
  `jam_selesai` time NOT NULL,
  `tgl_pengajuan` datetime NOT NULL,
  `status_approval` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `iduser_approver` varchar(15) DEFAULT NULL,
  `tgl_approval` datetime DEFAULT NULL,
  `catatan_approval` text,
  PRIMARY KEY (`id`),
  KEY `iduser_pengaju` (`iduser_pengaju`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Records of tlembur
-- ----------------------------

-- ----------------------------
-- Table structure for `tlog`
-- ----------------------------
DROP TABLE IF EXISTS `tlog`;
CREATE TABLE `tlog` (
  `idlog` bigint(20) NOT NULL AUTO_INCREMENT,
  `iduser` varchar(15) NOT NULL,
  `kodcustomer` varchar(20) NOT NULL,
  `jnsbisnis` varchar(1) NOT NULL DEFAULT 'M' COMMENT 'M=Maintenance, D=Developing, B=BIsnis, A=Administrasi',
  `tglorder` date NOT NULL,
  `fasorder` varchar(50) NOT NULL COMMENT 'Order melalui ..., terangkan dengan WA dari ssiapa atau email siapa',
  `desorder` text NOT NULL,
  `deslayan` text NOT NULL COMMENT 'Tuliskan Tanggal dan Aktifitasnya',
  `isselesai` tinyint(4) NOT NULL DEFAULT '0' COMMENT '0=belum, 1=selesai',
  `tglselesai` date NOT NULL DEFAULT '0000-00-00',
  `userorder` varchar(15) NOT NULL DEFAULT '-',
  `tgltarget` date NOT NULL DEFAULT '0000-00-00' COMMENT 'Target Tanggal Selesai',
  `prioritas` varchar(1) NOT NULL COMMENT '1=Sangat Tinggi, 2=Tinggi, 3=Biasa',
  `stsdel` int(1) DEFAULT '0' COMMENT '0=belum delete, 1=sudah delete',
  `isupdate` int(1) DEFAULT '0' COMMENT '0=belum selesai, 1=selesai',
  `tglupdate` date DEFAULT '0000-00-00' COMMENT 'tgl update ke atas',
  `istesting` int(1) DEFAULT '0' COMMENT '0=belum testing, 1= sudah testing',
  `tgltesting` date DEFAULT '0000-00-00' COMMENT 'tanggal testing',
  `ketterlambat` text,
  `nilai` enum('1','2','3','4','5') DEFAULT NULL,
  `file_uploads` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT 'JSON metadata file uploads',
  `idsprint` int(11) DEFAULT NULL,
  `id_parent` int(11) DEFAULT NULL,
  PRIMARY KEY (`idlog`)
) ENGINE=MyISAM AUTO_INCREMENT=7508 DEFAULT CHARSET=latin1;

-- ----------------------------
-- Records of tlog
-- ----------------------------

-- ----------------------------
-- Table structure for `tpinjaman`
-- ----------------------------
DROP TABLE IF EXISTS `tpinjaman`;
CREATE TABLE `tpinjaman` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `iduser_pemohon` varchar(20) NOT NULL,
  `alamat` varchar(255) DEFAULT NULL,
  `jabatan_pemohon` varchar(100) DEFAULT NULL,
  `no_telp` varchar(20) DEFAULT NULL,
  `nominal` decimal(15,2) NOT NULL DEFAULT '0.00',
  `keperluan` text NOT NULL,
  `tenor` int(11) NOT NULL DEFAULT '1',
  `cicilan_perbulan` decimal(15,2) NOT NULL DEFAULT '0.00',
  `periode_awal` varchar(7) DEFAULT NULL,
  `periode_akhir` varchar(7) DEFAULT NULL,
  `jumlah_dibayar` decimal(15,2) DEFAULT '0.00',
  `sisa_pinjaman` decimal(15,2) DEFAULT '0.00',
  `tgl_pengajuan` datetime DEFAULT CURRENT_TIMESTAMP,
  `status_approval` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `catatan_approval` varchar(255) DEFAULT NULL,
  `tgl_approval` datetime DEFAULT NULL,
  `iduser_approval` varchar(20) DEFAULT NULL,
  `status_lunas` enum('Belum','Lunas') NOT NULL DEFAULT 'Belum',
  PRIMARY KEY (`id`),
  KEY `idx_pemohon` (`iduser_pemohon`),
  KEY `idx_status` (`status_approval`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Records of tpinjaman
-- ----------------------------

-- ----------------------------
-- Table structure for `tsprint`
-- ----------------------------
DROP TABLE IF EXISTS `tsprint`;
CREATE TABLE `tsprint` (
  `idsprint` int(11) NOT NULL AUTO_INCREMENT,
  `judul` varchar(200) NOT NULL,
  `tgl_mulai` date NOT NULL,
  `tgl_selesai` date NOT NULL,
  `status` varchar(20) DEFAULT 'aktif',
  `iduser_create` varchar(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idsprint`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of tsprint
-- ----------------------------

-- ----------------------------
-- Table structure for `tsprint_plan`
-- ----------------------------
DROP TABLE IF EXISTS `tsprint_plan`;
CREATE TABLE `tsprint_plan` (
  `idplan` int(11) NOT NULL AUTO_INCREMENT,
  `idsprint` int(11) NOT NULL,
  `judul_plan` varchar(500) NOT NULL,
  `deskripsi` text,
  `iduser_assign` varchar(20) NOT NULL,
  `status` varchar(20) DEFAULT 'belum',
  `prioritas` int(11) DEFAULT '3',
  `progress` int(11) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`idplan`),
  KEY `idsprint` (`idsprint`),
  CONSTRAINT `tsprint_plan_ibfk_1` FOREIGN KEY (`idsprint`) REFERENCES `tsprint` (`idsprint`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
-- Records of tsprint_plan
-- ----------------------------

-- ----------------------------
-- Table structure for `ttanggungjawab_lembur`
-- ----------------------------
DROP TABLE IF EXISTS `ttanggungjawab_lembur`;
CREATE TABLE `ttanggungjawab_lembur` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `idlembur` int(11) NOT NULL,
  `idtask` int(11) DEFAULT NULL,
  `status_lembur` int(1) NOT NULL,
  `kesimpulan` text NOT NULL,
  `foto` text,
  `iduser_pelapor` varchar(15) NOT NULL,
  `tgl_lapor` datetime NOT NULL,
  `tgl_update` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idlembur` (`idlembur`),
  KEY `iduser_pelapor` (`iduser_pelapor`)
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Records of ttanggungjawab_lembur
-- ----------------------------
