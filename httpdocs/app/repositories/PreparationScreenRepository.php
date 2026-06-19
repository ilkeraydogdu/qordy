<?php
namespace App\Repositories;

use App\Core\BaseRepository;

/**
 * Preparation Screen Repository
 * Handles database operations for preparation screens
 * 
 * @package App\Repositories
 */
class PreparationScreenRepository extends BaseRepository {
    protected $table = 'preparation_screens';
    protected $primaryKey = 'screen_id';

    /**
     * Constructor
     * @param \PDO $database Database connection
     */
    public function __construct($database) {
        parent::__construct($database);
    }

    /**
     * Get all screens ordered by display order
     * @return array Screens
     */
    public function getAllOrdered(): array {
        $sql = "SELECT * FROM {$this->table}";
        $params = [];

        // Add tenant filter
        $tenantFilter = $this->getTenantFilter();
        if (!empty($tenantFilter['where'])) {
            $sql .= " WHERE " . $tenantFilter['where'];
            $params = $tenantFilter['params'];
        }

        $sql .= " ORDER BY display_order ASC, name ASC";
        return $this->fetchAll($sql, $params);
    }

    /**
     * Get active screens
     * @return array Active screens
     */
    public function getActive(): array {
        $sql = "SELECT * FROM {$this->table} WHERE is_active = 1";
        $params = [];

        // Add tenant filter
        $tenantFilter = $this->getTenantFilter();
        if (!empty($tenantFilter['where'])) {
            $sql .= " AND " . $tenantFilter['where'];
            $params = $tenantFilter['params'];
        }

        $sql .= " ORDER BY display_order ASC, name ASC";
        return $this->fetchAll($sql, $params);
    }

    /**
     * Get screen by slug
     * CRITICAL: Applies tenant filter for multi-tenant isolation
     * @param string $slug Screen slug
     * @return array|null Screen data or null
     */
    public function getBySlug(string $slug): ?array {
        // CRITICAL: Add tenant filter for tenant isolation
        // Use getTenantFilter() which supports both business_id and tenant_id columns
        $sql = "SELECT * FROM {$this->table} WHERE slug = :slug";
        $params = ['slug' => $slug];
        
        // Add tenant filter if applicable (skip for excluded tables)
        $tenantFilter = $this->getTenantFilter();
        if (!empty($tenantFilter['where'])) {
            $sql .= " AND " . $tenantFilter['where'];
            $params = array_merge($params, $tenantFilter['params']);
        }
        
        $sql .= " LIMIT 1";
        $result = $this->fetchOne($sql, $params);
        return $result ?: null;
    }

    /**
     * Get screen categories
     * @param string $screenId Screen ID
     * @return array Categories
     */
    public function getScreenCategories(string $screenId): array {
        $sql = "SELECT c.* 
                FROM categories c
                INNER JOIN preparation_screen_categories psc ON c.category_id = psc.category_id
                WHERE psc.screen_id = :screen_id
                ORDER BY c.name ASC";
        return $this->fetchAll($sql, ['screen_id' => $screenId]);
    }

    /**
     * Get screen category IDs
     * @param string $screenId Screen ID
     * @return array Category IDs
     */
    public function getScreenCategoryIds(string $screenId): array {
        $sql = "SELECT category_id FROM preparation_screen_categories WHERE screen_id = :screen_id";
        $results = $this->fetchAll($sql, ['screen_id' => $screenId]);
        return array_column($results, 'category_id');
    }

    /**
     * Assign categories to screen
     * @param string $screenId Screen ID
     * @param array $categoryIds Array of category IDs
     * @return bool Success
     */
    public function assignCategories(string $screenId, array $categoryIds): bool {
        // First, remove existing assignments
        $this->removeCategories($screenId);
        
        if (empty($categoryIds)) {
            return true;
        }
        
        // Insert new assignments
        $sql = "INSERT INTO preparation_screen_categories (screen_id, category_id) VALUES (:screen_id, :category_id)";
        $stmt = $this->db->prepare($sql);
        
        foreach ($categoryIds as $categoryId) {
            $stmt->execute([
                'screen_id' => $screenId,
                'category_id' => $categoryId
            ]);
        }
        
        return true;
    }

    /**
     * Remove all categories from screen
     * @param string $screenId Screen ID
     * @return bool Success
     */
    public function removeCategories(string $screenId): bool {
        $sql = "DELETE FROM preparation_screen_categories WHERE screen_id = :screen_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['screen_id' => $screenId]);
    }

    /**
     * Check if slug exists (excluding current screen)
     * @param string $slug Slug to check
     * @param string|null $excludeScreenId Screen ID to exclude from check
     * @return bool True if slug exists
     */
    public function slugExists(string $slug, ?string $excludeScreenId = null): bool {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE slug = :slug";
        $params = ['slug' => $slug];
        
        if ($excludeScreenId) {
            $sql .= " AND screen_id != :exclude_id";
            $params['exclude_id'] = $excludeScreenId;
        }
        
        $result = $this->fetchOne($sql, $params);
        return ($result['count'] ?? 0) > 0;
    }
}

