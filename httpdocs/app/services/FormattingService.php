<?php
namespace App\Services;

/**
 * Formatting Service
 * Centralized service for formatting data (currency, dates, IDs, durations)
 * Replaces global helper functions with OOP approach
 */
class FormattingService {
    /**
     * Format currency amount
     * @param float|int|string $amount Amount to format
     * @return string Formatted currency string
     */
    public function formatCurrency($amount = 0): string {
        if ($amount === null || $amount === '' || (is_float($amount) && is_nan($amount))) {
            return '0 ₺';
        }
        
        try {
            return number_format((float)$amount, 2, ',', '.') . ' ₺';
        } catch (\Exception $e) {
            return $amount . ' ₺';
        }
    }
    
    /**
     * Format date/timestamp
     * @param int|string $timestamp Timestamp or date string
     * @return string Formatted date string
     */
    public function formatDate($timestamp): string {
        try {
            if (is_string($timestamp)) {
                $timestamp = strtotime($timestamp);
            }
            return date('d/m/Y H:i', $timestamp);
        } catch (\Exception $e) {
            return '-';
        }
    }
    
    /**
     * Generate unique ID
     * @param string $prefix Optional prefix
     * @return string Generated ID
     */
    public function generateId(string $prefix = ''): string {
        $id = bin2hex(random_bytes(4)) . bin2hex(random_bytes(4)); // 16 character hex ID
        return $prefix ? $prefix . $id : $id;
    }
    
    /**
     * Generate order ID based on settings
     * Format: {prefix}{number} (e.g., cd001, cd002, etc.)
     * @return string Generated order ID
     */
    public function generateOrderId(): string {
        try {
            $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
            
            // Get order ID prefix from settings (default: 'cd')
            $prefix = $settingsService->getSetting('order_id_prefix', 'cd');
            if (empty($prefix)) {
                $prefix = 'cd';
            }
            
            // Get last order number from database
            $orderRepo = \App\Core\DependencyFactory::getOrderRepository();
            
            // Get the highest order number with this prefix
            $lastOrder = $orderRepo->getLastOrderByPrefix($prefix);
            
            $nextNumber = 1;
            if ($lastOrder) {
                $lastOrderId = $lastOrder['order_id'] ?? '';
                // Extract number from last order ID (e.g., "cd001" -> 1, "cd999" -> 999, "cd1000" -> 1000)
                if (preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', $lastOrderId, $matches)) {
                    $nextNumber = (int)$matches[1] + 1;
                }
            }
            
            // No length restriction - just use the number as-is (cd999 -> cd1000, cd1000 -> cd1001, etc.)
            return $prefix . $nextNumber;
        } catch (\Exception $e) {
            // Fallback to default ID generation
            error_log("Order ID generation error: " . $e->getMessage());
            return $this->generateId('o');
        }
    }
    
    /**
     * Get duration string from start time
     * @param int|null $startTime Start timestamp
     * @return string Duration string
     */
    public function getDuration($startTime = null): string {
        if (!$startTime) {
            return '';
        }
        
        // Handle both timestamp (int) and date string
        $timestamp = is_numeric($startTime) ? intval($startTime) : strtotime($startTime);
        if ($timestamp === false || $timestamp <= 0) {
            return '';
        }
        
        $now = time();
        $diff = floor(($now - $timestamp) / 60);
        
        if ($diff < 0) {
            // Future date - show 0 dk
            return '0 dk';
        }
        
        if ($diff < 60) {
            return $diff . ' dk';
        }
        
        $h = floor($diff / 60);
        $m = $diff % 60;
        return $h . 's ' . $m . 'dk';
    }
}

