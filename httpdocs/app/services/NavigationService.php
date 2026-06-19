<?php
namespace App\Services;

require_once __DIR__ . '/../core/BaseService.php';
require_once __DIR__ . '/../models/NavigationItem.php';
require_once __DIR__ . '/RoleMapper.php';
require_once __DIR__ . '/../core/Authorization.php';
require_once __DIR__ . '/../core/DependencyFactory.php';

use App\Models\NavigationItem;
use App\Core\Authorization;

/**
 * Navigation Service
 * Centralized service for loading and filtering navigation/sidebar items
 */
class NavigationService {
    private $db = null;
    private $navigationModel = null;
    private $auth = null;
    private $roleMapper = null;
    private $cache = [];
    private $cacheExpiry = 300; // 5 dakika

    public function __construct() {
        try {
            $this->db = \App\Core\DependencyFactory::getDatabase();
            $this->navigationModel = new NavigationItem($this->db);
            $this->auth = Authorization::getInstance();
            $this->roleMapper = RoleMapper::getInstance();
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('NavigationService: Initialization failed', ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Get navigation items for current user
     * Loads directly from database (NO FALLBACK)
     * Filters by role and permissions
     *
     * @param bool $useCache Use cache if available
     * @return array Filtered navigation items
     */
    public function getNavigationItems(bool $useCache = true): array {
        // Check if Super Admin - bypass cache for Super Admin
        $isSuperAdminCheck = $this->isSuperAdminCheck();
        if ($isSuperAdminCheck) {
            $useCache = false;
        }

        // Get cache key based on user role and permissions
        $cacheKey = $this->getCacheKey();

        // Check cache
        if ($useCache && isset($this->cache[$cacheKey])) {
            $cached = $this->cache[$cacheKey];
            if (time() - $cached['timestamp'] < $this->cacheExpiry) {
                $cachedData = $cached['data'];
                // If cache contains empty array, clear cache and reload
                if (empty($cachedData) || !is_array($cachedData)) {
                    unset($this->cache[$cacheKey]);
                } else {
                    return $cachedData;
                }
            }
        }

        // Load navigation items from database (NO FALLBACK)
        try {
            $items = $this->loadNavigationItemsFromDatabase();

            if (empty($items) || !is_array($items)) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('NavigationService: No navigation items found in database! Please run navigation seed script.', [
                        'is_super_admin' => $isSuperAdminCheck,
                        'user_id' => $_SESSION['user_id'] ?? null,
                        'role' => $_SESSION['role'] ?? null,
                        'role_id' => $_SESSION['role_id'] ?? null
                    ]);
                }
                return [];
            }
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('NavigationService: Failed to load navigation items from database', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'is_super_admin' => $isSuperAdminCheck
                ]);
            }
            return [];
        }

        // Get current user role
        $currentRoleId = $_SESSION['role_id'] ?? null;
        $currentRoleCode = $_SESSION['role'] ?? null;

        // Normalize roles
        $this->normalizeRoles($currentRoleId, $currentRoleCode);

        // Check if user is Super Admin (for bypass - super admin has access to everything)
        $isSuperAdmin = false;

        // First check session flag (most reliable)
        $isSuperAdminSession = \App\Core\SessionManager::get('is_super_admin') === true;
        if ($isSuperAdminSession) {
            $isSuperAdmin = true;
        }

        // Then check role code
        if (!$isSuperAdmin && $currentRoleCode) {
            $normalizedRoleCode = strtoupper(str_replace('ROLE_', '', trim($currentRoleCode)));
            if ($normalizedRoleCode === 'SUPER_ADMIN' || $normalizedRoleCode === 'QODMIN') {
                $isSuperAdmin = true;
            }
        }

        // Also check role_id
        if (!$isSuperAdmin && $currentRoleId) {
            $normalizedRoleId = strtoupper(str_replace('ROLE_', '', trim($currentRoleId)));
            if ($normalizedRoleId === 'SUPER_ADMIN' || $normalizedRoleId === 'QODMIN') {
                $isSuperAdmin = true;
            }
        }

        // Check Authorization method
        if (!$isSuperAdmin && $this->auth && $this->auth->isLoggedIn()) {
            try {
                $isSuperAdmin = $this->auth->isSuperAdmin();
            } catch (\Exception $e) {
                // Ignore exception, use previous checks
            }
        }

