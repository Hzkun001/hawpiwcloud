<?php

declare(strict_types=1);

final class BackupLogger
{
    private $config;

    public function __construct(BackupConfig $config)
    {
        $this->config = $config;
    }

    public function info(string $event, array $context = []): void
    {
        $this->write('info', $event, $context);
    }

    public function error(string $event, array $context = []): void
    {
        $this->write('error', $event, $context);
    }

    public function updateStatus(string $operation, string $status, array $context = []): void
    {
        $current = $this->readStatus();
        $entry = [
            'operation' => $operation,
            'status' => $status,
            'time' => date(DATE_ATOM),
            'context' => $context,
        ];

        $current['last_operation'] = $entry;
        if ($operation === 'backup') {
            $current['last_backup'] = $entry;
            if ($status === 'success') {
                $current['last_successful_backup'] = $entry;
            } elseif ($status === 'failed') {
                $current['last_failed_backup'] = $entry;
            }
        }

        $this->writeJson($this->config->statusPath, $current);
    }

    public function audit(string $operation, string $status, string $actor = 'system', string $ipAddress = 'cli', array $context = []): void
    {
        $this->appendJsonLine($this->config->auditPath, [
            'time' => date(DATE_ATOM),
            'user' => $actor,
            'ip_address' => $ipAddress,
            'operation' => $operation,
            'status' => $status,
            'context' => $context,
        ]);
    }

    public function readStatus(): array
    {
        if (!is_file($this->config->statusPath)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($this->config->statusPath), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function write(string $level, string $event, array $context): void
    {
        $line = json_encode([
            'time' => date(DATE_ATOM),
            'level' => $level,
            'event' => $event,
            'context' => $context,
        ], JSON_UNESCAPED_SLASHES);

        if (!is_string($line)) {
            throw new RuntimeException('Tidak dapat encode log backup.');
        }
        $this->appendLine($this->config->logPath, $line);
    }

    private function writeJson(string $path, array $value): void
    {
        $encoded = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded) || file_put_contents($path, $encoded . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Tidak dapat menulis status backup.');
        }
    }

    private function appendJsonLine(string $path, array $value): void
    {
        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
            throw new RuntimeException('Tidak dapat encode audit log.');
        }
        $this->appendLine($path, $encoded);
    }

    private function appendLine(string $path, string $line): void
    {
        if (file_put_contents($path, $line . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
            throw new RuntimeException('Tidak dapat menulis log backup.');
        }
    }
}
