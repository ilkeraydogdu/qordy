<?php
/**
 * Auth — Shared Base Controller
 *
 * Hosts the construction-time bootstrap and the cross-cutting helpers
 * that every concrete auth controller (Session, TwoFactor, Registration,
 * QordyAdminLogin) needs. Pulled out of the 3020-line god class so the
 * concrete controllers stay focused on their route surface.
 */
declare(strict_types=1);

namespace App\Controllers\Auth;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../services/AuthenticationService.php';

if (!class_exists('App\Core\Helpers\ConstantsHelper')) {
 require_once __DIR__ . '/../../core/Helpers/ConstantsHelper.php';
}

use App\Core\Controller;
use App\Core\SessionManager;

class AuthBaseController extends Controller
{
 /**
 * Resolved authentication service. Sub-classes reach for this when
 * they need to call `authenticateWithPin` / `logout` / etc.
 */
 protected $authService;

 public function __construct()
 {
 parent::__construct(); // Base Controller wires auth, firewall, helpers
 $this->authService = \App\Core\DependencyFactory::getAuthenticationService();
 }

 /**
 * "Beni hatırla" cookie'si için HMAC imzalı encoder.
 */
 private function rememberCookieSecret(): string
 {
 $candidates = [
 getenv('ENCRYPTION_KEY'),
 getenv('APP_KEY'),
 getenv('SECRET_KEY'),
 defined('APP_KEY') ? constant('APP_KEY') : null,
 defined('ENCRYPTION_KEY') ? constant('ENCRYPTION_KEY') : null,
 ];
 foreach ($candidates as $candidate) {
 if (is_string($candidate) && strlen($candidate) >= 16) {
 return $candidate;
 }
 }
 return 'qordy-remember-cookie-fallback-key-please-rotate';
 }

 protected function encodeRememberEmail(string $email): string
 {
 $email = trim($email);
 if ($email === '') {
 return '';
 }
 $payload = base64_encode($email);
 $signature = hash_hmac('sha256', 'v2|' . $payload, $this->rememberCookieSecret());
 return 'v2.' . $payload . '.' . $signature;
 }

 protected function decodeRememberEmail(?string $cookieValue): string
 {
 if (!is_string($cookieValue) || $cookieValue === '') {
 return '';
 }
 if (strpos($cookieValue, 'v2.') === 0) {
 $parts = explode('.', $cookieValue, 3);
 if (count($parts) !== 3) {
 return '';
 }
 [, $payload, $signature] = $parts;
 $expected = hash_hmac('sha256', 'v2|' . $payload, $this->rememberCookieSecret());
 if (!hash_equals($expected, $signature)) {
 return '';
 }
 $decoded = base64_decode($payload, true);
 return is_string($decoded) ? $decoded : '';
 }
 $legacy = base64_decode($cookieValue, true);
 return is_string($legacy) && filter_var($legacy, FILTER_VALIDATE_EMAIL) ? $legacy : '';
 }

 protected function getClientIP(): string
 {
 require_once __DIR__ . '/../../core/Helpers/IPHelper.php';
 return \App\Core\Helpers\IPHelper::getClientIP();
 }

 protected function redirectToLoginWithError(string $errCode, ?string $flashMessage = null, array $extraParams = []): void
 {
 if ($flashMessage !== null && isset($this->toastNotificationService)) {
 try {
 $this->toastNotificationService->setFlash('error', $flashMessage);
 } catch (\Throwable $e) {
 }
 }

 if (function_exists('session_write_close') && session_status() === PHP_SESSION_ACTIVE) {
 @session_write_close();
 }

 $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
 $protocol = getProtocol();

 $query = array_merge(['err' => $errCode], $extraParams);
 $url = $protocol . '://' . $currentHost . '/login?' . http_build_query($query);

 header('Location: ' . $url);
 exit;
 }

 protected function getSubdomain(): ?string
 {
 $host = $_SERVER['HTTP_HOST'] ?? '';
 $parts = explode('.', $host);

 if (count($parts) === 2 && $parts[1] === 'com') {
 return null;
 }
 if (count($parts) === 3 && $parts[0] === 'www' && $parts[1] === 'qordy') {
 return null;
 }
 if (count($parts) >= 3) {
 $subdomain = $parts[0];
 if ($subdomain !== 'www' && $subdomain !== 'qordy') {
 return $subdomain;
 }
 }
 return null;
 }

