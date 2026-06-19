<?php
namespace App\Services;

use App\Core\DependencyFactory;
use App\Core\TenantContext;

/**
 * Z Report data building and print payload generation.
 * Shared by web (AnalyticsController) and mobile (MobileAPIController).
 */
class ZReportService {

    public function buildZReportData(string $date, ?string $startDatetime = null, ?string $endDatetime = null): array {
        $businessInfo = $this->getBusinessInfo();
        $orderService = DependencyFactory::getOrderService();
        $hasDatetimeRange = $startDatetime && $endDatetime;

        if ($hasDatetimeRange) {
            $orders = $orderService->getOrdersByDatetimeRange($startDatetime, $endDatetime);
        } else {
            $orders = $orderService->getOrdersByDateRange($date, $date);
        }
        $orders = is_array($orders) ? $orders : [];

        usort($orders, function($a, $b) {
            return strtotime($a['created_at'] ?? '1970-01-01') - strtotime($b['created_at'] ?? '1970-01-01');
        });

        $orderLines = [];
        $orderItemService = DependencyFactory::getOrderItemService();
        $db = DependencyFactory::getDatabase();

        $tableNameCache = [];
        try {
            $tableIds = array_unique(array_filter(array_column($orders, 'table_id')));
            if (!empty($tableIds)) {
                $placeholders = implode(',', array_fill(0, count($tableIds), '?'));
                $stmt = $db->prepare("SELECT table_id, name FROM tables WHERE table_id IN ($placeholders)");
                $stmt->execute(array_values($tableIds));
                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                    $tableNameCache[$row['table_id']] = $row['name'];
                }
            }
        } catch (\Exception $e) {}

