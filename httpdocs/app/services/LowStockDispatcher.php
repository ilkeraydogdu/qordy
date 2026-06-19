<?php
namespace App\Services;

use App\Repositories\IngredientRepository;

/**
 * LowStockDispatcher
 *
 * Scans ingredients (and optionally menu_items) that fell at or below
 * their `min_threshold`, then routes an alert through the multi-channel
 * {@see NotificationDispatcher} according to each row's configured
 * `low_stock_action` / `notify_channels` / `notify_recipients`.
 *
 * Behavior per `low_stock_action`:
 *   - NONE               -> nothing
 *   - NOTIFY_ONLY        -> dispatch alerts, leave availability flags alone
 *   - NOTIFY_AND_DISABLE -> dispatch alerts AND mark is_available=0 / is_auto_disabled=1
 *   - DISABLE_ONLY       -> just flip availability flags, no alerts
 *
 * Dedup: a per-item notification is skipped if a SENT log row exists in
 * `low_stock_notifications_log` within the dedup window (default 6h).
 * The cron entry point, {@see run()}, is designed to be idempotent so it
 * can safely run every few minutes.
 */
class LowStockDispatcher
{
    private \PDO $db;
    private NotificationDispatcher $dispatcher;
    private IngredientRepository $ingredientRepo;
    private int $dedupMinutes = 360; // 6h window

    public function __construct(\PDO $db, NotificationDispatcher $dispatcher, IngredientRepository $ingredientRepo)
    {
        $this->db = $db;
        $this->dispatcher = $dispatcher;
        $this->ingredientRepo = $ingredientRepo;
    }

    /**
     * Scan + dispatch for every tenant. Returns a structured summary for
     * cron logging.
     *
     * @return array{ scanned:int, notified:int, disabled:int, skipped:int, errors:int }
     */
    public function run(): array
    {
        $summary = ['scanned' => 0, 'notified' => 0, 'disabled' => 0, 'skipped' => 0, 'errors' => 0];

        foreach ($this->findLowStockIngredients() as $row) {
            $summary['scanned']++;
            try {
                $r = $this->processItem('INGREDIENT', $row);
                if ($r === 'notified') $summary['notified']++;
                elseif ($r === 'disabled') $summary['disabled']++;
                elseif ($r === 'skipped')  $summary['skipped']++;
            } catch (\Throwable $e) {
                $summary['errors']++;
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning('LowStockDispatcher: item error', [
                        'item_id' => $row['ingredient_id'] ?? null,
                        'error'   => $e->getMessage(),
                    ]);
                }
            }
        }

        foreach ($this->findLowStockMenuItems() as $row) {
            $summary['scanned']++;
            try {
                $r = $this->processItem('MENU_ITEM', $row);
                if ($r === 'notified') $summary['notified']++;
                elseif ($r === 'disabled') $summary['disabled']++;
                elseif ($r === 'skipped')  $summary['skipped']++;
            } catch (\Throwable $e) {
                $summary['errors']++;
            }
        }

