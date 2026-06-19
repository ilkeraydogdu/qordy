<?php
namespace App\Models;

require_once __DIR__ . '/../core/Model.php';

class NavigationItem extends \App\Core\Model {
    protected $table = 'navigation_items';
    
    public function getAll() {
        try {
            // Try with display_order first
            $result = $this->query()
                ->where('is_active', 1)
                ->orderBy('display_order')
                ->orderBy('nav_key')
                ->get();
            return is_array($result) ? $result : [];
        } catch (\Exception $e) {
            // Fallback if display_order column doesn't exist
            try {
                $result = $this->query()
                    ->where('is_active', 1)
                    ->orderBy('nav_key')
                    ->get();
                return is_array($result) ? $result : [];
            } catch (\Exception $e2) {
                return [];
            }
        }
    }
    
    /**
     * Get all navigation items including inactive ones (for Super Admin)
     * @return array All navigation items
     */
    public function getAllIncludingInactive() {
        try {
            // Try with display_order first
            $result = $this->query()
                ->orderBy('display_order')
                ->orderBy('nav_key')
                ->get();
            return is_array($result) ? $result : [];
        } catch (\Exception $e) {
            // Fallback if display_order column doesn't exist
            try {
                $result = $this->query()
                    ->orderBy('nav_key')
                    ->get();
                return is_array($result) ? $result : [];
            } catch (\Exception $e2) {
                return [];
            }
        }
    }
    
    public function getById($navId) {
        return $this->query()
            ->where('nav_id', $navId)
            ->first();
    }
    
    public function getByKey($navKey) {
        return $this->query()
            ->where('nav_key', $navKey)
            ->first();
    }
    
    public function getByRole($role) {
        // Use direct SQL to avoid QueryBuilder ambiguity issues
        try {
            $hasDisplayOrder = \App\Core\DbSchema::hasColumn('navigation_items', 'display_order');

            if ($hasDisplayOrder) {
                $sql = "SELECT ni.* FROM navigation_items ni
                        INNER JOIN navigation_roles nr ON ni.nav_id = nr.nav_id
                        WHERE (nr.role = :role OR nr.role_id = :role)
                        AND ni.is_active = 1
                        ORDER BY ni.display_order ASC, ni.nav_key ASC";
            } else {
                $sql = "SELECT ni.* FROM navigation_items ni
                        INNER JOIN navigation_roles nr ON ni.nav_id = nr.nav_id
                        WHERE (nr.role = :role OR nr.role_id = :role)
                        AND ni.is_active = 1
                        ORDER BY ni.nav_key ASC";
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['role' => $role]);
            $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return is_array($result) ? $result : [];
        } catch (\Exception $e) {
            error_log("NavigationItem::getByRole error: " . $e->getMessage());
            // Fallback to simple query
            try {
                $sql = "SELECT ni.* FROM navigation_items ni
                        INNER JOIN navigation_roles nr ON ni.nav_id = nr.nav_id
                        WHERE (nr.role = :role OR nr.role_id = :role)
                        AND ni.is_active = 1
                        ORDER BY ni.nav_key ASC";
                $stmt = $this->db->prepare($sql);
                $stmt->execute(['role' => $role]);
                $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                return is_array($result) ? $result : [];
            } catch (\Exception $e2) {
                error_log("NavigationItem::getByRole fallback error: " . $e2->getMessage());
                return [];
            }
        }
    }
    
    public function getByPermission($permissionKey) {
        try {
            // Try with display_order first
            return $this->query()
                ->where('permission_key', $permissionKey)
                ->where('is_active', 1)
                ->orderBy('display_order')
                ->get();
        } catch (\Exception $e) {
            // Fallback if display_order column doesn't exist
            return $this->query()
                ->where('permission_key', $permissionKey)
                ->where('is_active', 1)
                ->orderBy('nav_key')
                ->get();
        }
    }
    
