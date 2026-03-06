<?php
// Approval Pinjaman - kodjab 1 dan 2
include "dbase.php";
include "islogin.php";

$pesan = "";
$kodjab = isset($_SESSION['DEFAULT_KODJAB']) ? $_SESSION['DEFAULT_KODJAB'] : 0;
$iduser = $_SESSION['DEFAULT_IDUSER'];

// Hanya kodjab 1 yang boleh akses
if ($kodjab != 1 && $kodjab != 2) {
    echo "<script>alert('Anda tidak memiliki akses ke halaman ini!'); window.location='index.php';</script>";
    exit;
}

// Proses Approve
if (isset($_POST['approve_pinjaman'])) {
    $id = (int) ($_POST['id_pinjaman'] ?? 0);
    $catatan = trim($_POST['catatan_approval'] ?? '');
    
    if ($id) {
        try {
            $stmt = $conn->prepare("
                UPDATE tpinjaman SET 
                    status_approval = 'Approved',
                    iduser_approval = :approver,
                    tgl_approval = NOW(),
                    catatan_approval = :catatan
                WHERE id = :id AND status_approval = 'Pending'
            ");
            $stmt->execute([
                ':approver' => $iduser,
                ':catatan' => $catatan,
                ':id' => $id
            ]);
            if ($stmt->rowCount() > 0) {
                $pesan = "<font color='blue'>Pinjaman berhasil di-Approve!</font>";
            } else {
                $pesan = "<font color='red'>Data tidak ditemukan atau sudah diproses.</font>";
            }
        } catch (Exception $e) {
            $pesan = "<font color='red'>ERROR: " . $e->getMessage() . "</font>";
        }
    }
}

// Proses Reject
if (isset($_POST['reject_pinjaman'])) {
    $id = (int) ($_POST['id_pinjaman'] ?? 0);
    $catatan = trim($_POST['catatan_approval'] ?? '');
    
    if ($id) {
        try {
            $stmt = $conn->prepare("
                UPDATE tpinjaman SET 
                    status_approval = 'Rejected',
                    iduser_approval = :approver,
                    tgl_approval = NOW(),
                    catatan_approval = :catatan
                WHERE id = :id AND status_approval = 'Pending'
            ");
            $stmt->execute([
                ':approver' => $iduser,
                ':catatan' => $catatan,
                ':id' => $id
            ]);
            if ($stmt->rowCount() > 0) {
                $pesan = "<font color='blue'>Pinjaman berhasil di-Reject.</font>";
            } else {
                $pesan = "<font color='red'>Data tidak ditemukan atau sudah diproses.</font>";
            }
        } catch (Exception $e) {
            $pesan = "<font color='red'>ERROR: " . $e->getMessage() . "</font>";
        }
    }
}

// Proses Ubah Status Lunas
if (isset($_POST['ubah_lunas'])) {
    $id = (int) ($_POST['id_pinjaman'] ?? 0);
    $status_baru = ($_POST['status_lunas_baru'] ?? '') === 'Lunas' ? 'Lunas' : 'Belum';
    
    if ($id) {
        try {
            $stmt = $conn->prepare("
                UPDATE tpinjaman SET status_lunas = :status 
                WHERE id = :id AND status_approval = 'Approved'
            ");
            $stmt->execute([':status' => $status_baru, ':id' => $id]);
            if ($stmt->rowCount() > 0) {
                $pesan = "<font color='blue'>Status lunas berhasil diubah menjadi " . htmlspecialchars($status_baru) . "!</font>";
            } else {
                $pesan = "<font color='red'>Data tidak ditemukan atau belum Approved.</font>";
            }
        } catch (Exception $e) {
            $pesan = "<font color='red'>ERROR: " . $e->getMessage() . "</font>";
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
      <li><i class="fa fa-check-square-o"></i> APPROVAL PINJAMAN</li> 
    </ol> 

    <section class="panel">
      <header class="panel-heading">
          <div class="clearfix"></div>
          <h4><?php echo $pesan;?></h4>
      </header>
      <section class="content">
      <div align="center"><strong>DAFTAR PENGAJUAN PINJAMAN - APPROVAL</strong></div>

      <div class="box-body">
      <div class="table-responsive">
      <table id="tbl-approval-pinjaman" class="table table-bordered table-striped table-hover"> 
        <thead> 
            <tr>
              <th>No</th>
              <th>Pemohon</th>
              <th>Tgl Pengajuan</th>
              <th>Nominal</th>
              <th>Tenor Awal</th>
              <th>Tenor Berjalan</th>
              <th>Cicilan/Bulan</th>
              <th>Keperluan</th>
              <th>Jabatan</th>
              <th>No. Telp</th>
              <th>Status</th>
              <th>Lunas</th>
              <th style="width:250px">Aksi</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $no = 1;
        try {
            $q = $conn->prepare("
                SELECT p.*, u.nama AS nama_pemohon, ap.nama AS nama_approver
                FROM tpinjaman p
                LEFT JOIN ruser u ON p.iduser_pemohon = u.iduser
                LEFT JOIN ruser ap ON p.iduser_approval = ap.iduser
                ORDER BY 
                    CASE p.status_approval 
                        WHEN 'Pending' THEN 0 
                        WHEN 'Approved' THEN 1 
                        WHEN 'Rejected' THEN 2 
                    END,
                    p.tgl_pengajuan DESC
            ");
            $q->execute();
            
            while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
                // Status badge
                $status = $row['status_approval'] ?? 'Pending';
                if ($status == 'Approved') {
                    $badge = '<span class="label label-success">Approved</span>';
                } elseif ($status == 'Rejected') {
                    $badge = '<span class="label label-danger">Rejected</span>';
                } else {
                    $badge = '<span class="label label-warning">Pending</span>';
                }
                
                // Status lunas
                $lunas = $row['status_lunas'] ?? 'Belum';
                if ($lunas == 'Lunas') {
                    $badgeLunas = '<span class="label label-success">Lunas</span>';
                } else {
                    $badgeLunas = '<span class="label label-default">Belum</span>';
                }
        ?>
            <tr>
                <td align="center"><font size=-1><?= $no ?></font></td>
                <td><font size=-1><?= htmlspecialchars($row['nama_pemohon']) ?></font></td>
                <td><font size=-1><?= date('d/m/Y', strtotime($row['tgl_pengajuan'])) ?></font></td>
                <?php 
                $tenor_berjalan = 0;
                if ($row['cicilan_perbulan'] > 0) {
                    $tenor_berjalan = floor(($row['jumlah_dibayar'] ?? 0) / $row['cicilan_perbulan']);
                }
                ?>
                <td align="right"><font size=-1>Rp <?= number_format($row['nominal'], 0, ',', '.') ?></font></td>
                <td align="center"><font size=-1><?= $row['tenor'] ?> bulan</font></td>
                <td align="center"><font size=-1><?= $tenor_berjalan ?> bulan</font></td>
                <td align="right"><font size=-1>Rp <?= number_format($row['cicilan_perbulan'], 0, ',', '.') ?></font></td>
                <td><font size=-1><?= htmlspecialchars($row['keperluan']) ?></font></td>
                <td><font size=-1><?= htmlspecialchars($row['jabatan_pemohon'] ?? '') ?></font></td>
                <td><font size=-1><?= htmlspecialchars($row['no_telp'] ?? '') ?></font></td>
                <td align="center"><font size=-1><?= $badge ?></font></td>
                <td align="center"><font size=-1><?= $badgeLunas ?></font></td>
                <td>
                    <?php if ($status == 'Pending') { ?>
                    <form method="POST" action="index.php?par=49" style="display:inline;">
                        <input type="hidden" name="id_pinjaman" value="<?= $row['id'] ?>">
                        <input type="text" name="catatan_approval" placeholder="Catatan..." 
                               class="form-control" style="display:inline-block; width:100%; margin-bottom:5px; font-size:12px;">
                        <button type="submit" name="approve_pinjaman" class="btn btn-success btn-xs"
                                onclick="return confirm('Approve pinjaman ini?')">
                            <i class="fa fa-check"></i> Approve
                        </button>
                        <button type="submit" name="reject_pinjaman" class="btn btn-danger btn-xs"
                                onclick="return confirm('Reject pinjaman ini?')">
                            <i class="fa fa-times"></i> Reject
                        </button>
                    </form>
                    <?php } else { ?>
                        <font size=-1>
                        <?php 
                        if ($row['catatan_approval']) {
                            echo "<em>" . htmlspecialchars($row['catatan_approval']) . "</em><br>";
                        }
                        if ($row['tgl_approval']) {
                            echo "<small class='text-muted'>" . date('d/m/Y H:i', strtotime($row['tgl_approval'])) . "</small><br>";
                        }
                        if ($row['nama_approver']) {
                            echo "<small class='text-muted'>oleh: " . htmlspecialchars($row['nama_approver']) . "</small><br>";
                        }
                        ?>
                        </font>
                        
                        <?php // Tombol ubah status lunas (hanya untuk Approved) ?>
                        <?php if ($status == 'Approved') { ?>
                        <div style="margin-top:5px;">
                            <form method="POST" action="index.php?par=49" style="display:inline;">
                                <input type="hidden" name="id_pinjaman" value="<?= $row['id'] ?>">
                                <?php if ($lunas == 'Belum') { ?>
                                <input type="hidden" name="status_lunas_baru" value="Lunas">
                                <button type="submit" name="ubah_lunas" class="btn btn-primary btn-xs"
                                        onclick="return confirm('Tandai pinjaman ini sebagai Lunas?')">
                                    <i class="fa fa-check-circle"></i> Set Lunas
                                </button>
                                <?php } else { ?>
                                <input type="hidden" name="status_lunas_baru" value="Belum">
                                <button type="submit" name="ubah_lunas" class="btn btn-warning btn-xs"
                                        onclick="return confirm('Ubah status ke Belum Lunas?')">
                                    <i class="fa fa-undo"></i> Set Belum
                                </button>
                                <?php } ?>
                            </form>
                            <a href='48_cetak_pinjaman.php?id=<?= $row['id'] ?>' target='_blank' class='btn btn-info btn-xs'>
                                <i class="fa fa-print"></i> Cetak
                            </a>
                        </div>
                        <?php } ?>
                    <?php } ?>
                </td>
            </tr>
        <?php
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
    </section>
</div> 

</body>
</html>

<script>
$(document).ready(function () {
    const tableId = '#tbl-approval-pinjaman';
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
