<?php include 'db.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="style1.css">
</head>

<body>
    <?php include 'header.php'; ?>
    <h1>Dashboard</h1>

    <?php
    session_start();

    // Cek apakah pengguna sudah login dan memiliki role jadwal_admin
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'jadwal_admin') {
        header('Location: index.php');
        exit;
    }

    // Statistik
    $total_mahasiswa = $pdo->query("SELECT COUNT(*) FROM mahasiswa")->fetchColumn();
    $total_tempat = $pdo->query("SELECT COUNT(*) FROM tempat")->fetchColumn();
    $start_week = date('Y-m-d', strtotime('monday this week'));
    $end_week = date('Y-m-d', strtotime('sunday this week'));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM jadwal WHERE tanggal_mulai <= ? AND tanggal_selesai >= ?");
    $stmt->execute([$end_week, $start_week]);
    $active_week = $stmt->fetchColumn();
    ?>

    <div class="stats-container">
        <div class="stat-card">
            <h2>Total Mahasiswa</h2>
            <p class="stat-number"><?php echo $total_mahasiswa; ?></p>
        </div>
        <div class="stat-card">
            <h2>Total Tempat</h2>
            <p class="stat-number"><?php echo $total_tempat; ?></p>
        </div>
        <div class="stat-card">
            <h2>Jadwal Aktif Minggu Ini</h2>
            <p class="stat-number"><?php echo $active_week; ?></p>
        </div>
    </div>

</body>

</html>