<?php
namespace App\Models;

require_once __DIR__ . '/../core/Model.php';

class Order extends \App\Core\Model {
    protected $table = 'orders';
    
    public function getAll() {
        return $this->query()
            ->orderBy('created_at', 'DESC')
            ->get();
    }
    
    public function getById($orderId) {
        return $this->query()
            ->where('order_id', $orderId)
            ->first();
    }
    
    public function getByTable($tableId) {
        return $this->query()
            ->where('table_id', $tableId)
            ->where('status', '!=', 'SERVED')
            ->where('status', '!=', 'CANCELLED')
            ->orderBy('created_at', 'DESC')
            ->get();
    }
    
    public function getByTableId($tableId) {
        return $this->query()
            ->where('table_id', $tableId)
            ->orderBy('created_at', 'DESC')
            ->get();
    }
    
    public function getByStatus($status) {
        return $this->query()
            ->where('status', $status)
            ->orderBy('created_at')
            ->get();
    }
    
    public function getByDateRange($startDate, $endDate) {
        return $this->query()
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->orderBy('created_at', 'DESC')
            ->get();
    }
    
    public function create($data) {
        return $this->query()
            ->insert($data);
    }

    /**
     * Get order items (hasMany relationship)
     * @param string $orderId
     * @return array
     */
    public function getOrderItems(string $orderId): array {
        require_once __DIR__ . '/OrderItem.php';
        $orderItemModel = new \App\Models\OrderItem();
        return $orderItemModel->getByOrder($orderId);
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
     * Get user who created the order (belongsTo relationship)
     * @param string $userId
     * @return array|null
     */
    public function getCreatedByUser(string $userId): ?array {
        require_once __DIR__ . '/User.php';
        $userModel = new \App\Models\User();
        return $userModel->findByUserId($userId);
    }

    public function updateStatus($orderId, $status) {
        $data = ['status' => $status, 'updated_at' => date('Y-m-d H:i:s')];
        return $this->query()
            ->where('order_id', $orderId)
            ->update($data);
    }

    public function updateOrder($orderId, $data) {
        return $this->query()
            ->where('order_id', $orderId)
            ->update($data);
    }

    public function deleteOrder($orderId) {
        return $this->query()
            ->where('order_id', $orderId)
            ->delete();
    }
    
    public function getForKitchen() {
        return $this->query()
            ->select(['o.*', 'oi.*', 'mi.name as item_name'])
            ->from('orders o')
            ->join('order_items oi', 'o.order_id', '=', 'oi.order_id')
            ->join('menu_items mi', 'oi.menu_item_id', '=', 'mi.menu_item_id')
            ->whereIn('o.status', ['PENDING', 'PREPARING'])
            ->orderBy('o.created_at')
            ->get();
    }
    
    public function getDailyRevenue($date) {
        $result = $this->query()
            ->whereRaw("DATE(created_at) = ?", [$date])
            ->where('status', '!=', 'CANCELLED')
            ->where('is_paid', 1)
            ->sum('total_amount');
        return (float)($result ?: 0);
    }
    
    public function getTopSellingItems($limit = 5) {
        return $this->query()
            ->select(['mi.name', 'COUNT(*) as count'])
            ->from('order_items oi')
            ->join('orders o', 'oi.order_id', '=', 'o.order_id')
            ->join('menu_items mi', 'oi.menu_item_id', '=', 'mi.menu_item_id')
            ->where('o.status', '!=', 'CANCELLED')
            ->where('o.is_paid', 1)
            ->groupBy('mi.name')
            ->orderBy('count', 'DESC')
            ->limit((int)$limit)
            ->get();
    }
    
    public function getOrderBySource($source) {
        return $this->query()
            ->where('order_source', $source)
            ->orderBy('created_at', 'DESC')
            ->get();
    }
    
    public function calculateTotalRevenue($startDate, $endDate) {
        $result = $this->query()
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('status', '!=', 'CANCELLED')
            ->where('is_paid', 1)
            ->sum('total_amount');
        return $result ?: 0;
    }
    
    public function calculateAvgOrderValue($startDate, $endDate) {
        $result = $this->query()
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('status', '!=', 'CANCELLED')
            ->where('is_paid', 1)
            ->avg('total_amount');
        return $result ?: 0;
    }
    
    public function getRevenueByCategory($startDate, $endDate) {
        return $this->query()
            ->select(['c.name as category_name', 'SUM(oi.price * oi.quantity) as revenue'])
            ->from('order_items oi')
            ->join('orders o', 'oi.order_id', '=', 'o.order_id')
            ->join('menu_items mi', 'oi.menu_item_id', '=', 'mi.menu_item_id')
            ->join('categories c', 'mi.category_id', '=', 'c.category_id')
            ->whereBetween('o.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('o.status', '!=', 'CANCELLED')
            ->where('o.is_paid', 1)
            ->groupBy('c.category_id')
            ->orderBy('revenue', 'DESC')
            ->get();
    }
    
    public function getHourlySales($startDate, $endDate) {
        return $this->query()
            ->select(['HOUR(created_at) as hour', 'COUNT(*) as order_count', 'SUM(total_amount) as revenue'])
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('status', '!=', 'CANCELLED')
            ->where('is_paid', 1)
            ->groupBy('HOUR(created_at)')
            ->orderBy('hour')
            ->get();
    }
    
    public function getDailyRevenueForChart($startDate, $endDate) {
        return $this->query()
            ->select(['DATE(created_at) as date', 'SUM(total_amount) as revenue'])
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('status', '!=', 'CANCELLED')
            ->where('is_paid', 1)
            ->groupBy('DATE(created_at)')
            ->orderBy('date')
            ->get();
    }
    
    public function getTotalSalesByShift($shiftId) {
        $result = $this->query()
            ->where('shift_id', $shiftId)
            ->where('status', '!=', 'CANCELLED')
            ->where('is_paid', 1)
            ->sum('total_amount');
        return $result ?: 0;
    }
}