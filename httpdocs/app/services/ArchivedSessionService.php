<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\ArchivedSessionRepository;

/**
 * Archived Session Service
 * Handles archived session-related business logic
 * 
 * @package App\Services
 */
class ArchivedSessionService extends BaseService {
    
    /**
     * Constructor
     * @param ArchivedSessionRepository $repository Archived session repository instance
     */
    public function __construct(ArchivedSessionRepository $repository) {
        parent::__construct($repository);
    }
    
    /**
     * Get all archived sessions
     * @return array All archived sessions
     */
    public function getAll(): array {
        return $this->repository->getAll();
    }
    
    /**
     * Get archived session by ID
     * @param string $sessionId Session ID
     * @return array|null Session data or null
     */
    public function getById(string $sessionId): ?array {
        return $this->repository->getById($sessionId);
    }
    
    /**
     * Create a new archived session
     * @param array $data Session data
     * @return bool|string Session ID on success, false on failure
     */
    public function createSession(array $data) {
        if (empty($data['session_id'])) {
            $data['session_id'] = generateId('sess');
        }
        return $this->repository->create($data);
    }
    
    /**
     * Update archived session
     * @param string $sessionId Session ID
     * @param array $data Session data to update
     * @return bool Success
     */
    public function updateSession(string $sessionId, array $data): bool {
        return $this->repository->update($sessionId, $data);
    }
    
    /**
     * Delete archived session
     * @param string $sessionId Session ID
     * @return bool Success
     */
    public function deleteSession(string $sessionId): bool {
        return $this->repository->delete($sessionId);
    }
    
    /**
     * Get archived sessions by table
     * @param string $tableId Table ID
     * @return array Archived sessions
     */
    public function getByTable(string $tableId): array {
        return $this->repository->getByTable($tableId);
    }
    
    /**
     * Get archived sessions by date range
     * @param string $startDate Start date (Y-m-d H:i:s)
     * @param string $endDate End date (Y-m-d H:i:s)
     * @return array Archived sessions
     */
    public function getByDateRange(string $startDate, string $endDate): array {
        return $this->repository->getByDateRange($startDate, $endDate);
    }
    
    /**
     * Get daily revenue from archived sessions
     * @param string $date Date (Y-m-d)
     * @return float Daily revenue
     */
    public function getDailyRevenue(string $date): float {
        return $this->repository->getDailyRevenue($date);
    }
    
    /**
     * Get daily tips from archived sessions
     * @param string $date Date (Y-m-d)
     * @return float Daily tips
     */
    public function getDailyTips(string $date): float {
        return $this->repository->getDailyTips($date);
    }
    
    /**
     * Get monthly revenue from archived sessions
     * @param int $year Year
     * @param int $month Month (1-12)
     * @return float Monthly revenue
     */
    public function getMonthlyRevenue(int $year, int $month): float {
        return $this->repository->getMonthlyRevenue($year, $month);
    }
    
    /**
     * Get top earning tables
     * @param int $limit Number of results to return
     * @return array Top earning tables
     */
    public function getTopEarningTables(int $limit = 5): array {
        return $this->repository->getTopEarningTables($limit);
    }
    
    /**
     * Get average session duration
     * @return float Average duration in minutes
     */
    public function getAverageSessionDuration(): float {
        return $this->repository->getAverageSessionDuration();
    }
    
    /**
     * Get table daily history
     * @param string $tableId Table ID
     * @param string $date Date (Y-m-d)
     * @return array Daily history sessions
     */
    public function getTableDailyHistory(string $tableId, string $date): array {
        $sessions = $this->repository->getByTable($tableId);
        
        // Filter by date
        $dailySessions = [];
        foreach ($sessions as $session) {
            $sessionDate = date('Y-m-d', strtotime($session['start_time'] ?? ''));
            if ($sessionDate === $date) {
                $dailySessions[] = $session;
            }
        }
        
        return $dailySessions;
    }
    
    /**
     * Get detailed session information
     * @param string $sessionId Session ID
     * @return array|null Detailed session data or null
     */
    public function getTableDetailedSession(string $sessionId): ?array {
        $session = $this->repository->getById($sessionId);
        if (!$session) {
            return null;
        }
        
        // Get orders for this session (by table and time range)
        $orderService = \App\Core\DependencyFactory::getOrderService();
        $receiptService = \App\Core\DependencyFactory::getReceiptService();
        
        $tableId = $session['table_id'] ?? '';
        $startTime = $session['start_time'] ?? '';
        $endTime = $session['end_time'] ?? date('Y-m-d H:i:s');
        
        // Get orders in this time range
        $orders = $orderService->getOrdersByTable($tableId);
        $sessionOrders = [];
        foreach ($orders as $order) {
            $orderTime = $order['created_at'] ?? '';
            if ($orderTime >= $startTime && $orderTime <= $endTime) {
                $sessionOrders[] = $order;
            }
        }
        
        // Get receipts for these orders
        $receiptIds = [];
        $receipts = [];
        if (!empty($session['receipt_ids'])) {
            $receiptIds = explode(',', $session['receipt_ids']);
            foreach ($receiptIds as $receiptId) {
                $receipt = $receiptService->getRepository()->findById(trim($receiptId));
                if ($receipt) {
                    $receipts[] = $receipt;
                }
            }
        }
        
        return [
            'session' => $session,
            'orders' => $sessionOrders,
            'receipts' => $receipts,
            'payment_breakdown' => json_decode($session['payment_breakdown'] ?? '{}', true)
        ];
    }
    
    /**
     * Get session receipts
     * @param string $sessionId Session ID
     * @return array Receipts for this session
     */
    public function getSessionReceipts(string $sessionId): array {
        $session = $this->repository->getById($sessionId);
        if (!$session || empty($session['receipt_ids'])) {
            return [];
        }
        
        $receiptService = \App\Core\DependencyFactory::getReceiptService();
        $receiptIds = explode(',', $session['receipt_ids']);
        $receipts = [];
        
        foreach ($receiptIds as $receiptId) {
            $receipt = $receiptService->getRepository()->findById(trim($receiptId));
            if ($receipt) {
                $receipts[] = $receipt;
            }
        }
        
        return $receipts;
    }
}
