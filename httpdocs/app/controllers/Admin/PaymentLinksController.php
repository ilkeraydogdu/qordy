<?php
namespace App\Controllers\Admin;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';

use App\Core\Controller;

/**
 * SuperAdmin UI for personalized payment links. Powers
 * /qodmin/payment-links and its create/store/revoke actions.
 */
class PaymentLinksController extends Controller {
    /** @var \App\Services\CustomPaymentLinkService */
    protected $service;

    public function __construct() {
        parent::__construct();
        $this->service = \App\Core\DependencyFactory::getCustomPaymentLinkService();

        if (!function_exists('getAdminUrl')) {
            require_once __DIR__ . '/../../helpers/url_helper.php';
        }
    }

    public function index() {
        $this->requireLogin();
        if (!$this->isSuperAdmin()) {
            header('Location: ' . BASE_URL . '/unauthorized');
            exit;
        }

        $filters = [
            'mode'      => $_GET['mode'] ?? '',
            'is_active' => $_GET['is_active'] ?? '',
            'q'         => trim((string)($_GET['q'] ?? '')),
        ];

        $links = $this->service->listAll($filters, 500);

        // Lookup maps so the list can show friendly names without
        // N+1 queries.
        $packagesById = $this->getPackagesById();
        $customersById = $this->getCustomersById();

        $this->render('admin/payment_links_index', [
            'links'         => $links,
            'filters'       => $filters,
            'packagesById'  => $packagesById,
            'customersById' => $customersById,
            'title'         => 'Özel Ödeme Bağlantıları',
            'is_super_admin'=> true,
        ]);
    }

    public function create() {
        $this->requireLogin();
        if (!$this->isSuperAdmin()) {
            header('Location: ' . BASE_URL . '/unauthorized');
            exit;
        }

        $packages = $this->getActivePackages();
        $customers = $this->getAllCustomersBrief();

        $this->render('admin/payment_links_create', [
            'packages'       => $packages,
            'customers'      => $customers,
            'title'          => 'Yeni Ödeme Bağlantısı',
            'is_super_admin' => true,
        ]);
    }

    public function store() {
        $this->requireLogin();
        if (!$this->isSuperAdmin()) {
            header('Location: ' . BASE_URL . '/unauthorized');
            exit;
        }

        $data = \App\Core\RequestParser::getRequestData();

        $payload = [
            'mode'            => $data['mode'] ?? 'existing_customer',
            'customer_id'     => $data['customer_id'] ?? null,
            'target_email'    => $data['target_email'] ?? null,
            'target_name'     => $data['target_name'] ?? null,
            'package_id'      => $data['package_id'] ?? '',
            'custom_price'    => (float)($data['custom_price'] ?? 0),
            'duration_months' => (int)($data['duration_months'] ?? 12),
            'currency'        => $data['currency'] ?? 'TRY',
            'note'            => $data['note'] ?? null,
            'is_single_use'   => !empty($data['is_single_use']),
            'max_uses'        => (int)($data['max_uses'] ?? 1),
            'expires_at'      => !empty($data['expires_at']) ? $data['expires_at'] : null,
            'created_by'      => $_SESSION['user_id'] ?? 'unknown',
        ];

        $result = $this->service->createLink($payload);

        if (!$result['success']) {
            $this->toastNotificationService->setFlash('error', $result['error'] ?? 'Bağlantı oluşturulamadı.');
            header('Location: ' . getAdminUrl('payment-links/create'));
            exit;
        }

        $this->toastNotificationService->setFlash('success', 'Ödeme bağlantısı oluşturuldu: ' . $result['url']);
        header('Location: ' . getAdminUrl('payment-links'));
        exit;
    }

    public function revoke(string $id) {
        $this->requireLogin();
        if (!$this->isSuperAdmin()) {
            header('Location: ' . BASE_URL . '/unauthorized');
            exit;
        }

        $this->service->revoke($id);
        $this->toastNotificationService->setFlash('success', 'Bağlantı iptal edildi.');
        header('Location: ' . getAdminUrl('payment-links'));
        exit;
    }

    public function toggleReusable(string $id) {
        $this->requireLogin();
        if (!$this->isSuperAdmin()) {
            header('Location: ' . BASE_URL . '/unauthorized');
            exit;
        }

        $link = $this->service->getById($id);
        if (!$link) {
            $this->toastNotificationService->setFlash('error', 'Bağlantı bulunamadı.');
            header('Location: ' . getAdminUrl('payment-links'));
            exit;
        }

        // Flip single-use <-> reusable; when enabling reusable keep
        // existing max_uses or default to 5.
        $currentlySingleUse = ((int)$link['is_single_use']) === 1;
        $nextReusable = $currentlySingleUse;
        $maxUses = $currentlySingleUse ? max(5, (int)$link['max_uses']) : 1;

        $this->service->setReusable($id, $nextReusable, $maxUses);
        $this->toastNotificationService->setFlash('success', 'Bağlantı kullanım modu güncellendi.');
        header('Location: ' . getAdminUrl('payment-links'));
        exit;
    }

    // --- helpers ----------------------------------------------------

    private function getActivePackages(): array {
        try {
            $repo = \App\Core\DependencyFactory::getPackageRepository();
            $all = $repo->getAll();
            return array_values(array_filter(
                is_array($all) ? $all : [],
                static fn($p) => !empty($p['is_active'])
            ));
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function getPackagesById(): array {
        $out = [];
        foreach ($this->getActivePackages() as $p) {
            $out[$p['package_id']] = $p;
        }
        return $out;
    }

    private function getAllCustomersBrief(): array {
        try {
            $repo = \App\Core\DependencyFactory::getCustomerRepository();
            $list = $repo->getAll();
            $out = [];
            foreach ($list as $c) {
                $out[] = [
                    'customer_id' => $c['customer_id'] ?? '',
                    'name'        => $c['company_name'] ?? ($c['name'] ?? $c['email'] ?? '—'),
                    'email'       => $c['email'] ?? '',
                ];
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function getCustomersById(): array {
        $out = [];
        foreach ($this->getAllCustomersBrief() as $c) {
            if (!empty($c['customer_id'])) {
                $out[$c['customer_id']] = $c;
            }
        }
        return $out;
    }
}
