<?php
session_start();
require_once __DIR__ . '/data_store.php';

// If already logged in, go to appropriate dashboard
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'teacher') {
        header('Location: teacher_dashboard.php');
    } elseif ($_SESSION['role'] === 'admin') {
        header('Location: admin_dashboard.php');
    } else {
        header('Location: index.php');
    }
    exit;
}

// Load users from JSON storage
$users = eq_get_users();

$error = '';
$role = isset($_GET['role']) ? trim($_GET['role']) : '';

// Validate role
if (!in_array($role, ['student', 'teacher', 'admin'])) {
    header('Location: role_selection.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $post_role = trim($_POST['role'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Please enter email and password.';
    } else {
        // Try to find user by username (key) first, then by email field
        $user = $users[$email] ?? null;
        if (!$user) {
            // Try to find by email field
            foreach ($users as $username => $userData) {
                if (isset($userData['email']) && $userData['email'] === $email) {
                    $user = $userData;
                    break;
                }
            }
        }
        
        if (!$user) {
            $pending = eq_get_pending_requests();
            if (isset($pending[$email])) {
                $error = 'Your registration is pending approval by an administrator.';
            } else {
                $error = 'Invalid email or password.';
            }
        } elseif (($user['password'] ?? '') !== $password) {
            $error = 'Invalid email or password.';
        } elseif (($user['role'] ?? '') !== $post_role) {
            $error = 'The selected role does not match your account.';
        } else {
            // Successful login - use username from database or email if username not found
            $_SESSION['username'] = isset($user['username']) ? $user['username'] : $email;
            $_SESSION['role'] = $user['role'];

            // redirect to appropriate dashboard
            if ($user['role'] === 'teacher') {
                header('Location: teacher_dashboard.php');
            } elseif ($user['role'] === 'admin') {
                header('Location: admin_dashboard.php');
            } else {
                header('Location: index.php');
            }
            exit;
        }
    }
}

// Role-specific configurations
$roleConfig = [
    'student' => [
        'title' => 'Student Login',
        'color' => '#0ea5e9',
        'bgColor' => '#e0f2fe',
        'buttonColor' => '#0ea5e9',
        'emailPlaceholder' => 'Enter your student email',
    ],
    'teacher' => [
        'title' => 'Instructor Login',
        'color' => '#22c55e',
        'bgColor' => '#dcfce7',
        'buttonColor' => '#22c55e',
        'emailPlaceholder' => 'Enter your instructor email',
    ],
    'admin' => [
        'title' => 'Administrator Login',
        'color' => '#a855f7',
        'bgColor' => '#f3e8ff',
        'buttonColor' => '#a855f7',
        'emailPlaceholder' => 'Enter your admin email',
    ],
];

$config = $roleConfig[$role];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>eduQuest - <?php echo htmlspecialchars($config['title']); ?></title>
    <link rel="stylesheet" href="styles.css">
    <style>
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
        
        .left-section h1 {
            font-size: 2rem;
            margin-bottom: 1rem;
            font-weight: 700;
        }
        
        .left-section p {
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 3rem;
            opacity: 0.95;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            color: #1f2933;
        }
        
        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: #764ba2;
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: #6b7280;
        }
        
        .right-section {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem;
        }
        
        .login-card {
            background: white;
            border-radius: 20px;
            padding: 3rem;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #6b7280;
            text-decoration: none;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            transition: color 0.2s;
        }
        
        .back-link:hover {
            color: #1f2933;
        }
        
        .login-card h2 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
            color: #1f2933;
        }
        
        .login-card .subtitle {
            color: #6b7280;
            margin-bottom: 2rem;
            font-size: 0.95rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #374151;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 1.1rem;
        }
        
        .input-wrapper input {
            width: 100%;
            padding: 0.75rem 0.75rem 0.75rem 2.75rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: border-color 0.2s;
        }
        
        .input-wrapper input:focus {
            outline: none;
            border-color: <?php echo $config['color']; ?>;
        }
        
        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #9ca3af;
            cursor: pointer;
            font-size: 1.1rem;
        }
        
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .remember-me input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }
        
        .forgot-password {
            color: <?php echo $config['color']; ?>;
            text-decoration: none;
        }
        
        .forgot-password:hover {
            text-decoration: underline;
        }
        
        .login-button {
            width: 100%;
            padding: 0.875rem;
            background: <?php echo $config['buttonColor']; ?>;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.2s;
            margin-bottom: 1.5rem;
        }
        
        .login-button:hover {
            opacity: 0.9;
        }
        
        .error-message {
            background: #fee2e2;
            color: #991b1b;
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }
        
        .signup-link {
            text-align: center;
            color: #6b7280;
            font-size: 0.9rem;
        }
        
        .signup-link a {
            color: <?php echo $config['color']; ?>;
            text-decoration: none;
            font-weight: 500;
        }
        
        .signup-link a:hover {
            text-decoration: underline;
        }
        
        .copyright {
            text-align: center;
            color: white;
            padding: 2rem;
            font-size: 0.85rem;
            opacity: 0.8;
        }
        
        @media (max-width: 968px) {
            .login-container {
                flex-direction: column;
            }
            
            .left-section {
                padding: 2rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
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
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number">5000+</div>
                    <div class="stat-label">Students</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">300+</div>
                    <div class="stat-label">Instructors</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">100+</div>
                    <div class="stat-label">Courses</div>
                </div>
            </div>
        </div>
        
        <div class="right-section">
            <div class="login-card">
                <a href="role_selection.php" class="back-link">
                    ← Back to role selection
                </a>
                <h2><?php echo htmlspecialchars($config['title']); ?></h2>
                <p class="subtitle">Enter your credentials to access your account</p>
                
                <?php if ($error !== ''): ?>
                    <div class="error-message">
                        <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>
                
                <form method="post">
                    <input type="hidden" name="role" value="<?php echo htmlspecialchars($role); ?>">
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <div class="input-wrapper">
                            <span class="input-icon">✉</span>
                            <input type="text" id="email" name="email" required placeholder="<?php echo htmlspecialchars($config['emailPlaceholder']); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-wrapper">
                            <span class="input-icon">🔒</span>
                            <input type="password" id="password" name="password" required placeholder="Enter your password">
                            <button type="button" class="password-toggle" onclick="togglePassword()">👁</button>
                        </div>
                    </div>
                    
                    <div class="form-options">
                        <label class="remember-me">
                            <input type="checkbox" name="remember">
                            <span>Remember me</span>
                        </label>
                        <a href="#" class="forgot-password">Forgot password?</a>
                    </div>
                    
                    <button type="submit" class="login-button">Sign In</button>
                </form>
                
                <div class="signup-link">
                    Don't have an account? <a href="register.php">Contact Administrator</a>
                </div>
            </div>
        </div>
    </div>
    <div class="copyright">© 2025 eduQuest. All rights reserved.</div>
    
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleButton = document.querySelector('.password-toggle');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleButton.textContent = '👁️';
            } else {
                passwordInput.type = 'password';
                toggleButton.textContent = '👁';
            }
        }
    </script>
</body>
</html>
