<?php
require_once 'auth.php';
redirectIfNotRole('mahasiswa');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $link_gdrive = $_POST['link_gdrive'];
    $mahasiswa_id = $_SESSION['user_id'];

    // Validasi link Google Drive
    if (preg_match('/^https:\/\/drive\.google\.com\/.+$/', $link_gdrive)) {
        $stmt = $conn->prepare("INSERT INTO logbook (mahasiswa_id, link_gdrive) VALUES (?, ?)");
        if ($stmt->execute([$mahasiswa_id, $link_gdrive])) {
            // Log aktivitas
            $stmt = $conn->prepare("INSERT INTO activity_log (user_id, activity) VALUES (?, ?)");
            $stmt->execute([$mahasiswa_id, "Mengunggah logbook: $link_gdrive"]);
            echo "<script>alert('Logbook berhasil diunggah!'); window.location.href='dashboard.php';</script>";
        } else {
            echo "<script>alert('Gagal mengunggah logbook.'); window.location.href='dashboard.php';</script>";
        }
    } else {
        echo "<script>alert('Link Google Drive tidak valid.'); window.location.href='dashboard.php';</script>";
    }
}
