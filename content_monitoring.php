<?php
session_start();
require_once __DIR__ . '/data_store.php';

// Require admin login
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: role_selection.php');
    exit;
}

// Load data
$assignments = eq_load_data('assignments');
$notes = eq_load_data('notes');
$contentStatuses = eq_load_data('content_statuses'); // Track approval status

// Course mapping
$courseNames = [
    'CS 101' => 'Web Development',
    'CS 201' => 'Database Systems',
    'CS 301' => 'Algorithms',
    'CS 401' => 'Software Engineering',
    'CS 999' => 'Test Course',
];

// Instructor mapping
$instructorMapping = [
    'CS 101' => 'Dr. Sarah Johnson',
    'CS 201' => 'Prof. Michael Chen',
    'CS 301' => 'Dr. Emily White',
    'CS 401' => 'Prof. David Lee',
    'CS 999' => 'Test Instructor',
];

// Initialize content statuses if not exists
if (empty($contentStatuses)) {
    $contentStatuses = [];
}

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $contentId = (int)($_POST['content_id'] ?? 0);
    $contentType = trim($_POST['content_type'] ?? '');
    
    if ($_POST['action'] === 'update_status') {
        $newStatus = trim($_POST['status'] ?? 'pending');
        $flagReason = trim($_POST['flag_reason'] ?? '');
        
        $contentStatuses[$contentType . '_' . $contentId] = [
            'status' => $newStatus,
            'flag_reason' => $flagReason,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        eq_save_data('content_statuses', $contentStatuses);
    } elseif ($_POST['action'] === 'delete_content') {
        if ($contentType === 'assignment') {
            foreach ($assignments as $index => $ass) {
                if ((int)$ass['id'] === $contentId) {
                    unset($assignments[$index]);
                    $assignments = array_values($assignments);
                    eq_save_data('assignments', $assignments);
                    break;
                }
            }
        } elseif ($contentType === 'note') {
            foreach ($notes as $index => $note) {
                if ((int)$note['id'] === $contentId) {
                    // Delete associated file if exists
                    if (!empty($note['attachment_stored'])) {
                        $filePath = __DIR__ . '/uploads/' . $note['attachment_stored'];
                        if (file_exists($filePath)) {
                            @unlink($filePath);
                        }
                    }
                    unset($notes[$index]);
                    $notes = array_values($notes);
                    eq_save_data('notes', $notes);
                    break;
                }
            }
        }
        // Remove status entry
        if (isset($contentStatuses[$contentType . '_' . $contentId])) {
            unset($contentStatuses[$contentType . '_' . $contentId]);
            eq_save_data('content_statuses', $contentStatuses);
        }
    }
    header('Location: content_monitoring.php');
    exit;
}

// Combine assignments and notes into unified content list
$allContent = [];

// Process assignments
foreach ($assignments as $ass) {
    $contentId = 'assignment_' . $ass['id'];
    $status = $contentStatuses[$contentId]['status'] ?? 'pending';
    $flagReason = $contentStatuses[$contentId]['flag_reason'] ?? '';
    
    $courseCode = $ass['course_code'] ?? 'CS 101';
    $courseName = $courseNames[$courseCode] ?? 'Unknown Course';
    $instructor = 'teacher1';
    
    $allContent[] = [
        'id' => $ass['id'],
        'type' => 'assignment',
        'title' => $ass['title'],
        'course_code' => $courseCode,
        'course_name' => $courseName,
        'instructor' => $instructor,
        'status' => $status,
        'flag_reason' => $flagReason,
        'uploaded_date' => isset($ass['created_at']) ? date('n/j/Y', strtotime($ass['created_at'])) : date('n/j/Y'),
        'file_size' => '0 MB',
    ];
}

// Process notes
foreach ($notes as $note) {
    $contentId = 'note_' . $note['id'];
    $status = $contentStatuses[$contentId]['status'] ?? 'pending';
    $flagReason = $contentStatuses[$contentId]['flag_reason'] ?? '';
    
    $courseCode = $note['course_code'] ?? 'CS 101';
    $courseName = $courseNames[$courseCode] ?? 'Unknown Course';
    $instructor = 'teacher1';
    
    $fileSize = '0 MB';
    if (!empty($note['attachment_stored'])) {
        $filePath = __DIR__ . '/uploads/' . $note['attachment_stored'];
        if (file_exists($filePath)) {
            $sizeBytes = filesize($filePath);
            $fileSize = round($sizeBytes / (1024 * 1024), 1) . ' MB';
        } elseif (isset($note['file_size'])) {
            $fileSize = round((int)$note['file_size'] / (1024 * 1024), 1) . ' MB';
        }
    }
    
    $allContent[] = [
        'id' => $note['id'],
        'type' => 'note',
        'title' => $note['title'],
        'course_code' => $courseCode,
        'course_name' => $courseName,
        'instructor' => $instructor,
        'status' => $status,
        'flag_reason' => $flagReason,
        'uploaded_date' => isset($note['created_at']) ? date('n/j/Y', strtotime($note['created_at'])) : date('n/j/Y'),
        'file_size' => $fileSize,
    ];
}

