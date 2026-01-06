<?php
// Handles file to image conversion
ini_set('display_errors', 1);
error_reporting(E_ALL);
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

$targetDir = "uploads/";
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0777, true);
}

if (!isset($_FILES["fileToUpload"])) {
    echo "<script>alert('No file uploaded'); window.location.href='index.php';</script>";
    exit;
}

$fileName = basename($_FILES["fileToUpload"]["name"]);
$targetFile = $targetDir . $fileName;

if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $targetFile)) {
    // SECURITY: Remove all executable permissions from uploaded file
    chmod($targetFile, 0444); // Read-only, no execute permissions
    
    // Convert to image
    $convertedImage = $targetDir . pathinfo($fileName, PATHINFO_FILENAME) . "_viz.png";
    $converterScript = "/opt/lampp/htdocs/malware/convert_file_to_image.py";
    
    // Use the Python from the virtual environment that has numpy/PIL already installed
    $pythonPath = "/home/kali/Desktop/FYP1/MalwareImageRecognitionFYP1/ml-service/.venv/bin/python3";
    
    // Make sure uploads directory is writable
    chmod($targetDir, 0777);
    
    // Fix: Use system libraries instead of LAMPP's old ones
    // This prevents the CXXABI version conflict
    $cmd = "LD_LIBRARY_PATH=/usr/lib/x86_64-linux-gnu:$LD_LIBRARY_PATH " . 
           escapeshellarg($pythonPath) . " " . escapeshellarg($converterScript) . " " . 
           escapeshellarg($targetFile) . " " . 
           escapeshellarg($convertedImage) . " 2>&1";
    $output = shell_exec($cmd);
    
    if (file_exists($convertedImage)) {
        // Success - redirect to conversion result page with image path
        echo "<script>window.location.href='conversion_result.php?image=" . urlencode($convertedImage) . "';</script>";
        exit;
    } else {
        echo "<div class='container' style='margin-top: 2rem;'>";
        echo "<div class='alert alert-error'>";
        echo "<h3>❌ Conversion Failed</h3>";
        echo "<p>Unable to convert file to image. Details:</p>";
        echo "<pre style='background: #000; padding: 1rem; border-radius: 4px; overflow-x: auto;'>" . htmlspecialchars($output) . "</pre>";
        echo "<a href='index.php' class='btn btn-primary' style='margin-top: 1rem;'>Try Again</a>";
        echo "</div></div>";
        exit;
    }
} else {
    echo "<script>alert('Upload failed'); window.location.href='index.php';</script>";
    exit;
}
?>
