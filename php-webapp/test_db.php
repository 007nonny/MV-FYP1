<?php
require 'config.php';

if (isset($conn) && $conn instanceof mysqli) {
    echo "Database connection successful!";
} else {
    echo "Database connection failed!";
}
?>