// Calculate statistics
$totalContent = count($allContent);
$pendingCount = 0;
$approvedCount = 0;
$flaggedCount = 0;
$rejectedCount = 0;

foreach ($allContent as $content) {
    switch ($content['status']) {
        case 'approved':
            $approvedCount++;
            break;
        case 'flagged':
            $flaggedCount++;
            break;
        case 'rejected':
            $rejectedCount++;
            break;
        default:
            $pendingCount++;
            break;
    }
}

// Handle search and filters
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$typeFilter = isset($_GET['type']) ? trim($_GET['type']) : 'all';

if ($searchQuery !== '') {
    $allContent = array_filter($allContent, function($content) use ($searchQuery) {
        return stripos($content['title'], $searchQuery) !== false ||
               stripos($content['course_code'], $searchQuery) !== false ||
               stripos($content['instructor'], $searchQuery) !== false;
    });
}

if ($statusFilter !== 'all') {
    $allContent = array_filter($allContent, function($content) use ($statusFilter) {
        return $content['status'] === $statusFilter;
    });
}

if ($typeFilter !== 'all') {
    $allContent = array_filter($allContent, function($content) use ($typeFilter) {
        return $content['type'] === $typeFilter;
    });
}

// Sort by uploaded date (newest first)
usort($allContent, function($a, $b) {
    return strtotime($b['uploaded_date']) - strtotime($a['uploaded_date']);
});

