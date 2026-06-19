<?php
namespace App\Repositories;

use App\Core\BaseRepository;

/**
 * Payment Gateway Repository
 * Handles database operations for payment gateways
 * 
 * @package App\Repositories
 */
class PaymentGatewayRepository extends BaseRepository {
    protected $table = 'payment_gateways';
    protected $primaryKey = 'gateway_id';

    /**
     * Constructor
     * @param \PDO $database Database connection
     */
    public function __construct($database) {
        parent::__construct($database);
    }

    /**
     * Get all gateways
     * @return array All gateways
     */
    public function getAll(): array {
        try {
            // Check if table exists
            $checkTable = $this->db->query("SHOW TABLES LIKE '{$this->table}'");
            if ($checkTable->rowCount() === 0) {
                return [];
            }
            
            $sql = "SELECT * FROM {$this->table} ORDER BY sort_order, gateway_name";
            return $this->fetchAll($sql);
        } catch (\Exception $e) {
            // Table doesn't exist or error occurred
            return [];
        }
    }

    /**
     * Get enabled gateways only
     * @return array Enabled gateways
     */
    public function getEnabled(): array {
        $sql = "SELECT * FROM {$this->table} WHERE is_enabled = 1 ORDER BY sort_order, gateway_name";
        return $this->fetchAll($sql);
    }

    /**
     * Get gateway by code
     * @param string $code Gateway code
     * @return array|null Gateway data or null
     */
    public function getByCode(string $code): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE gateway_code = :code LIMIT 1";
        return $this->fetchOne($sql, ['code' => $code]);
    }

    /**
     * Get gateway by ID
     * @param string $gatewayId Gateway ID
     * @return array|null Gateway data or null
     */
    public function getById(string $gatewayId): ?array {
        return $this->findById($gatewayId);
    }

    /**
     * Update gateway status
     * @param string $gatewayId Gateway ID
     * @param bool $enabled Enabled status
     * @return bool Success
     */
    public function updateStatus(string $gatewayId, bool $enabled): bool {
        $sql = "UPDATE {$this->table} SET is_enabled = :enabled, updated_at = NOW() WHERE {$this->primaryKey} = :id";
        return $this->execute($sql, ['id' => $gatewayId, 'enabled' => $enabled ? 1 : 0]);
    }

    /**
     * Update gateway config
     * @param string $gatewayId Gateway ID
     * @param array $config Config data
     * @return bool Success
     */
    public function updateConfig(string $gatewayId, array $config): bool {
        $data = [];
        
        // Only update fields that are provided
        if (isset($config['api_key'])) {
            $data['api_key'] = $config['api_key'];
        }
        if (isset($config['secret_key'])) {
            $data['secret_key'] = $config['secret_key'];
        }
        if (isset($config['merchant_id'])) {
            $data['merchant_id'] = $config['merchant_id'] ?: null;
        }
        if (isset($config['test_mode'])) {
            $testMode = (int)($config['test_mode'] ?? 0);
 $data['test_mode'] = ($testMode === 1 || $config['test_mode'] === true) ? 1 : 0;
        }
        if (isset($config['is_enabled'])) {
            $data['is_enabled'] = ($config['is_enabled'] == 1 || $config['is_enabled'] === true) ? 1 : 0;
        }
        if (isset($config['config_json'])) {
            $data['config_json'] = is_string($config['config_json']) ? $config['config_json'] : json_encode($config['config_json']);
        }
        
        if (empty($data)) {
            return false;
        }
        
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        return $this->update($gatewayId, $data);
    }
}

