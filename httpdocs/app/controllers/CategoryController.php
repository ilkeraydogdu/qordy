<?php
namespace App\Controllers;

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/DependencyFactory.php';

class CategoryController extends \App\Core\Controller {
    protected $categoryService;
    protected $menuItemService;

    public function __construct() {
        parent::__construct();
        $this->categoryService = \App\Core\DependencyFactory::getCategoryService();
        $this->menuItemService = \App\Core\DependencyFactory::getMenuItemService();
    }

    /**
     * Sanitize category visual fields (image URL + preset icon key).
     */
    private function extractCategoryVisualFields(array $requestData): array {
        if (!function_exists('posCategoryIconLibrary')) {
            require_once __DIR__ . '/../views/waiter/pos_icons.php';
        }

        $imageUrl = trim((string)($requestData['image_url'] ?? ''));
        if ($imageUrl !== '' && !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            $imageUrl = '';
        }

        $icon = trim((string)($requestData['icon'] ?? ''));
        $library = posCategoryIconLibrary();
        if ($icon !== '' && !isset($library[$icon])) {
            $icon = '';
        }

        return [
            'image_url' => $imageUrl !== '' ? $imageUrl : null,
            'icon' => $icon !== '' ? $icon : null,
        ];
    }
    
    public function index() {
        // NO AUTH CHECK

        // Check if this is an API request
        if ($this->isApiRequest()) {
            // Return JSON for API requests
            $isSuperAdmin = $this->isSuperAdmin();
            $categories = $this->categoryService->getAllCategories();
            
            $this->apiResponse([
                'success' => true,
                'categories' => $categories,
                'data' => $categories // For compatibility
            ]);
            return;
        }

        // Check if user is SUPER_ADMIN
        $isSuperAdmin = $this->isSuperAdmin();

        if ($isSuperAdmin) {
            // For SUPER_ADMIN, get all businesses and their categories
            $customerService = \App\Core\DependencyFactory::getCustomerService();
            $businesses = $customerService->getAllBusinesses();

            $allCategories = [];

            foreach ($businesses as $business) {
                // Handle different possible keys for business ID
                $businessId = $business['business_id'] ?? $business['customer_id'] ?? $business['id'] ?? null;
                
                if ($businessId === null) {
                    continue; // Skip if no valid business ID found
                }
                
                try {
                    $categories = $this->categoryService->getCategoriesByBusiness($businessId);
                    $allCategories[$businessId] = $categories;
                } catch (\Exception $e) {
                    // Log error but continue with other businesses
                    \App\Core\Logger::error("CategoryController: Failed to get categories for business", [
                        'business_id' => $businessId,
                        'error' => $e->getMessage()
                    ]);
                    $allCategories[$businessId] = [];
                }
            }

            $data = [
                'title' => 'Kategori Yönetimi - Qordy',
                'all_categories' => $allCategories,
                'businesses' => $businesses,
                'is_super_admin' => true,
                'categoriesFlat' => $this->categoryService->getAllCategories(), // Flat list for dropdowns
                'breadcrumbs' => [
                    ['label' => 'Ana Sayfa', 'url' => BASE_URL . '/qodmin/dashboard'],
                    ['label' => 'Kategoriler', 'url' => '']
                ]
            ];
        } else {
            // For regular users, get only their business data
            $data = [
                'title' => 'Kategori Yönetimi - Qordy',
                'categories' => $this->categoryService->getAllCategories(),
                'is_super_admin' => false,
                'categoriesFlat' => $this->categoryService->getAllCategories(), // Flat list for dropdowns
                'breadcrumbs' => [
                    ['label' => 'Ana Sayfa', 'url' => BASE_URL . '/business/dashboard'],
                    ['label' => 'Kategoriler', 'url' => '']
                ]
            ];
        }

        $this->view('admin/categories', $data);
    }
    
    public function add() {
        if (!$this->hasPermission('menu.categories')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $jsonInput = file_get_contents('php://input');
            $input = null;
            if (!empty($jsonInput)) {
                $input = json_decode($jsonInput, true);
                if (!is_array($input)) {
                    $input = null;
                }
            }
            $requestData = $input ?? $_POST;
            
            if (empty($requestData['name']) || !is_string($requestData['name']) || trim($requestData['name']) === '') {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_category_name', [], 400, [
                    'errors' => ['name' => ['Kategori adı (Türkçe) zorunludur.']]
                ]);
                return;
            }
            