 protected function getRedirectUrlByRole(string $roleCode, ?string $tableId = null): string
 {
 $normalizedRole = strtoupper(str_replace('ROLE_', '', trim($roleCode)));

 $constantsHelperClass = 'App\Core\Helpers\ConstantsHelper';
 if (!class_exists($constantsHelperClass)) {
 $helperPath = __DIR__ . '/../../core/Helpers/ConstantsHelper.php';
 if (file_exists($helperPath)) {
 require_once $helperPath;
 } else {
 \App\Core\Logger::error("ConstantsHelper file not found at: " . $helperPath);
 return $this->getRedirectUrlByRoleFallback($normalizedRole, $tableId);
 }
 }

 if (!class_exists($constantsHelperClass) || !method_exists($constantsHelperClass, 'getRole')) {
 \App\Core\Logger::error("ConstantsHelper::getRole() method not available");
 return $this->getRedirectUrlByRoleFallback($normalizedRole, $tableId);
 }

 try {
 require_once __DIR__ . '/../../core/Validators/RoleValidator.php';

 if (!\App\Core\Validators\RoleValidator::isValid($roleCode)) {
 \App\Core\Logger::warning("Invalid role detected in getRedirectUrlByRole", [
 'role' => $roleCode,
 'normalized_role' => \App\Core\Validators\RoleValidator::normalize($roleCode),
 ]);
 return '/login';
 }

 $roleRedirects = [
 'SUPER_ADMIN' => '/qodmin/dashboard',
 'QODMIN' => '/qodmin/dashboard',
 $constantsHelperClass::getRole('MANAGER') => '/business/dashboard',
 'BUSINESS_MANAGER' => '/business/dashboard',
 'ADMIN' => '/business/dashboard',
 'ADMINISTRATOR' => '/business/dashboard',
 $constantsHelperClass::getRole('WAITER') => '/waiter/dashboard',
 'GARSON' => '/waiter/dashboard',
 $constantsHelperClass::getRole('KITCHEN') => '/kitchen/dashboard',
 'MUTFAK' => '/kitchen/dashboard',
 'CHEF' => '/kitchen/dashboard',
 $constantsHelperClass::getRole('CASHIER') => '/pos',
 'KASIYER' => '/pos',
 'STOCK_MANAGER' => '/business/stock-dashboard',
 'STOK_YONETICISI' => '/business/stock-dashboard',
 'HR_MANAGER' => '/business/hr-dashboard',
 'IK_YONETICISI' => '/business/hr-dashboard',
 'CUSTOMER' => $tableId ? '/t/' . $tableId : '/menu',
 ];

 $resolved = $roleRedirects[$normalizedRole] ?? '/login';
 return $this->redirectForStaffChannel($normalizedRole, $resolved);
 } catch (\Exception $e) {
 \App\Core\Logger::error("Error in getRedirectUrlByRole: " . $e->getMessage());
 return $this->getRedirectUrlByRoleFallback($normalizedRole, $tableId);
 }
 }

 protected function redirectForStaffChannel(string $normalizedRole, string $target): string
 {
 if ($target !== '/business/dashboard') {
 return $target;
 }
 $channel = (string)($_SESSION['login_channel'] ?? '');
 if ($channel !== 'staff_pin') {
 return $target;
 }
 $userId = (string)($_SESSION['user_id'] ?? '');
 try {
 if ($userId !== '') {
 $userService = \App\Core\DependencyFactory::getUserService();
 $userRow = method_exists($userService, 'findByUserId')
 ? $userService->findByUserId($userId)
 : null;
 if (is_array($userRow) && !empty($userRow['preparation_screen_id'])) {
 try {
 $prepService = \App\Core\DependencyFactory::getPreparationScreenService();
 $screen = $prepService->getScreenById($userRow['preparation_screen_id']);
 if (is_array($screen) && !empty($screen['slug'])) {
 return '/preparation-screen/' . $screen['slug'];
 }
 } catch (\Throwable $e) {
 }
 }
 }
 } catch (\Throwable $e) {
 }
 return '/pos';
 }

