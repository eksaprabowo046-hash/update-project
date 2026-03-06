<?php
// 
include "dbase.php";  
include "islogin.php";
$pesan = "";

// Inisialisasi variabel filter
if (isset($_GET['tgl'])) {
	$tgl 	 = trim($_GET['tgl']);  
	$sdtgl	 = trim($_GET['sdtgl']);
}else {
    $tgl 	 = date('Y-m-d');  
	$sdtgl	 = date('Y-m-d');
}

// Filter pegawai (opsional, hanya untuk admin)
$filter_iduser = "";
if (isset($_GET['filter_iduser'])) {
	$filter_iduser = trim($_GET['filter_iduser']);
}

?>	 
 
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
	  <li><i class="fa fa-home"></i>LAPORAN PERIZINAN</li> 
	</ol> 
	<section class="panel">
	  <header class="panel-heading">
		  
		  <form role="form" method="GET" action="index.php">

			<!-- Filter Pegawai (hanya tampil untuk admin) -->
			<?php if ($_SESSION['DEFAULT_KODJAB'] == 1 || $_SESSION['DEFAULT_KODJAB'] == 2) { ?>
			<div class="form-group col-xs-12 col-sm-3">
				<label>Pegawai</label>	
				<select name="filter_iduser" id="filter_iduser" class="form-control">
					<option value="">-- Semua Pegawai --</option>
				<?php
				// Mengambil daftar pegawai dari database
					$qk = $conn->prepare("SELECT iduser, nama FROM ruser WHERE stsaktif = 1 ORDER BY nik ASC, nama ASC"); 
					$qk->execute(); 
					while($rsk = $qk->fetch()){ 
					   if ($filter_iduser <> $rsk['iduser']) {
					      echo "<option value=".$rsk['iduser'].">".$rsk['nama']."</option>\n"; 
					  } else {
					      echo "<option value=".$rsk['iduser']." SELECTED>".$rsk['nama']."</option>\n"; 
					  }	  
					}
				?>
				</select> 
			</div>

			<div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-6"> 
				<label>&nbsp;</label><br>
				<input type="hidden" name="par" id="par" value="28">
				
				<button type="submit" name="submit" class="btn btn-primary" value="Y">Submit</button>
				<button type="reset" class="btn btn-danger">Reset</button>
			</div>
			<?php } ?> 
		  </form>

		  <div class="clearfix">   </div>
		   
		  <h4><font color="red"><?php echo $pesan;?></font></h4>
	  </header>
	  <section class="content">
	  
	   <div class="box-body">
	   <table id="contoh" class="table table-bordered table-striped table-hover"> 
	
		<div>  
		<thead> 
			<tr class="height: 5px;">
			  <th>No</th>
			  <th>User</th>
			  <th>Tgl Izin</th>
			  <th>Lamanya</th>
			  <th>Keperluan</th>
			  <th>Keterangan</th>
			  <th>Status</th>
			</tr>
		  </thead>
		  <tbody>
		  <?php
			if($_GET['']!==""){
				// Tentukan filter user berdasarkan role
				if ($_SESSION['DEFAULT_KODJAB'] == 1 || $_SESSION['DEFAULT_KODJAB'] == 2) {
					// Admin: bisa filter per pegawai atau semua
					if ($filter_iduser != "") {
						$iduser = $filter_iduser;
					} else {
						$iduser = '%';
					}
				}else{
					// Non-admin: hanya lihat data sendiri
					$iduser = $_SESSION['DEFAULT_IDUSER'];
				}
			    try {  	
			        $strsql = "SELECT a.idizin, b.nama, a.lamanya, a.keperluan, a.tglizin, a.keterangan, a.isapprove FROM tizin a INNER JOIN ruser b ON a.iduser=b.iduser
					WHERE a.iduser LIKE '$iduser' AND a.isapprove <> 0 AND a.stsdel=0 ORDER BY a.idizin";
			        $sql = $conn->prepare($strsql);
					$sql->execute();

					// tampilkan
					$no=1;
					 while ($rs = $sql->fetch()) {
	            		echo "  <tr>
									<td align=center><font size=-1>" . $no . "</font></td>
						  			<td><font size=-1>".$rs['nama']."</font></td>
						  			<td><font size=-1>".$rs['tglizin']."</font></td>
						  			<td><font size=-1>".$rs['lamanya']."</font></td>
						  			<td><font size=-1>".$rs['keperluan']."</font></td>
						  			<td><font size=-1>".$rs['keterangan']."</font></td>
									<td align=center><div class='label label-success'>";
									if($rs['isapprove']==1){
										echo "Approve";
									}elseif($rs['isapprove']==2){
										echo "Reject";
									}
									echo "</div></td>
								</tr>";
	           	 				$no++;
						} 
			   }
				// kondisi jika terjadi error
			   catch (PDOException $e)	{
				  echo "  
				   	<tr>
				
					
					
					
			
					</tr>  ";
			 
			  }
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

