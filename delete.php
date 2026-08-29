<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

requireAuthentication();

$uploadDir = storageDirectory();

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
