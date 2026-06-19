<?php
namespace App\Controllers\Admin;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';

use App\Core\Controller;

class OrderApprovalController extends Controller {
    
    protected $approvalService;
    
    public function __construct() {
        parent::__construct();
        $this->approvalService = \App\Core\DependencyFactory::getOrderEditApprovalService();
    }
    
    /**
     * Show order approvals page (Onay Bekleyen Silme İşlemleri)
     */
    public function index() {
        $this->requirePermission('orders.edit');

        $isSuperAdmin = $this->isSuperAdmin();
        $businessId = null;

        if ($isSuperAdmin) {
            $businessId = $_GET['business_id'] ?? $_SESSION['selected_business_id'] ?? null;
            if ($businessId) {
                $this->ensureTenantContext();
            }
        } else {
            $this->ensureTenantContext();
        }

        $apiPrefix = $isSuperAdmin ? '/api/qodmin' : '/api/business';

        $this->view('admin/order_approvals', [
            'is_super_admin'       => $isSuperAdmin,
            'selected_business_id' => $businessId,
            'api_prefix'           => $apiPrefix,
            'csrf_token'           => $_SESSION['csrf_token'] ?? ''
        ]);
    }

    /**
     * Show order approval history page (İşlem Geçmişi)
     */
    public function history() {
        $this->requirePermission('orders.edit');

        $isSuperAdmin = $this->isSuperAdmin();
        $businessId = null;

        if ($isSuperAdmin) {
            $businessId = $_GET['business_id'] ?? $_SESSION['selected_business_id'] ?? null;
            if ($businessId) {
                $this->ensureTenantContext();
            }
        } else {
            $this->ensureTenantContext();
        }

        $apiPrefix = $isSuperAdmin ? '/api/qodmin' : '/api/business';

        $this->view('admin/order_approval_history', [
            'is_super_admin'       => $isSuperAdmin,
            'selected_business_id' => $businessId,
            'api_prefix'           => $apiPrefix,
            'csrf_token'           => $_SESSION['csrf_token'] ?? ''
        ]);
    }
    
    /**
     * Get pending approvals count (for sidebar badge)
     */
    public function getPendingCount() {
        $this->ensureTenantContext();
        $this->requirePermission('orders.edit');
        
        try {
            $count = $this->approvalService->getPendingCount();
            $this->apiResponse(['success' => true, 'count' => $count]);
        } catch (\Exception $e) {
            $this->apiResponse(['success' => false, 'count' => 0], 500);
        }
    }
    
    /**
     * Get approval history with filters
     */
    public function getApprovalHistory() {
        $this->ensureTenantContext();
        $this->requirePermission('orders.edit');
        
        try {
            $orderNumber = $_GET['order_number'] ?? '';
            $requestedByName = $_GET['requested_by_name'] ?? '';
            $dateFrom = $_GET['date_from'] ?? '';
            $dateTo = $_GET['date_to'] ?? '';
            $itemName = $_GET['item_name'] ?? '';
            $tableName = $_GET['table_name'] ?? '';
            $status = $_GET['status'] ?? '';
            $actionType = $_GET['action_type'] ?? '';
            
            $filters = array_filter([
                'order_number' => $orderNumber,
                'requested_by_name' => $requestedByName,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'item_name' => $itemName,
                'table_name' => $tableName,
                'status' => $status,
                'action_type' => $actionType,
            ]);
            
            $history = $this->approvalService->getApprovalHistory($filters);
            $this->apiResponse(['success' => true, 'history' => $history]);
        } catch (\Exception $e) {
            error_log("OrderApprovalController::getApprovalHistory error: " . $e->getMessage());
            $this->apiResponse(['success' => false, 'error' => 'Yüklenemedi', 'history' => []], 500);
        }
    }
    
    /**
     * Get single approval detail (for history page eye icon / detay modal)
     */
    public function getApprovalDetail() {
        $this->ensureTenantContext();
        $this->requirePermission('orders.edit');
        
        $approvalId = $_GET['approval_id'] ?? '';
        if ($approvalId === '') {
            $this->apiResponse(['success' => false, 'error' => 'approval_id gerekli'], 400);
            return;
        }
        
        $detail = $this->approvalService->getApprovalDetail($approvalId);
        if ($detail === null) {
            $this->apiResponse(['success' => false, 'error' => 'Kayıt bulunamadı'], 404);
            return;
        }
        
        $this->apiResponse(['success' => true, 'detail' => $detail]);
    }
    
