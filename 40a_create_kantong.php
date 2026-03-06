<?php
session_start();
include "dbase.php"; 
include "islogin.php"; 
$iduser   = $_SESSION['DEFAULT_IDUSER'];

// Pesan
$pesan = '';

// CREATE Kantong
if (isset($_POST['create_kantong'])) {
    $nama_kantong = $_POST['nama_kantong'];
    $deskripsi = $_POST['deskripsi'] ?? '';
    $saldo_awal = isset($_POST['saldo_awal']) ? floatval(str_replace(['.', ','], ['', '.'], $_POST['saldo_awal'])) : 0;
    
    if (empty($nama_kantong)) {
        $pesan = "<font color=red>Error: Nama Kantong harus diisi!</font>";
    } else {
        try {
            $id_kantong = generateIdKantong($conn);
            $conn->beginTransaction();
            try {
                $sql = "INSERT INTO tkantong (id_kantong, nama_kantong, deskripsi, history_kantong, created_by) 
                        VALUES (:id_kantong, :nama_kantong, :deskripsi, :history, :created_by)";
                $qins = $conn->prepare($sql);
                $qins->execute([
                    ':id_kantong' => $id_kantong,
                    ':nama_kantong' => $nama_kantong,
                    ':deskripsi' => $deskripsi,
                    ':history' => "Dibuat oleh $iduser",
                    ':created_by' => $iduser
                ]);
                
                // Insert saldo awal jika ada
                if ($saldo_awal > 0) {
                    $default_kodakun = getDefaultKodakun($conn) ?? '';
                    $notransaksi = generateNoTransaksi($conn);
                    
                    $sql_saldo = "INSERT INTO tkas (notransaksi, tgltransaksi, kodakun, deskripsi, satuan, plusmin, jumlah, hargaunit, totalharga, iduser_proses, id_kantong) 
                           VALUES (:notransaksi, :tgltransaksi, :kodakun, :deskripsi, 'Saldo Awal', '+', 1, :nominal, :nominal, :iduser, :kantong)";
                    
                    $q_saldo = $conn->prepare($sql_saldo);
                    $q_saldo->execute([
                        ':notransaksi' => $notransaksi,
                        ':tgltransaksi' => date('Y-m-d'),
                        ':kodakun' => $default_kodakun,
                        ':deskripsi' => 'Saldo Awal',
                        ':nominal' => $saldo_awal,
                        ':iduser' => $iduser,
                        ':kantong' => $id_kantong
                    ]);
                }
                
                $conn->commit();
                $pesan = "<font color=blue>Kantong '<strong>$nama_kantong</strong>' berhasil dibuat dengan ID: <strong>$id_kantong</strong></font>";
            } catch (PDOException $e) {
                $conn->rollBack();
                $pesan = "<font color=red>Error: " . $e->getMessage() . "</font>";
            }
        } catch (PDOException $e) {
            $pesan = "<font color=red>Error: " . $e->getMessage() . "</font>";
        }
    }
}

// DELETE Kantong
if (isset($_GET['del']) && $_GET['del'] == 'Y' && isset($_GET['id'])) {
    $id_del = $_GET['id'];
    try {
         $conn->beginTransaction();
         try {
             // Hapus semua data transaksi terkait kas (Top up, Transfer, Saldo Awal, dll)
             $qdelkas = $conn->prepare("DELETE FROM tkas WHERE id_kantong = :id");
             $qdelkas->execute([':id' => $id_del]);
             
             // Hapus data kantong
             $qdel = $conn->prepare("DELETE FROM tkantong WHERE id_kantong = :id");
             $qdel->execute([':id' => $id_del]);
             
             $conn->commit();
             $pesan = "<font color=blue>Kantong beserta seluruh histori transaksinya berhasil dihapus secara permanen.</font>";
         } catch (PDOException $e) {
             $conn->rollBack();
             $pesan = "<font color=red>Error hapus kantong: " . $e->getMessage() . "</font>";
         }
    } catch (PDOException $e) {
        $pesan = "<font color=red>Error: " . $e->getMessage() . "</font>";
    }
}

