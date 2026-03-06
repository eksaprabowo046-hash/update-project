<?php
// 
include "dbase.php";  
include "islogin.php";
$pesan = "";
if (isset($_GET['submit'])) {
	$tgl 	 = trim($_GET['tgl']);  
	$sdtgl	 = trim($_GET['sdtgl']);
    $statusbayar = 	trim($_GET['statusbayar']); 
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
  if (form.statusbayar.value == "blm_pilih"){
    alert("Status Bayar belum dipilih!");
    form.statusbayar.focus();
    return (false);
  }

  function validasi_input(form){
  if (form.tgl.value == "blm_pilih"){
    alert("Kelompok barang belum dipilih!");
    form.tgl.focus();
    return (false);
  }
  
  
return (true);
}
</script>

<body >
 
<div class="row"> 
    <ol class="breadcrumb">
	  <li><i class="fa fa-home"></i>LAPORAN INVOICE</li> 
	</ol> 
	<section class="panel">
	  <header class="panel-heading">
		  
		  <form role="form"  method="GET" onSubmit="return validasi_input(this)"  action="index.php"> 
		  
			 <div class="form-group col-xs-12 col-sm-3">
				  <label>Status Bayar</label>	 
				   <select name="statusbayar" id="statusbayar" class="form-control"    onKeyPress="if (event.keyCode==13) {tgl.focus()}">
					<option value="1" <?php if ($statusbayar=="1") {echo "SELECTED";} ?>>Terbayar</option>
					<option value="0" <?php if ($statusbayar=="0") {echo "SELECTED";} ?>>Belum</option>
					<option value="3" <?php if ($statusbayar=="3") {echo "SELECTED";} ?>>Semua</option> 
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
				 <input type="hidden" name="par" id="par" value="15">
				 
				 <button type="submit" name="submit" class="btn btn-primary" value="Y">Submit</button>
				 <button type="reset" class="btn btn-danger">Reset</button>
			   </div> 
		  </form>
		  <div class="clearfix">   </div>
		   
		  <h4><font color="red"><?php echo $pesan;?></font></h4>
	  </header>
	  <section class="content">
	  <div align="center">
	     
	     LAPORAN INVOICE 
		 <?php 
			if ($statusbayar=="1") {
				echo "SUDAH TERBAYAR";
				
			} else if ($statusbayar=="0") {
				echo "BELUM TERBAYAR";
			} else if ($statusbayar=="3") {
				echo "SEMUA";
				$statusbayar = '%';
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
			  <th>User</th>
			  <th>Customer</th>
			  <th>No Invoice</th>
			  <th>Tgl</th> 
			  <th>Nominal</th> 
			  <th>Keterangan</th>  
			  <th>Valid Until</th>
			  <th>Status Bayar</th> 
			  <th>Tgl Bayar</th> 
			  <th>Nominal</th>
			
			 
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
			        $strsql = "select * from tinvoice where tglinvoice>='$tgl' and tglinvoice<='$sdtgl' and isbayarinvoice like '$statusbayar' order by tglbayarinvoice";	
			        $sql = $conn->prepare($strsql);
					
					
					$sql->execute();
					//echo $strsql;	 				
					// tampilkan
					$no=1; 
					$tglbayar="";
					while($rs = $sql->fetch()) { 
						if ($rs['tglbayarinvoice']=='2000-01-01') {
							$tglbayar ="";
						}else {
							$tglbayar = $rs['tglbayarinvoice'];	
						}
					   $status="BELUM"; 
					   if ($rs['isbayarinvoice']==1){
					     $status ="SUDAH"; 
					   }
					   echo "   <tr>
						   <td align=center><font size=-1>".$no."</font></td>
						  <td><font size=-1>".$rs['iduserinvoice']."</font></td>
						  <td><font size=-1>".$rs['kodcustomer']."</font></td>
						  <td><font size=-1>".$rs['noinvoice']."</font></td>
						  <td><font size=-1>".$rs['tglinvoice']."</font></td>
						  <td><font size=-1>".number_format($rs['bsuinvoice'],0,',','.')."</font></td>
						  <td><font size=-1>".$rs['isiinvoice']."</font></td>
						  <td><font size=-1>".$rs['validinvoice']."</font></td>
						  <td><font size=-1>".$status."</font></td>
						  <td><font size=-1>".$tglbayar."</font></td>
						  <td><font size=-1>".number_format($rs['bsubayarinvoice'],0,',','.')."</font></td> 
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
 
</body>

</html>
