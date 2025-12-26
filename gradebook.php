<?php
session_start();
require_once __DIR__ . '/data_store.php';

// Require instructor login for grading queue
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header('Location: role_selection.php');
    exit;
}

$assignments = eq_load_data('assignments');
$submissions = eq_load_data('submissions');

$message = '';

// Handle grading (instructor)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'grade_submission') {
    $submission_id = (int)($_POST['submission_id'] ?? 0);
    $score = ($_POST['score'] === '') ? null : (float)$_POST['score'];
    $feedback = trim($_POST['feedback'] ?? '');

    foreach ($submissions as &$sub) {
        if ((int)$sub['id'] === $submission_id) {
            $sub['score'] = $score;
            $sub['feedback'] = $feedback;
            break;
        }
    }
    unset($sub);

    eq_save_data('submissions', $submissions);
    $message = 'Submission graded successfully.';
}

// Get instructor info
$username = $_SESSION['username'] ?? 'teacher1';
$instructorName = $username;
$instructorEmail = $username . '@gmail.com';
$initials = strtoupper(substr($username, 0, 1) . substr($username, -1));

// Course mapping
$courseNames = [
    'CS 101' => 'Web Development',
    'CS 201' => 'Database Systems',
    'CS 301' => 'Algorithms',
    'CS 401' => 'Software Engineering',
];

// Prepare submissions with assignment details
$submissionsWithDetails = [];
foreach ($submissions as $sub) {
    $assignment = null;
    foreach ($assignments as $ass) {
        if ((int)$ass['id'] === (int)$sub['assignment_id']) {
            $assignment = $ass;
            break;
        }
    }
    
    if ($assignment) {
        $isGraded = $sub['score'] !== null && $sub['score'] !== '';
        $courseCode = $assignment['course_code'] ?? 'CS ' . (100 + (int)$assignment['id']);
        
        // Generate student ID from name (simple hash)
        $studentId = 'S' . str_pad(abs(crc32($sub['student_name'])) % 100000, 5, '0', STR_PAD_LEFT);
        
        $submissionsWithDetails[] = [
            'id' => $sub['id'],
            'assignment_id' => $sub['assignment_id'],
            'assignment_title' => $assignment['title'],
            'course_code' => $courseCode,
            'student_name' => $sub['student_name'],
            'student_id' => $studentId,
            'submitted_at' => $sub['submitted_at'],
            'submitted_date' => date('n/j/Y', strtotime($sub['submitted_at'])),
            'file_name' => $sub['file_name'],
            'stored_name' => $sub['stored_name'],
            'score' => $sub['score'],
            'max_score' => $assignment['max_score'] ?? 100,
            'feedback' => $sub['feedback'],
            'is_graded' => $isGraded,
        ];
    }
}

// Sort by submitted date (newest first)
usort($submissionsWithDetails, function($a, $b) {
    return strtotime($b['submitted_at']) - strtotime($a['submitted_at']);
});

// Count pending submissions
$pendingCount = 0;
foreach ($submissionsWithDetails as $sub) {
    if (!$sub['is_graded']) {
        $pendingCount++;
    }
}

// Handle search
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
if ($searchQuery !== '') {
    $submissionsWithDetails = array_filter($submissionsWithDetails, function($sub) use ($searchQuery) {
        return stripos($sub['student_name'], $searchQuery) !== false ||
               stripos($sub['assignment_title'], $searchQuery) !== false ||
               stripos($sub['student_id'], $searchQuery) !== false;
    });
}

