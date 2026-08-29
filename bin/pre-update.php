#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . 'bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$separator = array_search('--', $argv, true);
$command = $separator === false ? [] : array_slice($argv, $separator + 1);
if ($command === []) {
    fwrite(STDERR, 'Gunakan: php bin/pre-update.php -- command [arg ...]' . PHP_EOL);
    exit(2);
}

try {
    $manager = new BackupManager(BackupConfig::fromEnvironment(dirname(__DIR__)));
    $backup = $manager->create('pre-update', ['type' => 'full']);
    fwrite(STDOUT, 'Backup sebelum update selesai: ' . $backup['archive'] . PHP_EOL);
    BackupProcess::run($command);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Update dibatalkan atau gagal: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
