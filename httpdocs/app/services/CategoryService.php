<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\CategoryRepository;

/**
 * Category Service - MVC, OOP, Centralized, Dynamic Multi-Language Support
 * Handles all category operations with integrated translation support
 */
class CategoryService extends BaseService {
    private $translationService;
    
    public function __construct(CategoryRepository $categoryRepository) {
        parent::__construct($categoryRepository);
        $this->translationService = \App\Core\DependencyFactory::getTranslationService();
    }
    
    /**
     * Get all categories ordered by display_order (with cache and translation)
     * @param string|null $lang Language code (default: current language)
     * @param bool $includeChildren Whether to include child categories in a hierarchical structure
     * @return array Categories with translations
     */
    public function getAllCategories(?string $lang = null, bool $includeChildren = false): array {
        if ($lang === null) {
            $lang = $this->translationService->getCurrentLanguage();
        }
        
        // CRITICAL: Get tenant ID for cache key to prevent cross-tenant cache pollution
        $tenantId = \App\Core\TenantResolver::resolve();
        if (!$tenantId && class_exists('\\App\\Core\\TenantContext')) {
            try {
                $tenantId = \App\Core\TenantContext::getId();
            } catch (\Exception $e) {
                // TenantContext not set, use 'all' as fallback
                $tenantId = 'all';
            }
        }
        if (!$tenantId) {
            $tenantId = 'all';
        }
        
        // DEBUG: Log tenant context
        if (class_exists('\App\Core\Logger')) {
            \App\Core\Logger::debug('CategoryService::getAllCategories - Called', [
                'tenant_id' => $tenantId,
                'language' => $lang,
                'include_children' => $includeChildren
            ]);
        }
        
        $cache = \App\Core\DependencyFactory::getCacheService();
        $cacheKey = 'menu:categories:' . $tenantId . ':' . $lang . ($includeChildren ? ':tree' : '');
        
        return $cache->remember($cacheKey, function() use ($lang, $includeChildren, $tenantId) {
            $categories = $this->repository->getAllOrdered();
            
            // DEBUG: Log result count
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::debug('CategoryService::getAllCategories - Fetched from DB', [
                    'tenant_id' => $tenantId,
                    'count' => count($categories),
                    'language' => $lang
                ]);
            }
            $categories = $this->translateCategories($categories, $lang);
            
            if ($includeChildren) {
                return $this->buildCategoryTree($categories);
            }
            
            return $categories;
        }, 300); // Cache for 5 minutes (optimized for fresh data)
    }
    
    /**
     * Get all categories for a specific business (for SUPER_ADMIN)
     * @param string $businessId Business ID
     * @param string|null $lang Language code (default: current language)
     * @param bool $includeChildren Whether to include child categories in a hierarchical structure
     * @return array Categories with translations for the specific business
     */
    public function getCategoriesByBusiness(string $businessId, ?string $lang = null, bool $includeChildren = false): array {
        if ($lang === null) {
            $lang = $this->translationService->getCurrentLanguage();
        }

        $cache = \App\Core\DependencyFactory::getCacheService();
        $cacheKey = 'menu:categories:business:' . $businessId . ':' . $lang . ($includeChildren ? ':tree' : '');

        return $cache->remember($cacheKey, function() use ($businessId, $lang, $includeChildren) {
            $categories = $this->repository->getByTenantId($businessId);
            $categories = $this->translateCategories($categories, $lang);

            if ($includeChildren) {
                return $this->buildCategoryTree($categories);
            }

            return $categories;
        }, 300); // Cache for 5 minutes (optimized for fresh data)
    }

    /**
     * Build category tree structure (parent-child hierarchy)
     * @param array $categories Flat categories array
     * @return array Categories organized in tree structure
     */
    private function buildCategoryTree(array $categories): array {
        $tree = [];
        $indexed = [];
        
        // First pass: index all categories
        foreach ($categories as $category) {
            $category['children'] = [];
            $indexed[$category['category_id']] = $category;
        }
        
        // Second pass: build tree
        foreach ($indexed as $category) {
            if (empty($category['parent_id'])) {
                // Top-level category
                $tree[] = $category;
            } else {
                // Child category
                if (isset($indexed[$category['parent_id']])) {
                    $indexed[$category['parent_id']]['children'][] = $category;
                } else {
                    // Parent not found, treat as top-level
                    $tree[] = $category;
                }
            }
        }
        
        return $tree;
    }
    
    /**
     * Translate category names and descriptions
     * @param array $categories Categories array
     * @param string $lang Language code
     * @return array Categories with translated names
     */
    private function translateCategories(array $categories, string $lang): array {
        if ($lang === 'tr') {
            // Turkish is default, no translation needed
            return $categories;
        }
        
        foreach ($categories as &$category) {
            $categoryId = $category['category_id'] ?? '';
            
            // Try to get translation from category_translations table
            $translation = $this->repository->getTranslation($categoryId, $lang);
            
            if ($translation) {
                if (!empty($translation['name'])) {
                    $category['name'] = $translation['name'];
                }
                if (!empty($translation['description'])) {
                    $category['description'] = $translation['description'];
                }
            }
        }
        
        return $categories;
    }
    
    /**
     * Get categories with item count (with cache)
     * @return array Categories with item counts
     */
    public function getCategoriesWithItemCount(): array {
        // CRITICAL: Get tenant ID for cache key to prevent cross-tenant cache pollution
        $tenantId = \App\Core\TenantResolver::resolve();
        if (!$tenantId && class_exists('\\App\\Core\\TenantContext')) {
            try {
                $tenantId = \App\Core\TenantContext::getId();
            } catch (\Exception $e) {
                $tenantId = 'all';
            }
        }
        if (!$tenantId) {
            $tenantId = 'all';
        }
        
        $cache = \App\Core\DependencyFactory::getCacheService();
        return $cache->remember('menu:categories:with_count:' . $tenantId, function() {
            return $this->repository->getWithItemCount();
        }, 300); // Cache for 5 minutes (optimized for fresh data)
    }
    
    /**
     * Get category by ID
     * @param string $categoryId Category ID
     * @return array|null Category data or null
     */
    public function getCategoryById(string $categoryId): ?array {
        return $this->repository->findById($categoryId);
    }

    /**
     * Batch load categories by IDs to avoid N+1 queries.
     * Returns a map of category_id => category_data.
     *
     * @param string[] $categoryIds
     * @return array<string, array>
     */
    public function getCategoriesByIds(array $categoryIds): array {
        $categoryIds = array_values(array_unique(array_filter($categoryIds, fn($id) => is_string($id) && $id !== '')));
        if (empty($categoryIds)) {
            return [];
        }

        $items = $this->repository->findByIds($categoryIds);
        $byId = [];
        foreach ($items as $item) {
            $byId[$item['category_id']] = $item;
        }
        return $byId;
    }
    
    /**
     * Get the deepest (leaf) category in the hierarchy for a given category ID
     * If the category has children, recursively finds the deepest child
     * @param string $categoryId Category ID to start from
     * @return array|null The deepest category in the hierarchy
     */
    public function getDeepestCategory(string $categoryId): ?array {
        $category = $this->repository->findById($categoryId);
        if (!$category) {
            return null;
        }
        
        // Get all categories to build hierarchy
        $allCategories = $this->repository->getAllOrdered();
        
        // Find all children of this category
        $children = array_filter($allCategories, function($cat) use ($categoryId) {
            return ($cat['parent_id'] ?? null) === $categoryId;
        });
        
        // If no children, this is the deepest category
        if (empty($children)) {
            return $category;
        }
        
        // Recursively find the deepest child
        // For now, return the first child (can be enhanced to find deepest path)
        // In practice, we'll check the direct category and its immediate children
        $deepest = $category;
        foreach ($children as $child) {
            $childDeepest = $this->getDeepestCategory($child['category_id']);
            if ($childDeepest) {
                $deepest = $childDeepest;
            }
        }
        
        return $deepest;
    }
    
    /**
     * Create a new category with translation support
     * @param array $categoryData Category data (can include name_en, description_en for translations)
     * @return bool|string Category ID on success, false on failure
     */
    public function createCategory(array $categoryData) {
        // Validate required name field
        if (empty($categoryData['name']) || !is_string($categoryData['name'])) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('CategoryService::createCategory - Invalid category name', [
                    'category_data' => $categoryData
                ]);
            }
            return false;
        }
        
        // Store original name before any modifications
        $originalName = trim($categoryData['name']);
        
        // Generate readable slug-based category_id from name if not provided
        if (empty($categoryData['category_id'])) {
            $slug = $this->generateCategorySlug($originalName);
            $categoryData['category_id'] = $slug;
            
            // If slug already exists, append a number
            $counter = 1;
            while ($this->repository->getById($categoryData['category_id'])) {
                $categoryData['category_id'] = $slug . '-' . $counter;
                $counter++;
            }
        }
        
        // CRITICAL: Ensure name is the actual category name, not another category_id
        $categoryData['name'] = $originalName;
        
        // Log category creation for debugging
        if (class_exists('\App\Core\Logger')) {
            \App\Core\Logger::info('CategoryService::createCategory - Creating category', [
                'name' => $categoryData['name'],
                'category_id' => $categoryData['category_id'],
                'parent_id' => $categoryData['parent_id'] ?? null
            ]);
        }
        
        // Check if category with same name already exists (case-insensitive)
        $existingCategory = $this->repository->findByName($categoryData['name']);
        if ($existingCategory) {
            return false; // Category with this name already exists
        }
        
        // Extract translation data before saving category
        $nameEn = $categoryData['name_en'] ?? '';
        $descriptionEn = $categoryData['description_en'] ?? '';
        unset($categoryData['name_en'], $categoryData['description_en']); // Remove from main data
        
        // CRITICAL: Ensure tenant_id is set (required for categories table)
        if (empty($categoryData['tenant_id'])) {
            $tenantId = \App\Core\TenantResolver::resolve();
            if (!$tenantId && class_exists('\\App\\Core\\TenantContext')) {
                try {
                    $tenantId = \App\Core\TenantContext::getId();
                } catch (\Exception $e) {
                    // TenantContext not set
                }
            }
            
            if ($tenantId) {
                $categoryData['tenant_id'] = $tenantId;
                
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::info('CategoryService::createCategory - Added tenant_id', [
                        'tenant_id' => $tenantId,
                        'category_name' => $categoryData['name']
                    ]);
                }
            } else {
                // No tenant_id available - log error
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('CategoryService::createCategory - No tenant_id available', [
                        'category_name' => $categoryData['name'],
                        'category_data' => $categoryData
                    ]);
                }
                return false; // Cannot create category without tenant_id
            }
        }
        
        $result = $this->repository->create($categoryData);
        
        if ($result) {
            $categoryId = $categoryData['category_id'];
            
            // Always save English translation (fallback to Turkish name if English not provided)
            $this->repository->saveTranslation($categoryId, 'en', [
                'name' => $nameEn ?: $categoryData['name'], // Fallback to Turkish name if English not provided
                'description' => $descriptionEn ?: ($categoryData['description'] ?? '')
            ]);
            
            // CRITICAL: Clear ALL category cache (comprehensive invalidation)
            try {
                $this->clearAllCategoryCache();
            } catch (\Exception $e) {
                // Cache error is not critical, log and continue
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning('CategoryService::createCategory - Cache invalidation failed', [
                        'error' => $e->getMessage(),
                        'category_id' => $categoryId
                    ]);
                }
            }
            
            return $categoryId;
        }
        
        return false;
    }
    
    /**
     * Update category with translation support
     * @param string $categoryId Category ID
     * @param array $categoryData Category data to update (can include name_en, description_en for translations)
     * @return bool Success
     */
    public function updateCategory(string $categoryId, array $categoryData): bool {
        // Extract translation data before updating category
        $nameEn = $categoryData['name_en'] ?? null;
        $descriptionEn = $categoryData['description_en'] ?? null;
        unset($categoryData['name_en'], $categoryData['description_en']); // Remove from main data
        
        $result = $this->repository->update($categoryId, $categoryData);
        
        if ($result) {
            // Save English translation if provided
            if ($nameEn !== null || $descriptionEn !== null) {
                // Get current category data to use as fallback
                $currentCategory = $this->repository->findById($categoryId);
                $this->repository->saveTranslation($categoryId, 'en', [
                    'name' => $nameEn !== null ? $nameEn : ($currentCategory['name'] ?? ''),
                    'description' => $descriptionEn !== null ? $descriptionEn : ($currentCategory['description'] ?? '')
                ]);
            }
            
            // CRITICAL: Clear ALL category cache (comprehensive invalidation)
            try {
                $this->clearAllCategoryCache();
            } catch (\Exception $e) {
                // Cache error is not critical, log and continue
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning('CategoryService::updateCategory - Cache invalidation failed', [
                        'error' => $e->getMessage(),
                        'category_id' => $categoryId
                    ]);
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Delete category
     * @param string $categoryId Category ID
     * @return bool Success
     */
    public function deleteCategory(string $categoryId): bool {
        $result = $this->repository->delete($categoryId);
        
        if ($result) {
            // CRITICAL: Clear ALL category cache when deleting
            $this->clearAllCategoryCache();
        }
        
        return $result;
    }
    
    /**
     * Clear all category-related cache
     * This ensures no stale data remains after create/update/delete operations
     * Made public so it can be called from controllers to force cache refresh
     */
    public function clearAllCategoryCache(): void {
        $cache = \App\Core\DependencyFactory::getCacheService();
        
        // Get tenant ID
        $tenantId = \App\Core\TenantResolver::resolve();
        if (!$tenantId && class_exists('\\App\\Core\\TenantContext')) {
            try {
                $tenantId = \App\Core\TenantContext::getId();
            } catch (\Exception $e) {
                $tenantId = null;
            }
        }
        
        // CRITICAL: Use pattern-based deletion for comprehensive cache clearing
        // This ensures ALL category and menu-related cache is cleared
        try {
            // Method 1: Try deleteByPattern if available (Redis/FileCache)
            if (method_exists($cache, 'deleteByPattern')) {
                $cache->deleteByPattern('menu:*');
                $cache->deleteByPattern('menu_*');
            } else {
                // Method 2: Fallback - try invalidate
                $cache->invalidate('menu:*');
                $cache->invalidate('menu_*');
            }
        } catch (\Exception $e) {
            // If pattern-based deletion fails, fall back to explicit key deletion
            if ($tenantId) {
                $cacheKeys = [
                    'menu:categories:' . $tenantId . ':tr',
                    'menu:categories:' . $tenantId . ':en',
                    'menu:categories:' . $tenantId . ':tr:tree',
                    'menu:categories:' . $tenantId . ':en:tree',
                    'menu:categories:with_count:' . $tenantId,
                    'menu:categories:with_products:' . $tenantId . ':tr',
                    'menu:categories:with_products:' . $tenantId . ':en',
                    'menu:categories:business:' . $tenantId . ':tr',
                    'menu:categories:business:' . $tenantId . ':en',
                    'menu:categories:business:' . $tenantId . ':tr:tree',
                    'menu:categories:business:' . $tenantId . ':en:tree',
                ];
                
                foreach ($cacheKeys as $key) {
                    $cache->delete($key);
                }
            }
        }
        
        // Log cache clear for debugging
        if (class_exists('\App\Core\Logger')) {
            \App\Core\Logger::debug('CategoryService - All category cache cleared', [
                'tenant_id' => $tenantId
            ]);
        }
    }
    
    /**
     * Get categories with kitchen requirement
     * @return array Categories that require kitchen
     */
    public function getCategoriesWithKitchenRequirement(): array {
        return $this->repository->getWithKitchenRequirement();
    }
    
    /**
     * Get categories that have products (only categories with available menu items)
     * Handles parent-child hierarchy: shows parent if it or any child has products
     * @param string|null $lang Language code (default: current language)
     * @return array Categories with products, organized hierarchically
     */
    public function getCategoriesWithProducts(?string $lang = null): array {
        if ($lang === null) {
            $lang = $this->translationService->getCurrentLanguage();
        }
        
        // CRITICAL: Get tenant ID for cache key to prevent cross-tenant cache pollution
        $tenantId = \App\Core\TenantResolver::resolve();
        if (!$tenantId && class_exists('\\App\\Core\\TenantContext')) {
            try {
                $tenantId = \App\Core\TenantContext::getId();
            } catch (\Exception $e) {
                $tenantId = 'all';
            }
        }
        if (!$tenantId) {
            $tenantId = 'all';
        }
        
        $cache = \App\Core\DependencyFactory::getCacheService();
        $cacheKey = 'menu:categories:with_products:' . $tenantId . ':' . $lang;
        
        return $cache->remember($cacheKey, function() use ($lang) {
            // Get all categories
            $allCategories = $this->repository->getAllOrdered();
            $allCategories = $this->translateCategories($allCategories, $lang);
            
            // Get all available menu items with translations and group by category
            $menuItemService = \App\Core\DependencyFactory::getMenuItemService();
            $menuItems = $menuItemService->getAvailableMenuItems($lang);
            
            // Build map of category_id => has_products
            $categoryHasProducts = [];
            foreach ($menuItems as $item) {
                $catId = $item['category_id'] ?? null;
                if ($catId) {
                    $categoryHasProducts[$catId] = true;
                }
            }
            
            // Build category tree to check parent categories
            $indexed = [];
            foreach ($allCategories as $category) {
                $category['has_products'] = isset($categoryHasProducts[$category['category_id']]);
                $category['children'] = [];
                $indexed[$category['category_id']] = $category;
            }
            
            // Build parent-child relationships and mark parents that have products in children
            foreach ($indexed as $categoryId => $category) {
                if (!empty($category['parent_id']) && isset($indexed[$category['parent_id']])) {
                    $indexed[$category['parent_id']]['children'][] = $category;
                    // If child has products, mark parent as having products
                    if ($category['has_products']) {
                        $indexed[$category['parent_id']]['has_products'] = true;
                    }
                }
            }
            
            // Recursively mark all parents that have children with products
            $this->markParentsWithProducts($indexed);
            
            // Filter: only keep categories that have products (directly or through children)
            $filteredCategories = [];
            foreach ($indexed as $categoryId => $category) {
                if (empty($category['parent_id']) && $category['has_products']) {
                    // Top-level category with products - include it and its children with products
                    $filteredCategories[] = $this->filterCategoryTree($category);
                }
            }
            
            // Sort categories by display_order (with name as fallback)
            usort($filteredCategories, function($a, $b) {
                $orderA = $a['display_order'] ?? 9999;
                $orderB = $b['display_order'] ?? 9999;
                if ($orderA === $orderB) {
                    $nameA = mb_strtolower($a['name'] ?? '', 'UTF-8');
                    $nameB = mb_strtolower($b['name'] ?? '', 'UTF-8');
                    return strcoll($nameA, $nameB);
                }
                return $orderA - $orderB;
            });
            
            // Also sort children within each category by display_order
            foreach ($filteredCategories as &$category) {
                if (!empty($category['children'])) {
                    usort($category['children'], function($a, $b) {
                        $orderA = $a['display_order'] ?? 9999;
                        $orderB = $b['display_order'] ?? 9999;
                        if ($orderA === $orderB) {
                            $nameA = mb_strtolower($a['name'] ?? '', 'UTF-8');
                            $nameB = mb_strtolower($b['name'] ?? '', 'UTF-8');
                            return strcoll($nameA, $nameB);
                        }
                        return $orderA - $orderB;
                    });
                }
            }
            
            return $filteredCategories;
        }, 300); // Cache for 5 minutes (optimized for fresh data)
    }
    
    /**
     * Mark all parent categories that have children with products
     * @param array $indexed Indexed categories array (passed by reference)
     */
    private function markParentsWithProducts(array &$indexed): void {
        $changed = true;
        // Keep iterating until no more changes (to handle deep hierarchies)
        while ($changed) {
            $changed = false;
            foreach ($indexed as $categoryId => $category) {
                if (!empty($category['parent_id']) && isset($indexed[$category['parent_id']])) {
                    if ($category['has_products'] && !$indexed[$category['parent_id']]['has_products']) {
                        $indexed[$category['parent_id']]['has_products'] = true;
                        $changed = true;
                    }
                }
            }
        }
    }
    
    /**
     * Filter category tree to only include categories with products
     * @param array $category Category with children
     * @return array Filtered category
     */
    private function filterCategoryTree(array $category): array {
        $filtered = $category;
        
        // Filter children to only include those with products
        if (!empty($category['children'])) {
            $filtered['children'] = [];
            foreach ($category['children'] as $child) {
                if ($child['has_products']) {
                    $filtered['children'][] = $this->filterCategoryTree($child);
                }
            }
        }
        
        return $filtered;
    }
    
    /**
     * Flatten category tree to flat list for display (parent categories come first, then children)
     * Categories are sorted by display_order
     * @param array $categoryTree Category tree
     * @return array Flat list of categories sorted by display_order
     */
    public function flattenCategoryTree(array $categoryTree): array {
        $flat = [];
        
        // Sort categories by display_order before flattening
        usort($categoryTree, function($a, $b) {
            $orderA = $a['display_order'] ?? 9999;
            $orderB = $b['display_order'] ?? 9999;
            if ($orderA === $orderB) {
                $nameA = mb_strtolower($a['name'] ?? '', 'UTF-8');
                $nameB = mb_strtolower($b['name'] ?? '', 'UTF-8');
                return strcoll($nameA, $nameB);
            }
            return $orderA - $orderB;
        });
        
        foreach ($categoryTree as $category) {
            // Add parent category (without children for flat structure)
            $flatCategory = $category;
            unset($flatCategory['children']);
            $flat[] = $flatCategory;
            
            // Add children recursively
            if (!empty($category['children'])) {
                // Sort children by display_order before processing
                $children = $category['children'];
                usort($children, function($a, $b) {
                    $orderA = $a['display_order'] ?? 9999;
                    $orderB = $b['display_order'] ?? 9999;
                    if ($orderA === $orderB) {
                        $nameA = mb_strtolower($a['name'] ?? '', 'UTF-8');
                        $nameB = mb_strtolower($b['name'] ?? '', 'UTF-8');
                        return strcoll($nameA, $nameB);
                    }
                    return $orderA - $orderB;
                });
                
                foreach ($children as $child) {
                    // Recursively flatten child tree
                    $childFlat = $this->flattenCategoryTree([$child]);
                    $flat = array_merge($flat, $childFlat);
                }
            }
        }
        
        return $flat;
    }
    
    /**
     * Generate a URL-friendly slug from category name
     * @param string $name Category name
     * @return string Slug
     */
    private function generateCategorySlug(string $name): string {
        // Convert to lowercase
        $slug = mb_strtolower($name, 'UTF-8');
        
        // Turkish character replacements
        $turkishChars = ['ı', 'ğ', 'ü', 'ş', 'ö', 'ç', 'İ', 'Ğ', 'Ü', 'Ş', 'Ö', 'Ç'];
        $englishChars = ['i', 'g', 'u', 's', 'o', 'c', 'i', 'g', 'u', 's', 'o', 'c'];
        $slug = str_replace($turkishChars, $englishChars, $slug);
        
        // Remove special characters except spaces and hyphens
        $slug = preg_replace('/[^a-z0-9\s\-]/', '', $slug);
        
        // Replace multiple spaces/hyphens with single hyphen
        $slug = preg_replace('/[\s\-]+/', '-', $slug);
        
        // Trim hyphens from ends
        $slug = trim($slug, '-');
        
        // Limit length to 50 characters
        if (strlen($slug) > 50) {
            $slug = substr($slug, 0, 50);
            $slug = trim($slug, '-');
        }
        
        return $slug ?: 'kategori';
    }
}

