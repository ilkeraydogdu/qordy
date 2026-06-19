<?php
namespace App\Controllers\SuperAdmin;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';

use App\Core\Controller;

class BusinessOwnersController extends Controller {
    
    public function index() {
        $this->requireLogin();
        
        if (!$this->isSuperAdmin()) {
            header('Location: ' . BASE_URL . '/unauthorized');
            exit;
        }
        
        try {
            $customerService = \App\Core\DependencyFactory::getCustomerService();
            $subscriptionService = \App\Core\DependencyFactory::getSubscriptionService();
            $db = \App\Core\DependencyFactory::getDatabase();
            
            $allCustomers = $customerService->getAllWithSubscriptions();
            
            $businessOwners = [];
            foreach ($allCustomers as $customer) {
                $customerId = $customer['customer_id'] ?? '';
                if (empty($customerId)) continue;
                
                $businessName = $customer['company_name'] ?? 
                              $customer['business_name'] ?? 
                              $customer['restaurant_name'] ?? '';
                
                if (empty($businessName)) {
                    $fn = $customer['first_name'] ?? '';
                    $ln = $customer['last_name'] ?? '';
                    if (!empty($fn) || !empty($ln)) {
                        $businessName = trim($fn . ' ' . $ln) . ' İşletmesi';
                    } else {
                        $businessName = $customer['email'] ?? 'Adsız İşletme';
                    }
                }
                
                $ownerName = trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''));
                if (empty($ownerName)) {
                    $ownerName = $customer['email'] ?? 'Bilinmiyor';
                }
                
                $sub = $customer['subscription'] ?? null;
                $hasSubscription = !empty($customer['subscription_id']) || (!empty($sub) && !empty($sub['subscription_id']));
                $subscriptionPackage = $customer['package_name'] ?? ($sub['package_name'] ?? '');
                $subscriptionEndDate = $sub['current_period_end'] ?? '';
                
                $staffCount = 0;
                try {
                    $staffStmt = $db->prepare("SELECT COUNT(*) as cnt FROM users WHERE tenant_id = :bid");
                    $staffStmt->execute(['bid' => $customerId]);
                    $staffRow = $staffStmt->fetch(\PDO::FETCH_ASSOC);
                    $staffCount = (int)($staffRow['cnt'] ?? 0);
                } catch (\Exception $e) {}
                
                $tableCount = 0;
                try {
                    $tableStmt = $db->prepare("SELECT COUNT(*) as cnt FROM tables WHERE tenant_id = :tid");
                    $tableStmt->execute(['tid' => $customerId]);
                    $tableRow = $tableStmt->fetch(\PDO::FETCH_ASSOC);
                    $tableCount = (int)($tableRow['cnt'] ?? 0);
                } catch (\Exception $e) {}
                
                $businessOwners[] = [
                    'user_id' => $customerId,
                    'customer_id' => $customerId,
                    'name' => $ownerName,
                    'email' => $customer['email'] ?? '',
                    'first_name' => $customer['first_name'] ?? '',
                    'last_name' => $customer['last_name'] ?? '',
                    'phone' => $customer['phone'] ?? '',
                    'subdomain' => $customer['subdomain'] ?? '',
                    'role' => 'BUSINESS_OWNER',
                    'is_active' => isset($customer['is_active']) ? (int)$customer['is_active'] : 0,
                    'business_id' => $customerId,
                    'business_name' => $businessName,
                    'logo_path' => $customer['logo_path'] ?? '',
                    'has_subscription' => $hasSubscription,
                    'subscription_package' => $subscriptionPackage,
                    'subscription_end_date' => $subscriptionEndDate,
                    'staff_count' => $staffCount,
                    'table_count' => $tableCount,
                    'created_at' => $customer['created_at'] ?? ''
                ];
            }
            
            usort($businessOwners, function($a, $b) {
                return strtotime($b['created_at'] ?: '2000-01-01') - strtotime($a['created_at'] ?: '2000-01-01');
            });
            
            $data = [
                'business_owners' => $businessOwners,
                'page' => 'business-owners'
            ];
            
