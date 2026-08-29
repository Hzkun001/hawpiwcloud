<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

requireAuthentication();

$uploadDir = storageDirectory();

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

function requestBodyExceededPostMaxSize(): bool
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return false;
    }

    $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
    $postMaxSize = iniSizeToBytes((string) ini_get('post_max_size'));

    return $contentLength > 0 && $postMaxSize > 0 && $contentLength > $postMaxSize;
}

// Validasi request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectWithStatus('error');
}

if (requestBodyExceededPostMaxSize()) {
    redirectWithStatus('error_size');
}

if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
    redirectWithStatus('error_security');
}

if (!isset($_FILES['fileToUpload']) || !is_array($_FILES['fileToUpload'])) {
    redirectWithStatus('error_nofile');
}

$file = $_FILES['fileToUpload'];

if (!isset($file['error'], $file['size'], $file['name'], $file['tmp_name'])
    || !is_int($file['error'])
    || !is_int($file['size'])
    || !is_string($file['name'])
    || !is_string($file['tmp_name'])) {
    redirectWithStatus('error');
}

// Cek error upload
if ($file['error'] !== UPLOAD_ERR_OK) {
    redirectWithStatus(match ($file['error']) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'error_size',
        UPLOAD_ERR_PARTIAL => 'error_partial',
        UPLOAD_ERR_NO_FILE => 'error_nofile',
        default => 'error',
    });
}

// Batas ukuran file: 20 MB
$maxFileSize = 20 * 1024 * 1024;
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

// Cadangkan nama secara atomik agar unggahan bersamaan tidak menimpa file.
$targetPath = $uploadDir . $sanitizedFileName;
$reservation = @fopen($targetPath, 'x');

for ($attempt = 0; $reservation === false && $attempt < 10; $attempt++) {
    $fileInfo = pathinfo($sanitizedFileName);
    $nameOnly = $fileInfo['filename'] ?? 'file';
    $extension = isset($fileInfo['extension']) ? '.' . $fileInfo['extension'] : '';
    $candidate = $nameOnly . '_' . bin2hex(random_bytes(8)) . $extension;
    $targetPath = $uploadDir . $candidate;
    $reservation = @fopen($targetPath, 'x');
    if ($reservation !== false) {
        $sanitizedFileName = $candidate;
    }
}

if ($reservation === false) {
    redirectWithStatus('error');
}

fclose($reservation);

// Pindahkan file ke penyimpanan privat.
if (move_uploaded_file($file['tmp_name'], $targetPath)) {
    redirectWithStatus('upload_success');
}

unlink($targetPath);
redirectWithStatus('error_permissions');
