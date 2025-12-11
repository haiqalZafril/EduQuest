<?php
session_start();
require_once __DIR__ . '/data_store.php';

// Require login
if (!isset($_SESSION['role'])) {
    header('Location: role_selection.php');
    exit;
}

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

// Get file extension
$extension = strtolower(pathinfo($safeFile, PATHINFO_EXTENSION));

// Try to get original filename from notes data
$notes = eq_load_data('notes');
$originalName = $safeFile; // Default to stored name
foreach ($notes as $n) {
    if (isset($n['attachment_stored']) && $n['attachment_stored'] === $safeFile) {
        $originalName = $n['attachment_name'] ?? $safeFile;
        break;
    }
}

// If not found in notes, try files data
if ($originalName === $safeFile) {
    $files = eq_load_data('files');
    foreach ($files as $f) {
        if (isset($f['stored_name']) && $f['stored_name'] === $safeFile) {
            $originalName = $f['original_name'] ?? $safeFile;
            break;
        }
    }
}

// Determine content type and preview method
$canPreview = false;
$previewType = 'none';

// Check if file can be previewed
if (in_array($extension, ['pdf'])) {
    $canPreview = true;
    $previewType = 'pdf';
} elseif (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'])) {
    $canPreview = true;
    $previewType = 'image';
} elseif (in_array($extension, ['txt', 'md', 'html', 'css', 'js', 'json', 'xml', 'csv'])) {
    $canPreview = true;
    $previewType = 'text';
}

// If preview is requested via AJAX or iframe, return appropriate content
if (isset($_GET['embed']) && $canPreview) {
    if ($previewType === 'pdf') {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . htmlspecialchars($originalName, ENT_QUOTES, 'UTF-8') . '"');
        readfile($path);
        exit;
    } elseif ($previewType === 'image') {
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'bmp' => 'image/bmp'
        ];
        $mimeType = $mimeTypes[$extension] ?? 'image/jpeg';
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: inline; filename="' . htmlspecialchars($originalName, ENT_QUOTES, 'UTF-8') . '"');
        readfile($path);
        exit;
    } elseif ($previewType === 'text') {
        $content = file_get_contents($path);
        header('Content-Type: text/plain; charset=utf-8');
        echo htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
        exit;
    }
}

// Otherwise, return a preview page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview: <?php echo htmlspecialchars($originalName); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #1f2937;
            color: #f9fafb;
            display: flex;
            flex-direction: column;
            height: 100vh;
        }
        
        .preview-header {
            background: #111827;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #374151;
        }
        
        .preview-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #f9fafb;
        }
        
        .preview-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            border: none;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: #3b82f6;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2563eb;
        }
        
        .btn-secondary {
            background: #374151;
            color: #f9fafb;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
        }
        
        .preview-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            overflow: auto;
        }
        
        .preview-content {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .preview-iframe {
            width: 100%;
            height: 100%;
            border: none;
            background: white;
        }
        
        .preview-image {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            border-radius: 8px;
        }
        
        .preview-text {
            width: 100%;
            max-width: 1200px;
            background: #111827;
            padding: 2rem;
            border-radius: 8px;
            white-space: pre-wrap;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            line-height: 1.6;
            overflow: auto;
            color: #e5e7eb;
        }
        
        .preview-message {
            text-align: center;
            padding: 3rem;
            color: #9ca3af;
        }
        
        .preview-message h2 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: #f9fafb;
        }
        
        .preview-message p {
            font-size: 1rem;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="preview-header">
        <div class="preview-title"><?php echo htmlspecialchars($originalName); ?></div>
        <div class="preview-actions">
            <a href="download.php?file=<?php echo urlencode($safeFile); ?>" class="btn btn-primary">
                <span>⬇</span>
                <span>Download</span>
            </a>
            <button onclick="window.close()" class="btn btn-secondary">
                <span>✕</span>
                <span>Close</span>
            </button>
        </div>
    </div>
    
    <div class="preview-container">
        <?php if ($canPreview): ?>
            <?php if ($previewType === 'pdf'): ?>
                <div class="preview-content">
                    <iframe src="preview.php?file=<?php echo urlencode($safeFile); ?>&embed=1" class="preview-iframe"></iframe>
                </div>
            <?php elseif ($previewType === 'image'): ?>
                <div class="preview-content">
                    <img src="preview.php?file=<?php echo urlencode($safeFile); ?>&embed=1" alt="<?php echo htmlspecialchars($originalName); ?>" class="preview-image">
                </div>
            <?php elseif ($previewType === 'text'): ?>
                <div class="preview-content">
                    <div class="preview-text" id="textContent">Loading...</div>
                </div>
                <script>
                    fetch('preview.php?file=<?php echo urlencode($safeFile); ?>&embed=1')
                        .then(response => response.text())
                        .then(text => {
                            document.getElementById('textContent').textContent = text;
                        })
                        .catch(error => {
                            document.getElementById('textContent').textContent = 'Error loading file content.';
                        });
                </script>
            <?php endif; ?>
        <?php else: ?>
            <div class="preview-message">
                <h2>Preview Not Available</h2>
                <p>This file type cannot be previewed in the browser.</p>
                <a href="download.php?file=<?php echo urlencode($safeFile); ?>" class="btn btn-primary">
                    <span>⬇</span>
                    <span>Download File</span>
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

