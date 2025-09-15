<?php
include 'db.php';
function sanitize($data)
{
    return htmlspecialchars(strip_tags(trim($data)));
}
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = sanitize($_POST['action']);
    try {
        if ($action == 'tambah') {
            $id_mahasiswa = filter_input(INPUT_POST, 'id_mahasiswa', FILTER_VALIDATE_INT);
            $id_tempat = filter_input(INPUT_POST, 'id_tempat', FILTER_VALIDATE_INT);
            $id_dosen_pembimbing = filter_input(INPUT_POST, 'id_dosen_pembimbing', FILTER_VALIDATE_INT) ?: null;
            if (!$id_mahasiswa || !$id_tempat) {
                http_response_code(400);
                echo json_encode(['error' => 'ID mahasiswa atau tempat tidak valid']);
                exit;
            }
            $stmt = $pdo->prepare("SELECT id FROM mahasiswa WHERE id = ?");
            $stmt->execute([$id_mahasiswa]);
            if (!$stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['error' => 'ID mahasiswa tidak ditemukan']);
                exit;
            }
            $stmt = $pdo->prepare("SELECT id FROM tempat WHERE id = ?");
            $stmt->execute([$id_tempat]);
            if (!$stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['error' => 'ID tempat tidak ditemukan']);
                exit;
            }
            if ($id_dosen_pembimbing) {
                $stmt = $pdo->prepare("SELECT id FROM dosen_pembimbing WHERE id = ?");
                $stmt->execute([$id_dosen_pembimbing]);
                if (!$stmt->fetch()) {
                    http_response_code(400);
                    echo json_encode(['error' => 'ID dosen pembimbing tidak ditemukan']);
                    exit;
                }
            }
            $ranges = [];
            for ($i = 0; isset($_POST['tanggal_mulai_' . $i]) && isset($_POST['tanggal_selesai_' . $i]); $i++) {
                $start = sanitize($_POST['tanggal_mulai_' . $i]);
                $end = sanitize($_POST['tanggal_selesai_' . $i]);
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Format tanggal tidak valid']);
                    exit;
                }
                if (strtotime($start) > strtotime($end)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Tanggal mulai harus sebelum atau sama dengan tanggal selesai']);
                    exit;
                }
                $stmt = $pdo->prepare("SELECT id, tanggal_mulai, tanggal_selesai FROM jadwal WHERE id_mahasiswa = ? AND tanggal_selesai >= ? AND tanggal_mulai <= ?");
                $stmt->execute([$id_mahasiswa, $start, $end]);
                $conflicts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($conflicts)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Jadwal bertabrakan dengan jadwal lain', 'conflicts' => $conflicts]);
                    exit;
                }
                $ranges[] = ['tanggal_mulai' => $start, 'tanggal_selesai' => $end];
            }
            if (empty($ranges)) {
                http_response_code(400);
                echo json_encode(['error' => 'Tidak ada rentang tanggal yang valid']);
                exit;
            }
            foreach ($ranges as $range) {
                $stmt = $pdo->prepare("INSERT INTO jadwal (id_mahasiswa, id_tempat, id_dosen_pembimbing, tanggal_mulai, tanggal_selesai) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$id_mahasiswa, $id_tempat, $id_dosen_pembimbing, $range['tanggal_mulai'], $range['tanggal_selesai']]);
            }
            echo json_encode(['success' => true]);
            exit;
        } elseif ($action == 'hapus') {
            $id_mahasiswa = filter_input(INPUT_POST, 'id_mahasiswa', FILTER_VALIDATE_INT);
            $tanggal = sanitize($_POST['tanggal']);
            if (!$id_mahasiswa || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
                http_response_code(400);
                echo json_encode(['error' => 'Input untuk penghapusan tidak valid']);
                exit;
            }
            $stmt = $pdo->prepare("SELECT id FROM mahasiswa WHERE id = ?");
            $stmt->execute([$id_mahasiswa]);
            if (!$stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['error' => 'ID mahasiswa tidak ditemukan']);
                exit;
            }
            $stmt = $pdo->prepare("DELETE FROM jadwal WHERE id_mahasiswa = ? AND tanggal_mulai <= ? AND tanggal_selesai >= ?");
            $stmt->execute([$id_mahasiswa, $tanggal, $tanggal]);
            echo json_encode(['success' => true]);
            exit;
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Aksi tidak valid']);
            exit;
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Kesalahan database: ' . $e->getMessage()]);
        exit;
    }
}
$selected_angkatan = isset($_GET['angkatan']) ? $_GET['angkatan'] : date('Y');
$selected_month = date('m');
$selected_year = date('Y');
if (isset($_GET['month']) && preg_match('/^(\d{2})-(\d{4})$/', $_GET['month'], $matches)) {
    $selected_month = $matches[1];
    $selected_year = $matches[2];
}
$stmt = $pdo->query("SELECT DISTINCT angkatan FROM mahasiswa ORDER BY angkatan DESC");
$angkatan_list = $stmt->fetchAll(PDO::FETCH_COLUMN);
$stmt = $pdo->query("SELECT DISTINCT jenis_tempat FROM tempat ORDER BY jenis_tempat");
$jenis_tempat_list = $stmt->fetchAll(PDO::FETCH_COLUMN);
$tempat_by_jenis = [];
foreach ($jenis_tempat_list as $jenis) {
    $stmt = $pdo->prepare("SELECT id, nama_tempat FROM tempat WHERE jenis_tempat = ? ORDER BY nama_tempat");
    $stmt->execute([$jenis]);
    $tempat_by_jenis[$jenis] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
$stmt = $pdo->query("SELECT id, nama FROM dosen_pembimbing ORDER BY nama");
$dosen_pembimbing_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt = $pdo->prepare("SELECT id, nama FROM mahasiswa WHERE angkatan = ? ORDER BY nama");
$stmt->execute([$selected_angkatan]);
$mahasiswa_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt = $pdo->prepare("SELECT j.*, m.nama AS nama_mahasiswa, t.nama_tempat, t.jenis_tempat, d.nama AS nama_dosen FROM jadwal j JOIN mahasiswa m ON j.id_mahasiswa = m.id JOIN tempat t ON j.id_tempat = t.id LEFT JOIN dosen_pembimbing d ON j.id_dosen_pembimbing = d.id WHERE m.angkatan = ? ORDER BY m.nama, j.tanggal_mulai");
$stmt->execute([$selected_angkatan]);
$jadwal_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total_days = [];
foreach ($mahasiswa_list as $mhs) {
    $stmt = $pdo->prepare("SELECT SUM(DATEDIFF(tanggal_selesai, tanggal_mulai) + 1) as total_days FROM jadwal WHERE id_mahasiswa = ?");
    $stmt->execute([$mhs['id']]);
    $total_days[$mhs['id']] = $stmt->fetchColumn() ?? 0;
}
function generateMonthTable($year, $month, $mahasiswa_list, $jadwal_data, $total_days)
{
    $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    $start_date = "$year-$month-01";
    $end_date = "$year-$month-$days_in_month";
    $table = "<h2>" . date('F Y', mktime(0, 0, 0, $month, 1, $year)) . "</h2>";
    $table .= "<table class='jadwalTable' data-year-month='$year-$month'><thead><tr><th>No</th><th>Nama</th><th>Total (Hari)</th>";
    for ($day = 1; $day <= $days_in_month; $day++) {
        $date = sprintf("%04d-%02d-%02d", $year, $month, $day);
        $is_holiday = (date('w', strtotime($date)) == 0) ? ' class="holiday"' : '';
        $table .= "<th$is_holiday>$day</th>";
    }
    $table .= "</tr></thead><tbody>";
    $no = 1;
    foreach ($mahasiswa_list as $mhs) {
        $table .= "<tr data-mahasiswa-id='" . htmlspecialchars($mhs['id']) . "'><td>$no</td><td>" . htmlspecialchars($mhs['nama']) . "</td><td>" . (isset($total_days[$mhs['id']]) ? htmlspecialchars($total_days[$mhs['id']]) : 0) . "</td>";
        for ($day = 1; $day <= $days_in_month; $day++) {
            $date = sprintf("%04d-%02d-%02d", $year, $month, $day);
            $tempat = '';
            $jenis = '';
            $mulai = '';
            $selesai = '';
            $dosen = '';
            foreach ($jadwal_data as $jdw) {
                if ($jdw['id_mahasiswa'] == $mhs['id'] && $date >= $jdw['tanggal_mulai'] && $date <= $jdw['tanggal_selesai']) {
                    $tempat = htmlspecialchars($jdw['nama_tempat']);
                    $jenis = htmlspecialchars($jdw['jenis_tempat']);
                    $mulai = htmlspecialchars($jdw['tanggal_mulai']);
                    $selesai = htmlspecialchars($jdw['tanggal_selesai']);
                    $dosen = htmlspecialchars($jdw['nama_dosen'] ?? 'Tidak ada');
                    break;
                }
            }
            $class = $tempat ? ' class="scheduled" data-jenis="' . htmlspecialchars(strtolower(str_replace(" ", "-", $jenis))) . '"' : '';
            $data_attrs = $tempat ? ' data-mulai="' . $mulai . '" data-selesai="' . $selesai . '" data-dosen="' . $dosen . '"' : '';
            $table .= "<td$class$data_attrs data-date='$date'>$tempat</td>";
        }
        $table .= "</tr>";
        $no++;
    }
    $table .= "</tbody></table>";
    return $table;
}
$month_tables = [];
$month_tables[] = generateMonthTable($selected_year, $selected_month, $mahasiswa_list, $jadwal_data, $total_days);
$next_month = $selected_month + 1;
$next_year = $selected_year;
if ($next_month > 12) {
    $next_month = 1;
    $next_year++;
}
$month_tables[] = generateMonthTable($next_year, $next_month, $mahasiswa_list, $jadwal_data, $total_days);
// Tambahkan tabel untuk bulan start_date jika berbeda
if (isset($_GET['start_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['start_date'])) {
    $start_ym = date('Y-m', strtotime($_GET['start_date']));
    $current_ym = sprintf('%04d-%02d', $selected_year, $selected_month);
    if ($start_ym !== $current_ym && $start_ym !== sprintf('%04d-%02d', $next_year, $next_month)) {
        $start_parts = explode('-', $start_ym);
        $month_tables[] = generateMonthTable((int)$start_parts[0], (int)$start_parts[1], $mahasiswa_list, $jadwal_data, $total_days);
    }
}
usort($month_tables, function ($a, $b) {
    $a_date = DateTime::createFromFormat('F Y', strip_tags($a));
    $b_date = DateTime::createFromFormat('F Y', strip_tags($b));
    return $a_date <=> $b_date;
});
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Tambah Jadwal</title>
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
    <h1>Tambah Jadwal Baru</h1>
    <form method="GET" class="filter-form" id="filterForm">
        <input type="hidden" name="id_tempat" id="hidden_id_tempat" value="<?php echo htmlspecialchars($_GET['id_tempat'] ?? ''); ?>">
        <input type="hidden" name="start_date" id="hidden_start_date" value="<?php echo htmlspecialchars($_GET['start_date'] ?? ''); ?>">
        <input type="hidden" name="mahasiswa_id" id="hidden_mahasiswa_id" value="<?php echo htmlspecialchars($_GET['mahasiswa_id'] ?? ''); ?>">
        <input type="hidden" name="id_dosen_pembimbing" id="hidden_id_dosen_pembimbing" value="<?php echo htmlspecialchars($_GET['id_dosen_pembimbing'] ?? ''); ?>">
        <label for="angkatan">Angkatan:</label>
        <select name="angkatan" id="angkatan" onchange="updateHiddenFields(); this.form.submit()">
            <?php foreach ($angkatan_list as $angk): ?>
                <option value="<?php echo htmlspecialchars($angk); ?>" <?php echo $angk == $selected_angkatan ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($angk); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <label for="month">Bulan:</label>
        <select name="month" id="month" onchange="updateHiddenFields(); this.form.submit()">
            <?php for ($m = 1; $m <= 12; $m++): $ym = sprintf("%02d-%04d", $m, $selected_year); ?>
                <option value="<?php echo $ym; ?>" <?php echo $m == $selected_month ? 'selected' : ''; ?>>
                    <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                </option>
            <?php endfor; ?>
        </select>
        <label for="year">Tahun:</label>
        <select name="year" id="year" onchange="updateHiddenFields(); this.form.submit()">
            <?php for ($y = date('Y') - 5; $y <= date('Y') + 5; $y++): ?>
                <option value="<?php echo $y; ?>" <?php echo $y == $selected_year ? 'selected' : ''; ?>>
                    <?php echo $y; ?>
                </option>
            <?php endfor; ?>
        </select>
    </form>
    <div class="dropdown-container">
        <?php foreach ($jenis_tempat_list as $index => $jenis): ?>
            <div class="dropdown-item">
                <label for="tempat_<?php echo $index; ?>"><?php echo htmlspecialchars($jenis); ?>:</label>
                <select id="tempat_<?php echo $index; ?>" data-jenis="<?php echo htmlspecialchars(strtolower(str_replace(" ", "-", $jenis))); ?>" onchange="setTempatColor(this)" aria-label="<?php echo htmlspecialchars($jenis); ?>">
                    <option value="">Pilih <?php echo htmlspecialchars($jenis); ?></option>
                    <?php foreach ($tempat_by_jenis[$jenis] as $tempat): ?>
                        <option value="<?php echo htmlspecialchars($tempat['id']); ?>" data-nama="<?php echo htmlspecialchars($tempat['nama_tempat']); ?>" <?php echo (isset($_GET['id_tempat']) && $_GET['id_tempat'] == $tempat['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($tempat['nama_tempat']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endforeach; ?>
        <div class="dropdown-item">
            <label for="dosen_pembimbing">Dosen Pembimbing:</label>
            <select id="dosen_pembimbing" name="id_dosen_pembimbing" onchange="updateHiddenFields()" aria-label="Dosen Pembimbing">
                <option value="">Pilih Dosen Pembimbing (Opsional)</option>
                <?php foreach ($dosen_pembimbing_list as $dosen): ?>
                    <option value="<?php echo htmlspecialchars($dosen['id']); ?>" <?php echo (isset($_GET['id_dosen_pembimbing']) && $_GET['id_dosen_pembimbing'] == $dosen['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($dosen['nama']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="table-controls">
        <button id="zoom-in" aria-label="Zoom In">Zoom In</button>
        <button id="zoom-out" aria-label="Zoom Out">Zoom Out</button>
    </div>
    <div class="table-container" style="overflow-x: auto;">
        <div class="zoom-wrapper" id="zoomWrapper" data-zoom="1">
            <?php echo implode('', $month_tables); ?>
        </div>
    </div>
</body>

</html>