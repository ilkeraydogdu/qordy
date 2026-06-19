<?php
namespace App\Services;

require_once __DIR__ . '/../core/DependencyFactory.php';

use PDO;
use PDOException;

/**
 * TenantService - Multi-tenant database management
 * Handles tenant-specific database creation and configuration
 */
class TenantService {

    private $centralDb;

    public function __construct() {
        $this->centralDb = \App\Core\DependencyFactory::getDatabase();
    }

    /**
     * Create tenant database
     * @param string $subdomain Subdomain for the tenant
     * @param string $customerId Customer ID
     * @return array ['success' => bool, 'database_name' => string|null, 'error' => string|null]
     */
    public function createTenantDatabase(string $subdomain, string $customerId): array {
        try {
            // Generate database name based on subdomain
            $databaseName = 'tenant_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $subdomain) . '_' . time();
            
            // Connect to MySQL server with privileges to create databases
            // CRITICAL: Use dbadmin user for CREATE DATABASE permission (root has socket auth issues)
            $host = $_ENV['DB_HOST'] ?? 'localhost';
            $username = $_ENV['DB_ROOT_USER'] ?? 'dbadmin';
            $password = $_ENV['DB_ROOT_PASS'] ?? 'QWerasdf01/';
            
            // Create a connection with privileges to create databases
            $pdo = new \PDO("mysql:host={$host}", $username, $password, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            // Create the database
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            // Select the database
            $pdo->exec("USE `{$databaseName}`");
            
            // Create tenant-specific tables by copying structure from central database
            $this->createTenantTables($pdo);
            
            // Update customer record with tenant database info (if column exists)
            try {
                if (\App\Core\DbSchema::hasColumn('customers', 'tenant_database')) {
                    $stmt = $this->centralDb->prepare("
                        UPDATE customers 
                        SET tenant_database = :database_name 
                        WHERE customer_id = :customer_id
                    ");
                    $stmt->execute([
                        'database_name' => $databaseName,
                        'customer_id' => $customerId
                    ]);
                } else {
                    // Column doesn't exist - log but don't fail
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::info('tenant_database column does not exist, skipping update', [
                            'customer_id' => $customerId,
                            'database_name' => $databaseName
                        ]);
                    }
                }
            } catch (\Exception $e) {
                // Log error but don't fail tenant database creation
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning('Failed to update tenant_database in customers table', [
                        'customer_id' => $customerId,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return [
                'success' => true,
                'database_name' => $databaseName,
                'error' => null
            ];

        } catch (PDOException $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Tenant database creation failed', [
                    'subdomain' => $subdomain,
                    'customer_id' => $customerId,
                    'error' => $e->getMessage()
                ]);
            }

            return [
                'success' => false,
                'database_name' => null,
                'error' => 'Tenant database creation failed: ' . $e->getMessage()
            ];
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Tenant database creation failed', [
                    'subdomain' => $subdomain,
                    'customer_id' => $customerId,
                    'error' => $e->getMessage()
                ]);
            }

