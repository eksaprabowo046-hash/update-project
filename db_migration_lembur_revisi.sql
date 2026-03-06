
-- Tambah kolom target (target pekerjaan lembur per tugas)
ALTER TABLE tdtllembur 
  ADD COLUMN IF NOT EXISTS `target` TEXT NULL AFTER `tugas`;

-- Tambah kolom kodcustomer (customer per tugas, dropdown dari rcustomer)
ALTER TABLE tdtllembur 
  ADD COLUMN IF NOT EXISTS `kodcustomer` VARCHAR(15) NULL AFTER `target`;