            $this->view('superadmin/business_owners', $data);
            
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Business Owners page error: ' . $e->getMessage());
            }
            $this->toastNotificationService->setFlash('error', 'İşletme sahipleri yüklenirken hata oluştu');
            header('Location: ' . BASE_URL . '/qodmin/dashboard');
            exit;
        }
    }
    
    public function update() {
        // Set proper headers first
        header('Content-Type: application/json; charset=utf-8');
        
        // Check authentication
        if (!$this->isLoggedIn()) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Unauthorized - Login required'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        if (!$this->isSuperAdmin()) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Unauthorized - Super admin required'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        try {
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $userId = $input['user_id'] ?? null;
            
            if (!$userId) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'User ID required'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            $db = \App\Core\DependencyFactory::getDatabase();
            $userService = \App\Core\DependencyFactory::getUserService();
            $customerService = \App\Core\DependencyFactory::getCustomerService();
            
            // Find user - try users table first, then customers table
            $user = $userService->findByUserId($userId);
            $isCustomer = false;
            $customerId = null;
            
            if (!$user) {
                // Try customers table
                try {
                    $customer = $customerService->getById($userId);
                    if ($customer) {
                        $isCustomer = true;
                        $customerId = $customer['customer_id'];
                        $user = $customer;
                    }
                } catch (\Exception $e) {
                    // Customer not found
                }
            }
            
            if (!$user) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Kullanıcı bulunamadı',
                    'user_id' => $userId
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            // Prepare updates based on source table
            $updates = [];
            
            if ($isCustomer && $customerId) {
                // Update customers table
                if (isset($input['first_name'])) {
                    $updates['first_name'] = $input['first_name'];
                }
                if (isset($input['last_name'])) {
                    $updates['last_name'] = $input['last_name'];
                }
                if (isset($input['email'])) {
                    $updates['email'] = $input['email'];
                }
                if (isset($input['phone'])) {
                    $updates['phone'] = $input['phone'];
                }
                
                $hasIsActive = \App\Core\DbSchema::hasColumn('customers', 'is_active');
                if ($hasIsActive && isset($input['is_active'])) {
                    $updates['is_active'] = (int)$input['is_active'];
                }
                
                if (empty($updates)) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'No updates provided'
                    ], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                
                // Build update query for customers
                $setClause = [];
                foreach ($updates as $key => $value) {
                    $setClause[] = "$key = :$key";
                }
                
                $sql = "UPDATE customers SET " . implode(', ', $setClause) . " WHERE customer_id = :customer_id";
                $stmt = $db->prepare($sql);
                $updates['customer_id'] = $customerId;
                $stmt->execute($updates);
                
            } else {
                // User is in users table
                $businessId = $user['tenant_id'] ?? $user['customer_id'] ?? null;
                $customersUpdated = false;
                $usersUpdates = [];
                
                // PRIMARY: Update customers table if business_id exists
                if ($businessId) {
                    try {
                        $customerUpdates = [];
                        if (isset($input['first_name'])) {
                            $customerUpdates['first_name'] = $input['first_name'];
                        }
                        if (isset($input['last_name'])) {
                            $customerUpdates['last_name'] = $input['last_name'];
                        }
                        if (isset($input['email'])) {
                            $customerUpdates['email'] = $input['email'];
                        }
                        if (isset($input['phone'])) {
                            $customerUpdates['phone'] = $input['phone'];
                        }
                        
                        $hasIsActive = \App\Core\DbSchema::hasColumn('customers', 'is_active');
                        if ($hasIsActive && isset($input['is_active'])) {
                            $customerUpdates['is_active'] = (int)$input['is_active'];
                        }
                        
                        if (!empty($customerUpdates)) {
                            $customerSetClause = [];
                            foreach ($customerUpdates as $key => $value) {
                                $customerSetClause[] = "$key = :customer_$key";
                            }
                            
                            $customerSql = "UPDATE customers SET " . implode(', ', $customerSetClause) . " WHERE customer_id = :customer_id";
                            $customerStmt = $db->prepare($customerSql);
                            $customerParams = [];
                            foreach ($customerUpdates as $key => $value) {
                                $customerParams["customer_$key"] = $value;
                            }
                            $customerParams['customer_id'] = $businessId;
                            $customerStmt->execute($customerParams);
                            $customersUpdated = true;
                        }
                    } catch (\Exception $e) {
                        // Log error but continue to try users table update
                        if (class_exists('\App\Core\Logger')) {
                            \App\Core\Logger::error('Failed to update customer table: ' . $e->getMessage(), [
                                'business_id' => $businessId,
                                'trace' => $e->getTraceAsString()
                            ]);
                        }
                    }
                }
                
                // SECONDARY: Update users table if columns exist
                $hasIsActive = false;
                $hasFirstName = false;
                $hasLastName = false;
                $hasEmail = false;
                $hasPhone = false;
                
                $columnNames  = \App\Core\DbSchema::columns('users');
                $hasIsActive  = in_array('is_active', $columnNames, true);
                $hasFirstName = in_array('first_name', $columnNames, true);
                $hasLastName = in_array('last_name', $columnNames);
                $hasEmail = in_array('email', $columnNames);
                $hasPhone = in_array('phone', $columnNames);
                
                // Check which fields to update in users table (only if column exists)
                if (isset($input['is_active']) && $hasIsActive) {
                    $usersUpdates['is_active'] = (int)$input['is_active'];
                }
                if (isset($input['first_name']) && $hasFirstName) {
                    $usersUpdates['first_name'] = $input['first_name'];
                }
                if (isset($input['last_name']) && $hasLastName) {
                    $usersUpdates['last_name'] = $input['last_name'];
                }
                if (isset($input['email']) && $hasEmail) {
                    $usersUpdates['email'] = $input['email'];
                }
                if (isset($input['phone']) && $hasPhone) {
                    $usersUpdates['phone'] = $input['phone'];
                }
                
                // Success if customers updated OR users has updates
                if (!$customersUpdated && empty($usersUpdates)) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'No updates provided or columns do not exist'
                    ], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                
                // Execute users table update if there are updates
                if (!empty($usersUpdates)) {
                    $setClause = [];
                    foreach ($usersUpdates as $key => $value) {
                        $setClause[] = "$key = :$key";
                    }
                    
                    $sql = "UPDATE users SET " . implode(', ', $setClause) . " WHERE user_id = :user_id";
                    $stmt = $db->prepare($sql);
                    $usersUpdates['user_id'] = $userId;
                    $stmt->execute($usersUpdates);
                }
            }
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Kullanıcı güncellendi'
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
            
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Update business owner error: ' . $e->getMessage(), [
                    'trace' => $e->getTraceAsString()
                ]);
            }
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Güncelleme başarısız: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    
    public function show($id = null) {
        // Set proper headers first
        header('Content-Type: application/json; charset=utf-8');
        
        // Check authentication
        if (!$this->isLoggedIn()) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Unauthorized - Login required'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        if (!$this->isSuperAdmin()) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Unauthorized - Super admin required'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        try {
            // Get user ID from route parameter, URL segment, or $_GET
            $userId = $id;
            
            // If route parameter is empty, try to get from URL
            if (empty($userId)) {
                // Try to extract from REQUEST_URI
                $requestUri = $_SERVER['REQUEST_URI'] ?? '';
                if (preg_match('#/api/qodmin/business-owners/([^/]+)#', $requestUri, $matches)) {
                    $userId = $matches[1];
                }
            }
            
            // Fallback to $_GET
            if (empty($userId)) {
                $userId = $_GET['id'] ?? null;
            }
            
            // Debug: Log received parameters
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::info('BusinessOwnersController@show called', [
                    'route_param_id' => $id,
                    'extracted_user_id' => $userId,
                    'get_id' => $_GET['id'] ?? null,
                    'request_uri' => $_SERVER['REQUEST_URI'] ?? null
                ]);
            }
            
            if (empty($userId)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'User ID required',
                    'debug' => [
                        'route_param' => $id,
                        'get_param' => $_GET['id'] ?? null,
                        'request_uri' => $_SERVER['REQUEST_URI'] ?? null
                    ]
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            $userService = \App\Core\DependencyFactory::getUserService();
            $customerService = \App\Core\DependencyFactory::getCustomerService();
            
            $user = null;
            $isCustomer = false;
            $db = \App\Core\DependencyFactory::getDatabase();

            // If userId starts with CUST_, it's a customer_id - get from customers first
            if (strpos($userId, 'CUST_') === 0) {
                try {
                    $customer = $customerService->getById($userId);
                    if ($customer) {
                        // Find the owner user (BUSINESS_MANAGER/TRIAL role) for this business
                        $ownerStmt = $db->prepare("SELECT * FROM users WHERE tenant_id = :tid AND role IN ('BUSINESS_MANAGER','TRIAL','ROLE_BUSINESS_MANAGER') ORDER BY created_at ASC LIMIT 1");
                        $ownerStmt->execute(['tid' => $userId]);
                        $ownerUser = $ownerStmt->fetch(\PDO::FETCH_ASSOC);

                        $user = [
                            'user_id' => $ownerUser['user_id'] ?? $customer['customer_id'],
                            'customer_id' => $customer['customer_id'],
                            'name' => $customer['email'] ?? trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')),
                            'email' => $customer['email'] ?? '',
                            'first_name' => $customer['first_name'] ?? '',
                            'last_name' => $customer['last_name'] ?? '',
                            'phone' => $customer['phone'] ?? '',
                            'role' => $ownerUser['role'] ?? 'BUSINESS_MANAGER',
                            'role_id' => $ownerUser['role_id'] ?? null,
                            'tenant_id' => $customer['customer_id'],
                            'is_active' => isset($customer['is_active']) ? (int)$customer['is_active'] : 1
                        ];
                        $isCustomer = true;
                    }
                } catch (\Exception $e) {}
            }

            // Otherwise try users table
            if (!$user) {
                $user = $userService->findByUserId($userId);
            }
            if (!$user) {
                $stmt = $db->prepare("SELECT * FROM users WHERE user_id = :user_id LIMIT 1");
                $stmt->execute(['user_id' => $userId]);
                $user = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
            }
            
            if (!$user || empty($user)) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'User not found',
                    'user_id' => $userId
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            // Get business/customer info - try multiple methods
            $businessId = $user['tenant_id'] ?? $user['customer_id'] ?? null;
            $business = null;
            
            // Debug: Log business_id
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::info('BusinessOwnersController@show - Looking for business', [
                    'user_id' => $userId,
                    'business_id' => $businessId,
                    'user_business_id' => $user['tenant_id'] ?? null,
                    'user_customer_id' => $user['customer_id'] ?? null
                ]);
            }
            
            // First try to get business by business_id using direct SQL (more reliable)
            if ($businessId) {
                try {
                    // Try customerService first
                    $business = $customerService->getById($businessId);
                    
                    // If not found, try direct SQL query
                    if (!$business) {
                        $db = \App\Core\DependencyFactory::getDatabase();
                        $stmt = $db->prepare("SELECT * FROM customers WHERE customer_id = :customer_id LIMIT 1");
                        $stmt->execute(['customer_id' => $businessId]);
                        $business = $stmt->fetch(\PDO::FETCH_ASSOC);
                    }
                    
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::info('BusinessOwnersController@show - Business found', [
                            'business_id' => $businessId,
                            'found' => !empty($business),
                            'business_keys' => $business ? array_keys($business) : [],
                            'business_first_name' => $business['first_name'] ?? null,
                            'business_last_name' => $business['last_name'] ?? null,
                            'business_phone' => $business['phone'] ?? null
                        ]);
                    }
                } catch (\Exception $e) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::error('BusinessOwnersController@show - Error getting business', [
                            'business_id' => $businessId,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
            
            // If business not found, try to find customer by user's email
            if (!$business && isset($user['email']) && !empty($user['email'])) {
                try {
                    $customerByEmail = $customerService->findByEmail($user['email']);
                    if ($customerByEmail) {
                        $business = $customerByEmail;
                        $businessId = $customerByEmail['customer_id'];
                        
                        if (class_exists('\App\Core\Logger')) {
                            \App\Core\Logger::info('BusinessOwnersController@show - Business found by email', [
                                'email' => $user['email'],
                                'business_id' => $businessId
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    // Ignore
                }
            }
            
            // If still not found, try by user's name field (which might be email)
            if (!$business && isset($user['name']) && !empty($user['name'])) {
                try {
                    // User's name field often stores email for frontend users
                    $customerByEmail = $customerService->findByEmail($user['name']);
                    if ($customerByEmail) {
                        $business = $customerByEmail;
                        $businessId = $customerByEmail['customer_id'];
                    }
                } catch (\Exception $e) {
                    // Ignore
                }
            }
            
            // Get user email - prefer user table, fallback to name field or customer table
            $userEmail = $user['email'] ?? '';
            if (empty($userEmail)) {
                // If email column doesn't exist or is empty, try name field (which stores email for customers)
                $userEmail = $user['name'] ?? '';
                // If still empty, get from customer table
                if (empty($userEmail) && $business) {
                    $userEmail = $business['email'] ?? '';
                }
            }
            
            // Get first_name - prefer user table, then business/customer table
            $userFirstName = $user['first_name'] ?? '';
            if (empty($userFirstName)) {
                if ($business) {
                    $userFirstName = $business['first_name'] ?? '';
                }
                // If still empty and name field contains full name, try to parse
                if (empty($userFirstName) && isset($user['name']) && strpos($user['name'], ' ') !== false) {
                    $nameParts = explode(' ', $user['name'], 2);
                    $userFirstName = $nameParts[0] ?? '';
                }
                // If still empty, try to extract from email (before @)
                if (empty($userFirstName) && !empty($userEmail) && strpos($userEmail, '@') !== false) {
                    $emailParts = explode('@', $userEmail);
                    $emailName = $emailParts[0] ?? '';
                    // Try to parse name from email (e.g., "john.doe" -> "john")
                    if (strpos($emailName, '.') !== false) {
                        $emailNameParts = explode('.', $emailName);
                        $userFirstName = ucfirst($emailNameParts[0] ?? '');
                    } else {
                        $userFirstName = ucfirst($emailName);
                    }
                }
            }
            
            // Get last_name - prefer user table, then business/customer table
            $userLastName = $user['last_name'] ?? '';
            if (empty($userLastName)) {
                if ($business) {
                    $userLastName = $business['last_name'] ?? '';
                }
                // If still empty and name field contains full name, try to parse
                if (empty($userLastName) && isset($user['name']) && strpos($user['name'], ' ') !== false) {
                    $nameParts = explode(' ', $user['name'], 2);
                    $userLastName = $nameParts[1] ?? '';
                }
                // If still empty, try to extract from email (before @)
                if (empty($userLastName) && !empty($userEmail) && strpos($userEmail, '@') !== false) {
                    $emailParts = explode('@', $userEmail);
                    $emailName = $emailParts[0] ?? '';
                    // Try to parse name from email (e.g., "john.doe" -> "doe")
                    if (strpos($emailName, '.') !== false) {
                        $emailNameParts = explode('.', $emailName);
                        $userLastName = ucfirst($emailNameParts[1] ?? '');
                    }
                }
            }
            
            // Get phone - prefer user table, then business/customer table
            $userPhone = $user['phone'] ?? '';
            if (empty($userPhone) && $business) {
                $userPhone = $business['phone'] ?? '';
            }
            
            // Debug: Log extracted values
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::info('BusinessOwnersController@show - Extracted user info', [
                    'user_id' => $userId,
                    'first_name' => $userFirstName,
                    'last_name' => $userLastName,
                    'phone' => $userPhone,
                    'email' => $userEmail,
                    'has_business' => !empty($business),
                    'business_first_name' => $business['first_name'] ?? null,
                    'business_last_name' => $business['last_name'] ?? null,
                    'business_phone' => $business['phone'] ?? null
                ]);
            }
            
            // Get business name - try multiple sources
            $businessName = '';
            if ($business) {
                $businessName = $business['company_name'] ?? 
                              $business['business_name'] ?? 
                              $business['restaurant_name'] ?? 
                              $business['name'] ?? '';
                
                // If still empty, try to construct from first/last name
                if (empty($businessName)) {
                    $firstName = $business['first_name'] ?? '';
                    $lastName = $business['last_name'] ?? '';
                    if (!empty($firstName) || !empty($lastName)) {
                        $businessName = trim($firstName . ' ' . $lastName) . ' İşletmesi';
                    }
                }
            }
            
            // Prepare user data for response
            $userData = [
                'user_id' => $user['user_id'] ?? $user['customer_id'] ?? '',
                'first_name' => $userFirstName,
                'last_name' => $userLastName,
                'email' => $userEmail,
                'phone' => $userPhone,
                'role' => $user['role'] ?? '',
                'role_id' => $user['role_id'] ?? '',
                'business_id' => $businessId,
                'business_name' => $businessName,
                'is_active' => isset($user['is_active']) ? (int)$user['is_active'] : 1
            ];
            
            // Debug: Log user data
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::info('BusinessOwnersController@show - User data prepared', [
                    'user_id' => $userId,
                    'user_data' => $userData,
                    'source' => $isCustomer ? 'customers' : 'users',
                    'has_business' => !empty($business)
                ]);
            }
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'user' => $userData
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
            
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Get business owner error: ' . $e->getMessage(), [
                    'trace' => $e->getTraceAsString()
                ]);
            }
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Kullanıcı bilgileri alınamadı: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    
    public function getPermissions($id = null) {
        // Set proper headers first
        header('Content-Type: application/json; charset=utf-8');
        
        // Check authentication
        if (!$this->isLoggedIn()) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Unauthorized - Login required'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        if (!$this->isSuperAdmin()) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Unauthorized - Super admin required'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        try {
            // Get user ID from route parameter or fallback to $_GET
            $userId = $id ?? $_GET['id'] ?? null;
            
            if (!$userId) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'User ID required'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            $db = \App\Core\DependencyFactory::getDatabase();
            $userService = \App\Core\DependencyFactory::getUserService();
            $customerService = \App\Core\DependencyFactory::getCustomerService();
            
            // Find user - try users table first, then customers table
            $user = $userService->findByUserId($userId);
            
            if (!$user) {
                // Try customers table
                try {
                    $customer = $customerService->getById($userId);
                    if ($customer) {
                        $user = [
                            'user_id' => $customer['customer_id'],
                            'role' => 'BUSINESS_MANAGER',
                            'role_id' => 'ROLE_BUSINESS_MANAGER'
                        ];
                    }
                } catch (\Exception $e) {
                    // Customer not found
                }
            }
            
            if (!$user) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Kullanıcı bulunamadı'
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            // Get user role
            $roleId = $user['role_id'] ?? $user['role'] ?? '';
            $role = $user['role'] ?? '';
            
            // Normalize role
            if (empty($roleId) && !empty($role)) {
                $roleId = $role;
            }
            
            // Get all navigation items
            $allNavItems = [];
            try {
                $navStmt = $db->query("SELECT nav_id, nav_key, nav_name, nav_label FROM navigation_items WHERE is_active = 1 ORDER BY display_order ASC, nav_key ASC");
                $allNavItems = $navStmt->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Exception $e) {
                // Navigation items table might not exist
            }
            
            // Get user's menu access (navigation_roles table)
            $permissions = [];
            if (!empty($allNavItems)) {
                foreach ($allNavItems as $navItem) {
                    $navId = $navItem['nav_id'] ?? '';
                    $hasAccess = false;
                    
                    // Check if user's role has access to this navigation item
                    try {
                        $checkStmt = $db->prepare("
                            SELECT COUNT(*) as count 
                            FROM navigation_roles 
                            WHERE nav_id = :nav_id 
                            AND (role_id = :role_id OR role = :role)
                        ");
                        $checkStmt->execute([
                            'nav_id' => $navId,
                            'role_id' => $roleId,
                            'role' => $role
                        ]);
                        $result = $checkStmt->fetch(\PDO::FETCH_ASSOC);
                        $hasAccess = ($result['count'] ?? 0) > 0;
                    } catch (\Exception $e) {
                        // If navigation_roles table doesn't exist or query fails, assume no access
                        $hasAccess = false;
                    }
                    
                    $permissions[] = [
                        'permission_id' => $navId,
                        'permission_name' => $navItem['label_tr'] ?? $navItem['nav_label'] ?? $navItem['nav_name'] ?? $navItem['nav_key'] ?? '',
                        'nav_key' => $navItem['nav_key'] ?? '',
                        'has_access' => $hasAccess
                    ];
                }
            }
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'permissions' => $permissions,
                'user_role' => $role,
                'user_role_id' => $roleId
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
            
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Get user permissions error: ' . $e->getMessage(), [
                    'trace' => $e->getTraceAsString()
                ]);
            }
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'İzinler alınamadı: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    
    public function delete() {
        // Set proper headers first
        header('Content-Type: application/json; charset=utf-8');
        
        // Check authentication
        if (!$this->isLoggedIn()) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Unauthorized - Login required'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        if (!$this->isSuperAdmin()) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Unauthorized - Super admin required'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $userId = null;
        try {
            $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $userId = $input['user_id'] ?? null;
            
            // Ensure userId is string and trim whitespace
            if ($userId !== null) {
                $userId = trim((string)$userId);
            }
            
            if (!$userId || $userId === '') {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'User ID required',
                    'debug' => [
                        'input' => $input,
                        'user_id_received' => $input['user_id'] ?? null
                    ]
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            $db = \App\Core\DependencyFactory::getDatabase();
            
            // Debug: Log user ID and input
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::info('Delete user request', [
                    'user_id' => $userId,
                    'user_id_length' => strlen($userId),
                    'input' => $input,
                    'request_uri' => $_SERVER['REQUEST_URI'] ?? null
                ]);
            }
            
            $userService = \App\Core\DependencyFactory::getUserService();
            $customerService = \App\Core\DependencyFactory::getCustomerService();
            
            $user = null;
            $isCustomer = false;
            $customerId = null;

            // If it's a customer_id (CUST_xxx), use BusinessDeletionService for full cleanup
            if (strpos($userId, 'CUST_') === 0) {
                $customer = $customerService->getById($userId);
                if ($customer) {
                    $isCustomer = true;
                    $customerId = $customer['customer_id'];
                    $user = $customer;
                }
            } else {
                $user = $userService->findByUserId($userId);
                if (!$user) {
                    $stmt = $db->prepare("SELECT * FROM users WHERE user_id = :user_id LIMIT 1");
                    $stmt->execute(['user_id' => $userId]);
                    $user = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
                }
            }
            
            if (!$user) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Kullanıcı bulunamadı',
                    'user_id' => $userId
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            // Log found user
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::info('User found for deletion', [
                    'user_id' => $userId,
                    'user_name' => $user['name'] ?? 'N/A',
                    'is_active' => $user['is_active'] ?? 'N/A',
                    'has_email_column' => isset($user['email'])
                ]);
            }
            
            if ($isCustomer && $customerId) {
                $deletionService = new \App\Services\BusinessDeletionService($db);
                $subdomain = $user['subdomain'] ?? '';
                $result = $deletionService->deleteBusinessCompletely($customerId, $subdomain);
                if (!$result['success']) {
                    throw new \Exception($result['message'] ?? 'İşletme silinemedi');
                }
            } else {
                $hasIsActive = \App\Core\DbSchema::hasColumn('users', 'is_active');
                if ($hasIsActive) {
                    // Soft delete - set is_active to 0
                    $stmt = $db->prepare("UPDATE users SET is_active = 0 WHERE user_id = :user_id");
                    $result = $stmt->execute(['user_id' => $userId]);
                    
                    if (!$result || $stmt->rowCount() === 0) {
                        throw new \Exception('Kullanıcı güncellenemedi');
                    }
                } else {
                    // Hard delete if is_active column doesn't exist
                    $stmt = $db->prepare("DELETE FROM users WHERE user_id = :user_id");
                    $result = $stmt->execute(['user_id' => $userId]);
                    
                    if (!$result || $stmt->rowCount() === 0) {
                        throw new \Exception('Kullanıcı silinemedi');
                    }
                }
            }
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Kullanıcı başarıyla silindi'
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
            
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Delete business owner error: ' . $e->getMessage(), [
                    'trace' => $e->getTraceAsString(),
                    'user_id' => $userId
                ]);
            }
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Silme başarısız: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}
