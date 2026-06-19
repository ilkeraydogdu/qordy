<?php
namespace App\Services;

use App\Core\TenantContext;
use App\Repositories\StockUnitRepository;

/**
 * Thin service over StockUnitRepository. Centralizes the tenant handling
 * so controllers don't need to reach into TenantContext directly.
 */
class StockUnitService
{
    /** @var StockUnitRepository */
    private $repo;

    public function __construct(StockUnitRepository $repo)
    {
        $this->repo = $repo;
    }

    public function list(): array
    {
        return $this->repo->listForTenant($this->currentTenantId());
    }

    public function create(array $input): string
    {
        $code = strtolower(trim((string)($input['code'] ?? '')));
        $label = trim((string)($input['label'] ?? ''));
        if ($code === '' || $label === '') {
            throw new \InvalidArgumentException('Birim kodu ve etiketi zorunludur.');
        }
        $tenantId = $this->currentTenantId();
        if ($this->repo->findByCode($code, $tenantId)) {
            throw new \InvalidArgumentException('Bu birim kodu zaten mevcut.');
        }
        $payload = [
            'tenant_id'       => $tenantId,
            'code'            => $code,
            'label'           => $label,
            'base_unit'       => isset($input['base_unit']) ? (string)$input['base_unit'] : $code,
            'factor_to_base'  => isset($input['factor_to_base']) ? (float)$input['factor_to_base'] : 1.0,
            'is_global'       => 0,
            'sort_order'      => (int)($input['sort_order'] ?? 100),
        ];
        $id = $this->repo->createUnit($payload);
        if ($id === false) {
            throw new \RuntimeException('Birim kaydedilemedi.');
        }
        return $id;
    }

    public function delete(string $unitId): bool
    {
        return $this->repo->delete($unitId);
    }

    /**
     * Resolve the active tenant id regardless of whether the caller used
     * TenantContext::setId() or just the session. Falls back to empty
     * string so repositories that accept nullable tenant can still work.
     */
    private function currentTenantId(): ?string
    {
        $tid = TenantContext::getId();
        if (is_string($tid) && $tid !== '') {
            return $tid;
        }
        if (!empty($_SESSION['business_id'])) {
            return (string)$_SESSION['business_id'];
        }
        return null;
    }
}
