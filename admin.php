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

$pesan_sukses = "";
$pesan_error = "";

// Status Tab Aktif (Dashboard, Pengguna, Peran, atau User Setting)
$tab_aktif = isset($_GET['tab']) ? trim($_GET['tab']) : 'dashboard';
if (isset($_POST['tambah_pengguna']) || isset($_POST['edit_pengguna']) || isset($_POST['hapus_pengguna'])) {
    $tab_aktif = 'pengguna';
} elseif (isset($_POST['tambah_peran']) || isset($_POST['edit_peran']) || isset($_POST['hapus_peran']) || isset($_POST['ubah_peran_pengguna'])) {
    $tab_aktif = 'peran';
} elseif (isset($_POST['simpan_setting_user']) || isset($_POST['simpan_profil_saya'])) {
    $tab_aktif = 'user-setting';
} elseif (isset($_POST['tambah_stok_masuk']) || isset($_POST['tambah_bahan_baru']) || isset($_POST['tambah_produk_baru'])) {
    $tab_aktif = 'dashboard';
}
if (in_array($tab_aktif, ['pengguna', 'peran', 'user-setting']) && !is_owner()) {
    $tab_aktif = 'dashboard';
}

// --- Migrasi Otomatis: Tambah kolom gambar jika belum ada (kompatibel MySQL 5.x) ---
$db_name = $conn->query("SELECT DATABASE()")->fetch_row()[0];
$chk_produk = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='$db_name' AND TABLE_NAME='produk' AND COLUMN_NAME='gambar'")->num_rows;
if ($chk_produk === 0) $conn->query("ALTER TABLE produk ADD COLUMN gambar VARCHAR(255) NULL");
$chk_bahan = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='$db_name' AND TABLE_NAME='bahan_baku' AND COLUMN_NAME='gambar'")->num_rows;
if ($chk_bahan === 0) $conn->query("ALTER TABLE bahan_baku ADD COLUMN gambar VARCHAR(255) NULL");
$chk_user_foto = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='$db_name' AND TABLE_NAME='users' AND COLUMN_NAME='foto'")->num_rows;
if ($chk_user_foto === 0) $conn->query("ALTER TABLE users ADD COLUMN foto VARCHAR(255) NULL");

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

// 1. Logika Tambah Stok Masuk (Inbound Stock - saat belanja bahan baku)
if (isset($_POST['tambah_stok_masuk'])) {
    $id_bahan = intval($_POST['id_bahan']);
    $jumlah_masuk = floatval($_POST['jumlah_masuk']);
    $catatan = trim($_POST['catatan_masuk']);

    if ($jumlah_masuk > 0) {
        $stmt_in = $conn->prepare("UPDATE bahan_baku SET stok = stok + ? WHERE id = ?");
        $stmt_in->bind_param("di", $jumlah_masuk, $id_bahan);
        if ($stmt_in->execute()) {
            $pesan_sukses = "Berhasil menambahkan stok bahan baku sebanyak $jumlah_masuk!";
        } else {
            $pesan_error = "Gagal memperbarui stok: " . $conn->error;
        }
    } else {
        $pesan_error = "Jumlah stok masuk harus lebih dari 0.";
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
            $pesan_sukses = "Bahan baku baru '$nama_bahan' berhasil didaftarkan!";
        } else {
            $pesan_error = "Gagal menambahkan bahan baku: " . $conn->error;
        }
    }
}

