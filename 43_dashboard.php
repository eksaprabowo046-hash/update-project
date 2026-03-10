<?php
include "dbase.php";

$tanggal = date("Y-m-d");

// Jumlah Terlambat Masuk Kerja Hari Ini

$jmlTerlambat = 0;
try {
    $sql = $conn->prepare("SELECT COUNT(*) as jml FROM tkehadiran WHERE tanggal = '$tanggal' AND hadir > '08:30:00'");
    $sql->execute();
    $rs = $sql->fetch();
    $jmlTerlambat = $rs['jml'];
} catch (PDOException $e) {
    $jmlTerlambat = 0;
}

// Total hadir hari ini
$jmlHadir = 0;
try {
    $sql = $conn->prepare("SELECT COUNT(*) as jml FROM tkehadiran WHERE tanggal = '$tanggal'");
    $sql->execute();
    $rs = $sql->fetch();
    $jmlHadir = $rs['jml'];
} catch (PDOException $e) {
    $jmlHadir = 0;
}

// Pegawai per Departemen

$departemen = [];
try {
    $sql = $conn->prepare("SELECT d.nama_divisi, COUNT(r.iduser) as jumlah FROM tbl_divisi d LEFT JOIN ruser r ON r.divisi = d.nama_divisi AND r.stsaktif = 1 GROUP BY d.nama_divisi ORDER BY d.nama_divisi");
    $sql->execute();
    while ($rs = $sql->fetch()) {
        $departemen[] = ['nama' => $rs['nama_divisi'], 'jumlah' => intval($rs['jumlah'])];
    }
} catch (PDOException $e) {
    $departemen = [];
}
$totalPegawai = array_sum(array_column($departemen, 'jumlah'));

// Izin, Cuti, Sakit yang Masih Aktif Hari Ini (memperhitungkan durasi lamanya)

