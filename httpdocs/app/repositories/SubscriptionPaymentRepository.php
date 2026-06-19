<?php
namespace App\Repositories;

use App\Core\BaseRepository;

class SubscriptionPaymentRepository extends BaseRepository {
    protected $table = 'subscription_payments';
    protected $primaryKey = 'payment_id';
    
    /**
     * Get payments by subscription ID
     * @param string $subscriptionId
     * @return array
     */
    public function getPaymentsBySubscription(string $subscriptionId): array {
        $sql = "SELECT * FROM {$this->table} 
                WHERE subscription_id = :subscription_id 
                ORDER BY created_at DESC";
        return $this->fetchAll($sql, ['subscription_id' => $subscriptionId]);
    }
    
    /**
     * Get payments by subscription ID (alias for getPaymentsBySubscription)
     * @param string $subscriptionId
     * @return array
     */
    public function getBySubscriptionId(string $subscriptionId): array {
        return $this->getPaymentsBySubscription($subscriptionId);
    }
    
    /**
     * Get payment by gateway transaction ID
     * @param string $transactionId
     * @return array|null
     */
    public function getByGatewayTransactionId(string $transactionId): ?array {
        $sql = "SELECT * FROM {$this->table} 
                WHERE gateway_transaction_id = :transaction_id 
                LIMIT 1";
        return $this->fetchOne($sql, ['transaction_id' => $transactionId]);
    }
    
    /**
     * Get payment by merchant OID
     * @param string $merchantOid
     * @return array|null
     */
    public function getByMerchantOid(string $merchantOid): ?array {
        $sql = "SELECT * FROM {$this->table} 
                WHERE merchant_oid = :merchant_oid 
                LIMIT 1";
        return $this->fetchOne($sql, ['merchant_oid' => $merchantOid]);
    }
    
    /**
     * Get payments by customer ID
     * @param string $customerId Customer ID
     * @return array Payments
     */
    public function getByCustomerId(string $customerId): array {
        $sql = "SELECT sp.*, s.customer_id, s.package_id, p.name as package_name
                FROM {$this->table} sp
                INNER JOIN subscriptions s ON sp.subscription_id = s.subscription_id
                LEFT JOIN packages p ON s.package_id = p.package_id
                WHERE s.customer_id = :customer_id
                ORDER BY sp.created_at DESC";
        return $this->fetchAll($sql, ['customer_id' => $customerId]);
    }
    
    /**
     * Update payment
     * @param string $paymentId
     * @param array $data
     * @return bool
     */
    public function update(string $paymentId, array $data): bool {
        return parent::update($paymentId, $data);
    }
}
