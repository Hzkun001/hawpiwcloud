# Simple Cloud Storage - Dokumentasi UAS

## Nama Aplikasi

hawpiwcloud

## Deskripsi

hawpiwcloud adalah aplikasi Simple Cloud Storage berbasis PHP untuk login, unggah file, unduh file, hapus file ke Trash, pengelolaan user, audit log, dan backup sederhana.

## Alamat Akses Cloud

- URL produksi: `https:cloud.hfzard.surf`
- Jika belum ada domain, isi dengan URL hosting aktif yang benar.

## Struktur Folder

```text
hawpiwcloud/
├── auth.php
├── storage.php
├── login.php
├── index.php
├── dashboard.php
├── dashboard-files.php
├── dashboard-upload.php
├── dashboard-users.php
├── trash.php
├── upload.php
├── download.php
├── delete.php
├── access.php
├── users.php
├── backup/
├── bin/
├── config/
├── deploy/
├── docs/
├── trash/
├── uploads/
└── assets/
```

## Fitur

- Login pengguna.
- Role-based access control untuk Admin, User, dan Viewer.
- Upload file dengan validasi tipe, ukuran, dan penamaan unik.
- Download file dengan pengecekan hak akses.
- Delete file ke folder Trash sebelum penghapusan permanen.
- Audit log login, upload, download, delete, dan akses ditolak.
- Backup source, uploads, trash, konfigurasi, dan database state.

## Struktur Level Akses

- Admin: mengelola semua file, semua user, backup, trash, dan akses file.
- User: mengunggah, mengunduh, dan menghapus file miliknya sendiri.
- Viewer: hanya melihat dan mengunduh file yang dibagikan.

## Cara Penggunaan

1. Buka URL aplikasi.
2. Login memakai akun demo atau akun yang dibuat admin.
3. Admin membuka dashboard untuk mengelola file, user, Trash, dan backup.
4. User membuka dashboard untuk upload dan kelola file miliknya.
5. Viewer hanya membuka halaman file yang dibagikan untuk melihat atau mengunduh.

## Strategi Backup

- Source code dibackup sebagai arsip terpisah.
- Folder `uploads/` dibackup karena berisi file aktif.
- Folder `trash/` dibackup untuk retention dan recovery.
- Data konfigurasi dan state backup disimpan tersendiri.
- Jika file hilang, restore dilakukan dari backup atau Trash.
- Jika database/state hilang, recovery dilakukan dari snapshot backup terakhir.

## Hasil Pengujian

| No | Pengujian | Hasil |
|---|---|---|
| 1 | Akses aplikasi dari cloud server | Lulus |
| 2 | Upload file kecil dan sedang | Lulus |
| 3 | Download file | Lulus |
| 4 | Delete file ke Trash | Lulus |
| 5 | Hak akses Admin, User, Viewer | Lulus |
| 6 | Multi-user access | Lulus |
| 7 | Kapasitas uploads dan trash | Lulus |

## Changelog

### Versi 1.0
- Fitur upload, download, delete dasar.

### Versi 1.1
- Menambahkan login pengguna.

### Versi 1.2
- Menambahkan level akses admin, user, viewer.

### Versi 1.3
- Menambahkan audit log dan folder trash.

### Versi 1.4
- Menambahkan validasi file dan backup project.

