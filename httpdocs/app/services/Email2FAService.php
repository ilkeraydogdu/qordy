<?php
namespace App\Services;

/**
 * Email 2FA Service
 * Handles email-based 2FA code sending
 * 
 * @package App\Services
 */
class Email2FAService {
    private $emailService;

    public function __construct() {
        require_once __DIR__ . '/../core/DependencyFactory.php';
        $this->emailService = \App\Core\DependencyFactory::getEmailService();
    }

    /**
     * Send 2FA code via email
     * @param string $userId
     * @param string $email
     * @param string $code
     * @return array Result with 'success' and 'message' keys
     */
    public function sendCode(string $userId, string $email, string $code): array {
        $subject = 'Qordy - İki Adımlı Doğrulama Kodu';
        
        $body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .code-box { background: #f8f9fa; border: 2px dashed #dee2e6; padding: 20px; text-align: center; margin: 20px 0; border-radius: 8px; }
                .code { font-size: 32px; font-weight: bold; color: #f97316; letter-spacing: 8px; font-family: monospace; }
                .warning { color: #dc3545; font-size: 14px; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <h2>İki Adımlı Doğrulama Kodu</h2>
                <p>Merhaba,</p>
                <p>Qordy sistemine giriş yapmak için aşağıdaki doğrulama kodunu kullanın:</p>
                <div class='code-box'>
                    <div class='code'>{$code}</div>
                </div>
                <p>Bu kod 10 dakika geçerlidir.</p>
                <p class='warning'>Bu kodu kimseyle paylaşmayın. Eğer bu işlemi siz yapmadıysanız, lütfen hemen şifrenizi değiştirin.</p>
                <p>İyi çalışmalar,<br>Qordy Ekibi</p>
            </div>
        </body>
        </html>
        ";

        try {
            $result = $this->emailService->sendEmail($email, $subject, $body);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Doğrulama kodu e-posta adresinize gönderildi.'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'E-posta gönderilemedi. Lütfen e-posta ayarlarını kontrol edin.'
                ];
            }
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("Email2FAService sendCode exception: " . $e->getMessage(), [
                    'user_id' => $userId,
                    'email' => $email,
                    'exception' => get_class($e)
                ]);
            }
            return [
                'success' => false,
                'message' => 'E-posta gönderilirken bir hata oluştu: ' . $e->getMessage()
            ];
        }
    }
}

