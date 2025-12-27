<?php
session_start();
require_once __DIR__ . '/data_store.php';

// Require instructor login
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header('Location: role_selection.php');
    exit;
}

// Load data
$assignments = eq_load_data('assignments');
$submissions = eq_load_data('submissions');
$notes = eq_load_data('notes');

// Calculate statistics
$activeCourses = count(array_unique(array_column($assignments, 'id')));
$totalStudents = count(array_unique(array_column($submissions, 'student_name')));
$pendingGrading = 0;
$dueToday = 0;
$today = date('Y-m-d');

foreach ($submissions as $sub) {
    if ($sub['score'] === null || $sub['score'] === '') {
        $pendingGrading++;
        // Check if assignment deadline is today
        foreach ($assignments as $ass) {
            if ((int)$ass['id'] === (int)$sub['assignment_id']) {
                $deadlineDate = date('Y-m-d', strtotime($ass['deadline']));
                if ($deadlineDate === $today) {
                    $dueToday++;
                    break;
                }
            }
        }
    }
}

// Get instructor info
$username = $_SESSION['username'] ?? 'teacher1';
$user = eq_get_user($username);

if ($user) {
    $instructorName = $user['name'] ?? $username;
    $instructorEmail = $user['email'] ?? ($username . '@gmail.com');
    $instructorAvatar = $user['avatar'] ?? '';
} else {
    $instructorName = $username;
    $instructorEmail = $username . '@gmail.com';
    $instructorAvatar = '';
}