        // Additional check: Check if role contains SUPER_ADMIN or QODMIN in any form
        if (!$isSuperAdmin) {
            $allRoleSources = [
                $currentRoleCode,
                $currentRoleId,
                $_SESSION['role'] ?? null,
                $_SESSION['role_id'] ?? null
            ];
            foreach ($allRoleSources as $roleSource) {
                if ($roleSource && (stripos($roleSource, 'SUPER_ADMIN') !== false || stripos($roleSource, 'QODMIN') !== false)) {
                    $isSuperAdmin = true;
                    break;
                }
            }
        }

        // Debug logging (ALWAYS log for super admin debugging)
        if (class_exists('\App\Core\Logger')) {
            \App\Core\Logger::debug('NavigationService: Super admin check', [
                'is_super_admin_session' => $isSuperAdminSession,
                'current_role_code' => $currentRoleCode,
                'normalized_role_code' => isset($normalizedRoleCode) ? $normalizedRoleCode : null,
                'is_super_admin' => $isSuperAdmin,
                'session_data' => [
                    'logged_in' => $_SESSION['logged_in'] ?? null,
                    'user_id' => $_SESSION['user_id'] ?? null,
                    'role' => $_SESSION['role'] ?? null
                ]
            ]);
        }

        // Check if user is Manager (for bypass)
        $normalizedRoleCode = $currentRoleCode ? strtoupper(str_replace('ROLE_', '', trim($currentRoleCode))) : null;
        $isManager = $this->isManagerRole($normalizedRoleCode, $currentRoleId);
        // BUSINESS_OWNER is the default role we assign on self-registration
        // (see CustomerService::register). It MUST be treated the same as
        // BUSINESS_MANAGER for navigation / subscription gating, otherwise
        // freshly-registered owners see a "buy package" screen even though
        // they already have an active trial subscription.
        $isBusinessManager = in_array($normalizedRoleCode, ['BUSINESS_MANAGER', 'BUSINESS_OWNER'], true);
        $isTrial = $normalizedRoleCode === 'TRIAL';

        // Debug logging for role checks

        // Also check if user is Super Admin (should have manager-like access)
        if (!$isSuperAdmin) {
            if ($normalizedRoleCode === 'SUPER_ADMIN' || $normalizedRoleCode === 'QODMIN') {
                $isSuperAdmin = true;
            }
            if (!$isSuperAdmin && $currentRoleId) {
                $normalizedRoleId = strtoupper(str_replace('ROLE_', '', trim($currentRoleId)));
                if ($normalizedRoleId === 'SUPER_ADMIN' || $normalizedRoleId === 'QODMIN') {
                    $isSuperAdmin = true;
                }
            }
        }

        // Treat Super Admin as Manager for navigation purposes
        if ($isSuperAdmin) {
            $isManager = true;
        }

