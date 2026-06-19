<?php
namespace App\Repositories;

use App\Core\BaseRepository;
use PDO;

/**
 * User Repository
 * Handles database operations for users
 * 
 * @package App\Repositories
 */
class UserRepository extends BaseRepository {
    protected $table = 'users';
    protected $primaryKey = 'user_id';

    /**
     * Find user by credentials
     * @param array $credentials User credentials (name, password)
     * @return array|null User data or null
     */
    public function findByCredentials($credentials) {
        $sql = "SELECT * FROM {$this->table} WHERE name = :name LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['name' => $credentials['name']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Find user by email (for customer authentication)
     * @param string $email Email address
     * @return array|null User data or null
     */
    public function findByEmail(string $email): ?array {
        try {
            // Email'i name olarak sakladığımız için name ile arama yap
            $sql = "SELECT * FROM {$this->table} WHERE name = :email LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['email' => $email]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result === false) {
                return null;
            }
            
            return $result;
        } catch (\PDOException $e) {
            error_log("UserRepository::findByEmail - Database error: " . $e->getMessage());
            return null;
        } catch (\Exception $e) {
            error_log("UserRepository::findByEmail - Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Find user by user ID
     * @param string $userId User ID
     * @return array|null User data or null
     */
    public function findByUserId($userId): ?array {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE user_id = :user_id LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // PDO::fetch() returns false if no row found, convert to null
            if ($result === false) {
                $result = null;
            }
            
            // Ensure we return array or null, never false
            if ($result !== null && !is_array($result)) {
                error_log("UserRepository::findByUserId - Unexpected result type: " . gettype($result));
                return null;
            }
            
            // Log for debugging
            error_log("UserRepository::findByUserId - userId: {$userId}, found: " . ($result ? 'yes' : 'no') . ", type: " . gettype($result));
            
            return $result;
        } catch (\PDOException $e) {
            error_log("UserRepository::findByUserId - Database error: " . $e->getMessage());
            return null;
        } catch (\Exception $e) {
            error_log("UserRepository::findByUserId - Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get staff members by role (scoped to the current tenant).
     * SECURITY: This method MUST filter by tenant_id. Returning users from
     * other tenants (the previous behaviour) leaks every business's staff.
     * @param string $role User role
     * @return array Staff members
     */
    public function getStaffByRole($role) {
        $tenantId = null;
        if (class_exists('\App\Core\TenantResolver')) {
            $tenantId = \App\Core\TenantResolver::resolve();
        }
        if (empty($tenantId)) {
            $tenantId = $_SESSION['business_id'] ?? ($_SESSION['customer_id'] ?? null);
        }
        if (empty($tenantId)) {
            // Fail closed: without a tenant we cannot return staff safely.
            return [];
        }
        $sql = "SELECT * FROM {$this->table} WHERE role = :role AND tenant_id = :tenant_id ORDER BY name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['role' => $role, 'tenant_id' => $tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find user by PIN (supports both hashed and plain text PINs)
     * CRITICAL: Filters by tenant context for multi-tenant isolation
     * @param string $pin PIN to search for
     * @return array|null User data or null
     */
    public function findByPin(string $pin): ?array {
        try {
            // Get tenant context for filtering - ensure it's set
            $tenantId = null;
            $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
            $subdomain = \App\Core\TenantContext::getSubdomainFromHost($host);
            
            if (class_exists('\App\Core\TenantContext')) {
                $tenantId = \App\Core\TenantContext::getId();
                
                // CRITICAL: If tenant context not set, try to set from subdomain
                // This ensures subdomain-based PIN login works correctly
                if (!$tenantId && $subdomain) {
                    try {
                        // First try to query database directly (fastest method)
                        try {
                            require_once __DIR__ . '/../core/DependencyFactory.php';
                            $db = \App\Core\DependencyFactory::getDatabase();
                            $stmt = $db->prepare("SELECT customer_id, company_name FROM customers WHERE subdomain = :subdomain AND is_active = 1 LIMIT 1");
                            $stmt->execute(['subdomain' => $subdomain]);
                            $customer = $stmt->fetch(\PDO::FETCH_ASSOC);
                            if ($customer && !empty($customer['customer_id'])) {
                                \App\Core\TenantContext::setId($customer['customer_id']);
                                $tenantId = $customer['customer_id'];
                                
                                if (class_exists('\App\Core\Logger')) {
                                    \App\Core\Logger::info('UserRepository::findByPin - Tenant context set from database', [
                                        'subdomain' => $subdomain,
                                        'tenant_id' => $tenantId,
                                        'customer_name' => $customer['company_name'] ?? 'unknown'
                                    ]);
                                }
                            }
                        } catch (\Exception $dbException) {
                            // Log but continue
                            if (class_exists('\App\Core\Logger')) {
                                \App\Core\Logger::warning('UserRepository::findByPin - Failed to set tenant from DB', [
                                    'error' => $dbException->getMessage(),
                                    'subdomain' => $subdomain
                                ]);
                            }
                        }
                        
                        // If still not set, try TenantMiddleware as fallback
                        if (!$tenantId) {
                            try {
                                require_once __DIR__ . '/../middleware/TenantMiddleware.php';
                                $tenantMiddleware = new \App\Middleware\TenantMiddleware();
                                $tenantMiddleware->handle();
                                $tenantId = \App\Core\TenantContext::getId();
                                
                                if (class_exists('\App\Core\Logger')) {
                                    \App\Core\Logger::debug('UserRepository::findByPin - Tenant context set from TenantMiddleware', [
                                        'subdomain' => $subdomain,
                                        'tenant_id' => $tenantId
                                    ]);
                                }
                            } catch (\Exception $e) {
                                // Log but continue - tenant context might not be critical for main domain
                                if (class_exists('\App\Core\Logger')) {
                                    \App\Core\Logger::debug('UserRepository::findByPin - Failed to set tenant context via middleware', [
                                        'error' => $e->getMessage(),
                                        'subdomain' => $subdomain
                                    ]);
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        // Log but continue - tenant context might not be critical for main domain
                        if (class_exists('\App\Core\Logger')) {
                            \App\Core\Logger::warning('UserRepository::findByPin - Failed to set tenant context', [
                                'error' => $e->getMessage(),
                                'subdomain' => $subdomain
                            ]);
                        }
                    }
                }
            }
            
            // Build SQL query with tenant filtering
            // CRITICAL: Filter by business_id at SQL level for performance and security
            $sql = "SELECT * FROM {$this->table} WHERE pin IS NOT NULL AND pin != ''";
            $params = [];
            
            // If tenant context is set, filter by tenant_id
            if ($tenantId) {
                $sql .= " AND tenant_id = :business_id";
                $params['business_id'] = $tenantId;
            }
            
            $sql .= " ORDER BY name";
            
            // Do NOT log the actual PIN or derivatives that would allow
            // reversing the credential from logs. Only log metadata.
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::info('UserRepository::findByPin - candidates fetched', [
                    'tenant_id'   => $tenantId,
                    'pin_length'  => strlen($pin),
                    'users_found' => count($users),
                ]);
            }
            
            // Check each user's PIN
            foreach ($users as $user) {
                if (!isset($user['pin']) || empty($user['pin'])) {
                    continue;
                }
                
                $storedPin = $user['pin'];
                $originalStoredPin = $storedPin; // Keep original for logging
                
                // CRITICAL: Check if PIN is encrypted (using EncryptionHelper)
                $isEncrypted = false;
                $decryptionSuccess = false;
                
                if (class_exists('\App\Helpers\EncryptionHelper')) {
                    require_once __DIR__ . '/../helpers/EncryptionHelper.php';
                    $isEncrypted = \App\Helpers\EncryptionHelper::isEncrypted($storedPin);
                    
                    if ($isEncrypted) {
                        // Decrypt PIN for comparison
                        try {
                            $decryptedPin = \App\Helpers\EncryptionHelper::decrypt($storedPin);
                            
                            // CRITICAL: Verify decryption actually worked
                            // decrypt() now returns false on failure
                            // If decrypted PIN is not false AND not empty, decryption succeeded
                            if ($decryptedPin !== false && !empty($decryptedPin)) {
                                // Decryption successful
                                $storedPin = $decryptedPin;
                                $decryptionSuccess = true;
                                
                                if (class_exists('\App\Core\Logger')) {
                                    \App\Core\Logger::info('UserRepository::findByPin - PIN decrypted successfully', [
                                        'user_id' => $user['user_id'] ?? 'unknown',
                                        'user_name' => $user['name'] ?? 'unknown',
                                        'original_length' => strlen($originalStoredPin),
                                        'decrypted_length' => strlen($storedPin)
                                    ]);
                                }
                            } else {
                                // Decryption failed (returned false)
                                // This means PIN is encrypted but we can't decrypt it
                                // (wrong key or corrupted data)
                                
                                if (class_exists('\App\Core\Logger')) {
                                    \App\Core\Logger::warning('UserRepository::findByPin - PIN is encrypted but decryption failed', [
                                        'user_id' => $user['user_id'] ?? 'unknown',
                                        'user_name' => $user['name'] ?? 'unknown',
                                        'pin_preview' => substr($originalStoredPin, 0, 30),
                                        'message' => 'Cannot decrypt PIN - encryption key mismatch or corrupted data'
                                    ]);
                                }
                                
                                // Skip this user - we can't verify their PIN
                                continue;
                            }
                        } catch (\Exception $e) {
                            if (class_exists('\App\Core\Logger')) {
                                \App\Core\Logger::error('UserRepository::findByPin - Decryption exception', [
                                    'user_id' => $user['user_id'] ?? 'unknown',
                                    'error' => $e->getMessage(),
                                    'pin_preview' => substr($originalStoredPin, 0, 30)
                                ]);
                            }
                            // Skip this user - decryption failed
                            continue;
                        }
                    }
                }
                
                // Check if stored PIN is hashed (bcrypt)
                // Only check for hash if not encrypted (encrypted PINs are decrypted above)
                $isHashed = false;
                if (!$isEncrypted && strlen($storedPin) >= 60) {
                    $isHashed = (strpos($storedPin, '$2y$') === 0 || 
                                 strpos($storedPin, '$2a$') === 0 || 
                                 strpos($storedPin, '$2b$') === 0);
                }
                
                if ($isHashed) {
                    // Compare using password_verify for hashed PINs
                    // CRITICAL: password_verify is the correct way to verify bcrypt hashes
                    $pinMatch = password_verify(trim($pin), $storedPin);
                    
                    // If password_verify fails, try with string conversion (in case of type mismatch)
                    if (!$pinMatch) {
                        $pinMatch = password_verify((string)trim($pin), $storedPin);
                    }
                    
                    // CRITICAL: For BUSINESS_MANAGER users, PIN field might contain password hash from old registrations
                    // If PIN verification fails, check if there's a password field and try that too
                    // This is a migration path for existing users who have password hash in PIN field
                    if (!$pinMatch && isset($user['password']) && !empty($user['password'])) {
                        $passwordHash = $user['password'];
                        // Check if password field also contains a hash
                        $isPasswordHashed = strlen($passwordHash) >= 60 && 
                                           (strpos($passwordHash, '$2y$') === 0 || 
                                            strpos($passwordHash, '$2a$') === 0 || 
                                            strpos($passwordHash, '$2b$') === 0);
                        
                        if ($isPasswordHashed) {
                            // Try verifying PIN against password hash (for migration compatibility)
                            $pinMatch = password_verify(trim($pin), $passwordHash);
                            if (!$pinMatch) {
                                $pinMatch = password_verify((string)trim($pin), $passwordHash);
                            }
                            
                            if ($pinMatch && class_exists('\App\Core\Logger')) {
                                \App\Core\Logger::info('UserRepository::findByPin - PIN matched against password hash (migration)', [
                                    'user_id' => $user['user_id'] ?? 'unknown',
                                    'user_name' => $user['name'] ?? 'unknown',
                                    'message' => 'PIN field contains password hash - this user should have PIN updated'
                                ]);
                            }
                        }
                    }
                    
                    // Credential-aware logging: NEVER emit the raw PIN
                    // or the stored hash preview in a way that could be
                    // correlated.
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::info('UserRepository::findByPin - PIN verification (hashed)', [
                            'user_id'    => $user['user_id'] ?? 'unknown',
                            'pin_match'  => $pinMatch,
                            'pin_length' => strlen($pin),
                            'is_hashed'  => $isHashed,
                            'is_encrypted' => $isEncrypted,
                            'tenant_id'  => $tenantId,
                        ]);
                    }
                    
                    if ($pinMatch) {
                        // Double-check tenant isolation with type-safe comparison
                        $userBusinessId = $user['tenant_id'] ?? null;
                        $userBusinessIdStr = trim((string)$userBusinessId);
                        $tenantIdStr = trim((string)$tenantId);
                        
                        if ($tenantId && (!isset($userBusinessId) || $userBusinessIdStr !== $tenantIdStr)) {
                            if (class_exists('\App\Core\Logger')) {
                                \App\Core\Logger::warning('UserRepository::findByPin - Tenant mismatch', [
                                    'user_id' => $user['user_id'] ?? 'unknown',
                                    'user_business_id' => $userBusinessId,
                                    'user_business_id_str' => $userBusinessIdStr,
                                    'tenant_id' => $tenantId,
                                    'tenant_id_str' => $tenantIdStr,
                                    'comparison' => ($userBusinessIdStr === $tenantIdStr) ? 'match' : 'mismatch'
                                ]);
                            }
                            continue;
                        }
                        
                        // DEBUG: Log successful PIN match
                        if (class_exists('\App\Core\Logger')) {
                            \App\Core\Logger::info('UserRepository::findByPin - PIN match found', [
                                'user_id' => $user['user_id'],
                                'user_name' => $user['name'] ?? 'unknown',
                                'user_business_id' => $userBusinessId,
                                'tenant_id' => $tenantId
                            ]);
                        }
                        
                        return $user;
                    }
                } else {
                    // Compare plain text PINs directly (or decrypted PINs)
                    // CRITICAL: Trim both PINs to handle whitespace issues
                    $pinTrimmed = trim($pin);
                    $storedPinTrimmed = trim($storedPin);
                    $pinMatch = ($pinTrimmed === $storedPinTrimmed);
                    
                    // Credential-aware logging: never emit the stored
                    // or input PIN, even in the plaintext compare path.
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::info('UserRepository::findByPin - PIN verification (plaintext compare)', [
                            'user_id'       => $user['user_id'] ?? 'unknown',
                            'pin_match'     => $pinMatch,
                            'pin_length'    => strlen($pinTrimmed),
                            'stored_length' => strlen($storedPinTrimmed),
                            'is_encrypted'  => $isEncrypted,
                            'tenant_id'     => $tenantId,
                        ]);
                    }
                    
                    if ($pinMatch) {
                        // Double-check tenant isolation with type-safe comparison
                        $userBusinessId = $user['tenant_id'] ?? null;
                        $userBusinessIdStr = trim((string)$userBusinessId);
                        $tenantIdStr = trim((string)$tenantId);
                        
                        if ($tenantId && (!isset($userBusinessId) || $userBusinessIdStr !== $tenantIdStr)) {
                            if (class_exists('\App\Core\Logger')) {
                                \App\Core\Logger::warning('UserRepository::findByPin - Tenant mismatch', [
                                    'user_id' => $user['user_id'] ?? 'unknown',
                                    'user_business_id' => $userBusinessId,
                                    'user_business_id_str' => $userBusinessIdStr,
                                    'tenant_id' => $tenantId,
                                    'tenant_id_str' => $tenantIdStr,
                                    'comparison' => ($userBusinessIdStr === $tenantIdStr) ? 'match' : 'mismatch'
                                ]);
                            }
                            continue;
                        }
                        
                        // DEBUG: Log successful PIN match
                        if (class_exists('\App\Core\Logger')) {
                            \App\Core\Logger::info('UserRepository::findByPin - PIN match found (plain text)', [
                                'user_id' => $user['user_id'],
                                'user_name' => $user['name'] ?? 'unknown',
                                'user_business_id' => $userBusinessId,
                                'tenant_id' => $tenantId
                            ]);
                        }
                        
                        return $user;
                    }
                }
            }
            
            // DEBUG: Log if no user found after checking all users
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('UserRepository::findByPin - No matching user found after PIN verification', [
                    'pin_length' => strlen($pin),
                    'pin_preview' => substr($pin, 0, 2) . '**',
                    'users_checked' => count($users),
                    'tenant_id' => $tenantId,
                    'sql' => $sql,
                    'params' => $params
                ]);
            }
            
            return null;
        } catch (\PDOException $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('UserRepository::findByPin - Database error', [
                    'error' => $e->getMessage(),
                    'pin_length' => strlen($pin),
                    'tenant_id' => $tenantId ?? 'none'
                ]);
            }
            return null;
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('UserRepository::findByPin - Error', [
                    'error' => $e->getMessage(),
                    'pin_length' => strlen($pin),
                    'tenant_id' => $tenantId ?? 'none'
                ]);
            }
            return null;
        }
    }
    
    /**
     * Get all users scoped to the current tenant.
     * SECURITY: If no tenant can be resolved we fail closed and return an
     * empty array. Previously this method returned EVERY user in the
     * database whenever the session tenant was missing, which leaked
     * emails, names, roles and PIN hashes across all businesses.
     * @return array Users belonging to the caller's tenant
     */
    public function getAll() {
        $tenantId = null;
        if (class_exists('\App\Core\TenantResolver')) {
            $tenantId = \App\Core\TenantResolver::resolve();
        }
        if (empty($tenantId)) {
            $tenantId = $_SESSION['business_id'] ?? ($_SESSION['customer_id'] ?? null);
        }
        if (empty($tenantId)) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('UserRepository::getAll - No tenant context, refusing to return all users');
            }
            return [];
        }
        return $this->getByBusinessId($tenantId);
    }
    
    /**
     * Get all users for a specific business (tenant isolation)
     * @param string $businessId Business/customer ID
     * @return array Users belonging to the business
     */
    public function getByBusinessId(string $businessId): array {
        if (empty($businessId)) {
            return [];
        }
        try {
            $sql = "SELECT * FROM {$this->table} WHERE tenant_id = :business_id ORDER BY name";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['business_id' => $businessId]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return is_array($result) ? $result : [];
        } catch (\PDOException $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('UserRepository::getByBusinessId - Error', ['error' => $e->getMessage()]);
            }
            return [];
        }
    }

