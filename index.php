<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';

$currentUser = authRequireLogin();

if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

$uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;

function isPreviewableImage(string $filePath): bool
{
    if (!is_file($filePath)) {
        return false;
    }

    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $mimeType = finfo_file($finfo, $filePath);
            finfo_close($finfo);

            if (is_string($mimeType) && str_starts_with($mimeType, 'image/')) {
                return true;
            }
        }
    }

    return (bool) preg_match('/\.(jpe?g|png|gif|webp|bmp|avif|svg)$/i', $filePath);
}

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
            'isImage' => isPreviewableImage($filePath),
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

function iniSizeToBytes(string $value): int
{
    $value = trim($value);
    $unit = strtolower(substr($value, -1));
    $number = (int) $value;

    return match ($unit) {
        'g' => $number * 1024 * 1024 * 1024,
        'm' => $number * 1024 * 1024,
        'k' => $number * 1024,
        default => (int) $value,
    };
}

$appUploadLimitBytes = 2 * 1024 * 1024;
$uploadMaxSizeBytes = iniSizeToBytes((string) ini_get('upload_max_filesize'));
$postMaxSizeBytes = iniSizeToBytes((string) ini_get('post_max_size'));

$serverLimits = array_filter([$uploadMaxSizeBytes, $postMaxSizeBytes], static fn(int $bytes): bool => $bytes > 0);
$effectiveServerUploadLimitBytes = $serverLimits === [] ? $appUploadLimitBytes : min($serverLimits);
$effectiveUploadLimitBytes = min($appUploadLimitBytes, $effectiveServerUploadLimitBytes);
$effectiveUploadLimitLabel = formatFileSize($effectiveUploadLimitBytes);

$status = $_GET['status'] ?? '';
$banner = null;
$assetVersion = '20260508-login';

