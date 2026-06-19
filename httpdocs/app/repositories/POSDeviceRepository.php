<?php
namespace App\Repositories;

use App\Core\BaseRepository;

/**
 * POS Device Repository
 * Handles database operations for POS devices
 * 
 * @package App\Repositories
 */
class POSDeviceRepository extends BaseRepository {
    protected $table = 'pos_devices';
    protected $primaryKey = 'device_id';

    /**
     * Constructor
     * @param \PDO $database Database connection
     */
    public function __construct($database) {
        parent::__construct($database);
    }

    /**
     * Get all devices
     * @return array All devices
     */
    public function getAll(): array {
        $sql = "SELECT * FROM {$this->table} ORDER BY device_name";
        return $this->fetchAll($sql);
    }

    /**
     * Get enabled devices only
     * @return array Enabled devices
     */
    public function getEnabled(): array {
        $sql = "SELECT * FROM {$this->table} WHERE is_enabled = 1 ORDER BY device_name";
        return $this->fetchAll($sql);
    }

    /**
     * Get devices by connection type
     * @param string $connectionType Connection type
     * @return array Devices
     */
    public function getByConnectionType(string $connectionType): array {
        $sql = "SELECT * FROM {$this->table} WHERE connection_type = :type AND is_enabled = 1 ORDER BY device_name";
        return $this->fetchAll($sql, ['type' => $connectionType]);
    }

    /**
     * Get device by ID
     * @param string $deviceId Device ID
     * @return array|null Device data or null
     */
    public function getById(string $deviceId): ?array {
        return $this->findById($deviceId);
    }

    /**
     * Update device status
     * @param string $deviceId Device ID
     * @param bool $enabled Enabled status
     * @return bool Success
     */
    public function updateStatus(string $deviceId, bool $enabled): bool {
        $sql = "UPDATE {$this->table} SET is_enabled = :enabled, updated_at = NOW() WHERE {$this->primaryKey} = :id";
        return $this->execute($sql, ['id' => $deviceId, 'enabled' => $enabled ? 1 : 0]);
    }
}

