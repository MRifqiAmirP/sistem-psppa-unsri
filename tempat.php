<?php
include 'db.php';

function sanitize($data)
{
    return htmlspecialchars(strip_tags(trim($data)));
}

// Handle tambah/edit/hapus
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'tambah' || $_POST['action'] == 'edit') {
            $nama_tempat = sanitize($_POST['nama_tempat']);
            $jenis_tempat = sanitize($_POST['jenis_tempat']);
            if (empty($nama_tempat) || empty($jenis_tempat)) {
                echo "<script>alert('Nama tempat dan jenis tempat tidak boleh kosong');</script>";
            } else {
                if ($_POST['action'] == 'tambah') {
                    $stmt = $pdo->prepare("INSERT INTO tempat (nama_tempat, jenis_tempat) VALUES (?, ?)");
                    $stmt->execute([$nama_tempat, $jenis_tempat]);
                } else {
                    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                    $stmt = $pdo->prepare("UPDATE tempat SET nama_tempat=?, jenis_tempat=? WHERE id=?");
                    $stmt->execute([$nama_tempat, $jenis_tempat, $id]);
                }
                header('Location: tempat.php');
                exit;
            }
        } elseif ($_POST['action'] == 'hapus') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $stmt = $pdo->prepare("DELETE FROM tempat WHERE id=?");
            $stmt->execute([$id]);
            header('Location: tempat.php');
            exit;
        }
    }
}

// Ambil data untuk dropdown jenis tempat
$stmt = $pdo->query("SELECT DISTINCT jenis_tempat FROM tempat ORDER BY jenis_tempat");
$jenis_tempat_list = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Ambil data untuk filter
$selected_jenis = isset($_GET['jenis']) && !empty($_GET['jenis']) ? sanitize($_GET['jenis']) : '';
$where_clause = '';
$params = [];
if ($selected_jenis) {
    $where_clause = "WHERE jenis_tempat = ?";
    $params[] = $selected_jenis;
}

// Query untuk daftar tempat
$sql = "SELECT * FROM tempat $where_clause ORDER BY nama_tempat";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tempat_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil data untuk edit
$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM tempat WHERE id=?");
    $stmt->execute([$_GET['edit']]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Tempat</title>
    <link rel="stylesheet" href="style1.css">
    <script src="script1.js"></script>
</head>

<body>
    <?php include 'header.php'; ?>
    <h1>Manajemen Tempat</h1>

    <h2><?php echo $edit ? 'Edit Tempat' : 'Tambah Tempat'; ?></h2>
    <form method="POST" class="mahasiswa-form">
        <input type="hidden" name="action" value="<?php echo $edit ? 'edit' : 'tambah'; ?>">
        <?php if ($edit): ?>
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($edit['id']); ?>">
        <?php endif; ?>
        <div class="form-group-container">
            <div class="form-group">
                <label for="nama_tempat">Nama Tempat:</label>
                <input type="text" name="nama_tempat" id="nama_tempat" value="<?php echo $edit ? htmlspecialchars($edit['nama_tempat']) : ''; ?>" required aria-label="Nama Tempat">
            </div>
            <div class="form-group">
                <label for="jenis_tempat">Jenis Tempat:</label>
                <select name="jenis_tempat" id="jenis_tempat" required aria-label="Jenis Tempat">
                    <option value="">Pilih Jenis Tempat</option>
                    <?php foreach ($jenis_tempat_list as $jenis): ?>
                        <option value="<?php echo htmlspecialchars($jenis); ?>" <?php echo ($edit && $edit['jenis_tempat'] == $jenis) ? 'selected' : ''; ?>><?php echo htmlspecialchars($jenis); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <button type="submit"><?php echo $edit ? 'Update' : 'Simpan'; ?></button>
    </form>

    <h2>Filter Tempat</h2>
    <form method="GET" class="filter-form">
        <div class="form-group">
            <label for="jenis">Jenis Tempat:</label>
            <select name="jenis" id="jenis">
                <option value="">Semua Jenis</option>
                <?php foreach ($jenis_tempat_list as $jenis): ?>
                    <option value="<?php echo htmlspecialchars($jenis); ?>" <?php echo $jenis == $selected_jenis ? 'selected' : ''; ?>><?php echo htmlspecialchars($jenis); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <button type="submit">Filter</button>
        </div>
    </form>

    <h2>Daftar Tempat</h2>
    <?php if (empty($tempat_list)): ?>
        <p>Tidak ada tempat yang sesuai dengan filter.</p>
    <?php else: ?>
        <table class="mahasiswa-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama Tempat</th>
                    <th>Jenis</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; ?>
                <?php foreach ($tempat_list as $row): ?>
                    <tr>
                        <td><?php echo $no++; ?></td>
                        <td><?php echo htmlspecialchars($row['nama_tempat']); ?></td>
                        <td><?php echo htmlspecialchars($row['jenis_tempat']); ?></td>
                        <td>
                            <a href="tempat.php?edit=<?php echo htmlspecialchars($row['id']); ?>" class="action-btn edit-btn">Edit</a>
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