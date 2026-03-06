<?php
session_start();
include "dbase.php";
include "islogin.php";

// AJAX: return JSON daftar task berdasarkan idlembur
if (isset($_GET['idlembur'])) {
    $idlembur = intval($_GET['idlembur'] ?? 0);
    if ($idlembur <= 0) { echo json_encode([]); exit; }

    $q = $conn->prepare("
        SELECT
            d.id,
            d.tugas,
            d.target,
            ru.nama AS nama_pegawai,
            rc.nmcustomer,
            tl.kesimpulan AS hasil,
            tl.foto
        FROM tdtllembur d
        LEFT JOIN ruser ru ON d.iduser_pegawai = ru.iduser
        LEFT JOIN rcustomer rc ON d.kodcustomer = rc.kodcustomer
        LEFT JOIN ttanggungjawab_lembur tl ON tl.idlembur = :idlembur2 AND tl.idtask = d.id
        WHERE d.idlembur = :idlembur
        ORDER BY d.id
    ");
    $q->execute([':idlembur' => $idlembur, ':idlembur2' => $idlembur]);
    $rows = $q->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {
        if (!empty($row['foto'])) {
            $decoded = json_decode($row['foto'], true);
            $row['foto_list'] = is_array($decoded) ? $decoded : [$row['foto']];
        } else {
            $row['foto_list'] = [];
        }
        unset($row['foto']);
    }

    header('Content-Type: application/json');
    echo json_encode($rows);
    exit;
}

$pesan = "";

if (!isset($_SESSION['DEFAULT_IDUSER']) || empty($_SESSION['DEFAULT_IDUSER'])) {
    die('ERROR: SESSION USER TIDAK VALID');
}

$iduser = $_SESSION['DEFAULT_IDUSER'];

// Simpan pertanggungjawaban lembur (header + detail per task)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['hapus'])) {

    $idlembur      = intval($_POST['idlembur'] ?? 0);
    $status_lembur = $_POST['status_lembur'] ?? '';
    $idtasks       = $_POST['idtask'] ?? [];      // array of tdtllembur.id
    $hasil_list    = $_POST['hasil'] ?? [];        // array of hasil per task

    if (empty($idlembur) || $status_lembur === '') {
        $pesan = "<div class='alert alert-danger'>Pilih pengajuan lembur dan status terlebih dahulu!</div>";
    } else {
        try {
            $conn->beginTransaction();

            // 1. Upsert HEADER ROW (idtask = NULL)
            $cekHeader = $conn->prepare("SELECT id FROM ttanggungjawab_lembur WHERE idlembur = :idlembur AND idtask IS NULL");
            $cekHeader->execute([':idlembur' => $idlembur]);
            if ($cekHeader->rowCount() > 0) {
                $hdr = $conn->prepare("UPDATE ttanggungjawab_lembur SET status_lembur=:status, tgl_update=NOW() WHERE idlembur=:idlembur AND idtask IS NULL");
                $hdr->execute([':status' => $status_lembur, ':idlembur' => $idlembur]);
            } else {
                $hdr = $conn->prepare("INSERT INTO ttanggungjawab_lembur (idlembur, idtask, status_lembur, iduser_pelapor, tgl_lapor) VALUES (:idlembur, NULL, :status, :iduser, NOW())");
                $hdr->execute([':idlembur' => $idlembur, ':status' => $status_lembur, ':iduser' => $iduser]);
            }

            // 2. Loop per task: upsert DETAIL ROW
            foreach ($idtasks as $i => $idtask) {
                $idtask = intval($idtask);
                $hasil  = trim($hasil_list[$i] ?? '');

                // Handle foto upload per task
                $foto_path = null;
                $foto_key  = 'foto_task_' . $idtask;

                if (isset($_FILES[$foto_key]) && !empty($_FILES[$foto_key]['name'][0])) {
                    $upload_dir = 'uploads/lembur/';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

                    $foto_paths = [];
                    foreach ($_FILES[$foto_key]['name'] as $fi => $fname) {
                        if ($_FILES[$foto_key]['error'][$fi] !== UPLOAD_ERR_OK) continue;
                        $ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
                        if (!in_array($ext, ['jpg','jpeg','png','gif'])) continue;
                        $new_name = 'lembur_' . $idlembur . '_t' . $idtask . '_' . time() . '_' . rand(1000,9999) . '.' . $ext;
                        $target   = $upload_dir . $new_name;
                        if (move_uploaded_file($_FILES[$foto_key]['tmp_name'][$fi], $target)) {
                            $foto_paths[] = $target;
                        }
                    }
                    if (!empty($foto_paths)) {
                        $foto_path = count($foto_paths) === 1 ? $foto_paths[0] : json_encode($foto_paths);
                    }
                }

                // Cek apakah detail row sudah ada
                $cekDetail = $conn->prepare("SELECT id, foto FROM ttanggungjawab_lembur WHERE idlembur=:idlembur AND idtask=:idtask");
                $cekDetail->execute([':idlembur' => $idlembur, ':idtask' => $idtask]);
                $existingDetail = $cekDetail->fetch();

                if ($existingDetail) {
                    // Kalau tidak ada foto baru, pertahankan foto lama
                    $foto_save = $foto_path ?? $existingDetail['foto'];
                    $upd = $conn->prepare("UPDATE ttanggungjawab_lembur SET kesimpulan=:hasil, foto=:foto, tgl_update=NOW() WHERE idlembur=:idlembur AND idtask=:idtask");
                    $upd->execute([':hasil' => $hasil, ':foto' => $foto_save, ':idlembur' => $idlembur, ':idtask' => $idtask]);
                } else {
                    $ins = $conn->prepare("INSERT INTO ttanggungjawab_lembur (idlembur, idtask, status_lembur, kesimpulan, foto, iduser_pelapor, tgl_lapor) VALUES (:idlembur, :idtask, '', :hasil, :foto, :iduser, NOW())");
                    $ins->execute([':idlembur' => $idlembur, ':idtask' => $idtask, ':hasil' => $hasil, ':foto' => $foto_path, ':iduser' => $iduser]);
                }
            }

            $conn->commit();
            $pesan = "<div class='alert alert-success'>Data pertanggungjawaban lembur berhasil disimpan!</div>";

        } catch (Exception $e) {
            $conn->rollBack();
            $pesan = "<div class='alert alert-danger'>ERROR: " . $e->getMessage() . "</div>";
        }
    }
}

