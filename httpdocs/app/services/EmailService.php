<?php
namespace App\Services;

use App\Services\Email\EmailDriverInterface;
use App\Services\Email\SmtpEmailDriver;
use App\Services\Email\MailEmailDriver;
use App\Services\Email\EmailType\AbstractEmailType;
use App\Services\Email\EmailType\ReservationConfirmationEmail;
use App\Services\Email\EmailType\ReservationReminderEmail;
use App\Models\EmailConfig;

/**
 * Email Service - Centralized Email Sending System
 * Handles all email operations with MVC OOP architecture
 * Supports SMTP and mail() drivers, template-based emails, and dynamic configuration
 */
class EmailService {
    private $settingsService;
    private $emailConfig;
    private $driver;
    
    public function __construct() {
        require_once __DIR__ . '/../core/DependencyFactory.php';
        $this->settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
        $this->emailConfig = new EmailConfig($this->settingsService);
        $this->driver = $this->createDriver();
    }
    
    /**
     * Create email driver based on configuration
     * @return EmailDriverInterface
     */
    private function createDriver(): EmailDriverInterface {
        $preferredDriver = $this->emailConfig->getPreferredDriver();
        
        if ($preferredDriver === 'smtp') {
            $smtpDriver = new SmtpEmailDriver($this->settingsService);
            if ($smtpDriver->isConfigured()) {
                return $smtpDriver;
            }
        }
        
        // Fallback to mail() driver
        return new MailEmailDriver($this->settingsService);
    }
    
    /**
     * Send email (generic method)
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $body Email body (HTML)
     * @param string|null $fromEmail Sender email (optional)
     * @param string|null $fromName Sender name (optional)
     * @return bool Success
     */
    public function sendEmail(string $to, string $subject, string $body, ?string $fromEmail = null, ?string $fromName = null): bool {
        $fromEmail = $fromEmail ?? $this->emailConfig->getFromEmail();
        $fromName = $fromName ?? $this->emailConfig->getFromName();
        
        return $this->driver->send($to, $subject, $body, $fromEmail, $fromName);
    }
    
    /**
     * Send email using email type
     * @param AbstractEmailType $emailType
     * @return bool Success
     */
    public function sendEmailType(AbstractEmailType $emailType): bool {
        if (!$emailType->validate()) {
            error_log("EmailService: Email type validation failed");
            return false;
        }
        
        $recipientEmail = $emailType->getRecipientEmail();
        if (empty($recipientEmail)) {
            error_log("EmailService: No recipient email found");
            return false;
        }
        
        $subject = $emailType->getSubject();
        $body = $emailType->render();
        
        return $this->sendEmail($recipientEmail, $subject, $body);
    }
    
    /**
     * Send reservation confirmation email
     * @param array $reservation Reservation data
     * @return bool Success
     */
    public function sendReservationConfirmation(array $reservation): bool {
        $emailType = new ReservationConfirmationEmail($this->settingsService, $reservation);
        return $this->sendEmailType($emailType);
    }
    
    /**
     * Send reservation reminder email
     * @param array $reservation Reservation data
     * @param int $hoursBefore Hours before reservation (default: 24)
     * @return bool Success
     */
    public function sendReservationReminder(array $reservation, int $hoursBefore = 24): bool {
        $emailType = new ReservationReminderEmail($this->settingsService, $reservation, $hoursBefore);
        return $this->sendEmailType($emailType);
    }
    
    /**
     * Get current email driver
     * @return EmailDriverInterface
     */
    public function getDriver(): EmailDriverInterface {
        return $this->driver;
    }
    
    /**
     * Get email configuration
     * @return EmailConfig
     */
    public function getEmailConfig(): EmailConfig {
        return $this->emailConfig;
    }
    
    /**
     * Reload driver (useful when settings change)
     * @return void
     */
    public function reloadDriver(): void {
        $this->emailConfig = new EmailConfig($this->settingsService);
        $this->driver = $this->createDriver();
    }
    
    /**
     * Test email sending
     * @param string $to Test recipient email
     * @return array Result with success status and message
     */
    public function testEmail(string $to): array {
        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'message' => 'Geçersiz email adresi',
                'driver' => $this->getDriverName()
            ];
        }
        
        $subject = 'Test Email - ' . $this->emailConfig->getFromName();
        $body = $this->getTestEmailTemplate();
        
        $result = $this->sendEmail($to, $subject, $body);
        
        return [
            'success' => $result,
            'message' => $result 
                ? 'Test emaili başarıyla gönderildi' 
                : 'Test emaili gönderilemedi. Lütfen ayarları kontrol edin.',
            'driver' => $this->getDriverName(),
            'smtp_configured' => $this->emailConfig->isSmtpConfigured()
        ];
    }
    
    /**
     * Get current driver name
     * @return string
     */
    public function getDriverName(): string {
        return $this->emailConfig->getPreferredDriver();
    }
    
    /**
     * Get test email template
     * @return string
     */
    private function getTestEmailTemplate(): string {
        $siteName = $this->settingsService->getSetting('site_name', 'Qordy');
        $driverName = $this->getDriverName();
        $smtpConfigured = $this->emailConfig->isSmtpConfigured();
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Test Email</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;'>
            <div style='background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
                <h1 style='color: white; margin: 0; font-size: 28px;'>{$siteName}</h1>
            </div>
            <div style='background: #ffffff; padding: 30px; border: 1px solid #e5e7eb; border-top: none; border-radius: 0 0 10px 10px;'>
                <h2 style='color: #1f2937; margin-top: 0;'>Test Email</h2>
                <p>Bu bir test emailidir. Email sistemi çalışıyor!</p>
                <div style='background: #f9fafb; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                    <p style='margin: 5px 0;'><strong>Kullanılan Driver:</strong> " . strtoupper($driverName) . "</p>
                    <p style='margin: 5px 0;'><strong>SMTP Yapılandırıldı:</strong> " . ($smtpConfigured ? 'Evet' : 'Hayır') . "</p>
                    <p style='margin: 5px 0;'><strong>Gönderim Zamanı:</strong> " . date('Y-m-d H:i:s') . "</p>
                </div>
                <p style='color: #6b7280; font-size: 14px;'>
                    Eğer bu emaili alıyorsanız, email sistemi düzgün çalışıyor demektir.
                </p>
            </div>
            <div style='text-align: center; margin-top: 20px; color: #9ca3af; font-size: 12px;'>
                <p>Bu otomatik bir e-postadır. Lütfen yanıtlamayın.</p>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Check if email service is properly configured
     * @return array Status information
     */
    public function getStatus(): array {
        $driver = $this->getDriver();
        $config = $this->emailConfig;
        
        return [
            'driver' => $this->getDriverName(),
            'driver_configured' => $driver->isConfigured(),
            'smtp_configured' => $config->isSmtpConfigured(),
            'from_email' => $config->getFromEmail(),
            'from_name' => $config->getFromName(),
            'smtp_host' => $config->getSmtpHost(),
            'smtp_port' => $config->getSmtpPort(),
            'smtp_encryption' => $config->getSmtpEncryption(),
            'has_phpmailer' => class_exists('PHPMailer\PHPMailer\PHPMailer')
        ];
    }
}
