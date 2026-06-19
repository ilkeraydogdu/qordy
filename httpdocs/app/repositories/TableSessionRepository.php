<?php
namespace App\Repositories;

use App\Core\BaseRepository;

/**
 * Table Session Repository
 * Handles database operations for table QR code sessions
 * 
 * @package App\Repositories
 */
class TableSessionRepository extends BaseRepository {
    protected $table = 'table_sessions';
    protected $primaryKey = 'session_id';

    /**
     * Constructor
     * @param \PDO $database Database connection
     */
    public function __construct($database) {
        parent::__construct($database);
    }

    /**
     * Create a new table session
     * @param array $data Session data
     * @return bool Success
     */
    public function createSession(array $data): bool {
        return $this->create($data);
    }

    /**
     * Get session by token
     * @param string $token Session token
     * @return array|null Session data or null
     */
    public function getByToken(string $token): ?array {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE session_token = :token LIMIT 1";
            return $this->fetchOne($sql, ['token' => $token]);
        } catch (\Throwable $e) {
            // Table or column might not exist - return null gracefully
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::debug('TableSessionRepository::getByToken - DB error (table/column may not exist)', [
                    'error' => $e->getMessage()
                ]);
            }
            return null;
        }
    }

    /**
     * Get session by QR code hash
     * @param string $qrHash QR code hash
     * @return array|null Session data or null
     */
    public function getByQRHash(string $qrHash): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE qr_code_hash = :hash LIMIT 1";
        return $this->fetchOne($sql, ['hash' => $qrHash]);
    }

    /**
     * Get active session for table
     * @param string $tableId Table ID
     * @return array|null Session data or null
     */
    public function getActiveSessionByTable(string $tableId): ?array {
        try {
            $sql = "SELECT * FROM {$this->table} 
                    WHERE table_id = :table_id 
                    AND is_active = 1
                    AND (expires_at IS NULL OR expires_at > NOW())
                    ORDER BY created_at DESC LIMIT 1";
            return $this->fetchOne($sql, ['table_id' => $tableId]);
        } catch (\Throwable $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::debug('TableSessionRepository::getActiveSessionByTable - DB error', [
                    'table_id' => $tableId,
                    'error' => $e->getMessage()
                ]);
            }
            return null;
        }
    }

    /**
     * Delete expired sessions
     * @return int Number of deleted sessions
     */
    public function deleteExpired(): int {
        try {
            $sql = "DELETE FROM {$this->table} WHERE (expires_at IS NOT NULL AND expires_at <= NOW()) OR is_active = 0";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->rowCount();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Delete session by token
     * @param string $token Session token
     * @return bool Success
     */
    public function deleteByToken(string $token): bool {
        try {
            $sql = "DELETE FROM {$this->table} WHERE session_token = :token";
            return $this->execute($sql, ['token' => $token]);
        } catch (\Throwable $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::debug('TableSessionRepository::deleteByToken - DB error', [
                    'error' => $e->getMessage()
                ]);
            }
            return false;
        }
    }

    /**
     * Delete all sessions for a table
     * @param string $tableId Table ID
     * @return bool Success
     */
    public function deleteByTable(string $tableId): bool {
        try {
            $sql = "DELETE FROM {$this->table} WHERE table_id = :table_id";
            return $this->execute($sql, ['table_id' => $tableId]);
        } catch (\Throwable $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::debug('TableSessionRepository::deleteByTable - DB error', [
                    'table_id' => $tableId,
                    'error' => $e->getMessage()
                ]);
            }
            return false;
        }
    }

    /**
     * Deactivate all sessions for a table (soft delete)
     * @param string $tableId Table ID
     * @return bool Success
     */
    public function deactivateByTable(string $tableId): bool {
        try {
            $sql = "UPDATE {$this->table} SET is_active = 0, end_time = NOW(), expires_at = NOW() WHERE table_id = :table_id AND is_active = 1";
            return $this->execute($sql, ['table_id' => $tableId]);
        } catch (\Throwable $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::debug('TableSessionRepository::deactivateByTable - DB error', [
                    'table_id' => $tableId,
                    'error' => $e->getMessage()
                ]);
            }
            return false;
        }
    }
}