    /**
     * Get payment transactions for history page (Alınan ödemeler)
     */
    public function getPaymentsForHistory() {
        $this->ensureTenantContext();
        $this->requirePermission('orders.edit');
        
        $dateFrom = $_GET['date_from'] ?? date('Y-m-d');
        $dateTo = $_GET['date_to'] ?? date('Y-m-d');
        
        try {
            $repo = \App\Core\DependencyFactory::getPaymentTransactionRepository();
            $transactions = $repo->getByDateRange($dateFrom, $dateTo);
            $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
            $settings = $settingsService->getSettings() ?? [];
            $businessName = is_array($settings) ? trim($settings['business_name'] ?? $settings['restaurant_name'] ?? '') : '';
            if ($businessName === '') {
                $tenantId = \App\Core\TenantContext::getId();
                if ($tenantId) {
                    try {
                        $customerService = \App\Core\DependencyFactory::getCustomerService();
                        $customer = $customerService->getById($tenantId);
                        $businessName = trim($customer['company_name'] ?? $customer['business_name'] ?? '') ?: 'İşletme';
                    } catch (\Throwable $e) {
                        $businessName = 'İşletme';
                    }
                }
            }
            $receiptRepo = \App\Core\DependencyFactory::getReceiptRepository();
            $receiptService = \App\Core\DependencyFactory::getReceiptService();
            $receiptsInRange = [];
            try {
                $receiptsInRange = $receiptService->getReceiptsByDateRangeForList($dateFrom, $dateTo);
            } catch (\Throwable $e) {
                // fallback: raw by date range
                $receiptsInRange = $receiptRepo ? $receiptRepo->getByDateRange($dateFrom, $dateTo) : [];
            }
            foreach ($transactions as &$p) {
                $role = strtoupper((string)($p['processed_by_role'] ?? ''));
                $name = trim((string)($p['processed_by_name'] ?? ''));
                $isManager = in_array($role, ['BUSINESS_MANAGER', 'ROLE_BUSINESS_MANAGER', 'MANAGER', 'ADMIN'], true);
                if ($name === '' || $isManager) {
                    $p['processed_by_display_name'] = $businessName !== '' ? $businessName : 'İşletme';
                } else {
                    $p['processed_by_display_name'] = $name;
                }
                $orderId = $p['order_id'] ?? '';
                $p['receipt_id'] = '';
                $p['receipt_number'] = '';
                $p['display_order_id'] = $orderId;
                if ($orderId !== '' && $receiptRepo) {
                    try {
                        $receipts = $receiptRepo->getByOrder($orderId);
                        $matchedReceipt = null;
                        foreach ($receipts as $r) {
                            if (strtoupper((string)($r['receipt_type'] ?? '')) === 'FULL') {
                                $matchedReceipt = $r;
                                break;
                            }
                        }
                        if ($matchedReceipt === null && !empty($receipts)) {
                            $matchedReceipt = $receipts[0];
                        }
                        if ($matchedReceipt !== null) {
                            $p['receipt_id'] = $matchedReceipt['receipt_id'] ?? '';
                            $p['receipt_number'] = $matchedReceipt['receipt_number'] ?? $p['receipt_id'];
                            $p['display_order_id'] = $matchedReceipt['order_id'] ?? $orderId;
                        }
                    } catch (\Throwable $e) {
                        // ignore
                    }
                }
                if ($p['receipt_id'] === '' && !empty($receiptsInRange)) {
                    $ptTableId = $p['table_id'] ?? '';
                    $ptTableName = trim((string)($p['table_name'] ?? ''));
                    $ptAmount = isset($p['amount']) ? (float) $p['amount'] : 0;
                    $ptCreated = $p['created_at'] ?? $p['timestamp'] ?? '';
                    $ptDate = $ptCreated ? date('Y-m-d', strtotime($ptCreated)) : '';
                    $best = null;
                    $bestDiff = null;
                    foreach ($receiptsInRange as $r) {
                        $rTableId = $r['table_id'] ?? '';
                        $rTableName = trim((string)($r['table_name'] ?? ''));
                        $rAmount = isset($r['total_amount']) ? (float) $r['total_amount'] : 0;
                        $rDate = isset($r['created_at']) ? date('Y-m-d', strtotime($r['created_at'])) : '';
                        $tableMatch = false;
                        if ($ptTableId !== '' && $rTableId !== '' && (string)$ptTableId === (string)$rTableId) $tableMatch = true;
                        elseif ($ptTableName !== '' && $rTableName !== '' && $ptTableName === $rTableName) $tableMatch = true;
                        if (!$tableMatch) continue;
                        if ($ptDate !== '' && $rDate !== '' && $ptDate !== $rDate) continue;
                        if ($ptAmount > 0 && abs($rAmount - $ptAmount) > 0.01) continue;
                        $diff = ($ptCreated && isset($r['created_at'])) ? abs(strtotime($ptCreated) - strtotime($r['created_at'])) : 0;
                        if ($best === null || $bestDiff === null || $diff < $bestDiff) {
                            $best = $r;
                            $bestDiff = $diff;
                        }
                    }
                    if ($best !== null) {
                        $p['receipt_id'] = $best['receipt_id'] ?? '';
                        $p['receipt_number'] = $best['receipt_number'] ?? $p['receipt_id'];
                        $p['display_order_id'] = $best['order_id'] ?? '';
                    }
                }
            }
            unset($p);
            $this->apiResponse(['success' => true, 'payments' => $transactions]);
        } catch (\Exception $e) {
            error_log("OrderApprovalController::getPaymentsForHistory error: " . $e->getMessage());
            $this->apiResponse(['success' => false, 'error' => 'Ödemeler yüklenemedi', 'payments' => []], 500);
        }
    }
    
