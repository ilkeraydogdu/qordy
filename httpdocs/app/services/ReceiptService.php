<?php
namespace App\Services;

use App\Core\BaseService;
use App\Core\DependencyFactory;
use App\Repositories\ReceiptRepository;

class ReceiptService extends BaseService {
    private $orderService;
    private $orderItemService;
    private $settingsService;
    private $receiptRepository;
    
    public function __construct(ReceiptRepository $repository) {
        parent::__construct($repository);
        $this->receiptRepository = $repository;
        $this->orderService = DependencyFactory::getOrderService();
        $this->orderItemService = DependencyFactory::getOrderItemService();
        $this->settingsService = DependencyFactory::getSystemSettingsService();
    }
    
    public function getRepository(): ReceiptRepository {
        return $this->receiptRepository;
    }
    
    public function getReceiptData(string $receiptId): ?array {
        try {
            $receipt = $this->repository->findById($receiptId);
            if ($receipt) {
                return $this->enrichReceiptTotalsFromOrder($receipt);
            }
            // Bazı listelerde receipt_number (örn. 20260205-00005) gönderiliyor; receipt_id farklı olabiliyor
            $receipt = $this->receiptRepository->findByReceiptNumber($receiptId);
            return $receipt ? $this->enrichReceiptTotalsFromOrder($receipt) : null;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * If receipt total is 0 but order has total, use order total (fixes 0 TL display for paid orders).
     */
    private function enrichReceiptTotalsFromOrder(array $receipt): array {
        $total = floatval($receipt['total_amount'] ?? 0);
        $orderId = $receipt['order_id'] ?? '';
        if ($total > 0 || $orderId === '') {
            return $receipt;
        }
        $order = $this->orderService->getOrderById($orderId);
        if (!$order) {
            return $receipt;
        }
        $orderTotal = floatval($order['total_amount'] ?? 0);
        if ($orderTotal > 0) {
            $receipt['total_amount'] = $orderTotal;
            $receipt['_total_from_order'] = true;
        } else {
            $items = $this->orderItemService->getOrderItemsByOrder($orderId);
            $subtotal = 0;
            foreach ($items as $it) {
                $subtotal += floatval($it['price'] ?? 0) * intval($it['quantity'] ?? 1);
            }
            if ($subtotal > 0 && function_exists('calculateReceiptTotals')) {
                require_once __DIR__ . '/../helpers/receipt.php';
                $settings = $this->settingsService->getSettings() ?? [];
                $totals = calculateReceiptTotals($subtotal, is_array($settings) ? $settings : [], floatval($receipt['discount_amount'] ?? 0));
                $receipt['total_amount'] = $totals['total_amount'];
                $receipt['tax_amount'] = $totals['tax_amount'];
                $receipt['service_charge'] = $totals['service_charge'];
                $receipt['_total_from_order'] = true;
            }
        }
        return $receipt;
    }
    
    public function getDailyReceipts(string $date): array {
        return $this->receiptRepository->getDailyReceipts($date);
    }
    
    public function getReceiptsByTable(string $tableId): array {
        return $this->receiptRepository->getByTable($tableId);
    }
    
    public function getReceiptsByDateRange(string $startDate, string $endDate): array {
        return $this->receiptRepository->getByDateRange($startDate, $endDate);
    }
    
    /**
     * Liste için: Sipariş başına tek fiş döner (tercihen ödeme fişi FULL, yoksa en son fiş).
     * En yeni en üstte (created_at DESC).
     */
    public function getReceiptsByDateRangeForList(string $startDate, string $endDate): array {
        $all = $this->receiptRepository->getByDateRange($startDate, $endDate);
        $byOrder = [];
        foreach ($all as $r) {
            $oid = $r['order_id'] ?? '';
            if ($oid === '') {
                $byOrder['_no_order_' . ($r['receipt_id'] ?? uniqid())] = $r;
                continue;
            }
            if (!isset($byOrder[$oid])) {
                $byOrder[$oid] = $r;
                continue;
            }
            $existing = $byOrder[$oid];
            $existingType = $existing['receipt_type'] ?? '';
            $newType = $r['receipt_type'] ?? '';
            $existingIsFull = (strtoupper($existingType) === 'FULL');
            $newIsFull = (strtoupper($newType) === 'FULL');
            if ($newIsFull && !$existingIsFull) {
                $byOrder[$oid] = $r;
            } elseif (!$newIsFull && $existingIsFull) {
                // keep existing
            } else {
                $existingAt = strtotime($existing['created_at'] ?? '0');
                $newAt = strtotime($r['created_at'] ?? '0');
                if ($newAt > $existingAt) {
                    $byOrder[$oid] = $r;
                }
            }
        }
        $list = array_values($byOrder);
        usort($list, function ($a, $b) {
            $tA = strtotime($a['created_at'] ?? '0');
            $tB = strtotime($b['created_at'] ?? '0');
            return $tB - $tA;
        });
        return $list;
    }
    
    public function getReceiptsByDatetimeRange(string $startDatetime, string $endDatetime): array {
        return $this->receiptRepository->getByDatetimeRange($startDatetime, $endDatetime);
    }
    
    public function generateReceipt(array $receiptData): array {
        try {
            $db = DependencyFactory::getDatabase();
            $receiptId = $receiptData['receipt_id'] ?? ('RCP-' . substr(md5(uniqid(mt_rand(), true)), 0, 10));
            $orderId = $receiptData['order_id'] ?? '';
            $receiptNumber = $receiptData['receipt_number'] ?? $receiptId;
            $paymentMethod = strtoupper(trim($receiptData['payment_method'] ?? 'CASH'));
            if (!in_array($paymentMethod, ['CASH', 'CARD', 'QR', 'MIXED', 'PENDING'])) {
                $paymentMethod = 'PENDING';
            }
            $receiptType = strtoupper(trim($receiptData['receipt_type'] ?? 'FULL'));
            if (!in_array($receiptType, ['FULL', 'PARTIAL', 'PREPARATION', 'ADISYON'])) {
                $receiptType = 'FULL';
            }
            $totalAmount = floatval($receiptData['total_amount'] ?? 0);
            $taxAmount = floatval($receiptData['tax_amount'] ?? 0);
            $serviceCharge = floatval($receiptData['service_charge'] ?? 0);
            $discountAmount = floatval($receiptData['discount_amount'] ?? 0);
            $tableId = $receiptData['table_id'] ?? null;
            $createdBy = $receiptData['created_by'] ?? ($_SESSION['user_id'] ?? 'system');
            $paymentBreakdownJson = null;
            if (!empty($receiptData['payment_breakdown']) && is_array($receiptData['payment_breakdown'])) {
                $paymentBreakdownJson = json_encode([
                    'cash' => floatval($receiptData['payment_breakdown']['cash'] ?? 0),
                    'card' => floatval($receiptData['payment_breakdown']['card'] ?? 0)
                ], JSON_UNESCAPED_UNICODE);
            }
            
            // Fix 0 TL: when total is 0 but order exists, use order total or calculate from items
            if ($totalAmount <= 0 && $orderId !== '') {
                $order = $this->orderService->getOrderById($orderId);
                if ($order) {
                    $orderTotal = floatval($order['total_amount'] ?? 0);
                    if ($orderTotal > 0) {
                        $totalAmount = $orderTotal;
                    } else {
                        $items = $this->orderItemService->getOrderItemsByOrder($orderId);
                        $subtotal = 0;
                        foreach ($items as $it) {
                            $subtotal += floatval($it['price'] ?? 0) * intval($it['quantity'] ?? 1);
                        }
                        if ($subtotal > 0 && function_exists('calculateReceiptTotals')) {
                            require_once __DIR__ . '/../helpers/receipt.php';
                            $settings = $this->settingsService->getSettings() ?? [];
                            $totals = calculateReceiptTotals($subtotal, is_array($settings) ? $settings : [], $discountAmount);
                            $totalAmount = $totals['total_amount'];
                            $taxAmount = $totals['tax_amount'];
                            $serviceCharge = $totals['service_charge'];
                        }
                    }
                }
            }
            
            $stmt = $db->prepare("INSERT INTO receipts (receipt_id, order_id, table_id, receipt_number, total_amount, tax_amount, service_charge, discount_amount, payment_method, payment_breakdown, receipt_type, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVE', ?, NOW()) ON DUPLICATE KEY UPDATE status = 'ACTIVE', total_amount = VALUES(total_amount), payment_method = VALUES(payment_method), payment_breakdown = VALUES(payment_breakdown), receipt_type = VALUES(receipt_type)");
            $stmt->execute([
                    $receiptId,
                $orderId,
                $tableId,
                $receiptNumber,
                $totalAmount,
                $taxAmount,
                $serviceCharge,
                $discountAmount,
                $paymentMethod,
                $paymentBreakdownJson,
                $receiptType,
                $createdBy
            ]);
            return ['success' => true, 'receipt_id' => $receiptId, 'receipt_number' => $receiptNumber, 'total_amount' => $totalAmount];
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), 'payment_breakdown') !== false || (int)$e->getCode() === 42) {
                $stmt = $db->prepare("INSERT INTO receipts (receipt_id, order_id, table_id, receipt_number, total_amount, tax_amount, service_charge, discount_amount, payment_method, receipt_type, status, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVE', ?, NOW()) ON DUPLICATE KEY UPDATE status = 'ACTIVE', total_amount = VALUES(total_amount), payment_method = VALUES(payment_method), receipt_type = VALUES(receipt_type)");
                $stmt->execute([
                    $receiptId, $orderId, $tableId, $receiptNumber, $totalAmount, $taxAmount, $serviceCharge, $discountAmount, $paymentMethod, $receiptType, $createdBy
                ]);
                return ['success' => true, 'receipt_id' => $receiptId, 'receipt_number' => $receiptNumber, 'total_amount' => $totalAmount];
            }
            throw $e;
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('ReceiptService::generateReceipt error', ['error' => $e->getMessage(), 'order_id' => $receiptData['order_id'] ?? '']);
            }
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function printReceipt(string $receiptId, ?string $printerId = null): array {
        try {
            $receipt = $this->getReceiptData($receiptId);
            if (!$receipt) {
                return ['success' => false, 'error' => 'Receipt not found'];
            }
            if (!empty($receipt['created_by'])) {
                $userRepo = \App\Core\DependencyFactory::getUserRepository();
                $creator = $userRepo->findById($receipt['created_by']);
                $receipt['created_by_name'] = $creator ? trim($creator['name'] ?? '') : '';
                $receipt['created_by_role'] = '';
                if ($creator && !empty($creator['role_id'])) {
                    $roleMapper = \App\Services\RoleMapper::getInstance();
                    $receipt['created_by_role'] = strtoupper((string)($roleMapper->getRoleCode($creator['role_id']) ?? ''));
                }
            }
            if (empty($receipt['created_by_name'])) {
                $receipt['created_by_name'] = '';
            }
            
            $orderId = $receipt['order_id'] ?? '';
            $printData = $receipt;
            $printData['receipt_type'] = $receipt['receipt_type'] ?? 'ADISYON';
            
            if (!empty($orderId)) {
                $items = $this->orderItemService->getOrderItemsByOrder($orderId);
                if (!empty($items)) {
                    foreach ($items as &$item) {
                        if (empty($item['item_name'])) {
                            $item['item_name'] = $item['name'] ?? $item['menu_item_name'] ?? 'Ürün';
                        }
                    }
                    unset($item);
                    if (!function_exists('groupOrderItemsForDisplay')) {
                        require_once __DIR__ . '/../helpers/functions.php';
                    }
                    $printData['items'] = function_exists('groupOrderItemsForDisplay') ? groupOrderItemsForDisplay($items) : $items;
                }
                
                $order = $this->orderService->getOrderById($orderId);
                if ($order) {
                    $printData['order_id'] = $orderId;
                    $printData['table_name'] = $order['table_name'] ?? '';
                    $printData['table_id'] = $order['table_id'] ?? $receipt['table_id'] ?? '';
                    $printData['total_amount'] = $order['total_amount'] ?? $receipt['total_amount'] ?? 0;
                    $printData['created_at'] = $order['created_at'] ?? $receipt['created_at'] ?? '';
                }
            }
            
            $db = DependencyFactory::getDatabase();
            $tenantId = \App\Core\TenantContext::getId();
            $queueId = 'q_' . substr(md5(uniqid(mt_rand(), true)), 0, 12);
            
            $stmt = $db->prepare("INSERT INTO receipt_print_queue (queue_id, receipt_id, tenant_id, screen_id, print_data, status, created_at) VALUES (?, ?, ?, ?, ?, 'PENDING', NOW())");
            $stmt->execute([
                $queueId,
                $receiptId,
                $tenantId,
                $printerId ?? 'cashier_main',
                json_encode($printData, JSON_UNESCAPED_UNICODE)
            ]);
            
            return ['success' => true, 'queue_id' => $queueId];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function reprintReceipt(string $receiptId): ?array {
        return $this->getReceiptData($receiptId);
    }
    
    public function voidReceipt(string $receiptId, string $reason = '', string $voidedBy = ''): array {
        try {
            $db = DependencyFactory::getDatabase();
            $stmt = $db->prepare("UPDATE receipts SET status = 'VOIDED', void_reason = ?, voided_by = ? WHERE receipt_id = ?");
            $stmt->execute([$reason, $voidedBy, $receiptId]);
            return ['success' => true];
            } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function generatePreparationReceipt(array $data): array {
        try {
            $db = DependencyFactory::getDatabase();
            $tenantId = \App\Core\TenantContext::getId();
            $queueId = 'q_' . substr(md5(uniqid(mt_rand(), true)), 0, 12);
            $screenId = $data['screen_id'] ?? 'kitchen_main';
            
            $printData = $data;
            $printData['receipt_type'] = $data['receipt_type'] ?? 'preparation';
            
            $stmt = $db->prepare("INSERT INTO receipt_print_queue (queue_id, receipt_id, tenant_id, screen_id, print_data, status, created_at) VALUES (?, NULL, ?, ?, ?, 'PENDING', NOW())");
            $stmt->execute([
                $queueId,
                $tenantId,
                $screenId,
                json_encode($printData, JSON_UNESCAPED_UNICODE)
            ]);
            
            return ['success' => true, 'queue_id' => $queueId];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public function addTestPrintToQueue(string $printerId, array $testData): array {
        try {
            $db = DependencyFactory::getDatabase();
            $tenantId = \App\Core\TenantContext::getId();
            $queueId = 'q_test_' . substr(md5(uniqid(mt_rand(), true)), 0, 12);
            
            $stmt = $db->prepare("INSERT INTO receipt_print_queue (queue_id, receipt_id, tenant_id, screen_id, print_data, status, created_at) VALUES (?, NULL, ?, ?, ?, 'PENDING', NOW())");
            $stmt->execute([
                $queueId,
                $tenantId,
                $printerId,
                json_encode($testData, JSON_UNESCAPED_UNICODE)
            ]);
            
            return ['success' => true, 'queue_id' => $queueId];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
