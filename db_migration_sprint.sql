-- ============================================
-- Migration: Fitur Sprint (Plan)
-- Import file ini di phpMyAdmin
-- ============================================

-- 1. Tabel Sprint
CREATE TABLE IF NOT EXISTS tsprint (
    idsprint INT AUTO_INCREMENT PRIMARY KEY,
    judul VARCHAR(200) NOT NULL,
    tgl_mulai DATE NOT NULL,
    tgl_selesai DATE NOT NULL,
    status VARCHAR(20) DEFAULT 'aktif',
    iduser_create VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- 2. Tabel Plan Items
CREATE TABLE IF NOT EXISTS tsprint_plan (
    idplan INT AUTO_INCREMENT PRIMARY KEY,
    idsprint INT NOT NULL,
    judul_plan VARCHAR(500) NOT NULL,
    deskripsi TEXT,
    iduser_assign VARCHAR(20) NOT NULL,
    status VARCHAR(20) DEFAULT 'belum',
    prioritas INT DEFAULT 3,
    progress INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (idsprint) REFERENCES tsprint(idsprint) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- 3. Tambah kolom idsprint di tabel tlog
-- Kalau error "Duplicate column", berarti sudah ada, abaikan saja
ALTER TABLE tlog ADD COLUMN idsprint INT DEFAULT NULL;

-- 4. Insert menu Sprint ke setting_hak_akses
INSERT IGNORE INTO setting_hak_akses (menu_nama) VALUES ('Sprint');
