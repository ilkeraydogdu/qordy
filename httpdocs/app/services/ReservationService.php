<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\ReservationRepository;
use App\Core\DependencyFactory;

class ReservationService extends BaseService {
    private $translationService;
    
    public function __construct(ReservationRepository $reservationRepository) {
        parent::__construct($reservationRepository);
        $this->translationService = DependencyFactory::getTranslationService();
    }
    
    /**
     * Get all reservations
     * @return array All reservations
     */
    public function getAllReservations(): array {
        return $this->repository->findAll();
    }
    
    /**
     * Get reservation by ID
     * @param string $reservationId Reservation ID
     * @return array|null Reservation data or null
     */
    public function getReservationById(string $reservationId): ?array {
        return $this->repository->findById($reservationId);
    }
    
    /**
     * Get reservations by date
     * @param string $date Date (Y-m-d)
     * @return array Reservations
     */
    public function getReservationsByDate(string $date): array {
        return $this->repository->getByDate($date);
    }
    
    /**
     * Get reservations by status
     * @param string $status Reservation status
     * @return array Reservations
     */
    public function getReservationsByStatus(string $status): array {
        return $this->repository->getByStatus($status);
    }
    
    /**
     * Get upcoming reservations
     * @return array Upcoming reservations
     */
    public function getUpcomingReservations(): array {
        return $this->repository->getUpcoming();
    }
    
    /**
     * Get reservations by table ID
     * @param string $tableId Table ID
     * @return array Reservations
     */
    public function getReservationsByTable(string $tableId): array {
        return $this->repository->getByTableId($tableId);
    }
    
    /**
     * Create a new reservation
     * @param array $reservationData Reservation data
     * @return bool|string Reservation ID on success, false on failure
     */
    public function createReservation(array $reservationData) {
        if (empty($reservationData['reservation_id'])) {
            $reservationData['reservation_id'] = 'r' . generateId();
        }
        
        // Normalize field names - convert 'guest_count' to 'guests' (database column name)
        if (isset($reservationData['guest_count']) && !isset($reservationData['guests'])) {
            $reservationData['guests'] = intval($reservationData['guest_count']);
        }
        
        // Remove guest_count completely (database column is 'guests', not 'guest_count')
        unset($reservationData['guest_count']);
        
        // Validate required fields
        if (empty($reservationData['customer_name']) || 
            empty($reservationData['date']) || 
            empty($reservationData['time'])) {
            return false;
        }
        
        $defaults = [
            'guests' => intval($reservationData['guests'] ?? 2),
            'contact' => $reservationData['contact'] ?? '',
            'notes' => $reservationData['notes'] ?? '',
            'status' => $reservationData['status'] ?? 'PENDING', // Default to PENDING for new reservations
            'created_at' => $reservationData['created_at'] ?? date('Y-m-d H:i:s')
        ];
        
        $reservationData = array_merge($defaults, $reservationData);
        
        // Final cleanup - ensure guest_count is not in the data (defensive check)
        unset($reservationData['guest_count']);
        
        $result = $this->repository->create($reservationData);
        
        if ($result) {
            return $reservationData['reservation_id'];
        }
        
        return false;
    }
    
    /**
     * Update reservation
     * @param string $reservationId Reservation ID
     * @param array $reservationData Reservation data to update
     * @return bool Success
     */
    public function updateReservation(string $reservationId, array $reservationData): bool {
        // Normalize field names - convert 'guest_count' to 'guests' (database column name)
        if (isset($reservationData['guest_count']) && !isset($reservationData['guests'])) {
            $reservationData['guests'] = intval($reservationData['guest_count']);
        }
        
        // Remove guest_count completely (database column is 'guests', not 'guest_count')
        unset($reservationData['guest_count']);
        
        return $this->repository->update($reservationId, $reservationData);
    }
    
    /**
     * Update reservation status
     * @param string $reservationId Reservation ID
     * @param string $status New status
     * @return bool Success
     */
    public function updateReservationStatus(string $reservationId, string $status): bool {
        $validStatuses = ['PENDING', 'CONFIRMED', 'CANCELLED', 'COMPLETED', 'NO_SHOW'];
        
        if (!in_array($status, $validStatuses)) {
            return false;
        }
        
        return $this->repository->update($reservationId, [
            'status' => $status
        ]);
    }
    
