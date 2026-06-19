<?php

declare(strict_types=1);

namespace App\Controllers\API\Mobile;

require_once __DIR__ . '/../../../core/Controller.php';
require_once __DIR__ . '/../../../core/DependencyFactory.php';

use App\Core\Controller;
use App\Core\DependencyFactory;

/**
 * Mobile API - Orders
 * Extracted from MobileAPIController (refactored Q4 2026)
 */
class MobileOrderController extends Controller
{
 /**
 * GET /api/mobile/orders
 */
 public function getOrders(): void
 {
 $businessId = $this->requireBusinessId();
 $status = $_GET['status'] ?? null;
 $limit = (int)($_GET['limit'] ?? 50);

 $orderService = DependencyFactory::getOrderService();
 $orders = $orderService->getOrdersByBusiness($businessId, $status, $limit);

 $this->jsonResponse(['orders' => $orders]);
 }

 /**
 * POST /api/mobile/orders
 */
 public function createOrder(): void
 {
 $businessId = $this->requireBusinessId();
 $input = $this->jsonInput();

 $orderService = DependencyFactory::getOrderService();
 $result = $orderService->placeOrder([
 'business_id' => $businessId,
 'table_id' => $input['table_id'] ?? null,
 'items' => $input['items'] ?? [],
 'notes' => $input['notes'] ?? ''
 ]);

 if (!$result) {
 $this->jsonError('Failed to create order', 500);
 return;
 }

 $this->jsonResponse([
 'status' => 'success',
 'order_id' => $result['order_id']
 ], 201);
 }

 /**
 * PATCH /api/mobile/orders/{id}/status
 */
 public function updateStatus(): void
 {
 $orderId = $_GET['id'] ?? null;
 $input = $this->jsonInput();
 $newStatus = $input['status'] ?? null;

 if (!$orderId || !$newStatus) {
 $this->jsonError('Missing order_id or status', 400);
 return;
 }

 $orderService = DependencyFactory::getOrderService();
 $result = $orderService->updateOrderStatus($orderId, $newStatus);

 if (!$result) {
 $this->jsonError('Failed to update status', 500);
 return;
 }

 $this->jsonResponse(['status' => 'success']);
 }

 protected function requireBusinessId(): string
 {
 // Implement tenant resolution
 return 'biz-123';
 }

 protected function jsonInput(): array
 {
 $raw = file_get_contents('php://input');
 return $raw ? (json_decode($raw, true) ?? []) : [];
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