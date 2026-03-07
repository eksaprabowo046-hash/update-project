<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'dbase.php';

// Simulate Add Pegawai
$idpegawai = 'admin'; // Administrator
$periode = '2026-03';
$gaji_pokok = 5000000;
$tunj_jabatan = 1500000;
$tunj_perjalanan = 0;
$thr = 0;

try {
    // Check if employee already exists in that period
    $qcek = $conn->prepare("SELECT id FROM tgaji WHERE iduser_pegawai = :idpegawai AND periode = :periode");
    $qcek->execute([':idpegawai' => $idpegawai, ':periode' => $periode]);
    
    if ($qcek->fetch()) {
        echo "Pegawai sudah ada di periode tersebut!\n";
    } else {
        // ... (simplified logic for testing insert)
        $total_pendapatan = $gaji_pokok + $tunj_jabatan + $tunj_perjalanan; // Removed THR from calculation
        $total_terima = $total_pendapatan; // ignoring deductions for a moment
        
        $stmt = $conn->prepare("
            INSERT INTO tgaji
            (iduser_pegawai, periode, gaji_pokok, tunj_jabatan, tunj_perjalanan, lembur, bonus,
             bpjs_tk, bpjs_kesehatan, pot_pinjaman, pot_lain, total_terima, status_gaji, tgl_input)
            VALUES
            (:idpegawai, :periode, :gaji_pokok, :tunj_jabatan, :tunj_perjalanan, 0, 0, 
             0, 0, 0, 0, :total_terima, 'Draft', NOW())
        ");
        
        $res = $stmt->execute([
            ':idpegawai' => $idpegawai,
            ':periode' => $periode,
            ':gaji_pokok' => $gaji_pokok,
            ':tunj_jabatan' => $tunj_jabatan,
            ':tunj_perjalanan' => $tunj_perjalanan,
            ':total_terima' => $total_terima
        ]);
        
        if ($res) {
            echo "Inserted successfully\n";
        } else {
            echo "Insert failed\n";
            print_r($stmt->errorInfo());
        }
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
