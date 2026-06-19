<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\TableSessionRepository;

/**
 * Table Session Service
 * Handles table QR code session management
 * 
 * @package App\Services
 */
class TableSessionService extends BaseService {
    private $tableService = null;

    /**
     * Constructor
     * @param TableSessionRepository $repository Table session repository
     */
    public function __construct(TableSessionRepository $repository) {
        parent::__construct($repository);
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
     * Create a new table session
     * @param string $tableId Table ID
     * @param int $expiryMinutes Expiry time in minutes (default 24 hours)
     * @return array|false Session data on success, false on failure
     */
    public function createSession(string $tableId, int $expiryMinutes = 1440) {
        // Verify table exists
        $table = $this->getTableService()->getTableById($tableId);
        if (!$table) {
            return false;
        }

        // Generate session token
        $sessionToken = $this->generateSessionToken();
        
        // Generate QR code hash from table URL
        $tableUrl = $table['url'] ?? BASE_URL . '/t/' . $tableId;
        $qrCodeHash = hash('sha256', $tableUrl . $sessionToken . time());

        // Calculate expiry
        $expiresAt = date('Y-m-d H:i:s', time() + ($expiryMinutes * 60));

        $sessionData = [
            'session_id' => generateId('ts'),
            'table_id' => $tableId,
            'session_token' => $sessionToken,
            'qr_code_hash' => $qrCodeHash,
            'expires_at' => $expiresAt
        ];

        if ($this->repository->createSession($sessionData)) {
            return $sessionData;
        }

        return false;
    }

    /**
     * Validate session token
     * @param string $token Session token
     * @return array|false Session data on success, false on failure
     */
    public function validateSession(string $token) {
        $session = $this->repository->getByToken($token);
        
        if (!$session) {
            return false;
        }

        // Check if expired
        if (strtotime($session['expires_at']) <= time()) {
            $this->repository->deleteByToken($token);
            return false;
        }

        return $session;
    }

    /**
     * Validate QR code hash
     * @param string $qrHash QR code hash
     * @return array|false Session data on success, false on failure
     */
    public function validateQRHash(string $qrHash) {
        $session = $this->repository->getByQRHash($qrHash);
        
        if (!$session) {
            return false;
        }

        // Check if expired
        if (strtotime($session['expires_at']) <= time()) {
            $this->repository->deleteByToken($session['session_token']);
            return false;
        }

        return $session;
    }

    /**
     * Get active session for table
     * @param string $tableId Table ID
     * @return array|false Session data on success, false on failure
     */
    public function getActiveSession(string $tableId) {
        return $this->repository->getActiveSessionByTable($tableId);
    }

    /**
     * Delete expired sessions
     * @return int Number of deleted sessions
     */
    public function cleanupExpired(): int {
        return $this->repository->deleteExpired();
    }

    /**
     * Clear all sessions for a table
     * @param string $tableId Table ID
     * @return bool Success
     */
    public function clearSessionsByTable(string $tableId): bool {
        return $this->repository->deleteByTable($tableId);
    }

    /**
     * Generate secure session token
     * @return string Session token
     */
    private function generateSessionToken(): string {
        return bin2hex(random_bytes(32));
    }
}

