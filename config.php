<?php
// Database configuration
define('DB_HOST', 'fdb1028.awardspace.net');
define('DB_USERNAME', '4561858_webrover');
define('DB_PASSWORD', '%,rJ+aX,9lmC@u/U');
define('DB_NAME', '4561858_webrover');

// Function to connect to the database
function connectDB() {
    $db = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
    
    // Check connection
    if ($db->connect_error) {
        die("Connection failed: " . $db->connect_error);
    }
    return $db;
}
?>
