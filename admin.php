<?php
require 'auth_check.php';
protect_page(['Owner', 'Admin']);
require 'config.php';

// Pastikan sesi id_user dan data pengguna tersinkronisasi
if (!isset($_SESSION['id_user']) && isset($_SESSION['username'])) {
    $q_u = $conn->prepare("SELECT id, nama_lengkap, role FROM users WHERE username = ?");
    $q_u->bind_param("s", $_SESSION['username']);
    $q_u->execute();
    $res_u = $q_u->get_result();
    if ($r = $res_u->fetch_assoc()) {
        $_SESSION['id_user'] = $r['id'];
        if (empty($_SESSION['nama_lengkap'])) {
            $_SESSION['nama_lengkap'] = $r['nama_lengkap'];
        }
        if (empty($_SESSION['role'])) {
            $_SESSION['role'] = $r['role'];
        }
    }
}

$pesan_sukses = "";
$pesan_error = "";

// 1. Logika Tambah Stok Masuk (Inbound Stock - saat belanja bahan baku)
if (isset($_POST['tambah_stok_masuk'])) {
    $id_bahan = intval($_POST['id_bahan']);
    $jumlah_masuk = floatval($_POST['jumlah_masuk']);
    $catatan = trim($_POST['catatan_masuk']);

    if ($jumlah_masuk > 0) {
        $stmt_in = $conn->prepare("UPDATE bahan_baku SET stok = stok + ? WHERE id = ?");
        $stmt_in->bind_param("di", $jumlah_masuk, $id_bahan);
        if ($stmt_in->execute()) {
            $pesan_sukses = "Berhasil menambahkan stok bahan baku sebanyak $jumlah_masuk!";
        } else {
            $pesan_error = "Gagal memperbarui stok: " . $conn->error;
        }
    } else {
        $pesan_error = "Jumlah stok masuk harus lebih dari 0.";
    }
}

// 2. Logika Tambah Bahan Baku Baru
if (isset($_POST['tambah_bahan_baru'])) {
    $nama_bahan = trim($_POST['nama_bahan']);
    $satuan = trim($_POST['satuan']);
    $stok_awal = floatval($_POST['stok_awal']);
    $batas_minimum = floatval($_POST['batas_minimum']);

    if (!empty($nama_bahan) && !empty($satuan)) {
        $stmt_new = $conn->prepare("INSERT INTO bahan_baku (nama_bahan, stok, satuan, batas_minimum) VALUES (?, ?, ?, ?)");
        $stmt_new->bind_param("sdsd", $nama_bahan, $stok_awal, $satuan, $batas_minimum);
        if ($stmt_new->execute()) {
            $pesan_sukses = "Bahan baku baru '$nama_bahan' berhasil didaftarkan!";
        } else {
            $pesan_error = "Gagal menambahkan bahan baku: " . $conn->error;
        }
    }
}

// 2b. Logika Tambah Menu Produk Baru (Makanan / Minuman)
if (isset($_POST['tambah_produk_baru'])) {
    $nama_produk = trim($_POST['nama_produk']);
    $harga = floatval($_POST['harga']);
    $kategori = trim($_POST['kategori']);

    if (!empty($nama_produk) && $harga > 0) {
        $stmt_p = $conn->prepare("INSERT INTO produk (nama_produk, harga, kategori) VALUES (?, ?, ?)");
        $stmt_p->bind_param("sds", $nama_produk, $harga, $kategori);
        if ($stmt_p->execute()) {
            $pesan_sukses = "Menu produk baru '$nama_produk' berhasil ditambahkan ke daftar kasir!";
        } else {
            $pesan_error = "Gagal menambahkan menu produk: " . $conn->error;
        }
    }
}

