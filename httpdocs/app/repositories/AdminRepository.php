<?php
namespace App\Repositories;

use App\Core\BaseRepository;

class AdminRepository extends BaseRepository {
    protected $table = 'admins';
    protected $primaryKey = 'admin_id';
    
    /**
     * Find admin by email
     * @param string $email
     * @return array|null
     */
    public function findByEmail(string $email): ?array {
        try {
            // Case-insensitive email search
            $sql = "SELECT * FROM {$this->table} WHERE LOWER(email) = LOWER(?) LIMIT 1";
            $results = $this->fetchAll($sql, [$email]);
            return !empty($results) ? $results[0] : null;
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("AdminRepository::findByEmail - Error", [
                    'error' => $e->getMessage(),
                    'email' => $email
                ]);
            }
            return null;
        }
    }
    
    /**
     * Find admin by ID
     * @param string $adminId
     * @return array|null
     */
    public function findById(string $adminId): ?array {
        return parent::findById($adminId);
    }
    
    /**
     * Create new admin
     * @param array $data
     * @return string|false Admin ID on success, false on failure
     */
    public function create(array $data): string|false {
        try {
            if (!isset($data['admin_id'])) {
                require_once __DIR__ . '/../helpers/functions.php';
                $data['admin_id'] = generateId('adm');
            }
            
            if (!isset($data['password_hash']) && isset($data['password'])) {
                $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
                unset($data['password']);
            }
            
            return parent::create($data);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("AdminRepository::create - Error", [
                    'error' => $e->getMessage()
                ]);
            }
            return false;
        }
    }
    
    /**
     * Update admin
     * @param string $adminId
     * @param array $data
     * @return bool
     */
    public function update(string $adminId, array $data): bool {
        try {
            if (isset($data['password']) && !isset($data['password_hash'])) {
                $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
                unset($data['password']);
            }
            
            return parent::update($adminId, $data);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("AdminRepository::update - Error", [
                    'error' => $e->getMessage(),
                    'admin_id' => $adminId
                ]);
            }
            return false;
        }
    }
}
