# Security Implementation Guide

## Overview
This document outlines the security measures implemented in the Malware Recognition website and setup instructions.

## Security Features Implemented

### 1. Input Validation & Sanitization
- **File Upload Validation**: MIME type checking, size limits, extension whitelisting
- **Path Traversal Prevention**: All file paths validated against allowed directories
- **SQL Injection Prevention**: Prepared statements for all database queries
- **XSS Prevention**: Output encoding using `htmlspecialchars()` with `ENT_QUOTES`

### 2. CSRF Protection
- CSRF tokens generated and validated on all state-changing operations
- Tokens stored in secure session and validated using `hash_equals()`

### 3. Session Security
- HTTP-only cookies to prevent JavaScript access
- Secure cookie flag (enable when using HTTPS)
- Strict same-site policy
- Session ID regeneration
- Session timeout (30 minutes)

### 4. Security Headers
- `X-Frame-Options: DENY` - Prevent clickjacking
- `X-Content-Type-Options: nosniff` - Prevent MIME sniffing
- `X-XSS-Protection: 1; mode=block` - Enable XSS filter
- `Content-Security-Policy` - Restrict resource loading
- `Referrer-Policy` - Control referrer information

### 5. File Security
- Uploaded files stored with secure permissions (0440)
- Upload directory configured to prevent script execution (.htaccess)
- Random filename generation to prevent overwrites
- Uploaded files cannot be executed

### 6. Rate Limiting
- 10 uploads per 5 minutes per session
- 10 classifications per 5 minutes per session
- Prevents DoS and brute force attacks

### 7. Command Injection Prevention
- All shell commands use `escapeshellarg()` for arguments
- No user input directly in shell commands
- Validation of script paths before execution

### 8. Error Handling
- Errors logged to file, not displayed to users
- Generic error messages to prevent information disclosure
- Security events logged with IP and timestamp

### 9. Database Security
- Credentials stored in environment variables
- Prepared statements prevent SQL injection
- UTF-8 character set to prevent injection
- Secure connection error handling

## Setup Instructions

### 1. Database Setup

Create a dedicated MySQL user with limited privileges:

```sql
-- Create database
CREATE DATABASE malware_db;

-- Create user with strong password
CREATE USER 'malware_user'@'localhost' IDENTIFIED BY 'YourStrongPassword123!';

-- Grant only necessary privileges
GRANT SELECT, INSERT, UPDATE ON malware_db.* TO 'malware_user'@'localhost';
FLUSH PRIVILEGES;

-- Create tables
USE malware_db;
CREATE TABLE uploads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    trojan_type VARCHAR(100),
    severity VARCHAR(50),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_uploaded_at (uploaded_at)
);
```

### 2. Environment Configuration

```bash
# Copy example env file
cp .env.example .env

# Edit with secure values
nano .env
```

Update `.env` with your database credentials.

### 3. Directory Permissions

```bash
# Create necessary directories
mkdir -p php-webapp/uploads
mkdir -p php-webapp/logs

# Set secure permissions
chmod 750 php-webapp/uploads
chmod 750 php-webapp/logs
chmod 640 php-webapp/config.php
chmod 640 php-webapp/.env

# Ensure .htaccess is in uploads
chmod 644 php-webapp/uploads/.htaccess
```

### 4. Apache/XAMPP Configuration

Add to your Apache configuration or `.htaccess`:

```apache
# Disable directory listing
Options -Indexes

# Enable .htaccess overrides
AllowOverride All

# Limit request size
LimitRequestBody 5242880

# PHP security settings
php_flag display_errors Off
php_flag log_errors On
php_value error_log /path/to/php-webapp/logs/error.log
```

### 5. HTTPS Setup (Highly Recommended)

```bash
# Generate self-signed certificate for testing
sudo openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout /opt/lampp/etc/ssl.key/server.key \
  -out /opt/lampp/etc/ssl.crt/server.crt

# Enable SSL module
sudo /opt/lampp/lampp enablessl

# Update security.php to enable HTTPS headers (line 59)
# Uncomment: header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
```

### 6. File Upload Testing

Test that uploads are secure:

```bash
# Try to upload a PHP file (should be blocked)
# Try to access uploaded files directly (should not execute)
# Verify .htaccess is working
```

## Security Checklist

- [ ] Database user has minimum required privileges
- [ ] Strong database password set
- [ ] `.env` file created and secured (not in git)
- [ ] Upload directory has `.htaccess` protection
- [ ] Logs directory created with proper permissions
- [ ] HTTPS enabled (for production)
- [ ] Error display disabled in production
- [ ] File permissions are restrictive (750 for dirs, 640 for files)
- [ ] Test CSRF protection
- [ ] Test file upload validation
- [ ] Test rate limiting
- [ ] Review security logs regularly

## Regular Maintenance

1. **Monitor Logs**: Check `logs/security.log` for suspicious activity
2. **Update Dependencies**: Keep PHP, MySQL, and libraries updated
3. **Backup Database**: Regular backups with secure storage
4. **Review Uploads**: Periodically check uploaded files
5. **Rotate Logs**: Implement log rotation to manage disk space

## Vulnerability Reporting

If you discover a security vulnerability, please email: [your-security-email]

## Additional Hardening (Optional)

### Fail2Ban Integration
```bash
# Create fail2ban filter for repeated failed uploads
sudo nano /etc/fail2ban/filter.d/malware-webapp.conf
```

### Web Application Firewall
Consider implementing ModSecurity rules for additional protection.

### Network Segmentation
- Run ML service on isolated network
- Use firewall rules to restrict access
- Implement VPN for admin access

## Testing Security

```bash
# Test SQL injection
# Test XSS
# Test path traversal
# Test CSRF
# Test file upload bypass
# Test rate limiting
# Use tools: OWASP ZAP, Burp Suite
```

## References

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [PHP Security Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/PHP_Configuration_Cheat_Sheet.html)
- [CWE Common Weaknesses](https://cwe.mitre.org/)
