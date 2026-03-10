<?php
include 'dbase.php';
echo "--- STRUKTUR TKANTONG ---\n";
$q = $conn->query("DESCRIBE tkantong");
while($r = $q->fetch(PDO::FETCH_ASSOC)) {
    echo "- {$r['Field']} ({$r['Type']})\n";
}

echo "\n--- STRUKTUR TKAS ---\n";
$q2 = $conn->query("DESCRIBE tkas");
while($r = $q2->fetch(PDO::FETCH_ASSOC)) {
    echo "- {$r['Field']} ({$r['Type']})\n";
}
?>
