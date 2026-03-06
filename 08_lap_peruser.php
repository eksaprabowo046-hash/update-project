<?php
// 
include "dbase.php";  
include_once "helper_log_display.php";
$pesan = "";
$tgl 	 = trim($_GET['tgl']);  
$sdtgl	 = trim($_GET['sdtgl']); 

 
if (isset($_GET['ins'])) {   
    
  $kodsupplier    = trim($_GET['kodsupplier']);  
   $nmsupplier     = trim($_GET['nmsupplier']); 
   $almtsupplier   = trim($_GET['almtsupplier']);
   $telp           = trim($_GET['telp']);
   $hp  		   = trim($_GET['hp']);
   $email 		   = trim($_GET['email']);
   $kodakun 	   = trim($_GET['kodakun']);
   $kodakun1 	   = trim($_GET['kodakun1']);
   $iduser 		   = $_SESSION['DEFAULT_IDUSER'];
   $issukses 	   = 1;
   $sql = "insert into rbrgkonsinyasi (kodbrg,kodcustomer) ";
   $sql = $sql . "values ('$kodbrg','$kodcustomer') ";
 
   try { 
	   //insert
	   $qins = $conn->prepare($sql);
	   $qins->execute(); 
	   //increment nomto akhir di trigger 
	   $pesan = "<font color=blue>Kode Barang <strong>".$kodbrg."</strong> Berhasil </font>"; 
	}//try
	   catch (PDOException $e)	{
	       
		  //echo "<font color=red>Error insert. Make sure kode barang is correct.</font>" ;
		  $pesan =  "<font color=red>Error insert. Make sure <strong>kode barang</strong> is correct.</font>" ; 
    }//catch
	
 
}
   

