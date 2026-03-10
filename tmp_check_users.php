<?php
include 'dbase.php';
$q = $conn->query("SELECT iduser, nama, stsaktif FROM ruser");
$users = $q->fetchAll(PDO::FETCH_ASSOC);
echo "Daftar User:\n";
foreach ($users as $u) {
    echo "- ID: {$u['iduser']}, Nama: {$u['nama']}, Aktif: {$u['stsaktif']}\n";
}
?>
