<?php
namespace App\Services;

use App\Core\AI\AIServiceManager;

/**
 * AI Service Helper
 * Kolay kullanım için wrapper servis
 * MVC yapısına uygun, merkezi AI yönetimi
 */
class AIService {
    private static $manager = null;
    
    /**
     * AI Service Manager instance al
     */
    private static function getManager(): AIServiceManager {
        if (self::$manager === null) {
            self::$manager = AIServiceManager::getInstance();
        }
        return self::$manager;
    }
    
    /**
     * AI servisi kullanılabilir mi?
     */
    public static function isAvailable(): bool {
        return self::getManager()->isAvailable();
    }
    
    /**
     * Metin çevirisi
     * @param string $text Çevrilecek metin
     * @param string $targetLanguage Hedef dil (en, de, fr, vb.)
     * @param string $sourceLanguage Kaynak dil (varsayılan: tr)
     * @return string|null Çevrilmiş metin
     */
    public static function translateText(string $text, string $targetLanguage = 'en', string $sourceLanguage = 'tr'): ?string {
        return self::getManager()->translateText($text, $targetLanguage, $sourceLanguage);
    }
    
    /**
     * Menü öğesi çevirisi
     * @param array $turkishContent Türkçe içerik
     * @param string $targetLanguage Hedef dil
     * @return array Çevrilmiş içerik
     */
    public static function translateMenuItem(array $turkishContent, string $targetLanguage): array {
        return self::getManager()->translateMenuItem($turkishContent, $targetLanguage);
    }
    
    /**
     * Menü açıklaması oluştur
     * @param string $dishName Yemek adı
     * @param string $ingredients Malzemeler
     * @return string Oluşturulmuş açıklama
     */
    public static function generateMenuDescription(string $dishName, string $ingredients = ''): string {
        return self::getManager()->generateMenuDescription($dishName, $ingredients);
    }
    
    /**
     * SEO içeriği oluştur
     * @param string $name Ürün adı
     * @param string $description Açıklama
     * @param string $categoryName Kategori adı
     * @param string $language Dil kodu
     * @return array SEO içeriği
     */
    public static function generateSEOContent(string $name, string $description, string $categoryName, string $language): array {
        return self::getManager()->generateSEOContent($name, $description, $categoryName, $language);
    }
    
    /**
     * Restoran performans analizi
     * @param array $data Analiz verileri
     * @return string AI analizi
     */
    public static function analyzeRestaurantPerformance(array $data): string {
        return self::getManager()->analyzeRestaurantPerformance($data);
    }
    
    /**
     * Tüm diller için çeviri oluştur
     * @param array $turkishContent Türkçe içerik
     * @param array $supportedLanguages Desteklenen diller
     * @param string $categoryName Kategori adı (SEO için)
     * @return array Tüm diller için çeviriler
     */
    public static function generateAllTranslations(array $turkishContent, array $supportedLanguages, string $categoryName = ''): array {
        $translations = [];
        $defaultLang = $supportedLanguages[0] ?? 'tr';
        
        foreach ($supportedLanguages as $lang) {
            if ($lang === $defaultLang) {
                continue; // Türkçe'yi atla
            }
            
            $translated = self::translateMenuItem($turkishContent, $lang);
            
            if (!empty($translated)) {
                $translations[$lang] = [
                    'name' => $translated['name'] ?? '',
                    'description' => $translated['description'] ?? '',
                    'ingredients' => $translated['ingredients'] ?? [],
                    'extras' => $translated['extras'] ?? []
                ];
                
                // SEO içeriği oluştur
                $seoContent = self::generateSEOContent(
                    $translated['name'] ?? $turkishContent['name'],
                    $translated['description'] ?? $turkishContent['description'] ?? '',
                    $categoryName,
                    $lang
                );
                
                $translations[$lang]['meta_title'] = $seoContent['meta_title'] ?? '';
                $translations[$lang]['meta_description'] = $seoContent['meta_description'] ?? '';
                $translations[$lang]['meta_keywords'] = $seoContent['meta_keywords'] ?? '';
            }
        }
        
        return $translations;
    }
}

