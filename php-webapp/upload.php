<?php
// Handles file upload, sends to ML service, displays results, and stores in DB
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'config.php';

$targetDir = "uploads/";
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0777, true);
}

if (!isset($_FILES["fileToUpload"])) {
    echo "<h2>Error: No file uploaded</h2>";
    echo "<p>Please go back and select a file.</p>";
    echo "<a href='index.php'>Go Back</a>";
    exit;
}

if (isset($_FILES["fileToUpload"])) {
    $fileName = basename($_FILES["fileToUpload"]["name"]);
    $targetFile = $targetDir . $fileName;
    $fileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
    
    // Allowed image and binary types
    $imageTypes = array("jpg", "jpeg", "png", "bmp", "gif");
    $binaryTypes = array("exe", "dll", "bin", "dat", "sys", "com");
    
    if (!in_array($fileType, array_merge($imageTypes, $binaryTypes)) && $fileType != "") {
        echo "<h2>Error: Unsupported file type.</h2>";
        echo "<p>Allowed: Images (JPG, PNG, BMP, GIF) or Binaries (EXE, DLL, BIN, DAT, SYS, COM)</p>";
        echo "<a href='index.php'>Go Back</a>";
        exit;
    }
    
    // Check file size (max 5MB)
    if ($_FILES["fileToUpload"]["size"] > 5000000) {
        echo "<h2>Error: File is too large. Maximum size is 5MB.</h2>";
        echo "<a href='index.php'>Go Back</a>";
        exit;
    }
    
    if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $targetFile)) {
        $fileToAnalyze = $targetFile;
        
        // If it's a binary file, convert to image first
        if (in_array($fileType, $binaryTypes) || $fileType == "") {
            echo "<p>🔄 Converting binary file to image visualization...</p>";
            $convertedImage = $targetDir . pathinfo($fileName, PATHINFO_FILENAME) . "_viz.png";
            $converterScript = "/home/kali/Desktop/FYP1/MalwareImageRecognitionFYP1/convert_file_to_image.py";
            
            $cmd = "python3 " . escapeshellarg($converterScript) . " " . 
                   escapeshellarg($targetFile) . " " . 
                   escapeshellarg($convertedImage) . " 2>&1";
            $output = shell_exec($cmd);
            
            if (file_exists($convertedImage)) {
                $fileToAnalyze = $convertedImage;
                echo "<p>✓ Converted to image successfully!</p>";
            } else {
                echo "<h2>Error: Failed to convert binary to image</h2>";
                echo "<pre>" . htmlspecialchars($output) . "</pre>";
                echo "<a href='index.php'>Go Back</a>";
                exit;
            }
        }
        
        // Send file to ML service
        $ml_url = 'http://127.0.0.1:5000/analyze';
        $cfile = new CURLFile($fileToAnalyze);
        $postfields = array('file' => $cfile);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $ml_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($response, true);
        $trojan_type = $result['trojan_type'] ?? 'unknown';
        $severity = $result['severity'] ?? 'unknown';
        $confidence = $result['confidence'] ?? 'N/A';
        echo "<h2>File has been uploaded successfully.</h2>";
        echo "<h3>Analysis Result:</h3>";
        echo "Trojan Type: " . htmlspecialchars($trojan_type) . "<br>";
        echo "Severity: " . htmlspecialchars($severity) . "<br>";
        echo "Confidence: " . htmlspecialchars($confidence) . "<br>";

        // Store in DB only if the connection is available
        if (isset($conn) && $conn instanceof mysqli) {
            $stmt = $conn->prepare("INSERT INTO uploads (filename, trojan_type, severity) VALUES (?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("sss", $targetFile, $trojan_type, $severity);
                $stmt->execute();
                $stmt->close();
            }
        } else {
            echo "<p><em>Database unavailable, result not saved to history.</em></p>";
        }
    } else {
        echo "Sorry, there was an error uploading your file.";
    }
} else {
    echo "No file uploaded.";
}
?>