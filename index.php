<?php
session_start();
require_once 'config.php';

// Redirect jika sudah login
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'mahasiswa') {
        header('Location: logbook.php');
    } elseif ($_SESSION['role'] === 'preceptor') {
        header('Location: feedback.php');
    } elseif ($_SESSION['role'] === 'admin') {
        header('Location: admin_users.php');
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = htmlspecialchars(trim($_POST['username']));
    $password = $_POST['password'];

    try {
        $stmt = $conn->prepare("SELECT id, username, password, role, nama FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && $password === $user['password']) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['nama'] = $user['nama'];

            // Log aktivitas
            $stmt = $conn->prepare("INSERT INTO activity_log (user_id, activity) VALUES (?, ?)");
            $stmt->execute([$user['id'], "Login"]);

            if ($user['role'] === 'mahasiswa') {
                header('Location: logbook.php');
            } elseif ($user['role'] === 'preceptor') {
                header('Location: feedback.php');
            } elseif ($user['role'] === 'admin') {
                header('Location: admin_users.php');
            }
            exit;
        } else {
            echo "<script>alert('Username atau password salah.');</script>";
        }
    } catch (PDOException $e) {
        echo "<script>alert('Terjadi kesalahan: " . addslashes($e->getMessage()) . "'); window.location.href='index.php';</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback Perseptor PSPPA</title>
    <link rel="stylesheet" href="css/styles.css">
</head>

<body>
    <?php include 'navbar.php'; ?>
    <section class="login-section">
        <div class="login-card">
            <h2 class="login-title">Login</h2>
            <form id="login-form" method="POST" action="index.php">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="btn-primary">Login</button>
                <p class="text-sm">Lupa password? <a href="https://wa.me/6282183469229" target="_blank">Hubungi Admin</a></p>
            </form>
        </div>
    </section>
    <?php include 'footer.php'; ?>
    <script src="js/script.js"></script>
</body>

</html>