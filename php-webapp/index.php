<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Entry point for the web app
// Handles file upload and displays results
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Malware Image Recognition - Upload</title>
</head>
<body>
    <h1>Upload a File for Malware Analysis</h1>
    <form action="upload.php" method="post" enctype="multipart/form-data">
        <input type="file" name="fileToUpload" required>
        <button type="submit">Upload & Analyze</button>
    </form>
    <p><small>Accepted formats:</small></p>
    <ul style="font-size: 0.9em;">
        <li><strong>Images:</strong> JPG, PNG, BMP, GIF</li>
        <li><strong>Binaries:</strong> EXE, DLL, BIN, DAT, SYS, COM (auto-converted to images)</li>
        <li><strong>Max size:</strong> 5MB</li>
    </ul>
</body>
</html>