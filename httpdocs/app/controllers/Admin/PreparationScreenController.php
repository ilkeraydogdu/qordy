<?php
namespace App\Controllers\Admin;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';

class PreparationScreenController extends \App\Core\Controller {
    protected $preparationScreenService;
    protected $categoryService;
    
    public function __construct() {
        parent::__construct();
        $this->preparationScreenService = \App\Core\DependencyFactory::getPreparationScreenService();
        $this->categoryService = \App\Core\DependencyFactory::getCategoryService();
    }

    /**
     * Read the tenant identifier from a preparation_screens row.
     * Supports both `tenant_id` (current schema) and `business_id` (legacy / future)
     * so tenant isolation works regardless of which column is populated.
     */
    private function getScreenTenantId(array $screen): ?string {
        $val = $screen['tenant_id'] ?? $screen['business_id'] ?? null;
        return $val !== null ? (string)$val : null;
    }
    
    /**
     * List all preparation screens
     */
    public function index() {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        $this->requirePermission('preparation-screens.view');
        
        // Sync permissions for existing screens to ensure BUSINESS_MANAGER role has access
        try {
            $dynamicPermissionService = \App\Core\DependencyFactory::getDynamicPermissionService();
            $dynamicPermissionService->syncPreparationScreenPermissions();
        } catch (\Exception $e) {
            error_log("Failed to sync preparation screen permissions in index: " . $e->getMessage());
        }
        
        $screens = $this->preparationScreenService->getAllScreens();
        
        // Load categories for each screen
        foreach ($screens as &$screen) {
            $screen['categories'] = $this->preparationScreenService->getScreenCategories($screen['screen_id']);
        }
        unset($screen);
        
        $data = [
            'title' => 'Hazırlık Ekranları Yönetimi',
            'screens' => $screens,
            'page' => 'preparation-screens',
            'seoParams' => [
                'name' => 'Hazırlık Ekranları'
            ],
            'is_super_admin' => $this->isSuperAdmin()
        ];
        
        $this->view('admin/preparation-screens/index', $data);
    }
    
    /**
     * Show create form
     */
    public function create() {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        
        // Permission check (Super Admin bypass is handled in requirePermission)
        $this->requirePermission('preparation-screens.create');
        
        try {
            $categories = $this->categoryService->getAllCategories();
            
            if (empty($categories)) {
                error_log("Warning: No categories found when creating preparation screen");
            }
        } catch (\Exception $e) {
            error_log("Error loading categories: " . $e->getMessage());
            $categories = [];
        }
        
        $data = [
            'title' => 'Yeni Hazırlık Ekranı Oluştur',
            'categories' => $categories,
            'screen' => null,
            'page' => 'preparation-screens-create',
            'seoParams' => [
                'name' => 'Yeni Hazırlık Ekranı'
            ]
        ];
        
        $this->view('admin/preparation-screens/create', $data);
    }
    
