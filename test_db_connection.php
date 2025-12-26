<?php
// Test Database Connection Script
// Visit this file in your browser to test your database connection settings

echo "<h2>Database Connection Test</h2>";
echo "<p>Testing connection to MySQL...</p>";

// Test standard XAMPP settings first
$db_host = 'localhost';
$db_username = 'root';
$db_password = '';
$db_port = 3306;
$db_name = 'eduquest_db';

echo "<h3>Testing with standard XAMPP settings:</h3>";
echo "Host: $db_host<br>";
echo "Username: $db_username<br>";
echo "Password: " . (empty($db_password) ? '(empty)' : '***') . "<br>";
echo "Port: $db_port<br>";
echo "Database: $db_name<br><br>";

$conn = @new mysqli($db_host, $db_username, $db_password, $db_name, $db_port);

if ($conn->connect_error) {
    echo "<p style='color: red;'>❌ Connection Failed: " . $conn->connect_error . "</p>";
    
    // Try without database name (to see if MySQL is running at all)
    echo "<h3>Trying to connect to MySQL server (without database):</h3>";
    $conn2 = @new mysqli($db_host, $db_username, $db_password, '', $db_port);
    if ($conn2->connect_error) {
        echo "<p style='color: red;'>❌ Cannot connect to MySQL server: " . $conn2->connect_error . "</p>";
        echo "<p><strong>Possible solutions:</strong></p>";
        echo "<ul>";
        echo "<li>Make sure MySQL is running in XAMPP Control Panel</li>";
        echo "<li>Check if port 3306 is correct (try 3310 if you have custom MySQL)</li>";
        echo "<li>Verify username and password</li>";
        echo "</ul>";
    } else {
        echo "<p style='color: green;'>✓ MySQL server is running!</p>";
        echo "<p style='color: orange;'>⚠ But database '$db_name' might not exist.</p>";
        echo "<p>You may need to create the database first using phpMyAdmin or setup_database.php</p>";
        $conn2->close();
    }
    
    // Try port 3310 if 3306 failed
    if ($db_port == 3306) {
        echo "<h3>Trying port 3310:</h3>";
        $conn3 = @new mysqli($db_host, $db_username, $db_password, $db_name, 3310);
        if ($conn3->connect_error) {
            echo "<p style='color: red;'>❌ Port 3310 also failed: " . $conn3->connect_error . "</p>";
        } else {
            echo "<p style='color: green;'>✓ Connection successful on port 3310!</p>";
            echo "<p>Update db_config.php to use port 3310</p>";
            $conn3->close();
        }
    }
} else {
    echo "<p style='color: green;'>✓ Connection Successful!</p>";
    
    // Check if database exists
    $result = $conn->query("SHOW DATABASES LIKE '$db_name'");
    if ($result && $result->num_rows > 0) {
        echo "<p style='color: green;'>✓ Database '$db_name' exists</p>";
        
        // Check if tables exist
        $conn->select_db($db_name);
        $tables = ['announcements', 'discussions', 'discussion_replies'];
        echo "<h3>Checking tables:</h3>";
        foreach ($tables as $table) {
            $result = $conn->query("SHOW TABLES LIKE '$table'");
            if ($result && $result->num_rows > 0) {
                echo "<p style='color: green;'>✓ Table '$table' exists</p>";
            } else {
                echo "<p style='color: orange;'>⚠ Table '$table' does not exist</p>";
            }
        }
    } else {
        echo "<p style='color: orange;'>⚠ Database '$db_name' does not exist</p>";
        echo "<p>You can create it using phpMyAdmin or setup_database.php</p>";
    }
    
    $conn->close();
}

echo "<hr>";
echo "<p><a href='db_config.php'>View db_config.php</a> | ";
echo "<a href='setup_database.php'>Run Database Setup</a> | ";
echo "<a href='index.php'>Back to Home</a></p>";
?>