// 2c. Logika Manajemen Pengguna & Hak Akses RBAC (Khusus Hak Akses Owner)
if (is_owner()) {
    // Tambah Pengguna Baru
    if (isset($_POST['tambah_pengguna'])) {
        $user_new = trim($_POST['username']);
        $pass_new = trim($_POST['password']);
        $nama_new = trim($_POST['nama_lengkap']);
        $email_new = trim($_POST['email']);
        $role_new = trim($_POST['role']);

        if (!in_array($role_new, ['Owner', 'Admin', 'Kasir'])) {
            $pesan_error = "Peran (role) yang dipilih tidak valid!";
        } elseif (empty($user_new) || empty($pass_new) || empty($nama_new)) {
            $pesan_error = "Nama lengkap, username, dan password wajib diisi!";
        } else {
            // Cek apakah username sudah ada
            $cek = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $cek->bind_param("s", $user_new);
            $cek->execute();
            if ($cek->get_result()->num_rows > 0) {
                $pesan_error = "Username '$user_new' sudah digunakan! Silakan gunakan username lain.";
            } else {
                $ins = $conn->prepare("INSERT INTO users (username, password, nama_lengkap, email, role) VALUES (?, ?, ?, ?, ?)");
                $ins->bind_param("sssss", $user_new, $pass_new, $nama_new, $email_new, $role_new);
                if ($ins->execute()) {
                    $pesan_sukses = "Pengguna baru '$nama_new' (@$user_new) dengan peran '$role_new' berhasil didaftarkan!";
                } else {
                    $pesan_error = "Gagal mendaftarkan pengguna: " . $conn->error;
                }
            }
        }
    }

    // Edit Pengguna / Role
    if (isset($_POST['edit_pengguna'])) {
        $id_target = intval($_POST['id_user']);
        $nama_target = trim($_POST['nama_lengkap']);
        $email_target = trim($_POST['email']);
        $role_target = trim($_POST['role']);
        $pass_target = trim($_POST['password']);

        if (!in_array($role_target, ['Owner', 'Admin', 'Kasir'])) {
            $pesan_error = "Peran (role) yang dipilih tidak valid!";
        } elseif (empty($nama_target)) {
            $pesan_error = "Nama lengkap tidak boleh kosong!";
        } else {
            if (!empty($pass_target)) {
                $stmt_up = $conn->prepare("UPDATE users SET nama_lengkap = ?, email = ?, role = ?, password = ? WHERE id = ?");
                $stmt_up->bind_param("ssssi", $nama_target, $email_target, $role_target, $pass_target, $id_target);
            } else {
                $stmt_up = $conn->prepare("UPDATE users SET nama_lengkap = ?, email = ?, role = ? WHERE id = ?");
                $stmt_up->bind_param("sssi", $nama_target, $email_target, $role_target, $id_target);
            }
            if ($stmt_up->execute()) {
                if (isset($_SESSION['id_user']) && $_SESSION['id_user'] == $id_target) {
                    $_SESSION['nama_lengkap'] = $nama_target;
                    $_SESSION['role'] = $role_target;
                }
                $pesan_sukses = "Data dan hak akses pengguna berhasil diperbarui!";
            } else {
                $pesan_error = "Gagal memperbarui pengguna: " . $conn->error;
            }
        }
    }

    // Hapus Pengguna
    if (isset($_POST['hapus_pengguna'])) {
        $id_del = intval($_POST['id_user_hapus']);
        if (isset($_SESSION['id_user']) && $id_del == $_SESSION['id_user']) {
            $pesan_error = "Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif!";
        } else {
            $stmt_del = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt_del->bind_param("i", $id_del);
            if ($stmt_del->execute()) {
                $pesan_sukses = "Pengguna berhasil dihapus dari sistem.";
            } else {
                $pesan_error = "Gagal menghapus pengguna: " . $conn->error;
            }
        }
    }
}

// 3. Ambil Data Metrik KPI
// Omzet Hari Ini
$q_today = $conn->query("SELECT COALESCE(SUM(total_pembayaran), 0) AS omzet_today, COUNT(*) AS trx_today FROM transaksi WHERE DATE(tanggal_transaksi) = CURDATE()");
$kpi_today = $q_today->fetch_assoc();

// Total Seluruh Transaksi & Omzet Akumulasi
$q_all = $conn->query("SELECT COALESCE(SUM(total_pembayaran), 0) AS total_omzet, COUNT(*) AS total_trx FROM transaksi");
$kpi_all = $q_all->fetch_assoc();

// Peringatan Stok Menipis (Stok <= Batas Minimum)
$q_alert = $conn->query("SELECT COUNT(*) AS total_menipis FROM bahan_baku WHERE stok <= batas_minimum");
$kpi_alert = $q_alert->fetch_assoc();

// Ambil Seluruh Data Bahan Baku
$bahan_baku_list = [];
$res_bahan = $conn->query("SELECT * FROM bahan_baku ORDER BY nama_bahan ASC");
if ($res_bahan) {
    while ($b = $res_bahan->fetch_assoc()) {
        $bahan_baku_list[] = $b;
    }
}

// Ambil Riwayat Transaksi Terbaru (Limit 10)
$transaksi_terbaru = [];
$res_trx = $conn->query("SELECT * FROM transaksi ORDER BY id DESC LIMIT 10");
if ($res_trx) {
    while ($t = $res_trx->fetch_assoc()) {
        $transaksi_terbaru[] = $t;
    }
}

