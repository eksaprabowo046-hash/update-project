<?php
include "dbase.php";

try {
    $sql = file_get_contents('db_migration_kantong.sql');
    $conn->exec($sql);
    echo "Migration kantong berhasil dijalankan!";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>