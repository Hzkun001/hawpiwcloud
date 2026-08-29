#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . 'bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$options = getopt('', ['reason::', 'type::', 'compression::', 'compression-level::']);
$reason = isset($options['reason']) && is_string($options['reason']) ? $options['reason'] : 'manual';

try {
    $manager = new BackupManager(BackupConfig::fromEnvironment(dirname(__DIR__)));
    echo json_encode($manager->create($reason, [
        'type' => isset($options['type']) && is_string($options['type']) ? $options['type'] : 'full',
        'compression' => isset($options['compression']) && is_string($options['compression']) ? $options['compression'] : null,
        'compression_level' => isset($options['compression-level']) ? (int) $options['compression-level'] : null,
    ]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Backup gagal: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
