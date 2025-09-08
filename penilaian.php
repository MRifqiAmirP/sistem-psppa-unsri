<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'auth.php';
redirectIfNotRole('preceptor');

// Buat CSRF token jika belum ada
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Tanggal saat ini
date_default_timezone_set('Asia/Jakarta');
$today = date('h:i A WIB, l, d F Y');
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulir Penilaian PKPA</title>
    <link rel="stylesheet" href="css/styles.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
</head>

<body>
    <?php include 'navbar.php'; ?>
    <section class="feedback-section">
        <h2 class="main-title">Formulir Penilaian PKPA</h2>
        <div class="feedback-card">
            <h3 class="card-title">Penilaian Mahasiswa</h3>
            <form id="penilaian-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="form-group">
                    <label for="tempat_pka">Tempat PKPA</label>
                    <select id="tempat_pka" name="tempat_pka" required>
                        <option value="" disabled selected>Pilih Tempat PKPA</option>
                        <option value="Rumah Sakit">Rumah Sakit</option>
                        <option value="Apotek">Apotek</option>
                        <option value="Puskesmas">Puskesmas</option>
                        <option value="Pedagang Besar Farmasi">Pedagang Besar Farmasi</option>
                        <option value="Industri">Industri</option>
                        <option value="BPOM">BPOM</option>
                        <option value="Dinas Kesehatan">Dinas Kesehatan</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="alamat">Alamat</label>
                    <input type="text" id="alamat" name="alamat" placeholder="Masukkan alamat" required>
                </div>
                <div class="form-group">
                    <label for="preseptor">Preseptor/Pembimbing</label>
                    <input type="text" id="preseptor" name="preseptor" placeholder="Masukkan Pembimbing" required>
                </div>
                <div class=" form-group">
                    <label for="periode">Periode PKPA</label>
                    <input type="text" id="periode" name="periode" placeholder="10 Januari 2025 - 10 Februari 2025" required>
                </div>
                <div class="form-group">
                    <label for="student-rows">Data Mahasiswa</label>
                    <div id="student-rows">
                        <div class="student-row">
                            <div class="form-group">
                                <input type="text" class="no" value="1" readonly>
                            </div>
                            <div class="form-group">
                                <input type="text" class="nim" placeholder="NIM" required>
                            </div>
                            <div class="form-group">
                                <input type="text" class="nama" placeholder="Nama" required>
                            </div>
                            <div class="form-group cpmk-fields">
                                <input type="number" class="cpmk" placeholder="CPMK 1" min="1" max="100" required>
                                <input type="number" class="cpmk" placeholder="CPMK 2" min="1" max="100" required>
                                <input type="number" class="cpmk" placeholder="CPMK 3" min="1" max="100" required>
                                <input type="number" class="cpmk" placeholder="CPMK 4" min="1" max="100" required>
                            </div>
                            <div class="form-group">
                                <input type="number" class="nilai_akhir" placeholder="Nilai Akhir" required>
                            </div>
                            <div class="form-group">
                                <input type="text" class="nilai_huruf" placeholder="Nilai Huruf" required>
                            </div>
                            <div class="form-group">
                                <button type="button" class="btn-remove">Hapus</button>
                            </div>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn-primary" onclick="addStudentRow()">Tambah Mahasiswa</button>
                <button type="button" class="btn-primary" onclick="addCpmk()">Tambah CPMK</button>
                <button type="submit" class="btn-primary">Simpan</button>
                <button type="button" class="btn-download" onclick="generatePDF()">Download PDF</button>
            </form>
        </div>
    </section>
    <?php include 'footer.php'; ?>

    <script>
        let cpmkCount = 4;

        function updateRowNumbers() {
            const rows = document.querySelectorAll(".student-row");
            rows.forEach((row, index) => {
                const noInput = row.querySelector(".no");
                noInput.value = index + 1;
            });
        }

        function addStudentRow() {
            const studentRows = document.getElementById("student-rows");
            const row = document.createElement("div");
            row.className = "student-row";

            const noGroup = document.createElement("div");
            noGroup.className = "form-group";
            const noInput = document.createElement("input");
            noInput.type = "text";
            noInput.className = "no";
            noInput.value = document.querySelectorAll(".student-row").length + 1;
            noInput.readOnly = true;
            noGroup.appendChild(noInput);

            const nimGroup = document.createElement("div");
            nimGroup.className = "form-group";
            const nimInput = document.createElement("input");
            nimInput.type = "text";
            nimInput.className = "nim";
            nimInput.placeholder = "NIM";
            nimInput.required = true;
            nimGroup.appendChild(nimInput);

            const namaGroup = document.createElement("div");
            namaGroup.className = "form-group";
            const namaInput = document.createElement("input");
            namaInput.type = "text";
            namaInput.className = "nama";
            namaInput.placeholder = "Nama";
            namaInput.required = true;
            namaGroup.appendChild(namaInput);

            const cpmkFields = document.createElement("div");
            cpmkFields.className = "form-group cpmk-fields";
            for (let i = 1; i <= cpmkCount; i++) {
                const cpmkInput = document.createElement("input");
                cpmkInput.type = "number";
                cpmkInput.className = "cpmk";
                cpmkInput.placeholder = `CPMK ${i}`;
                cpmkInput.min = "1";
                cpmkInput.max = "100";
                cpmkInput.required = true;
                cpmkFields.appendChild(cpmkInput);
            }

            const akhirGroup = document.createElement("div");
            akhirGroup.className = "form-group";
            const akhirInput = document.createElement("input");
            akhirInput.type = "number";
            akhirInput.className = "nilai_akhir";
            akhirInput.placeholder = "Nilai Akhir";
            akhirInput.required = true;
            akhirGroup.appendChild(akhirInput);

            const hurufGroup = document.createElement("div");
            hurufGroup.className = "form-group";
            const hurufInput = document.createElement("input");
            hurufInput.type = "text";
            hurufInput.className = "nilai_huruf";
            hurufInput.placeholder = "Nilai Huruf";
            hurufInput.required = true;
            hurufGroup.appendChild(hurufInput);

            const removeGroup = document.createElement("div");
            removeGroup.className = "form-group";
            const removeButton = document.createElement("button");
            removeButton.type = "button";
            removeButton.className = "btn-remove";
            removeButton.textContent = "Hapus";
            removeGroup.appendChild(removeButton);

            row.appendChild(noGroup);
            row.appendChild(nimGroup);
            row.appendChild(namaGroup);
            row.appendChild(cpmkFields);
            row.appendChild(akhirGroup);
            row.appendChild(hurufGroup);
            row.appendChild(removeGroup);

            studentRows.appendChild(row);

            removeButton.addEventListener("click", () => {
                row.remove();
                updateRowNumbers();
            });

            updateRowNumbers();
        }

        function addCpmk() {
            cpmkCount++;
            const rows = document.querySelectorAll(".student-row");
            rows.forEach(row => {
                const cpmkFields = row.querySelector(".cpmk-fields");
                const newCpmk = document.createElement("input");
                newCpmk.type = "number";
                newCpmk.className = "cpmk";
                newCpmk.placeholder = `CPMK ${cpmkCount}`;
                newCpmk.min = "1";
                newCpmk.max = "100";
                newCpmk.required = true;
                cpmkFields.appendChild(newCpmk);
            });
        }

        document.querySelectorAll(".student-row").forEach(row => {
            row.querySelector(".btn-remove").addEventListener("click", () => {
                row.remove();
                updateRowNumbers();
            });
        });

        const penilaianForm = document.getElementById("penilaian-form");
        if (penilaianForm) {
            penilaianForm.addEventListener("submit", async (e) => {
                e.preventDefault();

                const tempat_pka = document.getElementById("tempat_pka").value;
                const alamat = document.getElementById("alamat").value;
                const preseptor = document.getElementById("preseptor").value;
                const periode = document.getElementById("periode").value;
                const rows = document.querySelectorAll(".student-row");

                for (const row of rows) {
                    const nim = row.querySelector(".nim").value;
                    const nama = row.querySelector(".nama").value;
                    const cpmks = Array.from(row.querySelectorAll(".cpmk")).map((input) =>
                        parseInt(input.value)
                    );
                    const nilai_akhir = row.querySelector(".nilai_akhir").value;
                    const nilai_huruf = row.querySelector(".nilai_huruf").value;

                    if (nim.length < 5) {
                        alert(`NIM di baris ${row.querySelector(".no").value} harus minimal 5 karakter.`);
                        return;
                    }
                    if (nama.length < 3) {
                        alert(`Nama di baris ${row.querySelector(".no").value} harus minimal 3 karakter.`);
                        return;
                    }
                    for (let i = 0; i < cpmks.length; i++) {
                        if (isNaN(cpmks[i]) || cpmks[i] < 1 || cpmks[i] > 100) {
                            alert(`CPMK ${i + 1} di baris ${row.querySelector(".no").value} harus antara 1-100.`);
                            return;
                        }
                    }
                    if (!nilai_akhir && nilai_akhir !== 0) { // Cek kosong, termasuk 0
                        alert(`Nilai Akhir di baris ${row.querySelector(".no").value} wajib diisi.`);
                        return;
                    }
                    if (!["A", "B", "C", "D"].includes(nilai_huruf.toUpperCase())) {
                        alert(`Nilai Huruf di baris ${row.querySelector(".no").value} harus A, B, C, atau D.`);
                        return;
                    }
                }

                const formData = new FormData();
                formData.append("csrf_token", document.querySelector('input[name="csrf_token"]').value);
                formData.append("tempat_pka", tempat_pka);
                formData.append("alamat", alamat);
                formData.append("preseptor", preseptor);
                formData.append("periode", periode);
                const students = Array.from(rows).map((row, index) => ({
                    no: row.querySelector(".no").value,
                    nim: row.querySelector(".nim").value,
                    nama: row.querySelector(".nama").value,
                    cpmks: Array.from(row.querySelectorAll(".cpmk")).map(input => parseInt(input.value)),
                    nilai_akhir: parseFloat(row.querySelector(".nilai_akhir").value),
                    nilai_huruf: row.querySelector(".nilai_huruf").value
                }));
                formData.append("students", JSON.stringify(students));

                try {
                    const response = await fetch("submit_penilaian.php", {
                        method: "POST",
                        body: formData
                    });
                    const text = await response.text();
                    console.log("Raw response:", text);
                    const result = JSON.parse(text);
                    if (result.success) {
                        alert("Penilaian berhasil disimpan!");
                    } else {
                        alert("Gagal menyimpan penilaian: " + result.message);
                    }
                } catch (error) {
                    console.error("Error:", error);
                    alert("Terjadi kesalahan: " + error.message);
                }
            });
        }

        async function generatePDF() {
            try {
                const form = document.getElementById("penilaian-form");
                const inputs = form.querySelectorAll("input[required], select[required]");
                let valid = true;
                inputs.forEach(input => {
                    if (!input.value.trim()) {
                        valid = false;
                        input.classList.add("error");
                    } else {
                        input.classList.remove("error");
                    }
                });

                if (!valid) {
                    alert("Semua field wajib diisi!");
                    return;
                }

                const tempat_pka = document.getElementById("tempat_pka").value;
                const alamat = document.getElementById("alamat").value;
                const preseptor = document.getElementById("preseptor").value;
                const periode = document.getElementById("periode").value;
                const tanggal = periode.match(/\d{1,2}\s+[A-Za-z]+\s+\d{4}$/)?.[0] || ".....................";

                const rows = document.querySelectorAll(".student-row");
                const data = [];
                rows.forEach(row => {
                    const no = row.querySelector(".no").value;
                    const nim = row.querySelector(".nim").value;
                    const nama = row.querySelector(".nama").value;
                    const cpmks = Array.from(row.querySelectorAll(".cpmk")).map(input => input.value);
                    const nilai_akhir = row.querySelector(".nilai_akhir").value;
                    const nilai_huruf = row.querySelector(".nilai_huruf").value;

                    data.push({
                        no,
                        nim,
                        nama,
                        cpmks,
                        nilai_akhir,
                        nilai_huruf
                    });
                });

                const {
                    jsPDF
                } = window.jspdf;
                const doc = new jsPDF({
                    orientation: "landscape",
                    unit: "pt",
                    format: "a4"
                });

                const margin = 33;
                const pageWidth = 842 - 2 * margin;

                doc.setFont("times", "bold");
                doc.setFontSize(14);
                doc.text("LEMBAR PENILAIAN", 421, 40 + margin, {
                    align: "center"
                });
                doc.text(`PRAKTEK KERJA PROFESI APOTEKER BIDANG FARMASI ${tempat_pka.toUpperCase()}`, 421, 60 + margin, {
                    align: "center"
                });

                doc.setFont("times", "normal");
                doc.setFontSize(10);
                doc.setFont("times", "bold");
                doc.text("Tempat PKPA", margin, 90 + margin);
                doc.setFont("times", "normal");
                doc.text(":          " + tempat_pka, margin + 80, 90 + margin);

                doc.setFont("times", "bold");
                doc.text("Alamat", margin, 120 + margin);
                doc.setFont("times", "normal");
                doc.text(":          " + alamat, margin + 80, 120 + margin);

                doc.setFont("times", "bold");
                doc.text("Pembimbing", margin, 150 + margin);
                doc.setFont("times", "normal");
                doc.text(":          " + preseptor, margin + 80, 150 + margin);

                doc.setFont("times", "bold");
                doc.text("Periode PKPA", margin, 180 + margin);
                doc.setFont("times", "normal");
                doc.text(":          " + periode, margin + 80, 180 + margin);

                doc.setFont("times", "normal");
                doc.setFontSize(10);
                doc.setLineWidth(1);

                const maxCpmkCount = Math.max(...Array.from(rows).map(row => row.querySelectorAll(".cpmk").length));
                const noWidth = 30;
                const nimWidth = 80;
                const namaWidth = 150;
                const cpmkWidth = 70;
                const akhirWidth = 70;
                const hurufWidth = 70;
                const totalCpmkWidth = maxCpmkCount * cpmkWidth;
                const totalTableWidth = noWidth + nimWidth + namaWidth + totalCpmkWidth + akhirWidth + hurufWidth;

                const tableStartX = margin + (pageWidth - totalTableWidth) / 2;
                const scoreStartX = tableStartX;
                const scoreWidth = totalTableWidth;

                let currentY = 250 + margin;

                doc.setFont("times", "bold");
                doc.setFontSize(12);
                doc.rect(scoreStartX, 210 + margin - 1, scoreWidth, 40 + 1);
                doc.text("Nilai", scoreStartX + scoreWidth / 2, 225 + margin, {
                    align: "center"
                });

                let startX = tableStartX;
                doc.setFont("times", "bold");
                doc.rect(startX, currentY, noWidth, 40);
                doc.text("No", startX + noWidth / 2, currentY + 15, {
                    align: "center"
                });
                startX += noWidth;

                doc.rect(startX, currentY, nimWidth, 40);
                doc.text("NIM", startX + nimWidth / 2, currentY + 15, {
                    align: "center"
                });
                startX += nimWidth;

                doc.rect(startX, currentY, namaWidth, 40);
                doc.text("NAMA", startX + namaWidth / 2, currentY + 15, {
                    align: "center"
                });
                startX += namaWidth;

                for (let i = 1; i <= maxCpmkCount; i++) {
                    doc.rect(startX, currentY, cpmkWidth, 40);
                    doc.text(`CPMK ${i}`, startX + cpmkWidth / 2, currentY + 15, {
                        align: "center"
                    });
                    startX += cpmkWidth;
                }

                doc.rect(startX, currentY, akhirWidth, 40);
                doc.text("Nilai Akhir\nTotal", startX + akhirWidth / 2, currentY + 10, {
                    align: "center"
                });
                startX += akhirWidth;

                doc.rect(startX, currentY, hurufWidth, 40);
                doc.text("Nilai Huruf\n(A, B, C, D)", startX + hurufWidth / 2, currentY + 10, {
                    align: "center"
                });

                currentY += 40;
                data.forEach(rowData => {
                    startX = tableStartX;
                    doc.setFont("times", "normal");
                    doc.rect(startX, currentY, noWidth, 20);
                    doc.text(rowData.no, startX + noWidth / 2, currentY + 10, {
                        align: "center"
                    });
                    startX += noWidth;

                    doc.rect(startX, currentY, nimWidth, 20);
                    doc.text(rowData.nim, startX + nimWidth / 2, currentY + 10, {
                        align: "center"
                    });
                    startX += nimWidth;

                    doc.rect(startX, currentY, namaWidth, 20);
                    doc.text(rowData.nama, startX + namaWidth / 2, currentY + 10, {
                        align: "center"
                    });
                    startX += namaWidth;

                    rowData.cpmks.forEach(cpmk => {
                        doc.rect(startX, currentY, cpmkWidth, 20);
                        doc.text(cpmk, startX + cpmkWidth / 2, currentY + 10, {
                            align: "center"
                        });
                        startX += cpmkWidth;
                    });

                    for (let i = rowData.cpmks.length; i < maxCpmkCount; i++) {
                        doc.rect(startX, currentY, cpmkWidth, 20);
                        doc.text("-", startX + cpmkWidth / 2, currentY + 10, {
                            align: "center"
                        });
                        startX += cpmkWidth;
                    }

                    doc.rect(startX, currentY, akhirWidth, 20);
                    doc.text(rowData.nilai_akhir, startX + akhirWidth / 2, currentY + 10, {
                        align: "center"
                    });
                    startX += akhirWidth;

                    doc.rect(startX, currentY, hurufWidth, 20);
                    doc.text(rowData.nilai_huruf.toUpperCase(), startX + hurufWidth / 2, currentY + 10, {
                        align: "center"
                    });
                    currentY += 20;
                });

                doc.setFont("times", "normal");
                doc.setFontSize(10);
                doc.text(`......................., ${tanggal}`, margin + 560, currentY + 50);
                doc.text(`(${preseptor})`, margin + 560, currentY + 110);
                doc.text("Tanggal Cetak: <?php echo $today; ?>", margin, currentY + 90);

                doc.save("penilaian_pkpa.pdf");
            } catch (error) {
                console.error("Error generating PDF:", error);
                alert("Gagal menghasilkan PDF: " + error.message);
            }
        }
    </script>
    <script src="js/script.js"></script>
</body>

</html>