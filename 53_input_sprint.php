<?php
include "dbase.php";
$pesan = "";

// Auto-migrate: pastikan kolom idsprint ada di tabel tlog
try {
    if ($conn->query("SHOW COLUMNS FROM tlog LIKE 'idsprint'")->rowCount() == 0) {
        $conn->exec("ALTER TABLE tlog ADD COLUMN idsprint INT NULL");
    }
} catch (PDOException $e) {}

// Auto-migrate: pastikan kolom file_uploads ada di tabel tsprint
try {
    if ($conn->query("SHOW COLUMNS FROM tsprint LIKE 'file_uploads'")->rowCount() == 0) {
        $conn->exec("ALTER TABLE tsprint ADD COLUMN file_uploads TEXT NULL");
    }
} catch (PDOException $e) {}

// ===== AJAX: Get File Attachments Sprint =====
if (isset($_GET['ajax_sprint_files'])) {
    while (ob_get_level()) ob_end_clean();
    $sid = intval($_GET['idsprint']);
    try {
        $q = $conn->prepare("SELECT file_uploads FROM tsprint WHERE idsprint=?");
        $q->execute([$sid]);
        $r = $q->fetch();
        $files = !empty($r['file_uploads']) ? json_decode($r['file_uploads'], true) : [];
        if (!empty($files)) {
            echo '<div style="padding:10px 15px;">';
            echo '<strong><i class="fa fa-paperclip"></i> Lampiran Sprint:</strong><br><br>';
            foreach ($files as $f) {
                $fname = htmlspecialchars($f['filename']);
                $oname = htmlspecialchars($f['original_name'] ?? $f['filename']);
                $desc  = htmlspecialchars($f['description'] ?? '');
                $ftype = $f['file_type'] ?? 'file';
                $fpath = 'uploads/sprint/' . $fname;
                if ($ftype === 'image') {
                    echo '<div style="display:inline-block;margin:5px;text-align:center;vertical-align:top;">';
                    echo '<a href="'.$fpath.'" target="_blank">';
                    echo '<img src="'.$fpath.'" style="max-width:120px;max-height:100px;border:1px solid #ddd;border-radius:4px;padding:2px;">';
                    echo '</a>';
                    if ($desc) echo '<br><small style="color:#666;">'.$desc.'</small>';
                    echo '</div>';
                } else {
                    echo '<div style="margin:4px 0;">';
                    echo '<i class="fa fa-file"></i> <a href="'.$fpath.'" target="_blank">'.$oname.'</a>';
                    if ($desc) echo ' <small style="color:#888;">— '.$desc.'</small>';
                    echo '</div>';
                }
            }
            echo '</div>';
        } else {
            echo '<div style="padding:10px 15px;color:#999;"><em>Tidak ada lampiran untuk sprint ini.</em></div>';
        }
    } catch (PDOException $e) {
        echo '<div style="color:red;">Error: '.$e->getMessage().'</div>';
    }
    exit;
}

