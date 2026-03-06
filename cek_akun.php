<?php
include "dbase.php";
try {
    $stmt = $conn->query('SELECT kodakun, nmakun FROM takun WHERE tipe = "D" LIMIT 10');
    while($row = $stmt->fetch()) {
        echo $row['kodakun'] . ' - ' . $row['nmakun'] . PHP_EOL;
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>