<?php
namespace App\Models;

require_once __DIR__ . '/../core/Model.php';

class PaymentTransaction extends \App\Core\Model {
    protected $table = 'payment_transactions';
    
    public function getAll() {
        return $this->query()
            ->select(['pt.*', 'u.name as processed_by_name', 't.name as table_name'])
            ->from('payment_transactions pt')
            ->leftJoin('users u', 'pt.processed_by', '=', 'u.user_id')
            ->leftJoin('tables t', 'pt.table_id', '=', 't.table_id')
            ->orderBy('pt.timestamp', 'DESC')
            ->get();
    }
    
    public function getById($transactionId) {
        return $this->query()
            ->select(['pt.*', 'u.name as processed_by_name', 't.name as table_name'])
            ->from('payment_transactions pt')
            ->leftJoin('users u', 'pt.processed_by', '=', 'u.user_id')
            ->leftJoin('tables t', 'pt.table_id', '=', 't.table_id')
            ->where('pt.transaction_id', $transactionId)
            ->first();
    }
    
    public function getByTable($tableId) {
        return $this->query()
            ->from('payment_transactions')
            ->where('table_id', $tableId)
            ->orderBy('timestamp', 'DESC')
            ->get();
    }
    
    public function getByDateRange($startDate, $endDate) {
        return $this->query()
            ->select(['pt.*', 'u.name as processed_by_name', 't.name as table_name'])
            ->from('payment_transactions pt')
            ->leftJoin('users u', 'pt.processed_by', '=', 'u.user_id')
            ->leftJoin('tables t', 'pt.table_id', '=', 't.table_id')
            ->whereBetween('pt.timestamp', [$startDate, $endDate])
            ->orderBy('pt.timestamp', 'DESC')
            ->get();
    }
    
    public function getByShift($shiftId) {
        return $this->query()
            ->where('shift_id', $shiftId)
            ->orderBy('timestamp', 'DESC')
            ->get();
    }
    
    public function create($data) {
        // Use centralized mapper for payment_transactions table field filtering
        require_once __DIR__ . '/../core/DataMapper/PaymentTransactionMapper.php';
        $filteredData = \App\Core\DataMapper\PaymentTransactionMapper::filterAndMap($data);
        
        return $this->query()
            ->insert($filteredData);
    }

    public function updateTransaction($transactionId, $data) {
        return $this->query()
            ->where('transaction_id', $transactionId)
            ->update($data);
    }

    public function deleteTransaction($transactionId) {
        return $this->query()
            ->where('transaction_id', $transactionId)
            ->delete();
    }
    
    public function getDailyTotals($date) {
        $totalAmount = $this->query()
            ->whereRaw('DATE(timestamp) = ?', [$date])
            ->sum('amount');
        
        $totalTip = $this->query()
            ->whereRaw('DATE(timestamp) = ?', [$date])
            ->sum('tip');
        
        $transactionCount = $this->query()
            ->whereRaw('DATE(timestamp) = ?', [$date])
            ->count();
        
        return [
            'total_amount' => (float)($totalAmount ?: 0),
            'total_tip' => (float)($totalTip ?: 0),
            'transaction_count' => (int)$transactionCount
        ];
    }
    
    public function getMethodBreakdown($date) {
        return $this->query()
            ->select(['payment_method as method', 'COUNT(*) as count', 'SUM(amount) as total'])
            ->whereRaw('DATE(timestamp) = ?', [$date])
            ->groupBy('payment_method')
            ->get();
    }
    
    /**
     * Get order (belongsTo relationship)
     * @param string $orderId
     * @return array|null
     */
    public function getOrder(string $orderId): ?array {
        require_once __DIR__ . '/Order.php';
        $orderModel = new \App\Models\Order();
        return $orderModel->getById($orderId);
    }
    
    /**
     * Get table (belongsTo relationship)
     * @param string $tableId
     * @return array|null
     */
    public function getTable(string $tableId): ?array {
        require_once __DIR__ . '/Table.php';
        $tableModel = new \App\Models\Table();
        return $tableModel->getById($tableId);
    }
    
    /**
     * Get user who processed the transaction (belongsTo relationship)
     * @param string $userId
     * @return array|null
     */
    public function getProcessedByUser(string $userId): ?array {
        require_once __DIR__ . '/User.php';
        $userModel = new \App\Models\User();
        return $userModel->findByUserId($userId);
    }
    
    /**
     * Get shift (belongsTo relationship)
     * @param string $shiftId
     * @return array|null
     */
    public function getShift(string $shiftId): ?array {
        require_once __DIR__ . '/Shift.php';
        $shiftModel = new \App\Models\Shift();
        return $shiftModel->getById($shiftId);
    }
}