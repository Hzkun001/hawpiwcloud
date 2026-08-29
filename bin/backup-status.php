#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . 'bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

try {
    $config = BackupConfig::fromEnvironment(dirname(__DIR__));
    $config->ensureDirectories();
    $status = (new BackupLogger($config))->readStatus();
    echo json_encode($status === [] ? ['status' => 'never_run'] : $status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Tidak dapat membaca status: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
