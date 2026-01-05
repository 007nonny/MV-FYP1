<?php
// Handles image classification through ML service
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'config.php';

$targetDir = "uploads/";
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0777, true);
}

// Check if using preloaded image from conversion
$preloadedImage = $_POST['preloaded_image'] ?? '';
$fileToAnalyze = '';

if (!empty($preloadedImage) && file_exists($preloadedImage)) {
    // Use the preloaded image
    $fileToAnalyze = $preloadedImage;
    $fileName = basename($preloadedImage);
} elseif (isset($_FILES["imageToAnalyze"]) && $_FILES["imageToAnalyze"]["error"] == 0) {
    // Upload new image
    $fileName = basename($_FILES["imageToAnalyze"]["name"]);
    $targetFile = $targetDir . $fileName;
    $fileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
    
    $imageTypes = array("jpg", "jpeg", "png", "bmp", "gif");
    
    if (!in_array($fileType, $imageTypes)) {
        header("Location: analyze.php?error=invalidtype");
        exit;
    }
    
    if ($_FILES["imageToAnalyze"]["size"] > 5000000) {
        header("Location: analyze.php?error=filesize");
        exit;
    }
    
    if (move_uploaded_file($_FILES["imageToAnalyze"]["tmp_name"], $targetFile)) {
        $fileToAnalyze = $targetFile;
    } else {
        header("Location: analyze.php?error=upload");
        exit;
    }
} else {
    header("Location: analyze.php?error=nofile");
    exit;
}

// Send to ML service
$ml_url = 'http://127.0.0.1:8000/analyze';
$cfile = new CURLFile($fileToAnalyze);
$postfields = array('file' => $cfile);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $ml_url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode != 200 || empty($response)) {
    header("Location: analyze.php?error=mlservice");
    exit;
}

$result = json_decode($response, true);
$trojan_type = $result['trojan_type'] ?? 'Unknown';
$severity = $result['severity'] ?? 'unknown';
$confidence = $result['confidence'] ?? 'N/A';

// Store in database
$stmt = $conn->prepare("INSERT INTO uploads (filename, trojan_type, severity) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $fileToAnalyze, $trojan_type, $severity);
$stmt->execute();
$uploadId = $stmt->insert_id;
$stmt->close();

// Redirect to results page
header("Location: results.php?id=" . $uploadId);
exit;
?>
