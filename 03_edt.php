<?php
// 
session_start();
include "dbase.php";
include "islogin.php";
$pesan = "";
date_default_timezone_set('Asia/Jakarta');

function left($str, $length)
{
	return substr($str, 0, $length);
}

$uploadMessage = "";
if (isset($_POST['edt_exe']) && $_POST['edt_exe'] == 'Y') {
    
    $idlog = $_POST['idlog'];
    $jnsbisnis = $_POST['jnsbisnis'];
    $kodcustomer = $_POST['kodcustomer'];
    $tglorder = $_POST['tglorder'];
    $tgltarget = $_POST['tgltarget'];
    $userorder = $_POST['dikerjakan'];
    $prioritas = $_POST['prioritas'];
    $fasorder = $_POST['fasorder'];
    $desorder = $_POST['desorder'];
    $deslayan = $_POST['deslayan'];
    $ketterlambat = $_POST['ketterlambat'];
    $isselesai = $_POST['selesai'];
    $tglselesai = $_POST['tglselesai'];
    $istesting = $_POST['testing'];
    $tgltesting = $_POST['tgltesting'];
    $isupdate = $_POST['update'];
    $tglupdate = $_POST['tglupdate'];
    $nilai = isset($_POST['nilai']) ? $_POST['nilai'] : null;
    
    $newFileName = null; // Variable untuk menyimpan nama file
    
    $fileMetadata = []; // Array untuk menyimpan metadata file baru

	// Ambil file uploads yang sudah ada dari database
	$existingFiles = [];
    $sqlGetFiles = "SELECT file_uploads FROM tlog WHERE idlog = :idlog";
    $stmtGetFiles = $conn->prepare($sqlGetFiles);
    $stmtGetFiles->bindParam(':idlog', $idlog, PDO::PARAM_INT);
    $stmtGetFiles->execute();
    $resultFiles = $stmtGetFiles->fetch(PDO::FETCH_ASSOC);

	if ($resultFiles && !empty($resultFiles['file_uploads'])) {
        $existingFiles = json_decode($resultFiles['file_uploads'], true) ?? [];
    }


	// Proses multiple file uploads
	if (isset($_FILES['file_upload']) && is_array($_FILES['file_upload']['name'])) {
		$uploadDir = 'uploads/log/';
		if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
		$allowedImage = ['jpg', 'jpeg', 'png', 'gif'];
		$allowedVideo = ['mp4', 'avi', 'mov', 'wmv', 'mkv'];
		$allowedExt   = array_merge($allowedImage, $allowedVideo);
		$maxSize      = 50 * 1024 * 1024;
		foreach ($_FILES['file_upload']['name'] as $i => $origName) {
			if ($_FILES['file_upload']['error'][$i] !== UPLOAD_ERR_OK) continue;
			if (empty($origName)) continue;
			$tmpName  = $_FILES['file_upload']['tmp_name'][$i];
			$fileSize = $_FILES['file_upload']['size'][$i];
			$fileExt  = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
			if (!in_array($fileExt, $allowedExt)) {
				$uploadMessage .= "<font color='red'>✗ {$origName}: format tidak diizinkan.</font><br>";
				continue;
			}
			if ($fileSize > $maxSize) {
				$uploadMessage .= "<font color='red'>✗ {$origName}: ukuran terlalu besar (maks 50MB).</font><br>";
				continue;
			}
			$newFileName = 'log_' . $idlog . '_' . date('Ymd_His') . '_' . $i . '.' . $fileExt;
			if (move_uploaded_file($tmpName, $uploadDir . $newFileName)) {
				$existingFiles[] = [
					'filename'      => $newFileName,
					'original_name' => $origName,
					'file_size'     => $fileSize,
					'file_type'     => in_array($fileExt, $allowedImage) ? 'image' : 'video',
					'extension'     => $fileExt,
					'upload_date'   => date('Y-m-d H:i:s'),
					'uploaded_by'   => $_SESSION['DEFAULT_IDUSER']
				];
				$uploadMessage .= "<font color='green'>✓ {$origName} berhasil diupload.</font><br>";
			} else {
				$uploadMessage .= "<font color='red'>✗ {$origName}: gagal diupload.</font><br>";
			}
		}
	}
    
    // PROSES UPDATE DATA KE DATABASE
    try {
			// Prepare SQL query
			$sql = "UPDATE tlog SET 
			jnsbisnis = :jnsbisnis,
			kodcustomer = :kodcustomer,
			tglorder = :tglorder,
			tgltarget = :tgltarget,
			userorder = :userorder,
			prioritas = :prioritas,
			fasorder = :fasorder,
			desorder = :desorder,
			deslayan = :deslayan,
			ketterlambat = :ketterlambat,
			isselesai = :isselesai,
			tglselesai = :tglselesai,
			istesting = :istesting,
			tgltesting = :tgltesting,
			isupdate = :isupdate,
			tglupdate = :tglupdate,
			id_parent = :id_parent,
			file_uploads = :file_uploads";

		if ($nilai !== null) {
			$sql .= ", nilai = :nilai";
		}

		$sql .= " WHERE idlog = :idlog";

		$stmt = $conn->prepare($sql);

		// Bind parameter file_uploads
		$fileUploadsJson = json_encode($existingFiles);
		$stmt->bindParam(':file_uploads', $fileUploadsJson);
        $stmt->bindParam(':jnsbisnis', $jnsbisnis);
        $stmt->bindParam(':kodcustomer', $kodcustomer);
        $stmt->bindParam(':tglorder', $tglorder);
        $stmt->bindParam(':tgltarget', $tgltarget);
        $stmt->bindParam(':userorder', $userorder);
        $stmt->bindParam(':prioritas', $prioritas);
        $stmt->bindParam(':fasorder', $fasorder);
        $stmt->bindParam(':desorder', $desorder);
        $stmt->bindParam(':deslayan', $deslayan);
        $stmt->bindParam(':ketterlambat', $ketterlambat);
        $stmt->bindParam(':isselesai', $isselesai);
        $stmt->bindParam(':tglselesai', $tglselesai);
        $stmt->bindParam(':istesting', $istesting);
        $stmt->bindParam(':tgltesting', $tgltesting);
        $stmt->bindParam(':isupdate', $isupdate);
        $stmt->bindParam(':tglupdate', $tglupdate);
        
        if ($nilai !== null) {
            $stmt->bindParam(':nilai', $nilai);
        }
        
        $stmt->bindParam(':idlog', $idlog);
        
        // Subtask: id_parent
        $id_parent_val = isset($_POST['id_parent']) && $_POST['id_parent'] !== '' ? intval($_POST['id_parent']) : null;
        $stmt->bindParam(':id_parent', $id_parent_val, PDO::PARAM_INT);
        
        // Execute query
        $stmt->execute();
        
        $pesan = "<font color='green'>✓ Data berhasil diupdate!</font>";
    } catch (PDOException $e) {
        $pesan = "<font color='red'>✗ Error update: " . $e->getMessage() . "</font>";
    }
}

