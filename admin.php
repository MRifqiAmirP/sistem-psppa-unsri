<?php
require_once 'auth.php';
redirectIfNotRole('admin');

// Buat token CSRF jika belum ada
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fungsi untuk mengambil daftar pengguna
function getUsers($conn, $limit = 20, $offset = 0)
{
    $stmt = $conn->prepare("SELECT id, username, password, nama, role FROM users ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
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

// Fungsi untuk mengambil logbook
function getLogbook($conn, $limit = 20, $offset = 0)
{
    $stmt = $conn->prepare("SELECT l.komentar, l.tempat_wahana, l.created_at, u.nama 
                            FROM logbook l 
                            JOIN users u ON l.mahasiswa_id = u.id 
                            WHERE u.role = 'mahasiswa' 
                            ORDER BY l.created_at DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fungsi untuk mengambil feedback mahasiswa
function getMahasiswaFeedback($conn, $limit = 20, $offset = 0)
{
    $stmt = $conn->prepare("SELECT f.komentar, f.nilai, f.created_at, 
                                   COALESCE(u.nama, 'Tidak ada') AS preceptor_nama, 
                                   COALESCE(f.nama_mahasiswa, 'Tidak ada') AS mahasiswa_nama
                            FROM feedback f 
                            LEFT JOIN users u ON f.preceptor_id = u.id 
                            WHERE f.tipe = 'mahasiswa'
                            ORDER BY f.created_at DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fungsi untuk mengambil feedback prodi dan wahana
function getProdiWahanaFeedback($conn, $limit = 20, $offset = 0)
{
    $stmt = $conn->prepare("SELECT f.komentar, f.nama_wahana, f.tipe, f.created_at, 
                                   COALESCE(m.nama, 'Tidak ada') AS pengirim_nama
                            FROM feedback f 
                            LEFT JOIN users m ON f.mahasiswa_id = m.id 
                            WHERE f.tipe IN ('prodi', 'wahana')
                            ORDER BY f.created_at DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Proses hapus pengguna
if (isset($_GET['delete']) && isset($_GET['csrf_token']) && $_GET['csrf_token'] === $_SESSION['csrf_token']) {
    try {
        $user_id_to_delete = (int)$_GET['delete'];

        // Mulai transaksi
        $conn->beginTransaction();

        // Hapus entri terkait di activity_log
        $stmt = $conn->prepare("DELETE FROM activity_log WHERE user_id = ?");
        $stmt->execute([$user_id_to_delete]);

        // Hapus entri terkait di feedback (jika pengguna adalah preceptor atau mahasiswa)
        $stmt = $conn->prepare("DELETE FROM feedback WHERE preceptor_id = ? OR mahasiswa_id = ?");
        $stmt->execute([$user_id_to_delete, $user_id_to_delete]);

        // Hapus entri terkait di logbook (jika pengguna adalah mahasiswa)
        $stmt = $conn->prepare("DELETE FROM logbook WHERE mahasiswa_id = ?");
        $stmt->execute([$user_id_to_delete]);

        // Hapus pengguna dari tabel users
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
        $stmt->execute([$user_id_to_delete]);

        if ($stmt->rowCount() > 0) {
            // Catat aktivitas penghapusan
            $stmt = $conn->prepare("INSERT INTO activity_log (user_id, activity) VALUES (?, ?)");
            $stmt->execute([$_SESSION['user_id'], "Menghapus pengguna ID: " . $user_id_to_delete]);
            $conn->commit();
            echo "<script>alert('Pengguna berhasil dihapus!'); window.location.href='admin.php';</script>";
        } else {
            $conn->rollBack();
            echo "<script>alert('Gagal menghapus: Pengguna tidak ditemukan atau admin.'); window.location.href='admin.php';</script>";
        }
    } catch (PDOException $e) {
        $conn->rollBack();
        echo "<script>alert('Gagal menghapus pengguna: " . addslashes($e->getMessage()) . "'); window.location.href='admin.php';</script>";
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
            echo "<script>alert('Pengguna berhasil ditambahkan!'); window.location.href='admin.php';</script>";
        } else {
            echo "<script>alert('Gagal menambahkan pengguna.'); window.location.href='admin.php';</script>";
        }
    } catch (PDOException $e) {
        echo "<script>alert('Terjadi kesalahan: " . addslashes($e->getMessage()) . "'); window.location.href='admin.php';</script>";
    }
}

// Pengaturan paginasi
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20; // Limit untuk users, logbook, dan feedback
$limit_activity = 5; // Limit untuk activity log
$offset = ($page - 1) * $limit;
$offset_activity = ($page - 1) * $limit_activity;

?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - PSPPA Feedback System</title>
    <link rel="stylesheet" href="css/styles.css">
</head>

<body>
    <?php include 'navbar.php'; ?>
    <section class="dashboard-section">
        <h2 class="main-title">Dashboard Admin</h2>
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
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-primary">Tambah Pengguna</button>
                </form>
            </div>
            <!-- Daftar Pengguna -->
            <div class="card">
                <h3 class="card-title">Daftar Pengguna</h3>
                <?php
                $users = getUsers($conn, $limit, $offset);
                if ($users) {
                    echo "<table class='table-logbook'>";
                    echo "<tr><th>Nama</th><th>Username</th><th>Password</th><th>Role</th><th>Aksi</th></tr>";
                    foreach ($users as $user) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($user['nama']) . "</td>";
                        echo "<td>" . htmlspecialchars($user['username']) . "</td>";
                        echo "<td>" . htmlspecialchars($user['password']) . "</td>";
                        echo "<td>" . ucfirst($user['role']) . "</td>";
                        echo "<td><a href='admin.php?delete={$user['id']}&csrf_token=" . urlencode($_SESSION['csrf_token']) . "' onclick='return confirm(\"Yakin ingin menghapus pengguna ini?\")'>Hapus</a></td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                    // Paginasi untuk pengguna
                    $stmt = $conn->query("SELECT COUNT(*) FROM users");
                    $total_users = $stmt->fetchColumn();
                    $total_pages_users = ceil($total_users / $limit);
                    if ($total_pages_users > 1) {
                        echo "<div class='pagination'>";
                        if ($page > 1) {
                            echo "<a href='admin.php?page=" . ($page - 1) . "'>Sebelumnya</a>";
                        }
                        if ($page < $total_pages_users) {
                            echo "<a href='admin.php?page=" . ($page + 1) . "'>Berikutnya</a>";
                        }
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
                <?php
                $activities = getActivityLog($conn, $limit_activity, $offset_activity);
                if ($activities) {
                    foreach ($activities as $activity) {
                        echo "<div class='list-item'>";
                        echo "<p><strong>" . htmlspecialchars($activity['nama']) . ":</strong> " . htmlspecialchars($activity['activity']) . "</p>";
                        echo "<p><strong>Tanggal:</strong> {$activity['created_at']}</p>";
                        echo "</div>";
                    }
                    // Paginasi untuk log aktivitas
                    $stmt = $conn->query("SELECT COUNT(*) FROM activity_log");
                    $total_activity_logs = $stmt->fetchColumn();
                    $total_pages_activity = ceil($total_activity_logs / $limit_activity);
                    if ($total_pages_activity > 1) {
                        echo "<div class='pagination'>";
                        if ($page > 1) {
                            echo "<a href='admin.php?page=" . ($page - 1) . "'>Sebelumnya</a>";
                        }
                        if ($page < $total_pages_activity) {
                            echo "<a href='admin.php?page=" . ($page + 1) . "'>Berikutnya</a>";
                        }
                        echo "</div>";
                    }
                } else {
                    echo "<p>Belum ada aktivitas.</p>";
                }
                ?>
            </div>
            <!-- Daftar Logbook Mahasiswa -->
            <div class="card">
                <h3 class="card-title">Logbook Mahasiswa</h3>
                <?php
                $logbooks = getLogbook($conn, $limit, $offset);
                if ($logbooks) {
                    echo "<table class='table-logbook'>";
                    echo "<tr><th>Nama Mahasiswa</th><th>Tempat Wahana</th><th>Link Logbook</th><th>Tanggal</th></tr>";
                    foreach ($logbooks as $logbook) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($logbook['nama']) . "</td>";
                        echo "<td>" . htmlspecialchars($logbook['tempat_wahana']) . "</td>";
                        echo "<td><a href='" . htmlspecialchars($logbook['komentar']) . "' target='_blank'>Lihat Logbook</a></td>";
                        echo "<td>{$logbook['created_at']}</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                    // Paginasi untuk logbook
                    $stmt = $conn->query("SELECT COUNT(*) FROM logbook");
                    $total_logbook_logs = $stmt->fetchColumn();
                    $total_pages_logbook = ceil($total_logbook_logs / $limit);
                    if ($total_pages_logbook > 1) {
                        echo "<div class='pagination'>";
                        if ($page > 1) {
                            echo "<a href='admin.php?page=" . ($page - 1) . "'>Sebelumnya</a>";
                        }
                        if ($page < $total_pages_logbook) {
                            echo "<a href='admin.php?page=" . ($page + 1) . "'>Berikutnya</a>";
                        }
                        echo "</div>";
                    }
                } else {
                    echo "<p>Belum ada logbook yang diunggah.</p>";
                }
                ?>
            </div>
            <!-- Daftar Feedback Mahasiswa -->
            <div class="card">
                <h3 class="card-title">Feedback untuk Mahasiswa</h3>
                <div class="export-btn">
                    <a href="export_feedback.php?tipe=mahasiswa" class="btn btn-success">Export Feedback Mahasiswa ke Excel</a>
                </div>
                <?php
                $feedbacks = getMahasiswaFeedback($conn, $limit, $offset);
                if ($feedbacks) {
                    echo "<table class='table-logbook'>";
                    echo "<tr><th>Pengirim (Preceptor)</th><th>Penerima (Mahasiswa)</th><th>Komentar</th><th>Nilai</th><th>Tanggal</th></tr>";
                    foreach ($feedbacks as $feedback) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($feedback['preceptor_nama']) . "</td>";
                        echo "<td>" . htmlspecialchars($feedback['mahasiswa_nama']) . "</td>";
                        echo "<td>" . htmlspecialchars($feedback['komentar']) . "</td>";
                        echo "<td>" . ($feedback['nilai'] ? htmlspecialchars($feedback['nilai']) : '-') . "</td>";
                        echo "<td>{$feedback['created_at']}</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                    // Paginasi untuk feedback mahasiswa
                    $stmt = $conn->query("SELECT COUNT(*) FROM feedback WHERE tipe = 'mahasiswa'");
                    $total_feedback = $stmt->fetchColumn();
                    $total_pages_feedback = ceil($total_feedback / $limit);
                    if ($total_pages_feedback > 1) {
                        echo "<div class='pagination'>";
                        if ($page > 1) {
                            echo "<a href='admin.php?page=" . ($page - 1) . "'>Sebelumnya</a>";
                        }
                        if ($page < $total_pages_feedback) {
                            echo "<a href='admin.php?page=" . ($page + 1) . "'>Berikutnya</a>";
                        }
                        echo "</div>";
                    }
                } else {
                    echo "<p>Belum ada feedback untuk mahasiswa.</p>";
                }
                ?>
            </div>
            <!-- Daftar Feedback Prodi dan Wahana -->
            <div class="card">
                <h3 class="card-title">Feedback untuk Prodi dan Wahana</h3>
                <div class="export-btn">
                    <a href="export_feedback.php?tipe=all" class="btn btn-success">Export Semua Feedback ke Excel</a>
                </div>
                <?php
                $prodi_wahana_feedbacks = getProdiWahanaFeedback($conn, $limit, $offset);
                if ($prodi_wahana_feedbacks) {
                    echo "<table class='table-logbook'>";
                    echo "<tr><th>Pengirim (Mahasiswa)</th><th>Tipe</th><th>Nama Wahana</th><th>Komentar</th><th>Tanggal</th></tr>";
                    foreach ($prodi_wahana_feedbacks as $feedback) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($feedback['pengirim_nama']) . "</td>";
                        echo "<td>" . ucfirst($feedback['tipe']) . "</td>";
                        echo "<td>" . ($feedback['nama_wahana'] ? htmlspecialchars($feedback['nama_wahana']) : '-') . "</td>";
                        echo "<td>" . htmlspecialchars($feedback['komentar']) . "</td>";
                        echo "<td>{$feedback['created_at']}</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                    // Paginasi untuk feedback prodi dan wahana
                    $stmt = $conn->query("SELECT COUNT(*) FROM feedback WHERE tipe IN ('prodi', 'wahana')");
                    $total_prodi_wahana_feedback = $stmt->fetchColumn();
                    $total_pages_prodi_wahana = ceil($total_prodi_wahana_feedback / $limit);
                    if ($total_pages_prodi_wahana > 1) {
                        echo "<div class='pagination'>";
                        if ($page > 1) {
                            echo "<a href='admin.php?page=" . ($page - 1) . "'>Sebelumnya</a>";
                        }
                        if ($page < $total_pages_prodi_wahana) {
                            echo "<a href='admin.php?page=" . ($page + 1) . "'>Berikutnya</a>";
                        }
                        echo "</div>";
                    }
                } else {
                    echo "<p>Belum ada feedback untuk prodi atau wahana.</p>";
                }
                ?>
            </div>
        </div>
    </section>
    <?php include 'footer.php'; ?>
    <script src="js/script.js"></script>
</body>

</html>