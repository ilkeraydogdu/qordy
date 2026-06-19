<?php
namespace App\Controllers;

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/DependencyFactory.php';
use App\Core\Helpers\ConstantsHelper;

class MenuController extends \App\Core\Controller {
    protected $menuItemService;
    protected $categoryService; // Only for getting category list for dropdowns

    public function __construct() {
        parent::__construct(); // Initialize auth and other services
        $this->menuItemService = \App\Core\DependencyFactory::getMenuItemService();
        $this->categoryService = \App\Core\DependencyFactory::getCategoryService(); // Only for dropdown list
        // AI servis artık merkezi yapıda, static metodlar kullanılıyor
    }

    public function index() {
        // NO AUTH CHECK - Sadece session'dan business ID'yi al
        $customerId = \App\Core\TenantResolver::resolve();

        // Check if user is SUPER_ADMIN
        $isSuperAdmin = $this->isSuperAdmin();
        $businessId = null;

        // SuperAdmin için business_id query parametresini kontrol et ve TenantContext'e set et
        if ($isSuperAdmin) {
            $queryParams = \App\Core\RequestParser::getQueryParams();
            $businessId = $queryParams['business_id'] ?? null;

            if ($businessId) {
                try {
                    $customerService = \App\Core\DependencyFactory::getCustomerService();
                    $customer = $customerService->getById($businessId);
                    if ($customer) {
                        \App\Core\TenantContext::set($customer);
                        $_SESSION['business_id'] = $businessId;
                        $_SESSION['customer_id'] = $businessId;
                        $customerId = $businessId;
                    }
                } catch (\Exception $e) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::error('Failed to set tenant context from business_id in MenuController', [
                            'business_id' => $businessId,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            } else {
                // Super admin için business_id yoksa, businesses listesini göster
                // Tenant context set etmeden devam et
            try {
                $customerService = \App\Core\DependencyFactory::getCustomerService();
                $businesses = $customerService->getAllBusinesses();

                    $data = [
                        'title' => 'Menü Yönetimi - Qordy',
                        'menu_items' => [],
                        'categories' => [],
                        'preparation_screens' => [],
                        'is_super_admin' => $isSuperAdmin,
                        'businesses' => $businesses,
                        'message' => 'Lütfen bir işletme seçin',
                        'breadcrumbs' => [
                            ['label' => 'Ana Sayfa', 'url' => BASE_URL . '/qodmin'],
                            ['label' => 'Menü Yönetimi', 'url' => '']
                        ]
                    ];

                    $this->view('admin/menu', $data);
                    return;
                } catch (\Exception $e) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::error('MenuController::index - Failed to load businesses', [
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
        }

        // CRITICAL: Tenant context'i MANUEL olarak set et (business_id varsa)
        if ($customerId && !$isSuperAdmin) {
            try {
                $customerService = \App\Core\DependencyFactory::getCustomerService();
                $customer = $customerService->getById($customerId);
                if ($customer) {
                    \App\Core\TenantContext::set($customer);
                    $_SESSION['business_id'] = $customerId;
                    $_SESSION['customer_id'] = $customerId;
                }
            } catch (\Exception $e) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('MenuController::index - Failed to set tenant context', [
                        'customer_id' => $customerId,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
        
        // Eğer hala tenant context yoksa, ensureTenantContext() çağır
        if (!\App\Core\TenantContext::isSet() && !$isSuperAdmin) {
            $this->ensureTenantContext();
        }

        // Get all preparation screens (including inactive ones like mutfak)
        // Prioritize active screens, but include all for selection
        $preparationScreenService = \App\Core\DependencyFactory::getPreparationScreenService();
        $allScreens = $preparationScreenService->getAllScreens();
        $activeScreens = $preparationScreenService->getActiveScreens();
        
        // Create a map of active screen IDs for quick lookup
        $activeScreenIds = array_column($activeScreens, 'screen_id');
        
        // Sort: active screens first, then inactive, but always include mutfak/kitchen
        $preparationScreens = [];
        $mutfakScreen = null;
        
        foreach ($allScreens as $screen) {
            $screenName = strtolower(trim($screen['name'] ?? ''));
            
            // Check if this is Mutfak screen by name only (screen_id is the real identifier)
            $isMutfak = ($screenName === 'mutfak' || strpos($screenName, 'mutfak') !== false);
            
            if ($isMutfak) {
                $mutfakScreen = $screen;
                continue;
            }
            
            // Add active screens first
            if (in_array($screen['screen_id'] ?? '', $activeScreenIds)) {
                $preparationScreens[] = $screen;
            }
        }
        
        // Add mutfak screen at the beginning if found
        if ($mutfakScreen) {
            array_unshift($preparationScreens, $mutfakScreen);
        }
        
        // Add remaining inactive screens (except mutfak which is already added)
        foreach ($allScreens as $screen) {
            $screenId = $screen['screen_id'] ?? '';
            $screenName = strtolower(trim($screen['name'] ?? ''));
            
            $isMutfak = ($screenName === 'mutfak' || strpos($screenName, 'mutfak') !== false);
            
            if ($isMutfak) {
                continue; // Already added
            }
            
            if (!in_array($screenId, $activeScreenIds)) {
                $preparationScreens[] = $screen;
            }
        }

        // Enrich preparation screens with their category IDs for filtering
        $preparationScreensWithCategories = [];
        foreach ($preparationScreens as $screen) {
            $screenId = $screen['screen_id'] ?? '';
            $categoryIds = $preparationScreenService->getScreenCategoryIds($screenId);
            $screen['category_ids'] = $categoryIds;
            $preparationScreensWithCategories[] = $screen;
        }

        // Get categories and organize them hierarchically
        $allCategories = $this->categoryService->getAllCategories();
        $categoriesHierarchical = $this->organizeCategoriesHierarchically($allCategories);

        // Get business logo for display
        $businessLogoPath = null;
        $businessName = null;
        try {
            $tenantId = \App\Core\TenantContext::getId();
            if ($tenantId) {
                $businessService = \App\Core\DependencyFactory::getBusinessService();
                $businessInfo = $businessService->getBusinessInfo($tenantId);
                $businessLogoPath = $businessInfo['logo_path'] ?? $businessInfo['logo_url'] ?? null;
                $businessName = $businessInfo['company_name'] ?? $businessInfo['business_name'] ?? null;
            }
        } catch (\Exception $e) {
            // Silent fail - use defaults
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::debug('MenuController::index - Failed to load business logo', [
                    'error' => $e->getMessage()
                ]);
            }
        }

        // For all users (including SUPER_ADMIN), get their business data
        // SUPER_ADMIN will see all menu items from their assigned business or all businesses if needed
        $menuItems = $this->menuItemService->getAllMenuItems();
        
        // DEBUG: Log menu items count
        if (class_exists('\App\Core\Logger')) {
            \App\Core\Logger::debug('MenuController::index - Menu items loaded', [
                'count' => count($menuItems),
                'customer_id' => $customerId,
                'business_id' => $businessId,
                'is_super_admin' => $isSuperAdmin,
                'tenant_context_set' => \App\Core\TenantContext::isSet()
            ]);
        }
        
        $data = [
            'title' => 'Menü Yönetimi - Qordy',
            'menu_items' => $menuItems,
            'categories' => $categoriesHierarchical,
            'preparation_screens' => $preparationScreensWithCategories,
            'is_super_admin' => $isSuperAdmin,
            'business_logo_path' => $businessLogoPath,
            'business_name' => $businessName,
            'breadcrumbs' => [
                ['label' => 'Ana Sayfa', 'url' => BASE_URL . '/qodmin'],
                ['label' => 'Menü Yönetimi', 'url' => '']
            ]
        ];

        // For SUPER_ADMIN, also get businesses list for business selection
        if ($isSuperAdmin) {
            try {
                $customerService = \App\Core\DependencyFactory::getCustomerService();
                $data['businesses'] = $customerService->getAllBusinesses();
            } catch (\Exception $e) {
                // If business service fails, set empty array
                $data['businesses'] = [];
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('MenuController::index - Failed to load businesses', [
                        'error' => $e->getMessage()
                    ]);
                }
            }
        } else {
            $data['businesses'] = [];
        }

        $this->view('admin/menu', $data);
    }

    public function add() {
        if (!$this->hasPermission('menu.create')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Check if this is an AJAX/API request
            // If not, we'll redirect after processing (prevents showing raw JSON in browser)
            $isAjaxRequest = \App\Core\RequestTypeDetector::isAPIRequest();
            
            // Handle JSON input - JSON takes priority over POST
            $input = json_decode(file_get_contents('php://input'), true);
            $requestData = is_array($input) ? $input : $_POST;

            $this->bootstrapSuperAdminTenantFromRequest($requestData);

            // CRITICAL: Preprocess form data before validation
            // Convert empty strings to null for optional fields
            if (isset($requestData['image_url']) && $requestData['image_url'] === '') {
                $requestData['image_url'] = null;
            }
            if (isset($requestData['preparation_screen_id']) && $requestData['preparation_screen_id'] === '') {
                $requestData['preparation_screen_id'] = null;
            }
            if (isset($requestData['description']) && $requestData['description'] === '') {
                $requestData['description'] = null;
            }

            // CRITICAL: Convert price to numeric if it's a string
            if (isset($requestData['price'])) {
                if (is_string($requestData['price'])) {
                    $requestData['price'] = trim($requestData['price']);
                    if ($requestData['price'] === '') {
                        $requestData['price'] = null;
                    } else {
                        $requestData['price'] = is_numeric($requestData['price']) ? floatval($requestData['price']) : null;
                    }
                } elseif (!is_numeric($requestData['price'])) {
                    $requestData['price'] = null;
                }
            }

            // CRITICAL: Convert category_id empty string to null
            if (isset($requestData['category_id']) && $requestData['category_id'] === '') {
                $requestData['category_id'] = null;
            }

            // Ensure name field is set from translations if not provided directly
            if (empty($requestData['name']) && !empty($requestData['translations'])) {
                $translations = $requestData['translations'];
                $defaultLanguage = getAppConfig()->getDefaultLanguage();

                // Try to get name from default language translation
                if (isset($translations[$defaultLanguage]['name']) && !empty(trim($translations[$defaultLanguage]['name']))) {
                    $requestData['name'] = trim($translations[$defaultLanguage]['name']);
                } elseif (is_array($translations)) {
                    // Fallback: get name from first available translation
                    foreach ($translations as $lang => $trans) {
                        if (isset($trans['name']) && !empty(trim($trans['name']))) {
                            $requestData['name'] = trim($trans['name']);
                            break;
                        }
                    }
                }
            }

            // CRITICAL: Trim name if it exists
            if (isset($requestData['name']) && is_string($requestData['name'])) {
                $requestData['name'] = trim($requestData['name']);
                if ($requestData['name'] === '') {
                    $requestData['name'] = null;
                }
            }

            // Validate image_url length before validation (increased to 5000 for AI-generated URLs with long BASE_URL)
            if (!empty($requestData['image_url']) && strlen($requestData['image_url']) > 5000) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400, [
                    'errors' => ['image_url' => ['Resim URL en fazla 5000 karakter olabilir.']]
                ]);
                return;
            }

            // Validate request data using ValidationService
            $validationResult = $this->validateRequestData($requestData, 'menu_item');

            if (!$validationResult['valid']) {
                // If not AJAX request, redirect with error message
                if (!$isAjaxRequest) {
                    $_SESSION['flash_message'] = t('notifications.warning.missing_fields', 'Lütfen tüm gerekli alanları doldurun');
                    $_SESSION['flash_type'] = 'error';
                    
                    // Determine redirect URL based on user type
                    $isSuperAdmin = $this->isSuperAdmin();
                    $redirectUrl = $isSuperAdmin ? BASE_URL . '/qodmin/menu' : BASE_URL . '/business/menu';
                    
                    // Preserve business_id query parameter for SuperAdmin
                    if ($isSuperAdmin && isset($_GET['business_id'])) {
                        $redirectUrl .= '?business_id=' . urlencode($_GET['business_id']);
                    }
                    
                    header('Location: ' . $redirectUrl, true, 302);
                    exit;
                }
                
                $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400, [
                    'errors' => $validationResult['errors']
                ]);
                return;
            }

            $validatedData = $validationResult['data'];

            // Get ingredients and extras - ensure they are JSON strings
            $ingredients = $requestData['ingredients'] ?? '[]';
            $availableExtras = $requestData['available_extras'] ?? '[]';
            if (is_array($ingredients)) {
                $ingredients = json_encode($ingredients);
            }
            if (is_array($availableExtras)) {
                // CRITICAL: Validate extras - prevent product from adding itself
                $productName = strtolower(trim($requestData['name'] ?? $validatedData['name'] ?? ''));
                if (!empty($productName)) {
                    $extrasArray = $availableExtras;
                    foreach ($extrasArray as $index => $extra) {
                        if (is_array($extra)) {
                            $extraName = strtolower(trim($extra['name'] ?? ''));
                            if ($extraName === $productName) {
                                // Remove self-reference
                                unset($extrasArray[$index]);
                            }
                        }
                    }
                    $availableExtras = array_values($extrasArray); // Re-index array
                }
                $availableExtras = json_encode($availableExtras);
            }

            // CRITICAL: Extract category_id from requestData or validatedData (for CREATE)
            // Prefer requestData to ensure user's explicit selection
            $categoryIdForProcessing = $requestData['category_id'] ?? $validatedData['category_id'] ?? null;
            if ($categoryIdForProcessing === '') {
                $categoryIdForProcessing = null;
            }

            // CRITICAL: Check if category requires service (Servise gerek yok)
            // Get the deepest (leaf) category in the hierarchy
            // If deepest category has default_production_point = 'NONE' or requires_kitchen = 0, no preparation screen is needed
            $requiresService = true;
            $deepestCategory = null;
            if (!empty($categoryIdForProcessing)) {
                $deepestCategory = $this->categoryService->getDeepestCategory($categoryIdForProcessing);
                if ($deepestCategory) {
                    $defaultProductionPoint = $deepestCategory['default_production_point'] ?? '';
                    $requiresKitchen = isset($deepestCategory['requires_kitchen']) ? (int)$deepestCategory['requires_kitchen'] : 1;
                    // Servise gerek yok: default_production_point = 'NONE' veya requires_kitchen = 0
                    if ($defaultProductionPoint === 'NONE' || $requiresKitchen === 0) {
                    $requiresService = false;
                    }
                }
            }

            // Get preparation_screen_id from input (new dynamic system)
            // CRITICAL: If category doesn't require service, don't set preparation screen
            $preparationScreenId = null;
            if ($requiresService) {
                $preparationScreenId = $requestData['preparation_screen_id'] ?? null;
                if ($preparationScreenId === '') {
                    $preparationScreenId = null;
                }
                
                // AUTO-ASSIGN preparation screen based on category if not explicitly selected
                if (empty($preparationScreenId)) {
                    $preparationScreenId = $this->determinePreparationScreenByCategory($categoryIdForProcessing ?? $categoryId ?? null, $deepestCategory ?? null);
                }
                
                // Final fallback: assign to Mutfak (kitchen) if still empty
                if (empty($preparationScreenId)) {
                    $preparationScreenId = $this->getDefaultPreparationScreenId();
                }
            }

            // Get production_point from input, or use category default (for backward compatibility)
            $productionPoint = $requestData['production_point'] ?? null;

            // Convert empty string to null (ENUM columns don't accept empty strings)
            if ($productionPoint === '') {
                $productionPoint = null;
            }

            // If production_point not provided, get from deepest category default
            if (empty($productionPoint) && $deepestCategory && !empty($deepestCategory['default_production_point'])) {
                $productionPoint = $deepestCategory['default_production_point'];
            } elseif (empty($productionPoint) && !empty($categoryIdForProcessing)) {
                // Fallback to direct category if deepest not found
                $category = $this->categoryService->getCategoryById($categoryIdForProcessing);
                if ($category && !empty($category['default_production_point'])) {
                    $productionPoint = $category['default_production_point'];
                }
            }

            // Validate production_point - ensure it's a valid ENUM value or null
            $validProductionPoints = ConstantsHelper::getProductionPoints();
            if (!empty($productionPoint) && !in_array($productionPoint, $validProductionPoints)) {
                $productionPoint = null; // Invalid value, will use category default or null
            }

            // Ensure null is used instead of empty string for database
            if ($productionPoint === '') {
                $productionPoint = null;
            }

            // Get variant-related data
            $hasVariants = isset($requestData['has_variants']) ? (int)$requestData['has_variants'] : 0;
            $variants = $requestData['variants'] ?? [];
            $isDirectService = isset($requestData['is_direct_service']) ? (int)$requestData['is_direct_service'] : 0;

            // Handle stock tracking
            $trackStock = isset($requestData['track_stock']) ? (int)$requestData['track_stock'] : 0;
            
            // Handle stock - get directly from request (stock_quantity or stock field)
            if ($trackStock) {
                $stockQuantity = isset($requestData['stock_quantity']) ? intval($requestData['stock_quantity']) : 0;
            } else {
                $stockQuantity = isset($requestData['stock']) ? intval($requestData['stock']) : 999;
            }

            // Low stock threshold (optional) - only honored when track_stock is enabled
            $lowStockThreshold = 0;
            if ($trackStock && isset($requestData['low_stock_threshold']) && $requestData['low_stock_threshold'] !== '') {
                $lowStockThreshold = max(0, intval($requestData['low_stock_threshold']));
            }

            // SuperAdmin için business_id kontrolü
            $businessId = null;
            if ($this->isSuperAdmin()) {
                // Önce request'ten al
                $businessId = $requestData['business_id'] ?? null;

                // Eğer yoksa session'dan al
                if (empty($businessId)) {
                    $businessId = $_SESSION['business_id'] ?? null;
                }

                // Eğer hala yoksa TenantContext'ten al
                if (empty($businessId)) {
                    try {
                        $businessId = \App\Core\TenantContext::getId();
                    } catch (\Exception $e) {
                        // TenantContext'te yoksa hata fırlat
                        if (class_exists('\App\Core\Logger')) {
                            \App\Core\Logger::error('MenuController::add - Business ID required for SuperAdmin', [
                                'error' => $e->getMessage()
                            ]);
                        }
                        
                        // If not AJAX request, redirect with error message
                        if (!$isAjaxRequest) {
                            $_SESSION['flash_message'] = 'Business ID required for menu item creation';
                            $_SESSION['flash_type'] = 'error';
                            
                            $redirectUrl = BASE_URL . '/qodmin/menu';
                            if (isset($_GET['business_id'])) {
                                $redirectUrl .= '?business_id=' . urlencode($_GET['business_id']);
                            }
                            
                            header('Location: ' . $redirectUrl, true, 302);
                            exit;
                        }
                        
                        $this->toastNotificationService->sendApiResponse('error', 'Business ID required for menu item creation', [], 400);
                        return;
                    }
                }

                if (empty($businessId)) {
                    // If not AJAX request, redirect with error message
                    if (!$isAjaxRequest) {
                        $_SESSION['flash_message'] = 'Business ID required for menu item creation';
                        $_SESSION['flash_type'] = 'error';
                        
                        $redirectUrl = BASE_URL . '/qodmin/menu';
                        if (isset($_GET['business_id'])) {
                            $redirectUrl .= '?business_id=' . urlencode($_GET['business_id']);
                        }
                        
                        header('Location: ' . $redirectUrl, true, 302);
                        exit;
                    }
                    
                    $this->toastNotificationService->sendApiResponse('error', 'Business ID required for menu item creation', [], 400);
                    return;
                }
            }

            // Merge validatedData and requestData (validatedData takes priority for validated fields)
            // This ensures all fields are included, not just those in validation rules
            $mergedData = array_merge($requestData, $validatedData);
            
            // Process description - convert empty string to null
            $description = isset($mergedData['description']) && $mergedData['description'] !== null ? trim($mergedData['description']) : null;
            if ($description === '') {
                $description = null;
            }
            
            // Process image_url - convert empty string to null
            $imageUrl = isset($mergedData['image_url']) && $mergedData['image_url'] !== null ? trim($mergedData['image_url']) : null;
            if ($imageUrl === '') {
                $imageUrl = null;
            }
            
            $menuData = [
                'menu_item_id' => generateId('mi'),
                'name' => trim($mergedData['name'] ?? ''),
                'description' => $description,
                'price' => floatval($mergedData['price'] ?? 0),
                'category_id' => $categoryIdForProcessing,
                'preparation_screen_id' => $preparationScreenId,
                'production_point' => $productionPoint,
                'image_url' => $imageUrl,
                'is_available' => array_key_exists('is_available', $requestData)
                    ? (int)$requestData['is_available']
                    : 1,
                'track_stock' => $trackStock,
                'stock' => $stockQuantity,
                'low_stock_threshold' => $lowStockThreshold,
                'ingredients' => $ingredients,
                'available_extras' => $availableExtras,
                'has_variants' => $hasVariants,
                'is_direct_service' => $isDirectService
            ];

            // CRITICAL: Ensure tenant_id is set for tenant isolation
            // For SuperAdmin: use provided business_id
            // For regular users: ensure tenant context is set and use it
            $this->ensureTenantContext();
            $tenantId = \App\Core\TenantContext::getId();

            if ($this->isSuperAdmin() && $businessId) {
                $menuData['tenant_id'] = $businessId;
            } else {
                if ($tenantId) {
                    $menuData['tenant_id'] = $tenantId;
                } else {
                    // No tenant context - this should not happen for regular users
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::error('MenuController::add - No tenant context for regular user', [
                            'user_id' => $_SESSION['user_id'] ?? 'unknown'
                        ]);
                    }
                    
                    // If not AJAX request, redirect with error message
                    if (!$isAjaxRequest) {
                        $_SESSION['flash_message'] = 'Tenant context required';
                        $_SESSION['flash_type'] = 'error';
                        
                        // Determine redirect URL based on user type
                        $isSuperAdmin = $this->isSuperAdmin();
                        $redirectUrl = $isSuperAdmin ? BASE_URL . '/qodmin/menu' : BASE_URL . '/business/menu';
                        
                        // Preserve business_id query parameter for SuperAdmin
                        if ($isSuperAdmin && isset($_GET['business_id'])) {
                            $redirectUrl .= '?business_id=' . urlencode($_GET['business_id']);
                        }
                        
                        header('Location: ' . $redirectUrl, true, 302);
                        exit;
                    }
                    
                    $this->toastNotificationService->sendApiResponse('error', 'Tenant context required', [], 400);
                    return;
                }
            }

            // CRITICAL: Verify category belongs to current tenant
            if (!empty($menuData['category_id'])) {
                $category = $this->categoryService->getCategoryById($menuData['category_id']);
                if (!$category) {
                    // If not AJAX request, redirect with error message
                    if (!$isAjaxRequest) {
                        $_SESSION['flash_message'] = t('notifications.error.category_not_found', 'Kategori bulunamadı');
                        $_SESSION['flash_type'] = 'error';
                        
                        // Determine redirect URL based on user type
                        $isSuperAdmin = $this->isSuperAdmin();
                        $redirectUrl = $isSuperAdmin ? BASE_URL . '/qodmin/menu' : BASE_URL . '/business/menu';
                        
                        // Preserve business_id query parameter for SuperAdmin
                        if ($isSuperAdmin && isset($_GET['business_id'])) {
                            $redirectUrl .= '?business_id=' . urlencode($_GET['business_id']);
                        }
                        
                        header('Location: ' . $redirectUrl, true, 302);
                        exit;
                    }
                    
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.category_not_found', [], 404);
                    return;
                }

                if (!$this->isSuperAdmin()) {
                    $categoryBusinessId = $category['tenant_id'] ?? null;
                    if (!$tenantId || $categoryBusinessId !== $tenantId) {
                        \App\Core\Logger::warning('MenuController::add - Category tenant isolation violation', [
                            'category_id' => $menuData['category_id'],
                            'category_business_id' => $categoryBusinessId,
                            'tenant_id' => $tenantId
                        ]);
                        
                        // If not AJAX request, redirect with error message
                        if (!$isAjaxRequest) {
                            $_SESSION['flash_message'] = t('notifications.error.unauthorized', 'Bu işlemi yapmaya yetkiniz yok');
                            $_SESSION['flash_type'] = 'error';
                            
                            // Determine redirect URL based on user type
                            $isSuperAdmin = $this->isSuperAdmin();
                            $redirectUrl = $isSuperAdmin ? BASE_URL . '/qodmin/menu' : BASE_URL . '/business/menu';
                            
                            // Preserve business_id query parameter for SuperAdmin
                            if ($isSuperAdmin && isset($_GET['business_id'])) {
                                $redirectUrl .= '?business_id=' . urlencode($_GET['business_id']);
                            }
                            
                            header('Location: ' . $redirectUrl, true, 302);
                            exit;
                        }
                        
                        $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
                        return;
                    }
                }
            }

            // CRITICAL DEBUG: Log before creation attempt
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::info('MenuController::add - About to create menu item', [
                    'menu_item_id' => $menuData['menu_item_id'],
                    'name' => $menuData['name'],
                    'price' => $menuData['price'],
                    'category_id' => $menuData['category_id']
                ]);
            }

            try {
                $result = $this->menuItemService->createMenuItem($menuData);

                // CRITICAL DEBUG: Log result
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::info('MenuController::add - createMenuItem result', [
                        'result' => $result,
                        'result_type' => gettype($result),
                        'result_is_string' => is_string($result)
                    ]);
                }

                if ($result) {
                    $menuItemId = is_string($result) ? $result : $menuData['menu_item_id'];

                    // Create variants if product has variants
                    if ($hasVariants && !empty($variants) && is_array($variants)) {
                        try {
                            if (class_exists('\App\Core\Logger')) {
                                \App\Core\Logger::info('MenuController::add - Creating variants', [
                                    'menu_item_id' => $menuItemId,
                                    'variants_count' => count($variants),
                                    'variants' => $variants
                                ]);
                            }
                            $productVariantService = \App\Core\DependencyFactory::getProductVariantService();
                            $variantResult = $productVariantService->createVariants($menuItemId, $variants);
                            if (class_exists('\App\Core\Logger')) {
                                \App\Core\Logger::info('MenuController::add - Variants created successfully', [
                                    'menu_item_id' => $menuItemId,
                                    'result' => $variantResult
                                ]);
                            }
                        } catch (\Exception $e) {
                            // Log variant creation error but don't fail the whole operation
                            if (class_exists('\App\Core\Logger')) {
                                \App\Core\Logger::error('MenuController::add - Variant creation failed', [
                                    'menu_item_id' => $menuItemId,
                                    'variants' => $variants,
                                    'error' => $e->getMessage(),
                                    'trace' => $e->getTraceAsString()
                                ]);
                            }
                        }
                    } else {
                        if (class_exists('\App\Core\Logger')) {
                            \App\Core\Logger::debug('MenuController::add - No variants to create', [
                                'hasVariants' => $hasVariants,
                                'variants_empty' => empty($variants),
                                'variants_is_array' => is_array($variants),
                                'variants' => $variants
                            ]);
                        }
                    }

                    // Save translations if provided (from JSON input or POST)
                    $translations = null;
                    if (is_array($input) && isset($input['translations'])) {
                        $translations = $input['translations'];
                    } elseif (isset($requestData['translations'])) {
                        $translations = $requestData['translations'];
                    }
                    if ($translations && is_array($translations)) {
                        try {
                            require_once __DIR__ . '/../core/DependencyFactory.php';
                            $translationService = \App\Core\DependencyFactory::getMenuItemTranslationService();
                            $translationService->saveTranslations($menuItemId, $translations);
                        } catch (\Exception $e) {
                            // Log translation save error but don't fail the whole operation
                            if (class_exists('\App\Core\Logger')) {
                                \App\Core\Logger::error('MenuController::add - Translation save failed', [
                                    'menu_item_id' => $menuItemId,
                                    'error' => $e->getMessage()
                                ]);
                            }
                        }
                    }

                    // CRITICAL DEBUG: Success path
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::info('MenuController::add - SUCCESS - Sending success response', [
                            'menu_item_id' => $menuItemId
                        ]);
                    }

                    // If not AJAX request, redirect after successful creation
                    if (!$isAjaxRequest) {
                        $_SESSION['flash_message'] = t('notifications.success.menu_item_created', 'Ürün başarıyla oluşturuldu');
                        $_SESSION['flash_type'] = 'success';
                        
                        // Determine redirect URL based on user type
                        $isSuperAdmin = $this->isSuperAdmin();
                        $redirectUrl = $isSuperAdmin ? BASE_URL . '/qodmin/menu' : BASE_URL . '/business/menu';
                        
                        // Preserve business_id query parameter for SuperAdmin
                        if ($isSuperAdmin && isset($_GET['business_id'])) {
                            $redirectUrl .= '?business_id=' . urlencode($_GET['business_id']);
                        }
                        
                        header('Location: ' . $redirectUrl, true, 302);
                        exit;
                    }

                    // CACHE INVALIDATION: Clear menu cache after creating new item
                    $tenantId = \App\Core\TenantContext::getId();
                    if ($tenantId && class_exists('\App\Helpers\CacheHelper')) {
                        \App\Helpers\CacheHelper::clearMenuCache($tenantId);
                    }
                    
                    // Include menu_item_id in response for frontend to use (e.g., for image upload)
                    $this->toastNotificationService->sendApiResponse('success', 'notifications.success.menu_item_created', [], 200, [
                        'success' => true,
                        'message' => t('notifications.success.menu_item_created', 'Ürün başarıyla oluşturuldu'),
                        'menu_item_id' => $menuItemId
                    ]);
                } else {
                    // CRITICAL DEBUG: Failure path
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::error('MenuController::add - FAILURE - createMenuItem returned false', [
                            'menu_data' => $menuData
                        ]);
                    }

                    // If not AJAX request, redirect with error message
                    if (!$isAjaxRequest) {
                        $_SESSION['flash_message'] = t('notifications.error.create_failed', 'Ürün oluşturulamadı');
                        $_SESSION['flash_type'] = 'error';
                        
                        // Determine redirect URL based on user type
                        $isSuperAdmin = $this->isSuperAdmin();
                        $redirectUrl = $isSuperAdmin ? BASE_URL . '/qodmin/menu' : BASE_URL . '/business/menu';
                        
                        // Preserve business_id query parameter for SuperAdmin
                        if ($isSuperAdmin && isset($_GET['business_id'])) {
                            $redirectUrl .= '?business_id=' . urlencode($_GET['business_id']);
                        }
                        
                        header('Location: ' . $redirectUrl, true, 302);
                        exit;
                    }

                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.create_failed', [], 500);
                }
            } catch (\InvalidArgumentException $e) {
                // Validation error - extract error details
                $errorMessage = $e->getMessage();
                $errors = [];

                // Try to parse JSON error message
                if (preg_match('/\{.*\}/', $errorMessage, $matches)) {
                    $parsedErrors = json_decode($matches[0], true);
                    if (is_array($parsedErrors)) {
                        $errors = $parsedErrors;
                    }
                }

                // If no parsed errors, create a generic error
                if (empty($errors)) {
                    $errors = ['general' => ['Menü öğesi oluşturulamadı. Lütfen tüm gerekli alanları kontrol edin.']];
                }

                // If not AJAX request, redirect with error message
                if (!$isAjaxRequest) {
                    $_SESSION['flash_message'] = 'Menü öğesi oluşturulamadı. Lütfen tüm gerekli alanları kontrol edin.';
                    $_SESSION['flash_type'] = 'error';
                    
                    // Determine redirect URL based on user type
                    $isSuperAdmin = $this->isSuperAdmin();
                    $redirectUrl = $isSuperAdmin ? BASE_URL . '/qodmin/menu' : BASE_URL . '/business/menu';
                    
                    // Preserve business_id query parameter for SuperAdmin
                    if ($isSuperAdmin && isset($_GET['business_id'])) {
                        $redirectUrl .= '?business_id=' . urlencode($_GET['business_id']);
                    }
                    
                    header('Location: ' . $redirectUrl, true, 302);
                    exit;
                }

                $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400, [
                    'errors' => $errors
                ]);
            } catch (\RuntimeException $e) {
                // Database error
                $errorMessage = $e->getMessage();
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('MenuController::add - Database error', [
                        'error' => $errorMessage,
                        'trace' => $e->getTraceAsString()
                    ]);
                }

                // If not AJAX request, redirect with error message
                if (!$isAjaxRequest) {
                    $_SESSION['flash_message'] = 'Veritabanı hatası: ' . $errorMessage;
                    $_SESSION['flash_type'] = 'error';
                    
                    // Determine redirect URL based on user type
                    $isSuperAdmin = $this->isSuperAdmin();
                    $redirectUrl = $isSuperAdmin ? BASE_URL . '/qodmin/menu' : BASE_URL . '/business/menu';
                    
                    // Preserve business_id query parameter for SuperAdmin
                    if ($isSuperAdmin && isset($_GET['business_id'])) {
                        $redirectUrl .= '?business_id=' . urlencode($_GET['business_id']);
                    }
                    
                    header('Location: ' . $redirectUrl, true, 302);
                    exit;
                }

                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.create_failed', [], 500, [
                    'errors' => ['general' => [$errorMessage]]
                ]);
            } catch (\Exception $e) {
                // Other errors
                $errorMessage = $e->getMessage();
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('MenuController::add - Unexpected error', [
                        'error' => $errorMessage,
                        'type' => get_class($e),
                        'trace' => $e->getTraceAsString()
                    ]);
                }

                // If not AJAX request, redirect with error message
                if (!$isAjaxRequest) {
                    $_SESSION['flash_message'] = 'Beklenmeyen bir hata oluştu. Lütfen tekrar deneyin.';
                    $_SESSION['flash_type'] = 'error';
                    
                    // Determine redirect URL based on user type
                    $isSuperAdmin = $this->isSuperAdmin();
                    $redirectUrl = $isSuperAdmin ? BASE_URL . '/qodmin/menu' : BASE_URL . '/business/menu';
                    
                    // Preserve business_id query parameter for SuperAdmin
                    if ($isSuperAdmin && isset($_GET['business_id'])) {
                        $redirectUrl .= '?business_id=' . urlencode($_GET['business_id']);
                    }
                    
                    header('Location: ' . $redirectUrl, true, 302);
                    exit;
                }

                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.create_failed', [], 500, [
                    'errors' => ['general' => ['Beklenmeyen bir hata oluştu. Lütfen tekrar deneyin.']]
                ]);
            }
        }
    }

    public function edit() {
        if (!$this->hasPermission('menu.edit')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }

        $queryParams = \App\Core\RequestParser::getQueryParams();
        $menuItemId = $queryParams['id'] ?? '';
        if (empty($menuItemId)) {
            // Try to get from URL path
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
            if (preg_match('/edit\/([^\/]+)/', $path, $matches)) {
                $menuItemId = $matches[1];
            }
        }

        // GET request - return menu item with translations for editing
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            if (empty($menuItemId)) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
                return;
            }

            // CRITICAL: Verify tenant isolation before allowing edit
            $this->ensureTenantContext();
            $menuItem = $this->menuItemService->getMenuItemById($menuItemId);
            if (!$menuItem) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.menu_item_not_found', [], 404);
                return;
            }

            // Check tenant isolation (unless super admin)
            // menu_items table uses tenant_id column; fall back to business_id for legacy rows
            if (!$this->isSuperAdmin()) {
                $tenantId = \App\Core\TenantContext::getId();
                $itemTenantId = $menuItem['tenant_id'] ?? $menuItem['business_id'] ?? null;

                if (!$tenantId || (string)$itemTenantId !== (string)$tenantId) {
                    \App\Core\Logger::warning('MenuController::edit - Tenant isolation violation', [
                        'menu_item_id' => $menuItemId,
                        'item_tenant_id' => $itemTenantId,
                        'tenant_id' => $tenantId
                    ]);
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
                    return;
                }
            }

            // Get variants if product has variants
            if (!empty($menuItem['has_variants']) && $menuItem['has_variants'] == 1) {
                $productVariantService = \App\Core\DependencyFactory::getProductVariantService();
                $menuItem['variants'] = $productVariantService->getVariantsByProduct($menuItemId);
            } else {
                $menuItem['variants'] = [];
            }

            // Get translations
            $translationService = \App\Core\DependencyFactory::getMenuItemTranslationService();
            $translations = $translationService->getTranslationsForEdit($menuItemId);

            $menuItem['translations'] = $translations;

            $this->apiResponse($menuItem);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $isAjaxRequest = \App\Core\RequestTypeDetector::isAPIRequest();
            
            // Handle JSON input - JSON takes priority over POST
            $input = json_decode(file_get_contents('php://input'), true);
            $requestData = is_array($input) ? $input : $_POST;

            $this->bootstrapSuperAdminTenantFromRequest($requestData);

            // CRITICAL: Preprocess form data before validation (same as add method)
            // Convert empty strings to null for optional fields
            if (isset($requestData['image_url']) && $requestData['image_url'] === '') {
                $requestData['image_url'] = null;
            }
            if (isset($requestData['preparation_screen_id']) && $requestData['preparation_screen_id'] === '') {
                $requestData['preparation_screen_id'] = null;
            }
            if (isset($requestData['description']) && $requestData['description'] === '') {
                $requestData['description'] = null;
            }

            // CRITICAL: Convert price to numeric if it's a string
            if (isset($requestData['price'])) {
                if (is_string($requestData['price'])) {
                    $requestData['price'] = trim($requestData['price']);
                    if ($requestData['price'] === '') {
                        $requestData['price'] = null;
                    } else {
                        $requestData['price'] = is_numeric($requestData['price']) ? floatval($requestData['price']) : null;
                    }
                } elseif (!is_numeric($requestData['price'])) {
                    $requestData['price'] = null;
                }
            }

            // CRITICAL: Convert category_id empty string to null
            if (isset($requestData['category_id']) && $requestData['category_id'] === '') {
                $requestData['category_id'] = null;
            }

            // Ensure name field is set from translations if not provided directly
            if (empty($requestData['name']) && !empty($requestData['translations'])) {
                $translations = $requestData['translations'];
                $defaultLanguage = getAppConfig()->getDefaultLanguage();

                // Try to get name from default language translation
                if (isset($translations[$defaultLanguage]['name']) && !empty(trim($translations[$defaultLanguage]['name']))) {
                    $requestData['name'] = trim($translations[$defaultLanguage]['name']);
                } elseif (is_array($translations)) {
                    // Fallback: get name from first available translation
                    foreach ($translations as $lang => $trans) {
                        if (isset($trans['name']) && !empty(trim($trans['name']))) {
                            $requestData['name'] = trim($trans['name']);
                            break;
                        }
                    }
                }
            }

            // CRITICAL: Trim name if it exists
            if (isset($requestData['name']) && is_string($requestData['name'])) {
                $requestData['name'] = trim($requestData['name']);
                if ($requestData['name'] === '') {
                    $requestData['name'] = null;
                }
            }

            // Validate image_url length before validation (increased to 5000 for AI-generated URLs with long BASE_URL)
            if (!empty($requestData['image_url']) && strlen($requestData['image_url']) > 5000) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400, [
                    'errors' => ['image_url' => ['Resim URL en fazla 5000 karakter olabilir.']]
                ]);
                return;
            }

            // Validate request data using ValidationService
            // Use 'menu_item_edit' rule set for edit (category_id is optional)
            $validationResult = $this->validateRequestData($requestData, 'menu_item_edit');

            if (!$validationResult['valid']) {
                // CRITICAL: Build detailed error messages for user
                $errorMessages = [];
                $errors = $validationResult['errors'] ?? [];

                // Extract field-specific error messages
                foreach ($errors as $field => $messages) {
                    if (is_array($messages)) {
                        foreach ($messages as $message) {
                            $errorMessages[] = $message;
                        }
                    } elseif (is_string($messages)) {
                        $errorMessages[] = $messages;
                    }
                }

                // If no specific errors, provide generic message with field hints
                if (empty($errorMessages)) {
                    $missingFields = [];
                    if (empty($requestData['name']) || (is_string($requestData['name']) && trim($requestData['name']) === '')) {
                        $missingFields[] = 'Ürün adı';
                    }
                    // Note: category_id is optional for edit, so don't require it
                    if (empty($requestData['price']) || !is_numeric($requestData['price']) || floatval($requestData['price']) <= 0) {
                        $missingFields[] = 'Fiyat';
                    }

                    if (!empty($missingFields)) {
                        $errorMessages[] = 'Lütfen şu alanları doldurun: ' . implode(', ', $missingFields);
                    } else {
                        $errorMessages[] = 'Lütfen tüm gerekli alanları doldurun.';
                    }
                }

                $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400, [
                    'errors' => $validationResult['errors'],
                    'error_messages' => $errorMessages
                ]);
                return;
            }

            $validatedData = $validationResult['data'];

            // CRITICAL: Extract category_id from requestData FIRST (before using it)
            // This ensures we always use the user's explicit selection, not sanitized validation data
            if (isset($requestData['category_id'])) {
                $categoryIdFromRequest = $requestData['category_id'];
                // Convert empty string to null, but keep valid IDs as-is
                if ($categoryIdFromRequest === '' || $categoryIdFromRequest === null) {
                    $categoryIdForProcessing = null;
                } else {
                    $categoryIdForProcessing = trim((string)$categoryIdFromRequest);
                }
            } else {
                // If category_id not in request, we'll use existing value later
                $categoryIdForProcessing = null;
            }

            // Get ingredients and extras - ensure they are JSON strings
            $ingredients = $requestData['ingredients'] ?? '[]';
            $availableExtras = $requestData['available_extras'] ?? '[]';
            if (is_array($ingredients)) {
                $ingredients = json_encode($ingredients);
            }
            if (is_array($availableExtras)) {
                // CRITICAL: Validate extras - prevent product from adding itself
                $productName = strtolower(trim($requestData['name'] ?? $validatedData['name'] ?? $existingMenuItem['name'] ?? ''));
                if (!empty($productName)) {
                    $extrasArray = $availableExtras;
                    foreach ($extrasArray as $index => $extra) {
                        if (is_array($extra)) {
                            $extraName = strtolower(trim($extra['name'] ?? ''));
                            if ($extraName === $productName) {
                                // Remove self-reference
                                unset($extrasArray[$index]);
                            }
                        }
                    }
                    $availableExtras = array_values($extrasArray); // Re-index array
                }
                $availableExtras = json_encode($availableExtras);
            }

            // CRITICAL: Check if category requires service (Servise gerek yok)
            // Get the deepest (leaf) category in the hierarchy
            // If deepest category has default_production_point = 'NONE' or requires_kitchen = 0, no preparation screen is needed
            $requiresService = true;
            $deepestCategory = null;
            if (!empty($categoryIdForProcessing)) {
                $deepestCategory = $this->categoryService->getDeepestCategory($categoryIdForProcessing);
                if ($deepestCategory) {
                    $defaultProductionPoint = $deepestCategory['default_production_point'] ?? '';
                    $requiresKitchen = isset($deepestCategory['requires_kitchen']) ? (int)$deepestCategory['requires_kitchen'] : 1;
                    // Servise gerek yok: default_production_point = 'NONE' veya requires_kitchen = 0
                    if ($defaultProductionPoint === 'NONE' || $requiresKitchen === 0) {
                    $requiresService = false;
                    }
                }
            }

            // Get preparation_screen_id from input (new dynamic system)
            // CRITICAL: If category doesn't require service, don't set preparation screen
            $preparationScreenId = null;
            if ($requiresService) {
                $preparationScreenId = $requestData['preparation_screen_id'] ?? null;
                if ($preparationScreenId === '') {
                    $preparationScreenId = null;
                }
                
                // AUTO-ASSIGN preparation screen based on category if not explicitly selected
                if (empty($preparationScreenId)) {
                    $preparationScreenId = $this->determinePreparationScreenByCategory($categoryIdForProcessing ?? $categoryId ?? null, $deepestCategory ?? null);
                }
                
                // Final fallback: assign to Mutfak (kitchen) if still empty
                if (empty($preparationScreenId)) {
                    $preparationScreenId = $this->getDefaultPreparationScreenId();
                }
            }

            // Get production_point from input, or use category default (for backward compatibility)
            $productionPoint = $requestData['production_point'] ?? null;

            // Convert empty string to null (ENUM columns don't accept empty strings)
            if ($productionPoint === '') {
                $productionPoint = null;
            }

            // If production_point not provided, get from deepest category default
            if (empty($productionPoint) && $deepestCategory && !empty($deepestCategory['default_production_point'])) {
                $productionPoint = $deepestCategory['default_production_point'];
            } elseif (empty($productionPoint) && !empty($categoryIdForProcessing)) {
                // Fallback to direct category if deepest not found
                $category = $this->categoryService->getCategoryById($categoryIdForProcessing);
                if ($category && !empty($category['default_production_point'])) {
                    $productionPoint = $category['default_production_point'];
                }
            }

            // Validate production_point - ensure it's a valid ENUM value or null
            $validProductionPoints = ConstantsHelper::getProductionPoints();
            if (!empty($productionPoint) && !in_array($productionPoint, $validProductionPoints)) {
                $productionPoint = null; // Invalid value, will use category default or null
            }

            // Ensure null is used instead of empty string for database
            if ($productionPoint === '') {
                $productionPoint = null;
            }

            // Get variant-related data
            $hasVariants = isset($requestData['has_variants']) ? (int)$requestData['has_variants'] : 0;
            $variants = $requestData['variants'] ?? [];
            $isDirectService = isset($requestData['is_direct_service']) ? (int)$requestData['is_direct_service'] : 0;

            // Handle stock tracking
            $trackStock = isset($requestData['track_stock']) ? (int)$requestData['track_stock'] : 0;
            
            // Handle stock - get directly from request (stock_quantity or stock field)
            if ($trackStock) {
                $stockQuantity = isset($requestData['stock_quantity']) ? intval($requestData['stock_quantity']) : 0;
            } else {
                $stockQuantity = isset($requestData['stock']) ? intval($requestData['stock']) : 999;
            }

            // Low stock threshold (optional) - only kept when stock tracking is enabled
            $lowStockThreshold = 0;
            if ($trackStock && isset($requestData['low_stock_threshold']) && $requestData['low_stock_threshold'] !== '') {
                $lowStockThreshold = max(0, intval($requestData['low_stock_threshold']));
            }

            // CRITICAL: Verify tenant isolation before update
            $this->ensureTenantContext();
            $existingMenuItem = $this->menuItemService->getMenuItemById($menuItemId);
            if (!$existingMenuItem) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.menu_item_not_found', [], 404);
                return;
            }

            // Check tenant isolation (unless super admin)
            if (!$this->isSuperAdmin()) {
                $tenantId = \App\Core\TenantContext::getId();
                $itemBusinessId = $existingMenuItem['tenant_id'] ?? null;

                if (!$tenantId || $itemBusinessId !== $tenantId) {
                    \App\Core\Logger::warning('MenuController::edit - Tenant isolation violation', [
                        'menu_item_id' => $menuItemId,
                        'item_business_id' => $itemBusinessId,
                        'tenant_id' => $tenantId
                    ]);
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
                    return;
                }
            }

            // CRITICAL: category_id MUST come from requestData (user's explicit selection)
            // Don't use validatedData for category_id as it may be sanitized or missing
            $mergedData = array_merge($requestData, $validatedData);
            
            // ALWAYS use requestData['category_id'] directly (can be null, empty string, or valid ID)
            if (isset($requestData['category_id'])) {
                $categoryIdFromRequest = $requestData['category_id'];
                // Convert empty string to null, but keep valid IDs as-is
                if ($categoryIdFromRequest === '' || $categoryIdFromRequest === null) {
                    $categoryId = null;
                    $mergedData['category_id'] = null;
                } else {
                    $categoryId = trim((string)$categoryIdFromRequest);
                    $mergedData['category_id'] = $categoryId;
                }
            } else {
                // If category_id not in request, keep existing value (don't change)
                $categoryId = $existingMenuItem['category_id'] ?? null;
                $mergedData['category_id'] = $categoryId;
            }
            
            // Process description - convert empty string to null
            $description = isset($mergedData['description']) && $mergedData['description'] !== null ? trim($mergedData['description']) : null;
            if ($description === '') {
                $description = null;
            }
            
            // Process image_url - convert empty string to null
            $imageUrl = isset($mergedData['image_url']) && $mergedData['image_url'] !== null ? trim($mergedData['image_url']) : null;
            if ($imageUrl === '') {
                $imageUrl = null;
            }
            
            // Log category_id processing for debugging
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::debug('MenuController::edit - category_id processing', [
                    'requestData_category_id' => $requestData['category_id'] ?? 'not_set',
                    'validatedData_category_id' => $validatedData['category_id'] ?? 'not_set',
                    'mergedData_category_id' => $mergedData['category_id'] ?? 'not_set',
                    'final_categoryId' => $categoryId
                ]);
            }
            
            $menuData = [
                'name' => trim($mergedData['name'] ?? ''),
                'description' => $description,
                'price' => floatval($mergedData['price'] ?? 0),
                'category_id' => $categoryId,
                'preparation_screen_id' => $preparationScreenId,
                'production_point' => $productionPoint,
                'image_url' => $imageUrl,
                'is_available' => array_key_exists('is_available', $requestData)
                    ? (int)$requestData['is_available']
                    : (int)($existingMenuItem['is_available'] ?? 1),
                'track_stock' => $trackStock,
                'stock' => $stockQuantity,
                'low_stock_threshold' => $lowStockThreshold,
                'ingredients' => $ingredients,
                'available_extras' => $availableExtras,
                'has_variants' => $hasVariants,
                'is_direct_service' => $isDirectService
            ];
            
            // CRITICAL: Verify category belongs to current tenant (if category changed)
            // This check MUST be AFTER menuData is created
            if (!empty($menuData['category_id']) && $menuData['category_id'] !== ($existingMenuItem['category_id'] ?? null)) {
                $category = $this->categoryService->getCategoryById($menuData['category_id']);
                if (!$category) {
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.category_not_found', [], 404);
                    return;
                }

                if (!$this->isSuperAdmin()) {
                    $tenantId = \App\Core\TenantContext::getId();
                    $categoryBusinessId = $category['tenant_id'] ?? null;
                    if (!$tenantId || $categoryBusinessId !== $tenantId) {
                        \App\Core\Logger::warning('MenuController::edit - Category tenant isolation violation', [
                            'category_id' => $menuData['category_id'],
                            'category_business_id' => $categoryBusinessId,
                            'tenant_id' => $tenantId
                        ]);
                        $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
                        return;
                    }
                }
            }

            // CRITICAL: Log category_id BEFORE update for debugging
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::debug('MenuController::edit - Updating menu item with category_id', [
                    'menu_item_id' => $menuItemId,
                    'requestData_category_id' => $requestData['category_id'] ?? 'not_set',
                    'category_id' => $categoryId,
                    'original_category_id' => $existingMenuItem['category_id'] ?? null,
                    'category_changed' => ($categoryId !== ($existingMenuItem['category_id'] ?? null)),
                    'menuData_keys' => array_keys($menuData),
                    'menuData_category_id' => $menuData['category_id'] ?? 'not_set',
                    'menuData_full' => $menuData // Full menuData for debugging
                ]);
            }

            $result = $this->menuItemService->updateMenuItem($menuItemId, $menuData);
            
            // Log update result
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::debug('MenuController::edit - Update result', [
                    'menu_item_id' => $menuItemId,
                    'result' => $result,
                    'category_id_sent' => $categoryId
                ]);
            }

            if ($result) {
                // Update variants if product has variants
                $productVariantService = \App\Core\DependencyFactory::getProductVariantService();

                if ($hasVariants && !empty($variants) && is_array($variants)) {
                    // Delete existing variants and create new ones
                    $productVariantService->deleteVariantsByProduct($menuItemId);
                    $productVariantService->createVariants($menuItemId, $variants);
                } else {
                    // If has_variants is 0, delete all variants
                    $productVariantService->deleteVariantsByProduct($menuItemId);
                }

                // Save translations if provided (from JSON input or POST)
                $translations = null;
                if (is_array($input) && isset($input['translations'])) {
                    $translations = $input['translations'];
                } elseif (isset($requestData['translations'])) {
                    $translations = $requestData['translations'];
                }
                if ($translations && is_array($translations)) {
                    require_once __DIR__ . '/../core/DependencyFactory.php';
                    $translationService = \App\Core\DependencyFactory::getMenuItemTranslationService();
                    $translationService->saveTranslations($menuItemId, $translations);
                }

                // CACHE INVALIDATION: Clear menu cache after update
                $tenantId = \App\Core\TenantContext::getId();
                if ($tenantId && class_exists('\App\Helpers\CacheHelper')) {
                    \App\Helpers\CacheHelper::clearMenuCache($tenantId);
                }

                $this->toastNotificationService->sendApiResponse('success', 'notifications.success.menu_item_updated', [], 200);
            } else {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.update_failed', [], 500);
            }
        }
    }

    public function delete($id = null) {
        // Log the request for debugging
        \App\Core\Logger::info('MenuController::delete called', [
            'id_param' => $id,
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
            'uri' => $_SERVER['REQUEST_URI'] ?? 'UNKNOWN'
        ]);

        // Check if user has permission
        if (!$this->hasPermission('menu.delete')) {
            \App\Core\Logger::warning('MenuController::delete - Permission denied');
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }

        // Get ID from route parameter or query string
        if (empty($id)) {
            $queryParams = \App\Core\RequestParser::getQueryParams();
            $id = $queryParams['id'] ?? '';
            \App\Core\Logger::debug('MenuController::delete - ID from query params', ['id' => $id]);
        }

        if (empty($id)) {
            \App\Core\Logger::warning('MenuController::delete - No ID provided');
            $this->apiResponse([
                'success' => false,
                'error' => 'Menu item ID is required',
                'message' => t('notifications.error.invalid_data', 'Geçersiz veri')
            ], 400);
            return;
        }

        // CRITICAL: Verify menu item belongs to current tenant before deletion
        $this->ensureTenantContext();
        $menuItem = $this->menuItemService->getMenuItemById($id);

        if (!$menuItem) {
            \App\Core\Logger::warning('MenuController::delete - Menu item not found', ['id' => $id]);
            $this->apiResponse([
                'success' => false,
                'error' => 'Menu item not found',
                'message' => t('notifications.error.not_found', 'Ürün bulunamadı')
            ], 404);
            return;
        }

        // Check tenant isolation (unless super admin)
        if (!$this->isSuperAdmin()) {
            $tenantId = \App\Core\TenantContext::getId();
            $itemBusinessId = $menuItem['tenant_id'] ?? null;

            if (!$tenantId || $itemBusinessId !== $tenantId) {
                \App\Core\Logger::warning('MenuController::delete - Tenant isolation violation', [
                    'menu_item_id' => $id,
                    'item_business_id' => $itemBusinessId,
                    'tenant_id' => $tenantId,
                    'user_id' => $_SESSION['user_id'] ?? 'unknown'
                ]);
                $this->apiResponse([
                    'success' => false,
                    'error' => 'Unauthorized',
                    'message' => t('notifications.error.unauthorized', 'Bu işlemi yapmaya yetkiniz yok')
                ], 403);
                return;
            }
        }

        \App\Core\Logger::info('MenuController::delete - Deleting item', ['id' => $id]);
        $result = $this->menuItemService->deleteMenuItem($id);

        if ($result) {
            // CACHE INVALIDATION: Clear menu cache after deletion
            $tenantId = \App\Core\TenantContext::getId();
            if ($tenantId && class_exists('\App\Helpers\CacheHelper')) {
                \App\Helpers\CacheHelper::clearMenuCache($tenantId);
            }
            
            \App\Core\Logger::info('MenuController::delete - Success', ['id' => $id]);
            $this->apiResponse([
                'success' => true,
                'message' => t('notifications.success.menu_item_deleted', 'Ürün başarıyla silindi')
            ]);
        } else {
            \App\Core\Logger::error('MenuController::delete - Failed', ['id' => $id]);
            $this->apiResponse([
                'success' => false,
                'error' => 'Failed to delete menu item',
                'message' => t('notifications.error.delete_failed', 'Silme işlemi başarısız oldu')
            ], 500);
        }
    }

    /**
     * Extract menu items from uploaded image using Gemini Vision API
     */
    public function extractMenuFromImage() {
        // Increase PHP execution time for image processing and API calls (5 minutes)
        @set_time_limit(300);
        @ini_set('max_execution_time', '300');
        
        if (!$this->hasPermission('menu.create')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }

        // Ensure tenant context
        $requestData = \App\Core\RequestParser::getRequestData();
        $this->bootstrapSuperAdminTenantFromRequest($requestData);
        if (!empty($requestData['business_id'])) {
            $_GET['business_id'] = $requestData['business_id'];
        }
        $this->ensureTenantContext();

        try {
            // Check if file was uploaded
            if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                $this->toastNotificationService->sendApiResponse('error', 'Resim yüklenemedi. Lütfen geçerli bir resim dosyası seçin.', [], 400);
                return;
            }

            $file = $_FILES['image'];
            $fileTmpPath = $file['tmp_name'];
            $fileName = $file['name'];
            $fileSize = $file['size'];
            $fileType = $file['type'];

            // Validate file type
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
            if (!in_array($fileType, $allowedTypes)) {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Desteklenmeyen dosya formatı. Lütfen JPEG, PNG veya WebP formatında bir resim yükleyin.', 'data' => null]);
                exit;
            }

            // Validate file size (max 10MB)
            $maxSize = 10 * 1024 * 1024; // 10MB
            if ($fileSize > $maxSize) {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Dosya boyutu çok büyük. Maksimum 10MB olabilir.', 'data' => null]);
                exit;
            }

            // Read file and compress/resize if needed
            $imageData = file_get_contents($fileTmpPath);
            if ($imageData === false) {
                header('Content-Type: application/json');
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Resim okunamadı.', 'data' => null]);
                exit;
            }

            // Compress image to reduce processing time (max 1024x1024, 85% quality)
            try {
                $image = null;
                if ($fileType === 'image/jpeg' || $fileType === 'image/jpg') {
                    $image = imagecreatefromjpeg($fileTmpPath);
                } elseif ($fileType === 'image/png') {
                    $image = imagecreatefrompng($fileTmpPath);
                } elseif ($fileType === 'image/webp') {
                    $image = imagecreatefromwebp($fileTmpPath);
                }

                if ($image) {
                    $width = imagesx($image);
                    $height = imagesy($image);
                    
                    // Resize to 768px max for faster Gemini processing (prevent timeouts)
                    // Smaller = faster AI response, still enough detail for OCR
                    $maxDimension = 768;
                    if ($width > $maxDimension || $height > $maxDimension) {
                        $ratio = min($maxDimension / $width, $maxDimension / $height);
                        $newWidth = (int)($width * $ratio);
                        $newHeight = (int)($height * $ratio);
                        
                        $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
                        
                        // Preserve transparency for PNG
                        if ($fileType === 'image/png') {
                            imagealphablending($resizedImage, false);
                            imagesavealpha($resizedImage, true);
                        }
                        
                        imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                        
                        // Convert to JPEG with optimized quality (60%) for faster Gemini processing
                        ob_start();
                        imagejpeg($resizedImage, null, 60);
                        $imageData = ob_get_clean();
                        $fileType = 'image/jpeg';
                        
                        imagedestroy($resizedImage);
                    } else {
                        // Just convert to JPEG with optimized quality if not already
                        if ($fileType !== 'image/jpeg' && $fileType !== 'image/jpg') {
                            ob_start();
                            imagejpeg($image, null, 60);
                            $imageData = ob_get_clean();
                            $fileType = 'image/jpeg';
                        }
                    }
                    
                    imagedestroy($image);
                }
            } catch (\Exception $e) {
                // If compression fails, use original
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning('MenuController::extractMenuFromImage - Image compression failed, using original', ['error' => $e->getMessage()]);
                }
            }

            $imageBase64 = base64_encode($imageData);

            // Get GeminiService
            $geminiService = \App\Core\DependencyFactory::getGeminiService();

            if (!$geminiService->isAvailable()) {
                $this->toastNotificationService->sendApiResponse('error', 'Gemini API anahtarı yapılandırılmamış. Lütfen sistem ayarlarından Gemini API anahtarını yapılandırın.', [], 500);
                return;
            }

            // Extract menu items from image
            $extractedItems = $geminiService->extractMenuFromImage($imageBase64, $fileType);

            if (empty($extractedItems)) {
                $this->toastNotificationService->sendApiResponse('error', 'Menüden ürün çıkarılamadı. Lütfen daha net bir menü fotoğrafı deneyin.', [], 400);
                return;
            }

            // Return extracted items for review
            // Use direct JSON response instead of toastNotificationService
            header('Content-Type: application/json');
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => count($extractedItems) . ' ürün bulundu.',
                'data' => [
                    'items' => $extractedItems,
                    'count' => count($extractedItems)
                ]
            ]);
            exit;

        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('MenuController::extractMenuFromImage - Error: ' . $e->getMessage());
            }
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Menü çıkarılırken bir hata oluştu: ' . $e->getMessage(), 'data' => null]);
            exit;
        }
    }

    /**
     * Bulk add menu items from extraction
     */
    public function bulkAddFromExtraction() {
        try {
            if (!$this->hasPermission('menu.create')) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
                return;
            }

            $requestData = \App\Core\RequestParser::getRequestData();
            $this->bootstrapSuperAdminTenantFromRequest($requestData);
            if (!empty($requestData['business_id'])) {
                $_GET['business_id'] = $requestData['business_id'];
            }
            $this->ensureTenantContext();
            
            // DEBUG: Log tenant context
            $tenantId = \App\Core\TenantContext::getId();
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::info('MenuController::bulkAddFromExtraction - START', [
                    'tenant_id' => $tenantId,
                    'has_permission' => $this->hasPermission('menu.create')
                ]);
            }

            // Inner try for main logic
            try {
            $items = $requestData['items'] ?? [];
            
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::info('MenuController::bulkAddFromExtraction - Request received', [
                    'items_count' => count($items),
                    'first_item_sample' => !empty($items) ? $items[0] : null
                ]);
            }

            if (empty($items) || !is_array($items)) {
                $this->toastNotificationService->sendApiResponse('error', 'Geçersiz veri. Lütfen en az bir ürün seçin.', [], 400);
                return;
            }

            $results = [];
            $successCount = 0;
            $errorCount = 0;

            // Get categories for mapping
            $categoryService = \App\Core\DependencyFactory::getCategoryService();
            $allCategories = $categoryService->getAllCategories();
            
            // Create category map with multiple matching strategies
            $categoryMap = [];
            $categoryNormalizedMap = [];
            
            foreach ($allCategories as $cat) {
                $catName = trim($cat['name']);
                $catNameLower = mb_strtolower($catName, 'UTF-8');
                
                // Direct mapping
                $categoryMap[$catNameLower] = $cat['category_id'];
                
                // Normalized mapping (remove Turkish characters for better matching)
                $normalized = $this->normalizeTurkishChars($catNameLower);
                $categoryNormalizedMap[$normalized] = $cat['category_id'];
                
                // Also map common variations
                $variations = $this->getCategoryVariations($catNameLower);
                foreach ($variations as $variation) {
                    if (!isset($categoryMap[$variation])) {
                        $categoryMap[$variation] = $cat['category_id'];
                    }
                }
            }

            foreach ($items as $index => $item) {
                try {
                    // Validate required fields
                    if (empty($item['name']) || !isset($item['price'])) {
                        $results[] = [
                            'index' => $index,
                            'name' => $item['name'] ?? 'Bilinmeyen',
                            'success' => false,
                            'error' => 'Ad ve fiyat gereklidir'
                        ];
                        $errorCount++;
                        continue;
                    }

                    // Map category if provided - find existing or create new (with parent support)
                    $categoryId = null;
                    $createdNewCategory = false;
                    
                    // Determine category and parent category
                    $extractedCategory = !empty($item['category']) ? trim($item['category']) : null;
                    $extractedParentCategory = !empty($item['parent_category']) ? trim($item['parent_category']) : null;
                    
                    // If category is empty but parent_category exists, use parent_category as category
                    if (empty($extractedCategory) && !empty($extractedParentCategory)) {
                        $extractedCategory = $extractedParentCategory;
                        $extractedParentCategory = null; // Clear parent since we're using it as main category
                    }
                    
                    // If category and parent_category are the same, treat as main category (no parent)
                    if (!empty($extractedCategory) && !empty($extractedParentCategory) && 
                        mb_strtolower(trim($extractedCategory), 'UTF-8') === mb_strtolower(trim($extractedParentCategory), 'UTF-8')) {
                        $extractedParentCategory = null;
                    }
                    
                    if (!empty($extractedCategory)) {
                        $extractedCategoryLower = mb_strtolower($extractedCategory, 'UTF-8');
                        
                        // Check if there's a parent category
                        $parentCategoryId = null;
                        if (!empty($extractedParentCategory)) {
                            $extractedParentCategoryLower = mb_strtolower($extractedParentCategory, 'UTF-8');
                            
                            // Try to find or create parent category first
                            if (isset($categoryMap[$extractedParentCategoryLower])) {
                                $parentCategoryId = $categoryMap[$extractedParentCategoryLower];
                            } else {
                                $normalized = $this->normalizeTurkishChars($extractedParentCategoryLower);
                                if (isset($categoryNormalizedMap[$normalized])) {
                                    $parentCategoryId = $categoryNormalizedMap[$normalized];
                                } else {
                                    // Try fuzzy matching - but only match categories without parent_id (top-level)
                                    $parentCategoryId = $this->findSimilarCategory($extractedParentCategoryLower, array_filter($allCategories, function($cat) {
                                        return empty($cat['parent_id']);
                                    }), null);
                                }
                            }
                            
                            // Create parent category if not found
                            if (!$parentCategoryId) {
                                try {
                                    // Get tenant ID for new category - try multiple sources
                                    $tenantId = \App\Core\TenantContext::getId();
                                    if (!$tenantId) {
                                        $tenantId = \App\Core\TenantResolver::resolve();
                                    }
                                    
                                    if (!$tenantId) {
                                        if (class_exists('\App\Core\Logger')) {
                                            \App\Core\Logger::error('MenuController::bulkAddFromExtraction - No tenant_id available for parent category', [
                                                'category_name' => $extractedParentCategory
                                            ]);
                                        }
                                        // Don't skip - continue without parent
                                    } else {
                                    // CRITICAL: Ensure we're passing the actual category NAME, not an ID
                                    $newParentCategoryData = [
                                        'name' => trim($extractedParentCategory), // Use the extracted name directly
                                        'tenant_id' => $tenantId, // CRITICAL: Add tenant_id
                                            'parent_id' => null // Parent categories have no parent
                                    ];
                                    
                                    if (class_exists('\App\Core\Logger')) {
                                        \App\Core\Logger::info('MenuController::bulkAddFromExtraction - Attempting to create parent category', [
                                            'category_name' => $newParentCategoryData['name'],
                                            'data' => $newParentCategoryData
                                        ]);
                                    }
                                    
                                    $parentCategoryId = $categoryService->createCategory($newParentCategoryData);
                                    
                                    if ($parentCategoryId) {
                                        // Update maps
                                        $categoryMap[$extractedParentCategoryLower] = $parentCategoryId;
                                        $categoryNormalizedMap[$this->normalizeTurkishChars($extractedParentCategoryLower)] = $parentCategoryId;
                                        $allCategories = $categoryService->getAllCategories();
                                        
                                        if (class_exists('\App\Core\Logger')) {
                                            \App\Core\Logger::info('MenuController::bulkAddFromExtraction - Created parent category', [
                                                'category_name' => $extractedParentCategory,
                                                'category_id' => $parentCategoryId
                                            ]);
                                        }
                                    } else {
                                        // Failed to create parent category
                                        if (class_exists('\App\Core\Logger')) {
                                            \App\Core\Logger::error('MenuController::bulkAddFromExtraction - Failed to create parent category', [
                                                'category_name' => $extractedParentCategory,
                                                'data' => $newParentCategoryData,
                                                'tenant_id' => $tenantId
                                            ]);
                                            }
                                        }
                                    }
                                } catch (\Exception $e) {
                                    if (class_exists('\App\Core\Logger')) {
                                        \App\Core\Logger::warning('MenuController::bulkAddFromExtraction - Failed to create parent category', [
                                            'category_name' => $extractedParentCategory,
                                            'error' => $e->getMessage()
                                        ]);
                                    }
                                }
                            }
                        }
                        
                        // Now find or create the (sub)category
                        // Try direct match first - but check parent_id if parent is specified
                        if (isset($categoryMap[$extractedCategoryLower])) {
                            $foundCategoryId = $categoryMap[$extractedCategoryLower];
                            // Verify parent_id matches if parent is specified
                            if ($parentCategoryId) {
                                $foundCategory = array_filter($allCategories, function($cat) use ($foundCategoryId) {
                                    return $cat['category_id'] === $foundCategoryId;
                                });
                                $foundCategory = reset($foundCategory);
                                if ($foundCategory && ($foundCategory['parent_id'] ?? null) === $parentCategoryId) {
                                    $categoryId = $foundCategoryId;
                        } else {
                                    $categoryId = null; // Parent doesn't match, need to create new or find different
                                }
                            } else {
                                // No parent specified, check if found category has no parent
                                $foundCategory = array_filter($allCategories, function($cat) use ($foundCategoryId) {
                                    return $cat['category_id'] === $foundCategoryId;
                                });
                                $foundCategory = reset($foundCategory);
                                if ($foundCategory && empty($foundCategory['parent_id'])) {
                                    $categoryId = $foundCategoryId;
                                } else {
                                    $categoryId = null; // Found category has parent but we don't want one
                                }
                            }
                        }
                        
                        // If not found or parent doesn't match, try normalized match
                        if (!$categoryId) {
                            $normalized = $this->normalizeTurkishChars($extractedCategoryLower);
                            if (isset($categoryNormalizedMap[$normalized])) {
                                $foundCategoryId = $categoryNormalizedMap[$normalized];
                                // Verify parent_id matches if parent is specified
                                if ($parentCategoryId) {
                                    $foundCategory = array_filter($allCategories, function($cat) use ($foundCategoryId) {
                                        return $cat['category_id'] === $foundCategoryId;
                                    });
                                    $foundCategory = reset($foundCategory);
                                    if ($foundCategory && ($foundCategory['parent_id'] ?? null) === $parentCategoryId) {
                                        $categoryId = $foundCategoryId;
                                    }
                            } else {
                                    // No parent specified, check if found category has no parent
                                    $foundCategory = array_filter($allCategories, function($cat) use ($foundCategoryId) {
                                        return $cat['category_id'] === $foundCategoryId;
                                    });
                                    $foundCategory = reset($foundCategory);
                                    if ($foundCategory && empty($foundCategory['parent_id'])) {
                                        $categoryId = $foundCategoryId;
                                    }
                                }
                            }
                        }
                        
                        // If still not found, try fuzzy matching - but filter by parent_id
                        if (!$categoryId) {
                            $categoriesToSearch = $allCategories;
                            if ($parentCategoryId) {
                                // Only search categories with matching parent_id
                                $categoriesToSearch = array_filter($allCategories, function($cat) use ($parentCategoryId) {
                                    return ($cat['parent_id'] ?? null) === $parentCategoryId;
                                });
                            } else {
                                // Only search top-level categories (no parent)
                                $categoriesToSearch = array_filter($allCategories, function($cat) {
                                    return empty($cat['parent_id']);
                                });
                            }
                            $categoryId = $this->findSimilarCategory($extractedCategoryLower, $categoriesToSearch, $parentCategoryId);
                        }
                        
                        // If no match found, create new (sub)category
                        if (!$categoryId) {
                            try {
                                // Get tenant ID for new category - try multiple sources
                                $tenantId = \App\Core\TenantContext::getId();
                                if (!$tenantId) {
                                    $tenantId = \App\Core\TenantResolver::resolve();
                                }
                                
                                if (!$tenantId) {
                                    if (class_exists('\App\Core\Logger')) {
                                        \App\Core\Logger::error('MenuController::bulkAddFromExtraction - No tenant_id available for category', [
                                            'category_name' => $extractedCategory
                                        ]);
                                    }
                                    // Continue without category - will use fallback
                                } else {
                                    // CRITICAL: Ensure we're passing the actual category NAME, not an ID
                                    $newCategoryData = [
                                        'name' => trim($extractedCategory), // Use the extracted name directly
                                        'tenant_id' => $tenantId, // CRITICAL: Add tenant_id
                                        'parent_id' => $parentCategoryId // Set parent if exists
                                    ];
                                
                                if (class_exists('\App\Core\Logger')) {
                                    \App\Core\Logger::info('MenuController::bulkAddFromExtraction - Attempting to create category', [
                                        'category_name' => $newCategoryData['name'],
                                        'parent_id' => $parentCategoryId,
                                        'data' => $newCategoryData
                                    ]);
                                }
                                
                                $categoryId = $categoryService->createCategory($newCategoryData);
                                
                                if ($categoryId) {
                                    $createdNewCategory = true;
                                    // Update maps for next items
                                    $categoryMap[$extractedCategoryLower] = $categoryId;
                                    $categoryNormalizedMap[$normalized] = $categoryId;
                                    
                                    // Refresh categories list
                                    $allCategories = $categoryService->getAllCategories();
                                    
                                    if (class_exists('\App\Core\Logger')) {
                                        \App\Core\Logger::info('MenuController::bulkAddFromExtraction - Created new category', [
                                            'category_name' => $extractedCategory,
                                            'category_id' => $categoryId,
                                            'parent_id' => $parentCategoryId
                                        ]);
                                    }
                                } else {
                                    // Failed to create category
                                    if (class_exists('\App\Core\Logger')) {
                                        \App\Core\Logger::error('MenuController::bulkAddFromExtraction - Failed to create category', [
                                            'category_name' => $extractedCategory,
                                            'data' => $newCategoryData,
                                            'parent_id' => $parentCategoryId,
                                            'tenant_id' => $tenantId
                                        ]);
                                    }
                                }
                                } // Close else block for tenant_id check
                            } catch (\Exception $e) {
                                // If category creation fails, log but continue
                                if (class_exists('\App\Core\Logger')) {
                                    \App\Core\Logger::warning('MenuController::bulkAddFromExtraction - Failed to create category', [
                                        'category_name' => $extractedCategory,
                                        'error' => $e->getMessage()
                                    ]);
                                }
                            }
                        }
                    }

                    // If still no category, use first available category as fallback
                    if (!$categoryId && !empty($allCategories)) {
                        $categoryId = $allCategories[0]['category_id'];
                        if (class_exists('\App\Core\Logger')) {
                            \App\Core\Logger::info('MenuController::bulkAddFromExtraction - Using first category as fallback', [
                                'item_name' => trim($item['name']),
                                'fallback_category_id' => $categoryId
                            ]);
                        }
                    }

                    // Skip only if absolutely no categories exist and couldn't create one
                    if (!$categoryId) {
                        $results[] = [
                            'index' => $index,
                            'name' => trim($item['name']),
                            'success' => false,
                            'error' => 'Kategori oluşturulamadı ve sistemde başka kategori yok.'
                        ];
                        $errorCount++;
                        continue;
                    }

                    // Prepare menu item data - use Turkish as default
                    $nameTr = trim($item['name_tr'] ?? $item['name'] ?? '');
                    $nameEn = trim($item['name_en'] ?? $item['name'] ?? $nameTr);
                    $descriptionTr = trim($item['description_tr'] ?? $item['description'] ?? '');
                    $descriptionEn = trim($item['description_en'] ?? $item['description'] ?? '');
                    $ingredientsTr = is_array($item['ingredients_tr'] ?? null) ? $item['ingredients_tr'] : 
                                    (is_array($item['ingredients'] ?? null) ? $item['ingredients'] : []);
                    $ingredientsEn = is_array($item['ingredients_en'] ?? null) ? $item['ingredients_en'] : [];
                    
                    $menuItemData = [
                        'name' => $nameTr, // Turkish name as default
                        'price' => floatval($item['price']),
                        'description' => $descriptionTr, // Turkish description as default
                        'category_id' => $categoryId,
                        'ingredients' => json_encode($ingredientsTr), // Store Turkish ingredients as JSON string
                        'available_extras' => json_encode([]), // Store as JSON string
                        'is_available' => 1,
                        'track_stock' => 0,
                        'stock' => 999 // Use 'stock' instead of 'stock_quantity'
                    ];

                    // Create menu item using existing add logic
                    // We'll call the service directly to avoid redirect issues
                    $menuItemId = $this->menuItemService->createMenuItem($menuItemData);

                    if ($menuItemId) {
                        // Save translations for Turkish and English
                        try {
                            $translationService = \App\Core\DependencyFactory::getMenuItemTranslationService();
                            $translations = [
                                'tr' => [
                                    'name' => $nameTr,
                                    'description' => $descriptionTr,
                                    'ingredients' => $ingredientsTr
                                ],
                                'en' => [
                                    'name' => $nameEn,
                                    'description' => $descriptionEn,
                                    'ingredients' => $ingredientsEn
                                ]
                            ];
                            $translationService->saveTranslations($menuItemId, $translations);
                        } catch (\Exception $e) {
                            // Log error but don't fail the whole operation
                            if (class_exists('\App\Core\Logger')) {
                                \App\Core\Logger::warning('MenuController::bulkAddFromExtraction - Translation save failed', [
                                    'menu_item_id' => $menuItemId,
                                    'error' => $e->getMessage()
                                ]);
                            }
                        }
                        
                        $results[] = [
                            'index' => $index,
                            'name' => $nameTr,
                            'success' => true,
                            'menu_item_id' => $menuItemId,
                            'created_category' => $createdNewCategory
                        ];
                        $successCount++;
                    } else {
                        $results[] = [
                            'index' => $index,
                            'name' => $nameTr,
                            'success' => false,
                            'error' => 'Ürün oluşturulamadı'
                        ];
                        $errorCount++;
                    }

                } catch (\Exception $e) {
                    $results[] = [
                        'index' => $index,
                        'name' => $item['name'] ?? 'Bilinmeyen',
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                    $errorCount++;
                }
            }

            // Log summary for debugging
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::info('MenuController::bulkAddFromExtraction - Final Summary', [
                    'total_items' => count($items),
                    'success_count' => $successCount,
                    'error_count' => $errorCount,
                    'results_sample' => array_slice($results, 0, 3)
                ]);
            }
            
            // Return results
            $message = '';
            if ($successCount > 0 && $errorCount === 0) {
                $message = "{$successCount} ürün başarıyla eklendi.";
            } elseif ($successCount > 0 && $errorCount > 0) {
                $message = "{$successCount} ürün eklendi, {$errorCount} ürün eklenemedi.";
            } else {
                $message = "Hiçbir ürün eklenemedi. Lütfen log kayıtlarını kontrol edin.";
            }
            
            $this->toastNotificationService->sendApiResponse(
                $successCount > 0 ? 'success' : 'error',
                $message,
                [
                    'results' => $results,
                    'success_count' => $successCount,
                    'error_count' => $errorCount
                ],
                200
            );

            } catch (\Exception $e) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('MenuController::bulkAddFromExtraction - Inner Error: ' . $e->getMessage(), [
                        'trace' => $e->getTraceAsString()
                    ]);
                }
                $this->toastNotificationService->sendApiResponse('error', 'Ürünler eklenirken bir hata oluştu: ' . $e->getMessage(), [], 500);
            }
        } catch (\Exception $e) {
            // Outer catch-all for any unhandled errors
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('MenuController::bulkAddFromExtraction - FATAL ERROR', [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            
            $this->toastNotificationService->sendApiResponse(
                'error',
                'Kritik hata: ' . $e->getMessage(),
                [],
                500
            );
        }
    }

    /**
     * Super-admin JSON/API calls may carry business_id in the body instead of the query string.
     */
    private function bootstrapSuperAdminTenantFromRequest(array $requestData = []): void {
        if (!$this->isSuperAdmin() || \App\Core\TenantContext::isSet()) {
            return;
        }

        $businessId = $requestData['business_id']
            ?? $_GET['business_id']
            ?? $_SESSION['selected_business_id']
            ?? null;

        if (empty($businessId)) {
            return;
        }

        try {
            $customerService = \App\Core\DependencyFactory::getCustomerService();
            $customer = $customerService->getById($businessId);
            if ($customer) {
                \App\Core\TenantContext::set($customer);
                $_SESSION['selected_business_id'] = $businessId;
            }
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('MenuController tenant bootstrap failed', [
                    'business_id' => $businessId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Normalize Turkish characters for better category matching
     */
    private function normalizeTurkishChars($text) {
        $turkish = ['ç', 'ğ', 'ı', 'ö', 'ş', 'ü', 'Ç', 'Ğ', 'İ', 'Ö', 'Ş', 'Ü'];
        $english = ['c', 'g', 'i', 'o', 's', 'u', 'C', 'G', 'I', 'O', 'S', 'U'];
        return str_replace($turkish, $english, $text);
    }

    /**
     * Get common variations of category names
     */
    private function getCategoryVariations($categoryName) {
        $variations = [];
        
        // Common Turkish variations
        $variationsMap = [
            'kahve' => ['kahveler', 'kahve çeşitleri', 'kahveler & sıcaklar'],
            'pizza' => ['pizzalar', 'pizza çeşitleri'],
            'burger' => ['burgerler', 'hamburger', 'hamburgerler'],
            'tost' => ['tostlar', 'tost çeşitleri'],
            'salata' => ['salatalar', 'salata çeşitleri'],
            'tatlı' => ['tatlılar', 'tatlı çeşitleri', 'dessert', 'desserts'],
            'içecek' => ['içecekler', 'soğuk içecekler', 'sıcak içecekler'],
            'nargile' => ['nargile çeşitleri', 'nargileler'],
            'atıştırmalık' => ['atıştırmalıklar', 'snack', 'snacks'],
            'izgara' => ['ızgaralar', 'grill', 'grills'],
            'tavuk' => ['tavuk spesiyal', 'tavuk çeşitleri']
        ];
        
        foreach ($variationsMap as $key => $vars) {
            if (strpos($categoryName, $key) !== false) {
                $variations = array_merge($variations, $vars);
            }
        }
        
        return array_map(function($v) {
            return mb_strtolower($v, 'UTF-8');
        }, $variations);
    }

    /**
     * Find similar category using fuzzy matching
     * Improved to prioritize exact matches and handle parent-child relationships
     */
    private function findSimilarCategory($extractedCategory, $allCategories, $parentCategoryId = null) {
        $bestMatch = null;
        $bestScore = 0;
        $threshold = 0.6; // Minimum similarity threshold (60%)
        
        $extractedCategoryLower = mb_strtolower(trim($extractedCategory), 'UTF-8');
        $extractedCategoryNormalized = $this->normalizeTurkishChars($extractedCategoryLower);
        
        foreach ($allCategories as $cat) {
            $catName = mb_strtolower(trim($cat['name']), 'UTF-8');
            $catParentId = $cat['parent_id'] ?? null;
            
            // If parent is specified, only match categories with that parent
            if ($parentCategoryId !== null && $catParentId !== $parentCategoryId) {
                continue;
            }
            
            // If parent is NOT specified, only match top-level categories
            if ($parentCategoryId === null && $catParentId !== null) {
                continue;
            }
            
            // Exact match (highest priority)
            if ($extractedCategoryLower === $catName) {
                return $cat['category_id'];
            }
            
            // Normalized exact match
            $catNameNormalized = $this->normalizeTurkishChars($catName);
            if ($extractedCategoryNormalized === $catNameNormalized) {
                return $cat['category_id'];
            }
            
            // Calculate similarity
            $similarity = $this->calculateSimilarity($extractedCategoryLower, $catName);
            
            if ($similarity > $bestScore && $similarity >= $threshold) {
                $bestScore = $similarity;
                $bestMatch = $cat['category_id'];
            }
        }
        
        return $bestMatch;
    }

    /**
     * Calculate similarity between two strings (simple Levenshtein-based)
     */
    private function calculateSimilarity($str1, $str2) {
        // Normalize both strings
        $str1 = $this->normalizeTurkishChars($str1);
        $str2 = $this->normalizeTurkishChars($str2);
        
        // Remove common words
        $commonWords = ['çeşitleri', 'çeşit', 've', '&', 'ile'];
        foreach ($commonWords as $word) {
            $str1 = str_replace($word, '', $str1);
            $str2 = str_replace($word, '', $str2);
        }
        
        $str1 = trim($str1);
        $str2 = trim($str2);
        
        // Check if one contains the other
        if (strpos($str1, $str2) !== false || strpos($str2, $str1) !== false) {
            return 0.8; // High similarity
        }
        
        // Calculate Levenshtein distance
        $maxLen = max(mb_strlen($str1, 'UTF-8'), mb_strlen($str2, 'UTF-8'));
        if ((int)$maxLen === 0) {
            return 1.0;
        }
        
        $distance = levenshtein($str1, $str2);
        $similarity = 1 - ($distance / $maxLen);
        
        return $similarity;
    }

    public function addCategory() {
        // Check if user has permission
        if (!$this->hasPermission('menu.categories')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Handle JSON input - JSON takes priority over POST
            $jsonInput = file_get_contents('php://input');
            $input = null;
            if (!empty($jsonInput)) {
                $input = json_decode($jsonInput, true);
                // json_decode returns null on error, check if it's actually an array
                if (!is_array($input)) {
                    $input = null;
                }
            }
            $requestData = $input ?? $_POST;

            // Kategori için özel validation - sadece name (Türkçe kategori adı) zorunlu
            if (empty($requestData['name']) || !is_string($requestData['name']) || trim($requestData['name']) === '') {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_category_name', [], 400, [
                    'errors' => ['name' => ['Kategori adı (Türkçe) zorunludur.']]
                ]);
                return;
            }

            // Validate request data using ValidationService (sadece name için)
            $validationResult = $this->validateRequestData($requestData, 'category');

            if (!$validationResult['valid']) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_category_name', [], 400, [
                    'errors' => $validationResult['errors']
                ]);
                return;
            }

            $validatedData = $validationResult['data'];

            // Get name_en and description_en from request data
            $nameEn = trim($requestData['name_en'] ?? '');
            $descriptionEn = trim($requestData['description_en'] ?? '');

            // Get default_production_point from request data
            $defaultProductionPoint = $requestData['default_production_point'] ?? null;

            // Validate default_production_point
            $validProductionPoints = ConstantsHelper::getProductionPoints();
            if (!empty($defaultProductionPoint) && !in_array($defaultProductionPoint, $validProductionPoints)) {
                $defaultProductionPoint = null; // Invalid value
            }

            $categoryData = [
                'category_id' => generateId('cat'),
                'name' => trim($validatedData['name'] ?? ''),
                'name_en' => $nameEn ?: trim($validatedData['name'] ?? ''), // İngilizce isim yoksa Türkçe'yi kullan
                'description' => trim($validatedData['description'] ?? ''),
                'description_en' => $descriptionEn,
                'image_url' => trim($validatedData['image_url'] ?? ''),
                'requires_kitchen' => isset($requestData['requires_kitchen']) ? (int)$requestData['requires_kitchen'] : 0,
                'default_production_point' => $defaultProductionPoint
            ];

            // Check if category with same name already exists
            $categoryRepository = \App\Core\DependencyFactory::getCategoryRepository();
            $existingCategory = $categoryRepository->findByName($validatedData['name']);
            if ($existingCategory) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.category_exists', [], 400);
                return;
            }

            $result = $this->categoryService->createCategory($categoryData);

            if ($result) {
                // CACHE INVALIDATION: Clear menu cache after adding category
                $tenantId = \App\Core\TenantContext::getId();
                if ($tenantId && class_exists('\App\Helpers\CacheHelper')) {
                    \App\Helpers\CacheHelper::clearMenuCache($tenantId);
                }
                
                $this->toastNotificationService->sendApiResponse('success', 'notifications.success.category_added', [], 200);
            } else {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.create_failed', [], 500);
            }
        }
    }

    public function editCategory() {
        // Check if user has permission
        if (!$this->hasPermission('menu.categories')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }

        // Get ID from route parameter or GET
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $categoryId = $queryParams['id'] ?? '';
        if (empty($categoryId)) {
            // Try to get from URL path
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
            if (preg_match('/edit-category\/([^\/]+)/', $path, $matches)) {
                $categoryId = $matches[1];
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Handle JSON input - JSON takes priority over POST
            $input = json_decode(file_get_contents('php://input'), true);
            $requestData = $input ?? $_POST;

            // Kategori için özel validation - sadece name (Türkçe kategori adı) zorunlu
            if (empty($requestData['name']) || !is_string($requestData['name']) || trim($requestData['name']) === '') {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_category_name', [], 400, [
                    'errors' => ['name' => ['Kategori adı (Türkçe) zorunludur.']]
                ]);
                return;
            }

            // Validate request data using ValidationService (sadece name için)
            $validationResult = $this->validateRequestData($requestData, 'category');

            if (!$validationResult['valid']) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_category_name', [], 400, [
                    'errors' => $validationResult['errors']
                ]);
                return;
            }

            $validatedData = $validationResult['data'];

            // Get name_en and description_en from request data
            $nameEn = trim($requestData['name_en'] ?? '');
            $descriptionEn = trim($requestData['description_en'] ?? '');

            // Get default_production_point from request data
            $defaultProductionPoint = $requestData['default_production_point'] ?? null;

            // Validate default_production_point
            $validProductionPoints = ConstantsHelper::getProductionPoints();
            if (!empty($defaultProductionPoint) && !in_array($defaultProductionPoint, $validProductionPoints)) {
                $defaultProductionPoint = null; // Invalid value
            }

            $categoryData = [
                'name' => trim($validatedData['name'] ?? ''),
                'name_en' => $nameEn ?: trim($validatedData['name'] ?? ''), // İngilizce isim yoksa Türkçe'yi kullan
                'description' => trim($validatedData['description'] ?? ''),
                'description_en' => $descriptionEn,
                'image_url' => trim($validatedData['image_url'] ?? ''),
                'requires_kitchen' => isset($requestData['requires_kitchen']) ? (int)$requestData['requires_kitchen'] : 0,
                'default_production_point' => $defaultProductionPoint
            ];

            $result = $this->categoryService->updateCategory($categoryId, $categoryData);

            if ($result) {
                // CACHE INVALIDATION: Clear menu cache after category update
                $tenantId = \App\Core\TenantContext::getId();
                if ($tenantId && class_exists('\App\Helpers\CacheHelper')) {
                    \App\Helpers\CacheHelper::clearMenuCache($tenantId);
                }
                
                $this->toastNotificationService->sendApiResponse('success', 'notifications.success.category_updated', [], 200);
            } else {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.update_failed', [], 500);
            }
        }
    }

    public function deleteCategory() {
        // Check if user has permission
        $this->requirePermission('menu.categories');

        // Get ID from route parameter or GET
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $categoryId = $queryParams['id'] ?? '';
        if (empty($categoryId)) {
            // Try to get from URL path
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
            if (preg_match('/delete-category\/([^\/]+)/', $path, $matches)) {
                $categoryId = $matches[1];
            }
        }

        if (!empty($categoryId)) {
            // First, check if there are menu items in this category
            $menuItems = $this->menuItemService->getMenuItemsByCategory($categoryId);

            if (count($menuItems) > 0) {
                $this->toastNotificationService->setFlash('error', 'notifications.warning.category_has_items');
                \App\Core\HelperLoader::ensureLoaded();
                header('Location: ' . getAdminUrl('menu'));
                exit;
            }

            $result = $this->categoryService->deleteCategory($categoryId);

            if ($result) {
                // CACHE INVALIDATION: Clear menu cache after category deletion
                $tenantId = \App\Core\TenantContext::getId();
                if ($tenantId && class_exists('\App\Helpers\CacheHelper')) {
                    \App\Helpers\CacheHelper::clearMenuCache($tenantId);
                }
                
                $this->toastNotificationService->setFlash('success', 'notifications.success.category_deleted');
            } else {
                $this->toastNotificationService->setFlash('error', 'notifications.error.delete_failed');
            }
        }

        header('Location: ' . BASE_URL . '/admin/menu');
        exit;
    }

    public function updateAvailability() {
        $this->checkPermissionOrFail('menu.edit');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $requestData = \App\Core\RequestParser::getRequestData();
            $menuItemId = $requestData['menu_item_id'] ?? '';
            $isAvailable = $requestData['is_available'] ?? '';

            if (!empty($menuItemId)) {
                $result = $this->menuItemService->updateMenuItem($menuItemId, ['is_available' => $isAvailable]);

                if ($result) {
                    $this->apiResponse(['success' => true]);
                } else {
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.update_failed', [], 500);
                }
            } else {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            }
        }
    }

    public function updateStock() {
        $this->checkPermissionOrFail('menu.edit');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $requestData = \App\Core\RequestParser::getRequestData();
            $menuItemId = $requestData['menu_item_id'] ?? '';
            $stock = intval($requestData['stock'] ?? 0);

            if (!empty($menuItemId)) {
                $result = $this->menuItemService->updateStock($menuItemId, $stock);

                if ($result) {
                    $this->apiResponse(['success' => true]);
                } else {
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.update_failed', [], 500);
                }
            } else {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            }
        }
    }

    /**
     * Generate menu description using AI
     */
    /**
     * Bulk update prices by percentage
     */
    public function bulkUpdatePrices() {
        if (!$this->hasPermission('menu.edit')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $requestData = \App\Core\RequestParser::getRequestData();
            if ($input) {
                $requestData = array_merge($requestData, $input);
            }

            $percentage = floatval($requestData['percentage'] ?? 0);

            if (round((float)$percentage, 2) === 0.0) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
                return;
            }

            // Get all menu items
            $menuItems = $this->menuItemService->getAllMenuItems();

            if (empty($menuItems)) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.menu_item_not_found', [], 404);
                return;
            }

            $updated = 0;
            $multiplier = 1 + ($percentage / 100);

            foreach ($menuItems as $item) {
                $newPrice = $item['price'] * $multiplier;
                $result = $this->menuItemService->updateMenuItem($item['menu_item_id'], [
                    'price' => round($newPrice, 2)
                ]);

                if ($result) {
                    $updated++;
                }
            }

            $this->apiResponse([
                'success' => true,
                'message' => "{$updated} menü öğesinin fiyatı güncellendi.",
                'updated_count' => $updated
            ]);
        }
    }

    /**
     * Translate menu item using AI
     */
    public function translate() {
        if (!$this->hasPermission('menu.edit')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
                return;
            }

            $turkishContent = [
                'name' => sanitizeInput($input['name'] ?? ''),
                'description' => sanitizeInput($input['description'] ?? ''),
                'ingredients' => $input['ingredients'] ?? [],
                'extras' => $input['extras'] ?? []
            ];

            if (empty($turkishContent['name'])) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_product_name', [], 400);
                return;
            }

            // Get supported languages
            $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
            $supportedLanguagesJson = $settingsService->getSetting('supported_languages', '["tr","en"]');
            $supportedLanguages = json_decode($supportedLanguagesJson, true);
            if (!is_array($supportedLanguages) || empty($supportedLanguages)) {
                $supportedLanguages = ['tr', 'en'];
            }

            // Get category name for SEO
            $categoryName = '';
            if (!empty($input['category_id'])) {
                $category = $this->categoryService->getCategoryById($input['category_id']);
                $categoryName = $category['name'] ?? '';
            }

            // Load helper functions for formatting
            require_once __DIR__ . '/../helpers/text_formatting.php';

            // Use FreeTranslationService for translations (Gemini removed except for dashboard)
            $translations = [];
            $freeTranslationService = \App\Core\DependencyFactory::getFreeTranslationService();

            foreach ($supportedLanguages as $lang) {
                if ($lang === $supportedLanguages[0]) continue; // Skip default language

                $translatedName = null;
                $translatedDescription = null;

                // Translate name
                if (!empty($turkishContent['name'])) {
                    // Try AIService first (if available)
                    if (\App\Services\AIService::isAvailable()) {
                        $translatedName = \App\Services\AIService::translateText($turkishContent['name'], $lang, $supportedLanguages[0]);
                    }

                    // Fallback: FreeTranslationService
                    if (empty($translatedName) && $freeTranslationService) {
                        $translatedName = $freeTranslationService->translateText($turkishContent['name'], $lang, $supportedLanguages[0]);
                    }

                    // Ensure title case formatting is applied (extra safety check)
                    if (!empty($translatedName) && $lang === 'en') {
                        $translatedName = formatMenuTitleCase($translatedName);
                    }
                }

                // Translate description
                if (!empty($turkishContent['description'])) {
                    // Try AIService first (if available)
                    if (\App\Services\AIService::isAvailable()) {
                        $translatedDescription = \App\Services\AIService::translateText($turkishContent['description'], $lang, $supportedLanguages[0]);
                    }

                    // Fallback: FreeTranslationService
                    if (empty($translatedDescription) && $freeTranslationService) {
                        $translatedDescription = $freeTranslationService->translateText($turkishContent['description'], $lang, $supportedLanguages[0]);
                    }

                    // Formatting: Keep natural sentence structure for longer descriptions
                    // Only apply title case to very short descriptions (like single-line menu items)
                    if (!empty($translatedDescription) && $lang === 'en') {
                        // Only apply title case to very short descriptions (less than 50 chars)
                        if (strlen(trim($translatedDescription)) < 50 && !preg_match('/[.!?]$/', trim($translatedDescription))) {
                            $translatedDescription = formatMenuTitleCase($translatedDescription);
                        }
                        // For longer descriptions, ensure proper sentence capitalization (first letter uppercase)
                        else if (strlen($translatedDescription) > 0) {
                            $translatedDescription = ucfirst(trim($translatedDescription));
                        }
                    }
                }

                if ($translatedName || $translatedDescription) {
                    $translations[$lang] = [
                        'name' => $translatedName ?? $turkishContent['name'],
                        'description' => $translatedDescription ?? ($turkishContent['description'] ?? '')
                    ];
                }
            }

            // If still no translations, try AI service for all (using translateMenuItem which has better prompt)
            if (empty($translations) && \App\Services\AIService::isAvailable()) {
                $translationService = \App\Core\DependencyFactory::getMenuItemTranslationService();
                $aiTranslations = $translationService->generateAllTranslations(
                    $turkishContent,
                    $supportedLanguages,
                    $categoryName
                );

                // Merge AI translations with existing ones
                if (!empty($aiTranslations)) {
                    foreach ($aiTranslations as $lang => $trans) {
                        // Ensure title case formatting for English translations
                        if ($lang === 'en') {
                            if (!empty($trans['name'])) {
                                $trans['name'] = formatMenuTitleCase($trans['name']);
                            }
                            if (!empty($trans['description']) && strlen($trans['description']) < 100) {
                                $trans['description'] = formatMenuTitleCase($trans['description']);
                            }
                        }

                        if (!isset($translations[$lang])) {
                            $translations[$lang] = $trans;
                        } else {
                            // Merge, preferring AI translations
                            $translations[$lang]['name'] = $trans['name'] ?? $translations[$lang]['name'];
                            $translations[$lang]['description'] = $trans['description'] ?? $translations[$lang]['description'];
                        }
                    }
                }
            }

            if (empty($translations)) {
                $this->apiResponse([
                    'success' => false,
                    'error' => 'Çeviri oluşturulamadı. Lütfen manuel olarak girin.',
                    'translations' => []
                ], 200);
                return;
            }

            $this->apiResponse([
                'success' => true,
                'translations' => $translations
            ]);
        }
    }

    /**
     * Get menu item by ID with translations (API endpoint)
     */
    public function getMenuItem() {
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $menuItemId = $queryParams['id'] ?? '';

        if (empty($menuItemId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }

        $menuItem = $this->menuItemService->getMenuItemById($menuItemId);
        if (!$menuItem) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.menu_item_not_found', [], 404);
            return;
        }

        // Load variants if item has variants
        if (!empty($menuItem['has_variants']) && $menuItem['has_variants'] == 1) {
            $productVariantService = \App\Core\DependencyFactory::getProductVariantService();
            $menuItem['variants'] = $productVariantService->getActiveVariantsByProduct($menuItemId);
        } else {
            $menuItem['variants'] = [];
        }

        // Parse JSON fields if they exist
        if (!empty($menuItem['ingredients']) && is_string($menuItem['ingredients'])) {
            $menuItem['ingredients'] = json_decode($menuItem['ingredients'], true) ?? [];
        }
        if (!empty($menuItem['extras']) && is_string($menuItem['extras'])) {
            $menuItem['extras'] = json_decode($menuItem['extras'], true) ?? [];
        }
        if (!empty($menuItem['available_extras']) && is_string($menuItem['available_extras'])) {
            $menuItem['available_extras'] = json_decode($menuItem['available_extras'], true) ?? [];
        }

        // Normalize available_extras to extras for consistency
        if (isset($menuItem['available_extras']) && !isset($menuItem['extras'])) {
            $menuItem['extras'] = $menuItem['available_extras'];
        }

        // Get translations if requested
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $includeTranslations = isset($queryParams['translations']) && $queryParams['translations'] === 'true';
        if ($includeTranslations) {
            $translationService = \App\Core\DependencyFactory::getMenuItemTranslationService();
            $translations = $translationService->getTranslationsForEdit($menuItemId);
            $menuItem['translations'] = $translations;
        }

        $this->apiResponse($menuItem);
    }

    /**
     * Translate product name/description (API endpoint)
     * Supports source_language and target_language parameters
     */
    public function translateProductName() {
        if (!$this->hasPermission('menu.edit')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
                return;
            }

            $text = sanitizeInput($input['name'] ?? '');
            $sourceLanguage = $input['source_language'] ?? 'tr';
            $targetLanguage = $input['target_language'] ?? 'en';

            if (empty($text)) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_product_name', [], 400);
                return;
            }

            // If same language, return as is
            if ($sourceLanguage === $targetLanguage) {
                $this->apiResponse([
                    'success' => true,
                    'translated_text' => $text,
                    'name' => $text
                ]);
                return;
            }

            // Check if translation service is available
            try {
                $translatedText = null;

                // Try AIService first
                if (\App\Services\AIService::isAvailable()) {
                    $translatedText = \App\Services\AIService::translateText($text, $targetLanguage, $sourceLanguage);
                }

                // Fallback to FreeTranslationService
                if (empty($translatedText)) {
                    $freeTranslationService = \App\Core\DependencyFactory::getFreeTranslationService();
                    if ($freeTranslationService) {
                        $translatedText = $freeTranslationService->translateText($text, $targetLanguage, $sourceLanguage);
                    }
                }

                if (!empty($translatedText)) {
                    // Apply title case formatting for English names
                    if ($targetLanguage === 'en') {
                        require_once __DIR__ . '/../helpers/text_formatting.php';
                        $translatedText = formatMenuTitleCase($translatedText);
                    }

                    $this->apiResponse([
                        'success' => true,
                        'translated_text' => $translatedText,
                        'name' => $translatedText,
                        'english_name' => $translatedText // For backward compatibility
                    ]);
                } else {
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.translation_failed', [], 500);
                }
            } catch (\Exception $e) {
                \App\Core\Logger::error('Product name translation error: ' . $e->getMessage(), [
                    'text' => $text,
                    'source_language' => $sourceLanguage,
                    'target_language' => $targetLanguage,
                    'trace' => $e->getTraceAsString()
                ]);
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.translation_failed', [], 500);
            }
        }
    }

    /**
     * Translate category name to English (API endpoint)
     */
    public function translateCategoryName() {
        if (!$this->hasPermission('menu.edit')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
                return;
            }

            $turkishName = sanitizeInput($input['name'] ?? '');

            if (empty($turkishName)) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_product_name', [], 400);
                return;
            }

            // Check if translation service is available
            try {
                $englishName = null;

                // Try AIService first
                if (\App\Services\AIService::isAvailable()) {
                    $englishName = \App\Services\AIService::translateText($turkishName, 'en');
                }

                // Fallback: FreeTranslationService
                if (!$englishName || empty(trim($englishName))) {
                    $freeTranslationService = \App\Core\DependencyFactory::getFreeTranslationService();
                    $englishName = $freeTranslationService->translateText($turkishName, 'en', 'tr');
                }

                // Ensure title case formatting is applied (extra safety check)
                if ($englishName && !empty(trim($englishName))) {
                    require_once __DIR__ . '/../helpers/text_formatting.php';
                    $englishName = formatMenuTitleCase($englishName);

                    $this->apiResponse([
                        'success' => true,
                        'english_name' => $englishName
                    ], 200);
                } else {
                    // Translation failed but return graceful response
                    $this->apiResponse([
                        'success' => false,
                        'error' => 'Çeviri yapılamadı. Lütfen İngilizce ismi manuel olarak girin.',
                        'english_name' => null
                    ], 200);
                }
            } catch (\Exception $e) {
                // Log error but return graceful response
                \App\Core\Logger::error('Category translation error: ' . $e->getMessage());
                $this->apiResponse([
                    'success' => false,
                    'error' => 'Çeviri sırasında bir hata oluştu. Lütfen İngilizce ismi manuel olarak girin.',
                    'english_name' => null
                ], 200);
            }
        }
    }

    /**
     * Get translations for a menu item (API endpoint)
     * @param string|null $id Menu item ID from route parameter
     */
    public function getMenuItemTranslations($id = null) {
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $menuItemId = $id ?? $queryParams['id'] ?? '';

        if (empty($menuItemId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }

        $translationService = \App\Core\DependencyFactory::getMenuItemTranslationService();
        $translations = $translationService->getTranslationsForEdit($menuItemId);

        $this->apiResponse([
            'success' => true,
            'translations' => $translations
        ]);
    }

    /**
     * Get product stock history with daily sales and table information
     * GET /api/qodmin/menu-items/{id}/stock-history
     */
    public function getProductStockHistory($id = null) {
        $this->checkPermissionOrFail('menu.view');

        // Check if user is SUPER_ADMIN and handle business_id
        $isSuperAdmin = $this->isSuperAdmin();
        if ($isSuperAdmin) {
            $queryParams = \App\Core\RequestParser::getQueryParams();
            $businessId = $queryParams['business_id'] ?? null;

            if ($businessId) {
                try {
                    $customerService = \App\Core\DependencyFactory::getCustomerService();
                    $customer = $customerService->getById($businessId);
                    if ($customer) {
                        \App\Core\TenantContext::set($customer);
                        $_SESSION['business_id'] = $businessId;
                        $_SESSION['customer_id'] = $businessId;
                    }
                } catch (\Exception $e) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::error('Failed to set tenant context from business_id in MenuController::getProductStockHistory', [
                            'business_id' => $businessId,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
        }

        $queryParams = \App\Core\RequestParser::getQueryParams();
        $menuItemId = $id ?? $queryParams['id'] ?? '';
        $date = $queryParams['date'] ?? date('Y-m-d');

        if (empty($menuItemId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }

        try {
            // Get all order items for this product today
            $db = \App\Core\Database::getInstance();
            $sql = "SELECT
                        oi.order_item_id,
                        oi.order_id,
                        oi.quantity,
                        oi.price,
                        oi.created_at,
                        o.order_id,
                        o.table_id,
                        o.table_name,
                        o.status,
                        o.created_at as order_created_at
                    FROM order_items oi
                    INNER JOIN orders o ON oi.order_id = o.order_id
                    WHERE oi.menu_item_id = :menu_item_id
                    AND DATE(oi.created_at) = :date
                    ORDER BY oi.created_at DESC";

            $stmt = $db->prepare($sql);
            $stmt->execute([
                'menu_item_id' => $menuItemId,
                'date' => $date
            ]);
            $orderItems = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Calculate statistics
            $totalSales = 0;
            $totalQuantity = 0;
            $tableCount = [];
            $salesByHour = [];

            foreach ($orderItems as $item) {
                $totalSales += floatval($item['price']) * intval($item['quantity']);
                $totalQuantity += intval($item['quantity']);

                $tableId = $item['table_id'] ?? 'unknown';
                if (!isset($tableCount[$tableId])) {
                    $tableCount[$tableId] = [
                        'table_id' => $tableId,
                        'table_name' => $item['table_name'] ?? 'Bilinmeyen',
                        'count' => 0,
                        'quantity' => 0
                    ];
                }
                $tableCount[$tableId]['count']++;
                $tableCount[$tableId]['quantity'] += intval($item['quantity']);

                $hour = date('H', strtotime($item['created_at']));
                if (!isset($salesByHour[$hour])) {
                    $salesByHour[$hour] = 0;
                }
                $salesByHour[$hour] += intval($item['quantity']);
            }

            $this->apiResponse([
                'success' => true,
                'date' => $date,
                'statistics' => [
                    'total_sales_count' => count($orderItems),
                    'total_quantity' => $totalQuantity,
                    'total_revenue' => $totalSales,
                    'unique_tables' => count($tableCount)
                ],
                'tables' => array_values($tableCount),
                'sales_by_hour' => $salesByHour,
                'order_items' => $orderItems
            ]);
        } catch (\Exception $e) {
            \App\Core\Logger::error('MenuController::getProductStockHistory - Error', [
                'menu_item_id' => $menuItemId,
                'error' => $e->getMessage()
            ]);
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.load_failed', [], 500);
        }
    }

    /**
     * Organize categories hierarchically for dropdown display
     * @param array $categories Flat categories array
     * @return array Categories organized hierarchically (parent first, then children)
     */
    private function organizeCategoriesHierarchically(array $categories): array {
        // Build category map and parent-child relationships
        $categoryMap = [];
        $parentToChildren = [];
        $parentCategories = [];

        // First pass: index all categories
        foreach ($categories as $category) {
            $catId = $category['category_id'] ?? '';
            $parentId = $category['parent_id'] ?? null;

            $categoryMap[$catId] = $category;

            if (empty($parentId)) {
                // Top-level category
                $parentCategories[] = $category;
            } else {
                // Child category
                if (!isset($parentToChildren[$parentId])) {
                    $parentToChildren[$parentId] = [];
                }
                $parentToChildren[$parentId][] = $category;
            }
        }

        // Sort parent categories by display_order (with name as fallback)
        usort($parentCategories, function($a, $b) {
            $orderA = $a['display_order'] ?? 9999;
            $orderB = $b['display_order'] ?? 9999;
            if ($orderA === $orderB) {
                $nameA = mb_strtolower($a['name'] ?? '', 'UTF-8');
                $nameB = mb_strtolower($b['name'] ?? '', 'UTF-8');
                return strcoll($nameA, $nameB);
            }
            return $orderA - $orderB;
        });

        // Build hierarchical list: parent first, then its children
        $hierarchical = [];
        foreach ($parentCategories as $parent) {
            $parentId = $parent['category_id'] ?? '';
            $hierarchical[] = $parent;

            // Add children of this parent
            if (isset($parentToChildren[$parentId])) {
                // Sort children by display_order (with name as fallback)
                usort($parentToChildren[$parentId], function($a, $b) {
                    $orderA = $a['display_order'] ?? 9999;
                    $orderB = $b['display_order'] ?? 9999;
                    if ($orderA === $orderB) {
                        $nameA = mb_strtolower($a['name'] ?? '', 'UTF-8');
                        $nameB = mb_strtolower($b['name'] ?? '', 'UTF-8');
                        return strcoll($nameA, $nameB);
                    }
                    return $orderA - $orderB;
                });

                foreach ($parentToChildren[$parentId] as $child) {
                    $hierarchical[] = $child;
                }
            }
        }

        // Also add orphaned children (parent not in list)
        foreach ($categories as $category) {
            $parentId = $category['parent_id'] ?? null;
            if (!empty($parentId) && !isset($categoryMap[$parentId])) {
                // Parent not found, add as standalone
                $hierarchical[] = $category;
            }
        }

        return $hierarchical;
    }

    /**
     * Determine preparation screen based on category.
     * - Nargile/drinks/coffee categories -> Nargile screen
     * - Everything else -> Mutfak (kitchen) screen
     */
    private function determinePreparationScreenByCategory(?string $categoryId, ?array $category = null): ?string {
        try {
            if (empty($categoryId)) {
                return null;
            }
            
            // Get category if not provided
            if ($category === null) {
                $category = $this->categoryService->getCategoryById($categoryId);
            }
            
            if (empty($category)) {
                return null;
            }
            
            $categoryName = strtolower(trim($category['name'] ?? ''));
            $categorySlug = strtolower(trim($category['slug'] ?? ''));
            
            // Also check parent category if this is a child
            $parentName = '';
            $parentSlug = '';
            if (!empty($category['parent_id'])) {
                $parentCategory = $this->categoryService->getCategoryById($category['parent_id']);
                if ($parentCategory) {
                    $parentName = strtolower(trim($parentCategory['name'] ?? ''));
                    $parentSlug = strtolower(trim($parentCategory['slug'] ?? ''));
                }
            }
            
            // Categories that should go to Nargile screen (drinks, hookah, coffee, tea)
            $nargileCategoryKeywords = [
                'nargile', 'hookah', 'shisha',
                'içecek', 'icecek', 'drink',
                'kahve', 'coffee', 'cafe',
                'çay', 'cay', 'tea',
                'soğuk içecek', 'soguk icecek', 'cold drink',
                'sıcak içecek', 'sicak icecek', 'hot drink',
                'bitki çayı', 'bitki cayi', 'herbal tea'
            ];
            
            // Check if category or parent matches Nargile keywords
            $isNargileCategory = false;
            foreach ($nargileCategoryKeywords as $keyword) {
                if (strpos($categoryName, $keyword) !== false || 
                    strpos($categorySlug, $keyword) !== false ||
                    strpos($parentName, $keyword) !== false ||
                    strpos($parentSlug, $keyword) !== false) {
                    $isNargileCategory = true;
                    break;
                }
            }
            
            // Get preparation screens
            $preparationScreenService = \App\Core\DependencyFactory::getPreparationScreenService();
            $screens = $preparationScreenService->getActiveScreens();
            
            $mutfakScreenId = null;
            $nargileScreenId = null;
            
            foreach ($screens as $screen) {
                $screenName = strtolower(trim($screen['name'] ?? ''));
                $screenSlug = strtolower(trim($screen['slug'] ?? ''));
                
                // Check for Mutfak/Kitchen screen
                if ($screenName === 'mutfak' || $screenSlug === 'mutfak' || 
                    $screenName === 'kitchen' || $screenSlug === 'kitchen' ||
                    strpos($screenName, 'mutfak') !== false || strpos($screenName, 'kitchen') !== false) {
                    $mutfakScreenId = $screen['screen_id'] ?? null;
                }
                
                // Check for Nargile screen
                if ($screenName === 'nargile' || $screenSlug === 'nargile' ||
                    strpos($screenName, 'nargile') !== false || strpos($screenName, 'hookah') !== false) {
                    $nargileScreenId = $screen['screen_id'] ?? null;
                }
            }
            
            // Return appropriate screen based on category
            if ($isNargileCategory && $nargileScreenId) {
                return $nargileScreenId;
            }
            
            // Default to Mutfak for food items
            return $mutfakScreenId;
            
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('MenuController::determinePreparationScreenByCategory failed', [
                    'category_id' => $categoryId,
                    'error' => $e->getMessage()
                ]);
            }
            return null;
        }
    }
    
    /**
     * Determine a sensible default preparation screen (prefers kitchen/mutfak).
     */
    private function getDefaultPreparationScreenId(): ?string {
        try {
            $preparationScreenService = \App\Core\DependencyFactory::getPreparationScreenService();
            $screens = $preparationScreenService->getActiveScreens();

            $fallbackId = null;
            foreach ($screens as $screen) {
                $fallbackId ??= $screen['screen_id'] ?? null;
                $slug = strtolower($screen['slug'] ?? '');
                $name = strtolower($screen['name'] ?? '');

                // Check for mutfak/kitchen in both slug and name
                if ($slug === 'mutfak' || $slug === 'kitchen' || 
                    $name === 'mutfak' || $name === 'kitchen' ||
                    strpos($slug, 'mutfak') !== false || strpos($slug, 'kitchen') !== false) {
                    return $screen['screen_id'] ?? $fallbackId;
                }
            }

            return $fallbackId;
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('MenuController::getDefaultPreparationScreenId failed', [
                    'error' => $e->getMessage()
                ]);
            }
            return null;
        }
    }
    
    /**
     * Bulk update preparation screens for all menu items based on their categories.
     * POST /business/menu/fix-preparation-screens OR /qodmin/menu/fix-preparation-screens
     */
    public function fixPreparationScreens() {
        try {
            // Require authentication
            if (!$this->isSuperAdmin()) {
                $this->requireAuthentication();
            }
            
            $this->ensureTenantContext();
            $tenantId = \App\Core\TenantContext::getId();
            
            if (!$tenantId && !$this->isSuperAdmin()) {
                $this->toastNotificationService->sendApiResponse('error', 'Tenant ID required', [], 400);
                return;
            }
            
            // Get all menu items
            $menuItems = $this->menuItemService->getAllForCurrentTenant();
            
            if (empty($menuItems)) {
                $this->toastNotificationService->sendApiResponse('success', 'Güncellenecek ürün bulunamadı', ['updated' => 0], 200);
                return;
            }
            
            // Get categories for reference
            $categories = $this->categoryService->getAllForCurrentTenant();
            $categoryMap = [];
            foreach ($categories as $cat) {
                $categoryMap[$cat['category_id']] = $cat;
            }
            
            $updated = 0;
            $errors = 0;
            
            foreach ($menuItems as $item) {
                $menuItemId = $item['menu_item_id'] ?? null;
                $categoryId = $item['category_id'] ?? null;
                
                if (!$menuItemId) {
                    continue;
                }
                
                // Get the category
                $category = $categoryMap[$categoryId] ?? null;
                
                // Determine the correct preparation screen
                $newScreenId = $this->determinePreparationScreenByCategory($categoryId, $category);
                
                // If still null, use default (Mutfak)
                if (empty($newScreenId)) {
                    $newScreenId = $this->getDefaultPreparationScreenId();
                }
                
                // Update if screen ID is different or not set
                $currentScreenId = $item['preparation_screen_id'] ?? null;
                if ($newScreenId && $currentScreenId !== $newScreenId) {
                    try {
                        // Use updateMenuItem to ensure cache clearing
                        $this->menuItemService->updateMenuItem($menuItemId, [
                            'preparation_screen_id' => $newScreenId
                        ]);
                        $updated++;
                    } catch (\Exception $e) {
                        $errors++;
                        if (class_exists('\App\Core\Logger')) {
                            \App\Core\Logger::error('Failed to update menu item preparation screen', [
                                'menu_item_id' => $menuItemId,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                }
            }
            
            $message = "Hazırlık ekranları güncellendi. {$updated} ürün güncellendi.";
            if ($errors > 0) {
                $message .= " {$errors} ürün güncellenemedi.";
            }
            
            $this->toastNotificationService->sendApiResponse('success', $message, [
                'updated' => $updated,
                'errors' => $errors,
                'total' => count($menuItems)
            ], 200);
            
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('MenuController::fixPreparationScreens failed', [
                    'error' => $e->getMessage()
                ]);
            }
            $this->toastNotificationService->sendApiResponse('error', 'Güncelleme başarısız: ' . $e->getMessage(), [], 500);
        }
    }
}