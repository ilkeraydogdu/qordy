<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\TableSessionRepository;
use App\Repositories\CustomerSessionRepository;

/**
 * QR Code Security Service
 * Handles QR code security and validation
 * 
 * @package App\Services
 */
class QRCodeSecurityService extends BaseService {
    private $tableSessionService = null;
    private $customerSessionService = null;
    private $tableService = null;

    /**
     * Constructor
     * @param TableSessionRepository $tableSessionRepository Table session repository
     * @param CustomerSessionRepository $customerSessionRepository Customer session repository
     */
    public function __construct(
        TableSessionRepository $tableSessionRepository,
        CustomerSessionRepository $customerSessionRepository
    ) {
        parent::__construct($tableSessionRepository);
        $this->customerSessionRepository = $customerSessionRepository;
    }

    /**
     * Get table session service (lazy loading)
     * @return \App\Services\TableSessionService
     */
    private function getTableSessionService() {
        if ($this->tableSessionService === null) {
            require_once __DIR__ . '/../core/DependencyFactory.php';
            $this->tableSessionService = \App\Core\DependencyFactory::getTableSessionService();
        }
        return $this->tableSessionService;
    }

    /**
     * Get table service (lazy loading)
     * @return \App\Services\TableService
     */
    private function getTableService() {
        if ($this->tableService === null) {
            require_once __DIR__ . '/../core/DependencyFactory.php';
            $this->tableService = \App\Core\DependencyFactory::getTableService();
        }
        return $this->tableService;
    }

