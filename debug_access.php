<?php
include "dbase.php";
echo "--- ALL USERS ---\n";
$q = $conn->query("SELECT iduser, nama, kodjab, stsaktif FROM ruser");
while($r = $q->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: " . $r['iduser'] . " | Name: " . $r['nama'] . " | Kodjab: " . $r['kodjab'] . " | Status: " . $r['stsaktif'] . "\n";
}
echo "--- HAK AKSES FOR CURRENT ADMIN ---\n";
$q2 = $conn->query("SELECT * FROM tbl_hak_akses WHERE iduser='admin'");
while($r2 = $q2->fetch(PDO::FETCH_ASSOC)) {
    echo $r2['menu_nama'] . " : " . $r2['aktif'] . "\n";
}
?>