// Hapus semua data pertanggungjawaban beserta foto terkait
if (isset($_POST['hapus']) && isset($_POST['id_hapus'])) {
    try {
        $idlembur_hapus = intval($_POST['id_hapus']);

        // Hapus semua foto terkait
        $qfoto = $conn->prepare("SELECT foto FROM ttanggungjawab_lembur WHERE idlembur = :idlembur AND idtask IS NOT NULL");
        $qfoto->execute([':idlembur' => $idlembur_hapus]);
        while ($fd = $qfoto->fetch()) {
            if (empty($fd['foto'])) continue;
            $decoded = json_decode($fd['foto'], true);
            if (is_array($decoded)) {
                foreach ($decoded as $f) { if (file_exists($f)) unlink($f); }
            } elseif (file_exists($fd['foto'])) {
                unlink($fd['foto']);
            }
        }

        $conn->prepare("DELETE FROM ttanggungjawab_lembur WHERE idlembur = :idlembur")->execute([':idlembur' => $idlembur_hapus]);
        $pesan = "<div class='alert alert-success'>Data pertanggungjawaban lembur berhasil dihapus!</div>";

    } catch (PDOException $e) {
        $pesan = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Pertanggungjawaban Lembur</title>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
    <style>
        .task-card {
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 12px 15px;
            margin-bottom: 15px;
            background: #fafafa;
        }
        .task-card .task-header {
            background: #e8f4fd;
            border-radius: 4px;
            padding: 8px 12px;
            margin-bottom: 10px;
            font-weight: 600;
            color: #2c7be5;
        }
        .task-meta span {
            display: inline-block;
            margin-right: 15px;
            font-size: 13px;
            color: #555;
        }
        .preview-image {
            max-width: 100px;
            max-height: 100px;
            border: 1px solid #ddd;
            padding: 2px;
            border-radius: 3px;
            margin: 3px;
        }
        .foto-item {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
            padding: 6px 8px;
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
        }
        .foto-item .file-info {
            flex: 1;
            font-size: 12px;
            color: #555;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        #task-container { margin-top: 15px; }
        .existing-foto-thumb img { max-width: 70px; max-height: 70px; border-radius: 3px; border: 1px solid #ccc; margin: 2px; }
    </style>
</head>
<body>
<div class="row">
    <div class="col-md-12">
        <div class="breadcrumb">
            <span class="breadcrumb-title">
                <i class="fa fa-home"></i> PERTANGGUNGJAWABAN LEMBUR
            </span>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <section class="panel">
            <header class="panel-heading">

                <form role="form" method="POST" action="" enctype="multipart/form-data" id="form-spj">

                    <!-- ROW 1: Pilih Lembur + Status -->
                    <div class="row">
                        <div class="form-group col-md-6">
                            <label>Pilih Pengajuan Lembur <span style="color:red;">*</span></label>
                            <select name="idlembur" id="idlembur" class="form-control" required>
                                <option value="">-- Pilih Pengajuan Lembur --</option>
                                <?php
                                $qlembur = $conn->prepare("
                                    SELECT l.id,
                                           l.latarbelakang,
                                           DATE_FORMAT(l.tgl_lembur, '%d/%m/%Y') as tgl_formatted,
                                           COALESCE(t.id, 0) as has_report,
                                           GROUP_CONCAT(DISTINCT rc.nmcustomer ORDER BY rc.nmcustomer SEPARATOR ', ') as customers
                                    FROM tlembur l
                                    LEFT JOIN ttanggungjawab_lembur t ON l.id = t.idlembur AND t.idtask IS NULL
                                    LEFT JOIN tdtllembur d ON d.idlembur = l.id
                                    LEFT JOIN rcustomer rc ON d.kodcustomer = rc.kodcustomer
                                    WHERE l.iduser_pengaju IS NOT NULL
                                      AND l.status_approval = 'Approved'
                                    GROUP BY l.id
                                    ORDER BY l.tgl_lembur DESC
                                ");
                                $qlembur->execute();
                                while($lembur = $qlembur->fetch()) {
                                    $customer_label = !empty($lembur['customers']) ? $lembur['customers'] : 'Internal';
                                    $label = $lembur['tgl_formatted'].' - '.$customer_label;
                                    $badge = $lembur['has_report'] > 0 ? ' ✓' : '';
                                    echo "<option value='{$lembur['id']}'>{$label}{$badge}</option>";
                                }
                                ?>
                            </select>
                            <small class="text-dark">Tanda ✓ menandakan sudah ada laporan</small>
                        </div>

                        <div class="form-group col-md-4">
                            <label>Status Lembur <span style="color:red;">*</span></label>
                            <select name="status_lembur" id="status_lembur" class="form-control" required>
                                <option value="">-- Pilih Status --</option>
                                <option value="1">Selesai</option>
                                <option value="0">Tidak Selesai</option>
                            </select>
                        </div>
                    </div>

                    <!-- TASK LIST (auto-load via AJAX) -->
                    <div id="task-container" style="display:none;">
                        <div class="alert alert-info" style="padding:8px 12px; font-size:13px;">
                            <i class="fa fa-info-circle"></i>
                            Isi <strong>Hasil</strong> dan upload minimal <strong>1 foto</strong> untuk setiap tugas.
                        </div>
                        <div id="task-list"></div>
                    </div>

                    <div id="loading-tasks" style="display:none; padding:10px; color:#888;">
                        <i class="fa fa-spinner fa-spin"></i> Memuat daftar tugas...
                    </div>

                    <!-- BUTTON -->
                    <div class="row" style="margin-top:10px;">
                        <div class="col-md-12 text-right">
                            <button type="submit" class="btn btn-primary" id="btn-simpan" style="display:none;">
                                <i class="fa fa-save"></i> Simpan
                            </button>
                            <button type="reset" class="btn btn-danger" onclick="resetForm()">Reset</button>
                        </div>
                    </div>

                </form>

                <?php if ($pesan): ?>
                    <div style="margin-top:10px;"><?= $pesan ?></div>
                <?php endif; ?>

            </header>

            <!-- TABLE DAFTAR -->
            <section class="content">
                <div align="center"><strong>DAFTAR PERTANGGUNGJAWABAN LEMBUR</strong></div>

                <div class="box-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="daftar-tanggungjawab-lembur">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Tanggal</th>
                                    <th>Jam Mulai</th>
                                    <th>Latar Belakang</th>
                                    <th>Status</th>
                                    <th>Jumlah Task</th>
                                    <th>Penanggung Jawab</th>
                                    <th>Tanggal Laporan</th>
                                    <th style="width: 100px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            $no = 1;
                            // Ambil per lembur (header row = idtask IS NULL)
                            $q = $conn->prepare("
                                SELECT 
                                    t.id, t.idlembur, t.status_lembur, t.tgl_lapor,
                                    l.latarbelakang, l.tgl_lembur, l.jam_mulai,
                                    ru.nama,
                                    (SELECT COUNT(*) FROM ttanggungjawab_lembur td WHERE td.idlembur = t.idlembur AND td.idtask IS NOT NULL) as jumlah_task,
                                    (SELECT COUNT(*) FROM ttanggungjawab_lembur td WHERE td.idlembur = t.idlembur AND td.idtask IS NOT NULL AND td.foto IS NOT NULL) as task_dengan_foto
                                FROM ttanggungjawab_lembur t
                                LEFT JOIN tlembur l ON t.idlembur = l.id
                                LEFT JOIN ruser ru ON t.iduser_pelapor = ru.iduser
                                WHERE t.idtask IS NULL
                                ORDER BY t.tgl_lapor DESC
                            ");
                            $q->execute();

                            while ($row = $q->fetch()) {
                                $status = ($row['status_lembur'] == '1')
                                    ? '<span class="label label-success">Selesai</span>'
                                    : '<span class="label label-warning">Tidak Selesai</span>';

                                $task_info = $row['task_dengan_foto'] . '/' . $row['jumlah_task'] . ' task';
                            ?>
                                <tr>
                                    <td><?= $no ?></td>
                                    <td><?= date('d/m/Y', strtotime($row['tgl_lembur'])) ?></td>
                                    <td><?= $row['jam_mulai'] ?></td>
                                    <td><?= nl2br(htmlspecialchars($row['latarbelakang'])) ?></td>
                                    <td><?= $status ?></td>
                                    <td><span class="label label-info"><?= $task_info ?></span></td>
                                    <td><?= htmlspecialchars($row['nama']) ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($row['tgl_lapor'])) ?></td>
                                    <td>
                                        <div style="display:flex; gap:5px; justify-content:center;">
                                            <a href="36_cetak_spj_lembur.php?id=<?= $row['id'] ?>"
                                               class="btn btn-sm btn-info">Cetak</a>
                                            <button type="button"
                                                    onclick="confirmDelete(<?= $row['idlembur'] ?>)"
                                                    class="btn btn-sm btn-danger">Hapus</button>
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
</div>

<!-- Form tersembunyi untuk hapus -->
<form id="form_hapus" method="POST" action="" style="display:none;">
    <input type="hidden" name="hapus" value="1">
    <input type="hidden" name="id_hapus" id="id_hapus">
</form>

<!-- Modal Lihat Foto -->
<div class="modal fade" id="modalFoto" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Foto Lembur</h4>
            </div>
            <div class="modal-body" id="modalFotoBody" style="text-align:center;"></div>
        </div>
    </div>
</div>

</body>
</html>

<script>
// =============================================
// AJAX: Load tasks saat lembur dipilih
// =============================================
$('#idlembur').on('change', function() {
    var idlembur = $(this).val();
    if (!idlembur) {
        $('#task-container').hide();
        $('#btn-simpan').hide();
        $('#task-list').html('');
        return;
    }

    $('#loading-tasks').show();
    $('#task-container').hide();

    $.getJSON('36_tanggungjwb_lembur.php', { idlembur: idlembur }, function(tasks) {
        $('#loading-tasks').hide();
        if (!tasks || tasks.length === 0) {
            $('#task-list').html('<div class="alert alert-warning">Tidak ada tugas ditemukan untuk pengajuan ini.</div>');
            $('#task-container').show();
            return;
        }

        var html = '';
        tasks.forEach(function(task, idx) {
            var fotoHtml = '';

            // Tampilkan foto yang sudah ada
            if (task.foto_list && task.foto_list.length > 0) {
                fotoHtml += '<div class="existing-foto-thumb" style="margin-bottom:6px;">';
                task.foto_list.forEach(function(f) {
                    fotoHtml += '<img src="' + f + '" title="Foto tersimpan">';
                });
                fotoHtml += '<br><small class="text-muted">Foto tersimpan (upload baru untuk mengganti)</small>';
                fotoHtml += '</div>';
            }

            html += '<div class="task-card">';
            html += '<input type="hidden" name="idtask[]" value="' + task.id + '">';

            // Header task
            html += '<div class="task-header"><i class="fa fa-tasks"></i> ' + escHtml(task.tugas) + '</div>';

            // Meta info
            html += '<div class="task-meta" style="margin-bottom:10px;">';
            if (task.target) html += '<span><i class="fa fa-flag text-warning"></i> Target: <strong>' + escHtml(task.target) + '</strong></span>';
            html += '<span><i class="fa fa-user text-primary"></i> Pegawai: <strong>' + escHtml(task.nama_pegawai) + '</strong></span>';
            if (task.nmcustomer) html += '<span><i class="fa fa-building text-success"></i> Customer: <strong>' + escHtml(task.nmcustomer) + '</strong></span>';
            html += '</div>';

            // Hasil
            html += '<div class="form-group">';
            html += '<label>Hasil <span style="color:red;">*</span></label>';
            html += '<textarea name="hasil[]" class="form-control" rows="2" placeholder="Deskripsikan hasil untuk tugas ini..." required>' + escHtml(task.hasil || '') + '</textarea>';
            html += '</div>';

            // Foto
            html += '<div class="form-group">';
            html += '<label>Foto <span style="color:red;">*</span></label>';
            html += fotoHtml;
            html += '<div id="foto-list-' + task.id + '"></div>';
            html += '<button type="button" class="btn btn-sm btn-success" onclick="tambahFoto(' + task.id + ')">';
            html += '<i class="fa fa-plus"></i> Tambah Foto</button>';
            html += '<small class="text-dark" style="margin-left:8px;">JPG, PNG, GIF (Max 5MB)</small>';
            html += '<input type="file" id="foto-picker-' + task.id + '" accept="image/*" style="display:none;" onchange="handleFoto(this, ' + task.id + ')">';
            html += '</div>';

            html += '</div>'; // end task-card
        });

        $('#task-list').html(html);
        $('#task-container').show();
        $('#btn-simpan').show();

    }).fail(function() {
        $('#loading-tasks').hide();
        $('#task-list').html('<div class="alert alert-danger">Gagal memuat daftar tugas.</div>');
        $('#task-container').show();
    });
});

// =============================================
// FOTO per Task
// =============================================
var fotoCounters = {};
var fotoFilesMap = {};

function tambahFoto(taskId) {
    $('#foto-picker-' + taskId).val('').trigger('click');
}

function handleFoto(input, taskId) {
    if (!input.files || !input.files[0]) return;
    var file = input.files[0];
    var ext = file.name.split('.').pop().toLowerCase();
    var allowed = ['jpg','jpeg','png','gif'];

    if (!allowed.includes(ext)) { alert('Format file tidak diizinkan!'); return; }
    if (file.size > 5 * 1024 * 1024) { alert('Ukuran file maksimal 5MB!'); return; }

    if (!fotoCounters[taskId]) fotoCounters[taskId] = 0;
    if (!fotoFilesMap[taskId]) fotoFilesMap[taskId] = {};
    var id = 'ft_' + taskId + '_' + (fotoCounters[taskId]++);
    fotoFilesMap[taskId][id] = file;

    var reader = new FileReader();
    reader.onload = function(e) {
        var html = '<div class="foto-item" id="item_' + id + '">' +
            '<img src="' + e.target.result + '" class="preview-image">' +
            '<div class="file-info">' + file.name + ' (' + formatSize(file.size) + ')</div>' +
            '<button type="button" class="btn btn-xs btn-danger" onclick="removeFotoTask(\'' + id + '\',' + taskId + ')">' +
            '<i class="fa fa-times"></i></button>' +
            '</div>';
        $('#foto-list-' + taskId).append(html);
        syncTaskFotoInputs(taskId);
    };
    reader.readAsDataURL(file);
}

function removeFotoTask(id, taskId) {
    delete fotoFilesMap[taskId][id];
    $('#item_' + id).remove();
    syncTaskFotoInputs(taskId);
}

function syncTaskFotoInputs(taskId) {
    // Inject file inputs ke form saat submit
    // (ditangani di submit handler)
}

// Saat submit: inject semua file inputs ke form
$('#form-spj').on('submit', function(e) {
    // Hapus input lama
    $(this).find('.dynamic-foto-input').remove();

    var hasError = false;

    // Validasi setiap task harus ada foto (baru atau lama)
    $('.task-card').each(function() {
        var taskId = $(this).find('input[name="idtask[]"]').val();
        var hasFotoLama = $(this).find('.existing-foto-thumb').length > 0;
        var hasFotoBaru = fotoFilesMap[taskId] && Object.keys(fotoFilesMap[taskId]).length > 0;
        if (!hasFotoLama && !hasFotoBaru) {
            alert('Setiap tugas wajib memiliki minimal 1 foto!');
            hasError = true;
            return false; // break
        }
    });

    if (hasError) { e.preventDefault(); return false; }

    // Inject file inputs per task
    var form = this;
    for (var taskId in fotoFilesMap) {
        var files = fotoFilesMap[taskId];
        for (var fid in files) {
            var input = document.createElement('input');
            input.type = 'file';
            input.name = 'foto_task_' + taskId + '[]';
            input.className = 'dynamic-foto-input';
            input.style.display = 'none';
            var dt = new DataTransfer();
            dt.items.add(files[fid]);
            input.files = dt.files;
            form.appendChild(input);
        }
    }
});

function resetForm() {
    $('#task-container').hide();
    $('#task-list').html('');
    $('#btn-simpan').hide();
    fotoCounters = {};
    fotoFilesMap = {};
}

function confirmDelete(idlembur) {
    if (confirm('Hapus semua data pertanggungjawaban untuk lembur ini?')) {
        document.getElementById('id_hapus').value = idlembur;
        document.getElementById('form_hapus').submit();
    }
}

function lihatSemuaFoto(jsonFoto) {
    var arr = [];
    try { arr = JSON.parse(jsonFoto); } catch(e) { alert('Gagal memuat foto'); return; }
    var html = '';
    arr.forEach(function(f) {
        html += '<img src="' + f + '" style="max-width:100%; margin-bottom:10px; border:1px solid #ddd; padding:5px;">';
    });
    document.getElementById('modalFotoBody').innerHTML = html;
    $('#modalFoto').modal('show');
}

function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024*1024) return (bytes/1024).toFixed(1) + ' KB';
    return (bytes/(1024*1024)).toFixed(1) + ' MB';
}

function escHtml(str) {
    if (!str) return '';
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// DataTable
$(document).ready(function () {
    var tableId = '#daftar-tanggungjawab-lembur';
    if ($.fn.DataTable.isDataTable(tableId)) $(tableId).DataTable().destroy();
    $(tableId).DataTable({
        pageLength: 10,
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