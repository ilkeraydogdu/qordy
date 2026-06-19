<?php
namespace App\Repositories;

use App\Core\BaseRepository;

class ReceiptTemplateRepository extends BaseRepository {
    protected $table = 'receipt_templates';
    protected $primaryKey = 'template_id';
    
    public function __construct($database) {
        parent::__construct($database);
    }
    
    public function getDefault(?string $businessId = null) {
        if ($businessId !== null) {
            // First try to get business-specific default template
            $sql = "SELECT * FROM {$this->table} WHERE tenant_id = :business_id AND is_default = 1 LIMIT 1";
            $result = $this->fetchOne($sql, ['business_id' => $businessId]);
            if ($result) {
                return $result;
            }
        }
        
        // Fallback to system-wide default template
        $sql = "SELECT * FROM {$this->table} WHERE (tenant_id IS NULL OR tenant_id = '') AND is_default = 1 LIMIT 1";
        return $this->fetchOne($sql);
    }
    
    public function getByBusinessId(?string $businessId) {
        if ($businessId === null) {
            // Get system-wide templates
            $sql = "SELECT * FROM {$this->table} WHERE tenant_id IS NULL OR tenant_id = '' ORDER BY is_default DESC, created_at DESC";
        } else {
            // Get business-specific templates
            $sql = "SELECT * FROM {$this->table} WHERE tenant_id = :business_id ORDER BY is_default DESC, created_at DESC";
        }
        return $this->fetchAll($sql, $businessId !== null ? ['business_id' => $businessId] : []);
    }
    
    public function setAsDefault($templateId, ?string $businessId = null) {
        if ($businessId !== null) {
            // First unset all defaults for this business
            $sql = "UPDATE {$this->table} SET is_default = 0 WHERE tenant_id = :business_id AND is_default = 1";
            $this->db->prepare($sql)->execute(['business_id' => $businessId]);
        } else {
            // First unset all system-wide defaults
            $sql = "UPDATE {$this->table} SET is_default = 0 WHERE (tenant_id IS NULL OR tenant_id = '') AND is_default = 1";
            $this->db->exec($sql);
        }
        
        // Then set the new default
        $sql = "UPDATE {$this->table} SET is_default = 1 WHERE template_id = :template_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['template_id' => $templateId]);
    }
}

