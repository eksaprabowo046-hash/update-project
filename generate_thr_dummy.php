<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'dbase.php';

// Dapatkan 3 pegawai yang aktif
$q = $conn->query("SELECT iduser, nama, tgl_masuk FROM ruser WHERE stsaktif = 1 LIMIT 3");
$users = $q->fetchAll(PDO::FETCH_ASSOC);

if (count($users) == 0) {
    die("Tidak ada data user di tabel ruser.");
}

// Check if filter exists
$filter_bulan = isset($_GET['filter_bulan']) ? $_GET['filter_bulan'] : date('m');
$filter_tahun = isset($_GET['filter_tahun']) ? $_GET['filter_tahun'] : date('Y');
$periode = $filter_tahun . '-' . str_pad($filter_bulan, 2, '0', STR_PAD_LEFT);

echo "Periode Target: " . $periode . "\n";

foreach ($users as $index => $u) {
    if ($index > 3) break;
    $iduser = $u['iduser'];
    $gaji_pokok = 5000000 + ($index * 1000000); // Variasi gaji pokok
    $tunj_jabatan = 1500000;
    $lembur = 500000;
    
    // Variasi THR untuk melihat hasil:
    if ($index == 0) {
        $thr = $gaji_pokok + $tunj_jabatan;
        $ket = "Data dummy (Simulasi THR Penuh)";
    } elseif ($index == 1) {
        $thr = ($gaji_pokok + $tunj_jabatan) / 2;
        $ket = "Data dummy (Simulasi THR Proporsional 6 bln)";
    } else {
        $thr = 0;
        $ket = "Data dummy (Belum dapat THR)";
    }

    $qdel = $conn->prepare("DELETE FROM tgaji WHERE iduser_pegawai = ? AND periode = ?");
    $qdel->execute([$iduser, $periode]);
    
    $ins = $conn->prepare("
        INSERT INTO tgaji (
            iduser_pegawai, periode, gaji_pokok, tunj_jabatan, 
            tunj_perjalanan, lembur, bonus, thr, status_gaji,
            bpjs_kesehatan, bpjs_tk, pot_pinjaman, pot_lain,
            keterangan
        ) VALUES (
            ?, ?, ?, ?, 
            0, ?, 0, ?, 'Generated',
            150000, 100000, 0, 0,
            ?
        )
    ");
    
    try {
        if ($ins->execute([$iduser, $periode, $gaji_pokok, $tunj_jabatan, $lembur, $thr, $ket])) {
            echo "- Berhasil buat slip untuk: " . htmlspecialchars($u['nama']) . " (THR: Rp " . number_format($thr,0,',','.') . ")\n";
        } else {
            echo "- Gagal buat slip untuk: " . htmlspecialchars($u['nama']) . "\n";
            print_r($ins->errorInfo());
        }
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
?>