    /**
     * Get other transactions for history page (Diğer işlemler: giderler vb.)
     */
    public function getOtherTransactionsForHistory() {
        $this->ensureTenantContext();
        $this->requirePermission('orders.edit');
        
        $dateFrom = $_GET['date_from'] ?? date('Y-m-d');
        $dateTo = $_GET['date_to'] ?? date('Y-m-d');
        
        try {
            $financeService = \App\Core\DependencyFactory::getFinanceService();
            $expenses = $financeService->getExpensesByDateRange($dateFrom, $dateTo);
            $this->apiResponse(['success' => true, 'transactions' => $expenses]);
        } catch (\Exception $e) {
            error_log("OrderApprovalController::getOtherTransactionsForHistory error: " . $e->getMessage());
            $this->apiResponse(['success' => false, 'error' => 'İşlemler yüklenemedi', 'transactions' => []], 500);
        }
    }
    
    /**
     * Talep eden kullanıcı için onay/red geri bildirimi (garson/kasiyer POS ekranında gösterilecek)
     * Sadece login gerekir - orders.edit gerekmez.
     */
    public function getApprovalFeedback() {
        $this->ensureTenantContext();
        if (!$this->requireLogin(true)) {
            $this->apiResponse(['success' => false, 'feedback' => []], 401);
            return;
        }
        try {
            $userId = $_SESSION['user_id'] ?? '';
            if (empty($userId)) {
                $this->apiResponse(['success' => true, 'feedback' => []]);
                return;
            }
            $since = isset($_GET['since']) ? (int) $_GET['since'] : null;
            $feedback = $this->approvalService->getApprovalFeedbackForUser($userId, $since);
            $this->apiResponse(['success' => true, 'feedback' => $feedback]);
        } catch (\Exception $e) {
            error_log("OrderApprovalController::getApprovalFeedback error: " . $e->getMessage());
            $this->apiResponse(['success' => false, 'feedback' => []], 500);
        }
    }
    
    /**
     * Get pending approvals
     */
    public function getPendingApprovals() {
        // CRITICAL: Ensure tenant context is set before anything else
        $this->ensureTenantContext();
        $this->requirePermission('orders.edit');
        
        try {
            $approvals = $this->approvalService->getPendingApprovals();
            $this->apiResponse([
                'success' => true,
                'approvals' => $approvals
            ]);
        } catch (\Exception $e) {
            error_log("OrderApprovalController::getPendingApprovals error: " . $e->getMessage());
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.load_failed', [], 500);
        }
    }
    
