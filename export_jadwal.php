<?php
include 'db.php';

function sanitize($data)
{
    return htmlspecialchars(strip_tags(trim($data)));
}

// Rebuild the same filter query as in jadwal.php
$where_clauses = [];
$params = [];
$selected_angkatan = isset($_GET['angkatan']) && !empty($_GET['angkatan']) ? sanitize($_GET['angkatan']) : '';
$selected_jenis = isset($_GET['jenis']) && !empty($_GET['jenis']) ? sanitize($_GET['jenis']) : '';
$selected_tempat = isset($_GET['tempat']) && !empty($_GET['tempat']) ? filter_input(INPUT_GET, 'tempat', FILTER_VALIDATE_INT) : '';
$selected_semester = isset($_GET['semester']) && in_array($_GET['semester'], ['1', '2']) ? $_GET['semester'] : '';
$selected_date_range = isset($_GET['date_range']) && !empty($_GET['date_range']) ? sanitize($_GET['date_range']) : '';
$selected_tanggal_mulai = '';
$selected_tanggal_selesai = '';

if ($selected_date_range) {
    $dates = explode('|', $selected_date_range);
    if (count($dates) === 2 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dates[0]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dates[1])) {
        $selected_tanggal_mulai = $dates[0];
        $selected_tanggal_selesai = $dates[1];
        if ($selected_tanggal_mulai > $selected_tanggal_selesai) {
            die("Tanggal mulai tidak boleh setelah tanggal selesai");
        } else {
            $where_clauses[] = "j.tanggal_mulai <= ? AND j.tanggal_selesai >= ?";
            $params[] = $selected_tanggal_selesai;
            $params[] = $selected_tanggal_mulai;
        }
    } else {
        die("Format rentang tanggal tidak valid");
    }
}

if ($selected_angkatan) {
    $where_clauses[] = "m.angkatan = ?";
    $params[] = $selected_angkatan;
}
if ($selected_jenis) {
    $where_clauses[] = "t.jenis_tempat = ?";
    $params[] = $selected_jenis;
}
if ($selected_tempat) {
    $where_clauses[] = "j.id_tempat = ?";
    $params[] = $selected_tempat;
}
if ($selected_semester) {
    $month_range = $selected_semester == '1' ? "MONTH(j.tanggal_mulai) BETWEEN 1 AND 6" : "MONTH(j.tanggal_mulai) BETWEEN 7 AND 12";
    $where_clauses[] = $month_range;
}

// Query untuk daftar jadwal
$sql = "SELECT m.nama AS mahasiswa, t.nama_tempat AS tempat, t.jenis_tempat, d.nama AS dosen_pembimbing, j.tanggal_mulai, j.tanggal_selesai 
        FROM jadwal j 
        JOIN mahasiswa m ON j.id_mahasiswa = m.id 
        JOIN tempat t ON j.id_tempat = t.id 
        LEFT JOIN dosen_pembimbing d ON j.id_dosen_pembimbing = d.id";
if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}
$sql .= " ORDER BY j.tanggal_mulai, m.nama";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$jadwal_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Generate CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="jadwal_export_' . date('Ymd_His') . '.csv"');

// Create CSV file output
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for proper encoding in Excel
fputs($output, "\xEF\xBB\xBF");

// Write headers
fputcsv($output, ['No', 'Mahasiswa', 'Tempat', 'Jenis Tempat', 'Dosen Pembimbing', 'Tanggal Mulai', 'Tanggal Selesai'], ';');

// Write data rows
$no = 1;
foreach ($jadwal_list as $row) {
    fputcsv($output, [
        $no++,
        $row['mahasiswa'] ?? '',
        $row['tempat'] ?? '',
        $row['jenis_tempat'] ?? '',
        $row['dosen_pembimbing'] ?? 'Tidak ada',
        $row['tanggal_mulai'] ?? '',
        $row['tanggal_selesai'] ?? ''
    ], ';');
}

fclose($output);
exit;
