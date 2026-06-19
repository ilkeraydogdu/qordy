<?php
namespace App\Models;

use App\Core\Model;
use App\Services\SystemSettingsService;

/**
 * Email Configuration Model
 * Manages email and SMTP settings from SystemSettings
 */
class EmailConfig extends Model {
    private $settingsService;
    
    public function __construct(SystemSettingsService $settingsService) {
        $this->settingsService = $settingsService;
    }
    
    /**
     * Get SMTP host
     * @return string
     */
    public function getSmtpHost(): string {
        return $this->settingsService->getSetting('smtp_host', '');
    }
    
    /**
     * Get SMTP port
     * @return int
     */
    public function getSmtpPort(): int {
        return (int)$this->settingsService->getSetting('smtp_port', 587);
    }
    
    /**
     * Get SMTP encryption (tls/ssl/none)
     * @return string
     */
    public function getSmtpEncryption(): string {
        return $this->settingsService->getSetting('smtp_encryption', 'tls');
    }
    
    /**
     * Get SMTP username
     * @return string
     */
    public function getSmtpUsername(): string {
        return $this->settingsService->getSetting('smtp_username', '');
    }
    
    /**
     * Get SMTP password
     * @return string
     */
    public function getSmtpPassword(): string {
        return $this->settingsService->getSetting('smtp_password', '');
    }
    
    /**
     * Get from email address
     * @return string
     */
    public function getFromEmail(): string {
        // Use smtp_username as from_email since restaurant_email was removed
        return $this->settingsService->getSetting('smtp_username', '');
    }
    
    /**
     * Get from name
     * @return string
     */
    public function getFromName(): string {
        $fromName = $this->settingsService->getSetting('smtp_from_name', '');
        if (empty($fromName)) {
            $fromName = $this->settingsService->getSetting('site_name', 'Qordy');
        }
        return $fromName;
    }
    
    /**
     * Check if SMTP is configured
     * @return bool
     */
    public function isSmtpConfigured(): bool {
        return !empty($this->getSmtpHost()) && 
               !empty($this->getSmtpUsername()) && 
               !empty($this->getSmtpPassword()) &&
               !empty($this->getFromEmail());
    }
    
    /**
     * Get email driver preference (smtp or mail)
     * @return string
     */
    public function getPreferredDriver(): string {
        return $this->isSmtpConfigured() ? 'smtp' : 'mail';
    }
    
    /**
     * Get all email configuration as array
     * @return array
     */
    public function toArray(): array {
        return [
            'smtp_host' => $this->getSmtpHost(),
            'smtp_port' => $this->getSmtpPort(),
            'smtp_encryption' => $this->getSmtpEncryption(),
            'smtp_username' => $this->getSmtpUsername(),
            'smtp_password' => $this->getSmtpPassword(),
            'from_email' => $this->getFromEmail(),
            'from_name' => $this->getFromName(),
            'is_smtp_configured' => $this->isSmtpConfigured(),
            'preferred_driver' => $this->getPreferredDriver(),
        ];
    }
}

