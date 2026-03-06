<?php
// 
session_start();
include "dbase.php"; 
include "islogin.php"; 
$pesan = "";
$iduser   = $_SESSION['DEFAULT_IDUSER']; 

if ($kodjab!=1) {
	Header("location: index.php");
}

//ambil bulan dan tahun hari ini 
$bulan = 1; 
$tahun  = 2000;
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
// jalankan query
$sql = $conn->prepare("select month(CURDATE()) as bulan, year(CURDATE()) as tahun");
$sql->execute();  
if($sql->rowCount() > 0){ 
	$rs = $sql->fetch();
	$bulan = $rs['bulan'];
	$tahun = $rs['tahun'];	
}


function left($str, $length) {
     return substr($str, 0, $length);
} 

 

if (isset($_GET['ins'])) {    
   $noinvoice  = trim($_GET['noinvoice']);  
   $nopph  = trim($_GET['nopph']);
   $tglpph  = trim($_GET['tglpph']);
   $bsupph  = trim($_GET['bsupph']); 
   $iduserpph = $iduser;    
   $issukses = 1;
    
		
   $sql = "update tinvoice set nopph='$nopph', tglpph='$tglpph', "; 
   $sql = $sql . "bsupph=$bsupph, iduserpph='$iduserpph' where noinvoice='$noinvoice'"; 
 
   try { 
	   //insert
	   $qins = $conn->prepare($sql);
	   $qins->execute();  
	   $pesan = "<font color=blue>Record invoice<strong>".$noinvoice." </strong> updated PPh successfully</font>"; 
	}//try
	   catch (PDOException $e)	{
	       
		  //echo "<font color=red>Error insert. Make sure kode barang is correct.</font>" ;
		  $pesan =  "<font color=red>Error update. Make sure data is correct.</font>" ; 
    }//catch
	
 
}
   

//DELETE 
if (isset($_GET['del'])) {
   $noinvoice = trim($_GET['noinvoice']);	 
   $istidakbolehdel = 0;	  
   $sqlcek = "select * from tinvoice WHERE noinvoice='$noinvoice' and nopph<>'-'  "; 
    try { 
	   $qcek = $conn->prepare($sqlcek);
	   $qcek->execute(); 
	   if($qcek->rowCount() <1){ 
	      $istidakbolehdel = 1;
		  $pesan = "<font color=red>PPh dari Invoice <strong>".$noinvoice." </strong> belum diinput.</font><br>";
		  $pesan = $pesan. "<font color=red>Tidak boleh delete.</font>";
	   }				
	    
	}//try
	   catch (PDOException $e)	{
		  //echo "<font color=red>Error insert. Make sure kode barang is correct.</font>" ;
		  $pesan =  "<font color=red>Error checking. Make sure <strong>".$noinvoice."</strong> is correct.</font>" ;
	 
	}//catch
	 
    
	if ($istidakbolehdel == 0){
	   echo $noinvoice;	
	   $sql = "update tinvoice set nopph='-', tglpph='2000-01-01', "; 
       $sql = $sql . "bsupph=0, iduserpph='-' where noinvoice='$noinvoice'";
	   try { 
		   $qdel = $conn->prepare($sql);
		   $qdel->execute();
		    
		   $pesan = "<font color=blue>One record invoce no. <strong>".$noinvoice."</strong> deleted PPh successfully</font>"; 
		}//try
		   catch (PDOException $e)	{
			  //echo "<font color=red>Error insert. Make sure kode barang is correct.</font>" ;
			  $pesan =  "<font color=red>Error delete. Make sure <strong>".$noinvoice."</strong> is correct.</font>" ;
		 
		}//catch 
	}	
	 
}	

if (isset($_GET['para'])) { 
  $pesan = $_GET['pesan'];
}

?>	 

