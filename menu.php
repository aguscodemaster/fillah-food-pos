<?php
require 'config.php';

// Ambil seluruh daftar produk dari database
$query = "SELECT * FROM produk ORDER BY kategori ASC, id ASC";
$result = $conn->query($query);
$menu_list = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $menu_list[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Menu Digital - Fillah Food</title>
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
            --bg-canvas: #f8fafc;
            --card-bg: #ffffff;
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
            min-height: 100vh;
            display: flex;
            justify-content: center;
            padding: 12px;
        }

        .mobile-container {
            width: 100%;
            max-width: 480px;
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05);
            border: 1px solid var(--border);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            min-height: 90vh;
        }

        /* Banner Header */
        .banner-header {
            background: linear-gradient(135deg, #ea580c, #f59e0b);
            color: white;
            padding: 24px 20px 20px 20px;
            position: relative;
        }

        .brand-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .brand-title {
            font-size: 20px;
            font-weight: 800;
            letter-spacing: -0.3px;
        }

        .status-badge {
            background: rgba(255,255,255,0.25);
            backdrop-filter: blur(4px);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
        }

        .location-info {
            background: rgba(0,0,0,0.15);
            border-radius: 12px;
            padding: 10px 14px;
            font-size: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Category Filter Tabs */
        .cat-scroll {
            display: flex;
            gap: 8px;
            padding: 16px 20px;
            overflow-x: auto;
            border-bottom: 1px solid var(--border);
            background: #ffffff;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .cat-btn {
            background: #f1f5f9;
            color: var(--text-muted);
            border: none;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12.5px;
            font-weight: 700;
            white-space: nowrap;
            cursor: pointer;
            transition: all 0.2s;
        }

        .cat-btn.active, .cat-btn:hover {
            background: var(--primary);
            color: #ffffff;
            box-shadow: 0 4px 10px -2px rgba(234, 88, 12, 0.4);
        }

        /* Menu List Cards */
        .menu-list {
            padding: 16px 20px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            flex: 1;
        }

        .menu-card {
            background: #ffffff;
            border: 1.5px solid var(--border);
            border-radius: 16px;
            padding: 14px;
            display: flex;
            gap: 14px;
            transition: transform 0.15s, border-color 0.15s;
        }

        .menu-card:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
        }

        .menu-icon {
            width: 70px;
            height: 70px;
            border-radius: 12px;
            background: #fff7ed;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 34px;
            flex-shrink: 0;
        }

        .menu-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .menu-name {
            font-size: 14.5px;
            font-weight: 800;
            color: var(--text-dark);
            line-height: 1.3;
        }

        .menu-cat {
            font-size: 11px;
            color: var(--primary);
            font-weight: 700;
            margin-top: 2px;
        }

        .menu-price-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 8px;
        }

        .menu-price {
            font-size: 15px;
            font-weight: 800;
            color: var(--primary);
        }

        /* Bottom Floating Bar */
        .bottom-bar {
            padding: 14px 20px;
            background: #ffffff;
            border-top: 1.5px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn-call {
            background: var(--primary);
            color: #ffffff;
            border: none;
            padding: 10px 18px;
            border-radius: 12px;
            font-size: 12.5px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 10px -2px rgba(234, 88, 12, 0.4);
        }
    </style>
</head>
<body>

    <div class="mobile-container">
        <!-- Banner Header -->
        <header class="banner-header">
            <div class="brand-row">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 26px;">🍗</span>
                    <div>
                        <h1 class="brand-title">Fillah Food</h1>
                        <p style="font-size: 11px; opacity: 0.9;">Spesialis Ayam Geprek & Kuliner</p>
                    </div>
                </div>
                <span class="status-badge">🟢 Buka (10:00 - 21:00)</span>
            </div>

            <div class="location-info">
                <span>📍 Gumukmas, Jember</span>
                <span style="font-weight: 700; color: #fff;">Meja Pelanggan</span>
            </div>
        </header>

        <!-- Category Scroll Tabs -->
        <div class="cat-scroll">
            <button class="cat-btn active" onclick="filterCat('Semua')">Semua Menu</button>
            <button class="cat-btn" onclick="filterCat('Makanan')">🍗 Makanan</button>
            <button class="cat-btn" onclick="filterCat('Minuman')">🥤 Minuman</button>
        </div>

        <!-- Menu List -->
        <main class="menu-list">
            <?php foreach ($menu_list as $item): ?>
                <div class="menu-card" data-cat="<?php echo htmlspecialchars($item['kategori']); ?>">
                    <div class="menu-icon">
                        <?php echo ($item['kategori'] === 'Makanan') ? '🍗' : '🥤'; ?>
                    </div>
                    <div class="menu-info">
                        <div>
                            <div class="menu-name"><?php echo htmlspecialchars($item['nama_produk']); ?></div>
                            <div class="menu-cat"><?php echo htmlspecialchars($item['kategori']); ?></div>
                        </div>
                        <div class="menu-price-row">
                            <span class="menu-price">Rp <?php echo number_format($item['harga'], 0, ',', '.'); ?></span>
                            <span style="font-size: 11px; color: var(--text-muted); font-weight: 600;">Tersedia</span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </main>

        <!-- Bottom Bar -->
        <footer class="bottom-bar">
            <div>
                <div style="font-size: 11px; color: var(--text-muted);">Sudah memilih menu?</div>
                <div style="font-size: 12.5px; font-weight: 700;">Sebutkan ke Kasir di Depan</div>
            </div>
            <button class="btn-call" onclick="alert('Silakan sebutkan nomor Meja dan pesanan Anda ke Kasir di depan ya kak!')">
                📢 Panggil Kasir
            </button>
        </footer>
    </div>

    <script>
        function filterCat(cat) {
            const btns = document.querySelectorAll('.cat-btn');
            btns.forEach(b => b.classList.remove('active'));
            event.target.classList.add('active');

            const cards = document.querySelectorAll('.menu-card');
            cards.forEach(c => {
                if (cat === 'Semua' || c.getAttribute('data-cat') === cat) {
                    c.style.display = 'flex';
                } else {
                    c.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>
