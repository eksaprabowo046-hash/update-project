<?php
// 
include "dbase.php";
include "islogin.php";

$pesan = "";
$iduser = $_SESSION['DEFAULT_IDUSER'];
$kodjab = isset($_SESSION['DEFAULT_KODJAB']) ? $_SESSION['DEFAULT_KODJAB'] : 0;

// ========== AUTO-MIGRATION: Add missing columns ==========
try {
    $cek = $conn->query("SHOW COLUMNS FROM ruser LIKE 'nik'");
    if ($cek->rowCount() == 0) {
        $conn->exec("ALTER TABLE ruser ADD COLUMN nik VARCHAR(50) DEFAULT NULL AFTER iduser");
    }
} catch (Exception $e) {}

try {
    $cek = $conn->query("SHOW COLUMNS FROM ruser LIKE 'bank'");
    if ($cek->rowCount() == 0) {
        $conn->exec("ALTER TABLE ruser ADD COLUMN bank VARCHAR(100) DEFAULT NULL AFTER nama");
    }
} catch (Exception $e) {}

try {
    $cek = $conn->query("SHOW COLUMNS FROM tgaji LIKE 'bukti_tf'");
    if ($cek->rowCount() == 0) {
        $conn->exec("ALTER TABLE tgaji ADD COLUMN bukti_tf VARCHAR(255) DEFAULT NULL");
    }
} catch (Exception $e) {}

try {
    $cek = $conn->query("SHOW COLUMNS FROM tgaji LIKE 'keterangan'");
    if ($cek->rowCount() == 0) {
        $conn->exec("ALTER TABLE tgaji ADD COLUMN keterangan TEXT DEFAULT NULL");
    }
} catch (Exception $e) {}

