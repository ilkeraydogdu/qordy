<?php
namespace App\Core\Helpers;

/**
 * ID Generator Helper Class
 * Provides methods for generating unique IDs
 */
class IdGeneratorHelper {
    /**
     * Generate a unique ID
     * @param string $prefix Prefix for the ID (default: 'id')
     * @param int $length Length of random part (default: 13)
     * @return string Generated ID
     */
    public static function generateId(string $prefix = 'id', int $length = 13): string {
        $random = bin2hex(random_bytes(ceil($length / 2)));
        return $prefix . '_' . substr($random, 0, $length) . '_' . time();
    }
    
    /**
     * Generate a UUID v4
     * @return string UUID
     */
    public static function generateUUID(): string {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // Set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Set bits 6-7 to 10
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
    
    /**
     * Generate a short ID (alphanumeric)
     * @param int $length Length of ID (default: 8)
     * @return string Generated ID
     */
    public static function generateShortId(int $length = 8): string {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}

