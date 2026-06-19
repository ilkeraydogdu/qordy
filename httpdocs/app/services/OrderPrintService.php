<?php
namespace App\Services;

use App\Core\DependencyFactory;

/**
 * Order Print Service
 * Handles order printing (bridge app), PDF generation, and email sending
 * MC OOP architecture, no hardcode, no mock data
 */
class OrderPrintService {
    private $orderService;
    private $receiptService;
    private $orderItemService;
    private $settingsService;
    
    public function __construct() {
        $this->orderService = DependencyFactory::getOrderService();
        $this->receiptService = DependencyFactory::getReceiptService();
        $this->orderItemService = DependencyFactory::getOrderItemService();
        $this->settingsService = DependencyFactory::getSystemSettingsService();
    }
    
    /**
     * Get or create receipt for order
     * @param string $orderId Order ID
     * @param string $paymentMethod Payment method (default: CASH)
     * @return array|false Receipt data on success, false on failure
     */
    private function getOrCreateReceipt(string $orderId, string $paymentMethod = 'CASH'): array|false {
        // Check if receipt exists for this order
        $receipts = $this->receiptService->getRepository()->getByOrder($orderId);
        if (!empty($receipts) && isset($receipts[0])) {
            $receiptId = $receipts[0]['receipt_id'];
            return $this->receiptService->getReceiptData($receiptId);
        }
        
        // Create new receipt
        $receiptData = [
            'order_id' => $orderId,
            'payment_method' => $paymentMethod,
            'receipt_type' => 'FULL',
            'created_by' => $_SESSION['user_id'] ?? 'system'
        ];
        
        $receipt = $this->receiptService->generateReceipt($receiptData);
        if (!$receipt) {
            return false;
        }
        
        return $this->receiptService->getReceiptData($receipt['receipt_id']);
    }
    
