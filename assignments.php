<?php
session_start();
require_once __DIR__ . '/data_store.php';

// Require login
if (!isset($_SESSION['role'])) {
    header('Location: role_selection.php');
    exit;
}
$role = $_SESSION['role'];

$assignments = eq_load_data('assignments');
$submissions = eq_load_data('submissions');

$message = '';

// Check for success message from submission
if (isset($_GET['submitted']) && $_GET['submitted'] === '1') {
    $message = 'Assignment submitted successfully!';
}

// Handle assignment creation (instructor)
if ($role === 'teacher' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_assignment') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $deadline = trim($_POST['deadline'] ?? '');
    $max_score = (int)($_POST['max_score'] ?? 100);
    $rubric = trim($_POST['rubric'] ?? '');
    $course_code = trim($_POST['course_code'] ?? 'CS 101');

    if ($title !== '' && $deadline !== '') {
        $id = eq_next_id($assignments);
        $assignments[] = [
            'id' => $id,
            'title' => $title,
            'description' => $description,
            'deadline' => $deadline,
            'max_score' => $max_score,
            'rubric' => $rubric,
            'course_code' => $course_code
        ];
        eq_save_data('assignments', $assignments);
        $message = 'Assignment created successfully.';
    } else {
        $message = 'Title and deadline are required.';
    }
}

// Handle grading (instructor)
if ($role === 'teacher' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'grade_submission') {
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
    $message = 'Submission graded.';
}

// Handle assignment submission (student)
if ($role === 'student' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_assignment') {
    $assignment_id = (int)($_POST['assignment_id'] ?? 0);
    
    // Get student info
    $username = $_SESSION['username'] ?? 'student1';
    $studentNames = [
        'student1' => ['name' => 'student1', 'email' => 'student@gmail.com', 'initials' => 'S1'],
        'student2' => ['name' => 'John Doe', 'email' => 'student2@gmail.com', 'initials' => 'JD'],
    ];
    $studentName = $studentNames[$username]['name'] ?? 'Student';
    
    // Check if assignment exists
    $assignmentExists = false;
    foreach ($assignments as $a) {
        if ((int)$a['id'] === $assignment_id) {
            $assignmentExists = true;
            break;
        }
    }
    
    if (!$assignmentExists) {
        $message = 'Assignment not found.';
    } elseif (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] === UPLOAD_ERR_OK) {
        // Check if student already submitted
        $alreadySubmitted = false;
        foreach ($submissions as $sub) {
            if ((int)$sub['assignment_id'] === $assignment_id) {
                $subStudentName = strtolower($sub['student_name'] ?? '');
                $studentNameLower = strtolower($studentName);
                if ($username === 'student1') {
                    if ($subStudentName === 'student1') {
                        $alreadySubmitted = true;
                        break;
                    }
                } else {
                    $nameParts = explode(' ', $studentNameLower);
                    if (strpos($subStudentName, strtolower($username)) !== false ||
                        strpos($subStudentName, $nameParts[0] ?? '') !== false ||
                        strpos($subStudentName, $nameParts[1] ?? '') !== false ||
                        $subStudentName === $studentNameLower) {
                        $alreadySubmitted = true;
                        break;
                    }
                }
            }
        }
        
        if ($alreadySubmitted) {
            $message = 'You have already submitted this assignment.';
        } else {
            // Handle file upload
            $uploadsDir = __DIR__ . '/uploads';
            if (!is_dir($uploadsDir)) {
                mkdir($uploadsDir, 0777, true);
            }
            
            $originalName = basename($_FILES['submission_file']['name']);
            $safeName = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $originalName);
            $targetName = 'submission_' . time() . '_' . $assignment_id . '_' . $safeName;
            $targetPath = $uploadsDir . '/' . $targetName;
            
            if (move_uploaded_file($_FILES['submission_file']['tmp_name'], $targetPath)) {
                $id = eq_next_id($submissions);
                $submissions[] = [
                    'id' => $id,
                    'assignment_id' => $assignment_id,
                    'student_name' => $studentName,
                    'file_name' => $originalName,
                    'stored_name' => $targetName,
                    'submitted_at' => date('Y-m-d H:i:s'),
                    'score' => null,
                    'feedback' => null
                ];
                eq_save_data('submissions', $submissions);
                
                // Redirect to avoid resubmission on refresh
                header('Location: assignments.php?assignment_id=' . $assignment_id . '&submitted=1');
                exit;
            } else {
                $message = 'Failed to upload file.';
            }
        }
    } else {
        $message = 'Please select a file to submit.';
    }
}

