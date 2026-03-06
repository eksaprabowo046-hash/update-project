SET sql_mode = '';
ALTER TABLE tizin
ADD COLUMN kategori VARCHAR(20) DEFAULT NULL AFTER iduser;
