<?php
namespace App\Models;

require_once __DIR__ . '/../core/Model.php';

class User2FACode extends \App\Core\Model {
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
        
        return $this->query()
            ->insert([
                'user_id' => $userId,
                'code' => $code,
                'method' => $method,
                'expires_at' => $expiresAt,
                'used' => 0
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
        $codeRecord = $this->query()
            ->where('user_id', $userId)
            ->where('code', $code)
            ->where('method', $method)
            ->where('used', 0)
            ->whereRaw('expires_at > NOW()')
            ->orderBy('created_at', 'DESC')
            ->first();
        
        if (!$codeRecord) {
            return false;
        }
        
        // Mark code as used
        $this->query()
            ->where('code_id', $codeRecord['code_id'])
            ->update(['used' => 1]);
        
        return true;
    }

    /**
     * Clean up expired codes
     * @param int $daysOld Delete codes older than this many days
     * @return int Number of deleted codes
     */
    public function cleanupExpiredCodes(int $daysOld = 7): int {
        $deleted = $this->query()
            ->whereRaw('expires_at < DATE_SUB(NOW(), INTERVAL ? DAY)', [$daysOld])
            ->delete();
        
        return $deleted;
    }

    /**
     * Get the latest unused code for a user
     * @param string $userId
     * @param string $method
     * @return array|null
     */
    public function getLatestCode(string $userId, string $method): ?array {
        return $this->query()
            ->where('user_id', $userId)
            ->where('method', $method)
            ->where('used', 0)
            ->whereRaw('expires_at > NOW()')
            ->orderBy('created_at', 'DESC')
            ->first();
    }

    /**
     * Invalidate all codes for a user (security measure)
     * @param string $userId
     * @param string $method
     * @return bool
     */
    public function invalidateAllCodes(string $userId, string $method): bool {
        return $this->query()
            ->where('user_id', $userId)
            ->where('method', $method)
            ->where('used', 0)
            ->update(['used' => 1]);
    }
}

