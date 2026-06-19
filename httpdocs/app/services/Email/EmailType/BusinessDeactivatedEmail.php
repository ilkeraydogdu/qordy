<?php
namespace App\Services\Email\EmailType;

use App\Services\SystemSettingsService;

class BusinessDeactivatedEmail extends AbstractEmailType {
    
    public function getSubject(): string {
        $businessName = $this->data['business_name'] ?? 'İşletmeniz';
        return "İşletmeniz Pasife Alındı - {$businessName}";
    }
    
    public function getTemplatePath(): string {
        return 'business_deactivated.php';
    }
    
    public function getTemplateVariables(): array {
        $firstName = $this->data['first_name'] ?? '';
        $lastName = $this->data['last_name'] ?? '';
        $fullName = trim($firstName . ' ' . $lastName);
        if (empty($fullName)) {
            $fullName = $firstName ?: $lastName ?: 'Değerli Kullanıcı';
        }
        
        $qrStatusLabels = [
            'menu_only' => 'Sadece Menü Görüntüleme',
            'passive' => 'Tamamen Kapalı',
            'active' => 'Aktif',
        ];
        
        $qrMenuStatus = $this->data['qr_menu_status'] ?? 'passive';
        
        return [
            'fullName' => $fullName,
            'businessName' => $this->data['business_name'] ?? 'İşletmeniz',
            'qrMenuStatus' => $qrMenuStatus,
            'qrMenuStatusLabel' => $qrStatusLabels[$qrMenuStatus] ?? $qrMenuStatus,
            'deactivatedAt' => $this->data['deactivated_at'] ?? date('d.m.Y H:i'),
            'baseUrl' => defined('BASE_URL') ? BASE_URL : 'https://qordy.com',
        ];
    }
    
    public function validate(): bool {
        $email = $this->getRecipientEmail();
        return !empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL);
    }
    
    public function getRecipientEmail(): ?string {
        $email = $this->data['email'] ?? '';
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }
    
    protected function getEmailTitle(): string {
        return 'İşletme Pasife Alındı';
    }
}
