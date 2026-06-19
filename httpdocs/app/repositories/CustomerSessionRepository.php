<?php
namespace App\Repositories;

use App\Core\BaseRepository;

/**
 * Customer Session Repository
 * Handles database operations for customer table sessions
 * 
 * @package App\Repositories
 */
class CustomerSessionRepository extends BaseRepository {
    protected $table = 'customer_sessions';
    protected $primaryKey = 'customer_session_id';

    /**
     * Constructor
     * @param \PDO $database Database connection
     */
    public function __construct($database) {
        parent::__construct($database);
    }

    /**
     * Create a new customer session
     * @param array $data Session data
     * @return bool Success
     */
    public function createSession(array $data): bool {
        try {
            return $this->create($data);
        } catch (\Throwable $e) {
            if (isset($data['device_fingerprint']) && str_contains($e->getMessage(), 'device_fingerprint')) {
                unset($data['device_fingerprint']);
                return $this->create($data);
            }
            throw $e;
        }
    }

    /**
     * Get session by token
     * @param string $token Session token
     * @return array|null Session data or null
     */
    public function getByToken(string $token): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE session_token = :token AND expires_at > NOW() LIMIT 1";
        return $this->fetchOne($sql, ['token' => $token]);
    }

    /**
     * Get session by QR token
     * @param string $qrToken QR token
     * @return array|null Session data or null
     */
    public function getByQRToken(string $qrToken): ?array {
        try {
            // Check both qr_token and customer_identifier (for backwards compatibility)
            $sql = "SELECT * FROM {$this->table} 
                    WHERE (qr_token = :qr_token OR customer_identifier = :qr_token2)
                    AND is_active = 1 
                    AND (expires_at IS NULL OR expires_at > NOW()) 
                    LIMIT 1";
            return $this->fetchOne($sql, ['qr_token' => $qrToken, 'qr_token2' => $qrToken]);
        } catch (\Throwable $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::debug('CustomerSessionRepository::getByQRToken - DB error', [
                    'error' => $e->getMessage()
                ]);
            }
            return null;
        }
    }

    /**
     * Get active session for table
     * @param string $tableId Table ID
     * @return array|null Session data or null
     */
    public function getActiveSessionByTable(string $tableId): ?array {
        try {
            $sql = "SELECT cs.* FROM {$this->table} cs
                    LEFT JOIN table_sessions ts ON cs.table_session_id = ts.session_id
                    WHERE (cs.table_id = :table_id OR ts.table_id = :table_id2)
                    AND cs.is_active = 1 
                    AND (cs.expires_at IS NULL OR cs.expires_at > NOW()) 
                    ORDER BY cs.last_activity DESC LIMIT 1";
            return $this->fetchOne($sql, ['table_id' => $tableId, 'table_id2' => $tableId]);
        } catch (\Throwable $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::debug('CustomerSessionRepository::getActiveSessionByTable - DB error', [
                    'table_id' => $tableId,
                    'error' => $e->getMessage()
                ]);
            }
            return null;
        }
    }

    /**
     * Count active sessions for a table
     * @param string $tableId Table ID
     * @return int Active session count
     */
    public function countActiveByTable(string $tableId): int {
        try {
            $sql = "SELECT COUNT(*) as cnt FROM {$this->table} cs
                    LEFT JOIN table_sessions ts ON cs.table_session_id = ts.session_id
                    WHERE (cs.table_id = :table_id OR ts.table_id = :table_id2)
                    AND cs.is_active = 1 
                    AND (cs.expires_at IS NULL OR cs.expires_at > NOW())";
            $result = $this->fetchOne($sql, ['table_id' => $tableId, 'table_id2' => $tableId]);
            return intval($result['cnt'] ?? 0);
        } catch (\Throwable $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::debug('CustomerSessionRepository::countActiveByTable - DB error', [
                    'table_id' => $tableId,
                    'error' => $e->getMessage()
                ]);
            }
            return 0;
        }
    }

    /**
     * Update last activity
     * @param string $sessionId Session ID
     * @return bool Success
     */
    public function updateLastActivity(string $sessionId): bool {
        $sql = "UPDATE {$this->table} SET last_activity = NOW() WHERE {$this->primaryKey} = :id";
        return $this->execute($sql, ['id' => $sessionId]);
    }

    /**
     * Delete expired sessions
     * @return int Number of deleted sessions
     */
    public function deleteExpired(): int {
        $sql = "DELETE FROM {$this->table} WHERE expires_at <= NOW()";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * Delete session by token
     * @param string $token Session token
     * @return bool Success
     */
    public function deleteByToken(string $token): bool {
        $sql = "DELETE FROM {$this->table} WHERE session_token = :token";
        return $this->execute($sql, ['token' => $token]);
    }

    /**
     * Delete all sessions for a table
     * @param string $tableId Table ID
     * @return bool Success
     */
    public function deleteByTable(string $tableId): bool {
        try {
            // Delete by direct table_id or via table_sessions join
            $sql = "DELETE cs FROM {$this->table} cs
                    LEFT JOIN table_sessions ts ON cs.table_session_id = ts.session_id
                    WHERE cs.table_id = :table_id OR ts.table_id = :table_id2";
            return $this->execute($sql, ['table_id' => $tableId, 'table_id2' => $tableId]);
        } catch (\Throwable $e) {
            // Fallback to simple delete
            try {
                $sql = "DELETE FROM {$this->table} WHERE table_id = :table_id";
                return $this->execute($sql, ['table_id' => $tableId]);
            } catch (\Throwable $e2) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::debug('CustomerSessionRepository::deleteByTable - DB error', [
                        'table_id' => $tableId,
                        'error' => $e2->getMessage()
                    ]);
                }
                return false;
            }
        }
    }

    /**
     * Deactivate all sessions for a table (soft delete)
     * @param string $tableId Table ID
     * @return bool Success
     */
    public function deactivateByTable(string $tableId): bool {
        try {
            $sql = "UPDATE {$this->table} cs
                    LEFT JOIN table_sessions ts ON cs.table_session_id = ts.session_id
                    SET cs.is_active = 0, cs.expires_at = NOW()
                    WHERE (cs.table_id = :table_id OR ts.table_id = :table_id2)
                    AND cs.is_active = 1";
            return $this->execute($sql, ['table_id' => $tableId, 'table_id2' => $tableId]);
        } catch (\Throwable $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::debug('CustomerSessionRepository::deactivateByTable - DB error', [
                    'table_id' => $tableId,
                    'error' => $e->getMessage()
                ]);
            }
            return false;
        }
    }
}

