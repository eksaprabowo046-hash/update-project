<?php
// Approval SPL - Hanya kodjab 1
session_start();
include "dbase.php";
include "islogin.php";

$pesan = "";
$kodjab = isset($_SESSION['DEFAULT_KODJAB']) ? $_SESSION['DEFAULT_KODJAB'] : 0;
$iduser = $_SESSION['DEFAULT_IDUSER'];

// Hanya kodjab 1 (Admin) dan 2 (GM) yang boleh akses
if ($kodjab != 1 && $kodjab != 2) {
    echo "<script>alert('Anda tidak memiliki akses ke halaman ini!'); window.location='index.php';</script>";
    exit;
}

// Proses Approve
if (isset($_POST['approve_spl'])) {
    $id = $_POST['id_lembur'] ?? '';
    $catatan = trim($_POST['catatan_approval'] ?? '');
    
    if ($id) {
        try {
            $stmt = $conn->prepare("
                UPDATE tlembur SET 
                    status_approval = 'Approved',
                    iduser_approver = :approver,
                    tgl_approval = NOW(),
                    catatan_approval = :catatan
                WHERE id = :id AND status_approval = 'Pending'
            ");
            $stmt->execute([
                ':approver' => $iduser,
                ':catatan' => $catatan,
                ':id' => $id
            ]);
            $pesan = "SPL berhasil di-Approve!";
        } catch (Exception $e) {
            $pesan = "ERROR: " . $e->getMessage();
        }
    }
}

// Proses Reject
if (isset($_POST['reject_spl'])) {
    $id = $_POST['id_lembur'] ?? '';
    $catatan = trim($_POST['catatan_approval'] ?? '');
    
    if ($id) {
        try {
            $stmt = $conn->prepare("
                UPDATE tlembur SET 
                    status_approval = 'Rejected',
                    iduser_approver = :approver,
                    tgl_approval = NOW(),
                    catatan_approval = :catatan
                WHERE id = :id AND status_approval = 'Pending'
            ");
            $stmt->execute([
                ':approver' => $iduser,
                ':catatan' => $catatan,
                ':id' => $id
            ]);
            $pesan = "SPL berhasil di-Reject.";
        } catch (Exception $e) {
            $pesan = "ERROR: " . $e->getMessage();
        }
    }
}
?>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>

<body>
<div class="row"> 
    <ol class="breadcrumb">
	  <li><i class="fa fa-check-square-o"></i> APPROVAL SPL (SURAT PERINTAH LEMBUR)</li> 
	</ol> 

	<section class="panel">
	  <header class="panel-heading">
		  <div class="clearfix"></div>
		  <h4><font color="blue"><?php echo $pesan;?></font></h4>
	  </header>
	  <section class="content">
	  <div align="center"><strong>DAFTAR PENGAJUAN LEMBUR - APPROVAL</strong></div>

	  <div class="box-body">
	   <table id="tbl-approval" class="table table-bordered table-striped table-hover"> 
		<thead> 
			<tr>
			  <th>No</th>
			  <th>Pengaju</th>
			  <th>Tanggal Lembur</th>
			  <th>Jam Mulai</th>
			  <th>Tugas, Target & Pegawai</th>
			  <th>Status</th>
			  <th style="width:220px">Aksi</th>
			</tr>
		</thead>
		<tbody>
		<?php
		$no = 1;
		$q = $conn->prepare("
		    SELECT l.*, ru.nama AS nama_pengaju
		    FROM tlembur l
		    LEFT JOIN ruser ru ON l.iduser_pengaju = ru.iduser
		    ORDER BY 
		        CASE l.status_approval 
		            WHEN 'Pending' THEN 0 
		            WHEN 'Approved' THEN 1 
		            WHEN 'Rejected' THEN 2 
		        END,
		        l.tgl_pengajuan DESC
		");
		$q->execute();

		while ($row = $q->fetch()) {
		    // Ambil detail tugas
		    $qd = $conn->prepare("
		        SELECT d.tugas, d.target, d.kodcustomer, ru.nama AS nama_pegawai, rc.nmcustomer
		        FROM tdtllembur d
		        LEFT JOIN ruser ru ON d.iduser_pegawai = ru.iduser
		        LEFT JOIN rcustomer rc ON d.kodcustomer = rc.kodcustomer
		        WHERE d.idlembur = :idlembur
		        ORDER BY d.id
		    ");
		    $qd->execute([':idlembur' => $row['id']]);

		    $tugas_list = '';
		    while ($detail = $qd->fetch()) {
		        $tugas_list .= "• " . htmlspecialchars($detail['tugas']);
		        if (!empty($detail['target'])) {
		            $tugas_list .= " | Target: " . htmlspecialchars($detail['target']);
		        }
		        if (!empty($detail['nmcustomer'])) {
		            $tugas_list .= " | Customer: " . htmlspecialchars($detail['nmcustomer']);
		        }
		        $tugas_list .= " <em>(" . htmlspecialchars($detail['nama_pegawai']) . ")</em><br>";
		    }
		    if ($tugas_list === '') $tugas_list = "<em>- Tidak ada tugas -</em>";

		    // Status badge
		    $status = $row['status_approval'] ?? 'Pending';
		    if ($status == 'Approved') {
		        $badge = '<span class="label label-success">Approved</span>';
		    } elseif ($status == 'Rejected') {
		        $badge = '<span class="label label-danger">Rejected</span>';
		    } else {
		        $badge = '<span class="label label-warning">Pending</span>';
		    }
		?>
		    <tr>
		        <td><font size=-1><?= $no ?></font></td>
		        <td><font size=-1><?= htmlspecialchars($row['nama_pengaju']) ?></font></td>
		        <td><font size=-1><?= date('d/m/Y', strtotime($row['tgl_lembur'])) ?></font></td>
		        <td><font size=-1><?= $row['jam_mulai'] ?></font></td>
		        <td><font size=-1><?= $tugas_list ?></font></td>
		        <td align="center"><font size=-1><?= $badge ?></font></td>
		        <td>
		            <?php if ($status == 'Pending') { ?>
		            <form method="POST" action="index.php?par=35a" style="display:inline;">
		                <input type="hidden" name="id_lembur" value="<?= $row['id'] ?>">
		                <input type="text" name="catatan_approval" placeholder="Catatan..." 
		                       class="form-control" style="display:inline-block; width:100%; margin-bottom:5px; font-size:12px;">
		                <button type="submit" name="approve_spl" class="btn btn-success btn-xs"
		                        onclick="return confirm('Approve SPL ini?')">
		                    <i class="fa fa-check"></i> Approve
		                </button>
		                <button type="submit" name="reject_spl" class="btn btn-danger btn-xs"
		                        onclick="return confirm('Reject SPL ini?')">
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
		                    echo "<small class='text-muted'>" . date('d/m/Y H:i', strtotime($row['tgl_approval'])) . "</small>";
		                }
		                ?>
		                </font>
		            <?php } ?>
		        </td>
		    </tr>
		<?php
		    $no++;
		}
		?>
		</tbody>
		</table>
	  </div>
	  </section>
	</section>
   
</div> 
 
</body>
</html>

<script>
$(document).ready(function () {
    const tableId = '#tbl-approval';
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
