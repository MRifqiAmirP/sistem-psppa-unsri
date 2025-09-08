<?php
require_once 'auth.php';
redirectIfNotLoggedIn();
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ubah Kata Sandi</title>
    <link rel="stylesheet" href="css/styles.css">
</head>

<body>
    <?php include 'navbar.php'; ?>
    <section class="login-section">
        <div class="login-card">
            <h2 class="login-title">Ubah Kata Sandi</h2>
            <form method="POST" action="change_password.php">
                <div class="form-group">
                    <label for="current_password">Kata Sandi Saat Ini</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label for="new_password">Kata Sandi Baru</label>
                    <input type="password" id="new_password" name="new_password" required>
                </div>
                <button type="submit" class="btn-primary">Ubah Kata Sandi</button>
            </form>
        </div>
    </section>
    <?php include 'footer.php'; ?>
</body>

</html>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $user_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (password_verify($current_password, $user['password'])) {
        $new_password_hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        if ($stmt->execute([$new_password_hashed, $user_id])) {
            // Log aktivitas
            $stmt = $conn->prepare("INSERT INTO activity_log (user_id, activity) VALUES (?, ?)");
            $stmt->execute([$user_id, "Mengubah kata sandi"]);
            echo "<script>alert('Kata sandi berhasil diubah!'); window.location.href='dashboard.php';</script>";
        } else {
            echo "<script>alert('Gagal mengubah kata sandi.'); window.location.href='change_password.php';</script>";
        }
    } else {
        echo "<script>alert('Kata sandi saat ini salah.'); window.location.href='change_password.php';</script>";
    }
}
?>