<?php
include 'db.php';
session_start();

function sanitize($data)
{
    return htmlspecialchars(strip_tags(trim($data)));
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = sanitize($_POST['action']);

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }

    try {
        if ($action == 'tambah') {
            $id_mahasiswa = filter_input(INPUT_POST, 'id_mahasiswa', FILTER_VALIDATE_INT);
            $id_tempat = filter_input(INPUT_POST, 'id_tempat', FILTER_VALIDATE_INT);
            $id_dosen_pembimbing = filter_input(INPUT_POST, 'id_dosen_pembimbing', FILTER_VALIDATE_INT) ?: null;
            $id_preceptor1 = filter_input(INPUT_POST, 'id_preceptor1', FILTER_VALIDATE_INT) ?: null;
            $id_preceptor2 = filter_input(INPUT_POST, 'id_preceptor2', FILTER_VALIDATE_INT) ?: null;
            if (!$id_mahasiswa || !$id_tempat) {
                http_response_code(400);
                echo json_encode(['error' => 'ID mahasiswa atau tempat tidak valid']);
                exit;
            }
            $stmt = $conn->prepare("SELECT id FROM mahasiswa WHERE id = ?");
            $stmt->execute([$id_mahasiswa]);
            if (!$stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['error' => 'ID mahasiswa tidak ditemukan']);
                exit;
            }
            $stmt = $conn->prepare("SELECT id FROM tempat WHERE id = ?");
            $stmt->execute([$id_tempat]);
            if (!$stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['error' => 'ID tempat tidak ditemukan']);
                exit;
            }
            if ($id_dosen_pembimbing) {
                $stmt = $pdo->prepare("SELECT id FROM dosen_pembimbing WHERE id = ? AND tipe = 'dosen'");
                $stmt->execute([$id_dosen_pembimbing]);
                if (!$stmt->fetch()) {
                    http_response_code(400);
                    echo json_encode(['error' => 'ID dosen pembimbing tidak ditemukan']);
                    exit;
                }
            }
            if ($id_preceptor1) {
                $stmt = $pdo->prepare("SELECT id FROM dosen_pembimbing WHERE id = ? AND tipe = 'preceptor' AND id_tempat = ?");
                $stmt->execute([$id_preceptor1, $id_tempat]);
                if (!$stmt->fetch()) {
                    http_response_code(400);
                    echo json_encode(['error' => 'ID preceptor 1 tidak valid atau tidak terkait dengan tempat']);
                    exit;
                }
            }
            if ($id_preceptor2) {
                $stmt = $pdo->prepare("SELECT id FROM dosen_pembimbing WHERE id = ? AND tipe = 'preceptor' AND id_tempat = ?");
                $stmt->execute([$id_preceptor2, $id_tempat]);
                if (!$stmt->fetch()) {
                    http_response_code(400);
                    echo json_encode(['error' => 'ID preceptor 2 tidak valid atau tidak terkait dengan tempat']);
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
                $stmt = $conn->prepare("SELECT id, tanggal_mulai, tanggal_selesai FROM jadwal WHERE id_mahasiswa = ? AND tanggal_selesai >= ? AND tanggal_mulai <= ?");
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
                $stmt = $pdo->prepare("INSERT INTO jadwal (id_mahasiswa, id_tempat, id_dosen_pembimbing, id_preceptor1, id_preceptor2, tanggal_mulai, tanggal_selesai) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$id_mahasiswa, $id_tempat, $id_dosen_pembimbing, $id_preceptor1, $id_preceptor2, $range['tanggal_mulai'], $range['tanggal_selesai']]);
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
            $stmt = $conn->prepare("SELECT id FROM mahasiswa WHERE id = ?");
            $stmt->execute([$id_mahasiswa]);
            if (!$stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['error' => 'ID mahasiswa tidak ditemukan']);
                exit;
            }
            $stmt = $conn->prepare("DELETE FROM jadwal WHERE id_mahasiswa = ? AND tanggal_mulai <= ? AND tanggal_selesai >= ?");
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
    $stmt = $conn->prepare("SELECT id, nama_tempat FROM tempat WHERE jenis_tempat = ? ORDER BY nama_tempat");
    $stmt->execute([$jenis]);
    $tempat_by_jenis[$jenis] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Tempat Query Result for jenis $jenis: " . print_r($tempat_by_jenis[$jenis], true));
}

$stmt = $pdo->query("SELECT id, nama FROM dosen_pembimbing WHERE tipe = 'dosen' ORDER BY nama");
$dosen_pembimbing_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

$preceptor_list = [];
if (isset($_GET['id_tempat']) && filter_var($_GET['id_tempat'], FILTER_VALIDATE_INT)) {
    $stmt = $pdo->prepare("SELECT id, nama FROM dosen_pembimbing WHERE tipe = 'preceptor' AND id_tempat = ? ORDER BY nama");
    $stmt->execute([$_GET['id_tempat']]);
    $preceptor_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Preceptor Query Result for id_tempat {$_GET['id_tempat']}: " . print_r($preceptor_list, true));
    if (empty($preceptor_list)) {
        error_log("No preceptors found for id_tempat {$_GET['id_tempat']}");
    }
} else {
    error_log("No id_tempat provided for preceptor query");
}

$stmt = $pdo->prepare("SELECT id, nama FROM mahasiswa WHERE angkatan = ? ORDER BY nama");
$stmt->execute([$selected_angkatan]);
$mahasiswa_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT j.*, m.nama AS nama_mahasiswa, t.nama_tempat, t.jenis_tempat, d.nama AS nama_dosen, p1.nama AS nama_preceptor1, p2.nama AS nama_preceptor2 FROM jadwal j JOIN mahasiswa m ON j.id_mahasiswa = m.id JOIN tempat t ON j.id_tempat = t.id LEFT JOIN dosen_pembimbing d ON j.id_dosen_pembimbing = d.id LEFT JOIN dosen_pembimbing p1 ON j.id_preceptor1 = p1.id LEFT JOIN dosen_pembimbing p2 ON j.id_preceptor2 = p2.id WHERE m.angkatan = ? ORDER BY m.nama, j.tanggal_mulai");
$stmt->execute([$selected_angkatan]);
$jadwal_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_days = [];
foreach ($mahasiswa_list as $mhs) {
    $stmt = $conn->prepare("SELECT SUM(DATEDIFF(tanggal_selesai, tanggal_mulai) + 1) as total_days FROM jadwal WHERE id_mahasiswa = ?");
    $stmt->execute([$mhs['id']]);
    $total_days[$mhs['id']] = $stmt->fetchColumn() ?? 0;
}

function generateMonthTable($year, $month, $mahasiswa_list, $jadwal_data, $total_days)
{
    $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    $start_date = "$year-$month-01";
    $end_date = "$year-$month-$days_in_month";
    $table = "<h2>" . date('F Y', mktime(0, 0, 0, $month, 1, $year)) . "</h2>";
    $table .= "<table class='jadwalTable' data-year-month='$year-$month'><thead><tr><th>No</th><th>Nama</th><th>Total Hari</th>";
    for ($day = 1; $day <= $days_in_month; $day++) {
        $table .= "<th>$day</th>";
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
            $preceptor1 = '';
            $preceptor2 = '';
            foreach ($jadwal_data as $jdw) {
                if ($jdw['id_mahasiswa'] == $mhs['id'] && $date >= $jdw['tanggal_mulai'] && $date <= $jdw['tanggal_selesai']) {
                    $tempat = htmlspecialchars($jdw['nama_tempat']);
                    $jenis = htmlspecialchars($jdw['jenis_tempat']);
                    $mulai = htmlspecialchars($jdw['tanggal_mulai']);
                    $selesai = htmlspecialchars($jdw['tanggal_selesai']);
                    $dosen = htmlspecialchars($jdw['nama_dosen'] ?? 'Tidak ada');
                    $preceptor1 = htmlspecialchars($jdw['nama_preceptor1'] ?? 'Tidak ada');
                    $preceptor2 = htmlspecialchars($jdw['nama_preceptor2'] ?? 'Tidak ada');
                    break;
                }
            }
            $class = $tempat ? ' class="scheduled" data-jenis="' . htmlspecialchars(strtolower(str_replace(" ", "-", $jenis))) . '"' : '';
            $data_attrs = $tempat ? ' data-mulai="' . $mulai . '" data-selesai="' . $selesai . '" data-dosen="' . $dosen . '" data-preceptor1="' . $preceptor1 . '" data-preceptor2="' . $preceptor2 . '"' : '';
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

if (isset($_GET['get_preceptors']) && filter_var($_GET['id_tempat'], FILTER_VALIDATE_INT)) {
    header('Content-Type: application/json');
    $stmt = $pdo->prepare("SELECT id, nama FROM dosen_pembimbing WHERE tipe = 'preceptor' AND id_tempat = ? ORDER BY nama");
    $stmt->execute([$_GET['id_tempat']]);
    $preceptor_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($preceptor_list);
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Tambah Jadwal</title>
    <link rel="stylesheet" href="style1.css">
    <script src="script1.js"></script>
</head>

<body>
    <?php include 'header.php'; ?>
    <h1>Tambah Jadwal Baru</h1>
    <form method="GET" class="filter-form" id="filterForm">
        <input type="hidden" name="id_tempat" id="hidden_id_tempat" value="<?php echo htmlspecialchars($_GET['id_tempat'] ?? ''); ?>">
        <input type="hidden" name="start_date" id="hidden_start_date" value="<?php echo htmlspecialchars($_GET['start_date'] ?? ''); ?>">
        <input type="hidden" name="mahasiswa_id" id="hidden_mahasiswa_id" value="<?php echo htmlspecialchars($_GET['mahasiswa_id'] ?? ''); ?>">
        <input type="hidden" name="id_dosen_pembimbing" id="hidden_id_dosen_pembimbing" value="<?php echo htmlspecialchars($_GET['id_dosen_pembimbing'] ?? ''); ?>">
        <input type="hidden" name="id_preceptor1" id="hidden_id_preceptor1" value="<?php echo htmlspecialchars($_GET['id_preceptor1'] ?? ''); ?>">
        <input type="hidden" name="id_preceptor2" id="hidden_id_preceptor2" value="<?php echo htmlspecialchars($_GET['id_preceptor2'] ?? ''); ?>">
        <input type="hidden" name="csrf_token" id="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
        <label for="angkatan">Angkatan:</label>
        <select name="angkatan" id="angkatan" onchange="this.form.submit()">
            <option value="">Pilih Angkatan</option>
            <?php foreach ($angkatan_list as $angk): ?>
                <option value="<?php echo htmlspecialchars($angk); ?>" <?php echo $angk == $selected_angkatan ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($angk); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <label for="month">Bulan:</label>
        <select name="month" id="month" onchange="this.form.submit()">
            <option value="">Pilih Bulan</option>
            <?php for ($m = 1; $m <= 12; $m++): $ym = sprintf("%02d-%04d", $m, $selected_year); ?>
                <option value="<?php echo htmlspecialchars($ym); ?>" <?php echo $m == $selected_month ? 'selected' : ''; ?>>
                    <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                </option>
            <?php endfor; ?>
        </select>
        <label for="year">Tahun:</label>
        <select name="year" id="year" onchange="this.form.submit()">
            <option value="">Pilih Tahun</option>
            <?php for ($y = date('Y') - 5; $y <= date('Y') + 5; $y++): ?>
                <option value="<?php echo htmlspecialchars($y); ?>" <?php echo $y == $selected_year ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($y); ?>
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
                <?php if (empty($dosen_pembimbing_list)): ?>
                    <option value="" disabled>Tidak ada dosen tersedia</option>
                <?php else: ?>
                    <?php foreach ($dosen_pembimbing_list as $dosen): ?>
                        <option value="<?php echo htmlspecialchars($dosen['id']); ?>" <?php echo (isset($_GET['id_dosen_pembimbing']) && $_GET['id_dosen_pembimbing'] == $dosen['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dosen['nama']); ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>
        <div class="dropdown-item">
            <label for="preceptor1">Preceptor 1:</label>
            <select id="preceptor1" name="id_preceptor1" onchange="updateHiddenFields()" aria-label="Preceptor 1">
                <option value="">Pilih Preceptor 1 (Opsional)</option>
                <?php if (empty($preceptor_list)): ?>
                    <option value="" disabled><?php echo isset($_GET['id_tempat']) ? 'Tidak ada preceptor untuk tempat ini' : 'Pilih tempat terlebih dahulu'; ?></option>
                <?php else: ?>
                    <?php foreach ($preceptor_list as $preceptor): ?>
                        <option value="<?php echo htmlspecialchars($preceptor['id']); ?>" <?php echo (isset($_GET['id_preceptor1']) && $_GET['id_preceptor1'] == $preceptor['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($preceptor['nama']); ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>
        <div class="dropdown-item">
            <label for="preceptor2">Preceptor 2:</label>
            <select id="preceptor2" name="id_preceptor2" onchange="updateHiddenFields()" aria-label="Preceptor 2">
                <option value="">Pilih Preceptor 2 (Opsional)</option>
                <?php if (empty($preceptor_list)): ?>
                    <option value="" disabled>Pilih tempat terlebih dahulu</option>
                <?php else: ?>
                    <?php foreach ($preceptor_list as $preceptor): ?>
                        <option value="<?php echo htmlspecialchars($preceptor['id']); ?>" <?php echo (isset($_GET['id_preceptor2']) && $_GET['id_preceptor2'] == $preceptor['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($preceptor['nama']); ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
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