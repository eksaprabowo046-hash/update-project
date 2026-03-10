<?php
require 'vendor/autoload.php';
include 'dbase.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$filter_tahun = $_GET['tahun'] ?? date('Y');

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Data THR ' . $filter_tahun);

// --- PENGATURAN HEADER ---
$headers = [
    'ID PEGAWAI', 
    'NAMA PEGAWAI',
    'TAHUN', 
    'UPAH TERAKHIR (REF)', 
    'MASA KERJA (BLN)',
    'NOMINAL THR',
    'KETERANGAN'
];

$column = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($column . '1', $header);
    $column++;
}

// Styling Header (Festive Green)
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '2D5A27'], // Festive Green
    ],
    'borders' => [
        'allBorders' => ['borderStyle' => Border::BORDER_THIN],
    ],
];
$sheet->getStyle('A1:G1')->applyFromArray($headerStyle);
$sheet->getRowDimension('1')->setRowHeight(30);

// --- PENGISIAN DATA OTOMATIS ---
// Ambil semua pegawai aktif
$query = $conn->prepare("
    SELECT ru.iduser, ru.nama, ru.tgl_masuk,
           (SELECT gaji_pokok + tunj_jabatan FROM tgaji WHERE iduser_pegawai = ru.iduser ORDER BY periode DESC LIMIT 1) as upah_terakhir
    FROM ruser ru
    WHERE ru.stsaktif = 1
    ORDER BY ru.nama ASC
");
$query->execute();

$row = 2;
while ($user = $query->fetch(PDO::FETCH_ASSOC)) {
    $idpegawai = $user['iduser'];
    $upah = floatval($user['upah_terakhir'] ?? 5000000); // Fallback
    
    // Hitung Estimasi THR
    $thr_est = 0;
    $total_bulan = 0;
    if (!empty($user['tgl_masuk'])) {
        $tgl_masuk = new DateTime($user['tgl_masuk']);
        $tgl_thr = new DateTime($filter_tahun . '-12-31');
        $diff = $tgl_masuk->diff($tgl_thr);
        $total_bulan = ($diff->y * 12) + $diff->m;
        
        if ($total_bulan >= 12) {
            $thr_est = $upah;
        } else if ($total_bulan >= 1) {
            $thr_est = ($total_bulan / 12) * $upah;
        }
    }

    $sheet->setCellValue('A' . $row, $idpegawai);
    $sheet->setCellValue('B' . $row, $user['nama']);
    $sheet->setCellValue('C' . $row, $filter_tahun);
    $sheet->setCellValue('D' . $row, $upah);
    $sheet->setCellValue('E' . $row, $total_bulan);
    $sheet->setCellValue('F' . $row, round($thr_est));
    $sheet->setCellValue('G' . $row, 'Estimasi sistem');
    
    // Zebra Stripe
    if ($row % 2 == 0) {
        $sheet->getStyle('A'.$row.':G'.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E9F5E8');
    }
    $sheet->getStyle('A'.$row.':G'.$row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    
    $row++;
}

// Format Angka
$sheet->getStyle('D2:D' . ($row-1))->getNumberFormat()->setFormatCode('#,##0');
$sheet->getStyle('F2:F' . ($row-1))->getNumberFormat()->setFormatCode('#,##0');

// Lebar kolom otomatis
foreach (range('A', 'G') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

if (ob_get_length()) ob_end_clean();

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment;filename="TEMPLATE_THR_'.$filter_tahun.'.xls"');
header('Cache-Control: max-age=0');

$writer = new Xls($spreadsheet);
$writer->save('php://output');
exit;
