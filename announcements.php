<?php
session_start();
require_once __DIR__ . '/data_store.php';

// Different handling for teachers and students
$isTeacher = isset($_SESSION['role']) && $_SESSION['role'] === 'teacher';
$isStudent = isset($_SESSION['role']) && $_SESSION['role'] === 'student';

if (!$isTeacher && !$isStudent) {
    header('Location: role_selection.php');
    exit;
}

// Load announcements from database
$announcements = eq_load_announcements();

// Handle form submission (teachers only)
$message = '';
$messageType = '';

if ($isTeacher && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $content = trim($_POST['content'] ?? '');
    
    // Validate inputs
    if (empty($title)) {
        $message = 'Please enter an announcement title.';
        $messageType = 'error';
    } elseif (empty($category)) {
        $message = 'Please select a category.';
        $messageType = 'error';
    } elseif (empty($content)) {
        $message = 'Please write your announcement content.';
        $messageType = 'error';
    } else {
        // Save to database
        $author = $_SESSION['username'] ?? 'teacher1';
        $result = eq_save_announcement($author, 'teacher', $title, $category, $content);
        
        if ($result['success']) {
            $message = 'Announcement posted successfully!';
            $messageType = 'success';
            
            // Reload announcements from database
            $announcements = eq_load_announcements();
            
            // Reset form
            $_POST = [];
        } else {
            $errorMsg = isset($result['error']) ? $result['error'] : 'Unknown error';
            $message = 'Error posting announcement: ' . $errorMsg;
            $messageType = 'error';
        }
    }
}

// Process filters for students
$filteredAnnouncements = $announcements;
$categoryFilter = $_GET['category'] ?? '';
$searchFilter = $_GET['search'] ?? '';
$dateFilter = $_GET['date'] ?? '';

if ($isStudent) {
    // Apply category filter
    if ($categoryFilter && $categoryFilter !== 'all') {
        $filteredAnnouncements = array_filter($filteredAnnouncements, function($ann) use ($categoryFilter) {
            return $ann['category'] === $categoryFilter;
        });
    }
    
    // Apply search filter
    if ($searchFilter) {
        $searchLower = strtolower($searchFilter);
        $filteredAnnouncements = array_filter($filteredAnnouncements, function($ann) use ($searchLower) {
            $titleMatch = stripos($ann['title'], $searchLower) !== false;
            $contentMatch = stripos($ann['content'], $searchLower) !== false;
            return $titleMatch || $contentMatch;
        });
    }
    
    // Apply date filter
    if ($dateFilter) {
        $filteredAnnouncements = array_filter($filteredAnnouncements, function($ann) use ($dateFilter) {
            $createdDate = strtotime($ann['created_at']);
            $now = time();
            
            switch ($dateFilter) {
                case 'today':
                    $startOfDay = strtotime('today');
                    $endOfDay = strtotime('tomorrow') - 1;
                    return $createdDate >= $startOfDay && $createdDate <= $endOfDay;
                    
                case 'week':
                    $startOfWeek = strtotime('Monday this week');
                    return $createdDate >= $startOfWeek;
                    
                case 'month':
                    $startOfMonth = strtotime('first day of this month');
                    return $createdDate >= $startOfMonth;
                    
                case 'quarter':
                    $currentMonth = date('n');
                    $currentYear = date('Y');
                    $quarterMonth = (ceil($currentMonth / 3) - 1) * 3 + 1;
                    $startOfQuarter = strtotime($currentYear . '-' . str_pad($quarterMonth, 2, '0', STR_PAD_LEFT) . '-01');
                    return $createdDate >= $startOfQuarter;
                    
                default:
                    return true;
            }
        });
    }
}

// Get user info (different for teachers and students)
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

// Helper function to calculate time ago
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

