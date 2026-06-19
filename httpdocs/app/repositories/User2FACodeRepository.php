<?php
namespace App\Repositories;

use App\Core\BaseRepository;
use PDO;

/**
 * User2FACode Repository
 * Handles database operations for 2FA verification codes
 * 
 * @package App\Repositories
 */
class User2FACodeRepository extends BaseRepository {
    protected $table = 'user_2fa_codes';
    protected $primaryKey = 'code_id';

    /**
     * Create a new verification code
     * @param string $userId
     * @param string $code 6-digit code
     * @param string $method 'email' or 'sms'
     * @param int $expiryMinutes Default 10 minutes
     * @return bool
     */
    public function createCode(string $userId, string $code, string $method, int $expiryMinutes = 10): bool {
        $expiresAt = date('Y-m-d H:i:s', time() + ($expiryMinutes * 60));
        
        $sql = "INSERT INTO {$this->table} (user_id, code, method, expires_at, used, created_at) 
                VALUES (:user_id, :code, :method, :expires_at, 0, NOW())";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'user_id' => $userId,
            'code' => $code,
            'method' => $method,
            'expires_at' => $expiresAt
        ]);
    }

    /**
     * Verify a code
     * @param string $userId
     * @param string $code
     * @param string $method 'email' or 'sms'
     * @return bool
     */
    public function verifyCode(string $userId, string $code, string $method): bool {
        // First, find unused, non-expired code
        $sql = "SELECT code_id FROM {$this->table} 
                WHERE user_id = :user_id AND code = :code AND method = :method 
                AND used = 0 AND expires_at > NOW() 
                ORDER BY created_at DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'code' => $code,
            'method' => $method
        ]);
        $codeRecord = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$codeRecord) {
            return false;
        }
        
        // Mark code as used
        $updateSql = "UPDATE {$this->table} SET used = 1 WHERE code_id = :code_id";
        $updateStmt = $this->db->prepare($updateSql);
        $updateStmt->execute(['code_id' => $codeRecord['code_id']]);
        
        return true;
    }

    /**
     * Clean up expired codes
     * @param int $daysOld Delete codes older than this many days
     * @return int Number of deleted codes
     */
    public function cleanupExpiredCodes(int $daysOld = 7): int {
        $sql = "DELETE FROM {$this->table} WHERE expires_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['days' => $daysOld]);
        return $stmt->rowCount();
    }

    /**
     * Get the latest unused code for a user
     * @param string $userId
     * @param string $method
     * @return array|null
     */
    public function getLatestCode(string $userId, string $method): ?array {
        $sql = "SELECT * FROM {$this->table} 
                WHERE user_id = :user_id AND method = :method 
                AND used = 0 AND expires_at > NOW() 
                ORDER BY created_at DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $userId,
            'method' => $method
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Invalidate all codes for a user (security measure)
     * @param string $userId
     * @param string $method
     * @return bool
     */
    public function invalidateAllCodes(string $userId, string $method): bool {
        $sql = "UPDATE {$this->table} SET used = 1 
                WHERE user_id = :user_id AND method = :method AND used = 0";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            'user_id' => $userId,
            'method' => $method
        ]);
    }
}

