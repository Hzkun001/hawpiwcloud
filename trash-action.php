<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'storage.php';

$currentUser = authRequireLogin();
if (!authCanUseDashboard($currentUser)) {
    header('Location: index.php?status=error_forbidden');
    exit;
}

function redirectTrash(string $status): void
{
    header('Location: trash.php?status=' . rawurlencode($status));
    exit;
}

function trashActionValidCsrfToken(?string $token): bool
{
    return isset($_SESSION['csrf_token']) && is_string($_SESSION['csrf_token']) && $token !== null && hash_equals($_SESSION['csrf_token'], $token);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectTrash('error');
}

if (!trashActionValidCsrfToken($_POST['csrf_token'] ?? null)) {
    redirectTrash('error_security');
}

$action = (string) ($_POST['action'] ?? '');
$storedName = basename((string) ($_POST['stored_name'] ?? ''));

if ($action !== 'restore' || $storedName === '') {
    redirectTrash('error');
}

try {
    storageRestoreFileFromTrash($storedName, $currentUser);
    redirectTrash('restore_success');
} catch (Throwable $exception) {
    error_log('hawpiwcloud trash restore error: ' . $exception->getMessage());
    redirectTrash($exception->getMessage() === 'Anda tidak memiliki akses untuk memulihkan file ini.' ? 'error_forbidden' : 'restore_failed');
}
