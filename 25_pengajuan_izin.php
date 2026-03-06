<?php
// 
session_start();
include "dbase.php"; 
include "islogin.php"; 
$pesan = "";
$iduser   = $_SESSION['DEFAULT_IDUSER'];

// insert
if (isset($_POST['ins'])) {
	// iduser diambil dari iduser yang login dan tidak bisa diedit 
    $tglizin 	= htmlspecialchars($_POST['tglizin'], ENT_QUOTES);
    $kategori 	= htmlspecialchars($_POST['kategori'], ENT_QUOTES);
    $lamanya 	= htmlspecialchars($_POST['lamanya'], ENT_QUOTES);
    $keperluan 	= htmlspecialchars($_POST['keperluan'], ENT_QUOTES);
	$now 		= date("Y-m-d");

    try {
		// Persiapkan statement untuk menghindari SQL injection
        $sqlin = "INSERT INTO tizin (iduser, kategori, tglizin, lamanya, keperluan, tglentri) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sqlin);
        $stmt->execute([$iduser, $kategori, $tglizin, $lamanya, $keperluan, $now]);

        // Pesan sukses
        $pesan = "<font color=blue>New record created successfully</font>";
    } catch (PDOException $e) {
        // Penanganan kesalahan
        $pesan = "<font color=red>Error insert. Make sure data is correct.</font>";
    }
}

// DELETE 
if (isset($_POST['del'])) {
   $idizin = trim($_POST['idizin']);

   try {
	  $sql = $conn->prepare("UPDATE tizin SET stsdel=1 WHERE idizin=?");
	  $sql->execute([$idizin]);
	  // Pesan sukses
	  $pesan = "<font color=blue>Record deleted successfully</font>";
   } catch (PDOException $e) {
	  // Penanganan kesalahan
	  $pesan = "<font color=red>Error delete. Make sure data is correct.</font>";
   }
	 
}	

?>	 
<!-- Tambahkan referensi ke library Bootstrap dan jQuery -->
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
  
  <!-- Tambahkan referensi ke library Bootstrap Datepicker -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>


<script>
  $(document).ready(function() {
    $('.datepicker').datepicker({
      format: 'yyyy-mm-dd',
      autoclose: true
    });
  });
</script>


<script type="text/javascript"> 

//Mematikan event enter untuk submit
function stopRKey(evt) { 
  var evt = (evt) ? evt : ((event) ? event : null); 
  var node = (evt.target) ? evt.target : ((evt.srcElement) ? evt.srcElement : null); 
  if ((evt.keyCode == 13) && (node.type=="text"))  {return false;} 
} 
document.onkeypress = stopRKey; 
</script>
<body >
 
