<?php
namespace App\Controllers;

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/DependencyFactory.php';
use App\Core\Helpers\ConstantsHelper;
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    
class ReservationController extends \App\Core\Controller {
    protected $reservationService;
    protected $tableService;
    
    public function __construct() {
        parent::__construct(); // Initialize auth and other services
        $this->reservationService = \App\Core\DependencyFactory::getReservationService();
        $this->tableService = \App\Core\DependencyFactory::getTableService();
    }
    
    public function index() {
        $this->ensureTenantContext();
        $this->requirePermission('reservations.view');
        
        // Super admin kontrolü
        $isSuperAdmin = $this->isSuperAdmin();
        
        // İşletme ID'sini query parametresinden al (super admin için)
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $businessId = $queryParams['business_id'] ?? null;
        
        if ($isSuperAdmin && $businessId) {
            // Tenant context'i işletme ID'sine göre set et
            try {
                $customerService = \App\Core\DependencyFactory::getCustomerService();
                $customer = $customerService->getById($businessId);
                if ($customer) {
                    \App\Core\TenantContext::set($customer);
                }
            } catch (\Exception $e) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('Failed to set tenant context from business_id', [
                        'business_id' => $businessId,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
        
        $data = [
            'reservations' => $this->reservationService->getAllReservations(),
            'tables' => $this->tableService->getAllTables(),
            'is_super_admin' => $isSuperAdmin
        ];
        
        $this->view('admin/reservations', $data);
    }
    
    /**
     * Legacy GET handler for /reservations/add — the standalone add page
     * has been retired in favour of the AJAX modal on /reservations.
     * Keep the route alive as a permanent redirect so bookmarks don't
     * 404 the user.
     */
    public function showAddForm() {
        \App\Core\HelperLoader::ensureLoaded();
        header('Location: ' . getAdminUrl('reservations'));
        exit;
    }

    public function add() {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();

        $isAjax = $this->isAjaxRequest();
        \App\Core\HelperLoader::ensureLoaded();
        $listUrl = getAdminUrl('reservations');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            if ($isAjax) {
                $this->jsonResponse(['success' => false, 'errors' => ['Geçersiz istek metodu.']], 405);
                return;
            }
            header('Location: ' . $listUrl);
            exit;
        }

        $this->checkPermissionOrFail('reservations.create');

        $requestData = \App\Core\RequestParser::getRequestData();

        // CRITICAL: Verify table belongs to current tenant if table_id is provided
        $tableId = !empty($requestData['table_id']) ? $requestData['table_id'] : null;
        if ($tableId) {
            $table = $this->tableService->getTableById($tableId);
            if (!$table) {
                return $this->respondReservation(
                    $isAjax, false, ['Seçilen masa bulunamadı.'], 'error',
                    'notifications.error.table_not_found', $listUrl, 404
                );
            }

            if (!$this->isSuperAdmin()) {
                $tenantId = \App\Core\TenantContext::getId();
                $tableTenantId = $table['tenant_id'] ?? $table['business_id'] ?? null;
                if (!$tenantId || (string)$tableTenantId !== (string)$tenantId) {
                    \App\Core\Logger::warning('ReservationController::add - Table tenant isolation violation', [
                        'table_id' => $tableId,
                        'table_tenant_id' => $tableTenantId,
                        'tenant_id' => $tenantId
                    ]);
                    return $this->respondReservation(
                        $isAjax, false, ['Bu masaya erişim yetkiniz yok.'], 'error',
                        'notifications.error.unauthorized', $listUrl, 403
                    );
                }
            }
        }

        // Notes and special requests are intentionally no longer read from
        // the form — the UI removed both fields to keep the modal focused
        // on the core reservation details.
        $data = [
            'customer_name' => trim($requestData['customer_name'] ?? ''),
            'contact' => trim($requestData['contact'] ?? ''),
            'customer_email' => trim($requestData['customer_email'] ?? ''),
            'date' => $requestData['date'] ?? date('Y-m-d'),
            'time' => $requestData['time'] ?? '12:00',
            'guests' => intval($requestData['guests'] ?? 1),
            'table_id' => $tableId,
            'status' => $requestData['status'] ?? ConstantsHelper::getOrderStatus('PENDING'),
            'created_at' => date('Y-m-d H:i:s')
        ];

        $tenantId = \App\Core\TenantContext::getId();
        if ($tenantId) {
            // reservations table uses tenant_id column; keep business_id for forward compat
            $data['tenant_id'] = $tenantId;
            $data['business_id'] = $tenantId;
        }

        $validation = $this->reservationService->validateReservationData($data);

        // Capacity mismatch is downgraded to a warning — let the server
        // log it and still persist the reservation. Other validation
        // failures still block submission.
        $warnings = is_array($validation['warnings'] ?? null) ? $validation['warnings'] : [];

        if (!$validation['valid']) {
            $errors = is_array($validation['errors'] ?? null) ? array_values($validation['errors']) : ['Girilen bilgiler eksik veya geçersiz.'];
            return $this->respondReservation(
                $isAjax, false, $errors, 'error',
                'notifications.error.invalid_data', $listUrl, 422, ['warnings' => $warnings]
            );
        }

        $result = $this->reservationService->createReservation($data);

        if ($result) {
            if (!empty($data['table_id'])) {
                $table = $this->tableService->getTableById($data['table_id']);
                if ($table && $table['status'] !== 'RESERVED') {
                    $this->tableService->updateTableStatus($data['table_id'], 'RESERVED');
                }
            }

            if (!empty($requestData['send_reminder']) && !empty($data['customer_email'])) {
                try {
                    $emailService = \App\Core\DependencyFactory::getEmailService();
                    $reservation = $this->reservationService->getReservationById($result);
                    if ($reservation) {
                        if (!empty($reservation['table_id'])) {
                            $table = $this->tableService->getTableById($reservation['table_id']);
                            $reservation['table_name'] = $table['name'] ?? 'Belirlenmedi';
                        }
                        $emailService->sendReservationConfirmation($reservation);
                    }
                } catch (\Exception $e) {
                    \App\Core\Logger::error('Failed to send reservation confirmation email: ' . $e->getMessage());
                }
            }

            unset($_SESSION['form_data']);
            $reservationRow = $this->reservationService->getReservationById($result);
            return $this->respondReservation(
                $isAjax, true, [], 'success',
                'notifications.success.reservation_created', $listUrl, 200,
                [
                    'reservation'    => $reservationRow,
                    'reservation_id' => $result,
                    'warnings'       => $warnings,
                ]
            );
        }

        return $this->respondReservation(
            $isAjax, false, ['Rezervasyon oluşturulamadı.'], 'error',
            'notifications.error.create_failed', $listUrl, 500, ['warnings' => $warnings]
        );
    }

    /**
     * Unified response helper for the add flow. For AJAX callers returns
     * JSON with {success, errors, warnings, reservation, ...}. For
     * traditional form posts keeps the existing redirect + flash behaviour
     * so bookmarks and no-JS clients still work.
     */
    private function respondReservation(
        bool $isAjax,
        bool $success,
        array $errors,
        string $flashType,
        string $flashKey,
        string $redirectUrl,
        int $httpStatus = 200,
        array $extra = []
    ): void {
        if ($isAjax) {
            $payload = array_merge([
                'success' => $success,
                'errors'  => $errors,
            ], $extra);
            $this->jsonResponse($payload, $httpStatus);
            return;
        }

        $this->toastNotificationService->setFlash($flashType, $flashKey);
        header('Location: ' . $redirectUrl);
        exit;
    }

    private function isAjaxRequest(): bool {
        $xrw = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
        if ($xrw === 'xmlhttprequest') return true;
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (stripos($accept, 'application/json') !== false) return true;
        $ct = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($ct, 'application/json') !== false) return true;
        return false;
    }

    protected function jsonResponse(array $payload, int $status = 200): void {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    public function edit($id = null) {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        $this->requirePermission('reservations.edit');
        \App\Core\HelperLoader::ensureLoaded();
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $reservationId = $id ?? $queryParams['id'] ?? '';
        
        if (empty($reservationId)) {
            $this->toastNotificationService->setFlash('error', 'notifications.error.invalid_data');
            header('Location: ' . getAdminUrl('reservations'));
            exit;
        }
        
        $reservation = $this->reservationService->getReservationById($reservationId);
        
        if (!$reservation) {
            $this->toastNotificationService->setFlash('error', 'notifications.error.not_found');
            header('Location: ' . getAdminUrl('reservations'));
            exit;
        }
        
        // CRITICAL: Verify tenant isolation (unless super admin)
        if (!$this->isSuperAdmin()) {
            $tenantId = \App\Core\TenantContext::getId();
            $reservationTenantId = $reservation['tenant_id'] ?? $reservation['business_id'] ?? null;

            if (!$tenantId || (string)$reservationTenantId !== (string)$tenantId) {
                \App\Core\Logger::warning('ReservationController::edit - Tenant isolation violation', [
                    'reservation_id' => $reservationId,
                    'reservation_tenant_id' => $reservationTenantId,
                    'tenant_id' => $tenantId
                ]);
                $this->toastNotificationService->setFlash('error', 'notifications.error.unauthorized');
                header('Location: ' . getAdminUrl('reservations'));
                exit;
            }
        }
        
        // Get all tables and zones for the form
        $allTables = $this->tableService->getAllTables();
        $zones = $this->tableService->getAllZones();
        $tablesGroupedByZone = $this->tableService->getTablesGroupedByZone();
        
        $data = [
            'reservation' => $reservation,
            'tables' => $allTables,
            'zones' => $zones,
            'tablesGroupedByZone' => $tablesGroupedByZone,
            'baseUrl' => BASE_URL
        ];
        
        $this->view('admin/reservations_edit', $data);
    }
    
    public function update($id = null) {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        \App\Core\HelperLoader::ensureLoaded();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . getAdminUrl('reservations'));
            exit;
        }
        
        $this->checkPermissionOrFail('reservations.edit');
        
        $requestData = \App\Core\RequestParser::getRequestData();
        // Get reservation ID from route parameter or POST data
        $reservationId = $id ?? $requestData['reservation_id'] ?? '';
        \App\Core\HelperLoader::ensureLoaded();
        
        if (empty($reservationId)) {
            $this->toastNotificationService->setFlash('error', 'notifications.error.invalid_data');
            header('Location: ' . getAdminUrl('reservations'));
            exit;
        }
        
        // Get existing reservation to check table changes
        $existingReservation = $this->reservationService->getReservationById($reservationId);
        
        if (!$existingReservation) {
            $this->toastNotificationService->setFlash('error', 'notifications.error.not_found');
            header('Location: ' . getAdminUrl('reservations'));
            exit;
        }
        
        // CRITICAL: Verify tenant isolation (unless super admin)
        if (!$this->isSuperAdmin()) {
            $tenantId = \App\Core\TenantContext::getId();
            $reservationTenantId = $existingReservation['tenant_id'] ?? $existingReservation['business_id'] ?? null;

            if (!$tenantId || (string)$reservationTenantId !== (string)$tenantId) {
                \App\Core\Logger::warning('ReservationController::update - Tenant isolation violation', [
                    'reservation_id' => $reservationId,
                    'reservation_tenant_id' => $reservationTenantId,
                    'tenant_id' => $tenantId
                ]);
                $this->toastNotificationService->setFlash('error', 'notifications.error.unauthorized');
                header('Location: ' . getAdminUrl('reservations'));
                exit;
            }
        }
        
        // CRITICAL: Verify table belongs to current tenant if table_id is provided
        $tableId = !empty($requestData['table_id']) ? $requestData['table_id'] : null;
        if ($tableId) {
            $table = $this->tableService->getTableById($tableId);
            if (!$table) {
                $this->toastNotificationService->setFlash('error', 'notifications.error.table_not_found');
                header('Location: ' . getAdminUrl('reservations'));
                exit;
            }
            
            // Check tenant isolation (unless super admin)
            if (!$this->isSuperAdmin()) {
                $tenantId = \App\Core\TenantContext::getId();
                $tableTenantId = $table['tenant_id'] ?? $table['business_id'] ?? null;

                if (!$tenantId || (string)$tableTenantId !== (string)$tenantId) {
                    \App\Core\Logger::warning('ReservationController::update - Table tenant isolation violation', [
                        'table_id' => $tableId,
                        'table_tenant_id' => $tableTenantId,
                        'tenant_id' => $tenantId
                    ]);
                    $this->toastNotificationService->setFlash('error', 'notifications.error.unauthorized');
                    header('Location: ' . getAdminUrl('reservations'));
                    exit;
                }
            }
        }
        
        // Prepare reservation data
        $data = [
            'customer_name' => trim($requestData['customer_name'] ?? ''),
            'contact' => trim($requestData['contact'] ?? ''),
            'customer_email' => trim($requestData['customer_email'] ?? ''),
            'date' => $requestData['date'] ?? date('Y-m-d'),
            'time' => $requestData['time'] ?? '12:00',
            'guests' => intval($requestData['guests'] ?? 1),
            'table_id' => $tableId,
            // notes / special_requests intentionally no longer captured
            'status' => $requestData['status'] ?? $existingReservation['status'] ?? ConstantsHelper::getOrderStatus('PENDING'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // Validate reservation data (exclude current reservation from availability check)
        $validation = $this->reservationService->validateReservationData($data, $reservationId);
        
        if (!$validation['valid']) {
            $this->toastNotificationService->setFlash('error', 'notifications.error.invalid_data');
            header('Location: ' . getAdminUrl('reservations'));
            exit;
        }
        
        // Handle table status changes
        $oldTableId = $existingReservation['table_id'] ?? null;
        $newTableId = $data['table_id'] ?? null;
        
        // Update reservation
        $result = $this->reservationService->updateReservation($reservationId, $data);
        
        if ($result) {
            // Update table statuses
            // If table changed, free old table and reserve new one
            if ($oldTableId !== $newTableId) {
                if (!empty($oldTableId)) {
                    // Check if old table has other active reservations
                    $otherReservations = $this->reservationService->getReservationsByTable($oldTableId);
                    $hasOtherReservations = false;
                    $pendingStatus = ConstantsHelper::getOrderStatus('PENDING');
                    foreach ($otherReservations as $res) {
                        if ($res['reservation_id'] !== $reservationId && 
                            in_array($res['status'] ?? $pendingStatus, [$pendingStatus, 'CONFIRMED'])) {
                            $hasOtherReservations = true;
                            break;
                        }
                    }
                    
                    if (!$hasOtherReservations) {
                        $this->tableService->updateTableStatus($oldTableId, 'FREE');
                    }
                }
                
                if (!empty($newTableId)) {
                    $this->tableService->updateTableStatus($newTableId, 'RESERVED');
                }
            } else if (!empty($newTableId)) {
                // Same table, but check if status changed
                $defaultStatus = ConstantsHelper::getOrderStatus('PENDING');
                $pendingStatus = $defaultStatus;
                $oldStatus = $existingReservation['status'] ?? $defaultStatus;
                $newStatus = $data['status'] ?? $defaultStatus;
                
                // If status changed to CANCELLED or COMPLETED, free the table
                if (in_array($newStatus, ['CANCELLED', 'COMPLETED', 'NO_SHOW']) && 
                    !in_array($oldStatus, ['CANCELLED', 'COMPLETED', 'NO_SHOW'])) {
                    // Check if table has other active reservations
                    $otherReservations = $this->reservationService->getReservationsByTable($newTableId);
                    $hasOtherReservations = false;
                    foreach ($otherReservations as $res) {
                        if ($res['reservation_id'] !== $reservationId && 
                            in_array($res['status'] ?? $pendingStatus, [$pendingStatus, 'CONFIRMED'])) {
                            $hasOtherReservations = true;
                            break;
                        }
                    }
                    
                    if (!$hasOtherReservations) {
                        $this->tableService->updateTableStatus($newTableId, 'FREE');
                    }
                } else if (in_array($newStatus, [$pendingStatus, 'CONFIRMED']) &&
                          !in_array($oldStatus, [$pendingStatus, 'CONFIRMED'])) {
                    // Status changed to active, reserve the table
                    $this->tableService->updateTableStatus($newTableId, 'RESERVED');
                }
            }
            
            $this->toastNotificationService->setFlash('success', 'notifications.success.reservation_updated');
            header('Location: ' . getAdminUrl('reservations'));
            exit;
        } else {
            $this->toastNotificationService->setFlash('error', 'notifications.error.update_failed');
            header('Location: ' . getAdminUrl('reservations'));
            exit;
        }
    }
    
    public function updateStatus($id = null) {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        
        // Handle both POST and JSON requests
        $requestData = \App\Core\RequestParser::getRequestData();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid request method']);
            exit;
        }
        
        if (!$this->hasPermission('reservations.edit')) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
            exit;
        }
        
        $reservationId = $id ?? $requestData['reservation_id'] ?? '';
        $newStatus = $requestData['status'] ?? '';
        
        if (empty($reservationId) || empty($newStatus)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Geçersiz veri']);
            exit;
        }
        
        // CRITICAL: Verify tenant isolation before update
        $reservation = $this->reservationService->getReservationById($reservationId);
        if ($reservation && !$this->isSuperAdmin()) {
            $tenantId = \App\Core\TenantContext::getId();
            $reservationTenantId = $reservation['tenant_id'] ?? $reservation['business_id'] ?? null;

            if (!$tenantId || (string)$reservationTenantId !== (string)$tenantId) {
                \App\Core\Logger::warning('ReservationController::updateStatus - Tenant isolation violation', [
                    'reservation_id' => $reservationId,
                    'reservation_tenant_id' => $reservationTenantId,
                    'tenant_id' => $tenantId
                ]);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
                exit;
            }
        }
        
        // Validate status
        $validStatuses = ['PENDING', 'CONFIRMED', 'CANCELLED', 'COMPLETED', 'NO_SHOW'];
        if (!in_array($newStatus, $validStatuses)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Geçersiz durum']);
            exit;
        }
        
        $result = $this->reservationService->updateReservationStatus($reservationId, $newStatus);
        
        header('Content-Type: application/json');
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Durum güncellendi']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Güncelleme başarısız']);
        }
        exit;
    }
    
    public function delete() {
        // CRITICAL: Ensure tenant context is set
        $this->ensureTenantContext();
        \App\Core\HelperLoader::ensureLoaded();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . getAdminUrl('reservations'));
            exit;
        }
        
        if (!$this->hasPermission('reservations.delete')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }
        
        $requestData = \App\Core\RequestParser::getRequestData();
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $reservationId = $requestData['reservation_id'] ?? $queryParams['id'] ?? '';
        
        if (empty($reservationId)) {
            $this->toastNotificationService->setFlash('error', 'notifications.error.invalid_data');
            header('Location: ' . getAdminUrl('reservations'));
            exit;
        }
        
        $reservation = $this->reservationService->getReservationById($reservationId);
        
        if (!$reservation) {
            $this->toastNotificationService->setFlash('error', 'notifications.error.not_found');
            header('Location: ' . getAdminUrl('reservations'));
            exit;
        }
        
        // CRITICAL: Verify tenant isolation (unless super admin)
        if (!$this->isSuperAdmin()) {
            $tenantId = \App\Core\TenantContext::getId();
            $reservationTenantId = $reservation['tenant_id'] ?? $reservation['business_id'] ?? null;

            if (!$tenantId || (string)$reservationTenantId !== (string)$tenantId) {
                \App\Core\Logger::warning('ReservationController::delete - Tenant isolation violation', [
                    'reservation_id' => $reservationId,
                    'reservation_tenant_id' => $reservationTenantId,
                    'tenant_id' => $tenantId
                ]);
                $this->toastNotificationService->setFlash('error', 'notifications.error.unauthorized');
                header('Location: ' . getAdminUrl('reservations'));
                exit;
            }
        }
        
        $tableId = $reservation['table_id'] ?? null;
        
        $result = $this->reservationService->deleteReservation($reservationId);
        if ($result) {
            // Free the table if it was assigned
            if (!empty($tableId)) {
                // Check if table has other active reservations
                $otherReservations = $this->reservationService->getReservationsByTable($tableId);
                $hasOtherReservations = false;
                $pendingStatus = ConstantsHelper::getOrderStatus('PENDING');
                foreach ($otherReservations as $res) {
                    if ($res['reservation_id'] !== $reservationId && 
                        in_array($res['status'] ?? $pendingStatus, [$pendingStatus, 'CONFIRMED'])) {
                        $hasOtherReservations = true;
                        break;
                    }
                }
                
                if (!$hasOtherReservations) {
                    $this->tableService->updateTableStatus($tableId, 'FREE');
                }
            }
            
            $this->toastNotificationService->setFlash('success', 'notifications.success.reservation_cancelled');
            header('Location: ' . getAdminUrl('reservations'));
            exit;
        } else {
            $this->toastNotificationService->setFlash('error', 'notifications.error.delete_failed');
            header('Location: ' . getAdminUrl('reservations'));
            exit;
        }
    }
    
    public function getReservation($id = null) {
        if (!$this->hasPermission('reservations.view')) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.unauthorized', [], 401);
            return;
        }
        
        $queryParams = \App\Core\RequestParser::getQueryParams();
        $reservationId = $id ?? $queryParams['id'] ?? '';
        if (empty($reservationId)) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_data', [], 400);
        }
        
        $reservation = $this->reservationService->getReservationById($reservationId);
        if ($reservation) {
            $this->apiResponse($reservation);
        } else {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.not_found', [], 404);
        }
    }
}

