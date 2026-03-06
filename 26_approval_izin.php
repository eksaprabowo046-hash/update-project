<?php
// 
include "dbase.php";
include "islogin.php";

// Hanya kodjab 1 (Admin) dan 2 (GM) yang boleh akses Approval Perizinan
$kodjab_user = isset($_SESSION['DEFAULT_KODJAB']) ? $_SESSION['DEFAULT_KODJAB'] : 0;
if ($kodjab_user != 1 && $kodjab_user != 2) {
    echo "<script>alert('Anda tidak memiliki akses ke halaman Approval Perizinan!'); window.location='index.php';</script>";
    exit;
}

$pesan = "";
$tgl 	 = trim($_GET['tgl']);
$sdtgl	 = trim($_GET['sdtgl']);

if (isset($_POST['idizin'])) {
	$idizin = trim($_POST['idizin']);
	$approve = trim($_POST['approve']);
	$keterangan = trim($_POST['keterangan']);
	$tglapprove = date("Y-m-d");

	try {
		// pakai statement agar tidak bisa di inject
		$sql = $conn->prepare("UPDATE tizin SET isapprove=?, tglapprove=? , keterangan=? WHERE idizin=?");
		$sql->execute([$approve, $tglapprove, $keterangan, $idizin]);
		// Pesan sukses
		$pesan = "<font color=blue>Record updated successfully</font>";
	} catch (PDOException $e) {
		// Penanganan kesalahan
		$pesan = "<font color=red>Error update. Make sure data is correct.</font>";
	}
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

<body>

	<div class="row">
		<ol class="breadcrumb">
			<li><i class="fa fa-home"></i>APPROVAL PERIZINAN</li>
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
									<th>No</th>
									<th>User</th>
									<th>Tgl Izin</th>
									<th>Lamanya</th>
									<th>Keperluan</th>
									<th>Delete</th>
								</tr>
							</thead>
							<tbody>
								<?php
								if ($_GET[''] !== "") {
									$kodcustomer = ($_GET['kodcustomer']);
									$tgl = ($_GET['tgl']);
									$sdtgl = ($_GET['sdtgl']);
									try {
										$strsql = "SELECT a.idizin, b.nama, a.lamanya, a.keperluan, a.tglizin from tizin a INNER JOIN ruser b ON a.iduser=b.iduser
										   where a.stsdel=0 and a.isapprove=0 ORDER BY a.idizin";
										$sql = $conn->prepare($strsql);
										$sql->execute();

										// tampilkan
										$no = 1;
										while ($rs = $sql->fetch()) {
											echo "  <tr>
									<td align=center><font size=-1>" . $no . "</font></td>
									<td><font size=-1 >" . $rs['nama'] . "</font></td>
									<td><font size=-1 >" . $rs['tglizin'] . "</font></td>
									<td><font size=-1 >" . $rs['lamanya'] . "</font></td>
									<td><font size=-1 >" . $rs['keperluan'] . "</font></td>
									<td>" ?>
											<form method="POST" action="index.php?par=27" id="approveForm">
												<input type="hidden" name="idizin" id="idizin" value="<?php echo  $rs['idizin']; ?>">
												<!-- Button to trigger modal -->
												<button type="button" class="btn btn-info btn-xs" onclick="Approve('<?php echo $rs['idizin']; ?>')">Approved</button>
											</form>
											</td>
								<?php
											echo "</tr>";
											$no++;
										}
									}
									// kondisi jika terjadi error
									catch (PDOException $e) {
										echo "  
				   	<tr>
				
					
					
					
			
					</tr>  ";
									}
								}
								?>

							</tbody>
					</table>
				</div>
			</section>
		</section>

	</div>

	<!-- Modal -->
	<div class="modal fade" id="approvedModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
		<div class="modal-dialog" role="document">
			<div class="modal-content">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
					<h4 class="modal-title" id="myModalLabel">Confirm Approval</h4>
				</div>
				<div class="modal-body">
					<input type="text" name="keterangan" id="keterangan" placeholder="Keterangan..." class="form-control">
					<input type="hidden" name="idizin" id="idizin">
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-success" id="confirmApproval" data-approve="1">Approve</button>
					<button type="button" class="btn btn-danger" id="confirmReject" data-approve="2">Reject</button>
					<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
				</div>
			</div>
		</div>
	</div>
	<script>
		function Approve(id) {
			// Tampilkan modal
			$('#approvedModal').modal('show');

			// Set ID pada input tersembunyi
			$('#idizin').val(id);

			// Kosongkan nilai input 'keterangan'
			$('#keterangan').val('');
		}

		document.addEventListener('DOMContentLoaded', function() {
			var approveForm = document.getElementById('approveForm');
			var keteranganInputField = document.getElementById('keterangan');

			function handleApprovalClick(event) {
				var approveValue = event.target.getAttribute('data-approve');
				var keteranganValue = keteranganInputField.value;

				var keteranganInput = document.createElement('input');
				keteranganInput.setAttribute('type', 'hidden');
				keteranganInput.setAttribute('name', 'keterangan');
				keteranganInput.setAttribute('value', keteranganValue);

				var approveInput = document.createElement('input');
				approveInput.setAttribute('type', 'hidden');
				approveInput.setAttribute('name', 'approve');
				approveInput.setAttribute('value', approveValue);

				approveForm.appendChild(keteranganInput);
				approveForm.appendChild(approveInput);

				approveForm.submit();
			}

			document.getElementById('confirmApproval').addEventListener('click', handleApprovalClick);
			document.getElementById('confirmReject').addEventListener('click', handleApprovalClick);
		});
	</script>

</body>

</html>