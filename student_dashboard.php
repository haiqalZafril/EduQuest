<?php
session_start();
require_once __DIR__ . '/data_store.php';

// Require student login
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header('Location: role_selection.php');
    exit;
}

// Load data
$assignments = eq_load_data('assignments');
$submissions = eq_load_data('submissions');

// Get student info
$username = $_SESSION['username'] ?? 'student1';
$studentName = ucfirst($username); // Default name based on username
$studentEmail = $username . '@gmail.com';
$initials = 'AM'; // Default initials

// Map username to student name
$studentNames = [
    'student1' => ['name' => 'student1', 'email' => 'student@gmail.com', 'initials' => 'S1'],
    'student2' => ['name' => 'John Doe', 'email' => 'student2@gmail.com', 'initials' => 'JD'],
];

if (isset($studentNames[$username])) {
    $studentName = $studentNames[$username]['name'];
    $studentEmail = $studentNames[$username]['email'];
    $initials = $studentNames[$username]['initials'];
} else {
    // Generate from username
    $parts = explode(' ', ucwords(str_replace(['student', '_'], ['', ' '], $username)));
    if (count($parts) >= 2) {
        $studentName = $parts[0] . ' ' . $parts[1];
        $initials = strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
    } else {
        $studentName = ucfirst($username) . ' Student';
        $initials = strtoupper(substr($username, 0, 2));
    }
}

// Course mapping
$courseNames = [
    'CS 101' => 'Web Development',
    'CS 201' => 'Database Systems',
    'CS 301' => 'Algorithms',
    'CS 401' => 'Software Engineering',
];

// Get student's submissions - match by student name or username
// For demo purposes, we'll match any submission if username is student1
// In a real system, you'd have a proper student ID or email matching
$studentSubmissions = [];
if ($username === 'student1') {
    // For student1, match submissions with "student1" in the name
    $studentSubmissions = array_filter($submissions, function($sub) {
        $subStudentName = strtolower($sub['student_name'] ?? '');
        return $subStudentName === 'student1';
    });
} else {
    // For other students, try to match by username or any partial match
    $studentSubmissions = array_filter($submissions, function($sub) use ($username, $studentName) {
        $subStudentName = strtolower($sub['student_name'] ?? '');
        $usernameLower = strtolower($username);
        $studentNameLower = strtolower($studentName);
        $nameParts = explode(' ', $studentNameLower);
        
        // Match if username appears in student name or vice versa
        return strpos($subStudentName, $usernameLower) !== false ||
               strpos($subStudentName, $nameParts[0] ?? '') !== false ||
               strpos($subStudentName, $nameParts[1] ?? '') !== false ||
               $subStudentName === $studentNameLower;
    });
}

// Get unique courses from assignments
$activeCourses = [];
foreach ($assignments as $ass) {
    $courseCode = $ass['course_code'] ?? 'CS ' . (100 + (int)$ass['id']);
    if (!in_array($courseCode, $activeCourses)) {
        $activeCourses[] = $courseCode;
    }
}
$activeCoursesCount = count($activeCourses);

// Get pending assignments (not submitted yet, deadline not passed)
$pendingAssignments = [];
$now = time();
foreach ($assignments as $ass) {
    $deadlineTs = strtotime($ass['deadline']);
    $isPastDeadline = $now > $deadlineTs;
    
    // Check if student has submitted
    $hasSubmitted = false;
    foreach ($studentSubmissions as $sub) {
        if ((int)$sub['assignment_id'] === (int)$ass['id']) {
            $hasSubmitted = true;
            break;
        }
    }
    
    if (!$hasSubmitted && !$isPastDeadline) {
        $pendingAssignments[] = $ass;
    }
}
$pendingCount = count($pendingAssignments);

// Get completed assignments (submitted)
$completedCount = count($studentSubmissions);

