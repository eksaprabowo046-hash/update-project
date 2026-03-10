<?php
require 'vendor/autoload.php';
include 'dbase.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls; // Gunakan XLS, bukan XLSX
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Data Gaji Karyawan');

// --- PENGATURAN HEADER ---
$headers = [
    'ID PEGAWAI', 
    'NAMA PEGAWAI (INFO)',
    'PERIODE (YYYY-MM)', 
    'GAJI POKOK', 
    'TUNJ. JABATAN', 
    'TUNJ. DINAS', 
    'LEMBUR', 
    'BONUS', 
    'BPJS TK', 
    'BPJS KESEHATAN', 
    'POT. PINJAMAN', 
    'POT. LAIN', 
    'KETERANGAN'
];

$column = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($column . '1', $header);
    $column++;
}

// Styling Header (Premium Corporate Blue)
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '2F5597'],
    ],
    'borders' => [
        'allBorders' => ['borderStyle' => Border::BORDER_THIN],
    ],
];
$sheet->getStyle('A1:M1')->applyFromArray($headerStyle);
$sheet->getRowDimension('1')->setRowHeight(30);

// --- PENGISIAN DATA OTOMATIS ---
$periode_target = date('Y-m');
$query = $conn->prepare("
    SELECT ru.iduser, ru.nama, 
           COALESCE(g.gaji_pokok, 0) as gp, 
           COALESCE(g.tunj_jabatan, 0) as tj, 
           COALESCE(g.tunj_perjalanan, 0) as tp,
           COALESCE(g.bpjs_tk, 0) as bt,
           COALESCE(g.bpjs_kesehatan, 0) as bk
    FROM ruser ru
    LEFT JOIN (
        SELECT g1.* FROM tgaji g1
        INNER JOIN (SELECT iduser_pegawai, MAX(periode) as max_p FROM tgaji GROUP BY iduser_pegawai) g2
        ON g1.iduser_pegawai = g2.iduser_pegawai AND g1.periode = g2.max_p
    ) g ON ru.iduser = g.iduser_pegawai
    WHERE ru.stsaktif = 1
    ORDER BY ru.nama ASC
");
$query->execute();

$row = 2;
while ($data = $query->fetch(PDO::FETCH_ASSOC)) {
    $sheet->setCellValue('A' . $row, $data['iduser']);
    $sheet->setCellValue('B' . $row, $data['nama']);
    $sheet->setCellValue('C' . $row, $periode_target);
    $sheet->setCellValue('D' . $row, $data['gp']);
    $sheet->setCellValue('E' . $row, $data['tj']);
    $sheet->setCellValue('F' . $row, $data['tp']);
    $sheet->setCellValue('G' . $row, 0); 
    $sheet->setCellValue('H' . $row, 0); 
    $sheet->setCellValue('I' . $row, $data['bt']);
    $sheet->setCellValue('J' . $row, $data['bk']);
    
    // Potongan Pinjaman Aktif
    $pot_pinjaman = 0;
    try {
        $qp = $conn->prepare("SELECT SUM(cicilan_perbulan) FROM tpinjaman WHERE iduser_pemohon = :id AND status_approval = 'Approved' AND status_lunas = 'Belum'");
        $qp->execute([':id' => $data['iduser']]);
        $pot_pinjaman = floatval($qp->fetchColumn());
    } catch(Exception $e) {}
    
    $sheet->setCellValue('K' . $row, $pot_pinjaman);
    $sheet->setCellValue('L' . $row, 0);
    $sheet->setCellValue('M' . $row, 'Auto-generated');
    
    // Zebra Stripe
    if ($row % 2 == 0) {
        $sheet->getStyle('A'.$row.':M'.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F2F2F2');
    }
    $sheet->getStyle('A'.$row.':M'.$row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    
    $row++;
}

// Format Angka
$sheet->getStyle('D2:L' . ($row-1))->getNumberFormat()->setFormatCode('#,##0');

// Lebar kolom otomatis
foreach (range('A', 'M') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Bersihkan output buffer jika ada output yang tidak sengaja terkirim
if (ob_get_length()) ob_end_clean();

header('Content-Type: application/vnd.ms-excel'); // Tipe MIME untuk XLS
header('Content-Disposition: attachment;filename="LAPORAN_GAJI_MASUKAN_'.date('Y-m').'.xls"');
header('Cache-Control: max-age=0');

$writer = new Xls($spreadsheet); // Writer XLS
$writer->save('php://output');
exit;
