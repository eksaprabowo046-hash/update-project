<?php

function issudahhadir() {
    session_start();
    $iduser = $_SESSION['DEFAULT_IDUSER'];
    require('dbase.php');
    $tanggal = date("Y-m-d");
    $hadir = ''; 
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $sql = $conn->prepare("select iduser, tanggal, hadir, pulang from tkehadiran where iduser = '$iduser' and tanggal = '$tanggal'");
    $sql->execute();  
    if ($sql->rowCount() > 0) { 
        $rs = $sql->fetch();
        $hadir = $rs['hadir'];
    }    
    return $hadir;
}

function issudahpulang() {
    session_start();
    $iduser = $_SESSION['DEFAULT_IDUSER'];
    require('dbase.php');
    $tanggal = date("Y-m-d");
    $pulang = ''; 
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $sql = $conn->prepare("select iduser, tanggal, hadir, pulang from tkehadiran where iduser = '$iduser' and tanggal = '$tanggal'");
    $sql->execute();  
    if ($sql->rowCount() > 0) { 
        $rs = $sql->fetch();
        $pulang = $rs['pulang'];
    }    
    return $pulang;
}

// Fungsi untuk menghitung jarak antara dua titik koordinat menggunakan formula Haversine
function haversineDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371000; // Radius bumi dalam meter

    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) * sin($dLon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    $distance = $earthRadius * $c;

    return $distance;
}

// Fungsi untuk memeriksa apakah pengunjung berada di dalam area kantor
function isInsideOffice($visitorLatitude, $visitorLongitude) {
    // Koordinat kantor Anda
    $officeLatitude = -7.035801; // Ganti dengan koordinat lintang kantor Anda
    $officeLongitude = 110.474189; // Ganti dengan koordinat bujur kantor Anda
    $maxDistance = 1000; // Jarak maksimum dalam meter dari kantor

    // Hitung jarak antara lokasi pengunjung dan kantor menggunakan formula Haversine
    $distance = haversineDistance($officeLatitude, $officeLongitude, $visitorLatitude, $visitorLongitude);

    // Jika jarak kurang dari atau sama dengan maksimum, pengunjung berada di dalam area kantor
    return $distance <= $maxDistance;
}

// Mendapatkan data lokasi dari request POST
$data = json_decode(file_get_contents('php://input'), true);
$visitorLatitude = $data['latitude'];
$visitorLongitude = $data['longitude'];

echo "Posisi Anda : ".$visitorLatitude ." , ".$visitorLongitude . "<br>";

// Periksa apakah pengunjung berada di dalam area kantor
if (isInsideOffice($visitorLatitude, $visitorLongitude)) {
    // Izinkan akses ke web kantor
    echo "Selamat datang! Anda berada di dalam area kantor DSI.<br><br>";
    echo '<form name="kehadiran" method="POST" action="kehadiran.php">';
    if (issudahhadir() == '') {
        echo '<input type="submit" name="submit" value="HADIR" class="btn btn-primary">&nbsp';
    }   
    if (issudahpulang() == '') {
        echo '<input type="submit" name="submit" value="PULANG" class="btn btn-primary ' . (issudahhadir() == '' ? 'disabled' : '') . '">';
    }
    echo '</form>';
} else {
    // Tolak akses ke web kantor
    echo "Anda harus berada di dalam area kantor DSI untuk mengakses absensi ini.";
}

?>
