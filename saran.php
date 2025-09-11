<?php
require_once 'auth.php';
redirectIfNotRole('mahasiswa');

// Buat CSRF token jika belum ada
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_id = $_SESSION['user_id'];

// Proses pengiriman masukkan ke prodi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_prodi'])) {
    $komentar = htmlspecialchars(trim($_POST['komentar']));

    if (empty($komentar)) {
        echo "<script>alert('Komentar wajib diisi.');</script>";
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO feedback (mahasiswa_id, komentar, tipe) VALUES (?, ?, ?)");
            if ($stmt->execute([$user_id, $komentar, 'prodi'])) {
                $stmt = $conn->prepare("INSERT INTO activity_log (user_id, activity) VALUES (?, ?)");
                $stmt->execute([$user_id, "Memberikan masukkan ke prodi: $komentar"]);
                echo "<script>alert('Masukkan untuk prodi berhasil dikirim!'); window.location.href='saran.php';</script>";
            } else {
                echo "<script>alert('Gagal mengirim masukkan ke prodi.');</script>";
            }
        } catch (PDOException $e) {
            echo "<script>alert('Terjadi kesalahan: " . addslashes($e->getMessage()) . "');</script>";
        }
    }
}

// Proses pengiriman masukkan ke wahana
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_wahana'])) {
    $nama_wahana = htmlspecialchars(trim($_POST['nama_wahana']));
    $komentar = htmlspecialchars(trim($_POST['komentar']));

    if (empty($nama_wahana) || empty($komentar)) {
        echo "<script>alert('Nama wahana dan komentar wajib diisi.');</script>";
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO feedback (mahasiswa_id, nama_wahana, komentar, tipe) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$user_id, $nama_wahana, $komentar, 'wahana'])) {
                $stmt = $conn->prepare("INSERT INTO activity_log (user_id, activity) VALUES (?, ?)");
                $stmt->execute([$user_id, "Memberikan masukkan ke wahana: $nama_wahana - $komentar"]);
                echo "<script>alert('Masukkan untuk wahana berhasil dikirim!'); window.location.href='saran.php';</script>";
            } else {
                echo "<script>alert('Gagal mengirim masukkan ke wahana.');</script>";
            }
        } catch (PDOException $e) {
            echo "<script>alert('Terjadi kesalahan: " . addslashes($e->getMessage()) . "');</script>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saran untuk Prodi dan Wahana - PSPPA Feedback System</title>
    <link rel="stylesheet" href="css/styles.css">
</head>

<body>
    <?php include 'navbar.php'; ?>
    <section class="dashboard-section">
        <div class="grid-2">
            <!-- Saran untuk Prodi -->
            <div class="card">
                <h3 class="card-title">Saran untuk Prodi</h3>
                <form method="POST" action="saran.php">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="form-group">
                        <label for="komentar_prodi">Komentar</label>
                        <textarea id="komentar_prodi" name="komentar" placeholder="Masukkan komentar untuk prodi" required></textarea>
                    </div>
                    <button type="submit" class="btn-primary" name="submit_prodi">Kirim Masukkan</button>
                </form>
            </div>
            <!-- Saran untuk Wahana -->
            <div class="card">
                <h3 class="card-title">Saran untuk Wahana</h3>
                <form method="POST" action="saran.php">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="form-group">
                        <label for="nama_wahana">Nama Wahana</label>
                        <input type="text" id="nama_wahana" name="nama_wahana" placeholder="Nama wahana" required>
                    </div>
                    <div class="form-group">
                        <label for="komentar_wahana">Komentar</label>
                        <textarea id="komentar_wahana" name="komentar" placeholder="Komentar untuk wahana" required></textarea>
                    </div>
                    <button type="submit" class="btn-primary" name="submit_wahana">Kirim Masukkan</button>
                </form>
            </div>
        </div>
    </section>
    <?php include 'footer.php'; ?>
    <script src="js/script.js"></script>
</body>

</html>