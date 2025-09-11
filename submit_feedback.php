<?php
require_once 'auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo "<script>alert('CSRF token tidak valid.'); window.location.href='feedback.php';</script>";
        exit;
    }

    $tipe = $_POST['tipe'];
    $komentar = htmlspecialchars(trim($_POST['komentar']));
    $user_id = $_SESSION['user_id'];

    if (empty($komentar)) {
        echo "<script>alert('Komentar wajib diisi.'); window.location.href='feedback.php';</script>";
        exit;
    }

    try {
        if ($tipe === 'mahasiswa' && $_SESSION['role'] === 'preceptor') {
            $nama_mahasiswa = htmlspecialchars(trim($_POST['nama_mahasiswa']));
            if (empty($nama_mahasiswa)) {
                echo "<script>alert('Nama mahasiswa wajib diisi.'); window.location.href='feedback.php';</script>";
                exit;
            }
            $stmt = $conn->prepare("INSERT INTO feedback (preceptor_id, nama_mahasiswa, komentar, tipe) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $nama_mahasiswa, $komentar, 'mahasiswa']);
            $activity = "Memberikan feedback ke mahasiswa: $nama_mahasiswa";
        } elseif ($tipe === 'prodi' || $tipe === 'wahana') {
            $nama_wahana = ($tipe === 'wahana') ? htmlspecialchars(trim($_POST['nama_wahana'] ?? '')) : null;
            $stmt = $conn->prepare("INSERT INTO feedback (mahasiswa_id, preceptor_id, komentar, tipe, nama_wahana) VALUES (?, ?, ?, ?, ?)");
            $mahasiswa_id = ($_SESSION['role'] === 'mahasiswa') ? $user_id : null;
            $preceptor_id = ($_SESSION['role'] === 'preceptor') ? $user_id : null;
            $stmt->execute([$mahasiswa_id, $preceptor_id, $komentar, $tipe, $nama_wahana]);
            $activity = "Memberikan feedback untuk $tipe" . ($nama_wahana ? " ($nama_wahana)" : "");
        } else {
            echo "<script>alert('Tipe feedback atau role tidak valid.'); window.location.href='feedback.php';</script>";
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO activity_log (user_id, activity) VALUES (?, ?)");
        $stmt->execute([$user_id, $activity]);

        echo "<script>alert('Feedback berhasil dikirim!'); window.location.href='feedback.php';</script>";
    } catch (PDOException $e) {
        echo "<script>alert('Terjadi kesalahan: " . addslashes($e->getMessage()) . "'); window.location.href='feedback.php';</script>";
    }
} else {
    header('Location: feedback.php');
    exit;
}
