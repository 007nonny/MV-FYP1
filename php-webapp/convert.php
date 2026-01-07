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

// Validate CSRF token
if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
    logSecurityEvent('csrf_token_invalid', ['action' => 'file_conversion']);
    die("<script>alert('Invalid security token'); window.location.href='index.php';</script>");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Converting... - Malware Visualization</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <nav class="navbar">
        <div class="logo">
            <div class="logo-icon">☣</div>
            <h1>MALWARE <span class="highlight">VISUALIZATION</span></h1>
        </div>
    </nav>
    <div class="container">
        <div class="page-header">
            <h2>🔄 Converting File to Image...</h2>
            <p>Please wait while we validate and process your file</p>
        </div>
        <div style="text-align: center; padding: 3rem;">
            <div class="loading" style="width: 60px; height: 60px; border-width: 6px; margin: 2rem auto;"></div>
            <p style="color: #999;">Preparing file conversion...</p>
        </div>
    </div>
</body>
</html>
<?php
flush();

// Create secure upload directory
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0750, true); // Secure permissions
}

if (!isset($_FILES["fileToUpload"])) {
    logSecurityEvent('no_file_uploaded');
    echo "<script>alert('No file uploaded'); window.location.href='index.php';</script>";
    exit;
}

// Validate uploaded file
$validation = validateUploadedFile(
    $_FILES["fileToUpload"],
    array_merge(ALLOWED_IMAGE_TYPES, ALLOWED_BINARY_TYPES),
    MAX_FILE_SIZE
);

if (!$validation['valid']) {
    logSecurityEvent('file_validation_failed', ['errors' => $validation['errors']]);
    echo "<script>alert('File validation failed: " . implode(', ', $validation['errors']) . "'); window.location.href='index.php';</script>";
    exit;
}

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
        echo "<script>window.location.href='conversion_result.php?image=" . urlencode(basename($convertedImage)) . "';</script>";
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
    echo "<script>alert('Upload failed'); window.location.href='index.php';</script>";
    exit;
}
?>
