<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\POSDeviceRepository;
use App\Services\POS\Devices\POSDeviceInterface;
use App\Services\POS\Devices\SerialPOSDevice;
use App\Services\POS\Devices\NetworkPOSDevice;
use App\Services\POS\Devices\APIPOSDevice;

/**
 * POS Device Service
 * Manages POS devices
 * 
 * @package App\Services
 */
class POSDeviceService extends BaseService {
    private $devices = [];

    /**
     * Constructor
     * @param POSDeviceRepository $repository POS device repository
     */
    public function __construct(POSDeviceRepository $repository) {
        parent::__construct($repository);
        $this->loadDevices();
    }

    /**
     * Load all POS devices from database
     */
    private function loadDevices(): void {
        $devices = $this->repository->getAll();

        foreach ($devices as $deviceData) {
            $connectionType = $deviceData['connection_type'] ?? 'serial';
            $config = array_merge($deviceData, [
                'is_enabled' => ($deviceData['is_enabled'] ?? 0) == 1
            ]);

            $device = $this->createDevice($connectionType, $config);
            if ($device) {
                $this->devices[$deviceData['device_id']] = $device;
            }
        }
    }

    /**
     * Create device instance
     * @param string $connectionType Connection type
     * @param array $config Device config
     * @return POSDeviceInterface|null Device instance or null
     */
    private function createDevice(string $connectionType, array $config): ?POSDeviceInterface {
        switch (strtolower($connectionType)) {
            case 'serial':
                return new SerialPOSDevice($config);
            case 'network':
                return new NetworkPOSDevice($config);
            case 'api':
                return new APIPOSDevice($config);
            default:
                return null;
        }
    }

    /**
     * Get enabled devices
     * @return array Enabled devices
     */
    public function getEnabledDevices(): array {
        return array_filter($this->devices, function($device) {
            return $device->isEnabled();
        });
    }

    /**
     * Get device by ID
     * @param string $deviceId Device ID
     * @return POSDeviceInterface|null Device or null
     */
    public function getDevice(string $deviceId): ?POSDeviceInterface {
        return $this->devices[$deviceId] ?? null;
    }

    /**
     * Process payment via POS device
     * @param string $deviceId Device ID
     * @param array $paymentData Payment data
     * @return array Result
     */
    public function processPayment(string $deviceId, array $paymentData): array {
        $device = $this->getDevice($deviceId);
        
        if (!$device) {
            return [
                'success' => false,
                'error' => 'Device not found',
                'code' => 'DEVICE_NOT_FOUND'
            ];
        }

        if (!$device->isEnabled()) {
            return [
                'success' => false,
                'error' => 'Device is not enabled',
                'code' => 'DEVICE_DISABLED'
            ];
        }

        return $device->processPayment($paymentData);
    }

    /**
     * Test device connection
     * @param string $deviceId Device ID
     * @return array Test result
     */
    public function testDevice(string $deviceId): array {
        $device = $this->getDevice($deviceId);
        
        if (!$device) {
            return [
                'success' => false,
                'error' => 'Device not found'
            ];
        }

        $connected = $device->testConnection();

        return [
            'success' => $connected,
            'connected' => $connected,
            'device_name' => $device->getName()
        ];
    }

    /**
     * Reload devices from database
     */
    public function reloadDevices(): void {
        $this->devices = [];
        $this->loadDevices();
    }
    
    /**
     * Add a new POS device to the database
     * @param array $deviceData Device data
     * @return string|false Device ID on success, false on failure
     */
    public function addDevice(array $deviceData) {
        require_once __DIR__ . '/../helpers/functions.php';
        
        // Generate device_id if not provided
        if (empty($deviceData['device_id'])) {
            $deviceData['device_id'] = generateId('pos');
        }
        
        // Set defaults
        if (!isset($deviceData['is_enabled'])) {
            $deviceData['is_enabled'] = 1;
        }
        
        if (!isset($deviceData['connection_type'])) {
            $deviceData['connection_type'] = 'serial';
        }
        
        // Add timestamps
        $deviceData['created_at'] = date('Y-m-d H:i:s');
        $deviceData['updated_at'] = date('Y-m-d H:i:s');
        
        $result = $this->repository->create($deviceData);
        
        if ($result) {
            // Reload devices to include the new one
            $this->reloadDevices();
            return $deviceData['device_id'];
        }
        
        return false;
    }
}

