<?php
namespace App\Services;

use App\Repositories\User2FARepository;
use App\Repositories\User2FACodeRepository;
use App\Repositories\UserRepository;
use App\Core\DependencyFactory;

/**
 * Two Factor Authentication Service
 * Main service for 2FA operations
 * 
 * @package App\Services
 */
class TwoFactorAuthService {
    private $user2FARepository;
    private $user2FACodeRepository;
    private $userRepository;
    private $email2FAService;
    private $sms2FAService;
    private $translationService;

    public function __construct(
        User2FARepository $user2FARepository,
        User2FACodeRepository $user2FACodeRepository,
        UserRepository $userRepository
    ) {
        $this->user2FARepository = $user2FARepository;
        $this->user2FACodeRepository = $user2FACodeRepository;
        $this->userRepository = $userRepository;
        $this->translationService = DependencyFactory::getTranslationService();
        
        // Sub-services will be initialized lazily to avoid circular dependency
    }

    /**
     * Enable 2FA for a user
     * @param string $userId
     * @param string $method 'email' or 'sms'
     * @param string $secretCode Email address or phone number
     * @return array Result with 'success' and 'message' keys
     */
    public function enable2FA(string $userId, string $method, string $secretCode): array {
        if (!in_array($method, ['email', 'sms', 'whatsapp', 'totp'], true)) {
            return [
                'success' => false,
                'message' => $this->translationService->translate('notifications.error.invalid_2fa_method', null, [])
            ];
        }

        // Superadmin globally disabled this method? refuse.
        if (!$this->isMethodGloballyAllowed($method)) {
            return [
                'success' => false,
                'message' => 'Bu doğrulama yöntemi şu anda kapalı.'
            ];
        }

        // Validate secret code based on method
        if ($method === 'email' && !filter_var($secretCode, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'message' => $this->translationService->translate('notifications.error.invalid_email', null, [])
            ];
        }

        if (in_array($method, ['sms', 'whatsapp'], true)
            && !preg_match('/^5[0-9]{9}$/', preg_replace('/[^0-9]/', '', $secretCode))) {
            return [
                'success' => false,
                'message' => $this->translationService->translate('notifications.error.invalid_phone_format', null, [])
            ];
        }

        // Normalize phone number for SMS / WhatsApp
        if (in_array($method, ['sms', 'whatsapp'], true)) {
            $secretCode = preg_replace('/[^0-9]/', '', $secretCode);
            if (strlen($secretCode) === 11 && strpos($secretCode, '0') === 0) {
                $secretCode = substr($secretCode, 1);
            }
        }

        // TOTP enrolment from this endpoint expects the caller to have already
        // generated a base32 secret (e.g. mobile /totp/setup flow) and passed
        // a 6-digit verification code as `secret_code`. Production TOTP setup
        // should use MobileAPIController::totpSetup/totpConfirm instead;
        // we still accept the method here for admin tooling completeness.
        if ($method === 'totp' && !preg_match('/^[A-Z2-7]{16,}$/', strtoupper($secretCode))) {
            return [
                'success' => false,
                'message' => 'TOTP kurulumu için /api/mobile/security/totp/setup akışını kullanın.'
            ];
        }

        $result = $this->user2FARepository->enable($userId, $method, $secretCode);
        
        if ($result) {
            return [
                'success' => true,
                'message' => $this->translationService->translate('notifications.success.2fa_enabled', null, [])
            ];
        } else {
            return [
                'success' => false,
                'message' => $this->translationService->translate('notifications.error.2fa_enable_failed', null, [])
            ];
        }
    }

    /**
     * Disable 2FA for a user
     * @param string $userId
     * @param string $method 'email' or 'sms'
     * @return array Result with 'success' and 'message' keys
     */
    public function disable2FA(string $userId, string $method): array {
        if (!in_array($method, ['email', 'sms', 'whatsapp', 'totp'], true)) {
            return [
                'success' => false,
                'message' => $this->translationService->translate('notifications.error.invalid_2fa_method', null, [])
            ];
        }

        $result = $this->user2FARepository->disable($userId, $method);
        
        if ($result) {
            // Invalidate all codes for security
            $this->user2FACodeRepository->invalidateAllCodes($userId, $method);
            
            return [
                'success' => true,
                'message' => $this->translationService->translate('notifications.success.2fa_disabled', null, [])
            ];
        } else {
            return [
                'success' => false,
                'message' => $this->translationService->translate('notifications.error.2fa_disable_failed', null, [])
            ];
        }
    }

