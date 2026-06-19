<?php
namespace App\Repositories;

use App\Core\BaseRepository;
use PDO;

/**
 * Medical Report Repository
 * Handles database operations for medical reports
 * 
 * @package App\Repositories
 */
class MedicalReportRepository extends BaseRepository {
    protected $table = 'medical_reports';
    protected $primaryKey = 'report_id';

    /**
     * Find medical report by ID with related data
     * @param string $reportId Report ID
     * @return array|null Medical report data or null
     */
    public function findById($reportId) {
        try {
            $sql = "SELECT mr.*, u.name as staff_name
                    FROM {$this->table} mr
                    LEFT JOIN users u ON mr.user_id = u.user_id
                    WHERE mr.report_id = :report_id LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['report_id' => $reportId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\PDOException $e) {
            // Handle case where table doesn't exist (check both SQLSTATE and error code)
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();
            if ($errorCode === '42S02' || $errorCode === 1146 || strpos($errorMessage, "doesn't exist") !== false) {
                error_log("MedicalReportRepository: Table '{$this->table}' does not exist. Returning null.");
                return null;
            }
            error_log("MedicalReportRepository::findById error: " . $errorMessage);
            return null;
        }
    }

    /**
     * Get medical reports by user ID
     * @param string $userId User ID
     * @return array Medical reports
     */
    public function getByUserId($userId) {
        try {
            $sql = "SELECT * FROM {$this->table}
                    WHERE user_id = :user_id
                    ORDER BY start_date DESC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            // Handle case where table doesn't exist (check both SQLSTATE and error code)
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();
            if ($errorCode === '42S02' || $errorCode === 1146 || strpos($errorMessage, "doesn't exist") !== false) {
                error_log("MedicalReportRepository: Table '{$this->table}' does not exist. Returning empty array.");
                return [];
            }
            error_log("MedicalReportRepository::getByUserId error: " . $errorMessage);
            return [];
        }
    }

    /**
     * Get medical reports by date range
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return array Medical reports
     */
    public function getByDateRange($startDate, $endDate) {
        try {
            $sql = "SELECT mr.*, u.name as staff_name
                    FROM {$this->table} mr
                    LEFT JOIN users u ON mr.user_id = u.user_id
                    WHERE mr.start_date BETWEEN :start_date AND :end_date
                       OR mr.end_date BETWEEN :start_date AND :end_date
                       OR (mr.start_date <= :start_date AND mr.end_date >= :end_date)
                    ORDER BY mr.start_date DESC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            // Handle case where table doesn't exist
            if ($e->getCode() === '42S02') {
                error_log("MedicalReportRepository: Table '{$this->table}' does not exist. Returning empty array.");
                return [];
            }
            throw $e;
        }
    }

    /**
     * Get all medical reports with related data
     * @return array All medical reports
     */
    public function getAll() {
        try {
            $sql = "SELECT mr.*, u.name as staff_name
                    FROM {$this->table} mr
                    LEFT JOIN users u ON mr.user_id = u.user_id
                    ORDER BY mr.start_date DESC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            // Handle case where table doesn't exist
            if ($e->getCode() === '42S02') {
                error_log("MedicalReportRepository: Table '{$this->table}' does not exist. Returning empty array.");
                return [];
            }
            throw $e;
        }
    }

    /**
     * Calculate total report days for a user in a year
     * @param string $userId User ID
     * @param string $year Year (YYYY)
     * @return int Total days
     */
    public function getTotalDaysByUser($userId, $year) {
        try {
            $sql = "SELECT SUM(total_days) as total FROM {$this->table}
                    WHERE user_id = :user_id
                    AND YEAR(start_date) = :year";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId, 'year' => $year]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)($result['total'] ?? 0);
        } catch (\PDOException $e) {
            // Handle case where table doesn't exist (check both SQLSTATE and error code)
            $errorCode = $e->getCode();
            $errorMessage = $e->getMessage();
            if ($errorCode === '42S02' || $errorCode === 1146 || strpos($errorMessage, "doesn't exist") !== false) {
                error_log("MedicalReportRepository: Table '{$this->table}' does not exist. Returning 0.");
                return 0;
            }
            error_log("MedicalReportRepository::getTotalDaysByUser error: " . $errorMessage);
            return 0;
        }
    }
}