// ========== EXPORT EXCEL HANDLER ==========
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    $filter_pegawai = isset($_GET['filter_pegawai']) ? trim($_GET['filter_pegawai']) : '';
    $filter_bulan   = isset($_GET['filter_bulan']) ? trim($_GET['filter_bulan']) : '';
    $filter_tahun   = isset($_GET['filter_tahun']) ? trim($_GET['filter_tahun']) : '';

    $params = [];
    $where  = " WHERE 1=1 ";

    if (!empty($filter_pegawai)) {
        $where .= " AND g.iduser_pegawai = :filter_pegawai ";
        $params[':filter_pegawai'] = $filter_pegawai;
    }
    if (!empty($filter_bulan) && !empty($filter_tahun)) {
        $where .= " AND g.periode = :periode ";
        $params[':periode'] = $filter_tahun . '-' . str_pad($filter_bulan, 2, '0', STR_PAD_LEFT);
    } elseif (!empty($filter_bulan)) {
        $where .= " AND SUBSTRING(g.periode, 6, 2) = :filter_bulan ";
        $params[':filter_bulan'] = str_pad($filter_bulan, 2, '0', STR_PAD_LEFT);
    } elseif (!empty($filter_tahun)) {
        $where .= " AND SUBSTRING(g.periode, 1, 4) = :filter_tahun ";
        $params[':filter_tahun'] = $filter_tahun;
    }

    $strsql = "SELECT g.*, ru.nama AS nama_pegawai, ru.nik, ru.bank
               FROM tgaji g
               LEFT JOIN ruser ru ON g.iduser_pegawai = ru.iduser
               LEFT JOIN tbl_jabatan tj ON ru.kodjab = tj.kodjab
               $where
               ORDER BY g.periode DESC, ru.kodjab ASC, ru.nama ASC";

    $sql = $conn->prepare($strsql);
    $sql->execute($params);

    // Build Excel output
    $filename = "Laporan_Penggajian_" . date('Ymd_His') . ".xls";
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Cache-Control: max-age=0");

    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head><meta charset="UTF-8"><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Laporan Penggajian</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head>';
    echo '<body>';
    echo '<table border="1" cellpadding="3" cellspacing="0" style="border-collapse:collapse; font-family:Arial; font-size:10pt;">';

    // Header Row 1 (grouped)
    echo '<tr style="background-color:#FFA500; font-weight:bold; text-align:center;">';
    echo '<th rowspan="2">NO</th>';
    echo '<th rowspan="2">NIK</th>';
    echo '<th rowspan="2">NAMA</th>';
    echo '<th rowspan="2">BANK</th>';
    echo '<th rowspan="2">PERIODE</th>';
    echo '<th colspan="5">GAJI</th>';
    echo '<th rowspan="2">GAJI KOTOR</th>';
    echo '<th colspan="4">POTONGAN</th>';
    echo '<th rowspan="2">JML POT</th>';
    echo '<th rowspan="2">DITERIMA KARYAWAN</th>';
    echo '<th rowspan="2">STATUS PINJAMAN</th>';
    echo '<th rowspan="2">KETERANGAN</th>';
    echo '</tr>';

    // Header Row 2
    echo '<tr style="background-color:#FFA500; font-weight:bold; text-align:center;">';
    echo '<th>POKOK</th>';
    echo '<th>TUN JAB</th>';
    echo '<th>T. PERI DNS</th>';
    echo '<th>LEMBUR</th>';
    echo '<th>BONUS</th>';
    echo '<th>BPJS KES</th>';
    echo '<th>BPJS TK</th>';
    echo '<th>PINJAMAN</th>';
    echo '<th>POT LAINNYA</th>';
    echo '</tr>';

    $no = 1;
    $totPokok = 0; $totTunJab = 0; $totTunPerj = 0; $totLembur = 0; $totBonus = 0;
    $totGajiKotor = 0;
    $totBpjsKes = 0; $totBpjsTk = 0; $totPotPinjaman = 0; $totPotLain = 0;
    $totJmlPot = 0; $totDiterima = 0;

    while ($row = $sql->fetch(PDO::FETCH_ASSOC)) {
        $gaji_kotor = floatval($row['gaji_pokok']) + floatval($row['tunj_jabatan']) + floatval($row['tunj_perjalanan']) + floatval($row['lembur']) + floatval($row['bonus']);
        $jml_pot    = floatval($row['bpjs_kesehatan']) + floatval($row['bpjs_tk']) + floatval($row['pot_pinjaman']) + floatval($row['pot_lain']);
        $diterima   = $gaji_kotor - $jml_pot;

        $totPokok += floatval($row['gaji_pokok']);
        $totTunJab += floatval($row['tunj_jabatan']);
        $totTunPerj += floatval($row['tunj_perjalanan']);
        $totLembur += floatval($row['lembur']);
        $totBonus += floatval($row['bonus']);
        $totGajiKotor += $gaji_kotor;
        $totBpjsKes += floatval($row['bpjs_kesehatan']);
        $totBpjsTk += floatval($row['bpjs_tk']);
        $totPotPinjaman += floatval($row['pot_pinjaman']);
        $totPotLain += floatval($row['pot_lain']);
        $totJmlPot += $jml_pot;
        $totDiterima += $diterima;

        // Status pinjaman - detail cicilan per periode
        $status_pinjaman = '-';
        try {
            $qp = $conn->prepare("
                SELECT nominal, tenor, cicilan_perbulan, tgl_pengajuan, status_lunas
                FROM tpinjaman 
                WHERE iduser_pemohon = :iduser 
                  AND status_approval = 'Approved' 
                  AND DATE_FORMAT(tgl_pengajuan, '%Y-%m') <= :periode
                ORDER BY tgl_pengajuan ASC
            ");
            $qp->execute([':iduser' => $row['iduser_pegawai'], ':periode' => $row['periode']]);
            $pinjaman_list = $qp->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($pinjaman_list) > 0) {
                $parts = [];
                $prd = explode('-', $row['periode']);
                $prd_year = intval($prd[0]);
                $prd_month = intval($prd[1]);
                
                foreach ($pinjaman_list as $pin) {
                    $tenor_total = intval($pin['tenor']);
                    $loan_year = intval(date('Y', strtotime($pin['tgl_pengajuan'])));
                    $loan_month = intval(date('m', strtotime($pin['tgl_pengajuan'])));
                    $cicilan_ke = ($prd_year - $loan_year) * 12 + ($prd_month - $loan_month) + 1;
                    
                    if ($cicilan_ke < 1) continue;
                    if ($cicilan_ke > $tenor_total) continue;
                    
                    $text = 'Cicilan ke ' . $cicilan_ke . ' dari ' . $tenor_total . ' (Rp ' . number_format($pin['nominal'], 0, ',', '.') . ')';
                    if ($cicilan_ke == $tenor_total) {
                        $text .= ' - LUNAS';
                    }
                    $parts[] = $text;
                }
                $status_pinjaman = implode('; ', $parts);
            }
        } catch (Exception $e) {}

        echo '<tr>';
        echo '<td style="text-align:center;">' . $no . '</td>';
        echo '<td>' . htmlspecialchars($row['nik'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($row['nama_pegawai'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($row['bank'] ?? '') . '</td>';
        // Format periode untuk Excel
        $bulanArrExcel = ['01'=>'Jan','02'=>'Feb','03'=>'Mar','04'=>'Apr','05'=>'Mei','06'=>'Jun','07'=>'Jul','08'=>'Ags','09'=>'Sep','10'=>'Okt','11'=>'Nov','12'=>'Des'];
        $prdParts = explode('-', $row['periode'] ?? '');
        $prdDisplay = (count($prdParts) == 2) ? ($bulanArrExcel[$prdParts[1]] ?? $prdParts[1]) . ' ' . $prdParts[0] : $row['periode'];
        echo '<td style="text-align:center;">' . $prdDisplay . '</td>';
        echo '<td style="text-align:right;">' . number_format($row['gaji_pokok'], 0, ',', '.') . '</td>';
        echo '<td style="text-align:right;">' . number_format($row['tunj_jabatan'], 0, ',', '.') . '</td>';
        echo '<td style="text-align:right;">' . number_format($row['tunj_perjalanan'], 0, ',', '.') . '</td>';
        echo '<td style="text-align:right;">' . number_format($row['lembur'], 0, ',', '.') . '</td>';
        echo '<td style="text-align:right;">' . number_format($row['bonus'], 0, ',', '.') . '</td>';
        echo '<td style="text-align:right; font-weight:bold;">' . number_format($gaji_kotor, 0, ',', '.') . '</td>';
        echo '<td style="text-align:right;">' . number_format($row['bpjs_kesehatan'], 0, ',', '.') . '</td>';
        echo '<td style="text-align:right;">' . number_format($row['bpjs_tk'], 0, ',', '.') . '</td>';
        echo '<td style="text-align:right;">' . number_format($row['pot_pinjaman'], 0, ',', '.') . '</td>';
        echo '<td style="text-align:right;">' . number_format($row['pot_lain'], 0, ',', '.') . '</td>';
        echo '<td style="text-align:right; font-weight:bold;">' . number_format($jml_pot, 0, ',', '.') . '</td>';
        echo '<td style="text-align:right; font-weight:bold;">' . number_format($diterima, 0, ',', '.') . '</td>';
        echo '<td style="text-align:center;">' . $status_pinjaman . '</td>';
        echo '<td>' . htmlspecialchars($row['keterangan'] ?? '') . '</td>';
        echo '</tr>';
        $no++;
    }

    // Footer totals
    if ($no > 1) {
        echo '<tr style="font-weight:bold; background-color:#FFFF00;">';
        echo '<td colspan="5" style="text-align:right;">TOTAL:</td>';
        echo '<td style="text-align:right;">' . number_format($totPokok, 0, ',', '.') . '</td>';
        echo '<td style="text-align:right;">' . number_format($totTunJab, 0, ',', '.') . '</td>';
        echo '<td style="text-align:right;">' . number_format($totTunPerj, 0, ',', '.') . '</td>';
        echo '<td style="text-align:right;">' . number_format($totLembur, 0, ',', '.') . '</td>';
        echo '<td style="text-align:right;">' . number_format($totBonus, 0, ',', '.') . '</td>';
        echo '<td style="text-align:right;">' . number_format($totGajiKotor, 0, ',', '.') . '</td>';
        echo '<td style="text-align:right;">' . number_format($totBpjsKes, 0, ',', '.') . '</td>';
        echo '<td style="text-align:right;">' . number_format($totBpjsTk, 0, ',', '.') . '</td>';
        echo '<td style="text-align:right;">' . number_format($totPotPinjaman, 0, ',', '.') . '</td>';
        echo '<td style="text-align:right;">' . number_format($totPotLain, 0, ',', '.') . '</td>';
        echo '<td style="text-align:right;">' . number_format($totJmlPot, 0, ',', '.') . '</td>';
        echo '<td style="text-align:right;">' . number_format($totDiterima, 0, ',', '.') . '</td>';
        echo '<td colspan="2"></td>';
        echo '</tr>';
    }

    echo '</table>';
    echo '</body></html>';
    exit;
}

// ========== FILTERS ==========
$filter_pegawai = isset($_GET['filter_pegawai']) ? trim($_GET['filter_pegawai']) : '';
$filter_bulan   = isset($_GET['filter_bulan']) ? trim($_GET['filter_bulan']) : '';
$filter_tahun   = isset($_GET['filter_tahun']) ? trim($_GET['filter_tahun']) : '';

$bulanArr = [
    '01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April',
    '05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus',
    '09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'
];
?>

<style>
.lap-table th {
    background-color: #f5f5f5;
    color: #333;
    text-align: center;
    vertical-align: middle !important;
    font-size: 13px;
    padding: 6px 8px !important;
    white-space: nowrap;
}
.lap-table td {
    font-size: 13px;
    padding: 5px 8px !important;
    white-space: nowrap;
}
.lap-table .money {
    text-align: right;
}
.btn-export-excel {
    background-color: #217346;
    color: white;
    border: none;
}
.btn-export-excel:hover {
    background-color: #1a5c38;
    color: white;
}
/* Modal for bukti TF */
.modal-bukti {
    display: none;
    position: fixed;
    z-index: 1050;
    left: 0; top: 0;
    width: 100%; height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.7);
}
.modal-bukti-content {
    margin: 3% auto;
    max-width: 800px;
    background: #fff;
    border-radius: 8px;
    padding: 20px;
}
.modal-bukti-content img {
    max-width: 100%;
    max-height: 70vh;
}
.modal-bukti-close {
    font-size: 28px;
    cursor: pointer;
    color: #aaa;
    font-weight: bold;
    line-height: 1;
}
.modal-bukti-close:hover {
    color: #000;
}
.bukti-tf-gallery-item {
    border: 1px solid #eee;
    border-radius: 6px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: transform 0.2s;
    cursor: pointer;
}
.bukti-tf-gallery-item:hover {
    transform: scale(1.03);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.bukti-tf-gallery-item img {
    width: 200px;
    height: 150px;
    object-fit: cover;
}
.bukti-tf-gallery-pdf {
    width: 200px;
    height: 150px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: #f5f5f5;
    color: #e53935;
    text-decoration: none;
}
.bukti-tf-gallery-pdf i {
    font-size: 40px;
    margin-bottom: 5px;
}
</style>

<body>
<div class="row">
    <ol class="breadcrumb">
        <li><i class="fa fa-home"></i> LAPORAN PENGGAJIAN</li>
    </ol>
    <section class="panel">
        <header class="panel-heading">

            <form role="form" method="GET" action="index.php">
                <input type="hidden" name="par" value="51">

                <!-- Filter: Nama Pegawai -->
                <div class="form-group col-xs-12 col-sm-3">
                    <label>Nama Pegawai</label>
                    <select name="filter_pegawai" class="form-control">
                        <option value="">-- Semua Pegawai --</option>
                        <?php
                        $qpeg = $conn->prepare("SELECT iduser, nama FROM ruser WHERE stsaktif = 1 ORDER BY nik ASC, nama ASC");
                        $qpeg->execute();
                        while ($rpeg = $qpeg->fetch(PDO::FETCH_ASSOC)) {
                            $sel = ($filter_pegawai == $rpeg['iduser']) ? 'selected' : '';
                            echo "<option value='" . htmlspecialchars($rpeg['iduser']) . "' $sel>" . htmlspecialchars($rpeg['nama']) . "</option>";
                        }
                        ?>
                    </select>
                </div>

                <!-- Filter: Bulan -->
                <div class="form-group col-xs-12 col-sm-2">
                    <label>Bulan</label>
                    <select name="filter_bulan" class="form-control">
                        <option value="">-- Semua --</option>
                        <?php
                        foreach ($bulanArr as $kode => $nama_bln) {
                            $sel = ($filter_bulan == $kode) ? 'selected' : '';
                            echo "<option value='$kode' $sel>$nama_bln</option>";
                        }
                        ?>
                    </select>
                </div>

                <!-- Filter: Tahun -->
                <div class="form-group col-xs-12 col-sm-2">
                    <label>Tahun</label>
                    <select name="filter_tahun" class="form-control">
                        <option value="">-- Semua --</option>
                        <?php
                        try {
                            $qthn = $conn->prepare("SELECT DISTINCT YEAR(STR_TO_DATE(periode, '%Y-%m')) AS thn FROM tgaji ORDER BY thn DESC");
                            $qthn->execute();
                            while ($rthn = $qthn->fetch(PDO::FETCH_ASSOC)) {
                                if ($rthn['thn']) {
                                    $sel = ($filter_tahun == $rthn['thn']) ? 'selected' : '';
                                    echo "<option value='" . $rthn['thn'] . "' $sel>" . $rthn['thn'] . "</option>";
                                }
                            }
                        } catch (Exception $e) {
                            // If no data yet, show current year
                            echo "<option value='" . date('Y') . "'>" . date('Y') . "</option>";
                        }
                        ?>
                    </select>
                </div>

                <!-- Buttons -->
                <div class="form-group col-xs-12 col-sm-4">
                    <label>&nbsp;</label><br>
                    <button type="submit" name="submit" class="btn btn-primary" value="Y">
                        <i class="fa fa-search"></i> Filter
                    </button>
                    <button type="reset" class="btn btn-danger">Reset</button>
                    <button type="button" class="btn btn-export-excel" onclick="exportExcel()">
                        <i class="fa fa-download"></i> Export Excel
                    </button>
                </div>
            </form>
            <div class="clearfix"></div>

            <h4><font color="blue"><?php echo $pesan; ?></font></h4>
        </header>

        <section class="content">
            <div align="center"><strong>LAPORAN PENGGAJIAN</strong>
            <?php
            if (!empty($filter_bulan) || !empty($filter_tahun)) {
                $periode_display = '';
                if (!empty($filter_bulan)) $periode_display .= $bulanArr[$filter_bulan] ?? $filter_bulan;
                if (!empty($filter_bulan) && !empty($filter_tahun)) $periode_display .= ' ';
                if (!empty($filter_tahun)) $periode_display .= $filter_tahun;
                echo "<br><small>Periode: $periode_display</small>";
            }
            ?>
            </div>

            <div class="box-body">
                <div class="table-responsive">
                <table id="tabel_lap_gaji" class="table table-bordered table-striped table-hover lap-table">
                    <thead>
                        <tr>
                            <th rowspan="2" style="width:35px;">NO</th>
                            <th rowspan="2">NIK</th>
                            <th rowspan="2">NAMA</th>
                            <th rowspan="2">BANK</th>
                            <th rowspan="2">PERIODE</th>
                            <th colspan="5">GAJI</th>
                            <th rowspan="2">GAJI KOTOR</th>
                            <th colspan="4">POTONGAN</th>
                            <th rowspan="2">JML POT</th>
                            <th rowspan="2">DITERIMA KARYAWAN</th>
                            <th rowspan="2">STATUS PINJAMAN</th>
                            <th rowspan="2">KETERANGAN</th>
                            <th rowspan="2">BUKTI TF</th>
                        </tr>
                        <tr>
                            <!-- GAJI sub-columns -->
                            <th>POKOK</th>
                            <th>TUN JAB</th>
                            <th>T. PERI DNS</th>
                            <th>LEMBUR</th>
                            <th>BONUS</th>
                            <!-- POTONGAN sub-columns -->
                            <th>BPJS KES</th>
                            <th>BPJS TK</th>
                            <th>PINJAMAN</th>
                            <th>POT LAINNYA</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    try {
                        $params = [];
                        $where  = " WHERE 1=1 ";

                        if (!empty($filter_pegawai)) {
                            $where .= " AND g.iduser_pegawai = :filter_pegawai ";
                            $params[':filter_pegawai'] = $filter_pegawai;
                        }
                        if (!empty($filter_bulan) && !empty($filter_tahun)) {
                            $where .= " AND g.periode = :periode ";
                            $params[':periode'] = $filter_tahun . '-' . str_pad($filter_bulan, 2, '0', STR_PAD_LEFT);
                        } elseif (!empty($filter_bulan)) {
                            $where .= " AND SUBSTRING(g.periode, 6, 2) = :filter_bulan ";
                            $params[':filter_bulan'] = str_pad($filter_bulan, 2, '0', STR_PAD_LEFT);
                        } elseif (!empty($filter_tahun)) {
                            $where .= " AND SUBSTRING(g.periode, 1, 4) = :filter_tahun ";
                            $params[':filter_tahun'] = $filter_tahun;
                        }

                        $strsql = "SELECT g.*, ru.nama AS nama_pegawai, ru.nik, ru.bank
                                   FROM tgaji g
                                   LEFT JOIN ruser ru ON g.iduser_pegawai = ru.iduser
                                   LEFT JOIN tbl_jabatan tj ON ru.kodjab = tj.kodjab
                                   $where
                                   ORDER BY g.periode DESC, ru.kodjab ASC, ru.nama ASC";

                        $sql = $conn->prepare($strsql);
                        $sql->execute($params);

                        $no = 1;
                        $totPokok = 0; $totTunJab = 0; $totTunPerj = 0; $totLembur = 0; $totBonus = 0;
                        $totGajiKotor = 0;
                        $totBpjsKes = 0; $totBpjsTk = 0; $totPotPinjaman = 0; $totPotLain = 0;
                        $totJmlPot = 0; $totDiterima = 0;

                        while ($row = $sql->fetch(PDO::FETCH_ASSOC)) {
                            $gaji_kotor = floatval($row['gaji_pokok']) + floatval($row['tunj_jabatan']) + floatval($row['tunj_perjalanan']) + floatval($row['lembur']) + floatval($row['bonus']);
                            $jml_pot    = floatval($row['bpjs_kesehatan']) + floatval($row['bpjs_tk']) + floatval($row['pot_pinjaman']) + floatval($row['pot_lain']);
                            $diterima   = $gaji_kotor - $jml_pot;

                            // Accumulate totals
                            $totPokok += floatval($row['gaji_pokok']);
                            $totTunJab += floatval($row['tunj_jabatan']);
                            $totTunPerj += floatval($row['tunj_perjalanan']);
                            $totLembur += floatval($row['lembur']);
                            $totBonus += floatval($row['bonus']);
                            $totGajiKotor += $gaji_kotor;
                            $totBpjsKes += floatval($row['bpjs_kesehatan']);
                            $totBpjsTk += floatval($row['bpjs_tk']);
                            $totPotPinjaman += floatval($row['pot_pinjaman']);
                            $totPotLain += floatval($row['pot_lain']);
                            $totJmlPot += $jml_pot;
                            $totDiterima += $diterima;

                            // Status pinjaman - detail cicilan per periode
                            $status_pinjaman = '-';
                            try {
                                $qp = $conn->prepare("
                                    SELECT nominal, tenor, cicilan_perbulan, tgl_pengajuan, status_lunas
                                    FROM tpinjaman 
                                    WHERE iduser_pemohon = :iduser 
                                      AND status_approval = 'Approved' 
                                      AND DATE_FORMAT(tgl_pengajuan, '%Y-%m') <= :periode
                                    ORDER BY tgl_pengajuan ASC
                                ");
                                $qp->execute([':iduser' => $row['iduser_pegawai'], ':periode' => $row['periode']]);
                                $pinjaman_list = $qp->fetchAll(PDO::FETCH_ASSOC);
                                
                                if (count($pinjaman_list) > 0) {
                                    $parts = [];
                                    $prd = explode('-', $row['periode']);
                                    $prd_year = intval($prd[0]);
                                    $prd_month = intval($prd[1]);
                                    
                                    foreach ($pinjaman_list as $pin) {
                                        $tenor_total = intval($pin['tenor']);
                                        $loan_year = intval(date('Y', strtotime($pin['tgl_pengajuan'])));
                                        $loan_month = intval(date('m', strtotime($pin['tgl_pengajuan'])));
                                        $cicilan_ke = ($prd_year - $loan_year) * 12 + ($prd_month - $loan_month) + 1;
                                        
                                        if ($cicilan_ke < 1) continue;
                                        if ($cicilan_ke > $tenor_total) continue;
                                        
                                        $text = 'Cicilan ke ' . $cicilan_ke . ' dari ' . $tenor_total . ' (Rp ' . number_format($pin['nominal'], 0, ',', '.') . ')';
                                        if ($cicilan_ke == $tenor_total) {
                                            $text .= ' - LUNAS';
                                        }
                                        $parts[] = $text;
                                    }
                                    $status_pinjaman = implode('<br>', $parts);
                                }
                            } catch (Exception $e) {}

                            // Bukti TF - parse JSON or legacy single file
                            $buktiTfArr = [];
                            if (!empty($row['bukti_tf'])) {
                                $decoded = json_decode($row['bukti_tf'], true);
                                $buktiTfArr = is_array($decoded) ? $decoded : [$row['bukti_tf']];
                            }
                            if (count($buktiTfArr) > 0) {
                                $buktiTfJson = htmlspecialchars(json_encode($buktiTfArr), ENT_QUOTES);
                                $buktiTfBtn = '<button type="button" class="btn btn-info btn-xs" onclick=\'viewBuktiTfGallery(' . $buktiTfJson . ')\'><i class="fa fa-image"></i> ' . count($buktiTfArr) . ' file</button>';
                            } else {
                                $buktiTfBtn = '<span style="color:#ccc;">-</span>';
                            }

                            $statusBadge = '';
                            if ($row['status_gaji'] == 'Generated') {
                                $statusBadge = ' <span class="label label-success" style="font-size:9px;">G</span>';
                            }

                            echo '<tr>';
                            echo '<td style="text-align:center;">' . $no . '</td>';
                            echo '<td>' . htmlspecialchars($row['nik'] ?? '') . '</td>';
                            echo '<td>' . htmlspecialchars($row['nama_pegawai'] ?? '') . $statusBadge . '</td>';
                            echo '<td>' . htmlspecialchars($row['bank'] ?? '') . '</td>';
                            // Format periode
                            $prdParts = explode('-', $row['periode'] ?? '');
                            $bulanNama = ['01'=>'Jan','02'=>'Feb','03'=>'Mar','04'=>'Apr','05'=>'Mei','06'=>'Jun','07'=>'Jul','08'=>'Ags','09'=>'Sep','10'=>'Okt','11'=>'Nov','12'=>'Des'];
                            $prdDisplay = (count($prdParts) == 2) ? ($bulanNama[$prdParts[1]] ?? $prdParts[1]) . ' ' . $prdParts[0] : $row['periode'];
                            echo '<td style="text-align:center;">' . $prdDisplay . '</td>';
                            echo '<td class="money">' . number_format($row['gaji_pokok'], 0, ',', '.') . '</td>';
                            echo '<td class="money">' . number_format($row['tunj_jabatan'], 0, ',', '.') . '</td>';
                            echo '<td class="money">' . number_format($row['tunj_perjalanan'], 0, ',', '.') . '</td>';
                            $lembur_link = ($row['lembur'] > 0) ? '<a href="index.php?par=36&filter_pegawai=' . $row['iduser_pegawai'] . '" title="Tracing Lembur">' . number_format($row['lembur'], 0, ',', '.') . '</a>' : '0';
                            $pinjaman_link = ($row['pot_pinjaman'] > 0) ? '<a href="index.php?par=52&filter_pegawai=' . $row['iduser_pegawai'] . '" title="Tracing Pinjaman">' . number_format($row['pot_pinjaman'], 0, ',', '.') . '</a>' : '0';

                            echo '<td class="money">' . $lembur_link . '</td>';
                            echo '<td class="money">' . number_format($row['bonus'], 0, ',', '.') . '</td>';
                            echo '<td class="money" style="font-weight:bold;">' . number_format($gaji_kotor, 0, ',', '.') . '</td>';
                            echo '<td class="money">' . number_format($row['bpjs_kesehatan'], 0, ',', '.') . '</td>';
                            echo '<td class="money">' . number_format($row['bpjs_tk'], 0, ',', '.') . '</td>';
                            echo '<td class="money">' . $pinjaman_link . '</td>';
                            echo '<td class="money">' . number_format($row['pot_lain'], 0, ',', '.') . '</td>';
                            echo '<td class="money" style="font-weight:bold;">' . number_format($jml_pot, 0, ',', '.') . '</td>';
                            echo '<td class="money" style="font-weight:bold;">' . number_format($diterima, 0, ',', '.') . '</td>';
                            echo '<td style="text-align:center;">' . $status_pinjaman . '</td>';
                            echo '<td>' . htmlspecialchars($row['keterangan'] ?? '') . '</td>';
                            echo '<td style="text-align:center;">' . $buktiTfBtn . '</td>';
                            echo '</tr>';
                            $no++;
                        }

                        // Footer totals
                        if ($no > 1) {
                            echo '<tr style="background-color:#e6e6e6;">';
                            echo '<td colspan="5" style="text-align:right; font-weight:bold; font-size:15px !important;">TOTAL:</td>';
                            echo '<td class="money" style="font-weight:bold; font-size:14px !important; color:#000;">' . number_format($totPokok, 0, ',', '.') . '</td>';
                            echo '<td class="money" style="font-weight:bold; font-size:14px !important; color:#000;">' . number_format($totTunJab, 0, ',', '.') . '</td>';
                            echo '<td class="money" style="font-weight:bold; font-size:14px !important; color:#000;">' . number_format($totTunPerj, 0, ',', '.') . '</td>';
                            echo '<td class="money" style="font-weight:bold; font-size:14px !important; color:#000;">' . number_format($totLembur, 0, ',', '.') . '</td>';
                            echo '<td class="money" style="font-weight:bold; font-size:14px !important; color:#000;">' . number_format($totBonus, 0, ',', '.') . '</td>';
                            echo '<td class="money" style="font-weight:bold; font-size:14px !important; color:#000;">' . number_format($totGajiKotor, 0, ',', '.') . '</td>';
                            echo '<td class="money" style="font-weight:bold; font-size:14px !important; color:#000;">' . number_format($totBpjsKes, 0, ',', '.') . '</td>';
                            echo '<td class="money" style="font-weight:bold; font-size:14px !important; color:#000;">' . number_format($totBpjsTk, 0, ',', '.') . '</td>';
                            echo '<td class="money" style="font-weight:bold; font-size:14px !important; color:#000;">' . number_format($totPotPinjaman, 0, ',', '.') . '</td>';
                            echo '<td class="money" style="font-weight:bold; font-size:14px !important; color:#000;">' . number_format($totPotLain, 0, ',', '.') . '</td>';
                            echo '<td class="money" style="font-weight:bold; font-size:14px !important; color:#000;">' . number_format($totJmlPot, 0, ',', '.') . '</td>';
                            echo '<td class="money" style="font-weight:bold; font-size:14px !important; color:#000;">' . number_format($totDiterima, 0, ',', '.') . '</td>';
                            echo '<td colspan="4"></td>';
                            echo '</tr>';
                        }

                    } catch (PDOException $e) {
                        echo '<tr><td colspan="20" class="text-center">Terjadi error: ' . $e->getMessage() . '</td></tr>';
                    }
                    ?>
                    </tbody>
                </table>
                </div>
            </div>
        </section>
    </section>
</div>

<!-- Modal View Bukti TF (Gallery) -->
<div id="modalBuktiTF" class="modal-bukti">
    <div class="modal-bukti-content">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
            <h4 style="margin:0;"><i class="fa fa-image"></i> Bukti Transfer</h4>
            <span class="modal-bukti-close" onclick="closeBuktiTF()">&times;</span>
        </div>
        <hr style="margin:5px 0 15px;">
        <div id="buktiTfGalleryLap" style="display:flex; flex-wrap:wrap; gap:10px; justify-content:center;"></div>
    </div>
</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
<script>
$(document).ready(function () {
    var tableId = '#tabel_lap_gaji';

    if ($.fn.DataTable.isDataTable(tableId)) {
        $(tableId).DataTable().destroy();
    }

    $(tableId).DataTable({
        pageLength: 25,
        lengthChange: true,
        searching: true,
        ordering: true,
        order: [],
        info: true,
        autoWidth: false,
        language: {
            search: "Cari:",
            lengthMenu: "Tampilkan _MENU_ data",
            info: "Menampilkan _START_ s/d _END_ dari _TOTAL_ data",
            infoEmpty: "Tidak ada data",
            zeroRecords: "Data tidak ditemukan",
            paginate: {
                next: ">",
                previous: "<"
            }
        }
    });
});

function viewBuktiTfGallery(files) {
    var gallery = document.getElementById('buktiTfGalleryLap');
    gallery.innerHTML = '';
    
    files.forEach(function(filename) {
        var ext = filename.split('.').pop().toLowerCase();
        var item = document.createElement('div');
        item.className = 'bukti-tf-gallery-item';
        
        if (ext === 'pdf') {
            item.innerHTML = '<a href="uploads/bukti_tf/' + filename + '" target="_blank" class="bukti-tf-gallery-pdf">' +
                '<i class="fa fa-file-pdf-o"></i>' +
                '<small>' + filename.substring(0, 20) + '...</small>' +
                '</a>';
        } else {
            item.innerHTML = '<a href="uploads/bukti_tf/' + filename + '" target="_blank">' +
                '<img src="uploads/bukti_tf/' + filename + '" alt="Bukti TF">' +
                '</a>';
        }
        gallery.appendChild(item);
    });
    
    document.getElementById('modalBuktiTF').style.display = 'block';
}

function closeBuktiTF() {
    document.getElementById('modalBuktiTF').style.display = 'none';
    document.getElementById('buktiTfGalleryLap').innerHTML = '';
}

// Close modal on outside click
window.addEventListener('click', function(event) {
    var modal = document.getElementById('modalBuktiTF');
    if (event.target == modal) {
        closeBuktiTF();
    }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/xlsx-js-style/dist/xlsx.bundle.js"></script>
<script>
function exportExcel() {
    var table = document.getElementById('tabel_lap_gaji');
    var clone = table.cloneNode(true);
    
    // Remove last column (Bukti TF)
    clone.querySelectorAll('tr').forEach(function(row) {
        var cells = row.querySelectorAll('th, td');
        if (cells.length > 0) cells[cells.length - 1].remove();
    });
    
    // Replace <br> with space (fix header text like DITERIMA KARYAWAN)
    clone.querySelectorAll('br').forEach(function(br) {
        br.replaceWith(' ');
    });
    
    // Remove badges/labels completely (remove G badge from names)
    clone.querySelectorAll('.label').forEach(function(el) {
        el.remove();
    });
    // Convert links to plain text (keep the number)
    clone.querySelectorAll('button, a').forEach(function(el) {
        el.outerHTML = el.textContent;
    });
    
    // Generate workbook
    var wb = XLSX.utils.table_to_book(clone, {sheet: 'Laporan Penggajian', raw: true});
    var ws = wb.Sheets['Laporan Penggajian'];
    var range = XLSX.utils.decode_range(ws['!ref']);
    
    // Style definitions
    var headerStyle = {
        fill: {fgColor: {rgb: "FF8C00"}},
        font: {bold: true, color: {rgb: "000000"}, sz: 10},
        alignment: {horizontal: "center", vertical: "center"},
        border: {
            top: {style: "thin", color: {rgb: "000000"}},
            bottom: {style: "thin", color: {rgb: "000000"}},
            left: {style: "thin", color: {rgb: "000000"}},
            right: {style: "thin", color: {rgb: "000000"}}
        }
    };
    var totalStyle = {
        fill: {fgColor: {rgb: "FFFF00"}},
        font: {bold: true, sz: 10},
        border: {
            top: {style: "thin", color: {rgb: "000000"}},
            bottom: {style: "thin", color: {rgb: "000000"}},
            left: {style: "thin", color: {rgb: "000000"}},
            right: {style: "thin", color: {rgb: "000000"}}
        }
    };
    var dataStyle = {
        border: {
            top: {style: "thin", color: {rgb: "000000"}},
            bottom: {style: "thin", color: {rgb: "000000"}},
            left: {style: "thin", color: {rgb: "000000"}},
            right: {style: "thin", color: {rgb: "000000"}}
        },
        font: {sz: 10}
    };
    var dataStyleRight = {
        border: {
            top: {style: "thin", color: {rgb: "000000"}},
            bottom: {style: "thin", color: {rgb: "000000"}},
            left: {style: "thin", color: {rgb: "000000"}},
            right: {style: "thin", color: {rgb: "000000"}}
        },
        font: {sz: 10},
        alignment: {horizontal: "right"}
    };
    var totalStyleRight = {
        fill: {fgColor: {rgb: "FFFF00"}},
        font: {bold: true, sz: 10},
        alignment: {horizontal: "right"},
        border: {
            top: {style: "thin", color: {rgb: "000000"}},
            bottom: {style: "thin", color: {rgb: "000000"}},
            left: {style: "thin", color: {rgb: "000000"}},
            right: {style: "thin", color: {rgb: "000000"}}
        }
    };
    
    // Numeric columns (POKOK to DITERIMA) = index 5 to 16
    var numericCols = [5,6,7,8,9,10,11,12,13,14,15,16];
    
    // Force range to cover all 19 columns (NO through KETERANGAN)
    if (range.e.c < 18) range.e.c = 18;
    ws['!ref'] = XLSX.utils.encode_range(range);
    
    // Apply styles - force create ALL cells with borders
    for (var r = range.s.r; r <= range.e.r; r++) {
        for (var c = range.s.c; c <= range.e.c; c++) {
            var addr = XLSX.utils.encode_cell({r: r, c: c});
            var isNumCol = numericCols.indexOf(c) !== -1;
            
            // Determine style for this cell
            var style;
            if (r <= 1) {
                style = headerStyle;
            } else if (r === range.e.r) {
                style = isNumCol ? totalStyleRight : totalStyle;
            } else {
                style = isNumCol ? dataStyleRight : dataStyle;
            }
            
            if (ws[addr]) {
                // Cell exists - apply style
                ws[addr].s = style;
                // If cell value is empty/null, fill with space so border shows
                if (ws[addr].v === undefined || ws[addr].v === null || ws[addr].v === '') {
                    ws[addr].v = ' ';
                    ws[addr].t = 's';
                }
                // Force NIK column (B) as text
                if (c === 1 && ws[addr].v !== undefined && ws[addr].v !== null) {
                    ws[addr].t = 's';
                    ws[addr].v = String(ws[addr].v);
                    delete ws[addr].w;
                }
            } else {
                // Cell doesn't exist - create with space and style
                ws[addr] = {t: 's', v: ' ', s: style};
            }
        }
    }
    
    // Column widths
    ws['!cols'] = [
        {wch: 4}, {wch: 18}, {wch: 20}, {wch: 20}, {wch: 10},
        {wch: 14}, {wch: 12}, {wch: 12}, {wch: 12}, {wch: 10},
        {wch: 14}, {wch: 12}, {wch: 12}, {wch: 12}, {wch: 12},
        {wch: 12}, {wch: 20}, {wch: 30}, {wch: 20}
    ];
    
    // Filename
    var now = new Date();
    var d = now.getFullYear() + 
        String(now.getMonth()+1).padStart(2,'0') + 
        String(now.getDate()).padStart(2,'0') + '_' +
        String(now.getHours()).padStart(2,'0') +
        String(now.getMinutes()).padStart(2,'0') +
        String(now.getSeconds()).padStart(2,'0');
    
    XLSX.writeFile(wb, 'Laporan_Penggajian_' + d + '.xlsx');
}
</script>

</body>
</html>