            $validationResult = $this->validateRequestData($requestData, 'category');
            
            if (!$validationResult['valid']) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_category_name', [], 400, [
                    'errors' => $validationResult['errors']
                ]);
                return;
            }
            
            $validatedData = $validationResult['data'];
            
            // CRITICAL: Ensure business_id is set for tenant isolation
            $this->ensureTenantContext();
            $tenantId = \App\Core\TenantContext::getId();
            
            if (!$tenantId && !$this->isSuperAdmin()) {
                \App\Core\Logger::error('CategoryController::add - No tenant context', [
                    'user_id' => $_SESSION['user_id'] ?? 'unknown'
                ]);
                $this->toastNotificationService->sendApiResponse('error', 'Tenant context required', [], 400);
                return;
            }
            
            $nameEn = trim($requestData['name_en'] ?? '');
            $descriptionEn = trim($requestData['description_en'] ?? '');
            
            $categoryData = [
                'category_id' => generateId('cat'),
                'name' => trim($validatedData['name'] ?? ''),
                'name_en' => $nameEn ?: trim($validatedData['name'] ?? ''),
                'description' => trim($validatedData['description'] ?? ''),
                'description_en' => $descriptionEn,
                'requires_kitchen' => isset($requestData['requires_kitchen']) ? (int)$requestData['requires_kitchen'] : 0,
                'parent_id' => !empty($requestData['parent_id']) ? trim($requestData['parent_id']) : null
            ];
            $categoryData = array_merge($categoryData, $this->extractCategoryVisualFields($requestData));
            
            // CRITICAL: Add tenant_id for tenant isolation (categories table uses tenant_id, not business_id)
            if ($tenantId) {
                $categoryData['tenant_id'] = $tenantId;
            }
            
            $categoryRepository = \App\Core\DependencyFactory::getCategoryRepository();
            // Check for duplicate name within same business (tenant isolation)
            // findByName() already applies tenant filtering, so if it returns a category, it's from the same tenant
            $existingCategory = $categoryRepository->findByName($validatedData['name']);
            if ($existingCategory) {
                // findByName() already filtered by tenant, so if it exists, it's a duplicate
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.category_exists', [], 400);
                return;
            }
            
            $result = $this->categoryService->createCategory($categoryData);
            
            if ($result) {
                $this->toastNotificationService->sendApiResponse('success', 'notifications.success.category_added', [], 200);
            } else {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.create_failed', [], 500);
            }
        }
    }
    
    public function edit() {
        if (!$this->hasPermission('menu.categories')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $categoryId = $queryParams['id'] ?? '';
        if (empty($categoryId)) {
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
            if (preg_match('/edit\/([^\/]+)/', $path, $matches)) {
                $categoryId = $matches[1];
            }
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $requestData = $input ?? $_POST;
            
            if (empty($requestData['name']) || !is_string($requestData['name']) || trim($requestData['name']) === '') {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_category_name', [], 400, [
                    'errors' => ['name' => ['Kategori adı (Türkçe) zorunludur.']]
                ]);
                return;
            }
            
            $validationResult = $this->validateRequestData($requestData, 'category');
            
            if (!$validationResult['valid']) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_category_name', [], 400, [
                    'errors' => $validationResult['errors']
                ]);
                return;
            }
            
            $validatedData = $validationResult['data'];
            
            $nameEn = trim($requestData['name_en'] ?? '');
            $descriptionEn = trim($requestData['description_en'] ?? '');
            
            // CRITICAL: Verify tenant isolation before update
            $this->ensureTenantContext();
            $existingCategory = $this->categoryService->getCategoryById($categoryId);
            if (!$existingCategory) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.category_not_found', [], 404);
                return;
            }
            
            // Check tenant isolation (unless super admin)
            if (!$this->isSuperAdmin()) {
                $tenantId = \App\Core\TenantContext::getId();
                $categoryTenantId = $existingCategory['tenant_id'] ?? $existingCategory['business_id'] ?? null;
                
                if (!$tenantId || $categoryTenantId !== $tenantId) {
                    \App\Core\Logger::warning('CategoryController::edit - Tenant isolation violation', [
                        'category_id' => $categoryId,
                        'category_tenant_id' => $categoryTenantId,
                        'tenant_id' => $tenantId
                    ]);
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
                    return;
                }
            }
            
            $categoryData = [
                'name' => trim($validatedData['name'] ?? ''),
                'name_en' => $nameEn ?: trim($validatedData['name'] ?? ''),
                'description' => trim($validatedData['description'] ?? ''),
                'description_en' => $descriptionEn,
                'requires_kitchen' => isset($requestData['requires_kitchen']) ? (int)$requestData['requires_kitchen'] : 0,
                'parent_id' => !empty($requestData['parent_id']) ? trim($requestData['parent_id']) : null
            ];
            $categoryData = array_merge($categoryData, $this->extractCategoryVisualFields($requestData));
            
            $result = $this->categoryService->updateCategory($categoryId, $categoryData);
            
            if ($result) {
                $this->toastNotificationService->sendApiResponse('success', 'notifications.success.category_updated', [], 200);
            } else {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.update_failed', [], 500);
            }
        }
    }
    
    public function delete() {
        // Check if this is an AJAX request first
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        $isAjax = $isAjax || (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
        
        try {
            $this->requirePermission('menu.categories');
        } catch (\Exception $e) {
            \App\Core\Logger::error("CategoryController::delete - Permission error", [
                'message' => $e->getMessage()
            ]);
            if ($isAjax) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
                return;
            }
            header('Location: ' . BASE_URL . '/unauthorized');
            exit;
        }
        
        try {
            $queryParams = \App\Core\RequestParser::getQueryParams();
            $categoryId = $queryParams['id'] ?? '';
            if (empty($categoryId)) {
                $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
                if (preg_match('/delete\/([^\/]+)/', $path, $matches)) {
                    $categoryId = $matches[1];
                }
            }
            
            if (empty($categoryId)) {
                if ($isAjax) {
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
                    return;
                }
                $this->toastNotificationService->setFlash('error', 'notifications.error.invalid_data');
                \App\Core\HelperLoader::ensureLoaded();
                header('Location: ' . getAdminUrl('categories'));
                exit;
            }
            
            // Check if category has menu items
            $menuItems = $this->menuItemService->getMenuItemsByCategory($categoryId);
            
            if (count($menuItems) > 0) {
                if ($isAjax) {
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.category_has_items', [], 400);
                    return;
                }
                $this->toastNotificationService->setFlash('error', 'notifications.warning.category_has_items');
                \App\Core\HelperLoader::ensureLoaded();
                header('Location: ' . getAdminUrl('categories'));
                exit;
            }
            
            // Check if category exists first
            // CRITICAL: Verify tenant isolation before deletion
            $this->ensureTenantContext();
            $category = $this->categoryService->getCategoryById($categoryId);
            if (!$category) {
                // Category doesn't exist - might already be deleted
                // Consider this a success since the goal (deletion) is already achieved
                \App\Core\Logger::debug("CategoryController::delete - Category not found (may already be deleted)", [
                    'category_id' => $categoryId
                ]);
                
                if ($isAjax) {
                    $this->toastNotificationService->sendApiResponse('success', 'notifications.success.category_deleted', [], 200);
                    return;
                }
                $this->toastNotificationService->setFlash('success', 'notifications.success.category_deleted');
                \App\Core\HelperLoader::ensureLoaded();
                header('Location: ' . getAdminUrl('categories'));
                exit;
            }
            
            // Check tenant isolation (unless super admin)
            if (!$this->isSuperAdmin()) {
                $tenantId = \App\Core\TenantContext::getId();
                $categoryTenantId = $category['tenant_id'] ?? $category['business_id'] ?? null;
                
                if (!$tenantId || $categoryTenantId !== $tenantId) {
                    \App\Core\Logger::warning('CategoryController::delete - Tenant isolation violation', [
                        'category_id' => $categoryId,
                        'category_tenant_id' => $categoryTenantId,
                        'tenant_id' => $tenantId,
                        'user_id' => $_SESSION['user_id'] ?? 'unknown'
                    ]);
                    if ($isAjax) {
                        $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
                        return;
                    }
                    $this->toastNotificationService->setFlash('error', 'notifications.error.unauthorized');
                    \App\Core\HelperLoader::ensureLoaded();
                    header('Location: ' . getAdminUrl('categories'));
                    exit;
                }
            }
            
            // Attempt to delete
            $result = $this->categoryService->deleteCategory($categoryId);
            
            if ($result) {
                if ($isAjax) {
                    $this->toastNotificationService->sendApiResponse('success', 'notifications.success.category_deleted', [], 200);
                    return;
                }
                $this->toastNotificationService->setFlash('success', 'notifications.success.category_deleted');
            } else {
                \App\Core\Logger::error("CategoryController::delete - Delete failed", [
                    'category_id' => $categoryId,
                    'category_exists' => !empty($category)
                ]);
                
                if ($isAjax) {
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.delete_failed', [], 500);
                    return;
                }
                $this->toastNotificationService->setFlash('error', 'notifications.error.delete_failed');
            }
        } catch (\Exception $e) {
            \App\Core\Logger::error("CategoryController::delete - Exception", [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            if ($isAjax) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.delete_failed', [
                    'error_detail' => $e->getMessage()
                ], 500);
                return;
            }
            $this->toastNotificationService->setFlash('error', 'notifications.error.delete_failed');
        }
        
        // Non-AJAX fallback
        if (!$isAjax) {
            header('Location: ' . BASE_URL . '/admin/categories');
            exit;
        }
    }
    
    /**
     * API route için destroy metodu (RouteManager DELETE isteklerinde destroy() arar)
     * @param string $id Category ID
     */
    public function destroy(string $id): void {
        // Check if this is an AJAX/API request
        $isAjax = true; // API route'ları her zaman AJAX
        
        try {
            $this->requirePermission('menu.categories');
        } catch (\Exception $e) {
            \App\Core\Logger::error("CategoryController::destroy - Permission error", [
                'message' => $e->getMessage()
            ]);
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
            return;
        }
        
        try {
            if (empty($id)) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
                return;
            }
            
            // Check if category has menu items
            $menuItems = $this->menuItemService->getMenuItemsByCategory($id);
            
            if (count($menuItems) > 0) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.category_has_items', [], 400);
                return;
            }
            
            // Check if category exists first
            // CRITICAL: Verify tenant isolation before deletion
            $this->ensureTenantContext();
            $category = $this->categoryService->getCategoryById($id);
            if (!$category) {
                // Category doesn't exist - might already be deleted
                \App\Core\Logger::debug("CategoryController::destroy - Category not found (may already be deleted)", [
                    'category_id' => $id
                ]);
                $this->toastNotificationService->sendApiResponse('success', 'notifications.success.category_deleted', [], 200);
                return;
            }
            
            // Check tenant isolation (unless super admin)
            // categories table uses tenant_id column; prefer that, fall back to legacy business_id
            if (!$this->isSuperAdmin()) {
                $tenantId = \App\Core\TenantContext::getId();
                $categoryTenantId = $category['tenant_id'] ?? $category['business_id'] ?? null;

                if (!$tenantId || (string)$categoryTenantId !== (string)$tenantId) {
                    \App\Core\Logger::warning('CategoryController::destroy - Tenant isolation violation', [
                        'category_id' => $id,
                        'category_tenant_id' => $categoryTenantId,
                        'tenant_id' => $tenantId,
                        'user_id' => $_SESSION['user_id'] ?? 'unknown'
                    ]);
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
                    return;
                }
            }
            
            // Attempt to delete
            $result = $this->categoryService->deleteCategory($id);
            
            if ($result) {
                $this->toastNotificationService->sendApiResponse('success', 'notifications.success.category_deleted', [], 200);
            } else {
                \App\Core\Logger::error("CategoryController::destroy - Delete failed", [
                    'category_id' => $id,
                    'category_exists' => !empty($category)
                ]);
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.delete_failed', [], 500);
            }
        } catch (\Exception $e) {
            \App\Core\Logger::error("CategoryController::destroy - Exception", [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.delete_failed', [
                'error_detail' => $e->getMessage()
            ], 500);
        }
    }
}

