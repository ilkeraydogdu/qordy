<?php
namespace App\Services;

/**
 * Mobile Registration Verification - Cache-based (stateless)
 * E-posta ve telefon doğrulama kodlarını cache'de saklar (session yerine)
 * Mobil API stateless olduğu için session kullanılamaz
 */
class MobileRegistrationVerificationService
{
    private const CODE_EXPIRY_SECONDS = 600; // 10 dakika
    private const CACHE_PREFIX = 'mobile_reg_verify_';

    private $cache;

    public function __construct()
    {
        require_once __DIR__ . '/../core/DependencyFactory.php';
        $this->cache = \App\Core\DependencyFactory::getCacheService();
    }

    private function getEmailKey(string $email): string
    {
        return self::CACHE_PREFIX . 'email_' . md5(strtolower(trim($email)));
    }

    private function getPhoneKey(string $phone, string $countryCode): string
    {
        $full = preg_replace('/\D/', '', $countryCode) . preg_replace('/\D/', '', $phone);
        return self::CACHE_PREFIX . 'phone_' . md5($full);
    }

    /**
     * E-posta doğrulama kodu gönder
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
        $key = $this->getEmailKey($email);
        $this->cache->set($key, ['code' => $code], self::CODE_EXPIRY_SECONDS);

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
        <html><head><meta charset='UTF-8'></head>
        <body style='font-family:Arial,sans-serif;line-height:1.6;color:#333;'>
        <div style='max-width:600px;margin:0 auto;padding:20px;'>
        <h2>E-posta Doğrulama Kodu</h2>
        <p>Kayıt işleminizi tamamlamak için doğrulama kodunuz:</p>
        <div style='background:#f8f9fa;border:2px dashed #dee2e6;padding:20px;text-align:center;margin:20px 0;border-radius:8px;'>
        <span style='font-size:32px;font-weight:bold;color:#f97316;letter-spacing:8px;font-family:monospace;'>{$code}</span>
        </div>
        <p>Bu kod 10 dakika geçerlidir.</p>
        <p style='color:#dc3545;font-size:14px;'>Bu kodu kimseyle paylaşmayın.</p>
        <p>İyi çalışmalar,<br>{$appName} Ekibi</p>
        </div></body></html>";

        $sent = $emailService->sendEmail($email, $subject, $body);
        if (!$sent) {
            $this->cache->delete($key);
            return ['success' => false, 'error' => 'E-posta gönderilemedi. Lütfen tekrar deneyin.'];
        }

        return ['success' => true, 'message' => 'Doğrulama kodu e-posta adresinize gönderildi'];
    }

    /**
     * E-posta kodunu doğrula
     */
    public function verifyEmail(string $email, string $code): array
    {
        $email = trim(strtolower($email));
        $code = trim($code);
        if (empty($email) || empty($code) || strlen($code) < 4) {
            return ['success' => false, 'error' => 'E-posta ve kod gerekli'];
        }

        $key = $this->getEmailKey($email);
        $stored = $this->cache->get($key);
        if (!$stored || !is_array($stored)) {
            return ['success' => false, 'error' => 'Kod süresi dolmuş veya geçersiz. Lütfen tekrar kod gönderin.'];
        }
        if (!isset($stored['code']) || !hash_equals((string) $stored['code'], $code)) {
            return ['success' => false, 'error' => 'Geçersiz doğrulama kodu'];
        }

        $this->cache->delete($key);
        return ['success' => true, 'verified' => true];
    }

    /**
     * Telefon doğrulama kodu gönder (WhatsApp)
     */
    public function sendPhoneCode(string $phone, string $countryCode = '+90'): array
    {
        $phone = preg_replace('/\D/', '', $phone);
        if (empty($phone) || strlen($phone) < 9) {
            return ['success' => false, 'error' => 'Geçerli telefon numarası girin'];
        }
        if ($countryCode === '+90' && $phone[0] !== '5') {
            return ['success' => false, 'error' => 'Türkiye numarası 5 ile başlamalıdır (örn: 5321234567)'];
        }

        $cc = preg_replace('/\D/', '', $countryCode) ?: '90';
        $fullNumber = $cc . $phone;

        $code = (string) random_int(100000, 999999);
        $key = $this->getPhoneKey($phone, $countryCode);
        $this->cache->set($key, ['code' => $code, 'full_phone' => $fullNumber], self::CODE_EXPIRY_SECONDS);

        $whatsApp = \App\Core\DependencyFactory::getWhatsAppService();
        $result = $whatsApp->sendVerificationCode($fullNumber, $code);

        if (!$result['success']) {
            $this->cache->delete($key);
            return ['success' => false, 'error' => $result['message'] ?? 'WhatsApp ile kod gönderilemedi'];
        }

        return ['success' => true, 'message' => 'Doğrulama kodu WhatsApp ile gönderildi'];
    }

    /**
     * Telefon kodunu doğrula
     */
    public function verifyPhone(string $phone, string $countryCode, string $code): array
    {
        $phone = preg_replace('/\D/', '', $phone);
        $code = trim($code);
        if (empty($phone) || empty($code) || strlen($code) < 4) {
            return ['success' => false, 'error' => 'Telefon ve kod gerekli'];
        }

        $key = $this->getPhoneKey($phone, $countryCode);
        $stored = $this->cache->get($key);
        if (!$stored || !is_array($stored)) {
            return ['success' => false, 'error' => 'Kod süresi dolmuş veya geçersiz. Lütfen tekrar kod gönderin.'];
        }
        if (!isset($stored['code']) || !hash_equals((string) $stored['code'], $code)) {
            return ['success' => false, 'error' => 'Geçersiz doğrulama kodu'];
        }

        $this->cache->delete($key);
        return ['success' => true, 'verified' => true];
    }
}
