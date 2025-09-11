<?php
// Mulai output buffering
ob_start();

// Include library TCPDF dan koneksi database
require_once 'tcpdf/tcpdf.php';
require_once 'auth.php';

// Verifikasi CSRF token
session_start();
if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']) {
    ob_end_clean();
    die('CSRF token tidak valid');
}

// Ambil ID dari GET
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    ob_end_clean();
    die('ID tidak valid');
}

// Koneksi database
$host = 'localhost';
$dbname = 'psppa_feedback';
$username = 'root';
$password = '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    ob_end_clean();
    die('Koneksi database gagal: ' . $e->getMessage());
}

// Fetch data penilaian berdasarkan ID
$stmt = $pdo->prepare("SELECT id, nama, nim, student_no, tempat_pka, alamat, preseptor, periode, cpmks, nilai_akhir, nilai_huruf FROM penilaian WHERE id = ?");
$stmt->execute([$id]);
$nilai = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$nilai) {
    ob_end_clean();
    die('Data penilaian tidak ditemukan');
}

// Tentukan kategori PKA
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
$pka_category = getPkaCategory($nilai['tempat_pka'] ?? '');

// Tanggal saat ini
date_default_timezone_set('Asia/Jakarta');
$today_date_only = date('d F Y');
$today = date('h:i A WIB, l, d F Y');

// Parse CPMKs dari JSON
$cpmks = json_decode($nilai['cpmks'] ?? '[]', true) ?: [];
$maxCpmkCount = count($cpmks) > 0 ? count($cpmks) : 4; // Minimal 4 CPMK jika kosong

// Kelas TCPDF custom tanpa header/footer default
class MYPDF extends TCPDF
{
    public function Header() {}
    public function Footer() {}
}

// Inisialisasi TCPDF
$pdf = new MYPDF('L', 'pt', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Sistem PSPPA UNSRI');
$pdf->SetAuthor('Sistem PSPPA UNSRI');
$pdf->SetTitle('Lembar Penilaian PKPA');
$pdf->SetMargins(20, 20, 20); // Reduced margins to fit more content
$pdf->AddPage();

// --- JUDUL ---
$pdf->SetFont('times', 'B', 16);
$pdf->Cell(0, 14, 'LEMBAR PENILAIAN', 0, 1, 'C');
$pdf->Cell(0, 14, 'PRAKTEK KERJA PROFESI APOTEKER BIDANG FARMASI ' . strtoupper($pka_category), 0, 1, 'C');
$pdf->Ln(15);

// --- INFORMASI PKPA ---
$pdf->SetFont('times', '', 12);
$info = [
    'Tempat PKPA'   => $nilai['tempat_pka'] ?: '-',
    'Alamat'        => $nilai['alamat'] ?: '-',
    'Pembimbing'    => $nilai['preseptor'] ?: '-',
    'Periode PKPA'  => $nilai['periode'] ?: '-',
];

foreach ($info as $label => $value) {
    $pdf->SetFont('times', 'B', 12);
    $pdf->Cell(100, 12, $label, 0, 0); // Reduced width for label
    $pdf->SetFont('times', '', 12);
    $pdf->Cell(0, 12, ': ' . htmlspecialchars($value), 0, 1);
}
$pdf->Ln(10);

// --- TABEL NILAI ---
$pdf->SetFont('times', 'B', 12);

// Header tabel "NILAI" menyatu dengan tabel (colspan semua kolom)
$totalCols = 3 + $maxCpmkCount + 2; // No, NIM, Nama + CPMK + Nilai Akhir + Nilai Huruf
$totalWidth = 776; // Adjusted to fit within A4 landscape (816 pt - 40 pt margins)
$colWidth = floor(($totalWidth - (30 + 80 + 149)) / ($maxCpmkCount + 2)); // Dynamic width for CPMK and other columns
$pdf->Cell($totalWidth, 25, 'NILAI', 1, 1, 'C');

// Header kolom
$header = ['No', 'NIM', 'Nama'];
for ($i = 1; $i <= $maxCpmkCount; $i++) {
    $header[] = "CPMK $i";
}
$header[] = 'Nilai Akhir';
$header[] = 'Nilai Huruf';

$w = [30, 80, 150]; // Fixed widths for No, NIM, Nama
for ($i = 0; $i < $maxCpmkCount; $i++) $w[] = $colWidth; // Dynamic CPMK width
$w[] = $colWidth; // Nilai Akhir
$w[] = $colWidth; // Nilai Huruf

foreach ($header as $i => $h) {
    $pdf->Cell($w[$i], 25, $h, 1, 0, 'C');
}
$pdf->Ln();

// --- DATA TABEL (1 mahasiswa) ---
$pdf->SetFont('times', '', 11);
$data = [
    $nilai['student_no'] ?: '1',
    $nilai['nim'] ?: '-',
    $nilai['nama'] ?: '-'
];
for ($i = 0; $i < $maxCpmkCount; $i++) {
    $data[] = isset($cpmks[$i]) ? htmlspecialchars($cpmks[$i]) : '-';
}
$data[] = $nilai['nilai_akhir'] ?: '-';
$data[] = strtoupper($nilai['nilai_huruf'] ?: '-');

foreach ($data as $i => $d) {
    $pdf->Cell($w[$i], 20, htmlspecialchars($d), 1, 0, 'C');
}
$pdf->Ln(30);

// --- FOOTER CETAK & TANDA TANGAN ---
$pdf->Ln(50); // Reduced spacing

$pdf->SetFont('times', 'I', 10);
$pdf->Cell(0, 12, "Tanggal Cetak: $today", 0, 0, 'L'); // kiri

$pdf->SetFont('times', '', 12);
$nama_kota = htmlspecialchars(substr($nilai['alamat'] ?: '.....................', 0, 25));
$pdf->Cell(0, 12, $nama_kota . ', ' . $today_date_only, 0, 1, 'R'); // kanan (sejajar)

// spasi untuk tanda tangan
$pdf->Ln(50);

// tanda tangan preseptor
$pdf->SetFont('times', '', 12);
$pdf->Cell(0, 12, '(' . htmlspecialchars($nilai['preseptor'] ?: '-') . ')', 0, 1, 'R');

// Output PDF
ob_end_clean();
$pdf->Output('penilaian_pkpa.pdf', 'D');
exit;
