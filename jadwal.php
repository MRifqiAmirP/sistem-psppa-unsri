<?php
include 'config.php';

function sanitize($data)
{
    return htmlspecialchars(strip_tags(trim($data)));
}

// Handle get_preceptors for AJAX
if (isset($_GET['get_preceptors']) && filter_var($_GET['id_tempat'], FILTER_VALIDATE_INT)) {
    header('Content-Type: application/json');
    $stmt = $pdo->prepare("SELECT id, nama FROM dosen_pembimbing WHERE tipe = 'preceptor' AND id_tempat = ? ORDER BY nama");
    $stmt->execute([$_GET['id_tempat']]);
    $preceptor_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($preceptor_list);
    exit;
}

// Handle tambah/edit/hapus
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'tambah' || $_POST['action'] == 'edit') {
            $id_mahasiswa = filter_input(INPUT_POST, 'id_mahasiswa', FILTER_VALIDATE_INT);
            $id_tempat = filter_input(INPUT_POST, 'id_tempat', FILTER_VALIDATE_INT);
            $id_dosen_pembimbing = filter_input(INPUT_POST, 'id_dosen_pembimbing', FILTER_VALIDATE_INT) ?: null;
            $id_preceptor1 = filter_input(INPUT_POST, 'id_preceptor1', FILTER_VALIDATE_INT) ?: null;
            $id_preceptor2 = filter_input(INPUT_POST, 'id_preceptor2', FILTER_VALIDATE_INT) ?: null;
            $tanggal_mulai = sanitize($_POST['tanggal_mulai']);
            $tanggal_selesai = sanitize($_POST['tanggal_selesai']);
            if (!$id_mahasiswa || !$id_tempat || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_mulai) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal_selesai)) {
                echo "<script>alert('Input tidak valid');</script>";
            } elseif ($tanggal_mulai > $tanggal_selesai) {
                echo "<script>alert('Tanggal mulai harus sebelum atau sama dengan tanggal selesai');</script>";
            } else {
                if ($_POST['action'] == 'edit') {
                    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                    $stmt = $pdo->prepare("SELECT * FROM jadwal WHERE id_mahasiswa = ? AND id != ? AND (
                        (tanggal_mulai <= ? AND tanggal_selesai >= ?) OR
                        (tanggal_mulai <= ? AND tanggal_selesai >= ?) OR
                        (tanggal_mulai >= ? AND tanggal_selesai <= ?)
                    )");
                    $stmt->execute([$id_mahasiswa, $id, $tanggal_selesai, $tanggal_mulai, $tanggal_mulai, $tanggal_selesai, $tanggal_mulai, $tanggal_selesai]);
                    if ($stmt->fetch()) {
                        echo "<script>alert('Konflik jadwal terdeteksi: Jadwal tumpang tindih untuk mahasiswa ini');</script>";
                    } else {
                        $stmt = $pdo->prepare("UPDATE jadwal SET id_mahasiswa=?, id_tempat=?, id_dosen_pembimbing=?, id_preceptor1=?, id_preceptor2=?, tanggal_mulai=?, tanggal_selesai=? WHERE id=?");
                        $stmt->execute([$id_mahasiswa, $id_tempat, $id_dosen_pembimbing, $id_preceptor1, $id_preceptor2, $tanggal_mulai, $tanggal_selesai, $id]);
                        echo "<script>alert('Jadwal berhasil diperbarui'); window.location.href='jadwal.php';</script>";
                        exit;
                    }
                } else {
                    $stmt = $pdo->prepare("INSERT INTO jadwal (id_mahasiswa, id_tempat, id_dosen_pembimbing, id_preceptor1, id_preceptor2, tanggal_mulai, tanggal_selesai) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$id_mahasiswa, $id_tempat, $id_dosen_pembimbing, $id_preceptor1, $id_preceptor2, $tanggal_mulai, $tanggal_selesai]);
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
$stmt = $conn->query("SELECT d.id, d.nama, d.tipe, t.nama_tempat 
                     FROM dosen_pembimbing d 
                     LEFT JOIN tempat t ON d.id_tempat = t.id 
                     ORDER BY d.nama");
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
$sql = "SELECT j.id, m.nama AS mahasiswa, t.nama_tempat AS tempat, t.jenis_tempat, 
        dp.nama AS dosen_pembimbing, 
        CONCAT(COALESCE(p1.nama, ''), IF(COALESCE(p2.nama, '') != '', CONCAT(', ', p2.nama), '')) AS preceptor, 
        j.tanggal_mulai, j.tanggal_selesai 
        FROM jadwal j 
        JOIN mahasiswa m ON j.id_mahasiswa = m.id 
        JOIN tempat t ON j.id_tempat = t.id 
        LEFT JOIN dosen_pembimbing dp ON j.id_dosen_pembimbing = dp.id AND dp.tipe = 'dosen'
        LEFT JOIN dosen_pembimbing p1 ON j.id_preceptor1 = p1.id AND p1.tipe = 'preceptor'
        LEFT JOIN dosen_pembimbing p2 ON j.id_preceptor2 = p2.id AND p2.tipe = 'preceptor'";
if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}
$sql .= " ORDER BY j.tanggal_mulai, m.nama";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$jadwal_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil data untuk edit
$edit = null;
$preceptor_list_edit = [];
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM jadwal WHERE id=?");
    $stmt->execute([$_GET['edit']]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($edit) {
        $stmt = $pdo->prepare("SELECT id, nama FROM dosen_pembimbing WHERE tipe = 'preceptor' AND id_tempat = ? ORDER BY nama");
        $stmt->execute([$edit['id_tempat']]);
        $preceptor_list_edit = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Jadwal</title>
    <link rel="stylesheet" href="style1.css">
    <script src="script1.js"></script>
    <script>
        function validateEditForm() {
            const form = document.querySelector('.mahasiswa-form');
            if (!form) return;

            form.addEventListener('submit', (e) => {
                const tanggalMulai = form.querySelector('#tanggal_mulai').value;
                const tanggalSelesai = form.querySelector('#tanggal_selesai').value;
                const idMahasiswa = form.querySelector('#id_mahasiswa').value;
                const idTempat = form.querySelector('#id_tempat').value;

                if (!idMahasiswa || isNaN(idMahasiswa)) {
                    alert('Pilih mahasiswa terlebih dahulu');
                    e.preventDefault();
                    return;
                }
                if (!idTempat || isNaN(idTempat)) {
                    alert('Pilih tempat terlebih dahulu');
                    e.preventDefault();
                    return;
                }
                if (!tanggalMulai || !tanggalSelesai) {
                    alert('Tanggal mulai dan selesai harus diisi');
                    e.preventDefault();
                    return;
                }
                if (tanggalMulai > tanggalSelesai) {
                    alert('Tanggal mulai tidak boleh setelah tanggal selesai');
                    e.preventDefault();
                    return;
                }
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            validateEditForm();
            const tempatSelect = document.getElementById('id_tempat');
            if (tempatSelect) {
                tempatSelect.addEventListener('change', () => {
                    const idTempat = tempatSelect.value;
                    if (idTempat) {
                        fetch(`jadwal.php?id_tempat=${idTempat}&get_preceptors=1`)
                            .then(response => response.json())
                            .then(data => {
                                const preceptor1 = document.getElementById('id_preceptor1');
                                const preceptor2 = document.getElementById('id_preceptor2');
                                preceptor1.innerHTML = '<option value="">Tidak ada</option>';
                                preceptor2.innerHTML = '<option value="">Tidak ada</option>';
                                data.forEach(p => {
                                    const opt1 = document.createElement('option');
                                    opt1.value = p.id;
                                    opt1.textContent = p.nama;
                                    preceptor1.appendChild(opt1);
                                    const opt2 = opt1.cloneNode(true);
                                    preceptor2.appendChild(opt2);
                                });
                            })
                            .catch(error => {
                                console.error('Error fetching preceptors:', error);
                                alert('Gagal memuat preceptor.');
                            });
                    }
                });
            }
        });
    </script>
</head>

<body>
    <?php include 'header.php'; ?>
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
                <th>Preceptor</th>
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
                    <td><?php echo htmlspecialchars($row['preceptor'] ? trim($row['preceptor'], ', ') : 'Tidak ada'); ?></td>
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
                    foreach ($dosen_pembimbing_list as $row) {
                        if ($row['tipe'] != 'dosen') continue;
                        $selected = ($edit['id_dosen_pembimbing'] == $row['id']) ? 'selected' : '';
                        echo "<option value='{$row['id']}' $selected>" . htmlspecialchars($row['nama']) . "</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label for="id_preceptor1">Preceptor 1:</label>
                <select name="id_preceptor1" id="id_preceptor1">
                    <option value="">Tidak ada</option>
                    <?php
                    foreach ($preceptor_list_edit as $row) {
                        $selected = ($edit['id_preceptor1'] == $row['id']) ? 'selected' : '';
                        echo "<option value='{$row['id']}' $selected>" . htmlspecialchars($row['nama']) . "</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label for="id_preceptor2">Preceptor 2:</label>
                <select name="id_preceptor2" id="id_preceptor2">
                    <option value="">Tidak ada</option>
                    <?php
                    foreach ($preceptor_list_edit as $row) {
                        $selected = ($edit['id_preceptor2'] == $row['id']) ? 'selected' : '';
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
            <button type="button" onclick="window.location.href='jadwal.php'">Batal</button>
        </form>
    <?php endif; ?>

    <script>
        function downloadCSV() {
            const url = new URL(window.location.href);
            url.pathname = url.pathname.replace('jadwal.php', 'export_jadwal.php');
            window.location.href = url.toString();
        }

        function confirmDelete() {
            return confirm("Apakah Anda yakin ingin menghapus jadwal ini?");
        }
    </script>
</body>

</html>