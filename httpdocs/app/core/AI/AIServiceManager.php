<?php
namespace App\Core\AI;

use App\Core\AI\Providers\GeminiAIProvider;

/**
 * AI Service Manager
 * Merkezi AI servis yönetimi
 * Farklı AI provider'ları yönetir ve kullanır
 */
class AIServiceManager {
    private static $instance = null;
    private $providers = [];
    private $defaultProvider = 'gemini';
    
    private function __construct() {
        // Singleton pattern
    }
    
    /**
     * Singleton instance al
     */
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Provider kaydet
     */
    public function registerProvider(string $name, AIServiceInterface $provider): void {
        $this->providers[$name] = $provider;
    }
    
    /**
     * Varsayılan provider'ı ayarla
     */
    public function setDefaultProvider(string $name): void {
        if (isset($this->providers[$name])) {
            $this->defaultProvider = $name;
        }
    }
    
    /**
     * Provider al
     */
    public function getProvider(?string $name = null): ?AIServiceInterface {
        $name = $name ?? $this->defaultProvider;
        
        if (!isset($this->providers[$name])) {
            // Lazy load providers
            $this->loadProvider($name);
        }
        
        return $this->providers[$name] ?? null;
    }
    
    /**
     * Provider yükle
     */
    private function loadProvider(string $name): void {
        switch ($name) {
            case 'gemini':
                $this->providers['gemini'] = new GeminiAIProvider();
                break;
            // Gelecekte başka provider'lar eklenebilir
            // case 'openai':
            //     $this->providers['openai'] = new OpenAIProvider();
            //     break;
        }
    }
    
    /**
     * Kullanılabilir provider var mı?
     */
    public function isAvailable(?string $providerName = null): bool {
        $provider = $this->getProvider($providerName);
        return $provider !== null && $provider->isAvailable();
    }
    
    /**
     * Basit metin çevirisi (kolay kullanım)
     */
    public function translateText(string $text, string $targetLanguage = 'en', string $sourceLanguage = 'tr', ?string $providerName = null): ?string {
        $provider = $this->getProvider($providerName);
        if (!$provider || !$provider->isAvailable()) {
            return null;
        }
        
        return $provider->translateText($text, $targetLanguage, $sourceLanguage);
    }
    
    /**
     * Menü öğesi çevirisi (kolay kullanım)
     */
    public function translateMenuItem(array $turkishContent, string $targetLanguage, ?string $providerName = null): array {
        $provider = $this->getProvider($providerName);
        if (!$provider || !$provider->isAvailable()) {
            return [];
        }
        
        return $provider->translateMenuItem($turkishContent, $targetLanguage);
    }
    
    /**
     * Menü açıklaması oluştur (kolay kullanım)
     */
    public function generateMenuDescription(string $dishName, string $ingredients = '', ?string $providerName = null): string {
        $provider = $this->getProvider($providerName);
        if (!$provider || !$provider->isAvailable()) {
            return "Şefin özel tarifi ile hazırlanmış lezzet.";
        }
        
        return $provider->generateMenuDescription($dishName, $ingredients);
    }
    
    /**
     * SEO içeriği oluştur (kolay kullanım)
     */
    public function generateSEOContent(string $name, string $description, string $categoryName, string $language, ?string $providerName = null): array {
        $provider = $this->getProvider($providerName);
        if (!$provider || !$provider->isAvailable()) {
            return [
                'meta_title' => $name,
                'meta_description' => $description,
                'meta_keywords' => ''
            ];
        }
        
        return $provider->generateSEOContent($name, $description, $categoryName, $language);
    }
    
    /**
     * Restoran performans analizi (kolay kullanım)
     */
    public function analyzeRestaurantPerformance(array $data, ?string $providerName = null): string {
        $provider = $this->getProvider($providerName);
        if (!$provider || !$provider->isAvailable()) {
            return "AI Analizi şu an yapılamıyor. Lütfen AI yapılandırmasını kontrol edin.";
        }
        
        return $provider->analyzeRestaurantPerformance($data);
    }
}

