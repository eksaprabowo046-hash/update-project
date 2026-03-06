<?php
error_reporting(E_ALL);
ini_set('display_errors', 1); // Display all errors for debugging

header('Content-Type: application/json; charset=utf-8');

include "dbase.php";
// include "islogin.php";

function nameKolomNilai($nilai) {
    if ($nilai >= 4.5) {
        return "Sangat Baik";
    } elseif ($nilai >= 3.5) {
        return "Baik";
    } elseif ($nilai >= 2.5) {
        return "Normal";
    } elseif ($nilai >= 1.5) {
        return "Kurang";
    } elseif ($nilai > 0) {
        return "Evaluasi";
    } else {
        return "Belum Dinilai"; // Untuk nilai kosong atau 0
    }
}


$status = isset($_GET['status']) ? $_GET['status'] : '';

$tglAwal = isset($_GET['tglAwal']) ? $_GET['tglAwal'] : '';
$tglAkhir = isset($_GET['tglAkhir']) ? $_GET['tglAkhir'] : '';

// Database query
try {
    $sql = "SELECT
        userorder,
        SUM(CASE 
            WHEN tglselesai != '0000-00-00' AND tgltarget >= tglselesai THEN 1
            ELSE 0
        END) AS tepat_waktu,
        
        SUM(CASE 
            WHEN tglselesai = '0000-00-00' AND tgltarget >= CURDATE() THEN 1
            ELSE 0
        END) AS proses,
        
        SUM(CASE 
            WHEN tglselesai != '0000-00-00' AND tgltarget < tglselesai THEN 1
            WHEN tglselesai = '0000-00-00' AND tgltarget < CURDATE() THEN 1
            ELSE 0
        END) AS terlambat,

        COUNT(userorder) AS total_order,

        GROUP_CONCAT(CASE 
            WHEN tglselesai != '0000-00-00' AND tgltarget < tglselesai THEN ketterlambat 
        END) AS ket_terlambat,

        SUM(CASE WHEN nilai IS NOT NULL THEN 1 ELSE 0 END) AS jml_nilai
    FROM tlog
    WHERE tglorder BETWEEN ? AND ?
    GROUP BY userorder ORDER BY total_order DESC";

    // Eksekusi query
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(1, $tglAwal, PDO::PARAM_STR);
    $stmt->bindParam(2, $tglAkhir, PDO::PARAM_STR);
    $stmt->execute();

    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Hitung total per kategori
    $totalTerlambat = array_sum(array_column($data, 'terlambat'));
    $totalTepatWaktu = array_sum(array_column($data, 'tepat_waktu'));
    $totalProses = array_sum(array_column($data, 'proses'));
    $totalOrder = array_sum(array_column($data, 'total_order'));
    $jmlNilai  = array_sum(array_column($data, 'jml_nilai'));

    // Keterangan terlambat digabung saja
    $ketTerlambatArr = array_column($data, 'ket_terlambat');
    $ketTerlambat = implode(', ', array_filter($ketTerlambatArr));
    $ketTerlambat = $ketTerlambat ?: ""; // jika null, jadikan string kosong

    $data[] = [
        "userorder"     => "All",
        'tepat_waktu'   => $totalTepatWaktu,
        'proses'        => $totalProses,
        'terlambat'     => $totalTerlambat,
        'total_order'   => $totalOrder,
        'ket_terlambat' => $ketTerlambat,
        'jml_nilai'     => $jmlNilai
    ];

    foreach ($data as $key => &$value) {
        $terlambat = explode(",", $value['ket_terlambat'] ?? '');
        // bungkus masing-masing value dengan li
        $formatKet = "";
        foreach ($terlambat as $i => $ket) {
            $formatKet .= "<li style='list-style: disc;'>{$ket}</li>";
        }
        // bungkus lagi menggunakan ul
        $value['ket_terlambat'] = "<ul>{$formatKet}</ul>";
        // ambil nilai berdasarkan userorder

        $sql = "SELECT a.nilai, COUNT(*) AS jml_nilai, a.idlog, a.userorder, a.iduser, b.nmcustomer, 
               a.tglorder,a.tgltarget, a.tglselesai, a.desorder, a.ketterlambat
        FROM tlog a
        INNER JOIN rcustomer b ON a.kodcustomer = b.kodcustomer 
        WHERE (tglorder BETWEEN :awal AND :akhir) AND userorder = :userorder
        GROUP BY nilai
        ORDER BY nilai";
        $q = $conn->prepare($sql);
        $q->execute([
            ':awal' => $tglAwal,
            ':akhir' => $tglAkhir,
            ':userorder' => $value['userorder']
        ]);
        $value['nilai'] = $q->fetchAll(PDO::FETCH_ASSOC);
        foreach ($value['nilai'] as &$row) {
            if (isset($row['desorder'])) {
                // Pastikan string valid UTF-8
                $clean = mb_convert_encoding($row['desorder'], 'UTF-8', 'UTF-8');

                // Ganti karakter invalid/non-printable dengan spasi
                $clean = preg_replace('/[^\P{C}\n]+/u', ' ', $clean);

                // Normalisasi spasi ganda
                $clean = preg_replace('/\s+/', ' ', $clean);

                // Simpan kembali
                $row['desorder'] = htmlspecialchars(trim($clean), ENT_QUOTES, 'UTF-8');
            }
        }
        $jmlNilai = 0;
        foreach ($value['nilai'] as $i => &$nilai) {
            $nilaiNum = intval($nilai['nilai']);
            $jmlNilai += ($nilai['jml_nilai'] * $nilaiNum);
            $nilai['nilai'] = nameKolomNilai($nilai['nilai']);
        }

        if ($value['jml_nilai'] != 0) {
            $value['jml_nilai'] = (round($jmlNilai/$value['jml_nilai'], 2));
        }

        $value['ket_nilai'] = nameKolomNilai(round($value['jml_nilai'],2 ));

        // $sql = "SELECT a.nilai, a.idlog, a.userorder, a.iduser, b.nmcustomer, a.tglorder,a.tgltarget, a.tglselesai, a.desorder, a.ketterlambat
        // FROM tlog a
        // INNER JOIN rcustomer b  ON a.kodcustomer =b.kodcustomer 
        // WHERE (tglorder BETWEEN '$tglAwal' AND '$tglAkhir') AND userorder='{$value['userorder']}'
        // ORDER BY nilai;";

        // $q = $conn->query($sql);
        // $value['nilai'] = $q->fetchAll(PDO::FETCH_ASSOC);

        // $A = 0;
        // $B = 0;
        // $C = 0;
        // $D = 0;
        // $E = 0;
        // $N = 0;

        // if($value['nilai'][$key]['nilai'] == 5){
        //     $A++;
        // }elseif($value['nilai'][$key]['nilai'] == 4){
        //     $B++;
        // }elseif($value['nilai'][$key]['nilai'] == 3){
        //     $C++;
        // }elseif($value['nilai'][$key]['nilai'] == 2){
        //     $D++;
        // }elseif($value['nilai'][$key]['nilai'] == 1){
        //     $E++;
        // }else{
        //     $N++;
        // }
    }

    $response = [
        "status"  => empty($data) ? "empty" : "ok",
        "message" => empty($data) ? "Data tidak ditemukan" : "Berhasil ambil data",
        "data"    => $data // kalau kosong tetap []
    ];
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
} catch (PDOException $e) {
    http_response_code(500);
    $responeJson = json_encode([
        "status"  => "error",
        "message" => $e->getMessage()  . " | Query: " . $sql,
        "data"    => []
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    // error_log($responeJson); // cek di log server
    echo $responeJson;
    exit;
}
