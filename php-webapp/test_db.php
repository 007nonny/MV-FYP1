<?php
require 'config.php';

if ($conn) {
    echo "Database connection successful!";
} else {
    echo "Database connection failed!";
}
?>
