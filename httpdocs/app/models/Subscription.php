<?php
namespace App\Models;

require_once __DIR__ . '/../core/Model.php';

class Subscription extends \App\Core\Model {
    protected $table = 'subscriptions';
    
    /**
     * Find subscription by ID
     * @param string $subscriptionId
     * @return array|null
     */
    public function findById($subscriptionId) {
        return $this->query()
            ->where('subscription_id', $subscriptionId)
            ->first();
    }
    
    /**
     * Get customer's active subscription
     * @param string $customerId
     * @return array|null
     */
    public function getActiveSubscription($customerId) {
        return $this->query()
            ->where('customer_id', $customerId)
            ->where('status', 'active')
            ->where(function($query) {
                $query->whereNull('end_date')
                      ->orWhere('end_date', '>', date('Y-m-d H:i:s'));
            })
            ->orderBy('created_at', 'DESC')
            ->first();
    }
    
    /**
     * Get all customer subscriptions
     * @param string $customerId
     * @return array
     */
    public function getCustomerSubscriptions($customerId) {
        return $this->query()
            ->where('customer_id', $customerId)
            ->orderBy('created_at', 'DESC')
            ->get();
    }
    
    /**
     * Get expired subscriptions
     * @return array
     */
    public function getExpiredSubscriptions() {
        return $this->query()
            ->where('status', 'active')
            ->whereNotNull('end_date')
            ->where('end_date', '<=', date('Y-m-d H:i:s'))
            ->get();
    }
    
    /**
     * Create new subscription
     * @param array $data
     * @return string|false Subscription ID on success, false on failure
     */
    public function create(array $data) {
        if (!isset($data['subscription_id'])) {
            require_once __DIR__ . '/../helpers/functions.php';
            $data['subscription_id'] = generateId('sub');
        }
        
        $result = $this->query()->insert($data);
        
        if ($result) {
            return $data['subscription_id'];
        }
        
        return false;
    }
    
    /**
     * Update subscription
     * @param string $subscriptionId
     * @param array $data
     * @return bool
     */
    public function updateSubscription($subscriptionId, array $data) {
        return $this->query()
            ->where('subscription_id', $subscriptionId)
            ->update($data);
    }
}
