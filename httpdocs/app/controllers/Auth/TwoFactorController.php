<?php
/**
 * Auth — TwoFactor Controller
 *
 * Owns the 2FA challenge surface used by both the staff PIN channel
 * (SessionController) and the manager password channel
 * (RegistrationController). Methods are kept short and delegate the
 * actual session finalisation to the originating controller.
 *
 * Methods moved from AuthController.php:
 * show2FAVerify, switch2FAMethod, resend2FACode, verify2FA
 */
declare(strict_types=1);

namespace App\Controllers\Auth;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/AuthBaseController.php';

use App\Core\SessionManager;
use App\Core\TenantContext;

class TwoFactorController extends AuthBaseController
{
 /**
 * Show 2FA verification page
 * GET: auth/2fa/verify
 */
 public function show2FAVerify()
 {
 if (!isset($_SESSION['2fa_pending']) || !$_SESSION['2fa_pending']) {
 $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
 $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
 header('Location: ' . $protocol . '://' . $currentHost . '/login');
 exit;
 }

 $this->view('auth/2fa_verify', [
 'methods' => $_SESSION['2fa_methods'] ?? [],
 'currentMethod' => $_SESSION['2fa_method'] ?? 'email',
 ]);
 }

 /**
 * Let the user pick another enrolled (and admin-allowed) 2FA method
 * while sitting on the challenge page. AJAX → triggers a fresh code
 * delivery where applicable and reuses the same challenge page.
 * POST: auth/2fa/switch
 */
 public function switch2FAMethod()
 {
 header('Content-Type: application/json');
 if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
 echo json_encode(['success' => false, 'message' => 'Method not allowed']);
 return;
 }
 if (!isset($_SESSION['2fa_pending']) || !$_SESSION['2fa_pending']) {
 echo json_encode(['success' => false, 'message' => 'Doğrulama oturumu bulunamadı']);
 return;
 }
 $data = \App\Core\RequestParser::getRequestData();
 $target = strtolower(trim((string)($data['method'] ?? '')));
 $available = $_SESSION['2fa_methods'] ?? [];
 if (!in_array($target, $available, true)) {
 echo json_encode(['success' => false, 'message' => 'Bu yöntem hesabınız için uygun değil']);
 return;
 }
 $userId = $_SESSION['2fa_user_id'] ?? '';
 $_SESSION['2fa_method'] = $target;
 if ($target === 'totp') {
 echo json_encode(['success' => true, 'method' => $target, 'sent' => false]);
 return;
 }

