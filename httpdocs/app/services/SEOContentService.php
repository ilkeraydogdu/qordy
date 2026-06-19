<?php
namespace App\Services;

require_once __DIR__ . '/GeminiService.php';

/**
 * SEO Content Service
 * AI-powered SEO content generation and optimization using Gemini
 */
class SEOContentService {
    private $geminiService;
    private $cacheDir;
    private $cacheLifetime = 86400; // 24 hours
    
    public function __construct(?GeminiService $geminiService = null) {
        if ($geminiService !== null) {
            $this->geminiService = $geminiService;
        } else {
            try {
                require_once __DIR__ . '/../core/DependencyFactory.php';
                $this->geminiService = \App\Core\DependencyFactory::getGeminiService();
            } catch (\Exception $e) {
                $this->geminiService = new GeminiService();
            }
        }
        
        $this->cacheDir = __DIR__ . '/../../storage/cache/seo';
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    /**
     * Optimize meta title and description using AI
     * @param string $page Page identifier
     * @param string $currentTitle Current title
     * @param string $currentDescription Current description
     * @param array $keywords Target keywords
     * @param string $lang Language code
     * @return array Optimized title and description
     */
    public function optimizeMetaTags($page, $currentTitle, $currentDescription, $keywords = [], $lang = 'tr') {
        if (!$this->geminiService || !$this->geminiService->isAvailable()) {
            return [
                'title' => $currentTitle,
                'description' => $currentDescription
            ];
        }
        
        $cacheKey = md5("meta_{$page}_{$lang}_" . implode(',', $keywords));
        $cacheFile = $this->cacheDir . '/' . $cacheKey . '.json';
        
        // Check cache
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $this->cacheLifetime) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if ($cached) {
                return $cached;
            }
        }
        
