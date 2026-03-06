<?php
session_start();
include "dbase.php";
include "islogin.php";

require_once 'vendor/autoload.php';

use PhpOffice\PhpWord\TemplateProcessor;

if (!isset($_SESSION['DEFAULT_IDUSER'])) die('Session tidak valid');
$id = intval($_GET['id'] ?? 0);
if ($id <= 0) die('ID tidak valid');

function tanggalIndo($tanggal) {
    $bulan = [1 => 'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $pecah = explode('-', date('Y-m-d', strtotime($tanggal)));
    return $pecah[2] . ' ' . $bulan[(int)$pecah[1]] . ' ' . $pecah[0];
}

function hariIndo($tanggal) {
    $hari = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    return $hari[(int)date('w', strtotime($tanggal))];
}

$q = $conn->prepare("SELECT l.*, u.nama AS pengaju FROM tlembur l LEFT JOIN ruser u ON l.iduser_pengaju = u.iduser WHERE l.id = :id");
$q->execute([':id' => $id]);
$data = $q->fetch(PDO::FETCH_ASSOC);
if (!$data) die('Data tidak ditemukan');

$qd = $conn->prepare("SELECT d.tugas, ru.nama, j.nama_jabatan FROM tdtllembur d LEFT JOIN ruser ru ON d.iduser_pegawai = ru.iduser LEFT JOIN tbl_jabatan j ON ru.kodjab = j.kodjab WHERE d.idlembur = :id ORDER BY d.id");
$qd->execute([':id' => $id]);
$detail = $qd->fetchAll(PDO::FETCH_ASSOC);

$pegawaiUnik = array_unique(array_column($detail, 'nama'));
$daftarPegawaiNarasi = !empty($pegawaiUnik) ? implode(', ', $pegawaiUnik) : '-';

// Ambil jabatan unik dari detail
$jabatanUnik = array_unique(array_filter(array_column($detail, 'nama_jabatan')));
$jabatanLabel = !empty($jabatanUnik) ? implode(' / ', $jabatanUnik) : 'Staff';

$jumlahPegawai = count($pegawaiUnik);
$jumlahTarif   = number_format(90000 * $jumlahPegawai, 0, ',', '.');

$templatePath = __DIR__ . "/35_template_lembur.docx";
$template = new TemplateProcessor($templatePath);

// Set Data Header Standard
$template->setValue('judul', $data['latarbelakang']);
$template->setValue('latar_belakang', str_replace("\n", "<w:br/>", $data['latarbelakang']));
$template->setValue('hari', hariIndo($data['tgl_lembur']));
$template->setValue('tanggal_lembur', tanggalIndo($data['tgl_lembur']));
$template->setValue('waktu_lembur', $data['jam_mulai']);
$template->setValue('jabatan_pegawai', $jabatanLabel);
$template->setValue('daftar_pegawai1', $daftarPegawaiNarasi);
$template->setValue('jumlah_pegawai', $jumlahPegawai);
$template->setValue('jumlah_tarif', $jumlahTarif);
$template->setValue('tanggal_cetak', tanggalIndo(date('Y-m-d')));
$template->setValue('penanggung_jawab', strtoupper($data['pengaju']));

$jumlahBaris = count($detail);
if ($jumlahBaris > 0) {
    $template->cloneRow('daftar_pegawai', $jumlahBaris);
    $i = 1;
    foreach ($detail as $row) {
        $template->setValue("daftar_pegawai#{$i}", $row['nama']);
        $template->setValue("daftar_pekerjaan#{$i}", $row['tugas']);
        $i++;
    }
} else {
    $template->setValue('daftar_pegawai', '-');
    $template->setValue('daftar_pekerjaan', '-');
}

if ($jumlahBaris > 0) {
    $template->cloneRow('v_nama', $jumlahBaris);

    $j = 1;
    foreach ($detail as $row) {
        $jabatan = !empty($row['nama_jabatan']) ? $row['nama_jabatan'] : '-';
        $template->setValue("v_no#{$j}", $j);
        $template->setValue("v_nama#{$j}", strtoupper($row['nama']));
        $template->setValue("v_jabatan#{$j}", $jabatan);
        $template->setValue("v_tugas#{$j}", $row['tugas']);
        $j++;
    }
} else {
    // Jika kosong, hapus baris
    $template->setValue('v_no', '');
    $template->setValue('v_nama', '-');
    $template->setValue('v_tugas', '');
}

// Output file
ob_clean();
$kegiatan_short = mb_substr(preg_replace('/[^a-zA-Z0-9 ]/', '', $data['latarbelakang']), 0, 30);
$filename = date('Ymd', strtotime($data['tgl_lembur'])) . "_SPL_" . str_replace(' ', '_', trim($kegiatan_short)) . ".docx";

header("Content-Description: File Transfer");
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Cache-Control: must-revalidate');
header('Pragma: public');

$template->saveAs("php://output");
exit;
?>