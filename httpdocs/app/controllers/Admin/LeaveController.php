<?php
namespace App\Controllers\Admin;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';
require_once __DIR__ . '/../../helpers/functions.php';

use App\Core\Controller;

class LeaveController extends Controller {
    protected $leaveService;
    
    public function __construct() {
        parent::__construct();
        $this->leaveService = \App\Core\DependencyFactory::getLeaveService();
    }
    
    /** Ensure tenant context for business API; skip for qodmin (super admin). */
    private function ensureContextForBusiness(): void {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($uri, '/api/business/') !== false) {
            $this->ensureTenantContext();
        }
    }
    
    /** JSON API yolu (/api/...) — toast yerine apiResponse. */
    private function isJsonLeaveApiPath(): bool {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        return \strpos($uri, '/api/') !== false;
    }

    private function leaveApiError(string $messageKey, int $code = 400): void {
        if ($this->isJsonLeaveApiPath()) {
            $this->apiResponse(['success' => false, 'message' => $messageKey], $code);
        }
        $this->toastNotificationService->sendApiResponse('error', $messageKey, [], $code);
    }

    private function leaveApiSuccess(string $messageKey, array $extra = [], int $code = 200): void {
        if ($this->isJsonLeaveApiPath()) {
            $this->apiResponse(\array_merge(['success' => true, 'message' => $messageKey], $extra), $code);
        }
        $this->toastNotificationService->sendApiResponse('success', $messageKey, $extra, $code);
    }

    /** Check that user belongs to current tenant (business). Super admin bypasses. */
    private function userBelongsToTenant(string $userId): bool {
        if ($this->isSuperAdmin()) {
            return true;
        }
        $tenantId = \App\Core\TenantContext::getId();
        if (!$tenantId) {
            return false;
        }
        $userRepo = \App\Core\DependencyFactory::getUserRepository();
        $userBusinessId = $userRepo->getBusinessIdByUserId($userId);
        return $userBusinessId !== null && trim((string)$userBusinessId) === trim((string)$tenantId);
    }
    
    /** Check that leave (with user_id) belongs to current tenant. */
    private function leaveBelongsToTenant(array $leave): bool {
        $userId = $leave['user_id'] ?? '';
        return $userId !== '' && $this->userBelongsToTenant($userId);
    }
    
    /**
     * List leaves for the current tenant, optionally filtered by status/user.
     * Intended for the HR leaves dashboard.
     */
    public function listLeaves(): void {
        $this->requirePermission('hr.leave.approve');
        $this->ensureContextForBusiness();
        $qp = \App\Core\RequestParser::getQueryParams();
        $status = isset($qp['status']) && $qp['status'] !== '' ? strtoupper((string)$qp['status']) : null;

        $rows = $status
            ? $this->leaveService->getByStatus($status)
            : ($this->leaveService->getAll() ?? []);

        // Tenant isolation: drop rows whose owner is outside the current tenant.
        $filtered = [];
        foreach ((array)$rows as $row) {
            if (is_array($row) && $this->leaveBelongsToTenant($row)) {
                $filtered[] = $row;
            }
        }
        $this->apiResponse(['success' => true, 'data' => $filtered]);
    }

    /**
     * Approve a leave request.
     */
    public function approveLeave(string $leaveId): void {
        $this->requirePermission('hr.leave.approve');
        $this->ensureContextForBusiness();
        $leave = $this->leaveService->getById($leaveId);
        if (!$leave || !$this->leaveBelongsToTenant($leave)) {
            \App\Core\ResponseHandler::error('Kayıt bulunamadı', 'NOT_FOUND', 404);
            return;
        }
        $approver = (string)($_SESSION['user_id'] ?? '');
        $ok = $this->leaveService->approveLeave($leaveId, $approver);
        $this->apiResponse(['success' => (bool)$ok]);
    }

    /**
     * Reject a leave request.
     */
    public function rejectLeave(string $leaveId): void {
        $this->requirePermission('hr.leave.approve');
        $this->ensureContextForBusiness();
        $leave = $this->leaveService->getById($leaveId);
        if (!$leave || !$this->leaveBelongsToTenant($leave)) {
            \App\Core\ResponseHandler::error('Kayıt bulunamadı', 'NOT_FOUND', 404);
            return;
        }
        $approver = (string)($_SESSION['user_id'] ?? '');
        $ok = $this->leaveService->rejectLeave($leaveId, $approver);
        $this->apiResponse(['success' => (bool)$ok]);
    }

    /**
     * Return the logged-in user's own leaves (profile page endpoint).
     */
    public function myLeaves(): void {
        $this->requireLogin();
        $userId = (string)($_SESSION['user_id'] ?? '');
        if ($userId === '') {
            \App\Core\ResponseHandler::error('Yetkisiz', 'UNAUTHORIZED', 401);
            return;
        }
        $rows = $this->leaveService->getByUserId($userId);
        $this->apiResponse(['success' => true, 'data' => $rows]);
    }

    /**
     * Render the leaves admin dashboard view.
     */
    public function dashboard(): void {
        $this->requirePermission('hr.leave.approve');
        $this->ensureTenantContext();
        $this->ensureContextForBusiness();
        $staffMembers = [];
        $leaveTypes = [];
        try {
            $userService = \App\Core\DependencyFactory::getUserService();
            $all = $userService->getAll() ?? [];
            $tenantId = \App\Core\TenantContext::getId();
            foreach ($all as $u) {
                if (!\is_array($u)) {
                    continue;
                }
                $bid = $u['business_id'] ?? $u['tenant_id'] ?? null;
                if ($this->isSuperAdmin()) {
                    $staffMembers[] = ['user_id' => $u['user_id'] ?? '', 'name' => $u['name'] ?? ''];
                } elseif ($tenantId && $bid !== null && (string)$bid === (string)$tenantId) {
                    $staffMembers[] = ['user_id' => $u['user_id'] ?? '', 'name' => $u['name'] ?? ''];
                }
            }
            $leaveTypes = \App\Core\DependencyFactory::getLeaveTypeService()->getActive();
        } catch (\Throwable $e) {
            \App\Core\Logger::error('LeaveController::dashboard: ' . $e->getMessage());
        }
        $this->view('admin/leaves', [
            'is_super_admin' => $this->isSuperAdmin(),
            'staff_members' => $staffMembers,
            'leave_types' => $leaveTypes,
        ]);
    }

    /**
     * Aktif izin türleri (form select).
     */
    public function listLeaveTypes(): void {
        $this->requirePermission('hr.leave.approve');
        $this->ensureContextForBusiness();
        $types = \App\Core\DependencyFactory::getLeaveTypeService()->getActive();
        $this->apiResponse(['success' => true, 'data' => $types]);
    }

    /**
     * Personel / dönem bazlı izin özeti + satırlar (rapor).
     */
    public function report(): void {
        $this->requirePermission('hr.leave.approve');
        $this->ensureContextForBusiness();
        $qp = \App\Core\RequestParser::getQueryParams();
        $userId = isset($qp['user_id']) && (string)$qp['user_id'] !== '' ? (string)$qp['user_id'] : null;
        $start = (string)($qp['start'] ?? date('Y-01-01'));
        $end = (string)($qp['end'] ?? date('Y-12-31'));
        if ($userId !== null && !$this->userBelongsToTenant($userId)) {
            $this->apiResponse(['success' => false, 'message' => 'FORBIDDEN'], 403);
            return;
        }
        $rows = $this->leaveService->getByDateRange($start, $end) ?: [];
        $filtered = [];
        foreach ($rows as $row) {
            if (!\is_array($row) || !$this->leaveBelongsToTenant($row)) {
                continue;
            }
            if ($userId !== null && (string)($row['user_id'] ?? '') !== $userId) {
                continue;
            }
            $filtered[] = $row;
        }
        $byType = [];
        $totalDays = 0.0;
        foreach ($filtered as $r) {
            $d = (float)($r['total_days'] ?? 0);
            $totalDays += $d;
            $tid = (string)($r['leave_type_id'] ?? 'unknown');
            $tname = (string)($r['leave_type_name'] ?? $tid);
            if (!isset($byType[$tid])) {
                $byType[$tid] = [
                    'leave_type_id' => $tid,
                    'leave_type_name' => $tname,
                    'days' => 0.0,
                    'count' => 0,
                ];
            }
            $byType[$tid]['days'] += $d;
            $byType[$tid]['count']++;
        }
        $this->apiResponse([
            'success' => true,
            'data' => [
                'rows' => $filtered,
                'summary' => [
                    'total_days' => $totalDays,
                    'request_count' => \count($filtered),
                    'by_type' => \array_values($byType),
                ],
                'range' => ['start' => $start, 'end' => $end],
            ],
        ]);
    }

    /**
     * İK: personele izin ekle / talep oluştur (JSON).
     */
    public function createManaged(): void {
        $this->requirePermission('hr.leave.approve');
        $this->ensureContextForBusiness();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->apiResponse(['success' => false, 'message' => 'METHOD_NOT_ALLOWED'], 405);
            return;
        }
        $data = \App\Core\RequestParser::getRequestData();
        if (empty($data)) {
            $jb = \App\Core\RequestParser::getJsonBody();
            $data = \is_array($jb) ? $jb : [];
        }
        $userId = (string)($data['user_id'] ?? '');
        $leaveTypeId = (string)($data['leave_type_id'] ?? '');
        $startDate = (string)($data['start_date'] ?? '');
        $endDate = (string)($data['end_date'] ?? '');
        $reason = (string)($data['reason'] ?? '');
        $notes = (string)($data['notes'] ?? '');
        $status = strtoupper((string)($data['status'] ?? 'PENDING'));
        if (!\in_array($status, ['PENDING', 'APPROVED'], true)) {
            $status = 'PENDING';
        }
        if ($userId === '' || $leaveTypeId === '' || $startDate === '' || $endDate === '') {
            $this->apiResponse(['success' => false, 'message' => 'MISSING_FIELDS'], 400);
            return;
        }
        if (!$this->userBelongsToTenant($userId)) {
            $this->apiResponse(['success' => false, 'message' => 'FORBIDDEN'], 403);
            return;
        }
        $start = \DateTime::createFromFormat('Y-m-d', $startDate);
        $end = \DateTime::createFromFormat('Y-m-d', $endDate);
        if (!$start || !$end || $end < $start) {
            $this->apiResponse(['success' => false, 'message' => 'INVALID_DATES'], 400);
            return;
        }
        $totalDays = $this->leaveService->calculateDays($startDate, $endDate);
        $leaveData = [
            'leave_id' => generateId('lv'),
            'user_id' => $userId,
            'leave_type_id' => $leaveTypeId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_days' => $totalDays,
            'status' => $status,
            'reason' => \sanitizeInput($reason),
            'notes' => \sanitizeInput($notes),
        ];
        if ($status === 'APPROVED') {
            $leaveData['approved_by'] = $_SESSION['user_id'] ?? null;
            $leaveData['approved_at'] = \date('Y-m-d H:i:s');
        }
        $ok = $this->leaveService->create($leaveData);
        if ($ok) {
            $this->apiResponse(['success' => true, 'data' => ['leave_id' => $leaveData['leave_id']]], 201);
        }
        $this->apiResponse(['success' => false, 'message' => 'CREATE_FAILED'], 500);
    }

    public function addLeave() {
        $this->requirePermission('staff.edit');
        $this->ensureContextForBusiness();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 400);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            $data = $_POST;
        }
        
        $userId = $data['user_id'] ?? '';
        $leaveTypeId = $data['leave_type_id'] ?? '';
        $startDate = $data['start_date'] ?? '';
        $endDate = $data['end_date'] ?? '';
        $reason = $data['reason'] ?? '';
        $notes = $data['notes'] ?? '';
        
        if (empty($userId) || empty($leaveTypeId) || empty($startDate) || empty($endDate)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
            return;
        }
        
        $start = \DateTime::createFromFormat('Y-m-d', $startDate);
        $end = \DateTime::createFromFormat('Y-m-d', $endDate);
        
        if (!$start || !$end) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
            return;
        }
        
        if ($end < $start) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.invalid_date_range', [], 400);
            return;
        }
        
        if (!$this->userBelongsToTenant($userId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 403);
            return;
        }
        
        $totalDays = $this->leaveService->calculateDays($startDate, $endDate);
        
        $leaveData = [
            'leave_id' => generateId('lv'),
            'user_id' => $userId,
            'leave_type_id' => $leaveTypeId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_days' => $totalDays,
            'status' => 'APPROVED',
            'approved_by' => $_SESSION['user_id'] ?? null,
            'approved_at' => date('Y-m-d H:i:s'),
            'reason' => sanitizeInput($reason),
            'notes' => sanitizeInput($notes)
        ];
        
        $result = $this->leaveService->create($leaveData);
        
        if ($result) {
            $this->toastNotificationService->sendApiResponse('success', 'notifications.success.leave_added', ['leave_id' => $leaveData['leave_id']], 200);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.create_failed', [], 500);
        }
    }
    
    public function getLeave($leaveId = null) {
        if (!$this->hasPermission('hr.leave.approve') && !$this->hasPermission('staff.view')) {
            $this->requirePermission('hr.leave.approve');
        }
        $this->ensureContextForBusiness();
        $this->ensureTenantContext();
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $leaveId = $leaveId ?? $queryParams['id'] ?? '';
        
        if (empty($leaveId)) {
            $this->leaveApiError('notifications.error.invalid_data', 400);
        }
        
        $leave = $this->leaveService->findById($leaveId);
        
        if (!$leave) {
            $this->leaveApiError('notifications.error.not_found', 404);
        }
        if (!$this->leaveBelongsToTenant($leave)) {
            $this->leaveApiError('notifications.error.unauthorized', 403);
        }
        $this->apiResponse(['success' => true, 'data' => $leave]);
    }
    
    public function updateLeave($leaveId = null) {
        if (!$this->hasPermission('hr.leave.approve') && !$this->hasPermission('staff.edit')) {
            $this->requirePermission('hr.leave.approve');
        }
        $this->ensureContextForBusiness();
        $this->ensureTenantContext();
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $requestData = \App\Core\RequestParser::getRequestData();
        $leaveId = $leaveId ?? $queryParams['id'] ?? $requestData['id'] ?? '';
        
        if (empty($leaveId)) {
            $this->leaveApiError('notifications.error.invalid_data', 400);
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->leaveApiError('notifications.error.invalid_request', 400);
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            $data = $_POST;
        }
        
        $updateData = [];
        
        if (isset($data['leave_type_id'])) {
            $updateData['leave_type_id'] = sanitizeInput($data['leave_type_id']);
        }
        
        if (isset($data['start_date']) && isset($data['end_date'])) {
            $startDate = $data['start_date'];
            $endDate = $data['end_date'];
            
            $start = \DateTime::createFromFormat('Y-m-d', $startDate);
            $end = \DateTime::createFromFormat('Y-m-d', $endDate);
            
            if (!$start || !$end) {
                $this->leaveApiError('notifications.error.invalid_data', 400);
            }
            
            if ($end < $start) {
                $this->leaveApiError('notifications.warning.invalid_date_range', 400);
            }
            
            $updateData['start_date'] = $startDate;
            $updateData['end_date'] = $endDate;
            $updateData['total_days'] = $this->leaveService->calculateDays($startDate, $endDate);
        }
        
        if (isset($data['reason'])) {
            $updateData['reason'] = sanitizeInput($data['reason']);
        }
        
        if (isset($data['notes'])) {
            $updateData['notes'] = sanitizeInput($data['notes']);
        }
        
        if (isset($data['status'])) {
            $updateData['status'] = sanitizeInput($data['status']);
            if ($updateData['status'] === 'APPROVED') {
                $updateData['approved_by'] = $_SESSION['user_id'] ?? null;
                $updateData['approved_at'] = date('Y-m-d H:i:s');
            }
        }
        
        if (empty($updateData)) {
            $this->leaveApiError('notifications.error.invalid_data', 400);
        }
        
        $existing = $this->leaveService->findById($leaveId);
        if (!$existing) {
            $this->leaveApiError('notifications.error.not_found', 404);
        }
        if (!$this->leaveBelongsToTenant($existing)) {
            $this->leaveApiError('notifications.error.unauthorized', 403);
        }
        
        $result = $this->leaveService->update($leaveId, $updateData);
        
        if ($result) {
            $this->leaveApiSuccess('notifications.success.leave_updated', [], 200);
        }
        $this->leaveApiError('notifications.error.update_failed', 500);
    }
    
    public function deleteLeave($leaveId = null) {
        if (!$this->hasPermission('hr.leave.approve') && !$this->hasPermission('staff.edit')) {
            $this->requirePermission('hr.leave.approve');
        }
        $this->ensureContextForBusiness();
        $this->ensureTenantContext();
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $requestData = \App\Core\RequestParser::getRequestData();
        $leaveId = $leaveId ?? $queryParams['id'] ?? $requestData['id'] ?? '';
        
        if (empty($leaveId)) {
            $this->leaveApiError('notifications.error.invalid_data', 400);
        }
        
        $existing = $this->leaveService->findById($leaveId);
        if (!$existing) {
            $this->leaveApiError('notifications.error.not_found', 404);
        }
        if (!$this->leaveBelongsToTenant($existing)) {
            $this->leaveApiError('notifications.error.unauthorized', 403);
        }
        
        $result = $this->leaveService->delete($leaveId);
        
        if ($result) {
            $this->leaveApiSuccess('notifications.success.leave_deleted', [], 200);
        }
        $this->leaveApiError('notifications.error.delete_failed', 500);
    }
}

