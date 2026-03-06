<?php
// 
include "dbase.php";  
include_once "helper_log_display.php";
include "islogin.php";
$pesan = "";
if (isset($_GET['tgl'])) {
	$tgl 	 = trim($_GET['tgl']);  
	$sdtgl	 = trim($_GET['sdtgl']);
    $kodcustomer = 	trim($_GET['kodcustomer']); 
}else {
    $tgl 	 = date('Y-m-d');  
	$sdtgl	 = date('Y-m-d');
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
	  <li><i class="fa fa-home"></i>LAPORAN PER CUSTOMER</li> 
	</ol> 
	<section class="panel">
	  <header class="panel-heading">
		  
		  <form role="form"  method="GET" onSubmit="return validasi_input(this)"  action="index.php"> 
		  
			 <div class="form-group col-xs-12 col-sm-3">
				  <label>Nama Customer</label>	
				  <?php 
				  $kodcustomer  ="";
				  if (isset($_GET['kodcustomer'])) { 
				     if (!isset($_GET['ins']) && !isset($_GET['del'])) {
				       $kodcustomer   =($_GET['kodcustomer']);//INSERT
					 } 
				  }
				  ?>
				   <select name="kodcustomer" id="kodcustomer" class="form-control"    onKeyPress="if (event.keyCode==13) {kodcustomer.focus()}">
					<option value="blm_pilih">--Pilih  Customer--</option>
				<?php
				//mengambil nama-nama kategori yang ada di database  
					$qk = $conn->prepare("SELECT kodcustomer, nmcustomer FROM rcustomer  ORDER BY nmcustomer"); 
					$qk->execute(); 
					while($rsk = $qk->fetch()){ 
					   if ( $kodcustomer<>$rsk['kodcustomer']) {
					      echo "<option value=".$rsk['kodcustomer'].">".$rsk['nmcustomer']."</option>\n"; 
					  } else {
					      echo "<option value=".$rsk['kodcustomer']." SELECTED>".$rsk['nmcustomer']."</option>\n"; 
					  }	  
					}
			 
				?>
					</select> 
			 </div>	

		  <div class="form-group col-xs-12 col-sm-2 ">
		<label>Tanggal :  </label>
		<input name="tgl" id="dp1" type="text" value="<?php echo $tgl?>"   onKeyPress="if (event.keyCode==13) {dp1.focus()} size="16" class="form-control"> 
			  
	 
	  </div>
	

	   <div class="form-group col-xs-12 col-sm-2 ">
		<label>Sampai Tanggal :  </label>
		<input name="sdtgl" id="dp2" type="text" value="<?php echo $sdtgl?>"  size="16" class="form-control"  > 
			  
	  </div> 
		
			   </div>
			  <div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-6"> 
				 <input type="hidden" name="par" id="par" value="04">
				 
				 <button type="submit" name="submit" class="btn btn-primary" value="Y">Submit</button>
				 <button type="reset" class="btn btn-danger">Reset</button>
			   </div> 
		  </form>
		  <div class="clearfix">   </div>
		   
		  <h4><font color="red"><?php echo $pesan;?></font></h4>
	  </header>
	  <section class="content">
	  <div align="center">
	     
	    <?php
	     // echo $kodsupplier;

		  if ($kodcustomer  <>"" ) { 
			 $sqlk = $conn->prepare("select *  from rcustomer where kodcustomer= '$kodcustomer'");
			 $sqlk->execute();	
			 $rsk = $sqlk->fetch(); 
			 echo " LOG BOOK ".$rsk['nmcustomer'];

		  }
		?>  
<br>
		<?php echo " Dari tanggal ". $tgl. "&nbsp;&nbsp;&nbsp; Sampai tanggal " .$sdtgl; ?>
	  </div>
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
			  <th>Tgl Selesai</th>
			
			 
			</tr>
		  </thead>
		  <tbody>
		  <div id="show-product">
          <!-- data akan di tampilkan di sini -->
          </div>
		  <?php
			if($_GET['']!==""){
	           $kodcustomer=($_GET['kodcustomer']); 
			    try {  	
			        $strsql = "SELECT a.idlog,a.userorder, a.iduser, b.nmcustomer, a.tglorder, a.desorder, a.fasorder, a.deslayan, a.isselesai,a.tglselesai from tlog a inner join rcustomer b  on a.kodcustomer =b.kodcustomer where a.kodcustomer= '$kodcustomer' and (a.tglorder >='$tgl' and a.tglorder<='$sdtgl' ) and a.stsdel=0 order by a.tglorder, a.idlog";	
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
						   <td><font size=-1>".$rs['idlog']." | ".$rs['userorder']."</font></td>
						  <td><font size=-1>".$rs['iduser']."</font></td>
						  <td><font size=-1>".$rs['nmcustomer']."</font></td>
						  <td><font size=-1>".$rs['fasorder']."</font></td>
						  <td><font size=-1>".$rs['tglorder']."</font></td>
						  <td><font size=-1>".renderLogColumn($rs['desorder'], 'desorder', $rs['idlog'], 'Uraian Order')."</font></td>
						  <td><font size=-1>".renderLogColumn($rs['deslayan'], 'deslayan', $rs['idlog'], 'Aktivitas Layanan')."</font></td>
						  <td><font size=-1>".$status."</font></td>
						  <td><font size=-1>".$rs['tglselesai']."</font></td> 
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
