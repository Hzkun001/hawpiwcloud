<?php

declare(strict_types=1);

function dashboardNavClass(string $activePage, string $page): string
{
    return 'dashboard-nav-link' . ($activePage === $page ? ' is-active' : '');
}

function dashboardRenderLayoutStart(array $context, string $activePage, string $title, string $subtitle): void
{
    $currentUser = $context['currentUser'];
    $assetVersion = $context['assetVersion'];
    ?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?> - hawpiwcloud</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=<?= htmlspecialchars((string) $assetVersion, ENT_QUOTES, 'UTF-8'); ?>">
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
                <a class="<?= dashboardNavClass($activePage, 'overview'); ?>" href="dashboard.php">
                    <i class="fa-solid fa-gauge-high"></i> Ringkasan
                </a>
                <a class="<?= dashboardNavClass($activePage, 'upload'); ?>" href="dashboard-upload.php">
                    <i class="fa-solid fa-cloud-arrow-up"></i> Unggah Berkas
                </a>
                <a class="<?= dashboardNavClass($activePage, 'files'); ?>" href="dashboard-files.php">
                    <i class="fa-solid fa-file-lines"></i> Kelola Berkas
                </a>
                <a class="<?= dashboardNavClass($activePage, 'trash'); ?>" href="trash.php">
                    <i class="fa-solid fa-trash-can-arrow-up"></i> Trash
                </a>
                <?php if ($currentUser['role'] === 'admin'): ?>
                    <a class="<?= dashboardNavClass($activePage, 'users'); ?>" href="dashboard-users.php">
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
                        <h1 class="dashboard-header-title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h1>
                        <span class="dashboard-header-subtitle"><?= htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                </div>

                <div class="dashboard-header-user">
                    <div class="dashboard-avatar">
                        <?= htmlspecialchars(strtoupper(substr((string) $currentUser['name'], 0, 1)), ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <div class="dashboard-user-info">
                        <span class="dashboard-user-name"><?= htmlspecialchars((string) $currentUser['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="dashboard-user-role"><?= htmlspecialchars(authRoleLabel($currentUser), ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                </div>
            </header>

            <div class="dashboard-content stack">
    <?php
}

function dashboardRenderLayoutEnd(): void
{
    ?>
            </div>
        </main>
    </div>
    <script src="assets/app.js" defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const toggle = document.getElementById('mobile-toggle');
            const sidebar = document.getElementById('sidebar');

            if (!toggle || !sidebar) {
                return;
            }

            toggle.addEventListener('click', () => {
                sidebar.classList.toggle('is-open');
            });

            document.addEventListener('click', (event) => {
                if (sidebar.classList.contains('is-open') && !sidebar.contains(event.target) && !toggle.contains(event.target)) {
                    sidebar.classList.remove('is-open');
                }
            });
        });
    </script>
</body>

</html>
    <?php
}
