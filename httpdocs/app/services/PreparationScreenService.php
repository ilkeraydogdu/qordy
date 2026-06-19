<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\PreparationScreenRepository;
use App\Core\DependencyFactory;

/**
 * Preparation Screen Service
 * Handles business logic for preparation screens
 */
class PreparationScreenService extends BaseService {
    protected $orderService;
    protected $orderItemService;
    protected $menuItemService;
    protected $categoryService;
    protected $permissionModel;
    protected $roleService;
    
    public function __construct(PreparationScreenRepository $repository) {
        parent::__construct($repository);
        $this->orderService = DependencyFactory::getOrderService();
        $this->orderItemService = DependencyFactory::getOrderItemService();
        $this->menuItemService = DependencyFactory::getMenuItemService();
        $this->categoryService = DependencyFactory::getCategoryService();
        $this->permissionModel = DependencyFactory::getPermissionModel();
        $this->roleService = DependencyFactory::getRoleService();
    }
    
    /**
     * Get all screens
     *
     * Tenant isolation is handled at the repository layer via getTenantFilter()
     * (which automatically targets either `tenant_id` or `business_id` based on
     * the table schema). An additional service-level filter would previously
     * strip rows because it hard-coded the `tenant_id` key — this caused the
     * screen list to appear empty even after successful inserts.
     *
     * @return array Screens
     */
    public function getAllScreens(): array {
        $screens = $this->repository->getAllOrdered();

        // Defensive filter that supports both possible tenant columns.
        // Safe no-op when repository has already filtered.
        $tenantId = null;
        if (class_exists('\\App\\Core\\TenantContext')) {
            try {
                $tenantId = \App\Core\TenantContext::getId();
            } catch (\Exception $e) {
                // TenantContext not available
            }
        }

        if ($tenantId) {
            $screens = array_values(array_filter($screens, function ($screen) use ($tenantId) {
                $screenTenantId = $screen['tenant_id'] ?? $screen['business_id'] ?? null;
                // Allow legacy rows without any tenant column to pass through —
                // the repository already constrained the query, so they are safe.
                if ($screenTenantId === null) {
                    return true;
                }
                return (string)$screenTenantId === (string)$tenantId;
            }));
        }

        return $screens;
    }
    
    /**
     * Get active screens
     * @return array Active screens
     */
    public function getActiveScreens(): array {
        return $this->repository->getActive();
    }
    
    /**
     * Get screen by ID
     * @param string $screenId Screen ID
     * @return array|null Screen data or null
     */
    public function getScreenById(string $screenId): ?array {
        return $this->repository->findById($screenId);
    }
    
    /**
     * Get screen by slug
     * @param string $slug Screen slug
     * @return array|null Screen data or null
     */
    public function getScreenBySlug(string $slug): ?array {
        return $this->repository->getBySlug($slug);
    }
    
    /**
     * Get screen categories
     * @param string $screenId Screen ID
     * @return array Categories
     */
    public function getScreenCategories(string $screenId): array {
        return $this->repository->getScreenCategories($screenId);
    }
    
    /**
     * Get screen category IDs
     * @param string $screenId Screen ID
     * @return array Category IDs
     */
    public function getScreenCategoryIds(string $screenId): array {
        return $this->repository->getScreenCategoryIds($screenId);
    }
    
