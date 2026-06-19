<?php
namespace App\Helpers;

/**
 * Encryption Helper
 * Provides encryption/decryption functionality for sensitive data like PINs
 * Uses AES-256-CBC encryption
 */
class EncryptionHelper {
    private static $method = 'AES-256-CBC';
    private static $key = null;
    
    /**
     * Get encryption key from environment or generate default
     * @return string
     */
    private static function getKey() {
        if (self::$key !== null) {
            return self::$key;
        }
        
        // Try to get from environment
        $envKey = getenv('ENCRYPTION_KEY');
        if ($envKey && !empty($envKey)) {
            self::$key = hash('sha256', $envKey, true);
            return self::$key;
        }
        
        // Try to get from config
        if (defined('ENCRYPTION_KEY')) {
            self::$key = hash('sha256', ENCRYPTION_KEY, true);
            return self::$key;
        }
        
        // Fallback to a derived key from BASE_URL (not ideal but works)
        $fallback = defined('BASE_URL') ? BASE_URL : 'qordy-default-key-2026';
        self::$key = hash('sha256', $fallback . '-pin-encryption', true);
        return self::$key;
    }
    
    /**
     * Encrypt data
     * @param string $data Plain text data to encrypt
     * @return string Base64 encoded encrypted data with IV
     */
    public static function encrypt($data) {
        if (empty($data)) {
            return $data;
        }
        
        try {
            $ivLength = openssl_cipher_iv_length(self::$method);
            $iv = openssl_random_pseudo_bytes($ivLength);
            
            $encrypted = openssl_encrypt(
                $data,
                self::$method,
                self::getKey(),
                OPENSSL_RAW_DATA,
                $iv
            );
            
            if ($encrypted === false) {
                throw new \Exception('Encryption failed');
            }
            
            // Combine encrypted data and IV, then base64 encode
            return base64_encode($encrypted . '::' . $iv);
        } catch (\Exception $e) {
            error_log('EncryptionHelper::encrypt error: ' . $e->getMessage());
            return $data; // Return original data if encryption fails
        }
    }
    
    /**
     * Decrypt data
     * @param string $data Base64 encoded encrypted data with IV
     * @return string|false Decrypted plain text or false on failure
     */
    public static function decrypt($data) {
        if (empty($data)) {
            return $data;
        }
        
        try {
            $decoded = base64_decode($data, true);
            if ($decoded === false) {
                // Not base64 encoded, might be plain text (backward compatibility)
                error_log('EncryptionHelper::decrypt - Not base64 encoded');
                return false;
            }
            
            $parts = explode('::', $decoded, 2);
            if (count($parts) !== 2) {
                // Invalid format, might be plain text
                error_log('EncryptionHelper::decrypt - Invalid format (no :: separator)');
                return false;
            }
            
            list($encrypted, $iv) = $parts;
            
            $decrypted = openssl_decrypt(
                $encrypted,
                self::$method,
                self::getKey(),
                OPENSSL_RAW_DATA,
                $iv
            );
            
            if ($decrypted === false) {
                $opensslError = openssl_error_string();
                error_log('EncryptionHelper::decrypt - openssl_decrypt failed: ' . ($opensslError ?: 'unknown error'));
                // Return false to indicate decryption failure (don't return original)
                return false;
            }
            
            error_log('EncryptionHelper::decrypt - Successfully decrypted data (length: ' . strlen($decrypted) . ')');
            return $decrypted;
        } catch (\Exception $e) {
            error_log('EncryptionHelper::decrypt error: ' . $e->getMessage());
            // Return false to indicate failure (don't return original data)
            return false;
        }
    }
    
    /**
     * Check if data appears to be encrypted
     * @param string $data Data to check
     * @return bool True if appears encrypted, false otherwise
     */
    public static function isEncrypted($data) {
        if (empty($data)) {
            return false;
        }
        
        // Check if it's base64 encoded and contains our separator
        $decoded = base64_decode($data, true);
        if ($decoded === false) {
            return false;
        }
        
        return strpos($decoded, '::') !== false;
    }
}
