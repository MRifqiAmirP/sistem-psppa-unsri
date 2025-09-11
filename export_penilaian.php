<?php
require_once 'auth.php';
redirectIfNotRole('admin');
require_once 'config.php';

// Fungsi untuk mendapatkan jumlah maksimum CPMK
function getMaxCpmk($conn)
{
    $stmt = $conn->query("SELECT MAX(JSON_LENGTH(cpmks)) FROM penilaian");
    return $stmt->fetchColumn() ?: 4; // Minimal 4 CPMK
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="penilaian_mahasiswa.csv"');

$output = fopen('php://output', 'w');
fputs($output, "\xEF\xBB\xBF"); // UTF-8 BOM untuk kompatibilitas Excel

// Dapatkan jumlah maksimum CPMK
$max_cpmk = getMaxCpmk($conn);

// Buat header CSV dinamis
$headers = ['Nama', 'Tempat PKPA', 'Alamat', 'Preseptor'];
for ($i = 1; $i <= $max_cpmk; $i++) {
    $headers[] = "CPMK $i";
}
$headers[] = 'Nilai Akhir';
$headers[] = 'Nilai Huruf';
fputcsv($output, $headers, ';');

$stmt = $conn->query("SELECT nama, tempat_pka, alamat, preseptor, cpmks, nilai_akhir, nilai_huruf 
                      FROM penilaian 
                      ORDER BY created_at DESC");
$penilaian = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($penilaian as $nilai) {
    $cpmks = json_decode($nilai['cpmks'], true) ?? [];
    $row = [
        $nilai['nama'],
        $nilai['tempat_pka'],
        $nilai['alamat'] ?: '-',
        $nilai['preseptor'],
    ];
    for ($i = 0; $i < $max_cpmk; $i++) {
        $row[] = isset($cpmks[$i]) ? $cpmks[$i] : '-';
    }
    $row[] = $nilai['nilai_akhir'];
    $row[] = $nilai['nilai_huruf'];
    fputcsv($output, $row, ';');
}

fclose($output);
exit;
