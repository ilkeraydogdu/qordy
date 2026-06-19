<?php
namespace App\Services\Email\EmailType;

use App\Services\SystemSettingsService;

/**
 * Reservation Confirmation Email Type
 */
class ReservationConfirmationEmail extends AbstractEmailType {
    
    /**
     * Get email subject
     * @return string
     */
    public function getSubject(): string {
        $date = $this->data['date'] ?? '';
        $time = $this->data['time'] ?? '';
        return "Rezervasyon Onayı - {$date} {$time}";
    }
    
    /**
     * Get template path
     * @return string
     */
    public function getTemplatePath(): string {
        return 'reservation_confirmation.php';
    }
    
    /**
     * Get template variables
     * @return array
     */
    public function getTemplateVariables(): array {
        return [
            'customerName' => $this->data['customer_name'] ?? 'Sayın Müşterimiz',
            'date' => $this->data['date'] ?? '',
            'time' => $this->data['time'] ?? '',
            'guests' => $this->data['guests'] ?? $this->data['guest_count'] ?? 1,
            'tableName' => $this->data['table_name'] ?? 'Belirlenmedi',
            'notes' => $this->data['notes'] ?? '',
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
        $email = $this->data['customer_email'] ?? $this->data['contact'] ?? '';
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
        return 'Rezervasyon Onayı';
    }
}

