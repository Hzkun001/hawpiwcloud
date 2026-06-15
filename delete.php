<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'storage.php';

$currentUser = authRequireLogin();

$uploadDir = storageUploadDir();

function redirectWithStatus(string $status): void
{
    $target = authCanUseDashboard(authCurrentUser()) ? 'dashboard-files.php' : 'index.php';
    header('Location: ' . $target . '?status=' . rawurlencode($status));
    exit;
}

function isValidCsrfToken(?string $token): bool
{
    return isset($_SESSION['csrf_token']) && is_string($_SESSION['csrf_token']) && $token !== null && hash_equals($_SESSION['csrf_token'], $token);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectWithStatus('error');
}

if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
    redirectWithStatus('error_security');
}

if (!isset($_POST['file']) || $_POST['file'] === '') {
    redirectWithStatus('error');
}

// Amankan nama file
$fileName = basename((string)$_POST['file']);
$filePath = $uploadDir . $fileName;

if (!is_file($filePath) || !file_exists($filePath)) {
    redirectWithStatus('error');
}

$metadata = storageFileMetadata(storageReadMetadata($uploadDir), $fileName);
$file = [
    'name' => $fileName,
    'owner' => $metadata['owner'],
    'viewerAccess' => $metadata['viewerAccess'],
    'uploadedByRole' => $metadata['uploadedByRole'],
];

if (!authCanDeleteFile($currentUser, $file)) {
    authLogAudit('delete', 'denied', $currentUser, ['file' => $fileName]);
    redirectWithStatus('error_forbidden');
}

// File tetap dapat dipulihkan sampai retention Trash berakhir.
if (storageMoveFileToTrash($uploadDir, $fileName, $currentUser)) {
    authLogAudit('delete', 'success', $currentUser, ['file' => $fileName, 'trash' => 'true']);
    redirectWithStatus('delete_success');
}

authLogAudit('delete', 'failed', $currentUser, ['file' => $fileName]);

redirectWithStatus('error');
