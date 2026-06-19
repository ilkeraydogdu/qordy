<?php
namespace App\Controllers\Admin;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';

use App\Core\Controller;
use App\Core\Helpers\ConstantsHelper;

class ShiftsController extends Controller {
    protected $shiftService;
    protected $shiftScheduleService;
    protected $staffScheduleService;
    protected $userService;
    protected $guestStaffService;
    
    public function __construct() {
        parent::__construct();
        $this->shiftService = \App\Core\DependencyFactory::getShiftService();
        $this->shiftScheduleService = \App\Core\DependencyFactory::getShiftScheduleService();
        $this->staffScheduleService = \App\Core\DependencyFactory::getStaffScheduleService();
        $this->userService = \App\Core\DependencyFactory::getUserService();
        $this->guestStaffService = \App\Core\DependencyFactory::getGuestStaffService();
    }

    /** HR veya finans vardiya yetkisi (eski rollerle uyumlu). */
    protected function requireShiftManagePermission(): void {
        if ($this->isSuperAdmin()
            || $this->hasPermission('hr.shift.manage')
            || $this->hasPermission('finance.shifts')) {
            return;
        }
        $this->requirePermission('hr.shift.manage');
    }

    /** Vardiya sayfasını görüntüleme. */
    protected function canViewShiftsPage(): bool {
        return $this->isSuperAdmin()
            || $this->hasPermission('hr.shift.manage')
            || $this->hasPermission('finance.shifts')
            || $this->hasPermission('finance.view');
    }
    
