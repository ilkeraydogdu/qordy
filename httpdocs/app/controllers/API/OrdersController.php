<?php

declare(strict_types=1);

namespace App\Controllers\API;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';

use App\Core\Controller;
use App\Core\DependencyFactory;
use App\Models\OrderStatus;

/**
 * Public API - Orders (customer-facing)
 * Extracted from APIController (refactored Q4 2026)
 */
class OrdersController extends Controller
{
 /**
 * GET /api/menu
 * Public menu (no auth)
 */
 public function menu(): void
 {
 $businessId = $this->requireBusinessId();
 $menuService = DependencyFactory::getMenuService();
 $menu = $menuService->getPublicMenu($businessId);
 $this->jsonResponse(['menu' => $menu]);
 }

 /**
 * GET /api/orders
 */
 public function index(): void
 {
 $businessId = $this->requireBusinessId();
 $status = $_GET['status'] ?? null;
 $orderService = DependencyFactory::getOrderService();
 $orders = $orderService->getOrdersForApi($businessId, $status);
 $this->jsonResponse(['orders' => $orders]);
 }

 /**
 * POST /api/orders
 * Place a new order
 */
 public function place(): void
 {
 $businessId = $this->requireBusinessId();
 $input = $this->jsonInput();

 $orderService = DependencyFactory::getOrderService();
 $result = $orderService->placeOrder([
 'business_id' => $businessId,
 'table_id' => $input['table_id'] ?? null,
 'customer_session_id' => $input['session_id'] ?? null,
 'items' => $input['items'] ?? [],
 'notes' => $input['notes'] ?? ''
 ]);

 if (!$result) {
 $this->jsonError('Failed to place order', 500);
 return;
 }

 $this->jsonResponse([
 'status' => 'success',
 'order_id' => $result['order_id'],
 'status_code' => OrderStatus::PENDING
 ], 201);
 }

 /**
 * PATCH /api/orders/{id}/status
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

 if (!OrderStatus::isValid($newStatus)) {
 $this->jsonError('Invalid status', 400);
 return;
 }

 $orderService = DependencyFactory::getOrderService();
 $result = $orderService->updateOrderStatus($orderId, $newStatus);

 if (!$result) {
 $this->jsonError('Failed to update', 500);
 return;
 }

 // Invalidate related caches
 \App\Core\CacheManager::invalidateOrders();

 $this->jsonResponse(['status' => 'success']);
 }

 /**
 * POST /api/orders/{id}/call-waiter
 */
 public function callWaiter(): void
 {
 $orderId = $_GET['id'] ?? null;
 if (!$orderId) {
 $this->jsonError('Missing order_id', 400);
 return;
 }

 $orderService = DependencyFactory::getOrderService();
 $result = $orderService->callWaiter($orderId);

 if (!$result) {
 $this->jsonError('Failed to call waiter', 500);
 return;
 }

 $this->jsonResponse(['status' => 'success']);
 }

 protected function requireBusinessId(): string
 {
 $businessId = $_GET['business_id'] ?? $_POST['business_id'] ?? null;
 if (!$businessId) {
 $this->jsonError('Missing business_id', 400);
 }
 return (string)$businessId;
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