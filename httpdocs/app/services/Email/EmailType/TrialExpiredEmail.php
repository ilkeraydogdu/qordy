<?php
namespace App\Services\Email\EmailType;

class TrialExpiredEmail extends AbstractEmailType {
    
    public function getSubject(): string {
        return "Deneme Süreniz Sona Erdi — Devam Etmek İçin Plan Seçin";
    }
    
    public function getTemplatePath(): string {
        return 'trial_expired.php';
    }
    
    public function getTemplateVariables(): array {
        $firstName = $this->data['first_name'] ?? '';
        $fullName = trim(($this->data['first_name'] ?? '') . ' ' . ($this->data['last_name'] ?? '')) ?: 'Değerli Kullanıcı';
        
        return [
            'fullName' => $fullName,
            'firstName' => $firstName,
            'companyName' => $this->data['company_name'] ?? '',
            'baseUrl' => defined('BASE_URL') ? BASE_URL : 'https://qordy.com',
        ];
    }
    
    public function validate(): bool {
        $email = $this->getRecipientEmail();
        return !empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    public function getRecipientEmail(): ?string {
        $email = $this->data['email'] ?? '';
        if (empty($email)) return null;
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }
}
