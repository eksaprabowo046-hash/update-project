<?php
// 50_input_jabatan.php
// Sub-menu of Input User to manage Jabatan (Positions)
session_start();
include "dbase.php";
include "islogin.php";

// Access Control - Hanya Admin (kodjab=1)
if ($kodjab != 1 && $kodjab != 2) {
    echo "<script>alert('Anda tidak memiliki akses ke halaman ini!'); window.location='index.php';</script>";
    exit;
}

// Auto-create tabel jika belum ada
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS tbl_jabatan (
        kodjab INT(11) NOT NULL,
        nama_jabatan VARCHAR(100) NOT NULL,
        PRIMARY KEY (kodjab)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
} catch (PDOException $e) {
    // Abaikan error, lanjutkan proses
}

// Handle Form Submissions
$pesan = "";

// TAMBAH JABATAN
if (isset($_POST['save_jabatan'])) {
    try {
        $kodjab_new = intval($_POST['kodjab_new']);
        $nama = trim($_POST['nama_jabatan']);
        if (!empty($nama) && $kodjab_new > 0) {
            // Cek apakah kodjab sudah ada
            $cek = $conn->prepare("SELECT kodjab FROM tbl_jabatan WHERE kodjab = ?");
            $cek->execute([$kodjab_new]);
            if ($cek->rowCount() > 0) {
                $pesan = "<div class='alert alert-danger'>Kode Jabatan <strong>$kodjab_new</strong> sudah ada!</div>";
            } else {
                $stmt = $conn->prepare("INSERT INTO tbl_jabatan (kodjab, nama_jabatan) VALUES (?, ?)");
                $stmt->execute([$kodjab_new, $nama]);
                $pesan = "<div class='alert alert-success'>Jabatan berhasil ditambahkan!</div>";
            }
        }
    } catch (PDOException $e) {
        $pesan = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
    }
}

// UPDATE JABATAN
if (isset($_POST['update_jabatan'])) {
    try {
        $kodjab_edit = intval($_POST['kodjab_edit']);
        $nama = trim($_POST['nama_jabatan']);
        if (!empty($nama)) {
            $stmt = $conn->prepare("UPDATE tbl_jabatan SET nama_jabatan=? WHERE kodjab=?");
            $stmt->execute([$nama, $kodjab_edit]);
            $pesan = "<div class='alert alert-success'>Jabatan berhasil diupdate!</div>";
        }
    } catch (PDOException $e) {
        $pesan = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
    }
}

// DELETE JABATAN
if (isset($_POST['delete_jabatan'])) {
    try {
        $kodjab_del = intval($_POST['kodjab_del']);
        
        // Cek apakah ada user yang pakai kodjab ini
        $cek_user = $conn->prepare("SELECT COUNT(*) as jml FROM ruser WHERE kodjab = ?");
        $cek_user->execute([$kodjab_del]);
        $jml = $cek_user->fetch(PDO::FETCH_ASSOC)['jml'];
        
        if ($jml > 0) {
            $pesan = "<div class='alert alert-danger'>Tidak bisa menghapus! Ada <strong>$jml user</strong> yang menggunakan jabatan ini.</div>";
        } else {
            $stmt = $conn->prepare("DELETE FROM tbl_jabatan WHERE kodjab=?");
            $stmt->execute([$kodjab_del]);
            $pesan = "<div class='alert alert-success'>Jabatan berhasil dihapus!</div>";
        }
    } catch (PDOException $e) {
        $pesan = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
    }
}
?>

<script>
function editJabatan(kodjab, nama) {
    document.getElementById('edit_kodjab').value = kodjab;
    document.getElementById('edit_kodjab_display').value = kodjab;
    document.getElementById('edit_nama').value = nama;
    $('#editJabatanModal').modal('show');
}

function confirmDeleteJabatan(kodjab, nama) {
    if(confirm("Yakin ingin menghapus jabatan '" + nama + "'?")) {
        document.getElementById('del_kodjab').value = kodjab;
        document.getElementById('form_delete_jabatan').submit();
    }
}
</script>

