<?php
namespace App\Services\POS\Devices;

require_once __DIR__ . '/POSDeviceInterface.php';

/**
 * API POS Device (Payment Provider API)
 */
class APIPOSDevice implements POSDeviceInterface {
    private $deviceId;
    private $deviceName;
    private $apiEndpoint;
    private $apiKey;
    private $enabled;

    public function __construct(array $config) {
        $this->deviceId = $config['device_id'] ?? '';
        $this->deviceName = $config['device_name'] ?? '';
        $this->apiEndpoint = $config['api_endpoint'] ?? '';
        $this->apiKey = $config['api_key'] ?? '';
        $this->enabled = $config['is_enabled'] ?? false;
    }

    public function processPayment(array $paymentData): array {
        if (!$this->enabled) {
            return ['success' => false, 'error' => 'Device is not enabled'];
        }

        // Make API call to POS provider
        $ch = curl_init($this->apiEndpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($paymentData),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            return [
                'success' => true,
                'transaction_id' => $data['transaction_id'] ?? 'api_' . uniqid(),
                'amount' => floatval($paymentData['amount'] ?? 0)
            ];
        }

        return [
            'success' => false,
            'error' => 'API request failed',
            'http_code' => $httpCode
        ];
    }

    public function testConnection(): bool {
        // Test API endpoint
        $ch = curl_init($this->apiEndpoint . '/health');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 200 || $httpCode === 404; // 404 means endpoint exists
    }

    public function getName(): string {
        return $this->deviceName;
    }

    public function getType(): string {
        return 'api';
    }

    public function isEnabled(): bool {
        return $this->enabled && !empty($this->apiEndpoint);
    }
}

