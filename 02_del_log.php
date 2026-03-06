<?php
// 
include "dbase.php"; 
include "islogin.php"; 
$pesan = "";
if (isset($_GET['tgl'])) {
	$tgl 	 = trim($_GET['tgl']);  
	$sdtgl	 = trim($_GET['sdtgl']); 
	
}else {
    $tgl 	 = date('Y-m-d');  
	$sdtgl	 = date('Y-m-d');
}
$iduser   = $_SESSION['DEFAULT_IDUSER'];

//DELETE 
if (isset($_GET['del'])) {
   $idlog = $_GET['idlog']+0;	  
   
   $isbolehdel = 0;	  
   $sqlcek = "select * from tlog WHERE idlog=$idlog and iduser='$iduser' "; 
    
    try { 
	   $qcek = $conn->prepare($sqlcek);
	   $qcek->execute(); 
	   if($qcek->rowCount() <1 ){   
		  $pesan = "<font color=red>Log <strong>".$idlog." </strong> bukan milik user <strong>".$iduser." </strong></font><br>";
		  $pesan = $pesan. "<font color=red>Tidak boleh delete yang bukan lognya</font>";
	   }else {
	     $isbolehdel = 1;
		  
	   }				
	    
	}//try
	   catch (PDOException $e)	{
		  //echo "<font color=red>Error insert. Make sure kode barang is correct.</font>" ;
		  $pesan =  "<font color=red>Error checking. Make sure <strong>".$idlog."</strong> is correct.</font>" ;
	 
	}//catch  
 
    if ($isbolehdel == 1){
	   $sqld = "UPDATE tlog SET stsdel=1 WHERE idlog='$idlog'"; 
	   try { 
		   $qdel = $conn->prepare($sqld);
		   $qdel->execute();
		    
		   $pesan = "<font color=blue>One record <strong>".$idlog."</strong> deleted successfully</font>"; 
		}//try
		   catch (PDOException $e)	{
			  //echo "<font color=red>Error insert. Make sure kode barang is correct.</font>" ;
			  $pesan =  "<font color=red>Error delete. Make sure <strong>idlog</strong> is correct.</font>" ;
		 
		}//catch 
	}	
	 
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
	location.replace("index.php?par=02&kodcustomer="+id);	
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
<script language="javascript">
function ConfirmDelete()
{
  var x = confirm("Are you sure you want to delete?");
  if (x)
      return true;
  else
      return false;
}
</script>
<body >
 
<div class="row"> 
    <ol class="breadcrumb">
	  <li><i class="fa fa-home"></i> HAPUS LOG</li> 
	</ol> 
	<section class="panel">
	  <header class="panel-heading">
		  
		  <form role="form"  method="GET" onSubmit="return validasi_input(this)"  action="index.php"> 
		   
		  <div class="form-group col-xs-12 col-sm-2 ">
		<label>Tanggal :  </label>
		<input name="tgl" id="dp1" type="text" value="<?php echo $tgl; ?>"    onKeyPress="if (event.keyCode==13) {dp1.focus()}" size="16" class="form-control"> 
			  
	 
	  </div>
	

	   <div class="form-group col-xs-12 col-sm-2 ">
		<label>Sampai Tanggal :  </label>
		<input name="sdtgl" id="dp2" type="text" value="<?php echo $sdtgl; ?>" size="16" class="form-control"> 
			  
	  </div> 
		
			   </div>
			  <div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-6"> 
				 <input type="hidden" name="par" id="par" value="02">
				 
				 <button type="submit" name="submit" class="btn btn-primary" value="Y">Submit</button>
				 <button type="reset" class="btn btn-danger">Reset</button>
			   </div> 
		  </form>
		  <div class="clearfix">   </div>
		   
		  <h4><font color="red"><?php echo $pesan;?></font></h4>
	  </header>
	  <section class="content">
	  <div align="center">
	<div style="margin-bottom: 30px;">
		<?php echo " Dari tanggal ". $tgl. "&nbsp;&nbsp;&nbsp; Sampai tanggal " .$sdtgl; ?>
	</div>
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
			  <th>Hapus</th>
			
			 
			</tr>
		  </thead>
		  <tbody>
		  <div id="show-product">
          <!-- data akan di tampilkan di sini -->
          </div>
		  <?php
			if($_GET['']!==""){ 
			    try {  	
			        $strsql = "SELECT a.idlog, a.iduser, b.nmcustomer, a.tglorder, a.desorder,a.isselesai,a.fasorder, a.deslayan, a.tglselesai, a.userorder from tlog a inner join rcustomer b  on a.kodcustomer =b.kodcustomer where (a.tglorder >='$tgl' and a.tglorder<='$sdtgl' ) and a.stsdel=0 order by a.tglorder, a.idlog";	
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
						   <td align=center>".$no."</td>
						  <td><font size=-1>".$rs['idlog']." | ".$rs['userorder']."</font></td>
						  <td>".$rs['iduser']."</td>
						  <td>".$rs['nmcustomer']."</td>
						  <td>".$rs['fasorder']."</td>
						  <td>".$rs['tglorder']."</td>
						  <td>".$rs['desorder']."</td>
						  <td>".$rs['deslayan']."</td>
						  <td>".$status."</td>
						  <td>".$rs['tglselesai']."</td>
					
						  <td>"?><form method="GET" action="index.php">
						        <input type="hidden" name="tgl" id="par" value="<?php echo $tgl;?>">
								<input type="hidden" name="sdtgl" id="del" value="<?php echo $sdtgl;?>">
								<input type="hidden" name="idlog" id="idlog" value="<?php echo  $rs['idlog'];?>">  
								<input type="hidden" name="par" id="par" value="02">
								<input type="hidden" name="del" id="del" value="Y">
                   				<button type="submit" class="btn btn-danger btn-xs" value="Y" Onclick="return ConfirmDelete();" 
								<?php 
								if ($iduser != $rs['iduser']){
								  echo "disabled='disabled'"; ;
								}
								?>>Delete</button>     
							</form> 
						  		
						  </td>
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
				
					
					error: ". $e->getMessage()."
					
			
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