if (isset($_GET['edt'])) {
	$idlog = trim($_GET['idlog']);
	// Menyiapkan dan mengeksekusi pernyataan SQL
	$sql = "SELECT * FROM tlog WHERE idlog = :idlog";
	$qcek = $conn->prepare($sql);
	$qcek->bindParam(':idlog', $idlog, PDO::PARAM_INT);
	$qcek->execute();

	// Mengeksekusi pernyataan SQL
	$data = $qcek->fetch(PDO::FETCH_ASSOC);
	$uploadedFiles = [];
	if (!empty($data['file_uploads'])) {
		$uploadedFiles = json_decode($data['file_uploads'], true) ?? [];
	}
	if ($data != null) {
		$kodcustomer 	= $data['kodcustomer'];
		$nmcustomer 	= $data['nmcustomer'];
		$jnsbisnis 		= $data['jnsbisnis'];
		$tglorder 		= $data['tglorder'];
		$tgltarget      = $data['tgltarget'];
		$userorder 		= $data['userorder'];
		$prioritas 		= $data['prioritas'];
		$fasorder 		= $data['fasorder'];
		$desorder 		= $data['desorder'];
		$deslayan 		= $data['deslayan'];
		$isselesai 		= $data['isselesai'];
		$tglselesai 	= $data['tglselesai'] == '0000-00-00' ? '' : $data['tglselesai'];
		$istesting 		= $data['istesting'];
		$tgltesting 	= $data['tgltesting'] == '0000-00-00' ? '' : $data['tgltesting'];
		$isupdate 		= $data['isupdate'];
		$tglupdate 		= $data['tglupdate'] == '0000-00-00' ? '' : $data['tglupdate'];
		$ketterlambat	= $data['ketterlambat'];
		$nilai 			= $data['nilai'];
		$id_parent 		= isset($data['id_parent']) ? $data['id_parent'] : null;
	}
	$iduser   = $_SESSION['DEFAULT_IDUSER'];
	$tgl      = $_GET['tgl'];
	$sdtgl    = $_GET['sdtgl'];
}
// ambil enum
$sqldesc = "DESC tlog";
$qdesc = $conn->prepare($sqldesc);
$qdesc->execute();
$fieldAll = $qdesc->fetchAll();

