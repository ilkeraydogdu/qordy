<?php
namespace App\Core\Helpers;

/**
 * Authentication Helper Class
 * Provides methods for authentication-related operations
 */
class AuthenticationHelper {
    /**
     * Hash PIN using password_hash
     * @param string $pin PIN to hash
     * @return string Hashed PIN
     */
    public static function hashPin(string $pin): string {
        return password_hash($pin, PASSWORD_DEFAULT);
    }
    
    /**
     * Verify PIN against stored hash
     * @param string $pin Plain text PIN to verify
     * @param string $storedPin Stored PIN hash
     * @return bool True if PIN matches, false otherwise
     */
    public static function verifyPin(string $pin, string $storedPin): bool {
        if (empty($pin) || empty($storedPin)) {
            return false;
        }
        
        // Check if stored PIN is already hashed
        if (strlen($storedPin) >= 60 && 
            (strpos($storedPin, '$2y$') === 0 || 
             strpos($storedPin, '$2a$') === 0 || 
             strpos($storedPin, '$2b$') === 0)) {
            // PIN is hashed, use password_verify
            return password_verify($pin, $storedPin);
        }
        
        // If not hashed, this is a security issue - do not verify
        return false;
    }
    
    /**
     * Generate a secure random token
     * @param int $length Token length (default: 32)
     * @return string Random token
     */
    public static function generateToken(int $length = 32): string {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Generate a secure random password
     * @param int $length Password length (default: 16)
     * @return string Random password
     */
    public static function generatePassword(int $length = 16): string {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}

