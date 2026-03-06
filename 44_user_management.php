<?php
// 
session_start();
include "dbase.php"; 
include "islogin.php"; 
$pesan = "";

// Tambah kolom divisi di tabel ruser jika belum ada
try {
    $cek = $conn->query("SHOW COLUMNS FROM ruser LIKE 'divisi'");
    if ($cek->rowCount() == 0) {
        $conn->exec("ALTER TABLE ruser ADD COLUMN divisi VARCHAR(100) AFTER kodjab");
    }
} catch (PDOException $e) { 
    // Abaikan error, lanjutkan proses
}

// Tambah kolom tgl_masuk di tabel ruser jika belum ada
try {
    $cek2 = $conn->query("SHOW COLUMNS FROM ruser LIKE 'tgl_masuk'");
    if ($cek2->rowCount() == 0) {
        $conn->exec("ALTER TABLE ruser ADD COLUMN tgl_masuk DATE NULL AFTER divisi");
    }
} catch (PDOException $e) { 
    // Abaikan error, lanjutkan proses
}

// Tambah kolom nik dan bank di tabel ruser jika belum ada
try {
    $cek3 = $conn->query("SHOW COLUMNS FROM ruser LIKE 'nik'");
    if ($cek3->rowCount() == 0) {
        $conn->exec("ALTER TABLE ruser ADD COLUMN nik VARCHAR(50) NULL AFTER nama");
    }
    $cek4 = $conn->query("SHOW COLUMNS FROM ruser LIKE 'bank'");
    if ($cek4->rowCount() == 0) {
        $conn->exec("ALTER TABLE ruser ADD COLUMN bank VARCHAR(100) NULL AFTER nik");
    }
} catch (PDOException $e) {}

// Tambah kolom file_kontrak di tabel ruser jika belum ada
try {
    $cek5 = $conn->query("SHOW COLUMNS FROM ruser LIKE 'file_kontrak'");
    if ($cek5->rowCount() == 0) {
        $conn->exec("ALTER TABLE ruser ADD COLUMN file_kontrak VARCHAR(255) NULL AFTER tgl_masuk");
    }
} catch (PDOException $e) {}

// Pastikan folder uploads/kontrak ada
$kontrak_dir = __DIR__ . '/uploads/kontrak';
if (!is_dir($kontrak_dir)) {
    mkdir($kontrak_dir, 0755, true);
}
 
$iduser_login = $_SESSION['DEFAULT_IDUSER'];  

// Fetch Divisi options
$divisiOptions = [];
try {
    $stmt_div = $conn->query("SELECT nama_divisi FROM tbl_divisi ORDER BY nama_divisi ASC");
    while ($row_div = $stmt_div->fetch(PDO::FETCH_ASSOC)) {
        $divisiOptions[] = $row_div['nama_divisi'];
    }
} catch (PDOException $e) {
    // Handle error or ignore
}

// Fetch Jabatan options from tbl_jabatan
$jabatanOptions = [];
try {
    $stmt_jab = $conn->query("SELECT kodjab, nama_jabatan FROM tbl_jabatan ORDER BY kodjab ASC");
    while ($row_jab = $stmt_jab->fetch(PDO::FETCH_ASSOC)) {
        $jabatanOptions[$row_jab['kodjab']] = $row_jab['nama_jabatan'];
    }
} catch (PDOException $e) {
    // Fallback jika tabel belum ada
    $jabatanOptions = [1 => 'Admin / Pimpinan', 3 => 'Staff / User'];
}  

