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

// Optional course filter (used from My Courses "Manage" links)
$filter_course_code = isset($_GET['course_code']) ? trim($_GET['course_code']) : null;

$message = '';

// Check for success message from submission
if (isset($_GET['submitted']) && $_GET['submitted'] === '1') {
    $message = 'Assignment submitted successfully!';
} elseif (isset($_GET['updated']) && $_GET['updated'] === '1') {
    $message = 'Submission updated successfully!';
}

// Handle assignment update (instructor)
if ($role === 'teacher' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_assignment') {
    $assignment_id = (int)($_POST['assignment_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $deadline = trim($_POST['deadline'] ?? '');
    $max_score = (int)($_POST['max_score'] ?? 100);
    $rubric = trim($_POST['rubric'] ?? '');
    $course_code = trim($_POST['course_code'] ?? 'CS 101');

    if ($assignment_id > 0 && $title !== '' && $deadline !== '') {
        $assignmentFound = false;
        
        // Find and update the assignment
        foreach ($assignments as $index => &$assignment) {
            if ((int)$assignment['id'] === $assignment_id) {
                $assignmentFound = true;
                
                // Preserve existing files if no new files are uploaded
                $existingFiles = isset($assignment['files']) && is_array($assignment['files']) ? $assignment['files'] : [];
                
                // Process uploaded files
                $newFiles = [];
                $uploadsDir = __DIR__ . '/uploads';
                if (!is_dir($uploadsDir)) {
                    mkdir($uploadsDir, 0777, true);
                }
                
                // Handle file uploads
                if (isset($_FILES['assignment_files']) && !empty($_FILES['assignment_files']['name'])) {
                    // Handle both single and multiple file uploads
                    $fileNames = $_FILES['assignment_files']['name'];
                    if (!is_array($fileNames)) {
                        // Single file upload - convert to array format
                        $fileNames = [$fileNames];
                        $tmpNames = [$_FILES['assignment_files']['tmp_name']];
                        $errors = [$_FILES['assignment_files']['error']];
                        $sizes = [$_FILES['assignment_files']['size']];
                    } else {
                        // Multiple file uploads
                        $tmpNames = $_FILES['assignment_files']['tmp_name'];
                        $errors = $_FILES['assignment_files']['error'];
                        $sizes = $_FILES['assignment_files']['size'];
                    }
                    
                    $fileCount = count($fileNames);
                    for ($i = 0; $i < $fileCount; $i++) {
                        if (isset($errors[$i]) && $errors[$i] === UPLOAD_ERR_OK && !empty($fileNames[$i])) {
                            // Check file size (5MB limit)
                            if (isset($sizes[$i]) && $sizes[$i] > 5 * 1024 * 1024) {
                                continue; // Skip files that are too large
                            }
                            
                            $originalName = basename($fileNames[$i]);
                            $safeName = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $originalName);
                            $targetName = 'assignment_' . time() . '_' . $i . '_' . $safeName;
                            $targetPath = $uploadsDir . '/' . $targetName;
                            
                            if (isset($tmpNames[$i]) && move_uploaded_file($tmpNames[$i], $targetPath)) {
                                $newFiles[] = [
                                    'original_name' => $originalName,
                                    'stored_name' => $targetName,
                                    'size' => filesize($targetPath)
                                ];
                            }
                        }
                    }
                }
                
                // Combine existing files with new files
                $assignmentFiles = array_merge($existingFiles, $newFiles);
                
                // Update assignment fields
                $assignment['title'] = $title;
                $assignment['description'] = $description;
                $assignment['deadline'] = $deadline;
                $assignment['max_score'] = $max_score;
                $assignment['rubric'] = $rubric;
                $assignment['course_code'] = $course_code;
                $assignment['files'] = $assignmentFiles;
                
                break;
            }
        }
        unset($assignment);
        
        if ($assignmentFound) {
            eq_save_data('assignments', $assignments);
            $message = 'Assignment updated successfully.';
        } else {
            $message = 'Assignment not found.';
        }
    } else {
        $message = 'Title and deadline are required.';
    }
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
        // Process uploaded files
        $assignmentFiles = [];
        $uploadsDir = __DIR__ . '/uploads';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0777, true);
        }
        
        // Handle file uploads
        if (isset($_FILES['assignment_files']) && !empty($_FILES['assignment_files']['name'])) {
            // Handle both single and multiple file uploads
            $fileNames = $_FILES['assignment_files']['name'];
            if (!is_array($fileNames)) {
                // Single file upload - convert to array format
                $fileNames = [$fileNames];
                $tmpNames = [$_FILES['assignment_files']['tmp_name']];
                $errors = [$_FILES['assignment_files']['error']];
                $sizes = [$_FILES['assignment_files']['size']];
            } else {
                // Multiple file uploads
                $tmpNames = $_FILES['assignment_files']['tmp_name'];
                $errors = $_FILES['assignment_files']['error'];
                $sizes = $_FILES['assignment_files']['size'];
            }
            
            $fileCount = count($fileNames);
            for ($i = 0; $i < $fileCount; $i++) {
                if (isset($errors[$i]) && $errors[$i] === UPLOAD_ERR_OK && !empty($fileNames[$i])) {
                    // Check file size (5MB limit)
                    if (isset($sizes[$i]) && $sizes[$i] > 5 * 1024 * 1024) {
                        continue; // Skip files that are too large
                    }
                    
                    $originalName = basename($fileNames[$i]);
                    $safeName = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $originalName);
                    $targetName = 'assignment_' . time() . '_' . $i . '_' . $safeName;
                    $targetPath = $uploadsDir . '/' . $targetName;
                    
                    if (isset($tmpNames[$i]) && move_uploaded_file($tmpNames[$i], $targetPath)) {
                        $assignmentFiles[] = [
                            'original_name' => $originalName,
                            'stored_name' => $targetName,
                            'size' => filesize($targetPath)
                        ];
                    }
                }
            }
        }
        
        $id = eq_next_id($assignments);
        $assignments[] = [
            'id' => $id,
            'title' => $title,
            'description' => $description,
            'deadline' => $deadline,
            'max_score' => $max_score,
            'rubric' => $rubric,
            'course_code' => $course_code,
            'files' => $assignmentFiles
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

// Handle deleting an assignment (instructor or admin)
if (($role === 'teacher' || $role === 'admin') && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_assignment') {
    $assignment_id = (int)($_POST['assignment_id'] ?? 0);
    
    if ($assignment_id > 0) {
        $assignmentFound = false;
        
        // Find and remove the assignment
        foreach ($assignments as $index => $assignment) {
            if ((int)$assignment['id'] === $assignment_id) {
                $assignmentFound = true;
                
                // Delete assignment files if they exist
                if (isset($assignment['files']) && is_array($assignment['files'])) {
                    foreach ($assignment['files'] as $file) {
                        if (!empty($file['stored_name'])) {
                            $filePath = __DIR__ . '/uploads/' . $file['stored_name'];
                            if (file_exists($filePath)) {
                                @unlink($filePath);
                            }
                        }
                    }
                }
                
                // Delete related submissions and their files
                $submissionsToDelete = [];
                foreach ($submissions as $subIndex => $submission) {
                    if ((int)$submission['assignment_id'] === $assignment_id) {
                        // Delete submission file if exists
                        if (!empty($submission['stored_name'])) {
                            $filePath = __DIR__ . '/uploads/' . $submission['stored_name'];
                            if (file_exists($filePath)) {
                                @unlink($filePath);
                            }
                        }
                        $submissionsToDelete[] = $subIndex;
                    }
                }
                
                // Remove submissions from array (in reverse order to maintain indices)
                foreach (array_reverse($submissionsToDelete) as $subIndex) {
                    unset($submissions[$subIndex]);
                }
                
                // Remove assignment from array
                unset($assignments[$index]);
                break;
            }
        }
        
        if ($assignmentFound) {
            // Re-index arrays
            $assignments = array_values($assignments);
            $submissions = array_values($submissions);
            eq_save_data('assignments', $assignments);
            eq_save_data('submissions', $submissions);
            $message = 'Assignment and related submissions deleted successfully.';
        } else {
            $message = 'Assignment not found.';
        }
    } else {
        $message = 'Invalid assignment ID.';
    }
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
    
    // Find assignment and check deadline
    $assignment = null;
    foreach ($assignments as $a) {
        if ((int)$a['id'] === $assignment_id) {
            $assignment = $a;
            break;
        }
    }
    
    if (!$assignment) {
        $message = 'Assignment not found.';
    } else {
        $deadlineTs = strtotime($assignment['deadline']);
        $nowTs = time();
        $isPastDeadline = $nowTs > $deadlineTs;
        
        if ($isPastDeadline) {
            $message = 'The deadline for this assignment has passed. You can no longer submit or update this assignment.';
        } elseif (!isset($_FILES['submission_file']) || $_FILES['submission_file']['error'] !== UPLOAD_ERR_OK) {
            $message = 'Please select a file to submit.';
        } elseif ($_FILES['submission_file']['size'] > 5 * 1024 * 1024) { // 5MB limit
            $message = 'File size exceeds 5MB limit. Please upload a smaller file.';
        } else {
            // Check if student already submitted and track existing submission
            $alreadySubmitted = false;
            $existingIndex = null;
            $existingStoredName = null;
            foreach ($submissions as $index => $sub) {
                if ((int)$sub['assignment_id'] === $assignment_id) {
                    $subStudentName = strtolower($sub['student_name'] ?? '');
                    $studentNameLower = strtolower($studentName);
                    if ($username === 'student1') {
                        if ($subStudentName === 'student1') {
                            $alreadySubmitted = true;
                            $existingIndex = $index;
                            $existingStoredName = $sub['stored_name'] ?? null;
                            break;
                        }
                    } else {
                        $nameParts = explode(' ', $studentNameLower);
                        if (strpos($subStudentName, strtolower($username)) !== false ||
                            strpos($subStudentName, $nameParts[0] ?? '') !== false ||
                            strpos($subStudentName, $nameParts[1] ?? '') !== false ||
                            $subStudentName === $studentNameLower) {
                            $alreadySubmitted = true;
                            $existingIndex = $index;
                            $existingStoredName = $sub['stored_name'] ?? null;
                            break;
                        }
                    }
                }
            }
            
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
                // If updating an existing submission, remove old file and update record
                if ($alreadySubmitted && $existingIndex !== null && isset($submissions[$existingIndex])) {
                    if ($existingStoredName) {
                        $oldPath = $uploadsDir . '/' . $existingStoredName;
                        if (is_file($oldPath)) {
                            @unlink($oldPath);
                        }
                    }
                    
                    $submissions[$existingIndex]['file_name'] = $originalName;
                    $submissions[$existingIndex]['stored_name'] = $targetName;
                    $submissions[$existingIndex]['submitted_at'] = date('Y-m-d H:i:s');
                    // Reset grade and feedback on resubmission
                    $submissions[$existingIndex]['score'] = null;
                    $submissions[$existingIndex]['feedback'] = null;
                    
                    eq_save_data('submissions', $submissions);
                    
                    header('Location: assignments.php?assignment_id=' . $assignment_id . '&updated=1');
                    exit;
                }
                
                // Create new submission
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
$instructorName = $username;
$instructorEmail = $username . '@gmail.com';
$initials = strtoupper(substr($username, 0, 1) . substr($username, -1));

// Prepare assignments with statistics
$assignmentsWithStats = [];
foreach ($assignments as $a) {
    // If a course filter is applied, only include assignments from that course
    if ($filter_course_code && (($a['course_code'] ?? '') !== $filter_course_code)) {
        continue;
    }
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
        // Progress should reflect actual work completion, not time elapsed
        // If assignment is not submitted yet, progress should be 0%
        $progress = 0;
        if ($hasSubmitted) {
            $progress = 100;
        } else {
            // No work completed yet - progress remains 0%
            $progress = 0;
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
    <title>Assignments - eduQuest <?php echo $role === 'student' ? 'Student' : ($role === 'admin' ? 'Admin' : 'Instructor'); ?> Portal</title>
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
        
        .student-view .logo-icon {
            background: #3b82f6;
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
        
        .student-view .nav-link.active {
            background: #3b82f6;
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
        
        .student-view .user-avatar {
            background: #3b82f6;
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
            overflow: visible;
        }
        
        .assignment-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
        }
        
        .assignment-title-section {
            flex: 1;
        }
        
        .assignment-edit-buttons {
            pointer-events: auto !important;
        }
        
        .assignment-edit-buttons button {
            pointer-events: auto !important;
            position: relative;
            z-index: 10001 !important;
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
<body class="<?php echo $role === 'student' ? 'student-view' : 'teacher-view'; ?>">
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
                    <div class="header-title">eduQuest <?php echo $role === 'student' ? 'Student' : ($role === 'admin' ? 'Admin' : 'Instructor'); ?> Portal</div>
                </div>
                <div class="header-right">
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
                        <p class="page-subtitle"><?php echo $role === 'student' ? 'View your course assignments' : ($role === 'admin' ? 'Review and manage all assignments' : 'Create and manage course assignments'); ?></p>
                    </div>
                    <?php if ($role === 'teacher'): ?>
                        <button onclick="document.getElementById('createModal').style.display='flex'" class="create-assignment-btn">
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
                            <?php if (isset($selected_assignment['files']) && is_array($selected_assignment['files']) && !empty($selected_assignment['files'])): ?>
                                <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #e5e7eb;">
                                    <p style="margin-bottom: 0.75rem;"><strong>Assignment Files:</strong></p>
                                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                        <?php foreach ($selected_assignment['files'] as $file): ?>
                                            <div style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; background: #f9fafb; border-radius: 8px; border: 1px solid #e5e7eb;">
                                                <span style="font-size: 1.25rem;">📎</span>
                                                <div style="flex: 1;">
                                                    <div style="font-weight: 500; color: #1f2937; margin-bottom: 0.25rem;">
                                                        <?php echo htmlspecialchars($file['original_name']); ?>
                                                    </div>
                                                    <?php if (isset($file['size'])): ?>
                                                        <div style="font-size: 0.8rem; color: #6b7280;">
                                                            <?php 
                                                            $sizeInKB = round($file['size'] / 1024, 2);
                                                            echo $sizeInKB >= 1024 ? round($sizeInKB / 1024, 2) . ' MB' : $sizeInKB . ' KB';
                                                            ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div style="display: flex; gap: 0.5rem;">
                                                    <a href="preview.php?file=<?php echo urlencode($file['stored_name']); ?>&assignment_id=<?php echo (int)$selected_assignment['id']; ?>" target="_blank" style="padding: 0.5rem 1rem; background: #3b82f6; color: white; text-decoration: none; border-radius: 6px; font-size: 0.85rem; font-weight: 500; transition: background 0.2s;" onmouseover="this.style.background='#2563eb'" onmouseout="this.style.background='#3b82f6'">Preview</a>
                                                    <a href="download.php?file=<?php echo urlencode($file['stored_name']); ?>&assignment_id=<?php echo (int)$selected_assignment['id']; ?>" style="padding: 0.5rem 1rem; background: #22c55e; color: white; text-decoration: none; border-radius: 6px; font-size: 0.85rem; font-weight: 500; transition: background 0.2s;" onmouseover="this.style.background='#16a34a'" onmouseout="this.style.background='#22c55e'">Download</a>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
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
                        <?php
                        // Determine if this assignment is past deadline for the current time
                        $selectedDeadlineTs = strtotime($selected_assignment['deadline']);
                        $selectedIsPastDeadline = time() > $selectedDeadlineTs;
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
                                <?php if (!$selectedIsPastDeadline): ?>
                                    <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #e5e7eb;">
                                        <p style="color: #6b7280; margin-bottom: 1rem;">You can update your submission anytime before the deadline.</p>
                                        <form method="post" enctype="multipart/form-data"
                                              id="studentSubmissionForm"
                                              data-assignment-title="<?php echo htmlspecialchars($selected_assignment['title'], ENT_QUOTES, 'UTF-8'); ?>"
                                              data-course="<?php echo htmlspecialchars($courseCode . ' - ' . $courseName, ENT_QUOTES, 'UTF-8'); ?>"
                                              data-deadline="<?php echo htmlspecialchars($selected_assignment['deadline'], ENT_QUOTES, 'UTF-8'); ?>"
                                              data-max-score="<?php echo (int)($selected_assignment['max_score'] ?? 100); ?>"
                                              data-has-submission="1"
                                              data-current-file="<?php echo htmlspecialchars($studentSubmission['file_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                              style="max-width: 500px; margin: 0 auto;">
                                            <input type="hidden" name="action" value="submit_assignment">
                                            <input type="hidden" name="assignment_id" value="<?php echo (int)$selected_assignment['id']; ?>">
                                            <div style="margin-bottom: 1rem;">
                                                <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: #1f2937;">Replace File:</label>
                                                <input type="file" name="submission_file" required accept=".pdf,.doc,.docx,.zip,.rar,.txt,.sql,.db" onchange="validateFileSize(this)" style="width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 8px; background: white; cursor: pointer;">
                                                <p style="font-size: 0.75rem; color: #6b7280; margin-top: 0.25rem;">Accepted formats: PDF, DOC, DOCX, ZIP, RAR, TXT, SQL, DB (Max 5MB)</p>
                                            </div>
                                            <button type="submit" class="submit-assignment-btn">
                                                Update Submission
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <p style="margin-top: 1rem; color: #ef4444;">The deadline has passed. You can no longer update this submission.</p>
                                <?php endif; ?>
                            <?php else: ?>
                                <div style="padding: 1.5rem; background: #f9fafb; border-radius: 8px; border: 2px dashed #d1d5db;">
                                    <p style="color: #6b7280; margin-bottom: 1.5rem; text-align: center;">You haven't submitted this assignment yet.</p>
                                    <form method="post" enctype="multipart/form-data"
                                          id="studentSubmissionForm"
                                          data-assignment-title="<?php echo htmlspecialchars($selected_assignment['title'], ENT_QUOTES, 'UTF-8'); ?>"
                                          data-course="<?php echo htmlspecialchars($courseCode . ' - ' . $courseName, ENT_QUOTES, 'UTF-8'); ?>"
                                          data-deadline="<?php echo htmlspecialchars($selected_assignment['deadline'], ENT_QUOTES, 'UTF-8'); ?>"
                                          data-max-score="<?php echo (int)($selected_assignment['max_score'] ?? 100); ?>"
                                          data-has-submission="0"
                                          data-current-file=""
                                          style="max-width: 500px; margin: 0 auto;">
                                        <input type="hidden" name="action" value="submit_assignment">
                                        <input type="hidden" name="assignment_id" value="<?php echo (int)$selected_assignment['id']; ?>">
                                        <div style="margin-bottom: 1rem;">
                                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: #1f2937;">Select File to Submit:</label>
                                            <input type="file" name="submission_file" required accept=".pdf,.doc,.docx,.zip,.rar,.txt,.sql,.db" onchange="validateFileSize(this)" style="width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 8px; background: white; cursor: pointer;">
                                            <p style="font-size: 0.75rem; color: #6b7280; margin-top: 0.25rem;">Accepted formats: PDF, DOC, DOCX, ZIP, RAR, TXT, SQL, DB (Max 5MB)</p>
                                        </div>
                                        <button type="submit" class="submit-assignment-btn">
                                            Submit Assignment
                                        </button>
                                        <p style="margin-top: 0.75rem; font-size: 0.8rem; color: #6b7280; text-align: center;">You can update this submission anytime before the deadline.</p>
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
                                    <div class="assignment-card" style="position: relative;">
                                        <?php if ($role === 'teacher' || $role === 'admin'): ?>
                                            <div class="assignment-edit-buttons" style="position: absolute; top: 1.5rem; right: 1.5rem; z-index: 10000; display: flex; gap: 0.5rem;">
                                                <form method="post" onsubmit="return confirm('Are you sure you want to delete this assignment? This will also delete all related submissions. This action cannot be undone.');" style="display: inline-block; margin: 0;">
                                                    <input type="hidden" name="action" value="delete_assignment">
                                                    <input type="hidden" name="assignment_id" value="<?php echo htmlspecialchars($ass['id']); ?>">
                                                    <button type="submit" class="delete-btn" title="Delete Assignment" style="background: #fff; border: 1px solid #ef4444; color: #ef4444; cursor: pointer; font-size: 1.2rem; padding: 0.5rem; border-radius: 6px; transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; outline: none; box-shadow: 0 2px 4px rgba(0,0,0,0.1);" onmouseover="this.style.background='#fef2f2'; this.style.borderColor='#dc2626'; this.style.transform='scale(1.1)'; this.style.boxShadow='0 4px 8px rgba(0,0,0,0.15)'" onmouseout="this.style.background='#fff'; this.style.borderColor='#ef4444'; this.style.transform='scale(1)'; this.style.boxShadow='0 2px 4px rgba(0,0,0,0.1)'">🗑️</button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="assignment-header" style="padding-right: 4.5rem; position: relative; z-index: 1;">
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
    
    <!-- Student submission confirmation modal -->
    <?php if ($role === 'student'): ?>
    <div id="submissionConfirmModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
        <div style="background:white; border-radius:12px; padding:2rem; max-width:520px; width:90%; max-height:90vh; overflow-y:auto; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem;">
                <h2 style="font-size:1.25rem; font-weight:600; color:#111827;">Confirm Submission</h2>
                <button type="button" id="submissionConfirmClose" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:#6b7280;">&times;</button>
            </div>
            <p style="font-size:0.9rem; color:#6b7280; margin-bottom:1rem;">Please review the details below before submitting. You can still update your file until the assignment deadline.</p>
            <div style="display:flex; flex-direction:column; gap:0.75rem; margin-bottom:1.5rem;">
                <div>
                    <p style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; color:#9ca3af; margin-bottom:0.15rem;">Assignment</p>
                    <p id="confirmAssignmentTitle" style="font-size:0.95rem; font-weight:600; color:#111827;"></p>
                </div>
                <div>
                    <p style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; color:#9ca3af; margin-bottom:0.15rem;">Course</p>
                    <p id="confirmCourse" style="font-size:0.9rem; color:#111827;"></p>
                </div>
                <div style="display:flex; gap:1.5rem;">
                    <div style="flex:1;">
                        <p style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; color:#9ca3af; margin-bottom:0.15rem;">Deadline</p>
                        <p id="confirmDeadline" style="font-size:0.9rem; color:#111827;"></p>
                    </div>
                    <div style="flex:1;">
                        <p style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; color:#9ca3af; margin-bottom:0.15rem;">Max Score</p>
                        <p id="confirmMaxScore" style="font-size:0.9rem; color:#111827;"></p>
                    </div>
                </div>
                <div id="currentFileRow" style="display:none;">
                    <p style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; color:#9ca3af; margin-bottom:0.15rem;">Current File</p>
                    <p id="confirmCurrentFile" style="font-size:0.9rem; color:#111827;"></p>
                </div>
                <div>
                    <p style="font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; color:#9ca3af; margin-bottom:0.15rem;">New File</p>
                    <p id="confirmNewFile" style="font-size:0.9rem; color:#111827;"></p>
                </div>
            </div>
            <p id="confirmCanEdit" style="font-size:0.8rem; color:#6b7280; margin-bottom:1.5rem;"></p>
            <div style="display:flex; gap:0.75rem;">
                <button type="button" id="cancelSubmitBtn" style="flex:1; padding:0.75rem; background:#f3f4f6; color:#111827; border:none; border-radius:8px; font-size:0.9rem; font-weight:500; cursor:pointer;">Go Back</button>
                <button type="button" id="confirmSubmitBtn" style="flex:1; padding:0.75rem; background:#3b82f6; color:white; border:none; border-radius:8px; font-size:0.9rem; font-weight:500; cursor:pointer;">Confirm Submit</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Edit Assignment Modal -->
    <?php if ($role === 'teacher' || $role === 'admin'): ?>
    <div id="editModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:10000; align-items:center; justify-content:center; flex-direction:column;">
        <div style="background:white; border-radius:16px; padding:2.5rem; max-width:700px; width:90%; max-height:90vh; overflow-y:auto; position:relative; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);">
            <!-- Close Button -->
            <button onclick="document.getElementById('editModal').style.display='none'" style="position:absolute; top:1.5rem; right:1.5rem; background:none; border:none; font-size:1.75rem; cursor:pointer; color:#6b7280; width:32px; height:32px; display:flex; align-items:center; justify-content:center; border-radius:6px; transition:background 0.2s;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='none'">&times;</button>
            
            <!-- Header -->
            <div style="margin-bottom:2rem;">
                <h2 style="font-size:1.875rem; font-weight:700; color:#1f2937; margin-bottom:0.5rem;">Edit Assignment</h2>
                <p style="font-size:0.95rem; color:#6b7280;">Update the assignment details below.</p>
            </div>
            
            <form method="post" enctype="multipart/form-data" id="editAssignmentForm">
                <input type="hidden" name="action" value="update_assignment">
                <input type="hidden" name="assignment_id" id="editAssignmentId">
                <div style="display:flex; flex-direction:column; gap:1.5rem;">
                    <!-- Assignment Title -->
                    <div>
                        <label style="display:block; margin-bottom:0.5rem; font-weight:500; color:#1f2937; font-size:0.95rem;">Assignment Title <span style="color:#ef4444;">*</span></label>
                        <input type="text" name="title" id="editAssignmentTitle" required placeholder="Enter assignment title" style="width:100%; padding:0.875rem 1rem; border:1px solid #d1d5db; border-radius:8px; font-size:0.95rem; transition:border-color 0.2s;" onfocus="this.style.borderColor='#22c55e'; this.style.outline='none'" onblur="this.style.borderColor='#d1d5db'">
                    </div>
                    
                    <!-- Course -->
                    <div>
                        <label style="display:block; margin-bottom:0.5rem; font-weight:500; color:#1f2937; font-size:0.95rem;">Course <span style="color:#ef4444;">*</span></label>
                        <div style="position:relative;">
                            <select name="course_code" id="editAssignmentCourse" required style="width:100%; padding:0.875rem 1rem; padding-right:2.5rem; border:1px solid #d1d5db; border-radius:8px; font-size:0.95rem; background:white; appearance:none; cursor:pointer; transition:border-color 0.2s;" onfocus="this.style.borderColor='#22c55e'; this.style.outline='none'" onblur="this.style.borderColor='#d1d5db'">
                                <option value="CS 101">CS 101 - Web Development</option>
                                <option value="CS 201">CS 201 - Database Systems</option>
                                <option value="CS 301">CS 301 - Algorithms</option>
                                <option value="CS 401">CS 401 - Software Engineering</option>
                            </select>
                            <span style="position:absolute; right:1rem; top:50%; transform:translateY(-50%); pointer-events:none; color:#6b7280;">▼</span>
                        </div>
                    </div>
                    
                    <!-- Description -->
                    <div>
                        <label style="display:block; margin-bottom:0.5rem; font-weight:500; color:#1f2937; font-size:0.95rem;">Description <span style="color:#ef4444;">*</span></label>
                        <textarea name="description" id="editAssignmentDescription" required rows="5" placeholder="Enter assignment description and instructions" style="width:100%; padding:0.875rem 1rem; border:1px solid #d1d5db; border-radius:8px; font-size:0.95rem; resize:vertical; font-family:inherit; transition:border-color 0.2s;" onfocus="this.style.borderColor='#22c55e'; this.style.outline='none'" onblur="this.style.borderColor='#d1d5db'"></textarea>
                    </div>
                    
                    <!-- Points and Due Date Row -->
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                        <!-- Points -->
                        <div>
                            <label style="display:block; margin-bottom:0.5rem; font-weight:500; color:#1f2937; font-size:0.95rem;">Points <span style="color:#ef4444;">*</span></label>
                            <input type="number" name="max_score" id="editAssignmentMaxScore" min="1" required style="width:100%; padding:0.875rem 1rem; border:1px solid #d1d5db; border-radius:8px; font-size:0.95rem; transition:border-color 0.2s;" onfocus="this.style.borderColor='#22c55e'; this.style.outline='none'" onblur="this.style.borderColor='#d1d5db'">
                        </div>
                        
                        <!-- Due Date -->
                        <div>
                            <label style="display:block; margin-bottom:0.5rem; font-weight:500; color:#1f2937; font-size:0.95rem;">Due Date <span style="color:#ef4444;">*</span></label>
                            <div style="position:relative;">
                                <input type="datetime-local" name="deadline" id="editAssignmentDeadline" required style="width:100%; padding:0.875rem 1rem; padding-left:2.5rem; border:1px solid #d1d5db; border-radius:8px; font-size:0.95rem; transition:border-color 0.2s;" onfocus="this.style.borderColor='#22c55e'; this.style.outline='none'" onblur="this.style.borderColor='#d1d5db'">
                                <span style="position:absolute; left:0.875rem; top:50%; transform:translateY(-50%); pointer-events:none; color:#6b7280; font-size:1.1rem;">📅</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Rubric -->
                    <div>
                        <label style="display:block; margin-bottom:0.5rem; font-weight:500; color:#1f2937; font-size:0.95rem;">Rubric (Optional)</label>
                        <textarea name="rubric" id="editAssignmentRubric" rows="4" placeholder="Enter grading rubric and criteria" style="width:100%; padding:0.875rem 1rem; border:1px solid #d1d5db; border-radius:8px; font-size:0.95rem; resize:vertical; font-family:inherit; transition:border-color 0.2s;" onfocus="this.style.borderColor='#22c55e'; this.style.outline='none'" onblur="this.style.borderColor='#d1d5db'"></textarea>
                    </div>
                    
                    <!-- Attach Files -->
                    <div>
                        <label style="display:block; margin-bottom:0.5rem; font-weight:500; color:#1f2937; font-size:0.95rem;">Add More Files (Optional)</label>
                        <div id="editFileDropZone" style="border:2px dashed #d1d5db; border-radius:8px; padding:2.5rem; text-align:center; background:#fafafa; cursor:pointer; transition:all 0.2s;" onmouseover="this.style.borderColor='#22c55e'; this.style.background='#f0fdf4'" onmouseout="this.style.borderColor='#d1d5db'; this.style.background='#fafafa'" onclick="document.getElementById('editFileInput').click()">
                            <div style="font-size:2.5rem; margin-bottom:0.75rem;">⬆️</div>
                            <p style="color:#6b7280; font-size:0.95rem; margin:0;">Click to upload or drag and drop</p>
                            <p style="color:#9ca3af; font-size:0.85rem; margin-top:0.5rem;">Files will be added to existing assignment files</p>
                            <input type="file" id="editFileInput" name="assignment_files[]" multiple style="display:none;" onchange="handleEditFileSelect(event)">
                        </div>
                        <div id="editFileList" style="margin-top:0.75rem; display:none;">
                            <p style="font-size:0.85rem; color:#6b7280; margin-bottom:0.5rem;">Files to add:</p>
                            <div id="editFileItems"></div>
                        </div>
                    </div>
                    
                    <!-- Buttons -->
                    <div style="display:flex; gap:1rem; margin-top:1rem;">
                        <button type="button" onclick="document.getElementById('editModal').style.display='none'" style="flex:1; padding:0.875rem 1.5rem; background:white; color:#1f2937; border:1px solid #d1d5db; border-radius:8px; font-weight:500; cursor:pointer; font-size:0.95rem; transition:all 0.2s;" onmouseover="this.style.background='#f9fafb'; this.style.borderColor='#9ca3af'" onmouseout="this.style.background='white'; this.style.borderColor='#d1d5db'">Cancel</button>
                        <button type="submit" style="flex:1; padding:0.875rem 1.5rem; background:#22c55e; color:white; border:none; border-radius:8px; font-weight:500; cursor:pointer; font-size:0.95rem; transition:background 0.2s;" onmouseover="this.style.background='#16a34a'" onmouseout="this.style.background='#22c55e'">Update Assignment</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Create Assignment Modal -->
    <?php if ($role === 'teacher'): ?>
    <div id="createModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center; flex-direction:column;">
        <div style="background:white; border-radius:16px; padding:2.5rem; max-width:700px; width:90%; max-height:90vh; overflow-y:auto; position:relative; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);">
            <!-- Close Button -->
            <button onclick="document.getElementById('createModal').style.display='none'" style="position:absolute; top:1.5rem; right:1.5rem; background:none; border:none; font-size:1.75rem; cursor:pointer; color:#6b7280; width:32px; height:32px; display:flex; align-items:center; justify-content:center; border-radius:6px; transition:background 0.2s;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='none'">&times;</button>
            
            <!-- Header -->
            <div style="margin-bottom:2rem;">
                <h2 style="font-size:1.875rem; font-weight:700; color:#1f2937; margin-bottom:0.5rem;">Create New Assignment</h2>
                <p style="font-size:0.95rem; color:#6b7280;">Fill in the details to create a new assignment for your course.</p>
            </div>
            
            <form method="post" enctype="multipart/form-data" id="assignmentForm">
                <input type="hidden" name="action" value="create_assignment">
                <div style="display:flex; flex-direction:column; gap:1.5rem;">
                    <!-- Assignment Title -->
                    <div>
                        <label style="display:block; margin-bottom:0.5rem; font-weight:500; color:#1f2937; font-size:0.95rem;">Assignment Title <span style="color:#ef4444;">*</span></label>
                        <input type="text" name="title" required placeholder="Enter assignment title" style="width:100%; padding:0.875rem 1rem; border:1px solid #d1d5db; border-radius:8px; font-size:0.95rem; transition:border-color 0.2s;" onfocus="this.style.borderColor='#22c55e'; this.style.outline='none'" onblur="this.style.borderColor='#d1d5db'">
                    </div>
                    
                    <!-- Course -->
                    <div>
                        <label style="display:block; margin-bottom:0.5rem; font-weight:500; color:#1f2937; font-size:0.95rem;">Course <span style="color:#ef4444;">*</span></label>
                        <div style="position:relative;">
                            <select name="course_code" required style="width:100%; padding:0.875rem 1rem; padding-right:2.5rem; border:1px solid #d1d5db; border-radius:8px; font-size:0.95rem; background:white; appearance:none; cursor:pointer; transition:border-color 0.2s;" onfocus="this.style.borderColor='#22c55e'; this.style.outline='none'" onblur="this.style.borderColor='#d1d5db'">
                                <option value="" disabled selected>Select a course</option>
                                <option value="CS 101">CS 101 - Web Development</option>
                                <option value="CS 201">CS 201 - Database Systems</option>
                                <option value="CS 301">CS 301 - Algorithms</option>
                                <option value="CS 401">CS 401 - Software Engineering</option>
                            </select>
                            <span style="position:absolute; right:1rem; top:50%; transform:translateY(-50%); pointer-events:none; color:#6b7280;">▼</span>
                        </div>
                    </div>
                    
                    <!-- Description -->
                    <div>
                        <label style="display:block; margin-bottom:0.5rem; font-weight:500; color:#1f2937; font-size:0.95rem;">Description <span style="color:#ef4444;">*</span></label>
                        <textarea name="description" required rows="5" placeholder="Enter assignment description and instructions" style="width:100%; padding:0.875rem 1rem; border:1px solid #d1d5db; border-radius:8px; font-size:0.95rem; resize:vertical; font-family:inherit; transition:border-color 0.2s;" onfocus="this.style.borderColor='#22c55e'; this.style.outline='none'" onblur="this.style.borderColor='#d1d5db'"></textarea>
                    </div>
                    
                    <!-- Points and Due Date Row -->
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                        <!-- Points -->
                        <div>
                            <label style="display:block; margin-bottom:0.5rem; font-weight:500; color:#1f2937; font-size:0.95rem;">Points <span style="color:#ef4444;">*</span></label>
                            <input type="number" name="max_score" value="100" min="1" required style="width:100%; padding:0.875rem 1rem; border:1px solid #d1d5db; border-radius:8px; font-size:0.95rem; transition:border-color 0.2s;" onfocus="this.style.borderColor='#22c55e'; this.style.outline='none'" onblur="this.style.borderColor='#d1d5db'">
                        </div>
                        
                        <!-- Due Date -->
                        <div>
                            <label style="display:block; margin-bottom:0.5rem; font-weight:500; color:#1f2937; font-size:0.95rem;">Due Date <span style="color:#ef4444;">*</span></label>
                            <div style="position:relative;">
                                <input type="datetime-local" name="deadline" required style="width:100%; padding:0.875rem 1rem; padding-left:2.5rem; border:1px solid #d1d5db; border-radius:8px; font-size:0.95rem; transition:border-color 0.2s;" onfocus="this.style.borderColor='#22c55e'; this.style.outline='none'" onblur="this.style.borderColor='#d1d5db'">
                                <span style="position:absolute; left:0.875rem; top:50%; transform:translateY(-50%); pointer-events:none; color:#6b7280; font-size:1.1rem;">📅</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Attach Files -->
                    <div>
                        <label style="display:block; margin-bottom:0.5rem; font-weight:500; color:#1f2937; font-size:0.95rem;">Attach Files (Optional)</label>
                        <div id="fileDropZone" style="border:2px dashed #d1d5db; border-radius:8px; padding:2.5rem; text-align:center; background:#fafafa; cursor:pointer; transition:all 0.2s;" onmouseover="this.style.borderColor='#22c55e'; this.style.background='#f0fdf4'" onmouseout="this.style.borderColor='#d1d5db'; this.style.background='#fafafa'" onclick="document.getElementById('fileInput').click()">
                            <div style="font-size:2.5rem; margin-bottom:0.75rem;">⬆️</div>
                            <p style="color:#6b7280; font-size:0.95rem; margin:0;">Click to upload or drag and drop</p>
                            <input type="file" id="fileInput" name="assignment_files[]" multiple style="display:none;" onchange="handleFileSelect(event)">
                        </div>
                        <div id="fileList" style="margin-top:0.75rem; display:none;">
                            <p style="font-size:0.85rem; color:#6b7280; margin-bottom:0.5rem;">Selected files:</p>
                            <div id="fileItems"></div>
                        </div>
                    </div>
                    
                    <!-- Buttons -->
                    <div style="display:flex; gap:1rem; margin-top:1rem;">
                        <button type="button" onclick="document.getElementById('createModal').style.display='none'" style="flex:1; padding:0.875rem 1.5rem; background:white; color:#1f2937; border:1px solid #d1d5db; border-radius:8px; font-weight:500; cursor:pointer; font-size:0.95rem; transition:all 0.2s;" onmouseover="this.style.background='#f9fafb'; this.style.borderColor='#9ca3af'" onmouseout="this.style.background='white'; this.style.borderColor='#d1d5db'">Cancel</button>
                        <button type="submit" style="flex:1; padding:0.875rem 1.5rem; background:#22c55e; color:white; border:none; border-radius:8px; font-weight:500; cursor:pointer; font-size:0.95rem; transition:background 0.2s;" onmouseover="this.style.background='#16a34a'" onmouseout="this.style.background='#22c55e'">Create Assignment</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <script>
        // ===== EDIT ASSIGNMENT FUNCTIONS - Define early so they're available =====
        // Handle edit assignment button click - Make it globally accessible
        window.handleEditAssignmentClick = function(btn) {
            if (!btn) {
                console.error('Button element not provided');
                alert('Error: Button not found');
                return false;
            }
            
            try {
                const assignmentId = btn.getAttribute('data-assignment-id');
                const title = btn.getAttribute('data-assignment-title') || '';
                const description = btn.getAttribute('data-assignment-description') || '';
                const deadline = btn.getAttribute('data-assignment-deadline') || '';
                const maxScore = btn.getAttribute('data-assignment-maxscore') || '100';
                const rubric = btn.getAttribute('data-assignment-rubric') || '';
                const courseCode = btn.getAttribute('data-assignment-course') || 'CS 101';
                
                console.log('Edit button clicked:', {assignmentId, title, description, deadline, maxScore, rubric, courseCode});
                
                if (!assignmentId) {
                    alert('Error: Assignment ID is missing.');
                    return false;
                }
                
                if (window.openEditAssignmentModal) {
                    window.openEditAssignmentModal(assignmentId, title, description, deadline, maxScore, rubric, courseCode);
                } else {
                    alert('Error: Edit modal function not loaded. Please refresh the page.');
                }
                return false;
            } catch (error) {
                console.error('Error in handleEditAssignmentClick:', error);
                alert('Error opening edit form: ' + error.message);
                return false;
            }
        };
        
        // Open edit assignment modal with assignment data - Make it globally accessible
        window.openEditAssignmentModal = function(assignmentId, title, description, deadline, maxScore, rubric, courseCode) {
            try {
                console.log('openEditAssignmentModal called with:', {assignmentId, title, description, deadline, maxScore, rubric, courseCode});
                
                const editModal = document.getElementById('editModal');
                if (!editModal) {
                    console.error('Edit modal not found');
                    alert('Edit modal not available. Please refresh the page.');
                    return;
                }
                
                const editAssignmentId = document.getElementById('editAssignmentId');
                const editAssignmentTitle = document.getElementById('editAssignmentTitle');
                const editAssignmentDescription = document.getElementById('editAssignmentDescription');
                const editAssignmentDeadline = document.getElementById('editAssignmentDeadline');
                const editAssignmentMaxScore = document.getElementById('editAssignmentMaxScore');
                const editAssignmentRubric = document.getElementById('editAssignmentRubric');
                const editAssignmentCourse = document.getElementById('editAssignmentCourse');
                
                if (editAssignmentId) editAssignmentId.value = assignmentId || '';
                if (editAssignmentTitle) editAssignmentTitle.value = title || '';
                if (editAssignmentDescription) editAssignmentDescription.value = description || '';
                
                // Format deadline for datetime-local input (YYYY-MM-DDTHH:mm)
                let deadlineFormatted = '';
                if (deadline) {
                    // The deadline should already be in format "YYYY-MM-DDTHH:mm" or "YYYY-MM-DD HH:mm"
                    // Just ensure it's in the correct format for datetime-local
                    if (deadline.includes('T')) {
                        // Already has T separator, just take first 16 chars (YYYY-MM-DDTHH:mm)
                        deadlineFormatted = deadline.substring(0, 16);
                    } else if (deadline.includes(' ')) {
                        // Has space separator, replace with T
                        deadlineFormatted = deadline.replace(' ', 'T').substring(0, 16);
                    } else {
                        // Try to parse as Date
                        const deadlineDate = new Date(deadline);
                        if (!isNaN(deadlineDate.getTime())) {
                            const year = deadlineDate.getFullYear();
                            const month = String(deadlineDate.getMonth() + 1).padStart(2, '0');
                            const day = String(deadlineDate.getDate()).padStart(2, '0');
                            const hours = String(deadlineDate.getHours()).padStart(2, '0');
                            const minutes = String(deadlineDate.getMinutes()).padStart(2, '0');
                            deadlineFormatted = `${year}-${month}-${day}T${hours}:${minutes}`;
                        }
                    }
                }
                if (editAssignmentDeadline) editAssignmentDeadline.value = deadlineFormatted;
                if (editAssignmentMaxScore) editAssignmentMaxScore.value = maxScore || 100;
                if (editAssignmentRubric) editAssignmentRubric.value = rubric || '';
                if (editAssignmentCourse) editAssignmentCourse.value = courseCode || 'CS 101';
                
                editModal.style.display = 'flex';
                editModal.style.zIndex = '10000';
                console.log('Edit modal opened successfully');
            } catch (error) {
                console.error('Error opening edit modal:', error);
                alert('Error opening edit modal: ' + error.message);
            }
        };
        // ===== END EDIT ASSIGNMENT FUNCTIONS =====
        
        // Close modal when clicking outside
        const createModal = document.getElementById('createModal');
        if (createModal) {
            createModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.style.display = 'none';
                }
            });
        }
        
        // File drag and drop handling
        const fileDropZone = document.getElementById('fileDropZone');
        const fileInput = document.getElementById('fileInput');
        const fileList = document.getElementById('fileList');
        const fileItems = document.getElementById('fileItems');
        let selectedFiles = [];
        
        if (fileDropZone) {
            // Prevent default drag behaviors
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                fileDropZone.addEventListener(eventName, preventDefaults, false);
                document.body.addEventListener(eventName, preventDefaults, false);
            });
            
            // Highlight drop zone when item is dragged over it
            ['dragenter', 'dragover'].forEach(eventName => {
                fileDropZone.addEventListener(eventName, highlight, false);
            });
            
            ['dragleave', 'drop'].forEach(eventName => {
                fileDropZone.addEventListener(eventName, unhighlight, false);
            });
            
            // Handle dropped files
            fileDropZone.addEventListener('drop', handleDrop, false);
        }
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        function highlight(e) {
            fileDropZone.style.borderColor = '#22c55e';
            fileDropZone.style.background = '#f0fdf4';
        }
        
        function unhighlight(e) {
            fileDropZone.style.borderColor = '#d1d5db';
            fileDropZone.style.background = '#fafafa';
        }
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            handleFiles(files);
        }
        
        function handleFileSelect(e) {
            const files = e.target.files;
            handleFiles(files);
        }
        
        function handleFiles(files) {
            selectedFiles = Array.from(files);
            updateFileList();
        }
        
        function updateFileList() {
            if (selectedFiles.length === 0) {
                fileList.style.display = 'none';
                return;
            }
            
            fileList.style.display = 'block';
            fileItems.innerHTML = '';
            
            selectedFiles.forEach((file, index) => {
                const fileItem = document.createElement('div');
                fileItem.style.cssText = 'display:flex; align-items:center; justify-content:space-between; padding:0.5rem; background:white; border:1px solid #e5e7eb; border-radius:6px; margin-bottom:0.5rem;';
                fileItem.innerHTML = `
                    <span style="font-size:0.85rem; color:#1f2937; flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${file.name}</span>
                    <button type="button" onclick="removeFile(${index})" style="background:none; border:none; color:#ef4444; cursor:pointer; font-size:1.2rem; padding:0 0.5rem; margin-left:0.5rem;">&times;</button>
                `;
                fileItems.appendChild(fileItem);
            });
        }
        
        function removeFile(index) {
            selectedFiles.splice(index, 1);
            updateFileList();
            
            // Update the file input
            if (fileInput) {
                const dt = new DataTransfer();
                selectedFiles.forEach(file => dt.items.add(file));
                fileInput.files = dt.files;
            }
        }
        
        // Edit form file handling
        const editFileDropZone = document.getElementById('editFileDropZone');
        const editFileInput = document.getElementById('editFileInput');
        const editFileList = document.getElementById('editFileList');
        const editFileItems = document.getElementById('editFileItems');
        let editSelectedFiles = [];
        
        if (editFileDropZone) {
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                editFileDropZone.addEventListener(eventName, function(e) { e.preventDefault(); e.stopPropagation(); }, false);
                document.body.addEventListener(eventName, function(e) { e.preventDefault(); e.stopPropagation(); }, false);
            });
            
            ['dragenter', 'dragover'].forEach(eventName => {
                editFileDropZone.addEventListener(eventName, function() {
                    editFileDropZone.style.borderColor = '#22c55e';
                    editFileDropZone.style.background = '#f0fdf4';
                }, false);
            });
            
            ['dragleave', 'drop'].forEach(eventName => {
                editFileDropZone.addEventListener(eventName, function() {
                    editFileDropZone.style.borderColor = '#d1d5db';
                    editFileDropZone.style.background = '#fafafa';
                }, false);
            });
            
            editFileDropZone.addEventListener('drop', function(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                editHandleFiles(files);
            }, false);
        }
        
        function handleEditFileSelect(e) {
            const files = e.target.files;
            editHandleFiles(files);
        }
        
        function editHandleFiles(files) {
            editSelectedFiles = Array.from(files);
            updateEditFileList();
        }
        
        function updateEditFileList() {
            if (!editFileList || !editFileItems) return;
            if (editSelectedFiles.length === 0) {
                editFileList.style.display = 'none';
                return;
            }
            
            editFileList.style.display = 'block';
            editFileItems.innerHTML = '';
            
            editSelectedFiles.forEach((file, index) => {
                const fileItem = document.createElement('div');
                fileItem.style.cssText = 'display:flex; align-items:center; justify-content:space-between; padding:0.5rem; background:white; border:1px solid #e5e7eb; border-radius:6px; margin-bottom:0.5rem;';
                fileItem.innerHTML = `
                    <span style="font-size:0.85rem; color:#1f2937; flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${file.name}</span>
                    <button type="button" onclick="removeEditFile(${index})" style="background:none; border:none; color:#ef4444; cursor:pointer; font-size:1.2rem; padding:0 0.5rem; margin-left:0.5rem;">&times;</button>
                `;
                editFileItems.appendChild(fileItem);
            });
        }
        
        function removeEditFile(index) {
            editSelectedFiles.splice(index, 1);
            updateEditFileList();
            
            if (editFileInput) {
                const dt = new DataTransfer();
                editSelectedFiles.forEach(file => dt.items.add(file));
                editFileInput.files = dt.files;
            }
        }
        
        // Reset form when modal is closed
        if (createModal) {
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.target.style.display === 'none' || mutation.target.style.display === '') {
                        // Reset form
                        const form = document.getElementById('assignmentForm');
                        if (form) {
                            form.reset();
                            selectedFiles = [];
                            if (fileList) fileList.style.display = 'none';
                            if (fileItems) fileItems.innerHTML = '';
                        }
                    }
                });
            });
            observer.observe(createModal, {
                attributes: true,
                attributeFilter: ['style']
            });
        }
        
        // Reset edit form when edit modal is closed
        const editModal = document.getElementById('editModal');
        if (editModal) {
            const editObserver = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.target.style.display === 'none' || mutation.target.style.display === '') {
                        editSelectedFiles = [];
                        if (editFileList) editFileList.style.display = 'none';
                        if (editFileItems) editFileItems.innerHTML = '';
                        if (editFileInput) editFileInput.value = '';
                    }
                });
            });
            editObserver.observe(editModal, { attributes: true, attributeFilter: ['style'] });
        }

        // Student submission confirmation dialog
        const submissionForm = document.getElementById('studentSubmissionForm');
        const submissionConfirmModal = document.getElementById('submissionConfirmModal');
        if (submissionForm && submissionConfirmModal) {
            const confirmAssignmentTitle = document.getElementById('confirmAssignmentTitle');
            const confirmCourse = document.getElementById('confirmCourse');
            const confirmDeadline = document.getElementById('confirmDeadline');
            const confirmMaxScore = document.getElementById('confirmMaxScore');
            const confirmCurrentFile = document.getElementById('confirmCurrentFile');
            const confirmNewFile = document.getElementById('confirmNewFile');
            const confirmCanEdit = document.getElementById('confirmCanEdit');
            const currentFileRow = document.getElementById('currentFileRow');
            const confirmSubmitBtn = document.getElementById('confirmSubmitBtn');
            const cancelSubmitBtn = document.getElementById('cancelSubmitBtn');
            const closeConfirmBtn = document.getElementById('submissionConfirmClose');
            let submissionConfirmed = false;

            function hideConfirmModal() {
                submissionConfirmModal.style.display = 'none';
            }

            if (cancelSubmitBtn) {
                cancelSubmitBtn.addEventListener('click', hideConfirmModal);
            }
            if (closeConfirmBtn) {
                closeConfirmBtn.addEventListener('click', hideConfirmModal);
            }
            submissionConfirmModal.addEventListener('click', function (e) {
                if (e.target === submissionConfirmModal) {
                    hideConfirmModal();
                }
            });

            submissionForm.addEventListener('submit', function (e) {
                if (submissionConfirmed) {
                    return;
                }
                e.preventDefault();

                const fileInput = submissionForm.querySelector('input[type="file"][name="submission_file"]');
                if (!fileInput || fileInput.files.length === 0) {
                    alert('Please select a file to submit.');
                    return;
                }
                
                // Check file size (5MB limit)
                const maxSize = 5 * 1024 * 1024; // 5MB in bytes
                if (fileInput.files[0].size > maxSize) {
                    alert('File size exceeds 5MB limit. Please upload a smaller file.');
                    fileInput.value = ''; // Clear the file input
                    return;
                }

                const dataset = submissionForm.dataset;
                if (confirmAssignmentTitle) {
                    confirmAssignmentTitle.textContent = dataset.assignmentTitle || '';
                }
                if (confirmCourse) {
                    confirmCourse.textContent = dataset.course || '';
                }
                if (confirmDeadline) {
                    confirmDeadline.textContent = dataset.deadline || '';
                }
                if (confirmMaxScore) {
                    confirmMaxScore.textContent = dataset.maxScore ? dataset.maxScore + ' points' : '';
                }

                if (dataset.currentFile) {
                    if (currentFileRow) {
                        currentFileRow.style.display = 'block';
                    }
                    if (confirmCurrentFile) {
                        confirmCurrentFile.textContent = dataset.currentFile;
                    }
                } else if (currentFileRow) {
                    currentFileRow.style.display = 'none';
                }

                if (confirmNewFile) {
                    confirmNewFile.textContent = fileInput.files[0].name;
                }
                if (confirmCanEdit) {
                    confirmCanEdit.textContent = dataset.deadline
                        ? 'You can update this submission again anytime before ' + dataset.deadline + '.'
                        : 'You can update this submission again while the assignment is still open.';
                }

                submissionConfirmModal.style.display = 'flex';

                if (confirmSubmitBtn) {
                    confirmSubmitBtn.onclick = function () {
                        submissionConfirmed = true;
                        hideConfirmModal();
                        submissionForm.submit();
                    };
                }
            });
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
        
        // Event delegation for edit assignment buttons
        document.addEventListener('DOMContentLoaded', function() {
            document.addEventListener('click', function(e) {
                // Use closest() to find the button even if clicking on child elements (like the emoji)
                const btn = e.target.closest('.edit-assignment-btn');
                if (btn) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (window.handleEditAssignmentClick) {
                        window.handleEditAssignmentClick(btn);
                    }
                }
            });
        });
        
        // Close edit modal when clicking outside
        const editModal = document.getElementById('editModal');
        if (editModal) {
            editModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>
