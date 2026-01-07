-- Secure Database Setup Script for Malware Recognition Website
-- Run this script as MySQL root user

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS malware_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create dedicated user with strong password
-- IMPORTANT: Change 'YourStrongPassword123!' to a secure password
CREATE USER IF NOT EXISTS 'malware_user'@'localhost' IDENTIFIED BY 'YourStrongPassword123!';

-- Grant only necessary privileges (no DELETE, no DROP)
GRANT SELECT, INSERT, UPDATE ON malware_db.* TO 'malware_user'@'localhost';

-- Apply privileges
FLUSH PRIVILEGES;

-- Use the database
USE malware_db;

-- Create uploads table with proper indexes
CREATE TABLE IF NOT EXISTS uploads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    trojan_type VARCHAR(100) NOT NULL,
    severity VARCHAR(50) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_uploaded_at (uploaded_at),
    INDEX idx_trojan_type (trojan_type),
    INDEX idx_severity (severity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Display success message
SELECT 'Database setup complete! Remember to update your .env file with the password.' AS message;
