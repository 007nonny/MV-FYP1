<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'config.php';

$uploadId = $_GET['id'] ?? 0;

if ($uploadId == 0) {
    header("Location: analyze.php");
    exit;
}

// Fetch result from database
$stmt = $conn->prepare("SELECT * FROM uploads WHERE id = ?");
$stmt->bind_param("i", $uploadId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    header("Location: analyze.php?error=notfound");
    exit;
}

$data = $result->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analysis Results - Malware Visualization</title>
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
        <button class="login-btn">LOGIN</button>
    </nav>

    <div class="container">
        <div class="page-header">
            <h2>🎯 Analysis Complete</h2>
            <p>Machine Learning classification results</p>
        </div>

        <div class="results-section">
            <h3>Classification Results</h3>
            
            <?php
            $severityClass = 'severity-' . strtolower($data['severity']);
            $severityIcon = [
                'safe' => '✓',
                'high' => '⚠',
                'critical' => '☠',
                'unknown' => '?'
            ];
            $icon = $severityIcon[$data['severity']] ?? '?';
            ?>
            
            <div style="text-align: center; padding: 2rem; background: rgba(0,0,0,0.3); border-radius: 8px; margin-bottom: 2rem;">
                <div style="font-size: 4rem; margin-bottom: 1rem;">
                    <?php echo $icon; ?>
                </div>
                <h2 style="font-size: 2.5rem; margin-bottom: 0.5rem; color: #fff;">
                    <?php echo htmlspecialchars($data['trojan_type']); ?>
                </h2>
                <p class="<?php echo $severityClass; ?>" style="font-size: 1.5rem; font-weight: bold;">
                    Severity: <?php echo strtoupper($data['severity']); ?>
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
                <span class="result-label">Classification:</span>
                <span class="result-value"><?php echo htmlspecialchars($data['trojan_type']); ?></span>
            </div>

            <div class="result-item">
                <span class="result-label">Threat Level:</span>
                <span class="result-value <?php echo $severityClass; ?>">
                    <?php echo strtoupper($data['severity']); ?>
                </span>
            </div>

            <div class="result-item">
                <span class="result-label">Analyzed At:</span>
                <span class="result-value"><?php echo date('F j, Y, g:i A', strtotime($data['uploaded_at'])); ?></span>
            </div>

            <?php if (file_exists($data['filename'])): ?>
            <div style="margin-top: 2rem; text-align: center;">
                <h4 style="color: #fff; margin-bottom: 1rem;">Analyzed Image</h4>
                <img src="<?php echo htmlspecialchars($data['filename']); ?>" alt="Analyzed Image" style="max-width: 100%; max-height: 400px; border-radius: 8px; border: 2px solid #444;">
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
            <?php if ($data['severity'] == 'safe'): ?>
                <div class="info-card">
                    <div class="info-card-icon" style="color: #4caf50;">✓</div>
                    <h3>Safe File</h3>
                    <p>This file appears to be benign with no detected malicious patterns.</p>
                </div>
                <div class="info-card">
                    <div class="info-card-icon">🛡️</div>
                    <h3>Recommendation</h3>
                    <p>While classified as safe, always exercise caution with unknown files.</p>
                </div>
                <div class="info-card">
                    <div class="info-card-icon">📊</div>
                    <h3>Confidence</h3>
                    <p>Classification made with high confidence based on visual patterns.</p>
                </div>
            <?php elseif (in_array($data['severity'], ['high', 'critical'])): ?>
                <div class="info-card">
                    <div class="info-card-icon" style="color: #ff3333;">⚠</div>
                    <h3>Threat Detected</h3>
                    <p>This file exhibits malicious patterns consistent with <?php echo htmlspecialchars($data['trojan_type']); ?>.</p>
                </div>
                <div class="info-card">
                    <div class="info-card-icon">🚨</div>
                    <h3>Action Required</h3>
                    <p>Quarantine or delete this file immediately. Do not execute.</p>
                </div>
                <div class="info-card">
                    <div class="info-card-icon">🔒</div>
                    <h3>Security Alert</h3>
                    <p>Report to security team and scan system for additional infections.</p>
                </div>
            <?php else: ?>
                <div class="info-card">
                    <div class="info-card-icon">❓</div>
                    <h3>Unknown Classification</h3>
                    <p>Unable to classify with sufficient confidence. May be new malware variant.</p>
                </div>
                <div class="info-card">
                    <div class="info-card-icon">🔍</div>
                    <h3>Further Analysis</h3>
                    <p>Consider submitting to additional malware analysis platforms.</p>
                </div>
                <div class="info-card">
                    <div class="info-card-icon">⚠</div>
                    <h3>Caution Advised</h3>
                    <p>Treat as potentially malicious until verified by other methods.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
