<?php

declare(strict_types=1);

namespace App\Controllers\Menu;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';

use App\Core\Controller;
use App\Core\DependencyFactory;

/**
 * Menu - Item Management
 * Extracted from MenuController (refactored Q4 2026)
 *
 * Handles: CRUD for menu items, stock, availability, AI image extraction
 */
class MenuItemController extends Controller
{
 /**
 * GET /menu/items
 */
 public function index(): void
 {
 $menuItemService = DependencyFactory::getMenuItemService();
 $items = $menuItemService->getAllMenuItems();
 $this->jsonResponse(['items' => $items]);
 }

 /**
 * POST /menu/items
 */
 public function add(): void
 {
 $this->requireAdmin();
 $input = $this->jsonInput();

 $menuItemService = DependencyFactory::getMenuItemService();
 $result = $menuItemService->createMenuItem([
 'name' => $input['name'] ?? '',
 'category_id' => $input['category_id'] ?? null,
 'price' => (float)($input['price'] ?? 0),
 'description' => $input['description'] ?? '',
 'production_point' => $input['production_point'] ?? 'KITCHEN',
 'is_available' => $input['is_available'] ?? true,
 'track_stock' => $input['track_stock'] ?? false,
 'stock' => (int)($input['stock'] ?? 0)
 ]);

 if (!$result) {
 $this->jsonError('Failed to create menu item', 500);
 return;
 }

 $this->jsonResponse([
 'status' => 'success',
 'menu_item_id' => $result['menu_item_id']
 ], 201);
 }

 /**
 * PUT /menu/items/{id}
 */
 public function edit(): void
 {
 $this->requireAdmin();
 $itemId = $_GET['id'] ?? null;
 if (!$itemId) {
 $this->jsonError('Missing item_id', 400);
 return;
 }

 $input = $this->jsonInput();
 $menuItemService = DependencyFactory::getMenuItemService();
 $result = $menuItemService->updateMenuItem($itemId, $input);

 if (!$result) {
 $this->jsonError('Failed to update item', 500);
 return;
 }

 $this->jsonResponse(['status' => 'success']);
 }

 /**
 * DELETE /menu/items/{id}
 */
 public function delete(): void
 {
 $this->requireAdmin();
 $itemId = $_GET['id'] ?? null;
 if (!$itemId) {
 $this->jsonError('Missing item_id', 400);
 return;
 }

 $menuItemService = DependencyFactory::getMenuItemService();
 $result = $menuItemService->deleteMenuItem($itemId);

 if (!$result) {
 $this->jsonError('Failed to delete item', 500);
 return;
 }

 $this->jsonResponse(['status' => 'success']);
 }

 /**
 * PATCH /menu/items/{id}/availability
 */
 public function updateAvailability(): void
 {
 $itemId = $_GET['id'] ?? null;
 $input = $this->jsonInput();

 if (!$itemId || !isset($input['is_available'])) {
 $this->jsonError('Missing parameters', 400);
 return;
 }

 $menuItemService = DependencyFactory::getMenuItemService();
 $result = $menuItemService->setAvailability($itemId, (bool)$input['is_available']);

 if (!$result) {
 $this->jsonError('Failed to update availability', 500);
 return;
 }

 $this->jsonResponse(['status' => 'success']);
 }

 /**
 * POST /menu/items/extract-from-image
 * AI-powered menu extraction from photo
 */
 public function extractFromImage(): void
 {
 $this->requireAdmin();
 $input = $this->jsonInput();
 $imageUrl = $input['image_url'] ?? null;

 if (!$imageUrl) {
 $this->jsonError('Missing image_url', 400);
 return;
 }

 $geminiService = DependencyFactory::getGeminiService();
 $extracted = $geminiService->extractMenuFromImage($imageUrl);

 if (!$extracted) {
 $this->jsonError('AI extraction failed', 500);
 return;
 }

 $this->jsonResponse([
 'status' => 'success',
 'items' => $extracted['items'] ?? []
 ]);
 }

 /**
 * POST /menu/items/bulk-add
 */
 public function bulkAddFromExtraction(): void
 {
 $this->requireAdmin();
 $input = $this->jsonInput();
 $items = $input['items'] ?? [];

 if (empty($items)) {
 $this->jsonError('No items provided', 400);
 return;
 }

 $menuItemService = DependencyFactory::getMenuItemService();
 $created = 0;
 $errors = [];

 foreach ($items as $item) {
 $result = $menuItemService->createMenuItem($item);
 if ($result) {
 $created++;
 } else {
 $errors[] = $item['name'] ?? 'unknown';
 }
 }

 $this->jsonResponse([
 'status' => 'partial',
 'created' => $created,
 'failed' => count($errors),
 'errors' => $errors
 ]);
 }

 protected function requireAdmin(): void
 {
 if (!$this->isAdmin()) {
 $this->jsonError('Unauthorized', 403);
 }
 }

 protected function isAdmin(): bool
 {
 return ($_SESSION['role'] ?? '') === 'admin';
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