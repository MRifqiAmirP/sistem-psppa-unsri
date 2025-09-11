<?php
// Mulai output buffering
ob_start();

// Set header JSON
header('Content-Type: application/json');

// Koneksi ke database
require_once 'config.php';
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
    $conn->beginTransaction();

    foreach ($students as $student) {
        $cpmks_json = json_encode($student['cpmks']);
        $stmt = $conn->prepare("INSERT INTO penilaian (tempat_pka, alamat, preseptor, periode, student_no, nim, nama, cpmks, nilai_akhir, nilai_huruf) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $tempat_pka,
            $alamat,
            $preseptor,
            $periode,
            $student['no'],
            $student['nim'],
            $student['nama'],
            $cpmks_json,
            $student['nilai_akhir'],
            strtoupper($student['nilai_huruf'])
        ]);
    }

    // Tambahkan log aktivitas
    $stmt = $conn->prepare("INSERT INTO activity_log (user_id, activity) VALUES (?, ?)");
    $activity = "Memberikan penilaian periode: $periode";
    $stmt->execute([$_SESSION['user_id'], $activity]);

    $conn->commit();
    ob_end_clean();
    echo json_encode(['success' => true, 'message' => 'Penilaian berhasil disimpan']);
} catch (PDOException $e) {
    $conn->rollBack();
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan ke database: ' . $e->getMessage()]);
}

exit;
