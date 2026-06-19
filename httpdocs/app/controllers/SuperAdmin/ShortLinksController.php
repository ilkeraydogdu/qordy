<?php
namespace App\Controllers\SuperAdmin;

require_once __DIR__ . '/../../core/Controller.php';

use App\Core\Controller;
use App\Services\LinkShortenerService;

/**
 * Süper admin: kısaltılmış mail/WhatsApp linklerinin istatistiği.
 * pfdk.me API'sinden gelen tıklama verilerini local `short_links`
 * tablosuyla birleştirerek kampanya/müşteri kırılımıyla gösterir.
 */
class ShortLinksController extends Controller {

    public function index() {
        $this->requireLogin();
        if (!$this->isSuperAdmin()) {
            header('Location: ' . BASE_URL . '/unauthorized');
            exit;
        }

        $customerFilter = trim((string)($_GET['customer_id'] ?? ''));
        $campaignFilter = trim((string)($_GET['campaign'] ?? ''));
        $channelFilter  = trim((string)($_GET['channel'] ?? ''));
        $search         = trim((string)($_GET['q'] ?? ''));

        $db = \App\Core\DependencyFactory::getDatabase();

        $where = 'WHERE 1=1';
        $params = [];
        if ($customerFilter !== '') {
            $where .= ' AND sl.customer_id = :cid';
            $params[':cid'] = $customerFilter;
        }
        if ($campaignFilter !== '') {
            $where .= ' AND sl.campaign = :camp';
            $params[':camp'] = $campaignFilter;
        }
        if ($channelFilter !== '') {
            $where .= ' AND sl.channel = :ch';
            $params[':ch'] = $channelFilter;
        }
        if ($search !== '') {
            $where .= ' AND (sl.short_code LIKE :q OR sl.long_url LIKE :q OR sl.title LIKE :q)';
            $params[':q'] = '%' . $search . '%';
        }

        $sql = "SELECT sl.*,
                       c.company_name, c.email AS customer_email,
                       c.first_name, c.last_name
                FROM short_links sl
                LEFT JOIN customers c ON sl.customer_id = c.customer_id
                $where
                ORDER BY sl.created_at DESC
                LIMIT 500";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $summary = [
            'total_links'    => 0,
            'total_clicks'   => 0,
            'unique_customers' => 0,
            'active_campaigns' => 0,
        ];
        try {
            $sum = $db->query("SELECT
                    COUNT(*) AS total_links,
                    COALESCE(SUM(click_count),0) AS total_clicks,
                    COUNT(DISTINCT customer_id) AS unique_customers,
                    COUNT(DISTINCT campaign) AS active_campaigns
                FROM short_links")->fetch(\PDO::FETCH_ASSOC);
            if ($sum) $summary = array_merge($summary, array_map('intval', $sum));
        } catch (\Throwable $e) { /* tolerate */ }

        $campaigns = [];
        $channels = [];
        try {
            $campaigns = array_column(
                $db->query("SELECT DISTINCT campaign FROM short_links WHERE campaign IS NOT NULL AND campaign <> '' ORDER BY campaign")->fetchAll(\PDO::FETCH_ASSOC),
                'campaign'
            );
            $channels = array_column(
                $db->query("SELECT DISTINCT channel FROM short_links WHERE channel IS NOT NULL AND channel <> '' ORDER BY channel")->fetchAll(\PDO::FETCH_ASSOC),
                'channel'
            );
        } catch (\Throwable $e) { /* tolerate */ }

        $this->view('superadmin/short_links', [
            'title'       => 'Kısa Linkler & Tıklama Analizi - Qodmin',
            'page'        => 'short-links',
            'rows'        => $rows,
            'summary'     => $summary,
            'campaigns'   => $campaigns,
            'channels'    => $channels,
            'filters'     => [
                'customer_id' => $customerFilter,
                'campaign'    => $campaignFilter,
                'channel'     => $channelFilter,
                'q'           => $search,
            ],
            'configured'  => defined('PFDK_API_KEY') && PFDK_API_KEY !== '',
        ]);
    }

    /**
     * Bir linki manuel olarak pfdk.me'den senkronla.
     * POST: qodmin/short-links/{pfdk_id}/sync
     */
    public function sync(string $pfdkId) {
        $this->requireLogin();
        if (!$this->isSuperAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'forbidden']);
            return;
        }
        header('Content-Type: application/json; charset=utf-8');
        try {
            $svc = new LinkShortenerService();
            if (!$svc->isConfigured()) {
                echo json_encode(['success' => false, 'error' => 'shortener_not_configured']);
                return;
            }
            $stats = $svc->syncStats($pfdkId);
            echo json_encode(['success' => (bool)$stats, 'data' => $stats]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Tüm linkler için toplu senkron (top 200).
     */
    public function syncAll() {
        $this->requireLogin();
        if (!$this->isSuperAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'forbidden']);
            return;
        }
        header('Content-Type: application/json; charset=utf-8');
        try {
            $svc = new LinkShortenerService();
            if (!$svc->isConfigured()) {
                echo json_encode(['success' => false, 'error' => 'shortener_not_configured']);
                return;
            }
            $count = $svc->syncStatsBatch(200);
            echo json_encode(['success' => true, 'synced' => $count]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Tek bir linkin detay analitiğini JSON olarak döndür (modal için).
     */
    public function analytics(string $pfdkId) {
        $this->requireLogin();
        if (!$this->isSuperAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'forbidden']);
            return;
        }
        header('Content-Type: application/json; charset=utf-8');
        try {
            $svc = new LinkShortenerService();
            if (!$svc->isConfigured()) {
                echo json_encode(['success' => false, 'error' => 'shortener_not_configured']);
                return;
            }
            $data = $svc->analyticsFor($pfdkId, 100, 0);
            echo json_encode(['success' => (bool)$data, 'data' => $data]);
        } catch (\Throwable $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}
