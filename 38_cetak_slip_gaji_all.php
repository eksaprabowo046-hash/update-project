<?php
session_start();
include "dbase.php";
include "islogin.php";
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['DEFAULT_IDUSER'])) die('Session tidak valid');
$periode = trim($_GET['periode'] ?? '');
if (empty($periode) || !preg_match('/^\d{4}-\d{2}$/', $periode)) die('Periode tidak valid');

// Query semua data gaji untuk periode ini
$q = $conn->prepare("
    SELECT g.*, ru.nama AS nama_pegawai, ru.kodjab, ru.nik, ru.bank
    FROM tgaji g
    LEFT JOIN ruser ru ON g.iduser_pegawai = ru.iduser
    WHERE g.periode = :periode
    ORDER BY ru.kodjab ASC, ru.nama ASC
");
$q->execute([':periode' => $periode]);
$allData = $q->fetchAll(PDO::FETCH_ASSOC);

if (count($allData) == 0) die('Tidak ada data gaji untuk periode ini');

// Mapping jabatan
$jabatanMap = [];
try {
    $stmtJab = $conn->query("SELECT kodjab, nama_jabatan FROM tbl_jabatan");
    while ($rj = $stmtJab->fetch(PDO::FETCH_ASSOC)) {
        $jabatanMap[$rj['kodjab']] = $rj['nama_jabatan'];
    }
} catch (PDOException $e) {
    $jabatanMap = [1 => 'Direktur', 2 => 'Manager', 3 => 'Staff'];
}

$bulanArr = [
    '01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April',
    '05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus',
    '09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'
];
$bulanShort = ['01'=>'Jan','02'=>'Feb','03'=>'Mar','04'=>'Apr','05'=>'Mei','06'=>'Jun','07'=>'Jul','08'=>'Ags','09'=>'Sep','10'=>'Okt','11'=>'Nov','12'=>'Des'];

$pecah = explode('-', $periode);
$periode_display = ($bulanArr[$pecah[1]] ?? $pecah[1]) . ' ' . $pecah[0];
$periodeFile = ($bulanShort[$pecah[1]] ?? $pecah[1]) . $pecah[0];

function rp_all($n) { return number_format($n, 0, ',', '.'); }

// Prepare all slip data
$slips = [];
foreach ($allData as $data) {
    // Query lembur dates
    $lembur_dates = [];
    try {
        $ql = $conn->prepare("
            SELECT DISTINCT l.tgl_lembur
            FROM tdtllembur dl
            JOIN tlembur l ON dl.idlembur = l.id
            WHERE dl.iduser_pegawai = :iduser 
              AND l.status_approval = 'Approved'
              AND DATE_FORMAT(l.tgl_lembur, '%Y-%m') = :periode
            ORDER BY l.tgl_lembur ASC
        ");
        $ql->execute([':iduser' => $data['iduser_pegawai'], ':periode' => $data['periode']]);
        while ($rl = $ql->fetch(PDO::FETCH_ASSOC)) {
            $lembur_dates[] = date('d/m', strtotime($rl['tgl_lembur']));
        }
    } catch (Exception $e) {}

    // Query pinjaman info
    $pinjaman_info = '';
    try {
        $qpin = $conn->prepare("
            SELECT nominal, tenor, cicilan_perbulan, tgl_pengajuan
            FROM tpinjaman
            WHERE iduser_pemohon = :iduser 
              AND status_approval = 'Approved' 
              AND status_lunas = 'Belum'
              AND DATE_FORMAT(tgl_pengajuan, '%Y-%m') <= :periode
            ORDER BY tgl_pengajuan ASC
        ");
        $qpin->execute([':iduser' => $data['iduser_pegawai'], ':periode' => $data['periode']]);
        $pinjaman_list = $qpin->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($pinjaman_list) > 0) {
            $parts = [];
            $prd = explode('-', $data['periode']);
            $prd_year = intval($prd[0]);
            $prd_month = intval($prd[1]);
            
            foreach ($pinjaman_list as $pin) {
                $tenor_total = intval($pin['tenor']);
                $loan_year = intval(date('Y', strtotime($pin['tgl_pengajuan'])));
                $loan_month = intval(date('m', strtotime($pin['tgl_pengajuan'])));
                $cicilan_ke = ($prd_year - $loan_year) * 12 + ($prd_month - $loan_month) + 1;
                if ($cicilan_ke < 1) $cicilan_ke = 1;
                if ($cicilan_ke > $tenor_total) $cicilan_ke = $tenor_total;
                $parts[] = 'Cicilan ke ' . $cicilan_ke . ' dari ' . $tenor_total . ' (Rp ' . number_format($pin['nominal'], 0, ',', '.') . ')';
            }
            $pinjaman_info = implode('<br>', $parts);
        }
    } catch (Exception $e) {}

    // Calculate
    $gaji_pokok     = floatval($data['gaji_pokok']);
    $tunj_jabatan   = floatval($data['tunj_jabatan']);
    $tunj_perjalanan= floatval($data['tunj_perjalanan']);
    $lembur_val     = floatval($data['lembur']);
    $bonus_val      = floatval($data['bonus']);
    $gaji_kotor     = $gaji_pokok + $tunj_jabatan + $tunj_perjalanan + $lembur_val + $bonus_val;

    $bpjs_kes       = floatval($data['bpjs_kesehatan']);
    $bpjs_tk        = floatval($data['bpjs_tk']);
    $pot_pinjaman   = floatval($data['pot_pinjaman']);
    $pot_lain       = floatval($data['pot_lain']);
    $total_potongan = $bpjs_kes + $bpjs_tk + $pot_pinjaman + $pot_lain;
    $diterima       = $gaji_kotor - $total_potongan;

    $jabatan = $jabatanMap[$data['kodjab'] ?? 0] ?? '-';
    $namaFile = date('Ymd') . "_SlipGaji_" . $periodeFile . "_" . str_replace(' ', '_', $data['nama_pegawai']) . ".png";

    $slips[] = [
        'data' => $data,
        'lembur_dates' => $lembur_dates,
        'pinjaman_info' => $pinjaman_info,
        'gaji_pokok' => $gaji_pokok,
        'tunj_jabatan' => $tunj_jabatan,
        'tunj_perjalanan' => $tunj_perjalanan,
        'lembur_val' => $lembur_val,
        'bonus_val' => $bonus_val,
        'gaji_kotor' => $gaji_kotor,
        'bpjs_kes' => $bpjs_kes,
        'bpjs_tk' => $bpjs_tk,
        'pot_pinjaman' => $pot_pinjaman,
        'pot_lain' => $pot_lain,
        'total_potongan' => $total_potongan,
        'diterima' => $diterima,
        'jabatan' => $jabatan,
        'namaFile' => $namaFile,
    ];
}

$tglCetak = date('d/m/Y H:i');
$zipFileName = date('Ymd') . "_SlipGaji_" . $periodeFile . "_ALL.zip";
$totalSlips = count($slips);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak All Slip Gaji - <?= $periode_display ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            margin: 0;
            padding: 20px;
        }

        /* Progress overlay */
        #progress-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.7);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .progress-box {
            background: #fff;
            border-radius: 12px;
            padding: 40px 50px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            min-width: 400px;
        }
        .progress-box h3 {
            margin-bottom: 20px;
            color: #333;
        }
        .progress-bar-container {
            width: 100%;
            height: 24px;
            background: #e0e0e0;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 15px;
        }
        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #4CAF50, #45a049);
            border-radius: 12px;
            transition: width 0.3s ease;
            width: 0%;
        }
        .progress-text {
            font-size: 14px;
            color: #666;
        }
        .progress-done {
            color: #4CAF50;
            font-weight: bold;
            font-size: 16px;
        }

        /* === SLIP === */
        .slip-gaji {
            width: 500px;
            background: #fff;
            padding: 28px 30px;
            border: 1px solid #ccc;
            margin: 0 auto 30px;
        }

        .slip-header {
            text-align: center;
            padding-bottom: 12px;
            border-bottom: 2px solid #333;
            margin-bottom: 14px;
        }
        .slip-header .company { font-size: 15px; font-weight: bold; }
        .slip-header .title { font-size: 13px; margin-top: 2px; }
        .slip-header .periode { font-size: 12px; margin-top: 4px; color: #555; }

        .slip-info {
            display: flex;
            justify-content: space-between;
            padding-bottom: 10px;
            border-bottom: 1px solid #333;
            margin-bottom: 6px;
        }
        .slip-info .left { font-size: 12px; font-weight: bold; color: #c00; }
        .slip-info .right { font-size: 12px; font-weight: bold; color: #c00; text-align: right; }

        table.slip {
            width: 100%;
            border-collapse: collapse;
        }
        table.slip td {
            padding: 4px 0;
            font-size: 12px;
            vertical-align: top;
        }
        table.slip .col-label { }
        table.slip .col-sign { width: 20px; text-align: center; }
        table.slip .col-rp { width: 25px; text-align: right; }
        table.slip .col-amount { width: 100px; text-align: right; }

        .row-subtotal {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 12px;
            font-weight: bold;
            color: #c00;
            border-top: 1px solid #333;
            border-bottom: 1px solid #333;
            margin: 4px 0;
        }

        .row-diterima {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 13px;
            font-weight: bold;
            color: #c00;
            border-top: 2px solid #333;
            border-bottom: 2px solid #333;
            margin-top: 4px;
        }

        .slip-keterangan {
            padding-top: 6px;
            font-size: 11px;
            color: #333;
        }

        .slip-footer {
            margin-top: 16px;
            padding-top: 10px;
            border-top: 1px solid #ccc;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            font-size: 10px;
            color: #666;
        }
        .slip-footer .ttd {
            text-align: center;
        }
        .slip-footer .ttd-line {
            width: 110px;
            border-bottom: 1px solid #999;
            height: 35px;
            margin-bottom: 2px;
        }
    </style>
</head>
<body>

<!-- Progress overlay -->
<div id="progress-overlay">
    <div class="progress-box">
        <h3><i class="fa fa-cog fa-spin"></i> Generating Slip Gaji...</h3>
        <p style="margin-bottom:15px; color:#888;">Periode: <?= $periode_display ?> (<?= $totalSlips ?> pegawai)</p>
        <div class="progress-bar-container">
            <div class="progress-bar-fill" id="progressBar"></div>
        </div>
        <div class="progress-text" id="progressText">Mempersiapkan...</div>
    </div>
</div>

<!-- Render all slips (hidden during capture) -->
<?php foreach ($slips as $idx => $s): 
    $d = $s['data'];
    $nik = htmlspecialchars($d['nik'] ?? '-');
    $bank = htmlspecialchars($d['bank'] ?? '-');
    $keterangan = htmlspecialchars($d['keterangan'] ?? '');
?>
<div class="slip-gaji" id="slip-<?= $idx ?>" data-filename="<?= htmlspecialchars($s['namaFile']) ?>">

    <!-- HEADER -->
    <div class="slip-header">
        <div class="company">PT DUTA SOLUSI INFORMATIKA</div>
        <div class="title">SLIP GAJI KARYAWAN</div>
        <div class="periode">Periode: <?= $periode_display ?></div>
    </div>

    <!-- INFO -->
    <div class="slip-info">
        <div class="left"><?= htmlspecialchars($d['nama_pegawai']) ?></div>
        <div class="right">NoRek: <?= $bank ?></div>
    </div>

    <!-- PENDAPATAN -->
    <table class="slip">
        <tr>
            <td class="col-label">Gaji Pokok</td>
            <td class="col-sign">+</td>
            <td class="col-rp">Rp</td>
            <td class="col-amount"><?= rp_all($s['gaji_pokok']) ?></td>
        </tr>
        <tr>
            <td class="col-label">Tunjangan Jabatan</td>
            <td class="col-sign">+</td>
            <td class="col-rp">Rp</td>
            <td class="col-amount"><?= rp_all($s['tunj_jabatan']) ?></td>
        </tr>
        <tr>
            <td class="col-label">Tunjangan Perj Dinas</td>
            <td class="col-sign">+</td>
            <td class="col-rp">Rp</td>
            <td class="col-amount"><?= rp_all($s['tunj_perjalanan']) ?></td>
        </tr>
        <tr>
            <td class="col-label">
                Lembur
                <?php if (!empty($s['lembur_dates'])) { ?>
                    <br><small style="color:#666; font-size:10px;">(<?= implode(', ', $s['lembur_dates']) ?>)</small>
                <?php } ?>
            </td>
            <td class="col-sign">+</td>
            <td class="col-rp">Rp</td>
            <td class="col-amount"><?= rp_all($s['lembur_val']) ?></td>
        </tr>
        <tr>
            <td class="col-label">Bonus</td>
            <td class="col-sign">+</td>
            <td class="col-rp">Rp</td>
            <td class="col-amount"><?= rp_all($s['bonus_val']) ?></td>
        </tr>
    </table>

    <div class="row-subtotal">
        <span>Gaji Kotor</span>
        <span><?= rp_all($s['gaji_kotor']) ?></span>
    </div>

    <!-- POTONGAN -->
    <table class="slip">
        <tr>
            <td class="col-label">Potongan BPJS Kes</td>
            <td class="col-sign">-</td>
            <td class="col-rp">Rp</td>
            <td class="col-amount"><?= rp_all($s['bpjs_kes']) ?></td>
        </tr>
        <tr>
            <td class="col-label">Potongan BPJS TK</td>
            <td class="col-sign">-</td>
            <td class="col-rp">Rp</td>
            <td class="col-amount"><?= rp_all($s['bpjs_tk']) ?></td>
        </tr>
        <tr>
            <td class="col-label">
                Pinjaman
                <?php if (!empty($s['pinjaman_info'])) { ?>
                    <br><small style="color:#666; font-size:10px;">(<?= $s['pinjaman_info'] ?>)</small>
                <?php } ?>
            </td>
            <td class="col-sign">-</td>
            <td class="col-rp">Rp</td>
            <td class="col-amount"><?= rp_all($s['pot_pinjaman']) ?></td>
        </tr>
        <tr>
            <td class="col-label">Potongan Lainnya</td>
            <td class="col-sign">-</td>
            <td class="col-rp">Rp</td>
            <td class="col-amount"><?= rp_all($s['pot_lain']) ?></td>
        </tr>
    </table>

    <!-- DITERIMA -->
    <div class="row-diterima">
        <span>Diterima</span>
        <span>Rp &nbsp; <?= rp_all($s['diterima']) ?></span>
    </div>

    <!-- KETERANGAN -->
    <div class="slip-keterangan">Keterangan: <?= $keterangan ?: '-' ?></div>

    <!-- FOOTER -->
    <div class="slip-footer">
        <div>PT Duta Solusi Informatika &copy; <?= date('Y') ?></div>
        <div>Dicetak: <?= $tglCetak ?></div>
    </div>

</div>
<?php endforeach; ?>

<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jszip@3.10.1/dist/jszip.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/file-saver@2.0.5/dist/FileSaver.min.js"></script>
<script>
(function() {
    var totalSlips = <?= $totalSlips ?>;
    var zipFileName = <?= json_encode($zipFileName) ?>;
    var progressBar = document.getElementById('progressBar');
    var progressText = document.getElementById('progressText');

    function updateProgress(current, total, msg) {
        var pct = Math.round((current / total) * 100);
        progressBar.style.width = pct + '%';
        progressText.textContent = msg || ('Memproses ' + current + ' dari ' + total + '...');
    }

    // Wait for fonts/images to load
    setTimeout(function() {
        var zip = new JSZip();
        var slipElements = document.querySelectorAll('.slip-gaji');
        var processed = 0;

        function captureNext(index) {
            if (index >= slipElements.length) {
                // All done — generate ZIP
                updateProgress(totalSlips, totalSlips, 'Membuat file ZIP...');
                progressBar.style.background = 'linear-gradient(90deg, #2196F3, #1976D2)';

                zip.generateAsync({ type: 'blob' }).then(function(content) {
                    saveAs(content, zipFileName);
                    progressText.innerHTML = '<span class="progress-done">✅ Download selesai! (' + totalSlips + ' slip gaji)</span>';
                    progressBar.style.width = '100%';
                    progressBar.style.background = 'linear-gradient(90deg, #4CAF50, #45a049)';
                    
                    // Auto close after 3 seconds
                    setTimeout(function() {
                        window.close();
                    }, 3000);
                });
                return;
            }

            var el = slipElements[index];
            var filename = el.getAttribute('data-filename');
            updateProgress(index + 1, totalSlips, 'Memproses slip ' + (index + 1) + ' dari ' + totalSlips + '...');

            html2canvas(el, {
                scale: 2,
                backgroundColor: '#ffffff',
                useCORS: true
            }).then(function(canvas) {
                canvas.toBlob(function(blob) {
                    zip.file(filename, blob);
                    processed++;
                    // Process next with small delay to avoid freezing
                    setTimeout(function() {
                        captureNext(index + 1);
                    }, 50);
                }, 'image/png');
            }).catch(function(err) {
                console.error('Error capturing slip ' + index + ':', err);
                // Skip and continue
                setTimeout(function() {
                    captureNext(index + 1);
                }, 50);
            });
        }

        captureNext(0);
    }, 500);
})();
</script>

</body>
</html>
