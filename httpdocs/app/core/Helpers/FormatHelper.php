<?php
namespace App\Core\Helpers;

/**
 * Format Helper Class
 * Provides methods for formatting data (currency, dates, etc.)
 */
class FormatHelper {
    /**
     * Format currency
     * @param float|int|string $amount Amount to format
     * @param string $currency Currency code (default: TRY)
     * @param string $locale Locale (default: tr-TR)
     * @return string Formatted currency string
     */
    public static function formatCurrency($amount, string $currency = 'TRY', string $locale = 'tr-TR'): string {
        $amount = floatval($amount);
        if ($locale === 'tr-TR') {
            return number_format($amount, 2, ',', '.') . ' ₺';
        }
        // Use Intl if available
        if (class_exists('NumberFormatter')) {
            $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
            return $formatter->formatCurrency($amount, $currency);
        }
        return number_format($amount, 2, '.', ',') . ' ' . $currency;
    }
    
    /**
     * Format date
     * @param string|int|null $date Date string or timestamp
     * @param string $format Date format (default: d.m.Y H:i)
     * @return string Formatted date
     */
    public static function formatDate($date, string $format = 'd.m.Y H:i'): string {
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
     * Format date with locale
     * @param string|int|null $date Date string or timestamp
     * @param string $locale Locale (default: tr-TR)
     * @return string Formatted date
     */
    public static function formatDateLocale($date, string $locale = 'tr-TR'): string {
        if (empty($date)) {
            return '-';
        }
        try {
            $timestamp = is_numeric($date) ? intval($date) : strtotime($date);
            if ($timestamp === false) {
                return '-';
            }
            if (class_exists('IntlDateFormatter')) {
                $formatter = new \IntlDateFormatter($locale, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT);
                return $formatter->format($timestamp);
            }
            return date('d.m.Y H:i', $timestamp);
        } catch (\Exception $e) {
            return '-';
        }
    }
}

