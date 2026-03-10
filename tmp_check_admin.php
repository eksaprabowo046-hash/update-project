<?php
include "dbase.php";
$q = $conn->query("SELECT iduser, passwd, kodjab FROM ruser WHERE iduser='admin'");
$r = $q->fetch(PDO::FETCH_ASSOC);
if ($r) {
    echo $r['iduser'] . " | " . $r['passwd'] . " | " . $r['kodjab'] . "\n";
} else {
    echo "No admin user found\n";
}
?>
