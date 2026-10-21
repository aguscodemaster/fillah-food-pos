<?php
// Memanggil file koneksi database
include 'config.php';

// Menerima data pesanan dari JavaScript (Front-End)
$data = json_decode(file_get_contents('php://input'), true);

if (!empty($data)) {
    $total_bayar = $data['total_bayar'];
    $uang_diterima = $data['uang_diterima'];
    $kembalian = $uang_diterima - $total_bayar; // Perhitungan kembalian yang benar
    $pesanan = $data['pesanan']; 

    // Mulai Database Transaction (agar aman jika ada error di tengah proses)
    $conn->begin_transaction();

    try {
        // 1. Simpan data ke tabel transaksi utama
        $stmt = $conn->prepare("INSERT INTO transaksi (total_pembayaran, uang_diterima, kembalian) VALUES (?, ?, ?)");
        $stmt->bind_param("ddd", $total_bayar, $uang_diterima, $kembalian);
        $stmt->execute();
        $id_transaksi = $conn->insert_id; // Mengambil ID transaksi yang baru saja dibuat

        // 2. Simpan setiap menu yang dibeli & Potong Stok Bahan Baku
        foreach ($pesanan as $item) {
            $id_menu = $item['id_menu'];
            $qty = $item['qty']; // Jumlah porsi
            $subtotal = $item['harga'] * $qty;

            // Simpan ke tabel detail_transaksi
            $stmt_detail = $conn->prepare("INSERT INTO detail_transaksi (id_transaksi, id_menu, qty, subtotal) VALUES (?, ?, ?, ?)");
            $stmt_detail->bind_param("iiid", $id_transaksi, $id_menu, $qty, $subtotal);
            $stmt_detail->execute();

            // 3. LOGIKA PEMOTONGAN STOK BERDASARKAN RESEP
            $sql_resep = "SELECT id_bahan, jumlah_dibutuhkan FROM resep WHERE id_menu = $id_menu";
            $result_resep = $conn->query($sql_resep);

            while ($resep = $result_resep->fetch_assoc()) {
                $id_bahan = $resep['id_bahan'];
                
                // Total bahan dikurangi = (Kebutuhan per porsi x Jumlah porsi dibeli)
                $stok_berkurang = $resep['jumlah_dibutuhkan'] * $qty; 
                
                // Kurangi stok di database bahan_baku
                $stmt_stok = $conn->prepare("UPDATE bahan_baku SET stok = stok - ? WHERE id = ?");
                $stmt_stok->bind_param("di", $stok_berkurang, $id_bahan);
                $stmt_stok->execute();
            }
        }

        // Jika semua langkah di atas berhasil, simpan permanen ke database
        $conn->commit();
        
        // Kirim jawaban "sukses" kembali ke JavaScript
        echo json_encode(["status" => "success", "message" => "Berhasil!"]);

    } catch (Exception $e) {
        // Jika ada sistem yang gagal, batalkan semua perubahan stok (Rollback)
        $conn->rollback();
        
        // Kirim jawaban "error" ke JavaScript
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Data kosong!"]);
}
?>