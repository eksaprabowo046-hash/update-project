<?php
// 
include "dbase.php";  
include_once "helper_log_display.php";
include "islogin.php";
$pesan = "";

// Inisialisasi variabel filter
if (isset($_GET['tgl'])) {
	$tgl 	 = trim($_GET['tgl']);  
	$sdtgl	 = trim($_GET['sdtgl']);
}else {
    $tgl 	 = date('Y-m-d');  
	$sdtgl	 = date('Y-m-d');
} 

// Filter customer (opsional)
$kodcustomer = "";
if (isset($_GET['kodcustomer'])) { 
	if (!isset($_GET['ins']) && !isset($_GET['del'])) {
		$kodcustomer = trim($_GET['kodcustomer']);
	} 
}

// Filter kelompok bisnis (opsional)
$jnsbisnis = "";
if (isset($_GET['jnsbisnis'])) {
	$jnsbisnis = trim($_GET['jnsbisnis']);
}

// Filter dikerjakan oleh (opsional)
$filter_user = "";
if (isset($_GET['filter_user'])) {
	$filter_user = trim($_GET['filter_user']);
}

// Filter by Sprint
$filter_idsprint = isset($_GET['idsprint']) && intval($_GET['idsprint']) > 0 ? intval($_GET['idsprint']) : "";

// ========== HITUNG SUMMARY ==========
$totalLog = 0;
$totalMasihProses = 0;
$totalTerlambat = 0;

try {
	$swhere_sum = " WHERE (a.tglorder BETWEEN '$tgl' AND '$sdtgl') and a.stsdel=0";
	if ($kodcustomer != "") {
		$swhere_sum .= " AND a.kodcustomer = '$kodcustomer'";
	}
	if ($jnsbisnis != "") {
		$swhere_sum .= " AND a.jnsbisnis = '$jnsbisnis'";
	}
	if ($filter_user != "") {
		$swhere_sum .= " AND a.iduser = '$filter_user'";
	}
	if ($filter_idsprint != "") {
		$swhere_sum .= " AND a.idsprint = $filter_idsprint";
	}

	$sql_total = $conn->prepare("SELECT COUNT(*) as total FROM tlog a $swhere_sum");
	$sql_total->execute();
	$totalLog = $sql_total->fetch(PDO::FETCH_ASSOC)['total'];

	$sql_proses = $conn->prepare("SELECT COUNT(*) as total FROM tlog a $swhere_sum AND a.isselesai = 0");
	$sql_proses->execute();
	$totalMasihProses = $sql_proses->fetch(PDO::FETCH_ASSOC)['total'];

	$sql_terlambat = $conn->prepare("SELECT COUNT(*) as total FROM tlog a $swhere_sum AND a.isselesai = 0 AND a.tgltarget < CURDATE()");
	$sql_terlambat->execute();
	$totalTerlambat = $sql_terlambat->fetch(PDO::FETCH_ASSOC)['total'];
} catch (PDOException $e) {
	// Abaikan error
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
  //mencegah popup aktif 
    history.back();
}
//-->
</script>

<script type="text/javascript">
function validasi_input(form){
  // Validasi tanggal wajib diisi
  if (form.tgl.value == ""){
    alert("Tanggal awal belum diisi!");
    form.tgl.focus();
    return (false);
  }
  if (form.sdtgl.value == ""){
    alert("Tanggal akhir belum diisi!");
    form.sdtgl.focus();
    return (false);
  }
  return (true);
}
</script>

<body >
 
