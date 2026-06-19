<?php
namespace App\Validators;

/**
 * Printer Bridge Validator
 * Input validation for printer bridge endpoints
 */
class PrinterBridgeValidator {
    
    /**
     * Validate config code format (64 char hex)
     */
    public static function validateConfigCode(string $code): bool {
        return strlen($code) === 64 && ctype_xdigit($code);
    }
    
    /**
     * Validate and sanitize limit parameter
     */
    public static function validateLimit(int $limit): int {
        return max(1, min(100, $limit));
    }
    
    /**
     * Validate status whitelist
     */
    public static function validateStatus(string $status): bool {
        $allowedStatuses = ['PENDING', 'PRINTING', 'PRINTED', 'FAILED'];
        return in_array(strtoupper($status), $allowedStatuses);
    }
    
    /**
     * Validate bridge ID format (UUID)
     */
    public static function validateBridgeId(string $bridgeId): bool {
        $pattern = '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i';
        return preg_match($pattern, $bridgeId) === 1;
    }
    
    /**
     * Validate and sanitize device name
     */
    public static function sanitizeDeviceName(string $name): string {
        return htmlspecialchars(substr($name, 0, 200), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate and sanitize version string
     */
    public static function sanitizeVersion(string $version): string {
        return htmlspecialchars(substr($version, 0, 50), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate printer data array
     */
    public static function validatePrinterData(array $printer): bool {
        return isset($printer['id']) && isset($printer['name']);
    }
    
    /**
     * Sanitize printer name
     */
    public static function sanitizePrinterName(string $name): string {
        return htmlspecialchars(substr($name, 0, 200), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate printers array
     */
    public static function validatePrintersArray($printers): array {
        if (!is_array($printers)) {
            return ['valid' => false, 'error' => 'Printers must be an array'];
        }
        
        if (count($printers) > 50) {
            return ['valid' => false, 'error' => 'Too many printers (max 50)'];
        }
        
        return ['valid' => true];
    }
}
