<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'dashboard-context.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'dashboard-layout.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'dashboard-sections.php';

$context = dashboardBuildContext();

dashboardRenderLayoutStart(
    $context,
    'files',
    'Kelola Berkas',
    $context['currentUser']['role'] === 'admin' ? 'Kelola semua file dan tabel penyimpanan per user' : 'Kelola file milik Anda'
);
dashboardRenderBanner($context['banner']);
dashboardRenderOwnerTables($context);
dashboardRenderFiles($context);
dashboardRenderLayoutEnd();
