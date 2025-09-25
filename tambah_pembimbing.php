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
            $nama = sanitize($_POST['nama']);
            $tipe = in_array($_POST['tipe'], ['dosen', 'preceptor']) ? $_POST['tipe'] : 'dosen';
            $id_tempat = $tipe == 'preceptor' ? filter_input(INPUT_POST, 'id_tempat', FILTER_VALIDATE_INT) : null;

            if (empty($nama)) {
                echo "<script>alert('Nama harus diisi');</script>";
            } elseif ($tipe == 'preceptor' && !$id_tempat) {
                echo "<script>alert('Tempat wahana harus diisi untuk preceptor');</script>";
            } else {
                try {
                    if ($_POST['action'] == 'tambah') {
                        $stmt = $pdo->prepare("INSERT INTO dosen_pembimbing (nama, tipe, id_tempat) VALUES (?, ?, ?)");
                        $stmt->execute([$nama, $tipe, $id_tempat]);
                        echo "<script>alert('Pembimbing berhasil ditambahkan');</script>";
                    } else {
                        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                        $stmt = $pdo->prepare("UPDATE dosen_pembimbing SET nama=?, tipe=?, id_tempat=? WHERE id=?");
                        $stmt->execute([$nama, $tipe, $id_tempat, $id]);
                        echo "<script>alert('Pembimbing berhasil diperbarui');</script>";
                    }
                    header('Location: tambah_pembimbing.php');
                    exit;
                } catch (PDOException $e) {
                    echo "<script>alert('Error: " . addslashes($e->getMessage()) . "');</script>";
                }
            }
        } elseif ($_POST['action'] == 'hapus') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            try {
                $stmt = $pdo->prepare("DELETE FROM dosen_pembimbing WHERE id=?");
                $stmt->execute([$id]);
                echo "<script>alert('Pembimbing berhasil dihapus');</script>";
                header('Location: tambah_pembimbing.php');
                exit;
            } catch (PDOException $e) {
                echo "<script>alert('Error: " . addslashes($e->getMessage()) . "');</script>";
            }
        }
    }
}

