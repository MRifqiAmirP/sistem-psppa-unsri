<?php
include 'db.php';

function sanitize($data)
{
    return htmlspecialchars(strip_tags(trim($data)));
}

// Handle tambah/edit/hapus angkatan
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'tambah' || $_POST['action'] == 'edit') {
            $angkatan = filter_input(INPUT_POST, 'angkatan', FILTER_VALIDATE_INT);
            $semester = sanitize($_POST['semester']);
            $bulan_mulai = filter_input(INPUT_POST, 'bulan_mulai', FILTER_VALIDATE_INT);
            $bulan_selesai = filter_input(INPUT_POST, 'bulan_selesai', FILTER_VALIDATE_INT);
            $tahun_mulai = filter_input(INPUT_POST, 'tahun_mulai', FILTER_VALIDATE_INT);
            $tahun_selesai = filter_input(INPUT_POST, 'tahun_selesai', FILTER_VALIDATE_INT);

            if (!$angkatan || !$semester || !$bulan_mulai || !$bulan_selesai || !$tahun_mulai || !$tahun_selesai) {
                echo "<script>alert('Semua kolom wajib diisi dengan data yang valid');</script>";
            } elseif (!in_array($semester, ['ganjil', 'genap'])) {
                echo "<script>alert('Semester harus ganjil atau genap');</script>";
            } elseif ($bulan_mulai < 1 || $bulan_mulai > 12 || $bulan_selesai < 1 || $bulan_selesai > 12) {
                echo "<script>alert('Bulan harus antara 1 dan 12');</script>";
            } elseif ($tahun_mulai > $tahun_selesai || ($tahun_mulai == $tahun_selesai && $bulan_mulai > $bulan_selesai)) {
                echo "<script>alert('Tanggal mulai harus sebelum atau sama dengan tanggal selesai');</script>";
            } else {
                if ($_POST['action'] == 'tambah') {
                    $stmt = $pdo->prepare("INSERT INTO semester_angkatan (angkatan, semester, bulan_mulai, bulan_selesai, tahun_mulai, tahun_selesai) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$angkatan, $semester, $bulan_mulai, $bulan_selesai, $tahun_mulai, $tahun_selesai]);
                } else {
                    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                    $stmt = $pdo->prepare("UPDATE semester_angkatan SET angkatan=?, semester=?, bulan_mulai=?, bulan_selesai=?, tahun_mulai=?, tahun_selesai=? WHERE id=?");
                    $stmt->execute([$angkatan, $semester, $bulan_mulai, $bulan_selesai, $tahun_mulai, $tahun_selesai, $id]);
                }
                header('Location: angkatan.php');
                exit;
            }
        } elseif ($_POST['action'] == 'hapus') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $stmt = $pdo->prepare("DELETE FROM semester_angkatan WHERE id=?");
            $stmt->execute([$id]);
            header('Location: angkatan.php');
            exit;
        }
    }
}

// Ambil data untuk filter
$stmt = $pdo->query("SELECT DISTINCT angkatan FROM semester_angkatan ORDER BY angkatan DESC");
$angkatan_list = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Filter
$selected_angkatan = isset($_GET['angkatan']) && !empty($_GET['angkatan']) ? filter_input(INPUT_GET, 'angkatan', FILTER_VALIDATE_INT) : '';
$where_clause = '';
$params = [];
if ($selected_angkatan) {
    $where_clause = "WHERE angkatan = ?";
    $params[] = $selected_angkatan;
}

