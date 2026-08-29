# Berkontribusi

Terima kasih telah membantu mengembangkan hawpiwcloud. Perubahan sebaiknya tetap kecil, mudah direview, dan tidak menambah dependency tanpa kebutuhan yang jelas.

## Alur kerja

1. Buat branch dari `main`.
2. Jelaskan masalah dan perubahan yang diusulkan sebelum mengerjakan perubahan besar.
3. Ikuti pola keamanan yang sudah dipakai: autentikasi terpusat, CSRF untuk mutasi, validasi di batas kepercayaan, dan penyimpanan privat.
4. Perbarui dokumentasi atau smoke test jika perilaku aplikasi berubah.
5. Gunakan pesan commit yang singkat dan menjelaskan tujuan perubahan.

## Pemeriksaan lokal

Jalankan dari root repository:

```bash
for file in *.php; do php -l "$file"; done
bash tests/smoke.sh
git diff --check
```

Smoke test membutuhkan PHP dan `curl`. Jangan menjalankan pengujian dengan direktori data produksi.

## Pull request

Pull request sebaiknya menjelaskan konteks, perubahan utama, cara verifikasi, dan risiko migrasi bila ada. Jangan sertakan kata sandi, hash, `.env`, berkas pengguna, atau data produksi.

Perubahan UI harus mempertahankan akses keyboard, label yang jelas, dukungan layar kecil, dan preferensi reduced motion. Perubahan penyimpanan harus memastikan berkas tetap berada di luar document root.