    /**
     * Get business_id by user_id
     * @param string $userId User ID
     * @return string|null Business ID or null
     */
    public function getBusinessIdByUserId(string $userId): ?string {
        try {
            $sql = "SELECT tenant_id FROM {$this->table} WHERE user_id = :user_id LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && !empty($result['tenant_id'])) {
                return $result['tenant_id'];
            }
            
            return null;
        } catch (\PDOException $e) {
            error_log("UserRepository::getBusinessIdByUserId - Database error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Delete all users except the specified one
     * @param string $userId User ID to keep
     * @return bool Success
     */
    public function deleteAllExcept(string $userId): bool {
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} != :user_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['user_id' => $userId]);
    }
    
    /**
     * Delete all users
     * WARNING: Use with extreme caution!
     * @return bool Success
     */
    public function deleteAll(): bool {
        $sql = "DELETE FROM {$this->table}";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute();
    }
    
    /**
     * Load role and permissions for users (batch loading)
     * @param array $users Users data
     * @return array Users with role and permissions
     */
    protected function loadRelationForMany(array $users, string $relation): array {
        if ($relation === 'role' || $relation === 'permissions') {
            return $this->loadRoleAndPermissionsForUsers($users);
        }
        return parent::loadRelationForMany($users, $relation);
    }
    
