<?php
/**
 * Generate SQL INSERT statements from users.json
 * Run this from command line: php generate_user_insert_sql.php
 * Or access via browser to download the SQL file
 */

// Read users.json
$json_file = __DIR__ . '/data/users.json';
if (!file_exists($json_file)) {
    die("Error: users.json not found at $json_file\n");
}

$users = json_decode(file_get_contents($json_file), true);
if (!$users) {
    die("Error: Could not parse users.json\n");
}

// Generate SQL
$sql = "-- Auto-generated SQL to insert users from JSON into database\n";
$sql .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n\n";
$sql .= "USE `eduquest_db`;\n\n";
$sql .= "-- Insert users\n";

foreach ($users as $username => $user) {
    $username_escaped = addslashes($username);
    $password = addslashes($user['password'] ?? '');
    $role = addslashes($user['role'] ?? 'student');
    $name = addslashes($user['name'] ?? '');
    $email = addslashes($user['email'] ?? '');
    $academic = addslashes($user['academic'] ?? '');
    $avatar = addslashes($user['avatar'] ?? '');
    
    $sql .= "INSERT INTO `users` (`username`, `password`, `role`, `name`, `email`, `academic`, `avatar`) VALUES\n";
    $sql .= "('$username_escaped', '$password', '$role', '$name', '$email', '$academic', '$avatar')\n";
    $sql .= "ON DUPLICATE KEY UPDATE\n";
    $sql .= "  `password` = '$password',\n";
    $sql .= "  `role` = '$role',\n";
    $sql .= "  `name` = '$name',\n";
    $sql .= "  `email` = '$email',\n";
    $sql .= "  `academic` = '$academic',\n";
    $sql .= "  `avatar` = '$avatar';\n\n";
}

$sql .= "-- Total users: " . count($users) . "\n";

// Output SQL file
$output_file = __DIR__ . '/insert_users.sql';
file_put_contents($output_file, $sql);

// If running from browser, offer download
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="insert_users.sql"');
    echo $sql;
    echo "\n\n-- File also saved to: $output_file\n";
    echo "-- You can import this file in phpMyAdmin\n";
} else {
    echo "✓ SQL file generated successfully!\n";
    echo "Location: $output_file\n";
    echo "Total users: " . count($users) . "\n\n";
    echo "Next steps:\n";
    echo "1. Open phpMyAdmin (http://localhost/phpmyadmin/)\n";
    echo "2. Select 'eduquest_db' database\n";
    echo "3. Click 'Import' tab\n";
    echo "4. Choose file: insert_users.sql\n";
    echo "5. Click 'Go'\n";
}
?>
