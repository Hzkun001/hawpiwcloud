#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . 'bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

try {
    echo json_encode((new BackupManager(BackupConfig::fromEnvironment(dirname(__DIR__))))->applyRetention(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Retention gagal: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

