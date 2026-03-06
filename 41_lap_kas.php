<?php
// 
include "dbase.php";

// Auto-create/migrate tabel saldo awal
try {
    // Cek apakah tabel lama (dengan kolom bulan) masih ada, jika ya drop dan buat ulang
    $cekKolom = $conn->query("SHOW COLUMNS FROM tkas_saldo_awal LIKE 'bulan'");
    if ($cekKolom && $cekKolom->rowCount() > 0) {
        $conn->exec("DROP TABLE tkas_saldo_awal");
    }
} catch (PDOException $e) {}
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS `tkas_saldo_awal` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `saldo_awal` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `keterangan` VARCHAR(255) NULL,
        `iduser_input` VARCHAR(20) NOT NULL,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
} catch (PDOException $e) {}

// AJAX Handler untuk simpan saldo awal
if (isset($_POST['ajax_saldo_awal'])) {
    session_start();
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['DEFAULT_IDUSER'])) {
        echo json_encode(['success' => false, 'message' => 'Session tidak valid']);
        exit;
    }
    
    $saldo = floatval(str_replace(['.', ','], ['', '.'], $_POST['saldo_awal']));
    $ket = trim($_POST['keterangan'] ?? '');
    $iduser = $_SESSION['DEFAULT_IDUSER'];
    
    try {
        $cek = $conn->query("SELECT id FROM tkas_saldo_awal LIMIT 1");
        
        if ($cek->rowCount() > 0) {
            $sql = $conn->prepare("UPDATE tkas_saldo_awal SET saldo_awal = ?, keterangan = ?, iduser_input = ? WHERE id = (SELECT min_id FROM (SELECT MIN(id) AS min_id FROM tkas_saldo_awal) tmp)");
            $sql->execute([$saldo, $ket, $iduser]);
        } else {
            $sql = $conn->prepare("INSERT INTO tkas_saldo_awal (saldo_awal, keterangan, iduser_input) VALUES (?, ?, ?)");
            $sql->execute([$saldo, $ket, $iduser]);
        }
        echo json_encode(['success' => true, 'message' => 'Saldo awal berhasil disimpan']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// AJAX Handler untuk update approval (sebelum islogin.php agar tidak terganggu redirect)
if (isset($_POST['ajax_approve'])) {
    session_start();
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['DEFAULT_IDUSER'])) {
        echo json_encode(['success' => false, 'message' => 'Session tidak valid']);
        exit;
    }
    
    $idkas = (int) $_POST['idkas'];
    $sts_approve = $_POST['sts_approve'];
    $keterangan = trim($_POST['keterangan'] ?? '');
    
    try {
        $sql = $conn->prepare("UPDATE tkas SET sts_approve = :sts, keterangan = :ket WHERE idkas = :id");
        $sql->execute([':sts' => $sts_approve, ':ket' => $keterangan, ':id' => $idkas]);
        echo json_encode(['success' => true, 'message' => 'Berhasil diupdate']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

include "islogin.php";
$pesan = "";
$iduser   = $_SESSION['DEFAULT_IDUSER'];
$kodjab   = isset($_SESSION['DEFAULT_KODJAB']) ? $_SESSION['DEFAULT_KODJAB'] : 0;

if (isset($_GET['submit'])) {
    $tgl   = isset($_GET['tgl']) ? trim($_GET['tgl']) : date('Y-m-01');
    $sdtgl = isset($_GET['sdtgl']) ? trim($_GET['sdtgl']) : date('Y-m-d');
} else {
    $tgl   = date('Y-m-01');
    $sdtgl = date('Y-m-d');
}

$filter_akun      = isset($_GET['filter_akun']) ? trim($_GET['filter_akun']) : '';
$filter_plusmin   = isset($_GET['filter_plusmin']) ? trim($_GET['filter_plusmin']) : '';
$filter_diproses  = isset($_GET['filter_diproses']) ? trim($_GET['filter_diproses']) : '';
$filter_approve   = isset($_GET['filter_approve']) ? trim($_GET['filter_approve']) : '';
if (isset($_POST['update_approve']) && $kodjab == 1) {
    $idkas = (int) $_POST['idkas'];
    $sts_approve = $_POST['sts_approve'];
    $keterangan = trim($_POST['keterangan']);
    
    try {
        $sql = $conn->prepare("UPDATE tkas SET sts_approve = :sts, keterangan = :ket WHERE idkas = :id");
        $sql->execute([':sts' => $sts_approve, ':ket' => $keterangan, ':id' => $idkas]);
        $pesan = "<font color=blue>Data berhasil diupdate</font>";
    } catch (PDOException $e) {
        $pesan = "<font color=red>Error update: " . $e->getMessage() . "</font>";
    }
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

<style>
.plus-text { color: green; font-weight: bold; }
.min-text { color: red; font-weight: bold; }
.inline-form { display: inline; }
</style>

<body>
 
<div class="row"> 
    <ol class="breadcrumb">
	  <li><i class="fa fa-home"></i> LAPORAN ARUS KAS</li> 
	</ol> 
	<section class="panel">
	  <header class="panel-heading">
		  
		  <form role="form" method="GET" action="index.php"> 
		      
		      <!-- BARIS 1: Filter dropdown -->
		      <div class="form-group col-xs-12 col-sm-3">
				  <label>Akun</label>	 
				   <select name="filter_akun" id="filter_akun" class="form-control">
					<option value="">-- Semua Akun --</option>
					<?php
                        $qa_filter = $conn->prepare("SELECT kodakun, nmakun FROM takun WHERE tipe = 'D' ORDER BY kodakun"); 
                        $qa_filter->execute(); 
                        while($ra_filter = $qa_filter->fetch()){ 
                            $selected = ($filter_akun == $ra_filter['kodakun']) ? 'selected' : '';
                            echo "<option value='".htmlspecialchars($ra_filter['kodakun'])."' $selected>".$ra_filter['kodakun']." - ".htmlspecialchars($ra_filter['nmakun'])."</option>\n";
                        }
                    ?>
					</select> 
			 </div>	
		      
		      <div class="form-group col-xs-12 col-sm-2">
				  <label>+/-</label>	 
				   <select name="filter_plusmin" id="filter_plusmin" class="form-control">
					<option value="">-- Semua --</option>
					<option value="+" <?php echo ($filter_plusmin == '+') ? 'selected' : ''; ?>>+ Masuk</option>
                    <option value="-" <?php echo ($filter_plusmin == '-') ? 'selected' : ''; ?>>- Keluar</option>
					</select> 
			 </div>
			 
			 <div class="form-group col-xs-12 col-sm-3">
				  <label>Diproses Oleh</label>	 
				   <select name="filter_diproses" id="filter_diproses" class="form-control">
					<option value="">-- Semua User --</option>
					<?php
                        $qu_filter = $conn->prepare("SELECT iduser, nama FROM ruser WHERE stsaktif = 1 ORDER BY nik ASC, nama ASC"); 
                        $qu_filter->execute(); 
                        while($ru_filter = $qu_filter->fetch()){ 
                            $selected = ($filter_diproses == $ru_filter['iduser']) ? 'selected' : '';
                            echo "<option value='".htmlspecialchars($ru_filter['iduser'])."' $selected>".htmlspecialchars($ru_filter['nama'])."</option>\n";
                        }
                    ?>
					</select> 
			 </div>

			 <div class="form-group col-xs-12 col-sm-2">
				  <label>Status Approval</label>	 
				   <select name="filter_approve" id="filter_approve" class="form-control">
					<option value="">-- Semua --</option>
					<option value="B" <?php echo ($filter_approve == 'B') ? 'selected' : ''; ?>>Belum</option>
                    <option value="Y" <?php echo ($filter_approve == 'Y') ? 'selected' : ''; ?>>Disetujui</option>
					</select> 
			 </div>

			 <div class="clearfix"></div>
			 
			 <!-- BARIS 2: Tanggal dan Tombol -->
		  <div class="form-group col-xs-12 col-sm-2">
			<label>Tanggal :</label>
			<input name="tgl" id="dp1" type="text" value="<?php echo $tgl?>" size="16" class="form-control"> 
		  </div>
		
		   <div class="form-group col-xs-12 col-sm-2">
			<label>Sampai Tanggal :</label>
			<input name="sdtgl" id="dp2" type="text" value="<?php echo $sdtgl?>" size="16" class="form-control"> 
		  </div> 
			
			  <div class="form-group col-xs-12 col-sm-4"> 
				 <input type="hidden" name="par" id="par" value="41">
				 <label>&nbsp;</label><br>
				 <button type="submit" name="submit" class="btn btn-primary" value="Y">Submit</button>
				 <button type="reset" class="btn btn-danger">Reset</button>
			   </div> 
		  </form>
		  <div class="clearfix"></div>
		   
		  <h4><font color="red"><?php echo $pesan;?></font></h4>
	  </header>
	  <section class="content">
	  <div align="center">
	     
	     LAPORAN ARUS KAS
		<br>
		<?php echo " Dari tanggal ". $tgl. "&nbsp;&nbsp;&nbsp; Sampai tanggal " .$sdtgl; ?>
	  </div>
	  <?php
	  // Ambil saldo awal manual (1 record saja)
	  $saldoAwalManual = 0;
	  $saldoAwalKet = '';
	  try {
	      $qSaldo = $conn->query("SELECT saldo_awal, keterangan FROM tkas_saldo_awal LIMIT 1");
	      $rSaldo = $qSaldo->fetch(PDO::FETCH_ASSOC);
	      if ($rSaldo) {
	          $saldoAwalManual = $rSaldo['saldo_awal'];
	          $saldoAwalKet = $rSaldo['keterangan'];
	      }
	  } catch (PDOException $e) {}

	  // Hitung transaksi sebelum tanggal filter mulai
	  $transaksiSebelumnya = 0;
	  try {
	      $qPrev = $conn->prepare("SELECT 
	          COALESCE(SUM(CASE WHEN plusmin = '+' THEN totalharga ELSE 0 END), 0)
	        - COALESCE(SUM(CASE WHEN plusmin = '-' THEN totalharga ELSE 0 END), 0) AS net
	        FROM tkas WHERE tgltransaksi < ?");
	      $qPrev->execute([$tgl]);
	      $transaksiSebelumnya = $qPrev->fetchColumn();
	  } catch (PDOException $e) {}

	  // Saldo awal efektif = manual + transaksi sebelum filter
	  $saldoAwal = $saldoAwalManual + $transaksiSebelumnya;
	  ?>

	  <div style="background:#d9edf7; border:1px solid #bce8f1; border-radius:4px; padding:10px 15px; margin:10px 0;">
	      <div style="display:flex; align-items:center; gap:15px; flex-wrap:wrap;">
	          <strong>Saldo Awal Perusahaan :</strong>
	          <div>
	              <input type="text" id="input_saldo_awal" value="<?php echo number_format($saldoAwalManual, 0, ',', '.'); ?>" 
	                     style="width:160px; text-align:right; font-weight:bold; padding:4px 8px;" 
	                     class="form-control input-sm" placeholder="0"
	                     oninput="var v=this.value.replace(/[^0-9]/g,'');var f='';for(var i=0;i<v.length;i++){if(i>0&&(v.length-i)%3===0)f+='.';f+=v[i];}this.value=f;">
	          </div>
	          <div>
	              <input type="text" id="input_saldo_ket" value="<?php echo htmlspecialchars($saldoAwalKet); ?>" 
	                     style="width:150px; padding:4px 8px;" 
	                     class="form-control input-sm" placeholder="Ket...">
	          </div>
	          <div>
	              <button type="button" id="btn_simpan_saldo" class="btn btn-info btn-sm" onclick="simpanSaldoAwal(this)">Simpan</button>
	          </div>
	      </div>
	      <div style="margin-top:8px; font-size:12px; color:#555;">
	          <span>Saldo awal perusahaan: <strong>Rp <?php echo number_format($saldoAwalManual, 0, ',', '.'); ?></strong></span>
	          <?php if ($transaksiSebelumnya != 0) { ?>
	          &nbsp;|&nbsp; Transaksi sebelum <?php echo date('d/m/Y', strtotime($tgl)); ?>: <strong>Rp <?php echo number_format($transaksiSebelumnya, 0, ',', '.'); ?></strong>
	          <?php } ?>
	          &nbsp;|&nbsp; <strong style="color:#31708f;">Saldo awal laporan: Rp <?php echo number_format($saldoAwal, 0, ',', '.'); ?></strong>
	      </div>
	  </div>

	  <script>
	  function simpanSaldoAwal(btn) {
	      var saldoVal = document.getElementById('input_saldo_awal').value;
	      var ketVal = document.getElementById('input_saldo_ket').value;
	      btn.disabled = true;
	      btn.textContent = 'Saving...';

	      var xhr = new XMLHttpRequest();
	      xhr.open('POST', '41_lap_kas.php', true);
	      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
	      xhr.onreadystatechange = function() {
	          if (xhr.readyState === 4) {
	              if (xhr.status === 200) {
	                  try {
	                      var resp = JSON.parse(xhr.responseText);
	                      if (resp.success) {
	                          btn.className = 'btn btn-success btn-sm';
	                          btn.textContent = 'Tersimpan!';
	                          setTimeout(function() { location.reload(); }, 800);
	                      } else {
	                          alert('Gagal: ' + resp.message);
	                          btn.disabled = false;
	                          btn.textContent = 'Simpan';
	                      }
	                  } catch(e) {
	                      alert('Error parsing response');
	                      btn.disabled = false;
	                      btn.textContent = 'Simpan';
	                  }
	              } else {
	                  alert('Gagal menyimpan saldo awal');
	                  btn.disabled = false;
	                  btn.textContent = 'Simpan';
	              }
	          }
	      };
	      xhr.send('ajax_saldo_awal=1&saldo_awal=' + encodeURIComponent(saldoVal) + '&keterangan=' + encodeURIComponent(ketVal));
	  }
	  </script>


	   <div class="box-body">
	   <table id="tabel_lap_kas" class="table table-bordered table-striped table-hover"> 
	
		<div>  <thead> 
			<tr class="height: 5px;">
			 
			 
			 <th>#</th>
			  <th>No Transaksi</th>
			  <th>Tanggal</th>
			  <th>Kode Akun</th>
			  <th>Nama Akun</th>
			  <th>Deskripsi</th>
			  <th>Satuan</th>
			  <th>+/-</th>
			  <th>Jumlah</th>
			  <th>Harga Unit</th>
			  <th>Total Harga</th>
			  <th>Saldo</th>
			  <th>Diproses</th>
			  <th>Setujui GM</th>
			  <th>Keterangan</th>
			 
			</tr>
		  </thead>
		  <tbody>
		  <div id="show-product">
          </div>
		<?php
			// Selalu tampilkan data (tidak perlu klik Submit dulu)
			    try {
                    // 1. Siapkan parameter
                    $params = [
                        ':tgl' => $tgl, 
                        ':sdtgl' => $sdtgl
                    ];
                    
                    // 2. Query dasar
                    $strsql = "SELECT 
                            k.idkas, k.notransaksi, k.tgltransaksi, k.kodakun, k.deskripsi, 
                            k.satuan, k.plusmin, k.jumlah, k.hargaunit, k.totalharga, 
                            k.iduser_proses, k.sts_approve, k.keterangan,
                            a.nmakun,
                            u.nama AS nama_proses
                            FROM tkas k 
                            LEFT JOIN takun a ON k.kodakun = a.kodakun 
                            LEFT JOIN ruser u ON k.iduser_proses = u.iduser
                            WHERE (k.tgltransaksi >= :tgl AND k.tgltransaksi <= :sdtgl) ";
                    
                    // 3. Tambahkan filter dinamis
                    if (!empty($filter_akun)) {
                        $strsql .= " AND k.kodakun = :filter_akun ";
                        $params[':filter_akun'] = $filter_akun;
                    }
                    if (!empty($filter_plusmin)) {
                        $strsql .= " AND k.plusmin = :filter_plusmin ";
                        $params[':filter_plusmin'] = $filter_plusmin;
                    }
                    if (!empty($filter_diproses)) {
                        $strsql .= " AND k.iduser_proses = :filter_diproses ";
                        $params[':filter_diproses'] = $filter_diproses;
                    }
                    if (!empty($filter_approve)) {
                        $strsql .= " AND k.sts_approve = :filter_approve ";
                        $params[':filter_approve'] = $filter_approve;
                    }
                    
                    $strsql .= " ORDER BY k.tgltransaksi, k.notransaksi, k.idkas";
                    
                    // 4. Eksekusi query
                    $sql = $conn->prepare($strsql);
                    $sql->execute($params);
                    
                    $no = 1;
                    $totalMasuk = 0;
                    $totalKeluar = 0;
                    $saldo = $saldoAwal; // Mulai dari saldo awal

                    // Tampilkan baris saldo awal di tabel
                    echo "<tr style='background:#d9edf7; font-weight:bold;'>
                        <td align='center'></td>
                        <td colspan='5'>Saldo Awal Laporan</td>
                        <td colspan='5'></td>
                        <td align='right'>".number_format($saldoAwal, 0, ',', '.')."</td>
                        <td></td>
                        <td></td>
                        <td>".htmlspecialchars($saldoAwalKet)."</td>
                    </tr>";
                    
                    while ($rs = $sql->fetch(PDO::FETCH_ASSOC)) {
                        $plusminClass = ($rs['plusmin'] == '+') ? 'plus-text' : 'min-text';
                        
                        if ($rs['plusmin'] == '+') {
                            $totalMasuk += $rs['totalharga'];
                            $saldo += $rs['totalharga'];
                        } else {
                            $totalKeluar += $rs['totalharga'];
                            $saldo -= $rs['totalharga'];
                        }

                        echo "   <tr>
                           <td align=center><font size=-1>".$no."</font></td>
                          <td><font size=-1>".$rs['notransaksi']."</font></td>
                          <td><font size=-1>".date('d/m/Y', strtotime($rs['tgltransaksi']))."</font></td>
                          <td><font size=-1>".$rs['kodakun']."</font></td>
                          <td><font size=-1>".$rs['nmakun']."</font></td>
                          <td><font size=-1>".$rs['deskripsi']."</font></td>
                          <td><font size=-1>".$rs['satuan']."</font></td>
                          <td class='".$plusminClass."'>".$rs['plusmin']."</td>
                          <td align=right><font size=-1>".number_format($rs['jumlah'], 0, ',', '.')."</font></td>
                          <td align=right><font size=-1>".number_format($rs['hargaunit'], 0, ',', '.')."</font></td>
                          <td align=right><font size=-1>".number_format($rs['totalharga'], 0, ',', '.')."</font></td>
                          <td align=right><font size=-1>".number_format($saldo, 0, ',', '.')."</font></td>
                          <td><font size=-1>".$rs['nama_proses']."</font></td>";
                        
                        // Kolom Setujui GM (editable jika kodjab = 1)
                        if ($kodjab == 1) {
                            echo "<td>";
                            echo "<select class='form-control input-sm approve-select' data-idkas='" . $rs['idkas'] . "' style='width:110px;'>";
                            echo "<option value='B'" . ($rs['sts_approve'] == 'B' ? ' selected' : '') . ">Belum</option>";
                            echo "<option value='Y'" . ($rs['sts_approve'] == 'Y' ? ' selected' : '') . ">Disetujui</option>";
                            echo "</select>";
                            echo "</td>";
                        } else {
                            $statusText = ($rs['sts_approve'] == 'Y') ? "<span style='color:green;'>Disetujui</span>" : "Belum";
                            echo "<td><font size=-1>" . $statusText . "</font></td>";
                        }
                        
                        // Kolom Keterangan (editable jika kodjab = 1)
                        if ($kodjab == 1) {
                            echo "<td>";
                            echo "<input type='text' class='form-control input-sm ket-input' data-idkas='" . $rs['idkas'] . "' value='" . htmlspecialchars($rs['keterangan']) . "' style='width:130px;' placeholder='Ket...'>";
                            echo "</td>";
                        } else {
                            echo "<td><font size=-1>" . htmlspecialchars($rs['keterangan']) . "</font></td>";
                        }
                        
                        echo "</tr>";
                        $no++;
                    }
                    
                    
                } catch (PDOException $e) {
                    echo "<tr><td colspan='14' class='text-center'>Terjadi error: " . $e->getMessage() . "</td></tr>";
                }
		  ?> 
			
	  </tbody>
		</table>
	  </div>
	  </section>
	</section>
   
</div> 

<script>
window.addEventListener('load', function () {
    const tableId = '#tabel_lap_kas';

    if ($.fn.DataTable.isDataTable(tableId)) {
        $(tableId).DataTable().destroy();
    }

    $(tableId).DataTable({
        pageLength: 10,
        lengthChange: true,
        searching: true,
        ordering: true,
        info: true,
        autoWidth: false,
        language: {
            search: "Search:",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            infoEmpty: "No data",
            zeroRecords: "Data not found",
            paginate: {
                next: ">",
                previous: "<"
            }
        }
    });

    // AJAX: Update approval status tanpa refresh
    $(document).on('change', '.approve-select', function() {
        var el = $(this);
        var idkas = el.data('idkas');
        var stsApprove = el.val();
        // Ambil keterangan dari baris yang sama
        var ketInput = el.closest('tr').find('.ket-input');
        var keterangan = ketInput.length ? ketInput.val() : '';

        $.ajax({
            url: '41_lap_kas.php',
            method: 'POST',
            data: {
                ajax_approve: 1,
                idkas: idkas,
                sts_approve: stsApprove,
                keterangan: keterangan
            },
            dataType: 'json',
            success: function(resp) {
                if (resp.success) {
                    // Feedback visual
                    el.css('border-color', 'green');
                    setTimeout(function() { el.css('border-color', ''); }, 1500);
                } else {
                    alert('Gagal: ' + resp.message);
                }
            },
            error: function() {
                alert('Gagal menyimpan perubahan');
            }
        });
    });

    // AJAX: Update keterangan tanpa refresh
    $(document).on('change', '.ket-input', function() {
        var el = $(this);
        var idkas = el.data('idkas');
        var keterangan = el.val();
        // Ambil status approve dari baris yang sama
        var approveSelect = el.closest('tr').find('.approve-select');
        var stsApprove = approveSelect.length ? approveSelect.val() : 'B';

        $.ajax({
            url: '41_lap_kas.php',
            method: 'POST',
            data: {
                ajax_approve: 1,
                idkas: idkas,
                sts_approve: stsApprove,
                keterangan: keterangan
            },
            dataType: 'json',
            success: function(resp) {
                if (resp.success) {
                    el.css('border-color', 'green');
                    setTimeout(function() { el.css('border-color', ''); }, 1500);
                } else {
                    alert('Gagal: ' + resp.message);
                }
            },
            error: function() {
                alert('Gagal menyimpan keterangan');
            }
        });
    });

});
</script>

</body>
</html>
