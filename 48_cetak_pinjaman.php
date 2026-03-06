<?php
session_start();
include "dbase.php";
include "islogin.php";

require_once 'vendor/autoload.php';

use PhpOffice\PhpWord\TemplateProcessor;

if (!isset($_SESSION['DEFAULT_IDUSER'])) die('Session tidak valid');
$id = intval($_GET['id'] ?? 0);
if ($id <= 0) die('ID tidak valid');

// Helper: format tanggal Indonesia
function tanggalIndoPinjaman($tanggal) {
    $bulan = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $pecah = explode('-', date('Y-m-d', strtotime($tanggal)));
    return $pecah[2] . ' ' . $bulan[(int)$pecah[1]] . ' ' . $pecah[0];
}

function bulanIndoPinjaman($m) {
    $bulan = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    return $bulan[(int)$m] ?? '';
}

// Ambil data pinjaman
$q = $conn->prepare("SELECT p.*, u.nama AS nama_pemohon 
                      FROM tpinjaman p 
                      LEFT JOIN ruser u ON p.iduser_pemohon = u.iduser 
                      WHERE p.id = :id");
$q->execute([':id' => $id]);
$data = $q->fetch(PDO::FETCH_ASSOC);
if (!$data) die('Data tidak ditemukan');

// Hitung waktu tempo angsuran
$tglPengajuan = new DateTime($data['tgl_pengajuan']);
$tglSelesai = clone $tglPengajuan;
$tglSelesai->modify('+' . $data['tenor'] . ' months');
$waktuTempo = bulanIndoPinjaman($tglPengajuan->format('n')) . ' ' . $tglPengajuan->format('Y') 
    . ' – ' . bulanIndoPinjaman($tglSelesai->format('n')) . ' ' . $tglSelesai->format('Y');

// Load template
$templatePath = __DIR__ . '/48_template_pinjaman.docx';
if (!file_exists($templatePath)) {
    die('Template tidak ditemukan. Jalankan 48_generate_template_pinjaman.php terlebih dahulu.');
}

$template = new TemplateProcessor($templatePath);

// Set values
$template->setValue('nama', $data['nama_pemohon'] ?? '-');
$template->setValue('alamat', $data['alamat'] ?? '-');
$template->setValue('jabatan', $data['jabatan_pemohon'] ?? '-');
$template->setValue('no_telp', $data['no_telp'] ?? '-');
$template->setValue('nominal', 'Rp. ' . number_format($data['nominal'], 0, ',', '.') . ',-');
$template->setValue('rencana_bayar', $data['tenor'] . 'x angsur (potong gaji)');
$template->setValue('angsuran', 'Rp. ' . number_format($data['cicilan_perbulan'], 0, ',', '.') . ',-');
$template->setValue('waktu_tempo', $waktuTempo);
$template->setValue('tanggal_pengajuan', tanggalIndoPinjaman($data['tgl_pengajuan']));
$template->setValue('nama_direktur', 'Brojo Pramodo Laksono');

// Output file
ob_clean();
$namaPemohon = preg_replace('/[^a-zA-Z0-9 ]/', '', $data['nama_pemohon']);
$filename = date('Ymd', strtotime($data['tgl_pengajuan'])) . '_Pengajuan_Pinjaman_' . str_replace(' ', '_', trim($namaPemohon)) . '.docx';

header("Content-Description: File Transfer");
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Cache-Control: must-revalidate');
header('Pragma: public');

$template->saveAs("php://output");
exit;
?>
