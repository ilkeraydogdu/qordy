<?php
namespace App\Services\Email\EmailType;

use App\Services\SystemSettingsService;

/**
 * Contact Form Confirmation Email Type
 */
class ContactFormConfirmationEmail extends AbstractEmailType {
    
    /**
     * Get email subject
     * @return string
     */
    public function getSubject(): string {
        $siteName = $this->settingsService->getSetting('site_name', 'Qordy');
        return "İletişim Formunuz Alındı - {$siteName}";
    }
    
    /**
     * Get template path
     * @return string
     */
    public function getTemplatePath(): string {
        return 'contact_form_confirmation.php';
    }
    
    /**
     * Get template variables
     * @return array
     */
    public function getTemplateVariables(): array {
        return [
            'fullName' => $this->data['full_name'] ?? 'Sayın Müşterimiz',
            'email' => $this->data['email'] ?? '',
            'companyName' => $this->data['company_name'] ?? null,
            'phone' => $this->data['phone'] ?? null,
            'message' => $this->data['message'] ?? null,
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
        
        return $this->extractEmail($email);
    }
    
    /**
     * Get email title
     * @return string
     */
    protected function getEmailTitle(): string {
        return 'İletişim Formu Onayı';
    }
}
