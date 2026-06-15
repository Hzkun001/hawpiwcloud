<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'storage.php';

$currentUser = authRequireLogin();
if (!authCanUseDashboard($currentUser)) {
    header('Location: index.php?status=error_forbidden');
    exit;
}

if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];
$trashFiles = storageListTrashFiles($currentUser);
$status = $_GET['status'] ?? '';
$banner = null;
$assetVersion = (string) filemtime(__DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'dashboard.css');

if ($status === 'restore_success') {
    $banner = ['type' => 'success', 'title' => 'File dipulihkan', 'message' => 'File berhasil dikembalikan dari Trash ke daftar upload.'];
} elseif ($status === 'restore_failed') {
    $banner = ['type' => 'error', 'title' => 'Restore gagal', 'message' => 'File tidak dapat dipulihkan dari Trash. Cek log aplikasi untuk detail.'];
} elseif ($status === 'error_security') {
    $banner = ['type' => 'error', 'title' => 'Permintaan tidak valid', 'message' => 'Sesi atau token keamanan tidak cocok. Muat ulang halaman dan coba lagi.'];
} elseif ($status === 'error_forbidden') {
    $banner = ['type' => 'error', 'title' => 'Akses ditolak', 'message' => 'Anda tidak memiliki akses untuk memulihkan file tersebut.'];
} elseif ($status === 'error') {
    $banner = ['type' => 'error', 'title' => 'Terjadi kesalahan', 'message' => 'Permintaan Trash tidak dapat diproses.'];
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trash - hawpiwcloud</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?= htmlspecialchars($assetVersion, ENT_QUOTES, 'UTF-8'); ?>">
</head>

<body class="dashboard-body">
    <div class="dashboard-layout">
        <aside class="dashboard-sidebar" id="sidebar">
            <div class="dashboard-sidebar-header">
                <a class="brand" href="dashboard.php" aria-label="Dashboard hawpiwcloud">
                    <span class="brand-mark" aria-hidden="true">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                    </span>
                    <span class="brand-name">hawpiwcloud</span>
                </a>
            </div>

            <nav class="dashboard-nav" aria-label="Primary">
                <a class="dashboard-nav-link" href="dashboard.php">
                    <i class="fa-solid fa-gauge-high"></i> Ringkasan
                </a>
                <a class="dashboard-nav-link" href="dashboard-upload.php">
                    <i class="fa-solid fa-cloud-arrow-up"></i> Unggah Berkas
                </a>
                <a class="dashboard-nav-link" href="dashboard-files.php">
                    <i class="fa-solid fa-file-lines"></i> Kelola Berkas
                </a>
                <a class="dashboard-nav-link is-active" href="trash.php">
                    <i class="fa-solid fa-trash-can-arrow-up"></i> Trash
                </a>
                <?php if ($currentUser['role'] === 'admin'): ?>
                    <a class="dashboard-nav-link" href="dashboard-users.php">
                        <i class="fa-solid fa-users"></i> Manajemen User
                    </a>
                    <a class="dashboard-nav-link" href="backup-dashboard.php">
                        <i class="fa-solid fa-box-archive"></i> Status Backup
                    </a>
                <?php endif; ?>
            </nav>

            <div class="dashboard-sidebar-footer">
                <a class="dashboard-nav-link" href="index.php">
                    <i class="fa-solid fa-house"></i> Halaman Depan
                </a>
                <a class="dashboard-nav-logout" href="logout.php">
                    <i class="fa-solid fa-right-from-bracket"></i> Logout
                </a>
            </div>
        </aside>

        <main class="dashboard-main">
            <header class="dashboard-header">
                <div class="dashboard-header-left">
                    <button class="dashboard-mobile-toggle" id="mobile-toggle" aria-label="Toggle menu">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                    <div>
                        <h1 class="dashboard-header-title">Trash</h1>
                        <span class="dashboard-header-subtitle">Pulihkan file yang belum dihapus permanen</span>
                    </div>
                </div>

                <div class="dashboard-header-user">
                    <div class="dashboard-avatar">
                        <?= htmlspecialchars(strtoupper(substr($currentUser['name'], 0, 1)), ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <div class="dashboard-user-info">
                        <span class="dashboard-user-name"><?= htmlspecialchars($currentUser['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="dashboard-user-role"><?= htmlspecialchars(authRoleLabel($currentUser), ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                </div>
            </header>

            <div class="dashboard-content stack">
                <?php if ($banner !== null): ?>
                    <div class="banner <?= htmlspecialchars($banner['type'], ENT_QUOTES, 'UTF-8'); ?>" role="status" aria-live="polite">
                        <div class="banner-badge" aria-hidden="true">
                            <?php if ($banner['type'] === 'success'): ?>
                                <i class="fa-solid fa-check"></i>
                            <?php else: ?>
                                <i class="fa-solid fa-triangle-exclamation"></i>
                            <?php endif; ?>
                        </div>
                        <div>
                            <strong><?= htmlspecialchars($banner['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <p><?= htmlspecialchars($banner['message'], ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <section class="panel files-panel" aria-labelledby="trash-title">
                    <div class="panel-head">
                        <div>
                            <h2 id="trash-title">File di Trash</h2>
                            <span><?= count($trashFiles); ?> file dapat dipulihkan</span>
                        </div>
                    </div>

                    <?php if ($trashFiles !== []): ?>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Nama Berkas</th>
                                        <th>Ukuran</th>
                                        <th>Pemilik</th>
                                        <th>Dihapus Oleh</th>
                                        <th>Waktu Dihapus</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($trashFiles as $file): ?>
                                        <tr>
                                            <td data-label="Nama Berkas">
                                                <div class="file-name">
                                                    <div class="file-icon" aria-hidden="true">
                                                        <i class="fa-solid fa-file-arrow-up"></i>
                                                    </div>
                                                    <span title="<?= htmlspecialchars((string) $file['originalName'], ENT_QUOTES, 'UTF-8'); ?>">
                                                        <?= htmlspecialchars((string) $file['originalName'], ENT_QUOTES, 'UTF-8'); ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="meta" data-label="Ukuran"><?= htmlspecialchars(storageFormatFileSize((int) $file['size']), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="meta" data-label="Pemilik"><?= htmlspecialchars((string) $file['owner'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="meta" data-label="Dihapus Oleh"><?= htmlspecialchars((string) $file['deletedBy'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="meta" data-label="Waktu Dihapus"><?= htmlspecialchars((string) $file['deletedAt'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="meta" data-label="Aksi">
                                                <form class="action-form" action="trash-action.php" method="post" onsubmit="return confirm('Pulihkan file ini dari Trash?');">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="hidden" name="action" value="restore">
                                                    <input type="hidden" name="stored_name" value="<?= htmlspecialchars((string) $file['storedName'], ENT_QUOTES, 'UTF-8'); ?>">
                                                    <button class="action-button download" type="submit">
                                                        <i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Restore
                                                    </button>
                                                </form>
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
                                    <i class="fa-solid fa-trash-can"></i>
                                </div>
                                <h3>Trash kosong</h3>
                                <p>File yang dihapus akan muncul di sini selama belum melewati retention dan belum dipurge permanen.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </main>
    </div>

    <script src="assets/app.js" defer></script>
</body>
</html>
