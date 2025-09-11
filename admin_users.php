<?php
require_once 'auth.php';
redirectIfNotRole('admin');

// Buat token CSRF jika belum ada
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fungsi untuk mengambil daftar pengguna dengan filter role
function getUsers($conn, $role_filter = 'all', $limit = 20, $offset = 0)
{
    $query = "SELECT id, username, password, nama, role FROM users";
    if ($role_filter !== 'all') {
        $query .= " WHERE role = :role";
    }
    $query .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";

    $stmt = $conn->prepare($query);
    if ($role_filter !== 'all') {
        $stmt->bindValue(':role', $role_filter, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fungsi untuk mengambil log aktivitas
function getActivityLog($conn, $limit = 5, $offset = 0)
{
    $stmt = $conn->prepare("SELECT a.activity, a.created_at, u.nama 
                            FROM activity_log a 
                            JOIN users u ON a.user_id = u.id 
                            ORDER BY a.created_at DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Proses hapus pengguna
if (isset($_GET['delete_user']) && isset($_GET['csrf_token']) && $_GET['csrf_token'] === $_SESSION['csrf_token']) {
    try {
        $user_id_to_delete = (int)$_GET['delete_user'];
        $conn->beginTransaction();
        $stmt = $conn->prepare("DELETE FROM activity_log WHERE user_id = ?");
        $stmt->execute([$user_id_to_delete]);
        $stmt = $conn->prepare("DELETE FROM feedback WHERE preceptor_id = ? OR mahasiswa_id = ?");
        $stmt->execute([$user_id_to_delete, $user_id_to_delete]);
        $stmt = $conn->prepare("DELETE FROM logbook WHERE mahasiswa_id = ?");
        $stmt->execute([$user_id_to_delete]);
        $stmt = $conn->prepare("DELETE FROM penilaian WHERE nim IN (SELECT username FROM users WHERE id = ?)");
        $stmt->execute([$user_id_to_delete]);
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
        $stmt->execute([$user_id_to_delete]);
        if ($stmt->rowCount() > 0) {
            $stmt = $conn->prepare("INSERT INTO activity_log (user_id, activity) VALUES (?, ?)");
            $stmt->execute([$_SESSION['user_id'], "Menghapus pengguna ID: " . $user_id_to_delete]);
            $conn->commit();
            echo "<script>alert('Pengguna berhasil dihapus!'); window.location.href='admin_users.php';</script>";
        } else {
            $conn->rollBack();
            echo "<script>alert('Gagal menghapus: Pengguna tidak ditemukan atau admin.'); window.location.href='admin_users.php';</script>";
        }
    } catch (PDOException $e) {
        $conn->rollBack();
        echo "<script>alert('Gagal menghapus pengguna: " . addslashes($e->getMessage()) . "'); window.location.href='admin_users.php';</script>";
    }
}

// Proses hapus semua log aktivitas
if (isset($_GET['delete_all_activity']) && isset($_GET['csrf_token']) && $_GET['csrf_token'] === $_SESSION['csrf_token']) {
    try {
        $conn->beginTransaction();
        $stmt = $conn->prepare("DELETE FROM activity_log");
        $stmt->execute();
        $stmt = $conn->prepare("INSERT INTO activity_log (user_id, activity) VALUES (?, ?)");
        $stmt->execute([$_SESSION['user_id'], "Menghapus semua riwayat log aktivitas"]);
        $conn->commit();
        echo "<script>alert('Semua log aktivitas berhasil dihapus!'); window.location.href='admin_users.php';</script>";
    } catch (PDOException $e) {
        $conn->rollBack();
        echo "<script>alert('Gagal menghapus log aktivitas: " . addslashes($e->getMessage()) . "'); window.location.href='admin_users.php';</script>";
    }
}

// Proses tambah pengguna
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    $username = htmlspecialchars(trim($_POST['username']));
    $password = $_POST['password'];
    $nama = htmlspecialchars(trim($_POST['nama']));
    $role = $_POST['role'];
    try {
        $stmt = $conn->prepare("INSERT INTO users (username, password, nama, role) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$username, $password, $nama, $role])) {
            $stmt = $conn->prepare("INSERT INTO activity_log (user_id, activity) VALUES (?, ?)");
            $stmt->execute([$_SESSION['user_id'], "Menambah pengguna: $username"]);
            echo "<script>alert('Pengguna berhasil ditambahkan!'); window.location.href='admin_users.php';</script>";
        } else {
            echo "<script>alert('Gagal menambahkan pengguna.'); window.location.href='admin_users.php';</script>";
        }
    } catch (PDOException $e) {
        echo "<script>alert('Terjadi kesalahan: " . addslashes($e->getMessage()) . "'); window.location.href='admin_users.php';</script>";
    }
}

// Pengaturan paginasi
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$limit_activity = 5;
$offset = ($page - 1) * $limit;
$offset_activity = ($page - 1) * $limit_activity;

// Tangkap filter role
$role_filter = isset($_GET['role']) ? $_GET['role'] : 'all';
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pengguna - PSPPA Feedback System</title>
    <link rel="stylesheet" href="css/styles.css">
</head>

<body>
    <?php include 'navbar.php'; ?>
    <section class="dashboard-section">
        <div class="grid-2">
            <!-- Form Tambah Pengguna -->
            <div class="card">
                <h3 class="card-title">Tambah Pengguna Baru</h3>
                <form id="user-form" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="add_user" value="1">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <div class="form-group">
                        <label for="nama">Nama</label>
                        <input type="text" id="nama" name="nama" required>
                    </div>
                    <div class="form-group">
                        <label for="role">Role</label>
                        <select id="role" name="role" required>
                            <option value="mahasiswa">Mahasiswa</option>
                            <option value="preceptor">Preceptor</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-primary">Tambah</button>
                </form>
            </div>
            <!-- Daftar Pengguna -->
            <div class="card">
                <h3 class="card-title">Daftar Pengguna</h3>
                <!-- Filter Role -->
                <form method="GET" class="form-group">
                    <label for="role_filter">Filter Role</label>
                    <select id="role_filter" name="role" onchange="this.form.submit()">
                        <option value="all" <?php echo $role_filter === 'all' ? 'selected' : ''; ?>>Semua</option>
                        <option value="mahasiswa" <?php echo $role_filter === 'mahasiswa' ? 'selected' : ''; ?>>Mahasiswa</option>
                        <option value="preceptor" <?php echo $role_filter === 'preceptor' ? 'selected' : ''; ?>>Preceptor</option>
                        <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </form>
                <?php
                $users = getUsers($conn, $role_filter, $limit, $offset);
                if ($users) {
                    echo "<table class='table-logbook'>";
                    echo "<tr><th>Username</th><th>Password</th><th>Nama</th><th>Role</th><th>Aksi</th></tr>";
                    foreach ($users as $user) {
                        echo "<tr>";
                        echo "<td data-label='Username'>" . htmlspecialchars($user['username']) . "</td>";
                        echo "<td data-label='Password'>" . htmlspecialchars($user['password']) . "</td>";
                        echo "<td data-label='Nama'>" . htmlspecialchars($user['nama']) . "</td>";
                        echo "<td data-label='Role'>" . ucfirst($user['role']) . "</td>";
                        echo "<td data-label='Aksi'><a href='admin_users.php?delete_user={$user['id']}&csrf_token=" . urlencode($_SESSION['csrf_token']) . "' class='btn-remove' onclick='return confirm(\"Yakin ingin menghapus pengguna ini?\")'>Hapus</a></td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                    $query_count = $role_filter === 'all' ? "SELECT COUNT(*) FROM users" : "SELECT COUNT(*) FROM users WHERE role = :role";
                    $stmt = $conn->prepare($query_count);
                    if ($role_filter !== 'all') {
                        $stmt->bindValue(':role', $role_filter, PDO::PARAM_STR);
                    }
                    $stmt->execute();
                    $total_users = $stmt->fetchColumn();
                    $total_pages_users = ceil($total_users / $limit);
                    if ($total_pages_users > 1) {
                        echo "<div class='pagination'>";
                        if ($page > 1) echo "<a href='admin_users.php?page=" . ($page - 1) . "&role=" . urlencode($role_filter) . "'>Sebelumnya</a>";
                        if ($page < $total_pages_users) echo "<a href='admin_users.php?page=" . ($page + 1) . "&role=" . urlencode($role_filter) . "'>Berikutnya</a>";
                        echo "</div>";
                    }
                } else {
                    echo "<p>Belum ada pengguna.</p>";
                }
                ?>
            </div>
            <!-- Log Aktivitas -->
            <div class="card">
                <h3 class="card-title">Log Aktivitas</h3>
                <div class="export-btn">
                    <a href="admin_users.php?delete_all_activity=1&csrf_token=<?php echo urlencode($_SESSION['csrf_token']); ?>" class="btn-remove" onclick="return confirm('Yakin ingin menghapus semua riwayat log aktivitas?')">Hapus Semua Log</a>
                </div>
                <?php
                $activities = getActivityLog($conn, $limit_activity, $offset_activity);
                if ($activities) {
                    foreach ($activities as $activity) {
                        echo "<div class='list-item'>";
                        echo "<p><strong>" . htmlspecialchars($activity['nama']) . ":</strong> " . htmlspecialchars($activity['activity']) . "</p>";
                        echo "<p><strong>Tanggal:</strong> {$activity['created_at']}</p>";
                        echo "</div>";
                    }
                    $stmt = $conn->query("SELECT COUNT(*) FROM activity_log");
                    $total_activity_logs = $stmt->fetchColumn();
                    $total_pages_activity = ceil($total_activity_logs / $limit_activity);
                    if ($total_pages_activity > 1) {
                        echo "<div class='pagination'>";
                        if ($page > 1) echo "<a href='admin_users.php?page=" . ($page - 1) . "&role=" . urlencode($role_filter) . "'>Sebelumnya</a>";
                        if ($page < $total_pages_activity) echo "<a href='admin_users.php?page=" . ($page + 1) . "&role=" . urlencode($role_filter) . "'>Berikutnya</a>";
                        echo "</div>";
                    }
                } else {
                    echo "<p>Belum ada aktivitas.</p>";
                }
                ?>
            </div>
        </div>
    </section>
    <?php include 'footer.php'; ?>
    <script src="js/script.js"></script>
</body>

</html>