            return [
                'success' => false,
                'database_name' => null,
                'error' => 'Tenant database creation failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Create tenant-specific tables by copying structure from central database
     * @param PDO $pdo Tenant database connection
     */
    private function createTenantTables(PDO $pdo): void {
        // Define tables that should be created in tenant database
        $tenantTables = [
            'users',
            'orders', 
            'order_items',
            'products',
            'categories',
            'tables',
            'reservations',
            'payments',
            'receipts',
            'shifts',
            'staff',
            'inventory',
            'suppliers',
            'expenses',
            'settings'
        ];

        // Get the structure of each table from the central database
        $centralDbName = $_ENV['DB_NAME'] ?? 'qordy';
        
        foreach ($tenantTables as $tableName) {
            try {
                // Get table structure from central database
                $structureSql = "SHOW CREATE TABLE `{$centralDbName}`.`{$tableName}`";
                $stmt = $this->centralDb->query($structureSql);
                $row = $stmt->fetch();
                
                if ($row) {
                    $createStatement = $row['Create Table'];
                    
                    // Modify the CREATE statement to use the new table name
                    $createStatement = str_replace(
                        "CREATE TABLE `{$tableName}`",
                        "CREATE TABLE `{$tableName}`",
                        $createStatement
                    );
                    
                    // Execute the CREATE statement in the tenant database
                    $pdo->exec($createStatement);
                }
            } catch (\Exception $e) {
                // Table might not exist in central DB, skip it
                continue;
            }
        }
    }

    /**
     * Get tenant database connection
     * @param string $customerId Customer ID
     * @return PDO|null
     */
    public function getTenantConnection(string $customerId): ?PDO {
        try {
            if (!\App\Core\DbSchema::hasColumn('customers', 'tenant_database')) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::debug('tenant_database column does not exist, cannot get tenant connection', [
                        'customer_id' => $customerId
                    ]);
                }
                return null;
            }
            
            // Get tenant database name from customer record
            $stmt = $this->centralDb->prepare("SELECT tenant_database FROM customers WHERE customer_id = :customer_id");
            $stmt->execute(['customer_id' => $customerId]);
            $customer = $stmt->fetch();

            if (!$customer || empty($customer['tenant_database'])) {
                return null;
            }

            $tenantDbName = $customer['tenant_database'];
            $host = $_ENV['DB_HOST'] ?? 'localhost';
            $username = $_ENV['DB_USER'] ?? 'root';
            $password = $_ENV['DB_PASS'] ?? '';

            $pdo = new \PDO(
                "mysql:host={$host};dbname={$tenantDbName};charset=utf8mb4",
                $username,
                $password,
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );

            return $pdo;
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Tenant database connection failed', [
                    'customer_id' => $customerId,
                    'error' => $e->getMessage()
                ]);
            }
            return null;
        }
    }

    /**
     * Initialize tenant with default data
     * Single database approach - just ensures business_id is set for the owner
     * @param string $customerId Customer ID (business_id)
     * @param string $ownerUserId Owner user ID
     * @return bool
     */
    public function initializeTenant(string $customerId, string $ownerUserId): bool {
        try {
            // In single database approach, we just need to ensure the owner user
            // has the correct business_id and role
            
            // Check if owner user already has business_id set
            $stmt = $this->centralDb->prepare("SELECT tenant_id FROM users WHERE user_id = :user_id");
            $stmt->execute(['user_id' => $ownerUserId]);
            $user = $stmt->fetch();
            
            if ($user && empty($user['tenant_id'])) {
                // Update user with tenant_id
                $updateStmt = $this->centralDb->prepare("
                    UPDATE users 
                    SET tenant_id = :tenant_id,
                        role = 'BUSINESS_MANAGER'
                    WHERE user_id = :user_id
                ");
                
                $result = $updateStmt->execute([
                    'tenant_id' => $customerId,
                    'user_id' => $ownerUserId
                ]);
                
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::info('Tenant initialized (single DB)', [
                        'customer_id' => $customerId,
                        'owner_user_id' => $ownerUserId,
                        'updated' => $result
                    ]);
                }
                
                return $result;
            }
            
            // User already has business_id set
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::info('Tenant already initialized', [
                    'customer_id' => $customerId,
                    'owner_user_id' => $ownerUserId
                ]);
            }
            
            return true;
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Tenant initialization failed', [
                    'customer_id' => $customerId,
                    'owner_user_id' => $ownerUserId,
                    'error' => $e->getMessage()
                ]);
            }
            return false;
        }
    }

    /**
     * Check if tenant database exists
     * @param string $customerId Customer ID
     * @return bool
     */
    public function tenantDatabaseExists(string $customerId): bool {
        try {
            if (!\App\Core\DbSchema::hasColumn('customers', 'tenant_database')) {
                return false;
            }
            
            $stmt = $this->centralDb->prepare("SELECT tenant_database FROM customers WHERE customer_id = :customer_id");
            $stmt->execute(['customer_id' => $customerId]);
            $customer = $stmt->fetch();

            if (!$customer || empty($customer['tenant_database'])) {
                return false;
            }

            $tenantDbName = $customer['tenant_database'];
            $host = $_ENV['DB_HOST'] ?? 'localhost';
            // Use dbadmin user for checking database existence
            $username = $_ENV['DB_ROOT_USER'] ?? 'dbadmin';
            $password = $_ENV['DB_ROOT_PASS'] ?? 'QWerasdf01/';

            $pdo = new \PDO("mysql:host={$host}", $username, $password, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '{$tenantDbName}'");
            return $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
}