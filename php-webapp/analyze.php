<?php
require_once 'security.php';
require_once 'config.php';

startSecureSession();
setSecurityHeaders();

// Check if coming from conversion result
$preloadedImage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token - TEMPORARILY DISABLED FOR TESTING
    // if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
    //     logSecurityEvent('csrf_token_invalid', ['action' => 'analyze_preload']);
    //     die("<script>alert('Invalid security token'); window.location.href='index.php';</script>");
    // }
    
    // Sanitize preloaded image path
    if (isset($_POST['image_path'])) {
        $safePath = sanitizeFilePath($_POST['image_path'], UPLOAD_DIR);
        if ($safePath !== false) {
            $preloadedImage = $safePath;
        }
    }
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Threat Analysis - Trojan Visualization</title>
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
            <li><a href="analyze.php" class="active">Threat Analysis</a></li>
            <li><a href="#history">History</a></li>
            <li><a href="#about">About</a></li>
        </ul>
        <button class="login-btn">LOGIN</button>
    </nav>

    <div class="container">
        <div class="page-header">
            <h2>Threat Analysis</h2>
            <p>Upload a trojan visualization image for ML-based classification</p>
        </div>

        <!-- Upload Section -->
        <div class="upload-section">
            <div class="upload-box">
                <div class="upload-icon">🔍🖼️</div>
                <h3>Upload Visualization Image</h3>
                <p style="color: #999; margin-bottom: 1rem;">
                    Upload an image generated from the converter or your own trojan visualization
                </p>
                <form action="classify.php" method="post" enctype="multipart/form-data" id="classifyForm">
                    <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                    <?php if (!empty($preloadedImage)): ?>
                        <input type="hidden" name="preloaded_image" value="<?php echo h($preloadedImage); ?>">
                        <p style="color: #4caf50; margin: 1rem 0;">✓ Image from conversion loaded</p>
                    <?php endif; ?>
                    
                    <div class="file-input-wrapper">
                        <input type="file" name="imageToAnalyze" id="imageInput" accept="image/*" <?php echo empty($preloadedImage) ? 'required' : ''; ?>>
                        <label for="imageInput" class="file-input-label">
                            <?php echo empty($preloadedImage) ? 'Choose Image or Drag & Drop' : 'Or Choose Different Image'; ?>
                        </label>
                    </div>
                    <p class="supported-formats">
                        <strong>Accepted:</strong> JPG, PNG, BMP, GIF<br>
                        <strong>Max size:</strong> 5MB
                    </p>
                    <button type="submit" class="btn btn-primary">🎯 Analyze for Threats</button>
                </form>
            </div>

            <div class="preview-box" id="previewBox">
                <h3>Image Preview</h3>
                <?php if (!empty($preloadedImage)): ?>
                    <img src="<?php echo h(basename($preloadedImage)); ?>" alt="Preview" class="preview-image" id="previewImage">
                <?php else: ?>
                    <p style="color: #666; margin-top: 1rem;">Upload an image to preview</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- History Section -->
        <div class="history-section" id="history">
            <h3>📜 Analysis History</h3>
            <div class="history-table">
                <?php
                $result = $conn->query("SELECT * FROM uploads ORDER BY uploaded_at DESC LIMIT 20");
                
                if ($result && $result->num_rows > 0) {
                    echo "<table>";
                    echo "<thead><tr>";
                    echo "<th>ID</th>";
                    echo "<th>Filename</th>";
                    echo "<th>Trojan Type</th>";
                    echo "<th>Severity</th>";
                    echo "<th>Date & Time</th>";
                    echo "</tr></thead>";
                    echo "<tbody>";
                    
                    while ($row = $result->fetch_assoc()) {
                        $severityClass = 'severity-' . strtolower(preg_replace('/[^a-z0-9-]/', '', strtolower($row['severity'])));
                        echo "<tr>";
                        echo "<td>" . intval($row['id']) . "</td>";
                        echo "<td>" . h(basename($row['filename'])) . "</td>";
                        echo "<td><strong>" . h($row['trojan_type']) . "</strong></td>";
                        echo "<td class='" . h($severityClass) . "'><strong>" . h(strtoupper($row['severity'])) . "</strong></td>";
                        echo "<td>" . h(date('Y-m-d H:i:s', strtotime($row['uploaded_at']))) . "</td>";
                        echo "</tr>";
                    }
                    
                    echo "</tbody></table>";
                } else {
                    echo "<p style='text-align: center; color: #666; padding: 2rem;'>No analysis history yet. Start by analyzing your first file!</p>";
                }
                ?>
            </div>
        </div>

        <!-- Info Cards -->
        <div class="info-cards" style="margin-top: 3rem;">
            <div class="info-card">
                <div class="info-card-icon">🤖</div>
                <h3>AI-Powered Detection</h3>
                <p>Deep learning model trained on thousands of malware samples.</p>
            </div>
            <div class="info-card">
                <div class="info-card-icon">⚡</div>
                <h3>Real-time Analysis</h3>
                <p>Get instant classification results with confidence scores.</p>
            </div>
            <div class="info-card">
                <div class="info-card-icon">🎯</div>
                <h3>Multi-class Detection</h3>
                <p>Identifies Benign files and 4 types of trojans with high accuracy.</p>
            </div>
        </div>
    </div>

    <script>
        const imageInput = document.getElementById('imageInput');
        const previewBox = document.getElementById('previewBox');
        const fileLabel = document.querySelector('.file-input-label');

        imageInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                fileLabel.textContent = file.name;
                fileLabel.style.background = '#ff5555';
                
                // Show preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    const existingImg = document.getElementById('previewImage');
                    if (existingImg) {
                        existingImg.src = e.target.result;
                    } else {
                        previewBox.innerHTML = '<h3>Image Preview</h3><img src="' + e.target.result + '" alt="Preview" class="preview-image" id="previewImage">';
                    }
                }
                reader.readAsDataURL(file);
            }
        });

        // Drag and drop
        const uploadBox = document.querySelector('.upload-box');
        
        uploadBox.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.style.borderColor = '#ff5555';
        });

        uploadBox.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.style.borderColor = '#ff3333';
        });

        uploadBox.addEventListener('drop', function(e) {
            e.preventDefault();
            this.style.borderColor = '#ff3333';
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                imageInput.files = files;
                imageInput.dispatchEvent(new Event('change'));
            }
        });
    </script>
</body>
</html>
