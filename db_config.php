<?php
// Database Configuration
$db_host = 'localhost';
$db_username = 'root';
$db_password = '';  // Empty password for local XAMPP
$db_name = 'eduquest_db';
$db_port = 3310;

// Check if we need to create a new connection
$need_connection = true;
if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
    // Connection exists, check if it's still alive
    try {
        if (@$GLOBALS['conn']->ping()) {
            $need_connection = false;
        } else {
            // Connection is dead, clear it
            $GLOBALS['conn'] = null;
        }
    } catch (Exception $e) {
        // Connection object is in bad state, clear it
        $GLOBALS['conn'] = null;
    }
}

// Create connection if needed
if ($need_connection) {
    $GLOBALS['conn'] = new mysqli($db_host, $db_username, $db_password, $db_name, $db_port);
    
    // Check connection
    if ($GLOBALS['conn']->connect_error) {
        die('Database Connection Failed: ' . $GLOBALS['conn']->connect_error);
    }
    
    // Set charset to utf8
    $GLOBALS['conn']->set_charset('utf8mb4');
}

// Make $conn available in local scope for backward compatibility
$conn = $GLOBALS['conn'];

?>
