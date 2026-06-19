<?php
namespace App\Repositories;

use App\Core\BaseRepository;

/**
 * Feature Repository
 * Handles database operations for feature settings
 * 
 * @package App\Repositories
 */
class FeatureRepository extends BaseRepository {
    protected $table = 'feature_settings';
    protected $primaryKey = 'feature_key';

    /**
     * Constructor
     * @param \PDO $database Database connection
     */
    public function __construct($database) {
        parent::__construct($database);
    }

    /**
     * Get all features
     * @return array All features
     */
    public function getAll(): array {
        $sql = "SELECT * FROM {$this->table} ORDER BY feature_key";
        return $this->fetchAll($sql);
    }

    /**
     * Get enabled features only
     * @return array Enabled features
     */
    public function getEnabled(): array {
        $sql = "SELECT * FROM {$this->table} WHERE is_enabled = 1 ORDER BY feature_key";
        return $this->fetchAll($sql);
    }

    /**
     * Get feature by key
     * @param string $key Feature key
     * @return array|null Feature data or null
     */
    public function getByKey(string $key): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE feature_key = :key LIMIT 1";
        return $this->fetchOne($sql, ['key' => $key]);
    }

    /**
     * Check if feature is enabled
     * @param string $key Feature key
     * @return bool Is enabled
     */
    public function isEnabled(string $key): bool {
        $feature = $this->getByKey($key);
        $value = $feature['is_enabled'] ?? 0;
 return $feature && ((int)$value === 1 || $value === true);
    }

    /**
     * Update feature status
     * @param string $key Feature key
     * @param bool $enabled Enabled status
     * @return bool Success
     */
    public function updateStatus(string $key, bool $enabled): bool {
        $sql = "UPDATE {$this->table} SET is_enabled = :enabled, updated_at = NOW() WHERE feature_key = :key";
        return $this->execute($sql, ['key' => $key, 'enabled' => $enabled ? 1 : 0]);
    }

    /**
     * Update feature config
     * @param string $key Feature key
     * @param array $config Config array
     * @return bool Success
     */
    public function updateConfig(string $key, array $config): bool {
        // Note: config_json column doesn't exist in migration, using description field instead
        $description = $config['description'] ?? '';
        $sql = "UPDATE {$this->table} SET description = :description, updated_at = NOW() WHERE feature_key = :key";
        return $this->execute($sql, ['key' => $key, 'description' => $description]);
    }
}

