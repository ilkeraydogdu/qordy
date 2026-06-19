<?php

declare(strict_types=1);

namespace App\Controllers\API\Mobile;

require_once __DIR__ . '/../../../core/Controller.php';
require_once __DIR__ . '/../../../core/DependencyFactory.php';

use App\Core\Controller;
use App\Core\DependencyFactory;

/**
 * Mobile API - Authentication & 2FA
 * Extracted from MobileAPIController (refactored Q4 2026)
 *
 * Handles: login, logout, refresh, 2FA challenges, TOTP
 */
class MobileAuthController extends Controller
{
 /**
 * POST /api/mobile/auth/login
 */
 public function login(): void
 {
 $input = $this->jsonInput();
 $email = $input['email'] ?? null;
 $password = $input['password'] ?? null;

 if (!$email || !$password) {
 $this->jsonError('Email and password required', 400);
 return;
 }

 $authService = DependencyFactory::getAuthenticationService();
 $result = $authService->authenticate($email, $password);

 if (!$result) {
 $this->jsonError('Invalid credentials', 401);
 return;
 }

 // Check if 2FA required
 if ($result['requires_2fa'] ?? false) {
 $this->jsonResponse([
 'status' => '2fa_required',
 'challenge_id' => $result['challenge_id']
 ], 200);
 return;
 }

 $this->jsonResponse([
 'status' => 'success',
 'token' => $result['token'],
 'user' => $result['user']
 ], 200);
 }

 /**
 * POST /api/mobile/auth/verify-2fa
 */
 public function verify2FA(): void
 {
 $input = $this->jsonInput();
 $challengeId = $input['challenge_id'] ?? null;
 $code = $input['code'] ?? null;

 if (!$challengeId || !$code) {
 $this->jsonError('Missing challenge_id or code', 400);
 return;
 }

 $authService = DependencyFactory::getAuthenticationService();
 $result = $authService->verify2FACode($challengeId, $code);

 if (!$result) {
 $this->jsonError('Invalid 2FA code', 401);
 return;
 }

 $this->jsonResponse([
 'status' => 'success',
 'token' => $result['token']
 ], 200);
 }

 /**
 * POST /api/mobile/auth/refresh
 */
 public function refresh(): void
 {
 $refreshToken = $this->getBearerToken();
 if (!$refreshToken) {
 $this->jsonError('Missing refresh token', 401);
 return;
 }

 $authService = DependencyFactory::getAuthenticationService();
 $result = $authService->refreshToken($refreshToken);

 if (!$result) {
 $this->jsonError('Invalid refresh token', 401);
 return;
 }

 $this->jsonResponse([
 'status' => 'success',
 'token' => $result['token']
 ], 200);
 }

 /**
 * POST /api/mobile/auth/logout
 */
 public function logout(): void
 {
 $token = $this->getBearerToken();
 if ($token) {
 $authService = DependencyFactory::getAuthenticationService();
 $authService->revokeToken($token);
 }
 $this->jsonResponse(['status' => 'success'], 200);
 }

 /**
 * Get JSON input
 */
 protected function jsonInput(): array
 {
 $raw = file_get_contents('php://input');
 return $raw ? (json_decode($raw, true) ?? []) : [];
 }

 /**
 * Get bearer token from Authorization header
 */
 protected function getBearerToken(): ?string
 {
 $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
 if (preg_match('/Bearer\s+(.+)$/i', $auth, $matches)) {
 return $matches[1];
 }
 return null;
 }

 /**
 * JSON response
 */
 protected function jsonResponse($data, int $status = 200): void
 {
 http_response_code($status);
 header('Content-Type: application/json');
 echo json_encode($data);
 exit;
 }

 /**
 * JSON error
 */
 protected function jsonError(string $message, int $status = 400): void
 {
 $this->jsonResponse(['error' => $message], $status);
 }
}