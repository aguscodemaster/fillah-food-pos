<?php
// auth_check.php - Middleware Role-Based Access Control (RBAC)
// Capstone Project STSI4440 Universitas Terbuka - Kelompok 3C

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Memvalidasi hak akses pengguna berdasarkan peran (Role-Based Access Control).
 * @param array $allowed_roles Daftar peran yang diizinkan, misal: ['Owner', 'Admin']
 */
function protect_page(array $allowed_roles = []) {
    // 1. Cek apakah sudah login
    if (!isset($_SESSION['username']) || !isset($_SESSION['role'])) {
        header("Location: index.php?pesan=belum_login");
        exit();
    }

    // 2. Cek apakah peran pengguna sesuai dengan peran yang diizinkan
    if (!empty($allowed_roles) && !in_array($_SESSION['role'], $allowed_roles)) {
        header("Location: akses_ditolak.php");
        exit();
    }
}

/**
 * Memeriksa apakah pengguna saat ini adalah Owner (Pemilik Usaha)
 */
function is_owner() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'Owner';
}

/**
 * Memeriksa apakah pengguna saat ini adalah Admin atau Owner
 */
function is_admin_or_owner() {
    return isset($_SESSION['role']) && in_array($_SESSION['role'], ['Owner', 'Admin']);
}
?>