        // Filter navigation items
        try {
            $navigationItems = $this->filterNavigationItems($items, $isManager, $isBusinessManager, $isSuperAdmin, $currentRoleId, $currentRoleCode, $normalizedRoleCode, $isTrial);

            // Process and sort items
            $navigationItems = $this->processNavigationItems($navigationItems);

            // Only cache if we have items (don't cache empty arrays)
            if (!empty($navigationItems) && is_array($navigationItems)) {
                $this->cache[$cacheKey] = [
                    'data' => $navigationItems,
                    'timestamp' => time()
                ];
            }

            return $navigationItems;
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('NavigationService: Failed to filter/process navigation items', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'items_count' => is_array($items) ? count($items) : 0
                ]);
            }
            return [];
        }
    }

    /**
     * Load navigation items directly from database
     *
     * @return array Navigation items from database
     */
    private function loadNavigationItemsFromDatabase(): array {
        if (!$this->navigationModel) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('NavigationService: NavigationModel not initialized');
            }
            return [];
        }

        try {
            // Check if Super Admin - if so, load all items including inactive
            $isSuperAdminCheck = $this->isSuperAdminCheck();

            // Load all navigation items from database
            // For Super Admin, include inactive items
            $dbItems = $this->navigationModel->getNavigationAsArray(null, $isSuperAdminCheck);

            if (!empty($dbItems) && is_array($dbItems)) {
                return $dbItems;
            }
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('NavigationService: Failed to load from database', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        return [];
    }

    /**
     * Normalize roles (role_code <-> role_id conversion)
     *
     * @param string|null $currentRoleId
     * @param string|null $currentRoleCode
     */
    private function normalizeRoles(?string &$currentRoleId, ?string &$currentRoleCode): void {
        // Normalize role_code to role_id if needed
        if (!$currentRoleId && $currentRoleCode) {
            try {
                $roleId = $this->roleMapper->getRoleId($currentRoleCode);
                if ($roleId) {
                    $currentRoleId = $roleId;
                    $_SESSION['role_id'] = $roleId;
                }
            } catch (\Exception $e) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning('NavigationService: Could not map role_code to role_id', [
                        'role_code' => $currentRoleCode,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        // Get role_code from role_id if we have role_id but not role_code
        if ($currentRoleId && !$currentRoleCode) {
            try {
                $roleCode = $this->roleMapper->getRoleCode($currentRoleId);
                if ($roleCode) {
                    $currentRoleCode = $roleCode;
                    $_SESSION['role'] = $roleCode;
                }
            } catch (\Exception $e) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning('NavigationService: Could not map role_id to role_code', [
                        'role_id' => $currentRoleId,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
    }

    /**
     * Check if user has manager role
     * BUSINESS_MANAGER is NOT treated as MANAGER here
     * Only real restaurant managers (MANAGER, ADMIN, ADMINISTRATOR) get full access
     * BUSINESS_MANAGER (customers) must use package-based permissions
     *
     * @param string|null $normalizedRoleCode
     * @param string|null $currentRoleId
     * @return bool
     */
    private function isManagerRole(?string $normalizedRoleCode, ?string $currentRoleId): bool {
        // BUSINESS_MANAGER is EXCLUDED from manager roles
        // BUSINESS_MANAGER users are customers and must have package-based access
        // Only real restaurant managers (MANAGER, ADMIN, ADMINISTRATOR) get full access without package restrictions
        $managerRoles = ['MANAGER', 'ADMIN', 'ADMINISTRATOR']; // BUSINESS_MANAGER REMOVED

        if ($normalizedRoleCode && in_array($normalizedRoleCode, $managerRoles)) {
            return true;
        }

        $managerRoleIds = ['ROLE_MANAGER', 'ROLE_ADMIN', 'ROLE_ADMINISTRATOR']; // ROLE_BUSINESS_MANAGER REMOVED
        if ($currentRoleId && in_array($currentRoleId, $managerRoleIds)) {
            return true;
        }

        return false;
    }

    /**
     * Filter navigation items based on role and permissions
     *
     * @param array $items Navigation items from config
     * @param bool $isManager Is user manager
     * @param string|null $currentRoleId Current role ID
     * @param string|null $currentRoleCode Current role code
     * @param string|null $normalizedRoleCode Normalized role code
     * @return array Filtered navigation items
     */
    private function filterNavigationItems(array $items, bool $isManager, bool $isBusinessManager, bool $isSuperAdmin, ?string $currentRoleId, ?string $currentRoleCode, ?string $normalizedRoleCode, bool $isTrial = false): array {
        if (!is_array($items)) {
            $items = [];
        }

        // Nav IDs that are permanently hidden from every role (duplicates of a
        // parent link that re-routes to the same dashboard). Applying this
        // before the super-admin early-return keeps super admins from seeing
        // them too.
        $globallyHiddenIds = ['FINANCE_MAIN'];
        $stripHidden = function(array $list) use (&$stripHidden, $globallyHiddenIds): array {
            $out = [];
            foreach ($list as $item) {
                if (in_array($item['id'] ?? '', $globallyHiddenIds, true)) {
                    continue;
                }
                if (!empty($item['children']) && is_array($item['children'])) {
                    $item['children'] = array_values($stripHidden($item['children']));
                }
                $out[] = $item;
            }
            return $out;
        };
        $items = array_values($stripHidden($items));

        if ($isSuperAdmin) {
            return is_array($items) ? $items : [];
        }

        $navigationItems = [];

        if (!$isSuperAdmin && (!$this->auth || !$this->auth->isLoggedIn())) {
            return [];
        }

        // Check if user has an active subscription (for both BM and TRIAL)
        $hasActiveSubscription = false;
        if ($isBusinessManager || $isTrial) {
            try {
                $customerId = $this->auth ? $this->auth->getCurrentCustomerId() : null;
                if ($customerId) {
                    $subscriptionService = \App\Core\DependencyFactory::getSubscriptionService();
                    $bmSubscription = $subscriptionService->getCustomerSubscription($customerId);
                    if ($bmSubscription && !empty($bmSubscription['status']) && strtoupper($bmSubscription['status']) === 'ACTIVE') {
                        $hasActiveSubscription = true;
                    }
                }
            } catch (\Exception $e) {
                // Graceful degradation
            }
        }
        $businessManagerHasSubscription = $hasActiveSubscription;

        // TRIAL: limited feature set - only core features to evaluate the product
        $allowedMenuIdsForTrial = [
            'DASHBOARD',
            'MENU',
            'CATEGORIES',
            'TABLES',
            'ORDERS',
            'PROFILE',
            'ACCOUNT',
            'COMPANY',
            'PACKAGES',
            'SUBSCRIPTIONS',
            'BILLING',
        ];

        // BUSINESS_MANAGER: features based on purchased package
        $allowedMenuIdsForBusinessManager = $hasActiveSubscription
            ? [
                'DASHBOARD',
                'PROFILE',
                'ACCOUNT',
                'COMPANY',
                'PACKAGES',
                'SUBSCRIPTIONS',
                'PAYMENT_METHODS',
                'CARDS',
                'BILLING',
            ]
            : [
                'DASHBOARD',
                'PACKAGES',
                'SUBSCRIPTIONS',
            ];

        // Nav items that are NEVER shown to business managers (super-admin only)
        // Genişletildi: BLOG_MANAGEMENT, LEGAL_PAGES, FEATURES, TRIAL_SETTINGS,
        // TRIAL_USERS, BLOG, SORO_BLOG eklendi.
        $blockedForBusinessManager = [
            'ROLES_PERMISSIONS',   // Super-admin only
            'SYSTEM_SETTINGS',     // Super-admin only
            'ALL_BUSINESSES',      // Super-admin only
            'BUSINESS_OWNERS',     // Super-admin only
            'CONTACT_FORMS',       // Super-admin only
            'BANK_TRANSFERS',      // Super-admin only
            'BANK_ACCOUNTS',       // Super-admin only
            'SAAS_MANAGEMENT',     // Super-admin only
            'SUPER_ADMIN_DASHBOARD', // Super-admin only
            'SYSTEM_LOGS',         // Super-admin only
            'ERROR_LOGS',          // Super-admin only
            'BLOG_MANAGEMENT',     // Super-admin only
            'LEGAL_PAGES',         // Super-admin only
            'FEATURES',            // Super-admin only
            'TRIAL_SETTINGS',      // Super-admin only
            'TRIAL_USERS',         // Super-admin only
            'BLOG',                // Super-admin only
            'SORO_BLOG',           // Super-admin only
        ];

        foreach ($items as $item) {
            try {
                $itemId = $item['id'] ?? 'unknown';
                $itemRoles = $item['roles'] ?? [];
                $children = $item['children'] ?? [];

                // Filter children recursively before processing parent
                if (!empty($children) && is_array($children)) {
                    $filteredChildren = [];
                    foreach ($children as $child) {
                        $childId = $child['id'] ?? 'unknown';
                        $childRoles = $child['roles'] ?? [];
                        
                        // Manager role bypass: Manager has access to all navigation items
                        if ($isManager) {
                            $filteredChildren[] = $child;
                            continue;
                        }
                        
                        // TRIAL: limited fixed set of nav items
                        if ($isTrial) {
                            if (in_array($childId, $blockedForBusinessManager, true)) {
                                continue;
                            }
                            if (in_array($childId, $allowedMenuIdsForTrial, true)) {
                                $filteredChildren[] = $child;
                            }
                            continue;
                        }
                        
                        // BUSINESS_MANAGER: features based on package permissions
                        if ($isBusinessManager) {
                            if (in_array($childId, $blockedForBusinessManager, true)) {
                                continue;
                            }
                            if (in_array($childId, $allowedMenuIdsForBusinessManager, true)) {
                                $filteredChildren[] = $child;
                            } elseif (isset($child['permission']) && !empty($child['permission'])) {
                                try {
                                    if ($this->auth && $this->auth->hasPermission($child['permission'])) {
                                        $filteredChildren[] = $child;
                                    }
                                } catch (\Exception $e) { /* deny on error */ }
                            } else {
                                if ($businessManagerHasSubscription) {
                                    $filteredChildren[] = $child;
                                }
                            }
                            continue;
                        }
                        
                        // Check role match for child
                        $hasRole = $this->checkRoleMatch($childRoles, $currentRoleId, $currentRoleCode, $normalizedRoleCode);
                        
                        // Check permission for child
                        $hasPermission = false;
                        if (isset($child['permission']) && !empty($child['permission'])) {
                            try {
                                $hasPermission = $this->auth->hasPermission($child['permission']);
                            } catch (\Exception $e) {
                                $hasPermission = false;
                            }
                        }
                        
                        // Show child if: (has role AND (no permission required OR has permission)) OR (has permission even without role match)
                        if ($hasRole) {
                            if (isset($child['permission']) && !empty($child['permission']) && !$hasPermission) {
                                continue;
                            }
                            $filteredChildren[] = $child;
                        } elseif ($hasPermission) {
                            $filteredChildren[] = $child;
                        }
                    }
                    $item['children'] = $filteredChildren;
                }

                // Manager role bypass: Manager has access to all navigation items
                if ($isManager) {
                    $navigationItems[] = $item;
                    continue;
                }

                // TRIAL: limited fixed set + show parent containers with visible children
                if ($isTrial) {
                    if (in_array($itemId, $blockedForBusinessManager, true)) {
                        continue;
                    }

                    $shouldShow = false;
                    if (in_array($itemId, $allowedMenuIdsForTrial, true)) {
                        $shouldShow = true;
                    } else {
                        $isParentContainer = in_array($itemId, ['SCREENS', 'OPERATIONS', 'FINANCE', 'FINANCE_MAIN', 'ANALYTICS', 'HR', 'SETTINGS', 'SAAS_MANAGEMENT'], true);
                        if ($isParentContainer) {
                            $shouldShow = !empty($item['children']);
                        }
                    }

                    if ($shouldShow) {
                        $navigationItems[] = $item;
                    }
                    continue;
                }

                // BUSINESS_MANAGER: access gated by active subscription + package permissions
                if ($isBusinessManager) {
                    if (in_array($itemId, $blockedForBusinessManager, true)) {
                        continue;
                    }

                    $shouldShow = false;

                    if (in_array($itemId, $allowedMenuIdsForBusinessManager, true)) {
                        $shouldShow = true;
                    } else {
                        $isParentContainer = in_array($itemId, ['SCREENS', 'OPERATIONS', 'FINANCE', 'FINANCE_MAIN', 'ANALYTICS', 'HR', 'SETTINGS', 'SAAS_MANAGEMENT'], true);
                        if ($isParentContainer) {
                            $shouldShow = !empty($item['children']);
                        } elseif (isset($item['permission']) && !empty($item['permission'])) {
                            try {
                                $shouldShow = $this->auth && $this->auth->hasPermission($item['permission']);
                            } catch (\Exception $e) { /* deny on error */ }
                        } else {
                            $shouldShow = $businessManagerHasSubscription;
                        }
                    }

                    if ($shouldShow) {
                        $navigationItems[] = $item;
                    }
                    continue;

                }

                // Skip if no roles defined (public item) - but still check permission
                if (empty($itemRoles)) {
                    if (isset($item['permission']) && !empty($item['permission'])) {
                        if (!$this->auth->hasPermission($item['permission'])) {
                            continue;
                        }
                    }
                    $navigationItems[] = $item;
                    continue;
                }

                // Check role match
                $hasRole = $this->checkRoleMatch($itemRoles, $currentRoleId, $currentRoleCode, $normalizedRoleCode);

                // Check permission
                $hasPermission = false;
                if (isset($item['permission']) && !empty($item['permission'])) {
                    try {
                        $hasPermission = $this->auth->hasPermission($item['permission']);
                    } catch (\Exception $e) {
                        $hasPermission = false;
                    }
                }

                // Show item if: (has role AND (no permission required OR has permission)) OR (has permission even without role match)
                if ($hasRole) {
                    if (isset($item['permission']) && !empty($item['permission']) && !$hasPermission) {
                        continue;
                    }
                    $navigationItems[] = $item;
                } elseif ($hasPermission) {
                    // Permission-based access: if user has permission, show item even without role match
                    $navigationItems[] = $item;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        // Ensure return value is always an array
        return is_array($navigationItems) ? $navigationItems : [];
    }

    /**
     * Check if user role matches any of the item roles
     *
     * @param array $itemRoles Item roles
     * @param string|null $currentRoleId Current role ID
     * @param string|null $currentRoleCode Current role code
     * @param string|null $normalizedRoleCode Normalized role code
     * @return bool
     */
    private function checkRoleMatch(array $itemRoles, ?string $currentRoleId, ?string $currentRoleCode, ?string $normalizedRoleCode): bool {
        if (empty($itemRoles)) {
            return false;
        }

        foreach ($itemRoles as $itemRole) {
            // Normalize item role
            $normalizedItemRole = strtoupper(str_replace('ROLE_', '', trim($itemRole)));
            $itemRoleWithPrefix = 'ROLE_' . $normalizedItemRole;
            $itemRoleUpper = strtoupper(trim($itemRole));

            // Handle role_id (numeric or ROLE_* format)
            if ($currentRoleId) {
                $currentRoleIdUpper = strtoupper(trim($currentRoleId));
                // Direct match
                if ($currentRoleIdUpper === $itemRoleUpper) {
                    return true;
                }
                // Match normalized (without ROLE_ prefix)
                $currentRoleIdNormalized = str_replace('ROLE_', '', $currentRoleIdUpper);
                if ($currentRoleIdNormalized === $normalizedItemRole) {
                    return true;
                }
                // Numeric comparison if both are numeric
                if (is_numeric($itemRole) && is_numeric($currentRoleId)) {
                    if ((int)$itemRole === (int)$currentRoleId) {
                        return true;
                    }
                }
            }

            // Handle role_code (string)
            if ($normalizedRoleCode) {
                // Check exact match (both normalized, without ROLE_ prefix)
                if ($normalizedRoleCode === $normalizedItemRole) {
                    return true;
                }
                // Check with ROLE_ prefix
                if ($currentRoleCode) {
                    $currentRoleCodeUpper = strtoupper(trim($currentRoleCode));
                    if ($currentRoleCodeUpper === $itemRoleWithPrefix) {
                        return true;
                    }
                    // Check if current role matches item role (both normalized, without ROLE_ prefix)
                    $currentRoleCodeNormalized = str_replace('ROLE_', '', $currentRoleCodeUpper);
                    if ($currentRoleCodeNormalized === $normalizedItemRole) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Process and sort navigation items
     *
     * @param array $items Navigation items
     * @return array Processed navigation items
     */
    private function processNavigationItems(array $items): array {
        // Ensure $items is always an array
        if (!is_array($items)) {
            return [];
        }

        // Sort by display_order
        usort($items, function($a, $b) {
            $orderA = isset($a['display_order']) ? (int)$a['display_order'] : 999;
            $orderB = isset($b['display_order']) ? (int)$b['display_order'] : 999;
            return $orderA <=> $orderB;
        });

        // Determine URL prefix based on user role
        $isSuperAdmin = false;
        if ($this->auth && $this->auth->isLoggedIn()) {
            try {
                $isSuperAdmin = $this->auth->isSuperAdmin();
            } catch (\Exception $e) {
                $isSuperAdminSession = \App\Core\SessionManager::get('is_super_admin') === true;
                $sessionRole = \App\Core\SessionManager::get('role');
                $isSuperAdmin = ($isSuperAdminSession || $sessionRole === 'SUPER_ADMIN' || $sessionRole === 'ROLE_SUPER_ADMIN');
            }
        }
        $urlPrefix = $isSuperAdmin ? '/qodmin' : '/business';

        // Process parent-child relationships and update URLs
        $processedItems = [];
        $processedIds = [];

        foreach ($items as $item) {
            $itemId = $item['id'] ?? '';
            $parentId = $item['parent_id'] ?? null;

            // Update URL prefix if needed (only for admin/business/qodmin routes)
            if (isset($item['url']) && is_string($item['url'])) {
                // Replace /admin/, /qodmin/, or /business/ with appropriate prefix
                if (strpos($item['url'], '/admin/') === 0) {
                    $path = substr($item['url'], 7); // Remove '/admin/'
                    $item['url'] = $urlPrefix . '/' . $path;
                } elseif (strpos($item['url'], '/qodmin/') === 0) {
                    // Replace /qodmin/ with appropriate prefix based on role
                    $path = substr($item['url'], 8); // Remove '/qodmin/'
                    $item['url'] = $urlPrefix . '/' . $path;
                } elseif (strpos($item['url'], '/business/') === 0) {
                    $path = substr($item['url'], 10); // Remove '/business/'
                    $item['url'] = $urlPrefix . '/' . $path;
                }
            }

            // Update children URLs if exists
            if (isset($item['children']) && is_array($item['children'])) {
                foreach ($item['children'] as &$child) {
                    if (isset($child['url']) && is_string($child['url'])) {
                        if (strpos($child['url'], '/admin/') === 0) {
                            $path = substr($child['url'], 7);
                            $child['url'] = $urlPrefix . '/' . $path;
                        } elseif (strpos($child['url'], '/qodmin/') === 0) {
                            // Replace /qodmin/ with appropriate prefix based on role
                            $path = substr($child['url'], 8); // Remove '/qodmin/'
                            $child['url'] = $urlPrefix . '/' . $path;
                        } elseif (strpos($child['url'], '/business/') === 0) {
                            $path = substr($child['url'], 10);
                            $child['url'] = $urlPrefix . '/' . $path;
                        }
                    }
                }
                unset($child); // Break reference
            }

            // Skip if already processed as child
            if (in_array($itemId, $processedIds)) {
                continue;
            }

            // If item has parent, add to parent's children
            if ($parentId) {
                foreach ($processedItems as &$parentItem) {
                    if (($parentItem['id'] ?? '') === $parentId) {
                        if (!isset($parentItem['children'])) {
                            $parentItem['children'] = [];
                        }
                        $parentItem['children'][] = $item;
                        $processedIds[] = $itemId;
                        continue 2;
                    }
                }
            }

            // Add as top-level item
            $processedItems[] = $item;
            $processedIds[] = $itemId;
        }

        // Ensure return value is always an array
        return is_array($processedItems) ? $processedItems : [];
    }

    /**
     * Check if current user is Super Admin
     *
     * @return bool
     */
    private function isSuperAdminCheck(): bool {
        // First check session flag (most reliable)
        $isSuperAdminSession = \App\Core\SessionManager::get('is_super_admin') === true;
        if ($isSuperAdminSession) {
            return true;
        }

        // Then check role code
        if (isset($_SESSION['role'])) {
            $roleCode = strtoupper(str_replace('ROLE_', '', trim($_SESSION['role'])));
            if ($roleCode === 'SUPER_ADMIN' || $roleCode === 'QODMIN') {
                return true;
            }
        }

        // Also check role_id
        if (isset($_SESSION['role_id'])) {
            $roleId = strtoupper(str_replace('ROLE_', '', trim($_SESSION['role_id'])));
            if ($roleId === 'SUPER_ADMIN' || $roleId === 'QODMIN') {
                return true;
            }
        }

        // Check Authorization method
        if ($this->auth && $this->auth->isLoggedIn()) {
            try {
                return $this->auth->isSuperAdmin();
            } catch (\Exception $e) {
                return false;
            }
        }

        return false;
    }

    /**
     * Get cache key based on user role and permissions
     *
     * @return string Cache key
     */
    private function getCacheKey(): string {
        $roleId = $_SESSION['role_id'] ?? 'no_role';
        $roleCode = $_SESSION['role'] ?? 'no_role';
        $userId = $_SESSION['user_id'] ?? 'no_user';

        return 'navigation_' . md5($roleId . '_' . $roleCode . '_' . $userId);
    }

    /**
     * Clear navigation cache
     * Call this when navigation items are updated
     */
    public function clearCache(): void {
        $this->cache = [];
    }

    /**
     * Get navigation items for specific role (for testing/admin purposes)
     *
     * @param string|null $roleId Role ID
     * @param string|null $roleCode Role code
     * @return array Navigation items
     */
    public function getNavigationItemsForRole(?string $roleId = null, ?string $roleCode = null): array {
        // Temporarily set session values
        $originalRoleId = $_SESSION['role_id'] ?? null;
        $originalRoleCode = $_SESSION['role'] ?? null;

        if ($roleId) {
            $_SESSION['role_id'] = $roleId;
        }
        if ($roleCode) {
            $_SESSION['role'] = $roleCode;
        }

        try {
            $items = $this->getNavigationItems(false); // Don't use cache
        } finally {
            // Restore original values
            if ($originalRoleId !== null) {
                $_SESSION['role_id'] = $originalRoleId;
            } else {
                unset($_SESSION['role_id']);
            }
            if ($originalRoleCode !== null) {
                $_SESSION['role'] = $originalRoleCode;
            } else {
                unset($_SESSION['role']);
            }
        }

        return $items;
    }
}