    /**
     * Get orders for a specific screen (filtered by assigned categories)
     * @param string $screenId Screen ID
     * @return array Orders with items (only orders containing items from screen's assigned categories)
     */
    public function getOrdersForScreen(string $screenId): array {
        // Get screen data
        $screen = $this->getScreenById($screenId);

        if (!$screen) {
            // Screen not found, return empty array
            return [];
        }

        // Get screen's assigned categories
        $screenCategories = $this->getScreenCategories($screenId);

        // Extract category IDs
        $screenCategoryIds = array_column($screenCategories, 'category_id');
        $isNargileScreen = $this->isNargileScreen($screen);

        // Get active orders
        $activeOrders = $this->orderService->getActiveOrders();

        if (empty($activeOrders)) {
            return [];
        }

        // Get order IDs for batch loading
        $orderIds = array_column($activeOrders, 'order_id');

        // Load orders with items (optimized - prevents N+1)
        $ordersWithItems = $this->orderService->getOrdersWithItems($orderIds);

        // Filter orders that contain items assigned to this screen
        // Priority: 1) preparation_screen_id (new dynamic system), 2) category-based (backward compatibility)
        $filteredOrders = [];
        foreach ($ordersWithItems as $order) {
            $hasMatchingItem = false;

            // Check if order has any items assigned to this screen
            foreach ($order['items'] ?? [] as $item) {
                $menuItemId = $item['menu_item_id'] ?? '';
                if (empty($menuItemId)) {
                    continue;
                }

                // Get menu item to check assignment
                $menuItem = $this->menuItemService->getMenuItemById($menuItemId);
                if (!$menuItem) {
                    continue;
                }

                // Skip direct service products (production_point = NONE or is_direct_service = 1)
                $productionPoint = $menuItem['production_point'] ?? null;
                $isDirectService = !empty($menuItem['is_direct_service']) && $menuItem['is_direct_service'] == 1;
                if ($productionPoint === 'NONE' || $isDirectService) {
                    continue; // Skip this item - it doesn't need preparation
                }

                // Priority 1: Check if menu item is directly assigned to this screen (new dynamic system)
                $itemPreparationScreenId = $menuItem['preparation_screen_id'] ?? null;
                if (!empty($itemPreparationScreenId) && $itemPreparationScreenId === $screenId) {
                    $hasMatchingItem = true;
                    break;
                }

                // Priority 2: Fallback to category-based assignment (backward compatibility)
                // Only check category if preparation_screen_id is not set
                if (empty($itemPreparationScreenId)) {
                    $itemCategoryId = $menuItem['category_id'] ?? '';
                    if (!empty($itemCategoryId) && in_array($itemCategoryId, $screenCategoryIds)) {
                        $hasMatchingItem = true;
                        break;
                    }
                    
                    // Nargile fallback: match by category keywords if screen is nargile
                    if ($isNargileScreen && $this->isNargileCategory($itemCategoryId)) {
                        $hasMatchingItem = true;
                        break;
                    }
                }
            }

            if ($hasMatchingItem) {
                // Filter items to only show items assigned to this screen
                $filteredItems = [];
                foreach ($order['items'] ?? [] as $item) {
                    $menuItemId = $item['menu_item_id'] ?? '';
                    if (empty($menuItemId)) {
                        continue;
                    }

                    $menuItem = $this->menuItemService->getMenuItemById($menuItemId);
                    if (!$menuItem) {
                        continue;
                    }

                    // Skip direct service products (production_point = NONE or is_direct_service = 1)
                    $productionPoint = $menuItem['production_point'] ?? null;
                    $isDirectService = !empty($menuItem['is_direct_service']) && $menuItem['is_direct_service'] == 1;
                    if ($productionPoint === 'NONE' || $isDirectService) {
                        continue; // Skip this item - it doesn't need preparation
                    }

                    // Priority 1: Check if menu item is directly assigned to this screen
                    $itemPreparationScreenId = $menuItem['preparation_screen_id'] ?? null;
                    if (!empty($itemPreparationScreenId) && $itemPreparationScreenId === $screenId) {
                        $filteredItems[] = $item;
                        continue;
                    }

                    // Priority 2: Fallback to category-based assignment (only if preparation_screen_id is not set)
                    if (empty($itemPreparationScreenId)) {
                        $itemCategoryId = $menuItem['category_id'] ?? '';
                        if (!empty($itemCategoryId) && in_array($itemCategoryId, $screenCategoryIds)) {
                            $filteredItems[] = $item;
                            continue;
                        }
                        
                        // Nargile fallback: match by category keywords if screen is nargile
                        if ($isNargileScreen && $this->isNargileCategory($itemCategoryId)) {
                            $filteredItems[] = $item;
                        }
                    }
                }

                // Only add order if it has filtered items
                if (!empty($filteredItems)) {
                    $order['items'] = $filteredItems;
                    $filteredOrders[] = $order;
                }
            }
        }

        // NOTE: Kitchen/Mutfak is handled by KitchenController (/business/kitchen, /qodmin/kitchen)
        // Preparation screens are ONLY for non-kitchen screens (Nargile, Bar, etc.)
        // Items without preparation_screen_id are routed to kitchen_main, not to preparation screens

        // Sort by created_at (ASC - oldest first)
        usort($filteredOrders, function($a, $b) {
            $timeA = strtotime($a['created_at'] ?? 'now');
            $timeB = strtotime($b['created_at'] ?? 'now');
            return $timeA - $timeB;
        });

        return $filteredOrders;
    }

