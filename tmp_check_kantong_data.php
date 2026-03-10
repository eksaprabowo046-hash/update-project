<?php
include 'dbase.php';
echo "--- DATA KANTONG DARI EXCEL ---\n";
$q = $conn->query("SELECT * FROM tkantong WHERE history_kantong LIKE '%Excel%'");
$res = $q->fetchAll(PDO::FETCH_ASSOC);
if(empty($res)) {
    echo "TIDAK ADA DATA KANTONG DARI EXCEL.\n";
} else {
    print_r($res);
}

echo "\n--- DATA KAS DARI EXCEL ---\n";
$q2 = $conn->query("SELECT * FROM tkas WHERE deskripsi LIKE '%Excel%'");
$res2 = $q2->fetchAll(PDO::FETCH_ASSOC);
print_r($res2);
?>
