<?php
namespace App\Services;

use App\Repositories\TableActivityLogRepository;

/**
 * Table Activity Log Service
 * Masa hareket kayıtları: silme, eksiltme, iptal gibi işlemlerin loglanması
 */
class TableActivityLogService {
    private $repository;

    public function __construct(TableActivityLogRepository $repository) {
        $this->repository = $repository;
    }

    /**
     * Log an item deletion
     */
    public function logItemDeleted(array $params): bool {
        return $this->repository->createLog(array_merge($params, [
            'action_type' => 'ITEM_DELETED'
        ]));
    }

    /**
     * Log a quantity reduction
     */
    public function logQuantityReduced(array $params): bool {
        return $this->repository->createLog(array_merge($params, [
            'action_type' => 'ITEM_QUANTITY_REDUCED'
        ]));
    }

    /**
     * Log an order cancellation (all items deleted)
     */
    public function logAllOrdersDeleted(array $params): bool {
        return $this->repository->createLog(array_merge($params, [
            'action_type' => 'ALL_ORDERS_DELETED'
        ]));
    }

    /**
     * Log an order cancellation
     */
    public function logOrderCancelled(array $params): bool {
        return $this->repository->createLog(array_merge($params, [
            'action_type' => 'ORDER_CANCELLED'
        ]));
    }

    /**
     * Log a table transfer to cashier
     */
    public function logOrderTransferred(array $params): bool {
        return $this->repository->createLog(array_merge($params, [
            'action_type' => 'ORDER_TRANSFERRED'
        ]));
    }

    /**
     * Log a table move
     */
    public function logTableMoved(array $params): bool {
        return $this->repository->createLog(array_merge($params, [
            'action_type' => 'TABLE_MOVED'
        ]));
    }

    /**
     * Get activity logs for a table
     */
    public function getByTable(string $tableId, int $limit = 50, int $offset = 0, ?string $dateFilter = null): array {
        return $this->repository->getByTable($tableId, $limit, $offset, $dateFilter);
    }

    /**
     * Get activity logs for an order
     */
    public function getByOrder(string $orderId): array {
        return $this->repository->getByOrder($orderId);
    }

    /**
     * Get today's activity logs
     */
    public function getTodayLogs(): array {
        return $this->repository->getTodayLogs();
    }

    /**
     * Helper: Get performer info from session
     */
    public static function getPerformerInfo(): array {
        $userId = $_SESSION['user_id'] ?? $_SESSION['customer_id'] ?? '';
        $userName = $_SESSION['user_name'] ?? $_SESSION['username'] ?? '';
        $userRole = $_SESSION['role'] ?? $_SESSION['user_role'] ?? 'STAFF';

        // If username looks like an email, extract the readable part
        if (!empty($userName) && strpos($userName, '@') !== false) {
            $namePart = explode('@', $userName)[0];
            if (preg_match('/^[a-z0-9._-]+$/i', $namePart) && strlen($namePart) > 2) {
                $userName = ucfirst($namePart);
            }
        }

        if (empty($userName)) {
            $userName = 'Personel';
        }

        return [
            'performed_by' => $userId,
            'performed_by_name' => $userName,
            'performed_by_role' => $userRole,
        ];
    }

    /**
     * Helper: Get business ID from context
     */
    public static function getBusinessId(): string {
        $businessId = \App\Core\TenantResolver::resolve() ?? '';
        if (empty($businessId) && class_exists('\App\Core\TenantContext')) {
            $businessId = \App\Core\TenantContext::getId() ?? '';
        }
        return $businessId;
    }
}
