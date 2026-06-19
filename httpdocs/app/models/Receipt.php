<?php
namespace App\Models;

require_once __DIR__ . '/../core/Model.php';

class Receipt extends \App\Core\Model {
    protected $table = 'receipts';
    
    public function getAll() {
        return $this->query()
            ->select(['r.*', 'o.table_name', 'o.status as order_status'])
            ->from('receipts r')
            ->leftJoin('orders o', 'r.order_id', '=', 'o.order_id')
            ->orderBy('r.created_at', 'DESC')
            ->get();
    }
    
    public function getById($receiptId) {
        return $this->query()
            ->select(['r.*', 'o.table_name', 'o.status as order_status', 'o.customer_note'])
            ->from('receipts r')
            ->leftJoin('orders o', 'r.order_id', '=', 'o.order_id')
            ->where('r.receipt_id', $receiptId)
            ->first();
    }
    
    public function getByOrder($orderId) {
        return $this->query()
            ->where('order_id', $orderId)
            ->orderBy('created_at', 'DESC')
            ->get();
    }
    
    public function getByTable($tableId) {
        return $this->query()
            ->where('table_id', $tableId)
            ->orderBy('created_at', 'DESC')
            ->get();
    }
    
    public function getByReceiptNumber($receiptNumber) {
        return $this->query()
            ->where('receipt_number', $receiptNumber)
            ->first();
    }
    
    public function getByDateRange($startDate, $endDate) {
        return $this->query()
            ->select(['r.*', 'o.table_name'])
            ->from('receipts r')
            ->leftJoin('orders o', 'r.order_id', '=', 'o.order_id')
            ->whereBetween('r.created_at', [$startDate, $endDate])
            ->orderBy('r.created_at', 'DESC')
            ->get();
    }
    
    public function getDailyReceipts($date) {
        return $this->query()
            ->select(['r.*', 'o.table_name'])
            ->from('receipts r')
            ->leftJoin('orders o', 'r.order_id', '=', 'o.order_id')
            ->whereRaw('DATE(r.created_at) = ?', [$date])
            ->orderBy('r.created_at', 'DESC')
            ->get();
    }
    
    public function create($data) {
        return $this->query()
            ->insert($data);
    }
    
    public function updateReceipt($receiptId, $data) {
        return $this->query()
            ->where('receipt_id', $receiptId)
            ->update($data);
    }
    
    public function voidReceipt($receiptId, $reason, $voidedBy) {
        return $this->query()
            ->where('receipt_id', $receiptId)
            ->update([
                'status' => 'VOIDED',
                'void_reason' => $reason,
                'voided_at' => date('Y-m-d H:i:s'),
                'voided_by' => $voidedBy
            ]);
    }
    
    public function incrementPrintCount($receiptId) {
        $sql = "UPDATE {$this->table} SET print_count = print_count + 1 WHERE receipt_id = :receipt_id";
        return $this->rawQuery($sql, ['receipt_id' => $receiptId]);
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
}

