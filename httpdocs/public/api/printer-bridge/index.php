<?php
// Printer Bridge API router
// Route all /api/printer-bridge/* requests through App router

require_once __DIR__ . '/../../app/config/config.php';
require_once __DIR__ . '/../../app/core/App.php';
require_once __DIR__ . '/../../app/core/Controller.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$app = new App\Core\App();
