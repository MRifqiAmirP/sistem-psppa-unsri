<?php
require_once 'auth.php';
redirectIfNotRole('admin');

// Buat token CSRF jika belum ada
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fungsi untuk mengkategorikan tempat PKPA atau wahana
function getPkaCategory($tempat_pka)
{
    $lowerTempat = strtolower($tempat_pka);
    if (preg_match('/rumah sakit|rs|rsu|rsud|hospital/i', $lowerTempat)) return "Rumah Sakit";
    if (preg_match('/apotek|apotik|pharmacy/i', $lowerTempat)) return "Apotek";
    if (preg_match('/puskesmas|pusat kesehatan|pusat kesehatan masyarakat/i', $lowerTempat)) return "Puskesmas";
    if (preg_match('/pedagang besar|pbf|distributor farmasi|pharma distributor/i', $lowerTempat)) return "Pedagang Besar Farmasi";
    if (preg_match('/industri|industry|manufaktur/i', $lowerTempat)) return "Industri";
    if (preg_match('/bpom|badan pengawas obat|badan pom|pengawas obat/i', $lowerTempat)) return "BPOM";
    if (preg_match('/dinas kesehatan|dinkes|departemen kesehatan|kesehatan daerah/i', $lowerTempat)) return "Dinas Kesehatan";
    return "Lainnya";
}

