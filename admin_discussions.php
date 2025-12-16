<?php
session_start();
require_once __DIR__ . '/data_store.php';

// Require admin login
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: role_selection.php');
    exit;
}

// Load discussions
$discussions = eq_load_data('discussions');

// Handle deleting discussion
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_discussion') {
    $discussionId = (int)($_POST['discussion_id'] ?? 0);
    
    foreach ($discussions as $index => $discussion) {
        if ($discussion['id'] === $discussionId) {
            unset($discussions[$index]);
            $discussions = array_values($discussions);
            eq_save_data('discussions', $discussions);
            $message = 'Discussion deleted successfully!';
            $messageType = 'success';
            break;
        }
    }
}

// Handle deleting reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_reply') {
    $discussionId = (int)($_POST['discussion_id'] ?? 0);
    $replyId = (int)($_POST['reply_id'] ?? 0);
    
    foreach ($discussions as &$discussion) {
        if ($discussion['id'] === $discussionId) {
            foreach ($discussion['replies'] as $index => $reply) {
                if ($reply['id'] === $replyId) {
                    unset($discussion['replies'][$index]);
                    $discussion['replies'] = array_values($discussion['replies']);
                    $message = 'Reply deleted successfully!';
                    $messageType = 'success';
                    break;
                }
            }
            break;
        }
    }
    eq_save_data('discussions', $discussions);
}

// Filters
$searchFilter = $_GET['search'] ?? '';
$sortBy = $_GET['sort'] ?? 'newest';

// Apply filters
$filteredDiscussions = $discussions;

if ($searchFilter) {
    $searchLower = strtolower($searchFilter);
    $filteredDiscussions = array_filter($filteredDiscussions, function($disc) use ($searchLower) {
        $titleMatch = stripos($disc['title'], $searchLower) !== false;
        $contentMatch = stripos($disc['content'], $searchLower) !== false;
        return $titleMatch || $contentMatch;
    });
}