$enumFields = array();
foreach ($fieldAll as $value) {
	if (strpos($value['Type'], 'enum') !== false) {
		// Ekstrak nilai enum dari string Type
		preg_match_all("/'([^']*)'/", $value['Type'], $matches);
		$enumFields[$value['Field']] = $matches[1]; // Menyimpan sebagai array
	}
}

//cek user
$isboleh = 0;
$sqlcek = "SELECT * from tlog WHERE idlog=$idlog and (iduser='$iduser' or userorder='$iduser') ";

try {
	$qcek = $conn->prepare($sqlcek) or die($conn->errorInfo());
	$qcek->execute();
	if ($qcek->rowCount() < 1) {
		$pesan = "<font color=red>Log <strong>" . $idlog . " </strong> bukan milik user <strong>" . $iduser . " </strong></font><br>";
		$pesan = $pesan . "<font color=red>Tidak boleh update yang bukan lognya</font>";
	} else {
		$isboleh = 1;
	}
} //try
catch (PDOException $e) {
	//echo "<font color=red>Error insert. Make sure kode barang is correct.</font>" ;
	$pesan =  "<font color=red>Error checking. Make sure <strong>" . $idlog . "</strong> is correct.</font>";
} //catch  

if ($isboleh == 0) {
	echo "<script>window.location='index.php?par=03&para=ditolak&pesan=" . urlencode($pesan) . "';</script>";
	exit;
}

?>

<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>

<style>
/* Quill editor — sesuaikan dengan Bootstrap form-control */
.ql-toolbar.ql-snow {
    border: 1px solid #ccc;
    border-bottom: 1px solid #e0e0e0;
    border-radius: 4px 4px 0 0;
    padding: 4px 8px;
    background: #fafafa;
    font-family: 'Lato', sans-serif;
    height: 38px;
    white-space: nowrap;
    overflow-x: auto;
    overflow-y: hidden;
    scrollbar-width: thin;
}
.ql-toolbar.ql-snow::-webkit-scrollbar {
    height: 4px;
}
.ql-toolbar.ql-snow::-webkit-scrollbar-thumb {
    background: #ccc;
    border-radius: 2px;
}
.ql-toolbar.ql-snow::-webkit-scrollbar-track {
    background: transparent;
}
.ql-container.ql-snow {
    border: 1px solid #ccc;
    border-top: none;
    border-radius: 0 0 4px 4px;
    height: 120px;
    background: #fff;
    font-size: 14px;
    font-family: 'Lato', sans-serif;
    color: #797979;
}
.ql-editor {
    height: 120px;
    padding: 8px 12px;
    line-height: 1.5;
    color: #797979;
    overflow-y: auto;
}
.ql-editor.ql-blank::before {
    color: #aaa;
    font-style: normal;
    font-size: 14px;
    font-family: 'Lato', sans-serif;
}
/* Sama dengan *:focus {outline: none} di style.css */
.ql-container.ql-snow:focus-within,
.ql-toolbar.ql-snow:focus-within {
    outline: none;
    box-shadow: none;
    border-color: #ccc;
}
</style>

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
		location.replace("index.php?par=01&idkategori=" + id);

	}
