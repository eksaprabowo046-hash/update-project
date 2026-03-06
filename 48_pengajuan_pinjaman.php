<?php
// 
include "dbase.php";

// AJAX Handler untuk hapus pinjaman
if (isset($_POST['ajax_hapus_pinjaman'])) {
    session_start();
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['DEFAULT_IDUSER'])) {
        echo json_encode(['success' => false, 'message' => 'Session tidak valid']);
        exit;
    }
    
    $id = (int) $_POST['id'];
    $iduser = $_SESSION['DEFAULT_IDUSER'];
    
    try {
        // Hanya Direktur/GM yang bisa akses, hapus semua yg Pending
        $sql = $conn->prepare("DELETE FROM tpinjaman WHERE id = :id AND status_approval = 'Pending'");
        $sql->execute([':id' => $id]);
        
        if ($sql->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Pengajuan berhasil dihapus']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan atau tidak bisa dihapus']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// AJAX Handler untuk update/edit pinjaman
if (isset($_POST['ajax_update_pinjaman'])) {
    session_start();
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['DEFAULT_IDUSER'])) {
        echo json_encode(['success' => false, 'message' => 'Session tidak valid']);
        exit;
    }
    
    $id = (int) $_POST['id'];
    $jumlah_sudah_dibayar = (float) str_replace('.', '', $_POST['jumlah_sudah_dibayar'] ?? '0');
    $tenor_baru = (int) ($_POST['tenor_baru'] ?? 1);
    $cicilan_baru = (float) str_replace('.', '', $_POST['cicilan_baru'] ?? '0');
    $status_lunas = trim($_POST['status_lunas'] ?? 'Belum');
    
    if ($tenor_baru < 1 && $status_lunas !== 'Lunas') {
        echo json_encode(['success' => false, 'message' => 'Tenor harus minimal 1 bulan']);
        exit;
    }
    
    try {
        // Ambil data pinjaman
        $q = $conn->prepare("SELECT * FROM tpinjaman WHERE id = :id");
        $q->execute([':id' => $id]);
        $data = $q->fetch(PDO::FETCH_ASSOC);
        
        if (!$data) {
            echo json_encode(['success' => false, 'message' => 'Data pinjaman tidak ditemukan']);
            exit;
        }
        
        $nominal = $data['nominal'];
        $sisa = $nominal - $jumlah_sudah_dibayar;
        if ($sisa < 0) $sisa = 0;
        
        // Jika sisa = 0, otomatis lunas
        if ($sisa <= 0) {
            $status_lunas = 'Lunas';
            $tenor_baru = 0;
            $cicilan_baru = 0;
        }
        
        // Hitung periode akhir baru
        $periode_akhir_baru = $data['periode_akhir'];
        $pa = $data['periode_awal'] ?? '';
        if (!empty($pa) && $tenor_baru > 0) {
            $parts = explode('-', $pa);
            $thn = (int) $parts[0];
            $bln = (int) $parts[1];
            $bulan_sudah_bayar = ($data['cicilan_perbulan'] > 0) ? round($jumlah_sudah_dibayar / $data['cicilan_perbulan']) : 0;
            $bln += $bulan_sudah_bayar + $tenor_baru - 1;
            while ($bln > 12) { $bln -= 12; $thn++; }
            $periode_akhir_baru = $thn . '-' . str_pad($bln, 2, '0', STR_PAD_LEFT);
        }
        
        // Update — nominal TETAP, hanya tenor, cicilan, dan status
        $stmt = $conn->prepare("
            UPDATE tpinjaman 
            SET tenor = :tenor, cicilan_perbulan = :cicilan, status_lunas = :lunas,
                periode_akhir = :pb
            WHERE id = :id
        ");
        $stmt->execute([
            ':tenor'   => $tenor_baru,
            ':cicilan' => $cicilan_baru,
            ':lunas'   => $status_lunas,
            ':pb'      => $periode_akhir_baru,
            ':id'      => $id
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Data pinjaman berhasil diupdate']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

include "islogin.php";
$pesan = "";
$iduser = $_SESSION['DEFAULT_IDUSER'];
$kodjab = isset($_SESSION['DEFAULT_KODJAB']) ? $_SESSION['DEFAULT_KODJAB'] : 0;

// Cek akses: Direktur/GM langsung boleh, selain itu cek hak akses 'Pinjaman'
$pinjaman_allowed = ($kodjab == 1 || $kodjab == 2);
if (!$pinjaman_allowed) {
    try {
        $cek_akses = $conn->prepare("SELECT aktif FROM tbl_hak_akses WHERE iduser = ? AND menu_nama = 'Pinjaman'");
        $cek_akses->execute([$iduser]);
        $row_akses = $cek_akses->fetch(PDO::FETCH_ASSOC);
        if ($row_akses && $row_akses['aktif'] == 1) {
            $pinjaman_allowed = true;
        }
    } catch (PDOException $e) {}
}

if (!$pinjaman_allowed) {
    echo "<script>alert('Anda tidak memiliki akses ke halaman ini!'); window.location='index.php';</script>";
    exit;
}

// Auto-alter: tambah kolom periode_awal dan periode_akhir jika belum ada
try {
    $cekPA = $conn->query("SHOW COLUMNS FROM tpinjaman LIKE 'periode_awal'");
    if ($cekPA->rowCount() == 0) {
        $conn->exec("ALTER TABLE tpinjaman ADD COLUMN periode_awal VARCHAR(7) NULL AFTER cicilan_perbulan");
    }
    $cekPB = $conn->query("SHOW COLUMNS FROM tpinjaman LIKE 'periode_akhir'");
    if ($cekPB->rowCount() == 0) {
        $conn->exec("ALTER TABLE tpinjaman ADD COLUMN periode_akhir VARCHAR(7) NULL AFTER periode_awal");
    }
    $cekJD = $conn->query("SHOW COLUMNS FROM tpinjaman LIKE 'jumlah_dibayar'");
    if ($cekJD->rowCount() == 0) {
        $conn->exec("ALTER TABLE tpinjaman ADD COLUMN jumlah_dibayar DECIMAL(15,2) DEFAULT 0 AFTER periode_akhir");
    }
    $cekSP = $conn->query("SHOW COLUMNS FROM tpinjaman LIKE 'sisa_pinjaman'");
    if ($cekSP->rowCount() == 0) {
        $conn->exec("ALTER TABLE tpinjaman ADD COLUMN sisa_pinjaman DECIMAL(15,2) DEFAULT 0 AFTER jumlah_dibayar");
    }
} catch (PDOException $e) {}

// Fetch data jabatan user untuk auto-fill (format JSON untuk JavaScript)
$userJabatanMap = [];
try {
    $qjab = $conn->prepare("
        SELECT r.iduser, r.kodjab, COALESCE(j.nama_jabatan, 
            CASE r.kodjab WHEN 1 THEN 'Admin / Pimpinan' WHEN 2 THEN 'General Manager' WHEN 3 THEN 'Staff / User' ELSE 'Belum diatur' END
        ) AS nama_jabatan 
        FROM ruser r 
        LEFT JOIN tbl_jabatan j ON r.kodjab = j.kodjab 
        WHERE r.stsaktif = 1
    ");
    $qjab->execute();
    while ($rjab = $qjab->fetch(PDO::FETCH_ASSOC)) {
        $userJabatanMap[$rjab['iduser']] = $rjab['nama_jabatan'];
    }
} catch (PDOException $e) {}

// Ambil jabatan user yang login
$jabatan_login = isset($userJabatanMap[$iduser]) ? $userJabatanMap[$iduser] : '';

// Proses simpan atau update pengajuan pinjaman
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_pinjaman'])) {
    $edit_id   = (int) ($_POST['edit_id'] ?? 0);
    $nominal   = str_replace('.', '', $_POST['nominal'] ?? '0');
    $keperluan = trim($_POST['keperluan'] ?? '');
    $tenor     = (int) ($_POST['tenor'] ?? 1);
    $alamat    = trim($_POST['alamat'] ?? '');
    $jabatan   = trim($_POST['jabatan_pemohon'] ?? '');
    $no_telp   = trim($_POST['no_telp'] ?? '');
    $periode_awal  = trim($_POST['periode_awal'] ?? '');
    $periode_akhir = trim($_POST['periode_akhir'] ?? '');
    $jumlah_dibayar = (float) str_replace('.', '', $_POST['jumlah_dibayar_awal'] ?? '0');
    
    // Tentukan pemohon (dipilih dari dropdown oleh Direktur/GM)
    $pemohon = !empty($_POST['pemohon']) ? $_POST['pemohon'] : $iduser;
    
    if (!$nominal || $nominal <= 0 || !$keperluan || $tenor < 1) {
        $pesan = "<font color='red'>Semua field wajib diisi dengan benar!</font>";
    } else {
        // Kalkulasi sisa & status lunas dulu
        $sisa_pinjaman = $nominal - $jumlah_dibayar;
        if ($sisa_pinjaman < 0) $sisa_pinjaman = 0;
        $status_lunas = ($sisa_pinjaman <= 0) ? 'Lunas' : 'Belum';
        
        // Cicilan tetap dihitung dari nominal awal / tenor awal
        $cicilan = ($tenor > 0) ? round($nominal / $tenor, 2) : 0;
        
        try {
            if ($edit_id > 0) {
                // Proses Update
                $stmt = $conn->prepare("
                    UPDATE tpinjaman 
                    SET iduser_pemohon = :pemohon, alamat = :alamat, jabatan_pemohon = :jabatan, no_telp = :telp, 
                        nominal = :nominal, keperluan = :keperluan, tenor = :tenor, cicilan_perbulan = :cicilan, 
                        periode_awal = :periode_awal, periode_akhir = :periode_akhir, 
                        jumlah_dibayar = :jumlah_dibayar, sisa_pinjaman = :sisa_pinjaman, status_lunas = :status_lunas
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':pemohon'       => $pemohon,
                    ':alamat'        => $alamat,
                    ':jabatan'       => $jabatan,
                    ':telp'          => $no_telp,
                    ':nominal'       => $nominal,
                    ':keperluan'     => $keperluan,
                    ':tenor'         => $tenor,
                    ':cicilan'       => $cicilan,
                    ':periode_awal'  => $periode_awal,
                    ':periode_akhir' => $periode_akhir,
                    ':jumlah_dibayar'=> $jumlah_dibayar,
                    ':sisa_pinjaman' => $sisa_pinjaman,
                    ':status_lunas'  => $status_lunas,
                    ':id'            => $edit_id
                ]);
                $pesan = "<font color='blue'>Data pinjaman berhasil diupdate!</font>";
            } else {
                // Proses Insert (Baru)
                $stmt = $conn->prepare("
                    INSERT INTO tpinjaman 
                    (iduser_pemohon, alamat, jabatan_pemohon, no_telp, nominal, keperluan, tenor, cicilan_perbulan, periode_awal, periode_akhir, jumlah_dibayar, sisa_pinjaman, status_lunas, tgl_pengajuan) 
                    VALUES 
                    (:pemohon, :alamat, :jabatan, :telp, :nominal, :keperluan, :tenor, :cicilan, :periode_awal, :periode_akhir, :jumlah_dibayar, :sisa_pinjaman, :status_lunas, NOW())
                ");
                $stmt->execute([
                    ':pemohon'       => $pemohon,
                    ':alamat'        => $alamat,
                    ':jabatan'       => $jabatan,
                    ':telp'          => $no_telp,
                    ':nominal'       => $nominal,
                    ':keperluan'     => $keperluan,
                    ':tenor'         => $tenor,
                    ':cicilan'       => $cicilan,
                    ':periode_awal'  => $periode_awal,
                    ':periode_akhir' => $periode_akhir,
                    ':jumlah_dibayar'=> $jumlah_dibayar,
                    ':sisa_pinjaman' => $sisa_pinjaman,
                    ':status_lunas'  => $status_lunas
                ]);
                $pesan = "<font color='blue'>Pengajuan pinjaman berhasil disimpan!</font>";
            }
        } catch (PDOException $e) {
            $pesan = "<font color='red'>Error: " . $e->getMessage() . "</font>";
        }
    }
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
      <li><i class="fa fa-home"></i> PENGAJUAN PINJAMAN</li> 
    </ol> 
    <section class="panel">
      <header class="panel-heading">
          
          <form role="form" method="POST" action="index.php?par=48" id="formPinjaman"> 
              <input type="hidden" name="simpan_pinjaman" value="1">
              <input type="hidden" name="edit_id" id="edit_id" value="">
              <input type="hidden" id="edit_mode" value="0">
              
              <div class="form-group col-xs-12 col-sm-3">
                  <label>Pemohon <span style="color:red;">*</span></label>
                  <select name="pemohon" id="pemohon_select" class="form-control" required onchange="onPemohonChange(this.value)">
                      <option value="">-- Pilih Pemohon --</option>
                      <?php
                      $qp = $conn->prepare("SELECT iduser, nama FROM ruser WHERE stsaktif = 1 ORDER BY nama ASC");
                      $qp->execute();
                      while($rsp = $qp->fetch()) {
                          echo "<option value='".$rsp['iduser']."'>".htmlspecialchars($rsp['nama'])."</option>";
                      }
                      ?>
                  </select>
              </div>
              
              <div class="form-group col-xs-12 col-sm-3">
                  <label>Jabatan <span style="color:red;">*</span></label>
                  <input type="text" name="jabatan_pemohon" id="jabatan_pemohon" class="form-control" value="<?php echo htmlspecialchars($jabatan_login); ?>" readonly style="background:#f5f5f5;">
              </div>
              
              <div class="form-group col-xs-12 col-sm-3">
                  <label>No. Telp <span style="color:red;">*</span></label>
                  <input type="text" name="no_telp" class="form-control" placeholder="08xxxxxxxxxx" required>
              </div>
              
              <div class="form-group col-xs-12 col-sm-3">
                  <label>Nominal Pinjaman <span style="color:red;">*</span></label>
                  <div class="input-group">
                      <span class="input-group-addon">Rp</span>
                      <input type="text" name="nominal" id="nominal" class="form-control input-ribuan" placeholder="0" required autocomplete="off">
                  </div>
              </div>
              
              <div class="form-group col-xs-12 col-sm-3" id="field_sudah_bayar" style="display:none;">
                  <label>Jumlah yang sudah dibayar</label>
                  <div class="input-group">
                      <span class="input-group-addon">Rp</span>
                      <input type="text" id="jumlah_sudah_dibayar" class="form-control input-ribuan" placeholder="0" autocomplete="off" onkeyup="hitungSisaPinjaman()">
                  </div>
                  <small id="info_sisa" class="text-success" style="font-weight:bold;"></small>
              </div>
              
              <div class="clearfix"></div>
              
              <div class="form-group col-xs-12 col-sm-2">
                  <label>Tenor Cicilan (bulan) <span style="color:red;">*</span></label>
                  <input type="number" name="tenor" id="tenor" class="form-control" value="1" min="1" max="60" required onchange="hitungCicilan(); hitungPeriodeAkhir(); hitungKurang();" onkeyup="hitungCicilan(); hitungPeriodeAkhir(); hitungKurang();">
              </div>
              
              <div class="form-group col-xs-12 col-sm-3">
                  <label>Cicilan/Bulan (estimasi)</label>
                  <input type="text" id="cicilan_preview" class="form-control" readonly style="background:#f5f5f5; font-weight:bold;">
              </div>
              
              <div class="form-group col-xs-12 col-sm-2">
                  <label>Periode Awal <span style="color:red;">*</span></label>
                  <input type="month" name="periode_awal" id="periode_awal" class="form-control" value="<?php echo date('Y-m'); ?>" required onchange="hitungPeriodeAkhir(); hitungKurang();">
              </div>
              
              <div class="form-group col-xs-12 col-sm-2">
                  <label>Periode Berakhir</label>
                  <input type="month" name="periode_akhir" id="periode_akhir" class="form-control" readonly style="background:#f5f5f5; font-weight:bold;">
              </div>
              
              <div class="form-group col-xs-12 col-sm-3">
                  <label>Jumlah yang sudah dibayar</label>
                  <div class="input-group">
                      <span class="input-group-addon">Rp</span>
                      <input type="text" name="jumlah_dibayar_awal" id="jumlah_dibayar_awal" class="form-control input-ribuan" placeholder="0" autocomplete="off">
                  </div>
              </div>
              
              <div class="clearfix"></div>
              
              <div class="form-group col-xs-12 col-sm-3">
                  <label>Kurang Bayar (Sisa)</label>
                  <input type="text" id="kurang_bayar_preview" class="form-control" readonly style="background:#f5f5f5; font-weight:bold;">
              </div>
              
              <div class="form-group col-xs-12 col-sm-3">
                  <label>Tenor Berjalan</label>
                  <input type="text" id="tenor_berjalan_preview" class="form-control" readonly style="background:#f5f5f5; font-weight:bold;">
              </div>
              
              <div class="form-group col-xs-12 col-sm-3">
                  <label>Sisa Tenor</label>
                  <input type="text" id="sisa_tenor_preview" class="form-control" readonly style="background:#f5f5f5; font-weight:bold;">
              </div>
              
              <div class="form-group col-xs-12 col-sm-3">
                  <label>Lunas Pada (Bulan)</label>
                  <input type="text" id="bulan_lunas_preview" class="form-control" readonly style="background:#f5f5f5; font-weight:bold;">
              </div>
              
              <div class="clearfix"></div>
              
              <div class="form-group col-xs-12 col-sm-6">
                  <label>Alamat <span style="color:red;">*</span></label>
                  <textarea name="alamat" class="form-control" rows="3" placeholder="Alamat lengkap pemohon..." required></textarea>
              </div>

              
              <div class="form-group col-xs-12 col-sm-6">
                  <label>Keperluan / Alasan <span style="color:red;">*</span></label>
                  <textarea name="keperluan" class="form-control" rows="3" placeholder="Jelaskan keperluan pinjaman..." required></textarea>
              </div>
              
              <div class="clearfix"></div>
              
              <div class="form-group col-xs-12 col-sm-12" style="display:flex; justify-content:flex-end; gap:5px;">
                  <button type="submit" class="btn btn-primary" id="btn_submit">
                      Ajukan Pinjaman
                  </button>
                  <button type="button" class="btn btn-success" id="btn_update" style="display:none;" onclick="simpanEditPinjaman()">
                      <i class="fa fa-save"></i> Update Pinjaman
                  </button>
                  <button type="button" class="btn btn-warning" id="btn_cancel_edit" style="display:none;" onclick="batalEdit()">
                      <i class="fa fa-times"></i> Batal Edit
                  </button>
                  <button type="button" class="btn btn-danger" onclick="resetForm()">
                      Reset
                  </button>
              </div>
          </form>
          <div class="clearfix"></div>
         
          <h4><?php echo $pesan;?></h4>
      </header>
      <section class="content">
    <div align="center"><strong>DAFTAR PENGAJUAN PINJAMAN</strong></div>

    <div class="box-body">
    <div class="table-responsive">
    <table id="tabel_pinjaman" class="table table-bordered table-striped table-hover"> 
        <thead> 
            <tr>
              <th>#</th>
              <th>Pemohon</th>
              <th>Tanggal</th>
              <th>Nominal</th>
              <th>Tenor Awal</th>
              <th>Tenor Berjalan</th>
              <th>Cicilan/Bulan</th>
              <th>Periode</th>
              <th>Keperluan</th>
              <th>Status</th>
              <th>Catatan GM</th>
              <th>Lunas</th>
              <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
        <?php
            try {
                // Direktur/GM: lihat semua data pinjaman
                $strsql = "SELECT p.*, u.nama AS nama_pemohon 
                           FROM tpinjaman p 
                           LEFT JOIN ruser u ON p.iduser_pemohon = u.iduser 
                           ORDER BY p.tgl_pengajuan DESC";
                $sql = $conn->prepare($strsql);
                $sql->execute();
                
                $no = 1;
                while ($rs = $sql->fetch(PDO::FETCH_ASSOC)) {
                    // Status badge
                    $status = $rs['status_approval'];
                    if ($status == 'Approved') {
                        $badge = '<span class="label label-success">Approved</span>';
                    } elseif ($status == 'Rejected') {
                        $badge = '<span class="label label-danger">Rejected</span>';
                    } else {
                        $badge = '<span class="label label-warning">Pending</span>';
                    }
                    
                    // Status lunas
                    $lunas = $rs['status_lunas'];
                    if ($lunas == 'Lunas') {
                        $badgeLunas = '<span class="label label-success">Lunas</span>';
                    } else {
                        $badgeLunas = '<span class="label label-default">Belum</span>';
                    }
                    
                    echo "<tr id='row-pinjaman-".$rs['id']."'>";
                    echo "<td align='center'>".$no."</td>";
                    echo "<td>".htmlspecialchars($rs['nama_pemohon'])."</td>";
                    echo "<td>".date('d/m/Y', strtotime($rs['tgl_pengajuan']))."</td>";
                    $tenor_berjalan = 0;
                    if ($rs['cicilan_perbulan'] > 0) {
                        $tenor_berjalan = floor(($rs['jumlah_dibayar'] ?? 0) / $rs['cicilan_perbulan']);
                    }
                    echo "<td align='right'>Rp ".number_format($rs['nominal'], 0, ',', '.')."</td>";
                    echo "<td align='center'>".$rs['tenor']." bulan</td>";
                    echo "<td align='center'>".$tenor_berjalan." bulan</td>";
                    echo "<td align='right'>Rp ".number_format($rs['cicilan_perbulan'], 0, ',', '.')."</td>";
                    
                    // Tampilkan periode
                    $pa = isset($rs['periode_awal']) && $rs['periode_awal'] ? $rs['periode_awal'] : '-';
                    $pb = isset($rs['periode_akhir']) && $rs['periode_akhir'] ? $rs['periode_akhir'] : '-';
                    if ($pa !== '-' && $pb !== '-') {
                        echo "<td align='center'><small>".$pa." s/d ".$pb."</small></td>";
                    } else {
                        echo "<td align='center'>-</td>";
                    }
                    
                    echo "<td>".htmlspecialchars($rs['keperluan'])."</td>";
                    echo "<td align='center'>".$badge."</td>";
                    echo "<td>".htmlspecialchars($rs['catatan_approval'] ?? '')."</td>";
                    echo "<td align='center'>".$badgeLunas."</td>";
                    
                    // Aksi
                    echo "<td align='center'>";
                    $aksi = [];
                    if ($status == 'Pending') {
                        $aksi[] = "<button type='button' class='btn btn-danger btn-sm' onclick='hapusPinjaman(".$rs['id'].")'><i class='fa fa-trash'></i> Hapus</button>";
                    }
                    $dibayar = (float)($rs['jumlah_dibayar'] ?? 0);
                    $json_data = json_encode([
                        'id' => $rs['id'],
                        'pemohon' => $rs['iduser_pemohon'],
                        'jabatan' => $rs['jabatan_pemohon'],
                        'telp' => $rs['no_telp'],
                        'nominal' => $rs['nominal'],
                        'tenor' => $rs['tenor'],
                        'periode_awal' => $rs['periode_awal'],
                        'alamat' => $rs['alamat'],
                        'keperluan' => $rs['keperluan'],
                        'jumlah_dibayar' => $dibayar
                    ]);
                    $aksi[] = "<button type='button' class='btn btn-warning btn-sm' onclick='editPinjamanForm(".htmlspecialchars($json_data, ENT_QUOTES).")'>Edit</button>";
                    $aksi[] = "<a href='48_cetak_pinjaman.php?id=".$rs['id']."' target='_blank' class='btn btn-info btn-sm'>Cetak</a>";
                    echo implode(' ', $aksi);
                    echo "</td>";
                    
                    echo "</tr>";
                    $no++;
                }
            } catch (PDOException $e) {
                echo "<tr><td colspan='13' class='text-center'>Error: " . $e->getMessage() . "</td></tr>";
            }
        ?> 
        </tbody>
    </table>
    </div>
    </div>
    </section>
</div> 

<script>
// ========== MASKING RIBUAN ==========
function formatRibuan(angka) {
    if (!angka && angka !== 0) return '';
    var str = String(angka).replace(/[^0-9]/g, '');
    if (!str || str === '0') return str;
    str = str.replace(/^0+/, '') || '0';
    return str.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function parseRibuan(str) {
    if (!str) return 0;
    return parseFloat(String(str).replace(/\./g, '')) || 0;
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

function hitungCicilan() {
    var nominal = parseRibuan(document.getElementById('nominal').value);
    var tenor = parseInt(document.getElementById('tenor').value) || 1;
    if (tenor < 1) tenor = 1;
    var cicilan = Math.round(nominal / tenor);
    document.getElementById('cicilan_preview').value = 'Rp ' + formatRibuan(cicilan);
}

// ========== AUTO-FILL JABATAN ==========
var userJabatanMap = <?php echo json_encode($userJabatanMap); ?>;

function onPemohonChange(iduser) {
    var jabEl = document.getElementById('jabatan_pemohon');
    if (userJabatanMap[iduser]) {
        jabEl.value = userJabatanMap[iduser];
    } else {
        jabEl.value = '';
    }
}

// ========== HITUNG PERIODE AKHIR ==========
function hitungPeriodeAkhir() {
    var periodeAwal = document.getElementById('periode_awal').value; // format: YYYY-MM
    var tenor = parseInt(document.getElementById('tenor').value) || 1;
    if (tenor < 1) tenor = 1;
    
    if (!periodeAwal) {
        document.getElementById('periode_akhir').value = '';
        return;
    }
    
    // Parse tahun dan bulan
    var parts = periodeAwal.split('-');
    var tahun = parseInt(parts[0]);
    var bulan = parseInt(parts[1]);
    
    // Tambahkan tenor (tenor - 1 karena bulan awal sudah dihitung)
    bulan += (tenor - 1);
    
    // Handle overflow bulan
    while (bulan > 12) {
        bulan -= 12;
        tahun++;
    }
    
    // Format kembali ke YYYY-MM
    var bulanStr = bulan < 10 ? '0' + bulan : '' + bulan;
    document.getElementById('periode_akhir').value = tahun + '-' + bulanStr;
}

function hitungKurang() {
    var nominal = parseRibuan(document.getElementById('nominal').value) || 0;
    var dibayar = parseRibuan(document.getElementById('jumlah_dibayar_awal').value) || 0;
    var tenor = parseInt(document.getElementById('tenor').value) || 1;
    if (tenor < 1) tenor = 1;
    
    // Cicilan mengikuti = Nominal / Tenor Awal (Bukan Sisa)
    var cicilan = Math.round(nominal / tenor);
    
    // Kurang Bayar (Sisa Pinjaman)
    var kurang = nominal - dibayar;
    if (kurang < 0) kurang = 0;
    
    document.getElementById('kurang_bayar_preview').value = 'Rp ' + formatRibuan(kurang);
    
    // Hitung Tenor yang Sudah Berjalan (Sudah dibayar berapa bulan)
    var tenorBerjalan = 0;
    var sisaTenor = 0;
    if (cicilan > 0) {
        tenorBerjalan = Math.floor(dibayar / cicilan);
        sisaTenor = Math.ceil(kurang / cicilan);
    }
    
    var elTenorBerjalan = document.getElementById('tenor_berjalan_preview');
    if (elTenorBerjalan) {
        elTenorBerjalan.value = tenorBerjalan + ' bulan';
    }
    document.getElementById('sisa_tenor_preview').value = sisaTenor + ' bulan';
    
    // Hitung estimasi bulan lunas
    var periodeAwal = document.getElementById('periode_awal').value;
    if (periodeAwal && sisaTenor > 0) {
        var parts = periodeAwal.split('-');
        var thn = parseInt(parts[0]);
        var bln = parseInt(parts[1]);
        
        // Bulan berjalan dihitung dari total yang sudah dibayar + sisa
        bln += (tenorBerjalan + sisaTenor - 1);
        
        while (bln > 12) {
            bln -= 12;
            thn++;
        }
        var blnStr = bln < 10 ? '0' + bln : '' + bln;
        
        var monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
        var namaBulan = monthNames[bln - 1];
        
        document.getElementById('bulan_lunas_preview').value = namaBulan + ' ' + thn;
    } else if (sisaTenor === 0 && dibayar > 0) {
        document.getElementById('bulan_lunas_preview').value = 'Sudah Lunas';
    } else {
        document.getElementById('bulan_lunas_preview').value = '-';
    }
}

function hapusPinjaman(id) {
    if (!confirm('Yakin ingin menghapus pengajuan pinjaman ini?')) return;
    
    $.ajax({
        url: '48_pengajuan_pinjaman.php',
        method: 'POST',
        data: { ajax_hapus_pinjaman: 1, id: id },
        dataType: 'json',
        success: function(resp) {
            if (resp.success) {
                $('#row-pinjaman-' + id).fadeOut(300, function() { $(this).remove(); });
            } else {
                alert('Gagal: ' + resp.message);
            }
        },
        error: function() {
            alert('Gagal menghapus data');
        }
    });
}

// ========== EDIT PINJAMAN (AJAX) ==========
function editPinjaman(data) {
    // Set mode edit
    document.getElementById('edit_mode').value = '1';
    document.getElementById('edit_id').value = data.id;
    
    // Populate form
    $('#pemohon_select').val(data.iduser_pemohon).prop('disabled', true);
    onPemohonChange(data.iduser_pemohon);
    document.getElementById('nominal').value = formatRibuan(parseInt(data.nominal) || 0);
    document.getElementById('nominal').readOnly = true;
    document.getElementById('nominal').style.background = '#f5f5f5';
    document.getElementById('tenor').value = data.tenor;
    document.getElementById('periode_awal').value = data.periode_awal || '';
    hitungCicilan();
    hitungPeriodeAkhir();
    
    // Show field sudah bayar (manual input, kosong untuk diisi admin)
    document.getElementById('field_sudah_bayar').style.display = 'block';
    document.getElementById('jumlah_sudah_dibayar').value = '';
    document.getElementById('info_sisa').innerHTML = '';
    
    // Toggle buttons
    document.getElementById('btn_submit').style.display = 'none';
    document.getElementById('btn_update').style.display = 'inline-block';
    document.getElementById('btn_cancel_edit').style.display = 'inline-block';
    
    // Store original data
    window._editData = data;
    
    // Scroll ke form
    document.querySelector('.panel-heading').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function batalEdit() {
    // Reset mode
    document.getElementById('edit_mode').value = '0';
    document.getElementById('edit_id').value = '';
    
    // Reset form
    $('#pemohon_select').prop('disabled', false).val('');
    document.getElementById('jabatan_pemohon').value = '';
    document.getElementById('nominal').value = '';
    document.getElementById('nominal').readOnly = false;
    document.getElementById('nominal').style.background = '';
    document.getElementById('tenor').value = '1';
    document.getElementById('cicilan_preview').value = '';
    document.getElementById('periode_awal').value = '<?php echo date("Y-m"); ?>';
    document.getElementById('periode_akhir').value = '';
    
    // Hide field sudah bayar
    document.getElementById('field_sudah_bayar').style.display = 'none';
    document.getElementById('jumlah_sudah_dibayar').value = '';
    document.getElementById('info_sisa').innerHTML = '';
    
    // Toggle buttons
    document.getElementById('btn_submit').style.display = 'inline-block';
    document.getElementById('btn_update').style.display = 'none';
    document.getElementById('btn_cancel_edit').style.display = 'none';
    
    window._editData = null;
}

function hitungSisaPinjaman() {
    if (!window._editData) return;
    
    var nominal = parseFloat(window._editData.nominal) || 0;
    var cicilanLama = parseFloat(window._editData.cicilan) || 0;
    var sudahBayar = parseRibuan(document.getElementById('jumlah_sudah_dibayar').value);
    
    var sisa = nominal - sudahBayar;
    if (sisa < 0) sisa = 0;
    
    // Tampilkan info sisa
    document.getElementById('info_sisa').innerHTML = 'Sisa: Rp ' + formatRibuan(sisa);
    
    // Hitung tenor baru = sisa / cicilan lama (bulatkan ke atas)
    if (cicilanLama > 0 && sisa > 0) {
        var tenorBaru = Math.ceil(sisa / cicilanLama);
        document.getElementById('tenor').value = tenorBaru;
    } else if (sisa <= 0) {
        document.getElementById('tenor').value = 0;
    }
    
    // Update cicilan preview & periode
    hitungCicilan();
    hitungPeriodeAkhir();
}

function simpanEditPinjaman() {
    var id = document.getElementById('edit_id').value;
    var sudahBayar = parseRibuan(document.getElementById('jumlah_sudah_dibayar').value);
    var tenorBaru = parseInt(document.getElementById('tenor').value) || 0;
    
    // Hitung sisa dan cicilan baru
    var nominal = parseFloat(window._editData.nominal) || 0;
    var sisa = nominal - sudahBayar;
    if (sisa < 0) sisa = 0;
    var cicilanBaru = (tenorBaru > 0) ? Math.round(sisa / tenorBaru) : 0;
    var statusLunas = (sisa <= 0) ? 'Lunas' : 'Belum';
    
    if (!confirm('Yakin ingin mengupdate data pinjaman ini?\n\nSisa Pinjaman: Rp ' + formatRibuan(sisa) + '\nTenor Baru: ' + tenorBaru + ' bulan\nCicilan Baru: Rp ' + formatRibuan(cicilanBaru))) {
        return;
    }
    
    $.ajax({
        url: '48_pengajuan_pinjaman.php',
        method: 'POST',
        data: {
            ajax_update_pinjaman: 1,
            id: id,
            jumlah_sudah_dibayar: sudahBayar,
            tenor_baru: tenorBaru,
            cicilan_baru: cicilanBaru,
            status_lunas: statusLunas
        },
        dataType: 'json',
        success: function(resp) {
            if (resp.success) {
                alert('Data pinjaman berhasil diupdate!');
                location.reload();
            } else {
                alert('Gagal: ' + resp.message);
            }
        },
        error: function() {
            alert('Gagal mengupdate data');
        }
    });
}

function editPinjamanForm(data) {
    $('#edit_id').val(data.id);
    $('#pemohon_select').val(data.pemohon).trigger('change');
    $('input[name="no_telp"]').val(data.telp);
    $('#nominal').val(formatRibuan(Math.round(data.nominal)));
    $('#tenor').val(data.tenor);
    $('input[name="periode_awal"]').val(data.periode_awal);
    $('textarea[name="alamat"]').val(data.alamat);
    $('textarea[name="keperluan"]').val(data.keperluan);
    $('#jumlah_dibayar_awal').val(formatRibuan(Math.round(data.jumlah_dibayar)));
    
    // Hitung ulang cicilan dan periode
    hitungCicilan();
    hitungPeriodeAkhir();
    hitungKurang();
    
    // Ubah teks button
    $('#btn_submit_pinjaman').text('Update Pinjaman');
    
    // Scroll ke form paling atas
    $('html, body').animate({scrollTop: 0}, 'fast');
}

function resetForm() {
    $('#formPinjaman')[0].reset();
    $('#edit_id').val('');
    $('#btn_submit_pinjaman').text('Ajukan Pinjaman');
    document.getElementById('cicilan_preview').value='';
    $('#jumlah_dibayar_awal').val('');
    $('#kurang_bayar_preview').val('');
    $('#tenor_berjalan_preview').val('');
    $('#sisa_tenor_preview').val('');
    $('#bulan_lunas_preview').val('');
    
    var selPemohon = document.getElementById('pemohon_select');
    if (selPemohon && selPemohon.value) {
        onPemohonChange(selPemohon.value);
    }
}

$(document).ready(function() {
    // Masking
    $(document).on('keyup', '.input-ribuan', function() {
        maskRibuan(this);
        hitungCicilan();
        hitungKurang();
    });
    
    // Strip dots sebelum submit
    $('#formPinjaman').on('submit', function() {
        var nominalEl = document.getElementById('nominal');
        nominalEl.value = nominalEl.value.replace(/\./g, '');
    });
    
    // Auto-fill jabatan saat halaman load (untuk user yang login)
    // Trigger onchange untuk pemohon yang sudah terpilih
    var selPemohon = document.getElementById('pemohon_select');
    if (selPemohon && selPemohon.value) {
        onPemohonChange(selPemohon.value);
    }
    
    // Hitung periode akhir saat halaman load
    hitungPeriodeAkhir();
    
    // DataTable
    var tableId = '#tabel_pinjaman';
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
            search: "Cari:",
            lengthMenu: "Tampilkan _MENU_ data",
            info: "Menampilkan _START_ - _END_ dari _TOTAL_ data",
            infoEmpty: "Tidak ada data",
            zeroRecords: "Data tidak ditemukan",
            paginate: { next: ">", previous: "<" }
        }
    });
});
</script>

</body>
</html>
