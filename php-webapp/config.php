<?php
// config.php
// Database connection for Malware Image Recognition prototype

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "malware_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
// ...existing code...
?>