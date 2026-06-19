<?php
namespace App\Services\Email\EmailType;

use App\Services\SystemSettingsService;

/**
 * Welcome Email Type
 * Sent to new users after registration
 */
class WelcomeEmail extends AbstractEmailType {
    
    /**
     * Get email subject
     * @return string
     */
    public function getSubject(): string {
        return "Qordy'ye Hoş Geldiniz!";
    }
    
    /**
     * Get template path
     * @return string
     */
    public function getTemplatePath(): string {
        return 'welcome.php';
    }
    
    /**
     * Get template variables
     * @return array
     */
    public function getTemplateVariables(): array {
        $firstName = $this->data['first_name'] ?? '';
        $lastName = $this->data['last_name'] ?? '';
        $fullName = trim($firstName . ' ' . $lastName);
        if (empty($fullName)) {
            $fullName = $firstName ?: $lastName ?: 'Değerli Kullanıcı';
        }
        
        return [
            'firstName' => $firstName,
            'lastName' => $lastName,
            'fullName' => $fullName,
            'email' => $this->data['email'] ?? '',
            'customerId' => $this->data['customer_id'] ?? null,
            'baseUrl' => defined('BASE_URL') ? BASE_URL : 'https://qordy.com',
        ];
    }
    
    /**
     * Validate email data
     * @return bool
     */
    public function validate(): bool {
        $email = $this->getRecipientEmail();
        return !empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL);
    }
    
    /**
     * Get recipient email
     * @return string|null
     */
    public function getRecipientEmail(): ?string {
        $email = $this->data['email'] ?? '';
        if (empty($email)) {
            return null;
        }
        
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }
    
    /**
     * Get email title
     * @return string
     */
    protected function getEmailTitle(): string {
        return 'Hoş Geldiniz';
    }
}
