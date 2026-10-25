// State Keranjang Belanja Kasir
let keranjang = [];
let totalBelanja = 0;

// Format Rupiah
function formatRupiah(angka) {
    return 'Rp ' + Number(angka).toLocaleString('id-ID');
}

// 1. Tambah Menu ke Keranjang
function tambahItem(id_menu, nama, harga) {
    const index = keranjang.findIndex(item => item.id_menu === id_menu);
    if (index > -1) {
        keranjang[index].qty += 1;
    } else {
        keranjang.push({
            id_menu: id_menu,
            nama: nama,
            harga: Number(harga),
            qty: 1
        });
    }
    renderKeranjang();
}

// 2. Ubah Jumlah Porsi (Stepper)
function ubahQty(id_menu, delta) {
    const index = keranjang.findIndex(item => item.id_menu === id_menu);
    if (index > -1) {
        keranjang[index].qty += delta;
        if (keranjang[index].qty <= 0) {
            keranjang.splice(index, 1);
        }
    }
    renderKeranjang();
}

// 3. Hapus Item dari Keranjang
function hapusItem(id_menu) {
    keranjang = keranjang.filter(item => item.id_menu !== id_menu);
    renderKeranjang();
}

// 4. Kosongkan Keranjang
function kosongkanKeranjang() {
    if (keranjang.length === 0) return;
    if (confirm("Apakah Anda yakin ingin membatalkan semua pesanan ini?")) {
        keranjang = [];
        renderKeranjang();
        resetInputBayar();
    }
}

// 5. Perbarui Tampilan Keranjang
function renderKeranjang() {
    const container = document.getElementById('cart-items-container');
    container.innerHTML = '';

    if (keranjang.length === 0) {
        container.innerHTML = `
            <div id="cart-empty-state" style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
                <div style="font-size: 36px; margin-bottom: 8px;">🛒</div>
                <p style="font-size: 13px; font-weight: 600;">Keranjang belanja masih kosong</p>
                <p style="font-size: 11px; margin-top: 4px;">Klik salah satu menu di sebelah kiri untuk menambahkan pesanan.</p>
            </div>
        `;
        totalBelanja = 0;
    } else {
        totalBelanja = 0;
        keranjang.forEach(item => {
            const subtotal = item.harga * item.qty;
            totalBelanja += subtotal;

            const row = document.createElement('div');
            row.className = 'cart-item-row';
            row.innerHTML = `
                <div class="item-info">
                    <div class="item-title">${item.nama}</div>
                    <div class="item-subtotal">${formatRupiah(item.harga)} x ${item.qty} = ${formatRupiah(subtotal)}</div>
                </div>
                <div class="stepper">
                    <button type="button" class="step-btn" onclick="ubahQty(${item.id_menu}, -1)">-</button>
                    <span class="step-qty">${item.qty}</span>
                    <button type="button" class="step-btn" onclick="ubahQty(${item.id_menu}, 1)">+</button>
                    <button type="button" style="background:none; border:none; color:#dc2626; cursor:pointer; font-size:14px; margin-left:4px;" onclick="hapusItem(${item.id_menu})" title="Hapus">🗑️</button>
                </div>
            `;
            container.appendChild(row);
        });
    }

    // Update label total
    document.getElementById('label-subtotal').textContent = formatRupiah(totalBelanja);
    document.getElementById('label-total').textContent = formatRupiah(totalBelanja);

    hitungKembalianRealtime();
}

// 6. Tombol Cepat Pecahan Uang
function setUangPas() {
    document.getElementById('input-uang-bayar').value = totalBelanja;
    hitungKembalianRealtime();
}

function tambahNominal(nominal) {
    const input = document.getElementById('input-uang-bayar');
    const valSaatIni = Number(input.value) || 0;
    input.value = valSaatIni + nominal;
    hitungKembalianRealtime();
}

function resetInputBayar() {
    document.getElementById('input-uang-bayar').value = '';
    document.getElementById('label-kembalian').textContent = 'Rp 0';
    document.getElementById('catatan-order').value = '';
}