    /**
     * Load role and permissions for multiple users (batch loading)
     * @param array $users Users data
     * @return array Users with role and permissions
     */
    private function loadRoleAndPermissionsForUsers(array $users): array {
        if (empty($users)) {
            return $users;
        }
        
        // Get unique role codes
        $roleCodes = array_unique(array_column($users, 'role'));
        
        // Load role and permission data in batch
        try {
            require_once __DIR__ . '/../core/DependencyFactory.php';
            $roleService = \App\Core\DependencyFactory::getRoleService();
            
            $rolesData = [];
            foreach ($roleCodes as $roleCode) {
                $role = $roleService->getByRoleCode($roleCode);
                if ($role) {
                    $rolesData[$roleCode] = [
                        'role' => $role,
                        'permissions' => $roleService->getRolePermissionKeys($role['role_id'])
                    ];
                }
            }
            
            // Attach role and permissions to users
            foreach ($users as &$user) {
                $roleCode = $user['role'] ?? '';
                if (isset($rolesData[$roleCode])) {
                    $user['role_data'] = $rolesData[$roleCode]['role'];
                    $user['permissions'] = $rolesData[$roleCode]['permissions'];
                }
            }
        } catch (\Exception $e) {
            \App\Core\Logger::error("Error loading role and permissions for users", [
                'error' => $e->getMessage()
            ]);
        }
        
        return $users;
    }
}