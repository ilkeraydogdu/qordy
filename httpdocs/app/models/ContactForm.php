<?php
namespace App\Models;

require_once __DIR__ . '/../core/Model.php';

class ContactForm extends \App\Core\Model {
    protected $table = 'contact_forms';
    
    public function getAll(): array {
        try {
            // Check if table exists
            $checkTable = $this->db->query("SHOW TABLES LIKE '{$this->table}'");
            if ($checkTable->rowCount() === 0) {
                return [];
            }
            return $this->query()
                ->orderBy('created_at', 'DESC')
                ->get();
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("ContactForm::getAll - Error", [
                    'error' => $e->getMessage(),
                    'table' => $this->table
                ]);
            }
            return [];
        }
    }
    
    public function getById(string $contactId) {
        try {
            // Check if table exists
            $checkTable = $this->db->query("SHOW TABLES LIKE '{$this->table}'");
            if ($checkTable->rowCount() === 0) {
                return null;
            }
            return $this->query()
                ->where('contact_id', $contactId)
                ->first();
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("ContactForm::getById - Error", [
                    'error' => $e->getMessage(),
                    'contact_id' => $contactId
                ]);
            }
            return null;
        }
    }
    
    public function getByStatus(string $status): array {
        try {
            // Check if table exists
            $checkTable = $this->db->query("SHOW TABLES LIKE '{$this->table}'");
            if ($checkTable->rowCount() === 0) {
                return [];
            }
            return $this->query()
                ->where('status', $status)
                ->orderBy('created_at', 'DESC')
                ->get();
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("ContactForm::getByStatus - Error", [
                    'error' => $e->getMessage(),
                    'status' => $status
                ]);
            }
            return [];
        }
    }
    
    public function getNew(): array {
        return $this->getByStatus('new');
    }
    
    public function create(array $data): bool {
        // Generate unique ID
        if (!isset($data['contact_id'])) {
            $data['contact_id'] = uniqid('contact_', true);
        }
        
        // Insert and check if successful
        // For VARCHAR PRIMARY KEY, lastInsertId() may return empty string
        // but execute() success means insert was successful
        $result = $this->query()->insert($data);
        
        // If result is not false, insert was successful
        return $result !== false;
    }
    
    public function updateStatus(string $contactId, string $status, ?string $notes = null): bool {
        $data = ['status' => $status];
        
        if ($status === 'contacted') {
            $data['contacted_at'] = date('Y-m-d H:i:s');
        }
        
        if ($notes !== null) {
            $data['notes'] = $notes;
        }
        
        return $this->query()
            ->where('contact_id', $contactId)
            ->update($data);
    }
    
    public function getRecent(int $limit = 10): array {
        return $this->query()
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->get();
    }
    
    public function deleteById(string $contactId): bool {
        try {
            $result = $this->query()
                ->where('contact_id', $contactId)
                ->delete();
            
            return $result > 0;
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("ContactForm::deleteById - Error", [
                    'error' => $e->getMessage(),
                    'contact_id' => $contactId
                ]);
            }
            return false;
        }
    }
}
