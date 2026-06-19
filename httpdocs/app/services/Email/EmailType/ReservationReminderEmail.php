<?php
namespace App\Services\Email\EmailType;

use App\Services\SystemSettingsService;

/**
 * Reservation Reminder Email Type
 */
class ReservationReminderEmail extends AbstractEmailType {
    private $hoursBefore;
    
    public function __construct(SystemSettingsService $settingsService, array $data = [], int $hoursBefore = 24) {
        parent::__construct($settingsService, $data);
        $this->hoursBefore = $hoursBefore;
    }
    
    /**
     * Get email subject
     * @return string
     */
    public function getSubject(): string {
        return "Rezervasyon Hatırlatması - {$this->hoursBefore} Saat Kaldı";
    }
    
    /**
     * Get template path
     * @return string
     */
    public function getTemplatePath(): string {
        return 'reservation_reminder.php';
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
            'hoursBefore' => $this->hoursBefore,
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
        return 'Rezervasyon Hatırlatması';
    }
}

