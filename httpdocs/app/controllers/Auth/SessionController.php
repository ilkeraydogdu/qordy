<?php
/**
 * Auth — Session Controller
 *
 * Handles the staff PIN login flow (subdomain surfaces), the public
 * logout, the unauthorized page, the auth-check API endpoint, and the
 * CSRF token refresh used by the SPA shells.
 *
 * Methods moved from AuthController.php:
 * index, login, refreshCsrfToken, logout, unauthorized, apiCheckAuth
 */
declare(strict_types=1);

namespace App\Controllers\Auth;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/AuthBaseController.php';

use App\Core\SessionManager;
use App\Core\TenantContext;

class SessionController extends AuthBaseController
{
 public function index()
 {
 $this->login();
 }

 /**
 * Staff PIN login (subdomain surface)
 * GET -> shows auth/login view
 * POST -> runs the PIN auth pipeline
 */
 public function login()
 {
 // Ensure session is started but skip validation on login page to prevent redirect loops
 // Validation will be done after successful login
 SessionManager::ensureSession(true);

 // CRITICAL: Set tenant context from subdomain BEFORE any other operations
 // This ensures subdomain-based PIN login works correctly
 $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
 $subdomain = TenantContext::getSubdomainFromHost($host);

 if ($subdomain && !TenantContext::isSet()) {
 try {
 require_once __DIR__ . '/../../middleware/TenantMiddleware.php';
 $tenantMiddleware = new \App\Middleware\TenantMiddleware();
 $tenantMiddleware->handle();

 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::debug('Tenant context set on login page', [
 'subdomain' => $subdomain,
 'tenant_id' => TenantContext::getId(),
 ]);
 }
 } catch (\Exception $e) {
 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::error('Failed to initialize tenant context on login page', [
 'error' => $e->getMessage(),
 'subdomain' => $subdomain,
 ]);
 }
 }
 }

 // For GET requests, check if user is already logged in
 if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
 $isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
 $role = $_SESSION['role'] ?? null;
 $roleId = $_SESSION['role_id'] ?? null;

 if ($isLoggedIn) {
 if (!empty($role) || !empty($roleId)) {
 $currentRole = $role ?? '';
 $tableId = $_SESSION['table_id'] ?? null;

 require_once __DIR__ . '/../../core/Validators/RoleValidator.php';
 $isValidRole = \App\Core\Validators\RoleValidator::isValid($currentRole);

 if ($isValidRole || (!empty($roleId) && strpos($roleId, 'ROLE_') === 0)) {
 $redirectUrl = $this->getRedirectUrlByRole($currentRole, $tableId);

 if ($redirectUrl !== '/login' && strpos($redirectUrl, '/login') === false) {
 require_once __DIR__ . '/../../helpers/functions.php';
 $toastService = getToastNotificationService();
 $toastService->setFlash('info', 'auth.info.already_logged_in');
 session_write_close();

 $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
 $protocol = getProtocol();
 $fullRedirectUrl = $protocol . '://' . $currentHost . $redirectUrl;

 header('Location: ' . $fullRedirectUrl);
 exit;
 }
 }

 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::warning("Login page: Invalid role or redirect loop detected - clearing session", [
 'role' => $currentRole,
 'normalized_role' => \App\Core\Validators\RoleValidator::normalize($currentRole ?? ''),
 'is_valid_role' => $isValidRole,
 'role_id' => $roleId,
 'redirect_url' => $redirectUrl ?? 'not_called',
 ]);
 }
 $flashMessages = [];
 $flashKeys = ['error', 'success', 'warning', 'info'];
 foreach ($flashKeys as $key) {
 if (isset($_SESSION[$key])) {
 $flashMessages[$key] = $_SESSION[$key];
 }
 }

 $_SESSION = [];
 session_regenerate_id(true);
 SessionManager::resetInitialized();

 foreach ($flashMessages as $key => $value) {
 $_SESSION[$key] = $value;
 }
 } else {
 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::warning("Login page: Session has logged_in but no role - clearing session", [
 'user_id' => $_SESSION['user_id'] ?? 'unknown',
 ]);
 }
 $flashMessages = [];
 $flashKeys = ['error', 'success', 'warning', 'info'];
 foreach ($flashKeys as $key) {
 if (isset($_SESSION[$key])) {
 $flashMessages[$key] = $_SESSION[$key];
 }
 }

 $_SESSION = [];
 session_regenerate_id(true);
 SessionManager::resetInitialized();

 foreach ($flashMessages as $key => $value) {
 $_SESSION[$key] = $value;
 }
 }
 } else {
 $flashMessages = [];
 $flashKeys = ['error', 'success', 'warning', 'info'];
 foreach ($flashKeys as $key) {
 if (isset($_SESSION[$key])) {
 $flashMessages[$key] = $_SESSION[$key];
 }
 }

 $_SESSION = [];
 session_regenerate_id(true);
 SessionManager::resetInitialized();

 foreach ($flashMessages as $key => $value) {
 $_SESSION[$key] = $value;
 }
 }
 }

 if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
 $requestData = \App\Core\RequestParser::getRequestData();
 $pin = trim($requestData['pin'] ?? '');
 $tableId = trim($requestData['table_id'] ?? '');

 if (empty($pin)) {
 $this->redirectToLoginWithError('pin_required', 'Lütfen PIN kodunuzu giriniz.');
 }
 if (!preg_match('/^\d{4,8}$/', $pin)) {
 $this->redirectToLoginWithError('invalid_pin_format', 'PIN yalnızca rakamlardan oluşmalı ve 4-8 haneli olmalıdır.');
 }
 if (!empty($tableId) && !preg_match('/^[a-zA-Z0-9_-]+$/', $tableId)) {
 $this->redirectToLoginWithError('invalid_table', 'Geçersiz masa bilgisi.');
 }

 $currentIP = $this->getClientIP();
 $existingIP = $this->getIPByPinFromRedis($pin);

 $currentUserId = $_SESSION['user_id'] ?? null;
 $currentLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;

 if (!$currentLoggedIn && $existingIP) {
 $this->clearPinIPMappingFromRedis($pin);
 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::info("Cleared stale PIN mapping - user not logged in", [
 'pin_hash' => substr(hash('sha256', $pin), 0, 12),
 'existing_ip' => $existingIP,
 'current_ip' => $currentIP,
 ]);
 }
 $existingIP = null;
 }

 $normalizeIP = function($ip) {
 if (!$ip) return null;
 $ip = explode(':', $ip)[0];
 $ip = trim($ip);
 return $ip;
 };

 $normalizedExistingIP = $normalizeIP($existingIP);
 $normalizedCurrentIP = $normalizeIP($currentIP);

 if ($normalizedExistingIP && $normalizedExistingIP !== $normalizedCurrentIP && $currentLoggedIn) {
 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::warning("Login attempt blocked: PIN already active on another device", [
 'pin_hash' => substr(hash('sha256', $pin), 0, 12),
 'existing_ip' => $existingIP,
 'normalized_existing_ip' => $normalizedExistingIP,
 'current_ip' => $currentIP,
 'normalized_current_ip' => $normalizedCurrentIP,
 'current_logged_in' => $currentLoggedIn,
 ]);
 }
 $this->redirectToLoginWithError(
 'pin_already_active',
 'Bu PIN şu anda başka bir cihazda aktif. Lütfen önce diğer cihazdan çıkış yapın.'
 );
 }

 if ($normalizedExistingIP === $normalizedCurrentIP || !$normalizedExistingIP) {
 if ($normalizedExistingIP === $normalizedCurrentIP) {
 $this->clearPinIPMappingFromRedis($pin);
 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::info("Cleared PIN mapping for same IP login", [
 'pin_hash' => substr(hash('sha256', $pin), 0, 12),
 'ip' => $currentIP,
 'normalized_ip' => $normalizedCurrentIP,
 ]);
 }
 }
 }

 if ($currentLoggedIn && $currentUserId) {
 $userModel = new \App\Models\User();
if ($userByPin && $userByPin['user_id'] === $currentUserId) {
 $currentRole = $_SESSION['role'] ?? '';
 $redirectUrl = $this->getRedirectUrlByRole($currentRole, $tableId);
 $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
 $protocol = getProtocol();
 header('Location: ' . $protocol . '://' . $currentHost . $redirectUrl);
 exit;
 } else if ($userByPin) {
 $this->authService->logout();
 \App\Core\Logger::info("User logged out due to new login attempt", [
 'previous_user_id' => $currentUserId,
 'new_user_id' => $userByPin['user_id'] ?? 'unknown',
 'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
 ]);
 }
 }

 $_SESSION['temp_pin'] = $pin;

 $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
 $subdomain = TenantContext::getSubdomainFromHost($host);

 if ($subdomain && !TenantContext::isSet()) {
 try {
 require_once __DIR__ . '/../../middleware/TenantMiddleware.php';
 $tenantMiddleware = new \App\Middleware\TenantMiddleware();
 $tenantMiddleware->handle();
 } catch (\Exception $e) {
 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::error('Failed to initialize tenant context before PIN login', [
 'error' => $e->getMessage(),
 'subdomain' => $subdomain,
 ]);
 }
 }
 }

 $user = $this->authService->authenticateWithPin($pin);

 if ($user && !empty($user['user_id'])) {
 try {
 require_once __DIR__ . '/../../core/DependencyFactory.php';
 $userService = \App\Core\DependencyFactory::getUserService();
 $fullUserData = $userService->findByUserId($user['user_id']);
 if ($fullUserData) {
 $user = array_merge($user, $fullUserData);

 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::info("SessionController: Merged full user data after authentication", [
 'user_id' => $user['user_id'],
 'preparation_screen_id' => $user['preparation_screen_id'] ?? 'not_set',
 'has_preparation_screen_id' => isset($user['preparation_screen_id']),
 ]);
 }
 }
 } catch (\Exception $e) {
 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::warning("SessionController: Failed to fetch full user data immediately after auth", [
 'user_id' => $user['user_id'] ?? 'unknown',
 'error' => $e->getMessage(),
 ]);
 }
 }
 }

 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::info("SessionController: authenticateWithPin result", [
 'pin_length' => strlen($pin),
 'pin_preview' => substr($pin, 0, 2) . '**',
 'user_found' => $user !== false && !empty($user),
 'user_result_type' => gettype($user),
 'user_result_empty' => empty($user),
 'user_result_false' => ($user === false),
 'user_id' => $user['user_id'] ?? 'none',
 'user_role' => $user['role'] ?? 'none',
 'user_business_id' => $user['tenant_id'] ?? 'none',
 'preparation_screen_id' => $user['preparation_screen_id'] ?? 'not_set',
 'tenant_id' => TenantContext::getId(),
 'subdomain' => $subdomain ?? 'none',
 'host' => $_SERVER['HTTP_HOST'] ?? 'unknown',
 ]);
 }

 if ($user === false || empty($user)) {
 $tenantId = TenantContext::getId();
 $subdomain = TenantContext::getSubdomainFromHost($_SERVER['HTTP_HOST'] ?? '');

 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::error("PIN login failed - authentication returned false or empty", [
 'pin_length' => strlen($pin),
 'pin_preview' => substr($pin, 0, 2) . '**',
 'tenant_id' => $tenantId,
 'subdomain' => $subdomain ?? 'none',
 'host' => $_SERVER['HTTP_HOST'] ?? 'unknown',
 'ip' => $currentIP,
 'user_result_type' => gettype($user),
 'user_result_empty' => empty($user),
 'user_result_false' => ($user === false),
 'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
 'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
 ]);
 }

 $existingIP = $this->getIPByPinFromRedis($pin);
 if ($existingIP && $existingIP !== $currentIP) {
 $this->redirectToLoginWithError(
 'pin_in_use',
 'Bu PIN başka bir cihazda kullanılıyor. Lütfen diğer cihazdan çıkış yapın.'
 );
 }

 if ($tenantId && $subdomain) {
 $this->redirectToLoginWithError(
 'invalid_pin_tenant',
 sprintf('Geçersiz PIN veya bu PIN bu işletmeye (%s) ait değil.', htmlspecialchars($subdomain)),
 ['subdomain' => $subdomain]
 );
 } else if ($tenantId) {
 $this->redirectToLoginWithError(
 'invalid_pin_tenant',
 'Geçersiz PIN veya bu PIN bu işletmeye ait değil.'
 );
 } else {
 $this->redirectToLoginWithError(
 'invalid_pin',
 'Geçersiz PIN. Lütfen tekrar deneyin.'
 );
 }
 }

 if ($user && !empty($user['user_id'])) {
 $tenantId = TenantContext::getId();
 $userBusinessId = $user['tenant_id'] ?? null;

 if ($tenantId && $userBusinessId) {
 $userBusinessIdStr = trim((string)$userBusinessId);
 $tenantIdStr = trim((string)$tenantId);
 $userBusinessIdStr = preg_replace('/\s+/', '', $userBusinessIdStr);
 $tenantIdStr = preg_replace('/\s+/', '', $tenantIdStr);

 $userBusinessIdLower = strtolower($userBusinessIdStr);
 $tenantIdLower = strtolower($tenantIdStr);

 $isMatch = ($userBusinessIdStr === $tenantIdStr) ||
 ($userBusinessIdLower === $tenantIdLower) ||
 ($userBusinessId == $tenantId);

 if (!$isMatch) {
 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::error("SessionController: Login blocked - User does not belong to this business", [
 'user_id' => $user['user_id'],
 'user_name' => $user['name'] ?? 'unknown',
 'user_business_id' => $userBusinessId,
 'tenant_id' => $tenantId,
 'subdomain' => $subdomain ?? 'unknown',
 'host' => $_SERVER['HTTP_HOST'] ?? 'unknown',
 ]);
 }
 $this->redirectToLoginWithError(
 'pin_wrong_tenant',
 'Bu PIN bu işletmeye ait değil. Lütfen doğru işletme adresinden giriş yapın.'
 );
 }
 }

 $staffBusinessId = $user['tenant_id'] ?? $tenantId ?? null;
 if ($staffBusinessId) {
 try {
 $bizDb = \App\Core\DependencyFactory::getDatabase();
 $bizStmt = $bizDb->prepare("SELECT is_active, is_demo FROM customers WHERE customer_id = :id LIMIT 1");
 $bizStmt->execute(['id' => $staffBusinessId]);
 $bizRow = $bizStmt->fetch(\PDO::FETCH_ASSOC);

 if ($bizRow && isset($bizRow['is_active']) && (int)$bizRow['is_active'] === 0) {
 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::warning("SessionController: Login blocked - Business is deactivated (PIN login)", [
 'user_id' => $user['user_id'],
 'business_id' => $staffBusinessId,
 ]);
 }
 $this->redirectToLoginWithError(
 'business_inactive',
 'İşletmeniz pasife alınmıştır. Lütfen yöneticinizle iletişime geçin.'
 );
 }
 } catch (\Exception $bizEx) {
 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::warning("SessionController: Failed to check business active status", [
 'business_id' => $staffBusinessId,
 'error' => $bizEx->getMessage(),
 ]);
 }
 }
 }

 if (isset($user['is_active']) && (int)$user['is_active'] === 0) {
 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::warning('SessionController: Login blocked - Staff user is inactive', [
 'user_id' => $user['user_id'] ?? null,
 'user_name' => $user['name'] ?? null,
 ]);
 }
 $this->redirectToLoginWithError(
 'staff_inactive',
 'Personel hesabınız pasif durumdadır. Lütfen yöneticinizle iletişime geçin.'
 );
 }

 $normalizedStaffRole = strtoupper(str_replace('ROLE_', '', trim($user['role'] ?? '')));
 $isElevatedRole = in_array(
 $normalizedStaffRole,
 ['SUPER_ADMIN', 'SUPERADMIN', 'ADMIN', 'BUSINESS_MANAGER'],
 true
 );

 if (!$isElevatedRole && $staffBusinessId) {
 try {
 $trialService = \App\Core\DependencyFactory::getTrialService();
 if ($trialService) {
 $suspendInfo = $trialService->isBusinessSuspendedForStaff($staffBusinessId);
 if (!empty($suspendInfo['suspended'])) {
 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::warning('SessionController: Staff login blocked — business subscription suspended/expired', [
 'user_id' => $user['user_id'] ?? null,
 'business_id' => $staffBusinessId,
 'phase' => $suspendInfo['phase'] ?? 'unknown',
 'role' => $normalizedStaffRole,
 ]);
 }

 $errorCode = ($suspendInfo['phase'] ?? '') === 'expired'
 ? 'business_expired'
 : 'business_suspended';

 $message = $suspendInfo['reason']
 ?? 'İşletme hesabı ödeme nedeniyle geçici olarak askıya alındı. Lütfen işletme yöneticinizle iletişime geçin.';

 $this->redirectToLoginWithError($errorCode, $message);
 }
 }
 } catch (\Throwable $suspendEx) {
 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::warning('SessionController: Failed to evaluate subscription phase during staff login', [
 'business_id' => $staffBusinessId,
 'error' => $suspendEx->getMessage(),
 ]);
 }
 }
 }

 try {
 $_SESSION['is_demo'] = $staffBusinessId && \App\Core\DependencyFactory::getCustomerRepository()->isDemoCustomer($staffBusinessId);
 } catch (\Throwable $e) {
 $_SESSION['is_demo'] = false;
 }

 if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
 SessionManager::set('logged_in', true);
 SessionManager::set('user_id', $user['user_id']);
 SessionManager::set('username', $user['name'] ?? $user['username'] ?? '');

 if (!isset($_SESSION['role']) || empty($_SESSION['role'])) {
 $normalizedRole = strtoupper(str_replace('ROLE_', '', trim($user['role'] ?? '')));
 SessionManager::set('role', $normalizedRole);
 }

 if (!isset($_SESSION['role_id']) && !empty($user['role_id'])) {
 SessionManager::set('role_id', $user['role_id']);
 }

 if (!isset($_SESSION['business_id']) && !empty($user['tenant_id'])) {
 SessionManager::setTenantSession($user['tenant_id']);
 }

 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::warning("SessionController: Session was not set properly after authenticateWithPin, fixing it now", [
 'user_id' => $user['user_id'],
 'user_role' => $user['role'] ?? 'not_set',
 ]);
 }
 }

 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::debug("SessionController: User authenticated, proceeding with login flow", [
 'user_id' => $user['user_id'],
 'user_role' => $user['role'] ?? 'not_set',
 'user_role_id' => $user['role_id'] ?? 'not_set',
 'user_business_id' => $user['tenant_id'] ?? 'not_set',
 'tenant_id' => TenantContext::getId(),
 'subdomain' => $subdomain ?? 'none',
 'session_logged_in' => $_SESSION['logged_in'] ?? false,
 'session_user_id' => $_SESSION['user_id'] ?? 'not_set',
 'session_role' => $_SESSION['role'] ?? 'not_set',
 'session_role_id' => $_SESSION['role_id'] ?? 'not_set',
 ]);
 }

 require_once __DIR__ . '/../../core/DependencyFactory.php';
 $twoFactorAuthService = \App\Core\DependencyFactory::getTwoFactorAuthService();

 $enabledMethods = $twoFactorAuthService->getEffectiveMethods($user['user_id']);

 $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
 $require2FA = $settingsService->getSetting('require_2fa', '0') === '1';

 $skip2faForDemo = false;
 try {
 $skip2faForDemo = $staffBusinessId && \App\Core\DependencyFactory::getCustomerRepository()->isDemoCustomer($staffBusinessId);
 } catch (\Throwable $e) {
 }

 if (!$skip2faForDemo && (!empty($enabledMethods) || ($require2FA && in_array($user['role'] ?? '', ['ROLE_MANAGER', 'MANAGER'])))) {
 $_SESSION['2fa_user_id'] = $user['user_id'];
 $_SESSION['2fa_pending'] = true;
 $_SESSION['2fa_table_id'] = $tableId;
 $_SESSION['2fa_methods'] = $enabledMethods;

 $method = !empty($enabledMethods) ? $enabledMethods[0] : 'email';
 $_SESSION['2fa_method'] = $method;

 if ($method === 'totp') {
 $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
 $protocol = getProtocol();
 header('Location: ' . $protocol . '://' . $currentHost . '/auth/2fa/verify');
 exit;
 }

 $sendResult = ($method === 'whatsapp')
 ? $twoFactorAuthService->sendWhatsAppCode($user['user_id'])
 : $twoFactorAuthService->sendVerificationCode($user['user_id'], $method);

 if ($sendResult['success']) {
 $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
 $protocol = getProtocol();
 header('Location: ' . $protocol . '://' . $currentHost . '/auth/2fa/verify');
 exit;
 } else {
 \App\Core\Logger::error('2FA code send failed: ' . ($sendResult['message'] ?? 'Unknown error'));
 }
 }

 if (!empty($tableId)) {
 SessionManager::set('table_id', $tableId);
 }

 $currentIP = $this->getClientIP();
 if (empty($_SESSION['ip_address'])) {
 SessionManager::set('ip_address', $currentIP);
 }
 if (empty($_SESSION['user_agent'])) {
 SessionManager::set('user_agent', $_SERVER['HTTP_USER_AGENT'] ?? '');
 }
 if (empty($_SESSION['last_activity'])) {
 SessionManager::set('last_activity', time());
 }

 try {
 \App\Core\DependencyFactory::getActivityLogService()->log(
 'login',
 $user['tenant_id'] ?? null,
 $user['user_id'] ?? null,
 null,
 null,
 ['channel' => 'staff_pin', 'role' => $user['role'] ?? '']
 );
 } catch (\Throwable $e) {
 }

 if (!empty($_SESSION['is_demo']) && $staffBusinessId) {
 try {
 $fw = \App\Middleware\SecurityMiddleware::getFirewall();
 $ip = $fw->getClientIP();
 $uri = $_SERVER['REQUEST_URI'] ?? '';
 \App\Core\DependencyFactory::getDemoAccessLogRepository()->log(
 $staffBusinessId,
 $user['user_id'] ?? null,
 $ip,
 $_SERVER['HTTP_USER_AGENT'] ?? '',
 $_SERVER['REQUEST_METHOD'] ?? 'POST',
 $uri,
 'login'
 );
 } catch (\Throwable $e) {
 }
 }

 $requiresPasswordChange = $user['requires_password_change'] ?? false;
 if ($requiresPasswordChange) {
 SessionManager::set('force_password_change', true);

 $role = $user['role'] ?? $_SESSION['role'] ?? '';
 $normalizedRole = strtoupper(str_replace('ROLE_', '', trim($role)));

 $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
 $protocol = getProtocol();

 if ($normalizedRole === 'SUPER_ADMIN' || $normalizedRole === 'QODMIN') {
 $redirectPath = '/qodmin/profile?change_password=1';
 } else {
 $redirectPath = '/business/account?change_password=1';
 }
 $redirectUrl = $protocol . '://' . $currentHost . $redirectPath;

 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::info("User login - password change required", [
 'user_id' => $user['user_id'],
 'role' => $role,
 'redirect_url' => $redirectUrl,
 ]);
 }

 $this->toastNotificationService->setFlash('warning', 'İlk girişinizde şifrenizi değiştirmeniz zorunludur.');
 session_write_close();
 header('Location: ' . $redirectUrl);
 exit;
 }

 $role = $user['role'] ?? $_SESSION['role'] ?? '';
 $roleId = $user['role_id'] ?? $_SESSION['role_id'] ?? null;

 if (!empty($role)) {
 $normalizedRole = strtoupper(str_replace('ROLE_', '', trim($role)));
 $role = $normalizedRole;
 }

 if (empty($role)) {
 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::error("SessionController: Role is empty after login", [
 'user_id' => $user['user_id'] ?? 'unknown',
 'user_data' => [
 'role' => $user['role'] ?? 'not_set',
 'role_id' => $user['role_id'] ?? 'not_set',
 ],
 'session_role' => $_SESSION['role'] ?? 'not_set',
 'session_role_id' => $_SESSION['role_id'] ?? 'not_set',
 ]);
 }
 $this->redirectToLoginWithError(
 'role_missing',
 'Giriş başarılı ancak rol bilgisi bulunamadı. Lütfen yöneticinizle iletişime geçin.'
 );
 }

 $dbUser = null;
 if (!empty($user['user_id'])) {
 try {
 require_once __DIR__ . '/../../core/DependencyFactory.php';
 $userService = \App\Core\DependencyFactory::getUserService();
 $dbUser = $userService->findByUserId($user['user_id']);

 if ($dbUser) {
 if (empty($role) || !empty($dbUser['role'])) {
 $role = $dbUser['role'] ?? $role;
 $roleId = $dbUser['role_id'] ?? null;

 if (!empty($role)) {
 $normalizedRole = strtoupper(str_replace('ROLE_', '', trim($role)));
 $role = $normalizedRole;

 SessionManager::set('role', $role);
 if (!empty($roleId)) {
 SessionManager::set('role_id', $roleId);
 }
 }
 }
 }
 } catch (\Exception $e) {
 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::warning("SessionController: Failed to get user data from database", [
 'user_id' => $user['user_id'],
 'error' => $e->getMessage(),
 ]);
 }
 }
 }

 $preparationScreenId = null;

 if (isset($user['preparation_screen_id']) && $user['preparation_screen_id'] !== null && $user['preparation_screen_id'] !== '') {
 $preparationScreenId = $user['preparation_screen_id'];

 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::info('SessionController: Found preparation_screen_id in $user array', [
 'user_id' => $user['user_id'],
 'preparation_screen_id' => $preparationScreenId,
 ]);
 }
 }

 if (empty($preparationScreenId)) {
 try {
 if (!$dbUser && !empty($user['user_id'])) {
 require_once __DIR__ . '/../../core/DependencyFactory.php';
 $userService = \App\Core\DependencyFactory::getUserService();
 $dbUser = $userService->findByUserId($user['user_id']);
 }

 if ($dbUser) {
 $preparationScreenId = $dbUser['preparation_screen_id'] ?? null;
 if ($preparationScreenId === '') {
 $preparationScreenId = null;
 }

 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::info('SessionController: Found preparation_screen_id in database', [
 'user_id' => $user['user_id'],
 'preparation_screen_id' => $preparationScreenId,
 ]);
 }
 }
 } catch (\Exception $e) {
 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::warning("SessionController: Failed to get preparation_screen_id from database", [
 'user_id' => $user['user_id'],
 'error' => $e->getMessage(),
 ]);
 }
 }
 }

 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::info('SessionController: Final preparation_screen_id check', [
 'user_id' => $user['user_id'],
 'preparation_screen_id_from_user' => $user['preparation_screen_id'] ?? 'not_set',
 'preparation_screen_id_from_db' => $dbUser['preparation_screen_id'] ?? 'not_set',
 'final_preparation_screen_id' => $preparationScreenId,
 'db_user_exists' => !empty($dbUser),
 'will_redirect_to_prep_screen' => !empty($preparationScreenId),
 ]);
 }

 if (!empty($preparationScreenId)) {
 try {
 $preparationScreenService = \App\Core\DependencyFactory::getPreparationScreenService();
 $screen = $preparationScreenService->getScreenById($preparationScreenId);

 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::info('SessionController: Attempting to get preparation screen', [
 'user_id' => $user['user_id'],
 'preparation_screen_id' => $preparationScreenId,
 'screen_found' => !empty($screen),
 'screen_data' => $screen ? ['screen_id' => $screen['screen_id'] ?? 'N/A', 'name' => $screen['name'] ?? 'N/A', 'slug' => $screen['slug'] ?? 'N/A'] : 'not_found',
 ]);
 }

 if ($screen && !empty($screen['slug'])) {
 $redirectUrl = '/preparation-screen/' . $screen['slug'];

 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::info('SessionController: SUCCESS - Redirecting user to preparation screen', [
 'user_id' => $user['user_id'],
 'preparation_screen_id' => $preparationScreenId,
 'slug' => $screen['slug'],
 'redirect_url' => $redirectUrl,
 ]);
 }
 } else {
 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::warning('SessionController: Preparation screen not found or slug empty', [
 'user_id' => $user['user_id'],
 'preparation_screen_id' => $preparationScreenId,
 'screen_exists' => !empty($screen),
 'screen_slug' => $screen['slug'] ?? 'not_set',
 'fallback_to_role_redirect' => true,
 ]);
 }
 $redirectUrl = $this->getRedirectUrlByRole($role, $tableId);
 }
 } catch (\Exception $e) {
 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::error('SessionController: Exception getting preparation screen for redirect', [
 'error' => $e->getMessage(),
 'trace' => $e->getTraceAsString(),
 'preparation_screen_id' => $preparationScreenId,
 'user_id' => $user['user_id'],
 ]);
 }
 $redirectUrl = $this->getRedirectUrlByRole($role, $tableId);
 }
 } else {
 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::info('SessionController: No preparation_screen_id found, using role-based redirect', [
 'user_id' => $user['user_id'],
 'role' => $role,
 'preparation_screen_id_from_user' => $user['preparation_screen_id'] ?? 'not_set',
 'preparation_screen_id_from_db' => $dbUser['preparation_screen_id'] ?? 'not_set',
 ]);
 }
 $redirectUrl = $this->getRedirectUrlByRole($role, $tableId);
 }

 if ($redirectUrl === '/login' || strpos($redirectUrl, '/login') !== false) {
 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::warning("SessionController: Redirect URL is /login, role mapping may have failed", [
 'role' => $role,
 'normalized_role' => $normalizedRole ?? 'not_set',
 'role_id' => $roleId,
 'user_id' => $user['user_id'] ?? 'unknown',
 'user_role_from_db' => $dbUser['role'] ?? 'not_set',
 'user_role_from_auth' => $user['role'] ?? 'not_set',
 ]);
 }
 $redirectUrl = $this->getRedirectUrlByRoleFallback($role, $tableId);

 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::info("SessionController: Fallback redirect URL", [
 'fallback_redirect_url' => $redirectUrl,
 'role' => $role,
 'normalized_role' => $normalizedRole ?? 'not_set',
 ]);
 }
 }

 if ($redirectUrl === '/login' || strpos($redirectUrl, '/login') !== false) {
 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::error("SessionController: Cannot determine redirect URL, using role-based default", [
 'user_id' => $user['user_id'],
 'role' => $role,
 'normalized_role' => $normalizedRole ?? 'not_set',
 'role_id' => $roleId,
 'user_role_from_db' => $dbUser['role'] ?? 'not_set',
 'user_role_from_auth' => $user['role'] ?? 'not_set',
 ]);
 }

 $roleBasedDefaults = [
 'WAITER' => '/waiter/dashboard',
 'GARSON' => '/waiter/dashboard',
 'KITCHEN' => '/kitchen/dashboard',
 'MUTFAK' => '/kitchen/dashboard',
 'CHEF' => '/kitchen/dashboard',
 'CASHIER' => '/pos',
 'KASIYER' => '/pos',
 'MANAGER' => '/business/dashboard',
 'BUSINESS_MANAGER' => '/business/dashboard',
 'ADMIN' => '/business/dashboard',
 'ADMINISTRATOR' => '/business/dashboard',
 ];

 $normalizedRoleForDefault = $normalizedRole ?? strtoupper(str_replace('ROLE_', '', trim($role)));
 $redirectUrl = $roleBasedDefaults[$normalizedRoleForDefault] ?? '/business/dashboard';
 $redirectUrl = $this->redirectForStaffChannel($normalizedRoleForDefault, $redirectUrl);

 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::info("SessionController: Using role-based default redirect", [
 'final_redirect_url' => $redirectUrl,
 'normalized_role' => $normalizedRoleForDefault,
 ]);
 }
 }

 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::info("SessionController: About to redirect", [
 'user_id' => $user['user_id'],
 'username' => $user['name'] ?? 'Unknown',
 'role' => $role,
 'normalized_role' => $normalizedRole ?? 'not_set',
 'role_id' => $roleId,
 'redirect_url' => $redirectUrl,
 'base_url' => BASE_URL,
 'full_redirect_url' => BASE_URL . $redirectUrl,
 'ip' => $currentIP,
 'session_logged_in' => $_SESSION['logged_in'] ?? false,
 'session_role' => $_SESSION['role'] ?? 'not_set',
 'session_role_id' => $_SESSION['role_id'] ?? 'not_set',
 ]);
 }

 try {
 \App\Core\Logger::info("User logged in successfully", [
 'user_id' => $user['user_id'],
 'username' => $user['name'] ?? 'Unknown',
 'role' => $role,
 'redirect_url' => $redirectUrl,
 'ip' => $currentIP,
 ]);
 } catch (\Exception $e) {
 \App\Core\Logger::error("SessionController: Failed to log login: " . $e->getMessage());
 }

 session_write_close();

 $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
 $protocol = getProtocol();

 $fullRedirectUrl = $protocol . '://' . $currentHost . $redirectUrl;

 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::info("SessionController: Performing redirect", [
 'redirect_url' => $redirectUrl,
 'current_host' => $currentHost,
 'base_url' => BASE_URL,
 'full_redirect_url' => $fullRedirectUrl,
 'role' => $role,
 ]);
 }

 header('Location: ' . $fullRedirectUrl);
 exit;
 } else {
 $this->redirectToLoginWithError(
 'invalid_pin',
 'Geçersiz PIN. Lütfen tekrar deneyin.'
 );
 }
 } else {
 // Show login form (GET request)
 if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && isset($_SESSION['user_id'])) {
 $currentRole = $_SESSION['role'] ?? '';
 $roleId = $_SESSION['role_id'] ?? null;

 require_once __DIR__ . '/../../core/Validators/RoleValidator.php';
 $isValidRole = \App\Core\Validators\RoleValidator::isValid($currentRole);

 if ($isValidRole || (!empty($roleId) && in_array($roleId, ['ROLE_MANAGER', 'ROLE_WAITER', 'ROLE_KITCHEN', 'ROLE_CASHIER', 'ROLE_CUSTOMER'], true))) {
 $tableId = $_SESSION['table_id'] ?? '';
 $redirectUrl = $this->getRedirectUrlByRole($currentRole, $tableId);

 if ($redirectUrl !== '/login' && strpos($redirectUrl, '/login') === false) {
 $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
 $protocol = getProtocol();
 $fullRedirectUrl = $protocol . '://' . $currentHost . $redirectUrl;

 header('Location: ' . $fullRedirectUrl);
 exit;
 }
 }

 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::warning("Login page: Clearing invalid session", [
 'role' => $currentRole,
 'normalized_role' => $normalizedRole,
 'is_valid_role' => $isValidRole,
 'role_id' => $roleId,
 ]);
 }

 session_destroy();
 session_start();
 $_SESSION = [];
 $_SESSION['logged_in'] = false;
 }

 $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
 $subdomain = TenantContext::getSubdomainFromHost($host);

 $tenant = TenantContext::get();
 $companyName = null;

 if ($subdomain && !$tenant) {
 try {
 require_once __DIR__ . '/../../middleware/TenantMiddleware.php';
 $tenantMiddleware = new \App\Middleware\TenantMiddleware();
 $tenantMiddleware->handle();
 $tenant = TenantContext::get();
 } catch (\Exception $e) {
 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::warning('Error initializing tenant in login', [
 'error' => $e->getMessage(),
 'subdomain' => $subdomain,
 ]);
 }
 }
 }

 if ($tenant && isset($tenant['company_name'])) {
 $companyName = $tenant['company_name'];
 }

 $businessLogoPath = null;
 $businessName = null;
 $businessNumber = '';
 try {
 $tenantId = TenantContext::getId();
 if ($tenantId) {
 $businessService = \App\Core\DependencyFactory::getBusinessService();
 $businessInfo = $businessService->getBusinessInfo($tenantId);
 $businessLogoPath = $businessInfo['logo_path'] ?? $businessInfo['logo_url'] ?? null;
 $businessName = $businessInfo['company_name'] ?? $businessInfo['business_name'] ?? null;

 $customerRepo = \App\Core\DependencyFactory::getCustomerRepository();
 $customerRow = $customerRepo->findById($tenantId);
 if ($customerRow && !empty($customerRow['business_number'])) {
 $businessNumber = trim((string) $customerRow['business_number']);
 }
 }
 } catch (\Exception $e) {
 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::debug('SessionController::login - Failed to load business logo', [
 'error' => $e->getMessage(),
 ]);
 }
 }

 $displayName = $businessName ?: $companyName;

 $this->view('auth/login', [
 'business_logo_path' => $businessLogoPath,
 'company_name' => $displayName,
 'business_number' => $businessNumber,
 ]);
 }
 }

 public function refreshCsrfToken()
 {
 // Public endpoint: anonymous callers (e.g. /register, /login SPA
 // shells) also need to mint a fresh CSRF token bound to their
 // session. We intentionally do NOT gate this on being logged in.
 SessionManager::ensureSession();
 try {
 $token = \App\Core\Security\CSRFManager::generateToken();
 $this->apiResponse([
 'success' => true,
 'csrf_token' => $token,
 'token' => $token,
 ]);
 } catch (\Throwable $e) {
 if (class_exists('\\App\\Core\\Logger')) {
 \App\Core\Logger::error('refreshCsrfToken failed', [
 'error' => $e->getMessage(),
 ]);
 }
 $this->apiResponse([
 'success' => false,
 'error' => 'Token üretilemedi',
 ], 500);
 }
 }

 public function logout()
 {
 SessionManager::ensureSession();
 $logUid = $_SESSION['user_id'] ?? null;
 $logBid = $_SESSION['business_id'] ?? null;
 $logRole = $_SESSION['role'] ?? '';
 if ($logUid) {
 try {
 \App\Core\DependencyFactory::getActivityLogService()->log(
 'logout',
 $logBid !== null && $logBid !== '' ? (string)$logBid : null,
 (string)$logUid,
 null,
 null,
 ['role' => $logRole]
 );
 } catch (\Throwable $e) {
 }
 }

 if (isset($_COOKIE['remember_email'])) {
 $isSecure = isHttps();
 if (PHP_VERSION_ID >= 70300) {
 setcookie(
 'remember_email',
 '',
 [
 'expires' => time() - 3600,
 'path' => '/',
 'domain' => '',
 'secure' => $isSecure,
 'httponly' => true,
 'samesite' => 'Strict',
 ]
 );
 } else {
 setcookie('remember_email', '', time() - 3600, '/', '', $isSecure, true);
 }
 unset($_COOKIE['remember_email']);
 }

 $this->authService->logout();

 if (isset($_GET['demo_register']) && $_GET['demo_register'] === '1') {
 $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
 $protocol = getProtocol();
 $parts = explode('.', $currentHost);
 $mainHost = (count($parts) >= 3) ? implode('.', array_slice($parts, 1)) : $currentHost;
 header('Location: ' . $protocol . '://' . $mainHost . '/register');
 exit;
 }

 $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
 $protocol = getProtocol();
 header('Location: ' . $protocol . '://' . $currentHost . '/login');
 exit;
 }

 public function unauthorized()
 {
 SessionManager::ensureSession();

 $isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
 $role = $_SESSION['role'] ?? null;
 $roleId = $_SESSION['role_id'] ?? null;

 if (!$isLoggedIn) {
 $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
 $protocol = getProtocol();
 header('Location: ' . $protocol . '://' . $currentHost . '/login');
 exit;
 }

 if (empty($role) && empty($roleId)) {
 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::warning("Unauthorized page: Session has logged_in but no role - clearing session", [
 'user_id' => $_SESSION['user_id'] ?? 'unknown',
 ]);
 }
 session_destroy();
 session_start();
 SessionManager::resetInitialized();
 $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
 $protocol = getProtocol();
 header('Location: ' . $protocol . '://' . $currentHost . '/login?session_invalid=1');
 exit;
 }

 $this->view('auth/unauthorized');
 }

 public function apiCheckAuth()
 {
 if (isLoggedIn()) {
 $this->apiResponse([
 'authenticated' => true,
 'user_id' => $_SESSION['user_id'],
 'username' => $_SESSION['username'],
 'role' => $_SESSION['role'],
 ]);
 } else {
 $this->apiResponse(['authenticated' => false], 401);
 }
 }
}
