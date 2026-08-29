<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'storage.php';

$currentUser = authRequireLogin();

requireAuthentication();

$csrfToken = $_SESSION['csrf_token'];
$uploadDir = storageUploadDir();
$files = storageListFiles($uploadDir, $currentUser);

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

$appUploadLimitBytes = HAWPIWCLOUD_UPLOAD_MAX_BYTES;
$uploadMaxSizeBytes = iniSizeToBytes((string) ini_get('upload_max_filesize'));
$postMaxSizeBytes = iniSizeToBytes((string) ini_get('post_max_size'));
$serverLimits = array_filter([$uploadMaxSizeBytes, $postMaxSizeBytes], static fn(int $bytes): bool => $bytes > 0);
$effectiveServerUploadLimitBytes = $serverLimits === [] ? $appUploadLimitBytes : min($serverLimits);
$effectiveUploadLimitBytes = min($appUploadLimitBytes, $effectiveServerUploadLimitBytes);
$effectiveUploadLimitLabel = formatFileSize($effectiveUploadLimitBytes);

$status = $_GET['status'] ?? '';
$banner = null;
$publicCssPath = __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'public-bundle.css';
$publicCssSources = [
    $publicCssPath,
    __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'public.css',
    __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'cloud-storage.css',
    __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'responsive.css',
];
$assetVersion = (string) array_reduce($publicCssSources, static function (int $carry, string $path): int {
    return max($carry, is_file($path) ? (int) filemtime($path) : 0);
}, 0);

