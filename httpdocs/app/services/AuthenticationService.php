<?php
namespace App\Services;

require_once __DIR__ . '/../core/SessionManager.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/RoleMapper.php';

/**
 * Centralized Authentication Service
 * Handles user authentication with PIN-based login
 * 
 * Features:
 * - PIN hashing verification
 * - Session management
 * - Role normalization
 * - Security logging
 * 
 * @package App\Services
 * 
 * @example
 * $authService = new AuthenticationService();
 * $user = $authService->authenticateWithPin('1234');
 * if ($user) {
 *     // User authenticated
 * }
 */
class AuthenticationService {
    private $userRepository;
    private $roleMapper;
    private $roleService;
    
    /**
     * Constructor with dependency injection
     * @param \App\Repositories\UserRepository|null $userRepository Optional user repository
     * @param \App\Services\RoleService|null $roleService Optional role service
     */
    public function __construct(?\App\Repositories\UserRepository $userRepository = null, ?\App\Services\RoleService $roleService = null) {
        if ($userRepository !== null) {
            $this->userRepository = $userRepository;
        } else {
            // Use DependencyFactory for DI
            try {
                require_once __DIR__ . '/../core/DependencyFactory.php';
                $this->userRepository = \App\Core\DependencyFactory::getUserRepository();
            } catch (\Exception $e) {
                // Fallback to model if repository not available
                $this->userRepository = null;
            }
        }
        
        if ($roleService !== null) {
            $this->roleService = $roleService;
        } else {
            try {
                require_once __DIR__ . '/../core/DependencyFactory.php';
                $this->roleService = \App\Core\DependencyFactory::getRoleService();
            } catch (\Exception $e) {
                $this->roleService = null;
            }
        }
        
        $this->roleMapper = RoleMapper::getInstance();
    }
    
    /**
     * Get user model (for backward compatibility)
     */
    private function getUserModel() {
        if ($this->userRepository) {
            // Create a User model with repository's database connection
            $userModel = new \App\Models\User($this->userRepository->getDbConnection());
            return $userModel;
        }
        // Fallback
        return new \App\Models\User();
    }
    
