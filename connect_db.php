<?php
// connect_db.php for XAMPP
$servername = "localhost";
$username = "root";           // Default XAMPP username
$password = "";               // Default XAMPP password (empty)
$dbname = "memoraid_db";      // Your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Connection failed: ' . $conn->connect_error]));
}

// Set charset to utf8
$conn->set_charset("utf8");
?>