// 2b. Logika Tambah Menu Produk Baru (Makanan / Minuman)
if (isset($_POST['tambah_produk_baru'])) {
    $nama_produk = trim($_POST['nama_produk']);
    $harga       = floatval($_POST['harga']);
    $kategori    = trim($_POST['kategori']);

    if (!empty($nama_produk) && $harga > 0) {
        $gambar_produk = prosesUploadGambar('gambar_produk', 'produk');
        $stmt_p = $conn->prepare("INSERT INTO produk (nama_produk, harga, kategori, gambar) VALUES (?, ?, ?, ?)");
        $stmt_p->bind_param("sdss", $nama_produk, $harga, $kategori, $gambar_produk);
        if ($stmt_p->execute()) {
            $pesan_sukses = "Menu produk baru '$nama_produk' berhasil ditambahkan ke daftar kasir!";
        } else {
            $pesan_error = "Gagal menambahkan menu produk: " . $conn->error;
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

    // 2e. Simpan Profil, Kata Sandi, & Foto Profil Saya (Owner)
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

// 3. Ambil Data Metrik KPI
// Omzet Hari Ini
$q_today = $conn->query("SELECT COALESCE(SUM(total_pembayaran), 0) AS omzet_today, COUNT(*) AS trx_today FROM transaksi WHERE DATE(tanggal_transaksi) = CURDATE()");
$kpi_today = $q_today->fetch_assoc();

// Total Seluruh Transaksi & Omzet Akumulasi
$q_all = $conn->query("SELECT COALESCE(SUM(total_pembayaran), 0) AS total_omzet, COUNT(*) AS total_trx FROM transaksi");
$kpi_all = $q_all->fetch_assoc();

// Peringatan Stok Menipis (Stok <= Batas Minimum)
$q_alert = $conn->query("SELECT COUNT(*) AS total_menipis FROM bahan_baku WHERE stok <= batas_minimum");
$kpi_alert = $q_alert->fetch_assoc();

// Ambil Seluruh Data Bahan Baku
$bahan_baku_list = [];
$res_bahan = $conn->query("SELECT * FROM bahan_baku ORDER BY nama_bahan ASC");
if ($res_bahan) {
    while ($b = $res_bahan->fetch_assoc()) {
        $bahan_baku_list[] = $b;
    }
}

// Ambil Riwayat Transaksi Terbaru (Limit 10)
$transaksi_terbaru = [];
$res_trx = $conn->query("SELECT * FROM transaksi ORDER BY id DESC LIMIT 10");
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

    if (isset($_SESSION['id_user'])) {
        $stmt_cu = $conn->prepare("SELECT email, foto FROM users WHERE id = ?");
        $stmt_cu->bind_param("i", $_SESSION['id_user']);
        $stmt_cu->execute();
        $r_cu = $stmt_cu->get_result()->fetch_assoc();
        if ($r_cu) {
            $current_user_email = $r_cu['email'] ?? '';
            $current_user_foto  = $r_cu['foto'] ?? '';
            $_SESSION['foto']   = $current_user_foto;
        }
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
    <link rel="stylesheet" href="style.css">
    <style>
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
            padding: 10px;
            border-radius: 10px;
            font-size: 12.5px;
            font-weight: 700;
            text-align: center;
            text-decoration: none;
            display: block;
            margin-bottom: 8px;
            transition: background 0.15s;
        }

        .btn-switch-mode:hover {
            background: #0284c7;
            color: #ffffff;
        }

        .btn-logout {
            width: 100%;
            background: rgba(220, 38, 38, 0.15);
            color: #f87171;
            border: 1px solid rgba(220, 38, 38, 0.3);
            padding: 10px;
            border-radius: 10px;
            font-size: 12.5px;
            font-weight: 700;
            text-align: center;
            text-decoration: none;
            display: block;
            transition: all 0.15s;
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

        /* Top Header */
        .topbar {
            background: #ffffff;
            border-bottom: 1.5px solid var(--border);
            padding: clamp(12px, 2vw, 16px) clamp(16px, 3vw, 32px);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
            gap: 12px;
            box-shadow: var(--shadow-sm);
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

        /* KPI Metric Cards */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: clamp(12px, 2vw, 18px);
        }

        .kpi-card {
            background: var(--bg-card);
            border: 1.5px solid var(--border);
            border-radius: var(--radius-lg);
            padding: clamp(14px, 2vw, 20px);
            box-shadow: var(--shadow-sm);
            position: relative;
            overflow: hidden;
        }

        .kpi-title {
            font-size: 11.5px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .kpi-value {
            font-size: clamp(20px, 3.5vw, 24px);
            font-weight: 800;
            color: var(--text-dark);
            margin: 6px 0 4px 0;
        }

        .kpi-desc {
            font-size: 11.5px;
            color: var(--text-muted);
        }

        .kpi-card.alert-card {
            border-color: #fde68a;
            background: #fffbeb;
        }

        .kpi-card.alert-card .kpi-value {
            color: var(--warning);
        }

        /* Layout 2 Kolom: Persediaan Stok & Riwayat */
        .section-grid {
            display: grid;
            grid-template-columns: 1.4fr 1fr;
            gap: clamp(16px, 2.5vw, 24px);
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
            padding: 10px 12px;
            border-bottom: 1.5px solid var(--border);
            font-size: 11px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border);
            color: var(--text-dark);
            white-space: nowrap;
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
    <!-- Overlay Backdrop untuk Mobile Sidebar Drawer -->
    <div class="sidebar-overlay" onclick="closeSidebar()"></div>

    <!-- Sidebar Kiri -->
    <aside class="sidebar">
        <div>
            <div class="brand-section">
                <div class="brand-brand-wrap">
                    <div class="brand-icon">🍗</div>
                    <div class="brand-details">
                        <h2>Fillah Food</h2>
                        <p>Dashboard Manajemen</p>
                    </div>
                </div>
                <button type="button" class="btn-close-sidebar" onclick="closeSidebar()" title="Tutup Menu">✕</button>
            </div>

            <nav class="nav-menu">
                <a href="admin.php?tab=dashboard" data-tab="dashboard" class="nav-item <?php echo ($tab_aktif === 'dashboard') ? 'active' : ''; ?>" onclick="pindahTab('dashboard', event)">
                    <span>📊</span> Dashboard
                </a>
                <?php if (is_owner()): ?>
                <div class="nav-group <?php echo in_array($tab_aktif, ['pengguna', 'peran', 'user-setting']) ? 'open' : ''; ?>" id="nav-group-pengaturan">
                    <div class="nav-item nav-parent <?php echo in_array($tab_aktif, ['pengguna', 'peran', 'user-setting']) ? 'active' : ''; ?>" onclick="toggleSubmenu('nav-group-pengaturan')">
                        <div class="nav-parent-title">
                            <span>⚙️</span> Pengaturan
                        </div>
                        <span class="nav-arrow">▼</span>
                    </div>
                    <div class="nav-submenu">
                        <a href="admin.php?tab=pengguna" data-tab="pengguna" class="nav-subitem <?php echo ($tab_aktif === 'pengguna') ? 'active' : ''; ?>" onclick="pindahTab('pengguna', event)">
                            <span>👥</span> Pengelolaan Pengguna
                        </a>
                        <a href="admin.php?tab=peran" data-tab="peran" class="nav-subitem <?php echo ($tab_aktif === 'peran') ? 'active' : ''; ?>" onclick="pindahTab('peran', event)">
                            <span>🛡️</span> Peran
                        </a>
                        <a href="admin.php?tab=user-setting" data-tab="user-setting" class="nav-subitem <?php echo ($tab_aktif === 'user-setting') ? 'active' : ''; ?>" onclick="pindahTab('user-setting', event)">
                            <span>👤</span> User Setting
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                <a href="menu.php" target="_blank" class="nav-item">
                    <span>📱</span> E-Menu Pelanggan
                </a>
            </nav>
        </div>

        <div class="sidebar-footer">
            <a href="kasir.php" class="btn-switch-mode">
                🛒 Buka Layar Kasir POS
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
                <button type="button" class="btn-hamburger" onclick="toggleSidebar()" aria-label="Buka Menu">☰</button>
                <div class="topbar-title">
                    <h1 id="topbar-page-title">
                        <?php 
                            if ($tab_aktif === 'pengguna') echo 'Pengelolaan Pengguna';
                            elseif ($tab_aktif === 'peran') echo 'Peran & Hak Akses (RBAC)';
                            elseif ($tab_aktif === 'user-setting') echo 'User Setting & Profil Akun';
                            else echo 'Dashboard Manajemen & Persediaan'; 
                        ?>
                    </h1>
                    <p id="topbar-page-subtitle">
                        <?php 
                            if ($tab_aktif === 'pengguna') echo 'Daftar akun staf terdaftar, pendaftaran staf baru, dan kontrol akun';
                            elseif ($tab_aktif === 'peran') echo 'Konfigurasi pembagian hak akses fitur dan penetapan peran staf (Owner, Admin, Kasir)';
                            elseif ($tab_aktif === 'user-setting') echo 'Pengaturan kredensial akun staf dan profil pribadi';
                            else echo 'Sistem Informasi Terintegrasi UMKM Fillah Food - Capstone Project STSI4440 UT'; 
                        ?>
                    </p>
                </div>
            </div>

            <div class="admin-profile">
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
                            <span class="badge badge-role-owner" style="font-size: 10px; padding: 2px 8px;">👑 Owner (Pemilik Usaha)</span>
                        <?php else: ?>
                            <span class="badge badge-role-admin" style="font-size: 10px; padding: 2px 8px;">⚙️ Admin (Staf Persediaan)</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </header>

        <!-- Body Content -->
        <div class="content-body">

            <?php if (!empty($pesan_sukses)): ?>
                <div class="alert-box alert-success">✅ <?php echo htmlspecialchars($pesan_sukses); ?></div>
            <?php endif; ?>

            <?php if (!empty($pesan_error)): ?>
                <div class="alert-box alert-danger">⚠️ <?php echo htmlspecialchars($pesan_error); ?></div>
            <?php endif; ?>

            <!-- TAB VIEW 1: DASHBOARD (Ringkasan Omzet, Stok Bahan & Transaksi) -->
            <div id="view-dashboard" class="tab-view" style="<?php echo ($tab_aktif === 'dashboard') ? '' : 'display: none;'; ?>">

                <!-- KPI Cards -->
                <div class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-title">Omzet Penjualan Hari Ini</div>
                    <div class="kpi-value" style="color: var(--primary);">
                        Rp <?php echo number_format($kpi_today['omzet_today'], 0, ',', '.'); ?>
                    </div>
                    <div class="kpi-desc">Dari <?php echo $kpi_today['trx_today']; ?> transaksi kasir hari ini</div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-title">Total Pendapatan Akumulasi</div>
                    <div class="kpi-value">
                        Rp <?php echo number_format($kpi_all['total_omzet'], 0, ',', '.'); ?>
                    </div>
                    <div class="kpi-desc">Total <?php echo $kpi_all['total_trx']; ?> transaksi tercatat di database</div>
                </div>

                <div class="kpi-card <?php echo ($kpi_alert['total_menipis'] > 0) ? 'alert-card' : ''; ?>">
                    <div class="kpi-title">Peringatan Bahan Menipis</div>
                    <div class="kpi-value">
                        <?php echo $kpi_alert['total_menipis']; ?> Bahan
                    </div>
                    <div class="kpi-desc">
                        <?php echo ($kpi_alert['total_menipis'] > 0) ? '⚠️ Segera lakukan belanja bahan ke pasar!' : 'Semua persediaan bahan baku aman.'; ?>
                    </div>
                </div>
            </div>

            <!-- 2 Kolom: Manajemen Persediaan & Riwayat Transaksi -->
            <div class="section-grid">
                
                <!-- Kolom Kiri: Tabel Stok Persediaan Bahan Baku -->
                <div class="panel-card" id="stok-persediaan">
                    <div class="panel-header">
                        <h2><span>📦</span> Persediaan Bahan Baku (Inventory)</h2>
                        <div style="display: flex; gap: 8px;">
                            <button class="btn-primary-sm" style="background:var(--secondary); color:#000;" onclick="bukaModalProdukBaru()">+ Menu Produk Baru</button>
                            <button class="btn-primary-sm" onclick="bukaModalStokMasuk()">+ Stok Masuk</button>
                            <button class="btn-primary-sm" style="background:#475569;" onclick="bukaModalBahanBaru()">+ Bahan Baku</button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Nama Bahan</th>
                                    <th>Stok Saat Ini</th>
                                    <th>Batas Minimum</th>
                                    <th>Status Stok</th>
                                    <th>Aksi</th>
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
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($b['nama_bahan']); ?></strong></td>
                                        <td>
                                            <span style="font-size: 15px; font-weight: 800;"><?php echo rtrim(rtrim(number_format($stok, 2, '.', ''), '0'), '.'); ?></span> 
                                            <?php echo htmlspecialchars($b['satuan']); ?>
                                        </td>
                                        <td style="color: var(--text-muted);">
                                            <?php echo rtrim(rtrim(number_format($min, 2, '.', ''), '0'), '.'); ?> <?php echo htmlspecialchars($b['satuan']); ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $badge_class; ?>"><?php echo $badge_label; ?></span>
                                        </td>
                                        <td>
                                            <button style="background: none; border: 1px solid var(--border); padding: 4px 8px; border-radius: 6px; cursor: pointer; font-size: 11px; font-weight: 700;" onclick="isiStokCepat(<?php echo $b['id']; ?>, '<?php echo addslashes($b['nama_bahan']); ?>', '<?php echo $b['satuan']; ?>')">
                                                + Tambah
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Kolom Kanan: Riwayat Transaksi Kasir -->
                <div class="panel-card" id="riwayat-transaksi">
                    <div class="panel-header">
                        <h2><span>🧾</span> Transaksi Kasir Terakhir</h2>
                        <span style="font-size: 11px; color: var(--text-muted);">10 Transaksi Terakhir</span>
                    </div>

                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>No. Nota</th>
                                    <th>Waktu</th>
                                    <th>Total Tagihan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($transaksi_terbaru)): ?>
                                    <tr>
                                        <td colspan="3" style="text-align: center; color: var(--text-muted); padding: 20px;">
                                            Belum ada transaksi penjualan yang tercatat.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($transaksi_terbaru as $trx): ?>
                                        <tr>
                                            <td>
                                                <strong>#TRX-<?php echo str_pad($trx['id'], 4, "0", STR_PAD_LEFT); ?></strong>
                                            </td>
                                            <td style="font-size: 11.5px; color: var(--text-muted);">
                                                <?php echo date('d/m/Y H:i', strtotime($trx['tanggal_transaksi'])); ?>
                                            </td>
                                            <td style="font-weight: 800; color: var(--primary);">
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

        <!-- TAB VIEW 2: KELOLA PENGGUNA (Khusus Role Owner) -->
        <?php if (is_owner()): ?>
        <div id="view-pengguna" class="tab-view" style="<?php echo ($tab_aktif === 'pengguna') ? '' : 'display: none;'; ?>">
            <div class="panel-card" id="kelola-pengguna">
                <div class="panel-header">
                    <div>
                        <h2><span>👥</span> Kelola Pengguna & Hak Akses</h2>
                        <p style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                            Pengaturan akun pengguna, kata sandi, dan pembagian hak akses (Owner, Admin, Kasir).
                        </p>
                    </div>
                    <button class="btn-primary-sm" style="background: linear-gradient(135deg, var(--primary), var(--secondary)); display: flex; align-items: center; gap: 6px;" onclick="bukaModalPenggunaBaru()">
                        <span>➕</span> Tambah Pengguna Baru
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
                                <th>Aksi</th>
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
                                            <span class="badge badge-role-owner">👑 Owner</span>
                                        <?php elseif ($u['role'] === 'Admin'): ?>
                                            <span class="badge badge-role-admin">⚙️ Admin</span>
                                        <?php elseif ($u['role'] === 'User'): ?>
                                            <span class="badge badge-role-user">👤 User</span>
                                        <?php elseif ($u['role'] === 'Kasir'): ?>
                                            <span class="badge badge-role-kasir">🛒 Kasir</span>
                                        <?php else: ?>
                                            <span class="badge badge-role-custom">🛡️ <?php echo htmlspecialchars($u['role']); ?></span>
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
                                    <td>
                                        <div style="display: flex; gap: 6px;">
                                            <button type="button" style="background: #f1f5f9; border: 1px solid var(--border); padding: 5px 10px; border-radius: 6px; cursor: pointer; font-size: 11.5px; font-weight: 700; color: var(--text-dark);"
                                                onclick="bukaModalEditPengguna(<?php echo $u['id']; ?>, '<?php echo addslashes($u['nama_lengkap']); ?>', '<?php echo addslashes($u['username']); ?>', '<?php echo addslashes($u['email']); ?>', '<?php echo $u['role']; ?>')">
                                                ✏️ Edit
                                            </button>
                                            <button type="button" style="background: #e0f2fe; border: 1px solid #bae6fd; color: #0369a1; padding: 5px 10px; border-radius: 6px; cursor: pointer; font-size: 11.5px; font-weight: 700;"
                                                onclick="bukaUserSetting(<?php echo $u['id']; ?>)">
                                                ⚙️ Setting
                                            </button>
                                            <?php if (!isset($_SESSION['id_user']) || $u['id'] != $_SESSION['id_user']): ?>
                                                <form method="POST" action="" onsubmit="return confirm('Apakah Anda yakin ingin menghapus akun @<?php echo addslashes($u['username']); ?>?');" style="display: inline;">
                                                    <input type="hidden" name="id_user_hapus" value="<?php echo $u['id']; ?>">
                                                    <button type="submit" name="hapus_pengguna" style="background: #fee2e2; border: 1px solid #fecaca; color: #dc2626; padding: 5px 10px; border-radius: 6px; cursor: pointer; font-size: 11.5px; font-weight: 700;">
                                                        🗑️ Hapus
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <button disabled style="background: #f1f5f9; border: 1px solid var(--border); color: #94a3b8; padding: 5px 10px; border-radius: 6px; font-size: 11.5px; cursor: not-allowed;" title="Tidak dapat menghapus akun sendiri">
                                                    🔒 Aktif
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
                        <h2><span>🛡️</span> Daftar Peran & Hak Akses (RBAC)</h2>
                        <p style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                            Daftar peran sistem UMKM Fillah Food beserta rincian wewenang hak akses modul operasional.
                        </p>
                    </div>
                    <button type="button" class="btn-primary-sm" onclick="bukaModalPeranBaru()" style="padding: 10px 16px; font-size: 12.5px; font-weight: 700;">
                        ➕ Tambah Peran Baru
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
                                <th style="text-align: center; width: 140px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($roles_list as $r): 
                                $r_count = $role_user_counts[$r['nama_peran']] ?? 0;
                            ?>
                                <tr>
                                    <td>
                                        <?php if ($r['nama_peran'] === 'Owner'): ?>
                                            <span class="badge badge-role-owner" style="font-size: 12px; font-weight: 800;">👑 Owner</span>
                                        <?php elseif ($r['nama_peran'] === 'Admin'): ?>
                                            <span class="badge badge-role-admin" style="font-size: 12px; font-weight: 800;">⚙️ Admin</span>
                                        <?php elseif ($r['nama_peran'] === 'User'): ?>
                                            <span class="badge badge-role-user" style="font-size: 12px; font-weight: 800;">👤 User</span>
                                        <?php else: ?>
                                            <span class="badge badge-role-custom" style="font-size: 12px; font-weight: 800;">🛡️ <?php echo htmlspecialchars($r['nama_peran']); ?></span>
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
                                                <span class="badge badge-safe" style="font-size: 10.5px;">✓ POS Kasir</span>
                                            <?php endif; ?>
                                            <?php if ($r['akses_dashboard']): ?>
                                                <span class="badge badge-safe" style="font-size: 10.5px;">✓ Dashboard</span>
                                            <?php endif; ?>
                                            <?php if ($r['akses_stok']): ?>
                                                <span class="badge badge-safe" style="font-size: 10.5px;">✓ Stok Masuk</span>
                                            <?php endif; ?>
                                            <?php if ($r['akses_menu']): ?>
                                                <span class="badge badge-safe" style="font-size: 10.5px;">✓ Kelola Menu</span>
                                            <?php endif; ?>
                                            <?php if ($r['akses_pengguna']): ?>
                                                <span class="badge badge-safe" style="font-size: 10.5px;">✓ Kelola Pengguna</span>
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
                                    <td style="text-align: center;">
                                        <div style="display: flex; gap: 6px; justify-content: center;">
                                            <button type="button" style="background: #f1f5f9; border: 1px solid var(--border); padding: 5px 10px; border-radius: 6px; cursor: pointer; font-size: 11.5px; font-weight: 700; color: var(--text-dark);"
                                                onclick="bukaModalEditPeran(<?php echo $r['id']; ?>, '<?php echo addslashes($r['nama_peran']); ?>', '<?php echo addslashes($r['deskripsi']); ?>', <?php echo (int)$r['akses_pos']; ?>, <?php echo (int)$r['akses_dashboard']; ?>, <?php echo (int)$r['akses_stok']; ?>, <?php echo (int)$r['akses_menu']; ?>, <?php echo (int)$r['akses_pengguna']; ?>)">
                                                ✏️ Edit
                                            </button>

                                            <?php if (!in_array($r['nama_peran'], ['Owner', 'Admin', 'User'])): ?>
                                                <form method="POST" action="" onsubmit="return confirm('Apakah Anda yakin ingin menghapus peran <?php echo addslashes($r['nama_peran']); ?>?');" style="display: inline;">
                                                    <input type="hidden" name="id_role_hapus" value="<?php echo $r['id']; ?>">
                                                    <button type="submit" name="hapus_peran" style="background: #fee2e2; border: 1px solid #fecaca; color: #dc2626; padding: 5px 10px; border-radius: 6px; cursor: pointer; font-size: 11.5px; font-weight: 700;">
                                                        🗑️ Hapus
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span style="font-size: 11px; color: #94a3b8; padding: 5px 4px; font-weight: 600;" title="Peran inti sistem tidak dapat dihapus">🔒 Sistem</span>
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

        <!-- TAB VIEW 3: USER SETTING (Khusus Role Owner) -->
        <div id="view-user-setting" class="tab-view" style="<?php echo ($tab_aktif === 'user-setting') ? '' : 'display: none;'; ?>">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px;">
                
                <!-- Card 1: Pengaturan Akun Staf & Pengguna oleh Owner -->
                <div class="panel-card">
                    <div class="panel-header" style="border-bottom: 1.5px solid var(--border); padding-bottom: 14px; margin-bottom: 18px;">
                        <div>
                            <h2><span>⚙️</span> Pengaturan Akun Pengguna</h2>
                            <p style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                                Sebagai Owner, Anda dapat mengatur profil, email, dan mereset kata sandi staf.
                            </p>
                        </div>
                    </div>

                    <div class="form-group" style="background: #f8fafc; padding: 14px; border-radius: 10px; border: 1.5px solid var(--border); margin-bottom: 18px;">
                        <label class="form-label" style="font-weight: 700; color: var(--text-dark); margin-bottom: 6px;">
                            Pilih Pengguna yang Ingin Diatur:
                        </label>
                        <select id="setting-pilih-user" class="form-control" onchange="muatDataUserSetting(this.value)" style="font-weight: 600;">
                            <?php foreach ($users_list as $u): ?>
                                <option value="<?php echo $u['id']; ?>">
                                    <?php echo htmlspecialchars($u['nama_lengkap'] ?: $u['username']); ?> (@<?php echo htmlspecialchars($u['username']); ?>) — [<?php echo $u['role']; ?>]
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="id_user_setting" id="setting-id-user" value="<?php echo !empty($users_list) ? $users_list[0]['id'] : ''; ?>">

                        <div class="form-group">
                            <label class="form-label">📷 Foto Profil Pengguna <span style="font-weight:400;color:#64748b;">(Opsional — JPG/PNG/WebP, maks 2 MB)</span></label>
                            <div style="display: flex; align-items: center; gap: 16px; margin-top: 6px; background: #f8fafc; padding: 12px; border-radius: 10px; border: 1.5px solid var(--border);">
                                <div id="setting-avatar-preview" style="width: 60px; height: 60px; border-radius: 50%; overflow: hidden; display: flex; align-items: center; justify-content: center; font-size: 22px; font-weight: 800; border: 2px solid var(--border); flex-shrink: 0; box-shadow: 0 2px 6px rgba(0,0,0,0.06);
                                    <?php 
                                        $u0_role = !empty($users_list) ? $users_list[0]['role'] : 'User';
                                        if ($u0_role === 'Owner') echo 'background: #fef3c7; color: #b45309; border-color: #fde68a;';
                                        elseif ($u0_role === 'Admin') echo 'background: #e0f2fe; color: #0369a1; border-color: #bae6fd;';
                                        else echo 'background: #dcfce7; color: #15803d; border-color: #bbf7d0;';
                                    ?>">
                                    <?php if (!empty($users_list[0]['foto']) && file_exists(__DIR__ . '/' . $users_list[0]['foto'])): ?>
                                        <img src="<?php echo htmlspecialchars($users_list[0]['foto']); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <?php echo strtoupper(substr(!empty($users_list) ? ($users_list[0]['nama_lengkap'] ?: $users_list[0]['username']) : 'U', 0, 1)); ?>
                                    <?php endif; ?>
                                </div>
                                <div style="flex: 1;">
                                    <button type="button" class="btn-primary-sm" style="background: #ffffff; color: var(--text-dark); border: 1.5px solid var(--border); padding: 7px 14px; font-size: 12px; font-weight: 700;" onclick="document.getElementById('input-foto-user').click()">
                                        📁 Pilih Foto Baru
                                    </button>
                                    <input type="file" id="input-foto-user" name="foto_user_setting" accept="image/*" style="display: none;" onchange="previewFotoUser(this)">
                                    <div id="nama-file-user" style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Klik tombol di atas untuk memilih foto</div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Username (ID Akun)</label>
                            <input type="text" id="setting-username" class="form-control" disabled style="background: #f1f5f9; cursor: not-allowed; font-family: monospace; font-weight: 600;" value="<?php echo !empty($users_list) ? '@' . htmlspecialchars($users_list[0]['username']) : ''; ?>">
                            <small style="color: var(--text-muted); font-size: 11px;">Username bersifat permanen untuk integritas riwayat transaksi.</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Nama Lengkap Pengguna</label>
                            <input type="text" name="nama_lengkap_setting" id="setting-nama" class="form-control" required value="<?php echo !empty($users_list) ? htmlspecialchars($users_list[0]['nama_lengkap']) : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Alamat Email Pengguna</label>
                            <input type="email" name="email_setting" id="setting-email" class="form-control" placeholder="user@fillahfood.com" value="<?php echo !empty($users_list) ? htmlspecialchars($users_list[0]['email'] ?? '') : ''; ?>">
                            <small style="color: var(--text-muted); font-size: 11px;">Digunakan untuk verifikasi dan pengamanan login OTP email.</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Peran Hak Akses Akun</label>
                            <div style="padding: 6px 0;">
                                <?php 
                                    $first_role = !empty($users_list) ? $users_list[0]['role'] : 'User';
                                    if ($first_role === 'Owner'): ?>
                                        <span class="badge badge-role-owner" id="setting-role-badge">👑 Owner (Pemilik Usaha)</span>
                                    <?php elseif ($first_role === 'Admin'): ?>
                                        <span class="badge badge-role-admin" id="setting-role-badge">⚙️ Admin (Staf Persediaan)</span>
                                    <?php elseif ($first_role === 'User'): ?>
                                        <span class="badge badge-role-user" id="setting-role-badge">👤 User (Staf Operasional / POS)</span>
                                    <?php else: ?>
                                        <span class="badge badge-role-custom" id="setting-role-badge">🛡️ <?php echo htmlspecialchars($first_role); ?></span>
                                    <?php endif; ?>
                            </div>
                            <small style="color: var(--text-muted); font-size: 11px;">
                                🔒 Peran akun dikunci di User Setting. Pengaturan peran hanya dapat diubah melalui menu <strong>Pengelolaan Pengguna</strong> atau <strong>Peran</strong>.
                            </small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Reset / Atur Kata Sandi Baru</label>
                            <input type="password" name="password_setting" id="setting-password" class="form-control" placeholder="Ketik kata sandi baru (kosongkan jika tidak diubah)">
                            <small style="color: var(--text-muted); font-size: 11px;">Owner dapat langsung mengganti kata sandi staf jika staf lupa password.</small>
                        </div>

                        <div style="margin-top: 20px;">
                            <button type="submit" name="simpan_setting_user" class="btn-primary-sm" style="padding: 12px 20px; width: 100%; justify-content: center; font-size: 13px;">
                                💾 Simpan Pengaturan Pengguna
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Card 2: Profil & Sandi Saya (Owner) -->
                <div class="panel-card">
                    <div class="panel-header" style="border-bottom: 1.5px solid var(--border); padding-bottom: 14px; margin-bottom: 18px;">
                        <div>
                            <h2><span>👤</span> Profil & Sandi Saya (Owner)</h2>
                            <p style="font-size: 12px; color: var(--text-muted); margin-top: 4px;">
                                Pengaturan profil dan kata sandi akun Owner Anda yang sedang aktif.
                            </p>
                        </div>
                    </div>

                    <div style="background: #fffbeb; border: 1.5px solid #fde68a; border-radius: 12px; padding: 14px; margin-bottom: 18px; display: flex; align-items: center; gap: 14px;">
                        <div id="profil-saya-avatar-badge" style="width: 48px; height: 48px; border-radius: 50%; overflow: hidden; background: #fef3c7; color: #b45309; border: 2px solid #fde68a; display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: 800; flex-shrink: 0; box-shadow: 0 2px 6px rgba(180,83,9,0.12);">
                            <?php if (!empty($current_user_foto) && file_exists(__DIR__ . '/' . $current_user_foto)): ?>
                                <img src="<?php echo htmlspecialchars($current_user_foto); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                👑
                            <?php endif; ?>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-weight: 700; font-size: 13.5px; color: #92400e;">
                                <?php echo htmlspecialchars($_SESSION['nama_lengkap'] ?? 'Pemilik Usaha'); ?>
                            </div>
                            <div style="font-size: 11.5px; color: #b45309; margin-top: 2px;">
                                Username: <code>@<?php echo htmlspecialchars($_SESSION['username'] ?? 'owner'); ?></code> &bull; Peran: <strong>Owner (Pemilik Usaha)</strong>
                            </div>
                        </div>
                    </div>

                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="form-group">
                            <label class="form-label">📷 Foto Profil Saya <span style="font-weight:400;color:#64748b;">(Opsional — JPG/PNG/WebP, maks 2 MB)</span></label>
                            <div style="display: flex; align-items: center; gap: 16px; margin-top: 6px; background: #f8fafc; padding: 12px; border-radius: 10px; border: 1.5px solid var(--border);">
                                <div id="profil-saya-preview" style="width: 60px; height: 60px; border-radius: 50%; overflow: hidden; background: #fef3c7; color: #b45309; display: flex; align-items: center; justify-content: center; font-size: 22px; font-weight: 800; border: 2px solid #fde68a; flex-shrink: 0; box-shadow: 0 2px 6px rgba(0,0,0,0.06);">
                                    <?php if (!empty($current_user_foto) && file_exists(__DIR__ . '/' . $current_user_foto)): ?>
                                        <img src="<?php echo htmlspecialchars($current_user_foto); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        👑
                                    <?php endif; ?>
                                </div>
                                <div style="flex: 1;">
                                    <button type="button" class="btn-primary-sm" style="background: #ffffff; color: var(--text-dark); border: 1.5px solid var(--border); padding: 7px 14px; font-size: 12px; font-weight: 700;" onclick="document.getElementById('input-foto-saya').click()">
                                        📁 Ganti Foto Profil
                                    </button>
                                    <input type="file" id="input-foto-saya" name="foto_profil_saya" accept="image/*" style="display: none;" onchange="previewFotoSaya(this)">
                                    <div id="nama-file-saya" style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Pilih foto dari komputer Anda</div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Nama Lengkap Anda</label>
                            <input type="text" name="nama_profil" class="form-control" required value="<?php echo htmlspecialchars($_SESSION['nama_lengkap'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Alamat Email Anda</label>
                            <input type="email" name="email_profil" class="form-control" placeholder="owner@fillahfood.com" value="<?php echo htmlspecialchars($current_user_email); ?>">
                            <small style="color: var(--text-muted); font-size: 11px;">Alamat email penerima verifikasi & OTP keamanan sistem.</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Kata Sandi Baru</label>
                            <input type="password" name="password_baru_profil" class="form-control" placeholder="Ketik kata sandi baru jika ingin diganti">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Konfirmasi Kata Sandi Baru</label>
                            <input type="password" name="konfirmasi_password_profil" class="form-control" placeholder="Ulangi kata sandi baru untuk konfirmasi">
                            <small style="color: var(--text-muted); font-size: 11px;">Kosongkan kolom sandi jika tidak ingin mengubah kata sandi Anda saat ini.</small>
                        </div>

                        <div style="margin-top: 20px;">
                            <button type="submit" name="simpan_profil_saya" class="btn-primary-sm" style="padding: 12px 20px; width: 100%; justify-content: center; font-size: 13px; background: #0284c7;">
                                🔒 Perbarui Profil & Sandi Saya
                            </button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
        <?php endif; ?>

        </div>
    </div>

    <!-- Modal Form Tambah Stok Masuk -->
    <div class="modal-overlay" id="modal-stok-masuk">
        <div class="modal-box">
            <h3 class="modal-title">📥 Catat Stok Masuk (Belanja Bahan)</h3>
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

                <div class="modal-footer">
                    <button type="submit" name="tambah_stok_masuk" class="btn-primary-sm" style="flex:1; padding:12px;">Simpan Stok Masuk</button>
                    <button type="button" class="btn-primary-sm" style="background:#cbd5e1; color:#0f172a;" onclick="tutupModalStokMasuk()">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Form Tambah Bahan Baru -->
    <div class="modal-overlay" id="modal-bahan-baru">
        <div class="modal-box">
            <h3 class="modal-title">✨ Tambah Bahan Baku Baru</h3>
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
                    <label class="form-label">📷 Foto Bahan Baku <span style="font-weight:400;color:#64748b;">(Opsional — maks 2 MB, JPG/PNG/WebP)</span></label>
                    <div class="img-upload-area" onclick="document.getElementById('input-gambar-bahan').click()">
                        <div class="img-upload-preview" id="preview-bahan">
                            <span class="img-upload-icon">📦</span>
                            <span class="img-upload-hint">Klik untuk pilih foto</span>
                        </div>
                    </div>
                    <input type="file" id="input-gambar-bahan" name="gambar_bahan" accept="image/*" style="display:none" onchange="previewGambar(this, 'preview-bahan')">
                </div>

                <div class="modal-footer">
                    <button type="submit" name="tambah_bahan_baru" class="btn-primary-sm" style="flex:1; padding:12px;">Daftarkan Bahan</button>
                    <button type="button" class="btn-primary-sm" style="background:#cbd5e1; color:#0f172a;" onclick="tutupModalBahanBaru()">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Form Tambah Menu Produk Baru -->
    <div class="modal-overlay" id="modal-produk-baru">
        <div class="modal-box">
            <h3 class="modal-title">🍗 Tambah Menu Makanan / Minuman Baru</h3>
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
                    <label class="form-label">📷 Foto Menu <span style="font-weight:400;color:#64748b;">(Opsional — maks 2 MB, JPG/PNG/WebP)</span></label>
                    <div class="img-upload-area" onclick="document.getElementById('input-gambar-produk').click()">
                        <div class="img-upload-preview" id="preview-produk">
                            <span class="img-upload-icon" id="preview-produk-icon">🍗</span>
                            <span class="img-upload-hint">Klik untuk pilih foto</span>
                        </div>
                    </div>
                    <input type="file" id="input-gambar-produk" name="gambar_produk" accept="image/*" style="display:none" onchange="previewGambar(this, 'preview-produk')">
                </div>

                <div class="modal-footer">
                    <button type="submit" name="tambah_produk_baru" class="btn-primary-sm" style="flex:1; padding:12px;">Simpan Menu Baru</button>
                    <button type="button" class="btn-primary-sm" style="background:#cbd5e1; color:#0f172a;" onclick="tutupModalProdukBaru()">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Form Tambah Pengguna Baru (Khusus Owner) -->
    <?php if (is_owner()): ?>
    <div class="modal-overlay" id="modal-pengguna-baru">
        <div class="modal-box">
            <h3 class="modal-title">👥 Tambah Pengguna Baru</h3>
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
                                    if ($rl['nama_peran'] === 'Owner') echo '👑 Owner (Pemilik Usaha - Akses Penuh)';
                                    elseif ($rl['nama_peran'] === 'Admin') echo '⚙️ Admin (Staf Pengelola Persediaan & Menu)';
                                    elseif ($rl['nama_peran'] === 'User') echo '👤 User (Staf Operasional / Layar Kasir POS)';
                                    else echo '🛡️ ' . htmlspecialchars($rl['nama_peran']) . ' (' . htmlspecialchars($rl['deskripsi']) . ')';
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="modal-footer">
                    <button type="submit" name="tambah_pengguna" class="btn-primary-sm" style="flex:1; padding:12px;">Simpan Pengguna</button>
                    <button type="button" class="btn-primary-sm" style="background:#cbd5e1; color:#0f172a;" onclick="tutupModalPenggunaBaru()">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Form Edit Pengguna & Peran (Khusus Owner) -->
    <div class="modal-overlay" id="modal-edit-pengguna">
        <div class="modal-box">
            <h3 class="modal-title">✏️ Edit Pengguna & Peran Hak Akses</h3>
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
                                    if ($rl['nama_peran'] === 'Owner') echo '👑 Owner (Pemilik Usaha - Akses Penuh)';
                                    elseif ($rl['nama_peran'] === 'Admin') echo '⚙️ Admin (Staf Pengelola Persediaan & Menu)';
                                    elseif ($rl['nama_peran'] === 'User') echo '👤 User (Staf Operasional / Layar Kasir POS)';
                                    else echo '🛡️ ' . htmlspecialchars($rl['nama_peran']) . ' (' . htmlspecialchars($rl['deskripsi']) . ')';
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Reset Password Baru (Kosongkan jika tidak ingin ganti)</label>
                    <input type="password" name="password" class="form-control" placeholder="Ketik sandi baru jika ingin diubah">
                </div>

                <div class="modal-footer">
                    <button type="submit" name="edit_pengguna" class="btn-primary-sm" style="flex:1; padding:12px;">Simpan Perubahan</button>
                    <button type="button" class="btn-primary-sm" style="background:#cbd5e1; color:#0f172a;" onclick="tutupModalEditPengguna()">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Form Tambah Peran Baru (Khusus Owner) -->
    <div class="modal-overlay" id="modal-tambah-peran">
        <div class="modal-box" style="max-width: 500px;">
            <h3 class="modal-title">🛡️ Tambah Peran Baru</h3>
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
                            <span>🛒 Layar Kasir POS (Pencatatan Pesanan & Transaksi)</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                            <input type="checkbox" name="akses_dashboard" value="1" style="width: 16px; height: 16px;">
                            <span>📊 Dashboard Ringkasan Omzet & KPI Bisnis</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                            <input type="checkbox" name="akses_stok" value="1" style="width: 16px; height: 16px;">
                            <span>📦 Pencatatan Pasokan Stok Bahan Masuk</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                            <input type="checkbox" name="akses_menu" value="1" style="width: 16px; height: 16px;">
                            <span>🍗 Manajemen Menu Makanan & Minuman</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                            <input type="checkbox" name="akses_pengguna" value="1" style="width: 16px; height: 16px;">
                            <span>👥 Pengelolaan Akun Pengguna & Pengaturan</span>
                        </label>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" name="tambah_peran" class="btn-primary-sm" style="flex:1; padding:12px;">Simpan Peran Baru</button>
                    <button type="button" class="btn-primary-sm" style="background:#cbd5e1; color:#0f172a;" onclick="tutupModalPeranBaru()">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Form Edit Peran (Khusus Owner) -->
    <div class="modal-overlay" id="modal-edit-peran">
        <div class="modal-box" style="max-width: 500px;">
            <h3 class="modal-title">✏️ Edit Data Peran & Hak Akses</h3>
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
                            <span>🛒 Layar Kasir POS (Pencatatan Pesanan & Transaksi)</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                            <input type="checkbox" name="akses_dashboard" id="edit-akses-dash" value="1" style="width: 16px; height: 16px;">
                            <span>📊 Dashboard Ringkasan Omzet & KPI Bisnis</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                            <input type="checkbox" name="akses_stok" id="edit-akses-stok" value="1" style="width: 16px; height: 16px;">
                            <span>📦 Pencatatan Pasokan Stok Bahan Masuk</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                            <input type="checkbox" name="akses_menu" id="edit-akses-menu" value="1" style="width: 16px; height: 16px;">
                            <span>🍗 Manajemen Menu Makanan & Minuman</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                            <input type="checkbox" name="akses_pengguna" id="edit-akses-peng" value="1" style="width: 16px; height: 16px;">
                            <span>👥 Pengelolaan Akun Pengguna & Pengaturan</span>
                        </label>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" name="edit_peran" class="btn-primary-sm" style="flex:1; padding:12px;">Simpan Perubahan Peran</button>
                    <button type="button" class="btn-primary-sm" style="background:#cbd5e1; color:#0f172a;" onclick="tutupModalEditPeran()">Batal</button>
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
            resetPreview('preview-bahan', '📦');
            document.getElementById('input-gambar-bahan').value = '';
            document.getElementById('modal-bahan-baru').style.display = 'none';
        }
        function bukaModalProdukBaru() {
            document.getElementById('modal-produk-baru').style.display = 'flex';
        }
        function tutupModalProdukBaru() {
            // Reset preview saat modal ditutup
            resetPreview('preview-produk', '🍗');
            document.getElementById('input-gambar-produk').value = '';
            document.getElementById('modal-produk-baru').style.display = 'none';
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
                if (hintInput) hintInput.textContent = '🔒 Peran Owner memiliki wewenang penuh dan nama tidak dapat diubah.';
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
                    roleBadge.textContent = '👑 Owner (Pemilik Usaha)';
                } else if (u.role === 'Admin') {
                    roleBadge.className = 'badge badge-role-admin';
                    roleBadge.textContent = '⚙️ Admin (Staf Persediaan)';
                } else if (u.role === 'User') {
                    roleBadge.className = 'badge badge-role-user';
                    roleBadge.textContent = '👤 User (Staf Operasional / POS)';
                } else if (u.role === 'Kasir') {
                    roleBadge.className = 'badge badge-role-kasir';
                    roleBadge.textContent = '🛒 Kasir (Layar Kasir POS)';
                } else {
                    roleBadge.className = 'badge badge-role-custom';
                    roleBadge.textContent = '🛡️ ' + u.role;
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
            if (groupPengaturan) {
                var parentItem = groupPengaturan.querySelector('.nav-parent');
                if (tabName === 'pengguna' || tabName === 'peran' || tabName === 'user-setting') {
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
                    titleEl.textContent = 'Dashboard Manajemen & Persediaan';
                    subtitleEl.textContent = 'Sistem Informasi Terintegrasi UMKM Fillah Food - Capstone Project STSI4440 UT';
                } else if (tabName === 'pengguna') {
                    titleEl.textContent = 'Pengelolaan Pengguna';
                    subtitleEl.textContent = 'Daftar akun staf terdaftar, pendaftaran staf baru, dan kontrol akun';
                } else if (tabName === 'peran') {
                    titleEl.textContent = 'Peran & Hak Akses (RBAC)';
                    subtitleEl.textContent = 'Konfigurasi pembagian hak akses fitur dan penetapan peran staf (Owner, Admin, Kasir)';
                } else if (tabName === 'user-setting') {
                    titleEl.textContent = 'User Setting & Profil Akun';
                    subtitleEl.textContent = 'Pengaturan kredensial akun staf dan profil pribadi';
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
                const icon = kategori === 'Minuman' ? '🥤' : '🍗';
                resetPreview('preview-produk', icon);
            }
        }
    </script>
</body>
</html>
