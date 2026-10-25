<?php
$host = "localhost";
$user = "root";       
$pass = "";           
$db   = "fillah_food"; // Pastikan tanpa _db

// Coba koneksi default tanpa password (bawaan XAMPP umum)
try {
    $conn = new mysqli($host, $user, $pass, $db);
} catch (mysqli_sql_exception $e) {
    // Jika gagal autentikasi password, fallback ke password lokal jika ada
    $pass = "123456";
    $conn = new mysqli($host, $user, $pass, $db);
}

// Set zona waktu default ke Waktu Indonesia Barat (WIB)
date_default_timezone_set('Asia/Jakarta');

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Sinkronisasi zona waktu koneksi database ke WIB (+07:00)
$conn->query("SET time_zone = '+07:00'");
?>