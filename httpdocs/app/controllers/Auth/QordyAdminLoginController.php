<?php
/**
 * Auth — Qordy Admin Login Controller
 *
 * Handles only the super-admin login surface (/qodmin/login).
 * All other super-admin CRUD endpoints live in Admin/* controllers.
 *
 * Method moved from AuthController.php:
 * qodminLogin
 */
declare(strict_types=1);

namespace App\Controllers\Auth;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/AuthBaseController.php';

use App\Core\SessionManager;

class QordyAdminLoginController extends AuthBaseController
{
 /**
 * Qodmin (Super Admin) login
 * Email/password ile super admin girişi
 */
 public function qodminLogin()
 {
 SessionManager::ensureSession(true);

 // GET: Login formu göster
 if ($_SERVER['REQUEST_METHOD'] === 'GET') {
 // Zaten super admin giriş yapmışsa dashboard'a yönlendir
 $loggedIn = SessionManager::get('logged_in');
 if ($loggedIn === true) {
 $role = SessionManager::get('role') ?? '';
 if ($role === 'SUPER_ADMIN' || $role === 'QODMIN') {
 $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
 $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
 header('Location: ' . $protocol . '://' . $currentHost . '/qodmin/dashboard');
 exit;
 }
 }

 $this->render('auth/qodmin_login', []);
 return;
 }

 // POST: Login işlemi
 $requestData = \App\Core\RequestParser::getRequestData();
 $email = trim($requestData['email'] ?? '');
 $password = $requestData['password'] ?? '';

 if (empty($email) || empty($password)) {
 $this->toastNotificationService->setFlash('error', 'E-posta ve şifre gereklidir');
 session_write_close();
 $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
 $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
 header('Location: ' . $protocol . '://' . $currentHost . '/qodmin/login');
 exit;
 }

 // AdminService ile authentication
 try {
 $adminService = \App\Core\DependencyFactory::getAdminService();
 $admin = $adminService->authenticate($email, $password);

 if ($admin) {
 SessionManager::ensureSession();

 // Role bilgisini al (SUPER_ADMIN)
 $roleId = 'ROLE_SUPER_ADMIN';
 try {
 $roleService = \App\Core\DependencyFactory::getRoleService();
 $roleData = $roleService->getByRoleCode('SUPER_ADMIN');
 if ($roleData && isset($roleData['role_id'])) {
 $roleId = $roleData['role_id'];
 }
 } catch (\Exception $e) {
 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::warning("QordyAdminLoginController: Could not get role_id from RoleService", [
 'error' => $e->getMessage(),
 ]);
 }
 }

 // Session set et
 SessionManager::regenerateId();
 SessionManager::set('user_id', $admin['admin_id'] ?? $admin['id'] ?? null);
 SessionManager::set('username', 'Super Admin');
 SessionManager::set('email', $admin['email']);
 SessionManager::set('name', 'Super Admin');
 SessionManager::set('role', 'SUPER_ADMIN');
 SessionManager::set('role_id', $roleId);
 SessionManager::set('logged_in', true);
 SessionManager::set('login_time', time());
 SessionManager::set('is_super_admin', true);

 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::info("QordyAdminLoginController: Super admin authentication successful", [
 'admin_id' => $admin['admin_id'] ?? $admin['id'] ?? null,
 'email' => $email,
 'role' => 'SUPER_ADMIN',
 'role_id' => $roleId,
 'admin_data_keys' => array_keys($admin),
 ]);
 }

 try {
 \App\Core\DependencyFactory::getActivityLogService()->log(
 'login',
 null,
 (string)($admin['admin_id'] ?? $admin['id'] ?? ''),
 null,
 null,
 ['channel' => 'qodmin', 'email' => $email]
 );
 } catch (\Throwable $e) {
 }

 $this->toastNotificationService->setFlash('success', 'Super Admin girişi başarılı! Hoş geldiniz.');
 $redirectUrl = BASE_URL . '/qodmin/dashboard';

 if (function_exists('session_write_close')) {
 session_write_close();
 }

 header('Location: ' . $redirectUrl);
 exit;
 } else {
 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::warning("QordyAdminLoginController: Authentication failed", [
 'email' => $email,
 ]);
 }
 $this->toastNotificationService->setFlash('error', 'E-posta veya şifre hatalı');
 session_write_close();
 $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
 $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
 header('Location: ' . $protocol . '://' . $currentHost . '/qodmin/login');
 exit;
 }
 } catch (\Exception $e) {
 if (class_exists('\App\Core\Logger')) {
 \App\Core\Logger::error("QordyAdminLoginController - Error", [
 'error' => $e->getMessage(),
 'trace' => $e->getTraceAsString(),
 'email' => $email,
 ]);
 }
 $this->toastNotificationService->setFlash('auth_error', 'Giriş yapılırken bir hata oluştu: ' . $e->getMessage());
 $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
 $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
 header('Location: ' . $protocol . '://' . $currentHost . '/qodmin/login');
 exit;
 }
 }
}
