<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\PaymentGatewayRepository;
use App\Services\Payment\Gateways\PaymentGatewayInterface;

/**
 * Payment Gateway Service
 * Manages payment gateways and processes payments
 * 
 * @package App\Services
 */
class PaymentGatewayService extends BaseService {
    private $gateways = [];

    /**
     * Constructor
     * @param PaymentGatewayRepository $repository Payment gateway repository
     */
    public function __construct(PaymentGatewayRepository $repository) {
        parent::__construct($repository);
        $this->loadGateways();
    }

    /**
     * Load all payment gateways from database
     */
    private function loadGateways(): void {
        $gateways = $this->repository->getAll();

        foreach ($gateways as $gatewayData) {
            $code = $gatewayData['gateway_code'] ?? '';
            $code = strtolower($code);
            $config = [
                'api_key' => $gatewayData['api_key'] ?? '',
                'secret_key' => $gatewayData['secret_key'] ?? '',
                'merchant_id' => $gatewayData['merchant_id'] ?? '',
                'merchant_key' => $gatewayData['api_key'] ?? '',
                'merchant_salt' => $gatewayData['secret_key'] ?? '',
                'test_mode' => ($gatewayData['test_mode'] ?? 1) == 1,
                'is_enabled' => ($gatewayData['is_enabled'] ?? 0) == 1
            ];

            $gateway = $this->createGateway($code, $config);
            if ($gateway) {
                $this->gateways[$code] = $gateway;
            }
        }
    }

    /**
     * Create gateway instance dynamically using registry
     * @param string $code Gateway code
     * @param array $config Gateway config
     * @return PaymentGatewayInterface|null Gateway instance or null
     */
    private function createGateway(string $code, array $config): ?PaymentGatewayInterface {
        $gatewayClass = PaymentGatewayRegistry::getGatewayClass($code);
        
        if (!$gatewayClass || !class_exists($gatewayClass)) {
            \App\Core\Logger::warning("PaymentGatewayService: Gateway class not found for code: {$code}");
            return null;
        }
        
        if (!is_subclass_of($gatewayClass, PaymentGatewayInterface::class)) {
            \App\Core\Logger::error("PaymentGatewayService: Class {$gatewayClass} does not implement PaymentGatewayInterface");
            return null;
        }
        
        try {
            return new $gatewayClass($config);
        } catch (\Exception $e) {
            \App\Core\Logger::error("PaymentGatewayService: Failed to instantiate gateway {$code}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get enabled gateways
     * @return array Enabled gateways
     */
    public function getEnabledGateways(): array {
        return array_filter($this->gateways, function($gateway) {
            return $gateway->isEnabled();
        });
    }

    /**
     * Get gateway by code
     * @param string $code Gateway code
     * @return PaymentGatewayInterface|null Gateway or null
     */
    public function getGateway(string $code): ?PaymentGatewayInterface {
        return $this->gateways[strtolower($code)] ?? null;
    }

    /**
     * Process payment
     * @param string $gatewayCode Gateway code
     * @param array $paymentData Payment data
     * @return array Result
     */
    public function processPayment(string $gatewayCode, array $paymentData): array {
        $gateway = $this->getGateway($gatewayCode);
        
        if (!$gateway) {
            return [
                'success' => false,
                'error' => 'Gateway not found',
                'code' => 'GATEWAY_NOT_FOUND'
            ];
        }

        if (!$gateway->isEnabled()) {
            return [
                'success' => false,
                'error' => 'Gateway is not enabled',
                'code' => 'GATEWAY_DISABLED'
            ];
        }

        return $gateway->processPayment($paymentData);
    }

    /**
     * Reload gateways from database
     */
    public function reloadGateways(): void {
        $this->gateways = [];
        $this->loadGateways();
    }
}

