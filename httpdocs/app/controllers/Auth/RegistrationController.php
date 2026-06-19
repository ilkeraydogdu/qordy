<?php
/**
 * Auth — Registration Controller
 *
 * Owns the main-domain public surfaces: the customer (email+password)
 * login, the multi-step business registration, the email/phone
 * verification API endpoints, and the subdomain availability probe.
 *
 * Methods moved from AuthController.php:
 * publicLogin, register, checkSubdomainAvailability, sendRegisterEmailCode,
 * verifyRegisterEmail, sendRegisterPhoneCode, verifyRegisterPhone
 */
declare(strict_types=1);

namespace App\Controllers\Auth;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/AuthBaseController.php';

use App\Core\SessionManager;
use App\Core\TenantContext;

class RegistrationController extends AuthBaseController
{
 /**
 * Public müşteri girişi (qordy.com/login)
 * Ana domain'de çalışır, subdomain'lerde staff PIN login'e yönlendirilir
 */
 public function publicLogin()
 {
 // CRITICAL: Subdomain kontrolü - HEM GET HEM POST için
 // Eğer subdomain varsa staff PIN login'e yönlendir
 $subdomain = $this->getSubdomain();

 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::debug('PublicLogin - Subdomain detection', [
 'host' => $_SERVER['HTTP_HOST'] ?? 'unknown',
 'subdomain' => $subdomain,
 'is_subdomain' => !empty($subdomain),
 'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
 ]);
 }

