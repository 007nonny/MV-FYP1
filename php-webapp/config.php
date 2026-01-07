<?php
// config.php
// Secure Database connection for Malware Image Recognition

// Disable error display in production
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

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

// Allowed file types with MIME types
define('ALLOWED_IMAGE_TYPES', [
    'jpg' => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png' => ['image/png'],
    'bmp' => ['image/bmp', 'image/x-ms-bmp'],
    'gif' => ['image/gif']
]);

define('ALLOWED_BINARY_TYPES', [
    'exe' => ['application/x-dosexec', 'application/x-msdownload', 'application/octet-stream'],
    'dll' => ['application/x-dosexec', 'application/x-msdownload', 'application/octet-stream'],
    'bin' => ['application/octet-stream'],
    'dat' => ['application/octet-stream'],
    'sys' => ['application/octet-stream'],
    'com' => ['application/x-dosexec', 'application/octet-stream']
]);
?>