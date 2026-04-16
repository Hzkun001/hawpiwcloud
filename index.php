<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

$uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;

$files = [];
if (is_dir($uploadDir)) {
    $items = scandir($uploadDir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $filePath = $uploadDir . $item;
        if (!is_file($filePath)) {
            continue;
        }

        $files[] = [
            'name' => $item,
            'size' => filesize($filePath),
            'modified' => filemtime($filePath),
        ];
    }
}

usort($files, static fn(array $left, array $right): int => $right['modified'] <=> $left['modified']);

function formatFileSize(int $bytes): string
{
    if ($bytes >= 1024 * 1024) {
        return rtrim(rtrim(number_format($bytes / (1024 * 1024), 2, '.', ''), '0'), '.') . ' MB';
    }

    if ($bytes >= 1024) {
        return rtrim(rtrim(number_format($bytes / 1024, 2, '.', ''), '0'), '.') . ' KB';
    }

    return $bytes . ' B';
}

function formatTimestamp(int $timestamp): string
{
    return date('M j, Y H:i', $timestamp);
}

$status = $_GET['status'] ?? '';
$banner = null;

if ($status === 'upload_success') {
    $banner = ['type' => 'success', 'title' => 'Unggahan selesai', 'message' => 'Berkas Anda berhasil ditambahkan ke penyimpanan awan.'];
} elseif ($status === 'delete_success') {
    $banner = ['type' => 'success', 'title' => 'Berkas dihapus', 'message' => 'Berkas yang dipilih berhasil dihapus.'];
} elseif ($status === 'error_permissions') {
    $banner = ['type' => 'error', 'title' => 'Penyimpanan tidak tersedia', 'message' => 'Folder unggahan tidak dapat ditulis. Periksa izin folder pada server.'];
} elseif ($status === 'error_size') {
    $banner = ['type' => 'error', 'title' => 'Berkas terlalu besar', 'message' => 'Berkas yang dipilih melebihi batas unggahan 20 MB.'];
} elseif ($status === 'error_partial') {
    $banner = ['type' => 'error', 'title' => 'Unggahan terputus', 'message' => 'Proses unggahan berkas belum selesai. Silakan coba lagi.'];
} elseif ($status === 'error_nofile') {
    $banner = ['type' => 'error', 'title' => 'Tidak ada berkas dipilih', 'message' => 'Pilih berkas terlebih dahulu sebelum mengirim formulir unggahan.'];
} elseif ($status === 'error_type') {
    $banner = ['type' => 'error', 'title' => 'Jenis berkas tidak didukung', 'message' => 'File yang dipilih tidak sesuai dengan jenis yang diizinkan untuk diunggah.'];
} elseif ($status === 'error_security') {
    $banner = ['type' => 'error', 'title' => 'Permintaan tidak valid', 'message' => 'Sesi atau token keamanan tidak cocok. Silakan muat ulang halaman dan coba lagi.'];
} elseif ($status === 'error') {
    $banner = ['type' => 'error', 'title' => 'Terjadi kesalahan', 'message' => 'Silakan coba lagi dan periksa pilihan berkas atau izin server.'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>hawpiwcloud</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
    <main class="shell">
        <header class="site-header">
            <a class="brand" href="#top" aria-label="Beranda hawpiwcloud">
                <span class="brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M12 4v11" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <path d="m7.5 9 4.5-4.5L16.5 9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M5.5 15.5V17A2.5 2.5 0 0 0 8 19.5h8A2.5 2.5 0 0 0 18.5 17v-1.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
                <span class="brand-name">hawpiwcloud</span>
            </a>

            <nav class="site-nav" aria-label="Primary">
                <a href="#top">Beranda</a>
                <a href="#how-it-works">Cara Kerja</a>
                <a href="#files-title">Tentang</a>
                <a class="action-button nav-cta" href="#upload-panel">Masuk</a>
            </nav>
        </header>

        <section class="hero" id="top">
            <h1>Simpan dan Tinjau Berkas dalam Satu Dasbor yang Bersih</h1>
            <p class="subtitle">Unggah, tinjau, unduh, dan kelola berkas Anda melalui antarmuka tenang yang terinspirasi dari produk SaaS modern dan dasbor awan yang rapi.</p>
        </section>

        <div class="stack">
            <?php if ($banner !== null): ?>
                <div class="banner <?= htmlspecialchars($banner['type']); ?>" role="status" aria-live="polite">
                    <div class="banner-badge">
                        <?php if ($banner['type'] === 'success'): ?>
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true" width="18" height="18">
                                <path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        <?php else: ?>
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true" width="18" height="18">
                                <path d="M12 9v5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                <path d="M12 16.5h.01" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                                <path d="M10.29 3.86l-8.17 14A2 2 0 0 0 3.85 21h16.3a2 2 0 0 0 1.73-3.14l-8.17-14a2 2 0 0 0-3.42 0Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                            </svg>
                        <?php endif; ?>
                    </div>
                    <div>
                        <strong><?= htmlspecialchars($banner['title']); ?></strong>
                        <p><?= htmlspecialchars($banner['message']); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <section class="panel" id="upload-panel" aria-labelledby="upload-title">
                <div class="panel-head">
                    <div>
                        <h2 id="upload-title">Unggah Berkas</h2>
                        <span>Pilih berkas, tinjau pratinjau, lalu unggah.</span>
                    </div>
                </div>

                <div class="upload-card">
                    <form action="upload.php" method="post" enctype="multipart/form-data" id="upload-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="upload-grid">
                            <label class="dropzone" id="dropzone" for="file-input">
                                <input class="dropzone-input" id="file-input" type="file" name="fileToUpload" accept="*/*" required>
                                <div class="dropzone-content" id="dropzone-content">
                                    <div class="dropzone-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" width="28" height="28">
                                            <path d="M12 16V4" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>
                                            <path d="m7.5 8.5 4.5-4.5 4.5 4.5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M5.5 15.5V17A2.5 2.5 0 0 0 8 19.5h8A2.5 2.5 0 0 0 18.5 17v-1.5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </div>
                                    <div class="dropzone-title">Klik untuk mengunggah atau seret dan lepaskan</div>
                                    <p class="dropzone-copy">Semua jenis file bisa diunggah. Pratinjau akan tampil otomatis untuk gambar agar Anda bisa memastikan berkas sebelum dikirim.</p>
                                    <span class="file-chip" id="file-chip">Belum ada berkas yang dipilih</span>
                                </div>
                            </label>

                            <aside class="preview-panel" aria-live="polite">
                                <div class="preview-titlebar">
                                    <div class="window-dots" aria-hidden="true">
                                        <span></span>
                                        <span></span>
                                        <span></span>
                                    </div>
                                    <div class="preview-title">Pratinjau hawpiwcloud</div>
                                </div>
                                <div class="preview-shell">
                                    <div class="preview-empty" id="preview-empty">
                                        <svg viewBox="0 0 24 24" fill="none" width="30" height="30" aria-hidden="true">
                                            <path d="M7 3.75h6.5L19 9.25V20a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4.75a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                                            <path d="M13.5 3.75V9.25H19" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                                        </svg>
                                        <div>Pratinjau unggahan akan muncul di sini</div>
                                        <span>Pilih gambar atau dokumen untuk memastikan berkas sebelum dikirim.</span>
                                    </div>
                                    <img class="preview-image" id="preview-image" alt="Pratinjau berkas">
                                    <div class="preview-icon" id="preview-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" width="34" height="34">
                                            <path d="M7 3.75h6.5L19 9.25V20a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4.75a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                                            <path d="M13.5 3.75V9.25H19" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                                        </svg>
                                    </div>
                                </div>

                                <div class="preview-meta">
                                    <strong id="preview-name">Belum ada berkas yang dipilih</strong>
                                    <span id="preview-details">Ukuran dan jenis berkas akan tampil di sini.</span>
                                    <div class="helper-note">
                                        <span><strong>Tips:</strong> Gambar akan menampilkan thumbnail secara otomatis.</span>
                                        <span>Batas unggahan maksimal: 20 MB</span>
                                    </div>
                                </div>

                                <div class="upload-actions">
                                    <button class="secondary-button" type="button" id="clear-file">Atur Ulang</button>
                                    <button class="primary-button" type="submit">
                                        <svg class="button-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="M12 16V4" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>
                                            <path d="m7.5 8.5 4.5-4.5 4.5 4.5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M5.5 15.5V17A2.5 2.5 0 0 0 8 19.5h8A2.5 2.5 0 0 0 18.5 17v-1.5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        Unggah Berkas
                                    </button>
                                </div>
                            </aside>
                        </div>
                    </form>
                </div>
            </section>

            <section class="panel files-panel" aria-labelledby="files-title">
                <div class="panel-head">
                    <div>
                        <h2 id="files-title">Berkas Tersimpan</h2>
                        <span><?= count($files); ?> berkas tersimpan di ruang kerja Anda</span>
                    </div>
                </div>

                <?php if (count($files) > 0): ?>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Nama Berkas</th>
                                    <th>Ukuran</th>
                                    <th>Terakhir Diubah</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($files as $file): ?>
                                    <tr>
                                        <td>
                                            <div class="file-name">
                                                <div class="file-icon" aria-hidden="true">
                                                    <svg viewBox="0 0 24 24" fill="none">
                                                        <path d="M7 3.75h6.5L19 9.25V20a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4.75a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                                                        <path d="M13.5 3.75V9.25H19" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                                                    </svg>
                                                </div>
                                                <span title="<?= htmlspecialchars($file['name']); ?>"><?= htmlspecialchars($file['name']); ?></span>
                                            </div>
                                        </td>
                                        <td class="meta"><?= formatFileSize((int)$file['size']); ?></td>
                                        <td class="meta"><?= formatTimestamp((int)$file['modified']); ?></td>
                                        <td>
                                            <div class="actions">
                                                <a class="action-button download icon-only" href="download.php?file=<?= urlencode($file['name']); ?>" aria-label="Unduh <?= htmlspecialchars($file['name']); ?>" title="Unduh">
                                                    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                        <path d="M12 3.75v9.5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>
                                                        <path d="m8.25 9.75 3.75 3.75 3.75-3.75" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/>
                                                        <path d="M5.5 18.5h13" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>
                                                    </svg>
                                                </a>
                                                <form class="action-form" action="delete.php" method="post" onsubmit="return confirm('Hapus berkas ini?');">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="hidden" name="file" value="<?= htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8'); ?>">
                                                    <button class="action-button delete icon-only" type="submit" aria-label="Hapus <?= htmlspecialchars($file['name']); ?>" title="Hapus">
                                                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                            <path d="M5.75 7h12.5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>
                                                            <path d="M9 7V5.75A1.75 1.75 0 0 1 10.75 4h2.5A1.75 1.75 0 0 1 15 5.75V7" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/>
                                                            <path d="M8.5 7.25l.55 10.3A1.75 1.75 0 0 0 10.8 19h2.4a1.75 1.75 0 0 0 1.75-1.45l.55-10.3" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"/>
                                                            <path d="M10.25 10.25v4.5M13.75 10.25v4.5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>
                                                        </svg>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-card">
                            <div class="empty-mark" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none" width="28" height="28">
                                    <path d="M7.5 18.5h9a4 4 0 0 0 .9-7.89 5.5 5.5 0 0 0-10.48-1.28A3.75 3.75 0 0 0 7.5 18.5Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M12 12v5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                                    <path d="M9.75 14.25 12 12l2.25 2.25" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                            <h3>Belum ada berkas</h3>
                            <p>Penyimpanan Anda masih kosong. Unggah berkas untuk menampilkan daftar dan mulai mengelolanya dari dasbor ini.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </section>

            <section class="how-section" id="how-it-works" aria-labelledby="how-title">
                <div class="how-header">
                    <h2 id="how-title">Cara Kerja</h2>
                    <p>Tiga langkah sederhana untuk mengunggah, meninjau, dan mengelola berkas Anda dari satu dasbor penyimpanan awan yang bersih.</p>
                </div>

                <div class="steps-grid">
                    <article class="step-card">
                        <div class="step-pill">1</div>
                        <h3>Unggah Berkas Anda</h3>
                        <p>Klik area unggah atau seret berkas ke dropzone. Anda dapat memilih gambar, dokumen, atau arsip sebelum mengirimkannya ke server.</p>
                    </article>

                    <article class="step-card">
                        <div class="step-pill">2</div>
                        <h3>Tinjau Pratinjau</h3>
                        <p>Periksa panel pratinjau di sebelah kanan untuk memastikan nama, ukuran, serta thumbnail atau ikon berkas sebelum melanjutkan.</p>
                    </article>

                    <article class="step-card">
                        <div class="step-pill">3</div>
                        <h3>Kelola Hasilnya</h3>
                        <p>Setelah unggah selesai, berkas Anda akan muncul di tabel sehingga bisa diunduh kapan saja atau dihapus dengan satu klik.</p>
                    </article>
                </div>

                <section class="safety-card" aria-labelledby="safety-title">
                    <div class="safety-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" width="22" height="22">
                            <path d="M12 3.75 19 6.5v5.25c0 4.42-2.83 7.99-7 9.5-4.17-1.51-7-5.08-7-9.5V6.5l7-2.75Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                            <path d="M9.5 12.25 11.2 14l3.3-3.3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <div>
                        <h3 id="safety-title">Keamanan Penyimpanan Awan</h3>
                        <p>Setiap unggahan divalidasi sebelum disimpan. Aplikasi memeriksa jenis berkas, ukuran, dan izin tujuan agar penyimpanan tetap stabil dan aman untuk penggunaan sehari-hari.</p>
                        <div class="safety-list">
                            <span>Divalidasi sebelum disimpan</span>
                            <span>Pratinjau gambar dan dokumen</span>
                            <span>Batas ukuran ditegakkan</span>
                            <span>Kontrol unduh dan hapus</span>
                        </div>
                    </div>
                </section>

                <div class="cta-band">
                    <h3>Butuh cara yang lebih rapi untuk mengatur berkas?</h3>
                    <p>Gunakan dasbor ini untuk mengunggah, meninjau, dan mengelola berkas dengan antarmuka minimal yang tetap nyaman dibaca di desktop, tablet, dan ponsel.</p>
                    <a class="cta-button" href="#upload-panel">Mulai Sekarang</a>
                </div>
            </section>

            <section class="faq-section" id="faq" aria-labelledby="faq-title">
                <div class="faq-header">
                    <div class="faq-kicker">Punya pertanyaan?</div>
                    <h2 id="faq-title">Pertanyaan yang Sering Diajukan</h2>
                    <p>Berikut jawaban singkat untuk hal-hal yang paling sering ditanyakan tentang hawpiwcloud dan cara penggunaannya.</p>
                </div>

                <div class="faq-list" data-faq>
                    <article class="faq-item">
                        <button class="faq-question" type="button" aria-expanded="false">
                            <span>Bagaimana cara mengunggah berkas?</span>
                            <span class="faq-icon" aria-hidden="true">+</span>
                        </button>
                        <div class="faq-answer">
                            <div class="faq-answer-inner">Klik area unggah, pilih berkas dari perangkat Anda, periksa pratinjau di panel kanan, lalu tekan tombol <strong>Unggah Berkas</strong>.</div>
                        </div>
                    </article>

                    <article class="faq-item">
                        <button class="faq-question" type="button" aria-expanded="false">
                            <span>Format berkas apa yang didukung?</span>
                            <span class="faq-icon" aria-hidden="true">+</span>
                        </button>
                        <div class="faq-answer">
                            <div class="faq-answer-inner">Pada mode saat ini, semua jenis file bisa diunggah. Gambar tetap mendapat pratinjau visual, sementara file lain tampil sebagai nama dan ikon berkas.</div>
                        </div>
                    </article>

                    <article class="faq-item">
                        <button class="faq-question" type="button" aria-expanded="false">
                            <span>Apakah saya bisa melihat pratinjau sebelum mengunggah?</span>
                            <span class="faq-icon" aria-hidden="true">+</span>
                        </button>
                        <div class="faq-answer">
                            <div class="faq-answer-inner">Bisa. Gambar akan ditampilkan sebagai thumbnail, sedangkan jenis berkas lain akan tampil sebagai ikon beserta nama dan ukurannya.</div>
                        </div>
                    </article>

                    <article class="faq-item">
                        <button class="faq-question" type="button" aria-expanded="false">
                            <span>Berapa batas ukuran unggahan?</span>
                            <span class="faq-icon" aria-hidden="true">+</span>
                        </button>
                        <div class="faq-answer">
                            <div class="faq-answer-inner">Batas unggahan saat ini adalah 20 MB per berkas. Jika melebihi batas ini, sistem akan menampilkan pesan kesalahan agar Anda bisa memilih berkas yang lebih kecil.</div>
                        </div>
                    </article>

                    <article class="faq-item">
                        <button class="faq-question" type="button" aria-expanded="false">
                            <span>Apakah berkas saya aman?</span>
                            <span class="faq-icon" aria-hidden="true">+</span>
                        </button>
                        <div class="faq-answer">
                            <div class="faq-answer-inner">Setiap unggahan divalidasi sebelum disimpan. Sistem mengecek izin folder, ukuran berkas, dan proses unggah agar tetap stabil dan aman digunakan.</div>
                        </div>
                    </article>

                    <article class="faq-item">
                        <button class="faq-question" type="button" aria-expanded="false">
                            <span>Bagaimana cara menghapus berkas?</span>
                            <span class="faq-icon" aria-hidden="true">+</span>
                        </button>
                        <div class="faq-answer">
                            <div class="faq-answer-inner">Pada tabel berkas, klik ikon hapus di kolom Aksi. Sistem akan meminta konfirmasi sebelum berkas benar-benar dihapus dari folder penyimpanan.</div>
                        </div>
                    </article>
                </div>

                <p class="faq-footnote">Jika pertanyaan Anda belum terjawab, Anda bisa langsung mencoba unggah satu berkas dan melihat pratinjau serta respons sistem secara langsung.</p>
            </section>

            <footer class="site-footer" aria-labelledby="footer-brand-title">
                <div class="footer-inner">
                    <div class="footer-top">
                        <div class="footer-brand-block">
                            <h2 class="footer-brand-name" id="footer-brand-title">hawpiwcloud</h2>
                            <p class="footer-copy">Platform penyimpanan berkas sederhana untuk mengunggah, meninjau, mengunduh, dan mengelola file dengan tampilan yang bersih dan cepat dipahami.</p>

                            <div class="footer-signup" aria-label="Langganan pembaruan">
                                <form action="#" method="post" onsubmit="return false;">
                                    <input type="email" name="email" placeholder="Masukkan email Anda" aria-label="Masukkan email Anda">
                                    <button type="button">Gabung</button>
                                </form>
                            </div>
                        </div>

                        <div class="footer-columns" aria-label="Tautan footer">
                            <div class="footer-column">
                                <h4>Produk</h4>
                                <div class="footer-links">
                                    <a href="#upload-panel">Unggah Berkas</a>
                                    <a href="#files">Daftar Berkas</a>
                                    <a href="#how-it-works">Cara Kerja</a>
                                    <a href="#faq">FAQ</a>
                                </div>
                            </div>

                            <div class="footer-column">
                                <h4>Perusahaan</h4>
                                <div class="footer-links">
                                    <a href="#top">Tentang</a>
                                    <a href="#how-it-works">Blog</a>
                                    <a href="#upload-panel">Harga</a>
                                    <a href="#faq">Kontak</a>
                                </div>
                            </div>

                            <div class="footer-column">
                                <h4>Sumber Daya</h4>
                                <div class="footer-links">
                                    <a href="#how-it-works">Panduan</a>
                                    <a href="#files">Lihat Berkas</a>
                                    <a href="#upload-panel">Pratinjau Berkas</a>
                                    <a href="#faq">Bantuan</a>
                                </div>
                            </div>

                            <div class="footer-column">
                                <h4>Legal</h4>
                                <div class="footer-links">
                                    <a href="#">Kebijakan Privasi</a>
                                    <a href="#">Ketentuan Layanan</a>
                                    <a href="#">Kebijakan Cookie</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="footer-divider" aria-hidden="true"></div>

                    <div class="footer-bottom">
                        <div class="footer-social" aria-label="Sosial media">
                            <a class="social-link" href="#" aria-label="X">
                                <svg viewBox="0 0 24 24" fill="none" width="17" height="17" aria-hidden="true">
                                    <path d="M4 4l7.4 8.7L4.1 20h2.2l6.4-6.9L18.4 20H20l-7.8-9.1L20 4h-2.2l-6 6.5L6.6 4H4Z" fill="currentColor"/>
                                </svg>
                            </a>
                            <a class="social-link" href="#" aria-label="LinkedIn">
                                <svg viewBox="0 0 24 24" fill="none" width="17" height="17" aria-hidden="true">
                                    <path d="M6.5 9.25V18M6.5 6.2v.1" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    <path d="M10.25 18v-4.5c0-1.94 1.05-3.25 2.82-3.25 1.72 0 2.68 1.15 2.68 3.25V18M15.75 10.25V18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M6.5 5.5a.75.75 0 1 0 0 1.5.75.75 0 0 0 0-1.5Z" fill="currentColor"/>
                                </svg>
                            </a>
                        </div>

                        <div class="footer-bottom-copy">© 2026 hawpiwcloud. Hak cipta dilindungi.</div>

                        <div class="footer-policy-links">
                            <a href="#">Kebijakan Privasi</a>
                            <a href="#">Ketentuan Layanan</a>
                            <a href="#">Kebijakan Cookie</a>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </main>
    <script src="assets/app.js" defer></script>
</body>
</html>