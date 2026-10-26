# Sistem Informasi Persediaan dan Kasir Terintegrasi (POS)
## UMKM Fillah Food - Gumukmas, Jember
**Mata Kuliah:** STSI4440 - Capstone Project  
**Program Studi:** S1 Sistem Informasi, Fakultas Sains dan Teknologi  
**Institusi:** Universitas Terbuka (Tahun 2026)  
**Kelompok:** Kelas 84 Kelompok 3C  

---

## 👥 Tim Pengembang (Kelompok 3C)
1. **Argi Eko Saputro** (NIM: 049149751)
2. **Aisyah Marta Dewi** (NIM: 049918958)
3. **Agus Bonari** (NIM: 050444357)
4. **Hilma Arum Maisyaroh** (NIM: 050769505)

---

## 📌 Deskripsi Sistem
Aplikasi web responsif yang mengintegrasikan transaksi kasir (Point of Sale) dengan manajemen persediaan bahan baku secara otomatis (*real-time*). Setiap transaksi penjualan yang diselesaikan di kasir secara otomatis memotong stok bahan baku terkait berdasarkan formula resep menu (*Bill of Materials / BOM*).

### Fitur Utama:
1. **Autentikasi & RBAC:** Hak akses terpisah untuk **Admin** (pemilik kedai) dan **Kasir**.
2. **Kasir (POS):** Input pesanan cepat, kalkulasi otomatis, hitung uang kembalian, dan cetak struk nota.
3. **Pemotongan Stok Otomatis (BOM):** Pengurangan saldo bahan baku langsung saat pesanan dibayar.
4. **Manajemen Persediaan:** Monitoring stok bahan baku dan peringatan bahan menipis (*low-stock alert*).
5. **Dasbor Laporan:** Rekapitulasi transaksi harian dan mutasi stok.

---

## ⚙️ Panduan Instalasi & Menjalankan di XAMPP

1. **Unduh / Clone Repositori:**
   Pindahkan folder proyek ini ke dalam folder `htdocs` XAMPP Anda:
   ```text
   C:\xampp\htdocs\fillah-food-pos
   ```

2. **Nyalakan XAMPP:**
   Buka **XAMPP Control Panel**, lalu klik **Start** pada modul **Apache** dan **MySQL**.

3. **Impor Database:**
   * Buka peramban (*browser*) dan masuk ke: `http://localhost/phpmyadmin/`
   * Buat database baru bernama: `fillah_food`
   * Klik tab **Import**, pilih file **`database.sql`** yang ada di dalam folder proyek, lalu klik **Go / Kirim**.

4. **Akses Aplikasi Web:**
   Buka browser dan kunjungi:
   ```text
   http://localhost/fillah-food-pos/
   ```

### 🔑 Akun Login Bawaan (Default):
* **Owner (Pemilik - Hak Akses Penuh & Laporan):**
  * Username: `owner_nihayatus`
  * Password: `12345`
* **Admin (Staf Gudang & Persediaan):**
  * Username: `admin_persediaan`
  * Password: `12345`
* **Kasir (Aisyah Marta Dewi - Transaksi POS):**
  * Username: `kasir_1` *(atau ketik `aisyah`)*
  * Password: `12345`

---

## 🚨 SOP WAJIB KOLABORASI GIT (AGAR TIDAK SALING TIMPA!)

> **⚠️ ATURAN EMAS: SELALU PULL SEBELUM CODING, DAN SELALU PULL SEBELUM PUSH!**  
> Jangan sampai Anda menimpa kodingan teman yang baru di-push dengan file lama di laptop Anda.

### 🔄 1. Sebelum Mulai Coding (Wajib Dilakukan Tiap Hari):
Tarik perubahan terbaru yang sudah dikerjakan teman:
```bash
git pull origin main
```

### 💻 2. Saat Selesai Menambah / Memperbaiki Fitur:
Periksa file apa saja yang Anda ubah:
```bash
git status
```
Tambahkan file yang sudah diedit ke staging area:
```bash
git add .
```
Simpan perubahan dengan pesan commit yang jelas:
```bash
git commit -m "feat: tambah tabel resep menu dan relasi bahan"
```

### 🚀 3. Sesaat Sebelum Melakukan Push (PENTING!):
Sebelum push, pastikan tarik lagi perubahan terbaru (siapa tahu ada teman yang baru saja push beberapa menit lalu):
```bash
git pull origin main
```
Jika tidak ada konflik (*Already up to date*), langsung push:
```bash
git push origin main
```

---

## 🛑 PANTANGAN KERAS (DILARANG DILAKUKAN):
1. **DILARANG KERAS menjalankan `git push --force` atau `git push -f`!**  
   Perintah ini akan **menghapus permanen** kodingan teman Anda di GitHub dan menggantinya paksa dengan file di laptop Anda.
2. **Dilarang langsung mengedit file yang sedang dikerjakan teman** tanpa koordinasi di grup WhatsApp terlebih dahulu.
3. **Jika terjadi konflik (*Merge Conflict*):** Jangan panik dan jangan asal timpa. Buka file yang berkonflik, diskusikan dengan teman yang bersangkutan, simpan bagian yang benar, lalu commit ulang.

---

## 📝 Format Pesan Commit yang Rapi (Standar Industri):
Gunakan awalan berikut agar riwayat commit di GitHub dinilai bagus oleh dosen/tutor:
* `feat:` jika menambah fitur baru (contoh: `git commit -m "feat: buat halaman input stok bahan masuk"`)
* `fix:` jika memperbaiki bug/error (contoh: `git commit -m "fix: perbaiki query pemotongan stok bahan"`)
* `style:` jika merapikan tampilan CSS/HTML (contoh: `git commit -m "style: perbaiki tata letak struk kasir"`)
* `docs:` jika mengupdate dokumentasi atau laporan (contoh: `git commit -m "docs: perbarui panduan instalasi di README"`)
