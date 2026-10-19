# Daftar Perbaikan PCB Barcode Print

Dokumen ini berisi daftar rekomendasi perbaikan berdasarkan analisa awal. Tidak ada perubahan kode aplikasi yang dilakukan.

## Prioritas Tinggi

- Backup project dan database sebelum perubahan apa pun.
- Pindahkan koneksi database dari hardcoded di `Login1.php`, `Register1.php`, `view.php`, `userview.php`, dan `print-barcode.php` ke satu file konfigurasi.
- Jangan gunakan user MySQL `root` untuk aplikasi; buat user khusus dengan akses terbatas ke database aplikasi.
- Ganti query SQL manual ke prepared statement, terutama untuk login, register, upload, split batch, delete, dan record print.
- Tambahkan CSRF token untuk semua form `POST`, terutama print, upload, register, tambah custom EV, dan delete data.
- Escape semua output HTML menggunakan `htmlspecialchars()`, terutama data WO, batch, nomor barcode, username, dan pesan error/success.

## Prioritas Sedang

- Validasi input lebih ketat, seperti format WO, nomor barcode, batch ID, tanggal produksi, dan tipe EV Charger.
- Batasi file upload dengan validasi ekstensi, MIME type, ukuran file, dan isi file.
- Pisahkan akses fitur admin dan user dengan lebih jelas agar fungsi admin tidak dapat dipanggil oleh role user.
- Perkuat session login dengan `session_regenerate_id(true)` setelah login berhasil.
- Perbaiki logout agar membersihkan cookie session selain menjalankan `session_destroy()`.
- Jangan simpan dump database produksi di repo, terutama jika berisi user, hash password, atau data produksi.

## Prioritas Rendah

- Pecah file besar seperti `view.php` dan `userview.php` menjadi modul kecil: auth, database, upload, batch, print, dan template.
- Kurangi duplikasi kode antara halaman admin dan halaman user.
- Standarkan nama file, misalnya konsisten memakai `login.php` atau `Login1.php`.
- Buat dokumentasi setup aplikasi, termasuk cara import database, konfigurasi koneksi, login default, dan cara menjalankan di XAMPP.
- Tambahkan logging error agar detail koneksi atau error database tidak langsung tampil ke user.
- Rapikan typo UI, misalnya `EV Chager` menjadi `EV Charger`.

## Urutan Aman Pengerjaan

1. Backup project dan database.
2. Buat dokumentasi setup dan konfigurasi database terpusat.
3. Tambahkan escaping output HTML dan validasi input ringan.
4. Ubah query penting ke prepared statement.
5. Tambahkan CSRF token dan perkuat session.
6. Refactor file besar setelah aplikasi stabil.

## Catatan Produksi

Jika aplikasi sedang berjalan dan dipakai produksi, hindari perubahan besar sekaligus. Mulai dari perbaikan yang risikonya rendah dan mudah di-rollback, seperti dokumentasi, backup, konfigurasi database, escaping output, dan validasi ringan.
