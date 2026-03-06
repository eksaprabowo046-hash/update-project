<?php
// 46_input_divisi.php
// Sub-menu of Input User to manage Departments/Divisions
session_start();
include "dbase.php";
include "islogin.php";

// Access Control (Check 'Input Divisi' permission or fallback to 'Input User' logic)
if ($kodjab != 1 && $kodjab != 2) {
    echo "<script>alert('Anda tidak memiliki akses ke halaman ini!'); window.location='index.php';</script>";
    exit;
}
// menutab.php will handle the link visibility.

// 1. Table `tbl_divisi` is expected to exist.
// Run this SQL manually if needed:
/*
CREATE TABLE IF NOT EXISTS tbl_divisi (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama_divisi VARCHAR(100) NOT NULL
);
INSERT INTO tbl_divisi (nama_divisi) VALUES ('IT'), ('HRD'), ('Finance'), ('Marketing');
*/

// 2. Handle Form Submissions
$pesan = "";
if (isset($_POST['save_divisi'])) {
    try {
        $nama = trim($_POST['nama_divisi']);
        if (!empty($nama)) {
            $stmt = $conn->prepare("INSERT INTO tbl_divisi (nama_divisi) VALUES (?)");
            $stmt->execute([$nama]);
            $pesan = "Divisi berhasil ditambahkan!";
        }
    } catch (PDOException $e) {
        $pesan = "Error: " . $e->getMessage();
    }
}

if (isset($_POST['update_divisi'])) {
    try {
        $id = $_POST['id_divisi'];
        $nama = trim($_POST['nama_divisi']);
        if (!empty($nama)) {
            $stmt = $conn->prepare("UPDATE tbl_divisi SET nama_divisi=? WHERE id=?");
            $stmt->execute([$nama, $id]);
            $pesan = "Divisi berhasil diupdate!";
        }
    } catch (PDOException $e) {
        $pesan = "Error: " . $e->getMessage();
    }
}

if (isset($_POST['delete_divisi'])) {
    try {
        $id = $_POST['id_del'];
        $stmt = $conn->prepare("DELETE FROM tbl_divisi WHERE id=?");
        $stmt->execute([$id]);
        $pesan = "Divisi berhasil dihapus!";
    } catch (PDOException $e) {
        $pesan = "Error: " . $e->getMessage();
    }
}
?>

<script>
function editDivisi(id, nama) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_nama').value = nama;
    $('#editDivisiModal').modal('show');
}

function confirmDelete(id) {
    if(confirm("Yakin ingin menghapus divisi ini?")) {
        document.getElementById('del_id').value = id;
        document.getElementById('form_delete').submit();
    }
}
</script>

<div class="row">
    <ol class="breadcrumb">
        <li><i class="fa fa-home"></i> INPUT USER / KELOLA DIVISI</li>
    </ol>
    <section class="panel">
        <header class="panel-heading">
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addDivisiModal" style="margin-top: 15px;">
              <i class="fa fa-plus"></i> Tambah Divisi
            </button>
            <br><br>
            <?php if (!empty($pesan)) echo "<div class='alert alert-info'>$pesan</div>"; ?>
        </header>
        <div class="panel-body">
            <table class="table table-bordered table-striped table-hover">
                <thead>
                    <tr>
                        <th style="width: 50px; text-align: center;">No</th>
                        <th>Nama Divisi</th>
                        <th style="width: 150px; text-align: center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $conn->query("SELECT * FROM tbl_divisi ORDER BY nama_divisi ASC");
                    $no = 1;
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    ?>
                    <tr>
                        <td align="center" style="vertical-align: middle;"><?php echo $no++; ?></td>
                        <td style="vertical-align: middle;"><?php echo htmlspecialchars($row['nama_divisi']); ?></td>
                        <td align="center" style="vertical-align: middle;">
                            <button class="btn btn-warning btn-xs" onclick="editDivisi('<?php echo $row['id']; ?>', '<?php echo htmlspecialchars($row['nama_divisi']); ?>')">Edit</button>
                            <button class="btn btn-danger btn-xs" onclick="confirmDelete('<?php echo $row['id']; ?>')">Hapus</button>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<!-- Form Invisible for Delete -->
<form method="POST" action="index.php?par=46" id="form_delete" style="display:none;">
    <input type="hidden" name="delete_divisi" value="1">
    <input type="hidden" name="id_del" id="del_id">
</form>

<!-- Modal Add -->
<div class="modal fade" id="addDivisiModal" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Tambah Divisi</h4>
            </div>
            <div class="modal-body">
                <form method="POST" action="index.php?par=46">
                    <div class="form-group">
                        <label>Nama Divisi</label>
                        <input type="text" name="nama_divisi" class="form-control" required autocomplete="off">
                    </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                <button type="submit" name="save_divisi" class="btn btn-primary">Simpan</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="editDivisiModal" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Edit Divisi</h4>
            </div>
            <div class="modal-body">
                <form method="POST" action="index.php?par=46">
                    <input type="hidden" name="id_divisi" id="edit_id">
                    <div class="form-group">
                        <label>Nama Divisi</label>
                        <input type="text" name="nama_divisi" id="edit_nama" class="form-control" required autocomplete="off">
                    </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                <button type="submit" name="update_divisi" class="btn btn-primary">Update</button>
                </form>
            </div>
        </div>
    </div>
</div>
