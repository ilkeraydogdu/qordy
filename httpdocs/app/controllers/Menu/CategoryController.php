<?php

declare(strict_types=1);

namespace App\Controllers\Menu;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';

use App\Core\Controller;
use App\Core\DependencyFactory;
use App\Models\OrderStatus;

/**
 * Menu - Category Management
 * Extracted from MenuController (refactored Q4 2026)
 *
 * Handles: add/edit/delete category, category variations
 */
class CategoryController extends Controller
{
 /**
 * GET /menu/categories
 */
 public function index(): void
 {
 $categoryService = DependencyFactory::getCategoryService();
 $categories = $categoryService->getAllCategories();
 $this->jsonResponse(['categories' => $categories]);
 }

 /**
 * POST /menu/categories
 */
 public function add(): void
 {
 $this->requireAdmin();
 $input = $this->jsonInput();

 $categoryService = DependencyFactory::getCategoryService();
 $result = $categoryService->createCategory([
 'name' => $input['name'] ?? '',
 'description' => $input['description'] ?? '',
 'parent_id' => $input['parent_id'] ?? null,
 'sort_order' => (int)($input['sort_order'] ?? 0)
 ]);

 if (!$result) {
 $this->jsonError('Failed to create category', 500);
 return;
 }

 $this->jsonResponse([
 'status' => 'success',
 'category_id' => $result['category_id']
 ], 201);
 }

 /**
 * PUT /menu/categories/{id}
 */
 public function edit(): void
 {
 $this->requireAdmin();
 $categoryId = $_GET['id'] ?? null;
 if (!$categoryId) {
 $this->jsonError('Missing category_id', 400);
 return;
 }

 $input = $this->jsonInput();
 $categoryService = DependencyFactory::getCategoryService();
 $result = $categoryService->updateCategory($categoryId, [
 'name' => $input['name'] ?? null,
 'description' => $input['description'] ?? null,
 'sort_order' => isset($input['sort_order']) ? (int)$input['sort_order'] : null
 ]);

 if (!$result) {
 $this->jsonError('Failed to update category', 500);
 return;
 }

 $this->jsonResponse(['status' => 'success']);
 }

 /**
 * DELETE /menu/categories/{id}
 */
 public function delete(): void
 {
 $this->requireAdmin();
 $categoryId = $_GET['id'] ?? null;
 if (!$categoryId) {
 $this->jsonError('Missing category_id', 400);
 return;
 }

 $categoryService = DependencyFactory::getCategoryService();
 $result = $categoryService->deleteCategory($categoryId);

 if (!$result) {
 $this->jsonError('Failed to delete category', 500);
 return;
 }

 $this->jsonResponse(['status' => 'success']);
 }

 /**
 * Get category variations (Turkish char normalization)
 */
 private function getCategoryVariations(string $name): array
 {
 $variations = [$name];
 $map = ['ı' => 'i', 'İ' => 'I', 'ş' => 's', 'Ş' => 'S',
 'ğ' => 'g', 'Ğ' => 'G', 'ü' => 'u', 'Ü' => 'U',
 'ö' => 'o', 'Ö' => 'O', 'ç' => 'c', 'Ç' => 'C'];
 $ascii = strtr($name, $map);
 $variations[] = $ascii;
 $variations[] = mb_strtolower($name);
 $variations[] = mb_strtoupper($name);
 return array_unique($variations);
 }

 /**
 * Find similar category by name similarity
 */
 private function findSimilarCategory(
 string $name,
 array $allCategories,
 ?string $parentId = null
 ): ?array {
 $best = null;
 $bestScore = 0;

 foreach ($allCategories as $cat) {
 if ($parentId !== null && ($cat['parent_id'] ?? null) !== $parentId) {
 continue;
 }
 $score = $this->calculateSimilarity($name, $cat['name']);
 if ($score > $bestScore && $score > 0.6) {
 $best = $cat;
 $bestScore = $score;
 }
 }

 return $best;
 }

 /**
 * Calculate Levenshtein-based similarity
 */
 private function calculateSimilarity(string $a, string $b): float
 {
 $a = mb_strtolower(trim($a));
 $b = mb_strtolower(trim($b));
 if ($a === $b) return 1.0;
 $maxLen = max(mb_strlen($a), mb_strlen($b));
 if ($maxLen === 0) return 0;
 $distance = levenshtein($a, $b);
 return 1.0 - ($distance / $maxLen);
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