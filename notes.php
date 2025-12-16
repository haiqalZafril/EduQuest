<?php
session_start();
require_once __DIR__ . '/data_store.php';

// Require login
if (!isset($_SESSION['role'])) {
    header('Location: role_selection.php');
    exit;
}
$role = $_SESSION['role'];

$notes = eq_load_data('notes');
$message = '';

// Handle creating a new note (instructor)
if ($role === 'teacher' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_note') {
    $title = trim($_POST['title'] ?? '');
    $topic = trim($_POST['topic'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $course_code = trim($_POST['course_code'] ?? 'CS 101');

    if ($title === '') {
        $message = 'Title is required.';
    } else {
        // Optional file attachment
        $attachmentOriginal = null;
        $attachmentStored = null;
        $fileSize = 0;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['name'] !== '') {
            $uploadsDir = __DIR__ . '/uploads';
            if (!is_dir($uploadsDir)) {
                mkdir($uploadsDir, 0777, true);
            }
            $originalName = basename($_FILES['attachment']['name']);
            $safeName = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $originalName);
            $targetName = 'note_' . time() . '_' . $safeName;
            $targetPath = $uploadsDir . '/' . $targetName;
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $targetPath)) {
                $attachmentOriginal = $originalName;
                $attachmentStored = $targetName;
                $fileSize = filesize($targetPath);
            }
        }

        // Version number: count existing notes with same title + 1
        $version = 1;
        foreach ($notes as $n) {
            if ($n['title'] === $title && isset($n['version']) && (int)$n['version'] >= $version) {
                $version = (int)$n['version'] + 1;
            }
        }

        $id = eq_next_id($notes);
        $notes[] = [
            'id' => $id,
            'title' => $title,
            'topic' => $topic,
            'content' => $content,
            'created_at' => date('Y-m-d H:i:s'),
            'version' => $version,
            'attachment_name' => $attachmentOriginal,
            'attachment_stored' => $attachmentStored,
            'file_size' => $fileSize,
            'course_code' => $course_code,
            'downloads' => 0,
            'status' => 'shared'
        ];
        eq_save_data('notes', $notes);
        $message = 'Note saved (version ' . $version . ').';
    }
}

// Course mapping
$courseNames = [
    'CS 101' => 'Web Development',
    'CS 201' => 'Database Systems',
    'CS 301' => 'Algorithms',
    'CS 401' => 'Software Engineering',
];

// Instructor mapping for courses (for student view)
$instructorMapping = [
    'CS 101' => 'Dr. Sarah Johnson',
    'CS 201' => 'Prof. Michael Chen',
    'CS 301' => 'Dr. Emily White',
    'CS 401' => 'Prof. David Brown',
];

// Get user info based on role
if ($role === 'student') {
    $username = $_SESSION['username'] ?? 'student1';
    $studentNames = [
        'student1' => ['name' => 'student1', 'email' => 'student@gmail.com', 'initials' => 'S1'],
        'student2' => ['name' => 'John Doe', 'email' => 'student2@gmail.com', 'initials' => 'JD'],
    ];
    if (isset($studentNames[$username])) {
        $userName = $studentNames[$username]['name'];
        $userEmail = $studentNames[$username]['email'];
        $initials = $studentNames[$username]['initials'];
    } else {
        $parts = explode(' ', ucwords(str_replace(['student', '_'], ['', ' '], $username)));
        if (count($parts) >= 2) {
            $userName = $parts[0] . ' ' . $parts[1];
            $initials = strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
        } else {
            $userName = ucfirst($username) . ' Student';
            $initials = strtoupper(substr($username, 0, 2));
        }
        $userEmail = $username . '@gmail.com';
    }
} else {
    $username = $_SESSION['username'] ?? 'teacher1';
    $userName = 'Dr. ' . ucfirst($username);
    $userEmail = $username . '@gmail.com';
    $initials = strtoupper(substr($username, 0, 1) . substr($username, -1));
}

// Prepare notes for display (get latest version of each note)
$notesByTitle = [];
foreach ($notes as $n) {
    $t = $n['title'];
    if (!isset($notesByTitle[$t])) {
        $notesByTitle[$t] = [];
    }
    $notesByTitle[$t][] = $n;
}

