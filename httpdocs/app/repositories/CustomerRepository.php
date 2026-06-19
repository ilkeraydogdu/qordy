<?php
namespace App\Repositories;

use App\Core\BaseRepository;

class CustomerRepository extends BaseRepository {
    protected $table = 'customers';
    protected $primaryKey = 'customer_id';
    
    /**
     * Find customer by email
     * @param string $email
     * @return array|null
     */
    public function findByEmail(string $email): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE email = :email LIMIT 1";
        return $this->fetchOne($sql, ['email' => $email]);
    }
    
    /**
     * Find customer by ID
     * @param string $customerId
     * @return array|null
     */
    public function findById(string $customerId): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1";
        return $this->fetchOne($sql, ['id' => $customerId]);
    }
    
    /**
     * Find customer by email verification token
     * @param string $token
     * @return array|null
     */
    public function findByToken(string $token): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE email_verification_token = :token LIMIT 1";
        return $this->fetchOne($sql, ['token' => $token]);
    }
    
    /**
     * Update last login time
     * @param string $customerId
     * @return bool
     */
    public function updateLastLogin(string $customerId): bool {
        $sql = "UPDATE {$this->table} SET last_login_at = NOW() WHERE {$this->primaryKey} = :id";
        return $this->execute($sql, ['id' => $customerId]);
    }
    
    /**
     * Check if email exists
     * @param string $email
     * @param string|null $excludeCustomerId Exclude this customer ID from check
     * @return bool
     */
    public function emailExists(string $email, ?string $excludeCustomerId = null): bool {
        $sql = "SELECT 1 FROM {$this->table} WHERE email = :email";
        $params = ['email' => $email];
        
        if ($excludeCustomerId) {
            $sql .= " AND {$this->primaryKey} != :exclude_id";
            $params['exclude_id'] = $excludeCustomerId;
        }
        
        $sql .= " LIMIT 1";
        
        $result = $this->fetchOne($sql, $params);
        return $result !== null;
    }
    
    /**
     * Find customer by subdomain
     * @param string $subdomain
     * @return array|null
     */
    public function findBySubdomain(string $subdomain): ?array {
        try {
            // Check if subdomain column exists
            $hasSubdomain = $this->hasColumn('subdomain');
            $hasCustomDomain = $this->hasColumn('custom_domain');
            
            if ($hasSubdomain) {
                // First try to find by subdomain column
                $sql = "SELECT * FROM {$this->table} WHERE subdomain = :subdomain AND is_active = 1 LIMIT 1";
                $result = $this->fetchOne($sql, ['subdomain' => $subdomain]);
                if ($result) {
                    return $result;
                }
            }
            
            // Also check custom_domain if column exists
            if ($hasCustomDomain) {
                $baseDomain = $_ENV['BASE_DOMAIN'] ?? 'qordy.com';
                $fullDomain = $subdomain . '.' . $baseDomain;
                $sql = "SELECT * FROM {$this->table} WHERE custom_domain = :domain AND is_active = 1 LIMIT 1";
                $result = $this->fetchOne($sql, ['domain' => $fullDomain]);
                if ($result) {
                    return $result;
                }
            }
            
            return null;
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('CustomerRepository::findBySubdomain - Error', [
                    'error' => $e->getMessage(),
                    'subdomain' => $subdomain,
                    'trace' => $e->getTraceAsString()
                ]);
            }
            return null;
        }
    }
    
    /**
     * Find customer by custom domain
     * @param string $domain Full domain (e.g., 'muglanakliyat.com.tr')
     * @return array|null
     */
    public function findByCustomDomain(string $domain): ?array {
        try {
            // Check if custom_domain column exists
            if (!$this->hasColumn('custom_domain')) {
                return null;
            }
            
            $sql = "SELECT * FROM {$this->table} WHERE custom_domain = :domain AND is_active = 1 LIMIT 1";
            $result = $this->fetchOne($sql, ['domain' => $domain]);
            
            return $result ?: null;
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('CustomerRepository::findByCustomDomain - Error', [
                    'error' => $e->getMessage(),
                    'domain' => $domain,
                    'trace' => $e->getTraceAsString()
                ]);
            }
            return null;
        }
    }
    
    /**
     * Get all customers
     * @return array All customers
     */
    public function getAll(): array {
        $sql = "SELECT * FROM {$this->table} ORDER BY created_at DESC";
        return $this->fetchAll($sql);
    }

    /**
     * Whether this customer is the read-only demo tenant.
     */
    public function isDemoCustomer(string $customerId): bool {
        if ($customerId === '') {
            return false;
        }
        if (!$this->hasColumn('is_demo')) {
            return false;
        }
        $sql = "SELECT is_demo FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1";
        $row = $this->fetchOne($sql, ['id' => $customerId]);
        return $row && !empty($row['is_demo']);
    }
}
