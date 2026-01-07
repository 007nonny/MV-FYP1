<?php
// Handles image classification through ML service with security
require_once 'security.php';
require_once 'config.php';

startSecureSession();
setSecurityHeaders();

// Check rate limiting
if (!checkRateLimit('classification', 10, 300)) {
    logSecurityEvent('rate_limit_exceeded', ['action' => 'classification']);
    header("Location: analyze.php?error=ratelimit");
    exit;
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
    logSecurityEvent('csrf_token_invalid', ['action' => 'classification']);
    header("Location: analyze.php?error=csrf");
    exit;
}

// Create secure upload directory
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0750, true);
}

// Check if using preloaded image from conversion
$preloadedImage = $_POST['preloaded_image'] ?? '';
$fileToAnalyze = '';

if (!empty($preloadedImage)) {
    // Validate path to prevent directory traversal
    $safePath = sanitizeFilePath($preloadedImage, UPLOAD_DIR);
    if ($safePath !== false && file_exists($safePath)) {
        $fileToAnalyze = $safePath;
        $fileName = basename($safePath);
    } else {
        logSecurityEvent('path_traversal_attempt', ['attempted_path' => $preloadedImage]);
        header("Location: analyze.php?error=invalid");
        exit;
    }
} elseif (isset($_FILES["imageToAnalyze"]) && $_FILES["imageToAnalyze"]["error"] == 0) {
    // Validate uploaded file
    $validation = validateUploadedFile(
        $_FILES["imageToAnalyze"],
        ALLOWED_IMAGE_TYPES,
        MAX_FILE_SIZE
    );
    
    if (!$validation['valid']) {
        logSecurityEvent('file_validation_failed', ['errors' => $validation['errors']]);
        header("Location: analyze.php?error=validation");
        exit;
    }
    
    // Generate safe filename
    $safeFileName = generateSafeFilename($_FILES["imageToAnalyze"]["name"]);
    $targetFile = UPLOAD_DIR . $safeFileName;
    
    if (move_uploaded_file($_FILES["imageToAnalyze"]["tmp_name"], $targetFile)) {
        // SECURITY: Remove all executable permissions
        chmod($targetFile, 0440); // Read-only, no execute
        $fileToAnalyze = $targetFile;
        $fileName = $safeFileName;
    } else {
        logSecurityEvent('upload_failed');
        header("Location: analyze.php?error=upload");
        exit;
    }
} else {
    logSecurityEvent('no_file_provided');
    header("Location: analyze.php?error=nofile");
    exit;
}

// Send to ML service with timeout
$ml_url = 'http://127.0.0.1:8000/analyze';

try {
    $cfile = new CURLFile($fileToAnalyze);
    $postfields = array('file' => $cfile);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $ml_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($httpCode != 200 || empty($response)) {
        logSecurityEvent('ml_service_error', ['http_code' => $httpCode, 'error' => $curlError]);
        header("Location: analyze.php?error=mlservice");
        exit;
    }

    $result = json_decode($response, true);
    if (!$result) {
        logSecurityEvent('ml_service_invalid_response');
        header("Location: analyze.php?error=mlservice");
        exit;
    }
    
    $trojan_type = $result['trojan_type'] ?? 'Unknown';
    $severity = $result['severity'] ?? 'unknown';
    $confidence = $result['confidence'] ?? 'N/A';

    // Store in database using prepared statement
    $stmt = $conn->prepare("INSERT INTO uploads (filename, trojan_type, severity) VALUES (?, ?, ?)");
    if (!$stmt) {
        logSecurityEvent('database_prepare_failed', ['error' => $conn->error]);
        header("Location: analyze.php?error=database");
        exit;
    }
    
    $stmt->bind_param("sss", $fileName, $trojan_type, $severity);
    $stmt->execute();
    $uploadId = $stmt->insert_id;
    $stmt->close();

    // Redirect to results page
    header("Location: results.php?id=" . intval($uploadId));
    exit;
    
} catch (Exception $e) {
    logSecurityEvent('classification_exception', ['message' => $e->getMessage()]);
    header("Location: analyze.php?error=exception");
    exit;
}
?>