// Ambil data pembimbing
try {
    $stmt = $pdo->query("SELECT d.id, d.nama, d.tipe, t.nama_tempat 
                         FROM dosen_pembimbing d 
                         LEFT JOIN tempat t ON d.id_tempat = t.id 
                         ORDER BY d.nama");
    $pembimbing_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "<script>alert('Error mengambil data pembimbing: " . addslashes($e->getMessage()) . "');</script>";
    $pembimbing_list = [];
}

// Ambil data tempat untuk dropdown
try {
    $stmt = $pdo->query("SELECT id, nama_tempat FROM tempat ORDER BY nama_tempat");
    $tempat_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "<script>alert('Error mengambil data tempat: " . addslashes($e->getMessage()) . "');</script>";
    $tempat_list = [];
}

// Ambil data untuk edit
$edit = null;
if (isset($_GET['edit'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM dosen_pembimbing WHERE id=?");
        $stmt->execute([$_GET['edit']]);
        $edit = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo "<script>alert('Error mengambil data edit: " . addslashes($e->getMessage()) . "');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Manajemen Pembimbing</title>
    <link rel="stylesheet" href="style1.css">
    <script src="script1.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const tipeSelect = document.getElementById('tipe');
            const tempatSelect = document.getElementById('id_tempat');
            if (tipeSelect && tempatSelect) {
                function toggleTempat() {
                    tempatSelect.disabled = tipeSelect.value !== 'preceptor';
                    tempatSelect.required = tipeSelect.value === 'preceptor';
                }
                tipeSelect.addEventListener('change', toggleTempat);
                toggleTempat();
            }

            const form = document.querySelector('.pembimbing-form');
            if (form) {
                form.addEventListener('submit', (e) => {
                    const nama = form.querySelector('#nama').value;
                    const tipe = form.querySelector('#tipe').value;
                    const idTempat = form.querySelector('#id_tempat').value;

                    if (!nama) {
                        alert('Nama harus diisi');
                        e.preventDefault();
                        return;
                    }
                    if (tipe === 'preceptor' && !idTempat) {
                        alert('Tempat wahana harus diisi untuk preceptor');
                        e.preventDefault();
                        return;
                    }
                });
            }
        });
    </script>
</head>

<body>
    <header>
        <a href="beranda.php">Dashboard</a>
        <a href="mahasiswa1.php">Mahasiswa</a>
        <a href="tempat.php">Tempat</a>
        <a href="jadwal.php">Jadwal</a>
        <a href="tambah_pembimbing.php">Pembimbing</a>
        <a href="tambah_jadwal.php">Tambah Jadwal</a>
        <a href="logout.php" class="logout-btn">Logout</a>
    </header>
    <h1>Manajemen Pembimbing</h1>

    <h2>Tambah/Edit Pembimbing</h2>
    <form method="POST" class="pembimbing-form">
        <input type="hidden" name="action" value="<?php echo $edit ? 'edit' : 'tambah'; ?>">
        <?php if ($edit): ?>
            <input type="hidden" name="id" value="<?php echo $edit['id']; ?>">
        <?php endif; ?>
        <div class="form-group">
            <label for="nama">Nama:</label>
            <input type="text" name="nama" id="nama" value="<?php echo $edit ? htmlspecialchars($edit['nama']) : ''; ?>" required>
        </div>
        <div class="form-group">
            <label for="tipe">Tipe:</label>
            <select name="tipe" id="tipe" required>
                <option value="dosen" <?php echo $edit && $edit['tipe'] == 'dosen' ? 'selected' : ''; ?>>Dosen Pembimbing</option>
                <option value="preceptor" <?php echo $edit && $edit['tipe'] == 'preceptor' ? 'selected' : ''; ?>>Preceptor</option>
            </select>
        </div>
        <div class="form-group">
            <label for="id_tempat">Tempat Wahana:</label>
            <select name="id_tempat" id="id_tempat">
                <option value="">Pilih Tempat (Opsional)</option>
                <?php foreach ($tempat_list as $tempat): ?>
                    <option value="<?php echo htmlspecialchars($tempat['id']); ?>" <?php echo $edit && $edit['id_tempat'] == $tempat['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($tempat['nama_tempat']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit"><?php echo $edit ? 'Simpan Perubahan' : 'Tambah Pembimbing'; ?></button>
        <?php if ($edit): ?>
            <button type="button" onclick="window.location.href='tambah_pembimbing.php'">Batal</button>
        <?php endif; ?>
    </form>

    <h2>Daftar Pembimbing</h2>
    <?php if (empty($pembimbing_list)): ?>
        <p>Tidak ada pembimbing yang tersedia.</p>
    <?php else: ?>
        <table>
            <tr>
                <th>No</th>
                <th>Nama</th>
                <th>Tipe</th>
                <th>Tempat Wahana</th>
                <th>Aksi</th>
            </tr>
            <?php
            $no = 1;
            foreach ($pembimbing_list as $row):
            ?>
                <tr>
                    <td><?php echo $no++; ?></td>
                    <td><?php echo htmlspecialchars($row['nama']); ?></td>
                    <td><?php echo htmlspecialchars(ucfirst($row['tipe'])); ?></td>
                    <td><?php echo htmlspecialchars($row['nama_tempat'] ?? 'Tidak ada'); ?></td>
                    <td>
                        <a href="tambah_pembimbing.php?edit=<?php echo $row['id']; ?>" class="action-btn edit-btn">Edit</a> |
                        <form method="POST" style="display:inline;" class="delete-form">
                            <input type="hidden" name="action" value="hapus">
                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                            <button type="submit" class="action-btn delete-btn" onclick="return confirm('Apakah Anda yakin ingin menghapus pembimbing ini?');">Hapus</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</body>

</html>