<?php
require_once 'security.php';
require_once 'config.php';

startSecureSession();
enforceCanonicalBaseUrl();
setSecurityHeaders();

// Sanitize and validate image path to prevent path traversal
$imagePath = $_GET['image'] ?? '';
if (empty($imagePath)) {
    header("Location: index.php?error=noresult");
    exit;
}

// Build full path and validate
$fullPath = UPLOAD_DIR . basename($imagePath);
if (!file_exists($fullPath)) {
    header("Location: index.php?error=notfound");
    exit;
}

$imagePath = $fullPath;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conversion Result - Trojan Visualization</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="logo">
            <div class="logo-icon">☣</div>
            <h1>TROJAN <span class="highlight">VISUALIZATION</span></h1>
        </div>
        <ul class="nav-links">
            <li><a href="index.php">Dashboard</a></li>
            <li><a href="analyze.php">Threat Analysis</a></li>
            <li><a href="#history">History</a></li>
            <li><a href="#about">About</a></li>
        </ul>
    </nav>

    <div class="container">
        <div class="page-header">
            <h2>✓ Conversion Successful</h2>
            <p>Your file has been converted to a trojan visualization image</p>
        </div>

        <div class="results-section">
            <h3>Generated Visualization</h3>
            <div style="text-align: center; padding: 2rem;">
                <img src="uploads/<?php echo h(basename($imagePath)); ?>" alt="Trojan Visualization" style="max-width: 100%; max-height: 600px; border-radius: 8px; border: 2px solid #444;">
            </div>
            
            <div class="action-buttons">
                <form action="analyze.php" method="post" style="display: inline;">
                    <?php $csrfToken = generateCSRFToken(); ?>
                    <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                    <input type="hidden" name="image_path" value="<?php echo h($imagePath); ?>">
                    <button type="submit" class="btn btn-primary">🔍 Analyze This Image</button>
                </form>
                <a href="uploads/<?php echo h(basename($imagePath)); ?>" download class="btn btn-secondary">💾 Download Image</a>
                <a href="index.php" class="btn btn-secondary">🔄 Convert Another File</a>
            </div>
        </div>

        <!-- Info about next steps -->
        <div class="info-cards">
            <div class="info-card">
                <div class="info-card-icon">🎯</div>
                <h3>Next: Threat Analysis</h3>
                <p>Click "Analyze This Image" to run ML-based malware classification on this visualization.</p>
            </div>
            <div class="info-card">
                <div class="info-card-icon">📊</div>
                <h3>View Results</h3>
                <p>Get detailed classification including trojan type, severity level, and confidence score.</p>
            </div>
            <div class="info-card">
                <div class="info-card-icon">📜</div>
                <h3>History Tracking</h3>
                <p>All analyses are saved in your history for future reference.</p>
            </div>
        </div>
    </div>
</body>
</html>
