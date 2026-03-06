<?php
// 
include "dbase.php";
include "islogin.php";
$pesan = $_SESSION['PESAN'] ?? '';
unset($_SESSION['PESAN']);
$iduser   = $_SESSION['DEFAULT_IDUSER'];
$kodjab   = isset($_SESSION['DEFAULT_KODJAB']) ? $_SESSION['DEFAULT_KODJAB'] : 0;

$tgl   = isset($_GET['tgl']) ? trim($_GET['tgl']) : date('Y-m-d');
$sdtgl = isset($_GET['sdtgl']) ? trim($_GET['sdtgl']) : date('Y-m-d');

$filter_client     = isset($_GET['filter_client']) ? trim($_GET['filter_client']) : '';
$filter_aktivitas  = isset($_GET['filter_aktivitas']) ? trim($_GET['filter_aktivitas']) : '';
$filter_tgl_mulai  = isset($_GET['filter_tgl_mulai']) ? trim($_GET['filter_tgl_mulai']) : '';
$filter_tgl_selesai = isset($_GET['filter_tgl_selesai']) ? trim($_GET['filter_tgl_selesai']) : '';
$filter_dikerjakan = isset($_GET['filter_dikerjakan']) ? trim($_GET['filter_dikerjakan']) : '';

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Laporan Implementasi</title>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>
    
    <style>
    .filter-container {
        display: flex;
        gap: 10px;
        align-items: center;
        margin-bottom: 15px;
        flex-wrap: wrap;
    }
    .filter-container input,
    .filter-container select {
        padding: 5px 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 13px;
    }
    .filter-container label {
        margin: 0;
        font-weight: normal;
    }
    </style>
</head>

