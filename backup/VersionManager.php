<?php

declare(strict_types=1);

final class FileVersionManager
{
    private $config;

    public function __construct(BackupConfig $config)
    {
        $this->config = $config;
        $this->config->ensureDirectories();
    }

    public function capture(string $filePath, string $fileName, array $metadata = []): ?array
    {
        if (!is_file($filePath)) {
            return null;
        }

        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($fileName)) ?: 'file';
        $versionId = date('Ymd_His') . '_' . bin2hex(random_bytes(3));
        $directory = $this->config->versionsDir . DIRECTORY_SEPARATOR . $safeName;
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Tidak dapat membuat direktori versi file.');
        }

        $storedName = $versionId . '_' . $safeName;
        $destination = $directory . DIRECTORY_SEPARATOR . $storedName;
        if (!copy($filePath, $destination)) {
            throw new RuntimeException('Tidak dapat menyimpan versi file.');
        }

        $entry = [
            'version_id' => $versionId,
            'file_name' => $fileName,
            'stored_path' => $destination,
            'bytes' => filesize($destination),
            'sha256' => hash_file('sha256', $destination),
            'created_at' => date(DATE_ATOM),
            'metadata' => $metadata,
        ];
        $index = $this->readIndex();
        $index[$fileName][] = $entry;
        $this->writeIndex($index);

        return $entry;
    }

    public function list(): array
    {
        return $this->readIndex();
    }

    public function restore(string $fileName, string $versionId, string $uploadDir): array
    {
        foreach ($this->readIndex()[$fileName] ?? [] as $entry) {
            if (($entry['version_id'] ?? '') !== $versionId || !is_file((string) ($entry['stored_path'] ?? ''))) {
                continue;
            }

            $target = $uploadDir . basename($fileName);
            $this->capture($target, $fileName, ['reason' => 'before-version-restore']);
            if (!copy((string) $entry['stored_path'], $target)) {
                throw new RuntimeException('Tidak dapat memulihkan versi file.');
            }

            return $entry;
        }

        throw new RuntimeException('Versi file tidak ditemukan.');
    }

    private function readIndex(): array
    {
        $path = $this->config->versionsDir . DIRECTORY_SEPARATOR . 'index.json';
        if (!is_file($path)) {
            return [];
        }
        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function writeIndex(array $index): void
    {
        $encoded = json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded) || file_put_contents($this->config->versionsDir . DIRECTORY_SEPARATOR . 'index.json', $encoded . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Tidak dapat menulis index versi file.');
        }
    }
}