// Get latest version of each note
$latestNotes = [];
foreach ($notesByTitle as $title => $versions) {
    usort($versions, function ($a, $b) {
        return (int)$b['version'] <=> (int)$a['version'];
    });
    $latest = $versions[0];
    
    // Calculate file size if attachment exists
    $fileSize = 0;
    if (!empty($latest['attachment_stored'])) {
        $filePath = __DIR__ . '/uploads/' . $latest['attachment_stored'];
        if (file_exists($filePath)) {
            $fileSize = filesize($filePath);
        } elseif (isset($latest['file_size'])) {
            $fileSize = $latest['file_size'];
        }
    }
    
    // Format file size
    $fileSizeFormatted = '0 MB';
    if ($fileSize > 0) {
        $fileSizeMB = round($fileSize / (1024 * 1024), 1);
        $fileSizeFormatted = $fileSizeMB . ' MB';
    }
    
    $courseCode = $latest['course_code'] ?? 'CS ' . (100 + ((int)$latest['id'] % 4));
    $courseName = $courseNames[$courseCode] ?? 'General';
    
    $latestNotes[] = [
        'id' => $latest['id'],
        'title' => $latest['title'],
        'course_code' => $courseCode,
        'course_name' => $courseName,
        'version' => $latest['version'] ?? 1,
        'status' => $latest['status'] ?? 'shared',
        'downloads' => $latest['downloads'] ?? rand(20, 50),
        'file_size' => $fileSizeFormatted,
        'last_updated' => $latest['created_at'],
        'last_updated_formatted' => date('n/j/Y', strtotime($latest['created_at'])),
        'attachment_stored' => $latest['attachment_stored'] ?? null,
        'attachment_name' => $latest['attachment_name'] ?? null,
        'instructor' => $instructorMapping[$courseCode] ?? 'Instructor',
    ];
}

// Sort by last updated (newest first)
usort($latestNotes, function($a, $b) {
    return strtotime($b['last_updated']) - strtotime($a['last_updated']);
});

