<?php
// 
include "dbase.php";
include "islogin.php";

$pesan = "";
$iduser = $_SESSION['DEFAULT_IDUSER'];
$kodjab = isset($_SESSION['DEFAULT_KODJAB']) ? $_SESSION['DEFAULT_KODJAB'] : 0;

// Auto-create table if not exists
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS `tkas_saldo_awal` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `bulan` INT(2) NOT NULL,
        `tahun` INT(4) NOT NULL,
        `saldo_awal` DECIMAL(15,2) NOT NULL DEFAULT 0,
        `keterangan` VARCHAR(255) NULL,
        `iduser_input` VARCHAR(20) NOT NULL,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `unik_bulan_tahun` (`bulan`, `tahun`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
} catch (PDOException $e) {
    // Table already exists
}

// Nama bulan Indonesia
$namaBulan = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

// PROSES SIMPAN / UPDATE
if (isset($_POST['simpan_saldo'])) {
    $bulan = (int) $_POST['bulan'];
    $tahun = (int) $_POST['tahun'];
    $saldo = floatval(str_replace(['.', ','], ['', '.'], $_POST['saldo_awal']));
    $keterangan = trim($_POST['keterangan'] ?? '');
    
    if ($bulan < 1 || $bulan > 12 || $tahun < 2000) {
        $pesan = "<font color=red>Bulan atau Tahun tidak valid!</font>";
    } else {
        try {
            // Cek apakah sudah ada data untuk bulan/tahun ini
            $cek = $conn->prepare("SELECT id FROM tkas_saldo_awal WHERE bulan = ? AND tahun = ?");
            $cek->execute([$bulan, $tahun]);
            
            if ($cek->rowCount() > 0) {
                // Update
                $sql = $conn->prepare("UPDATE tkas_saldo_awal SET saldo_awal = ?, keterangan = ?, iduser_input = ? WHERE bulan = ? AND tahun = ?");
                $sql->execute([$saldo, $keterangan, $iduser, $bulan, $tahun]);
                $pesan = "<font color=blue>Saldo awal " . $namaBulan[$bulan] . " $tahun berhasil diupdate!</font>";
            } else {
                // Insert
                $sql = $conn->prepare("INSERT INTO tkas_saldo_awal (bulan, tahun, saldo_awal, keterangan, iduser_input) VALUES (?, ?, ?, ?, ?)");
                $sql->execute([$bulan, $tahun, $saldo, $keterangan, $iduser]);
                $pesan = "<font color=blue>Saldo awal " . $namaBulan[$bulan] . " $tahun berhasil disimpan!</font>";
            }
        } catch (PDOException $e) {
            $pesan = "<font color=red>Error: " . $e->getMessage() . "</font>";
        }
    }
}

// PROSES HAPUS
if (isset($_GET['hapus'])) {
    $hapusId = (int) $_GET['hapus'];
    try {
        $sql = $conn->prepare("DELETE FROM tkas_saldo_awal WHERE id = ?");
        $sql->execute([$hapusId]);
        $pesan = "<font color=blue>Data saldo awal berhasil dihapus!</font>";
    } catch (PDOException $e) {
        $pesan = "<font color=red>Error hapus: " . $e->getMessage() . "</font>";
    }
}

// PROSES EDIT (ambil data untuk form)
$editData = null;
if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];
    $qEdit = $conn->prepare("SELECT * FROM tkas_saldo_awal WHERE id = ?");
    $qEdit->execute([$editId]);
    $editData = $qEdit->fetch(PDO::FETCH_ASSOC);
}
?>

<script type="text/javascript"> 
function stopRKey(evt) { 
  var evt = (evt) ? evt : ((event) ? event : null); 
  var node = (evt.target) ? evt.target : ((evt.srcElement) ? evt.srcElement : null); 
  if ((evt.keyCode == 13) && (node.type=="text"))  {return false;} 
} 
document.onkeypress = stopRKey; 
</script>

<body>