if ($status === 'upload_success') {
    $banner = ['type' => 'success', 'title' => 'Unggahan selesai', 'message' => 'Berkas Anda berhasil ditambahkan ke penyimpanan.'];
} elseif ($status === 'delete_success') {
    $banner = ['type' => 'success', 'title' => 'Berkas dipindahkan', 'message' => 'Berkas dipindahkan ke Trash dan masih bisa dipulihkan selama retensi aktif.'];
} elseif ($status === 'restore_success') {
    $banner = ['type' => 'success', 'title' => 'File dipulihkan', 'message' => 'File berhasil dikembalikan dari Trash.'];
} elseif ($status === 'error_permissions') {
    $banner = ['type' => 'error', 'title' => 'Penyimpanan tidak tersedia', 'message' => 'Layanan penyimpanan sedang tidak tersedia sementara. Silakan coba lagi.'];
} elseif ($status === 'error_size' || $status === 'error_server_limit') {
    $banner = ['type' => 'error', 'title' => 'Berkas terlalu besar', 'message' => 'Ukuran berkas melebihi batas unggahan saat ini (' . $effectiveUploadLimitLabel . ').'];
} elseif ($status === 'error_partial') {
    $banner = ['type' => 'error', 'title' => 'Unggahan terputus', 'message' => 'Proses unggahan belum selesai. Silakan coba lagi.'];
} elseif ($status === 'error_nofile') {
    $banner = ['type' => 'error', 'title' => 'Tidak ada berkas dipilih', 'message' => 'Pilih berkas terlebih dahulu sebelum mengirim formulir unggahan.'];
} elseif ($status === 'error_type') {
    $banner = ['type' => 'error', 'title' => 'Jenis berkas tidak didukung', 'message' => 'Format file yang dipilih tidak termasuk tipe yang diizinkan.'];
} elseif ($status === 'error_security') {
    $banner = ['type' => 'error', 'title' => 'Permintaan tidak valid', 'message' => 'Sesi atau token keamanan tidak cocok. Muat ulang halaman lalu coba lagi.'];
} elseif ($status === 'error_forbidden') {
    $banner = ['type' => 'error', 'title' => 'Akses dibatasi', 'message' => 'Role akun Anda tidak memiliki kewenangan untuk membuka halaman atau menjalankan aksi tersebut.'];
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/public-bundle.css?v=<?= htmlspecialchars($assetVersion, ENT_QUOTES, 'UTF-8'); ?>">
</head>

<body>
    <main class="shell app-shell">
        <header class="site-header app-header">
            <a class="brand" href="#top" aria-label="Beranda hawpiwcloud">
                <span class="brand-mark" aria-hidden="true">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                </span>
                <span class="brand-name">hawpiwcloud</span>
            </a>

            <nav class="site-nav" aria-label="Primary">
                <a href="#files">Berkas</a>
                <a href="#upload-panel">Upload</a>
                <?php if (authCanUseDashboard($currentUser)): ?>
                    <a href="dashboard.php">Dashboard</a>
                <?php endif; ?>
                <a class="action-button nav-cta" href="logout.php">Logout</a>
            </nav>
        </header>

        <section class="hero app-hero" id="top">
            <div class="hero-copy">
                <div class="hero-kicker">Penyimpanan berkas</div>
                <h1><?= !authCanUseDashboard($currentUser) ? 'Akses file yang dibagikan untuk Viewer' : 'Kelola file dengan tampilan yang ringkas'; ?></h1>
                <p class="subtitle"><?= !authCanUseDashboard($currentUser) ? 'Anda berada di area Viewer. Unduh file yang tersedia tanpa perlu membuka panel yang lebih berat.' : 'Halaman utama ini difokuskan ke daftar file, unggah cepat, dan akses yang langsung dipakai tanpa panel tambahan yang berlebihan.'; ?></p>
            </div>

            <div class="hero-metrics" aria-label="Ringkasan penyimpanan">
                <article class="metric-card">
                    <span>Total File</span>
                    <strong><?= count($files); ?></strong>
                </article>
                <article class="metric-card">
                    <span>Batas Upload</span>
                    <strong><?= htmlspecialchars($effectiveUploadLimitLabel, ENT_QUOTES, 'UTF-8'); ?></strong>
                </article>
                <article class="metric-card">
                    <span>Akses</span>
                    <strong><?= authCanUseDashboard($currentUser) ? 'Penuh' : 'Terbatas'; ?></strong>
                </article>
            </div>
        </section>

        <div class="stack">
            <?php if ($banner !== null): ?>
                <div class="banner <?= htmlspecialchars($banner['type']); ?>" role="status" aria-live="polite">
                    <div class="banner-badge">
                        <?php if ($banner['type'] === 'success'): ?>
                            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                        <?php else: ?>
                            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                        <?php endif; ?>
                    </div>
                    <div>
                        <strong><?= htmlspecialchars($banner['title']); ?></strong>
                        <p><?= htmlspecialchars($banner['message']); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="app-grid app-grid--gallery">
                <section class="panel files-panel files-panel--gallery" aria-labelledby="files-title" id="files">
                    <div class="panel-head">
                        <div>
                            <h2 id="files-title">Berkas Cloud</h2>
                            <span><?= count($files); ?> berkas tersedia untuk dilihat dan diunduh</span>
                        </div>
                    </div>

                    <?php if (count($files) > 0): ?>
                        <div class="gallery-grid">
                            <?php foreach ($files as $file): ?>
                                <article class="gallery-card">
                                    <div class="gallery-media">
                                        <?php if ($file['isImage']): ?>
                                            <img class="file-preview" src="uploads/<?= htmlspecialchars(rawurlencode($file['name']), ENT_QUOTES, 'UTF-8'); ?>" alt="Pratinjau <?= htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8'); ?>" loading="lazy">
                                        <?php else: ?>
                                            <div class="file-icon file-icon--gallery" aria-hidden="true">
                                                <i class="fa-solid fa-file-lines"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="gallery-body">
                                        <div class="gallery-meta">
                                            <span class="gallery-name" title="<?= htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span class="gallery-info"><?= formatFileSize((int) $file['size']); ?> · <?= formatTimestamp((int) $file['modified']); ?></span>
                                        </div>
                                        <div class="gallery-actions">
                                            <a class="action-button download icon-only" href="download.php?file=<?= urlencode($file['name']); ?>" aria-label="Unduh <?= htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8'); ?>" title="Unduh">
                                                <i class="fa-solid fa-download" aria-hidden="true"></i>
                                            </a>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-card">
                                <div class="empty-mark" aria-hidden="true">
                                    <i class="fa-solid fa-cloud-arrow-up"></i>
                                </div>
                                <h3>Belum ada berkas</h3>
                                <p>Cloud masih kosong. File yang diunggah admin dapat dibagikan untuk viewer dan user, sedangkan file user tetap menjadi milik akun tersebut.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </section>
            </div>

            <section class="how-section compact-section" aria-labelledby="how-title">
                <div class="how-header">
                    <h2 id="how-title">Alur Singkat</h2>
                    <p>Login, lihat file, lalu unduh. Tampilan ini dipusatkan pada display dan browsing konten.</p>
                </div>

                <div class="steps-grid compact-steps">
                    <article class="step-card">
                        <div class="step-pill">1</div>
                        <h3>Lihat file</h3>
                        <p>Daftar file ditampilkan langsung dengan preview gambar, nama, ukuran, dan waktu ubah.</p>
                    </article>

                    <article class="step-card">
                        <div class="step-pill">2</div>
                        <h3>Unduh cepat</h3>
                        <p>Klik ikon unduh pada file yang tersedia tanpa perlu masuk ke panel yang berat.</p>
                    </article>

                    <article class="step-card">
                        <div class="step-pill">3</div>
                        <h3>Kelola di dashboard</h3>
                        <p>Jika perlu upload atau aksi lanjutan, dashboard tetap tersedia untuk role yang berwenang.</p>
                    </article>
                </div>
            </section>

            <section class="faq-section compact-section" id="faq" aria-labelledby="faq-title">
                <div class="faq-header">
                    <div class="faq-kicker">Bantuan</div>
                    <h2 id="faq-title">FAQ Singkat</h2>
                    <p>Jawaban singkat untuk hal yang paling sering muncul.</p>
                </div>

                <div class="faq-list">
                    <details class="faq-item">
                        <summary class="faq-question">
                            <span>Bagaimana cara mengunggah berkas?</span>
                            <span class="faq-icon" aria-hidden="true"><i class="fa-solid fa-plus"></i></span>
                        </button>
                        <div class="faq-answer">
                            <div class="faq-answer-inner">Unggah tersedia melalui panel Upload. File yang diunggah user menjadi milik akun tersebut.</div>
                        </div>
                    </details>

                    <details class="faq-item">
                        <summary class="faq-question">
                            <span>Berapa batas ukuran unggahan?</span>
                            <span class="faq-icon" aria-hidden="true"><i class="fa-solid fa-plus"></i></span>
                        </button>
                        <div class="faq-answer">
                            <div class="faq-answer-inner">Batas unggahan saat ini adalah <?= htmlspecialchars($effectiveUploadLimitLabel, ENT_QUOTES, 'UTF-8'); ?> per berkas.</div>
                        </div>
                    </details>

                    <article class="faq-item">
                        <button class="faq-question" type="button" aria-expanded="false">
                            <span>Apakah file aman?</span>
                            <span class="faq-icon" aria-hidden="true"><i class="fa-solid fa-plus"></i></span>
                        </button>
                        <div class="faq-answer">
                            <div class="faq-answer-inner">Setiap unggahan divalidasi sebelum disimpan, lalu diproses lewat backend sesuai role akun.</div>
                        </div>
                    </details>
                </div>
            </section>

            <footer class="site-footer minimal-footer" aria-labelledby="footer-brand-title">
                <div class="footer-inner">
                    <div class="footer-top">
                        <div class="footer-brand-block">
                            <h2 class="footer-brand-name" id="footer-brand-title">hawpiwcloud</h2>
                            <p class="footer-copy">Penyimpanan berkas sederhana untuk penggunaan harian dengan akses berbasis role.</p>
                        </div>

                        <div class="footer-columns" aria-label="Tautan footer">
                            <div class="footer-column">
                                <h4>Produk</h4>
                                <div class="footer-links">
                                    <a href="#files">Berkas</a>
                                    <?php if (authCanUseDashboard($currentUser)): ?>
                                        <a href="dashboard.php">Dashboard</a>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="footer-column">
                                <h4>Bantuan</h4>
                                <div class="footer-links">
                                    <a href="#upload-panel">Upload</a>
                                    <a href="#faq">FAQ</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="footer-divider" aria-hidden="true"></div>

                    <div class="footer-bottom">
                        <div class="footer-social" aria-label="Sosial media">
                            <a class="social-link" href="#" aria-label="X"><i class="fa-brands fa-x-twitter" aria-hidden="true"></i></a>
                            <a class="social-link" href="#" aria-label="LinkedIn"><i class="fa-brands fa-linkedin-in" aria-hidden="true"></i></a>
                        </div>

                        <div class="footer-bottom-copy">© 2026 hawpiwcloud.</div>

                        <div class="footer-policy-links">
                            <a href="#files">Berkas</a>
                            <a href="#upload-panel">Upload</a>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </main>
    <script src="assets/app.js" defer></script>
</body>

</html>
