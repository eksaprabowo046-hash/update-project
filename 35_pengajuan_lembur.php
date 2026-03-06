<?php
// 
session_start();
include "dbase.php";
include "islogin.php";

$pesan = "";
$tglini = date('Y-m-d');

// Validasi session user login
if (!isset($_SESSION['DEFAULT_IDUSER']) || empty($_SESSION['DEFAULT_IDUSER'])) {
    die('ERROR: SESSION USER TIDAK VALID');
}

$iduser = $_SESSION['DEFAULT_IDUSER'];

// echo '<pre>';
// var_dump($_SESSION);
// die;

// Cek apakah mode edit
$is_edit_mode = false;
$edit_data = null;
$id_edit = isset($_GET['edit']) ? intval($_GET['edit']) : 0;

if ($id_edit > 0) {
    $is_edit_mode = true;
    // Get data untuk edit
    $qedit = $conn->prepare("SELECT * FROM tlembur WHERE id = :id");
    $qedit->bindParam(':id', $id_edit);
    $qedit->execute();
    $edit_data = $qedit->fetch();
}

// Proses simpan / update data pengajuan lembur
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['hapus'])) {

    $id_post     = intval($_POST['id_edit'] ?? 0);
    $latarblkg   = trim($_POST['latarblkg'] ?? '');
    $aktivitas   = $_POST['aktivitas'] ?? [];
    $pegawai     = $_POST['pegawai'] ?? [];
    $target      = $_POST['target'] ?? [];
    $customer    = $_POST['customer'] ?? [];
    $wktlembur   = $_POST['wktlembur'] ?? '';
    $jam_mulai   = $_POST['jam_mulai'] ?? '';

    if (!$latarblkg || !$wktlembur || !$jam_mulai || empty($aktivitas)) {
        $pesan = "Semua field wajib diisi!";
    } else {
        try {
            $conn->beginTransaction();

            if ($id_post > 0) {
                // Mode edit: update header
                $stmt = $conn->prepare("UPDATE tlembur SET latarbelakang=:latar, tgl_lembur=:tgl, jam_mulai=:mulai WHERE id=:id");
                $stmt->execute([':latar' => $latarblkg, ':tgl' => $wktlembur, ':mulai' => $jam_mulai, ':id' => $id_post]);
                // Hapus detail lama lalu insert ulang
                $conn->prepare("DELETE FROM tdtllembur WHERE idlembur = :id")->execute([':id' => $id_post]);
                $id_lembur = $id_post;
                $pesan = "Data pengajuan lembur berhasil diperbarui";
            } else {
                // Mode tambah baru
                $stmt = $conn->prepare("
                    INSERT INTO tlembur (latarbelakang, iduser_pengaju, tgl_lembur, jam_mulai, tgl_pengajuan)
                    VALUES (:latar, :pengaju, :tgl, :mulai, NOW())
                ");
                $stmt->execute([':latar' => $latarblkg, ':pengaju' => $iduser, ':tgl' => $wktlembur, ':mulai' => $jam_mulai]);
                $id_lembur = $conn->lastInsertId();
                $pesan = "Data pengajuan lembur berhasil disimpan";
            }

            // Insert detail per pegawai
            foreach ($aktivitas as $i => $tugas) {
                $idpegawai = $pegawai[$i] ?? null;
                if ($tugas && $idpegawai) {
                    $stmtD = $conn->prepare("
                        INSERT INTO tdtllembur (idlembur, iduser_pegawai, tugas, target, kodcustomer)
                        VALUES (:idlembur, :pegawai, :tugas, :target, :kodcustomer)
                    ");
                    $stmtD->execute([
                        ':idlembur'    => $id_lembur,
                        ':pegawai'     => $pegawai[$i] ?? null,
                        ':tugas'       => $tugas,
                        ':target'      => $target[$i] ?? null,
                        ':kodcustomer' => $customer[$i] ?? null
                    ]);
                }
            }

            $conn->commit();
            // Reset edit state setelah simpan
            $is_edit_mode = false;
            $edit_data    = null;
            $id_edit      = 0;

        } catch (Exception $e) {
            $conn->rollBack();
            $pesan = "ERROR: " . $e->getMessage();
        }
    }
}


// Proses hapus data
if (isset($_POST['hapus']) && isset($_POST['id_hapus'])) {
    try {
        $id_hapus = $_POST['id_hapus'];
        
        // Hapus detail lembur terlebih dahulu (akan otomatis terhapus karena foreign key cascade)
        $sql_del = "DELETE FROM tlembur WHERE id = :id";
        $stmt_del = $conn->prepare($sql_del);
        $stmt_del->bindParam(':id', $id_hapus);
        $stmt_del->execute();
        
        $pesan = "Data pengajuan lembur berhasil dihapus!";
    } catch (PDOException $e) {
        $pesan = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Pengajuan Lembur</title>
    <!-- Tambahkan referensi ke library Bootstrap dan jQuery -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
    
    <!-- Tambahkan referensi ke library Bootstrap Datepicker -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>
    
    <script>
        $(document).ready(function(){
            // Inisialisasi datepicker
            $('.datepicker').datepicker({
                format: 'yyyy-mm-dd',
                autoclose: true,
                todayHighlight: true
            });
            
            // Function untuk menambah baris aktivitas
            $('#add_activity_btn').click(function(){
                var newRow = `
                    <tr>
                        <td>
                            <input type="text" name="aktivitas[]" class="form-control" placeholder="Deskripsi tugas..." required>
                        </td>
                        <td>
                            <input type="text" name="target[]" class="form-control" placeholder="Target...">
                        </td>
                        <td>
                            <select name="pegawai[]" class="form-control" required>
                                <option value="">-- Pilih Pegawai --</option>
                                <?php
                                $qp = $conn->prepare("SELECT * FROM ruser WHERE stsaktif = 1 ORDER BY nik ASC, nama ASC");
                                $qp->execute();
                                while($rsp = $qp->fetch()) {
                                    echo "<option value='".$rsp['iduser']."'>".$rsp['nama']."</option>";
                                }
                                ?>
                            </select>
                        </td>
                        <td>
                            <select name="customer[]" class="form-control">
                                <option value="">-- Pilih Customer --</option>
                                <?php
                                $qc = $conn->prepare("SELECT kodcustomer, nmcustomer FROM rcustomer ORDER BY nmcustomer");
                                $qc->execute();
                                while($rsc = $qc->fetch()) {
                                    echo "<option value='".$rsc['kodcustomer']."'>".$rsc['nmcustomer']."</option>";
                                }
                                ?>
                            </select>
                        </td>
                        <td>
                            <button type="button" class="btn btn-danger btn-sm" onclick="removeActivityRow(this)">
                                <i class="fa fa-minus"></i> Hapus
                            </button>
                        </td>
                    </tr>
                `;
                $('#activity_table_body').append(newRow);
                updateRemoveButtons();
            });
            
            // Update status button hapus
            updateRemoveButtons();
        });
        
        function removeActivityRow(btn) {
            $(btn).closest('tr').remove();
            updateRemoveButtons();
        }
        
        function updateRemoveButtons() {
            var rowCount = $('#activity_table_body tr').length;
            if (rowCount <= 1) {
                $('#activity_table_body tr').find('button.btn-danger').prop('disabled', true);
            } else {
                $('#activity_table_body tr').find('button.btn-danger').prop('disabled', false);
            }
        }
        
        function confirmDelete(id) {
            if(confirm('Apakah Anda yakin ingin menghapus pengajuan lembur ini?')) {
                document.getElementById('id_hapus').value = id;
                document.getElementById('form_hapus').submit();
            }
        }
    </script>
</head>
<body>
<div class="row">
    <div class="breadcrumb">
        <span class="breadcrumb-title"><i class="fa fa-home"></i> PENGAJUAN LEMBUR</span>
    </div>

    <section class="panel">
        <header class="panel-heading">
            <form role="form" method="POST" action="index.php?par=35<?= $is_edit_mode ? '&edit='.$id_edit : '' ?>">
                <?php if ($is_edit_mode && $edit_data): ?>
                    <input type="hidden" name="id_edit" value="<?= $edit_data['id'] ?>">
                <?php endif; ?>
                <div class="form-group col-xs-12 col-md-6">
                    <label>Latar Belakang <span style="color:red;">*</span></label>
                    <textarea name="latarblkg" class="form-control" id="latarbelakang" rows="3" cols="50" autocomplete="off" placeholder="Latar Belakang" required><?= $is_edit_mode ? htmlspecialchars($edit_data['latarbelakang'] ?? '') : '' ?></textarea>
                </div>
                <div class="col-xs-12 col-md-6" style="display:flex; gap:10px;">
                    <div class="form-group" style="flex:1;">
                        <label>Tanggal Lembur <span style="color:red;">*</span></label>
                        <input name="wktlembur" id="dp1" size="7" class="form-control datepicker" value="<?= $is_edit_mode ? htmlspecialchars($edit_data['tgl_lembur'] ?? $tglini) : $tglini ?>" required>
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label>Jam Mulai <span style="color:red;">*</span></label>
                        <input type="time" name="jam_mulai" id="jam_mulai" class="form-control" value="<?= $is_edit_mode ? htmlspecialchars($edit_data['jam_mulai'] ?? '') : '' ?>" required>
                    </div>
                </div>
                
                <div class="clearfix"></div>
                
                <div class="col-xs-12">
                    <label>Daftar Tugas <span style="color:red;">*</span></label>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th style="width: 28%;">Tugas</th>
                                <th style="width: 27%;">Target</th>
                                <th style="width: 17%;">Pegawai</th>
                                <th style="width: 12%;">Customer</th>
                                <th style="width: 8%;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="activity_table_body">
                            <?php if ($is_edit_mode && $edit_data): ?>
                                <?php
                                $qdetail = $conn->prepare("SELECT d.*, ru.nama FROM tdtllembur d 
                                                          LEFT JOIN ruser ru ON d.iduser_pegawai = ru.iduser 
                                                          WHERE d.idlembur = :id");
                                $qdetail->bindParam(':id', $id_edit);
                                $qdetail->execute();
                                while($detail = $qdetail->fetch()):
                                ?>
                                <tr>
                                    <td>
                                        <input type="text" name="aktivitas[]" class="form-control" required 
                                               value="<?php echo htmlspecialchars($detail['tugas']); ?>">
                                    </td>
                                    <td>
                                        <input type="text" name="target[]" class="form-control" 
                                               value="<?php echo htmlspecialchars($detail['target'] ?? ''); ?>">
                                    </td>
                                    <td>
                                        <select name="pegawai[]" class="form-control" required>
                                            <option value="">-- Pilih Pegawai --</option>
                                            <?php
                                            $qp = $conn->prepare("SELECT * FROM ruser WHERE stsaktif = 1 ORDER BY nik ASC, nama ASC");
                                            $qp->execute();
                                            while($rsp = $qp->fetch()) {
                                                $selected = ($rsp['iduser'] == $detail['iduser_pegawai']) ? 'selected' : '';
                                                echo "<option value='".$rsp['iduser']."' $selected>".$rsp['nama']."</option>";
                                            }
                                            ?>
                                        </select>
                                    </td>
                                    <td>
                                        <select name="customer[]" class="form-control">
                                            <option value="">-- Pilih Customer --</option>
                                            <?php
                                            $qc = $conn->prepare("SELECT kodcustomer, nmcustomer FROM rcustomer ORDER BY nmcustomer");
                                            $qc->execute();
                                            while($rsc = $qc->fetch()) {
                                                $selc = ($rsc['kodcustomer'] == ($detail['kodcustomer'] ?? '')) ? 'selected' : '';
                                                echo "<option value='".$rsc['kodcustomer']."' $selc>".$rsc['nmcustomer']."</option>";
                                            }
                                            ?>
                                        </select>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-danger btn-sm" onclick="removeActivityRow(this)">
                                            <i class="fa fa-minus"></i> Hapus
                                        </button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td>
                                        <input type="text" name="aktivitas[]" class="form-control" placeholder="Deskripsi tugas..." required>
                                    </td>
                                    <td>
                                        <input type="text" name="target[]" class="form-control" placeholder="Target...">
                                    </td>
                                    <td>
                                        <select name="pegawai[]" class="form-control" required>
                                            <option value="">-- Pilih Pegawai --</option>
                                            <?php
                                            $qp = $conn->prepare("SELECT * FROM ruser WHERE stsaktif = 1 ORDER BY nik ASC, nama ASC");
                                            $qp->execute();
                                            while($rsp = $qp->fetch()) {
                                                echo "<option value='".$rsp['iduser']."'>".$rsp['nama']."</option>";
                                            }
                                            ?>
                                        </select>
                                    </td>
                                    <td>
                                        <select name="customer[]" class="form-control">
                                            <option value="">-- Pilih Customer --</option>
                                            <?php
                                            $qc = $conn->prepare("SELECT kodcustomer, nmcustomer FROM rcustomer ORDER BY nmcustomer");
                                            $qc->execute();
                                            while($rsc = $qc->fetch()) {
                                                echo "<option value='".$rsc['kodcustomer']."'>".$rsc['nmcustomer']."</option>";
                                            }
                                            ?>
                                        </select>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-danger btn-sm" onclick="removeActivityRow(this)" disabled>
                                            <i class="fa fa-minus"></i> Hapus
                                        </button>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    
                    <button type="button" class="btn btn-success" id="add_activity_btn">
                        <i class="fa fa-plus"></i> Tambah Tugas
                    </button>
                </div>
                
                <div class="form-group col-xs-12" style="display:flex; justify-content:flex-end; gap:5px;">
                    <button type="submit" class="btn btn-primary">
                        <?= $is_edit_mode ? 'Update' : 'Simpan' ?>
                    </button>
                    <?php if ($is_edit_mode): ?>
                        <a href="index.php?par=35" class="btn btn-default">Batal</a>
                    <?php else: ?>
                        <button type="reset" class="btn btn-danger">Reset</button>
                    <?php endif; ?>
                </div>
            </form>
            <div class="clearfix"></div>
           
            <h4><font color="blue"><?php echo $pesan;?></font></h4>
        </header>
        <section class="content">
    <div align="center"><strong>DAFTAR PENGAJUAN LEMBUR</strong></div>

    <div class="box-body">
        <div class="table-responsive">
            <table id="daftar-pengajuan-lembur" class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th style="width:50px">No</th>
                        <th>Latar Belakang</th>
                        <th style="width:120px">Tanggal</th>
                        <th style="width:100px">Jam Mulai</th>
                        <th>Tugas, Target & Pegawai</th>
                        <th style="width:100px">Status</th>
                        <th style="width:160px">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $no = 1;
                $q = $conn->prepare("
                    SELECT *
                    FROM tlembur
                    WHERE iduser_pengaju IS NOT NULL
                    ORDER BY tgl_pengajuan DESC
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

                    $aktivitas_list = '';
                    while ($detail = $qd->fetch()) {
                        $aktivitas_list .= "• "
                            . htmlspecialchars($detail['tugas']);
                        if (!empty($detail['target'])) {
                            $aktivitas_list .= " | Target: " . htmlspecialchars($detail['target']);
                        }
                        if (!empty($detail['nmcustomer'])) {
                            $aktivitas_list .= " | Customer: " . htmlspecialchars($detail['nmcustomer']);
                        }
                        $aktivitas_list .= " <em>("
                            . htmlspecialchars($detail['nama_pegawai'])
                            . ")</em><br>";
                    }

                    if ($aktivitas_list === '') {
                        $aktivitas_list = "<em>- Tidak ada tugas -</em>";
                    }
                ?>
                    <tr>
                        <td><?= $no ?></td>
                        <td><?= nl2br(htmlspecialchars($row['latarbelakang'])) ?></td>
                        <td><?= date('d/m/Y', strtotime($row['tgl_lembur'])) ?></td>
                        <td><?= $row['jam_mulai'] ?></td>
                        <td><?= $aktivitas_list ?></td>
                        <td align="center" style="vertical-align:middle;">
                            <?php
                            $status = $row['status_approval'] ?? 'Pending';
                            if ($status == 'Approved') {
                                echo '<span class="label label-success">Approved</span>';
                            } elseif ($status == 'Rejected') {
                                echo '<span class="label label-danger">Rejected</span>';
                            } else {
                                echo '<span class="label label-warning">Pending</span>';
                            }
                            if ($status != 'Pending' && !empty($row['catatan_approval'])) {
                                echo '<br><small style="color:#555; font-style:italic; display:block; text-align:center; margin-top:5px;">Ket: ' . htmlspecialchars($row['catatan_approval']) . '</small>';
                            }
                            ?>
                        </td>
                        <td>
                            <div class="d-flex" role="group" style="gap: 5px; flex-wrap: nowrap;">
                                <a href="index.php?par=35&edit=<?= $row['id'] ?>" class="btn btn-warning btn-sm">Edit</a>
                                <a href="35_cetak_lembur.php?id=<?= $row['id'] ?>" target="_blank" class="btn btn-info btn-sm">Cetak</a>
                                <button type="button" onclick="confirmDelete(<?= $row['id'] ?>)" class="btn btn-danger btn-sm">Hapus</button>
                            </div>
                        </td>
                    </tr>
                <?php
                    $no++;
                }
                ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

    </section>
</div>

<!-- Form tersembunyi untuk hapus -->
<form id="form_hapus" method="POST" action="index.php?par=35" style="display:none;">
    <input type="hidden" name="hapus" value="1">
    <input type="hidden" name="id_hapus" id="id_hapus">
</form>

</body>
</html>

<script>
$(document).ready(function () {

    const tableId = '#daftar-pengajuan-lembur';

    // Cegah DataTable dipanggil lebih dari sekali
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
            paginate: {
                next: ">",
                previous: "<"
            }
        }
    });

});
</script>
