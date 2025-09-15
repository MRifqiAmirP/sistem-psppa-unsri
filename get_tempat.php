<?php
include 'db.php';

header('Content-Type: application/json');

$jenis = isset($_GET['jenis']) ? $_GET['jenis'] : '';
if ($jenis) {
    $stmt = $pdo->prepare("SELECT id, nama_tempat FROM tempat WHERE jenis_tempat = ? ORDER BY nama_tempat");
    $stmt->execute([$jenis]);
    $tempat = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($tempat);
} else {
    echo json_encode([]);
}
