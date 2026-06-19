<?php
namespace App\Services\POS\Devices;

/**
 * POS Device Interface
 * All POS devices must implement this interface
 * 
 * @package App\Services\POS\Devices
 */
interface POSDeviceInterface {
    /**
     * Process payment
     * @param array $paymentData Payment data
     * @return array Result
     */
    public function processPayment(array $paymentData): array;

    /**
     * Test connection
     * @return bool Success
     */
    public function testConnection(): bool;

    /**
     * Get device name
     * @return string Device name
     */
    public function getName(): string;

    /**
     * Get device type
     * @return string Device type
     */
    public function getType(): string;

    /**
     * Check if device is enabled
     * @return bool Is enabled
     */
    public function isEnabled(): bool;
}

