<?php
require 'auth_check.php';
protect_page(['Kasir', 'Admin', 'Owner']);
require 'config.php';

// Ambil seluruh daftar produk aktif dari database
$query_produk = "SELECT * FROM produk ORDER BY kategori ASC, nama_produk ASC";
$result_produk = $conn->query($query_produk);
$daftar_produk = [];
if ($result_produk) {
    while ($row = $result_produk->fetch_assoc()) {
        $daftar_produk[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Kasir POS - Fillah Food</title>
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
            --success: #16a34a;
            --success-hover: #15803d;
            --danger: #dc2626;
            --bg-canvas: #f1f5f9;
            --bg-card: #ffffff;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
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
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* Top Navbar */
        .navbar {
            background: #ffffff;
            border-bottom: 1.5px solid var(--border);
            padding: 10px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.03);
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-logo {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            box-shadow: 0 4px 8px -2px rgba(234, 88, 12, 0.4);
        }

        .brand-text h1 {
            font-size: 18px;
            font-weight: 800;
            color: var(--text-dark);
            letter-spacing: -0.3px;
        }

        .brand-text p {
            font-size: 11px;
            color: var(--text-muted);
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .user-pill {
            background: var(--primary-light);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12.5px;
            font-weight: 600;
            color: var(--primary);
            border: 1px solid #fed7aa;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-link {
            text-decoration: none;
            font-size: 12.5px;
            font-weight: 600;
            color: var(--text-muted);
            padding: 6px 12px;
            border-radius: 8px;
            transition: all 0.15s;
        }

        .btn-link:hover {
            background: #e2e8f0;
            color: var(--text-dark);
        }

        .btn-logout {
            background: #fee2e2;
            color: var(--danger);
            border: 1px solid #fecaca;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 12.5px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.15s;
        }

        .btn-logout:hover {
            background: var(--danger);
            color: #ffffff;
        }

        /* Main Workspace Container */
        .workspace {
            display: flex;
            flex: 1;
            height: calc(100vh - 65px);
            overflow: hidden;
        }

        /* Left Panel: Menu Catalog */
        .catalog-panel {
            flex: 1.4;
            padding: 18px 24px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .catalog-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
        }

        .category-pills {
            display: flex;
            gap: 8px;
        }

        .cat-btn {
            background: #ffffff;
            border: 1.5px solid var(--border);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12.5px;
            font-weight: 700;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.2s;
        }

        .cat-btn.active, .cat-btn:hover {
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
            box-shadow: 0 4px 10px -2px rgba(234, 88, 12, 0.4);
        }

        .search-box {
            position: relative;
            min-width: 240px;
        }

        .search-input {
            width: 100%;
            padding: 8px 14px 8px 36px;
            border: 1.5px solid var(--border);
            border-radius: 20px;
            font-size: 13px;
            outline: none;
            background: #ffffff;
            transition: all 0.2s;
        }

        .search-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(234, 88, 12, 0.15);
        }

        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 14px;
            color: var(--text-muted);
        }

        /* Products Grid */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
            gap: 16px;
            padding-bottom: 20px;
        }

        .product-card {
            background: var(--bg-card);
            border: 1.5px solid var(--border);
            border-radius: 16px;
            padding: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            box-shadow: 0 2px 5px rgba(0,0,0,0.02);
        }

        .product-card:hover {
            transform: translateY(-3px);
            border-color: var(--primary);
            box-shadow: 0 8px 20px -4px rgba(234, 88, 12, 0.2);
        }

        .card-header-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--primary-light);
            color: var(--primary);
            font-size: 10px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 10px;
        }

        .product-thumb {
            width: 100%;
            height: 110px;
            border-radius: 12px;
            object-cover: cover;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 42px;
            margin-bottom: 12px;
        }

        .product-name {
            font-size: 14px;
            font-weight: 700;
            color: var(--text-dark);
            line-height: 1.3;
            margin-bottom: 6px;
            height: 36px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-price {
            font-size: 15px;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 10px;
        }

        .btn-add-item {
            width: 100%;
            background: #f8fafc;
            color: var(--text-dark);
            border: 1.5px solid var(--border);
            padding: 8px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.15s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
        }

        .product-card:hover .btn-add-item {
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
        }

        /* Right Panel: Order Ticket (Keranjang Kasir) */
        .cart-panel {
            flex: 1;
            background: #ffffff;
            border-left: 1.5px solid var(--border);
            display: flex;
            flex-direction: column;
            box-shadow: -4px 0 15px rgba(0,0,0,0.03);
            max-width: 440px;
        }

        .cart-header {
            padding: 16px 20px;
            border-bottom: 1.5px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .cart-header h2 {
            font-size: 16px;
            font-weight: 800;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-clear-cart {
            background: none;
            border: none;
            color: var(--danger);
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-clear-cart:hover {
            text-decoration: underline;
        }

        /* Order Meta (Meja & Order Type) */
        .order-meta {
            padding: 12px 20px;
            background: #f8fafc;
            border-bottom: 1px solid var(--border);
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .meta-select {
            width: 100%;
            padding: 8px 10px;
            border-radius: 8px;
            border: 1px solid var(--border);
            font-size: 12px;
            font-weight: 600;
            color: var(--text-dark);
            outline: none;
            background: #ffffff;
        }

        /* Cart Items List */
        .cart-items-wrapper {
            flex: 1;
            overflow-y: auto;
            padding: 12px 20px;
        }

        .cart-item-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px dashed var(--border);
            gap: 8px;
        }

        .item-info {
            flex: 1;
        }

        .item-title {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-dark);
        }

        .item-subtotal {
            font-size: 12px;
            font-weight: 600;
            color: var(--primary);
            margin-top: 2px;
        }

        .stepper {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .step-btn {
            width: 26px;
            height: 26px;
            border-radius: 6px;
            border: 1px solid var(--border);
            background: #f8fafc;
            font-size: 14px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.15s;
        }

        .step-btn:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .step-qty {
            font-size: 13px;
            font-weight: 700;
            width: 24px;
            text-align: center;
        }

        /* Cart Footer & Checkout */
        .cart-footer {
            padding: 16px 20px;
            border-top: 1.5px solid var(--border);
            background: #ffffff;
            box-shadow: 0 -4px 10px rgba(0,0,0,0.02);
        }

        .order-notes-input {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 12px;
            margin-bottom: 12px;
            outline: none;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            margin-bottom: 6px;
            color: var(--text-muted);
        }

        .summary-total {
            display: flex;
            justify-content: space-between;
            font-size: 18px;
            font-weight: 800;
            color: var(--text-dark);
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1.5px dashed var(--border);
        }

        .quick-cash-row {
            display: flex;
            gap: 6px;
            margin: 12px 0 8px 0;
        }

        .quick-cash-btn {
            flex: 1;
            padding: 6px 2px;
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            color: var(--text-dark);
            cursor: pointer;
            transition: background 0.15s;
        }

        .quick-cash-btn:hover {
            background: #ffedd5;
            border-color: var(--primary);
        }

        .cash-input-group {
            margin-bottom: 12px;
        }

        .cash-input {
            width: 100%;
            padding: 10px 12px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
            outline: none;
        }

        .cash-input:focus {
            border-color: var(--primary);
        }

        .change-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            font-weight: 700;
            color: var(--success);
            margin-bottom: 12px;
        }

        .btn-checkout {
            width: 100%;
            background: linear-gradient(135deg, var(--success), var(--success-hover));
            color: white;
            border: none;
            padding: 14px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 6px 15px -3px rgba(22, 163, 74, 0.4);
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-checkout:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 20px -3px rgba(22, 163, 74, 0.5);
        }

        /* Modal Struk Nota Belanja */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 999;
        }

        .receipt-card {
            background: #ffffff;
            width: 360px;
            max-height: 90vh;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);
            overflow-y: auto;
            font-family: 'Courier New', Courier, monospace;
        }

        .receipt-header {
            text-align: center;
            border-bottom: 1px dashed #000;
            padding-bottom: 12px;
            margin-bottom: 12px;
        }

        .receipt-header h3 {
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 1px;
        }

        .receipt-header p {
            font-size: 11px;
            line-height: 1.3;
        }

        .receipt-meta {
            font-size: 11.5px;
            border-bottom: 1px dashed #000;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }

        .receipt-items {
            border-bottom: 1px dashed #000;
            padding-bottom: 8px;
            margin-bottom: 8px;
            font-size: 12px;
        }

        .receipt-summary {
            font-size: 12px;
            border-bottom: 1px dashed #000;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }

        .receipt-summary .row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
        }

        .receipt-footer {
            text-align: center;
            font-size: 11px;
            margin-bottom: 16px;
        }

        .modal-actions {
            display: flex;
            gap: 8px;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        .btn-print {
            flex: 1;
            background: var(--primary);
            color: white;
            border: none;
            padding: 10px;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
        }

        .btn-close-modal {
            flex: 1;
            background: #e2e8f0;
            color: var(--text-dark);
            border: none;
            padding: 10px;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
        }

        @media print {
            body * {
                visibility: hidden;
            }
            .receipt-card, .receipt-card * {
                visibility: visible;
            }
            .receipt-card {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                box-shadow: none;
                padding: 0;
            }
            .modal-actions {
                display: none !important;
            }
        }
    </style>
</head>
<body>

    <!-- Header Navbar -->
    <header class="navbar">
        <div class="navbar-brand">
            <div class="brand-logo">🍗</div>
            <div class="brand-text">
                <h1>Fillah Food POS</h1>
                <p>Mode Operasional Kasir</p>
            </div>
        </div>

        <div class="nav-actions">
            <span class="user-pill">
                <span>👤</span> <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong> (<?php echo htmlspecialchars($_SESSION['role']); ?>)
            </span>
            <a href="menu.php" target="_blank" class="btn-link">📱 E-Menu Pelanggan</a>
            <?php if (in_array($_SESSION['role'], ['Owner', 'Admin'])): ?>
                <a href="admin.php" class="btn-link">⚙️ Dashboard Manajemen</a>
            <?php endif; ?>
            <a href="logout.php" class="btn-logout">Keluar (Logout)</a>
        </div>
    </header>

    <!-- Workspace -->
    <div class="workspace">
        
        <!-- Left: Product Catalog -->
        <main class="catalog-panel">
            <div class="catalog-toolbar">
                <div class="category-pills">
                    <button class="cat-btn active" onclick="filterKategori('Semua')">Semua Menu</button>
                    <button class="cat-btn" onclick="filterKategori('Makanan')">🍗 Makanan</button>
                    <button class="cat-btn" onclick="filterKategori('Minuman')">🥤 Minuman</button>
                </div>

                <div class="search-box">
                    <span class="search-icon">🔍</span>
                    <input type="text" id="cari-menu" class="search-input" placeholder="Cari nama menu..." onkeyup="cariMenuLive()">
                </div>
            </div>

            <!-- Product Cards Grid -->
            <div class="products-grid" id="daftar-menu-grid">
                <?php if (empty($daftar_produk)): ?>
                    <p style="color: #888; font-style: italic;">Belum ada menu di database.</p>
                <?php else: ?>
                    <?php foreach ($daftar_produk as $p): ?>
                        <div class="product-card" 
                             data-kategori="<?php echo htmlspecialchars($p['kategori']); ?>" 
                             data-nama="<?php echo htmlspecialchars(strtolower($p['nama_produk'])); ?>"
                             onclick="tambahItem(<?php echo $p['id']; ?>, '<?php echo addslashes($p['nama_produk']); ?>', <?php echo $p['harga']; ?>)">
                            
                            <span class="card-header-badge"><?php echo htmlspecialchars($p['kategori']); ?></span>
                            
                            <div class="product-thumb">
                                <?php echo ($p['kategori'] === 'Makanan') ? '🍗' : '🥤'; ?>
                            </div>
                            
                            <h3 class="product-name"><?php echo htmlspecialchars($p['nama_produk']); ?></h3>
                            <div class="product-price">Rp <?php echo number_format($p['harga'], 0, ',', '.'); ?></div>
                            
                            <button class="btn-add-item" type="button" style="pointer-events: none;">
                                <span>+</span> Tambah ke Pesanan
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>

        <!-- Right: Active Order Ticket (Keranjang) -->
        <aside class="cart-panel">
            <div class="cart-header">
                <h2><span>🛒</span> Pesanan Saat Ini</h2>
                <button class="btn-clear-cart" onclick="kosongkanKeranjang()">Kosongkan</button>
            </div>

            <div class="order-meta">
                <div>
                    <label style="font-size: 11px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 2px;">Tipe Pesanan</label>
                    <select id="tipe-pesanan" class="meta-select">
                        <option value="Dine In (Makan di Tempat)">Makan di Tempat</option>
                        <option value="Take Away (Bungkus)">Bungkus Pulang</option>
                    </select>
                </div>
                <div>
                    <label style="font-size: 11px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 2px;">Nomor Meja</label>
                    <select id="no-meja" class="meta-select">
                        <option value="Meja 01">Meja 01</option>
                        <option value="Meja 02">Meja 02</option>
                        <option value="Meja 03">Meja 03</option>
                        <option value="Meja 04">Meja 04</option>
                        <option value="Meja 05">Meja 05</option>
                        <option value="Bungkus / Kasir">Bungkus / Kasir</option>
                    </select>
                </div>
            </div>

            <!-- List Item Keranjang -->
            <div class="cart-items-wrapper" id="cart-items-container">
                <div id="cart-empty-state" style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
                    <div style="font-size: 36px; margin-bottom: 8px;">🛒</div>
                    <p style="font-size: 13px; font-weight: 600;">Keranjang belanja masih kosong</p>
                    <p style="font-size: 11px; margin-top: 4px;">Klik salah satu menu di sebelah kiri untuk menambahkan pesanan.</p>
                </div>
            </div>

            <!-- Summary & Checkout -->
            <div class="cart-footer">
                <input type="text" id="catatan-order" class="order-notes-input" placeholder="Catatan pesanan (misal: Pedas level 3, es sedikit)...">

                <div class="summary-row">
                    <span>Subtotal:</span>
                    <span id="label-subtotal">Rp 0</span>
                </div>
                <div class="summary-row">
                    <span>Pajak Resto (0%):</span>
                    <span>Rp 0</span>
                </div>
                <div class="summary-total">
                    <span>Total Tagihan:</span>
                    <span id="label-total" style="color: var(--primary);">Rp 0</span>
                </div>

                <!-- Tombol Cepat Pecahan Uang Tunai -->
                <div class="quick-cash-row">
                    <button class="quick-cash-btn" onclick="setUangPas()">Uang Pas</button>
                    <button class="quick-cash-btn" onclick="tambahNominal(10000)">+10k</button>
                    <button class="quick-cash-btn" onclick="tambahNominal(20000)">+20k</button>
                    <button class="quick-cash-btn" onclick="tambahNominal(50000)">+50k</button>
                    <button class="quick-cash-btn" onclick="tambahNominal(100000)">+100k</button>
                </div>

                <div class="cash-input-group">
                    <input type="number" id="input-uang-bayar" class="cash-input" placeholder="Nominal Uang Bayar (Rp)" oninput="hitungKembalianRealtime()">
                </div>

                <div class="change-row">
                    <span>Uang Kembalian:</span>
                    <span id="label-kembalian" style="font-size: 16px;">Rp 0</span>
                </div>

                <button class="btn-checkout" onclick="prosesBayarDanCetak()">
                    <span>💳</span> Proses Bayar & Cetak Struk
                </button>
            </div>
        </aside>

    </div>

    <!-- Modal Pop-up Cetak Struk Nota Kasir -->
    <div class="modal-overlay" id="modal-struk">
        <div class="receipt-card">
            <div class="receipt-header">
                <h3>🍗 FILLAH FOOD</h3>
                <p>Spesialis Ayam Geprek & Kuliner<br>Jl. Gumukmas, Jember - Jawa Timur<br>WhatsApp: 0858-0982-7735</p>
            </div>

            <div class="receipt-meta">
                <div style="display: flex; justify-content: space-between;">
                    <span id="receipt-no-nota">TRX-000000</span>
                    <span id="receipt-tanggal">25/10/2026</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-top: 2px;">
                    <span>Kasir: <strong id="receipt-kasir">kasir_1</strong></span>
                    <span id="receipt-meja">Meja 01</span>
                </div>
                <div id="receipt-catatan-wrapper" style="margin-top: 2px; font-style: italic; color: #555; display: none;">
                    Ket: <span id="receipt-catatan"></span>
                </div>
            </div>

            <div class="receipt-items" id="receipt-items-list">
                <!-- Rincian menu terisi oleh JS -->
            </div>

            <div class="receipt-summary">
                <div class="row">
                    <span>Total Belanja:</span>
                    <span id="receipt-total" style="font-weight: bold;">Rp 0</span>
                </div>
                <div class="row">
                    <span>Tunai / Bayar:</span>
                    <span id="receipt-bayar">Rp 0</span>
                </div>
                <div class="row" style="font-weight: bold;">
                    <span>Kembalian:</span>
                    <span id="receipt-kembalian">Rp 0</span>
                </div>
            </div>

            <div class="receipt-footer">
                <p>Terima kasih atas kunjungan Anda!</p>
                <p style="margin-top: 2px; font-size: 10px;">Makanan Halal, Nikmat, & Higienis</p>
            </div>

            <div class="modal-actions">
                <button class="btn-print" onclick="window.print()">🖨️ Cetak Struk</button>
                <button class="btn-close-modal" onclick="tutupModalStruk()">Tutup & Pesanan Baru</button>
            </div>
        </div>
    </div>

    <script src="script.js?v=<?php echo time(); ?>"></script>
</body>
</html>