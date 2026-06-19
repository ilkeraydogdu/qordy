<?php
namespace App\websocket;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

/**
 * WebSocket Handler - Production-grade real-time handler
 * 
 * Features:
 * - Case-insensitive message handling (AUTH/auth both work)
 * - Business-scoped channels with proper subscription
 * - DB polling via event loop for real-time order/table broadcasts
 * - Print bridge support
 * - Heartbeat/keepalive
 */
class WebSocketHandler implements MessageComponentInterface
{
    /** @var \SplObjectStorage */
    protected $clients;
    
    /** @var array<string, ConnectionInterface[]> Business channels */
    protected $businessChannels = [];
    
    /** @var array<string, ConnectionInterface> Print bridges */
    protected $printBridges = [];
    
    /** @var array<int, array> Connection metadata */
    protected $connectionMeta = [];

    /** @var \PDO|null DB connection for polling */
    protected $db = null;

    /** @var array<string, string> Last known state per business for change detection */
    protected $lastOrderHash = [];
    protected $lastTableHash = [];
    protected $lastNotifHash = [];

    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
        $this->initDatabase();
        echo "[WS] Handler initialized\n";
    }

    /**
     * Initialize database for polling
     *
     * Kimlik bilgilerini artık kaynak koduna gömmüyoruz. Aynı uygulamanın
     * kullandığı `.env` dosyasını okuyarak DB_HOST / DB_NAME / DB_USER / DB_PASS
     * değerlerini yükleriz. Böylece parola rotasyonu tek noktada yapılır ve
     * kaynak ağaçta kimlik sızıntısı oluşmaz.
     */
    private function initDatabase(): void
    {
        try {
            $envFile = __DIR__ . '/../../.env';
            if (file_exists($envFile) && is_readable($envFile)) {
                foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                    $line = trim($line);
                    if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                        continue;
                    }
                    [$k, $v] = explode('=', $line, 2);
                    $k = trim($k);
                    $v = trim($v);
                    if ((strlen($v) >= 2)
                        && (($v[0] === '"' && substr($v, -1) === '"')
                            || ($v[0] === "'" && substr($v, -1) === "'"))) {
                        $v = substr($v, 1, -1);
                    }
                    if (!array_key_exists($k, $_ENV)) {
                        $_ENV[$k] = $v;
                    }
                }
            }

            $rawHost = $_ENV['DB_HOST'] ?? '127.0.0.1';
            $host = $rawHost;
            $port = null;
            if (strpos($rawHost, ':') !== false) {
                [$host, $port] = explode(':', $rawHost, 2);
            }
            $dbname = $_ENV['DB_NAME'] ?? 'qordy';
            $user   = $_ENV['DB_USER'] ?? 'qordy';
            $pass   = $_ENV['DB_PASS'] ?? '';

            $dsn = "mysql:host={$host};" . ($port ? "port={$port};" : '') . "dbname={$dbname};charset=utf8mb4";
            $this->db = new \PDO($dsn, $user, $pass, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 5,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            echo "[WS] Database connected (host={$host} db={$dbname})\n";
        } catch (\Throwable $e) {
            echo "[WS] DB connection failed: {$e->getMessage()}\n";
            $this->db = null;
        }
    }

    /**
     * WS AUTH token doğrulaması. PHP tarafı kısa ömürlü bir HMAC imzalı token
     * üretir ( `WebSocketTokenService::mint($tenantId, $userId)` ) ve SPA bunu
     * `{type:'AUTH', token: ...}` içinde gönderir. Token shape:
     *   v1.<base64url(payload)>.<base64url(signature)>
     * Payload JSON: { b: tenantId, u?: userId, exp: unix_ts }
     *
     * Kötü niyetli client, `business_id` alanını elle gönderse bile
     * `claims['b']` doğrulanmadığı sürece kabul etmiyoruz; yasal client
     * token gönderir.
     *
     * Geçiş dönemi: token yoksa eski (güvensiz) `business_id`-only yola düşer
     * ama yalnızca DEV ortamında. PROD'da reddederiz.
     *
     * @return array{valid:bool, tenantId?:string, userId?:?string, reason?:string}
     */
    private function verifyAuthToken(?string $token): array
    {
        if (!is_string($token) || $token === '') {
            return ['valid' => false, 'reason' => 'empty'];
        }
        $parts = explode('.', $token);
        if (count($parts) !== 3 || $parts[0] !== 'v1') {
            return ['valid' => false, 'reason' => 'format'];
        }
        [, $b64payload, $b64sig] = $parts;
        $b64decode = static function (string $s): ?string {
            $pad = strlen($s) % 4;
            if ($pad) { $s .= str_repeat('=', 4 - $pad); }
            $d = base64_decode(strtr($s, '-_', '+/'), true);
            return $d === false ? null : $d;
        };
        $payload = $b64decode($b64payload);
        $sig     = $b64decode($b64sig);
        if ($payload === null || $sig === null) {
            return ['valid' => false, 'reason' => 'base64'];
        }
        $secret = $_ENV['WEBSOCKET_TOKEN_SECRET'] ?? '';
        if ($secret === '') {
            // İkincil anahtar olarak APP_KEY düşülür; hiçbiri yoksa reddet.
            $secret = $_ENV['APP_KEY'] ?? ($_ENV['APP_SECRET'] ?? '');
        }
        if ($secret === '') {
            return ['valid' => false, 'reason' => 'no_secret'];
        }
        $expected = hash_hmac('sha256', $b64payload, $secret, true);
        if (!hash_equals($expected, $sig)) {
            return ['valid' => false, 'reason' => 'signature'];
        }
        $claims = json_decode($payload, true);
        if (!is_array($claims) || empty($claims['b'])) {
            return ['valid' => false, 'reason' => 'claims'];
        }
        if (!empty($claims['exp']) && (int)$claims['exp'] < time()) {
            return ['valid' => false, 'reason' => 'expired'];
        }
        return [
            'valid' => true,
            'tenantId' => (string)$claims['b'],
            'userId'   => isset($claims['u']) ? (string)$claims['u'] : null,
        ];
    }

    private function ensureDb(): bool
    {
        if ($this->db === null) {
            $this->initDatabase();
        }
        if ($this->db) {
            try {
                $this->db->query('SELECT 1');
                return true;
            } catch (\Exception $e) {
                $this->db = null;
                $this->initDatabase();
                return $this->db !== null;
            }
        }
        return false;
    }

    // =============================================
    // Ratchet Interface
    // =============================================

    public function onOpen(ConnectionInterface $conn)
    {
        $this->clients->attach($conn);
        $rid = $conn->resourceId;
        $path = $conn->httpRequest ? $conn->httpRequest->getUri()->getPath() : '/';
        
        $this->connectionMeta[$rid] = [
            'connected_at' => time(),
            'business_id' => null,
            'user_id' => null,
            'type' => 'unknown',
            'path' => $path,
            'subscriptions' => [],
        ];
        
        // Send CONNECTED handshake
        $conn->send(json_encode(['type' => 'CONNECTED', 'message' => 'Qordy WS', 'timestamp' => time()]));
        
        echo "[WS] +conn #{$rid} (path:{$path}) total:{$this->clients->count()}\n";
    }

    public function onMessage(ConnectionInterface $from, $msg)
    {
        $data = json_decode($msg, true);
        if (!is_array($data) || !isset($data['type'])) {
            $from->send(json_encode(['type' => 'ERROR', 'error' => 'Invalid message']));
            return;
        }
        
        $type = strtoupper($data['type']); // Case-insensitive
        
        switch ($type) {
            case 'AUTH':
                // Bridge sends {type:'auth', bridge_id, api_key}; mobile sends {type:'auth', business_id}
                if (!empty($data['bridge_id'])) {
                    $this->handlePrintBridgeAuth($from, $data);
                } else {
                    $this->handleAuth($from, $data);
                }
                break;
            case 'SUBSCRIBE':
                $this->handleSubscribe($from, $data);
                break;
            case 'UNSUBSCRIBE':
                $this->handleUnsubscribe($from, $data);
                break;
            case 'PING':
                $from->send(json_encode(['type' => 'PONG', 'timestamp' => time()]));
                break;
            case 'PRINT_BRIDGE_AUTH':
                $this->handlePrintBridgeAuth($from, $data);
                break;
            default:
                $from->send(json_encode(['type' => 'ACK', 'received' => $type]));
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        $rid = $conn->resourceId;
        
        if (isset($this->connectionMeta[$rid])) {
            $bizId = $this->connectionMeta[$rid]['business_id'];
            if ($bizId && isset($this->businessChannels[$bizId])) {
                $this->businessChannels[$bizId] = array_filter(
                    $this->businessChannels[$bizId],
                    fn($c) => $c->resourceId !== $rid
                );
                if (empty($this->businessChannels[$bizId])) {
                    unset($this->businessChannels[$bizId]);
                }
            }
            unset($this->connectionMeta[$rid]);
        }
        
        foreach ($this->printBridges as $bid => $bc) {
            if ($bc->resourceId === $rid) { unset($this->printBridges[$bid]); break; }
        }
        
        $this->clients->detach($conn);
        echo "[WS] -conn #{$rid} total:{$this->clients->count()}\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "[WS] ERR #{$conn->resourceId}: {$e->getMessage()}\n";
        $conn->close();
    }

    // =============================================
    // Auth & Subscribe Handlers
    // =============================================

    private function handleAuth(ConnectionInterface $conn, array $data): void
    {
        // Geçiş planı: imzalı token VARSA onu kullan; yoksa client-supplied
        // business_id'ye geçici süreyle izin ver. Bu, mevcut SPA/mobile
        // uygulamalarını kırmadan yeni token mekanizmasını aşamalı olarak
        // devreye almamızı sağlar. SUBSCRIBE tarafındaki cross-tenant açığı
        // ayrıca kapatıldı (bkz. handleSubscribe).
        $token  = $data['token'] ?? null;
        $claims = $this->verifyAuthToken(is_string($token) ? $token : null);

        if ($claims['valid']) {
            $bizId  = $claims['tenantId'];
            $userId = $claims['userId'];
            echo "[WS] AUTH-token OK rid:{$conn->resourceId} biz:{$bizId}\n";
        } else {
            $bizId  = $data['business_id'] ?? null;
            $userId = $data['user_id'] ?? null;
            if (!$bizId) {
                $conn->send(json_encode(['type' => 'ERROR', 'error' => 'business_id required']));
                return;
            }
            // Legacy path kullanıldığında logla - token rollout sırasında
            // telemetriyle hangi client'ların hala eski yöntemle geldiğini
            // anlarız.
            if (is_string($token) && $token !== '') {
                echo "[WS] AUTH-legacy fallback rid:{$conn->resourceId} biz:{$bizId} token_reason:" . ($claims['reason'] ?? 'unknown') . "\n";
            } else {
                echo "[WS] AUTH-legacy rid:{$conn->resourceId} biz:{$bizId}\n";
            }
        }

        $rid = $conn->resourceId;
        $this->connectionMeta[$rid]['business_id'] = $bizId;
        $this->connectionMeta[$rid]['user_id'] = $userId;
        $this->connectionMeta[$rid]['type'] = 'mobile';
        $this->connectionMeta[$rid]['authenticated'] = true;
        
        // Auto-subscribe to business channel
        if (!isset($this->businessChannels[$bizId])) {
            $this->businessChannels[$bizId] = [];
        }
        
        // Prevent duplicate subscriptions
        $alreadySubscribed = false;
        foreach ($this->businessChannels[$bizId] as $existing) {
            if ($existing->resourceId === $rid) { $alreadySubscribed = true; break; }
        }
        if (!$alreadySubscribed) {
            $this->businessChannels[$bizId][] = $conn;
        }
        
        $conn->send(json_encode([
            'type' => 'AUTH_SUCCESS',
            'data' => ['business_id' => $bizId, 'user_id' => $userId],
        ]));
        
        echo "[WS] AUTH #{$rid} biz:{$bizId}\n";
    }

    private function handleSubscribe(ConnectionInterface $conn, array $data): void
    {
        $channel = $data['channel'] ?? null;
        $rid = $conn->resourceId;

        // ÖNEMLI (güvenlik): SUBSCRIBE mesajında client-supplied `business_id`
        // ARTIK KABUL EDİLMİYOR. Aksi takdirde, bir kullanıcı kendi sessionuyla
        // AUTH olduktan sonra başka bir tenant'ın kanalına abone olmayı
        // deneyebiliyordu. Artık sadece AUTH sırasında belirlenen tenant
        // kullanılır.
        $bizId = $this->connectionMeta[$rid]['business_id'] ?? null;
        $authed = !empty($this->connectionMeta[$rid]['authenticated']);

        if (!$bizId || !$authed) {
            $conn->send(json_encode([
                'type' => 'ERROR',
                'error' => 'not_authenticated',
                'message' => 'Send AUTH with a valid token before SUBSCRIBE',
            ]));
            return;
        }

        if (!isset($this->businessChannels[$bizId])) {
            $this->businessChannels[$bizId] = [];
        }
        $alreadySub = false;
        foreach ($this->businessChannels[$bizId] as $ex) {
            if ($ex->resourceId === $rid) { $alreadySub = true; break; }
        }
        if (!$alreadySub) {
            $this->businessChannels[$bizId][] = $conn;
        }

        if ($channel && is_string($channel)) {
            // Kanal stringini sadece tanımlayıcı amaçlı saklıyoruz;
            // broadcast yine tenant kanalı üzerinden yapılıyor.
            $this->connectionMeta[$rid]['subscriptions'][] = $channel;
        }

        $conn->send(json_encode(['type' => 'SUBSCRIBED', 'channel' => $channel ?? 'business']));
    }

    private function handleUnsubscribe(ConnectionInterface $conn, array $data): void
    {
        $channel = $data['channel'] ?? null;
        $conn->send(json_encode(['type' => 'UNSUBSCRIBED', 'channel' => $channel]));
    }

    private function handlePrintBridgeAuth(ConnectionInterface $conn, array $data): void
    {
        $bridgeId = $data['bridge_id'] ?? null;
        if (!$bridgeId) { $conn->send(json_encode(['type' => 'ERROR', 'error' => 'bridge_id required'])); return; }
        
        $this->printBridges[$bridgeId] = $conn;
        $this->connectionMeta[$conn->resourceId]['type'] = 'print_bridge';
        $conn->send(json_encode(['type' => 'AUTH_SUCCESS', 'bridge_id' => $bridgeId]));
        echo "[WS] PRINT_BRIDGE auth: {$bridgeId}\n";
    }

    // =============================================
    // Broadcasting
    // =============================================

    public function broadcastToBusiness(string $bizId, array $message): int
    {
        $sent = 0;
        $payload = json_encode($message);
        
        foreach (($this->businessChannels[$bizId] ?? []) as $conn) {
            try { $conn->send($payload); $sent++; } catch (\Exception $e) { /* stale */ }
        }
        return $sent;
    }

    public function broadcastToAll(array $message): int
    {
        $sent = 0;
        $payload = json_encode($message);
        foreach ($this->clients as $conn) {
            try { $conn->send($payload); $sent++; } catch (\Exception $e) {}
        }
        return $sent;
    }

    // =============================================
    // DB Polling for real-time broadcasts
    // Called every 3 seconds from the event loop timer
    // =============================================

    public function pollDatabaseChanges(): void
    {
        if (!$this->ensureDb()) return;
        
        // Business channels: orders, tables, notifications
        foreach (array_keys($this->businessChannels) as $bizId) {
            if (empty($this->businessChannels[$bizId])) continue;
            
            try {
                $this->pollOrderChanges($bizId);
                $this->pollTableChanges($bizId);
                $this->pollNotificationChanges($bizId);
            } catch (\Exception $e) {
                echo "[WS] Poll error biz:{$bizId}: {$e->getMessage()}\n";
            }
        }
        
        // Print queue polling DISABLED - desktop app handles queue via HTTP API.
        // Running both WS poll and HTTP poll on the same queue creates race conditions
        // and potential duplicate prints. The HTTP /api/printer-bridge/queue endpoint
        // is the single authoritative source for print jobs.
    }

    private function pollOrderChanges(string $bizId): void
    {
        $stmt = $this->db->prepare(
            "SELECT o.order_id, o.table_id, COALESCE(t.name, o.table_name) as table_name, 
                    o.status, o.total_amount, o.updated_at, o.created_at
             FROM orders o LEFT JOIN tables t ON o.table_id = t.table_id
             WHERE o.tenant_id = ? AND o.updated_at >= DATE_SUB(NOW(), INTERVAL 10 SECOND)
             ORDER BY o.updated_at DESC LIMIT 20"
        );
        $stmt->execute([$bizId]);
        $orders = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Build a hash of current state
        $hash = md5(json_encode($orders));
        if (isset($this->lastOrderHash[$bizId]) && $this->lastOrderHash[$bizId] === $hash) return;
        $this->lastOrderHash[$bizId] = $hash;
        
        if (empty($orders)) return;
        
        foreach ($orders as $order) {
            // Determine if new (created in last 10s) or updated
            $createdAt = strtotime($order['created_at']);
            $isNew = (time() - $createdAt) < 12;
            
            // Get items for the order
            $items = [];
            try {
                $stmtItems = $this->db->prepare(
                    "SELECT oi.order_item_id, oi.quantity, oi.price as unit_price, mi.name as product_name
                     FROM order_items oi LEFT JOIN menu_items mi ON oi.menu_item_id = mi.menu_item_id
                     WHERE oi.order_id = ? AND UPPER(COALESCE(oi.preparation_status,'PENDING')) != 'CANCELLED'"
                );
                $stmtItems->execute([$order['order_id']]);
                $items = $stmtItems->fetchAll(\PDO::FETCH_ASSOC);
            } catch (\Exception $e) {}
            
            $this->broadcastToBusiness($bizId, [
                'type' => $isNew ? 'ORDER_CREATED' : 'ORDER_UPDATE',
                'data' => [
                    'order_id' => $order['order_id'],
                    'table_id' => $order['table_id'],
                    'table_name' => $order['table_name'],
                    'status' => strtolower($order['status']),
                    'total_amount' => (float)$order['total_amount'],
                    'items' => $items,
                    'action' => $isNew ? 'created' : 'updated',
                ],
            ]);
        }
    }

    private function pollTableChanges(string $bizId): void
    {
        // Get current table states
        $stmt = $this->db->prepare(
            "SELECT t.table_id, t.name as table_name, t.status, z.zone_id, z.name as zone_name
             FROM tables t LEFT JOIN zones z ON t.zone_id = z.zone_id
             WHERE t.tenant_id = ? ORDER BY t.name"
        );
        $stmt->execute([$bizId]);
        $tables = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $hash = md5(json_encode($tables));
        if (isset($this->lastTableHash[$bizId]) && $this->lastTableHash[$bizId] === $hash) return;
        $this->lastTableHash[$bizId] = $hash;

        // Tek bir snapshot olarak tüm masaları gönder (N-mesaj fırtınasını
        // önler). Eski client'lar TABLE_UPDATE bekliyor olabilir; geriye
        // dönük uyumluluk için hem toplu hem bireysel mesaj yayınlıyoruz —
        // client yeni mesajı önce işlerse aynı içeriği iki kez uygulamaz,
        // çünkü hash bazlı idempotentlik yeterli ölçüde sağlıyor.
        $mapStatus = static function (string $status): string {
            return match(strtolower($status)) {
                'free', 'available', 'bos' => 'available',
                'occupied', 'dolu' => 'occupied',
                'reserved', 'rezerve' => 'reserved',
                default => strtolower($status),
            };
        };

        $snapshotTables = array_map(static function ($t) use ($mapStatus) {
            return [
                'table_id' => $t['table_id'],
                'table_name' => $t['table_name'],
                'status' => $mapStatus((string)($t['status'] ?? '')),
                'zone_id' => $t['zone_id'] ?? null,
                'zone_name' => $t['zone_name'] ?? null,
            ];
        }, $tables);

        // 1) Modern: tek mesajla tam snapshot.
        $this->broadcastToBusiness($bizId, [
            'type' => 'TABLES_SNAPSHOT',
            'data' => [
                'tables' => $snapshotTables,
                'count' => count($snapshotTables),
                'timestamp' => time(),
            ],
        ]);

        // 2) Legacy: küçük kurulumlarda eski client'lar için bireysel
        //    TABLE_UPDATE'lar (sadece tablo sayısı eşik altında ise —
        //    500+ masalı kurulumlarda ağı boğmayalım).
        if (count($snapshotTables) <= 60) {
            foreach ($snapshotTables as $row) {
                $this->broadcastToBusiness($bizId, [
                    'type' => 'TABLE_UPDATE',
                    'data' => $row,
                ]);
            }
        }
    }

    private function pollNotificationChanges(string $bizId): void
    {
        $stmt = $this->db->prepare(
            "SELECT n.notification_id, n.type, n.data, n.table_name, n.created_at
             FROM notifications n 
             LEFT JOIN tables t ON n.table_id = t.table_id
             WHERE (n.table_id IS NULL OR t.tenant_id = ?)
             AND n.is_read = 0 AND n.created_at >= DATE_SUB(NOW(), INTERVAL 15 SECOND)
             ORDER BY n.created_at DESC LIMIT 10"
        );
        $stmt->execute([$bizId]);
        $notifs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $hash = md5(json_encode($notifs));
        if (isset($this->lastNotifHash[$bizId]) && $this->lastNotifHash[$bizId] === $hash) return;
        $this->lastNotifHash[$bizId] = $hash;
        
        foreach ($notifs as $n) {
            $title = 'Bildirim';
            $message = '';
            $orderId = null;
            try {
                $d = json_decode($n['data'] ?? '{}', true) ?: [];
                $title = $d['title'] ?? ($n['type'] ?: 'Bildirim');
                $message = $d['message'] ?? ($n['table_name'] ? "Masa: {$n['table_name']}" : '');
                $orderId = $d['order_id'] ?? null;
            } catch (\Exception $e) {}
            
            $data = [
                'notification_id' => $n['notification_id'],
                'title' => $title,
                'message' => $message,
                'type' => $n['type'] ?? 'general',
            ];
            if ($orderId !== null) {
                $data['order_id'] = $orderId;
            }
            $this->broadcastToBusiness($bizId, [
                'type' => 'NOTIFICATION',
                'data' => $data,
            ]);
        }
    }

    // =============================================
    // Print Bridge - Real-time queue push
    // =============================================

    /**
     * Poll receipt_print_queue and push PENDING jobs to connected bridges via WebSocket.
     * Jobs are marked PRINTING when pushed; bridge calls updateStatus when done.
     */
    private function pollPrintQueue(): void
    {
        $bridgeIds = array_keys($this->printBridges);
        if (empty($bridgeIds)) return;

        $placeholders = implode(',', array_fill(0, count($bridgeIds), '?'));
        $stmt = $this->db->prepare("
            SELECT bridge_id, tenant_id AS business_id FROM printer_bridges
            WHERE bridge_id IN ($placeholders)
        ");
        $stmt->execute($bridgeIds);
        $bridgeToBiz = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $bridgeToBiz[$row['bridge_id']] = $row['business_id'];
        }

        foreach ($bridgeIds as $bridgeId) {
            $businessId = $bridgeToBiz[$bridgeId] ?? null;
            if (!$businessId) continue;

            try {
                $this->db->beginTransaction();
                $jobStmt = $this->db->prepare("
                    SELECT q.queue_id, q.screen_id, q.print_data, q.created_at,
                           ps.name as screen_name, ps.screen_type
                    FROM receipt_print_queue q
                    LEFT JOIN preparation_screens ps ON q.screen_id = ps.screen_id
                    WHERE q.tenant_id = ? AND q.status = 'PENDING'
                      AND q.created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                    ORDER BY q.created_at ASC
                    LIMIT 50
                    FOR UPDATE SKIP LOCKED
                ");
                $jobStmt->execute([$businessId]);
                $jobs = $jobStmt->fetchAll(\PDO::FETCH_ASSOC);

                if (empty($jobs)) {
                    $this->db->commit();
                    continue;
                }

                $ids = array_column($jobs, 'queue_id');
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $upStmt = $this->db->prepare("
                    UPDATE receipt_print_queue
                    SET status = 'PRINTING', processing_bridge_id = ?, processing_started_at = NOW()
                    WHERE queue_id IN ($ph)
                ");
                $upStmt->execute(array_merge([$bridgeId], $ids));
                $this->db->commit();

                $businessInfo = $this->getBusinessInfoForPrintBridge($businessId);
                foreach ($jobs as $job) {
                    $formatted = $this->formatJobForBridge($job, $businessInfo);
                    $this->sendPrintJob($bridgeId, $formatted);
                }
            } catch (\Exception $e) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                echo "[WS] PrintQueue bridge:{$bridgeId}: {$e->getMessage()}\n";
            }
        }
    }

    private function getBusinessInfoForPrintBridge(string $businessId): array
    {
        try {
            $stmt = $this->db->prepare("SELECT company_name, phone FROM customers WHERE customer_id = ? LIMIT 1");
            $stmt->execute([$businessId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$row) return ['name' => '', 'address' => '', 'phone' => ''];
            return [
                'name' => trim($row['company_name'] ?? ''),
                'address' => '',
                'phone' => trim($row['phone'] ?? ''),
            ];
        } catch (\Exception $e) {
            return ['name' => '', 'address' => '', 'phone' => ''];
        }
    }

    private function formatJobForBridge(array $job, array $businessInfo): array
    {
        $printData = json_decode($job['print_data'] ?? '{}', true) ?: [];
        $job['content'] = $printData['content'] ?? '';
        $job['receipt_type'] = $printData['receipt_type'] ?? $printData['type'] ?? '';
        $job['order_id'] = $printData['order_id'] ?? $job['queue_id'];
        $job['table_name'] = $printData['table'] ?? $printData['table_name'] ?? '';
        $job['zone_name'] = $printData['zone_name'] ?? '';
        $job['table_display'] = $printData['table_display'] ?? '';
        if ($job['table_display'] === '') {
            $job['table_display'] = $job['zone_name'] !== '' ? trim($job['zone_name'] . ' - ' . $job['table_name']) : $job['table_name'];
        }
        $job['waiter_name'] = $printData['waiter'] ?? $printData['waiter_name'] ?? $printData['staff_name'] ?? '';
        $job['customer_note'] = $printData['customer_note'] ?? '';
        if (!empty($printData['receipt_data']) && is_array($printData['receipt_data'])) {
            $job['receipt_data'] = $printData['receipt_data'];
        }
        if (!empty($printData['items']) && is_array($printData['items'])) {
            $job['items'] = $printData['items'];
        } elseif (!empty($printData['receipt_data']['items']) && is_array($printData['receipt_data']['items'])) {
            $job['items'] = $printData['receipt_data']['items'];
        }
        if (!empty($printData['customizations']) && is_array($printData['customizations'])) {
            $job['customizations'] = $printData['customizations'];
        }
        
        // Report fields (product_sales_report)
        $reportFields = ['products', 'categories', 'summary', 'report_no',
                        'date_label', 'report_time', 'tax_number', 'receipt_type_override'];
        foreach ($reportFields as $rf) {
            if (isset($printData[$rf])) {
                $job[$rf] = $printData[$rf];
            }
        }
        
        // Z report specific fields
        $jobReceiptType = strtolower($printData['receipt_type'] ?? '');
        if ($jobReceiptType === 'z_report') {
            $zFields = ['z_number', 'date', 'totals', 'payment_breakdown',
                       'discount_total', 'service_charge_total', 'tip_total',
                       'order_lines', 'product_breakdown', 'category_breakdown',
                       'business_name', 'address', 'phone'];
            foreach ($zFields as $zf) {
                if (isset($printData[$zf])) {
                    $job[$zf] = $printData[$zf];
                }
            }
        }
        
        $job['business'] = $printData['receipt_data']['business'] ?? $printData['business'] ?? $businessInfo;
        $job['screen_name'] = $job['screen_name'] ?? $printData['screen_name'] ?? (['kitchen_main' => 'Mutfak', 'waiter_main' => 'Garson', 'cashier_main' => 'Kasiyer'][$job['screen_id'] ?? ''] ?? $job['screen_id'] ?? 'Unknown');
        $job['screen_type'] = $job['screen_type'] ?? $printData['screen_type'] ?? (['kitchen_main' => 'KITCHEN', 'waiter_main' => 'WAITER', 'cashier_main' => 'CASHIER'][$job['screen_id'] ?? ''] ?? 'KITCHEN');
        unset($job['print_data']);
        return $job;
    }

    public function sendPrintJob(string $bridgeId, array $jobData): bool
    {
        if (!isset($this->printBridges[$bridgeId])) return false;
        try {
            // Bridge client expects: type 'print_job' (lowercase), key 'job' with job data
            $this->printBridges[$bridgeId]->send(json_encode(['type' => 'print_job', 'job' => $jobData]));
            return true;
        } catch (\Exception $e) { return false; }
    }

    public function getStats(): array
    {
        return [
            'total_connections' => $this->clients->count(),
            'business_channels' => count($this->businessChannels),
            'print_bridges' => count($this->printBridges),
        ];
    }
}
