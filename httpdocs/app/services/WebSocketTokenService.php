<?php
namespace App\Services;

/**
 * WebSocketTokenService
 *
 * Kısa ömürlü, HMAC-SHA256 imzalı WebSocket AUTH token üretir ve doğrular.
 * WebSocket sunucusu (app/websocket/WebSocketHandler::verifyAuthToken) ile
 * birebir uyumlu format kullanır:
 *
 *     v1.<base64url(payload_json)>.<base64url(signature_bytes)>
 *
 * payload_json:
 *   {
 *     "b": "<tenant_id>",          // tenant_id (zorunlu)
 *     "u": "<user_id>",            // opsiyonel: oturumun sahibi
 *     "iat": 1713880000,           // issued_at
 *     "exp": 1713880900            // expires_at (iat + TTL)
 *   }
 *
 * Imza: HMAC-SHA256( base64url(payload_json), secret )
 *
 * Secret önceliği:
 *   1) $_ENV['WEBSOCKET_TOKEN_SECRET']
 *   2) $_ENV['APP_KEY']
 *   3) $_ENV['APP_SECRET']
 *   4) fallback: boş — bu durumda mint() null döner ve çağıran tarafın
 *      "legacy auth" yoluna düşmesini sağlarız. Production'da .env'e
 *      WEBSOCKET_TOKEN_SECRET eklenmesi GEREKİR.
 */
final class WebSocketTokenService
{
    private const DEFAULT_TTL = 900;
    private const VERSION = 'v1';

    public static function mint(string $tenantId, ?string $userId = null, int $ttlSeconds = self::DEFAULT_TTL): ?string
    {
        if ($tenantId === '') {
            return null;
        }
        $secret = self::resolveSecret();
        if ($secret === '') {
            return null;
        }

        $now = time();
        $claims = [
            'b'   => $tenantId,
            'iat' => $now,
            'exp' => $now + max(60, $ttlSeconds),
        ];
        if ($userId !== null && $userId !== '') {
            $claims['u'] = $userId;
        }

        $payload = json_encode($claims, JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            return null;
        }

        $b64payload = self::b64url($payload);
        $sig = hash_hmac('sha256', $b64payload, $secret, true);
        $b64sig = self::b64url($sig);

        return self::VERSION . '.' . $b64payload . '.' . $b64sig;
    }

    /**
     * Token'i doğrular ve claims dizisini döner. Geçersizse null.
     */
    public static function verify(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3 || $parts[0] !== self::VERSION) {
            return null;
        }
        [, $b64payload, $b64sig] = $parts;

        $payload = self::b64urlDecode($b64payload);
        $sig = self::b64urlDecode($b64sig);
        if ($payload === null || $sig === null) {
            return null;
        }

        $secret = self::resolveSecret();
        if ($secret === '') {
            return null;
        }

        $expected = hash_hmac('sha256', $b64payload, $secret, true);
        if (!hash_equals($expected, $sig)) {
            return null;
        }

        $claims = json_decode($payload, true);
        if (!is_array($claims) || empty($claims['b'])) {
            return null;
        }
        if (!empty($claims['exp']) && (int)$claims['exp'] < time()) {
            return null;
        }
        return $claims;
    }

    private static function resolveSecret(): string
    {
        $candidates = [
            $_ENV['WEBSOCKET_TOKEN_SECRET'] ?? null,
            $_ENV['APP_KEY'] ?? null,
            $_ENV['APP_SECRET'] ?? null,
        ];
        foreach ($candidates as $c) {
            if (is_string($c) && $c !== '') {
                return $c;
            }
        }
        return '';
    }

    private static function b64url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function b64urlDecode(string $s): ?string
    {
        $pad = strlen($s) % 4;
        if ($pad) { $s .= str_repeat('=', 4 - $pad); }
        $d = base64_decode(strtr($s, '-_', '+/'), true);
        return $d === false ? null : $d;
    }
}