    /**
     * Validate QR code access
     * @param string $tableId Table ID from URL
     * @param string|null $qrToken QR token from session/cookie
     * @return array|false Validation result with session data on success, false on failure
     */
    public function validateQRAccess(string $tableId, ?string $qrToken = null) {
        // Get client IP and User-Agent
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

        // SIMPLIFIED APPROACH: If tableId is valid, create/return a session
        // The customer has already scanned the QR code to get here
        
        // First, verify the table exists
        try {
            $table = $this->getTableService()->getTableById($tableId);
            if (!$table) {
                return [
                    'valid' => false,
                    'error' => 'TABLE_NOT_FOUND'
                ];
            }
        } catch (\Throwable $e) {
            // If we can't verify table, still allow access (fail-open for customer experience)
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('QRCodeSecurityService: Table verification failed, allowing access', [
                    'table_id' => $tableId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Check if customer already has a valid session for this table
        if ($qrToken) {
            try {
                $customerSession = $this->customerSessionRepository->getByQRToken($qrToken);
                
                // Accept session if it's active (even if table_id check has issues with schema)
                if ($customerSession && !empty($customerSession['customer_session_id'])) {
                    // Check if session is for this table or expired
                    $sessionTableId = $customerSession['table_id'] ?? null;
                    $isExpired = isset($customerSession['expires_at']) && strtotime($customerSession['expires_at']) <= time();
                    $isActive = isset($customerSession['is_active']) ? $customerSession['is_active'] == 1 : true;
                    
                    if (!$isExpired && $isActive && ($sessionTableId === $tableId || $sessionTableId === null)) {
                        // Valid existing session - update last activity
                        try {
                            $this->customerSessionRepository->updateLastActivity($customerSession['customer_session_id']);
                        } catch (\Throwable $e) {
                            // Non-critical
                        }
                        
                        // Ensure table_id is set in the session data
                        $customerSession['table_id'] = $tableId;
                        
                        return [
                            'valid' => true,
                            'session' => $customerSession,
                            'type' => 'existing'
                        ];
                    }
                }
            } catch (\Throwable $e) {
                // Session lookup failed - continue to create new session
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::debug('QRCodeSecurityService: Session lookup failed, will create new', [
                        'table_id' => $tableId,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        // Check capacity but DON'T block - just flag overcapacity
        $isOvercapacity = false;
        try {
            $table = $table ?? $this->getTableService()->getTableById($tableId);
            $capacity = intval($table['capacity'] ?? 0);
            if ($capacity > 0) {
                $activeCount = $this->customerSessionRepository->countActiveByTable($tableId);
                if ($activeCount >= $capacity) {
                    $isOvercapacity = true;
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::info('QRCodeSecurityService: Table overcapacity (allowed)', [
                            'table_id' => $tableId,
                            'capacity' => $capacity,
                            'active_sessions' => $activeCount
                        ]);
                    }
                }
            }
        } catch (\Throwable $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::debug('QRCodeSecurityService: Capacity check failed, allowing', [
                    'table_id' => $tableId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Create session - try DB first, fallback to virtual
        $session = $this->createPersistentSession($tableId, $ipAddress, $userAgent, $isOvercapacity);
        if (!$session) {
            $session = $this->createVirtualSession($tableId, $ipAddress, $userAgent);
        }
        
        // Flag overcapacity in session
        if ($isOvercapacity && isset($session['session'])) {
            $session['session']['is_overcapacity'] = true;
        }
        
        return $session;
    }
    
    /**
     * Create a persistent session in the database
     * @param string $tableId Table ID
     * @param string $ipAddress IP address
     * @param string $userAgent User agent
     * @param bool $isOvercapacity Whether this session exceeds table capacity
     * @return array|null Session data or null on failure
     */
    private function createPersistentSession(string $tableId, string $ipAddress, string $userAgent, bool $isOvercapacity = false): ?array {
        try {
            $sessionId = generateId('cs');
            $sessionToken = bin2hex(random_bytes(32));
            $qrToken = $this->generateQRToken($tableId, $ipAddress, $userAgent);
            
            $sessionData = [
                'customer_session_id' => $sessionId,
                'table_id' => $tableId,
                'table_session_id' => $sessionId,
                'session_token' => $sessionToken,
                'qr_token' => $qrToken,
                'customer_identifier' => $qrToken,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'expires_at' => date('Y-m-d H:i:s', time() + (24 * 60 * 60)),
                'is_active' => 1,
                'is_overcapacity' => $isOvercapacity ? 1 : 0
            ];
            
            // Add device fingerprint if feature is enabled
            try {
                $featureService = \App\Core\DependencyFactory::getFeatureService();
                if ($featureService->isEnabled('device_fingerprint')) {
                    $sessionData['device_fingerprint'] = self::generateDeviceFingerprint($ipAddress, $userAgent);
                }
            } catch (\Throwable $e) {}
            
            $result = $this->customerSessionRepository->createSession($sessionData);
            
            if ($result) {
                return [
                    'valid' => true,
                    'session' => $sessionData,
                    'type' => 'persistent'
                ];
            }
            
            return null;
        } catch (\Throwable $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::debug('QRCodeSecurityService: Failed to create persistent session, will use virtual', [
                    'table_id' => $tableId,
                    'error' => $e->getMessage()
                ]);
            }
            return null;
        }
    }
    
    /**
     * Create a virtual session for graceful degradation
     * Used when session tables don't exist or have schema issues
     * @param string $tableId Table ID
     * @param string $ipAddress IP address
     * @param string $userAgent User agent
     * @return array Virtual session data
     */
    private function createVirtualSession(string $tableId, string $ipAddress, string $userAgent): array {
        // Generate virtual session tokens (not persisted to database)
        $virtualToken = hash('sha256', $tableId . $ipAddress . $userAgent . time() . random_bytes(16));
        $sessionToken = bin2hex(random_bytes(32));
        
        return [
            'valid' => true,
            'session' => [
                'customer_session_id' => 'virtual_' . bin2hex(random_bytes(8)),
                'table_id' => $tableId,
                'session_token' => $sessionToken,
                'qr_token' => $virtualToken,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'expires_at' => date('Y-m-d H:i:s', time() + (24 * 60 * 60)),
                'is_virtual' => true
            ],
            'type' => 'virtual'
        ];
    }

    /**
     * Create customer session from QR code
     * @param string $tableId Table ID
     * @param string $tableSessionToken Table session token
     * @param string $ipAddress IP address
     * @param string $userAgent User agent
     * @return array|false Customer session data on success, false on failure
     */
    public function createCustomerSession(string $tableId, string $tableSessionToken, string $ipAddress, string $userAgent) {
        // Validate table session
        $tableSession = $this->getTableSessionService()->validateSession($tableSessionToken);
        
        if (!$tableSession || $tableSession['table_id'] !== $tableId) {
            return false;
        }

        // Generate QR token
        $qrToken = $this->generateQRToken($tableId, $ipAddress, $userAgent);
        
        // Generate customer session token
        $sessionToken = bin2hex(random_bytes(32));

        // Calculate expiry (24 hours)
        $expiresAt = date('Y-m-d H:i:s', time() + (24 * 60 * 60));

        $sessionData = [
            'customer_session_id' => generateId('cs'),
            'table_id' => $tableId,
            'session_token' => $sessionToken,
            'qr_token' => $qrToken,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'expires_at' => $expiresAt
        ];

        if ($this->customerSessionRepository->createSession($sessionData)) {
            return $sessionData;
        }

        return false;
    }

    /**
     * Validate customer session
     * @param string $sessionToken Session token
     * @param string|null $ipAddress IP address for validation
     * @return array|false Session data on success, false on failure
     */
    public function validateCustomerSession(string $sessionToken, ?string $ipAddress = null) {
        $session = $this->customerSessionRepository->getByToken($sessionToken);
        
        if (!$session) {
            return false;
        }

        // Check if expired
        if (strtotime($session['expires_at']) <= time()) {
            $this->customerSessionRepository->deleteByToken($sessionToken);
            return false;
        }

        // Optional IP validation
        if ($ipAddress && $session['ip_address'] !== $ipAddress) {
            // Log suspicious activity but don't block (IP might change on mobile)
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning("Customer session IP mismatch", [
                    'session_id' => $session['customer_session_id'],
                    'expected_ip' => $session['ip_address'],
                    'actual_ip' => $ipAddress
                ]);
            }
        }

        // Update last activity
        $this->customerSessionRepository->updateLastActivity($session['customer_session_id']);

        return $session;
    }

    /**
     * Check if direct link access is allowed
     * @param string $tableId Table ID
     * @return bool Is allowed
     */
    public function isDirectLinkAllowed(string $tableId): bool {
        // Check feature setting
        require_once __DIR__ . '/../core/DependencyFactory.php';
        $featureService = \App\Core\DependencyFactory::getFeatureService();
        
        // If QR-only mode is enabled, direct links are not allowed
        // For now, we'll allow direct links but require QR validation for full access
        return true;
    }

    /**
     * Generate QR token
     * @param string $tableId Table ID
     * @param string $ipAddress IP address
     * @param string $userAgent User agent
     * @return string QR token
     */
    private function generateQRToken(string $tableId, string $ipAddress, string $userAgent): string {
        $data = $tableId . $ipAddress . $userAgent . time() . random_bytes(16);
        return hash('sha256', $data);
    }

    /**
     * Generate a device fingerprint from IP and user agent.
     * Only used when device_fingerprint feature is enabled.
     */
    public static function generateDeviceFingerprint(string $ipAddress, string $userAgent): string {
        return hash('sha256', $ipAddress . '|' . $userAgent);
    }

    /**
     * Update customer session location
     * @param string $sessionId Customer session ID
     * @param float $latitude Latitude
     * @param float $longitude Longitude
     * @return bool Success
     */
    public function updateSessionLocation(string $sessionId, float $latitude, float $longitude): bool {
        try {
            $db = $this->customerSessionRepository->getDbConnection();
            $stmt = $db->prepare("UPDATE customer_sessions SET latitude = :lat, longitude = :lng, last_activity = NOW() WHERE customer_session_id = :id");
            return $stmt->execute(['lat' => $latitude, 'lng' => $longitude, 'id' => $sessionId]);
        } catch (\Throwable $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::debug('QRCodeSecurityService: Failed to update location', [
                    'session_id' => $sessionId,
                    'error' => $e->getMessage()
                ]);
            }
            return false;
        }
    }
    
    /**
     * Check if customer session is still active (not expired by inactivity)
     * @param string $sessionId Customer session ID
     * @param int $inactivityMinutes Minutes of inactivity before session expires
     * @return array Session status with 'active' bool and 'last_activity' timestamp
     */
    public function checkSessionActivity(string $sessionId, int $inactivityMinutes = 30): array {
        if (str_starts_with($sessionId, 'virtual_')) {
            return ['active' => true, 'has_orders' => false, 'is_virtual' => true];
        }
        
        try {
            $db = $this->customerSessionRepository->getDbConnection();
            
            $stmt = $db->prepare("SELECT * FROM customer_sessions WHERE customer_session_id = :id LIMIT 1");
            $stmt->execute(['id' => $sessionId]);
            $session = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$session) {
                return ['active' => false, 'reason' => 'SESSION_NOT_FOUND'];
            }
            
            // SESSION_ENDED: only when is_active is explicitly set to 0 (payment processed)
            if (isset($session['is_active']) && intval($session['is_active']) === 0) {
                return ['active' => false, 'reason' => 'SESSION_ENDED'];
            }
            
            // Check for active (unpaid, not cancelled) orders on this session
            $orderStmt = $db->prepare("SELECT COUNT(*) as cnt FROM orders WHERE customer_session_id = :sid AND is_paid = 0 AND status NOT IN ('CANCELLED')");
            $orderStmt->execute(['sid' => $sessionId]);
            $orderCount = intval($orderStmt->fetch(\PDO::FETCH_ASSOC)['cnt'] ?? 0);
            
            // If has orders, session is ALWAYS active (never timeout)
            if ($orderCount > 0) {
                return ['active' => true, 'has_orders' => true, 'order_count' => $orderCount];
            }
            
            // No orders: check inactivity timeout
            $lastActivity = strtotime($session['last_activity'] ?? $session['created_at'] ?? 'now');
            $inactiveSeconds = time() - $lastActivity;
            $timeoutSeconds = $inactivityMinutes * 60;
            
            if ($inactiveSeconds > $timeoutSeconds) {
                return [
                    'active' => false,
                    'reason' => 'INACTIVITY_TIMEOUT',
                    'inactive_seconds' => $inactiveSeconds,
                    'timeout_seconds' => $timeoutSeconds
                ];
            }
            
            return [
                'active' => true,
                'has_orders' => false,
                'remaining_seconds' => $timeoutSeconds - $inactiveSeconds
            ];
        } catch (\Throwable $e) {
            return ['active' => true, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Cleanup expired sessions
     * @return int Number of deleted sessions
     */
    public function cleanupExpired(): int {
        $tableDeleted = $this->repository->deleteExpired();
        $customerDeleted = $this->customerSessionRepository->deleteExpired();
        return $tableDeleted + $customerDeleted;
    }
}

