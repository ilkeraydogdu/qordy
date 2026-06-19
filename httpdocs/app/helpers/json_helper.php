<?php
/**
 * JSON Helper Functions
 * 
 * Provides safe JSON encoding functions to prevent JavaScript syntax errors
 * when passing data from PHP to JavaScript
 */

if (!function_exists('safeJsonEncode')) {
    /**
     * Safely encode data to JSON with error handling and default fallback
     * 
     * @param mixed $data Data to encode
     * @param string|array $default Default value if encoding fails (default: '[]')
     * @return string JSON encoded string
     */
    function safeJsonEncode($data, $default = '[]') {
        // Null check
        if ($data === null) {
            return is_string($default) ? $default : json_encode($default);
        }
        
        // Encode with safe flags to prevent XSS and handle special characters
        $json = json_encode(
            $data, 
            JSON_UNESCAPED_UNICODE |  // Don't escape unicode characters (for Turkish etc.)
            JSON_HEX_TAG |            // Escape < and > to prevent </script> injection
            JSON_HEX_AMP |            // Escape & to prevent HTML entity issues
            JSON_HEX_APOS |           // Escape ' to prevent string breaking
            JSON_HEX_QUOT             // Escape " to prevent string breaking
        );
        
        // Error check
        if (json_last_error() !== JSON_ERROR_NONE || $json === false) {
            // Log the error for debugging
            $errorMsg = 'JSON encode error: ' . json_last_error_msg();
            if (function_exists('error_log')) {
                error_log($errorMsg);
            }
            
            // Return default value
            return is_string($default) ? $default : json_encode($default);
        }
        
        return $json;
    }
}

if (!function_exists('safeJsonEncodeForJs')) {
    /**
     * Safely encode data to JSON specifically for inline JavaScript
     * Automatically handles common default types
     * 
     * @param mixed $data Data to encode
     * @param string $type Type hint: 'array', 'object', 'string', 'number', 'boolean'
     * @return string JSON encoded string with appropriate default
     */
    function safeJsonEncodeForJs($data, $type = 'array') {
        $defaults = [
            'array' => '[]',
            'object' => '{}',
            'string' => '""',
            'number' => '0',
            'boolean' => 'false',
            'null' => 'null'
        ];
        
        $default = $defaults[$type] ?? '[]';
        return safeJsonEncode($data, $default);
    }
}

