<?php
session_start();
require_once __DIR__ . '/data_store.php';

// Require login
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['teacher', 'student'])) {
    header('Location: role_selection.php');
    exit;
}

$role = $_SESSION['role'];

// Load data
$assignments = eq_load_data('assignments');
$submissions = eq_load_data('submissions');
$notes = eq_load_data('notes');
$courseMaterials = [];

if ($role === 'teacher') {
    // INSTRUCTOR VIEW
    // Get instructor info
    $username = $_SESSION['username'] ?? 'teacher1';
    $instructorName = $username;
    $instructorEmail = $username . '@gmail.com';
    $initials = strtoupper(substr($username, 0, 1) . substr($username, -1));
    
    // Define courses
    $courses = [
        [
            'code' => 'CS 101',
            'name' => 'Web Development',
            'semester' => 'Fall 2025',
            'color' => '#3b82f6',
            'icon' => '📖',
        ],
        [
            'code' => 'CS 201',
            'name' => 'Database Systems',
            'semester' => 'Fall 2025',
            'color' => '#22c55e',
            'icon' => '📖',
        ],
        [
            'code' => 'CS 301',
            'name' => 'Algorithms',
            'semester' => 'Fall 2025',
            'color' => '#a855f7',
            'icon' => '📖',
        ],
        [
            'code' => 'CS 401',
            'name' => 'Software Engineering',
            'semester' => 'Fall 2025',
            'color' => '#f97316',
            'icon' => '📖',
        ],
    ];
    
    // Calculate statistics for each course
    foreach ($courses as &$course) {
        $courseCode = $course['code'];

        // Get assignments that belong to this course by explicit course_code
        $courseAssignments = [];
        foreach ($assignments as $ass) {
            $assCourseCode = $ass['course_code'] ?? null;
            if ($assCourseCode === $courseCode) {
                $courseAssignments[] = $ass;
            }
        }

        // Collect distinct students who submitted to any assignment in this course
        $courseStudents = [];
        foreach ($courseAssignments as $ass) {
            foreach ($submissions as $sub) {
                if ((int)$sub['assignment_id'] === (int)$ass['id']) {
                    if (!in_array($sub['student_name'], $courseStudents, true)) {
                        $courseStudents[] = $sub['student_name'];
                    }
                }
            }
        }

        // Get notes for this course using course_code
        $courseNotes = [];
        foreach ($notes as $note) {
            if (($note['course_code'] ?? null) === $courseCode) {
                $courseNotes[] = $note;
            }
        }
        
        $totalAssignments = count($courseAssignments);
        $completedAssignments = 0;
        foreach ($courseAssignments as $ass) {
            $hasSubmissions = false;
            foreach ($submissions as $sub) {
                if ((int)$sub['assignment_id'] === (int)$ass['id']) {
                    $hasSubmissions = true;
                    break;
                }
            }
            if ($hasSubmissions) {
                $completedAssignments++;
            }
        }
        
        $progress = $totalAssignments > 0 ? round(($completedAssignments / $totalAssignments) * 100) : 0;
        
        // Calculate average grade
        $totalScore = 0;
        $totalMaxScore = 0;
        $gradedCount = 0;
        foreach ($courseAssignments as $ass) {
            foreach ($submissions as $sub) {
                if ((int)$sub['assignment_id'] === (int)$ass['id']) {
                    if ($sub['score'] !== null && $sub['score'] !== '') {
                        $totalScore += (float)$sub['score'];
                        $totalMaxScore += (float)($ass['max_score'] ?? 100);
                        $gradedCount++;
                    }
                }
            }
        }
        $averageGrade = $gradedCount > 0 && $totalMaxScore > 0 ? round(($totalScore / $totalMaxScore) * 100, 1) : rand(80, 90);
        
        // Calculate completion rate (based on assignments completed)
        $completionRate = $totalAssignments > 0 ? round(($completedAssignments / $totalAssignments) * 100) : rand(60, 70);
        
        // Attendance rate (simulated)
        $attendanceRate = rand(90, 98);
        
        $course['students'] = count($courseStudents);
        $course['assignments'] = count($courseAssignments);
        $course['notes'] = count($courseNotes);
        $course['progress'] = $progress;
        $course['average_grade'] = $averageGrade;
        $course['attendance_rate'] = $attendanceRate;
        $course['completion_rate'] = $completionRate;
        
        // Add course details for modal
        switch($course['code']) {
            case 'CS 101':
                $course['description'] = 'Introduction to modern web development using HTML, CSS, JavaScript, and popular frameworks.';
                $course['schedule'] = 'Mon, Wed, Fri 10:00 AM - 11:30 AM';
                $course['location'] = 'Room 301, Computer Science Building';
                break;
            case 'CS 201':
                $course['description'] = 'Comprehensive study of database systems, SQL, normalization, and database management.';
                $course['schedule'] = 'Tue, Thu 2:00 PM - 3:30 PM';
                $course['location'] = 'Room 205, Computer Science Building';
                break;
            case 'CS 301':
                $course['description'] = 'Advanced algorithms, data structures, and computational complexity analysis.';
                $course['schedule'] = 'Mon, Wed 1:00 PM - 2:30 PM';
                $course['location'] = 'Room 402, Engineering Building';
                break;
            case 'CS 401':
                $course['description'] = 'Software engineering principles, design patterns, and project management methodologies.';
                $course['schedule'] = 'Tue, Thu 10:00 AM - 11:30 AM';
                $course['location'] = 'Room 315, Computer Science Building';
                break;
            default:
                $course['description'] = 'Course description not available.';
                $course['schedule'] = 'Schedule TBA';
                $course['location'] = 'Location TBA';
        }
    }
    unset($course);
    
} else {
    // STUDENT VIEW
    // Get student info
    $username = $_SESSION['username'] ?? 'student1';
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
        $studentEmail = $username . '@gmail.com';
    }
    
    // Build course materials from shared notes (latest version per title)
    $notesByTitle = [];
    foreach ($notes as $n) {
        $t = $n['title'] ?? '';
        if ($t === '') {
            continue;
        }
        if (!isset($notesByTitle[$t])) {
            $notesByTitle[$t] = [];
        }
        $notesByTitle[$t][] = $n;
    }

    $latestNotes = [];
    foreach ($notesByTitle as $title => $versions) {
        usort($versions, function ($a, $b) {
            return (int)($b['version'] ?? 1) <=> (int)($a['version'] ?? 1);
        });
        $latest = $versions[0];

        // Determine course and basic metadata
        $courseCode = $latest['course_code'] ?? null;
        if (!$courseCode) {
            continue;
        }

        $fileSize = 0;
        if (!empty($latest['attachment_stored'])) {
            $filePath = __DIR__ . '/uploads/' . $latest['attachment_stored'];
            if (file_exists($filePath)) {
                $fileSize = filesize($filePath);
            } elseif (isset($latest['file_size'])) {
                $fileSize = (int)$latest['file_size'];
            }
        }
        $fileSizeFormatted = '0 MB';
        if ($fileSize > 0) {
            $fileSizeMB = round($fileSize / (1024 * 1024), 1);
            $fileSizeFormatted = $fileSizeMB . ' MB';
        }

        $latestNotes[] = [
            'title' => $latest['title'],
            'course_code' => $courseCode,
            'attachment_stored' => $latest['attachment_stored'] ?? null,
            'file_size' => $fileSizeFormatted,
            'last_updated' => $latest['created_at'] ?? '',
            'last_updated_formatted' => isset($latest['created_at']) ? date('n/j/Y', strtotime($latest['created_at'])) : '',
        ];
    }

    foreach ($latestNotes as $note) {
        $code = $note['course_code'];
        if (!isset($courseMaterials[$code])) {
            $courseMaterials[$code] = [];
        }

        if (!empty($note['attachment_stored'])) {
            $courseMaterials[$code][] = [
                'title' => $note['title'],
                'file' => $note['attachment_stored'],
                'size' => $note['file_size'],
                'date' => $note['last_updated_formatted'],
            ];
        }
    }

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
    
    // Get all course codes that the student has activity in
    $studentCourseCodes = [];
    
    // Get courses from assignments (where student has assignments)
    foreach ($assignments as $ass) {
        $courseCode = $ass['course_code'] ?? null;
        if ($courseCode && !in_array($courseCode, $studentCourseCodes)) {
            $studentCourseCodes[] = $courseCode;
        }
    }
    
    // Get courses from student's submissions
    foreach ($studentSubmissions as $sub) {
        $assignmentId = (int)$sub['assignment_id'];
        foreach ($assignments as $ass) {
            if ((int)$ass['id'] === $assignmentId) {
                $courseCode = $ass['course_code'] ?? null;
                if ($courseCode && !in_array($courseCode, $studentCourseCodes)) {
                    $studentCourseCodes[] = $courseCode;
                }
                break;
            }
        }
    }
    
    // Get courses from notes (where notes are available)
    foreach ($notes as $note) {
        $courseCode = $note['course_code'] ?? null;
        if ($courseCode && !in_array($courseCode, $studentCourseCodes)) {
            $studentCourseCodes[] = $courseCode;
        }
    }
    
    // Define all possible courses with instructor and schedule
    $teacherUsername = 'teacher1'; // Use the logged-in teacher's username
    $allCourses = [
        [
            'code' => 'CS 101',
            'name' => 'Web Development',
            'instructor' => $teacherUsername,
            'schedule' => 'Mon, Wed, Fri 10:00 AM',
            'progress' => 65,
            'current_grade' => 87,
        ],
        [
            'code' => 'CS 201',
            'name' => 'Database Systems',
            'instructor' => $teacherUsername,
            'schedule' => 'Tue, Thu 2:00 PM',
            'progress' => 72,
            'current_grade' => 91,
        ],
        [
            'code' => 'CS 301',
            'name' => 'Algorithms',
            'instructor' => $teacherUsername,
            'schedule' => 'Mon, Wed 1:00 PM',
            'progress' => 58,
            'current_grade' => 85,
        ],
        [
            'code' => 'CS 401',
            'name' => 'Software Engineering',
            'instructor' => $teacherUsername,
            'schedule' => 'Tue, Thu 10:00 AM',
            'progress' => 45,
            'current_grade' => 89,
        ],
    ];
    
    // Filter courses to only include those the student has activity in
    $courses = [];
    foreach ($allCourses as $course) {
        if (in_array($course['code'], $studentCourseCodes)) {
            $courses[] = $course;
        }
    }
    
    // Calculate pending assignments and grades for each course
    $now = time();
    foreach ($courses as &$course) {
        $courseCode = $course['code'];
        $courseNum = (int)str_replace('CS ', '', $courseCode);
        
        // Get assignments for this course
        $courseAssignments = [];
        foreach ($assignments as $ass) {
            $assCourseCode = $ass['course_code'] ?? 'CS ' . (100 + (int)$ass['id']);
            if ($assCourseCode === $courseCode) {
                $courseAssignments[] = $ass;
            }
        }
        
        // Count pending assignments (not submitted, deadline not passed)
        $pendingCount = 0;
        foreach ($courseAssignments as $ass) {
            $deadlineTs = strtotime($ass['deadline']);
            $isPastDeadline = $now > $deadlineTs;
            
            $hasSubmitted = false;
            foreach ($studentSubmissions as $sub) {
                if ((int)$sub['assignment_id'] === (int)$ass['id']) {
                    $hasSubmitted = true;
                    break;
                }
            }
            
            if (!$hasSubmitted && !$isPastDeadline) {
                $pendingCount++;
            }
        }
        
        $course['pending'] = $pendingCount;
        
        // Calculate current grade from submissions
        $totalScore = 0;
        $totalMaxScore = 0;
        $gradedCount = 0;
        foreach ($courseAssignments as $ass) {
            foreach ($studentSubmissions as $sub) {
                if ((int)$sub['assignment_id'] === (int)$ass['id']) {
                    if ($sub['score'] !== null && $sub['score'] !== '') {
                        $totalScore += (float)$sub['score'];
                        $totalMaxScore += (float)($ass['max_score'] ?? 100);
                        $gradedCount++;
                        break;
                    }
                }
            }
        }
        
        if ($gradedCount > 0) {
            $course['current_grade'] = round(($totalScore / $totalMaxScore) * 100);
        }
        
        // Calculate progress based on completed assignments
        $completed = 0;
        foreach ($courseAssignments as $ass) {
            foreach ($studentSubmissions as $sub) {
                if ((int)$sub['assignment_id'] === (int)$ass['id']) {
                    $completed++;
                    break;
                }
            }
        }
        if (count($courseAssignments) > 0) {
            $course['progress'] = round(($completed / count($courseAssignments)) * 100);
        }
        
        // Prepare course details for modal
        switch($courseCode) {
            case 'CS 101':
                $course['description'] = 'Learn the fundamentals of web development including HTML, CSS, JavaScript, and modern frameworks.';
                break;
            case 'CS 201':
                $course['description'] = 'Explore database design, SQL queries, normalization, and database management systems.';
                break;
            case 'CS 301':
                $course['description'] = 'Study algorithm design, analysis, data structures, and computational complexity.';
                break;
            case 'CS 401':
                $course['description'] = 'Understand software engineering principles, design patterns, and project management.';
                break;
            default:
                $course['description'] = 'Course description not available.';
        }
        
        $course['duration'] = '9/1/2024 - 12/15/2024';
        $course['students_count'] = rand(35, 45);
        
        // Get upcoming events (assignments and exams)
        $upcomingEvents = [];
        foreach ($courseAssignments as $ass) {
            $deadlineTs = strtotime($ass['deadline']);
            if ($deadlineTs >= $now) {
                $upcomingEvents[] = [
                    'type' => 'assignment',
                    'title' => $ass['title'] . ' Due',
                    'date' => date('m/d/Y', $deadlineTs),
                    'timestamp' => $deadlineTs
                ];
            }
        }
        // Sort by date
        usort($upcomingEvents, function($a, $b) {
            return $a['timestamp'] - $b['timestamp'];
        });
        $course['upcoming_events'] = array_slice($upcomingEvents, 0, 5); // Limit to 5 events
    }
    unset($course);
}

