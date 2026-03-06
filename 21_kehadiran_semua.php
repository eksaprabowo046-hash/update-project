<?php

include "dbase.php";  
$pesan = "";

$tgl 	 = date("Y-m-d");  
$sdtgl	 = date("Y-m-d"); 
$iduser  = $_SESSION['DEFAULT_IDUSER'];

if (isset($_POST['submit'])) {
	$tgl 	 = trim($_POST['tgl']);  
	$sdtgl	 = trim($_POST['sdtgl']); 
}  
 
function hari_ini($hari){
	//$hari = date ("D");
	switch($hari){
		case 'Sun':
			$hari_ini = "Minggu";
		break;
		case 'Mon':			
			$hari_ini = "Senin";
		break;
		case 'Tue':
			$hari_ini = "Selasa";
		break;
		case 'Wed':
			$hari_ini = "Rabu";
		break;
		case 'Thu':
			$hari_ini = "Kamis";
		break;
		case 'Fri':
			$hari_ini = "Jumat";
		break;
		case 'Sat':
			$hari_ini = "Sabtu";
		break;
		default:
			$hari_ini = "Tidak di ketahui";		
		break;
	}
	return  $hari_ini ;
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
  
  
return (true);
}
</script>

<body >
 
<div class="row"> 
    <ol class="breadcrumb">
	  <li><i class="fa fa-home"></i>LAPORAN KEHADIRAN SEMUA USERS </li> 
	</ol> 
	<section class="panel">
	  <header class="panel-heading">
		  
		<form role="form"  method="POST" onSubmit="return validasi_input(this)"  action="index.php?par=21"> 
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
			  <th>User</th>
			  <th>Hari</th>
			  <th>Tanggal</th>
			  <th>Hadir</th>
			  <th>Pulang</th>   
			</tr>
		  </thead>
		  <tbody>
		  <div id="show-product">
          <!-- data akan di tampilkan di sini -->
          </div>
		  <?php 
			    try {  	
			        $strsql = "select * from tkehadiran where   (tanggal >='$tgl' and tanggal<='$sdtgl' ) 
								order by tanggal,iduser";
			        $sql = $conn->prepare($strsql);
					$sql->execute();
					//echo $strsql;	 				
					// tampilkan
					$no=1;
					while($rs = $sql->fetch()) {  
					   echo "   
					      <tr> 
						  <td align=center><font size=-1>".$no."</font></td>
						  <td><font size=-1>".$rs['iduser']."</font></td>
						  <td><font size=-1>".hari_ini(date('D', strtotime($rs['tanggal'])))."</font></td>
						  <td><font size=-1>".$rs['tanggal']."</font></td>
						  <td><font size=-1>".$rs['hadir']."</font></td>
						  <td><font size=-1>".$rs['pulang']."</font></td>  
						  </tr> ";
						$no++;	
					} 
			   }//try
			   catch (PDOException $e)	{
				  echo "  
				   <tr> 
					</tr>  ";
			 
			  }//catch 
			 
		  ?> 
			
		  </tbody>
		</table>
	  </div>
	  </section>
	</section>
   
</div> 
 
</body>

</html>
