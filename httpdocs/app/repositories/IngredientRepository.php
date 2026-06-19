<?php
namespace App\Repositories;

use App\Core\BaseRepository;

class IngredientRepository extends BaseRepository {
    protected $table = 'ingredients';
    protected $primaryKey = 'ingredient_id';

    public function __construct($database) {
        parent::__construct($database);
    }

    public function getAll(): array {
        $sql = "SELECT * FROM {$this->table}";
        $params = [];
        
        // Add tenant filter
        $sql = $this->addTenantToWhere($sql, $params);
        $sql .= " ORDER BY name ASC";
        
        return $this->fetchAll($sql, $params);
    }

    /**
     * Fetch tenant-scoped ingredients filtered by item_type (e.g. CLEANING).
     * Defensive against deployments where the column has not yet been added.
     *
     * @param string $itemType
     * @return array
     */
    public function getByItemType(string $itemType): array {
        if (!$this->hasColumn('item_type')) {
            // Column not yet migrated; treat every row as the default type.
            return $itemType === 'INGREDIENT' ? $this->getAll() : [];
        }

        $sql = "SELECT * FROM {$this->table} WHERE item_type = :item_type";
        $params = ['item_type' => $itemType];

        $sql = $this->addTenantToWhere($sql, $params);
        $sql .= " ORDER BY name ASC";

        return $this->fetchAll($sql, $params);
    }

    public function getById(string $ingredientId): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id";
        $params = ['id' => $ingredientId];
        
        // Add tenant filter
        $sql = $this->addTenantToWhere($sql, $params);
        $sql .= " LIMIT 1";
        
        return $this->fetchOne($sql, $params);
    }

    /**
     * Tenant-scoped case-insensitive name lookup. Returns null if no
     * ingredient with that name exists for the current tenant — callers
     * should then create one.
     */
    public function findByName(string $name): ?array {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $sql = "SELECT * FROM {$this->table} WHERE LOWER(name) = LOWER(:n)";
        $params = ['n' => $name];

        $sql = $this->addTenantToWhere($sql, $params);
        $sql .= " LIMIT 1";

        return $this->fetchOne($sql, $params);
    }

    public function updateStock(string $ingredientId, float $amount): bool {
        $sql = "UPDATE {$this->table} SET current_stock = current_stock - :amount WHERE {$this->primaryKey} = :id";
        $params = ['id' => $ingredientId, 'amount' => $amount];
        
        // Add tenant filter for security
        $filter = $this->getTenantFilter();
        if (!empty($filter['where'])) {
            $sql .= " AND " . $filter['where'];
            $params = array_merge($params, $filter['params']);
        }
        
        return $this->execute($sql, $params);
    }

    public function addStock(string $ingredientId, float $amount): bool {
        $sql = "UPDATE {$this->table} SET current_stock = current_stock + :amount WHERE {$this->primaryKey} = :id";
        $params = ['id' => $ingredientId, 'amount' => $amount];
        
        // Add tenant filter for security
        $filter = $this->getTenantFilter();
        if (!empty($filter['where'])) {
            $sql .= " AND " . $filter['where'];
            $params = array_merge($params, $filter['params']);
        }
        
        return $this->execute($sql, $params);
    }

    public function getLowStock(): array {
        $sql = "SELECT * FROM {$this->table} WHERE current_stock <= min_threshold";
        $params = [];
        
        // Add tenant filter
        $sql = $this->addTenantToWhere($sql, $params);
        $sql .= " ORDER BY current_stock ASC";
        
        return $this->fetchAll($sql, $params);
    }

    public function getOutOfStock(): array {
        $sql = "SELECT * FROM {$this->table} WHERE current_stock <= 0";
        $params = [];
        
        // Add tenant filter
        $sql = $this->addTenantToWhere($sql, $params);
        $sql .= " ORDER BY name ASC";
        
        return $this->fetchAll($sql, $params);
    }

    public function getBelowParLevel(): array {
        $sql = "SELECT * FROM {$this->table} WHERE par_level IS NOT NULL AND current_stock < par_level";
        $params = [];
        
        // Add tenant filter
        $sql = $this->addTenantToWhere($sql, $params);
        $sql .= " ORDER BY current_stock ASC";
        
        return $this->fetchAll($sql, $params);
    }

    public function getUsageByDateRange(string $startDate, string $endDate): array {
        // Get tenant filter for ingredients
        $ingredientFilter = $this->getTenantFilter();
        $params = [
            'start_date' => $startDate,
            'end_date' => $endDate
        ];
        $whereConditions = [];
        
        // Filter ingredients by tenant
        if (!empty($ingredientFilter['where'])) {
            $whereConditions[] = "i." . $ingredientFilter['where'];
            $params = array_merge($params, $ingredientFilter['params']);
        }
        
        // Also filter orders by tenant
        $orderFilter = $this->getTenantFilter();
        if (!empty($orderFilter['where'])) {
            $whereConditions[] = "o." . $orderFilter['where'];
            $params = array_merge($params, $orderFilter['params']);
        }
        
        $whereClause = '';
        if (!empty($whereConditions)) {
            $whereClause = " AND " . implode(" AND ", $whereConditions);
        }
        
        $sql = "SELECT 
                    i.name, 
                    i.current_stock, 
                    i.unit, 
                    COALESCE(SUM(mii.amount * oi.quantity), 0) as used_amount
                FROM {$this->table} i
                LEFT JOIN menu_item_ingredients mii ON i.{$this->primaryKey} = mii.ingredient_id
                LEFT JOIN order_items oi ON mii.menu_item_id = oi.menu_item_id
                LEFT JOIN orders o ON oi.order_id = o.order_id
                WHERE o.created_at BETWEEN :start_date AND :end_date{$whereClause}
                GROUP BY i.{$this->primaryKey}
                ORDER BY i.name ASC";
        
        return $this->fetchAll($sql, $params);
    }
}

