<?php
namespace App\Repositories;

use App\Core\BaseRepository;

class BusinessSettingsRepository extends BaseRepository {
    protected $table = 'business_settings';
    protected $primaryKey = 'setting_id';

    public function __construct($database) {
        parent::__construct($database);
    }

    /**
     * Get business settings by business ID
     * @param string $businessId Business ID
     * @return array|null Settings data or null if not found
     */
    public function getByBusinessId(string $businessId): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE tenant_id = :business_id LIMIT 1";
        $result = $this->fetchOne($sql, ['business_id' => $businessId]);
        return $result ?: null;
    }

    /**
     * Get a specific setting value for a business
     * @param string $businessId Business ID
     * @param string $settingKey Setting key (e.g., 'waiter_delete_requires_approval')
     * @param mixed $defaultValue Default value if setting not found
     * @return mixed Setting value or default
     */
    public function getSetting(string $businessId, string $settingKey, $defaultValue = null) {
        $settings = $this->getByBusinessId($businessId);
        if ($settings && isset($settings[$settingKey])) {
            return $settings[$settingKey];
        }
        return $defaultValue;
    }

    /**
     * Create or update business settings
     * @param string $businessId Business ID
     * @param array $data Settings data
     * @return bool Success
     */
    public function createOrUpdate(string $businessId, array $data): bool {
        // Check if settings exist
        $existing = $this->getByBusinessId($businessId);
        
        if ($existing) {
            // Update existing settings
            $settingId = $existing['setting_id'];
            $data['updated_at'] = date('Y-m-d H:i:s');
            return $this->update($settingId, $data);
        } else {
            // Create new settings
            require_once __DIR__ . '/../helpers/functions.php';
            if (!isset($data['setting_id'])) {
                $data['setting_id'] = generateId('bs');
            }
            $data['tenant_id'] = $businessId;
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = date('Y-m-d H:i:s');
            return $this->create($data) !== false;
        }
    }

    /**
     * Update a specific setting for a business
     * @param string $businessId Business ID
     * @param string $settingKey Setting key
     * @param mixed $value Setting value
     * @return bool Success
     */
    public function updateSetting(string $businessId, string $settingKey, $value): bool {
        $existing = $this->getByBusinessId($businessId);
        
        if ($existing) {
            // Update existing
            $data = [
                $settingKey => $value,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            return $this->update($existing['setting_id'], $data);
        } else {
            // Create new with this setting
            require_once __DIR__ . '/../helpers/functions.php';
            $data = [
                'setting_id' => generateId('bs'),
                'tenant_id' => $businessId,
                $settingKey => $value,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            return $this->create($data) !== false;
        }
    }

    /**
     * Get all business settings
     * @return array All business-specific settings
     */
    public function getAllBusinessSettings(): array {
        $sql = "SELECT * FROM {$this->table} ORDER BY created_at DESC";
        return $this->fetchAll($sql);
    }
}
