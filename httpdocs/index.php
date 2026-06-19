<?php
/**
 * Root Index.php - Fallback for Plesk DocumentRoot
 * 
 * This file exists as a fallback when DocumentRoot points to project root
 * instead of the recommended /public directory.
 * 
 * It simply delegates to the proper public/index.php entry point.
 */

// Check if public/index.php exists and delegate to it
if (file_exists(__DIR__ . '/public/index.php')) {
    require __DIR__ . '/public/index.php';
    exit;
}

// If public/index.php doesn't exist, show configuration error
http_response_code(500);
die('Application structure error. Please ensure public/index.php exists and DocumentRoot points to /public directory.');

