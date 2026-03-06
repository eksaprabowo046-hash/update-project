<?php
// 
include "dbase.php";
include "islogin.php";
$pesan = $_SESSION['PESAN'];
unset($_SESSION['PESAN']);
$iduser   = $_SESSION['DEFAULT_IDUSER'];
if (isset($_GET['tgl'])) {
	$tgl 	 = trim($_GET['tgl']);
	$sdtgl	 = trim($_GET['sdtgl']);
} else {
	$tgl 	 = date('Y-m-d');
	$sdtgl	 = date('Y-m-d');
}


if ($_GET['para'] == "ditolak") {
	$pesan = $_GET['pesan'];
}

if (isset($_GET['edt_exe'])) {
	$idlog   		= $_GET['idlog'];
	$kodcustomer   	= trim($_GET['kodcustomer']);
	$nmcustomer   	= trim($_GET['nmcustomer']);
	$jnsbisnis  	= trim($_GET['jnsbisnis']);
	$tglorder    	= $_GET['tglorder'];
	$tgltarget    	= $_GET['tgltarget'];
	$dikerjakan   	= $_GET['dikerjakan'];
	$prioritas		= $_GET['prioritas'];
	$fasorder  		= trim($_GET['fasorder']);
	$desorder  		= htmlspecialchars($_GET['desorder'], ENT_QUOTES, 'UTF-8');
	$deslayan   	= htmlspecialchars($_GET['deslayan'], ENT_QUOTES, 'UTF-8');
	$tglselesai 	= $_GET['tglselesai'];
	$isselesai 		= $_GET['selesai'];
	$testing 		= $_GET['testing'];
	$tgltesting 	= $_GET['tgltesting'];
	$update 		= $_GET['update'];
	$tglupdate 		= $_GET['tglupdate'];
	$ketterlambat	= htmlspecialchars($_GET['ketterlambat'], ENT_QUOTES, 'UTF-8');
	$nilai 			= isset($_GET['nilai']) ? $_GET['nilai'] : 0;


	$sql = "UPDATE tlog SET kodcustomer='{$kodcustomer}', jnsbisnis='{$jnsbisnis}',tglorder='{$tglorder}', fasorder='{$fasorder}', desorder='{$desorder}',deslayan='{$deslayan}', isselesai={$isselesai}, tglselesai='{$tglselesai}', istesting={$testing}, tgltesting='{$tgltesting}', isupdate={$update}, tglupdate='{$tglupdate}',tgltarget='{$tgltarget}', userorder='{$dikerjakan}', prioritas={$prioritas}, ketterlambat='{$ketterlambat}'";

	if (empty($nilai) && $nilai != 0) {
		$sql .= ", nilai='{$nilai}'";
	}

	$sql .= " WHERE idlog={$idlog}";

	try {
		//insert
		$qins = $conn->prepare($sql);
		$qins->execute();
		//increment nomto akhir di trigger 
		$kodbrgpesan  = $kodbrg;
		echo "<script>location.replace('index.php?par=03')</script>";
		$_SESSION['PESAN'] = "<font color=blue>Updated <strong> </strong>  successfully</font>";
	} //try
	catch (PDOException $e) {

		//echo "<font color=red>Error insert. Make sure kode barang is correct.</font>" ;
		$_SESSION['PESAN'] =  "<font color=red>Error update. Make sure data is correct.</font>";
	} //catch


}
?>