    /**
     * Get order_item_id list for items that belong to this screen (for preparation_status update)
     * @param string $orderId Order ID
     * @param string $screenId Screen ID (e.g. kitchen_main or preparation_screens.screen_id)
     * @return array Order item IDs
     */
    public function getOrderItemIdsForScreen(string $orderId, string $screenId): array {
        $order = $this->orderService->getOrderById($orderId);
        if (!$order) {
            return [];
        }
        $orderItems = $this->orderItemService->getOrderItemsByOrder($orderId);
        if (empty($orderItems)) {
            return [];
        }
        $isKitchenMain = ($screenId === 'kitchen_main');
        $screen = $isKitchenMain ? null : $this->getScreenById($screenId);
        $screenCategoryIds = [];
        $isNargileScreen = false;
        if ($screen) {
            $screenCategories = $this->getScreenCategories($screenId);
            $screenCategoryIds = array_column($screenCategories, 'category_id');
            $isNargileScreen = $this->isNargileScreen($screen);
        }
        $itemIds = [];
        foreach ($orderItems as $item) {
            $menuItemId = $item['menu_item_id'] ?? '';
            if (empty($menuItemId)) {
                continue;
            }
            $menuItem = $this->menuItemService->getMenuItemById($menuItemId);
            if (!$menuItem) {
                continue;
            }
            $productionPoint = $menuItem['production_point'] ?? null;
            $isDirectService = !empty($menuItem['is_direct_service']) && $menuItem['is_direct_service'] == 1;
            if ($productionPoint === 'NONE' || $isDirectService) {
                continue;
            }
            $itemPreparationScreenId = $menuItem['preparation_screen_id'] ?? null;
            if ($isKitchenMain) {
                if (!empty($itemPreparationScreenId) && $itemPreparationScreenId === 'kitchen_main') {
                    $itemIds[] = $item['order_item_id'];
                } elseif (empty($itemPreparationScreenId) && ($productionPoint === 'KITCHEN' || (isset($menuItem['requires_kitchen']) && (int)$menuItem['requires_kitchen'] === 1))) {
                    $itemIds[] = $item['order_item_id'];
                } elseif (empty($itemPreparationScreenId) && !empty($menuItem['category_id'])) {
                    $category = $this->categoryService->getCategoryById($menuItem['category_id']);
                    if ($category && ($category['default_production_point'] ?? '') === 'KITCHEN') {
                        $itemIds[] = $item['order_item_id'];
                    }
                }
                continue;
            }
            if (!empty($itemPreparationScreenId) && $itemPreparationScreenId === $screenId) {
                $itemIds[] = $item['order_item_id'];
                continue;
            }
            if (empty($itemPreparationScreenId)) {
                $itemCategoryId = $menuItem['category_id'] ?? '';
                if (!empty($itemCategoryId) && in_array($itemCategoryId, $screenCategoryIds)) {
                    $itemIds[] = $item['order_item_id'];
                    continue;
                }
                if ($isNargileScreen && $this->isNargileCategory($itemCategoryId)) {
                    $itemIds[] = $item['order_item_id'];
                }
            }
        }
        return $itemIds;
    }
    
    /**
     * Reserved slugs that cannot be used for preparation screens
     * These are handled by dedicated controllers (KitchenController, etc.)
     */
    private const RESERVED_SLUGS = ['mutfak', 'kitchen', 'kasa', 'cashier', 'garson', 'waiter'];
    
    /**
     * Create a new screen
     * @param array $data Screen data
     * @return string|false Screen ID on success, false on failure
     */
    public function createScreen(array $data): string|false {
        // Generate screen_id if not provided
        if (empty($data['screen_id'])) {
            $data['screen_id'] = 'SCR_' . strtoupper(uniqid());
        }
        
        // Validate slug
        $slug = $data['slug'] ?? '';
        if (empty($slug)) {
            // Generate slug from name
            $name = $data['name'] ?? '';
            $slug = $this->generateSlug($name);
            $data['slug'] = $slug;
        }
        
        // Check for reserved slugs (kitchen, cashier, waiter are handled by dedicated controllers)
        if (in_array(strtolower($slug), self::RESERVED_SLUGS)) {
            \App\Core\Logger::warning('PreparationScreenService: Attempted to create screen with reserved slug', [
                'slug' => $slug,
                'reserved_slugs' => self::RESERVED_SLUGS
            ]);
            return false; // Reserved slug - cannot create
        }
        
        // Check if slug already exists
        if ($this->repository->slugExists($slug)) {
            return false; // Slug already exists
        }
        
        // Extract category_ids before creating (not a column in preparation_screens table)
        $categoryIds = $data['category_ids'] ?? [];
        unset($data['category_ids']);
        
        // Create screen
        $result = $this->repository->create($data);
        
        if ($result) {
            $screenId = $data['screen_id'];
            
            // Assign categories if provided
            if (!empty($categoryIds) && is_array($categoryIds)) {
                $this->assignCategories($screenId, $categoryIds);
            }
            
            // Create permissions for this screen
            $this->createScreenPermissions($screenId, $slug);
            
            return $screenId;
        }
        
        return false;
    }
    
