<?php
session_start();
require 'config.php';

// Pastikan hanya role Admin yang bisa mengakses halaman ini
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'Admin') {
    header("Location: index.php");
    exit();
}

$pesan_sukses = "";
$pesan_error = "";

// --- Migrasi Otomatis: Tambah kolom gambar jika belum ada (kompatibel MySQL 5.x) ---
$db_name = $conn->query("SELECT DATABASE()")->fetch_row()[0];
$chk_produk = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='$db_name' AND TABLE_NAME='produk' AND COLUMN_NAME='gambar'")->num_rows;
if ($chk_produk === 0) $conn->query("ALTER TABLE produk ADD COLUMN gambar VARCHAR(255) NULL");
$chk_bahan = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='$db_name' AND TABLE_NAME='bahan_baku' AND COLUMN_NAME='gambar'")->num_rows;
if ($chk_bahan === 0) $conn->query("ALTER TABLE bahan_baku ADD COLUMN gambar VARCHAR(255) NULL");

// Helper: Proses Upload Gambar (opsional)
function prosesUploadGambar(string $field, string $prefix): string {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        return ''; // Tidak ada file atau error, kembalikan kosong (pakai gambar default)
    }
    $tmp  = $_FILES[$field]['tmp_name'];
    $name = $_FILES[$field]['name'];
    $size = $_FILES[$field]['size'];
    $mime = mime_content_type($tmp);

    $allowed_mime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($mime, $allowed_mime)) return '';
    if ($size > 2 * 1024 * 1024) return ''; // Maks 2 MB

    $ext      = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $filename = $prefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest     = __DIR__ . '/uploads/' . $filename;

    return move_uploaded_file($tmp, $dest) ? 'uploads/' . $filename : '';
}

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
    $nama_bahan    = trim($_POST['nama_bahan']);
    $satuan        = trim($_POST['satuan']);
    $stok_awal     = floatval($_POST['stok_awal']);
    $batas_minimum = floatval($_POST['batas_minimum']);

    if (!empty($nama_bahan) && !empty($satuan)) {
        $gambar_bahan = prosesUploadGambar('gambar_bahan', 'bahan');
        $stmt_new = $conn->prepare("INSERT INTO bahan_baku (nama_bahan, stok, satuan, batas_minimum, gambar) VALUES (?, ?, ?, ?, ?)");
        $stmt_new->bind_param("sdsds", $nama_bahan, $stok_awal, $satuan, $batas_minimum, $gambar_bahan);
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
    $harga       = floatval($_POST['harga']);
    $kategori    = trim($_POST['kategori']);

    if (!empty($nama_produk) && $harga > 0) {
        $gambar_produk = prosesUploadGambar('gambar_produk', 'produk');
        $stmt_p = $conn->prepare("INSERT INTO produk (nama_produk, harga, kategori, gambar) VALUES (?, ?, ?, ?)");
        $stmt_p->bind_param("sdss", $nama_produk, $harga, $kategori, $gambar_produk);
        if ($stmt_p->execute()) {
            $pesan_sukses = "Menu produk baru '$nama_produk' berhasil ditambahkan ke daftar kasir!";
        } else {
            $pesan_error = "Gagal menambahkan menu produk: " . $conn->error;
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
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background-color: var(--bg-canvas);
            color: var(--text-dark);
            display: flex;
            height: 100vh;
            height: 100dvh;
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
            z-index: 100;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(4px);
            z-index: 90;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.25s ease;
        }

        .brand-section {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .brand-brand-wrap {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-icon {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            box-shadow: 0 4px 12px rgba(234, 88, 12, 0.4);
            flex-shrink: 0;
        }

        .brand-details h2 {
            font-size: 16px;
            font-weight: 800;
            letter-spacing: -0.3px;
            color: #ffffff;
        }

        .brand-details p {
            font-size: 11px;
            color: #94a3b8;
        }

        .btn-close-sidebar {
            display: none;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: #ffffff;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            align-items: center;
            justify-content: center;
        }

        .nav-menu {
            margin-top: 20px;
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
            -webkit-overflow-scrolling: touch;
        }

        /* Top Header */
        .topbar {
            background: #ffffff;
            border-bottom: 1.5px solid var(--border);
            padding: clamp(12px, 2vw, 16px) clamp(16px, 3vw, 32px);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
            gap: 12px;
            box-shadow: var(--shadow-sm);
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn-hamburger {
            display: none;
            background: #f8fafc;
            border: 1.5px solid var(--border);
            color: var(--text-dark);
            width: 38px;
            height: 38px;
            border-radius: var(--radius-sm);
            font-size: 18px;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.15s;
            flex-shrink: 0;
        }

        .btn-hamburger:hover {
            background: #ffedd5;
            border-color: var(--primary);
            color: var(--primary);
        }

        .topbar-title h1 {
            font-size: clamp(16px, 2.5vw, 20px);
            font-weight: 800;
            color: var(--text-dark);
        }

        .topbar-title p {
            font-size: 12px;
            color: var(--text-muted);
        }

        .admin-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        .avatar {
            width: clamp(34px, 4vw, 40px);
            height: clamp(34px, 4vw, 40px);
            background: #ffedd5;
            color: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: clamp(15px, 2vw, 18px);
            font-weight: 800;
            border: 2px solid #fed7aa;
            flex-shrink: 0;
        }

        /* Content Body */
        .content-body {
            padding: clamp(14px, 2.5vw, 32px);
            display: flex;
            flex-direction: column;
            gap: clamp(16px, 2.5vw, 28px);
        }

        /* KPI Metric Cards */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: clamp(12px, 2vw, 18px);
        }

        .kpi-card {
            background: var(--bg-card);
            border: 1.5px solid var(--border);
            border-radius: var(--radius-lg);
            padding: clamp(14px, 2vw, 20px);
            box-shadow: var(--shadow-sm);
            position: relative;
            overflow: hidden;
        }

        .kpi-title {
            font-size: 11.5px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .kpi-value {
            font-size: clamp(20px, 3.5vw, 24px);
            font-weight: 800;
            color: var(--text-dark);
            margin: 6px 0 4px 0;
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
            gap: clamp(16px, 2.5vw, 24px);
        }

        .panel-card {
            background: var(--bg-card);
            border: 1.5px solid var(--border);
            border-radius: var(--radius-lg);
            padding: clamp(14px, 2.5vw, 22px);
            box-shadow: var(--shadow-sm);
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .panel-header h2 {
            font-size: clamp(14.5px, 2vw, 16px);
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
            border-radius: var(--radius-sm);
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 10px -2px rgba(234, 88, 12, 0.4);
            transition: all 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
        }

        .btn-primary-sm:hover {
            background: var(--primary-hover);
        }

        /* Table Styling */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12.5px;
            text-align: left;
        }

        th {
            background: #f8fafc;
            color: var(--text-muted);
            font-weight: 700;
            padding: 10px 12px;
            border-bottom: 1.5px solid var(--border);
            font-size: 11px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border);
            color: var(--text-dark);
            white-space: nowrap;
        }

        tr:last-child td {
            border-bottom: none;
        }

        /* Badges */
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

        /* Form Modal Input Stok */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 999;
            padding: 12px;
        }

        .modal-box {
            background: #ffffff;
            width: min(94vw, 440px);
            max-height: 90vh;
            max-height: 90dvh;
            overflow-y: auto;
            border-radius: var(--radius-lg);
            padding: clamp(16px, 4vw, 24px);
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.25);
            -webkit-overflow-scrolling: touch;
        }

        .modal-title {
            font-size: clamp(15px, 2.5vw, 17px);
            font-weight: 800;
            margin-bottom: 14px;
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
            border-radius: var(--radius-sm);
            font-size: 16px; /* 16px prevents iOS zoom */
            outline: none;
            background: #ffffff;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(234, 88, 12, 0.15);
        }

        .modal-footer {
            display: flex;
            gap: 10px;
            margin-top: 18px;
            flex-wrap: wrap;
        }


        .img-upload-area {
            border: 2px dashed #cbd5e1;
            border-radius: 10px;
            cursor: pointer;
            transition: border-color 0.2s, background 0.2s;
            overflow: hidden;
        }

        .img-upload-area:hover {
            border-color: var(--primary);
            background: #fff7ed;
        }

        .img-upload-preview {
            width: 100%;
            height: 110px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 6px;
            overflow: hidden;
        }

        .img-upload-icon {
            font-size: 32px;
            line-height: 1;
        }

        .img-upload-hint {
            font-size: 11.5px;
            color: #64748b;
            font-weight: 600;
        }

        /* ==========================================================================
           RESPONSIVE BREAKPOINTS FOR ADMIN DASHBOARD
           ========================================================================== */

        /* Tablets Landscape / Medium Desktops (769px - 1024px) */
        @media (max-width: 1024px) {
            .sidebar {
                width: 220px;
                padding: 18px 12px;
            }

            .section-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Mobile & Tablet Portrait (< 768px) */
        @media (max-width: 768px) {
            body {
                display: block;
                height: auto;
                min-height: 100vh;
                min-height: 100dvh;
                overflow-x: hidden;
                overflow-y: auto;
            }

            .main-content {
                height: auto;
                overflow: visible;
            }

            /* Off-canvas mobile sidebar drawer */
            .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                bottom: 0;
                width: 280px;
                max-width: 85vw;
                height: 100vh;
                height: 100dvh;
                transform: translateX(-100%);
                box-shadow: 0 0 35px rgba(0, 0, 0, 0.4);
            }

            body.sidebar-open .sidebar {
                transform: translateX(0);
            }

            .sidebar-overlay {
                display: block;
            }

            body.sidebar-open .sidebar-overlay {
                opacity: 1;
                pointer-events: auto;
            }

            .btn-close-sidebar {
                display: flex;
            }

            .btn-hamburger {
                display: flex;
            }

            .topbar {
                padding: 10px 14px;
            }

            .topbar-title p {
                display: none; /* Hide long subtitle on mobile */
            }

            .admin-profile-name {
                display: none; /* Show only avatar on small screens */
            }

            .section-grid {
                grid-template-columns: 1fr;
            }

            .kpi-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }

            .kpi-card {
                padding: 12px;
            }

            .kpi-value {
                font-size: 18px;
            }
        }

        /* Smallest Phones (< 440px) */
        @media (max-width: 440px) {
            .kpi-grid {
                grid-template-columns: 1fr;
            }

            .panel-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .panel-header .btn-primary-sm {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Overlay Backdrop untuk Mobile Sidebar Drawer -->
    <div class="sidebar-overlay" onclick="closeSidebar()"></div>

    <!-- Sidebar Kiri -->
    <aside class="sidebar">
        <div>
            <div class="brand-section">
                <div class="brand-brand-wrap">
                    <div class="brand-icon">🍗</div>
                    <div class="brand-details">
                        <h2>Fillah Food</h2>
                        <p>Dashboard Manajemen</p>
                    </div>
                </div>
                <button type="button" class="btn-close-sidebar" onclick="closeSidebar()" title="Tutup Menu">✕</button>
            </div>

            <nav class="nav-menu">
                <a href="admin.php" class="nav-item active">
                    <span>📊</span> Ringkasan & Dasbor
                </a>
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
            <div class="topbar-left">
                <button type="button" class="btn-hamburger" onclick="toggleSidebar()" aria-label="Buka Menu">☰</button>
                <div class="topbar-title">
                    <h1>Dasbor Manajemen & Persediaan</h1>
                    <p>Sistem Informasi Terintegrasi UMKM Fillah Food - Capstone Project STSI4440 UT</p>
                </div>
            </div>

            <div class="admin-profile">
                <div class="avatar">N</div>
                <div class="admin-profile-name">
                    <div style="font-size: 13.5px; font-weight: 700;">Ibu Nihayatus Sofiyah</div>
                    <div style="font-size: 11px; color: var(--text-muted);">Pemilik Usaha (Super Admin)</div>
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
            <form method="POST" action="" enctype="multipart/form-data">
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

                <div class="form-group">
                    <label class="form-label">📷 Foto Bahan Baku <span style="font-weight:400;color:#64748b;">(Opsional — maks 2 MB, JPG/PNG/WebP)</span></label>
                    <div class="img-upload-area" onclick="document.getElementById('input-gambar-bahan').click()">
                        <div class="img-upload-preview" id="preview-bahan">
                            <span class="img-upload-icon">📦</span>
                            <span class="img-upload-hint">Klik untuk pilih foto</span>
                        </div>
                    </div>
                    <input type="file" id="input-gambar-bahan" name="gambar_bahan" accept="image/*" style="display:none" onchange="previewGambar(this, 'preview-bahan')">
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
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-group">
                    <label class="form-label">Nama Menu Makanan / Minuman</label>
                    <input type="text" name="nama_produk" class="form-control" placeholder="Contoh: Ayam Bakar Madu" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Kategori Menu</label>
                    <select name="kategori" id="select-kategori-produk" class="form-control" required onchange="updateDefaultPreviewProduk(this.value)">
                        <option value="Makanan">Makanan</option>
                        <option value="Minuman">Minuman</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Harga Jual Satuan (Rp)</label>
                    <input type="number" step="500" name="harga" class="form-control" placeholder="Contoh: 12000" required>
                </div>

                <div class="form-group">
                    <label class="form-label">📷 Foto Menu <span style="font-weight:400;color:#64748b;">(Opsional — maks 2 MB, JPG/PNG/WebP)</span></label>
                    <div class="img-upload-area" onclick="document.getElementById('input-gambar-produk').click()">
                        <div class="img-upload-preview" id="preview-produk">
                            <span class="img-upload-icon" id="preview-produk-icon">🍗</span>
                            <span class="img-upload-hint">Klik untuk pilih foto</span>
                        </div>
                    </div>
                    <input type="file" id="input-gambar-produk" name="gambar_produk" accept="image/*" style="display:none" onchange="previewGambar(this, 'preview-produk')">
                </div>

                <div class="modal-footer">
                    <button type="submit" name="tambah_produk_baru" class="btn-primary-sm" style="flex:1; padding:12px;">Simpan Menu Baru</button>
                    <button type="button" class="btn-primary-sm" style="background:#cbd5e1; color:#0f172a;" onclick="tutupModalProdukBaru()">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        /* ---- Modal Open/Close ---- */
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
            // Reset preview saat modal ditutup
            resetPreview('preview-bahan', '📦');
            document.getElementById('input-gambar-bahan').value = '';
            document.getElementById('modal-bahan-baru').style.display = 'none';
        }
        function bukaModalProdukBaru() {
            document.getElementById('modal-produk-baru').style.display = 'flex';
        }
        function tutupModalProdukBaru() {
            // Reset preview saat modal ditutup
            resetPreview('preview-produk', '🍗');
            document.getElementById('input-gambar-produk').value = '';
            document.getElementById('modal-produk-baru').style.display = 'none';
        }
        function isiStokCepat(id, nama, satuan) {
            document.getElementById('select-id-bahan').value = id;
            document.getElementById('satuan-label').textContent = satuan;
            bukaModalStokMasuk();
        }
        function toggleSidebar() {
            document.body.classList.toggle('sidebar-open');
        }
        function closeSidebar() {
            document.body.classList.remove('sidebar-open');
        }

        /* ---- Preview Gambar Upload ---- */
        function previewGambar(input, previewId) {
            const previewEl = document.getElementById(previewId);
            if (!input.files || !input.files[0]) return;
            const file = input.files[0];
            // Validasi di sisi klien
            if (!file.type.startsWith('image/')) {
                alert('File harus berupa gambar (JPG, PNG, WebP, GIF).');
                input.value = '';
                return;
            }
            if (file.size > 2 * 1024 * 1024) {
                alert('Ukuran gambar maksimal 2 MB.');
                input.value = '';
                return;
            }
            const reader = new FileReader();
            reader.onload = function(e) {
                previewEl.innerHTML = `<img src="${e.target.result}" alt="Preview" style="width:100%;height:100%;object-fit:cover;border-radius:10px;">`;
            };
            reader.readAsDataURL(file);
        }

        function resetPreview(previewId, emoji) {
            const el = document.getElementById(previewId);
            if (el) {
                el.innerHTML = `<span class="img-upload-icon">${emoji}</span><span class="img-upload-hint">Klik untuk pilih foto</span>`;
            }
        }

        function updateDefaultPreviewProduk(kategori) {
            // Hanya update default icon jika belum ada gambar dipilih
            const input = document.getElementById('input-gambar-produk');
            if (!input.files || !input.files[0]) {
                const icon = kategori === 'Minuman' ? '🥤' : '🍗';
                resetPreview('preview-produk', icon);
            }
        }
    </script>
</body>
</html>
