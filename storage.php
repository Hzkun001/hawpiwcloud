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
        'guestAccess' => array_key_exists('guestAccess', $item) ? (bool) $item['guestAccess'] : false,
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
        'guestAccess' => $canSetAccess && (bool) ($access['guestAccess'] ?? false),
        'uploadedAt' => date(DATE_ATOM),
    ];

    return storageWriteMetadata($uploadDir, $metadata);
}

function storageUpdateFileAccess(string $uploadDir, string $fileName, bool $viewerAccess, bool $guestAccess): bool
{
    $metadata = storageReadMetadata($uploadDir);
    $current = storageFileMetadata($metadata, $fileName);
    $metadata[$fileName] = array_merge($metadata[$fileName] ?? [], [
        'owner' => $current['owner'],
        'uploadedByRole' => $current['uploadedByRole'],
        'viewerAccess' => $viewerAccess,
        'guestAccess' => $guestAccess,
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
            'guestAccess' => $fileMetadata['guestAccess'],
        ];

        if (!authCanViewFile($user, $file)) {
            continue;
        }

        $files[] = $file;
    }

    usort($files, static fn(array $left, array $right): int => $right['modified'] <=> $left['modified']);

    return $files;
}