    /**
     * Send verification code
     * @param string $userId
     * @param string $method 'email' or 'sms'
     * @return array Result with 'success' and 'message' keys
     */
    public function sendVerificationCode(string $userId, string $method): array {
        // Route WhatsApp through the dedicated Meta Cloud API sender. TOTP
        // has no server-side send step (codes are generated client-side),
        // and the caller should simply prompt the user for their app code.
        if ($method === 'whatsapp') {
            return $this->sendWhatsAppCode($userId);
        }
        if ($method === 'totp') {
            return [
                'success' => true,
                'message' => 'TOTP için uygulamanızda görünen 6 haneli kodu girin.'
            ];
        }
        if (!in_array($method, ['email', 'sms'], true)) {
            return [
                'success' => false,
                'message' => $this->translationService->translate('notifications.error.invalid_2fa_method', null, [])
            ];
        }

        // Check if 2FA is enabled
        if (!$this->user2FARepository->isEnabled($userId, $method)) {
            return [
                'success' => false,
                'message' => $this->translationService->translate('notifications.error.2fa_not_enabled', null, [])
            ];
        }

        // Get user info
        $user = $this->userRepository->findByUserId($userId);
        if (!$user) {
            return [
                'success' => false,
                'message' => $this->translationService->translate('notifications.error.user_not_found', null, [])
            ];
        }

        // Get 2FA config
        $config = $this->user2FARepository->getByUserAndMethod($userId, $method);
        if (!$config) {
            return [
                'success' => false,
                'message' => $this->translationService->translate('notifications.error.2fa_config_not_found', null, [])
            ];
        }

        // Cryptographically secure 6-digit code. rand() is predictable and
        // should never be used for OTP material.
        $code = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

        // Save code to database
        $this->user2FACodeRepository->createCode($userId, $code, $method, 10); // 10 minutes expiry

        // Send code via appropriate method
        try {
            if ($method === 'email') {
                if (!$this->email2FAService) {
                    require_once __DIR__ . '/../core/DependencyFactory.php';
                    $this->email2FAService = \App\Core\DependencyFactory::getEmail2FAService();
                }
                if (!$this->email2FAService) {
                    throw new \Exception('Email2FAService could not be initialized');
                }
                $result = $this->email2FAService->sendCode($userId, $config['secret_code'], $code);
                
                // Log for debugging if sending failed
                if (!$result['success']) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::warning("2FA email send failed", [
                            'user_id' => $userId,
                            'email' => $config['secret_code'],
                            'message' => $result['message'] ?? 'No message provided'
                        ]);
                    }
                }
                
                return $result;
            } else {
                if (!$this->sms2FAService) {
                    require_once __DIR__ . '/../core/DependencyFactory.php';
                    $this->sms2FAService = \App\Core\DependencyFactory::getSMS2FAService();
                }
                if (!$this->sms2FAService) {
                    throw new \Exception('SMS2FAService could not be initialized');
                }
                $result = $this->sms2FAService->sendCode($userId, $config['secret_code'], $code);
                
                // Log for debugging if sending failed
                if (!$result['success']) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::warning("2FA SMS send failed", [
                            'user_id' => $userId,
                            'phone' => $config['secret_code'],
                            'message' => $result['message'] ?? 'No message provided'
                        ]);
                    }
                }
                
                return $result;
            }
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("2FA code send exception: " . $e->getMessage(), [
                    'user_id' => $userId,
                    'method' => $method,
                    'exception' => get_class($e),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            return [
                'success' => false,
                'message' => 'Kod gönderilirken bir hata oluştu: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Verify code
     *
     * IMPORTANT arg order is (userId, code, method) — do not reorder.
     * Callers (AuthController, AdminController, TwoFactorController,
     * MobileAPIController) depend on this signature.
     *
     * @param string $userId
     * @param string $code   6-digit numeric code entered by the user
     * @param string $method 'email' | 'sms' | 'whatsapp' | 'totp'
     * @return array Result with 'success' and 'message' keys
     */
    public function verifyCode(string $userId, string $code, string $method): array {
        if (!in_array($method, ['email', 'sms', 'whatsapp', 'totp'])) {
            return [
                'success' => false,
                'message' => $this->translationService->translate('notifications.error.invalid_2fa_method', null, [])
            ];
        }

        // Validate code format (6 digits)
        if (!preg_match('/^\d{6}$/', $code)) {
            return [
                'success' => false,
                'message' => $this->translationService->translate('notifications.error.invalid_code_format', null, [])
            ];
        }

        // TOTP: verify the live 30-second window against the stored
        // base32 secret. No DB round-trip for the code itself.
        if ($method === 'totp') {
            $row = $this->user2FARepository->getByUserAndMethod($userId, 'totp');
            $secret = $row['secret_code'] ?? '';
            if ($secret === '') {
                return [
                    'success' => false,
                    'message' => $this->translationService->translate('notifications.error.invalid_or_expired_code', null, []),
                ];
            }
            require_once __DIR__ . '/TotpService.php';
            // Use a wider ±60s window to accommodate device clock drift;
            // see MobileAPIController::totpConfirm for rationale.
            $ok = \App\Services\TotpService::verifyCode($secret, $code, 2);
            return [
                'success' => $ok,
                'message' => $ok
                    ? $this->translationService->translate('notifications.success.code_verified', null, [])
                    : $this->translationService->translate('notifications.error.invalid_or_expired_code', null, []),
            ];
        }

        $result = $this->user2FACodeRepository->verifyCode($userId, $code, $method);
        
        if ($result) {
            return [
                'success' => true,
                'message' => $this->translationService->translate('notifications.success.code_verified', null, [])
            ];
        } else {
            return [
                'success' => false,
                'message' => $this->translationService->translate('notifications.error.invalid_or_expired_code', null, [])
            ];
        }
    }

    /**
     * Check if 2FA is enabled for a user
     * @param string $userId
     * @param string $method 'email', 'sms', 'whatsapp' or 'totp'
     * @return bool
     */
    public function is2FAEnabled(string $userId, string $method): bool {
        return $this->user2FARepository->isEnabled($userId, $method);
    }

    /**
     * Get all enabled 2FA methods for a user
     * @param string $userId
     * @return array
     */
    public function getEnabledMethods(string $userId): array {
        return $this->user2FARepository->getEnabledMethods($userId);
    }

    /**
     * Whether a given 2FA method is globally allowed by the superadmin.
     * Defaults: totp + email on, whatsapp + sms off.
     */
    public function isMethodGloballyAllowed(string $method): bool {
        try {
            $settings = DependencyFactory::getSystemSettingsService();
            $key = 'auth_2fa_' . $method . '_enabled';
            $default = in_array($method, ['totp', 'email'], true) ? '1' : '0';
            return (string)$settings->getSetting($key, $default) === '1';
        } catch (\Throwable $e) {
            return $method === 'totp';
        }
    }

    /**
     * Intersect user-enrolled methods with superadmin-allowed methods.
     * Sorted by preference (totp → whatsapp → email → sms). Use this
     * in login paths instead of getEnabledMethods() so a method the
     * admin has disabled is never offered.
     */
    public function getEffectiveMethods(string $userId): array {
        $enrolled = $this->getEnabledMethods($userId);
        $out = [];
        foreach (['totp', 'whatsapp', 'email', 'sms'] as $m) {
            if (in_array($m, $enrolled, true) && $this->isMethodGloballyAllowed($m)) {
                $out[] = $m;
            }
        }
        return $out;
    }

    /**
     * Overrides sendVerificationCode for WhatsApp which uses the Meta
     * Cloud API rather than the email/sms sub-services. TOTP has no
     * "send" step since codes are generated client-side.
     */
    public function sendWhatsAppCode(string $userId): array {
        if (!$this->isMethodGloballyAllowed('whatsapp')) {
            return ['success' => false, 'message' => 'WhatsApp doğrulama şu an kapalı'];
        }
        $cfg = $this->user2FARepository->getByUserAndMethod($userId, 'whatsapp');
        $phone = $cfg['secret_code'] ?? '';
        if ($phone === '') {
            return ['success' => false, 'message' => 'WhatsApp 2FA kurulu değil'];
        }
        $code = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $this->user2FACodeRepository->createCode($userId, $code, 'whatsapp', 10);

        $wa = \App\Core\DependencyFactory::getWhatsAppService();
        return $wa->sendVerificationCode($phone, $code);
    }
}

