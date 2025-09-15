<?php
include 'config.php';

function sanitize($data)
{
    return htmlspecialchars(strip_tags(trim($data)));
}

// Handle tambah/edit/hapus
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'tambah' || $_POST['action'] == 'edit') {
            $nama = sanitize($_POST['nama']);
            $nim = sanitize($_POST['nim']);
            $angkatan = filter_input(INPUT_POST, 'angkatan', FILTER_VALIDATE_INT);
            if (empty($nama) || empty($nim) || !$angkatan) {
                echo "<script>alert('Semua kolom wajib diisi dengan data yang valid');</script>";
            } else {
                if ($_POST['action'] == 'tambah') {
                    $stmt = $conn->prepare("INSERT INTO mahasiswa (nama, nim, angkatan) VALUES (?, ?, ?)");
                    $stmt->execute([$nama, $nim, $angkatan]);
                } else {
                    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                    $stmt = $conn->prepare("UPDATE mahasiswa SET nama=?, nim=?, angkatan=? WHERE id=?");
                    $stmt->execute([$nama, $nim, $angkatan, $id]);
                }
                header('Location: mahasiswa.php');
                exit;
            }
        } elseif ($_POST['action'] == 'hapus') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $stmt = $conn->prepare("DELETE FROM mahasiswa WHERE id=?");
            $stmt->execute([$id]);
            header('Location: mahasiswa.php');
            exit;
        }
    }
}

// Ambil data untuk dropdown angkatan
$stmt = $conn->query("SELECT DISTINCT angkatan FROM semester_angkatan ORDER BY angkatan DESC");
$angkatan_list = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Ambil data untuk filter
$selected_angkatan = isset($_GET['angkatan']) && !empty($_GET['angkatan']) ? filter_input(INPUT_GET, 'angkatan', FILTER_VALIDATE_INT) : '';
$where_clause = '';
$params = [];
if ($selected_angkatan) {
    $where_clause = "WHERE angkatan = ?";
    $params[] = $selected_angkatan;
}

// Query untuk daftar mahasiswa
$sql = "SELECT * FROM mahasiswa $where_clause ORDER BY nama";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$mahasiswa_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil data untuk edit
$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM mahasiswa WHERE id=?");
    $stmt->execute([$_GET['edit']]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Mahasiswa</title>
    <link rel="stylesheet" href="style1.css">
    <script src="script1.js"></script>
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
    <h1>Manajemen Mahasiswa</h1>

    <a href="angkatan.php" class="action-btn edit-btn">Kelola Angkatan</a>

    <h2><?php echo $edit ? 'Edit Mahasiswa' : 'Tambah Mahasiswa'; ?></h2>
    <form method="POST" class="mahasiswa-form">
        <input type="hidden" name="action" value="<?php echo $edit ? 'edit' : 'tambah'; ?>">
        <?php if ($edit): ?>
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($edit['id']); ?>">
        <?php endif; ?>
        <div class="form-group">
            <label for="nama">Nama:</label>
            <input type="text" name="nama" id="nama" value="<?php echo $edit ? htmlspecialchars($edit['nama']) : ''; ?>" required aria-label="Nama Mahasiswa">
        </div>
        <div class="form-group">
            <label for="nim">NIM:</label>
            <input type="text" name="nim" id="nim" value="<?php echo $edit ? htmlspecialchars($edit['nim']) : ''; ?>" required aria-label="NIM Mahasiswa">
        </div>
        <div class="form-group">
            <label for="angkatan-form">Angkatan:</label>
            <select name="angkatan" id="angkatan-form" required aria-label="Angkatan Mahasiswa">
                <option value="">Pilih Angkatan</option>
                <?php foreach ($angkatan_list as $angk): ?>
                    <option value="<?php echo htmlspecialchars($angk); ?>" <?php echo ($edit && $edit['angkatan'] == $angk) ? 'selected' : ''; ?>><?php echo htmlspecialchars($angk); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit"><?php echo $edit ? 'Update' : 'Simpan'; ?></button>
    </form>

    <h2>Filter Mahasiswa</h2>
    <form method="GET" class="filter-form">
        <div class="form-group">
            <label for="angkatan-filter">Angkatan:</label>
            <select name="angkatan" id="angkatan-filter">
                <option value="">Semua Angkatan</option>
                <?php foreach ($angkatan_list as $angk): ?>
                    <option value="<?php echo htmlspecialchars($angk); ?>" <?php echo $angk == $selected_angkatan ? 'selected' : ''; ?>><?php echo htmlspecialchars($angk); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <button type="submit">Filter</button>
        </div>
    </form>

    <h2>Daftar Mahasiswa</h2>
    <?php if (empty($mahasiswa_list)): ?>
        <p>Tidak ada mahasiswa yang sesuai dengan filter.</p>
    <?php else: ?>
        <table class="mahasiswa-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nama</th>
                    <th>NIM</th>
                    <th>Angkatan</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($mahasiswa_list as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['id']); ?></td>
                        <td><?php echo htmlspecialchars($row['nama']); ?></td>
                        <td><?php echo htmlspecialchars($row['nim']); ?></td>
                        <td><?php echo htmlspecialchars($row['angkatan']); ?></td>
                        <td>
                            <a href="mahasiswa.php?edit=<?php echo htmlspecialchars($row['id']); ?>" class="action-btn edit-btn">Edit</a>
                            <form method="POST" class="delete-form">
                                <input type="hidden" name="action" value="hapus">
                                <input type="hidden" name="id" value="<?php echo htmlspecialchars($row['id']); ?>">
                                <button type="submit" class="action-btn delete-btn" onclick="return confirmDelete();">Hapus</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>

</html>