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

// POST Handler: Impor Kantong dari Excel
if (isset($_POST['proses_import_kantong'])) {
    if (isset($_FILES['file_excel']) && $_FILES['file_excel']['error'] == UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['file_excel']['tmp_name'];
        try {
            require 'vendor/autoload.php';
            if (!class_exists('ZipArchive')) {
                $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xls');
            } else {
                $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file_tmp);
            }
            
            $spreadsheet = $reader->load($file_tmp);
            $datas = $spreadsheet->getActiveSheet()->toArray();
            $succ_ins = 0; $succ_upd = 0; $succ_adj = 0;
            
            $conn->beginTransaction();
            
            for ($i = 1; $i < count($datas); $i++) {
                $id_k = trim($datas[$i][0] ?? '');
                $nama_k = trim($datas[$i][1] ?? '');
                $desk_k = trim($datas[$i][2] ?? '');
                
                // UNIVERSAL FINANCE PARSER (V4) - Anti Bug Nol Hilang
                $raw = trim($datas[$i][3] ?? '0');
                $clean = str_replace(['Rp', ' ', 'rp'], '', $raw);
                
                $has_dot = (strpos($clean, '.') !== false);
                $has_comma = (strpos($clean, ',') !== false);
                
                if ($has_dot && $has_comma) {
                    $dot_p = strrpos($clean, '.');
                    $comma_p = strrpos($clean, ',');
                    if ($dot_p > $comma_p) {
                        // US Format: 1,000.00 -> Dot is decimal
                        $clean = str_replace(',', '', $clean);
                    } else {
                        // ID Format: 1.000,00 -> Comma is decimal
                        $clean = str_replace('.', '', $clean);
                        $clean = str_replace(',', '.', $clean);
                    }
                } elseif ($has_dot) {
                    // Only dots. 1.000 or 1.000.000 or 1.5
                    if (substr_count($clean, '.') > 1 || preg_match('/\.\d{3}$/', $clean)) {
                        $clean = str_replace('.', '', $clean);
                    }
                } elseif ($has_comma) {
                    // Only commas. 1,000 or 1,5
                    if (substr_count($clean, ',') > 1 || preg_match('/,\d{3}$/', $clean)) {
                        $clean = str_replace(',', '', $clean);
                    } else {
                        $clean = str_replace(',', '.', $clean);
                    }
                }
                
                $saldo_excel = floatval($clean);
                
                if (empty($nama_k)) continue;
                
                if (empty($id_k)) {
                    // NEW KANTONG
                    $id_baru = generateIdKantong($conn);
                    $qins = $conn->prepare("INSERT INTO tkantong (id_kantong, nama_kantong, deskripsi, history_kantong, created_by) VALUES (?, ?, ?, ?, ?)");
                    $qins->execute([$id_baru, $nama_k, $desk_k, "Dibuat via Excel", $iduser]);
                    
                    if ($saldo_excel != 0) {
                        $def_akun = getDefaultKodakun($conn);
                        $notrans = generateNoTransaksi($conn);
                        $plusmin = ($saldo_excel > 0) ? '+' : '-';
                        $nominal = abs($saldo_excel);
                        $qkas = $conn->prepare("INSERT INTO tkas (notransaksi, tgltransaksi, kodakun, deskripsi, satuan, plusmin, jumlah, hargaunit, totalharga, iduser_proses, id_kantong) VALUES (?, ?, ?, ?, 'Saldo Awal', ?, 1, ?, ?, ?, ?)");
                        $qkas->execute([$notrans, date('Y-m-d'), $def_akun, "Saldo Awal via Excel", $plusmin, $nominal, $nominal, $iduser, $id_baru]);
                    }
                    $succ_ins++;
                } else {
                    // UPDATE EXISTING (Nama & Deskripsi)
                    $qupd = $conn->prepare("UPDATE tkantong SET nama_kantong = ?, deskripsi = ? WHERE id_kantong = ?");
                    $qupd->execute([$nama_k, $desk_k, $id_k]);
                    
                    // Cek Selisih Saldo untuk Adjustment Otomatis
                    $current_saldo = getSaldoKantong($conn, $id_k);
                    if (abs($saldo_excel - $current_saldo) > 0.01) {
                        $diff = $saldo_excel - $current_saldo;
                        $plusmin = ($diff > 0) ? '+' : '-';
                        $nominal = abs($diff);
                        $notrans = generateNoTransaksi($conn);
                        $def_akun = getDefaultKodakun($conn);
                        
                        $qadj = $conn->prepare("INSERT INTO tkas (notransaksi, tgltransaksi, kodakun, deskripsi, satuan, plusmin, jumlah, hargaunit, totalharga, iduser_proses, id_kantong) VALUES (?, ?, ?, ?, 'Adjustment', ?, 1, ?, ?, ?, ?)");
                        $qadj->execute([$notrans, date('Y-m-d'), $def_akun, "Penyesuaian Saldo via Excel", $plusmin, $nominal, $nominal, $iduser, $id_k]);
                        $succ_adj++;
                    }
                    $succ_upd++;
                }
            }
            $conn->commit();
            $pesan = "<div class='alert alert-info' style='border-radius:15px; background:rgba(232, 245, 233, 0.9); color:#2e7d32; border:1px solid #c8e6c9;'>
                        <i class='fa fa-check-circle'></i> <b>Sinkronisasi Selesai!</b><br>
                        - $succ_ins Kantong Baru Dibuat<br>
                        - $succ_upd Informasi Kantong Diperbarui<br>
                        - $succ_adj Saldo Telah Disamakan Persis dengan Excel
                      </div>";
        } catch (Exception $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            $pesan = "<div class='alert alert-danger' style='border-radius:15px;'>Gagal impor: " . $e->getMessage() . "</div>";
        }
    }
}

