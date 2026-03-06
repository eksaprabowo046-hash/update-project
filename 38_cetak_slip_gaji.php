<?php
session_start();
include "dbase.php";
include "islogin.php";
date_default_timezone_set('Asia/Jakarta');

if (!isset($_SESSION['DEFAULT_IDUSER'])) die('Session tidak valid');
$id = intval($_GET['id'] ?? 0);
if ($id <= 0) die('ID tidak valid');

// Ambil data gaji + NIK + Bank
$q = $conn->prepare("
    SELECT g.*, ru.nama AS nama_pegawai, ru.kodjab, ru.nik, ru.bank
    FROM tgaji g
    LEFT JOIN ruser ru ON g.iduser_pegawai = ru.iduser
    WHERE g.id = :id
");
$q->execute([':id' => $id]);
$data = $q->fetch(PDO::FETCH_ASSOC);
if (!$data) die('Data gaji tidak ditemukan');

// Query tanggal-tanggal lembur approved untuk periode ini
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

// Query detail pinjaman (aktif atau lunas dalam tenor) untuk label cicilan
$pinjaman_info = '';
try {
    // Parse periode gaji
    $prd = explode('-', $data['periode']);
    $prd_year  = intval($prd[0]);
    $prd_month = intval($prd[1]);

    // Ambil pinjaman yang aktif ATAU sudah lunas tapi periodenya masih dalam tenor
    $qpin = $conn->prepare("
        SELECT nominal, tenor, cicilan_perbulan, periode_awal, tgl_pengajuan
        FROM tpinjaman
        WHERE iduser_pemohon = :iduser 
          AND status_approval = 'Approved' 
          AND (
            -- Pinjaman aktif: periode_awal <= periode gaji
            ( (status_lunas = 'Belum' OR status_lunas = '' OR status_lunas IS NULL)
              AND (periode_awal IS NULL OR periode_awal <= :periode) )
            OR
            -- Pinjaman lunas: periode gaji masih dalam rentang tenor
            ( status_lunas = 'Lunas'
              AND periode_awal IS NOT NULL AND periode_akhir IS NOT NULL
              AND periode_awal <= :periode2 AND periode_akhir >= :periode3 )
          )
        ORDER BY COALESCE(periode_awal, DATE_FORMAT(tgl_pengajuan,'%Y-%m')) ASC
    ");
    $qpin->execute([
        ':iduser'  => $data['iduser_pegawai'],
        ':periode' => $data['periode'],
        ':periode2'=> $data['periode'],
        ':periode3'=> $data['periode'],
    ]);
    $pinjaman_list = $qpin->fetchAll(PDO::FETCH_ASSOC);

    if (count($pinjaman_list) > 0) {
        $parts = [];
        foreach ($pinjaman_list as $pin) {
            $tenor_total = intval($pin['tenor']);

            // Hitung cicilan ke berapa berdasarkan selisih dari periode_awal
            // Fallback ke tgl_pengajuan jika periode_awal belum diisi
            $ref_date = !empty($pin['periode_awal']) ? $pin['periode_awal'] : date('Y-m', strtotime($pin['tgl_pengajuan']));
            $ref_parts   = explode('-', $ref_date);
            $ref_year    = intval($ref_parts[0]);
            $ref_month   = intval($ref_parts[1]);
            $cicilan_ke  = ($prd_year - $ref_year) * 12 + ($prd_month - $ref_month) + 1;

            if ($cicilan_ke < 1) $cicilan_ke = 1;
            if ($cicilan_ke > $tenor_total) $cicilan_ke = $tenor_total;

            $parts[] = 'Cicilan ke ' . $cicilan_ke . ' dari ' . $tenor_total . ' (Rp ' . number_format($pin['nominal'], 0, ',', '.') . ')';
        }
        $pinjaman_info = implode('<br>', $parts);
    }
} catch (Exception $e) {}

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
$jabatan = $jabatanMap[$data['kodjab'] ?? 0] ?? '-';
$bulanArr = [
    '01'=>'Januari','02'=>'Februari','03'=>'Maret','04'=>'April',
    '05'=>'Mei','06'=>'Juni','07'=>'Juli','08'=>'Agustus',
    '09'=>'September','10'=>'Oktober','11'=>'November','12'=>'Desember'
];
$pecah = explode('-', $data['periode']);
$periode_display = ($bulanArr[$pecah[1]] ?? $pecah[1]) . ' ' . $pecah[0];

// Hitung komponen
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

function rp($n) { return number_format($n, 0, ',', '.'); }

$bulanShort = ['01'=>'Jan','02'=>'Feb','03'=>'Mar','04'=>'Apr','05'=>'Mei','06'=>'Jun','07'=>'Jul','08'=>'Ags','09'=>'Sep','10'=>'Okt','11'=>'Nov','12'=>'Des'];
$periodeFile = ($bulanShort[$pecah[1]] ?? $pecah[1]) . $pecah[0];
$namaFile = date('Ymd') . "_SlipGaji_" . $periodeFile . "_" . str_replace(' ', '_', $data['nama_pegawai']) . ".png";
$nik = htmlspecialchars($data['nik'] ?? '-');
$bank = htmlspecialchars($data['bank'] ?? '-');
$keterangan = htmlspecialchars($data['keterangan'] ?? '');
$tglCetak = date('d/m/Y H:i');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Slip Gaji - <?= htmlspecialchars($data['nama_pegawai']) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background: #fff;
            margin: 0;
            padding: 0;
        }


        /* === SLIP === */
        #slip-gaji {
            width: 500px;
            background: #fff;
            padding: 28px 30px;
            border: 1px solid #ccc;
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



<div id="slip-gaji">

    <!-- HEADER -->
    <div class="slip-header">
        <div class="company">PT DUTA SOLUSI INFORMATIKA</div>
        <div class="title">SLIP GAJI KARYAWAN</div>
        <div class="periode">Periode: <?= $periode_display ?></div>
    </div>

    <!-- INFO -->
    <div class="slip-info">
        <div class="left"><?= htmlspecialchars($data['nama_pegawai']) ?></div>
        <div class="right">NoRek: <?= $bank ?></div>
    </div>

    <!-- PENDAPATAN -->
    <table class="slip">
        <tr>
            <td class="col-label">Gaji Pokok</td>
            <td class="col-sign">+</td>
            <td class="col-rp">Rp</td>
            <td class="col-amount"><?= rp($gaji_pokok) ?></td>
        </tr>
        <tr>
            <td class="col-label">Tunjangan Jabatan</td>
            <td class="col-sign">+</td>
            <td class="col-rp">Rp</td>
            <td class="col-amount"><?= rp($tunj_jabatan) ?></td>
        </tr>
        <tr>
            <td class="col-label">Tunjangan Perj Dinas</td>
            <td class="col-sign">+</td>
            <td class="col-rp">Rp</td>
            <td class="col-amount"><?= rp($tunj_perjalanan) ?></td>
        </tr>
        <tr>
            <td class="col-label">
                Lembur
                <?php if (!empty($lembur_dates)) { ?>
                    <br><small style="color:#666; font-size:10px;">(<?= implode(', ', $lembur_dates) ?>)</small>
                <?php } ?>
            </td>
            <td class="col-sign">+</td>
            <td class="col-rp">Rp</td>
            <td class="col-amount"><?= rp($lembur_val) ?></td>
        </tr>
        <tr>
            <td class="col-label">Bonus</td>
            <td class="col-sign">+</td>
            <td class="col-rp">Rp</td>
            <td class="col-amount"><?= rp($bonus_val) ?></td>
        </tr>
    </table>

    <div class="row-subtotal">
        <span>Gaji Kotor</span>
        <span><?= rp($gaji_kotor) ?></span>
    </div>

    <!-- POTONGAN -->
    <table class="slip">
        <tr>
            <td class="col-label">Potongan BPJS Kes</td>
            <td class="col-sign">-</td>
            <td class="col-rp">Rp</td>
            <td class="col-amount"><?= rp($bpjs_kes) ?></td>
        </tr>
        <tr>
            <td class="col-label">Potongan BPJS TK</td>
            <td class="col-sign">-</td>
            <td class="col-rp">Rp</td>
            <td class="col-amount"><?= rp($bpjs_tk) ?></td>
        </tr>
        <tr>
            <td class="col-label">
                Pinjaman
                <?php if (!empty($pinjaman_info)) { ?>
                    <br><small style="color:#666; font-size:10px;">(<?= $pinjaman_info ?>)</small>
                <?php } ?>
            </td>
            <td class="col-sign">-</td>
            <td class="col-rp">Rp</td>
            <td class="col-amount"><?= rp($pot_pinjaman) ?></td>
        </tr>
        <tr>
            <td class="col-label">Potongan Lainnya</td>
            <td class="col-sign">-</td>
            <td class="col-rp">Rp</td>
            <td class="col-amount"><?= rp($pot_lain) ?></td>
        </tr>
    </table>

    <!-- DITERIMA -->
    <div class="row-diterima">
        <span>Diterima</span>
        <span>Rp &nbsp; <?= rp($diterima) ?></span>
    </div>

    <!-- KETERANGAN -->
    <div class="slip-keterangan">Keterangan: <?= $keterangan ?: '-' ?></div>

    <!-- FOOTER -->
    <div class="slip-footer">
        <div>PT Duta Solusi Informatika &copy; <?= date('Y') ?></div>
        <div>Dicetak: <?= $tglCetak ?></div>
    </div>

</div>

<script>
function doCapture() {
    setTimeout(function() {
        html2canvas(document.getElementById('slip-gaji'), {
            scale: 2,
            backgroundColor: '#ffffff',
            useCORS: true
        }).then(function(canvas) {
            var link = document.createElement('a');
            link.download = <?= json_encode($namaFile) ?>;
            link.href = canvas.toDataURL('image/png');
            link.click();
        });
    }, 300);
}
</script>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js" onload="doCapture()"></script>

</body>
</html>