// Apply sorting
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

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Discussions - Admin Panel</title>
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
            background: #a855f7;
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
            background: #a855f7;
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
        
        .header-title {
            font-size: 1.25rem;
            font-weight: 600;
        }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: #a855f7;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
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
        
        .filter-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .filter-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .filter-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #1f2937;
            font-size: 0.9rem;
        }
        
        .form-input,
        .form-select {
            padding: 0.875rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 1rem;
            font-family: inherit;
            transition: border-color 0.2s;
            width: 100%;
        }
        
        .form-input:focus,
        .form-select:focus {
            outline: none;
            border-color: #a855f7;
            box-shadow: 0 0 0 3px rgba(168, 85, 247, 0.1);
        }
        
        .filter-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 1rem;
        }
        
        .btn-primary {
            background: #a855f7;
            color: white;
        }
        
        .btn-primary:hover {
            background: #9333ea;
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
            text-decoration: none;
            text-align: center;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
        }
        
        .btn-danger {
            background: #ef4444;
            color: white;
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
        }
        
        .btn-danger:hover {
            background: #dc2626;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            text-align: center;
            margin-bottom: 2rem;
            width: 100%;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #a855f7;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #6b7280;
            font-size: 0.9rem;
        }
        
        .discussions-container {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .discussion-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #a855f7;
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
            background: #f9fafb;
            padding: 1rem;
            border-radius: 6px;
            color: #4b5563;
            line-height: 1.6;
            margin-bottom: 1rem;
            word-break: break-word;
        }
        
        .discussion-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid #e5e7eb;
        }
        
        .reply-count {
            color: #6b7280;
            font-size: 0.9rem;
        }
        
        .discussion-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .reply-list {
            margin-left: 2rem;
            padding-top: 1rem;
            border-top: 1px solid #e5e7eb;
        }
        
        .reply-item {
            background: #f9fafb;
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            border-left: 3px solid #d1d5db;
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
            font-size: 0.9rem;
        }
        
        .reply-time {
            color: #9ca3af;
            font-size: 0.85rem;
        }
        
        .reply-content {
            color: #4b5563;
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 0.5rem;
        }
        
        .reply-actions {
            display: flex;
            justify-content: flex-end;
        }
        
        .empty-state {
            padding: 3rem;
            text-align: center;
            color: #6b7280;
        }
        
        .empty-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-logo">
                <div class="logo-icon">⚙️</div>
                <div class="logo-text">Admin Panel</div>
            </div>
            <nav>
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="./admin_dashboard.php" class="nav-link">
                            <span class="nav-icon">☰</span>
                            <span>Overview</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <span class="nav-icon">👥</span>
                            <span>User Management</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <span class="nav-icon">🎓</span>
                            <span>Course Management</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="./admin_announcements.php" class="nav-link">
                            <span class="nav-icon">📢</span>
                            <span>Announcements</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="./admin_discussions.php" class="nav-link active">
                            <span class="nav-icon">💬</span>
                            <span>Discussion</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <span class="nav-icon">📊</span>
                            <span>Reports</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <span class="nav-icon">⚙️</span>
                            <span>Settings</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <header class="header">
                <div class="header-title">Discussion Management</div>
                <div class="header-right">
                    <div class="notification-icon" style="font-size: 1.5rem; color: #6b7280;">🔔</div>
                    <div class="user-avatar">AD</div>
                    <a href="logout.php" style="margin-left: 1rem; padding: 0.5rem 1rem; background: #ef4444; color: white; text-decoration: none; border-radius: 6px; font-size: 0.85rem;">Logout</a>
                </div>
            </header>
            
            <!-- Content Area -->
            <div class="content-area">
                <h1 class="page-title">Manage Discussions</h1>
                
                <?php if ($message): ?>
                    <div class="alert alert-success">
                        ✓ <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Statistics -->
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($discussions); ?></div>
                    <div class="stat-label">Total Discussions</div>
                </div>
                
                <!-- Filter Section -->
                <div class="filter-card">
                    <h3 class="filter-title">🔍 Filter & Search</h3>
                    <form method="GET">
                        <div class="filter-group">
                            <div class="form-group">
                                <label class="form-label">Search Title or Content</label>
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
                        </div>
                        
                        <div class="filter-buttons">
                            <button type="submit" class="btn btn-primary">🔍 Apply Filters</button>
                            <a href="admin_discussions.php" class="btn btn-secondary">Reset</a>
                        </div>
                    </form>
                </div>
                
                <!-- Discussions List -->
                <h3 style="font-size: 1.1rem; font-weight: 600; margin-bottom: 1.5rem;">💬 Discussions (<?php echo count($filteredDiscussions); ?>)</h3>
                
                <?php if (count($filteredDiscussions) > 0): ?>
                    <div class="discussions-container">
                        <?php foreach ($filteredDiscussions as $discussion): ?>
                            <div class="discussion-card">
                                <div class="discussion-header">
                                    <div>
                                        <h3 class="discussion-title"><?php echo htmlspecialchars($discussion['title']); ?></h3>
                                    </div>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="delete_discussion">
                                        <input type="hidden" name="discussion_id" value="<?php echo $discussion['id']; ?>">
                                        <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this discussion and all its replies?')">🗑 Delete</button>
                                    </form>
                                </div>
                                
                                <div class="discussion-meta">
                                    <span class="role-badge role-<?php echo htmlspecialchars($discussion['author_role'] ?? 'student'); ?>">
                                        <?php echo ucfirst(htmlspecialchars($discussion['author_role'] ?? 'Student')); ?>
                                    </span>
                                    <span><strong>By:</strong> <?php echo htmlspecialchars($discussion['author']); ?></span>
                                    <span><strong>Posted:</strong> <?php echo timeAgo($discussion['created_at']); ?></span>
                                </div>
                                
                                <div class="discussion-content">
                                    <?php echo nl2br(htmlspecialchars($discussion['content'])); ?>
                                </div>
                                
                                <!-- Replies -->
                                <?php if (isset($discussion['replies']) && count($discussion['replies']) > 0): ?>
                                    <div class="reply-list">
                                        <h5 style="margin-bottom: 1rem; font-size: 0.95rem; font-weight: 600;">Replies (<?php echo count($discussion['replies']); ?>)</h5>
                                        <?php foreach ($discussion['replies'] as $reply): ?>
                                            <div class="reply-item">
                                                <div class="reply-header">
                                                    <div>
                                                        <div class="reply-author"><?php echo htmlspecialchars($reply['author']); ?></div>
                                                    </div>
                                                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                                                        <span class="reply-time"><?php echo timeAgo($reply['created_at']); ?></span>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="action" value="delete_reply">
                                                            <input type="hidden" name="discussion_id" value="<?php echo $discussion['id']; ?>">
                                                            <input type="hidden" name="reply_id" value="<?php echo $reply['id']; ?>">
                                                            <button type="submit" class="btn btn-danger" onclick="return confirm('Delete this reply?')" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;">Delete</button>
                                                        </form>
                                                    </div>
                                                </div>
                                                <div class="reply-content">
                                                    <?php echo nl2br(htmlspecialchars($reply['content'])); ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">💭</div>
                        <p>No discussions found. Try adjusting your filters.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