<div class="row"> 
    <ol class="breadcrumb">
	  <li><i class="fa fa-home"></i>LAPORAN LOG</li> 
	</ol>

	<!-- Summary Cards (di atas filter) - style dashboard -->
	<style>
		.log-stat-card {
			background: #fff;
			border-radius: 8px;
			padding: 16px 20px;
			box-shadow: 0 2px 8px rgba(0,0,0,0.08);
			display: flex;
			align-items: center;
			gap: 14px;
			flex: 1;
			min-width: 150px;
			cursor: pointer;
			transition: box-shadow 0.2s, transform 0.2s;
		}
		.log-stat-card:hover { box-shadow: 0 6px 18px rgba(0,0,0,0.14); transform: translateY(-2px); }
		.log-stat-card .ls-icon {
			width: 50px; height: 50px;
			border-radius: 10px;
			display: flex; align-items: center; justify-content: center;
			font-size: 22px; color: #fff; flex-shrink: 0;
		}
		.log-stat-card .ls-info h3 { margin: 0; font-size: 26px; font-weight: 700; color: #333; line-height: 1.1; }
		.log-stat-card .ls-info p  { margin: 3px 0 0; font-size: 12px; color: #888; }
	</style>
	<div style="display: flex; gap: 15px; padding: 0 0 15px 0; flex-wrap: wrap;">
		<div class="log-stat-card" onclick="filterTable('semua')">
			<div class="ls-icon" style="background: linear-gradient(135deg, #54a0ff, #2e86de);"><i class="fa fa-list-alt"></i></div>
			<div class="ls-info">
				<h3><?= $totalLog ?></h3>
				<p>Total Log</p>
			</div>
		</div>
		<div class="log-stat-card" onclick="filterTable('proses')">
			<div class="ls-icon" style="background: linear-gradient(135deg, #ffa502, #e67e22);"><i class="fa fa-spinner"></i></div>
			<div class="ls-info">
				<h3><?= $totalMasihProses ?></h3>
				<p>Masih Proses</p>
			</div>
		</div>
		<div class="log-stat-card" onclick="filterTable('terlambat')">
			<div class="ls-icon" style="background: linear-gradient(135deg, #ff6b6b, #ee5a24);"><i class="fa fa-warning"></i></div>
			<div class="ls-info">
				<h3><?= $totalTerlambat ?></h3>
				<p>Terlambat</p>
			</div>
		</div>
	</div>

	<section class="panel">
	  <header class="panel-heading">
		<form role="form"  method="GET" onSubmit="return validasi_input(this)"  action="index.php"> 
		
			<!-- Filter Tanggal -->
			<div class="form-group col-xs-12 col-sm-2 ">
				<label>Tanggal :  </label>
				<input name="tgl" id="dp1" type="text" value="<?php echo $tgl; ?>"  onKeyPress="if (event.keyCode==13) {dp1.focus()}" size="16" class="form-control"> 
			</div>

			<div class="form-group col-xs-12 col-sm-2 ">
				<label>Sampai Tanggal :  </label>
				<input name="sdtgl" id="dp2" type="text" value="<?php echo $sdtgl; ?>"  size="16" class="form-control"> 
			</div> 

			<!-- Filter Customer (opsional) -->
			<div class="form-group col-xs-12 col-sm-2">
				<label>Nama Customer</label>	
				<select name="kodcustomer" id="kodcustomer" class="form-control">
					<option value="">-- Semua Customer --</option>
				<?php
				// Mengambil daftar customer dari database  
					$qk = $conn->prepare("SELECT kodcustomer, nmcustomer FROM rcustomer ORDER BY nmcustomer"); 
					$qk->execute(); 
					while($rsk = $qk->fetch()){ 
					   if ($kodcustomer <> $rsk['kodcustomer']) {
					      echo "<option value=".$rsk['kodcustomer'].">".$rsk['nmcustomer']."</option>\n"; 
					  } else {
					      echo "<option value=".$rsk['kodcustomer']." SELECTED>".$rsk['nmcustomer']."</option>\n"; 
					  }	  
					}
				?>
				</select> 
			</div>

			<!-- Filter Kelompok Bisnis (opsional) -->
			<div class="form-group col-xs-12 col-sm-2 ">
				<label>Kelompok Bisnis</label>	
				<select name="jnsbisnis" id="jnsbisnis" class="form-control">
					<option value="" <?= $jnsbisnis == "" ? "selected" : ""; ?>>-- Semua Bisnis --</option>
					<option value="A" <?= $jnsbisnis == "A" ? "selected" : ""; ?>>A:Administration</option>
					<option value="B" <?= $jnsbisnis == "B" ? "selected" : ""; ?>>B:Business</option>
					<option value="D" <?= $jnsbisnis == "D" ? "selected" : ""; ?>>D:Developing</option>
					<option value="M" <?= $jnsbisnis == "M" ? "selected" : ""; ?>>M:Maintenance</option>
				</select> 
			</div> 

			<!-- Filter Dikerjakan Oleh -->
			<div class="form-group col-xs-12 col-sm-2">
				<label>Dikerjakan oleh :</label>
				<select name="filter_user" id="filter_user" class="form-control">
					<option value="">-- Semua User --</option>
				<?php
					$qu = $conn->prepare("SELECT iduser, nama FROM ruser WHERE stsaktif = 1 ORDER BY nik ASC, nama ASC"); 
					$qu->execute(); 
					while($ru = $qu->fetch()){ 
						$sel_user = ($filter_user == $ru['iduser']) ? 'selected' : '';
						echo "<option value='".htmlspecialchars($ru['iduser'])."' $sel_user>".htmlspecialchars($ru['nama'])."</option>\n"; 
					}
				?>
				</select> 
			</div>
				
		</div>
			<div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-6"> 
				<input type="hidden" name="par" id="par" value="04">
				
				<button type="submit" name="submit" class="btn btn-primary" value="Y">Submit</button>
				<button type="reset" class="btn btn-danger">Reset</button>
			</div> 
		</form>
		<div class="clearfix">   </div>
		   
		<h4><font color="red"><?php echo $pesan;?></font></h4>
	  </header>
	 <div align="center">
	   <?php 
	   // Tampilkan info customer jika dipilih
	   if ($kodcustomer <> "") { 
		 $sqlk = $conn->prepare("select * from rcustomer where kodcustomer= '$kodcustomer'");
		 $sqlk->execute();	
		 $rsk = $sqlk->fetch(); 
		 echo " LOG BOOK ".$rsk['nmcustomer'];
	   } else {
		 echo " LOG BOOK";
	   }
	   ?>
<br>
		<?php echo " Dari tanggal ". $tgl. "&nbsp;&nbsp;&nbsp; Sampai tanggal " .$sdtgl; ?>
	  </div>


	   <div class="box-body">
	   <table id="contoh" class="table table-bordered table-striped table-hover"> 
		<br>
		<div>  <thead> 
			<tr class="height: 5px;">
			  <th>No</th>
			  <th>Ticket</th>
			  <th>User</th>
			  <th>Mitra</th>
			  <th>Order By</th>
			  <th>Tgl Order</th> 
			  <th>Tgl Target</th>
			  <th>Prioritas</th>
			  <th>Order</th>
			  <th>Order Layanan</th>  
			  <th>Dikerjakan Oleh</th>
			  <th>Status</th>
			  <th>Tgl Selesai</th>
			  <th>Aksi</th>
			
			</tr>
		  </thead>
		  <tbody>
		  <div id="show-product">
          <!-- data akan di tampilkan di sini -->
          </div>
		  <?php
			if($_GET['']!==""){
			    try {
					// Bangun kondisi WHERE secara dinamis berdasarkan filter
					$swhere = " WHERE (a.tglorder BETWEEN '$tgl' AND '$sdtgl') and a.stsdel=0";
					
					// Tambah filter customer jika dipilih
					if ($kodcustomer != "") {
						$swhere .= " AND a.kodcustomer = '$kodcustomer'";
					}
					// Tambah filter kelompok bisnis jika dipilih
					if ($jnsbisnis != "") {
						$swhere .= " AND a.jnsbisnis = '$jnsbisnis'";
					}
					// Tambah filter dikerjakan oleh
					if ($filter_user != "") {
						$swhere .= " AND a.iduser = '$filter_user'";
					}
					// Filter sprint
					if ($filter_idsprint != "") {
						$swhere .= " AND a.idsprint = $filter_idsprint";
					}

			        $strsql = "SELECT a.userorder,a.idlog,a.iduser, b.nmcustomer, a.tglorder,a.fasorder,a.isselesai, a.desorder, a.deslayan, a.tglselesai, a.tgltarget, a.prioritas,
							COALESCE(u.nama, a.iduser) AS nama_dikerjakan
							from tlog a inner join rcustomer b  on a.kodcustomer =b.kodcustomer 
							LEFT JOIN ruser u ON a.iduser = u.iduser
							{$swhere}
							order by a.tglorder, a.idlog";	
			        $sql = $conn->prepare($strsql);
					
					
					$sql->execute();
					// tampilkan
					$no=1;
					while($rs = $sql->fetch()) { 
					   $status="Open"; 
					   $statusClass = "label-warning";
					   if ($rs['isselesai']==1){
					     $status ="Close";
					     $statusClass = "label-success";
					   }

					   // Cek terlambat
					   $terlambatFlag = "";
					   $terlambatText = "";
					   if ($rs['isselesai'] == 0 && !empty($rs['tgltarget']) && $rs['tgltarget'] < date('Y-m-d')) {
					     $terlambatFlag = " <span class='label label-danger' style='font-size:9px;'>Terlambat</span>";
					     $terlambatText = "Ya";
					   }

					   // Konversi kode prioritas ke teks
					   $prioritas="";
					   switch ($rs['prioritas']) {
						   case 1:
							   $prioritas="Sangat Tinggi";
							   break;
						   case 2:
							   $prioritas="Tinggi";
							   break;
						   case 3:
							   $prioritas="Biasa";
							   break;
						   default:
							   $prioritas="";
							   break;
					   }

					   // Escape data untuk JavaScript
					   $js_ticket = htmlspecialchars($rs['idlog'].' | '.$rs['userorder'], ENT_QUOTES);
					   $js_user = htmlspecialchars($rs['iduser'], ENT_QUOTES);
					   $js_mitra = htmlspecialchars($rs['nmcustomer'], ENT_QUOTES);
					   $js_orderby = htmlspecialchars($rs['fasorder'], ENT_QUOTES);
					   $js_tglorder = htmlspecialchars($rs['tglorder'], ENT_QUOTES);
					   $js_tgltarget = htmlspecialchars($rs['tgltarget'] ?? '-', ENT_QUOTES);
					   $js_prioritas = htmlspecialchars($prioritas, ENT_QUOTES);
					   $js_order = htmlspecialchars($rs['desorder'] ?? '', ENT_QUOTES);
					   $js_layanan = htmlspecialchars($rs['deslayan'] ?? '', ENT_QUOTES);
					   $js_dikerjakan = htmlspecialchars($rs['nama_dikerjakan'], ENT_QUOTES);
					   $js_status = htmlspecialchars($status, ENT_QUOTES);
					   $js_tglselesai = htmlspecialchars($rs['tglselesai'] ?? '-', ENT_QUOTES);
					   $js_terlambat = htmlspecialchars($terlambatText, ENT_QUOTES);

					   echo "   <tr>
						   <td align=center><font size=-1>".$no."</font></td>
						   <td><font size=-1>".$rs['idlog']." | ".$rs['userorder']."</font></td>
						  <td><font size=-1>".$rs['iduser']."</font></td>
						  <td><font size=-1>".$rs['nmcustomer']."</font></td>
						  <td><font size=-1>".$rs['fasorder']."</font></td>
						  <td><font size=-1>".$rs['tglorder']."</font></td>
						  <td><font size=-1>".$rs['tgltarget']."</font></td>
						  <td><font size=-1>".$prioritas."</font></td>
						  <td><font size=-1>".renderLogColumn($rs['desorder'], 'desorder', $rs['idlog'], 'Uraian Order')."</font></td>
						  <td><font size=-1>".renderLogColumn($rs['deslayan'], 'deslayan', $rs['idlog'], 'Aktivitas Layanan')."</font></td>
						  <td><font size=-1>".$rs['nama_dikerjakan']."</font></td>
						  <td align=center><span class='label ".$statusClass."'>".$status."</span>".$terlambatFlag."</td>
						  <td><font size=-1>".$rs['tglselesai']."</font></td>
						  <td align=center>
						    <button type='button' class='btn btn-info btn-xs' onclick=\"showDetail('$js_ticket','$js_user','$js_mitra','$js_orderby','$js_tglorder','$js_tgltarget','$js_prioritas','$js_dikerjakan','$js_status','$js_tglselesai','$js_terlambat',{$rs['idlog']})\">
						      <i class='fa fa-search'></i> Detail
						    </button>
						  </td>
						</tr>
						<div id='ord_{$rs['idlog']}' style='display:none;'>".stripslashes($rs['desorder'] ?? '')."</div>
						<div id='lay_{$rs['idlog']}' style='display:none;'>".stripslashes($rs['deslayan'] ?? '')."</div>";
						$no++;	
					} 
			   }//try
			   catch (PDOException $e)	{
				  echo "  
				   <tr>
					  <td colspan='14' align='center'>Tidak ada data</td>
					</tr>  ";
			 
			  }//catch 
			}
		  ?> 
			
		  </tbody>
		</table>
	  </div>
	  </section>
	</section>
   
</div> 

<?= renderLogModal() ?>

<!-- Modal Detail Log -->
<div class="modal fade" id="modalDetailLog" tabindex="-1" role="dialog">
  <div class="modal-dialog" style="width:90%; max-width:700px;">
    <div class="modal-content">
      <div class="modal-header" style="background: #3498db; color: #fff;">
        <button type="button" class="close" data-dismiss="modal" style="color:#fff; opacity:0.8;">&times;</button>
        <h4 class="modal-title"><i class="fa fa-file-text-o"></i> Detail Log: <span id="detail_ticket_header"></span></h4>
      </div>
      <div class="modal-body">
        <table class="table table-bordered" style="margin-bottom: 0;">
          <tr>
            <td style="width:35%; font-weight:bold; background:#f5f5f5;"><i class="fa fa-tag"></i> Ticket</td>
            <td id="detail_ticket"></td>
          </tr>
          <tr>
            <td style="font-weight:bold; background:#f5f5f5;"><i class="fa fa-user"></i> User</td>
            <td id="detail_user"></td>
          </tr>
          <tr>
            <td style="font-weight:bold; background:#f5f5f5;"><i class="fa fa-building"></i> Mitra</td>
            <td id="detail_mitra"></td>
          </tr>
          <tr>
            <td style="font-weight:bold; background:#f5f5f5;"><i class="fa fa-user-circle"></i> Order By</td>
            <td id="detail_orderby"></td>
          </tr>
          <tr>
            <td style="font-weight:bold; background:#f5f5f5;"><i class="fa fa-calendar"></i> Tgl Order</td>
            <td id="detail_tglorder"></td>
          </tr>
          <tr>
            <td style="font-weight:bold; background:#f5f5f5;"><i class="fa fa-calendar-check-o"></i> Tgl Target</td>
            <td id="detail_tgltarget"></td>
          </tr>
          <tr>
            <td style="font-weight:bold; background:#f5f5f5;"><i class="fa fa-exclamation-triangle"></i> Prioritas</td>
            <td id="detail_prioritas"></td>
          </tr>
          <tr>
            <td style="font-weight:bold; background:#f5f5f5;"><i class="fa fa-clipboard"></i> Order</td>
            <td id="detail_order" style="max-height:200px; overflow-y:auto; word-break:break-word;"></td>
          </tr>
          <tr>
            <td style="font-weight:bold; background:#f5f5f5;"><i class="fa fa-wrench"></i> Order Layanan</td>
            <td id="detail_layanan" style="max-height:200px; overflow-y:auto; word-break:break-word;"></td>
          </tr>
          <tr>
            <td style="font-weight:bold; background:#f5f5f5;"><i class="fa fa-users"></i> Dikerjakan Oleh</td>
            <td id="detail_dikerjakan"></td>
          </tr>
          <tr>
            <td style="font-weight:bold; background:#f5f5f5;"><i class="fa fa-info-circle"></i> Status</td>
            <td id="detail_status"></td>
          </tr>
          <tr id="row_terlambat" style="display:none;">
            <td style="font-weight:bold; background:#fff0f0;"><i class="fa fa-warning" style="color:#e74c3c;"></i> Terlambat</td>
            <td id="detail_terlambat" style="color:#e74c3c; font-weight:bold;"></td>
          </tr>
          <tr>
            <td style="font-weight:bold; background:#f5f5f5;"><i class="fa fa-calendar-o"></i> Tgl Selesai</td>
            <td id="detail_tglselesai"></td>
          </tr>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<style>
#detail_order img, #detail_layanan img {
    max-width: 100%;
    max-height: 180px;
    height: auto;
    border-radius: 4px;
    border: 1px solid #ddd;
    margin: 4px 0;
    display: block;
}
#detail_order p, #detail_layanan p { margin: 2px 0; }
</style>
<script>
function showDetail(ticket, user, mitra, orderby, tglorder, tgltarget, prioritas, dikerjakan, status, tglselesai, terlambat, idlog) {
    document.getElementById('detail_ticket_header').innerText = ticket;
    document.getElementById('detail_ticket').innerText = ticket;
    document.getElementById('detail_user').innerText = user;
    document.getElementById('detail_mitra').innerText = mitra;
    document.getElementById('detail_orderby').innerText = orderby;
    document.getElementById('detail_tglorder').innerText = tglorder;
    document.getElementById('detail_tgltarget').innerText = tgltarget;
    document.getElementById('detail_prioritas').innerText = prioritas;

    // Baca konten HTML dari hidden div berdasarkan idlog
    var ordEl = document.getElementById('ord_' + idlog);
    var layEl = document.getElementById('lay_' + idlog);
    document.getElementById('detail_order').innerHTML = ordEl ? ordEl.innerHTML : '';
    document.getElementById('detail_layanan').innerHTML = layEl ? layEl.innerHTML : '';

    document.getElementById('detail_dikerjakan').innerText = dikerjakan;
    
    // Status dengan badge
    var statusColor = (status === 'Close') ? 'success' : 'warning';
    document.getElementById('detail_status').innerHTML = "<span class='label label-" + statusColor + "'>" + status + "</span>";
    
    // Terlambat row
    if (terlambat && terlambat !== '') {
        document.getElementById('row_terlambat').style.display = '';
        document.getElementById('detail_terlambat').innerHTML = "<span class='label label-danger'>Ya - Melewati Target</span>";
    } else {
        document.getElementById('row_terlambat').style.display = 'none';
    }
    
    document.getElementById('detail_tglselesai').innerText = tglselesai;
    
    $('#modalDetailLog').modal('show');
}

// Fungsi filter tabel berdasarkan klik kartu summary
function filterTable(tipe) {
    var table = $('#contoh').DataTable();
    
    if (tipe === 'semua') {
        // Reset semua filter - tampilkan semua
        table.search('').columns().search('').draw();
    } else if (tipe === 'proses') {
        // Filter hanya yang Open
        table.search('').columns().search('').draw();
        table.column(11).search('Open').draw();
    } else if (tipe === 'terlambat') {
        // Filter yang Terlambat
        table.search('').columns().search('').draw();
        table.search('Terlambat').draw();
    }
}
</script>

</body>

</html>
