<?php
// 
session_start();
include "dbase.php";
include "islogin.php";

$pesan = "";
$tglini = date('Y-m-d');

// VALIDASI USER LOGIN
if (!isset($_SESSION['DEFAULT_IDUSER']) || empty($_SESSION['DEFAULT_IDUSER'])) {
    die('ERROR: SESSION USER TIDAK VALID');
}

$iduser = $_SESSION['DEFAULT_IDUSER'];

// FILTER TANGGAL
$tgl_dari = '';
$tgl_sampai = '';
$tgl_dari_display = '';
$tgl_sampai_display = '';

// Jika tidak ada filter, set default ke bulan ini
if (!isset($_GET['tgl_dari']) && !isset($_GET['tgl_sampai'])) {
    // Default: tanggal 1 bulan ini sampai hari ini
    $tgl_dari = date('Y-m-01'); // Tanggal 1 bulan ini
    $tgl_sampai = date('Y-m-d'); // Hari ini
    $tgl_dari_display = date('01/m/Y');
    $tgl_sampai_display = date('d/m/Y');
} else {
    // Jika ada filter dari GET
    if (isset($_GET['tgl_dari']) && !empty($_GET['tgl_dari'])) {
        $tgl_dari_display = $_GET['tgl_dari'];
        // Konversi dari dd/mm/yyyy ke Y-m-d untuk query database
        $parts = explode('/', $_GET['tgl_dari']);
        if (count($parts) == 3) {
            $tgl_dari = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
        }
    }

    if (isset($_GET['tgl_sampai']) && !empty($_GET['tgl_sampai'])) {
        $tgl_sampai_display = $_GET['tgl_sampai'];
        // Konversi dari dd/mm/yyyy ke Y-m-d untuk query database
        $parts = explode('/', $_GET['tgl_sampai']);
        if (count($parts) == 3) {
            $tgl_sampai = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
        }
    }
}
?>

<!-- Tambahkan referensi ke library Bootstrap dan jQuery -->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>

<!-- Tambahkan referensi ke library Bootstrap Datepicker -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>