// TAMBAH Saldo Kantong
if (isset($_POST['tambah_saldo'])) {
    $kantong_target = $_POST['kantong_target'];
    $nominal_tambah = floatval(str_replace(['.', ','], ['', '.'], $_POST['nominal_tambah']));
    $deskripsi_tambah = $_POST['deskripsi_tambah'] ?? 'Penambahan saldo kantong';
    
    if (empty($kantong_target)) {
        $pesan = "<font color=red>Error: Pilih kantong terlebih dahulu!</font>";
    } elseif ($nominal_tambah <= 0) {
        $pesan = "<font color=red>Error: Nominal harus lebih dari 0!</font>";
    } else {
        // Cek kantong ada
        $qcek_kantong = $conn->prepare("SELECT id_kantong FROM tkantong WHERE id_kantong = :id");
        $qcek_kantong->execute([':id' => $kantong_target]);
        if ($qcek_kantong->rowCount() == 0) {
            $pesan = "<font color=red>Error: Kantong tidak ditemukan!</font>";
        } else {
            // Cek dan get kodakun yang valid
            $default_kodakun = getDefaultKodakun($conn) ?? '';
            
            try {
                $notransaksi = generateNoTransaksi($conn);
                
                // Insert transaksi kredit (masuk) ke kantong target
                $sql = "INSERT INTO tkas (notransaksi, tgltransaksi, kodakun, deskripsi, satuan, plusmin, jumlah, hargaunit, totalharga, iduser_proses, id_kantong) 
                       VALUES (:notransaksi, :tgltransaksi, :kodakun, :deskripsi, 'Top Up', '+', 1, :nominal, :nominal, :iduser, :kantong)";
                
                $q = $conn->prepare($sql);
                $q->execute([
                    ':notransaksi' => $notransaksi,
                    ':tgltransaksi' => date('Y-m-d'),
                    ':kodakun' => $default_kodakun,
                    ':deskripsi' => $deskripsi_tambah,
                    ':nominal' => $nominal_tambah,
                    ':iduser' => $iduser,
                    ':kantong' => $kantong_target
                ]);
                
                $pesan = "<font color=blue>Saldo kantong berhasil ditambah Rp " . number_format($nominal_tambah, 0, ',', '.') . "!</font>";
                
            } catch (PDOException $e) {
                $pesan = "<font color=red>Error tambah saldo: " . $e->getMessage() . "</font>";
            }
        }
    }
}

