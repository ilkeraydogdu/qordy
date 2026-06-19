<?php

declare(strict_types=1);

namespace App\Controllers\API;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';

use App\Core\Controller;
use App\Core\DependencyFactory;

/**
 * Public API - Tables & Zones & Floors
 * Extracted from APIController (refactored Q4 2026)
 */
class TablesController extends Controller
{
 /**
 * GET /api/tables
 * List all tables for a business
 */
 public function index(): void
 {
 $businessId = $this->requireBusinessId();
 $tableService = DependencyFactory::getTableService();
 $tables = $tableService->getTablesByBusiness($businessId);
 $this->jsonResponse(['tables' => $tables]);
 }

 /**
 * GET /api/zones
 */
 public function zones(): void
 {
 $businessId = $this->requireBusinessId();
 $zoneService = DependencyFactory::getZoneService();
 $zones = $zoneService->getZonesByBusiness($businessId);
 $this->jsonResponse(['zones' => $zones]);
 }

 /**
 * GET /api/floors
 */
 public function floors(): void
 {
 $businessId = $this->requireBusinessId();
 $floorService = DependencyFactory::getFloorService();
 $floors = $floorService->getFloorsByBusiness($businessId);
 $this->jsonResponse(['floors' => $floors]);
 }

 /**
 * GET /api/tables/{id}/order-sessions
 */
 public function orderSessions(): void
 {
 $tableId = $_GET['id'] ?? null;
 if (!$tableId) {
 $this->jsonError('Missing table id', 400);
 return;
 }

 $tableService = DependencyFactory::getTableService();
 $sessions = $tableService->getOrderSessions($tableId);
 $this->jsonResponse(['sessions' => $sessions]);
 }

 /**
 * GET /api/tables/{id}/qr
 * Download QR code for table
 */
 public function downloadQR(): void
 {
 $tableId = $_GET['id'] ?? null;
 if (!$tableId) {
 $this->jsonError('Missing table id', 400);
 return;
 }

 $tableService = DependencyFactory::getTableService();
 $qrData = $tableService->getQRCode($tableId);

 if (!$qrData) {
 $this->jsonError('QR code not found', 404);
 return;
 }

 header('Content-Type: image/png');
 echo $qrData;
 exit;
 }

 protected function requireBusinessId(): string
 {
 $businessId = $_GET['business_id'] ?? $_POST['business_id'] ?? null;
 if (!$businessId) {
 $this->jsonError('Missing business_id', 400);
 }
 return (string)$businessId;
 }

 protected function jsonResponse($data, int $status = 200): void
 {
 http_response_code($status);
 header('Content-Type: application/json');
 echo json_encode($data);
 exit;
 }

 protected function jsonError(string $message, int $status = 400): void
 {
 $this->jsonResponse(['error' => $message], $status);
 }
}