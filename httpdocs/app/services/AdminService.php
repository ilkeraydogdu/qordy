<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\AdminRepository;

class AdminService extends BaseService {
    protected $repository;
    
    public function __construct(AdminRepository $repository) {
        $this->repository = $repository;
    }
    
    /**
     * Authenticate admin by email and password
     * @param string $email
     * @param string $password
     * @return array|false Admin data on success, false on failure
     */
    public function authenticate(string $email, string $password): array|false {
        try {
            $admin = $this->repository->findByEmail($email);
            
            if (!$admin) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning("AdminService::authenticate - Admin not found", [
                        'email' => $email,
                        'searched_email' => strtolower(trim($email))
                    ]);
                }
                return false;
            }
            
            // Verify password
            if (!password_verify($password, $admin['password_hash'])) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning("AdminService::authenticate - Invalid password", [
                        'email' => $email,
                        'admin_id' => $admin['admin_id'] ?? null
                    ]);
                }
                return false;
            }
            
            // Remove password hash from returned data
            unset($admin['password_hash']);
            
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::info("AdminService::authenticate - Success", [
                    'email' => $email,
                    'admin_id' => $admin['admin_id'] ?? null
                ]);
            }
            
            return $admin;
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("AdminService::authenticate - Error", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'email' => $email
                ]);
            }
            return false;
        }
    }
    
    /**
     * Get admin by ID
     * @param string $adminId
     * @return array|null
     */
    public function getAdminById(string $adminId): ?array {
        try {
            $admin = $this->repository->findById($adminId);
            if ($admin) {
                unset($admin['password_hash']);
            }
            return $admin;
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("AdminService::getAdminById - Error", [
                    'error' => $e->getMessage(),
                    'admin_id' => $adminId
                ]);
            }
            return null;
        }
    }
    
    /**
     * Get admin by email
     * @param string $email
     * @return array|null
     */
    public function getAdminByEmail(string $email): ?array {
        try {
            $admin = $this->repository->findByEmail($email);
            if ($admin) {
                unset($admin['password_hash']);
            }
            return $admin;
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("AdminService::getAdminByEmail - Error", [
                    'error' => $e->getMessage(),
                    'email' => $email
                ]);
            }
            return null;
        }
    }
    
    /**
     * Create new admin
     * @param array $data
     * @return array
     */
    public function createAdmin(array $data): array {
        try {
            // Validate required fields
            if (empty($data['email'])) {
                return ['success' => false, 'error' => 'Email is required'];
            }
            
            if (empty($data['password'])) {
                return ['success' => false, 'error' => 'Password is required'];
            }
            
            // Check if email already exists
            $existing = $this->repository->findByEmail($data['email']);
            if ($existing) {
                return ['success' => false, 'error' => 'Email already exists'];
            }
            
            // Create admin
            $adminId = $this->repository->create($data);
            
            if ($adminId) {
                return ['success' => true, 'admin_id' => $adminId];
            } else {
                return ['success' => false, 'error' => 'Failed to create admin'];
            }
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("AdminService::createAdmin - Error", [
                    'error' => $e->getMessage()
                ]);
            }
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Update admin
     * @param string $adminId
     * @param array $data
     * @return array
     */
    public function updateAdmin(string $adminId, array $data): array {
        try {
            $result = $this->repository->update($adminId, $data);
            
            if ($result) {
                return ['success' => true];
            } else {
                return ['success' => false, 'error' => 'Failed to update admin'];
            }
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("AdminService::updateAdmin - Error", [
                    'error' => $e->getMessage(),
                    'admin_id' => $adminId
                ]);
            }
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