// Calculate average grade
$totalScore = 0;
$totalMaxScore = 0;
$gradedCount = 0;
foreach ($studentSubmissions as $sub) {
    if ($sub['score'] !== null && $sub['score'] !== '') {
        foreach ($assignments as $ass) {
            if ((int)$ass['id'] === (int)$sub['assignment_id']) {
                $totalScore += (float)$sub['score'];
                $totalMaxScore += (float)($ass['max_score'] ?? 100);
                $gradedCount++;
                break;
            }
        }
    }
}
$averageGrade = $gradedCount > 0 ? round(($totalScore / $totalMaxScore) * 100) : 0;

// Get upcoming assignments (next 3, sorted by deadline)
$upcomingAssignments = [];
foreach ($assignments as $ass) {
    $deadlineTs = strtotime($ass['deadline']);
    $isPastDeadline = $now > $deadlineTs;
    
    if (!$isPastDeadline) {
        // Check if student has submitted
        $hasSubmitted = false;
        $submissionProgress = 0;
        foreach ($studentSubmissions as $sub) {
            if ((int)$sub['assignment_id'] === (int)$ass['id']) {
                $hasSubmitted = true;
                $submissionProgress = 100; // Completed
                break;
            }
        }
        
        // Estimate progress (for demo, use random or based on days until deadline)
        if (!$hasSubmitted) {
            $daysUntilDeadline = ($deadlineTs - $now) / (60 * 60 * 24);
            $totalDays = 14; // Assume 2 weeks for assignment
            $submissionProgress = max(0, min(100, round((($totalDays - $daysUntilDeadline) / $totalDays) * 100)));
        }
        
        $courseCode = $ass['course_code'] ?? 'CS ' . (100 + (int)$ass['id']);
        $upcomingAssignments[] = [
            'id' => $ass['id'],
            'title' => $ass['title'],
            'course_code' => $courseCode,
            'course_name' => $courseNames[$courseCode] ?? 'General',
            'deadline' => date('n/j/Y', $deadlineTs),
            'deadline_ts' => $deadlineTs,
            'progress' => $submissionProgress,
            'has_submitted' => $hasSubmitted,
        ];
    }
}

// Sort by deadline
usort($upcomingAssignments, function($a, $b) {
    return $a['deadline_ts'] - $b['deadline_ts'];
});

// Get only next 3
$upcomingAssignments = array_slice($upcomingAssignments, 0, 3);

// Get recent grades (last 4 graded submissions)
$recentGrades = [];
foreach ($studentSubmissions as $sub) {
    if ($sub['score'] !== null && $sub['score'] !== '') {
        foreach ($assignments as $ass) {
            if ((int)$ass['id'] === (int)$sub['assignment_id']) {
                $courseCode = $ass['course_code'] ?? 'CS ' . (100 + (int)$ass['id']);
                $score = (float)$sub['score'];
                $maxScore = (float)($ass['max_score'] ?? 100);
                $percentage = round(($score / $maxScore) * 100);
                
                $recentGrades[] = [
                    'title' => $ass['title'],
                    'course_code' => $courseCode,
                    'course_name' => $courseNames[$courseCode] ?? 'General',
                    'score' => $score,
                    'max_score' => $maxScore,
                    'percentage' => $percentage,
                    'submitted_at' => $sub['submitted_at'],
                    'timestamp' => strtotime($sub['submitted_at']),
                ];
                break;
            }
        }
    }
}

// Sort by submission date (newest first)
usort($recentGrades, function($a, $b) {
    return $b['timestamp'] - $a['timestamp'];
});

// Get only last 4
$recentGrades = array_slice($recentGrades, 0, 4);

