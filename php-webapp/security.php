<?php
// security.php - Core security functions and session management

if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', dirname(__DIR__));
}

if (!defined('APP_SCHEME')) {
    define('APP_SCHEME', getenv('APP_SCHEME') ?: 'http');
}

if (!defined('APP_HOST')) {
    define('APP_HOST', getenv('APP_HOST') ?: 'localhost');
}

if (!defined('APP_PORT')) {
    define('APP_PORT', getenv('APP_PORT') ?: '8000');
}

if (!defined('APP_BASE_URL')) {
    $appPortSuffix = in_array(APP_PORT, ['80', '443'], true) ? '' : ':' . APP_PORT;
    define('APP_BASE_URL', APP_SCHEME . '://' . APP_HOST . $appPortSuffix);
}

if (!defined('ML_SERVICE_BASE_URL')) {
    define('ML_SERVICE_BASE_URL', rtrim(getenv('ML_SERVICE_BASE_URL') ?: 'http://127.0.0.1:5000', '/'));
}

if (!defined('ML_ANALYZE_URL')) {
    define('ML_ANALYZE_URL', ML_SERVICE_BASE_URL . '/analyze');
}

if (!defined('CONVERTER_SCRIPT_PATH')) {
    define('CONVERTER_SCRIPT_PATH', PROJECT_ROOT . '/convert_file_to_image.py');
}

if (!defined('DEFAULT_UPLOAD_DIR')) {
    define('DEFAULT_UPLOAD_DIR', __DIR__ . '/uploads/');
}

if (!defined('DEFAULT_SESSION_DIR')) {
    define('DEFAULT_SESSION_DIR', __DIR__ . '/sessions');
}

function ensureDirectoryExists($dir, $mode = 0750) {
    if (!is_dir($dir)) {
        @mkdir($dir, $mode, true);
    }

    return is_dir($dir) && is_writable($dir);
}

function getSessionStoragePath() {
    return DEFAULT_SESSION_DIR;
}

function getPreferredPythonPath() {
    $candidatePaths = [
        PROJECT_ROOT . '/ml-service/.venv/bin/python',
        PROJECT_ROOT . '/ml-service/.venv/bin/python3',
    ];

    foreach ($candidatePaths as $candidatePath) {
        if (file_exists($candidatePath) && is_executable($candidatePath)) {
            return $candidatePath;
        }
    }

    return null;
}

function isHttpEndpointAvailable($url, $timeoutSeconds = 2) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_NOBODY, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeoutSeconds);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeoutSeconds);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_errno($ch);
        curl_close($ch);

        return $curlError === 0 && $httpCode >= 200 && $httpCode < 500;
    }

    $headers = @get_headers($url);
    if ($headers === false || empty($headers[0])) {
        return false;
    }

    return strpos($headers[0], '200') !== false;
}

function isMlServiceReachable() {
    return isHttpEndpointAvailable(ML_SERVICE_BASE_URL . '/openapi.json');
}

function getSystemHealth() {
    $uploadsDir = defined('UPLOAD_DIR') ? UPLOAD_DIR : DEFAULT_UPLOAD_DIR;
    $sessionDir = getSessionStoragePath();
    $pythonPath = getPreferredPythonPath();

    return [
        'app_base_url' => APP_BASE_URL,
        'ml_service_url' => ML_SERVICE_BASE_URL,
        'session_dir' => $sessionDir,
        'session_ready' => is_dir($sessionDir) && is_writable($sessionDir),
        'uploads_dir' => $uploadsDir,
        'uploads_dir_ready' => (is_dir($uploadsDir) && is_writable($uploadsDir)) || (!is_dir($uploadsDir) && is_writable(dirname($uploadsDir))),
        'converter_script' => CONVERTER_SCRIPT_PATH,
        'converter_ready' => file_exists(CONVERTER_SCRIPT_PATH),
        'python_path' => $pythonPath,
        'python_ready' => $pythonPath !== null,
        'ml_service_ready' => isMlServiceReachable(),
        'db_connected' => isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli,
    ];
}

function enforceCanonicalBaseUrl() {
    if (PHP_SAPI === 'cli') {
        return;
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        return;
    }

    $expectedHost = APP_HOST . (in_array(APP_PORT, ['80', '443'], true) ? '' : ':' . APP_PORT);
    $currentHost = $_SERVER['HTTP_HOST'] ?? '';

    if ($currentHost !== '' && strcasecmp($currentHost, $expectedHost) !== 0) {
        header('Location: ' . APP_BASE_URL . ($_SERVER['REQUEST_URI'] ?? '/'));
        exit;
    }
}

// Start secure session
function startSecureSession() {
    // Prevent session fixation
    if (session_status() == PHP_SESSION_NONE) {
        $sessionDir = getSessionStoragePath();
        if (ensureDirectoryExists($sessionDir, 0750)) {
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

function getStatusBadgeColor($isReady) {
    return $isReady ? '#4caf50' : '#ffb74d';
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