// Get current page for active menu highlighting
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses - eduQuest <?php echo ucfirst($role); ?> Portal</title>
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
            <?php if ($role === 'student'): ?>
            margin-bottom: 1.5rem;
            <?php else: ?>
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            <?php endif; ?>
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
        
        .create-course-btn {
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
        
        .create-course-btn:hover {
            background: #16a34a;
        }
        
        /* Courses Grid */
        .courses-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }
        
        .course-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .course-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        <?php if ($role === 'student'): ?>
        /* Student Course Card Styles */
        .course-header {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .course-icon-small {
            font-size: 1.2rem;
            color: #3b82f6;
            margin-top: 0.25rem;
        }
        
        .course-info h3 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: #1f2937;
        }
        
        .course-info .instructor {
            font-size: 0.85rem;
            color: #6b7280;
            margin-bottom: 0.5rem;
        }
        
        .course-info .schedule {
            font-size: 0.85rem;
            color: #6b7280;
        }
        
        .course-metrics {
            display: flex;
            gap: 1.5rem;
            margin: 1rem 0;
            padding: 1rem 0;
            border-top: 1px solid #f3f4f6;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .metric-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .metric-icon {
            font-size: 1rem;
            color: #6b7280;
        }
        
        .metric-text {
            font-size: 0.9rem;
            color: #1f2937;
            font-weight: 500;
        }
        
        .metric-grade {
            font-size: 0.9rem;
            color: #1f2937;
            font-weight: 600;
        }
        
        .course-progress {
            margin: 1rem 0;
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
            position: relative;
        }
        
        .progress-fill {
            height: 100%;
            background: #4b5563;
            border-radius: 4px;
            transition: width 0.3s;
        }
        
        .progress-percentage {
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            font-size: 0.75rem;
            color: #6b7280;
            padding-right: 4px;
        }
        
        .course-actions {
            margin-top: 1rem;
        }
        
        .view-course-btn {
            width: 100%;
            padding: 0.75rem;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            color: #1f2937;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            text-align: center;
            display: block;
        }
        
        .view-course-btn:hover {
            background: #f3f4f6;
            border-color: #d1d5db;
        }
        
        <?php else: ?>
        /* Instructor Course Card Styles */
        .course-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .course-icon {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
        }
        
        .course-info h3 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .course-info p {
            font-size: 0.85rem;
            color: #6b7280;
        }
        
        .course-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .stat-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }
        
        .stat-icon.students {
            background: #dbeafe;
            color: #2563eb;
        }
        
        .stat-icon.assignments {
            background: #fef3c7;
            color: #f59e0b;
        }
        
        .stat-icon.notes {
            background: #e0e7ff;
            color: #6366f1;
        }
        
        .stat-text {
            display: flex;
            flex-direction: column;
        }
        
        .stat-value {
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: #6b7280;
        }
        
        .course-progress {
            margin-bottom: 1.5rem;
        }
        
        .progress-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        
        .progress-label {
            font-size: 0.85rem;
            font-weight: 500;
            color: #6b7280;
        }
        
        .progress-percentage {
            font-size: 0.85rem;
            font-weight: 600;
            color: #1f2937;
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.3s;
        }
        
        .course-actions {
            display: flex;
            gap: 0.75rem;
        }
        
        .course-btn {
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
        
        .course-btn:hover {
            background: #f9fafb;
            border-color: #d1d5db;
        }
        
        .course-btn.primary {
            background: #22c55e;
            color: white;
            border-color: #22c55e;
        }
        
        .course-btn.primary:hover {
            background: #16a34a;
        }
        <?php endif; ?>
        
        @media (max-width: 968px) {
            .courses-grid {
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
        
        /* Course Modal Styles */
        .course-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            overflow-y: auto;
            padding: 2rem;
        }
        
        .course-modal.active {
            display: flex;
        }
        
        .course-modal-content {
            background: white;
            border-radius: 16px;
            max-width: 800px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
        
        .course-modal-header {
            padding: 2rem 2rem 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .course-modal-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: #3b82f6;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }
        
        .course-modal-title {
            flex: 1;
        }
        
        .course-modal-title h2 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: #1f2937;
        }
        
        .course-modal-title p {
            font-size: 0.9rem;
            color: #6b7280;
        }
        
        .course-modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #6b7280;
            cursor: pointer;
            padding: 0.5rem;
            line-height: 1;
        }
        
        .course-modal-close:hover {
            color: #1f2937;
        }
        
        .course-metrics-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .course-metric-card {
            text-align: center;
        }
        
        .course-metric-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.25rem;
        }
        
        .course-metric-label {
            font-size: 0.75rem;
            color: #6b7280;
        }
        
        .course-metric-icon {
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
        }
        
        .course-modal-tabs {
            display: flex;
            border-bottom: 1px solid #e5e7eb;
            padding: 0 2rem;
        }
        
        .course-modal-tab {
            padding: 1rem 1.5rem;
            background: none;
            border: none;
            border-bottom: 2px solid transparent;
            font-size: 0.9rem;
            font-weight: 500;
            color: #6b7280;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .course-modal-tab:hover {
            color: #1f2937;
        }
        
        .course-modal-tab.active {
            color: #3b82f6;
            border-bottom-color: #3b82f6;
        }
        
        .course-modal-body {
            padding: 2rem;
        }
        
        .course-tab-content {
            display: none;
        }
        
        .course-tab-content.active {
            display: block;
        }
        
        .course-description {
            margin-bottom: 2rem;
        }
        
        .course-description h3 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.75rem;
            color: #1f2937;
        }
        
        .course-description p {
            color: #6b7280;
            line-height: 1.6;
        }
        
        .course-info-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
            color: #6b7280;
        }
        
        .course-info-icon {
            width: 20px;
            text-align: center;
        }
        
        .upcoming-events {
            margin-top: 2rem;
        }
        
        .upcoming-events h3 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #1f2937;
        }
        
        .event-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .event-item:last-child {
            border-bottom: none;
        }
        
        .event-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #dbeafe;
            color: #2563eb;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
        }
        
        .event-content {
            flex: 1;
        }
        
        .event-title {
            font-weight: 500;
            color: #1f2937;
            margin-bottom: 0.25rem;
        }
        
        .event-date {
            font-size: 0.85rem;
            color: #6b7280;
        }
        
        .event-tag {
            display: inline-block;
            padding: 0.125rem 0.5rem;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 500;
            background: #e0e7ff;
            color: #6366f1;
            margin-left: 0.5rem;
        }
        
        .course-bottom-metrics {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e5e7eb;
        }
        
        .course-bottom-metric-card {
            text-align: center;
            padding: 1rem;
            background: #f9fafb;
            border-radius: 8px;
        }
        
        .course-bottom-metric-icon {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .course-bottom-metric-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.25rem;
        }
        
        .course-bottom-metric-label {
            font-size: 0.75rem;
            color: #6b7280;
        }

        /* Course Materials list inside modal */
        .materials-list {
            margin-top: 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .material-item {
            padding: 0.75rem 0.5rem;
            border-bottom: 1px solid #f3f4f6;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }

        .material-main {
            flex: 1;
        }

        .material-title {
            font-size: 0.95rem;
            font-weight: 500;
            color: #1f2937;
        }

        .material-meta {
            font-size: 0.8rem;
            color: #6b7280;
            margin-top: 0.15rem;
        }

        .material-actions {
            display: flex;
            gap: 0.5rem;
            flex-shrink: 0;
        }

        .material-btn {
            padding: 0.4rem 0.75rem;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            color: #1f2937;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .material-btn.primary {
            background: #3b82f6;
            border-color: #3b82f6;
            color: #ffffff;
        }

        .material-btn:hover {
            background: #e5e7eb;
        }

        .material-btn.primary:hover {
            background: #2563eb;
        }
        
        @media (max-width: 768px) {
            .course-metrics-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .course-modal-content {
                margin: 1rem;
                max-height: 95vh;
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
                    <div class="header-title">eduQuest <?php echo ucfirst($role); ?> Portal</div>
                </div>
                <div class="header-right">
                    <div class="user-profile">
                        <div class="user-avatar"><?php echo htmlspecialchars($initials); ?></div>
                        <div class="user-info">
                            <div class="user-name"><?php echo htmlspecialchars($role === 'student' ? $studentName : $instructorName); ?></div>
                            <div class="user-email"><?php echo htmlspecialchars($role === 'student' ? $studentEmail : $instructorEmail); ?></div>
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
                        <h1>My Courses</h1>
                        <p class="page-subtitle"><?php echo $role === 'student' ? 'View your enrolled courses' : 'Manage your teaching courses'; ?></p>
                    </div>
                    <?php if ($role === 'teacher'): ?>
                    <a href="#" class="create-course-btn">
                        <span>+</span>
                        <span>Create Course</span>
                    </a>
                    <?php endif; ?>
                </div>
                
                <!-- Courses Grid -->
                <div class="courses-grid">
                    <?php foreach ($courses as $course): ?>
                        <div class="course-card">
                            <?php if ($role === 'student'): ?>
                            <!-- Student Course Card -->
                            <div class="course-header">
                                <span class="course-icon-small">📖</span>
                                <div class="course-info">
                                    <h3><?php echo htmlspecialchars($course['code'] . ' - ' . $course['name']); ?></h3>
                                    <div class="instructor"><?php echo htmlspecialchars($course['instructor']); ?></div>
                                    <div class="schedule"><?php echo htmlspecialchars($course['schedule']); ?></div>
                                </div>
                            </div>
                            
                            <div class="course-metrics">
                                <div class="metric-item">
                                    <span class="metric-icon">📄</span>
                                    <span class="metric-text"><?php echo htmlspecialchars($course['pending'] ?? 0); ?> Pending</span>
                                </div>
                                <div class="metric-item">
                                    <span class="metric-grade"><?php echo htmlspecialchars($course['current_grade'] ?? 0); ?>% Current Grade</span>
                                </div>
                            </div>
                            
                            <div class="course-progress">
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo htmlspecialchars($course['progress'] ?? 0); ?>%;"></div>
                                    <span class="progress-percentage"><?php echo htmlspecialchars($course['progress'] ?? 0); ?>%</span>
                                </div>
                            </div>
                            
                            <div class="course-actions">
                                <button onclick="openCourseModal('<?php echo htmlspecialchars($course['code'], ENT_QUOTES); ?>')" class="view-course-btn">View</button>
                            </div>
                            <?php else: ?>
                            <!-- Instructor Course Card -->
                            <div class="course-header">
                                <div class="course-icon" style="background: <?php echo htmlspecialchars($course['color']); ?>;">
                                    <?php echo htmlspecialchars($course['icon']); ?>
                                </div>
                                <div class="course-info">
                                    <h3><?php echo htmlspecialchars($course['code'] . ': ' . $course['name']); ?></h3>
                                    <p><?php echo htmlspecialchars($course['semester']); ?></p>
                                </div>
                            </div>
                            
                            <div class="course-stats">
                                <div class="stat-item">
                                    <div class="stat-icon students">👥</div>
                                    <div class="stat-text">
                                        <span class="stat-value"><?php echo htmlspecialchars($course['students']); ?></span>
                                        <span class="stat-label">Students</span>
                                    </div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-icon assignments">📄</div>
                                    <div class="stat-text">
                                        <span class="stat-value"><?php echo htmlspecialchars($course['assignments']); ?></span>
                                        <span class="stat-label">Assignments</span>
                                    </div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-icon notes">📖</div>
                                    <div class="stat-text">
                                        <span class="stat-value"><?php echo htmlspecialchars($course['notes']); ?></span>
                                        <span class="stat-label">Notes</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="course-progress">
                                <div class="progress-header">
                                    <span class="progress-label">Course Progress</span>
                                    <span class="progress-percentage"><?php echo htmlspecialchars($course['progress']); ?>%</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo htmlspecialchars($course['progress']); ?>%; background: <?php echo htmlspecialchars($course['color']); ?>;"></div>
                                </div>
                            </div>
                            
                            <div class="course-actions">
                                <a href="assignments.php?course_code=<?php echo urlencode($course['code']); ?>" class="course-btn primary">Manage</a>
                                <button onclick="openTeacherCourseModal('<?php echo htmlspecialchars($course['code'], ENT_QUOTES); ?>')" class="course-btn">View Details</button>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Teacher Course Detail Modal -->
    <?php if ($role === 'teacher'): ?>
    <div id="teacherCourseModal" class="course-modal">
        <div class="course-modal-content">
            <div class="course-modal-header">
                <div class="course-modal-icon" id="teacherModalCourseIcon">📖</div>
                <div class="course-modal-title">
                    <h2 id="teacherModalCourseTitle">CS 101 - Web Development</h2>
                    <p id="teacherModalCourseSemester">Fall 2025</p>
                </div>
                <button class="course-modal-close" onclick="closeTeacherCourseModal()">&times;</button>
            </div>
            
            <div class="course-metrics-grid">
                <div class="course-metric-card">
                    <div class="course-metric-icon">🏆</div>
                    <div class="course-metric-value" id="teacherModalAvgGrade">85.2%</div>
                    <div class="course-metric-label">Average Grade</div>
                </div>
                <div class="course-metric-card">
                    <div class="course-metric-icon">✓</div>
                    <div class="course-metric-value" id="teacherModalAttendance">94.6%</div>
                    <div class="course-metric-label">Attendance Rate</div>
                </div>
                <div class="course-metric-card">
                    <div class="course-metric-icon">🎯</div>
                    <div class="course-metric-value" id="teacherModalCompletion">65%</div>
                    <div class="course-metric-label">Completion Rate</div>
                </div>
                <div class="course-metric-card">
                    <div class="course-metric-icon">👥</div>
                    <div class="course-metric-value" id="teacherModalActiveStudents">35</div>
                    <div class="course-metric-label">Active Students</div>
                </div>
            </div>
            
            <div class="course-modal-tabs">
                <button class="course-modal-tab active" onclick="switchTeacherTab('overview', this)">Overview</button>
                <button class="course-modal-tab" onclick="switchTeacherTab('students', this)">Students</button>
                <button class="course-modal-tab" onclick="switchTeacherTab('performance', this)">Performance</button>
                <button class="course-modal-tab" onclick="switchTeacherTab('schedule', this)">Schedule</button>
            </div>
            
            <div class="course-modal-body">
                <!-- Overview Tab -->
                <div id="teacher-tab-overview" class="course-tab-content active">
                    <div class="course-description">
                        <h3>Course Description</h3>
                        <p id="teacherModalDescription">Introduction to modern web development using HTML, CSS, JavaScript, and popular frameworks.</p>
                    </div>
                    
                    <div class="course-info-item">
                        <span class="course-info-icon">📅</span>
                        <span id="teacherModalSchedule">Mon, Wed, Fri 10:00 AM - 11:30 AM</span>
                    </div>
                    
                    <div class="course-info-item">
                        <span class="course-info-icon">🏢</span>
                        <span id="teacherModalLocation">Room 301, Computer Science Building</span>
                    </div>
                    
                    <div class="course-bottom-metrics">
                        <div class="course-bottom-metric-card">
                            <div class="course-bottom-metric-icon">📄</div>
                            <div class="course-bottom-metric-value" id="teacherModalTotalAssignments">8</div>
                            <div class="course-bottom-metric-label">Total Assignments</div>
                        </div>
                        <div class="course-bottom-metric-card">
                            <div class="course-bottom-metric-icon">📖</div>
                            <div class="course-bottom-metric-value" id="teacherModalCourseNotes">12</div>
                            <div class="course-bottom-metric-label">Course Notes</div>
                        </div>
                        <div class="course-bottom-metric-card">
                            <div class="course-bottom-metric-icon">👥</div>
                            <div class="course-bottom-metric-value" id="teacherModalEnrolledStudents">35</div>
                            <div class="course-bottom-metric-label">Enrolled Students</div>
                        </div>
                    </div>
                </div>
                
                <!-- Students Tab -->
                <div id="teacher-tab-students" class="course-tab-content">
                    <div class="course-description">
                        <h3>Students</h3>
                        <p style="color: #6b7280;">Student roster and enrollment information will be displayed here.</p>
                    </div>
                </div>
                
                <!-- Performance Tab -->
                <div id="teacher-tab-performance" class="course-tab-content">
                    <div class="course-description">
                        <h3>Performance</h3>
                        <p style="color: #6b7280;">Course performance analytics and statistics will be displayed here.</p>
                    </div>
                </div>
                
                <!-- Schedule Tab -->
                <div id="teacher-tab-schedule" class="course-tab-content">
                    <div class="course-description">
                        <h3>Schedule</h3>
                        <p style="color: #6b7280;">Detailed course schedule and calendar will be displayed here.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Teacher course data from PHP
        const teacherCoursesData = <?php echo json_encode($courses); ?>;
        
        function openTeacherCourseModal(courseCode) {
            const course = teacherCoursesData.find(c => c.code === courseCode);
            if (!course) return;
            
            // Populate modal with course data
            document.getElementById('teacherModalCourseTitle').textContent = course.code + ' - ' + course.name;
            document.getElementById('teacherModalCourseSemester').textContent = course.semester || 'Fall 2025';
            const iconElement = document.getElementById('teacherModalCourseIcon');
            iconElement.textContent = course.icon || '📖';
            iconElement.style.background = course.color || '#3b82f6';
            document.getElementById('teacherModalAvgGrade').textContent = (course.average_grade || 85.2) + '%';
            document.getElementById('teacherModalAttendance').textContent = (course.attendance_rate || 94.6) + '%';
            document.getElementById('teacherModalCompletion').textContent = (course.completion_rate || 65) + '%';
            document.getElementById('teacherModalActiveStudents').textContent = course.students || 35;
            document.getElementById('teacherModalDescription').textContent = course.description || 'Course description not available.';
            document.getElementById('teacherModalSchedule').textContent = course.schedule || 'Schedule not available';
            document.getElementById('teacherModalLocation').textContent = course.location || 'Location not available';
            document.getElementById('teacherModalTotalAssignments').textContent = course.assignments || 0;
            document.getElementById('teacherModalCourseNotes').textContent = course.notes || 0;
            document.getElementById('teacherModalEnrolledStudents').textContent = course.students || 35;
            
            // Show modal
            document.getElementById('teacherCourseModal').classList.add('active');
            
            // Reset to Overview tab
            switchTeacherTab('overview');
        }
        
        function closeTeacherCourseModal() {
            document.getElementById('teacherCourseModal').classList.remove('active');
        }
        
        function switchTeacherTab(tabName, buttonElement) {
            // Hide all tabs
            document.querySelectorAll('#teacherCourseModal .course-tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all tab buttons
            document.querySelectorAll('#teacherCourseModal .course-modal-tab').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById('teacher-tab-' + tabName).classList.add('active');
            
            // Add active class to clicked tab button
            if (buttonElement) {
                buttonElement.classList.add('active');
            }
        }
        
        // Close modal when clicking outside
        document.getElementById('teacherCourseModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeTeacherCourseModal();
            }
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeTeacherCourseModal();
            }
        });
    </script>
    <?php endif; ?>
    
    <!-- Course Detail Modal -->
    <?php if ($role === 'student'): ?>
    <div id="courseModal" class="course-modal">
        <div class="course-modal-content">
            <div class="course-modal-header">
                <div class="course-modal-icon" id="modalCourseIcon">📖</div>
                <div class="course-modal-title">
                    <h2 id="modalCourseTitle">CS 101 - Web Development</h2>
                    <p id="modalCourseInstructor">teacher1</p>
                </div>
                <button class="course-modal-close" onclick="closeCourseModal()">&times;</button>
            </div>
            
            <div class="course-metrics-grid">
                <div class="course-metric-card">
                    <div class="course-metric-icon">🏆</div>
                    <div class="course-metric-value" id="modalCurrentGrade">87%</div>
                    <div class="course-metric-label">Current Grade</div>
                </div>
                <div class="course-metric-card">
                    <div class="course-metric-icon">🎯</div>
                    <div class="course-metric-value" id="modalProgress">65%</div>
                    <div class="course-metric-label">Progress</div>
                </div>
                <div class="course-metric-card">
                    <div class="course-metric-icon">📄</div>
                    <div class="course-metric-value" id="modalPending">3</div>
                    <div class="course-metric-label">Pending</div>
                </div>
                <div class="course-metric-card">
                    <div class="course-metric-icon">👥</div>
                    <div class="course-metric-value" id="modalStudents">45</div>
                    <div class="course-metric-label">Students</div>
                </div>
            </div>
            
            <div class="course-modal-tabs">
                <button class="course-modal-tab active" onclick="switchTab('overview', this)">Overview</button>
                <button class="course-modal-tab" onclick="switchTab('materials', this)">Materials</button>
                <button class="course-modal-tab" onclick="switchTab('grades', this)">Grades</button>
                <button class="course-modal-tab" onclick="switchTab('schedule', this)">Schedule</button>
            </div>
            
            <div class="course-modal-body">
                <!-- Overview Tab -->
                <div id="tab-overview" class="course-tab-content active">
                    <div class="course-description">
                        <h3>Course Description</h3>
                        <p id="modalDescription">Learn the fundamentals of web development including HTML, CSS, JavaScript, and modern frameworks.</p>
                    </div>
                    
                    <div class="course-info-item">
                        <span class="course-info-icon">📅</span>
                        <span id="modalSchedule">Mon, Wed, Fri 10:00 AM</span>
                    </div>
                    
                    <div class="course-info-item">
                        <span class="course-info-icon">⏰</span>
                        <span id="modalDuration">9/1/2024 - 12/15/2024</span>
                    </div>
                    
                    <div class="upcoming-events">
                        <h3>Upcoming Events</h3>
                        <div id="modalUpcomingEvents">
                            <!-- Events will be populated by JavaScript -->
                        </div>
                    </div>
                </div>
                
                <!-- Materials Tab -->
                <div id="tab-materials" class="course-tab-content">
                    <div class="course-description">
                        <h3>Course Materials</h3>
                        <p id="materialsDescription" style="color: #6b7280;">Course materials and resources will be available here.</p>
                    </div>
                    <div id="course-materials-list" class="materials-list"></div>
                </div>
                
                <!-- Grades Tab -->
                <div id="tab-grades" class="course-tab-content">
                    <div class="course-description">
                        <h3>Grades</h3>
                        <p style="color: #6b7280;">Your grades for this course will be displayed here.</p>
                    </div>
                </div>
                
                <!-- Schedule Tab -->
                <div id="tab-schedule" class="course-tab-content">
                    <div class="course-description">
                        <h3>Course Schedule</h3>
                        <p style="color: #6b7280;">Detailed course schedule will be available here.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Course data from PHP
        const coursesData = <?php echo json_encode($courses); ?>;
        const courseMaterialsData = <?php echo json_encode($courseMaterials); ?>;
        
        function openCourseModal(courseCode) {
            const course = coursesData.find(c => c.code === courseCode);
            if (!course) return;
            
            // Populate modal with course data
            document.getElementById('modalCourseTitle').textContent = course.code + ' - ' + course.name;
            document.getElementById('modalCourseInstructor').textContent = course.instructor || 'Instructor';
            document.getElementById('modalCurrentGrade').textContent = (course.current_grade || 0) + '%';
            document.getElementById('modalProgress').textContent = (course.progress || 0) + '%';
            document.getElementById('modalPending').textContent = course.pending || 0;
            document.getElementById('modalStudents').textContent = course.students_count || 45;
            document.getElementById('modalDescription').textContent = course.description || 'Course description not available.';
            document.getElementById('modalSchedule').textContent = course.schedule || 'Schedule not available';
            document.getElementById('modalDuration').textContent = course.duration || 'Duration not available';
            
            // Populate upcoming events
            const eventsContainer = document.getElementById('modalUpcomingEvents');
            eventsContainer.innerHTML = '';
            
            if (course.upcoming_events && course.upcoming_events.length > 0) {
                course.upcoming_events.forEach(event => {
                    const eventDiv = document.createElement('div');
                    eventDiv.className = 'event-item';
                    eventDiv.innerHTML = `
                        <div class="event-icon">✓</div>
                        <div class="event-content">
                            <div class="event-title">
                                ${event.title}
                                <span class="event-tag">${event.type}</span>
                            </div>
                            <div class="event-date">${event.date}</div>
                        </div>
                    `;
                    eventsContainer.appendChild(eventDiv);
                });
            } else {
                eventsContainer.innerHTML = '<p style="color: #6b7280; padding: 1rem 0;">No upcoming events.</p>';
            }

            // Populate materials for this course
            populateCourseMaterials(course.code);

            // Show modal
            document.getElementById('courseModal').classList.add('active');
            
            // Reset to Overview tab
            switchTab('overview');
        }
        
        function closeCourseModal() {
            document.getElementById('courseModal').classList.remove('active');
        }
        
        function switchTab(tabName, buttonElement) {
            // Hide all tabs
            document.querySelectorAll('.course-tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.course-modal-tab').forEach(btn => {
                btn.classList.remove('active');
            });

            // Show selected tab
            document.getElementById('tab-' + tabName).classList.add('active');
            
            // Add active class to clicked tab button
            if (buttonElement) {
                buttonElement.classList.add('active');
            }

            // If switching to Materials, ensure list is up to date for current course
            if (tabName === 'materials') {
                const titleEl = document.getElementById('modalCourseTitle');
                if (titleEl) {
                    const text = titleEl.textContent || '';
                    const code = text.split('-')[0].trim(); // e.g. "CS 101 - Web Development"
                    if (code) {
                        populateCourseMaterials(code);
                    }
                }
            }
        }

        function populateCourseMaterials(courseCode) {
            const list = document.getElementById('course-materials-list');
            const desc = document.getElementById('materialsDescription');
            if (!list || !desc) return;

            const items = courseMaterialsData[courseCode] || [];
            list.innerHTML = '';

            if (!items.length) {
                desc.textContent = 'No materials have been shared yet for this course.';
                return;
            }

            desc.textContent = 'Download and preview materials shared for this course.';

            items.forEach(item => {
                const row = document.createElement('div');
                row.className = 'material-item';

                const main = document.createElement('div');
                main.className = 'material-main';
                const title = document.createElement('div');
                title.className = 'material-title';
                title.textContent = item.title || 'Material';
                const meta = document.createElement('div');
                meta.className = 'material-meta';
                const size = item.size || '';
                const date = item.date || '';
                meta.textContent = [size, date].filter(Boolean).join(' • ');
                main.appendChild(title);
                main.appendChild(meta);

                const actions = document.createElement('div');
                actions.className = 'material-actions';

                if (item.file) {
                    const downloadLink = document.createElement('a');
                    downloadLink.className = 'material-btn primary';
                    downloadLink.href = 'download.php?file=' + encodeURIComponent(item.file);
                    downloadLink.textContent = 'Download';
                    actions.appendChild(downloadLink);

                    const previewBtn = document.createElement('button');
                    previewBtn.type = 'button';
                    previewBtn.className = 'material-btn';
                    previewBtn.textContent = 'Preview';
                    previewBtn.addEventListener('click', () => {
                        window.open('preview.php?file=' + encodeURIComponent(item.file), '_blank');
                    });
                    actions.appendChild(previewBtn);
                }

                row.appendChild(main);
                row.appendChild(actions);
                list.appendChild(row);
            });
        }
        
        // Close modal when clicking outside
        document.getElementById('courseModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeCourseModal();
            }
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeCourseModal();
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>
