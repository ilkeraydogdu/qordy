<?php
namespace App\Services\Email\EmailType;

class TrialStartedEmail extends AbstractEmailType {
    
    public function getSubject(): string {
        $days = $this->data['trial_duration_days'] ?? 14;
        return "Hoş Geldiniz! {$days} Günlük Ücretsiz Denemeniz Başladı";
    }
    
    public function getTemplatePath(): string {
        return 'trial_started.php';
    }
    
    public function getTemplateVariables(): array {
        $firstName = $this->data['first_name'] ?? '';
        $lastName = $this->data['last_name'] ?? '';
        $fullName = trim($firstName . ' ' . $lastName) ?: 'Değerli Kullanıcı';
        
        return [
            'fullName' => $fullName,
            'firstName' => $firstName,
            'email' => $this->data['email'] ?? '',
            'trialDays' => $this->data['trial_duration_days'] ?? 14,
            'trialEndsAt' => $this->data['trial_ends_at'] ?? '',
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
