<?php
include "dbase.php";
include "islogin.php";

$pesan = "";
$iduser = $_SESSION['DEFAULT_IDUSER'];
$kodjab = isset($_SESSION['DEFAULT_KODJAB']) ? $_SESSION['DEFAULT_KODJAB'] : 0;

// ========== AUTO-MIGRATION: Ensure THR table or column exists ==========
// Since we are using the existing 'thr' column in 'tgaji' but dedicated to this menu, 
// we might want to distinguish TRH records from regular salary records.
// Let's use STATUS_GAJI = 'THR' to identify THR-only records in tgaji.

// AJAX Handler: Hitung THR otomatis (Logic similar to what was in 38_penggajian)
if (isset($_GET['ajax']) && $_GET['ajax'] == 'hitung_thr') {
    $idpegawai = $_GET['idpegawai'] ?? '';
    $tahun = $_GET['tahun'] ?? date('Y');
    
    try {
        $q = $conn->prepare("SELECT nama, tgl_masuk, (SELECT gaji_pokok + tunj_jabatan FROM tgaji WHERE iduser_pegawai = ruser.iduser ORDER BY periode DESC LIMIT 1) as upah_terakhir FROM ruser WHERE iduser = :id");
        $q->execute([':id' => $idpegawai]);
        $user = $q->fetch(PDO::FETCH_ASSOC);
        
        if ($user && !empty($user['tgl_masuk'])) {
            $tgl_masuk = new DateTime($user['tgl_masuk']);
            $tgl_thr = new DateTime($tahun . '-12-31'); // Usually end of year or Hari Raya date
            
            $diff = $tgl_masuk->diff($tgl_thr);
            $total_bulan = ($diff->y * 12) + $diff->m;
            
            $upah = floatval($user['upah_terakhir'] ?? 5000000); // Fallback if no payroll record
            
            if ($total_bulan < 1) {
                $thr = 0;
            } else if ($total_bulan >= 12) {
                $thr = $upah;
            } else {
                $thr = ($total_bulan / 12) * $upah;
            }
            
            echo json_encode([
                'masa_kerja_bulan' => $total_bulan,
                'upah' => $upah,
                'thr' => round($thr)
            ]);
        } else {
            echo json_encode(['error' => 'Data pegawai atau tgl masuk tidak ditemukan']);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// POST Handler: Simpan THR
if (isset($_POST['submit_thr'])) {
    $idpegawai = $_POST['idpegawai'];
    $tahun = $_POST['tahun'];
    $nominal = str_replace('.', '', $_POST['nominal']);
    $periode = $tahun . '-12'; // Dummy month for THR
    
    // Check if THR already exists for this year
    $check = $conn->prepare("SELECT id FROM tgaji WHERE iduser_pegawai = ? AND periode = ? AND status_gaji = 'THR'");
    $check->execute([$idpegawai, $periode]);
    
    if ($check->rowCount() > 0) {
        $q = $conn->prepare("UPDATE tgaji SET thr = ?, tgl_input = NOW() WHERE iduser_pegawai = ? AND periode = ? AND status_gaji = 'THR'");
        $q->execute([$nominal, $idpegawai, $periode]);
        $pesan = "THR berhasil diperbarui!";
    } else {
        $q = $conn->prepare("INSERT INTO tgaji (iduser_pegawai, periode, thr, status_gaji, tgl_input) VALUES (?, ?, ?, 'THR', NOW())");
        $q->execute([$idpegawai, $periode, $nominal]);
        $pesan = "THR berhasil disimpan!";
    }
}

$filter_tahun = $_GET['filter_tahun'] ?? date('Y');
?>

    <ol class="breadcrumb" style="background: linear-gradient(135deg, #2d5a27 0%, #1e3c1a 100%); color: white; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
        <li style="color: #d4af37; font-weight: bold;"><i class="fa fa-gift"></i> MANAJEMEN THR (Tunjangan Hari Raya)</li>
    </ol>

    <!-- Custom Festive Styles -->
    <style>
        :root {
            --thr-green: #2d5a27;
            --thr-gold: #d4af37;
            --thr-light-green: #e9f5e8;
            --thr-dark: #1e3c1a;
        }
        .thr-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.05);
            border-left: 5px solid var(--thr-green);
            transition: transform 0.3s ease;
        }
        .thr-card:hover {
            transform: translateY(-5px);
        }
        .thr-card i {
            font-size: 40px;
            color: var(--thr-gold);
            opacity: 0.8;
        }
        .thr-card.gold-border {
            border-left-color: var(--thr-gold);
        }
        .thr-stat-val {
            font-size: 24px;
            font-weight: 800;
            color: var(--thr-dark);
            margin: 5px 0;
        }
        .thr-stat-label {
            font-size: 13px;
            color: #777;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .panel-heading {
            background: #fff !important;
            border-bottom: 2px solid var(--thr-light-green) !important;
            padding: 15px 20px !important;
        }
        .btn-primary {
            background-color: var(--thr-green) !important;
            border-color: var(--thr-green) !important;
        }
        .btn-success {
            background-color: var(--thr-gold) !important;
            border-color: var(--thr-gold) !important;
            color: #fff !important;
        }
        .table thead th {
            background-color: var(--thr-green);
            color: white;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
        }
        .modal-header {
            background: var(--thr-green);
            color: white;
            border-radius: 5px 5px 0 0;
        }
        .modal-title { font-weight: 700; color: #fff !important; }
        .input-group-btn .btn-info {
            background-color: var(--thr-dark) !important;
            border-color: var(--thr-dark) !important;
        }
    </style>

    <?php
    // Calculate Stats
    $q_stat = $conn->prepare("SELECT COUNT(*) as total_peg, SUM(thr) as total_thr FROM tgaji WHERE status_gaji = 'THR' AND SUBSTRING(periode,1,4) = :tahun");
    $q_stat->execute([':tahun' => $filter_tahun]);
    $stat = $q_stat->fetch(PDO::FETCH_ASSOC);
    ?>

    <!-- Summary Widgets -->
    <div class="col-md-4">
        <div class="thr-card">
            <div class="row">
                <div class="col-xs-3"><i class="fa fa-users"></i></div>
                <div class="col-xs-9 text-right">
                    <div class="thr-stat-label">Penerima THR <?= $filter_tahun ?></div>
                    <div class="thr-stat-val"><?= $stat['total_peg'] ?> Orang</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="thr-card gold-border">
            <div class="row">
                <div class="col-xs-3"><i class="fa fa-money"></i></div>
                <div class="col-xs-9 text-right">
                    <div class="thr-stat-label">Total Cair (Rp)</div>
                    <div class="thr-stat-val"><?= number_format($stat['total_thr'] ?? 0, 0, ',', '.') ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="thr-card">
            <div class="row">
                <div class="col-xs-3"><i class="fa fa-calendar-check-o"></i></div>
                <div class="col-xs-9 text-right">
                    <div class="thr-stat-label">Status Periode</div>
                    <div class="thr-stat-val"><?= $filter_tahun ?> Aktif</div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-12">
        <section class="panel">
            <header class="panel-heading">
                <form class="form-inline" method="GET">
                    <input type="hidden" name="par" value="56">
                    <div class="form-group">
                        <label>Tahun:</label>
                        <select name="filter_tahun" class="form-control">
                            <?php 
                            $start_y = date('Y') + 2; 
                            for($i=$start_y; $i>=2015; $i--) { 
                                $sel = ($filter_tahun == $i) ? 'selected' : '';
                                echo "<option value='$i' $sel>$i</option>";
                            } ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Tampilkan</button>
                    <button type="button" class="btn btn-success" data-toggle="modal" data-target="#modalAddThr">Input THR Baru</button>
                </form>
            </header>
            
            <div class="panel-body">
                <?php if($pesan) echo "<div class='alert alert-success'>$pesan</div>"; ?>
                
                <table class="table table-bordered table-striped" id="tableThr">
                    <thead>
                        <tr>
                            <th>NO</th>
                            <th>NAMA PEGAWAI</th>
                            <th>TAHUN</th>
                            <th>NOMINAL THR</th>
                            <th>TGL INPUT</th>
                            <th>AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $q = $conn->prepare("
                            SELECT g.*, ru.nama 
                            FROM tgaji g 
                            JOIN ruser ru ON g.iduser_pegawai = ru.iduser 
                            WHERE g.status_gaji = 'THR' AND SUBSTRING(g.periode,1,4) = :tahun
                            ORDER BY ru.nama ASC
                        ");
                        $q->execute([':tahun' => $filter_tahun]);
                        $no = 1;

                        while($r = $q->fetch(PDO::FETCH_ASSOC)) {
                            $is_high = ($r['thr'] >= 5000000); // Highlight high THR
                            $badge_class = $is_high ? 'label-success' : 'label-info';
                            $nominal_style = $is_high ? 'font-weight:bold; color:var(--thr-green); font-size: 15px;' : 'font-weight:bold;';
                            
                            echo "<tr style='transition: all 0.2s;'>
                                <td align='center'>$no</td>
                                <td>
                                    <div style='font-weight:bold; color:var(--thr-dark);'><i class='fa fa-user-circle' style='color:#ccc; margin-right:5px;'></i> " . htmlspecialchars($r['nama']) . "</div>
                                    <small style='color:#999; font-size:10px;'>ID: " . $r['iduser_pegawai'] . "</small>
                                </td>
                                <td align='center'><span class='label $badge_class'>$filter_tahun</span></td>
                                <td align='right' style='$nominal_style'>
                                    " . ($is_high ? "<i class='fa fa-star' style='color:var(--thr-gold); font-size:10px;'></i> " : "") . "
                                    Rp " . number_format($r['thr'], 0, ',', '.') . "
                                </td>
                                <td>
                                    <div style='font-size:11px; color:#666;'><i class='fa fa-clock-o'></i> " . date('d M Y, H:i', strtotime($r['tgl_input'])) . "</div>
                                </td>
                                <td align='center'>
                                    <button class='btn btn-warning btn-sm' style='border-radius:20px; padding: 2px 12px;' onclick='editThr(" . json_encode($r) . ")'><i class='fa fa-edit'></i> Edit</button>
                                </td>
                            </tr>";
                            $no++;
                        }
                        ?>

                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>

<!-- Modal Add/Edit THR -->
<div class="modal fade" id="modalAddThr" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <form method="POST" action="index.php?par=56">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title">Input / Edit THR</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Pegawai</label>
                        <select name="idpegawai" id="thr_idpegawai" class="form-control select2me" required>
                            <option value="">-- Pilih Pegawai --</option>
                            <?php
                            $qp = $conn->query("SELECT iduser, nama FROM ruser WHERE stsaktif=1 ORDER BY nama ASC");
                            while($rp = $qp->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value='".$rp['iduser']."'>".$rp['nama']."</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Tahun</label>
                        <select name="tahun" id="thr_tahun" class="form-control" required>
                            <?php 
                            for($i=date('Y')+2; $i>=2015; $i--) { 
                                $sel = ($filter_tahun == $i) ? 'selected' : '';
                                echo "<option value='$i' $sel>$i</option>";
                            } ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Nominal THR</label>
                        <div class="input-group">
                            <input type="text" name="nominal" id="thr_nominal" class="form-control input-ribuan" required>
                            <span class="input-group-btn">
                                <button class="btn btn-info" type="button" onclick="autoHitung()">Auto Hitung</button>
                            </span>
                        </div>
                        <small id="hint_thr" class="text-muted"></small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                    <button type="submit" name="submit_thr" class="btn btn-primary">Simpan</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function autoHitung() {
    var idp = $('#thr_idpegawai').val();
    var thn = $('#thr_tahun').val();
    if(!idp) { alert('Pilih pegawai dulu!'); return; }
    
    $.ajax({
        url: 'index.php?par=56&ajax=hitung_thr',
        data: { idpegawai: idp, tahun: thn },
        dataType: 'json',
        success: function(res) {
            if(res.thr !== undefined) {
                $('#thr_nominal').val(formatRibuan(res.thr));
                $('#hint_thr').text('Masa kerja: ' + res.masa_kerja_bulan + ' bulan. Upah dasar: Rp ' + formatRibuan(res.upah));
            } else {
                alert(res.error || 'Gagal hitung');
            }
        }
    });
}

function editThr(data) {
    $('#thr_idpegawai').val(data.iduser_pegawai).trigger('change');
    $('#thr_nominal').val(formatRibuan(data.thr));
    $('#modalAddThr').modal('show');
}

function formatRibuan(n) {
    return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

$(document).ready(function() {
    $('.input-ribuan').on('keyup', function() {
        var val = $(this).val().replace(/\D/g, "");
        $(this).val(formatRibuan(val));
    });
    $('#tableThr').DataTable();
});
</script>
