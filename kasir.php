<?php
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] != 'Kasir') {
    header("Location: index.php"); 
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Sistem Kasir - Fillah Food</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f0f2f5; color: #333; }
        .container { max-width: 1000px; margin: 30px auto; background: #ffffff; padding: 25px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #e74c3c; padding-bottom: 15px; margin-bottom: 20px; }
        header h1 { color: #2c3e50; font-size: 24px; }
        .logout-btn { background-color: #c0392b; color: white; padding: 8px 15px; text-decoration: none; border-radius: 5px; font-weight: bold; }
        main { display: flex; gap: 25px; }
        .menu-section, .cart-section { flex: 1; background: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #e1e8ed; }
        h2 { color: #34495e; margin-bottom: 15px; font-size: 20px; border-bottom: 1px solid #ccc; padding-bottom: 10px; }
        .menu-item { display: flex; justify-content: space-between; align-items: center; background: #fff; padding: 12px 15px; margin-bottom: 12px; border-radius: 6px; border-left: 4px solid #3498db; }
        .menu-item button { background-color: #3498db; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; font-weight: bold; }
        #daftar-pesanan li { display: flex; justify-content: space-between; background: #fff; margin-bottom: 8px; padding: 10px; border-radius: 4px; border: 1px solid #eee; }
        .summary input { width: 100%; padding: 12px; margin-top: 8px; border: 1px solid #ccc; border-radius: 5px; }
        .summary button { background-color: #27ae60; color: white; border: none; padding: 15px; width: 100%; border-radius: 5px; font-size: 16px; font-weight: bold; cursor: pointer; margin-top: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>POS Fillah Food - Mode Kasir</h1>
            <p>Halo, <strong><?php echo $_SESSION['username']; ?></strong>! | <a href="logout.php" class="logout-btn">Logout</a></p>
        </header>
        <main>
            <section class="menu-section">
                <h2>Daftar Menu</h2>
                <div class="menu-item">
                    <span>Ayam Geprek Paha (Rp 10.000)</span>
                    <button onclick="tambahPesanan(1, 'Ayam Geprek Paha', 10000)">Tambah</button>
                </div>
                <div class="menu-item">
                    <span>Es Teh Jumbo (Rp 3.000)</span>
                    <button onclick="tambahPesanan(2, 'Es Teh Jumbo', 3000)">Tambah</button>
                </div>
            </section>
            
            <section class="cart-section">
                <h2>Rincian Pesanan</h2>
                <ul id="daftar-pesanan" style="list-style-type: none; padding: 0;"></ul>
                <div class="summary">
                    <h3 style="color:#e74c3c; margin-top:15px;">Total Belanja: Rp <span id="total-belanja">0</span></h3>
                    <label style="margin-top: 15px; display: block; font-weight:bold;">Uang Pembayaran (Rp):</label>
                    <input type="number" id="uang-bayar" placeholder="Masukkan nominal">
                    <button onclick="prosesBayar()">Proses Bayar & Cetak Struk</button>
                    <h3 style="margin-top:15px; color:#27ae60;">Kembalian: Rp <span id="kembalian">0</span></h3>
                </div>
            </section>
        </main>
    </div>
    <script src="script.js"></script>
</body>
</html>