// ===== AJAX: Get Log Harian terkait Sprint =====
if (isset($_GET['ajax_log_detail'])) {
    while (ob_get_level()) ob_end_clean();
    $sid = intval($_GET['idsprint']);
    try {
        $q = $conn->prepare("SELECT a.idlog, a.tglorder, a.desorder, a.deslayan, b.nmcustomer,
                              COALESCE(u.nama, a.userorder) as nama_dikerjakan,
                              a.isselesai, a.tgltarget
                              FROM tlog a
                              LEFT JOIN rcustomer b ON a.kodcustomer = b.kodcustomer
                              LEFT JOIN ruser u ON a.userorder = u.iduser
                              WHERE a.idsprint = ? AND a.stsdel = 0
                              ORDER BY a.tglorder ASC");
        $q->execute([$sid]);
        $no = 1;
        while ($r = $q->fetch()) {
            $statusBadge = $r['isselesai'] == 1
                ? '<span class="label label-success">Selesai</span>'
                : '<span class="label label-warning">Open</span>';
            $desText = strip_tags(stripslashes($r['desorder'] ?? ''));
            $desText = mb_strlen($desText) > 70 ? mb_substr($desText, 0, 70) . '...' : $desText;
            echo "<tr>
                <td align=center><font size=-1>{$no}</font></td>
                <td><font size=-1>{$r['tglorder']}</font></td>
                <td><font size=-1>".htmlspecialchars($r['nmcustomer'])."</font></td>
                <td><font size=-1>".htmlspecialchars($r['nama_dikerjakan'])."</font></td>
                <td><font size=-1>".htmlspecialchars($desText)."</font></td>
                <td align=center>{$statusBadge}</td>
            </tr>";
            $no++;
        }
        if ($no == 1) {
            echo "<tr><td colspan='6' align='center'><em>Belum ada log harian yang ditautkan ke sprint ini.<br><small>Buat log di menu Create Log dan pilih sprint ini.</small></em></td></tr>";
        }
    } catch (PDOException $e) {
        echo "<tr><td colspan='6' style='color:red;'>Error: ".$e->getMessage()."</td></tr>";
    }
    exit;
}

// ===== AJAX: Get Sprint Stats (dari log harian) =====
if (isset($_GET['ajax_stats'])) {
    while (ob_get_level()) ob_end_clean();
    $sid = intval($_GET['idsprint']);
    try {
        $q = $conn->prepare("SELECT
            COUNT(*) as total,
            SUM(CASE WHEN isselesai=1 THEN 1 ELSE 0 END) as selesai,
            SUM(CASE WHEN isselesai=0 THEN 1 ELSE 0 END) as open_count
            FROM tlog WHERE idsprint=? AND stsdel=0");
        $q->execute([$sid]);
        $r = $q->fetch();
        echo json_encode($r);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// ===== INSERT Sprint =====
if (isset($_POST['ins_sprint'])) {
    $judul = trim($_POST['judul']);
    $tgl_mulai = trim($_POST['tgl_mulai']);
    $tgl_selesai = trim($_POST['tgl_selesai']);
    
    if (empty($judul) || empty($tgl_mulai) || empty($tgl_selesai)) {
        $pesan = "<font color=red>Semua field harus diisi!</font>";
    } else {
        try {
            $sql = "INSERT INTO tsprint (judul, tgl_mulai, tgl_selesai, status, iduser_create) VALUES (?, ?, ?, 'aktif', ?)";
            $conn->prepare($sql)->execute([$judul, $tgl_mulai, $tgl_selesai, $iduser]);
            $newSprintId = $conn->lastInsertId();

            // ===== Proses Multiple File Upload =====
            if ($newSprintId && isset($_FILES['file_upload']) && is_array($_FILES['file_upload']['name'])) {
                $uploadDir   = 'uploads/sprint/';
                if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
                $allowedExt  = ['jpg','jpeg','png','gif','pdf','doc','docx','xls','xlsx','mp4','avi','mov'];
                $allowedImg  = ['jpg','jpeg','png','gif'];
                $maxSize     = 50 * 1024 * 1024;
                $descriptions = isset($_POST['file_desc']) ? $_POST['file_desc'] : [];
                $savedFiles  = [];
                foreach ($_FILES['file_upload']['name'] as $i => $origName) {
                    if ($_FILES['file_upload']['error'][$i] !== UPLOAD_ERR_OK) continue;
                    if (empty($origName)) continue;
                    $tmpName = $_FILES['file_upload']['tmp_name'][$i];
                    $fileSize = $_FILES['file_upload']['size'][$i];
                    $fileExt = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                    $desc = isset($descriptions[$i]) ? trim($descriptions[$i]) : '';
                    if (!in_array($fileExt, $allowedExt) || $fileSize > $maxSize) continue;
                    $newFileName = 'sprint_' . $newSprintId . '_' . date('Ymd_His') . '_' . $i . '.' . $fileExt;
                    if (move_uploaded_file($tmpName, $uploadDir . $newFileName)) {
                        $savedFiles[] = [
                            'filename'      => $newFileName,
                            'original_name' => $origName,
                            'file_size'     => $fileSize,
                            'file_type'     => in_array($fileExt, $allowedImg) ? 'image' : 'file',
                            'extension'     => $fileExt,
                            'description'   => $desc,
                            'upload_date'   => date('Y-m-d H:i:s'),
                        ];
                    }
                }
                if (!empty($savedFiles)) {
                    $conn->prepare("UPDATE tsprint SET file_uploads=? WHERE idsprint=?")
                         ->execute([json_encode($savedFiles), $newSprintId]);
                }
            }

            $pesan = "<font color=blue>Sprint <strong>".htmlspecialchars($judul)."</strong> berhasil dibuat!</font>";
        } catch (PDOException $e) {
            $pesan = "<font color=red>Error: ".$e->getMessage()."</font>";
        }
    }
}

// ===== UPDATE Sprint Status =====
if (isset($_GET['toggle_sprint'])) {
    $sid = intval($_GET['idsprint']);
    $newStatus = $_GET['toggle_sprint'] == 'selesai' ? 'selesai' : 'aktif';
    try {
        $conn->prepare("UPDATE tsprint SET status=? WHERE idsprint=?")->execute([$newStatus, $sid]);
        $pesan = "<font color=blue>Status sprint berhasil diubah ke <strong>{$newStatus}</strong>!</font>";
    } catch (PDOException $e) {
        $pesan = "<font color=red>Error: ".$e->getMessage()."</font>";
    }
}

// ===== DELETE Sprint =====
if (isset($_GET['del_sprint'])) {
    $sid = intval($_GET['idsprint']);
    try {
        $conn->prepare("DELETE FROM tsprint WHERE idsprint=?")->execute([$sid]);
        $pesan = "<font color=blue>Sprint berhasil dihapus!</font>";
    } catch (PDOException $e) {
        $pesan = "<font color=red>Error: ".$e->getMessage()."</font>";
    }
}
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>

<style>
.progress { margin-bottom: 0; }
</style>
<script>
function fixProgressColors() {
    var bars = document.querySelectorAll('.progress-bar');
    bars.forEach(function(bar) {
        var pct = parseInt(bar.textContent) || 0;
        bar.className = 'progress-bar';
        if (pct >= 100)      bar.className += ' progress-bar-success';
        else if (pct >= 50)  bar.className += ' progress-bar-warning';
        else if (pct > 0)    bar.className += ' progress-bar-danger';
        else                 bar.style.background = '#ccc';
    });
}
// Run multiple times to catch DataTables render
setTimeout(fixProgressColors, 300);
setTimeout(fixProgressColors, 800);
setTimeout(fixProgressColors, 1500);
// Hook into DataTables draw event
$(document).ready(function(){
    $('#contoh').on('draw.dt', fixProgressColors);
    fixProgressColors();
});
</script>

<body>
<div class="row">
    <ol class="breadcrumb">
        <li><i class="fa fa-tasks"></i> INPUT SPRINT (PLAN)</li>
    </ol>
    
    <section class="panel">
        <header class="panel-heading">
            <form role="form" method="POST" action="index.php?par=53" enctype="multipart/form-data">
                <div class="form-group col-xs-12 col-sm-4">
                    <label>Judul Sprint</label>
                    <input name="judul" type="text" class="form-control" placeholder="Contoh: Sprint Minggu ke-9" required>
                </div>
                <div class="form-group col-xs-6 col-sm-2">
                    <label>Tanggal Mulai</label>
                    <input name="tgl_mulai" id="dp_mulai" type="text" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group col-xs-6 col-sm-2">
                    <label>Tanggal Selesai</label>
                    <input name="tgl_selesai" id="dp_selesai" type="text" class="form-control" value="<?php echo date('Y-m-d', strtotime('+6 days')); ?>" required>
                </div>
                <div class="form-group col-xs-12 col-sm-2" style="margin-top:24px;">
                    <input type="hidden" name="ins_sprint" value="Y">
                    <button type="submit" class="btn btn-primary"><i class="fa fa-plus"></i> Buat Sprint</button>
                </div>
                <div class="clearfix"></div>
                <div class="form-group col-xs-12" style="margin-top:8px;">
                    <label><i class="fa fa-paperclip"></i> Lampiran / File Referensi <small class="text-muted">(opsional)</small></label>
                    <div id="uploadRowsSprint"></div>
                    <button type="button" class="btn btn-default btn-xs" onclick="tambahBarisSprint()">
                        <i class="fa fa-plus"></i> Tambah File
                    </button>
                    <small class="text-muted" style="margin-left:8px;">Format: jpg, png, pdf, doc, xls, mp4 — maks 50MB per file</small>
                </div>
            </form>
            <div class="clearfix"></div>
            <h4><font color="red"><?php echo $pesan; ?></font></h4>
        </header>
        
        <section class="content">
            <div align="center"><strong>DAFTAR SPRINT</strong></div>
            <div class="box-body">
                <table id="contoh" class="table table-bordered table-striped table-hover">
                    <thead>
                        <tr>
                            <th style="width:40px;">#</th>
                            <th>Judul Sprint</th>
                            <th>Mulai</th>
                            <th>Selesai</th>
                            <th>Status</th>
                            <th style="width:200px;">Progress</th>
                            <th>Dibuat Oleh</th>
                            <th style="width:180px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    try {
                        $strsql = "SELECT s.*, u.nama as creator_nama,
                            (SELECT COUNT(*) FROM tlog WHERE idsprint=s.idsprint AND stsdel=0) as log_total,
                            (SELECT COUNT(*) FROM tlog WHERE idsprint=s.idsprint AND stsdel=0 AND isselesai=1) as log_selesai,
                            (SELECT COUNT(*) FROM tlog WHERE idsprint=s.idsprint AND stsdel=0 AND isselesai=0) as log_proses
                            FROM tsprint s 
                            LEFT JOIN ruser u ON s.iduser_create=u.iduser
                            ORDER BY s.idsprint DESC";
                        $sql = $conn->prepare($strsql);
                        $sql->execute();
                        $no = 1;
                        while ($rs = $sql->fetch()) {
                            $statusBadge = $rs['status'] == 'aktif' 
                                ? '<span class="label label-primary">Aktif</span>' 
                                : '<span class="label label-success">Selesai</span>';
                            
                            $logTotal   = intval($rs['log_total'] ?? 0);
                            $logSelesai = intval($rs['log_selesai'] ?? 0);
                            $avgProg = $logTotal > 0 ? intval(($logSelesai / $logTotal) * 100) : 0;
                            if ($avgProg >= 100)    { $barClass = 'progress-bar-success'; }
                            elseif ($avgProg >= 50) { $barClass = 'progress-bar-info'; }
                            elseif ($avgProg > 0)   { $barClass = 'progress-bar-warning'; }
                            else                    { $barClass = ''; }
                            $barWidth = max($avgProg, 8);
                            $barBg = ($avgProg == 0) ? 'background:#ccc;' : '';
                            $progressBar = '<div class="progress" style="margin:0;"><div class="progress-bar '.$barClass.'" style="width:'.$barWidth.'%;'.$barBg.'">' . $avgProg . '%</div></div><small>' . $logSelesai . '/' . $logTotal . ' log selesai</small>';

                            
                            $toggleBtn = $rs['status'] == 'aktif'
                                ? "<a href='index.php?par=53&toggle_sprint=selesai&idsprint={$rs['idsprint']}' class='btn btn-xs btn-success' style='margin: 0 2px;' title='Selesaikan'><i class='fa fa-check'></i></a>"
                                : "<a href='index.php?par=53&toggle_sprint=aktif&idsprint={$rs['idsprint']}' class='btn btn-xs btn-warning' style='margin: 0 2px;' title='Aktifkan kembali'><i class='fa fa-undo'></i></a>";

                            echo "<tr>
                                <td align=center><font size=-1>{$no}</font></td>
                                <td><font size=-1><a href='javascript:void(0)' onclick=\"showDetail({$rs['idsprint']}, '".addslashes($rs['judul'])."')\" style='font-weight:bold;'>{$rs['judul']}</a></font></td>
                                <td><font size=-1>{$rs['tgl_mulai']}</font></td>
                                <td><font size=-1>{$rs['tgl_selesai']}</font></td>
                                <td align=center>{$statusBadge}</td>
                                <td>{$progressBar}</td>
                                <td><font size=-1>".htmlspecialchars($rs['creator_nama'] ?? $rs['iduser_create'])."</font></td>
                                <td align=center>
                                    <button class='btn btn-xs btn-info' style='margin: 0 2px;' onclick=\"showDetail({$rs['idsprint']}, '".addslashes($rs['judul'])."')\" title='Lihat Detail'><i class='fa fa-search'></i> Detail</button>
                                    <a href='index.php?par=04&idsprint={$rs['idsprint']}' class='btn btn-xs btn-primary' style='margin: 0 2px;' title='Lihat Log yang ditag ke sprint ini'><i class='fa fa-external-link'></i> Log</a>
                                    {$toggleBtn}
                                    <a href='index.php?par=53&del_sprint=1&idsprint={$rs['idsprint']}' class='btn btn-xs btn-danger' style='margin: 0 2px;' onclick=\"return confirm('Hapus sprint ini?')\" title='Hapus'><i class='fa fa-trash'></i></a>
                                </td>
                            </tr>";
                            $no++;
                        }
                    } catch (PDOException $e) {
                        echo "<tr><td colspan='8' style='color:red;'>Error: ".$e->getMessage()."</td></tr>";
                    }
                    ?>
                    </tbody>
                </table>
            </div>
            
            <!-- DETAIL SPRINT -->
            <div id="sectionDetail" style="display:none; margin-top:30px; border-top:2px solid #5bc0de; padding-top:20px;">
                <div style="margin-bottom:15px; padding-left:15px;">
                    <strong style="font-size:16px;"><i class="fa fa-list"></i> LOG HARIAN SPRINT — <span id="detailNama"></span></strong>
                    <span id="detailStats" style="margin-left:15px;"></span>
                </div>

                <div class="box-body">
                    <table class="table table-bordered table-striped table-hover">
                        <thead>
                            <tr>
                                <th style="width:40px;">#</th>
                                <th>Tgl Order</th>
                                <th>Customer</th>
                                <th>Dikerjakan Oleh</th>
                                <th>Uraian Order</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="detailContent"></tbody>
                    </table>
                </div>

                <!-- Lampiran Sprint -->
                <div id="detailFiles" style="margin:10px 15px 0;"></div>

                <div style="background:#fffbe6; padding:10px 15px; border-radius:5px; margin:10px 15px 0; border-left:4px solid #f39c12; font-size:12px; color:#856404;">
                    <i class="fa fa-info-circle"></i> Log harian yang muncul di sini adalah log dari <strong>Create Log</strong> yang dipilih sprint ini.
                    Untuk menambah, buka <a href="index.php?par=01">Create Log</a> dan pilih sprint yang sesuai.
                </div>

                <div align="right" style="margin-top:10px; padding-right:15px;">
                    <button type="button" class="btn btn-default btn-sm" onclick="$('#sectionDetail').fadeOut();"><i class="fa fa-times"></i> Tutup Detail</button>
                </div>
            </div>

        </section>
    </section>
</div>

<!-- Modal Update Plan Item -->
<div class="modal fade" id="editPlanModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Update Plan Item</h4>
            </div>
            <form method="POST" action="index.php?par=53">
                <div class="modal-body">
                    <input type="hidden" name="upd_plan" value="Y">
                    <input type="hidden" name="idplan" id="edit_idplan">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status_plan" id="edit_status" class="form-control">
                            <option value="belum">Belum</option>
                            <option value="proses">Proses</option>
                            <option value="selesai">Selesai</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Progress (%)</label>
                        <input type="number" name="progress_plan" id="edit_progress" class="form-control" min="0" max="100" value="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script type="text/javascript">
$(document).ready(function(){
    $('#dp_mulai, #dp_selesai').datepicker({ format: 'yyyy-mm-dd', autoclose: true });
});

// ===== Upload Dinamis Sprint =====
function tambahBarisSprint() {
    var div = document.createElement('div');
    div.style.cssText = 'display:flex;align-items:center;gap:8px;margin-bottom:8px;';
    div.innerHTML =
        '<input type="file" name="file_upload[]" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.mp4,.avi,.mov" class="form-control" style="flex:1;">'+
        '<input type="text" name="file_desc[]" placeholder="Keterangan file..." class="form-control" style="flex:1;">'+
        '<button type="button" class="btn btn-danger btn-xs" onclick="this.parentNode.remove()"><i class="fa fa-times"></i></button>';
    document.getElementById('uploadRowsSprint').appendChild(div);
}

var currentSprintId = 0;

function showDetail(idsprint, judul) {
    currentSprintId = idsprint;
    $('#detailNama').text(judul);
    $('#detailContent').html('<tr><td colspan="6" align="center">Memuat data...</td></tr>');
    $('#detailStats').html('');
    $('#detailFiles').html('<em style="color:#aaa;">Memuat lampiran...</em>');
    $('#sectionDetail').fadeIn();
    $('html, body').animate({ scrollTop: $("#sectionDetail").offset().top - 20 }, 500);
    
    // Load log harian terkait sprint
    $.ajax({
        url: 'index.php?par=53&ajax_log_detail=1&idsprint=' + idsprint,
        type: 'GET',
        success: function(response) { $('#detailContent').html(response); },
        error: function() { $('#detailContent').html('<tr><td colspan="6" align="center" style="color:red;">Gagal memuat data.</td></tr>'); }
    });
    
    // Load stats dari log
    $.ajax({
        url: 'index.php?par=53&ajax_stats=1&idsprint=' + idsprint,
        type: 'GET',
        dataType: 'json',
        success: function(data) {
            if (data && data.total > 0) {
                $('#detailStats').html(
                    '<span class="label label-default">Total Log: '+data.total+'</span> '+
                    '<span class="label label-success">Selesai: '+data.selesai+'</span> '+
                    '<span class="label label-warning">Open: '+data.open_count+'</span>'
                );
            } else {
                $('#detailStats').html('<span class="label label-default">Belum ada log</span>');
            }
        }
    });

    // Load lampiran sprint
    $.ajax({
        url: 'index.php?par=53&ajax_sprint_files=1&idsprint=' + idsprint,
        type: 'GET',
        success: function(response) { $('#detailFiles').html(response); },
        error: function() { $('#detailFiles').html(''); }
    });
}


</script>

</body>
</html>
