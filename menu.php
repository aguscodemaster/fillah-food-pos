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
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background-color: #f1f5f9;
            color: var(--text-dark);
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            justify-content: center;
            padding: clamp(12px, 2.5vw, 28px);
        }

        .mobile-container {
            width: 100%;
            max-width: 820px; /* Expands elegantly on tablet/desktop */
            background: #ffffff;
            border-radius: var(--radius-xl);
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.06);
            border: 1px solid var(--border);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            min-height: 90vh;
            position: relative;
        }

        /* Banner Header */
        .banner-header {
            background: linear-gradient(135deg, #ea580c, #f59e0b);
            color: white;
            padding: clamp(16px, 3vw, 24px) clamp(16px, 3vw, 24px) clamp(14px, 2.5vw, 20px) clamp(16px, 3vw, 24px);
            position: relative;
        }

        .brand-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            gap: 10px;
            flex-wrap: wrap;
        }

        .brand-title {
            font-size: clamp(18px, 3.5vw, 22px);
            font-weight: 800;
            letter-spacing: -0.3px;
            color: #ffffff;
        }

        .status-badge {
            background: rgba(255,255,255,0.25);
            backdrop-filter: blur(4px);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .status-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #22c55e;
            box-shadow: 0 0 0 2px rgba(34, 197, 94, 0.4);
            display: inline-block;
        }

        .location-info {
            background: rgba(0,0,0,0.15);
            border-radius: var(--radius-sm);
            padding: 8px 12px;
            font-size: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
        }

        /* Category Filter Tabs */
        .cat-scroll {
            display: flex;
            gap: 8px;
            padding: 12px clamp(14px, 2.5vw, 24px);
            overflow-x: auto;
            border-bottom: 1px solid var(--border);
            background: #ffffff;
            position: sticky;
            top: 0;
            z-index: 10;
            -webkit-overflow-scrolling: touch;
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
            padding: clamp(14px, 2.5vw, 24px);
            display: grid;
            grid-template-columns: 1fr;
            gap: clamp(10px, 1.5vw, 14px);
            flex: 1;
            align-content: start;
        }

        /* 2 Kolom pada Tablet & Desktop */
        @media (min-width: 600px) {
            .menu-list {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .menu-card {
            background: #ffffff;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-md);
            padding: 12px;
            display: flex;
            gap: 12px;
            transition: transform 0.15s, border-color 0.15s, box-shadow 0.15s;
            box-shadow: var(--shadow-sm);
        }

        .menu-card:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 6px 14px -3px rgba(234, 88, 12, 0.15);
        }

        .menu-icon {
            width: clamp(56px, 10vw, 68px);
            height: clamp(56px, 10vw, 68px);
            border-radius: var(--radius-sm);
            background: #fff7ed;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: clamp(28px, 5vw, 34px);
            flex-shrink: 0;
            overflow: hidden; /* Untuk gambar upload agar crop rapi */
        }

        .menu-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-width: 0;
        }

        .menu-name {
            font-size: clamp(13px, 2vw, 14.5px);
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
            margin-top: 6px;
        }

        .menu-price {
            font-size: clamp(13.5px, 2vw, 15px);
            font-weight: 800;
            color: var(--primary);
        }

        /* Bottom Floating Bar */
        .bottom-bar {
            padding: clamp(12px, 2vw, 16px) clamp(16px, 2.5vw, 24px);
            background: #ffffff;
            border-top: 1.5px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        .btn-call {
            background: var(--primary);
            color: #ffffff;
            border: none;
            padding: 10px 18px;
            border-radius: var(--radius-sm);
            font-size: 12.5px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 10px -2px rgba(234, 88, 12, 0.4);
            white-space: nowrap;
            transition: all 0.15s;
        }

        .btn-call:hover {
            background: var(--primary-hover);
        }

        /* Mobile Edge-to-Edge (< 600px) */
        @media (max-width: 600px) {
            body {
                padding: 0;
                background: #ffffff;
            }

            .mobile-container {
                border-radius: 0;
                border: none;
                box-shadow: none;
                min-height: 100vh;
                min-height: 100dvh;
            }
        }
    </style>
</head>
<body>

    <div class="mobile-container">
        <!-- Banner Header -->
        <header class="banner-header">
            <div class="brand-row">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 36px; height: 36px; background: rgba(255,255,255,0.2); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"></path>
                            <path d="M7 2v20"></path>
                            <path d="M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"></path>
                        </svg>
                    </div>
                    <div>
                        <h1 class="brand-title">Fillah Food</h1>
                        <p style="font-size: 11px; opacity: 0.9;">Spesialis Ayam Geprek & Kuliner</p>
                    </div>
                </div>
                <span class="status-badge"><span class="status-dot"></span> Buka (10:00 - 21:00)</span>
            </div>

            <div class="location-info">
                <span style="display:inline-flex; align-items:center; gap:5px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                    <span>Gumukmas, Jember</span>
                </span>
                <span style="font-weight: 700; color: #fff;">Meja Pelanggan</span>
            </div>
        </header>

        <!-- Category Scroll Tabs -->
        <div class="cat-scroll">
            <button class="cat-btn active" onclick="filterCat('Semua')">Semua Menu</button>
            <button class="cat-btn" onclick="filterCat('Makanan')">Makanan</button>
            <button class="cat-btn" onclick="filterCat('Minuman')">Minuman</button>
        </div>

        <!-- Menu List -->
        <main class="menu-list">
            <?php foreach ($menu_list as $item): ?>
                <div class="menu-card" data-cat="<?php echo htmlspecialchars($item['kategori']); ?>">
                    <div class="menu-icon">
                        <?php if (!empty($item['gambar']) && file_exists($item['gambar'])): ?>
                            <img src="<?php echo htmlspecialchars($item['gambar']); ?>" 
                                 alt="<?php echo htmlspecialchars($item['nama_produk']); ?>"
                                 style="width:100%;height:100%;object-fit:cover;">
                        <?php else: ?>
                            <?php if ($item['kategori'] === 'Makanan'): ?>
                                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#ea580c" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"></path><path d="M7 2v20"></path><path d="M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"></path></svg>
                            <?php else: ?>
                                <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#0284c7" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 8h1a4 4 0 1 1 0 8h-1"></path><path d="M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4Z"></path><line x1="6" y1="2" x2="6" y2="4"></line><line x1="10" y1="2" x2="10" y2="4"></line><line x1="14" y1="2" x2="14" y2="4"></line></svg>
                            <?php endif; ?>
                        <?php endif; ?>
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
            <button class="btn-call" onclick="alert('Silakan sebutkan nomor Meja dan pesanan Anda ke Kasir di depan ya kak!')" style="display:inline-flex; align-items:center; gap:6px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"></path><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"></path></svg>
                <span>Panggil Kasir</span>
            </button>
        </footer>
    </div>

    <script>
        function filterCat(cat, btn) {
            const btns = document.querySelectorAll('.cat-btn');
            btns.forEach(b => b.classList.remove('active'));
            if (btn) {
                btn.classList.add('active');
            } else if (window.event && window.event.currentTarget) {
                window.event.currentTarget.classList.add('active');
            }

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
