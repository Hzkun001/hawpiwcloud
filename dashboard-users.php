<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'dashboard-context.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'dashboard-layout.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'dashboard-sections.php';

$context = dashboardBuildContext();
if ($context['currentUser']['role'] !== 'admin') {
    header('Location: dashboard.php?status=error_forbidden');
    exit;
}

dashboardRenderLayoutStart($context, 'users', 'Manajemen User', 'Tambah, lihat, dan hapus akun pengguna');
dashboardRenderBanner($context['banner']);
dashboardRenderUsers($context);
dashboardRenderLayoutEnd();
