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
$studentName = ucfirst(str_replace('student', '', $username)) . ' Martinez';
$studentEmail = $username . '@gmail.com';
$initials = 'AM';

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

// Get student's submissions
$studentSubmissions = [];
if ($username === 'student1') {
    $studentSubmissions = array_filter($submissions, function($sub) {
        $subStudentName = strtolower($sub['student_name'] ?? '');
        return $subStudentName === 'student1';
    });
} else {
    $studentSubmissions = array_filter($submissions, function($sub) use ($username, $studentName) {
        $subStudentName = strtolower($sub['student_name'] ?? '');
        $usernameLower = strtolower($username);
        $studentNameLower = strtolower($studentName);
        $nameParts = explode(' ', $studentNameLower);
        return strpos($subStudentName, $usernameLower) !== false ||
               strpos($subStudentName, $nameParts[0] ?? '') !== false ||
               strpos($subStudentName, $nameParts[1] ?? '') !== false ||
               $subStudentName === $studentNameLower;
    });
}

// Group grades by course
$courseGrades = [];
$allGradedSubmissions = [];

foreach ($studentSubmissions as $sub) {
    if ($sub['score'] !== null && $sub['score'] !== '') {
        foreach ($assignments as $ass) {
            if ((int)$ass['id'] === (int)$sub['assignment_id']) {
                $courseCode = $ass['course_code'] ?? 'CS ' . (100 + (int)$ass['id']);
                $score = (float)$sub['score'];
                $maxScore = (float)($ass['max_score'] ?? 100);
                $percentage = round(($score / $maxScore) * 100);
                
                if (!isset($courseGrades[$courseCode])) {
                    $courseGrades[$courseCode] = [
                        'course_code' => $courseCode,
                        'course_name' => $courseNames[$courseCode] ?? 'General',
                        'assignments' => [],
                        'total_score' => 0,
                        'total_max_score' => 0,
                    ];
                }
                
                $courseGrades[$courseCode]['assignments'][] = [
                    'title' => $ass['title'],
                    'score' => $score,
                    'max_score' => $maxScore,
                    'percentage' => $percentage,
                ];
                
                $courseGrades[$courseCode]['total_score'] += $score;
                $courseGrades[$courseCode]['total_max_score'] += $maxScore;
                
                $allGradedSubmissions[] = [
                    'score' => $score,
                    'max_score' => $maxScore,
                ];
                break;
            }
        }
    }
}

// Calculate course averages
foreach ($courseGrades as &$course) {
    if ($course['total_max_score'] > 0) {
        $course['average'] = round(($course['total_score'] / $course['total_max_score']) * 100);
    } else {
        $course['average'] = 0;
    }
}
unset($course);

// Calculate overall GPA
$overallTotalScore = 0;
$overallTotalMaxScore = 0;
foreach ($allGradedSubmissions as $sub) {
    $overallTotalScore += $sub['score'];
    $overallTotalMaxScore += $sub['max_score'];
}

$overallGPA = $overallTotalMaxScore > 0 ? round(($overallTotalScore / $overallTotalMaxScore) * 100, 1) : 0;

// Get letter grade
function getLetterGrade($percentage) {
    if ($percentage >= 90) return 'A';
    if ($percentage >= 80) return 'B';
    if ($percentage >= 70) return 'C';
    if ($percentage >= 60) return 'D';
    return 'F';
}

$overallLetterGrade = getLetterGrade($overallGPA);

