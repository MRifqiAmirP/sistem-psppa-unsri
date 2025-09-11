<nav class="navbar">
    <div class="container">
        <!-- Logo di kiri -->
        <div class="navbar-logo">
            <img src="Logo.png" alt="Logo">
            <div class="navbar-title">Program Studi Pendidikan Profesi Apoteker</div>
        </div>
        <!-- Menu utama -->
        <div class="navbar-menu">
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'preceptor') { ?>
                <a id="nav-feedback" class="nav-link" href="feedback.php">Formulir Feedback</a>
                <a id="nav-penilaian" class="nav-link" href="penilaian.php">Formulir Penilaian</a>
            <?php } elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') { ?>
                <a id="nav-admin-users" class="nav-link" href="admin_users.php">Manajemen Pengguna</a>
                <a id="nav-admin-data" class="nav-link" href="admin_data.php">Manajemen Data</a>
            <?php } elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'mahasiswa') { ?>
                <a id="nav-logbook" class="nav-link" href="logbook.php">Logbook</a>
                <a id="nav-saran" class="nav-link" href="saran.php">Saran</a>
            <?php } ?>
            <!-- Titik tiga (dropdown user) -->
            <div class="navbar-user dropdown">
                <button class="dropbtn">⋮</button>
                <div class="dropdown-content">
                    <span class="user-name">
                        <?php echo $_SESSION['username'] ?? 'Pengguna'; ?>
                    </span>
                    <a href="ubah_akun.php">Ubah Username dan Password</a>
                    <a href="logout.php">Logout</a>
                </div>
            </div>
        </div>
    </div>
</nav>
<!-- JS untuk dropdown -->
<script>
    document.querySelectorAll(".dropbtn").forEach(btn => {
        btn.addEventListener("click", function(e) {
            e.stopPropagation();
            const menu = this.nextElementSibling;
            menu.classList.toggle("show");
        });
    });
    window.addEventListener("click", function() {
        document.querySelectorAll(".dropdown-content").forEach(menu => {
            menu.classList.remove("show");
        });
    });
</script>