    public function shifts() {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        if (!$this->canViewShiftsPage()) {
            $this->requirePermission('hr.shift.manage');
        }
        
        // Get view type (weekly or monthly)
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $viewType = $queryParams['view'] ?? 'weekly'; // weekly or monthly
        $selectedDate = $queryParams['date'] ?? date('Y-m-d');
        $showAllStaff = ($queryParams['show_all'] ?? '0') === '1';
        
        // Calculate date range based on view type
        if ($viewType === 'monthly') {
            $startDate = date('Y-m-01', strtotime($selectedDate));
            $endDate = date('Y-m-t', strtotime($selectedDate));
        } else {
            // Weekly view - get week start (Monday)
            $date = new \DateTime($selectedDate);
            $dayOfWeek = $date->format('w');
            $daysToMonday = ($dayOfWeek == 0) ? 6 : $dayOfWeek - 1;
            $date->modify("-{$daysToMonday} days");
            $startDate = $date->format('Y-m-d');
            $date->modify('+6 days');
            $endDate = $date->format('Y-m-d');
        }
        
        // Get shift schedules (planned shifts) - with error handling
        $shiftSchedules = [];
        try {
            $shiftSchedules = $this->shiftScheduleService->getByDateRange($startDate, $endDate);
        } catch (\Exception $e) {
            \App\Core\Logger::error("ShiftScheduleService error: " . $e->getMessage());
            $shiftSchedules = [];
        }
        
        // Get actual shifts (completed shifts)
        $endDateFull = $endDate . ' 23:59:59';
        $startDateFull = $startDate . ' 00:00:00';
        $actualShifts = [];
        try {
            $actualShifts = $this->shiftService->getShiftsByDateRange($startDateFull, $endDateFull);
        } catch (\Exception $e) {
            \App\Core\Logger::error("ShiftService error: " . $e->getMessage());
            $actualShifts = [];
        }
        
        // Get all staff for dropdown
        $allStaff = [];
        try {
            $allStaff = $this->userService->getAll();
        } catch (\Exception $e) {
            \App\Core\Logger::error("UserService getAll error: " . $e->getMessage());
            $allStaff = [];
        }
        
        // Filter out customers, only get staff members
        $validStaffRoles = [
            'ROLE_MANAGER', 'ROLE_WAITER', 'ROLE_KITCHEN', 'ROLE_CASHIER',
            'MANAGER', 'WAITER', 'KITCHEN', 'CASHIER'
        ];
        
        $staffMembers = array_filter($allStaff, function($user) use ($validStaffRoles) {
            $userRole = strtoupper(trim($user['role'] ?? $user['role_id'] ?? ''));
            
            if (empty($userRole)) {
                return false;
            }
            
            if ($userRole === 'CUSTOMER' || 
                $userRole === 'ROLE_CUSTOMER' ||
                strpos($userRole, 'CUSTOMER') !== false) {
                return false;
            }
            
            if (in_array($userRole, $validStaffRoles, true)) {
                return true;
            }
            
            if (strpos($userRole, 'ROLE_') === 0) {
                $roleWithoutPrefix = substr($userRole, 5);
                $validRoles = [
                    ConstantsHelper::getRole('MANAGER'),
                    ConstantsHelper::getRole('WAITER'),
                    ConstantsHelper::getRole('KITCHEN'),
                    ConstantsHelper::getRole('CASHIER')
                ];
                if (in_array($roleWithoutPrefix, $validRoles, true)) {
                    return true;
                }
            }
            
            return false;
        });
        $staffMembers = array_values($staffMembers);
        
        // Get guest staff
        $guestStaff = [];
        try {
            $guestStaff = $this->guestStaffService->getActive();
        } catch (\Exception $e) {
            \App\Core\Logger::error("Error getting guest staff: " . $e->getMessage());
            $guestStaff = [];
        }
        
        // Get weekly schedules for all staff (batch loading - optimize N+1 query problem)
        $staffSchedules = [];
        try {
            $staffIds = array_column($staffMembers, 'user_id');
            if (!empty($staffIds)) {
                $staffSchedules = $this->staffScheduleService->getWeeklySchedulesBatch($staffIds);
            }
        } catch (\Exception $e) {
            \App\Core\Logger::error("StaffScheduleService batch error: " . $e->getMessage());
            // Fallback to empty array
            $staffSchedules = [];
        }
        
        $scheduleStats = [
            'total' => is_array($shiftSchedules) ? count($shiftSchedules) : 0,
            'by_status' => [],
            'unique_staff' => 0,
        ];
        if (is_array($shiftSchedules) && $shiftSchedules) {
            $seen = [];
            foreach ($shiftSchedules as $s) {
                if (!is_array($s)) {
                    continue;
                }
                $st = (string)($s['status'] ?? 'PLANNED');
                $scheduleStats['by_status'][$st] = ($scheduleStats['by_status'][$st] ?? 0) + 1;
                $sid = (string)($s['staff_id'] ?? '');
                if ($sid !== '') {
                    $seen[$sid] = true;
                }
            }
            $scheduleStats['unique_staff'] = count($seen);
        }

        try {
            $data = [
                'page_title' => 'Vardiya Planlama',
                'page' => 'shifts',
                'view_type' => $viewType,
                'selected_date' => $selectedDate,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'shift_schedules' => $shiftSchedules,
                'actual_shifts' => $actualShifts,
                'staff_members' => $staffMembers,
                'guest_staff' => $guestStaff,
                'staff_schedules' => $staffSchedules,
                'is_super_admin' => $this->isSuperAdmin(),
                'show_all_staff' => $showAllStaff,
                'schedule_stats' => $scheduleStats,
            ];
            
            $this->view('admin/shifts', $data);
        } catch (\Exception $e) {
            \App\Core\Logger::error("Error rendering shifts view: " . $e->getMessage());
            if ($this->isApiRequest()) {
                $this->apiResponse(['success' => false, 'message' => 'Vardiya sayfası yüklenirken bir hata oluştu.'], 500);
            } else {
                echo "Vardiya sayfası yüklenirken bir hata oluştu. Lütfen tekrar deneyin.";
            }
        }
    }
    
    public function createShift() {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->apiResponse(['success' => false, 'message' => 'notifications.error.invalid_request'], 400);
                return;
            }
            
            $this->requireShiftManagePermission();
            
            $requestData = \App\Core\RequestParser::getRequestData();
            
            // CRITICAL: Ensure business_id is set for tenant isolation
            $tenantId = \App\Core\TenantContext::getId();
            if (!$tenantId && !$this->isSuperAdmin()) {
                \App\Core\Logger::error('Admin/ShiftsController::createShift - No tenant context', [
                    'user_id' => $_SESSION['user_id'] ?? 'unknown'
                ]);
                $this->apiResponse(['success' => false, 'message' => 'Tenant context required'], 400);
                return;
            }
            $staffId = $requestData['staff_id'] ?? '';
            $startDate = $requestData['start_date'] ?? date('Y-m-d');
            $startTime = $requestData['start_time'] ?? '09:00';
            $endTime = $requestData['end_time'] ?? '17:00';
            $openingCash = floatval($requestData['opening_cash'] ?? 0);
            $notes = $requestData['notes'] ?? '';
            
            if (empty($staffId)) {
                $this->apiResponse(['success' => false, 'message' => 'notifications.error.staff_required'], 400);
                return;
            }
            
