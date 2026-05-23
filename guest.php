<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'storage.php';

$uploadDir = storageUploadDir();
$files = storageListFiles($uploadDir, null);
$assetVersion = (string) filemtime(__DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'styles.css');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guest hawpiwcloud</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="assets/styles.css?v=<?= htmlspecialchars($assetVersion, ENT_QUOTES, 'UTF-8'); ?>">
</head>

<body>
    <main class="shell">
        <header class="site-header">
            <a class="brand" href="guest.php" aria-label="Guest hawpiwcloud">
                <span class="brand-mark" aria-hidden="true">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                </span>
                <span class="brand-name">hawpiwcloud</span>
            </a>

            <nav class="site-nav" aria-label="Primary">
                <a href="#files">Berkas Guest</a>
                <a class="action-button nav-cta" href="login.php">Login</a>
            </nav>
        </header>

        <section class="hero" id="top">
            <h1>Akses Guest</h1>
            <p class="subtitle">Guest hanya dapat melihat file yang dibagikan admin. Tombol download tidak tersedia pada akses ini.</p>
            <div style="margin-top: 1.5rem;">
                <a class="primary-button" href="login.php">Login ke Akun Anda</a>
            </div>
        </section>

        <section class="panel files-panel" aria-labelledby="files-title" id="files">
            <div class="panel-head">
                <div>
                    <h2 id="files-title">Tabel Khusus Guest</h2>
                    <span><?= count($files); ?> file dapat dilihat</span>
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
                                        <div class="file-name">
                                            <?php if ($file['isImage']): ?>
                                                <img class="file-preview" src="uploads/<?= htmlspecialchars(rawurlencode($file['name']), ENT_QUOTES, 'UTF-8'); ?>" alt="Pratinjau <?= htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8'); ?>" loading="lazy">
                                            <?php else: ?>
                                                <div class="file-icon" aria-hidden="true">
                                                    <i class="fa-solid fa-file-lines"></i>
                                                </div>
                                            <?php endif; ?>
                                            <span title="<?= htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($file['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                    </td>
                                    <td class="meta" data-label="Ukuran"><?= storageFormatFileSize((int) $file['size']); ?></td>
                                    <td class="meta" data-label="Terakhir Diubah"><?= storageFormatTimestamp((int) $file['modified']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-card">
                        <h3>Belum ada file untuk Guest</h3>
                        <p>Admin belum menandai file apa pun untuk tabel Guest.</p>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>

</html>
