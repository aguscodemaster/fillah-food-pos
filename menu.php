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
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
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
                <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                    <button type="button" onclick="bukaModalBannerHD()" style="background: rgba(255,255,255,0.22); border: 1px solid rgba(255,255,255,0.4); color: #ffffff; padding: 5px 12px; border-radius: 20px; font-size: 11.5px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; transition: all 0.15s;" onmouseover="this.style.background='rgba(255,255,255,0.32)'" onmouseout="this.style.background='rgba(255,255,255,0.22)'">
                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line><line x1="11" y1="8" x2="11" y2="14"></line><line x1="8" y1="11" x2="14" y2="11"></line></svg>
                        <span>Lihat Banner HD</span>
                    </button>
                    <span class="status-badge"><span class="status-dot"></span> Buka (10:00 - 21:00)</span>
                </div>
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
                <?php
                    $thumb_src = !empty($item['gambar']) ? $item['gambar'] : '';
                    $hd_src = $thumb_src;
                    if (!empty($thumb_src)) {
                        $info = pathinfo($thumb_src);
                        $dir = (!empty($info['dirname']) && $info['dirname'] !== '.') ? $info['dirname'] . '/' : '';
                        $candidate_hd = $dir . $info['filename'] . '_hd.' . $info['extension'];
                        if (file_exists($candidate_hd)) {
                            $hd_src = $candidate_hd;
                        }
                    }
                ?>
                <div class="menu-card" data-cat="<?php echo htmlspecialchars($item['kategori']); ?>">
                    <div class="menu-icon" 
                         <?php if (!empty($thumb_src) && file_exists($thumb_src)): ?>
                             onclick="bukaModalFotoHD(this)"
                             data-thumb="<?php echo htmlspecialchars($thumb_src); ?>"
                             data-hd="<?php echo htmlspecialchars($hd_src); ?>"
                             data-nama="<?php echo htmlspecialchars($item['nama_produk']); ?>"
                             data-harga="Rp <?php echo number_format($item['harga'], 0, ',', '.'); ?>"
                             data-cat="<?php echo htmlspecialchars($item['kategori']); ?>"
                             title="Klik untuk melihat foto HD"
                             style="cursor: pointer;"
                         <?php endif; ?>>
                        <?php if (!empty($thumb_src) && file_exists($thumb_src)): ?>
                            <img src="<?php echo htmlspecialchars($thumb_src); ?>" 
                                 alt="<?php echo htmlspecialchars($item['nama_produk']); ?>"
                                 loading="lazy"
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

    <!-- Modal Foto HD Lightbox (Ringan & Cepat: Load Thumb Dulu, Swap HD Otomatis) -->
    <div id="modal-foto-hd" class="hd-modal-overlay" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; width:100vw; height:100vh; background:rgba(15,23,42,0.78); backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px); z-index:999999; align-items:center; justify-content:center; padding:16px;" onclick="tutupModalFotoHD(event)">
        <div class="hd-modal-card" style="background:#ffffff; width:100%; max-width:420px; border-radius:18px; overflow:hidden; box-shadow:0 25px 50px -12px rgba(0,0,0,0.5); display:flex; flex-direction:column; position:relative; margin:auto;" onclick="event.stopPropagation()">
            <button type="button" class="hd-modal-close" onclick="tutupModalFotoHD()" title="Tutup Pratinjau (Esc)" style="position:absolute; top:12px; right:12px; z-index:10; width:34px; height:34px; border-radius:50%; background:rgba(15,23,42,0.65); color:#ffffff; border:1px solid rgba(255,255,255,0.25); display:flex; align-items:center; justify-content:center; cursor:pointer;">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
            
            <div class="hd-modal-img-wrap" style="width:100%; height:280px; max-height:48vh; background:#0f172a; position:relative; overflow:hidden; display:flex; align-items:center; justify-content:center;">
                <span id="hd-modal-badge" class="hd-modal-badge" style="position:absolute; top:12px; left:12px; background:rgba(15,23,42,0.75); color:#38bdf8; font-size:11px; font-weight:700; padding:4px 10px; border-radius:20px; display:inline-flex; align-items:center; gap:6px; border:1px solid rgba(56,189,248,0.3); z-index:2;">
                    <span class="spinner-dot" style="width:6px;height:6px;border-radius:50%;background:#38bdf8;display:inline-block;"></span> Memuat HD...
                </span>
                <img id="hd-modal-img" class="hd-modal-img" src="" alt="Foto Produk HD" style="width:100%; height:100%; object-fit:cover;">
            </div>
            
            <div class="hd-modal-body" style="padding:16px 20px 20px 20px; display:flex; flex-direction:column; gap:12px; background:#ffffff;">
                <div class="hd-modal-head-row" style="display:flex; justify-content:space-between; align-items:flex-start; gap:10px;">
                    <h3 id="hd-modal-title" class="hd-modal-title" style="font-size:17px; font-weight:800; color:#0f172a; margin:0; line-height:1.3;">Nama Menu</h3>
                    <span id="hd-modal-cat" class="hd-cat-badge makanan" style="font-size:11px; font-weight:700; padding:3px 8px; border-radius:12px; white-space:nowrap; background:#fff7ed; color:#ea580c; border:1px solid #fed7aa;">Makanan</span>
                </div>
                
                <div class="hd-modal-price-row" style="display:flex; justify-content:space-between; align-items:center; padding-top:6px; border-top:1px dashed #e2e8f0;">
                    <div>
                        <div style="font-size:11px; color:#64748b; font-weight:600;">Harga Menu</div>
                        <div id="hd-modal-price" class="hd-modal-price" style="font-size:18px; font-weight:800; color:#ea580c;">Rp 0</div>
                    </div>
                    <div style="font-size:11.5px; color:#16a34a; font-weight:700; display:flex; align-items:center; gap:4px;">
                        <span style="width:7px; height:7px; background:#16a34a; border-radius:50%; display:inline-block;"></span> Tersedia
                    </div>
                </div>

                <div style="font-size:11.5px; color:#64748b; line-height:1.45; background:#f8fafc; padding:8px 12px; border-radius:8px; border:1px solid #e2e8f0;">
                    💡 <strong>Tips:</strong> Foto di atas merupakan sajian asli Fillah Food &amp; Drink. Tentukan pilihan Anda lalu sebutkan ke kasir di kasir depan.
                </div>
                
                <div class="hd-modal-actions" style="display:flex; gap:10px; margin-top:4px;">
                    <button type="button" class="btn-hd-action" onclick="tutupModalFotoHD()" style="flex:1; background:#ea580c; color:#ffffff; border:none; padding:10px 16px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; gap:6px; box-shadow:0 4px 12px -2px rgba(234,88,12,0.4);">
                        <span>Tutup Pratinjau</span>
                    </button>
                </div>
            </div>
        </div>
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

        // --- Fitur Lightbox Modal Foto HD Cepat & Ringan ---
        function bukaModalFotoHD(el) {
            if (!el) return;
            const thumbSrc = el.getAttribute('data-thumb') || '';
            const hdSrc    = el.getAttribute('data-hd') || thumbSrc;
            const nama     = el.getAttribute('data-nama') || 'Foto Produk';
            const harga    = el.getAttribute('data-harga') || '';
            const cat      = el.getAttribute('data-cat') || '';

            const modal      = document.getElementById('modal-foto-hd');
            if (!modal) return;
            const modalImg   = document.getElementById('hd-modal-img');
            const modalTitle = document.getElementById('hd-modal-title');
            const modalPrice = document.getElementById('hd-modal-price');
            const modalCat   = document.getElementById('hd-modal-cat');
            const modalBadge = document.getElementById('hd-modal-badge');

            if (modalTitle) modalTitle.textContent = nama;
            if (modalPrice) modalPrice.textContent = harga;
            if (modalCat) {
                modalCat.textContent = cat;
                if (cat === 'Makanan') {
                    modalCat.className = 'hd-cat-badge makanan';
                    modalCat.style.background = '#fff7ed';
                    modalCat.style.color = '#ea580c';
                    modalCat.style.border = '1px solid #fed7aa';
                } else if (cat === 'Minuman') {
                    modalCat.className = 'hd-cat-badge minuman';
                    modalCat.style.background = '#f0f9ff';
                    modalCat.style.color = '#0284c7';
                    modalCat.style.border = '1px solid #bae6fd';
                } else {
                    modalCat.className = 'hd-cat-badge banner';
                    modalCat.style.background = '#fef3c7';
                    modalCat.style.color = '#d97706';
                    modalCat.style.border = '1px solid #fde68a';
                }
            }

            // Tampilkan thumbnail instan terlebih dahulu (ringan, tanpa menunggu)
            if (modalImg) {
                modalImg.src = thumbSrc;
                modalImg.style.filter = 'blur(6px)';
            }
            if (modalBadge) {
                modalBadge.innerHTML = '<span class="spinner-dot" style="width:6px;height:6px;border-radius:50%;background:#38bdf8;display:inline-block;"></span> Memuat HD...';
            }

            modal.style.display = 'flex';
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';

            // Muat versi HD di latar belakang secara asinkron
            const hdLoader = new Image();
            hdLoader.src = hdSrc;
            hdLoader.onload = function() {
                if (modal.style.display === 'flex' && modalImg) {
                    modalImg.src = hdSrc;
                    modalImg.style.filter = 'blur(0)';
                    if (modalBadge) modalBadge.innerHTML = '✨ Resolusi Tinggi (HD)';
                }
            };
            hdLoader.onerror = function() {
                if (modalImg) modalImg.style.filter = 'blur(0)';
                if (modalBadge) modalBadge.innerHTML = 'Foto Produk Asli';
            };
        }

        function bukaModalBannerHD() {
            bukaModalFotoHD({
                getAttribute: function(attr) {
                    if (attr === 'data-thumb') return 'uploads/menu_banner_hd.png';
                    if (attr === 'data-hd') return 'uploads/menu_banner_hd.png';
                    if (attr === 'data-nama') return 'Banner Resmi Fillah Food & Drink';
                    if (attr === 'data-harga') return 'Daftar Harga & Menu Lengkap';
                    if (attr === 'data-cat') return 'Banner Resmi';
                    return '';
                }
            });
        }

        function tutupModalFotoHD(event) {
            if (event && event.target && event.target !== event.currentTarget) return;
            const modal = document.getElementById('modal-foto-hd');
            if (modal) {
                modal.style.display = 'none';
                modal.classList.remove('active');
                document.body.style.overflow = '';
            }
        }

        // Shortcut Esc untuk menutup modal
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') tutupModalFotoHD();
        });
    </script>
</body>
</html>
