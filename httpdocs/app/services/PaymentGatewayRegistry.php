<?php
namespace App\Services;

/**
 * Payment Gateway Registry
 * Gateway tanımlarını ve konfigürasyonlarını yönetir
 */
class PaymentGatewayRegistry {
    private static $config = null;
    private static $gatewayDefinitions = null;
    
    /**
     * Load gateway configuration
     */
    private static function loadConfig(): array {
        if (self::$config === null) {
            $configPath = __DIR__ . '/../config/payment_gateways.php';
            if (file_exists($configPath)) {
                self::$config = require $configPath;
            } else {
                self::$config = ['gateways' => []];
            }
        }
        return self::$config;
    }
    
    /**
     * Get all gateway definitions
     */
    public static function getGatewayDefinitions(): array {
        if (self::$gatewayDefinitions === null) {
            $config = self::loadConfig();
            self::$gatewayDefinitions = $config['gateways'] ?? [];
        }
        return self::$gatewayDefinitions;
    }
    
    /**
     * Get gateway definition by code
     */
    public static function getGatewayDefinition(string $code): ?array {
        $definitions = self::getGatewayDefinitions();
        return $definitions[strtolower($code)] ?? null;
    }
    
    /**
     * Get gateway class by code
     */
    public static function getGatewayClass(string $code): ?string {
        $definition = self::getGatewayDefinition($code);
        return $definition['class'] ?? null;
    }
    
    /**
     * Get gateway fields configuration
     */
    public static function getGatewayFields(string $code): array {
        $definition = self::getGatewayDefinition($code);
        return $definition['fields'] ?? [];
    }
    
    /**
     * Get all gateway definitions for seeding
     */
    public static function getSeedData(): array {
        $definitions = self::getGatewayDefinitions();
        $seedData = [];
        
        foreach ($definitions as $code => $definition) {
            $seedData[] = [
                'gateway_id' => $definition['gateway_id'],
                'gateway_code' => $definition['gateway_code'],
                'gateway_name' => $definition['gateway_name'],
                'display_name' => $definition['display_name'],
                'name' => $definition['gateway_name'], // Eski 'name' sütunu için uyumluluk
                'description' => $definition['description'],
                'is_enabled' => 0,
                'test_mode' => 1,
                'sort_order' => $definition['sort_order'],
                'api_key' => '',
                'secret_key' => '',
                'merchant_id' => null,
                'config_json' => json_encode([])
            ];
        }
        
        return $seedData;
    }
}
