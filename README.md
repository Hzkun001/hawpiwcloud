# hawpiwcloud

hawpiwcloud adalah aplikasi penyimpanan berkas sederhana berbasis PHP untuk mengunggah, melihat pratinjau, mengunduh, dan menghapus file langsung dari browser. Aplikasi ini tidak memakai database; semua berkas disimpan di folder `uploads/` dan metadata pemilik file disimpan di `uploads/.metadata.json`.

## Temuan Dari Hosting

Saat dijelajahi di `https://cloud.hfzard.surf`, aplikasi yang aktif saat ini menampilkan:

- Halaman publik `index.php` berisi ringkasan penyimpanan, daftar file, unggah cepat, alur singkat, dan FAQ.
- Halaman login `login.php` menyediakan akun demo untuk admin, user, dan viewer.
- Dashboard admin memiliki menu ringkasan, unggah berkas, kelola berkas, trash, manajemen user, dan status backup.
- Halaman unggah mendukung drag and drop, pratinjau file, dan penandaan file untuk tabel Viewer.
- Halaman kelola berkas menampilkan file per pemilik, pratinjau, unduh, hapus, dan toggle akses Viewer.
- Halaman trash menampung file yang bisa dipulihkan sebelum dihapus permanen.
- Halaman status backup menampilkan restore point, verifikasi SHA-256, retention, restore versi, dan backup manual.

## Fitur

- Unggah berkas melalui form atau drag and drop.
- Pratinjau otomatis untuk gambar sebelum unggah.
- Daftar berkas tersimpan dengan informasi ukuran dan waktu perubahan.
- Unduh berkas langsung dari tabel daftar file.
- Hapus berkas dengan konfirmasi sebelum tindakan dijalankan.
- Halaman login langsung dengan informasi akun dan level pengguna.
- Role Admin, User, dan Viewer dengan kewenangan berbeda.
- Admin dapat melihat tabel penyimpanan per user dan mengatur file untuk tabel Viewer.
- Admin dapat membuat banyak akun baru dari dashboard.
- Proteksi CSRF pada form unggah dan hapus.
- Penghapusan file memakai Trash agar file tidak langsung hilang permanen.
- Dashboard memakai halaman terpisah untuk unggah, kelola berkas, manajemen user, dan Trash.
- Backup terjadwal ZIP, dump state/database SQL, status backup, restore, dan replikasi offsite.
- Restore point full/incremental, SHA-256 sidecar, optional AES-256 encryption, retention otomatis, multi-storage, notifikasi, dan audit log.
- Dashboard admin backup manual, one-click restore, serta versioning file.
- Di hosting yang membatasi `proc_open` atau binary CLI, aktifkan mode web-friendly dengan `BACKUP_DATABASE_DRIVER=json`, `BACKUP_COMPRESSION_FORMAT=zip`, `BACKUP_OFFSITE_DRIVER=local`, dan `BACKUP_ENCRYPTION_PASSWORD` kosong.
- Validasi ukuran unggahan dengan batas maksimal 2 MB per berkas.
- Validasi jenis file: JPG, PNG, GIF, WEBP, PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, CSV, ZIP, dan RAR.
- Dokumentasi UAS tersedia di [docs/uas.md](docs/uas.md).

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
php -d upload_max_filesize=2M -d post_max_size=3M -S localhost:8000
```

Smoke test memakai direktori sementara, PHP built-in server, dan `curl`, lalu membersihkan seluruh data uji secara otomatis.

```text
http://localhost:8000
```

### Opsi 2: Apache/Nginx

1. Arahkan document root ke folder proyek ini.
2. Pastikan PHP aktif di server.
3. Pastikan folder `uploads/` dapat ditulis oleh server.
4. Buka `index.php` melalui browser.

## Implementasi Cloud Server

Isi alamat produksi berikut sebelum pengumpulan:

- URL aplikasi: `https://cloud.hfzard.surf`
- Document root: folder proyek `hawpiwcloud`
- Akses login: [login.php](login.php)

## Cara Menggunakan

1. Buka aplikasi dan login sebagai admin, user, atau viewer.
2. Admin dapat mengelola semua file dan melihat tabel penyimpanan setiap user dari dashboard.
3. Admin dapat menandai file mana yang masuk ke tabel khusus Viewer.
4. User dapat mengunggah, mengunduh, dan menghapus file miliknya sendiri.
5. Viewer hanya dapat melihat dan mengunduh file yang ditandai untuk Viewer.
6. Viewer membuka dashboard dan hanya dapat melihat file yang ditandai untuk Viewer.

