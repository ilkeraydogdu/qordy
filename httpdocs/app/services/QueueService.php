<?php
namespace App\Services;

use App\Models\QueueEntry;
use App\Models\QueueSettings;
use App\Models\QueueQrToken;
use App\Core\Logger;

require_once __DIR__ . '/../models/QueueEntry.php';
require_once __DIR__ . '/../models/QueueSettings.php';
require_once __DIR__ . '/../models/QueueQrToken.php';
require_once __DIR__ . '/../core/DependencyFactory.php';

/**
 * QueueService - core business logic for the QR-based waiting-line (sıra) feature.
 *
 * Responsibilities:
 *   - Create queue entries with duplicate/cooldown protection
 *   - Compute real-time position info for polling clients
 *   - Transition entries between WAITING / NOTIFIED / SEATED / CANCELLED / NO_SHOW
 *   - Defer notification work to QueueNotificationService
 */
class QueueService
{
    private QueueEntry $entryModel;
    private QueueSettings $settingsModel;
    private QueueQrToken $tokenModel;

    public function __construct()
    {
        $this->entryModel = new QueueEntry();
        $this->settingsModel = new QueueSettings();
        $this->tokenModel = new QueueQrToken();
    }

    public function getSettings(string $tenantId): array
    {
        return $this->settingsModel->findForTenant($tenantId);
    }

    /**
     * Kapı ekranı / form / enqueue için: sıra modu açık mı?
     * auto_queue_from_tables=1 iken: en az bir masa FREE ise yönetimdeki (manuel) is_accepting_queue
     * geçerli; hiç FREE yoksa (tümü dolu) sıra modu zorunlu açılır.
     */
    public function getEffectiveIsAcceptingQueue(string $tenantId, ?array $settings = null): bool
    {
        $settings = $settings ?? $this->getSettings($tenantId);
        $manual = !empty($settings['is_accepting_queue']);
        if (empty($settings['auto_queue_from_tables'])) {
            return $manual;
        }
        $tables = [];
        try {
            $tableService = \App\Core\DependencyFactory::getTableService();
            $tables = $tableService->getAllTables();
        } catch (\Throwable $e) {
            Logger::warning('getEffectiveIsAcceptingQueue: tables', ['error' => $e->getMessage(), 'tenant' => $tenantId]);
            return $manual;
        }
        if (count($tables) === 0) {
            return $manual;
        }
        foreach ($tables as $t) {
            if (($t['status'] ?? 'FREE') === 'FREE') {
                return $manual;
            }
        }
        return true;
    }

    public function updateSettings(string $tenantId, array $data): bool
    {
        return $this->settingsModel->updateForTenant($tenantId, $data);
    }