<body style="background-color: #ecf0f5;">

    <div class="row">
        
        <section class="panel">
            
            <div class="panel-body"> 
                <ol class="breadcrumb" style="background-color: #fff; border-bottom: 1px solid #eee;">
                    <li><i class="fa fa-home"></i> LAPORAN IMPLEMENTASI</li>
                </ol>
                
                <?php if (!empty($pesan)): ?>
                    <h4 style="margin-top: 15px;"><?php echo $pesan; ?></h4>
                <?php endif; ?>

                <form role="form" method="GET" action="index.php" style="margin-bottom: 20px;">
                    
                    <div class="form-inline" style="margin-bottom: 15px; display: flex; gap: 10px;">
                        <div class="form-group">
                            <label>Tanggal : </label>
                            <input name="tgl" id="dp1" type="text" value="<?php echo htmlspecialchars($tgl); ?>" size="16" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Sampai Tanggal : </label>
                            <input name="sdtgl" id="dp2" type="text" value="<?php echo htmlspecialchars($sdtgl); ?>" size="16" class="form-control">
                        </div>
                    </div>
                    
                    <div class="filter-container">
                        <label><strong>Filter:</strong></label>
                        
                        <select id="filterClient" name="filter_client" class="form-control" style="width: 200px;">
                            <option value="">-- Semua Client --</option>
                            <?php
                                $qk_cust_filter = $conn->prepare("SELECT * FROM rcustomer ORDER BY nmcustomer"); 
                                $qk_cust_filter->execute(); 
                                while($rsk_cust_filter = $qk_cust_filter->fetch()){ 
                                    $selected = ($filter_client == $rsk_cust_filter['nmcustomer']) ? 'selected' : '';
                                    echo "<option value='".htmlspecialchars($rsk_cust_filter['nmcustomer'])."' $selected>".htmlspecialchars($rsk_cust_filter['nmcustomer'])."</option>\n";
                                }
                            ?>
                        </select>
                        
                        <input type="text" id="filterAktivitas" name="filter_aktivitas" placeholder="Aktivitas" class="form-control" style="width: 150px;"
                            value="<?php echo htmlspecialchars($filter_aktivitas); ?>" />
                        
                        <input type="date" id="filterTglMulai" name="filter_tgl_mulai" placeholder="Tgl Mulai" class="form-control" style="width: 160px;"
                            value="<?php echo htmlspecialchars($filter_tgl_mulai); ?>" />
                        
                        <input type="date" id="filterTglSelesai" name="filter_tgl_selesai" placeholder="Tgl Selesai" class="form-control" style="width: 160px;"
                            value="<?php echo htmlspecialchars($filter_tgl_selesai); ?>" />
                        
                        <select id="filterDikerjakan" name="filter_dikerjakan" class="form-control" style="width: 200px;">
                            <option value="">-- Semua User --</option>
                            <?php
                                $qk_user_filter = $conn->prepare("SELECT * FROM ruser WHERE stsaktif = 1 ORDER BY nik ASC, nama ASC"); 
                                $qk_user_filter->execute(); 
                                while($rsk_user_filter = $qk_user_filter->fetch()){ 
                                    $selected = ($filter_dikerjakan == $rsk_user_filter['nama']) ? 'selected' : '';
                                    echo "<option value='".htmlspecialchars($rsk_user_filter['nama'])."' $selected>".htmlspecialchars($rsk_user_filter['nama'])."</option>\n";
                                }
                            ?>
                        </select>
                        
                    </div>

                    <div>
                        <input type="hidden" name="par" id="par" value="34">
                        <button type="submit" name="submit" class="btn btn-primary">Submit Filter</button>
                        <a href="index.php?par=34" class="btn btn-danger">Reset Filter</a>
                    </div>
                </form>

                <div align="center" style="margin-bottom: 15px;">
                    <?php echo " Dari tanggal " . htmlspecialchars($tgl) . "&nbsp;&nbsp;&nbsp; Sampai tanggal " . htmlspecialchars($sdtgl); ?>
                </div>
                
                <div class="box-body">
                    <table id="contoh" class="table table-bordered table-striped table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nama Client</th>
                                <th>Aktivitas</th>
                                <th>Tgl Mulai</th>
                                <th>Tgl Selesai</th>
                                <th>Dikerjakan Oleh</th>
                                <th>Penanggung Jawab</th>
                                <th>Terlambat</th>
                                <th>Selesai</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            try {
                                // 1. Siapkan parameter
                                $params = [
                                    ':tgl' => $tgl, 
                                    ':sdtgl' => $sdtgl
                                ];
                                
                                // 2. Query dasar
                                $strsql = "SELECT 
                                        t.id, t.aktivitas, t.tglmulai, t.tglselesai, t.iduser, t.isselesai,
                                        r.nmcustomer,
                                        u_order.nama AS nama_dikerjakan_oleh,
                                        u_pj.nama AS nama_penanggung_jawab
                                        FROM timplementasi t 
                                        LEFT JOIN rcustomer r ON t.kodcustomer = r.kodcustomer 
                                        LEFT JOIN ruser u_order ON t.userorder = u_order.iduser
                                        LEFT JOIN ruser u_pj ON t.userpj = u_pj.iduser
                                        WHERE (t.tglmulai >= :tgl AND t.tglmulai <= :sdtgl) AND t.stsdel = 0 ";
                                
                                // 3. Tambahkan filter dinamis
                                if (!empty($filter_client)) {
                                    $strsql .= " AND r.nmcustomer = :filter_client ";
                                    $params[':filter_client'] = $filter_client;
                                }
                                if (!empty($filter_aktivitas)) {
                                    $strsql .= " AND t.aktivitas LIKE :filter_aktivitas ";
                                    $params[':filter_aktivitas'] = '%' . $filter_aktivitas . '%';
                                }
                                if (!empty($filter_tgl_mulai)) {
                                    $strsql .= " AND t.tglmulai = :filter_tgl_mulai ";
                                    $params[':filter_tgl_mulai'] = $filter_tgl_mulai;
                                }
                                if (!empty($filter_tgl_selesai)) {
                                    $strsql .= " AND t.tglselesai = :filter_tgl_selesai ";
                                    $params[':filter_tgl_selesai'] = $filter_tgl_selesai;
                                }
                                if (!empty($filter_dikerjakan)) {
                                    $strsql .= " AND u_order.nama = :filter_dikerjakan ";
                                    $params[':filter_dikerjakan'] = $filter_dikerjakan;
                                }
                                
                                $strsql .= " ORDER BY r.nmcustomer, t.tglmulai";
                                
                                // 4. Eksekusi query
                                $sql = $conn->prepare($strsql);
                                $sql->execute($params);
                                
                                $no = 1;
                                $current_client = "";
                                
                                while ($rs = $sql->fetch(PDO::FETCH_ASSOC)) {
                                    $terlambat_status = "Tidak";
                                    $tgl_selesai = $rs['tglselesai'];
                                    $tgl_hari_ini = date('Y-m-d');

                                    if ($tgl_selesai < $tgl_hari_ini) {
                                        $terlambat_status = "<span style='color: red; font-weight: bold;'>Ya</span>";
                                    }

                                    echo "<tr>";
                                    echo "<td align=center>" . $no . "</td>";
                                    $client_name = $rs['nmcustomer'];
                                    if ($client_name != $current_client) {
                                        echo "<td><strong>" . htmlspecialchars($client_name) . "</strong></td>";
                                        $current_client = $client_name; 
                                    } else {
                                        echo "<td></td>"; 
                                    }
                                    echo "<td>" . htmlspecialchars($rs['aktivitas']) . "</td>";
                                    echo "<td>" . htmlspecialchars(date('d/m/Y', strtotime($rs['tglmulai']))) . "</td>";
                                    echo "<td>" . ($rs['tglselesai'] ? htmlspecialchars(date('d/m/Y', strtotime($rs['tglselesai']))) : '-') . "</td>";
                                    echo "<td>" . htmlspecialchars($rs['nama_dikerjakan_oleh']) . "</td>";
                                    echo "<td>" . htmlspecialchars($rs['nama_penanggung_jawab']) . "</td>";
                                    echo "<td>" . $terlambat_status . "</td>";

                                    $status_selesai = ($rs['isselesai'] == 1) ? "Ya" : "Belum";
                                    echo "<td>" . $status_selesai . "</td>";

                                    echo "</tr>";
                                    $no++;
                                }
                            } catch (PDOException $e) {
                                echo "<tr><td colspan='9' class='text-center'>Terjadi error: " . $e->getMessage() . "</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                
            </div>
        </section>
    </div>

<script type="text/javascript">
(function($) {
    // Tambahkan delay untuk memastikan DataTable eksternal sudah selesai
    setTimeout(function() {
        // Hapus semua elemen DataTable yang mungkin sudah dibuat
        $('#contoh_wrapper').find('.dataTables_length, .dataTables_filter, .dataTables_info, .dataTables_paginate').remove();
        
        // Destroy DataTable jika sudah ada
        if ($.fn.DataTable.isDataTable('#contoh')) {
            $('#contoh').DataTable().clear().destroy();
        }
        
        // Hapus wrapper jika ada
        if ($('#contoh').parent().hasClass('dataTables_wrapper')) {
            $('#contoh').unwrap();
        }
        
        var table = $('#contoh').DataTable({
            "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]],
            "pageLength": 10,
            "dom": 'lrtrip',
            "destroy": true
        });
        
        // Inisialisasi untuk datepicker form utama
        if (typeof $.fn.datepicker === 'function') {
            $('#dp1').datepicker({ format: 'yyyy-mm-dd', autoclose: true });
            $('#dp2').datepicker({ format: 'yyyy-mm-dd', autoclose: true });
        }
    }, 100);
})(jQuery);
</script>

</body>
</html>