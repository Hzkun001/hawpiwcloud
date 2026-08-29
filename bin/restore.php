#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . 'bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$options = getopt('', ['archive::', 'snapshot::', 'components::', 'yes', 'skip-database', 'skip-safety-backup']);
$archive = isset($options['archive']) && is_string($options['archive']) ? $options['archive'] : '';
$snapshot = isset($options['snapshot']) && is_string($options['snapshot']) ? $options['snapshot'] : '';
if (($archive === '' && $snapshot === '') || !isset($options['yes'])) {
    fwrite(STDERR, 'Gunakan: php bin/restore.php --snapshot=RESTORE_POINT_ID --yes [--components=uploads,trash,database,source] [--skip-safety-backup]' . PHP_EOL);
    exit(2);
}

try {
    $manager = new BackupManager(BackupConfig::fromEnvironment(dirname(__DIR__)));
    if ($snapshot !== '') {
        $components = isset($options['components']) && is_string($options['components']) ? explode(',', $options['components']) : ['uploads', 'trash', 'database'];
        $result = $manager->restorePoint($snapshot, $components, !isset($options['skip-safety-backup']));
    } else {
        $result = $manager->restore($archive, !isset($options['skip-database']), !isset($options['skip-safety-backup']));
    }
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Restore gagal: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
