<?php
session_start();
require 'config.php';

// Jika sudah login, langsung arahkan ke halamannya masing-masing
if (isset($_SESSION['username'])) {
    if (in_array($_SESSION['role'], ['Owner', 'Admin'])) {
        header("Location: admin.php");
        exit();
    } else {
        header("Location: kasir.php");
        exit();
    }
}

$error = "";

if (isset($_POST['login'])) {
    $username_raw = trim($_POST['username']);
    $username_clean = strtolower($username_raw);
    $password = trim($_POST['password']);

    // Alias fleksibel: mendukung nama peran (owner/admin/kasir), nama personel, atau username teknis
    if (in_array($username_clean, ['owner', 'pemilik', 'owner_nihayatus', 'admin_nihayatus', 'nihayatus', 'sofiyah', 'ibu_sofiyah'])) {
        $username = 'owner_nihayatus';
    } elseif (in_array($username_clean, ['admin', 'admin_persediaan', 'gudang', 'persediaan', 'staf_persediaan', 'admin_gudang'])) {
        $username = 'admin_persediaan';
    } elseif (in_array($username_clean, ['kasir', 'kasir_1', 'kasir1', 'aisyah', 'aisya', 'aisyah_marta', 'aisyah_dewi'])) {
        $username = 'kasir_1';
    } else {
        $username = $username_raw;
    }

    // Menggunakan Prepared Statement untuk keamanan dari SQL Injection
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND password = ?");
    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $_SESSION['id_user']      = $row['id'];
        $_SESSION['username']     = $row['username'];
        $_SESSION['nama_lengkap'] = $row['nama_lengkap'];
        $_SESSION['role']         = $row['role'];
        $_SESSION['foto']         = $row['foto'] ?? '';
        
        // Arahkan berdasarkan peran (Role-Based Access Control)
        if (in_array($row['role'], ['Owner', 'Admin'])) {
            header("Location: admin.php");
            exit();
        } else {
            header("Location: kasir.php");
            exit();
        }
    } else {
        $error = "Username atau password yang Anda masukkan salah!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Sistem - Fillah Food POS</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 50%, #f1f5f9 100%);
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: clamp(12px, 3vw, 24px);
        }

        .login-card {
            background: var(--bg-card);
            width: 100%;
            max-width: 420px;
            padding: clamp(20px, 6vw, 36px) clamp(16px, 5vw, 32px);
            border-radius: var(--radius-xl);
            box-shadow: 0 10px 30px -5px rgba(234, 88, 12, 0.15), 0 0 1px 1px rgba(0,0,0,0.05);
            border: 1px solid rgba(234, 88, 12, 0.1);
        }

        .brand-header {
            text-align: center;
            margin-bottom: clamp(18px, 4vw, 28px);
        }

        .brand-icon {
            width: clamp(52px, 12vw, 64px);
            height: clamp(52px, 12vw, 64px);
            background: linear-gradient(135deg, #ea580c, #f59e0b);
            border-radius: var(--radius-md);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: clamp(26px, 6vw, 32px);
            box-shadow: 0 8px 16px -4px rgba(234, 88, 12, 0.4);
            margin-bottom: 12px;
        }

        .brand-title {
            font-size: clamp(20px, 5vw, 24px);
            font-weight: 800;
            color: var(--text-dark);
            letter-spacing: -0.5px;
        }

        .brand-subtitle {
            font-size: clamp(11.5px, 2.8vw, 13px);
            color: var(--text-muted);
            margin-top: 4px;
        }

        .badge-academic {
            display: inline-block;
            background: var(--primary-light);
            color: var(--primary);
            font-size: 11px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 20px;
            margin-top: 8px;
            border: 1px solid #fed7aa;
            line-height: 1.3;
        }

        .error-alert {
            background-color: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
            padding: 12px 14px;
            border-radius: var(--radius-md);
            font-size: 13px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 6px;
        }

        .input-wrapper {
            position: relative;
        }

        .form-input {
            width: 100%;
            min-height: 44px; /* Touch target >= 44px */
            padding: 10px 14px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-md);
            font-size: 14px;
            color: var(--text-dark);
            transition: all 0.2s ease;
            outline: none;
            background: #ffffff;
        }

        .form-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(234, 88, 12, 0.15);
        }

        .btn-toggle-pwd {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .btn-toggle-pwd:hover {
            color: var(--primary);
            background-color: var(--primary-light);
        }

        .btn-toggle-pwd:focus-visible {
            outline: 2px solid var(--primary);
        }

        .btn-submit {
            width: 100%;
            min-height: 46px; /* Touch target >= 44px */
            background: linear-gradient(135deg, #ea580c, #c2410c);
            color: white;
            border: none;
            padding: 12px;
            border-radius: var(--radius-md);
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 6px 15px -3px rgba(234, 88, 12, 0.4);
            transition: all 0.2s ease;
            margin-top: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 20px -3px rgba(234, 88, 12, 0.5);
        }

        /* Demo credentials helper */
        .demo-box {
            margin-top: 20px;
            padding: 12px 14px;
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: var(--radius-md);
            font-size: 12px;
            color: #475569;
        }

        .demo-box strong {
            color: var(--text-dark);
        }

        .demo-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 4px;
            margin-top: 6px;
            padding: 6px 8px;
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.15s;
            font-size: 11.5px;
        }

        .demo-row:hover {
            background: #ffedd5;
            border-color: var(--primary);
        }

        .public-menu-link {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
        }

        .public-menu-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }

        .public-menu-link a:hover {
            text-decoration: underline;
        }

        /* Media queries for very small screens */
        @media (max-width: 360px) {
            .login-card {
                padding: 16px 12px;
            }
            .demo-row {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="brand-header">
            <div class="brand-icon">🍗</div>
            <h1 class="brand-title">Fillah Food POS</h1>
            <p class="brand-subtitle">Sistem Informasi Persediaan & Kasir Terintegrasi</p>
            <span class="badge-academic">STSI4440 Capstone Project UT - Kelompok 3C</span>
        </div>

        <?php if (!empty($error)): ?>
            <div class="error-alert">
                <span>⚠️</span>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label class="form-label" for="username">Nama Pengguna (Username)</label>
                <div class="input-wrapper">
                    <input type="text" id="username" name="username" class="form-input" placeholder="Masukkan username" required autofocus>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Kata Sandi (Password)</label>
                <div class="input-wrapper" style="position: relative;">
                    <input type="password" id="password" name="password" class="form-input" placeholder="Masukkan password" required style="padding-right: 44px;">
                    <button type="button" class="btn-toggle-pwd" onclick="togglePassword()" title="Tampilkan/Sembunyikan Kata Sandi" aria-label="Toggle password visibility">
                        <!-- Icon Eye (Open) -->
                        <svg id="iconEye" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                        <!-- Icon Eye Off (Slash) -->
                        <svg id="iconEyeOff" style="display: none;" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"></path>
                            <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"></path>
                            <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"></path>
                            <line x1="2" y1="2" x2="22" y2="22"></line>
                        </svg>
                    </button>
                </div>
            </div>

            <button type="submit" name="login" class="btn-submit">
                Masuk ke Sistem ➜
            </button>
        </form>

        <!-- Akun Demo Bawaan untuk Penilaian Dosen/Penguji (3 Peran) -->
        <div class="demo-box">
            <div style="font-weight: 700; margin-bottom: 4px;">🔑 Pilihan Akun Demo (Klik untuk Autofill):</div>
            <div class="demo-row" onclick="fillLogin('owner_nihayatus', '12345')">
                <span>👑 <strong>Owner:</strong> owner_nihayatus (Ibu Sofiyah)</span>
                <span>Pass: 12345</span>
            </div>
            <div class="demo-row" onclick="fillLogin('admin_persediaan', '12345')">
                <span>⚙️ <strong>Admin:</strong> admin_persediaan (Gudang)</span>
                <span>Pass: 12345</span>
            </div>
            <div class="demo-row" onclick="fillLogin('kasir_1', '12345')">
                <span>🛒 <strong>Kasir:</strong> kasir_1 / aisyah (Aisyah)</span>
                <span>Pass: 12345</span>
            </div>
        </div>

        <div class="public-menu-link">
            <span>Pelanggan ingin melihat menu? </span>
            <a href="menu.php" target="_blank">Buka E-Menu Publik 📱</a>
        </div>
    </div>

    <script>
        function fillLogin(user, pass) {
            document.getElementById('username').value = user;
            document.getElementById('password').value = pass;
            var err = document.querySelector('.error-alert');
            if (err) err.style.display = 'none';
        }

        function togglePassword() {
            var pwd = document.getElementById('password');
            var eye = document.getElementById('iconEye');
            var eyeOff = document.getElementById('iconEyeOff');
            if (pwd.type === 'password') {
                pwd.type = 'text';
                eye.style.display = 'none';
                eyeOff.style.display = 'block';
            } else {
                pwd.type = 'password';
                eye.style.display = 'block';
                eyeOff.style.display = 'none';
            }
        }
    </script>
</body>
</html>