    /**
     * Accept a new visitor into the queue.
     *
     * @param string $tenantId
     * @param array  $data    user-submitted + server-filled fields
     * @return array ['success' => bool, 'entry' => array|null, 'error' => string|null, 'code' => string|null]
     */
    public function enqueue(string $tenantId, array $data): array
    {
        $settings = $this->getSettings($tenantId);
        if (empty($settings['is_enabled'])) {
            return ['success' => false, 'error' => 'queue_disabled', 'code' => 'QUEUE_DISABLED'];
        }
        if (!$this->getEffectiveIsAcceptingQueue($tenantId, $settings)) {
            return ['success' => false, 'error' => 'queue_not_accepting', 'code' => 'QUEUE_NOT_ACCEPTING'];
        }

        $name     = trim((string) ($data['name']  ?? ''));
        $phoneCC  = trim((string) ($data['phone_country'] ?? ''));
        $phone    = $this->normalizePhone((string) ($data['phone'] ?? ''), $phoneCC);
        $party    = max(1, (int) ($data['party_size'] ?? 1));
        $maxParty = max(1, (int) ($settings['max_party_size'] ?? 12));
        $sessionKey = (string) ($data['session_key'] ?? '');

        if ($name === '' || $phone === '' || $sessionKey === '') {
            return ['success' => false, 'error' => 'missing_fields', 'code' => 'VALIDATION'];
        }
        if (!$this->isValidE164($phone)) {
            return ['success' => false, 'error' => 'invalid_phone', 'code' => 'VALIDATION'];
        }
        if ($party > $maxParty) {
            return [
                'success' => false,
                'error' => 'party_too_large',
                'code' => 'PARTY_TOO_LARGE',
                'max' => $maxParty,
            ];
        }
        if (!empty($settings['require_email']) && empty($data['email'])) {
            return ['success' => false, 'error' => 'email_required', 'code' => 'VALIDATION'];
        }

        // 1) Same browser session already has an active ticket?
        $existingSession = $this->entryModel->findActiveBySessionKey($tenantId, $sessionKey);
        if ($existingSession) {
            return [
                'success' => true,
                'entry' => $existingSession,
                'reused' => true,
            ];
        }

        // 2) Same phone inside cooldown window?
        $cooldown = (int) ($settings['entry_cooldown_minutes'] ?? 90);
        $recent = $this->entryModel->findRecentByPhone($tenantId, $phone, $cooldown);
        if ($recent && in_array($recent['status'], QueueEntry::ACTIVE_STATUSES, true)) {
            return [
                'success' => true,
                'entry' => $recent,
                'reused' => true,
            ];
        }
        if ($recent && !in_array($recent['status'], QueueEntry::ACTIVE_STATUSES, true)) {
            // Recently completed / cancelled with same phone – block briefly to stop spam
            return [
                'success' => false,
                'error' => 'recently_used',
                'code' => 'COOLDOWN',
                'minutes' => $cooldown,
            ];
        }

        $queueNumber = $this->entryModel->nextQueueNumberForToday($tenantId);
        $queueId = 'Q' . strtoupper(bin2hex(random_bytes(6)));

        $language = (string) ($data['language'] ?? $settings['default_language']);
        $allowedLanguages = $settings['languages'] ?? ['tr'];
        if (!in_array($language, $allowedLanguages, true)) {
            $language = $settings['default_language'] ?? 'tr';
        }

        $row = [
            'tenant_id'          => $tenantId,
            'queue_id'           => $queueId,
            'queue_number'       => $queueNumber,
            'token_id'           => $data['token_id'] ?? null,
            'session_key'        => $sessionKey,
            'device_fingerprint' => $data['device_fingerprint'] ?? null,
            'ip_address'         => $data['ip_address'] ?? null,
            'user_agent'         => $this->truncate((string) ($data['user_agent'] ?? ''), 250),
            'name'               => $this->truncate($name, 120),
            'surname'            => $this->truncate(trim((string) ($data['surname'] ?? '')), 120) ?: null,
            'phone'              => $phone,
            'phone_country'      => $this->truncate((string) ($data['phone_country'] ?? ''), 5) ?: null,
            'email'              => $this->truncate(trim((string) ($data['email'] ?? '')), 255) ?: null,
            'party_size'         => $party,
            'has_baby'           => !empty($data['has_baby']) ? 1 : 0,
            'has_accessibility'  => !empty($data['has_accessibility']) ? 1 : 0,
            'note'               => $this->truncate(trim((string) ($data['note'] ?? '')), 500) ?: null,
            'language'           => $language,
            'status'             => QueueEntry::STATUS_WAITING,
            'marketing_opt_in'   => !empty($data['marketing_opt_in']) ? 1 : 0,
            'notifications'      => json_encode(new \stdClass()),
        ];

        $id = $this->entryModel->createEntry($row);
        if (!$id) {
            return ['success' => false, 'error' => 'db_error', 'code' => 'INTERNAL'];
        }

        if (!empty($data['token_id'])) {
            $this->tokenModel->incrementConsumption((int) $data['token_id']);
        }

        $entry = $this->entryModel->findByQueueId($queueId);

        // IMPORTANT: queue entries are NOT mirrored into `contact_forms` anymore.
        // That mirror polluted the super-admin contact-forms inbox with synthetic
        // "[queue/...]" rows that had nothing to do with landing-page inquiries.
        // The `queue_entries` table IS the CRM source of truth — the per-business
        // admin and the super-admin queue view both read from it directly.

        return ['success' => true, 'entry' => $entry, 'reused' => false];
    }