<div class="row"> 
    <ol class="breadcrumb">
	  <li><i class="fa fa-home"></i> SETTING SALDO AWAL KAS</li> 
	</ol> 
	<section class="panel">
	  <header class="panel-heading">
		  
		  <h4><font color="red"><?php echo $pesan;?></font></h4>
		  
		  <!-- Form Input Saldo Awal -->
		  <form role="form" method="POST" action="index.php?par=55">
		      <input type="hidden" name="simpan_saldo" value="1">
		      
		      <div class="form-group col-xs-12 col-sm-2">
				  <label>Bulan</label>
				  <select name="bulan" class="form-control" required>
					<?php
					for ($i = 1; $i <= 12; $i++) {
					    $sel = ($editData && $editData['bulan'] == $i) ? 'selected' : '';
					    echo "<option value='$i' $sel>" . $namaBulan[$i] . "</option>";
					}
					?>
				  </select>
			  </div>
			  
			  <div class="form-group col-xs-12 col-sm-2">
				  <label>Tahun</label>
				  <input type="number" name="tahun" class="form-control" 
				         value="<?php echo $editData ? $editData['tahun'] : date('Y'); ?>" 
				         min="2000" max="2099" required>
			  </div>
			  
			  <div class="form-group col-xs-12 col-sm-3">
				  <label>Saldo Awal (Rp)</label>
				  <input type="text" name="saldo_awal" class="form-control" 
				         value="<?php echo $editData ? number_format($editData['saldo_awal'], 0, ',', '.') : ''; ?>" 
				         placeholder="Contoh: 10.000.000" required>
			  </div>
			  
			  <div class="form-group col-xs-12 col-sm-3">
				  <label>Keterangan</label>
				  <input type="text" name="keterangan" class="form-control" 
				         value="<?php echo $editData ? htmlspecialchars($editData['keterangan']) : ''; ?>" 
				         placeholder="Keterangan (opsional)">
			  </div>
			  
			  <div class="form-group col-xs-12 col-sm-2">
				  <label>&nbsp;</label><br>
				  <button type="submit" class="btn btn-primary">
				      <?php echo $editData ? 'Update' : 'Simpan'; ?>
				  </button>
				  <?php if ($editData) { ?>
				  <a href="index.php?par=55" class="btn btn-default">Batal</a>
				  <?php } ?>
			  </div>
		  </form>
		  <div class="clearfix"></div>
		  
	  </header>
	  
	  <section class="content">
	  <div class="box-body">
	   <table id="tabel_saldo_awal" class="table table-bordered table-striped table-hover"> 
	
		<thead> 
			<tr>
			  <th>#</th>
			  <th>Bulan</th>
			  <th>Tahun</th>
			  <th>Saldo Awal</th>
			  <th>Keterangan</th>
			  <th>Diinput Oleh</th>
			  <th>Terakhir Update</th>
			  <th>Aksi</th>
			</tr>
		</thead>
		<tbody>
		<?php
		try {
		    $sql = $conn->prepare("SELECT s.*, u.nama AS nama_user 
		                           FROM tkas_saldo_awal s 
		                           LEFT JOIN ruser u ON s.iduser_input = u.iduser 
		                           ORDER BY s.tahun DESC, s.bulan DESC");
		    $sql->execute();
		    $no = 1;
		    
		    while ($rs = $sql->fetch(PDO::FETCH_ASSOC)) {
		        echo "<tr>
		            <td align=center>$no</td>
		            <td>" . $namaBulan[$rs['bulan']] . "</td>
		            <td>" . $rs['tahun'] . "</td>
		            <td align=right><strong>Rp " . number_format($rs['saldo_awal'], 0, ',', '.') . "</strong></td>
		            <td>" . htmlspecialchars($rs['keterangan']) . "</td>
		            <td>" . htmlspecialchars($rs['nama_user']) . "</td>
		            <td>" . date('d/m/Y H:i', strtotime($rs['updated_at'])) . "</td>
		            <td>
		                <a href='index.php?par=55&edit=" . $rs['id'] . "' class='btn btn-warning btn-xs'>Edit</a>
		                <a href='index.php?par=55&hapus=" . $rs['id'] . "' class='btn btn-danger btn-xs' onclick=\"return confirm('Yakin hapus saldo awal " . $namaBulan[$rs['bulan']] . " " . $rs['tahun'] . "?')\">Hapus</a>
		            </td>
		        </tr>";
		        $no++;
		    }
		} catch (PDOException $e) {
		    echo "<tr><td colspan='8' class='text-center'>Error: " . $e->getMessage() . "</td></tr>";
		}
		?>
		</tbody>
	   </table>
	  </div>
	  </section>
	</section>
</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
<script>
$(document).ready(function () {
    const tableId = '#tabel_saldo_awal';
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
        order: [[2, 'desc'], [1, 'desc']]
    });
});
</script>

</body>
</html>
