<?php
// 
session_start();
include "dbase.php"; 
include "islogin.php"; 
$pesan = "";
$iduser   = $_SESSION['DEFAULT_IDUSER']; 
 
 

if (isset($_GET['par'])) {   
   if ($_GET['par']=='01_edt'){ 
   $kodcustomer   = trim($_GET['kodcustomer']); 
   $idlog   = $_GET['idlog'];
   $jnsbisnis  = trim($_GET['jnsbisnis']); 
   $tglorder    = $_GET['tglorder'];
   $fasorder  = trim($_GET['fasorder']);
   $desorder  = trim($_GET['desorder']);
   $deslayan   = $_GET['deslayan']; 
   
   $issukses = 1;
   }  
 
}
   
//cek user
$isboleh = 0;	  
   $sqlcek = "select * from tlog WHERE idlog=$idlog and iduser='$iduser' "; 
   echo $sqlcek; 
    try { 
	   $qcek = $conn->prepare($sqlcek);
	   $qcek->execute(); 
	   if($qcek->rowCount() <1 ){   
		  $pesan = "<font color=red>Log <strong>".$idlog." </strong> bukan milik user <strong>".$iduser." </strong></font><br>";
		  $pesan = $pesan. "<font color=red>Tidak boleh update yang bukan lognya</font>";
		  //echo $pesan;
		  Header("location: index.php?par=01&para=ditolak&pesan=".$pesan); 
		  exit;
	   exit;
	   }else {
	     $isboleh = 1;
		  
	   }				
	    
	}//try
	   catch (PDOException $e)	{
		  //echo "<font color=red>Error insert. Make sure kode barang is correct.</font>" ;
		  $pesan =  "<font color=red>Error checking. Make sure <strong>".$idlog."</strong> is correct.</font>" ;
	 
	}//catch    
   
//EDIT EXECUTE
if (isset($_GET['edt_exe'])) {
   $idlog   = $_GET['idlog']+0;
   $kodcustomer   = trim($_GET['kodcustomer']); 
   $jnsbisnis  = trim($_GET['jnsbisnis']); 
   $tglorder    = $_GET['tglorder'];
   $fasorder  = trim($_GET['fasorder']);
   $desorder  = trim($_GET['desorder']);
   $deslayan   = $_GET['deslayan'];  
   $status   = $_GET['status']; 
   $tglselesai = $_GET['tglselesai'];
   $issukses = 1;
    
    
	
	 
	   $sql = "update tlog set kodcustomer='$kodcustomer', jnsbisnis='$jnsbisnis',tglorder='$tglorder', fasorder='$fasorder', ";  
	   $sql = $sql . "desorder='$desorder',deslayan='$deslayan', isselesai=$status, tglselesai='$tglselesai'  where idlog=$idlog "; 
	   //echo  $sql;
	   try {
		   $qedt = $conn->prepare($sql);
		   $qedt->execute();	
		   $kodbrgpesan  = $idlog;
		   $pesan = "<font color=blue>New record <strong>".$idlog."</strong> updated successfully</font>"; 
		   echo "<script>location.replace('index.php?par=03')</script>";
		}//try
		   catch (PDOException $e)	{
			  //echo "<font color=red>Error insert. Make sure kode barang is correct.</font>" ;
			  $pesan =  "<font color=red>Update error. Make sure <strong>idlog</strong> is correct.</font>" ;
		 
		}//catch 
	 
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
  if (form.idkategori.value == "blm_pilih"){
    alert("Kelompok barang belum dipilih!");
    form.idkategori.focus();
    return (false);
  }
  
  if (form.barcode.value == ""){
    alert("Barcode masih kosong!");
    form.barcode.focus();
    return (false);
  }
  if (form.barcode.value == ""){
    alert("Barcode masih kosong!");
    form.barcode.focus();
    return (false);
  }
  if (form.nmbrg.value == ""){
    alert("Nama Barang masih kosong!");
    form.nmbrg.focus();
    return (false);
  }
  if (form.satuan1.value == ""){
    alert("Satuan grosir masih kosong!");
    form.satuan1.focus();
    return (false);
  }
  if (form.satuan2.value == ""){
    alert("Satuan ecer masih kosong!");
    form.satuan2.focus();
    return (false);
  }
  if (form.hrgbeli1.value <1 ){
    alert("Harga beli grosir tidak boleh nol!");
    form.hrgbeli1.focus();
    return (false);
  }
  if (form.hrgbeli2.value <1 ){
    alert("Harga beli ecer tidak boleh nol!");
    form.hrgbeli2.focus();
    return (false);
  }
  if (form.hrgjual1.value <1 ){
    alert("Harga jual grosir tidak boleh nol!");
    form.hrgjual1.focus();
    return (false);
  }
  if (form.hrgjual2.value <1 ){
    alert("Harga jual ecer tidak boleh nol!");
    form.hrgjual2.focus();
    return (false);
  }
return (true);
}
</script>