$jmlCuti = 0;
$jmlSakit = 0;
try {
    $sql = $conn->prepare("SELECT 
        SUM(CASE WHEN kategori = 'Cuti' THEN 1 ELSE 0 END) as cuti,
        SUM(CASE WHEN kategori = 'Sakit' THEN 1 ELSE 0 END) as sakit
        FROM tizin 
        WHERE stsdel = 0 
        AND tglizin <= '$tanggal'
        AND DATE_ADD(tglizin, INTERVAL 
            CASE 
                WHEN lamanya = '3 Hari' THEN 2
                WHEN lamanya = '2 Hari' THEN 1
                ELSE 0
            END DAY) >= '$tanggal'");
    $sql->execute();
    $rs = $sql->fetch();
    $jmlCuti = intval($rs['cuti']);
    $jmlSakit = intval($rs['sakit']);
} catch (PDOException $e) {
    $jmlCuti = 0;
    $jmlSakit = 0;
}
$jmlIzin = $jmlCuti + $jmlSakit;

// Detail Kehadiran Hari Ini (untuk popup)
$detailTerlambat = [];
$detailTepatWaktu = [];
$detailSemuaHadir = [];
try {
    $sql = $conn->prepare("SELECT k.iduser, r.nama, k.hadir, k.pulang FROM tkehadiran k INNER JOIN ruser r ON k.iduser = r.iduser WHERE k.tanggal = '$tanggal' ORDER BY k.hadir ASC");
    $sql->execute();
    while ($row = $sql->fetch(PDO::FETCH_ASSOC)) {
        $detailSemuaHadir[] = $row;
        if ($row['hadir'] > '08:30:00') {
            $detailTerlambat[] = $row;
        } else {
            $detailTepatWaktu[] = $row;
        }
    }
} catch (PDOException $e) {}

// Detail Izin Aktif Hari Ini (Cuti & Sakit, untuk popup)
$detailCuti = [];
$detailSakit = [];
try {
    $sql = $conn->prepare("SELECT a.idizin, r.nama, a.kategori, a.tglizin, a.lamanya, a.keperluan 
        FROM tizin a INNER JOIN ruser r ON a.iduser = r.iduser
        WHERE a.stsdel = 0 AND a.tglizin <= '$tanggal'
        AND DATE_ADD(a.tglizin, INTERVAL 
            CASE 
                WHEN a.lamanya = '3 Hari' THEN 2
                WHEN a.lamanya = '2 Hari' THEN 1
                ELSE 0
            END DAY) >= '$tanggal'
        ORDER BY a.tglizin DESC");
    $sql->execute();
    while ($row = $sql->fetch(PDO::FETCH_ASSOC)) {
        if ($row['kategori'] == 'Cuti') {
            $detailCuti[] = $row;
        } else {
            $detailSakit[] = $row;
        }
    }
} catch (PDOException $e) {}
$detailIzin = array_merge($detailCuti, $detailSakit);

// Rekap Sakit & Cuti per Karyawan dalam Setahun
$rekapKaryawan = [];
try {
    $sql = $conn->prepare("SELECT r.nama,
        SUM(CASE WHEN a.kategori = 'Sakit' THEN 1 ELSE 0 END) as total_sakit,
        SUM(CASE WHEN a.kategori = 'Cuti' THEN 1 ELSE 0 END) as total_cuti
        FROM ruser r
        LEFT JOIN tizin a ON a.iduser = r.iduser AND a.stsdel = 0 AND a.isapprove = 1 AND YEAR(a.tglizin) = YEAR(CURDATE())
        WHERE r.stsaktif = 1
        GROUP BY r.iduser, r.nama
        ORDER BY r.nama");
    $sql->execute();
    while ($row = $sql->fetch(PDO::FETCH_ASSOC)) {
        $rekapKaryawan[] = $row;
    }
} catch (PDOException $e) {
    $rekapKaryawan = [];
}

// Detail Pegawai per Departemen (untuk popup pie chart)
$detailDepartemen = [];
try {
    $sql = $conn->prepare("SELECT r.nama, r.divisi FROM ruser r WHERE r.stsaktif = 1 ORDER BY r.nik ASC, r.nama ASC");
    $sql->execute();
    while ($row = $sql->fetch(PDO::FETCH_ASSOC)) {
        $div = $row['divisi'] ?: 'Tidak Ada Divisi';
        if (!isset($detailDepartemen[$div])) $detailDepartemen[$div] = [];
        $detailDepartemen[$div][] = $row['nama'];
    }
} catch (PDOException $e) {}

// Log Terlambat, Masih Proses, Selesai (Semua Log Aktif)

$logTerlambat = 0;
$logProses = 0;
$logSelesai = 0;
$detailLogTerlambat = [];
$detailLogProses = [];
$detailLogSelesai = [];
try {
    $sql = $conn->prepare("SELECT 
        SUM(CASE 
            WHEN tglselesai != '0000-00-00' AND tgltarget < tglselesai THEN 1
            WHEN tglselesai = '0000-00-00' AND tgltarget < '$tanggal' THEN 1
            ELSE 0
        END) AS terlambat,
        SUM(CASE 
            WHEN tglselesai = '0000-00-00' AND tgltarget >= '$tanggal' THEN 1
            ELSE 0
        END) AS proses,
        SUM(CASE 
            WHEN tglselesai != '0000-00-00' AND tgltarget >= tglselesai THEN 1
            ELSE 0
        END) AS selesai
        FROM tlog WHERE stsdel = 0");
    $sql->execute();
    $rs = $sql->fetch();
    $logTerlambat = intval($rs['terlambat']);
    $logProses = intval($rs['proses']);
    $logSelesai = intval($rs['selesai']);
} catch (PDOException $e) {
    $logTerlambat = 0;
    $logProses = 0;
    $logSelesai = 0;
}
$logTotal = $logTerlambat + $logProses + $logSelesai;

// Detail Log per status (untuk popup pie chart)
try {
    $sql = $conn->prepare("SELECT a.idlog, a.iduser, a.userorder, b.nmcustomer, a.fasorder, a.tglorder, a.desorder, a.deslayan, a.tgltarget, a.tglselesai, r.nama as nama_pegawai FROM tlog a LEFT JOIN rcustomer b ON a.kodcustomer = b.kodcustomer LEFT JOIN ruser r ON a.iduser = r.iduser WHERE a.stsdel = 0 ORDER BY a.tglorder DESC");
    $sql->execute();
    while ($row = $sql->fetch(PDO::FETCH_ASSOC)) {
        $belumSelesai = (empty($row['tglselesai']) || $row['tglselesai'] == '0000-00-00');
        $isTerlambat = (!$belumSelesai && $row['tgltarget'] < $row['tglselesai']) || ($belumSelesai && $row['tgltarget'] < $tanggal);
        $isProses = ($belumSelesai && $row['tgltarget'] >= $tanggal);
        $isSelesai = (!$belumSelesai && $row['tgltarget'] >= $row['tglselesai']);
        if ($isTerlambat) $detailLogTerlambat[] = $row;
        elseif ($isProses) $detailLogProses[] = $row;
        elseif ($isSelesai) $detailLogSelesai[] = $row;
    }
} catch (PDOException $e) {}

// --- DATA KEUANGAN UNTUK DASHBOARD ---
try {
    // Total Saldo (Semua Transaksi + Saldo Awal Manual)
    $qSaldoAwal = $conn->query("SELECT saldo_awal FROM tkas_saldo_awal LIMIT 1");
    $awal_man = $qSaldoAwal->fetchColumn() ?: 0;
    
    $qTotal = $conn->query("SELECT 
        SUM(CASE WHEN plusmin = '+' THEN totalharga ELSE 0 END) - 
        SUM(CASE WHEN plusmin = '-' THEN totalharga ELSE 0 END) AS net
        FROM tkas");
    $saldo_total = ($qTotal->fetchColumn() ?: 0) + $awal_man;

    // Masuk & Keluar Bulan Ini
    $bln_ini = date('Y-m');
    $qBln = $conn->prepare("SELECT 
        SUM(CASE WHEN plusmin = '+' THEN totalharga ELSE 0 END) as masuk,
        SUM(CASE WHEN plusmin = '-' THEN totalharga ELSE 0 END) as keluar
        FROM tkas WHERE tgltransaksi LIKE ?");
    $qBln->execute([$bln_ini . '%']);
    $rBln = $qBln->fetch();
    $d_kas_masuk = $rBln['masuk'] ?: 0;
    $d_kas_keluar = $rBln['keluar'] ?: 0;
} catch (Exception $e) {
    $saldo_total = 0; $d_kas_masuk = 0; $d_kas_keluar = 0;
}
?>

<style>
    .dashboard-container { padding: 10px 0; }
    .dashboard-row { display: flex; flex-wrap: wrap; margin: 0 -10px; }
    .dashboard-col { padding: 0 10px; margin-bottom: 20px; }
    .dashboard-col-4 { width: 33.333%; }
    .dashboard-col-3 { width: 25%; }
    .dashboard-col-6 { width: 50%; }
    .dashboard-col-12 { width: 100%; }

    .stat-card {
        background: #fff;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        display: flex;
        align-items: center;
        gap: 15px;
        height: 100%;
        transition: box-shadow 0.2s;
    }
    .stat-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.13); }
    .stat-card.clickable { cursor: pointer; }
    .stat-card.clickable:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.15); }

    /* Modal Detail Styles */
    .modal-detail-dash .modal-header { border-radius: 4px 4px 0 0; color: #fff; }
    .modal-detail-dash .modal-header .close { color: #fff; opacity: 0.8; }
    .modal-detail-dash .modal-header .close:hover { opacity: 1; }
    .modal-detail-dash .table { margin-bottom: 0; }
    .modal-detail-dash .table th { background: #f8f9fa; font-size: 12px; text-transform: uppercase; color: #555; }
    .modal-detail-dash .table td { font-size: 13px; vertical-align: middle; }
    .modal-detail-dash .modal-body { padding: 0; max-height: 400px; overflow-y: auto; }
    .modal-detail-dash .empty-msg { text-align: center; padding: 30px; color: #999; font-style: italic; }
    .stat-card .stat-icon {
        width: 56px; height: 56px;
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 24px; color: #fff; flex-shrink: 0;
    }
    .stat-card .stat-info h3 { margin: 0; font-size: 28px; font-weight: 700; color: #333; }
    .stat-card .stat-info p { margin: 4px 0 0; font-size: 13px; color: #888; font-weight: 400; }

    .bg-danger-soft { background: linear-gradient(135deg, #ff6b6b, #ee5a24); }
    .bg-info-soft { background: linear-gradient(135deg, #54a0ff, #2e86de); }
    .bg-success-soft { background: linear-gradient(135deg, #5cd85c, #27ae60); }
    .bg-warning-soft { background: linear-gradient(135deg, #ffa502, #e67e22); }
    .bg-purple-soft { background: linear-gradient(135deg, #a29bfe, #6c5ce7); }

    .chart-panel {
        background: #fff;
        border-radius: 8px;
        padding: 20px 24px 24px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        height: 100%;
    }
    .chart-panel h4 {
        font-size: 16px; font-weight: 600; color: #333;
        margin: 0 0 16px; padding-bottom: 10px;
        border-bottom: 2px solid #f0f0f0;
    }
    .chart-panel .chart-wrap {
        display: flex; justify-content: center; align-items: center;
        min-height: 260px;
    }

    .section-title {
        font-size: 18px; font-weight: 600; color: #333;
        margin: 10px 0 16px; padding-left: 12px;
        border-left: 4px solid #2e86de;
    }

    @media (max-width: 768px) {
        .dashboard-col-4, .dashboard-col-3, .dashboard-col-6 { width: 100%; }
        .stat-card .stat-info h3 { font-size: 22px; }
    }
</style>

<body>

<div class="row">
    <ol class="breadcrumb">
        <li><i class="fa fa-dashboard"></i> DASHBOARD ANALYTICS</li>
    </ol>

    <div class="dashboard-container">

        <!-- SECTION: Keuangan Real-Time -->
        <div class="section-title"><i class="fa fa-money"></i> Ringkasan Keuangan Real-Time</div>
        <div class="dashboard-row">
            <div class="dashboard-col dashboard-col-4">
                <div class="stat-card" style="background: linear-gradient(135deg, #021B79 0%, #0575E6 100%); color: white; border: none;">
                    <div class="stat-icon" style="background: rgba(255,255,255,0.2);"><i class="fa fa-bank"></i></div>
                    <div class="stat-info">
                        <h3 style="color: white;">Rp <?php echo number_format($saldo_total, 0, ',', '.'); ?></h3>
                        <p style="color: rgba(255,255,255,0.8);">Total Saldo Kas</p>
                    </div>
                </div>
            </div>
            <div class="dashboard-col dashboard-col-4">
                <div class="stat-card" style="border-left: 5px solid #27ae60;">
                    <div class="stat-icon bg-success-soft"><i class="fa fa-arrow-up"></i></div>
                    <div class="stat-info">
                        <h3>Rp <?php echo number_format($d_kas_masuk, 0, ',', '.'); ?></h3>
                        <p>Kas Masuk (Bulan Ini)</p>
                    </div>
                </div>
            </div>
            <div class="dashboard-col dashboard-col-4">
                <div class="stat-card" style="border-left: 5px solid #e74c3c;">
                    <div class="stat-icon bg-danger-soft"><i class="fa fa-arrow-down"></i></div>
                    <div class="stat-info">
                        <h3>Rp <?php echo number_format($d_kas_keluar, 0, ',', '.'); ?></h3>
                        <p>Kas Keluar (Bulan Ini)</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION: Kehadiran Hari Ini -->
        <div class="section-title"><i class="fa fa-clock-o"></i> Kehadiran Hari Ini (<?php echo date('d-m-Y'); ?>)</div>
        <div class="dashboard-row">
            <div class="dashboard-col dashboard-col-4">
                <div class="stat-card clickable" data-toggle="modal" data-target="#modalTerlambat">
                    <div class="stat-icon bg-danger-soft"><i class="fa fa-warning"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $jmlTerlambat; ?></h3>
                        <p>Terlambat Masuk Kerja</p>
                    </div>
                </div>
            </div>
            <div class="dashboard-col dashboard-col-4">
                <div class="stat-card clickable" data-toggle="modal" data-target="#modalTepatWaktu">
                    <div class="stat-icon bg-success-soft"><i class="fa fa-check-circle"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $jmlHadir - $jmlTerlambat; ?></h3>
                        <p>Hadir Tepat Waktu</p>
                    </div>
                </div>
            </div>
            <div class="dashboard-col dashboard-col-4">
                <div class="stat-card clickable" data-toggle="modal" data-target="#modalTotalHadir">
                    <div class="stat-icon bg-info-soft"><i class="fa fa-users"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $jmlHadir; ?></h3>
                        <p>Total Hadir Hari Ini</p>
                    </div>
                </div>
            </div>
        </div>

        <!--  SECTION: Izin, Cuti, Sakit  -->
        <div class="section-title"><i class="fa fa-calendar"></i> Informasi Izin, Cuti & Sakit Hari Ini</div>
        <div class="dashboard-row">
            <div class="dashboard-col dashboard-col-4">
                <div class="stat-card clickable" data-toggle="modal" data-target="#modalIzin">
                    <div class="stat-icon bg-warning-soft"><i class="fa fa-file-text-o"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $jmlIzin; ?></h3>
                        <p>Izin</p>
                    </div>
                </div>
            </div>
            <div class="dashboard-col dashboard-col-4">
                <div class="stat-card clickable" data-toggle="modal" data-target="#modalCuti">
                    <div class="stat-icon bg-purple-soft"><i class="fa fa-plane"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $jmlCuti; ?></h3>
                        <p>Cuti</p>
                    </div>
                </div>
            </div>
            <div class="dashboard-col dashboard-col-4">
                <div class="stat-card clickable" data-toggle="modal" data-target="#modalSakit">
                    <div class="stat-icon bg-danger-soft"><i class="fa fa-medkit"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $jmlSakit; ?></h3>
                        <p>Sakit</p>
                    </div>
                </div>
            </div>
        </div>

        <!--  SECTION: Daftar Karyawan Rekap Sakit & Cuti -->
        <div class="section-title"><i class="fa fa-table"></i> Daftar Karyawan - Rekap Sakit & Cuti Tahun <?php echo date('Y'); ?></div>
        <div class="dashboard-row">
            <div class="dashboard-col" style="width:100%;">
                <div class="chart-panel">
                    <div class="row" style="margin-bottom: 15px;">
                        <div class="form-group col-xs-12 col-sm-3">
                            <label>Pegawai</label>
                            <select id="filterPegawaiRekap" class="form-control">
                                <option value="">-- Semua Pegawai --</option>
                                <?php foreach ($rekapKaryawan as $rk) { ?>
                                <option value="<?php echo $rk['nama']; ?>"><?php echo $rk['nama']; ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="form-group col-xs-12 col-sm-3">
                            <label>&nbsp;</label><br>
                            <button type="button" id="btnResetFilterRekap" class="btn btn-danger">Reset</button>
                        </div>
                    </div>
                    <table id="tblRekapKaryawan" class="table table-bordered table-striped table-hover">
                        <thead>
                            <tr>
                                <th style="width:40px;text-align:center;">No</th>
                                <th>Nama</th>
                                <th>Total Sakit</th>
                                <th>Total Cuti</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (count($rekapKaryawan) > 0) { $no=1; foreach ($rekapKaryawan as $rk) { ?>
                            <tr>
                                <td style="text-align:center;"><?php echo $no++; ?></td>
                                <td><?php echo $rk['nama']; ?></td>
                                <td><?php echo intval($rk['total_sakit']); ?></td>
                                <td><?php echo intval($rk['total_cuti']); ?></td>
                            </tr>
                        <?php } } else { ?>
                            <tr><td colspan="4" style="text-align:center;">Tidak ada data</td></tr>
                        <?php } ?>
                        </tbody>
                    </table>
                    <script>
                    $(document).ready(function() {
                        var tblRekap = $('#tblRekapKaryawan').DataTable({
                            pageLength: 10,
                            lengthMenu: [5, 10, 25, 50]
                        });

                        // Filter pegawai pada rekap
                        $('#filterPegawaiRekap').on('change', function() {
                            var val = $(this).val();
                            tblRekap.column(1).search(val ? '^' + $.fn.dataTable.util.escapeRegex(val) + '$' : '', true, false).draw();
                        });

                        // Reset filter
                        $('#btnResetFilterRekap').on('click', function() {
                            $('#filterPegawaiRekap').val('');
                            tblRekap.column(1).search('').draw();
                        });
                    });
                    </script>
                </div>
            </div>
        </div>

        <!-- SECTION: Diagram Pie -->
        <div class="section-title"><i class="fa fa-pie-chart"></i> Diagram Statistik</div>
        <div class="dashboard-row">
            <!-- Diagram Pie: Pegawai per Departemen -->
            <div class="dashboard-col dashboard-col-6">
                <div class="chart-panel">
                    <h4><i class="fa fa-building-o"></i> Jumlah Pegawai per Departemen</h4>
                    <div class="chart-wrap">
                        <canvas id="chartDepartemen"></canvas>
                    </div>
                </div>
            </div>
            <!-- Diagram Pie: Status Log Hari Ini -->
            <div class="dashboard-col dashboard-col-6">
                <div class="chart-panel">
                    <h4><i class="fa fa-tasks"></i> Status Semua Log Aktif</h4>
                    <div class="chart-wrap">
                        <canvas id="chartLogStatus"></canvas>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Modal: Terlambat Masuk Kerja -->
<div class="modal fade modal-detail-dash" id="modalTerlambat" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #ff6b6b, #ee5a24);">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-warning"></i> Terlambat Masuk Kerja - <?php echo date('d-m-Y'); ?></h4>
            </div>
            <div class="modal-body">
                <?php if (count($detailTerlambat) > 0) { ?>
                <table class="table table-striped table-hover">
                    <thead><tr><th>No</th><th>Nama</th><th>Jam Hadir</th><th>Jam Pulang</th></tr></thead>
                    <tbody>
                    <?php $no=1; foreach ($detailTerlambat as $d) { ?>
                        <tr><td><?php echo $no++; ?></td><td><?php echo $d['nama']; ?></td><td><span style="color:#e74c3c;font-weight:600;"><?php echo $d['hadir']; ?></span></td><td><?php echo $d['pulang'] ?: '-'; ?></td></tr>
                    <?php } ?>
                    </tbody>
                </table>
                <?php } else { ?>
                <div class="empty-msg"><i class="fa fa-check-circle" style="font-size:24px;color:#27ae60;"></i><br>Tidak ada yang terlambat hari ini</div>
                <?php } ?>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button></div>
        </div>
    </div>
</div>

<!-- Modal: Hadir Tepat Waktu -->
<div class="modal fade modal-detail-dash" id="modalTepatWaktu" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #5cd85c, #27ae60);">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-check-circle"></i> Hadir Tepat Waktu - <?php echo date('d-m-Y'); ?></h4>
            </div>
            <div class="modal-body">
                <?php if (count($detailTepatWaktu) > 0) { ?>
                <table class="table table-striped table-hover">
                    <thead><tr><th>No</th><th>Nama</th><th>Jam Hadir</th><th>Jam Pulang</th></tr></thead>
                    <tbody>
                    <?php $no=1; foreach ($detailTepatWaktu as $d) { ?>
                        <tr><td><?php echo $no++; ?></td><td><?php echo $d['nama']; ?></td><td><span style="color:#27ae60;font-weight:600;"><?php echo $d['hadir']; ?></span></td><td><?php echo $d['pulang'] ?: '-'; ?></td></tr>
                    <?php } ?>
                    </tbody>
                </table>
                <?php } else { ?>
                <div class="empty-msg"><i class="fa fa-info-circle" style="font-size:24px;color:#3498db;"></i><br>Belum ada data hadir tepat waktu</div>
                <?php } ?>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button></div>
        </div>
    </div>
</div>

<!-- Modal: Total Hadir -->
<div class="modal fade modal-detail-dash" id="modalTotalHadir" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #54a0ff, #2e86de);">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-users"></i> Total Hadir Hari Ini - <?php echo date('d-m-Y'); ?></h4>
            </div>
            <div class="modal-body">
                <?php if (count($detailSemuaHadir) > 0) { ?>
                <table class="table table-striped table-hover">
                    <thead><tr><th>No</th><th>Nama</th><th>Jam Hadir</th><th>Jam Pulang</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php $no=1; foreach ($detailSemuaHadir as $d) { 
                        $isTerlambat = ($d['hadir'] > '08:30:00');
                    ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><?php echo $d['nama']; ?></td>
                            <td><?php echo $d['hadir']; ?></td>
                            <td><?php echo $d['pulang'] ?: '-'; ?></td>
                            <td><?php echo $isTerlambat ? '<span class="label label-danger">Terlambat</span>' : '<span class="label label-success">Tepat Waktu</span>'; ?></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
                <?php } else { ?>
                <div class="empty-msg"><i class="fa fa-info-circle" style="font-size:24px;color:#3498db;"></i><br>Belum ada data kehadiran hari ini</div>
                <?php } ?>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button></div>
        </div>
    </div>
</div>

<!-- Modal: Izin (Semua) -->
<div class="modal fade modal-detail-dash" id="modalIzin" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #ffa502, #e67e22);">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-file-text-o"></i> Izin Aktif Hari Ini</h4>
            </div>
            <div class="modal-body">
                <?php if (count($detailIzin) > 0) { ?>
                <table class="table table-striped table-hover">
                    <thead><tr><th>No</th><th>Nama</th><th>Kategori</th><th>Tgl Izin</th><th>Lamanya</th><th>Keperluan</th></tr></thead>
                    <tbody>
                    <?php $no=1; foreach ($detailIzin as $d) { ?>
                        <tr>
                            <td><?php echo $no++; ?></td>
                            <td><?php echo $d['nama']; ?></td>
                            <td><?php echo $d['kategori'] == 'Cuti' ? '<span class="label label-info">'.$d['kategori'].'</span>' : '<span class="label label-danger">'.$d['kategori'].'</span>'; ?></td>
                            <td><?php echo $d['tglizin']; ?></td>
                            <td><?php echo $d['lamanya']; ?></td>
                            <td><?php echo $d['keperluan']; ?></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
                <?php } else { ?>
                <div class="empty-msg"><i class="fa fa-info-circle" style="font-size:24px;color:#3498db;"></i><br>Tidak ada izin aktif hari ini</div>
                <?php } ?>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button></div>
        </div>
    </div>
</div>

<!-- Modal: Cuti -->
<div class="modal fade modal-detail-dash" id="modalCuti" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #a29bfe, #6c5ce7);">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-plane"></i> Cuti Aktif Hari Ini</h4>
            </div>
            <div class="modal-body">
                <?php if (count($detailCuti) > 0) { ?>
                <table class="table table-striped table-hover">
                    <thead><tr><th>No</th><th>Nama</th><th>Tgl Izin</th><th>Lamanya</th><th>Keperluan</th></tr></thead>
                    <tbody>
                    <?php $no=1; foreach ($detailCuti as $d) { ?>
                        <tr><td><?php echo $no++; ?></td><td><?php echo $d['nama']; ?></td><td><?php echo $d['tglizin']; ?></td><td><?php echo $d['lamanya']; ?></td><td><?php echo $d['keperluan']; ?></td></tr>
                    <?php } ?>
                    </tbody>
                </table>
                <?php } else { ?>
                <div class="empty-msg"><i class="fa fa-info-circle" style="font-size:24px;color:#3498db;"></i><br>Tidak ada cuti aktif hari ini</div>
                <?php } ?>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button></div>
        </div>
    </div>
</div>

<!-- Modal: Sakit -->
<div class="modal fade modal-detail-dash" id="modalSakit" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #ff6b6b, #ee5a24);">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-medkit"></i> Sakit Aktif Hari Ini</h4>
            </div>
            <div class="modal-body">
                <?php if (count($detailSakit) > 0) { ?>
                <table class="table table-striped table-hover">
                    <thead><tr><th>No</th><th>Nama</th><th>Tgl Izin</th><th>Lamanya</th><th>Keperluan</th></tr></thead>
                    <tbody>
                    <?php $no=1; foreach ($detailSakit as $d) { ?>
                        <tr><td><?php echo $no++; ?></td><td><?php echo $d['nama']; ?></td><td><?php echo $d['tglizin']; ?></td><td><?php echo $d['lamanya']; ?></td><td><?php echo $d['keperluan']; ?></td></tr>
                    <?php } ?>
                    </tbody>
                </table>
                <?php } else { ?>
                <div class="empty-msg"><i class="fa fa-info-circle" style="font-size:24px;color:#3498db;"></i><br>Tidak ada yang sakit hari ini</div>
                <?php } ?>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button></div>
        </div>
    </div>
</div>

<!-- Modal: Detail Departemen -->
<div class="modal fade modal-detail-dash" id="modalDepartemen" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #3498db, #2980b9);">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-building-o"></i> <span id="deptTitle">Pegawai Departemen</span></h4>
            </div>
            <div class="modal-body">
                <div id="deptContent"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button></div>
        </div>
    </div>
</div>

<!-- Modal: Detail Log Status -->
<div class="modal fade modal-detail-dash" id="modalLogStatus" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header" id="logStatusHeader" style="background: linear-gradient(135deg, #3498db, #2980b9);">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-tasks"></i> <span id="logStatusTitle">Detail Log</span></h4>
            </div>
            <div class="modal-body">
                <div id="logStatusContent"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button></div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.0.0/dist/chart.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>
<script>
Chart.register(ChartDataLabels);

// Data departemen untuk popup
var deptData = <?php echo json_encode($detailDepartemen); ?>;
var deptNames = [<?php foreach ($departemen as $d) { echo "'" . addslashes($d['nama']) . "',"; } ?>];

// Data log per status untuk popup
var logData = {
    'Terlambat': <?php echo json_encode($detailLogTerlambat); ?>,
    'Masih Proses': <?php echo json_encode($detailLogProses); ?>,
    'Selesai': <?php echo json_encode($detailLogSelesai); ?>
};
var logColors = {
    'Terlambat': 'linear-gradient(135deg, #ff6b6b, #ee5a24)',
    'Masih Proses': 'linear-gradient(135deg, #ffa502, #e67e22)',
    'Selesai': 'linear-gradient(135deg, #54a0ff, #2e86de)'
};

// Diagram PIE: Pegawai per Departemen 
var chartDept = new Chart(document.getElementById('chartDepartemen'), {
    type: 'pie',
    data: {
        labels: [
            <?php foreach ($departemen as $d) { echo "'" . $d['nama'] . ": " . $d['jumlah'] . "',"; } ?>
        ],
        datasets: [{
            data: [<?php foreach ($departemen as $d) { echo $d['jumlah'] . ","; } ?>],
            backgroundColor: [
                'rgb(52, 152, 219)',
                'rgb(46, 204, 113)',
                'rgb(155, 89, 182)',
                'rgb(243, 156, 18)',
                'rgb(231, 76, 60)',
                'rgb(26, 188, 156)',
                'rgb(241, 196, 15)',
                'rgb(142, 68, 173)'
            ]
        }]
    },
    options: {
        responsive: true,
        onClick: function(evt, elements) {
            if (elements.length > 0) {
                var idx = elements[0].index;
                var nama = deptNames[idx];
                var pegawai = deptData[nama] || [];
                var html = '<h5 style="margin:15px;color:#333;"><i class="fa fa-building-o"></i> ' + nama + ' <span class="label label-primary">' + pegawai.length + ' orang</span></h5>';
                if (pegawai.length > 0) {
                    html += '<table class="table table-striped table-hover"><thead><tr><th>No</th><th>Nama Pegawai</th></tr></thead><tbody>';
                    pegawai.forEach(function(n, i) {
                        html += '<tr><td>' + (i+1) + '</td><td>' + n + '</td></tr>';
                    });
                    html += '</tbody></table>';
                } else {
                    html += '<div class="empty-msg">Tidak ada pegawai di departemen ini</div>';
                }
                document.getElementById('deptTitle').textContent = 'Departemen: ' + nama;
                document.getElementById('deptContent').innerHTML = html;
                $('#modalDepartemen').modal('show');
            }
        },
        plugins: {
            datalabels: {
                anchor: 'center',
                formatter: function(value, ctx) {
                    let sum = ctx.dataset.data.reduce((a, b) => a + b, 0);
                    let pct = (value * 100 / sum).toFixed(1);
                    return pct + '%';
                },
                color: '#fff',
                font: { weight: 'bold', size: 13 }
            },
            legend: { position: 'bottom' },
            title: {
                display: true,
                text: 'Total Pegawai: <?php echo $totalPegawai; ?>',
                font: { size: 14 }
            }
        }
    }
});

// Diagram PIE: Status Log Hari Ini
var logStatusNames = ['Terlambat', 'Masih Proses', 'Selesai'];
var chartLog = new Chart(document.getElementById('chartLogStatus'), {
    type: 'pie',
    data: {
        labels: [
            'Terlambat: <?php echo $logTerlambat; ?>',
            'Masih Proses: <?php echo $logProses; ?>',
            'Selesai: <?php echo $logSelesai; ?>'
        ],
        datasets: [{
            data: [<?php echo $logTerlambat; ?>, <?php echo $logProses; ?>, <?php echo $logSelesai; ?>],
            backgroundColor: [
                'rgb(255, 99, 132)',
                'rgb(255, 205, 86)',
                'rgb(54, 162, 235)'
            ]
        }]
    },
    options: {
        responsive: true,
        onClick: function(evt, elements) {
            if (elements.length > 0) {
                var idx = elements[0].index;
                var statusName = logStatusNames[idx];
                var logs = logData[statusName] || [];
                var html = '';
                if (logs.length > 0) {
                    html += '<table class="table table-striped table-hover"><thead><tr><th>No</th><th>Ticket</th><th>User</th><th>Mitra</th><th>Fas Order</th><th>Tgl Order</th><th>Uraian Order</th><th>Layanan</th><th>Target</th><th>Selesai</th></tr></thead><tbody>';
                    logs.forEach(function(l, i) {
                        var userName = l.nama_pegawai || l.userorder || l.iduser || '-';
                        var selesai = (l.tglselesai == '0000-00-00' || !l.tglselesai) ? '<span class="label label-warning">Belum Selesai</span>' : l.tglselesai;
                        html += '<tr><td>' + (i+1) + '</td><td>' + (l.idlog || '-') + '</td><td>' + userName + '</td><td>' + (l.nmcustomer || '-') + '</td><td>' + (l.fasorder || '-') + '</td><td>' + (l.tglorder || '-') + '</td><td>' + (l.desorder || '-') + '</td><td>' + (l.deslayan || '-') + '</td><td>' + (l.tgltarget || '-') + '</td><td>' + selesai + '</td></tr>';
                    });
                    html += '</tbody></table>';
                } else {
                    html += '<div class="empty-msg"><i class="fa fa-info-circle" style="font-size:24px;color:#3498db;"></i><br>Tidak ada log dengan status ini</div>';
                }
                document.getElementById('logStatusTitle').textContent = 'Log Status: ' + statusName + ' (' + logs.length + ')';
                document.getElementById('logStatusHeader').style.background = logColors[statusName];
                document.getElementById('logStatusContent').innerHTML = html;
                $('#modalLogStatus').modal('show');
            }
        },
        plugins: {
            datalabels: {
                anchor: 'center',
                formatter: function(value, ctx) {
                    let sum = ctx.dataset.data.reduce((a, b) => a + b, 0);
                    if (sum === 0) return '';
                    let pct = (value * 100 / sum).toFixed(1);
                    return value === 0 ? '' : pct + '%';
                },
                color: '#fff',
                font: { weight: 'bold', size: 13 }
            },
            legend: { position: 'bottom' },
            title: {
                display: true,
                text: 'Total Log: <?php echo $logTotal; ?>',
                font: { size: 14 }
            }
        }
    }
});

// Ubah cursor jadi pointer saat hover di atas slice pie chart
var canvasDept = document.getElementById('chartDepartemen');
var canvasLog = document.getElementById('chartLogStatus');
[canvasDept, canvasLog].forEach(function(canvas) {
    canvas.addEventListener('mousemove', function(e) {
        var chart = (canvas === canvasDept) ? chartDept : chartLog;
        var points = chart.getElementsAtEventForMode(e, 'nearest', {intersect: true}, false);
        canvas.style.cursor = points.length > 0 ? 'pointer' : 'default';
    });
});
</script>

</body>
</html>
