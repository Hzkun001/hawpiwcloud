<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'storage.php';

$currentUser = authRequireLogin();

$uploadDir = storageUploadDir();

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

$metadata = storageFileMetadata(storageReadMetadata($uploadDir), $fileName);
$file = [
    'name' => $fileName,
    'owner' => $metadata['owner'],
    'viewerAccess' => $metadata['viewerAccess'],
    'guestAccess' => $metadata['guestAccess'],
    'uploadedByRole' => $metadata['uploadedByRole'],
];

if (!authCanDownloadFile($currentUser, $file)) {
    http_response_code(403);
    echo 'Anda tidak memiliki akses untuk mengunduh file ini.';
    exit;
}

// Tentukan tipe MIME
$mimeType = 'application/octet-stream';
if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo !== false) {
        $detectedMimeType = finfo_file($finfo, $filePath);

        if (is_string($detectedMimeType) && $detectedMimeType !== '') {
            $mimeType = $detectedMimeType;
        }
    }
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