// Filter by single assignment if requested
$selected_assignment_id = isset($_GET['assignment_id']) ? (int)$_GET['assignment_id'] : 0;
$selected_assignment = null;
if ($selected_assignment_id) {
    foreach ($assignments as $a) {
        if ((int)$a['id'] === $selected_assignment_id) {
            $selected_assignment = $a;
            break;
        }
    }
}

function eq_count_submissions_for(int $assignment_id, array $submissions): int {
    $c = 0;
    foreach ($submissions as $s) {
        if ((int)$s['assignment_id'] === $assignment_id) {
            $c++;
        }
    }
    return $c;
}

// Course mapping
$courseNames = [
    'CS 101' => 'Web Development',
    'CS 201' => 'Database Systems',
    'CS 301' => 'Algorithms',
    'CS 401' => 'Software Engineering',
];

// Get instructor info (for instructor view)
$username = $_SESSION['username'] ?? 'teacher1';
$instructorName = 'Dr. ' . ucfirst($username);
$instructorEmail = $username . '@gmail.com';
$initials = strtoupper(substr($username, 0, 1) . substr($username, -1));

// Prepare assignments with statistics
$assignmentsWithStats = [];
foreach ($assignments as $a) {
    $deadlineTs = strtotime($a['deadline']);
    $nowTs = time();
    $isActive = $nowTs <= $deadlineTs;
    
    $submissionCount = eq_count_submissions_for((int)$a['id'], $submissions);
    $courseCode = $a['course_code'] ?? 'CS ' . (100 + (int)$a['id']);
    $courseName = $courseNames[$courseCode] ?? 'General';
    
    // Estimate total students (you can enhance this with actual enrollment data)
    $totalStudents = 35 + ((int)$a['id'] % 10);
    
    $progress = $totalStudents > 0 ? round(($submissionCount / $totalStudents) * 100) : 0;
    
    $assignmentsWithStats[] = [
        'id' => $a['id'],
        'title' => $a['title'],
        'deadline' => $a['deadline'],
        'deadline_formatted' => date('M j, Y', $deadlineTs),
        'max_score' => $a['max_score'] ?? 100,
        'course_code' => $courseCode,
        'course_name' => $courseName,
        'is_active' => $isActive,
        'submission_count' => $submissionCount,
        'total_students' => $totalStudents,
        'progress' => $progress,
        'description' => $a['description'] ?? '',
        'rubric' => $a['rubric'] ?? '',
    ];
}

// Sort by deadline (upcoming first)
usort($assignmentsWithStats, function($a, $b) {
    return strtotime($a['deadline']) - strtotime($b['deadline']);
});

// Student-specific data preparation
$studentAssignments = [];
if ($role === 'student') {
    $username = $_SESSION['username'] ?? 'student1';
    
    // Get student info
    $studentNames = [
        'student1' => ['name' => 'student1', 'email' => 'student@gmail.com', 'initials' => 'S1'],
        'student2' => ['name' => 'John Doe', 'email' => 'student2@gmail.com', 'initials' => 'JD'],
    ];
    
    $studentName = $studentNames[$username]['name'] ?? 'Student';
    $studentEmail = $studentNames[$username]['email'] ?? $username . '@gmail.com';
    $initials = $studentNames[$username]['initials'] ?? 'ST';
    
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
    
    // Prepare assignments for student view
    $now = time();
    foreach ($assignments as $a) {
        $deadlineTs = strtotime($a['deadline']);
        $isPastDeadline = $now > $deadlineTs;
        $courseCode = $a['course_code'] ?? 'CS ' . (100 + (int)$a['id']);
        $courseName = $courseNames[$courseCode] ?? 'General';
        
        // Check if student has submitted
        $hasSubmitted = false;
        $submissionScore = null;
        $submissionFeedback = null;
        foreach ($studentSubmissions as $sub) {
            if ((int)$sub['assignment_id'] === (int)$a['id']) {
                $hasSubmitted = true;
                $submissionScore = $sub['score'] ?? null;
                $submissionFeedback = $sub['feedback'] ?? null;
                break;
            }
        }
        
        // Calculate progress (for pending assignments)
        $progress = 0;
        if ($hasSubmitted) {
            $progress = 100;
        } else if (!$isPastDeadline) {
            $daysUntilDeadline = ($deadlineTs - $now) / (60 * 60 * 24);
            $totalDays = 14; // Assume 2 weeks for assignment
            $progress = max(0, min(100, round((($totalDays - $daysUntilDeadline) / $totalDays) * 100)));
        }
        
        // Calculate grade percentage
        $gradePercentage = null;
        if ($submissionScore !== null && $submissionScore !== '') {
            $maxScore = (float)($a['max_score'] ?? 100);
            $gradePercentage = round(((float)$submissionScore / $maxScore) * 100);
        }
        
        // Format deadline
        $deadlineFormatted = date('M j, Y g:i A', $deadlineTs);
        $deadlineShort = date('M j, Y', $deadlineTs);
        
        // Determine status
        $status = 'pending';
        if ($hasSubmitted) {
            $status = 'submitted';
        } else if ($isPastDeadline) {
            $status = 'overdue';
        }
        
        $studentAssignments[] = [
            'id' => $a['id'],
            'title' => $a['title'],
            'deadline' => $a['deadline'],
            'deadline_formatted' => $deadlineShort,
            'deadline_full' => $deadlineFormatted,
            'max_score' => $a['max_score'] ?? 100,
            'course_code' => $courseCode,
            'course_name' => $courseName,
            'has_submitted' => $hasSubmitted,
            'score' => $submissionScore,
            'grade_percentage' => $gradePercentage,
            'progress' => $progress,
            'status' => $status,
            'description' => $a['description'] ?? '',
            'rubric' => $a['rubric'] ?? '',
        ];
    }
    
    // Sort by deadline (upcoming first)
    usort($studentAssignments, function($a, $b) {
        return strtotime($a['deadline']) - strtotime($b['deadline']);
    });
}

