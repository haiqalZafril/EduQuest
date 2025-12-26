<?php
session_start();
require_once __DIR__ . '/data_store.php';

// Require admin login
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: role_selection.php');
    exit;
}

// Determine current page
$currentPage = 'overview';

// Load data for statistics
$assignments = eq_load_data('assignments');
$submissions = eq_load_data('submissions');
$notes = eq_load_data('notes');

// Basic user list (kept in sync with login.php)
$users = [
    'teacher1' => ['password' => 'teacher123', 'role' => 'teacher'],
    'student1' => ['password' => 'student123', 'role' => 'student'],
    'admin1'   => ['password' => 'admin123',   'role' => 'admin'],
];

// Calculate statistics
$totalUsers = count($users);
$activeCourses = count(array_unique(array_column($assignments, 'id'))) ?: 124;
$totalAssignments = count($assignments) ?: 892;

// Get admin info
$username = $_SESSION['username'] ?? 'admin1';
$adminName = 'Admin User';
$adminEmail = 'admin@gmail.com';
$initials = 'AU';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>eduQuest Admin Portal</title>
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
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #1f2937;
        }
        
        .section-subtitle {
            color: #6b7280;
            margin-bottom: 2rem;
        }
        
        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .stat-icon-wrapper {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 1rem;
        }
        
        .stat-icon-wrapper.blue {
            background: #dbeafe;
            color: #2563eb;
        }
        
        .stat-icon-wrapper.green {
            background: #dcfce7;
            color: #22c55e;
        }
        
        .stat-icon-wrapper.purple {
            background: #f3e8ff;
            color: #a855f7;
        }
        
        .stat-icon-wrapper.orange {
            background: #fed7aa;
            color: #ea580c;
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
            margin-bottom: 0.5rem;
        }
        
        .stat-detail {
            font-size: 0.85rem;
            color: #6b7280;
        }
        
        /* Quick Actions */
        .quick-actions {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .quick-actions-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #1f2937;
        }
        
        .quick-actions-placeholder {
            color: #6b7280;
            font-size: 0.95rem;
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
                        <a href="./admin_dashboard.php" class="nav-link <?php echo $currentPage === 'overview' ? 'active' : ''; ?>">
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
                        <a href="content_monitoring.php" class="nav-link">
                            <span class="nav-icon">🛡️</span>
                            <span>Content Monitoring</span>
                        <a href="#" class="nav-link">
                            <span class="nav-icon">🎓</span>
                            <span>Course Management</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="./admin_announcements.php" class="nav-link">
                            <span class="nav-icon">📢</span>
                            <span>Announcements</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="./admin_discussions.php" class="nav-link">
                            <span class="nav-icon">💬</span>
                            <span>Discussion</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <span class="nav-icon">📊</span>
                            <span>Reports</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <span class="nav-icon">⚙️</span>
                            <span>Settings</span>
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
                <!-- System Overview -->
                <div class="system-overview">
                    <h1 class="section-title">System Overview</h1>
                    <p class="section-subtitle">Monitor platform statistics and performance</p>
                    
                    <div class="stats-grid">
                        <!-- Total Users Card -->
                        <div class="stat-card">
                            <div class="stat-icon-wrapper blue">👥</div>
                            <div class="stat-value"><?php echo number_format($totalUsers); ?></div>
                            <div class="stat-label">Total Users</div>
                            <div class="stat-detail">Current registered users</div>
                        </div>
                        
                        <!-- Active Courses Card -->
                        <div class="stat-card">
                            <div class="stat-icon-wrapper green">🎓</div>
                            <div class="stat-value"><?php echo $activeCourses; ?></div>
                            <div class="stat-label">Active Courses</div>
                            <div class="stat-detail">+8 new courses</div>
                        </div>
                        
                        <!-- Assignments Card -->
                        <div class="stat-card">
                            <div class="stat-icon-wrapper purple">📄</div>
                            <div class="stat-value"><?php echo $totalAssignments; ?></div>
                            <div class="stat-label">Assignments</div>
                            <div class="stat-detail">+45 this week</div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="quick-actions">
                    <h2 class="quick-actions-title">Quick Actions</h2>
                    <p class="quick-actions-placeholder">Admin management features coming soon...</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

