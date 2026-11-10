<?php
// perbaiki_gambar.php - Skrip Otomatis Sinkronisasi Foto Produk Fillah Food
require_once 'config.php';

echo "<div style='font-family: sans-serif; max-width: 600px; margin: 40px auto; padding: 25px; border-radius: 12px; border: 1.5px solid #e2e8f0; box-shadow: 0 4px 15px rgba(0,0,0,0.05);'>";
echo "<h2 style='color: #ea580c; margin-top:0;'>Sinkronisasi Foto Produk Fillah Food</h2>";

// 1. Pastikan kolom gambar ada di tabel produk
$db_name = $conn->query("SELECT DATABASE()")->fetch_row()[0];
$chk = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='$db_name' AND TABLE_NAME='produk' AND COLUMN_NAME='gambar'")->num_rows;
if ($chk === 0) {
    $conn->query("ALTER TABLE produk ADD COLUMN gambar VARCHAR(255) NULL");
    echo "<p style='color:green;'>✔ Kolom 'gambar' berhasil ditambahkan ke tabel produk.</p>";
}

// 2. Update path gambar makanan & minuman
$conn->query("UPDATE produk SET gambar = 'uploads/menu_ayam_geprek.png' WHERE kategori = 'Makanan'");
$conn->query("UPDATE produk SET gambar = 'uploads/menu_aneka_es.png' WHERE kategori = 'Minuman'");
$conn->query("UPDATE produk SET gambar = 'uploads/menu_sosis_kocek.png' WHERE nama_produk LIKE '%Sosis%'");
$conn->query("UPDATE produk SET gambar = 'uploads/menu_tahu_kocek.png' WHERE nama_produk LIKE '%Tahu Kocek%'");
$conn->query("UPDATE produk SET gambar = 'uploads/menu_tahu_krispi.png' WHERE nama_produk LIKE '%Tahu Krispi%'");

echo "<p style='color:green;'>✔ Seluruh data produk di database berhasil diperbarui dengan path gambar resmi!</p>";

// 3. Cek fisik folder uploads
$dir_uploads = __DIR__ . '/uploads';
if (is_dir($dir_uploads)) {
    $files = scandir($dir_uploads);
    $png_count = 0;
    foreach ($files as $f) {
        if (str_ends_with($f, '.png')) $png_count++;
    }
    echo "<p style='color:green;'>✔ Folder uploads/ ditemukan dengan <b>$png_count file foto</b>.</p>";
} else {
    echo "<p style='color:red;'>❌ Folder uploads/ belum ada di laptop Anda. Silakan jalankan 'git pull origin main' di terminal.</p>";
}

echo "<hr style='border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;'>";
echo "<a href='kasir.php' style='display:inline-block; padding: 10px 20px; background: #ea580c; color: #fff; text-decoration: none; border-radius: 8px; font-weight: bold;'>Buka Halaman Kasir</a>";
echo "</div>";
?>
