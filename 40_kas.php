<?php
session_start();
include "dbase.php"; 
include "islogin.php"; 
$iduser   = $_SESSION['DEFAULT_IDUSER'];
$kodjab   = isset($_SESSION['DEFAULT_KODJAB']) ? $_SESSION['DEFAULT_KODJAB'] : 0; 
$hariini  = date('Y-m-d');
$pesan    = '';

// ========== AUTO-MIGRATION: Add bukti_kas column ==========
try {
    $cek = $conn->query("SHOW COLUMNS FROM tkas LIKE 'bukti_kas'");
    if ($cek->rowCount() == 0) {
        $conn->exec("ALTER TABLE tkas ADD COLUMN bukti_kas VARCHAR(255) DEFAULT NULL");
    }
} catch (Exception $e) {}

// Create upload directory if not exists
if (!is_dir(__DIR__ . '/uploads/bukti_kas')) {
    @mkdir(__DIR__ . '/uploads/bukti_kas', 0755, true);
}

// Generate No Transaksi
function generateNoTransaksi($conn) {
    $prefix = 'KAS-' . date('Ym') . '-';
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

// INSERT Transaksi
if (isset($_POST['ins'])) {
    $notransaksi    = generateNoTransaksi($conn);
    $tgltransaksi   = $_POST['tgltransaksi'];
    $iduser_proses  = $_POST['diproses'];
    $id_kantong     = $_POST['id_kantong'];
    
    $kodakun_list   = $_POST['kodakun'];
    $deskripsi_list = $_POST['deskripsi'];
    $satuan_list    = $_POST['satuan'];
    $plusmin_list   = $_POST['plusmin'];
    $jumlah_list    = $_POST['jumlah'];
    $hargaunit_list = $_POST['hargaunit'];
    
    if (empty($kodakun_list[0]) || empty($tgltransaksi)) {
        $pesan = "<font color=red>Error: Tanggal dan minimal 1 item transaksi harus diisi.</font>";
    } else {
        $conn->beginTransaction();
        
        try {
            $sql = "INSERT INTO tkas (notransaksi, tgltransaksi, kodakun, deskripsi, satuan, plusmin, jumlah, hargaunit, totalharga, iduser_proses, bukti_kas, id_kantong) 
                    VALUES (:notransaksi, :tgltransaksi, :kodakun, :deskripsi, :satuan, :plusmin, :jumlah, :hargaunit, :totalharga, :iduser_proses, :bukti_kas, :id_kantong)";
            $qins = $conn->prepare($sql);
            
            foreach ($kodakun_list as $i => $kodakun) {
                if (!empty($kodakun)) {
                    $jumlah = floatval(str_replace(',', '.', $jumlah_list[$i]));
                    $hargaunit = floatval(str_replace(['.', ','], ['', '.'], $hargaunit_list[$i]));
                    $totalharga = $jumlah * $hargaunit;
                    
                    // Handle multi-file upload per item
                    $buktiFiles = [];
                    if (isset($_FILES['bukti_kas'])) {
                        // bukti_kas is indexed as bukti_kas[itemIndex][fileIndex]
                        // but HTML multiple file input with name="bukti_kas_0[]" needs different handling
                        // We use name="bukti_kas_{$i}[]" pattern via JS
                    }
                    // Check for files uploaded with name pattern bukti_kas_N
                    $fileKey = 'bukti_kas_' . $i;
                    if (isset($_FILES[$fileKey])) {
                        $fileCount = is_array($_FILES[$fileKey]['name']) ? count($_FILES[$fileKey]['name']) : 0;
                        for ($f = 0; $f < $fileCount; $f++) {
                            if ($_FILES[$fileKey]['error'][$f] == UPLOAD_ERR_OK) {
                                $ext = strtolower(pathinfo($_FILES[$fileKey]['name'][$f], PATHINFO_EXTENSION));
                                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
                                if (in_array($ext, $allowed)) {
                                    $fname = 'bukti_kas_' . date('YmdHis') . '_' . $i . '_' . $f . '.' . $ext;
                                    $dest = __DIR__ . '/uploads/bukti_kas/' . $fname;
                                    if (move_uploaded_file($_FILES[$fileKey]['tmp_name'][$f], $dest)) {
                                        $buktiFiles[] = $fname;
                                    }
                                }
                            }
                        }
                    }
                    $buktiJson = !empty($buktiFiles) ? json_encode($buktiFiles) : null;
                    
                    $qins->execute([
                        ':notransaksi'   => $notransaksi,
                        ':tgltransaksi'  => $tgltransaksi,
                        ':kodakun'       => $kodakun,
                        ':deskripsi'     => $deskripsi_list[$i] ?? '',
                        ':satuan'        => $satuan_list[$i] ?? '',
                        ':plusmin'       => $plusmin_list[$i] ?? '-',
                        ':jumlah'        => $jumlah,
                        ':hargaunit'     => $hargaunit,
                        ':totalharga'    => $totalharga,
                        ':iduser_proses' => $iduser_proses,
                        ':bukti_kas'     => $buktiJson,
                        ':id_kantong'    => $id_kantong
                    ]);
                }
            }
            
            $conn->commit();
            $pesan = "<font color=blue>Transaksi $notransaksi berhasil disimpan</font>";
            
        } catch (PDOException $e) {
            $conn->rollBack();
            $pesan = "<font color=red>Error insert: " . $e->getMessage() . "</font>";
        }
    }
}

// DELETE Transaksi 
if (isset($_GET['del']) && isset($_GET['id'])) {
    $idkas = (int) $_GET['id'];
    
    // Cek status approval terlebih dahulu
    $sqlApprove = "SELECT sts_approve FROM tkas WHERE idkas = :id";
    $qApprove = $conn->prepare($sqlApprove);
    $qApprove->execute([':id' => $idkas]);
    $rowApprove = $qApprove->fetch();
    
    if ($rowApprove && $rowApprove['sts_approve'] == 'Y') {
        $pesan = "<font color=red>Transaksi sudah disetujui GM, tidak dapat dihapus</font>";
    } else {
        // Cek kepemilikan (case-insensitive)
        $sqlcek = "SELECT * FROM tkas WHERE idkas = :id AND LOWER(iduser_proses) = LOWER(:iduser)";
        $qcek = $conn->prepare($sqlcek);
        $qcek->execute([':id' => $idkas, ':iduser' => $iduser]);
        
        if ($qcek->rowCount() < 1 && $kodjab != 1) {
            $pesan = "<font color=red>Tidak boleh hapus data milik user lain</font>";
        } else {
            try {
                $sql = $conn->prepare("DELETE FROM tkas WHERE idkas = :id");
                $sql->execute([':id' => $idkas]);
                $pesan = "<font color=blue>Data berhasil dihapus</font>";
            } catch (PDOException $e) {
                $pesan = "<font color=red>Error delete: " . $e->getMessage() . "</font>";
            }
        }
    }
}

// UPDATE Transaksi
if (isset($_POST['upd'])) {
    $idkas          = (int) $_POST['edit_idkas'];
    $tgltransaksi   = $_POST['edit_tgltransaksi'];
    $kodakun        = $_POST['edit_kodakun'];
    $deskripsi      = $_POST['edit_deskripsi'];
    $satuan         = $_POST['edit_satuan'];
    $plusmin        = $_POST['edit_plusmin'];
    $jumlah         = floatval(str_replace(',', '.', $_POST['edit_jumlah']));
    $hargaunit      = floatval(str_replace(['.', ','], ['', '.'], $_POST['edit_hargaunit']));
    $totalharga     = $jumlah * $hargaunit;
    
    // Cek status approval
    $sqlApprove = "SELECT sts_approve FROM tkas WHERE idkas = :id";
    $qApprove = $conn->prepare($sqlApprove);
    $qApprove->execute([':id' => $idkas]);
    $rowApprove = $qApprove->fetch();
    
    if ($rowApprove && $rowApprove['sts_approve'] == 'Y') {
        $pesan = "<font color=red>Transaksi sudah disetujui GM, tidak dapat diubah</font>";
    } else {
        // Cek kepemilikan (case-insensitive)
        $sqlcek = "SELECT * FROM tkas WHERE idkas = :id AND LOWER(iduser_proses) = LOWER(:iduser)";
        $qcek = $conn->prepare($sqlcek);
        $qcek->execute([':id' => $idkas, ':iduser' => $iduser]);
        
        if ($qcek->rowCount() < 1 && $kodjab != 1) {
            $pesan = "<font color=red>Tidak boleh edit data milik user lain</font>";
        } else {
            try {
                // Handle multi bukti kas upload on edit
                $bukti_sql = '';
                $bukti_param = [];
                
                // Get existing bukti files
                $qExisting = $conn->prepare("SELECT bukti_kas FROM tkas WHERE idkas = :id");
                $qExisting->execute([':id' => $idkas]);
                $rowExisting = $qExisting->fetch(PDO::FETCH_ASSOC);
                $existingFiles = [];
                if (!empty($rowExisting['bukti_kas'])) {
                    $decoded = json_decode($rowExisting['bukti_kas'], true);
                    $existingFiles = is_array($decoded) ? $decoded : [$rowExisting['bukti_kas']];
                }
                
                // Upload new files
                $newFiles = [];
                if (isset($_FILES['edit_bukti_kas']) && is_array($_FILES['edit_bukti_kas']['name'])) {
                    $fileCount = count($_FILES['edit_bukti_kas']['name']);
                    for ($f = 0; $f < $fileCount; $f++) {
                        if ($_FILES['edit_bukti_kas']['error'][$f] == UPLOAD_ERR_OK) {
                            $ext = strtolower(pathinfo($_FILES['edit_bukti_kas']['name'][$f], PATHINFO_EXTENSION));
                            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
                            if (in_array($ext, $allowed)) {
                                $fname = 'bukti_kas_' . date('YmdHis') . '_edit_' . $idkas . '_' . $f . '.' . $ext;
                                $dest = __DIR__ . '/uploads/bukti_kas/' . $fname;
                                if (move_uploaded_file($_FILES['edit_bukti_kas']['tmp_name'][$f], $dest)) {
                                    $newFiles[] = $fname;
                                }
                            }
                        }
                    }
                }
                
                if (!empty($newFiles)) {
                    $allFiles = array_merge($existingFiles, $newFiles);
                    $bukti_sql = ', bukti_kas = :bukti_kas';
                    $bukti_param[':bukti_kas'] = json_encode($allFiles);
                }
                
                $sql = "UPDATE tkas SET 
                        tgltransaksi = :tgltransaksi,
                        kodakun = :kodakun,
                        deskripsi = :deskripsi,
                        satuan = :satuan,
                        plusmin = :plusmin,
                        jumlah = :jumlah,
                        hargaunit = :hargaunit,
                        totalharga = :totalharga
                        $bukti_sql
                        WHERE idkas = :idkas";
                $qupd = $conn->prepare($sql);
                $params = [
                    ':tgltransaksi' => $tgltransaksi,
                    ':kodakun'      => $kodakun,
                    ':deskripsi'    => $deskripsi,
                    ':satuan'       => $satuan,
                    ':plusmin'      => $plusmin,
                    ':jumlah'       => $jumlah,
                    ':hargaunit'    => $hargaunit,
                    ':totalharga'   => $totalharga,
                    ':idkas'        => $idkas
                ];
                $params = array_merge($params, $bukti_param);
                $qupd->execute($params);
                $pesan = "<font color=blue>Data berhasil diupdate</font>";
            } catch (PDOException $e) {
                $pesan = "<font color=red>Error update: " . $e->getMessage() . "</font>";
            }
        }
    }
}

// Fetch data akun untuk dropdown di modal
$akunOptions = [];
$qaModal = $conn->prepare("SELECT kodakun, nmakun FROM takun WHERE tipe = 'D' ORDER BY kodakun");
$qaModal->execute();
while($raModal = $qaModal->fetch()) {
    $akunOptions[] = $raModal;
}

?>	 

<body>
 
<div class="row"> 
    <ol class="breadcrumb">
      <li><i class="fa fa-home"></i>PENCATATAN KAS</li> 
	</ol> 
	<section class="panel">
	  <header class="panel-heading">
		  
		  <form role="form" method="POST" action="index.php?par=40" enctype="multipart/form-data"> 
			 
			<div class="row">
				<div class="col-xs-12 col-md-3">
				  <div class="form-group">
					  <label>No. Transaksi</label>	
					  <input type="text" class="form-control" value="<?php echo generateNoTransaksi($conn); ?>" readonly style="font-weight:bold; background-color:#f5f5f5;">
				   </div>
				 </div>	
				 
				<div class="col-xs-12 col-md-3">
					<div class="form-group">
						 <label>Tanggal Transaksi</label>	
						 <input type="date" name="tgltransaksi" class="form-control" value="<?php echo $hariini; ?>" required>
					</div>
				</div>
				<div class="col-xs-12 col-md-3">
					<div class="form-group">
						 <label>Diproses Oleh</label>	
						  <select name="diproses" id="diproses" class="form-control" required> 
							<option value="" selected disabled>-- Pilih User --</option>
								<?php
									$qk = $conn->prepare("SELECT * FROM ruser WHERE stsaktif = 1 ORDER BY nik ASC, nama ASC"); 
									$qk->execute(); 
									while($rsk = $qk->fetch()){ 
									   if ($iduser == $rsk['iduser']) {
										  echo "<option value=".$rsk['iduser']." SELECTED>".$rsk['nama']."</option>\n"; 
									   } else {
										  echo "<option value=".$rsk['iduser'].">".$rsk['nama']."</option>\n"; 
									   }	  
									}
								?>
							</select> 
					</div>
				</div>
				<div class="col-xs-12 col-md-3">
					<div class="form-group">
						 <label>Kantong</label>	
						  <select name="id_kantong" id="id_kantong" class="form-control" required> 
							<option value="" selected disabled>-- Pilih Kantong --</option>
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
			</div>
			
			<div class="row">
                <div class="col-xs-12">
                    <label>Daftar Item Transaksi</label>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th style="width: 15%;">Akun</th>
                                <th style="width: 20%;">Deskripsi</th>
                                <th style="width: 10%;">Satuan</th>
                                <th style="width: 8%;">+/-</th>
                                <th style="width: 10%;">Jumlah</th>
                                <th style="width: 12%;">Harga Unit</th>
                                <th style="width: 12%;">Total Harga</th>
                                <th style="width: 12%;">Bukti / Struk</th>
                                <th style="width: 5%;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="item_table_body">
                            <tr>
                                <td>
                                    <select name="kodakun[]" class="form-control" required>
                                        <option value="">-- Pilih Akun --</option>
                                        <?php
                                        $qa = $conn->prepare("SELECT kodakun, nmakun FROM takun WHERE tipe = 'D' ORDER BY kodakun");
                                        $qa->execute();
                                        while($ra = $qa->fetch()) {
                                            echo "<option value='".$ra['kodakun']."'>".$ra['kodakun']." - ".$ra['nmakun']."</option>";
                                        }
                                        ?>
                                    </select>
                                </td>
                                <td><input type="text" name="deskripsi[]" class="form-control" placeholder="Deskripsi item..."></td>
                                <td>
                                    <select name="satuan[]" class="form-control">
                                        <option value="">--</option>
                                        <option value="Pcs">Pcs</option>
                                        <option value="Unit">Unit</option>
                                        <option value="Rim">Rim</option>
                                        <option value="Pak">Pak</option>
                                        <option value="Dus">Dus</option>
                                        <option value="Liter">Liter</option>
                                        <option value="Kg">Kg</option>
                                        <option value="Org">Org</option>
                                        <option value="Bln">Bln</option>
                                        <option value="Ls">Ls (Lumpsum)</option>
                                    </select>
                                </td>
                                <td>
                                    <select name="plusmin[]" class="form-control">
                                        <option value="-">- (Keluar)</option>
                                        <option value="+">+ (Masuk)</option>
                                    </select>
                                </td>
                                <td><input type="text" name="jumlah[]" class="form-control calc-jumlah" value="1" onkeyup="maskRibuan(this); hitungTotal(this)" onchange="hitungTotal(this)"></td>
                                <td><input type="text" name="hargaunit[]" class="form-control calc-harga" placeholder="0" onkeyup="maskRibuan(this); hitungTotal(this)" onchange="hitungTotal(this)"></td>
                                <td><input type="text" name="totalharga[]" class="form-control calc-total" placeholder="0" readonly style="background-color:#f5f5f5;"></td>
                                <td>
                                    <label class="bukti-upload-area" data-index="0">
                                        <i class="fa fa-cloud-upload" style="font-size:18px; color:#999;"></i>
                                        <span class="bukti-upload-text">Pilih File</span>
                                        <input type="file" name="bukti_kas_0[]" multiple accept=".jpg,.jpeg,.png,.gif,.pdf" style="display:none;" onchange="updateBuktiLabel(this)">
                                        <small class="bukti-count" style="display:none; color:green; font-weight:bold;"></small>
                                    </label>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-danger btn-sm" onclick="removeItemRow(this)" disabled>
                                        <i class="fa fa-minus"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <button type="button" class="btn btn-success" id="add_item_btn">
                        <i class="fa fa-plus"></i> Tambah Item
                    </button>
                </div>
            </div>
            
            <hr>

			<div class="row">
				 <div class="col-xs-12"> 
					<div class="form-group"> 
						 <input type="hidden" name="par" id="par" value="40">
                         <input type="hidden" name="ins" id="ins" value="Y">
                         <button type="submit" name="submit" class="btn btn-primary" value="Y">Simpan Transaksi</button>
                         <button type="reset" class="btn btn-danger">Reset</button>
                     </div> 
				 </div>
			</div> 
          </form>
		  <h4><font color="red"><?php echo $pesan;?></font></h4>
	  </header>
	  <section class="content">
	  <div align="center">DAFTAR TRANSAKSI KAS
	  </div>
	  <div class="box-body">
	   <table id="tabel_kas" class="table table-bordered table-striped table-hover"> 
		  <thead> 
			<tr>
			  <th>#</th>
              <th>No Transaksi</th>
              <th>Kantong</th>
              <th>Tanggal</th>
              <th>Akun</th>
              <th>Deskripsi</th>
              <th>+/-</th>
              <th>Jumlah</th>
              <th>Total</th>
              <th>Diproses</th>
              <th>Bukti</th>
              <th>Aksi</th>
            </tr>
		  </thead>
		  <tbody>
		  <?php 
			   try { 
			        $sql = $conn->prepare("
                        SELECT 
                            k.idkas, k.notransaksi, k.tgltransaksi, k.kodakun, k.deskripsi, 
                            k.satuan, k.plusmin, k.jumlah, k.hargaunit, k.totalharga, k.iduser_proses, k.sts_approve, k.bukti_kas, k.id_kantong,
                            a.nmakun,
                            u.nama AS nama_proses,
                            t.nama_kantong
                        FROM 
                            tkas k
                        LEFT JOIN 
                            takun a ON k.kodakun = a.kodakun
                        LEFT JOIN 
                            ruser u ON k.iduser_proses = u.iduser
                        LEFT JOIN 
                            tkantong t ON k.id_kantong = t.id_kantong
                        WHERE
                            1=1 
                        ORDER BY 
                            k.tgltransaksi DESC, k.notransaksi DESC
                    "); 
					$sql->execute();				
					
					$no=1;
                    while($rs = $sql->fetch()) { 
                        $plusminClass = ($rs['plusmin'] == '+') ? 'color:green;font-weight:bold;' : 'color:red;font-weight:bold;';
                        $isApproved = ($rs['sts_approve'] == 'Y');
                        $isOwnerOrAdmin = (strcasecmp($iduser, $rs['iduser_proses']) == 0 || $kodjab == 1);
					    echo "   <tr>
						  <td align=center><font size=-1>".$no."</font></td>
                          <td><font size=-1>".$rs['notransaksi']."</font></td>
                          <td><font size=-1>".$rs['nama_kantong']."</font></td>
                          <td><font size=-1>".date('d/m/Y', strtotime($rs['tgltransaksi']))."</font></td>
                          <td><font size=-1>".$rs['kodakun']." - ".$rs['nmakun']."</font></td>
                          <td><font size=-1>".$rs['deskripsi']."</font></td>
                          <td align=center style='".$plusminClass."'>".$rs['plusmin']."</td>
                          <td align=right><font size=-1>".number_format($rs['jumlah'], 0, ',', '.')."</font></td>
                          <td align=right><font size=-1>".number_format($rs['totalharga'], 0, ',', '.')."</font></td>
                          <td><font size=-1>".$rs['nama_proses']."</font></td>
						  <td style='text-align:center;'>";
                          // Bukti kas - parse JSON or legacy single file
                          $buktiArr = [];
                          if (!empty($rs['bukti_kas'])) {
                              $decoded = json_decode($rs['bukti_kas'], true);
                              $buktiArr = is_array($decoded) ? $decoded : [$rs['bukti_kas']];
                          }
                          if (count($buktiArr) > 0) {
                              $buktiJson = htmlspecialchars(json_encode($buktiArr), ENT_QUOTES);
                              echo "<button type='button' class='btn btn-info btn-xs' onclick='viewBuktiKasGallery(" . $buktiJson . ")'>"
                                 . "<i class='fa fa-image'></i> " . count($buktiArr) . " file"
                                 . "</button>";
                          } else {
                              echo "<span style='color:#ccc;'>-</span>";
                          }
                          echo "</td>
						  <td>";
                          
                          // Status badge
                          if ($isApproved) {
                              echo "<span class='label label-success' style='margin-right:5px;'>Approved</span>";
                          }
                          ?>
                          
                          <?php 
                            // Tombol Edit dan Delete hanya untuk pemilik atau kodjab=1
                            // Jika sudah approved, tombol disabled
                            if ($isOwnerOrAdmin) {
                                if ($isApproved) {
                                    // Approved - buttons disabled
                                    echo "<button class='btn btn-warning btn-xs' disabled title='Sudah disetujui GM'>Edit</button> ";
                                    echo "<button class='btn btn-danger btn-xs' disabled title='Sudah disetujui GM'>Delete</button>";
                                } else {
                                    // Not approved - buttons active
                          ?>
                                <button type="button" class="btn btn-warning btn-xs" 
                                    onclick="openEditModal(
                                        '<?php echo $rs['idkas'];?>',
                                        '<?php echo $rs['notransaksi'];?>',
                                        '<?php echo $rs['tgltransaksi'];?>',
                                        '<?php echo $rs['kodakun'];?>',
                                        '<?php echo addslashes($rs['deskripsi']);?>',
                                        '<?php echo $rs['satuan'];?>',
                                        '<?php echo $rs['plusmin'];?>',
                                        <?php echo floatval($rs['jumlah']);?>,
                                        <?php echo floatval($rs['hargaunit']);?>,
                                        '<?php echo htmlspecialchars($rs['bukti_kas'] ?? '', ENT_QUOTES);?>'
                                    )">
                                    Edit
                                </button>
                                <a href="index.php?par=40&del=Y&id=<?php echo $rs['idkas'];?>" 
                                   class="btn btn-danger btn-xs" 
                                   onclick="return confirm('Yakin hapus item ini?')">
                                   Delete
                                </a>
                          <?php
                                }
                            } else {
                                echo "<button class='btn btn-warning btn-xs' disabled>Edit</button> ";
                                echo "<button class='btn btn-danger btn-xs' disabled>Delete</button>";
                            }
                          ?>
						  </td> 
						  <?php	
						   echo	"</td>
						</tr> ";
						$no++;	
					} 
			   }
			   catch (PDOException $e)	{
				  echo "<tr><td colspan='11' align='center'>Error: ".$e->getMessage()."</td></tr>";
			  }
			 
		  ?> 
			
	  </tbody>
		</table>
	  </div>
	  </section>
	</section>
   
</div> 

<!-- Modal View Bukti Kas (Gallery) -->
<div id="modalBuktiKas" style="display:none; position:fixed; z-index:1050; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.7);">
    <div style="margin:3% auto; max-width:800px; background:#fff; border-radius:8px; padding:20px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
            <h4 style="margin:0;"><i class="fa fa-image"></i> Bukti Transaksi</h4>
            <span style="font-size:28px; cursor:pointer; color:#aaa; font-weight:bold; line-height:1;" onclick="closeBuktiKas()">&times;</span>
        </div>
        <hr style="margin:5px 0 15px;">
        <div id="buktiGallery" style="display:flex; flex-wrap:wrap; gap:10px; justify-content:center;"></div>
    </div>
</div>

<style>
.bukti-upload-area {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 6px 4px;
    border: 2px dashed #ddd;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
    min-height: 50px;
    background: #fafafa;
}
.bukti-upload-area:hover {
    border-color: #2196F3;
    background: #e3f2fd;
}
.bukti-upload-area.has-files {
    border-color: #4CAF50;
    background: #e8f5e9;
}
.bukti-upload-text {
    font-size: 11px;
    color: #999;
    margin-top: 2px;
}
.bukti-gallery-item {
    border: 1px solid #eee;
    border-radius: 6px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: transform 0.2s;
    cursor: pointer;
}
.bukti-gallery-item:hover {
    transform: scale(1.03);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.bukti-gallery-item img {
    width: 200px;
    height: 150px;
    object-fit: cover;
}
.bukti-gallery-pdf {
    width: 200px;
    height: 150px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: #f5f5f5;
    color: #e53935;
}
.bukti-gallery-pdf i {
    font-size: 40px;
    margin-bottom: 5px;
}
.edit-bukti-thumb {
    display: inline-block;
    position: relative;
    margin: 4px;
    border-radius: 4px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.15);
}
.edit-bukti-thumb img {
    width: 60px;
    height: 45px;
    object-fit: cover;
    cursor: pointer;
}
.edit-bukti-thumb .pdf-icon {
    width: 60px;
    height: 45px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #ffebee;
    color: #e53935;
    font-size: 20px;
    cursor: pointer;
}
</style>

<!-- Modal Edit Transaksi -->
<div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-labelledby="editModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="editModalLabel">Edit Transaksi</h4>
            </div>
            <form role="form" method="POST" action="index.php?par=40" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="upd" value="Y">
                    <input type="hidden" name="edit_idkas" id="edit_idkas">
                    
                    <div class="form-group">
                        <label>No. Transaksi</label>
                        <input type="text" class="form-control" id="edit_notransaksi" readonly style="background-color:#f5f5f5;">
                    </div>
                    
                    <div class="form-group">
                        <label>Tanggal Transaksi</label>
                        <input type="date" name="edit_tgltransaksi" id="edit_tgltransaksi" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Akun</label>
                        <select name="edit_kodakun" id="edit_kodakun" class="form-control" required>
                            <option value="">-- Pilih Akun --</option>
                            <?php foreach($akunOptions as $akun): ?>
                                <option value="<?php echo $akun['kodakun']; ?>"><?php echo $akun['kodakun'] . ' - ' . $akun['nmakun']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Deskripsi</label>
                        <input type="text" name="edit_deskripsi" id="edit_deskripsi" class="form-control" placeholder="Deskripsi item...">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Satuan</label>
                                <select name="edit_satuan" id="edit_satuan" class="form-control">
                                    <option value="">--</option>
                                    <option value="Pcs">Pcs</option>
                                    <option value="Unit">Unit</option>
                                    <option value="Rim">Rim</option>
                                    <option value="Pak">Pak</option>
                                    <option value="Dus">Dus</option>
                                    <option value="Liter">Liter</option>
                                    <option value="Kg">Kg</option>
                                    <option value="Org">Org</option>
                                    <option value="Bln">Bln</option>
                                    <option value="Ls">Ls (Lumpsum)</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>+/-</label>
                                <select name="edit_plusmin" id="edit_plusmin" class="form-control">
                                    <option value="-">- (Keluar)</option>
                                    <option value="+">+ (Masuk)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Jumlah</label>
                                <input type="text" name="edit_jumlah" id="edit_jumlah" class="form-control" value="1" onkeyup="maskRibuan(this); hitungTotalEdit()" onchange="hitungTotalEdit()">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Harga Unit</label>
                                <input type="text" name="edit_hargaunit" id="edit_hargaunit" class="form-control" placeholder="0" onkeyup="maskRibuan(this); hitungTotalEdit()" onchange="hitungTotalEdit()">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Total Harga</label>
                                <input type="text" id="edit_totalharga" class="form-control" readonly style="background-color:#f5f5f5;">
                            </div>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="form-group">
                            <label>Upload Bukti / Struk (JPG/PNG/PDF) — bisa lebih dari 1 file</label>
                            <input type="file" name="edit_bukti_kas[]" id="edit_bukti_kas" class="form-control" accept=".jpg,.jpeg,.png,.gif,.pdf" multiple>
                            <div id="edit_bukti_preview" style="margin-top:8px;"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>

<script type="text/javascript">
// ========== MASKING RIBUAN ==========
function formatRibuan(angka) {
    if (!angka && angka !== 0) return '';
    var str = String(angka).replace(/[^0-9]/g, '');
    return str.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function parseRibuan(str) {
    if (!str) return 0;
    return parseFloat(String(str).replace(/\./g, '').replace(',', '.')) || 0;
}

function maskRibuan(el) {
    var pos = el.selectionStart;
    var oldLen = el.value.length;
    var raw = el.value.replace(/[^0-9]/g, '');
    el.value = formatRibuan(raw);
    var newLen = el.value.length;
    var newPos = pos + (newLen - oldLen);
    if (newPos < 0) newPos = 0;
    el.setSelectionRange(newPos, newPos);
}

// Strip titik sebelum submit form
function stripDotsBeforeSubmit(form) {
    var inputs = form.querySelectorAll('.calc-jumlah, .calc-harga, .calc-total');
    inputs.forEach(function(inp) {
        inp.value = inp.value.replace(/\./g, '');
    });
    // Modal edit fields
    var editFields = ['edit_jumlah', 'edit_hargaunit'];
    editFields.forEach(function(id) {
        var el = document.getElementById(id);
        if (el) el.value = el.value.replace(/\./g, '');
    });
    return true;
}

document.addEventListener('DOMContentLoaded', function() {
    const addBtn = document.getElementById('add_item_btn');
    if (addBtn) { 
        addBtn.addEventListener('click', addItemRow);
    }
    
    updateRemoveButtons();

    // Attach stripDots ke semua form
    var forms = document.querySelectorAll('form[method="POST"]');
    forms.forEach(function(f) {
        f.addEventListener('submit', function() {
            stripDotsBeforeSubmit(f);
        });
    });
});

var _buktiItemCounter = 1; // Start at 1 since first row is 0

function addItemRow() {
    const tableBody = document.getElementById('item_table_body');
    const newRow = document.createElement('tr');
    var itemIndex = _buktiItemCounter++;

    newRow.innerHTML = `
        <td>
            <select name="kodakun[]" class="form-control" required>
                <option value="">-- Pilih Akun --</option>
                <?php
                $qa = $conn->prepare("SELECT kodakun, nmakun FROM takun WHERE tipe = 'D' ORDER BY kodakun");
                $qa->execute();
                while($ra = $qa->fetch()) {
                    echo "<option value='".$ra['kodakun']."'>".$ra['kodakun']." - ".$ra['nmakun']."</option>";
                }
                ?>
            </select>
        </td>
        <td><input type="text" name="deskripsi[]" class="form-control" placeholder="Deskripsi item..."></td>
        <td>
            <select name="satuan[]" class="form-control">
                <option value="">--</option>
                <option value="Pcs">Pcs</option>
                <option value="Unit">Unit</option>
                <option value="Rim">Rim</option>
                <option value="Pak">Pak</option>
                <option value="Dus">Dus</option>
                <option value="Liter">Liter</option>
                <option value="Kg">Kg</option>
                <option value="Org">Org</option>
                <option value="Bln">Bln</option>
                <option value="Ls">Ls (Lumpsum)</option>
            </select>
        </td>
        <td>
            <select name="plusmin[]" class="form-control">
                <option value="-">- (Keluar)</option>
                <option value="+">+ (Masuk)</option>
            </select>
        </td>
        <td><input type="text" name="jumlah[]" class="form-control calc-jumlah" value="1" onkeyup="maskRibuan(this); hitungTotal(this)" onchange="hitungTotal(this)"></td>
        <td><input type="text" name="hargaunit[]" class="form-control calc-harga" placeholder="0" onkeyup="maskRibuan(this); hitungTotal(this)" onchange="hitungTotal(this)"></td>
        <td><input type="text" name="totalharga[]" class="form-control calc-total" placeholder="0" readonly style="background-color:#f5f5f5;"></td>
        <td>
            <label class="bukti-upload-area" data-index="${itemIndex}">
                <i class="fa fa-cloud-upload" style="font-size:18px; color:#999;"></i>
                <span class="bukti-upload-text">Pilih File</span>
                <input type="file" name="bukti_kas_${itemIndex}[]" multiple accept=".jpg,.jpeg,.png,.gif,.pdf" style="display:none;" onchange="updateBuktiLabel(this)">
                <small class="bukti-count" style="display:none; color:green; font-weight:bold;"></small>
            </label>
        </td>
        <td>
            <button type="button" class="btn btn-danger btn-sm" onclick="removeItemRow(this)">
                <i class="fa fa-minus"></i>
            </button>
        </td>
    `;
    
    tableBody.appendChild(newRow);
    updateRemoveButtons();
}

function removeItemRow(button) {
    const row = button.parentNode.parentNode;
    row.parentNode.removeChild(row);
    updateRemoveButtons();
}

function updateRemoveButtons() {
    const tableBody = document.getElementById('item_table_body');
    const rows = tableBody.getElementsByTagName('tr');
    
    if (rows.length === 1) {
        const firstButton = rows[0].querySelector('.btn-danger');
        if (firstButton) {
            firstButton.disabled = true;
        }
    } else {
        for (let i = 0; i < rows.length; i++) {
            const button = rows[i].querySelector('.btn-danger');
            if (button) {
                button.disabled = false;
            }
        }
    }
}

// Fungsi hitung Total Harga otomatis
function hitungTotal(el) {
    var row = el.closest('tr');
    var jumlah = parseRibuan(row.querySelector('.calc-jumlah').value);
    var harga = parseRibuan(row.querySelector('.calc-harga').value);
    var total = jumlah * harga;
    row.querySelector('.calc-total').value = formatRibuan(total);
}

// Fungsi buka modal edit dan isi data
function openEditModal(idkas, notransaksi, tgltransaksi, kodakun, deskripsi, satuan, plusmin, jumlah, hargaunit, buktiKas) {
    document.getElementById('edit_idkas').value = idkas;
    document.getElementById('edit_notransaksi').value = notransaksi;
    document.getElementById('edit_tgltransaksi').value = tgltransaksi;
    document.getElementById('edit_kodakun').value = kodakun;
    document.getElementById('edit_deskripsi').value = deskripsi;
    document.getElementById('edit_satuan').value = satuan;
    document.getElementById('edit_plusmin').value = plusmin;
    document.getElementById('edit_jumlah').value = formatRibuan(jumlah || 1);
    document.getElementById('edit_hargaunit').value = formatRibuan(parseFloat(hargaunit) || 0);
    
    // Reset file input
    document.getElementById('edit_bukti_kas').value = '';
    
    // Show existing bukti thumbnails
    var previewDiv = document.getElementById('edit_bukti_preview');
    previewDiv.innerHTML = '';
    if (buktiKas && buktiKas.length > 0) {
        try {
            var files = JSON.parse(buktiKas);
            if (Array.isArray(files) && files.length > 0) {
                var label = document.createElement('div');
                label.innerHTML = '<small style="color:green;"><i class="fa fa-check-circle"></i> <b>' + files.length + ' file</b> sudah diupload sebelumnya (upload baru akan ditambahkan):</small>';
                previewDiv.appendChild(label);
                var thumbDiv = document.createElement('div');
                thumbDiv.style.marginTop = '6px';
                files.forEach(function(f) {
                    var ext = f.split('.').pop().toLowerCase();
                    var wrapper = document.createElement('span');
                    wrapper.className = 'edit-bukti-thumb';
                    if (ext === 'pdf') {
                        wrapper.innerHTML = '<span class="pdf-icon" onclick="viewBuktiKasGallery([\'' + f + '\'])"><i class="fa fa-file-pdf-o"></i></span>';
                    } else {
                        wrapper.innerHTML = '<img src="uploads/bukti_kas/' + f + '" onclick="viewBuktiKasGallery([\'' + f + '\'])" title="' + f + '">';
                    }
                    thumbDiv.appendChild(wrapper);
                });
                previewDiv.appendChild(thumbDiv);
            }
        } catch(e) {
            // Legacy single file
            var label = document.createElement('div');
            label.innerHTML = '<small style="color:green;"><i class="fa fa-check-circle"></i> 1 file sudah diupload</small>';
            previewDiv.appendChild(label);
        }
    }
    
    // Hitung total
    hitungTotalEdit();
    
    // Tampilkan modal
    $('#editModal').modal('show');
}

// Update bukti upload label
function updateBuktiLabel(input) {
    var area = input.closest('.bukti-upload-area');
    var text = area.querySelector('.bukti-upload-text');
    var count = area.querySelector('.bukti-count');
    var fileCount = input.files.length;
    
    if (fileCount > 0) {
        area.classList.add('has-files');
        text.textContent = '';
        count.style.display = 'block';
        count.textContent = fileCount + ' file dipilih';
        area.querySelector('i').style.color = '#4CAF50';
    } else {
        area.classList.remove('has-files');
        text.textContent = 'Pilih File';
        count.style.display = 'none';
        area.querySelector('i').style.color = '#999';
    }
}

// View Bukti Kas Gallery
function viewBuktiKasGallery(files) {
    var gallery = document.getElementById('buktiGallery');
    gallery.innerHTML = '';
    
    files.forEach(function(filename) {
        var ext = filename.split('.').pop().toLowerCase();
        var item = document.createElement('div');
        item.className = 'bukti-gallery-item';
        
        if (ext === 'pdf') {
            item.innerHTML = '<a href="uploads/bukti_kas/' + filename + '" target="_blank" class="bukti-gallery-pdf">' +
                '<i class="fa fa-file-pdf-o"></i>' +
                '<small>' + filename.substring(0, 20) + '...</small>' +
                '</a>';
        } else {
            item.innerHTML = '<a href="uploads/bukti_kas/' + filename + '" target="_blank">' +
                '<img src="uploads/bukti_kas/' + filename + '" alt="Bukti">' +
                '</a>';
        }
        gallery.appendChild(item);
    });
    
    document.getElementById('modalBuktiKas').style.display = 'block';
}

function closeBuktiKas() {
    document.getElementById('modalBuktiKas').style.display = 'none';
    document.getElementById('buktiGallery').innerHTML = '';
}

// Close bukti modal on outside click
window.addEventListener('click', function(event) {
    var modal = document.getElementById('modalBuktiKas');
    if (event.target == modal) {
        closeBuktiKas();
    }
});

// Fungsi hitung total di modal edit
function hitungTotalEdit() {
    var jumlah = parseRibuan(document.getElementById('edit_jumlah').value);
    var harga = parseRibuan(document.getElementById('edit_hargaunit').value);
    var total = jumlah * harga;
    document.getElementById('edit_totalharga').value = formatRibuan(total);
}

// DataTables
$(document).ready(function () {
    const tableId = '#tabel_kas';

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
});
</script>

</body>

</html>
