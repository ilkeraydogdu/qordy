<?php
namespace App\Controllers\Admin;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';

use App\Core\Controller;

class PackagesController extends Controller {
    
    protected $packageService;
    
    public function __construct() {
        parent::__construct();
        $this->packageService = \App\Core\DependencyFactory::getPackageService();
        if (!function_exists('getAdminUrl')) {
            require_once __DIR__ . '/../../helpers/url_helper.php';
        }
    }
    
    /**
     * List all packages
     */
    public function index() {
        $this->requireLogin();
        
        $isSuperAdmin = $this->isSuperAdmin();
        
        if (!$isSuperAdmin) {
            header('Location: ' . BASE_URL . '/unauthorized');
            exit;
        }
        
        try {
            // Get packages from repository
            $packageRepo = \App\Core\DependencyFactory::getPackageRepository();
            $packages = $packageRepo->getAll();
            
            // Ensure packages is an array
            if (!is_array($packages)) {
                $packages = [];
            }
            
            // Filter valid packages (must have package_id and name)
            $packages = array_filter($packages, function($pkg) {
                if (!is_array($pkg)) {
                    return false;
                }
                // Must have package_id
                if (empty($pkg['package_id'])) {
                    return false;
                }
                // Must have name (even if empty string, but not null)
                if (!isset($pkg['name'])) {
                    return false;
                }
                return true;
            });
            
            // Reset array keys after filtering
            $packages = array_values($packages);
            
            // Log for debugging
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::debug('PackagesController::index - Packages loaded', [
                    'total_count' => count($packages),
                    'is_super_admin' => $isSuperAdmin
                ]);
            }
            
            $this->view('admin/packages', [
                'packages' => $packages,
                'is_super_admin' => $isSuperAdmin
            ]);
        } catch (\Exception $e) {
            // Log error for debugging
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('PackagesController::index - Error loading packages', [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
            } else {
                error_log('PackagesController::index - Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            }
            
            // On error, show empty list
            $this->view('admin/packages', [
                'packages' => [],
                'is_super_admin' => $isSuperAdmin
            ]);
        }
    }
    
    /**
     * Show create package form
     */
    public function create() {
        $this->requireLogin();
        
        $isSuperAdmin = $this->isSuperAdmin();
        if (!$isSuperAdmin) {
            header('Location: ' . BASE_URL . '/unauthorized');
            exit;
        }
        
        // Get navigation items grouped with permissions
        $navigationItems = $this->getNavigationItemsWithPermissions();
        
        $this->view('admin/packages_form', [
            'package' => null,
            'navigationItems' => $navigationItems,
            'packagePermissionIds' => [],
            'is_super_admin' => $isSuperAdmin
        ]);
    }
    
    /**
     * Store new package
     */
    public function store() {
        // Check if AJAX request FIRST - before any output
        $isAjax = (
            (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
            (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
            (!empty($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)
        );
        
        // Force JSON response for AJAX requests - SET HEADER FIRST
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
        }
        
        $this->requireLogin();
        
        if (!$this->isSuperAdmin()) {
            if ($isAjax) {
                return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
            }
            header('Location: ' . BASE_URL . '/unauthorized');
            exit;
        }
        
        try {
            $requestData = \App\Core\RequestParser::getRequestData();
            
            // Log request data for debugging
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::debug('PackagesController::store - Creating package', [
                    'is_ajax' => $isAjax,
                    'package_name' => $requestData['name'] ?? 'N/A',
                    'has_permissions' => !empty($requestData['permissions']),
                    'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
                    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'N/A',
                    'x_requested_with' => $_SERVER['HTTP_X_REQUESTED_WITH'] ?? 'N/A',
                    'request_data_keys' => array_keys($requestData)
                ]);
            }
            
            // Validate required fields
            if (empty($requestData['name'])) {
                $errorMsg = 'Paket adı gereklidir.';
                if ($isAjax) {
                    return $this->jsonResponse(['success' => false, 'message' => $errorMsg], 400);
                }
                $this->toastNotificationService->setFlash('error', $errorMsg);
                header('Location: ' . getAdminUrl('packages'));
                exit;
            }
            
            $result = $this->packageService->createPackage($requestData);
            
            if ($result['success']) {
                $packageId = $result['package_id'] ?? 'unknown';
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::info('PackagesController::store - Package created successfully', [
                        'package_id' => $packageId
                    ]);
                }
                
                if ($isAjax) {
                    return $this->jsonResponse([
                        'success' => true,
                        'message' => 'Paket başarıyla oluşturuldu',
                        'package_id' => $packageId
                    ]);
                }
                
                $this->toastNotificationService->setFlash('success', 'Paket başarıyla oluşturuldu');
                header('Location: ' . getAdminUrl('packages'));
                exit;
            } else {
                $errorMsg = $result['error'] ?? 'Paket oluşturulurken bir hata oluştu';
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('PackagesController::store - Package creation failed', [
                        'error' => $errorMsg
                    ]);
                }
                
                if ($isAjax) {
                    return $this->jsonResponse([
                        'success' => false,
                        'message' => $errorMsg
                    ], 400);
                }
                
                $this->toastNotificationService->setFlash('error', $errorMsg);
                header('Location: ' . getAdminUrl('packages'));
                exit;
            }
        } catch (\PDOException $e) {
            $errorMsg = 'Veritabanı hatası: Paket oluşturulamadı. Lütfen tekrar deneyin.';
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('PackagesController::store - PDOException', [
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            
            if ($isAjax) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => $errorMsg
                ], 500);
            }
            
            $this->toastNotificationService->setFlash('error', $errorMsg);
            header('Location: ' . getAdminUrl('packages'));
            exit;
        } catch (\Exception $e) {
            $errorMsg = 'Paket oluşturulurken beklenmeyen bir hata oluştu: ' . $e->getMessage();
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('PackagesController::store - Exception', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            
            if ($isAjax) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => $errorMsg
                ], 500);
            }
            
