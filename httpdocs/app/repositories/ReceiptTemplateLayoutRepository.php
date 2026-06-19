<?php
namespace App\Repositories;

use App\Core\BaseRepository;

/**
 * Receipt Template Layout Repository
 * Handles database operations for receipt template layouts
 */
class ReceiptTemplateLayoutRepository extends BaseRepository {
    protected $table = 'receipt_template_layouts';
    protected $primaryKey = 'layout_id';
    
    public function __construct($database) {
        parent::__construct($database);
    }
    
    /**
     * Override create to handle JSON fields
     */
    public function create(array $data) {
        // Ensure JSON fields are properly encoded
        if (isset($data['layout_data']) && is_array($data['layout_data'])) {
            $data['layout_data'] = json_encode($data['layout_data'], JSON_UNESCAPED_UNICODE);
        }
        if (isset($data['social_media']) && is_array($data['social_media'])) {
            $data['social_media'] = json_encode($data['social_media'], JSON_UNESCAPED_UNICODE);
        }
        if (isset($data['icon_positions']) && is_array($data['icon_positions'])) {
            $data['icon_positions'] = json_encode($data['icon_positions'], JSON_UNESCAPED_UNICODE);
        }
        
        // Generate layout_id if not provided
        if (!isset($data['layout_id']) || empty($data['layout_id'])) {
            require_once __DIR__ . '/../helpers/functions.php';
            $data['layout_id'] = generateId('lt');
        }
        
        return parent::create($data);
    }
    
    /**
     * Override update to handle JSON fields
     */
    public function update(string $id, array $data): bool {
        // Ensure JSON fields are properly encoded
        if (isset($data['layout_data']) && is_array($data['layout_data'])) {
            $data['layout_data'] = json_encode($data['layout_data'], JSON_UNESCAPED_UNICODE);
        }
        if (isset($data['social_media']) && is_array($data['social_media'])) {
            $data['social_media'] = json_encode($data['social_media'], JSON_UNESCAPED_UNICODE);
        }
        if (isset($data['icon_positions']) && is_array($data['icon_positions'])) {
            $data['icon_positions'] = json_encode($data['icon_positions'], JSON_UNESCAPED_UNICODE);
        }
        
        return parent::update($id, $data);
    }
    
    /**
     * Override findById to decode JSON fields
     */
    public function findById(string $id): ?array {
        $result = parent::findById($id);
        if ($result) {
            return $this->decodeJsonFields($result);
        }
        return null;
    }
    
    /**
     * Decode JSON fields in result array
     */
    private function decodeJsonFields(array $data): array {
        if (isset($data['layout_data']) && is_string($data['layout_data'])) {
            $data['layout_data'] = json_decode($data['layout_data'], true) ?? [];
        }
        if (isset($data['social_media']) && is_string($data['social_media'])) {
            $data['social_media'] = json_decode($data['social_media'], true) ?? [];
        }
        if (isset($data['icon_positions']) && is_string($data['icon_positions'])) {
            $data['icon_positions'] = json_decode($data['icon_positions'], true) ?? [];
        }
        return $data;
    }
    
    /**
     * Get layout by business ID
     * @param string|null $businessId Business ID (null for system-wide layouts)
     * @return array|null Layout data or null
     */
    public function getByBusinessId(?string $businessId): ?array {
        if ($businessId === null) {
            // Get system-wide default layout
            $sql = "SELECT * FROM {$this->table} WHERE tenant_id IS NULL AND is_default = 1 AND is_active = 1 LIMIT 1";
            $result = $this->fetchOne($sql);
        } else {
            // First try to get business-specific default layout
            $sql = "SELECT * FROM {$this->table} WHERE tenant_id = :business_id AND is_default = 1 AND is_active = 1 LIMIT 1";
            $result = $this->fetchOne($sql, ['business_id' => $businessId]);
            
            // Fallback to system-wide default if not found
            if (!$result) {
                $sql = "SELECT * FROM {$this->table} WHERE tenant_id IS NULL AND is_default = 1 AND is_active = 1 LIMIT 1";
                $result = $this->fetchOne($sql);
            }
        }
        
        if ($result) {
            return $this->decodeJsonFields($result);
        }
        
        return null;
    }
    
    /**
     * Get all layouts by business ID
     * @param string|null $businessId Business ID (null for system-wide layouts)
     * @return array Layouts
     */
    public function getAllByBusinessId(?string $businessId): array {
        if ($businessId === null) {
            // Get system-wide layouts
            $sql = "SELECT * FROM {$this->table} WHERE tenant_id IS NULL ORDER BY is_default DESC, created_at DESC";
            $results = $this->fetchAll($sql);
        } else {
            // Get business-specific layouts
            $sql = "SELECT * FROM {$this->table} WHERE tenant_id = :business_id ORDER BY is_default DESC, created_at DESC";
            $results = $this->fetchAll($sql, ['business_id' => $businessId]);
        }
        
        // Decode JSON fields for each result
        return array_map([$this, 'decodeJsonFields'], $results);
    }
    
    /**
     * Get layout by template ID
     * @param string $templateId Template ID
     * @return array|null Layout data or null
     */
    public function getByTemplateId(string $templateId): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE template_id = :template_id AND is_active = 1 LIMIT 1";
        $result = $this->fetchOne($sql, ['template_id' => $templateId]);
        
        if ($result) {
            return $this->decodeJsonFields($result);
        }
        
        return null;
    }
    
    /**
     * Set layout as default for business
     * @param string $layoutId Layout ID
     * @param string|null $businessId Business ID (null for system-wide)
     * @return bool Success
     */
    public function setAsDefault(string $layoutId, ?string $businessId = null): bool {
        // Start transaction
        $this->db->beginTransaction();
        
        try {
            // First, unset all defaults for this business
            if ($businessId !== null) {
                $sql = "UPDATE {$this->table} SET is_default = 0 WHERE tenant_id = :business_id AND is_default = 1";
                $stmt = $this->db->prepare($sql);
                $stmt->execute(['business_id' => $businessId]);
            } else {
                // Unset all system-wide defaults
                $sql = "UPDATE {$this->table} SET is_default = 0 WHERE tenant_id IS NULL AND is_default = 1";
                $this->db->exec($sql);
            }
            
            // Then set the new default - scope the update to the caller's
            // tenant so a crafted layout_id cannot promote another tenant's
            // layout. $businessId === null means system-wide defaults only.
            if ($businessId !== null) {
                $sql = "UPDATE {$this->table} SET is_default = 1 WHERE layout_id = :layout_id AND tenant_id = :business_id";
                $stmt = $this->db->prepare($sql);
                $result = $stmt->execute(['layout_id' => $layoutId, 'business_id' => $businessId]);
            } else {
                $sql = "UPDATE {$this->table} SET is_default = 1 WHERE layout_id = :layout_id AND tenant_id IS NULL";
                $stmt = $this->db->prepare($sql);
                $result = $stmt->execute(['layout_id' => $layoutId]);
            }
            
            if ($result) {
                $this->db->commit();
                return true;
            } else {
                $this->db->rollBack();
                return false;
            }
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("ReceiptTemplateLayoutRepository::setAsDefault error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get default layout (system-wide or business-specific)
     * @param string|null $businessId Business ID
     * @return array|null Default layout or null
     */
    public function getDefault(?string $businessId = null): ?array {
        return $this->getByBusinessId($businessId);
    }
}