if ($status === 'upload_success') {
    $banner = ['type' => 'success', 'title' => 'Unggahan selesai', 'message' => 'Berkas Anda berhasil ditambahkan ke penyimpanan awan.'];
} elseif ($status === 'delete_success') {
    $banner = ['type' => 'success', 'title' => 'Berkas dihapus', 'message' => 'Berkas yang dipilih berhasil dihapus.'];
} elseif ($status === 'error_permissions') {
    $banner = ['type' => 'error', 'title' => 'Penyimpanan tidak tersedia', 'message' => 'Layanan penyimpanan sedang tidak tersedia sementara. Silakan coba lagi beberapa saat lagi.'];
} elseif ($status === 'error_size') {
    $banner = ['type' => 'error', 'title' => 'Berkas terlalu besar', 'message' => 'Ukuran berkas melebihi batas unggahan saat ini (' . $effectiveUploadLimitLabel . '). Silakan pilih berkas yang lebih kecil lalu coba lagi.'];
} elseif ($status === 'error_server_limit') {
    $banner = ['type' => 'error', 'title' => 'Berkas terlalu besar', 'message' => 'Ukuran berkas melampaui batas unggahan saat ini (' . $effectiveUploadLimitLabel . '). Silakan kompres berkas atau pilih file lain yang lebih kecil.'];
} elseif ($status === 'error_partial') {
    $banner = ['type' => 'error', 'title' => 'Unggahan terputus', 'message' => 'Proses unggahan berkas belum selesai. Silakan coba lagi.'];
} elseif ($status === 'error_nofile') {
    $banner = ['type' => 'error', 'title' => 'Tidak ada berkas dipilih', 'message' => 'Pilih berkas terlebih dahulu sebelum mengirim formulir unggahan.'];
} elseif ($status === 'error_type') {
    $banner = ['type' => 'error', 'title' => 'Jenis berkas tidak didukung', 'message' => 'File yang dipilih tidak sesuai dengan jenis yang diizinkan untuk diunggah.'];
} elseif ($status === 'error_security') {
    $banner = ['type' => 'error', 'title' => 'Permintaan tidak valid', 'message' => 'Sesi atau token keamanan tidak cocok. Silakan muat ulang halaman dan coba lagi.'];
} elseif ($status === 'error_forbidden') {
    $banner = ['type' => 'error', 'title' => 'Akses dashboard dibatasi', 'message' => 'Akun user biasa hanya dapat mengakses halaman website. Dashboard dan fitur pengelolaan tersedia untuk admin.'];
} elseif ($status === 'error') {
    $banner = ['type' => 'error', 'title' => 'Terjadi kesalahan', 'message' => 'Silakan coba lagi dan pastikan berkas yang dipilih sudah benar.'];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>hawpiwcloud</title>
    <link rel="stylesheet" href="assets/styles.css?v=<?= htmlspecialchars($assetVersion, ENT_QUOTES, 'UTF-8'); ?>">
</head>

<body>
    <main class="shell">
        <header class="site-header">
            <a class="brand" href="#top" aria-label="Beranda hawpiwcloud">
                <span class="brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none">
                        <path d="M12 4v11" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                        <path d="m7.5 9 4.5-4.5L16.5 9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M5.5 15.5V17A2.5 2.5 0 0 0 8 19.5h8A2.5 2.5 0 0 0 18.5 17v-1.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </span>
                <span class="brand-name">hawpiwcloud</span>
            </a>

            <nav class="site-nav" aria-label="Primary">
                <a href="#top">Beranda</a>
                <a href="#files">Berkas</a>
                <a href="#how-it-works">Cara Kerja</a>
                <a href="#faq">FAQ</a>
                <?php if (authIsAdmin()): ?>
                    <a href="dashboard.php">Dashboard</a>
                <?php endif; ?>
                <a class="action-button nav-cta" href="logout.php">Logout</a>
            </nav>
        </header>

        <section class="hero" id="top">
            <h1>Penyimpanan Berbasis Cloud Computing</h1>
            <p class="subtitle">Unggah, tinjau, unduh, dan kelola berkas Anda melalui antarmuka tenang yang terinspirasi dari produk SaaS modern dan dasbor awan yang rapi.</p>
        </section>

        <section class="cta-band" id="masuk-section" aria-labelledby="masuk-title">
            <h3 id="masuk-title">Selamat Datang, <?= htmlspecialchars($currentUser['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
            <p><?= authIsAdmin() ? 'Akun admin dapat membuka dashboard untuk mengelola unggahan, daftar berkas, unduhan, dan penghapusan file.' : 'Akun user biasa dapat mengakses halaman website ini, sementara dashboard dan fitur pengelolaan tetap dibatasi untuk admin.'; ?></p>
            <div class="entry-actions">
                <?php if (authIsAdmin()): ?>
                    <a class="primary-button" href="dashboard.php">
                        <svg class="button-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M12 16V4" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" />
                            <path d="m7.5 8.5 4.5-4.5 4.5 4.5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M5.5 15.5V17A2.5 2.5 0 0 0 8 19.5h8A2.5 2.5 0 0 0 18.5 17v-1.5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        Buka Dashboard
                    </a>
                    <a class="secondary-button" href="dashboard.php#files">
                        <svg class="button-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M7 3.75h6.5L19 9.25V20a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4.75a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" />
                            <path d="M13.5 3.75V9.25H19" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" />
                        </svg>
                        Lihat Dashboard
                    </a>
                <?php else: ?>
                    <a class="primary-button" href="#files">
                        <svg class="button-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M7 3.75h6.5L19 9.25V20a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4.75a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" />
                            <path d="M13.5 3.75V9.25H19" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" />
                        </svg>
                        Lihat Berkas
                    </a>
                    <a class="secondary-button" href="#how-it-works">
                        <svg class="button-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M12 5v14" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                            <path d="M5 12h14" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                        </svg>
                        Cara Kerja
                    </a>
                <?php endif; ?>
                <a class="secondary-button" href="logout.php">
                    <svg class="button-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M9.75 5.75H7A2 2 0 0 0 5 7.75v8.5a2 2 0 0 0 2 2h2.75" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                        <path d="M13 8.5 16.5 12 13 15.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M16.25 12H9.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                    </svg>
                    Logout
                </a>
            </div>
        </section>

        <div class="stack">
            <?php if ($banner !== null): ?>
                <div class="banner <?= htmlspecialchars($banner['type']); ?>" role="status" aria-live="polite">
                    <div class="banner-badge">
                        <?php if ($banner['type'] === 'success'): ?>
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true" width="18" height="18">
                                <path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        <?php else: ?>
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true" width="18" height="18">
                                <path d="M12 9v5" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                <path d="M12 16.5h.01" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                                <path d="M10.29 3.86l-8.17 14A2 2 0 0 0 3.85 21h16.3a2 2 0 0 0 1.73-3.14l-8.17-14a2 2 0 0 0-3.42 0Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
                            </svg>
                        <?php endif; ?>
                    </div>
                    <div>
                        <strong><?= htmlspecialchars($banner['title']); ?></strong>
                        <p><?= htmlspecialchars($banner['message']); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <section class="panel files-panel" aria-labelledby="files-title" id="files">
                <div class="panel-head">
                    <div>
                        <h2 id="files-title">Berkas Cloud</h2>
                        <span><?= count($files); ?> berkas tersedia untuk diunduh</span>
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
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($files as $file): ?>
                                    <tr>
                                        <td data-label="Nama Berkas">
                                            <div class="file-row">
                                                <div class="file-name">
                                                    <?php if ($file['isImage']): ?>
                                                        <img class="file-preview" src="uploads/<?= htmlspecialchars(rawurlencode($file['name']), ENT_QUOTES, 'UTF-8'); ?>" alt="Pratinjau <?= htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8'); ?>" loading="lazy">
                                                    <?php else: ?>
                                                        <div class="file-icon" aria-hidden="true">
                                                            <svg viewBox="0 0 24 24" fill="none">
                                                                <path d="M7 3.75h6.5L19 9.25V20a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4.75a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
                                                                <path d="M13.5 3.75V9.25H19" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
                                                            </svg>
                                                        </div>
                                                    <?php endif; ?>
                                                    <span title="<?= htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                                </div>
                                                <div class="actions file-actions">
                                                    <a class="action-button download icon-only" href="download.php?file=<?= urlencode($file['name']); ?>" aria-label="Unduh <?= htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8'); ?>" title="Unduh">
                                                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                            <path d="M12 3.75v9.5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" />
                                                            <path d="m8.25 9.75 3.75 3.75 3.75-3.75" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" />
                                                            <path d="M5.5 18.5h13" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" />
                                                        </svg>
                                                    </a>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="meta" data-label="Ukuran"><?= formatFileSize((int) $file['size']); ?></td>
                                        <td class="meta" data-label="Terakhir Diubah"><?= formatTimestamp((int) $file['modified']); ?></td>
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
                                    <path d="M7.5 18.5h9a4 4 0 0 0 .9-7.89 5.5 5.5 0 0 0-10.48-1.28A3.75 3.75 0 0 0 7.5 18.5Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                                    <path d="M12 12v5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" />
                                    <path d="M9.75 14.25 12 12l2.25 2.25" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </div>
                            <h3>Belum ada berkas</h3>
                            <p>Cloud masih kosong. Berkas yang diunggah admin akan tampil di sini dan bisa diunduh oleh user biasa.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </section>

            <section class="how-section" id="how-it-works" aria-labelledby="how-title">
                <div class="how-header">
                    <h2 id="how-title">Cara Kerja</h2>
                    <p>Tiga langkah sederhana untuk membaca, mengunduh, dan memahami alur pengelolaan berkas di hawpiwcloud.</p>
                </div>

                <div class="steps-grid">
                    <article class="step-card">
                        <div class="step-pill">1</div>
                        <h3>Lihat Berkas Cloud</h3>
                        <p>Setelah login, user biasa dapat melihat daftar file yang tersedia beserta ukuran, waktu perubahan terakhir, dan pratinjau gambar.</p>
                    </article>

                    <article class="step-card">
                        <div class="step-pill">2</div>
                        <h3>Unduh File</h3>
                        <p>Klik ikon unduh pada baris file untuk menyimpan berkas dari cloud ke perangkat Anda tanpa akses untuk mengubah atau menghapusnya.</p>
                    </article>

                    <article class="step-card">
                        <div class="step-pill">3</div>
                        <h3>Admin Mengelola</h3>
                        <p>Upload dan hapus file hanya tersedia di dashboard admin, sehingga user biasa tetap berada pada akses baca dan unduh saja.</p>
                    </article>
                </div>

                <section class="safety-card" aria-labelledby="safety-title">
                    <div class="safety-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" width="22" height="22">
                            <path d="M12 3.75 19 6.5v5.25c0 4.42-2.83 7.99-7 9.5-4.17-1.51-7-5.08-7-9.5V6.5l7-2.75Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" />
                            <path d="M9.5 12.25 11.2 14l3.3-3.3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </div>
                    <div>
                        <h3 id="safety-title">Keamanan Penyimpanan Awan</h3>
                        <p>Setiap unggahan divalidasi sebelum disimpan. Aplikasi memeriksa jenis berkas, ukuran, dan izin tujuan agar penyimpanan tetap stabil dan aman untuk penggunaan sehari-hari.</p>
                        <div class="safety-list">
                            <span>Divalidasi sebelum disimpan</span>
                            <span>Pratinjau gambar dan dokumen</span>
                            <span>Batas ukuran ditegakkan</span>
                            <span>Role akses dipisahkan</span>
                        </div>
                    </div>
                </section>

                <div class="cta-band">
                    <h3>Butuh cara yang lebih rapi untuk mengatur berkas?</h3>
                    <p><?= authIsAdmin() ? 'Gunakan dashboard untuk mengelola berkas lewat halaman kerja khusus yang lebih fokus, dengan tampilan yang tetap nyaman dibaca di desktop, tablet, dan ponsel.' : 'Login Anda aktif sebagai user biasa. Halaman website tetap dapat dibaca, sedangkan dashboard pengelolaan hanya tersedia untuk admin.'; ?></p>
                    <a class="cta-button" href="<?= authIsAdmin() ? 'dashboard.php' : 'logout.php'; ?>"><?= authIsAdmin() ? 'Buka Dashboard' : 'Logout' ?></a>
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
                            <div class="faq-answer-inner">Unggah berkas hanya tersedia untuk admin melalui dashboard. User biasa dapat melihat dan mengunduh berkas yang sudah tersedia di halaman ini.</div>
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
                            <div class="faq-answer-inner">Admin dapat melihat pratinjau sebelum mengunggah dari dashboard. Di halaman user, gambar yang sudah tersimpan tetap tampil sebagai thumbnail pada daftar berkas.</div>
                        </div>
                    </article>

                    <article class="faq-item">
                        <button class="faq-question" type="button" aria-expanded="false">
                            <span>Berapa batas ukuran unggahan?</span>
                            <span class="faq-icon" aria-hidden="true">+</span>
                        </button>
                        <div class="faq-answer">
                            <div class="faq-answer-inner">Batas unggahan saat ini adalah <?= htmlspecialchars($effectiveUploadLimitLabel); ?> per berkas. Jika berkas Anda lebih besar, silakan kompres terlebih dahulu atau unggah file yang ukurannya lebih kecil.</div>
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
                            <div class="faq-answer-inner">Penghapusan berkas hanya bisa dilakukan oleh admin dari dashboard. User biasa tidak memiliki tombol hapus dan tidak bisa mengakses endpoint delete.</div>
                        </div>
                    </article>
                </div>

                <p class="faq-footnote">Jika pertanyaan Anda belum terjawab, cek daftar berkas cloud atau hubungi admin untuk penambahan dan penghapusan file.</p>
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
                                    <a href="#how-it-works">Cara Kerja</a>
                                    <?php if (authIsAdmin()): ?>
                                        <a href="dashboard.php">Dashboard</a>
                                    <?php endif; ?>
                                    <a href="#faq">FAQ</a>
                                </div>
                            </div>

                            <div class="footer-column">
                                <h4>Perusahaan</h4>
                                <div class="footer-links">
                                    <a href="#top">Tentang</a>
                                    <a href="#how-it-works">Blog</a>
                                    <?php if (authIsAdmin()): ?>
                                        <a href="dashboard.php">Dashboard</a>
                                    <?php endif; ?>
                                    <a href="#faq">Kontak</a>
                                </div>
                            </div>

                            <div class="footer-column">
                                <h4>Sumber Daya</h4>
                                <div class="footer-links">
                                    <a href="#how-it-works">Panduan</a>
                                    <?php if (authIsAdmin()): ?>
                                        <a href="dashboard.php">Lihat Dashboard</a>
                                    <?php endif; ?>
                                    <a href="#faq">Panduan Berkas</a>
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
                                    <path d="M4 4l7.4 8.7L4.1 20h2.2l6.4-6.9L18.4 20H20l-7.8-9.1L20 4h-2.2l-6 6.5L6.6 4H4Z" fill="currentColor" />
                                </svg>
                            </a>
                            <a class="social-link" href="#" aria-label="LinkedIn">
                                <svg viewBox="0 0 24 24" fill="none" width="17" height="17" aria-hidden="true">
                                    <path d="M6.5 9.25V18M6.5 6.2v.1" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                    <path d="M10.25 18v-4.5c0-1.94 1.05-3.25 2.82-3.25 1.72 0 2.68 1.15 2.68 3.25V18M15.75 10.25V18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    <path d="M6.5 5.5a.75.75 0 1 0 0 1.5.75.75 0 0 0 0-1.5Z" fill="currentColor" />
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
