# hawpiwcloud

hawpiwcloud adalah aplikasi penyimpanan berkas sederhana berbasis PHP. Satu kata sandi bersama melindungi fitur unggah, daftar, unduh, dan hapus. Berkas disimpan di luar document root dan hanya dapat diambil melalui handler unduhan yang sudah diautentikasi.

## Kebutuhan

- PHP 8.0 atau lebih baru dengan ekstensi Fileinfo.
- HTTPS pada deployment publik.
- Direktori data yang writable di luar document root.
- Dua environment variable: `HAWPIWCLOUD_DATA_DIR` dan `HAWPIWCLOUD_PASSWORD_HASH`.

Tidak ada database, framework, Composer, atau paket JavaScript.

## Konfigurasi

1. Buat direktori privat beserta subdirektori `files/`. Lokasinya tidak boleh berada di dalam document root aplikasi.

   ```bash
   mkdir -p /home/account/hawpiwcloud-data/files
   chmod 700 /home/account/hawpiwcloud-data /home/account/hawpiwcloud-data/files
   ```

2. Buat hash kata sandi. Perintah berikut membaca kata sandi dari standard input dan hanya mencetak hash:

   ```bash
   php -r 'echo password_hash(trim(fgets(STDIN)), PASSWORD_DEFAULT), PHP_EOL;'
   ```

3. Atur environment variable melalui panel hosting, konfigurasi virtual host, atau service manager:

   ```text
   HAWPIWCLOUD_DATA_DIR=/home/account/hawpiwcloud-data
   HAWPIWCLOUD_PASSWORD_HASH=<hasil password_hash>
   ```

   Jangan simpan kata sandi, hash, atau file `.env` di repository maupun document root.

4. Untuk pengembangan lokal, ekspor kedua nilai tersebut lalu jalankan server dari root proyek:

   ```bash
   php -S localhost:8000
   ```

Aplikasi mengembalikan HTTP 503 jika konfigurasi data atau hash tidak aman. Tidak ada fallback ke folder publik.

## Migrasi dari Versi Lama

Lakukan migrasi dalam masa henti singkat agar tidak ada unggahan baru selama pemindahan.

1. Cadangkan folder publik `uploads/` beserta metadata dan izin file.
2. Pindahkan hanya berkas pengguna ke `$HAWPIWCLOUD_DATA_DIR/files/`; jangan pindahkan `.gitkeep`.
3. Bandingkan jumlah dan checksum berkas sumber dengan tujuan.
4. Hapus folder publik `uploads/` setelah verifikasi berhasil.
5. Jalankan pemeriksaan pada bagian Pengujian sebelum membuka kembali aplikasi.

Untuk rollback, pulihkan kode lama dan salinan cadangan ke lokasi semula. Jangan gunakan direktori publik sebagai fallback pada kode baru.

## Alur

1. Pengguna membuka `login.php` dan memasukkan kata sandi bersama.
2. PHP memverifikasi hash, mengganti session ID, lalu membuka dashboard.
3. `index.php` membaca daftar berkas dari direktori privat.
4. `upload.php`, `download.php`, dan `delete.php` memakai autentikasi, CSRF untuk mutasi, dan path privat yang sama.
5. Sesi berakhir setelah 30 menit tanpa aktivitas atau ketika pengguna menekan Keluar.

Lima kegagalan login dari satu alamat jaringan dalam 15 menit dibatasi sementara. Implementasi berbasis file lock ini ditujukan untuk satu server; gunakan rate limiter bersama jika aplikasi dipindahkan ke beberapa node.

## Batas Unggahan

- `upload_max_filesize = 20M`
- `post_max_size = 24M`
- Validasi aplikasi: 20 MB per berkas

Semua jenis berkas dapat disimpan karena direktori data tidak dapat diakses langsung dari web. Nama berkas dibersihkan, dan nama yang sama mendapat suffix acak tanpa menimpa berkas lama.

## Pengujian

Jalankan lint dan smoke test dari root proyek:

```bash
for file in *.php; do php -l "$file"; done
bash tests/smoke.sh
```

Smoke test memakai direktori sementara, PHP built-in server, dan `curl`, lalu membersihkan seluruh data uji secara otomatis.

## Struktur

```text
cloud-storage/
├── bootstrap.php
├── login.php
├── index.php
├── upload.php
├── download.php
├── delete.php
├── assets/
├── tests/
├── spec.md
├── plan.md
└── tasks.md
```

## Batasan

- Satu kata sandi memberi akses penuh ke seluruh berkas dan operasi.
- Tidak ada akun per pengguna, pemulihan kata sandi, audit log, atau versioning.
- Penyimpanan tetap bergantung pada kapasitas dan backup server tujuan.

## Lisensi

Belum ditentukan.
