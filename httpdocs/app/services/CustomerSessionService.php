<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\CustomerSessionRepository;

/**
 * Customer Session Service
 * Handles customer session management
 * 
 * @package App\Services
 */
class CustomerSessionService extends BaseService {
    /**
     * Constructor
     * @param CustomerSessionRepository $repository Customer session repository
     */
    public function __construct(CustomerSessionRepository $repository) {
        parent::__construct($repository);
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

        // Update last activity
        $this->repository->updateLastActivity($session['customer_session_id']);

        return $session;
    }

    /**
     * Count active sessions for a table
     * @param string $tableId Table ID
     * @return int Active session count
     */
    public function countActiveSessionsByTable(string $tableId): int {
        return $this->repository->countActiveByTable($tableId);
    }

    /**
     * End all customer sessions for a table (soft delete - preserves history)
     * @param string $tableId Table ID
     * @return bool Success
     */
    public function clearSessionsByTable(string $tableId): bool {
        return $this->repository->deactivateByTable($tableId);
    }

    /**
     * Extend session expiry
     * @param string $sessionId Session ID
     * @param int $additionalMinutes Additional minutes
     * @return bool Success
     */
    public function extendSession(string $sessionId, int $additionalMinutes = 60): bool {
        $session = $this->repository->findById($sessionId);
        
        if (!$session) {
            return false;
        }

        $newExpiry = date('Y-m-d H:i:s', strtotime($session['expires_at']) + ($additionalMinutes * 60));
        
        $sql = "UPDATE {$this->repository->getTableName()} SET expires_at = :expires_at, last_activity = NOW() WHERE {$this->repository->getPrimaryKey()} = :id";
        return $this->repository->execute($sql, ['id' => $sessionId, 'expires_at' => $newExpiry]);
    }
}

