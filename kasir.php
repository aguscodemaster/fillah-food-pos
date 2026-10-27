<?php
require 'auth_check.php';
protect_page(['User', 'Kasir', 'Admin', 'Owner']);
require 'config.php';

// Ambil seluruh daftar produk aktif dari database
$query_produk = "SELECT * FROM produk ORDER BY kategori ASC, nama_produk ASC";
$result_produk = $conn->query($query_produk);
$daftar_produk = [];
if ($result_produk) {
    while ($row = $result_produk->fetch_assoc()) {
        $daftar_produk[] = $row;
    }
}

// Ambil info transaksi terakhir untuk sinkronisasi nomor nota sesuai daftar transaksi
$q_last = $conn->query("SELECT MAX(id) AS last_id FROM transaksi");
$row_last = $q_last ? $q_last->fetch_assoc() : null;
$next_trx_id = ($row_last && $row_last['last_id']) ? ($row_last['last_id'] + 1) : 1;
$next_no_nota = "#TRX-" . str_pad($next_trx_id, 4, "0", STR_PAD_LEFT);
$waktu_sekarang = date('d/m/Y H:i:s') . ' WIB';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Kasir POS - Fillah Food</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background-color: var(--bg-canvas);
            color: var(--text-dark);
            height: 100vh;
            height: 100dvh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* Top Navbar */
        .navbar {
            background: #ffffff;
            border-bottom: 1.5px solid var(--border);
            padding: clamp(8px, 1.5vw, 12px) clamp(12px, 2.5vw, 24px);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0;
            box-shadow: var(--shadow-sm);
            gap: 10px;
            z-index: 20;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: clamp(8px, 1.5vw, 12px);
        }

        .brand-logo {
            width: clamp(34px, 5vw, 42px);
            height: clamp(34px, 5vw, 42px);
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: clamp(18px, 3vw, 22px);
            box-shadow: 0 4px 8px -2px rgba(234, 88, 12, 0.4);
            flex-shrink: 0;
        }

        .brand-text h1 {
            font-size: clamp(15px, 2.5vw, 18px);
            font-weight: 800;
            color: var(--text-dark);
            letter-spacing: -0.3px;
            white-space: nowrap;
        }

        .brand-text p {
            font-size: 11px;
            color: var(--text-muted);
            display: block;
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: clamp(6px, 1.2vw, 12px);
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .user-pill {
            background: var(--primary-light);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            color: var(--primary);
            border: 1px solid #fed7aa;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
        }

        .btn-link {
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            padding: 6px 10px;
            border-radius: var(--radius-sm);
            transition: all 0.15s;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .btn-link:hover {
            background: #e2e8f0;
            color: var(--text-dark);
        }

        .btn-logout {
            background: #fee2e2;
            color: var(--danger);
            border: 1px solid #fecaca;
            padding: 6px 12px;
            border-radius: var(--radius-sm);
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.15s;
            white-space: nowrap;
        }

        .btn-logout:hover {
            background: var(--danger);
            color: #ffffff;
        }

        /* Mobile View Tabs Switcher (Visible only on <= 850px) */
        .kasir-view-tabs {
            display: none;
            background: #ffffff;
            border-bottom: 1.5px solid var(--border);
            padding: 6px 12px;
            gap: 8px;
            flex-shrink: 0;
            z-index: 15;
        }

        .view-tab-btn {
            flex: 1;
            padding: 8px 12px;
            border-radius: var(--radius-sm);
            border: 1.5px solid var(--border);
            background: #f8fafc;
            color: var(--text-muted);
            font-size: 13px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            cursor: pointer;
            transition: all 0.15s;
        }

        .view-tab-btn.active {
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
            box-shadow: 0 2px 8px -1px rgba(234, 88, 12, 0.4);
        }

        .view-tab-badge {
            background: #ffffff;
            color: var(--primary);
            padding: 2px 7px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 800;
        }

        .view-tab-btn.active .view-tab-badge {
            background: #ffffff;
            color: var(--primary);
        }

        /* Main Workspace Container */
        .workspace {
            display: flex;
            flex: 1;
            height: calc(100vh - 65px);
            height: calc(100dvh - 65px);
            overflow: hidden;
            position: relative;
        }

        /* Left Panel: Menu Catalog */
        .catalog-panel {
            flex: 1.4;
            padding: clamp(12px, 2vw, 20px) clamp(12px, 2.5vw, 24px);
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 16px;
            background-color: var(--bg-canvas);
            -webkit-overflow-scrolling: touch;
        }

        .catalog-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            justify-content: space-between;
        }

        .category-pills {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 2px;
            max-width: 100%;
        }

        .cat-btn {
            background: #ffffff;
            border: 1.5px solid var(--border);
            padding: 7px 14px;
            border-radius: 20px;
            font-size: 12.5px;
            font-weight: 700;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .cat-btn.active, .cat-btn:hover {
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
            box-shadow: 0 4px 10px -2px rgba(234, 88, 12, 0.4);
        }

        .search-box {
            position: relative;
            min-width: 220px;
            flex-grow: 1;
            max-width: 320px;
        }

        .search-input {
            width: 100%;
            padding: 8px 14px 8px 36px;
            border: 1.5px solid var(--border);
            border-radius: 20px;
            font-size: 13px;
            outline: none;
            background: #ffffff;
            transition: all 0.2s;
        }

        .search-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(234, 88, 12, 0.15);
        }

        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 14px;
            color: var(--text-muted);
            pointer-events: none;
        }

        /* Products Grid */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: clamp(10px, 1.5vw, 16px);
            padding-bottom: 20px;
        }

        .product-card {
            background: var(--bg-card);
            border: 1.5px solid var(--border);
            border-radius: var(--radius-md);
            padding: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            box-shadow: var(--shadow-sm);
        }

        .product-card:hover {
            transform: translateY(-2px);
            border-color: var(--primary);
            box-shadow: 0 8px 20px -4px rgba(234, 88, 12, 0.2);
        }

        .product-card:active {
            transform: scale(0.98);
        }

        .card-header-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--primary-light);
            color: var(--primary);
            font-size: 10px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 10px;
        }

        .product-thumb {
            width: 100%;
            height: clamp(80px, 12vw, 105px);
            border-radius: 10px;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: clamp(32px, 5vw, 40px);
            margin-bottom: 10px;
        }

        .product-name {
            font-size: clamp(12.5px, 2vw, 14px);
            font-weight: 700;
            color: var(--text-dark);
            line-height: 1.3;
            margin-bottom: 4px;
            min-height: 34px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-price {
            font-size: clamp(13.5px, 2vw, 15px);
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 8px;
        }

        .btn-add-item {
            width: 100%;
            background: #f8fafc;
            color: var(--text-dark);
            border: 1.5px solid var(--border);
            padding: 7px;
            border-radius: var(--radius-sm);
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.15s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            min-height: 36px;
        }

        .product-card:hover .btn-add-item {
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
        }

        /* Right Panel: Order Ticket (Keranjang Kasir) */
        .cart-panel {
            flex: 1;
            background: #ffffff;
            border-left: 1.5px solid var(--border);
            display: flex;
            flex-direction: column;
            box-shadow: -4px 0 15px rgba(0,0,0,0.03);
            max-width: 440px;
            min-width: 320px;
            height: 100%;
            overflow: hidden;
        }

        .cart-header {
            padding: 12px 18px;
            border-bottom: 1.5px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #ffffff;
            flex-shrink: 0;
            gap: 8px;
        }

        .cart-header h2 {
            font-size: clamp(14px, 2vw, 16px);
            font-weight: 800;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-back-to-menu {
            display: none; /* Only shown on mobile cart view */
            background: #f1f5f9;
            border: 1px solid var(--border);
            color: var(--text-dark);
            font-size: 11.5px;
            font-weight: 700;
            padding: 5px 10px;
            border-radius: var(--radius-sm);
            cursor: pointer;
        }

        .btn-clear-cart {
            background: none;
            border: none;
            color: var(--danger);
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            padding: 4px 6px;
        }

        .btn-clear-cart:hover {
            text-decoration: underline;
        }

        /* Order Meta (Meja & Order Type) */
        .order-meta {
            padding: 10px 18px;
            background: #f8fafc;
            border-bottom: 1px solid var(--border);
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            flex-shrink: 0;
        }

        .meta-select {
            width: 100%;
            padding: 7px 8px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            font-size: 12px;
            font-weight: 600;
            color: var(--text-dark);
            outline: none;
            background: #ffffff;
        }

        /* Cart Items List */
        .cart-items-wrapper {
            flex: 1;
            overflow-y: auto;
            padding: 10px 18px;
            -webkit-overflow-scrolling: touch;
        }

        .cart-item-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px dashed var(--border);
            gap: 8px;
        }

        .item-info {
            flex: 1;
            min-width: 0;
        }

        .item-title {
            font-size: 12.5px;
            font-weight: 700;
            color: var(--text-dark);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .item-subtotal {
            font-size: 11.5px;
            font-weight: 600;
            color: var(--primary);
            margin-top: 2px;
        }

        .stepper {
            display: flex;
            align-items: center;
            gap: 5px;
            flex-shrink: 0;
        }

        .step-btn {
            width: 28px;
            height: 28px;
            border-radius: 6px;
            border: 1px solid var(--border);
            background: #f8fafc;
            font-size: 14px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.15s;
        }

        .step-btn:hover, .step-btn:active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .step-qty {
            font-size: 13px;
            font-weight: 700;
            min-width: 22px;
            text-align: center;
        }

        /* Cart Footer & Checkout */
        .cart-footer {
            padding: 12px 18px clamp(12px, 2vw, 18px) 18px;
            border-top: 1.5px solid var(--border);
            background: #ffffff;
            box-shadow: 0 -4px 10px rgba(0,0,0,0.02);
            flex-shrink: 0;
        }

        .order-notes-input {
            width: 100%;
            padding: 7px 10px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 12px;
            margin-bottom: 10px;
            outline: none;
            background: #ffffff;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            font-size: 12.5px;
            margin-bottom: 4px;
            color: var(--text-muted);
        }

        .summary-total {
            display: flex;
            justify-content: space-between;
            font-size: clamp(16px, 2.5vw, 18px);
            font-weight: 800;
            color: var(--text-dark);
            margin-top: 6px;
            padding-top: 6px;
            border-top: 1.5px dashed var(--border);
        }

        .quick-cash-row {
            display: flex;
            gap: 4px;
            margin: 10px 0 6px 0;
            flex-wrap: wrap;
        }

        .quick-cash-btn {
            flex: 1 1 calc(20% - 4px);
            min-width: 48px;
            padding: 6px 2px;
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            color: var(--text-dark);
            cursor: pointer;
            transition: all 0.15s;
            text-align: center;
        }

        .quick-cash-btn:hover, .quick-cash-btn:active {
            background: #ffedd5;
            border-color: var(--primary);
            color: var(--primary);
        }

        .cash-input-group {
            margin-bottom: 8px;
        }

        .cash-input {
            width: 100%;
            padding: 9px 12px;
            border: 1.5px solid var(--border);
            border-radius: var(--radius-sm);
            font-size: 16px; /* 16px prevents mobile browser zoom */
            font-weight: 700;
            outline: none;
            background: #ffffff;
        }

        .cash-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(234, 88, 12, 0.15);
        }

        .change-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12.5px;
            font-weight: 700;
            color: var(--success);
            margin-bottom: 10px;
        }

        .btn-checkout {
            width: 100%;
            background: linear-gradient(135deg, var(--success), var(--success-hover));
            color: white;
            border: none;
            padding: 12px;
            border-radius: var(--radius-md);
            font-size: 14.5px;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 6px 15px -3px rgba(22, 163, 74, 0.4);
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 44px;
        }

        .btn-checkout:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 20px -3px rgba(22, 163, 74, 0.5);
        }

        /* Mobile Floating Bottom Cart Bar (Sticky trigger) */
        .mobile-floating-cart {
            display: none !important; /* Tersembunyi pada layar desktop */
        }

        @keyframes slideUpFloat {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .float-cart-info {
            display: flex;
            flex-direction: column;
            gap: 1px;
            min-width: 0;
        }

        .float-cart-badge {
            font-size: 11px;
            color: #94a3b8;
            font-weight: 600;
            white-space: nowrap;
        }

        .float-cart-total {
            font-size: 15px;
            font-weight: 800;
            color: #fde047;
            white-space: nowrap;
        }

        .btn-float-checkout {
            background: var(--primary);
            color: #ffffff;
            border: none;
            padding: 8px 14px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
            flex-shrink: 0;
            box-shadow: 0 4px 10px rgba(234, 88, 12, 0.4);
        }

        .btn-float-checkout:active {
            transform: scale(0.97);
        }

        /* Modal Struk Nota Belanja */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 999;
            padding: 16px;
        }

        .receipt-card {
            background: #ffffff;
            width: min(94vw, 360px);
            max-height: 88vh;
            max-height: 88dvh;
            border-radius: var(--radius-lg);
            padding: 22px 18px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
            overflow-y: auto;
            font-family: 'Courier Prime', 'Courier New', Courier, monospace;
            -webkit-overflow-scrolling: touch;
            color: #0f172a;
            border: 1px solid #e2e8f0;
        }

        .receipt-header {
            text-align: center;
            border-bottom: 1.5px dashed #64748b;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }

        .receipt-header h3 {
            font-size: 17px;
            font-weight: 800;
            letter-spacing: 0.5px;
            color: #0f172a;
            margin-bottom: 3px;
        }

        .receipt-header p {
            font-size: 11px;
            line-height: 1.35;
            color: #475569;
        }

        .receipt-meta {
            font-size: 11.5px;
            border-bottom: 1.5px dashed #64748b;
            padding-bottom: 8px;
            margin-bottom: 8px;
            line-height: 1.5;
        }

        .receipt-meta-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2px;
        }

        .receipt-items-header {
            display: flex;
            justify-content: space-between;
            font-size: 10.5px;
            font-weight: 800;
            text-transform: uppercase;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 4px;
            margin-bottom: 6px;
            color: #475569;
        }

        .receipt-items {
            border-bottom: 1.5px dashed #64748b;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }

        .receipt-item-row {
            margin-bottom: 6px;
        }

        .receipt-item-name {
            font-size: 12px;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.25;
        }

        .receipt-item-detail {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            color: #475569;
            margin-top: 1px;
        }

        .receipt-item-subtotal {
            font-weight: 700;
            color: #0f172a;
        }

        .receipt-summary {
            font-size: 12px;
            border-bottom: 1.5px dashed #64748b;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }

        .receipt-summary .row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
        }

        .receipt-summary .total-row {
            font-size: 15px;
            font-weight: 800;
            color: #0f172a;
            margin: 5px 0;
            padding-top: 5px;
            border-top: 1px dashed #cbd5e1;
        }

        .receipt-footer {
            text-align: center;
            font-size: 10.5px;
            margin-bottom: 16px;
            color: #475569;
            line-height: 1.35;
        }

        .modal-actions {
            display: flex;
            gap: 8px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            flex-wrap: wrap;
        }

        .btn-print {
            flex: 1;
            min-width: 120px;
            background: var(--primary);
            color: white;
            border: none;
            padding: 10px;
            border-radius: var(--radius-sm);
            font-weight: 700;
            cursor: pointer;
            min-height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.15s;
        }

        .btn-print:hover {
            background: var(--primary-hover);
        }

        .btn-close-modal {
            flex: 1;
            min-width: 120px;
            background: #e2e8f0;
            color: var(--text-dark);
            border: none;
            padding: 10px;
            border-radius: var(--radius-sm);
            font-weight: 700;
            cursor: pointer;
            min-height: 42px;
        }

        /* ==========================================================================
           RESPONSIVE BREAKPOINTS FOR POS CASHIER
           ========================================================================== */

        /* Tablet Landscape & Medium Desktops (851px - 1100px) */
        @media (max-width: 1100px) and (min-width: 851px) {
            .cart-panel {
                max-width: 360px;
                min-width: 300px;
            }
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            }
        }

        /* Mobile & Tablet Portrait View (< 850px) */
        @media (max-width: 850px) {
            body {
                overflow: hidden;
            }

            .navbar {
                padding: 8px 12px;
            }

            .brand-text p {
                display: none; /* Keep navbar clean on mobile */
            }

            .kasir-view-tabs {
                display: flex; /* Show tabs switcher on mobile */
            }

            .workspace {
                height: calc(100vh - 106px);
                height: calc(100dvh - 106px);
            }

            /* Default mobile: show Catalog, hide Cart */
            .catalog-panel {
                display: flex;
                width: 100%;
                flex: 1;
                padding: 12px 12px 75px 12px; /* Bottom padding so floating cart won't cover items */
            }

            .cart-panel {
                display: none;
                width: 100%;
                max-width: 100%;
                min-width: 100%;
                border-left: none;
                flex: 1;
            }

            /* When cart tab active: show Cart, hide Catalog */
            body.mobile-view-cart .catalog-panel {
                display: none;
            }

            body.mobile-view-cart .cart-panel {
                display: flex;
            }

            body.mobile-view-cart .btn-back-to-menu {
                display: inline-flex;
            }

            .mobile-floating-cart {
                display: none;
                position: fixed;
                bottom: max(14px, env(safe-area-inset-bottom, 14px));
                left: 14px;
                right: 14px;
                margin: 0 auto;
                max-width: 440px;
                width: calc(100% - 28px);
                background: #0f172a;
                color: #ffffff;
                border-radius: 14px;
                padding: 9px 14px;
                box-shadow: 0 10px 25px -3px rgba(0, 0, 0, 0.4), 0 0 0 1px rgba(255, 255, 255, 0.12);
                align-items: center;
                justify-content: space-between;
                z-index: 50;
                cursor: pointer;
                transition: transform 0.2s ease, opacity 0.2s ease;
            }

            .mobile-floating-cart.has-items {
                display: flex !important;
                animation: slideUpFloat 0.25s ease;
            }

            body.mobile-view-cart .mobile-floating-cart {
                display: none !important;
            }

            .catalog-toolbar {
                flex-direction: column;
                align-items: stretch;
                gap: 8px;
            }

            .search-box {
                max-width: 100%;
                min-width: 100%;
            }

            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
                gap: 10px;
            }

            .product-card {
                padding: 10px;
            }

            .product-thumb {
                height: 80px;
                font-size: 32px;
            }

            .product-name {
                font-size: 12.5px;
                min-height: 32px;
            }

            .product-price {
                font-size: 13.5px;
            }
        }

        /* Large Screen Media Query - Memastikan bar melayang TIDAK tampil pada layar besar */
        @media (min-width: 851px) {
            .mobile-floating-cart {
                display: none !important;
            }
        }

        /* Small Phones (< 480px) */
        @media (max-width: 480px) {
            .navbar-brand .brand-logo {
                width: 32px;
                height: 32px;
                font-size: 16px;
            }

            .brand-text h1 {
                font-size: 14px;
            }

            .nav-actions .btn-link {
                padding: 5px 6px;
                font-size: 11px;
            }

            .user-pill {
                padding: 4px 8px;
                font-size: 11px;
            }

            .products-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 8px;
            }

            .quick-cash-btn {
                flex: 1 1 calc(33.3% - 4px);
                font-size: 10.5px;
                padding: 8px 2px;
            }
        }

        /* Print Media Query - Khusus Cetak ke Kertas Struk / Thermal POS Printer */
        @media print {
            @page {
                size: auto;
                margin: 0mm; /* Menghilangkan margin browser bawaan agar pas di kertas struk */
            }

            html, body {
                background: #ffffff !important;
                color: #000000 !important;
                margin: 0 !important;
                padding: 0 !important;
                height: auto !important;
                overflow: visible !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            body * {
                visibility: hidden !important;
            }

            .modal-overlay {
                position: static !important;
                background: transparent !important;
                backdrop-filter: none !important;
                padding: 0 !important;
                display: block !important;
            }

            .receipt-card, .receipt-card * {
                visibility: visible !important;
            }

            .receipt-card {
                position: absolute !important;
                left: 0 !important;
                right: 0 !important;
                top: 0 !important;
                margin: 0 auto !important;
                width: 100% !important;
                max-width: 78mm !important; /* Standar ukuran lebar kertas struk thermal 80mm / 58mm */
                padding: 6mm 4mm 12mm 4mm !important; /* Padding tepi kertas struk */
                box-shadow: none !important;
                border: none !important; /* Tanpa border kotak luar khas struk printer rol */
                border-radius: 0 !important;
                font-size: 11.5px !important;
                line-height: 1.35 !important;
                color: #000000 !important;
                font-family: 'Courier New', Courier, monospace !important;
            }

            .receipt-header, .receipt-meta, .receipt-items, .receipt-summary {
                border-color: #000000 !important;
            }

            .receipt-items-header {
                border-color: #000000 !important;
                color: #000000 !important;
            }

            .receipt-item-detail, .receipt-meta-row {
                color: #000000 !important;
            }

            .modal-actions {
                display: none !important;
            }
        }
    </style>
</head>
<body>

    <!-- Header Navbar -->
    <header class="navbar">
        <div class="navbar-brand">
            <div class="brand-logo">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"></path>
                    <path d="M7 2v20"></path>
                    <path d="M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"></path>
                </svg>
            </div>
            <div class="brand-text">
                <h1>Fillah Food POS</h1>
                <p>Mode Operasional Kasir</p>
            </div>
        </div>

        <div class="nav-actions">
            <span class="user-pill" style="display: inline-flex; align-items: center; gap: 7px;">
                <?php if (!empty($_SESSION['foto']) && file_exists(__DIR__ . '/' . $_SESSION['foto'])): ?>
                    <img src="<?php echo htmlspecialchars($_SESSION['foto']); ?>" alt="Foto" style="width: 22px; height: 22px; border-radius: 50%; object-fit: cover;">
                <?php else: ?>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                <?php endif; ?>
                <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong> (<?php echo htmlspecialchars($_SESSION['role']); ?>)
            </span>
            <a href="menu.php" target="_blank" class="btn-link" style="display:inline-flex; align-items:center; gap:5px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="20" x="5" y="2" rx="2" ry="2"></rect><path d="M12 18h.01"></path></svg>
                <span>E-Menu Pelanggan</span>
            </a>
            <?php if (in_array($_SESSION['role'], ['Owner', 'Admin'])): ?>
                <a href="admin.php" class="btn-link" style="display:inline-flex; align-items:center; gap:5px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    <span>Dashboard Manajemen</span>
                </a>
            <?php endif; ?>
            <a href="logout.php" class="btn-logout">Keluar (Logout)</a>
        </div>
    </header>

    <!-- Mobile View Switcher Tabs (Only visible on screens <= 850px) -->
    <div class="kasir-view-tabs">
        <button type="button" class="view-tab-btn active" id="tab-btn-menu" onclick="switchKasirView('menu')">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"></path><path d="M6 6h10"></path><path d="M6 10h10"></path></svg>
            <span>Katalog Menu</span>
        </button>
        <button type="button" class="view-tab-btn" id="tab-btn-cart" onclick="switchKasirView('cart')">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"></circle><circle cx="19" cy="21" r="1"></circle><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"></path></svg>
            <span>Keranjang</span> <span class="view-tab-badge cart-badge-count">0</span>
        </button>
    </div>

    <!-- Workspace -->
    <div class="workspace">
        
        <!-- Left: Product Catalog -->
        <main class="catalog-panel">
            <div class="catalog-toolbar">
                <div class="category-pills">
                    <button class="cat-btn active" onclick="filterKategori('Semua')">Semua Menu</button>
                    <button class="cat-btn" onclick="filterKategori('Makanan')">Makanan</button>
                    <button class="cat-btn" onclick="filterKategori('Minuman')">Minuman</button>
                    <button type="button" class="cat-btn" onclick="bukaModalBannerHD()" title="Lihat Banner Menu Resmi HD" style="background:#fff7ed; border-color:#fdba74; color:#ea580c; display:inline-flex; align-items:center; gap:5px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line><line x1="11" y1="8" x2="11" y2="14"></line><line x1="8" y1="11" x2="14" y2="11"></line></svg>
                        <span>Banner HD</span>
                    </button>
                </div>

                <div class="search-box">
                    <span class="search-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    </span>
                    <input type="text" id="cari-menu" class="search-input" placeholder="Cari nama menu..." onkeyup="cariMenuLive()">
                </div>
            </div>

            <!-- Product Cards Grid -->
            <div class="products-grid" id="daftar-menu-grid">
                <?php if (empty($daftar_produk)): ?>
                    <p style="color: #888; font-style: italic;">Belum ada menu di database.</p>
                <?php else: ?>
                    <?php foreach ($daftar_produk as $p): ?>
                        <?php
                            $thumb_src = !empty($p['gambar']) ? $p['gambar'] : '';
                            $hd_src = $thumb_src;
                            if (!empty($thumb_src)) {
                                $info = pathinfo($thumb_src);
                                $dir = (!empty($info['dirname']) && $info['dirname'] !== '.') ? $info['dirname'] . '/' : '';
                                $candidate_hd = $dir . $info['filename'] . '_hd.' . $info['extension'];
                                if (file_exists($candidate_hd)) {
                                    $hd_src = $candidate_hd;
                                }
                            }
                        ?>
                        <div class="product-card" 
                             data-kategori="<?php echo htmlspecialchars($p['kategori']); ?>" 
                             data-nama="<?php echo htmlspecialchars(strtolower($p['nama_produk'])); ?>"
                             onclick="tambahItem(<?php echo $p['id']; ?>, '<?php echo addslashes($p['nama_produk']); ?>', <?php echo $p['harga']; ?>)">
                            
                            <span class="card-header-badge"><?php echo htmlspecialchars($p['kategori']); ?></span>
                            
                            <div class="product-thumb" 
                                 <?php if (!empty($thumb_src) && file_exists($thumb_src)): ?>
                                     onclick="event.stopPropagation(); bukaModalFotoHD(this);"
                                     data-thumb="<?php echo htmlspecialchars($thumb_src); ?>"
                                     data-hd="<?php echo htmlspecialchars($hd_src); ?>"
                                     data-nama="<?php echo htmlspecialchars($p['nama_produk']); ?>"
                                     data-harga="Rp <?php echo number_format($p['harga'], 0, ',', '.'); ?>"
                                     data-cat="<?php echo htmlspecialchars($p['kategori']); ?>"
                                     data-id="<?php echo $p['id']; ?>"
                                     data-rawharga="<?php echo $p['harga']; ?>"
                                     title="Klik untuk memperbesar foto HD"
                                 <?php endif; ?>>
                                <?php if (!empty($thumb_src) && file_exists($thumb_src)): ?>
                                    <img src="<?php echo htmlspecialchars($thumb_src); ?>" 
                                         alt="<?php echo htmlspecialchars($p['nama_produk']); ?>"
                                         loading="lazy"
                                         style="width:100%;height:100%;object-fit:cover;border-radius:10px;">
                                    <span class="zoom-hint" title="Perbesar HD">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line><line x1="11" y1="8" x2="11" y2="14"></line><line x1="8" y1="11" x2="14" y2="11"></line></svg>
                                    </span>
                                <?php else: ?>
                                    <?php if ($p['kategori'] === 'Makanan'): ?>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#ea580c" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"></path><path d="M7 2v20"></path><path d="M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"></path></svg>
                                    <?php else: ?>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#0284c7" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 8h1a4 4 0 1 1 0 8h-1"></path><path d="M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4Z"></path><line x1="6" y1="2" x2="6" y2="4"></line><line x1="10" y1="2" x2="10" y2="4"></line><line x1="14" y1="2" x2="14" y2="4"></line></svg>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            
                            <h3 class="product-name"><?php echo htmlspecialchars($p['nama_produk']); ?></h3>
                            <div class="product-price">Rp <?php echo number_format($p['harga'], 0, ',', '.'); ?></div>
                            
                            <button class="btn-add-item" type="button" style="pointer-events: none;">
                                <span>+</span> Tambah ke Pesanan
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>

        <!-- Right: Active Order Ticket (Keranjang) -->
        <aside class="cart-panel">
            <div class="cart-header">
                <button type="button" class="btn-back-to-menu" onclick="switchKasirView('menu')">
                    ← Kembali
                </button>
                <h2>
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"></circle><circle cx="19" cy="21" r="1"></circle><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"></path></svg>
                    <span>Pesanan Saat Ini</span>
                </h2>
                <button class="btn-clear-cart" onclick="kosongkanKeranjang()">Kosongkan</button>
            </div>

            <div class="order-meta">
                <div>
                    <label style="font-size: 11px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 2px;">Tipe Pesanan</label>
                    <select id="tipe-pesanan" class="meta-select">
                        <option value="Dine In (Makan di Tempat)">Makan di Tempat</option>
                        <option value="Take Away (Bungkus)">Bungkus Pulang</option>
                    </select>
                </div>
                <div>
                    <label style="font-size: 11px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 2px;">Nomor Meja</label>
                    <select id="no-meja" class="meta-select">
                        <option value="Meja 01">Meja 01</option>
                        <option value="Meja 02">Meja 02</option>
                        <option value="Meja 03">Meja 03</option>
                        <option value="Meja 04">Meja 04</option>
                        <option value="Meja 05">Meja 05</option>
                        <option value="Bungkus / Kasir">Bungkus / Kasir</option>
                    </select>
                </div>
            </div>

            <!-- List Item Keranjang -->
            <div class="cart-items-wrapper" id="cart-items-container">
                <div id="cart-empty-state" style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
                    <div style="display: flex; justify-content: center; margin-bottom: 12px; color: #cbd5e1;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"></circle><circle cx="19" cy="21" r="1"></circle><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"></path></svg>
                    </div>
                    <p style="font-size: 14px; font-weight: 700; color: #475569;">Keranjang Masih Kosong</p>
                    <p style="font-size: 12px; margin-top: 4px; color: #94a3b8;">Klik salah satu menu di sebelah kiri untuk menambahkan pesanan.</p>
                </div>
            </div>

            <!-- Summary & Checkout -->
            <div class="cart-footer">
                <input type="text" id="catatan-order" class="order-notes-input" placeholder="Catatan pesanan (misal: Pedas level 3, es sedikit)...">

                <div class="summary-row">
                    <span>Subtotal:</span>
                    <span id="label-subtotal">Rp 0</span>
                </div>
                <div class="summary-row">
                    <span>Pajak Resto (0%):</span>
                    <span>Rp 0</span>
                </div>
                <div class="summary-total">
                    <span>Total Tagihan:</span>
                    <span id="label-total" style="color: var(--primary);">Rp 0</span>
                </div>

                <!-- Tombol Cepat Pecahan Uang Tunai -->
                <div class="quick-cash-row">
                    <button class="quick-cash-btn" onclick="setUangPas()">Uang Pas</button>
                    <button class="quick-cash-btn" onclick="tambahNominal(10000)">+10k</button>
                    <button class="quick-cash-btn" onclick="tambahNominal(20000)">+20k</button>
                    <button class="quick-cash-btn" onclick="tambahNominal(50000)">+50k</button>
                    <button class="quick-cash-btn" onclick="tambahNominal(100000)">+100k</button>
                </div>

                <div class="cash-input-group">
                    <input type="number" id="input-uang-bayar" class="cash-input" placeholder="Nominal Uang Bayar (Rp)" oninput="hitungKembalianRealtime()">
                </div>

                <div class="change-row">
                    <span>Uang Kembalian:</span>
                    <span id="label-kembalian" style="font-size: 16px;">Rp 0</span>
                </div>

                <button class="btn-checkout" onclick="prosesBayarDanCetak()" style="display:inline-flex; align-items:center; justify-content:center; gap:8px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>
                    <span>Proses Bayar & Cetak Struk</span>
                </button>
            </div>
        </aside>
    </div>

    <!-- Mobile Floating Checkout Bar (Hanya tampil pada mobile/tablet saat ada item) -->
    <div class="mobile-floating-cart" id="mobile-floating-cart" onclick="switchKasirView('cart')">
        <div class="float-cart-info">
            <span class="float-cart-badge" style="display:inline-flex; align-items:center; gap:6px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"></circle><circle cx="19" cy="21" r="1"></circle><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"></path></svg>
                <span><span id="float-cart-count">0</span> item</span>
            </span>
            <span class="float-cart-total" id="float-cart-total">Rp 0</span>
        </div>
        <button type="button" class="btn-float-checkout" style="display:inline-flex; align-items:center; gap:6px;">
            <span>Lihat Pesanan & Bayar</span>
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
        </button>
    </div>

    <!-- Modal Pop-up Cetak Struk Nota Kasir -->
    <div class="modal-overlay" id="modal-struk">
        <div class="receipt-card">
            <div class="receipt-header">
                <h3>FILLAH FOOD</h3>
                <p><strong>SPESIALIS AYAM GEPREK & KULINER</strong><br>
                Jl. Gumukmas, Jember - Jawa Timur<br>
                WhatsApp: 0858-0982-7735</p>
            </div>

            <div class="receipt-meta">
                <div class="receipt-meta-row">
                    <span>No. Nota:</span>
                    <strong id="receipt-no-nota"><?php echo htmlspecialchars($next_no_nota); ?></strong>
                </div>
                <div class="receipt-meta-row">
                    <span>Waktu:</span>
                    <span id="receipt-tanggal"><?php echo htmlspecialchars($waktu_sekarang); ?></span>
                </div>
                <div class="receipt-meta-row">
                    <span>Kasir:</span>
                    <strong id="receipt-kasir"><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
                </div>
                <div class="receipt-meta-row">
                    <span>Layanan:</span>
                    <span id="receipt-meja">Meja 01</span>
                </div>
                <div id="receipt-catatan-wrapper" class="receipt-meta-row" style="display: none; font-style: italic; color: #475569;">
                    <span>Catatan:</span>
                    <span id="receipt-catatan"></span>
                </div>
            </div>

            <div class="receipt-items-header">
                <span>Item Menu</span>
                <span>Subtotal</span>
            </div>

            <div class="receipt-items" id="receipt-items-list">
                <!-- Rincian menu terisi oleh JS -->
            </div>

            <div class="receipt-summary">
                <div class="row">
                    <span>Subtotal:</span>
                    <span id="receipt-subtotal-val">Rp 0</span>
                </div>
                <div class="row">
                    <span>Pajak Resto (0%):</span>
                    <span>Rp 0</span>
                </div>
                <div class="row total-row">
                    <span>TOTAL BELANJA:</span>
                    <span id="receipt-total">Rp 0</span>
                </div>
                <div class="row">
                    <span>Tunai / Diterima:</span>
                    <span id="receipt-bayar">Rp 0</span>
                </div>
                <div class="row" style="font-weight: 700;">
                    <span>Kembalian:</span>
                    <span id="receipt-kembalian">Rp 0</span>
                </div>
            </div>

            <div class="receipt-footer">
                <p>================================</p>
                <p style="font-weight: 700; margin: 4px 0;">*** TERIMA KASIH ***</p>
                <p>Makanan Halal, Nikmat, & Higienis</p>
                <p style="margin-top: 2px;">Kritik & Saran: 0858-0982-7735</p>
            </div>

            <div class="modal-actions">
                <button class="btn-print" onclick="window.print()" style="display:inline-flex; align-items:center; justify-content:center; gap:6px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect width="12" height="8" x="6" y="14"></rect></svg>
                    <span>Cetak Struk</span>
                </button>
                <button class="btn-close-modal" onclick="tutupModalStruk()">Tutup & Pesanan Baru</button>
            </div>
        </div>
    </div>

    <!-- Modal Foto HD Lightbox (Ringan & Cepat: Load Thumb Dulu, Swap HD Otomatis) -->
    <div id="modal-foto-hd" class="hd-modal-overlay" onclick="tutupModalFotoHD(event)">
        <div class="hd-modal-card" onclick="event.stopPropagation()">
            <button type="button" class="hd-modal-close" onclick="tutupModalFotoHD()" title="Tutup Pratinjau (Esc)">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
            
            <div class="hd-modal-img-wrap">
                <span id="hd-modal-badge" class="hd-modal-badge">
                    <span class="spinner-dot"></span> Memuat HD...
                </span>
                <img id="hd-modal-img" class="hd-modal-img" src="" alt="Foto Produk HD">
            </div>
            
            <div class="hd-modal-body">
                <div class="hd-modal-head-row">
                    <h3 id="hd-modal-title" class="hd-modal-title">Nama Menu</h3>
                    <span id="hd-modal-cat" class="hd-cat-badge makanan">Makanan</span>
                </div>
                
                <div class="hd-modal-price-row">
                    <div>
                        <div style="font-size: 11px; color: var(--text-muted); font-weight: 600;">Harga Satuan</div>
                        <div id="hd-modal-price" class="hd-modal-price">Rp 0</div>
                    </div>
                    <div style="font-size: 11.5px; color: #16a34a; font-weight: 700; display:flex; align-items:center; gap:4px;">
                        <span style="width:7px; height:7px; background:#16a34a; border-radius:50%; display:inline-block;"></span> Menu Aktif POS
                    </div>
                </div>
                
                <div class="hd-modal-actions">
                    <button type="button" class="btn-hd-action" id="hd-modal-action-btn">
                        <span>+</span> Tambah ke Pesanan Kasir
                    </button>
                    <button type="button" class="btn-hd-close-sec" onclick="tutupModalFotoHD()">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="script.js?v=<?php echo time(); ?>"></script>
</body>
</html>