// Calculate grade trend (compare with previous average)
$gradeTrend = [];
if (count($recentGrades) >= 2) {
    $latest = $recentGrades[0]['percentage'];
    $previous = $recentGrades[1]['percentage'];
    if ($latest > $previous) {
        $gradeTrend[$recentGrades[0]['title']] = 'up';
    } elseif ($latest < $previous) {
        $gradeTrend[$recentGrades[0]['title']] = 'down';
    } else {
        $gradeTrend[$recentGrades[0]['title']] = 'stable';
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
    <title>eduQuest Student Portal</title>
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
            background: #3b82f6;
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
            background: #3b82f6;
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
            position: relative;
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
            background: #3b82f6;
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
            font-size: 1.75rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .welcome-subtitle {
            color: #6b7280;
            font-size: 0.95rem;
        }
        
        /* Stats Cards */
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
            display: flex;
            align-items: center;
            gap: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
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
        
        .stat-icon.blue {
            background: #dbeafe;
            color: #2563eb;
        }
        
        .stat-icon.orange {
            background: #fed7aa;
            color: #ea580c;
        }
        
        .stat-icon.green {
            background: #dcfce7;
            color: #16a34a;
        }
        
        .stat-icon.purple {
            background: #e9d5ff;
            color: #9333ea;
        }
        
        .stat-content {
            flex: 1;
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            font-size: 0.85rem;
            color: #6b7280;
        }
        
        /* Section */
        .section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }
        
        /* Assignment Item */
        .assignment-item {
            padding: 1rem 0;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .assignment-item:last-child {
            border-bottom: none;
        }
        
        .assignment-header-row {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 0.75rem;
        }
        
        .assignment-title-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .assignment-title {
            font-weight: 600;
            font-size: 1rem;
        }
        
        .warning-icon {
            color: #ef4444;
            font-size: 1.1rem;
        }
        
        .assignment-meta {
            display: flex;
            gap: 1rem;
            color: #6b7280;
            font-size: 0.9rem;
            margin-bottom: 0.75rem;
        }
        
        .progress-container {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .progress-bar {
            flex: 1;
            height: 8px;
            background: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: #3b82f6;
            border-radius: 4px;
            transition: width 0.3s;
        }
        
        .progress-text {
            font-size: 0.85rem;
            color: #6b7280;
            min-width: 40px;
        }
        
        /* Grade Item */
        .grade-item {
            padding: 1rem 0;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .grade-item:last-child {
            border-bottom: none;
        }
        
        .grade-header-row {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 0.5rem;
        }
        
        .grade-title {
            font-weight: 600;
            font-size: 1rem;
        }
        
        .grade-meta {
            color: #6b7280;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        
        .grade-score {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .grade-percentage {
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        .trend-icon {
            font-size: 0.9rem;
        }
        
        .trend-up {
            color: #16a34a;
        }
        
        .trend-down {
            color: #ef4444;
        }
        
        .trend-stable {
            color: #6b7280;
        }
        
        /* Quick Actions */
        .quick-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .action-btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            border: none;
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s;
        }
        
        .action-btn.primary {
            background: #3b82f6;
            color: white;
        }
        
        .action-btn.primary:hover {
            background: #2563eb;
        }
        
        .action-btn.secondary {
            background: white;
            color: #1f2937;
            border: 1px solid #e5e7eb;
        }
        
        .action-btn.secondary:hover {
            background: #f9fafb;
            border-color: #d1d5db;
        }
        
        @media (max-width: 1200px) {
            .stats-grid {
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
                        <a href="student_dashboard.php" class="nav-link <?php echo ($currentPage === 'student_dashboard.php') ? 'active' : ''; ?>">
                            <span class="nav-icon">☰</span>
                            <span>Overview</span>
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
                </ul>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <header class="header">
                <div class="header-left">
                    <div class="header-title">eduQuest Student Portal</div>
                </div>
                <div class="header-right">
                    <div class="notification-icon">🔔</div>
                    <div class="user-profile">
                        <div class="user-avatar"><?php echo htmlspecialchars($initials); ?></div>
                        <div class="user-info">
                            <div class="user-name"><?php echo htmlspecialchars($studentName); ?></div>
                            <div class="user-email"><?php echo htmlspecialchars($studentEmail); ?></div>
                        </div>
                    </div>
                    <a href="logout.php" style="margin-left: 1rem; padding: 0.5rem 1rem; background: #ef4444; color: white; text-decoration: none; border-radius: 6px; font-size: 0.85rem;">Logout</a>
                </div>
            </header>
            
            <!-- Content Area -->
            <div class="content-area">
                <!-- Welcome Section -->
                <div class="welcome-section">
                    <h1 class="welcome-title">Welcome back, <?php echo htmlspecialchars(explode(' ', $studentName)[0]); ?>!</h1>
                    <p class="welcome-subtitle">Here's your academic overview</p>
                </div>
                
                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue">📄</div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo $activeCoursesCount; ?></div>
                            <div class="stat-label">Active Courses</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon orange">⏰</div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo $pendingCount; ?></div>
                            <div class="stat-label">Pending Assignments</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green">✓</div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo $completedCount; ?></div>
                            <div class="stat-label">Completed</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon purple">🏆</div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo $averageGrade; ?>%</div>
                            <div class="stat-label">Average Grade</div>
                        </div>
                    </div>
                </div>
                
                <!-- Upcoming Assignments -->
                <div class="section">
                    <h2 class="section-title">Upcoming Assignments</h2>
                    <?php if (empty($upcomingAssignments)): ?>
                        <p style="color: #6b7280; text-align: center; padding: 2rem;">No upcoming assignments.</p>
                    <?php else: ?>
                        <?php foreach ($upcomingAssignments as $ass): ?>
                            <div class="assignment-item">
                                <div class="assignment-header-row">
                                    <div class="assignment-title-row">
                                        <div class="assignment-title"><?php echo htmlspecialchars($ass['title']); ?></div>
                                        <?php if ($ass['progress'] < 50 && !$ass['has_submitted']): ?>
                                            <span class="warning-icon">⚠️</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="assignment-meta">
                                    <span>Course: <?php echo htmlspecialchars($ass['course_code']); ?></span>
                                    <span>Due: <?php echo htmlspecialchars($ass['deadline']); ?></span>
                                </div>
                                <div class="progress-container">
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $ass['progress']; ?>%;"></div>
                                    </div>
                                    <div class="progress-text"><?php echo $ass['progress']; ?>%</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Recent Grades -->
                <div class="section">
                    <h2 class="section-title">Recent Grades</h2>
                    <?php if (empty($recentGrades)): ?>
                        <p style="color: #6b7280; text-align: center; padding: 2rem;">No grades yet.</p>
                    <?php else: ?>
                        <?php foreach ($recentGrades as $grade): ?>
                            <div class="grade-item">
                                <div class="grade-header-row">
                                    <div class="grade-title"><?php echo htmlspecialchars($grade['title']); ?></div>
                                    <div class="grade-score">
                                        <div class="grade-percentage"><?php echo $grade['percentage']; ?>%</div>
                                        <?php
                                        $trend = isset($gradeTrend[$grade['title']]) ? $gradeTrend[$grade['title']] : 'stable';
                                        $trendClass = $trend === 'up' ? 'trend-up' : ($trend === 'down' ? 'trend-down' : 'trend-stable');
                                        $trendIcon = $trend === 'up' ? '↑' : ($trend === 'down' ? '↓' : '→');
                                        ?>
                                        <span class="trend-icon <?php echo $trendClass; ?>"><?php echo $trendIcon; ?></span>
                                    </div>
                                </div>
                                <div class="grade-meta">
                                    <span>Course: <?php echo htmlspecialchars($grade['course_code']); ?></span>
                                </div>
                                <div class="grade-score">
                                    <span style="color: #6b7280; font-size: 0.9rem;">Score: <?php echo (int)$grade['score']; ?>/<?php echo (int)$grade['max_score']; ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Quick Actions -->
                <div class="section">
                    <h2 class="section-title">Quick Actions</h2>
                    <div class="quick-actions">
                        <a href="assignments.php" class="action-btn primary">View Assignments</a>
                        <a href="notes.php" class="action-btn secondary">Access Notes</a>
                        <a href="student_grades.php" class="action-btn secondary">Check Grades</a>
                        <a href="my_courses.php" class="action-btn secondary">My Courses</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

