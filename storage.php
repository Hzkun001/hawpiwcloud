<?php

declare(strict_types=1);

const HAWPIWCLOUD_UPLOAD_MAX_BYTES = 2 * 1024 * 1024;
const HAWPIWCLOUD_ALLOWED_EXTENSIONS = [
    'jpg', 'jpeg', 'png', 'gif', 'webp',
    'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
    'txt', 'csv', 'zip', 'rar',
];
const HAWPIWCLOUD_ALLOWED_FILE_LABEL = 'JPG, PNG, GIF, WEBP, PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, CSV, ZIP, RAR';

function storageUploadDir(): string
{
    return __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
}

function storageMetadataPath(string $uploadDir): string
{
    return $uploadDir . '.metadata.json';
}

function storageReadMetadata(string $uploadDir): array
{
    $metadataPath = storageMetadataPath($uploadDir);
    if (!is_file($metadataPath)) {
        return [];
    }

    $raw = file_get_contents($metadataPath);
    if (!is_string($raw) || $raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : [];
}

function storageWriteMetadata(string $uploadDir, array $metadata): bool
{
    if (!is_dir($uploadDir)) {
        return false;
    }

    $encoded = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded)) {
        return false;
    }

    return file_put_contents(storageMetadataPath($uploadDir), $encoded . PHP_EOL, LOCK_EX) !== false;
}

function storageFileMetadata(array $metadata, string $fileName): array
{
    $item = $metadata[$fileName] ?? [];
    if (!is_array($item)) {
        $item = [];
    }

    $legacyShared = array_key_exists('shared', $item) ? (bool) $item['shared'] : true;

    return [
        'owner' => isset($item['owner']) && is_string($item['owner']) && $item['owner'] !== '' ? $item['owner'] : 'admin',
        'viewerAccess' => array_key_exists('viewerAccess', $item) ? (bool) $item['viewerAccess'] : $legacyShared,
        'uploadedByRole' => isset($item['uploadedByRole']) && is_string($item['uploadedByRole']) ? $item['uploadedByRole'] : 'admin',
    ];
}

function storageRegisterFile(string $uploadDir, string $fileName, array $user, array $access = []): bool
{
    $metadata = storageReadMetadata($uploadDir);
    $isAdminUpload = ($user['role'] ?? '') === 'admin';
    $isUserUpload = ($user['role'] ?? '') === 'user';
    $canSetAccess = $isAdminUpload || $isUserUpload;
    
    $metadata[$fileName] = [
        'owner' => $user['username'] ?? 'admin',
        'uploadedByRole' => $user['role'] ?? 'admin',
        'viewerAccess' => $canSetAccess && (bool) ($access['viewerAccess'] ?? false),
        'uploadedAt' => date(DATE_ATOM),
    ];

    return storageWriteMetadata($uploadDir, $metadata);
}

function storageUpdateFileAccess(string $uploadDir, string $fileName, bool $viewerAccess): bool
{
    $metadata = storageReadMetadata($uploadDir);
    $current = storageFileMetadata($metadata, $fileName);
    $metadata[$fileName] = array_merge($metadata[$fileName] ?? [], [
        'owner' => $current['owner'],
        'uploadedByRole' => $current['uploadedByRole'],
        'viewerAccess' => $viewerAccess,
        'updatedAt' => date(DATE_ATOM),
    ]);

    return storageWriteMetadata($uploadDir, $metadata);
}

function storageRemoveFileMetadata(string $uploadDir, string $fileName): void
{
    $metadata = storageReadMetadata($uploadDir);
    unset($metadata[$fileName]);
    storageWriteMetadata($uploadDir, $metadata);
}

function storageBackupBootstrapPath(): string
{
    return __DIR__ . DIRECTORY_SEPARATOR . 'backup' . DIRECTORY_SEPARATOR . 'bootstrap.php';
}

function storageBackupModuleAvailable(): bool
{
    return is_file(storageBackupBootstrapPath());
}

