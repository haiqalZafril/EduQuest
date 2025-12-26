<?php
// Database Configuration
$db_host = 'localhost';
$db_username = 'root';
$db_password = '';
$db_name = 'eduquest_db';

// Create connection
$conn = new mysqli($db_host, $db_username, $db_password, $db_name);

// Check connection
if ($conn->connect_error) {
    die('Database Connection Failed: ' . $conn->connect_error);
}

// Set charset to utf8
$conn->set_charset('utf8');

?>
