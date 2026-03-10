<?php
include 'dbase.php';

try {
    // 1. Tambah 2 Pegawai Dummy jika belum ada
    $dummies = [
        ['id' => 'budi', 'nama' => 'Budi Santoso'],
        ['id' => 'siska', 'nama' => 'Siska Putri']
    ];
    
    foreach ($dummies as $d) {
        $cek = $conn->prepare("SELECT iduser FROM ruser WHERE iduser = ?");
        $cek->execute([$d['id']]);
        if (!$cek->fetch()) {
            $conn->prepare("INSERT INTO ruser (iduser, nama, passwd, kodjab, stsaktif) VALUES (?, ?, '123', 3, 1)")
                 ->execute([$d['id'], $d['nama']]);
        }
    }
    
    $periode = '2026-03';
    // 2. Bersihkan data lama Maret 2026
    $conn->exec("DELETE FROM tgaji WHERE periode = '$periode'");
    
    // 3. Masukkan data gaji untuk 3 orang (admin, budi, siska)
    $users = $conn->query("SELECT iduser, nama FROM ruser WHERE stsaktif = 1 LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($users as $u) {
        $gp = 4000000 + (rand(0, 10) * 100000);
        $tj = 500000;
        $tp = 200000;
        $lm = 150000;
        $bo = 100000;
        $bt = 100000;
        $bk = 150000;
        $pp = 0;
        $pl = 0;
        $tt = ($gp + $tj + $tp + $lm + $bo) - ($bt + $bk + $pp + $pl);
        
        $conn->prepare("
            INSERT INTO tgaji (
                iduser_pegawai, periode, gaji_pokok, tunj_jabatan, tunj_perjalanan, 
                lembur, bonus, bpjs_tk, bpjs_kesehatan, pot_pinjaman, pot_lain, 
                total_terima, keterangan, status_gaji, tgl_input
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Hasil Simulasi Impor', 'Imported', NOW()
            )
        ")->execute([$u['iduser'], $periode, $gp, $tj, $tp, $lm, $bo, $bt, $bk, $pp, $pl, $tt]);
    }
    
    echo "BERHASIL: 2 Pegawai baru ditambahkan dan 3 data gaji Maret 2026 telah dimasukkan.";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
?>
