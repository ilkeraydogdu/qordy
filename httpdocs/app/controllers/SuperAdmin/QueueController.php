<?php
namespace App\Controllers\SuperAdmin;

use App\Core\Controller;
use App\Core\DependencyFactory;
use App\Services\QueueService;

/**
 * Super-admin queue: pick a business first (same flow as /qodmin/tables), then
 * show that tenant queue at /qodmin/queue/{id}.
 */
class QueueController extends Controller
{
    private QueueService $queueService;

    public function __construct()
    {
        parent::__construct();
        // DI üzerinden paylaşılan tekil QueueService'i al; yeni new
        // çağrısı yapmak merkezi instance sözleşmesini bozardı.
        $this->queueService = DependencyFactory::getQueueService();
    }

    public function index(): void
    {
        $this->requireLogin();
        if (!$this->isSuperAdmin()) {
            header('Location: ' . BASE_URL . '/unauthorized');
            exit;
        }

        $filterBusinessId = trim((string)($_GET['business_id'] ?? ''));
        if ($filterBusinessId !== '') {
            header('Location: ' . BASE_URL . '/qodmin/queue/' . rawurlencode($filterBusinessId));
            exit;
        }

        $this->view('superadmin/queue/index', [
            'page' => 'queue',
        ]);
    }

    public function show($tenantId = null): void
    {
        $this->requireLogin();
        if (!$this->isSuperAdmin()) {
            header('Location: ' . BASE_URL . '/unauthorized');
            exit;
        }

        $tenantId = (string)$tenantId;
        if ($tenantId === '') {
            header('Location: ' . BASE_URL . '/qodmin/queue');
            exit;
        }

        $business = $this->fetchBusiness($tenantId);
        if (!$business) {
            header('Location: ' . BASE_URL . '/qodmin/queue');
            exit;
        }

        // Aynı görünüm: süper admin de işletme tarafındaki /business/queue ile
        // birebir aynı dashboard'u görsün. Bunun için geçici olarak bu tenant
        // context'i aktif ediyoruz (sayfa yalnızca okuma amaçlı; POST işlemleri
        // için süper admin "İşletmeye giriş yap" akışını kullanır).
        \App\Core\TenantContext::set($business);

        $crmFrom   = trim((string) ($_GET['crm_from'] ?? ''));
        $crmTo     = trim((string) ($_GET['crm_to'] ?? ''));
        $crmStatus = trim((string) ($_GET['crm_status'] ?? ''));
        $crmLimit  = (int) ($_GET['crm_limit'] ?? 200);
        $crmLimit  = max(1, min(500, $crmLimit));
        $crmFilters = [
            'date_from' => (preg_match('/^\d{4}-\d{2}-\d{2}$/', $crmFrom) ? $crmFrom : ''),
            'date_to'   => (preg_match('/^\d{4}-\d{2}-\d{2}$/', $crmTo) ? $crmTo : ''),
            'status'    => $crmStatus,
            'limit'     => $crmLimit,
        ];

        $settings   = $this->queueService->getSettings($tenantId);
        $active     = $this->queueService->getActiveQueue($tenantId);
        $recent     = $this->queueService->getCrmList($tenantId, $crmFilters);
        $token      = $this->queueService->ensureActiveToken($tenantId);
        $displayUrl = $this->buildDoorDisplayUrl($tenantId);

        // Meta/WhatsApp izni — süper admin paneli de aynı switch'i görebilmeli
        $db = \App\Core\DependencyFactory::getDatabase();
        $stmt = $db->prepare('SELECT meta_whatsapp_enabled FROM customers WHERE customer_id = :cid LIMIT 1');
        $stmt->execute([':cid' => $tenantId]);
        $metaAllowed = (int) $stmt->fetchColumn() === 1;

        $this->view('admin/queue/index', [
            'settings'            => $settings,
            'active'              => $active,
            'recent'              => $recent,
            'token'               => $token,
            'displayUrl'          => $displayUrl,
            'metaWhatsappAllowed' => $metaAllowed,
            // Süper admin salt-okunur bağlamı için bayrak — CRM silme butonları
            // kapatılır ve üstte "işletmeye giriş yap" banner'ı gösterilir.
            'can_delete_crm'      => false,
            'crm_from'            => $crmFrom,
            'crm_to'              => $crmTo,
            'crm_status'          => $crmStatus,
            'crm_limit'           => $crmLimit,
            'superadmin_context'  => $business,
        ]);
    }

    /**
     * Platform ekran URL'i — QueueController ile aynı mantık, ama SuperAdmin
     * controller bağımsız çalışsın diye burada yerel yardımcı tutuyoruz.
     */
    private function buildDoorDisplayUrl(string $tenantId): string
    {
        try {
            $urlService = \App\Core\DependencyFactory::getUrlService();
            return $urlService->buildTenantUrl($tenantId, '/sira');
        } catch (\Throwable $e) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $apex = $_ENV['BASE_DOMAIN'] ?? 'qordy.com';
            return $protocol . '://' . $apex . '/sira';
        }
    }

    /** @return array|null */
    private function fetchBusiness(string $tenantId): ?array
    {
        $db = \App\Core\DependencyFactory::getDatabase();
        $stmt = $db->prepare("SELECT customer_id, subdomain, company_name, first_name, last_name, email, logo_path, is_active
                              FROM customers WHERE customer_id = :id LIMIT 1");
        $stmt->execute([':id' => $tenantId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

}
