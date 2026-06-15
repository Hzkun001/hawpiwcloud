<?php

declare(strict_types=1);

final class BackupArchive
{
    public static function extension(string $format): string
    {
        switch (strtolower($format)) {
            case 'zip':
                return '.zip';
            case 'tar.gz':
            case 'tgz':
                return '.tar.gz';
            case '7z':
                return '.7z';
            default:
                throw new RuntimeException('Format kompresi tidak didukung: ' . $format);
        }
    }

    public static function createFromDirectory(string $source, string $destination, ?callable $include = null, int $level = 6): void
    {
        $format = self::formatForPath($destination);
        if ($format !== 'zip' && !BackupProcess::isAvailable()) {
            throw new RuntimeException('Format kompresi non-ZIP membutuhkan eksekusi proses eksternal. Gunakan ZIP di hosting ini.');
        }
        if ($format === 'zip') {
            self::createZip($destination, static function (ZipArchive $zip) use ($source, $include, $level): void {
                self::addDirectoryToZip($zip, $source, $include, $level);
            });
            return;
        }

        $stage = self::temporaryDirectory();
        try {
            self::copySelectedDirectory($source, $stage, $include);
            self::createExternal($stage, $destination, $format, $level);
        } finally {
            self::removeDirectory($stage);
        }
    }

    public static function createFromFiles(array $files, string $destination, int $level = 6): void
    {
        $format = self::formatForPath($destination);
        if ($format !== 'zip' && !BackupProcess::isAvailable()) {
            throw new RuntimeException('Format kompresi non-ZIP membutuhkan eksekusi proses eksternal. Gunakan ZIP di hosting ini.');
        }
        if ($format === 'zip') {
            self::createZip($destination, static function (ZipArchive $zip) use ($files, $level): void {
                foreach ($files as $archivePath => $sourcePath) {
                    if (is_file($sourcePath)) {
                        $path = str_replace(DIRECTORY_SEPARATOR, '/', (string) $archivePath);
                        $zip->addFile($sourcePath, $path);
                        $zip->setCompressionName($path, ZipArchive::CM_DEFLATE, $level);
                    }
                }
            });
            return;
        }

        $stage = self::temporaryDirectory();
        try {
            foreach ($files as $archivePath => $sourcePath) {
                if (!is_file($sourcePath)) {
                    continue;
                }
                $destinationPath = $stage . DIRECTORY_SEPARATOR . ltrim((string) $archivePath, DIRECTORY_SEPARATOR);
                self::makeDirectory(dirname($destinationPath));
                if (!copy($sourcePath, $destinationPath)) {
                    throw new RuntimeException('Tidak dapat menyiapkan artifact kompresi.');
                }
            }
            self::createExternal($stage, $destination, $format, $level);
        } finally {
            self::removeDirectory($stage);
        }
    }

    public static function extract(string $archive, string $destination): void
    {
        self::makeDirectory($destination);
        $format = self::formatForPath($archive);
        if ($format === 'zip') {
            self::extractZip($archive, $destination);
            return;
        }

        if (!BackupProcess::isAvailable()) {
            throw new RuntimeException('Ekstraksi format non-ZIP tidak didukung di hosting ini. Gunakan ZIP untuk backup dan restore web.');
        }

        if ($format === 'tar.gz') {
            $names = preg_split('/\r?\n/', trim(BackupProcess::capture(['tar', '-tzf', $archive]))) ?: [];
            self::assertSafePaths($names);
            BackupProcess::run(['tar', '-xzf', $archive, '-C', $destination]);
            return;
        }

        $listing = BackupProcess::capture(['7z', 'l', '-slt', $archive]);
        preg_match_all('/^Path = (.+)$/m', $listing, $matches);
        self::assertSafePaths(array_slice($matches[1] ?? [], 1));
        BackupProcess::run(['7z', 'x', '-y', '-o' . $destination, $archive]);
    }

    private static function createExternal(string $source, string $destination, string $format, int $level): void
    {
        self::makeDirectory(dirname($destination));
        if ($format === 'tar.gz') {
            BackupProcess::run(['tar', '-czf', $destination, '-C', $source, '.'], null, ['GZIP' => '-' . max(1, min(9, $level))]);
        } else {
            BackupProcess::run(['7z', 'a', '-t7z', '-mx=' . max(0, min(9, $level)), $destination, '.'], null, [], null, $source);
        }

        if (!is_file($destination)) {
            throw new RuntimeException('Archive tidak terbentuk: ' . $destination);
        }
    }