</script>

<script type="text/javascript">
	function validasi_input(form) {
		if (form.idkategori.value == "blm_pilih") {
			alert("Kelompok barang belum dipilih!");
			form.idkategori.focus();
			return (false);
		}

		if (form.barcode.value == "") {
			alert("Barcode masih kosong!");
			form.barcode.focus();
			return (false);
		}

		return (true);
	}

	// Validasi file upload
	// Fungsi upload dinamis — sama seperti Create Log
	var uploadIndex = 0;
	function tambahBaris() {
		var div = document.createElement('div');
		div.className = 'upload-row';
		div.style.cssText = 'display:flex;align-items:center;gap:8px;margin-bottom:8px;';
		div.innerHTML =
			'<input type="file" name="file_upload[]" accept="image/*,video/*" class="form-control" style="flex:1;">'+
			'<button type="button" class="btn btn-danger btn-xs" onclick="this.parentNode.remove()"><i class="fa fa-times"></i></button>';
		document.getElementById('uploadRows').appendChild(div);
	}
	// Satu baris otomatis saat halaman dibuka
	tambahBaris();
</script>

<body>
	<div class="row">
		<ol class="breadcrumb">
			<li><i class="fa fa-home"></i>UPDATE LOG</li>
		</ol>
		<section class="panel">
			<header class="panel-heading">
				<form role="form" method="POST" onSubmit="return validasi_input(this)" action="" enctype="multipart/form-data">
					<div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-6">
						<label>Kelompok Bisnis</label>
						<select name="jnsbisnis" id="jnsbisnis" class="form-control" placeholder="Jenis Bisnis..." onKeyPress="if (event.keyCode==13) {kodcustomer.focus()}">
							<option value="A" <?= $jnsbisnis == "A" ? "selected" : "" ?>>A:Administration</option>
							<option value="B" <?= $jnsbisnis == "B" ? "selected" : "" ?>>B:Business</option>
							<option value="D" <?= $jnsbisnis == "D" ? "selected" : "" ?>>D:Developing</option>
							<option value="M" <?= $jnsbisnis == "M" ? "selected" : "" ?>>M:Maintenance</option>
						</select>
					</div>
					<div class="form-group col-xs-12 col-sm-3 col-md-3 col-lg-3">
						<label>Customer / Mitra</label>
						<?php echo $kodcustomer; ?>
						<select name="kodcustomer" id="kodcustomer" class="select2 form-control" placeholder="Customer..." onKeyPress="if (event.keyCode==13) {tglorder.focus()}">
							<?php
							//mengambil nama-nama kategori yang ada di database  
							$qk = $conn->prepare("SELECT * FROM rcustomer WHERE status = 1 ORDER BY kodcustomer ");
							$qk->execute();
							while ($rsk = $qk->fetch()) {
								$selected = ($kodcustomer == $rsk['kodcustomer']) ? "SELECTED" : "";
								echo "<option value='" . $rsk['kodcustomer'] . "' $selected>" . $rsk['nmcustomer'] . "</option>";
							}
							?>
						</select>
					</div>
					<div class="form-group col-xs-12 col-sm-3 col-md-3 col-lg-3">
						<label>Nilai</label>
						<div class="search-box-material">
							<select name="nilai" id="nilai" class="form-control" onKeyPress="if (event.keyCode==13) {kodcustomer.focus()}" <?= $_SESSION['DEFAULT_KODJAB'] == '1' ? '' : 'disabled' ?>>
								<option value="" disabled selected>-- Nilai --</option>
								<?php
								foreach ($enumFields['nilai'] as $key => $value) {
									$nameNilai = nameKolomNilai($value);
									echo "<option value='{$value}' " . ($nilai == $value ? 'selected' : '') . ">{$nameNilai}</option>";
								}
								?>
							</select>
						</div>
					</div>
					<div class="col-xs-12 col-sm-6 col-md-6 col-lg-3">
						<div class="form-group">
							<label>Tanggal Order</label>
							<input name="tglorder" size="7" autocomplete="off" class="form-control date-picker" value="<?php echo $tglorder; ?>" onKeyPress="if (event.keyCode==13) {tgltarget.focus()}">
						</div>
					</div>
					<div class="col-xs-12 col-sm-6 col-md-6 col-lg-3 ">
						<div class="form-group">
							<label>Target Selesai</label>
							<input name="tgltarget" id="dp2" autocomplete="off" size="7" class="form-control" value="<?php echo $tgltarget; ?>" onKeyPress="if (event.keyCode==13) {tglorder.focus()}">
						</div>
					</div>
					<div class="col-xs-12 col-sm-6 col-md-6 col-lg-2 ">
						<label>Dikerjakan Oleh</label>
						<select name="dikerjakan" id="dikerjakan" class="form-control" placeholder="Dikerjakan Oleh" onKeyPress="if (event.keyCode==13) {tgltarget.focus()}">
							<option value="" selected disabled>-- Pilih User --</option>
							<?php
							//mengambil nama-nama kategori yang ada di database  
							$qk = $conn->prepare("SELECT * FROM ruser WHERE stsaktif = 1 ORDER BY nik ASC, nama ASC ");
							$qk->execute();
							while ($rsk = $qk->fetch()) {
								$selected = ($userorder == $rsk['iduser']) ? "SELECTED" : "";
								echo "<option value='" . $rsk['iduser'] . "' $selected>" . $rsk['nama'] . "</option>";
							}
							?>
						</select>
					</div>
					<div class="col-xs-12 col-sm-6 col-md-6 col-lg-2 ">
						<div class="form-group">
							<label>Status Prioritas</label>
							<div class="search-box-material">
								<select name="prioritas" id="prioritas" class="form-control" onKeyPress="if (event.keyCode==13) {kodcustomer.focus()}">
									<option value="" disabled>-- Pilih Prioritas --</option>
									<option value="1" <?= ($prioritas == '1') ? 'selected' : '' ?>>Sangat Tinggi</option>
									<option value="2" <?= ($prioritas == '2') ? 'selected' : '' ?>>Tinggi</option>
									<option value="3" <?= ($prioritas == '3') ? 'selected' : '' ?>>Biasa</option>
								</select>
							</div>
						</div>
					</div>
					<div class="col-xs-12 col-sm-6 col-md-6 col-lg-2 ">
						<div class="form-group">
							<label>Order Melalui</label>
							<div class="search-box-material">
								<input name="fasorder" onKeyUp="this.value = this.value.toUpperCase();" value="<?php echo $fasorder; ?>" class="form-control" id="fasorder" type="text1" step="any" autocomplete="off">
								<div class="result1"></div>
							</div>
						</div>
					</div>
					<div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-3">
						<label>Subtask dari (Opsional)</label>
						<select name="id_parent" class="select2 form-control">
							<option value="">-- Bukan Subtask --</option>
							<?php
							try {
								$qparent = $conn->prepare("SELECT t.idlog, t.desorder, c.nmcustomer FROM tlog t LEFT JOIN rcustomer c ON t.kodcustomer = c.kodcustomer WHERE t.stsdel = 0 AND t.id_parent IS NULL AND t.idlog != :current_id ORDER BY t.idlog DESC LIMIT 100");
								$qparent->execute([':current_id' => $idlog]);
								while($rsparent = $qparent->fetch()) {
									$parentLabel = '#' . $rsparent['idlog'] . ' - ' . mb_substr(strip_tags($rsparent['desorder']), 0, 50) . ' (' . $rsparent['nmcustomer'] . ')';
									$sel = (isset($id_parent) && $id_parent == $rsparent['idlog']) ? 'selected' : '';
									echo "<option value='".$rsparent['idlog']."' $sel>".htmlspecialchars($parentLabel)."</option>\n";
								}
							} catch (PDOException $e) {}
							?>
						</select>
					</div>
					<div class="clearfix"></div>
					<div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-6">
						<label>Uraian Order</label>
						<div class="search-box-material">
							<input type="hidden" name="desorder" id="desorder">
							<div id="desorder_editor"></div>
						</div>
					</div>
					<div class="form-group col-xs-12 col-sm-6 col-md-3 col-lg-3">
						<label>Aktivitas Layanan</label>
						<div class="search-box-material">
							<input type="hidden" name="deslayan" id="deslayan">
							<div id="deslayan_editor"></div>
						</div>
					</div>
					<div class="form-group col-xs-12 col-sm-6 col-md-3 col-lg-3">
						<label>Ket Terlambat</label>
						<div class="search-box-material">
							<input type="hidden" name="ketterlambat" id="ketterlambat">
							<div id="ketterlambat_editor"></div>
						</div>
					</div>
					<!-- Status dari produksi -->
					<div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-6">
						<!-- <label>Status Produksi</label> -->
						<label style="display: flex; align-items: center; justify-content: space-between;"><span>Status Produksi</span><span>Samakan Status <input type="checkbox" name="samakan_status" class="samakan_status"></span></label>
						<select name="selesai" id="selesai" class="form-control" placeholder="Status..." onKeyPress="if (event.keyCode==13) {kodbrg.focus()}">
							<option value="0" <?= $isselesai == 0 ? 'selected' : ''; ?>>Belum</option>
							<option value="1" <?= $isselesai == 1 ? 'selected' : ''; ?>>Selesai</option>
						</select>
					</div>
					<div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-6">
						<label style="display: flex; align-items: center; justify-content: space-between;"><span>Tanggal Selesai</span><span>Samakan Tanggal <input type="checkbox" name="samakan_tanggal" class="samakan_tanggal"></span></label>
						<input name="tglselesai" value="<?php echo $tglselesai; ?>" autocomplete="off" size="16" class="form-control date-picker" onKeyPress="if (event.keyCode==13) {nmbrg.focus()}">
					</div>
					<!-- Status dari Testing -->
					<div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-6">
						<label>Status Testing</label>
						<select name="testing" id="testing" class="form-control" placeholder="Status..." onKeyPress="if (event.keyCode==13) {kodbrg.focus()}">
							<option value="0" <?= $istesting == 0 ? 'selected' : ''; ?>>Belum</option>
							<option value="1" <?= $istesting == 1 ? 'selected' : ''; ?>>Selesai</option>
						</select>
					</div>
					<div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-6">
						<label>Tanggal Selesai</label>
						<input name="tgltesting" id="dp3" value="<?php echo $tgltesting; ?>" autocomplete="off" size="16" class="form-control" onKeyPress="if (event.keyCode==13) {nmbrg.focus()}">
					</div>
					<!-- Status dari Update -->
					<div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-6">
						<label>Status Update</label>
						<select name="update" id="update" class="form-control" placeholder="Status..." onKeyPress="if (event.keyCode==13) {kodbrg.focus()}">
							<option value="0" <?php echo ($isupdate == 0) ? 'selected' : ''; ?>>Belum</option>
							<option value="1" <?php echo ($isupdate == 1) ? 'selected' : ''; ?>>Selesai</option>
						</select>
					</div>
					<div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-6">
						<label>Tanggal Selesai</label>
						<input name="tglupdate" id="dp4" value="<?php echo $tglupdate; ?>" autocomplete="off" size="16" class="form-control" onKeyPress="if (event.keyCode==13) {nmbrg.focus()}">
					</div>
					<div class="form-group text-right" style="margin-bottom: 0px;">
						<label style="margin-bottom: 0px;">Selesai di Tgl. Order <input type="checkbox" name="selesai_tglorder" class="selesai_tglorder"></label>
						<label style="margin-left: 12px; margin-bottom: 0px;">Selesai Hari Ini <input type="checkbox" name="selesai_hariini" class="selesai_hariini"></label>
					</div>
					<!-- Upload File Section -->
					<div class="form-group col-xs-12" style="margin-top:10px;">
						<label><i class="fa fa-upload"></i> Upload File</label>
						<div id="uploadRows"></div>
						<button type="button" class="btn btn-default btn-sm" onclick="tambahBaris()">
							<i class="fa fa-plus"></i> Tambah File
						</button>
						<small class="help-block">Foto (JPG, JPEG, PNG, GIF) / Video (MP4, AVI, MOV, WMV, MKV). Maks 50MB per file.</small>
					</div>
					<div class="form-group col-xs-12 col-sm-4 col-md-4 col-lg-4 text-left">
						<label><i class="fa fa-eye"></i> File Terupload</label>
						<div>
							<?php if (!empty($uploadedFiles) && count($uploadedFiles) > 0): ?>
								<button type="button" class="btn btn-info btn-block" onclick="viewUploadedFiles()">
									<i class="fa fa-file"></i> Lihat File (<?php echo count($uploadedFiles); ?>)
								</button>
							<?php else: ?>
								<button type="button" class="btn btn-default btn-block" disabled>
									<i class="fa fa-file"></i> Belum Ada File
								</button>
							<?php endif; ?>
						</div>
					</div>
					<div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-6">
						<input type="hidden" name="tgl" id="idlog" value="<?php echo $tgl; ?>">
						<input type="hidden" name="sdtgl" id="par" value="<?php echo $sdtgl; ?>">
						<input type="hidden" name="idlog" id="idlog" value="<?php echo $idlog; ?>">
						<input type="hidden" name="par" id="par" value="03">
						<input type="hidden" name="edt_exe" id="edt_exe" value="Y">
						<button type="submit" name="submit" class="btn btn-primary" value="Y" id="btn_update_log">Update Data</button>
						<button type="reset" class="btn btn-danger" id="btn_reset_log">Reset</button>
					</div>
				</form>
				<div class="clearfix"> </div>
				<h4>
					<font color="red"><?php echo $pesan; ?></font>
					<?php echo $uploadMessage; ?>
				</h4>
			</header>
		</section>
	</div>

	<style>
		.btn-file {
			position: relative;
			overflow: hidden;
		}
		.btn-file input[type=file] {
			position: absolute;
			top: 0;
			right: 0;
			min-width: 100%;
			min-height: 100%;
			font-size: 100px;
			text-align: right;
			filter: alpha(opacity=0);
			opacity: 0;
			outline: none;
			background: white;
			cursor: inherit;
			display: block;
		}
	</style>

	<!-- Modal untuk view files -->
	<div id="fileModal" class="modal fade" tabindex="-1" role="dialog">
		<div class="modal-dialog modal-lg">
			<div class="modal-content">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal">&times;</button>
					<h4 class="modal-title"><i class="fa fa-files-o"></i> File Terupload</h4>
				</div>
				<div class="modal-body">
					<div class="table-responsive">
						<table class="table table-bordered table-striped">
							<thead>
								<tr>
									<th width="5%">#</th>
									<th width="30%">Nama File</th>
									<th width="15%">Tipe</th>
									<th width="15%">Ukuran</th>
									<th width="20%">Tanggal Upload</th>
									<th width="15%">Aksi</th>
								</tr>
							</thead>
							<tbody id="fileTableBody">
								<!-- Akan diisi dengan JavaScript -->
							</tbody>
						</table>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
				</div>
			</div>
		</div>
	</div>

	<!-- Modal untuk preview image/video -->
	<div id="previewModal" class="modal fade" tabindex="-1" role="dialog">
		<div class="modal-dialog modal-lg">
			<div class="modal-content">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal">&times;</button>
					<h4 class="modal-title" id="previewTitle">Preview File</h4>
				</div>
				<div class="modal-body text-center" id="previewBody">
					<!-- Akan diisi dengan JavaScript -->
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
				</div>
			</div>
		</div>
	</div>

	<script>
	// Data files dari PHP
	var uploadedFilesData = <?php echo json_encode($uploadedFiles); ?>;

	function viewUploadedFiles() {
		var tbody = document.getElementById('fileTableBody');
		tbody.innerHTML = '';
		
		if (uploadedFilesData.length === 0) {
			tbody.innerHTML = '<tr><td colspan="6" class="text-center">Tidak ada file</td></tr>';
		} else {
			uploadedFilesData.forEach(function(file, index) {
				var fileSize = (file.file_size / 1024).toFixed(2) + ' KB';
				if (file.file_size > 1024 * 1024) {
					fileSize = (file.file_size / (1024 * 1024)).toFixed(2) + ' MB';
				}
				
				var row = '<tr>' +
					'<td>' + (index + 1) + '</td>' +
					'<td>' + file.original_name + '</td>' +
					'<td><span class="label label-' + (file.file_type === 'image' ? 'success' : 'info') + '">' + 
						file.file_type.toUpperCase() + '</span></td>' +
					'<td>' + fileSize + '</td>' +
					'<td>' + file.upload_date + '</td>' +
					'<td>' +
						'<button class="btn btn-sm btn-primary" onclick="previewFile(\'' + file.filename + '\', \'' + 
						file.file_type + '\', \'' + file.original_name + '\')" title="Preview">' +
						'<i class="fa fa-eye"></i></button> ' +
						'<a href="uploads/log/' + file.filename + '" download="' + file.original_name + '" ' +
						'class="btn btn-sm btn-success" title="Download">' +
						'<i class="fa fa-download"></i></a>' +
					'</td>' +
				'</tr>';
				
				tbody.innerHTML += row;
			});
		}
		
		$('#fileModal').modal('show');
	}

	function previewFile(filename, fileType, originalName) {
		var previewBody = document.getElementById('previewBody');
		var previewTitle = document.getElementById('previewTitle');
		
		previewTitle.textContent = 'Preview: ' + originalName;
		
		if (fileType === 'image') {
			previewBody.innerHTML = '<img src="uploads/log/' + filename + '" class="img-responsive" style="max-height: 500px;">';
		} else if (fileType === 'video') {
			previewBody.innerHTML = '<video controls style="max-width: 100%; max-height: 500px;">' +
				'<source src="uploads/log/' + filename + '" type="video/mp4">' +
				'Browser Anda tidak mendukung video tag.' +
				'</video>';
		}
		
		$('#previewModal').modal('show');
	}
	</script>

	<style>
	.modal-lg {
		width: 90%;
		max-width: 1000px;
	}

	#previewBody img,
	#previewBody video {
		margin: 0 auto;
	}
	</style>

