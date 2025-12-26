<?php
session_start();
require_once __DIR__ . '/data_store.php';

// Require login (student or instructor)
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['student', 'teacher'])) {
    header('Location: role_selection.php');
    exit;
}

$isStudent = $_SESSION['role'] === 'student';

$files = eq_load_data('files');
$message = '';

// Handle file upload (both students and instructors can upload)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_file') {
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        // Check file size (5MB limit)
        if ($_FILES['file']['size'] > 5 * 1024 * 1024) {
            $message = 'File size exceeds 5MB limit. Please upload a smaller file.';
        } else {
            $uploadsDir = __DIR__ . '/uploads';
            if (!is_dir($uploadsDir)) {
                mkdir($uploadsDir, 0777, true);
            }
            
            $originalName = basename($_FILES['file']['name']);
            $safeName = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $originalName);
            $targetName = 'file_' . time() . '_' . $safeName;
            $targetPath = $uploadsDir . '/' . $targetName;
            
            if (move_uploaded_file($_FILES['file']['tmp_name'], $targetPath)) {
            $fileSize = filesize($targetPath);
            $category = trim($_POST['category'] ?? 'Other');
            
            // Determine category from file extension if not provided
            if ($category === 'Other' || $category === '') {
                $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                if (in_array($ext, ['pdf', 'doc', 'docx', 'txt', 'rtf'])) {
                    $category = 'Document';
                } elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg'])) {
                    $category = 'Image';
                } elseif (in_array($ext, ['mp4', 'avi', 'mov', 'wmv', 'flv'])) {
                    $category = 'Video';
                } elseif (in_array($ext, ['zip', 'rar', '7z', 'tar', 'gz'])) {
                    $category = 'Archive';
                } else {
                    $category = 'Other';
                }
            }
            
            // Get current user info
            $currentUsername = $_SESSION['username'] ?? ($isStudent ? 'student1' : 'teacher1');
            $currentRole = $_SESSION['role'] ?? ($isStudent ? 'student' : 'teacher');
            
            $id = eq_next_id($files);
            $files[] = [
                'id' => $id,
                'original_name' => $originalName,
                'stored_name' => $targetName,
                'category' => $category,
                'size' => $fileSize,
                'uploaded_date' => date('Y-m-d'),
                'uploaded_datetime' => date('Y-m-d H:i:s'),
                'uploaded_by' => $currentUsername,
                'uploaded_by_role' => $currentRole
            ];
            eq_save_data('files', $files);
            $message = 'File uploaded successfully.';
            } else {
                $message = 'Failed to upload file.';
            }
        }
    } else {
        $message = 'No file selected or upload error.';
    }
}

// Handle file deletion (users can only delete their own files)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_file') {
    $fileId = (int)($_POST['file_id'] ?? 0);
    $currentUsername = $_SESSION['username'] ?? ($isStudent ? 'student1' : 'teacher1');
    
    foreach ($files as $key => $file) {
        if ((int)$file['id'] === $fileId) {
            // Check if the file belongs to the current user
            $fileOwner = $file['uploaded_by'] ?? '';
            if ($fileOwner === $currentUsername) {
                // Delete physical file
                $filePath = __DIR__ . '/uploads/' . $file['stored_name'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                // Remove from array
                unset($files[$key]);
                $files = array_values($files); // Re-index array
                eq_save_data('files', $files);
                $message = 'File deleted successfully.';
            } else {
                $message = 'You can only delete your own files.';
            }
            break;
        }
    }
}

// Get user info based on role
$username = $_SESSION['username'] ?? ($isStudent ? 'student1' : 'teacher1');
$user = eq_get_user($username);

if ($user) {
    $userName = $user['name'] ?? ucfirst($username);
    $userEmail = $user['email'] ?? ($username . '@gmail.com');
    $userAvatar = $user['avatar'] ?? '';
} else {
    $userName = ucfirst($username);
    $userEmail = $username . '@gmail.com';
    $userAvatar = '';
}

// Generate initials from name
$nameParts = explode(' ', $userName);
if (count($nameParts) >= 2) {
    $initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
} else {
    $initials = strtoupper(substr($userName, 0, 2));
}

// Get current user info for filtering
$currentUsername = $_SESSION['username'] ?? ($isStudent ? 'student1' : 'teacher1');

