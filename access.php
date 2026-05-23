<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'storage.php';

$currentUser = authRequireLogin();
if (!in_array($currentUser['role'], ['admin', 'user'], true)) {
    redirectWithAccessStatus('error_forbidden');
}

$uploadDir = storageUploadDir();
$isAjax = isset($_POST['ajax']) && $_POST['ajax'] === 'true';

function respond(string $status, bool $isAjax, string $fallbackStatus = ''): void
{
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['status' => $status]);
        exit;
    }
    
    $redirectStatus = $fallbackStatus !== '' ? $fallbackStatus : $status;
    header('Location: dashboard.php?status=' . rawurlencode($redirectStatus) . '#files');
    exit;
}

function isValidCsrfToken(?string $token): bool
{
    return isset($_SESSION['csrf_token']) && is_string($_SESSION['csrf_token']) && $token !== null && hash_equals($_SESSION['csrf_token'], $token);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond('error', $isAjax);
}

if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
    respond('error_security', $isAjax);
}

if (!isset($_POST['file']) || $_POST['file'] === '') {
    respond('error', $isAjax);
}

$fileName = basename((string) $_POST['file']);
$filePath = $uploadDir . $fileName;

if (!is_file($filePath)) {
    respond('error', $isAjax);
}

$metadata = storageReadMetadata($uploadDir);
$fileMetadata = storageFileMetadata($metadata, $fileName);

if ($currentUser['role'] !== 'admin' && $fileMetadata['owner'] !== $currentUser['username']) {
    respond('error_forbidden', $isAjax);
}

if (storageUpdateFileAccess($uploadDir, $fileName, isset($_POST['viewer_access']) && $_POST['viewer_access'] !== 'false', isset($_POST['guest_access']) && $_POST['guest_access'] !== 'false')) {
    respond('success', $isAjax, 'access_success');
}

respond('error_permissions', $isAjax);

