<?php
session_start();
require_once __DIR__ . '/data_store.php';

// If already logged in, redirect
if (isset($_SESSION['role'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = trim($_POST['role'] ?? 'student');
    $password = trim($_POST['password'] ?? '');
    $confirm = trim($_POST['confirm_password'] ?? '');

    // Basic validation
    if ($name === '' || $email === '' || $password === '' || $confirm === '') {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (!in_array($role, ['student', 'teacher'])) {
        $error = 'Invalid role selection.';
    } else {
        $users = eq_get_users();
        $pending = eq_get_pending_requests();
        if (isset($users[$email])) {
            $error = 'An account with that email already exists.';
        } elseif (isset($pending[$email])) {
            $error = 'A registration request with that email is already pending approval.';
        } else {
            $req = [
                'name' => $name,
                'email' => $email,
                'role' => $role,
                'password' => $password,
                'created_at' => date('c')
            ];
            eq_save_pending_request($email, $req);
            $message = 'Your request has been submitted and is pending approval by an administrator.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>eduQuest - Contact Administrator</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        /* Reuse the look from role_selection/login pages */
        .login-container {
            min-height: 100vh;
            display: flex;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .left-section {
            flex: 1;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            color: white;
        }
        .logo-section {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 2rem;
        }
        .logo-icon {
            width: 50px;
            height: 50px;
            background: #764ba2;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        .logo-text {
            font-size: 1.5rem;
            font-weight: 700;
        }
        .left-section h1 { font-size: 2rem; margin-bottom: 1rem; font-weight:700; }
        .left-section p { font-size: 1rem; line-height: 1.6; margin-bottom: 3rem; opacity:0.95; }

        .right-section { flex:1; display:flex; align-items:center; justify-content:center; padding:3rem; }
        .login-card { background:white; border-radius:20px; padding:2.25rem; width:100%; max-width:520px; box-shadow:0 20px 60px rgba(0,0,0,0.3); }
        .back-link { display:inline-flex; align-items:center; gap:0.5rem; color:#6b7280; text-decoration:none; margin-bottom:1rem; font-size:0.9rem; }
        .back-link:hover { color:#1f2933; }
        .login-card h2 { font-size:1.6rem; margin-bottom:0.25rem; color:#1f2933; }
        .login-card .subtitle { color:#6b7280; margin-bottom:1.25rem; font-size:0.95rem; }
        .form-group { margin-bottom:1rem; }
        .form-group label { display:block; margin-bottom:0.5rem; color:#374151; font-weight:500; font-size:0.9rem; }
        .input-wrapper { position:relative; }
        .input-icon { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#9ca3af; font-size:1.1rem; }
        .input-wrapper input, .input-wrapper select { width:100%; padding:0.75rem 0.75rem 0.75rem 2.75rem; border:1px solid #d1d5db; border-radius:8px; font-size:0.95rem; }
        .input-wrapper input:focus, .input-wrapper select:focus { outline:none; border-color:#667eea; }
        .register-button { width:100%; padding:0.875rem; background:#0ea5e9; color:white; border:none; border-radius:8px; font-size:1rem; font-weight:600; cursor:pointer; }
        .error-message { background:#fee2e2; color:#991b1b; padding:0.75rem; border-radius:8px; margin-bottom:1rem; font-size:0.95rem; }
        .success-message { background:#ecfdf5; color:#065f46; padding:0.75rem; border-radius:8px; margin-bottom:1rem; font-size:0.95rem; }
        .copyright { text-align:center; color:white; padding:2rem; font-size:0.85rem; opacity:0.8; }
        @media (max-width:968px) { .login-container { flex-direction:column; } .left-section { padding:2rem; } }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="left-section">
            <div class="logo-section">
                <div class="logo-icon">🎓</div>
                <div class="logo-text">eduQuest</div>
            </div>
            <h1>Teaching and Learning Management System</h1>
            <p>Empowering education through innovative technology. Connect, learn, and grow with our comprehensive platform designed for instructors, students, and administrators.</p>
        </div>

        <div class="right-section">
            <div class="login-card">
                <a href="role_selection.php" class="back-link">← Back to role selection</a>
                <h2>Contact Administrator</h2>
                <p class="subtitle">Request an account to get access to eduQuest</p>

                <?php if (!empty($error)): ?>
                    <div class="error-message"><?php echo eq_h($error); ?></div>
                <?php endif; ?>

                <?php if (!empty($message)): ?>
                    <div class="success-message"><?php echo eq_h($message); ?></div>
                <?php endif; ?>

                <form method="post">
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <div class="input-wrapper">
                            <span class="input-icon">👤</span>
                            <input type="text" id="name" name="name" required value="<?php echo eq_h($_POST['name'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <div class="input-wrapper">
                            <span class="input-icon">✉</span>
                            <input type="email" id="email" name="email" required value="<?php echo eq_h($_POST['email'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="role">Role</label>
                        <div class="input-wrapper">
                            <span class="input-icon">🔖</span>
                            <select id="role" name="role">
                                <option value="student" <?php echo (($_POST['role'] ?? '') === 'student') ? 'selected' : ''; ?>>Student</option>
                                <option value="teacher" <?php echo (($_POST['role'] ?? '') === 'teacher') ? 'selected' : ''; ?>>Instructor</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-wrapper">
                            <span class="input-icon">🔒</span>
                            <input type="password" id="password" name="password" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <div class="input-wrapper">
                            <span class="input-icon">🔒</span>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                    </div>

                    <button type="submit" class="register-button">Submit Request</button>
                </form>

                <p style="margin-top:1rem; color:#6b7280; font-size:0.9rem;">If you already have an account, <a href="login.php?role=student">sign in</a>.</p>
            </div>
        </div>
    </div>
    <div class="copyright">© 2025 eduQuest. All rights reserved.</div>
</body>
</html>