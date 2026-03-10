<?php
require 'vendor/autoload.php';
include 'dbase.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Daftar Kantong');

// --- PENGATURAN HEADER ---
$headers = [
    'ID KANTONG (Biarkan kosong untuk baru)', 
    'NAMA KANTONG',
    'DESKRIPSI', 
    'SALDO AWAL (Hanya untuk Kantong Baru)'
];

$column = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($column . '1', $header);
    $column++;
}

// Styling Header (Corporate Blue-Gray)
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '455A64'], 
    ],
    'borders' => [
        'allBorders' => ['borderStyle' => Border::BORDER_THIN],
    ],
];
$sheet->getStyle('A1:D1')->applyFromArray($headerStyle);
$sheet->getRowDimension('1')->setRowHeight(30);

// --- PENGISIAN DATA KANTONG EXIST ---
// Query JOIN dengan SUM Kas untuk mendapatkan saldo aktual
$sql = "SELECT k.id_kantong, k.nama_kantong, k.deskripsi, 
               SUM(CASE WHEN s.plusmin = '+' THEN s.totalharga ELSE -s.totalharga END) as saldo_aktual
        FROM tkantong k
        LEFT JOIN tkas s ON k.id_kantong = s.id_kantong
        GROUP BY k.id_kantong
        ORDER BY k.id_kantong ASC";
$query = $conn->query($sql);

$row = 2;
while ($data = $query->fetch(PDO::FETCH_ASSOC)) {
    $sheet->setCellValue('A' . $row, $data['id_kantong']);
    $sheet->setCellValue('B' . $row, $data['nama_kantong']);
    $sheet->setCellValue('C' . $row, $data['deskripsi']);
    $sheet->setCellValue('D' . $row, $data['saldo_aktual'] ?? 0);
    
    // Zebra Stripe
    if ($row % 2 == 0) {
        $sheet->getStyle('A'.$row.':D'.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F5F5F5');
    }
    $sheet->getStyle('A'.$row.':D'.$row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    
    $row++;
}

// Tambahkan baris kosong untuk input baru
for($i=0; $i<5; $i++) {
    $sheet->getStyle('A'.$row.':D'.$row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $row++;
}

// Format Angka Saldo
$sheet->getStyle('D2:D' . ($row-1))->getNumberFormat()->setFormatCode('#,##0');

// Lebar kolom otomatis
foreach (range('A', 'D') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

if (ob_get_length()) ob_end_clean();

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment;filename="MASTER_KANTONG_'.date('Y-m-d').'.xls"');
header('Cache-Control: max-age=0');

$writer = new Xls($spreadsheet);
$writer->save('php://output');
exit;
