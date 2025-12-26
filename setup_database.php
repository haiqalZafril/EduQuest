<?php
// Database Setup Script for EduQuest
// Visit this file in your browser to automatically create the database and tables

$db_host = 'localhost';
$db_username = 'root';
$db_password = '';
$db_name = 'eduquest_db';

// Try to connect to MySQL (without specifying database first)
$conn = new mysqli($db_host, $db_username, $db_password);

// Check connection
if ($conn->connect_error) {
    die('Connection Failed: ' . $conn->connect_error);
}

// SQL commands to create database and tables
$sql_commands = array(
    // Create database
    "CREATE DATABASE IF NOT EXISTS `eduquest_db`;",
    
    // Use the database
    "USE `eduquest_db`;",
    
    // Create announcements table
    "CREATE TABLE IF NOT EXISTS `announcements` (
        `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `author` VARCHAR(255) NOT NULL,
        `author_role` VARCHAR(50) NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `category` VARCHAR(50) NOT NULL,
        `content` LONGTEXT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_created_at` (`created_at`),
        INDEX `idx_category` (`category`),
        INDEX `idx_author` (`author`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    // Create discussions table
    "CREATE TABLE IF NOT EXISTS `discussions` (
        `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `author` VARCHAR(255) NOT NULL,
        `author_role` VARCHAR(50) NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `content` LONGTEXT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_created_at` (`created_at`),
        INDEX `idx_author` (`author`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    // Create discussion_replies table
    "CREATE TABLE IF NOT EXISTS `discussion_replies` (
        `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        `discussion_id` INT NOT NULL,
        `author` VARCHAR(255) NOT NULL,
        `author_role` VARCHAR(50) NOT NULL,
        `content` LONGTEXT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`discussion_id`) REFERENCES `discussions`(`id`) ON DELETE CASCADE,
        INDEX `idx_discussion_id` (`discussion_id`),
        INDEX `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    // Create users table
    "CREATE TABLE IF NOT EXISTS `users` (
      `username` VARCHAR(255) NOT NULL PRIMARY KEY,
      `password` VARCHAR(255) NOT NULL,
      `role` ENUM('student','teacher','admin') NOT NULL DEFAULT 'student',
      `name` VARCHAR(255) DEFAULT '',
      `email` VARCHAR(255) DEFAULT '',
      `academic` VARCHAR(255) DEFAULT '',
      `avatar` VARCHAR(255) DEFAULT '',
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX `idx_role` (`role`),
      INDEX `idx_email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    // Create pending_users table
    "CREATE TABLE IF NOT EXISTS `pending_users` (
      `email` VARCHAR(255) NOT NULL PRIMARY KEY,
      `name` VARCHAR(255) NOT NULL,
      `role` ENUM('student','teacher') NOT NULL DEFAULT 'student',
      `password` VARCHAR(255) NOT NULL,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX `idx_role` (`role`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    // Create assignments table
    "CREATE TABLE IF NOT EXISTS `assignments` (
      `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
      `title` VARCHAR(255) NOT NULL,
      `description` TEXT,
      `deadline` DATETIME NOT NULL,
      `max_score` INT DEFAULT 100,
      `rubric` TEXT,
      `course_code` VARCHAR(50) DEFAULT '',
      `files` JSON DEFAULT NULL,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX `idx_deadline` (`deadline`),
      INDEX `idx_course_code` (`course_code`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    // Create notes table
    "CREATE TABLE IF NOT EXISTS `notes` (
      `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
      `title` VARCHAR(255) NOT NULL,
      `topic` VARCHAR(255) DEFAULT '',
      `content` TEXT,
      `course_code` VARCHAR(50) DEFAULT '',
      `attachment_name` VARCHAR(255) DEFAULT '',
      `attachment_stored` VARCHAR(255) DEFAULT '',
      `file_size` INT DEFAULT 0,
      `version` INT DEFAULT 1,
      `downloads` INT DEFAULT 0,
      `status` VARCHAR(50) DEFAULT 'shared',
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX `idx_course_code` (`course_code`),
      INDEX `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    // Create submissions table
    "CREATE TABLE IF NOT EXISTS `submissions` (
      `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
      `assignment_id` INT NOT NULL,
      `student_name` VARCHAR(255) NOT NULL,
      `file_name` VARCHAR(255) DEFAULT '',
      `stored_name` VARCHAR(255) DEFAULT '',
      `score` INT DEFAULT NULL,
      `feedback` TEXT,
      `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX `idx_assignment_id` (`assignment_id`),
      INDEX `idx_student_name` (`student_name`),
      INDEX `idx_submitted_at` (`submitted_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    // Create files table
    "CREATE TABLE IF NOT EXISTS `files` (
      `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
      `title` VARCHAR(255) NOT NULL,
      `description` TEXT,
      `file_name` VARCHAR(255) DEFAULT '',
      `stored_name` VARCHAR(255) DEFAULT '',
      `file_size` INT DEFAULT 0,
      `file_type` VARCHAR(100) DEFAULT '',
      `uploaded_by` VARCHAR(255) DEFAULT '',
      `course_code` VARCHAR(50) DEFAULT '',
      `downloads` INT DEFAULT 0,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX `idx_uploaded_by` (`uploaded_by`),
      INDEX `idx_course_code` (`course_code`),
      INDEX `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    // Create content_statuses table
    "CREATE TABLE IF NOT EXISTS `content_statuses` (
      `content_key` VARCHAR(255) NOT NULL PRIMARY KEY,
      `status` VARCHAR(50) NOT NULL DEFAULT 'pending',
      `flag_reason` TEXT,
      `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
);

$success_count = 0;
$error_count = 0;
$messages = array();

foreach ($sql_commands as $sql) {
    if ($conn->query($sql) === TRUE) {
        $success_count++;
        $messages[] = array('type' => 'success', 'text' => 'Executed: ' . substr($sql, 0, 60) . '...');
    } else {
        $error_count++;
        $messages[] = array('type' => 'error', 'text' => 'Error: ' . $conn->error);
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduQuest Database Setup</title>
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
            padding: 2rem;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid #e5e7eb;
        }
        
        .logo {
            font-size: 2rem;
        }
        
        .title {
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .subtitle {
            color: #6b7280;
            font-size: 0.9rem;
        }
        
        .status {
            display: flex;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .status-item {
            flex: 1;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
        }
        
        .status-success {
            background: #dcfce7;
            border-left: 4px solid #22c55e;
        }
        
        .status-error {
            background: #fee2e2;
            border-left: 4px solid #ef4444;
        }
        
        .status-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .status-label {
            font-size: 0.9rem;
            color: #6b7280;
        }
        
        .messages {
            margin-bottom: 2rem;
        }
        
        .message {
            padding: 1rem;
            margin-bottom: 0.75rem;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        
        .message-success {
            background: #dcfce7;
            color: #166534;
            border-left: 4px solid #22c55e;
        }
        
        .message-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }
        
        .footer {
            padding-top: 1.5rem;
            border-top: 1px solid #e5e7eb;
            text-align: center;
        }
        
        .footer-text {
            color: #6b7280;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        
        .button {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: #22c55e;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: background 0.2s;
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }
        
        .button:hover {
            background: #16a34a;
        }
        
        .success-banner {
            background: #dcfce7;
            border: 2px solid #22c55e;
            color: #166534;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        
        .success-banner h2 {
            margin-bottom: 0.5rem;
        }
        
        .table-info {
            background: #f9fafb;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        
        .table-info h3 {
            margin-bottom: 0.75rem;
            font-size: 1rem;
        }
        
        .table-list {
            list-style: none;
            padding-left: 1rem;
        }
        
        .table-list li {
            padding: 0.5rem 0;
            color: #4b5563;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">🎓</div>
            <div>
                <div class="title">EduQuest Database Setup</div>
                <div class="subtitle">Automatic Database Configuration</div>
            </div>
        </div>
        
        <?php if ($error_count === 0): ?>
            <div class="success-banner">
                <h2>✓ Database Setup Successful!</h2>
                <p>All tables have been created successfully. Your EduQuest LMS is ready to use announcements and discussions with MySQL database.</p>
            </div>
        <?php endif; ?>
        
        <div class="status">
            <div class="status-item status-success">
                <div class="status-number"><?php echo $success_count; ?></div>
                <div class="status-label">Commands Executed</div>
            </div>
            <div class="status-item <?php echo $error_count > 0 ? 'status-error' : 'status-success'; ?>">
                <div class="status-number"><?php echo $error_count; ?></div>
                <div class="status-label"><?php echo $error_count > 0 ? 'Errors' : 'No Errors'; ?></div>
            </div>
        </div>
        
        <div class="messages">
            <h3 style="margin-bottom: 1rem; font-size: 1rem;">Execution Log:</h3>
            <?php foreach ($messages as $msg): ?>
                <div class="message message-<?php echo $msg['type']; ?>">
                    <?php echo htmlspecialchars($msg['text']); ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if ($error_count === 0): ?>
            <div class="table-info">
                <h3>📊 Created Tables:</h3>
                <ul class="table-list">
                    <li><strong>announcements</strong> - Stores all announcements from teachers and students</li>
                    <li><strong>discussions</strong> - Stores all discussion topics</li>
                    <li><strong>discussion_replies</strong> - Stores replies to discussions</li>
                    <li><strong>users</strong> - Stores all user accounts</li>
                    <li><strong>pending_users</strong> - Stores pending registration requests</li>
                    <li><strong>assignments</strong> - Stores all assignments</li>
                    <li><strong>notes</strong> - Stores all notes</li>
                    <li><strong>submissions</strong> - Stores assignment submissions</li>
                    <li><strong>files</strong> - Stores file uploads</li>
                    <li><strong>content_statuses</strong> - Stores content moderation statuses</li>
                </ul>
            </div>
            
            <div class="table-info">
                <h3>🚀 Next Steps:</h3>
                <ul class="table-list">
                    <li><a href="migrate_to_database.php" style="color:#22c55e; font-weight:bold;">Run Migration Script</a> to copy existing JSON data to database</li>
                    <li>Go to announcements.php and create a test announcement</li>
                    <li>Go to discussion.php and create a test discussion</li>
                    <li>Check your phpMyAdmin to see the data in the tables</li>
                    <li>All data is now stored in MySQL database!</li>
                </ul>
            </div>
        <?php else: ?>
            <div class="table-info" style="background: #fee2e2; border-left: 4px solid #ef4444;">
                <h3 style="color: #991b1b;">⚠️ Setup Incomplete</h3>
                <p>There were errors during setup. Check the log above and ensure:</p>
                <ul class="table-list" style="color: #991b1b;">
                    <li>MySQL is running in XAMPP Control Panel</li>
                    <li>Database username and password are correct in db_config.php</li>
                    <li>You have permission to create databases</li>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="footer">
            <p class="footer-text">Database Configuration File: db_config.php</p>
            <p class="footer-text">Schema File: schema.sql</p>
            <a href="../" class="button">← Back to EduQuest</a>
        </div>
    </div>
</body>
</html>
