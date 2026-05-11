<?php
// security.php - Core security functions and session management

// Start secure session
function startSecureSession() {
    // Prevent session fixation
    if (session_status() == PHP_SESSION_NONE) {
        $sessionDir = __DIR__ . '/sessions';
        if (!is_dir($sessionDir)) {
            mkdir($sessionDir, 0750, true);
        }
        if (is_dir($sessionDir) && is_writable($sessionDir)) {
            session_save_path($sessionDir);
        }

        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 1 : 0);
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_samesite', 'Strict');
        session_start();
        
        // Regenerate session ID periodically
        if (!isset($_SESSION['created'])) {
            $_SESSION['created'] = time();
        } else if (time() - $_SESSION['created'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['created'] = time();
        }
    }
}

// Generate and validate CSRF tokens
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Sanitize file paths to prevent directory traversal
function sanitizeFilePath($path, $allowedDir) {
    $realPath = realpath($path);
    $realAllowedDir = realpath($allowedDir);
    
    // Check if path exists and is within allowed directory
    if ($realPath === false || strpos($realPath, $realAllowedDir) !== 0) {
        return false;
    }
    return $realPath;
}

// Validate uploaded file
function validateUploadedFile($file, $allowedTypes, $maxSize = 5000000) {
    $errors = [];
    
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "File upload error";
        return ['valid' => false, 'errors' => $errors];
    }
    
    // Check file size
    if ($file['size'] > $maxSize) {
        $errors[] = "File too large. Maximum size: " . ($maxSize / 1000000) . "MB";
    }
    
    // Validate MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    // Get file extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Check if extension and MIME type are allowed
    if (!isset($allowedTypes[$extension])) {
        $errors[] = "File type not allowed: " . htmlspecialchars($extension);
    } elseif (!in_array($mimeType, $allowedTypes[$extension])) {
        $errors[] = "File MIME type mismatch. Expected: " . implode(', ', $allowedTypes[$extension]) . ", Got: " . $mimeType;
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'mime_type' => $mimeType,
        'extension' => $extension
    ];
}

// Generate safe filename
function generateSafeFilename($originalName) {
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
    $uniqueId = bin2hex(random_bytes(8));
    return $safeName . '_' . $uniqueId . '.' . $extension;
}

// Set security headers
function setSecurityHeaders() {
    // Prevent clickjacking
    header("X-Frame-Options: DENY");
    
    // Prevent MIME type sniffing
    header("X-Content-Type-Options: nosniff");
    
    // XSS Protection
    header("X-XSS-Protection: 1; mode=block");
    
    // Content Security Policy
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:;");
    
    // Referrer Policy
    header("Referrer-Policy: strict-origin-when-cross-origin");
    
    // Force HTTPS (uncomment when using HTTPS)
    // header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
}

// Rate limiting
function checkRateLimit($identifier, $maxAttempts = 5, $timeWindow = 300) {
    if (!isset($_SESSION['rate_limit'])) {
        $_SESSION['rate_limit'] = [];
    }
    
    $now = time();
    $key = $identifier;
    
    // Clean old entries
    if (isset($_SESSION['rate_limit'][$key])) {
        $_SESSION['rate_limit'][$key] = array_filter(
            $_SESSION['rate_limit'][$key],
            function($timestamp) use ($now, $timeWindow) {
                return ($now - $timestamp) < $timeWindow;
            }
        );
    } else {
        $_SESSION['rate_limit'][$key] = [];
    }
    
    // Check limit
    if (count($_SESSION['rate_limit'][$key]) >= $maxAttempts) {
        return false;
    }
    
    // Add current attempt
    $_SESSION['rate_limit'][$key][] = $now;
    return true;
}

// Sanitize output
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Log security events
function logSecurityEvent($event, $details = []) {
    $logFile = __DIR__ . '/logs/security.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0750, true);
    }
    
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'event' => $event,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'details' => $details
    ];
    
    file_put_contents($logFile, json_encode($logEntry) . PHP_EOL, FILE_APPEND | LOCK_EX);
}
?>
