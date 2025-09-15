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
            $id_mahasiswa = filter_input(INPUT_POST, 'id_mahasiswa', FILTER_VALIDATE_INT);
            $id_tempat = filter_input(INPUT_POST, 'id_tempat', FILTER_VALIDATE_INT);
            $id_dosen_pembimbing = filter_input(INPUT_POST, 'id_dosen_pembimbing', FILTER_VALIDATE_INT) ?: null;
            $tanggal_mulai = sanitize($_POST['tanggal_mulai']);
            $tanggal_selesai = sanitize($_POST['tanggal_selesai']);
            if (!$id_mahasiswa || !$id_tempat || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_mulai) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_selesai)) {
                echo "<script>alert('Input tidak valid');</script>";
            } elseif ($tanggal_mulai > $tanggal_selesai) {
                echo "<script>alert('Tanggal mulai harus sebelum atau sama dengan tanggal selesai');</script>";
            } else {
                if ($_POST['action'] == 'tambah') {
                    $stmt = $conn->prepare("INSERT INTO jadwal (id_mahasiswa, id_tempat, id_dosen_pembimbing, tanggal_mulai, tanggal_selesai) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$id_mahasiswa, $id_tempat, $id_dosen_pembimbing, $tanggal_mulai, $tanggal_selesai]);
                } else {
                    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                    $stmt = $conn->prepare("UPDATE jadwal SET id_mahasiswa=?, id_tempat=?, id_dosen_pembimbing=?, tanggal_mulai=?, tanggal_selesai=? WHERE id=?");
                    $stmt->execute([$id_mahasiswa, $id_tempat, $id_dosen_pembimbing, $tanggal_mulai, $tanggal_selesai, $id]);
                }
                header('Location: jadwal.php');
                exit;
            }
        } elseif ($_POST['action'] == 'hapus') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $stmt = $conn->prepare("DELETE FROM jadwal WHERE id=?");
            $stmt->execute([$id]);
            header('Location: jadwal.php');
            exit;
        }
    }
}

// Ambil data untuk filter
$stmt = $conn->query("SELECT DISTINCT angkatan FROM mahasiswa ORDER BY angkatan DESC");
$angkatan_list = $stmt->fetchAll(PDO::FETCH_COLUMN);
$stmt = $conn->query("SELECT DISTINCT jenis_tempat FROM tempat ORDER BY jenis_tempat");
$jenis_tempat_list = $stmt->fetchAll(PDO::FETCH_COLUMN);
$stmt = $conn->query("SELECT id, nama_tempat FROM tempat ORDER BY nama_tempat");
$tempat_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt = $conn->query("SELECT id, nama FROM dosen_pembimbing ORDER BY nama");
$dosen_pembimbing_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil rentang tanggal unik dari tabel jadwal
$stmt = $conn->query("SELECT DISTINCT tanggal_mulai, tanggal_selesai FROM jadwal ORDER BY tanggal_mulai");
$date_ranges = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filter
$where_clauses = [];
$params = [];
$selected_angkatan = isset($_GET['angkatan']) && !empty($_GET['angkatan']) ? sanitize($_GET['angkatan']) : '';
$selected_jenis = isset($_GET['jenis']) && !empty($_GET['jenis']) ? sanitize($_GET['jenis']) : '';
$selected_tempat = isset($_GET['tempat']) && !empty($_GET['tempat']) ? filter_input(INPUT_GET, 'tempat', FILTER_VALIDATE_INT) : '';
$selected_semester = isset($_GET['semester']) && in_array($_GET['semester'], ['1', '2']) ? $_GET['semester'] : '';
$selected_date_range = isset($_GET['date_range']) && !empty($_GET['date_range']) ? sanitize($_GET['date_range']) : '';
$selected_tanggal_mulai = '';
$selected_tanggal_selesai = '';

