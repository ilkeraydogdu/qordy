<?php
namespace App\Services;

use App\Config\Database;
use PDO;

/**
 * Link Shortener Service — pfdk.me REST API entegrasyonu.
 *
 * Uzun URL'leri kısaltır ve local `short_links` tablosunda saklar. Aynı
 * müşteri + kampanya için daha önce üretilmiş bir link varsa tekrar API
 * çağrısı yapmadan cache'ten döner. Tıklama istatistikleri
 * `pfdk.me/api/v1/urls/:id` üzerinden periyodik olarak senkronlanır.
 *
 * Kullanım:
 *   $svc = new \App\Services\LinkShortenerService();
 *   $short = $svc->shortenForCustomer(
 *       $customerId,
 *       'https://pofudukcafe.qordy.com/customer/packages/list',
 *       [
 *           'campaign' => 'trial_grace_day_3',
 *           'channel'  => 'email',
 *           'title'    => 'Pofuduk Cafe — Paket Seç',
 *           'purpose'  => 'musteri_ozel',
 *       ]
 *   );
 *   // $short => ['short_url' => 'https://pfdk.me/abc123', 'pfdk_id' => 'moa54v...', ...]
 *
 * Kısaltma başarısız olursa **her zaman uzun URL'ye fallback** yapar;
 * mail gönderimleri asla bu yüzden kırılmaz.
 */
class LinkShortenerService
{
    private PDO $pdo;
    private string $apiUrl;
    private string $apiKey;
    private string $shortDomain;

    public function __construct(?PDO $pdo = null)
    {
        if ($pdo === null) {
            $pdo = (new Database())->getConnection();
        }
        $this->pdo = $pdo;
        $this->apiUrl = rtrim(defined('PFDK_API_URL') ? PFDK_API_URL : 'https://pfdk.me/api/v1', '/');
        $this->apiKey = defined('PFDK_API_KEY') ? PFDK_API_KEY : '';
        $this->shortDomain = rtrim(defined('PFDK_SHORT_DOMAIN') ? PFDK_SHORT_DOMAIN : 'https://pfdk.me', '/');
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && $this->apiUrl !== '';
    }

    /**
     * Bir müşteri için (ve opsiyonel kampanya için) kısa URL döndür.
     * Cache hit durumunda yeniden API çağrısı yapmaz.
     *
     * @param string|null $customerId
     * @param string $longUrl
     * @param array{campaign?:string,channel?:string,title?:string,purpose?:string,force_new?:bool} $opts
     * @return array{short_url:string,long_url:string,short_code:?string,pfdk_id:?string,cached:bool,fallback:bool}
     */
    public function shortenForCustomer(?string $customerId, string $longUrl, array $opts = []): array
    {
        $longUrl = trim($longUrl);
        if ($longUrl === '') {
            return [
                'short_url' => '',
                'long_url' => '',
                'short_code' => null,
                'pfdk_id' => null,
                'cached' => false,
                'fallback' => true,
            ];
        }

        $campaign = isset($opts['campaign']) ? substr((string)$opts['campaign'], 0, 128) : null;
        $channel  = isset($opts['channel'])  ? substr((string)$opts['channel'], 0, 16)  : null;
        $title    = isset($opts['title'])    ? substr((string)$opts['title'], 0, 250)   : null;
        $purpose  = isset($opts['purpose'])  ? (string)$opts['purpose'] : 'musteri_ozel';
        $forceNew = !empty($opts['force_new']);

        // 1) Cache lookup — aynı müşteri+kampanya+long_url varsa dön.
        if (!$forceNew) {
            $cached = $this->findCached($customerId, $longUrl, $campaign);
            if ($cached) {
                return [
                    'short_url'  => $cached['short_url'],
                    'long_url'   => $cached['long_url'],
                    'short_code' => $cached['short_code'],
                    'pfdk_id'    => $cached['pfdk_id'],
                    'cached'     => true,
                    'fallback'   => false,
                ];
            }
        }

        if (!$this->isConfigured()) {
            return $this->fallbackResult($longUrl);
        }

        // 2) API çağrısı
        try {
            $resp = $this->apiRequest('POST', '/urls', [
                'originalUrl' => $longUrl,
                'title'       => $title,
                'campaign'    => $campaign,
                'purpose'     => $purpose,
            ]);

            if (empty($resp['success']) || empty($resp['data']['shortUrl'])) {
                $this->log('shorten failed — empty response', ['resp' => $resp, 'longUrl' => $longUrl]);
                return $this->fallbackResult($longUrl);
            }

            $data = $resp['data'];
            $row = [
                'customer_id' => $customerId,
                'pfdk_id'     => (string)$data['id'],
                'short_code'  => (string)$data['shortCode'],
                'short_url'   => (string)$data['shortUrl'],
                'long_url'    => $longUrl,
                'purpose'     => $purpose,
                'campaign'    => $campaign,
                'title'       => $title,
                'channel'     => $channel,
            ];

            $this->persist($row);

            return [
                'short_url'  => $row['short_url'],
                'long_url'   => $row['long_url'],
                'short_code' => $row['short_code'],
                'pfdk_id'    => $row['pfdk_id'],
                'cached'     => false,
                'fallback'   => false,
            ];
        } catch (\Throwable $e) {
            $this->log('shorten exception', ['err' => $e->getMessage(), 'longUrl' => $longUrl]);
            return $this->fallbackResult($longUrl);
        }
    }

