<?php
// Database Configuration
$db_host = 'localhost';
$db_username = 'root';
$db_password = '9801';
$db_name = 'eduquest_db';

// Create connection
/* for haiqal only $conn = new mysqli($db_host, $db_username, null, $db_name, 3310); */
$conn = new mysqli($db_host, $db_username, null, $db_name,  3310);

// Check connection
if ($conn->connect_error) {
    die('Database Connection Failed: ' . $conn->connect_error);
}

// Set charset to utf8
$conn->set_charset('utf8');

?>