 // CRITICAL: Subdomain varsa PIN login'e yönlendir (staff/personel girişi)
 if ($subdomain) {
 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::info('PublicLogin - Redirecting to staff PIN login (subdomain detected)', [
 'subdomain' => $subdomain,
 'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
 ]);
 }
 return (new SessionController())->login();
 }

 // Ana domain - public müşteri girişi (/login)
 SessionManager::ensureSession(true);

 // GET: Login formu göster
 if ($_SERVER['REQUEST_METHOD'] === 'GET') {
 if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
 $role = $_SESSION['role'] ?? '';
 if ($role === 'BUSINESS_MANAGER' || $role === 'MANAGER' || $role === 'TRIAL' || $role === 'ROLE_TRIAL') {
 $customerId = $_SESSION['customer_id'] ?? null;
 if ($customerId) {
 try {
 $subscriptionService = \App\Core\DependencyFactory::getSubscriptionService();
 $subscription = $subscriptionService->getCustomerSubscription($customerId);
 $hasActiveSubscription = $subscription && !empty($subscription['status']) && strtoupper($subscription['status']) === 'ACTIVE';

 $hasSuperAdminAssigned = $this->hasSuperAdminAssignedBusiness($customerId);

 if ($hasActiveSubscription || $hasSuperAdminAssigned) {
 $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
 $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
 header('Location: ' . $protocol . '://' . $currentHost . '/business/dashboard');
 exit;
 }
 } catch (\Exception $e) {
 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::warning("PublicLogin: Error checking subscription", [
 'error' => $e->getMessage(),
 'customer_id' => $customerId,
 ]);
 }
 }
 } else {
 $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
 $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
 header('Location: ' . $protocol . '://' . $currentHost . '/business/dashboard');
 exit;
 }
 }
 }

 $tenant = TenantContext::get();
 $companyName = null;
 if ($tenant && isset($tenant['company_name'])) {
 $companyName = $tenant['company_name'];
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
 \App\Core\Logger::warning('Error initializing tenant in publicLogin', [
 'error' => $e->getMessage(),
 'subdomain' => $subdomain,
 ]);
 }
 }
 }

 if ($tenant && isset($tenant['company_name'])) {
 $companyName = $tenant['company_name'];
 }

 $prefillEmail = '';
 if (isset($_COOKIE['remember_email']) && !isset($_SESSION['logged_in'])) {
 $prefillEmail = $this->decodeRememberEmail($_COOKIE['remember_email']);
 }

 $formAction = '/login';

 $this->render('auth/public_login', [
 'company_name' => $companyName,
 'prefill_email' => $prefillEmail,
 'form_action' => $formAction,
 ]);
 return;
 }

 // POST: Login işlemi
 $subdomainPost = $this->getSubdomain();
 if ($subdomainPost) {
 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::info('PublicLogin POST - Redirecting to staff PIN login (subdomain detected in POST)', [
 'subdomain' => $subdomainPost,
 'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
 'has_pin' => !empty($requestData['pin'] ?? ''),
 'has_email' => !empty($requestData['email'] ?? ''),
 ]);
 }
 return (new SessionController())->login();
 }

 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::info("PublicLogin POST request received", [
 'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
 'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
 'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'unknown',
 'has_post_data' => !empty($_POST),
 'post_keys' => array_keys($_POST),
 'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
 ]);
 }

 $requestData = \App\Core\RequestParser::getRequestData();
 $email = trim($requestData['email'] ?? '');
 $password = $requestData['password'] ?? '';
 $rememberMe = isset($requestData['rememberMe']) && ($requestData['rememberMe'] === 'on' || $requestData['rememberMe'] === '1' || $requestData['rememberMe'] === true);

 $redirectUrl = '/login';

 if (empty($email) || empty($password)) {
 $this->toastNotificationService->setFlash('error', 'E-posta ve şifre gereklidir');
 session_write_close();
 $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
 $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
 header('Location: ' . $protocol . '://' . $currentHost . $redirectUrl);
 exit;
 }

 // Müşteri authentication - CustomerService kullan
 try {
 $customerService = \App\Core\DependencyFactory::getCustomerService();
 $customer = $customerService->authenticate($email, $password);

 if ($customer) {
 $tenantId = TenantContext::getId();
 $customerBusinessId = $customer['customer_id'];

 if ($tenantId && $customerBusinessId !== $tenantId) {
 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::warning("PublicLogin blocked: Customer from wrong subdomain", [
 'customer_id' => $customerBusinessId,
 'tenant_id' => $tenantId,
 'email' => $email,
 ]);
 }
 $this->toastNotificationService->setFlash('error', 'E-posta veya şifre hatalı');
 session_write_close();
 $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
 $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
 header('Location: ' . $protocol . '://' . $currentHost . $redirectUrl);
 exit;
 }

 SessionManager::ensureSession();

 $userService = \App\Core\DependencyFactory::getUserService();
 $user = $userService->findByEmail($email);

 if (!$user) {
 $userId = $customer['customer_id'];
 } else {
 $userId = $user['user_id'];
 }

 // ------------------------------------------------------------------
 // 2FA gate for manager (e-mail + password) logins. We reuse the same
 // challenge page as staff PIN login but remember that this is a
 // manager flow so verify2FA completes the session correctly.
 // ------------------------------------------------------------------
 try {
 $twoFactorAuthService = \App\Core\DependencyFactory::getTwoFactorAuthService();
 $enabledMethods = $twoFactorAuthService->getEffectiveMethods($userId);
 $isDemo = !empty($customer['is_demo']);
 if (!$isDemo && !empty($enabledMethods)) {
 $_SESSION['2fa_pending'] = true;
 $_SESSION['2fa_user_id'] = $userId;
 $_SESSION['2fa_methods'] = $enabledMethods;
 $_SESSION['2fa_method'] = $enabledMethods[0];
 $_SESSION['2fa_login_type'] = 'manager_password';
 $_SESSION['2fa_manager_payload'] = [
 'customer_id' => $customer['customer_id'],
 'user_id' => $userId,
 'email' => $customer['email'],
 'remember_me' => (bool)$rememberMe,
 'is_demo' => $isDemo,
 ];

 $method = $enabledMethods[0];
 if ($method !== 'totp') {
 $sendResult = ($method === 'whatsapp')
 ? $twoFactorAuthService->sendWhatsAppCode($userId)
 : $twoFactorAuthService->sendVerificationCode($userId, $method);
 if (empty($sendResult['success'])) {
 \App\Core\Logger::warning('Manager 2FA send failed', [
 'user_id' => $userId,
 'method' => $method,
 'result' => $sendResult,
 ]);
 }
 }

 session_write_close();
 $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
 $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
 header('Location: ' . $protocol . '://' . $currentHost . '/auth/2fa/verify');
 exit;
 }
 } catch (\Throwable $e) {
 \App\Core\Logger::warning('Manager 2FA gate skipped', ['error' => $e->getMessage()]);
 }

 $role = $user['role'] ?? 'BUSINESS_MANAGER';
 $roleId = $user['role_id'] ?? null;

 if (!$roleId) {
 try {
 $roleService = \App\Core\DependencyFactory::getRoleService();
 $roleData = $roleService->getByRoleCode('BUSINESS_MANAGER');
 if ($roleData && isset($roleData['role_id'])) {
 $roleId = $roleData['role_id'];
 }
 } catch (\Exception $e) {
 // RoleService hatası - devam et
 }
 }

 SessionManager::regenerateId();
 SessionManager::set('user_id', $userId);
 SessionManager::set('username', $customer['email']);
 SessionManager::set('role', 'BUSINESS_MANAGER');
 SessionManager::set('role_id', $roleId);
 SessionManager::set('logged_in', true);
 SessionManager::set('login_time', time());
 SessionManager::setTenantSession($customer['customer_id']);
 SessionManager::set('is_demo', !empty($customer['is_demo']));

 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::info("PublicLogin: Customer authentication successful", [
 'customer_id' => $customer['customer_id'],
 'user_id' => $userId,
 'email' => $email,
 'role' => 'BUSINESS_MANAGER',
 'role_id' => $roleId,
 ]);
 }

 if ($rememberMe) {
 $cookieExpiry = time() + (30 * 24 * 60 * 60);
 $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
 $signedValue = $this->encodeRememberEmail($email);

 if (PHP_VERSION_ID >= 70300) {
 setcookie(
 'remember_email',
 $signedValue,
 [
 'expires' => $cookieExpiry,
 'path' => '/',
 'domain' => '',
 'secure' => $isSecure,
 'httponly' => true,
 'samesite' => 'Strict',
 ]
 );
 } else {
 setcookie(
 'remember_email',
 $signedValue,
 $cookieExpiry,
 '/',
 '',
 $isSecure,
 true
 );
 }
 } else {
 if (isset($_COOKIE['remember_email'])) {
 setcookie('remember_email', '', time() - 3600, '/', '', true, true);
 unset($_COOKIE['remember_email']);
 }
 }

 $this->toastNotificationService->setFlash('success', 'Giriş başarılı! Hoş geldiniz.');

 try {
 \App\Core\DependencyFactory::getActivityLogService()->log(
 'login',
 $customer['customer_id'] ?? null,
 $userId,
 null,
 null,
 ['channel' => 'business_owner']
 );
 } catch (\Throwable $e) {
 }

 if (!empty($_SESSION['is_demo']) && !empty($customer['customer_id'])) {
 try {
 $fw = \App\Middleware\SecurityMiddleware::getFirewall();
 $ip = $fw->getClientIP();
 \App\Core\DependencyFactory::getDemoAccessLogRepository()->log(
 $customer['customer_id'],
 $userId,
 $ip,
 $_SERVER['HTTP_USER_AGENT'] ?? '',
 $_SERVER['REQUEST_METHOD'] ?? 'POST',
 $_SERVER['REQUEST_URI'] ?? '',
 'login'
 );
 } catch (\Throwable $e) {
 }
 }

 $redirectUrl = BASE_URL . '/business/dashboard';

 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::info("PublicLogin: Redirecting to business dashboard (main domain)", [
 'redirect_url' => $redirectUrl,
 'note' => 'Staying on main domain after login',
 ]);
 }

 if (function_exists('session_write_close')) {
 session_write_close();
 }

 header('Location: ' . $redirectUrl);
 exit;
 } else {
 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::warning("PublicLogin: Customer authentication failed", [
 'email' => $email,
 'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
 'request_uri' => $requestUri ?? 'unknown',
 ]);
 }

 $this->toastNotificationService->setFlash('error', 'E-posta veya şifre hatalı');
 session_write_close();
 $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
 $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
 header('Location: ' . $protocol . '://' . $currentHost . $redirectUrl);
 exit;
 }
 } catch (\Exception $e) {
 if ($e->getMessage() === 'BUSINESS_DEACTIVATED') {
 $this->toastNotificationService->setFlash('error', 'İşletmeniz pasife alınmıştır. Lütfen yöneticinizle iletişime geçin.');
 session_write_close();
 $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
 $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
 header('Location: ' . $protocol . '://' . $currentHost . $redirectUrl);
 exit;
 }

 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::error("PublicLogin: Exception during authentication", [
 'error' => $e->getMessage(),
 'email' => $email,
 'trace' => $e->getTraceAsString(),
 'request_uri' => $requestUri ?? 'unknown',
 ]);
 }

 $this->toastNotificationService->setFlash('error', 'Giriş sırasında bir hata oluştu. Lütfen tekrar deneyin.');
 session_write_close();
 $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
 $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
 header('Location: ' . $protocol . '://' . $currentHost . $redirectUrl);
 exit;
 }
 }

 /**
 * Public müşteri kaydı (qordy.com/business/register)
 */
 public function register()
 {
 SessionManager::ensureSession(true);

 // GET: Kayıt formu göster
 if ($_SERVER['REQUEST_METHOD'] === 'GET') {
 if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
 $role = $_SESSION['role'] ?? '';
 $packageId = $_GET['package'] ?? null;

 if ($role === 'BUSINESS_MANAGER' || $role === 'MANAGER' || $role === 'ROLE_BUSINESS_MANAGER' || $role === 'TRIAL' || $role === 'ROLE_TRIAL') {
 $planType = $_GET['plan'] ?? 'monthly';
 $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
 $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
 if ($packageId) {
 header('Location: ' . $protocol . '://' . $currentHost . '/customer/packages/' . urlencode($packageId) . '/purchase?pricing_type=' . urlencode($planType));
 } else {
 header('Location: ' . $protocol . '://' . $currentHost . '/business/dashboard');
 }
 exit;
 }

 if (!$packageId) {
 $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
 $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
 header('Location: ' . $protocol . '://' . $currentHost . '/dashboard');
 exit;
 }
 }

 $packageId = $_GET['package'] ?? null;
 $planType = $_GET['plan'] ?? 'monthly';
 if ($packageId) {
 $_SESSION['selected_package'] = $packageId;
 $_SESSION['selected_plan_type'] = $planType;
 }

 $this->render('auth/register', [
 'selected_package' => $packageId,
 'selected_plan_type' => $planType,
 ]);
 return;
 }

 // POST: Kayıt işlemi
 $requestData = \App\Core\RequestParser::getRequestData();

 $email = trim($requestData['email'] ?? '');
 $password = $requestData['password'] ?? '';
 $passwordConfirm = $requestData['password_confirm'] ?? '';
 $firstName = trim($requestData['first_name'] ?? '');
 $lastName = trim($requestData['last_name'] ?? '');
 $companyName = trim($requestData['company_name'] ?? '');
 $subdomain = strtolower(trim($requestData['subdomain'] ?? ''));
 $subdomain = preg_replace('/[^a-z0-9-]/', '', $subdomain);
 $subdomain = trim($subdomain, '-');

 $verifySvc = new \App\Services\RegistrationVerificationService();
 $phone = $verifySvc->getVerifiedPhone();

 if (!$verifySvc->isEmailVerified($email)) {
 $this->toastNotificationService->setFlash('error', 'E-posta adresinizi doğrulamanız gerekmektedir');
 session_write_close();
 $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
 $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
 header('Location: ' . $protocol . '://' . $currentHost . '/register');
 exit;
 }

 if (!$verifySvc->isPhoneVerified() || empty($phone)) {
 $this->toastNotificationService->setFlash('error', 'Telefon numaranızı doğrulamanız gerekmektedir');
 session_write_close();
 $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
 $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
 header('Location: ' . $protocol . '://' . $currentHost . '/register');
 exit;
 }

 if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
 $this->toastNotificationService->setFlash('error', 'Geçerli bir e-posta adresi giriniz');
 session_write_close();
 $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
 $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
 header('Location: ' . $protocol . '://' . $currentHost . '/register');
 exit;
 }

 if (empty($password) || strlen($password) < 8) {
 $this->toastNotificationService->setFlash('error', 'Şifre en az 8 karakter olmalıdır');
 session_write_close();
 $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
 $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
 header('Location: ' . $protocol . '://' . $currentHost . '/register');
 exit;
 }
 if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[^\w\s]/', $password)) {
 $this->toastNotificationService->setFlash('error', 'Şifre büyük harf, küçük harf, rakam ve noktalama işareti içermelidir');
 session_write_close();
 $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
 $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
 header('Location: ' . $protocol . '://' . $currentHost . '/register');
 exit;
 }

 if ($password !== $passwordConfirm) {
 $this->toastNotificationService->setFlash('error', 'Şifreler eşleşmiyor');
 session_write_close();
 $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
 $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
 header('Location: ' . $protocol . '://' . $currentHost . '/register');
 exit;
 }

 $customerService = \App\Core\DependencyFactory::getCustomerService();
 $result = $customerService->register([
 'email' => $email,
 'password' => $password,
 'first_name' => $firstName,
 'last_name' => $lastName,
 'phone' => $phone,
 'company_name' => $companyName ?: ($firstName . ' ' . $lastName),
 'subdomain' => $subdomain ?: null,
 ]);

 if ($result['success']) {
 $verifySvc->clearVerificationState();
 $user = $this->authService->authenticateWithEmailPassword($email, $password);

 if ($user) {
 $effectiveTenantId = $user['tenant_id'] ?? $result['customer_id'] ?? null;
 if ($effectiveTenantId) {
 SessionManager::setTenantSession($effectiveTenantId);
 }

 $customerId = \App\Core\TenantResolver::resolve() ?? ($result['customer_id'] ?? null);

 $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
 $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

 if ($customerId) {
 try {
 $trialService = \App\Core\DependencyFactory::getTrialService();
 $trialResult = $trialService->createTrialSubscription($customerId);
 if ($trialResult['success']) {
 $_SESSION['is_trial'] = true;
 $_SESSION['trial_ends_at'] = $trialResult['trial_ends_at'];
 $days = $trialResult['duration_days'];
 $this->toastNotificationService->setFlash('success', "Hoş geldiniz! {$days} günlük ücretsiz denemeniz başladı.");
 } else {
 $this->toastNotificationService->setFlash('success', 'Kayıt başarılı! Hoş geldiniz.');
 }
 } catch (\Exception $e) {
 \App\Core\Logger::error('Trial creation failed during registration', ['error' => $e->getMessage()]);
 $this->toastNotificationService->setFlash('success', 'Kayıt başarılı! Hoş geldiniz.');
 }

 $cacheKey = 'subscription_check_' . $customerId;
 unset($_SESSION[$cacheKey . '_data']);
 unset($_SESSION[$cacheKey . '_time']);
 } else {
 $this->toastNotificationService->setFlash('success', 'Kayıt başarılı! Hoş geldiniz.');
 }

 unset($_SESSION['selected_package']);
 unset($_SESSION['selected_plan_type']);
 $_SESSION['show_package_selection'] = false;
 session_write_close();
 header('Location: ' . $protocol . '://' . $currentHost . '/business/dashboard');
 exit;
 } else {
 if (isset($result['user_id']) && !empty($result['user_id'])) {
 require_once __DIR__ . '/../../core/DependencyFactory.php';
 $userService = \App\Core\DependencyFactory::getUserService();
 $user = $userService->findByUserId($result['user_id']);

 if ($user) {
 SessionManager::regenerateId();
 $_SESSION['user_id'] = $user['user_id'];
 $_SESSION['username'] = $user['name'] ?? $email;
 $_SESSION['role'] = $user['role'] ?? 'BUSINESS_MANAGER';
 $_SESSION['role_id'] = $user['role_id'] ?? null;
 $_SESSION['logged_in'] = true;
 $_SESSION['login_time'] = time();
 $_SESSION['show_package_selection'] = false;

 $effectiveTenantId = $user['tenant_id'] ?? $result['customer_id'] ?? null;
 if ($effectiveTenantId) {
 SessionManager::setTenantSession($effectiveTenantId);
 }

 $fallbackCustomerId = $effectiveTenantId ?? $result['customer_id'] ?? null;
 if ($fallbackCustomerId) {
 try {
 $trialService = \App\Core\DependencyFactory::getTrialService();
 $trialResult = $trialService->createTrialSubscription($fallbackCustomerId);
 if ($trialResult['success']) {
 $_SESSION['is_trial'] = true;
 $_SESSION['trial_ends_at'] = $trialResult['trial_ends_at'];
 $days = $trialResult['duration_days'];
 $this->toastNotificationService->setFlash('success', "Hoş geldiniz! {$days} günlük ücretsiz denemeniz başladı.");
 } else {
 $this->toastNotificationService->setFlash('success', 'Kayıt başarılı! Hoş geldiniz.');
 }
 } catch (\Exception $e) {
 \App\Core\Logger::error('Trial creation failed during fallback registration', ['error' => $e->getMessage()]);
 $this->toastNotificationService->setFlash('success', 'Kayıt başarılı! Hoş geldiniz.');
 }

 $cacheKey = 'subscription_check_' . $fallbackCustomerId;
 unset($_SESSION[$cacheKey . '_data']);
 unset($_SESSION[$cacheKey . '_time']);
 } else {
 $this->toastNotificationService->setFlash('success', 'Kayıt başarılı! Hoş geldiniz.');
 }

 unset($_SESSION['selected_package']);
 unset($_SESSION['selected_plan_type']);
 session_write_close();
 $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
 $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
 header('Location: ' . $protocol . '://' . $currentHost . '/business/dashboard');
 exit;
 }
 }

 $this->toastNotificationService->setFlash('error', 'Kayıt başarılı ancak giriş yapılamadı. Lütfen giriş sayfasından tekrar deneyiniz.');
 session_write_close();
 $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
 $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
 header('Location: ' . $protocol . '://' . $currentHost . '/login');
 exit;
 }
 } else {
 $this->toastNotificationService->setFlash('error', $result['error']);
 session_write_close();
 $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
 $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
 header('Location: ' . $protocol . '://' . $currentHost . '/register');
 exit;
 }
 }

 /**
 * API: Check if a subdomain is available for registration
 */
 public function checkSubdomainAvailability()
 {
 header('Content-Type: application/json; charset=utf-8');
 $slug = strtolower(trim($_GET['subdomain'] ?? ''));
 $slug = preg_replace('/[^a-z0-9-]/', '', $slug);
 $slug = trim($slug, '-');

 if (strlen($slug) < 2) {
 echo json_encode(['available' => false, 'reason' => 'too_short']);
 exit;
 }

 try {
 $db = \App\Core\DependencyFactory::getDatabase();
 $stmt = $db->prepare("SELECT COUNT(*) FROM customers WHERE subdomain = ? LIMIT 1");
 $stmt->execute([$slug]);
 $count = (int)$stmt->fetchColumn();
 echo json_encode(['available' => $count === 0, 'slug' => $slug]);
 } catch (\Exception $e) {
 echo json_encode(['available' => true, 'slug' => $slug]); // allow on error
 }
 exit;
 }

 /**
 * API: Send email verification code during registration
 */
 public function sendRegisterEmailCode()
 {
 SessionManager::ensureSession(true);
 header('Content-Type: application/json; charset=utf-8');
 $data = \App\Core\RequestParser::getRequestData();
 $email = trim($data['email'] ?? '');
 if (empty($email)) {
 echo json_encode(['success' => false, 'error' => 'E-posta gerekli']);
 return;
 }
 $svc = new \App\Services\RegistrationVerificationService();
 $result = $svc->sendEmailCode($email);
 echo json_encode($result['success'] ? ['success' => true, 'message' => $result['message']] : ['success' => false, 'error' => $result['error']]);
 }

 /**
 * API: Verify email code during registration
 */
 public function verifyRegisterEmail()
 {
 SessionManager::ensureSession(true);
 header('Content-Type: application/json; charset=utf-8');
 $data = \App\Core\RequestParser::getRequestData();
 $email = trim($data['email'] ?? '');
 $code = trim($data['code'] ?? '');
 if (empty($email) || empty($code)) {
 echo json_encode(['success' => false, 'error' => 'E-posta ve kod gerekli']);
 return;
 }
 $svc = new \App\Services\RegistrationVerificationService();
 $result = $svc->verifyEmail($email, $code);
 echo json_encode($result);
 }

 /**
 * API: Send phone verification code via WhatsApp during registration
 */
 public function sendRegisterPhoneCode()
 {
 SessionManager::ensureSession(true);
 header('Content-Type: application/json; charset=utf-8');
 $data = \App\Core\RequestParser::getRequestData();
 $phone = trim($data['phone'] ?? '');
 $countryCode = $data['country_code'] ?? '+90';
 if (empty($phone)) {
 echo json_encode(['success' => false, 'error' => 'Telefon numarası gerekli']);
 return;
 }
 $svc = new \App\Services\RegistrationVerificationService();
 $result = $svc->sendPhoneCode($phone, $countryCode);
 echo json_encode($result['success'] ? ['success' => true, 'message' => $result['message']] : ['success' => false, 'error' => $result['error']]);
 }

 /**
 * API: Verify phone code during registration
 */
 public function verifyRegisterPhone()
 {
 SessionManager::ensureSession(true);
 header('Content-Type: application/json; charset=utf-8');
 $data = \App\Core\RequestParser::getRequestData();
 $phone = trim($data['phone'] ?? '');
 $countryCode = $data['country_code'] ?? '+90';
 $code = trim($data['code'] ?? '');
 if (empty($phone) || empty($code)) {
 echo json_encode(['success' => false, 'error' => 'Telefon ve kod gerekli']);
 return;
 }
 $svc = new \App\Services\RegistrationVerificationService();
 $result = $svc->verifyPhone($phone, $countryCode, $code);
 echo json_encode($result);
 }
}
