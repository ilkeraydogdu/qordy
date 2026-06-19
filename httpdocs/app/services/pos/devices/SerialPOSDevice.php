<?php
namespace App\Services\POS\Devices;

require_once __DIR__ . '/POSDeviceInterface.php';

/**
 * Serial Port POS Device (ESC/POS)
 */
class SerialPOSDevice implements POSDeviceInterface {
    private $deviceId;
    private $deviceName;
    private $serialPort;
    private $baudRate;
    private $enabled;

    public function __construct(array $config) {
        $this->deviceId = $config['device_id'] ?? '';
        $this->deviceName = $config['device_name'] ?? '';
        $this->serialPort = $config['serial_port'] ?? '';
        $this->baudRate = $config['baud_rate'] ?? 9600;
        $this->enabled = $config['is_enabled'] ?? false;
    }

    public function processPayment(array $paymentData): array {
        if (!$this->enabled) {
            return ['success' => false, 'error' => 'Device is not enabled'];
        }

        // In production, use serial port library (e.g., php-serial)
        // For now, simulate
        return [
            'success' => true,
            'transaction_id' => 'serial_' . uniqid(),
            'amount' => floatval($paymentData['amount'] ?? 0)
        ];
    }

    public function testConnection(): bool {
        // Test serial port connection
        // In production: check if port exists and is accessible
        return !empty($this->serialPort);
    }

    public function getName(): string {
        return $this->deviceName;
    }

    public function getType(): string {
        return 'serial';
    }

    public function isEnabled(): bool {
        return $this->enabled;
    }
}

