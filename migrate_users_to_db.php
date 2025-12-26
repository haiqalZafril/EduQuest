<?php
/**
 * User Migration Script - Migrate all users from JSON to MySQL Database
 * Run this script by accessing: http://localhost:3310/EduQuest/migrate_users_to_db.php
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database Configuration (inline to avoid connection errors)
$db_host = 'localhost';
$db_username = 'root';
$db_password = '';
$db_name = 'eduquest_db';
$db_port = 3310;

// Try to create connection
$conn = null;
try {
    $conn = @new mysqli($db_host, $db_username, $db_password, $db_name, $db_port);
    if ($conn->connect_error) {
        $conn = null;
    } else {
        $conn->set_charset('utf8mb4');
    }
} catch (Exception $e) {
    $conn = null;
}

// HTML Header
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Migration - EduQuest</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            border-bottom: 3px solid #3498db;
            padding-bottom: 10px;
        }
        .success {
            color: #27ae60;
            padding: 10px;
            margin: 5px 0;
            background: #d4edda;
            border-left: 4px solid #27ae60;
        }
        .error {
            color: #e74c3c;
            padding: 10px;
            margin: 5px 0;
            background: #f8d7da;
            border-left: 4px solid #e74c3c;
        }
        .info {
            color: #2980b9;
            padding: 10px;
            margin: 5px 0;
            background: #d1ecf1;
            border-left: 4px solid #2980b9;
        }
        .summary {
            background: #ecf0f1;
            padding: 20px;
            border-radius: 5px;
            margin-top: 20px;
        }
        .summary h2 {
            margin-top: 0;
            color: #2c3e50;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 User Migration to Database</h1>
        
<?php

$migrated = 0;
$skipped = 0;
$errors = 0;

// Step 1: Check database connection
echo "<div class='info'><strong>Step 1:</strong> Checking database connection...</div>";
if (!$conn) {
    echo "<div class='error'>❌ Database connection failed!</div>";
    echo "<div class='info'><strong>Current settings:</strong><br>";
    echo "Host: $db_host<br>";
    echo "Port: $db_port<br>";
    echo "Database: $db_name<br>";
    echo "Username: $db_username<br><br>";
    echo "<strong>Possible solutions:</strong><br>";
    echo "1. Make sure XAMPP MySQL is running<br>";
    echo "2. Check if port 3310 is correct (might be 3306)<br>";
    echo "3. Verify database 'eduquest_db' exists in phpMyAdmin<br>";
    echo "4. Try accessing: <a href='http://localhost/phpmyadmin/' target='_blank'>phpMyAdmin</a></div>";
    
    // Try alternative port
    echo "<div class='info'><strong>Testing alternative port 3306...</strong></div>";
    $conn_alt = @new mysqli($db_host, $db_username, $db_password, $db_name, 3306);
    if ($conn_alt && !$conn_alt->connect_error) {
        echo "<div class='success'>✓ Connection works on port 3306! Please update db_config.php to use port 3306</div>";
        $conn_alt->close();
    }
    
    die("</div></body></html>");
}
echo "<div class='success'>✓ Database connection successful! (Port: $db_port)</div>";

// Step 2: Check if users table exists
echo "<div class='info'><strong>Step 2:</strong> Checking if users table exists...</div>";
$table_check = $conn->query("SHOW TABLES LIKE 'users'");
if (!$table_check || $table_check->num_rows == 0) {
    echo "<div class='error'>❌ Users table does not exist! Please run add_missing_tables.sql first.</div>";
    echo "<div class='info'>You can run the SQL file in phpMyAdmin:<br>";
    echo "1. Open phpMyAdmin (http://localhost/phpmyadmin/)<br>";
    echo "2. Select eduquest_db database<br>";
    echo "3. Click 'Import' tab<br>";
    echo "4. Upload add_missing_tables.sql file<br>";
    echo "5. Click 'Go'</div>";
    die("</div></body></html>");
}
echo "<div class='success'>✓ Users table exists!</div>";

// Step 3: Load users from JSON
echo "<div class='info'><strong>Step 3:</strong> Loading users from users.json...</div>";
$json_file = __DIR__ . '/data/users.json';
if (!file_exists($json_file)) {
    echo "<div class='error'>❌ users.json file not found at: $json_file</div>";
    die("</div></body></html>");
}

$users_json = json_decode(file_get_contents($json_file), true);
if (!$users_json || !is_array($users_json)) {
    echo "<div class='error'>❌ Failed to parse users.json or file is empty.</div>";
    die("</div></body></html>");
}

$total_users = count($users_json);
echo "<div class='success'>✓ Found $total_users users in JSON file.</div>";

// Step 4: Migrate users
echo "<div class='info'><strong>Step 4:</strong> Migrating users to database...</div>";
echo "<hr>";

foreach ($users_json as $username => $user_data) {
    // Check if user already exists
    $username_escaped = $conn->real_escape_string($username);
    $check_query = "SELECT username FROM users WHERE username = '$username_escaped' LIMIT 1";
    $result = $conn->query($check_query);
    
    if ($result && $result->num_rows > 0) {
        echo "<div class='info'>⏭ User already exists: <strong>$username</strong> (skipped)</div>";
        $skipped++;
        continue;
    }
    
    // Prepare user data
    $password = $conn->real_escape_string($user_data['password'] ?? '');
    $role = $conn->real_escape_string($user_data['role'] ?? 'student');
    $name = $conn->real_escape_string($user_data['name'] ?? '');
    $email = $conn->real_escape_string($user_data['email'] ?? '');
    $academic = $conn->real_escape_string($user_data['academic'] ?? '');
    $avatar = $conn->real_escape_string($user_data['avatar'] ?? '');
    
    // Insert user
    $insert_query = "INSERT INTO users (username, password, role, name, email, academic, avatar) 
                     VALUES ('$username_escaped', '$password', '$role', '$name', '$email', '$academic', '$avatar')";
    
    if ($conn->query($insert_query)) {
        echo "<div class='success'>✓ Migrated user: <strong>$username</strong> ($name) - Role: $role</div>";
        $migrated++;
    } else {
        echo "<div class='error'>✕ Error migrating user <strong>$username</strong>: " . $conn->error . "</div>";
        $errors++;
    }
}

// Step 5: Verify migration
echo "<hr>";
echo "<div class='info'><strong>Step 5:</strong> Verifying migration...</div>";
$count_query = "SELECT COUNT(*) as total FROM users";
$count_result = $conn->query($count_query);
$db_count = 0;
if ($count_result) {
    $row = $count_result->fetch_assoc();
    $db_count = $row['total'];
}
echo "<div class='success'>✓ Total users now in database: <strong>$db_count</strong></div>";

// Summary
echo "<div class='summary'>";
echo "<h2>📊 Migration Summary</h2>";
echo "<p><strong>Users in JSON file:</strong> $total_users</p>";
echo "<p><strong>Successfully migrated:</strong> <span style='color:#27ae60;'>$migrated</span></p>";
echo "<p><strong>Already existed (skipped):</strong> <span style='color:#2980b9;'>$skipped</span></p>";
echo "<p><strong>Errors:</strong> <span style='color:#e74c3c;'>$errors</span></p>";
echo "<p><strong>Total users in database:</strong> <span style='color:#2c3e50;'>$db_count</span></p>";

if ($errors == 0 && $migrated > 0) {
    echo "<p style='color:#27ae60; font-weight:bold;'>🎉 Migration completed successfully!</p>";
} elseif ($errors == 0 && $migrated == 0 && $skipped > 0) {
    echo "<p style='color:#2980b9; font-weight:bold;'>✓ All users were already in the database.</p>";
} elseif ($errors > 0) {
    echo "<p style='color:#e74c3c; font-weight:bold;'>⚠ Migration completed with errors.</p>";
}
echo "</div>";

?>
    </div>
</body>
</html>
