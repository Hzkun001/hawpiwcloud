<?php

declare(strict_types=1);

final class BackupManager
{
    private $config;
    private $logger;
    private $databaseDumper;
    private $offsite;
    private $crypto;
    private $notifier;

    public function __construct(BackupConfig $config)
    {
        $this->config = $config;
        $this->config->ensureDirectories();
        $this->logger = new BackupLogger($this->config);
        $this->databaseDumper = new BackupDatabaseDumper($this->config);
        $this->offsite = new BackupOffsiteReplicator($this->config);
        $this->crypto = new BackupCrypto($this->config);
        $this->notifier = new BackupNotifier($this->config, $this->logger);
    }

    public function create(string $reason = 'manual', array $options = []): array
    {
        $actor = (string) ($options['actor'] ?? 'system');
        $ipAddress = (string) ($options['ip_address'] ?? 'cli');
        $webFriendly = $this->config->webFriendlyMode();

        return $this->withLock(function () use ($reason, $options, $actor, $ipAddress, $webFriendly): array {
            $startedAt = date(DATE_ATOM);
            $date = date('Y-m-d');
            $snapshotId = $this->snapshotId($reason);
            $relativeDirectory = $date . DIRECTORY_SEPARATOR . $snapshotId;
            $snapshotDir = $this->config->backupRoot . DIRECTORY_SEPARATOR . $relativeDirectory;
            $format = $this->normalizeFormat((string) ($options['compression'] ?? $this->config->compressionFormat));
            $level = max(0, min(9, (int) ($options['compression_level'] ?? $this->config->compressionLevel)));
            if ($webFriendly) {
                $format = 'zip';
                $level = min($level, 6);
            }
            $extension = BackupArchive::extension($format);
            $requestedType = strtolower((string) ($options['type'] ?? 'full'));
            $previousInventory = $this->readJson($this->config->inventoryPath);
            $type = $requestedType === 'incremental' && isset($previousInventory['snapshot_id']) ? 'incremental' : 'full';
            $inventory = $this->buildInventory();
            $changes = $this->calculateChanges($inventory, $previousInventory['files'] ?? [], $type);
            $archiveName = 'backup_cloud_storage_' . $date . '_' . $snapshotId . $extension;
            $plainArchivePath = $snapshotDir . DIRECTORY_SEPARATOR . $archiveName;

            $this->logger->info('backup.started', ['reason' => $reason, 'snapshot' => $snapshotId, 'type' => $type]);
            $this->logger->audit('backup.create', 'started', $actor, $ipAddress, ['snapshot' => $snapshotId, 'type' => $type]);

            try {
                $this->makeDirectory($snapshotDir);
                $statePath = $snapshotDir . DIRECTORY_SEPARATOR . 'application_state.json';
                $this->writeJson($statePath, $this->readApplicationState());

                $sourceArchive = $snapshotDir . DIRECTORY_SEPARATOR . 'source_code' . $extension;
                BackupArchive::createFromDirectory($this->config->appRoot, $sourceArchive, function (string $relative, string $path) use ($type, $changes): bool {
                    return $this->includeSource($relative, $path, $type, $changes['source']['changed']);
                }, $level);

                $uploadsArchive = $snapshotDir . DIRECTORY_SEPARATOR . 'uploads' . $extension;
                BackupArchive::createFromDirectory($this->config->appRoot . DIRECTORY_SEPARATOR . 'uploads', $uploadsArchive, function (string $relative, string $path) use ($type, $changes): bool {
                    return $type === 'full' || is_dir($path) || isset($changes['uploads']['changed'][$relative]);
                }, $level);

                $trashArchive = $snapshotDir . DIRECTORY_SEPARATOR . 'trash' . $extension;
                BackupArchive::createFromDirectory($this->config->trashDir, $trashArchive, function (string $relative, string $path) use ($type, $changes): bool {
                    return $type === 'full' || is_dir($path) || isset($changes['trash']['changed'][$relative]);
                }, $level);

                $database = $snapshotDir . DIRECTORY_SEPARATOR . 'database.sql';
                $this->databaseDumper->dump($database, $this->readApplicationState());
                $datedDatabase = $snapshotDir . DIRECTORY_SEPARATOR . 'database_cloud_storage_' . $date . '.sql';
                if (!copy($database, $datedDatabase)) {
                    throw new RuntimeException('Tidak dapat membuat arsip database bertanggal.');
                }

                $storedArtifacts = [];
                $checksumFiles = [];
                foreach ([$sourceArchive, $uploadsArchive, $trashArchive, $datedDatabase, $database, $statePath] as $artifact) {
                    $stored = $this->crypto->encrypt($artifact);
                    $storedArtifacts[basename($stored)] = $stored;
                    $checksum = $this->writeChecksum($stored);
                    $checksumFiles[basename($checksum)] = $checksum;
                }

                $manifestPath = $snapshotDir . DIRECTORY_SEPARATOR . 'manifest.json';
                $manifest = [
                    'application' => 'hawpiwcloud',
                    'snapshot_id' => $snapshotId,
                    'backup_type' => $type,
                    'base_snapshot_id' => $type === 'incremental' ? ($previousInventory['full_snapshot_id'] ?? null) : $snapshotId,
                    'reason' => $reason,
                    'created_at' => $startedAt,
                    'retention_tier' => $this->retentionTier(),
                    'database_driver' => $this->config->databaseDriver,
                    'compression' => ['format' => $format, 'level' => $level, 'mode' => $webFriendly ? 'web-friendly' : 'standard'],
                    'encrypted' => $this->crypto->enabled(),
                    'components' => [
                        'source' => basename($this->cryptoPath($sourceArchive)),
                        'uploads' => basename($this->cryptoPath($uploadsArchive)),
                        'trash' => basename($this->cryptoPath($trashArchive)),
                        'database' => basename($this->cryptoPath($database)),
                        'application_state' => basename($this->cryptoPath($statePath)),
                    ],
                    'changes' => $changes,
                    'artifacts' => $this->artifactMetadata($storedArtifacts),
                ];
                $this->writeJson($manifestPath, $manifest);
                $packageFiles = array_merge($storedArtifacts, $checksumFiles, ['manifest.json' => $manifestPath]);
                BackupArchive::createFromFiles($packageFiles, $plainArchivePath, $level);
                $archivePath = $this->crypto->encrypt($plainArchivePath);
                $checksumPath = $this->writeChecksum($archivePath);
                $relativeArchive = $relativeDirectory . DIRECTORY_SEPARATOR . basename($archivePath);
                $relativeChecksum = $relativeDirectory . DIRECTORY_SEPARATOR . basename($checksumPath);
                $offsite = array_merge(
                    $this->offsite->replicate($archivePath, $relativeArchive),
                    $this->offsite->replicate($checksumPath, $relativeChecksum)
                );

                $point = [
                    'snapshot_id' => $snapshotId,
                    'type' => $type,
                    'base_snapshot_id' => $manifest['base_snapshot_id'],
                    'retention_tier' => $manifest['retention_tier'],
                    'reason' => $reason,
                    'archive' => $archivePath,
                    'relative_archive' => $relativeArchive,
                    'checksum' => $checksumPath,
                    'bytes' => filesize($archivePath),
                    'encrypted' => $this->crypto->enabled(),
                    'compression' => $format,
                    'offsite' => $offsite,
                    'created_at' => $startedAt,
                    'status' => 'success',
                ];
                $this->appendCatalog($point);
                $this->writeJson($this->config->inventoryPath, [
                    'snapshot_id' => $snapshotId,
                    'full_snapshot_id' => $type === 'full' ? $snapshotId : ($previousInventory['full_snapshot_id'] ?? $snapshotId),
                    'files' => $inventory,
                ]);
                $this->logger->info('backup.completed', $point);
                $this->logger->audit('backup.create', 'success', $actor, $ipAddress, $point);
                $this->logger->updateStatus('backup', 'success', $point);
                $this->notifyCapacity();
                $this->notifier->send('hawpiwcloud backup berhasil', $snapshotId . ' (' . $type . ') selesai.');

                return $point;
            } catch (Throwable $exception) {
                $context = ['reason' => $reason, 'snapshot' => $snapshotId, 'type' => $type, 'error' => $exception->getMessage()];
                $this->logger->error('backup.failed', $context);
                $this->logger->audit('backup.create', 'failed', $actor, $ipAddress, $context);
                $this->logger->updateStatus('backup', 'failed', $context);
                $this->notifier->send('hawpiwcloud backup gagal', $snapshotId . ': ' . $exception->getMessage());
                throw $exception;
            }
        });
    }