// Generate initials from name
$nameParts = explode(' ', $instructorName);
if (count($nameParts) >= 2) {
    $initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
} else {
    $initials = strtoupper(substr($instructorName, 0, 2));
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

// Generate recent activity from actual data
$recentActivity = [];
$activities = [];

// Add submission activities
foreach ($submissions as $sub) {
    if (isset($sub['submitted_at'])) {
        $assignment = null;
        foreach ($assignments as $ass) {
            if ((int)$ass['id'] === (int)$sub['assignment_id']) {
                $assignment = $ass;
                break;
            }
        }
        
        if ($assignment) {
            $courseCode = $assignment['course_code'] ?? 'CS ' . (100 + (int)$sub['assignment_id']);
            $activities[] = [
                'type' => ($sub['score'] !== null && $sub['score'] !== '') ? 'graded' : 'submission',
                'course' => $courseCode,
                'student' => $sub['student_name'] ?? 'Unknown Student',
                'timestamp' => strtotime($sub['submitted_at']),
                'datetime' => $sub['submitted_at']
            ];
        }
    }
}

// Add note upload activities
foreach ($notes as $note) {
    if (isset($note['created_at'])) {
        $activities[] = [
            'type' => 'note',
            'course' => $note['course_code'] ?? 'CS 101',
            'student' => null,
            'title' => $note['title'] ?? 'Note',
            'timestamp' => strtotime($note['created_at']),
            'datetime' => $note['created_at']
        ];
    }
}

// Sort activities by timestamp (most recent first)
usort($activities, function($a, $b) {
    return $b['timestamp'] - $a['timestamp'];
});

// Take only the 10 most recent and format them
foreach (array_slice($activities, 0, 10) as $activity) {
    $recentActivity[] = [
        'type' => $activity['type'],
        'course' => $activity['course'],
        'student' => $activity['student'],
        'title' => $activity['title'] ?? null,
        'time' => timeAgo($activity['datetime']),
        'timestamp' => $activity['timestamp']
    ];
}

// Upcoming deadlines
$upcomingDeadlines = [];
foreach ($assignments as $ass) {
    $deadline = strtotime($ass['deadline']);
    $now = time();
    if ($deadline >= $now) {
        $upcomingDeadlines[] = [
            'title' => $ass['title'],
            'course' => 'CS ' . (100 + (int)$ass['id']),
            'deadline' => $ass['deadline'],
            'formatted' => date('M j, Y', $deadline),
            'time' => date('g:i A', $deadline),
        ];
    }
}
// Sort by deadline
usort($upcomingDeadlines, function($a, $b) {
    return strtotime($a['deadline']) - strtotime($b['deadline']);
});
$upcomingDeadlines = array_slice($upcomingDeadlines, 0, 4);

// Format deadline display
foreach ($upcomingDeadlines as &$deadline) {
    $deadlineTime = strtotime($deadline['deadline']);
    $todayStart = strtotime('today');
    $tomorrowStart = strtotime('tomorrow');
    
    if ($deadlineTime >= $todayStart && $deadlineTime < $tomorrowStart) {
        $deadline['display'] = 'Today, ' . $deadline['time'];
    } elseif ($deadlineTime >= $tomorrowStart && $deadlineTime < strtotime('+2 days')) {
        $deadline['display'] = 'Tomorrow, ' . $deadline['time'];
    } else {
        $deadline['display'] = $deadline['formatted'] . ', ' . $deadline['time'];
    }
}
unset($deadline);

// Get current page for active menu highlighting
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>eduQuest Instructor Portal</title>
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
        
        .user-profile {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #22c55e;
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
        
        .welcome-section {
            margin-bottom: 2rem;
        }
        
        .welcome-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .welcome-subtitle {
            color: #6b7280;
        }
        
        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            position: relative;
        }
        
        .stat-label-row {
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }
        
        .stat-info {
            position: relative;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #e5e7eb;
            color: #374151;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            cursor: help;
            flex-shrink: 0;
        }
        
        .stat-tooltip {
            display: none;
            position: absolute;
            top: 125%;
            left: 0;
            width: 320px;
            background: #111827;
            color: white;
            padding: 0.9rem;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.25);
            z-index: 10;
            font-size: 0.85rem;
            line-height: 1.35;
        }
        
        .stat-tooltip::after {
            content: '';
            position: absolute;
            top: -6px;
            left: 12px;
            border-width: 6px;
            border-style: solid;
            border-color: transparent transparent #111827 transparent;
        }
        
        .stat-info:hover .stat-tooltip,
        .stat-info:focus-within .stat-tooltip {
            display: block;
        }
        
        .stat-tooltip .tooltip-title {
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        
        .stat-tooltip .tooltip-list {
            margin: 0.35rem 0;
            padding-left: 1rem;
        }
        
        .stat-tooltip .tooltip-list li {
            margin-bottom: 0.15rem;
        }
        
        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .stat-icon.doc { background: #dbeafe; color: #2563eb; }
        .stat-icon.users { background: #e0e7ff; color: #6366f1; }
        .stat-icon.check { background: #fef3c7; color: #f59e0b; }
        .stat-icon.clock { background: #d1fae5; color: #10b981; }
        
        .trend-icon {
            color: #22c55e;
            font-size: 1.2rem;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            color: #6b7280;
            font-size: 0.9rem;
        }
        
        .stat-change {
            font-size: 0.85rem;
            color: #6b7280;
            margin-top: 0.5rem;
        }
        
        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .content-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .activity-list {
            list-style: none;
        }
        
        .activity-item {
            display: flex;
            align-items: start;
            gap: 0.75rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #3b82f6;
            margin-top: 0.5rem;
            flex-shrink: 0;
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-text {
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }
        
        .activity-course {
            font-weight: 600;
            color: #1f2937;
        }
        
        .activity-time {
            font-size: 0.85rem;
            color: #6b7280;
        }
        
        .deadline-list {
            list-style: none;
        }
        
        .deadline-item {
            display: flex;
            align-items: start;
            gap: 0.75rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .deadline-item:last-child {
            border-bottom: none;
        }
        
        .deadline-icon {
            width: 20px;
            height: 20px;
            border-radius: 4px;
            background: #fed7aa;
            color: #ea580c;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            flex-shrink: 0;
            margin-top: 0.1rem;
        }
        
        .deadline-content {
            flex: 1;
        }
        
        .deadline-text {
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }
        
        .deadline-course {
            font-weight: 600;
            color: #1f2937;
        }
        
        .deadline-time {
            font-size: 0.85rem;
            color: #6b7280;
        }
        
        /* Quick Actions */
        .quick-actions {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
        }
        
        .action-btn {
            padding: 0.875rem 1rem;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            background: white;
            color: #1f2937;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: block;
            text-align: center;
        }
        
        .action-btn:hover {
            background: #f9fafb;
            border-color: #d1d5db;
        }
        
        .action-btn.primary {
            background: #22c55e;
            color: white;
            border-color: #22c55e;
        }
        
        .action-btn.primary:hover {
            background: #16a34a;
        }
        
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .actions-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 200px;
            }
            
            .main-content {
                margin-left: 200px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .actions-grid {
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
                </ul>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <header class="header">
                <div class="header-left">
                    <div class="header-title">eduQuest Instructor Portal</div>
                </div>
                <div class="header-right">
                    <div class="user-profile" style="position: relative;">
                        <div class="user-avatar" onclick="window.location.href='teacher_profile.php';" title="My Profile" style="cursor:pointer">
                            <?php if (!empty($instructorAvatar) && file_exists(__DIR__ . '/' . $instructorAvatar)): ?>
                                <img src="<?php echo htmlspecialchars($instructorAvatar); ?>" alt="avatar" style="width:100%; height:100%; object-fit:cover; border-radius:50%;" />
                            <?php else: ?>
                                <?php echo $initials; ?>
                            <?php endif; ?>
                        </div>
                        <div class="user-info">
                            <div class="user-name"><?php echo $instructorName; ?></div>
                            <div class="user-email"><?php echo $instructorEmail; ?></div>
                        </div>
                        <a href="logout.php" style="margin-left: 1rem; padding: 0.5rem 1rem; background: #ef4444; color: white; text-decoration: none; border-radius: 6px; font-size: 0.85rem;">Logout</a>
                    </div>
                </div>
            </header>
            
            <!-- Content Area -->
            <div class="content-area">
                <!-- Welcome Section -->
                <div class="welcome-section">
                    <h1 class="welcome-title">Welcome back, <?php echo $instructorName; ?>!</h1>
                    <p class="welcome-subtitle">Here's what's happening with your courses today.</p>
                </div>
                
                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon doc">📝</div>
                            <div class="trend-icon">📈</div>
                        </div>
                        <div class="stat-value"><?php echo $activeCourses; ?></div>
                        <div class="stat-label">Active Courses</div>
                        <div class="stat-change">+2 this semester</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon users">👥</div>
                            <div class="trend-icon">📈</div>
                        </div>
                        <div class="stat-value"><?php echo $totalStudents; ?></div>
                        <div class="stat-label">Total Students</div>
                        <div class="stat-change">+12 this month</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-header">
                            <div class="stat-icon check">✓</div>
                            <div class="trend-icon">📈</div>
                        </div>
                        <div class="stat-value"><?php echo $pendingGrading; ?></div>
                        <div class="stat-label">Pending Grading</div>
                        <div class="stat-change"><?php echo $dueToday; ?> due today</div>
                    </div>
                    
                    <!-- Avg Response Time card removed as per requirements -->
                </div>
                
                <!-- Content Grid -->
                <div class="content-grid">
                    <!-- Recent Activity -->
                    <div class="content-card">
                        <h2 class="card-title">Recent Activity</h2>
                        <div id="activity-container">
                            <ul class="activity-list" id="activity-list">
                                <?php if (empty($recentActivity)): ?>
                                    <li class="activity-item">
                                        <div class="activity-content">
                                            <div class="activity-text" style="color: #6b7280; font-style: italic;">
                                                No recent activity
                                            </div>
                                        </div>
                                    </li>
                                <?php else: ?>
                                    <?php foreach ($recentActivity as $activity): ?>
                                        <li class="activity-item">
                                            <div class="activity-dot"></div>
                                            <div class="activity-content">
                                                <div class="activity-text">
                                                    <?php
                                                    if ($activity['type'] === 'submission') {
                                                        echo 'New assignment submission';
                                                    } elseif ($activity['type'] === 'graded') {
                                                        echo 'Assignment graded';
                                                    } elseif ($activity['type'] === 'note') {
                                                        echo 'Note uploaded: ' . htmlspecialchars($activity['title'] ?? 'Untitled');
                                                    } elseif ($activity['type'] === 'file') {
                                                        echo 'File uploaded: ' . htmlspecialchars($activity['title'] ?? 'Untitled');
                                                    }
                                                    ?>
                                                    <?php if ($activity['student']): ?>
                                                        for <span class="activity-course"><?php echo htmlspecialchars($activity['course']); ?> • <?php echo htmlspecialchars($activity['student']); ?></span>
                                                    <?php else: ?>
                                                        for <span class="activity-course"><?php echo htmlspecialchars($activity['course']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="activity-time"><?php echo htmlspecialchars($activity['time']); ?></div>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Upcoming Deadlines -->
                    <div class="content-card">
                        <h2 class="card-title">Upcoming Deadlines</h2>
                        <ul class="deadline-list">
                            <?php if (empty($upcomingDeadlines)): ?>
                                <li class="deadline-item">
                                    <div class="deadline-content">
                                        <div class="deadline-text">No upcoming deadlines</div>
                                    </div>
                                </li>
                            <?php else: ?>
                                <?php foreach ($upcomingDeadlines as $deadline): ?>
                                    <li class="deadline-item">
                                        <div class="deadline-icon">!</div>
                                        <div class="deadline-content">
                                            <div class="deadline-text">
                                                <?php echo htmlspecialchars($deadline['title']); ?> for <span class="deadline-course"><?php echo $deadline['course']; ?></span>
                                            </div>
                                            <div class="deadline-time"><?php echo $deadline['display']; ?></div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="quick-actions">
                    <h2 class="card-title">Quick Actions</h2>
                    <div class="actions-grid">
                        <a href="assignments.php" class="action-btn primary">Create Assignment</a>
                        <a href="notes.php" class="action-btn">Upload Notes</a>
                        <a href="gradebook.php" class="action-btn">Grade Submissions</a>
                        <a href="#" class="action-btn">Send Announcement</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Real-time activity updates
        function updateActivityList() {
            fetch('teacher_activity_api.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    const activityList = document.getElementById('activity-list');
                    if (!activityList) return;
                    
                    // Handle error response
                    if (data.error) {
                        console.error('API Error:', data.error);
                        return;
                    }
                    
                    if (!data.activities || data.activities.length === 0) {
                        activityList.innerHTML = '<li class="activity-item"><div class="activity-content"><div class="activity-text" style="color: #6b7280; font-style: italic;">No recent activity</div></div></li>';
                        return;
                    }
                    
                    let html = '';
                    data.activities.forEach(activity => {
                        let activityText = '';
                        if (activity.type === 'submission') {
                            activityText = 'New assignment submission';
                        } else if (activity.type === 'graded') {
                            activityText = 'Assignment graded';
                        } else if (activity.type === 'note') {
                            activityText = 'Note uploaded: ' + (activity.title || 'Untitled');
                        } else if (activity.type === 'file') {
                            activityText = 'File uploaded: ' + (activity.title || 'Untitled');
                        }
                        
                        let studentPart = '';
                        if (activity.student) {
                            studentPart = ' for <span class="activity-course">' + escapeHtml(activity.course) + ' • ' + escapeHtml(activity.student) + '</span>';
                        } else {
                            studentPart = ' for <span class="activity-course">' + escapeHtml(activity.course) + '</span>';
                        }
                        
                        html += `
                            <li class="activity-item">
                                <div class="activity-dot"></div>
                                <div class="activity-content">
                                    <div class="activity-text">${activityText}${studentPart}</div>
                                    <div class="activity-time">${escapeHtml(activity.time)}</div>
                                </div>
                            </li>
                        `;
                    });
                    
                    activityList.innerHTML = html;
                })
                .catch(error => {
                    console.error('Error fetching activity:', error);
                });
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Update activity list every 30 seconds
        updateActivityList();
        setInterval(updateActivityList, 30000);
    </script>
</body>
</html>

