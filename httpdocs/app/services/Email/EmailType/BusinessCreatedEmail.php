<?php
namespace App\Services\Email\EmailType;

use App\Services\SystemSettingsService;

/**
 * Business Created Email Type
 * Sent to users when their business is created
 */
class BusinessCreatedEmail extends AbstractEmailType {
    
    /**
     * Get email subject
     * @return string
     */
    public function getSubject(): string {
        $businessName = $this->data['business_name'] ?? 'İşletmeniz';
        return "İşletmeniz Oluşturuldu - {$businessName}";
    }
    
    /**
     * Get template path
     * @return string
     */
    public function getTemplatePath(): string {
        return 'business_created.php';
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
            'businessName' => $this->data['business_name'] ?? 'İşletmeniz',
            'businessId' => $this->data['business_id'] ?? '',
            'subdomain' => $this->data['subdomain'] ?? '',
            'hasPackage' => !empty($this->data['package_name']),
            'packageName' => $this->data['package_name'] ?? null,
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
        return 'İşletme Oluşturuldu';
    }
}
