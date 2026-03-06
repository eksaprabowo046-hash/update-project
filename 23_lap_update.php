<?php
// 
include "dbase.php";
include_once "helper_log_display.php";
$pesan = "";
$tgl 	 = trim($_GET['tgl']);
$sdtgl	 = trim($_GET['sdtgl']);
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

<body>

	<div class="row">
		<ol class="breadcrumb">
			<li><i class="fa fa-home"></i>LAPORAN UPDATE YANG MASIH OPEN</li>
		</ol>
		<section class="panel">
			<header class="panel-heading">


				<div class="clearfix"> </div>

				<h4>
					<font color="red"><?php echo $pesan; ?></font>
				</h4>
			</header>
			<section class="content">

				<div class="box-body">
					<table id="contoh" class="table table-bordered table-striped table-hover">

						<div>
							<thead>
								<tr class="height: 5px;">
									<th>#</th>
									<th>Ticket By</th>
									<th>Dikerjakan</th>
									<th>Mitra</th>
									<th>FasOrder</th>
									<th>Tgl Order</th>
									<th>Tgl Target</th>
									<th>Order</th>
									<th>Order Layanan</th>
									<th>Aksi</th>
								</tr>
							</thead>
							<tbody>
								<?php
								if ($_GET[''] !== "") {
									$kodcustomer = ($_GET['kodcustomer']);
									$tgl = ($_GET['tgl']);
									$sdtgl = ($_GET['sdtgl']);
									$iduser = $_SESSION['DEFAULT_IDUSER'];
									try {
										$strsql = "SELECT a.idlog,a.userorder, a.iduser, b.nmcustomer,a.fasorder, a.tglorder,a.tgltarget, a.desorder, a.deslayan, a.tglselesai from tlog a inner join rcustomer b  on a.kodcustomer =b.kodcustomer where a.isselesai=1 and a.istesting=1 and a.isupdate=0 and a.stsdel=0 order by a.tglorder, a.idlog";
										$sql = $conn->prepare($strsql);


										$sql->execute();
										// tampilkan
										$no = 1;
										while ($rs = $sql->fetch()) {

											// Cek apakah $iduser sama dengan iduser atau userorder
											if ($iduser == strtoupper($rs['iduser']) || $iduser == strtoupper($rs['userorder'])) {
												// Tampilkan tombol Edit yang dapat diklik
												$btnedit = "<a href='index.php?idlog=" . $rs['idlog'] . "&par=03_edt&edt=Y' type='button' class='btn btn-sm btn-info' target='_blank'>Edit</a>";
											} else {
												// Tampilkan tombol Edit yang tidak dapat diklik (atau tombol yang tidak aktif)
												$btnedit = "<a href='#' type='button' class='btn btn-sm btn-info disabled' aria-disabled='true'>Edit</a>";
											}
											// Tambahkan kondisi untuk memeriksa tanggal target
											if ($rs['tgltarget'] < date('Y-m-d')) {

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