    public function verify(string $archivePath, string $actor = 'system', string $ipAddress = 'cli'): array
    {
        $temporaryRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hawpiwcloud-verify-' . bin2hex(random_bytes(6));
        try {
            $extractRoot = $this->materializePackage($archivePath, $temporaryRoot);
            $manifest = $this->verifyExtractedPackage($extractRoot);
            $result = ['archive' => $archivePath, 'snapshot_id' => $manifest['snapshot_id'] ?? null, 'status' => 'success'];
            $this->logger->audit('backup.verify', 'success', $actor, $ipAddress, $result);

            return $result;
        } catch (Throwable $exception) {
            $this->logger->audit('backup.verify', 'failed', $actor, $ipAddress, ['archive' => $archivePath, 'error' => $exception->getMessage()]);
            throw $exception;
        } finally {
            $this->removeDirectory($temporaryRoot);
        }
    }

    public function restore(string $archivePath, bool $restoreDatabase = true, bool $createSafetyBackup = true): array
    {
        return $this->restoreArchive($archivePath, ['uploads', 'trash', 'database'], $createSafetyBackup, 'system', 'cli', $restoreDatabase);
    }

    public function restorePoint(string $snapshotId, array $components, bool $createSafetyBackup = true, string $actor = 'system', string $ipAddress = 'cli'): array
    {
        $points = $this->chainForSnapshot($snapshotId);
        if ($points === []) {
            throw new RuntimeException('Restore point tidak ditemukan.');
        }

        $safety = $createSafetyBackup ? $this->create('pre-restore', ['actor' => $actor, 'ip_address' => $ipAddress]) : null;

        return $this->withLock(function () use ($points, $components, $safety, $actor, $ipAddress): array {
            $temporaryRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hawpiwcloud-restore-' . bin2hex(random_bytes(6));
            $stages = [
                'source' => $temporaryRoot . DIRECTORY_SEPARATOR . 'source',
                'uploads' => $temporaryRoot . DIRECTORY_SEPARATOR . 'uploads',
                'trash' => $temporaryRoot . DIRECTORY_SEPARATOR . 'trash',
            ];
            foreach ($stages as $stage) {
                $this->makeDirectory($stage);
            }

            try {
                $latestExtract = '';
                foreach ($points as $index => $point) {
                    $extractRoot = $this->materializePackage((string) $point['archive'], $temporaryRoot . DIRECTORY_SEPARATOR . 'package-' . $index);
                    $manifest = $this->verifyExtractedPackage($extractRoot);
                    $latestExtract = $extractRoot;
                    foreach (['source', 'uploads', 'trash'] as $component) {
                        if (!in_array($component, $components, true)) {
                            continue;
                        }
                        if (($manifest['backup_type'] ?? 'full') === 'full') {
                            $this->emptyDirectory($stages[$component]);
                        }
                        $artifact = $this->decryptExtractedArtifact($extractRoot, (string) $manifest['components'][$component], $temporaryRoot);
                        BackupArchive::extract($artifact, $stages[$component]);
                        $this->removeRelativePaths($stages[$component], $manifest['changes'][$component]['deleted'] ?? []);
                    }
                }

                if (in_array('uploads', $components, true)) {
                    $this->replaceDirectory($this->config->appRoot . DIRECTORY_SEPARATOR . 'uploads', $stages['uploads'], $temporaryRoot);
                }
                if (in_array('trash', $components, true)) {
                    $this->replaceDirectory($this->config->trashDir, $stages['trash'], $temporaryRoot);
                }
                if (in_array('source', $components, true)) {
                    $this->syncSourceDirectory($stages['source']);
                }

                $latestManifest = $this->readJson($latestExtract . DIRECTORY_SEPARATOR . 'manifest.json');
                $state = $this->decryptExtractedArtifact($latestExtract, (string) $latestManifest['components']['application_state'], $temporaryRoot);
                $this->restoreApplicationState($state, $components);
                if (in_array('database', $components, true)) {
                    $database = $this->decryptExtractedArtifact($latestExtract, (string) $latestManifest['components']['database'], $temporaryRoot);
                    $this->databaseDumper->restore($database);
                }

                $result = [
                    'snapshot_id' => $points[count($points) - 1]['snapshot_id'],
                    'components' => $components,
                    'safety_backup' => $safety['archive'] ?? null,
                    'restored_at' => date(DATE_ATOM),
                    'status' => 'success',
                ];
                $this->logger->info('restore.completed', $result);
                $this->logger->audit('backup.restore', 'success', $actor, $ipAddress, $result);
                $this->logger->updateStatus('restore', 'success', $result);

                return $result;
            } catch (Throwable $exception) {
                $context = ['error' => $exception->getMessage(), 'components' => $components];
                $this->logger->error('restore.failed', $context);
                $this->logger->audit('backup.restore', 'failed', $actor, $ipAddress, $context);
                $this->logger->updateStatus('restore', 'failed', $context);
                throw $exception;
            } finally {
                $this->removeDirectory($temporaryRoot);
            }
        });
    }