<div class="row">
    <ol class="breadcrumb">
        <li><i class="fa fa-home"></i> INPUT USER / KELOLA JABATAN</li>
    </ol>
    <section class="panel">
        <header class="panel-heading">
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addJabatanModal" style="margin-top: 15px;">
              <i class="fa fa-plus"></i> Tambah Jabatan
            </button>
            <br><br>
            <?php if (!empty($pesan)) echo $pesan; ?>
        </header>
        <div class="panel-body">
            <table class="table table-bordered table-striped table-hover">
                <thead>
                    <tr>
                        <th style="width: 50px; text-align: center;">No</th>
                        <th style="width: 100px; text-align: center;">Kode Jabatan</th>
                        <th>Nama Jabatan</th>
                        <th style="width: 80px; text-align: center;">Jumlah User</th>
                        <th style="width: 150px; text-align: center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $conn->query("SELECT j.*, (SELECT COUNT(*) FROM ruser WHERE ruser.kodjab = j.kodjab) AS jml_user FROM tbl_jabatan j ORDER BY j.kodjab ASC");
                    $no = 1;
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    ?>
                    <tr>
                        <td align="center" style="vertical-align: middle;"><?php echo $no++; ?></td>
                        <td align="center" style="vertical-align: middle;"><strong><?php echo $row['kodjab']; ?></strong></td>
                        <td style="vertical-align: middle;">
                            <?php echo htmlspecialchars($row['nama_jabatan']); ?>
                            <?php if ($row['kodjab'] == 1) echo " <span class='label label-info'>Admin</span>"; ?>
                        </td>
                        <td align="center" style="vertical-align: middle;">
                            <span class="badge"><?php echo $row['jml_user']; ?></span>
                        </td>
                        <td align="center" style="vertical-align: middle;">
                            <button class="btn btn-warning btn-xs" onclick="editJabatan('<?php echo $row['kodjab']; ?>', '<?php echo htmlspecialchars($row['nama_jabatan']); ?>')">Edit</button>
                            <button class="btn btn-danger btn-xs" onclick="confirmDeleteJabatan('<?php echo $row['kodjab']; ?>', '<?php echo htmlspecialchars($row['nama_jabatan']); ?>')">Hapus</button>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<!-- Form Invisible for Delete -->
<form method="POST" action="index.php?par=50" id="form_delete_jabatan" style="display:none;">
    <input type="hidden" name="delete_jabatan" value="1">
    <input type="hidden" name="kodjab_del" id="del_kodjab">
</form>

<!-- Modal Add -->
<div class="modal fade" id="addJabatanModal" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Tambah Jabatan</h4>
            </div>
            <div class="modal-body">
                <form method="POST" action="index.php?par=50">
                    <div class="form-group">
                        <label>Kode Jabatan</label>
                        <input type="number" name="kodjab_new" class="form-control" required autocomplete="off" min="1" placeholder="Contoh: 6">
                        <small class="text-muted">Kode unik untuk jabatan ini (angka). Kode 1 = Admin.</small>
                    </div>
                    <div class="form-group">
                        <label>Nama Jabatan</label>
                        <input type="text" name="nama_jabatan" class="form-control" required autocomplete="off" placeholder="Contoh: Supervisor">
                    </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                <button type="submit" name="save_jabatan" class="btn btn-primary">Simpan</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="editJabatanModal" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Edit Jabatan</h4>
            </div>
            <div class="modal-body">
                <form method="POST" action="index.php?par=50">
                    <input type="hidden" name="kodjab_edit" id="edit_kodjab">
                    <div class="form-group">
                        <label>Kode Jabatan</label>
                        <input type="text" id="edit_kodjab_display" class="form-control" readonly>
                        <small class="text-muted">Kode jabatan tidak bisa diubah.</small>
                    </div>
                    <div class="form-group">
                        <label>Nama Jabatan</label>
                        <input type="text" name="nama_jabatan" id="edit_nama" class="form-control" required autocomplete="off">
                    </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                <button type="submit" name="update_jabatan" class="btn btn-primary">Update</button>
                </form>
            </div>
        </div>
    </div>
</div>
