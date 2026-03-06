<?php

include "dbase.php";  
$pesan = "";

$tgl 	 = date("Y-m-d");  
$sdtgl	 = date("Y-m-d"); 
$iduser  = $_SESSION['DEFAULT_IDUSER'];

if (isset($_POST['submit'])) {
	$tgl 	 = trim($_POST['tgl']);  
	$sdtgl	 = trim($_POST['sdtgl']); 
	if ($kodjab==1 || $kodjab==2){
	   $iduser  = trim($_POST['iduser']);
	}
}  

// Handler AJAX untuk detail (Modal)
if (isset($_GET['ajax_detail'])) {
    while (ob_get_level()) ob_end_clean();
    $d_iduser = $_GET['iduser'];
    $d_tgl = $_GET['tgl'];
    $d_sdtgl = $_GET['sdtgl'];
    
    try {
        $strsql = "SELECT a.*, b.nama FROM tkehadiran a INNER JOIN ruser b ON a.iduser=b.iduser 
                    WHERE a.iduser='$d_iduser' AND (a.tanggal >='$d_tgl' AND a.tanggal<='$d_sdtgl') 
                    ORDER BY a.tanggal DESC";
        $sql = $conn->prepare($strsql);
        $sql->execute();
        
        $no=1;
        while($rs = $sql->fetch()) {
            // Logika 1: Datang Terlambat vs Tepat Waktu
            $statusMasuk = ($rs['hadir'] > '08:30:00') ? '<span style="font-weight:600; color:#d9534f;">Terlambat</span>' : '<span style="font-weight:600; color:#5cb85c;">Tepat Waktu</span>';
            
            // Logika 2: Pulang Sesuai 8 Jam vs Kurang Jam
            $statusPulang = '-'; // Default jika belum absen pulang
            if ($rs['pulang'] != '00:00:00' && !empty($rs['pulang'])) {
                $timeHadir = strtotime($rs['hadir']);
                $timePulang = strtotime($rs['pulang']);
                
                $selisihDetik = $timePulang - $timeHadir;
                $delapanJamDetik = 8 * 60 * 60; // 28800 detik
                
                if ($selisihDetik < $delapanJamDetik) {
                    $statusPulang = '<span style="font-weight:600; color:#f0ad4e;">Kurang Jam Kerja</span>';
                } else {
                    $statusPulang = '<span style="font-weight:600; color:#5cb85c;">Sesuai 8 Jam</span>';
                }
            }
            
            // Gabungkan kedua status berdampingan
            $statusHadirAkhir = $statusMasuk . " | " . $statusPulang;
            
            echo "<tr> 
                    <td align=center><font size=-1>".$no."</font></td>
                    <td><font size=-1>".hari_ini(date('D', strtotime($rs['tanggal'])))."</font></td>
                    <td><font size=-1>".$rs['tanggal']."</font></td>
                    <td><font size=-1>".$rs['hadir']."</font></td>
                    <td><font size=-1>".$rs['pulang']."</font></td>
                    <td><font size=-1>".$statusHadirAkhir."</font></td>
                  </tr>";
            $no++;	
        } 
    } catch (PDOException $e) { }
    exit;
}

// Filter pegawai (opsional, hanya untuk admin)
$filter_iduser = "";
if (isset($_POST['filter_iduser'])) {
	$filter_iduser = trim($_POST['filter_iduser']);
}

// Filter status telat/tidak
$filter_status = "";
if (isset($_POST['filter_status'])) {
	$filter_status = trim($_POST['filter_status']);
}

// Hitung sum terlambat dalam range tanggal yang dipilih
$sumTerlambat = 0;
try {
	$sqlLate = "SELECT COUNT(*) as jml FROM tkehadiran a WHERE a.tanggal >= '$tgl' AND a.tanggal <= '$sdtgl' AND a.hadir > '08:30:00'";
	if ($kodjab == 1 || $kodjab == 2) {
		if ($filter_iduser != "") {
			$sqlLate .= " AND a.iduser = '$filter_iduser'";
		}
	} else {
		$sqlLate .= " AND a.iduser = '$iduser'";
	}
	$qLate = $conn->prepare($sqlLate);
	$qLate->execute();
	$rsLate = $qLate->fetch();
	$sumTerlambat = intval($rsLate['jml']);
} catch (PDOException $e) {
	$sumTerlambat = 0;
}