            $startDateTime = $startDate . ' ' . $startTime . ':00';
            $endDateTime = $startDate . ' ' . $endTime . ':00';
            
            // Check if staff already has a shift on this date
            try {
                $existingShifts = $this->shiftService->getShiftsByStaff($staffId);
                foreach ($existingShifts as $existing) {
                    $existingDate = date('Y-m-d', strtotime($existing['start_time'] ?? ''));
                    if ($existingDate === $startDate && ($existing['status'] ?? '') === 'OPEN') {
                        $this->apiResponse(['success' => false, 'message' => 'notifications.error.shift_already_exists'], 400);
                        return;
                    }
                }
            } catch (\Exception $e) {
                \App\Core\Logger::error("createShift - error checking existing shifts: " . $e->getMessage());
            }
            
            // Get staff name
            try {
                $staff = $this->userService->findByUserId($staffId);
                $staffName = $staff['name'] ?? 'Bilinmeyen';
            } catch (\Exception $e) {
                \App\Core\Logger::error("createShift - error getting staff name: " . $e->getMessage());
                $staffName = 'Bilinmeyen';
            }
            
            $shiftData = [
                'staff_id' => $staffId,
                'staff_name' => $staffName,
                'start_time' => $startDateTime,
                'end_time' => $endDateTime,
                'opening_cash' => $openingCash,
                'status' => 'OPEN',
                'notes' => $notes
            ];
            
            // Add tenant_id for tenant isolation (shifts table uses tenant_id column)
            if ($tenantId) {
                $shiftData['tenant_id'] = $tenantId;
                $shiftData['business_id'] = $tenantId; // legacy noop when column absent
            }

            $result = $this->shiftService->createShift($shiftData);
            
