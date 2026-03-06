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
   
   $kodcustomer   = trim($_GET['kodcustomer']); 
   $noinvoice  = trim($_GET['noinvoice']); 
   $tglinvoice    = $_GET['tglinvoice'];
   $bsuinvoice  = trim($_GET['bsuinvoice']);
   $isiinvoice  = addslashes(trim($_GET['isiinvoice']));
   $iduserinvoice = $iduser;    
   $validinvoice    = $_GET['validinvoice'];
   $issukses = 1;
    
		
   $sql = "insert into tinvoice (kodcustomer,noinvoice,tglinvoice,bsuinvoice,isiinvoice,iduserinvoice,validinvoice) "; 
   $sql = $sql . "values ('$kodcustomer','$noinvoice','$tglinvoice',$bsuinvoice,'$isiinvoice','$iduserinvoice','$validinvoice')"; 
 
   try { 
	   //insert
	   $qins = $conn->prepare($sql);
	   $qins->execute(); 
	   //increment nomto akhir di trigger 
	   $kodbrgpesan  = $kodbrg;
	   $pesan = "<font color=blue>New record <strong> </strong> created successfully</font>"; 
	}//try
	   catch (PDOException $e)	{
	       
		  //echo "<font color=red>Error insert. Make sure kode barang is correct.</font>" ;
		  $pesan =  "<font color=red>Error insert. Make sure data is correct.</font>" ; 
    }//catch
	
 
}
   

