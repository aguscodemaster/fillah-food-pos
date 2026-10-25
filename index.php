<?php
session_start();
require 'config.php';

// Jika sudah login, langsung arahkan ke halamannya masing-masing
if (isset($_SESSION['username'])) {
    if ($_SESSION['role'] === 'Admin') {
        header("Location: admin.php");
        exit();
    } elseif ($_SESSION['role'] === 'Kasir') {
        header("Location: kasir.php");
        exit();
    }
}

$error = "";

if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Menggunakan Prepared Statement untuk keamanan dari SQL Injection
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND password = ?");
    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $_SESSION['id_user']  = $row['id'];
        $_SESSION['username'] = $row['username'];
        $_SESSION['role']     = $row['role'];
        
        // Arahkan berdasarkan peran (Role-Based Access Control)
        if ($row['role'] === 'Admin') {
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
    <style>
        :root {
            --primary: #ea580c;
            --primary-hover: #c2410c;
            --primary-light: #fff7ed;
            --bg-canvas: #f8fafc;
            --card-bg: #ffffff;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 50%, #f1f5f9 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-card {
            background: var(--card-bg);
            width: 100%;
            max-width: 420px;
            padding: 36px 32px;
            border-radius: 20px;
            box-shadow: 0 10px 30px -5px rgba(234, 88, 12, 0.15), 0 0 1px 1px rgba(0,0,0,0.05);
            border: 1px solid rgba(234, 88, 12, 0.1);
        }

        .brand-header {
            text-align: center;
            margin-bottom: 28px;
        }

        .brand-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, #ea580c, #f59e0b);
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            box-shadow: 0 8px 16px -4px rgba(234, 88, 12, 0.4);
            margin-bottom: 14px;
        }

        .brand-title {
            font-size: 24px;
            font-weight: 800;
            color: var(--text-dark);
            letter-spacing: -0.5px;
        }

        .brand-subtitle {
            font-size: 13px;
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
        }

        .error-alert {
            background-color: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
            padding: 12px 14px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-group {
            margin-bottom: 18px;
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
            padding: 12px 14px;
            border: 1.5px solid var(--border);
            border-radius: 12px;
            font-size: 14px;
            color: var(--text-dark);
            transition: all 0.2s ease;
            outline: none;
        }

        .form-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(234, 88, 12, 0.15);
        }

        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, #ea580c, #c2410c);
            color: white;
            border: none;
            padding: 13px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 6px 15px -3px rgba(234, 88, 12, 0.4);
            transition: all 0.2s ease;
            margin-top: 8px;
        }

        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 20px -3px rgba(234, 88, 12, 0.5);
        }

        /* Demo credentials helper */
        .demo-box {
            margin-top: 24px;
            padding: 14px;
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 12px;
            font-size: 11.5px;
            color: #475569;
        }

        .demo-box strong {
            color: var(--text-dark);
        }

        .demo-row {
            display: flex;
            justify-content: space-between;
            margin-top: 6px;
            padding: 4px 6px;
            background: #ffffff;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.15s;
        }

        .demo-row:hover {
            background: #ffedd5;
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
                <div class="input-wrapper">
                    <input type="password" id="password" name="password" class="form-input" placeholder="Masukkan password" required>
                </div>
            </div>

            <button type="submit" name="login" class="btn-submit">
                Masuk ke Sistem ➜
            </button>
        </form>

        <!-- Akun Demo Bawaan untuk Penilaian Dosen/Penguji -->
        <div class="demo-box">
            <div style="font-weight: 700; margin-bottom: 4px;">🔑 Akun Demo (Klik untuk autofill):</div>
            <div class="demo-row" onclick="fillLogin('admin_nihayatus', '12345')">
                <span>👤 <strong>Admin:</strong> admin_nihayatus</span>
                <span>Pass: 12345</span>
            </div>
            <div class="demo-row" onclick="fillLogin('kasir_1', '12345')">
                <span>🛒 <strong>Kasir:</strong> kasir_1</span>
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
        }
    </script>
</body>
</html>