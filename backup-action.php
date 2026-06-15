<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'storage.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . 'bootstrap.php';

$currentUser = authRequireAdmin();
authStartSession();

function backupRedirect(string $type, string $message): void
{
    $_SESSION['backup_flash'] = ['type' => $type, 'message' => $message];
    header('Location: backup-dashboard.php');
    exit;
}

function backupValidCsrf(?string $token): bool
{
    return isset($_SESSION['csrf_token']) && is_string($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !backupValidCsrf($_POST['csrf_token'] ?? null)) {
    backupRedirect('error', 'Permintaan tidak valid. Muat ulang halaman lalu coba lagi.');
}

$action = isset($_POST['action']) ? (string) $_POST['action'] : '';
$ipAddress = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : 'web';
$actor = (string) $currentUser['username'];

try {
    $config = BackupConfig::fromEnvironment(__DIR__);
    $manager = new BackupManager($config);

    if ($action === 'create') {
        $type = ($_POST['type'] ?? '') === 'incremental' ? 'incremental' : 'full';
        $compression = in_array($_POST['compression'] ?? '', ['zip', 'tar.gz', '7z'], true) ? (string) $_POST['compression'] : 'zip';
        $level = max(0, min(9, (int) ($_POST['compression_level'] ?? 6)));
        $result = $manager->create('dashboard-manual', [
            'type' => $type,
            'compression' => $compression,
            'compression_level' => $level,
            'actor' => $actor,
            'ip_address' => $ipAddress,
        ]);
        backupRedirect('success', 'Backup manual ' . $result['snapshot_id'] . ' berhasil dibuat.');
    }

    if ($action === 'restore') {
        $snapshotId = (string) ($_POST['snapshot_id'] ?? '');
        $components = array_values(array_intersect((array) ($_POST['components'] ?? []), ['source', 'database', 'uploads', 'trash']));
        if ($snapshotId === '' || $components === []) {
            throw new RuntimeException('Pilih restore point dan minimal satu komponen.');
        }
        $manager->restorePoint($snapshotId, $components, true, $actor, $ipAddress);
        backupRedirect('success', 'Restore point ' . $snapshotId . ' berhasil dipulihkan.');
    }

    if ($action === 'verify') {
        $snapshotId = (string) ($_POST['snapshot_id'] ?? '');
        $point = null;
        foreach ($manager->listRestorePoints() as $candidate) {
            if (($candidate['snapshot_id'] ?? '') === $snapshotId) {
                $point = $candidate;
            }
        }
        if (!is_array($point)) {
            throw new RuntimeException('Restore point tidak ditemukan.');
        }
        $manager->verify((string) $point['archive'], $actor, $ipAddress);
        backupRedirect('success', 'Integritas restore point ' . $snapshotId . ' valid.');
    }

    if ($action === 'retention') {
        $result = $manager->applyRetention($actor, $ipAddress);
        backupRedirect('success', 'Retention selesai. ' . $result['count'] . ' restore point lama dihapus.');
    }

    if ($action === 'restore_version') {
        $fileName = basename((string) ($_POST['file_name'] ?? ''));
        $versionId = (string) ($_POST['version_id'] ?? '');
        (new FileVersionManager($config))->restore($fileName, $versionId, storageUploadDir());
        (new BackupLogger($config))->audit('file.version.restore', 'success', $actor, $ipAddress, ['file' => $fileName, 'version_id' => $versionId]);
        backupRedirect('success', 'Versi file ' . $fileName . ' berhasil dipulihkan.');
    }

    throw new RuntimeException('Aksi backup tidak didukung.');
} catch (Throwable $exception) {
    if (isset($config) && $config instanceof BackupConfig) {
        (new BackupLogger($config))->audit('backup.dashboard.' . ($action !== '' ? $action : 'unknown'), 'failed', $actor, $ipAddress, ['error' => $exception->getMessage()]);
    }
    backupRedirect('error', $exception->getMessage());
}