// Get admin info
$username = $_SESSION['username'] ?? 'admin1';
$adminName = 'Admin User';
$adminEmail = 'admin@gmail.com';
$initials = 'AU';

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Content Quality Monitoring - eduQuest Admin Portal</title>
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
        
        .logo-subtitle {
            font-size: 0.85rem;
            color: #6b7280;
            margin-top: 0.25rem;
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
            font-size: 1.1rem;
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
            justify-content: flex-end;
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
            background: #a855f7;
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
            background: #f5f7fa;
        }
        
        .page-header {
            margin-bottom: 2rem;
        }
        
        .page-title {
            font-size: 1.75rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #1f2937;
        }
        
        .page-subtitle {
            color: #6b7280;
            font-size: 0.95rem;
        }
        
        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .stat-card.white {
            background: white;
        }
        
        .stat-card.blue {
            background: #dbeafe;
        }
        
        .stat-card.green {
            background: #dcfce7;
        }
        
        .stat-card.orange {
            background: #fed7aa;
        }
        
        .stat-card.red {
            background: #fee2e2;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
            color: #1f2937;
        }
        
        .stat-label {
            color: #6b7280;
            font-size: 0.9rem;
        }
        
        /* Search and Filters */
        .search-filters {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .search-bar {
            flex: 1;
            position: relative;
        }
        
        .search-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.95rem;
        }
        
        .filter-select {
            padding: 0.75rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.95rem;
            background: white;
            cursor: pointer;
        }
        
        /* Content List */
        .content-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .content-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: start;
        }
        
        .content-info {
            flex: 1;
        }
        
        .content-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        
        .content-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-right: 0.75rem;
        }
        
        .content-tag {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .content-tag.note {
            background: #f3e8ff;
            color: #7c3aed;
        }
        
        .content-tag.assignment {
            background: #dbeafe;
            color: #2563eb;
        }
        
        .content-tag.pending {
            background: #dbeafe;
            color: #2563eb;
        }
        
        .content-tag.approved {
            background: #dcfce7;
            color: #166534;
        }
        
        .content-tag.flagged {
            background: #fed7aa;
            color: #ea580c;
        }
        
        .content-tag.rejected {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .content-details {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            font-size: 0.9rem;
            color: #6b7280;
        }
        
        .flag-reason-box {
            background: #fff7ed;
            border-left: 3px solid #ea580c;
            padding: 0.75rem;
            border-radius: 6px;
            margin-top: 0.75rem;
        }
        
        .flag-reason-label {
            font-weight: 600;
            color: #dc2626;
            margin-bottom: 0.25rem;
        }
        
        .flag-reason-text {
            color: #ea580c;
            font-size: 0.9rem;
        }
        
        .content-actions {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            min-width: 120px;
        }
        
        .action-btn {
            padding: 0.625rem 1rem;
            border-radius: 8px;
            border: none;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-decoration: none;
        }
        
        .action-btn.review {
            background: #1f2937;
            color: white;
        }
        
        .action-btn.review:hover {
            background: #374151;
        }
        
        .action-btn.delete {
            background: #ef4444;
            color: white;
        }
        
        .action-btn.delete:hover {
            background: #dc2626;
        }
        
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
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
                grid-template-columns: repeat(2, 1fr);
            }
            
            .content-card {
                flex-direction: column;
            }
            
            .content-actions {
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
                <div>
                    <div class="logo-text">eduQuest</div>
                    <div class="logo-subtitle">Admin Portal</div>
                </div>
            </div>
            <nav>
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="admin_dashboard.php" class="nav-link">
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
                        <a href="content_monitoring.php" class="nav-link active">
                            <span class="nav-icon">🛡️</span>
                            <span>Content Monitoring</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <header class="header">
                <div class="header-right">
                    <div class="user-profile">
                        <div class="user-avatar"><?php echo $initials; ?></div>
                        <div class="user-info">
                            <div class="user-name"><?php echo $adminName; ?></div>
                            <div class="user-email"><?php echo $adminEmail; ?></div>
                        </div>
                    </div>
                    <a href="logout.php" style="margin-left: 1rem; padding: 0.5rem 1rem; background: #ef4444; color: white; text-decoration: none; border-radius: 6px; font-size: 0.85rem; transition: background 0.2s;">Logout</a>
                </div>
            </header>
            
            <!-- Content Area -->
            <div class="content-area">
                <!-- Page Header -->
                <div class="page-header">
                    <h1 class="page-title">Content Quality Monitoring</h1>
                    <p class="page-subtitle">Review and manage uploaded materials to ensure institutional standards.</p>
                </div>
                
                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card white">
                        <div class="stat-value"><?php echo $totalContent; ?></div>
                        <div class="stat-label">Total Content</div>
                    </div>
                    <div class="stat-card blue">
                        <div class="stat-value"><?php echo $pendingCount; ?></div>
                        <div class="stat-label">Pending Review</div>
                    </div>
                    <div class="stat-card green">
                        <div class="stat-value"><?php echo $approvedCount; ?></div>
                        <div class="stat-label">Approved</div>
                    </div>
                    <div class="stat-card orange">
                        <div class="stat-value"><?php echo $flaggedCount; ?></div>
                        <div class="stat-label">Flagged</div>
                    </div>
                    <div class="stat-card red">
                        <div class="stat-value"><?php echo $rejectedCount; ?></div>
                        <div class="stat-label">Rejected</div>
                    </div>
                </div>
                
                <!-- Search and Filters -->
                <div class="search-filters">
                    <form method="get" class="search-bar">
                        <input type="text" name="search" class="search-input" placeholder="Search content..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                    </form>
                    <select name="status" class="filter-select" onchange="this.form.submit()" form="filterForm">
                        <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending Review</option>
                        <option value="approved" <?php echo $statusFilter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="flagged" <?php echo $statusFilter === 'flagged' ? 'selected' : ''; ?>>Flagged</option>
                        <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                    <form method="get" id="filterForm" style="display: inline;">
                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>">
                        <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
                        <select name="type" class="filter-select" onchange="this.form.submit()">
                            <option value="all" <?php echo $typeFilter === 'all' ? 'selected' : ''; ?>>All Types</option>
                            <option value="assignment" <?php echo $typeFilter === 'assignment' ? 'selected' : ''; ?>>Assignment</option>
                            <option value="note" <?php echo $typeFilter === 'note' ? 'selected' : ''; ?>>Note</option>
                        </select>
                    </form>
                </div>
                
                <!-- Content List -->
                <div class="content-list">
                    <?php if (empty($allContent)): ?>
                        <div class="content-card">
                            <p style="color: #6b7280; text-align: center; padding: 2rem; width: 100%;">
                                No content found matching your criteria.
                            </p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($allContent as $content): ?>
                            <div class="content-card">
                                <div class="content-info">
                                    <div class="content-header">
                                        <div class="content-title"><?php echo htmlspecialchars($content['title']); ?></div>
                                        <span class="content-tag <?php echo $content['type']; ?>">
                                            <?php echo ucfirst($content['type']); ?>
                                        </span>
                                        <span class="content-tag <?php echo $content['status']; ?>">
                                            <?php 
                                            if ($content['status'] === 'pending') {
                                                echo '👁️ Pending Review';
                                            } elseif ($content['status'] === 'approved') {
                                                echo '✓ Approved';
                                            } elseif ($content['status'] === 'flagged') {
                                                echo '⚠️ Flagged';
                                            } elseif ($content['status'] === 'rejected') {
                                                echo '✗ Rejected';
                                            } else {
                                                echo ucfirst($content['status']);
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    
                                    <div class="content-details">
                                        <div><?php echo htmlspecialchars($content['course_code'] . ' - ' . $content['course_name']); ?></div>
                                        <div>Instructor: <?php echo htmlspecialchars($content['instructor']); ?></div>
                                        <div>Uploaded: <?php echo $content['uploaded_date']; ?><?php echo $content['file_size'] !== '0 MB' ? ' Size: ' . $content['file_size'] : ''; ?></div>
                                        <?php if ($content['status'] === 'flagged' && !empty($content['flag_reason'])): ?>
                                            <div class="flag-reason-box">
                                                <div class="flag-reason-label">Flag Reason:</div>
                                                <div class="flag-reason-text"><?php echo htmlspecialchars($content['flag_reason']); ?></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="content-actions">
                                    <button onclick="openReviewModal(<?php echo $content['id']; ?>, '<?php echo htmlspecialchars($content['type'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($content['title'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($content['status'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($content['flag_reason'], ENT_QUOTES); ?>')" class="action-btn review">
                                        Review
                                    </button>
                                    <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this content?');">
                                        <input type="hidden" name="action" value="delete_content">
                                        <input type="hidden" name="content_id" value="<?php echo $content['id']; ?>">
                                        <input type="hidden" name="content_type" value="<?php echo htmlspecialchars($content['type']); ?>">
                                        <button type="submit" class="action-btn delete">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Review Modal -->
    <div id="reviewModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
        <div style="background:white; border-radius:12px; padding:2rem; max-width:600px; width:90%; max-height:90vh; overflow-y:auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                <h2 style="font-size:1.5rem; font-weight:600;">Review Content</h2>
                <button onclick="document.getElementById('reviewModal').style.display='none'" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:#6b7280;">&times;</button>
            </div>
            <div style="margin-bottom:1rem; padding:1rem; background:#f9fafb; border-radius:8px;">
                <p style="font-weight:600; margin-bottom:0.25rem;" id="modalContentTitle"></p>
            </div>
            <form method="post" id="reviewForm">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="content_id" id="modalContentId">
                <input type="hidden" name="content_type" id="modalContentType">
                <div style="display:flex; flex-direction:column; gap:1rem;">
                    <div>
                        <label style="display:block; margin-bottom:0.5rem; font-weight:500;">Status *</label>
                        <select name="status" id="modalStatus" required style="width:100%; padding:0.75rem; border:1px solid #d1d5db; border-radius:8px;">
                            <option value="pending">Pending Review</option>
                            <option value="approved">Approved</option>
                            <option value="flagged">Flagged</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    <div id="flagReasonDiv" style="display:none;">
                        <label style="display:block; margin-bottom:0.5rem; font-weight:500;">Flag Reason</label>
                        <textarea name="flag_reason" id="modalFlagReason" rows="3" placeholder="Enter reason for flagging..." style="width:100%; padding:0.75rem; border:1px solid #d1d5db; border-radius:8px;"></textarea>
                    </div>
                    <div style="display:flex; gap:1rem; margin-top:1rem;">
                        <button type="submit" style="flex:1; padding:0.75rem; background:#a855f7; color:white; border:none; border-radius:8px; font-weight:500; cursor:pointer;">Update Status</button>
                        <button type="button" onclick="document.getElementById('reviewModal').style.display='none'" style="flex:1; padding:0.75rem; background:#f3f4f6; color:#1f2937; border:none; border-radius:8px; font-weight:500; cursor:pointer;">Cancel</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openReviewModal(contentId, contentType, contentTitle, currentStatus, flagReason) {
            document.getElementById('modalContentId').value = contentId;
            document.getElementById('modalContentType').value = contentType;
            document.getElementById('modalContentTitle').textContent = contentTitle;
            document.getElementById('modalStatus').value = currentStatus;
            document.getElementById('modalFlagReason').value = flagReason || '';
            
            // Show/hide flag reason based on status
            const flagReasonDiv = document.getElementById('flagReasonDiv');
            const statusSelect = document.getElementById('modalStatus');
            
            function toggleFlagReason() {
                if (statusSelect.value === 'flagged') {
                    flagReasonDiv.style.display = 'block';
                } else {
                    flagReasonDiv.style.display = 'none';
                }
            }
            
            toggleFlagReason();
            statusSelect.addEventListener('change', toggleFlagReason);
            
            document.getElementById('reviewModal').style.display = 'flex';
        }
        
        // Close modal when clicking outside
        document.getElementById('reviewModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                this.style.display = 'none';
            }
        });
    </script>
</body>
</html>

