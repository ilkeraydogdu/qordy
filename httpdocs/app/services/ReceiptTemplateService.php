<?php
namespace App\Services;

use App\Core\BaseService;
use App\Repositories\ReceiptTemplateRepository;

class ReceiptTemplateService extends BaseService {
    public function __construct(ReceiptTemplateRepository $repository) {
        parent::__construct($repository);
    }
    
    public function getAllTemplates(?string $businessId = null) {
        if ($businessId !== null) {
            return $this->repository->getByBusinessId($businessId);
        }
        return $this->repository->getAll();
    }
    
    public function getTemplateById($templateId) {
        return $this->repository->findById($templateId);
    }
    
    public function getTemplateByBusinessId(?string $businessId) {
        return $this->repository->getByBusinessId($businessId);
    }
    
    public function getDefaultTemplate(?string $businessId = null) {
        $template = $this->repository->getDefault($businessId);
        if (!$template) {
            // Return system default template
            return $this->getSystemDefaultTemplate();
        }
        return $template;
    }
    
    public function createTemplate($templateData) {
        return $this->repository->create($templateData);
    }
    
    public function updateTemplate($templateId, $templateData) {
        return $this->repository->update($templateId, $templateData);
    }
    
    public function deleteTemplate($templateId) {
        return $this->repository->delete($templateId);
    }
    
    public function setAsDefault($templateId, ?string $businessId = null) {
        return $this->repository->setAsDefault($templateId, $businessId);
    }
    
    private function getSystemDefaultTemplate() {
        // Xprinter XP-Q805K için ESC/POS formatında varsayılan şablon
        // 80mm thermal printer - 48 karakter genişlik
        return [
            'template_id' => 'default',
            'template_name' => 'Varsayılan Fiş Şablonu (Xprinter XP-Q805K)',
            'template_content' => "\x1B\x40" . // Initialize printer
                "\x1B\x61\x01" . // Center align
                "\x1D\x21\x11" . // Double width + height
                "{{business_name}}\n" .
                "\x1D\x21\x00" . // Normal font
                "\x1B\x64\x01" . // Line feed
                "{{business_address}}\n" .
                "{{business_phone}}\n" .
                "\x1B\x64\x01" . // Line feed
                "--------------------------------\n" .
                "\x1B\x61\x00" . // Left align
                "Fiş No: {{receipt_number}}\n" .
                "Sipariş: {{order_id}}\n" .
                "Masa: {{table_name}}\n" .
                "Tarih: {{order_date}}\n" .
                "--------------------------------\n" .
                str_pad("Ürün", 20, ' ', STR_PAD_RIGHT) . str_pad("Adet", 5, ' ', STR_PAD_LEFT) . str_pad("Tutar", 15, ' ', STR_PAD_LEFT) . "\n" .
                "--------------------------------\n" .
                "{{items}}" .
                "--------------------------------\n" .
                str_pad("Ara Toplam:", 28, ' ', STR_PAD_RIGHT) . str_pad("{{subtotal}}", 18, ' ', STR_PAD_LEFT) . "\n" .
                str_pad("Servis Ücreti:", 28, ' ', STR_PAD_RIGHT) . str_pad("{{service_charge}}", 18, ' ', STR_PAD_LEFT) . "\n" .
                str_pad("KDV:", 28, ' ', STR_PAD_RIGHT) . str_pad("{{tax_amount}}", 18, ' ', STR_PAD_LEFT) . "\n" .
                str_pad("İndirim:", 28, ' ', STR_PAD_RIGHT) . str_pad("{{discount_amount}}", 18, ' ', STR_PAD_LEFT) . "\n" .
                "================================\n" .
                "\x1B\x45\x01" . // Bold
                str_pad("TOPLAM:", 28, ' ', STR_PAD_RIGHT) . str_pad("{{total_amount}}", 18, ' ', STR_PAD_LEFT) . "\n" .
                "\x1B\x45\x00" . // Normal
                "================================\n" .
                "\x1B\x64\x01" . // Line feed
                "Ödeme: {{payment_method}}\n" .
                "\x1B\x64\x02" . // 2 line feeds
                "\x1B\x61\x01" . // Center align
                "{{footer_text}}\n" .
                "Teşekkür Ederiz!\n" .
                "\x1B\x64\x03" . // 3 line feeds
                "\x1D\x56\x00", // Cut paper
            'is_default' => 1
        ];
    }
    
    /**
     * Format template content for Xprinter XP-Q805K
     * Converts template variables to actual values
     * @param string $templateContent Template content with variables
     * @param array $data Data to replace variables
     * @return string Formatted receipt content
     */
    public function formatTemplateForXprinter(string $templateContent, array $data): string {
        $output = $templateContent;
        
        // Replace all template variables
        foreach ($data as $key => $value) {
            $output = str_replace('{{' . $key . '}}', $value, $output);
        }
        
        return $output;
    }
    
    /**
     * Validate ESC/POS template format
     * @param string $templateContent Template content to validate
     * @return array Validation result with 'valid' boolean and 'errors' array
     */
    public function validateTemplate(string $templateContent): array {
        $errors = [];
        
        // Check for required variables
        $requiredVars = ['business_name', 'receipt_number', 'order_id', 'items', 'total_amount'];
        foreach ($requiredVars as $var) {
            if (strpos($templateContent, '{{' . $var . '}}') === false) {
                $errors[] = "Gerekli değişken eksik: {{$var}}";
            }
        }
        
        // Check for invalid ESC/POS commands (basic validation)
        // This is a simple check - more complex validation can be added
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}