// Query untuk daftar semester angkatan
$sql = "SELECT * FROM semester_angkatan $where_clause ORDER BY angkatan DESC, semester";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$semester_angkatan_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil data untuk edit
$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM semester_angkatan WHERE id=?");
    $stmt->execute([$_GET['edit']]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Angkatan</title>
    <link rel="stylesheet" href="style1.css">
    <script src="script1.js"></script>
</head>

<body>
    <?php include 'header.php'; ?>
    <h1>Manajemen Angkatan</h1>

    <a href="mahasiswa.php" class="action-btn edit-btn">Kembali</a>

    <h2><?php echo $edit ? 'Edit Angkatan' : 'Tambah Angkatan'; ?></h2>
    <form method="POST" class="mahasiswa-form">
        <input type="hidden" name="action" value="<?php echo $edit ? 'edit' : 'tambah'; ?>">
        <?php if ($edit): ?>
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($edit['id']); ?>">
        <?php endif; ?>
        <div class="form-group-container">
            <div class="form-group">
                <label for="angkatan">Angkatan:</label>
                <input type="number" name="angkatan" id="angkatan" value="<?php echo $edit ? htmlspecialchars($edit['angkatan']) : ''; ?>" required aria-label="Angkatan">
            </div>
            <div class="form-group">
                <label for="semester">Semester:</label>
                <select name="semester" id="semester" required aria-label="Semester">
                    <option value="">Pilih Semester</option>
                    <option value="ganjil" <?php echo ($edit && $edit['semester'] == 'ganjil') ? 'selected' : ''; ?>>Ganjil</option>
                    <option value="genap" <?php echo ($edit && $edit['semester'] == 'genap') ? 'selected' : ''; ?>>Genap</option>
                </select>
            </div>
            <div class="form-group">
                <label for="bulan_mulai">Bulan Mulai:</label>
                <input type="number" name="bulan_mulai" id="bulan_mulai" min="1" max="12" value="<?php echo $edit ? htmlspecialchars($edit['bulan_mulai']) : ''; ?>" required aria-label="Bulan Mulai">
            </div>
            <div class="form-group">
                <label for="bulan_selesai">Bulan Selesai:</label>
                <input type="number" name="bulan_selesai" id="bulan_selesai" min="1" max="12" value="<?php echo $edit ? htmlspecialchars($edit['bulan_selesai']) : ''; ?>" required aria-label="Bulan Selesai">
            </div>
            <div class="form-group">
                <label for="tahun_mulai">Tahun Mulai:</label>
                <input type="number" name="tahun_mulai" id="tahun_mulai" value="<?php echo $edit ? htmlspecialchars($edit['tahun_mulai']) : ''; ?>" required aria-label="Tahun Mulai">
            </div>
            <div class="form-group">
                <label for="tahun_selesai">Tahun Selesai:</label>
                <input type="number" name="tahun_selesai" id="tahun_selesai" value="<?php echo $edit ? htmlspecialchars($edit['tahun_selesai']) : ''; ?>" required aria-label="Tahun Selesai">
            </div>
        </div>
        <button type="submit"><?php echo $edit ? 'Update' : 'Simpan'; ?></button>
    </form>

    <h2>Filter Angkatan</h2>
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

    <h2>Daftar Angkatan</h2>
    <?php if (empty($semester_angkatan_list)): ?>
        <p>Tidak ada data angkatan yang sesuai dengan filter.</p>
    <?php else: ?>
        <table class="mahasiswa-table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Angkatan</th>
                    <th>Semester</th>
                    <th>Bulan Mulai</th>
                    <th>Bulan Selesai</th>
                    <th>Tahun Mulai</th>
                    <th>Tahun Selesai</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; ?>
                <?php foreach ($semester_angkatan_list as $row): ?>
                    <tr>
                        <td><?php echo $no++; ?></td>
                        <td><?php echo htmlspecialchars($row['angkatan']); ?></td>
                        <td><?php echo htmlspecialchars($row['semester']); ?></td>
                        <td><?php echo htmlspecialchars($row['bulan_mulai']); ?></td>
                        <td><?php echo htmlspecialchars($row['bulan_selesai']); ?></td>
                        <td><?php echo htmlspecialchars($row['tahun_mulai']); ?></td>
                        <td><?php echo htmlspecialchars($row['tahun_selesai']); ?></td>
                        <td>
                            <a href="angkatan.php?edit=<?php echo htmlspecialchars($row['id']); ?>" class="action-btn edit-btn">Edit</a>
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