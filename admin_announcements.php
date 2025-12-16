<?php
session_start();
require_once __DIR__ . '/data_store.php';

// Require admin login
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: role_selection.php');
    exit;
}

// Load announcements from database
$announcements = eq_load_announcements();

// Handle deleting announcement
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_announcement') {
    $announcementId = (int)($_POST['announcement_id'] ?? 0);
    
    if (eq_delete_announcement($announcementId)) {
        $message = 'Announcement deleted successfully!';
        $messageType = 'success';
        
        // Reload announcements from database
        $announcements = eq_load_announcements();
    } else {
        $message = 'Error deleting announcement. Please try again.';
        $messageType = 'error';
    }
}

// Filters
$searchFilter = $_GET['search'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$authorFilter = $_GET['author'] ?? '';
$sortBy = $_GET['sort'] ?? 'newest';

// Apply filters
$filteredAnnouncements = $announcements;

if ($searchFilter) {
    $searchLower = strtolower($searchFilter);
    $filteredAnnouncements = array_filter($filteredAnnouncements, function($ann) use ($searchLower) {
        $titleMatch = stripos($ann['title'], $searchLower) !== false;
        $contentMatch = stripos($ann['content'], $searchLower) !== false;
        return $titleMatch || $contentMatch;
    });
}

if ($categoryFilter && $categoryFilter !== 'all') {
    $filteredAnnouncements = array_filter($filteredAnnouncements, function($ann) use ($categoryFilter) {
        return $ann['category'] === $categoryFilter;
    });
}

if ($authorFilter) {
    $authorLower = strtolower($authorFilter);
    $filteredAnnouncements = array_filter($filteredAnnouncements, function($ann) use ($authorLower) {
        return stripos($ann['author'], $authorLower) !== false;
    });
}

// Apply sorting
if ($sortBy === 'newest') {
    usort($filteredAnnouncements, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
} elseif ($sortBy === 'oldest') {
    usort($filteredAnnouncements, function($a, $b) {
        return strtotime($a['created_at']) - strtotime($b['created_at']);
    });
}

// Get unique authors for filter
$authors = array_unique(array_column($announcements, 'author'));

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
    <title>Manage Announcements - Admin Panel</title>
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
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 1rem;
            font-family: inherit;
            transition: border-color 0.2s;
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
        
        .announcements-table {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .table-header {
            display: grid;
            grid-template-columns: 1fr 2fr 1fr 1fr 1.5fr 0.8fr;
            gap: 1rem;
            background: #f9fafb;
            padding: 1rem;
            font-weight: 600;
            color: #374151;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .table-row {
            display: grid;
            grid-template-columns: 1fr 2fr 1fr 1fr 1.5fr 0.8fr;
            gap: 1rem;
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
            align-items: center;
        }
        
        .table-row:last-child {
            border-bottom: none;
        }
        
        .table-row:hover {
            background: #f9fafb;
        }
        
        .category-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .category-exam {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .category-event {
            background: #fef3c7;
            color: #92400e;
        }
        
        .category-assignment {
            background: #e0e7ff;
            color: #3730a3;
        }
        
        .category-general {
            background: #d1fae5;
            color: #065f46;
        }
        
        .category-others {
            background: #f3e8ff;
            color: #6b21a8;
        }
        
        .title-cell {
            font-weight: 600;
            color: #1f2937;
            word-break: break-word;
        }
        
        .author-cell {
            color: #6b7280;
        }
        
        .time-cell {
            color: #9ca3af;
            font-size: 0.9rem;
        }
        
        .action-cell {
            display: flex;
            justify-content: center;
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
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            text-align: center;
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
                        <a href="./admin_announcements.php" class="nav-link active">
                            <span class="nav-icon">📢</span>
                            <span>Announcements</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="./admin_discussions.php" class="nav-link">
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
                <div class="header-title">Announcement Management</div>
                <div class="header-right">
                    <div class="notification-icon" style="font-size: 1.5rem; color: #6b7280;">🔔</div>
                    <div class="user-avatar">AD</div>
                    <a href="logout.php" style="margin-left: 1rem; padding: 0.5rem 1rem; background: #ef4444; color: white; text-decoration: none; border-radius: 6px; font-size: 0.85rem;">Logout</a>
                </div>
            </header>
            
            <!-- Content Area -->
            <div class="content-area">
                <h1 class="page-title">Manage Announcements</h1>
                
                <?php if ($message): ?>
                    <div class="alert alert-success">
                        ✓ <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Statistics -->
                <div class="stats-row">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($announcements); ?></div>
                        <div class="stat-label">Total Announcements</div>
                    </div>
                </div>
                
                <!-- Filter Section -->
                <div class="filter-card">
                    <h3 class="filter-title">🔍 Filter & Search</h3>
                    <form method="GET">
                        <div class="filter-group">
                            <div class="form-group">
                                <label class="form-label">Search Title or Content</label>
                                <input type="text" name="search" class="form-input" placeholder="Search announcements..." value="<?php echo htmlspecialchars($searchFilter); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Category</label>
                                <select name="category" class="form-select">
                                    <option value="">All Categories</option>
                                    <option value="exam" <?php echo ($categoryFilter === 'exam') ? 'selected' : ''; ?>>Exam</option>
                                    <option value="event" <?php echo ($categoryFilter === 'event') ? 'selected' : ''; ?>>Event</option>
                                    <option value="assignment" <?php echo ($categoryFilter === 'assignment') ? 'selected' : ''; ?>>Assignment</option>
                                    <option value="general" <?php echo ($categoryFilter === 'general') ? 'selected' : ''; ?>>General</option>
                                    <option value="others" <?php echo ($categoryFilter === 'others') ? 'selected' : ''; ?>>Others</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Sort By</label>
                                <select name="sort" class="form-select">
                                    <option value="newest" <?php echo ($sortBy === 'newest') ? 'selected' : ''; ?>>Newest First</option>
                                    <option value="oldest" <?php echo ($sortBy === 'oldest') ? 'selected' : ''; ?>>Oldest First</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="filter-buttons">
                            <button type="submit" class="btn btn-primary">🔍 Apply Filters</button>
                            <a href="admin_announcements.php" class="btn btn-secondary">Reset</a>
                        </div>
                    </form>
                </div>
                
                <!-- Announcements Table -->
                <div class="announcements-table">
                    <div class="table-header">
                        <div>Title</div>
                        <div>Content Preview</div>
                        <div>Category</div>
                        <div>Author</div>
                        <div>Posted</div>
                        <div>Action</div>
                    </div>
                    
                    <?php if (count($filteredAnnouncements) > 0): ?>
                        <?php foreach ($filteredAnnouncements as $announcement): ?>
                            <div class="table-row">
                                <div class="title-cell">
                                    <?php echo htmlspecialchars(substr($announcement['title'], 0, 30)); ?>
                                    <?php if (strlen($announcement['title']) > 30): ?>...<?php endif; ?>
                                </div>
                                
                                <div class="author-cell" style="font-size: 0.9rem;">
                                    <?php echo htmlspecialchars(substr($announcement['content'], 0, 50)); ?>
                                    <?php if (strlen($announcement['content']) > 50): ?>...<?php endif; ?>
                                </div>
                                
                                <div>
                                    <span class="category-badge category-<?php echo htmlspecialchars($announcement['category']); ?>">
                                        <?php echo ucfirst(htmlspecialchars($announcement['category'])); ?>
                                    </span>
                                </div>
                                
                                <div class="author-cell">
                                    <?php echo htmlspecialchars($announcement['author']); ?>
                                </div>
                                
                                <div class="time-cell">
                                    <?php echo timeAgo($announcement['created_at']); ?>
                                </div>
                                
                                <div class="action-cell">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="delete_announcement">
                                        <input type="hidden" name="announcement_id" value="<?php echo $announcement['id']; ?>">
                                        <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this announcement?')">🗑 Delete</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">📭</div>
                            <p>No announcements found. Try adjusting your filters.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
