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
$ml_url = ML_ANALYZE_URL;

try {
    if (!isMlServiceReachable()) {
        logSecurityEvent('ml_service_unreachable', ['url' => ML_SERVICE_BASE_URL]);
        header("Location: analyze.php?error=mlservice");
        exit;
    }

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
    $trojan_subtype = $result['trojan_subtype'] ?? 'Unknown';
    $severity = $result['severity'] ?? 'unknown';
    $confidence = $result['confidence'] ?? 'N/A';

    $sessionResult = [
        'id' => 0,
        'filename' => $fileToAnalyze,
        'trojan_type' => $trojan_type,
        'trojan_subtype' => $trojan_subtype,
        'severity' => $severity,
        'confidence' => $confidence,
        'uploaded_at' => date('Y-m-d H:i:s'),
    ];

    // If the database is unavailable, keep the result in session and
    // continue to the results page instead of failing with a fatal error.
    if (!isset($conn) || !($conn instanceof mysqli)) {
        $_SESSION['last_analysis'] = $sessionResult;

        header("Location: results.php?source=session&confidence=" . urlencode($confidence));
        exit;
    }

    // Store in database using prepared statement
    // Support both schema versions (with/without confidence column)
    $hasConfidenceColumn = false;
    $columnCheck = $conn->query("SHOW COLUMNS FROM uploads LIKE 'confidence'");
    if ($columnCheck && $columnCheck->num_rows > 0) {
        $hasConfidenceColumn = true;
    }

    if ($hasConfidenceColumn) {
        $stmt = $conn->prepare("INSERT INTO uploads (filename, trojan_type, trojan_subtype, severity, confidence) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) {
            logSecurityEvent('database_prepare_failed', ['error' => $conn->error]);
            $_SESSION['last_analysis'] = $sessionResult;
            header("Location: results.php?source=session&confidence=" . urlencode($confidence));
            exit;
        }
        $stmt->bind_param("sssss", $fileName, $trojan_type, $trojan_subtype, $severity, $confidence);
    } else {
        $stmt = $conn->prepare("INSERT INTO uploads (filename, trojan_type, trojan_subtype, severity) VALUES (?, ?, ?, ?)");
        if (!$stmt) {
            logSecurityEvent('database_prepare_failed', ['error' => $conn->error]);
            $_SESSION['last_analysis'] = $sessionResult;
            header("Location: results.php?source=session&confidence=" . urlencode($confidence));
            exit;
        }
        $stmt->bind_param("ssss", $fileName, $trojan_type, $trojan_subtype, $severity);
    }

    if (!$stmt->execute()) {
        logSecurityEvent('database_execute_failed', ['error' => $stmt->error]);
        $stmt->close();
        $_SESSION['last_analysis'] = $sessionResult;
        header("Location: results.php?source=session&confidence=" . urlencode($confidence));
        exit;
    }

    $uploadId = $stmt->insert_id;
    $stmt->close();

    // Redirect to results page
    header("Location: results.php?id=" . intval($uploadId) . "&confidence=" . urlencode($confidence));
    exit;
    
} catch (Exception $e) {
    logSecurityEvent('classification_exception', ['message' => $e->getMessage()]);
    header("Location: analyze.php?error=exception");
    exit;
}
?>
