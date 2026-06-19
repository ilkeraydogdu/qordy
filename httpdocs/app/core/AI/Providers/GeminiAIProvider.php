<?php
namespace App\Core\AI\Providers;

use App\Core\AI\AIServiceInterface;
use App\Services\GeminiService;

/**
 * Gemini AI Provider
 * GeminiService'i AIServiceInterface'e adapte eder
 */
class GeminiAIProvider implements AIServiceInterface {
    private $geminiService;
    
    public function __construct() {
        $this->geminiService = \App\Core\DependencyFactory::getGeminiService();
    }
    
    /**
     * Servisin kullanılabilir olup olmadığını kontrol et
     */
    public function isAvailable(): bool {
        return $this->geminiService !== null && $this->geminiService->isAvailable();
    }
    
    /**
     * Basit metin çevirisi
     */
    public function translateText(string $text, string $targetLanguage = 'en', string $sourceLanguage = 'tr'): ?string {
        if (!$this->isAvailable() || empty($text)) {
            return null;
        }
        
        return $this->geminiService->translateText($text, $targetLanguage);
    }
    
    /**
     * Menü öğesi çevirisi
     */
    public function translateMenuItem(array $turkishContent, string $targetLanguage): array {
        if (!$this->isAvailable()) {
            return [];
        }
        
        return $this->geminiService->translateMenuItem($turkishContent, $targetLanguage);
    }
    
    /**
     * Menü açıklaması oluştur
     */
    public function generateMenuDescription(string $dishName, string $ingredients = ''): string {
        if (!$this->isAvailable()) {
            return "Şefin özel tarifi ile hazırlanmış lezzet.";
        }
        
        return $this->geminiService->generateMenuDescription($dishName, $ingredients);
    }
    
    /**
     * SEO içeriği oluştur
     */
    public function generateSEOContent(string $name, string $description, string $categoryName, string $language): array {
        if (!$this->isAvailable()) {
            return [
                'meta_title' => $name,
                'meta_description' => $description,
                'meta_keywords' => ''
            ];
        }
        
        return $this->geminiService->generateSEOContent($name, $description, $categoryName, $language);
    }
    
    /**
     * Restoran performans analizi
     */
    public function analyzeRestaurantPerformance(array $data): string {
        if (!$this->isAvailable()) {
            return "AI Analizi şu an yapılamıyor. Lütfen GEMINI_API_KEY yapılandırmasını kontrol edin.";
        }
        
        return $this->geminiService->analyzeRestaurantPerformance($data);
    }
}