// 4. Ambil Seluruh Data Pengguna (Khusus Role Owner)
$users_list = [];
if (is_owner()) {
    $res_users = $conn->query("SELECT id, username, nama_lengkap, email, role FROM users ORDER BY FIELD(role, 'Owner', 'Admin', 'Kasir'), id ASC");
    if ($res_users) {
        while ($u = $res_users->fetch_assoc()) {
            $users_list[] = $u;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin & Persediaan - Fillah Food</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #ea580c;
            --primary-hover: #c2410c;
            --primary-light: #fff7ed;
            --secondary: #f59e0b;
            --sidebar-bg: #0f172a;
            --sidebar-hover: #1e293b;
            --bg-canvas: #f8fafc;
            --bg-card: #ffffff;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --success: #16a34a;
            --warning: #d97706;
            --danger: #dc2626;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            background-color: var(--bg-canvas);
            color: var(--text-dark);
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        /* Sidebar Kiri */
        .sidebar {
            width: 260px;
            background-color: var(--sidebar-bg);
            color: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 24px 16px;
            flex-shrink: 0;
        }

        .brand-section {
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 24px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .brand-icon {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            box-shadow: 0 4px 12px rgba(234, 88, 12, 0.4);
        }

        .brand-details h2 {
            font-size: 16px;
            font-weight: 800;
            letter-spacing: -0.3px;
        }

        .brand-details p {
            font-size: 11px;
            color: #94a3b8;
        }

        .nav-menu {
            margin-top: 24px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: #cbd5e1;
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 600;
            border-radius: 10px;
            transition: all 0.2s;
        }

        .nav-item:hover, .nav-item.active {
            background-color: var(--sidebar-hover);
            color: #ffffff;
        }

        .nav-item.active {
            background-color: var(--primary);
            box-shadow: 0 4px 12px rgba(234, 88, 12, 0.35);
        }

        .sidebar-footer {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding-top: 16px;
        }

        .btn-switch-mode {
            width: 100%;
            background: #1e293b;
            color: #38bdf8;
            border: 1px solid #0284c7;
            padding: 10px;
            border-radius: 10px;
            font-size: 12.5px;
            font-weight: 700;
            text-align: center;
            text-decoration: none;
            display: block;
            margin-bottom: 8px;
            transition: background 0.15s;
        }

        .btn-switch-mode:hover {
            background: #0284c7;
            color: #ffffff;
        }

        .btn-logout {
            width: 100%;
            background: rgba(220, 38, 38, 0.15);
            color: #f87171;
            border: 1px solid rgba(220, 38, 38, 0.3);
            padding: 10px;
            border-radius: 10px;
            font-size: 12.5px;
            font-weight: 700;
            text-align: center;
            text-decoration: none;
            display: block;
            transition: all 0.15s;
        }

        .btn-logout:hover {
            background: #dc2626;
            color: #ffffff;
        }

        /* Konten Utama */
        .main-content {
            flex: 1;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }

        /* Top Header */
        .topbar {
            background: #ffffff;
            border-bottom: 1.5px solid var(--border);
            padding: 16px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
        }

        .topbar-title h1 {
            font-size: 20px;
            font-weight: 800;
            color: var(--text-dark);
        }

        .topbar-title p {
            font-size: 12.5px;
            color: var(--text-muted);
        }

        .admin-profile {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .avatar {
            width: 40px;
            height: 40px;
            background: #ffedd5;
            color: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 800;
            border: 2px solid #fed7aa;
        }

        /* Content Body */
        .content-body {
            padding: 28px 32px;
            display: flex;
            flex-direction: column;
            gap: 28px;
        }

        .alert-box {
            padding: 14px 18px;
            border-radius: 12px;
            font-size: 13.5px;
            font-weight: 600;
        }

        .alert-success {
            background-color: #dcfce7;
            color: var(--success);
            border: 1px solid #bbf7d0;
        }

        .alert-danger {
            background-color: #fee2e2;
            color: var(--danger);
            border: 1px solid #fecaca;
        }

        /* KPI Metric Cards */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
        }

        .kpi-card {
            background: var(--bg-card);
            border: 1.5px solid var(--border);
            border-radius: 18px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.02);
            position: relative;
            overflow: hidden;
        }

        .kpi-title {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .kpi-value {
            font-size: 24px;
            font-weight: 800;
            color: var(--text-dark);
            margin: 8px 0 4px 0;
        }

        .kpi-desc {
            font-size: 11.5px;
            color: var(--text-muted);
        }

        .kpi-card.alert-card {
            border-color: #fde68a;
            background: #fffbeb;
        }

        .kpi-card.alert-card .kpi-value {
            color: var(--warning);
        }

        /* Layout 2 Kolom: Persediaan Stok & Riwayat */
        .section-grid {
            display: grid;
            grid-template-columns: 1.4fr 1fr;
            gap: 24px;
        }

        @media (max-width: 1024px) {
            .section-grid {
                grid-template-columns: 1fr;
            }
        }

        .panel-card {
            background: var(--bg-card);
            border: 1.5px solid var(--border);
            border-radius: 18px;
            padding: 22px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
        }

        .panel-header h2 {
            font-size: 16px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary-sm {
            background: var(--primary);
            color: #ffffff;
            border: none;
            padding: 8px 14px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 10px -2px rgba(234, 88, 12, 0.4);
            transition: all 0.15s;
        }

        .btn-primary-sm:hover {
            background: var(--primary-hover);
        }

        /* Table Styling */
        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            text-align: left;
        }

        th {
            background: #f8fafc;
            color: var(--text-muted);
            font-weight: 700;
            padding: 10px 14px;
            border-bottom: 1.5px solid var(--border);
            font-size: 11.5px;
            text-transform: uppercase;
        }

        td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border);
            color: var(--text-dark);
        }

        tr:last-child td {
            border-bottom: none;
        }

        /* Badges */
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            display: inline-block;
        }

        .badge-safe {
            background: #dcfce7;
            color: var(--success);
            border: 1px solid #bbf7d0;
        }

        .badge-warning {
            background: #fef3c7;
            color: var(--warning);
            border: 1px solid #fde68a;
        }

        .badge-danger {
            background: #fee2e2;
            color: var(--danger);
            border: 1px solid #fecaca;
        }

        .badge-role-owner {
            background: #fef3c7;
            color: #b45309;
            border: 1px solid #fde68a;
        }

        .badge-role-admin {
            background: #e0f2fe;
            color: #0369a1;
            border: 1px solid #bae6fd;
        }

        .badge-role-kasir {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        /* Form Modal Input Stok */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 100;
        }

        .modal-box {
            background: #ffffff;
            width: 100%;
            max-width: 440px;
            border-radius: 18px;
            padding: 24px;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2);
        }

        .modal-title {
            font-size: 17px;
            font-weight: 800;
            margin-bottom: 16px;
        }

        .form-group {
            margin-bottom: 14px;
        }

        .form-label {
            display: block;
            font-size: 12.5px;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-size: 13.5px;
            outline: none;
        }

        .form-control:focus {
            border-color: var(--primary);
        }

        .modal-footer {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
    </style>
</head>
<body>

    <!-- Sidebar Kiri -->
    <aside class="sidebar">
        <div>
            <div class="brand-section">
                <div class="brand-icon">🍗</div>
                <div class="brand-details">
                    <h2>Fillah Food</h2>
                    <p>Dashboard Manajemen</p>
                </div>
            </div>

            <nav class="nav-menu">
                <a href="admin.php" class="nav-item active">
                    <span>📊</span> Ringkasan & Dasbor
                </a>
                <a href="#stok-persediaan" class="nav-item">
                    <span>📦</span> Stok Bahan Baku
                </a>
                <a href="#riwayat-transaksi" class="nav-item">
                    <span>🧾</span> Riwayat Penjualan
                </a>
                <?php if (is_owner()): ?>
                <a href="#manajemen-pengguna" class="nav-item">
                    <span>👥</span> Manajemen Pengguna (RBAC)
                </a>
                <?php endif; ?>
                <a href="menu.php" target="_blank" class="nav-item">
                    <span>📱</span> E-Menu Pelanggan
                </a>
            </nav>
        </div>

        <div class="sidebar-footer">
            <a href="kasir.php" class="btn-switch-mode">
                🛒 Buka Layar Kasir POS
            </a>
            <a href="logout.php" class="btn-logout">
                Keluar (Logout)
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Topbar -->
        <header class="topbar">
            <div class="topbar-title">
                <h1>Dasbor Manajemen & Persediaan</h1>
                <p>Sistem Informasi Terintegrasi UMKM Fillah Food - Capstone Project STSI4440 UT</p>
            </div>

            <div class="admin-profile">
                <div class="avatar" style="<?php echo (is_owner()) ? 'background: linear-gradient(135deg, #f59e0b, #d97706);' : 'background: linear-gradient(135deg, #0284c7, #0369a1);'; ?>">
                    <?php 
                        $nama_disp = !empty($_SESSION['nama_lengkap']) ? $_SESSION['nama_lengkap'] : $_SESSION['username'];
                        echo strtoupper(substr($nama_disp, 0, 1)); 
                    ?>
                </div>
                <div>
                    <div style="font-size: 13.5px; font-weight: 700;">
                        <?php echo htmlspecialchars($nama_disp); ?>
                    </div>
                    <div style="font-size: 11px; margin-top: 2px;">
                        <?php if (is_owner()): ?>
                            <span class="badge badge-role-owner" style="font-size: 10px; padding: 2px 8px;">👑 Owner (Pemilik Usaha)</span>
                        <?php else: ?>
                            <span class="badge badge-role-admin" style="font-size: 10px; padding: 2px 8px;">⚙️ Admin (Staf Persediaan)</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </header>

        <!-- Body Content -->
        <div class="content-body">

            <?php if (!empty($pesan_sukses)): ?>
                <div class="alert-box alert-success">✅ <?php echo htmlspecialchars($pesan_sukses); ?></div>
            <?php endif; ?>

            <?php if (!empty($pesan_error)): ?>
                <div class="alert-box alert-danger">⚠️ <?php echo htmlspecialchars($pesan_error); ?></div>
            <?php endif; ?>

            <!-- KPI Cards -->
            <div class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-title">Omzet Penjualan Hari Ini</div>
                    <div class="kpi-value" style="color: var(--primary);">
                        Rp <?php echo number_format($kpi_today['omzet_today'], 0, ',', '.'); ?>
                    </div>
                    <div class="kpi-desc">Dari <?php echo $kpi_today['trx_today']; ?> transaksi kasir hari ini</div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-title">Total Pendapatan Akumulasi</div>
                    <div class="kpi-value">
                        Rp <?php echo number_format($kpi_all['total_omzet'], 0, ',', '.'); ?>
                    </div>
                    <div class="kpi-desc">Total <?php echo $kpi_all['total_trx']; ?> transaksi tercatat di database</div>
                </div>

                <div class="kpi-card <?php echo ($kpi_alert['total_menipis'] > 0) ? 'alert-card' : ''; ?>">
                    <div class="kpi-title">Peringatan Bahan Menipis</div>
                    <div class="kpi-value">
                        <?php echo $kpi_alert['total_menipis']; ?> Bahan
                    </div>
                    <div class="kpi-desc">
                        <?php echo ($kpi_alert['total_menipis'] > 0) ? '⚠️ Segera lakukan belanja bahan ke pasar!' : 'Semua persediaan bahan baku aman.'; ?>
                    </div>
                </div>
            </div>

            <!-- 2 Kolom: Manajemen Persediaan & Riwayat Transaksi -->
            <div class="section-grid">
                
                <!-- Kolom Kiri: Tabel Stok Persediaan Bahan Baku -->
                <div class="panel-card" id="stok-persediaan">
                    <div class="panel-header">
                        <h2><span>📦</span> Persediaan Bahan Baku (Inventory)</h2>
                        <div style="display: flex; gap: 8px;">
                            <button class="btn-primary-sm" style="background:var(--secondary); color:#000;" onclick="bukaModalProdukBaru()">+ Menu Produk Baru</button>
                            <button class="btn-primary-sm" onclick="bukaModalStokMasuk()">+ Stok Masuk</button>
                            <button class="btn-primary-sm" style="background:#475569;" onclick="bukaModalBahanBaru()">+ Bahan Baku</button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Nama Bahan</th>
                                    <th>Stok Saat Ini</th>
                                    <th>Batas Minimum</th>
                                    <th>Status Stok</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bahan_baku_list as $b): ?>
                                    <?php 
                                        $stok = floatval($b['stok']);
                                        $min = floatval($b['batas_minimum']);
                                        if ($stok <= 0) {
                                            $badge_class = "badge-danger";
                                            $badge_label = "Habis / Kritis";
                                        } elseif ($stok <= $min) {
                                            $badge_class = "badge-warning";
                                            $badge_label = "Menipis";
                                        } else {
                                            $badge_class = "badge-safe";
                                            $badge_label = "Stok Aman";
                                        }
                                    ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($b['nama_bahan']); ?></strong></td>
                                        <td>
                                            <span style="font-size: 15px; font-weight: 800;"><?php echo rtrim(rtrim(number_format($stok, 2, '.', ''), '0'), '.'); ?></span> 
                                            <?php echo htmlspecialchars($b['satuan']); ?>
                                        </td>
                                        <td style="color: var(--text-muted);">
                                            <?php echo rtrim(rtrim(number_format($min, 2, '.', ''), '0'), '.'); ?> <?php echo htmlspecialchars($b['satuan']); ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $badge_class; ?>"><?php echo $badge_label; ?></span>
                                        </td>
                                        <td>
                                            <button style="background: none; border: 1px solid var(--border); padding: 4px 8px; border-radius: 6px; cursor: pointer; font-size: 11px; font-weight: 700;" onclick="isiStokCepat(<?php echo $b['id']; ?>, '<?php echo addslashes($b['nama_bahan']); ?>', '<?php echo $b['satuan']; ?>')">
                                                + Tambah
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Kolom Kanan: Riwayat Transaksi Kasir -->
                <div class="panel-card" id="riwayat-transaksi">
                    <div class="panel-header">
                        <h2><span>🧾</span> Transaksi Kasir Terakhir</h2>
                        <span style="font-size: 11px; color: var(--text-muted);">10 Transaksi Terakhir</span>
                    </div>

                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>No. Nota</th>
                                    <th>Waktu</th>
                                    <th>Total Tagihan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($transaksi_terbaru)): ?>
                                    <tr>
                                        <td colspan="3" style="text-align: center; color: var(--text-muted); padding: 20px;">
                                            Belum ada transaksi penjualan yang tercatat.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($transaksi_terbaru as $trx): ?>
                                        <tr>
                                            <td>
                                                <strong>#TRX-<?php echo str_pad($trx['id'], 4, "0", STR_PAD_LEFT); ?></strong>
                                            </td>
                                            <td style="font-size: 11.5px; color: var(--text-muted);">
                                                <?php echo date('d/m/Y H:i', strtotime($trx['tanggal_transaksi'])); ?>
                                            </td>
                                            <td style="font-weight: 800; color: var(--primary);">
                                                Rp <?php echo number_format($trx['total_pembayaran'], 0, ',', '.'); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            <!-- Modul Manajemen Pengguna & Hak Akses (RBAC) -->
            <?php if (is_owner()): ?>
            <div class="panel-card" id="manajemen-pengguna" style="margin-top: 24px;">
                <div class="panel-header">
                    <div>
                        <h2><span>👥</span> Manajemen Pengguna & Hak Akses (RBAC)</h2>
                        <p style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                            Pengendalian hak akses berbasis peran (Role-Based Access Control) untuk Owner, Admin, dan Kasir.
                        </p>
                    </div>
                    <button class="btn-primary-sm" style="background: linear-gradient(135deg, var(--primary), var(--secondary)); display: flex; align-items: center; gap: 6px;" onclick="bukaModalPenggunaBaru()">
                        <span>➕</span> Tambah Pengguna Baru
                    </button>
                </div>

                <!-- Ringkasan Peran RBAC -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 14px; margin-bottom: 20px;">
                    <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 12px; padding: 14px;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                            <span style="font-size: 18px;">👑</span>
                            <strong style="font-size: 13px; color: #b45309;">Owner (Pemilik Usaha)</strong>
                        </div>
                        <div style="font-size: 11.5px; color: #78350f; line-height: 1.4;">
                            Akses mutlak & penuh: Pengawasan omzet real-time, stok persediaan, formula resep BOM, menu produk, serta manajemen akun & role RBAC.
                        </div>
                    </div>

                    <div style="background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 12px; padding: 14px;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                            <span style="font-size: 18px;">⚙️</span>
                            <strong style="font-size: 13px; color: #0369a1;">Admin (Staf Persediaan)</strong>
                        </div>
                        <div style="font-size: 11.5px; color: #075985; line-height: 1.4;">
                            Akses operasional gudang: Input pasokan stok bahan masuk, tambah bahan baku baru, manajemen harga menu, dan pantau riwayat penjualan.
                        </div>
                    </div>

                    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 14px;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
                            <span style="font-size: 18px;">🛒</span>
                            <strong style="font-size: 13px; color: #15803d;">Kasir (Staf Penjualan)</strong>
                        </div>
                        <div style="font-size: 11.5px; color: #14532d; line-height: 1.4;">
                            Akses transaksi POS: Layar pencatatan pesanan kasir, perhitungan otomatis, cetak struk nota, dan pemotongan stok bahan otomatis.
                        </div>
                    </div>
                </div>

                <!-- Tabel Pengguna Terdaftar -->
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Pengguna</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Hak Akses (Role)</th>
                                <th>Izin Akses Halaman</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users_list as $u): ?>
                                <tr>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <div style="width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 13px;
                                                <?php 
                                                    if ($u['role'] === 'Owner') echo 'background: #fef3c7; color: #b45309; border: 1px solid #fde68a;';
                                                    elseif ($u['role'] === 'Admin') echo 'background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd;';
                                                    else echo 'background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0;';
                                                ?>">
                                                <?php echo strtoupper(substr($u['nama_lengkap'] ?: $u['username'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <strong><?php echo htmlspecialchars($u['nama_lengkap'] ?: $u['username']); ?></strong>
                                                <?php if (isset($_SESSION['id_user']) && $u['id'] == $_SESSION['id_user']): ?>
                                                    <span style="font-size: 10px; background: #e2e8f0; color: #475569; padding: 1px 6px; border-radius: 10px; margin-left: 4px;">Akun Anda</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><code>@<?php echo htmlspecialchars($u['username']); ?></code></td>
                                    <td style="color: var(--text-muted); font-size: 12px;">
                                        <?php echo htmlspecialchars($u['email'] ?: 'Belum diisi'); ?>
                                    </td>
                                    <td>
                                        <?php if ($u['role'] === 'Owner'): ?>
                                            <span class="badge badge-role-owner">👑 Owner</span>
                                        <?php elseif ($u['role'] === 'Admin'): ?>
                                            <span class="badge badge-role-admin">⚙️ Admin</span>
                                        <?php else: ?>
                                            <span class="badge badge-role-kasir">🛒 Kasir</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size: 12px; color: var(--text-muted);">
                                        <?php 
                                            if ($u['role'] === 'Owner') echo 'Semua Layar + Manajemen Pengguna';
                                            elseif ($u['role'] === 'Admin') echo 'Dashboard & Stok Bahan (admin.php, kasir.php)';
                                            else echo 'Hanya Layar Kasir POS (kasir.php)';
                                        ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 6px;">
                                            <button type="button" style="background: #f1f5f9; border: 1px solid var(--border); padding: 5px 10px; border-radius: 6px; cursor: pointer; font-size: 11.5px; font-weight: 700; color: var(--text-dark);"
                                                onclick="bukaModalEditPengguna(<?php echo $u['id']; ?>, '<?php echo addslashes($u['nama_lengkap']); ?>', '<?php echo addslashes($u['username']); ?>', '<?php echo addslashes($u['email']); ?>', '<?php echo $u['role']; ?>')">
                                                ✏️ Edit
                                            </button>
                                            <?php if (!isset($_SESSION['id_user']) || $u['id'] != $_SESSION['id_user']): ?>
                                                <form method="POST" action="" onsubmit="return confirm('Apakah Anda yakin ingin menghapus akun @<?php echo addslashes($u['username']); ?>?');" style="display: inline;">
                                                    <input type="hidden" name="id_user_hapus" value="<?php echo $u['id']; ?>">
                                                    <button type="submit" name="hapus_pengguna" style="background: #fee2e2; border: 1px solid #fecaca; color: #dc2626; padding: 5px 10px; border-radius: 6px; cursor: pointer; font-size: 11.5px; font-weight: 700;">
                                                        🗑️ Hapus
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <button disabled style="background: #f1f5f9; border: 1px solid var(--border); color: #94a3b8; padding: 5px 10px; border-radius: 6px; font-size: 11.5px; cursor: not-allowed;" title="Tidak dapat menghapus akun sendiri">
                                                    🔒 Aktif
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php else: ?>
            <!-- Jika Login Sebagai Admin (Non-Owner), Tampilkan Keterangan RBAC Bahwa Manajemen Pengguna Dibatasi -->
            <div class="panel-card" style="margin-top: 24px; background: #f8fafc; border: 1.5px dashed #cbd5e1; padding: 24px; text-align: center;">
                <div style="font-size: 32px; margin-bottom: 8px;">🛡️</div>
                <h3 style="font-size: 15px; font-weight: 800; color: #1e293b; margin-bottom: 6px;">
                    Hak Akses Manajemen Pengguna Dibatasi (RBAC)
                </h3>
                <p style="font-size: 12.5px; color: var(--text-muted); max-width: 600px; margin: 0 auto; line-height: 1.5;">
                    Anda saat ini masuk dengan hak akses <strong>Admin (Staf Pengelola Persediaan)</strong>. Sesuai arsitektur keamanan <em>Role-Based Access Control (RBAC)</em>, modul penambahan akun dan perubahan peran pengguna hanya dapat diakses oleh akun <strong>Owner (Ibu Nihayatus Sofiyah)</strong>.
                </p>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <!-- Modal Form Tambah Stok Masuk -->
    <div class="modal-overlay" id="modal-stok-masuk">
        <div class="modal-box">
            <h3 class="modal-title">📥 Catat Stok Masuk (Belanja Bahan)</h3>
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">Pilih Bahan Baku</label>
                    <select name="id_bahan" id="select-id-bahan" class="form-control" required>
                        <?php foreach ($bahan_baku_list as $b): ?>
                            <option value="<?php echo $b['id']; ?>">
                                <?php echo htmlspecialchars($b['nama_bahan']); ?> (Satuan: <?php echo htmlspecialchars($b['satuan']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Jumlah Pasokan Masuk (<span id="satuan-label">satuan</span>)</label>
                    <input type="number" step="0.01" name="jumlah_masuk" class="form-control" placeholder="Contoh: 10 atau 2.5" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Catatan Belanja / Suplier (Opsional)</label>
                    <input type="text" name="catatan_masuk" class="form-control" placeholder="Contoh: Belanja di Pasar Rogojampi / Grosir">
                </div>

                <div class="modal-footer">
                    <button type="submit" name="tambah_stok_masuk" class="btn-primary-sm" style="flex:1; padding:12px;">Simpan Stok Masuk</button>
                    <button type="button" class="btn-primary-sm" style="background:#cbd5e1; color:#0f172a;" onclick="tutupModalStokMasuk()">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Form Tambah Bahan Baru -->
    <div class="modal-overlay" id="modal-bahan-baru">
        <div class="modal-box">
            <h3 class="modal-title">✨ Tambah Bahan Baku Baru</h3>
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">Nama Bahan Baku</label>
                    <input type="text" name="nama_bahan" class="form-control" placeholder="Contoh: Bawang Merah, Saus Tiram" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Satuan Ukuran</label>
                    <input type="text" name="satuan" class="form-control" placeholder="Contoh: kg, liter, butir, bungkus, potong" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Stok Awal</label>
                    <input type="number" step="0.01" name="stok_awal" class="form-control" placeholder="0" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Ambang Batas Minimum (Peringatan)</label>
                    <input type="number" step="0.01" name="batas_minimum" class="form-control" placeholder="Contoh: 2" required>
                </div>

                <div class="modal-footer">
                    <button type="submit" name="tambah_bahan_baru" class="btn-primary-sm" style="flex:1; padding:12px;">Daftarkan Bahan</button>
                    <button type="button" class="btn-primary-sm" style="background:#cbd5e1; color:#0f172a;" onclick="tutupModalBahanBaru()">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Form Tambah Menu Produk Baru -->
    <div class="modal-overlay" id="modal-produk-baru">
        <div class="modal-box">
            <h3 class="modal-title">🍗 Tambah Menu Makanan / Minuman Baru</h3>
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">Nama Menu Makanan / Minuman</label>
                    <input type="text" name="nama_produk" class="form-control" placeholder="Contoh: Ayam Bakar Madu" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Kategori Menu</label>
                    <select name="kategori" class="form-control" required>
                        <option value="Makanan">Makanan</option>
                        <option value="Minuman">Minuman</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Harga Jual Satuan (Rp)</label>
                    <input type="number" step="500" name="harga" class="form-control" placeholder="Contoh: 12000" required>
                </div>

                <div class="modal-footer">
                    <button type="submit" name="tambah_produk_baru" class="btn-primary-sm" style="flex:1; padding:12px;">Simpan Menu Baru</button>
                    <button type="button" class="btn-primary-sm" style="background:#cbd5e1; color:#0f172a;" onclick="tutupModalProdukBaru()">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Form Tambah Pengguna Baru (Khusus Owner) -->
    <?php if (is_owner()): ?>
    <div class="modal-overlay" id="modal-pengguna-baru">
        <div class="modal-box">
            <h3 class="modal-title">👥 Tambah Pengguna Baru (RBAC)</h3>
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">Nama Lengkap Pengguna</label>
                    <input type="text" name="nama_lengkap" class="form-control" placeholder="Contoh: Aisyah Marta Dewi" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Username (Nama Pengguna untuk Login)</label>
                    <input type="text" name="username" class="form-control" placeholder="Contoh: kasir_2" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Kata Sandi (Password Awal)</label>
                    <input type="password" name="password" class="form-control" placeholder="Masukkan kata sandi awal" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Alamat Email (Opsional / Notifikasi)</label>
                    <input type="email" name="email" class="form-control" placeholder="Contoh: user@fillahfood.com">
                </div>

                <div class="form-group">
                    <label class="form-label">Pilih Peran Hak Akses (Role)</label>
                    <select name="role" class="form-control" required>
                        <option value="Kasir">🛒 Kasir (Hanya Layar Transaksi POS)</option>
                        <option value="Admin">⚙️ Admin (Staf Pengelola Persediaan & Menu)</option>
                        <option value="Owner">👑 Owner (Pemilik Usaha - Akses Penuh)</option>
                    </select>
                </div>

                <div class="modal-footer">
                    <button type="submit" name="tambah_pengguna" class="btn-primary-sm" style="flex:1; padding:12px;">Simpan Pengguna</button>
                    <button type="button" class="btn-primary-sm" style="background:#cbd5e1; color:#0f172a;" onclick="tutupModalPenggunaBaru()">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Form Edit Pengguna & Peran (Khusus Owner) -->
    <div class="modal-overlay" id="modal-edit-pengguna">
        <div class="modal-box">
            <h3 class="modal-title">✏️ Edit Pengguna & Peran Hak Akses</h3>
            <form method="POST" action="">
                <input type="hidden" name="id_user" id="edit-id-user">

                <div class="form-group">
                    <label class="form-label">Username (Akun)</label>
                    <input type="text" id="edit-username" class="form-control" disabled style="background: #f1f5f9; cursor: not-allowed;">
                </div>

                <div class="form-group">
                    <label class="form-label">Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" id="edit-nama-lengkap" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Alamat Email</label>
                    <input type="email" name="email" id="edit-email" class="form-control">
                </div>

                <div class="form-group">
                    <label class="form-label">Peran Hak Akses (Role)</label>
                    <select name="role" id="edit-role" class="form-control" required>
                        <option value="Kasir">🛒 Kasir (Hanya Layar Transaksi POS)</option>
                        <option value="Admin">⚙️ Admin (Staf Pengelola Persediaan & Menu)</option>
                        <option value="Owner">👑 Owner (Pemilik Usaha - Akses Penuh)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Reset Password Baru (Kosongkan jika tidak ingin ganti)</label>
                    <input type="password" name="password" class="form-control" placeholder="Ketik sandi baru jika ingin diubah">
                </div>

                <div class="modal-footer">
                    <button type="submit" name="edit_pengguna" class="btn-primary-sm" style="flex:1; padding:12px;">Simpan Perubahan</button>
                    <button type="button" class="btn-primary-sm" style="background:#cbd5e1; color:#0f172a;" onclick="tutupModalEditPengguna()">Batal</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        function bukaModalStokMasuk() {
            document.getElementById('modal-stok-masuk').style.display = 'flex';
        }

        function tutupModalStokMasuk() {
            document.getElementById('modal-stok-masuk').style.display = 'none';
        }

        function bukaModalBahanBaru() {
            document.getElementById('modal-bahan-baru').style.display = 'flex';
        }

        function tutupModalBahanBaru() {
            document.getElementById('modal-bahan-baru').style.display = 'none';
        }

        function bukaModalProdukBaru() {
            document.getElementById('modal-produk-baru').style.display = 'flex';
        }

        function tutupModalProdukBaru() {
            document.getElementById('modal-produk-baru').style.display = 'none';
        }

        function bukaModalPenggunaBaru() {
            var el = document.getElementById('modal-pengguna-baru');
            if (el) el.style.display = 'flex';
        }

        function tutupModalPenggunaBaru() {
            var el = document.getElementById('modal-pengguna-baru');
            if (el) el.style.display = 'none';
        }

        function bukaModalEditPengguna(id, nama, user, email, role) {
            document.getElementById('edit-id-user').value = id;
            document.getElementById('edit-nama-lengkap').value = nama;
            document.getElementById('edit-username').value = '@' + user;
            document.getElementById('edit-email').value = email;
            document.getElementById('edit-role').value = role;
            var el = document.getElementById('modal-edit-pengguna');
            if (el) el.style.display = 'flex';
        }

        function tutupModalEditPengguna() {
            var el = document.getElementById('modal-edit-pengguna');
            if (el) el.style.display = 'none';
        }

        function isiStokCepat(id, nama, satuan) {
            document.getElementById('select-id-bahan').value = id;
            document.getElementById('satuan-label').textContent = satuan;
            bukaModalStokMasuk();
        }
    </script>
</body>
</html>
