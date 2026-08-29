<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';

authStartSession();

try {
    $currentUser = authRequireAdmin();
} catch (Throwable $exception) {
    error_log('hawpiwcloud backup download auth error: ' . $exception->getMessage());
    header('Location: dashboard.php?status=error_forbidden');
    exit;
}

if ((!isset($_GET['snapshot_id']) || !is_string($_GET['snapshot_id']) || $_GET['snapshot_id'] === '')
    && (!isset($_GET['archive']) || !is_string($_GET['archive']) || $_GET['archive'] === '')) {
    header('Location: backup-dashboard.php');
    exit;
}

$snapshotId = isset($_GET['snapshot_id']) && is_string($_GET['snapshot_id']) ? $_GET['snapshot_id'] : '';
$requestedArchive = isset($_GET['archive']) && is_string($_GET['archive']) ? $_GET['archive'] : '';

$bootstrap = __DIR__ . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . 'bootstrap.php';
if (!is_file($bootstrap)) {
    header('Location: backup-dashboard.php');
    exit;
}

require_once $bootstrap;

try {
    $config = BackupConfig::fromEnvironment(__DIR__);
    $manager = new BackupManager($config);
    $points = $manager->listRestorePoints();

    $archivePath = null;
    if ($requestedArchive !== '') {
        $archivePath = $requestedArchive;
    } else {
        foreach ($points as $point) {
            if ((string) ($point['snapshot_id'] ?? '') === $snapshotId) {
                $archivePath = is_string($point['archive'] ?? null) ? (string) $point['archive'] : null;
                break;
            }
        }
    }

    if (!is_string($archivePath) || $archivePath === '') {
        header('Location: backup-dashboard.php?status=error');
        exit;
    }

    $resolvedArchive = realpath($archivePath);
    $resolvedBackupRoot = realpath($config->backupRoot) ?: $config->backupRoot;

    if ($resolvedArchive === false || !is_file($resolvedArchive)) {
        header('Location: backup-dashboard.php?status=error');
        exit;
    }

    $backupRootPrefix = $resolvedBackupRoot . DIRECTORY_SEPARATOR;
    if (substr($resolvedArchive, 0, strlen($backupRootPrefix)) !== $backupRootPrefix && $resolvedArchive !== $resolvedBackupRoot) {
        header('Location: backup-dashboard.php?status=error');
        exit;
    }

    $filename = basename($resolvedArchive);
    if (substr(strtolower($filename), -4) !== '.zip') {
        header('Location: backup-dashboard.php?status=error');
        exit;
    }

    header('Content-Type: application/zip');
    header('Content-Length: ' . (string) filesize($resolvedArchive));
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('X-Content-Type-Options: nosniff');
    readfile($resolvedArchive);
    exit;
} catch (Throwable $exception) {
    error_log('hawpiwcloud backup download error: ' . $exception->getMessage());
    header('Location: backup-dashboard.php?status=error');
    exit;
}