if ($selected_date_range) {
    $dates = explode('|', $selected_date_range);
    if (count($dates) === 2 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dates[0]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dates[1])) {
        $selected_tanggal_mulai = $dates[0];
        $selected_tanggal_selesai = $dates[1];
        if ($selected_tanggal_mulai > $selected_tanggal_selesai) {
            echo "<script>alert('Tanggal mulai tidak boleh setelah tanggal selesai');</script>";
            $selected_date_range = '';
            $selected_tanggal_mulai = '';
            $selected_tanggal_selesai = '';
        } else {
            $where_clauses[] = "j.tanggal_mulai <= ? AND j.tanggal_selesai >= ?";
            $params[] = $selected_tanggal_selesai;
            $params[] = $selected_tanggal_mulai;
        }
    } else {
        echo "<script>alert('Format rentang tanggal tidak valid');</script>";
        $selected_date_range = '';
    }
}

if ($selected_angkatan) {
    $where_clauses[] = "m.angkatan = ?";
    $params[] = $selected_angkatan;
}
if ($selected_jenis) {
    $where_clauses[] = "t.jenis_tempat = ?";
    $params[] = $selected_jenis;
}
if ($selected_tempat) {
    $where_clauses[] = "j.id_tempat = ?";
    $params[] = $selected_tempat;
}
if ($selected_semester) {
    $month_range = $selected_semester == '1' ? "MONTH(j.tanggal_mulai) BETWEEN 1 AND 6" : "MONTH(j.tanggal_mulai) BETWEEN 7 AND 12";
    $where_clauses[] = $month_range;
}

// Query untuk daftar jadwal
$sql = "SELECT j.id, m.nama AS mahasiswa, t.nama_tempat AS tempat, t.jenis_tempat, d.nama AS dosen_pembimbing, j.tanggal_mulai, j.tanggal_selesai 
        FROM jadwal j 
        JOIN mahasiswa m ON j.id_mahasiswa = m.id 
        JOIN tempat t ON j.id_tempat = t.id 
        LEFT JOIN dosen_pembimbing d ON j.id_dosen_pembimbing = d.id";
