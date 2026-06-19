<?php
namespace App\Services;

use App\Models\ContactForm;
use App\Core\DependencyFactory;
use App\Core\SecurityFirewall;
use App\Core\Validators\RequestValidator;

class ContactFormService {
    private $contactFormModel;
    private $securityFirewall;
    private $validator;
    
    public function __construct() {
        $this->contactFormModel = new ContactForm();
        $this->securityFirewall = new SecurityFirewall([
            'rate_limits' => [
                'contact_form' => [
                    'max_requests' => 5,
                    'window' => 3600 // 1 hour
                ]
            ]
        ]);
        $this->validator = new RequestValidator();
    }
    
    /**
     * Submit contact form with security validation
     * @param array $data Form data
     * @return array Result with success status and message
     */
    public function submitForm(array $data): array {
        try {
            // Ensure table exists
            $this->ensureTableExists();
            
            // Get client IP
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            
            // Rate limiting check
            if (!$this->securityFirewall->checkRateLimit($ipAddress, 'contact_form')) {
                return [
                    'success' => false,
                    'message' => 'Çok fazla istek gönderdiniz. Lütfen daha sonra tekrar deneyin.'
                ];
            }
            
            // IP blocking check
            if ($this->securityFirewall->isIPBlocked($ipAddress)) {
                return [
                    'success' => false,
                    'message' => 'Erişim engellendi.'
                ];
            }
            
            // Validate data
            $isValid = $this->validator->validate($data, 'contact_form');
            $errors = $this->validator->getErrors();
            
            if (!$isValid) {
                // Get first error message if available
                $firstError = $this->validator->getFirstError();
                $errorMessage = $firstError ?: 'Lütfen tüm gerekli alanları doğru şekilde doldurun.';
                
                return [
                    'success' => false,
                    'message' => $errorMessage,
                    'errors' => $errors
                ];
            }
            
            // Get validated and sanitized data from validator
            $sanitizedData = $this->validator->getValidatedData();
            
            // Prepare data for database
            $formData = [
                'contact_id' => uniqid('contact_', true),
                'full_name' => $sanitizedData['full_name'] ?? '',
                'email' => $sanitizedData['email'] ?? '',
                'phone' => $sanitizedData['phone'] ?? null,
                'company_name' => $sanitizedData['company_name'] ?? null,
                'message' => $sanitizedData['message'] ?? null,
                'status' => 'new',
                'ip_address' => $ipAddress,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ];
            
            // Save to database
            $result = $this->contactFormModel->create($formData);
            
            if ($result) {
                // Send confirmation email to user
                try {
                    $emailService = new \App\Services\EmailService();
                    $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
                    $emailType = new \App\Services\Email\EmailType\ContactFormConfirmationEmail(
                        $settingsService,
                        $formData
                    );
                    $emailService->sendEmailType($emailType);
                } catch (\Exception $e) {
                    // Log error but don't fail form submission
                    \App\Core\Logger::error('Failed to send contact form confirmation email', [
                        'error' => $e->getMessage(),
                        'form_data' => $formData
                    ]);
                }
                
                // Send notification to admin (optional - can be implemented later)
                // $this->notifyAdmin($formData);
                
                return [
                    'success' => true,
                    'message' => 'Mesajınız başarıyla gönderildi. En kısa sürede sizinle iletişime geçeceğiz.'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Bir hata oluştu. Lütfen daha sonra tekrar deneyin.'
                ];
            }
            
        } catch (\Exception $e) {
            \App\Core\Logger::error('Contact form submission error', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            
            return [
                'success' => false,
                'message' => 'Bir hata oluştu. Lütfen daha sonra tekrar deneyin.'
            ];
        }
    }
    
    /**
     * Get all contact forms
     * @return array
     */
    public function getAll(): array {
        return $this->contactFormModel->getAll();
    }
    
    /**
     * Get contact form by ID
     * @param string $contactId
     * @return array|null
     */
    public function getById(string $contactId): ?array {
        return $this->contactFormModel->getById($contactId);
    }
    
    /**
     * Get contact forms by status
     * @param string $status
     * @return array
     */
    public function getByStatus(string $status): array {
        return $this->contactFormModel->getByStatus($status);
    }
    
    /**
     * Get new contact forms
     * @return array
     */
    public function getNew(): array {
        return $this->contactFormModel->getNew();
    }
    
    /**
     * Update contact form status
     * @param string $contactId
     * @param string $status
     * @param string|null $notes
     * @return bool
     */
    public function updateStatus(string $contactId, string $status, ?string $notes = null): bool {
        return $this->contactFormModel->updateStatus($contactId, $status, $notes);
    }
    
    /**
     * Get recent contact forms
     * @param int $limit
     * @return array
     */
    public function getRecent(int $limit = 10): array {
        return $this->contactFormModel->getRecent($limit);
    }
    
    /**
     * Delete contact form
     * @param string $contactId
     * @return bool
     */
    public function delete(string $contactId): bool {
        return $this->contactFormModel->deleteById($contactId);
    }
    
    /**
     * Send reply email to contact form submitter
     * @param string $contactId
     * @param string $replyMessage
     * @return array Result with success status and message
     */
    public function sendReply(string $contactId, string $replyMessage): array {
        try {
            $contactForm = $this->contactFormModel->getById($contactId);
            
            if (!$contactForm) {
                return [
                    'success' => false,
                    'message' => 'İletişim formu bulunamadı'
                ];
            }
            
            // Send email
            $emailService = new \App\Services\EmailService();
            $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
            
            // Prepare email content
            $subject = 'Qordy - İletişim Formunuza Yanıt';
            $body = $this->prepareReplyEmailBody($contactForm, $replyMessage, $settingsService);
            
            $result = $emailService->sendEmail(
                $contactForm['email'],
                $subject,
                $body
            );
            
            if ($result) {
                // Update status to contacted if it's new
                if ($contactForm['status'] === 'new') {
                    $this->updateStatus($contactId, 'contacted', 'Yanıt e-postası gönderildi: ' . date('Y-m-d H:i:s'));
                }
                
                return [
                    'success' => true,
                    'message' => 'Yanıt e-postası başarıyla gönderildi'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'E-posta gönderilirken bir hata oluştu'
                ];
            }
        } catch (\Exception $e) {
            \App\Core\Logger::error('Contact form reply email error', [
                'error' => $e->getMessage(),
                'contact_id' => $contactId
            ]);
            
            return [
                'success' => false,
                'message' => 'Bir hata oluştu: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Prepare reply email body HTML
     * @param array $contactForm
     * @param string $replyMessage
     * @param \App\Services\SystemSettingsService $settingsService
     * @return string
     */
    private function prepareReplyEmailBody(array $contactForm, string $replyMessage, $settingsService): string {
        $siteName = $settingsService->getSetting('site_name', 'Qordy');
        
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yanıtınız</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #f97316; color: white; padding: 20px; text-align: center; }
        .content { background-color: #f8f9fa; padding: 20px; margin-top: 20px; }
        .message { background-color: white; padding: 20px; margin-top: 20px; border-left: 4px solid #f97316; }
        .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>' . htmlspecialchars($siteName) . '</h1>
        </div>
        <div class="content">
            <p>Sayın ' . htmlspecialchars($contactForm['full_name']) . ',</p>
            <p>İletişim formunuza gönderdiğiniz mesajınıza yanıt vermek istiyoruz:</p>
            <div class="message">
                ' . nl2br(htmlspecialchars($replyMessage)) . '
            </div>
            <p>Başka bir sorunuz varsa, lütfen bizimle iletişime geçmekten çekinmeyin.</p>
            <p>Saygılarımızla,<br>' . htmlspecialchars($siteName) . ' Ekibi</p>
        </div>
        <div class="footer">
            <p>Bu e-posta ' . htmlspecialchars($siteName) . ' tarafından gönderilmiştir.</p>
        </div>
    </div>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Improve text with Gemini AI
     * @param string $text
     * @return array Result with success status and improved text
     */
    public function improveTextWithGemini(string $text): array {
        try {
            if (empty(trim($text))) {
                return [
                    'success' => false,
                    'message' => 'Metin boş olamaz'
                ];
            }
            
            $geminiService = new \App\Services\GeminiService();
            
            if (!$geminiService->isAvailable()) {
                return [
                    'success' => false,
                    'message' => 'Gemini AI servisi şu an kullanılamıyor. Lütfen API anahtarını kontrol edin.'
                ];
            }
            
            $improvedText = $geminiService->improveText($text);
            
            if ($improvedText && !empty(trim($improvedText))) {
                return [
                    'success' => true,
                    'improved_text' => trim($improvedText)
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Metin düzeltilemedi. Lütfen tekrar deneyin.'
                ];
            }
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Gemini text improvement error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            
            return [
                'success' => false,
                'message' => 'Bir hata oluştu: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Ensure contact_forms table exists
     * @return void
     */
    private function ensureTableExists(): void {
        try {
            $db = \App\Core\DependencyFactory::getDatabase();
            $checkTable = $db->query("SHOW TABLES LIKE 'contact_forms'");
            if ($checkTable->rowCount() === 0) {
                $sql = "CREATE TABLE IF NOT EXISTS contact_forms (
                    contact_id VARCHAR(50) PRIMARY KEY,
                    full_name VARCHAR(255) NOT NULL,
                    email VARCHAR(255) NOT NULL,
                    phone VARCHAR(20),
                    company_name VARCHAR(255),
                    message TEXT,
                    status ENUM('new', 'contacted', 'closed') DEFAULT 'new',
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    contacted_at DATETIME NULL,
                    notes TEXT,
                    INDEX idx_email (email),
                    INDEX idx_status (status),
                    INDEX idx_created_at (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
                
                $db->exec($sql);
            }
        } catch (\Exception $e) {
            // Log error but don't fail - table creation will be handled by migration
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Failed to ensure contact_forms table exists', [
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
}
