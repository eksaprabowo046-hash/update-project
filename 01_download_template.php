<?php

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Font;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$spreadsheet->getProperties()
    ->setCreator("Your Application")
    ->setTitle("Template Import Log")
    ->setSubject("Template Import Log")
    ->setDescription("Template untuk import data log");

$sheet->getColumnDimension('A')->setWidth(8);
$sheet->getColumnDimension('B')->setWidth(20);
$sheet->getColumnDimension('C')->setWidth(15);
$sheet->getColumnDimension('D')->setWidth(15);
$sheet->getColumnDimension('E')->setWidth(15);
$sheet->getColumnDimension('F')->setWidth(18);
$sheet->getColumnDimension('G')->setWidth(15);
$sheet->getColumnDimension('H')->setWidth(15);
$sheet->getColumnDimension('I')->setWidth(35);
$sheet->getColumnDimension('J')->setWidth(40);

$headers = ['No', 'Jenis Report', 'Mitra', 'Tanggal Order', 'Tanggal Selesai', 'Dikerjakan Oleh', 'Prioritas', 'Order Melalui', 'Uraian Order', 'Aktivitas Layanan'];
$sheet->fromArray($headers, NULL, 'A1');

$headerStyle = [
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF'],
        'size' => 11
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '4472C4']
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000']
        ]
    ]
];

$sheet->getStyle('A1:J1')->applyFromArray($headerStyle);
$sheet->getRowDimension('1')->setRowHeight(25);

$sampleData = [
    [1, 'A: ADMINISTRATION', 'CUST001', '2025-10-07', '2025-10-15', 'USER01', 'Sangat Tinggi', 'WA', 'Membuat laporan keuangan bulanan', '2025-10-07: Mulai analisis data'],
    [2, 'B: BUSINESS', 'CUST002', '2025-10-08', '2025-10-20', 'USER02', 'Tinggi', 'Email', 'Konsultasi bisnis', '2025-10-08: Meeting dengan klien'],
    [3, 'D: DEVELOPING', 'CUST003', '2025-10-09', '2025-10-25', 'USER03', 'Biasa', 'Telp', 'Pengembangan aplikasi', '2025-10-09: Analisa requirement']
];

$rowNum = 2;
foreach ($sampleData as $row) {
    $sheet->fromArray($row, NULL, 'A' . $rowNum);
    $rowNum++;
}

$dataStyle = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => 'CCCCCC']
        ]
    ],
    'alignment' => [
        'vertical' => Alignment::VERTICAL_TOP,
        'wrapText' => true
    ]
];

$sheet->getStyle('A2:J' . ($rowNum - 1))->applyFromArray($dataStyle);

$sheet->getStyle('A2:A' . ($rowNum - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('D2:E' . ($rowNum - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('G2:G' . ($rowNum - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

for ($i = 2; $i < $rowNum; $i++) {
    $sheet->getRowDimension($i)->setRowHeight(-1);
}

$instructionSheet = $spreadsheet->createSheet();
$instructionSheet->setTitle('Instruksi');

$instructionSheet->setCellValue('A1', 'INSTRUKSI PENGISIAN TEMPLATE IMPORT LOG');
$instructionSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$instructionSheet->mergeCells('A1:D1');

$instructions = [
    [''],
    ['Keterangan Kolom:', ''],
    ['No', 'Nomor urut (angka, contoh: 1, 2, 3)'],
    ['Jenis Report', 'A: ADMINISTRATION, B: BUSINESS, D: DEVELOPING, M: MAINTENANCE'],
    ['Mitra', 'Kode Customer/Mitra (harus sudah terdaftar di database)'],
    ['Tanggal Order', 'Tanggal order format YYYY-MM-DD (contoh: 2025-10-07) atau gunakan format tanggal Excel'],
    ['Tanggal Selesai', 'Tanggal target selesai format YYYY-MM-DD atau gunakan format tanggal Excel'],
    ['Dikerjakan Oleh', 'ID User yang mengerjakan (harus sudah terdaftar di database)'],
    ['Prioritas', 'Sangat Tinggi / Tinggi / Biasa atau 1 / 2 / 3'],
    ['Order Melalui', 'Sarana order seperti WA, Email, Telp, dll'],
    ['Uraian Order', 'Deskripsi/uraian order (maksimal 1000 karakter)'],
    ['Aktivitas Layanan', 'Deskripsi aktivitas layanan (maksimal 2000 karakter)'],
    [''],
    ['CATATAN PENTING:', ''],
    ['1.', 'Header harus sama persis seperti contoh (case sensitive)'],
    ['2.', 'Customer/Mitra dan User harus sudah terdaftar di database'],
    ['3.', 'Tanggal bisa menggunakan format Excel date atau teks YYYY-MM-DD'],
    ['4.', 'Jenis bisnis harus diisi lengkap dengan format: "A: ADMINISTRATION", dll'],
    ['5.', 'Data akan divalidasi sebelum disimpan ke database'],
    ['6.', 'Baris dengan error akan diabaikan dan tidak diimport'],
    ['7.', 'Hapus baris contoh sebelum mengisi data Anda sendiri']
];

$instructionRow = 3;
foreach ($instructions as $instruction) {
    if (count($instruction) == 1) {
        $instructionSheet->setCellValue('A' . $instructionRow, $instruction[0]);
        if (!empty($instruction[0])) {
            $instructionSheet->getStyle('A' . $instructionRow)->getFont()->setBold(true);
        }
    } else {
        $instructionSheet->setCellValue('A' . $instructionRow, $instruction[0]);
        $instructionSheet->setCellValue('B' . $instructionRow, $instruction[1]);
        $instructionSheet->getStyle('A' . $instructionRow)->getFont()->setBold(true);
    }
    $instructionRow++;
}

$instructionSheet->getColumnDimension('A')->setWidth(20);
$instructionSheet->getColumnDimension('B')->setWidth(80);
$instructionSheet->getStyle('A3:B' . ($instructionRow - 1))->getAlignment()->setWrapText(true);

$spreadsheet->setActiveSheetIndex(0);

$filename = 'template_import_log_' . date('Ymd') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Cache-Control: max-age=1');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: cache, must-revalidate');
header('Pragma: public');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>