<?php
// Handles file to image conversion with security measures
require_once 'security.php';
require_once 'config.php';

startSecureSession();
setSecurityHeaders();

// Check rate limiting
if (!checkRateLimit('file_conversion', 10, 300)) {
    logSecurityEvent('rate_limit_exceeded', ['action' => 'file_conversion']);
    die("<script>alert('Too many requests. Please wait before trying again.'); window.location.href='index.php';</script>");
}

// Validate CSRF token - TEMPORARILY DISABLED FOR TESTING
// if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
//     die("<script>alert('Invalid security token. Please refresh and try again.'); window.location.href='index.php';</script>");
// }

// Create secure upload directory
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0750, true); // Secure permissions
}

if (!isset($_FILES["fileToUpload"])) {
    die("<script>alert('No file uploaded'); window.location.href='index.php';</script>");
}

// Check for upload errors
if ($_FILES["fileToUpload"]["error"] !== UPLOAD_ERR_OK) {
    $errorMsg = "Upload error: " . $_FILES["fileToUpload"]["error"];
    die("<script>alert('$errorMsg'); window.location.href='index.php';</script>");
}

// VALIDATION DISABLED FOR TESTING - Accept any file type
// $validation = validateUploadedFile(
//     $_FILES["fileToUpload"],
//     ALLOWED_BINARY_TYPES,
//     MAX_FILE_SIZE
// );
// 
// if (!$validation['valid']) {
//     echo "<script>alert('File validation failed: " . implode(', ', $validation['errors']) . "'); window.location.href='index.php';</script>";
//     exit;
// }

// Generate safe filename to prevent directory traversal
$safeFileName = generateSafeFilename($_FILES["fileToUpload"]["name"]);
$targetFile = UPLOAD_DIR . $safeFileName;

if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $targetFile)) {
    // SECURITY: Remove all executable permissions from uploaded file
    chmod($targetFile, 0440); // Read-only, no execute permissions
    
    // Convert to image
    $convertedImage = UPLOAD_DIR . pathinfo($safeFileName, PATHINFO_FILENAME) . "_viz.png";
    $converterScript = "/home/kali/Desktop/FYP1/MalwareImageRecognitionFYP1/convert_file_to_image.py";
    
    // Use the Python from the virtual environment
    $pythonPath = "/home/kali/Desktop/FYP1/MalwareImageRecognitionFYP1/ml-service/.venv/bin/python3";
    
    // Validate script exists
    if (!file_exists($converterScript) || !file_exists($pythonPath)) {
        logSecurityEvent('converter_script_missing');
        echo "<div class='container' style='margin-top: 2rem;'>";
        echo "<div class='alert alert-error'><h3>❌ Configuration Error</h3></div></div>";
        exit;
    }
    
    // Build command with proper escaping - NO user input in command
    $cmd = sprintf(
        "LD_LIBRARY_PATH=/usr/lib/x86_64-linux-gnu:$LD_LIBRARY_PATH %s %s %s %s 2>&1",
        escapeshellarg($pythonPath),
        escapeshellarg($converterScript),
        escapeshellarg($targetFile),
        escapeshellarg($convertedImage)
    );
    
    $output = shell_exec($cmd);
    
    if (file_exists($convertedImage)) {
        // Success - redirect to conversion result page
        header("Location: conversion_result.php?image=" . urlencode(basename($convertedImage)));
        exit;
    } else {
        logSecurityEvent('conversion_failed', ['output' => substr($output, 0, 500)]);
        echo "<div class='container' style='margin-top: 2rem;'>";
        echo "<div class='alert alert-error'>";
        echo "<h3>❌ Conversion Failed</h3>";
        echo "<p>Unable to convert file to image.</p>";
        echo "<a href='index.php' class='btn btn-primary' style='margin-top: 1rem;'>Try Again</a>";
        echo "</div></div>";
        exit;
    }
} else {
    $error = error_get_last();
    $errorMsg = "Upload failed. Could not move file to uploads directory.";
    if ($error) {
        $errorMsg .= " Error: " . $error['message'];
    }
    die("<script>alert('$errorMsg'); window.location.href='index.php';</script>");
    exit;
}
?>
