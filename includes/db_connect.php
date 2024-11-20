<?php
$servername = "localhost"; // Or use 127.0.0.1
$username = "root";        // Your MySQL username
$password = "mysql";       // Your MySQL password
$dbname = "GP24";          // Your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
