<?php
namespace App\Services\POS\Devices;

require_once __DIR__ . '/POSDeviceInterface.php';

/**
 * Network POS Device (TCP/IP)
 */
class NetworkPOSDevice implements POSDeviceInterface {
    private $deviceId;
    private $deviceName;
    private $host;
    private $port;
    private $enabled;

    public function __construct(array $config) {
        $this->deviceId = $config['device_id'] ?? '';
        $this->deviceName = $config['device_name'] ?? '';
        $this->host = $config['network_host'] ?? '';
        $this->port = $config['network_port'] ?? 9100;
        $this->enabled = $config['is_enabled'] ?? false;
    }

    public function processPayment(array $paymentData): array {
        if (!$this->enabled) {
            return ['success' => false, 'error' => 'Device is not enabled'];
        }

        // In production, use socket connection
        return [
            'success' => true,
            'transaction_id' => 'network_' . uniqid(),
            'amount' => floatval($paymentData['amount'] ?? 0)
        ];
    }

    public function testConnection(): bool {
        // Test network connection
        $socket = @fsockopen($this->host, $this->port, $errno, $errstr, 5);
        if ($socket) {
            fclose($socket);
            return true;
        }
        return false;
    }

    public function getName(): string {
        return $this->deviceName;
    }

    public function getType(): string {
        return 'network';
    }

    public function isEnabled(): bool {
        return $this->enabled;
    }
}

