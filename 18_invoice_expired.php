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
	  <li><i class="fa fa-home"></i>LAPORAN INVOICE EXPIRED</li> 
	</ol> 
	<section class="panel">
	  <header class="panel-heading">
		  
		  <form role="form"  method="GET" onSubmit="return validasi_input(this)"  action="index.php"> 
		  
			  

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
				 <input type="hidden" name="par" id="par" value="18">
				 
				 <button type="submit" name="submit" class="btn btn-primary" value="Y">Submit</button>
				 <button type="reset" class="btn btn-danger">Reset</button>
			   </div> 
		  </form>
		  <div class="clearfix">   </div>
		   
		  <h4><font color="red"><?php echo $pesan;?></font></h4>
	  </header>
	  <section class="content">
	  <div align="center">
	     
	     LAPORAN INVOICE EXPIRED 
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
		<?php echo " EXPIRED Tanggal ". $tgl. "&nbsp;&nbsp;&nbsp; Sampai Dengan " .$sdtgl; ?>
	  </div>
	   <div class="box-body">
	   <table id="contoh" class="table table-bordered table-striped table-hover"> 
	
		<div>  <thead> 
			<tr class="height: 5px;">
			 
			 
			 <th>#</th>
			  <th><font size=-1>User</font></th>
			  <th><font size=-1>Customer</font></th>
			  <th><font size=-1>No Invoice</font></th>
			  <th><font size=-1>Tgl</font></th> 
			  <th><font size=-1>Nominal</font></th> 
			  <th><font size=-1>Keterangan</font></th> 
			  <th><font size=-1>Status Bayar</font></th>		
			  <th><font size=-1>Tanggal Bayar</font></th>	
			  <th><font size=-1>Nominal Bayar</font></th>	
			  <th><font size=-1>Valid Until</font></th> 
			
			 
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
			        $strsql = "select * from tinvoice where validinvoice>='$tgl' and validinvoice<='$sdtgl'  order by validinvoice";	
			        $sql = $conn->prepare($strsql);
					
					
					$sql->execute();
					//echo $strsql;	 				
					// tampilkan
					$no=1;
					while($rs = $sql->fetch()) { 
					   $status="BELUM"; 
					   if ($rs['isbayarinvoice']==1){
					     $status ="SUDAH"; 
					   }
					   echo "   <tr>
						   <td align=center><font size=-2>".$no."</font></td>
						  <td><font size=-2>".$rs['iduserinvoice']."</font></td>
						  <td><font size=-2>".$rs['kodcustomer']."</font></td>
						  <td><font size=-2>".$rs['noinvoice']."</font></td>
						  <td><font size=-2>".$rs['tglinvoice']."</font></td>
						  <td><font size=-2>".number_format($rs['bsuinvoice'],0,',','.')."</font></td>
						  <td><font size=-2>".$rs['isiinvoice']."</font></td>
						  <td><font size=-2>".$status."</font></td>
						  <td><font size=-2>".$rs['tglbayarinvoice']."</font></td>
						  <td><font size=-2>".number_format($rs['bsubayarinvoice'],0,',','.')."</font></td>  
						  <td><font size=-2><strong>".$rs['validinvoice']."</strong></font></td> 
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
