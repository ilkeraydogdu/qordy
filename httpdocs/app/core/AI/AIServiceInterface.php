<?php
namespace App\Core\AI;

/**
 * AI Service Interface
 * Tüm AI provider'ların uyması gereken interface
 */
interface AIServiceInterface {
    /**
     * Servisin kullanılabilir olup olmadığını kontrol et
     * @return bool
     */
    public function isAvailable(): bool;
    
    /**
     * Basit metin çevirisi
     * @param string $text Çevrilecek metin
     * @param string $targetLanguage Hedef dil kodu (en, de, fr, vb.)
     * @param string $sourceLanguage Kaynak dil kodu (varsayılan: tr)
     * @return string|null Çevrilmiş metin
     */
    public function translateText(string $text, string $targetLanguage = 'en', string $sourceLanguage = 'tr'): ?string;
    
    /**
     * Menü öğesi çevirisi (isim, açıklama, malzemeler, ekstralar)
     * @param array $turkishContent Türkçe içerik ['name' => '', 'description' => '', 'ingredients' => [], 'extras' => []]
     * @param string $targetLanguage Hedef dil kodu
     * @return array Çevrilmiş içerik
     */
    public function translateMenuItem(array $turkishContent, string $targetLanguage): array;
    
    /**
     * Menü açıklaması oluştur
     * @param string $dishName Yemek adı
     * @param string $ingredients Malzemeler (virgülle ayrılmış)
     * @return string Oluşturulmuş açıklama
     */
    public function generateMenuDescription(string $dishName, string $ingredients = ''): string;
    
    /**
     * SEO içeriği oluştur
     * @param string $name Ürün adı
     * @param string $description Açıklama
     * @param string $categoryName Kategori adı
     * @param string $language Dil kodu
     * @return array ['meta_title' => '', 'meta_description' => '', 'meta_keywords' => '']
     */
    public function generateSEOContent(string $name, string $description, string $categoryName, string $language): array;
    
    /**
     * Restoran performans analizi
     * @param array $data Analiz verileri
     * @return string AI tarafından oluşturulmuş analiz
     */
    public function analyzeRestaurantPerformance(array $data): string;
}

