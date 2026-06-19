<?php
namespace App\Repositories;

use App\Core\BaseRepository;

class PackageRepository extends BaseRepository {
    protected $table = 'packages';
    protected $primaryKey = 'package_id';
    
    /**
     * Get all packages (including inactive ones for admin)
     * @return array
     */
    public function getAll(): array {
        try {
            // Check if table exists
            $checkTable = $this->db->query("SHOW TABLES LIKE '{$this->table}'");
            if ($checkTable->rowCount() === 0) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning('PackageRepository::getAll - Table does not exist', [
                        'table' => $this->table
                    ]);
                }
                return [];
            }

            // Get ALL packages (including inactive) - for admin view
            // Try with created_at first, fallback to package_id if column doesn't exist
            $sql = "SELECT * FROM {$this->table} ORDER BY created_at DESC, package_id DESC";
            $results = $this->fetchAll($sql);
            
            // If empty, try without created_at ordering
            if (empty($results)) {
                $sql = "SELECT * FROM {$this->table} ORDER BY package_id DESC";
                $results = $this->fetchAll($sql);
            }
            
            // Log for debugging
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::debug('PackageRepository::getAll - Packages retrieved', [
                    'count' => count($results),
                    'table' => $this->table
                ]);
            }
            
            // Ensure results is always an array
            return is_array($results) ? $results : [];
        } catch (\PDOException $e) {
            // If ordering fails, try without ordering
            try {
                $sql = "SELECT * FROM {$this->table}";
                $results = $this->fetchAll($sql);
                
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning('PackageRepository::getAll - Fallback query used', [
                        'error' => $e->getMessage(),
                        'count' => count($results)
                    ]);
                }
                
                return is_array($results) ? $results : [];
            } catch (\Exception $e2) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('PackageRepository::getAll - Query failed', [
                        'error' => $e2->getMessage(),
                        'table' => $this->table
                    ]);
                }
                return [];
            }
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('PackageRepository::getAll - Exception', [
                    'error' => $e->getMessage(),
                    'table' => $this->table,
                    'trace' => $e->getTraceAsString()
                ]);
            }
            return [];
        }
    }
    
    /**
     * Get all active packages
     * @return array
     */
    public function getActivePackages(): array {
        try {
            $checkTable = $this->db->query("SHOW TABLES LIKE '{$this->table}'");
            if ($checkTable->rowCount() === 0) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning('PackageRepository::getActivePackages - Table does not exist', [
                        'table' => $this->table
                    ]);
                }
                return [];
            }

            $sql = "SELECT * FROM {$this->table} WHERE is_active = 1 ORDER BY package_id DESC";
            $results = $this->fetchAll($sql);
            
            // Log for debugging
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::debug('PackageRepository::getActivePackages - Packages retrieved', [
                    'count' => count($results),
                    'table' => $this->table
                ]);
            }
            
            // Ensure results is always an array
            return is_array($results) ? $results : [];
        } catch (\PDOException $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('PackageRepository::getActivePackages - PDOException', [
                    'error' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'table' => $this->table,
                    'trace' => $e->getTraceAsString()
                ]);
            }
            return [];
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('PackageRepository::getActivePackages - Exception', [
                    'error' => $e->getMessage(),
                    'table' => $this->table,
                    'trace' => $e->getTraceAsString()
                ]);
            }
            return [];
        }
    }
    
    /**
     * Get package permissions
     * @param string $packageId
     * @return array
     */
    public function getPackagePermissions(string $packageId): array {
        try {
            $checkPackagePerms = $this->db->query("SHOW TABLES LIKE 'package_permissions'");
            $checkSystemPerms = $this->db->query("SHOW TABLES LIKE 'system_permissions'");
            
            if ($checkPackagePerms->rowCount() === 0) {
                return [];
            }
            
            // If system_permissions table exists, join with it
            if ($checkSystemPerms->rowCount() > 0) {
                $sql = "SELECT sp.*, COALESCE(sp.permission_key, pp.permission_id) as permission_key 
                        FROM package_permissions pp
                        LEFT JOIN system_permissions sp ON pp.permission_id = sp.permission_id
                        WHERE pp.package_id = :package_id
                        ORDER BY COALESCE(sp.permission_key, pp.permission_id)";
            } else {
                // If system_permissions doesn't exist, use permission_id directly as permission_key
                $sql = "SELECT pp.permission_id as permission_key, pp.permission_id
                        FROM package_permissions pp
                        WHERE pp.package_id = :package_id
                        ORDER BY pp.permission_id";
            }
            return $this->fetchAll($sql, ['package_id' => $packageId]);
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * Get package permission keys
     * @param string $packageId
     * @return array
     */
    public function getPackagePermissionKeys(string $packageId): array {
        $permissions = $this->getPackagePermissions($packageId);
        // Use permission_key if available, otherwise fallback to permission_id
        $keys = [];
        foreach ($permissions as $perm) {
            $key = $perm['permission_key'] ?? $perm['permission_id'] ?? null;
            if ($key) {
                $keys[] = $key;
            }
        }
        return array_unique($keys);
    }
    
    /**
     * Assign permission to package
     * @param string $packageId
     * @param string $permissionId
     * @return bool
     */
    public function assignPermission(string $packageId, string $permissionId): bool {
        try {
            $checkTable = $this->db->query("SHOW TABLES LIKE 'package_permissions'");
            if ($checkTable->rowCount() === 0) {
                return false;
            }
            
            require_once __DIR__ . '/../helpers/functions.php';
            $id = generateId('ppkg');
            
            $sql = "INSERT INTO package_permissions (package_permission_id, package_id, permission_id)
                    VALUES (:id, :package_id, :permission_id)
                    ON DUPLICATE KEY UPDATE package_permission_id = package_permission_id";
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'id' => $id,
                'package_id' => $packageId,
                'permission_id' => $permissionId
            ]);
        } catch (\PDOException $e) {
            // Duplicate entry - already assigned
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Remove permission from package
     * @param string $packageId
     * @param string $permissionId
     * @return bool
     */
    public function removePermission(string $packageId, string $permissionId): bool {
        try {
            $checkTable = $this->db->query("SHOW TABLES LIKE 'package_permissions'");
            if ($checkTable->rowCount() === 0) {
                return false;
            }
            
            $sql = "DELETE FROM package_permissions 
                    WHERE package_id = :package_id AND permission_id = :permission_id";
            return $this->execute($sql, [
                'package_id' => $packageId,
                'permission_id' => $permissionId
            ]);
        } catch (\Exception $e) {
            return false;
        }
    }

    // ─────────────────────────────────────────────────────────────────
    //  Paket ↔ Rol eşlemesi (yeni rol-bazlı paket yönetimi)
    // ─────────────────────────────────────────────────────────────────

    /**
     * Paket-rol eşlemelerini getir.
     *
     * @return array [{role_id, role_code, role_name, is_owner_role}, ...]
     */
    public function getPackageRoles(string $packageId): array {
        try {
            $check = $this->db->query("SHOW TABLES LIKE 'package_roles'");
            if ($check->rowCount() === 0) return [];

            $sql = "SELECT pr.role_id, pr.is_owner_role,
                           r.role_code, r.role_name, r.description
                    FROM package_roles pr
                    INNER JOIN roles r ON r.role_id = pr.role_id
                    WHERE pr.package_id = :pid
                    ORDER BY pr.is_owner_role DESC, r.role_code ASC";
            return $this->fetchAll($sql, ['pid' => $packageId]);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Paket-rol eşlemelerini toplu güncelle.
     *  - Listedeki rol eşlemeleri oluşturulur (varsa güncellenir)
     *  - Eski eşlemeler silinir (owner'ı korur)
     *
     * @param array $roles  [{role_id, is_owner_role?}, ...]   veya [role_id, ...]
     */
    public function syncPackageRoles(string $packageId, array $roles): bool {
        $check = $this->db->query("SHOW TABLES LIKE 'package_roles'");
        if ($check->rowCount() === 0) return false;

        // Normalize giriş
        $normalized = [];
        foreach ($roles as $entry) {
            if (is_string($entry)) {
                $normalized[] = ['role_id' => $entry, 'is_owner_role' => 0];
            } elseif (is_array($entry) && !empty($entry['role_id'])) {
                $normalized[] = [
                    'role_id' => $entry['role_id'],
                    'is_owner_role' => !empty($entry['is_owner_role']) ? 1 : 0,
                ];
            }
        }

        require_once __DIR__ . '/../helpers/functions.php';
        $this->db->beginTransaction();
        try {
            // Mevcut eşlemelerle karşılaştırma
            $existing = $this->fetchAll(
                "SELECT role_id, is_owner_role FROM package_roles WHERE package_id = :pid",
                ['pid' => $packageId]
            );
            $existingIds = array_column($existing, 'role_id');
            $newIds = array_column($normalized, 'role_id');

            // Silinecekler: eski - yeni
            $toDelete = array_diff($existingIds, $newIds);
            if ($toDelete) {
                $in = implode(',', array_fill(0, count($toDelete), '?'));
                $stmt = $this->db->prepare(
                    "DELETE FROM package_roles WHERE package_id = ? AND role_id IN ($in)"
                );
                $stmt->execute(array_merge([$packageId], array_values($toDelete)));
            }

            // Upsert
            foreach ($normalized as $r) {
                $sql = "INSERT INTO package_roles (package_role_id, package_id, role_id, is_owner_role)
                        VALUES (:id, :pid, :rid, :owner)
                        ON DUPLICATE KEY UPDATE is_owner_role = VALUES(is_owner_role)";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    'id' => generateId('pkgrole'),
                    'pid' => $packageId,
                    'rid' => $r['role_id'],
                    'owner' => $r['is_owner_role'],
                ]);
            }

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    /**
     * Paketin owner (işletme sahibi) rolünü döndürür.
     * Yoksa ilk rol, o da yoksa null.
     */
    public function getPackageOwnerRoleId(string $packageId): ?string {
        try {
            $check = $this->db->query("SHOW TABLES LIKE 'package_roles'");
            if ($check->rowCount() === 0) return null;

            $stmt = $this->db->prepare("
                SELECT role_id FROM package_roles
                WHERE package_id = :pid
                ORDER BY is_owner_role DESC, role_id ASC
                LIMIT 1
            ");
            $stmt->execute(['pid' => $packageId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row ? $row['role_id'] : null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
