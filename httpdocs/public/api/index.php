<?php
// Check if this is a printer-bridge request
$uri = $_SERVER['REQUEST_URI'] ?? '';
if (strpos($uri, '/api/printer-bridge') !== false) {
    require_once __DIR__ . '/printer-bridge/index.php';
    exit;
}

// Original router code for other API requests
require_once '../../app/config/config.php';
require_once '../../app/core/App.php';
require_once '../../app/core/Controller.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$app = new App\Core\App();
