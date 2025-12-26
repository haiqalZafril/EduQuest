<?php
session_start();
require_once __DIR__ . '/data_store.php';

// Require student login
if (!isset($_SESSION['username']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header('Location: role_selection.php');
    exit;
}

$currentUser = $_SESSION['username'];
$currentRole = $_SESSION['role'];

// Students can only view/edit their own profile, admin/teacher view handled elsewhere
$viewUser = $currentUser;

$user = eq_get_user($viewUser);
if (!$user) {
    header('Location: role_selection.php');
    exit;
}

$success = '';
$error = '';

// Handle profile updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save_profile';

    if ($action === 'save_profile') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        // Basic validation
        if ($name === '' || $email === '') {
            $error = 'Name and email are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please provide a valid email address.';
        } else {
            $user['name'] = $name;
            $user['email'] = $email;
            eq_save_user($viewUser, $user);
            $success = 'Profile updated successfully.';
        }
    }

    if ($action === 'change_password') {
        $current = trim($_POST['current_password'] ?? '');
        $new = trim($_POST['new_password'] ?? '');
        $confirm = trim($_POST['confirm_password'] ?? '');

        if (($user['password'] ?? '') !== $current) {
            $error = 'Current password incorrect.';
        } elseif ($new === '' || $new !== $confirm) {
            $error = 'New passwords do not match or are empty.';
        } else {
            $user['password'] = $new;
            eq_save_user($viewUser, $user);
            $success = 'Password updated.';
        }
    }

    if ($action === 'upload_avatar' && isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $tmp = $_FILES['avatar']['tmp_name'];
        $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        $allowed = ['png','jpg','jpeg','gif'];
        if (!in_array(strtolower($ext), $allowed)) {
            $error = 'Invalid image type.';
        } else {
            $avatarsDir = __DIR__ . '/data/avatars';
            if (!is_dir($avatarsDir)) mkdir($avatarsDir, 0777, true);
            $target = $avatarsDir . '/' . preg_replace('/[^a-z0-9_\-\.]/i', '_', $viewUser) . '.' . $ext;
            if (move_uploaded_file($tmp, $target)) {
                $user['avatar'] = 'data/avatars/' . basename($target);
                eq_save_user($viewUser, $user);
                $success = 'Avatar uploaded.';
            } else {
                $error = 'Unable to save uploaded file.';
            }
        }
    }
}

// Helper for initials
$initials = '';
if (!empty($user['name'])) {
    $parts = preg_split('/\s+/', $user['name']);
    $initials = strtoupper(substr($parts[0],0,1) . (isset($parts[1]) ? substr($parts[1],0,1) : ''));
} else {
    $initials = strtoupper(substr($viewUser,0,2));
}

// Current page for active menu
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>My Profile - eduQuest (Student)</title>
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

        .user-avatar {
            cursor: pointer;
            pointer-events: auto;
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
    <style>
    /* Strong override for profile pages to force avatar and input styles (last-applied) */
    .profile-overview .avatar { width:72px; height:72px; border-radius:50% !important; display:flex; align-items:center; justify-content:center; overflow:hidden; color:#fff; font-weight:600; font-size:1.15rem }
    .profile-overview .avatar img { width:100%; height:100%; object-fit:cover; display:block }
    .user-avatar-small { width:40px; height:40px; border-radius:50% !important; display:flex; align-items:center; justify-content:center; overflow:hidden; color:#fff; font-weight:600; font-size:0.9rem }
    .user-avatar-small img { width:100%; height:100%; object-fit:cover; display:block }
    .profile-page .form-grid { grid-template-columns: 1fr !important; }
    .profile-page input[type="text"], .profile-page input[type="email"], .profile-page input[type="password"], .profile-page input[type="file"], .profile-page select, .profile-page textarea { padding: 0.4rem 0.5rem; border-radius: 6px; border: 1px solid #d1d5db; font-size: 0.9rem; width: 100%; box-sizing: border-box; }
    .profile-page input[type="file"] { padding: 0.25rem 0.25rem }
    .profile-page .form-group { margin-bottom: 0.6rem }
    </style>
</head>
<body class="profile-page">
<div class="dashboard-container">
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
            </ul>
        </nav>
    </aside>

    <div class="main-content">
        <header class="header">
            <div class="header-left"><div class="header-title">eduQuest Student Portal</div></div>
            <div class="header-right">
                <div class="notification-icon">🔔</div>
                <div class="user-profile">
                    <div class="user-avatar-small role-student">
                        <?php if (!empty($user['avatar']) && file_exists(__DIR__ . '/' . $user['avatar'])): ?>
                            <img src="<?php echo eq_h($user['avatar']); ?>" alt="avatar" />
                        <?php else: ?>
                            <?php echo eq_h($initials); ?>
                        <?php endif; ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?php echo eq_h($user['name'] ?? $viewUser); ?></div>
                        <div class="user-email"><?php echo eq_h($user['email'] ?? ''); ?></div>
                    </div>
                </div>
                <a href="logout.php" class="btn danger small">Logout</a>
            </div>
        </header>

        <div class="content-area">
            <?php if ($error): ?>
                <div class="alert error"><?php echo eq_h($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert success"><?php echo eq_h($success); ?></div>
            <?php endif; ?>

            <div class="card profile-overview">
                <div class="avatar role-student">
                    <?php if (!empty($user['avatar']) && file_exists(__DIR__ . '/' . $user['avatar'])): ?>
                        <img src="<?php echo eq_h($user['avatar']); ?>" alt="avatar" />
                    <?php else: ?>
                        <?php echo eq_h($initials); ?>
                    <?php endif; ?>
                </div>
                <div>
                    <h2><?php echo eq_h($user['name'] ?? $viewUser); ?></h2>
                    <div><?php echo eq_h($user['email'] ?? ''); ?></div>
                </div>
            </div>

            <div class="card">
                <h3>Edit Profile</h3>
                <form method="post">
                    <input type="hidden" name="action" value="save_profile">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" name="name" value="<?php echo eq_h($user['name'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" value="<?php echo eq_h($user['email'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="form-actions"><button class="btn">Save Changes</button></div>
                </form>
            </div>

            <div class="card">
                <h3>Change Password</h3>
                <form method="post">
                    <input type="hidden" name="action" value="change_password">
                    <label>Current Password</label>
                    <input type="password" name="current_password">
                    <label>New Password</label>
                    <input type="password" name="new_password">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password">
                    <div class="form-actions"><button class="btn">Update Password</button></div>
                </form>
            </div>

            <div class="card">
                <h3>Profile Image</h3>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_avatar">
                    <input type="file" name="avatar" id="avatarFile">
                    <div class="form-actions"><button class="btn">Upload</button></div>
                </form>
            </div>

        </div>
    </div>
</div>
</body>
</html>