<?php
namespace App\Models;

require_once __DIR__ . '/../core/Model.php';

class User extends \App\Core\Model {
    protected $table = 'users';

    public function findByUserId(string $userId) {
        return $this->query()
            ->where('user_id', $userId)
            ->first();
    }

    /**
     * Find user by PIN (supports both hashed and plain text PINs)
     * CRITICAL: Filters by tenant context for multi-tenant isolation
     */
    public function findByPin(string $pin) {
        // Get tenant context for filtering
        $tenantId = null;
        if (class_exists('\App\Core\TenantContext')) {
            $tenantId = \App\Core\TenantContext::getId();
        }
        
        // Build query with tenant filtering
        $query = $this->query();
        
        // If tenant context is set, filter by business_id
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        
        // Get users filtered by tenant
        $users = $query->get();
        
        // If no users found with tenant filter, try without filter (for main domain SuperAdmin)
        if (empty($users) && $tenantId) {
            // Log for debugging
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::debug('findByPin: No users found with tenant filter, trying without filter', [
                    'tenant_id' => $tenantId,
                    'pin_length' => strlen($pin)
                ]);
            }
        }

        foreach ($users as $user) {
            if (!isset($user['pin']) || empty($user['pin'])) {
                continue;
            }
            
            $storedPin = $user['pin'];
            

            // Check if stored PIN is hashed (starts with $2y$, $2a$, or $2b$ and is 60+ chars)
            $isHashed = strlen($storedPin) >= 60 && 
                       (strpos($storedPin, '$2y$') === 0 || 
                        strpos($storedPin, '$2a$') === 0 || 
                        strpos($storedPin, '$2b$') === 0);
                        
            // Check if stored PIN is encrypted (base64 with :: separator)
            $isEncrypted = false;
            if (class_exists('\App\Helpers\EncryptionHelper')) {
                $isEncrypted = \App\Helpers\EncryptionHelper::isEncrypted($storedPin);
            }
            
            $pinMatches = false;
            
            if ($isHashed) {
                $pinMatches = password_verify($pin, $storedPin);
            } elseif ($isEncrypted) {
                $decryptedPin = \App\Helpers\EncryptionHelper::decrypt($storedPin);
                $pinMatches = ($pin === $decryptedPin);
            } else {
                $pinMatches = ($pin === $storedPin);
            }
            
            if ($pinMatches) {
                // CRITICAL: Double-check tenant isolation before returning
                if ($tenantId && (!isset($user['tenant_id']) || $user['tenant_id'] !== $tenantId)) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::warning('findByPin: User found but business_id mismatch', [
                            'user_id' => $user['user_id'] ?? 'unknown',
                            'user_business_id' => $user['tenant_id'] ?? null,
                            'tenant_id' => $tenantId
                        ]);
                    }
                    continue; // Skip this user, try next
                }
                return $user;
            }
        }

        return null;
    }

    public function getAll(): array {
        return $this->query()
            ->orderBy('name')
            ->get();
    }

    public function create(array $data) {
        // Store PIN as plain text (for admin panel display)
        // PINs are no longer hashed by default
        return $this->query()
            ->insert($data);
    }

    public function updateUser(string $userId, array $data) {
        // Store PIN as plain text (for admin panel display)
        // PINs are no longer hashed by default
        return $this->query()
            ->where('user_id', $userId)
            ->update($data);
    }

    public function deleteUser(string $userId) {
        return $this->query()
            ->where('user_id', $userId)
            ->delete();
    }

    public function getStaffByRole(string $role): array {
        return $this->query()
            ->where('role', $role)
            ->orderBy('name')
            ->get();
    }

    /**
     * Update user PIN (stored as plain text)
     */
    public function updatePin(string $userId, string $newPin) {
        return $this->query()
            ->where('user_id', $userId)
            ->update(['pin' => $newPin]);
    }

    /**
     * Verify user PIN (supports both hashed and plain text)
     */
    public function verifyPin(string $userId, string $pin): bool {
        $user = $this->findByUserId($userId);
        if (!$user || !isset($user['pin'])) {
            return false;
        }

        $storedPin = $user['pin'];
        
        // Check if stored PIN is hashed
        $isHashed = strlen($storedPin) >= 60 && 
                   (strpos($storedPin, '$2y$') === 0 || 
                    strpos($storedPin, '$2a$') === 0 || 
                    strpos($storedPin, '$2b$') === 0);
        
        // Check if stored PIN is encrypted
        $isEncrypted = false;
        if (class_exists('\App\Helpers\EncryptionHelper')) {
            $isEncrypted = \App\Helpers\EncryptionHelper::isEncrypted($storedPin);
        }
        
        if ($isHashed) {
            return password_verify($pin, $storedPin);
        } elseif ($isEncrypted) {
            $decryptedPin = \App\Helpers\EncryptionHelper::decrypt($storedPin);
            return $pin === $decryptedPin;
        } else {
            return $pin === $storedPin;
        }
    }
}
