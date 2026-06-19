<?php
namespace App\Models;

require_once __DIR__ . '/../core/Model.php';

class User2FA extends \App\Core\Model {
    protected $table = 'user_2fa';
    protected $primaryKey = 'user_2fa_id';

    /**
     * Get 2FA configuration for a user
     * @param string $userId
     * @return array|null
     */
    public function getByUserId(string $userId): ?array {
        return $this->query()
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * Get 2FA configuration for a user by method
     * @param string $userId
     * @param string $method 'email' or 'sms'
     * @return array|null
     */
    public function getByUserAndMethod(string $userId, string $method): ?array {
        return $this->query()
            ->where('user_id', $userId)
            ->where('method', $method)
            ->first();
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
            return $this->query()
                ->where('user_2fa_id', $existing['user_2fa_id'])
                ->update([
                    'is_enabled' => 1,
                    'secret_code' => $secretCode
                ]);
        } else {
            return $this->query()
                ->insert([
                    'user_id' => $userId,
                    'method' => $method,
                    'is_enabled' => 1,
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
        
        return $this->query()
            ->where('user_2fa_id', $config['user_2fa_id'])
            ->update([
                'is_enabled' => 0
            ]);
    }

    /**
     * Get all enabled 2FA methods for a user
     * @param string $userId
     * @return array
     */
    public function getEnabledMethods(string $userId): array {
        $configs = $this->query()
            ->where('user_id', $userId)
            ->where('is_enabled', 1)
            ->get();
        
        $methods = [];
        foreach ($configs as $config) {
            $methods[] = $config['method'];
        }
        
        return $methods;
    }
}