    /**
     * Print order via bridge app
     * @param string $orderId Order ID
     * @param string|null $printerId Optional printer ID
     * @param string $paymentMethod Payment method (default: CASH)
     * @return array Result with success status and message
     */
    public function printOrder(string $orderId, ?string $printerId = null, string $paymentMethod = 'CASH'): array {
        // GUARD: Check order status - reject only CANCELLED and REFUNDED (SERVED orders may be printed for receipt)
        $order = $this->orderService->getOrderById($orderId);
        if (!$order) {
            return ['success' => false, 'error' => 'Sipariş bulunamadı'];
        }
        $orderStatus = strtoupper($order['status'] ?? '');
        if (in_array($orderStatus, ['CANCELLED', 'REFUNDED'])) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('OrderPrintService::printOrder - Rejected: order status is ' . $orderStatus, [
                    'order_id' => $orderId
                ]);
            }
            return ['success' => false, 'error' => "İptal veya iade edilmiş sipariş yazdırılamaz (durum: {$orderStatus})"];
        }
        
        // Get or create receipt
        $receiptData = $this->getOrCreateReceipt($orderId, $paymentMethod);
        if (!$receiptData) {
            return [
                'success' => false,
                'error' => 'Sipariş için fiş oluşturulamadı'
            ];
        }
        
        $receiptId = $receiptData['receipt']['receipt_id'];
        
        // Print receipt via bridge app (CASHIER screen - payment receipts must not go to preparation screens)
        $result = $this->receiptService->printReceipt($receiptId, $printerId);
        
        if (!empty($result['success'])) {
            return [
                'success' => true,
                'receipt_id' => $receiptId,
                'message' => 'Yazdırma kuyruğuna eklendi'
            ];
        }
        return [
            'success' => false,
            'error' => !empty($result['error']) ? $result['error'] : 'Yazdırma kuyruğuna eklenemedi'
        ];
    }
    
    /**
     * Generate order PDF data (returns receipt data for PDF generation)
     * @param string $orderId Order ID
     * @param string $paymentMethod Payment method (default: CASH)
     * @return array|false Receipt data on success, false on failure
     */
    public function generateOrderPDFData(string $orderId, string $paymentMethod = 'CASH'): array|false {
        return $this->getOrCreateReceipt($orderId, $paymentMethod);
    }
    
    /**
     * Print adisyon (order receipt without payment) for table
     * 
     * Davranış (fiş biriktirme önleme):
     * - order_id verilirse: Sadece o sipariş yazdırılır
     * - order_id verilmezse: Sadece EN SON sipariş yazdırılır (masadaki tüm siparişler DEĞİL)
     * - print_all=true verilirse: Eski davranış - masadaki tüm siparişler (admin/kasa kapanışı için)
     * 
     * @param string $tableId Table ID
     * @param string|null $printerId Optional printer ID
     * @param string|null $orderId Optional - sadece bu siparişi yazdır
     * @param bool $printAll Optional - true ise masadaki tüm siparişleri yazdır (varsayılan: false)
     * @return array Result with success status and message
     */
    public function printAdisyonForTable(string $tableId, ?string $printerId = null, ?string $orderId = null, bool $printAll = false): array {
        // Get active orders for this table
        $orders = $this->orderService->getActiveOrdersByTable($tableId);
        
        if (empty($orders)) {
            return [
                'success' => false,
                'error' => 'Bu masa için aktif sipariş bulunmamaktadır.'
            ];
        }
        
        // Filtrele: order_id varsa sadece o sipariş, print_all true ise hepsi, yoksa sadece en son sipariş
        if ($orderId) {
            $orders = array_filter($orders, fn($o) => ($o['order_id'] ?? '') === $orderId);
            if (empty($orders)) {
                return ['success' => false, 'error' => 'Belirtilen sipariş bu masada bulunamadı.'];
            }
        } elseif (!$printAll) {
            // En son sipariş (OrderRepository zaten ORDER BY created_at DESC döndürüyor)
            $orders = [reset($orders)];
        }
        
        $printedCount = 0;
        $errors = [];
        
        foreach ($orders as $order) {
            $oid = $order['order_id'] ?? '';
            if (empty($oid)) {
                continue;
            }
            
            // GUARD: Double-check order status (getActiveOrdersByTable should already filter, but be safe)
            $orderStatus = strtoupper($order['status'] ?? '');
            if (in_array($orderStatus, ['CANCELLED', 'SERVED', 'REFUNDED'])) {
                continue;
            }
            
            // Boş siparişleri atla (ürün yoksa adisyon yazdırmaya gerek yok)
            $orderItems = $this->orderItemService->getOrderItemsByOrder($oid);
            if (empty($orderItems)) {
                continue;
            }
            
            // Filter out cancelled items before checking if order has printable content
            $orderItems = array_filter($orderItems, function($item) {
                return strtoupper($item['preparation_status'] ?? $item['status'] ?? '') !== 'CANCELLED';
            });
            if (empty($orderItems)) {
                continue;
            }
            
            // Create adisyon receipt (without payment method, receipt_type = 'ADISYON')
            $receiptData = [
                'order_id' => $oid,
                'payment_method' => '', // No payment method for adisyon
                'receipt_type' => 'ADISYON', // Adisyon type
                'created_by' => $_SESSION['user_id'] ?? 'system'
            ];
            
            $receipt = $this->receiptService->generateReceipt($receiptData);
            if (!$receipt || empty($receipt['receipt_id'])) {
                $errors[] = "Sipariş {$oid} için adisyon oluşturulamadı";
                continue;
            }
            
            $receiptId = $receipt['receipt_id'];
            
            // Print adisyon via bridge app (CASHIER screen = masaüstü kasa yazıcısına gider)
            $result = $this->receiptService->printReceipt($receiptId, $printerId);
            
            if (!empty($result['success'])) {
                $printedCount++;
            } else {
                $errors[] = "Sipariş {$oid} için adisyon yazdırılamadı" . (!empty($result['error']) ? ': ' . $result['error'] : '');
            }
        }
        
        if ($printedCount > 0) {
            return [
                'success' => true,
                'message' => "{$printedCount} adisyon yazdırma kuyruğuna eklendi",
                'printed_count' => $printedCount,
                'total_orders' => count($orders),
                'errors' => $errors
            ];
        } else {
            return [
                'success' => false,
                'error' => !empty($errors) ? implode(', ', $errors) : 'Adisyon yazdırılamadı'
            ];
        }
    }
}