<script type="text/javascript">
	//Mematikan event enter untuk submit
	function stopRKey(evt) {
		var evt = (evt) ? evt : ((event) ? event : null);
		var node = (evt.target) ? evt.target : ((evt.srcElement) ? evt.srcElement : null);
		if ((evt.keyCode == 13) && (node.type == "text")) {
			return false;
		}
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
	function pilih(id) {
		location.replace("index.php?par=03&kodcustomer=" + id);
	}
</script>


<script type="text/javascript">
	function validasi_input(form) {
		if (form.kodcustomer.value == "blm_pilih") {
			alert("Kelompok barang belum dipilih!");
			form.kodcustomer.focus();
			return (false);
		}

		function validasi_input(form) {
			if (form.tgl.value == "blm_pilih") {
				alert("Kelompok barang belum dipilih!");
				form.tgl.focus();
				return (false);
			}
		}


		return (true);
	}
</script>

<body>

	<div class="row">
		<ol class="breadcrumb">
			<li><i class="fa fa-home"></i> UPDATE LOG CUSTOMER</li>
		</ol>
		<section class="panel">
			<header class="panel-heading">

				<form role="form" method="GET" onSubmit="return validasi_input(this)" action="index.php">



					<div class="form-group col-xs-12 col-sm-2 ">
						<label>Tanggal : </label>
						<input name="tgl" id="dp1" type="text" value="<?php echo $tgl; ?>" onKeyPress="if (event.keyCode==13) {dp1.focus()} size=" 16" class="form-control" value="<?php echo $tgl; ?> ">


					</div>


					<div class="form-group col-xs-12 col-sm-2 ">
						<label>Sampai Tanggal : </label>
						<input name="sdtgl" id="dp2" type="text" value="<?php echo $sdtgl; ?>" size="16" class="form-control" value="<?php echo $sdtgl; ?> ">

					</div>

	</div>
	<div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-6">
		<input type="hidden" name="par" id="par" value="03">

		<button type="submit" name="submit" class="btn btn-primary" value="Y">Submit</button>
		<button type="reset" class="btn btn-danger">Reset</button>
	</div>
	</form>
	<div class="clearfix"> </div>

	<h4>
		<font color="red"><?php echo $pesan; ?></font>
	</h4>
	</header>
	<section class="content">
		<div align="center">
			<?php
			// echo $kodsupplier;

			if ($kodcustomer  <> "") {
				$sqlk = $conn->prepare("select *  from rcustomer where kodcustomer= '$kodcustomer'");
				$sqlk->execute();
				$rsk = $sqlk->fetch();
				//echo "Supplier ".$rsk['kodsupplier'];
				echo "  Daftar Order Customer " . $rsk['nmcustomer'];
			}
			?>
			<br>
			<?php echo " Dari tanggal " . $tgl . "&nbsp;&nbsp;&nbsp; Sampai tanggal " . $sdtgl; ?>
		</div>
		<div class="box-body">
			<table id="contoh" class="table table-bordered table-striped table-hover">

				<div>
					<thead>
						<tr class="height: 5px;">
							<th>#</th>
							<th>Ticket By</th>
							<th>User</th>
							<th>Jns Bisnis</th>
							<th>Mitra</th>
							<th>FasOrder</th>
							<th>Tgl Order</th>
							<th>Order</th>
							<th>Order Layanan</th>
							<th>Status</th>
							<th>Tgl Selesai</th>
							<th>File</th>
							<th>Edit</th>
						</tr>
					</thead>
					<tbody>
						<div id="show-product">
							<!-- data akan di tampilkan di sini -->
						</div>
						<?php
						if ($_GET[''] !== "") {
							try {
								$strsql = "SELECT a.userorder, a.idlog, a.kodcustomer, a.iduser, a.jnsbisnis, b.nmcustomer, a.fasorder, a.tglorder, a.desorder, a.deslayan, a.tglupdate, a.isupdate, a.file_uploads, a.id_parent from tlog a inner join rcustomer b on a.kodcustomer = b.kodcustomer where (a.tglorder >= '$tgl' and a.tglorder <= '$sdtgl') and a.stsdel = 0 order by a.tglorder, a.idlog";
								$sql = $conn->prepare($strsql);


								$sql->execute();
								//echo $strsql;	 				
								// tampilkan
								$no = 1;
								while ($rs = $sql->fetch()) {
									// Hitung file upload
									$fileCount = 0;
									if (!empty($rs['file_uploads'])) {
										$files = json_decode($rs['file_uploads'], true);
										$fileCount = is_array($files) ? count($files) : 0;
									}

									$status = ($rs['isupdate'] == 1) ? "Selesai" : "Belum";

								// Preview HTML untuk kolom tabel
								$desorder_html = stripslashes($rs['desorder']);
								$deslayan_html = stripslashes($rs['deslayan']);

								$desorder_long = mb_strlen(trim(strip_tags($desorder_html))) > 100 || stripos($desorder_html, '<img') !== false;
								$deslayan_long  = mb_strlen(trim(strip_tags($deslayan_html))) > 100 || stripos($deslayan_html, '<img') !== false;

								$row_id = $rs['idlog'];

								echo "<tr>
									<td>{$no}</td>
									<td><font size='-1'>{$rs['idlog']} | {$rs['userorder']}";
								if (!empty($rs['id_parent'])) {
									echo "<br><span class='label label-info' style='font-size:9px; margin-top:4px; display:inline-block;'>↳ Subtask dari #{$rs['id_parent']}</span>";
								}
								echo "</font></td>
									<td>{$rs['iduser']}</td>
									<td>{$rs['jnsbisnis']}</td>
									<td>{$rs['nmcustomer']}</td>
									<td>{$rs['fasorder']}</td>
									<td>{$rs['tglorder']}</td>
									<td><div class='rich-preview'>{$desorder_html}</div>";
								if ($desorder_long) echo "<a href='#' class='btn-lihat-konten' data-target='#konten_desorder_{$row_id}' data-title='Uraian Order' title='Lihat selengkapnya'><i class='fa fa-expand text-primary'></i> selengkapnya</a><div id='konten_desorder_{$row_id}' style='display:none;'>{$desorder_html}</div>";
								echo "</td>
									<td><div class='rich-preview'>{$deslayan_html}</div>";
								if ($deslayan_long) echo "<a href='#' class='btn-lihat-konten' data-target='#konten_deslayan_{$row_id}' data-title='Aktivitas Layanan' title='Lihat selengkapnya'><i class='fa fa-expand text-primary'></i> selengkapnya</a><div id='konten_deslayan_{$row_id}' style='display:none;'>{$deslayan_html}</div>";
								echo "</td>
									<td>{$status}</td>
									<td>{$rs['tglupdate']}</td>
									<td>";
								?>

								<!-- Kolom FILE -->
								<?php if ($fileCount > 0): ?>
									<a href="index.php?par=03&edt=Y&idlog=<?php echo $rs['idlog']; ?>&tgl=<?php echo $tgl; ?>&sdtgl=<?php echo $sdtgl; ?>" 
										class="btn btn-xs btn-info">
										<i class="fa fa-file"></i> <?php echo $fileCount; ?> file
									</a>
								<?php else: ?>
									<span>-</span>
								<?php endif; ?>

								<?php
									echo "</td>
										<td>
											<form method='GET' action='index.php'>
												<input type='hidden' name='idlog' value='".trim($rs['idlog'])."'>
												<input type='hidden' name='par' value='03_edt'>
												<input type='hidden' name='edt' value='Y'>
												<button type='submit' class='btn btn-primary btn-xs' ". 
													((strtoupper($iduser) != strtoupper($rs['iduser'])) ? "disabled" : "") .">
													Edit
												</button>
											</form>
										</td>
									</tr>";

									$no++;
								}
							} //try
							catch (PDOException $e) {
								echo "  
				   <tr>
				
					
					
					
			
					</tr>  ";
							} //catch 
						}
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
      <div class="modal-body text-center">
      </div>
    </div>
  </div>
</div>

<style>
/* Gambar di dalam tabel diperkecil */
#contoh td img {
    max-width: 80px;
    max-height: 60px;
    cursor: pointer;
    border: 1px solid #ddd;
    border-radius: 3px;
    padding: 2px;
}
/* Modal gambar — kompak */
#imgModal .modal-body {
    max-height: 60vh;
    overflow-y: auto;
    padding: 15px;
    text-align: center;
}
#imgModal .modal-body img {
    max-width: 400px;
    max-height: 350px;
    height: auto;
    display: block;
    margin: 0 auto 10px auto;
    border-radius: 4px;
    border: 1px solid #ddd;
}
/* Rich HTML preview di tabel */
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
$('#imgModal').on('hidden.bs.modal', function() {
    $('#imgModal .modal-body').html('');
});
</script>

</body>

</html>