<?php
session_start();
require_once __DIR__ . '/data_store.php';

// Require logged-in user
if (!isset($_SESSION['username']) || !isset($_SESSION['role'])) {
    header('Location: role_selection.php');
    exit;
}

$viewUser = $_SESSION['username'];
$currentRole = $_SESSION['role'];

$user = eq_get_user($viewUser);
if (!$user) {
    $error = 'User not found.';
}

$success = '';
$error = $error ?? '';

// Handle profile updates
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {
    $action = $_POST['action'] ?? 'save_profile';

    if ($action === 'save_profile') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');

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

// Reload data
$user = eq_get_user($viewUser) ?: $user;

// Helper for initials
$initials = '';
if (!empty($user['name'])) {
    $parts = preg_split('/\s+/', $user['name']);
    $initials = strtoupper(substr($parts[0],0,1) . (isset($parts[1]) ? substr($parts[1],0,1) : ''));
} else {
    $initials = strtoupper(substr($viewUser,0,2));
}

// Role classes
$roleClass = ($currentRole === 'teacher') ? 'role-teacher' : (($currentRole === 'admin') ? 'role-admin' : 'role-student');

// Current page for active menu
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Profile - eduQuest</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="dashboard-container">
    <aside class="sidebar">
        <div class="sidebar-logo">
            <div class="logo-icon">🎓</div>
            <div class="logo-text"><strong>eduQuest</strong></div>
        </div>
        <nav>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="index.php" class="nav-link <?php echo ($currentPage === 'index.php') ? 'active' : ''; ?>">
                        <span class="nav-icon">☰</span>
                        <span>Overview</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="profile.php" class="nav-link <?php echo ($currentPage === 'profile.php') ? 'active' : ''; ?>">
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

    <div class="main-content">
        <header class="header">
            <div class="header-left"><div class="header-title">eduQuest Profile</div></div>
            <div class="header-right">
                <div class="user-profile">
                    <div class="user-avatar <?php echo $roleClass; ?>">
                        <?php if (!empty($user['avatar']) && file_exists(__DIR__ . '/' . $user['avatar'])): ?>
                            <img src="<?php echo eq_h($user['avatar']); ?>" alt="avatar">
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
                <?php if ($error): ?><div class="alert error"><?php echo eq_h($error); ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert success"><?php echo eq_h($success); ?></div><?php endif; ?>


        <div class="card profile-overview">
            <div class="avatar <?php echo $roleClass; ?>">
                <?php if (!empty($user['avatar']) && file_exists(__DIR__ . '/' . $user['avatar'])): ?>
                    <img src="<?php echo eq_h($user['avatar']); ?>" alt="avatar">
                <?php else: ?>
                    <?php echo eq_h($initials); ?>
                <?php endif; ?>
            </div>
            <div>
                <h2><?php echo eq_h($user['name'] ?? $viewUser); ?></h2>
                <div><?php echo eq_h($user['email'] ?? ''); ?></div>
                <div class="muted">Role: <?php echo eq_h($currentRole); ?></div>
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
                <input type="file" name="avatar">
                <div class="form-actions"><button class="btn">Upload</button></div>
            </form>
        </div>

        </div>
    </div>
</div>
</body>
</html>






