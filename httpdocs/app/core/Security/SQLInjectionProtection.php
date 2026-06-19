<?php
namespace App\Core\Security;

/**
 * SQL Injection Protection
 * Provides utilities to detect and prevent SQL injection attacks
 */
class SQLInjectionProtection {
    // Common SQL injection patterns
    private static $dangerousPatterns = [
        // SQL keywords used in injection
        '/(\bUNION\b.*\bSELECT\b)/i',
        '/(\bSELECT\b.*\bFROM\b)/i',
        '/(\bINSERT\b.*\bINTO\b)/i',
        '/(\bUPDATE\b.*\bSET\b)/i',
        '/(\bDELETE\b.*\bFROM\b)/i',
        '/(\bDROP\b.*\bTABLE\b)/i',
        '/(\bALTER\b.*\bTABLE\b)/i',
        '/(\bCREATE\b.*\bTABLE\b)/i',
        '/(\bEXEC\b|\bEXECUTE\b)/i',
        '/(\bSCRIPT\b)/i',
        
        // Comment-based injection
        '/(--|\#|\/\*|\*\/)/',
        
        // Union-based injection
        '/(\bUNION\b.*\bALL\b)/i',
        
        // Time-based blind SQL injection
        '/(\bSLEEP\b|\bWAITFOR\b|\bDELAY\b)/i',
        
        // Boolean-based blind SQL injection
        '/(\bOR\b.*=.*\bOR\b|\bAND\b.*=.*\bAND\b)/i',
        '/(\bOR\b.*\d+.*=.*\d+)/i',
        '/(\bAND\b.*\d+.*=.*\d+)/i',
        
        // Stacked queries
        '/(;.*\b(SELECT|INSERT|UPDATE|DELETE|DROP|CREATE|ALTER)\b)/i',
        
        // Function-based injection
        '/(\bCONCAT\b|\bCHAR\b|\bASCII\b|\bSUBSTRING\b)/i',
    ];
    
    /**
     * Detect SQL injection attempt in a string
     * @param string $input Input string to check
     * @return bool True if SQL injection pattern detected
     */
    public static function detect(string $input): bool {
        if (!is_string($input) || empty($input)) {
            return false;
        }
        
        foreach (self::$dangerousPatterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Validate column name against whitelist
     * @param string $columnName Column name to validate
     * @param array $allowedColumns Whitelist of allowed column names
     * @return bool True if column is allowed
     */
    public static function validateColumnName(string $columnName, array $allowedColumns): bool {
        // Remove any table prefix (e.g., "table.column" -> "column")
        $columnName = preg_replace('/^[a-zA-Z0-9_]+\./', '', $columnName);
        
        // Check against whitelist
        return in_array($columnName, $allowedColumns, true);
    }
    
    /**
     * Sanitize column name (remove dangerous characters)
     * @param string $columnName Column name to sanitize
     * @return string Sanitized column name
     */
    public static function sanitizeColumnName(string $columnName): string {
        // Remove any table prefix
        $columnName = preg_replace('/^[a-zA-Z0-9_]+\./', '', $columnName);
        
        // Only allow alphanumeric characters and underscores
        $columnName = preg_replace('/[^a-zA-Z0-9_]/', '', $columnName);
        
        return $columnName;
    }
    
    /**
     * Validate table name
     * @param string $tableName Table name to validate
     * @param array $allowedTables Whitelist of allowed table names
     * @return bool True if table is allowed
     */
    public static function validateTableName(string $tableName, array $allowedTables): bool {
        return in_array($tableName, $allowedTables, true);
    }
    
    /**
     * Sanitize table name
     * @param string $tableName Table name to sanitize
     * @return string Sanitized table name
     */
    public static function sanitizeTableName(string $tableName): string {
        // Only allow alphanumeric characters and underscores
        return preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
    }
}