// TRANSFER Saldo antar Kantong
if (isset($_POST['transfer_saldo'])) {
    $kantong_asal = $_POST['kantong_asal'];
    $kantong_tujuan = $_POST['kantong_tujuan'];
    $nominal_transfer = floatval(str_replace(['.', ','], ['', '.'], $_POST['nominal_transfer']));
    $deskripsi_transfer = $_POST['deskripsi_transfer'] ?? 'Transfer saldo antar kantong';
    
    if (empty($kantong_asal) || empty($kantong_tujuan)) {
        $pesan = "<font color=red>Error: Pilih kantong asal dan tujuan!</font>";
    } elseif ($kantong_asal == $kantong_tujuan) {
        $pesan = "<font color=red>Error: Kantong asal dan tujuan tidak boleh sama!</font>";
    } elseif ($nominal_transfer <= 0) {
        $pesan = "<font color=red>Error: Nominal transfer harus lebih dari 0!</font>";
    } else {
        // Cek kantong asal dan tujuan ada
        $qcek = $conn->prepare("SELECT id_kantong FROM tkantong WHERE id_kantong IN (:asal, :tujuan)");
        $qcek->execute([':asal' => $kantong_asal, ':tujuan' => $kantong_tujuan]);
        if ($qcek->rowCount() < 2) {
            $pesan = "<font color=red>Error: Kantong asal atau tujuan tidak ditemukan!</font>";
        } else {
            // Cek saldo kantong asal
            $saldo_asal = getSaldoKantong($conn, $kantong_asal);
            if ($nominal_transfer > $saldo_asal) {
                $pesan = "<font color=red>Error: Saldo kantong asal tidak cukup! Saldo tersedia: Rp " . number_format($saldo_asal, 0, ',', '.') . "</font>";
            } else {
                $conn->beginTransaction();
                try {
                    $notransaksi_debit = generateNoTransaksi($conn);
                    // generateNoTransaksi kedua setelah transaksi pertama akan sama jika record belum di-insert, 
                    // jadi kita bisa akali dengan menambahkan sufiks khusus atau generate ulang untuk kredit
                    
                    $default_kodakun = getDefaultKodakun($conn) ?? '';
                    
                    // Insert transaksi debit (keluar) dari kantong asal
                    $sql_debit = "INSERT INTO tkas (notransaksi, tgltransaksi, kodakun, deskripsi, satuan, plusmin, jumlah, hargaunit, totalharga, iduser_proses, id_kantong) 
                                 VALUES (:notransaksi, :tgltransaksi, :kodakun, :deskripsi, 'Transfer', '-', 1, :nominal, :nominal, :iduser, :kantong_asal)";
                    
                    $qdebit = $conn->prepare($sql_debit);
                    $params_debit = [
                        ':notransaksi' => $notransaksi_debit,
                        ':tgltransaksi' => date('Y-m-d'),
                        ':kodakun' => $default_kodakun,
                        ':deskripsi' => $deskripsi_transfer,
                        ':nominal' => $nominal_transfer,
                        ':iduser' => $iduser,
                        ':kantong_asal' => $kantong_asal
                    ];
                    $qdebit->execute($params_debit);

                    // Panggil generate lagi setelah insert pertama berhasil agar mendapat counter baru
                    $notransaksi_kredit = generateNoTransaksi($conn);

                    // Insert transaksi kredit (masuk) ke kantong tujuan  
                    $sql_kredit = "INSERT INTO tkas (notransaksi, tgltransaksi, kodakun, deskripsi, satuan, plusmin, jumlah, hargaunit, totalharga, iduser_proses, id_kantong) 
                                  VALUES (:notransaksi, :tgltransaksi, :kodakun, :deskripsi, 'Transfer', '+', 1, :nominal, :nominal, :iduser, :kantong_tujuan)";
                    
                    $qkredit = $conn->prepare($sql_kredit);
                    $params_kredit = [
                        ':notransaksi' => $notransaksi_kredit,
                        ':tgltransaksi' => date('Y-m-d'),
                        ':kodakun' => $default_kodakun,
                        ':deskripsi' => $deskripsi_transfer,
                        ':nominal' => $nominal_transfer,
                        ':iduser' => $iduser,
                        ':kantong_tujuan' => $kantong_tujuan
                    ];
                    $qkredit->execute($params_kredit);
                    
                    $conn->commit();
                    $pesan = "<font color=blue>Transfer saldo Rp " . number_format($nominal_transfer, 0, ',', '.') . " dari $kantong_asal ke $kantong_tujuan berhasil!</font>";
                    
                } catch (PDOException $e) {
                    $conn->rollBack();
                    $pesan = "<font color=red>Error transfer: " . $e->getMessage() . "</font>";
                }
            }
        }
    }
}

// Function untuk generate No Transaksi (copy dari 40_kas.php)
function generateNoTransaksi($conn) {
    $prefix = 'TRF-' . date('Ym') . '-';
    $sql = "SELECT notransaksi FROM tkas WHERE notransaksi LIKE :prefix ORDER BY notransaksi DESC LIMIT 1";
    $q = $conn->prepare($sql);
    $q->execute([':prefix' => $prefix . '%']);
    $row = $q->fetch();
    
    if ($row) {
        $lastNum = (int) substr($row['notransaksi'], -4);
        $newNum = $lastNum + 1;
    } else {
        $newNum = 1;
    }
    
    return $prefix . str_pad($newNum, 4, '0', STR_PAD_LEFT);
}

// Function untuk generate ID Kantong otomatis
function generateIdKantong($conn) {
    $prefix = 'KANTONG';
    $sql = "SELECT id_kantong FROM tkantong WHERE id_kantong LIKE :prefix ORDER BY id_kantong DESC LIMIT 1";
    $q = $conn->prepare($sql);
    $q->execute([':prefix' => $prefix . '%']);
    $row = $q->fetch();
    
    if ($row) {
        // e.g. KANTONG001 -> 001
        $lastNum = (int) substr($row['id_kantong'], 7);
        $newNum = $lastNum + 1;
    } else {
        $newNum = 1;
    }
    
    return $prefix . str_pad($newNum, 3, '0', STR_PAD_LEFT);
}

