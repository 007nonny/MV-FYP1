<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'config.php';
require_once 'security.php';

startSecureSession();
enforceCanonicalBaseUrl();
setSecurityHeaders();

$uploadId = $_GET['id'] ?? 0;
$useSessionFallback = (($_GET['source'] ?? '') === 'session');
$dbAvailable = isset($conn) && ($conn instanceof mysqli);
$usingSessionResult = (($useSessionFallback || !$dbAvailable) && isset($_SESSION['last_analysis']));

if ($usingSessionResult) {
    $data = $_SESSION['last_analysis'];
    $data['id'] = $data['id'] ?? 0;
    $data['uploaded_at'] = $data['uploaded_at'] ?? date('Y-m-d H:i:s');

    $trojanType = trim((string)($data['trojan_type'] ?? 'Unknown'));
    $trojanSubtype = trim((string)($data['trojan_subtype'] ?? 'Unknown'));
    $severity = strtolower(trim((string)($data['severity'] ?? 'unknown')));

    $confidence = 'N/A';
    if (isset($data['confidence']) && $data['confidence'] !== '') {
        $confidence = (string)$data['confidence'];
    } elseif (isset($_GET['confidence']) && preg_match('/^[0-9]+(\.[0-9]+)?%$/', $_GET['confidence'])) {
        $confidence = $_GET['confidence'];
    }

    $isMalicious = ($trojanType !== 'Uncertain');
    $statusLabel = ($trojanType === 'Uncertain')
        ? 'Low Confidence — Uncertain Classification'
        : (in_array($severity, ['high', 'critical'], true) ? 'Trojan Detected' : 'Malware Detected');

    $severityClass = 'severity-' . preg_replace('/[^a-z0-9-]/', '', $severity);
    $severityIcon = [
        'safe'     => '✓',
        'medium'   => '⚠',
        'high'     => '⚠',
        'critical' => '☠',
        'unknown'  => '?'
    ];
    $icon = $severityIcon[$severity] ?? ($isMalicious ? '⚠' : '?');
    $data = [
        'id' => 0,
        'filename' => $data['filename'] ?? '',
        'trojan_type' => $trojanType,
        'trojan_subtype' => $trojanSubtype,
        'severity' => $severity,
        'uploaded_at' => $data['uploaded_at'],
    ];
} else {
    if ($uploadId == 0) {
        header("Location: analyze.php");
        exit;
    }

    // Fetch result from database
    $stmt = $conn->prepare("SELECT * FROM uploads WHERE id = ?");
    if (!$stmt) {
        if (isset($_SESSION['last_analysis'])) {
            header("Location: results.php?source=session&confidence=" . urlencode($_GET['confidence'] ?? 'N/A'));
            exit;
        }
        header("Location: analyze.php?error=database");
        exit;
    }

    $stmt->bind_param("i", $uploadId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 0) {
        header("Location: analyze.php?error=notfound");
        exit;
    }

    $data = $result->fetch_assoc();
    $stmt->close();

    $trojanType = trim((string)($data['trojan_type'] ?? 'Unknown'));
    $trojanSubtype = trim((string)($data['trojan_subtype'] ?? 'Unknown'));
    $severity = strtolower(trim((string)($data['severity'] ?? 'unknown')));

    // Prefer DB confidence if available, fallback to query param for backward compatibility
    $confidence = 'N/A';
    if (isset($data['confidence']) && $data['confidence'] !== '') {
        $confidence = (string)$data['confidence'];
    } elseif (isset($_GET['confidence']) && preg_match('/^[0-9]+(\.[0-9]+)?%$/', $_GET['confidence'])) {
        $confidence = $_GET['confidence'];
    }

    $isMalicious = ($trojanType !== 'Uncertain');

    $statusLabel = ($trojanType === 'Uncertain')
        ? 'Low Confidence — Uncertain Classification'
        : (in_array($severity, ['high', 'critical'], true) ? 'Trojan Detected' : 'Malware Detected');

    $severityClass = 'severity-' . preg_replace('/[^a-z0-9-]/', '', $severity);
    $severityIcon = [
        'safe'     => '✓',
        'medium'   => '⚠',
        'high'     => '⚠',
        'critical' => '☠',
        'unknown'  => '?'
    ];
    $icon = $severityIcon[$severity] ?? ($isMalicious ? '⚠' : '?');
}