    public function create($data) {
        if (!isset($data['nav_id'])) {
            $data['nav_id'] = $data['nav_key'];
        }

        // Use direct SQL to avoid QueryBuilder parameter issues
        // Ensure nav_id is included
        if (!isset($data['nav_id'])) {
            $data['nav_id'] = $data['nav_key'];
        }
        
        try {
            $columns = array_keys($data);
            $placeholders = array_map(function($col) { return ':' . $col; }, $columns);
            
            $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $stmt = $this->db->prepare($sql);
            
            foreach ($data as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }
            
            $result = $stmt->execute();
            
            if (!$result) {
                $errorInfo = $stmt->errorInfo();
                $errorMsg = "NavigationItem::create error: " . ($errorInfo[2] ?? 'Unknown error') . " | SQL: " . $sql . " | Data: " . json_encode($data);
                error_log($errorMsg);
                throw new \Exception($errorMsg);
            }
            
            // Get row count to verify insert
            $rowCount = $stmt->rowCount();
            if ($rowCount == 0) {
                $errorInfo = $stmt->errorInfo();
                $errorMsg = "NavigationItem::create: Insert returned 0 affected rows! Error: " . ($errorInfo[2] ?? 'Unknown') . " | SQL: " . $sql . " | Data: " . json_encode($data);
                error_log($errorMsg);
                throw new \Exception($errorMsg);
            }
            
            // Verify the item was actually inserted
            $verification = $this->db->query("SELECT COUNT(*) as cnt FROM {$this->table} WHERE nav_key = " . $this->db->quote($data['nav_key']))->fetch(\PDO::FETCH_ASSOC);
            if (($verification['cnt'] ?? 0) == 0) {
                $errorMsg = "NavigationItem::create: Item '{$data['nav_key']}' was not found in database after insert! Row count: $rowCount | SQL: " . $sql;
                error_log($errorMsg);
                throw new \Exception($errorMsg);
            }
            
            return $result;
        } catch (\PDOException $e) {
            $errorMsg = "NavigationItem::create PDOException: " . $e->getMessage() . " | SQL: " . ($sql ?? 'N/A') . " | Data: " . json_encode($data);
            error_log($errorMsg);
            throw new \Exception($errorMsg, 0, $e);
        } catch (\Exception $e) {
            // Re-throw if it's already our custom exception
            throw $e;
        }
    }
    
    public function updateNav($navId, $data) {
        // Use direct SQL to avoid QueryBuilder parameter issues
        $set = [];
        foreach ($data as $key => $value) {
            $set[] = "{$key} = :{$key}";
        }
        $setClause = implode(', ', $set);
        
        $sql = "UPDATE {$this->table} SET {$setClause} WHERE nav_id = :nav_id";
        $stmt = $this->db->prepare($sql);
        
        foreach ($data as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':nav_id', $navId);
        
        return $stmt->execute();
    }
    
    public function deleteNav($navId) {
        $this->deleteRoleMappings($navId);
        return $this->query()
            ->where('nav_id', $navId)
            ->delete();
    }
    
    public function assignRole($navId, $role) {
        // Normalize role to role_id if needed
        $roleId = $this->normalizeRoleToId($role);
        $roleCode = '';
        
        // Get role_code for backward compatibility
        if (strpos($role, 'ROLE_') === 0) {
            try {
                require_once __DIR__ . '/../core/DependencyFactory.php';
                $roleService = \App\Core\DependencyFactory::getRoleService();
                $roleData = $roleService->getByRoleId($roleId);
                if ($roleData && isset($roleData['role_code'])) {
                    $roleCode = $roleData['role_code'];
                }
            } catch (\Exception $e) {
                // Fall through
            }
        } else {
            $roleCode = $role;
        }
        
        // Check for existing (use role_id)
        $existing = $this->db->prepare("SELECT 1 FROM navigation_roles WHERE nav_id = ? AND role_id = ? LIMIT 1");
        $existing->execute([$navId, $roleId]);
        $result = $existing->fetch(\PDO::FETCH_ASSOC);
        
        if ($result) {
            return true;
        }
        
        // Insert with both role and role_id for backward compatibility
        $stmt = $this->db->prepare("INSERT INTO navigation_roles (nav_id, role, role_id) VALUES (?, ?, ?)");
        return $stmt->execute([$navId, $roleCode, $roleId]);
    }
    
