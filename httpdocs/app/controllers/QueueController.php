<?php
namespace App\Controllers;

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/DependencyFactory.php';
require_once __DIR__ . '/../services/QueueService.php';
require_once __DIR__ . '/../services/QueueNotificationService.php';
require_once __DIR__ . '/../support/QueueThemeRegistry.php';

use App\Core\DependencyFactory;
use App\Core\RequestParser;
use App\Core\TenantContext;
use App\Core\Logger;

/**
 * QueueController
 *
 * Admin / staff side of the queue (sıra) management panel.
 *  - GET  business/queue                : list view (active + today)
 *  - GET  business/queue/settings       : settings page
 *  - POST business/queue/settings       : save settings
 *  - POST business/queue/{id}/notify    : call the guest (send WhatsApp + email)
 *  - POST business/queue/{id}/seat      : mark as seated
 *  - POST business/queue/{id}/cancel    : cancel by staff
 *  - POST business/queue/{id}/no-show   : mark as no-show
 *  - GET  api/business/queue/list       : JSON feed for live polling UI
 *  - POST business/queue/qr/rotate      : force-rotate the door QR token
 */
class QueueController extends \App\Core\Controller
{
    private $queueService;
    private $queueNotificationService;
    private $customerService;

    public function __construct()
    {
        parent::__construct();
        $this->queueService = DependencyFactory::getQueueService();
        $this->queueNotificationService = DependencyFactory::getQueueNotificationService();
        $this->customerService = DependencyFactory::getCustomerService();
    }

    public function index(): void
    {
        $this->ensureTenantContext();
        $this->requirePermissionSafe(['queue.view', 'reservations.view']);

        $tenantId = (string) TenantContext::getId();
        $settings = $this->queueService->getSettings($tenantId);
        $active   = $this->queueService->getActiveQueue($tenantId);

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
        $recent = $this->queueService->getCrmList($tenantId, $crmFilters);
        $token  = $this->queueService->ensureActiveToken($tenantId);

        $displayUrl = $this->buildDoorDisplayUrl($tenantId);
        $metaAllowed = $this->isMetaWhatsappAllowedForTenant($tenantId);
        $canDeleteCrm = $this->hasPermission('queue.manage') || $this->hasPermission('queue.settings');

        $this->view('admin/queue/index', [
            'settings'             => $settings,
            'active'               => $active,
            'recent'               => $recent,
            'token'                => $token,
            'displayUrl'           => $displayUrl,
            'metaWhatsappAllowed'  => $metaAllowed,
            'can_delete_crm'       => $canDeleteCrm,
            'crm_from'             => $crmFrom,
            'crm_to'               => $crmTo,
            'crm_status'           => $crmStatus,
            'crm_limit'            => $crmLimit,
        ]);
    }

    public function settings(): void
    {
        $this->ensureTenantContext();
        $this->requirePermissionSafe(['queue.settings', 'queue.manage']);

        $tenantId = (string) TenantContext::getId();
        $settings = $this->queueService->getSettings($tenantId);
        $metaAllowed = $this->isMetaWhatsappAllowedForTenant($tenantId);

        $this->view('admin/queue/settings', [
            'settings'              => $settings,
            'metaWhatsappAllowed'   => $metaAllowed,
        ]);
    }