    public function applyRetention(string $actor = 'system', string $ipAddress = 'cli'): array
    {
        $catalog = $this->readCatalog();
        $now = time();
        $latestFull = null;
        foreach ($catalog as $point) {
            if (($point['status'] ?? '') === 'success' && ($point['type'] ?? '') === 'full') {
                $latestFull = $point['snapshot_id'];
            }
        }

        $deleted = [];
        foreach ($catalog as &$point) {
            if (($point['status'] ?? '') !== 'success' || isset($point['deleted_at'])) {
                continue;
            }
            $tier = (string) ($point['retention_tier'] ?? 'daily');
            $days = (int) ($this->config->retention[$tier . '_days'] ?? $this->config->retention['daily_days']);
            $created = strtotime((string) ($point['created_at'] ?? '')) ?: $now;
            if ($created + $days * 86400 > $now || $point['snapshot_id'] === $latestFull || $this->hasActiveDependents((string) $point['snapshot_id'], $catalog)) {
                continue;
            }

            $this->removeDirectory(dirname((string) $point['archive']));
            $this->offsite->remove((string) ($point['relative_archive'] ?? ''));
            $this->offsite->remove((string) ($point['relative_archive'] ?? '') . '.sha256');
            $point['deleted_at'] = date(DATE_ATOM);
            $deleted[] = $point['snapshot_id'];
        }
        unset($point);
        $this->writeJson($this->config->catalogPath, $catalog);
        $result = ['deleted' => $deleted, 'count' => count($deleted)];
        $this->logger->audit('backup.retention', 'success', $actor, $ipAddress, $result);

        return $result;
    }