    /**
     * Normalize role to role_id
     */
    private function normalizeRoleToId($role): string {
        // If already role_id, return it
        if (strpos($role, 'ROLE_') === 0) {
            return $role;
        }
        
        // Try to get from RoleService first
        try {
            require_once __DIR__ . '/../core/DependencyFactory.php';
            $roleService = \App\Core\DependencyFactory::getRoleService();
            $roleData = $roleService->getByRoleCode($role);
            if ($roleData && isset($roleData['role_id'])) {
                return $roleData['role_id'];
            }
        } catch (\Exception $e) {
            // Fall through to RoleMapper
        }
        
        // Fallback: Try RoleMapper
        try {
            require_once __DIR__ . '/../services/RoleMapper.php';
            $roleMapper = \App\Services\RoleMapper::getInstance();
            $mappedRoleId = $roleMapper->getRoleId($role);
            if ($mappedRoleId) {
                return $mappedRoleId;
            }
        } catch (\Exception $e) {
            // Fall through to final fallback
        }
        
        // Final fallback mapping (should not be needed after migration)
        $mapping = [
            'MANAGER' => 'ROLE_MANAGER',
            'WAITER' => 'ROLE_WAITER',
            'KITCHEN' => 'ROLE_KITCHEN',
            'CASHIER' => 'ROLE_CASHIER',
            'CUSTOMER' => 'ROLE_CUSTOMER',
        ];
        
        return $mapping[strtoupper($role)] ?? $role;
    }
    
    public function removeRole($navId, $role) {
        return $this->query()
            ->from('navigation_roles')
            ->where('nav_id', $navId)
            ->where('role', $role)
            ->delete();
    }
    
    public function deleteRoleMappings($navId) {
        // Use direct SQL to avoid QueryBuilder table confusion
        $sql = "DELETE FROM navigation_roles WHERE nav_id = :nav_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['nav_id' => $navId]);
        return $stmt->rowCount();
    }
    
    /**
     * Remove all roles for a navigation item
     * @param int $navId Navigation item ID
     * @return bool Success status
     */
    public function removeRolesForNav($navId) {
        return $this->deleteRoleMappings($navId);
    }
    
    /**
     * Add role to navigation item (supports both role_id and role_code)
     * @param int $navId Navigation item ID
     * @param mixed $role Role ID (int) or role code (string)
     * @return bool Success status
     */
    public function addRoleToNav($navId, $role) {
        try {
            // Check if role is numeric (role_id) or string (role_code)
            if (is_numeric($role)) {
                // role_id
                $sql = "INSERT INTO navigation_roles (nav_id, role_id) VALUES (:nav_id, :role_id) 
                        ON DUPLICATE KEY UPDATE nav_id = nav_id";
                $stmt = $this->db->prepare($sql);
                return $stmt->execute([
                    'nav_id' => $navId,
                    'role_id' => (int)$role
                ]);
            } else {
                // role_code
                $sql = "INSERT INTO navigation_roles (nav_id, role) VALUES (:nav_id, :role) 
                        ON DUPLICATE KEY UPDATE nav_id = nav_id";
                $stmt = $this->db->prepare($sql);
                return $stmt->execute([
                    'nav_id' => $navId,
                    'role' => $role
                ]);
            }
        } catch (\Exception $e) {
            error_log("NavigationItem::addRoleToNav error: " . $e->getMessage());
            return false;
        }
    }
    
