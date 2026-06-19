<?php
namespace App\Repositories;

use App\Core\BaseRepository;

class SavedPaymentMethodRepository extends BaseRepository {
    protected $table = 'saved_payment_methods';
    protected $primaryKey = 'saved_card_id';
    
    /**
     * Get saved payment methods by customer ID
     * @param string $customerId
     * @return array
     */
    public function getByCustomerId(string $customerId): array {
        $sql = "SELECT * FROM {$this->table} 
                WHERE customer_id = :customer_id 
                AND is_active = 1
                ORDER BY is_default DESC, created_at DESC";
        return $this->fetchAll($sql, ['customer_id' => $customerId]);
    }
    
    /**
     * Get default payment method for customer
     * @param string $customerId
     * @return array|null
     */
    public function getDefaultByCustomerId(string $customerId): ?array {
        $sql = "SELECT * FROM {$this->table} 
                WHERE customer_id = :customer_id 
                AND is_default = 1 
                AND is_active = 1
                LIMIT 1";
        return $this->fetchOne($sql, ['customer_id' => $customerId]);
    }
    
    /**
     * Set default payment method
     * @param string $savedCardId
     * @param string $customerId
     * @return bool
     */
    public function setDefault(string $savedCardId, string $customerId): bool {
        // First, unset all defaults for this customer
        $this->execute("UPDATE {$this->table} SET is_default = 0 WHERE customer_id = :customer_id", [
            'customer_id' => $customerId
        ]);
        
        // Then set this one as default
        return $this->update($savedCardId, ['is_default' => 1]);
    }
    
    /**
     * Deactivate payment method (soft delete)
     * @param string $savedCardId
     * @return bool
     */
    public function deactivate(string $savedCardId): bool {
        return $this->update($savedCardId, ['is_active' => 0]);
    }
}
