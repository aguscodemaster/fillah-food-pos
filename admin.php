<?php
require_once 'auth_check.php';
protect_page(['Owner', 'Admin']);
require_once 'config.php';

// Migrasi alias sesi jika sebelumnya login menggunakan admin_nihayatus
if (isset($_SESSION['username']) && $_SESSION['username'] === 'admin_nihayatus') {
    $_SESSION['username'] = 'owner_nihayatus';
    $_SESSION['id_user']  = 1;
}

// Pastikan sesi id_user dan data pengguna tersinkronisasi
if ((!isset($_SESSION['id_user']) || $_SESSION['id_user'] == 3 || !isset($_SESSION['foto'])) && isset($_SESSION['username'])) {
    $q_u = $conn->prepare("SELECT id, nama_lengkap, role, foto FROM users WHERE username = ?");
    $q_u->bind_param("s", $_SESSION['username']);
    $q_u->execute();
    $res_u = $q_u->get_result();
    if ($r = $res_u->fetch_assoc()) {
        $_SESSION['id_user'] = $r['id'];
        if (empty($_SESSION['nama_lengkap'])) {
            $_SESSION['nama_lengkap'] = $r['nama_lengkap'];
        }
        if (empty($_SESSION['role'])) {
            $_SESSION['role'] = $r['role'];
        }
        $_SESSION['foto'] = $r['foto'] ?? '';
    }
}


// Helper rendering ikon SVG modern (tanpa emoji kasar)
if (!function_exists('ui_icon')) {
    function ui_icon($name, $size = 16, $class = '', $style = '') {
        $style_attr = 'width:' . $size . 'px; height:' . $size . 'px; vertical-align: middle; flex-shrink: 0;' . $style;
        $class_attr = $class ? ' class="' . $class . '"' : '';
        return '<svg' . $class_attr . ' style="' . $style_attr . '"><use href="#icon-' . $name . '"></use></svg>';
    }
}
$pesan_sukses = "";
$pesan_error = "";

// Status Tab Aktif (Dashboard, Produk, Stok, Pengguna, Peran, atau Profil)
$tab_aktif = isset($_GET['tab']) ? trim($_GET['tab']) : 'dashboard';
if ($tab_aktif === 'user-setting') {
    $tab_aktif = 'profil';
}
if (isset($_POST['tambah_pengguna']) || isset($_POST['edit_pengguna']) || isset($_POST['hapus_pengguna'])) {
    $tab_aktif = 'pengguna';
} elseif (isset($_POST['tambah_peran']) || isset($_POST['edit_peran']) || isset($_POST['hapus_peran']) || isset($_POST['ubah_peran_pengguna'])) {
    $tab_aktif = 'peran';
} elseif (isset($_POST['simpan_profil_saya']) || isset($_POST['simpan_setting_user'])) {
    $tab_aktif = 'profil';
} elseif (isset($_POST['tambah_stok_masuk']) || isset($_POST['catat_stok_keluar']) || isset($_POST['tambah_bahan_baru']) || isset($_POST['edit_bahan']) || isset($_POST['hapus_bahan'])) {
    $tab_aktif = 'stok';
} elseif (isset($_POST['tambah_produk_baru']) || isset($_POST['edit_produk']) || isset($_POST['hapus_produk']) || isset($_POST['simpan_resep_item']) || isset($_POST['hapus_resep_item'])) {
    $tab_aktif = 'produk';
}
if (in_array($tab_aktif, ['pengguna', 'peran']) && !is_owner()) {
    $tab_aktif = 'dashboard';
}

// --- Migrasi Otomatis: Tambah kolom gambar jika belum ada (kompatibel MySQL 5.x) ---
$db_name = $conn->query("SELECT DATABASE()")->fetch_row()[0];
$chk_produk = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='$db_name' AND TABLE_NAME='produk' AND COLUMN_NAME='gambar'")->num_rows;
if ($chk_produk === 0) $conn->query("ALTER TABLE produk ADD COLUMN gambar VARCHAR(255) NULL");
$chk_bahan = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='$db_name' AND TABLE_NAME='bahan_baku' AND COLUMN_NAME='gambar'")->num_rows;
if ($chk_bahan === 0) $conn->query("ALTER TABLE bahan_baku ADD COLUMN gambar VARCHAR(255) NULL");
$chk_user_nl = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='$db_name' AND TABLE_NAME='users' AND COLUMN_NAME='nama_lengkap'")->num_rows;
if ($chk_user_nl === 0) $conn->query("ALTER TABLE users ADD COLUMN nama_lengkap VARCHAR(100) NOT NULL DEFAULT ''");
$chk_user_em = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='$db_name' AND TABLE_NAME='users' AND COLUMN_NAME='email'")->num_rows;
if ($chk_user_em === 0) $conn->query("ALTER TABLE users ADD COLUMN email VARCHAR(100) NULL");
$chk_user_foto = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='$db_name' AND TABLE_NAME='users' AND COLUMN_NAME='foto'")->num_rows;
if ($chk_user_foto === 0) $conn->query("ALTER TABLE users ADD COLUMN foto VARCHAR(255) NULL");

// Auto-Seed Menu Lengkap Banner Resmi Fillah Food & Drink (20 Menu)
$cnt_prod = $conn->query("SELECT COUNT(*) FROM produk");
if ($cnt_prod && ($row_p = $cnt_prod->fetch_row())) {
    if ($row_p[0] < 20) {
        $conn->query("INSERT INTO produk (id, nama_produk, harga, kategori, gambar) VALUES 
            (1, 'Ayam Geprek + Nasi', 10000, 'Makanan', 'uploads/menu_ayam_geprek.png'),
            (2, 'Es Teh Jumbo', 3000, 'Minuman', 'uploads/menu_aneka_es.png'),
            (3, 'Ayam Saja', 8000, 'Makanan', 'uploads/menu_ayam_geprek.png'),
            (4, 'Ayam Geprek Keju Mozzarella', 15000, 'Makanan', 'uploads/menu_ayam_geprek.png'),
            (5, 'Es Jeruk Peras Segar', 5000, 'Minuman', 'uploads/menu_aneka_es.png'),
            (6, 'Paket Hemat Pelajar (Ayam Geprek + Nasi + Es Teh)', 12000, 'Makanan', 'uploads/menu_ayam_geprek.png'),
            (7, 'Sosis Kocek', 5000, 'Makanan', 'uploads/menu_sosis_kocek.png'),
            (8, 'Tahu Kocek', 5000, 'Makanan', 'uploads/menu_tahu_kocek.png'),
            (9, 'Tahu Krispi', 5000, 'Makanan', 'uploads/menu_tahu_krispi.png'),
            (10, 'Es Teh Cup Biasa', 2000, 'Minuman', 'uploads/menu_aneka_es.png'),
            (11, 'Es Jeruk Jumbo', 4000, 'Minuman', 'uploads/menu_aneka_es.png'),
            (12, 'Es Jeruk Cup Biasa', 3000, 'Minuman', 'uploads/menu_aneka_es.png'),
            (13, 'Es Chocolatos', 5000, 'Minuman', 'uploads/menu_aneka_es.png'),
            (14, 'Es Milo', 5000, 'Minuman', 'uploads/menu_aneka_es.png'),
            (15, 'Pop Ice', 4000, 'Minuman', 'uploads/menu_aneka_es.png'),
            (16, 'Es Kopi', 5000, 'Minuman', 'uploads/menu_aneka_es.png'),
            (17, 'Nutrisari', 3000, 'Minuman', 'uploads/menu_aneka_es.png'),
            (18, 'Matcha / Brown Sugar', 5000, 'Minuman', 'uploads/menu_aneka_es.png'),
            (19, 'Teajus', 1000, 'Minuman', 'uploads/menu_aneka_es.png'),
            (20, 'Marimas', 2000, 'Minuman', 'uploads/menu_aneka_es.png')
            ON DUPLICATE KEY UPDATE nama_produk=VALUES(nama_produk), harga=VALUES(harga), kategori=VALUES(kategori), gambar=VALUES(gambar)");
    }
    $conn->query("UPDATE produk SET gambar='uploads/menu_ayam_geprek.png' WHERE id IN (1, 3, 4, 6) AND (gambar IS NULL OR gambar = '')");
    $conn->query("UPDATE produk SET gambar='uploads/menu_aneka_es.png' WHERE id IN (2, 5) AND (gambar IS NULL OR gambar = '')");
    $conn->query("UPDATE produk SET nama_produk='Ayam Saja' WHERE id=3 AND nama_produk LIKE '%A la Carte%'");
}

// --- Migrasi Otomatis: Tabel riwayat_stok (Inbound & Outbound Log) ---
$chk_riwayat = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='$db_name' AND TABLE_NAME='riwayat_stok'")->num_rows;
if ($chk_riwayat === 0) {
    $conn->query("CREATE TABLE riwayat_stok (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_bahan INT NOT NULL,
        tipe ENUM('masuk', 'keluar_rusak', 'keluar_penjualan') NOT NULL,
        jumlah DECIMAL(10,2) NOT NULL,
        keterangan VARCHAR(255) NULL,
        dicatat_oleh VARCHAR(100) NULL,
        waktu TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_bahan) REFERENCES bahan_baku(id) ON DELETE CASCADE
    ) ENGINE=InnoDB;");
}

// --- Migrasi Otomatis: Tabel roles & kolom role dinamis pada users ---
$chk_roles = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='$db_name' AND TABLE_NAME='roles'")->num_rows;
if ($chk_roles === 0) {
    $conn->query("CREATE TABLE roles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama_peran VARCHAR(50) NOT NULL UNIQUE,
        deskripsi VARCHAR(255) NOT NULL,
        akses_pos TINYINT(1) DEFAULT 1,
        akses_dashboard TINYINT(1) DEFAULT 0,
        akses_stok TINYINT(1) DEFAULT 0,
        akses_menu TINYINT(1) DEFAULT 0,
        akses_pengguna TINYINT(1) DEFAULT 0,
        dibuat_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $conn->query("INSERT INTO roles (nama_peran, deskripsi, akses_pos, akses_dashboard, akses_stok, akses_menu, akses_pengguna) VALUES
        ('Owner', 'Pemilik Usaha - Hak akses penuh seluruh sistem, finansial, dan pengguna', 1, 1, 1, 1, 1),
        ('Admin', 'Staf Persediaan - Pengelolaan stok bahan baku, belanja persediaan, dan menu', 1, 1, 1, 1, 0),
        ('User', 'Pengguna Operasional - Hak akses transaksi kasir POS dan cetak nota struk', 1, 0, 0, 0, 0)
    ");
}
$col_info = $conn->query("SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='$db_name' AND TABLE_NAME='users' AND COLUMN_NAME='role'")->fetch_assoc();
if ($col_info && strtolower($col_info['DATA_TYPE']) === 'enum') {
    $conn->query("ALTER TABLE users MODIFY COLUMN role VARCHAR(50) NOT NULL DEFAULT 'User'");
    $conn->query("UPDATE users SET role = 'User' WHERE role = 'Kasir'");
}

// Helper: Proses Upload Gambar (opsional)
if (!function_exists('prosesUploadGambar')) {
    function prosesUploadGambar(string $field, string $prefix): string {
        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
            return ''; // Tidak ada file atau error, kembalikan kosong (pakai gambar default)
        }
        $tmp  = $_FILES[$field]['tmp_name'];
        $name = $_FILES[$field]['name'];
        $size = $_FILES[$field]['size'];
        $mime = mime_content_type($tmp);

        $allowed_mime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($mime, $allowed_mime)) return '';
        if ($size > 2 * 1024 * 1024) return ''; // Maks 2 MB

        $ext      = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $filename = $prefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest     = __DIR__ . '/uploads/' . $filename;

        return move_uploaded_file($tmp, $dest) ? 'uploads/' . $filename : '';
    }
}

$logged_user = !empty($_SESSION['nama_lengkap']) ? $_SESSION['nama_lengkap'] : (isset($_SESSION['username']) ? $_SESSION['username'] : 'Admin');

// 1. Logika Tambah Stok Masuk (Inbound Stock - saat belanja/kulakan bahan baku)
if (isset($_POST['tambah_stok_masuk'])) {
    $id_bahan = intval($_POST['id_bahan']);
    $jumlah_masuk = floatval($_POST['jumlah_masuk']);
    $catatan = trim($_POST['catatan_masuk']);

    if ($jumlah_masuk > 0) {
        $stmt_in = $conn->prepare("UPDATE bahan_baku SET stok = stok + ? WHERE id = ?");
        $stmt_in->bind_param("di", $jumlah_masuk, $id_bahan);
        if ($stmt_in->execute()) {
            $pesan_sukses = "Berhasil menambahkan stok bahan baku sebanyak $jumlah_masuk!";
            // Catat ke riwayat mutasi stok
            $stmt_log = $conn->prepare("INSERT INTO riwayat_stok (id_bahan, tipe, jumlah, keterangan, dicatat_oleh) VALUES (?, 'masuk', ?, ?, ?)");
            $stmt_log->bind_param("idss", $id_bahan, $jumlah_masuk, $catatan, $logged_user);
            $stmt_log->execute();
        } else {
            $pesan_error = "Gagal memperbarui stok: " . $conn->error;
        }
    } else {
        $pesan_error = "Jumlah stok masuk harus lebih dari 0.";
    }
}

// 1b. Logika Catat Stok Keluar (Outbound - Bahan Rusak / Basi / Kadaluwarsa / Penyesuaian)
if (isset($_POST['catat_stok_keluar'])) {
    $id_bahan = intval($_POST['id_bahan']);
    $jumlah_keluar = floatval($_POST['jumlah_keluar']);
    $alasan = trim($_POST['alasan']);
    $catatan = trim($_POST['catatan_keluar']);
    $keterangan_full = $alasan . (!empty($catatan) ? " (" . $catatan . ")" : "");

    if ($jumlah_keluar > 0) {
        $stmt_out = $conn->prepare("UPDATE bahan_baku SET stok = GREATEST(0, stok - ?) WHERE id = ?");
        $stmt_out->bind_param("di", $jumlah_keluar, $id_bahan);
        if ($stmt_out->execute()) {
            $pesan_sukses = "Berhasil mencatat pengeluaran bahan rusak/basi sebanyak $jumlah_keluar!";
            // Catat ke riwayat mutasi stok
            $stmt_log = $conn->prepare("INSERT INTO riwayat_stok (id_bahan, tipe, jumlah, keterangan, dicatat_oleh) VALUES (?, 'keluar_rusak', ?, ?, ?)");
            $stmt_log->bind_param("idss", $id_bahan, $jumlah_keluar, $keterangan_full, $logged_user);
            $stmt_log->execute();
        } else {
            $pesan_error = "Gagal memproses stok keluar: " . $conn->error;
        }
    } else {
        $pesan_error = "Jumlah stok keluar harus lebih dari 0.";
    }
}

// 2. Logika Tambah Bahan Baku Baru
if (isset($_POST['tambah_bahan_baru'])) {
    $nama_bahan    = trim($_POST['nama_bahan']);
    $satuan        = trim($_POST['satuan']);
    $stok_awal     = floatval($_POST['stok_awal']);
    $batas_minimum = floatval($_POST['batas_minimum']);

    if (!empty($nama_bahan) && !empty($satuan)) {
        $gambar_bahan = prosesUploadGambar('gambar_bahan', 'bahan');
        $stmt_new = $conn->prepare("INSERT INTO bahan_baku (nama_bahan, stok, satuan, batas_minimum, gambar) VALUES (?, ?, ?, ?, ?)");
        $stmt_new->bind_param("sdsds", $nama_bahan, $stok_awal, $satuan, $batas_minimum, $gambar_bahan);
        if ($stmt_new->execute()) {
            $new_bahan_id = $conn->insert_id;
            $pesan_sukses = "Bahan baku baru '$nama_bahan' berhasil didaftarkan!";
            if ($stok_awal > 0) {
                $ket_awal = "Saldo stok awal saat pendaftaran";
                $stmt_log = $conn->prepare("INSERT INTO riwayat_stok (id_bahan, tipe, jumlah, keterangan, dicatat_oleh) VALUES (?, 'masuk', ?, ?, ?)");
                $stmt_log->bind_param("idss", $new_bahan_id, $stok_awal, $ket_awal, $logged_user);
                $stmt_log->execute();
            }
        } else {
            $pesan_error = "Gagal menambahkan bahan baku: " . $conn->error;
        }
    }
}

// 2b. Logika Edit Bahan Baku
if (isset($_POST['edit_bahan'])) {
    $id_bahan      = intval($_POST['id_bahan']);
    $nama_bahan    = trim($_POST['nama_bahan']);
    $satuan        = trim($_POST['satuan']);
    $batas_minimum = floatval($_POST['batas_minimum']);

    if (!empty($nama_bahan) && !empty($satuan) && $id_bahan > 0) {
        $gambar_baru = prosesUploadGambar('gambar_bahan_edit', 'bahan');
        if (!empty($gambar_baru)) {
            $stmt_eb = $conn->prepare("UPDATE bahan_baku SET nama_bahan = ?, satuan = ?, batas_minimum = ?, gambar = ? WHERE id = ?");
            $stmt_eb->bind_param("ssdsi", $nama_bahan, $satuan, $batas_minimum, $gambar_baru, $id_bahan);
        } else {
            $stmt_eb = $conn->prepare("UPDATE bahan_baku SET nama_bahan = ?, satuan = ?, batas_minimum = ? WHERE id = ?");
            $stmt_eb->bind_param("ssdi", $nama_bahan, $satuan, $batas_minimum, $id_bahan);
        }
        if ($stmt_eb->execute()) {
            $pesan_sukses = "Data bahan baku '$nama_bahan' berhasil diperbarui!";
        } else {
            $pesan_error = "Gagal memperbarui bahan baku: " . $conn->error;
        }
    }
}

// 2c. Logika Hapus Bahan Baku
if (isset($_POST['hapus_bahan'])) {
    $id_bahan = intval($_POST['id_bahan']);
    if ($id_bahan > 0) {
        $stmt_db = $conn->prepare("DELETE FROM bahan_baku WHERE id = ?");
        $stmt_db->bind_param("i", $id_bahan);
        if ($stmt_db->execute()) {
            $pesan_sukses = "Bahan baku berhasil dihapus dari sistem.";
        } else {
            $pesan_error = "Gagal menghapus bahan baku: " . $conn->error;
        }
    }
}

// 3. Logika Tambah Menu Produk Baru (Makanan / Minuman)
if (isset($_POST['tambah_produk_baru'])) {
    $nama_produk = trim($_POST['nama_produk']);
    $harga       = floatval($_POST['harga']);
    $kategori    = trim($_POST['kategori']);

    if (!empty($nama_produk) && $harga > 0) {
        $gambar_produk = prosesUploadGambar('gambar_produk', 'produk');
        $stmt_p = $conn->prepare("INSERT INTO produk (nama_produk, harga, kategori, gambar) VALUES (?, ?, ?, ?)");
        $stmt_p->bind_param("sdss", $nama_produk, $harga, $kategori, $gambar_produk);
        if ($stmt_p->execute()) {
            $pesan_sukses = "Menu produk baru '$nama_produk' berhasil ditambahkan ke katalog!";
        } else {
            $pesan_error = "Gagal menambahkan menu produk: " . $conn->error;
        }
    }
}

// 3b. Logika Edit Menu Produk
if (isset($_POST['edit_produk'])) {
    $id_produk   = intval($_POST['id_produk']);
    $nama_produk = trim($_POST['nama_produk']);
    $harga       = floatval($_POST['harga']);
    $kategori    = trim($_POST['kategori']);

    if (!empty($nama_produk) && $harga > 0 && $id_produk > 0) {
        $gambar_baru = prosesUploadGambar('gambar_produk_edit', 'produk');
        if (!empty($gambar_baru)) {
            $stmt_ep = $conn->prepare("UPDATE produk SET nama_produk = ?, harga = ?, kategori = ?, gambar = ? WHERE id = ?");
            $stmt_ep->bind_param("sdssi", $nama_produk, $harga, $kategori, $gambar_baru, $id_produk);
        } else {
            $stmt_ep = $conn->prepare("UPDATE produk SET nama_produk = ?, harga = ?, kategori = ? WHERE id = ?");
            $stmt_ep->bind_param("sdsi", $nama_produk, $harga, $kategori, $id_produk);
        }
        if ($stmt_ep->execute()) {
            $pesan_sukses = "Menu '$nama_produk' berhasil diperbarui!";
        } else {
            $pesan_error = "Gagal memperbarui menu: " . $conn->error;
        }
    }
}

// 3c. Logika Hapus Menu Produk
if (isset($_POST['hapus_produk'])) {
    $id_produk = intval($_POST['id_produk']);
    if ($id_produk > 0) {
        $stmt_dp = $conn->prepare("DELETE FROM produk WHERE id = ?");
        $stmt_dp->bind_param("i", $id_produk);
        if ($stmt_dp->execute()) {
            $pesan_sukses = "Menu produk berhasil dihapus dari katalog.";
        } else {
            $pesan_error = "Gagal menghapus menu: " . $conn->error;
        }
    }
}

// 3d. Logika Mapping Resep (Tambah / Ubah Komposisi Bahan Menu)
if (isset($_POST['simpan_resep_item'])) {
    $id_menu   = intval($_POST['id_menu']);
    $id_bahan  = intval($_POST['id_bahan']);
    $takaran   = floatval($_POST['jumlah_dibutuhkan']);

    if ($id_menu > 0 && $id_bahan > 0 && $takaran > 0) {
        // Cek apakah bahan sudah ada di resep menu ini
        $stmt_cr = $conn->prepare("SELECT id FROM resep WHERE id_menu = ? AND id_bahan = ?");
        $stmt_cr->bind_param("ii", $id_menu, $id_bahan);
        $stmt_cr->execute();
        $res_cr = $stmt_cr->get_result();

        if ($r_row = $res_cr->fetch_assoc()) {
            $stmt_ur = $conn->prepare("UPDATE resep SET jumlah_dibutuhkan = ? WHERE id = ?");
            $stmt_ur->bind_param("di", $takaran, $r_row['id']);
            $stmt_ur->execute();
            $pesan_sukses = "Takaran bahan pada resep berhasil diperbarui!";
        } else {
            $stmt_ir = $conn->prepare("INSERT INTO resep (id_menu, id_bahan, jumlah_dibutuhkan) VALUES (?, ?, ?)");
            $stmt_ir->bind_param("iid", $id_menu, $id_bahan, $takaran);
            $stmt_ir->execute();
            $pesan_sukses = "Bahan baku berhasil ditambahkan ke dalam formula resep menu!";
        }
    } else {
        $pesan_error = "Mohon pilih bahan baku dan isi takaran yang valid.";
    }
}

