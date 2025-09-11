<?php
require_once 'auth.php';
redirectIfNotRole('admin');

// Buat token CSRF jika belum ada (opsional, tapi aman)
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Tangkap parameter tipe
$tipe = isset($_GET['tipe']) ? $_GET['tipe'] : 'all'; // 'mahasiswa', 'prodi_wahana', atau 'all'

// Nama file Excel dengan tanggal
$filename = "feedback_export_" . date('Y-m-d') . ".xls";

// Header untuk download Excel
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// Fungsi untuk membersihkan data (hindari tab dan newline)
function cleanData(&$str)
{
    if ($str == 't' || $str == ' ') $str = '&nbsp;';
    $str = str_replace('"', '""', $str);
    $str = str_replace("\t", "\\t", $str);
    $str = str_replace("\n", "\\n", $str);
    $str = str_replace("\r", "\\r", $str);
    if (strpos($str, '  ') !== false) $str = utf8_encode($str);
}

// Ambil data feedback berdasarkan tipe
$exportData = array();
if ($tipe === 'mahasiswa') {
    // Feedback mahasiswa
    $stmt = $conn->prepare("SELECT f.komentar, f.nilai, f.created_at, 
                                   COALESCE(u.nama, 'Tidak ada') AS preceptor_nama, 
                                   COALESCE(f.nama_mahasiswa, 'Tidak ada') AS mahasiswa_nama,
                                   f.tipe
                            FROM feedback f 
                            LEFT JOIN users u ON f.preceptor_id = u.id 
                            WHERE f.tipe = 'mahasiswa'
                            ORDER BY f.created_at DESC");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $exportData[] = $row;
    }
} elseif ($tipe === 'prodi_wahana') {
    // Feedback prodi dan wahana saja
    $stmt = $conn->prepare("SELECT f.komentar, f.nama_wahana, f.tipe, f.created_at, f.nama_mahasiswa,
                                   COALESCE(u1.nama, 'Tidak ada') AS preceptor_nama,
                                   COALESCE(u2.nama, 'Tidak ada') AS pengirim_nama
                            FROM feedback f 
                            LEFT JOIN users u1 ON f.preceptor_id = u1.id 
                            LEFT JOIN users u2 ON f.mahasiswa_id = u2.id 
                            WHERE f.tipe IN ('prodi', 'wahana')
                            ORDER BY f.created_at DESC");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $exportData[] = $row;
    }
} else {
    // Semua feedback
    $stmt = $conn->prepare("SELECT f.komentar, f.nama_wahana, f.tipe, f.created_at, f.nama_mahasiswa,
                                   COALESCE(u1.nama, 'Tidak ada') AS preceptor_nama,
                                   COALESCE(u2.nama, 'Tidak ada') AS pengirim_nama,
                                   f.nilai
                            FROM feedback f 
                            LEFT JOIN users u1 ON f.preceptor_id = u1.id 
                            LEFT JOIN users u2 ON f.mahasiswa_id = u2.id 
                            ORDER BY f.created_at DESC");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $exportData[] = $row;
    }
}

// Jika tidak ada data
if (empty($exportData)) {
    echo "Tidak ada data feedback untuk diexport.";
    exit;
}

// Output header kolom (tanpa ID)
echo "Tipe\tKomentar\tNama Mahasiswa/Wahana\tPengirim/Penerima\tNilai\tTanggal\n";

// Output data
foreach ($exportData as $row) {
    array_walk($row, 'cleanData'); // Bersihkan data
    // Sesuaikan kolom berdasarkan data (tanpa ID)
    $outputRow = array(
        $row['tipe'] ?? '',
        $row['komentar'] ?? '',
        $row['nama_mahasiswa'] ?? $row['nama_wahana'] ?? '',
        $row['preceptor_nama'] ?? $row['pengirim_nama'] ?? '',
        $row['nilai'] ?? '-',
        $row['created_at'] ?? ''
    );
    echo implode("\t", $outputRow) . "\n";
}

exit;
