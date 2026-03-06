<?php
// 
session_start();
include "dbase.php"; 
include "islogin.php"; 
$iduser = $_SESSION['DEFAULT_IDUSER'];


// Insert Grup
if (isset($_POST['ins_grup'])) {
    $nmgroup = htmlspecialchars(trim($_POST['nmgroup']), ENT_QUOTES);
    
    if (!empty($nmgroup)) {
        try {
            $sql = "INSERT INTO tgroup_akun (nmgroup) VALUES (?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$nmgroup]);
            $pesan = "<font color=blue>Grup akun berhasil ditambahkan</font>";
        } catch (PDOException $e) {
            $pesan = "<font color=red>Error insert grup. " . $e->getMessage() . "</font>";
        }
    } else {
        $pesan = "<font color=red>Nama grup tidak boleh kosong</font>";
    }
}

// Delete Grup
if (isset($_POST['del_grup'])) {
    $kodgroup = trim($_POST['kodgroup']);
    
    $cek = $conn->prepare("SELECT COUNT(*) as jml FROM takun WHERE kodgroup = ?");
    $cek->execute([$kodgroup]);
    $row = $cek->fetch();
    
    if ($row['jml'] > 0) {
        $pesan = "<font color=red>Grup tidak bisa dihapus karena masih dipakai oleh " . $row['jml'] . " akun</font>";
    } else {
        try {
            $sql = $conn->prepare("DELETE FROM tgroup_akun WHERE kodgroup = ?");
            $sql->execute([$kodgroup]);
            $pesan = "<font color=blue>Grup berhasil dihapus</font>";
        } catch (PDOException $e) {
            $pesan = "<font color=red>Error delete grup</font>";
        }
    }
}


// Insert Akun
if (isset($_POST['ins_akun'])) {
    $kodakun = htmlspecialchars(trim($_POST['kodakun']), ENT_QUOTES);
    $kodgroup = htmlspecialchars(trim($_POST['kodgroup']), ENT_QUOTES);
    $nmakun = htmlspecialchars(trim($_POST['nmakun']), ENT_QUOTES);
    $tipe = htmlspecialchars(trim($_POST['tipe']), ENT_QUOTES);
    
    if (!empty($kodakun) && !empty($nmakun) && !empty($kodgroup)) {
        try {
            $sql = "INSERT INTO takun (kodakun, kodgroup, nmakun, tipe) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$kodakun, $kodgroup, $nmakun, $tipe]);
            $pesan = "<font color=blue>Akun berhasil ditambahkan</font>";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate') !== false) {
                $pesan = "<font color=red>Kode akun sudah ada, gunakan kode lain</font>";
            } else {
                $pesan = "<font color=red>Error insert akun. " . $e->getMessage() . "</font>";
            }
        }
    } else {
        $pesan = "<font color=red>Semua field harus diisi</font>";
    }
}

// Update Akun
if (isset($_POST['upd_akun'])) {
    $kodakun_lama = htmlspecialchars(trim($_POST['kodakun_lama']), ENT_QUOTES);
    $kodakun = htmlspecialchars(trim($_POST['kodakun']), ENT_QUOTES);
    $kodgroup = htmlspecialchars(trim($_POST['kodgroup']), ENT_QUOTES);
    $nmakun = htmlspecialchars(trim($_POST['nmakun']), ENT_QUOTES);
    $tipe = htmlspecialchars(trim($_POST['tipe']), ENT_QUOTES);
    
    if (!empty($kodakun) && !empty($nmakun) && !empty($kodgroup)) {
        try {
            $sql = "UPDATE takun SET kodakun=?, kodgroup=?, nmakun=?, tipe=? WHERE kodakun=?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$kodakun, $kodgroup, $nmakun, $tipe, $kodakun_lama]);
            $pesan = "<font color=blue>Akun berhasil diupdate</font>";
        } catch (PDOException $e) {
            $pesan = "<font color=red>Error update akun. " . $e->getMessage() . "</font>";
        }
    } else {
        $pesan = "<font color=red>Semua field harus diisi</font>";
    }
}

// Delete Akun
if (isset($_POST['del_akun'])) {
    $kodakun = trim($_POST['kodakun']);
    
    $cek = $conn->prepare("SELECT COUNT(*) as jml FROM tkas WHERE kodakun = ?");
    $cek->execute([$kodakun]);
    $row = $cek->fetch();
    
    if ($row['jml'] > 0) {
        $pesan = "<font color=red>Akun tidak bisa dihapus karena sudah dipakai di " . $row['jml'] . " transaksi</font>";
    } else {
        try {
            $sql = $conn->prepare("DELETE FROM takun WHERE kodakun = ?");
            $sql->execute([$kodakun]);
            $pesan = "<font color=blue>Akun berhasil dihapus</font>";
        } catch (PDOException $e) {
            $pesan = "<font color=red>Error delete akun</font>";
        }
    }
}

?>	 
<!-- Tambahkan referensi ke library Bootstrap dan jQuery -->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>