// Fetch menu master for Hak Akses modal
$allMenus = [];
$hidden_staff = ['Input User'];
$hidden_from_akses = ['Input User']; // Menu yang tidak tampil di modal Atur Hak Akses
$default_off_staff = ['Invoice', 'Laporan Invoice', 'Penggajian', 'Keuangan', 'Pinjaman'];
try {
    $checkTable = $conn->query("SHOW TABLES LIKE 'setting_hak_akses'");
    if ($checkTable->rowCount() > 0) {
        // Sync default menus (sama seperti di 45_hak_akses.php)
        $defaultMenus = ['Log','Input User','Implementasi','Invoice','Laporan Log','Laporan Invoice',
            'Laporan Kehadiran','Perizinan','Lembur','Penggajian','Keuangan','About','Kalender','Customer','Dashboard','Pinjaman'];
        foreach ($defaultMenus as $menu) {
            $conn->exec("INSERT IGNORE INTO setting_hak_akses (menu_nama) VALUES ('" . addslashes($menu) . "')");
        }
        // (Pinjaman sekarang bisa dikonfigurasi via hak akses)
        $allMenus = $conn->query("SELECT menu_nama FROM setting_hak_akses ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
        // Hilangkan menu yang tidak perlu ditampilkan di modal hak akses
        $allMenus = array_values(array_filter($allMenus, function($m) use ($hidden_from_akses) {
            return !in_array($m, $hidden_from_akses);
        }));
    }
} catch (PDOException $e) {}

// SAVE HAK AKSES (AJAX)
if (isset($_POST['save_akses'])) {
    $target_user = $_POST['target_user'];
    $menus_aktif = isset($_POST['menus']) ? $_POST['menus'] : [];
    
    foreach ($allMenus as $menu) {
        $aktif = in_array($menu, $menus_aktif) ? 1 : 0;
        $stmt = $conn->prepare("INSERT INTO tbl_hak_akses (iduser, menu_nama, aktif) VALUES (?, ?, ?) 
                                ON DUPLICATE KEY UPDATE aktif = ?");
        $stmt->execute([$target_user, $menu, $aktif, $aktif]);
    }
    
    $pesan = "<div class='alert alert-success'><i class='fa fa-check-circle'></i> Hak akses untuk <strong>" . htmlspecialchars($target_user) . "</strong> berhasil disimpan!</div>";
    echo "<script>setTimeout(function(){ window.location.href = 'index.php?par=44'; }, 1000);</script>";
}

// INSERT
if (isset($_POST['ins'])) {   
    $iduser_new = trim($_POST['iduser_new']); 
    $nama = trim($_POST['nama']); 
    $passwd = $_POST['passwd']; 
    $kodjab = $_POST['kodjab'];
    $divisi = $_POST['divisi'];
    $nik = trim($_POST['nik'] ?? '');
    $bank = trim($_POST['bank'] ?? '');
    $stsaktif = isset($_POST['stsaktif']) ? $_POST['stsaktif'] : 1;
    $tgl_masuk = !empty($_POST['tgl_masuk']) ? $_POST['tgl_masuk'] : null;
    
    // Handle upload file kontrak kerja (PDF)
    $file_kontrak = '';
    if (isset($_FILES['file_kontrak']) && $_FILES['file_kontrak']['error'] == UPLOAD_ERR_OK) {
        $tmp_name = $_FILES['file_kontrak']['tmp_name'];
        $orig_name = $_FILES['file_kontrak']['name'];
        $file_ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
        $file_size = $_FILES['file_kontrak']['size'];
        
        if ($file_ext !== 'pdf') {
            $pesan = "<font color=red>File kontrak harus berformat PDF!</font>";
        } elseif ($file_size > 5 * 1024 * 1024) { // Max 5MB
            $pesan = "<font color=red>Ukuran file kontrak maksimal 5MB!</font>";
        } else {
            $file_kontrak = 'kontrak_' . $iduser_new . '_' . time() . '.pdf';
            $upload_path = $kontrak_dir . '/' . $file_kontrak;
            if (!move_uploaded_file($tmp_name, $upload_path)) {
                $pesan = "<font color=red>Gagal mengupload file kontrak!</font>";
                $file_kontrak = '';
            }
        }
    }
    
    // Jika ada error dari validasi file, skip insert
    if (empty($pesan)) {
    // Cek dulu apakah ID User sudah ada?
    $cek_id = $conn->prepare("SELECT iduser FROM ruser WHERE iduser = ?");
    $cek_id->execute([$iduser_new]);
    
    if ($cek_id->rowCount() > 0) {
        $pesan = "<font color=red>Gagal menambahkan. ID User <strong>".$iduser_new."</strong> sudah ada!</font>";
    } else {
        // Lanjut insert kalau belum ada
        $file_kontrak_val = !empty($file_kontrak) ? "'$file_kontrak'" : "NULL";
        $sql = "insert into ruser (iduser, nama, nik, bank, passwd, kodjab, divisi, stsaktif, tgl_masuk, file_kontrak) "; 
        $tgl_val = $tgl_masuk ? "'$tgl_masuk'" : "NULL";
        $sql = $sql . "values ('$iduser_new', '$nama', '$nik', '$bank', '$passwd', '$kodjab', '$divisi', '$stsaktif', $tgl_val, $file_kontrak_val)"; 
        
        try { 
            $qins = $conn->prepare($sql);
            $qins->execute(); 
            
            // Auto-save default hak akses untuk user baru
            $default_off_staff = ['Invoice', 'Laporan Invoice', 'Penggajian', 'Keuangan', 'Pinjaman'];
            $hidden_staff_menus = ['Input User'];
            try {
                foreach ($allMenus as $menu) {
                    // Skip menu yang hidden untuk staff
                    if ($kodjab != 1 && in_array($menu, $hidden_staff_menus)) continue;
                    
                    // Tentukan default aktif/nonaktif
                    if ($kodjab != 1 && $kodjab != 2 && in_array($menu, $default_off_staff)) {
                        $aktif = 0; // Nonaktif untuk Staff
                    } else {
                        $aktif = 1; // Aktif
                    }
                    
                    $stmt_akses = $conn->prepare("INSERT IGNORE INTO tbl_hak_akses (iduser, menu_nama, aktif) VALUES (?, ?, ?)");
                    $stmt_akses->execute([$iduser_new, $menu, $aktif]);
                }
            } catch (PDOException $e2) {
                // Lanjut saja kalau gagal insert hak akses
            }
            
            $pesan = "<font color=blue>User baru <strong> ".$iduser_new."  </strong> berhasil ditambahkan (hak akses default tersimpan)</font>"; 
        } catch (PDOException $e) {
            $pesan = "<font color=red>Gagal menambahkan <strong> ".$iduser_new." </strong>. " . $e->getMessage() . "</font>"; 
        }
    }
    } // end empty($pesan) check
}

// UPDATE
if (isset($_POST['upd'])) {
    $iduser_new = trim($_POST['iduser_new']); 
    $nama = trim($_POST['nama']); 
    $passwd = $_POST['passwd']; 
    $kodjab = $_POST['kodjab'];
    $divisi = $_POST['divisi'];
    $nik = trim($_POST['nik'] ?? '');
    $bank = trim($_POST['bank'] ?? '');
    $stsaktif = isset($_POST['stsaktif']) ? $_POST['stsaktif'] : 1;
    $tgl_masuk = !empty($_POST['tgl_masuk']) ? $_POST['tgl_masuk'] : null;
    $tgl_sql = $tgl_masuk ? "tgl_masuk='$tgl_masuk'" : "tgl_masuk=NULL";
    
    // Handle upload file kontrak kerja (PDF) - UPDATE
    $kontrak_sql = '';
    if (isset($_FILES['file_kontrak']) && $_FILES['file_kontrak']['error'] == UPLOAD_ERR_OK) {
        $tmp_name = $_FILES['file_kontrak']['tmp_name'];
        $orig_name = $_FILES['file_kontrak']['name'];
        $file_ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
        $file_size = $_FILES['file_kontrak']['size'];
        
        if ($file_ext !== 'pdf') {
            $pesan = "<font color=red>File kontrak harus berformat PDF!</font>";
        } elseif ($file_size > 5 * 1024 * 1024) { // Max 5MB
            $pesan = "<font color=red>Ukuran file kontrak maksimal 5MB!</font>";
        } else {
            // Hapus file lama jika ada
            $old_file_stmt = $conn->prepare("SELECT file_kontrak FROM ruser WHERE iduser = ?");
            $old_file_stmt->execute([$iduser_new]);
            $old_file_row = $old_file_stmt->fetch();
            if ($old_file_row && !empty($old_file_row['file_kontrak'])) {
                $old_path = $kontrak_dir . '/' . $old_file_row['file_kontrak'];
                if (file_exists($old_path)) {
                    unlink($old_path);
                }
            }
            
            $new_kontrak = 'kontrak_' . $iduser_new . '_' . time() . '.pdf';
            $upload_path = $kontrak_dir . '/' . $new_kontrak;
            if (move_uploaded_file($tmp_name, $upload_path)) {
                $kontrak_sql = ", file_kontrak='$new_kontrak'";
            } else {
                $pesan = "<font color=red>Gagal mengupload file kontrak!</font>";
            }
        }
    }
    
    if (empty($pesan)) {
    // Only update password if not empty
    if (!empty($passwd)) {
        $sql = "UPDATE ruser SET nama='$nama', nik='$nik', bank='$bank', passwd='$passwd', kodjab='$kodjab', divisi='$divisi', stsaktif='$stsaktif', $tgl_sql $kontrak_sql WHERE iduser='$iduser_new'";
    } else {
        $sql = "UPDATE ruser SET nama='$nama', nik='$nik', bank='$bank', kodjab='$kodjab', divisi='$divisi', stsaktif='$stsaktif', $tgl_sql $kontrak_sql WHERE iduser='$iduser_new'";
    }
    
    try { 
        $qupd = $conn->prepare($sql);
        $qupd->execute(); 
        $pesan = "<font color=blue>User <strong> ".$iduser_new."  </strong> berhasil diperbarui</font>"; 
    } catch (PDOException $e) {
        $pesan = "<font color=red>Gagal memperbarui <strong> ".$iduser_new." </strong>. Pastikan data benar.</font>"; 
    }
    } // end empty($pesan) check
}

// DELETE 
if (isset($_POST['del'])) {
    $iduser_del = trim($_POST['iduser_del']);
    
    // Check if user has logs (optional safety check)
    $istidakbolehdel = 0;	  
    $sqlcek = "select * from tlog WHERE iduser='$iduser_del'"; 
    
    try { 
        $qcek = $conn->prepare($sqlcek);
        $qcek->execute(); 
        if($qcek->rowCount() > 0){ 
            $istidakbolehdel = 1;
            $pesan = "<font color=red>User <strong>".$iduser_del."</strong> memiliki data log.</font><br>";
            $pesan = $pesan."<font color=red>Tidak boleh delete user yang sudah ada lognya</font>";
        }				
    } catch (PDOException $e) {
        $pesan = "<font color=red>Error checking. Make sure <strong>".$iduser_del."</strong> is correct.</font>";
    }
    
    if ($istidakbolehdel==0){
        $sqld = "DELETE FROM ruser WHERE iduser='$iduser_del'";
        
        try { 
            $qdel = $conn->prepare($sqld);
            $qdel->execute();
            $pesan = "<font color=blue>User <strong>".$iduser_del."</strong> berhasil dihapus</font>"; 
        } catch (PDOException $e) {
            $pesan = "<font color=red>Gagal menghapus. Pastikan <strong>".$iduser_del."</strong> benar.</font>";
        }
    }	
}

// HAPUS FILE KONTRAK
if (isset($_POST['hapus_kontrak'])) {
    $iduser_hk = trim($_POST['iduser_hapus_kontrak']);
    
    // Ambil nama file lama
    $old_stmt = $conn->prepare("SELECT file_kontrak FROM ruser WHERE iduser = ?");
    $old_stmt->execute([$iduser_hk]);
    $old_row = $old_stmt->fetch();
    
    if ($old_row && !empty($old_row['file_kontrak'])) {
        // Hapus file fisik
        $old_path = $kontrak_dir . '/' . $old_row['file_kontrak'];
        if (file_exists($old_path)) {
            unlink($old_path);
        }
        // Set kolom file_kontrak ke NULL
        $upd_stmt = $conn->prepare("UPDATE ruser SET file_kontrak = NULL WHERE iduser = ?");
        $upd_stmt->execute([$iduser_hk]);
        $pesan = "<font color=blue>File kontrak untuk <strong>".$iduser_hk."</strong> berhasil dihapus</font>";
    } else {
        $pesan = "<font color=red>Tidak ada file kontrak untuk dihapus</font>";
    }
}	

?>	 

<style>
/* Removed search-box-material as it might interfere with standard inputs */

/* Vertically center the modal */
.modal {
  text-align: center;
  padding: 0!important;
}

.modal:before {
  content: '';
  display: inline-block;
  height: 100%;
  vertical-align: middle;
  margin-right: -4px; /* Adjusts for spacing */
}

.modal-dialog {
  display: inline-block;
  text-align: left;
  vertical-align: middle;
  width: 90%; 
  max-width: 600px;
}
</style>

<script type="text/javascript"> 
function stopRKey(evt) { 
    var evt = (evt) ? evt : ((event) ? event : null); 
    var node = (evt.target) ? evt.target : ((evt.srcElement) ? evt.srcElement : null); 
    if ((evt.keyCode == 13) && (node.type=="text")) {return false;} 
} 
document.onkeypress = stopRKey; 
</script>

<script language="javascript">
function ConfirmDelete() {
    var x = confirm("Apakah Anda yakin ingin menghapus user ini?");
    if (x) return true;
    else return false;
}

function validasi_input(form){
    if (form.iduser_new.value == ""){
        alert("ID User masih kosong!");
        form.iduser_new.focus();
        return (false);
    }
    if (form.nama.value == ""){
        alert("Nama user masih kosong!");
        form.nama.focus();
        return (false);
    }
    return (true);
}

// Function to populate modal with user data
function editUser(id, nama, kodjab, divisi, stsaktif, tgl_masuk, nik, bank, file_kontrak) {
    document.getElementById('edit_iduser_new').value = id;
    document.getElementById('edit_nama').value = nama;
    document.getElementById('edit_nik').value = nik ? nik : '';
    document.getElementById('edit_bank').value = bank ? bank : '';
    document.getElementById('edit_kodjab').value = kodjab;
    document.getElementById('edit_divisi').value = divisi;
    document.getElementById('edit_stsaktif').value = stsaktif;
    document.getElementById('edit_tgl_masuk').value = tgl_masuk ? tgl_masuk : '';
    document.getElementById('edit_passwd').value = ""; // Clear password field
    
    // Tampilkan info file kontrak yang sudah ada
    var kontrakInfo = document.getElementById('edit_kontrak_info');
    var hapusKontrakInput = document.getElementById('edit_hapus_kontrak');
    if (hapusKontrakInput) hapusKontrakInput.value = '';
    if (file_kontrak && file_kontrak !== '') {
        kontrakInfo.innerHTML = '<span class="label label-success"><i class="fa fa-file-pdf-o"></i> File tersimpan</span> <a href="uploads/kontrak/' + file_kontrak + '" target="_blank" class="btn btn-xs btn-info">Lihat PDF</a> <button type="button" class="btn btn-xs btn-danger" onclick="hapusKontrak(\'' + id + '\')"><i class="fa fa-trash"></i> Hapus File</button>';
    } else {
        kontrakInfo.innerHTML = '<span class="label label-default">Belum ada file kontrak</span>';
    }
    
    // Show Modal (using Bootstrap 3 syntax as per project)
    $('#editUserModal').modal('show');
}
</script>

<body>
 
<div class="row"> 
    <ol class="breadcrumb">
        <li><i class="fa fa-home"></i>MANAGE USER</li> 
    </ol> 
    <section class="panel">
        <header class="panel-heading">
            
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#createUserModal" style="margin-top: 30px;">
              <i class="fa fa-plus"></i> Tambah User Baru
            </button>
            <div class="clearfix"></div>
            <br>
            
            <h4><font color="red"><?php echo $pesan;?></font></h4>
        </header>
        <section class="content">
            <div align="center" style="margin-top: 20px;">Daftar User</div>
            <div class="box-body">
                <table id="contoh" class="table table-bordered table-striped table-hover"> 
                    <thead> 
                        <tr class="height: 5px;">
                            <th>#</th>
                            <th>ID User</th>
                            <th>Nama</th>
                            <th>NIK</th>
                            <th>Bank</th>
                            <th>Jabatan</th>
                            <th>Divisi</th>
                            <th>Status</th>
                            <th>Tgl Masuk</th>
                            <th>Kontrak</th>
                            <th>Aksi</th> 
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        try { 
                            $sql = $conn->prepare("select * from ruser order by stsaktif DESC, kodjab ASC, nik ASC, nama ASC"); 
                            $sql->execute();	 				
                            $no=1;
                            while($rs = $sql->fetch()) { 
                                $jabatan = isset($jabatanOptions[$rs['kodjab']]) ? $jabatanOptions[$rs['kodjab']] : 'Belum diatur';
                                $status_aktif = (isset($rs['stsaktif']) && $rs['stsaktif'] == 1) ? "<span class='label label-success'>Aktif</span>" : "<span class='label label-danger'>Tidak Aktif</span>";
                                $tgl_masuk_val = isset($rs['tgl_masuk']) && $rs['tgl_masuk'] ? date('d-m-Y', strtotime($rs['tgl_masuk'])) : '-';
                                $file_kontrak_val = isset($rs['file_kontrak']) && !empty($rs['file_kontrak']) ? $rs['file_kontrak'] : '';
                                
                                // Tampilan kolom kontrak
                                if (!empty($file_kontrak_val)) {
                                    $kontrak_link = "<a href='uploads/kontrak/".$file_kontrak_val."' target='_blank' class='btn btn-success btn-xs'><i class='fa fa-file-pdf-o'></i> Lihat</a>";
                                } else {
                                    $kontrak_link = "-";
                                }
                                
                                // Escape strings for JS
                                $js_id = htmlspecialchars($rs['iduser'], ENT_QUOTES);
                                $js_nama = htmlspecialchars($rs['nama'], ENT_QUOTES);
                                $js_jab = $rs['kodjab'];
                                $js_div = htmlspecialchars($rs['divisi'] ?? '', ENT_QUOTES);
                                $js_sts = isset($rs['stsaktif']) ? $rs['stsaktif'] : 1;
                                $js_tgl = isset($rs['tgl_masuk']) && $rs['tgl_masuk'] ? $rs['tgl_masuk'] : '';
                                $js_nik = htmlspecialchars($rs['nik'] ?? '', ENT_QUOTES);
                                $js_bank = htmlspecialchars($rs['bank'] ?? '', ENT_QUOTES);
                                $js_kontrak = htmlspecialchars($file_kontrak_val, ENT_QUOTES);
                                
                                echo "<tr>
                                    <td align=center>".$no."</td>
                                    <td>".$rs['iduser']."</td>
                                    <td>".$rs['nama']."</td>
                                    <td>".($rs['nik'] ?? '-')."</td>
                                    <td>".($rs['bank'] ?? '-')."</td>
                                    <td>".$jabatan."</td>
                                    <td>".($rs['divisi'] ?? '')."</td>
                                    <td align=center>".$status_aktif."</td>
                                    <td>".$tgl_masuk_val."</td>
                                    <td align=center>".$kontrak_link."</td>
                                    <td align=center>
                                        <button type='button' class='btn btn-warning btn-xs' 
                                            onclick=\"editUser('$js_id', '$js_nama', '$js_jab', '$js_div', '$js_sts', '$js_tgl', '$js_nik', '$js_bank', '$js_kontrak')\">
                                            Edit
                                        </button>
                                        <button type='button' class='btn btn-info btn-xs' 
                                            onclick=\"aturAkses('$js_id', '$js_nama', '$js_jab')\">
                                            <i class='fa fa-key'></i> Akses
                                        </button>
                                        <form method='POST' action='index.php?par=44' style='display:inline;'>
                                            <input type='hidden' name='iduser_del' value='".$rs['iduser']."'>  
                                            <input type='hidden' name='del' value='Y'>
                                            <button type='submit' class='btn btn-danger btn-xs' value='Y' Onclick='return ConfirmDelete();'>Delete</button>     
                                        </form> 
                                    </td> 
                                </tr>";
                                $no++;	
                            } 
                        } catch (PDOException $e) {
                            echo "<tr>
                                <td colspan='11' align='center'>No data available</td>
                            </tr>";
                        }
                        ?> 
                    </tbody>
                </table>
            </div>
        </section>
    </section>
</div> 

<!-- Modal Create User -->
<div id="createUserModal" class="modal fade" role="dialog">
  <div class="modal-dialog">

    <!-- Modal content-->
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title">Tambah User Baru</h4>
      </div>
      <div class="modal-body">
         <form role="form" method="POST" onSubmit="return validasi_input(this)" action="index.php?par=44" autocomplete="off" enctype="multipart/form-data">
            <!-- Dummy inputs to prevent autofill -->
            <input type="text" style="display:none">
            <input type="password" style="display:none">
            <div class="form-group">
                <label>ID User (Login)</label>
                <input type="text" name="iduser_new" class="form-control" placeholder="ID User..." autocomplete="off">
            </div>
            <div class="form-group">
                <label>Nama Lengkap</label>
                <input type="text" name="nama" class="form-control" placeholder="Nama Lengkap..." autocomplete="off">
            </div>
            <div class="row">
              <div class="col-sm-6">
                <div class="form-group">
                    <label>NIK</label>
                    <input type="text" name="nik" class="form-control" placeholder="Nomor Induk Karyawan..." autocomplete="off">
                </div>
              </div>
              <div class="col-sm-6">
                <div class="form-group">
                    <label>Bank (Nama Bank & No. Rek)</label>
                    <input type="text" name="bank" class="form-control" placeholder="Contoh: BCA 1234567890" autocomplete="off">
                </div>
              </div>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="passwd" class="form-control" placeholder="Password..." autocomplete="new-password">
            </div>
            <div class="form-group">
                <label>Jabatan</label>
                <select name="kodjab" class="form-control">
                    <option value="">-- Pilih Jabatan --</option>
                    <?php foreach($jabatanOptions as $kj => $nj) {
                        echo "<option value=\"".htmlspecialchars($kj)."\">".htmlspecialchars($nj)."</option>";
                    } ?>
                </select>
            </div>
            <div class="form-group">
                <label>Divisi</label>
                <select name="divisi" class="form-control">
                    <option value="">-- Pilih Divisi --</option>
                    <?php foreach($divisiOptions as $div) {
                        echo "<option value=\"".htmlspecialchars($div)."\">".htmlspecialchars($div)."</option>";
                    } ?>
                </select>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="stsaktif" class="form-control">
                    <option value="1" selected>Aktif</option>
                    <option value="0">Tidak Aktif</option>
                </select>
            </div>
            <div class="form-group">
                <label>Tanggal Masuk</label>
                <input type="date" name="tgl_masuk" class="form-control">
            </div>
            <div class="form-group">
                <label>Kontrak Kerja (PDF, maks 5MB)</label>
                <input type="file" name="file_kontrak" class="form-control" accept=".pdf">
            </div>
            
            <input type="hidden" name="ins" value="Y">
            
            <button type="submit" name="submit" class="btn btn-primary" value="Y">Create User</button>
            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
         </form>
      </div>
    </div>

  </div>
</div>

<!-- Modal Edit User -->
<div id="editUserModal" class="modal fade" role="dialog">
  <div class="modal-dialog">

    <!-- Modal content-->
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title">Edit User</h4>
      </div>
      <div class="modal-body">
         <form role="form" method="POST" action="index.php?par=44" enctype="multipart/form-data">
            <div class="form-group">
                <label>ID User</label>
                <input type="text" name="iduser_new" id="edit_iduser_new" class="form-control" readonly>
            </div>
            <div class="form-group">
                <label>Nama Lengkap</label>
                <input type="text" name="nama" id="edit_nama" class="form-control">
            </div>
            <div class="row">
              <div class="col-sm-6">
                <div class="form-group">
                    <label>NIK</label>
                    <input type="text" name="nik" id="edit_nik" class="form-control" placeholder="Nomor Induk Karyawan...">
                </div>
              </div>
              <div class="col-sm-6">
                <div class="form-group">
                    <label>Bank (Nama Bank & No. Rek)</label>
                    <input type="text" name="bank" id="edit_bank" class="form-control" placeholder="Contoh: BCA 1234567890">
                </div>
              </div>
            </div>
            <div class="form-group">
                <label>Password (Isi jika ingin mengubah)</label>
                <input type="password" name="passwd" id="edit_passwd" class="form-control">
            </div>
            <div class="form-group">
                <label>Jabatan</label>
                <select name="kodjab" id="edit_kodjab" class="form-control">
                    <option value="">-- Pilih Jabatan --</option>
                    <?php foreach($jabatanOptions as $kj => $nj) {
                        echo "<option value=\"".htmlspecialchars($kj)."\">".htmlspecialchars($nj)."</option>";
                    } ?>
                </select>
            </div>
            <div class="form-group">
                <label>Divisi</label>
                <select name="divisi" id="edit_divisi" class="form-control">
                     <option value="">-- Pilih Divisi --</option>
                     <?php foreach($divisiOptions as $div) {
                        echo "<option value=\"".htmlspecialchars($div)."\">".htmlspecialchars($div)."</option>";
                    } ?>
                </select>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="stsaktif" id="edit_stsaktif" class="form-control">
                    <option value="1">Aktif</option>
                    <option value="0">Tidak Aktif</option>
                </select>
            </div>
            <div class="form-group">
                <label>Tanggal Masuk</label>
                <input type="date" name="tgl_masuk" id="edit_tgl_masuk" class="form-control">
            </div>
            <div class="form-group">
                <label>Kontrak Kerja (PDF, maks 5MB)</label>
                <div id="edit_kontrak_info" style="margin-bottom:5px;"></div>
                <input type="file" name="file_kontrak" class="form-control" accept=".pdf">
                <small class="text-muted">Kosongkan jika tidak ingin mengubah file kontrak</small>
            </div>
            
            <input type="hidden" name="upd" value="Y">
            
            <button type="submit" class="btn btn-primary">Update Data</button>
            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
         </form>
      </div>
    </div>

  </div>
</div>

<!-- Hidden form for hapus kontrak (di luar modal agar tidak nested form) -->
<form id="formHapusKontrak" method="POST" action="index.php?par=44" style="display:none;">
    <input type="hidden" name="hapus_kontrak" value="1">
    <input type="hidden" name="iduser_hapus_kontrak" id="edit_hapus_kontrak_iduser" value="">
</form>

<!-- Modal Atur Hak Akses -->
<div id="aksesModal" class="modal fade" role="dialog">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header" style="background: #5bc0de; color: white;">
        <button type="button" class="close" data-dismiss="modal" style="color:white;">&times;</button>
        <h4 class="modal-title"><i class="fa fa-key"></i> Atur Hak Akses: <span id="akses_user_label"></span></h4>
      </div>
      <div class="modal-body">
        <form method="POST" action="index.php?par=44" id="formAkses">
          <input type="hidden" name="target_user" id="akses_target_user">
          <input type="hidden" name="save_akses" value="1">
          
          <div style="margin-bottom: 10px;">
            <button type="button" class="btn btn-success btn-xs" onclick="toggleAllAkses(true)"><i class="fa fa-check-square-o"></i> Centang Semua</button>
            <button type="button" class="btn btn-danger btn-xs" onclick="toggleAllAkses(false)"><i class="fa fa-square-o"></i> Hapus Semua</button>
          </div>
          
          <table class="table table-bordered table-striped" style="margin-bottom:0;">
            <thead><tr><th style="width:40px">#</th><th>Menu</th><th style="width:90px;text-align:center;">Akses</th></tr></thead>
            <tbody id="akses_menu_list">
            </tbody>
          </table>
          
          <div style="margin-top:15px; text-align:right;">
            <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan Hak Akses</button>
            <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
// Menu master data (from PHP)
var menuMaster = <?php echo json_encode($allMenus); ?>;
var hiddenStaff = <?php echo json_encode($hidden_staff); ?>;
var defaultOffStaff = <?php echo json_encode($default_off_staff); ?>;

function aturAkses(iduser, nama, kodjab) {
    document.getElementById('akses_target_user').value = iduser;
    document.getElementById('akses_user_label').innerText = nama;
    
    var isStaff = (kodjab != 1);
    var tbody = document.getElementById('akses_menu_list');
    tbody.innerHTML = '<tr><td colspan="3" align="center">Memuat...</td></tr>';
    
    // Fetch user's current access via AJAX
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '45_hak_akses.php?get_akses&iduser=' + encodeURIComponent(iduser), true);
    xhr.onload = function() {
        var userAccess = {};
        try { userAccess = JSON.parse(xhr.responseText); } catch(e) {}
        
        var html = '';
        var no = 1;
        // Bangun array menu beserta status checked-nya
        var menuItems = [];
        for (var i = 0; i < menuMaster.length; i++) {
            var menu = menuMaster[i];
            if (isStaff && hiddenStaff.indexOf(menu) !== -1) continue;
            var isChecked;
            if (userAccess.hasOwnProperty(menu)) {
                isChecked = (userAccess[menu] == 1);
            } else {
                if (isStaff && defaultOffStaff.indexOf(menu) !== -1) {
                    isChecked = false;
                } else {
                    isChecked = true;
                }
            }
            menuItems.push({ menu: menu, checked: isChecked });
        }
        // Urutkan: Aktif dulu, lalu abjad
        menuItems.sort(function(a, b) {
            if (a.checked !== b.checked) return a.checked ? -1 : 1;
            return a.menu.localeCompare(b.menu);
        });
        for (var i = 0; i < menuItems.length; i++) {
            var menu = menuItems[i].menu;
            var isChecked = menuItems[i].checked;
            
            var checkedAttr = isChecked ? 'checked' : '';
            var labelClass = isChecked ? 'text-success' : 'text-danger';
            var labelText = isChecked ? 'Aktif' : 'Nonaktif';
            
            html += '<tr>';
            html += '<td align="center">' + no + '</td>';
            html += '<td style="font-weight:bold;">' + menu + '</td>';
            html += '<td align="center"><label style="margin:0;cursor:pointer;">';
            html += '<input type="checkbox" name="menus[]" value="' + menu + '" ' + checkedAttr + ' onchange="updateAksesLabel(this)"> ';
            html += '<span class="' + labelClass + '" style="font-weight:bold;">' + labelText + '</span>';
            html += '</label></td>';
            html += '</tr>';
            no++;
        }
        tbody.innerHTML = html;
    };
    xhr.send();
    
    $('#aksesModal').modal('show');
}

function updateAksesLabel(cb) {
    var span = cb.nextElementSibling;
    if (cb.checked) {
        span.className = 'text-success';
        span.style.fontWeight = 'bold';
        span.innerText = 'Aktif';
    } else {
        span.className = 'text-danger';
        span.style.fontWeight = 'bold';
        span.innerText = 'Nonaktif';
    }
}

function toggleAllAkses(state) {
    var cbs = document.querySelectorAll('#akses_menu_list input[type=checkbox]');
    for (var i = 0; i < cbs.length; i++) {
        cbs[i].checked = state;
        updateAksesLabel(cbs[i]);
    }
}

function hapusKontrak(iduser) {
    if (confirm('Apakah Anda yakin ingin menghapus file kontrak kerja untuk user ' + iduser + '?')) {
        document.getElementById('edit_hapus_kontrak_iduser').value = iduser;
        document.getElementById('formHapusKontrak').submit();
    }
}
</script>
 
</body>