// Filter files to show only current user's files
$userFiles = array_filter($files, function($file) use ($currentUsername) {
    $fileOwner = $file['uploaded_by'] ?? '';
    return $fileOwner === $currentUsername;
});

// Search filter
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$filteredFiles = $userFiles;
if ($searchQuery !== '') {
    $filteredFiles = array_filter($userFiles, function($file) use ($searchQuery) {
        return stripos($file['original_name'], $searchQuery) !== false ||
               stripos($file['category'], $searchQuery) !== false;
    });
}

// Sort by uploaded date (newest first)
usort($filteredFiles, function($a, $b) {
    return strtotime($b['uploaded_datetime']) - strtotime($a['uploaded_datetime']);
});

// Format file size
function formatFileSize($bytes) {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 0) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

// Get file icon based on extension
function getFileIcon($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (in_array($ext, ['pdf'])) {
        return '📄'; // Red document
    } elseif (in_array($ext, ['doc', 'docx'])) {
        return '📘'; // Blue document
    } elseif (in_array($ext, ['zip', 'rar', '7z'])) {
        return '📦'; // Yellow folder
    } elseif (in_array($ext, ['sql', 'db'])) {
        return '📋'; // Grey document
    } else {
        return '📄'; // Default
    }
}

// Get file icon color
function getFileIconColor($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (in_array($ext, ['pdf'])) {
        return '#ef4444'; // Red
    } elseif (in_array($ext, ['doc', 'docx'])) {
        return '#3b82f6'; // Blue
    } elseif (in_array($ext, ['zip', 'rar', '7z'])) {
        return '#f59e0b'; // Yellow
    } elseif (in_array($ext, ['sql', 'db'])) {
        return '#6b7280'; // Grey
    } else {
        return '#6b7280'; // Default grey
    }
}

