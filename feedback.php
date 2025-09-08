<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'auth.php';
redirectIfNotRole('preceptor');

// Buat CSRF token jika belum ada
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Tanggal saat ini
date_default_timezone_set('Asia/Jakarta');
$today = date('h:i A WIB, l, d F Y');
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulir Feedback</title>
    <link rel="stylesheet" href="css/styles.css">
</head>

<body>
    <?php include 'navbar.php'; ?>
    <section class="feedback-section">
        <h2 class="main-title">Formulir Feedback</h2>
        <div class="grid-2">
            <!-- Feedback Mahasiswa -->
            <div class="feedback-card">
                <h3 class="card-title">Feedback Mahasiswa</h3>
                <form id="feedback-mahasiswa-form" method="POST" action="submit_feedback.php">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="tipe" value="mahasiswa">
                    <div class="form-group">
                        <label for="nama_mahasiswa">Nama Mahasiswa</label>
                        <input type="text" id="nama_mahasiswa" name="nama_mahasiswa" required>
                    </div>
                    <div class="form-group">
                        <label for="komentar_mahasiswa">Komentar</label>
                        <textarea id="komentar_mahasiswa" name="komentar" rows="5" required></textarea>
                    </div>
                    <button type="submit" class="btn-primary">Kirim Feedback</button>
                </form>
            </div>
            <!-- Feedback ke Prodi -->
            <div class="feedback-card">
                <h3 class="card-title">Feedback untuk Prodi</h3>
                <form id="feedback-prodi-form" method="POST" action="submit_feedback.php">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="tipe" value="prodi">
                    <div class="form-group">
                        <label for="komentar_prodi">Komentar</label>
                        <textarea id="komentar_prodi" name="komentar" rows="5" required></textarea>
                    </div>
                    <button type="submit" class="btn-primary">Kirim Feedback</button>
                </form>
            </div>
        </div>
    </section>
    <?php include 'footer.php'; ?>
    <script src="js/script.js"></script>
</body>

</html>