 try {
 $svc = \App\Core\DependencyFactory::getTwoFactorAuthService();
 $res = ($target === 'whatsapp')
 ? $svc->sendWhatsAppCode($userId)
 : $svc->sendVerificationCode($userId, $target);
 echo json_encode([
 'success' => (bool)($res['success'] ?? false),
 'method' => $target,
 'sent' => (bool)($res['success'] ?? false),
 'message' => $res['message'] ?? '',
 ]);
 } catch (\Throwable $e) {
 \App\Core\Logger::error('2FA switch failed: ' . $e->getMessage());
 echo json_encode(['success' => false, 'message' => 'Kod gönderilemedi']);
 }
 }

 /**
 * Resend the code for the currently selected 2FA method. TOTP has
 * no resend (client-generated). POST: auth/2fa/resend
 */
 public function resend2FACode()
 {
 header('Content-Type: application/json');
 if (!isset($_SESSION['2fa_pending']) || !$_SESSION['2fa_pending']) {
 echo json_encode(['success' => false, 'message' => 'Doğrulama oturumu bulunamadı']);
 return;
 }
 $method = $_SESSION['2fa_method'] ?? 'email';
 $userId = $_SESSION['2fa_user_id'] ?? '';
 if ($method === 'totp') {
 echo json_encode(['success' => false, 'message' => 'Authenticator uygulaması için kod sunucudan gönderilmez']);
 return;
 }
 try {
 $svc = \App\Core\DependencyFactory::getTwoFactorAuthService();
 $res = ($method === 'whatsapp')
 ? $svc->sendWhatsAppCode($userId)
 : $svc->sendVerificationCode($userId, $method);
 echo json_encode([
 'success' => (bool)($res['success'] ?? false),
 'message' => $res['message'] ?? '',
 ]);
 } catch (\Throwable $e) {
 \App\Core\Logger::error('2FA resend failed: ' . $e->getMessage());
 echo json_encode(['success' => false, 'message' => 'Kod gönderilemedi']);
 }
 }

 /**
 * Verify 2FA code
 * POST: auth/2fa/verify
 */
 public function verify2FA()
 {
 if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
 $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
 $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
 header('Location: ' . $protocol . '://' . $currentHost . '/login');
 exit;
 }

 if (!isset($_SESSION['2fa_pending']) || !$_SESSION['2fa_pending']) {
 $this->toastNotificationService->setFlash('error', 'notifications.error.not_found');
 session_write_close();
 $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
 $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
 header('Location: ' . $protocol . '://' . $currentHost . '/login');
 exit;
 }

 $requestData = \App\Core\RequestParser::getRequestData();
 $code = trim($requestData['code'] ?? '');
 $userId = $_SESSION['2fa_user_id'] ?? '';
 $method = $_SESSION['2fa_method'] ?? 'email';
 $tableId = $_SESSION['2fa_table_id'] ?? '';

 if (empty($code) || empty($userId)) {
 $this->toastNotificationService->setFlash('error', 'notifications.warning.missing_fields');
 session_write_close();
 $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
 $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
 header('Location: ' . $protocol . '://' . $currentHost . '/auth/2fa/verify');
 exit;
 }

 try {
 require_once __DIR__ . '/../../core/DependencyFactory.php';
 $twoFactorAuthService = \App\Core\DependencyFactory::getTwoFactorAuthService();

 $result = $twoFactorAuthService->verifyCode($userId, $code, $method);

 if ($result['success']) {
 $loginType = $_SESSION['2fa_login_type'] ?? 'staff_pin';
 $managerPayload = $_SESSION['2fa_manager_payload'] ?? null;

 unset($_SESSION['2fa_pending']);
 unset($_SESSION['2fa_user_id']);
 unset($_SESSION['2fa_method']);
 unset($_SESSION['2fa_methods']);
 unset($_SESSION['2fa_table_id']);
 unset($_SESSION['2fa_login_type']);
 unset($_SESSION['2fa_manager_payload']);

 if ($loginType === 'manager_password' && is_array($managerPayload)) {
 return $this->completeManagerLoginAfter2FA($managerPayload);
 }

 $user = $this->authService->authenticateWithPin($_SESSION['temp_pin'] ?? '');
 unset($_SESSION['temp_pin']);

 if ($user) {
 $requiresPasswordChange = $user['requires_password_change'] ?? false;
 if ($requiresPasswordChange) {
 SessionManager::set('force_password_change', true);

 $role = $_SESSION['role'] ?? '';
 $normalizedRole = strtoupper(str_replace('ROLE_', '', trim($role)));

 $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
 $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
 if ($normalizedRole === 'SUPER_ADMIN' || $normalizedRole === 'QODMIN') {
 $redirectUrl = $protocol . '://' . $currentHost . '/qodmin/profile?change_password=1';
 } else {
 $redirectUrl = $protocol . '://' . $currentHost . '/business/account?change_password=1';
 }

 $this->toastNotificationService->setFlash('warning', 'İlk girişinizde şifrenizi değiştirmeniz zorunludur.');
 session_write_close();
 header('Location: ' . $redirectUrl);
 exit;
 }

 if (!empty($tableId)) {
 SessionManager::set('table_id', $tableId);
 }

 $role = $_SESSION['role'] ?? '';
 $redirectUrl = $this->getRedirectUrlByRole($role, $tableId);

 $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
 $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
 header('Location: ' . $protocol . '://' . $currentHost . $redirectUrl);
 exit;
 } else {
 $this->toastNotificationService->setFlash('error', 'notifications.error.create_failed');
 session_write_close();
 $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
 $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
 header('Location: ' . $protocol . '://' . $currentHost . '/login');
 exit;
 }
 } else {
 $this->toastNotificationService->setFlash('error', 'notifications.error.unauthorized');
 session_write_close();
 $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
 $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
 header('Location: ' . $protocol . '://' . $currentHost . '/auth/2fa/verify');
 exit;
 }
 } catch (\Exception $e) {
 \App\Core\Logger::error('2FA verification error: ' . $e->getMessage());
 $this->toastNotificationService->setFlash('error', 'notifications.error.generic_error');
 session_write_close();
 $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
 $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
 header('Location: ' . $protocol . '://' . $currentHost . '/auth/2fa/verify');
 exit;
 }
 }

 /**
 * Finalise a manager (email + password) login after the 2FA code
 * has been verified. Mirrors the session set-up and redirect that
 * publicLogin() would have done inline, without re-checking the
 * password.
 */
 private function completeManagerLoginAfter2FA(array $payload): void
 {
 $customerId = $payload['customer_id'] ?? '';
 $userId = $payload['user_id'] ?? $customerId;
 $email = $payload['email'] ?? '';
 $rememberMe = !empty($payload['remember_me']);
 $isDemo = !empty($payload['is_demo']);

 SessionManager::ensureSession();
 $roleId = null;
 try {
 $roleService = \App\Core\DependencyFactory::getRoleService();
 $roleData = $roleService->getByRoleCode('BUSINESS_MANAGER');
 $roleId = $roleData['role_id'] ?? null;
 } catch (\Throwable $e) {
 // non-fatal, keep roleId null
 }

 SessionManager::regenerateId();
 SessionManager::set('user_id', $userId);
 SessionManager::set('username', $email);
 SessionManager::set('role', 'BUSINESS_MANAGER');
 SessionManager::set('role_id', $roleId);
 SessionManager::set('logged_in', true);
 SessionManager::set('login_time', time());
 SessionManager::setTenantSession($customerId);
 SessionManager::set('is_demo', $isDemo);

 if ($rememberMe && $email !== '') {
 $cookieExpiry = time() + (30 * 24 * 60 * 60);
 $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
 $signedValue = $this->encodeRememberEmail($email);
 if (PHP_VERSION_ID >= 70300) {
 setcookie('remember_email', $signedValue, [
 'expires' => $cookieExpiry,
 'path' => '/',
 'secure' => $isSecure,
 'httponly' => true,
 'samesite' => 'Strict',
 ]);
 } else {
 setcookie('remember_email', $signedValue, $cookieExpiry, '/', '', $isSecure, true);
 }
 }

 try {
 \App\Core\DependencyFactory::getActivityLogService()->log(
 'login', $customerId, $userId, null, null,
 ['channel' => 'business_owner', '2fa' => true]
 );
 } catch (\Throwable $e) {
 \App\Core\Logger::warning('TwoFactorController: 2FA activity log failed', [
 'customer_id' => $customerId,
 'user_id' => $userId,
 'error' => $e->getMessage(),
 ]);
 }

 $this->toastNotificationService->setFlash('success', 'Giriş başarılı! Hoş geldiniz.');
 session_write_close();
 header('Location: ' . BASE_URL . '/business/dashboard');
 exit;
 }
}
