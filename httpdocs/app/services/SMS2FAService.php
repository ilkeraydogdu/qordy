<?php
namespace App\Services;

/**
 * SMS 2FA Service
 * Handles SMS-based 2FA code sending
 * 
 * @package App\Services
 */
class SMS2FAService {
    private $smsService;

    public function __construct() {
        require_once __DIR__ . '/../core/DependencyFactory.php';
        $this->smsService = \App\Core\DependencyFactory::getSMSService();
    }

    /**
     * Send 2FA code via SMS
     * @param string $userId
     * @param string $phone
     * @param string $code
     * @return array Result with 'success' and 'message' keys
     */
    public function sendCode(string $userId, string $phone, string $code): array {
        $message = "Qordy 2FA kodu: {$code}. Bu kodu kimseyle paylasmayin. 10 dakika gecerlidir.";
        
        $result = $this->smsService->sendSMS($phone, $message);
        
        if ($result['success']) {
            return [
                'success' => true,
                'message' => 'Doğrulama kodu telefon numaranıza gönderildi.'
            ];
        } else {
            return [
                'success' => false,
                'message' => $result['message'] ?? 'SMS gönderilemedi.'
            ];
        }
    }
}