// Get current page for active menu highlighting
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grading Queue - eduQuest Instructor Portal</title>
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
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .page-title-section {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .page-title-section h1 {
            font-size: 1.75rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .page-subtitle {
            color: #6b7280;
            font-size: 0.95rem;
        }
        
        .pending-badge {
            background: #fed7aa;
            color: #ea580c;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        /* Search Bar */
        .search-container {
            margin-bottom: 1.5rem;
        }
        
        .search-bar {
            position: relative;
            width: 100%;
        }
        
        .search-input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.95rem;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #22c55e;
        }
        
        /* Submission Cards */
        .submissions-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .submission-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: start;
        }
        
        .submission-info {
            flex: 1;
        }
        
        .submission-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .assignment-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-right: 0.75rem;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-badge.pending {
            background: #fed7aa;
            color: #ea580c;
        }
        
        .status-badge.graded {
            background: #dcfce7;
            color: #166534;
        }
        
        .submission-details {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        
        .detail-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            color: #6b7280;
        }
        
        .detail-icon {
            width: 20px;
            text-align: center;
        }
        
        .grade-display {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
            color: #166534;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .submission-actions {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            min-width: 120px;
        }
        
        .action-btn {
            padding: 0.625rem 1rem;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            background: white;
            color: #1f2937;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .action-btn:hover {
            background: #f9fafb;
            border-color: #d1d5db;
        }
        
        .message-alert {
            background: #dbeafe;
            color: #1e40af;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 200px;
            }
            
            .main-content {
                margin-left: 200px;
            }
            
            .submission-card {
                flex-direction: column;
            }
            
            .submission-actions {
                width: 100%;
                flex-direction: row;
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
                        <div class="user-avatar"><?php echo $initials; ?></div>
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
                <!-- Page Header -->
                <div class="page-header">
                    <div class="page-title-section">
                        <div>
                            <h1>Grading Queue</h1>
                            <p class="page-subtitle"><?php echo $pendingCount; ?> submission<?php echo $pendingCount !== 1 ? 's' : ''; ?> pending review</p>
                        </div>
                        <?php if ($pendingCount > 0): ?>
                            <span class="pending-badge"><?php echo $pendingCount; ?> Pending</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($message !== ''): ?>
                    <div class="message-alert">
                        <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Search Bar -->
                <div class="search-container">
                    <form method="get" class="search-bar">
                        <input type="text" name="search" class="search-input" placeholder="Search submissions..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                    </form>
                </div>
                
                <!-- Submissions List -->
                <div class="submissions-list">
                    <?php if (empty($submissionsWithDetails)): ?>
                        <div class="submission-card">
                            <p style="color: #6b7280; text-align: center; padding: 2rem; width: 100%;">
                                <?php echo $searchQuery !== '' ? 'No submissions found matching your search.' : 'No submissions yet.'; ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($submissionsWithDetails as $sub): ?>
                            <div class="submission-card">
                                <div class="submission-info">
                                    <div class="submission-header">
                                        <div class="assignment-title"><?php echo htmlspecialchars($sub['assignment_title']); ?></div>
                                        <span class="status-badge <?php echo $sub['is_graded'] ? 'graded' : 'pending'; ?>">
                                            <?php echo $sub['is_graded'] ? 'Graded' : 'Pending'; ?>
                                        </span>
                                    </div>
                                    
                                    <div class="submission-details">
                                        <div class="detail-row">
                                            <span class="detail-icon">👤</span>
                                            <span><?php echo htmlspecialchars($sub['student_name']); ?> <?php echo htmlspecialchars($sub['student_id']); ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-icon">📅</span>
                                            <span><?php echo $sub['submitted_date']; ?> Submitted</span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-icon">📄</span>
                                            <span>1 file(s) Attached</span>
                                        </div>
                                        <?php if ($sub['is_graded']): ?>
                                            <div class="grade-display">
                                                <span>✓</span>
                                                <span>Graded: <?php echo (int)$sub['score']; ?>/<?php echo $sub['max_score']; ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="submission-actions">
                                    <?php if ($sub['is_graded']): ?>
                                        <button onclick="openGradeModal(<?php echo $sub['id']; ?>, '<?php echo htmlspecialchars($sub['student_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($sub['assignment_title'], ENT_QUOTES); ?>', <?php echo (int)$sub['score']; ?>, <?php echo $sub['max_score']; ?>, '<?php echo htmlspecialchars($sub['feedback'] ?? '', ENT_QUOTES); ?>')" class="action-btn">Review</button>
                                    <?php else: ?>
                                        <button onclick="openGradeModal(<?php echo $sub['id']; ?>, '<?php echo htmlspecialchars($sub['student_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($sub['assignment_title'], ENT_QUOTES); ?>', null, <?php echo $sub['max_score']; ?>, '')" class="action-btn">Grade</button>
                                    <?php endif; ?>
                                    <a href="download.php?file=<?php echo urlencode($sub['stored_name']); ?>" class="action-btn">
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
    
    <!-- Grade Modal -->
    <div id="gradeModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
        <div style="background:white; border-radius:12px; padding:2rem; max-width:600px; width:90%; max-height:90vh; overflow-y:auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                <h2 style="font-size:1.5rem; font-weight:600;">Grade Submission</h2>
                <button onclick="document.getElementById('gradeModal').style.display='none'" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:#6b7280;">&times;</button>
            </div>
            <div style="margin-bottom:1rem; padding:1rem; background:#f9fafb; border-radius:8px;">
                <p style="font-weight:600; margin-bottom:0.25rem;" id="modalStudentName"></p>
                <p style="color:#6b7280; font-size:0.9rem;" id="modalAssignmentTitle"></p>
            </div>
            <form method="post" id="gradeForm">
                <input type="hidden" name="action" value="grade_submission">
                <input type="hidden" name="submission_id" id="modalSubmissionId">
                <div style="display:flex; flex-direction:column; gap:1rem;">
                    <div>
                        <label style="display:block; margin-bottom:0.5rem; font-weight:500;">Score *</label>
                        <input type="number" name="score" id="modalScore" step="0.1" min="0" required style="width:100%; padding:0.75rem; border:1px solid #d1d5db; border-radius:8px;">
                        <span style="color:#6b7280; font-size:0.85rem;" id="modalMaxScore"></span>
                    </div>
                    <div>
                        <label style="display:block; margin-bottom:0.5rem; font-weight:500;">Feedback</label>
                        <textarea name="feedback" id="modalFeedback" rows="5" placeholder="Provide feedback to the student..." style="width:100%; padding:0.75rem; border:1px solid #d1d5db; border-radius:8px;"></textarea>
                    </div>
                    <div style="display:flex; gap:1rem; margin-top:1rem;">
                        <button type="submit" style="flex:1; padding:0.75rem; background:#22c55e; color:white; border:none; border-radius:8px; font-weight:500; cursor:pointer;">Save Grade</button>
                        <button type="button" onclick="document.getElementById('gradeModal').style.display='none'" style="flex:1; padding:0.75rem; background:#f3f4f6; color:#1f2937; border:none; border-radius:8px; font-weight:500; cursor:pointer;">Cancel</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openGradeModal(submissionId, studentName, assignmentTitle, currentScore, maxScore, feedback) {
            document.getElementById('modalSubmissionId').value = submissionId;
            document.getElementById('modalStudentName').textContent = studentName;
            document.getElementById('modalAssignmentTitle').textContent = assignmentTitle;
            document.getElementById('modalScore').value = currentScore !== null ? currentScore : '';
            document.getElementById('modalScore').max = maxScore;
            document.getElementById('modalMaxScore').textContent = 'out of ' + maxScore + ' points';
            document.getElementById('modalFeedback').value = feedback;
            document.getElementById('gradeModal').style.display = 'flex';
        }
        
        // Close modal when clicking outside
        document.getElementById('gradeModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                this.style.display = 'none';
            }
        });
    </script>
</body>
</html>
