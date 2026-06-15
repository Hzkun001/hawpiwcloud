<?php

declare(strict_types=1);

final class BackupOffsiteReplicator
{
    private $config;

    public function __construct(BackupConfig $config)
    {
        $this->config = $config;
    }

    public function replicate(string $archive, string $relativePath): array
    {
        $targets = $this->targets();
        if ($targets === []) {
            if ($this->config->requireOffsite) {
                throw new RuntimeException('Backup offsite diwajibkan tetapi target belum dikonfigurasi.');
            }

            return [];
        }

        $results = [];
        $successful = 0;
        $requiredFailure = false;
        foreach ($targets as $target) {
            try {
                $location = $this->copy($archive, $relativePath, $target);
                $results[] = ['name' => $target['name'], 'status' => 'success', 'location' => $location];
                $successful++;
            } catch (Throwable $exception) {
                $results[] = ['name' => $target['name'], 'status' => 'failed', 'error' => $exception->getMessage()];
                $requiredFailure = $requiredFailure || (bool) ($target['required'] ?? false);
            }
        }

        if ($requiredFailure || ($this->config->requireOffsite && $successful === 0)) {
            throw new RuntimeException('Replikasi offsite gagal: ' . json_encode($results, JSON_UNESCAPED_SLASHES));
        }

        return $results;
    }

    public function remove(string $relativePath): array
    {
        $results = [];
        foreach ($this->targets() as $target) {
            try {
                $this->removeFromTarget($relativePath, $target);
                $results[] = ['name' => $target['name'], 'status' => 'success'];
            } catch (Throwable $exception) {
                $results[] = ['name' => $target['name'], 'status' => 'failed', 'error' => $exception->getMessage()];
            }
        }

        return $results;
    }

    private function targets(): array
    {
        if ($this->config->offsiteTargets !== []) {
            return array_map(static function (array $target): array {
                return [
                    'name' => (string) ($target['name'] ?? $target['driver'] ?? 'offsite'),
                    'driver' => strtolower((string) ($target['driver'] ?? 'none')),
                    'target' => (string) ($target['target'] ?? ''),
                    'required' => (bool) ($target['required'] ?? false),
                ];
            }, $this->config->offsiteTargets);
        }

        if ($this->config->offsiteDriver === 'none') {
            return [];
        }

        return [[
            'name' => 'offsite',
            'driver' => $this->config->offsiteDriver,
            'target' => $this->config->offsiteTarget,
            'required' => $this->config->requireOffsite,
        ]];
    }

    private function copy(string $archive, string $relativePath, array $target): string
    {
        if ($target['target'] === '') {
            throw new RuntimeException('Target offsite kosong.');
        }

        if (($target['driver'] === 'rsync' || $target['driver'] === 'rclone') && !BackupProcess::isAvailable()) {
            throw new RuntimeException('Hosting membatasi eksekusi command eksternal. Gunakan target offsite local untuk mode web-friendly.');
        }

        if ($target['driver'] === 'local') {
            return $this->copyLocal($archive, $relativePath, $target['target']);
        }
        if ($target['driver'] === 'rsync') {
            return $this->copyRsync($archive, $relativePath, $target['target']);
        }
        if ($target['driver'] === 'rclone') {
            return $this->copyRclone($archive, $relativePath, $target['target']);
        }

        throw new RuntimeException('Driver offsite tidak didukung: ' . $target['driver']);
    }

    private function copyLocal(string $archive, string $relativePath, string $root): string
    {
        $destination = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $relativePath;
        $directory = dirname($destination);
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Tidak dapat membuat direktori offsite lokal.');
        }
        if (!copy($archive, $destination)) {
            throw new RuntimeException('Tidak dapat menyalin backup ke lokasi offsite lokal.');
        }

        return $destination;
    }

    private function copyRsync(string $archive, string $relativePath, string $root): string
    {
        $target = rtrim($root, '/') . '/' . str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
        BackupProcess::run(['rsync', '-a', $archive, $target]);

        return $target;
    }

    private function copyRclone(string $archive, string $relativePath, string $root): string
    {
        $target = rtrim($root, '/') . '/' . str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
        BackupProcess::run(['rclone', 'copyto', $archive, $target]);

        return $target;
    }

    private function removeFromTarget(string $relativePath, array $target): void
    {
        if ($target['driver'] === 'local') {
            @unlink(rtrim($target['target'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $relativePath);
            return;
        }
        if (($target['driver'] === 'rsync' || $target['driver'] === 'rclone') && !BackupProcess::isAvailable()) {
            return;
        }
        if ($target['driver'] === 'rclone') {
            BackupProcess::run(['rclone', 'deletefile', rtrim($target['target'], '/') . '/' . str_replace(DIRECTORY_SEPARATOR, '/', $relativePath)]);
        }
    }
}
