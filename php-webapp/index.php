<?php
require_once 'security.php';
startSecureSession();
setSecurityHeaders();
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document to Image Converter - Malware Visualization</title>
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
            <li><a href="index.php" class="active">Dashboard</a></li>
            <li><a href="analyze.php">Threat Analysis</a></li>
            <li><a href="#history">History</a></li>
            <li><a href="#about">About</a></li>
        </ul>
        <button class="login-btn">LOGIN</button>
    </nav>

    <!-- Main Container -->
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h2>Document to Image Converter</h2>
            <p>Transform Malicious Documents into Visual Images</p>
        </div>

        <!-- Upload Section -->
        <div class="upload-section">
            <!-- Upload Box -->
            <div class="upload-box">
                <div class="upload-icon">📄🔒</div>
                <h3>Upload Your File</h3>
                <form action="convert.php" method="post" enctype="multipart/form-data" id="uploadForm">
                    <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                    <div class="file-input-wrapper">
                        <input type="file" name="fileToUpload" id="fileInput" required>
                        <label for="fileInput" class="file-input-label">Drag & Drop or Browse</label>
                    </div>
                    <p class="supported-formats">
                        <strong>Supported Formats:</strong> Any file type (.exe, .dll, .pdf, .txt, .doc, etc.)
                    </p>
                    <button type="submit" class="btn btn-primary">Convert to Image</button>
                </form>
            </div>

            <!-- Preview Box -->
            <div class="preview-box" id="previewBox">
                <h3>Generated Image Preview</h3>
                <p style="color: #666; margin-top: 1rem;">Upload a file to see the visualization</p>
            </div>
        </div>

        <!-- Info Cards -->
        <div class="info-cards">
            <div class="info-card">
                <div class="info-card-icon">🎨</div>
                <h3>Visualizing Malware</h3>
                <p>Convert documents into images to reveal hidden threats.</p>
            </div>
            <div class="info-card">
                <div class="info-card-icon">🔍</div>
                <h3>File Analysis</h3>
                <p>See the malicious components and suspicious activities.</p>
            </div>
            <div class="info-card">
                <div class="info-card-icon">🛡️</div>
                <h3>Forensic Insights</h3>
                <p>Ideal for researchers and cybersecurity experts.</p>
            </div>
        </div>
    </div>

    <script>
        // File input handling
        const fileInput = document.getElementById('fileInput');
        const fileLabel = document.querySelector('.file-input-label');

        fileInput.addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name;
            if (fileName) {
                fileLabel.textContent = fileName;
                fileLabel.style.background = '#ff5555';
            }
        });

        // Drag and drop
        const uploadBox = document.querySelector('.upload-box');
        
        uploadBox.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.style.borderColor = '#ff5555';
            this.style.background = 'rgba(40, 40, 55, 0.8)';
        });

        uploadBox.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.style.borderColor = '#ff3333';
            this.style.background = 'rgba(30, 30, 45, 0.8)';
        });

        uploadBox.addEventListener('drop', function(e) {
            e.preventDefault();
            this.style.borderColor = '#ff3333';
            this.style.background = 'rgba(30, 30, 45, 0.8)';
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                fileLabel.textContent = files[0].name;
                fileLabel.style.background = '#ff5555';
            }
        });
    </script>
</body>
</html>