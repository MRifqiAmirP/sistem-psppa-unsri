<?php include 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="style1.css">
</head>

<body>
    <header>
        <a href="beranda.php">Dashboard</a>
        <a href="mahasiswa1.php">Mahasiswa</a>
        <a href="tempat.php">Tempat</a>
        <a href="jadwal.php">Jadwal</a>
        <a href="tambah_jadwal.php">Tambah Jadwal</a>
        <a href="logout.php" class="logout-btn">Logout</a>
    </header>

    <h1>Dashboard</h1>

    <?php
    session_start();

    // Cek apakah pengguna sudah login dan memiliki role jadwal_admin
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'jadwal_admin') {
        header('Location: index.php');
        exit;
    }

    // Statistik
    $total_mahasiswa = $conn->query("SELECT COUNT(*) FROM mahasiswa")->fetchColumn();
    $total_tempat = $conn->query("SELECT COUNT(*) FROM tempat")->fetchColumn();
    $start_week = date('Y-m-d', strtotime('monday this week'));
    $end_week = date('Y-m-d', strtotime('sunday this week'));
    $stmt = $conn->prepare("SELECT COUNT(*) FROM jadwal WHERE tanggal_mulai <= ? AND tanggal_selesai >= ?");
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