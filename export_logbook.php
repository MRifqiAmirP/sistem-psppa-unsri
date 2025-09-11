<?php
require_once 'auth.php';
redirectIfNotRole('admin');
require_once 'config.php';

// Set header untuk file CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="logbook_mahasiswa.csv"');

// Buka output stream
$output = fopen('php://output', 'w');
fputs($output, "\xEF\xBB\xBF"); // UTF-8 BOM untuk kompatibilitas Excel

// Header kolom
fputcsv($output, ['Nama Mahasiswa', 'Tempat Wahana', 'Link Logbook', 'Tanggal'], ';');

// Ambil data logbook
$stmt = $conn->query("SELECT u.nama, l.tempat_wahana, l.komentar, l.created_at 
                      FROM logbook l 
                      JOIN users u ON l.mahasiswa_id = u.id 
                      WHERE u.role = 'mahasiswa' 
                      ORDER BY l.created_at DESC");
$logbooks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Output data ke CSV
foreach ($logbooks as $logbook) {
    fputcsv($output, [
        $logbook['nama'],
        $logbook['tempat_wahana'],
        $logbook['komentar'],
        $logbook['created_at']
    ], ';');
}

fclose($output);
exit;
