<?php
require_once 'auth.php';
redirectIfNotRole('mahasiswa');

// Buat CSRF token jika belum ada
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_id = $_SESSION['user_id'];

// Proses pengiriman logbook
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_logbook'])) {
    $tempat_wahana = htmlspecialchars(trim($_POST['tempat_wahana']));
    $komentar = htmlspecialchars(trim($_POST['komentar']));

    if (empty($tempat_wahana) || empty($komentar)) {
        echo "<script>alert('Tempat wahana dan komentar wajib diisi.');</script>";
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO logbook (mahasiswa_id, tempat_wahana, komentar) VALUES (?, ?, ?)");
            if ($stmt->execute([$user_id, $tempat_wahana, $komentar])) {
                $stmt = $conn->prepare("INSERT INTO activity_log (user_id, activity) VALUES (?, ?)");
                $stmt->execute([$user_id, "Mengunggah logbook: $tempat_wahana - $komentar"]);
                echo "<script>alert('Logbook berhasil diunggah!'); window.location.href='logbook.php';</script>";
            } else {
                echo "<script>alert('Gagal mengunggah logbook.');</script>";
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
    <title>Unggah Logbook - PSPPA Feedback System</title>
    <link rel="stylesheet" href="css/styles.css">
</head>

<body>
    <?php include 'navbar.php'; ?>
    <section class="dashboard-section">
        <div class="card">
            <h3 class="card-title">Unggah Logbook</h3>
            <form method="POST" action="logbook.php">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="form-group">
                    <label for="tempat_wahana">Tempat Wahana</label>
                    <input type="text" id="tempat_wahana" name="tempat_wahana" placeholder="Masukkan tempat wahana" required>
                </div>
                <div class="form-group">
                    <label for="komentar">Link Google Drive</label>
                    <textarea id="komentar" name="komentar" placeholder="Masukkan komentar Anda" required></textarea>
                </div>
                <button type="submit" class="btn-primary" name="submit_logbook">Unggah</button>
            </form>
            <h3 class="card-title" style="margin-top: 1.5rem;">Riwayat Logbook</h3>
            <?php
            $stmt = $conn->prepare("SELECT id, tempat_wahana, komentar, created_at FROM logbook WHERE mahasiswa_id = ? ORDER BY created_at DESC");
            $stmt->execute([$user_id]);
            $logbooks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($logbooks) {
                echo '<div class="list-item"><strong>Tempat Wahana | Komentar | Tanggal</strong></div>';
                foreach ($logbooks as $logbook) {
                    echo "<div class='list-item'>";
                    echo "<p>{$logbook['tempat_wahana']} | {$logbook['komentar']} | {$logbook['created_at']}</p>";
                    echo "</div>";
                }
            } else {
                echo "<p>Belum ada logbook.</p>";
            }
            ?>
        </div>
    </section>
    <?php include 'footer.php'; ?>
    <script src="js/script.js"></script>
</body>

</html>