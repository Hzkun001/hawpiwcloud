# hawpiwcloud

hawpiwcloud adalah aplikasi penyimpanan berkas sederhana berbasis PHP untuk mengunggah, melihat pratinjau, mengunduh, dan menghapus file langsung dari browser. Aplikasi ini tidak memakai database; semua berkas disimpan di folder `uploads/` dan dikelola dari satu dasbor yang responsif dan bersih.

## Fitur

- Unggah berkas melalui form atau drag and drop.
- Pratinjau otomatis untuk gambar sebelum unggah.
- Daftar berkas tersimpan dengan informasi ukuran dan waktu perubahan.
- Unduh berkas langsung dari tabel daftar file.
- Hapus berkas dengan konfirmasi sebelum tindakan dijalankan.
- Proteksi CSRF pada form unggah dan hapus.
- Validasi ukuran unggahan dengan batas maksimal 20 MB per berkas.

## Kebutuhan

- PHP 8.0 atau lebih baru.
- Web server seperti Apache, Nginx, XAMPP, Laragon, atau PHP built-in server.
- Folder `uploads/` harus punya izin tulis.

## Cara Menjalankan

### Opsi 1: PHP Built-in Server

Jalankan perintah berikut dari root proyek:

```bash
php -S localhost:8000
```

Lalu buka:

```text
http://localhost:8000
```

### Opsi 2: Apache/Nginx

1. Arahkan document root ke folder proyek ini.
2. Pastikan PHP aktif di server.
3. Pastikan folder `uploads/` dapat ditulis oleh server.
4. Buka `index.php` melalui browser.

## Cara Menggunakan

1. Buka halaman utama aplikasi.
2. Pilih file melalui area unggah atau seret file ke dropzone.
3. Cek pratinjau file di panel kanan.
4. Tekan tombol unggah untuk menyimpan file.
5. File yang berhasil diunggah akan muncul di tabel berkas tersimpan.
6. Gunakan tombol unduh untuk mengambil file atau tombol hapus untuk menghapusnya.

## Struktur Proyek

```text
cloud-storage/
├── index.php
├── upload.php
├── download.php
├── delete.php
├── assets/
│   ├── app.js
│   └── styles.css
├── uploads/
└── .user.ini
```

## Catatan Teknis

- Semua nama file dibersihkan agar karakter berbahaya diganti dengan garis bawah.
- Jika nama file sudah ada, sistem akan menambahkan timestamp agar file lama tidak tertimpa.
- File yang diunggah disimpan langsung ke folder `uploads/` tanpa database.
- Pratinjau gambar ditangani di sisi frontend, sedangkan file non-gambar tetap bisa diunggah dan dikelola.

## Batas Unggahan

Konfigurasi saat ini menggunakan batas berikut:

- `upload_max_filesize = 20M`
- `post_max_size = 24M`
- Batas validasi aplikasi: 20 MB per file

## Tampilan dan Interaksi

Antarmuka aplikasi menggunakan gaya dasbor modern dengan:

- Hero section dan navigasi yang jelas.
- Panel unggah dengan area drag and drop.
- Tabel berkas tersimpan.
- Bagian FAQ dan penjelasan alur penggunaan.

## Keamanan

- Form unggah dan hapus memakai token CSRF.
- File diakses melalui skrip unduh agar nama file divalidasi.
- Operasi hapus hanya menerima request `POST`.

## Pengembangan Lanjutan

Jika ingin mengembangkan proyek ini, beberapa ide berikut bisa ditambahkan:

- Filter tipe file yang diizinkan.
- Paging atau pencarian pada daftar file.
- Preview khusus untuk PDF dan dokumen office.
- Penyimpanan metadata file ke database.
- Autentikasi pengguna untuk membatasi akses.

## Lisensi

Belum ditentukan.