<script>
// ========== QUILL RICH EDITOR — EDIT LOG ==========
(function() {
    var toolbarOptions = [
        [{ 'header': [1, 2, 3, false] }],
        ['bold', 'italic', 'underline', 'strike'],
        [{ 'list': 'ordered' }, { 'list': 'bullet' }],
        ['link', 'image'],
        [{ 'color': [] }, { 'background': [] }],
        ['clean']
    ];

    // Editor: Uraian Order
    var quill_desorder = new Quill('#desorder_editor', {
        theme: 'snow',
        modules: { toolbar: toolbarOptions },
        placeholder: 'Uraian Order...'
    });
    quill_desorder.clipboard.dangerouslyPasteHTML(<?php echo json_encode((string)$desorder); ?>);

    // Editor: Aktivitas Layanan
    var quill_deslayan = new Quill('#deslayan_editor', {
        theme: 'snow',
        modules: { toolbar: toolbarOptions },
        placeholder: 'Tulis Tanggal dan Aktifitasnya...'
    });
    quill_deslayan.clipboard.dangerouslyPasteHTML(<?php echo json_encode((string)$deslayan); ?>);

    // Editor: Ket Terlambat
    var quill_ketterlambat = new Quill('#ketterlambat_editor', {
        theme: 'snow',
        modules: { toolbar: toolbarOptions },
        placeholder: 'Ket Keterlambatan...'
    });
    quill_ketterlambat.clipboard.dangerouslyPasteHTML(<?php echo json_encode((string)$ketterlambat); ?>);

    // Sync nilai Quill ke hidden input sebelum form submit
    var btn_update = document.getElementById('btn_update_log');
    if (btn_update) {
        btn_update.addEventListener('click', function() {
            document.getElementById('desorder').value     = quill_desorder.root.innerHTML;
            document.getElementById('deslayan').value     = quill_deslayan.root.innerHTML;
            document.getElementById('ketterlambat').value = quill_ketterlambat.root.innerHTML;
        });
    }

    // Reset juga membersihkan editor
    var btn_reset = document.getElementById('btn_reset_log');
    if (btn_reset) {
        btn_reset.addEventListener('click', function() {
            quill_desorder.setText('');
            quill_deslayan.setText('');
            quill_ketterlambat.setText('');
        });
    }
})();
</script>

</body>

</html>