    public function getNavigationAsArray($role = null, $includeInactive = false) {
        require_once __DIR__ . '/../helpers/translations.php';
        
        // For Super Admin, load all items including inactive ones
        $items = $role ? $this->getByRole($role) : ($includeInactive ? $this->getAllIncludingInactive() : $this->getAll());
        
        // Ensure $items is always an array
        if (!is_array($items)) {
            $items = [];
        }
        
        $result = [];
        $childrenMap = [];
        
        // First pass: Convert all items and separate children
        $parentItems = [];
        
        foreach ($items as $item) {
            $roles = $this->getRolesForNav($item['nav_id']);
            $navKey = $item['nav_key'] ?? '';
            
            // Use only label_tr from database - NO FALLBACKS
            $label = $item['label_tr'] ?? '';
            
            // Get icon from database - NO FALLBACK, use as is
            $icon = $item['icon'] ?? null;
            // If icon is empty or null, keep it as null (will be handled by renderer)
            if (empty($icon) || $icon === 'null' || $icon === 'NULL') {
                $icon = null;
            }
            
            $navItem = [
                'id' => $navKey,
                'icon' => $icon,
                'label' => $label,
                'url' => $item['url'],
                'permission' => $item['permission_key'],
                'roles' => $roles,
                'parent_id' => $item['parent_id'] ?? null
            ];
            
            // Separate parents and children
            if (empty($item['parent_id'])) {
                $parentItems[$item['nav_id']] = $navItem;
            } else {
                if (!isset($childrenMap[$item['parent_id']])) {
                    $childrenMap[$item['parent_id']] = [];
                }
                $childrenMap[$item['parent_id']][] = $navItem;
            }
        }
        
        // Second pass: Attach children to parents
        foreach ($parentItems as $navId => $parentItem) {
            if (isset($childrenMap[$navId]) && !empty($childrenMap[$navId])) {
                $parentItem['children'] = $childrenMap[$navId];
            }
            // Keep parent_id for grouping logic - NO REMOVAL
            $result[] = $parentItem;
        }
        
        return $result;
    }
    
