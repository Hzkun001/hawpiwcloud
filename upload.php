<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'storage.php';

$currentUser = authRequireLogin();
if (!authCanUpload($currentUser)) {
    header('Location: index.php?status=error_forbidden');
    exit;
}

$uploadDir = storageUploadDir();

function redirectWithStatus(string $status): void
{
    $target = authCanUseDashboard(authCurrentUser()) ? 'dashboard.php' : 'index.php';
    header('Location: ' . $target . '?status=' . rawurlencode($status));
    exit;
}

function redirectWithSecurityError(): void
{
    header('Location: dashboard.php?status=error_security');
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
if ((int)$file['size'] > storageEffectiveUploadLimitBytes()) {
    redirectWithStatus('error_size');
}

if (!storageIsAllowedUpload($file)) {
    redirectWithStatus('error_type');
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
    storageRegisterFile($uploadDir, $sanitizedFileName, $currentUser, [
        'viewerAccess' => isset($_POST['viewer_access']),
        'guestAccess' => isset($_POST['guest_access']),
    ]);
    redirectWithStatus('upload_success');
}

redirectWithStatus('error_permissions');
