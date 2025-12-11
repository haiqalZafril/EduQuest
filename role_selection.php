<?php
session_start();

// If already logged in, go to dashboard
if (isset($_SESSION['role'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>eduQuest - Role Selection</title>
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
        
        .role-cards {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .role-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.25rem;
            border-radius: 12px;
            border: 2px solid #e5e7eb;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
        }
        
        .role-card:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .role-card.student {
            background: #e0f2fe;
            border-color: #0ea5e9;
        }
        
        .role-card.teacher {
            background: #dcfce7;
            border-color: #22c55e;
        }
        
        .role-card.admin {
            background: #f3e8ff;
            border-color: #a855f7;
        }
        
        .role-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin-right: 1rem;
        }
        
        .role-card.student .role-icon {
            background: #0ea5e9;
            color: white;
        }
        
        .role-card.teacher .role-icon {
            background: #22c55e;
            color: white;
        }
        
        .role-card.admin .role-icon {
            background: #a855f7;
            color: white;
        }
        
        .role-info {
            flex: 1;
        }
        
        .role-info h3 {
            font-size: 1.1rem;
            margin-bottom: 0.25rem;
            color: #1f2933;
        }
        
        .role-info p {
            font-size: 0.85rem;
            color: #6b7280;
            margin: 0;
        }
        
        .role-arrow {
            color: #9ca3af;
            font-size: 1.2rem;
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
                <h2>Welcome Back!</h2>
                <p class="subtitle">Select your role to continue</p>
                <div class="role-cards">
                    <a href="login.php?role=student" class="role-card student">
                        <div class="role-icon">🎓</div>
                        <div class="role-info">
                            <h3>Student</h3>
                            <p>Access your courses, assignments, and grades</p>
                        </div>
                        <div class="role-arrow">→</div>
                    </a>
                    <a href="login.php?role=teacher" class="role-card teacher">
                        <div class="role-icon">📖</div>
                        <div class="role-info">
                            <h3>Instructor</h3>
                            <p>Manage classes, create content, and grade assignments</p>
                        </div>
                        <div class="role-arrow">→</div>
                    </a>
                    <a href="login.php?role=admin" class="role-card admin">
                        <div class="role-icon">🛡️</div>
                        <div class="role-info">
                            <h3>Administrator</h3>
                            <p>System management and administrative controls</p>
                        </div>
                        <div class="role-arrow">→</div>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <div class="copyright">© 2025 eduQuest. All rights reserved.</div>
</body>
</html>

