<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\UserRepository;

/**
 * User Service
 * Handles user-related business logic including authentication and user management
 * 
 * @package App\Services
 */
class UserService extends BaseService {
    /**
     * Constructor
     * @param UserRepository $userRepository User repository instance
     */
    public function __construct(UserRepository $userRepository) {
        parent::__construct($userRepository);
    }

    /**
     * Authenticate user with credentials
     * @param array $credentials User credentials (name, password)
     * @return array|false User data on success, false on failure
     */
    public function authenticate($credentials) {
        $user = $this->repository->findByCredentials($credentials);
        if ($user && password_verify($credentials['password'], $user['password'])) {
            return $user;
        }
        return false;
    }

    /**
     * Find user by credentials
     * @param array $credentials User credentials
     * @return array|null User data or null
     */
    public function findByCredentials($credentials) {
        return $this->repository->findByCredentials($credentials);
    }

    /**
     * Create a new user
     * PINs are stored encrypted for security
     * 
     * @param array $data User data including user_id, name, pin, role
     * @return bool|string User ID on success, false on failure
     * 
     * @example
     * $userService->create([
     *     'user_id' => 'u1',
     *     'name' => 'John Doe',
     *     'pin' => '1234',
     *     'role' => 'WAITER'
     * ]);
     */
    public function create(array $data) {
        try {
            // Validate required fields
            if (empty($data['user_id']) || empty($data['name'])) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning('UserService::create - Missing required fields', [
                        'has_user_id' => !empty($data['user_id']),
                        'has_name' => !empty($data['name']),
                        'data_keys' => array_keys($data)
                    ]);
                }
                return false;
            }
            
            // Encrypt PIN before storing (not hash - we need to decrypt and show it in admin panel)
            if (isset($data['pin']) && !empty($data['pin'])) {
                $pin = trim($data['pin']);
                
                require_once __DIR__ . '/../helpers/EncryptionHelper.php';
                
                // Check if PIN is already encrypted
                $isEncrypted = \App\Helpers\EncryptionHelper::isEncrypted($pin);
                
                // Check if PIN is already hashed (old format - keep as is for backward compatibility)
                $isHashed = strlen($pin) >= 60 && 
                           (strpos($pin, '$2y$') === 0 || 
                            strpos($pin, '$2a$') === 0 || 
                            strpos($pin, '$2b$') === 0);
                
                // Only encrypt if not already encrypted or hashed
                if (!$isEncrypted && !$isHashed) {
                    $data['pin'] = \App\Helpers\EncryptionHelper::encrypt($pin);
                }
            }
            
            $result = parent::create($data);
            
