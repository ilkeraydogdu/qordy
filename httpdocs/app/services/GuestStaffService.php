<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\GuestStaffRepository;

class GuestStaffService extends BaseService {
    
    public function __construct(GuestStaffRepository $repository) {
        parent::__construct($repository);
    }

    /**
     * Create or get guest staff
     * @param array $data
     * @return string Guest staff ID
     */
    public function createOrGet(array $data): string {
        $data = $this->withTenantId($data);
        $phone = $data['phone'] ?? '';

        if (empty($phone)) {
            return $this->insertNew($data);
        }

        $existing = $this->repository->findByPhone($phone);
        if ($existing) {
            $updateData = [];
            foreach (['first_name', 'last_name', 'email', 'age', 'height_cm', 'weight_kg',
                      'gender', 'tc_no', 'daily_rate', 'photo_path', 'address'] as $k) {
                if (array_key_exists($k, $data) && $data[$k] !== null && $data[$k] !== '') {
                    $updateData[$k] = $data[$k];
                }
            }
            if (!empty($updateData)) {
                $this->repository->update($existing['guest_staff_id'], $updateData);
            }
            return $existing['guest_staff_id'];
        }

        return $this->insertNew($data);
    }

    /**
     * Create a guest staff entry. Like createOrGet but never upserts — the
     * caller explicitly wants a new record (typical from HR UI "new day
     * worker" button).
     *
     * @return string guest_staff_id
     */
    public function create(array $data): string {
        return $this->insertNew($this->withTenantId($data));
    }

    /**
     * Update an existing guest staff profile.
     */
    public function update(string $guestStaffId, array $data): bool {
        // Whitelist editable columns so arbitrary fields can't be injected.
        $allowed = array_intersect_key($data, array_flip([
            'first_name', 'last_name', 'phone', 'email', 'age', 'height_cm',
            'weight_kg', 'gender', 'tc_no', 'daily_rate', 'photo_path',
            'address', 'is_active',
        ]));
        if (empty($allowed)) return false;
        return (bool)$this->repository->update($guestStaffId, $allowed);
    }

    public function getActive(): array {
        return $this->repository->getActive();
    }

    public function search(string $search): array {
        return $this->repository->search($search);
    }

    /**
     * Persist a fresh row — always stamps tenant_id from context so multi-
     * tenant isolation can never leak through the "no phone" path.
     */
    private function insertNew(array $data): string {
        $guestStaffId = generateId('gs');
        $data['guest_staff_id'] = $guestStaffId;
        if (!isset($data['is_active'])) {
            $data['is_active'] = 1;
        }
        $this->repository->create($data);
        return $guestStaffId;
    }

    /**
     * Resolve tenant_id from context when not provided explicitly.
     */
    private function withTenantId(array $data): array {
        if (empty($data['tenant_id']) && class_exists('\App\Core\TenantContext')) {
            $tid = \App\Core\TenantContext::getId();
            if ($tid) {
                $data['tenant_id'] = $tid;
            }
        }
        return $data;
    }
}

