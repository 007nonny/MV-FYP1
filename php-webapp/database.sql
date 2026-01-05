-- MySQL database setup for malware image recognition
CREATE DATABASE IF NOT EXISTS malware_db;
USE malware_db;

CREATE TABLE IF NOT EXISTS uploads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    trojan_type VARCHAR(100),
    severity VARCHAR(100),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