            if ($result === false) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('UserService::create - Parent create returned false', [
                        'user_id' => $data['user_id'] ?? 'unknown',
                        'name' => $data['name'] ?? 'unknown',
                        'data_keys' => array_keys($data)
                    ]);
                }
            }
            
            return $result;
        } catch (\PDOException $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('UserService::create - PDOException', [
                    'message' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'sql_state' => $e->errorInfo[0] ?? 'unknown',
                    'error_code' => $e->errorInfo[1] ?? 'unknown',
                    'error_message' => $e->errorInfo[2] ?? 'unknown',
                    'user_id' => $data['user_id'] ?? 'unknown',
                    'name' => $data['name'] ?? 'unknown'
                ]);
            }
            // Re-throw to allow controller to handle
            throw $e;
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('UserService::create - Exception', [
                    'message' => $e->getMessage(),
                    'user_id' => $data['user_id'] ?? 'unknown',
                    'name' => $data['name'] ?? 'unknown',
                    'trace' => $e->getTraceAsString()
                ]);
            }
            // Re-throw to allow controller to handle
            throw $e;
        }
    }

    /**
     * Update user
     * PINs are stored encrypted for security
     * 
     * @param string $id User ID
     * @param array $data User data to update
     * @return bool Success
     * 
     * @example
     * $userService->update('u1', ['name' => 'Jane Doe', 'pin' => '5678']);
     */
    public function update(string $id, array $data): bool {
        // Encrypt PIN before storing (not hash - we need to decrypt and show it in admin panel)
        if (isset($data['pin']) && !empty($data['pin'])) {
            $pin = trim($data['pin']);
            
            require_once __DIR__ . '/../helpers/EncryptionHelper.php';
            
            // Check if PIN is already encrypted
            $isEncrypted = \App\Helpers\EncryptionHelper::isEncrypted($pin);
            
            // Check if PIN is already hashed (old format - keep as is for backward compatibility)
            $isHashed = strlen($pin) >= 60 && 
                       (strpos($pin, '$2y$') === 0 || 
                        strpos($pin, '$2a$') === 0 || 
                        strpos($pin, '$2b$') === 0);
            
            // Only encrypt if not already encrypted or hashed
            if (!$isEncrypted && !$isHashed) {
                $data['pin'] = \App\Helpers\EncryptionHelper::encrypt($pin);
            }
        }
        return parent::update($id, $data);
    }
    
    /**
     * Get decrypted PIN for a user
     * 
     * @param string $userId User ID
     * @return string|null Decrypted PIN or null if not found
     */
    public function getDecryptedPin(string $userId): ?string {
        $user = $this->findByUserId($userId);
        if (!$user || empty($user['pin'])) {
            return null;
        }
        
        require_once __DIR__ . '/../helpers/EncryptionHelper.php';
        
        try {
            // Try to decrypt
            $decrypted = \App\Helpers\EncryptionHelper::decrypt($user['pin']);
            return $decrypted;
        } catch (\Exception $e) {
            // If decryption fails, might be old format or plain text
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('UserService::getDecryptedPin - Decryption failed', [
                    'user_id' => $userId,
                    'error' => $e->getMessage()
                ]);
            }
            return null;
        }
    }
    
    /**
     * Update user password
     * @param string $userId User ID
     * @param string $newPassword New password (plain text)
     * @return bool Success
     */
    public function updatePassword(string $userId, string $newPassword): bool {
        // Hash the password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Update password and clear requires_password_change flag
        $updateData = [
            'pin' => $hashedPassword,
            'password' => $hashedPassword, // Also update password field if exists
            'requires_password_change' => false
        ];
        
        return $this->update($userId, $updateData);
    }
    
    /**
     * Find user by email
     * @param string $email Email address
     * @return array|null User data or null
     */
    public function findByEmail(string $email): ?array {
        $result = $this->repository->findByEmail($email);
        
        // Ensure we return array or null, never false or other types
        if ($result === false || (!is_array($result) && $result !== null)) {
            return null;
        }
        
        return $result;
    }
    
    /**
     * Find user by user_id
     * @param string $userId User ID
     * @return array|null User data or null
     */
    public function findByUserId(string $userId): ?array {
        $result = $this->repository->findByUserId($userId);
        
        // Ensure we return array or null, never false or other types
        if ($result === false || (!is_array($result) && $result !== null)) {
            error_log("UserService::findByUserId - Invalid result type: " . gettype($result) . " for userId: " . $userId);
            return null;
        }
        
        return $result;
    }
    
    /**
     * Get staff by role
     * @param string $role Role name
     * @return array Staff members
     */
    public function getStaffByRole(string $role): array {
        return $this->repository->getStaffByRole($role);
    }
    
    /**
     * Get all users (with cache)
     * @return array All users
     */
    public function getAll(): array {
        $cache = \App\Core\DependencyFactory::getCacheService();
        $tenantId = \App\Core\TenantResolver::resolve();
        $cacheKey = $tenantId ? "users:all:{$tenantId}" : 'users:all';
        
        return $cache->remember($cacheKey, function() {
            return $this->repository->getAll();
        }, 120);
    }
    
    /**
     * Check if a PIN is already hashed
     * 
     * @param string $pin PIN to check
     * @return bool True if PIN is hashed, false otherwise
     */
    private function isHashed(string $pin): bool {
        // Hashed PINs are typically 60 characters long and start with $2y$, $2a$, or $2b$
        return strlen($pin) >= 60 && 
               (strpos($pin, '$2y$') === 0 || 
                strpos($pin, '$2a$') === 0 || 
                strpos($pin, '$2b$') === 0);
    }
}