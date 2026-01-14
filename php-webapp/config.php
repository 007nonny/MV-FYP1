<?php
// config.php
// Secure Database connection for Malware Image Recognition

// Enable error display for debugging
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');
error_reporting(E_ALL);

// Database credentials - USE ENVIRONMENT VARIABLES IN PRODUCTION
$servername = getenv('DB_HOST') ?: "localhost";
$username = getenv('DB_USER') ?: "malware_user";
$password = getenv('DB_PASS') ?: "Change_This_Password_123!";
$dbname = getenv('DB_NAME') ?: "malware_db";

// Create connection with error handling
try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    // Check connection
    if ($conn->connect_error) {
        throw new Exception("Database connection failed");
    }
    
    // Set charset to prevent SQL injection
    $conn->set_charset("utf8mb4");
    
} catch (Exception $e) {
    // Log error securely without exposing details
    error_log("Database connection error: " . $e->getMessage());
    die("Service temporarily unavailable. Please try again later.");
}

// Define secure upload directory
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_FILE_SIZE', 5000000); // 5MB

// Allowed file types with MIME types (only for converted output images)
define('ALLOWED_IMAGE_TYPES', [
    'png' => ['image/png']
]);

// Allowed executable file types for conversion
define('ALLOWED_BINARY_TYPES', [
    'exe' => ['application/x-dosexec', 'application/x-msdownload', 'application/octet-stream'],
    'dll' => ['application/x-dosexec', 'application/x-msdownload', 'application/octet-stream'],
    'bin' => ['application/octet-stream'],
    'dat' => ['application/octet-stream'],
    'sys' => ['application/octet-stream'],
    'com' => ['application/x-dosexec', 'application/octet-stream']
]);
?>