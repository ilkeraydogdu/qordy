<?php
namespace App\Services\Payment\Gateways;

/**
 * Payment Gateway Interface
 * All payment gateways must implement this interface
 * 
 * @package App\Services\Payment\Gateways
 */
interface PaymentGatewayInterface {
    /**
     * Process payment
     * @param array $paymentData Payment data (amount, order_id, customer info, etc.)
     * @return array Result with success, transaction_id, etc.
     */
    public function processPayment(array $paymentData): array;

    /**
     * Refund payment
     * @param array $refundData Refund data (transaction_id, amount, reason)
     * @return array Result
     */
    public function refundPayment(array $refundData): array;

    /**
     * Verify payment status
     * @param string $transactionId Transaction ID
     * @return array Payment status
     */
    public function verifyPayment(string $transactionId): array;

    /**
     * Get gateway name
     * @return string Gateway name
     */
    public function getName(): string;

    /**
     * Get gateway code
     * @return string Gateway code
     */
    public function getCode(): string;

    /**
     * Check if gateway is enabled
     * @return bool Is enabled
     */
    public function isEnabled(): bool;

    /**
     * Check if gateway is in test mode
     * @return bool Is test mode
     */
    public function isTestMode(): bool;

    /**
     * Handle payment callback from gateway
     * @param array $callbackData Callback data from gateway
     * @return array Result with success, transaction_id, status, etc.
     */
    public function handleCallback(array $callbackData): array;
}

