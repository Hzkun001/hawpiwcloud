#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . 'bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$options = getopt('', ['file::', 'older-than-days::']);
try {
    $trash = new TrashManager(BackupConfig::fromEnvironment(dirname(__DIR__)));
    if (isset($options['file']) && is_string($options['file']) && $options['file'] !== '') {
        $result = [$trash->purge($options['file'])];
    } else {
        $days = isset($options['older-than-days']) ? (int) $options['older-than-days'] : 30;
        $result = $trash->purgeOlderThanDays($days);
    }

    echo json_encode(['purged' => count($result), 'items' => $result], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $exception) {
    if (isset($trash)) {
        $config = BackupConfig::fromEnvironment(dirname(__DIR__));
        (new BackupLogger($config))->audit('trash.purge', 'failed', 'system', 'cli', ['error' => $exception->getMessage()]);
    }
    fwrite(STDERR, 'Purge Trash gagal: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
