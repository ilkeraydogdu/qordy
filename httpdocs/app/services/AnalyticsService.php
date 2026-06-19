<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\OrderRepository;

class AnalyticsService extends BaseService {
    public function __construct(OrderRepository $orderRepository) {
        parent::__construct($orderRepository);
    }

    public function getAnalyticsForDate($date) {
        $sql = "SELECT 
                    COUNT(*) as total_orders,
                    SUM(total_amount) as total_revenue,
                    AVG(total_amount) as avg_order_value,
                    SUM(CASE WHEN status = 'SERVED' THEN 1 ELSE 0 END) as completed_orders
                FROM orders 
                WHERE DATE(created_at) = :date
                    AND status != 'CANCELLED'
                    AND (is_paid = 1 OR status = 'SERVED')";
        
        $dailyStats = $this->db->fetch($sql, ['date' => $date]);
        
        // Get top selling items
        $topSellingSql = "SELECT 
                            mi.name,
                            COUNT(*) as count,
                            SUM(oi.quantity) as total_quantity,
                            SUM(oi.price * oi.quantity) as revenue
                          FROM order_items oi
                          JOIN orders o ON oi.order_id = o.order_id
                          JOIN menu_items mi ON oi.menu_item_id = mi.menu_item_id
                          WHERE DATE(o.created_at) = :date
                            AND o.status != 'CANCELLED'
                            AND (o.is_paid = 1 OR o.status = 'SERVED')
                          GROUP BY mi.menu_item_id
                          ORDER BY total_quantity DESC
                          LIMIT 10";
        
        $topSelling = $this->db->fetchAll($topSellingSql, ['date' => $date]);
        
        // Get hourly sales
        $hourlySalesSql = "SELECT 
                             HOUR(created_at) as hour,
                             COUNT(*) as order_count,
                             SUM(total_amount) as revenue
                           FROM orders 
                           WHERE DATE(created_at) = :date
                             AND status != 'CANCELLED'
                             AND (is_paid = 1 OR status = 'SERVED')
                           GROUP BY HOUR(created_at)
                           ORDER BY hour";
        
        $hourlySales = $this->db->fetchAll($hourlySalesSql, ['date' => $date]);
        
        // Get category sales
        $categorySalesSql = "SELECT 
                               c.name as category_name,
                               COUNT(*) as item_count,
                               SUM(oi.quantity) as total_quantity,
                               SUM(oi.price * oi.quantity) as revenue
                             FROM order_items oi
                             JOIN orders o ON oi.order_id = o.order_id
                             JOIN menu_items mi ON oi.menu_item_id = mi.menu_item_id
                             JOIN categories c ON mi.category_id = c.category_id
                             WHERE DATE(o.created_at) = :date
                               AND o.status != 'CANCELLED'
                               AND (o.is_paid = 1 OR o.status = 'SERVED')
                             GROUP BY c.category_id
                             ORDER BY revenue DESC";
        
        $categorySales = $this->db->fetchAll($categorySalesSql, ['date' => $date]);
        
        return [
            'date' => $date,
            'daily_stats' => $dailyStats,
            'top_selling_items' => $topSelling,
            'hourly_sales' => $hourlySales,
            'category_sales' => $categorySales
        ];
    }

    public function getWeeklyAnalytics($startDate, $endDate) {
        $sql = "SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as total_orders,
                    SUM(total_amount) as total_revenue,
                    AVG(total_amount) as avg_order_value
                FROM orders 
                WHERE created_at BETWEEN :start_date AND :end_date
                    AND status != 'CANCELLED'
                    AND (is_paid = 1 OR status = 'SERVED')
                GROUP BY DATE(created_at)
                ORDER BY date";
        
        return $this->db->fetchAll($sql, [
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);
    }

    public function getMonthlyAnalytics($month, $year) {
        $sql = "SELECT 
                    DAY(created_at) as day,
                    COUNT(*) as total_orders,
                    SUM(total_amount) as total_revenue,
                    AVG(total_amount) as avg_order_value
                FROM orders 
                WHERE MONTH(created_at) = :month AND YEAR(created_at) = :year
                    AND status != 'CANCELLED'
                    AND (is_paid = 1 OR status = 'SERVED')
                GROUP BY DAY(created_at)
                ORDER BY day";
        
        return $this->db->fetchAll($sql, [
            'month' => $month,
            'year' => $year
        ]);
    }

    public function getYearlyAnalytics($year) {
        $sql = "SELECT 
                    MONTH(created_at) as month,
                    COUNT(*) as total_orders,
                    SUM(total_amount) as total_revenue,
                    AVG(total_amount) as avg_order_value
                FROM orders 
                WHERE YEAR(created_at) = :year
                    AND status != 'CANCELLED'
                    AND (is_paid = 1 OR status = 'SERVED')
                GROUP BY MONTH(created_at)
                ORDER BY month";
        
        return $this->db->fetchAll($sql, ['year' => $year]);
    }

    public function getTablePerformance($tableId, $date) {
        $sql = "SELECT 
                    COUNT(*) as total_orders,
                    SUM(total_amount) as total_revenue,
                    AVG(total_amount) as avg_order_value,
                    MIN(created_at) as first_order_time,
                    MAX(created_at) as last_order_time
                FROM orders 
                WHERE table_id = :table_id AND DATE(created_at) = :date
                    AND status != 'CANCELLED'
                    AND (is_paid = 1 OR status = 'SERVED')";
        
        return $this->db->fetch($sql, [
            'table_id' => $tableId,
            'date' => $date
        ]);
    }

    public function getStaffPerformance($staffId, $date) {
        $sql = "SELECT 
                    COUNT(*) as total_orders,
                    SUM(total_amount) as total_revenue,
                    AVG(total_amount) as avg_order_value
                FROM orders 
                WHERE created_by = :staff_id AND DATE(created_at) = :date
                    AND status != 'CANCELLED'
                    AND (is_paid = 1 OR status = 'SERVED')";
        
        return $this->db->fetch($sql, [
            'staff_id' => $staffId,
            'date' => $date
        ]);
    }
}
