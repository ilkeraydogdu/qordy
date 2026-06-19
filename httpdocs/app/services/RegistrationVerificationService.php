<?php
namespace App\Services;

/**
 * Registration Verification Service
 * Handles email and phone (WhatsApp) verification during registration
 * Stores codes in session
 */
class RegistrationVerificationService
{
    private const CODE_EXPIRY_SECONDS = 600; // 10 minutes
    private const SESSION_PREFIX = 'reg_verify_';

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
    }

    private function getEmailCodeKey(string $email): string
    {
        return self::SESSION_PREFIX . 'email_' . md5(strtolower(trim($email)));
    }

    private function getPhoneCodeKey(string $phone, string $countryCode): string
    {
        $full = preg_replace('/\D/', '', $countryCode) . preg_replace('/\D/', '', $phone);
        return self::SESSION_PREFIX . 'phone_' . md5($full);
    }

    /**
     * Generate and send email verification code
     */
    public function sendEmailCode(string $email): array
    {
        $email = trim(strtolower($email));
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Geçerli e-posta gerekli'];
        }

        $customerRepo = \App\Core\DependencyFactory::getCustomerRepository();
        if ($customerRepo->emailExists($email)) {
            return ['success' => false, 'error' => 'Bu e-posta adresi zaten kullanılıyor'];
        }

        $code = (string) random_int(100000, 999999);
        $key = $this->getEmailCodeKey($email);
        $_SESSION[$key] = [
            'code' => $code,
            'expires' => time() + self::CODE_EXPIRY_SECONDS
        ];

        $emailService = \App\Core\DependencyFactory::getEmailService();
        $appName = 'Qordy';
        if (function_exists('getAppConfig')) {
            try {
                $cfg = getAppConfig();
                $appName = $cfg ? $cfg->getAppName() : 'Qordy';
            } catch (\Exception $e) {}
        }

        $subject = $appName . ' - E-posta Doğrulama Kodu';
        $body = "
        <!DOCTYPE html>
        <html><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'>
        <link rel='preconnect' href='https://fonts.googleapis.com'><link rel='preconnect' href='https://fonts.gstatic.com' crossorigin>
        <link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700&display=swap' rel='stylesheet'>
        </head>
        <body style='margin:0;padding:0;font-family:Inter,-apple-system,sans-serif;line-height:1.6;color:#334155;background:#f8fafc;'>
        <div style='max-width:480px;margin:0 auto;padding:32px 24px;'>
        <div style='background:linear-gradient(135deg,#6366f1 0%,#8b5cf6 100%);border-radius:16px;padding:28px 24px;margin-bottom:24px;text-align:center;'>
        <h1 style='font-family:Plus Jakarta Sans,sans-serif;font-size:1.5rem;font-weight:700;color:#fff;margin:0 0 8px 0;'>E-posta Doğrulama</h1>
        <p style='font-size:0.875rem;color:rgba(255,255,255,0.9);margin:0;'>Kayıt işleminizi tamamlamak için kodunuz</p>
        </div>
        <div style='background:#fff;border-radius:12px;padding:24px;border:1px solid #e2e8f0;box-shadow:0 1px 3px rgba(0,0,0,0.05);'>
        <div style='background:#f1f5f9;border-radius:10px;padding:20px;text-align:center;margin:0 0 20px 0;'>
        <span style='font-size:28px;font-weight:700;color:#6366f1;letter-spacing:6px;font-family:ui-monospace,monospace;'>{$code}</span>
        </div>
        <p style='font-size:0.875rem;color:#64748b;margin:0 0 12px 0;'>Bu kod 10 dakika geçerlidir.</p>
        <p style='font-size:0.75rem;color:#ef4444;margin:0;'>Bu kodu kimseyle paylaşmayın.</p>
        </div>
        <p style='font-size:0.8125rem;color:#94a3b8;margin-top:24px;'>İyi çalışmalar,<br><strong>{$appName}</strong> Ekibi</p>
        </div></body></html>";

        $sent = $emailService->sendEmail($email, $subject, $body);
        if (!$sent) {
            unset($_SESSION[$key]);
            return ['success' => false, 'error' => 'E-posta gönderilemedi. Lütfen tekrar deneyin.'];
        }

        return ['success' => true, 'message' => 'Doğrulama kodu e-posta adresinize gönderildi'];
    }

    /**
     * Verify email code
     */
    public function verifyEmail(string $email, string $code): array
    {
        $email = trim(strtolower($email));
        $code = trim($code);
        if (empty($email) || empty($code) || strlen($code) < 4) {
            return ['success' => false, 'error' => 'E-posta ve kod gerekli'];
        }

        $key = $this->getEmailCodeKey($email);
        $stored = $_SESSION[$key] ?? null;
        if (!$stored) {
            return ['success' => false, 'error' => 'Kod süresi dolmuş veya geçersiz. Lütfen tekrar kod gönderin.'];
        }
        if ($stored['expires'] < time()) {
            unset($_SESSION[$key]);
            return ['success' => false, 'error' => 'Kod süresi doldu. Lütfen tekrar kod gönderin.'];
        }
        if (!hash_equals($stored['code'], $code)) {
            return ['success' => false, 'error' => 'Geçersiz doğrulama kodu'];
        }

        unset($_SESSION[$key]);
        $_SESSION[self::SESSION_PREFIX . 'email_verified'] = $email;
        return ['success' => true, 'verified' => true];
    }

    /**
     * Generate and send phone verification code via WhatsApp
     */
    public function sendPhoneCode(string $phone, string $countryCode = '+90'): array
    {
        $phone = preg_replace('/\D/', '', $phone);
        $cc = preg_replace('/\D/', '', $countryCode) ?: '90';
        if (empty($phone) || strlen($phone) < 8) {
            return ['success' => false, 'error' => 'Geçerli telefon numarası girin'];
        }
        if ($cc === '90' && $phone[0] !== '5') {
            return ['success' => false, 'error' => 'Türkiye numarası 5 ile başlamalıdır (örn: 5321234567)'];
        }

        $cc = preg_replace('/\D/', '', $countryCode);
        if (empty($cc)) {
            $cc = '90';
        }
        $fullNumber = $cc . $phone;

        $code = (string) random_int(100000, 999999);
        $key = $this->getPhoneCodeKey($phone, $countryCode);
        $_SESSION[$key] = [
            'code' => $code,
            'expires' => time() + self::CODE_EXPIRY_SECONDS,
            'full_phone' => $fullNumber
        ];

        $whatsApp = \App\Core\DependencyFactory::getWhatsAppService();
        $result = $whatsApp->sendVerificationCode($fullNumber, $code);

        if (!$result['success']) {
            unset($_SESSION[$key]);
            return ['success' => false, 'error' => $result['message']];
        }

        return ['success' => true, 'message' => 'Doğrulama kodu WhatsApp ile gönderildi'];
    }

    /**
     * Verify phone code
     */
    public function verifyPhone(string $phone, string $countryCode, string $code): array
    {
        $phone = preg_replace('/\D/', '', $phone);
        $code = trim($code);
        if (empty($phone) || empty($code) || strlen($code) < 4) {
            return ['success' => false, 'error' => 'Telefon ve kod gerekli'];
        }

        $key = $this->getPhoneCodeKey($phone, $countryCode);
        $stored = $_SESSION[$key] ?? null;
        if (!$stored) {
            return ['success' => false, 'error' => 'Kod süresi dolmuş veya geçersiz. Lütfen tekrar kod gönderin.'];
        }
        if ($stored['expires'] < time()) {
            unset($_SESSION[$key]);
            return ['success' => false, 'error' => 'Kod süresi doldu. Lütfen tekrar kod gönderin.'];
        }
        if (!hash_equals($stored['code'], $code)) {
            return ['success' => false, 'error' => 'Geçersiz doğrulama kodu'];
        }

        $cc = preg_replace('/\D/', '', $countryCode) ?: '90';
        $fullPhone = $cc . $phone;
        unset($_SESSION[$key]);
        $_SESSION[self::SESSION_PREFIX . 'phone_verified'] = $fullPhone;
        return ['success' => true, 'verified' => true];
    }

    /**
     * Check if email was verified in this session
     */
    public function isEmailVerified(string $email): bool
    {
        return isset($_SESSION[self::SESSION_PREFIX . 'email_verified']) &&
            $_SESSION[self::SESSION_PREFIX . 'email_verified'] === trim(strtolower($email));
    }

    /**
     * Check if phone was verified in this session
     */
    public function isPhoneVerified(): bool
    {
        return isset($_SESSION[self::SESSION_PREFIX . 'phone_verified']);
    }

    /**
     * Get verified phone (with country code)
     */
    public function getVerifiedPhone(): ?string
    {
        return $_SESSION[self::SESSION_PREFIX . 'phone_verified'] ?? null;
    }

    /**
     * Clear verification state after successful registration
     */
    public function clearVerificationState(): void
    {
        foreach (array_keys($_SESSION) as $k) {
            if (strpos($k, self::SESSION_PREFIX) === 0) {
                unset($_SESSION[$k]);
            }
        }
    }
}
