
ALTER TABLE tlembur 
  ADD COLUMN status_approval ENUM('Pending','Approved','Rejected') DEFAULT 'Pending' AFTER tgl_pengajuan,
  ADD COLUMN iduser_approver VARCHAR(15) NULL AFTER status_approval,
  ADD COLUMN tgl_approval DATETIME NULL AFTER iduser_approver,
  ADD COLUMN catatan_approval TEXT NULL AFTER tgl_approval;
