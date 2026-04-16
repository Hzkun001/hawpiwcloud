<?php
declare(strict_types=1);

$uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;

if (!isset($_GET['file']) || $_GET['file'] === '') {
    http_response_code(400);
    echo 'Parameter file tidak valid.';
    exit;
}

// Amankan nama file
$fileName = basename((string)$_GET['file']);
$filePath = $uploadDir . $fileName;

if (!is_file($filePath) || !file_exists($filePath)) {
    http_response_code(404);
    echo 'File tidak ditemukan.';
    exit;
}

// Tentukan tipe MIME
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = $finfo ? finfo_file($finfo, $filePath) : 'application/octet-stream';
if ($finfo) {
    finfo_close($finfo);
}

header('Content-Description: File Transfer');
header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . rawurlencode($fileName) . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Expires: 0');

readfile($filePath);
exit;