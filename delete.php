<?php
declare(strict_types=1);

session_start();

$uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;

function redirectWithStatus(string $status): void
{
    header('Location: index.php?status=' . rawurlencode($status));
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

// Hapus file
if (unlink($filePath)) {
    redirectWithStatus('delete_success');
}

redirectWithStatus('error');