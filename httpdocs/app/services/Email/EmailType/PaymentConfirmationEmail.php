<?php
namespace App\Services\Email\EmailType;

use App\Services\SystemSettingsService;

/**
 * Payment Confirmation Email Type
 * Sent to users after successful payment for package purchase
 */
class PaymentConfirmationEmail extends AbstractEmailType {
    
    /**
     * Get email subject
     * @return string
     */
    public function getSubject(): string {
        $packageName = $this->data['package_name'] ?? 'Paket';
        return "Ödemeniz Alındı - {$packageName}";
    }
    
    /**
     * Get template path
     * @return string
     */
    public function getTemplatePath(): string {
        return 'payment_confirmation.php';
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
            $fullName = $firstName ?: $lastName ?: 'Değerli Müşterimiz';
        }
        
        return [
            'firstName' => $firstName,
            'lastName' => $lastName,
            'fullName' => $fullName,
            'email' => $this->data['email'] ?? '',
            'packageName' => $this->data['package_name'] ?? 'Paket',
            'amount' => $this->data['amount'] ?? 0,
            'currency' => $this->data['currency'] ?? 'TRY',
            'paymentMethod' => $this->data['payment_method'] ?? 'Manuel',
            'paymentDate' => $this->data['payment_date'] ?? date('d.m.Y H:i'),
            'subscriptionId' => $this->data['subscription_id'] ?? '',
            'paymentStatus' => $this->data['payment_status'] ?? 'completed',
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
        return 'Ödeme Onayı';
    }
}
