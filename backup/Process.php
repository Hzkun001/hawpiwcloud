<?php

declare(strict_types=1);

final class BackupProcess
{
    public static function isAvailable(): bool
    {
        if (!function_exists('proc_open')) {
            return false;
        }

        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        return !in_array('proc_open', $disabled, true);
    }

    public static function run(array $command, ?string $stdoutPath = null, array $environment = [], ?string $stdinPath = null, ?string $workingDirectory = null): void
    {
        if (!self::isAvailable()) {
            throw new RuntimeException('Hosting memblokir eksekusi proses eksternal. Gunakan mode ZIP-only atau jalankan backup via CLI di server yang mengizinkan proc_open.');
        }

        $stdout = $stdoutPath === null ? ['pipe', 'w'] : fopen($stdoutPath, 'wb');
        $stdin = $stdinPath === null ? ['pipe', 'r'] : fopen($stdinPath, 'rb');
        if ($stdout === false || $stdin === false) {
            throw new RuntimeException('Tidak dapat membuka file untuk proses eksternal.');
        }

        $pipes = [];
        $processEnvironment = null;
        if ($environment !== []) {
            $baseEnvironment = getenv();
            $processEnvironment = array_merge(is_array($baseEnvironment) ? $baseEnvironment : [], $environment);
        }

        $process = proc_open($command, [$stdin, $stdout, ['pipe', 'w']], $pipes, $workingDirectory, $processEnvironment);
        if (!is_resource($process)) {
            throw new RuntimeException('Tidak dapat menjalankan command: ' . implode(' ', $command));
        }

        if ($stdinPath === null) {
            fclose($pipes[0]);
        }

        if ($stdoutPath === null) {
            stream_get_contents($pipes[1]);
            fclose($pipes[1]);
        }

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($stdoutPath !== null) {
            fclose($stdout);
        }
        if ($stdinPath !== null) {
            fclose($stdin);
        }

        if ($exitCode !== 0) {
            throw new RuntimeException(sprintf(
                'Command gagal (%d): %s%s',
                $exitCode,
                implode(' ', $command),
                is_string($stderr) && $stderr !== '' ? ' - ' . trim($stderr) : ''
            ));
        }
    }

    public static function capture(array $command, array $environment = [], ?string $workingDirectory = null): string
    {
        $temporary = tempnam(sys_get_temp_dir(), 'hawpiwcloud-process-');
        if ($temporary === false) {
            throw new RuntimeException('Tidak dapat membuat file temporary proses.');
        }

        try {
            self::run($command, $temporary, $environment, null, $workingDirectory);

            return (string) file_get_contents($temporary);
        } finally {
            @unlink($temporary);
        }
    }
}
