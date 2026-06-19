<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\SystemSettingsRepository;
use App\Core\DependencyFactory;

/**
 * SMS Service - Netgsm Integration
 * Handles SMS sending via Netgsm API
 * 
 * @package App\Services
 */
class SMSService {
    private $settingsService;
    private $username;
    private $password;
    private $msgheader;
    private $apiUrl = 'https://api.netgsm.com.tr/sms/send/get';
    private $translationService;

    public function __construct() {
        require_once __DIR__ . '/../core/DependencyFactory.php';
        $this->settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
        $this->translationService = DependencyFactory::getTranslationService();
        $this->loadConfig();
    }

    /**
     * Load SMS configuration from settings
     */
    private function loadConfig(): void {
        $this->username = $this->settingsService->getSetting('netgsm_username', '');
        $this->password = $this->settingsService->getSetting('netgsm_password', '');
        $this->msgheader = $this->settingsService->getSetting('netgsm_msgheader', '');
    }

    /**
     * Reload configuration (useful after settings update)
     */
    public function reloadConfig(): void {
        $this->loadConfig();
    }

    /**
     * Check if SMS service is configured
     * @return bool
     */
    public function isConfigured(): bool {
        return !empty($this->username) && !empty($this->password);
    }

    /**
     * Send SMS via Netgsm
     * @param string $phone Phone number (format: 5XXXXXXXXX without country code)
     * @param string $message SMS message (max 160 characters for single SMS)
     * @return array Result with 'success' and 'message' keys
     */
    public function sendSMS(string $phone, string $message): array {
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'SMS servisi yapılandırılmamış. Lütfen ayarlardan Netgsm bilgilerini girin.'
            ];
        }

        // Validate phone number (Turkish format: 5XXXXXXXXX)
        $phone = $this->normalizePhone($phone);
        if (!$phone) {
            return [
                'success' => false,
                'message' => $this->translationService->translate('notifications.error.invalid_phone_format', null, [])
            ];
        }

        // Prepare API parameters
        $params = [
            'usercode' => $this->username,
            'password' => $this->password,
            'gsmno' => $phone,
            'message' => $message,
            'msgheader' => $this->msgheader ?: $this->username,
            'language' => 'TR',
            'filter' => '0',
            'startdate' => '',
            'stopdate' => ''
        ];

        // Build query string
        $queryString = http_build_query($params);
        $url = $this->apiUrl . '?' . $queryString;

        // Send request
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                error_log("SMSService: cURL error: " . $error);
                return [
                    'success' => false,
                    'message' => $this->translationService->translate('notifications.error.sms_send_failed', ['error' => $error], [])
                ];
            }

            if ($httpCode !== 200) {
                error_log("SMSService: HTTP error: " . $httpCode);
                return [
                    'success' => false,
                    'message' => $this->translationService->translate('notifications.error.sms_service_unavailable', ['code' => $httpCode], [])
                ];
            }

            // Parse Netgsm response
            // Success response format: "00 <message_id>" or just a number
            // Error response format: error code and message
            $response = trim($response);
            
            if (is_numeric($response) || strpos($response, '00') === 0) {
                // Success
                return [
                    'success' => true,
                    'message' => $this->translationService->translate('notifications.success.sms_sent', null, []),
                    'message_id' => $response
                ];
            } else {
                // Error
                $errorMessage = $this->getErrorMessage($response);
                error_log("SMSService: API error: " . $response);
                return [
                    'success' => false,
                    'message' => $this->translationService->translate('notifications.error.sms_send_failed', ['error' => $errorMessage], [])
                ];
            }

        } catch (\Exception $e) {
            error_log("SMSService: Exception: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $this->translationService->translate('notifications.error.sms_send_failed', ['error' => $e->getMessage()], [])
            ];
        }
    }

    /**
     * Normalize phone number to Turkish format (5XXXXXXXXX)
     * @param string $phone
     * @return string|false Normalized phone or false if invalid
     */
    private function normalizePhone(string $phone): string|false {
        // Remove all non-digit characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Remove country code if present (90)
        if (strlen($phone) === 12 && strpos($phone, '90') === 0) {
            $phone = substr($phone, 2);
        }
        
        // Remove leading 0 if present
        if (strlen($phone) === 11 && strpos($phone, '0') === 0) {
            $phone = substr($phone, 1);
        }
        
        // Validate: should be 10 digits starting with 5
        if (strlen($phone) === 10 && strpos($phone, '5') === 0) {
            return $phone;
        }
        
        return false;
    }

    /**
     * Get human-readable error message from Netgsm error code
     * @param string $errorCode
     * @return string
     */
    private function getErrorMessage(string $errorCode): string {
        $errorMessages = [
            '20' => 'Mesaj metninde hata var',
            '30' => 'Geçersiz kullanıcı adı, şifre veya yetkisiz IP',
            '40' => 'Abone hesabında yeterli kredi yok',
            '50' => 'Abone hesabı aktif değil',
            '51' => 'Abone hesabı askıya alınmış',
            '70' => 'Hatalı sorgu. Gönderdiğiniz parametrelerden birisi hatalı veya zorunlu alanlardan birisi eksik',
            '80' => 'Gönderilemedi',
            '85' => 'Mükerrer gönderim',
            '100' => 'Sistem hatası',
            '101' => 'Sistem hatası'
        ];
        
        $code = trim($errorCode);
        if (isset($errorMessages[$code])) {
            return $errorMessages[$code];
        }
        
        return 'Bilinmeyen hata: ' . $errorCode;
    }
}