<section class="content">
    <div class="breadcrumb">
        <span class="breadcrumb-title"><i class="fa fa-home"></i> LAPORAN LEMBUR</span>
    </div>
    
    <div class="box">
        <div class="box-body">
            <form method="GET" action="" id="form-filter">
                <!-- Jaga parameter 'par' agar tidak hilang saat submit -->
                <?php if(isset($_GET['par'])): ?>
                    <input type="hidden" name="par" value="<?php echo htmlspecialchars($_GET['par']); ?>">
                <?php endif; ?>
                
                <div class="row">
                    <!-- Filter Pegawai -->
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Pegawai</label>
                            <select name="filter_pegawai" class="form-control">
                                <option value="">-- Semua Pegawai --</option>
                                <?php
                                $filter_pegawai_val = isset($_GET['filter_pegawai']) ? trim($_GET['filter_pegawai']) : '';
                                $qp = $conn->prepare("SELECT iduser, nama FROM ruser WHERE stsaktif = 1 ORDER BY nik ASC, nama ASC");
                                $qp->execute();
                                while($rsp = $qp->fetch()) {
                                    $sel = ($filter_pegawai_val == $rsp['iduser']) ? 'selected' : '';
                                    echo "<option value='".htmlspecialchars($rsp['iduser'])."' $sel>".htmlspecialchars($rsp['nama'])."</option>\n";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Dari Tanggal</label>
                            <input type="text" class="form-control datepicker" name="tgl_dari" 
                                   id="tgl_dari" placeholder="dd/mm/yyyy" 
                                   value="<?php echo htmlspecialchars($tgl_dari_display); ?>" 
                                   autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Sampai Tanggal</label>
                            <input type="text" class="form-control datepicker" name="tgl_sampai" 
                                   id="tgl_sampai" placeholder="dd/mm/yyyy" 
                                   value="<?php echo htmlspecialchars($tgl_sampai_display); ?>" 
                                   autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="form-group">
                            <label>&nbsp;</label><br>
                            <button type="submit" class="btn btn-primary">
                                <i class="fa fa-search"></i> Filter
                            </button>
                            <button type="button" id="btn-reset" class="btn btn-danger">
                                <i class="fa fa-refresh"></i> Reset
                            </button>
                            <button type="button" onclick="exportLaporan()" class="btn btn-success">
                                <i class="fa fa-file-excel-o"></i> Export Excel
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div align="center" style="margin-bottom: 15px;">
        <strong>
            <?php 
            if (!empty($tgl_dari_display) && !empty($tgl_sampai_display)) {
                echo "Data Lembur dari Tanggal " . htmlspecialchars($tgl_dari_display) . " sampai " . htmlspecialchars($tgl_sampai_display);
            } elseif (!empty($tgl_dari_display)) {
                echo "Data Lembur dari Tanggal " . htmlspecialchars($tgl_dari_display);
            } elseif (!empty($tgl_sampai_display)) {
                echo "Data Lembur sampai Tanggal " . htmlspecialchars($tgl_sampai_display);
            } else {
                echo "Semua Data Lembur";
            }
            ?>
        </strong>
    </div>

    <!-- Di bawah ini akan berisi view dari data pengajuan lembur yang telah diinputkan sebelumnya -->
    <div class="box">
        <div class="box-body">
            <div class="table-responsive">
                <table id="daftar-pengajuan-lembur" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Tanggal</th>
                            <th>Jam Mulai</th>
                            <th>Tugas, Target & Pegawai</th>
                            <th>Status</th>
                            <th>Kesimpulan</th>
                            <th>Foto</th>
                            <th>Penanggung Jawab</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $no = 1;
                    $filter_pegawai = isset($_GET['filter_pegawai']) ? trim($_GET['filter_pegawai']) : '';
                    
                    // We need to filter based on tdtllembur.iduser_pegawai
                    // Since a lembur request can have multiple tasks for different employees, we join tdtllembur
                    
                    $sql = "
                        SELECT DISTINCT l.id, l.tgl_lembur, l.jam_mulai, l.tgl_pengajuan,
                               t.status_lembur,
                               t.kesimpulan,
                               t.foto,
                               ru.nama as nama_penanggung_jawab
                        FROM tlembur l
                        LEFT JOIN ttanggungjawab_lembur t ON t.idlembur = l.id AND t.idtask IS NULL
                        LEFT JOIN ruser ru ON t.iduser_pelapor = ru.iduser
                    ";
                    
                    if (!empty($filter_pegawai)) {
                        $sql .= " INNER JOIN tdtllembur dt ON dt.idlembur = l.id ";
                    }
                    
                    $sql .= " WHERE l.iduser_pengaju IS NOT NULL ";
                    
                    $params = [];
                    
                    if (!empty($filter_pegawai)) {
                        $sql .= " AND dt.iduser_pegawai = :filter_pegawai ";
                        $params[':filter_pegawai'] = $filter_pegawai;
                    }
                    
                    // Tambahkan filter tanggal jika ada
                    if (!empty($tgl_dari)) {
                        $sql .= " AND l.tgl_lembur >= :tgl_dari";
                        $params[':tgl_dari'] = $tgl_dari;
                    }
                    
                    if (!empty($tgl_sampai)) {
                        $sql .= " AND l.tgl_lembur <= :tgl_sampai";
                        $params[':tgl_sampai'] = $tgl_sampai;
                    }
                    
                    $sql .= " ORDER BY l.tgl_pengajuan DESC";
                    
                    $q = $conn->prepare($sql);
                    $q->execute($params);

                    while ($row = $q->fetch()) {
                        // Ambil detail tugas
                        $qd_sql = "
                            SELECT d.tugas, d.target, d.kodcustomer, ru.nama AS nama_pegawai, rc.nmcustomer
                            FROM tdtllembur d
                            LEFT JOIN ruser ru ON d.iduser_pegawai = ru.iduser
                            LEFT JOIN rcustomer rc ON d.kodcustomer = rc.kodcustomer
                            WHERE d.idlembur = :idlembur
                        ";
                        
                        $qd_params = [':idlembur' => $row['id']];
                        
                        if (!empty($filter_pegawai)) {
                            $qd_sql .= " AND d.iduser_pegawai = :filter_pegawai ";
                            $qd_params[':filter_pegawai'] = $filter_pegawai;
                        }
                        
                        $qd_sql .= " ORDER BY d.id ";
                        
                        $qd = $conn->prepare($qd_sql);
                        $qd->execute($qd_params);

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

                        // Status Lembur
                        $status_display = '<span class="label label-danger">Belum dilaporkan</span>';
                        if ($row['status_lembur'] !== null) {
                            $status_display = $row['status_lembur'] == '1'
                                ? '<span class="label label-success">Selesai</span>'
                                : '<span class="label label-warning">Tidak Selesai</span>';
                        }

                        // Kesimpulan - ambil dari detail rows (idtask IS NOT NULL)
                        $qk = $conn->prepare("SELECT kesimpulan FROM ttanggungjawab_lembur WHERE idlembur = :id AND idtask IS NOT NULL AND kesimpulan IS NOT NULL AND kesimpulan != '' ORDER BY id");
                        $qk->execute([':id' => $row['id']]);
                        $kesimpulan_list = [];
                        while ($kr = $qk->fetch()) {
                            $kesimpulan_list[] = htmlspecialchars($kr['kesimpulan']);
                        }
                        $kesimpulan_display = !empty($kesimpulan_list)
                            ? implode('<br>', $kesimpulan_list)
                            : '<em class="text-muted">-</em>';

                        // Foto - ambil dari detail rows (idtask IS NOT NULL), kumpulkan semua path jadi flat array
                        $qf = $conn->prepare("SELECT foto FROM ttanggungjawab_lembur WHERE idlembur = :id AND idtask IS NOT NULL AND foto IS NOT NULL ORDER BY id");
                        $qf->execute([':id' => $row['id']]);
                        $all_foto = [];
                        while ($fr = $qf->fetch()) {
                            $decoded = json_decode($fr['foto'], true);
                            if (is_array($decoded)) {
                                $all_foto = array_merge($all_foto, $decoded);
                            } elseif (!empty($fr['foto'])) {
                                $all_foto[] = $fr['foto'];
                            }
                        }
                        $foto_display = '<em class="text-muted">-</em>';
                        if (!empty($all_foto)) {
                            $encoded = htmlspecialchars(json_encode($all_foto), ENT_QUOTES);
                            $foto_display = "
                                <button class='btn btn-xs btn-info' onclick='lihatSemuaFoto(`{$encoded}`)'>
                                    Lihat (" . count($all_foto) . ")
                                </button>
                            ";
                        }

                        // Penanggung Jawab
                        $penanggung_jawab_display = '<em class="text-muted">-</em>';
                        if (!empty($row['nama_penanggung_jawab'])) {
                            $penanggung_jawab_display = htmlspecialchars($row['nama_penanggung_jawab']);
                        }

                        echo "
                        <tr>
                            <td>{$no}</td>
                            <td>".date('d/m/Y', strtotime($row['tgl_lembur']))."</td>
                            <td>{$row['jam_mulai']}</td>
                            <td>{$aktivitas_list}</td>
                            <td>{$status_display}</td>
                            <td>{$kesimpulan_display}</td>
                            <td>{$foto_display}</td>
                            <td>{$penanggung_jawab_display}</td>
                        </tr>";
                        $no++;
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<div class="modal fade" id="modalFoto" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Foto Lembur</h4>
            </div>
            <div class="modal-body" id="modalFotoBody" style="text-align:center;">
                <!-- foto akan diisi via JS -->
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    
    // INISIALISASI DATEPICKER
    $('.datepicker').datepicker({
        format: 'dd/mm/yyyy',
        autoclose: true,
        todayHighlight: true,
        orientation: 'bottom'
    });

    // TOMBOL RESET
    $('#btn-reset').on('click', function() {
        // Kosongkan input field
        $('#tgl_dari').val('');
        $('#tgl_sampai').val('');
        
        // Clear datepicker
        $('.datepicker').datepicker('clearDates');
    });

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

function exportLaporan() {
    var table = document.getElementById('daftar-pengajuan-lembur');
    var rows = table.querySelectorAll('tr');
    var csv = [];
    
    for (var i = 0; i < rows.length; i++) {
        var row = [];
        var cols = rows[i].querySelectorAll('td, th');
        
        for (var j = 0; j < cols.length; j++) {
            // Skip kolom foto
            if (j == 7) continue;
            var text = cols[j].innerText.replace(/"/g, '""').replace(/\n/g, ' ');
            row.push('"' + text + '"');
        }
        csv.push(row.join(';'));
    }
    
    var csvContent = '\uFEFF' + csv.join('\n');
    var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    var link = document.createElement('a');
    var tglDari = document.getElementById('tgl_dari').value || 'all';
    var tglSampai = document.getElementById('tgl_sampai').value || 'all';
    link.href = URL.createObjectURL(blob);
    link.download = 'Laporan_Lembur_' + tglDari.replace(/\//g,'-') + '_sd_' + tglSampai.replace(/\//g,'-') + '.csv';
    link.click();
}

function lihatSemuaFoto(jsonFoto) {
    let fotoArray = [];

    try {
        fotoArray = JSON.parse(jsonFoto);
    } catch (e) {
        alert('Gagal memuat foto');
        return;
    }

    let html = '';
    fotoArray.forEach(f => {
        html += `
            <img src="${f}"
                 style="max-width:100%; margin-bottom:10px; border:1px solid #ddd; padding:5px;">
        `;
    });

    document.getElementById('modalFotoBody').innerHTML = html;
    $('#modalFoto').modal('show');
}
</script>