if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}
$sql .= " ORDER BY j.tanggal_mulai, m.nama";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$jadwal_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil data untuk edit
$edit = null;
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM jadwal WHERE id=?");
    $stmt->execute([$_GET['edit']]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Jadwal</title>
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
    <h1>Manajemen Jadwal</h1>

    <h2>Filter Jadwal</h2>
    <form method="GET" class="filter-form">
        <div class="form-group">
            <label for="angkatan">Angkatan:</label>
            <select name="angkatan" id="angkatan">
                <option value="">Semua Angkatan</option>
                <?php foreach ($angkatan_list as $angk): ?>
                    <option value="<?php echo htmlspecialchars($angk); ?>" <?php echo $angk == $selected_angkatan ? 'selected' : ''; ?>><?php echo htmlspecialchars($angk); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
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
            <label for="tempat">Tempat:</label>
            <select name="tempat" id="tempat">
                <option value="">Semua Tempat</option>
                <?php foreach ($tempat_list as $tempat): ?>
                    <option value="<?php echo htmlspecialchars($tempat['id']); ?>" <?php echo $tempat['id'] == $selected_tempat ? 'selected' : ''; ?>><?php echo htmlspecialchars($tempat['nama_tempat']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="semester">Semester:</label>
            <select name="semester" id="semester">
                <option value="">Semua Semester</option>
                <option value="1" <?php echo $selected_semester == '1' ? 'selected' : ''; ?>>Semester 1 (Jan-Jun)</option>
                <option value="2" <?php echo $selected_semester == '2' ? 'selected' : ''; ?>>Semester 2 (Jul-Des)</option>
            </select>
        </div>
        <div class="form-group">
            <label for="date_range">Rentang Tanggal:</label>
            <select name="date_range" id="date_range">
                <option value="">Semua Rentang Tanggal</option>
                <?php foreach ($date_ranges as $range): ?>
                    <?php $range_value = htmlspecialchars($range['tanggal_mulai'] . '|' . $range['tanggal_selesai']); ?>
                    <option value="<?php echo $range_value; ?>" <?php echo $range_value == $selected_date_range ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($range['tanggal_mulai'] . ' s/d ' . $range['tanggal_selesai']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <button type="submit">Filter</button>
            <button type="button" onclick="downloadCSV()" class="action-btn">Download CSV</button>
        </div>
    </form>

    <h2>Daftar Jadwal</h2>
    <?php if (empty($jadwal_list)): ?>
        <p>Tidak ada jadwal yang sesuai dengan filter.</p>
    <?php else: ?>
        <table>
            <tr>
                <th>No</th>
                <th>Mahasiswa</th>
                <th>Tempat</th>
                <th>Jenis Tempat</th>
                <th>Dosen Pembimbing</th>
                <th>Mulai</th>
                <th>Selesai</th>
                <th>Aksi</th>
            </tr>
            <?php
            $no = 1;
            foreach ($jadwal_list as $row):
            ?>
                <tr>
                    <td><?php echo $no++; ?></td>
                    <td><?php echo htmlspecialchars($row['mahasiswa']); ?></td>
                    <td><?php echo htmlspecialchars($row['tempat']); ?></td>
                    <td><?php echo htmlspecialchars($row['jenis_tempat']); ?></td>
                    <td><?php echo htmlspecialchars($row['dosen_pembimbing'] ?? 'Tidak ada'); ?></td>
                    <td><?php echo htmlspecialchars($row['tanggal_mulai']); ?></td>
                    <td><?php echo htmlspecialchars($row['tanggal_selesai']); ?></td>
                    <td>
                        <a href="jadwal.php?edit=<?php echo $row['id']; ?>" class="action-btn edit-btn">Edit</a> |
                        <form method="POST" style="display:inline;" class="delete-form">
                            <input type="hidden" name="action" value="hapus">
                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                            <button type="submit" class="action-btn delete-btn" onclick="return confirmDelete();">Hapus</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <?php if ($edit): ?>
        <h2>Edit Jadwal</h2>
        <form method="POST" class="mahasiswa-form">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" value="<?php echo $edit['id']; ?>">
            <div class="form-group">
                <label for="id_mahasiswa">Mahasiswa:</label>
                <select name="id_mahasiswa" id="id_mahasiswa" required>
                    <?php
                    $stmt = $conn->query("SELECT id, nama FROM mahasiswa");
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $selected = ($edit['id_mahasiswa'] == $row['id']) ? 'selected' : '';
                        echo "<option value='{$row['id']}' $selected>" . htmlspecialchars($row['nama']) . "</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label for="id_tempat">Tempat:</label>
                <select name="id_tempat" id="id_tempat" required>
                    <?php
                    $stmt = $conn->query("SELECT id, nama_tempat FROM tempat");
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $selected = ($edit['id_tempat'] == $row['id']) ? 'selected' : '';
                        echo "<option value='{$row['id']}' $selected>" . htmlspecialchars($row['nama_tempat']) . "</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label for="id_dosen_pembimbing">Dosen Pembimbing:</label>
                <select name="id_dosen_pembimbing" id="id_dosen_pembimbing">
                    <option value="">Tidak ada</option>
                    <?php
                    $stmt = $conn->query("SELECT id, nama FROM dosen_pembimbing");
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $selected = ($edit['id_dosen_pembimbing'] == $row['id']) ? 'selected' : '';
                        echo "<option value='{$row['id']}' $selected>" . htmlspecialchars($row['nama']) . "</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label for="tanggal_mulai">Tanggal Mulai:</label>
                <input type="date" name="tanggal_mulai" id="tanggal_mulai" value="<?php echo htmlspecialchars($edit['tanggal_mulai']); ?>" required>
            </div>
            <div class="form-group">
                <label for="tanggal_selesai">Tanggal Selesai:</label>
                <input type="date" name="tanggal_selesai" id="tanggal_selesai" value="<?php echo htmlspecialchars($edit['tanggal_selesai']); ?>" required>
            </div>
            <button type="submit">Simpan Perubahan</button>
        </form>
    <?php endif; ?>

    <script>
        function downloadCSV() {
            const url = new URL(window.location.href);
            url.pathname = url.pathname.replace('jadwal.php', 'export_jadwal.php');
            window.location.href = url.toString();
        }
    </script>
</body>

</html>