<?php
namespace App\Services\Email\EmailType;

use App\Services\SystemSettingsService;

/**
 * Abstract Email Type
 * Base class for all email types
 */
abstract class AbstractEmailType {
    protected $settingsService;
    protected $data;
    
    public function __construct(SystemSettingsService $settingsService, array $data = []) {
        $this->settingsService = $settingsService;
        $this->data = $data;
    }
    
    /**
     * Get email subject
     * @return string
     */
    abstract public function getSubject(): string;
    
    /**
     * Get email template path (relative to app/views/emails/)
     * @return string
     */
    abstract public function getTemplatePath(): string;
    
    /**
     * Get template variables
     * @return array
     */
    abstract public function getTemplateVariables(): array;
    
    /**
     * Validate email data
     * @return bool
     */
    abstract public function validate(): bool;
    
    /**
     * Get recipient email address
     * @return string|null
     */
    abstract public function getRecipientEmail(): ?string;
    
    /**
     * Render email template
     * @return string HTML content
     */
    public function render(): string {
        $variables = $this->getTemplateVariables();
        $templatePath = $this->getTemplatePath();
        
        // Add layout variables (central settings injection)
        $variables['siteName'] = $this->settingsService->getSetting('site_name', 'Qordy');
        $variables['restaurantPhone'] = $this->settingsService->getSetting('restaurant_phone', '');
        $variables['restaurantAddress'] = $this->settingsService->getSetting('restaurant_address', '');
        // Tüm mail şablonlarının paylaştığı iletişim bilgileri — settings'ten gelir,
        // yoksa marka standart değerlerine düşer. Hardcoded string yerine templatelar
        // $supportEmail / $supportPhone kullansın.
        $variables['supportEmail'] = $this->settingsService->getSetting('support_email', 'destek@qordy.com');
        $variables['supportPhone'] = $this->settingsService->getSetting('support_phone', '0850 309 32 53');
        // tel: link için boşluk/ayraç temizlenmiş E.164-benzeri format (Türkiye +90 varsayımı).
        $digits = preg_replace('/[^0-9]/', '', (string)$variables['supportPhone']);
        if ($digits !== '' && $digits[0] === '0') { $digits = '9' . $digits; }
        elseif ($digits !== '' && substr($digits, 0, 2) !== '90') { $digits = '90' . $digits; }
        $variables['supportPhoneE164'] = '+' . $digits;
        $variables['emailTitle'] = $this->getEmailTitle();
        
        // Extract template content
        ob_start();
        extract($variables);
        $templateFullPath = __DIR__ . '/../../../views/emails/' . $templatePath;
        if (file_exists($templateFullPath)) {
            include $templateFullPath;
        } else {
            echo "Template not found: {$templateFullPath}";
        }
        $content = ob_get_clean();
        
        // Wrap in layout
        $layoutPath = __DIR__ . '/../../../views/emails/layouts/email_layout.php';
        if (file_exists($layoutPath)) {
            ob_start();
            $variables['content'] = $content;
            extract($variables);
            include $layoutPath;
            return ob_get_clean();
        }
        
        return $content;
    }
    
    /**
     * Get email title for layout
     * @return string
     */
    protected function getEmailTitle(): string {
        return 'Email';
    }
    
    /**
     * Extract email from contact field if needed
     * @param string $contact
     * @return string|null
     */
    protected function extractEmail(string $contact): ?string {
        if (filter_var($contact, FILTER_VALIDATE_EMAIL)) {
            return $contact;
        }
        
        if (preg_match('/[\w\.-]+@[\w\.-]+\.\w+/', $contact, $matches)) {
            return $matches[0];
        }
        
        return null;
    }
}

