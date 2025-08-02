<?php
// db_connect.php - Establishes the connection to your MySQL database.

// --- Database Configuration ---
// Replace these with your actual database credentials.
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'courier_management_system');

// --- Establish Connection ---
// Create a new MySQLi object to connect to the database.
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// --- Connection Check ---
// Verify that the connection was successful. If not, terminate the script
// and display an error message for debugging purposes.
if ($conn->connect_error) {
    // In a production environment, you would log this error instead of displaying it.
    die("Connection failed: " . $conn->connect_error);
}

// --- Set Character Set ---
// Ensure that data is stored and retrieved using the UTF-8 character set,
// which supports a wide range of characters.
$conn->set_charset("utf8mb4");

?>
