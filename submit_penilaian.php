<?php
// Mulai output buffering
ob_start();

// Set header JSON
header('Content-Type: application/json');

// Koneksi ke database (sesuaikan dengan konfigurasi Anda)
$host = 'localhost';
$dbname = 'psppa_feedback'; // Sesuaikan dengan nama database Anda
$username = 'root'; // Sesuaikan dengan username MySQL Anda
$password = ''; // Sesuaikan dengan password MySQL Anda (kosong jika default XAMPP)

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Koneksi database gagal: ' . $e->getMessage()]);
    exit;
}

// Pastikan tidak ada output sebelum header
require_once 'auth.php';
redirectIfNotRole('preceptor');

// Validasi CSRF token
session_start();
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'CSRF token tidak valid']);
    exit;
}

// Proses data
$tempat_pka = $_POST['tempat_pka'] ?? '';
$alamat = $_POST['alamat'] ?? '';
$preseptor = $_POST['preseptor'] ?? '';
$periode = $_POST['periode'] ?? '';
$students = json_decode($_POST['students'] ?? '[]', true);

// Validasi data
if (empty($tempat_pka) || empty($alamat) || empty($preseptor) || empty($periode) || empty($students)) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
    exit;
}

// Simpan data ke database
try {
    $pdo->beginTransaction();

    foreach ($students as $student) {
        $stmt = $pdo->prepare("INSERT INTO penilaian (tempat_pka, alamat, preseptor, periode, student_no, nim, nama, cpmk1, cpmk2, cpmk3, cpmk4, nilai_akhir, nilai_huruf) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $tempat_pka,
            $alamat,
            $preseptor,
            $periode,
            $student['no'],
            $student['nim'],
            $student['nama'],
            $student['cpmks'][0] ?? null,
            $student['cpmks'][1] ?? null,
            $student['cpmks'][2] ?? null,
            $student['cpmks'][3] ?? null,
            $student['nilai_akhir'],
            strtoupper($student['nilai_huruf'])
        ]);
    }

    // Tambahkan log aktivitas
    $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, activity) VALUES (?, ?)");
    $activity = "Memberikan penilaian periode: $periode";
    $stmt->execute([$_SESSION['user_id'], $activity]);

    $pdo->commit();
    ob_end_clean();
    echo json_encode(['success' => true, 'message' => 'Penilaian berhasil disimpan']);
} catch (PDOException $e) {
    $pdo->rollBack();
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan ke database: ' . $e->getMessage()]);
}

exit;
?>