    public function listRestorePoints(): array
    {
        $points = array_values(array_filter($this->readCatalog(), static function (array $point): bool {
            return ($point['status'] ?? '') === 'success' && !isset($point['deleted_at']);
        }));
        usort($points, static function (array $left, array $right): int {
            return strcmp((string) $right['created_at'], (string) $left['created_at']);
        });

        return $points;
    }

    public function statistics(): array
    {
        $points = $this->listRestorePoints();

        return [
            'total' => count($points),
            'bytes' => array_sum(array_map(static function (array $point): int {
                return (int) ($point['bytes'] ?? 0);
            }, $points)),
            'latest' => $points[0] ?? null,
        ];
    }

    private function restoreArchive(string $archivePath, array $components, bool $safetyBackup, string $actor, string $ipAddress, bool $restoreDatabase): array
    {
        $point = null;
        foreach ($this->readCatalog() as $catalogPoint) {
            if (($catalogPoint['archive'] ?? '') === $archivePath) {
                $point = $catalogPoint;
            }
        }
        if (is_array($point)) {
            return $this->restorePoint((string) $point['snapshot_id'], $restoreDatabase ? $components : array_diff($components, ['database']), $safetyBackup, $actor, $ipAddress);
        }

        throw new RuntimeException('Restore archive lama tidak lagi didukung langsung. Pilih restore point dari catalog.');
    }

    private function materializePackage(string $archivePath, string $temporaryRoot): string
    {
        if (!is_file($archivePath)) {
            throw new RuntimeException('Arsip restore tidak ditemukan: ' . $archivePath);
        }
        $this->verifyChecksumSidecar($archivePath);
        $this->makeDirectory($temporaryRoot);
        $plain = $this->crypto->decrypt($archivePath, $temporaryRoot . DIRECTORY_SEPARATOR . basename(substr($archivePath, 0, -4)));
        $extractRoot = $temporaryRoot . DIRECTORY_SEPARATOR . 'snapshot';
        $this->makeDirectory($extractRoot);
        BackupArchive::extract($plain, $extractRoot);

        return $extractRoot;
    }