    /**
     * Authenticate user with PIN
     *
     * This method verifies the provided PIN against all users in the database.
     * Only hashed PINs are accepted for security reasons.
     *
     * @param string $pin Plain text PIN to verify
     * @return array|false User data on success, false on failure
     *
     * @example
     * $user = $authService->authenticateWithPin('1234');
     * if ($user) {
     *     echo "Logged in as: " . $user['name'];
     * }
     */
    public function authenticateWithPin(string $pin) {
        \App\Core\SessionManager::ensureSession();

        // CRITICAL: Ensure tenant context is set from subdomain BEFORE any PIN validation
        // This is essential for subdomain-based PIN login to work correctly
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
        $subdomain = \App\Core\TenantContext::getSubdomainFromHost($host);
        $tenantId = \App\Core\TenantContext::getId();
        
        // If tenant context not set, force set it from subdomain
        if (!$tenantId && $subdomain) {
            try {
                // First try to query database directly (fastest and most reliable method)
                try {
                    require_once __DIR__ . '/../core/DependencyFactory.php';
                    $db = \App\Core\DependencyFactory::getDatabase();
                    $stmt = $db->prepare("SELECT customer_id, company_name FROM customers WHERE subdomain = :subdomain AND is_active = 1 LIMIT 1");
                    $stmt->execute(['subdomain' => $subdomain]);
                    $customer = $stmt->fetch(\PDO::FETCH_ASSOC);
                    if ($customer && !empty($customer['customer_id'])) {
                        \App\Core\TenantContext::setId($customer['customer_id']);
                        $tenantId = $customer['customer_id'];
                        
                        if (class_exists('\App\Core\Logger')) {
                            \App\Core\Logger::info('AuthenticationService: Tenant context set directly from database', [
                                'subdomain' => $subdomain,
                                'tenant_id' => $tenantId,
                                'customer_name' => $customer['company_name'] ?? 'unknown'
                            ]);
                        }
                    }
                } catch (\Exception $dbException) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::warning('AuthenticationService: Failed to set tenant context from database', [
                            'error' => $dbException->getMessage(),
                            'subdomain' => $subdomain
                        ]);
                    }
                }
                
                // If still not set, try TenantMiddleware as fallback
                if (!$tenantId) {
                    try {
                        require_once __DIR__ . '/../middleware/TenantMiddleware.php';
                        $tenantMiddleware = new \App\Middleware\TenantMiddleware();
                        $tenantMiddleware->handle();
                        $tenantId = \App\Core\TenantContext::getId();
                        
                        if (class_exists('\App\Core\Logger')) {
                            \App\Core\Logger::debug('AuthenticationService: Tenant context set via TenantMiddleware', [
                                'subdomain' => $subdomain,
                                'tenant_id' => $tenantId,
                                'host' => $host
                            ]);
                        }
                    } catch (\Exception $e) {
                        if (class_exists('\App\Core\Logger')) {
                            \App\Core\Logger::warning('AuthenticationService: Failed to set tenant context via middleware', [
                                'error' => $e->getMessage(),
                                'subdomain' => $subdomain,
                                'host' => $host
                            ]);
                        }
                    }
                }
            } catch (\Exception $e) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('AuthenticationService: Failed to set tenant context in authenticateWithPin', [
                        'error' => $e->getMessage(),
                        'subdomain' => $subdomain,
                        'host' => $host
                    ]);
                }
            }
        }
        
        // DEBUG: Log tenant context status with full details
        if (class_exists('\App\Core\Logger')) {
            \App\Core\Logger::info('AuthenticationService::authenticateWithPin - Tenant context status', [
                'tenant_id' => $tenantId,
                'tenant_id_type' => gettype($tenantId),
                'subdomain' => $subdomain,
                'host' => $host,
                'tenant_context_set' => \App\Core\TenantContext::isSet(),
                'tenant_context_id' => \App\Core\TenantContext::getId(),
                'session_tenant_id' => $_SESSION['tenant_id'] ?? 'not_set',
                'session_tenant_subdomain' => $_SESSION['tenant_subdomain'] ?? 'not_set'
            ]);
        }

        if (empty($pin)) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning("Authentication failed: Empty PIN");
            }
            return false;
        }

        // Validate PIN format (only numbers, 4-8 digits)
        if (!preg_match('/^\d{4,8}$/', $pin)) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning("Authentication failed: Invalid PIN format", [
                    'pin_length' => strlen($pin),
                    'ip'         => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                ]);
            }
            return false;
        }

        // Trim and normalize PIN
        $pin = trim($pin);

        // Check if user is already logged in with this PIN
        $currentUserId = $_SESSION['user_id'] ?? null;
        $currentLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
        
        if ($currentLoggedIn && $currentUserId) {
            // Find user by PIN to check if it's the same user
            $userModel = $this->getUserModel();
            $userByPin = $userModel->findByPin($pin);
            
            if ($userByPin && $userByPin['user_id'] === $currentUserId) {
                // Same user already logged in - return current user data
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::debug("User already logged in with same PIN", [
                        'user_id' => $currentUserId,
                        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                    ]);
                }
                return $userByPin;
            }
            // Different user - will continue with new authentication (old session will be replaced)
        }
        
        // Use UserRepository instead of User Model for better tenant filtering and PIN handling
        // UserRepository::findByPin() handles tenant context, encrypted PINs, and SQL filtering properly
        if (!$this->userRepository) {
            require_once __DIR__ . '/../core/DependencyFactory.php';
            $this->userRepository = \App\Core\DependencyFactory::getUserRepository();
        }
        
        // DEBUG: Log before PIN search
        if (class_exists('\App\Core\Logger')) {
            \App\Core\Logger::info("AuthenticationService::authenticateWithPin - Starting PIN search", [
                'pin_length' => strlen($pin),
                'pin_preview' => substr($pin, 0, 2) . '**',
                'tenant_id' => $tenantId,
                'tenant_context_set' => \App\Core\TenantContext::isSet(),
                'subdomain' => $subdomain,
                'host' => $host
            ]);
        }
        
        $user = $this->userRepository->findByPin($pin);
        
        // Log PIN search result
        if (class_exists('\App\Core\Logger')) {
            \App\Core\Logger::info("AuthenticationService::authenticateWithPin - PIN search result", [
                'pin_length' => strlen($pin),
                'pin_preview' => substr($pin, 0, 2) . '**',
                'tenant_id' => $tenantId,
                'user_found' => !empty($user),
                'user_result_type' => gettype($user),
                'user_result_empty' => empty($user),
                'user_result_false' => ($user === false),
                'user_id' => $user['user_id'] ?? 'none',
                'user_name' => $user['name'] ?? 'none',
                'user_business_id' => $user['tenant_id'] ?? 'none',
                'user_role' => $user['role'] ?? 'none',
                'preparation_screen_id' => $user['preparation_screen_id'] ?? 'not_set',
                'has_preparation_screen_id' => isset($user['preparation_screen_id'])
            ]);
        }
        
        // CRITICAL: Ensure preparation_screen_id is included in returned user data
        // If findByPin didn't return it, fetch it from database
        if ($user && !isset($user['preparation_screen_id']) && !empty($user['user_id'])) {
            try {
                $userService = \App\Core\DependencyFactory::getUserService();
                $fullUserData = $userService->findByUserId($user['user_id']);
                if ($fullUserData && isset($fullUserData['preparation_screen_id'])) {
                    $user['preparation_screen_id'] = $fullUserData['preparation_screen_id'];
                    
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::info("AuthenticationService::authenticateWithPin - Added preparation_screen_id to user data", [
                            'user_id' => $user['user_id'],
                            'preparation_screen_id' => $user['preparation_screen_id']
                        ]);
                    }
                }
            } catch (\Exception $e) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning("AuthenticationService::authenticateWithPin - Failed to fetch preparation_screen_id", [
                        'user_id' => $user['user_id'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        if ($user) {
            // CRITICAL: Double-check tenant/subdomain isolation
            // Users can only login to their assigned business subdomain
            $userBusinessId = $user['tenant_id'] ?? null;
            
            // Get subdomain from host for additional verification
            $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
            $currentSubdomain = \App\Core\TenantContext::getSubdomainFromHost($host);
            
            // DEBUG: Log tenant isolation check with full details
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::info("AuthenticationService: Tenant isolation check START", [
                    'user_id' => $user['user_id'],
                    'user_name' => $user['name'] ?? 'unknown',
                    'user_business_id' => $userBusinessId,
                    'user_business_id_type' => gettype($userBusinessId),
                    'tenant_id' => $tenantId,
                    'tenant_id_type' => gettype($tenantId),
                    'tenant_context_set' => \App\Core\TenantContext::isSet(),
                    'subdomain' => $_SESSION['tenant_subdomain'] ?? 'unknown',
                    'current_subdomain' => $currentSubdomain,
                    'host' => $host,
                    'pin_length' => strlen($pin)
                ]);
            }
            
            if ($tenantId) {
                // Subdomain login - user must belong to this business
                // CRITICAL: business_id in users table = customer_id (they are the same value)
                // So we compare user's business_id with tenant's customer_id
                
                // Normalize both values to strings for comparison (handle type mismatches)
                $userBusinessIdStr = trim((string)$userBusinessId);
                $tenantIdStr = trim((string)$tenantId);
                
                // Remove any whitespace or special characters
                $userBusinessIdStr = preg_replace('/\s+/', '', $userBusinessIdStr);
                $tenantIdStr = preg_replace('/\s+/', '', $tenantIdStr);
                
                // Also try case-insensitive comparison
                $userBusinessIdLower = strtolower($userBusinessIdStr);
                $tenantIdLower = strtolower($tenantIdStr);
                
                // Check if user belongs to this tenant using multiple comparison methods
                $isMatch = ($userBusinessIdStr === $tenantIdStr) || 
                           ($userBusinessIdLower === $tenantIdLower) ||
                           ($userBusinessId == $tenantId);
                
                // DEBUG: Log comparison details
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::info("AuthenticationService: Tenant isolation comparison", [
                        'user_business_id' => $userBusinessId,
                        'user_business_id_str' => $userBusinessIdStr,
                        'user_business_id_lower' => $userBusinessIdLower,
                        'tenant_id' => $tenantId,
                        'tenant_id_str' => $tenantIdStr,
                        'tenant_id_lower' => $tenantIdLower,
                        'comparison_exact' => ($userBusinessIdStr === $tenantIdStr),
                        'comparison_case_insensitive' => ($userBusinessIdLower === $tenantIdLower),
                        'comparison_loose' => ($userBusinessId == $tenantId),
                        'is_match' => $isMatch,
                        'user_business_id_empty' => empty($userBusinessId)
                    ]);
                }
                
                if (empty($userBusinessId) || !$isMatch) {
                    // Log detailed comparison for debugging
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::error("Login blocked: Tenant isolation check failed - User does not belong to this business", [
                            'user_id' => $user['user_id'],
                            'user_name' => $user['name'] ?? 'unknown',
                            'user_business_id' => $userBusinessId,
                            'user_business_id_str' => $userBusinessIdStr,
                            'user_business_id_lower' => $userBusinessIdLower,
                            'tenant_id' => $tenantId,
                            'tenant_id_str' => $tenantIdStr,
                            'tenant_id_lower' => $tenantIdLower,
                            'comparison_exact' => ($userBusinessIdStr === $tenantIdStr),
                            'comparison_case_insensitive' => ($userBusinessIdLower === $tenantIdLower),
                            'comparison_loose' => ($userBusinessId == $tenantId),
                            'is_match' => $isMatch,
                            'subdomain' => $_SESSION['tenant_subdomain'] ?? 'unknown',
                            'current_subdomain' => $currentSubdomain,
                            'pin_length' => strlen($pin),
                            'host' => $host,
                            'message' => 'Bu kullanıcı bu işletmeye ait değil. Başka bir işletmeye giriş yapamazsınız.'
                        ]);
                    }
                    return false;
                }
                
                // Log successful tenant match
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::info("AuthenticationService: Tenant isolation check PASSED", [
                        'user_id' => $user['user_id'],
                        'user_business_id' => $userBusinessId,
                        'tenant_id' => $tenantId,
                        'comparison_method' => 'exact_match'
                    ]);
                }
            } else {
                // Main domain login - only allow SuperAdmin/Qodmin without business_id
                $userRole = strtoupper($user['role'] ?? '');
                $isAdmin = in_array($userRole, ['SUPER_ADMIN', 'QODMIN', 'ROLE_SUPER_ADMIN']);
                
                if (!$isAdmin && !empty($userBusinessId)) {
                    // Regular user with business_id trying to login from main domain
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::warning("Login blocked: Business user must login via subdomain", [
                            'user_id' => $user['user_id'],
                            'user_business_id' => $userBusinessId,
                            'role' => $userRole,
                            'pin_length' => strlen($pin)
                        ]);
                    }
                    return false;
                }
            }
            
            // Get current IP address
            $currentIP = $this->getClientIP();
            
            // Check if this PIN is already in use on a different IP
            // If PIN is active on another device, prevent new login
            // BUT: Only block if user is actually logged in (to prevent false positives from stale mappings)
            $existingIP = $this->getIPByPin($pin);
            
            // Normalize IPs for comparison
            $normalizeIP = function($ip) {
                if (!$ip) return null;
                $ip = explode(':', $ip)[0]; // Remove port
                return trim($ip);
            };
            
            $normalizedExistingIP = $normalizeIP($existingIP);
            $normalizedCurrentIP = $normalizeIP($currentIP);
            
            // Check if user is logged in
            $currentLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
            
            // If user is NOT logged in and Redis has mapping, it's stale - clear it
            if (!$currentLoggedIn && $existingIP) {
                $this->clearPinIPMapping($pin);
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::info("Cleared stale PIN mapping in AuthenticationService", [
                        'pin_hash'    => substr(hash('sha256', $pin), 0, 12),
                        'existing_ip' => $existingIP,
                    ]);
                }
                $existingIP = null;
                $normalizedExistingIP = null;
            }
            
            // Only block if PIN is on different IP AND user is logged in
            if ($normalizedExistingIP && $normalizedExistingIP !== $normalizedCurrentIP && $currentLoggedIn) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning("Login blocked: PIN already in use on another device", [
                        'pin_hash'               => substr(hash('sha256', $pin), 0, 12),
                        'existing_ip'            => $existingIP,
                        'normalized_existing_ip' => $normalizedExistingIP,
                        'current_ip'             => $currentIP,
                        'normalized_current_ip'  => $normalizedCurrentIP,
                        'user_id'                => $user['user_id'],
                        'current_logged_in'      => $currentLoggedIn,
                    ]);
                }
                // Return false to prevent login - user must logout from other device first
                return false;
            }
            
            // Check if this IP already has an active session with a different user
            $existingUserId = $this->getUserIdByIP($currentIP);
            if ($existingUserId && $existingUserId !== $user['user_id']) {
                // Different user already logged in from this IP - logout old user and allow new login
                $this->logoutUserFromIP($currentIP, $existingUserId);
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::info("Previous user logged out from IP due to new user login", [
                        'ip' => $currentIP,
                        'old_user_id' => $existingUserId,
                        'new_user_id' => $user['user_id'],
                        'new_username' => $user['name'] ?? 'unknown'
                    ]);
                }
                // Continue with login - old session has been invalidated
            }
            
            // Check if this user is already logged in from a different IP
            $existingIPForUser = $this->getIPByUserId($user['user_id']);
            if ($existingIPForUser && $existingIPForUser !== $currentIP) {
                // User already logged in from different IP - logout old session
                $this->logoutUserFromIP($existingIPForUser, $user['user_id']);
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::info("User logged out from old IP due to new login", [
                        'user_id' => $user['user_id'],
                        'old_ip' => $existingIPForUser,
                        'new_ip' => $currentIP
                    ]);
                }
            }
            // Get role_id from user (new system) or convert from role_code (old system)
            $roleId = $user['role_id'] ?? null;
            $rawRole = $user['role'] ?? '';
            $normalizedRole = $this->roleMapper->normalizeRole($rawRole);
            
            // CRITICAL: Validate role before proceeding with login
            // This prevents invalid roles like "SUBDOMAIN" from being set in session
            if (empty($normalizedRole)) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning("AuthenticationService: Login rejected - empty role", [
                        'user_id' => $user['user_id'] ?? 'unknown',
                        'raw_role' => $rawRole
                    ]);
                }
                return false;
            }
            
            // Validate role using RoleMapper
            if (!$this->roleMapper->isValidRole($normalizedRole)) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning("AuthenticationService: Login rejected - invalid role", [
                        'user_id' => $user['user_id'] ?? 'unknown',
                        'raw_role' => $rawRole,
                        'normalized_role' => $normalizedRole
                    ]);
                }
                return false;
            }
            
            // Also validate using RoleService to ensure role is active
            try {
                if ($this->roleService) {
                    $roleData = $this->roleService->getByRoleCode($normalizedRole);
                    if (!$roleData || (isset($roleData['is_active']) && !$roleData['is_active'])) {
                        if (class_exists('\App\Core\Logger')) {
                            \App\Core\Logger::warning("AuthenticationService: Login rejected - role is inactive", [
                                'user_id' => $user['user_id'] ?? 'unknown',
                                'normalized_role' => $normalizedRole
                            ]);
                        }
                        return false;
                    }
                    // If role is valid and active, get role_id
                    if (isset($roleData['role_id'])) {
                        $roleId = $roleData['role_id'];
                    }
                } else {
                    require_once __DIR__ . '/../core/DependencyFactory.php';
                    $roleService = \App\Core\DependencyFactory::getRoleService();
                    $roleData = $roleService->getByRoleCode($normalizedRole);
                    if (!$roleData || (isset($roleData['is_active']) && !$roleData['is_active'])) {
                        if (class_exists('\App\Core\Logger')) {
                            \App\Core\Logger::warning("AuthenticationService: Login rejected - role is inactive", [
                                'user_id' => $user['user_id'] ?? 'unknown',
                                'normalized_role' => $normalizedRole
                            ]);
                        }
                        return false;
                    }
                    if ($roleData && isset($roleData['role_id'])) {
                        $roleId = $roleData['role_id'];
                    }
                }
            } catch (\Exception $e) {
                error_log("AuthenticationService: Failed to validate role with RoleService: " . $e->getMessage());
                // Don't fail login if RoleService fails, but log the error
            }
            
            // Fallback: Try RoleMapper if role_id still not found
            if (!$roleId) {
                try {
                    require_once __DIR__ . '/RoleMapper.php';
                    $roleMapper = \App\Services\RoleMapper::getInstance();
                    $mappedRoleId = $roleMapper->getRoleId($normalizedRole);
                    if ($mappedRoleId) {
                        $roleId = $mappedRoleId;
                        if (class_exists('\App\Core\Logger')) {
                            \App\Core\Logger::debug("AuthenticationService: Using RoleMapper fallback for {$normalizedRole} -> {$roleId}");
                        }
                    }
                } catch (\Exception $mapperException) {
                    error_log("AuthenticationService: RoleMapper fallback failed: " . $mapperException->getMessage());
                }
            }
            
            // Ensure role_id is set - if still null, reject login
            if (!$roleId) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning("AuthenticationService: Login rejected - could not determine role_id", [
                        'user_id' => $user['user_id'] ?? 'unknown',
                        'normalized_role' => $normalizedRole
                    ]);
                }
                return false;
            }

            // Regenerate session ID to prevent session fixation
            \App\Core\SessionManager::regenerateId();
            \App\Core\SessionManager::clearTenantSelectionOverflow();

            // Set session data with both role_id (new) and role (backward compatibility)
            \App\Core\SessionManager::set('user_id', $user['user_id']);
            \App\Core\SessionManager::set('username', $user['name'] ?? $user['username'] ?? '');
            \App\Core\SessionManager::set('role', $normalizedRole); // Keep for backward compatibility
            
            // ALWAYS set role_id - this is critical for permission checks
            if ($roleId) {
                \App\Core\SessionManager::set('role_id', $roleId); // New system
            } else {
                // If role_id still not found, try one more time with RoleService
                if ($this->roleService) {
                    try {
                        $roleData = $this->roleService->getByRoleCode($normalizedRole);
                        if ($roleData && isset($roleData['role_id'])) {
                            $roleId = $roleData['role_id'];
                            \App\Core\SessionManager::set('role_id', $roleId);
                        }
                    } catch (\Exception $e) {
                        error_log("AuthenticationService: Final attempt to get role_id failed: " . $e->getMessage());
                    }
                }
            }
            
            \App\Core\SessionManager::set('logged_in', true);
            \App\Core\SessionManager::set('login_time', time());
            // Mark this session as a STAFF channel login so downstream
            // surfaces (e.g. /business/dashboard plan-selection) never
            // try to treat a waiter/cashier/kitchen PIN user as the
            // business owner who may purchase packages.
            \App\Core\SessionManager::set('login_channel', 'staff_pin');
            
            // Set IP address and User-Agent in session immediately after login
            // This prevents redirect loops when redirecting to dashboard
            \App\Core\SessionManager::set('ip_address', $currentIP);
            \App\Core\SessionManager::set('user_agent', $_SERVER['HTTP_USER_AGENT'] ?? '');
            \App\Core\SessionManager::set('last_activity', time());
            
            $effectiveTenantId = !empty($userBusinessId) ? $userBusinessId : $tenantId;
            if ($effectiveTenantId) {
                \App\Core\SessionManager::setTenantSession($effectiveTenantId);
            }
            
            // Store IP -> User ID mapping in Redis for session management
            $this->setIPUserMapping($currentIP, $user['user_id']);
            
            // Store PIN -> IP mapping in Redis for PIN-based session control
            $this->setPinIPMapping($pin, $currentIP);
            
            // Store User ID -> PIN mapping for logout cleanup
            $this->setUserIdPinMapping($user['user_id'], $pin);

            // Log successful authentication with detailed information
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::info("User authenticated successfully", [
                    'user_id' => $user['user_id'],
                    'username' => $user['name'] ?? $user['username'] ?? 'unknown',
                    'raw_role' => $rawRole,
                    'normalized_role' => $normalizedRole,
                    'role_id' => $roleId,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                    'login_time' => date('Y-m-d H:i:s'),
                    'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown'
                ]);
            }

            return $user;
        }

        // Log failed authentication attempt with detailed information
        if (class_exists('\App\Core\Logger')) {
            \App\Core\Logger::warning("Authentication failed: Invalid PIN", [
                'pin_length' => strlen($pin),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'attempted_at' => date('Y-m-d H:i:s'),
                'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
                'failed_attempts_count' => $_SESSION['failed_login_attempts'] ?? 0
            ]);
        }

        // Track failed login attempts for rate limiting purposes
        if (isset($_SESSION['failed_login_attempts'])) {
            $_SESSION['failed_login_attempts']++;
        } else {
            $_SESSION['failed_login_attempts'] = 1;
        }

        return false;
    }
    
    /**
     * Authenticate user with email and password (for customers)
     * 
     * @param string $email Email address
     * @param string $password Plain text password
     * @return array|false User data on success, false on failure
     */
    public function authenticateWithEmailPassword(string $email, string $password) {
        \App\Core\SessionManager::ensureSession();
        
        if (empty($email) || empty($password)) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning("Authentication failed: Empty email or password");
            }
            return false;
        }
        
        // Find user by email (email is stored in name field for customers)
        $user = $this->userRepository->findByEmail($email);
        
        if (!$user) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning("Authentication failed: User not found", [
                    'email' => $email,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
            }
            return false;
        }
        
        // Verify password (stored in pin field as hash for customers)
        $storedPasswordHash = $user['pin'] ?? '';
        if (!password_verify($password, $storedPasswordHash)) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning("Authentication failed: Invalid password", [
                    'email' => $email,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
            }
            return false;
        }
        
        // CRITICAL: Check tenant/subdomain isolation for email/password login
        $tenantId = \App\Core\TenantContext::getId();
        $userBusinessId = $user['tenant_id'] ?? null;
        
        if ($tenantId) {
            // Subdomain login - user must belong to this business
            if (empty($userBusinessId) || $userBusinessId !== $tenantId) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning("Login blocked: User does not belong to this subdomain (email auth)", [
                        'user_id' => $user['user_id'],
                        'email' => $email,
                        'user_business_id' => $userBusinessId,
                        'tenant_id' => $tenantId
                    ]);
                }
                return false;
            }
        } else {
            // Main domain login - only allow SuperAdmin/Qodmin or users without business_id
            $userRole = strtoupper($user['role'] ?? '');
            $isAdmin = in_array($userRole, ['SUPER_ADMIN', 'QODMIN', 'ROLE_SUPER_ADMIN']);
            
            if (!$isAdmin && !empty($userBusinessId)) {
                // Business user trying to login from main domain - must use subdomain
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning("Login blocked: Business user must use subdomain (email auth)", [
                        'user_id' => $user['user_id'],
                        'email' => $email,
                        'user_business_id' => $userBusinessId
                    ]);
                }
                return false;
            }
        }
        
        // Get current IP address
        $currentIP = $this->getClientIP();
        
        // Get role information
        $rawRole = $user['role'] ?? '';
        $normalizedRole = strtoupper(trim($rawRole));
        
        // Get role_id
        $roleId = $user['role_id'] ?? null;
        
        // Validate role_id with RoleService
        if (!$roleId && $this->roleService) {
            try {
                $roleData = $this->roleService->getByRoleCode($normalizedRole);
                if ($roleData && isset($roleData['role_id'])) {
                    $roleId = $roleData['role_id'];
                }
            } catch (\Exception $e) {
                error_log("AuthenticationService: Failed to validate role with RoleService: " . $e->getMessage());
            }
        }
        
        // Fallback: Try RoleMapper if role_id still not found
        if (!$roleId) {
            try {
                require_once __DIR__ . '/RoleMapper.php';
                $roleMapper = \App\Services\RoleMapper::getInstance();
                $mappedRoleId = $roleMapper->getRoleId($normalizedRole);
                if ($mappedRoleId) {
                    $roleId = $mappedRoleId;
                }
            } catch (\Exception $mapperException) {
                error_log("AuthenticationService: RoleMapper fallback failed: " . $mapperException->getMessage());
            }
        }
        
        // Ensure role_id is set
        if (!$roleId) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning("AuthenticationService: Login rejected - could not determine role_id", [
                    'user_id' => $user['user_id'] ?? 'unknown',
                    'normalized_role' => $normalizedRole
                ]);
            }
            return false;
        }
        
        // Regenerate session ID to prevent session fixation
        \App\Core\SessionManager::regenerateId();
        \App\Core\SessionManager::clearTenantSelectionOverflow();
        
        // Set session data
        \App\Core\SessionManager::set('user_id', $user['user_id']);
        \App\Core\SessionManager::set('username', $user['name'] ?? $email);
        \App\Core\SessionManager::set('role', $normalizedRole);
        \App\Core\SessionManager::set('role_id', $roleId);
        \App\Core\SessionManager::set('logged_in', true);
        \App\Core\SessionManager::set('login_time', time());
        // Email/password login identifies the account holder (business
        // owner / qodmin). Only this channel may reach package-purchase
        // surfaces.
        \App\Core\SessionManager::set('login_channel', 'email_password');
        \App\Core\SessionManager::set('ip_address', $currentIP);
        \App\Core\SessionManager::set('user_agent', $_SERVER['HTTP_USER_AGENT'] ?? '');
        \App\Core\SessionManager::set('last_activity', time());
        
        $effectiveTenantId = !empty($userBusinessId) ? $userBusinessId : $tenantId;
        if ($effectiveTenantId) {
            \App\Core\SessionManager::setTenantSession($effectiveTenantId);
        }
        
        // Store IP -> User ID mapping
        $this->setIPUserMapping($currentIP, $user['user_id']);
        
        // Log successful authentication
        if (class_exists('\App\Core\Logger')) {
            \App\Core\Logger::info("User authenticated successfully with email/password", [
                'user_id' => $user['user_id'],
                'email' => $email,
                'normalized_role' => $normalizedRole,
                'role_id' => $roleId,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'login_time' => date('Y-m-d H:i:s')
            ]);
        }
        
        return $user;
    }
    
    /**
     * Check if user is authenticated
     * 
     * @return bool True if user is logged in, false otherwise
     */
    public function isAuthenticated(): bool {
        \App\Core\SessionManager::ensureSession();
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    /**
     * Get current authenticated user
     * 
     * @return array|null User data or null if not authenticated
     */
    public function getCurrentUser(): ?array {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return null;
        }
        
        $userModel = $this->getUserModel();
        return $userModel->findByUserId($userId);
    }
    
    /**
     * Logout current user
     * Clears session data and destroys session
     */
    public function logout(): void {
        \App\Core\SessionManager::ensureSession();
        
        // Get current IP and user ID before clearing session
        $currentIP = $this->getClientIP();
        $userId = $_SESSION['user_id'] ?? null;
        
        // Clear IP mapping
        if ($currentIP && $userId) {
            $this->clearIPUserMapping($currentIP);
        }
        
        // Get PIN from Redis mapping and clear it
        if ($userId) {
            $pin = $this->getPinByUserId($userId);
            if ($pin) {
                $this->clearPinIPMapping($pin);
                $this->clearUserIdPinMapping($userId);
            }
            
            // Clear session invalidation keys for this user
            if ($currentIP) {
                $this->clearSessionInvalidation($userId, $currentIP);
            }
        }
        
        // Clear all session data
        $_SESSION = [];
        
        // Destroy session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
    }
    
    /**
     * Get client IP address
     * Same logic as SessionManager for consistency
     */
    private function getClientIP(): string {
        // Use centralized IP helper for consistency
        require_once __DIR__ . '/../core/Helpers/IPHelper.php';
        return \App\Core\Helpers\IPHelper::getClientIP();
    }
    
    /**
     * Get user ID by IP address from Redis
     */
    private function getUserIdByIP(string $ip): ?string {
        try {
            $redis = $this->getRedisConnection();
            if (!$redis) {
                return null;
            }
            
            $key = 'ip_user_mapping:' . $ip;
            $userId = $redis->get($key);
            return $userId ? (string)$userId : null;
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning("Failed to get user ID by IP: " . $e->getMessage());
            }
            return null;
        }
    }
    
    /**
     * Get IP address by user ID from Redis
     */
    private function getIPByUserId(string $userId): ?string {
        try {
            $redis = $this->getRedisConnection();
            if (!$redis) {
                return null;
            }
            
            $key = 'user_ip_mapping:' . $userId;
            $ip = $redis->get($key);
            return $ip ? (string)$ip : null;
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning("Failed to get IP by user ID: " . $e->getMessage());
            }
            return null;
        }
    }
    
    /**
     * Set IP -> User ID mapping in Redis
     */
    private function setIPUserMapping(string $ip, string $userId): void {
        try {
            $redis = $this->getRedisConnection();
            if (!$redis) {
                return;
            }
            
            // Set IP -> User ID mapping (TTL: 24 hours)
            $ipKey = 'ip_user_mapping:' . $ip;
            $redis->setex($ipKey, 86400, $userId);
            
            // Set User ID -> IP mapping (TTL: 24 hours)
            $userKey = 'user_ip_mapping:' . $userId;
            $oldIP = $redis->get($userKey);
            if ($oldIP && $oldIP !== $ip) {
                // Clear old IP mapping
                $oldIPKey = 'ip_user_mapping:' . $oldIP;
                $redis->del($oldIPKey);
            }
            $redis->setex($userKey, 86400, $ip);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning("Failed to set IP user mapping: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Clear IP -> User ID mapping from Redis
     */
    private function clearIPUserMapping(string $ip): void {
        try {
            $redis = $this->getRedisConnection();
            if (!$redis) {
                return;
            }
            
            $ipKey = 'ip_user_mapping:' . $ip;
            $userId = $redis->get($ipKey);
            
            if ($userId) {
                // Clear user -> IP mapping
                $userKey = 'user_ip_mapping:' . $userId;
                $redis->del($userKey);
            }
            
            // Clear IP -> user mapping
            $redis->del($ipKey);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning("Failed to clear IP user mapping: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Logout user from specific IP (centralized session invalidation)
     * This invalidates the session on the old device/IP
     */
    private function logoutUserFromIP(string $ip, ?string $userId = null): void {
        try {
            $redis = $this->getRedisConnection();
            if (!$redis) {
                return;
            }
            
            // Get user ID from IP mapping if not provided
            if (!$userId) {
                $ipKey = 'ip_user_mapping:' . $ip;
                $userId = $redis->get($ipKey);
                if (!$userId) {
                    return;
                }
            }
            
            // Mark session as invalidated in Redis (for SessionManager to check)
            $sessionInvalidationKey = 'session_invalidated:' . $userId . ':' . $ip;
            $redis->setex($sessionInvalidationKey, 3600, '1'); // 1 hour TTL
            
            // Clear IP and PIN mappings
            $this->clearIPUserMapping($ip);
            
            // Get PIN from user ID mapping and clear it
            $pin = $this->getPinByUserId($userId);
            if ($pin) {
                $this->clearPinIPMapping($pin);
            }
            
            // Clear user ID -> PIN mapping
            $this->clearUserIdPinMapping($userId);
            
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::info("Session invalidated for user on IP", [
                    'user_id' => $userId,
                    'ip' => $ip
                ]);
            }
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning("Failed to logout user from IP: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Clear session invalidation flag for user on IP
     */
    private function clearSessionInvalidation(string $userId, string $ip): void {
        try {
            $redis = $this->getRedisConnection();
            if (!$redis) {
                return;
            }
            
            $sessionInvalidationKey = 'session_invalidated:' . $userId . ':' . $ip;
            $redis->del($sessionInvalidationKey);
            
            // Also clear any other session invalidation keys for this user (different IPs)
            $pattern = 'session_invalidated:' . $userId . ':*';
            $keys = $redis->keys($pattern);
            if (!empty($keys)) {
                foreach ($keys as $key) {
                    $redis->del($key);
                }
            }
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::debug("Failed to clear session invalidation: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Check if session is invalidated for user on IP
     */
    public function isSessionInvalidated(string $userId, string $ip): bool {
        try {
            $redis = $this->getRedisConnection();
            if (!$redis) {
                // Redis not available - assume session is not invalidated
                return false;
            }
            
            $sessionInvalidationKey = 'session_invalidated:' . $userId . ':' . $ip;
            $invalidated = $redis->get($sessionInvalidationKey);
            return $invalidated === '1';
        } catch (\Exception $e) {
            // Log error but don't block - assume session is valid if check fails
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning("Failed to check session invalidation: " . $e->getMessage(), [
                    'user_id' => $userId,
                    'ip' => $ip
                ]);
            }
            return false;
        }
    }
    
    /**
     * Get Redis connection for IP mapping
     */
    private function getRedisConnection(): ?\Redis {
        if (!extension_loaded('redis')) {
            // Redis extension not loaded - use file-based fallback
            return null;
        }
        
        try {
            $cacheConfig = require __DIR__ . '/../config/cache.php';
            if ($cacheConfig['driver'] !== 'redis') {
                // Redis not configured - use file-based fallback
                return null;
            }
            
            $redis = new \Redis();
            $host = $cacheConfig['redis']['host'] ?? '127.0.0.1';
            $port = $cacheConfig['redis']['port'] ?? 6379;
            $password = $cacheConfig['redis']['password'] ?? null;
            $database = isset($_ENV['REDIS_RATELIMIT_DATABASE']) ? (int)$_ENV['REDIS_RATELIMIT_DATABASE'] : 2;
            $timeout = $cacheConfig['redis']['timeout'] ?? 2.5;
            
            if (!$redis->connect($host, $port, $timeout)) {
                // Connection failed - log and use file-based fallback
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::debug("Redis connection failed: Unable to connect to {$host}:{$port}");
                }
                return null;
            }
            
            if ($password !== null) {
                if (!$redis->auth($password)) {
                    // Authentication failed - log and use file-based fallback
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::warning("Redis authentication failed for database {$database}");
                    }
                    return null;
                }
            }
            
            $redis->select($database);
            return $redis;
        } catch (\Exception $e) {
            // Redis error - log and use file-based fallback
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning("Redis connection error: " . $e->getMessage());
            }
            return null;
        }
    }
    
    /**
     * Get IP address by PIN from Redis
     */
    private function getIPByPin(string $pin): ?string {
        try {
            $redis = $this->getRedisConnection();
            if (!$redis) {
                // Redis not available - return null (no mapping exists)
                return null;
            }
            
            $key = 'pin_session:' . $pin;
            $ip = $redis->get($key);
            
            // If key exists but value is empty or expired, return null
            if ($ip === false || empty($ip)) {
                return null;
            }
            
            return (string)$ip;
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning("Failed to get IP by PIN: " . $e->getMessage());
            }
            // On error, return null (assume no mapping exists)
            return null;
        }
    }
    
    /**
     * Set PIN -> IP mapping in Redis
     */
    private function setPinIPMapping(string $pin, string $ip): void {
        try {
            $redis = $this->getRedisConnection();
            if (!$redis) {
                return;
            }
            
            // Set PIN -> IP mapping (TTL: 24 hours - same as session lifetime)
            $key = 'pin_session:' . $pin;
            $redis->setex($key, 86400, $ip);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning("Failed to set PIN IP mapping: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Clear PIN -> IP mapping from Redis
     */
    private function clearPinIPMapping(string $pin): void {
        try {
            $redis = $this->getRedisConnection();
            if (!$redis) {
                return;
            }
            
            $key = 'pin_session:' . $pin;
            $redis->del($key);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning("Failed to clear PIN IP mapping: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Check if PIN has active session on different IP
     */
    private function checkPinSession(string $pin, string $currentIP): bool {
        $existingIP = $this->getIPByPin($pin);
        if ($existingIP && $existingIP !== $currentIP) {
            return false; // PIN is in use on different IP
        }
        return true; // PIN is available or same IP
    }
    
    /**
     * Set User ID -> PIN mapping in Redis
     */
    private function setUserIdPinMapping(string $userId, string $pin): void {
        try {
            $redis = $this->getRedisConnection();
            if (!$redis) {
                return;
            }
            
            // Set User ID -> PIN mapping (TTL: 24 hours)
            $key = 'user_pin_mapping:' . $userId;
            $redis->setex($key, 86400, $pin);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning("Failed to set user ID PIN mapping: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Get PIN by User ID from Redis
     */
    private function getPinByUserId(string $userId): ?string {
        try {
            $redis = $this->getRedisConnection();
            if (!$redis) {
                return null;
            }
            
            $key = 'user_pin_mapping:' . $userId;
            $pin = $redis->get($key);
            return $pin ? (string)$pin : null;
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning("Failed to get PIN by user ID: " . $e->getMessage());
            }
            return null;
        }
    }
    
    /**
     * Clear User ID -> PIN mapping from Redis
     */
    private function clearUserIdPinMapping(string $userId): void {
        try {
            $redis = $this->getRedisConnection();
            if (!$redis) {
                return;
            }
            
            $key = 'user_pin_mapping:' . $userId;
            $redis->del($key);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning("Failed to clear user ID PIN mapping: " . $e->getMessage());
            }
        }
    }
}