// 3e. Logika Hapus Bahan dari Resep
if (isset($_POST['hapus_resep_item'])) {
    $id_resep = intval($_POST['id_resep']);
    if ($id_resep > 0) {
        $stmt_dr = $conn->prepare("DELETE FROM resep WHERE id = ?");
        $stmt_dr->bind_param("i", $id_resep);
        if ($stmt_dr->execute()) {
            $pesan_sukses = "Bahan berhasil dikeluarkan dari resep menu.";
        } else {
            $pesan_error = "Gagal menghapus bahan dari resep: " . $conn->error;
        }
    }
}

// 2c. Logika Manajemen Pengguna & Hak Akses RBAC (Khusus Hak Akses Owner)
if (is_owner()) {
    // Tambah Pengguna Baru
    if (isset($_POST['tambah_pengguna'])) {
        $user_new = trim($_POST['username']);
        $pass_new = trim($_POST['password']);
        $nama_new = trim($_POST['nama_lengkap']);
        $email_new = trim($_POST['email']);
        $role_new = trim($_POST['role']);

        if (!in_array($role_new, ['Owner', 'Admin', 'Kasir'])) {
            $pesan_error = "Peran (role) yang dipilih tidak valid!";
        } elseif (empty($user_new) || empty($pass_new) || empty($nama_new)) {
            $pesan_error = "Nama lengkap, username, dan password wajib diisi!";
        } else {
            // Cek apakah username sudah ada
            $cek = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $cek->bind_param("s", $user_new);
            $cek->execute();
            if ($cek->get_result()->num_rows > 0) {
                $pesan_error = "Username '$user_new' sudah digunakan! Silakan gunakan username lain.";
            } else {
                $ins = $conn->prepare("INSERT INTO users (username, password, nama_lengkap, email, role) VALUES (?, ?, ?, ?, ?)");
                $ins->bind_param("sssss", $user_new, $pass_new, $nama_new, $email_new, $role_new);
                if ($ins->execute()) {
                    $pesan_sukses = "Pengguna baru '$nama_new' (@$user_new) dengan peran '$role_new' berhasil didaftarkan!";
                } else {
                    $pesan_error = "Gagal mendaftarkan pengguna: " . $conn->error;
                }
            }
        }
    }

    // Edit Pengguna / Role
    if (isset($_POST['edit_pengguna'])) {
        $id_target = intval($_POST['id_user']);
        $nama_target = trim($_POST['nama_lengkap']);
        $email_target = trim($_POST['email']);
        $role_target = trim($_POST['role']);
        $pass_target = trim($_POST['password']);

        if (!in_array($role_target, ['Owner', 'Admin', 'Kasir'])) {
            $pesan_error = "Peran (role) yang dipilih tidak valid!";
        } elseif (empty($nama_target)) {
            $pesan_error = "Nama lengkap tidak boleh kosong!";
        } else {
            if (!empty($pass_target)) {
                $stmt_up = $conn->prepare("UPDATE users SET nama_lengkap = ?, email = ?, role = ?, password = ? WHERE id = ?");
                $stmt_up->bind_param("ssssi", $nama_target, $email_target, $role_target, $pass_target, $id_target);
            } else {
                $stmt_up = $conn->prepare("UPDATE users SET nama_lengkap = ?, email = ?, role = ? WHERE id = ?");
                $stmt_up->bind_param("sssi", $nama_target, $email_target, $role_target, $id_target);
            }
            if ($stmt_up->execute()) {
                if (isset($_SESSION['id_user']) && $_SESSION['id_user'] == $id_target) {
                    $_SESSION['nama_lengkap'] = $nama_target;
                    $_SESSION['role'] = $role_target;
                }
                $pesan_sukses = "Data dan hak akses pengguna berhasil diperbarui!";
            } else {
                $pesan_error = "Gagal memperbarui pengguna: " . $conn->error;
            }
        }
    }

    // Hapus Pengguna
    if (isset($_POST['hapus_pengguna'])) {
        $id_del = intval($_POST['id_user_hapus']);
        if (isset($_SESSION['id_user']) && $id_del == $_SESSION['id_user']) {
            $pesan_error = "Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif!";
        } else {
            $stmt_del = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt_del->bind_param("i", $id_del);
            if ($stmt_del->execute()) {
                $pesan_sukses = "Pengguna berhasil dihapus dari sistem.";
            } else {
                $pesan_error = "Gagal menghapus pengguna: " . $conn->error;
            }
        }
    }

    // 2d. Simpan Pengaturan Pengguna (User Setting oleh Owner - Profil, Sandi, & Foto Profil)
    if (isset($_POST['simpan_setting_user'])) {
        $id_target    = intval($_POST['id_user_setting']);
        $nama_target  = trim($_POST['nama_lengkap_setting']);
        $email_target = trim($_POST['email_setting']);
        $pass_target  = trim($_POST['password_setting']);
        $foto_target  = prosesUploadGambar('foto_user_setting', 'user');

        if (empty($nama_target)) {
            $pesan_error = "Nama lengkap pengguna tidak boleh kosong!";
        } else {
            if (!empty($pass_target)) {
                $stmt_up = $conn->prepare("UPDATE users SET nama_lengkap = ?, email = ?, password = ? WHERE id = ?");
                $stmt_up->bind_param("sssi", $nama_target, $email_target, $pass_target, $id_target);
            } else {
                $stmt_up = $conn->prepare("UPDATE users SET nama_lengkap = ?, email = ? WHERE id = ?");
                $stmt_up->bind_param("ssi", $nama_target, $email_target, $id_target);
            }
            if ($stmt_up->execute()) {
                if (!empty($foto_target)) {
                    $stmt_f = $conn->prepare("UPDATE users SET foto = ? WHERE id = ?");
                    $stmt_f->bind_param("si", $foto_target, $id_target);
                    $stmt_f->execute();
                    if (isset($_SESSION['id_user']) && $_SESSION['id_user'] == $id_target) {
                        $_SESSION['foto'] = $foto_target;
                    }
                }
                if (isset($_SESSION['id_user']) && $_SESSION['id_user'] == $id_target) {
                    $_SESSION['nama_lengkap'] = $nama_target;
                }
                $pesan_sukses = "Pengaturan pengguna dan foto profil berhasil disimpan!";
            } else {
                $pesan_error = "Gagal menyimpan pengaturan pengguna: " . $conn->error;
            }
        }
    }


    // 2f. Tambah Peran Baru (Khusus Tab Peran)
    if (isset($_POST['tambah_peran'])) {
        $nama_peran = trim($_POST['nama_peran']);
        $deskripsi  = trim($_POST['deskripsi']);
        $akses_pos  = isset($_POST['akses_pos']) ? 1 : 0;
        $akses_dash = isset($_POST['akses_dashboard']) ? 1 : 0;
        $akses_stok = isset($_POST['akses_stok']) ? 1 : 0;
        $akses_menu = isset($_POST['akses_menu']) ? 1 : 0;
        $akses_peng = isset($_POST['akses_pengguna']) ? 1 : 0;

        if (empty($nama_peran)) {
            $pesan_error = "Nama peran tidak boleh kosong!";
        } else {
            $cek = $conn->prepare("SELECT id FROM roles WHERE LOWER(nama_peran) = LOWER(?)");
            $cek->bind_param("s", $nama_peran);
            $cek->execute();
            if ($cek->get_result()->num_rows > 0) {
                $pesan_error = "Peran '$nama_peran' sudah terdaftar di sistem!";
            } else {
                $ins = $conn->prepare("INSERT INTO roles (nama_peran, deskripsi, akses_pos, akses_dashboard, akses_stok, akses_menu, akses_pengguna) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $ins->bind_param("ssiiiii", $nama_peran, $deskripsi, $akses_pos, $akses_dash, $akses_stok, $akses_menu, $akses_peng);
                if ($ins->execute()) {
                    $pesan_sukses = "Peran baru '$nama_peran' berhasil ditambahkan ke daftar peran!";
                } else {
                    $pesan_error = "Gagal menambahkan peran baru: " . $conn->error;
                }
            }
        }
    }

    // 2g. Edit Peran (Khusus Tab Peran)
    if (isset($_POST['edit_peran'])) {
        $id_role    = intval($_POST['id_role']);
        $nama_peran = trim($_POST['nama_peran']);
        $deskripsi  = trim($_POST['deskripsi']);
        $akses_pos  = isset($_POST['akses_pos']) ? 1 : 0;
        $akses_dash = isset($_POST['akses_dashboard']) ? 1 : 0;
        $akses_stok = isset($_POST['akses_stok']) ? 1 : 0;
        $akses_menu = isset($_POST['akses_menu']) ? 1 : 0;
        $akses_peng = isset($_POST['akses_pengguna']) ? 1 : 0;

        if (empty($nama_peran)) {
            $pesan_error = "Nama peran tidak boleh kosong!";
        } else {
            $q_old = $conn->prepare("SELECT nama_peran FROM roles WHERE id = ?");
            $q_old->bind_param("i", $id_role);
            $q_old->execute();
            $old_role = $q_old->get_result()->fetch_assoc()['nama_peran'] ?? '';

            // Peran Owner selalu memiliki hak akses penuh untuk integritas sistem
            if ($old_role === 'Owner') {
                $nama_peran = 'Owner';
                $akses_pos = 1; $akses_dash = 1; $akses_stok = 1; $akses_menu = 1; $akses_peng = 1;
            }

            $stmt_up = $conn->prepare("UPDATE roles SET nama_peran = ?, deskripsi = ?, akses_pos = ?, akses_dashboard = ?, akses_stok = ?, akses_menu = ?, akses_pengguna = ? WHERE id = ?");
            $stmt_up->bind_param("ssiiiiii", $nama_peran, $deskripsi, $akses_pos, $akses_dash, $akses_stok, $akses_menu, $akses_peng, $id_role);
            if ($stmt_up->execute()) {
                if (!empty($old_role) && $old_role !== $nama_peran) {
                    $up_u = $conn->prepare("UPDATE users SET role = ? WHERE role = ?");
                    $up_u->bind_param("ss", $nama_peran, $old_role);
                    $up_u->execute();
                    if ($_SESSION['role'] === $old_role) {
                        $_SESSION['role'] = $nama_peran;
                    }
                }
                $pesan_sukses = "Data peran '$nama_peran' berhasil diperbarui!";
            } else {
                $pesan_error = "Gagal memperbarui peran: " . $conn->error;
            }
        }
    }

    // 2h. Hapus Peran (Khusus Tab Peran)
    if (isset($_POST['hapus_peran'])) {
        $id_role = intval($_POST['id_role_hapus']);
        $q_chk = $conn->prepare("SELECT nama_peran FROM roles WHERE id = ?");
        $q_chk->bind_param("i", $id_role);
        $q_chk->execute();
        $r_chk = $q_chk->get_result()->fetch_assoc();
        $nama_del = $r_chk['nama_peran'] ?? '';

        if (in_array($nama_del, ['Owner', 'Admin', 'User'])) {
            $pesan_error = "Peran inti sistem ('$nama_del') tidak dapat dihapus!";
        } else {
            $cek_u = $conn->prepare("SELECT COUNT(*) AS total FROM users WHERE role = ?");
            $cek_u->bind_param("s", $nama_del);
            $cek_u->execute();
            $tot_u = $cek_u->get_result()->fetch_assoc()['total'];
            if ($tot_u > 0) {
                $pesan_error = "Tidak dapat menghapus peran '$nama_del' karena sedang digunakan oleh $tot_u akun pengguna!";
            } else {
                $stmt_del = $conn->prepare("DELETE FROM roles WHERE id = ?");
                $stmt_del->bind_param("i", $id_role);
                if ($stmt_del->execute()) {
                    $pesan_sukses = "Peran '$nama_del' berhasil dihapus dari daftar peran.";
                } else {
                    $pesan_error = "Gagal menghapus peran: " . $conn->error;
                }
            }
        }
    }

    // 2i. Ubah Peran Pengguna Cepat
    if (isset($_POST['ubah_peran_pengguna'])) {
        $id_target   = intval($_POST['id_user_peran']);
        $role_target = trim($_POST['role_baru']);

        if (empty($role_target)) {
            $pesan_error = "Peran yang dipilih tidak valid!";
        } else {
            if (isset($_SESSION['id_user']) && $_SESSION['id_user'] == $id_target && $role_target !== 'Owner') {
                $pesan_error = "Anda tidak dapat menurunkan peran akun Anda sendiri yang sedang aktif!";
            } else {
                $stmt_r = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
                $stmt_r->bind_param("si", $role_target, $id_target);
                if ($stmt_r->execute()) {
                    if (isset($_SESSION['id_user']) && $_SESSION['id_user'] == $id_target) {
                        $_SESSION['role'] = $role_target;
                    }
                    $pesan_sukses = "Peran pengguna berhasil diperbarui menjadi '$role_target'!";
                } else {
                    $pesan_error = "Gagal memperbarui peran pengguna: " . $conn->error;
                }
            }
        }
    }
}

// 2e. Simpan Profil, Kata Sandi, & Foto Profil Saya (Owner & Admin)
if (isset($_POST['simpan_profil_saya'])) {
    $id_saya    = intval($_SESSION['id_user'] ?? 0);
    $nama_saya  = trim($_POST['nama_profil']);
    $email_saya = trim($_POST['email_profil']);
    $pass_baru  = trim($_POST['password_baru_profil']);
    $pass_konf  = trim($_POST['konfirmasi_password_profil']);
    $foto_saya  = prosesUploadGambar('foto_profil_saya', 'user');

    if (empty($nama_saya)) {
        $pesan_error = "Nama lengkap profil tidak boleh kosong!";
    } elseif (!empty($pass_baru) && $pass_baru !== $pass_konf) {
        $pesan_error = "Konfirmasi kata sandi baru tidak cocok!";
    } else {
        if (!empty($pass_baru)) {
            $stmt_me = $conn->prepare("UPDATE users SET nama_lengkap = ?, email = ?, password = ? WHERE id = ?");
            $stmt_me->bind_param("sssi", $nama_saya, $email_saya, $pass_baru, $id_saya);
        } else {
            $stmt_me = $conn->prepare("UPDATE users SET nama_lengkap = ?, email = ? WHERE id = ?");
            $stmt_me->bind_param("ssi", $nama_saya, $email_saya, $id_saya);
        }
        if ($stmt_me->execute()) {
            $_SESSION['nama_lengkap'] = $nama_saya;
            if (!empty($foto_saya)) {
                $stmt_fs = $conn->prepare("UPDATE users SET foto = ? WHERE id = ?");
                $stmt_fs->bind_param("si", $foto_saya, $id_saya);
                $stmt_fs->execute();
                $_SESSION['foto'] = $foto_saya;
            }
            $pesan_sukses = "Profil, kata sandi, dan foto profil Anda berhasil diperbarui!";
        } else {
            $pesan_error = "Gagal memperbarui profil: " . $conn->error;
        }
    }
}

// 3. Ambil Data Metrik KPI & Analytics Matrix Dashboard
// Omzet Hari Ini
$q_today = $conn->query("SELECT COALESCE(SUM(total_pembayaran), 0) AS omzet_today, COUNT(*) AS trx_today FROM transaksi WHERE DATE(tanggal_transaksi) = CURDATE()");
$kpi_today = $q_today ? $q_today->fetch_assoc() : ['omzet_today' => 0, 'trx_today' => 0];

// Omzet Bulan Berjalan
$q_month = $conn->query("SELECT COALESCE(SUM(total_pembayaran), 0) AS omzet_month, COUNT(*) AS trx_month FROM transaksi WHERE DATE_FORMAT(tanggal_transaksi, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')");
$kpi_month = $q_month ? $q_month->fetch_assoc() : ['omzet_month' => 0, 'trx_month' => 0];

// Total Porsi Terjual Bulan Berjalan
$q_porsi = $conn->query("SELECT COALESCE(SUM(dt.qty), 0) AS total_porsi FROM detail_transaksi dt JOIN transaksi t ON dt.id_transaksi = t.id WHERE DATE_FORMAT(t.tanggal_transaksi, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')");
$total_porsi_bulan = $q_porsi ? $q_porsi->fetch_assoc()['total_porsi'] : 0;

// Rata-rata Nilai per Transaksi (Average Order Value)
$aov_today = ($kpi_today['trx_today'] > 0) ? ($kpi_today['omzet_today'] / $kpi_today['trx_today']) : 0;
$aov_month = ($kpi_month['trx_month'] > 0) ? ($kpi_month['omzet_month'] / $kpi_month['trx_month']) : 0;

// Total Seluruh Transaksi & Omzet Akumulasi Sejak Awal
$q_all = $conn->query("SELECT COALESCE(SUM(total_pembayaran), 0) AS total_omzet, COUNT(*) AS total_trx FROM transaksi");
$kpi_all = $q_all ? $q_all->fetch_assoc() : ['total_omzet' => 0, 'total_trx' => 0];

// Top 5 Menu Produk Terlaris (Matrix Analytics)
$top_produk_list = [];
$q_top = $conn->query("SELECT p.id, p.nama_produk, p.kategori, p.harga, p.gambar, 
                             COALESCE(SUM(dt.qty), 0) AS total_qty, 
                             COALESCE(SUM(dt.subtotal), 0) AS total_nominal 
                      FROM detail_transaksi dt 
                      JOIN produk p ON dt.id_menu = p.id 
                      GROUP BY dt.id_menu 
                      ORDER BY total_qty DESC 
                      LIMIT 5");
$max_qty_top = 1;
if ($q_top) {
    while ($tp = $q_top->fetch_assoc()) {
        $top_produk_list[] = $tp;
        if ($tp['total_qty'] > $max_qty_top) {
            $max_qty_top = $tp['total_qty'];
        }
    }
}

// Peringatan Stok Menipis (Stok <= Batas Minimum)
$q_alert = $conn->query("SELECT COUNT(*) AS total_menipis FROM bahan_baku WHERE stok <= batas_minimum");
$kpi_alert = $q_alert ? $q_alert->fetch_assoc() : ['total_menipis' => 0];

// Ambil Seluruh Data Bahan Baku
$bahan_baku_list = [];
$res_bahan = $conn->query("SELECT * FROM bahan_baku ORDER BY nama_bahan ASC");
if ($res_bahan) {
    while ($b = $res_bahan->fetch_assoc()) {
        $bahan_baku_list[] = $b;
    }
}
$total_bahan_count = count($bahan_baku_list);

// Ambil Daftar Bahan Kritis / Menipis untuk Widget Dashboard
$bahan_kritis_list = [];
$res_kritis = $conn->query("SELECT * FROM bahan_baku WHERE stok <= batas_minimum ORDER BY stok ASC");
if ($res_kritis) {
    while ($bk = $res_kritis->fetch_assoc()) {
        $bahan_kritis_list[] = $bk;
    }
}

// Ambil Seluruh Data Produk / Menu
$produk_list = [];
$res_produk = $conn->query("SELECT * FROM produk ORDER BY kategori ASC, nama_produk ASC");
if ($res_produk) {
    while ($p = $res_produk->fetch_assoc()) {
        $produk_list[] = $p;
    }
}

// Ambil Mapping Resep per Menu (Bill of Materials)
$resep_map = [];
$res_resep = $conn->query("
    SELECT r.id, r.id_menu, r.id_bahan, r.jumlah_dibutuhkan, b.nama_bahan, b.satuan 
    FROM resep r 
    JOIN bahan_baku b ON r.id_bahan = b.id 
    ORDER BY b.nama_bahan ASC
");
if ($res_resep) {
    while ($r = $res_resep->fetch_assoc()) {
        $resep_map[$r['id_menu']][] = $r;
    }
}

// Ambil Riwayat Mutasi Stok Terbaru (Inbound & Outbound, Limit 20)
$riwayat_stok_list = [];
$res_riwayat = $conn->query("
    SELECT rs.*, b.nama_bahan, b.satuan 
    FROM riwayat_stok rs 
    JOIN bahan_baku b ON rs.id_bahan = b.id 
    ORDER BY rs.id DESC LIMIT 20
");
if ($res_riwayat) {
    while ($rw = $res_riwayat->fetch_assoc()) {
        $riwayat_stok_list[] = $rw;
    }
}

// Ambil Riwayat Transaksi Terbaru dengan Rincian Porsi (Limit 8)
$transaksi_terbaru = [];
$res_trx = $conn->query("SELECT t.*, COUNT(dt.id) AS total_item, COALESCE(SUM(dt.qty), 0) AS total_qty 
                         FROM transaksi t 
                         LEFT JOIN detail_transaksi dt ON t.id = dt.id_transaksi 
                         GROUP BY t.id 
                         ORDER BY t.id DESC 
                         LIMIT 8");
if ($res_trx) {
    while ($t = $res_trx->fetch_assoc()) {
        $transaksi_terbaru[] = $t;
    }
}

// 4. Ambil Data Pengguna & Peran (Khusus Role Owner)
$users_list = [];
$roles_list = [];
$role_user_counts = [];
$current_user_email = "";
$current_user_foto  = "";

if (is_owner()) {
    $res_users = $conn->query("SELECT id, username, nama_lengkap, email, role, foto FROM users ORDER BY FIELD(role, 'Owner', 'Admin', 'User'), id ASC");
    if ($res_users) {
        while ($u = $res_users->fetch_assoc()) {
            $users_list[] = $u;
        }
    }

    $res_roles = $conn->query("SELECT * FROM roles ORDER BY id ASC");
    if ($res_roles) {
        while ($r = $res_roles->fetch_assoc()) {
            $roles_list[] = $r;
            $role_user_counts[$r['nama_peran']] = 0;
        }
    }

    foreach ($users_list as $u) {
        $r_name = ($u['role'] === 'Kasir') ? 'User' : $u['role'];
        if (isset($role_user_counts[$r_name])) {
            $role_user_counts[$r_name]++;
        } else {
            $role_user_counts[$r_name] = 1;
        }
    }

}

// Ambil data user aktif saat ini untuk tab Profil Saya (Owner & Admin)
if (isset($_SESSION['id_user'])) {
    $stmt_cu = $conn->prepare("SELECT email, foto, nama_lengkap FROM users WHERE id = ?");
    $stmt_cu->bind_param("i", $_SESSION['id_user']);
    $stmt_cu->execute();
    $r_cu = $stmt_cu->get_result()->fetch_assoc();
    if ($r_cu) {
        $current_user_email = $r_cu['email'] ?? '';
        $current_user_foto  = $r_cu['foto'] ?? '';
        if (!empty($r_cu['nama_lengkap'])) {
            $_SESSION['nama_lengkap'] = $r_cu['nama_lengkap'];
        }
        $_SESSION['foto'] = $current_user_foto;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin & Persediaan - Fillah Food</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <style>
        /* Lightbox Modal Foto HD Standalone */
        .hd-modal-overlay {
            position: fixed !important;
            top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important;
            width: 100vw !important; height: 100vh !important;
            background: rgba(15, 23, 42, 0.78) !important;
            backdrop-filter: blur(8px) !important;
            -webkit-backdrop-filter: blur(8px) !important;
            display: none;
            align-items: center !important;
            justify-content: center !important;
            padding: 16px !important;
            z-index: 999999 !important;
        }
        .hd-modal-overlay.active {
            display: flex !important;
        }
        .hd-modal-card {
            background: #ffffff !important;
            width: 100% !important;
            max-width: 420px !important;
            border-radius: 18px !important;
            overflow: hidden !important;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5) !important;
            display: flex !important;
            flex-direction: column !important;
            position: relative !important;
            margin: auto !important;
        }
        .hd-modal-img-wrap {
            width: 100% !important;
            height: 280px !important;
            max-height: 48vh !important;
            background: #0f172a !important;
            position: relative !important;
            overflow: hidden !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
        }
        .hd-modal-img {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
            transition: filter 0.25s ease !important;
        }

        body {
            background-color: var(--bg-canvas);
            color: var(--text-dark);
            display: flex;
            height: 100vh;
            height: 100dvh;
            overflow: hidden;
        }

        /* Sidebar Kiri */
        .sidebar {
            width: 260px;
            background-color: var(--sidebar-bg);
            color: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 24px 16px;
            flex-shrink: 0;
            z-index: 100;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(4px);
            z-index: 90;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.25s ease;
        }

        .brand-section {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .brand-brand-wrap {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-icon {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            box-shadow: 0 4px 12px rgba(234, 88, 12, 0.4);
            flex-shrink: 0;
        }

        .brand-details h2 {
            font-size: 16px;
            font-weight: 800;
            letter-spacing: -0.3px;
            color: #ffffff;
        }

        .brand-details p {
            font-size: 11px;
            color: #94a3b8;
        }

        .btn-close-sidebar {
            display: none;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: #ffffff;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            align-items: center;
            justify-content: center;
        }

        .nav-menu {
            margin-top: 20px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: #cbd5e1;
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 600;
            border-radius: 10px;
            transition: all 0.2s;
        }

        .nav-item:hover, .nav-item.active {
            background-color: var(--sidebar-hover);
            color: #ffffff;
        }

        .nav-item.active {
            background-color: var(--primary);
            box-shadow: 0 4px 12px rgba(234, 88, 12, 0.35);
        }

        /* Navigasi Dropdown / Submenu Pengaturan */
        .nav-group {
            display: flex;
            flex-direction: column;
        }

        .nav-parent {
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            user-select: none;
        }

        .nav-parent-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .nav-parent .nav-arrow {
            font-size: 10px;
            color: #94a3b8;
            transition: transform 0.2s ease;
        }

        .nav-group.open .nav-parent .nav-arrow {
            transform: rotate(180deg);
            color: #ffffff;
        }

        .nav-parent.active {
            background-color: rgba(255, 255, 255, 0.1);
            box-shadow: none;
            color: #ffffff;
        }

        .nav-submenu {
            display: none;
            flex-direction: column;
            gap: 4px;
            margin-top: 4px;
            margin-bottom: 6px;
            padding-left: 14px;
            margin-left: 18px;
            border-left: 2px solid rgba(255, 255, 255, 0.12);
        }

        .nav-group.open .nav-submenu {
            display: flex;
        }

        .nav-subitem {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 12px;
            color: #94a3b8;
            text-decoration: none;
            font-size: 12.5px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.15s;
        }

        .nav-subitem:hover {
            background-color: rgba(255, 255, 255, 0.08);
            color: #ffffff;
        }

        .nav-subitem.active {
            background: rgba(234, 88, 12, 0.22);
            color: #fdba74;
            font-weight: 700;
            box-shadow: inset 3px 0 0 #f97316;
        }

        .sidebar-footer {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding-top: 16px;
        }

        .btn-switch-mode {
            width: 100%;
            background: #1e293b;
            color: #38bdf8;
            border: 1px solid #0284c7;
            padding: 0 14px;
            border-radius: 10px;
            font-size: 12.5px;
            font-weight: 700;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 8px;
            height: 40px;
            box-sizing: border-box;
            transition: all 0.15s ease;
        }

        .btn-switch-mode:hover {
            background: #0284c7;
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(2, 132, 199, 0.3);
        }

        .btn-logout {
            width: 100%;
            background: rgba(220, 38, 38, 0.15);
            color: #f87171;
            border: 1px solid rgba(220, 38, 38, 0.3);
            padding: 0 14px;
            border-radius: 10px;
            font-size: 12.5px;
            font-weight: 700;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            height: 40px;
            box-sizing: border-box;
            transition: all 0.15s ease;
        }

        .btn-logout:hover {
            background: #dc2626;
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
        }

        .btn-logout:hover {
            background: #dc2626;
            color: #ffffff;
        }

        /* Konten Utama */
        .main-content {
            flex: 1;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            -webkit-overflow-scrolling: touch;
        }

        /* Top Header (Fixed Sticky Top) */
        .topbar {
            position: sticky;
            top: 0;
            z-index: 50;
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-bottom: 1.5px solid var(--border);
            padding: clamp(12px, 2vw, 16px) clamp(16px, 3vw, 32px);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
            gap: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn-hamburger {
            display: none;
            background: #f8fafc;
            border: 1.5px solid var(--border);
            color: var(--text-dark);
            width: 38px;
            height: 38px;
            border-radius: var(--radius-sm);
            font-size: 18px;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.15s;
            flex-shrink: 0;
        }

        .btn-hamburger:hover {
            background: #ffedd5;
            border-color: var(--primary);
            color: var(--primary);
        }

        .topbar-title h1 {
            font-size: clamp(16px, 2.5vw, 20px);
            font-weight: 800;
            color: var(--text-dark);
        }

        .topbar-title p {
            font-size: 12px;
            color: var(--text-muted);
        }

        .admin-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-shrink: 0;
        }

        .avatar {
            width: clamp(34px, 4vw, 40px);
            height: clamp(34px, 4vw, 40px);
            background: #ffedd5;
            color: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: clamp(15px, 2vw, 18px);
            font-weight: 800;
            border: 2px solid #fed7aa;
            flex-shrink: 0;
        }

        /* Content Body */
        .content-body {
            padding: clamp(14px, 2.5vw, 32px);
            display: flex;
            flex-direction: column;
            gap: clamp(16px, 2.5vw, 28px);
        }

        /* Modern Executive KPI Matrix */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 16px;
        }

        .kpi-card {
            background: #ffffff;
            border: 1.5px solid var(--border);
            border-radius: 12px;
            padding: 18px 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.2s ease;
        }

        .kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.06);
            border-color: #cbd5e1;
        }

        .kpi-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .kpi-title {
            font-size: 11px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }

        .kpi-icon-badge {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .kpi-value {
            font-size: 23px;
            font-weight: 800;
            color: var(--text-dark);
            margin: 4px 0 6px 0;
            letter-spacing: -0.5px;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .kpi-desc {
            font-size: 12px;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 500;
        }

        .kpi-card.alert-card {
            border-color: #fca5a5;
            background: #fff5f5;
        }

        .kpi-card.alert-card .kpi-value {
            color: #dc2626;
        }

        /* Modern Quick Actions Toolbar */
        .quick-action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }

        .quick-action-item {
            background: #ffffff;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            padding: 12px 14px;
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: var(--text-dark);
            cursor: pointer;
            transition: all 0.15s ease;
            box-shadow: 0 1px 2px rgba(0,0,0,0.03);
            text-align: left;
        }

        .quick-action-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.06);
            border-color: #94a3b8;
        }

        .quick-action-icon {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .quick-action-content strong {
            font-size: 12.5px;
            font-weight: 700;
            display: block;
            line-height: 1.3;
        }

        .quick-action-content span {
            font-size: 11px;
            color: var(--text-muted);
            display: block;
            margin-top: 1px;
        }

        /* Top 5 Best-Seller Matrix List */
        .top-seller-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .top-seller-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .top-seller-item:last-child {
            border-bottom: none;
        }

        .rank-badge {
            width: 24px;
            height: 24px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .rank-badge-1 { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
        .rank-badge-2 { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
        .rank-badge-3 { background: #ffedd5; color: #c2410c; border: 1px solid #fed7aa; }
        .rank-badge-other { background: #f8fafc; color: #64748b; border: 1px solid #e2e8f0; }

        .progress-bar-bg {
            height: 6px;
            background: #f1f5f9;
            border-radius: 3px;
            overflow: hidden;
            margin-top: 5px;
        }

        .progress-bar-fill {
            height: 100%;
            border-radius: 3px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            transition: width 0.3s ease;
        }

        /* Layout 2 Kolom Section Matrix */
        .section-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            align-items: start;
        }
        @media (max-width: 900px) {
            .section-grid {
                grid-template-columns: 1fr;
            }
        }

        .panel-card {
            background: var(--bg-card);
            border: 1.5px solid var(--border);
            border-radius: var(--radius-lg);
            padding: clamp(14px, 2.5vw, 22px);
            box-shadow: var(--shadow-sm);
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .panel-header h2 {
            font-size: clamp(14.5px, 2vw, 16px);
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary-sm {
            background: var(--primary);
            color: #ffffff;
            border: none;
            padding: 8px 14px;
            border-radius: var(--radius-sm);
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 10px -2px rgba(234, 88, 12, 0.4);
            transition: all 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
        }

        .btn-primary-sm:hover {
            background: var(--primary-hover);
        }

        /* Table Styling */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12.5px;
            text-align: left;
        }

        th {
            background: #f8fafc;
            color: var(--text-muted);
            font-weight: 700;
            padding: 12px 14px;
            border-bottom: 1.5px solid var(--border);
            font-size: 11px;
            text-transform: uppercase;
            white-space: nowrap;
            vertical-align: middle;
            letter-spacing: 0.5px;
        }

        td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border);
            color: var(--text-dark);
            white-space: nowrap;
            vertical-align: middle;
        }

        tr:last-child td {
            border-bottom: none;
        }

        /* Badges */
        .badge-safe {
            background: #dcfce7;
            color: var(--success);
            border: 1px solid #bbf7d0;
        }

        .badge-warning {
            background: #fef3c7;
            color: var(--warning);
            border: 1px solid #fde68a;
        }

        .badge-danger {
            background: #fee2e2;
            color: var(--danger);
            border: 1px solid #fecaca;
        }

        .badge-role-owner {
            background: #fef3c7;
            color: #b45309;
            border: 1px solid #fde68a;
        }

        .badge-role-admin {
            background: #e0f2fe;
            color: #0369a1;
            border: 1px solid #bae6fd;
        }

        .badge-role-kasir, .badge-role-user {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .badge-role-custom {
            background: #f3e8ff;
            color: #7e22ce;
            border: 1px solid #e9d5ff;
        }

        /* Form Modal Input Stok */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 999;
            padding: 12px;
        }

        .modal-box {
            background: #ffffff;
            width: min(94vw, 440px);
            max-height: 90vh;
            max-height: 90dvh;
            overflow-y: auto;
            border-radius: var(--radius-lg);
            padding: clamp(16px, 4vw, 24px);
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.25);
            -webkit-overflow-scrolling: touch;
        }

        .modal-title {
            font-size: clamp(15px, 2.5vw, 17px);
            font-weight: 800;
            margin-bottom: 14px;
        }

        .form-group {
            margin-bottom: 14px;
        }

        .form-label {
            display: block;
            font-size: 12.5px;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 16px; /* 16px prevents iOS zoom */
            outline: none;
            background: #ffffff;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(234, 88, 12, 0.15);
        }

        .modal-footer {
            display: flex;
            gap: 10px;
            margin-top: 18px;
            flex-wrap: wrap;
        }


        .img-upload-area {
            border: 2px dashed #cbd5e1;
            border-radius: 10px;
            cursor: pointer;
            transition: border-color 0.2s, background 0.2s;
            overflow: hidden;
        }

        .img-upload-area:hover {
            border-color: var(--primary);
            background: #fff7ed;
        }

        .img-upload-preview {
            width: 100%;
            height: 110px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 6px;
            overflow: hidden;
        }

        .img-upload-icon {
            font-size: 32px;
            line-height: 1;
        }

        .img-upload-hint {
            font-size: 11.5px;
            color: #64748b;
            font-weight: 600;
        }

        /* Style Komponen Modul Menu Produk & Stok */
        .item-thumb-box {
            width: 44px;
            height: 44px;
            border-radius: 8px;
            background: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            flex-shrink: 0;
        }
        .item-thumb-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .filter-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 16px;
        }
        .filter-pills {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .filter-pill {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            color: #475569;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12.5px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s;
        }
        .filter-pill.active, .filter-pill:hover {
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
        }
        .search-input-wrap {
            position: relative;
            min-width: 240px;
        }
        .search-input-wrap input {
            width: 100%;
            padding: 8px 12px 8px 34px;
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            font-size: 13px;
            outline: none;
            transition: border-color 0.15s;
        }
        .search-input-wrap input:focus {
            border-color: var(--primary);
        }
        .search-input-wrap .search-icon {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 13px;
        }
        .badge-kat-makanan {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
        }
        .badge-kat-minuman {
            background: #e0f2fe;
            color: #0284c7;
            border: 1px solid #bae6fd;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
        }
        .resep-tag-list {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            max-width: 320px;
        }
        .resep-tag {
            background: #f1f5f9;
            color: #334155;
            border: 1px solid #e2e8f0;
            font-size: 11px;
            font-weight: 600;
            padding: 2px 7px;
            border-radius: 5px;
        }
        .btn-table-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            padding: 5px 10px;
            font-size: 11.5px;
            font-weight: 600;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            color: #334155;
            cursor: pointer;
            transition: all 0.15s ease;
            height: 28px;
            line-height: 1;
            white-space: nowrap;
            box-sizing: border-box;
        }
        .btn-table-action:hover {
            background: #f8fafc;
            border-color: #94a3b8;
        }
        .btn-table-action.btn-resep {
            background: #eff6ff;
            color: #1d4ed8;
            border-color: #bfdbfe;
        }
        .btn-table-action.btn-resep:hover {
            background: #dbeafe;
            border-color: #93c5fd;
        }
        .btn-table-action.btn-inbound {
            color: #15803d;
            border-color: #bbf7d0;
            background: #f0fdf4;
        }
        .btn-table-action.btn-inbound:hover {
            background: #dcfce7;
            border-color: #86efac;
        }
        .btn-table-action.btn-outbound {
            color: #b91c1c;
            border-color: #fecaca;
            background: #fef2f2;
        }
        .btn-table-action.btn-outbound:hover {
            background: #fee2e2;
            border-color: #fca5a5;
        }
        .btn-table-action.btn-danger-sm {
            background: #fef2f2;
            color: #dc2626;
            border-color: #fecaca;
        }
        .btn-table-action.btn-danger-sm:hover {
            background: #fee2e2;
            border-color: #fca5a5;
        }

        /* ==========================================================================
           RESPONSIVE BREAKPOINTS FOR ADMIN DASHBOARD
           ========================================================================== */

        /* Tablets Landscape / Medium Desktops (769px - 1024px) */
        @media (max-width: 1024px) {
            .sidebar {
                width: 220px;
                padding: 18px 12px;
            }

            .section-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Mobile & Tablet Portrait (< 768px) */
        @media (max-width: 768px) {
            body {
                display: block;
                height: auto;
                min-height: 100vh;
                min-height: 100dvh;
                overflow-x: hidden;
                overflow-y: auto;
            }

            .main-content {
                height: auto;
                overflow: visible;
            }

            /* Off-canvas mobile sidebar drawer */
            .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                bottom: 0;
                width: 280px;
                max-width: 85vw;
                height: 100vh;
                height: 100dvh;
                transform: translateX(-100%);
                box-shadow: 0 0 35px rgba(0, 0, 0, 0.4);
            }

            body.sidebar-open .sidebar {
                transform: translateX(0);
            }

            .sidebar-overlay {
                display: block;
            }

            body.sidebar-open .sidebar-overlay {
                opacity: 1;
                pointer-events: auto;
            }

            .btn-close-sidebar {
                display: flex;
            }

            .btn-hamburger {
                display: flex;
            }

            .topbar {
                padding: 10px 14px;
            }

            .topbar-title p {
                display: none; /* Hide long subtitle on mobile */
            }

            .admin-profile-name {
                display: none; /* Show only avatar on small screens */
            }

            .section-grid {
                grid-template-columns: 1fr;
            }

            .kpi-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }

            .kpi-card {
                padding: 12px;
            }

            .kpi-value {
                font-size: 18px;
            }
        }

        /* Smallest Phones (< 440px) */
        @media (max-width: 440px) {
            .kpi-grid {
                grid-template-columns: 1fr;
            }

            .panel-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .panel-header .btn-primary-sm {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- SVG Sprite Ikon Modern (Lucide/Feather Style) -->
    <svg style="display: none;" xmlns="http://www.w3.org/2000/svg">
      <defs>
        <symbol id="icon-utensils" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"></path>
          <path d="M7 2v20"></path>
          <path d="M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"></path>
        </symbol>
        <symbol id="icon-dashboard" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect width="7" height="9" x="3" y="3" rx="1"></rect>
          <rect width="7" height="5" x="14" y="3" rx="1"></rect>
          <rect width="7" height="9" x="14" y="12" rx="1"></rect>
          <rect width="7" height="5" x="3" y="16" rx="1"></rect>
        </symbol>
        <symbol id="icon-book" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"></path>
          <path d="M6 6h10"></path>
          <path d="M6 10h10"></path>
        </symbol>
        <symbol id="icon-box" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"></path>
          <path d="m3.3 7 8.7 5 8.7-5"></path>
          <path d="M12 22V12"></path>
        </symbol>
        <symbol id="icon-settings" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path>
          <circle cx="12" cy="12" r="3"></circle>
        </symbol>
        <symbol id="icon-users" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
          <circle cx="9" cy="7" r="4"></circle>
          <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
          <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
        </symbol>
        <symbol id="icon-user" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path>
          <circle cx="12" cy="7" r="4"></circle>
        </symbol>
        <symbol id="icon-shield" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
        </symbol>
        <symbol id="icon-phone" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect width="14" height="20" x="5" y="2" rx="2" ry="2"></rect>
          <path d="M12 18h.01"></path>
        </symbol>
        <symbol id="icon-cart" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="8" cy="21" r="1"></circle>
          <circle cx="19" cy="21" r="1"></circle>
          <path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"></path>
        </symbol>
        <symbol id="icon-receipt" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"></path>
          <line x1="8" y1="8" x2="16" y2="8"></line>
          <line x1="8" y1="12" x2="16" y2="12"></line>
          <line x1="8" y1="16" x2="12" y2="16"></line>
        </symbol>
        <symbol id="icon-search" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="11" cy="11" r="8"></circle>
          <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
        </symbol>
        <symbol id="icon-edit" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"></path>
        </symbol>
        <symbol id="icon-trash" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M3 6h18"></path>
          <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
          <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
        </symbol>
        <symbol id="icon-plus" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="12" y1="5" x2="12" y2="19"></line>
          <line x1="5" y1="12" x2="19" y2="12"></line>
        </symbol>
        <symbol id="icon-inbound" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 3v12"></path>
          <path d="m8 11 4 4 4-4"></path>
          <path d="M8 5H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-4"></path>
        </symbol>
        <symbol id="icon-outbound" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 15V3"></path>
          <path d="m8 7 4-4 4 4"></path>
          <path d="M8 5H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-4"></path>
        </symbol>
        <symbol id="icon-alert" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"></circle>
          <line x1="12" y1="8" x2="12" y2="12"></line>
          <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </symbol>
        <symbol id="icon-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="20 6 9 17 4 12"></polyline>
        </symbol>
        <symbol id="icon-lock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect width="18" height="11" x="3" y="11" rx="2" ry="2"></rect>
          <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
        </symbol>
        <symbol id="icon-camera" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"></path>
          <circle cx="12" cy="13" r="3"></circle>
        </symbol>
        <symbol id="icon-save" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
          <polyline points="17 21 17 13 7 13 7 21"></polyline>
          <polyline points="7 3 7 8 15 8"></polyline>
        </symbol>
        <symbol id="icon-sparkles" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"></path>
        </symbol>
        <symbol id="icon-folder" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"></path>
        </symbol>
        <symbol id="icon-zap" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
        </symbol>
        <symbol id="icon-crown" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M11.562 3.266a.5.5 0 0 1 .876 0L15.39 8.87a1 1 0 0 0 1.516.294L21.183 5.5a.5.5 0 0 1 .798.519l-2.834 10.769a1 1 0 0 1-.969.712H5.822a1 1 0 0 1-.97-.712L2.02 6.019a.5.5 0 0 1 .797-.52l4.277 3.665a1 1 0 0 0 1.516-.294z"></path>
          <path d="M5 21h14"></path>
        </symbol>
        <symbol id="icon-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="18" y1="6" x2="6" y2="18"></line>
          <line x1="6" y1="6" x2="18" y2="18"></line>
        </symbol>
        <symbol id="icon-menu" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="4" y1="12" x2="20" y2="12"></line>
          <line x1="4" y1="6" x2="20" y2="6"></line>
          <line x1="4" y1="18" x2="20" y2="18"></line>
        </symbol>
      </defs>
    </svg>
    <!-- Overlay Backdrop untuk Mobile Sidebar Drawer -->
    <div class="sidebar-overlay" onclick="closeSidebar()"></div>

    <!-- Sidebar Kiri -->
    <aside class="sidebar">
        <div>
            <div class="brand-section">
                <div class="brand-brand-wrap">
                    <div class="brand-icon"><?php echo ui_icon("utensils", 22); ?></div>
                    <div class="brand-details">
                        <h2>Fillah Food</h2>
                        <p>Dashboard Manajemen</p>
                    </div>
                </div>
                <button type="button" class="btn-close-sidebar" onclick="closeSidebar()" title="Tutup Menu"><?php echo ui_icon("close", 16); ?></button>
            </div>

            <nav class="nav-menu">
                <a href="admin.php?tab=dashboard" data-tab="dashboard" class="nav-item <?php echo ($tab_aktif === 'dashboard') ? 'active' : ''; ?>" onclick="pindahTab('dashboard', event)">
                    <span><?php echo ui_icon("dashboard", 18); ?></span> Dashboard
                </a>
                <a href="admin.php?tab=produk" data-tab="produk" class="nav-item <?php echo ($tab_aktif === 'produk') ? 'active' : ''; ?>" onclick="pindahTab('produk', event)">
                    <span><?php echo ui_icon("book", 18); ?></span> Menu Produk
                </a>
                <a href="admin.php?tab=stok" data-tab="stok" class="nav-item <?php echo ($tab_aktif === 'stok') ? 'active' : ''; ?>" onclick="pindahTab('stok', event)">
                    <span><?php echo ui_icon("box", 18); ?></span> Stok Bahan Baku
                </a>
                <div class="nav-group <?php echo in_array($tab_aktif, ['pengguna', 'peran', 'profil']) ? 'open' : ''; ?>" id="nav-group-pengaturan">
                    <div class="nav-item nav-parent <?php echo in_array($tab_aktif, ['pengguna', 'peran', 'profil']) ? 'active' : ''; ?>" onclick="toggleSubmenu('nav-group-pengaturan')">
                        <div class="nav-parent-title">
                            <span><?php echo ui_icon("settings", 18); ?></span> Pengaturan
                        </div>
                        <span class="nav-arrow">▼</span>
                    </div>
                    <div class="nav-submenu">
                        <?php if (is_owner()): ?>
                        <a href="admin.php?tab=pengguna" data-tab="pengguna" class="nav-subitem <?php echo ($tab_aktif === 'pengguna') ? 'active' : ''; ?>" onclick="pindahTab('pengguna', event)">
                            <span><?php echo ui_icon("users", 16); ?></span> Pengelolaan Pengguna
                        </a>
                        <a href="admin.php?tab=peran" data-tab="peran" class="nav-subitem <?php echo ($tab_aktif === 'peran') ? 'active' : ''; ?>" onclick="pindahTab('peran', event)">
                            <span><?php echo ui_icon("shield", 16); ?></span> Peran
                        </a>
                        <?php endif; ?>
                        <a href="admin.php?tab=profil" data-tab="profil" class="nav-subitem <?php echo ($tab_aktif === 'profil') ? 'active' : ''; ?>" onclick="pindahTab('profil', event)">
                            <span><?php echo ui_icon("user", 16); ?></span> Profil Saya
                        </a>
                    </div>
                </div>
                <a href="menu.php" target="_blank" class="nav-item">
                    <span><?php echo ui_icon("phone", 18); ?></span> E-Menu Pelanggan
                </a>
            </nav>
        </div>

        <div class="sidebar-footer">
            <a href="kasir.php" class="btn-switch-mode">
                <?php echo ui_icon("cart", 16); ?> Buka Layar Kasir POS
            </a>
            <a href="logout.php" class="btn-logout">
                Keluar (Logout)
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Topbar -->
        <header class="topbar">
            <div class="topbar-left">
                <button type="button" class="btn-hamburger" onclick="toggleSidebar()" aria-label="Buka Menu"><?php echo ui_icon("menu", 20); ?></button>
                <div class="topbar-title">
                    <h1 id="topbar-page-title">
                        <?php 
                            if ($tab_aktif === 'pengguna') echo 'Pengelolaan Pengguna';
                            elseif ($tab_aktif === 'peran') echo 'Peran & Hak Akses (RBAC)';
                            elseif ($tab_aktif === 'profil') echo 'Profil Saya';
                            elseif ($tab_aktif === 'produk') echo 'Katalog Menu & Resep';
                            elseif ($tab_aktif === 'stok') echo 'Persediaan Bahan Baku (Stok)';
                            else echo 'Dashboard Manajemen'; 
                        ?>
                    </h1>
                    <p id="topbar-page-subtitle">
                        <?php 
                            if ($tab_aktif === 'pengguna') echo 'Daftar akun staf terdaftar, pendaftaran staf baru, dan kontrol akun';
                            elseif ($tab_aktif === 'peran') echo 'Konfigurasi pembagian hak akses fitur dan penetapan peran staf (Owner, Admin, Kasir)';
                            elseif ($tab_aktif === 'profil') echo 'Kelola identitas akun, foto profil, dan kata sandi keamanan Anda';
                            elseif ($tab_aktif === 'produk') echo 'Kelola daftar menu makanan, minuman, harga jual, dan formula resep bahan baku';
                            elseif ($tab_aktif === 'stok') echo 'Pantau sisa stok bahan baku, catat kulakan stok masuk (inbound), dan bahan rusak (outbound)';
                            else echo 'Ringkasan performa omzet bisnis, peringatan stok bahan baku, dan transaksi kasir'; 
                        ?>
                    </p>
                </div>
            </div>

            <div class="admin-profile" onclick="pindahTab('profil', event)" style="cursor: pointer;" title="Buka Pengaturan Profil Saya">
                <div class="avatar" style="<?php echo (is_owner()) ? 'background: linear-gradient(135deg, #f59e0b, #d97706);' : 'background: linear-gradient(135deg, #0284c7, #0369a1);'; ?> overflow: hidden; padding: 0;">
                    <?php if (!empty($_SESSION['foto']) && file_exists(__DIR__ . '/' . $_SESSION['foto'])): ?>
                        <img src="<?php echo htmlspecialchars($_SESSION['foto']); ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <?php 
                            $nama_disp = !empty($_SESSION['nama_lengkap']) ? $_SESSION['nama_lengkap'] : $_SESSION['username'];
                            echo strtoupper(substr($nama_disp, 0, 1)); 
                        ?>
                    <?php endif; ?>
                </div>
                <div class="admin-profile-name">
                    <div style="font-size: 13.5px; font-weight: 700;">
                        <?php echo htmlspecialchars($nama_disp); ?>
                    </div>
                    <div style="font-size: 11px; margin-top: 2px;">
                        <?php if (is_owner()): ?>
                            <span class="badge badge-role-owner" style="font-size: 10px; padding: 2px 8px;"><?php echo ui_icon("crown", 12); ?> Owner (Pemilik Usaha)</span>
                        <?php else: ?>
                            <span class="badge badge-role-admin" style="font-size: 10px; padding: 2px 8px;"><?php echo ui_icon("settings", 12); ?> Admin (Staf Persediaan)</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </header>

        <!-- Body Content -->
        <div class="content-body">

            <?php if (!empty($pesan_sukses)): ?>
                <div class="alert-box alert-success"><?php echo ui_icon("check", 16); ?> <?php echo htmlspecialchars($pesan_sukses); ?></div>
            <?php endif; ?>

            <?php if (!empty($pesan_error)): ?>
                <div class="alert-box alert-danger"><?php echo ui_icon("alert", 16); ?> <?php echo htmlspecialchars($pesan_error); ?></div>
            <?php endif; ?>

            <!-- TAB VIEW 1: DASHBOARD MATRIX (Ringkasan Eksekutif, Stok Bahan & Performa Bisnis) -->
            <div id="view-dashboard" class="tab-view" style="<?php echo ($tab_aktif === 'dashboard') ? '' : 'display: none;'; ?>">

                <?php
                    // Helper nama hari & bulan bahasa Indonesia
                    $hari_indo = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
                    $bulan_indo = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
                    $tgl_sekarang_indo = $hari_indo[date('l')] . ', ' . date('j') . ' ' . $bulan_indo[(int)date('n')] . ' ' . date('Y');
                ?>

                <!-- 1. Executive Welcome & Shift Bar -->
                <div style="background: linear-gradient(135deg, #1e293b, #0f172a); color: #ffffff; border-radius: 12px; padding: 16px 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px; box-shadow: 0 4px 16px rgba(15, 23, 42, 0.12); margin-bottom: 20px;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div style="width: 44px; height: 44px; border-radius: 10px; background: rgba(234, 88, 12, 0.2); border: 1.5px solid rgba(234, 88, 12, 0.4); display: flex; align-items: center; justify-content: center; color: #fb923c; flex-shrink: 0;">
                            <?php echo ui_icon("utensils", 22); ?>
                        </div>
                        <div>
                            <div style="font-weight: 800; font-size: 15px; letter-spacing: -0.3px; display: flex; align-items: center; gap: 8px;">
                                <span>Operasional Fillah Food POS</span>
                                <span style="font-size: 10.5px; background: #15803d; color: #dcfce7; padding: 2px 8px; border-radius: 20px; font-weight: 700; letter-spacing: 0.3px;">KASIR SIAP</span>
                            </div>
                            <div style="font-size: 12px; color: #94a3b8; margin-top: 2px;">
                                <?php echo $tgl_sekarang_indo; ?> &bull; Masuk sebagai <strong><?php echo htmlspecialchars($_SESSION['nama_lengkap'] ?? 'Pemilik'); ?></strong>
                            </div>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <span style="font-size: 12px; background: rgba(255,255,255,0.08); padding: 6px 12px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.12);">
                            Akumulasi Bulan Ini: <strong style="color: #38bdf8;">Rp <?php echo number_format($kpi_month['omzet_month'], 0, ',', '.'); ?></strong> (<?php echo $kpi_month['trx_month']; ?> Trx)
                        </span>
                        <a href="kasir.php" class="btn-primary-sm" style="display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; font-size: 12px; font-weight: 700;">
                            <?php echo ui_icon("cart", 14); ?> <span>Buka Kasir</span>
                        </a>
                    </div>
                </div>

                <!-- 2. The 4-Card Executive KPI Matrix -->
                <div class="kpi-grid" style="margin-bottom: 20px;">
                    <!-- KPI 1: Omzet Hari Ini -->
                    <div class="kpi-card">
                        <div class="kpi-card-header">
                            <span class="kpi-title">Omzet Hari Ini</span>
                            <div class="kpi-icon-badge" style="background: #fff7ed; color: #ea580c; border: 1px solid #ffedd5;">
                                <?php echo ui_icon("receipt", 18); ?>
                            </div>
                        </div>
                        <div class="kpi-value" style="color: #ea580c;">
                            Rp <?php echo number_format($kpi_today['omzet_today'], 0, ',', '.'); ?>
                        </div>
                        <div class="kpi-desc">
                            <span style="display:inline-block; width:7px; height:7px; border-radius:50%; background: <?php echo ($kpi_today['trx_today'] > 0) ? '#16a34a' : '#94a3b8'; ?>;"></span>
                            <span><strong><?php echo $kpi_today['trx_today']; ?></strong> transaksi berhasil hari ini</span>
                        </div>
                    </div>

                    <!-- KPI 2: Omzet Bulan Ini -->
                    <div class="kpi-card">
                        <div class="kpi-card-header">
                            <span class="kpi-title">Omzet Bulan Ini</span>
                            <div class="kpi-icon-badge" style="background: #f0f9ff; color: #0284c7; border: 1px solid #e0f2fe;">
                                <?php echo ui_icon("sparkles", 18); ?>
                            </div>
                        </div>
                        <div class="kpi-value" style="color: #0284c7;">
                            Rp <?php echo number_format($kpi_month['omzet_month'], 0, ',', '.'); ?>
                        </div>
                        <div class="kpi-desc">
                            <span><strong><?php echo $total_porsi_bulan; ?></strong> porsi terjual dari <strong><?php echo $kpi_month['trx_month']; ?></strong> nota</span>
                        </div>
                    </div>

                    <!-- KPI 3: Menu Paling Laris -->
                    <div class="kpi-card">
                        <div class="kpi-card-header">
                            <span class="kpi-title">Menu Terlaris</span>
                            <div class="kpi-icon-badge" style="background: #f5f3ff; color: #7c3aed; border: 1px solid #ede9fe;">
                                <?php echo ui_icon("crown", 18); ?>
                            </div>
                        </div>
                        <div class="kpi-value" style="color: #7c3aed; font-size: 17px; line-height: 1.3;" title="<?php echo !empty($top_produk_list) ? htmlspecialchars($top_produk_list[0]['nama_produk']) : 'Belum Ada Penjualan'; ?>">
                            <?php echo !empty($top_produk_list) ? htmlspecialchars($top_produk_list[0]['nama_produk']) : 'Belum Ada Penjualan'; ?>
                        </div>
                        <div class="kpi-desc">
                            <?php if (!empty($top_produk_list)): ?>
                                <span>Terjual <strong><?php echo $top_produk_list[0]['total_qty']; ?> porsi</strong> (Rp <?php echo number_format($top_produk_list[0]['total_nominal'], 0, ',', '.'); ?>)</span>
                            <?php else: ?>
                                <span>Menunggu transaksi kasir pertama</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- KPI 4: Kesehatan Persediaan -->
                    <div class="kpi-card <?php echo ($kpi_alert['total_menipis'] > 0) ? 'alert-card' : ''; ?>">
                        <div class="kpi-card-header">
                            <span class="kpi-title">Kesehatan Persediaan</span>
                            <div class="kpi-icon-badge" style="<?php echo ($kpi_alert['total_menipis'] > 0) ? 'background: #fef2f2; color: #dc2626; border: 1px solid #fecaca;' : 'background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0;'; ?>">
                                <?php echo ($kpi_alert['total_menipis'] > 0) ? ui_icon("alert", 18) : ui_icon("check", 18); ?>
                            </div>
                        </div>
                        <div class="kpi-value" style="<?php echo ($kpi_alert['total_menipis'] > 0) ? 'color: #dc2626;' : 'color: #16a34a;'; ?>">
                            <?php echo ($kpi_alert['total_menipis'] > 0) ? $kpi_alert['total_menipis'] . ' Bahan Kritis' : '100% Stok Aman'; ?>
                        </div>
                        <div class="kpi-desc">
                            <span><strong><?php echo $total_bahan_count - $kpi_alert['total_menipis']; ?></strong> dari <strong><?php echo $total_bahan_count; ?></strong> bahan di atas ambang aman</span>
                        </div>
                    </div>
                </div>

                <!-- 3. Modern Quick Actions Toolbar -->
                <div class="quick-action-grid">
                    <a href="kasir.php" class="quick-action-item" style="border-color: #fdba74; background: linear-gradient(to right, #ffffff, #fff7ed);">
                        <div class="quick-action-icon" style="background: #ea580c; color: #ffffff;">
                            <?php echo ui_icon("cart", 18); ?>
                        </div>
                        <div class="quick-action-content">
                            <strong style="color: #c2410c;">Layar Kasir POS</strong>
                            <span>Input pesanan & cetak nota</span>
                        </div>
                    </a>

                    <div class="quick-action-item" onclick="bukaModalStokMasuk()" style="border-color: #bbf7d0; background: linear-gradient(to right, #ffffff, #f0fdf4);">
                        <div class="quick-action-icon" style="background: #16a34a; color: #ffffff;">
                            <?php echo ui_icon("inbound", 18); ?>
                        </div>
                        <div class="quick-action-content">
                            <strong style="color: #15803d;">Catat Stok Masuk</strong>
                            <span>Kulakan belanja bahan dapur</span>
                        </div>
                    </div>

                    <div class="quick-action-item" onclick="pindahTab('produk', event)" style="border-color: #bae6fd; background: linear-gradient(to right, #ffffff, #f0f9ff);">
                        <div class="quick-action-icon" style="background: #0284c7; color: #ffffff;">
                            <?php echo ui_icon("book", 18); ?>
                        </div>
                        <div class="quick-action-content">
                            <strong style="color: #0369a1;">Katalog Menu & Resep</strong>
                            <span>Formula bahan & harga jual</span>
                        </div>
                    </div>

                    <a href="menu.php" target="_blank" class="quick-action-item" style="border-color: #f0abfc; background: linear-gradient(to right, #ffffff, #fdf4ff);">
                        <div class="quick-action-icon" style="background: #9333ea; color: #ffffff;">
                            <?php echo ui_icon("phone", 18); ?>
                        </div>
                        <div class="quick-action-content">
                            <strong style="color: #7e22ce;">E-Menu Pelanggan</strong>
                            <span>Buka menu digital via QR</span>
                        </div>
                    </a>
                </div>

                <!-- 4. Executive Analytics Matrix (2 Kolom Seimbang) -->
                <div class="section-grid">
                    
                    <!-- Kolom Kiri: Status Stok Dapur & Log Mutasi -->
                    <div style="display: flex; flex-direction: column; gap: 20px;">
                        
                        <!-- Panel Stok Dapur -->
                        <?php if (!empty($bahan_kritis_list)): ?>
                            <div class="panel-card" style="border-left: 4px solid #dc2626;">
                                <div class="panel-header">
                                    <div>
                                        <h2><span><?php echo ui_icon("alert", 20); ?></span> Peringatan Bahan Menipis / Kritis</h2>
                                        <p style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">Segera belanja bahan di bawah ambang minimum berikut</p>
                                    </div>
                                    <button class="btn-primary-sm" onclick="pindahTab('stok', event)">Buka Stok &rarr;</button>
                                </div>
                                <div class="table-responsive">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>Bahan Baku</th>
                                                <th>Sisa Stok</th>
                                                <th>Batas Min</th>
                                                <th>Status</th>
                                                <th style="text-align: right;">Aksi</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($bahan_kritis_list as $bk): ?>
                                                <tr>
                                                    <td><strong><?php echo htmlspecialchars($bk['nama_bahan']); ?></strong></td>
                                                    <td style="color: #dc2626; font-weight: 800;">
                                                        <?php echo rtrim(rtrim(number_format($bk['stok'], 2, '.', ''), '0'), '.'); ?> <?php echo htmlspecialchars($bk['satuan']); ?>
                                                    </td>
                                                    <td style="color: var(--text-muted);">
                                                        <?php echo rtrim(rtrim(number_format($bk['batas_minimum'], 2, '.', ''), '0'), '.'); ?> <?php echo htmlspecialchars($bk['satuan']); ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge <?php echo ($bk['stok'] <= 0) ? 'badge-danger' : 'badge-warning'; ?>">
                                                            <?php echo ($bk['stok'] <= 0) ? 'Habis' : 'Menipis'; ?>
                                                        </span>
                                                    </td>
                                                    <td style="text-align: right; white-space: nowrap;">
                                                        <button class="btn-table-action btn-inbound" onclick="isiStokCepat(<?php echo $bk['id']; ?>, '<?php echo addslashes($bk['nama_bahan']); ?>', '<?php echo $bk['satuan']; ?>')" title="Catat Kulakan Stok Masuk">
                                                            <?php echo ui_icon("plus", 12); ?> Belanja
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="panel-card" style="border-left: 4px solid #16a34a;">
                                <div class="panel-header">
                                    <div>
                                        <h2><span><?php echo ui_icon("box", 20); ?></span> Status Persediaan Bahan Baku</h2>
                                        <p style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">Monitoring ketersediaan fisik bahan baku dapur</p>
                                    </div>
                                    <button class="btn-primary-sm" onclick="pindahTab('stok', event)">Kelola Bahan &rarr;</button>
                                </div>
                                <div style="padding: 14px 16px; background: #f0fdf4; border-radius: 10px; border: 1.5px solid #bbf7d0; display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                                    <span style="display:inline-flex; align-items:center; justify-content:center; width:36px; height:36px; border-radius:50%; background:#dcfce7; color:#16a34a; flex-shrink:0;">
                                        <?php echo ui_icon("check", 20); ?>
                                    </span>
                                    <div>
                                        <strong style="color: #15803d; font-size: 13.5px;">Semua Bahan Baku Aman (100%)</strong>
                                        <p style="font-size: 12px; color: #166534; margin: 2px 0 0 0;">Total <?php echo $total_bahan_count; ?> jenis bahan baku tersedia di atas ambang batas minimum.</p>
                                    </div>
                                </div>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 8px;">
                                    <?php 
                                        $preview_bahan = array_slice($bahan_baku_list, 0, 6);
                                        foreach ($preview_bahan as $pb): 
                                    ?>
                                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px; font-size: 11.5px;">
                                            <div style="font-weight: 700; color: var(--text-dark); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($pb['nama_bahan']); ?>">
                                                <?php echo htmlspecialchars($pb['nama_bahan']); ?>
                                            </div>
                                            <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 4px;">
                                                <span style="font-weight: 800; color: #15803d;">
                                                    <?php echo rtrim(rtrim(number_format($pb['stok'], 2, '.', ''), '0'), '.'); ?> <?php echo htmlspecialchars($pb['satuan']); ?>
                                                </span>
                                                <span style="font-size: 9.5px; color: #16a34a; background: #dcfce7; padding: 1px 5px; border-radius: 4px; font-weight: 700;">Aman</span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Panel Menu Produk Terlaris & Kontribusi -->
                        <div class="panel-card">
                            <div class="panel-header">
                                <div>
                                    <h2><span><?php echo ui_icon("sparkles", 20); ?></span> 5 Menu Terlaris (Kontribusi Omzet)</h2>
                                    <p style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">Menu paling digemari pelanggan berdasarkan total porsi terjual</p>
                                </div>
                                <button class="btn-primary-sm" onclick="pindahTab('produk', event)">Lihat Katalog &rarr;</button>
                            </div>
                            
                            <?php if (empty($top_produk_list)): ?>
                                <div style="text-align: center; color: var(--text-muted); padding: 24px; font-size: 12.5px;">
                                    Belum ada transaksi menu yang tercatat di sistem.
                                </div>
                            <?php else: ?>
                                <div class="top-seller-list">
                                    <?php foreach ($top_produk_list as $idx => $tp): 
                                        $rank = $idx + 1;
                                        $rank_class = ($rank === 1) ? 'rank-badge-1' : (($rank === 2) ? 'rank-badge-2' : (($rank === 3) ? 'rank-badge-3' : 'rank-badge-other'));
                                        $pct = ($max_qty_top > 0) ? round(($tp['total_qty'] / $max_qty_top) * 100) : 0;
                                    ?>
                                        <div class="top-seller-item">
                                            <span class="rank-badge <?php echo $rank_class; ?>">#<?php echo $rank; ?></span>
                                            <div style="flex: 1; min-width: 0;">
                                                <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px;">
                                                    <span style="font-weight: 700; font-size: 13px; color: var(--text-dark); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($tp['nama_produk']); ?>">
                                                        <?php echo htmlspecialchars($tp['nama_produk']); ?>
                                                    </span>
                                                    <span style="font-weight: 800; font-size: 12.5px; color: var(--primary); white-space: nowrap;">
                                                        Rp <?php echo number_format($tp['total_nominal'], 0, ',', '.'); ?>
                                                    </span>
                                                </div>
                                                <div style="display: flex; align-items: center; justify-content: space-between; font-size: 11px; color: var(--text-muted); margin-top: 2px;">
                                                    <span>Kategori: <?php echo htmlspecialchars($tp['kategori']); ?></span>
                                                    <span><strong><?php echo $tp['total_qty']; ?></strong> porsi terjual</span>
                                                </div>
                                                <div class="progress-bar-bg">
                                                    <div class="progress-bar-fill" style="width: <?php echo $pct; ?>%;"></div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                    </div>

                    <!-- Kolom Kanan: Live Feed Transaksi Kasir Terakhir -->
                    <div class="panel-card" id="riwayat-transaksi">
                        <div class="panel-header">
                            <div>
                                <h2><span><?php echo ui_icon("receipt", 20); ?></span> Transaksi Kasir Terakhir</h2>
                                <p style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">Aktivitas penjualan kasir POS yang berhasil diproses</p>
                            </div>
                            <a href="kasir.php" class="btn-primary-sm" style="display: inline-flex; align-items: center; gap: 6px;">
                                <?php echo ui_icon("plus", 12); ?> Transaksi Baru
                            </a>
                        </div>

                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>No. Nota</th>
                                        <th>Waktu</th>
                                        <th>Porsi</th>
                                        <th style="text-align: right;">Total Tagihan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($transaksi_terbaru)): ?>
                                        <tr>
                                            <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 24px;">
                                                Belum ada transaksi penjualan kasir yang tercatat.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($transaksi_terbaru as $trx): ?>
                                            <tr>
                                                <td>
                                                    <strong style="color: #0369a1;">#TRX-<?php echo str_pad($trx['id'], 4, "0", STR_PAD_LEFT); ?></strong>
                                                </td>
                                                <td style="font-size: 11.5px; color: var(--text-muted);">
                                                    <?php echo date('d/m/Y H:i', strtotime($trx['tanggal_transaksi'])); ?>
                                                </td>
                                                <td>
                                                    <span class="badge" style="background: #f1f5f9; color: var(--text-dark); border: 1px solid #e2e8f0; font-size: 11px;">
                                                        <?php echo $trx['total_qty']; ?> Porsi
                                                    </span>
                                                </td>
                                                <td style="font-weight: 800; color: var(--primary); text-align: right;">
                                                    Rp <?php echo number_format($trx['total_pembayaran'], 0, ',', '.'); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div> <!-- /section-grid -->
            </div> <!-- /view-dashboard -->

        <!-- TAB VIEW 2: MENU PRODUK & RESEP -->
        <div id="view-produk" class="tab-view" style="<?php echo ($tab_aktif === 'produk') ? '' : 'display: none;'; ?>">
            <div class="panel-card">
                <div class="panel-header">
                    <div>
                        <h2><span><?php echo ui_icon("book", 20); ?></span> Katalog Menu Makanan & Minuman</h2>
                        <span style="font-size: 12px; color: var(--text-muted);">Kelola harga jual, foto produk, dan formula resep bahan baku</span>
                    </div>
                    <button class="btn-primary-sm" onclick="bukaModalProdukBaru()" style="display:inline-flex; align-items:center; gap:6px;"><?php echo ui_icon("plus", 14); ?> <span>Tambah Menu Baru</span></button>
                </div>

                <div class="filter-bar">
                    <div class="filter-pills">
                        <button type="button" class="filter-pill active" onclick="filterKategoriProduk('all', this)">Semua (<?php echo count($produk_list); ?>)</button>
                        <button type="button" class="filter-pill" onclick="filterKategoriProduk('Makanan', this)">Makanan</button>
                        <button type="button" class="filter-pill" onclick="filterKategoriProduk('Minuman', this)">Minuman</button>
                    </div>
                    <div class="search-input-wrap">
                        <span class="search-icon"><?php echo ui_icon("search", 16); ?></span>
                        <input type="text" id="cari-menu-produk" placeholder="Cari nama menu..." onkeyup="filterMenuProduk()">
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="tabel-menu-produk">
                        <thead>
                            <tr>
                                <th style="width: 50px;">Foto</th>
                                <th>Nama Menu</th>
                                <th>Kategori</th>
                                <th>Harga Jual</th>
                                <th>Formula Resep (Bahan Baku)</th>
                                <th style="text-align: right;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($produk_list)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 30px;">
                                        Belum ada menu produk terdaftar. Klik <strong>"+ Tambah Menu Baru"</strong> untuk mulai mendaftarkan.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($produk_list as $p): ?>
                                    <tr class="baris-produk" data-kategori="<?php echo htmlspecialchars($p['kategori']); ?>">
                                        <td>
                                            <?php
                                                $thumb_src = (!empty($p['gambar']) && file_exists(__DIR__ . '/' . $p['gambar'])) ? $p['gambar'] : '';
                                                $hd_src = $thumb_src;
                                                if (!empty($thumb_src)) {
                                                    $info = pathinfo($thumb_src);
                                                    $dir = (!empty($info['dirname']) && $info['dirname'] !== '.') ? $info['dirname'] . '/' : '';
                                                    $candidate_hd = $dir . $info['filename'] . '_hd.' . $info['extension'];
                                                    if (file_exists(__DIR__ . '/' . $candidate_hd)) {
                                                        $hd_src = $candidate_hd;
                                                    }
                                                }
                                            ?>
                                            <div class="item-thumb-box"
                                                 <?php if (!empty($thumb_src)): ?>
                                                     onclick="bukaModalFotoHD(this)"
                                                     data-thumb="<?php echo htmlspecialchars($thumb_src); ?>"
                                                     data-hd="<?php echo htmlspecialchars($hd_src); ?>"
                                                     data-nama="<?php echo htmlspecialchars($p['nama_produk']); ?>"
                                                     data-harga="Rp <?php echo number_format($p['harga'], 0, ',', '.'); ?>"
                                                     data-cat="<?php echo htmlspecialchars($p['kategori']); ?>"
                                                     title="Klik untuk memperbesar foto HD"
                                                     style="cursor: pointer; position: relative;"
                                                 <?php endif; ?>>
                                                <?php if (!empty($thumb_src)): ?>
                                                    <img src="<?php echo htmlspecialchars($thumb_src); ?>" alt="<?php echo htmlspecialchars($p['nama_produk']); ?>" loading="lazy">
                                                <?php else: ?>
                                                    <span><?php echo ($p['kategori'] === 'Minuman') ? ui_icon("utensils", 24) : ui_icon("utensils", 24); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <strong class="nama-produk-teks" style="font-size: 13.5px;"><?php echo htmlspecialchars($p['nama_produk']); ?></strong>
                                            <div style="font-size: 11px; color: var(--text-muted);">ID Menu: #PRD-<?php echo str_pad($p['id'], 3, '0', STR_PAD_LEFT); ?></div>
                                        </td>
                                        <td>
                                            <span class="<?php echo ($p['kategori'] === 'Minuman') ? 'badge-kat-minuman' : 'badge-kat-makanan'; ?>">
                                                <?php echo htmlspecialchars($p['kategori']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span style="font-weight: 800; color: var(--primary); font-size: 14px;">
                                                Rp <?php echo number_format($p['harga'], 0, ',', '.'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($resep_map[$p['id']])): ?>
                                                <div class="resep-tag-list">
                                                    <?php foreach ($resep_map[$p['id']] as $item_r): ?>
                                                        <span class="resep-tag">
                                                            <?php echo htmlspecialchars($item_r['nama_bahan']); ?>: 
                                                            <strong><?php echo rtrim(rtrim(number_format($item_r['jumlah_dibutuhkan'], 2, '.', ''), '0'), '.'); ?> <?php echo htmlspecialchars($item_r['satuan']); ?></strong>
                                                        </span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <span style="font-size: 11.5px; color: #94a3b8; font-style: italic; display:inline-flex; align-items:center; gap:4px;"><?php echo ui_icon("alert", 13); ?> Belum diatur resep</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: right; white-space: nowrap;">
                                            <div style="display: inline-flex; align-items: center; gap: 5px; justify-content: flex-end;">
                                                <button type="button" class="btn-table-action btn-resep" onclick="bukaModalAturResep(<?php echo $p['id']; ?>, '<?php echo addslashes($p['nama_produk']); ?>')" title="Formula Resep Bahan">
                                                    <?php echo ui_icon("settings", 12); ?> Atur Resep
                                                </button>
                                                <button type="button" class="btn-table-action" onclick="bukaModalEditProduk(<?php echo htmlspecialchars(json_encode($p)); ?>)" title="Ubah Data Menu">
                                                    <?php echo ui_icon("edit", 12); ?> Edit
                                                </button>
                                                <form method="POST" action="" style="display: inline-flex; margin: 0;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus menu \'<?php echo addslashes($p['nama_produk']); ?>\'?');">
                                                    <input type="hidden" name="id_produk" value="<?php echo $p['id']; ?>">
                                                    <button type="submit" name="hapus_produk" class="btn-table-action btn-danger-sm" title="Hapus Menu">
                                                        <?php echo ui_icon("trash", 12); ?> Hapus
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB VIEW 3: STOK BAHAN BAKU (INBOUND & OUTBOUND) -->
        <div id="view-stok" class="tab-view" style="<?php echo ($tab_aktif === 'stok') ? '' : 'display: none;'; ?>">
            <div class="panel-card">
                <div class="panel-header">
                    <div>
                        <h2><span><?php echo ui_icon("box", 20); ?></span> Persediaan Bahan Baku (Gudang & Dapur)</h2>
                        <span style="font-size: 12px; color: var(--text-muted);">Pantau saldo fisik bahan, catat kulakan masuk, dan bahan rusak/basi</span>
                    </div>
                    <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                        <button class="btn-primary-sm" style="background: #16a34a; display:inline-flex; align-items:center; gap:6px;" onclick="bukaModalStokMasuk()">
                            <?php echo ui_icon("inbound", 14); ?> <span>Catat Stok Masuk</span>
                        </button>
                        <button class="btn-primary-sm" style="background: #dc2626; display:inline-flex; align-items:center; gap:6px;" onclick="bukaModalStokKeluar()">
                            <?php echo ui_icon("outbound", 14); ?> <span>Catat Rusak / Basi</span>
                        </button>
                        <button class="btn-primary-sm" style="background: #334155; display:inline-flex; align-items:center; gap:6px;" onclick="bukaModalBahanBaru()">
                            <?php echo ui_icon("plus", 14); ?> <span>Tambah Bahan Baru</span>
                        </button>
                    </div>
                </div>

                <div class="filter-bar">
                    <div style="font-size: 13px; color: var(--text-muted);">
                        Menampilkan <strong><?php echo count($bahan_baku_list); ?></strong> jenis bahan baku terdaftar
                    </div>
                    <div class="search-input-wrap">
                        <span class="search-icon"><?php echo ui_icon("search", 16); ?></span>
                        <input type="text" id="cari-bahan-baku" placeholder="Cari bahan baku..." onkeyup="filterBahanBaku()">
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="tabel-bahan-baku">
                        <thead>
                            <tr>
                                <th style="width: 50px;">Foto</th>
                                <th>Nama Bahan</th>
                                <th>Stok Saat Ini</th>
                                <th>Batas Minimum</th>
                                <th>Status Stok</th>
                                <th style="text-align: right;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bahan_baku_list as $b): ?>
                                <?php 
                                    $stok = floatval($b['stok']);
                                    $min = floatval($b['batas_minimum']);
                                    if ($stok <= 0) {
                                        $badge_class = "badge-danger";
                                        $badge_label = "Habis / Kritis";
                                    } elseif ($stok <= $min) {
                                        $badge_class = "badge-warning";
                                        $badge_label = "Menipis";
                                    } else {
                                        $badge_class = "badge-safe";
                                        $badge_label = "Stok Aman";
                                    }
                                ?>
                                <tr class="baris-bahan">
                                    <td>
                                        <div class="item-thumb-box">
                                            <?php if (!empty($b['gambar']) && file_exists(__DIR__ . '/' . $b['gambar'])): ?>
                                                <img src="<?php echo htmlspecialchars($b['gambar']); ?>" alt="<?php echo htmlspecialchars($b['nama_bahan']); ?>">
                                            <?php else: ?>
                                                <span><?php echo ui_icon("box", 24); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <strong class="nama-bahan-teks" style="font-size: 13.5px;"><?php echo htmlspecialchars($b['nama_bahan']); ?></strong>
                                        <div style="font-size: 11px; color: var(--text-muted);">Satuan: <?php echo htmlspecialchars($b['satuan']); ?></div>
                                    </td>
                                    <td>
                                        <span style="font-size: 15px; font-weight: 800; color: <?php echo ($stok <= $min) ? 'var(--accent)' : 'inherit'; ?>;">
                                            <?php echo rtrim(rtrim(number_format($stok, 2, '.', ''), '0'), '.'); ?>
                                        </span> 
                                        <span style="font-size: 12px; color: var(--text-muted);"><?php echo htmlspecialchars($b['satuan']); ?></span>
                                    </td>
                                    <td style="color: var(--text-muted);">
                                        <?php echo rtrim(rtrim(number_format($min, 2, '.', ''), '0'), '.'); ?> <?php echo htmlspecialchars($b['satuan']); ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $badge_class; ?>"><?php echo $badge_label; ?></span>
                                    </td>
                                    <td style="text-align: right; white-space: nowrap;">
                                        <div style="display: inline-flex; align-items: center; gap: 5px; justify-content: flex-end;">
                                            <button type="button" class="btn-table-action btn-inbound" onclick="isiStokCepat(<?php echo $b['id']; ?>, '<?php echo addslashes($b['nama_bahan']); ?>', '<?php echo $b['satuan']; ?>')" title="Catat Kulakan Stok Masuk">
                                                <?php echo ui_icon("plus", 12); ?> Masuk
                                            </button>
                                            <button type="button" class="btn-table-action btn-outbound" onclick="catatKeluarCepat(<?php echo $b['id']; ?>, '<?php echo addslashes($b['nama_bahan']); ?>', '<?php echo $b['satuan']; ?>')" title="Catat Bahan Rusak atau Basi">
                                                <?php echo ui_icon("outbound", 12); ?> Rusak
                                            </button>
                                            <button type="button" class="btn-table-action" onclick="bukaModalEditBahan(<?php echo htmlspecialchars(json_encode($b)); ?>)" title="Ubah Data Bahan">
                                                <?php echo ui_icon("edit", 12); ?> Edit
                                            </button>
                                            <form method="POST" action="" style="display: inline-flex; margin: 0;" onsubmit="return confirm('Hapus bahan baku \'<?php echo addslashes($b['nama_bahan']); ?>\'? Formula resep yang menggunakannya juga akan terhapus.');">
                                                <input type="hidden" name="id_bahan" value="<?php echo $b['id']; ?>">
                                                <button type="submit" name="hapus_bahan" class="btn-table-action btn-danger-sm" title="Hapus Bahan Baku">
                                                    <?php echo ui_icon("trash", 12); ?> Hapus
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Tabel Riwayat Mutasi Stok -->
            <div class="panel-card" style="margin-top: 24px;">
                <div class="panel-header">
                    <div>
                        <h2><span><?php echo ui_icon("box", 20); ?></span> Riwayat Mutasi Stok Masuk & Keluar</h2>
                        <span style="font-size: 12px; color: var(--text-muted);">20 log aktivitas kulakan belanja dan pengeluaran bahan terbaru</span>
                    </div>
                </div>

                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Waktu Mutasi</th>
                                <th>Bahan Baku</th>
                                <th>Jenis Mutasi</th>
                                <th>Jumlah</th>
                                <th>Keterangan / Catatan</th>
                                <th>Dicatat Oleh</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($riwayat_stok_list)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 24px;">
                                        Belum ada catatan mutasi stok. Catat stok masuk atau bahan rusak untuk mulai melacak riwayat persediaan.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($riwayat_stok_list as $rw): ?>
                                    <tr>
                                        <td style="font-size: 11.5px; color: var(--text-muted);">
                                            <?php echo date('d/m/Y H:i', strtotime($rw['waktu'])); ?>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($rw['nama_bahan']); ?></strong>
                                        </td>
                                        <td>
                                            <?php if ($rw['tipe'] === 'masuk'): ?>
                                                <span class="badge" style="background:#dcfce7; color:#15803d; border:1px solid #bbf7d0; display:inline-flex; align-items:center; gap:4px;"><?php echo ui_icon("inbound", 12); ?> Stok Masuk</span>
                                            <?php elseif ($rw['tipe'] === 'keluar_rusak'): ?>
                                                <span class="badge" style="background:#fee2e2; color:#b91c1c; border:1px solid #fecaca; display:inline-flex; align-items:center; gap:4px;"><?php echo ui_icon("outbound", 12); ?> Rusak / Basi</span>
                                            <?php else: ?>
                                                <span class="badge" style="background:#e0f2fe; color:#0369a1; border:1px solid #bae6fd; display:inline-flex; align-items:center; gap:4px;"><?php echo ui_icon("cart", 12); ?> Terjual Kasir</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($rw['tipe'] === 'masuk'): ?>
                                                <span style="color: #15803d; font-weight: 800; font-size: 13.5px;">
                                                    +<?php echo rtrim(rtrim(number_format($rw['jumlah'], 2, '.', ''), '0'), '.'); ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="color: #b91c1c; font-weight: 800; font-size: 13.5px;">
                                                    -<?php echo rtrim(rtrim(number_format($rw['jumlah'], 2, '.', ''), '0'), '.'); ?>
                                                </span>
                                            <?php endif; ?>
                                            <span style="font-size: 11.5px; color: var(--text-muted);"><?php echo htmlspecialchars($rw['satuan']); ?></span>
                                        </td>
                                        <td style="font-size: 12.5px;">
                                            <?php echo htmlspecialchars($rw['keterangan'] ?? '-'); ?>
                                        </td>
                                        <td style="font-size: 11.5px; color: var(--text-muted);">
                                            <?php echo htmlspecialchars($rw['dicatat_oleh'] ?? 'Sistem'); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB VIEW: KELOLA PENGGUNA (Khusus Role Owner) -->
        <?php if (is_owner()): ?>
        <div id="view-pengguna" class="tab-view" style="<?php echo ($tab_aktif === 'pengguna') ? '' : 'display: none;'; ?>">
            <div class="panel-card" id="kelola-pengguna">
                <div class="panel-header">
                    <div>
                        <h2><span><?php echo ui_icon("users", 20); ?></span> Kelola Pengguna & Hak Akses</h2>
                        <p style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                            Pengaturan akun pengguna, kata sandi, dan pembagian hak akses (Owner, Admin, Kasir).
                        </p>
                    </div>
                    <button class="btn-primary-sm" style="background: linear-gradient(135deg, var(--primary), var(--secondary)); display: flex; align-items: center; gap: 6px;" onclick="bukaModalPenggunaBaru()">
                        <span><?php echo ui_icon("plus", 14); ?></span> Tambah Pengguna Baru
                    </button>
                </div>

                <!-- Tabel Pengguna Terdaftar -->
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Pengguna</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Peran (Role)</th>
                                <th>Izin Akses Layar</th>
                                <th style="text-align: right;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users_list as $u): ?>
                                <tr>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <div style="width: 34px; height: 34px; border-radius: 50%; overflow: hidden; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 13px; flex-shrink: 0;
                                                <?php 
                                                    if ($u['role'] === 'Owner') echo 'background: #fef3c7; color: #b45309; border: 1.5px solid #fde68a;';
                                                    elseif ($u['role'] === 'Admin') echo 'background: #e0f2fe; color: #0369a1; border: 1.5px solid #bae6fd;';
                                                    else echo 'background: #dcfce7; color: #15803d; border: 1.5px solid #bbf7d0;';
                                                ?>">
                                                <?php if (!empty($u['foto']) && file_exists(__DIR__ . '/' . $u['foto'])): ?>
                                                    <img src="<?php echo htmlspecialchars($u['foto']); ?>" alt="Foto" style="width: 100%; height: 100%; object-fit: cover;">
                                                <?php else: ?>
                                                    <?php echo strtoupper(substr($u['nama_lengkap'] ?: $u['username'], 0, 1)); ?>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <strong><?php echo htmlspecialchars($u['nama_lengkap'] ?: $u['username']); ?></strong>
                                                <?php if (isset($_SESSION['id_user']) && $u['id'] == $_SESSION['id_user']): ?>
                                                    <span style="font-size: 10px; background: #e2e8f0; color: #475569; padding: 1px 6px; border-radius: 10px; margin-left: 4px;">Akun Anda</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><code>@<?php echo htmlspecialchars($u['username']); ?></code></td>
                                    <td style="color: var(--text-muted); font-size: 12px;">
                                        <?php echo htmlspecialchars($u['email'] ?: 'Belum diisi'); ?>
                                    </td>
                                    <td>
                                        <?php if ($u['role'] === 'Owner'): ?>
                                            <span class="badge badge-role-owner"><?php echo ui_icon("crown", 12); ?> Owner</span>
                                        <?php elseif ($u['role'] === 'Admin'): ?>
                                            <span class="badge badge-role-admin"><?php echo ui_icon("settings", 12); ?> Admin</span>
                                        <?php elseif ($u['role'] === 'User'): ?>
                                            <span class="badge badge-role-user"><?php echo ui_icon("user", 12); ?> User</span>
                                        <?php elseif ($u['role'] === 'Kasir'): ?>
                                            <span class="badge badge-role-kasir"><?php echo ui_icon("cart", 12); ?> Kasir</span>
                                        <?php else: ?>
                                            <span class="badge badge-role-custom"><?php echo ui_icon("shield", 12); ?> <?php echo htmlspecialchars($u['role']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size: 12px; color: var(--text-muted);">
                                        <?php 
                                            if ($u['role'] === 'Owner') echo 'Semua Layar + Kelola Pengguna';
                                            elseif ($u['role'] === 'Admin') echo 'Dashboard & Stok Bahan (admin.php, kasir.php)';
                                            elseif ($u['role'] === 'User' || $u['role'] === 'Kasir') echo 'Hanya Layar Kasir POS (kasir.php)';
                                            else echo 'Sesuai Wewenang Peran Khusus';
                                        ?>
                                    </td>
                                    <td style="text-align: right; white-space: nowrap;">
                                        <div style="display: inline-flex; align-items: center; gap: 5px; justify-content: flex-end;">
                                            <button type="button" class="btn-table-action"
                                                onclick="bukaModalEditPengguna(<?php echo $u['id']; ?>, '<?php echo addslashes($u['nama_lengkap']); ?>', '<?php echo addslashes($u['username']); ?>', '<?php echo addslashes($u['email']); ?>', '<?php echo $u['role']; ?>')" title="Ubah Data Pengguna">
                                                <?php echo ui_icon("edit", 12); ?> Edit
                                            </button>
                                            <button type="button" class="btn-table-action btn-resep"
                                                onclick="bukaUserSetting(<?php echo $u['id']; ?>)" title="Pengaturan Profil Staf">
                                                <?php echo ui_icon("settings", 12); ?> Setting
                                            </button>
                                            <?php if (!isset($_SESSION['id_user']) || $u['id'] != $_SESSION['id_user']): ?>
                                                <form method="POST" action="" onsubmit="return confirm('Apakah Anda yakin ingin menghapus akun @<?php echo addslashes($u['username']); ?>?');" style="display: inline-flex; margin: 0;">
                                                    <input type="hidden" name="id_user_hapus" value="<?php echo $u['id']; ?>">
                                                    <button type="submit" name="hapus_pengguna" class="btn-table-action btn-danger-sm" title="Hapus Akun Pengguna">
                                                        <?php echo ui_icon("trash", 12); ?> Hapus
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <button disabled class="btn-table-action" style="background: #f1f5f9; border-color: #cbd5e1; color: #94a3b8; cursor: not-allowed; opacity: 0.7;" title="Tidak dapat menghapus akun sendiri yang sedang aktif">
                                                    <?php echo ui_icon("check", 12); ?> Aktif
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TAB VIEW 2b: PERAN & HAK AKSES (Khusus Role Owner) -->
        <div id="view-peran" class="tab-view" style="<?php echo ($tab_aktif === 'peran') ? '' : 'display: none;'; ?>">
            <!-- 1. Daftar Peran Pengguna (RBAC) -->
            <div class="panel-card" style="margin-bottom: 22px;">
                <div class="panel-header" style="border-bottom: 1.5px solid var(--border); padding-bottom: 14px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                    <div>
                        <h2><span><?php echo ui_icon("shield", 20); ?></span> Daftar Peran & Hak Akses (RBAC)</h2>
                        <p style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                            Daftar peran sistem UMKM Fillah Food beserta rincian wewenang hak akses modul operasional.
                        </p>
                    </div>
                    <button type="button" class="btn-primary-sm" onclick="bukaModalPeranBaru()" style="padding: 10px 16px; font-size: 12.5px; font-weight: 700;">
                        <?php echo ui_icon("plus", 14); ?> Tambah Peran Baru
                    </button>
                </div>

                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 140px;">Nama Peran</th>
                                <th>Deskripsi / Tanggung Jawab</th>
                                <th>Hak Akses Modul</th>
                                <th style="text-align: center; width: 110px;">Pengguna</th>
                                <th style="text-align: right; width: 150px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($roles_list as $r): 
                                $r_count = $role_user_counts[$r['nama_peran']] ?? 0;
                            ?>
                                <tr>
                                    <td>
                                        <?php if ($r['nama_peran'] === 'Owner'): ?>
                                            <span class="badge badge-role-owner" style="font-size: 12px; font-weight: 800;"><?php echo ui_icon("crown", 12); ?> Owner</span>
                                        <?php elseif ($r['nama_peran'] === 'Admin'): ?>
                                            <span class="badge badge-role-admin" style="font-size: 12px; font-weight: 800;"><?php echo ui_icon("settings", 12); ?> Admin</span>
                                        <?php elseif ($r['nama_peran'] === 'User'): ?>
                                            <span class="badge badge-role-user" style="font-size: 12px; font-weight: 800;"><?php echo ui_icon("user", 12); ?> User</span>
                                        <?php else: ?>
                                            <span class="badge badge-role-custom" style="font-size: 12px; font-weight: 800;"><?php echo ui_icon("shield", 12); ?> <?php echo htmlspecialchars($r['nama_peran']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="font-size: 13px; font-weight: 600; color: var(--text-dark);">
                                            <?php echo htmlspecialchars($r['deskripsi']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="display: flex; flex-wrap: wrap; gap: 4px;">
                                            <?php if ($r['akses_pos']): ?>
                                                <span class="badge badge-safe" style="font-size: 10.5px;">&check; POS Kasir</span>
                                            <?php endif; ?>
                                            <?php if ($r['akses_dashboard']): ?>
                                                <span class="badge badge-safe" style="font-size: 10.5px;">&check; Dashboard</span>
                                            <?php endif; ?>
                                            <?php if ($r['akses_stok']): ?>
                                                <span class="badge badge-safe" style="font-size: 10.5px;">&check; Stok Masuk</span>
                                            <?php endif; ?>
                                            <?php if ($r['akses_menu']): ?>
                                                <span class="badge badge-safe" style="font-size: 10.5px;">&check; Kelola Menu</span>
                                            <?php endif; ?>
                                            <?php if ($r['akses_pengguna']): ?>
                                                <span class="badge badge-safe" style="font-size: 10.5px;">&check; Kelola Pengguna</span>
                                            <?php endif; ?>
                                            <?php if (!$r['akses_pos'] && !$r['akses_dashboard'] && !$r['akses_stok'] && !$r['akses_menu'] && !$r['akses_pengguna']): ?>
                                                <span class="badge badge-danger" style="font-size: 10.5px;">Tidak Ada Akses</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="badge" style="background: #f1f5f9; color: var(--text-dark); font-weight: 700; border: 1px solid var(--border);">
                                            <?php echo $r_count; ?> Akun
                                        </span>
                                    </td>
                                    <td style="text-align: right; white-space: nowrap;">
                                        <div style="display: inline-flex; align-items: center; gap: 5px; justify-content: flex-end;">
                                            <button type="button" class="btn-table-action"
                                                onclick="bukaModalEditPeran(<?php echo $r['id']; ?>, '<?php echo addslashes($r['nama_peran']); ?>', '<?php echo addslashes($r['deskripsi']); ?>', <?php echo (int)$r['akses_pos']; ?>, <?php echo (int)$r['akses_dashboard']; ?>, <?php echo (int)$r['akses_stok']; ?>, <?php echo (int)$r['akses_menu']; ?>, <?php echo (int)$r['akses_pengguna']; ?>)" title="Ubah Hak Akses Peran">
                                                <?php echo ui_icon("edit", 12); ?> Edit
                                            </button>

                                            <?php if (!in_array($r['nama_peran'], ['Owner', 'Admin', 'User'])): ?>
                                                <form method="POST" action="" onsubmit="return confirm('Apakah Anda yakin ingin menghapus peran <?php echo addslashes($r['nama_peran']); ?>?');" style="display: inline-flex; margin: 0;">
                                                    <input type="hidden" name="id_role_hapus" value="<?php echo $r['id']; ?>">
                                                    <button type="submit" name="hapus_peran" class="btn-table-action btn-danger-sm" title="Hapus Peran Khusus">
                                                        <?php echo ui_icon("trash", 12); ?> Hapus
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="btn-table-action" style="background: #f1f5f9; border-color: #e2e8f0; color: #94a3b8; cursor: default;" title="Peran inti sistem bawaan tidak dapat dihapus">
                                                    <?php echo ui_icon("lock", 12); ?> Sistem
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

                <?php endif; ?>

        <!-- TAB VIEW 3: PROFIL SAYA (Pusat Pengaturan Identitas & Keamanan Akun) -->
        <div id="view-profil" class="tab-view" style="<?php echo ($tab_aktif === 'profil') ? '' : 'display: none;'; ?>">
            <div style="display: grid; grid-template-columns: 310px 1fr; gap: 24px; align-items: start;">
                
                <!-- Kolom Kiri: Kartu Identitas & Status Akun -->
                <div class="panel-card" style="text-align: center; padding: 28px 22px;">
                    <!-- Avatar dengan Tombol Kamera -->
                    <div style="position: relative; width: 88px; height: 88px; margin: 0 auto 16px auto;">
                        <div id="profil-avatar-display" style="width: 100%; height: 100%; border-radius: 50%; overflow: hidden; display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: 800; box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
                            <?php echo (is_owner()) ? 'background: #fef3c7; color: #b45309; border: 3px solid #fed7aa;' : 'background: #e0f2fe; color: #0369a1; border: 3px solid #bae6fd;'; ?>">
                            <?php if (!empty($current_user_foto) && file_exists(__DIR__ . '/' . $current_user_foto)): ?>
                                <img src="<?php echo htmlspecialchars($current_user_foto); ?>" alt="Foto Profil" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <?php echo strtoupper(substr($_SESSION['nama_lengkap'] ?? $_SESSION['username'] ?? 'U', 0, 1)); ?>
                            <?php endif; ?>
                        </div>
                        <button type="button" onclick="document.getElementById('input-foto-profil-baru').click()" title="Ganti Foto Profil" style="position: absolute; bottom: 0; right: 0; width: 30px; height: 30px; border-radius: 50%; background: var(--primary); color: #ffffff; border: 2.5px solid #ffffff; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 2px 6px rgba(0,0,0,0.18); transition: transform 0.15s ease;">
                            <?php echo ui_icon("camera", 14); ?>
                        </button>
                    </div>

                    <!-- Nama Lengkap & Username -->
                    <h3 style="font-size: 16px; font-weight: 800; color: var(--text-dark); margin-bottom: 4px;">
                        <?php echo htmlspecialchars($_SESSION['nama_lengkap'] ?? 'Pengguna'); ?>
                    </h3>
                    <div style="margin-bottom: 12px;">
                        <code style="font-size: 12px; color: var(--text-muted); background: #f1f5f9; padding: 3px 10px; border-radius: 6px; font-weight: 600;">
                            @<?php echo htmlspecialchars($_SESSION['username'] ?? 'user'); ?>
                        </code>
                    </div>

                    <!-- Badge Peran -->
                    <div style="margin-bottom: 20px;">
                        <?php if (is_owner()): ?>
                            <span class="badge badge-role-owner" style="font-size: 11px; padding: 4px 12px; font-weight: 700;">
                                <?php echo ui_icon("crown", 12); ?> Owner (Pemilik Usaha)
                            </span>
                        <?php elseif (($_SESSION['role'] ?? '') === 'Admin'): ?>
                            <span class="badge badge-role-admin" style="font-size: 11px; padding: 4px 12px; font-weight: 700;">
                                <?php echo ui_icon("settings", 12); ?> Admin (Staf Persediaan)
                            </span>
                        <?php else: ?>
                            <span class="badge badge-role-user" style="font-size: 11px; padding: 4px 12px; font-weight: 700;">
                                <?php echo ui_icon("user", 12); ?> Staf Kasir POS
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Detail Status Akun & Keamanan -->
                    <div style="border-top: 1px solid var(--border); padding-top: 16px; margin-bottom: 20px; text-align: left;">
                        <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px;">
                            Ringkasan Akun:
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 11px; font-size: 12px;">
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <span style="color: var(--text-muted);">Status Akun:</span>
                                <span style="color: #15803d; font-weight: 700; background: #dcfce7; padding: 2px 9px; border-radius: 12px; font-size: 11px; display: inline-flex; align-items: center; gap: 5px;">
                                    <span style="width: 6px; height: 6px; border-radius: 50%; background: #16a34a;"></span> Aktif
                                </span>
                            </div>
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <span style="color: var(--text-muted);">Hak Wewenang:</span>
                                <span style="font-weight: 700; color: var(--text-dark); font-size: 11.5px;">
                                    <?php echo (is_owner()) ? 'Akses Penuh Seluruh Modul' : 'Akses Operasional'; ?>
                                </span>
                            </div>
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <span style="color: var(--text-muted);">Proteksi Sandi:</span>
                                <span style="font-weight: 600; color: #0369a1; display: inline-flex; align-items: center; gap: 4px; font-size: 11.5px;">
                                    <?php echo ui_icon("lock", 12); ?> Terenkripsi
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Shortcut POS -->
                    <a href="kasir.php" class="btn-primary-sm" style="width: 100%; height: 40px; justify-content: center; font-size: 12.5px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                        <?php echo ui_icon("cart", 16); ?> <span>Buka Layar Kasir POS</span>
                    </a>
                </div>

                <!-- Kolom Kanan: Formulir Pengaturan Profil & Keamanan -->
                <div class="panel-card" style="padding: 26px 28px;">
                    <div class="panel-header" style="border-bottom: 1.5px solid var(--border); padding-bottom: 14px; margin-bottom: 22px;">
                        <div>
                            <h2><span><?php echo ui_icon("user", 20); ?></span> Pengaturan Profil & Keamanan</h2>
                            <p style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                                Perbarui identitas profil, email, foto avatar, dan kata sandi akun Anda.
                            </p>
                        </div>
                    </div>

                    <form method="POST" action="" enctype="multipart/form-data">
                        
                        <!-- Section 1: Informasi Identitas Diri -->
                        <div style="margin-bottom: 22px;">
                            <h3 style="font-size: 13px; font-weight: 800; color: var(--text-dark); margin-bottom: 14px; display: flex; align-items: center; gap: 6px;">
                                <?php echo ui_icon("sparkles", 15); ?> <span>Informasi Identitas Diri</span>
                            </h3>

                            <!-- Foto Profil Uploader Box -->
                            <div class="form-group" style="margin-bottom: 16px;">
                                <label class="form-label" style="font-weight: 700; margin-bottom: 6px;">Foto Profil Akun <span style="font-weight: 400; color: var(--text-muted);">(Opsional — Format JPG, PNG, atau WebP, Maks 2 MB)</span></label>
                                <div style="display: flex; align-items: center; gap: 16px; background: #f8fafc; padding: 12px 16px; border-radius: 12px; border: 1.5px solid var(--border);">
                                    <div id="profil-form-avatar-preview" style="width: 52px; height: 52px; border-radius: 50%; overflow: hidden; display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: 800; border: 2px solid var(--border); flex-shrink: 0; box-shadow: 0 2px 6px rgba(0,0,0,0.06);
                                        <?php echo (is_owner()) ? 'background: #fef3c7; color: #b45309; border-color: #fde68a;' : 'background: #e0f2fe; color: #0369a1; border-color: #bae6fd;'; ?>">
                                        <?php if (!empty($current_user_foto) && file_exists(__DIR__ . '/' . $current_user_foto)): ?>
                                            <img src="<?php echo htmlspecialchars($current_user_foto); ?>" alt="Preview" style="width: 100%; height: 100%; object-fit: cover;">
                                        <?php else: ?>
                                            <?php echo strtoupper(substr($_SESSION['nama_lengkap'] ?? $_SESSION['username'] ?? 'U', 0, 1)); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div style="flex: 1;">
                                        <button type="button" class="btn-primary-sm" style="background: #ffffff; color: var(--text-dark); border: 1.5px solid var(--border); padding: 7px 14px; font-size: 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;" onclick="document.getElementById('input-foto-profil-baru').click()">
                                            <?php echo ui_icon("folder", 14); ?> <span>Pilih Foto Baru</span>
                                        </button>
                                        <input type="file" id="input-foto-profil-baru" name="foto_profil_saya" accept="image/*" style="display: none;" onchange="previewFotoProfilSaya(this)">
                                        <div id="nama-file-profil-saya" style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Pilih file foto dari perangkat Anda untuk memperbarui avatar</div>
                                    </div>
                                </div>
                            </div>

                            <!-- 2 Kolom: Nama Lengkap & Email -->
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 14px; margin-bottom: 14px;">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label" style="font-weight: 700; margin-bottom: 4px;">Nama Lengkap Anda</label>
                                    <input type="text" name="nama_profil" class="form-control" required style="height: 40px;" value="<?php echo htmlspecialchars($_SESSION['nama_lengkap'] ?? ''); ?>">
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label" style="font-weight: 700; margin-bottom: 4px;">Alamat Email</label>
                                    <input type="email" name="email_profil" class="form-control" placeholder="nama@fillahfood.com" style="height: 40px;" value="<?php echo htmlspecialchars($current_user_email); ?>">
                                </div>
                            </div>

                            <!-- Username ID Akun (Permanen) -->
                            <div class="form-group" style="margin-bottom: 0;">
                                <label class="form-label" style="font-weight: 700; margin-bottom: 4px;">Username (ID Akun)</label>
                                <input type="text" class="form-control" disabled style="background: #f1f5f9; cursor: not-allowed; font-family: monospace; font-weight: 600; height: 40px;" value="@<?php echo htmlspecialchars($_SESSION['username'] ?? 'user'); ?>">
                                <div style="display: flex; align-items: center; gap: 5px; margin-top: 4px; font-size: 11px; color: var(--text-muted);">
                                    <?php echo ui_icon("lock", 11); ?>
                                    <span>Username bersifat tetap sebagai identitas otentikasi dan riwayat transaksi kasir.</span>
                                </div>
                            </div>
                        </div>

                        <!-- Divider Line Symmetrical -->
                        <div style="border-top: 1.5px solid var(--border); margin: 22px 0;"></div>

                        <!-- Section 2: Keamanan & Ganti Kata Sandi -->
                        <div style="margin-bottom: 22px;">
                            <h3 style="font-size: 13px; font-weight: 800; color: var(--text-dark); margin-bottom: 14px; display: flex; align-items: center; gap: 6px;">
                                <?php echo ui_icon("lock", 15); ?> <span>Keamanan & Ganti Kata Sandi</span>
                            </h3>

                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 14px;">
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label" style="font-weight: 700; margin-bottom: 4px;">Kata Sandi Baru</label>
                                    <input type="password" name="password_baru_profil" class="form-control" placeholder="Ketik sandi baru jika ingin diubah" style="height: 40px;">
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <label class="form-label" style="font-weight: 700; margin-bottom: 4px;">Konfirmasi Kata Sandi Baru</label>
                                    <input type="password" name="konfirmasi_password_profil" class="form-control" placeholder="Ulangi kata sandi baru untuk konfirmasi" style="height: 40px;">
                                </div>
                            </div>
                            <small style="color: var(--text-muted); font-size: 11.5px; display: block; margin-top: 6px;">
                                Biarkan kedua kolom kata sandi di atas kosong jika Anda tidak berencana mengganti kata sandi akun saat ini.
                            </small>
                        </div>

                        <!-- Section 3: Submit Button Bar -->
                        <div style="border-top: 1.5px solid var(--border); padding-top: 20px; display: flex; justify-content: flex-end;">
                            <button type="submit" name="simpan_profil_saya" class="btn-primary-sm" style="height: 44px; padding: 0 28px; font-size: 13px; font-weight: 700; display: inline-flex; align-items: center; gap: 8px;">
                                <?php echo ui_icon("save", 16); ?> <span>Simpan & Perbarui Profil Saya</span>
                            </button>
                        </div>

                    </form>
                </div>

            </div>
        </div>

        </div>
    </div>

    <!-- Modal Form Tambah Stok Masuk -->
    <div class="modal-overlay" id="modal-stok-masuk">
        <div class="modal-box">
            <h3 class="modal-title">Catat Stok Masuk (Belanja Bahan)</h3>
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">Pilih Bahan Baku</label>
                    <select name="id_bahan" id="select-id-bahan" class="form-control" required>
                        <?php foreach ($bahan_baku_list as $b): ?>
                            <option value="<?php echo $b['id']; ?>">
                                <?php echo htmlspecialchars($b['nama_bahan']); ?> (Satuan: <?php echo htmlspecialchars($b['satuan']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Jumlah Pasokan Masuk (<span id="satuan-label">satuan</span>)</label>
                    <input type="number" step="0.01" name="jumlah_masuk" class="form-control" placeholder="Contoh: 10 atau 2.5" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Catatan Belanja / Suplier (Opsional)</label>
                    <input type="text" name="catatan_masuk" class="form-control" placeholder="Contoh: Belanja di Pasar Rogojampi / Grosir">
                </div>

                <div class="modal-footer" style="display:flex; gap:10px; margin-top:20px;">
                    <button type="submit" name="tambah_stok_masuk" class="btn-primary-sm" style="flex:1; height:42px; justify-content:center; font-size:13px; font-weight:700;">Simpan Stok Masuk</button>
                    <button type="button" class="btn-primary-sm" style="height:42px; padding:0 22px; font-size:13px; font-weight:600; border-radius:8px; border:1.5px solid #cbd5e1; background:#ffffff; color:#475569;" onclick="tutupModalStokMasuk()">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Form Tambah Bahan Baru -->
    <div class="modal-overlay" id="modal-bahan-baru">
        <div class="modal-box">
            <h3 class="modal-title">Tambah Bahan Baku Baru</h3>
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-group">
                    <label class="form-label">Nama Bahan Baku</label>
                    <input type="text" name="nama_bahan" class="form-control" placeholder="Contoh: Bawang Merah, Saus Tiram" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Satuan Ukuran</label>
                    <input type="text" name="satuan" class="form-control" placeholder="Contoh: kg, liter, butir, bungkus, potong" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Stok Awal</label>
                    <input type="number" step="0.01" name="stok_awal" class="form-control" placeholder="0" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Ambang Batas Minimum (Peringatan)</label>
                    <input type="number" step="0.01" name="batas_minimum" class="form-control" placeholder="Contoh: 2" required>
                </div>

                <div class="form-group">
                    <label class="form-label"> Foto Bahan Baku <span style="font-weight:400;color:#64748b;">(Opsional — maks 2 MB, JPG/PNG/WebP)</span></label>
                    <div class="img-upload-area" onclick="document.getElementById('input-gambar-bahan').click()">
                        <div class="img-upload-preview" id="preview-bahan">
                            <span class="img-upload-icon"><?php echo ui_icon("box", 32); ?></span>
                            <span class="img-upload-hint">Klik untuk pilih foto</span>
                        </div>
                    </div>
                    <input type="file" id="input-gambar-bahan" name="gambar_bahan" accept="image/*" style="display:none" onchange="previewGambar(this, 'preview-bahan')">
                </div>

                <div class="modal-footer" style="display:flex; gap:10px; margin-top:20px;">
                    <button type="submit" name="tambah_bahan_baru" class="btn-primary-sm" style="flex:1; height:42px; justify-content:center; font-size:13px; font-weight:700;">Daftarkan Bahan</button>
                    <button type="button" class="btn-primary-sm" style="height:42px; padding:0 22px; font-size:13px; font-weight:600; border-radius:8px; border:1.5px solid #cbd5e1; background:#ffffff; color:#475569;" onclick="tutupModalBahanBaru()">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Form Tambah Menu Produk Baru -->
    <div class="modal-overlay" id="modal-produk-baru">
        <div class="modal-box">
            <h3 class="modal-title">Tambah Menu Makanan / Minuman Baru</h3>
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-group">
                    <label class="form-label">Nama Menu Makanan / Minuman</label>
                    <input type="text" name="nama_produk" class="form-control" placeholder="Contoh: Ayam Bakar Madu" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Kategori Menu</label>
                    <select name="kategori" id="select-kategori-produk" class="form-control" required onchange="updateDefaultPreviewProduk(this.value)">
                        <option value="Makanan">Makanan</option>
                        <option value="Minuman">Minuman</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Harga Jual Satuan (Rp)</label>
                    <input type="number" step="500" name="harga" class="form-control" placeholder="Contoh: 12000" required>
                </div>

                <div class="form-group">
                    <label class="form-label"> Foto Menu <span style="font-weight:400;color:#64748b;">(Opsional — maks 2 MB, JPG/PNG/WebP)</span></label>
                    <div class="img-upload-area" onclick="document.getElementById('input-gambar-produk').click()">
                        <div class="img-upload-preview" id="preview-produk">
                            <span class="img-upload-icon" id="preview-produk-icon"><?php echo ui_icon("utensils", 32); ?></span>
                            <span class="img-upload-hint">Klik untuk pilih foto</span>
                        </div>
                    </div>
                    <input type="file" id="input-gambar-produk" name="gambar_produk" accept="image/*" style="display:none" onchange="previewGambar(this, 'preview-produk')">
                </div>

                <div class="modal-footer" style="display:flex; gap:10px; margin-top:20px;">
                    <button type="submit" name="tambah_produk_baru" class="btn-primary-sm" style="flex:1; height:42px; justify-content:center; font-size:13px; font-weight:700;">Simpan Menu Baru</button>
                    <button type="button" class="btn-primary-sm" style="height:42px; padding:0 22px; font-size:13px; font-weight:600; border-radius:8px; border:1.5px solid #cbd5e1; background:#ffffff; color:#475569;" onclick="tutupModalProdukBaru()">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Form Catat Stok Keluar / Bahan Rusak & Basi -->
    <div class="modal-overlay" id="modal-stok-keluar">
        <div class="modal-box">
            <h3 class="modal-title">Catat Bahan Rusak / Basi (Outbound)</h3>
            <p style="font-size:12px; color:var(--text-muted); margin-top:-6px; margin-bottom:14px;">Catat bahan baku yang rusak, basi, kadaluwarsa, atau tumpah untuk memotong stok fisik.</p>
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">Pilih Bahan Baku</label>
                    <select name="id_bahan" id="select-keluar-id-bahan" class="form-control" required onchange="updateSatuanKeluar(this)">
                        <?php foreach ($bahan_baku_list as $b): ?>
                            <option value="<?php echo $b['id']; ?>" data-satuan="<?php echo htmlspecialchars($b['satuan']); ?>" data-stok="<?php echo $b['stok']; ?>">
                                <?php echo htmlspecialchars($b['nama_bahan']); ?> (Tersisa: <?php echo rtrim(rtrim(number_format($b['stok'], 2, '.', ''), '0'), '.'); ?> <?php echo htmlspecialchars($b['satuan']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Jumlah Keluar / Rusak (<span id="satuan-label-keluar">satuan</span>)</label>
                    <input type="number" step="0.01" name="jumlah_keluar" class="form-control" placeholder="Contoh: 1 atau 0.5" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Alasan Pengurangan Stok</label>
                    <select name="alasan" class="form-control" required>
                        <option value="Basi / Busuk">Basi / Busuk</option>
                        <option value="Kadaluwarsa (Expired)">Kadaluwarsa (Expired)</option>
                        <option value="Rusak / Tumpah / Gosong">Rusak / Tumpah / Gosong</option>
                        <option value="Sampel / Tester">Sampel / Tester</option>
                        <option value="Penyesuaian Selisih Opname">Penyesuaian Selisih Opname</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Catatan Tambahan (Opsional)</label>
                    <input type="text" name="catatan_keluar" class="form-control" placeholder="Contoh: Ditemukan berjamur di wadah penyimpanan">
                </div>

                <div class="modal-footer" style="display:flex; gap:10px; margin-top:20px;">
                    <button type="submit" name="catat_stok_keluar" class="btn-primary-sm" style="flex:1; height:42px; justify-content:center; font-size:13px; font-weight:700; background:#dc2626;">Simpan Pengurangan Stok</button>
                    <button type="button" class="btn-primary-sm" style="height:42px; padding:0 22px; font-size:13px; font-weight:600; border-radius:8px; border:1.5px solid #cbd5e1; background:#ffffff; color:#475569;" onclick="tutupModalStokKeluar()">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Edit Bahan Baku -->
    <div class="modal-overlay" id="modal-edit-bahan">
        <div class="modal-box">
            <h3 class="modal-title"><?php echo ui_icon("edit", 13); ?> Edit Bahan Baku</h3>
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="id_bahan" id="edit-id-bahan">
                
                <div class="form-group">
                    <label class="form-label">Nama Bahan Baku</label>
                    <input type="text" name="nama_bahan" id="edit-nama-bahan" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Satuan Ukuran</label>
                    <input type="text" name="satuan" id="edit-satuan-bahan" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Ambang Batas Minimum</label>
                    <input type="number" step="0.01" name="batas_minimum" id="edit-batas-bahan" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Ganti Foto Bahan Baku (Opsional)</label>
                    <div class="img-upload-area" onclick="document.getElementById('input-edit-gambar-bahan').click()">
                        <div class="img-upload-preview" id="preview-edit-bahan">
                            <span class="img-upload-icon"><?php echo ui_icon("box", 32); ?></span>
                            <span class="img-upload-hint">Klik untuk ganti foto</span>
                        </div>
                    </div>
                    <input type="file" id="input-edit-gambar-bahan" name="gambar_bahan_edit" accept="image/*" style="display:none" onchange="previewGambar(this, 'preview-edit-bahan')">
                </div>

                <div class="modal-footer" style="display:flex; gap:10px; margin-top:20px;">
                    <button type="submit" name="edit_bahan" class="btn-primary-sm" style="flex:1; height:42px; justify-content:center; font-size:13px; font-weight:700;">Simpan Perubahan</button>
                    <button type="button" class="btn-primary-sm" style="height:42px; padding:0 22px; font-size:13px; font-weight:600; border-radius:8px; border:1.5px solid #cbd5e1; background:#ffffff; color:#475569;" onclick="tutupModalEditBahan()">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Edit Menu Produk -->
    <div class="modal-overlay" id="modal-edit-produk">
        <div class="modal-box">
            <h3 class="modal-title"><?php echo ui_icon("edit", 13); ?> Edit Menu Makanan / Minuman</h3>
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="id_produk" id="edit-id-produk">

                <div class="form-group">
                    <label class="form-label">Nama Menu</label>
                    <input type="text" name="nama_produk" id="edit-nama-produk" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Kategori</label>
                    <select name="kategori" id="edit-kategori-produk" class="form-control" required>
                        <option value="Makanan">Makanan</option>
                        <option value="Minuman">Minuman</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Harga Jual Satuan (Rp)</label>
                    <input type="number" step="500" name="harga" id="edit-harga-produk" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Ganti Foto Menu (Opsional)</label>
                    <div class="img-upload-area" onclick="document.getElementById('input-edit-gambar-produk').click()">
                        <div class="img-upload-preview" id="preview-edit-produk">
                            <span class="img-upload-icon"><?php echo ui_icon("utensils", 32); ?></span>
                            <span class="img-upload-hint">Klik untuk ganti foto</span>
                        </div>
                    </div>
                    <input type="file" id="input-edit-gambar-produk" name="gambar_produk_edit" accept="image/*" style="display:none" onchange="previewGambar(this, 'preview-edit-produk')">
                </div>

                <div class="modal-footer" style="display:flex; gap:10px; margin-top:20px;">
                    <button type="submit" name="edit_produk" class="btn-primary-sm" style="flex:1; height:42px; justify-content:center; font-size:13px; font-weight:700;">Simpan Perubahan</button>
                    <button type="button" class="btn-primary-sm" style="height:42px; padding:0 22px; font-size:13px; font-weight:600; border-radius:8px; border:1.5px solid #cbd5e1; background:#ffffff; color:#475569;" onclick="tutupModalEditProduk()">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Atur Resep & Komposisi Bahan Baku (BOM) -->
    <div class="modal-overlay" id="modal-atur-resep">
        <div class="modal-box" style="max-width: 580px;">
            <h3 class="modal-title">Formula Resep: <span id="resep-nama-menu" style="color:var(--primary);">Menu</span></h3>
            <p style="font-size:12px; color:var(--text-muted); margin-top:-6px; margin-bottom:16px;">Tentukan komposisi takaran bahan baku yang terpotong otomatis saat menu ini terjual di kasir.</p>

            <!-- Tabel Komposisi Bahan Saat Ini -->
            <div style="margin-bottom: 20px;">
                <h4 style="font-size:13px; font-weight:700; margin-bottom:8px; color:var(--text-dark);">Bahan Baku Terpasang:</h4>
                <div class="table-responsive" style="max-height: 220px; overflow-y: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Bahan Baku</th>
                                <th>Takaran Dibutuhkan</th>
                                <th style="text-align:right;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="resep-tabel-body">
                            <!-- Diisi via JavaScript renderResepItems() -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Form Tambah Bahan ke Resep -->
            <div style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 10px; padding: 14px;">
                <h4 style="font-size:12.5px; font-weight:700; margin-bottom:10px; color:#1e293b;">+ Tambah / Sesuaikan Bahan:</h4>
                <form method="POST" action="">
                    <input type="hidden" name="id_menu" id="resep-form-id-menu">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label" style="font-size:11px;">Pilih Bahan</label>
                            <select name="id_bahan" id="resep-select-bahan" class="form-control" required onchange="updateSatuanResep(this)">
                                <?php foreach ($bahan_baku_list as $b): ?>
                                    <option value="<?php echo $b['id']; ?>" data-satuan="<?php echo htmlspecialchars($b['satuan']); ?>">
                                        <?php echo htmlspecialchars($b['nama_bahan']); ?> (<?php echo htmlspecialchars($b['satuan']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label" style="font-size:11px;">Takaran / Porsi (<span id="resep-satuan-label">satuan</span>)</label>
                            <input type="number" step="0.001" name="jumlah_dibutuhkan" class="form-control" placeholder="Contoh: 1 atau 0.1" required>
                        </div>
                    </div>
                    <button type="submit" name="simpan_resep_item" class="btn-primary-sm" style="width:100%; margin-top:12px; padding:9px;">
                        Simpan ke Resep Menu
                    </button>
                </form>
            </div>

            <div class="modal-footer" style="margin-top: 16px;">
                <button type="button" class="btn-primary-sm" style="background:#cbd5e1; color:#0f172a; width:100%;" onclick="tutupModalAturResep()">Tutup</button>
            </div>
        </div>
    </div>

    <!-- Modal Form Tambah Pengguna Baru (Khusus Owner) -->
    <?php if (is_owner()): ?>
    <div class="modal-overlay" id="modal-pengguna-baru">
        <div class="modal-box">
            <h3 class="modal-title">Tambah Pengguna Baru</h3>
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">Nama Lengkap Pengguna</label>
                    <input type="text" name="nama_lengkap" class="form-control" placeholder="Contoh: Aisyah Marta Dewi" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Username (Nama Pengguna untuk Login)</label>
                    <input type="text" name="username" class="form-control" placeholder="Contoh: kasir_2" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Kata Sandi (Password Awal)</label>
                    <input type="password" name="password" class="form-control" placeholder="Masukkan kata sandi awal" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Alamat Email (Opsional / Notifikasi)</label>
                    <input type="email" name="email" class="form-control" placeholder="Contoh: user@fillahfood.com">
                </div>

                <div class="form-group">
                    <label class="form-label">Pilih Peran Hak Akses (Role)</label>
                    <select name="role" class="form-control" required>
                        <?php foreach ($roles_list as $rl): ?>
                            <option value="<?php echo htmlspecialchars($rl['nama_peran']); ?>" <?php echo ($rl['nama_peran'] === 'User') ? 'selected' : ''; ?>>
                                <?php 
                                    if ($rl['nama_peran'] === 'Owner') echo '<?php echo ui_icon("crown", 12); ?> Owner (Pemilik Usaha - Akses Penuh)';
                                    elseif ($rl['nama_peran'] === 'Admin') echo '<?php echo ui_icon("settings", 12); ?> Admin (Staf Pengelola Persediaan & Menu)';
                                    elseif ($rl['nama_peran'] === 'User') echo '<?php echo ui_icon("user", 12); ?> User (Staf Operasional / Layar Kasir POS)';
                                    else echo '<?php echo ui_icon("shield", 12); ?> ' . htmlspecialchars($rl['nama_peran']) . ' (' . htmlspecialchars($rl['deskripsi']) . ')';
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="modal-footer" style="display:flex; gap:10px; margin-top:20px;">
                    <button type="submit" name="tambah_pengguna" class="btn-primary-sm" style="flex:1; height:42px; justify-content:center; font-size:13px; font-weight:700;">Simpan Pengguna</button>
                    <button type="button" class="btn-primary-sm" style="height:42px; padding:0 22px; font-size:13px; font-weight:600; border-radius:8px; border:1.5px solid #cbd5e1; background:#ffffff; color:#475569;" onclick="tutupModalPenggunaBaru()">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Form Edit Pengguna & Peran (Khusus Owner) -->
    <div class="modal-overlay" id="modal-edit-pengguna">
        <div class="modal-box">
            <h3 class="modal-title"><?php echo ui_icon("edit", 13); ?> Edit Pengguna & Peran Hak Akses</h3>
            <form method="POST" action="">
                <input type="hidden" name="id_user" id="edit-id-user">

                <div class="form-group">
                    <label class="form-label">Username (Akun)</label>
                    <input type="text" id="edit-username" class="form-control" disabled style="background: #f1f5f9; cursor: not-allowed;">
                </div>

                <div class="form-group">
                    <label class="form-label">Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" id="edit-nama-lengkap" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Alamat Email</label>
                    <input type="email" name="email" id="edit-email" class="form-control">
                </div>

                <div class="form-group">
                    <label class="form-label">Peran Hak Akses (Role)</label>
                    <select name="role" id="edit-role" class="form-control" required>
                        <?php foreach ($roles_list as $rl): ?>
                            <option value="<?php echo htmlspecialchars($rl['nama_peran']); ?>">
                                <?php 
                                    if ($rl['nama_peran'] === 'Owner') echo '<?php echo ui_icon("crown", 12); ?> Owner (Pemilik Usaha - Akses Penuh)';
                                    elseif ($rl['nama_peran'] === 'Admin') echo '<?php echo ui_icon("settings", 12); ?> Admin (Staf Pengelola Persediaan & Menu)';
                                    elseif ($rl['nama_peran'] === 'User') echo '<?php echo ui_icon("user", 12); ?> User (Staf Operasional / Layar Kasir POS)';
                                    else echo '<?php echo ui_icon("shield", 12); ?> ' . htmlspecialchars($rl['nama_peran']) . ' (' . htmlspecialchars($rl['deskripsi']) . ')';
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Reset Password Baru (Kosongkan jika tidak ingin ganti)</label>
                    <input type="password" name="password" class="form-control" placeholder="Ketik sandi baru jika ingin diubah">
                </div>

                <div class="modal-footer" style="display:flex; gap:10px; margin-top:20px;">
                    <button type="submit" name="edit_pengguna" class="btn-primary-sm" style="flex:1; height:42px; justify-content:center; font-size:13px; font-weight:700;">Simpan Perubahan</button>
                    <button type="button" class="btn-primary-sm" style="height:42px; padding:0 22px; font-size:13px; font-weight:600; border-radius:8px; border:1.5px solid #cbd5e1; background:#ffffff; color:#475569;" onclick="tutupModalEditPengguna()">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Form Tambah Peran Baru (Khusus Owner) -->
    <div class="modal-overlay" id="modal-tambah-peran">
        <div class="modal-box" style="max-width: 500px;">
            <h3 class="modal-title"><?php echo ui_icon("shield", 12); ?> Tambah Peran Baru</h3>
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">Nama Peran</label>
                    <input type="text" name="nama_peran" class="form-control" placeholder="Contoh: Supervisor, Barista, Auditor" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Deskripsi / Tanggung Jawab</label>
                    <input type="text" name="deskripsi" class="form-control" placeholder="Contoh: Mengawasi operasional dan stok harian" required>
                </div>

                <div class="form-group">
                    <label class="form-label" style="margin-bottom: 8px;">Izin Hak Akses Modul:</label>
                    <div style="display: flex; flex-direction: column; gap: 8px; background: #f8fafc; padding: 12px; border-radius: 8px; border: 1.5px solid var(--border);">
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                            <input type="checkbox" name="akses_pos" value="1" checked style="width: 16px; height: 16px;">
                            <span>Layar Kasir POS (Pencatatan Pesanan & Transaksi)</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                            <input type="checkbox" name="akses_dashboard" value="1" style="width: 16px; height: 16px;">
                            <span>Dashboard Ringkasan Omzet & KPI Bisnis</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                            <input type="checkbox" name="akses_stok" value="1" style="width: 16px; height: 16px;">
                            <span>Pencatatan Pasokan Stok Bahan Masuk</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                            <input type="checkbox" name="akses_menu" value="1" style="width: 16px; height: 16px;">
                            <span>Manajemen Menu Makanan & Minuman</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                            <input type="checkbox" name="akses_pengguna" value="1" style="width: 16px; height: 16px;">
                            <span>Pengelolaan Akun Pengguna & Pengaturan</span>
                        </label>
                    </div>
                </div>

                <div class="modal-footer" style="display:flex; gap:10px; margin-top:20px;">
                    <button type="submit" name="tambah_peran" class="btn-primary-sm" style="flex:1; height:42px; justify-content:center; font-size:13px; font-weight:700;">Simpan Peran Baru</button>
                    <button type="button" class="btn-primary-sm" style="height:42px; padding:0 22px; font-size:13px; font-weight:600; border-radius:8px; border:1.5px solid #cbd5e1; background:#ffffff; color:#475569;" onclick="tutupModalPeranBaru()">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Form Edit Peran (Khusus Owner) -->
    <div class="modal-overlay" id="modal-edit-peran">
        <div class="modal-box" style="max-width: 500px;">
            <h3 class="modal-title"><?php echo ui_icon("edit", 13); ?> Edit Data Peran & Hak Akses</h3>
            <form method="POST" action="">
                <input type="hidden" name="id_role" id="edit-id-role">

                <div class="form-group">
                    <label class="form-label">Nama Peran</label>
                    <input type="text" name="nama_peran" id="edit-nama-peran" class="form-control" required>
                    <small id="edit-peran-hint" style="color: var(--text-muted); font-size: 11px;"></small>
                </div>

                <div class="form-group">
                    <label class="form-label">Deskripsi / Tanggung Jawab</label>
                    <input type="text" name="deskripsi" id="edit-deskripsi-peran" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label" style="margin-bottom: 8px;">Izin Hak Akses Modul:</label>
                    <div style="display: flex; flex-direction: column; gap: 8px; background: #f8fafc; padding: 12px; border-radius: 8px; border: 1.5px solid var(--border);">
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                            <input type="checkbox" name="akses_pos" id="edit-akses-pos" value="1" style="width: 16px; height: 16px;">
                            <span>Layar Kasir POS (Pencatatan Pesanan & Transaksi)</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                            <input type="checkbox" name="akses_dashboard" id="edit-akses-dash" value="1" style="width: 16px; height: 16px;">
                            <span>Dashboard Ringkasan Omzet & KPI Bisnis</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                            <input type="checkbox" name="akses_stok" id="edit-akses-stok" value="1" style="width: 16px; height: 16px;">
                            <span>Pencatatan Pasokan Stok Bahan Masuk</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                            <input type="checkbox" name="akses_menu" id="edit-akses-menu" value="1" style="width: 16px; height: 16px;">
                            <span>Manajemen Menu Makanan & Minuman</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                            <input type="checkbox" name="akses_pengguna" id="edit-akses-peng" value="1" style="width: 16px; height: 16px;">
                            <span>Pengelolaan Akun Pengguna & Pengaturan</span>
                        </label>
                    </div>
                </div>

                <div class="modal-footer" style="display:flex; gap:10px; margin-top:20px;">
                    <button type="submit" name="edit_peran" class="btn-primary-sm" style="flex:1; height:42px; justify-content:center; font-size:13px; font-weight:700;">Simpan Perubahan Peran</button>
                    <button type="button" class="btn-primary-sm" style="height:42px; padding:0 22px; font-size:13px; font-weight:600; border-radius:8px; border:1.5px solid #cbd5e1; background:#ffffff; color:#475569;" onclick="tutupModalEditPeran()">Batal</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        /* ---- Modal Open/Close ---- */
        function bukaModalStokMasuk() {
            document.getElementById('modal-stok-masuk').style.display = 'flex';
        }
        function tutupModalStokMasuk() {
            document.getElementById('modal-stok-masuk').style.display = 'none';
        }
        function bukaModalBahanBaru() {
            document.getElementById('modal-bahan-baru').style.display = 'flex';
        }
        function tutupModalBahanBaru() {
            // Reset preview saat modal ditutup
            resetPreview('preview-bahan', 'Bahan');
            document.getElementById('input-gambar-bahan').value = '';
            document.getElementById('modal-bahan-baru').style.display = 'none';
        }
        function bukaModalProdukBaru() {
            document.getElementById('modal-produk-baru').style.display = 'flex';
        }
        function tutupModalProdukBaru() {
            // Reset preview saat modal ditutup
            resetPreview('preview-produk', 'Menu');
            document.getElementById('input-gambar-produk').value = '';
            document.getElementById('modal-produk-baru').style.display = 'none';
        }

        /* ---- Modal Stok Keluar ---- */
        function bukaModalStokKeluar() {
            var el = document.getElementById('modal-stok-keluar');
            if (el) el.style.display = 'flex';
        }
        function tutupModalStokKeluar() {
            var el = document.getElementById('modal-stok-keluar');
            if (el) el.style.display = 'none';
        }
        function catatKeluarCepat(id, nama, satuan) {
            var sel = document.getElementById('select-keluar-id-bahan');
            if (sel) sel.value = id;
            var lbl = document.getElementById('satuan-label-keluar');
            if (lbl) lbl.textContent = satuan;
            bukaModalStokKeluar();
        }
        function updateSatuanKeluar(sel) {
            var opt = sel.options[sel.selectedIndex];
            var satuan = opt ? (opt.getAttribute('data-satuan') || 'satuan') : 'satuan';
            var lbl = document.getElementById('satuan-label-keluar');
            if (lbl) lbl.textContent = satuan;
        }

        /* ---- Modal Edit Bahan Baku ---- */
        function bukaModalEditBahan(b) {
            document.getElementById('edit-id-bahan').value = b.id;
            document.getElementById('edit-nama-bahan').value = b.nama_bahan;
            document.getElementById('edit-satuan-bahan').value = b.satuan;
            document.getElementById('edit-batas-bahan').value = b.batas_minimum;
            var preview = document.getElementById('preview-edit-bahan');
            if (preview) {
                if (b.gambar) {
                    preview.innerHTML = '<img src="' + b.gambar + '" style="width:100%; height:100%; object-fit:cover;">';
                } else {
                    resetPreview('preview-edit-bahan', 'Bahan');
                }
            }
            document.getElementById('input-edit-gambar-bahan').value = '';
            document.getElementById('modal-edit-bahan').style.display = 'flex';
        }
        function tutupModalEditBahan() {
            document.getElementById('modal-edit-bahan').style.display = 'none';
        }

        /* ---- Modal Edit Produk ---- */
        function bukaModalEditProduk(p) {
            document.getElementById('edit-id-produk').value = p.id;
            document.getElementById('edit-nama-produk').value = p.nama_produk;
            document.getElementById('edit-kategori-produk').value = p.kategori;
            document.getElementById('edit-harga-produk').value = p.harga;
            var preview = document.getElementById('preview-edit-produk');
            if (preview) {
                if (p.gambar) {
                    preview.innerHTML = '<img src="' + p.gambar + '" style="width:100%; height:100%; object-fit:cover;">';
                } else {
                    var icon = (p.kategori === 'Minuman') ? 'Minuman' : 'Makanan';
                    resetPreview('preview-edit-produk', icon);
                }
            }
            document.getElementById('input-edit-gambar-produk').value = '';
            document.getElementById('modal-edit-produk').style.display = 'flex';
        }
        function tutupModalEditProduk() {
            document.getElementById('modal-edit-produk').style.display = 'none';
        }

        /* ---- Modal Atur Resep (BOM) ---- */
        var dataResepMap = <?php echo json_encode($resep_map); ?>;
        function bukaModalAturResep(idMenu, namaMenu) {
            document.getElementById('resep-nama-menu').textContent = namaMenu;
            document.getElementById('resep-form-id-menu').value = idMenu;
            renderResepItems(idMenu);
            var sel = document.getElementById('resep-select-bahan');
            if (sel) updateSatuanResep(sel);
            document.getElementById('modal-atur-resep').style.display = 'flex';
        }
        function tutupModalAturResep() {
            document.getElementById('modal-atur-resep').style.display = 'none';
        }
        function updateSatuanResep(sel) {
            var opt = sel.options[sel.selectedIndex];
            var satuan = opt ? (opt.getAttribute('data-satuan') || 'satuan') : 'satuan';
            var lbl = document.getElementById('resep-satuan-label');
            if (lbl) lbl.textContent = satuan;
        }
        function renderResepItems(idMenu) {
            var tbody = document.getElementById('resep-tabel-body');
            if (!tbody) return;
            var items = dataResepMap[idMenu] || [];
            if (items.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3" style="text-align:center; color:#94a3b8; padding:14px; font-size:12.5px;">Belum ada bahan baku yang dipetakan pada resep menu ini.</td></tr>';
                return;
            }
            var html = '';
            items.forEach(function(item) {
                html += '<tr>' +
                    '<td><strong>' + item.nama_bahan + '</strong></td>' +
                    '<td><span style="font-weight:700; color:var(--primary);">' + parseFloat(item.jumlah_dibutuhkan) + '</span> ' + item.satuan + '</td>' +
                    '<td style="text-align:right;">' +
                        '<form method="POST" action="" style="display:inline-flex; margin:0;" onsubmit="return confirm(\'Keluarkan ' + item.nama_bahan + ' dari resep menu ini?\');">' +
                            '<input type="hidden" name="id_resep" value="' + item.id + '">' +
                            '<button type="submit" name="hapus_resep_item" class="btn-table-action btn-danger-sm" title="Hapus dari Formula Resep">' +
                                '<svg style="width:12px;height:12px;vertical-align:middle;flex-shrink:0;"><use href="#icon-trash"></use></svg> Hapus' +
                            '</button>' +
                        '</form>' +
                    '</td>' +
                '</tr>';
            });
            tbody.innerHTML = html;
        }

        /* ---- Filter & Search Menu Produk ---- */
        var currentKategoriFilter = 'all';
        function filterKategoriProduk(kat, btn) {
            currentKategoriFilter = kat;
            document.querySelectorAll('.filter-pill').forEach(function(p) { p.classList.remove('active'); });
            if (btn) btn.classList.add('active');
            filterMenuProduk();
        }
        function filterMenuProduk() {
            var inputEl = document.getElementById('cari-menu-produk');
            var query = (inputEl ? inputEl.value : '').toLowerCase();
            var rows = document.querySelectorAll('.baris-produk');
            rows.forEach(function(row) {
                var nama = (row.querySelector('.nama-produk-teks') ? row.querySelector('.nama-produk-teks').textContent : '').toLowerCase();
                var kat = row.getAttribute('data-kategori') || '';
                var matchKat = (currentKategoriFilter === 'all' || kat === currentKategoriFilter);
                var matchQuery = (nama.indexOf(query) !== -1);
                row.style.display = (matchKat && matchQuery) ? '' : 'none';
            });
        }

        /* ---- Filter & Search Bahan Baku ---- */
        function filterBahanBaku() {
            var inputEl = document.getElementById('cari-bahan-baku');
            var query = (inputEl ? inputEl.value : '').toLowerCase();
            var rows = document.querySelectorAll('.baris-bahan');
            rows.forEach(function(row) {
                var nama = (row.querySelector('.nama-bahan-teks') ? row.querySelector('.nama-bahan-teks').textContent : '').toLowerCase();
                row.style.display = (nama.indexOf(query) !== -1) ? '' : 'none';
            });
        }

        function bukaModalPenggunaBaru() {
            var el = document.getElementById('modal-pengguna-baru');
            if (el) el.style.display = 'flex';
        }

        function tutupModalPenggunaBaru() {
            var el = document.getElementById('modal-pengguna-baru');
            if (el) el.style.display = 'none';
        }

        function bukaModalEditPengguna(id, nama, user, email, role) {
            document.getElementById('edit-id-user').value = id;
            document.getElementById('edit-nama-lengkap').value = nama;
            document.getElementById('edit-username').value = '@' + user;
            document.getElementById('edit-email').value = email;
            document.getElementById('edit-role').value = role;
            var el = document.getElementById('modal-edit-pengguna');
            if (el) el.style.display = 'flex';
        }

        function tutupModalEditPengguna() {
            var el = document.getElementById('modal-edit-pengguna');
            if (el) el.style.display = 'none';
        }

        function bukaModalPeranBaru() {
            var el = document.getElementById('modal-tambah-peran');
            if (el) el.style.display = 'flex';
        }

        function tutupModalPeranBaru() {
            var el = document.getElementById('modal-tambah-peran');
            if (el) el.style.display = 'none';
        }

        function bukaModalEditPeran(id, nama, deskripsi, pos, dash, stok, menu, peng) {
            document.getElementById('edit-id-role').value = id;
            var namaInput = document.getElementById('edit-nama-peran');
            var hintInput = document.getElementById('edit-peran-hint');
            namaInput.value = nama;
            if (nama === 'Owner') {
                namaInput.readOnly = true;
                namaInput.style.background = '#f1f5f9';
                if (hintInput) hintInput.textContent = 'Peran Owner memiliki wewenang penuh dan nama tidak dapat diubah.';
            } else {
                namaInput.readOnly = false;
                namaInput.style.background = '#ffffff';
                if (hintInput) hintInput.textContent = '';
            }
            document.getElementById('edit-deskripsi-peran').value = deskripsi;
            document.getElementById('edit-akses-pos').checked = (pos == 1);
            document.getElementById('edit-akses-dash').checked = (dash == 1);
            document.getElementById('edit-akses-stok').checked = (stok == 1);
            document.getElementById('edit-akses-menu').checked = (menu == 1);
            document.getElementById('edit-akses-peng').checked = (peng == 1);

            var el = document.getElementById('modal-edit-peran');
            if (el) el.style.display = 'flex';
        }

        function tutupModalEditPeran() {
            var el = document.getElementById('modal-edit-peran');
            if (el) el.style.display = 'none';
        }

        function isiStokCepat(id, nama, satuan) {
            document.getElementById('select-id-bahan').value = id;
            document.getElementById('satuan-label').textContent = satuan;
            bukaModalStokMasuk();
        }
        var dataUsersMap = <?php echo json_encode($users_list); ?>;

        function toggleSidebar() {
            document.body.classList.toggle('sidebar-open');
        }
        function closeSidebar() {
            document.body.classList.remove('sidebar-open');
        }

        function toggleSubmenu(groupId) {
            var group = document.getElementById(groupId);
            if (group) {
                group.classList.toggle('open');
            }
        }

        function previewFotoUser(input) {
            if (input.files && input.files[0]) {
                var file = input.files[0];
                var label = document.getElementById('nama-file-user');
                if (label) label.textContent = 'File: ' + file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
                var reader = new FileReader();
                reader.onload = function(e) {
                    var preview = document.getElementById('setting-avatar-preview');
                    if (preview) {
                        preview.innerHTML = '<img src="' + e.target.result + '" style="width: 100%; height: 100%; object-fit: cover;">';
                    }
                };
                reader.readAsDataURL(file);
            }
        }

        function previewFotoSaya(input) {
            if (input.files && input.files[0]) {
                var file = input.files[0];
                var label = document.getElementById('nama-file-saya');
                if (label) label.textContent = 'File: ' + file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
                var reader = new FileReader();
                reader.onload = function(e) {
                    var p1 = document.getElementById('profil-saya-preview');
                    var p2 = document.getElementById('profil-saya-avatar-badge');
                    if (p1) p1.innerHTML = '<img src="' + e.target.result + '" style="width: 100%; height: 100%; object-fit: cover;">';
                    if (p2) p2.innerHTML = '<img src="' + e.target.result + '" style="width: 100%; height: 100%; object-fit: cover;">';
                };
                reader.readAsDataURL(file);
            }
        }

        function muatDataUserSetting(userId) {
            if (!dataUsersMap || dataUsersMap.length === 0) return;
            var u = dataUsersMap.find(function(item) { return item.id == userId; });
            if (!u) return;

            var idInput = document.getElementById('setting-id-user');
            var userInput = document.getElementById('setting-username');
            var namaInput = document.getElementById('setting-nama');
            var emailInput = document.getElementById('setting-email');
            var passInput = document.getElementById('setting-password');
            var selectUser = document.getElementById('setting-pilih-user');
            var roleBadge = document.getElementById('setting-role-badge');
            var avatarPreview = document.getElementById('setting-avatar-preview');
            var namaFileUser = document.getElementById('nama-file-user');
            var inputFotoUser = document.getElementById('input-foto-user');

            if (idInput) idInput.value = u.id;
            if (userInput) userInput.value = '@' + u.username;
            if (namaInput) namaInput.value = u.nama_lengkap || '';
            if (emailInput) emailInput.value = u.email || '';
            if (passInput) passInput.value = '';
            if (inputFotoUser) inputFotoUser.value = '';
            if (namaFileUser) namaFileUser.textContent = 'Klik tombol di atas untuk memilih foto';

            if (selectUser && selectUser.value != userId) {
                selectUser.value = userId;
            }

            if (avatarPreview) {
                if (u.foto) {
                    avatarPreview.style.background = 'transparent';
                    avatarPreview.innerHTML = '<img src="' + u.foto + '" style="width: 100%; height: 100%; object-fit: cover;">';
                } else {
                    var initChar = (u.nama_lengkap || u.username || 'U').charAt(0).toUpperCase();
                    var bgCol = (u.role === 'Owner') ? '#fef3c7' : ((u.role === 'Admin') ? '#e0f2fe' : '#dcfce7');
                    var textCol = (u.role === 'Owner') ? '#b45309' : ((u.role === 'Admin') ? '#0369a1' : '#15803d');
                    var borderCol = (u.role === 'Owner') ? '#fde68a' : ((u.role === 'Admin') ? '#bae6fd' : '#bbf7d0');
                    avatarPreview.style.background = bgCol;
                    avatarPreview.style.color = textCol;
                    avatarPreview.style.borderColor = borderCol;
                    avatarPreview.innerHTML = initChar;
                }
            }

            if (roleBadge) {
                if (u.role === 'Owner') {
                    roleBadge.className = 'badge badge-role-owner';
                    roleBadge.innerHTML = '<svg style="width:12px;height:12px;vertical-align:middle;flex-shrink:0;"><use href="#icon-crown"></use></svg> Owner (Pemilik Usaha)';
                } else if (u.role === 'Admin') {
                    roleBadge.className = 'badge badge-role-admin';
                    roleBadge.innerHTML = '<svg style="width:12px;height:12px;vertical-align:middle;flex-shrink:0;"><use href="#icon-settings"></use></svg> Admin (Staf Persediaan)';
                } else if (u.role === 'User') {
                    roleBadge.className = 'badge badge-role-user';
                    roleBadge.innerHTML = '<svg style="width:12px;height:12px;vertical-align:middle;flex-shrink:0;"><use href="#icon-user"></use></svg> User (Staf Operasional / POS)';
                } else if (u.role === 'Kasir') {
                    roleBadge.className = 'badge badge-role-kasir';
                    roleBadge.innerHTML = '<svg style="width:12px;height:12px;vertical-align:middle;flex-shrink:0;"><use href="#icon-cart"></use></svg> Kasir (Layar Kasir POS)';
                } else {
                    roleBadge.className = 'badge badge-role-custom';
                    roleBadge.innerHTML = '<svg style="width:12px;height:12px;vertical-align:middle;flex-shrink:0;"><use href="#icon-shield"></use></svg> ' + u.role;
                }
            }
        }

        function bukaUserSetting(userId) {
            pindahTab('user-setting');
            muatDataUserSetting(userId);
        }

        function pindahTab(tabName, ev) {
            if (ev) ev.preventDefault();

            // Ubah item navigasi yang aktif di sidebar
            document.querySelectorAll('.nav-menu .nav-item, .nav-menu .nav-subitem').forEach(function(item) {
                item.classList.remove('active');
            });
            var activeNav = document.querySelector('[data-tab="' + tabName + '"]');
            if (activeNav) activeNav.classList.add('active');

            // Jika membuka child Pengaturan, pastikan grup Pengaturan berstatus open
            var groupPengaturan = document.getElementById('nav-group-pengaturan');
            if (tabName === 'user-setting') {
                tabName = 'profil';
            }
            if (groupPengaturan) {
                var parentItem = groupPengaturan.querySelector('.nav-parent');
                if (tabName === 'pengguna' || tabName === 'peran' || tabName === 'profil') {
                    groupPengaturan.classList.add('open');
                    if (parentItem) parentItem.classList.add('active');
                } else {
                    if (parentItem) parentItem.classList.remove('active');
                }
            }

            // Sembunyikan semua tab view, tampilkan yang dipilih
            var views = document.querySelectorAll('.tab-view');
            views.forEach(function(v) {
                v.style.display = 'none';
            });
            var targetView = document.getElementById('view-' + tabName);
            if (targetView) {
                targetView.style.display = 'block';
            }

            // Perbarui judul halaman di Topbar
            var titleEl = document.getElementById('topbar-page-title');
            var subtitleEl = document.getElementById('topbar-page-subtitle');
            if (titleEl && subtitleEl) {
                if (tabName === 'dashboard') {
                    titleEl.textContent = 'Dashboard Manajemen';
                    subtitleEl.textContent = 'Ringkasan performa omzet bisnis, peringatan stok bahan baku, dan transaksi kasir';
                } else if (tabName === 'produk') {
                    titleEl.textContent = 'Katalog Menu & Resep';
                    subtitleEl.textContent = 'Kelola daftar menu makanan, minuman, harga jual, dan formula resep bahan baku';
                } else if (tabName === 'stok') {
                    titleEl.textContent = 'Persediaan Bahan Baku (Stok)';
                    subtitleEl.textContent = 'Pantau sisa stok bahan baku, catat kulakan stok masuk (inbound), dan bahan rusak (outbound)';
                } else if (tabName === 'pengguna') {
                    titleEl.textContent = 'Pengelolaan Pengguna';
                    subtitleEl.textContent = 'Daftar akun staf terdaftar, pendaftaran staf baru, dan kontrol akun';
                } else if (tabName === 'peran') {
                    titleEl.textContent = 'Peran & Hak Akses (RBAC)';
                    subtitleEl.textContent = 'Konfigurasi pembagian hak akses fitur dan penetapan peran staf (Owner, Admin, Kasir)';
                } else if (tabName === 'profil' || tabName === 'user-setting') {
                    titleEl.textContent = 'Profil Saya';
                    subtitleEl.textContent = 'Kelola identitas akun, foto profil, dan kata sandi keamanan Anda';
                }
            }

            // Sinkronisasi URL query string tanpa reload halaman
            if (window.history && window.history.pushState) {
                var newUrl = window.location.pathname + '?tab=' + tabName;
                window.history.pushState({tab: tabName}, '', newUrl);
            }

            // Tutup sidebar drawer jika di mobile
            if (typeof closeSidebar === 'function') {
                closeSidebar();
            }
        }

        window.addEventListener('popstate', function(e) {
            var params = new URLSearchParams(window.location.search);
            var tab = params.get('tab') || 'dashboard';
            pindahTab(tab);
        });

        // Inisialisasi awal form User Setting & Peran
        document.addEventListener('DOMContentLoaded', function() {
            var selectUser = document.getElementById('setting-pilih-user');
            if (selectUser && selectUser.value) {
                muatDataUserSetting(selectUser.value);
            } else if (typeof dataUsersMap !== 'undefined' && dataUsersMap.length > 0) {
                muatDataUserSetting(dataUsersMap[0].id);
            }

            var selectPeranUser = document.getElementById('peran-pilih-user');
            if (selectPeranUser && selectPeranUser.value) {
                updatePeranSelect(selectPeranUser.value);
            }
        });
        /* ---- Preview Gambar Upload ---- */
                function previewFotoProfilSaya(input) {
            if (input.files && input.files[0]) {
                var file = input.files[0];
                if (!file.type.startsWith('image/')) {
                    alert('File harus berupa gambar (JPG, PNG, WebP).');
                    input.value = '';
                    return;
                }
                if (file.size > 2 * 1024 * 1024) {
                    alert('Ukuran foto profil maksimal 2 MB.');
                    input.value = '';
                    return;
                }
                var reader = new FileReader();
                reader.onload = function(e) {
                    var elLeft = document.getElementById('profil-avatar-display');
                    var elForm = document.getElementById('profil-form-avatar-preview');
                    var nameEl = document.getElementById('nama-file-profil-saya');
                    if (elLeft) {
                        elLeft.style.background = 'transparent';
                        elLeft.innerHTML = '<img src="' + e.target.result + '" style="width:100%; height:100%; object-fit:cover;">';
                    }
                    if (elForm) {
                        elForm.style.background = 'transparent';
                        elForm.innerHTML = '<img src="' + e.target.result + '" style="width:100%; height:100%; object-fit:cover;">';
                    }
                    if (nameEl) {
                        nameEl.textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
                        nameEl.style.color = 'var(--primary)';
                        nameEl.style.fontWeight = '700';
                    }
                };
                reader.readAsDataURL(file);
            }
        }

        function previewGambar(input, previewId) {
            const previewEl = document.getElementById(previewId);
            if (!input.files || !input.files[0]) return;
            const file = input.files[0];
            // Validasi di sisi klien
            if (!file.type.startsWith('image/')) {
                alert('File harus berupa gambar (JPG, PNG, WebP, GIF).');
                input.value = '';
                return;
            }
            if (file.size > 2 * 1024 * 1024) {
                alert('Ukuran gambar maksimal 2 MB.');
                input.value = '';
                return;
            }
            const reader = new FileReader();
            reader.onload = function(e) {
                previewEl.innerHTML = `<img src="${e.target.result}" alt="Preview" style="width:100%;height:100%;object-fit:cover;border-radius:10px;">`;
            };
            reader.readAsDataURL(file);
        }

        function resetPreview(previewId, emoji) {
            const el = document.getElementById(previewId);
            if (el) {
                el.innerHTML = `<span class="img-upload-icon">${emoji}</span><span class="img-upload-hint">Klik untuk pilih foto</span>`;
            }
        }

        function updateDefaultPreviewProduk(kategori) {
            // Hanya update default icon jika belum ada gambar dipilih
            const input = document.getElementById('input-gambar-produk');
            if (!input.files || !input.files[0]) {
                const icon = kategori === 'Minuman' ? 'Minuman' : 'Makanan';
                resetPreview('preview-produk', icon);
            }
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

        function tutupModalFotoHD(event) {
            if (event && event.target && event.target !== event.currentTarget) return;
            const modal = document.getElementById('modal-foto-hd');
            if (modal) {
                modal.style.display = 'none';
                modal.classList.remove('active');
                document.body.style.overflow = '';
            }
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') tutupModalFotoHD();
        });
    </script>

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
                        <div style="font-size:11px; color:#64748b; font-weight:600;">Harga Satuan</div>
                        <div id="hd-modal-price" class="hd-modal-price" style="font-size:18px; font-weight:800; color:#ea580c;">Rp 0</div>
                    </div>
                    <div style="font-size:11.5px; color:#16a34a; font-weight:700; display:flex; align-items:center; gap:4px;">
                        <span style="width:7px; height:7px; background:#16a34a; border-radius:50%; display:inline-block;"></span> Katalog Menu
                    </div>
                </div>
                
                <div class="hd-modal-actions" style="display:flex; gap:10px; margin-top:4px;">
                    <button type="button" class="btn-hd-action" onclick="tutupModalFotoHD()" style="flex:1; background:#ea580c; color:#ffffff; border:none; padding:10px 16px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; gap:6px; box-shadow:0 4px 12px -2px rgba(234,88,12,0.4);">
                        <span>Tutup Pratinjau</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
