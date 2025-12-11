<?php
// EduQuest - Teaching and Learning Management System (Mini Module)
// Focus: Manage Assignments and Notes for Software Engineering subject
session_start();

// Redirect to role selection if not logged in
if (!isset($_SESSION['role'])) {
    header('Location: role_selection.php');
    exit;
}

// Redirect instructors, students, and admins to their dashboards
if ($_SESSION['role'] === 'teacher') {
    header('Location: teacher_dashboard.php');
    exit;
} elseif ($_SESSION['role'] === 'student') {
    header('Location: student_dashboard.php');
    exit;
} elseif ($_SESSION['role'] === 'admin') {
    header('Location: admin_dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>EduQuest LMS - Dashboard</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <header class="main-header">
        <div class="logo">EduQuest<span>LMS</span></div>
        <nav class="top-nav">
            <a href="index.php" class="active">Dashboard</a>
            <a href="assignments.php">Assignments</a>
            <a href="notes.php">Notes</a>
            <a href="gradebook.php">Grades</a>
            <?php if (isset($_SESSION['role'])): ?>
                <span class="muted" style="margin-left:1rem;">
                    Logged in as <?php echo htmlspecialchars($_SESSION['role'], ENT_QUOTES, 'UTF-8'); ?>
                </span>
                <a href="logout.php" class="btn small secondary" style="margin-left:0.5rem;">Logout</a>
            <?php else: ?>
                <a href="role_selection.php" class="btn small secondary" style="margin-left:1rem;">Login</a>
            <?php endif; ?>
        </nav>
    </header>

    <main class="container">
        <section class="hero">
            <h1>EduQuest - Teaching &amp; Learning Management</h1>
            <p>Module: <strong>Manage Assignments and Notes</strong> (Software Engineering)</p>
        </section>

        <section class="cards-grid">
            <a href="assignments.php" class="card">
                <h2>Assignment Management</h2>
                <p>Create, distribute, collect, and grade assignments with deadlines and reminders.</p>
                <ul>
                    <li>Deadline tracking</li>
                    <li>Student submissions</li>
                    <li>Basic grading</li>
                </ul>
            </a>

            <a href="notes.php" class="card">
                <h2>Notes Management</h2>
                <p>Organize and share course notes and learning materials with version control.</p>
                <ul>
                    <li>Create and edit notes</li>
                    <li>Attach files</li>
                    <li>Track versions</li>
                </ul>
            </a>

            <a href="gradebook.php" class="card">
                <h2>Assessment &amp; Grades</h2>
                <p>View grades calculated from graded assignments with simple statistics.</p>
                <ul>
                    <li>Rubric overview</li>
                    <li>Feedback summary</li>
                    <li>Grade calculation</li>
                </ul>
            </a>
        </section>

        <section class="info-section">
            <h2>System Integration (Mini Simulation)</h2>
            <p>
                This prototype integrates with simplified:
            </p>
            <ul>
                <li>User management (simulated instructor/student selection)</li>
                <li>Course management (Software Engineering)</li>
                <li>Notifications (deadline highlights and status badges)</li>
            </ul>
        </section>
    </main>

    <footer class="main-footer">
        <span>&copy; <?php echo date('Y'); ?> EduQuest LMS &mdash; Software Engineering Module</span>
    </footer>

    <script src="script.js"></script>
</body>
</html>


