<?php
namespace App\Repositories;

use App\Core\BaseRepository;

/**
 * Category Repository
 * Handles database operations for categories
 * 
 * @package App\Repositories
 */
class CategoryRepository extends BaseRepository {
    protected $table = 'categories';
    protected $primaryKey = 'category_id';

    /**
     * Constructor
     * @param \PDO $database Database connection
     */
    public function __construct($database) {
        parent::__construct($database);
        $this->ensureVisualColumns();
    }

    /**
     * Ensure image_url and icon columns exist on categories table.
     */
    private function ensureVisualColumns(): void {
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;

        try {
            if (!$this->hasColumn('image_url')) {
                $this->db->exec("ALTER TABLE {$this->table} ADD COLUMN image_url VARCHAR(500) NULL DEFAULT NULL AFTER description");
            }
            if (!$this->hasColumn('icon')) {
                $this->db->exec("ALTER TABLE {$this->table} ADD COLUMN icon VARCHAR(64) NULL DEFAULT NULL AFTER image_url");
            }
        } catch (\Exception $e) {
            // Silent — migration script can be run manually
        }
    }

    /**
     * Get category by ID
     * @param string $categoryId Category ID
     * @return array|null Category data or null if not found
     */
    public function getById(string $categoryId): ?array {
        return $this->findById($categoryId);
    }

    /**
     * Get all categories ordered by display order
     * @return array Categories
     */
    public function getAllOrdered(): array {
        // Use new tenant filter helper
        $filter = $this->getTenantFilter();
        $params = $filter['params'];
        $whereClause = !empty($filter['where']) ? ' WHERE ' . $filter['where'] : '';
        
        $orderBy = \App\Core\DbSchema::hasColumn($this->table, 'display_order')
            ? 'display_order ASC, name ASC'
            : 'name ASC';
        $sql = "SELECT * FROM {$this->table}{$whereClause} ORDER BY {$orderBy}";
        return $this->fetchAll($sql, $params);
    }
    
    /**
     * Get tenant ID from session or TenantContext
     * @return string|null
     */
    private function getTenantId(): ?string {
        // Try session first
        $tenantId = \App\Core\TenantResolver::resolve();
        
        // If not in session, try TenantContext
        if (!$tenantId && class_exists('\App\Core\TenantContext')) {
            try {
                $tenantId = \App\Core\TenantContext::getId();
            } catch (\Exception $e) {
                // TenantContext not set, continue
            }
        }
        
        return $tenantId;
    }

    /**
     * Get categories with item count
     * @return array Categories with item_count field
     */
    public function getWithItemCount(): array {
        // Use new tenant filter helper for categories
        $categoryFilter = $this->getTenantFilter();
        $params = $categoryFilter['params'];
        $whereConditions = [];
        
        // Add tenant filter for categories
        if (!empty($categoryFilter['where'])) {
            $whereConditions[] = "c." . $categoryFilter['where'];
        }
        
        // Get tenant ID for filtering menu_items
        $tenantId = \App\Core\TenantResolver::resolve();
        if (!$tenantId && class_exists('\\App\\Core\\TenantContext')) {
            $tenantId = \App\Core\TenantContext::getId();
        }
        
        if ($tenantId && \App\Core\DbSchema::hasColumn('menu_items', 'tenant_id')) {
            $whereConditions[] = "(m.menu_item_id IS NULL OR m.tenant_id = :menu_tenant_id)";
            $params['menu_tenant_id'] = $tenantId;
        }

        $whereClause = !empty($whereConditions) ? ' WHERE ' . implode(' AND ', $whereConditions) : '';
        $joinTenantFilter = ($tenantId && isset($params['menu_tenant_id'])) ? " AND m.tenant_id = :menu_tenant_id" : "";
        $orderBy = \App\Core\DbSchema::hasColumn($this->table, 'display_order')
            ? 'c.display_order ASC, c.name ASC'
            : 'c.name ASC';

        $sql = "SELECT c.*, COUNT(CASE WHEN m.menu_item_id IS NOT NULL THEN 1 END) as item_count
                FROM {$this->table} c
                LEFT JOIN menu_items m ON c.category_id = m.category_id{$joinTenantFilter}
                {$whereClause}
                GROUP BY c.category_id
                ORDER BY {$orderBy}";
        return $this->fetchAll($sql, $params);
    }
    
