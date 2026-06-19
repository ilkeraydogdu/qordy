<?php
namespace App\Repositories;

use App\Core\BaseRepository;

class SubscriptionRepository extends BaseRepository {
    protected $table = 'subscriptions';
    protected $primaryKey = 'subscription_id';

    /**
     * Column on subscriptions that stores the tenant id (links to customers.customer_id).
     * Canonical column is `tenant_id`; legacy schemas may still use business_id/customer_id.
     */
    private function subscriptionCustomerIdColumn(): string {
        if ($this->hasColumn('tenant_id')) {
            return 'tenant_id';
        }
        if ($this->hasColumn('business_id')) {
            return 'business_id';
        }
        if ($this->hasColumn('customer_id')) {
            return 'customer_id';
        }
        return 'tenant_id';
    }
    
    /**
     * Get customer's active subscription
     * @param string $customerId
     * @return array|null
     */
    public function getActiveSubscription(string $customerId): ?array {
        try {
            // Check if packages table exists
            $checkTable = $this->db->query("SHOW TABLES LIKE 'packages'");
            $packagesTableExists = $checkTable->rowCount() > 0;
            
            $idColumn = $this->subscriptionCustomerIdColumn();
            
            // Check which date columns exist
            $hasEndDate = $this->hasColumn('end_date');
            $hasCurrentPeriodEnd = $this->hasColumn('current_period_end');
            
            // Build date condition based on available columns
            $dateCondition = '';
            if ($hasCurrentPeriodEnd && $hasEndDate) {
                $dateCondition = "AND (s.current_period_end IS NULL OR s.current_period_end > NOW() OR s.end_date IS NULL OR s.end_date > NOW())";
            } elseif ($hasCurrentPeriodEnd) {
                $dateCondition = "AND (s.current_period_end IS NULL OR s.current_period_end > NOW())";
            } elseif ($hasEndDate) {
                $dateCondition = "AND (s.end_date IS NULL OR s.end_date > NOW())";
            }
            
            if ($packagesTableExists) {
                $sql = "SELECT s.*, p.name as package_name, p.description as package_description
                        FROM {$this->table} s
                        LEFT JOIN packages p ON s.package_id = p.package_id
                        WHERE s.{$idColumn} = :customer_id 
                        AND s.status = 'active'
                        {$dateCondition}
                        ORDER BY s.created_at DESC
                        LIMIT 1";
            } else {
                // If packages table doesn't exist, get subscription without package info
                $sql = "SELECT s.*
                        FROM {$this->table} s
                        WHERE s.{$idColumn} = :customer_id 
                        AND s.status = 'active'
                        {$dateCondition}
                        ORDER BY s.created_at DESC
                        LIMIT 1";
            }
            
            return $this->fetchOne($sql, ['customer_id' => $customerId]);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("SubscriptionRepository::getActiveSubscription - Error", [
                    'error' => $e->getMessage(),
                    'customer_id' => $customerId,
                    'trace' => $e->getTraceAsString()
                ]);
            }
            return null;
        }
    }

    /**
     * Ödeme bekleyen ücretli abonelik (deneme satırı tekilleştirildiğinde erişim için).
     * Çok eski pending kayıtları kasıtlı olarak hariç tutulur.
     */
    public function getPendingSubscriptionForAccess(string $customerId): ?array {
        try {
            $checkTable = $this->db->query("SHOW TABLES LIKE 'packages'");
            $packagesTableExists = $checkTable->rowCount() > 0;
            $idColumn = $this->subscriptionCustomerIdColumn();
            $recent = 'AND COALESCE(s.updated_at, s.created_at) >= DATE_SUB(NOW(), INTERVAL 14 DAY)';

            if ($packagesTableExists) {
                $sql = "SELECT s.*, p.name as package_name, p.description as package_description
                        FROM {$this->table} s
                        LEFT JOIN packages p ON s.package_id = p.package_id
                        WHERE s.{$idColumn} = :customer_id
                        AND s.status = 'pending'
                        AND COALESCE(s.amount, 0) > 0
                        {$recent}
                        ORDER BY COALESCE(s.updated_at, s.created_at) DESC
                        LIMIT 1";
            } else {
                $sql = "SELECT s.*
                        FROM {$this->table} s
                        WHERE s.{$idColumn} = :customer_id
                        AND s.status = 'pending'
                        AND COALESCE(s.amount, 0) > 0
                        {$recent}
                        ORDER BY COALESCE(s.updated_at, s.created_at) DESC
                        LIMIT 1";
            }

            return $this->fetchOne($sql, ['customer_id' => $customerId]);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('SubscriptionRepository::getPendingSubscriptionForAccess - Error', [
                    'error' => $e->getMessage(),
                    'customer_id' => $customerId,
                ]);
            }
            return null;
        }
    }
    
    /**
     * Get all customer subscriptions
     * @param string $customerId
     * @return array
     */
    public function getCustomerSubscriptions(string $customerId): array {
        try {
            // Check if packages table exists
            $checkTable = $this->db->query("SHOW TABLES LIKE 'packages'");
            $packagesTableExists = $checkTable->rowCount() > 0;
            
            $idColumn = $this->subscriptionCustomerIdColumn();
            
            if ($packagesTableExists) {
                $sql = "SELECT s.*, p.name as package_name, p.description as package_description
                        FROM {$this->table} s
                        LEFT JOIN packages p ON s.package_id = p.package_id
                        WHERE s.{$idColumn} = :customer_id
                        ORDER BY s.created_at DESC";
            } else {
                $sql = "SELECT s.*
                        FROM {$this->table} s
                        WHERE s.{$idColumn} = :customer_id
                        ORDER BY s.created_at DESC";
            }
            return $this->fetchAll($sql, ['customer_id' => $customerId]);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("SubscriptionRepository::getCustomerSubscriptions - Error", [
                    'error' => $e->getMessage(),
                    'customer_id' => $customerId
                ]);
            }
            return [];
        }
    }
    
    /**
     * Get expired subscriptions
     * @return array
     */
    public function getExpiredSubscriptions(): array {
        $sql = "SELECT * FROM {$this->table}
                WHERE status = 'active'
                AND current_period_end IS NOT NULL
                AND current_period_end <= NOW()";
        return $this->fetchAll($sql);
    }
    
    /**
     * Get subscription with package details
     * @param string $subscriptionId
     * @return array|null
     */
    public function getSubscriptionWithPackage(string $subscriptionId): ?array {
        try {
            // Check if packages table exists
            $checkTable = $this->db->query("SHOW TABLES LIKE 'packages'");
            $packagesTableExists = $checkTable->rowCount() > 0;
            
            if ($packagesTableExists) {
                $sql = "SELECT s.*, p.name as package_name, p.description as package_description,
                        p.price_one_time, p.price_monthly, p.price_yearly
                        FROM {$this->table} s
                        LEFT JOIN packages p ON s.package_id = p.package_id
                        WHERE s.subscription_id = :subscription_id
                        LIMIT 1";
            } else {
                $sql = "SELECT s.*
                        FROM {$this->table} s
                        WHERE s.subscription_id = :subscription_id
                        LIMIT 1";
            }
            return $this->fetchOne($sql, ['subscription_id' => $subscriptionId]);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("SubscriptionRepository::getSubscriptionWithPackage - Error", [
                    'error' => $e->getMessage(),
                    'subscription_id' => $subscriptionId
                ]);
            }
            return null;
        }
    }
    
    /**
     * Get all subscriptions with customer and package info
     * @return array
     */
    public function getAll(): array {
        try {
            // Check if packages table exists
            $checkTable = $this->db->query("SHOW TABLES LIKE 'packages'");
            $packagesTableExists = $checkTable->rowCount() > 0;
            
            $subIdCol = $this->subscriptionCustomerIdColumn();
            if ($packagesTableExists) {
                // last_payment_*: only completed charges (pending/failed must not look like "paid")
                // total_paid_completed: sum of completed payments for this subscription row
                $sql = "SELECT s.*, 
                        c.email as customer_email, c.first_name, c.last_name, c.company_name,
                        p.name as package_name, p.description as package_description,
                        (SELECT COALESCE(SUM(sp2.amount), 0) FROM subscription_payments sp2 
                            WHERE sp2.subscription_id = s.subscription_id AND sp2.payment_status = 'completed') as total_paid_completed,
                        (SELECT sp.amount FROM subscription_payments sp 
                            WHERE sp.subscription_id = s.subscription_id AND sp.payment_status = 'completed' 
                            ORDER BY COALESCE(sp.payment_date, sp.created_at) DESC LIMIT 1) as last_payment_amount,
                        (SELECT sp.payment_method FROM subscription_payments sp 
                            WHERE sp.subscription_id = s.subscription_id AND sp.payment_status = 'completed' 
                            ORDER BY COALESCE(sp.payment_date, sp.created_at) DESC LIMIT 1) as last_payment_method,
                        (SELECT sp.payment_date FROM subscription_payments sp 
                            WHERE sp.subscription_id = s.subscription_id AND sp.payment_status = 'completed' 
                            ORDER BY COALESCE(sp.payment_date, sp.created_at) DESC LIMIT 1) as last_payment_date
                        FROM {$this->table} s
                        LEFT JOIN customers c ON s.`{$subIdCol}` = c.customer_id
                        LEFT JOIN packages p ON s.package_id = p.package_id
                        ORDER BY s.created_at DESC";
            } else {
                $sql = "SELECT s.*, 
                        c.email as customer_email, c.first_name, c.last_name, c.company_name
                        FROM {$this->table} s
                        LEFT JOIN customers c ON s.`{$subIdCol}` = c.customer_id
                        ORDER BY s.created_at DESC";
            }
            return $this->fetchAll($sql);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("SubscriptionRepository::getAll - Error", [
                    'error' => $e->getMessage()
                ]);
            }
            return [];
        }
    }

    /**
     * Get the most recent subscription for a customer regardless of status
     * Used to determine detailed subscription state (trial expired, cancelled, etc.)
     * @param string $customerId
     * @return array|null
     */
    public function getLatestSubscription(string $customerId): ?array {
        try {
            $checkTable = $this->db->query("SHOW TABLES LIKE 'packages'");
            $packagesTableExists = $checkTable->rowCount() > 0;

            $idColumn = $this->subscriptionCustomerIdColumn();

            if ($packagesTableExists) {
                $sql = "SELECT s.*, p.name as package_name
                        FROM {$this->table} s
                        LEFT JOIN packages p ON s.package_id = p.package_id
                        WHERE s.{$idColumn} = :customer_id
                        ORDER BY s.created_at DESC
                        LIMIT 1";
            } else {
                $sql = "SELECT s.*
                        FROM {$this->table} s
                        WHERE s.{$idColumn} = :customer_id
                        ORDER BY s.created_at DESC
                        LIMIT 1";
            }

            return $this->fetchOne($sql, ['customer_id' => $customerId]);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getPaymentsForSubscription(string $subscriptionId): array {
        try {
            $sql = "SELECT * FROM subscription_payments WHERE subscription_id = :sid ORDER BY payment_date DESC, created_at DESC";
            return $this->fetchAll($sql, ['sid' => $subscriptionId]);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Sum of all completed subscription charges (actual collected revenue).
     */
    public function getTotalCompletedPaymentsAmount(): float {
        try {
            $checkTable = $this->db->query("SHOW TABLES LIKE 'subscription_payments'");
            if ($checkTable->rowCount() === 0) {
                return 0.0;
            }
            $row = $this->fetchOne(
                "SELECT COALESCE(SUM(amount), 0) AS total FROM subscription_payments WHERE payment_status = 'completed'"
            );
            return floatval($row['total'] ?? 0);
        } catch (\Exception $e) {
            return 0.0;
        }
    }
}
