<?php
namespace App\Controllers;

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/DependencyFactory.php';

/**
 * Guest staff (yevmiyeci / geçici personel) CRUD.
 *
 * These workers are not real users and never log in — they only appear
 * in shift scheduling dropdowns. The UI mirrors the minimal HR fields
 * the plan calls for (ad/soyad/telefon + opsiyonel yaş/boy/kilo/cinsiyet
 * /tc no/günlük yevmiye/adres/foto).
 */
class GuestStaffController extends \App\Core\Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function applyTenantContext(): void
    {
        if ($this->isSuperAdmin()) {
            $qp = \App\Core\RequestParser::getQueryParams();
            $requested = $qp['business_id'] ?? $qp['tenant_id'] ?? null;
            if ($requested) {
                try {
                    $cs = \App\Core\DependencyFactory::getCustomerService();
                    $customer = $cs->getById($requested);
                    if ($customer) {
                        \App\Core\TenantContext::set($customer);
                        $_SESSION['business_id'] = $requested;
                        $_SESSION['customer_id'] = $requested;
                        return;
                    }
                } catch (\Throwable $e) { /* ignore */ }
            }
            return;
        }
        parent::ensureTenantContext();
    }

    private function ensurePermission(string $perm): void
    {
        if (!$this->hasPermission($perm)
            && !$this->hasPermission('hr.view')
            && !$this->hasPermission('staff.view')) {
            \App\Core\ResponseHandler::error('Yetkilendirme hatası', 'UNAUTHORIZED', 401);
        }
    }

    public function index(): void
    {
        $this->applyTenantContext();
        $this->ensurePermission('hr.guest_staff.manage');
        $this->view('admin/guest-staff', [
            'is_super_admin' => $this->isSuperAdmin(),
        ]);
    }

    public function list(): void
    {
        $this->applyTenantContext();
        $this->ensurePermission('hr.guest_staff.manage');
        $qp = \App\Core\RequestParser::getQueryParams();
        $svc = \App\Core\DependencyFactory::getGuestStaffService();
        $rows = isset($qp['q']) && $qp['q'] !== '' ? $svc->search((string)$qp['q']) : $svc->getActive();
        $this->apiResponse(['success' => true, 'data' => $rows]);
    }

    public function create(): void
    {
        $this->applyTenantContext();
        $this->ensurePermission('hr.guest_staff.manage');
        $body = \App\Core\RequestParser::getJsonBody() ?: \App\Core\RequestParser::getRequestData();
        $data = $this->sanitize($body);
        if (empty($data['first_name'])) {
            \App\Core\ResponseHandler::error('Ad zorunlu', 'VALIDATION', 400);
            return;
        }
        $id = \App\Core\DependencyFactory::getGuestStaffService()->create($data);
        $this->apiResponse(['success' => true, 'guest_staff_id' => $id]);
    }

    public function update(string $guestStaffId): void
    {
        $this->applyTenantContext();
        $this->ensurePermission('hr.guest_staff.manage');
        $body = \App\Core\RequestParser::getJsonBody() ?: \App\Core\RequestParser::getRequestData();
        $ok = \App\Core\DependencyFactory::getGuestStaffService()->update($guestStaffId, $this->sanitize($body));
        $this->apiResponse(['success' => (bool)$ok]);
    }

    public function delete(string $guestStaffId): void
    {
        $this->applyTenantContext();
        $this->ensurePermission('hr.guest_staff.manage');
        // Soft delete via is_active flag so shift history is preserved.
        $ok = \App\Core\DependencyFactory::getGuestStaffService()->update($guestStaffId, ['is_active' => 0]);
        $this->apiResponse(['success' => (bool)$ok]);
    }

    /**
     * Whitelist and coerce the HR form fields. Only columns we know about
     * in the phase2 migration are allowed through.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function sanitize(array $body): array
    {
        $out = [];
        $keys = [
            'first_name' => 'string',
            'last_name'  => 'string',
            'phone'      => 'string',
            'email'      => 'string',
            'age'        => 'int',
            'height_cm'  => 'int',
            'weight_kg'  => 'int',
            'gender'     => 'gender',
            'tc_no'      => 'string',
            'daily_rate' => 'float',
            'photo_path' => 'string',
            'address'    => 'string',
            'is_active'  => 'bool',
        ];
        foreach ($keys as $k => $type) {
            if (!array_key_exists($k, $body)) continue;
            $v = $body[$k];
            if ($v === '' || $v === null) { $out[$k] = null; continue; }
            switch ($type) {
                case 'int':    $out[$k] = (int)$v; break;
                case 'float':  $out[$k] = (float)$v; break;
                case 'bool':   $out[$k] = $v ? 1 : 0; break;
                case 'gender':
                    $g = strtoupper((string)$v);
                    $out[$k] = in_array($g, ['M', 'F', 'O'], true) ? $g : null;
                    break;
                default:
                    $out[$k] = trim((string)$v);
            }
        }
        return $out;
    }
}