//DELETE 
if (isset($_GET['del'])) {
   $noinvoice = trim($_GET['noinvoice']);	 
   $istidakbolehdel = 0;	  
   $sqlcek = "select * from tinvoice WHERE noinvoice='$noinvoice' and isbayar=1 "; 
    try { 
	   $qcek = $conn->prepare($sqlcek);
	   $qcek->execute(); 
	   if($qcek->rowCount() <1){ 
	      $istidakbolehdel = 1;
		  $pesan = "<font color=red>Log <strong>".$noinvoice." </strong> sudah dibayarkan tanggal '$tglbayarinvoice'</font><br>";
		  $pesan = $pesan. "<font color=red>Tidak boleh delete.</font>";
	   }				
	    
	}//try
	   catch (PDOException $e)	{
		  //echo "<font color=red>Error insert. Make sure kode barang is correct.</font>" ;
		  $pesan =  "<font color=red>Error checking. Make sure <strong>".$noinvoice."</strong> is correct.</font>" ;
	 
	}//catch
	try { 
	   $qcek = $conn->prepare($sqlcek);
	   $qcek->execute(); 
	   if($qcek->rowCount() <1){ 
	      $istidakbolehdel = 1;
		  $pesan = "<font color=red>Log <strong>".$noinvoice." </strong> bukan milik user <strong>".$iduser." </strong></font><br>";
		  $pesan = $pesan. "<font color=red>Tidak boleh delete yang bukan lognya</font>";
	   }				
	    
	}//try
	   catch (PDOException $e)	{
		  //echo "<font color=red>Error insert. Make sure kode barang is correct.</font>" ;
		  $pesan =  "<font color=red>Error checking. Make sure <strong>".$noinvoice."</strong> is correct.</font>" ;
	 
	}//catch 
    
	if ($istidakbolehdel == 0){
	   $sqld = "DELETE  FROM  tinvoice WHERE noinvoice='$noinvoice'  "; 
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
  if (form.kodcustomer.value == "blm_pilih"){
    alert("Customer belum dipilih!");
    form.kodcustomer.focus();
    return (false);
  }
  
  if (form.noinvoice.value == ""){
    alert("No Invoice masih kosong!");
    form.noinvoice.focus();
    return (false);
  }
  if (form.bsuinvoice.value == ""){
    alert("Nominal masih kosong!");
    form.bsuinvoice.focus();
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
	  <li><i ></i><font color="blue">INPUT INVOICE</font></li> 
	</ol>
	  
	<section class="panel">
	  <header class="panel-heading">
		  
		  <form role="form"  method="GET" onSubmit="return validasi_input(this)"  action="index.php"> 
		  
		    <div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-6">
				  <label>Customer / Mitra</label>	
				  <?php 
				  $kodcustomer  ="";
				  if (isset($_GET['kodcustomer'])) { 
				      $kodcustomer  =$_GET['kodcustomer'];
				  }
				  ?>
				   <select name="kodcustomer" id="kodcustomer" class="form-control"  placeholder="Customer..."    onKeyPress="if (event.keyCode==13) {tglorder.focus()}"> <option value="blm_pilih">-- Pilih Customer --</option>
				<?php
				//mengambil nama-nama kategori yang ada di database  
					$qk = $conn->prepare("SELECT * FROM rcustomer ORDER BY kodcustomer "); 
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
			 
			 <div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-6">
				  <label>No. Invoice</label>	
				  <div class="search-box-material"><input name="noinvoice" onKeyUp="this.value = this.value.toUpperCase();" class="form-control" id="noinvoice" type="text1" step="any" autocomplete="off" placeholder="No. Invoice"    >
				  <div class="result1"></div>   
				  </div>
			 </div>	
				 
			 <div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-6">
				  <label>Tanggal Invoice</label>	 
				  <input name="tglinvoice" id="dp1"   size="16" class="form-control" value="<?php echo $tglini; ?>" onKeyPress="if (event.keyCode==13) {nominal.focus()}" >  
			 </div>	     
			 
			 <div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-6">
				  <label>Nominal Rp.</label>	
				  <div class="search-box-material"><input name="bsuinvoice" onKeyUp="this.value = this.value.toUpperCase();" class="form-control" id="bsuinvoice" type="text1" step="any" autocomplete="off" placeholder="Tulis nominal hanya angka"    >
				  <div class="result1"></div>   
				  </div>
			 </div>	 
			 
			 <div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-6">
				  <label>Ket. Invoice</label>	
				  <div class="search-box-material"><input name="isiinvoice" onKeyUp="this.value = this.value.toUpperCase();" class="form-control" id="isiinvoice" type="text1" step="any" autocomplete="off" placeholder="Keterangan Invoice"    >
				  <div class="result1"></div>   
				  </div>
			 </div>	
			 <br> 
			 
			 <div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-6">
				  <label>Valid Until</label>	 
				  <input name="validinvoice" id="dp2"   size="16" class="form-control" value="<?php echo $tglini; ?>" onKeyPress="if (event.keyCode==13) {submit.focus()}" >  
			 </div>	 
	 
			  <div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-6"> 
				 <input type="hidden" name="par" id="par" value="09">
				 <input type="hidden" name="ins" id="ins" value="Y">
				 <button type="submit" name="submit" class="btn btn-primary" value="Y">Insert Data</button>
				 <button type="reset" class="btn btn-danger">Reset</button>
			   </div> 
			    
		  </form>
		  <div class="clearfix">   </div>
		   
		  <h4><font color="red"><?php echo $pesan;?></font></h4>
	  </header>
	  <section class="content">
	  <div align="center">INVOICE <!--BULAN INI: <?= $bulan?>-<?= $tahun?> --> 
	  </div>
	  <div class="box-body">
	   <table id="contoh" class="table table-bordered table-striped table-hover"> 
		  <thead> 
			<tr class="height: 5px;">
			  <th>#</th>
			  <th>User</th>
			  <th>Customer</th>
			  <th>Nomor</th>
			  <th>Tanggal</th> 
			  <th>Nominal</th>
			  <th>Ket</th> 
			  <th>Valid Until</th> 
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
			        //$sql = $conn->prepare("select * from tinvoice where month(tglinvoice)=$bulan and year(tglinvoice)=$tahun order by tglinvoice"); 
					$sql = $conn->prepare("select * from tinvoice  order by tglinvoice");
					$sql->execute();	 				
					// tampilkan
					$no=1;
					while($rs = $sql->fetch()) { 
					   echo "   <tr>
						  <td align=center> <font size=-1>".$no."</font></td>
						  <td><font size=-1>".$rs['iduserinvoice']."</font></td>
						  <td><font size=-1>".$rs['kodcustomer']."</font></td>
						  <td><font size=-1>".$rs['noinvoice']."</font></td> 
						  <td><font size=-1>".$rs['tglinvoice']."</font></td>
						  <td><font size=-1>".$rs['bsuinvoice']."</font></td>
						  <td><font size=-1>".$rs['isiinvoice']."</font></td> 
						  <td><font size=-1>".$rs['validinvoice']."</font></td>
						  <td>"?>
						  		<form method="GET" action="index.php">
								<input type="hidden" name="noinvoice" id="noinvoice" value="<?php echo  $rs['noinvoice'];?>">  
								<input type="hidden" name="par" id="par" value="09">
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