//DELETE 
if (isset($_GET['del'])) {
   $kodsupplier = trim($_GET['kodsupplier']);	
   $kodbrg      = trim($_GET['kodbrg']);
   $nmbrg      = trim($_GET['nmbrg']);
   $nmsupplier      = trim($_GET['nmsupplier']);
   
	   //delete 
	   $sqld = "DELETE  FROM  rbrgkonsinyasi WHERE kodbrg='$kodbrg' and kodcustomer='$kodcustomer'  "; 
	   try { 
		   $qdel = $conn->prepare($sqld);
		   $qdel->execute();
		    
		   $pesan = "<font color=blue><strong>".$nmbrg."</strong> berhasil dihapus untuk supplier <strong>".$nmsupplier."</strong></font>"; 
		}//try
		   catch (PDOException $e)	{
			  //echo "<font color=red>Error insert. Make sure kode barang is correct.</font>" ;
			  //$pesan =  "<font color=red>Error delete. Make sure <strong>kode barang</strong> is correct.</font>" ;
		  echo $e;
		}//catch 
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
<script language="JavaScript" type="text/JavaScript">
<!--
function kembali(){
  //cxegah popup aktif 
    history.back();
}
//-->
</script>

 
<script languange="Javascript">
function pilih(id){
	location.replace("index.php?par=04&kodcustomer="+id);	
}
</script>


<script type="text/javascript">
function validasi_input(form){
  if (form.kodcustomer.value == "blm_pilih"){
    alert("Kelompok barang belum dipilih!");
    form.kodcustomer.focus();
    return (false);
  }

  function validasi_input(form){
  if (form.tgl.value == "blm_pilih"){
    alert("Kelompok barang belum dipilih!");
    form.tgl.focus();
    return (false);
  }
}
  
  
return (true);
}
</script>

<body >
 
<div class="row"> 
    <ol class="breadcrumb">
	  <li><i class="fa fa-home"></i>LAPORAN PERUSER</li> 
	</ol> 
	<section class="panel">
	  <header class="panel-heading">
		  
		   <form role="form"  method="GET" onSubmit="return validasi_input(this)"  action="index.php"> 
		  <div class="form-group col-xs-3 col-sm-3">
				  <label>User</label>	
				  <?php 
				  $iduser  ="";
				  if (isset($_GET['iduser'])) { 
				      $iduser  =$_GET['iduser'];
				  }
				  ?>
				   <select name="iduser" id="iduser" class="form-control"  placeholder="Customer..."    onKeyPress="if (event.keyCode==13) {tglorder.focus()}"> <option value="blm_pilih">-- Pilih User --</option>
				<?php
				//mengambil nama-nama kategori yang ada di database  
					$qk = $conn->prepare("SELECT * FROM ruser WHERE stsaktif = 1 ORDER BY nik ASC, nama ASC "); 
					$qk->execute(); 
					while($rsk = $qk->fetch()){ 
					   if ( $iduser<>$rsk['iduser']) {
					      echo "<option value=".$rsk['iduser'].">".$rsk['nama']."</option>\n"; 
					  } else {
					      echo "<option value=".$rsk['iduser']." SELECTED>".$rsk['nama']."</option>\n"; 
					  }	  
					}
			 
				?>
					</select> 
			 </div>	
			 

		  <div class="form-group col-xs-12 col-sm-2 ">
		<label>Tanggal :  </label>
		<input name="tgl" id="dp1" type="text" value="<?php echo $tgl;?>"  onKeyPress="if (event.keyCode==13) {dp1.focus()} size="16" class="form-control" value="<?php echo $tgl; ?> "> 
			  
	 
	  </div>
		
	   <div class="form-group col-xs-12 col-sm-2 ">
		<label>Sampai Tanggal :  </label>
		<input name="sdtgl" id="dp2" type="text" value="<?php echo $sdtgl?>"  size="16" class="form-control"  > 
			  
	  </div> 
			   </div>
			  <div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-6"> 
				 <input type="hidden" name="par" id="par" value="08">
				 
				 <button type="submit" name="submit" class="btn btn-primary" value="Y">Submit</button>
				 <button type="reset" class="btn btn-danger">Reset</button>
			   </div> 
		  </form>

		  <div class="clearfix">   </div>
		   
		  <h4><font color="red"><?php echo $pesan;?></font></h4>
	  </header>
	  <section class="content">
	  
	   <div class="box-body">
	   <table id="contoh" class="table table-bordered table-striped table-hover"> 
	
		<div>  <thead> 
			<tr class="height: 5px;">
			 
			 
			 <th>#</th>
			 <th>Ticket By</th>
			  <th>User</th>
			  <th>Mitra</th>
			  <th>FasOrder</th>
			  <th>Tgl Order</th> 
			  <th>Order</th>
			  <th>Order Layanan</th>  
			  <th>Status</th>  
			
			
			 
			</tr>
		  </thead>
		  <tbody>
		  <div id="show-product">
          <!-- data akan di tampilkan di sini -->
          </div>
		  <?php
			if($_GET['']!==""){
	           $kodcustomer=($_GET['kodcustomer']);
	           $tgl=($_GET['tgl']);
	            $sdtgl=($_GET['sdtgl']);
			    try {  	
			        $strsql = "SELECT a.idlog, a.iduser, a.userorder, b.nmcustomer,a.fasorder, a.tglorder, a.desorder, a.deslayan, a.tglselesai,a.isselesai from tlog a inner join rcustomer b  on a.kodcustomer =b.kodcustomer where a.userorder= '$iduser' and (a.tglorder >='$tgl' and a.tglorder<='$sdtgl' ) and a.stsdel=0 order by a.tglorder, a.idlog";
			        $sql = $conn->prepare($strsql);
					
					
					$sql->execute();
					//echo $strsql;	 				
					// tampilkan
					$no=1;
					while($rs = $sql->fetch()) { 
					    $status="Open"; 
					   if ($rs['isselesai']==1){
					     $status ="Close"; 
					   }
					   echo "   <tr>
						   <td align=center><font size=-1>".$no."</font></td>
						  <td><font size=-1>".$rs['idlog']." | ".$rs['iduser']."</font></td>
						  <td><font size=-1>".$rs['userorder']."</font></td>
						  <td><font size=-1>".$rs['nmcustomer']."</font></td>
						  <td><font size=-1>".$rs['fasorder']."</font></td>
						  <td><font size=-1>".$rs['tglorder']."</font></td>
						  <td><font size=-1>".renderLogColumn($rs['desorder'], 'desorder', $rs['idlog'], 'Uraian Order')."</font></td>
						  <td><font size=-1>".renderLogColumn($rs['deslayan'], 'deslayan', $rs['idlog'], 'Aktivitas Layanan')."</font></td>
						  <td><font size=-1>".$status."</font></td>
						  

						  
						  "?>
							</form> 
						
						  		
						  <?php	
						   echo	"</td>
						</tr> ";
						$no++;	
					} 
			   }//try
			   catch (PDOException $e)	{
				  echo "  
				   <tr>
				
					
					
					
			
					</tr>  ";
			 
			  }//catch 
			}
		  ?> 
			
		  </tbody>
		</table>
	  </div>
	  </section>
	</section>
   
</div> 

<?php echo renderLogModal(); ?>
 
</body>

</html>