// Function untuk dapat kode akun default
function getDefaultKodakun($conn) {
    try {
        // Prioritas: cari akun dengan tipe 'D' (Debit)
        $stmt = $conn->query("SELECT kodakun FROM takun WHERE tipe = 'D' ORDER BY kodakun LIMIT 1");
        $row = $stmt->fetch();
        if ($row && !empty($row['kodakun'])) {
            return $row['kodakun'];
        }
        
        // Fallback: ambil akun pertama yang ada
        $stmt2 = $conn->query("SELECT kodakun FROM takun ORDER BY kodakun LIMIT 1");
        $row2 = $stmt2->fetch();
        if ($row2 && !empty($row2['kodakun'])) {
            return $row2['kodakun'];
        }
        
        return null; // tidak ada akun sama sekali
    } catch (Exception $e) {
        return null;
    }
}

// Function untuk hitung saldo kantong
function getSaldoKantong($conn, $id_kantong) {
    $sql = "SELECT 
                SUM(CASE WHEN plusmin = '+' THEN totalharga ELSE -totalharga END) as saldo
            FROM tkas 
            WHERE id_kantong = :id_kantong";
    $q = $conn->prepare($sql);
    $q->execute([':id_kantong' => $id_kantong]);
    $row = $q->fetch();
    return floatval($row['saldo'] ?? 0);
}

?>

<body>
 