    /**
     * Position + eta info, safe for public polling. Does not expose PII of other people.
     */
    public function buildPublicStatus(array $entry): array
    {
        $settings = $this->getSettings($entry['tenant_id']);
        $ahead = 0;
        if (in_array($entry['status'], QueueEntry::ACTIVE_STATUSES, true)) {
            $ahead = $this->entryModel->countWaitingAhead($entry['tenant_id'], (int) $entry['id']);
        }

        $position = $entry['status'] === QueueEntry::STATUS_WAITING ? $ahead + 1 : 0;
        $avgWait = max(1, (int) ($settings['average_wait_minutes'] ?? 15));
        $etaMinutes = $entry['status'] === QueueEntry::STATUS_WAITING
            ? $ahead * $avgWait
            : 0;

        $tableLabel = '';
        $notifJson = $entry['notifications'] ?? null;
        if (is_string($notifJson) && $notifJson !== '') {
            $decoded = json_decode($notifJson, true);
            if (is_array($decoded) && !empty($decoded['table_label'])) {
                $tableLabel = (string) $decoded['table_label'];
            }
        } elseif (is_array($notifJson) && !empty($notifJson['table_label'])) {
            $tableLabel = (string) $notifJson['table_label'];
        }

        return [
            'queue_id'       => $entry['queue_id'],
            'queue_number'   => (int) $entry['queue_number'],
            'status'         => $entry['status'],
            'name'           => $entry['name'],
            'party_size'     => (int) $entry['party_size'],
            'language'       => $entry['language'],
            'ahead'          => $ahead,
            'position'       => $position,
            'eta_minutes'    => $etaMinutes,
            'avg_wait'       => $avgWait,
            'notified_at'    => $entry['notified_at'] ?? null,
            'seated_at'      => $entry['seated_at'] ?? null,
            'created_at'     => $entry['created_at'] ?? null,
            'table_label'    => $tableLabel,
            'display_message_key' => $this->statusToMessageKey($entry['status']),
        ];
    }

    public function getEntryForPublic(string $queueId): ?array
    {
        return $this->entryModel->findByQueueId($queueId);
    }

    public function getActiveQueue(string $tenantId): array
    {
        return $this->entryModel->getActiveForTenant($tenantId);
    }

    public function getRecent(string $tenantId, int $limit = 50): array
    {
        return $this->entryModel->getRecentForTenant($tenantId, $limit);
    }

    public function getCrmList(string $tenantId, array $filters = []): array
    {
        return $this->entryModel->getFilteredForTenant($tenantId, $filters);
    }

    public function deleteCrmEntries(string $tenantId, array $ids, bool $includeActive = false): int
    {
        return $this->entryModel->deleteByIdsForTenant($tenantId, $ids, $includeActive);
    }

    /**
     * Mark an entry as NOTIFIED (called/summoned). Caller is responsible for sending the actual
     * notification through QueueNotificationService.
     */
    public function markNotified(int $entryId, array $notifyResult = []): bool
    {
        return $this->entryModel->updateStatus($entryId, QueueEntry::STATUS_NOTIFIED, [
            'notified_at'     => date('Y-m-d H:i:s'),
            'notifications'   => $notifyResult,
            'notify_attempts' => 1,
        ]);
    }

