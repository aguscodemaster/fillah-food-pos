CREATE DATABASE IF NOT EXISTS fillah_food;
USE fillah_food;

-- 1. Tabel Pengguna (Sesuai dengan Modul Login & RBAC)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('Admin', 'Kasir') NOT NULL
) ENGINE=InnoDB;

-- Insert pengguna default (password: 12345)
INSERT INTO users (username, password, role) VALUES 
('admin_nihayatus', '12345', 'Admin'),
('kasir_1', '12345', 'Kasir')
ON DUPLICATE KEY UPDATE username=username;

-- 2. Tabel Bahan Baku (Untuk Modul Persediaan / Inventory)
CREATE TABLE IF NOT EXISTS bahan_baku (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_bahan VARCHAR(100) NOT NULL,
    stok DECIMAL(10,2) NOT NULL,
    satuan VARCHAR(20) NOT NULL,
    batas_minimum DECIMAL(10,2) NOT NULL
) ENGINE=InnoDB;

INSERT INTO bahan_baku (id, nama_bahan, stok, satuan, batas_minimum) VALUES 
(1, 'Ayam Potong Paha', 50, 'potong', 10),
(2, 'Beras', 20, 'kg', 5),
(3, 'Cabai Rawit', 5, 'kg', 1)
ON DUPLICATE KEY UPDATE nama_bahan=VALUES(nama_bahan);

-- 3. Tabel Produk/Menu (Untuk Modul Kasir / POS)
CREATE TABLE IF NOT EXISTS produk (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_produk VARCHAR(100) NOT NULL,
    harga DECIMAL(10,2) NOT NULL,
    kategori ENUM('Makanan', 'Minuman') NOT NULL
) ENGINE=InnoDB;

INSERT INTO produk (id, nama_produk, harga, kategori) VALUES 
(1, 'Ayam Geprek + Nasi', 10000, 'Makanan'),
(2, 'Es Teh Jumbo', 3000, 'Minuman')
ON DUPLICATE KEY UPDATE nama_produk=VALUES(nama_produk);

-- 4. Tabel Resep (Formula BOM untuk Pemotongan Stok Otomatis)
CREATE TABLE IF NOT EXISTS resep (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_menu INT NOT NULL,
    id_bahan INT NOT NULL,
    jumlah_dibutuhkan DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (id_menu) REFERENCES produk(id) ON DELETE CASCADE,
    FOREIGN KEY (id_bahan) REFERENCES bahan_baku(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Resep Menu 1 (Ayam Geprek + Nasi): 1 potong ayam, 0.1 kg beras, 0.02 kg cabai
INSERT INTO resep (id_menu, id_bahan, jumlah_dibutuhkan) VALUES 
(1, 1, 1.00),
(1, 2, 0.10),
(1, 3, 0.02);

-- 5. Tabel Transaksi Kasir
CREATE TABLE IF NOT EXISTS transaksi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tanggal_transaksi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    total_pembayaran DECIMAL(10,2) NOT NULL,
    uang_diterima DECIMAL(10,2) NOT NULL,
    kembalian DECIMAL(10,2) NOT NULL
) ENGINE=InnoDB;

-- 6. Tabel Detail Transaksi
CREATE TABLE IF NOT EXISTS detail_transaksi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_transaksi INT NOT NULL,
    id_menu INT NOT NULL,
    qty INT NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (id_transaksi) REFERENCES transaksi(id) ON DELETE CASCADE,
    FOREIGN KEY (id_menu) REFERENCES produk(id) ON DELETE CASCADE
) ENGINE=InnoDB;