function hari_ini($hari){
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
	  <li><i class="fa fa-home"></i>SETTING KEHADIRAN</li> 
	</ol> 
	<section class="panel">
	  <header class="panel-heading">
		  
		<form role="form"  method="POST" onSubmit="return validasi_input(this)"  action="index.php?par=20"> 
		    <?php 
			if ($kodjab==1 || $kodjab==2) {
			?>
				<div class="form-group col-xs-3 col-sm-3">
					<label>Pegawai</label>	 
					<select name="filter_iduser" id="filter_iduser" class="form-control">
					<option value="">-- Semua Pegawai --</option>
					<?php
					//mengambil nama-nama pegawai yang ada di database  
					    $isaktif = 1;
						$qk = $conn->prepare("SELECT iduser, nama FROM ruser WHERE stsaktif=$isaktif ORDER BY nik ASC, nama ASC "); 
						$qk->execute(); 
						while($rsk = $qk->fetch()){ 
						if ( $filter_iduser<>$rsk['iduser']) {
							echo "<option value=".$rsk['iduser'].">".$rsk['nama']."</option>\n"; 
						} else {
							echo "<option value=".$rsk['iduser']." SELECTED>".$rsk['nama']."</option>\n"; 
						}	  
						}
					?>
						</select> 
				</div>	
			<?php
			}
			?> 

		  	<div class="form-group col-xs-12 col-sm-2 ">
				<label>Tanggal :  </label>
				<input name="tgl" id="dp1" type="text" value="<?php echo $tgl;?>"  onKeyPress="if (event.keyCode==13) {dp1.focus()} size="16" class="form-control" value="<?php echo $tgl; ?> "> 
	  		</div>
		
			<div class="form-group col-xs-12 col-sm-2 ">
				<label>Sampai Tanggal :  </label>
				<input name="sdtgl" id="dp2" type="text" value="<?php echo $sdtgl?>"  size="16" class="form-control"  > 
			</div> 

			<div class="form-group col-xs-12 col-sm-2">
				<label>Status</label>
				<select name="filter_status" id="filter_status" class="form-control">
					<option value="">-- Semua --</option>
					<option value="telat" <?php if($filter_status=='telat') echo 'SELECTED'; ?>>Terlambat</option>
					<option value="tepat" <?php if($filter_status=='tepat') echo 'SELECTED'; ?>>Tepat Waktu</option>
				</select>
			</div>

			<div class="form-group col-xs-6 col-sm-2">
				<label>Sum Terlambat</label>
				<div class="form-control" style="background:#fff;font-weight:600;"><?php echo $sumTerlambat; ?></div>
			</div>

			<div class="form-group col-xs-12 col-sm-2" style="margin-top:24px;">
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
	
		  <thead> 
			<tr>
			  <th style="width:40px;">#</th> 
			  <th style="width: 200px;">User</th>
			  <th>Hari</th>
			  <th>Tanggal</th>
			  <th style="white-space: nowrap; width: 100px;">SUM Tepat Waktu</th>
			  <th>Hadir</th>
			  <th style="white-space: nowrap; width: 100px;">SUM Terlambat</th>
			  <th>Pulang</th>   
			</tr>
		  </thead>
		  <tbody>
		  <?php 
			    try {  	
					// Tentukan filter user berdasarkan role
					if ($kodjab == 1 || $kodjab == 2) {
						if ($filter_iduser != "") {
							$where_user = "AND a.iduser = '$filter_iduser'";
						} else {
							$where_user = "";
						}
					} else {
						$where_user = "AND a.iduser = '$iduser'";
					}

					// Filter status telat/tepat waktu
					$where_status = "";
					if ($filter_status == 'telat') {
						$where_status = "AND a.hadir > '08:30:00'";
					} elseif ($filter_status == 'tepat') {
						$where_status = "AND a.hadir <= '08:30:00'";
					}

			        $strsql = "SELECT 
									a.iduser, 
									b.nama,
									SUM(CASE WHEN a.hadir <= '08:30:00' THEN 1 ELSE 0 END) as totalTepat,
									SUM(CASE WHEN a.hadir > '08:30:00' THEN 1 ELSE 0 END) as totalTerlambat,
									MAX(a.tanggal) as latestTgl
								FROM tkehadiran a 
								INNER JOIN ruser b ON a.iduser = b.iduser
								WHERE (a.tanggal >= '$tgl' AND a.tanggal <= '$sdtgl') $where_user $where_status 
								GROUP BY a.iduser, b.nama
								ORDER BY b.nama";
			        $sql = $conn->prepare($strsql);
					$sql->execute();
					
					$noMaster = 1;
					while($rsMaster = $sql->fetch()) {
						$uid = $rsMaster['iduser'];
						$namaUser = $rsMaster['nama'];
						$totalTepat = $rsMaster['totalTepat'];
						$totalTerlambat = $rsMaster['totalTerlambat'];
						$latestTgl = $rsMaster['latestTgl'];
						
						// Ambil jam hadir & pulang di tanggal terbaru (latestTgl) tersebut
						$qLatest = $conn->prepare("SELECT hadir, pulang FROM tkehadiran WHERE iduser = ? AND tanggal = ? LIMIT 1");
						$qLatest->execute([$uid, $latestTgl]);
						$latestData = $qLatest->fetch();
						
						$latestHari = hari_ini(date('D', strtotime($latestTgl)));
						$latestHadir = $latestData['hadir'] ?? '-';
						$latestPulang = $latestData['pulang'] ?? '-';
						
						$btn_nama = addslashes($namaUser);
						echo "<tr>
							<td align=center><font size=-1><a href='javascript:void(0)' onclick=\"showDetail('".$uid."', '".$btn_nama."', '".$tgl."', '".$sdtgl."')\" class='btn btn-xs btn-info' style='margin-right:5px;' title='Lihat Detail'><i class='fa fa-search'></i></a> ".$noMaster."</font></td>
							<td><font size=-1>".$namaUser."</font></td>
							<td><font size=-1>".$latestHari."</font></td>
							<td><font size=-1>".$latestTgl."</font></td>
							<td align=center><font size=-1><span style='font-weight:600;'>".$totalTepat."</span></font></td>
							<td><font size=-1>".$latestHadir."</font></td>
							<td align=center><font size=-1><span style='font-weight:600;'>".$totalTerlambat."</span></font></td>
							<td><font size=-1>".$latestPulang."</font></td>
						</tr>";
						
						$noMaster++;
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
	  <div id="sectionDetail" style="display:none; margin-top:50px; border-top:2px solid #5bc0de; padding-top:30px;">
		<div align="left" style="margin-bottom:15px; padding-left:15px;"><strong>DETAIL KEHADIRAN - <span id="detailNama"></span></strong></div>
		<div class="box-body">
			<table class="table table-bordered table-striped table-hover">
				<thead>
					<tr>
						<th style="width:40px;">#</th>
						<th>Hari</th>
						<th>Tanggal</th>
						<th>Hadir</th>
						<th>Pulang</th>
						<th>Status</th>
					</tr>
				</thead>
				<tbody id="detailContent">
					<!-- Konten akan dimuat via AJAX -->
				</tbody>
			</table>
			<div align="right">
				<button type="button" class="btn btn-default btn-sm" onclick="$('#sectionDetail').fadeOut();">Tutup Detail</button>
			</div>
		</div>
	  </div>
	  </section>
	</section>
   
</div> 

<script type="text/javascript">
function showDetail(uid, nama, tgl1, tgl2) {
    $('#detailNama').text(nama);
    $('#detailContent').html('<tr><td colspan="6" align="center">Sedang memuat data...</td></tr>');
    $('#sectionDetail').fadeIn();
    
    // Scroll ke detail
    $('html, body').animate({
        scrollTop: $("#sectionDetail").offset().top - 20
    }, 500);

    $.ajax({
        url: 'index.php?par=20&ajax_detail=1',
        type: 'GET',
        data: { iduser: uid, tgl: tgl1, sdtgl: tgl2 },
        success: function(response) {
            $('#detailContent').html(response);
        },
        error: function() {
            $('#detailContent').html('<tr><td colspan="6" align="center" style="color:red;">Gagal mengambil data.</td></tr>');
        }
    });
}
</script>
 
</body>

</html>