// Fungsi untuk mengambil logbook dengan filter tempat
function getLogbook($conn, $place_filter = 'all', $limit = 20, $offset = 0)
{
    $query = "SELECT l.id, l.komentar, l.tempat_wahana, l.created_at, u.nama 
              FROM logbook l 
              JOIN users u ON l.mahasiswa_id = u.id 
              WHERE u.role = 'mahasiswa'";
    if ($place_filter !== 'all') {
        $query .= " AND :place_filter = (CASE 
            WHEN l.tempat_wahana REGEXP 'rumah sakit|rs|rsu|rsud|hospital' THEN 'Rumah Sakit'
            WHEN l.tempat_wahana REGEXP 'apotek|apotik|pharmacy' THEN 'Apotek'
            WHEN l.tempat_wahana REGEXP 'puskesmas|pusat kesehatan|pusat kesehatan masyarakat' THEN 'Puskesmas'
            WHEN l.tempat_wahana REGEXP 'pedagang besar|pbf|distributor farmasi|pharma distributor' THEN 'Pedagang Besar Farmasi'
            WHEN l.tempat_wahana REGEXP 'industri|industry|manufaktur' THEN 'Industri'
            WHEN l.tempat_wahana REGEXP 'bpom|badan pengawas obat|badan pom|pengawas obat' THEN 'BPOM'
            WHEN l.tempat_wahana REGEXP 'dinas kesehatan|dinkes|departemen kesehatan|kesehatan daerah' THEN 'Dinas Kesehatan'
            ELSE 'Lainnya' END)";
    }
    $query .= " ORDER BY l.created_at DESC LIMIT :limit OFFSET :offset";

    $stmt = $conn->prepare($query);
    if ($place_filter !== 'all') {
        $stmt->bindValue(':place_filter', $place_filter, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fungsi untuk mengambil feedback mahasiswa
function getMahasiswaFeedback($conn, $limit = 20, $offset = 0)
{
    $stmt = $conn->prepare("SELECT f.id, f.komentar, f.nilai, f.created_at, 
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

// Fungsi untuk mengambil feedback prodi dan wahana dengan filter tipe
function getProdiWahanaFeedback($conn, $type_filter = 'all', $limit = 20, $offset = 0)
{
    $query = "SELECT f.id, f.komentar, f.nama_wahana, f.tipe, f.created_at, 
                     COALESCE(m.nama, u.nama, 'Tidak ada') AS pengirim_nama
              FROM feedback f 
              LEFT JOIN users m ON f.mahasiswa_id = m.id 
              LEFT JOIN users u ON f.preceptor_id = u.id 
              WHERE f.tipe IN ('prodi', 'wahana')";
    if ($type_filter !== 'all') {
        $query .= " AND f.tipe = :type_filter";
    }
    $query .= " ORDER BY f.created_at DESC LIMIT :limit OFFSET :offset";

    $stmt = $conn->prepare($query);
    if ($type_filter !== 'all') {
        $stmt->bindValue(':type_filter', $type_filter, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fungsi untuk mengambil rekapan penilaian dengan filter tempat
function getPenilaian($conn, $place_filter = 'all', $limit = 5, $offset = 0)
{
    $query = "SELECT p.id, p.nama, p.tempat_pka, p.alamat, p.preseptor, p.cpmks, p.nilai_akhir, p.nilai_huruf
              FROM penilaian p";
    if ($place_filter !== 'all') {
        $query .= " WHERE :place_filter = (CASE 
            WHEN p.tempat_pka REGEXP 'rumah sakit|rs|rsu|rsud|hospital' THEN 'Rumah Sakit'
            WHEN p.tempat_pka REGEXP 'apotek|apotik|pharmacy' THEN 'Apotek'
            WHEN p.tempat_pka REGEXP 'puskesmas|pusat kesehatan|pusat kesehatan masyarakat' THEN 'Puskesmas'
            WHEN p.tempat_pka REGEXP 'pedagang besar|pbf|distributor farmasi|pharma distributor' THEN 'Pedagang Besar Farmasi'
            WHEN p.tempat_pka REGEXP 'industri|industry|manufaktur' THEN 'Industri'
            WHEN p.tempat_pka REGEXP 'bpom|badan pengawas obat|badan pom|pengawas obat' THEN 'BPOM'
            WHEN p.tempat_pka REGEXP 'dinas kesehatan|dinkes|departemen kesehatan|kesehatan daerah' THEN 'Dinas Kesehatan'
            ELSE 'Lainnya' END)";
    }
    $query .= " ORDER BY p.created_at DESC LIMIT :limit OFFSET :offset";

    $stmt = $conn->prepare($query);
    if ($place_filter !== 'all') {
        $stmt->bindValue(':place_filter', $place_filter, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fungsi untuk mendapatkan jumlah maksimum CPMK
function getMaxCpmk($conn, $place_filter = 'all')
{
    $query = "SELECT MAX(JSON_LENGTH(cpmks)) FROM penilaian";
    if ($place_filter !== 'all') {
        $query .= " WHERE :place_filter = (CASE 
            WHEN tempat_pka REGEXP 'rumah sakit|rs|rsu|rsud|hospital' THEN 'Rumah Sakit'
            WHEN tempat_pka REGEXP 'apotek|apotik|pharmacy' THEN 'Apotek'
            WHEN tempat_pka REGEXP 'puskesmas|pusat kesehatan|pusat kesehatan masyarakat' THEN 'Puskesmas'
            WHEN tempat_pka REGEXP 'pedagang besar|pbf|distributor farmasi|pharma distributor' THEN 'Pedagang Besar Farmasi'
            WHEN tempat_pka REGEXP 'industri|industry|manufaktur' THEN 'Industri'
            WHEN tempat_pka REGEXP 'bpom|badan pengawas obat|badan pom|pengawas obat' THEN 'BPOM'
            WHEN tempat_pka REGEXP 'dinas kesehatan|dinkes|departemen kesehatan|kesehatan daerah' THEN 'Dinas Kesehatan'
            ELSE 'Lainnya' END)";
    }
    $stmt = $conn->prepare($query);
    if ($place_filter !== 'all') {
        $stmt->bindValue(':place_filter', $place_filter, PDO::PARAM_STR);
    }
    $stmt->execute();
    return $stmt->fetchColumn() ?: 4; // Minimal 4 CPMK
}

// Proses hapus logbook
if (isset($_GET['delete_logbook']) && isset($_GET['csrf_token']) && $_GET['csrf_token'] === $_SESSION['csrf_token']) {
    try {
        $logbook_id = (int)$_GET['delete_logbook'];
        $conn->beginTransaction();
        $stmt = $conn->prepare("DELETE FROM logbook WHERE id = ?");
        $stmt->execute([$logbook_id]);
        if ($stmt->rowCount() > 0) {
            $stmt = $conn->prepare("INSERT INTO activity_log (user_id, activity) VALUES (?, ?)");
            $stmt->execute([$_SESSION['user_id'], "Menghapus logbook ID: " . $logbook_id]);
            $conn->commit();
            echo "<script>alert('Logbook berhasil dihapus!'); window.location.href='admin_data.php';</script>";
        } else {
            $conn->rollBack();
            echo "<script>alert('Gagal menghapus: Logbook tidak ditemukan.'); window.location.href='admin_data.php';</script>";
        }
    } catch (PDOException $e) {
        $conn->rollBack();
        echo "<script>alert('Gagal menghapus logbook: " . addslashes($e->getMessage()) . "'); window.location.href='admin_data.php';</script>";
    }
}

// Proses hapus feedback
if (isset($_GET['delete_feedback']) && isset($_GET['csrf_token']) && $_GET['csrf_token'] === $_SESSION['csrf_token']) {
    try {
        $feedback_id = (int)$_GET['delete_feedback'];
        $conn->beginTransaction();
        $stmt = $conn->prepare("DELETE FROM feedback WHERE id = ?");
        $stmt->execute([$feedback_id]);
        if ($stmt->rowCount() > 0) {
            $stmt = $conn->prepare("INSERT INTO activity_log (user_id, activity) VALUES (?, ?)");
            $stmt->execute([$_SESSION['user_id'], "Menghapus feedback ID: " . $feedback_id]);
            $conn->commit();
            echo "<script>alert('Feedback berhasil dihapus!'); window.location.href='admin_data.php';</script>";
        } else {
            $conn->rollBack();
            echo "<script>alert('Gagal menghapus: Feedback tidak ditemukan.'); window.location.href='admin_data.php';</script>";
        }
    } catch (PDOException $e) {
        $conn->rollBack();
        echo "<script>alert('Gagal menghapus feedback: " . addslashes($e->getMessage()) . "'); window.location.href='admin_data.php';</script>";
    }
}

// Proses hapus penilaian
if (isset($_GET['delete_penilaian']) && isset($_GET['csrf_token']) && $_GET['csrf_token'] === $_SESSION['csrf_token']) {
    try {
        $penilaian_id = (int)$_GET['delete_penilaian'];
        $conn->beginTransaction();
        $stmt = $conn->prepare("DELETE FROM penilaian WHERE id = ?");
        $stmt->execute([$penilaian_id]);
        if ($stmt->rowCount() > 0) {
            $stmt = $conn->prepare("INSERT INTO activity_log (user_id, activity) VALUES (?, ?)");
            $stmt->execute([$_SESSION['user_id'], "Menghapus penilaian ID: " . $penilaian_id]);
            $conn->commit();
            echo "<script>alert('Penilaian berhasil dihapus!'); window.location.href='admin_data.php';</script>";
        } else {
            $conn->rollBack();
            echo "<script>alert('Gagal menghapus: Penilaian tidak ditemukan.'); window.location.href='admin_data.php';</script>";
        }
    } catch (PDOException $e) {
        $conn->rollBack();
        echo "<script>alert('Gagal menghapus penilaian: " . addslashes($e->getMessage()) . "'); window.location.href='admin_data.php';</script>";
    }
}

// Pengaturan paginasi
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$limit_penilaian = 5;
$offset = ($page - 1) * $limit;
$offset_penilaian = ($page - 1) * $limit_penilaian;

// Tangkap filter
$place_filter = isset($_GET['place']) ? $_GET['place'] : 'all';
$type_filter = isset($_GET['type']) ? $_GET['type'] : 'all';

// Dapatkan jumlah maksimum CPMK
$max_cpmk = getMaxCpmk($conn, $place_filter);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Data - PSPPA Feedback System</title>
    <link rel="stylesheet" href="css/styles.css">
</head>

<body>
    <?php include 'navbar.php'; ?>
    <section class="dashboard-section">
        <div class="grid-2">
            <!-- Daftar Logbook Mahasiswa -->
            <div class="card">
                <h3 class="card-title">Logbook Mahasiswa</h3>
                <form method="GET" class="form-group">
                    <label for="place_filter_logbook">Filter Tempat</label>
                    <select id="place_filter_logbook" name="place" onchange="this.form.submit()">
                        <option value="all" <?php echo $place_filter === 'all' ? 'selected' : ''; ?>>Semua</option>
                        <option value="Rumah Sakit" <?php echo $place_filter === 'Rumah Sakit' ? 'selected' : ''; ?>>Rumah Sakit</option>
                        <option value="Apotek" <?php echo $place_filter === 'Apotek' ? 'selected' : ''; ?>>Apotek</option>
                        <option value="Puskesmas" <?php echo $place_filter === 'Puskesmas' ? 'selected' : ''; ?>>Puskesmas</option>
                        <option value="Pedagang Besar Farmasi" <?php echo $place_filter === 'Pedagang Besar Farmasi' ? 'selected' : ''; ?>>Pedagang Besar Farmasi</option>
                        <option value="Industri" <?php echo $place_filter === 'Industri' ? 'selected' : ''; ?>>Industri</option>
                        <option value="BPOM" <?php echo $place_filter === 'BPOM' ? 'selected' : ''; ?>>BPOM</option>
                        <option value="Dinas Kesehatan" <?php echo $place_filter === 'Dinas Kesehatan' ? 'selected' : ''; ?>>Dinas Kesehatan</option>
                        <option value="Lainnya" <?php echo $place_filter === 'Lainnya' ? 'selected' : ''; ?>>Lainnya</option>
                    </select>
                </form>
                <div class="export-btn">
                    <a href="export_logbook.php?place=<?php echo urlencode($place_filter); ?>" class="btn-download">Export Logbook ke Excel</a>
                </div>
                <?php
                $logbooks = getLogbook($conn, $place_filter, $limit, $offset);
                if ($logbooks) {
                    echo "<table class='table-logbook'>";
                    echo "<tr><th>Nama Mahasiswa</th><th>Tempat Wahana</th><th>Kategori Tempat</th><th>Link Logbook</th><th>Tanggal</th><th>Aksi</th></tr>";
                    foreach ($logbooks as $logbook) {
                        echo "<tr>";
                        echo "<td data-label='Nama Mahasiswa'>" . htmlspecialchars($logbook['nama']) . "</td>";
                        echo "<td data-label='Tempat Wahana'>" . htmlspecialchars($logbook['tempat_wahana']) . "</td>";
                        echo "<td data-label='Kategori Tempat'>" . getPkaCategory($logbook['tempat_wahana']) . "</td>";
                        echo "<td data-label='Link Logbook'><a href='" . htmlspecialchars($logbook['komentar']) . "' target='_blank'>Lihat Logbook</a></td>";
                        echo "<td data-label='Tanggal'>{$logbook['created_at']}</td>";
                        echo "<td data-label='Aksi'><a href='admin_data.php?delete_logbook={$logbook['id']}&csrf_token=" . urlencode($_SESSION['csrf_token']) . "&place=" . urlencode($place_filter) . "&type=" . urlencode($type_filter) . "' class='btn-remove' onclick='return confirm(\"Yakin ingin menghapus logbook ini?\")'>Hapus</a></td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                    $query_count = $place_filter === 'all' ?
                        "SELECT COUNT(*) FROM logbook l JOIN users u ON l.mahasiswa_id = u.id WHERE u.role = 'mahasiswa'" :
                        "SELECT COUNT(*) FROM logbook l JOIN users u ON l.mahasiswa_id = u.id WHERE u.role = 'mahasiswa' AND :place_filter = (CASE 
                            WHEN l.tempat_wahana REGEXP 'rumah sakit|rs|rsu|rsud|hospital' THEN 'Rumah Sakit'
                            WHEN l.tempat_wahana REGEXP 'apotek|apotik|pharmacy' THEN 'Apotek'
                            WHEN l.tempat_wahana REGEXP 'puskesmas|pusat kesehatan|pusat kesehatan masyarakat' THEN 'Puskesmas'
                            WHEN l.tempat_wahana REGEXP 'pedagang besar|pbf|distributor farmasi|pharma distributor' THEN 'Pedagang Besar Farmasi'
                            WHEN l.tempat_wahana REGEXP 'industri|industry|manufaktur' THEN 'Industri'
                            WHEN l.tempat_wahana REGEXP 'bpom|badan pengawas obat|badan pom|pengawas obat' THEN 'BPOM'
                            WHEN l.tempat_wahana REGEXP 'dinas kesehatan|dinkes|departemen kesehatan|kesehatan daerah' THEN 'Dinas Kesehatan'
                            ELSE 'Lainnya' END)";
                    $stmt = $conn->prepare($query_count);
                    if ($place_filter !== 'all') {
                        $stmt->bindValue(':place_filter', $place_filter, PDO::PARAM_STR);
                    }
                    $stmt->execute();
                    $total_logbook_logs = $stmt->fetchColumn();
                    $total_pages_logbook = ceil($total_logbook_logs / $limit);
                    if ($total_pages_logbook > 1) {
                        echo "<div class='pagination'>";
                        if ($page > 1) echo "<a href='admin_data.php?page=" . ($page - 1) . "&place=" . urlencode($place_filter) . "&type=" . urlencode($type_filter) . "'>Sebelumnya</a>";
                        if ($page < $total_pages_logbook) echo "<a href='admin_data.php?page=" . ($page + 1) . "&place=" . urlencode($place_filter) . "&type=" . urlencode($type_filter) . "'>Berikutnya</a>";
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
                    <a href="export_feedback.php?tipe=mahasiswa" class="btn-download">Export Feedback Mahasiswa ke Excel</a>
                </div>
                <?php
                $feedbacks = getMahasiswaFeedback($conn, $limit, $offset);
                if ($feedbacks) {
                    echo "<table class='table-logbook'>";
                    echo "<tr><th>Pengirim (Preceptor)</th><th>Penerima (Mahasiswa)</th><th>Komentar</th><th>Nilai</th><th>Tanggal</th><th>Aksi</th></tr>";
                    foreach ($feedbacks as $feedback) {
                        echo "<tr>";
                        echo "<td data-label='Pengirim (Preceptor)'>" . htmlspecialchars($feedback['preceptor_nama']) . "</td>";
                        echo "<td data-label='Penerima (Mahasiswa)'>" . htmlspecialchars($feedback['mahasiswa_nama']) . "</td>";
                        echo "<td data-label='Komentar'>" . htmlspecialchars($feedback['komentar']) . "</td>";
                        echo "<td data-label='Nilai'>" . ($feedback['nilai'] ? htmlspecialchars($feedback['nilai']) : '-') . "</td>";
                        echo "<td data-label='Tanggal'>{$feedback['created_at']}</td>";
                        echo "<td data-label='Aksi'><a href='admin_data.php?delete_feedback={$feedback['id']}&csrf_token=" . urlencode($_SESSION['csrf_token']) . "&place=" . urlencode($place_filter) . "&type=" . urlencode($type_filter) . "' class='btn-remove' onclick='return confirm(\"Yakin ingin menghapus feedback ini?\")'>Hapus</a></td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                    $stmt = $conn->query("SELECT COUNT(*) FROM feedback WHERE tipe = 'mahasiswa'");
                    $total_feedback = $stmt->fetchColumn();
                    $total_pages_feedback = ceil($total_feedback / $limit);
                    if ($total_pages_feedback > 1) {
                        echo "<div class='pagination'>";
                        if ($page > 1) echo "<a href='admin_data.php?page=" . ($page - 1) . "&place=" . urlencode($place_filter) . "&type=" . urlencode($type_filter) . "'>Sebelumnya</a>";
                        if ($page < $total_pages_feedback) echo "<a href='admin_data.php?page=" . ($page + 1) . "&place=" . urlencode($place_filter) . "&type=" . urlencode($type_filter) . "'>Berikutnya</a>";
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
                <form method="GET" class="form-group">
                    <label for="type_filter">Filter Tipe</label>
                    <select id="type_filter" name="type" onchange="this.form.submit()">
                        <option value="all" <?php echo $type_filter === 'all' ? 'selected' : ''; ?>>Semua</option>
                        <option value="prodi" <?php echo $type_filter === 'prodi' ? 'selected' : ''; ?>>Prodi</option>
                        <option value="wahana" <?php echo $type_filter === 'wahana' ? 'selected' : ''; ?>>Wahana</option>
                    </select>
                </form>
                <div class="export-btn">
                    <a href="export_feedback.php?tipe=prodi_wahana&type=<?php echo urlencode($type_filter); ?>" class="btn-download">Export Feedback Prodi/Wahana ke Excel</a>
                </div>
                <?php
                $prodi_wahana_feedbacks = getProdiWahanaFeedback($conn, $type_filter, $limit, $offset);
                if ($prodi_wahana_feedbacks) {
                    echo "<table class='table-logbook'>";
                    echo "<tr><th>Pengirim</th><th>Tipe</th><th>Nama Wahana</th><th>Komentar</th><th>Tanggal</th><th>Aksi</th></tr>";
                    foreach ($prodi_wahana_feedbacks as $feedback) {
                        echo "<tr>";
                        echo "<td data-label='Pengirim'>" . htmlspecialchars($feedback['pengirim_nama']) . "</td>";
                        echo "<td data-label='Tipe'>" . ucfirst($feedback['tipe']) . "</td>";
                        echo "<td data-label='Nama Wahana'>" . ($feedback['nama_wahana'] ? htmlspecialchars($feedback['nama_wahana']) : '-') . "</td>";
                        echo "<td data-label='Komentar'>" . htmlspecialchars($feedback['komentar']) . "</td>";
                        echo "<td data-label='Tanggal'>{$feedback['created_at']}</td>";
                        echo "<td data-label='Aksi'><a href='admin_data.php?delete_feedback={$feedback['id']}&csrf_token=" . urlencode($_SESSION['csrf_token']) . "&place=" . urlencode($place_filter) . "&type=" . urlencode($type_filter) . "' class='btn-remove' onclick='return confirm(\"Yakin ingin menghapus feedback ini?\")'>Hapus</a></td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                    $query_count = $type_filter === 'all' ?
                        "SELECT COUNT(*) FROM feedback WHERE tipe IN ('prodi', 'wahana')" :
                        "SELECT COUNT(*) FROM feedback WHERE tipe = :type_filter";
                    $stmt = $conn->prepare($query_count);
                    if ($type_filter !== 'all') {
                        $stmt->bindValue(':type_filter', $type_filter, PDO::PARAM_STR);
                    }
                    $stmt->execute();
                    $total_prodi_wahana_feedback = $stmt->fetchColumn();
                    $total_pages_prodi_wahana = ceil($total_prodi_wahana_feedback / $limit);
                    if ($total_pages_prodi_wahana > 1) {
                        echo "<div class='pagination'>";
                        if ($page > 1) echo "<a href='admin_data.php?page=" . ($page - 1) . "&place=" . urlencode($place_filter) . "&type=" . urlencode($type_filter) . "'>Sebelumnya</a>";
                        if ($page < $total_pages_prodi_wahana) echo "<a href='admin_data.php?page=" . ($page + 1) . "&place=" . urlencode($place_filter) . "&type=" . urlencode($type_filter) . "'>Berikutnya</a>";
                        echo "</div>";
                    }
                } else {
                    echo "<p>Belum ada feedback untuk prodi atau wahana.</p>";
                }
                ?>
            </div>
            <!-- Bagian Rekapan Penilaian Mahasiswa -->
            <div class="card">
                <h3 class="card-title">Rekapan Penilaian Mahasiswa</h3>
                <form method="GET" class="form-group">
                    <label for="place_filter_penilaian">Filter Tempat</label>
                    <select id="place_filter_penilaian" name="place" onchange="this.form.submit()">
                        <option value="all" <?php echo $place_filter === 'all' ? 'selected' : ''; ?>>Semua</option>
                        <option value="Rumah Sakit" <?php echo $place_filter === 'Rumah Sakit' ? 'selected' : ''; ?>>Rumah Sakit</option>
                        <option value="Apotek" <?php echo $place_filter === 'Apotek' ? 'selected' : ''; ?>>Apotek</option>
                        <option value="Puskesmas" <?php echo $place_filter === 'Puskesmas' ? 'selected' : ''; ?>>Puskesmas</option>
                        <option value="Pedagang Besar Farmasi" <?php echo $place_filter === 'Pedagang Besar Farmasi' ? 'selected' : ''; ?>>Pedagang Besar Farmasi</option>
                        <option value="Industri" <?php echo $place_filter === 'Industri' ? 'selected' : ''; ?>>Industri</option>
                        <option value="BPOM" <?php echo $place_filter === 'BPOM' ? 'selected' : ''; ?>>BPOM</option>
                        <option value="Dinas Kesehatan" <?php echo $place_filter === 'Dinas Kesehatan' ? 'selected' : ''; ?>>Dinas Kesehatan</option>
                        <option value="Lainnya" <?php echo $place_filter === 'Lainnya' ? 'selected' : ''; ?>>Lainnya</option>
                    </select>
                </form>
                <div class="export-btn">
                    <a href="export_penilaian.php?place=<?php echo urlencode($place_filter); ?>" class="btn-download">Export Penilaian ke Excel</a>
                </div>
                <?php
                $penilaian = getPenilaian($conn, $place_filter, $limit_penilaian, $offset_penilaian);
                if ($penilaian) {
                    echo "<table class='table-logbook'>";
                    echo "<tr><th>Nama</th><th>Tempat PKPA</th><th>Kategori Tempat</th><th>Alamat</th><th>Preseptor</th>";
                    for ($i = 1; $i <= $max_cpmk; $i++) {
                        echo "<th>CPMK $i</th>";
                    }
                    echo "<th>Nilai Akhir</th><th>Nilai Huruf</th><th>Aksi</th></tr>";
                    foreach ($penilaian as $nilai) {
                        $cpmks = json_decode($nilai['cpmks'], true) ?? [];
                        echo "<tr>";
                        echo "<td data-label='Nama'>" . htmlspecialchars($nilai['nama']) . "</td>";
                        echo "<td data-label='Tempat PKPA'>" . htmlspecialchars($nilai['tempat_pka']) . "</td>";
                        echo "<td data-label='Kategori Tempat'>" . getPkaCategory($nilai['tempat_pka']) . "</td>";
                        echo "<td data-label='Alamat'>" . htmlspecialchars($nilai['alamat'] ?: '-') . "</td>";
                        echo "<td data-label='Preseptor'>" . htmlspecialchars($nilai['preseptor']) . "</td>";
                        for ($i = 0; $i < $max_cpmk; $i++) {
                            echo "<td data-label='CPMK " . ($i + 1) . "'>" . (isset($cpmks[$i]) ? htmlspecialchars($cpmks[$i]) : '-') . "</td>";
                        }
                        echo "<td data-label='Nilai Akhir'>" . htmlspecialchars($nilai['nilai_akhir']) . "</td>";
                        echo "<td data-label='Nilai Huruf'>" . htmlspecialchars($nilai['nilai_huruf']) . "</td>";
                        echo "<td data-label='Aksi'>";
                        echo "<a href='admin_data.php?delete_penilaian={$nilai['id']}&csrf_token=" . urlencode($_SESSION['csrf_token']) . "&place=" . urlencode($place_filter) . "&type=" . urlencode($type_filter) . "' class='btn-remove' onclick='return confirm(\"Yakin ingin menghapus penilaian ini?\")'>Hapus</a>";
                        echo "<a href='export_single_penilaian_pdf.php?id={$nilai['id']}&csrf_token=" . urlencode($_SESSION['csrf_token']) . "' class='btn-download' style='margin-left: 10px;'> PDF</a>";
                        echo "</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                    $query_count = $place_filter === 'all' ?
                        "SELECT COUNT(*) FROM penilaian" :
                        "SELECT COUNT(*) FROM penilaian WHERE :place_filter = (CASE 
                            WHEN tempat_pka REGEXP 'rumah sakit|rs|rsu|rsud|hospital' THEN 'Rumah Sakit'
                            WHEN tempat_pka REGEXP 'apotek|apotik|pharmacy' THEN 'Apotek'
                            WHEN tempat_pka REGEXP 'puskesmas|pusat kesehatan|pusat kesehatan masyarakat' THEN 'Puskesmas'
                            WHEN tempat_pka REGEXP 'pedagang besar|pbf|distributor farmasi|pharma distributor' THEN 'Pedagang Besar Farmasi'
                            WHEN tempat_pka REGEXP 'industri|industry|manufaktur' THEN 'Industri'
                            WHEN tempat_pka REGEXP 'bpom|badan pengawas obat|badan pom|pengawas obat' THEN 'BPOM'
                            WHEN tempat_pka REGEXP 'dinas kesehatan|dinkes|departemen kesehatan|kesehatan daerah' THEN 'Dinas Kesehatan'
                            ELSE 'Lainnya' END)";
                    $stmt = $conn->prepare($query_count);
                    if ($place_filter !== 'all') {
                        $stmt->bindValue(':place_filter', $place_filter, PDO::PARAM_STR);
                    }
                    $stmt->execute();
                    $total_penilaian = $stmt->fetchColumn();
                    $total_pages_penilaian = ceil($total_penilaian / $limit_penilaian);
                    if ($total_pages_penilaian > 1) {
                        echo "<div class='pagination'>";
                        if ($page > 1) echo "<a href='admin_data.php?page=" . ($page - 1) . "&place=" . urlencode($place_filter) . "&type=" . urlencode($type_filter) . "'>Sebelumnya</a>";
                        if ($page < $total_pages_penilaian) echo "<a href='admin_data.php?page=" . ($page + 1) . "&place=" . urlencode($place_filter) . "&type=" . urlencode($type_filter) . "'>Berikutnya</a>";
                        echo "</div>";
                    }
                } else {
                    echo "<p>Belum ada data penilaian.</p>";
                }
                ?>
            </div>
        </div>
    </section>
    <?php include 'footer.php'; ?>
    <script src="js/script.js"></script>
</body>

</html>