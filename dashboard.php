<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';

$currentUser = authRequireAdmin();

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

$imageCount = count(array_filter($files, static fn(array $file): bool => (bool) $file['isImage']));
$latestLabel = $files !== [] ? formatTimestamp((int) $files[0]['modified']) : 'Belum ada berkas';

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
} elseif ($status === 'error') {
    $banner = ['type' => 'error', 'title' => 'Terjadi kesalahan', 'message' => 'Silakan coba lagi dan pastikan berkas yang dipilih sudah benar.'];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>hawpiwcloud Dashboard</title>
    <link rel="stylesheet" href="assets/styles.css?v=<?= htmlspecialchars($assetVersion, ENT_QUOTES, 'UTF-8'); ?>">
</head>

<body>
    <main class="shell">
        <header class="site-header">
            <a class="brand" href="dashboard.php" aria-label="Dashboard hawpiwcloud">
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
                <a href="#overview">Ringkasan</a>
                <a href="#upload-panel">Upload</a>
                <a href="#files">Berkas</a>
                <a class="action-button nav-cta" href="index.php">Landing</a>
                <a class="action-button nav-cta" href="logout.php">Logout</a>
            </nav>
        </header>

        <section class="hero" id="top">
            <h1>Dashboard hawpiwcloud</h1>
            <p class="subtitle">Semua kontrol penting ada di satu layar: lihat ringkasan, unggah file baru, dan kelola berkas tersimpan tanpa harus berpindah halaman.</p>
        </section>

        <section class="cta-band" id="overview" aria-labelledby="dashboard-title">
            <h3 id="dashboard-title">Ruang Kerja Aktif</h3>
            <p>Login sebagai <?= htmlspecialchars($currentUser['name'], ENT_QUOTES, 'UTF-8'); ?>. Gunakan dashboard ini untuk memantau file tersimpan, memulai unggahan baru, dan memastikan pratinjau gambar tetap mudah dicek.</p>
            <div class="entry-actions">
                <a class="primary-button" href="#upload-panel">
                    <svg class="button-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M12 16V4" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" />
                        <path d="m7.5 8.5 4.5-4.5 4.5 4.5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" />
                        <path d="M5.5 15.5V17A2.5 2.5 0 0 0 8 19.5h8A2.5 2.5 0 0 0 18.5 17v-1.5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    Unggah Baru
                </a>
                <a class="secondary-button" href="#files">
                    <svg class="button-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M7 3.75h6.5L19 9.25V20a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4.75a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" />
                        <path d="M13.5 3.75V9.25H19" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" />
                    </svg>
                    Lihat Daftar
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

            <div class="steps-grid" aria-label="Ringkasan dashboard">
                <article class="step-card">
                    <div class="step-pill">1</div>
                    <h3><?= count($files); ?> Berkas Tersimpan</h3>
                    <p>Seluruh file yang ada di folder penyimpanan tampil di sini dan bisa diunduh atau dihapus langsung dari dashboard.</p>
                </article>

                <article class="step-card">
                    <div class="step-pill">2</div>
                    <h3><?= $imageCount; ?> Preview Gambar</h3>
                    <p>File gambar ditampilkan dengan thumbnail sehingga isi berkas bisa dicek tanpa perlu membuka file satu per satu.</p>
                </article>

                <article class="step-card">
                    <div class="step-pill">3</div>
                    <h3><?= htmlspecialchars($effectiveUploadLimitLabel); ?> Batas Upload</h3>
                    <p>Ukuran unggahan dibatasi otomatis agar server tetap aman, dan dashboard akan menyesuaikan peringatan jika file terlalu besar.</p>
                </article>
            </div>

            <section class="panel" id="upload-panel" aria-labelledby="upload-title">
                <div class="panel-head">
                    <div>
                        <h2 id="upload-title">Unggah Berkas</h2>
                        <span>Gunakan panel ini untuk menambah file baru ke ruang kerja Anda.</span>
                    </div>
                </div>

                <div class="upload-card">
                    <form action="upload.php" method="post" enctype="multipart/form-data" id="upload-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="upload-grid">
                            <label class="dropzone" id="dropzone" for="file-input">
                                <input class="dropzone-input" id="file-input" type="file" name="fileToUpload" accept="*/*" data-max-file-bytes="<?= htmlspecialchars((string) $effectiveUploadLimitBytes, ENT_QUOTES, 'UTF-8'); ?>" data-max-file-label="<?= htmlspecialchars($effectiveUploadLimitLabel, ENT_QUOTES, 'UTF-8'); ?>" required>
                                <div class="dropzone-content" id="dropzone-content">
                                    <div class="dropzone-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" width="28" height="28">
                                            <path d="M12 16V4" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" />
                                            <path d="m7.5 8.5 4.5-4.5 4.5 4.5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" />
                                            <path d="M5.5 15.5V17A2.5 2.5 0 0 0 8 19.5h8A2.5 2.5 0 0 0 18.5 17v-1.5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" />
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
                                            <path d="M7 3.75h6.5L19 9.25V20a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4.75a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" />
                                            <path d="M13.5 3.75V9.25H19" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" />
                                        </svg>
                                        <div>Pratinjau unggahan akan muncul di sini</div>
                                        <span>Pilih gambar atau dokumen untuk memastikan berkas sebelum dikirim.</span>
                                    </div>
                                    <img class="preview-image" id="preview-image" alt="Pratinjau berkas">
                                    <div class="preview-icon" id="preview-icon" aria-hidden="true">
                                        <svg viewBox="0 0 24 24" fill="none" width="34" height="34">
                                            <path d="M7 3.75h6.5L19 9.25V20a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4.75a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
                                            <path d="M13.5 3.75V9.25H19" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round" />
                                        </svg>
                                    </div>
                                </div>

                                <div class="preview-meta">
                                    <strong id="preview-name">Belum ada berkas yang dipilih</strong>
                                    <span id="preview-details">Ukuran dan jenis berkas akan tampil di sini.</span>
                                    <div class="helper-note">
                                        <span><strong>Tips:</strong> Gambar akan menampilkan thumbnail secara otomatis.</span>
                                        <span>Batas unggahan saat ini: <?= htmlspecialchars($effectiveUploadLimitLabel); ?> per berkas.</span>
                                    </div>
                                    <p class="upload-feedback" id="upload-feedback" role="alert" aria-live="assertive" hidden></p>
                                </div>

                                <div class="upload-actions">
                                    <button class="secondary-button" type="button" id="clear-file">Atur Ulang</button>
                                    <button class="primary-button" type="submit">
                                        <svg class="button-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="M12 16V4" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" />
                                            <path d="m7.5 8.5 4.5-4.5 4.5 4.5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" />
                                            <path d="M5.5 15.5V17A2.5 2.5 0 0 0 8 19.5h8A2.5 2.5 0 0 0 18.5 17v-1.5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        Unggah Berkas
                                    </button>
                                </div>
                            </aside>
                        </div>
                    </form>
                </div>
            </section>

            <section class="panel files-panel" aria-labelledby="files-title" id="files">
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
                                                    <span title="<?= htmlspecialchars($file['name']); ?>"><?= htmlspecialchars($file['name']); ?></span>
                                                </div>
                                                <div class="actions file-actions">
                                                    <a class="action-button download icon-only" href="download.php?file=<?= urlencode($file['name']); ?>" aria-label="Unduh <?= htmlspecialchars($file['name']); ?>" title="Unduh">
                                                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                            <path d="M12 3.75v9.5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" />
                                                            <path d="m8.25 9.75 3.75 3.75 3.75-3.75" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" />
                                                            <path d="M5.5 18.5h13" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" />
                                                        </svg>
                                                    </a>
                                                    <form class="action-form" action="delete.php" method="post" onsubmit="return confirm('Hapus berkas ini?');">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                                        <input type="hidden" name="file" value="<?= htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8'); ?>">
                                                        <button class="action-button delete icon-only" type="submit" aria-label="Hapus <?= htmlspecialchars($file['name']); ?>" title="Hapus">
                                                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                                <path d="M5.75 7h12.5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" />
                                                                <path d="M9 7V5.75A1.75 1.75 0 0 1 10.75 4h2.5A1.75 1.75 0 0 1 15 5.75V7" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" />
                                                                <path d="M8.5 7.25l.55 10.3A1.75 1.75 0 0 0 10.8 19h2.4a1.75 1.75 0 0 0 1.75-1.45l.55-10.3" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round" />
                                                                <path d="M10.25 10.25v4.5M13.75 10.25v4.5" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" />
                                                            </svg>
                                                        </button>
                                                    </form>
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
                            <p>Penyimpanan Anda masih kosong. Unggah berkas untuk menampilkan daftar dan mulai mengelolanya dari dashboard ini.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>
    <script src="assets/app.js" defer></script>
</body>

</html>