<script type="text/javascript">
$(document).ready(function(){
    $('.search-box-kategori input[type="text"]').on("keyup input", function(){
        /* Get input value on change */
        var inputVal = $(this).val();
        var resultDropdown = $(this).siblings(".result");
        if(inputVal.length){
            $.get("01a_search_kategori.php", {term: inputVal}).done(function(data){
                // Display the returned data in browser
                resultDropdown.html(data);
            });
        } else{
            resultDropdown.empty();
        }
    });
    
    // Set search input value on click of result item
    $(document).on("click", ".result p", function(){
        $(this).parents(".search-box-kategori").find('input[type="text"]').val($(this).text());
        $(this).parent(".result").empty();
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
	location.replace("index.php?par=01&idkategori="+id);
	
}
</script>

<script type="text/javascript">
function validasi_input(form){
 
  
  if (form.noinvoice.value == ""){
    alert("No Invoice masih kosong!");
    form.noinvoice.focus();
    return (false);
  }
  if (form.nopph.value == ""){
    alert("No PPh masih kosong!");
    form.nopph.focus();
    return (false);
  }
  
  if (form.bsupph.value == ""){
    alert("Nominal masih kosong!");
    form.bsupph.focus();
    return (false);
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
	  <li><i class="fa fa-home"></i>INPUT PPh</li> 
	</ol> 
	<section class="panel">
	  <header class="panel-heading">
		  
		  <form role="form"  method="GET" onSubmit="return validasi_input(this)"  action="index.php"> 
		  
		     
			 
			 <div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-6">
				  <label>No. Invoice</label>	
				  <div class="search-box-material"><input name="noinvoice" onKeyUp="this.value = this.value.toUpperCase();" class="form-control" id="noinvoice" type="text1" step="any" autocomplete="off" placeholder="No. Invoice"    >
				  <div class="result1"></div>   
				  </div>
			 </div>	
			 
			 <div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-6">
				  <label>No. PPh</label>	
				  <div class="search-box-material"><input name="nopph" onKeyUp="this.value = this.value.toUpperCase();" class="form-control" id="nopph" type="text1" step="any" autocomplete="off" placeholder="Jenis dan No. PPh"    >
				  <div class="result1"></div>   
				  </div>
			 </div>	
				 
			 <div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-6">
				  <label>Tanggal PPh</label>	 
				  <input name="tglpph" id="dp1"   size="16" class="form-control" value="<?php echo $tglini; ?>" onKeyPress="if (event.keyCode==13) {nominal.focus()}" >  
			 </div>	     
			 
			 <div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-6">
				  <label>Nominal PPh Rp.</label>	
				  <div class="search-box-material"><input name="bsupph" onKeyUp="this.value = this.value.toUpperCase();" class="form-control" id="bsupph" type="text1" step="any" autocomplete="off" placeholder="Tulis nominal hanya angka"    >
				  <div class="result1"></div>   
				  </div>
			 </div>	 
			 
			 
			 <br>  
	 
			  <div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-6"> 
				 <input type="hidden" name="par" id="par" value="13">
				 <input type="hidden" name="ins" id="ins" value="Y">
				 <button type="submit" name="submit" class="btn btn-primary" value="Y">Simpan PPh</button>
				 <button type="reset" class="btn btn-danger">Reset</button>
			   </div> 
			    
		  </form>
		  <div class="clearfix">   </div>
		   
		  <h4><font color="red"><?php echo $pesan;?></font></h4>
	  </header>
	  <section class="content">
	  <div align="center">PPH BULAN <!--INI : <?= $bulan?>-<?= $tahun?> -->
	  </div>
	  <div class="box-body">
	   <table id="contoh" class="table table-bordered table-striped table-hover"> 
		  <thead> 
			<tr class="height: 5px;">
			  <th>#</th>
			  <th>User</th>
			  <th>Customer</th>
			  <th>Invoice</th>
			  <th>Tanggal</th> 
			  <th>Nominal</th>
			  <th>PPh</th> 
			  <th>Tanggal</th>
			  <th>Nominal</th>  
			  <th>Action</th> 			  
			</tr>
		  </thead>
		  <tbody>
		  <div id="show-product">
          <!-- data akan di tampilkan di sini -->
          </div>
		  <?php 
			   $tglsekarang  = date('Y-m-d'); 
			   try { 
			        //$sql = $conn->prepare("select * from tinvoice where month(tglpph)=$bulan and year(tglpph)=$tahun order by tglpph"); 
					$sql = $conn->prepare("select * from tinvoice   order by tglpph"); 
					
					$sql->execute();	 				
					// tampilkan
					$no=1;
					while($rs = $sql->fetch()) { 
						$tglbayar = '';
						if ($rs['tglpph'] != '2000-01-01'){
							$tglbayar=$rs['tglpph'];
						}	
					   echo "   <tr>
						  <td align=center>".$no."</td>
						  <td>".$rs['iduserinvoice']."</td>
						  <td>".$rs['kodcustomer']."</td>
						  <td>".$rs['noinvoice']."</td> 
						  <td>".$rs['tglinvoice']."</td>
						  <td>".$rs['bsuinvoice']."</td>
						  <td>".$rs['nopph']."</td> 
						  <td>".$tglbayar."</td>
						  <td>".$rs['bsupph']."</td> 
						  <td>"?>
						  		<form method="GET" action="index.php">
								<input type="hidden" name="noinvoice" id="noinvoice" value="<?php echo  $rs['noinvoice'];?>">  
								<input type="hidden" name="par" id="par" value="13">
								<input type="hidden" name="del" id="del" value="Y">
                   				<button type="submit" class="btn btn-danger btn-xs" value="Y" Onclick="return ConfirmDelete();" 
								<?php 
								if ($iduser != $rs['iduserinvoice']){
								  echo "disabled='disabled'"; ;
								}
								?>>Delete</button>     
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