    /**
     * Sadece kısa URL döndür (geriye dönük kolay kullanım).
     */
    public function shorten(?string $customerId, string $longUrl, array $opts = []): string
    {
        $r = $this->shortenForCustomer($customerId, $longUrl, $opts);
        return $r['short_url'] ?: $longUrl;
    }

    /**
     * Tek bir linkin tıklama bilgisini pfdk.me'den çek ve DB'ye yaz.
     */
    public function syncStats(string $pfdkId): ?array
    {
        if (!$this->isConfigured()) return null;
        try {
            $resp = $this->apiRequest('GET', '/urls/' . rawurlencode($pfdkId));
            if (empty($resp['success']) || empty($resp['data'])) return null;
            $d = $resp['data'];
            $stmt = $this->pdo->prepare(
                'UPDATE short_links SET click_count = :c, last_synced_at = NOW() WHERE pfdk_id = :id LIMIT 1'
            );
            $stmt->execute([':c' => (int)($d['clicks'] ?? 0), ':id' => $pfdkId]);
            return [
                'pfdk_id'    => $pfdkId,
                'clicks'     => (int)($d['clicks'] ?? 0),
                'isActive'   => (bool)($d['isActive'] ?? true),
                'expiresAt'  => $d['expiresAt'] ?? null,
            ];
        } catch (\Throwable $e) {
            $this->log('syncStats exception', ['err' => $e->getMessage(), 'pfdk_id' => $pfdkId]);
            return null;
        }
    }

