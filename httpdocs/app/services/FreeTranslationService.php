<?php
namespace App\Services;

/**
 * Free Translation Service
 * Ücretsiz çeviri API'leri kullanarak çeviri yapar
 * MyMemory Translation API kullanır (ücretsiz, API key gerektirmez)
 */
class FreeTranslationService {
    
    /**
     * MyMemory Translation API endpoint
     */
    private const MYMEMORY_API = 'https://api.mymemory.translated.net/get';
    
    /**
     * Dil kodlarını MyMemory formatına çevir
     */
    private function getLanguageCode(string $lang): string {
        $langMap = [
            'tr' => 'tr',
            'en' => 'en',
            'de' => 'de',
            'fr' => 'fr',
            'es' => 'es',
            'it' => 'it',
            'ru' => 'ru',
            'ar' => 'ar',
            'zh' => 'zh',
            'ja' => 'ja',
            'ko' => 'ko',
            'pt' => 'pt',
            'nl' => 'nl',
            'pl' => 'pl',
            'sv' => 'sv',
            'da' => 'da',
            'fi' => 'fi',
            'no' => 'no',
            'cs' => 'cs',
            'hu' => 'hu',
            'ro' => 'ro',
            'bg' => 'bg',
            'hr' => 'hr',
            'sk' => 'sk',
            'sl' => 'sl',
            'el' => 'el',
            'he' => 'he',
            'th' => 'th',
            'vi' => 'vi',
            'id' => 'id',
            'ms' => 'ms',
            'hi' => 'hi',
            'uk' => 'uk'
        ];
        
        return $langMap[strtolower($lang)] ?? 'en';
    }
    
    /**
     * Metin çevirisi yap
     * @param string $text Çevrilecek metin
     * @param string $targetLanguage Hedef dil (en, de, fr, vb.)
     * @param string $sourceLanguage Kaynak dil (varsayılan: tr)
     * @return string|null Çevrilmiş metin
     */
    public function translateText(string $text, string $targetLanguage = 'en', string $sourceLanguage = 'tr'): ?string {
        if (empty(trim($text))) {
            return null;
        }
        
        // Aynı dil ise çeviri yapma
        if (strtolower($sourceLanguage) === strtolower($targetLanguage)) {
            return $text;
        }
        
        // Load helper functions
        require_once __DIR__ . '/../helpers/text_normalization.php';
        require_once __DIR__ . '/../helpers/text_formatting.php';
        
        // Step 1: Check menu dictionary first (for English translations)
        if ($targetLanguage === 'en' && $sourceLanguage === 'tr') {
            $dictionaryService = new \App\Services\MenuDictionaryService();
            $dictionaryTranslation = $dictionaryService->getTranslation($text, $targetLanguage);
            
            if ($dictionaryTranslation !== null) {
                // Apply title case formatting
                return formatMenuTitleCase($dictionaryTranslation);
            }
        }
        
        $sourceLang = $this->getLanguageCode($sourceLanguage);
        $targetLang = $this->getLanguageCode($targetLanguage);
        
        try {
            // MyMemory API'ye istek at
            $url = self::MYMEMORY_API . '?' . http_build_query([
                'q' => $text,
                'langpair' => $sourceLang . '|' . $targetLang
            ]);
            
            // cURL ile istek at
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; RestaurantApp/1.0)');
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                error_log("FreeTranslationService cURL Error: " . $curlError);
                return null;
            }
            
            if ($httpCode !== 200) {
                error_log("FreeTranslationService HTTP Error: " . $httpCode);
                return null;
            }
            
            $data = json_decode($response, true);
            
            if (!$data || !isset($data['responseData'])) {
                error_log("FreeTranslationService: Invalid response format");
                return null;
            }
            
            // Response kontrolü
            if (isset($data['responseStatus']) && $data['responseStatus'] !== 200) {
                error_log("FreeTranslationService API Error: " . ($data['responseStatus'] ?? 'Unknown'));
                return null;
            }
            
            $translatedText = $data['responseData']['translatedText'] ?? null;
            
            if ($translatedText && !empty(trim($translatedText))) {
                // MyMemory bazen HTML entity'leri döndürür, decode et
                $translatedText = html_entity_decode($translatedText, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $translatedText = trim($translatedText);
                
                // Apply title case formatting for English translations
                if ($targetLanguage === 'en') {
                    $translatedText = formatMenuTitleCase($translatedText);
                }
                
                return $translatedText;
            }
            
            return null;
            
        } catch (\Exception $e) {
            error_log("FreeTranslationService Exception: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Servisin kullanılabilir olup olmadığını kontrol et
     * @return bool
     */
    public function isAvailable(): bool {
        // cURL kontrolü
        if (!function_exists('curl_init')) {
            return false;
        }
        
        // cURL kullanılabilir olduğu için servis kullanılabilir
        // Test çevirisi yapmaya gerek yok, sadece cURL var mı kontrol et
        return true;
    }
}

