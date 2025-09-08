document.addEventListener("DOMContentLoaded", () => {
  // Validasi form login
  const loginForm = document.getElementById("login-form");
  if (loginForm) {
    loginForm.addEventListener("submit", (e) => {
      const username = document.getElementById("username").value;
      const password = document.getElementById("password").value;
      if (username.length < 3) {
        e.preventDefault();
        alert("Username harus minimal 3 karakter.");
      } else if (password.length < 8) {
        e.preventDefault();
        alert("Password harus minimal 8 karakter.");
      }
    });
  }

  // Validasi form tambah pengguna (admin.php)
  const userForm = document.getElementById("user-form");
  if (userForm) {
    userForm.addEventListener("submit", (e) => {
      const username = document.getElementById("username").value;
      const password = document.getElementById("password").value;
      const nama = document.getElementById("nama").value;
      if (username.length < 3) {
        e.preventDefault();
        alert("Username harus minimal 3 karakter.");
      } else if (password.length < 8) {
        e.preventDefault();
        alert("Password harus minimal 8 karakter.");
      } else if (nama.length < 2) {
        e.preventDefault();
        alert("Nama harus minimal 2 karakter.");
      }
    });
  }

  // Validasi form feedback mahasiswa
  const feedbackMahasiswaForm = document.getElementById(
    "feedback-mahasiswa-form"
  );
  if (feedbackMahasiswaForm) {
    feedbackMahasiswaForm.addEventListener("submit", (e) => {
      const mahasiswa = document.getElementById("mahasiswa").value;
      const komentar = document.getElementById("komentar_mahasiswa").value;
      if (!mahasiswa) {
        e.preventDefault();
        alert("Pilih mahasiswa terlebih dahulu.");
      }
      if (komentar.length < 10) {
        e.preventDefault();
        alert("Komentar harus minimal 10 karakter.");
      }
    });
  }

  // Validasi form feedback prodi
  const feedbackProdiForm = document.getElementById("feedback-prodi-form");
  if (feedbackProdiForm) {
    feedbackProdiForm.addEventListener("submit", (e) => {
      const prodi = document.getElementById("prodi").value;
      const komentar = document.getElementById("komentar_prodi").value;
      if (prodi.length < 3) {
        e.preventDefault();
        alert("Nama prodi harus minimal 3 karakter.");
      }
      if (komentar.length < 10) {
        e.preventDefault();
        alert("Komentar harus minimal 10 karakter.");
      }
    });
  }

  // Validasi form ubah username/password
  const changePasswordForm = document.getElementById("change-password-form");
  if (changePasswordForm) {
    changePasswordForm.addEventListener("submit", (e) => {
      const username = document.getElementById("username").value;
      const oldPassword = document.getElementById("old_password").value;
      const newPassword = document.getElementById("new_password").value;
      const confirmPassword = document.getElementById("confirm_password").value;
      if (username.length < 3) {
        e.preventDefault();
        alert("Username harus minimal 3 karakter.");
      }
      if (oldPassword.length < 8) {
        e.preventDefault();
        alert("Password lama harus minimal 8 karakter.");
      }
      if (newPassword.length < 8) {
        e.preventDefault();
        alert("Password baru harus minimal 8 karakter.");
      }
      if (newPassword !== confirmPassword) {
        e.preventDefault();
        alert("Password baru dan konfirmasi tidak sama.");
      }
    });
  }

  // Validasi form penilaian PKPA
  const penilaianForm = document.getElementById("penilaian-form");
  if (penilaianForm) {
    penilaianForm.addEventListener("submit", (e) => {
      e.preventDefault(); // Mencegah submit default

      // Validasi input shared
      const tempat_pka = document.getElementById("tempat_pka").value;
      const alamat = document.getElementById("alamat").value;
      const preseptor = document.getElementById("preseptor").value;
      const periode = document.getElementById("periode").value;

      if (tempat_pka.length < 3) {
        alert("Tempat PKA harus minimal 3 karakter.");
        return;
      }
      if (alamat.length < 10) {
        alert("Alamat harus minimal 10 karakter.");
        return;
      }
      if (preseptor.length < 3) {
        alert("Preseptor/Pembimbing harus minimal 3 karakter.");
        return;
      }
      if (periode.length < 10) {
        alert("Periode PKA harus minimal 10 karakter.");
        return;
      }

      // Validasi setiap baris mahasiswa
      const rows = document.querySelectorAll(".student-row");
      if (rows.length === 0) {
        alert("Tambahkan setidaknya satu mahasiswa!");
        return;
      }

      for (const row of rows) {
        const nim = row.querySelector(".nim").value;
        const nama = row.querySelector(".nama").value;
        const cpmks = Array.from(row.querySelectorAll(".cpmk")).map((input) =>
          parseInt(input.value)
        );
        const nilai_akhir = parseInt(row.querySelector(".nilai_akhir").value);
        const nilai_huruf = row.querySelector(".nilai_huruf").value;

        if (nim.length < 5) {
          alert(
            `NIM di baris ${
              row.querySelector(".no").value
            } harus minimal 5 karakter.`
          );
          return;
        }
        if (nama.length < 3) {
          alert(
            `Nama di baris ${
              row.querySelector(".no").value
            } harus minimal 3 karakter.`
          );
          return;
        }
        for (let i = 0; i < cpmks.length; i++) {
          if (isNaN(cpmks[i]) || cpmks[i] < 1 || cpmks[i] > 100) {
            alert(
              `CPMK ${i + 1} di baris ${
                row.querySelector(".no").value
              } harus antara 1-100.`
            );
            return;
          }
        }
        if (isNaN(nilai_akhir) || nilai_akhir < 0 || nilai_akhir > 100) {
          alert(
            `Nilai Akhir Total di baris ${
              row.querySelector(".no").value
            } harus antara 0-100.`
          );
          return;
        }
        if (!["A", "B", "C", "D"].includes(nilai_huruf.toUpperCase())) {
          alert(
            `Nilai Huruf di baris ${
              row.querySelector(".no").value
            } harus A, B, C, atau D.`
          );
          return;
        }
      }

      // Jika validasi lolos, lanjutkan ke generatePDF
      generatePDF();
    });

    // Attach validation to the "Download PDF" button
    const downloadButton = document.querySelector(
      'button[onclick="generatePDF()"]'
    );
    if (downloadButton) {
      downloadButton.addEventListener("click", (e) => {
        e.preventDefault();
        penilaianForm.dispatchEvent(new Event("submit"));
      });
    }
  }
});
