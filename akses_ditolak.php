<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
http_response_code(403);

$username = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Tamu / Belum Login';
$role     = isset($_SESSION['role']) ? htmlspecialchars($_SESSION['role']) : '-';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Akses Ditolak | Fillah Food POS</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #ea580c;
            --danger: #dc2626;
            --bg-canvas: #f8fafc;
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
            background-color: var(--bg-canvas);
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .error-card {
            background: #ffffff;
            width: 100%;
            max-width: 500px;
            border-radius: 24px;
            padding: 40px 32px;
            text-align: center;
            box-shadow: 0 10px 25px -5px rgba(220, 38, 38, 0.1), 0 0 1px 1px rgba(0,0,0,0.05);
            border: 1px solid #fee2e2;
        }

        .error-icon {
            width: 80px;
            height: 80px;
            background: #fee2e2;
            color: var(--danger);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            margin-bottom: 20px;
            border: 4px solid #fecaca;
        }

        .error-title {
            font-size: 24px;
            font-weight: 800;
            color: var(--danger);
            margin-bottom: 8px;
        }

        .error-subtitle {
            font-size: 14px;
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 24px;
        }

        .user-info-box {
            background: #f8fafc;
            border: 1.5px dashed var(--border);
            border-radius: 14px;
            padding: 14px;
            font-size: 13px;
            text-align: left;
            margin-bottom: 28px;
        }

        .user-info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 6px;
        }

        .user-info-row:last-child {
            margin-bottom: 0;
        }

        .actions {
            display: flex;
            gap: 12px;
        }

        .btn {
            flex: 1;
            padding: 12px;
            border-radius: 12px;
            font-size: 13.5px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s;
            display: inline-block;
        }

        .btn-primary {
            background: var(--primary);
            color: #ffffff;
        }

        .btn-primary:hover {
            background: #c2410c;
        }

        .btn-outline {
            background: #f1f5f9;
            color: var(--text-dark);
            border: 1px solid var(--border);
        }

        .btn-outline:hover {
            background: #e2e8f0;
        }
    </style>
</head>
<body>

    <div class="error-card">
        <div class="error-icon">🛑</div>
        <h1 class="error-title">403 - Akses Ditolak!</h1>
        <p class="error-subtitle">
            Halaman yang Anda tuju dilindungi oleh kebijakan <strong>Role-Based Access Control (RBAC)</strong>. Peran akun Anda saat ini tidak memiliki izin untuk membuka halaman manajemen atau persediaan ini.
        </p>

        <div class="user-info-box">
            <div class="user-info-row">
                <span style="color: var(--text-muted);">Nama Pengguna:</span>
                <strong><?php echo $username; ?></strong>
            </div>
            <div class="user-info-row">
                <span style="color: var(--text-muted);">Peran Anda (Role):</span>
                <span style="background: #fee2e2; color: #dc2626; padding: 2px 8px; border-radius: 6px; font-weight: 700;">
                    <?php echo $role; ?>
                </span>
            </div>
            <div class="user-info-row">
                <span style="color: var(--text-muted);">Tingkat Izin:</span>
                <span style="font-weight: 600;">Hanya Layar Kasir (POS)</span>
            </div>
        </div>

        <div class="actions">
            <?php if ($role === 'Kasir'): ?>
                <a href="kasir.php" class="btn btn-primary">🛒 Kembali ke Layar Kasir</a>
            <?php else: ?>
                <a href="index.php" class="btn btn-primary">🏠 Ke Halaman Login</a>
            <?php endif; ?>
            <a href="logout.php" class="btn btn-outline">Ganti Akun</a>
        </div>
    </div>

</body>
</html>