        $waiterNameCache = [];
        try {
            $createdByIds = array_unique(array_filter(array_column($orders, 'created_by')));
            if (!empty($createdByIds)) {
                $placeholders = implode(',', array_fill(0, count($createdByIds), '?'));
                $stmt = $db->prepare("SELECT user_id, name FROM users WHERE user_id IN ($placeholders)");
                $stmt->execute(array_values($createdByIds));
                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                    if (!empty($row['name']) && trim($row['name']) !== '') {
                        $waiterNameCache[$row['user_id']] = trim($row['name']);
                    }
                }
            }
        } catch (\Exception $e) {}

        $allOrderIds = array_filter(array_column($orders, 'order_id'));
        $orderItemsCache = [];
        if (!empty($allOrderIds)) {
            foreach ($allOrderIds as $oid) {
                $orderItemsCache[$oid] = $orderItemService->getOrderItemsByOrder($oid);
            }
        }

        $productBreakdown = [];
        $categoryBreakdown = [];
        $discountTotal = 0;

        $paymentBreakdown = [
            'cash' => ['count' => 0, 'total' => 0],
            'card' => ['count' => 0, 'total' => 0],
            'online' => ['count' => 0, 'total' => 0]
        ];

        $completedOrders = 0;
        $cancelledOrders = 0;
        $pendingOrders = 0;
        $cancelledRevenue = 0;
        $paidRevenue = 0;
        $paidOrderCount = 0;

        foreach ($orders as $order) {
            $status = strtoupper($order['status'] ?? 'PENDING');
            $amount = floatval($order['total_amount'] ?? 0);
            $orderId = $order['order_id'] ?? '';

            $orderIsPaidFlag = !empty($order['is_paid']) && ($order['is_paid'] == 1 || $order['is_paid'] === '1');
            $orderPaymentMethod = strtoupper(trim($order['payment_method'] ?? ''));
            $orderHasPayment = !empty($orderPaymentMethod) && !in_array($orderPaymentMethod, ['PENDING', '-', '']);
            $isPaid = $orderIsPaidFlag || $orderHasPayment;

            if (in_array($status, ['SERVED', 'READY', 'DELIVERED', 'ON_DELIVERY'])) {
                $completedOrders++;
            } elseif ($status === 'CANCELLED') {
                $cancelledOrders++;
                $cancelledRevenue += $amount;
            } else {
                $pendingOrders++;
            }

            if (!$isPaid || $status === 'CANCELLED' || $amount <= 0) {
                continue;
            }

            $items = $orderItemsCache[$orderId] ?? [];

            $paidRevenue += $amount;
            $paidOrderCount++;
            $discountTotal += floatval($order['discount_amount'] ?? 0);

            $itemNames = [];
            foreach ($items as $item) {
                $qty = $item['quantity'] ?? 1;
                $name = $item['item_name'] ?? $item['name'] ?? $item['menu_item_name'] ?? '?';
                $itemNames[] = $qty . 'x' . mb_substr($name, 0, 18);
            }

            $tableId = $order['table_id'] ?? '';
            $tableName = $order['table_name'] ?? ($tableNameCache[$tableId] ?? '-');
            $createdBy = $order['created_by'] ?? '';
            $waiterName = $waiterNameCache[$createdBy] ?? ($createdBy ?: 'QR');

            $orderLines[] = [
                'short_id' => $orderId,
                'order_id' => $orderId,
                'time' => date('H:i', strtotime($order['created_at'] ?? 'now')),
                'table' => $tableName,
                'waiter' => $waiterName,
                'items_text' => implode(', ', $itemNames),
                'item_count' => count($items),
                'amount' => $amount,
                'status' => $status,
                'payment_method' => $order['payment_method'] ?? '-'
            ];

            foreach ($items as $item) {
                $itemName = $item['item_name'] ?? $item['name'] ?? $item['menu_item_name'] ?? '?';
                $qty = intval($item['quantity'] ?? 1);
                $unitPrice = floatval($item['price'] ?? 0);
                $itemTotal = $unitPrice * $qty;
                $category = $item['category_name'] ?? $item['category'] ?? 'Kategorisiz';

                $key = $itemName . '|' . number_format($unitPrice, 2, '.', '');
                if (!isset($productBreakdown[$key])) {
                    $productBreakdown[$key] = [
                        'name' => $itemName,
                        'category' => $category,
                        'unit_price' => $unitPrice,
                        'quantity' => 0,
                        'total' => 0
                    ];
                }
                $productBreakdown[$key]['quantity'] += $qty;
                $productBreakdown[$key]['total'] += $itemTotal;

                if (!isset($categoryBreakdown[$category])) {
                    $categoryBreakdown[$category] = ['quantity' => 0, 'total' => 0];
                }
                $categoryBreakdown[$category]['quantity'] += $qty;
                $categoryBreakdown[$category]['total'] += $itemTotal;
            }
        }

        $totalOrders = count($orders);

        usort($productBreakdown, function($a, $b) {
            return $b['quantity'] - $a['quantity'];
        });
        uasort($categoryBreakdown, function($a, $b) {
            return $b['total'] <=> $a['total'];
        });

        $tenantId = TenantContext::getId();
        try {
            $txParams = [];
            if ($hasDatetimeRange) {
                $txSql = "SELECT method, amount FROM payment_transactions WHERE created_at BETWEEN ? AND ?";
                $txParams = [$startDatetime, $endDatetime];
            } else {
                $txSql = "SELECT method, amount FROM payment_transactions WHERE DATE(created_at) = ?";
                $txParams = [$date];
            }
            if ($tenantId) {
                $txSql .= " AND tenant_id = ?";
                $txParams[] = $tenantId;
            }
            $txStmt = $db->prepare($txSql);
            $txStmt->execute($txParams);
            $transactions = $txStmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($transactions as $tx) {
                $method = strtoupper($tx['method'] ?? 'CASH');
                $txAmount = floatval($tx['amount'] ?? 0);

                if (in_array($method, ['CREDIT_CARD', 'CARD'])) {
                    $paymentBreakdown['card']['count']++;
                    $paymentBreakdown['card']['total'] += $txAmount;
                } elseif (in_array($method, ['ONLINE_PAYMENT', 'ONLINE'])) {
                    $paymentBreakdown['online']['count']++;
                    $paymentBreakdown['online']['total'] += $txAmount;
                } else {
                    $paymentBreakdown['cash']['count']++;
                    $paymentBreakdown['cash']['total'] += $txAmount;
                }
            }
        } catch (\Exception $e) {}

        $txTotal = $paymentBreakdown['cash']['total'] + $paymentBreakdown['card']['total'] + $paymentBreakdown['online']['total'];
        if ($paidRevenue > 0 && abs($txTotal - $paidRevenue) > 0.01) {
            $gap = $paidRevenue - $txTotal;
            $paymentBreakdown['cash']['total'] += $gap;
            if ($gap > 0) {
                $paymentBreakdown['cash']['count'] = max($paymentBreakdown['cash']['count'], 1);
            }
        }

        $orderLinesTotal = 0;
        foreach ($orderLines as $line) {
            $orderLinesTotal += $line['amount'];
        }

        $avgOrderValue = $paidOrderCount > 0 ? $paidRevenue / $paidOrderCount : 0;
        $zNumber = 'Z' . date('Ymd', strtotime($date)) . '-001';

        return [
            'business' => $businessInfo,
            'date' => $date,
            'z_number' => $zNumber,
            'report_time' => date('Y-m-d H:i:s'),
            'order_lines' => $orderLines,
            'product_breakdown' => array_values($productBreakdown),
            'category_breakdown' => $categoryBreakdown,
            'totals' => [
                'gross_revenue' => $paidRevenue,
                'order_lines_total' => $orderLinesTotal,
                'total_orders' => $totalOrders,
                'completed_orders' => $completedOrders,
                'cancelled_orders' => $cancelledOrders,
                'cancelled_revenue' => $cancelledRevenue,
                'pending_orders' => $pendingOrders,
                'avg_order' => $avgOrderValue,
                'paid_count' => $paidOrderCount,
                'tx_count' => $paymentBreakdown['cash']['count'] + $paymentBreakdown['card']['count'] + $paymentBreakdown['online']['count']
            ],
            'payment_breakdown' => $paymentBreakdown,
            'discount_total' => $discountTotal,
            'service_charge_total' => 0,
            'tip_total' => 0
        ];
    }

    public function getBusinessInfo(): array {
        $tenantId = TenantContext::getId();
        $businessName = '';
        $taxNumber = '';
        $address = '';
        $phone = '';

        if ($tenantId) {
            try {
                $customerService = DependencyFactory::getCustomerService();
                $customer = $customerService->getById($tenantId);
                if ($customer) {
                    $businessName = $customer['company_name'] ?? $customer['business_name'] ?? '';
                    $taxNumber = $customer['tax_number'] ?? $customer['vkn'] ?? '';
                    $address = $customer['address'] ?? '';
                    $phone = $customer['phone'] ?? '';
                }
            } catch (\Exception $e) {}
        }

        if (empty($businessName)) {
            $businessName = function_exists('getAppConfig') ? (getAppConfig()->getAppName() ?? 'Qordy') : 'Qordy';
        }

        return [
            'name' => $businessName,
            'tax_number' => $taxNumber ?: '-',
            'address' => $address ?: '-',
            'phone' => $phone ?: '-'
        ];
    }

    /**
     * Build print payload for receipt_print_queue (thermal printer).
     */
    public function getPrintPayload(array $reportData): array {
        return [
            'receipt_type' => 'z_report',
            'receipt_type_override' => 'z_report',
            'type' => 'z_report',
            'order_id' => 'ZRPT-' . date('Ymd', strtotime($reportData['date'] ?? 'now')),
            'table_name' => '',
            'screen_type' => 'CASHIER',
            'business_name' => $reportData['business']['name'] ?? '',
            'tax_number' => $reportData['business']['tax_number'] ?? '-',
            'address' => $reportData['business']['address'] ?? '-',
            'phone' => $reportData['business']['phone'] ?? '-',
            'z_number' => $reportData['z_number'],
            'report_date' => $reportData['date'],
            'report_time' => date('d.m.Y H:i'),
            'order_lines' => $reportData['order_lines'],
            'product_breakdown' => $reportData['product_breakdown'],
            'category_breakdown' => $reportData['category_breakdown'],
            'totals' => $reportData['totals'],
            'payment_breakdown' => $reportData['payment_breakdown'],
            'discount_total' => $reportData['discount_total'] ?? 0,
            'service_charge_total' => $reportData['service_charge_total'] ?? 0,
            'tip_total' => $reportData['tip_total'] ?? 0
        ];
    }
}