function storageDefaultTrashDir(): string
{
    $envTrashDir = getenv('TRASH_DIR');
    if (is_string($envTrashDir) && trim($envTrashDir) !== '') {
        return rtrim($envTrashDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    return __DIR__ . DIRECTORY_SEPARATOR . 'trash' . DIRECTORY_SEPARATOR;
}

function storageAvailableTrashName(string $trashDir, string $fileName): string
{
    $fileName = basename($fileName);
    if (!file_exists($trashDir . $fileName)) {
        return $fileName;
    }

    $info = pathinfo($fileName);
    $extension = isset($info['extension']) && is_string($info['extension']) && $info['extension'] !== '' ? '.' . $info['extension'] : '';
    $base = isset($info['filename']) && is_string($info['filename']) && $info['filename'] !== '' ? $info['filename'] : 'file';

    do {
        $candidate = $base . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(2)) . $extension;
    } while (file_exists($trashDir . $candidate));

    return $candidate;
}

function storageMoveFileToTrash(string $uploadDir, string $fileName, array $deletedBy): bool
{
    $allMetadata = storageReadMetadata($uploadDir);
    $metadata = array_merge(
        storageFileMetadata($allMetadata, $fileName),
        isset($allMetadata[$fileName]) && is_array($allMetadata[$fileName]) ? $allMetadata[$fileName] : []
    );

    if (storageBackupModuleAvailable()) {
        try {
            require_once storageBackupBootstrapPath();
            $config = BackupConfig::fromEnvironment(__DIR__);
            (new FileVersionManager($config))->capture($uploadDir . $fileName, $fileName, ['reason' => 'before-trash', 'owner' => $metadata['owner'] ?? 'admin']);
            $trash = new TrashManager($config);
            $trash->moveFromUploads($fileName, $metadata, (string) ($deletedBy['username'] ?? 'unknown'));
            storageRemoveFileMetadata($uploadDir, $fileName);

            return true;
        } catch (Throwable $exception) {
            error_log('hawpiwcloud trash backup-module error: ' . $exception->getMessage());
        }
    }

    try {
        $source = $uploadDir . $fileName;
        if (!is_file($source)) {
            throw new RuntimeException('File sumber tidak ditemukan.');
        }

        $trashDir = storageTrashDir();
        if (!is_dir($trashDir) && !mkdir($trashDir, 0755, true) && !is_dir($trashDir)) {
            throw new RuntimeException('Direktori Trash tidak dapat dibuat.');
        }

        $storedName = storageAvailableTrashName($trashDir, $fileName);
        $destination = $trashDir . $storedName;
        if (!rename($source, $destination)) {
            throw new RuntimeException('File gagal dipindahkan ke Trash.');
        }

        $trashMetadata = storageReadTrashMetadata();
        $trashMetadata[$storedName] = array_merge($metadata, [
            'originalName' => $fileName,
            'storedName' => $storedName,
            'deletedBy' => (string) ($deletedBy['username'] ?? 'unknown'),
            'deletedAt' => date(DATE_ATOM),
        ]);

        if (!storageWriteTrashMetadata($trashMetadata)) {
            rename($destination, $source);
            throw new RuntimeException('Metadata Trash gagal ditulis.');
        }

        storageRemoveFileMetadata($uploadDir, $fileName);

        return true;
    } catch (Throwable $exception) {
        error_log('hawpiwcloud trash error: ' . $exception->getMessage());

        return false;
    }
}

function storageTrashDir(): string
{
    $backupBootstrap = storageBackupBootstrapPath();
    if (is_file($backupBootstrap)) {
        require_once $backupBootstrap;

        return BackupConfig::fromEnvironment(__DIR__)->trashDir . DIRECTORY_SEPARATOR;
    }

    return storageDefaultTrashDir();
}

function storageTrashMetadataPath(): string
{
    return storageTrashDir() . '.metadata.json';
}

function storageReadTrashMetadata(): array
{
    $metadataPath = storageTrashMetadataPath();
    if (!is_file($metadataPath)) {
        return [];
    }

    $decoded = json_decode((string) file_get_contents($metadataPath), true);

    return is_array($decoded) ? $decoded : [];
}

function storageWriteTrashMetadata(array $metadata): bool
{
    $trashDir = storageTrashDir();
    if (!is_dir($trashDir) && !mkdir($trashDir, 0755, true) && !is_dir($trashDir)) {
        return false;
    }

    $encoded = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded)) {
        return false;
    }

    return file_put_contents($trashDir . '.metadata.json', $encoded . PHP_EOL, LOCK_EX) !== false;
}

function storageCanManageTrashFile(array $user, array $trashFile): bool
{
    if (($user['role'] ?? '') === 'admin') {
        return true;
    }

    return ($user['role'] ?? '') === 'user' && ($trashFile['owner'] ?? '') === ($user['username'] ?? '');
}

function storageListTrashFiles(?array $user = null): array
{
    $trashDir = storageTrashDir();
    $metadata = storageReadTrashMetadata();
    $files = [];

    foreach ($metadata as $storedName => $item) {
        if (!is_string($storedName) || !is_array($item)) {
            continue;
        }

        $path = $trashDir . basename($storedName);
        if (!is_file($path)) {
            continue;
        }

        $trashFile = array_merge($item, [
            'storedName' => basename($storedName),
            'originalName' => isset($item['originalName']) && is_string($item['originalName']) ? $item['originalName'] : basename($storedName),
            'owner' => isset($item['owner']) && is_string($item['owner']) ? $item['owner'] : 'admin',
            'deletedBy' => isset($item['deletedBy']) && is_string($item['deletedBy']) ? $item['deletedBy'] : 'unknown',
            'deletedAt' => isset($item['deletedAt']) && is_string($item['deletedAt']) ? $item['deletedAt'] : '',
            'size' => filesize($path),
            'modified' => filemtime($path),
            'isImage' => storageIsPreviewableImage($path),
        ]);

        if ($user !== null && !storageCanManageTrashFile($user, $trashFile)) {
            continue;
        }

        $files[] = $trashFile;
    }

    usort($files, static function (array $left, array $right): int {
        $leftDeleted = isset($left['deletedAt']) ? strtotime((string) $left['deletedAt']) : false;
        $rightDeleted = isset($right['deletedAt']) ? strtotime((string) $right['deletedAt']) : false;

        return ($rightDeleted ?: 0) <=> ($leftDeleted ?: 0);
    });

    return $files;
}

