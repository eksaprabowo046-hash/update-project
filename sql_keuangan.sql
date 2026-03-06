-- 1. TABEL tgroup_akun (Master Grup Akun)

CREATE TABLE IF NOT EXISTS `tgroup_akun` (
  `kodgroup` INT(11) NOT NULL AUTO_INCREMENT,
  `nmgroup` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`kodgroup`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data Awal Grup Akun (5 Grup Standar Akuntansi)
INSERT INTO `tgroup_akun` (`kodgroup`, `nmgroup`) VALUES
(1, 'ASET'),
(2, 'LIABILITAS'),
(3, 'MODAL'),
(4, 'PENDAPATAN'),
(5, 'BIAYA');


-- 2. TABEL takun (Master Akun )

CREATE TABLE IF NOT EXISTS `takun` (
  `kodakun` VARCHAR(20) NOT NULL ,
  `kodgroup` INT(11) NOT NULL,
  `nmakun` VARCHAR(100) NOT NULL,
  `tipe` ENUM('H','D') NOT NULL DEFAULT 'D' ,
  PRIMARY KEY (`kodakun`),
  INDEX `fk_takun_tgroup_idx` (`kodgroup` ASC),
  CONSTRAINT `fk_takun_tgroup`
    FOREIGN KEY (`kodgroup`)
    REFERENCES `tgroup_akun` (`kodgroup`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- 3. TABEL tkas (Transaksi Kas)

CREATE TABLE IF NOT EXISTS `tkas` (
  `idkas` INT(11) NOT NULL AUTO_INCREMENT,
  `notransaksi` VARCHAR(20) NOT NULL ,
  `tgltransaksi` DATE NOT NULL,
  `kodakun` VARCHAR(20) NOT NULL ,
  `deskripsi` VARCHAR(255) NULL ,
  `satuan` VARCHAR(50) NULL ,
  `plusmin` ENUM('+','-') NOT NULL DEFAULT '-' ,
  `jumlah` DECIMAL(10,2) NOT NULL DEFAULT 1 ,
  `hargaunit` DECIMAL(15,2) NOT NULL DEFAULT 0 ,
  `totalharga` DECIMAL(15,2) NOT NULL DEFAULT 0 ,
  `iduser_proses` VARCHAR(20) NOT NULL ,
  `sts_approve` ENUM('B','Y') NOT NULL DEFAULT 'B' ,
  `keterangan` VARCHAR(255) NULL ,
  PRIMARY KEY (`idkas`),
  UNIQUE INDEX `notransaksi_UNIQUE` (`notransaksi` ASC, `kodakun` ASC, `deskripsi`(100) ASC),
  INDEX `fk_tkas_takun_idx` (`kodakun` ASC),
  INDEX `fk_tkas_ruser_idx` (`iduser_proses` ASC),
  INDEX `idx_tgltransaksi` (`tgltransaksi` ASC),
  CONSTRAINT `fk_tkas_takun`
    FOREIGN KEY (`kodakun`)
    REFERENCES `takun` (`kodakun`)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

