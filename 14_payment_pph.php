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
   $tglbayarpph    = $_GET['tglbayarpph'];  
   $iduserpph = $iduser;    
   $issukses = 1;
    
		
   $sql = "update tinvoice set isbayarpph=1, tglbayarpph='$tglbayarpph', iduserpph='$iduserpph'  "; 
   $sql = $sql . "where noinvoice='$noinvoice'"; 
 
   try { 
	   //insert
	   $qins = $conn->prepare($sql);
	   $qins->execute();  
	   $pesan = "<font color=blue>Record invoice<strong>".$noinvoice." </strong> updated payment pph successfully</font>"; 
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
   $sqlcek = "select * from tinvoice WHERE noinvoice='$noinvoice' and isbayarpph=0 "; 
    try { 
	   $qcek = $conn->prepare($sqlcek);
	   $qcek->execute(); 
	   if($qcek->rowCount() !=0){ 
	      $istidakbolehdel = 1;
		  $pesan = "<font color=red>PPh dari Invoice <strong>".$noinvoice." </strong> belum dibayarkan.</font><br>";
		  $pesan = $pesan. "<font color=red>Tidak boleh delete.</font>";
	   }				
	    
	}//try
	   catch (PDOException $e)	{
		  //echo "<font color=red>Error insert. Make sure kode barang is correct.</font>" ;
		  $pesan =  "<font color=red>Error checking. Make sure <strong>".$noinvoice."</strong> is correct.</font>" ;
	 
	}//catch
 
	if ($istidakbolehdel == 0){
	   $sqld = "update tinvoice set isbayarpph=0, tglbayarpph='2000-01-01' WHERE noinvoice='$noinvoice'  "; 
	   try { 
		   $qdel = $conn->prepare($sqld);
		   $qdel->execute();
		    
		   $pesan = "<font color=blue>One record invoce no. <strong>".$noinvoice."</strong> deleted successfully</font>"; 
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
  if (form.bsubayarpph.value == ""){
    alert("Nominal masih kosong!");
    form.bsubayarpph.focus();
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

/* Style the list */
ul.breadcrumb {
  padding: 10px 16px;
  list-style: none;
  background-color: #eee;
}
</script>
<body >
 
<div class="row"> 
    <ol   class="breadcrumb">
	  <li ><i class="fa fa-home">PAYMENT PPh</i></li> 
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
				  <label>Tanggal Bayar PPh</label>	 
				  <input name="tglbayarpph" id="dp1"   size="16" class="form-control" value="<?php echo $tglini; ?>" onKeyPress="if (event.keyCode==13) {nominal.focus()}" >  
			 </div>	  
			 <br>  
	 
			  <div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-6"> 
				 <input type="hidden" name="par" id="par" value="14">
				 <input type="hidden" name="ins" id="ins" value="Y">
				 <button type="submit" name="submit" class="btn btn-primary" value="Y">Simpan Payment</button>
				 <button type="reset" class="btn btn-danger">Reset</button>
			   </div> 
			    
		  </form>
		  <div class="clearfix">   </div>
		   
		  <h4><font color="red"><?php echo $pesan;?></font></h4>
	  </header>
	  <section class="content">
	  <div align="center">PPh PAYMENT <!--BULAN INI : <?= $bulan?>-<?= $tahun?> -->
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
			  <th>Tgl Bayar</th> 
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
			        //$sql = $conn->prepare("select * from tinvoice where month(tglbayarpph)=$bulan and year(tglbayarpph)=$tahun order by tglbayarpph"); 
					$sql = $conn->prepare("select * from tinvoice  order by tglbayarpph"); 
					
					$sql->execute();	 				
					// tampilkan
					$no=1;
					while($rs = $sql->fetch()) { 
						$tglbayar = '';
						if ($rs['tglbayarpph'] != '2000-01-01'){
							$tglbayar=$rs['tglbayarpph'];
						}
						$tglpph = '';
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
						  <td>".$tglpph."</td>
						  <td>".$rs['bsupph']."</td>
						  <td>".$tglbayar."</td>
						  <td>"?>
						  		<form method="GET" action="index.php">
								<input type="hidden" name="noinvoice" id="noinvoice" value="<?php echo  $rs['noinvoice'];?>">  
								<input type="hidden" name="par" id="par" value="14">
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
