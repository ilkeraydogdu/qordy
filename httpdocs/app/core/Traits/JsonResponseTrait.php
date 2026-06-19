<?php
/**
 * JsonResponse Trait (Q4 2026)
 *
 * DRY helper: json response, error, input parsing.
 * Use this in any controller that needs standardized JSON API responses.
 *
 * Usage:
 * class MyController extends Controller {
 * use JsonResponseTrait;
 * }
 */

declare(strict_types=1);

namespace App\Core\Traits;

trait JsonResponseTrait
{
 /**
 * Send JSON response and exit
 */
 protected function jsonResponse($data, int $statusCode = 200, array $headers = []): void
 {
 if (!headers_sent()) {
 http_response_code($statusCode);
 header('Content-Type: application/json; charset=utf-8');
 foreach ($headers as $k => $v) {
 header("$k: $v");
 }
 }
 echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
 exit;
 }

 /**
 * Send JSON error and exit
 */
 protected function jsonError(string $message, int $statusCode = 400, array $errors = []): void
 {
 $this->jsonResponse([
 'success' => false,
 'error' => $message,
 'errors' => $errors,
 ], $statusCode);
 }

 /**
 * Send JSON success and exit
 */
 protected function jsonSuccess($data = null, string $message = 'OK'): void
 {
 $this->jsonResponse([
 'success' => true,
 'message' => $message,
 'data' => $data,
 ]);
 }

 /**
 * Parse JSON input from request body
 */
 protected function jsonInput(): array
 {
 $raw = file_get_contents('php://input');
 if (empty($raw)) {
 return [];
 }
 $data = json_decode($raw, true);
 return is_array($data) ? $data : [];
 }

 /**
 * Require admin permission
 */
 protected function requireAdmin(string $permission = 'admin.access'): void
 {
 if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
 $this->jsonError('Unauthorized', 401);
 }
 if (!$this->hasPermission($permission)) {
 $this->jsonError('Forbidden', 403);
 }
 }

 /**
 * Get business ID from session
 */
 protected function getBusinessId(): string
 {
 return (string)($_SESSION['business_id'] ?? '');
 }

 /**
 * Get user ID from session
 */
 protected function getUserId(): ?int
 {
 return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
 }
}