    private static function createZip(string $destination, callable $writer): void
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('Ekstensi PHP zip wajib diaktifkan.');
        }

        self::makeDirectory(dirname($destination));
        $zip = new ZipArchive();
        if ($zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Tidak dapat membuat ZIP: ' . $destination);
        }

        $writer($zip);
        if (!$zip->close() || !is_file($destination)) {
            throw new RuntimeException('Tidak dapat menyimpan ZIP: ' . $destination);
        }
    }

    private static function addDirectoryToZip(ZipArchive $zip, string $source, ?callable $include, int $level): void
    {
        $added = false;
        if (is_dir($source)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $item) {
                $path = $item->getPathname();
                $relative = ltrim(substr($path, strlen($source)), DIRECTORY_SEPARATOR);
                if ($relative === '' || ($include !== null && !$include($relative, $path))) {
                    continue;
                }

                $archivePath = str_replace(DIRECTORY_SEPARATOR, '/', $relative);
                if ($item->isDir()) {
                    $zip->addEmptyDir($archivePath);
                } elseif ($item->isFile()) {
                    $zip->addFile($path, $archivePath);
                    $zip->setCompressionName($archivePath, ZipArchive::CM_DEFLATE, $level);
                }
                $added = true;
            }
        }

        if (!$added) {
            $zip->addEmptyDir('.');
        }
    }

    private static function extractZip(string $archive, string $destination): void
    {
        $zip = new ZipArchive();
        if ($zip->open($archive) !== true) {
            throw new RuntimeException('Tidak dapat membuka ZIP: ' . $archive);
        }

        $names = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $names[] = (string) $zip->getNameIndex($index);
        }
        self::assertSafePaths($names);

        if (!$zip->extractTo($destination)) {
            $zip->close();
            throw new RuntimeException('Tidak dapat mengekstrak ZIP: ' . $archive);
        }
        $zip->close();
    }

    private static function copySelectedDirectory(string $source, string $destination, ?callable $include): void
    {
        if (!is_dir($source)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            $path = $item->getPathname();
            $relative = ltrim(substr($path, strlen($source)), DIRECTORY_SEPARATOR);
            if ($relative === '' || ($include !== null && !$include($relative, $path))) {
                continue;
            }

            $target = $destination . DIRECTORY_SEPARATOR . $relative;
            if ($item->isDir()) {
                self::makeDirectory($target);
            } elseif ($item->isFile()) {
                self::makeDirectory(dirname($target));
                if (!copy($path, $target)) {
                    throw new RuntimeException('Tidak dapat menyiapkan file archive: ' . $relative);
                }
            }
        }
    }

    private static function assertSafePaths(array $paths): void
    {
        foreach ($paths as $path) {
            $normalized = str_replace('\\', '/', (string) $path);
            if (substr($normalized, 0, 1) === '/'
                || preg_match('/^[A-Za-z]:\//', $normalized) === 1
                || in_array('..', explode('/', $normalized), true)) {
                throw new RuntimeException('Archive memuat path yang tidak aman.');
            }
        }
    }

    private static function formatForPath(string $path): string
    {
        $plain = substr($path, -4) === '.enc' ? substr($path, 0, -4) : $path;
        if (substr($plain, -7) === '.tar.gz') {
            return 'tar.gz';
        }
        if (substr($plain, -3) === '.7z') {
            return '7z';
        }
        if (substr($plain, -4) === '.zip') {
            return 'zip';
        }

        throw new RuntimeException('Ekstensi archive tidak didukung: ' . $path);
    }

    private static function temporaryDirectory(): string
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hawpiwcloud-archive-' . bin2hex(random_bytes(6));
        self::makeDirectory($path);

        return $path;
    }

    private static function makeDirectory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0700, true) && !is_dir($path)) {
            throw new RuntimeException('Tidak dapat membuat direktori: ' . $path);
        }
    }

    private static function removeDirectory(string $path): void
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
