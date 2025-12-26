<?php
// Database Configuration for XAMPP
$db_host = 'localhost';
$db_username = 'root';
$db_password = '';  // Empty string for XAMPP default (no password). Change if your MySQL has a password
$db_name = 'eduquest_db';

// Try to connect - test both common ports
$ports_to_try = [3306, 3310];  // Standard port first, then custom port
$conn = null;
$last_error = '';

foreach ($ports_to_try as $db_port) {
    $test_conn = @new mysqli($db_host, $db_username, $db_password, $db_name, $db_port);
    
    // Check if connection was successful
    if ($test_conn && !$test_conn->connect_error) {
        // Connection successful!
        $test_conn->set_charset('utf8');
        $conn = $test_conn;
        break; // Success, exit loop
    } else {
        // Connection failed, try next port
        if ($test_conn && $test_conn->connect_error) {
            $last_error = $test_conn->connect_error . " (tried port $db_port)";
            $test_conn->close();
        } else {
            $last_error = "Could not connect to MySQL (tried port $db_port)";
        }
        $conn = null;
    }
}

// If all ports failed, set error message
if (!$conn) {
    $GLOBALS['db_connection_error'] = "Failed to connect to MySQL. Last error: $last_error. Tried ports: " . implode(', ', $ports_to_try);
}

?>