            if ($result) {
                $this->apiResponse(['success' => true, 'message' => 'notifications.success.shift_created'], 200);
            } else {
                $this->apiResponse(['success' => false, 'message' => 'notifications.error.shift_create_failed'], 500);
            }
        } catch (\Exception $e) {
            \App\Core\Logger::error("createShift error: " . $e->getMessage());
            $this->apiResponse(['success' => false, 'message' => 'notifications.error.shift_create_failed', 'error' => $e->getMessage()], 500);
        }
    }
    
    public function updateShift() {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->apiResponse(['success' => false, 'message' => 'notifications.error.invalid_request'], 400);
                return;
            }
            
            $this->requireShiftManagePermission();
            
            $requestData = \App\Core\RequestParser::getRequestData();
            $shiftId = $requestData['shift_id'] ?? '';
            if (empty($shiftId)) {
                $this->apiResponse(['success' => false, 'message' => 'notifications.error.shift_id_required'], 400);
                return;
            }
            
            // CRITICAL: Verify shift belongs to current tenant
            $shift = $this->shiftService->getShiftById($shiftId);
            if ($shift && !$this->isSuperAdmin()) {
                $tenantId = \App\Core\TenantContext::getId();
                $shiftTenantId = $shift['tenant_id'] ?? $shift['business_id'] ?? null;

                if (!$tenantId || ($shiftTenantId !== null && (string)$shiftTenantId !== (string)$tenantId)) {
                    \App\Core\Logger::warning('Admin/ShiftsController::updateShift - Tenant isolation violation', [
                        'shift_id' => $shiftId,
                        'shift_tenant_id' => $shiftTenantId,
                        'tenant_id' => $tenantId
                    ]);
                    $this->apiResponse(['success' => false, 'message' => 'notifications.error.unauthorized'], 403);
                    return;
                }
            }
            
            $startDate = $requestData['start_date'] ?? '';
            $startTime = $requestData['start_time'] ?? '';
            $endTime = $requestData['end_time'] ?? '';
            $openingCash = isset($requestData['opening_cash']) ? floatval($requestData['opening_cash']) : null;
            $notes = $requestData['notes'] ?? '';
            
            $shiftData = [];
            
            if ($startDate && $startTime) {
                $shiftData['start_time'] = $startDate . ' ' . $startTime . ':00';
            }
            
            if ($startDate && $endTime) {
                $shiftData['end_time'] = $startDate . ' ' . $endTime . ':00';
            }
            
            if ($openingCash !== null) {
                $shiftData['opening_cash'] = $openingCash;
            }
            
            if ($notes !== '') {
                $shiftData['notes'] = $notes;
            }
            
            if (empty($shiftData)) {
                $this->apiResponse(['success' => false, 'message' => 'notifications.error.no_data_to_update'], 400);
                return;
            }
            
            $result = $this->shiftService->updateShift($shiftId, $shiftData);
            
            if ($result) {
                $this->apiResponse(['success' => true, 'message' => 'notifications.success.shift_updated'], 200);
            } else {
                $this->apiResponse(['success' => false, 'message' => 'notifications.error.shift_update_failed'], 500);
            }
        } catch (\Exception $e) {
            \App\Core\Logger::error("updateShift error: " . $e->getMessage());
            $this->apiResponse(['success' => false, 'message' => 'notifications.error.shift_update_failed', 'error' => $e->getMessage()], 500);
        }
    }
    
    public function deleteShift() {
        try {
            $this->ensureTenantContext();
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->apiResponse(['success' => false, 'message' => 'notifications.error.invalid_request'], 400);
                return;
            }
            
            $this->requireShiftManagePermission();
            
            $requestData = \App\Core\RequestParser::getRequestData();
            $queryParams = \App\Core\RequestParser::getQueryParams();
            $shiftId = $requestData['shift_id'] ?? $queryParams['id'] ?? '';
            if (empty($shiftId)) {
                $this->apiResponse(['success' => false, 'message' => 'notifications.error.shift_id_required'], 400);
                return;
            }
            $shift = $this->shiftService->getShiftById($shiftId);
            if ($shift && !$this->isSuperAdmin()) {
                $tenantId = \App\Core\TenantContext::getId();
                $shiftTenant = $shift['tenant_id'] ?? $shift['business_id'] ?? null;
                if ($tenantId && $shiftTenant !== null && (string)$shiftTenant !== (string)$tenantId) {
                    $this->apiResponse(['success' => false, 'message' => 'notifications.error.unauthorized'], 403);
                    return;
                }
            }
            
            $result = $this->shiftService->deleteShift($shiftId);
            
            if ($result) {
                $this->apiResponse(['success' => true, 'message' => 'notifications.success.shift_deleted'], 200);
            } else {
                $this->apiResponse(['success' => false, 'message' => 'notifications.error.shift_delete_failed'], 500);
            }
        } catch (\Exception $e) {
            \App\Core\Logger::error("deleteShift error: " . $e->getMessage());
            $this->apiResponse(['success' => false, 'message' => 'notifications.error.shift_delete_failed', 'error' => $e->getMessage()], 500);
        }
    }
    
    public function saveStaffSchedule() {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->apiResponse(['success' => false, 'message' => 'notifications.error.invalid_request'], 400);
                return;
            }
            
            $this->requireShiftManagePermission();
            
            $requestData = \App\Core\RequestParser::getRequestData();
            $staffId = $requestData['staff_id'] ?? '';
            if (empty($staffId)) {
                $this->apiResponse(['success' => false, 'message' => 'notifications.error.staff_required'], 400);
                return;
            }
            
            $weeklySchedule = [];
            for ($day = 0; $day < 7; $day++) {
                $isWorking = isset($requestData["day_{$day}_working"]) && $requestData["day_{$day}_working"] == '1';
                $weeklySchedule[$day] = [
                    'is_working' => $isWorking ? 1 : 0,
                    'start_time' => $requestData["day_{$day}_start"] ?? '09:00:00',
                    'end_time' => $requestData["day_{$day}_end"] ?? '17:00:00',
                    'break_start' => $requestData["day_{$day}_break_start"] ?? null,
                    'break_end' => $requestData["day_{$day}_break_end"] ?? null
                ];
            }
            
            $result = $this->staffScheduleService->saveWeeklySchedule($staffId, $weeklySchedule);
            
            if ($result) {
                $this->apiResponse(['success' => true, 'message' => 'notifications.success.schedule_saved'], 200);
            } else {
                $this->apiResponse(['success' => false, 'message' => 'notifications.error.schedule_save_failed'], 500);
            }
        } catch (\Exception $e) {
            \App\Core\Logger::error("saveStaffSchedule error: " . $e->getMessage());
            $this->apiResponse(['success' => false, 'message' => 'notifications.error.schedule_save_failed', 'error' => $e->getMessage()], 500);
        }
    }
    
    public function createShiftSchedule() {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->apiResponse(['success' => false, 'message' => 'notifications.error.invalid_request'], 400);
                return;
            }
            
            $this->requireShiftManagePermission();
            
            $requestData = \App\Core\RequestParser::getRequestData();
            $staffType = $requestData['staff_type'] ?? 'USER';
            $staffId = $requestData['staff_id'] ?? '';
            $shiftDate = $requestData['shift_date'] ?? '';
            $startTime = $requestData['start_time'] ?? '09:00';
            $endTime = $requestData['end_time'] ?? '17:00';
            $shiftType = $requestData['shift_type'] ?? 'REGULAR';
            $notes = $requestData['notes'] ?? '';
            
            $firstName = $requestData['first_name'] ?? '';
            $lastName = $requestData['last_name'] ?? '';
            $phone = $requestData['phone'] ?? '';
            $guestStaffId = null;
            
            if ($staffType === 'GUEST_STAFF') {
                if (empty($firstName) || empty($lastName) || empty($phone)) {
                    $this->apiResponse(['success' => false, 'message' => 'notifications.error.guest_staff_required'], 400);
                    return;
                }
                
                try {
                    $guestStaffId = $this->guestStaffService->createOrGet([
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'phone' => $phone,
                        'email' => $requestData['email'] ?? null
                    ]);
                    $staffId = $guestStaffId;
                } catch (\Exception $e) {
                    \App\Core\Logger::error("createShiftSchedule - guest staff creation error: " . $e->getMessage());
                    $this->apiResponse(['success' => false, 'message' => 'notifications.error.guest_staff_creation_failed', 'error' => $e->getMessage()], 500);
                    return;
                }
            } else {
                if (empty($staffId) || empty($shiftDate)) {
                    $this->apiResponse(['success' => false, 'message' => 'notifications.error.required_fields'], 400);
                    return;
                }
            }
            
            if (empty($shiftDate)) {
                $this->apiResponse(['success' => false, 'message' => 'notifications.error.required_fields'], 400);
                return;
            }
            
            $today = date('Y-m-d');
            if ($shiftDate < $today) {
                $this->apiResponse(['success' => false, 'message' => 'notifications.error.no_past_date'], 400);
                return;
            }
            
            // CRITICAL: Ensure business_id is set for tenant isolation
            $tenantId = \App\Core\TenantContext::getId();
            if (!$tenantId && !$this->isSuperAdmin()) {
                \App\Core\Logger::error('Admin/ShiftsController::createShiftSchedule - No tenant context', [
                    'user_id' => $_SESSION['user_id'] ?? 'unknown'
                ]);
                $this->apiResponse(['success' => false, 'message' => 'Tenant context required'], 400);
                return;
            }
            
            $scheduleData = [
                'staff_id' => $staffId,
                'staff_type' => $staffType,
                'shift_date' => $shiftDate,
                'start_time' => $startTime . ':00',
                'end_time' => $endTime . ':00',
                'shift_type' => $shiftType,
                'status' => 'PLANNED',
                'notes' => $notes,
                'created_by' => $_SESSION['user_id'] ?? ''
            ];
            
            // Add tenant_id for tenant isolation (shift_schedules uses tenant_id column)
            if ($tenantId) {
                $scheduleData['tenant_id'] = $tenantId;
                $scheduleData['business_id'] = $tenantId; // legacy noop when column absent
            }

            if ($staffType === 'GUEST_STAFF') {
                $scheduleData['guest_staff_id'] = $guestStaffId;
                $scheduleData['staff_name'] = trim($firstName . ' ' . $lastName);
                $scheduleData['staff_phone'] = $phone;
            }

            $result = $this->shiftScheduleService->createSchedule($scheduleData);
            
            if ($result) {
                $this->apiResponse(['success' => true, 'message' => 'notifications.success.shift_schedule_created'], 200);
            } else {
                $this->apiResponse(['success' => false, 'message' => 'notifications.error.shift_schedule_create_failed'], 500);
            }
        } catch (\Exception $e) {
            \App\Core\Logger::error("createShiftSchedule error: " . $e->getMessage());
            $this->apiResponse(['success' => false, 'message' => 'notifications.error.shift_schedule_create_failed', 'error' => $e->getMessage()], 500);
        }
    }
    
    public function createWeeklyShiftSchedule() {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->apiResponse(['success' => false, 'message' => 'notifications.error.invalid_request'], 400);
                return;
            }
            
            $this->requireShiftManagePermission();
            
            // CRITICAL: Ensure business_id is set for tenant isolation
            $tenantId = \App\Core\TenantContext::getId();
            if (!$tenantId && !$this->isSuperAdmin()) {
                \App\Core\Logger::error('Admin/ShiftsController::createWeeklyShiftSchedule - No tenant context', [
                    'user_id' => $_SESSION['user_id'] ?? 'unknown'
                ]);
                $this->apiResponse(['success' => false, 'message' => 'Tenant context required'], 400);
                return;
            }
            
            $requestData = \App\Core\RequestParser::getRequestData();
            $staffType = $requestData['staff_type'] ?? 'USER';
            $staffId = $requestData['staff_id'] ?? '';
            $weekStartDate = $requestData['week_start_date'] ?? '';
            
            $firstName = $requestData['first_name'] ?? '';
            $lastName = $requestData['last_name'] ?? '';
            $phone = $requestData['phone'] ?? '';
            $guestStaffId = null;
            
            if ($staffType === 'GUEST_STAFF') {
                if (empty($firstName) || empty($lastName) || empty($phone)) {
                    $this->apiResponse(['success' => false, 'message' => 'notifications.error.guest_staff_required'], 400);
                    return;
                }
                
                try {
                    $guestStaffId = $this->guestStaffService->createOrGet([
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'phone' => $phone,
                        'email' => $requestData['email'] ?? null
                    ]);
                    $staffId = $guestStaffId;
                } catch (\Exception $e) {
                    \App\Core\Logger::error("createWeeklyShiftSchedule - guest staff creation error: " . $e->getMessage());
                    $this->apiResponse(['success' => false, 'message' => 'notifications.error.guest_staff_creation_failed', 'error' => $e->getMessage()], 500);
                    return;
                }
            } else {
                if (empty($staffId) || empty($weekStartDate)) {
                    $this->apiResponse(['success' => false, 'message' => 'notifications.error.required_fields'], 400);
                    return;
                }
            }
            
            if (empty($weekStartDate)) {
                $this->apiResponse(['success' => false, 'message' => 'notifications.error.required_fields'], 400);
                return;
            }
            
            $today = date('Y-m-d');
            if ($weekStartDate < $today) {
                $this->apiResponse(['success' => false, 'message' => 'notifications.error.no_past_date'], 400);
                return;
            }
            
            $startDate = new \DateTime($weekStartDate);
            $dayOfWeek = (int)$startDate->format('w');
            $daysToMonday = ($dayOfWeek == 0) ? 6 : $dayOfWeek - 1;
            $startDate->modify("-{$daysToMonday} days");
            
            $createdCount = 0;
            $skippedCount = 0;
            $isGuestStaff = ($staffType === 'GUEST_STAFF');
            
            $weeklySchedule = [];
            if (!$isGuestStaff) {
                try {
                    $weeklySchedule = $this->staffScheduleService->getWeeklySchedule($staffId);
                } catch (\Exception $e) {
                    \App\Core\Logger::error("Error getting weekly schedule: " . $e->getMessage());
                }
            }
            
            for ($i = 0; $i < 7; $i++) {
                try {
                    $currentDate = clone $startDate;
                    $currentDate->modify("+{$i} days");
                    $dateStr = $currentDate->format('Y-m-d');
                    
                    if ($dateStr < $today) {
                        $skippedCount++;
                        continue;
                    }
                    
                    $dayEnabled = isset($requestData["day_{$i}_enabled"]) && $requestData["day_{$i}_enabled"] == '1';
                    if (!$dayEnabled) {
                        continue;
                    }
                    
                    try {
                        $existing = $this->shiftScheduleService->getRepository()->getByStaffAndDate($staffId, $dateStr);
                        if ($existing) {
                            $skippedCount++;
                            continue;
                        }
                    } catch (\Exception $e) {
                        \App\Core\Logger::error("Error checking existing shift: " . $e->getMessage());
                    }
                    
                    $dayOfWeek = (int)$currentDate->format('w');
                    $startTime = $requestData["day_{$i}_start"] ?? '09:00';
                    $endTime = $requestData["day_{$i}_end"] ?? '17:00';
                    
                    if (!$isGuestStaff && isset($weeklySchedule[$dayOfWeek]) && 
                        ($weeklySchedule[$dayOfWeek]['is_working'] ?? 0) == 1) {
                        $daySchedule = $weeklySchedule[$dayOfWeek];
                        $startTime = $daySchedule['start_time'] ? substr($daySchedule['start_time'], 0, 5) : $startTime;
                        $endTime = $daySchedule['end_time'] ? substr($daySchedule['end_time'], 0, 5) : $endTime;
                    }
                    
                    if (strlen($startTime) == 5) {
                        $startTime .= ':00';
                    }
                    if (strlen($endTime) == 5) {
                        $endTime .= ':00';
                    }
                    
                    $scheduleData = [
                        'staff_id' => $staffId,
                        'staff_type' => $staffType,
                        'shift_date' => $dateStr,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'shift_type' => 'REGULAR',
                        'status' => 'PLANNED',
                        'created_by' => $_SESSION['user_id'] ?? ''
                    ];
                    
                    // Add tenant_id for tenant isolation (shift_schedules uses tenant_id column)
                    if ($tenantId) {
                        $scheduleData['tenant_id'] = $tenantId;
                        $scheduleData['business_id'] = $tenantId;
                    }
                    
                    if ($isGuestStaff) {
                        $scheduleData['guest_staff_id'] = $guestStaffId;
                        $scheduleData['staff_name'] = trim($firstName . ' ' . $lastName);
                        $scheduleData['staff_phone'] = $phone;
                    }
                    
                    $result = $this->shiftScheduleService->createSchedule($scheduleData);
                    if ($result) {
                        $createdCount++;
                    }
                } catch (\Exception $e) {
                    \App\Core\Logger::error("Error creating shift for day {$i}: " . $e->getMessage());
                }
            }
            
            $this->apiResponse([
                'success' => true,
                'message' => 'notifications.success.weekly_shifts_created',
                'created' => $createdCount,
                'skipped' => $skippedCount
            ], 200);
        } catch (\Exception $e) {
            \App\Core\Logger::error("createWeeklyShiftSchedule error: " . $e->getMessage());
            $this->apiResponse(['success' => false, 'message' => 'notifications.error.weekly_shifts_create_failed', 'error' => $e->getMessage()], 500);
        }
    }
    
    public function generateShiftsFromSchedule() {
        $this->ensureTenantContext();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->apiResponse(['success' => false, 'message' => 'notifications.error.invalid_request'], 400);
            return;
        }
        
        $this->requireShiftManagePermission();
        
        $requestData = \App\Core\RequestParser::getRequestData();
        $staffId = $requestData['staff_id'] ?? '';
        $startDate = $requestData['start_date'] ?? date('Y-m-d');
        $endDate = $requestData['end_date'] ?? date('Y-m-d', strtotime('+7 days'));
        
        if (empty($staffId)) {
            $this->apiResponse(['success' => false, 'message' => 'notifications.error.staff_required'], 400);
            return;
        }
        
        $count = $this->shiftScheduleService->generateFromWeeklySchedule($staffId, $startDate, $endDate);
        
        $this->apiResponse(['success' => true, 'message' => 'notifications.success.shifts_generated', 'count' => $count], 200);
    }

    /**
     * Clock-in endpoint: marks actual_start on a schedule row.
     * Phase 2 — needed for overtime tracking.
     */
    public function clockIn(string $scheduleId): void {
        $this->ensureTenantContext();
        $this->requirePermission('finance.view');
        $ok = $this->shiftScheduleService->clockIn($scheduleId);
        $this->apiResponse(['success' => (bool)$ok]);
    }

    /**
     * Clock-out endpoint: marks actual_end and computes overtime_minutes.
     * Optional body: {"overtime_minutes": 45}
     */
    public function clockOut(string $scheduleId): void {
        $this->ensureTenantContext();
        $this->requirePermission('finance.view');
        $body = \App\Core\RequestParser::getJsonBody() ?: [];
        $ot = isset($body['overtime_minutes']) ? (int)$body['overtime_minutes'] : null;
        $ok = $this->shiftScheduleService->clockOut($scheduleId, null, $ot);
        $this->apiResponse(['success' => (bool)$ok]);
    }

    /**
     * JSON: planlı vardiya detayı (düzenleme formu).
     */
    public function getShiftSchedule(string $scheduleId): void {
        $this->ensureTenantContext();
        $this->requireShiftManagePermission();
        $repo = $this->shiftScheduleService->getRepository();
        $row = $repo->findById($scheduleId);
        if (!$row || !\is_array($row)) {
            $this->apiResponse(['success' => false, 'message' => 'NOT_FOUND'], 404);
            return;
        }
        if (($row['staff_type'] ?? 'USER') === 'USER' && !empty($row['staff_id'])) {
            try {
                $u = $this->userService->findByUserId($row['staff_id']);
                $row['staff_display_name'] = $u['name'] ?? '';
            } catch (\Throwable $e) {
                $row['staff_display_name'] = '';
            }
        } else {
            $row['staff_display_name'] = trim((string)($row['staff_name'] ?? ''));
        }
        $this->apiResponse(['success' => true, 'data' => $row]);
    }

    public function updateShiftSchedule(string $scheduleId): void {
        $this->ensureTenantContext();
        $this->requireShiftManagePermission();
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method !== 'POST' && $method !== 'PUT') {
            $this->apiResponse(['success' => false, 'message' => 'METHOD_NOT_ALLOWED'], 405);
            return;
        }
        $repo = $this->shiftScheduleService->getRepository();
        $existing = $repo->findById($scheduleId);
        if (!$existing) {
            $this->apiResponse(['success' => false, 'message' => 'NOT_FOUND'], 404);
            return;
        }
        $data = \App\Core\RequestParser::getRequestData();
        if (empty($data) && $method === 'PUT') {
            $data = \App\Core\RequestParser::getJsonBody() ?: [];
        }
        $update = [];
        if (!empty($data['shift_date'])) {
            $update['shift_date'] = substr(preg_replace('/[^0-9\-]/', '', (string)$data['shift_date']), 0, 10);
        }
        if (isset($data['start_time']) && $data['start_time'] !== '') {
            $st = (string)$data['start_time'];
            $update['start_time'] = (\strlen($st) === 5 && strpos($st, ':') !== false) ? $st . ':00' : $st;
        }
        if (isset($data['end_time']) && $data['end_time'] !== '') {
            $et = (string)$data['end_time'];
            $update['end_time'] = (\strlen($et) === 5 && strpos($et, ':') !== false) ? $et . ':00' : $et;
        }
        if (isset($data['shift_type']) && $data['shift_type'] !== '') {
            $update['shift_type'] = strtoupper(substr(preg_replace('/[^A-Z_]/i', '', (string)$data['shift_type']), 0, 32));
        }
        if (\array_key_exists('notes', $data)) {
            $update['notes'] = (string)$data['notes'];
        }
        if (isset($data['status']) && $data['status'] !== '') {
            $update['status'] = strtoupper(substr(preg_replace('/[^A-Z_]/i', '', (string)$data['status']), 0, 32));
        }
        if (empty($update)) {
            $this->apiResponse(['success' => false, 'message' => 'NO_FIELDS'], 400);
            return;
        }
        $ok = $this->shiftScheduleService->updateSchedule($scheduleId, $update);
        $this->apiResponse(['success' => (bool)$ok]);
    }

    public function deleteShiftSchedule(string $scheduleId): void {
        $this->ensureTenantContext();
        $this->requireShiftManagePermission();
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method !== 'POST' && $method !== 'DELETE') {
            $this->apiResponse(['success' => false, 'message' => 'METHOD_NOT_ALLOWED'], 405);
            return;
        }
        $repo = $this->shiftScheduleService->getRepository();
        if (!$repo->findById($scheduleId)) {
            $this->apiResponse(['success' => false, 'message' => 'NOT_FOUND'], 404);
            return;
        }
        $ok = $this->shiftScheduleService->deleteSchedule($scheduleId);
        $this->apiResponse(['success' => (bool)$ok]);
    }

    /**
     * Return the logged-in user's upcoming shifts (7 days window).
     * Phase 2 — "Benim vardiyam" mobile+web endpoint.
     */
    /**
     * Render the personal "Vardiyalarım" page.
     */
    public function mySchedulePage(): void {
        $this->requireLogin();
        $this->view('admin/my-schedule');
    }

    public function mySchedule(): void {
        $this->requireLogin();
        $this->ensureTenantContext();

        $userId = (string)($_SESSION['user_id'] ?? '');
        if ($userId === '') {
            $this->apiResponse(['success' => false, 'message' => 'UNAUTHORIZED'], 401);
            return;
        }

        $qp = \App\Core\RequestParser::getQueryParams();
        $start = $qp['start'] ?? date('Y-m-d');
        $end   = $qp['end']   ?? date('Y-m-d', strtotime('+7 days'));

        $rows = $this->shiftScheduleService->getByDateRange($start, $end) ?: [];
        $mine = [];
        foreach ($rows as $row) {
            if (isset($row['staff_id']) && (string)$row['staff_id'] === $userId) {
                $mine[] = $row;
            }
        }
        $this->apiResponse(['success' => true, 'data' => $mine]);
    }
}