// 7. Hitung Kembalian Realtime
function hitungKembalianRealtime() {
    const inputUang = Number(document.getElementById('input-uang-bayar').value) || 0;
    const labelKembali = document.getElementById('label-kembalian');

    if (inputUang >= totalBelanja && totalBelanja > 0) {
        const kembalian = inputUang - totalBelanja;
        labelKembali.textContent = formatRupiah(kembalian);
        labelKembali.style.color = 'var(--success)';
    } else {
        labelKembali.textContent = 'Rp 0';
        labelKembali.style.color = 'var(--text-muted)';
    }
}

// 8. Filter Kategori Menu
function filterKategori(kategori) {
    const buttons = document.querySelectorAll('.cat-btn');
    buttons.forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');

    const cards = document.querySelectorAll('.product-card');
    cards.forEach(card => {
        const cat = card.getAttribute('data-kategori');
        if (kategori === 'Semua' || cat === kategori) {
            card.style.display = 'flex';
        } else {
            card.style.display = 'none';
        }
    });
}

// 9. Pencarian Menu Live
function cariMenuLive() {
    const keyword = document.getElementById('cari-menu').value.toLowerCase().trim();
    const cards = document.querySelectorAll('.product-card');

    cards.forEach(card => {
        const nama = card.getAttribute('data-nama');
        if (nama.includes(keyword)) {
            card.style.display = 'flex';
        } else {
            card.style.display = 'none';
        }
    });
}

// 10. Proses Transaksi Kasir & Buka Modal Struk
function prosesBayarDanCetak() {
    if (keranjang.length === 0) {
        alert("Keranjang pesanan masih kosong!");
        return;
    }

    const uangBayar = Number(document.getElementById('input-uang-bayar').value);
    if (!uangBayar || uangBayar < totalBelanja) {
        alert("Nominal uang pembayaran kurang dari total belanja!");
        document.getElementById('input-uang-bayar').focus();
        return;
    }

    const noMeja = document.getElementById('no-meja').value;
    const tipePesanan = document.getElementById('tipe-pesanan').value;
    const catatan = document.getElementById('catatan-order').value;

    const payload = {
        total_bayar: totalBelanja,
        uang_diterima: uangBayar,
        no_meja: `${tipePesanan} - ${noMeja}`,
        catatan: catatan,
        pesanan: keranjang
    };

    // Kirim AJAX fetch ke backend
    fetch('proses_transaksi.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            bukaModalStruk(data);
        } else {
            alert("⚠️ Transaksi Gagal:\n" + data.message);
        }
    })
    .catch(err => {
        console.error(err);
        alert("Terjadi kesalahan koneksi server: " + err.message);
    });
}

// 11. Tampilkan Pop-up Struk Nota
function bukaModalStruk(data) {
    document.getElementById('receipt-no-nota').textContent = data.no_nota;
    document.getElementById('receipt-tanggal').textContent = data.tanggal;
    document.getElementById('receipt-kasir').textContent = data.kasir;
    document.getElementById('receipt-meja').textContent = data.no_meja;

    if (data.catatan && data.catatan.trim() !== '') {
        document.getElementById('receipt-catatan').textContent = data.catatan;
        document.getElementById('receipt-catatan-wrapper').style.display = 'block';
    } else {
        document.getElementById('receipt-catatan-wrapper').style.display = 'none';
    }

    const itemsList = document.getElementById('receipt-items-list');
    itemsList.innerHTML = '';
    data.rincian.forEach(item => {
        const row = document.createElement('div');
        row.style.display = 'flex';
        row.style.justifyContent = 'space-between';
        row.style.marginBottom = '3px';
        row.innerHTML = `
            <span>${item.nama} x${item.qty}</span>
            <span>${formatRupiah(item.harga * item.qty)}</span>
        `;
        itemsList.appendChild(row);
    });

    document.getElementById('receipt-total').textContent = formatRupiah(data.total_bayar);
    document.getElementById('receipt-bayar').textContent = formatRupiah(data.uang_diterima);
    document.getElementById('receipt-kembalian').textContent = formatRupiah(data.kembalian);

    // Tampilkan modal
    document.getElementById('modal-struk').style.display = 'flex';
}

// 12. Tutup Modal Struk & Reset untuk Transaksi Baru
function tutupModalStruk() {
    document.getElementById('modal-struk').style.display = 'none';
    keranjang = [];
    renderKeranjang();
    resetInputBayar();
}