    /**
     * Delete reservation
     * @param string $reservationId Reservation ID
     * @return bool Success
     */
    public function deleteReservation(string $reservationId): bool {
        return $this->repository->delete($reservationId);
    }

    /**
     * Check if table is available for reservation
     * @param string $tableId Table ID
     * @param string $date Date (Y-m-d)
     * @param string $time Time (H:i)
     * @param string $excludeReservationId Optional reservation ID to exclude from check
     * @param int $reservationDurationMinutes Reservation duration in minutes (default: 120 = 2 hours)
     * @return bool True if available, false if conflict exists
     */
    public function isTableAvailable(string $tableId, string $date, string $time, string $excludeReservationId = '', int $reservationDurationMinutes = 120): bool {
        return $this->repository->isTableAvailable($tableId, $date, $time, $excludeReservationId, $reservationDurationMinutes);
    }
    
    /**
     * Get conflicting reservations
     * @param string $tableId Table ID
     * @param string $date Date (Y-m-d)
     * @param string $time Time (H:i)
     * @param string $excludeReservationId Optional reservation ID to exclude from check
     * @param int $reservationDurationMinutes Reservation duration in minutes (default: 120)
     * @return array Conflicting reservations
     */
    public function getConflictingReservations(string $tableId, string $date, string $time, string $excludeReservationId = '', int $reservationDurationMinutes = 120): array {
        return $this->repository->getConflictingReservations($tableId, $date, $time, $excludeReservationId, $reservationDurationMinutes);
    }
    
    /**
     * Get reservations by date range
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return array Reservations
     */
    public function getReservationsByDateRange(string $startDate, string $endDate): array {
        return $this->repository->getReservationsByDateRange($startDate, $endDate);
    }

    /**
     * Check if table capacity is sufficient for guest count
     * @param string $tableId Table ID
     * @param int $guestCount Number of guests
     * @return bool True if capacity is sufficient
     */
    public function checkTableCapacity(string $tableId, int $guestCount): bool {
        $tableService = \App\Core\DependencyFactory::getTableService();
        $table = $tableService->getTableById($tableId);
        
        if (!$table) {
            return false;
        }
        
        $capacity = intval($table['capacity'] ?? 0);
        return $guestCount > 0 && $guestCount <= $capacity;
    }

    /**
     * Get reserved table IDs for a specific date and time
     * @param string $date Date (Y-m-d)
     * @param string $time Time (H:i)
     * @return array Table IDs
     */
    public function getReservedTableIds(string $date, string $time): array {
        return $this->repository->getReservedTableIds($date, $time);
    }

