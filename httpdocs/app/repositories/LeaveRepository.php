<?php
namespace App\Repositories;

use App\Core\BaseRepository;
use PDO;

/**
 * Leave Repository
 * Handles database operations for leaves
 * 
 * @package App\Repositories
 */
class LeaveRepository extends BaseRepository {
    protected $table = 'leaves';
    protected $primaryKey = 'leave_id';

    /**
     * Find leave by ID with related data
     * @param string $leaveId Leave ID
     * @return array|null Leave data or null
     */
    public function findById($leaveId) {
        try {
            // Check if table exists first
            $sanitizedTable = preg_replace('/[^a-zA-Z0-9_]/', '', $this->table);
            $stmt = $this->db->prepare("SHOW TABLES LIKE :table");
            $stmt->execute(['table' => $sanitizedTable]);
            if ($stmt->rowCount() === 0) {
                return null;
            }
            
            $sql = "SELECT l.*, u.name as staff_name, lt.type_name as leave_type_name, 
                    lt.type_code as leave_type_code, lt.is_paid, a.name as approved_by_name
                    FROM {$this->table} l
                    LEFT JOIN users u ON l.user_id = u.user_id
                    LEFT JOIN leave_types lt ON l.leave_type_id = lt.leave_type_id
                    LEFT JOIN users a ON l.approved_by = a.user_id
                    WHERE l.leave_id = :leave_id LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['leave_id' => $leaveId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\PDOException $e) {
            error_log("LeaveRepository::findById error: " . $e->getMessage());
            return null;
        } catch (\Exception $e) {
            error_log("LeaveRepository::findById error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get leaves by user ID
     * @param string $userId User ID
     * @return array Leaves
     */
    public function getByUserId($userId) {
        try {
            // Check if table exists first
            $sanitizedTable = preg_replace('/[^a-zA-Z0-9_]/', '', $this->table);
            $stmt = $this->db->prepare("SHOW TABLES LIKE :table");
            $stmt->execute(['table' => $sanitizedTable]);
            if ($stmt->rowCount() === 0) {
                // Table doesn't exist, return empty array
                return [];
            }
            
            $sql = "SELECT l.*, lt.type_name as leave_type_name, lt.type_code as leave_type_code,
                    lt.is_paid, a.name as approved_by_name
                    FROM {$this->table} l
                    LEFT JOIN leave_types lt ON l.leave_type_id = lt.leave_type_id
                    LEFT JOIN users a ON l.approved_by = a.user_id
                    WHERE l.user_id = :user_id
                    ORDER BY l.start_date DESC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            // Table doesn't exist or other database error, return empty array
            error_log("LeaveRepository::getByUserId error: " . $e->getMessage());
            return [];
        } catch (\Exception $e) {
            error_log("LeaveRepository::getByUserId error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get leaves by date range
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return array Leaves
     */
    public function getByDateRange($startDate, $endDate) {
        try {
            $sql = "SELECT l.*, u.name as staff_name, lt.type_name as leave_type_name,
                    lt.type_code as leave_type_code
                    FROM {$this->table} l
                    LEFT JOIN users u ON l.user_id = u.user_id
                    LEFT JOIN leave_types lt ON l.leave_type_id = lt.leave_type_id
                    WHERE l.start_date BETWEEN :start_date AND :end_date
                       OR l.end_date BETWEEN :start_date AND :end_date
                       OR (l.start_date <= :start_date AND l.end_date >= :end_date)
                    ORDER BY l.start_date DESC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            // Handle case where table doesn't exist
            if ($e->getCode() === '42S02') {
                error_log("LeaveRepository: Table '{$this->table}' does not exist. Returning empty array.");
                return [];
            }
            error_log("LeaveRepository::getByDateRange error: " . $e->getMessage());
            return [];
        } catch (\Exception $e) {
            error_log("LeaveRepository::getByDateRange error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get leaves by status
     * @param string $status Status
     * @return array Leaves
     */
    public function getByStatus($status) {
        try {
            $sql = "SELECT l.*, u.name as staff_name, lt.type_name as leave_type_name
                    FROM {$this->table} l
                    LEFT JOIN users u ON l.user_id = u.user_id
                    LEFT JOIN leave_types lt ON l.leave_type_id = lt.leave_type_id
                    WHERE l.status = :status
                    ORDER BY l.start_date DESC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['status' => $status]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            // Handle case where table doesn't exist
            if ($e->getCode() === '42S02') {
                error_log("LeaveRepository: Table '{$this->table}' does not exist. Returning empty array.");
                return [];
            }
            error_log("LeaveRepository::getByStatus error: " . $e->getMessage());
            return [];
        } catch (\Exception $e) {
            error_log("LeaveRepository::getByStatus error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all leaves with related data
     * @return array All leaves
     */
    public function getAll() {
        try {
            $sql = "SELECT l.*, u.name as staff_name, lt.type_name as leave_type_name,
                    lt.type_code as leave_type_code, a.name as approved_by_name
                    FROM {$this->table} l
                    LEFT JOIN users u ON l.user_id = u.user_id
                    LEFT JOIN leave_types lt ON l.leave_type_id = lt.leave_type_id
                    LEFT JOIN users a ON l.approved_by = a.user_id
                    ORDER BY l.start_date DESC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            // Handle case where table doesn't exist
            if ($e->getCode() === '42S02') {
                error_log("LeaveRepository: Table '{$this->table}' does not exist. Returning empty array.");
                return [];
            }
            error_log("LeaveRepository::getAll error: " . $e->getMessage());
            return [];
        } catch (\Exception $e) {
            error_log("LeaveRepository::getAll error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Calculate total leave days for a user in a year
     * @param string $userId User ID
     * @param string $year Year (YYYY)
     * @param string|null $leaveTypeId Optional leave type ID
     * @return int Total days
     */
    public function getTotalDaysByUser($userId, $year, $leaveTypeId = null) {
        try {
            $sql = "SELECT SUM(total_days) as total FROM {$this->table}
                    WHERE user_id = :user_id
                    AND YEAR(start_date) = :year
                    AND status = 'APPROVED'";
            
            $params = ['user_id' => $userId, 'year' => $year];
            
            if ($leaveTypeId) {
                $sql .= " AND leave_type_id = :leave_type_id";
                $params['leave_type_id'] = $leaveTypeId;
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)($result['total'] ?? 0);
        } catch (\PDOException $e) {
            // Handle case where table doesn't exist (check both SQLSTATE and error code)
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();
            if ($errorCode === '42S02' || $errorCode === 1146 || strpos($errorMessage, "doesn't exist") !== false) {
                error_log("LeaveRepository: Table '{$this->table}' does not exist. Returning 0.");
                return 0;
            }
            error_log("LeaveRepository::getTotalDaysByUser error: " . $errorMessage);
            return 0;
        } catch (\Exception $e) {
            error_log("LeaveRepository::getTotalDaysByUser error: " . $e->getMessage());
            return 0;
        }
    }
}