    private function verifyExtractedPackage(string $extractRoot): array
    {
        $manifest = $this->readJson($extractRoot . DIRECTORY_SEPARATOR . 'manifest.json');
        if ($manifest === []) {
            throw new RuntimeException('Manifest backup tidak ditemukan.');
        }
        foreach (($manifest['artifacts'] ?? []) as $name => $metadata) {
            $path = $extractRoot . DIRECTORY_SEPARATOR . basename((string) $name);
            if (!is_file($path) || !isset($metadata['sha256']) || hash_file('sha256', $path) !== $metadata['sha256']) {
                throw new RuntimeException('Checksum artifact tidak cocok: ' . $name);
            }
            $this->verifyChecksumSidecar($path);
        }

        return $manifest;
    }

    private function decryptExtractedArtifact(string $extractRoot, string $name, string $temporaryRoot): string
    {
        $path = $extractRoot . DIRECTORY_SEPARATOR . basename($name);
        if (substr($path, -4) !== '.enc') {
            return $path;
        }

        $destination = $temporaryRoot . DIRECTORY_SEPARATOR . 'decrypted-' . bin2hex(random_bytes(4)) . '-' . basename(substr($path, 0, -4));

        return $this->crypto->decrypt($path, $destination);
    }

    private function readApplicationState(): array
    {
        return [
            'uploads_metadata' => $this->readJson($this->config->appRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . '.metadata.json'),
            'trash_metadata' => $this->readJson($this->config->trashDir . DIRECTORY_SEPARATOR . '.metadata.json'),
            'users' => $this->readJson($this->config->appRoot . DIRECTORY_SEPARATOR . 'users.json'),
        ];
    }

    private function restoreApplicationState(string $statePath, array $components): void
    {
        $state = $this->readJson($statePath);
        if (in_array('uploads', $components, true)) {
            $this->writeJson($this->config->appRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . '.metadata.json', $state['uploads_metadata'] ?? []);
        }
        if (in_array('trash', $components, true)) {
            $this->writeJson($this->config->trashDir . DIRECTORY_SEPARATOR . '.metadata.json', $state['trash_metadata'] ?? []);
        }
        if (in_array('database', $components, true)) {
            $this->writeJson($this->config->appRoot . DIRECTORY_SEPARATOR . 'users.json', $state['users'] ?? []);
        }
    }

    private function buildInventory(): array
    {
        return [
            'source' => $this->inventoryForDirectory($this->config->appRoot, function (string $relative, string $path): bool {
                return $this->includeSource($relative, $path, 'full', []);
            }),
            'uploads' => $this->inventoryForDirectory($this->config->appRoot . DIRECTORY_SEPARATOR . 'uploads'),
            'trash' => $this->inventoryForDirectory($this->config->trashDir),
        ];
    }