    public function markSeated(int $entryId): bool
    {
        return $this->entryModel->updateStatus($entryId, QueueEntry::STATUS_SEATED, [
            'seated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function cancel(int $entryId, bool $byCustomer = false): bool
    {
        return $this->entryModel->updateStatus($entryId, QueueEntry::STATUS_CANCELLED, [
            'cancelled_at' => date('Y-m-d H:i:s'),
            'note'         => $byCustomer ? 'cancelled_by_customer' : null,
        ]);
    }

    public function markNoShow(int $entryId): bool
    {
        return $this->entryModel->updateStatus($entryId, QueueEntry::STATUS_NO_SHOW, [
            'expired_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Find NOTIFIED entries that passed the configured auto_no_show window.
     */
    public function sweepStaleNotified(string $tenantId): int
    {
        $settings = $this->getSettings($tenantId);
        $window = (int) ($settings['auto_no_show_minutes'] ?? 5);
        if ($window <= 0) {
            return 0;
        }
        $stale = $this->entryModel->findStaleNotified($tenantId, $window);
        $count = 0;
        foreach ($stale as $e) {
            if ($this->markNoShow((int) $e['id'])) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Guarantee we always have an active rotating QR token for the door screen.
     */
    public function ensureActiveToken(string $tenantId): array
    {
        $current = $this->tokenModel->findCurrentForTenant($tenantId);
        if ($current) {
            return $current;
        }
        return $this->rotateToken($tenantId);
    }

    public function rotateToken(string $tenantId): array
    {
        $settings = $this->getSettings($tenantId);
        $ttl = (int) ($settings['qr_token_ttl_seconds'] ?? 90);
        $ttl = max(15, min(3600, $ttl));
        $token = bin2hex(random_bytes(16));

        $expiresAt = date('Y-m-d H:i:s', time() + $ttl);

        $id = $this->tokenModel->createToken([
            'tenant_id'  => $tenantId,
            'token'      => $token,
            'expires_at' => $expiresAt,
        ]);

        if ($id) {
            $this->tokenModel->revokeForTenant($tenantId, $id);
            return [
                'id'         => $id,
                'tenant_id'  => $tenantId,
                'token'      => $token,
                'expires_at' => $expiresAt,
                'issued_at'  => date('Y-m-d H:i:s'),
            ];
        }

        // Fallback: try once more without the race-prone revoke
        return [
            'id' => 0,
            'tenant_id' => $tenantId,
            'token' => $token,
            'expires_at' => $expiresAt,
            'issued_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function getTokenByValue(string $token): ?array
    {
        return $this->tokenModel->findByToken($token);
    }

    public function purgeOldTokens(int $olderThanHours = 24): int
    {
        return $this->tokenModel->purgeExpired($olderThanHours);
    }

    /**
     * Pragmatic phone normalizer: strips spaces, dashes, parentheses; keeps leading +
     */
    /**
     * Normalize a phone into E.164 form (+<countrycode><national>).
     *
     * Accepts any of these for a Turkish number:
     *   "05321234567", "5321234567", "+90 532 123 45 67", "90 532 1234567"
     * and always returns "+905321234567".
     *
     * The $country parameter is a country dial code ("+90", "90", "+49", ...).
     * It is only used as a fallback when the phone itself doesn't carry one.
     */
    public function normalizePhone(string $phone, string $country = ''): string
    {
        $phone   = trim($phone);
        $country = trim($country);
        if ($phone === '') {
            return '';
        }

        $hasPlus = str_starts_with($phone, '+');
        $digits  = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return '';
        }

        $ccDigits = preg_replace('/\D+/', '', $country) ?? '';
        if ($ccDigits === '') {
            $ccDigits = '90'; // default assumption for Turkish-first product
        }

        if ($hasPlus) {
            // The whole number already includes a country code.
            return '+' . $digits;
        }

        // No leading "+" -> treat as national number. Strip trunk prefix "0".
        if (str_starts_with($digits, $ccDigits) && strlen($digits) > strlen($ccDigits) + 6) {
            // Number already starts with the dial code, just re-prefix.
            return '+' . $digits;
        }
        $digits = ltrim($digits, '0');
        return '+' . $ccDigits . $digits;
    }

    private function isValidE164(string $phone): bool
    {
        // E.164: leading "+" + 8..15 digits
        return (bool) preg_match('/^\+[1-9]\d{7,14}$/', $phone);
    }

    private function truncate(string $str, int $len): string
    {
        $str = trim($str);
        return mb_substr($str, 0, $len);
    }

    private function statusToMessageKey(string $status): string
    {
        switch ($status) {
            case QueueEntry::STATUS_WAITING:   return 'queue.status.waiting';
            case QueueEntry::STATUS_NOTIFIED:  return 'queue.status.notified';
            case QueueEntry::STATUS_SEATED:    return 'queue.status.seated';
            case QueueEntry::STATUS_CANCELLED: return 'queue.status.cancelled';
            case QueueEntry::STATUS_NO_SHOW:   return 'queue.status.no_show';
            case QueueEntry::STATUS_EXPIRED:   return 'queue.status.expired';
        }
        return 'queue.status.unknown';
    }

}
