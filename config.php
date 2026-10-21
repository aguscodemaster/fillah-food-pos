<?php
$host = "localhost";
$user = "root";       
$pass = "";           
$db   = "fillah_food"; // Pastikan tanpa _db

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}
?>