    /**
     * Update screen
     * @param string $screenId Screen ID
     * @param array $data Screen data
     * @return bool Success
     */
    public function updateScreen(string $screenId, array $data): bool {
        // Check if slug is being changed
        if (isset($data['slug'])) {
            $slug = $data['slug'];
            if ($this->repository->slugExists($slug, $screenId)) {
                return false; // Slug already exists
            }
        }
        
        // Extract category_ids before updating (not a column in preparation_screens table)
        $categoryIds = $data['category_ids'] ?? [];
        unset($data['category_ids']);
        
        // Update screen
        $result = $this->repository->update($screenId, $data);
        
        if ($result && !empty($categoryIds) && is_array($categoryIds)) {
            // Update categories
            $this->assignCategories($screenId, $categoryIds);
        }
        
        return $result;
    }
    
    /**
     * Delete screen
     * @param string $screenId Screen ID
     * @return bool Success
     */
    public function deleteScreen(string $screenId): bool {
        // Get screen to get slug for permission cleanup
        $screen = $this->getScreenById($screenId);
        $slug = $screen['slug'] ?? '';
        
        // Delete screen (cascade will handle categories)
        $result = $this->repository->delete($screenId);
        
        if ($result && !empty($slug)) {
            // Delete permissions
            $this->deleteScreenPermissions($slug);
        }
        
        return $result;
    }
    
    /**
     * Assign categories to screen
     * @param string $screenId Screen ID
     * @param array $categoryIds Array of category IDs
     * @return bool Success
     */
    public function assignCategories(string $screenId, array $categoryIds): bool {
        return $this->repository->assignCategories($screenId, $categoryIds);
    }
    
    /**
     * Generate slug from name
     * @param string $name Name to convert to slug
     * @return string Slug
     */
    private function generateSlug(string $name): string {
        // Convert Turkish characters
        $turkish = ['ç', 'ğ', 'ı', 'ö', 'ş', 'ü', 'Ç', 'Ğ', 'İ', 'Ö', 'Ş', 'Ü'];
        $english = ['c', 'g', 'i', 'o', 's', 'u', 'C', 'G', 'I', 'O', 'S', 'U'];
        $name = str_replace($turkish, $english, $name);
        
        // Convert to lowercase
        $name = mb_strtolower($name);
        
        // Replace spaces and special characters with hyphens
        $name = preg_replace('/[^a-z0-9]+/', '-', $name);
        
        // Remove leading/trailing hyphens
        $name = trim($name, '-');
        
        return $name;
    }
    
    /**
     * Create permissions for a screen
     * @param string $screenId Screen ID
     * @param string $slug Screen slug
     * @return void
     */
    private function createScreenPermissions(string $screenId, string $slug): void {
        $permissions = [
            [
                'permission_id' => "preparation-screen.{$slug}.view",
                'permission_key' => "preparation-screen.{$slug}.view",
                'permission_name' => "View {$slug} Preparation Screen",
                'description' => "View orders for {$slug} preparation screen"
            ],
            [
                'permission_id' => "preparation-screen.{$slug}.update_status",
                'permission_key' => "preparation-screen.{$slug}.update_status",
                'permission_name' => "Update Status for {$slug} Screen",
                'description' => "Update order status for {$slug} preparation screen"
            ]
        ];
        
        $createdPermissionIds = [];
        foreach ($permissions as $perm) {
            try {
                // Check if permission already exists
                $existing = $this->permissionModel->getByKey($perm['permission_key']);
                if (!$existing) {
                    $this->permissionModel->create($perm);
                    $createdPermissionIds[] = $perm['permission_id'];
                } else {
                    $createdPermissionIds[] = $existing['permission_id'];
                }
            } catch (\Exception $e) {
                error_log("Failed to create permission {$perm['permission_key']}: " . $e->getMessage());
            }
        }
        
        // Automatically assign permissions to KITCHEN role (and other relevant roles)
        if (!empty($createdPermissionIds)) {
            $this->assignPermissionsToRoles($createdPermissionIds, $slug);
        }
    }
    
