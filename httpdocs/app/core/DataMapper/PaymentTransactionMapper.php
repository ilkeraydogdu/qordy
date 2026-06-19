<?php
namespace App\Core\DataMapper;

/**
 * Payment Transaction Data Mapper
 * Single source of truth for payment_transactions table field filtering and mapping
 * Prevents code duplication and ensures consistency across repositories, models, and query builders
 */
class PaymentTransactionMapper {
    /**
     * Fields that should be excluded from payment_transactions table
     * These fields might come from other services or session data but don't exist in the table
     */
    private static $excludedFields = [
        'business_id',
        'status',
        'session_id',
        'businessId',
        'sessionId',
        'order_id',
        'payment_method',  // Will be mapped to 'method'
        'external_reference',  // Will be mapped to 'external_transaction_id'
    ];
    
    /**
     * Whitelist of allowed fields for payment_transactions table
     * Only these fields are allowed to be inserted/updated
     */
    private static $allowedFields = [
        'transaction_id',
        'gateway_id',
        'external_transaction_id',
        'gateway_response',
        'table_id',
        'amount',
        'method',
        'tip',
        'service_charge',
        'timestamp',
        'processed_by',
        'shift_id',
        'source',
        'created_at'
    ];
    
    /**
     * Field mappings - maps external field names to database field names
     */
    private static $fieldMappings = [
        'payment_method' => 'method',
        'external_reference' => 'external_transaction_id',
    ];
    
    /**
     * Filter and map payment transaction data
     * Removes excluded fields and applies field mappings
     * 
     * @param array $data Raw payment transaction data
     * @return array Filtered and mapped data ready for database insertion
     */
    public static function filterAndMap(array $data): array {
        $filtered = [];
        
        foreach ($data as $key => $value) {
            // Skip excluded fields
            if (in_array($key, self::$excludedFields, true)) {
                continue;
            }
            
            // Apply field mappings
            $mappedKey = self::$fieldMappings[$key] ?? $key;
            
            // Only include allowed fields
            if (in_array($mappedKey, self::$allowedFields, true)) {
                $filtered[$mappedKey] = $value;
            }
        }
        
        return $filtered;
    }
    
    /**
     * Get excluded fields list
     * @return array List of excluded field names
     */
    public static function getExcludedFields(): array {
        return self::$excludedFields;
    }
    
    /**
     * Get allowed fields list
     * @return array List of allowed field names
     */
    public static function getAllowedFields(): array {
        return self::$allowedFields;
    }
    
    /**
     * Check if a field is excluded
     * @param string $fieldName Field name to check
     * @return bool True if field is excluded
     */
    public static function isExcluded(string $fieldName): bool {
        return in_array($fieldName, self::$excludedFields, true);
    }
    
    /**
     * Check if a field is allowed
     * @param string $fieldName Field name to check (after mapping)
     * @return bool True if field is allowed
     */
    public static function isAllowed(string $fieldName): bool {
        return in_array($fieldName, self::$allowedFields, true);
    }
    
    /**
     * Get mapped field name
     * @param string $fieldName Original field name
     * @return string Mapped field name or original if no mapping exists
     */
    public static function getMappedField(string $fieldName): string {
        return self::$fieldMappings[$fieldName] ?? $fieldName;
    }
}
