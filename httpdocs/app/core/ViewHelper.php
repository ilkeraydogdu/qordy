<?php
namespace App\Core;

/**
 * View Helper Class
 * Provides utility methods for view rendering, especially XSS protection
 */
class ViewHelper {
    /**
     * Escape HTML to prevent XSS attacks
     * @param string|null $text Text to escape
     * @param int $flags htmlspecialchars flags
     * @return string Escaped text
     */
    public static function escape(?string $text, int $flags = ENT_QUOTES | ENT_HTML5, string $encoding = 'UTF-8'): string {
        if ($text === null) {
            return '';
        }
        return htmlspecialchars($text, $flags, $encoding);
    }
    
    /**
     * Escape HTML attribute value
     * @param string|null $text Text to escape
     * @return string Escaped text
     */
    public static function escapeAttr(?string $text): string {
        return self::escape($text, ENT_QUOTES | ENT_HTML5);
    }
    
    /**
     * Escape JavaScript string
     * @param string|null $text Text to escape
     * @return string Escaped text for use in JavaScript
     */
    public static function escapeJs(?string $text): string {
        if ($text === null) {
            return '';
        }
        return json_encode($text, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * Escape URL
     * @param string|null $url URL to escape
     * @return string Escaped URL
     */
    public static function escapeUrl(?string $url): string {
        if ($url === null) {
            return '';
        }
        return htmlspecialchars($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    /**
     * Sanitize output (alias for escape)
     * @param string|null $text Text to sanitize
     * @return string Sanitized text
     */
    public static function sanitize(?string $text): string {
        return self::escape($text);
    }
    
    /**
     * Format currency
     * @param float|int|string $amount Amount to format
     * @param string $currency Currency code (default: TRY)
     * @return string Formatted currency string
     */
    public static function formatCurrency($amount, string $currency = 'TRY'): string {
        $amount = floatval($amount);
        return number_format($amount, 2, ',', '.') . ' ₺';
    }
    
    /**
     * Format date
     * @param string|null $date Date string or timestamp
     * @param string $format Date format (default: d.m.Y H:i)
     * @return string Formatted date
     */
    public static function formatDate(?string $date, string $format = 'd.m.Y H:i'): string {
        if (empty($date)) {
            return '-';
        }
        try {
            $timestamp = is_numeric($date) ? intval($date) : strtotime($date);
            if ($timestamp === false) {
                return '-';
            }
            return date($format, $timestamp);
        } catch (\Exception $e) {
            return '-';
        }
    }
    
    /**
     * Truncate text
     * @param string|null $text Text to truncate
     * @param int $length Maximum length
     * @param string $suffix Suffix to append
     * @return string Truncated text
     */
    public static function truncate(?string $text, int $length = 100, string $suffix = '...'): string {
        if ($text === null) {
            return '';
        }
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        return mb_substr($text, 0, $length) . $suffix;
    }
}

