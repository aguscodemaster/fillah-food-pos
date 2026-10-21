let totalBelanja = 0;
let pesanan = []; 

// Fungsi untuk menambahkan menu ke keranjang
function tambahPesanan(id_menu, nama, harga) {
    // Cek apakah item sudah ada di keranjang
    let itemAda = pesanan.find(item => item.id_menu === id_menu);
    if (itemAda) {
        itemAda.qty += 1; // Jika ada, tambah jumlahnya
    } else {
        pesanan.push({ id_menu: id_menu, nama: nama, harga: harga, qty: 1 }); // Jika belum, masukkan sebagai item baru
    }
    
    totalBelanja += harga;
    updateTampilanKasir();
}

// Fungsi untuk memperbarui tampilan keranjang di sebelah kanan
function updateTampilanKasir() {
    const daftarPesanan = document.getElementById('daftar-pesanan');
    daftarPesanan.innerHTML = ''; // Kosongkan daftar sebelum diperbarui
    
    if (pesanan.length === 0) {
        daftarPesanan.innerHTML = '<p style="color: #888; font-style: italic;">Belum ada pesanan...</p>';
    } else {
        pesanan.forEach((item) => {
            const li = document.createElement('li');
            li.textContent = `${item.nama} (x${item.qty}) - Rp ${item.harga * item.qty}`;
            daftarPesanan.appendChild(li);
        });
    }
    
    // Perbarui teks total belanja
    document.getElementById('total-belanja').textContent = totalBelanja;
}

// Fungsi untuk memproses pembayaran ke database
function prosesBayar() {
    const uangBayar = parseInt(document.getElementById('uang-bayar').value);
    
    if (pesanan.length === 0) {
        alert("Keranjang masih kosong!"); 
        return;
    }
    if (isNaN(uangBayar) || uangBayar < totalBelanja) {
        alert("Nominal pembayaran kurang atau tidak valid!"); 
        return;
    }

    const dataTransaksi = {
        total_bayar: totalBelanja,
        uang_diterima: uangBayar,
        pesanan: pesanan
    };

    // Mengirim data pesanan ke PHP (Back-End)
    fetch('proses_transaksi.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(dataTransaksi)
    })
    .then(response => response.json())
    .then(data => {
        if(data.status === "success") {
            const kembalian = uangBayar - totalBelanja;
            document.getElementById('kembalian').textContent = kembalian;
            alert("Transaksi Berhasil! Stok otomatis dipotong.\nKembalian: Rp " + kembalian);
            
            // Reset Kasir untuk pesanan berikutnya
            totalBelanja = 0;
            pesanan = [];
            updateTampilanKasir();
            document.getElementById('uang-bayar').value = '';
        } else {
            alert("Terjadi Error: " + data.message);
        }
    })
    .catch(error => console.error('Error:', error));
}