            $this->toastNotificationService->setFlash('error', $errorMsg);
            header('Location: ' . getAdminUrl('packages'));
            exit;
        }
    }
    
    /**
     * Show edit package form
     */
    public function edit($packageId) {
        $this->requireLogin();
        
        $isSuperAdmin = $this->isSuperAdmin();
        if (!$isSuperAdmin) {
            header('Location: ' . BASE_URL . '/unauthorized');
            exit;
        }
        
        $package = $this->packageService->getPackageById($packageId);
        
        if (!$package) {
            $this->toastNotificationService->setFlash('error', 'Paket bulunamadı');
            header('Location: ' . getAdminUrl('packages'));
            exit;
        }
        
        // Get package permissions
        $packagePermissionIds = [];
        try {
            $packageRepo = \App\Core\DependencyFactory::getPackageRepository();
            $permissions = $packageRepo->getPackagePermissions($packageId);
            $packagePermissionIds = array_column($permissions, 'permission_id');
        } catch (\Exception $e) {
            // Permissions not available
        }
        
        // Get navigation items grouped with permissions
        $navigationItems = $this->getNavigationItemsWithPermissions();

        // Rol-bazlı: paketin rolleri + mevcut tüm aktif roller
        $packageRoles = [];
        $allRoles = [];
        try {
            $packageRepo = \App\Core\DependencyFactory::getPackageRepository();
            $packageRoles = $packageRepo->getPackageRoles($packageId);
            $roleRepo = \App\Core\DependencyFactory::getRoleRepository();
            $allRoles = $roleRepo->getActiveRoles();
        } catch (\Exception $e) {
            // rol tabloları hazır değilse sessizce atla
        }

        $this->view('admin/packages_form', [
            'package' => $package,
            'navigationItems' => $navigationItems,
            'packagePermissionIds' => $packagePermissionIds,
            'packageRoles' => $packageRoles,
            'allRoles' => $allRoles,
            'is_super_admin' => $isSuperAdmin
        ]);
    }
    
    /**
     * Get package data for edit (API)
     */
    public function editData($packageId) {
        $this->requireLogin();
        
        $isSuperAdmin = $this->isSuperAdmin();
        if (!$isSuperAdmin) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        
        $package = $this->packageService->getPackageById($packageId);
        
        if (!$package) {
            return $this->jsonResponse(['success' => false, 'message' => 'Paket bulunamadı'], 404);
        }
        
        // Get package permissions
        $packagePermissionIds = [];
        try {
            $packageRepo = \App\Core\DependencyFactory::getPackageRepository();
            $permissions = $packageRepo->getPackagePermissions($packageId);
            $packagePermissionIds = array_column($permissions, 'permission_id');
        } catch (\Exception $e) {
            // Permissions not available
        }
        
        return $this->jsonResponse([
            'success' => true,
            'package' => $package,
            'permissions' => $packagePermissionIds
        ]);
    }
    
    /**
     * Update package
     */
    public function update($packageId) {
        // Check if AJAX request FIRST - before any output
        $isAjax = (
            (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
            (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
            (!empty($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false)
        );
        
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
        }
        
        $this->requireLogin();
        
        if (!$this->isSuperAdmin()) {
            if ($isAjax) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        
        try {
            $requestData = \App\Core\RequestParser::getRequestData();
            
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::debug('PackagesController::update - Request data', [
                    'package_id' => $packageId,
                    'is_ajax' => $isAjax,
                    'data_keys' => array_keys($requestData)
                ]);
            }
            
            $result = $this->packageService->updatePackage($packageId, $requestData);
            
            // Log result
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::debug('PackagesController::update - Service result', [
                    'package_id' => $packageId,
                    'result' => $result,
                    'is_ajax' => $isAjax
                ]);
            }
            
            if ($result['success']) {
                // CRITICAL: Log before response
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::info('PackagesController::update - Success, preparing response', [
                        'is_ajax' => $isAjax,
                        'package_id' => $packageId,
                        'headers_sent' => headers_sent($file, $line),
                        'headers_sent_file' => $file ?? 'none',
                        'headers_sent_line' => $line ?? 0
                    ]);
                }
                
                if ($isAjax) {
                    // CRITICAL: Check if headers already sent
                    if (headers_sent($hsfile, $hsline)) {
                        if (class_exists('\App\Core\Logger')) {
                            \App\Core\Logger::error('PackagesController::update - Headers already sent', [
                                'file' => $hsfile,
                                'line' => $hsline
                            ]);
                        }
                    }
                    
                    http_response_code(200);
                    echo json_encode([
                        'success' => true,
                        'message' => 'Paket başarıyla güncellendi',
                        'package_id' => $packageId
                    ], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                
                $this->toastNotificationService->setFlash('success', 'Paket başarıyla güncellendi');
                header('Location: ' . getAdminUrl('packages'));
                exit;
            } else {
                $errorMsg = $result['error'] ?? 'Paket güncellenirken bir hata oluştu';
                
                if ($isAjax) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => $errorMsg
                    ], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                
                $this->toastNotificationService->setFlash('error', $errorMsg);
                header('Location: ' . getAdminUrl('packages/' . $packageId . '/edit'));
                exit;
            }
        } catch (\Exception $e) {
            $errorMsg = 'Paket güncellenirken beklenmeyen bir hata oluştu: ' . $e->getMessage();
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('PackagesController::update - Exception', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'package_id' => $packageId
                ]);
            }
            
            if ($isAjax) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => $errorMsg
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            $this->toastNotificationService->setFlash('error', $errorMsg);
            header('Location: ' . getAdminUrl('packages/' . $packageId . '/edit'));
            exit;
        }
    }
    
    /**
     * Delete package
     */
    public function delete($packageId) {
        // Force JSON response
        header('Content-Type: application/json; charset=utf-8');
        
        $this->requireLogin();
        
        $isSuperAdmin = $this->isSuperAdmin();
        if (!$isSuperAdmin) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        try {
            $result = $this->packageService->deletePackage($packageId);
            
            if ($result['success']) {
                http_response_code(200);
                echo json_encode(['success' => true, 'message' => 'Paket başarıyla silindi'], JSON_UNESCAPED_UNICODE);
                exit;
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => $result['error'] ?? 'Paket silinirken bir hata oluştu'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('PackagesController::delete - Exception', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'package_id' => $packageId
                ]);
            }
            
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Paket silinirken bir hata oluştu: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    
    /**
     * Get package permissions (API)
     */
    public function getPermissions($packageId) {
        $this->requireLogin();
        
        $isSuperAdmin = $this->isSuperAdmin();
        if (!$isSuperAdmin && !$this->hasPermission('admin.packages.view')) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        
        try {
            $packageRepo = \App\Core\DependencyFactory::getPackageRepository();
            $permissions = $packageRepo->getPackagePermissions($packageId);
            
            return $this->jsonResponse(['success' => true, 'permissions' => $permissions]);
        } catch (\Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Assign permission to package (API)
     */
    public function assignPermission($packageId) {
        $this->requireLogin();
        
        $isSuperAdmin = $this->isSuperAdmin();
        if (!$isSuperAdmin && !$this->hasPermission('admin.packages.update')) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        
        $requestData = \App\Core\RequestParser::getRequestData();
        $permissionId = $requestData['permission_id'] ?? null;
        
        if (!$permissionId) {
            return $this->jsonResponse(['success' => false, 'message' => 'Permission ID required'], 400);
        }
        
        try {
            $packageRepo = \App\Core\DependencyFactory::getPackageRepository();
            $packageRepo->assignPermission($packageId, $permissionId);
            
            return $this->jsonResponse(['success' => true, 'message' => 'Permission assigned']);
        } catch (\Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
    
    // ──────────────────────────────────────────────────────────────
    //  Rol-bazlı paket yönetimi (yeni API)
    //  Eski getPermissions/assignPermission deprecated; backward-compat korunur.
    // ──────────────────────────────────────────────────────────────

    /**
     * Paketin rollerini listeler (GET).
     */
    public function getRoles($packageId) {
        $this->requireLogin();

        $isSuperAdmin = $this->isSuperAdmin();
        if (!$isSuperAdmin && !$this->hasPermission('admin.packages.view')) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            $packageRepo = \App\Core\DependencyFactory::getPackageRepository();
            $roles = $packageRepo->getPackageRoles($packageId);
            return $this->jsonResponse(['success' => true, 'roles' => $roles]);
        } catch (\Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Paketin rollerini toplu günceller (POST/PUT).
     * Payload: { roles: [role_id, ...] ya da [{role_id, is_owner_role}, ...] }
     */
    public function assignRoles($packageId) {
        $this->requireLogin();

        $isSuperAdmin = $this->isSuperAdmin();
        if (!$isSuperAdmin && !$this->hasPermission('admin.packages.update')) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $requestData = \App\Core\RequestParser::getRequestData();
        $roles = $requestData['roles'] ?? [];
        if (!is_array($roles)) {
            return $this->jsonResponse(['success' => false, 'message' => 'roles bir dizi olmalı'], 400);
        }

        try {
            $packageRepo = \App\Core\DependencyFactory::getPackageRepository();
            $ok = $packageRepo->syncPackageRoles($packageId, $roles);
            if (!$ok) {
                return $this->jsonResponse(['success' => false, 'message' => 'Roller güncellenemedi'], 500);
            }
            $updated = $packageRepo->getPackageRoles($packageId);
            return $this->jsonResponse(['success' => true, 'roles' => $updated]);
        } catch (\Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove permission from package (API)
     */
    public function removePermission($packageId, $permissionId) {
        $this->requireLogin();
        
        $isSuperAdmin = $this->isSuperAdmin();
        if (!$isSuperAdmin && !$this->hasPermission('admin.packages.update')) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        
        try {
            $packageRepo = \App\Core\DependencyFactory::getPackageRepository();
            $packageRepo->removePermission($packageId, $permissionId);
            
            return $this->jsonResponse(['success' => true, 'message' => 'Permission removed']);
        } catch (\Exception $e) {
            return $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Toggle package active status
     */
    public function toggleActive($packageId) {
        $this->requireLogin();
        
        if (!$this->isSuperAdmin()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        
        $package = $this->packageService->getPackageById($packageId);
        if (!$package) {
            return $this->jsonResponse(['success' => false, 'message' => 'Paket bulunamadı'], 404);
        }
        
        $newStatus = !$package['is_active'];
        $result = $this->packageService->updatePackage($packageId, ['is_active' => $newStatus]);
        
        if ($result['success']) {
            return $this->jsonResponse([
                'success' => true, 
                'message' => $newStatus ? 'Paket aktif edildi' : 'Paket pasif edildi',
                'is_active' => $newStatus
            ]);
        } else {
            return $this->jsonResponse(['success' => false, 'message' => $result['error'] ?? 'Durum güncellenemedi'], 500);
        }
    }
    
    /**
     * Apply discount to package
     */
    public function applyDiscount($packageId) {
        $this->requireLogin();
        
        if (!$this->isSuperAdmin()) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        
        $requestData = \App\Core\RequestParser::getRequestData();
        $discountPercentage = isset($requestData['discount_percentage']) ? floatval($requestData['discount_percentage']) : 0;
        
        if ($discountPercentage < 0 || $discountPercentage > 100) {
            return $this->jsonResponse(['success' => false, 'message' => 'İndirim yüzdesi 0-100 arasında olmalıdır'], 400);
        }
        
        $package = $this->packageService->getPackageById($packageId);
        if (!$package) {
            return $this->jsonResponse(['success' => false, 'message' => 'Paket bulunamadı'], 404);
        }
        
        $updateData = ['discount_percentage' => $discountPercentage];
        $result = $this->packageService->updatePackage($packageId, $updateData);
        
        if ($result['success']) {
            return $this->jsonResponse([
                'success' => true, 
                'message' => 'İndirim uygulandı',
                'discount_percentage' => $discountPercentage
            ]);
        } else {
            return $this->jsonResponse(['success' => false, 'message' => $result['error'] ?? 'İndirim uygulanamadı'], 500);
        }
    }
    
    /**
     * Generate package description using Gemini AI
     */
    public function generateDescription() {
        $this->requireLogin();
        
        $isSuperAdmin = $this->isSuperAdmin();
        if (!$isSuperAdmin && !$this->hasPermission('admin.packages.create') && !$this->hasPermission('admin.packages.update')) {
            return $this->jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
        }
        
        try {
            $requestData = \App\Core\RequestParser::getRequestData();
            $packageName = $requestData['package_name'] ?? '';
            
            if (empty($packageName)) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Paket adı gereklidir.'
                ], 400);
            }
            
            // Get package data if package_id is provided (for edit mode)
            $packageData = [];
            if (!empty($requestData['package_id'])) {
                $package = $this->packageService->getPackageById($requestData['package_id']);
                if ($package) {
                    $packageData = $package;
                }
            } else {
                // For new packages, use form data
                $packageData = [
                    'price_one_time' => $requestData['price_one_time'] ?? null,
                    'price_monthly' => $requestData['price_monthly'] ?? null,
                    'price_yearly' => $requestData['price_yearly'] ?? null,
                    'features' => $requestData['features'] ?? null
                ];
            }
            
            $geminiService = \App\Core\DependencyFactory::getGeminiService();
            $description = $geminiService->generatePackageDescription($packageName, $packageData);
            
            return $this->jsonResponse([
                'success' => true,
                'description' => $description
            ]);
        } catch (\Exception $e) {
            // Log the error for debugging
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Package description generation failed: ' . $e->getMessage(), [
                    'trace' => $e->getTraceAsString(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]);
            } else {
                error_log('Package description generation failed: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            }
            
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Açıklama oluşturulurken bir hata oluştu: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get navigation items with their related permissions
     * Groups permissions by navigation item (menu) and filters only allowed items for packages
     */
    private function getNavigationItemsWithPermissions(): array {
        try {
            // Auto-sync: ensure system_permissions are up-to-date with navigation_items
            // before building the package permission form. This makes the system dynamic —
            // new nav items get their permissions created automatically.
            try {
                $db = \App\Core\DependencyFactory::getDatabase();
                require_once __DIR__ . '/../../services/NavigationPermissionSync.php';
                $syncService = new \App\Services\NavigationPermissionSync($db);
                $syncService->syncAll();
            } catch (\Exception $syncEx) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning('PackagesController: NavigationPermissionSync failed', [
                        'error' => $syncEx->getMessage()
                    ]);
                }
            }

            // These nav items are excluded from package feature selection because they are
            // either super-admin-only (SaaS management) or always granted to business managers
            // with an active subscription (ROLES_PERMISSIONS, SYSTEM_SETTINGS, ERROR_LOGS)
            // and therefore cannot be purchased as a package feature.
            $excludedNavIds = [
                // Super admin / SaaS management items
                'SUPER_ADMIN_DASHBOARD', 'SAAS_MANAGEMENT', 'ALL_BUSINESSES',
                'BUSINESS_OWNERS', 'CONTACT_FORMS', 'PACKAGES', 'SUBSCRIPTIONS',
                'BANK_TRANSFERS', 'BANK_ACCOUNTS', 'ALL_USERS', 'CUSTOMERS',
                'MIGRATIONS', 'FEATURE_FLAGS', 'FEATURE_FLAGS_BUSINESS',
                'GENERAL_SETTINGS', 'BUSINESS_SETTINGS',
                'SYSTEM_LOGS', 'ERROR_LOGS', 'PREP_SCREENS',
                // Admin-only or always-available items (not purchasable features)
                'PAYMENT_GATEWAYS',
                'ROLES_PERMISSIONS',
                'SYSTEM_SETTINGS',
                'ERROR_ANALYTICS',
                'LIVE_CHAT',
                'TRIAL_MANAGEMENT',
            ];

            $containerIds = ['SCREENS', 'OPERATIONS', 'FINANCE', 'ANALYTICS', 'SETTINGS'];

            $groupMap = [
                'DASHBOARD' => 'summary',
                'SCREENS' => 'screens',
                'OPERATIONS' => 'operations',
                'FINANCE' => 'finance',
                'ANALYTICS' => 'analytics',
                'SETTINGS' => 'settings',
            ];

            $navigationService = \App\Core\DependencyFactory::getNavigationService();
            $treeNavItems = $navigationService->getNavigationItems(true);
            $allNavItems = $this->mergeOrphanNavigationItems($treeNavItems);

            $permissionService = \App\Core\DependencyFactory::getPermissionService();
            $allPermissions = $permissionService->getAll();

            $permissionsByPrefix = [];
            foreach ($allPermissions as $perm) {
                $key = $perm['permission_key'] ?? '';
                $permId = $perm['permission_id'] ?? '';
                if (empty($key) || empty($permId)) continue;
                $prefix = explode('.', $key)[0];
                $permissionsByPrefix[$prefix][] = $perm;
            }

            $db = \App\Core\DependencyFactory::getDatabase();

            $groupedItems = [
                'summary' => [], 'screens' => [], 'operations' => [],
                'finance' => [], 'analytics' => [], 'settings' => []
            ];

            foreach ($allNavItems as $item) {
                $itemId = $item['id'] ?? '';
                $parentId = $item['parent_id'] ?? null;
                $children = $item['children'] ?? [];
                $itemIdUpper = strtoupper(trim($itemId));
                $parentIdUpper = $parentId ? strtoupper(trim($parentId)) : '';

                if (in_array($itemIdUpper, $excludedNavIds) || $parentIdUpper === 'SAAS_MANAGEMENT' || $itemIdUpper === 'ROOT') {
                    continue;
                }

                $isContainer = in_array($itemIdUpper, $containerIds);
                $permData = $this->resolveNavPermission($item, $permissionsByPrefix, $db, $isContainer);

                $navItemData = [
                    'nav_id' => $itemId,
                    'nav_label' => $item['label'] ?? $itemId,
                    'permission_key' => $permData['key'],
                    'permission_prefix' => $permData['prefix'],
                    'permissions' => $permData['permissions'],
                    'icon' => $item['icon'] ?? null,
                    'url' => $item['url'] ?? '',
                    'parent_id' => $parentId,
                    'children' => []
                ];

                if (!empty($children) && is_array($children)) {
                    foreach ($children as $child) {
                        $childId = $child['id'] ?? '';
                        $childIdUpper = strtoupper(trim($childId));
                        if (in_array($childIdUpper, $excludedNavIds)) continue;

                        $childPerm = $this->resolveNavPermission($child, $permissionsByPrefix, $db, false);
                        $navItemData['children'][] = [
                            'nav_id' => $childId,
                            'nav_label' => $child['label'] ?? $childId,
                            'permission_key' => $childPerm['key'],
                            'permission_prefix' => $childPerm['prefix'],
                            'permissions' => $childPerm['permissions'],
                            'icon' => $child['icon'] ?? null,
                            'url' => $child['url'] ?? ''
                        ];
                    }
                }

                if ($itemIdUpper === 'DASHBOARD') {
                    $groupedItems['summary'][] = $navItemData;
                } elseif (isset($groupMap[$itemIdUpper])) {
                    $groupedItems[$groupMap[$itemIdUpper]][] = $navItemData;
                } elseif (isset($groupMap[$parentIdUpper])) {
                    $groupKey = $groupMap[$parentIdUpper];
                    $found = false;
                    foreach ($groupedItems[$groupKey] as &$parentItem) {
                        if (strtoupper(trim($parentItem['nav_id'])) === $parentIdUpper) {
                            $parentItem['children'][] = $navItemData;
                            $found = true;
                            break;
                        }
                    }
                    unset($parentItem);
                    if (!$found) {
                        $groupedItems[$groupKey][] = $navItemData;
                    }
                }
            }

            $parentDefaults = [
                'screens' => ['id' => 'SCREENS', 'label' => 'Ekranlar', 'icon' => 'Monitor'],
                'operations' => ['id' => 'OPERATIONS', 'label' => 'İşlemler', 'icon' => 'ShoppingCart'],
                'finance' => ['id' => 'FINANCE', 'label' => 'Finans', 'icon' => 'Wallet'],
                'analytics' => ['id' => 'ANALYTICS', 'label' => 'Analizler', 'icon' => 'BarChart'],
                'settings' => ['id' => 'SETTINGS', 'label' => 'Ayarlar', 'icon' => 'Settings'],
            ];

            foreach ($parentDefaults as $groupKey => $defaults) {
                if (empty($groupedItems[$groupKey])) continue;

                $parentItem = null;
                $standalone = [];

                foreach ($groupedItems[$groupKey] as $item) {
                    if (strtoupper(trim($item['nav_id'] ?? '')) === $defaults['id']) {
                        $parentItem = $item;
                    } else {
                        $standalone[] = $item;
                    }
                }

                if (!$parentItem && !empty($standalone)) {
                    $parentItem = [
                        'nav_id' => $defaults['id'], 'nav_label' => $defaults['label'],
                        'permission_key' => '', 'permission_prefix' => strtolower($defaults['id']),
                        'permissions' => [], 'icon' => $defaults['icon'],
                        'url' => '#', 'parent_id' => null, 'children' => $standalone
                    ];
                    $groupedItems[$groupKey] = [$parentItem];
                } elseif ($parentItem && !empty($standalone)) {
                    $existingChildIds = array_map(fn($c) => strtoupper(trim($c['nav_id'] ?? '')), $parentItem['children'] ?? []);
                    foreach ($standalone as $child) {
                        $childUpper = strtoupper(trim($child['nav_id'] ?? ''));
                        if (!in_array($childUpper, $existingChildIds)) {
                            $parentItem['children'][] = $child;
                        }
                    }
                    $groupedItems[$groupKey] = [$parentItem];
                }
            }

            return $groupedItems;
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('getNavigationItemsWithPermissions failed', [
                    'error' => $e->getMessage()
                ]);
            }
            return [
                'summary' => [], 'screens' => [], 'operations' => [],
                'finance' => [], 'analytics' => [], 'settings' => []
            ];
        }
    }

    /**
     * Resolve permission data for a navigation item.
     * If the item has no permission_key, auto-generates one from the nav_key
     * and ensures a matching system_permission exists.
     */
    private function resolveNavPermission(array $item, array &$permissionsByPrefix, $db, bool $isContainer): array {
        $permissionKey = $item['permission'] ?? '';
        $itemId = strtoupper(trim($item['id'] ?? ''));

        if (empty($permissionKey) || $permissionKey === '-') {
            // Convert underscore-separated nav IDs to hyphenated permission prefixes
            // e.g. PREPARATION_SCREENS → preparation-screens, FINANCE_MAIN → finance-main
            $generatedPrefix = strtolower(str_replace(['_', ' '], '-', $itemId));
            $permissionKey = $generatedPrefix . '.view';

            if (!isset($permissionsByPrefix[$generatedPrefix])) {
                if (!$isContainer) {
                    $newPermId = $generatedPrefix . '.view';
                    $newPermName = ($item['label'] ?? $itemId) . ' Görüntüleme';
                    try {
                        $stmt = $db->prepare("INSERT IGNORE INTO system_permissions (permission_id, permission_key, permission_name) VALUES (?, ?, ?)");
                        $stmt->execute([$newPermId, $newPermId, $newPermName]);
                    } catch (\Exception $e) { /* ignore duplicates */ }
                    $permissionsByPrefix[$generatedPrefix] = [[
                        'permission_id' => $newPermId,
                        'permission_key' => $newPermId,
                        'permission_name' => $newPermName
                    ]];
                } else {
                    $permissionsByPrefix[$generatedPrefix] = [];
                }
            }
        }

        $prefix = explode('.', $permissionKey)[0] ?? '';

        if (empty($prefix) || !isset($permissionsByPrefix[$prefix])) {
            $prefix = strtolower(str_replace([' ', '-'], '_', $itemId));
            if (!isset($permissionsByPrefix[$prefix])) {
                $permissionsByPrefix[$prefix] = [];
            }
        }

        $filtered = array_filter($permissionsByPrefix[$prefix] ?? [], fn($p) => !empty($p['permission_id'] ?? ''));

        return [
            'key' => $permissionKey,
            'prefix' => $prefix,
            'permissions' => array_values($filtered)
        ];
    }

    private function flattenNavTree(array $items): array {
        $flat = [];
        foreach ($items as $item) {
            $children = $item['children'] ?? [];
            $copy = $item;
            unset($copy['children']);
            $flat[] = $copy;
            if (!empty($children)) {
                $flat = array_merge($flat, $this->flattenNavTree($children));
            }
        }
        return $flat;
    }

    /**
     * Ağaçta olmayan (çok seviyeli menü) öğelerini veritabanından ekler — paket formunda tüm menüler görünsün.
     */
    private function mergeOrphanNavigationItems(array $treeNavItems): array {
        $flat = $this->flattenNavTree($treeNavItems);
        $presentKeys = [];
        foreach ($flat as $row) {
            if (!empty($row['id'])) {
                $presentKeys[strtoupper(trim($row['id']))] = true;
            }
        }
        $excluded = [
            'SUPER_ADMIN_DASHBOARD', 'SAAS_MANAGEMENT', 'ALL_BUSINESSES',
            'BUSINESS_OWNERS', 'CONTACT_FORMS', 'PACKAGES', 'SUBSCRIPTIONS',
            'BANK_TRANSFERS', 'BANK_ACCOUNTS', 'ALL_USERS', 'CUSTOMERS',
            'MIGRATIONS', 'FEATURE_FLAGS', 'FEATURE_FLAGS_BUSINESS',
            'GENERAL_SETTINGS', 'BUSINESS_SETTINGS',
            'SYSTEM_LOGS', 'ERROR_LOGS', 'PREP_SCREENS'
        ];
        try {
            $db = \App\Core\DependencyFactory::getDatabase();
            $stmt = $db->query("SELECT nav_id, nav_key, label_tr, parent_id, permission_key, url, icon FROM navigation_items WHERE is_active = 1 ORDER BY display_order ASC, nav_key ASC");
            $rows = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
        } catch (\Exception $e) {
            $rows = [];
        }
        $merged = $treeNavItems;
        foreach ($rows as $r) {
            $nk = strtoupper(trim($r['nav_key'] ?? ''));
            if ($nk === '' || in_array($nk, $excluded, true)) {
                continue;
            }
            $p = strtoupper(trim((string)($r['parent_id'] ?? '')));
            if ($p === 'SAAS_MANAGEMENT') {
                continue;
            }
            if (isset($presentKeys[$nk])) {
                continue;
            }
            $merged[] = [
                'id' => $r['nav_key'],
                'label' => $r['label_tr'] ?? $nk,
                'permission' => $r['permission_key'] ?? '',
                'url' => $r['url'] ?? '',
                'icon' => $r['icon'] ?? null,
                'parent_id' => $r['parent_id'] ?? null,
                'children' => []
            ];
            $presentKeys[$nk] = true;
        }
        return $merged;
    }
}
