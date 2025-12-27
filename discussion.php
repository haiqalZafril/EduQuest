<?php
session_start();
require_once __DIR__ . '/data_store.php';

// Check user role
$isTeacher = isset($_SESSION['role']) && $_SESSION['role'] === 'teacher';
$isStudent = isset($_SESSION['role']) && $_SESSION['role'] === 'student';

if (!$isTeacher && !$isStudent) {
    header('Location: role_selection.php');
    exit;
}

// Load discussions from database
$discussions = eq_load_discussions();

// Handle creating new discussion (teachers and students)
$message = '';
$messageType = '';

if (($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_discussion')) {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    
    if (empty($title)) {
        $message = 'Please enter a discussion title.';
        $messageType = 'error';
    } elseif (empty($content)) {
        $message = 'Please write discussion content.';
        $messageType = 'error';
    } else {
        $author = $_SESSION['username'] ?? 'user';
        $role = $_SESSION['role'] ?? 'student';
        
        $result = eq_save_discussion($author, $role, $title, $content);
        
        if ($result['success']) {
            $message = 'Discussion topic created successfully!';
            $messageType = 'success';
            
            // Reload discussions from database
            $discussions = eq_load_discussions();
            $_POST = [];
        } else {
            $errorMsg = isset($result['error']) ? $result['error'] : 'Unknown error';
            $message = 'Error creating discussion: ' . $errorMsg;
            $messageType = 'error';
        }
    }
}

// Handle adding reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_reply') {
    $discussionId = (int)($_POST['discussion_id'] ?? 0);
    $replyContent = trim($_POST['reply_content'] ?? '');
    
    if (empty($replyContent)) {
        $message = 'Please write a reply.';
        $messageType = 'error';
    } else {
        $author = $_SESSION['username'] ?? 'user';
        $role = $_SESSION['role'] ?? 'student';
        
        $result = eq_add_discussion_reply($discussionId, $author, $role, $replyContent);
        
        if ($result['success']) {
            $message = 'Reply posted successfully!';
            $messageType = 'success';
            
            // Reload discussions from database
            $discussions = eq_load_discussions();
        } else {
            $errorMsg = isset($result['error']) ? $result['error'] : 'Unknown error';
            $message = 'Error posting reply: ' . $errorMsg;
            $messageType = 'error';
        }
    }
}

// Handle editing reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_reply') {
    $discussionId = (int)($_POST['discussion_id'] ?? 0);
    $replyId = (int)($_POST['reply_id'] ?? 0);
    $replyContent = trim($_POST['reply_content'] ?? '');
    
    if (empty($replyContent)) {
        $message = 'Please write a reply.';
        $messageType = 'error';
    } else {
        // Get the reply to check author
        $replyFound = false;
        foreach ($discussions as $discussion) {
            if ($discussion['id'] === $discussionId) {
                foreach ($discussion['replies'] as $reply) {
                    if ($reply['id'] === $replyId && $reply['author'] === $_SESSION['username']) {
                        $replyFound = true;
                        break;
                    }
                }
                break;
            }
        }
        
        if ($replyFound) {
            if (eq_update_discussion_reply($replyId, $replyContent)) {
                $message = 'Reply updated successfully!';
                $messageType = 'success';
                
                // Reload discussions from database
                $discussions = eq_load_discussions();
            } else {
                $message = 'Error updating reply. Please try again.';
                $messageType = 'error';
            }
        } else {
            $message = 'You can only edit your own replies.';
            $messageType = 'error';
        }
    }
}

// Handle deleting reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_reply') {
    $discussionId = (int)($_POST['discussion_id'] ?? 0);
    $replyId = (int)($_POST['reply_id'] ?? 0);
    
    // Get the reply to check author
    $replyFound = false;
    foreach ($discussions as $discussion) {
        if ($discussion['id'] === $discussionId) {
            foreach ($discussion['replies'] as $reply) {
                if ($reply['id'] === $replyId && $reply['author'] === $_SESSION['username']) {
                    $replyFound = true;
                    break;
                }
            }
            break;
        }
    }
    
    if ($replyFound) {
        if (eq_delete_discussion_reply($replyId)) {
            $message = 'Reply deleted successfully!';
            $messageType = 'success';
            
            // Reload discussions from database
            $discussions = eq_load_discussions();
        } else {
            $message = 'Error deleting reply. Please try again.';
            $messageType = 'error';
        }
    } else {
        $message = 'You can only delete your own replies.';
        $messageType = 'error';
    }
}