## Akun Demo

```text
admin  / admin123  - Admin
user   / user123   - User
viewer / viewer123 - Viewer
```

## Struktur Proyek

```text
cloud-storage/
├── bootstrap.php
├── login.php
├── index.php
├── auth.php
├── storage.php
├── login.php
├── logout.php
├── users.php
├── upload.php
├── download.php
├── delete.php
├── access.php
├── backup/
├── bin/
├── config/
├── deploy/
├── docs/
├── trash/
├── assets/
│   ├── css/
│   │   ├── base/
│   │   ├── components/
│   │   └── pages/
│   └── app.js
├── uploads/
└── .user.ini
```

## Batasan

- Semua nama file dibersihkan agar karakter berbahaya diganti dengan garis bawah.
- Jika nama file sudah ada, sistem akan menambahkan timestamp agar file lama tidak tertimpa.
- File yang diunggah disimpan langsung ke folder `uploads/` tanpa database.
- Metadata pemilik dan akses Viewer disimpan di `uploads/.metadata.json`.
- Akun tambahan buatan admin disimpan di `users.json`.
- Backup lokal secara default disimpan di `../hawpiwcloud-backups`, di luar document root aplikasi.
- Jika hosting membatasi shell, jalankan backup via dashboard dengan mode web-friendly, bukan melalui skrip `deploy/run-backup.example.sh`.
- Dokumentasi lengkap konfigurasi, scheduler, Trash retention, restore, dan keamanan tersedia di `docs/BACKUP_AND_RESTORE.md`.
- Panduan struktur CSS modular tersedia di `docs/FRONTEND_STYLES.md`.
- File lama tanpa metadata dianggap milik admin dan dapat dilihat Viewer.
- Pratinjau gambar ditangani di sisi frontend, sedangkan file non-gambar tetap bisa diunggah dan dikelola.

## Batas Unggahan

Konfigurasi saat ini menggunakan batas berikut:

- `upload_max_filesize = 2M`
- `post_max_size = 3M`
- Batas validasi aplikasi: 2 MB per file

### Jika masih mentok di bawah 2 MB

Jika unggahan gagal di bawah 2 MB, berarti batas efektif server masih lebih kecil dari konfigurasi aplikasi.

Hal yang perlu dicek:

- Nilai `upload_max_filesize` pada konfigurasi PHP aktif.
- Nilai `post_max_size` pada konfigurasi PHP aktif.
- Restart web server/PHP-FPM setelah mengubah konfigurasi.

Catatan:

- File `.user.ini` biasanya berlaku pada mode CGI/FastCGI.
- Pada server tertentu (misalnya Apache dengan modul PHP atau konfigurasi container tertentu), Anda perlu mengubah `php.ini` utama atau konfigurasi pool/server agar perubahan benar-benar aktif.

## Tampilan dan Interaksi

Antarmuka aplikasi menggunakan gaya dasbor modern dengan:

- Hero section dan navigasi yang jelas.
- Panel unggah dengan area drag and drop.
- Tabel berkas tersimpan.
- Tabel penyimpanan per user untuk admin.
- Informasi level pengguna pada halaman login.
- Bagian FAQ dan penjelasan alur penggunaan.

## Keamanan

- Form unggah dan hapus memakai token CSRF.
- File diakses melalui skrip unduh agar nama file divalidasi.
- Operasi hapus hanya menerima request `POST`.
- Upload, download, dan delete divalidasi ulang di backend berdasarkan role pengguna.

## Audit Log

Log aktivitas disimpan oleh modul backup dan mencatat:

- login berhasil dan gagal
- upload berhasil dan ditolak
- download berhasil dan ditolak
- delete berhasil dan ditolak
- percobaan akses ke halaman admin atau halaman terproteksi
- aktivitas admin pada manajemen user

## Pengembangan Lanjutan

Jika ingin mengembangkan proyek ini, beberapa ide berikut bisa ditambahkan:

- Paging atau pencarian pada daftar file.
- Preview khusus untuk PDF dan dokumen office.
- Penyimpanan metadata file ke database.
- Panel admin untuk menambah, mengubah, dan menghapus akun secara dinamis.

## Lisensi

Belum ditentukan.