function storageAvailableUploadName(string $uploadDir, string $fileName): string
{
    $fileName = basename($fileName);
    if (!file_exists($uploadDir . $fileName)) {
        return $fileName;
    }

    $info = pathinfo($fileName);
    $extension = isset($info['extension']) && is_string($info['extension']) && $info['extension'] !== '' ? '.' . $info['extension'] : '';
    $base = isset($info['filename']) && is_string($info['filename']) && $info['filename'] !== '' ? $info['filename'] : 'file';

    do {
        $candidate = $base . '_restored_' . date('Ymd_His') . '_' . bin2hex(random_bytes(2)) . $extension;
    } while (file_exists($uploadDir . $candidate));

    return $candidate;
}

function storageRestoreFileFromTrash(string $storedName, array $restoredBy): array
{
    $uploadDir = storageUploadDir();
    $trashDir = storageTrashDir();
    $storedName = basename($storedName);
    $metadata = storageReadTrashMetadata();

    if (!isset($metadata[$storedName]) || !is_array($metadata[$storedName])) {
        throw new RuntimeException('File Trash tidak ditemukan.');
    }

    $trashFile = array_merge($metadata[$storedName], [
        'storedName' => $storedName,
        'owner' => isset($metadata[$storedName]['owner']) && is_string($metadata[$storedName]['owner']) ? $metadata[$storedName]['owner'] : 'admin',
    ]);

    if (!storageCanManageTrashFile($restoredBy, $trashFile)) {
        throw new RuntimeException('Anda tidak memiliki akses untuk memulihkan file ini.');
    }

    $source = $trashDir . $storedName;
    if (!is_file($source)) {
        throw new RuntimeException('File Trash tidak tersedia di disk.');
    }

    $originalName = isset($metadata[$storedName]['originalName']) && is_string($metadata[$storedName]['originalName']) && $metadata[$storedName]['originalName'] !== ''
        ? basename($metadata[$storedName]['originalName'])
        : $storedName;
    $restoredName = storageAvailableUploadName($uploadDir, $originalName);
    $destination = $uploadDir . $restoredName;

    if (!rename($source, $destination)) {
        throw new RuntimeException('File gagal dipulihkan dari Trash.');
    }

    $uploadMetadata = storageReadMetadata($uploadDir);
    $restoredMetadata = $metadata[$storedName];
        unset($restoredMetadata['storedName'], $restoredMetadata['originalName'], $restoredMetadata['deletedBy'], $restoredMetadata['deletedAt']);
        $uploadMetadata[$restoredName] = array_merge($restoredMetadata, [
            'owner' => isset($restoredMetadata['owner']) && is_string($restoredMetadata['owner']) ? $restoredMetadata['owner'] : 'admin',
            'uploadedByRole' => isset($restoredMetadata['uploadedByRole']) && is_string($restoredMetadata['uploadedByRole']) ? $restoredMetadata['uploadedByRole'] : 'admin',
            'viewerAccess' => (bool) ($restoredMetadata['viewerAccess'] ?? false),
            'restoredAt' => date(DATE_ATOM),
            'restoredBy' => (string) ($restoredBy['username'] ?? 'unknown'),
            'restoredFromTrash' => $storedName,
    ]);

    unset($metadata[$storedName]);
    if (!storageWriteMetadata($uploadDir, $uploadMetadata) || !storageWriteTrashMetadata($metadata)) {
        rename($destination, $source);
        throw new RuntimeException('Metadata restore gagal ditulis.');
    }

    try {
        $backupBootstrap = storageBackupBootstrapPath();
        if (is_file($backupBootstrap)) {
            require_once $backupBootstrap;
            $config = BackupConfig::fromEnvironment(__DIR__);
            (new BackupLogger($config))->audit('trash.restore', 'success', (string) ($restoredBy['username'] ?? 'unknown'), 'web', [
                'storedName' => $storedName,
                'restoredName' => $restoredName,
            ]);
        }
    } catch (Throwable $exception) {
        error_log('hawpiwcloud trash restore audit error: ' . $exception->getMessage());
    }

    return [
        'storedName' => $storedName,
        'restoredName' => $restoredName,
    ];
}

