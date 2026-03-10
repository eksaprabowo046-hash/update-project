<?php
include 'dbase.php';
$q = $conn->query("SELECT COUNT(*) FROM tgaji WHERE periode = '2026-03'");
echo "Jumlah data gaji Maret 2026: " . $q->fetchColumn();
?>