// Handle deleting discussion (anyone can delete their own)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_discussion') {
    $discussionId = (int)($_POST['discussion_id'] ?? 0);
    
    // Check if user is the author
    $isAuthor = false;
    foreach ($discussions as $discussion) {
        if ($discussion['id'] === $discussionId && $discussion['author'] === $_SESSION['username']) {
            $isAuthor = true;
            break;
        }
    }
    
    if ($isAuthor) {
        if (eq_delete_discussion($discussionId)) {
            $message = 'Discussion deleted successfully!';
            $messageType = 'success';
            
            // Reload discussions from database
            $discussions = eq_load_discussions();
        } else {
            $message = 'Error deleting discussion. Please try again.';
            $messageType = 'error';
        }
    } else {
        $message = 'You can only delete your own discussions.';
        $messageType = 'error';
    }
}

// Filters
$searchFilter = $_GET['search'] ?? '';
$sortBy = $_GET['sort'] ?? 'newest';

// Apply search filter
$filteredDiscussions = $discussions;
if ($searchFilter) {
    $searchLower = strtolower($searchFilter);
    $filteredDiscussions = array_filter($filteredDiscussions, function($disc) use ($searchLower) {
        $titleMatch = stripos($disc['title'], $searchLower) !== false;
        $contentMatch = stripos($disc['content'], $searchLower) !== false;
        return $titleMatch || $contentMatch;
    });
}

// Apply sort filter
if ($sortBy === 'newest') {
    usort($filteredDiscussions, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
} elseif ($sortBy === 'oldest') {
    usort($filteredDiscussions, function($a, $b) {
        return strtotime($a['created_at']) - strtotime($b['created_at']);
    });
} elseif ($sortBy === 'mostactive') {
    usort($filteredDiscussions, function($a, $b) {
        $aReplies = isset($a['replies']) ? count($a['replies']) : 0;
        $bReplies = isset($b['replies']) ? count($b['replies']) : 0;
        return $bReplies - $aReplies;
    });
}

// Helper function
function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $currentTime = time();
    $diff = $currentTime - $timestamp;
    
    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' min ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y', $timestamp);
    }
}

// Get user info
$username = $_SESSION['username'] ?? 'user';
$user = eq_get_user($username);

if ($user) {
    $displayName = $user['name'] ?? ucfirst($username);
    $displayEmail = $user['email'] ?? ($username . '@gmail.com');
    $userAvatar = $user['avatar'] ?? '';
} else {
    $displayName = ucfirst($username);
    $displayEmail = $username . '@gmail.com';
    $userAvatar = '';
}

