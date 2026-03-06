<?php
session_start();
include "dbase.php";
include "islogin.php";

require_once 'vendor/autoload.php';

use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpWord\IOFactory;

if (!isset($_SESSION['DEFAULT_IDUSER'])) die('Session tidak valid');
$id = intval($_GET['id'] ?? 0);
if ($id <= 0) die('ID tidak valid');

function tanggalIndo($tanggal) {
    $bulan = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $pecah = explode('-', date('Y-m-d', strtotime($tanggal)));
    return $pecah[2] . ' ' . $bulan[(int)$pecah[1]] . ' ' . $pecah[0];
}
function hariIndo($tanggal) {
    $hari = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    return $hari[(int)date('w', strtotime($tanggal))];
}

// Ambil data SPJ
$q = $conn->prepare("
    SELECT t.*, l.latarbelakang, l.tgl_lembur, l.jam_mulai,
           ru.nama AS pelapor
    FROM ttanggungjawab_lembur t
    LEFT JOIN tlembur l ON t.idlembur = l.id
    LEFT JOIN ruser ru ON t.iduser_pelapor = ru.iduser
    WHERE t.id = :id AND t.idtask IS NULL
");
$q->execute([':id' => $id]);
$data = $q->fetch(PDO::FETCH_ASSOC);
if (!$data) die('Data SPJ tidak ditemukan');

// Ambil semua foto dari detail rows (per task)
$qfoto = $conn->prepare("
    SELECT tl.foto, tl.kesimpulan, d.tugas
    FROM ttanggungjawab_lembur tl
    LEFT JOIN tdtllembur d ON tl.idtask = d.id
    WHERE tl.idlembur = :idlembur AND tl.idtask IS NOT NULL
    ORDER BY tl.id
");
$qfoto->execute([':idlembur' => $data['idlembur']]);
$detailRows = $qfoto->fetchAll(PDO::FETCH_ASSOC);

// Kumpulkan semua foto dari semua task
$allFotos = [];
$hasilPerTask = [];
foreach ($detailRows as $dr) {
    if (!empty($dr['foto'])) {
        $decoded = json_decode($dr['foto'], true);
        if (is_array($decoded)) {
            $allFotos = array_merge($allFotos, $decoded);
        } else {
            $allFotos[] = $dr['foto'];
        }
    }
    if (!empty($dr['tugas']) && !empty($dr['kesimpulan'])) {
        $hasilPerTask[] = ['tugas' => $dr['tugas'], 'hasil' => $dr['kesimpulan']];
    }
}

// Ambil detail pegawai & tugas
$qd = $conn->prepare("
    SELECT d.tugas, ru.nama, ru.kodjab
    FROM tdtllembur d
    LEFT JOIN ruser ru ON d.iduser_pegawai = ru.iduser
    WHERE d.idlembur = :id ORDER BY d.id
");
$qd->execute([':id' => $data['idlembur']]);
$detail = $qd->fetchAll(PDO::FETCH_ASSOC);

$jabatanMap = [];
try {
    $stmtJab = $conn->query("SELECT kodjab, nama_jabatan FROM tbl_jabatan");
    while ($rj = $stmtJab->fetch(PDO::FETCH_ASSOC)) {
        $jabatanMap[$rj['kodjab']] = $rj['nama_jabatan'];
    }
} catch (PDOException $e) {
    $jabatanMap = [1 => 'Direktur', 2 => 'Manager', 3 => 'Staff', 4 => 'Programmer'];
}

// Ambil penanggung jawab (Direktur - kodjab=1)
$qpj = $conn->prepare("SELECT nama, kodjab FROM ruser WHERE kodjab = 1 LIMIT 1");
$qpj->execute();
$pengaju = $qpj->fetch(PDO::FETCH_ASSOC);
$nama_pengaju = $pengaju['nama'] ?? '-';
$jabatan_pengaju = 'Direktur';

// Hitung
$pegawaiUnik = [];
foreach ($detail as $d) {
    if (!isset($pegawaiUnik[$d['nama']])) {
        $pegawaiUnik[$d['nama']] = $jabatanMap[$d['kodjab'] ?? 0] ?? '';
    }
}
$jumlahPegawai = count($pegawaiUnik);
$tarif = 90000;
$totalBiaya = $tarif * $jumlahPegawai;
$statusText = ($data['status_lembur'] == 1) ? 'Sampe Selesai' : 'Tidak Selesai';

// Group tugas per pegawai
$tugasPerPegawai = [];
foreach ($detail as $d) {
    $key = $d['nama'];
    if (!isset($tugasPerPegawai[$key])) {
        $tugasPerPegawai[$key] = ['nama' => $d['nama'], 'jabatan' => $jabatanMap[$d['kodjab'] ?? 0] ?? '', 'tugas' => []];
    }
    $tugasPerPegawai[$key]['tugas'][] = $d['tugas'];
}

$tglFormatted = tanggalIndo($data['tgl_lembur']);
$hariFormatted = hariIndo($data['tgl_lembur']);
$kegiatanList = array_values(array_unique(array_column($detail, 'tugas')));
$nl = '</w:t><w:br/><w:t>'; // line break di Word XML

// ===== LOAD TEMPLATE =====
$templatePath = __DIR__ . '/36_template_lpj_lembur.docx';
$template = new TemplateProcessor($templatePath);

// I. Pendahuluan
$template->setValue('pendahuluan', "Lembur yang dilaksanakan pada hari {$hariFormatted}, {$tglFormatted}, bertujuan untuk {$data['latarbelakang']}.");

// II. Daftar Pegawai
$lines = [];
$no = 1;
foreach ($pegawaiUnik as $nama => $jab) {
    $lines[] = "{$no}.  {$nama}" . ($jab ? " - {$jab}" : '');
    $no++;
}
$template->setValue('daftar_pegawai', implode($nl, $lines));

// III. Pelaksanaan
$lines = [];
$lines[] = "•  Hari/Tanggal: {$hariFormatted}, {$tglFormatted}";
$lines[] = "•  Durasi: Dimulai pukul " . substr($data['jam_mulai'],0,5) . " s/d Selesai";
$lines[] = "•  Kegiatan Utama:";
foreach ($kegiatanList as $keg) {
    $lines[] = "     o  {$keg}";
}
$template->setValue('pelaksanaan', implode($nl, $lines));

// IV. Hasil Pekerjaan
$lines = [];
$noPeg = 1;
foreach ($tugasPerPegawai as $peg) {
    $jabLabel = $peg['jabatan'] ? " ({$peg['jabatan']})" : '';
    $lines[] = "{$noPeg}. " . strtoupper($peg['nama']) . $jabLabel;
    foreach ($peg['tugas'] as $tugas) {
        $lines[] = "     •  {$tugas}";
    }
    $noPeg++;
}
$template->setValue('hasil_pekerjaan', implode($nl, $lines));

// V. Anggaran
$lines = [];
$lines[] = "Total biaya lembur dihitung sebagai berikut:";
$lines[] = "•  Tarif lembur: Rp " . number_format($tarif, 0, ',', '.') . " per orang";
$lines[] = "•  Durasi lembur: {$statusText}";
$lines[] = "•  Total biaya lembur: Rp " . number_format($tarif, 0, ',', '.') . " x {$jumlahPegawai} orang = Rp " . number_format($totalBiaya, 0, ',', '.');
$template->setValue('anggaran', implode($nl, $lines));

// VI. Kesimpulan — kalimat singkat berdasarkan status
if ($data['status_lembur'] == 1) {
    $kalimatKesimpulan = "Seluruh kegiatan lembur yang dilaksanakan pada hari {$hariFormatted}, {$tglFormatted} telah terselesaikan dengan baik.";
} else {
    $kalimatKesimpulan = "Kegiatan lembur yang dilaksanakan pada hari {$hariFormatted}, {$tglFormatted} tidak dapat terselesaikan sepenuhnya.";
}
$template->setValue('kesimpulan', $kalimatKesimpulan);

// TTD
$template->setValue('tempat_tanggal', 'Semarang, ' . $tglFormatted);
$template->setValue('nama_pengaju', strtoupper($nama_pengaju));
$template->setValue('jabatan_pengaju', $jabatan_pengaju);

// Lampiran keterangan
$lampLines = [];
foreach ($kegiatanList as $idx => $keg) {
    $lampLines[] = ($idx+1) . ".  " . $keg;
}
$template->setValue('lampiran', implode($nl, $lampLines));

// ===== SIMPAN TEMPLATE → TAMBAH LAMPIRAN PER TASK =====
$tempFile = tempnam(sys_get_temp_dir(), 'lpj_') . '.docx';
$template->saveAs($tempFile);

// Load ulang untuk tambah lampiran per task
$phpWord = IOFactory::load($tempFile, 'Word2007');
$sections = $phpWord->getSections();
$lastSection = $sections[0];

if (!empty($detailRows)) {
    $lastSection->addTextBreak(1);
    $noTask = 1;
    foreach ($detailRows as $dr) {
        // Judul tugas
        $lastSection->addText(
            $noTask . ". " . ($dr['tugas'] ?? '-'),
            ['bold' => true, 'size' => 11],
            ['spaceAfter' => 60]
        );

        // Keterangan/hasil
        if (!empty($dr['kesimpulan'])) {
            $lastSection->addText(
                "Hasil: " . $dr['kesimpulan'],
                ['size' => 11],
                ['spaceAfter' => 80]
            );
        }

        // Foto untuk task ini
        $fotoTask = [];
        if (!empty($dr['foto'])) {
            $decoded = json_decode($dr['foto'], true);
            $fotoTask = is_array($decoded) ? $decoded : [$dr['foto']];
        }
        foreach ($fotoTask as $fotoPath) {
            if (file_exists($fotoPath)) {
                $imageSize = @getimagesize($fotoPath);
                $w = 450; $h = 280;
                if ($imageSize && $imageSize[1] > 0) {
                    $h = min(280, round($w / ($imageSize[0] / $imageSize[1])));
                }
                $lastSection->addImage($fotoPath, ['width' => $w, 'height' => $h]);
                $lastSection->addTextBreak(1);
            }
        }

        $lastSection->addTextBreak(1);
        $noTask++;
    }
}

// ===== OUTPUT =====
$kegiatan_short = mb_substr(preg_replace('/[^a-zA-Z0-9 ]/', '', $data['latarbelakang'] ?? ''), 0, 30);
$filename = date('Ymd', strtotime($data['tgl_lembur'])) . "_LPJ_" . str_replace(' ', '_', trim($kegiatan_short)) . ".docx";

ob_clean();
header("Content-Description: File Transfer");
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Cache-Control: must-revalidate');
header('Pragma: public');

$phpWord->save("php://output", 'Word2007');
@unlink($tempFile);
exit;
?>