    private function getRolesForNav($navId) {
        // Use direct SQL to avoid QueryBuilder ambiguity issues
        try {
            $sql = "SELECT nr.role_id, nr.role FROM navigation_roles nr WHERE nr.nav_id = :nav_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['nav_id' => $navId]);
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $roles = [];
            foreach ($results as $row) {
                // Prefer role_id if available, fallback to role (role_code)
                if (!empty($row['role_id'])) {
                    $roles[] = $row['role_id'];
                }
                // Also include role_code for backward compatibility
                if (!empty($row['role']) && !in_array($row['role'], $roles)) {
                    $normalizedRole = strtoupper(trim($row['role']));
                    if (!in_array($normalizedRole, $roles)) {
                        $roles[] = $normalizedRole;
                    }
                }
            }
            
            return $roles;
        } catch (\Exception $e) {
            error_log("NavigationItem::getRolesForNav error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Infer icon from nav_key or label
     */
    private function inferIconFromNavKey($navKey, $label = '') {
        $navKeyUpper = strtoupper($navKey);
        $labelLower = strtolower($label);
        
        if (stripos($navKeyUpper, 'DASHBOARD') !== false || stripos($labelLower, 'özet') !== false) return 'LayoutDashboard';
        if (stripos($navKeyUpper, 'SETTINGS') !== false || stripos($labelLower, 'ayar') !== false) return 'Settings';
        if (stripos($navKeyUpper, 'ANALYTICS') !== false || stripos($labelLower, 'analitik') !== false || stripos($labelLower, 'analiz') !== false) return 'BarChart';
        if (stripos($navKeyUpper, 'USER') !== false || stripos($labelLower, 'kullanıcı') !== false || stripos($labelLower, 'personel') !== false) return 'User';
        if (stripos($navKeyUpper, 'ORDER') !== false || stripos($labelLower, 'sipariş') !== false) return 'ShoppingCart';
        if (stripos($navKeyUpper, 'MENU') !== false || stripos($labelLower, 'menü') !== false) return 'FileText';
        if (stripos($navKeyUpper, 'TABLE') !== false || stripos($labelLower, 'masa') !== false) return 'Grid';
        if (stripos($navKeyUpper, 'PAYMENT') !== false || stripos($labelLower, 'ödeme') !== false) return 'CreditCard';
        if (stripos($navKeyUpper, 'REPORT') !== false || stripos($labelLower, 'rapor') !== false) return 'FileText';
        if (stripos($navKeyUpper, 'LOG') !== false) return 'FileText';
        if (stripos($navKeyUpper, 'ERROR') !== false || stripos($labelLower, 'hata') !== false) return 'AlertCircle';
        if (stripos($navKeyUpper, 'FINANCE') !== false || stripos($labelLower, 'finans') !== false) return 'Wallet';
        if (stripos($navKeyUpper, 'CATEGORY') !== false || stripos($labelLower, 'kategori') !== false) return 'Folder';
        if (stripos($navKeyUpper, 'CALENDAR') !== false || stripos($labelLower, 'takvim') !== false || stripos($labelLower, 'rezervasyon') !== false) return 'Calendar';
        if (stripos($navKeyUpper, 'PRINTER') !== false || stripos($labelLower, 'yazıcı') !== false) return 'Printer';
        if (stripos($navKeyUpper, 'SCREEN') !== false || stripos($labelLower, 'ekran') !== false) return 'Monitor';
        if (stripos($navKeyUpper, 'ROLE') !== false || stripos($labelLower, 'rol') !== false || stripos($labelLower, 'yetki') !== false) return 'Shield';
        if (stripos($navKeyUpper, 'SHIFT') !== false || stripos($labelLower, 'vardiya') !== false) return 'Clock';
        if (stripos($navKeyUpper, 'KITCHEN') !== false || stripos($labelLower, 'mutfak') !== false) return 'ChefHat';
        if (stripos($navKeyUpper, 'WAITER') !== false || stripos($labelLower, 'garson') !== false) return 'User';
        if (stripos($navKeyUpper, 'CASHIER') !== false || stripos($labelLower, 'kasa') !== false) return 'CreditCard';
        if (stripos($navKeyUpper, 'PACKAGE') !== false || stripos($labelLower, 'paket') !== false) return 'Package';
        if (stripos($navKeyUpper, 'SUBSCRIPTION') !== false || stripos($labelLower, 'abonelik') !== false) return 'Calendar';
        if (stripos($navKeyUpper, 'BUSINESS') !== false || stripos($labelLower, 'işletme') !== false) return 'Building';
        if (stripos($navKeyUpper, 'CONTACT') !== false || stripos($labelLower, 'iletişim') !== false) return 'Mail';
        if (stripos($navKeyUpper, 'SYSTEM') !== false || stripos($labelLower, 'sistem') !== false) return 'Settings';
        if (stripos($navKeyUpper, 'FEATURE') !== false || stripos($labelLower, 'özellik') !== false) return 'ToggleRight';
        if (stripos($navKeyUpper, 'MIGRATION') !== false || stripos($labelLower, 'migration') !== false) return 'RefreshCw';
        if (stripos($navKeyUpper, 'SAAS') !== false || stripos($labelLower, 'saas') !== false) return 'Building2';
        if (stripos($navKeyUpper, 'GLOBAL') !== false || stripos($labelLower, 'global') !== false) return 'TrendingUp';
        if (stripos($navKeyUpper, 'EXPENSE') !== false || stripos($labelLower, 'gider') !== false) return 'TrendingDown';
        if (stripos($navKeyUpper, 'INVOICE') !== false || stripos($labelLower, 'fatura') !== false) return 'Receipt';
        if (stripos($navKeyUpper, 'SUPPLIER') !== false || stripos($labelLower, 'tedarikçi') !== false) return 'Package';
        if (stripos($navKeyUpper, 'WASTE') !== false || stripos($labelLower, 'fire') !== false) return 'Trash';
        if (stripos($navKeyUpper, 'INVENTORY') !== false || stripos($labelLower, 'stok') !== false) return 'Package';
        
        return 'Circle';
    }
}

