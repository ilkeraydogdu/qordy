<?php
namespace App\Repositories;

use App\Core\BaseRepository;
use PDO;

/**
 * User2FA Repository
 * Handles database operations for user 2FA configuration
 * 
 * @package App\Repositories
 */
class User2FARepository extends BaseRepository {
    protected $table = 'user_2fa';
    protected $primaryKey = 'user_2fa_id';

    /**
     * Get 2FA configuration for a user
     * @param string $userId
     * @return array|null
     */
    public function getByUserId(string $userId): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE user_id = :user_id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Get 2FA configuration for a user by method
     * @param string $userId
     * @param string $method 'email' or 'sms'
     * @return array|null
     */
    public function getByUserAndMethod(string $userId, string $method): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE user_id = :user_id AND method = :method LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'method' => $method
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Check if 2FA is enabled for a user
     * @param string $userId
     * @param string $method 'email' or 'sms'
     * @return bool
     */
    public function isEnabled(string $userId, string $method): bool {
        $config = $this->getByUserAndMethod($userId, $method);
        return $config && ($config['is_enabled'] ?? false);
    }

    /**
     * Enable 2FA for a user
     * @param string $userId
     * @param string $method 'email' or 'sms'
     * @param string $secretCode Email address or phone number
     * @return bool
     */
    public function enable(string $userId, string $method, string $secretCode): bool {
        $existing = $this->getByUserAndMethod($userId, $method);
        
        if ($existing) {
            $sql = "UPDATE {$this->table} SET is_enabled = 1, secret_code = :secret_code, updated_at = NOW() 
                    WHERE user_2fa_id = :user_2fa_id";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'secret_code' => $secretCode,
                'user_2fa_id' => $existing['user_2fa_id']
            ]);
        } else {
            $sql = "INSERT INTO {$this->table} (user_id, method, is_enabled, secret_code, created_at, updated_at) 
                    VALUES (:user_id, :method, 1, :secret_code, NOW(), NOW())";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'user_id' => $userId,
                'method' => $method,
                'secret_code' => $secretCode
            ]);
        }
    }

    /**
     * Disable 2FA for a user
     * @param string $userId
     * @param string $method 'email' or 'sms'
     * @return bool
     */
    public function disable(string $userId, string $method): bool {
        $config = $this->getByUserAndMethod($userId, $method);
        if (!$config) {
            return false;
        }
        
        $sql = "UPDATE {$this->table} SET is_enabled = 0, updated_at = NOW() WHERE user_2fa_id = :user_2fa_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['user_2fa_id' => $config['user_2fa_id']]);
    }

    /**
     * Get all enabled 2FA methods for a user
     * @param string $userId
     * @return array
     */
    public function getEnabledMethods(string $userId): array {
        // Check if table exists first
        try {
            $sql = "SELECT method FROM {$this->table} WHERE user_id = :user_id AND is_enabled = 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            // Table doesn't exist, return empty array
            if (strpos($e->getMessage(), "doesn't exist") !== false) {
                return [];
            }
            throw $e;
        }
        
        $methods = [];
        foreach ($results as $result) {
            $methods[] = $result['method'];
        }
        
        return $methods;
    }
}

