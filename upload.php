<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'storage.php';

$currentUser = authRequireLogin();
if (!authCanUpload($currentUser)) {
    authLogAudit('upload', 'denied', $currentUser, ['reason' => 'role_restricted']);
    header('Location: index.php?status=error_forbidden');
    exit;
}

$uploadDir = storageUploadDir();

function redirectWithStatus(string $status): void
{
    $user = authCurrentUser();
    if (authCanUseDashboard($user)) {
        $target = 'dashboard-upload.php';
    } else {
        $target = 'index.php';
    }

    header('Location: ' . $target . '?status=' . rawurlencode($status));
    exit;
}

function redirectWithSecurityError(): void
{
    $target = authCanUseDashboard(authCurrentUser()) ? 'dashboard-upload.php' : 'index.php';

    header('Location: ' . $target . '?status=error_security');
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

if (storageRequestBodyExceededPostMaxSize()) {
    redirectWithStatus('error_server_limit');
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
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'error_server_limit',
        UPLOAD_ERR_PARTIAL => 'error_partial',
        UPLOAD_ERR_NO_FILE => 'error_nofile',
        default => 'error',
    });
}

// Batas ukuran file: 2 MB
if ((int)$file['size'] > storageEffectiveUploadLimitBytes()) {
    authLogAudit('upload', 'denied', $currentUser, ['reason' => 'size_limit', 'file' => (string) $file['name']]);
    redirectWithStatus('error_size');
}

if (!storageIsAllowedUpload($file)) {
    authLogAudit('upload', 'denied', $currentUser, ['reason' => 'file_type', 'file' => (string) $file['name']]);
    redirectWithStatus('error_type');
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
    storageRegisterFile($uploadDir, $sanitizedFileName, $currentUser, [
        'viewerAccess' => isset($_POST['viewer_access']),
    ]);
    storageCaptureFileVersion($uploadDir, $sanitizedFileName, ['reason' => 'upload', 'owner' => $currentUser['username']]);
    authLogAudit('upload', 'success', $currentUser, ['file' => $sanitizedFileName, 'size' => (int) $file['size']]);
    redirectWithStatus('upload_success');
}

authLogAudit('upload', 'failed', $currentUser, ['file' => $sanitizedFileName]);

redirectWithStatus('error_permissions');