$displayImageUrl = '';
if (!empty($data['filename'])) {
    $candidateBasename = basename($data['filename']);
    if (file_exists(UPLOAD_DIR . $candidateBasename)) {
        $displayImageUrl = 'uploads/' . rawurlencode($candidateBasename);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analysis Results - Trojan Visualization</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar">
        <div class="logo">
            <div class="logo-icon">☣</div>
            <h1>MALWARE <span class="highlight">VISUALIZATION</span></h1>
        </div>
        <ul class="nav-links">
            <li><a href="index.php">Dashboard</a></li>
            <li><a href="analyze.php">Threat Analysis</a></li>
            <li><a href="analyze.php#history">History</a></li>
            <li><a href="#about">About</a></li>
        </ul>
    </nav>

    <div class="container">
        <div class="page-header">
            <h2>🎯 Analysis Complete</h2>
            <p>Machine Learning classification results</p>
        </div>

        <?php if ($usingSessionResult && !$dbAvailable): ?>
            <div style="background: rgba(255, 183, 77, 0.12); border: 1px solid #ffb74d; color: #ffe0b2; padding: 1rem 1.25rem; border-radius: 8px; margin-bottom: 1.5rem;">
                Results are being shown from the active session because database storage is not available.
            </div>
        <?php elseif ($usingSessionResult && $useSessionFallback): ?>
            <div style="background: rgba(100, 181, 246, 0.12); border: 1px solid #64b5f6; color: #bbdefb; padding: 1rem 1.25rem; border-radius: 8px; margin-bottom: 1.5rem;">
                This page is showing a previously saved session result. Run a new analysis to save the latest result to the database.
            </div>
        <?php endif; ?>

        <div class="results-section">
            <h3>Classification Results</h3>
            
            <div style="text-align: center; padding: 2rem; background: rgba(0,0,0,0.3); border-radius: 8px; margin-bottom: 2rem;">
                <div style="font-size: 4rem; margin-bottom: 1rem;">
                    <?php echo $icon; ?>
                </div>
                <h2 style="font-size: 2.5rem; margin-bottom: 0.3rem; color: #fff;">
                    <?php echo htmlspecialchars($trojanType); ?>
                </h2>
                <?php if (!empty($trojanSubtype) && $trojanSubtype !== 'Unknown'): ?>
                <p style="font-size: 1.2rem; color: #ff6b6b; margin-bottom: 0.4rem; font-weight: bold;">
                    <?php echo htmlspecialchars($trojanSubtype); ?>
                </p>
                <?php endif; ?>
                <p class="<?php echo $severityClass; ?>" style="font-size: 1.3rem; font-weight: bold; margin-bottom: 0.3rem;">
                    Severity: <?php echo ucfirst($severity); ?>
                </p>
                <p style="font-size: 1.1rem; color: #4fc3f7; font-weight: bold;">
                    Confidence: <?php echo htmlspecialchars($confidence); ?>
                </p>
            </div>

            <div class="result-item">
                <span class="result-label">Analysis ID:</span>
                <span class="result-value">#<?php echo $data['id']; ?></span>
            </div>

            <div class="result-item">
                <span class="result-label">Filename:</span>
                <span class="result-value"><?php echo htmlspecialchars(basename($data['filename'])); ?></span>
            </div>

            <div class="result-item">
                <span class="result-label">Malware Category:</span>
                <span class="result-value"><?php echo htmlspecialchars($trojanType); ?></span>
            </div>

            <div class="result-item">
                <span class="result-label">Predicted Family:</span>
                <span class="result-value" style="color: #ff6b6b; font-size: 1.1rem;">
                    <?php echo (!empty($trojanSubtype) && $trojanSubtype !== 'Unknown') ? htmlspecialchars($trojanSubtype) : '—'; ?>
                </span>
            </div>

            <div class="result-item">
                <span class="result-label">Severity Level:</span>
                <span class="result-value <?php echo $severityClass; ?>">
                    <?php echo ($severity === 'unknown') ? 'Unknown' : ucfirst($severity); ?>
                </span>
            </div>

            <div class="result-item">
                <span class="result-label">Model Confidence:</span>
                <span class="result-value" style="color: #4fc3f7; font-size: 1.1rem;"><?php echo htmlspecialchars($confidence); ?></span>
            </div>

            <div class="result-item">
                <span class="result-label">Analyzed At:</span>
                <span class="result-value"><?php echo date('F j, Y, g:i A', strtotime($data['uploaded_at'])); ?></span>
            </div>

            <?php if (!empty($displayImageUrl)): ?>
            <div style="margin-top: 2rem; text-align: center;">
                <h4 style="color: #fff; margin-bottom: 1rem;">Analyzed Image</h4>
                <img src="<?php echo htmlspecialchars($displayImageUrl); ?>" alt="Analyzed Image" style="max-width: 100%; max-height: 400px; border-radius: 8px; border: 2px solid #444;">
            </div>
            <?php endif; ?>

            <div class="action-buttons">
                <a href="analyze.php" class="btn btn-primary">🔄 Analyze Another</a>
                <a href="analyze.php#history" class="btn btn-secondary">📜 View History</a>
                <a href="index.php" class="btn btn-secondary">🏠 Back to Dashboard</a>
            </div>
        </div>

        <!-- Threat Information -->
        <div class="info-cards" style="margin-top: 3rem;">
            <?php if (!$isMalicious): ?>
                <div class="info-card">
                    <div class="info-card-icon">❓</div>
                    <h3>Uncertain Classification</h3>
                    <p>Confidence was too low to confirm. Closest match: <strong><?php echo htmlspecialchars($trojanSubtype); ?></strong>. Treat with caution.</p>
                </div>
                <div class="info-card">
                    <div class="info-card-icon">🔍</div>
                    <h3>Further Analysis Recommended</h3>
                    <p>Submit to additional malware platforms or re-scan with a cleaner image for a better result.</p>
                </div>
                <div class="info-card">
                    <div class="info-card-icon">📊</div>
                    <h3>Model Confidence</h3>
                    <p>Prediction confidence: <?php echo htmlspecialchars($confidence); ?>. Threshold is 50%.</p>
                </div>
            <?php elseif (in_array($severity, ['high', 'critical'], true)): ?>
                <div class="info-card">
                    <div class="info-card-icon" style="color: #ff3333;">⚠</div>
                    <h3>Trojan Detected</h3>
                    <p>This file exhibits malicious patterns consistent with <?php echo htmlspecialchars($trojanType); ?>.</p>
                </div>
                <div class="info-card">
                    <div class="info-card-icon">🚨</div>
                    <h3>Action Required</h3>
                    <p>Quarantine or delete this file immediately. Do not execute.</p>
                </div>
                <div class="info-card">
                    <div class="info-card-icon">📊</div>
                    <h3>Confidence</h3>
                    <p>Model confidence for this prediction: <?php echo htmlspecialchars($confidence); ?>.</p>
                </div>
            <?php else: ?>
                <div class="info-card">
                    <div class="info-card-icon" style="color: #ffb74d;">⚠</div>
                    <h3>Malware Detected</h3>
                    <p>This file exhibits malicious patterns consistent with <?php echo htmlspecialchars($trojanSubtype ?: $trojanType); ?>.</p>
                </div>
                <div class="info-card">
                    <div class="info-card-icon">🔍</div>
                    <h3>Further Analysis</h3>
                    <p>Consider submitting to additional malware analysis platforms for confirmation.</p>
                </div>
                <div class="info-card">
                    <div class="info-card-icon">📊</div>
                    <h3>Confidence</h3>
                    <p>Model confidence for this prediction: <?php echo htmlspecialchars($confidence); ?>.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
