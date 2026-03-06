<?php
include_once "dbase.php";

// === BULK UPDATE ===
if (isset($_POST['bulk']) && $_POST['bulk'] == '1') {
    $nilai = isset($_POST['nilai']) ? $_POST['nilai'] : '';
    $idlogs_str = isset($_POST['idlogs']) ? $_POST['idlogs'] : '';
    
    if ($nilai === '' || $idlogs_str === '') {
        echo json_encode(['error' => 'Parameter tidak lengkap']);
        exit;
    }
    
    // Parse comma-separated IDs
    $idlogs = array_filter(array_map('intval', explode(',', $idlogs_str)));
    
    if (empty($idlogs)) {
        echo json_encode(['error' => 'Tidak ada ID valid']);
        exit;
    }
    
    try {
        // Build placeholders for IN clause
        $placeholders = implode(',', array_fill(0, count($idlogs), '?'));
        $sql = "UPDATE tlog SET nilai = ? WHERE idlog IN ($placeholders)";
        $q = $conn->prepare($sql);
        
        // Bind parameters: nilai first, then all idlogs
        $params = array_merge([$nilai], $idlogs);
        $q->execute($params);
        
        echo json_encode(['success' => true, 'updated' => $q->rowCount()]);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// === SINGLE UPDATE (existing) ===
$id = isset($_GET['idlog']) ? $_GET['idlog'] : '';
$nilai = isset($_GET['nilai']) ? $_GET['nilai'] : '';

$sql = "UPDATE tlog SET nilai = :nilai WHERE idlog = :idlog";
$q = $conn->prepare($sql);
$q->bindParam(':nilai', $nilai);
$q->bindParam(':idlog', $id);
$q->execute();
echo json_encode($q->errorInfo());
?>