    /**
     * Assign permissions to relevant roles automatically
     * @param array $permissionIds Permission IDs to assign
     * @param string $slug Screen slug
     * @return void
     */
    private function assignPermissionsToRoles(array $permissionIds, string $slug): void {
        try {
            // Get roles that should have preparation screen permissions
            // BUSINESS_MANAGER added because they create and manage preparation screens
            $kitchenRole = $this->roleService->getByRoleCode('KITCHEN');
            $managerRole = $this->roleService->getByRoleCode('MANAGER');
            $businessManagerRole = $this->roleService->getByRoleCode('BUSINESS_MANAGER');
            
            $rolesToAssign = [];
            if ($kitchenRole) {
                $rolesToAssign[] = $kitchenRole['role_id'];
            }
            if ($managerRole) {
                $rolesToAssign[] = $managerRole['role_id'];
            }
            if ($businessManagerRole) {
                $rolesToAssign[] = $businessManagerRole['role_id'];
            }
            
            // Assign permissions to each role
            foreach ($rolesToAssign as $roleId) {
                foreach ($permissionIds as $permissionId) {
                    try {
                        // Check if permission is already assigned
                        $currentPermissions = $this->roleService->getRolePermissionKeys($roleId);
                        if (!in_array($permissionId, $currentPermissions)) {
                            $this->roleService->assignPermission($roleId, $permissionId);
                        }
                    } catch (\Exception $e) {
                        error_log("Failed to assign permission {$permissionId} to role {$roleId}: " . $e->getMessage());
                    }
                }
            }
        } catch (\Exception $e) {
            error_log("Failed to assign permissions to roles: " . $e->getMessage());
        }
    }
    
    /**
     * Delete permissions for a screen
     * @param string $slug Screen slug
     * @return void
     */
    private function deleteScreenPermissions(string $slug): void {
        $permissionKeys = [
            "preparation-screen.{$slug}.view",
            "preparation-screen.{$slug}.update_status"
        ];

        foreach ($permissionKeys as $permissionKey) {
            try {
                $permission = $this->permissionModel->getByKey($permissionKey);
                if ($permission) {
                    $this->permissionModel->deletePermission($permission['permission_id']);
                }
            } catch (\Exception $e) {
                error_log("Failed to delete permission {$permissionKey}: " . $e->getMessage());
            }
        }
    }

    /**
     * Get all screens with their assigned printers
     * @param string $businessId Business ID for tenant isolation
     * @return array Screens with assigned printers
     */
    public function getScreensWithAssignedPrinters(string $businessId): array {
        // Get all screens for the business
        $screens = $this->getActiveScreens();

        // Get all printers for the business
        $printerService = \App\Core\DependencyFactory::getPrinterService();
        $printers = $printerService->getAllPrintersWithZones($businessId);

        // Group printers by preparation screen
        $printersByScreen = [];
        foreach ($printers as $printer) {
            $screenId = $printer['preparation_screen_id'] ?? null;
            if ($screenId) {
                if (!isset($printersByScreen[$screenId])) {
                    $printersByScreen[$screenId] = [];
                }
                $printersByScreen[$screenId][] = $printer;
            }
        }

        // Attach printers to screens
        foreach ($screens as &$screen) {
            $screenId = $screen['screen_id'] ?? '';
            $screen['assigned_printers'] = $printersByScreen[$screenId] ?? [];
        }

        return $screens;
    }

    private function isNargileScreen(array $screen): bool {
        $screenName = strtolower(trim($screen['name'] ?? ''));
        $screenSlug = strtolower(trim($screen['slug'] ?? ''));
        return ($screenName === 'nargile' || $screenSlug === 'nargile' ||
            strpos($screenName, 'nargile') !== false || strpos($screenSlug, 'nargile') !== false ||
            strpos($screenName, 'hookah') !== false || strpos($screenSlug, 'hookah') !== false);
    }

    private function isNargileCategory(?string $categoryId): bool {
        if (empty($categoryId)) {
            return false;
        }
        
        $category = $this->categoryService->getCategoryById($categoryId);
        if (!$category) {
            return false;
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
        
        // SADECE nargile/hookah/shisha anahtar kelimeleri kullan
        // İçecek, kahve, çay gibi genel kategoriler nargile ekranına gitmemeli!
        // Bu kategoriler mutfak veya bar ekranına aittir.
        // Nargile ürünleri doğru yönlendirilmek için:
        //   1. menu_items.preparation_screen_id ile doğrudan atanmalı (en iyi yol)
        //   2. Nargile kategorisi preparation_screen_categories ile ekrana bağlanmalı
        //   3. Bu fallback SADECE "nargile" kelimesini içeren kategoriler için geçerli
        $nargileCategoryKeywords = [
            'nargile', 'hookah', 'shisha'
        ];
        
        foreach ($nargileCategoryKeywords as $keyword) {
            if (strpos($categoryName, $keyword) !== false ||
                strpos($categorySlug, $keyword) !== false ||
                strpos($parentName, $keyword) !== false ||
                strpos($parentSlug, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }
}

