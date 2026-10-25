CREATE DATABASE IF NOT EXISTS fillah_food;
USE fillah_food;

-- 1. Tabel Pengguna (Sesuai dengan Modul Login & RBAC Terpadu)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    nama_lengkap VARCHAR(100) NOT NULL DEFAULT '',
    email VARCHAR(100) NULL,
    role ENUM('Owner', 'Admin', 'Kasir') NOT NULL DEFAULT 'Kasir'
) ENGINE=InnoDB;

-- Insert pengguna default multi-role (password: 12345)
INSERT INTO users (username, password, nama_lengkap, email, role) VALUES 
('owner_nihayatus', '12345', 'Ibu Nihayatus Sofiyah (Pemilik)', 'owner@fillahfood.com', 'Owner'),
('admin_persediaan', '12345', 'Staf Pengelola Persediaan', 'admin@fillahfood.com', 'Admin'),
('kasir_1', '12345', 'Aisyah Marta Dewi (Kasir)', 'kasir@fillahfood.com', 'Kasir')
ON DUPLICATE KEY UPDATE role=VALUES(role), nama_lengkap=VALUES(nama_lengkap);

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
(3, 'Cabai Rawit', 5, 'kg', 1),
(4, 'Minyak Goreng', 15, 'liter', 3),
(5, 'Teh Melati', 20, 'bungkus', 5),
(6, 'Gula Pasir', 10, 'kg', 2),
(7, 'Telur Ayam', 30, 'butir', 5)
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
(2, 'Es Teh Jumbo', 3000, 'Minuman'),
(3, 'Ayam Geprek Sambal Bawang (A la Carte)', 8000, 'Makanan'),
(4, 'Ayam Geprek Keju Mozzarella', 15000, 'Makanan'),
(5, 'Es Jeruk Peras Segar', 5000, 'Minuman'),
(6, 'Paket Hemat Pelajar (Ayam Geprek + Nasi + Es Teh)', 12000, 'Makanan')
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

-- Resep Komposisi BOM (Bill of Materials)
INSERT INTO resep (id_menu, id_bahan, jumlah_dibutuhkan) VALUES 
(1, 1, 1.00), -- Menu 1 butuh 1 paha ayam
(1, 2, 0.10), -- 0.1 kg beras
(1, 3, 0.02), -- 0.02 kg cabai
(2, 5, 0.10), -- Es Teh butuh 0.1 bungkus teh
(2, 6, 0.03), -- 0.03 kg gula
(3, 1, 1.00),
(3, 3, 0.02),
(3, 4, 0.05),
(4, 1, 1.00),
(4, 2, 0.10),
(4, 3, 0.02),
(5, 6, 0.04),
(6, 1, 1.00),
(6, 2, 0.10),
(6, 3, 0.02),
(6, 5, 0.10);

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