// Handle search
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
if ($searchQuery !== '') {
    $latestNotes = array_filter($latestNotes, function($note) use ($searchQuery) {
        return stripos($note['title'], $searchQuery) !== false ||
               stripos($note['course_code'], $searchQuery) !== false ||
               stripos($note['course_name'], $searchQuery) !== false;
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
    <title>Course Notes - eduQuest <?php echo $role === 'student' ? 'Student' : 'Instructor'; ?> Portal</title>
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
        
        .create-notes-btn {
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
        
        .create-notes-btn:hover {
            background: #16a34a;
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
        
        .page-title-section h1 {
            font-size: 1.75rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .page-subtitle {
            color: #6b7280;
            font-size: 0.95rem;
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
        
        .student-view .search-input:focus {
            border-color: #3b82f6;
        }
        
        /* Notes Grid */
        .notes-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }
        
        .note-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            position: relative;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .note-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .note-menu {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            cursor: pointer;
            color: #9ca3af;
            font-size: 1.2rem;
        }
        
        .note-header {
            display: flex;
            align-items: start;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .note-icon {
            width: 48px;
            height: 48px;
            background: #3b82f6;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            flex-shrink: 0;
        }
        
        
        .note-title-section {
            flex: 1;
        }
        
        .note-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .note-course {
            font-size: 0.9rem;
            color: #6b7280;
        }
        
        .note-instructor {
            font-size: 0.85rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }
        
        .note-badges {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        
        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .badge.version {
            background: #f3f4f6;
            color: #6b7280;
        }
        
        .badge.status {
            background: #dcfce7;
            color: #166534;
        }
        
        .note-details {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-size: 0.75rem;
            color: #6b7280;
            margin-bottom: 0.25rem;
        }
        
        .detail-value {
            font-size: 0.9rem;
            font-weight: 600;
            color: #1f2937;
        }
        
        .note-action {
            width: 100%;
        }
        
        .note-actions {
            display: flex;
            gap: 0.75rem;
            margin-top: 1rem;
        }
        
        .download-btn {
            width: 100%;
            padding: 0.75rem;
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
        
        .download-btn:hover {
            background: #f9fafb;
            border-color: #d1d5db;
        }
        
        .download-btn-primary {
            flex: 1;
            padding: 0.75rem;
            border-radius: 8px;
            border: none;
            background: #3b82f6;
            color: white;
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
        
        .download-btn-primary:hover:not(:disabled) {
            background: #2563eb;
        }
        
        .download-btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .preview-btn {
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
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .preview-btn:hover:not(:disabled) {
            background: #f9fafb;
            border-color: #d1d5db;
        }
        
        .preview-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
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
            .notes-grid {
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
            
            .note-details {
                grid-template-columns: 1fr;
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
                    <div class="header-title">eduQuest <?php echo $role === 'student' ? 'Student' : 'Instructor'; ?> Portal</div>
                </div>
                <div class="header-right">
                    <div class="notification-icon">
                        🔔
                        <span class="notification-dot"></span>
                    </div>
                    <div class="user-profile" style="position: relative;">
                        <div class="user-avatar"><?php echo htmlspecialchars($initials); ?></div>
                        <div class="user-info">
                            <div class="user-name"><?php echo htmlspecialchars($userName); ?></div>
                            <div class="user-email"><?php echo htmlspecialchars($userEmail); ?></div>
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
                        <h1>Course Notes</h1>
                        <p class="page-subtitle"><?php echo $role === 'student' ? 'Access learning materials shared by your instructors' : 'Create, organize, and share learning materials'; ?></p>
                    </div>
                    <?php if ($role === 'teacher'): ?>
                        <button onclick="document.getElementById('createModal').style.display='block'" class="create-notes-btn">
                            <span>+</span>
                            <span>Create Notes</span>
                        </button>
                    <?php endif; ?>
                </div>
                
                <?php if ($message !== ''): ?>
                    <div class="message-alert">
                        <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Search Bar -->
                <div class="search-container">
                    <form method="get" class="search-bar">
                        <input type="text" name="search" class="search-input" placeholder="Search notes..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                    </form>
                </div>
                
                <!-- Notes Grid -->
                <div class="notes-grid">
                    <?php if (empty($latestNotes)): ?>
                        <div class="note-card" style="grid-column: 1 / -1;">
                            <p style="color: #6b7280; text-align: center; padding: 2rem;">
                                <?php echo $searchQuery !== '' ? 'No notes found matching your search.' : ($role === 'student' ? 'No notes available yet.' : 'No notes created yet. Click "Create Notes" to get started.'); ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($latestNotes as $note): ?>
                            <div class="note-card">
                                <?php if ($role === 'teacher'): ?>
                                    <div class="note-menu">⋮</div>
                                <?php endif; ?>
                                
                                <div class="note-header">
                                    <div class="note-icon">📄</div>
                                    <div class="note-title-section">
                                        <div class="note-title"><?php echo htmlspecialchars($note['title']); ?></div>
                                        <div class="note-course"><?php echo htmlspecialchars($note['course_code'] . ' - ' . $note['course_name']); ?></div>
                                        <?php if ($role === 'student' && isset($note['instructor'])): ?>
                                            <div class="note-instructor">By <?php echo htmlspecialchars($note['instructor']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if ($role === 'teacher'): ?>
                                    <div class="note-badges">
                                        <span class="badge version">v<?php echo (float)$note['version'] == (int)$note['version'] ? (int)$note['version'] : number_format($note['version'], 1); ?></span>
                                        <span class="badge status"><?php echo ucfirst($note['status']); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="note-details">
                                    <?php if ($role === 'student'): ?>
                                        <div class="detail-item">
                                            <span class="detail-label">Size</span>
                                            <span class="detail-value"><?php echo $note['file_size']; ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <span class="detail-label">Uploaded</span>
                                            <span class="detail-value"><?php echo $note['last_updated_formatted']; ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <span class="detail-label">Downloads</span>
                                            <span class="detail-value"><?php echo $note['downloads']; ?></span>
                                        </div>
                                    <?php else: ?>
                                        <div class="detail-item">
                                            <span class="detail-label">Downloads</span>
                                            <span class="detail-value"><?php echo $note['downloads']; ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <span class="detail-label">Size</span>
                                            <span class="detail-value"><?php echo $note['file_size']; ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <span class="detail-label">Last Updated</span>
                                            <span class="detail-value"><?php echo $note['last_updated_formatted']; ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="note-actions">
                                    <?php if ($role === 'student'): ?>
                                        <?php if ($note['attachment_stored']): ?>
                                            <a href="download.php?file=<?php echo urlencode($note['attachment_stored']); ?>" class="download-btn-primary">
                                                <span>⬇</span>
                                                <span>Download</span>
                                            </a>
                                            <button class="preview-btn" onclick="openPreview('<?php echo urlencode($note['attachment_stored']); ?>', '<?php echo htmlspecialchars($note['title'], ENT_QUOTES); ?>')">
                                                <span>👁</span>
                                                <span>Preview</span>
                                            </button>
                                        <?php else: ?>
                                            <button class="download-btn-primary" disabled style="opacity: 0.5; cursor: not-allowed;">
                                                <span>⬇</span>
                                                <span>No File</span>
                                            </button>
                                            <button class="preview-btn" disabled style="opacity: 0.5; cursor: not-allowed;">
                                                <span>👁</span>
                                                <span>Preview</span>
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="note-action">
                                            <?php if ($note['attachment_stored']): ?>
                                                <a href="download.php?file=<?php echo urlencode($note['attachment_stored']); ?>" class="download-btn">
                                                    <span>⬇</span>
                                                    <span>Download</span>
                                                </a>
                                            <?php else: ?>
                                                <button class="download-btn" disabled style="opacity: 0.5; cursor: not-allowed;">
                                                    <span>⬇</span>
                                                    <span>No File</span>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Create Note Modal -->
    <?php if ($role === 'teacher'): ?>
    <div id="createModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
        <div style="background:white; border-radius:12px; padding:2rem; max-width:600px; width:90%; max-height:90vh; overflow-y:auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                <h2 style="font-size:1.5rem; font-weight:600;">Create New Note</h2>
                <button onclick="document.getElementById('createModal').style.display='none'" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:#6b7280;">&times;</button>
            </div>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create_note">
                <div style="display:flex; flex-direction:column; gap:1rem;">
                    <div>
                        <label style="display:block; margin-bottom:0.5rem; font-weight:500;">Note Title *</label>
                        <input type="text" name="title" required placeholder="e.g., Introduction to HTML & CSS" style="width:100%; padding:0.75rem; border:1px solid #d1d5db; border-radius:8px;">
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
                        <label style="display:block; margin-bottom:0.5rem; font-weight:500;">Topic / Chapter</label>
                        <input type="text" name="topic" placeholder="e.g., Requirements Management" style="width:100%; padding:0.75rem; border:1px solid #d1d5db; border-radius:8px;">
                    </div>
                    <div>
                        <label style="display:block; margin-bottom:0.5rem; font-weight:500;">Summary / Key Points</label>
                        <textarea name="content" rows="4" placeholder="Main ideas, examples, diagrams, etc." style="width:100%; padding:0.75rem; border:1px solid #d1d5db; border-radius:8px;"></textarea>
                    </div>
                    <div>
                        <label style="display:block; margin-bottom:0.5rem; font-weight:500;">Attach file (PDF, slides, etc.)</label>
                        <input type="file" name="attachment" style="width:100%; padding:0.75rem; border:1px solid #d1d5db; border-radius:8px;">
                    </div>
                    <div style="display:flex; gap:1rem; margin-top:1rem;">
                        <button type="submit" style="flex:1; padding:0.75rem; background:#22c55e; color:white; border:none; border-radius:8px; font-weight:500; cursor:pointer;">Create Note</button>
                        <button type="button" onclick="document.getElementById('createModal').style.display='none'" style="flex:1; padding:0.75rem; background:#f3f4f6; color:#1f2937; border:none; border-radius:8px; font-weight:500; cursor:pointer;">Cancel</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Preview Modal -->
    <div id="previewModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.9); z-index:2000; align-items:center; justify-content:center;">
        <div style="width:95%; height:95%; background:#1f2937; border-radius:12px; display:flex; flex-direction:column; overflow:hidden;">
            <div style="padding:1rem 2rem; background:#111827; border-bottom:1px solid #374151; display:flex; justify-content:space-between; align-items:center;">
                <h2 style="font-size:1.1rem; font-weight:600; color:#f9fafb;" id="previewTitle">Preview</h2>
                <button onclick="closePreview()" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:#9ca3af; padding:0.25rem 0.5rem;">&times;</button>
            </div>
            <div style="flex:1; position:relative; overflow:hidden;">
                <iframe id="previewFrame" src="" style="width:100%; height:100%; border:none; background:white;"></iframe>
            </div>
        </div>
    </div>
    
    <script>
        // Close modal when clicking outside
        document.getElementById('createModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                this.style.display = 'none';
            }
        });
        
        // Preview functionality
        function openPreview(fileName, noteTitle) {
            if (!fileName) {
                alert('No file available for preview.');
                return;
            }
            const modal = document.getElementById('previewModal');
            const frame = document.getElementById('previewFrame');
            const title = document.getElementById('previewTitle');
            
            title.textContent = noteTitle || 'Preview';
            frame.src = 'preview.php?file=' + encodeURIComponent(fileName);
            modal.style.display = 'flex';
            
            // Prevent body scroll when modal is open
            document.body.style.overflow = 'hidden';
        }
        
        function closePreview() {
            const modal = document.getElementById('previewModal');
            const frame = document.getElementById('previewFrame');
            
            modal.style.display = 'none';
            frame.src = ''; // Clear iframe to stop loading
            document.body.style.overflow = ''; // Restore body scroll
        }
        
        // Close preview modal when clicking outside
        document.getElementById('previewModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closePreview();
            }
        });
        
        // Close preview modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('previewModal');
                if (modal && modal.style.display === 'flex') {
                    closePreview();
                }
            }
        });
    </script>
</body>
</html>