        return $summary;
    }

    /**
     * Process a single low-stock item: decide which channels, dedup, dispatch,
     * then auto-disable when configured.
     *
     * @param array<string, mixed> $row
     * @return string one of: notified, disabled, skipped
     */
    private function processItem(string $itemType, array $row): string
    {
        $action   = strtoupper((string)($row['low_stock_action'] ?? 'NOTIFY_ONLY'));
        $tenantId = (string)($row['tenant_id'] ?? '');
        $itemId   = (string)($row[$itemType === 'INGREDIENT' ? 'ingredient_id' : 'item_id'] ?? '');
        $name     = (string)($row['name'] ?? 'Ürün');

        if ($action === 'NONE' || $itemId === '') {
            return 'skipped';
        }

        $didNotify = false;

        if (in_array($action, ['NOTIFY_ONLY', 'NOTIFY_AND_DISABLE'], true)) {
            if ($this->wasRecentlyNotified($tenantId, $itemType, $itemId)) {
                return 'skipped';
            }
            $channels   = $this->parseChannels($row['notify_channels'] ?? 'in_app');
            $recipients = $this->parseRecipients($row['notify_recipients'] ?? null, $tenantId);

            $title = 'Stok uyarısı: ' . $name;
            $body  = sprintf(
                'Mevcut stok %s %s, minimum eşik %s %s.',
                (string)($row['current_stock'] ?? 0),
                (string)($row['unit'] ?? ''),
                (string)($row['min_threshold'] ?? 0),
                (string)($row['unit'] ?? '')
            );

            foreach ($this->fanOutRecipients($recipients, $channels) as $delivery) {
                try {
                    $res = $this->dispatcher->dispatch(array_merge($delivery, [
                        'tenant_id' => $tenantId,
                        'title'     => $title,
                        'body'      => $body,
                        'data'      => [
                            'kind'      => 'LOW_STOCK',
                            'item_type' => $itemType,
                            'item_id'   => $itemId,
                        ],
                    ]));
                    foreach ($res['results'] ?? [] as $ch => $r) {
                        $this->log($tenantId, $itemType, $itemId, $ch, $r['status'] ?? 'SENT', $r['detail'] ?? null);
                    }
                    $didNotify = true;
                } catch (\Throwable $e) {
                    $this->log($tenantId, $itemType, $itemId, 'dispatcher', 'FAILED', $e->getMessage());
                }
            }
        }

        if (in_array($action, ['DISABLE_ONLY', 'NOTIFY_AND_DISABLE'], true)) {
            $this->autoDisable($itemType, $itemId, $tenantId);
            return 'disabled';
        }

        return $didNotify ? 'notified' : 'skipped';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function findLowStockIngredients(): array
    {
        $sql = "SELECT *
                FROM ingredients
                WHERE low_stock_action <> 'NONE'
                  AND current_stock <= min_threshold";
        return $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function findLowStockMenuItems(): array
    {
        // menu_items uses a different column layout; compute low-stock via
        // current_stock (if column exists) or is_sold_out. Fall back quietly.
        try {
            $sql = "SELECT *
                    FROM menu_items
                    WHERE low_stock_action <> 'NONE'
                      AND current_stock IS NOT NULL
                      AND current_stock <= COALESCE(min_threshold, 0)";
            return $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function wasRecentlyNotified(string $tenantId, string $itemType, string $itemId): bool
    {
        $sql = "SELECT 1 FROM low_stock_notifications_log
                WHERE tenant_id = :tid AND item_type = :it AND item_id = :iid
                  AND status = 'SENT'
                  AND created_at >= (NOW() - INTERVAL :mins MINUTE)
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':tid'  => $tenantId,
            ':it'   => $itemType,
            ':iid'  => $itemId,
            ':mins' => $this->dedupMinutes,
        ]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * @return string[]
     */
    private function parseChannels(string $raw): array
    {
        $raw = strtolower(trim($raw));
        if ($raw === '') return ['in_app'];
        $out = [];
        foreach (explode(',', $raw) as $c) {
            $c = trim($c);
            if ($c === '') continue;
            if ($c === 'meta') $c = 'whatsapp';
            $out[] = $c;
        }
        return $out ?: ['in_app'];
    }

    /**
     * @return array<int, array{email?:string, phone?:string, user_id?:string}>
     */
    private function parseRecipients($raw, string $tenantId): array
    {
        $list = [];
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $item) {
                    if (is_array($item)) $list[] = $item;
                }
            }
        } elseif (is_array($raw)) {
            foreach ($raw as $item) {
                if (is_array($item)) $list[] = $item;
            }
        }
        if (!empty($list)) return $list;

        // Fallback: BUSINESS_OWNER email/phone for this tenant.
        try {
            $stmt = $this->db->prepare(
                "SELECT email, phone, user_id FROM users
                 WHERE tenant_id = :tid
                   AND role IN ('BUSINESS_OWNER', 'BUSINESS_MANAGER', 'STOCK_MANAGER')
                 LIMIT 5"
            );
            $stmt->execute([':tid' => $tenantId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $u) {
                $list[] = [
                    'email'   => $u['email'] ?? null,
                    'phone'   => $u['phone'] ?? null,
                    'user_id' => $u['user_id'] ?? null,
                ];
            }
        } catch (\Throwable $e) { /* ignore */ }

        if (empty($list)) {
            $list[] = []; // at minimum, deliver an in-app broadcast.
        }
        return $list;
    }

    /**
     * Expand each (recipient, channel) combination into a dispatcher payload.
     *
     * @param array<int, array<string, mixed>> $recipients
     * @param string[] $channels
     * @return array<int, array<string, mixed>>
     */
    private function fanOutRecipients(array $recipients, array $channels): array
    {
        $out = [];
        foreach ($recipients as $r) {
            $out[] = [
                'channels' => $channels,
                'email'    => $r['email'] ?? null,
                'phone'    => $r['phone'] ?? null,
                'user_id'  => $r['user_id'] ?? null,
            ];
        }
        return $out;
    }

    private function autoDisable(string $itemType, string $itemId, string $tenantId): void
    {
        try {
            $table = $itemType === 'MENU_ITEM' ? 'menu_items' : 'ingredients';
            $pk    = $itemType === 'MENU_ITEM' ? 'item_id'    : 'ingredient_id';
            $sql = "UPDATE {$table}
                    SET is_available = 0, is_auto_disabled = 1, updated_at = NOW()
                    WHERE {$pk} = :id";
            $params = [':id' => $itemId];
            if ($tenantId !== '') {
                $sql .= ' AND tenant_id = :tid';
                $params[':tid'] = $tenantId;
            }
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $this->log($tenantId, $itemType, $itemId, 'auto_disable', 'SENT', 'is_auto_disabled=1');
        } catch (\Throwable $e) {
            $this->log($tenantId, $itemType, $itemId, 'auto_disable', 'FAILED', $e->getMessage());
        }
    }

    private function log(string $tenantId, string $itemType, string $itemId, string $channel, string $status, ?string $detail): void
    {
        try {
            $status = in_array(strtoupper($status), ['SENT', 'FAILED', 'SKIPPED'], true)
                ? strtoupper($status) : 'SENT';
            $stmt = $this->db->prepare(
                "INSERT INTO low_stock_notifications_log
                 (log_id, tenant_id, item_type, item_id, channel, status, detail, created_at)
                 VALUES (:lid, :tid, :it, :iid, :ch, :st, :det, NOW())"
            );
            $stmt->execute([
                ':lid' => 'lsn_' . bin2hex(random_bytes(10)),
                ':tid' => $tenantId,
                ':it'  => $itemType,
                ':iid' => $itemId,
                ':ch'  => substr($channel, 0, 30),
                ':st'  => $status,
                ':det' => $detail !== null ? substr($detail, 0, 1000) : null,
            ]);
        } catch (\Throwable $e) {
            // logging failure is not fatal
        }
    }
}
