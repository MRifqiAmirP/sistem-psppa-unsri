<?php
require_once 'config.php';
session_start();

// Cek login
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Buat CSRF token jika belum ada
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_id = $_SESSION['user_id'];
$message = "";

// Ambil username sekarang
$stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = "<span style='color:red;'>CSRF token tidak valid.</span>";
    } else {
        $new_username = trim($_POST['username']);
        $old_password = $_POST['old_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        // Cek password lama
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && $old_password === $row['password']) {
            if ($new_password === $confirm_password) {
                if (strlen($new_password) >= 8) {
                    // Update username & password (tanpa hash)
                    $update = $conn->prepare("UPDATE users SET username = ?, password = ? WHERE id = ?");
                    if ($update->execute([$new_username, $new_password, $user_id])) {
                        $_SESSION['username'] = $new_username;
                        // Catat aktivitas
                        $stmt = $conn->prepare("INSERT INTO activity_log (user_id, activity) VALUES (?, ?)");
                        $stmt->execute([$user_id, "Mengubah username/password"]);
                        $message = "<span style='color:green;'>Username dan Password berhasil diubah!</span>";
                    } else {
                        $message = "<span style='color:red;'>Gagal mengupdate akun.</span>";
                    }
                } else {
                    $message = "<span style='color:red;'>Password minimal 8 karakter!</span>";
                }
            } else {
                $message = "<span style='color:red;'>Password baru dan konfirmasi tidak sama!</span>";
            }
        } else {
            $message = "<span style='color:red;'>Password lama salah!</span>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ubah Username & Password</title>
    <link rel="stylesheet" href="css/styles.css">
</head>

<body>
    <?php include 'navbar.php'; ?>
    <section class="change-password-section">
        <div class="card">
            <h2 class="card-title">Ubah Username dan Password</h2>
            <p><?php echo $message; ?></p>
            <form id="change-password-form" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="form-group">
                    <label for="username">Username Baru</label>
                    <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="old_password">Password Lama</label>
                    <input type="password" id="old_password" name="old_password" required>
                </div>
                <div class="form-group">
                    <label for="new_password">Password Baru</label>
                    <input type="password" id="new_password" name="new_password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Konfirmasi Password Baru</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit" class="btn-primary">Update</button>
            </form>
            <a href="index.php" class="btn-secondary">Kembali</a>
        </div>
    </section>
    <?php include 'footer.php'; ?>
    <script src="js/script.js"></script>
</body>

</html>