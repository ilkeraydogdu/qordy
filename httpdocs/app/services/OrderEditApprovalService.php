<?php
namespace App\Services;

require_once __DIR__ . '/../core/DependencyFactory.php';
require_once __DIR__ . '/../helpers/functions.php';

class OrderEditApprovalService {
    private $db;
    
    public function __construct() {
        $this->db = \App\Core\DependencyFactory::getDatabase();
    }
    
    /**
     * Create approval request
     * @param array $data Approval request data
     * @return string|false Approval ID on success, false on failure
     */
    public function createApprovalRequest(array $data) {
        $approvalId = generateId('appr');
        
        $approvalData = [
            'approval_id' => $approvalId,
            'order_item_id' => $data['order_item_id'] ?? '',
            'order_id' => $data['order_id'] ?? '',
            'table_id' => $data['table_id'] ?? null,
            'table_name' => $data['table_name'] ?? null,
            'action_type' => $data['action_type'] ?? 'DELETE', // DELETE or REDUCE_QUANTITY
            'old_quantity' => intval($data['old_quantity'] ?? 1),
            'new_quantity' => isset($data['new_quantity']) ? intval($data['new_quantity']) : null,
            'item_name' => $data['item_name'] ?? null,
            'item_price' => isset($data['item_price']) ? floatval($data['item_price']) : null,
            'requested_by' => $data['requested_by'] ?? '',
            'requested_by_name' => $data['requested_by_name'] ?? '',
            'status' => 'PENDING',
            'requested_at' => date('Y-m-d H:i:s')
        ];
        if (isset($data['order_items_snapshot']) && ($data['action_type'] ?? '') === 'DELETE_ORDER') {
            $approvalData['deleted_items_snapshot'] = is_string($data['order_items_snapshot'])
                ? $data['order_items_snapshot']
                : json_encode($data['order_items_snapshot'], JSON_UNESCAPED_UNICODE);
        }
        if (isset($data['prep_items_snapshot']) && ($data['action_type'] ?? '') === 'PAYMENT_PREP_CANCEL') {
            $approvalData['cancelled_prep_items_snapshot'] = is_string($data['prep_items_snapshot'])
                ? $data['prep_items_snapshot']
                : json_encode($data['prep_items_snapshot'], JSON_UNESCAPED_UNICODE);
        }
        if (isset($data['affected_item_snapshot']) && in_array($data['action_type'] ?? '', ['DELETE', 'REDUCE_QUANTITY'], true)) {
            $approvalData['affected_item_snapshot'] = is_string($data['affected_item_snapshot'])
                ? $data['affected_item_snapshot']
                : json_encode($data['affected_item_snapshot'], JSON_UNESCAPED_UNICODE);
        }
        
        try {
            $columns = array_keys($approvalData);
            $placeholders = array_map(function($col) { return ':' . $col; }, $columns);
            
            $sql = "INSERT INTO order_edit_approvals (" . implode(', ', $columns) . ") 
                    VALUES (" . implode(', ', $placeholders) . ")";
            
            $stmt = $this->db->prepare($sql);
            foreach ($approvalData as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }
            
            $ok = false;
            try {
                $ok = $stmt->execute();
            } catch (\Throwable $e) {
                if (isset($approvalData['deleted_items_snapshot']) && (strpos($e->getMessage(), 'deleted_items_snapshot') !== false || strpos($e->getMessage(), 'Unknown column') !== false)) {
                    unset($approvalData['deleted_items_snapshot']);
                    $columns = array_keys($approvalData);
                    $placeholders = array_map(function($col) { return ':' . $col; }, $columns);
                    $sql = "INSERT INTO order_edit_approvals (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
                    $stmt = $this->db->prepare($sql);
                    foreach ($approvalData as $key => $value) {
                        $stmt->bindValue(':' . $key, $value);
                    }
                    $ok = $stmt->execute();
                } elseif (isset($approvalData['cancelled_prep_items_snapshot']) && (strpos($e->getMessage(), 'cancelled_prep_items_snapshot') !== false || strpos($e->getMessage(), 'Unknown column') !== false)) {
                    unset($approvalData['cancelled_prep_items_snapshot']);
                    $columns = array_keys($approvalData);
                    $placeholders = array_map(function($col) { return ':' . $col; }, $columns);
                    $sql = "INSERT INTO order_edit_approvals (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
                    $stmt = $this->db->prepare($sql);
                    foreach ($approvalData as $key => $value) {
                        $stmt->bindValue(':' . $key, $value);
                    }
                    $ok = $stmt->execute();
                } elseif (isset($approvalData['affected_item_snapshot']) && (strpos($e->getMessage(), 'affected_item_snapshot') !== false || strpos($e->getMessage(), 'Unknown column') !== false)) {
                    unset($approvalData['affected_item_snapshot']);
                    $columns = array_keys($approvalData);
                    $placeholders = array_map(function($col) { return ':' . $col; }, $columns);
                    $sql = "INSERT INTO order_edit_approvals (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
                    $stmt = $this->db->prepare($sql);
                    foreach ($approvalData as $key => $value) {
                        $stmt->bindValue(':' . $key, $value);
                    }
                    $ok = $stmt->execute();
                } else {
                    throw $e;
                }
            }
            
            if ($ok) {
                // Create notification for managers/admins
                $this->createNotificationForApproval($approvalId, $approvalData);
                return $approvalId;
            }
            
            return false;
        } catch (\Exception $e) {
            error_log("OrderEditApprovalService::createApprovalRequest error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create notification for managers/admins about approval request
     */
    private function createNotificationForApproval($approvalId, $approvalData) {
        try {
            $notificationService = \App\Core\DependencyFactory::getNotificationService();
            
            $actionType = $approvalData['action_type'] ?? 'DELETE';
            if ($actionType === 'PAYMENT_PREP_CANCEL') {
                $actionText = 'ödeme (hazırlanan ürünleri iptal)';
                $itemInfo = 'Masa ödemesi - mutfak/hazırlık iptali';
            } elseif ($actionType === 'DELETE_ORDER') {
                $actionText = 'tüm siparişleri silme';
                $itemInfo = $approvalData['item_name'] ?? 'Masadaki tüm siparişler';
            } else {
                $actionText = $actionType === 'DELETE' ? 'silme' : 'azaltma';
                $itemInfo = $approvalData['item_name'] ?? 'Ürün';
            }
            $tableInfo = $approvalData['table_name'] 
                ? ($approvalData['table_name'] . ' masası') 
                : 'Masa';
            
            $message = sprintf(
                '%s tarafından %s için %s talebi: %s',
                $approvalData['requested_by_name'] ?? 'Garson',
                $tableInfo,
                $actionText,
                $itemInfo
            );
            
            // Create notification with type EDIT_APPROVAL (shortened to fit DB column)
            // We'll use a special type for this
            $notificationService->create(
                'EDIT_APPROVAL',
                $approvalData['table_id'] ?? '',
                $approvalData['table_name'] ?? 'Masa',
                [
                    'approval_id' => $approvalId,
                    'action_type' => $approvalData['action_type'],
                    'item_name' => $approvalData['item_name'],
                    'requested_by' => $approvalData['requested_by_name']
                ],
                true // Play sound
            );
        } catch (\Exception $e) {
            error_log("OrderEditApprovalService::createNotificationForApproval error: " . $e->getMessage());
        }
    }
    
    /**
     * Check if there is already a PENDING approval request for this order item.
     * Aynı ürün (order_item_id) için henüz yanıtlanmamış istek varsa tekrar oluşturulmasın.
     * @param string $orderItemId
     * @return bool True if pending approval exists
     */
    /**
     * Check if there is already a PENDING payment-prep-cancel request for this table.
     * @param string $tableId Table ID
     * @return bool True if pending approval exists
     */
    public function hasPendingPaymentPrepCancelForTable(string $tableId): bool {
        if (empty($tableId)) {
            return false;
        }
        try {
            $sql = "SELECT 1 FROM order_edit_approvals 
                    WHERE table_id = :table_id AND action_type = 'PAYMENT_PREP_CANCEL' AND status = 'PENDING' 
                    LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':table_id', $tableId);
            $stmt->execute();
            return (bool) $stmt->fetch();
        } catch (\Exception $e) {
            error_log("OrderEditApprovalService::hasPendingPaymentPrepCancelForTable error: " . $e->getMessage());
            return false;
        }
    }

    public function hasPendingApprovalForOrderItem(string $orderItemId): bool {
        if (empty($orderItemId)) {
            return false;
        }
        try {
            $sql = "SELECT 1 FROM order_edit_approvals 
                    WHERE order_item_id = :order_item_id AND status = 'PENDING' 
                    LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':order_item_id', $orderItemId);
            $stmt->execute();
            return (bool) $stmt->fetch();
        } catch (\Exception $e) {
            error_log("OrderEditApprovalService::hasPendingApprovalForOrderItem error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if there is any PENDING approval for this table (including DELETE_ORDER bulk requests)
     */
    public function hasPendingApprovalForTable(string $tableId): bool {
        if (empty($tableId)) {
            return false;
        }
        try {
            $sql = "SELECT 1 FROM order_edit_approvals 
                    WHERE table_id = :table_id AND status = 'PENDING' 
                    LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':table_id', $tableId);
            $stmt->execute();
            return (bool) $stmt->fetch();
        } catch (\Exception $e) {
            error_log("OrderEditApprovalService::hasPendingApprovalForTable error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get approval by ID
     * @param string $approvalId
     * @return array|null
     */
    public function getApprovalById(string $approvalId): ?array {
        try {
            $sql = "SELECT * FROM order_edit_approvals WHERE approval_id = :approval_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':approval_id', $approvalId);
            $stmt->execute();
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (\Exception $e) {
            error_log("OrderEditApprovalService::getApprovalById error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get pending approvals
     * @return array
     */
    public function getPendingApprovals(): array {
        try {
            // CRITICAL: Filter by tenant for non-super-admin users
            $tenantId = \App\Core\TenantContext::getId();
            $isSuperAdmin = false;
            
            // Check if user is super admin
            if (isset($_SESSION['role'])) {
                $role = strtoupper($_SESSION['role']);
                $isSuperAdmin = (strpos($role, 'SUPER_ADMIN') !== false || strpos($role, 'QODMIN') !== false);
            }
            
            $sql = "SELECT a.*,
                    COALESCE(NULLIF(a.item_price, 0), oi.price) as item_price,
                    COALESCE(u.name, a.requested_by_name) as requested_by_name,
                    COALESCE(r.role_name, r.role_code, u.role) as requested_by_role
                    FROM order_edit_approvals a
                    LEFT JOIN orders o ON a.order_id = o.order_id
                    LEFT JOIN order_items oi ON a.order_item_id = oi.order_item_id AND a.order_item_id IS NOT NULL AND a.order_item_id != ''
                    LEFT JOIN users u ON a.requested_by = u.user_id
                    LEFT JOIN roles r ON u.role_id = r.role_id
                    WHERE a.status = 'PENDING'";
            
            $params = [];
            
            // Add tenant filter if not super admin
            // NOTE: orders table only has tenant_id column (not business_id)
            if (!$isSuperAdmin && $tenantId) {
                $sql .= " AND o.tenant_id = :tenant_id";
                $params['tenant_id'] = $tenantId;
            }
            
            $sql .= " ORDER BY a.requested_at DESC";
            
            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("OrderEditApprovalService::getPendingApprovals error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get count of pending approvals (for badge)
     * @return int
     */
    public function getPendingCount(): int {
        $approvals = $this->getPendingApprovals();
        return count($approvals);
    }
    
    /**
     * Get approval history with filters
     * @param array $filters ['order_number' => string, 'requested_by_name' => string, 'date_from' => string, 'date_to' => string, 'item_name' => string, 'table_name' => string, 'status' => string]
     * @return array
     */
    public function getApprovalHistory(array $filters = []): array {
        try {
            $tenantId = \App\Core\TenantContext::getId();
            $isSuperAdmin = false;
            if (isset($_SESSION['role'])) {
                $role = strtoupper($_SESSION['role']);
                $isSuperAdmin = (strpos($role, 'SUPER_ADMIN') !== false || strpos($role, 'QODMIN') !== false);
            }
            
            $sql = "SELECT a.*, a.order_id as order_number FROM order_edit_approvals a
                    LEFT JOIN orders o ON a.order_id = o.order_id
                    WHERE 1=1";
            $params = [];
            
            if (!$isSuperAdmin && $tenantId) {
                $sql .= " AND o.tenant_id = :tenant_id";
                $params['tenant_id'] = $tenantId;
            }
            
            if (!empty($filters['order_number'])) {
                $sql .= " AND a.order_id LIKE :order_number";
                $params['order_number'] = '%' . $filters['order_number'] . '%';
            }
            if (!empty($filters['requested_by_name'])) {
                $sql .= " AND (a.requested_by_name LIKE :req_name OR a.requested_by LIKE :req_name2)";
                $params['req_name'] = '%' . $filters['requested_by_name'] . '%';
                $params['req_name2'] = '%' . $filters['requested_by_name'] . '%';
            }
            if (!empty($filters['date_from'])) {
                $sql .= " AND DATE(a.requested_at) >= :date_from";
                $params['date_from'] = $filters['date_from'];
            }
            if (!empty($filters['date_to'])) {
                $sql .= " AND DATE(a.requested_at) <= :date_to";
                $params['date_to'] = $filters['date_to'];
            }
            if (!empty($filters['item_name'])) {
                $sql .= " AND a.item_name LIKE :item_name";
                $params['item_name'] = '%' . $filters['item_name'] . '%';
            }
            if (!empty($filters['table_name'])) {
                $sql .= " AND (a.table_name LIKE :table_name OR a.table_id LIKE :table_id)";
                $params['table_name'] = '%' . $filters['table_name'] . '%';
                $params['table_id'] = '%' . $filters['table_name'] . '%';
            }
            if (!empty($filters['status'])) {
                $sql .= " AND a.status = :status";
                $params['status'] = $filters['status'];
            }
            if (!empty($filters['action_type'])) {
                $sql .= " AND a.action_type = :action_type";
                $params['action_type'] = $filters['action_type'];
            }
            
            $sql .= " ORDER BY a.requested_at DESC LIMIT 500";
            
            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Enrich with requested_by role from users+roles; if not in users, treat as customer (işletme sahibi)
            foreach ($rows as &$row) {
                $reqBy = $row['requested_by'] ?? '';
                if ($reqBy === '') {
                    continue;
                }
                try {
                    $userSql = "SELECT u.name, COALESCE(r.role_name, r.role_code, u.role) as requested_by_role FROM users u LEFT JOIN roles r ON u.role_id = r.role_id WHERE u.user_id = :uid";
                    $userStmt = $this->db->prepare($userSql);
                    $userStmt->bindValue(':uid', $reqBy);
                    $userStmt->execute();
                    $u = $userStmt->fetch(\PDO::FETCH_ASSOC);
                    if ($u) {
                        if (empty($row['requested_by_name']) && !empty($u['name'])) {
                            $row['requested_by_name'] = $u['name'];
                        }
                        $row['requested_by_role'] = $u['requested_by_role'] ?? null;
                        continue;
                    }
                    $custStmt = $this->db->prepare("SELECT COALESCE(r.role_name, r.role_code) as role FROM customers c CROSS JOIN roles r WHERE c.customer_id = :cid AND r.role_code = 'BUSINESS_MANAGER' LIMIT 1");
                    $custStmt->bindValue(':cid', $reqBy);
                    $custStmt->execute();
                    $cr = $custStmt->fetch(\PDO::FETCH_ASSOC);
                    if ($cr && !empty($cr['role'])) {
                        $row['requested_by_role'] = $cr['role'];
                    }
                    // İşletme sahibi ise listede de işletme adı göster (email yerine)
                    $custNameStmt = $this->db->prepare("SELECT company_name FROM customers WHERE customer_id = :cid LIMIT 1");
                    $custNameStmt->bindValue(':cid', $reqBy);
                    $custNameStmt->execute();
                    $custName = $custNameStmt->fetch(\PDO::FETCH_ASSOC);
                    if ($custName && !empty(trim($custName['company_name'] ?? ''))) {
                        $row['requested_by_name'] = trim($custName['company_name']);
                    }
                } catch (\Throwable $e) {
                    // ignore
                }
            }
            unset($row);
            return $rows;
        } catch (\Exception $e) {
            error_log("OrderEditApprovalService::getApprovalHistory error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get single approval detail for history view (göz ikonu): approval + who requested (role), who approved/rejected, when, order items.
     * @param string $approvalId
     * @return array|null { approval: array, order_items: array } or null if not found / tenant mismatch
     */
    public function getApprovalDetail(string $approvalId): ?array {
        try {
            $tenantId = \App\Core\TenantContext::getId();
            $isSuperAdmin = false;
            if (isset($_SESSION['role'])) {
                $role = strtoupper($_SESSION['role']);
                $isSuperAdmin = (strpos($role, 'SUPER_ADMIN') !== false || strpos($role, 'QODMIN') !== false);
            }
            
            $sql = "SELECT a.*, a.order_id as order_number,
                    u_req.name as requested_by_display_name,
                    COALESCE(TRIM(c_req.company_name), '') as requested_by_business_name,
                    COALESCE(r_req.role_name, r_req.role_code, u_req.role,
                        CASE WHEN c_req.customer_id IS NOT NULL THEN (SELECT COALESCE(r_bm.role_name, r_bm.role_code) FROM roles r_bm WHERE r_bm.role_code = 'BUSINESS_MANAGER' LIMIT 1) ELSE NULL END
                    ) as requested_by_role
                    FROM order_edit_approvals a
                    LEFT JOIN orders o ON a.order_id = o.order_id
                    LEFT JOIN users u_req ON a.requested_by = u_req.user_id
                    LEFT JOIN roles r_req ON u_req.role_id = r_req.role_id
                    LEFT JOIN customers c_req ON a.requested_by = c_req.customer_id
                    WHERE a.approval_id = :approval_id";
            $params = ['approval_id' => $approvalId];
            if (!$isSuperAdmin && $tenantId) {
                $sql .= " AND o.tenant_id = :tenant_id";
                $params['tenant_id'] = $tenantId;
            }
            $stmt = $this->db->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue(':' . $k, $v);
            }
            $stmt->execute();
            $approval = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$approval) {
                return null;
            }
            // İşletme sahibi (customer) ise işletme adı; garson/kasiyer (user) ise kişi adı kullan
            $businessName = isset($approval['requested_by_business_name']) ? trim((string)$approval['requested_by_business_name']) : '';
            if ($businessName !== '') {
                $approval['requested_by_name'] = $businessName;
            } else {
                $approval['requested_by_name'] = $approval['requested_by_name'] ?: $approval['requested_by_display_name'] ?? '';
            }
            unset($approval['requested_by_display_name'], $approval['requested_by_business_name']);
            
            $orderId = $approval['order_id'] ?? '';
            $orderItems = [];
            if ($orderId !== '') {
                $orderItemService = \App\Core\DependencyFactory::getOrderItemService();
                $orderItems = $orderItemService->getOrderItemsByOrder($orderId);
            }
            
            $deletedOrderItems = [];
            if (($approval['action_type'] ?? '') === 'DELETE_ORDER' && !empty($approval['deleted_items_snapshot'])) {
                $decoded = json_decode($approval['deleted_items_snapshot'], true);
                $deletedOrderItems = is_array($decoded) ? $decoded : [];
            }
            
            $cancelledPrepItems = [];
            if (($approval['action_type'] ?? '') === 'PAYMENT_PREP_CANCEL' && !empty($approval['cancelled_prep_items_snapshot'])) {
                $decoded = json_decode($approval['cancelled_prep_items_snapshot'], true);
                $cancelledPrepItems = is_array($decoded) ? $decoded : [];
            }
            
            $affectedItem = null;
            if (in_array($approval['action_type'] ?? '', ['DELETE', 'REDUCE_QUANTITY'], true) && !empty($approval['affected_item_snapshot'])) {
                $decoded = json_decode($approval['affected_item_snapshot'], true);
                $affectedItem = is_array($decoded) ? $decoded : null;
            }
            
            return [
                'approval' => $approval,
                'order_items' => $orderItems,
                'deleted_order_items' => $deletedOrderItems,
                'cancelled_prep_items' => $cancelledPrepItems,
                'affected_item' => $affectedItem,
            ];
        } catch (\Exception $e) {
            error_log("OrderEditApprovalService::getApprovalDetail error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Approve request
     * @param string $approvalId
     * @param string $approvedBy User ID
     * @param string $approvedByName User name
     * @return bool
     */
    public function approveRequest(string $approvalId, string $approvedBy, string $approvedByName): bool {
        try {
            $approval = $this->getApprovalById($approvalId);
            if (!$approval || $approval['status'] !== 'PENDING') {
                return false;
            }
            
            $orderItemService = \App\Core\DependencyFactory::getOrderItemService();
            $orderService = \App\Core\DependencyFactory::getOrderService();
            
            $success = false;
            
            if ($approval['action_type'] === 'PAYMENT_PREP_CANCEL') {
                // İptal: masadaki hazırlanan ürünleri CANCELLED yap, sipariş toplamlarını güncelle
                $tableId = $approval['table_id'] ?? '';
                if (empty($tableId)) {
                    return false;
                }
                $orderItemRepo = \App\Core\DependencyFactory::getOrderItemRepository();
                $ids = $orderItemRepo->getOrderItemIdsInPreparationByTableId($tableId);
                if (empty($ids)) {
                    $success = true;
                } else {
                    $orderItemService->updatePreparationStatusByIds($ids, 'CANCELLED');
                    $orders = $orderService->getActiveOrdersByTable($tableId);
                    foreach ($orders as $order) {
                        $orderItems = $orderItemService->getOrderItemsByOrder($order['order_id']);
                        $activeItems = array_filter($orderItems, function ($it) {
                            $st = $it['preparation_status'] ?? '';
                            return $st !== 'CANCELLED';
                        });
                        $newTotal = 0;
                        foreach ($activeItems as $it) {
                            $newTotal += floatval($it['price'] ?? 0) * intval($it['quantity'] ?? 1);
                        }
                        $orderService->updateOrderTotal($order['order_id'], round($newTotal, 2));
                    }
                    $success = true;
                }
            } elseif ($approval['action_type'] === 'DELETE') {
                // Delete order item
                $success = $orderItemService->deleteOrderItem($approval['order_item_id']);
                
                if ($success) {
                    // Update order total
                    $order = $orderService->getOrderById($approval['order_id']);
                    if ($order) {
                        $itemTotal = floatval($approval['item_price'] ?? 0) * intval($approval['old_quantity'] ?? 1);
                        $newTotal = floatval($order['total_amount'] ?? 0) - $itemTotal;
                        if ($newTotal < 0) $newTotal = 0;
                        $orderService->updateOrderTotal($approval['order_id'], $newTotal);
                    }
                }
            } elseif ($approval['action_type'] === 'REDUCE_QUANTITY') {
                // Update quantity
                $newQuantity = intval($approval['new_quantity'] ?? 0);
                if ($newQuantity > 0) {
                    $success = $orderItemService->updateQuantity($approval['order_item_id'], $newQuantity);
                    
                    if ($success) {
                        // Update order total
                        $order = $orderService->getOrderById($approval['order_id']);
                        if ($order) {
                            $quantityDiff = intval($approval['old_quantity'] ?? 1) - $newQuantity;
                            $priceDiff = floatval($approval['item_price'] ?? 0) * $quantityDiff;
                            $newTotal = floatval($order['total_amount'] ?? 0) - $priceDiff;
                            if ($newTotal < 0) $newTotal = 0;
                            $orderService->updateOrderTotal($approval['order_id'], $newTotal);
                        }
                    }
                }
            } elseif ($approval['action_type'] === 'DELETE_ORDER') {
                // Bulk delete: delete all items in order, cancel order
                $orderId = $approval['order_id'] ?? '';
                $tableId = $approval['table_id'] ?? '';
                if (!empty($orderId)) {
                    $items = $orderItemService->getOrderItemsByOrder($orderId);
                    foreach ($items as $item) {
                        $orderItemService->deleteOrderItem($item['order_item_id'] ?? '');
                    }
                    $orderService->updateOrderStatus($orderId, 'CANCELLED');
                    $orderService->updateOrderTotal($orderId, 0);
                    $success = true;
                    if (!empty($tableId)) {
                        $activeOrders = $orderService->getActiveOrdersByTable($tableId);
                        if (empty($activeOrders)) {
                            $tableService = \App\Core\DependencyFactory::getTableService();
                            $tableService->updateTableStatus($tableId, 'FREE');
                        }
                    }
                }
            }
            
            if ($success) {
                // Update approval status
                $sql = "UPDATE order_edit_approvals 
                        SET status = 'APPROVED',
                            approved_by = :approved_by,
                            approved_by_name = :approved_by_name,
                            processed_at = NOW()
                        WHERE approval_id = :approval_id";
                $stmt = $this->db->prepare($sql);
                $stmt->bindValue(':approved_by', $approvedBy);
                $stmt->bindValue(':approved_by_name', $approvedByName);
                $stmt->bindValue(':approval_id', $approvalId);
                $stmt->execute();
                
                return true;
            }
            
            return false;
        } catch (\Exception $e) {
            error_log("OrderEditApprovalService::approveRequest error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Reject request
     * @param string $approvalId
     * @param string $rejectedBy User ID
     * @param string $rejectedByName User name
     * @param string $reason Rejection reason
     * @return bool
     */
    public function rejectRequest(string $approvalId, string $rejectedBy, string $rejectedByName, string $reason = ''): bool {
        try {
            $approval = $this->getApprovalById($approvalId);
            if (!$approval || $approval['status'] !== 'PENDING') {
                return false;
            }
            
            // Minimal update: only columns that definitely exist (status, processed_at).
            // Avoids "Unknown column" errors on rejected_reason / approved_by.
            $sql = "UPDATE order_edit_approvals 
                    SET status = 'REJECTED',
                        processed_at = NOW()
                    WHERE approval_id = :approval_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':approval_id', $approvalId);
            $ok = $stmt->execute();
            if (!$ok) {
                return false;
            }
            
            // Optionally set approved_by / rejected_reason if columns exist (no error if missing)
            try {
                $stmt2 = $this->db->prepare("UPDATE order_edit_approvals SET approved_by = ?, approved_by_name = ? WHERE approval_id = ?");
                $stmt2->execute([$rejectedBy, $rejectedByName, $approvalId]);
            } catch (\Throwable $e) {
                // ignore
            }
            try {
                $stmt3 = $this->db->prepare("UPDATE order_edit_approvals SET rejected_reason = ? WHERE approval_id = ?");
                $stmt3->execute([$reason, $approvalId]);
            } catch (\Throwable $e) {
                // ignore
            }
            
            return true;
        } catch (\Exception $e) {
            error_log("OrderEditApprovalService::rejectRequest error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Masa taşındığında bu siparişlere ait azaltma/iptal taleplerinin masa bilgisini günceller.
     * @param array $orderIds Taşınan sipariş ID'leri
     * @param string $toTableId Yeni masa ID
     * @param string $toTableName Yeni masa adı
     * @return bool
     */
    public function updateTableForOrders(array $orderIds, string $toTableId, string $toTableName): bool {
        if (empty($orderIds)) {
            return true;
        }
        try {
            $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
            $sql = "UPDATE order_edit_approvals SET table_id = ?, table_name = ? WHERE order_id IN ($placeholders)";
            $params = array_merge([$toTableId, $toTableName], $orderIds);
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
        } catch (\Exception $e) {
            error_log("OrderEditApprovalService::updateTableForOrders error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Talep eden kullanıcı için onay/red geri bildirimi (garson/kasiyer ekranında gösterilecek)
     * @param string $requestedByUserId Talep eden user_id
     * @param int|null $since Unix timestamp - bu tarihten sonra işlenen talepler
     * @return array
     */
    public function getApprovalFeedbackForUser(string $requestedByUserId, ?int $since = null): array {
        if (empty($requestedByUserId)) {
            return [];
        }
        try {
            $tenantId = \App\Core\TenantContext::getId();
            $isSuperAdmin = false;
            if (isset($_SESSION['role'])) {
                $role = strtoupper($_SESSION['role']);
                $isSuperAdmin = (strpos($role, 'SUPER_ADMIN') !== false || strpos($role, 'QODMIN') !== false);
            }
            
            $sql = "SELECT a.approval_id, a.status, a.item_name, a.rejected_reason, a.processed_at, a.table_name
                    FROM order_edit_approvals a
                    LEFT JOIN orders o ON a.order_id = o.order_id
                    WHERE a.requested_by = :user_id
                    AND a.status IN ('APPROVED', 'REJECTED')
                    AND a.processed_at IS NOT NULL";
            $params = ['user_id' => $requestedByUserId];
            
            if ($since !== null && $since > 0) {
                $sql .= " AND a.processed_at >= FROM_UNIXTIME(:since)";
                $params['since'] = $since;
            }
            
            if (!$isSuperAdmin && $tenantId) {
                $sql .= " AND o.tenant_id = :tenant_id";
                $params['tenant_id'] = $tenantId;
            }
            
            $sql .= " ORDER BY a.processed_at DESC LIMIT 20";
            
            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("OrderEditApprovalService::getApprovalFeedbackForUser error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check if user role requires approval
     * @param string $userId
     * @param string|null $businessId Business ID (optional, for business-specific settings)
     * @return bool
     */
    public function requiresApproval(string $userId, ?string $businessId = null): bool {
        try {
            // Get user role
            $userService = \App\Core\DependencyFactory::getUserService();
            $user = $userService->findByUserId($userId);
            
            if (!$user) {
                return true; // Default to requiring approval if user not found
            }
            
            $role = $user['role'] ?? '';
            $roleUpper = strtoupper($role);
            // İşletme yöneticisi (BUSINESS_MANAGER), ADMIN ve MANAGER onay gerektirmez
            if (in_array($roleUpper, ['ADMIN', 'MANAGER', 'BUSINESS_MANAGER'])) {
                return false;
            }
            
            // Check business-specific setting if businessId is provided
            if ($businessId !== null) {
                $businessSettingsService = \App\Core\DependencyFactory::getBusinessSettingsService();
                $businessRequiresApproval = $businessSettingsService->waiterDeleteRequiresApproval($businessId);
                
                // If business setting is explicitly enabled, require approval
                if ($businessRequiresApproval) {
                    return true;
                }
                
                // If business setting is disabled, check system-wide setting for backward compatibility
                $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
                $systemRequiresApproval = $settingsService->getSetting('order_edit_requires_approval', '1') === '1';
                return $systemRequiresApproval;
            }
            
            // No businessId provided, check system setting only
            $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
            $requiresApproval = $settingsService->getSetting('order_edit_requires_approval', '1') === '1';
            
            if (!$requiresApproval) {
                return false;
            }
            
            // Check if user role is in approval required roles
            $approvalRole = $settingsService->getSetting('order_edit_approval_role', 'MANAGER');
            
            // For waiter and other roles, require approval
            return true;
        } catch (\Exception $e) {
            error_log("OrderEditApprovalService::requiresApproval error: " . $e->getMessage());
            return true; // Default to requiring approval on error
        }
    }
}
