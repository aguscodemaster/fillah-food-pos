<?php
include 'config.php';

echo "<h3>Status Koneksi dan Database:</h3>";
if ($conn) {
    echo "Koneksi ke database <b>" . $db . "</b> BERHASIL!<br><br>";
    
    // Cek apakah tabel transaksi ada di database ini
    $cek = $conn->query("SHOW TABLES LIKE 'transaksi'");
    if ($cek->num_rows > 0) {
        echo "<span style='color: green; font-weight: bold;'>✔ Tabel 'transaksi' DITEMUKAN di database ini! Sistem sudah siap.</span>";
    } else {
        echo "<span style='color: red; font-weight: bold;'>❌ Tabel 'transaksi' TIDAK ADA di database '" . $db . "'!</span><br>";
        echo "Artinya, Anda membuat tabelnya di database yang berbeda dengan yang ditulis di file config.php.";
    }
}
?>