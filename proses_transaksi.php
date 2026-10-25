<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require 'config.php';

// Pastikan hanya kasir atau admin yang dapat memproses transaksi
if (!isset($_SESSION['username'])) {
    echo json_encode(["status" => "error", "message" => "Sesi Anda telah berakhir, silakan login kembali."]);
    exit();
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (empty($data) || empty($data['pesanan'])) {
    echo json_encode(["status" => "error", "message" => "Keranjang pesanan masih kosong!"]);
    exit();
}

$total_bayar   = floatval($data['total_bayar']);
$uang_diterima = floatval($data['uang_diterima']);
$no_meja       = isset($data['no_meja']) ? htmlspecialchars(trim($data['no_meja'])) : 'Bungkus/Takeaway';
$catatan       = isset($data['catatan']) ? htmlspecialchars(trim($data['catatan'])) : '';
$pesanan       = $data['pesanan'];

if ($uang_diterima < $total_bayar) {
    echo json_encode(["status" => "error", "message" => "Uang pembayaran kurang dari total belanja!"]);
    exit();
}

$kembalian = $uang_diterima - $total_bayar;

// Mulai Database Transaction (ACID)
$conn->begin_transaction();

try {
    // 1. VALIDASI KETERSEDIAAN STOK BAHAN BAKU
    // Cek apakah semua bahan baku mencukupi sebelum memproses transaksi
    foreach ($pesanan as $item) {
        $id_menu = intval($item['id_menu']);
        $qty_menu = intval($item['qty']);

        $stmt_cek = $conn->prepare("
            SELECT r.id_bahan, r.jumlah_dibutuhkan, b.nama_bahan, b.stok, b.satuan 
            FROM resep r 
            JOIN bahan_baku b ON r.id_bahan = b.id 
            WHERE r.id_menu = ?
        ");
        $stmt_cek->bind_param("i", $id_menu);
        $stmt_cek->execute();
        $res_cek = $stmt_cek->get_result();

        while ($row = $res_cek->fetch_assoc()) {
            $total_dibutuhkan = $row['jumlah_dibutuhkan'] * $qty_menu;
            if ($row['stok'] < $total_dibutuhkan) {
                throw new Exception("Stok bahan baku '{$row['nama_bahan']}' tidak mencukupi! Tersisa {$row['stok']} {$row['satuan']}, dibutuhkan {$total_dibutuhkan} {$row['satuan']}.");
            }
        }
    }

    // 2. SIMPAN KE TABEL TRANSAKSI UTAMA
    $stmt_trx = $conn->prepare("INSERT INTO transaksi (total_pembayaran, uang_diterima, kembalian) VALUES (?, ?, ?)");
    $stmt_trx->bind_param("ddd", $total_bayar, $uang_diterima, $kembalian);
    $stmt_trx->execute();
    $id_transaksi = $conn->insert_id;

    // 3. SIMPAN DETAIL TRANSAKSI & KURANGI STOK BAHAN BAKU (BOM)
    $stmt_detail = $conn->prepare("INSERT INTO detail_transaksi (id_transaksi, id_menu, qty, subtotal) VALUES (?, ?, ?, ?)");
    $stmt_kurang = $conn->prepare("UPDATE bahan_baku SET stok = stok - ? WHERE id = ?");

    foreach ($pesanan as $item) {
        $id_menu = intval($item['id_menu']);
        $qty = intval($item['qty']);
        $subtotal = floatval($item['harga']) * $qty;

        // Simpan baris detail transaksi
        $stmt_detail->bind_param("iiid", $id_transaksi, $id_menu, $qty, $subtotal);
        $stmt_detail->execute();

        // Cari resep bahan baku untuk menu ini
        $stmt_resep = $conn->prepare("SELECT id_bahan, jumlah_dibutuhkan FROM resep WHERE id_menu = ?");
        $stmt_resep->bind_param("i", $id_menu);
        $stmt_resep->execute();
        $res_resep = $stmt_resep->get_result();

        while ($resep = $res_resep->fetch_assoc()) {
            $id_bahan = $resep['id_bahan'];
            $stok_berkurang = $resep['jumlah_dibutuhkan'] * $qty;

            // Potong stok di tabel bahan_baku
            $stmt_kurang->bind_param("di", $stok_berkurang, $id_bahan);
            $stmt_kurang->execute();
        }
    }

    // Komit transaksi database jika semua tahap sukses
    $conn->commit();

    echo json_encode([
        "status"        => "success",
        "message"       => "Transaksi berhasil diproses!",
        "id_transaksi"  => $id_transaksi,
        "no_nota"       => "#TRX-" . str_pad($id_transaksi, 4, "0", STR_PAD_LEFT),
        "tanggal"       => date('d/m/Y H:i:s') . ' WIB',
        "kasir"         => $_SESSION['username'],
        "no_meja"       => $no_meja,
        "catatan"       => $catatan,
        "total_bayar"   => $total_bayar,
        "uang_diterima" => $uang_diterima,
        "kembalian"     => $kembalian,
        "rincian"       => $pesanan
    ]);

} catch (Exception $e) {
    // Batalkan seluruh perubahan jika terjadi galat (Rollback)
    $conn->rollback();
    echo json_encode([
        "status"  => "error",
        "message" => $e->getMessage()
    ]);
}
?>