#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . 'bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$options = getopt('', ['archive:']);
$archive = isset($options['archive']) && is_string($options['archive']) ? $options['archive'] : '';
if ($archive === '') {
    fwrite(STDERR, 'Gunakan: php bin/verify-backup.php --archive=/path/to/archive' . PHP_EOL);
    exit(2);
}

try {
    echo json_encode((new BackupManager(BackupConfig::fromEnvironment(dirname(__DIR__))))->verify($archive), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Verifikasi gagal: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