 protected function getRedirectUrlByRoleFallback(string $normalizedRole, ?string $tableId = null): string
 {
  $roleRedirects = [
  'SUPER_ADMIN' => '/qodmin/dashboard',
  'QODMIN' => '/qodmin/dashboard',
  'MANAGER' => '/business/dashboard',
  'BUSINESS_MANAGER' => '/business/dashboard',
  'ADMIN' => '/business/dashboard',
  'ADMINISTRATOR' => '/business/dashboard',
  'WAITER' => '/waiter/dashboard',
  'GARSON' => '/waiter/dashboard',
  'KITCHEN' => '/kitchen/dashboard',
  'MUTFAK' => '/kitchen/dashboard',
  'CHEF' => '/kitchen/dashboard',
  'CASHIER' => '/pos',
  'KASIYER' => '/pos',
  'STOCK_MANAGER' => '/business/stock-dashboard',
  'STOK_YONETICISI' => '/business/stock-dashboard',
  'HR_MANAGER' => '/business/hr-dashboard',
  'IK_YONETICISI' => '/business/hr-dashboard',
  'CUSTOMER' => $tableId ? '/t/' . $tableId : '/menu',
  ];

 $resolved = $roleRedirects[$normalizedRole] ?? '/login';
 return $this->redirectForStaffChannel($normalizedRole, $resolved);
 }

 protected function hasSuperAdminAssignedBusiness(string $customerId): bool
 {
 try {
 $subscriptionService = \App\Core\DependencyFactory::getSubscriptionService();
 $subscription = $subscriptionService->getCustomerSubscription($customerId);
 return !empty($subscription);
 } catch (\Exception $e) {
 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::warning("AuthController: Error checking super admin assignment", [
 'error' => $e->getMessage(),
 'customer_id' => $customerId,
 ]);
 }
 return false;
 }
 }

 protected function getIPByPinFromRedis(string $pin): ?string
 {
 if (!extension_loaded('redis')) {
 return null;
 }

 try {
 $cacheConfig = require __DIR__ . '/../../config/cache.php';
 if ($cacheConfig['driver'] !== 'redis') {
 return null;
 }

 $redis = new \Redis();
 $host = $cacheConfig['redis']['host'] ?? '127.0.0.1';
 $port = $cacheConfig['redis']['port'] ?? 6379;
 $password = $cacheConfig['redis']['password'] ?? null;
 $database = isset($_ENV['REDIS_RATELIMIT_DATABASE']) ? (int)$_ENV['REDIS_RATELIMIT_DATABASE'] : 2;
 $timeout = $cacheConfig['redis']['timeout'] ?? 2.5;

 if (!$redis->connect($host, $port, $timeout)) {
 return null;
 }

 if ($password !== null) {
 if (!$redis->auth($password)) {
 return null;
 }
 }

 $redis->select($database);

 $key = 'pin_session:' . $pin;
 $ip = $redis->get($key);
 return $ip ? (string)$ip : null;
 } catch (\Exception $e) {
 return null;
 }
 }

 protected function clearPinIPMappingFromRedis(string $pin): void
 {
 if (!extension_loaded('redis')) {
 return;
 }

 try {
 $cacheConfig = require __DIR__ . '/../../config/cache.php';
 if ($cacheConfig['driver'] !== 'redis') {
 return;
 }

 $redis = new \Redis();
 $host = $cacheConfig['redis']['host'] ?? '127.0.0.1';
 $port = $cacheConfig['redis']['port'] ?? 6379;
 $password = $cacheConfig['redis']['password'] ?? null;
 $database = isset($_ENV['REDIS_RATELIMIT_DATABASE']) ? (int)$_ENV['REDIS_RATELIMIT_DATABASE'] : 2;
 $timeout = $cacheConfig['redis']['timeout'] ?? 2.5;

 if (!$redis->connect($host, $port, $timeout)) {
 return;
 }

 if ($password !== null) {
 if (!$redis->auth($password)) {
 return;
 }
 }

 $redis->select($database);

 $key = 'pin_session:' . $pin;
 $redis->del($key);
 } catch (\Exception $e) {
 }
 }
}
