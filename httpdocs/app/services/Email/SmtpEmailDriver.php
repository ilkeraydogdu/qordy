<?php
namespace App\Services\Email;

use App\Services\SystemSettingsService;

/**
 * SMTP Email Driver
 * Uses PHPMailer to send emails via SMTP
 */
class SmtpEmailDriver implements EmailDriverInterface {
    private $settingsService;
    private $config;
    
    public function __construct(SystemSettingsService $settingsService) {
        $this->settingsService = $settingsService;
        $this->loadConfig();
    }
    
    /**
     * Load SMTP configuration from settings
     */
    private function loadConfig(): void {
        $this->config = [
            'host' => $this->settingsService->getSetting('smtp_host', ''),
            'port' => (int)$this->settingsService->getSetting('smtp_port', 587),
            'encryption' => $this->settingsService->getSetting('smtp_encryption', 'tls'),
            'username' => $this->settingsService->getSetting('smtp_username', ''),
            'password' => $this->settingsService->getSetting('smtp_password', ''),
            'from_email' => $this->settingsService->getSetting('smtp_username', ''), // Use smtp_username as from_email
            'from_name' => $this->settingsService->getSetting('smtp_from_name', $this->settingsService->getSetting('site_name', 'Qordy')),
        ];
    }
    
    /**
     * Check if SMTP is properly configured
     * @return bool
     */
    public function isConfigured(): bool {
        // Check if PHPMailer is available
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            return false;
        }
        
        return !empty($this->config['host']) && 
               !empty($this->config['username']) && 
               !empty($this->config['password']) &&
               !empty($this->config['from_email']);
    }
    
    /**
     * Send email via SMTP
     * @param string $to
     * @param string $subject
     * @param string $body
     * @param string|null $fromEmail
     * @param string|null $fromName
     * @return bool
     */
    public function send(string $to, string $subject, string $body, ?string $fromEmail = null, ?string $fromName = null): bool {
        if (!$this->isConfigured()) {
            error_log("SmtpEmailDriver: SMTP not properly configured");
            return false;
        }
        
        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            error_log("SmtpEmailDriver: Invalid recipient email: {$to}");
            return false;
        }
        
        $fromEmail = $fromEmail ?? $this->config['from_email'];
        $fromName = $fromName ?? $this->config['from_name'];
        
        // Check if PHPMailer is available
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            error_log("SmtpEmailDriver: PHPMailer not installed. Please run 'composer install'");
            return false;
        }
        
        $mail = null;
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = $this->config['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->config['username'];
            $mail->Password = $this->config['password'];
            $mail->SMTPSecure = $this->getEncryptionType();
            $mail->Port = $this->config['port'];
            $mail->CharSet = 'UTF-8';
            
            // Recipients
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($to);
            $mail->addReplyTo($fromEmail, $fromName);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = strip_tags($body);
            
            $mail->send();
            error_log("SmtpEmailDriver: Email sent successfully to {$to}");
            return true;
            
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            $errorInfo = $mail ? $mail->ErrorInfo : $e->getMessage();
            error_log("SmtpEmailDriver: Failed to send email to {$to}: " . $errorInfo);
            return false;
        } catch (\Exception $e) {
            error_log("SmtpEmailDriver: Exception sending email: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get encryption type constant for PHPMailer
     * @return string
     */
    private function getEncryptionType(): string {
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            return '';
        }
        
        $encryption = strtolower($this->config['encryption']);
        switch ($encryption) {
            case 'ssl':
                return \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            case 'tls':
                return \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            default:
                return '';
        }
    }
}