// Sort announcements by date (newest first)
usort($announcements, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// Get current page
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - eduQuest</title>
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
        
        .user-profile {
            display: flex;
            align-items: center;
            gap: 1rem;
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
        
        /* Content Area */
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
        
        .announcements-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        /* Alert Messages */
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
        
        /* Form Section */
        .form-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
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
            min-height: 150px;
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
        
        .form-button:active {
            transform: scale(0.98);
        }
        
        /* Announcements List */
        .announcements-list {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .announcement-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            transition: box-shadow 0.2s;
            border-left: 4px solid #22c55e;
        }
        
        .announcement-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .announcement-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 0.75rem;
        }
        
        .announcement-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1f2937;
        }
        
        .announcement-meta {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
            margin-bottom: 1rem;
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
        
        .announcement-time {
            color: #6b7280;
            font-size: 0.9rem;
        }
        
        .announcement-author {
            color: #6b7280;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .announcement-content {
            color: #4b5563;
            line-height: 1.6;
            margin-bottom: 1rem;
            word-break: break-word;
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
        
        @media (max-width: 1200px) {
            .announcements-grid {
                grid-template-columns: 1fr;
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
                    <?php if ($isTeacher): ?>
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
                    <?php elseif ($isStudent): ?>
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
                    <?php endif; ?>
                </ul>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <header class="header">
                <div class="header-left">
                    <div class="header-title">Announcements</div>
                </div>
                <div class="header-right">
                    <div class="notification-icon">🔔</div>
                    <div class="user-profile" style="position: relative;">
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
                <?php if ($isTeacher): ?>
                    <h1 class="page-title">Announcements</h1>
                    
                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $messageType; ?>">
                            <?php echo $messageType === 'success' ? '✓' : '✕'; ?> <?php echo $message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="announcements-grid">
                        <!-- Post New Announcement Form -->
                        <div class="form-card">
                            <h2 class="form-section-title">📝 Post New Announcement</h2>
                            <form method="POST">
                                <div class="form-group">
                                    <label class="form-label">Announcement Title *</label>
                                    <input type="text" name="title" class="form-input" placeholder="Enter announcement title" value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Category *</label>
                                    <select name="category" class="form-select" required>
                                        <option value="">-- Select Category --</option>
                                        <option value="exam" <?php echo (($_POST['category'] ?? '') === 'exam') ? 'selected' : ''; ?>>Exam</option>
                                        <option value="event" <?php echo (($_POST['category'] ?? '') === 'event') ? 'selected' : ''; ?>>Event</option>
                                        <option value="assignment" <?php echo (($_POST['category'] ?? '') === 'assignment') ? 'selected' : ''; ?>>Assignment</option>
                                        <option value="general" <?php echo (($_POST['category'] ?? '') === 'general') ? 'selected' : ''; ?>>General</option>
                                        <option value="others" <?php echo (($_POST['category'] ?? '') === 'others') ? 'selected' : ''; ?>>Others</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Announcement Content *</label>
                                    <textarea name="content" class="form-textarea" placeholder="Write your announcement here..." required><?php echo htmlspecialchars($_POST['content'] ?? ''); ?></textarea>
                                </div>
                                
                                <button type="submit" class="form-button">📤 Post Announcement</button>
                            </form>
                        </div>
                        
                        <!-- Announcements List -->
                        <div>
                            <h2 class="form-section-title">📋 Recent Announcements</h2>
                            <?php if (count($announcements) > 0): ?>
                                <div class="announcements-list">
                                    <?php foreach (array_slice($announcements, 0, 10) as $announcement): ?>
                                        <div class="announcement-card">
                                            <div class="announcement-header">
                                                <h3 class="announcement-title"><?php echo htmlspecialchars($announcement['title']); ?></h3>
                                            </div>
                                            <div class="announcement-meta">
                                                <span class="category-badge category-<?php echo htmlspecialchars($announcement['category']); ?>">
                                                    <?php echo ucfirst(htmlspecialchars($announcement['category'])); ?>
                                                </span>
                                                <span class="role-badge role-<?php echo htmlspecialchars($announcement['author_role'] ?? 'teacher'); ?>">
                                                    <?php echo ucfirst(htmlspecialchars($announcement['author_role'] ?? 'Teacher')); ?>
                                                </span>
                                                <span class="announcement-time">
                                                    <strong>Posted:</strong> <?php echo timeAgo($announcement['created_at']); ?>
                                                </span>
                                            </div>
                                            <p class="announcement-content"><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                                            <div style="font-size: 0.85rem; color: #9ca3af;">
                                                By: <strong><?php echo htmlspecialchars($announcement['author']); ?></strong>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <div class="empty-state-icon">📭</div>
                                    <p>No announcements yet. Create your first announcement!</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                <?php elseif ($isStudent): ?>
                    <h1 class="page-title">Announcements</h1>
                    
                    <!-- Filters -->
                    <div class="form-card" style="margin-bottom: 2rem;">
                        <h2 class="form-section-title">🔍 Filter Announcements</h2>
                        <form method="GET" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
                            <div class="form-group" style="flex: 1; min-width: 200px; margin-bottom: 0;">
                                <label class="form-label">Search</label>
                                <input type="text" name="search" class="form-input" placeholder="Search announcements..." value="<?php echo htmlspecialchars($searchFilter); ?>">
                            </div>
                            
                            <div class="form-group" style="flex: 1; min-width: 180px; margin-bottom: 0;">
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
                            
                            <div class="form-group" style="flex: 1; min-width: 180px; margin-bottom: 0;">
                                <label class="form-label">Posted</label>
                                <select name="date" class="form-select">
                                    <option value="">All Time</option>
                                    <option value="today" <?php echo ($dateFilter === 'today') ? 'selected' : ''; ?>>Today</option>
                                    <option value="week" <?php echo ($dateFilter === 'week') ? 'selected' : ''; ?>>This Week</option>
                                    <option value="month" <?php echo ($dateFilter === 'month') ? 'selected' : ''; ?>>This Month</option>
                                    <option value="quarter" <?php echo ($dateFilter === 'quarter') ? 'selected' : ''; ?>>This Quarter</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="form-button" style="align-self: flex-end;">🔍 Filter</button>
                            <a href="announcements.php" class="form-button" style="background: #6b7280; align-self: flex-end; text-decoration: none; text-align: center; margin-bottom: 0;">Reset</a>
                        </form>
                    </div>
                    
                    <!-- Announcements List -->
                    <h2 class="form-section-title">📋 Announcements (<?php echo count($filteredAnnouncements); ?>)</h2>
                    <?php if (count($filteredAnnouncements) > 0): ?>
                        <div class="announcements-list">
                            <?php 
                            // Sort by date (newest first)
                            usort($filteredAnnouncements, function($a, $b) {
                                return strtotime($b['created_at']) - strtotime($a['created_at']);
                            });
                            
                            foreach ($filteredAnnouncements as $announcement): ?>
                                <div class="announcement-card">
                                    <div class="announcement-header">
                                        <h3 class="announcement-title"><?php echo htmlspecialchars($announcement['title']); ?></h3>
                                    </div>
                                    <div class="announcement-meta">
                                        <span class="category-badge category-<?php echo htmlspecialchars($announcement['category']); ?>">
                                            <?php echo ucfirst(htmlspecialchars($announcement['category'])); ?>
                                        </span>
                                        <span class="announcement-time">
                                            <strong>Posted:</strong> <?php echo timeAgo($announcement['created_at']); ?>
                                        </span>
                                    </div>
                                    <p class="announcement-content"><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                                    <div style="font-size: 0.85rem; color: #9ca3af;">
                                        By: <strong><?php echo htmlspecialchars($announcement['author']); ?></strong>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">📭</div>
                            <p>No announcements found. Try adjusting your filters.</p>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