        try {
            $keywordsStr = !empty($keywords) ? implode(', ', $keywords) : '';
            $langName = $lang === 'tr' ? 'Türkçe' : 'English';
            
            $prompt = "Sen bir SEO uzmanısın. Aşağıdaki bilgileri kullanarak SEO optimizasyonu yap.

Sayfa: {$page}
Mevcut Başlık: {$currentTitle}
Mevcut Açıklama: {$currentDescription}
Hedef Anahtar Kelimeler: {$keywordsStr}
Dil: {$langName}

Görevler:
1. Meta başlık (title) oluştur: Maksimum 60 karakter, anahtar kelimeleri içermeli, çekici olmalı
2. Meta açıklama (description) oluştur: Maksimum 160 karakter, anahtar kelimeleri içermeli, kullanıcıyı tıklamaya teşvik etmeli

Sadece JSON formatında döndür:
{
    \"title\": \"optimize edilmiş başlık\",
    \"description\": \"optimize edilmiş açıklama\"
}";
            
            $response = $this->geminiService->callGeminiAPI('gemini-2.5-flash', $prompt);
            
            if ($response) {
                // Try to extract JSON from response
                $jsonMatch = [];
                if (preg_match('/\{[^}]+\}/s', $response, $jsonMatch)) {
                    $optimized = json_decode($jsonMatch[0], true);
                    if ($optimized && isset($optimized['title']) && isset($optimized['description'])) {
                        // Cache the result
                        file_put_contents($cacheFile, json_encode($optimized, JSON_UNESCAPED_UNICODE));
                        return $optimized;
                    }
                }
            }
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("SEOContentService optimizeMetaTags error: " . $e->getMessage());
            }
        }
        
        return [
            'title' => $currentTitle,
            'description' => $currentDescription
        ];
    }
    
    /**
     * Analyze keywords and suggest improvements
     * @param string $content Content to analyze
     * @param string $lang Language code
     * @return array Keyword suggestions
     */
    public function analyzeKeywords($content, $lang = 'tr') {
        if (!$this->geminiService || !$this->geminiService->isAvailable()) {
            return [];
        }
        
        $cacheKey = md5("keywords_" . md5($content) . "_{$lang}");
        $cacheFile = $this->cacheDir . '/keywords_' . $cacheKey . '.json';
        
        // Check cache
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $this->cacheLifetime) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if ($cached) {
                return $cached;
            }
        }
        
        try {
            $langName = $lang === 'tr' ? 'Türkçe' : 'English';
            
            $prompt = "Sen bir SEO uzmanısın. Aşağıdaki içeriği analiz et ve SEO için önemli anahtar kelimeler öner.

İçerik: " . substr($content, 0, 2000) . "
Dil: {$langName}

Görevler:
1. İçerikten çıkarılabilecek anahtar kelimeleri listele (5-10 adet)
2. Her anahtar kelime için önem skoru ver (1-10 arası)
3. İlgili uzun kuyruk anahtar kelimeler öner (3-5 adet)

Sadece JSON formatında döndür:
{
    \"keywords\": [
        {\"keyword\": \"anahtar kelime\", \"score\": 8},
        ...
    ],
    \"longTail\": [\"uzun kuyruk 1\", \"uzun kuyruk 2\", ...]
}";
            
            $response = $this->geminiService->callGeminiAPI('gemini-2.5-flash', $prompt);
            
            if ($response) {
                $jsonMatch = [];
                if (preg_match('/\{[^}]+\}/s', $response, $jsonMatch)) {
                    $analysis = json_decode($jsonMatch[0], true);
                    if ($analysis) {
                        file_put_contents($cacheFile, json_encode($analysis, JSON_UNESCAPED_UNICODE));
                        return $analysis;
                    }
                }
            }
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("SEOContentService analyzeKeywords error: " . $e->getMessage());
            }
        }
        
        return [];
    }
    
    /**
     * Score content quality for SEO
     * @param string $content Content to score
     * @param array $targetKeywords Target keywords
     * @param string $lang Language code
     * @return array Quality score and suggestions
     */
    public function scoreContentQuality($content, $targetKeywords = [], $lang = 'tr') {
        if (!$this->geminiService || !$this->geminiService->isAvailable()) {
            return [
                'score' => 70,
                'suggestions' => []
            ];
        }
        
        $cacheKey = md5("quality_" . md5($content) . "_" . implode(',', $targetKeywords) . "_{$lang}");
        $cacheFile = $this->cacheDir . '/quality_' . $cacheKey . '.json';
        
        // Check cache
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $this->cacheLifetime) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if ($cached) {
                return $cached;
            }
        }
        
        try {
            $keywordsStr = !empty($targetKeywords) ? implode(', ', $targetKeywords) : 'Yok';
            $langName = $lang === 'tr' ? 'Türkçe' : 'English';
            
            $prompt = "Sen bir SEO uzmanısın. Aşağıdaki içeriği SEO açısından değerlendir.

İçerik: " . substr($content, 0, 2000) . "
Hedef Anahtar Kelimeler: {$keywordsStr}
Dil: {$langName}

Görevler:
1. İçeriğe 0-100 arası SEO skoru ver
2. İyileştirme önerileri listele (3-5 adet)
3. Eksik olan SEO elementlerini belirt

Sadece JSON formatında döndür:
{
    \"score\": 85,
    \"suggestions\": [\"öneri 1\", \"öneri 2\", ...],
    \"missing\": [\"eksik element 1\", ...]
}";
            
            $response = $this->geminiService->callGeminiAPI('gemini-2.5-flash', $prompt);
            
            if ($response) {
                $jsonMatch = [];
                if (preg_match('/\{[^}]+\}/s', $response, $jsonMatch)) {
                    $score = json_decode($jsonMatch[0], true);
                    if ($score) {
                        file_put_contents($cacheFile, json_encode($score, JSON_UNESCAPED_UNICODE));
                        return $score;
                    }
                }
            }
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("SEOContentService scoreContentQuality error: " . $e->getMessage());
            }
        }
        
        return [
            'score' => 70,
            'suggestions' => [],
            'missing' => []
        ];
    }
    
    /**
     * Generate SEO-optimized content for a topic
     * @param string $topic Topic to write about
     * @param array $keywords Target keywords
     * @param string $lang Language code
     * @param int $length Target length in words
     * @return string Generated content
     */
    public function generateContent($topic, $keywords = [], $lang = 'tr', $length = 500) {
        if (!$this->geminiService || !$this->geminiService->isAvailable()) {
            return '';
        }
        
        $cacheKey = md5("content_{$topic}_" . implode(',', $keywords) . "_{$lang}_{$length}");
        $cacheFile = $this->cacheDir . '/content_' . $cacheKey . '.txt';
        
        // Check cache
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $this->cacheLifetime) {
            return file_get_contents($cacheFile);
        }
        
        try {
            $keywordsStr = !empty($keywords) ? implode(', ', $keywords) : '';
            $langName = $lang === 'tr' ? 'Türkçe' : 'English';
            
            $prompt = "Sen bir içerik yazarı ve SEO uzmanısın. Aşağıdaki konu hakkında SEO optimizasyonlu içerik yaz.

Konu: {$topic}
Hedef Anahtar Kelimeler: {$keywordsStr}
Hedef Uzunluk: {$length} kelime
Dil: {$langName}

Gereksinimler:
1. İçerik SEO optimizasyonlu olmalı (anahtar kelimeler doğal şekilde kullanılmalı)
2. Okuyucu dostu ve bilgilendirici olmalı
3. Başlıklar (H2, H3) içermeli
4. Anahtar kelimeleri başlıklarda ve ilk paragrafta kullan
5. İçerik değerli bilgiler içermeli

Sadece içeriği döndür, başka açıklama ekleme.";
            
            $response = $this->geminiService->callGeminiAPI('gemini-2.5-pro', $prompt);
            
            if ($response) {
                file_put_contents($cacheFile, $response);
                return $response;
            }
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("SEOContentService generateContent error: " . $e->getMessage());
            }
        }
        
        return '';
    }
    
    /**
     * Analyze competitor content and suggest improvements
     * @param string $competitorContent Competitor's content
     * @param string $ourContent Our content
     * @param string $lang Language code
     * @return array Analysis and suggestions
     */
    public function analyzeCompetitor($competitorContent, $ourContent, $lang = 'tr') {
        if (!$this->geminiService || !$this->geminiService->isAvailable()) {
            return [];
        }
        
        try {
            $langName = $lang === 'tr' ? 'Türkçe' : 'English';
            
            $prompt = "Sen bir SEO uzmanısın. Rakip içeriği analiz et ve bizim içeriğimizi nasıl iyileştirebileceğimizi öner.

Rakip İçerik: " . substr($competitorContent, 0, 1500) . "
Bizim İçerik: " . substr($ourContent, 0, 1500) . "
Dil: {$langName}

Görevler:
1. Rakip içeriğin güçlü yönlerini listele
2. Bizim içeriğimizin eksiklerini belirt
3. İyileştirme önerileri sun (5-7 adet)

Sadece JSON formatında döndür:
{
    \"competitorStrengths\": [\"güçlü yön 1\", ...],
    \"ourWeaknesses\": [\"eksik 1\", ...],
    \"suggestions\": [\"öneri 1\", ...]
}";
            
            $response = $this->geminiService->callGeminiAPI('gemini-2.5-pro', $prompt);
            
            if ($response) {
                $jsonMatch = [];
                if (preg_match('/\{[^}]+\}/s', $response, $jsonMatch)) {
                    return json_decode($jsonMatch[0], true);
                }
            }
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("SEOContentService analyzeCompetitor error: " . $e->getMessage());
            }
        }
        
        return [];
    }
}