    /**
     * Approve request
     */
    public function approveRequest() {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        $this->requirePermission('orders.edit');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input === null) {
            $input = $_POST;
        }
        
        $approvalId = $input['approval_id'] ?? '';
        
        if (empty($approvalId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        // CRITICAL: Verify approval belongs to current tenant
        $approval = $this->approvalService->getApprovalById($approvalId);
        if ($approval && !$this->isSuperAdmin()) {
            // Get order from approval to check tenant
            $orderService = \App\Core\DependencyFactory::getOrderService();
            $order = $orderService->getOrderById($approval['order_id'] ?? '');
            if ($order) {
                $tenantId = \App\Core\TenantContext::getId();
                // NOTE: orders table uses tenant_id column (not business_id)
                $orderTenantId = $order['tenant_id'] ?? null;
                
                if (!$tenantId || $orderTenantId !== $tenantId) {
                    \App\Core\Logger::warning('Admin/OrderApprovalController::approveRequest - Tenant isolation violation', [
                        'approval_id' => $approvalId,
                        'order_id' => $approval['order_id'] ?? 'unknown',
                        'order_tenant_id' => $orderTenantId,
                        'tenant_id' => $tenantId
                    ]);
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
                    return;
                }
            }
        }
        
        $userId = $_SESSION['user_id'] ?? '';
        $userName = $_SESSION['user_name'] ?? $_SESSION['username'] ?? 'Yönetici';
        
        $result = $this->approvalService->approveRequest($approvalId, $userId, $userName);
        
        if ($result) {
            // Canlı bildirimlerden düşmesi için ilgili bildirimi okundu işaretle
            $notificationService = \App\Core\DependencyFactory::getNotificationService();
            $notificationService->markAsReadByApprovalId($approvalId);
            $this->toastNotificationService->sendApiResponse('success', 'notifications.success.approved', [], 200);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.approval_failed', [], 500);
        }
    }
    
    /**
     * Reject request
     */
    public function rejectRequest() {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        $this->requirePermission('orders.edit');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 405);
            return;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input === null) {
            $input = $_POST;
        }
        
        $approvalId = $input['approval_id'] ?? '';
        $reason = $input['reason'] ?? '';
        
        if (empty($approvalId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        // CRITICAL: Verify approval belongs to current tenant
        $approval = $this->approvalService->getApprovalById($approvalId);
        if ($approval && !$this->isSuperAdmin()) {
            // Get order from approval to check tenant
            $orderService = \App\Core\DependencyFactory::getOrderService();
            $order = $orderService->getOrderById($approval['order_id'] ?? '');
            if ($order) {
                $tenantId = \App\Core\TenantContext::getId();
                // NOTE: orders table uses tenant_id column (not business_id)
                $orderTenantId = $order['tenant_id'] ?? null;
                
                if (!$tenantId || $orderTenantId !== $tenantId) {
                    \App\Core\Logger::warning('Admin/OrderApprovalController::rejectRequest - Tenant isolation violation', [
                        'approval_id' => $approvalId,
                        'order_id' => $approval['order_id'] ?? 'unknown',
                        'order_tenant_id' => $orderTenantId,
                        'tenant_id' => $tenantId
                    ]);
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
                    return;
                }
            }
        }
        
        $userId = $_SESSION['user_id'] ?? '';
        $userName = $_SESSION['user_name'] ?? $_SESSION['username'] ?? 'Yönetici';
        
        $result = $this->approvalService->rejectRequest($approvalId, $userId, $userName, $reason);
        
        if ($result) {
            // Canlı bildirimlerden düşmesi için ilgili bildirimi okundu işaretle
            $notificationService = \App\Core\DependencyFactory::getNotificationService();
            $notificationService->markAsReadByApprovalId($approvalId);
            $this->toastNotificationService->sendApiResponse('success', 'notifications.success.rejected', [], 200);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.rejection_failed', [], 500);
        }
    }
}