    /**
     * Store new screen
     */
    public function store() {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        $this->requirePermission('preparation-screens.create');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 400);
            return;
        }
        
        $requestData = \App\Core\RequestParser::getRequestData();
        
        $name = trim($requestData['name'] ?? '');
        $slug = trim($requestData['slug'] ?? '');
        $description = trim($requestData['description'] ?? '');
        $productionPoint = trim($requestData['production_point'] ?? '');
        $categoryIds = $requestData['category_ids'] ?? [];
        $isActive = isset($requestData['is_active']) ? (int)$requestData['is_active'] : 1;
        $displayOrder = isset($requestData['display_order']) ? (int)$requestData['display_order'] : 0;
        
        if (empty($name)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', ['message' => 'Ekran adı gereklidir'], 400);
            return;
        }
        
        // Ensure category_ids is an array
        if (!is_array($categoryIds)) {
            $categoryIds = [];
        }
        
        // Validate that at least one category is selected
        if (empty($categoryIds)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', ['message' => 'En az bir kategori seçilmelidir'], 400);
            return;
        }
        
        // CRITICAL: Verify categories exist and belong to current tenant
        // Note: getCategoryById() already applies tenant filter via BaseRepository::findById()
        // If category is found, it belongs to current tenant (tenant isolation is handled at repository level)
        if (!$this->isSuperAdmin()) {
            foreach ($categoryIds as $categoryId) {
                $category = $this->categoryService->getCategoryById($categoryId);
                if (!$category) {
                    // Category not found or doesn't belong to current tenant (repository filter prevents cross-tenant access)
                    \App\Core\Logger::warning('Admin/PreparationScreenController::store - Category not found or access denied', [
                        'category_id' => $categoryId,
                        'tenant_id' => \App\Core\TenantContext::getId()
                    ]);
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
                    return;
                }
            }
        }
        
        // production_point is now optional (for backward compatibility)
        // If not provided, set to null
        if (empty($productionPoint)) {
            $productionPoint = null;
        } else {
            // Validate production_point if provided
            $validProductionPoints = ConstantsHelper::getProductionPoints();
            if (!in_array($productionPoint, $validProductionPoints)) {
                $productionPoint = null; // Invalid value, set to null
            }
        }
        
        // CRITICAL: Ensure tenant_id is set for tenant isolation
        // preparation_screens table uses `tenant_id` column (not business_id)
        $tenantId = \App\Core\TenantContext::getId();
        if (empty($tenantId)) {
            $tenantId = \App\Core\TenantResolver::resolve();
        }

        if (empty($tenantId) && !$this->isSuperAdmin()) {
            \App\Core\Logger::error('Admin/PreparationScreenController::store - Missing tenant context');
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
            return;
        }

        $screenData = [
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'production_point' => $productionPoint,
            'is_active' => $isActive,
            'display_order' => $displayOrder,
            'category_ids' => $categoryIds
        ];

        // Add tenant_id for tenant isolation (column name in preparation_screens table)
        if ($tenantId) {
            $screenData['tenant_id'] = $tenantId;
            // Keep business_id too for forward compatibility; BaseRepository strips unknown columns
            $screenData['business_id'] = $tenantId;
        }
        
        $screenId = $this->preparationScreenService->createScreen($screenData);
        
        if ($screenId) {
            // Sync permissions to ensure they're assigned to relevant roles
            try {
                $dynamicPermissionService = \App\Core\DependencyFactory::getDynamicPermissionService();
                $dynamicPermissionService->syncPreparationScreenPermissions();
            } catch (\Exception $e) {
                error_log("Failed to sync permissions after screen creation: " . $e->getMessage());
            }
            
            $this->toastNotificationService->sendApiResponse('success', 'notifications.success.created', ['item' => 'Hazırlık ekranı'], 200);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.create_failed', ['item' => 'Hazırlık ekranı'], 500);
        }
    }
    
    /**
     * Show edit form
     */
    public function edit($id) {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        $this->requirePermission('preparation-screens.edit');
        
        $screen = $this->preparationScreenService->getScreenById($id);
        
        if (!$screen) {
            require_once __DIR__ . '/../../helpers/url_helper.php';
            header('Location: ' . getAdminUrl('preparation-screens'));
            exit;
        }
        
        // CRITICAL: Verify tenant isolation (unless super admin)
        if (!$this->isSuperAdmin()) {
            $tenantId = \App\Core\TenantContext::getId();
            $screenTenantId = $this->getScreenTenantId($screen);

            if (!$tenantId || $screenTenantId !== (string)$tenantId) {
                \App\Core\Logger::warning('Admin/PreparationScreenController::edit - Tenant isolation violation', [
                    'screen_id' => $id,
                    'screen_tenant_id' => $screenTenantId,
                    'tenant_id' => $tenantId
                ]);
                require_once __DIR__ . '/../../helpers/url_helper.php';
                header('Location: ' . getAdminUrl('preparation-screens'));
                exit;
            }
        }
        
        try {
            $categories = $this->categoryService->getAllCategories();
            
            if (empty($categories)) {
                error_log("Warning: No categories found when editing preparation screen");
            }
        } catch (\Exception $e) {
            error_log("Error loading categories: " . $e->getMessage());
            $categories = [];
        }
        
        $screenCategories = $this->preparationScreenService->getScreenCategories($id);
        $screenCategoryIds = array_column($screenCategories, 'category_id');
        
        $data = [
            'title' => 'Hazırlık Ekranı Düzenle',
            'screen' => $screen,
            'categories' => $categories,
            'screen_category_ids' => $screenCategoryIds,
            'page' => 'preparation-screens-edit',
            'seoParams' => [
                'name' => $screen['name'] ?? '',
                'id' => $screen['screen_id'] ?? ''
            ]
        ];
        
        $this->view('admin/preparation-screens/edit', $data);
    }
    
    /**
     * Update screen
     */
    public function update($id) {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        $this->requirePermission('preparation-screens.edit');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 400);
            return;
        }
        
        // CRITICAL: Verify tenant isolation before update
        $existingScreen = $this->preparationScreenService->getScreenById($id);
        if (!$existingScreen) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.not_found', ['item' => 'Hazırlık ekranı'], 404);
            return;
        }
        
        // Check tenant isolation (unless super admin)
        if (!$this->isSuperAdmin()) {
            $tenantId = \App\Core\TenantContext::getId();
            $screenTenantId = $this->getScreenTenantId($existingScreen);

            if (!$tenantId || $screenTenantId !== (string)$tenantId) {
                \App\Core\Logger::warning('Admin/PreparationScreenController::update - Tenant isolation violation', [
                    'screen_id' => $id,
                    'screen_tenant_id' => $screenTenantId,
                    'tenant_id' => $tenantId
                ]);
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
                return;
            }
        }
        
        $requestData = \App\Core\RequestParser::getRequestData();
        
        $name = trim($requestData['name'] ?? '');
        $slug = trim($requestData['slug'] ?? '');
        $productionPoint = trim($requestData['production_point'] ?? '');
        $categoryIds = $requestData['category_ids'] ?? [];
        $isActive = isset($requestData['is_active']) ? (int)$requestData['is_active'] : 1;
        
        if (empty($name)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', ['message' => 'Ekran adı gereklidir'], 400);
            return;
        }
        
        // Auto-generate slug if not provided
        if (empty($slug)) {
            require_once __DIR__ . '/../../helpers/functions.php';
            $slug = generateSlug($name);
        }
        
        // Ensure category_ids is an array
        if (!is_array($categoryIds)) {
            $categoryIds = [];
        }
        
        // Validate that at least one category is selected
        if (empty($categoryIds)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', ['message' => 'En az bir kategori seçilmelidir'], 400);
            return;
        }
        
        // CRITICAL: Verify categories belong to current tenant
        // Note: categoryService->getCategoryById() already applies tenant filter at repository level,
        // so a returned category implicitly belongs to the current tenant. Keep defensive check below
        // supporting both tenant_id and business_id column names.
        if (!$this->isSuperAdmin()) {
            $tenantId = \App\Core\TenantContext::getId();
            foreach ($categoryIds as $categoryId) {
                $category = $this->categoryService->getCategoryById($categoryId);
                if ($category) {
                    $categoryTenantId = $category['tenant_id'] ?? $category['business_id'] ?? null;
                    if ($categoryTenantId !== null && (string)$categoryTenantId !== (string)$tenantId) {
                        \App\Core\Logger::warning('Admin/PreparationScreenController::update - Category tenant isolation violation', [
                            'category_id' => $categoryId,
                            'category_tenant_id' => $categoryTenantId,
                            'tenant_id' => $tenantId
                        ]);
                        $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
                        return;
                    }
                }
            }
        }
        
        // production_point is now optional (for backward compatibility)
        // If not provided, set to null
        if (empty($productionPoint)) {
            $productionPoint = null;
        } else {
            // Validate production_point if provided
            $validProductionPoints = ConstantsHelper::getProductionPoints();
            if (!in_array($productionPoint, $validProductionPoints)) {
                $productionPoint = null; // Invalid value, set to null
            }
        }
        
        // Get existing screen to preserve display_order
        $displayOrder = $existingScreen['display_order'] ?? 0;
        
        $screenData = [
            'name' => $name,
            'slug' => $slug,
            'production_point' => $productionPoint,
            'is_active' => $isActive,
            'display_order' => $displayOrder, // Keep existing value, not shown in form
            'category_ids' => $categoryIds
        ];
        
        $result = $this->preparationScreenService->updateScreen($id, $screenData);
        
        if ($result) {
            $this->toastNotificationService->sendApiResponse('success', 'notifications.success.updated', ['item' => 'Hazırlık ekranı'], 200);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.update_failed', ['item' => 'Hazırlık ekranı'], 500);
        }
    }
    
    /**
     * Toggle screen active status
     */
    public function toggleActive($id) {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        $this->requirePermission('preparation-screens.edit');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 400);
            return;
        }
        
        // CRITICAL: Verify tenant isolation before toggle
        $screen = $this->preparationScreenService->getScreenById($id);
        if (!$screen) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.not_found', ['item' => 'Hazırlık ekranı'], 404);
            return;
        }
        
        // Check tenant isolation (unless super admin)
        if (!$this->isSuperAdmin()) {
            $tenantId = \App\Core\TenantContext::getId();
            $screenTenantId = $this->getScreenTenantId($screen);

            if (!$tenantId || $screenTenantId !== (string)$tenantId) {
                \App\Core\Logger::warning('Admin/PreparationScreenController::toggleActive - Tenant isolation violation', [
                    'screen_id' => $id,
                    'screen_tenant_id' => $screenTenantId,
                    'tenant_id' => $tenantId
                ]);
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
                return;
            }
        }
        
        // Toggle is_active status
        $newStatus = !empty($screen['is_active']) ? 0 : 1;
        $result = $this->preparationScreenService->updateScreen($id, ['is_active' => $newStatus]);
        
        if ($result) {
            $this->apiResponse([
                'success' => true,
                'is_active' => $newStatus,
                'message' => $newStatus ? 'Ekran aktif edildi' : 'Ekran pasif edildi'
            ]);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.update_failed', ['item' => 'Hazırlık ekranı'], 500);
        }
    }
    
    /**
     * Delete screen
     */
    public function delete($id) {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        $this->requirePermission('preparation-screens.delete');
        
        // CRITICAL: Verify tenant isolation before deletion
        $screen = $this->preparationScreenService->getScreenById($id);
        if (!$screen) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.not_found', ['item' => 'Hazırlık ekranı'], 404);
            return;
        }
        
        // Check tenant isolation (unless super admin)
        if (!$this->isSuperAdmin()) {
            $tenantId = \App\Core\TenantContext::getId();
            $screenTenantId = $this->getScreenTenantId($screen);

            if (!$tenantId || $screenTenantId !== (string)$tenantId) {
                \App\Core\Logger::warning('Admin/PreparationScreenController::delete - Tenant isolation violation', [
                    'screen_id' => $id,
                    'screen_tenant_id' => $screenTenantId,
                    'tenant_id' => $tenantId
                ]);
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
                return;
            }
        }
        
        $result = $this->preparationScreenService->deleteScreen($id);
        
        if ($result) {
            $this->toastNotificationService->sendApiResponse('success', 'notifications.success.deleted', ['item' => 'Hazırlık ekranı'], 200);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.delete_failed', ['item' => 'Hazırlık ekranı'], 500);
        }
    }
    
    /**
     * Get all categories (AJAX)
     */
    public function getCategories() {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        $this->requirePermission('preparation-screens.view');
        
        $categories = $this->categoryService->getAllCategories();
        $this->apiResponse($categories);
    }
    
    /**
     * Get all preparation screens (API endpoint for desktop app)
     * GET /api/preparation-screens
     */
    public function getAll() {
        // Try to authenticate via API key first (for desktop app)
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $queryParams = \App\Core\RequestParser::getQueryParams();
        
        // Check for API key in Authorization header
        $headers = getallheaders();
        $apiKey = null;
        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
            if (preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
                $apiKey = $matches[1];
            }
        }
        
        // Fallback to query params or body
        if (!$apiKey) {
            $apiKey = $queryParams['api_key'] ?? $input['api_key'] ?? null;
        }
        
        // Authenticate via API key
        if ($apiKey) {
            $printerBridgeService = \App\Core\DependencyFactory::getPrinterBridgeService();
            $bridge = $printerBridgeService->getBridgeByApiKey($apiKey);
            
            if (!$bridge) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
                return;
            }
            
            // Set tenant context from bridge
            $businessId = $bridge['business_id'];
            $_SESSION['business_id'] = $businessId;
            $this->setTenantContext($businessId);
        } else {
            // Regular session-based auth
            $this->ensureTenantContext();
            $this->requirePermission('preparation-screens.view');
        }
        
        $screens = $this->preparationScreenService->getAllScreens();
        
        // Format for API response
        $formattedScreens = array_map(function($screen) {
            $tenantId = $screen['tenant_id'] ?? $screen['business_id'] ?? null;
            return [
                'screen_id' => $screen['screen_id'],
                'name' => $screen['name'],
                'slug' => $screen['slug'],
                'is_active' => (int)($screen['is_active'] ?? 0) === 1,
                'business_id' => $tenantId
            ];
        }, $screens);
        
        $this->apiResponse([
            'success' => true,
            'screens' => $formattedScreens
        ]);
    }
    
    /**
     * Get active preparation screens
     * GET /api/business/preparation-screens/active
     */
    public function getActiveScreens() {
        $this->ensureTenantContext();
        
        if (!$this->checkPermissionOrFail('preparation-screens.view')) {
            return;
        }
        
        try {
            $screens = $this->preparationScreenService->getActiveScreens();
            
            // Format for API response
            $formattedScreens = array_map(function($screen) {
                return [
                    'screen_id' => $screen['screen_id'],
                    'id' => $screen['screen_id'], // Alias for compatibility
                    'name' => $screen['name'],
                    'slug' => $screen['slug'],
                    'production_point' => $screen['production_point'] ?? '',
                    'is_active' => $screen['is_active'] == 1
                ];
            }, $screens);
            
            $this->apiResponse([
                'success' => true,
                'screens' => $formattedScreens
            ]);
            
        } catch (\Exception $e) {
            \App\Core\Logger::error('PreparationScreenController::getActiveScreens - Error: ' . $e->getMessage());
            $this->apiResponse([
                'success' => false,
                'error' => 'Failed to load screens'
            ], 500);
        }
    }
}