    /**
     * Validate reservation data
     * @param array $reservationData Reservation data
     * @param string $excludeReservationId Optional reservation ID to exclude from availability check (for updates)
     * @return array ['valid' => bool, 'errors' => array]
     */
    public function validateReservationData(array $reservationData, string $excludeReservationId = ''): array {
        $errors = [];
        $warnings = [];
        
        // Required fields
        if (empty($reservationData['customer_name'])) {
            $errors[] = $this->translationService->translate('notifications.warning.customer_name_required', null, []);
        } elseif (strlen(trim($reservationData['customer_name'])) < 2) {
            $errors[] = $this->translationService->translate('notifications.error.customer_name_min_length', null, []);
        } elseif (strlen(trim($reservationData['customer_name'])) > 100) {
            $errors[] = $this->translationService->translate('notifications.error.customer_name_max_length', null, []);
        }
        
        if (empty($reservationData['contact'])) {
            $errors[] = $this->translationService->translate('notifications.warning.contact_required', null, []);
        } elseif (strlen(trim($reservationData['contact'])) < 5) {
            $errors[] = $this->translationService->translate('notifications.error.contact_min_length', null, []);
        }
        
        // Email validation (optional but must be valid if provided)
        if (!empty($reservationData['customer_email'])) {
            if (!filter_var($reservationData['customer_email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = $this->translationService->translate('notifications.error.invalid_email', null, []);
            } elseif (strlen($reservationData['customer_email']) > 255) {
                $errors[] = $this->translationService->translate('notifications.error.email_too_long', null, []);
            }
        }
        
        if (empty($reservationData['date'])) {
            $errors[] = $this->translationService->translate('notifications.warning.date_required', null, []);
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $reservationData['date'])) {
            $errors[] = $this->translationService->translate('notifications.error.invalid_date_format', null, []);
        } else {
            $selectedDate = strtotime($reservationData['date']);
            $today = strtotime('today');
            $maxDate = strtotime('+1 year');
            
            if ($selectedDate < $today) {
                $errors[] = $this->translationService->translate('notifications.error.past_date_not_allowed', null, []);
            } elseif ($selectedDate > $maxDate) {
                $errors[] = $this->translationService->translate('notifications.error.reservation_date_too_far', null, []);
            }
        }
        
        if (empty($reservationData['time'])) {
            $errors[] = $this->translationService->translate('notifications.warning.time_required', null, []);
        } elseif (!preg_match('/^\d{2}:\d{2}$/', $reservationData['time'])) {
            $errors[] = $this->translationService->translate('notifications.error.invalid_time_format', null, []);
        } else {
            // Validate time is within business hours (optional - can be configured)
            $timeParts = explode(':', $reservationData['time']);
            $hour = intval($timeParts[0] ?? 0);
            $minute = intval($timeParts[1] ?? 0);
            
            if ($hour < 0 || $hour > 23) {
                $errors[] = $this->translationService->translate('notifications.error.invalid_hour_range', null, []);
            } elseif ($minute < 0 || $minute > 59) {
                $errors[] = $this->translationService->translate('notifications.error.invalid_minute_range', null, []);
            }
        }
        
        $guestCount = intval($reservationData['guests'] ?? $reservationData['guest_count'] ?? 0);
        if ($guestCount < 1) {
                $errors[] = $this->translationService->translate('notifications.error.guest_count_min', null, []);
        } elseif ($guestCount > 100) {
                $errors[] = $this->translationService->translate('notifications.error.guest_count_max', null, []);
        }
        
        // Table validation if provided
        if (!empty($reservationData['table_id'])) {
            $tableId = $reservationData['table_id'];
            $tableService = \App\Core\DependencyFactory::getTableService();
            $table = $tableService->getTableById($tableId);
            
            if (!$table) {
                $errors[] = $this->translationService->translate('notifications.error.table_not_found', null, []);
            } else {
                // Capacity mismatch is non-fatal — product decision is to
                // let staff overbook a table (splitting parties across
                // tables is common at peak times). Surface as a warning
                // that the controller/UI can display but still save.
                if (!$this->checkTableCapacity($tableId, $guestCount)) {
                    $capacity = $table['capacity'] ?? 0;
                    $warnings[] = $this->translationService->translate('notifications.error.table_capacity_insufficient', ['capacity' => $capacity], []);
                }
                
            // Check table availability (exclude current reservation if updating)
            if (!$this->isTableAvailable($tableId, $reservationData['date'], $reservationData['time'], $excludeReservationId)) {
                // Get conflicting reservations for better error message
                $conflicts = $this->getConflictingReservations($tableId, $reservationData['date'], $reservationData['time'], $excludeReservationId);
                if (!empty($conflicts)) {
                    $conflictInfo = [];
                    foreach ($conflicts as $conflict) {
                        $conflictInfo[] = $conflict['customer_name'] . ' (' . $conflict['time'] . ')';
                    }
                    $errors[] = $this->translationService->translate('notifications.error.table_not_available_conflicts', ['conflicts' => implode(', ', $conflictInfo)], []);
                } else {
                    $errors[] = $this->translationService->translate('notifications.error.table_not_available', null, []);
                }
            }
            }
        }
        
        // Validate status
        if (!empty($reservationData['status'])) {
            $validStatuses = ['PENDING', 'CONFIRMED', 'CANCELLED', 'COMPLETED', 'NO_SHOW'];
            if (!in_array($reservationData['status'], $validStatuses)) {
                $errors[] = $this->translationService->translate('notifications.error.invalid_reservation_status', null, []);
            }
        }
        
        // Validate notes length
        if (!empty($reservationData['notes']) && strlen($reservationData['notes']) > 1000) {
            $errors[] = $this->translationService->translate('notifications.error.notes_max_length', null, []);
        }
        
        // Validate special requests length
        if (!empty($reservationData['special_requests']) && strlen($reservationData['special_requests']) > 1000) {
            $errors[] = 'Özel istekler en fazla 1000 karakter olabilir';
        }
        
        return [
            'valid'    => empty($errors),
            'errors'   => $errors,
            'warnings' => $warnings,
        ];
    }
}

