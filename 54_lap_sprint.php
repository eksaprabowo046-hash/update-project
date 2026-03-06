<?php
include "dbase.php";
$pesan = "";

$filter_status = "";
$tgl_dari = date('Y-m-01');
$tgl_sampai = date('Y-m-d');

if (isset($_POST['submit'])) {
    $filter_status = trim($_POST['filter_status'] ?? '');
    $tgl_dari = trim($_POST['tgl_dari'] ?? date('Y-m-01'));
    $tgl_sampai = trim($_POST['tgl_sampai'] ?? date('Y-m-d'));
}
?>

<body>
<div class="row">
    <ol class="breadcrumb">
        <li><i class="fa fa-bar-chart"></i> LAPORAN SPRINT</li>
    </ol>
    
    <section class="panel">
        <header class="panel-heading">
            <form role="form" method="POST" action="index.php?par=54">
                <div class="form-group col-xs-12 col-sm-2">
                    <label>Dari Tanggal</label>
                    <input name="tgl_dari" id="dp_sprint1" type="text" class="form-control date-picker" value="<?php echo $tgl_dari; ?>">
                </div>
                <div class="form-group col-xs-12 col-sm-2">
                    <label>Sampai Tanggal</label>
                    <input name="tgl_sampai" id="dp_sprint2" type="text" class="form-control date-picker" value="<?php echo $tgl_sampai; ?>">
                </div>
                <div class="form-group col-xs-12 col-sm-2">
                    <label>Status</label>
                    <select name="filter_status" class="form-control">
                        <option value="">-- Semua --</option>
                        <option value="aktif" <?php if($filter_status=='aktif') echo 'selected'; ?>>Aktif</option>
                        <option value="selesai" <?php if($filter_status=='selesai') echo 'selected'; ?>>Selesai</option>
                    </select>
                </div>
                <div class="form-group col-xs-12 col-sm-2" style="margin-top:24px;">
                    <button type="submit" name="submit" class="btn btn-primary" value="Y">Filter</button>
                    <button type="reset" class="btn btn-danger">Reset</button>
                </div>
            </form>
            <div class="clearfix"></div>
        </header>
        
        <section class="content">
            <?php
            $where = "WHERE s.tgl_mulai <= '$tgl_sampai' AND (s.tgl_selesai >= '$tgl_dari' OR s.tgl_selesai IS NULL)";
            if ($filter_status != '') {
                $where .= " AND s.status = '$filter_status'";
            }
            
            try {
                $qSum = $conn->prepare("SELECT 
                    COUNT(*) as total_sprint,
                    SUM(CASE WHEN s.status='aktif' THEN 1 ELSE 0 END) as sprint_aktif,
                    SUM(CASE WHEN s.status='selesai' THEN 1 ELSE 0 END) as sprint_selesai
                    FROM tsprint s $where");
                $qSum->execute();
                $sum = $qSum->fetch();
            ?>
            <style>
                .sprint-stat-card {
                    background: #fff;
                    border-radius: 8px;
                    padding: 16px 20px;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
                    display: flex;
                    align-items: center;
                    gap: 14px;
                    flex: 1;
                    min-width: 150px;
                    transition: box-shadow 0.2s, transform 0.2s;
                }
                .sprint-stat-card:hover { box-shadow: 0 6px 18px rgba(0,0,0,0.14); transform: translateY(-2px); }
                .sprint-stat-card .ss-icon {
                    width: 50px; height: 50px;
                    border-radius: 10px;
                    display: flex; align-items: center; justify-content: center;
                    font-size: 22px; color: #fff; flex-shrink: 0;
                }
                .sprint-stat-card .ss-info h3 { margin: 0; font-size: 26px; font-weight: 700; color: #333; line-height: 1.1; }
                .sprint-stat-card .ss-info p  { margin: 3px 0 0; font-size: 12px; color: #888; }
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
            setTimeout(fixProgressColors, 300);
            setTimeout(fixProgressColors, 800);
            setTimeout(fixProgressColors, 1500);
            $(document).ready(function(){
                $('#contoh').on('draw.dt', fixProgressColors);
                fixProgressColors();
            });
            </script>
            <div class="row" style="padding: 0 0 15px 0;">
                <div class="col-xs-12 col-md-4" style="margin-bottom: 15px;">
                    <div class="sprint-stat-card" style="height: 100%;">
                        <div class="ss-icon" style="background: linear-gradient(135deg, #54a0ff, #2e86de);"><i class="fa fa-list-alt"></i></div>
                        <div class="ss-info">
                            <h3><?php echo $sum['total_sprint']; ?></h3>
                            <p>Total Sprint</p>
                        </div>
                    </div>
                </div>
                <div class="col-xs-12 col-md-4" style="margin-bottom: 15px;">
                    <div class="sprint-stat-card" style="height: 100%;">
                        <div class="ss-icon" style="background: linear-gradient(135deg, #ffa502, #e67e22);"><i class="fa fa-spinner"></i></div>
                        <div class="ss-info">
                            <h3><?php echo $sum['sprint_aktif']; ?></h3>
                            <p>Sprint Aktif</p>
                        </div>
                    </div>
                </div>
                <div class="col-xs-12 col-md-4" style="margin-bottom: 15px;">
                    <div class="sprint-stat-card" style="height: 100%;">
                        <div class="ss-icon" style="background: linear-gradient(135deg, #26de81, #20bf6b);"><i class="fa fa-check-circle"></i></div>
                        <div class="ss-info">
                            <h3><?php echo $sum['sprint_selesai']; ?></h3>
                            <p>Sprint Selesai</p>
                        </div>
                    </div>
                </div>
            </div>
            <?php } catch (PDOException $e) {} ?>
            
            <div class="box-body">
                <table id="contoh" class="table table-bordered table-striped table-hover">
                    <thead>
                        <tr>
                            <th style="width:40px;">#</th>
                            <th>Judul Sprint</th>
                            <th>Periode</th>
                            <th>Status</th>
                            <th>Total Plan</th>
                            <th>Selesai</th>
                            <th>Proses</th>
                            <th>Belum</th>
                            <th style="width:200px;">Progress</th>
                            <th>Dibuat Oleh</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    try {
                        $strsql = "SELECT s.*, u.nama as creator_nama,
                            (SELECT COUNT(*) FROM tsprint_plan WHERE idsprint=s.idsprint) as total_plan,
                            (SELECT COUNT(*) FROM tsprint_plan WHERE idsprint=s.idsprint AND status='belum') as plan_belum,
                            (SELECT COUNT(*) FROM tlog WHERE idsprint=s.idsprint AND stsdel=0) as log_total,
                            (SELECT COUNT(*) FROM tlog WHERE idsprint=s.idsprint AND stsdel=0 AND isselesai=1) as log_selesai,
                            (SELECT COUNT(*) FROM tlog WHERE idsprint=s.idsprint AND stsdel=0 AND isselesai=0) as log_proses
                            FROM tsprint s 
                            LEFT JOIN ruser u ON s.iduser_create=u.iduser
                            $where
                            ORDER BY s.tgl_mulai DESC";
                        $sql = $conn->prepare($strsql);
                        $sql->execute();
                        $no = 1;
                        while ($rs = $sql->fetch()) {
                            $statusBadge = $rs['status'] == 'aktif' 
                                ? '<span class="label label-primary">Aktif</span>' 
                                : '<span class="label label-success">Selesai</span>';
                            
                            $logTotal   = intval($rs['log_total'] ?? 0);
                            $logSelesai = intval($rs['log_selesai'] ?? 0);
                            $logProses  = intval($rs['log_proses'] ?? 0);
                            $avgProg = $logTotal > 0 ? intval(($logSelesai / $logTotal) * 100) : 0;
                            if ($avgProg >= 100)    $barClass = 'progress-bar-success';
                            elseif ($avgProg >= 50) $barClass = 'progress-bar-warning';
                            elseif ($avgProg > 0)   $barClass = 'progress-bar-danger';
                            else                    $barClass = '';
                            
                            echo "<tr>
                                <td align=center><font size=-1>{$no}</font></td>
                                <td><font size=-1><strong>".htmlspecialchars($rs['judul'])."</strong></font></td>
                                <td><font size=-1>{$rs['tgl_mulai']} s/d {$rs['tgl_selesai']}</font></td>
                                <td align=center>{$statusBadge}</td>
                                <td align=center><font size=-1>{$rs['total_plan']}</font></td>
                                <td align=center><font size=-1><span style='color:green;font-weight:bold;'>{$logSelesai}</span></font></td>
                                <td align=center><font size=-1><span style='color:orange;font-weight:bold;'>{$logProses}</span></font></td>
                                <td align=center><font size=-1>{$rs['plan_belum']}</font></td>
                                <td>
                                    <div class='progress' style='margin:0;'>
                                        <div class='progress-bar {$barClass}' style='width:{$avgProg}%;min-width:20px;'>{$avgProg}%</div>
                                    </div>
                                    <small style='color:#888;'>{$logSelesai}/{$logTotal} log selesai</small>
                                </td>
                                <td><font size=-1>".htmlspecialchars($rs['creator_nama'] ?? $rs['iduser_create'])."</font></td>
                            </tr>";
                            $no++;
                        }
                    } catch (PDOException $e) {
                        echo "<tr><td colspan='10' style='color:red;'>Error: ".$e->getMessage()."</td></tr>";
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </section>
    </section>
</div>