<div class="row"> 
    <ol class="breadcrumb">
	  <li><i class="fa fa-home"></i>PENGAJUAN IZIN</li> 
	</ol> 
	<section class="panel">
	  <header class="panel-heading">
		  
		  <form role="form" method="POST" onSubmit="return validasi_input(this)" action="index.php?par=26"> 

			    <div class="col-xs-12 col-sm-6 col-md-6 col-lg-3">
			     <label>Nama</label>	
						  <?php 
						  // Ambil nama user yang login
						  $namaUser = $iduser;
						  try {
						      $qNama = $conn->prepare("SELECT nama FROM ruser WHERE iduser = ?");
						      $qNama->execute([$iduser]);
						      $rsNama = $qNama->fetch();
						      if ($rsNama) { $namaUser = $rsNama['nama']; }
						  } catch (PDOException $e) {}
						  ?>
						  <input type="text" class="form-control" value="<?php echo $namaUser; ?>" readonly style="background-color:#eee;">
						  <input type="hidden" name="user" value="<?php echo $iduser; ?>">
			    </div>
				<div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-3">
			    	<label>Kategori</label>	 
			    	<select name="kategori" id="kategori" class="form-control">
			    		<option value="" selected disabled>-- Pilih Kategori --</option>
			    		<option value="Sakit">Sakit</option>
			    		<option value="Cuti">Cuti</option>
			    	</select>
			    </div>
                 <div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-3">
			    	<label>Tanggal</label>	 
			    	<input name="tglizin" id="dp2" value="<?php echo date('Y-m-d'); ?>" size="16" class="form-control">  
			    </div>
                <div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-3">
			    	<label>Berapa Lama</label>	 
			    	<select name="lamanya" id="lamanya" class="form-control">
			    		<option value="" selected disabled>-- Pilih Durasi --</option>
			    		<option value="1-3 Jam">1-3 Jam</option>
			    		<option value="1 Hari">1 Hari</option>
			    		<option value="2 Hari">2 Hari</option>
			    		<option value="3 Hari">3 Hari</option>
			    	</select>
			    </div>
					 <div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-3">
						  <label>Keperluan</label>	
						  <input type="text" name="keperluan" id="keperluan" class="form-control">
					 </div> 			 
					 <div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-3"> 
					 <label style="visibility:hidden;">Aksi</label>
					 <div>
					 <input type="hidden" name="par" id="par" value="01">
					 <input type="hidden" name="ins" id="ins" value="Y">
					 <button type=" " name="submit" class="btn btn-primary" value="Y">Insert Data</button>
					 <button type="reset" class="btn btn-danger">Reset</button>
					 </div>
				 </div> 
			    
		  </form>
		  <div class="clearfix">   </div>
		  <h4><font color="red"><?php echo $pesan;?></font></h4>
		  <div class="box-body">
	   <table id="contoh" class="table table-bordered table-striped table-hover"> 
		  <thead> 
			<tr class="height: 5px;">
			  <th>No</th>
			  <th>User</th>
			  <th>Kategori</th>
			  <th>Tgl Izin</th>
			  <th>Lamanya</th>
			  <th>Keperluan</th>
			  <th>Delete</th>
			</tr>
		  </thead>
		  <tbody>
		  <div id="show-product">
          <!-- data akan di tampilkan di sini -->
          </div>
		  <?php 
		  	   $iduser  = $_SESSION['DEFAULT_IDUSER'];
			   $tglsekarang  = date('Y-m-d'); 
			   try { 
			        $sql = $conn->prepare("SELECT a.idizin, b.nama, a.kategori, a.lamanya, a.keperluan, a.tglizin from tizin a INNER JOIN ruser b ON a.iduser=b.iduser
										   where a.tglizin='$tglsekarang' AND a.iduser='$iduser' AND a.stsdel=0 ORDER BY a.idizin");
					$sql->execute();	 				
					// tampilkan
					$no=1;
					while($rs = $sql->fetch()) { 
					   echo "   <tr>
						  <td align=center><font size=-1>".$no."</font></td>
						  <td><font size=-1>".$rs['nama']."</font></td>
						  <td><font size=-1>".$rs['kategori']."</font></td>
						  <td><font size=-1>".$rs['tglizin']."</font></td>
						  <td><font size=-1>".$rs['lamanya']."</font></td>
						  <td><font size=-1>".$rs['keperluan']."</font></td>

						  <td>"?>
						  		<form method="POST" action="index.php?par=26">
								<input type="hidden" name="idizin" id="idizin" value="<?php echo  $rs['idizin'];?>">  
								<input type="hidden" name="par" id="par" value="01">
								<input type="hidden" name="del" id="del" value="Y">
                   				<button type="submit" class="btn btn-danger btn-xs" value="Y">Delete</button>     
							</form> 
						  </td> 
						  <?php	
						   echo	"</td>
						</tr> ";
						$no++;	
					} 
			   }//try
			   catch (PDOException $e)	{
				  echo "  
				   <tr>
					  <td></td>
					  <td></td>
					  <td></td>
					  <td></td>
					  <td></td> 
					  <td></td> 
					</tr>  ";
			 
			  }//catch 
			 
		  ?> 
			
		  </tbody>
		</table>
	  </div>
	  </header>
	</section>
   
</div> 
 
</body>

</html>
