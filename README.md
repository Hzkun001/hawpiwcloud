# hawpiwcloud

hawpiwcloud adalah aplikasi penyimpanan berkas sederhana berbasis PHP untuk mengunggah, melihat pratinjau, mengunduh, dan menghapus file langsung dari browser. Aplikasi ini tidak memakai database; semua berkas disimpan di folder `uploads/` dan metadata pemilik file disimpan di `uploads/.metadata.json`.

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

- PHP 8.0 atau lebih baru.
- Web server seperti Apache, Nginx, XAMPP, Laragon, atau PHP built-in server.
- Folder `uploads/` harus punya izin tulis.

## Cara Menjalankan

### Opsi 1: PHP Built-in Server

Jalankan perintah berikut dari root proyek:

```bash
php -d upload_max_filesize=2M -d post_max_size=3M -S localhost:8000
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

## Implementasi Cloud Server

Isi alamat produksi berikut sebelum pengumpulan:

- URL aplikasi: `https://<domain-atau-subdomain-aktif>`
- Document root: folder proyek `hawpiwcloud`
- Akses login: `login.php`

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

## Catatan Teknis

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
