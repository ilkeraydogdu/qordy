<?php
namespace App\Services\Email\EmailType;

class TrialExpiringEmail extends AbstractEmailType {
    
    public function getSubject(): string {
        $days = $this->data['remaining_days'] ?? 3;
        return "Deneme Sürenizin Bitmesine {$days} Gün Kaldı!";
    }
    
    public function getTemplatePath(): string {
        return 'trial_expiring.php';
    }
    
    public function getTemplateVariables(): array {
        $firstName = $this->data['first_name'] ?? '';
        $fullName = trim(($this->data['first_name'] ?? '') . ' ' . ($this->data['last_name'] ?? '')) ?: 'Değerli Kullanıcı';
        
        return [
            'fullName' => $fullName,
            'firstName' => $firstName,
            'remainingDays' => $this->data['remaining_days'] ?? 3,
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