// Generate initials from name
$nameParts = explode(' ', $displayName);
if (count($nameParts) >= 2) {
    $initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
} else {
    $initials = strtoupper(substr($displayName, 0, 2));
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discussion - eduQuest</title>
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
            background: #22c55e;
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
            background: #22c55e;
            color: white;
        }
        
        .nav-icon {
            width: 20px;
            text-align: center;
        }
        
        .main-content {
            flex: 1;
            margin-left: 250px;
        }
        
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
        }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        
        .notification-icon {
            font-size: 1.5rem;
            color: #6b7280;
            cursor: pointer;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: #22c55e;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
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
        
        .content-area {
            padding: 2rem;
        }
        
        .page-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .page-subtitle {
            color: #6b7280;
            margin-bottom: 2rem;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .alert-success {
            background: #dcfce7;
            color: #166534;
            border-left: 4px solid #22c55e;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }
        
        .discussion-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .tab {
            padding: 1rem 1.5rem;
            border: none;
            background: none;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            color: #6b7280;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s;
        }
        
        .tab.active {
            color: #22c55e;
            border-bottom-color: #22c55e;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .form-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        
        .form-section-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #1f2937;
        }
        
        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 0.875rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 1rem;
            font-family: inherit;
            transition: border-color 0.2s;
        }
        
        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: #22c55e;
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1);
        }
        
        .form-textarea {
            resize: vertical;
            min-height: 120px;
        }
        
        .form-button {
            background: #22c55e;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            font-size: 1rem;
        }
        
        .form-button:hover {
            background: #16a34a;
        }
        
        .form-button.secondary {
            background: #6b7280;
        }
        
        .form-button.secondary:hover {
            background: #4b5563;
        }
        
        .form-button.danger {
            background: #ef4444;
        }
        
        .form-button.danger:hover {
            background: #dc2626;
        }
        
        .filter-bar {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: flex-end;
            margin-bottom: 2rem;
        }
        
        .filter-bar .form-group {
            flex: 1;
            min-width: 200px;
            margin-bottom: 0;
        }
        
        .discussion-item {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #22c55e;
        }
        
        .discussion-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }
        
        .discussion-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }
        
        .discussion-meta {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            font-size: 0.9rem;
            color: #6b7280;
            margin-bottom: 1rem;
        }
        
        .role-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .role-teacher {
            background: #dbeafe;
            color: #0c4a6e;
        }
        
        .role-student {
            background: #fce7f3;
            color: #831843;
        }
        
        .discussion-content {
            color: #4b5563;
            line-height: 1.6;
            margin-bottom: 1rem;
            padding: 1rem;
            background: #f9fafb;
            border-radius: 6px;
        }
        
        .discussion-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9rem;
            color: #6b7280;
        }
        
        .discussion-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .discussion-actions button {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .reply-section {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e5e7eb;
        }
        
        .reply-item {
            background: #f9fafb;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            margin-left: 2rem;
        }
        
        .reply-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 0.5rem;
        }
        
        .reply-author {
            font-weight: 600;
            color: #1f2937;
        }
        
        .reply-time {
            font-size: 0.85rem;
            color: #9ca3af;
        }
        
        .reply-content {
            color: #4b5563;
            line-height: 1.5;
            margin-bottom: 0.75rem;
        }
        
        .reply-actions {
            display: flex;
            gap: 0.5rem;
            font-size: 0.85rem;
        }
        
        .reply-actions button {
            background: none;
            border: none;
            color: #3b82f6;
            cursor: pointer;
            text-decoration: underline;
            padding: 0;
        }
        
        .reply-actions button:hover {
            color: #1d4ed8;
        }
        
        .reply-form {
            margin-top: 1rem;
            padding: 1rem;
            background: white;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6b7280;
        }
        
        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .edit-form {
            background: #f9fafb;
            padding: 1rem;
            border-radius: 6px;
            margin-top: 0.5rem;
            border: 1px solid #e5e7eb;
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
                    <?php if ($isTeacher): ?>
                        <li class="nav-item">
                            <a href="teacher_dashboard.php" class="nav-link">
                                <span class="nav-icon">☰</span>
                                <span>Overview</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="teacher_profile.php" class="nav-link">
                                <span class="nav-icon">👤</span>
                                <span>My Profile</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="my_courses.php" class="nav-link">
                                <span class="nav-icon">🎓</span>
                                <span>My Courses</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="assignments.php" class="nav-link">
                                <span class="nav-icon">📄</span>
                                <span>Assignments</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="gradebook.php" class="nav-link">
                                <span class="nav-icon">✓</span>
                                <span>Grading</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="notes.php" class="nav-link">
                                <span class="nav-icon">📖</span>
                                <span>Notes</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="files.php" class="nav-link">
                                <span class="nav-icon">📁</span>
                                <span>Files</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="announcements.php" class="nav-link">
                                <span class="nav-icon">📢</span>
                                <span>Announcements</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="discussion.php" class="nav-link active">
                                <span class="nav-icon">💬</span>
                                <span>Discussion</span>
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a href="student_dashboard.php" class="nav-link">
                                <span class="nav-icon">☰</span>
                                <span>Overview</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="student_profile.php" class="nav-link">
                                <span class="nav-icon">👤</span>
                                <span>My Profile</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="my_courses.php" class="nav-link">
                                <span class="nav-icon">🎓</span>
                                <span>My Courses</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="assignments.php" class="nav-link">
                                <span class="nav-icon">📄</span>
                                <span>Assignments</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="student_grades.php" class="nav-link">
                                <span class="nav-icon">🏆</span>
                                <span>Grades</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="notes.php" class="nav-link">
                                <span class="nav-icon">📖</span>
                                <span>Notes</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="files.php" class="nav-link">
                                <span class="nav-icon">📁</span>
                                <span>My Files</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="announcements.php" class="nav-link">
                                <span class="nav-icon">📢</span>
                                <span>Announcements</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="discussion.php" class="nav-link active">
                                <span class="nav-icon">💬</span>
                                <span>Discussion</span>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <header class="header">
                <div class="header-left">
                    <div class="header-title">Discussion Forum</div>
                </div>
                <div class="header-right">
                    <div class="notification-icon">🔔</div>
                    <div class="user-profile" style="display: flex; align-items: center; gap: 1rem;">
                        <div class="user-avatar">
                            <?php if (!empty($userAvatar) && file_exists(__DIR__ . '/' . $userAvatar)): ?>
                                <img src="<?php echo htmlspecialchars($userAvatar); ?>" alt="avatar" style="width:100%; height:100%; object-fit:cover; border-radius:50%;" />
                            <?php else: ?>
                                <?php echo $initials; ?>
                            <?php endif; ?>
                        </div>
                        <div class="user-info">
                            <div class="user-name"><?php echo $displayName; ?></div>
                            <div class="user-email"><?php echo $displayEmail; ?></div>
                        </div>
                        <a href="logout.php" style="margin-left: 1rem; padding: 0.5rem 1rem; background: #ef4444; color: white; text-decoration: none; border-radius: 6px; font-size: 0.85rem;">Logout</a>
                    </div>
                </div>
            </header>
            
            <!-- Content Area -->
            <div class="content-area">
                <h1 class="page-title">Discussion Forum</h1>
                
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <?php echo $messageType === 'success' ? '✓' : '✕'; ?> <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Tabs -->
                <div class="discussion-tabs">
                    <button class="tab active" onclick="switchTab('create')">📝 Create Discussion</button>
                    <button class="tab" onclick="switchTab('existing')">📋 Existing Discussions</button>
                </div>
                
                <!-- Create Discussion Tab -->
                <div id="create" class="tab-content active">
                    <div class="form-card">
                        <h2 class="form-section-title">📝 Start New Discussion Topic</h2>
                        <form method="POST">
                                <input type="hidden" name="action" value="create_discussion">
                                
                                <div class="form-group">
                                    <label class="form-label">Discussion Title *</label>
                                    <input type="text" name="title" class="form-input" placeholder="Enter discussion title" required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Discussion Content *</label>
                                    <textarea name="content" class="form-textarea" placeholder="Write your discussion content..." required></textarea>
                                </div>
                                
                                <button type="submit" class="form-button">🚀 Create Topic</button>
                            </form>
                        </div>
                    </div>
                
                <!-- Existing Discussions Tab -->
                <div id="existing" class="tab-content">
                    <!-- Filters -->
                    <div class="form-card" style="margin-bottom: 2rem;">
                        <h2 class="form-section-title">🔍 Filter & Sort</h2>
                        <form method="GET" class="filter-bar">
                            <div class="form-group">
                                <label class="form-label">Search</label>
                                <input type="text" name="search" class="form-input" placeholder="Search discussions..." value="<?php echo htmlspecialchars($searchFilter); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Sort By</label>
                                <select name="sort" class="form-select">
                                    <option value="newest" <?php echo ($sortBy === 'newest') ? 'selected' : ''; ?>>Newest First</option>
                                    <option value="oldest" <?php echo ($sortBy === 'oldest') ? 'selected' : ''; ?>>Oldest First</option>
                                    <option value="mostactive" <?php echo ($sortBy === 'mostactive') ? 'selected' : ''; ?>>Most Active</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="form-button">🔍 Filter</button>
                            <a href="discussion.php" class="form-button secondary" style="text-decoration: none; text-align: center; margin-bottom: 0;">Reset</a>
                        </form>
                    </div>
                    
                    <!-- Discussions List -->
                    <h2 class="form-section-title">📋 Topics (<?php echo count($filteredDiscussions); ?>)</h2>
                    
                    <?php if (count($filteredDiscussions) > 0): ?>
                        <?php foreach ($filteredDiscussions as $discussion): ?>
                            <div class="discussion-item">
                                <div class="discussion-header">
                                    <div>
                                        <h3 class="discussion-title"><?php echo htmlspecialchars($discussion['title']); ?></h3>
                                    </div>
                                    <?php if ($discussion['author'] === $_SESSION['username']): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="delete_discussion">
                                            <input type="hidden" name="discussion_id" value="<?php echo $discussion['id']; ?>">
                                            <button type="submit" class="form-button danger" style="padding: 0.5rem 1rem; font-size: 0.85rem;" onclick="return confirm('Are you sure?')">🗑 Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="discussion-meta">
                                    <span class="role-badge role-<?php echo htmlspecialchars($discussion['author_role'] ?? 'student'); ?>">
                                        <?php echo ucfirst(htmlspecialchars($discussion['author_role'] ?? 'Student')); ?>
                                    </span>
                                    <span><strong>By:</strong> <?php echo htmlspecialchars($discussion['author']); ?></span>
                                    <span><strong>Posted:</strong> <?php echo timeAgo($discussion['created_at']); ?></span>
                                    <span><strong>Replies:</strong> <?php echo isset($discussion['replies']) ? count($discussion['replies']) : 0; ?></span>
                                </div>
                                
                                <div class="discussion-content">
                                    <?php echo nl2br(htmlspecialchars($discussion['content'])); ?>
                                </div>
                                
                                <!-- Reply Section -->
                                <div class="reply-section">
                                    <h4 style="margin-bottom: 1rem; font-size: 1rem;">Replies (<?php echo isset($discussion['replies']) ? count($discussion['replies']) : 0; ?>)</h4>
                                    
                                    <?php if (isset($discussion['replies']) && count($discussion['replies']) > 0): ?>
                                        <?php foreach ($discussion['replies'] as $reply): ?>
                                            <div class="reply-item" id="reply-<?php echo $reply['id']; ?>">
                                                <div class="reply-header">
                                                    <div>
                                                        <span class="reply-author"><?php echo htmlspecialchars($reply['author']); ?></span>
                                                        <span class="role-badge role-<?php echo $reply['author_role']; ?>" style="margin-left: 0.5rem; font-size: 0.75rem;">
                                                            <?php echo ucfirst($reply['author_role']); ?>
                                                        </span>
                                                    </div>
                                                    <span class="reply-time"><?php echo timeAgo($reply['created_at']); ?></span>
                                                </div>
                                                
                                                <div class="reply-content">
                                                    <?php echo nl2br(htmlspecialchars($reply['content'])); ?>
                                                </div>
                                                
                                                <?php if ($reply['author'] === $_SESSION['username']): ?>
                                                    <div class="reply-actions">
                                                        <button onclick="showEditForm('reply-<?php echo $reply['id']; ?>')">✏ Edit</button>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="action" value="delete_reply">
                                                            <input type="hidden" name="discussion_id" value="<?php echo $discussion['id']; ?>">
                                                            <input type="hidden" name="reply_id" value="<?php echo $reply['id']; ?>">
                                                            <button type="submit" style="color: #ef4444; background: none; border: none; cursor: pointer; text-decoration: underline;" onclick="return confirm('Are you sure?')">🗑 Delete</button>
                                                        </form>
                                                    </div>
                                                    
                                                    <div class="edit-form" id="edit-form-reply-<?php echo $reply['id']; ?>" style="display: none;">
                                                        <form method="POST">
                                                            <input type="hidden" name="action" value="edit_reply">
                                                            <input type="hidden" name="discussion_id" value="<?php echo $discussion['id']; ?>">
                                                            <input type="hidden" name="reply_id" value="<?php echo $reply['id']; ?>">
                                                            
                                                            <textarea name="reply_content" class="form-textarea" style="min-height: 80px; margin-bottom: 0.5rem;"><?php echo htmlspecialchars($reply['content']); ?></textarea>
                                                            
                                                            <div style="display: flex; gap: 0.5rem;">
                                                                <button type="submit" class="form-button" style="padding: 0.5rem 1rem; font-size: 0.85rem;">💾 Save</button>
                                                                <button type="button" onclick="hideEditForm('reply-<?php echo $reply['id']; ?>')" class="form-button secondary" style="padding: 0.5rem 1rem; font-size: 0.85rem;">Cancel</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    
                                    <!-- Add Reply Form -->
                                    <div class="reply-form">
                                        <h5 style="margin-bottom: 0.75rem; font-size: 0.95rem;">Add Your Reply</h5>
                                        <form method="POST">
                                            <input type="hidden" name="action" value="add_reply">
                                            <input type="hidden" name="discussion_id" value="<?php echo $discussion['id']; ?>">
                                            
                                            <textarea name="reply_content" class="form-textarea" placeholder="Write your reply..." required style="min-height: 80px; margin-bottom: 0.75rem;"></textarea>
                                            
                                            <button type="submit" class="form-button" style="padding: 0.5rem 1rem; font-size: 0.85rem;">📤 Post Reply</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">💭</div>
                            <p>No discussions found. Try adjusting your filters.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }
        
        function showEditForm(replyId) {
            const replyItem = document.getElementById(replyId);
            const editForm = replyItem.querySelector('[id^="edit-form-"]');
            if (editForm) {
                editForm.style.display = 'block';
            }
        }
        
        function hideEditForm(replyId) {
            const replyItem = document.getElementById(replyId);
            const editForm = replyItem.querySelector('[id^="edit-form-"]');
            if (editForm) {
                editForm.style.display = 'none';
            }
        }
    </script>
</body>
</html>
