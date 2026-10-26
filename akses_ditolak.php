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
        <div class="error-icon" style="display: flex; align-items: center; justify-content: center; width: 64px; height: 64px; margin: 0 auto 16px; background: #fee2e2; color: #dc2626; border-radius: 50%;">
            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
        </div>
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
            <?php if ($role === 'Kasir' || $role === 'User'): ?>
                <a href="kasir.php" class="btn btn-primary" style="display:inline-flex; align-items:center; justify-content:center; gap:6px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"></circle><circle cx="19" cy="21" r="1"></circle><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"></path></svg>
                    <span>Kembali ke Layar Kasir</span>
                </a>
            <?php else: ?>
                <a href="index.php" class="btn btn-primary" style="display:inline-flex; align-items:center; justify-content:center; gap:6px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
                    <span>Ke Halaman Login</span>
                </a>
            <?php endif; ?>
            <a href="logout.php" class="btn btn-outline">Ganti Akun</a>
        </div>
    </div>

</body>
</html>
