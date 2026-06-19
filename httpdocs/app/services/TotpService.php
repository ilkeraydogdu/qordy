<?php
namespace App\Services;

/**
 * RFC 6238 Time-based One-Time Password (TOTP) helper.
 *
 * Zero external dependencies — pure PHP implementation compatible with
 * Google Authenticator, Authy, Microsoft Authenticator and any other
 * standard TOTP client.
 *
 * Usage:
 *   - `generateSecret(32)` when enrolling a user; persist the base32
 *     string in `user_2fa.secret_code` with `method='totp'`.
 *   - `generateCode($secret)` to verify what the user should currently
 *     see (useful for tests / debugging only — the client produces the
 *     code, we only verify).
 *   - `verifyCode($secret, $code, $window = 1)` to accept a user-supplied
 *     6-digit code. `$window` is the number of ±30s steps we tolerate;
 *     `1` (±30s) is what every mainstream service uses.
 *   - `otpauthUri($secret, $label, $issuer)` produces the
 *     `otpauth://totp/...` URI that the client app scans as a QR code.
 *
 * This class is deliberately stateless; the persistence layer
 * (User2FARepository, mobile_2fa_challenges table) lives elsewhere.
 */
final class TotpService
{
    private const DEFAULT_PERIOD = 30;
    private const DEFAULT_DIGITS = 6;
    private const DEFAULT_ALGO = 'sha1';

    /** Base32 alphabet (RFC 4648, no padding). */
    private const B32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Generate a cryptographically strong base32-encoded secret.
     *
     * 32 characters of base32 == 160 bits of entropy, exceeding the 128
     * bits recommended by RFC 4226 / Google Authenticator spec.
     */
    public static function generateSecret(int $length = 32): string
    {
        $out = '';
        $bytes = random_bytes($length);
        for ($i = 0; $i < $length; $i++) {
            $out .= self::B32_ALPHABET[\ord($bytes[$i]) & 0x1F];
        }
        return $out;
    }

    /**
     * Verify a 6-digit TOTP code against [$secret].
     *
     * $window allows ±N 30-second steps to compensate for device clock
     * drift. With $window = 1 we accept the previous, current and next
     * codes — the Google Authenticator default.
     */
    public static function verifyCode(
        string $secret,
        string $code,
        int $window = 1,
        ?int $atTimestamp = null
    ): bool {
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== self::DEFAULT_DIGITS) {
            return false;
        }
        $ts = $atTimestamp ?? time();
        $counter = (int) floor($ts / self::DEFAULT_PERIOD);
        for ($offset = -$window; $offset <= $window; $offset++) {
            $candidate = self::hotp($secret, $counter + $offset);
            // Constant-time comparison — hash_equals handles the length
            // check first, so unequal-length inputs return false safely.
            if (hash_equals($candidate, $code)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Generate the code that should be valid right now. Useful for
     * tests. Real verification should use [verifyCode].
     */
    public static function generateCode(string $secret, ?int $atTimestamp = null): string
    {
        $ts = $atTimestamp ?? time();
        $counter = (int) floor($ts / self::DEFAULT_PERIOD);
        return self::hotp($secret, $counter);
    }

    /**
     * Build the `otpauth://totp/...` URI expected by authenticator apps.
     *
     * The label format per Google spec is `Issuer:Account`; issuer is
     * also included as a query param for clients that ignore the label.
     */
    public static function otpauthUri(
        string $secret,
        string $account,
        string $issuer
    ): string {
        $label = rawurlencode($issuer . ':' . $account);
        $params = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => strtoupper(self::DEFAULT_ALGO),
            'digits' => self::DEFAULT_DIGITS,
            'period' => self::DEFAULT_PERIOD,
        ]);
        return "otpauth://totp/{$label}?{$params}";
    }

    /**
     * HOTP (RFC 4226) — the algorithmic core of TOTP.
     *
     * @param string $secret Base32-encoded shared secret.
     * @param int    $counter 64-bit moving factor.
     */
    private static function hotp(string $secret, int $counter): string
    {
        $key = self::base32Decode($secret);
        if ($key === '') {
            return str_repeat('0', self::DEFAULT_DIGITS);
        }
        // Pack counter as 64-bit big-endian.
        $binCounter = pack('N*', 0, $counter);
        $hash = hash_hmac(self::DEFAULT_ALGO, $binCounter, $key, true);
        $offset = \ord(substr($hash, -1)) & 0x0F;
        $truncated = (\ord($hash[$offset]) & 0x7F) << 24
                   | (\ord($hash[$offset + 1]) & 0xFF) << 16
                   | (\ord($hash[$offset + 2]) & 0xFF) << 8
                   | (\ord($hash[$offset + 3]) & 0xFF);
        $modulus = 10 ** self::DEFAULT_DIGITS;
        return str_pad((string)($truncated % $modulus), self::DEFAULT_DIGITS, '0', STR_PAD_LEFT);
    }

    private static function base32Decode(string $b32): string
    {
        $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32));
        if ($b32 === '') return '';
        $bits = '';
        $len = strlen($b32);
        for ($i = 0; $i < $len; $i++) {
            $val = strpos(self::B32_ALPHABET, $b32[$i]);
            if ($val === false) continue;
            $bits .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
        }
        $bytes = '';
        $chunks = str_split($bits, 8);
        foreach ($chunks as $chunk) {
            if (strlen($chunk) === 8) {
                $bytes .= chr(bindec($chunk));
            }
        }
        return $bytes;
    }
}