// Function untuk generate No Transaksi (copy dari 40_kas.php)
function generateNoTransaksi($conn) {
    static $last_new_num = 0;
    $prefix = 'TRF-' . date('Ym') . '-';
    $sql = "SELECT notransaksi FROM tkas WHERE notransaksi LIKE :prefix ORDER BY notransaksi DESC LIMIT 1";
    $q = $conn->prepare($sql);
    $q->execute([':prefix' => $prefix . '%']);
    $row = $q->fetch();
    
    if ($row) {
        $lastNum = (int) substr($row['notransaksi'], -4);
        $newNum = max($lastNum + 1, $last_new_num + 1);
    } else {
        $newNum = max(1, $last_new_num + 1);
    }
    
    $last_new_num = $newNum;
    return $prefix . str_pad($newNum, 4, '0', STR_PAD_LEFT);
}

// Function untuk generate ID Kantong otomatis
function generateIdKantong($conn) {
    static $last_k_num = 0;
    $prefix = 'KANTONG';
    $sql = "SELECT id_kantong FROM tkantong WHERE id_kantong LIKE :prefix ORDER BY id_kantong DESC LIMIT 1";
    $q = $conn->prepare($sql);
    $q->execute([':prefix' => $prefix . '%']);
    $row = $q->fetch();
    
    if ($row) {
        // e.g. KANTONG001 -> 001
        $lastNum = (int) substr($row['id_kantong'], 7);
        $newNum = max($lastNum + 1, $last_k_num + 1);
    } else {
        $newNum = max(1, $last_k_num + 1);
    }
    
    $last_k_num = $newNum;
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
/* PREMIUM UI V2 - Visual Excellence */
:root {
    --primary-gradient: linear-gradient(135deg, #2c3e50 0%, #4ca1af 100%);
    --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    --danger-gradient: linear-gradient(135deg, #cb2d3e 0%, #ef473a 100%);
    --warning-gradient: linear-gradient(135deg, #f2994a 0%, #f2c94c 100%);
    --glass-shadow: 0 10px 40px -10px rgba(0, 64, 128, 0.2);
}

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.kantong-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    border-radius: 20px;
    border: 1px solid rgba(255, 255, 255, 0.3);
    box-shadow: var(--glass-shadow);
    margin-bottom: 30px;
    animation: fadeInUp 0.6s cubic-bezier(0.2, 0.8, 0.2, 1);
    transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
    overflow: hidden;
}

.kantong-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 50px -15px rgba(0, 64, 128, 0.25);
}

.kantong-header {
    background: var(--primary-gradient);
    color: white;
    border-radius: 20px 20px 0 0;
    padding: 20px 30px;
    font-weight: 800;
    font-size: 18px;
    letter-spacing: 0.5px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.kantong-header.transfer { background: var(--success-gradient); }
.kantong-header.topup { background: var(--warning-gradient); }

.kantong-body { padding: 30px; }

/* Table Styling Enhancement */
.table-hover tbody tr {
    transition: all 0.3s ease;
}

.table-hover tbody tr:hover {
    background-color: rgba(30, 136, 229, 0.05) !important;
    transform: scale(1.008);
}

.badge-premium {
    padding: 8px 15px;
    border-radius: 25px;
    font-weight: 800;
    font-size: 14px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    display: inline-block;
}

.btn-premium {
    border-radius: 25px;
    font-weight: 800;
    text-transform: uppercase;
    font-size: 12px;
    letter-spacing: 1px;
    padding: 10px 20px;
    transition: all 0.3s;
    border: none;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.btn-premium:hover {
    transform: translateY(-3px) scale(1.05);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
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
      <div class="panel kantong-card" style="overflow: hidden;">
          <div class="kantong-header" style="background: #455a64; display: flex; justify-content: space-between; align-items: center; padding: 12px 20px;">
              <span style="font-size: 16px;"><i class="fa fa-list"></i> Daftar Seluruh Kantong</span>
              <button type="button" class="btn btn-info btn-sm" onclick="document.getElementById('modalImportKantong').style.display='block'" style="font-weight: bold; border-radius: 20px; box-shadow: 0 4px 10px rgba(0,0,0,0.2);">
                 <i class="fa fa-file-excel-o"></i> Kelola Kantong (Excel)
              </button>
          </div>
          <div class="kantong-body" style="padding: 0;">
	   <div class="table-responsive">
	   <table id="tabel_kantong" class="table table-hover" style="margin-bottom: 0;"> 
		  <thead style="background: #f8f9fa;"> 
			<tr style="color: #607d8b; font-weight: 800; text-transform: uppercase; font-size: 12px;">
			  <th style="padding: 15px; text-align: center;">#</th>
              <th style="padding: 15px;">Informasi Kantong</th>
              <th style="padding: 15px;">Deskripsi</th>
              <th style="padding: 15px;">Saldo Terkini</th>
              <th style="padding: 15px;">Admin / Waktu</th>
              <th style="padding: 15px; text-align: center;">Aksi Cepat</th>
            </tr>
		  </thead>
		  <tbody>
		  <?php 
			   try { 
			        $sql = $conn->prepare("SELECT * FROM tkantong ORDER BY created_at DESC"); 
					$sql->execute();				
					$no=1;
                    $total_seluruh_saldo = 0;
                    while($rs = $sql->fetch()) { 
                        $saldo = getSaldoKantong($conn, $rs['id_kantong']);
                        $is_positive = ($saldo >= 0);
                        
					    echo "   <tr class='premium-row'>
						  <td align=center style='vertical-align: middle;'>
                             <div class='badge' style='background: var(--primary-gradient); color: white; width: 30px; height: 30px; line-height: 22px; border-radius: 50%; box-shadow: 0 4px 8px rgba(0,0,0,0.1); border: 2px solid white;'>".$no."</div>
                          </td>
                          <td style='vertical-align: middle;'>
                             <div style='font-weight: 800; color: #2c3e50; font-size: 16px; letter-spacing: -0.3px;'>".htmlspecialchars($rs['nama_kantong'])."</div>
                             <div style='font-size: 11px; color: #7f8c8d; font-weight: 700; text-transform: uppercase;'>ID: <code style='background:#ecf0f1; padding: 2px 5px; border-radius: 4px; color:#2980b9;'>".htmlspecialchars($rs['id_kantong'])."</code></div>
                          </td>
                          <td style='vertical-align: middle;'>
                             <div style='font-size: 13px; color: #34495e; font-weight: 500;'>".(empty($rs['deskripsi']) ? '<i style="color:#bdc3c7;">Tidak ada deskripsi</i>' : htmlspecialchars($rs['deskripsi']))."</div>
                          </td>
                          <td style='vertical-align: middle;'>
                             <div class='badge-premium' style='background: ".($is_positive ? 'rgba(39, 174, 96, 0.1)' : 'rgba(231, 76, 60, 0.1)')."; color: ".($is_positive ? '#27ae60' : '#e74c3c')."; border: 1.5px solid ".($is_positive ? 'rgba(39, 174, 96, 0.2)' : 'rgba(231, 76, 60, 0.2)')."; width: 100%; text-align: right; min-width: 140px; font-size: 15px; font-family: \"Courier New\", Courier, monospace;'>
                                <i class='fa fa-money'></i> Rp ".number_format($saldo, 0, ',', '.')."
                             </div>
                          </td>
                          <td style='vertical-align: middle;'>
                             <div style='font-size: 12px; font-weight: 700; color: #2c3e50;'><i class='fa fa-user-shield'></i> ".htmlspecialchars($rs['created_by'])."</div>
                             <div style='font-size: 11px; color: #95a5a6;'><i class='fa fa-calendar-alt'></i> ".date('d M Y', strtotime($rs['created_at']))."</div>
                          </td>
                          <td align=center style='vertical-align: middle;'>
                          ";
                          
                          echo "<button class='btn btn-success btn-premium' onclick=\"tambahSaldo('".$rs['id_kantong']."', '".$rs['nama_kantong']."')\" title='Kelola Saldo' style='background: var(--success-gradient); color:white; margin-right:5px;'><i class='fa fa-plus-circle'></i> Saldo</button>";
                          
                          echo "<a href='index.php?par=40a&del=Y&id=".$rs['id_kantong']."' class='btn btn-danger btn-premium' style='background: var(--danger-gradient); color:white;' onclick=\"return confirm('Hapus Kantong ini?')\"><i class='fa fa-trash'></i> Hapus</a>";

                          
					    echo "
					  </td> 
					</tr> ";
					$no++;
                    $total_seluruh_saldo += $saldo;
                    }
		   }//try
		   catch (PDOException $e)	{
		     echo "<tr><td colspan='6' align='center'><font color=red>Error mengambil data: ".$e->getMessage()."</font></td></tr>";
		   }//catch
		  ?>
		  </tbody>
          <tfoot style="background: rgba(236, 240, 241, 0.5); border-top: 2px solid #bdc3c7;">
             <tr>
                <td colspan="3" style="padding: 15px 30px; font-weight: 800; color: #2c3e50; font-size: 14px; text-transform: uppercase;">
                   <i class="fa fa-chart-pie"></i> Konsolidasi Dana Seluruh Kantong
                </td>
                <td style="padding: 15px; text-align: right;">
                    <div style="font-weight: 900; font-size: 18px; color: #2980b9;">
                        Rp <?php echo number_format($total_seluruh_saldo ?? 0, 0, ',', '.'); ?>
                    </div>
                </td>
                <td colspan="2"></td>
             </tr>
          </tfoot>
	   </table>
	   </div>
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

<!-- Modal Import Kantong (Pusat Kontrol Excel) -->
<div id="modalImportKantong" class="modal">
    <div class="modal-content" style="max-width: 500px; border-radius: 12px; box-shadow: 0 8px 30px rgba(0,0,0,0.2);">
        <span class="close" onclick="document.getElementById('modalImportKantong').style.display='none'" style="font-size: 28px;">&times;</span>
        <h3 style="color: #455A64; font-weight: bold;"><i class="fa fa-folder-open"></i> Pusat Kontrol Kantong (Excel)</h3>
        <p class="text-muted">Kelola kategori kantong masal (Mendukung .xls)</p>
        <hr style="border-top: 2px solid #eee;">
        
        <!-- Langkah 1: Unduh -->
        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #1e88e5;">
            <h5 style="margin-top:0; font-weight:bold;">Langkah 1: Ambil Master Data</h5>
            <p style="font-size: 13px;">Unduh daftar kantong yang sudah ada untuk diedit atau ditambah baru.</p>
            <a href="40a_export_template_kantong.php" class="btn btn-primary btn-block" style="border-radius: 20px; background-color: #455A64 !important; border-color: #455A64 !important;">
                <i class="fa fa-download"></i> Unduh Tabel Kantong
            </a>
        </div>

        <!-- Langkah 2: Unggah -->
        <div style="background: #fdfdfe; padding: 15px; border-radius: 8px; border: 1px dashed #455A64;">
            <h5 style="margin-top:0; font-weight:bold;">Langkah 2: Unggah Perubahan</h5>
            <p style="font-size: 13px;">ID yang kosong akan dianggap sebagai Kantong Baru.</p>
            <form action="" method="POST" enctype="multipart/form-data">
                <input type="file" name="file_excel" accept=".xls,.xlsx" required class="form-control" style="margin-bottom: 15px;">
                <button type="submit" name="proses_import_kantong" class="btn btn-success btn-block" style="border-radius: 20px; font-weight: bold;">
                    <i class="fa fa-upload"></i> Proses Data Kantong
                </button>
            </form>
        </div>
    </div>
</div>

</body>
