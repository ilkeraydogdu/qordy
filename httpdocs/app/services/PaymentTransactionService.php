<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\PaymentTransactionRepository;

class PaymentTransactionService extends BaseService {

    public function __construct(PaymentTransactionRepository $repository) {
        parent::__construct($repository);
    }

    public function createTransaction(array $data): ?string {
        try {
            $result = $this->repository->create($data);
            if ($result) {
                return is_string($result) ? $result : (string)$result;
            }
            return null;
        } catch (\Exception $e) {
            \App\Core\Logger::error('PaymentTransactionService::createTransaction error: ' . $e->getMessage());
            return null;
        }
    }

    public function getByDateRange(string $startDate, string $endDate): array {
        return $this->repository->getByDateRange($startDate, $endDate);
    }

    public function getByOrderId(string $orderId): ?array {
        return $this->repository->getByOrderId($orderId);
    }

    public function getByShiftId(string $shiftId): array {
        return $this->repository->getByShiftId($shiftId);
    }
}