// If viewing a specific assignment, show detail view inline
$showDetailView = $selected_assignment && ($role === 'teacher' || $role === 'student');

// Get current page for active menu highlighting
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignments - eduQuest <?php echo $role === 'student' ? 'Student' : 'Instructor'; ?> Portal</title>
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
            background: <?php echo $role === 'student' ? '#1e3a8a' : 'white'; ?>;
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
            background: <?php echo $role === 'student' ? '#3b82f6' : '#22c55e'; ?>;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }
        
        .logo-text {
            color: <?php echo $role === 'student' ? 'white' : '#1f2937'; ?>;
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
            color: <?php echo $role === 'student' ? '#cbd5e1' : '#6b7280'; ?>;
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .nav-link:hover {
            background: <?php echo $role === 'student' ? '#1e40af' : '#f9fafb'; ?>;
            color: <?php echo $role === 'student' ? 'white' : '#1f2937'; ?>;
        }
        
        .nav-link.active {
            background: <?php echo $role === 'student' ? '#3b82f6' : '#22c55e'; ?>;
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
        
        .notification-dot {
            position: absolute;
            top: -2px;
            right: -2px;
            width: 8px;
            height: 8px;
            background: #ef4444;
            border-radius: 50%;
            border: 2px solid white;
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
            background: <?php echo $role === 'student' ? '#3b82f6' : '#22c55e'; ?>;
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
            margin-bottom: 2rem;
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
        
        .create-assignment-btn {
            background: #22c55e;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            border: none;
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: background 0.2s;
        }
        
        .create-assignment-btn:hover {
            background: #16a34a;
        }
        
        /* Assignment Cards */
        .assignments-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        /* Student Grid View */
        .assignments-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }
        
        .assignment-card-student {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .assignment-card-student:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .assignment-header-student {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .assignment-icon-student {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            background: #3b82f6;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
        }
        
        .assignment-info-student h3 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: #1f2937;
        }
        
        .assignment-info-student p {
            font-size: 0.85rem;
            color: #6b7280;
        }
        
        .assignment-stats-student {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .stat-item-student {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .stat-icon-student {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }
        
        .stat-icon-student.pending {
            background: #fef3c7;
            color: #f59e0b;
        }
        
        .stat-icon-student.grade {
            background: #dbeafe;
            color: #2563eb;
        }
        
        .stat-text-student {
            display: flex;
            flex-direction: column;
        }
        
        .stat-value-student {
            font-weight: 600;
            font-size: 0.95rem;
            color: #1f2937;
        }
        
        .stat-label-student {
            font-size: 0.75rem;
            color: #6b7280;
        }
        
        .assignment-progress-student {
            margin-bottom: 1.5rem;
        }
        
        .progress-header-student {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        
        .progress-label-student {
            font-size: 0.85rem;
            font-weight: 500;
            color: #6b7280;
        }
        
        .progress-percentage-student {
            font-size: 0.85rem;
            font-weight: 600;
            color: #1f2937;
        }
        
        .progress-bar-student {
            width: 100%;
            height: 8px;
            background: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-fill-student {
            height: 100%;
            background: #3b82f6;
            border-radius: 4px;
            transition: width 0.3s;
        }
        
        .assignment-actions-student {
            display: flex;
            gap: 0.75rem;
        }
        
        .assignment-btn-student {
            flex: 1;
            padding: 0.75rem;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            background: white;
            color: #1f2937;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            text-align: center;
        }
        
        .assignment-btn-student:hover {
            background: #f9fafb;
            border-color: #d1d5db;
        }
        
        .assignment-btn-student.primary {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }
        
        .assignment-btn-student.primary:hover {
            background: #2563eb;
        }
        
        .submit-assignment-btn {
            width: 100%;
            padding: 0.75rem 1.5rem;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .submit-assignment-btn:hover {
            background: #2563eb;
        }
        
        @media (max-width: 968px) {
            .assignments-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .assignment-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            position: relative;
        }
        
        .assignment-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }
        
        .assignment-title-section {
            flex: 1;
        }
        
        .assignment-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .assignment-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-badge.active {
            background: #dcfce7;
            color: #166534;
        }
        
        .status-badge.closed {
            background: #f3f4f6;
            color: #6b7280;
        }
        
        .course-name {
            color: #6b7280;
            font-size: 0.9rem;
        }
        
        .assignment-menu {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            cursor: pointer;
            color: #9ca3af;
            font-size: 1.2rem;
        }
        
        .assignment-details {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .detail-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }
        
        .detail-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }
        
        .detail-icon.calendar {
            background: #dbeafe;
            color: #2563eb;
        }
        
        .detail-icon.people {
            background: #fef3c7;
            color: #f59e0b;
        }
        
        .detail-icon.points {
            background: #e0e7ff;
            color: #6366f1;
        }
        
        .detail-text {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-size: 0.75rem;
            color: #6b7280;
        }
        
        .detail-value {
            font-weight: 600;
            color: #1f2937;
        }
        
        .progress-section {
            margin-bottom: 1rem;
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 0.5rem;
        }
        
        .progress-fill {
            height: 100%;
            background: #22c55e;
            border-radius: 4px;
            transition: width 0.3s;
        }
        
        .progress-text {
            font-size: 0.85rem;
            color: #6b7280;
        }
        
        .assignment-actions {
            display: flex;
            justify-content: flex-end;
        }
        
        .view-submissions-btn {
            padding: 0.625rem 1.25rem;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            background: white;
            color: #1f2937;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .view-submissions-btn:hover {
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
        
        @media (max-width: 968px) {
            .assignment-details {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 200px;
            }
            
            .main-content {
                margin-left: 200px;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
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
                    <?php if ($role === 'student'): ?>
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
                        <li class="nav-item">
                            <a href="announcements.php" class="nav-link <?php echo ($currentPage === 'announcements.php') ? 'active' : ''; ?>">
                                <span class="nav-icon">📢</span>
                                <span>Announcements</span>
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
                    <?php endif; ?>
                </ul>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <header class="header">
                <div class="header-left">
                    <div class="header-title">eduQuest <?php echo $role === 'student' ? 'Student' : 'Instructor'; ?> Portal</div>
                </div>
                <div class="header-right">
                    <div class="notification-icon">
                        🔔
                        <span class="notification-dot"></span>
                    </div>
                    <div class="user-profile" style="position: relative;">
                        <div class="user-avatar"><?php echo $role === 'student' ? ($initials ?? 'ST') : $initials; ?></div>
                        <div class="user-info">
                            <div class="user-name"><?php echo $role === 'student' ? ($studentName ?? 'Student') : $instructorName; ?></div>
                            <div class="user-email"><?php echo $role === 'student' ? ($studentEmail ?? 'student@gmail.com') : $instructorEmail; ?></div>
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
                        <h1>Assignments</h1>
                        <p class="page-subtitle"><?php echo $role === 'student' ? 'View your course assignments' : 'Create and manage course assignments'; ?></p>
                    </div>
                    <?php if ($role === 'teacher'): ?>
                        <button onclick="document.getElementById('createModal').style.display='block'" class="create-assignment-btn">
                            <span>+</span>
                            <span>Create Assignment</span>
                        </button>
                    <?php endif; ?>
                </div>
                
                <?php if ($message !== ''): ?>
                    <div class="message-alert">
                        <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($showDetailView): ?>
                    <!-- Back Button -->
                    <div style="margin-bottom: 1.5rem;">
                        <a href="assignments.php" style="display: inline-flex; align-items: center; gap: 0.5rem; color: #6b7280; text-decoration: none; font-size: 0.9rem;">
                            <span>←</span>
                            <span>Back to Assignments</span>
                        </a>
                    </div>
                    
                    <!-- Assignment Detail View -->
                    <?php
                    $subsForAssignment = array_filter($submissions, function ($s) use ($selected_assignment) {
                        return (int)$s['assignment_id'] === (int)$selected_assignment['id'];
                    });
                    $courseCode = $selected_assignment['course_code'] ?? 'CS ' . (100 + (int)$selected_assignment['id']);
                    $courseName = $courseNames[$courseCode] ?? 'General';
                    ?>
                    <div class="assignment-card" style="margin-bottom: 2rem;">
                        <div class="assignment-title"><?php echo htmlspecialchars($selected_assignment['title']); ?></div>
                        <div class="course-name" style="margin-top: 0.5rem; margin-bottom: 1rem;"><?php echo htmlspecialchars($courseCode . ' - ' . $courseName); ?></div>
                        <div style="color: #6b7280; margin-bottom: 1.5rem;">
                            <p><strong>Deadline:</strong> <?php echo htmlspecialchars($selected_assignment['deadline']); ?></p>
                            <p><strong>Max Score:</strong> <?php echo (int)($selected_assignment['max_score'] ?? 100); ?></p>
                            <?php if (!empty($selected_assignment['description'])): ?>
                                <p style="margin-top: 1rem;"><strong>Description:</strong></p>
                                <p><?php echo nl2br(htmlspecialchars($selected_assignment['description'])); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($selected_assignment['rubric'])): ?>
                                <p style="margin-top: 1rem;"><strong>Rubric:</strong></p>
                                <p><?php echo nl2br(htmlspecialchars($selected_assignment['rubric'])); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Submissions Table (Instructor) or Student Submission (Student) -->
                    <?php if ($role === 'teacher'): ?>
                    <div class="assignment-card">
                        <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1.5rem;">Submissions</h2>
                        <?php if (empty($subsForAssignment)): ?>
                            <p style="color: #6b7280; text-align: center; padding: 2rem;">No submissions yet.</p>
                        <?php else: ?>
                            <div style="overflow-x: auto;">
                                <table style="width: 100%; border-collapse: collapse;">
                                    <thead>
                                        <tr style="background: #f9fafb; border-bottom: 1px solid #e5e7eb;">
                                            <th style="padding: 0.75rem; text-align: left; font-weight: 600; font-size: 0.9rem;">Student</th>
                                            <th style="padding: 0.75rem; text-align: left; font-weight: 600; font-size: 0.9rem;">Submitted At</th>
                                            <th style="padding: 0.75rem; text-align: left; font-weight: 600; font-size: 0.9rem;">File</th>
                                            <th style="padding: 0.75rem; text-align: left; font-weight: 600; font-size: 0.9rem;">Score</th>
                                            <th style="padding: 0.75rem; text-align: left; font-weight: 600; font-size: 0.9rem;">Feedback</th>
                                            <th style="padding: 0.75rem; text-align: left; font-weight: 600; font-size: 0.9rem;">Grade</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($subsForAssignment as $s): ?>
                                            <tr style="border-bottom: 1px solid #f3f4f6;">
                                                <td style="padding: 0.75rem; font-size: 0.9rem;"><?php echo htmlspecialchars($s['student_name']); ?></td>
                                                <td style="padding: 0.75rem; font-size: 0.9rem; color: #6b7280;"><?php echo htmlspecialchars($s['submitted_at']); ?></td>
                                                <td style="padding: 0.75rem;">
                                                    <a href="download.php?file=<?php echo urlencode($s['stored_name']); ?>" style="color: #2563eb; text-decoration: none;">
                                                        <?php echo htmlspecialchars($s['file_name']); ?>
                                                    </a>
                                                </td>
                                                <td style="padding: 0.75rem; font-size: 0.9rem;">
                                                    <?php
                                                    if ($s['score'] === null || $s['score'] === '') {
                                                        echo '<span style="color: #6b7280;">Not graded</span>';
                                                    } else {
                                                        echo htmlspecialchars((string)$s['score']) . ' / ' . (int)($selected_assignment['max_score'] ?? 100);
                                                    }
                                                    ?>
                                                </td>
                                                <td style="padding: 0.75rem; font-size: 0.9rem; color: #6b7280;">
                                                    <?php
                                                    if ($s['feedback'] === null || $s['feedback'] === '') {
                                                        echo '-';
                                                    } else {
                                                        echo nl2br(htmlspecialchars($s['feedback']));
                                                    }
                                                    ?>
                                                </td>
                                                <td style="padding: 0.75rem;">
                                                    <form method="post" style="display: flex; flex-direction: column; gap: 0.5rem; min-width: 200px;">
                                                        <input type="hidden" name="action" value="grade_submission">
                                                        <input type="hidden" name="submission_id" value="<?php echo (int)$s['id']; ?>">
                                                        <input type="number" name="score" step="0.1" min="0" max="<?php echo (int)($selected_assignment['max_score'] ?? 100); ?>"
                                                               value="<?php echo $s['score'] !== null ? htmlspecialchars((string)$s['score']) : ''; ?>"
                                                               placeholder="Score" style="padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.9rem;">
                                                        <textarea name="feedback" placeholder="Feedback" rows="2" style="padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.9rem; resize: vertical;"><?php echo $s['feedback'] !== null ? htmlspecialchars($s['feedback']) : ''; ?></textarea>
                                                        <button type="submit" style="padding: 0.5rem; background: #22c55e; color: white; border: none; border-radius: 6px; font-size: 0.85rem; font-weight: 500; cursor: pointer;">Save</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                        <!-- Student Submission View -->
                        <?php
                        // Find student's submission for this assignment
                        $studentSubmission = null;
                        if ($role === 'student') {
                            $username = $_SESSION['username'] ?? 'student1';
                            $studentNames = [
                                'student1' => ['name' => 'student1', 'email' => 'student@gmail.com', 'initials' => 'S1'],
                                'student2' => ['name' => 'John Doe', 'email' => 'student2@gmail.com', 'initials' => 'JD'],
                            ];
                            $studentName = $studentNames[$username]['name'] ?? 'Student';
                            
                            // Get student's submissions
                            $studentSubs = [];
                            if ($username === 'student1') {
                                $studentSubs = array_filter($submissions, function($sub) {
                                    $subStudentName = strtolower($sub['student_name'] ?? '');
                                    return $subStudentName === 'student1';
                                });
                            } else {
                                $studentSubs = array_filter($submissions, function($sub) use ($username, $studentName) {
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
                            
                            foreach ($studentSubs as $sub) {
                                if ((int)$sub['assignment_id'] === (int)$selected_assignment['id']) {
                                    $studentSubmission = $sub;
                                    break;
                                }
                            }
                        }
                        ?>
                        <div class="assignment-card" style="margin-bottom: 2rem;">
                            <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1.5rem;">Your Submission</h2>
                            <?php if ($studentSubmission): ?>
                                <div style="padding: 1rem; background: #f9fafb; border-radius: 8px; margin-bottom: 1rem;">
                                    <p style="margin-bottom: 0.5rem;"><strong>Submitted At:</strong> <?php echo htmlspecialchars($studentSubmission['submitted_at']); ?></p>
                                    <p style="margin-bottom: 0.5rem;"><strong>File:</strong> 
                                        <a href="download.php?file=<?php echo urlencode($studentSubmission['stored_name']); ?>" style="color: #2563eb; text-decoration: none;">
                                            <?php echo htmlspecialchars($studentSubmission['file_name']); ?>
                                        </a>
                                    </p>
                                    <?php if ($studentSubmission['score'] !== null && $studentSubmission['score'] !== ''): ?>
                                        <p style="margin-bottom: 0.5rem;"><strong>Score:</strong> <?php echo htmlspecialchars((string)$studentSubmission['score']); ?> / <?php echo (int)($selected_assignment['max_score'] ?? 100); ?></p>
                                        <p style="margin-bottom: 0.5rem;"><strong>Grade:</strong> <?php 
                                            $maxScore = (float)($selected_assignment['max_score'] ?? 100);
                                            $percentage = round(((float)$studentSubmission['score'] / $maxScore) * 100);
                                            echo $percentage . '%';
                                        ?></p>
                                    <?php else: ?>
                                        <p style="color: #6b7280;">Not graded yet.</p>
                                    <?php endif; ?>
                                    <?php if (!empty($studentSubmission['feedback'])): ?>
                                        <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e5e7eb;">
                                            <p style="margin-bottom: 0.5rem;"><strong>Feedback:</strong></p>
                                            <p><?php echo nl2br(htmlspecialchars($studentSubmission['feedback'])); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div style="padding: 1.5rem; background: #f9fafb; border-radius: 8px; border: 2px dashed #d1d5db;">
                                    <p style="color: #6b7280; margin-bottom: 1.5rem; text-align: center;">You haven't submitted this assignment yet.</p>
                                    <form method="post" enctype="multipart/form-data" style="max-width: 500px; margin: 0 auto;">
                                        <input type="hidden" name="action" value="submit_assignment">
                                        <input type="hidden" name="assignment_id" value="<?php echo (int)$selected_assignment['id']; ?>">
                                        <div style="margin-bottom: 1rem;">
                                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: #1f2937;">Select File to Submit:</label>
                                            <input type="file" name="submission_file" required accept=".pdf,.doc,.docx,.zip,.rar,.txt,.sql,.db" style="width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 8px; background: white; cursor: pointer;">
                                            <p style="font-size: 0.75rem; color: #6b7280; margin-top: 0.25rem;">Accepted formats: PDF, DOC, DOCX, ZIP, RAR, TXT, SQL, DB</p>
                                        </div>
                                        <button type="submit" class="submit-assignment-btn">
                                            Submit Assignment
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <?php if ($role === 'student'): ?>
                        <!-- Student Assignments Grid -->
                        <div class="assignments-grid">
                            <?php if (empty($studentAssignments)): ?>
                                <div class="assignment-card-student" style="grid-column: 1 / -1;">
                                    <p style="color: #6b7280; text-align: center; padding: 2rem;">No assignments available.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($studentAssignments as $ass): ?>
                                    <div class="assignment-card-student">
                                        <div class="assignment-header-student">
                                            <div class="assignment-icon-student">📄</div>
                                            <div class="assignment-info-student">
                                                <h3><?php echo htmlspecialchars($ass['title']); ?></h3>
                                                <p><?php echo htmlspecialchars($ass['course_code'] . ' - ' . $ass['course_name']); ?></p>
                                            </div>
                                        </div>
                                        
                                        <div class="assignment-stats-student">
                                            <div class="stat-item-student">
                                                <div class="stat-icon-student pending">📄</div>
                                                <div class="stat-text-student">
                                                    <span class="stat-value-student">
                                                        <?php 
                                                        if ($ass['status'] === 'overdue') {
                                                            echo 'Overdue';
                                                        } else if ($ass['has_submitted']) {
                                                            echo 'Submitted';
                                                        } else {
                                                            echo 'Pending';
                                                        }
                                                        ?>
                                                    </span>
                                                    <span class="stat-label-student">Status</span>
                                                </div>
                                            </div>
                                            <div class="stat-item-student">
                                                <div class="stat-icon-student grade">🏆</div>
                                                <div class="stat-text-student">
                                                    <span class="stat-value-student">
                                                        <?php 
                                                        if ($ass['grade_percentage'] !== null) {
                                                            echo $ass['grade_percentage'] . '%';
                                                        } else {
                                                            echo 'N/A';
                                                        }
                                                        ?>
                                                    </span>
                                                    <span class="stat-label-student">Current Grade</span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="assignment-progress-student">
                                            <div class="progress-header-student">
                                                <span class="progress-label-student">Progress</span>
                                                <span class="progress-percentage-student"><?php echo $ass['progress']; ?>%</span>
                                            </div>
                                            <div class="progress-bar-student">
                                                <div class="progress-fill-student" style="width: <?php echo $ass['progress']; ?>%;"></div>
                                            </div>
                                        </div>
                                        
                                        <div style="margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid #f3f4f6;">
                                            <p style="font-size: 0.85rem; color: #6b7280; margin-bottom: 0.25rem;">Due Date:</p>
                                            <p style="font-size: 0.9rem; font-weight: 500; color: #1f2937;"><?php echo htmlspecialchars($ass['deadline_formatted']); ?></p>
                                        </div>
                                        
                                        <div class="assignment-actions-student">
                                            <a href="assignments.php?assignment_id=<?php echo (int)$ass['id']; ?>" class="assignment-btn-student primary">View Assignment</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <!-- Instructor Assignments List -->
                        <div class="assignments-list">
                            <?php if (empty($assignmentsWithStats)): ?>
                                <div class="assignment-card">
                                    <p style="color: #6b7280; text-align: center; padding: 2rem;">No assignments created yet. Click "Create Assignment" to get started.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($assignmentsWithStats as $ass): ?>
                                    <div class="assignment-card">
                                        <div class="assignment-menu">⋮</div>
                                        
                                        <div class="assignment-header">
                                            <div class="assignment-title-section">
                                                <div class="assignment-title"><?php echo htmlspecialchars($ass['title']); ?></div>
                                                <div class="assignment-meta">
                                                    <span class="status-badge <?php echo $ass['is_active'] ? 'active' : 'closed'; ?>">
                                                        <?php echo $ass['is_active'] ? 'Active' : 'Closed'; ?>
                                                    </span>
                                                    <span class="course-name"><?php echo htmlspecialchars($ass['course_code'] . ' - ' . $ass['course_name']); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="assignment-details">
                                            <div class="detail-item">
                                                <div class="detail-icon calendar">📅</div>
                                                <div class="detail-text">
                                                    <span class="detail-label">Due Date</span>
                                                    <span class="detail-value"><?php echo $ass['deadline_formatted']; ?></span>
                                                </div>
                                            </div>
                                            <div class="detail-item">
                                                <div class="detail-icon people">👥</div>
                                                <div class="detail-text">
                                                    <span class="detail-label">Submissions</span>
                                                    <span class="detail-value"><?php echo $ass['submission_count']; ?>/<?php echo $ass['total_students']; ?></span>
                                                </div>
                                            </div>
                                            <div class="detail-item">
                                                <div class="detail-icon points">📄</div>
                                                <div class="detail-text">
                                                    <span class="detail-label">Points</span>
                                                    <span class="detail-value"><?php echo $ass['max_score']; ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="progress-section">
                                            <div class="progress-bar">
                                                <div class="progress-fill" style="width: <?php echo $ass['progress']; ?>%;"></div>
                                            </div>
                                            <div class="progress-text"><?php echo $ass['progress']; ?>% submitted</div>
                                        </div>
                                        
                                        <div class="assignment-actions">
                                            <a href="assignments.php?assignment_id=<?php echo (int)$ass['id']; ?>" class="view-submissions-btn">View Submissions</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Create Assignment Modal -->
    <?php if ($role === 'teacher'): ?>
    <div id="createModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
        <div style="background:white; border-radius:12px; padding:2rem; max-width:600px; width:90%; max-height:90vh; overflow-y:auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                <h2 style="font-size:1.5rem; font-weight:600;">Create New Assignment</h2>
                <button onclick="document.getElementById('createModal').style.display='none'" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:#6b7280;">&times;</button>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="create_assignment">
                <div style="display:flex; flex-direction:column; gap:1rem;">
                    <div>
                        <label style="display:block; margin-bottom:0.5rem; font-weight:500;">Title *</label>
                        <input type="text" name="title" required style="width:100%; padding:0.75rem; border:1px solid #d1d5db; border-radius:8px;">
                    </div>
                    <div>
                        <label style="display:block; margin-bottom:0.5rem; font-weight:500;">Course</label>
                        <select name="course_code" style="width:100%; padding:0.75rem; border:1px solid #d1d5db; border-radius:8px;">
                            <option value="CS 101">CS 101 - Web Development</option>
                            <option value="CS 201">CS 201 - Database Systems</option>
                            <option value="CS 301">CS 301 - Algorithms</option>
                            <option value="CS 401">CS 401 - Software Engineering</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block; margin-bottom:0.5rem; font-weight:500;">Deadline *</label>
                        <input type="datetime-local" name="deadline" required style="width:100%; padding:0.75rem; border:1px solid #d1d5db; border-radius:8px;">
                    </div>
                    <div>
                        <label style="display:block; margin-bottom:0.5rem; font-weight:500;">Maximum Score</label>
                        <input type="number" name="max_score" value="100" min="1" style="width:100%; padding:0.75rem; border:1px solid #d1d5db; border-radius:8px;">
                    </div>
                    <div>
                        <label style="display:block; margin-bottom:0.5rem; font-weight:500;">Description / Instructions</label>
                        <textarea name="description" rows="4" style="width:100%; padding:0.75rem; border:1px solid #d1d5db; border-radius:8px;"></textarea>
                    </div>
                    <div>
                        <label style="display:block; margin-bottom:0.5rem; font-weight:500;">Rubric (assessment criteria)</label>
                        <textarea name="rubric" rows="3" placeholder="Criteria 1, Criteria 2, ..." style="width:100%; padding:0.75rem; border:1px solid #d1d5db; border-radius:8px;"></textarea>
                    </div>
                    <div style="display:flex; gap:1rem; margin-top:1rem;">
                        <button type="submit" style="flex:1; padding:0.75rem; background:#22c55e; color:white; border:none; border-radius:8px; font-weight:500; cursor:pointer;">Create Assignment</button>
                        <button type="button" onclick="document.getElementById('createModal').style.display='none'" style="flex:1; padding:0.75rem; background:#f3f4f6; color:#1f2937; border:none; border-radius:8px; font-weight:500; cursor:pointer;">Cancel</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <script>
        // Close modal when clicking outside
        document.getElementById('createModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                this.style.display = 'none';
            }
        });
    </script>
</body>
</html>
