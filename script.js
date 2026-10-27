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

// Kompatibilitas fungsi
window.tambahItem = tambahItem;
window.tambahPesanan = tambahItem;

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
                <div style="display: flex; justify-content: center; margin-bottom: 12px; color: #cbd5e1;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"></circle><circle cx="19" cy="21" r="1"></circle><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"></path></svg>
                </div>
                <p style="font-size: 14px; font-weight: 700; color: #475569;">Keranjang Masih Kosong</p>
                <p style="font-size: 12px; margin-top: 4px; color: #94a3b8;">Klik salah satu menu di sebelah kiri untuk menambahkan pesanan.</p>
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
                    <button type="button" style="background:none; border:none; color:#dc2626; cursor:pointer; padding:4px; display:inline-flex; align-items:center; justify-content:center; margin-left:4px;" onclick="hapusItem(${item.id_menu})" title="Hapus dari pesanan">
                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg>
                    </button>
                </div>
            `;
            container.appendChild(row);
        });
    }

    // Update total count & label
    const totalQty = keranjang.reduce((sum, item) => sum + item.qty, 0);
    document.querySelectorAll('.cart-badge-count').forEach(el => {
        el.textContent = totalQty;
    });

    const floatBar = document.getElementById('mobile-floating-cart');
    if (floatBar) {
        const floatCount = document.getElementById('float-cart-count');
        const floatTotal = document.getElementById('float-cart-total');
        if (floatCount) floatCount.textContent = totalQty;
        if (floatTotal) floatTotal.textContent = formatRupiah(totalBelanja);
        if (totalQty > 0) {
            floatBar.classList.add('has-items');
        } else {
            floatBar.classList.remove('has-items');
        }
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
            alert("Transaksi Gagal:\n" + data.message);
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
    document.getElementById('receipt-tanggal').textContent = data.tanggal || (new Date().toLocaleString('id-ID') + ' WIB');
    document.getElementById('receipt-kasir').textContent = data.kasir;
    document.getElementById('receipt-meja').textContent = data.no_meja;

    const catatanWrapper = document.getElementById('receipt-catatan-wrapper');
    if (catatanWrapper) {
        if (data.catatan && data.catatan.trim() !== '') {
            document.getElementById('receipt-catatan').textContent = data.catatan;
            catatanWrapper.style.display = 'flex';
        } else {
            catatanWrapper.style.display = 'none';
        }
    }

    const itemsList = document.getElementById('receipt-items-list');
    itemsList.innerHTML = '';
    if (data.rincian && Array.isArray(data.rincian)) {
        data.rincian.forEach(item => {
            const row = document.createElement('div');
            row.className = 'receipt-item-row';
            row.innerHTML = `
                <div class="receipt-item-name">${item.nama}</div>
                <div class="receipt-item-detail">
                    <span>${item.qty} x ${formatRupiah(item.harga)}</span>
                    <span class="receipt-item-subtotal">${formatRupiah(item.harga * item.qty)}</span>
                </div>
            `;
            itemsList.appendChild(row);
        });
    }

    const subtotalEl = document.getElementById('receipt-subtotal-val');
    if (subtotalEl) {
        subtotalEl.textContent = formatRupiah(data.total_bayar);
    }

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
    switchKasirView('menu');
}

// 13. Navigasi Tampilan Responsive Layar Kasir (Mobile / Tablet)
function switchKasirView(view) {
    const tabMenu = document.getElementById('tab-btn-menu');
    const tabCart = document.getElementById('tab-btn-cart');
    if (view === 'cart') {
        document.body.classList.add('mobile-view-cart');
        if (tabMenu) tabMenu.classList.remove('active');
        if (tabCart) tabCart.classList.add('active');
    } else {
        document.body.classList.remove('mobile-view-cart');
        if (tabMenu) tabMenu.classList.add('active');
        if (tabCart) tabCart.classList.remove('active');
    }
}
window.switchKasirView = switchKasirView;

// --- 14. Lightbox Modal Foto HD Cepat & Ringan (Progressive Swap) ---
function bukaModalFotoHD(el) {
    if (!el) return;
    const thumbSrc = el.getAttribute('data-thumb') || '';
    const hdSrc    = el.getAttribute('data-hd') || thumbSrc;
    const nama     = el.getAttribute('data-nama') || 'Foto Produk';
    const harga    = el.getAttribute('data-harga') || '';
    const cat      = el.getAttribute('data-cat') || '';
    const prodId   = el.getAttribute('data-id') || null;
    const rawHarga = el.getAttribute('data-rawharga') || null;

    const modal      = document.getElementById('modal-foto-hd');
    if (!modal) return;
    const modalImg   = document.getElementById('hd-modal-img');
    const modalTitle = document.getElementById('hd-modal-title');
    const modalPrice = document.getElementById('hd-modal-price');
    const modalCat   = document.getElementById('hd-modal-cat');
    const modalBadge = document.getElementById('hd-modal-badge');
    const btnAction  = document.getElementById('hd-modal-action-btn');

    if (modalTitle) modalTitle.textContent = nama;
    if (modalPrice) modalPrice.textContent = harga;
    if (modalCat) {
        modalCat.textContent = cat;
        if (cat === 'Makanan') {
            modalCat.className = 'hd-cat-badge makanan';
        } else if (cat === 'Minuman') {
            modalCat.className = 'hd-cat-badge minuman';
        } else {
            modalCat.className = 'hd-cat-badge banner';
        }
    }

    // Konfigurasi Tombol Tambah ke Pesanan (di Kasir POS)
    if (btnAction) {
        if (prodId && window.tambahItem) {
            btnAction.style.display = 'inline-flex';
            btnAction.onclick = function() {
                window.tambahItem(parseInt(prodId), nama, parseFloat(rawHarga));
                tutupModalFotoHD();
            };
        } else {
            btnAction.style.display = 'none';
        }
    }

    // Tampilkan thumbnail instan terlebih dahulu (ringan, tanpa menunggu)
    if (modalImg) {
        modalImg.src = thumbSrc;
        modalImg.style.filter = 'blur(6px)';
    }
    if (modalBadge) {
        modalBadge.innerHTML = '<span class="spinner-dot"></span> Memuat HD...';
    }

    modal.classList.add('active');
    document.body.style.overflow = 'hidden';

    // Muat versi HD di latar belakang secara asinkron
    const hdLoader = new Image();
    hdLoader.src = hdSrc;
    hdLoader.onload = function() {
        if (modal.classList.contains('active') && modalImg) {
            modalImg.src = hdSrc;
            modalImg.style.filter = 'blur(0)';
            if (modalBadge) modalBadge.innerHTML = '✨ Resolusi Tinggi (HD)';
        }
    };
    hdLoader.onerror = function() {
        if (modalImg) modalImg.style.filter = 'blur(0)';
        if (modalBadge) modalBadge.innerHTML = 'Foto Produk Asli';
    };
}

function bukaModalBannerHD() {
    bukaModalFotoHD({
        getAttribute: function(attr) {
            if (attr === 'data-thumb') return 'uploads/menu_banner_hd.png';
            if (attr === 'data-hd') return 'uploads/menu_banner_hd.png';
            if (attr === 'data-nama') return 'Banner Resmi Fillah Food & Drink';
            if (attr === 'data-harga') return 'Daftar Harga & Menu Lengkap';
            if (attr === 'data-cat') return 'Banner Resmi';
            return '';
        }
    });
}

function tutupModalFotoHD(event) {
    if (event && event.target && event.target !== event.currentTarget) return;
    const modal = document.getElementById('modal-foto-hd');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

window.bukaModalFotoHD = bukaModalFotoHD;
window.bukaModalBannerHD = bukaModalBannerHD;
window.tutupModalFotoHD = tutupModalFotoHD;

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') tutupModalFotoHD();
});