    private function inventoryForDirectory(string $root, ?callable $include = null): array
    {
        if (!is_dir($root)) {
            return [];
        }
        $inventory = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }
            $path = $item->getPathname();
            $relative = ltrim(substr($path, strlen($root)), DIRECTORY_SEPARATOR);
            if ($include !== null && !$include($relative, $path)) {
                continue;
            }
            $inventory[$relative] = ['bytes' => $item->getSize(), 'mtime' => $item->getMTime(), 'sha256' => hash_file('sha256', $path)];
        }
        ksort($inventory);

        return $inventory;
    }

    private function calculateChanges(array $current, array $previous, string $type): array
    {
        $result = [];
        foreach (['source', 'uploads', 'trash'] as $section) {
            $old = isset($previous[$section]) && is_array($previous[$section]) ? $previous[$section] : [];
            $changed = $type === 'full' ? $current[$section] : array_filter(
                $current[$section],
                static function (array $metadata, string $path) use ($old): bool {
                    return !isset($old[$path]['sha256']) || $old[$path]['sha256'] !== $metadata['sha256'];
                },
                ARRAY_FILTER_USE_BOTH
            );
            $result[$section] = ['changed' => $changed, 'deleted' => $type === 'full' ? [] : array_values(array_diff(array_keys($old), array_keys($current[$section])))];
        }

        return $result;
    }

    private function includeSource(string $relative, string $path, string $type, array $changed): bool
    {
        $topLevel = explode(DIRECTORY_SEPARATOR, $relative)[0];
        $resolvedPath = realpath($path) ?: $path;
        $backupRoot = realpath($this->config->backupRoot) ?: $this->config->backupRoot;
        $trashRoot = realpath($this->config->trashDir) ?: $this->config->trashDir;
        $versionsRoot = realpath($this->config->versionsDir) ?: $this->config->versionsDir;

        return !in_array($topLevel, ['.git', '.DS_Store', 'uploads', 'trash', 'var'], true)
            && !$this->pathIsInside($resolvedPath, $backupRoot)
            && !$this->pathIsInside($resolvedPath, $trashRoot)
            && !$this->pathIsInside($resolvedPath, $versionsRoot)
            && ($type === 'full' || is_dir($path) || isset($changed[$relative]));
    }

    private function chainForSnapshot(string $snapshotId): array
    {
        $catalog = array_values(array_filter($this->readCatalog(), static function (array $point): bool {
            return ($point['status'] ?? '') === 'success' && !isset($point['deleted_at']);
        }));
        $target = null;
        foreach ($catalog as $point) {
            if (($point['snapshot_id'] ?? '') === $snapshotId) {
                $target = $point;
            }
        }
        if (!is_array($target)) {
            return [];
        }

        $base = (string) (($target['type'] ?? 'full') === 'full' ? $snapshotId : ($target['base_snapshot_id'] ?? ''));
        $chain = array_values(array_filter($catalog, static function (array $point) use ($base, $target): bool {
            return ($point['snapshot_id'] ?? '') === $base
                || (($point['base_snapshot_id'] ?? '') === $base && strcmp((string) $point['created_at'], (string) $target['created_at']) <= 0);
        }));
        usort($chain, static function (array $left, array $right): int {
            return strcmp((string) $left['created_at'], (string) $right['created_at']);
        });

        return $chain;
    }

    private function retentionTier(): string
    {
        if ((int) date('j') === 1) {
            return 'monthly';
        }

        return (int) date('N') === 7 ? 'weekly' : 'daily';
    }

    private function hasActiveDependents(string $snapshotId, array $catalog): bool
    {
        foreach ($catalog as $point) {
            if (!isset($point['deleted_at']) && ($point['base_snapshot_id'] ?? '') === $snapshotId && ($point['snapshot_id'] ?? '') !== $snapshotId) {
                return true;
            }
        }

        return false;
    }

    private function notifyCapacity(): void
    {
        $total = disk_total_space($this->config->backupRoot);
        $free = disk_free_space($this->config->backupRoot);
        if ($total === false || $free === false || $total <= 0) {
            return;
        }
        $usedPercent = (int) round((1 - ($free / $total)) * 100);
        if ($usedPercent >= $this->config->capacityWarningPercent) {
            $this->notifier->send('hawpiwcloud kapasitas backup hampir penuh', 'Pemakaian storage backup mencapai ' . $usedPercent . '%.');
        }
    }

    private function artifactMetadata(array $artifacts): array
    {
        $metadata = [];
        foreach ($artifacts as $name => $path) {
            $bytes = filesize($path);
            $sha256 = hash_file('sha256', $path);
            if ($bytes === false || !is_string($sha256)) {
                throw new RuntimeException('Artifact backup tidak terbentuk: ' . $name);
            }
            $metadata[$name] = ['bytes' => $bytes, 'sha256' => $sha256];
        }

        return $metadata;
    }

    private function writeChecksum(string $path): string
    {
        $checksum = hash_file('sha256', $path);
        if (!is_string($checksum)) {
            throw new RuntimeException('Tidak dapat membuat checksum: ' . $path);
        }
        $sidecar = $path . '.sha256';
        if (file_put_contents($sidecar, $checksum . '  ' . basename($path) . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Tidak dapat menulis checksum: ' . $sidecar);
        }

        return $sidecar;
    }

    private function verifyChecksumSidecar(string $path): void
    {
        $sidecar = $path . '.sha256';
        if (!is_file($sidecar)) {
            throw new RuntimeException('Checksum sidecar tidak ditemukan: ' . basename($sidecar));
        }
        $expected = strtok(trim((string) file_get_contents($sidecar)), " \t");
        if (!is_string($expected) || !hash_equals($expected, (string) hash_file('sha256', $path))) {
            throw new RuntimeException('Checksum gagal diverifikasi: ' . basename($path));
        }
    }

    private function cryptoPath(string $path): string
    {
        return $this->crypto->enabled() ? $path . '.enc' : $path;
    }

    private function normalizeFormat(string $format): string
    {
        switch (strtolower($format)) {
            case 'zip':
                return 'zip';
            case 'tar.gz':
            case 'tgz':
                return 'tar.gz';
            case '7z':
                return '7z';
            default:
                throw new RuntimeException('Format kompresi tidak didukung: ' . $format);
        }
    }

    private function appendCatalog(array $point): void
    {
        $catalog = $this->readCatalog();
        $catalog[] = $point;
        $this->writeJson($this->config->catalogPath, $catalog);
    }

    private function readCatalog(): array
    {
        $catalog = $this->readJson($this->config->catalogPath);

        if (!is_array($catalog)) {
            return [];
        }

        $expectedIndex = 0;
        foreach ($catalog as $key => $_value) {
            if ($key !== $expectedIndex) {
                return [];
            }
            $expectedIndex++;
        }

        return $catalog;
    }

    private function replaceDirectory(string $destination, string $stage, string $temporaryRoot): void
    {
        $previous = $temporaryRoot . DIRECTORY_SEPARATOR . 'previous-' . basename($destination);
        if (is_dir($destination) && !rename($destination, $previous)) {
            throw new RuntimeException('Tidak dapat menyiapkan direktori lama: ' . $destination);
        }
        if (!rename($stage, $destination)) {
            if (is_dir($previous)) {
                rename($previous, $destination);
            }
            throw new RuntimeException('Tidak dapat memasang direktori hasil restore: ' . $destination);
        }
        $this->removeDirectory($previous);
    }

    private function copyDirectory(string $source, string $destination): void
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $item) {
            $relative = ltrim(substr($item->getPathname(), strlen($source)), DIRECTORY_SEPARATOR);
            $target = $destination . DIRECTORY_SEPARATOR . $relative;
            if ($item->isDir()) {
                $this->makeDirectory($target);
            } elseif (!copy($item->getPathname(), $target)) {
                throw new RuntimeException('Tidak dapat restore source file: ' . $relative);
            }
        }
    }

    private function syncSourceDirectory(string $stage): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->config->appRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $path = $item->getPathname();
            $relative = ltrim(substr($path, strlen($this->config->appRoot)), DIRECTORY_SEPARATOR);
            if (!$this->includeSource($relative, $path, 'full', [])) {
                continue;
            }

            $staged = $stage . DIRECTORY_SEPARATOR . $relative;
            if ($item->isFile() && !is_file($staged)) {
                unlink($path);
            } elseif ($item->isDir() && !is_dir($staged)) {
                @rmdir($path);
            }
        }
        $this->copyDirectory($stage, $this->config->appRoot);
    }

    private function removeRelativePaths(string $root, array $paths): void
    {
        foreach ($paths as $relative) {
            $path = $root . DIRECTORY_SEPARATOR . ltrim((string) $relative, DIRECTORY_SEPARATOR);
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function emptyDirectory(string $path): void
    {
        $this->removeDirectory($path);
        $this->makeDirectory($path);
    }

    private function withLock(callable $callback)
    {
        $lock = fopen($this->config->backupRoot . DIRECTORY_SEPARATOR . '.backup.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('Proses backup lain sedang berjalan.');
        }
        try {
            return $callback();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function snapshotId(string $reason): string
    {
        $safeReason = preg_replace('/[^a-z0-9_-]+/i', '-', strtolower($reason)) ?: 'manual';

        return trim($safeReason, '-') . '_' . date('His') . '_' . bin2hex(random_bytes(3));
    }

    private function pathIsInside(string $path, string $root): bool
    {
        $prefix = $root . DIRECTORY_SEPARATOR;

        return $path === $root || substr($path, 0, strlen($prefix)) === $prefix;
    }

    private function readJson(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }
        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function writeJson(string $path, array $value): void
    {
        $this->makeDirectory(dirname($path));
        $encoded = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded) || file_put_contents($path, $encoded . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Tidak dapat menulis JSON: ' . $path);
        }
    }

    private function makeDirectory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0700, true) && !is_dir($path)) {
            throw new RuntimeException('Tidak dapat membuat direktori: ' . $path);
        }
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($path);
    }
}