function storageCaptureFileVersion(string $uploadDir, string $fileName, array $metadata = []): bool
{
    if (!storageBackupModuleAvailable()) {
        return false;
    }

    try {
        require_once storageBackupBootstrapPath();

        return (new FileVersionManager(BackupConfig::fromEnvironment(__DIR__)))->capture($uploadDir . $fileName, $fileName, $metadata) !== null;
    } catch (Throwable $exception) {
        error_log('hawpiwcloud versioning error: ' . $exception->getMessage());

        return false;
    }
}

function storageIsPreviewableImage(string $filePath): bool
{
    if (!is_file($filePath)) {
        return false;
    }

    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $mimeType = finfo_file($finfo, $filePath);

            if (is_string($mimeType) && str_starts_with($mimeType, 'image/')) {
                return true;
            }
        }
    }

    return (bool) preg_match('/\.(jpe?g|png|gif|webp|bmp|avif|svg)$/i', $filePath);
}

function storageFormatFileSize(int $bytes): string
{
    if ($bytes >= 1024 * 1024) {
        return rtrim(rtrim(number_format($bytes / (1024 * 1024), 2, '.', ''), '0'), '.') . ' MB';
    }

    if ($bytes >= 1024) {
        return rtrim(rtrim(number_format($bytes / 1024, 2, '.', ''), '0'), '.') . ' KB';
    }

    return $bytes . ' B';
}

function storageFormatTimestamp(int $timestamp): string
{
    return date('M j, Y H:i', $timestamp);
}

function storageIniSizeToBytes(string $value): int
{
    $value = trim($value);
    $unit = strtolower(substr($value, -1));
    $number = (int) $value;

    return match ($unit) {
        'g' => $number * 1024 * 1024 * 1024,
        'm' => $number * 1024 * 1024,
        'k' => $number * 1024,
        default => (int) $value,
    };
}

function storageEffectiveUploadLimitBytes(): int
{
    $uploadMaxSizeBytes = storageIniSizeToBytes((string) ini_get('upload_max_filesize'));
    $postMaxSizeBytes = storageIniSizeToBytes((string) ini_get('post_max_size'));

    $serverLimits = array_filter([$uploadMaxSizeBytes, $postMaxSizeBytes], static fn(int $bytes): bool => $bytes > 0);
    $effectiveServerUploadLimitBytes = $serverLimits === [] ? HAWPIWCLOUD_UPLOAD_MAX_BYTES : min($serverLimits);

    return min(HAWPIWCLOUD_UPLOAD_MAX_BYTES, $effectiveServerUploadLimitBytes);
}

function storageRequestBodyExceededPostMaxSize(): bool
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return false;
    }

    $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
    $postMaxSize = storageIniSizeToBytes((string) ini_get('post_max_size'));

    return $contentLength > 0 && $postMaxSize > 0 && $contentLength > $postMaxSize;
}

function storageAllowedAcceptAttribute(): string
{
    return implode(',', array_map(static fn(string $extension): string => '.' . $extension, HAWPIWCLOUD_ALLOWED_EXTENSIONS));
}

function storageAllowedTypeLabel(): string
{
    return HAWPIWCLOUD_ALLOWED_FILE_LABEL;
}

function storageFileExtension(string $fileName): string
{
    return strtolower((string) pathinfo($fileName, PATHINFO_EXTENSION));
}

function storageIsAllowedUpload(array $file): bool
{
    $fileName = isset($file['name']) ? (string) $file['name'] : '';
    $extension = storageFileExtension($fileName);

    return in_array($extension, HAWPIWCLOUD_ALLOWED_EXTENSIONS, true);
}

function storageListFiles(string $uploadDir, ?array $user = null): array
{
    $files = [];
    $metadata = storageReadMetadata($uploadDir);

    if (!is_dir($uploadDir)) {
        return $files;
    }

    $items = scandir($uploadDir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..' || $item === '.metadata.json') {
            continue;
        }

        $filePath = $uploadDir . $item;
        if (!is_file($filePath)) {
            continue;
        }

        $fileMetadata = storageFileMetadata($metadata, $item);
        $file = [
            'name' => $item,
            'size' => filesize($filePath),
            'modified' => filemtime($filePath),
            'isImage' => storageIsPreviewableImage($filePath),
            'owner' => $fileMetadata['owner'],
            'uploadedByRole' => $fileMetadata['uploadedByRole'],
            'viewerAccess' => $fileMetadata['viewerAccess'],
        ];

        if (!authCanViewFile($user, $file)) {
            continue;
        }

        $files[] = $file;
    }

    usort($files, static fn(array $left, array $right): int => $right['modified'] <=> $left['modified']);

    return $files;
}