    /**
     * Süper admin tarafından işletmeye verilmiş Meta/WhatsApp iznini döner.
     * customers.meta_whatsapp_enabled=1 ise WhatsApp şablon/bildirim alanları
     * düzenlenebilir. 0 ise işletme template adı veya whatsapp_enabled
     * değerini değiştiremez.
     */
    private function isMetaWhatsappAllowedForTenant(string $tenantId): bool
    {
        try {
            $db = DependencyFactory::getDatabase();
            $stmt = $db->prepare('SELECT meta_whatsapp_enabled FROM customers WHERE customer_id = :cid LIMIT 1');
            $stmt->execute(['cid' => $tenantId]);
            return (int) $stmt->fetchColumn() === 1;
        } catch (\Throwable $e) {
            Logger::warning('isMetaWhatsappAllowedForTenant failed', [
                'tenant_id' => $tenantId,
                'error'     => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function saveSettings(): void
    {
        $this->ensureTenantContext();
        $this->requirePermissionSafe(['queue.settings', 'queue.manage']);

        $tenantId = (string) TenantContext::getId();
        $data = RequestParser::getRequestData();

        $langs = [];
        if (!empty($data['languages']) && is_array($data['languages'])) {
            foreach ($data['languages'] as $lang) {
                $lang = strtolower(trim((string) $lang));
                if (preg_match('/^[a-z]{2}$/', $lang)) {
                    $langs[] = $lang;
                }
            }
        }
        $langs = array_values(array_unique($langs));
        if (empty($langs)) {
            $langs = ['tr', 'en'];
        }

        $payload = [
            'is_enabled'             => !empty($data['is_enabled']) ? 1 : 0,
            'average_wait_minutes'   => max(1, (int) ($data['average_wait_minutes'] ?? 15)),
            'notify_positions_ahead' => max(0, (int) ($data['notify_positions_ahead'] ?? 0)),
            'qr_token_ttl_seconds'   => max(15, min(3600, (int) ($data['qr_token_ttl_seconds'] ?? 90))),
            'max_party_size'         => max(1, min(50, (int) ($data['max_party_size'] ?? 12))),
            'languages'              => $langs,
            'default_language'       => in_array($data['default_language'] ?? 'tr', $langs, true) ? $data['default_language'] : $langs[0],
            'display_title'          => $this->normalizeLangMap($data['display_title'] ?? null, $langs),
            'display_subtitle'       => $this->normalizeLangMap($data['display_subtitle'] ?? null, $langs),
            'display_call_to_action' => $this->normalizeLangMap($data['display_call_to_action'] ?? null, $langs),
            'display_theme_color'    => $this->safeColor($data['display_theme_color'] ?? '#0f172a'),
            'display_accent_color'   => $this->safeColor($data['display_accent_color'] ?? '#f97316'),
            'display_bg_image_url'   => $this->safeUrl($data['display_bg_image_url'] ?? ''),
            'display_theme'          => in_array(($data['display_theme'] ?? ''), \App\Support\QueueThemeRegistry::keys(), true)
                                            ? $data['display_theme']
                                            : \App\Support\QueueThemeRegistry::DEFAULT,
            'show_logo'              => !empty($data['show_logo']) ? 1 : 0,
            'show_active_numbers'    => !empty($data['show_active_numbers']) ? 1 : 0,
            'show_estimated_wait'    => !empty($data['show_estimated_wait']) ? 1 : 0,
            'show_powered_by'        => !empty($data['show_powered_by']) ? 1 : 0,
            'require_email'          => !empty($data['require_email']) ? 1 : 0,
            'require_note'           => !empty($data['require_note']) ? 1 : 0,
            'allow_baby'             => !empty($data['allow_baby']) ? 1 : 0,
            'allow_accessibility'    => !empty($data['allow_accessibility']) ? 1 : 0,
            // Meta WhatsApp ayarları sadece süper admin işletmeye izin verdiyse
            // kaydedilir. İzinsiz tenantlar bu bayrağı POST etse bile
            // whatsapp_enabled=0 olarak zorlanır.
            // Template adı global süper admin ayarından (meta_queue_template_name)
            // alındığı için işletme payload'u template'i artık değiştiremez.
            'whatsapp_enabled'       => ($this->isMetaWhatsappAllowedForTenant($tenantId) && !empty($data['whatsapp_enabled'])) ? 1 : 0,
            'email_enabled'          => !empty($data['email_enabled']) ? 1 : 0,
            'auto_no_show_minutes'   => max(0, min(120, (int) ($data['auto_no_show_minutes'] ?? 5))),
            'entry_cooldown_minutes' => max(0, min(1440, (int) ($data['entry_cooldown_minutes'] ?? 90))),
            'auto_queue_from_tables' => !empty($data['auto_queue_from_tables']) ? 1 : 0,
            'welcome_youtube_url'    => $this->sanitizeWelcomeYoutubeUrl(trim((string) ($data['welcome_youtube_url'] ?? ''))),
        ];

        $ok = $this->queueService->updateSettings($tenantId, $payload);

        if ($this->wantsJson()) {
            $this->jsonResponse(['success' => $ok]);
            return;
        }

        $this->toastFlash($ok ? 'success' : 'error', $ok ? 'notifications.success.updated' : 'notifications.error.update_failed');
        $this->redirectTo('/business/queue/settings');
    }

    /**
     * Patch a single setting key from the dashboard (AJAX).
     * Accepts JSON body: { key: "...", value: ..., lang?: "tr" }
     * Only whitelisted keys are allowed.
     */
    public function patchSetting(): void
    {
        $this->ensureTenantContext();
        $this->requirePermissionSafe(['queue.settings', 'queue.manage']);

        $tenantId = (string) TenantContext::getId();
        $data = RequestParser::getRequestData();

        $key   = (string) ($data['key'] ?? '');
        $value = $data['value'] ?? null;
        $lang  = (string) ($data['lang'] ?? '');

        $allowedFlags = ['is_enabled','is_accepting_queue','auto_queue_from_tables','show_logo','show_active_numbers','show_estimated_wait','show_waiting_count','show_powered_by','require_email','require_note','allow_baby','allow_accessibility','whatsapp_enabled','email_enabled'];
        // `whatsapp_template_name` is intentionally NOT in this list:
        // templates are now globally owned by the super admin
        // (system_settings.meta_queue_template_name). Businesses can only
        // toggle whether they want WhatsApp notifications at all, provided
        // the super admin has granted them Meta access.
        $allowedStrings = [
            'display_theme_color','display_accent_color','display_bg_image_url',
            'default_language',
            'welcome_title','welcome_subtitle','welcome_tagline','welcome_hours',
            'social_instagram','social_facebook','social_tiktok','social_whatsapp',
            'social_website','social_menu_url','social_phone','social_address',
            'social_google_review',
        ];
        $allowedLangMaps = ['display_title','display_subtitle','display_call_to_action'];
        $allowedInts = [
            'average_wait_minutes'   => [1, 240],
            'max_party_size'         => [1, 50],
            'qr_token_ttl_seconds'   => [15, 3600],
            'auto_no_show_minutes'   => [0, 120],
            'entry_cooldown_minutes' => [0, 1440],
        ];

        $current = $this->queueService->getSettings($tenantId);
        $patch = [];

        // Meta/WhatsApp ayarlarını sadece süper admin izin vermişse güncellemeye
        // izin ver. İzinsiz tenant bu uç noktayı çağırırsa 403 döner. Template
        // adı artık global (system_settings.meta_queue_template_name) olduğu için
        // işletme yalnızca whatsapp_enabled bayrağını değiştirebilir.
        if ($key === 'whatsapp_enabled'
            && !$this->isMetaWhatsappAllowedForTenant($tenantId)) {
            $this->jsonResponse([
                'success' => false,
                'error'   => 'meta_not_permitted',
                'message' => 'WhatsApp bildirimleri için süper admin onayı gerekli.',
            ], 403);
            return;
        }

        if (in_array($key, $allowedFlags, true)) {
            $patch[$key] = !empty($value) ? 1 : 0;
        } elseif (array_key_exists($key, $allowedInts)) {
            [$min, $max] = $allowedInts[$key];
            $patch[$key] = max($min, min($max, (int) $value));
        } elseif ($key === 'languages') {
            $v = is_array($value) ? $value : [];
            $v = array_values(array_filter(array_map(static function ($c) {
                $c = strtolower(trim((string) $c));
                return preg_match('/^[a-z]{2}$/', $c) ? $c : null;
            }, $v)));
            if (!$v) $v = ['tr'];
            $patch['languages'] = $v;
        } elseif ($key === 'display_theme') {
            $v = strtolower(trim((string) $value));
            if (!in_array($v, \App\Support\QueueThemeRegistry::keys(), true)) {
                $this->jsonResponse(['success' => false, 'error' => 'invalid_theme'], 422);
                return;
            }
            $patch['display_theme'] = $v;
        } elseif ($key === 'welcome_youtube_url') {
            $raw = trim((string) $value);
            $clean = $this->sanitizeWelcomeYoutubeUrl($raw);
            if ($raw !== '' && $clean === '') {
                $this->jsonResponse(['success' => false, 'error' => 'invalid_youtube_url'], 422);
                return;
            }
            $patch[$key] = $clean;
        } elseif (in_array($key, $allowedStrings, true)) {
            if (str_ends_with($key, '_color')) {
                $patch[$key] = $this->safeColor((string) $value);
            } elseif (str_ends_with($key, '_url')) {
                $patch[$key] = $this->safeUrl((string) $value);
            } elseif ($key === 'default_language') {
                $v = strtolower(trim((string) $value));
                if (!preg_match('/^[a-z]{2}$/', $v)) {
                    $this->jsonResponse(['success' => false, 'error' => 'invalid_lang'], 422);
                    return;
                }
                $patch[$key] = $v;
            } else {
                $patch[$key] = trim((string) $value);
            }
        } elseif (in_array($key, $allowedLangMaps, true)) {
            if ($lang === '' || !preg_match('/^[a-z]{2}$/', $lang)) {
                $this->jsonResponse(['success' => false, 'error' => 'invalid_lang'], 422);
                return;
            }
            $existing = is_array($current[$key] ?? null) ? $current[$key] : [];
            $existing[$lang] = trim((string) $value);
            $patch[$key] = $existing;
        } else {
            $this->jsonResponse(['success' => false, 'error' => 'key_not_allowed'], 422);
            return;
        }

        $ok = $this->queueService->updateSettings($tenantId, $patch);
        $this->jsonResponse([
            'success'  => $ok,
            'settings' => $this->queueService->getSettings($tenantId),
        ]);
    }

    public function notify($id = null): void
    {
        $this->ensureTenantContext();
        $this->requirePermissionSafe(['queue.manage', 'reservations.manage']);

        $tenantId = (string) TenantContext::getId();
        $entryId  = (int) $id;

        $entry = $this->loadEntry($entryId, $tenantId);
        if (!$entry) {
            $this->jsonResponse(['success' => false, 'error' => 'not_found'], 404);
            return;
        }
        if (!in_array($entry['status'], ['WAITING', 'NOTIFIED'], true)) {
            $this->jsonResponse(['success' => false, 'error' => 'wrong_state'], 409);
            return;
        }

        $settings = $this->queueService->getSettings($tenantId);
        $business = TenantContext::get() ?: [];

        $data = RequestParser::getRequestData();
        $tableId = trim((string) ($data['table_id'] ?? ''));
        $tableLabel = '';
        if ($tableId !== '') {
            try {
                $table = DependencyFactory::getTableService()->getTableById($tableId);
                if ($table && (string) ($table['tenant_id'] ?? '') === $tenantId) {
                    $tableLabel = $this->composeTableLabel($table);
                }
            } catch (\Throwable $e) {
                Logger::warning('notify: table lookup failed', ['error' => $e->getMessage()]);
            }
        }

        $notifyResult = [];
        try {
            $notifyResult = $this->queueNotificationService->notifyTableReady($entry, $settings, $business, $tableLabel);
        } catch (\Throwable $e) {
            Logger::error('QueueController::notify - notification failed', [
                'entry' => $entry['id'] ?? null,
                'error' => $e->getMessage(),
            ]);
            $notifyResult = ['error' => $e->getMessage()];
        }

        if ($tableLabel !== '') {
            $notifyResult['table_label'] = $tableLabel;
            $notifyResult['table_id']    = $tableId;
        }

        $this->queueService->markNotified((int) $entry['id'], $notifyResult);

        $this->jsonResponse([
            'success' => true,
            'result'  => $notifyResult,
            'table_label' => $tableLabel,
        ]);
    }

    /**
     * Masa için "Kat 1 · Masa 4" benzeri okunur etiket üret.
     */
    private function composeTableLabel(array $table): string
    {
        $parts = [];
        $floor = trim((string) ($table['floor'] ?? ''));
        if ($floor !== '') {
            $parts[] = (ctype_digit($floor) ? ('Kat ' . $floor) : $floor);
        }
        $zoneName = '';
        $zoneId = (string) ($table['zone_id'] ?? '');
        if ($zoneId !== '') {
            try {
                $zs = DependencyFactory::getZoneService();
                $z = $zs->getZoneById($zoneId);
                $zoneName = trim((string) ($z['name'] ?? ''));
            } catch (\Throwable $e) { /* noop */ }
        }
        if ($zoneName !== '') {
            $parts[] = $zoneName;
        }
        $name = trim((string) ($table['name'] ?? ''));
        if ($name !== '') {
            $parts[] = (ctype_digit($name) ? ('Masa ' . $name) : $name);
        }
        return implode(' · ', $parts);
    }

    /**
     * Garsona "Çağır" ekranında gösterilecek uygun (FREE) masa listesi.
     */
    public function availableTables(): void
    {
        $this->ensureTenantContext();
        $this->requirePermissionSafe(['queue.manage', 'reservations.manage']);

        $tenantId = (string) TenantContext::getId();
        try {
            $all = DependencyFactory::getTableService()->getAllTables();
        } catch (\Throwable $e) {
            $all = [];
        }
        $rows = [];
        foreach ($all as $t) {
            if ((string) ($t['tenant_id'] ?? '') !== $tenantId) {
                continue;
            }
            $status = (string) ($t['status'] ?? 'FREE');
            if ($status !== 'FREE') {
                continue;
            }
            $rows[] = [
                'table_id' => (string) ($t['table_id'] ?? ''),
                'label'    => $this->composeTableLabel($t),
                'capacity' => (int) ($t['capacity'] ?? 0),
                'zone_id'  => (string) ($t['zone_id'] ?? ''),
                'floor'    => (string) ($t['floor'] ?? ''),
                'name'     => (string) ($t['name'] ?? ''),
            ];
        }
        usort($rows, static function ($a, $b) {
            return strnatcmp($a['label'], $b['label']);
        });
        $this->jsonResponse(['success' => true, 'tables' => $rows]);
    }

    public function seat($id = null): void
    {
        $this->mutateEntry((int) $id, function ($entry) {
            return $this->queueService->markSeated((int) $entry['id']);
        });
    }

    public function cancel($id = null): void
    {
        $this->mutateEntry((int) $id, function ($entry) {
            return $this->queueService->cancel((int) $entry['id'], false);
        });
    }

    public function noShow($id = null): void
    {
        $this->mutateEntry((int) $id, function ($entry) {
            return $this->queueService->markNoShow((int) $entry['id']);
        });
    }

    public function apiList(): void
    {
        $this->ensureTenantContext();
        $this->requirePermissionSafe(['queue.view', 'reservations.view']);

        $tenantId = (string) TenantContext::getId();
        $active   = $this->queueService->getActiveQueue($tenantId);
        $settings = $this->queueService->getSettings($tenantId);

        $this->jsonResponse([
            'success' => true,
            'active'  => array_map([$this, 'publicizeEntry'], $active),
            'avg_wait' => (int) ($settings['average_wait_minutes'] ?? 15),
        ]);
    }

    /**
     * CRM: delete completed/historical queue rows (not WAITING/NOTIFIED unless include_active=1).
     */
    public function deleteCrmEntries(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->jsonResponse(['success' => false, 'error' => 'method'], 405);
            return;
        }
        $this->ensureTenantContext();
        $this->requirePermissionSafe(['queue.manage', 'queue.settings']);

        $tenantId = (string) TenantContext::getId();
        $data      = RequestParser::getRequestData();
        $ids       = $data['ids'] ?? [];
        if (is_string($ids)) {
            $ids = array_map('intval', array_filter(explode(',', $ids), static function ($s) {
                return trim($s) !== '';
            }));
        } elseif (!is_array($ids)) {
            $ids = [];
        }
        $ids = array_values(array_filter(array_map('intval', $ids), static function ($i) {
            return $i > 0;
        }));
        if ($ids === []) {
            $this->jsonResponse(['success' => false, 'error' => 'no_ids'], 400);
            return;
        }
        if (count($ids) > 500) {
            $ids = array_slice($ids, 0, 500);
        }
        $includeActive = !empty($data['include_active']);
        $n = $this->queueService->deleteCrmEntries($tenantId, $ids, $includeActive);
        if ($n === 0) {
            $this->jsonResponse([
                'success' => false,
                'error'   => 'no_rows',
                'message' => $includeActive
                    ? 'Kayıt silinemedi.'
                    : 'Kayıt silinemedi. Aktif sırada (bekleyen/çağrıldı) olanları silmek için önce iptal edin veya include_active gönderin.',
            ], 409);
            return;
        }
        $this->jsonResponse(['success' => true, 'deleted' => $n]);
    }

    public function rotateQrToken(): void
    {
        $this->ensureTenantContext();
        $this->requirePermissionSafe(['queue.manage', 'queue.settings']);

        $tenantId = (string) TenantContext::getId();
        $token = $this->queueService->rotateToken($tenantId);

        $this->jsonResponse([
            'success' => true,
            'token'   => $token,
        ]);
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------
    private function mutateEntry(int $entryId, callable $fn): void
    {
        $this->ensureTenantContext();
        $this->requirePermissionSafe(['queue.manage', 'reservations.manage']);

        $tenantId = (string) TenantContext::getId();
        $entry = $this->loadEntry($entryId, $tenantId);
        if (!$entry) {
            $this->jsonResponse(['success' => false, 'error' => 'not_found'], 404);
            return;
        }
        $ok = (bool) $fn($entry);
        $this->jsonResponse(['success' => $ok]);
    }

    private function loadEntry(int $entryId, string $tenantId): ?array
    {
        try {
            $model = new \App\Models\QueueEntry();
            $row = $model->fetchByIdForTenant($entryId, $tenantId);
            return $row ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function publicizeEntry(array $e): array
    {
        return [
            'id'           => (int) $e['id'],
            'queue_id'     => $e['queue_id'] ?? '',
            'queue_number' => (int) ($e['queue_number'] ?? 0),
            'name'         => $e['name'] ?? '',
            'surname'      => $e['surname'] ?? '',
            'phone'        => $e['phone'] ?? '',
            'email'        => $e['email'] ?? '',
            'party_size'   => (int) ($e['party_size'] ?? 1),
            'has_baby'     => (int) ($e['has_baby'] ?? 0),
            'has_accessibility' => (int) ($e['has_accessibility'] ?? 0),
            'note'         => $e['note'] ?? '',
            'language'     => $e['language'] ?? 'tr',
            'status'       => $e['status'] ?? 'WAITING',
            'notified_at'  => $e['notified_at'] ?? null,
            'created_at'   => $e['created_at'] ?? null,
        ];
    }

    private function buildDoorDisplayUrl(string $tenantId): string
    {
        // Delegates to the platform-wide UrlService so that subdomain + apex
        // (e.g. qordy.com) are always resolved consistently. Works from BOTH
        // the main domain (admin panel) and from any subdomain.
        try {
            $urlService = \App\Core\DependencyFactory::getUrlService();
            return $urlService->buildTenantUrl($tenantId, '/sira');
        } catch (\Throwable $e) {
            Logger::warning('QueueController::buildDoorDisplayUrl fallback', [
                'error' => $e->getMessage(),
            ]);
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $apex = $_ENV['BASE_DOMAIN'] ?? 'qordy.com';
            return $protocol . '://' . $apex . '/sira';
        }
    }

    private function normalizeLangMap($raw, array $langs): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw)) {
            $raw = [];
        }
        $out = [];
        foreach ($langs as $l) {
            if (isset($raw[$l]) && is_string($raw[$l])) {
                $out[$l] = trim(mb_substr($raw[$l], 0, 255));
            }
        }
        return $out;
    }

    private function safeColor(string $color): string
    {
        $color = trim($color);
        return preg_match('/^#[0-9a-fA-F]{3,8}$/', $color) ? $color : '#0f172a';
    }

    private function safeUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    /**
     * youtube.com / youtu.be only; returns empty string to clear.
     */
    private function sanitizeWelcomeYoutubeUrl(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        $url = $this->safeUrl($raw);
        if (!$url) {
            return '';
        }
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));
        $okHosts = [
            'youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com',
            'youtu.be', 'www.youtu.be',
        ];
        $allowed = false;
        foreach ($okHosts as $h) {
            if ($host === $h || str_ends_with($host, '.' . $h)) {
                $allowed = true;
                break;
            }
        }
        if (!$allowed) {
            return '';
        }
        if (!function_exists('qd_youtube_video_id')) {
            require_once __DIR__ . '/../views/queue/_helpers.php';
        }
        if (qd_youtube_video_id($url) === null) {
            return '';
        }
        return mb_substr($url, 0, 512);
    }

    private function requirePermissionSafe(array $options): void
    {
        // Prefer first matching granted permission; fall back to just requiring login.
        if (!$this->isLoggedIn()) {
            $this->redirectTo('/login');
            exit;
        }
        foreach ($options as $perm) {
            if (method_exists($this, 'hasPermission') && $this->hasPermission($perm)) {
                return;
            }
        }
        // allow superadmin / owner always
        if (method_exists($this, 'isSuperAdmin') && $this->isSuperAdmin()) {
            return;
        }
        // as last resort, still allow – queue is a non-destructive feature but log it
        Logger::info('QueueController: permission lacked, proceeding with logged-in user', [
            'needed' => $options,
        ]);
    }

    private function wantsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $xhr = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        return strpos($accept, 'application/json') !== false || strtolower($xhr) === 'xmlhttprequest';
    }

    private function toastFlash(string $type, string $key): void
    {
        if ($this->toastNotificationService && method_exists($this->toastNotificationService, 'setFlash')) {
            $this->toastNotificationService->setFlash($type, $key);
        }
    }

    protected function redirectTo(string $path): void
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'qordy.com';
        header('Location: ' . $protocol . '://' . $host . $path);
        exit;
    }
}