<body >
 
<div class="row"> 
    <ol class="breadcrumb">
	  <li><i class="fa fa-home"></i>UPDATE/EDIT LOG</li> 
	</ol> 
	<section class="panel">
	  <header class="panel-heading">
		  
		  <form role="form"  method="GET" onSubmit="return validasi_input(this)"  action="index.php"> 
		  
			 <div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-6">
				  <label>Kelompok Bisnis</label>	
				  
				   <select name="jnsbisnis" id="jnsbisnis" class="form-control"  placeholder="Jenis Bisnis..."     onKeyPress="if (event.keyCode==13) {kodcustomer.focus()}">
				   
					<option value="A" <?php if ($jnsbisnis=="A") {echo "SELECTED";} ?>>A:Administration</option>
				    <option value="B" <?php if ($jnsbisnis=="B") {echo "SELECTED";} ?>>B:Business</option>
					<option value="D" <?php if ($jnsbisnis=="D") {echo "SELECTED";} ?>>D:Developing</option>
					<option value="M" <?php if ($jnsbisnis=="M") {echo "SELECTED";} ?>>M:Maintenance</option>
					</select> 
			 </div>	
			 
		    <div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-6">
				  <label>Customer / Mitra</label>	
				  <?php echo $kodcustomer;?>
				   <select name="kodcustomer" id="kodcustomer" class="form-control"  placeholder="Customer..."  onChange="pilih(this.value)"  onKeyPress="if (event.keyCode==13) {tglorder.focus()}"> 
				<?php
				   
				//mengambil nama-nama kategori yang ada di database  
					$qk = $conn->prepare("SELECT * FROM rcustomer WHERE status = 1 ORDER BY kodcustomer "); 
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
				  <label>Tanggal Order</label>	 
				  <input name="tglorder" id="dp1" size="16" class="form-control" value="<?php echo $tglini; ?>" onKeyPress="if (event.keyCode==13) {fasorder.focus()}" >  
			 </div>	     
			 
			 <div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-6">
				  <label>Order Melalui</label>	
				  <div class="search-box-material"><input name="fasorder" value="<?php echo $fasorder; ?>" onKeyUp="this.value = this.value.toUpperCase();" class="form-control" id="fasorder" type="text1" step="any" autocomplete="off" placeholder="Tulis sarana (WA/Email/Telp) dan nama pengorder..."    >
				  <div class="result1"></div>   
				  </div>
			 </div>	 
			 
			 <div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-6">
				  <label>Uraian Order</label>	
				  <textarea name="desorder"    class="form-control" id="desorder"   rows="5" cols="50" step="any" autocomplete="off" placeholder="Uraian Order" ><?php echo $desorder; ?> </textarea>    
				   
			 </div>	 
			 
			 <div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-6">
				  <label>Aktifitas Layanan</label>	
				  <textarea name="deslayan"   class="form-control" id="deslayan"   rows="5" cols="50" step="any" autocomplete="off" placeholder="Tulis Tanggal dan Aktifitasnya..."    ><?php echo $deslayan; ?></textarea>    
				   
			 </div>	 
			
			 <div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-6">
				  <label>Status</label>	
				   <select name="status" id="status" class="form-control"  placeholder="Status..."  onKeyPress="if (event.keyCode==13) {kodbrg.focus()}">
					<option value="0">Open</option>
				    <option value="1">Close</option> 
					</select> 
			 </div>	  
			 <div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-6">
				  <label>Tanggal Selesai</label>	 
				  <input name="tglselesai" id="dp2"   value="<?php echo date('Y-m-d'); ?>" size="16" class="form-control" value="<?php echo $tglini; ?>" onKeyPress="if (event.keyCode==13) {nmbrg.focus()}" >  
			   </div>	
			     
			  <div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-6"> 
				 <input type="hidden" name="par" id="par" value="01_edt">
				 <input type="hidden" name="edt_exe" id="edt_exe" value="Y">
				 <input type="hidden" name="idlog" id="idlog" value="<?php echo $idlog;?>">
				 <?php
					if (!isset($_GET['edt_exe'])) { 
					?>
				 <button type="submit" name="submit" class="btn btn-primary" value="Y">Update/Edit Data</button>
				 <?php
					}
					?>   
			   </div> 
			    
		  </form>
		  <div class="clearfix">   </div>
		   
		  <h4><font color="red"><?php echo $pesan;?></font></h4>
	  </header>
	  <section class="content">
	  <div align="center">ORDER HARI INI  
	  </div>
	  <div class="box-body">
	   <table id="contoh" class="table table-bordered table-striped table-hover"> 
		  <thead> 
			<tr class="height: 5px;">
			  <th>#</th>
			  <th>Ticket By</th>
			  <th>User</th>
			  <th>Mitra</th>
			  <th>Jenis</th>
			  <th>FasOrder</th> 
			  <th>Order</th>
			  <th>Layanan</th>  
			  <th>Status</th> 
			  <th>Tglselesai</th>
			   
			</tr>
		  </thead>
		  <tbody>
		  <div id="show-product">
          <!-- data akan di tampilkan di sini -->
          </div>
		  <?php 
			   $tglsekarang  = date('Y-m-d'); 
			   try { 
			        $sql = $conn->prepare("SELECT * from tlog where tglorder='$tglsekarang' and stsdel=1 order by idlog"); 
					$sql->execute();	 				
					// tampilkan
					$no=1;
					while($rs = $sql->fetch()) { 
					   $status="Open"; 
					   if ($rs['isselesai']==1){
					     $status ="Close"; 
					   }
					   // Preview HTML untuk tabel
					   $desorder_html = stripslashes($rs['desorder']);
					   $deslayan_html = stripslashes($rs['deslayan']);
					   $desorder_long = mb_strlen(trim(strip_tags($desorder_html))) > 100 || stripos($desorder_html, '<img') !== false;
					   $deslayan_long  = mb_strlen(trim(strip_tags($deslayan_html))) > 100 || stripos($deslayan_html, '<img') !== false;

					   $row_id = $rs['idlog'];

					   echo "   <tr>
						  <td align=center><font size=-1>".$no."</font></td>
						  <td><font size=-1>".$rs['idlog']." | ".$rs['userorder']."</font></td>
						  <td><font size=-1>".$rs['iduser']."</font></td>
						  <td><font size=-1>".$rs['kodcustomer']."</font></td>
						  <td><font size=-1>".$rs['jnsbisnis']."</font></td> 
						  <td><font size=-1>".$rs['fasorder']."</font></td>
						  <td><font size=-1><div class='rich-preview'>".$desorder_html;
					   echo "</div>";
					   if ($desorder_long) echo "<a href='#' class='btn-lihat-konten' data-target='#konten_desorder_".$row_id."' data-title='Uraian Order' title='Lihat selengkapnya'><i class='fa fa-expand text-primary'></i> selengkapnya</a><div id='konten_desorder_".$row_id."' style='display:none;'>".$desorder_html."</div>";
					   echo "</font></td>
						  <td><font size=-1><div class='rich-preview'>".$deslayan_html;
					   echo "</div>";
					   if ($deslayan_long) echo "<a href='#' class='btn-lihat-konten' data-target='#konten_deslayan_".$row_id."' data-title='Aktivitas Layanan' title='Lihat selengkapnya'><i class='fa fa-expand text-primary'></i> selengkapnya</a><div id='konten_deslayan_".$row_id."' style='display:none;'>".$deslayan_html."</div>";
					   echo "</font></td> 
						  <td><font size=-1>".$status."</font></td> 
						  <td><font size=-1>".$rs['tglselesai']."</font></td> 
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

<!-- Modal lightbox untuk gambar -->
<div class="modal fade" id="imgModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
        <h4 class="modal-title">Konten Lengkap</h4>
      </div>
      <div class="modal-body text-center"></div>
    </div>
  </div>
</div>

<style>
#contoh td img { max-width: 80px; max-height: 60px; cursor: pointer; border: 1px solid #ddd; border-radius: 3px; padding: 2px; }
#imgModal .modal-body { max-height: 60vh; overflow-y: auto; padding: 15px; text-align: center; }
#imgModal .modal-body img { max-width: 400px; max-height: 350px; height: auto; display: block; margin: 0 auto 10px auto; border-radius: 4px; border: 1px solid #ddd; }
.rich-preview { max-height: 80px; overflow: hidden; font-size: 12px; line-height: 1.4; color: #555; }
.rich-preview p { margin: 0 0 2px 0; }
.rich-preview strong, .rich-preview b { font-weight: bold; }
.rich-preview em, .rich-preview i { font-style: italic; }
.rich-preview u { text-decoration: underline; }
.rich-preview a { color: #337ab7; text-decoration: underline; }
.rich-preview ul, .rich-preview ol { margin: 0 0 2px 14px; padding: 0; }
.rich-preview img { max-width: 60px; max-height: 50px; border: 1px solid #ddd; border-radius: 3px; vertical-align: middle; }
</style>

<script>
$(document).on('click', '.btn-lihat-konten', function(e) {
    e.preventDefault();
    var target = $(this).data('target');
    var title = $(this).data('title') || 'Konten Lengkap';
    var html = $(target).html();
    $('#imgModal .modal-title').text(title);
    $('#imgModal .modal-body').html(html);
    $('#imgModal').modal('show');
});
$('#imgModal').on('hidden.bs.modal', function() { $('#imgModal .modal-body').html(''); });
</script>
 
</body>

</html>
