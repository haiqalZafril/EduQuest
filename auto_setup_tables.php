<?php
/**
 * Auto Setup Missing Tables
 * This script automatically creates all missing tables in eduquest_db database
 * Visit this file in your browser to run it
 */

require_once __DIR__ . '/db_config.php';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Auto Setup Tables</title>";
echo "<style>body{font-family:Arial;padding:2rem;background:#f5f5f5;} .container{max-width:800px;margin:0 auto;background:white;padding:2rem;border-radius:8px;} .success{color:green;} .error{color:red;} .info{color:blue;} table{border-collapse:collapse;width:100%;margin-top:1rem;} th,td{padding:0.5rem;text-align:left;border:1px solid #ddd;} th{background:#f0f0f0;}</style></head><body>";
echo "<div class='container'>";
echo "<h1>Auto Setup Missing Tables</h1>";
echo "<p>This script will automatically create all missing tables in the eduquest_db database.</p>";

// Get connection
$conn = eq_get_db();
if (!$conn) {
    die("<p class='error'>❌ Cannot connect to database. Please check db_config.php settings.</p></div></body></html>");
}

echo "<p class='success'>✓ Database connection successful!</p>";
echo "<hr>";

// SQL commands to create missing tables
$sql_commands = [
    // Users table
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
    
    // Pending Users table
    "CREATE TABLE IF NOT EXISTS `pending_users` (
      `email` VARCHAR(255) NOT NULL PRIMARY KEY,
      `name` VARCHAR(255) NOT NULL,
      `role` ENUM('student','teacher') NOT NULL DEFAULT 'student',
      `password` VARCHAR(255) NOT NULL,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX `idx_role` (`role`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    // Assignments table
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
    
    // Notes table
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
    
    // Submissions table
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
    
    // Files table
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
    
    // Content Statuses table
    "CREATE TABLE IF NOT EXISTS `content_statuses` (
      `content_key` VARCHAR(255) NOT NULL PRIMARY KEY,
      `status` VARCHAR(50) NOT NULL DEFAULT 'pending',
      `flag_reason` TEXT,
      `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX `idx_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
];

$success_count = 0;
$error_count = 0;
$table_names = ['users', 'pending_users', 'assignments', 'notes', 'submissions', 'files', 'content_statuses'];

echo "<h2>Creating Tables...</h2>";
echo "<table><tr><th>Table</th><th>Status</th></tr>";

foreach ($sql_commands as $index => $sql) {
    $table_name = $table_names[$index] ?? 'unknown';
    
    if ($conn->query($sql) === TRUE) {
        // Check if table was created or already existed
        $result = $conn->query("SHOW TABLES LIKE '$table_name'");
        if ($result && $result->num_rows > 0) {
            echo "<tr><td><strong>$table_name</strong></td><td class='success'>✓ Created/Already exists</td></tr>";
            $success_count++;
        } else {
            echo "<tr><td><strong>$table_name</strong></td><td class='error'>✕ Failed</td></tr>";
            $error_count++;
        }
    } else {
        // Check if error is because table already exists
        if (strpos($conn->error, 'already exists') !== false) {
            echo "<tr><td><strong>$table_name</strong></td><td class='info'>⏭ Already exists</td></tr>";
            $success_count++;
        } else {
            echo "<tr><td><strong>$table_name</strong></td><td class='error'>✕ Error: " . htmlspecialchars($conn->error) . "</td></tr>";
            $error_count++;
        }
    }
}

echo "</table>";

echo "<hr>";
echo "<h2>Summary</h2>";
echo "<p><strong>Tables processed:</strong> " . count($table_names) . "</p>";
echo "<p><strong>Successful:</strong> <span class='success'>$success_count</span></p>";
echo "<p><strong>Errors:</strong> <span class='error'>$error_count</span></p>";

if ($error_count == 0) {
    echo "<p class='success' style='font-weight:bold; padding:1rem; background:#d4edda; border-radius:4px;'>✓ All tables created successfully!</p>";
    echo "<p><a href='migrate_to_database.php' style='display:inline-block; padding:0.5rem 1rem; background:#007bff; color:white; text-decoration:none; border-radius:4px; margin-top:1rem;'>Next: Migrate Data →</a></p>";
} else {
    echo "<p class='error' style='font-weight:bold; padding:1rem; background:#f8d7da; border-radius:4px;'>⚠ Some errors occurred. Please check above.</p>";
}

echo "<hr>";
echo "<p><a href='index.php'>← Back to Home</a> | <a href='test_db_connection.php'>Test Connection</a></p>";
echo "</div></body></html>";
?>

