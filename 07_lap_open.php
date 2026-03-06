<?php
// 
include "dbase.php";
include_once "helper_log_display.php";
$pesan = "";
$tgl 	 = trim($_GET['tgl']);
$sdtgl	 = trim($_GET['sdtgl']);

if (isset($_GET['tgl'])) {
	$tgl 	 = trim($_GET['tgl']);  
	$sdtgl	 = trim($_GET['sdtgl']);
	$kodcustomer = 	trim($_GET['kodcustomer']);
	$dikerjakan = 	trim($_GET['dikerjakan']);
}else {
    $tgl 	 = "";  
	$sdtgl	 = "";
	$kodcustomer = "";
	$dikerjakan = "";
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
		location.replace("index.php?par=04&kodcustomer=" + id);
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
			<li><i class="fa fa-home"></i>LAPORAN PRODUKSI YANG MASIH OPEN</li>
		</ol>
		<section class="panel">
			<header class="panel-heading">
				<form role="form"  method="GET" onSubmit="return validasi_input(this)"  action="index.php"> 
					<div class="form-group col-xs-12 col-sm-2 ">
						<label>Tanggal :  </label>
						<input name="tgl" id="dp1" type="text"  onKeyPress="if (event.keyCode==13) {dp1.focus()} size="16" class="form-control" value="<?php echo $tgl; ?>"> 
					</div>
					

					<div class="form-group col-xs-12 col-sm-2 ">
						<label>Sampai Tanggal :  </label>
						<input name="sdtgl" id="dp2" type="text"   size="16" class="form-control" value="<?php echo $sdtgl; ?>"> 
					</div> 

					<div class="form-group col-xs-12 col-sm-2 ">
						<label>Customer </label>
						<select name="kodcustomer" id="kodcustomer" class="form-control" onKeyPress="if (event.keyCode==13) {kodcustomer.focus()}">
							<option value="">All</option>
							<?php
								//mengambil nama-nama kategori yang ada di database  
								$qk = $conn->prepare("SELECT * FROM rcustomer ORDER BY kodcustomer ");
								$qk->execute();
								while ($rsk = $qk->fetch()) {
									$selected = ($kodcustomer == $rsk['kodcustomer']) ? "SELECTED" : "";
									echo "<option value='" . $rsk['kodcustomer'] . "' $selected>" . $rsk['nmcustomer'] . "</option>";
								}
							?>
						</select>
					</div> 
					<div class="form-group col-xs-12 col-sm-2 ">
						<label>Dikerjakan Oleh</label>	
						<select name="dikerjakan" id="dikerjakan" class="form-control"  placeholder="Dikerjakan Oleh"    onKeyPress="if (event.keyCode==13) {tglorder.focus()}"> 
							<option value="" selected>All</option>
							<?php
							//mengambil nama-nama kategori yang ada di database  
								$qk = $conn->prepare("SELECT * FROM ruser WHERE stsaktif = 1 ORDER BY nik ASC, nama ASC "); 
								$qk->execute(); 
								while($rsk = $qk->fetch()){ 
									$selected = $dikerjakan == $rsk['iduser'] ? "SELECTED" : "";  
									echo "<option value='{$rsk['iduser']}' {$selected}>{$rsk['nama']}</option>\n"; 
								}
							?>
						</select> 
					</div> 
						
					</div>
					<div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-6"> 
						<input type="hidden" name="par" id="par" value="07">
						
						<button type="submit" name="submit" class="btn btn-primary" value="Y">Tampilkan</button>
						<button type="reset" class="btn btn-danger">Reset</button>
					</div> 
				</form>
				<div class="clearfix"> </div>
				<h4>
					<span color="red"><?php echo $pesan; ?></span>
				</h4>
			</header>
			<section class="content">

				<div class="box-body">
					<table id="contoh" class="table table-bordered table-striped table-hover">
						<div>
							<thead>
								<tr class="height: 5px;">
									<th>No</th>
									<th>Ticket By</th>
									<th>Dikerjakan</th>
									<th>Mitra</th>
									<th>FasOrder</th>
									<th>Tgl Order</th>
									<th>Tgl Target</th>
									<th>Order</th>
									<th>Order Layanan</th>
									<th>Ket. Terlambat</th>
									<th>Nilai</th>
									<th>Aksi</th>
								</tr>
							</thead>
							<tbody>
								<div id="show-product">
									<!-- data akan di tampilkan di sini -->
								</div>
								<?php
								if ($_GET[''] !== "") {
									$kodcustomer = ($_GET['kodcustomer']);
									$tgl 		= ($_GET['tgl']);
									$sdtgl 		= ($_GET['sdtgl']);
									$iduser   	= $_SESSION['DEFAULT_IDUSER'];
									$dikerjakan  = isset($_GET['dikerjakan']) ? $_GET['dikerjakan'] : '';
									$swhere = "WHERE a.isselesai= '0' and a.stsdel=0 ";
									if ($tgl != "" && $sdtgl != "") {
										$swhere .= " AND (a.tglorder BETWEEN '$tgl' AND '$sdtgl')";
									}

									if ($kodcustomer != "") {
										$swhere .= " AND a.kodcustomer = '$kodcustomer'";
									}

									if ($dikerjakan != "") {
										$swhere .= " AND a.userorder = '$dikerjakan'";
									}
									try {
										$strsql = "SELECT a.idlog,a.userorder, a.iduser, b.nmcustomer,a.fasorder, a.tglorder,a.tgltarget, a.desorder, a.deslayan, a.tglselesai, a.prioritas, a.ketterlambat, a.nilai 
													from tlog a 
													inner join rcustomer b  on a.kodcustomer =b.kodcustomer 
													{$swhere}
													order by a.prioritas, a.tgltarget";
										$sql = $conn->prepare($strsql);

										$sql->execute();
										// tampilkan
										$no = 1;
										while ($rs = $sql->fetch()) {
											$rs['nilai'] = nameKolomNilai($rs['nilai']);
											// Cek apakah $iduser sama dengan iduser atau userorder
											if ($iduser == strtoupper($rs['iduser']) || $iduser == strtoupper($rs['userorder'])) {
												// Tampilkan tombol Edit yang dapat diklik
												$btnedit = "<a href='index.php?idlog=" . $rs['idlog'] . "&par=03_edt&edt=Y' type='button' class='btn btn-sm btn-info' target='_blank'>Edit</a>";
											} else {
												// Tampilkan tombol Edit yang tidak dapat diklik (atau tombol yang tidak aktif)
												$btnedit = "<a href='#' type='button' class='btn btn-sm btn-info disabled' aria-disabled='true'>Edit</a>";
											}
											// Tambahkan kondisi untuk memeriksa tanggal target
											if ($rs['tgltarget'] < date("Y-m-d")) {

												echo "  <tr>
															<td align=center><font size=-1 color='red'>" . $no . "</font></td>
															<td><font size=-1 color='red'>" . $rs['idlog'] . " | " . $rs['iduser'] . "</font></td>
															<td><font size=-1 color='red'>" . $rs['userorder'] . "</font></td>
															<td><font size=-1 color='red'>" . $rs['nmcustomer'] . "</font></td>
															<td><font size=-1 color='red'>" . $rs['fasorder'] . "</font></td>
															<td><font size=-1 color='red'>" . $rs['tglorder'] . "</font></td>
															<td><font size=-1 color='red'>" . $rs['tgltarget'] . "</font></td>
															<td><font size=-1 color='red'>" . renderLogColumn($rs['desorder'], 'desorder', $rs['idlog'], 'Uraian Order') . "</font></td>
															<td><font size=-1 color='red'>" . renderLogColumn($rs['deslayan'], 'deslayan', $rs['idlog'], 'Aktivitas Layanan') . "</font></td>
															<td><font size=-1 color='red'>" . $rs['ketterlambat'] . "</font></td>
															<td><font size=-1 color='red'>" . $rs['nilai'] . "</font></td>
															<td><font size=-1 color='red'>" . $btnedit . "</font></td>
														</tr>";
											} else {
												echo "  <tr>
															<td align=center><font size=-1>" . $no . "</font></td>
															<td><font size=-1 >" . $rs['idlog'] . " | " . $rs['iduser'] . "</font></td>
															<td><font size=-1 >" . $rs['userorder'] . "</font></td>
															<td><font size=-1 >" . $rs['nmcustomer'] . "</font></td>
															<td><font size=-1 >" . $rs['fasorder'] . "</font></td>
															<td><font size=-1 >" . $rs['tglorder'] . "</font></td>
															<td><font size=-1 >" . $rs['tgltarget'] . "</font></td>
															<td><font size=-1 >" . renderLogColumn($rs['desorder'], 'desorder', $rs['idlog'], 'Uraian Order') . "</font></td>
															<td><font size=-1 >" . renderLogColumn($rs['deslayan'], 'deslayan', $rs['idlog'], 'Aktivitas Layanan') . "</font></td>
															<td><font size=-1 >" . $rs['ketterlambat'] . "</font></td>
															<td><font size=-1 color='red'>" . $rs['nilai'] . "</font></td>
															<td><font size=-1 >" . $btnedit . "</font></td>
														</tr>";
											}


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

<?php echo renderLogModal(); ?>

</body>

</html>