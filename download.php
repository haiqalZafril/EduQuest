<?php
// Very simple and minimal file download handler.

require_once __DIR__ . '/data_store.php';

$file = isset($_GET['file']) ? $_GET['file'] : '';
if ($file === '') {
    http_response_code(400);
    echo 'Missing file parameter.';
    exit;
}

$safeFile = basename($file); // prevent directory traversal
$path = __DIR__ . '/uploads/' . $safeFile;

if (!is_file($path)) {
    http_response_code(404);
    echo 'File not found.';
    exit;
}

// Try to get original filename from files data
$files = eq_load_data('files');
$originalName = $safeFile; // Default to stored name
foreach ($files as $f) {
    if ($f['stored_name'] === $safeFile) {
        $originalName = $f['original_name'];
        break;
    }
}

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . htmlspecialchars($originalName, ENT_QUOTES, 'UTF-8') . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;