<style>
/* Custom styling untuk halaman Kantong agar lebih menarik */
.kantong-card {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.08);
    margin-bottom: 25px;
    border: none;
    transition: transform 0.2s;
}
.kantong-card:hover {
    box-shadow: 0 6px 15px rgba(0,0,0,0.12);
}
.kantong-header {
    background: linear-gradient(135deg, #1e88e5 0%, #1565c0 100%);
    color: white;
    border-radius: 8px 8px 0 0;
    padding: 15px 20px;
    font-weight: 600;
}
.kantong-header.transfer {
    background: linear-gradient(135deg, #43a047 0%, #2e7d32 100%);
}
.kantong-header.topup {
    background: linear-gradient(135deg, #ffb300 0%, #f39c12 100%);
}
.kantong-body {
    padding: 20px;
}
.table-kantong {
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}
.table-kantong thead {
    background-color: #f4f6f9;
    color: #333;
}
</style>

<div class="row"> 
    <ol class="breadcrumb" style="margin-bottom: 20px; background:#fff; box-shadow:0 1px 3px rgba(0,0,0,0.05); border-radius:4px;">
      <li><i class="fa fa-home"></i> KELOLA KANTONG</li> 
	</ol> 
	
	<?php if(!empty($pesan)): ?>
        <div style="margin-bottom:20px; padding:15px; background:#e8f4fd; border-left:4px solid #2196F3; border-radius:4px;">
            <h4 style="margin:0;"><?php echo $pesan;?></h4>
        </div>
    <?php endif; ?>

	<section class="col-md-12">
      
      <!-- Box Create Kantong -->
      <div class="panel kantong-card">
          <div class="kantong-header">
              <i class="fa fa-plus-square"></i> Buat Kantong Baru
          </div>
          <div class="kantong-body">
              <form role="form" method="POST" action="index.php?par=40a"> 
			 
			<div class="row">
				<div class="col-xs-12 col-md-4">
					<div class="form-group">
						 <label>Nama Kantong</label>	
						 <input type="text" name="nama_kantong" class="form-control" placeholder="Contoh: Kantong Utama" required>
					</div>
				</div>
				
				<div class="col-xs-12 col-md-4">
					<div class="form-group">
						 <label>Saldo Awal</label>	
						 <input type="text" name="saldo_awal" class="form-control" placeholder="0" onkeyup="maskRibuan(this)">
					</div>
				</div>
				
				<div class="col-xs-12 col-md-4">
					<div class="form-group">
						 <label>Deskripsi</label>	
						 <input type="text" name="deskripsi" class="form-control" placeholder="Deskripsi kantong...">
					</div>
				</div>
			</div>
			
			<div class="row">
				 <div class="col-xs-12" style="margin-top:10px;"> 
					<div class="form-group"> 
						 <input type="hidden" name="par" value="40a">
                         <input type="hidden" name="create_kantong" value="Y">
                         <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Simpan Kantong</button>
                         <button type="reset" class="btn btn-default">Reset</button>
                     </div> 
				 </div>
			</div> 
          </form>
          </div>
      </div>
      
      <!-- Box Transfer Saldo -->
      <div class="panel kantong-card">
          <div class="kantong-header transfer">
              <i class="fa fa-exchange"></i> Transfer Saldo Antar Kantong
          </div>
          <div class="kantong-body">
		  <form role="form" method="POST" action="index.php?par=40a"> 
			<div class="row">
				<div class="col-xs-12 col-md-3">
				  <div class="form-group">
					  <label>Kantong Asal</label>	
					  <select name="kantong_asal" class="form-control" required>
						<option value="">-- Pilih Kantong Asal --</option>
						<?php
							$qk = $conn->prepare("SELECT id_kantong, nama_kantong FROM tkantong ORDER BY nama_kantong ASC"); 
							$qk->execute(); 
							while($rsk = $qk->fetch()){ 
							  echo "<option value='".$rsk['id_kantong']."'>".$rsk['nama_kantong']."</option>\n"; 
							}
						?>
					  </select>
				   </div>
				 </div>	
				 
				<div class="col-xs-12 col-md-3">
					<div class="form-group">
						 <label>Kantong Tujuan</label>	
						 <select name="kantong_tujuan" class="form-control" required>
							<option value="">-- Pilih Kantong Tujuan --</option>
							<?php
								$qk = $conn->prepare("SELECT id_kantong, nama_kantong FROM tkantong ORDER BY nama_kantong ASC"); 
								$qk->execute(); 
								while($rsk = $qk->fetch()){ 
								  echo "<option value='".$rsk['id_kantong']."'>".$rsk['nama_kantong']."</option>\n"; 
								}
							?>
						 </select>
					</div>
				</div>
				
				<div class="col-xs-12 col-md-3">
					<div class="form-group">
						 <label>Nominal Transfer</label>	
						 <input type="text" name="nominal_transfer" class="form-control" placeholder="0" onkeyup="maskRibuan(this)" required>
					</div>
				</div>
				
				<div class="col-xs-12 col-md-3">
					<div class="form-group">
						 <label>Deskripsi</label>	
						 <input type="text" name="deskripsi_transfer" class="form-control" placeholder="Deskripsi transfer..." value="Transfer saldo antar kantong">
					</div>
				</div>
			</div>
			
			<div class="row">
				 <div class="col-xs-12" style="margin-top:10px;"> 
					<div class="form-group"> 
						 <input type="hidden" name="transfer_saldo" value="Y">
                         <button type="submit" class="btn btn-success"><i class="fa fa-paper-plane"></i> Kirim Saldo</button>
                     </div> 
				 </div>
			</div> 
          </form>
          </div>
      </div>
		  
      <!-- Box Topup Saldo -->
      <div class="panel kantong-card">
          <div class="kantong-header topup">
              <i class="fa fa-plus-circle"></i> Tambah Saldo (Top-Up) Kantong
          </div>
          <div class="kantong-body">
		  <form role="form" method="POST" action="index.php?par=40a"> 
			<div class="row">
				<div class="col-xs-12 col-md-4">
				  <div class="form-group">
					  <label>Pilih Kantong</label>	
					  <select name="kantong_target" class="form-control" required>
						<option value="">-- Pilih Kantong --</option>
						<?php
							$qk = $conn->prepare("SELECT id_kantong, nama_kantong FROM tkantong ORDER BY nama_kantong ASC"); 
							$qk->execute(); 
							while($rsk = $qk->fetch()){ 
							  echo "<option value='".$rsk['id_kantong']."'>".$rsk['nama_kantong']."</option>\n"; 
							}
						?>
					  </select>
				   </div>
				 </div>	
				 
				<div class="col-xs-12 col-md-4">
					<div class="form-group">
						 <label>Nominal Tambah</label>	
						 <input type="text" name="nominal_tambah" class="form-control" placeholder="0" onkeyup="maskRibuan(this)" required>
					</div>
				</div>
				
				<div class="col-xs-12 col-md-4">
					<div class="form-group">
						 <label>Deskripsi</label>	
						 <input type="text" name="deskripsi_tambah" class="form-control" placeholder="Deskripsi penambahan..." value="Penambahan saldo kantong">
					</div>
				</div>
			</div>
			
			<div class="row">
				 <div class="col-xs-12" style="margin-top:10px;"> 
					<div class="form-group"> 
						 <input type="hidden" name="tambah_saldo" value="Y">
                         <button type="submit" class="btn btn-warning"><i class="fa fa-money"></i> Tambah Saldo</button>
                     </div> 
				 </div>
			</div> 
          </form>
          </div>
      </div>
	  
	  <!-- Box Tabel -->
      <div class="panel kantong-card">
          <div class="kantong-header" style="background: #455a64;">
              <i class="fa fa-list"></i> Daftar Seluruh Kantong
          </div>
          <div class="kantong-body">
	   <table id="tabel_kantong" class="table table-bordered table-striped table-hover table-kantong"> 
		  <thead> 
			<tr>
			  <th>#</th>
              <th>Nama Kantong</th>
              <th>Deskripsi</th>
              <th>Saldo</th>
              <th>Dibuat Oleh</th>
              <th>Dibuat Tanggal</th>
              <th>Aksi</th>
            </tr>
		  </thead>
		  <tbody>
		  <?php 
			   try { 
			        $sql = $conn->prepare("SELECT * FROM tkantong ORDER BY created_at DESC"); 
					$sql->execute();				
					$no=1;
                    while($rs = $sql->fetch()) { 
                        $saldo = getSaldoKantong($conn, $rs['id_kantong']);
					    echo "   <tr>
						  <td align=center><font size=-1>".$no."</font></td>
                          <td><font size=-1>".$rs['nama_kantong']."</font></td>
                          <td><font size=-1>".$rs['deskripsi']."</font></td>
                          <td><font size=-1 color='".($saldo >= 0 ? 'green' : 'red')."'><strong>Rp ".number_format($saldo, 0, ',', '.')."</strong></font></td>
                          <td><font size=-1>".$rs['created_by']."</font></td>
                          <td><font size=-1>".date('d/m/Y H:i', strtotime($rs['created_at']))."</font></td>
                          <td align=center>
                          ";
                          
                          echo "<button class='btn btn-success btn-xs' onclick=\"tambahSaldo('".$rs['id_kantong']."', '".$rs['nama_kantong']."')\" title='Tambah Saldo' style='margin-right:4px;'><i class='fa fa-plus'></i> Saldo</button>";
                          
                          echo "<a href='index.php?par=40a&del=Y&id=".$rs['id_kantong']."' class='btn btn-danger btn-xs' onclick=\"return confirm('Yakin hapus kantong ini? Peringatan: Seluruh histori transaksi dan saldo dari kantong ini juga akan ikut terhapus secara permanen dari Kas!')\"><i class='fa fa-trash'></i> Delete</a>";

                          
					    echo "
					  </td> 
					</tr> ";
					$no++;
                    }
		   }//try
		   catch (PDOException $e)	{
		     echo "<tr><td colspan='7' align='center'><font color=red>Error mengambil data: ".$e->getMessage()."</font></td></tr>";
		   }//catch
		  ?>
		  </tbody>
	   </table>
	  </div>
      </div>
	</section>
</div>

<script>
function maskRibuan(angka) {
    var rev = parseInt(angka.value.replace(/,.*|[^0-9]/g, ''), 10).toString().split('').reverse().join('');
    var rev2 = '';
    for (var i = 0; i < rev.length; i++) {
        rev2 += rev[i];
        if ((i + 1) % 3 === 0 && i !== (rev.length - 1)) {
            rev2 += '.';
        }
    }
    angka.value = rev2.split('').reverse().join('');
}

function tambahSaldo(idKantong, namaKantong) {
    // Auto-fill form tambah saldo
    document.querySelector('select[name="kantong_target"]').value = idKantong;
    document.querySelector('input[name="nominal_tambah"]').focus();
    
    // Scroll ke form tambah saldo
    var headers = document.querySelectorAll('h4');
    for (var i = 0; i < headers.length; i++) {
        if (headers[i].textContent.includes('TAMBAH SALDO KANTONG')) {
            headers[i].scrollIntoView({behavior: 'smooth'});
            break;
        }
    }
}
</script>

</body>