// Get current page for active menu highlighting
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grades - eduQuest Student Portal</title>
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
        
        /* Overall GPA Section */
        .gpa-section {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .gpa-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #6b7280;
            margin-bottom: 1.5rem;
        }
        
        .gpa-content {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        
        .gpa-icon {
            width: 60px;
            height: 60px;
            background: #9333ea;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
            flex-shrink: 0;
        }
        
        .gpa-main {
            flex: 1;
        }
        
        .gpa-value {
            font-size: 2.5rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.25rem;
        }
        
        .gpa-trend {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #16a34a;
            font-size: 0.95rem;
            font-weight: 500;
        }
        
        .trend-arrow {
            font-size: 1.2rem;
        }
        
        /* Course Section */
        .course-section {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .course-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1.5rem;
        }
        
        .course-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1f2937;
        }
        
        .course-grade {
            text-align: right;
        }
        
        .course-grade-value {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .course-grade-value.grade-a {
            color: #16a34a;
        }
        
        .course-grade-value.grade-b {
            color: #3b82f6;
        }
        
        .course-grade-value.grade-c {
            color: #f59e0b;
        }
        
        .course-grade-letter {
            font-size: 0.95rem;
            color: #6b7280;
        }
        
        .course-progress-bar {
            width: 100%;
            height: 12px;
            background: #e5e7eb;
            border-radius: 6px;
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        
        .course-progress-fill {
            height: 100%;
            background: #4b5563;
            border-radius: 6px;
            transition: width 0.3s;
        }
        
        .assignment-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .assignment-item:last-child {
            border-bottom: none;
        }
        
        .assignment-name {
            flex: 1;
            font-weight: 500;
            color: #1f2937;
        }
        
        .assignment-progress {
            flex: 1;
            height: 8px;
            background: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .assignment-progress-fill {
            height: 100%;
            background: #4b5563;
            border-radius: 4px;
        }
        
        .assignment-score {
            font-weight: 600;
            font-size: 0.95rem;
            min-width: 60px;
            text-align: right;
        }
        
        .assignment-score.grade-a {
            color: #16a34a;
        }
        
        .assignment-score.grade-b {
            color: #3b82f6;
        }
        
        .assignment-score.grade-c {
            color: #6b7280;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 200px;
            }
            
            .main-content {
                margin-left: 200px;
            }
            
            .gpa-content {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .course-header {
                flex-direction: column;
                gap: 1rem;
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
                <!-- Overall Grade Point Average -->
                <div class="gpa-section">
                    <div class="gpa-title">Overall Grade Point Average</div>
                    <div class="gpa-content">
                        <div class="gpa-icon">🎖️</div>
                        <div class="gpa-main">
                            <div class="gpa-value"><?php echo $overallGPA; ?>% (<?php echo $overallLetterGrade; ?>)</div>
                        </div>
                    </div>
                </div>
                
                <!-- Course Grades -->
                <?php if (empty($courseGrades)): ?>
                    <div class="course-section">
                        <p style="color: #6b7280; text-align: center; padding: 2rem;">No grades yet.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($courseGrades as $course): 
                        $letterGrade = getLetterGrade($course['average']);
                        $gradeClass = 'grade-' . strtolower($letterGrade);
                    ?>
                        <div class="course-section">
                            <div class="course-header">
                                <div class="course-title"><?php echo htmlspecialchars($course['course_code']); ?> - <?php echo htmlspecialchars($course['course_name']); ?></div>
                                <div class="course-grade">
                                    <div class="course-grade-value <?php echo $gradeClass; ?>"><?php echo $course['average']; ?>%</div>
                                    <div class="course-grade-letter">Grade: <?php echo $letterGrade; ?></div>
                                </div>
                            </div>
                            
                            <div class="course-progress-bar">
                                <div class="course-progress-fill" style="width: <?php echo $course['average']; ?>%;"></div>
                            </div>
                            
                            <?php foreach ($course['assignments'] as $assignment): 
                                $assignmentGradeClass = 'grade-' . strtolower(getLetterGrade($assignment['percentage']));
                            ?>
                                <div class="assignment-item">
                                    <div class="assignment-name"><?php echo htmlspecialchars($assignment['title']); ?></div>
                                    <div class="assignment-progress">
                                        <div class="assignment-progress-fill" style="width: <?php echo $assignment['percentage']; ?>%;"></div>
                                    </div>
                                    <div class="assignment-score <?php echo $assignmentGradeClass; ?>">
                                        <?php echo (int)$assignment['score']; ?>/<?php echo (int)$assignment['max_score']; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>

