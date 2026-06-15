<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'dashboard-context.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'dashboard-layout.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'dashboard-sections.php';

$context = dashboardBuildContext();

dashboardRenderLayoutStart($context, 'upload', 'Unggah Berkas', 'Tambah file baru dan atur akses Viewer');
dashboardRenderBanner($context['banner']);
dashboardRenderUpload($context);
dashboardRenderLayoutEnd();
