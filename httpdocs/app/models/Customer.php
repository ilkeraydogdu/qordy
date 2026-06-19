<?php
namespace App\Models;

require_once __DIR__ . '/../core/Model.php';

class Customer extends \App\Core\Model {
    protected $table = 'customers';
    
    /**
     * Find customer by email
     * @param string $email
     * @return array|null
     */
    public function findByEmail($email) {
        return $this->query()
            ->where('email', $email)
            ->first();
    }
    
    /**
     * Find customer by ID
     * @param string $customerId
     * @return array|null
     */
    public function findById($customerId) {
        return $this->query()
            ->where('customer_id', $customerId)
            ->first();
    }
    
    /**
     * Find customer by email verification token
     * @param string $token
     * @return array|null
     */
    public function findByToken($token) {
        return $this->query()
            ->where('email_verification_token', $token)
            ->first();
    }
    
    /**
     * Create new customer
     * @param array $data
     * @return string|false Customer ID on success, false on failure
     */
    public function create(array $data) {
        // Password hash'leme
        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        // Email verification token oluştur
        $data['email_verification_token'] = bin2hex(random_bytes(32));
        $data['customer_id'] = 'CUST_' . uniqid();

        // Ensure is_active is set properly
        if (!isset($data['is_active'])) {
            $data['is_active'] = 1;
        }

        // Ensure tenant_database is set if provided
        if (!isset($data['tenant_database'])) {
            $data['tenant_database'] = null;
        }

        $result = $this->query()->insert($data);

        if ($result) {
            return $data['customer_id'];
        }

        return false;
    }
    
    /**
     * Verify email with token
     * @param string $token
     * @return bool
     */
    public function verifyEmail($token) {
        $customer = $this->findByToken($token);
        
        if ($customer) {
            $this->query()
                ->where('customer_id', $customer['customer_id'])
                ->update([
                    'email_verified' => true,
                    'status' => 'active',
                    'email_verification_token' => null
                ]);
            return true;
        }
        
        return false;
    }
    
    /**
     * Update last login time
     * @param string $customerId
     * @return bool
     */
    public function updateLastLogin($customerId) {
        return $this->query()
            ->where('customer_id', $customerId)
            ->update([
                'last_login_at' => date('Y-m-d H:i:s')
            ]);
    }
    
    /**
     * Update customer profile
     * @param string $customerId
     * @param array $data
     * @return bool
     */
    public function updateProfile($customerId, array $data) {
        // Email değişikliği varsa email_verified'i false yap
        if (isset($data['email'])) {
            $data['email_verified'] = false;
            $data['email_verification_token'] = bin2hex(random_bytes(32));
        }
        
        // Şifre değişikliği varsa hash'le
        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        
        return $this->query()
            ->where('customer_id', $customerId)
            ->update($data);
    }

    /**
     * Find customer by subdomain
     * @param string $subdomain
     * @return array|null
     */
    public function findBySubdomain(string $subdomain) {
        try {
            if (\App\Core\DbSchema::hasColumn($this->table, 'subdomain')) {
                return $this->query()
                    ->where('subdomain', $subdomain)
                    ->first();
            } else {
                // If 'subdomain' column doesn't exist, return null
                return null;
            }
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("Customer::findBySubdomain - Error", [
                    'error' => $e->getMessage(),
                    'subdomain' => $subdomain,
                    'table' => $this->table
                ]);
            }
            return null;
        }
    }
}