    /**
     * Get categories by tenant_id (business)
     * @param string $tenantId Tenant/Business ID
     * @return array Categories for the specific business
     */
    public function getByTenantId(string $tenantId): array {
        if (!\App\Core\DbSchema::hasColumn($this->table, 'tenant_id')) {
            return $this->getAllOrdered();
        }
        $orderBy = \App\Core\DbSchema::hasColumn($this->table, 'display_order')
            ? 'display_order ASC, name ASC'
            : 'name ASC';
        $sql = "SELECT * FROM {$this->table} WHERE tenant_id = :tenant_id ORDER BY {$orderBy}";
        return $this->fetchAll($sql, ['tenant_id' => $tenantId]);
    }

    /**
     * Find category by name (case-insensitive)
     * @param string $name Category name
     * @return array|null Category or null if not found
     */
    public function findByName(string $name): ?array {
        // CRITICAL: Add tenant isolation to prevent cross-tenant category conflicts
        $sql = "SELECT * FROM {$this->table} WHERE LOWER(name) = LOWER(:name)";
        $params = ['name' => $name];
        
        // Add tenant filter
        $sql = $this->addTenantToWhere($sql, $params);
        $sql .= " LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    /**
     * Get categories that require kitchen preparation
     * @return array Categories requiring kitchen
     */
    public function getWithKitchenRequirement(): array {
        $sql = "SELECT * FROM {$this->table} WHERE requires_kitchen = 1";
        $params = [];
        
        // Add tenant filter
        $sql = $this->addTenantToWhere($sql, $params);
        $sql .= " ORDER BY name ASC";
        
        return $this->fetchAll($sql, $params);
    }
    
    /**
     * Get category translation for a specific language
     * @param string $categoryId Category ID
     * @param string $lang Language code
     * @return array|null Translation data or null
     */
    public function getTranslation(string $categoryId, string $lang): ?array {
        try {
            $sql = "SELECT * FROM category_translations 
                    WHERE category_id = :category_id AND language_code = :lang 
                    LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'category_id' => $categoryId,
                'lang' => $lang
            ]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (\Exception $e) {
            // Table might not exist, return null
            return null;
        }
    }
    
    /**
     * Create or update category translation
     * @param string $categoryId Category ID
     * @param string $lang Language code
     * @param array $translationData Translation data (name, description)
     * @return bool Success
     */
    public function saveTranslation(string $categoryId, string $lang, array $translationData): bool {
        try {
            $existing = $this->getTranslation($categoryId, $lang);
            
            if ($existing) {
                // Update existing translation
                $sql = "UPDATE category_translations 
                        SET name = :name, description = :description, updated_at = NOW()
                        WHERE category_id = :category_id AND language_code = :lang";
                $stmt = $this->db->prepare($sql);
                return $stmt->execute([
                    'name' => $translationData['name'] ?? '',
                    'description' => $translationData['description'] ?? '',
                    'category_id' => $categoryId,
                    'lang' => $lang
                ]);
            } else {
                // Insert new translation
                $sql = "INSERT INTO category_translations 
                        (category_id, language_code, name, description, created_at, updated_at)
                        VALUES (:category_id, :lang, :name, :description, NOW(), NOW())";
                $stmt = $this->db->prepare($sql);
                return $stmt->execute([
                    'category_id' => $categoryId,
                    'lang' => $lang,
                    'name' => $translationData['name'] ?? '',
                    'description' => $translationData['description'] ?? ''
                ]);
            }
        } catch (\Exception $e) {
            // Table might not exist, try to create it
            try {
                $this->createTranslationTable();
                // Retry after creating table
                return $this->saveTranslation($categoryId, $lang, $translationData);
            } catch (\Exception $e2) {
                error_log("Category translation save error: " . $e2->getMessage());
                return false;
            }
        }
    }
    
    /**
     * Delete category and its translations
     * @param string $id Category ID
     * @return bool Success
     */
    public function delete(string $id): bool {
        // Trim and validate ID
        $id = trim($id);
        if (empty($id)) {
            \App\Core\Logger::warning("CategoryRepository::delete - Empty ID provided");
            return false;
        }
        
        try {
            // First, delete translations manually to avoid foreign key issues
            // (in case CASCADE is not set up properly)
            try {
                $deleteTranslationsSql = "DELETE FROM category_translations WHERE category_id = :category_id";
                $translationStmt = $this->db->prepare($deleteTranslationsSql);
                $translationStmt->execute(['category_id' => $id]);
                \App\Core\Logger::debug("CategoryRepository::delete - Translations deleted", [
                    'category_id' => $id,
                    'rows_affected' => $translationStmt->rowCount()
                ]);
            } catch (\Exception $e) {
                // Translation table might not exist or already deleted, continue
                \App\Core\Logger::debug("CategoryRepository::delete - Translation deletion skipped", [
                    'error' => $e->getMessage()
                ]);
            }
            
            // Now delete the category itself
            $paramName = $this->primaryKey;
            $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :{$paramName}";
            
            \App\Core\Logger::debug("CategoryRepository::delete", [
                'table' => $this->table,
                'primary_key' => $this->primaryKey,
                'id' => $id
            ]);
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([$paramName => $id]);
            $rowsAffected = $stmt->rowCount();
            
            \App\Core\Logger::debug("CategoryRepository::delete - Result", [
                'success' => $result,
                'rows_affected' => $rowsAffected
            ]);
            
            if ($result && $rowsAffected > 0) {
                // Invalidate cache
                try {
                    $cache = $this->getCache();
                    $cache->delete($this->getCacheKey("id:{$id}"));
                    $cache->delete($this->getCacheKey('all'));
                    $cache->delete('menu:categories');
                    $cache->delete('menu:categories:tr');
                    $cache->delete('menu:categories:en');
                    $cache->delete('menu:categories:with_count');
                } catch (\Exception $e) {
                    \App\Core\Logger::warning("CategoryRepository::delete - Cache error", ['error' => $e->getMessage()]);
                }
                return true;
            }
            
            // If no rows were affected, check if record exists
            if ($rowsAffected === 0) {
                $existing = $this->findById($id);
                if (!$existing) {
                    \App\Core\Logger::warning("CategoryRepository::delete - Category not found", ['id' => $id]);
                    // Category doesn't exist, consider it already deleted
                    return true;
                } else {
                    \App\Core\Logger::error("CategoryRepository::delete - Delete failed (possible constraint)", [
                        'id' => $id,
                        'category_exists' => true
                    ]);
                }
            }
            
            return false;
        } catch (\PDOException $e) {
            \App\Core\Logger::error("CategoryRepository::delete - PDOException", [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'id' => $id
            ]);
            return false;
        } catch (\Exception $e) {
            \App\Core\Logger::error("CategoryRepository::delete - Exception", [
                'message' => $e->getMessage(),
                'id' => $id
            ]);
            return false;
        }
    }
    
    /**
     * Create category_translations table if it doesn't exist
     * @return void
     */
    private function createTranslationTable(): void {
        $sql = "CREATE TABLE IF NOT EXISTS category_translations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category_id VARCHAR(50) NOT NULL,
            language_code VARCHAR(10) NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_category_lang (category_id, language_code),
            FOREIGN KEY (category_id) REFERENCES categories(category_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->exec($sql);
    }
}

