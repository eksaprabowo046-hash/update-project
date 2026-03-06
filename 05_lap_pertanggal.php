<?php
// 
include "dbase.php";  
include_once "helper_log_display.php";
include "islogin.php";
$pesan = "";
if (isset($_GET['tgl'])) {
	$tgl 	 = trim($_GET['tgl']);  
	$sdtgl	 = trim($_GET['sdtgl']);
	// $kodcustomer = 	trim($_GET['kodcustomer']);
	$jnsbisnis = 	trim($_GET['jnsbisnis']);
}else {
    $tgl 	 = date('Y-m-d');  
	$sdtgl	 = date('Y-m-d');
	// $kodcustomer = "";
	$jnsbisnis = "";
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
  if (form.kodbrg.value == "blm_pilih"){
    alert("Kelompok barang belum dipilih!");
    form.kodbrg.focus();
    return (false);
  }
}
  
  
return (true);
}
</script>

<body >
 
<div class="row"> 
    <ol class="breadcrumb">
	  <li><i class="fa fa-home"></i>LAPORAN PER TANGGAL</li> 
	</ol> 
	<section class="panel">
	  <header class="panel-heading">
		<form role="form"  method="GET" onSubmit="return validasi_input(this)"  action="index.php"> 
			<div class="form-group col-xs-12 col-sm-2 ">
				<label>Tanggal :  </label>
				<input name="tgl" id="dp1" type="text"  onKeyPress="if (event.keyCode==13) {dp1.focus()} size="16" class="form-control" value="<?php echo $tgl; ?>"> 
			</div>
			

			<div class="form-group col-xs-12 col-sm-2 ">
				<label>Sampai Tanggal :  </label>
				<input name="sdtgl" id="dp2" type="text"   size="16" class="form-control" value="<?php echo $sdtgl; ?>"> 
			</div> 

			<!-- <div class="form-group col-xs-12 col-sm-2 ">
				<label>Customer </label>
				<select name="kodcustomer" id="kodcustomer" class="form-control" onKeyPress="if (event.keyCode==13) {kodcustomer.focus()}">
					<option value="" disabled selected>-- Customer --</option>
					<?php
						//mengambil nama-nama kategori yang ada di database  
						// $qk = $conn->prepare("SELECT * FROM rcustomer ORDER BY kodcustomer ");
						// $qk->execute();
						// while ($rsk = $qk->fetch()) {
						// 	$selected = ($kodcustomer == $rsk['kodcustomer']) ? "SELECTED" : "";
						// 	echo "<option value='" . $rsk['kodcustomer'] . "' $selected>" . $rsk['nmcustomer'] . "</option>";
						// }
					?>
				</select>
			</div>  -->
			<div class="form-group col-xs-12 col-sm-2 ">
				<label>Kelompok Bisnis</label>	
				<select name="jnsbisnis" id="jnsbisnis" class="form-control" onKeyPress="if (event.keyCode==13) {kodcustomer.focus()}">
					<option value="" <?= $jnsbisnis == "" ? "selected" : ""; ?> disabled>-- Pilih Jenis Bisnis --</option>
					<option value="A" <?= $jnsbisnis == "A" ? "selected" : ""; ?>>A:Administration</option>
					<option value="B" <?= $jnsbisnis == "B" ? "selected" : ""; ?>>B:Business</option>
					<option value="D" <?= $jnsbisnis == "D" ? "selected" : ""; ?>>D:Developing</option>
					<option value="M" <?= $jnsbisnis == "M" ? "selected" : ""; ?>>M:Maintenance</option>
				</select> 
			</div> 
				
</div>
					<div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-6"> 
						<input type="hidden" name="par" id="par" value="05">
						
						<button type="submit" name="submit" class="btn btn-primary" value="Y">Submit</button>
						<button type="reset" class="btn btn-danger">Reset</button>
					</div> 
		  </form>
		  <div class="clearfix">   </div>
		   
		  <h4><font color="red"><?php echo $pesan;?></font></h4>
	  </header>
	 <div align="center">
	   <?php echo "LOG BOOK" ?>
<br>
		<?php echo " Dari tanggal ". $tgl. "&nbsp;&nbsp;&nbsp; Sampai tanggal " .$sdtgl; ?>
	  </div>
	   <div class="box-body">
	   <table id="contoh" class="table table-bordered table-striped table-hover"> 
		<br>
		<div>  <thead> 
			<tr class="height: 5px;">
			  <th>No</th>
			  <th>Ticket</th>
			  <th>User</th>
			  <th>Mitra</th>
			  <th>Order By</th>
			  <th>Tgl Order</th> 
			  <th>Tgl Target</th>
			  <th>Prioritas</th>
			  <th>Order</th>
			  <th>Order Layanan</th>  
			  <th>Status</th>
			  <th>Tgl Selesai</th>
			
			
			</tr>
		  </thead>
		  <tbody>
		  <div id="show-product">
          <!-- data akan di tampilkan di sini -->
          </div>
		  <?php
			if($_GET['']!==""){ 
			    try {
					// $swhere = " WHERE (a.tglorder >='$tgl' and a.tglorder<='$sdtgl') and a.stsdel=0";
					$swhere = " WHERE (a.tglorder BETWEEN '$tgl' AND '$sdtgl') and a.stsdel=0";
					// if ($kodcustomer != "") {
					// 	$swhere .= " AND a.kodcustomer = '$kodcustomer'";
					// }
					if ($jnsbisnis != "") {
						$swhere .= " AND a.jnsbisnis = '$jnsbisnis'";
					}

			        $strsql = "SELECT a.userorder,a.idlog,a.iduser, b.nmcustomer, a.tglorder,a.fasorder,a.isselesai, a.desorder, a.deslayan, a.tglselesai, a.tgltarget, a.prioritas
								from tlog a inner join rcustomer b  on a.kodcustomer =b.kodcustomer 
								{$swhere}
								order by a.tglorder, a.idlog";	
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

					   // prioritas Sangat tinggi, Tinggi, Biasa
					   $prioritas="";
					   switch ($rs['prioritas']) {
						   case 1:
							   $prioritas="Sangat Tinggi";
							   break;
						   case 2:
							   $prioritas="Tinggi";
							   break;
						   case 3:
							   $prioritas="Biasa";
							   break;
						   default:
							   $prioritas="";
							   break;
					   }
					   echo "   <tr>
						   <td align=center><font size=-1>".$no."</font></td>
						   <td><font size=-1>".$rs['idlog']." | ".$rs['userorder']."</font></td>
						  <td><font size=-1>".$rs['iduser']."</font></td>
						  <td><font size=-1>".$rs['nmcustomer']."</font></td>
						  <td><font size=-1>".$rs['fasorder']."</font></td>
						  <td><font size=-1>".$rs['tglorder']."</font></td>
						  <td><font size=-1>".$rs['tgltarget']."</font></td>
						  <td><font size=-1>".$prioritas."</font></td>
						  <td><font size=-1>".renderLogColumn($rs['desorder'], 'desorder', $rs['idlog'], 'Uraian Order')."</font></td>
						  <td><font size=-1>".renderLogColumn($rs['deslayan'], 'deslayan', $rs['idlog'], 'Aktivitas Layanan')."</font></td>
						  <td><font size=-1>".$status."</font></td>
						  <td><font size=-1>".$rs['tglselesai']."</font></td> "?>
						  		
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
