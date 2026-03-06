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
	  <li><i class="fa fa-home"></i>LAPORAN INVOICE BELUM BAYAR</li> 
	</ol> 
	 
	   
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
			        $strsql = "select * from tinvoice where isbayarinvoice=0 order by tglinvoice";	
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
						   <td align=center><font size=-1>".$no."</font></td>
						  <td><font size=-1>".$rs['iduserinvoice']."</font></td>
						  <td><font size=-1>".$rs['kodcustomer']."</font></td>
						  <td><font size=-1>".$rs['noinvoice']."</font></td>
						  <td><font size=-1>".$rs['tglinvoice']."</font></td>
						  <td><font size=-1>".number_format($rs['bsuinvoice'],0,',','.')."</font></td>
						  <td><font size=-1>".$rs['isiinvoice']."</font></td>
						  <td><font size=-1>".$rs['validinvoice']."</font></td> 
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
