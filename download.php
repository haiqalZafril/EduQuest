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

// Try to get original filename from files data or assignment files
$files = eq_load_data('files');
$assignments = eq_load_data('assignments');
$originalName = $safeFile; // Default to stored name

// First check assignments for assignment files
$assignment_id = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : 0;
if ($assignment_id > 0) {
    foreach ($assignments as $assignment) {
        if ((int)$assignment['id'] === $assignment_id && isset($assignment['files']) && is_array($assignment['files'])) {
            foreach ($assignment['files'] as $file) {
                if (isset($file['stored_name']) && $file['stored_name'] === $safeFile) {
                    $originalName = $file['original_name'];
                    break 2;
                }
            }
        }
    }
}

// If not found in assignments, check files data
if ($originalName === $safeFile) {
    foreach ($files as $f) {
        if ($f['stored_name'] === $safeFile) {
            $originalName = $f['original_name'];
            break;
        }
    }
}

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . htmlspecialchars($originalName, ENT_QUOTES, 'UTF-8') . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;


