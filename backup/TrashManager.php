<?php

declare(strict_types=1);

final class TrashManager
{
    private $config;
    private $logger;
    private $offsite;
    private $crypto;

    public function __construct(BackupConfig $config)
    {
        $this->config = $config;
        $this->config->ensureDirectories();
        $this->logger = new BackupLogger($this->config);
        $this->offsite = new BackupOffsiteReplicator($this->config);
        $this->crypto = new BackupCrypto($this->config);
    }

    public function moveFromUploads(string $fileName, array $metadata, string $deletedBy): array
    {
        $fileName = basename($fileName);
        $source = $this->config->appRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $fileName;
        if (!is_file($source)) {
            throw new RuntimeException('File upload tidak ditemukan.');
        }

        $storedName = $this->availableTrashName($fileName);
        $destination = $this->config->trashDir . DIRECTORY_SEPARATOR . $storedName;
        if (!rename($source, $destination)) {
            throw new RuntimeException('Tidak dapat memindahkan file ke Trash.');
        }

        try {
            $trashMetadata = $this->readMetadata();
            $trashMetadata[$storedName] = array_merge($metadata, [
                'originalName' => $fileName,
                'storedName' => $storedName,
                'deletedBy' => $deletedBy,
                'deletedAt' => date(DATE_ATOM),
            ]);
            $this->writeMetadata($trashMetadata);
        } catch (Throwable $exception) {
            rename($destination, $source);
            throw $exception;
        }
        $this->logger->info('trash.moved', $trashMetadata[$storedName]);
        $this->logger->audit('trash.move', 'success', $deletedBy, 'web', $trashMetadata[$storedName]);

        return $trashMetadata[$storedName];
    }

    public function purge(string $storedName): array
    {
        $storedName = basename($storedName);
        $path = $this->config->trashDir . DIRECTORY_SEPARATOR . $storedName;
        $metadata = $this->readMetadata();
        if (!is_file($path) || !isset($metadata[$storedName]) || !is_array($metadata[$storedName])) {
            throw new RuntimeException('File Trash tidak ditemukan: ' . $storedName);
        }

        $date = date('Y-m-d');
        $id = date('His') . '_' . bin2hex(random_bytes(3));
        $relativeDirectory = 'trash-archive' . DIRECTORY_SEPARATOR . $date . DIRECTORY_SEPARATOR . $id;
        $archiveDir = $this->config->backupRoot . DIRECTORY_SEPARATOR . $relativeDirectory;
        if (!is_dir($archiveDir) && !mkdir($archiveDir, 0700, true) && !is_dir($archiveDir)) {
            throw new RuntimeException('Tidak dapat membuat direktori arsip Trash.');
        }

        $metadataPath = $archiveDir . DIRECTORY_SEPARATOR . 'metadata.json';
        $purgedMetadata = array_merge($metadata[$storedName], ['purgedAt' => date(DATE_ATOM)]);
        $encoded = json_encode($purgedMetadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded) || file_put_contents($metadataPath, $encoded . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Tidak dapat menulis metadata arsip Trash.');
        }

        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $storedName) ?: 'file';
        $archiveName = 'trash_deleted_' . $safeName . '_' . date('Ymd_His') . '.zip';
        $archive = $archiveDir . DIRECTORY_SEPARATOR . $archiveName;
        BackupArchive::createFromFiles([
            'files/' . $storedName => $path,
            'metadata.json' => $metadataPath,
        ], $archive);
        @unlink($metadataPath);
        $archive = $this->crypto->encrypt($archive);
        $checksum = $this->writeChecksum($archive);
        $relativeArchive = $relativeDirectory . DIRECTORY_SEPARATOR . basename($archive);
        $offsite = array_merge(
            $this->offsite->replicate($archive, $relativeArchive),
            $this->offsite->replicate($checksum, $relativeArchive . '.sha256')
        );

        if (!unlink($path)) {
            throw new RuntimeException('Arsip Trash dibuat tetapi file asli tidak dapat dihapus.');
        }
        unset($metadata[$storedName]);
        $this->writeMetadata($metadata);

        $result = [
            'file' => $storedName,
            'archive' => $archive,
            'checksum' => $checksum,
            'offsite' => $offsite,
            'metadata' => $purgedMetadata,
        ];
        $this->logger->info('trash.purged', $result);
        $this->logger->audit('trash.purge', 'success', (string) ($purgedMetadata['deletedBy'] ?? 'system'), 'cli', $result);

        return $result;
    }

    public function purgeOlderThanDays(int $days): array
    {
        $threshold = time() - max(0, $days) * 86400;
        $results = [];
        foreach ($this->readMetadata() as $storedName => $metadata) {
            $deletedAt = isset($metadata['deletedAt']) ? strtotime((string) $metadata['deletedAt']) : false;
            if ($deletedAt !== false && $deletedAt <= $threshold) {
                $results[] = $this->purge((string) $storedName);
            }
        }

        return $results;
    }

    private function availableTrashName(string $fileName): string
    {
        if (!file_exists($this->config->trashDir . DIRECTORY_SEPARATOR . $fileName)) {
            return $fileName;
        }

        $info = pathinfo($fileName);
        $extension = isset($info['extension']) ? '.' . $info['extension'] : '';

        return ($info['filename'] ?? 'file') . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(2)) . $extension;
    }

    private function readMetadata(): array
    {
        $path = $this->config->trashDir . DIRECTORY_SEPARATOR . '.metadata.json';
        if (!is_file($path)) {
            return [];
        }
        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function writeMetadata(array $metadata): void
    {
        $encoded = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded) || file_put_contents($this->config->trashDir . DIRECTORY_SEPARATOR . '.metadata.json', $encoded . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Tidak dapat menulis metadata Trash.');
        }
    }

    private function writeChecksum(string $path): string
    {
        $checksum = hash_file('sha256', $path);
        $sidecar = $path . '.sha256';
        if (!is_string($checksum) || file_put_contents($sidecar, $checksum . '  ' . basename($path) . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Tidak dapat membuat checksum arsip Trash.');
        }

        return $sidecar;
    }
}