    /**
     * `limit` kadar en eski/son senkronlanmamış linki güncelle.
     * Cron'dan çağrılabilir.
     */
    public function syncStatsBatch(int $limit = 100): int
    {
        if (!$this->isConfigured()) return 0;
        $stmt = $this->pdo->prepare(
            'SELECT pfdk_id FROM short_links
             WHERE pfdk_id IS NOT NULL AND pfdk_id <> ""
             ORDER BY last_synced_at IS NULL DESC, last_synced_at ASC
             LIMIT :lim'
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $done = 0;
        foreach ($ids as $id) {
            if ($this->syncStats((string)$id) !== null) {
                $done++;
            }
        }
        return $done;
    }

    /**
     * Detaylı analitik (IP, ülke vs.) — süper admin paneli için.
     */
    public function analyticsFor(string $pfdkId, int $limit = 100, int $offset = 0): ?array
    {
        if (!$this->isConfigured()) return null;
        try {
            $resp = $this->apiRequest('GET', '/urls/' . rawurlencode($pfdkId) . '/analytics', [
                'limit' => $limit, 'offset' => $offset,
            ], true);
            if (empty($resp['success'])) return null;
            return $resp;
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ---------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------

    private function findCached(?string $customerId, string $longUrl, ?string $campaign): ?array
    {
        $sql = 'SELECT pfdk_id, short_code, short_url, long_url FROM short_links
                WHERE long_url = :lu
                  AND ' . ($customerId === null ? 'customer_id IS NULL' : 'customer_id = :cid') . '
                  AND ' . ($campaign === null ? 'campaign IS NULL' : 'campaign = :camp') . '
                ORDER BY id DESC LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $params = [':lu' => $longUrl];
        if ($customerId !== null) $params[':cid'] = $customerId;
        if ($campaign !== null)   $params[':camp'] = $campaign;
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function persist(array $row): void
    {
        $sql = 'INSERT INTO short_links
            (customer_id, pfdk_id, short_code, short_url, long_url, purpose, campaign, title, channel)
            VALUES (:customer_id, :pfdk_id, :short_code, :short_url, :long_url, :purpose, :campaign, :title, :channel)
            ON DUPLICATE KEY UPDATE short_url = VALUES(short_url), long_url = VALUES(long_url),
                purpose = VALUES(purpose), campaign = VALUES(campaign),
                title = VALUES(title), channel = VALUES(channel)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':customer_id' => $row['customer_id'],
            ':pfdk_id'     => $row['pfdk_id'],
            ':short_code'  => $row['short_code'],
            ':short_url'   => $row['short_url'],
            ':long_url'    => $row['long_url'],
            ':purpose'     => $row['purpose'],
            ':campaign'    => $row['campaign'],
            ':title'       => $row['title'],
            ':channel'     => $row['channel'],
        ]);
    }

    private function fallbackResult(string $longUrl): array
    {
        return [
            'short_url'  => $longUrl,
            'long_url'   => $longUrl,
            'short_code' => null,
            'pfdk_id'    => null,
            'cached'     => false,
            'fallback'   => true,
        ];
    }

    private function apiRequest(string $method, string $path, array $payload = [], bool $isQuery = false): array
    {
        $url = $this->apiUrl . $path;
        $ch = curl_init();
        $headers = [
            'X-API-Key: ' . $this->apiKey,
            'Accept: application/json',
        ];

        if ($method === 'GET' || $isQuery) {
            if (!empty($payload)) {
                $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($payload);
            }
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        } else {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $headers[] = 'Content-Type: application/json';
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        // pfdk.me edge drops empty User-Agent; bir UA göndermek şart.
        curl_setopt($ch, CURLOPT_USERAGENT, 'QordyLinkShortener/1.0 (+https://qordy.com)');

        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new \App\Exceptions\ExternalServiceException('pfdk.me curl error: ' . $err, ['service' => 'pfdk.me', 'error' => $err], 503);
        }
        $json = json_decode((string)$body, true);
        if (!is_array($json)) {
            throw new \App\Exceptions\ExternalServiceException('pfdk.me invalid json (HTTP ' . $code . ')', ['service' => 'pfdk.me', 'code' => $code, 'body' => substr((string)$body, 0, 200)]);
        }
        if ($code >= 400) {
            throw new \App\Exceptions\ExternalServiceException('pfdk.me HTTP ' . $code, ['service' => 'pfdk.me', 'code' => $code, 'error' => $json['error'] ?? 'unknown'], 502);
        }
        return $json;
    }

    private function log(string $msg, array $ctx = []): void
    {
        if (class_exists('\\App\\Core\\Logger')) {
            \App\Core\Logger::warning('[LinkShortener] ' . $msg, $ctx);
            return;
        }
        error_log('[LinkShortener] ' . $msg . ' ' . json_encode($ctx));
    }
}
