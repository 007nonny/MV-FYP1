<?php
require_once 'security.php';
require_once 'config.php';

startSecureSession();
enforceCanonicalBaseUrl();
setSecurityHeaders();

$health = getSystemHealth();
$checks = [
    'Session storage' => [$health['session_ready'], $health['session_dir']],
    'Uploads directory' => [$health['uploads_dir_ready'], $health['uploads_dir']],
    'Converter script' => [$health['converter_ready'], $health['converter_script']],
    'Python environment' => [$health['python_ready'], $health['python_path'] ?: 'Python executable not found'],
    'ML service' => [$health['ml_service_ready'], $health['ml_service_url']],
    'Database' => [$health['db_connected'], $health['db_connected'] ? 'Connected' : 'Optional fallback mode active'],
];

$allCriticalReady = $health['session_ready']
    && $health['uploads_dir_ready']
    && $health['converter_ready']
    && $health['python_ready']
    && $health['ml_service_ready'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Health - Trojan Visualization</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <nav class="navbar">
        <div class="logo">
            <div class="logo-icon">☣</div>
            <h1>MALWARE <span class="highlight">VISUALIZATION</span></h1>
        </div>
        <ul class="nav-links">
            <li><a href="index.php">Dashboard</a></li>
            <li><a href="analyze.php">Threat Analysis</a></li>
            <li><a href="health.php" class="active">System Health</a></li>
        </ul>
    </nav>

    <div class="container">
        <div class="page-header">
            <h2>System Health</h2>
            <p>Use this page before your presentation to verify that the prototype is ready.</p>
        </div>

        <div style="background: rgba(0, 0, 0, 0.28); border: 1px solid <?php echo $allCriticalReady ? '#4caf50' : '#ffb74d'; ?>; color: #fff; padding: 1rem 1.25rem; border-radius: 10px; margin-bottom: 1.5rem;">
            <strong><?php echo $allCriticalReady ? 'System Ready' : 'Attention Required'; ?></strong><br>
            <?php if ($allCriticalReady): ?>
                All critical services are available. You can safely run the demo.
            <?php else: ?>
                One or more critical services are unavailable. Fix the items below before presenting.
            <?php endif; ?>
        </div>

        <div class="results-section">
            <h3>Readiness Checks</h3>
            <?php foreach ($checks as $label => [$ready, $detail]): ?>
                <div class="result-item">
                    <span class="result-label"><?php echo h($label); ?></span>
                    <span class="result-value" style="color: <?php echo $ready ? '#4caf50' : '#ffb74d'; ?>;">
                        <?php echo $ready ? 'OK' : 'Check'; ?> — <?php echo h((string)$detail); ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="info-cards" style="margin-top: 2rem;">
            <div class="info-card">
                <div class="info-card-icon">🚀</div>
                <h3>Recommended Start URL</h3>
                <p><a href="<?php echo h(APP_BASE_URL); ?>" style="color: #4fc3f7;"><?php echo h(APP_BASE_URL); ?></a></p>
            </div>
            <div class="info-card">
                <div class="info-card-icon">🧠</div>
                <h3>ML Endpoint</h3>
                <p><?php echo h(ML_ANALYZE_URL); ?></p>
            </div>
            <div class="info-card">
                <div class="info-card-icon">🛠️</div>
                <h3>Presentation Tip</h3>
                <p>Always use the same host, keep both services running, and refresh this page before starting the demo.</p>
            </div>
        </div>
    </div>
</body>
</html>
