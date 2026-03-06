<?php
// 
include "dbase.php";

// AJAX Handler untuk simpan adjustment pinjaman
if (isset($_POST['ajax_adjust_pinjaman'])) {
    session_start();
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['DEFAULT_IDUSER'])) {
        echo json_encode(['success' => false, 'message' => 'Session tidak valid']);
        exit;
    }
    
    $id = (int) $_POST['id'];
    $tenor_baru = (int) $_POST['tenor_baru'];
    $sudah_bayar = (int) $_POST['sudah_bayar'];
    $status_lunas = trim($_POST['status_lunas']);
    $catatan_adjust = trim($_POST['catatan_adjust'] ?? '');
    $periode_awal = trim($_POST['periode_awal'] ?? '');
    
    if ($tenor_baru < 1) {
        echo json_encode(['success' => false, 'message' => 'Tenor harus minimal 1 bulan']);
        exit;
    }
    
    try {
        // Ambil data pinjaman
        $q = $conn->prepare("SELECT * FROM tpinjaman WHERE id = :id AND status_approval = 'Approved'");
        $q->execute([':id' => $id]);
        $data = $q->fetch(PDO::FETCH_ASSOC);
        
        if (!$data) {
            echo json_encode(['success' => false, 'message' => 'Data pinjaman tidak ditemukan atau belum disetujui']);
            exit;
        }
        
        // Hitung sisa pinjaman dan cicilan baru
        $nominal_awal = (float) $data['nominal'];
        $sudah_dibayar = (float) $sudah_bayar; // karena sudah dalam nominal uang
        $sisa_pinjaman = $nominal_awal - $sudah_dibayar;
        if ($sisa_pinjaman < 0) $sisa_pinjaman = 0;
        
        $cicilan_baru = ($tenor_baru > 0) ? round($sisa_pinjaman / $tenor_baru, 2) : 0;
        
        // Hitung periode akhir baru (dari periode awal + tenor baru)
        $periode_akhir_baru = '';
        if (!empty($periode_awal)) {
            $parts = explode('-', $periode_awal);
            $thn = (int) $parts[0];
            $bln = (int) $parts[1];
            // Periode akhir = periode awal + tenor baru - 1
            $bln += ($tenor_baru - 1);
            while ($bln > 12) { $bln -= 12; $thn++; }
            $periode_akhir_baru = $thn . '-' . str_pad($bln, 2, '0', STR_PAD_LEFT);
        }
        
        // Update
        $stmt = $conn->prepare("
            UPDATE tpinjaman 
            SET tenor = :tenor, cicilan_perbulan = :cicilan, status_lunas = :lunas, 
                catatan_approval = :catatan, periode_akhir = :pb, jumlah_dibayar = :jd, sisa_pinjaman = :sp
            WHERE id = :id
        ");
        $stmt->execute([
            ':tenor'   => $tenor_baru,
            ':cicilan' => $cicilan_baru,
            ':lunas'   => $status_lunas,
            ':catatan' => $catatan_adjust,
            ':pb'      => $periode_akhir_baru,
            ':jd'      => $sudah_dibayar,
            ':sp'      => $sisa_pinjaman,
            ':id'      => $id
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Adjustment berhasil disimpan']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

include "islogin.php";
$pesan = "";
$iduser = $_SESSION['DEFAULT_IDUSER'];
$kodjab = isset($_SESSION['DEFAULT_KODJAB']) ? $_SESSION['DEFAULT_KODJAB'] : 0;

// Hanya Admin/GM yang bisa akses
if ($kodjab != 1 && $kodjab != 2) {
    echo "<script>alert('Anda tidak memiliki akses ke halaman ini!'); window.location='index.php';</script>";
    return;
}

// Filter
$filter_pegawai = isset($_GET['filter_pegawai']) ? trim($_GET['filter_pegawai']) : '';
$filter_status  = isset($_GET['filter_status']) ? trim($_GET['filter_status']) : '';
$filter_lunas   = isset($_GET['filter_lunas']) ? trim($_GET['filter_lunas']) : '';

// ========== HITUNG SUMMARY ==========
$totalPinjaman = 0;
$totalApproved = 0;
$totalPending = 0;
$totalRejected = 0;
$totalLunas = 0;
$totalNominal = 0;
$totalNominalApproved = 0;

try {
    $swhere = " WHERE 1=1";
    if ($filter_pegawai != '') {
        $swhere .= " AND p.iduser_pemohon = '$filter_pegawai'";
    }
    if ($filter_status != '') {
        $swhere .= " AND p.status_approval = '$filter_status'";
    }
    if ($filter_lunas != '') {
        $swhere .= " AND p.status_lunas = '$filter_lunas'";
    }

    $sql_total = $conn->prepare("SELECT COUNT(*) as total FROM tpinjaman p $swhere");
    $sql_total->execute();
    $totalPinjaman = $sql_total->fetch(PDO::FETCH_ASSOC)['total'];

    $sql_approved = $conn->prepare("SELECT COUNT(*) as total FROM tpinjaman p $swhere AND p.status_approval = 'Approved'");
    $sql_approved->execute();
    $totalApproved = $sql_approved->fetch(PDO::FETCH_ASSOC)['total'];

    $sql_pending = $conn->prepare("SELECT COUNT(*) as total FROM tpinjaman p $swhere AND p.status_approval = 'Pending'");
    $sql_pending->execute();
    $totalPending = $sql_pending->fetch(PDO::FETCH_ASSOC)['total'];

    $sql_rejected = $conn->prepare("SELECT COUNT(*) as total FROM tpinjaman p $swhere AND p.status_approval = 'Rejected'");
    $sql_rejected->execute();
    $totalRejected = $sql_rejected->fetch(PDO::FETCH_ASSOC)['total'];

    $sql_lunas = $conn->prepare("SELECT COUNT(*) as total FROM tpinjaman p $swhere AND p.status_lunas = 'Lunas'");
    $sql_lunas->execute();
    $totalLunas = $sql_lunas->fetch(PDO::FETCH_ASSOC)['total'];

    $sql_nominal = $conn->prepare("SELECT COALESCE(SUM(p.nominal), 0) as total FROM tpinjaman p $swhere");
    $sql_nominal->execute();
    $totalNominal = $sql_nominal->fetch(PDO::FETCH_ASSOC)['total'];

    $sql_nom_app = $conn->prepare("SELECT COALESCE(SUM(p.nominal), 0) as total FROM tpinjaman p $swhere AND p.status_approval = 'Approved'");
    $sql_nom_app->execute();
    $totalNominalApproved = $sql_nom_app->fetch(PDO::FETCH_ASSOC)['total'];

} catch (PDOException $e) {
    // Abaikan error
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
      <li><i class="fa fa-home"></i> LAPORAN PINJAMAN</li> 
    </ol>


    <section class="panel">
      <header class="panel-heading">
        <form role="form" method="GET" action="index.php"> 
            <input type="hidden" name="par" value="52">
            
            <!-- Filter Pegawai -->
            <div class="form-group col-xs-12 col-sm-3">
                <label>Pegawai :</label>
                <select name="filter_pegawai" class="form-control">
                    <option value="">-- Semua Pegawai --</option>
                    <?php
                    $qp = $conn->prepare("SELECT iduser, nama FROM ruser WHERE stsaktif = 1 ORDER BY nik ASC, nama ASC");
                    $qp->execute();
                    while($rsp = $qp->fetch()) {
                        $sel = ($filter_pegawai == $rsp['iduser']) ? 'selected' : '';
                        echo "<option value='".htmlspecialchars($rsp['iduser'])."' $sel>".htmlspecialchars($rsp['nama'])."</option>\n";
                    }
                    ?>
                </select> 
            </div>

            <!-- Filter Status -->
            <div class="form-group col-xs-12 col-sm-2">
                <label>Status Approval :</label>
                <select name="filter_status" class="form-control">
                    <option value="" <?= $filter_status == '' ? 'selected' : '' ?>>-- Semua --</option>
                    <option value="Pending" <?= $filter_status == 'Pending' ? 'selected' : '' ?>>Menunggu</option>
                    <option value="Approved" <?= $filter_status == 'Approved' ? 'selected' : '' ?>>Disetujui</option>
                    <option value="Rejected" <?= $filter_status == 'Rejected' ? 'selected' : '' ?>>Ditolak</option>
                </select> 
            </div>

            <!-- Filter Lunas -->
            <div class="form-group col-xs-12 col-sm-2">
                <label>Status Lunas :</label>
                <select name="filter_lunas" class="form-control">
                    <option value="" <?= $filter_lunas == '' ? 'selected' : '' ?>>-- Semua --</option>
                    <option value="Belum" <?= $filter_lunas == 'Belum' ? 'selected' : '' ?>>Belum Lunas</option>
                    <option value="Lunas" <?= $filter_lunas == 'Lunas' ? 'selected' : '' ?>>Lunas</option>
                </select> 
            </div>

            <div class="form-group col-xs-12 col-sm-3" style="padding-top: 22px;">
                <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> Filter</button>
                <a href="index.php?par=52" class="btn btn-danger"><i class="fa fa-refresh"></i> Reset</a>
            </div>
        </form>
        <div class="clearfix"></div>

        <h4><font color="red"><?php echo $pesan; ?></font></h4>
      </header>

      <div align="center">
        <strong>LAPORAN PINJAMAN</strong>
        <?php
        if ($filter_pegawai != '') {
            $qn = $conn->prepare("SELECT nama FROM ruser WHERE iduser = :id");
            $qn->execute([':id' => $filter_pegawai]);
            $rn = $qn->fetch();
            if ($rn) echo " — " . htmlspecialchars($rn['nama']);
        }
        if ($filter_status != '') echo " | Status: $filter_status";
        if ($filter_lunas != '') echo " | Lunas: $filter_lunas";
        ?>
      </div>

      <div class="box-body">
      <div class="table-responsive">
      <table id="tabel_lap_pinjaman" class="table table-bordered table-striped table-hover"> 
        <thead> 
            <tr>
              <th>#</th>
              <th>Pemohon</th>
              <th>Jabatan</th>
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
                $swhere_data = " WHERE 1=1";
                if ($filter_pegawai != '') {
                    $swhere_data .= " AND p.iduser_pemohon = '$filter_pegawai'";
                }
                if ($filter_status != '') {
                    $swhere_data .= " AND p.status_approval = '$filter_status'";
                }
                if ($filter_lunas != '') {
                    $swhere_data .= " AND p.status_lunas = '$filter_lunas'";
                }

                $strsql = "SELECT p.*, u.nama AS nama_pemohon 
                           FROM tpinjaman p 
                           LEFT JOIN ruser u ON p.iduser_pemohon = u.iduser 
                           $swhere_data
                           ORDER BY p.tgl_pengajuan DESC";
                $sql = $conn->prepare($strsql);
                $sql->execute();
                
                $no = 1;
                while ($rs = $sql->fetch(PDO::FETCH_ASSOC)) {
                    // Status badge
                    $status = $rs['status_approval'];
                    if ($status == 'Approved') {
                        $badge = '<span class="label label-success">Disetujui</span>';
                    } elseif ($status == 'Rejected') {
                        $badge = '<span class="label label-danger">Ditolak</span>';
                    } else {
                        $badge = '<span class="label label-warning">Menunggu</span>';
                    }
                    
                    // Status lunas
                    $lunas = $rs['status_lunas'];
                    if ($lunas == 'Lunas') {
                        $badgeLunas = '<span class="label label-success">Lunas</span>';
                    } else {
                        $badgeLunas = '<span class="label label-default">Belum</span>';
                    }
                    
                    // Periode
                    $pa = isset($rs['periode_awal']) && $rs['periode_awal'] ? $rs['periode_awal'] : '-';
                    $pb = isset($rs['periode_akhir']) && $rs['periode_akhir'] ? $rs['periode_akhir'] : '-';
                    $periode_display = ($pa !== '-' && $pb !== '-') ? "<small>$pa s/d $pb</small>" : '-';
                    
                    echo "<tr>";
                    echo "<td align='center'>$no</td>";
                    echo "<td>".htmlspecialchars($rs['nama_pemohon'] ?? $rs['iduser_pemohon'])."</td>";
                    echo "<td>".htmlspecialchars($rs['jabatan_pemohon'] ?? '-')."</td>";
                    echo "<td>".date('d/m/Y', strtotime($rs['tgl_pengajuan']))."</td>";
                    $tenor_berjalan = 0;
                    if ($rs['cicilan_perbulan'] > 0) {
                        $tenor_berjalan = floor(($rs['jumlah_dibayar'] ?? 0) / $rs['cicilan_perbulan']);
                    }
                    echo "<td align='right'>Rp ".number_format($rs['nominal'], 0, ',', '.')."</td>";
                    echo "<td align='center'>".$rs['tenor']." bulan</td>";
                    echo "<td align='center'>".$tenor_berjalan." bulan</td>";
                    echo "<td align='right'>Rp ".number_format($rs['cicilan_perbulan'], 0, ',', '.')."</td>";
                    echo "<td align='center'>$periode_display</td>";
                    echo "<td>".htmlspecialchars($rs['keperluan'])."</td>";
                    echo "<td align='center'>$badge</td>";
                    echo "<td>".htmlspecialchars($rs['catatan_approval'] ?? '')."</td>";
                    echo "<td align='center'>$badgeLunas</td>";
                    echo "<td align='center'>";
                    $aksi = [];
                    $aksi[] = "<a href='48_cetak_pinjaman.php?id=".$rs['id']."' target='_blank' class='btn btn-info btn-xs'><i class='fa fa-print'></i> Cetak</a>";
                    if ($status == 'Approved') {
                        $sudah_bayar = (float)($rs['jumlah_dibayar'] ?? 0);
                        
                        // Data untuk tombol adjust
                        $js_data = htmlspecialchars(json_encode([
                            'id' => $rs['id'],
                            'nama' => $rs['nama_pemohon'] ?? $rs['iduser_pemohon'],
                            'nominal' => $rs['nominal'],
                            'tenor' => $rs['tenor'],
                            'cicilan' => $rs['cicilan_perbulan'],
                            'periode_awal' => $rs['periode_awal'] ?? '',
                            'periode_akhir' => $rs['periode_akhir'] ?? '',
                            'status_lunas' => $rs['status_lunas'],
                            'catatan' => $rs['catatan_approval'] ?? '',
                            'sudah_bayar' => $sudah_bayar
                        ]), ENT_QUOTES);
                        $aksi[] = "<button type='button' class='btn btn-warning btn-xs' onclick='showAdjust($js_data)'><i class='fa fa-pencil'></i> Adjust</button>";
                    }
                    echo implode(' ', $aksi);
                    echo "</td>";
                    echo "</tr>";
                    $no++;
                }
            } catch (PDOException $e) {
                echo "<tr><td colspan='14' class='text-center'>Error: " . $e->getMessage() . "</td></tr>";
            }
        ?> 
        </tbody>
    </table>
    </div>
    </div>
    </section>
    </section>

    <!-- Panel Adjustment (muncul di bawah tabel saat klik Adjust) -->
    <section class="panel" id="panel_adjust" style="display:none; border: 2px solid #f0ad4e; margin-top: -15px;">
      <header class="panel-heading" style="background: #fcf8e3; border-bottom: 1px solid #f0ad4e;">
        <h4 style="margin:0; color:#8a6d3b;"><i class="fa fa-pencil-square-o"></i> Adjustment Pinjaman — <span id="adj_nama"></span></h4>
      </header>
      <div class="panel-body" style="padding: 15px 20px;">
        <form id="formAdjust" onsubmit="return simpanAdjust()">
          <input type="hidden" id="adj_id">
          
          <div class="row">
            <div class="col-sm-2">
              <label>Nominal Awal</label>
              <input type="text" id="adj_nominal" class="form-control" readonly style="background:#f5f5f5; font-weight:bold;">
            </div>
            <div class="col-sm-2">
              <label>Cicilan Lama</label>
              <input type="text" id="adj_cicilan_lama" class="form-control" readonly style="background:#f5f5f5;">
            </div>
            <div class="col-sm-3">
              <label>Sudah Bayar</label>
              <div class="input-group">
                <span class="input-group-addon">Rp</span>
                <input type="text" id="adj_sudah_bayar" class="form-control input-ribuan" onkeyup="hitungAdjust()">
              </div>
            </div>
            <div class="col-sm-3">
              <label>Sisa Pinjaman</label>
              <input type="text" id="adj_sisa" class="form-control" readonly style="background:#e8f5e9; font-weight:bold; color:#2e7d32;">
            </div>
          </div>
          
          <div class="row" style="margin-top:10px;">
            <div class="col-sm-2">
              <label>Tenor Baru (bulan) <span style="color:red;">*</span></label>
              <input type="number" id="adj_tenor" class="form-control" min="1" max="60" onchange="hitungAdjust()" onkeyup="hitungAdjust()">
            </div>
            <div class="col-sm-3">
              <label>Cicilan Baru/Bulan (otomatis)</label>
              <input type="text" id="adj_cicilan" class="form-control" readonly style="background:#e3f2fd; font-weight:bold; color:#1565c0;">
            </div>
            <div class="col-sm-2">
              <label>Status Lunas <span style="color:red;">*</span></label>
              <select id="adj_lunas" class="form-control">
                <option value="Belum">Belum Lunas</option>
                <option value="Lunas">Lunas</option>
              </select>
            </div>
            <div class="col-sm-2">
              <label>Periode Awal</label>
              <input type="month" id="adj_periode_awal" class="form-control" readonly style="background:#f5f5f5;">
            </div>
          </div>
          
          <div class="row" style="margin-top:10px;">
            <div class="col-sm-4">
              <label>Catatan Adjustment</label>
              <input type="text" id="adj_catatan" class="form-control" placeholder="Alasan adjustment...">
            </div>
            <div class="col-sm-3" style="padding-top:22px;">
              <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Simpan</button>
              <button type="button" class="btn btn-default" onclick="tutupAdjust()">Batal</button>
            </div>
          </div>
        </form>
      </div>
    </section>

</div> 

<script>
// ========== FORMAT RIBUAN ==========
function formatRibuan(angka) {
    if (!angka && angka !== 0) return '0';
    var str = String(Math.round(angka)).replace(/[^0-9]/g, '');
    if (!str || str === '0') return '0';
    str = str.replace(/^0+/, '') || '0';
    return str.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

// ========== SHOW ADJUST PANEL ==========
function showAdjust(data) {
    document.getElementById('adj_id').value = data.id;
    document.getElementById('adj_nama').innerText = data.nama;
    document.getElementById('adj_nominal').value = 'Rp ' + formatRibuan(data.nominal);
    document.getElementById('adj_cicilan_lama').value = 'Rp ' + formatRibuan(data.cicilan);
    document.getElementById('adj_sudah_bayar').value = formatRibuan(data.sudah_bayar || 0);
    document.getElementById('adj_tenor').value = data.tenor;
    document.getElementById('adj_lunas').value = data.status_lunas;
    document.getElementById('adj_periode_awal').value = data.periode_awal || '';
    document.getElementById('adj_catatan').value = '';
    
    // Simpan data asli untuk kalkulasi
    document.getElementById('adj_nominal').dataset.raw = data.nominal;
    document.getElementById('adj_cicilan_lama').dataset.raw = data.cicilan;
    
    hitungAdjust();
    
    var panel = document.getElementById('panel_adjust');
    panel.style.display = 'block';
    panel.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function tutupAdjust() {
    document.getElementById('panel_adjust').style.display = 'none';
}

// ========== HITUNG SISA, CICILAN & PERIODE ==========
function hitungAdjust() {
    var nominal = parseFloat(document.getElementById('adj_nominal').dataset.raw) || 0;
    var sudahDibayar = parseInt(document.getElementById('adj_sudah_bayar').value.replace(/\./g, '')) || 0;
    var tenorBaru = parseInt(document.getElementById('adj_tenor').value) || 1;
    if (tenorBaru < 1) tenorBaru = 1;
    if (sudahDibayar < 0) sudahDibayar = 0;
    
    // Hitung sisa pinjaman
    var sisa = nominal - sudahDibayar;
    if (sisa < 0) sisa = 0;
    
    document.getElementById('adj_sisa').value = 'Rp ' + formatRibuan(sisa);
    
    // Hitung cicilan baru dari SISA (bukan dari nominal awal)
    var cicilanBaru = (tenorBaru > 0) ? Math.round(sisa / tenorBaru) : 0;
    document.getElementById('adj_cicilan').value = 'Rp ' + formatRibuan(cicilanBaru);
}

// ========== SIMPAN ADJUSTMENT ==========
function simpanAdjust() {
    var id = document.getElementById('adj_id').value;
    var tenor = document.getElementById('adj_tenor').value;
    var sudahBayar = document.getElementById('adj_sudah_bayar').value.replace(/\./g, '');
    var lunas = document.getElementById('adj_lunas').value;
    var catatan = document.getElementById('adj_catatan').value;
    var pa = document.getElementById('adj_periode_awal').value;
    
    if (!confirm('Yakin ingin menyimpan adjustment ini?')) return false;
    
    $.ajax({
        url: '52_lap_pinjaman.php',
        method: 'POST',
        data: {
            ajax_adjust_pinjaman: 1,
            id: id,
            tenor_baru: tenor,
            sudah_bayar: sudahBayar,
            status_lunas: lunas,
            catatan_adjust: catatan,
            periode_awal: pa
        },
        dataType: 'json',
        success: function(resp) {
            if (resp.success) {
                alert('Adjustment berhasil disimpan!');
                location.reload();
            } else {
                alert('Gagal: ' + resp.message);
            }
        },
        error: function() {
            alert('Gagal menyimpan adjustment');
        }
    });
    
    return false;
}

$(document).ready(function() {
    var tableId = '#tabel_lap_pinjaman';
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