// Get current page for active menu highlighting
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Manager - eduQuest <?php echo $isStudent ? 'Student' : 'Instructor'; ?> Portal</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f5f7fa;
            color: #1f2937;
        }
        
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            width: 250px;
            background: white;
            border-right: 1px solid #e5e7eb;
            padding: 2rem 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        
        .sidebar-logo {
            padding: 0 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .logo-icon {
            width: 40px;
            height: 40px;
            background: <?php echo $isStudent ? '#3b82f6' : '#22c55e'; ?>;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }
        
        .logo-text {
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .nav-menu {
            list-style: none;
        }
        
        .nav-item {
            margin: 0.25rem 0;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1.5rem;
            color: #6b7280;
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .nav-link:hover {
            background: #f9fafb;
            color: #1f2937;
        }
        
        .nav-link.active {
            background: <?php echo $isStudent ? '#3b82f6' : '#22c55e'; ?>;
            color: white;
        }
        
        .nav-icon {
            width: 20px;
            text-align: center;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 250px;
        }
        
        /* Header */
        .header {
            background: white;
            border-bottom: 1px solid #e5e7eb;
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .header-title {
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .header-title .logo-icon {
            width: 32px;
            height: 32px;
            background: #22c55e;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
        }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        
        .user-profile {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: <?php echo $isStudent ? '#3b82f6' : '#22c55e'; ?>;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        
        .user-info {
            display: flex;
            flex-direction: column;
        }
        
        .user-name {
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .user-email {
            font-size: 0.8rem;
            color: #6b7280;
        }
        
        /* Content Area */
        .content-area {
            padding: 2rem;
        }
        
        .page-header {
            margin-bottom: 2rem;
        }
        
        .page-title {
            font-size: 1.75rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .page-subtitle {
            color: #6b7280;
            font-size: 0.95rem;
        }
        
        .file-manager-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            gap: 1rem;
        }
        
        .search-container {
            flex: 1;
            max-width: 400px;
            position: relative;
        }
        
        .search-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.2s;
            text-align: left;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #22c55e;
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1);
        }
        
        .upload-btn {
            padding: 0.75rem 1.5rem;
            background: <?php echo $isStudent ? '#3b82f6' : '#22c55e'; ?>;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
        }
        
        .upload-btn:hover {
            background: <?php echo $isStudent ? '#2563eb' : '#16a34a'; ?>;
        }
        
        .message {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #6ee7b7;
        }
        
        .files-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
        }
        
        .file-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: all 0.2s;
            position: relative;
        }
        
        .file-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .file-card-header {
            display: flex;
            align-items: start;
            justify-content: space-between;
            margin-bottom: 1rem;
        }
        
        .file-icon {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            background: #f3f4f6;
        }
        
        .file-menu {
            position: relative;
        }
        
        .file-menu-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 0.25rem;
            color: #6b7280;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .file-menu-btn:hover {
            color: #1f2937;
        }
        
        .file-menu-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            min-width: 150px;
            z-index: 10;
            display: none;
        }
        
        .file-menu-dropdown.show {
            display: block;
        }
        
        .file-menu-item {
            padding: 0.75rem 1rem;
            cursor: pointer;
            font-size: 0.9rem;
            color: #1f2937;
            transition: background 0.2s;
        }
        
        .file-menu-item:hover {
            background: #f9fafb;
        }
        
        .file-menu-item.delete {
            color: #ef4444;
        }
        
        .file-name {
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 0.5rem;
            color: #1f2937;
            word-break: break-word;
        }
        
        .file-meta {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            margin-bottom: 1rem;
        }
        
        .file-meta-item {
            font-size: 0.85rem;
            color: #6b7280;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .file-meta-label {
            font-weight: 500;
            color: #1f2937;
        }
        
        .file-category {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            background: #f3f4f6;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
            color: #6b7280;
        }
        
        .file-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .download-btn {
            flex: 1;
            padding: 0.5rem 1rem;
            background: <?php echo $isStudent ? '#3b82f6' : '#22c55e'; ?>;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.2s;
            text-decoration: none;
        }
        
        .download-btn:hover {
            background: <?php echo $isStudent ? '#2563eb' : '#16a34a'; ?>;
        }
        
        /* Upload Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.show {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
        }
        
        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6b7280;
        }
        
        .modal-close:hover {
            color: #1f2937;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            font-size: 0.9rem;
            color: #1f2937;
        }
        
        .form-input,
        .form-select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        
        .form-input:focus,
        .form-select:focus {
            outline: none;
            border-color: #22c55e;
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1);
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: <?php echo $isStudent ? '#3b82f6' : '#22c55e'; ?>;
            color: white;
        }
        
        .btn-primary:hover {
            background: <?php echo $isStudent ? '#2563eb' : '#16a34a'; ?>;
        }
        
        .btn-secondary {
            background: #f3f4f6;
            color: #1f2937;
        }
        
        .btn-secondary:hover {
            background: #e5e7eb;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 200px;
            }
            
            .main-content {
                margin-left: 200px;
            }
            
            .files-grid {
                grid-template-columns: 1fr;
            }
            
            .file-manager-header {
                flex-direction: column;
            }
            
            .search-container {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-logo">
                <div class="logo-icon">🎓</div>
                <div class="logo-text">eduQuest</div>
            </div>
            <nav>
                <ul class="nav-menu">
                    <?php if ($isStudent): ?>
                        <li class="nav-item">
                            <a href="student_dashboard.php" class="nav-link <?php echo ($currentPage === 'student_dashboard.php') ? 'active' : ''; ?>">
                                <span class="nav-icon">☰</span>
                                <span>Overview</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="student_profile.php" class="nav-link <?php echo ($currentPage === 'student_profile.php') ? 'active' : ''; ?>">
                                <span class="nav-icon">👤</span>
                                <span>My Profile</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="my_courses.php" class="nav-link <?php echo ($currentPage === 'my_courses.php') ? 'active' : ''; ?>">
                                <span class="nav-icon">🎓</span>
                                <span>My Courses</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="assignments.php" class="nav-link <?php echo ($currentPage === 'assignments.php') ? 'active' : ''; ?>">
                                <span class="nav-icon">📄</span>
                                <span>Assignments</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="student_grades.php" class="nav-link <?php echo ($currentPage === 'student_grades.php') ? 'active' : ''; ?>">
                                <span class="nav-icon">🏆</span>
                                <span>Grades</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="notes.php" class="nav-link <?php echo ($currentPage === 'notes.php') ? 'active' : ''; ?>">
                                <span class="nav-icon">📖</span>
                                <span>Notes</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="files.php" class="nav-link <?php echo ($currentPage === 'files.php') ? 'active' : ''; ?>">
                                <span class="nav-icon">📁</span>
                                <span>My Files</span>
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a href="teacher_dashboard.php" class="nav-link <?php echo ($currentPage === 'teacher_dashboard.php') ? 'active' : ''; ?>">
                                <span class="nav-icon">☰</span>
                                <span>Overview</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="teacher_profile.php" class="nav-link <?php echo ($currentPage === 'teacher_profile.php') ? 'active' : ''; ?>">
                                <span class="nav-icon">👤</span>
                                <span>My Profile</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="my_courses.php" class="nav-link <?php echo ($currentPage === 'my_courses.php') ? 'active' : ''; ?>">
                                <span class="nav-icon">🎓</span>
                                <span>My Courses</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="assignments.php" class="nav-link <?php echo ($currentPage === 'assignments.php') ? 'active' : ''; ?>">
                                <span class="nav-icon">📄</span>
                                <span>Assignments</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="gradebook.php" class="nav-link <?php echo ($currentPage === 'gradebook.php') ? 'active' : ''; ?>">
                                <span class="nav-icon">✓</span>
                                <span>Grading</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="notes.php" class="nav-link <?php echo ($currentPage === 'notes.php') ? 'active' : ''; ?>">
                                <span class="nav-icon">📖</span>
                                <span>Notes</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="files.php" class="nav-link <?php echo ($currentPage === 'files.php') ? 'active' : ''; ?>">
                                <span class="nav-icon">📁</span>
                                <span>Files</span>
                            </a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a href="announcements.php" class="nav-link <?php echo ($currentPage === 'announcements.php') ? 'active' : ''; ?>">
                            <span class="nav-icon">📢</span>
                            <span>Announcements</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="discussion.php" class="nav-link <?php echo ($currentPage === 'discussion.php') ? 'active' : ''; ?>">
                            <span class="nav-icon">💬</span>
                            <span>Discussion</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <header class="header">
                <div class="header-left">
                    <div class="header-title">
                        <?php if ($isStudent): ?>
                            <div class="logo-icon" style="background: #3b82f6;">📚</div>
                            <span>eduQuest Student Portal</span>
                        <?php else: ?>
                            <div class="logo-icon">🎓</div>
                            <span>eduQuest Instructor Portal</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="header-right">
                    <div class="user-profile">
                        <div class="user-avatar">
                            <?php if (!empty($userAvatar) && file_exists(__DIR__ . '/' . $userAvatar)): ?>
                                <img src="<?php echo eq_h($userAvatar); ?>" alt="avatar" style="width:100%; height:100%; object-fit:cover; border-radius:50%;" />
                            <?php else: ?>
                                <?php echo eq_h($initials); ?>
                            <?php endif; ?>
                        </div>
                        <div class="user-info">
                            <div class="user-name"><?php echo eq_h($userName); ?></div>
                            <div class="user-email"><?php echo eq_h($userEmail); ?></div>
                        </div>
                    </div>
                    <a href="logout.php" style="margin-left: 1rem; padding: 0.5rem 1rem; background: #ef4444; color: white; text-decoration: none; border-radius: 6px; font-size: 0.85rem;">Logout</a>
                </div>
            </header>
            
            <!-- Content Area -->
            <div class="content-area">
                <div class="page-header">
                    <h1 class="page-title">File Manager</h1>
                    <p class="page-subtitle">Upload, download, and manage your files</p>
                </div>
                
                <?php if ($message): ?>
                    <div class="message"><?php echo eq_h($message); ?></div>
                <?php endif; ?>
                
                <div class="file-manager-header">
                    <div class="search-container">
                        <input type="text" class="search-input" id="searchInput" placeholder="Search files..." value="<?php echo eq_h($searchQuery); ?>">
                    </div>
                    <button class="upload-btn" onclick="openUploadModal()">
                        <span>⬆</span>
                        <span>Upload Files</span>
                    </button>
                </div>
                
                <div class="files-grid">
                    <?php if (empty($filteredFiles)): ?>
                        <div style="grid-column: 1 / -1; text-align: center; padding: 3rem; color: #6b7280;">
                            <p style="font-size: 1.1rem; margin-bottom: 0.5rem;">No files found</p>
                            <p style="font-size: 0.9rem;">Upload your first file to get started</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($filteredFiles as $file): ?>
                            <div class="file-card">
                                <div class="file-card-header">
                                    <div class="file-icon" style="background: <?php echo getFileIconColor($file['original_name']); ?>20; color: <?php echo getFileIconColor($file['original_name']); ?>;">
                                        <?php echo getFileIcon($file['original_name']); ?>
                                    </div>
                                    <div class="file-menu">
                                        <button class="file-menu-btn" onclick="toggleMenu(<?php echo $file['id']; ?>)">⋮</button>
                                        <div class="file-menu-dropdown" id="menu-<?php echo $file['id']; ?>">
                                            <div class="file-menu-item delete" onclick="deleteFile(<?php echo $file['id']; ?>)">Delete</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="file-name"><?php echo eq_h($file['original_name']); ?></div>
                                <div class="file-meta">
                                    <div class="file-meta-item">
                                        <span class="file-category"><?php echo eq_h($file['category']); ?></span>
                                    </div>
                                    <div class="file-meta-item">
                                        <span class="file-meta-label">Size</span>
                                        <span><?php echo formatFileSize($file['size']); ?></span>
                                    </div>
                                    <div class="file-meta-item">
                                        <span class="file-meta-label">Uploaded</span>
                                        <span><?php echo date('n/j/Y', strtotime($file['uploaded_date'])); ?></span>
                                    </div>
                                </div>
                                <div class="file-actions">
                                    <a href="download.php?file=<?php echo urlencode($file['stored_name']); ?>" class="download-btn">
                                        <span>⬇</span>
                                        <span>Download</span>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Upload Modal -->
    <div class="modal" id="uploadModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Upload File</h2>
                <button class="modal-close" onclick="closeUploadModal()">&times;</button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_file">
                <div class="form-group">
                    <label class="form-label" for="file">Select File (Max 5MB)</label>
                    <input type="file" class="form-input" id="file" name="file" required onchange="validateFileSize(this)">
                </div>
                <div class="form-group">
                    <label class="form-label" for="category">Category</label>
                    <select class="form-select" id="category" name="category">
                        <option value="Document">Document</option>
                        <option value="Image">Image</option>
                        <option value="Video">Video</option>
                        <option value="Archive">Archive</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeUploadModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Delete Form -->
    <form method="POST" id="deleteForm" style="display: none;">
        <input type="hidden" name="action" value="delete_file">
        <input type="hidden" name="file_id" id="deleteFileId">
    </form>
    
    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const query = this.value.trim();
                if (query) {
                    window.location.href = 'files.php?search=' + encodeURIComponent(query);
                } else {
                    window.location.href = 'files.php';
                }
            }
        });
        
        // Upload modal
        function openUploadModal() {
            document.getElementById('uploadModal').classList.add('show');
        }
        
        function closeUploadModal() {
            document.getElementById('uploadModal').classList.remove('show');
        }
        
        // Close modal on outside click
        document.getElementById('uploadModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeUploadModal();
            }
        });
        
        // File menu toggle
        function toggleMenu(fileId) {
            const menus = document.querySelectorAll('.file-menu-dropdown');
            menus.forEach(menu => {
                if (menu.id === 'menu-' + fileId) {
                    menu.classList.toggle('show');
                } else {
                    menu.classList.remove('show');
                }
            });
        }
        
        // Close menus on outside click
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.file-menu')) {
                document.querySelectorAll('.file-menu-dropdown').forEach(menu => {
                    menu.classList.remove('show');
                });
            }
        });
        
        // Delete file
        function deleteFile(fileId) {
            if (confirm('Are you sure you want to delete this file?')) {
                document.getElementById('deleteFileId').value = fileId;
                document.getElementById('deleteForm').submit();
            }
        }
        
        // File size validation function
        function validateFileSize(input) {
            const maxSize = 5 * 1024 * 1024; // 5MB in bytes
            if (input.files && input.files[0]) {
                if (input.files[0].size > maxSize) {
                    alert('File size exceeds 5MB limit. Please upload a smaller file.');
                    input.value = ''; // Clear the file input
                    return false;
                }
            }
            return true;
        }
    </script>
</body>
</html>