<script type="text/javascript"> 
//Mematikan event enter untuk submit
function stopRKey(evt) { 
  var evt = (evt) ? evt : ((event) ? event : null); 
  var node = (evt.target) ? evt.target : ((evt.srcElement) ? evt.srcElement : null); 
  if ((evt.keyCode == 13) && (node.type=="text"))  {return false;} 
} 
document.onkeypress = stopRKey; 

// Fungsi untuk membuka modal edit
function editAkun(kodakun, kodgroup, nmakun, tipe) {
    document.getElementById('edit_kodakun_lama').value = kodakun;
    document.getElementById('edit_kodakun').value = kodakun;
    document.getElementById('edit_kodgroup').value = kodgroup;
    document.getElementById('edit_nmakun').value = nmakun;
    document.getElementById('edit_tipe').value = tipe;
    $('#modalEditAkun').modal('show');
}
</script>
<body>
 
<div class="row"> 
    <ol class="breadcrumb" style="display: flex; justify-content: space-between; align-items: center;">
	  <span><i class="fa fa-home"></i> CREATE AKUN</span>
	  <button type="button" class="btn btn-info btn-sm" data-toggle="modal" data-target="#modalGrup">
	      <i class="fa fa-cog"></i> Kelola Grup
	  </button>
	</ol> 
	
	<!-- MODAL KELOLA GRUP AKUN -->
	<div class="modal fade" id="modalGrup" tabindex="-1" role="dialog" aria-labelledby="modalGrupLabel">
	  <div class="modal-dialog" role="document">
	    <div class="modal-content">
	      <div class="modal-header">
	        <h4 class="modal-title" id="modalGrupLabel">Kelola Grup Akun</h4>
	      </div>
	      <div class="modal-body">
	      	<form role="form" method="POST" action="index.php?par=39" class="form-inline" style="margin-bottom: 15px;"> 
			   <div class="form-group">
				  <input type="text" name="nmgroup" class="form-control" placeholder="Nama Grup Baru" style="width: 250px;">
			   </div>
			   <input type="hidden" name="ins_grup" value="Y">
			   <button type="submit" class="btn btn-primary btn-sm">Tambah</button>
		    </form>
		    
		    <table class="table table-bordered table-striped table-condensed"> 
			  <thead> 
				<tr>
				  <th width="50">Kode</th>
				  <th>Nama Grup</th>
				  <th width="70">Hapus</th>
				</tr>
			  </thead>
			  <tbody>
			  <?php 
				try { 
					$sql = $conn->prepare("SELECT * FROM tgroup_akun ORDER BY kodgroup");
					$sql->execute();				
					while($rs = $sql->fetch()) { 
					   echo "<tr>
						  <td align=center>".$rs['kodgroup']."</td>
						  <td>".$rs['nmgroup']."</td>
						  <td>"?>
						  		<form method="POST" action="index.php?par=39" style="margin:0;">
								<input type="hidden" name="kodgroup" value="<?php echo $rs['kodgroup'];?>">  
								<input type="hidden" name="del_grup" value="Y">
               					<button type="submit" class="btn btn-danger btn-xs" onclick="return confirm('Yakin hapus grup ini?')">Hapus</button>     
							</form> 
						  </td> 
						  <?php	
					   echo	"</tr>";	
					} 
				} catch (PDOException $e) {
				  echo "<tr><td colspan='3'>Error load data</td></tr>";
				} 
			  ?> 
			  </tbody>
			</table>
	      </div>
	      <div class="modal-footer">
	        <button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
	      </div>
	    </div>
	  </div>
	</div>
	
	<!-- MODAL EDIT AKUN -->
	<div class="modal fade" id="modalEditAkun" tabindex="-1" role="dialog" aria-labelledby="modalEditAkunLabel">
	  <div class="modal-dialog" role="document">
	    <div class="modal-content">
	      <div class="modal-header">
	        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
	          <span aria-hidden="true">&times;</span>
	        </button>
	        <h4 class="modal-title" id="modalEditAkunLabel">Edit Akun</h4>
	      </div>
	      <form role="form" method="POST" action="index.php?par=39">
	      <div class="modal-body">
	      	<input type="hidden" name="kodakun_lama" id="edit_kodakun_lama">
	      	<input type="hidden" name="upd_akun" value="Y">
	      	
	      	<div class="form-group">
	      		<label>Kode Akun</label>
	      		<input type="text" name="kodakun" id="edit_kodakun" class="form-control">
	      	</div>
	      	<div class="form-group">
	      		<label>Grup</label>
	      		<select name="kodgroup" id="edit_kodgroup" class="form-control">
	      			<?php
					$qg = $conn->prepare("SELECT * FROM tgroup_akun ORDER BY kodgroup");
					$qg->execute();
					while($rg = $qg->fetch()) {
						echo "<option value='".$rg['kodgroup']."'>".$rg['nmgroup']."</option>";
					}
					?>
	      		</select>
	      	</div>
	      	<div class="form-group">
	      		<label>Nama Akun</label>
	      		<input type="text" name="nmakun" id="edit_nmakun" class="form-control">
	      	</div>
	      	<div class="form-group">
	      		<label>Tipe</label>
	      		<select name="tipe" id="edit_tipe" class="form-control">
	      			<option value="H">Header</option>
	      			<option value="D">Detail</option>
	      		</select>
	      	</div>
	      </div>
	      <div class="modal-footer">
	        <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
	        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
	      </div>
	      </form>
	    </div>
	  </div>
	</div>
	
	<!-- SECTION UTAMA: KELOLA AKUN -->
	<section class="panel">
	  <header class="panel-heading">
		  
		  <form role="form" method="POST" action="index.php?par=39"> 
			 <div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-2">
				  <label>Kode Akun</label>	
				  <input type="text" name="kodakun" class="form-control" placeholder="Contoh: 5.1.06">
			 </div>
			 <div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-2">
				  <label>Grup</label>	
				  <select name="kodgroup" class="form-control">
				  	<option value="">-- Pilih Grup --</option>
				  	<?php
					$qg = $conn->prepare("SELECT * FROM tgroup_akun ORDER BY kodgroup");
					$qg->execute();
					while($rg = $qg->fetch()) {
						echo "<option value='".$rg['kodgroup']."'>".$rg['nmgroup']."</option>";
					}
					?>
				  </select>
			 </div>
			 <div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-3">
				  <label>Nama Akun</label>	
				  <input type="text" name="nmakun" class="form-control" placeholder="Contoh: Biaya Perlengkapan">
			 </div>
			 <div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-2">
				  <label>Tipe</label>	
				  <select name="tipe" class="form-control">
				  	<option value="H">Header</option>
				  	<option value="D" selected>Detail</option>
				  </select>
			 </div>
			 <div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-3"> 
				 <label>&nbsp;</label><br>
				 <input type="hidden" name="ins_akun" value="Y">
				 <button type="submit" class="btn btn-primary">Tambah Akun</button>
				 <button type="reset" class="btn btn-danger">Reset</button>
			 </div> 
		  </form>
		  <div class="clearfix"></div>
		  <h4><font color="red"><?php echo $pesan;?></font></h4>
	  </header>
	  <section class="content">
	    <div align="center"><strong>DAFTAR AKUN</strong></div>
		  <div class="box-body">
		   <table id="tabel_akun" class="table table-bordered table-striped table-hover"> 
			  <thead> 
				<tr>
				  <th width="80">Kode Akun</th>
				  <th width="100">Grup</th>
				  <th>Nama Akun</th>
				  <th width="60">Tipe</th>
				  <th width="120">Aksi</th>
				</tr>
			  </thead>
			  <tbody>
			  <?php 
				   try { 
				        $sql = $conn->prepare("SELECT a.kodakun, a.kodgroup, a.nmakun, a.tipe, b.nmgroup 
				        					   FROM takun a 
				        					   INNER JOIN tgroup_akun b ON a.kodgroup = b.kodgroup 
				        					   ORDER BY a.kodakun");
						$sql->execute();				
						while($rs = $sql->fetch()) { 
						   $tipeTeks = ($rs['tipe'] == 'H') ? 'Header' : 'Detail';
						   echo "<tr>
							  <td align=center><font size=-1>".$rs['kodakun']."</font></td>
							  <td><font size=-1>".$rs['nmgroup']."</font></td>
							  <td><font size=-1>".$rs['nmakun']."</font></td>
							  <td align=center><font size=-1>".$tipeTeks."</font></td>
							  <td>"?>
							  		<button type="button" class="btn btn-warning btn-xs" onclick="editAkun('<?php echo $rs['kodakun'];?>', '<?php echo $rs['kodgroup'];?>', '<?php echo addslashes($rs['nmakun']);?>', '<?php echo $rs['tipe'];?>')">Edit</button>
							  		<form method="POST" action="index.php?par=39" style="display:inline;">
									<input type="hidden" name="kodakun" value="<?php echo $rs['kodakun'];?>">  
									<input type="hidden" name="del_akun" value="Y">
                   					<button type="submit" class="btn btn-danger btn-xs" onclick="return confirm('Yakin hapus akun ini?')">Delete</button>     
								</form> 
							  </td> 
							  <?php	
							   echo	"</td>
							</tr> ";	
						} 
				   }
				   catch (PDOException $e)	{
					  echo "<tr><td colspan='5'>Error load data: ".$e->getMessage()."</td></tr>";
				  } 
			  ?> 
			  </tbody>
			</table>
		  </div>
	  </section>
	</section>
   
</div> 
 
</body>

</html>

<script>
$(document).ready(function () {
    const tableId = '#tabel_akun';

    // Cegah DataTable dipanggil lebih dari sekali
    if ($.fn.DataTable.isDataTable(tableId)) {
        $(tableId).DataTable().destroy();
    }

    $(tableId).DataTable({
        pageLength: 10,
        lengthChange: true,
        searching: true,
        ordering: true,
        info: true,
        autoWidth: false,
        language: {
            search: "Search:",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            infoEmpty: "Tidak ada data",
            zeroRecords: "Data tidak ditemukan",
            paginate: {
                next: ">",
                previous: "<"
            }
        }
    });
});
</script>
