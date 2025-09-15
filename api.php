<?php
header('Content-Type: application/json');
// Restrict CORS for production (allow only specific origins)
header('Access-Control-Allow-Origin: http://your-trusted-domain.com');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

$db_path = 'database/jadwal.db';
$action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_STRING);

try {
    $db = new PDO("sqlite:$db_path");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create database tables if they don't exist
    $db->exec("
        CREATE TABLE IF NOT EXISTS mahasiswa (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nama TEXT NOT NULL,
            nim TEXT NOT NULL UNIQUE,
            prodi TEXT NOT NULL,
            semester INTEGER NOT NULL,
            angkatan INTEGER NOT NULL
        )
    ");
    $db->exec("
        CREATE TABLE IF NOT EXISTS tempat (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nama_tempat TEXT NOT NULL UNIQUE,
            alamat TEXT NOT NULL,
            kontak TEXT NOT NULL,
            jenis_tempat TEXT NOT NULL
        )
    ");
    $db->exec("
        CREATE TABLE IF NOT EXISTS jadwal (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            id_mahasiswa INTEGER NOT NULL,
            id_tempat INTEGER NOT NULL,
            tanggal_mulai TEXT NOT NULL,
            tanggal_selesai TEXT NOT NULL,
            FOREIGN KEY (id_mahasiswa) REFERENCES mahasiswa(id),
            FOREIGN KEY (id_tempat) REFERENCES tempat(id)
        )
    ");

    if ($action === 'statistics') {
        $stmt = $db->query("SELECT COUNT(*) FROM mahasiswa");
        $total_students = $stmt->fetchColumn();

        $stmt = $db->query("SELECT COUNT(*) FROM tempat");
        $total_locations = $stmt->fetchColumn();

        $start = date('Y-m-d', strtotime('monday this week'));
        $end = date('Y-m-d', strtotime('sunday this week'));
        $stmt = $db->prepare("SELECT COUNT(*) FROM jadwal WHERE tanggal_mulai <= ? AND tanggal_selesai >= ?");
        $stmt->execute([$end, $start]);
        $active_this_week = $stmt->fetchColumn();

        echo json_encode([
            'students' => $total_students,
            'locations' => $total_locations,
            'this_week' => $active_this_week
        ]);
    } elseif ($action === 'daily_schedule') {
        $date = filter_input(INPUT_GET, 'date', FILTER_SANITIZE_STRING);
        if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid or missing date']);
            exit;
        }
        $stmt = $db->prepare("
            SELECT m.nama, t.nama_tempat
            FROM jadwal j
            JOIN mahasiswa m ON j.id_mahasiswa = m.id
            JOIN tempat t ON j.id_tempat = t.id
            WHERE ? BETWEEN date(j.tanggal_mulai) AND date(j.tanggal_selesai)
        ");
        $stmt->execute([$date]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } elseif ($action === 'angkatan') {
        $stmt = $db->query("SELECT DISTINCT angkatan FROM mahasiswa ORDER BY angkatan");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } elseif ($action === 'jenis_tempat') {
        $stmt = $db->query("SELECT DISTINCT jenis_tempat FROM tempat ORDER BY jenis_tempat");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } elseif ($action === 'tempat') {
        $jenis = filter_input(INPUT_GET, 'jenis', FILTER_SANITIZE_STRING);
        $stmt = $db->prepare("SELECT id, nama_tempat FROM tempat WHERE jenis_tempat = ?");
        $stmt->execute([$jenis]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } elseif ($action === 'mahasiswa') {
        $angkatan = filter_input(INPUT_GET, 'angkatan', FILTER_SANITIZE_STRING);
        $stmt = $db->prepare("SELECT id, nama FROM mahasiswa WHERE angkatan = ?");
        $stmt->execute([$angkatan]);
        $mahasiswa = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($mahasiswa as &$m) {
            $m['total_days'] = calculateTotalDays($db, $m['id'], $angkatan);
        }
        echo json_encode($mahasiswa);
    } elseif ($action === 'add_jadwal' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (
            !isset($data['id_mahasiswa'], $data['id_tempat'], $data['start'], $data['end']) ||
            !is_numeric($data['id_mahasiswa']) || !is_numeric($data['id_tempat']) ||
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['start']) ||
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['end'])
        ) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid input data']);
            exit;
        }
        if (strtotime($data['start']) > strtotime($data['end'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Start date must be before end date']);
            exit;
        }
        // Validasi overlap jadwal
        $stmt = $db->prepare("SELECT COUNT(*) FROM jadwal WHERE id_mahasiswa = ? AND (
            (tanggal_mulai <= ? AND tanggal_selesai >= ?) OR
            (tanggal_mulai <= ? AND tanggal_selesai >= ?) OR
            (tanggal_mulai >= ? AND tanggal_selesai <= ?)
        )");
        $stmt->execute([
            $data['id_mahasiswa'],
            $data['end'],
            $data['start'],
            $data['start'],
            $data['end'],
            $data['start'],
            $data['end']
        ]);
        if ($stmt->fetchColumn() > 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Schedule overlaps with existing schedule']);
            exit;
        }
        $stmt = $db->prepare("INSERT INTO jadwal (id_mahasiswa, id_tempat, tanggal_mulai, tanggal_selesai) VALUES (?, ?, ?, ?)");
        $stmt->execute([$data['id_mahasiswa'], $data['id_tempat'], $data['start'], $data['end']]);
        echo json_encode(['success' => true]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

function calculateTotalDays($db, $id_mahasiswa, $angkatan)
{
    $stmt = $db->prepare("
        SELECT SUM(julianday(tanggal_selesai) - julianday(tanggal_mulai) + 1) as total_days
        FROM jadwal
        WHERE id_mahasiswa = ? AND strftime('%Y', tanggal_mulai) = ?
    ");
    $stmt->execute([$id_mahasiswa, $angkatan]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return (int)($result['total_days'] ?? 0);
}
