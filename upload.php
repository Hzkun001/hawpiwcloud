<?php

declare(strict_types=1);

session_start();

$uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
$appMaxFileSize = 2 * 1024 * 1024;

function redirectWithStatus(string $status): void
{
    header('Location: index.php?status=' . rawurlencode($status));
    exit;
}

function iniSizeToBytes(string $value): int
{
    $value = trim($value);
    $unit = strtolower(substr($value, -1));
    $number = (int)$value;

    return match ($unit) {
        'g' => $number * 1024 * 1024 * 1024,
        'm' => $number * 1024 * 1024,
        'k' => $number * 1024,
        default => (int)$value,
    };
}

function effectiveServerUploadLimitBytes(): int
{
    $uploadMaxSize = iniSizeToBytes((string) ini_get('upload_max_filesize'));
    $postMaxSize = iniSizeToBytes((string) ini_get('post_max_size'));

    $limits = array_filter([$uploadMaxSize, $postMaxSize], static fn(int $bytes): bool => $bytes > 0);

    if ($limits === []) {
        return 0;
    }

    return min($limits);
}

function requestBodyExceededPostMaxSize(): bool
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return false;
    }

    $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
    $postMaxSize = iniSizeToBytes((string) ini_get('post_max_size'));

    return $contentLength > 0 && $postMaxSize > 0 && $contentLength > $postMaxSize;
}

function redirectWithSecurityError(): void
{
    header('Location: index.php?status=error_security');
    exit;
}

function isValidCsrfToken(?string $token): bool
{
    return isset($_SESSION['csrf_token']) && is_string($_SESSION['csrf_token']) && $token !== null && hash_equals($_SESSION['csrf_token'], $token);
}

// Pastikan folder uploads ada
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
        redirectWithStatus('error_permissions');
    }
}

if (!is_writable($uploadDir)) {
    redirectWithStatus('error_permissions');
}

// Validasi request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectWithStatus('error');
}

if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
    redirectWithSecurityError();
}

if (requestBodyExceededPostMaxSize()) {
    redirectWithStatus('error_server_limit');
}

if (!isset($_FILES['fileToUpload'])) {
    redirectWithStatus('error_nofile');
}

$file = $_FILES['fileToUpload'];

// Cek error upload
if ($file['error'] !== UPLOAD_ERR_OK) {
    redirectWithStatus(match ($file['error']) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'error_server_limit',
        UPLOAD_ERR_PARTIAL => 'error_partial',
        UPLOAD_ERR_NO_FILE => 'error_nofile',
        default => 'error',
    });
}

// Batas ukuran file: 2 MB
$maxFileSize = $appMaxFileSize;
$effectiveServerLimit = effectiveServerUploadLimitBytes();
if ($effectiveServerLimit > 0 && (int) $file['size'] > $effectiveServerLimit) {
    redirectWithStatus('error_server_limit');
}

if ((int)$file['size'] > $maxFileSize) {
    redirectWithStatus('error_size');
}

// Ambil nama file asli dan bersihkan karakter berbahaya
$originalName = (string)$file['name'];
$baseName = basename($originalName);
$sanitizedFileName = preg_replace('/[^A-Za-z0-9._-]/', '_', $baseName);

if ($sanitizedFileName === null || $sanitizedFileName === '') {
    redirectWithStatus('error');
}

// Cegah file tertimpa: tambahkan timestamp jika nama sudah ada
$targetPath = $uploadDir . $sanitizedFileName;
if (file_exists($targetPath)) {
    $fileInfo = pathinfo($sanitizedFileName);
    $nameOnly = $fileInfo['filename'] ?? 'file';
    $extension = isset($fileInfo['extension']) ? '.' . $fileInfo['extension'] : '';
    $sanitizedFileName = $nameOnly . '_' . date('Ymd_His') . $extension;
    $targetPath = $uploadDir . $sanitizedFileName;
}

// Pindahkan file ke folder uploads
if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    redirectWithStatus('upload_success');
}

redirectWithStatus('error_permissions');
