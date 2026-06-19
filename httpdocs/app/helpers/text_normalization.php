<?php
/**
 * Text Normalization Helper
 * Normalizes Turkish text for consistent translation lookups
 */

if (!function_exists('normalizeTextForTranslation')) {
    /**
     * Normalize text for translation lookup
     * - Converts to lowercase
     * - Normalizes Turkish characters (ı→i, ş→s, ğ→g, etc.)
     * - Removes extra whitespace
     * - Trims the result
     * 
     * @param string $text Text to normalize
     * @return string Normalized text
     */
    function normalizeTextForTranslation(string $text): string {
        if (empty($text)) {
            return '';
        }
        
        // Convert to lowercase
        $normalized = mb_strtolower($text, 'UTF-8');
        
        // Normalize Turkish characters to ASCII equivalents for lookup
        // This helps match variations like "MANTARLI TAVUK" and "mantarli tavuk"
        $turkishToAscii = [
            'ı' => 'i',
            'İ' => 'i',
            'ş' => 's',
            'Ş' => 's',
            'ğ' => 'g',
            'Ğ' => 'g',
            'ü' => 'u',
            'Ü' => 'u',
            'ö' => 'o',
            'Ö' => 'o',
            'ç' => 'c',
            'Ç' => 'c',
        ];
        
        $normalized = strtr($normalized, $turkishToAscii);
        
        // Remove extra whitespace and trim
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        $normalized = trim($normalized);
        
        return $normalized;
    }
}

if (!function_exists('normalizeTextArray')) {
    /**
     * Normalize an array of texts
     * 
     * @param array $texts Array of texts to normalize
     * @return array Normalized texts
     */
    function normalizeTextArray(array $texts): array {
        return array_map('normalizeTextForTranslation', $texts);
    }
}

