<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'dashboard-context.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'dashboard-layout.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'dashboard-sections.php';

$context = dashboardBuildContext();

dashboardRenderLayoutStart(
    $context,
    'overview',
    'Dashboard',
    $context['currentUser']['role'] === 'admin' ? 'Ringkasan penyimpanan dan status backup' : 'Ringkasan penyimpanan pribadi Anda'
);
dashboardRenderBanner($context['banner']);
dashboardRenderOverview($context);
dashboardRenderLayoutEnd();
