<?php
namespace App\Services\Email;

use App\Services\SystemSettingsService;

/**
 * Mail Email Driver (Fallback)
 * Uses PHP's native mail() function as fallback
 */
class MailEmailDriver implements EmailDriverInterface {
    private $settingsService;
    private $fromEmail;
    private $fromName;
    
    public function __construct(SystemSettingsService $settingsService) {
        $this->settingsService = $settingsService;
        // Use smtp_username as from_email since restaurant_email was removed
        $this->fromEmail = $this->settingsService->getSetting('smtp_username', '');
        $this->fromName = $this->settingsService->getSetting('smtp_from_name', $this->settingsService->getSetting('site_name', 'Qordy'));
    }
    
    /**
     * Check if mail driver is configured
     * @return bool
     */
    public function isConfigured(): bool {
        return function_exists('mail');
    }
    
    /**
     * Send email using PHP mail() function
     * @param string $to
     * @param string $subject
     * @param string $body
     * @param string|null $fromEmail
     * @param string|null $fromName
     * @return bool
     */
    public function send(string $to, string $subject, string $body, ?string $fromEmail = null, ?string $fromName = null): bool {
        if (!$this->isConfigured()) {
            error_log("MailEmailDriver: mail() function not available");
            return false;
        }
        
        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            error_log("MailEmailDriver: Invalid recipient email: {$to}");
            return false;
        }
        
        $fromEmail = $fromEmail ?? $this->fromEmail;
        $fromName = $fromName ?? $this->fromName;
        
        // Email headers
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $fromName . ' <' . $fromEmail . '>',
            'Reply-To: ' . $fromEmail,
            'X-Mailer: PHP/' . phpversion()
        ];
        
        try {
            $result = mail($to, $subject, $body, implode("\r\n", $headers));
            
            if (!$result) {
                error_log("MailEmailDriver: Failed to send email to {$to}");
                return false;
            }
            
            error_log("MailEmailDriver: Email sent successfully to {$to}");
            return true;
        } catch (\Exception $e) {
            error_log("MailEmailDriver: Exception sending email: " . $e->getMessage());
            return false;
        }
    }
}

