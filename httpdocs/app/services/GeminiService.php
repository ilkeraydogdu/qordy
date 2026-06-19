<?php
namespace App\Services;

/**
 * Gemini AI Service
 * Provides AI-powered features for menu descriptions and restaurant analytics
 */
class GeminiService {
    private $apiKey;
    private $opusmaxApiKey;
    private $baseUrlV1Beta = 'https://generativelanguage.googleapis.com/v1beta/models/';
    private $baseUrlV1 = 'https://generativelanguage.googleapis.com/v1/models/';
    private $cacheDir = __DIR__ . '/../../storage/cache/gemini';
    private $cacheLifetime = 3600; // 1 hour
    private $rateLimitFile = __DIR__ . '/../../storage/rate_limits/gemini.json';
    private $maxRequestsPerMinute = 10;
    private $maxRequestsPerHour = 100;
    
    public function __construct() {
        // Get API key from database (via SystemSettingsService) instead of .env
        try {
            $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
            $this->apiKey = $settingsService->getGeminiApiKey();
            $this->opusmaxApiKey = $settingsService->getOpusmaxApiKey();
        } catch (\Exception $e) {
            // Fallback to constant or empty if database is not available
            $this->apiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
            $this->opusmaxApiKey = '';
        }
        
        // Create cache directory if it doesn't exist
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
        
        // Create rate limit directory if it doesn't exist
        $rateLimitDir = dirname($this->rateLimitFile);
        if (!is_dir($rateLimitDir)) {
            mkdir($rateLimitDir, 0755, true);
        }
        
        // Service is optional. API key validation warnings used to fire on
        // every request (GeminiService is resolved during DI bootstrap), which
        // spammed the log with tens of thousands of lines per day. They are
        // now deferred to the actual call path so the warning surfaces only
        // when AI features are really used.
    }
    
    /**
     * Check if service is available
     * @return bool
     */
    public function isAvailable(): bool {
        $hasGemini = !empty($this->apiKey) && preg_match('/^AIza[0-9A-Za-z_-]{35}$/', $this->apiKey) === 1;
        $hasOpusmax = !empty($this->opusmaxApiKey);
        return $hasGemini || $hasOpusmax;
    }
    
    /**
     * Check rate limit
     * @return bool
     */
    private function checkRateLimit(): bool {
        if (!file_exists($this->rateLimitFile)) {
            $this->updateRateLimit();
            return true;
        }
        
        $data = json_decode(file_get_contents($this->rateLimitFile), true);
        if (!$data) {
            $this->updateRateLimit();
            return true;
        }
        
        $now = time();
        $minuteRequests = array_filter($data['minute'] ?? [], function($time) use ($now) {
            return ($now - $time) < 60;
        });
        $hourRequests = array_filter($data['hour'] ?? [], function($time) use ($now) {
            return ($now - $time) < 3600;
        });
        
        if (count($minuteRequests) >= $this->maxRequestsPerMinute) {
            return false;
        }
        
        if (count($hourRequests) >= $this->maxRequestsPerHour) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Update rate limit tracking
     */
    private function updateRateLimit(): void {
        $data = [
            'minute' => [],
            'hour' => []
        ];
        
        if (file_exists($this->rateLimitFile)) {
            $existing = json_decode(file_get_contents($this->rateLimitFile), true);
            if ($existing) {
                $data = $existing;
            }
        }
        
        $now = time();
        $data['minute'][] = $now;
        $data['hour'][] = $now;
        
        // Clean old entries
        $data['minute'] = array_filter($data['minute'], function($time) use ($now) {
            return ($now - $time) < 60;
        });
        $data['hour'] = array_filter($data['hour'], function($time) use ($now) {
            return ($now - $time) < 3600;
        });
        
        file_put_contents($this->rateLimitFile, json_encode($data));
    }
    
    /**
     * Get cache key for a prompt
     * @param string $prompt
     * @return string
     */
    private function getCacheKey(string $prompt): string {
        return md5($prompt);
    }
    
    /**
     * Get cached response
     * @param string $cacheKey
     * @return string|null
     */
    private function getCached(string $cacheKey): ?string {
        $cacheFile = $this->cacheDir . '/' . $cacheKey . '.cache';
        
        if (!file_exists($cacheFile)) {
            return null;
        }
        
        $cacheData = json_decode(file_get_contents($cacheFile), true);
        if (!$cacheData) {
            return null;
        }
        
        // Check if cache is expired
        if (time() - $cacheData['timestamp'] > $this->cacheLifetime) {
            unlink($cacheFile);
            return null;
        }
        
        return $cacheData['response'];
    }
    
    /**
     * Cache response
     * @param string $cacheKey
     * @param string $response
     */
    private function setCached(string $cacheKey, string $response): void {
        $cacheFile = $this->cacheDir . '/' . $cacheKey . '.cache';
        $cacheData = [
            'timestamp' => time(),
            'response' => $response
        ];
        file_put_contents($cacheFile, json_encode($cacheData));
    }
    
    /**
     * Generate menu description using AI
     * @param string $dishName - Name of the dish
     * @param string $ingredients - Main ingredients or notes
     * @return string - Generated description
     */
    public function generateMenuDescription($dishName, $ingredients = '') {
        if (!$this->isAvailable()) {
            return "Şefin özel tarifi ile hazırlanmış lezzet.";
        }
        
        try {
            $prompt = "Sen lüks ve modern bir restoranın baş aşçısısın.
Aşağıdaki yemek için kısa, iştah açıcı, satış odaklı ve etkileyici bir menü açıklaması yaz.

Yemek Adı: {$dishName}
Ana Malzemeler/Notlar: {$ingredients}

Kurallar:
1. Maksimum 2 cümle olsun.
2. Türk mutfağına uygun, sıcak bir dil kullan.
3. Emojiler kullanma.
4. Sadece açıklamayı döndür.";

            // Check cache first
            $cacheKey = $this->getCacheKey($prompt);
            $cached = $this->getCached($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
            
            // Check rate limit
            if (!$this->checkRateLimit()) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning('GeminiService: Rate limit exceeded');
                }
                return "Şefin özel tarifi ile hazırlanmış lezzet.";
            }
            
            $response = $this->callGeminiAPI('gemini-1.5-flash', $prompt);
            
            if ($response) {
                $response = trim($response);
                // Cache the response
                $this->setCached($cacheKey, $response);
                // Update rate limit
                $this->updateRateLimit();
                return $response;
            }
            
            return "Şefin özel tarifi ile hazırlanmış lezzet.";
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("Gemini API Error: " . $e->getMessage(), [
                    'dish_name' => $dishName,
                    'exception' => get_class($e)
                ]);
            }
            return "Şefin özel tarifi ile hazırlanmış lezzet.";
        }
    }
    
    /**
     * Generate package description using AI
     * @param string $packageName - Name of the package
     * @param array $packageData - Package data (prices, features, etc.)
     * @return string - Generated description
     */
    public function generatePackageDescription($packageName, $packageData = []) {
        if (!$this->isAvailable()) {
            return "Bu paket, işletmenizin ihtiyaçlarına özel olarak tasarlanmış kapsamlı bir çözümdür.";
        }
        
        try {
            $priceInfo = [];
            if (!empty($packageData['price_one_time'])) {
                $priceInfo[] = "Tek seferlik: " . number_format($packageData['price_one_time'], 2) . " ₺";
            }
            if (!empty($packageData['price_monthly'])) {
                $priceInfo[] = "Aylık: " . number_format($packageData['price_monthly'], 2) . " ₺";
            }
            if (!empty($packageData['price_yearly'])) {
                $priceInfo[] = "Yıllık: " . number_format($packageData['price_yearly'], 2) . " ₺";
            }
            $priceText = !empty($priceInfo) ? implode(", ", $priceInfo) : "Fiyatlandırma bilgisi mevcut değil";
            
            $features = [];
            if (!empty($packageData['features'])) {
                if (is_string($packageData['features'])) {
                    $decoded = json_decode($packageData['features'], true);
                    if (is_array($decoded)) {
                        $features = $decoded;
                    }
                } elseif (is_array($packageData['features'])) {
                    $features = $packageData['features'];
                }
            }
            $featuresText = !empty($features) ? implode(", ", $features) : "";
            
            $prompt = "Sen profesyonel bir SaaS ürün pazarlama uzmanısın.
Aşağıdaki paket için müşteri odaklı, çekici ve profesyonel bir paket açıklaması yaz.

Paket Adı: {$packageName}
Fiyatlandırma: {$priceText}" . (!empty($featuresText) ? "\nÖzellikler: {$featuresText}" : "") . "

Kurallar:
1. Maksimum 3-4 cümle olsun.
2. Paketin değerini ve faydalarını vurgula.
3. Profesyonel ve satış odaklı bir dil kullan.
4. Türkçe yaz.
5. Emojiler kullanma.
6. Sadece açıklamayı döndür, başlık veya başka metin ekleme.";

            // Check cache first
            $cacheKey = $this->getCacheKey($prompt);
            $cached = $this->getCached($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
            
            // Check rate limit
            if (!$this->checkRateLimit()) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning('GeminiService: Rate limit exceeded for package description');
                }
                return "Bu paket, işletmenizin ihtiyaçlarına özel olarak tasarlanmış kapsamlı bir çözümdür.";
            }
            
            $response = $this->callGeminiAPI('gemini-1.5-flash', $prompt);
            
            if ($response) {
                $response = trim($response);
                // Cache the response
                $this->setCached($cacheKey, $response);
                // Update rate limit
                $this->updateRateLimit();
                return $response;
            }
            
            return "Bu paket, işletmenizin ihtiyaçlarına özel olarak tasarlanmış kapsamlı bir çözümdür.";
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("Gemini API Error: " . $e->getMessage(), [
                    'package_name' => $packageName,
                    'exception' => get_class($e)
                ]);
            }
            return "Bu paket, işletmenizin ihtiyaçlarına özel olarak tasarlanmış kapsamlı bir çözümdür.";
        }
    }
    
    /**
     * Analyze restaurant performance and provide strategic recommendations
     * @param array $data - Analytics data (revenue, top items, expenses, etc.)
     * @return string - AI-generated insights and recommendations
     */
    public function analyzeRestaurantPerformance($data) {
        if (!$this->isAvailable()) {
            return "AI Analizi şu an yapılamıyor. Lütfen GEMINI_API_KEY yapılandırmasını kontrol edin.";
        }
        
        try {
            $prompt = "Sen profesyonel bir restoran işletme danışmanısın. 
Aşağıdaki verileri analiz et ve işletme sahibine 3 maddelik stratejik öneri ver:
" . json_encode($data, JSON_UNESCAPED_UNICODE) . "

Dil: Türkçe. Kısa ve vurucu ol.";

            // Check cache first
            $cacheKey = $this->getCacheKey($prompt);
            $cached = $this->getCached($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
            
            // Check rate limit
            if (!$this->checkRateLimit()) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning('GeminiService: Rate limit exceeded for performance analysis');
                }
                return "AI Analizi şu an yapılamıyor. Lütfen daha sonra tekrar deneyin.";
            }
            
            $response = $this->callGeminiAPI('gemini-1.5-pro', $prompt);
            
            if ($response) {
                // Cache the response (longer cache for analytics - 24 hours)
                $cacheFile = $this->cacheDir . '/' . $cacheKey . '.cache';
                $cacheData = [
                    'timestamp' => time(),
                    'response' => $response
                ];
                file_put_contents($cacheFile, json_encode($cacheData));
                // Update rate limit
                $this->updateRateLimit();
                return $response;
            }
            
            return "Veriler analiz edilemedi.";
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("Gemini API Error: " . $e->getMessage(), [
                    'data' => $data,
                    'exception' => get_class($e)
                ]);
            }
            return "AI Analizi şu an yapılamıyor.";
        }
    }
    
    /**
     * Improve text - make it more professional and fix errors
     * @param string $text
     * @return string|null
     */
    public function improveText(string $text): ?string {
        if (!$this->isAvailable()) {
            return null;
        }
        
        try {
            $prompt = "Aşağıdaki metni düzelt, hataları gider ve daha kurumsal ve profesyonel bir hale getir. Metni Türkçe olarak düzelt ve sadece düzeltilmiş metni döndür, başka açıklama ekleme:\n\n" . $text;
            return $this->callGeminiAPIPrivate('gemini-2.5-flash', $prompt);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("Gemini improveText error: " . $e->getMessage());
            }
            return null;
        }
    }
    
    /**
     * Public method to call Gemini API (for use by other services)
     * @param string $model Model name
     * @param string $prompt Prompt text
     * @return string|null Response text
     */
    public function callGeminiAPI($model, $prompt) {
        return $this->callGeminiAPIPrivate($model, $prompt);
    }
    
    /**
     * Call Gemini API with proper error handling
     * @param string $model - Model name (gemini-2.5-flash, gemini-2.5-pro, gemini-2.0-flash, etc.)
     *                         Old model names (gemini-1.5-flash, gemini-1.5-pro) are automatically mapped to 2.5 versions
     *                         See: https://ai.google.dev/gemini-api/docs/quickstart
     * @param string $prompt - Prompt text
     * @return string|null - Response text
     * @throws \Exception
     */
    private function callGeminiAPIPrivate($model, $prompt) {
        try {
            return $this->callGeminiAPIPrivateInternal($model, $prompt);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning("Gemini API call failed: " . $e->getMessage() . ". Attempting Opusmax Claude fallback...");
            }
            try {
                return $this->callOpusmaxAPI($prompt);
            } catch (\Exception $fallbackEx) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error("Opusmax Claude fallback failed: " . $fallbackEx->getMessage());
                }
                throw $e;
            }
        }
    }

    private function callGeminiAPIPrivateInternal($model, $prompt) {
        if (empty($this->apiKey)) {
            throw new \Exception('GEMINI_API_KEY is not configured. Please check your API key in system settings.');
        }
        
        // Validate API key format (should start with AIza)
        if (!preg_match('/^AIza[0-9A-Za-z_-]{35}$/', $this->apiKey)) {
            throw new \Exception('Invalid GEMINI_API_KEY format. API key should start with "AIza" and be 39 characters long.');
        }
        
        // Map model names - Google deprecated Gemini 1.5 models, now using Gemini 2.5/2.0
        // Based on Google's latest documentation: https://ai.google.dev/gemini-api/docs/quickstart
        $modelMap = [
            'gemini-1.5-flash' => 'gemini-2.5-flash',
            'gemini-1.5-flash-latest' => 'gemini-2.5-flash',
            'gemini-1.5-pro' => 'gemini-2.5-pro',
            'gemini-1.5-pro-latest' => 'gemini-2.5-pro',
            'gemini-2.0-flash' => 'gemini-2.0-flash',
            'gemini-2.5-flash' => 'gemini-2.5-flash',
            'gemini-2.5-pro' => 'gemini-2.5-pro',
            'gemini-pro' => 'gemini-2.5-flash' // Fallback to 2.5-flash for old gemini-pro
        ];
        
        $actualModel = $modelMap[$model] ?? $model;
        
        // Prepare request data
        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ]
        ];
        
        // Use v1 API first (v1beta is deprecated, newer models require v1)
        // Try v1 API first, then fallback to v1beta if needed for backward compatibility
        $url = $this->baseUrlV1 . $actualModel . ':generateContent';
        $apiVersion = 'v1';
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'x-goog-api-key: ' . $this->apiKey
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            throw new \Exception("CURL Error: {$curlError}");
        }
        
        if ($httpCode !== 200) {
            $errorMessage = "HTTP {$httpCode}";
            $shouldFallback = false;
            
            if ($response) {
                $errorData = json_decode($response, true);
                if (isset($errorData['error']['message'])) {
                    $errorMessage = $errorData['error']['message'];
                    // Check if error is about model not found - try fallback
                    if (strpos($errorMessage, 'is not found') !== false || strpos($errorMessage, 'not supported') !== false) {
                        $shouldFallback = true;
                    }
                }
            }
            
            // Fallback 1: Try with gemini-2.5-flash model using v1 API
            if ($shouldFallback && $actualModel !== 'gemini-2.5-flash') {
                $fallbackUrl = $this->baseUrlV1 . 'gemini-2.5-flash:generateContent';
                
                $ch2 = curl_init($fallbackUrl);
                curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch2, CURLOPT_POST, true);
                curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch2, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'x-goog-api-key: ' . $this->apiKey
                ]);
                curl_setopt($ch2, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch2, CURLOPT_CONNECTTIMEOUT, 10);
                
                $response = curl_exec($ch2);
                $httpCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch2);
                curl_close($ch2);
                
                if ($curlError) {
                    throw new \Exception("CURL Error: {$curlError}");
                }
                
                if ($httpCode === 200) {
                    // Success with fallback, continue processing
                } else {
                    $fallbackError = $errorMessage;
                    if ($response) {
                        $fallbackErrorData = json_decode($response, true);
                        if (isset($fallbackErrorData['error']['message'])) {
                            $fallbackError = $fallbackErrorData['error']['message'];
                        }
                    }
                    // Final fallback: Try v1beta API (for backward compatibility with older models)
                    $v1betaUrl = $this->baseUrlV1Beta . 'gemini-2.5-flash:generateContent';
                    
                    $ch3 = curl_init($v1betaUrl);
                    curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch3, CURLOPT_POST, true);
                    curl_setopt($ch3, CURLOPT_POSTFIELDS, json_encode($data));
                    curl_setopt($ch3, CURLOPT_HTTPHEADER, [
                        'Content-Type: application/json',
                        'x-goog-api-key: ' . $this->apiKey
                    ]);
                    curl_setopt($ch3, CURLOPT_TIMEOUT, 30);
                    curl_setopt($ch3, CURLOPT_CONNECTTIMEOUT, 10);
                    
                    $response = curl_exec($ch3);
                    $httpCode = curl_getinfo($ch3, CURLINFO_HTTP_CODE);
                    $curlError = curl_error($ch3);
                    curl_close($ch3);
                    
                    if ($curlError) {
                        throw new \Exception("Gemini API Error ({$apiVersion}/{$actualModel}): {$errorMessage} | Fallback (gemini-2.5-flash) failed: {$fallbackError} | v1beta fallback also failed: CURL Error: {$curlError}");
                    }
                    
                    if ($httpCode === 200) {
                        // Success with v1beta fallback
                    } else {
                        $v1betaError = $fallbackError;
                        if ($response) {
                            $v1betaErrorData = json_decode($response, true);
                            if (isset($v1betaErrorData['error']['message'])) {
                                $v1betaError = $v1betaErrorData['error']['message'];
                            }
                        }
                        throw new \Exception("Gemini API Error ({$apiVersion}/{$actualModel}): {$errorMessage} | Fallback (gemini-2.5-flash) failed: {$fallbackError} | v1beta fallback also failed: {$v1betaError}");
                    }
                }
            } else {
                throw new \Exception("Gemini API Error ({$apiVersion}/{$actualModel}): {$errorMessage}");
            }
        }
        
        $result = json_decode($response, true);
        
        if (!$result) {
            throw new \Exception("Invalid JSON response from Gemini API");
        }
        
        // Check for API errors in response
        if (isset($result['error'])) {
            throw new \Exception("Gemini API Error: " . ($result['error']['message'] ?? 'Unknown error'));
        }
        
        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            return $result['candidates'][0]['content']['parts'][0]['text'];
        }
        
        // Alternative response format
        if (isset($result['candidates'][0]['text'])) {
            return $result['candidates'][0]['text'];
        }
        
        // Try to find text in any part
        if (isset($result['candidates'][0])) {
            $candidate = $result['candidates'][0];
            if (isset($candidate['content']['parts'])) {
                foreach ($candidate['content']['parts'] as $part) {
                    if (isset($part['text'])) {
                        return $part['text'];
                    }
                }
            }
        }
        
        // Check if response was blocked
        if (isset($result['candidates'][0]['finishReason']) && 
            $result['candidates'][0]['finishReason'] !== 'STOP') {
            $finishReason = $result['candidates'][0]['finishReason'];
            if ($finishReason === 'STOP' && isset($result['candidates'][0]['content'])) {
                // Response completed but content might be empty or in different format
                $content = $result['candidates'][0]['content'];
                // Check if content has role but no parts (empty response)
                if (isset($content['role']) && (!isset($content['parts']) || empty($content['parts']))) {
                    // This is an empty response, return empty string instead of throwing error
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::warning('Gemini API returned empty response', [
                            'finishReason' => $finishReason,
                            'response' => json_encode($result)
                        ]);
                    }
                    return '';
                }
            }
            throw new \Exception("Gemini API: Response blocked - " . $finishReason);
        }
        
        // Check if response has candidates but no text content (empty response)
        if (isset($result['candidates'][0]) && isset($result['candidates'][0]['content'])) {
            $content = $result['candidates'][0]['content'];
            // If content exists but has no parts or empty parts, it's an empty response
            if (!isset($content['parts']) || empty($content['parts'])) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning('Gemini API returned empty response (no parts)', [
                        'response' => json_encode($result)
                    ]);
                }
                return '';
            }
        }
        
        // Log unexpected format but don't throw - return empty string instead
        if (class_exists('\App\Core\Logger')) {
            \App\Core\Logger::error("Unexpected response format from Gemini API", [
                'response' => json_encode($result)
            ]);
        }
        
        // Return empty string instead of throwing exception to prevent breaking the application
        return '';
    }
    
    /**
     * Translate menu item content to target language
     * @param array $turkishContent - Turkish content [name, description, ingredients, extras]
     * @param string $targetLanguage - Target language code (en, etc.)
     * @return array - Translated content
     */
    public function translateMenuItem(array $turkishContent, string $targetLanguage): array {
        if (!$this->isAvailable()) {
            return [];
        }
        
        try {
            $name = $turkishContent['name'] ?? '';
            $description = $turkishContent['description'] ?? '';
            $ingredients = $turkishContent['ingredients'] ?? [];
            $extras = $turkishContent['extras'] ?? [];
            
            $languageNames = [
                'en' => 'English',
                'tr' => 'Turkish',
                'de' => 'German',
                'fr' => 'French',
                'es' => 'Spanish',
                'ar' => 'Arabic'
            ];
            
            $targetLangName = $languageNames[$targetLanguage] ?? $targetLanguage;
            
            $prompt = "You are a professional restaurant menu translator. Translate the following Turkish menu item content to {$targetLangName}. 
            
IMPORTANT RULES:
1. Keep the same tone and style (appetizing, professional, restaurant-quality)
2. Preserve any special formatting or structure
3. For ingredients and extras, translate each item separately
4. Return ONLY a valid JSON object with this exact structure:
{
    \"name\": \"translated name\",
    \"description\": \"translated description\",
    \"ingredients\": [\"ingredient1\", \"ingredient2\"],
    \"extras\": [{\"name\": \"extra name\", \"price\": price_number}]
}

Turkish Content:
Name: {$name}
Description: {$description}
Ingredients: " . (is_array($ingredients) ? implode(', ', $ingredients) : $ingredients) . "
Extras: " . (is_array($extras) ? json_encode($extras, JSON_UNESCAPED_UNICODE) : $extras) . "

Return ONLY the JSON object, no other text.";
            
            // Check cache
            $cacheKey = $this->getCacheKey($prompt);
            $cached = $this->getCached($cacheKey);
            if ($cached !== null) {
                $decoded = json_decode($cached, true);
                if ($decoded) {
                    return $decoded;
                }
            }
            
            // Check rate limit
            if (!$this->checkRateLimit()) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning('GeminiService: Rate limit exceeded for translation');
                }
                return [];
            }
            
            $response = $this->callGeminiAPI('gemini-1.5-flash', $prompt);
            
            if ($response) {
                $response = trim($response);
                // Try to extract JSON from response
                if (preg_match('/\{.*\}/s', $response, $matches)) {
                    $json = $matches[0];
                    $decoded = json_decode($json, true);
                    if ($decoded) {
                        $this->setCached($cacheKey, $json);
                        $this->updateRateLimit();
                        return $decoded;
                    }
                }
            }
            
            return [];
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("Gemini API Error: " . $e->getMessage(), [
                    'content' => $turkishContent,
                    'target_language' => $targetLanguage,
                    'exception' => get_class($e)
                ]);
            }
            return [];
        }
    }
    
    /**
     * Simple text translation with restaurant/food terminology optimization
     * @param string $text Text to translate
     * @param string $targetLanguage Target language code (en, de, fr, etc.)
     * @param string $contextType Optional context: 'category' for menu categories, 'menu_item' for menu items, null for general
     * @return string|null Translated text
     */
    public function translateText(string $text, string $targetLanguage = 'en', ?string $contextType = null): ?string {
        if (empty($text)) {
            return null;
        }
        
        // Load helper functions
        require_once __DIR__ . '/../helpers/text_normalization.php';
        require_once __DIR__ . '/../helpers/text_formatting.php';
        
        // Step 1: Check menu dictionary first (for English translations)
        if ($targetLanguage === 'en') {
            $dictionaryService = new \App\Services\MenuDictionaryService();
            $dictionaryTranslation = $dictionaryService->getTranslation($text, $targetLanguage);
            
            if ($dictionaryTranslation !== null) {
                // Apply title case formatting
                return formatMenuTitleCase($dictionaryTranslation);
            }
        }
        
        // Step 2: If not in dictionary or not English, use AI translation
        if (!$this->isAvailable()) {
            return null;
        }
        
        try {
            $languageNames = [
                'en' => 'English',
                'tr' => 'Turkish',
                'de' => 'German',
                'fr' => 'French',
                'es' => 'Spanish',
                'ar' => 'Arabic'
            ];
            
            $targetLangName = $languageNames[$targetLanguage] ?? $targetLanguage;
            
            // Build context-aware prompt based on context type
            if ($contextType === 'category') {
                $prompt = "You are a professional restaurant menu translator specializing in Turkish to {$targetLangName} translations. You understand cultural food context and restaurant industry terminology.

Translate the following Turkish restaurant menu category name to {$targetLangName}.

IMPORTANT RULES:
1. Use proper {$targetLangName} restaurant menu terminology that customers would recognize and understand
2. Keep it concise and professional (typically 1-3 words)
3. Use plural form when appropriate (e.g., \"Foods\" not \"Food\", \"Beverages\" not \"Beverage\")
4. Preserve the meaning and context of restaurant/cafe menu categories
5. Consider cultural context - some Turkish food categories don't have direct equivalents in other cultures
6. For food categories, use common terms that would appear on a restaurant menu in {$targetLangName}-speaking countries
7. Return the translation in Title Case format (capitalize first letter of each major word)

Common Turkish to {$targetLangName} restaurant category translations:
- \"Yemekler\" → \"Main Courses\" or \"Entrees\" (depending on region)
- \"İçecekler\" → \"Beverages\" or \"Drinks\"
- \"Tatlılar\" → \"Desserts\"
- \"Kahvaltılıklar\" → \"Breakfast Items\" or \"Breakfast Menu\"
- \"Aperitifler\" → \"Appetizers\" or \"Starters\"
- \"Ana Yemekler\" → \"Main Courses\"
- \"Salatalar\" → \"Salads\"
- \"Çorbalar\" → \"Soups\"
- \"Fast Food\" → \"Fast Food\" (same in English)
- \"İskender\" → \"Iskender\" (keep as-is, it's a proper noun)
- \"Kebaplar\" → \"Kebabs\"
- \"Burger\" → \"Burgers\" (same in English)

Turkish category name: {$text}

Return ONLY the {$targetLangName} translation in Title Case, no explanations or additional text.";
            } elseif ($contextType === 'menu_item') {
                // Multi-step translation: First analyze meaning, then translate with proper terminology
                $prompt = "You are a professional restaurant menu translator with expertise in Turkish cuisine and {$targetLangName} restaurant terminology. You understand cultural food context, culinary traditions, and restaurant industry standards.

STEP 1: ANALYZE THE MEANING
First, analyze what this Turkish menu item represents:
- What type of dish is it? (main course, appetizer, dessert, beverage, etc.)
- What are the key ingredients or components?
- Is it a traditional Turkish dish or a modern fusion dish?
- What cooking method is implied? (grilled, baked, fried, etc.)

STEP 2: TRANSLATE WITH PROFESSIONAL TERMINOLOGY
Now translate to {$targetLangName} using proper restaurant menu terminology:

CRITICAL TRANSLATION RULES:
1. Use authentic {$targetLangName} restaurant menu terminology that customers recognize
2. Maintain the appetizing, professional tone that makes dishes sound appealing
3. Use natural menu item naming conventions (e.g., \"Mushroom Chicken\" not \"Chicken with Mushrooms\")
4. For international dishes (Pizza, Burger, etc.), keep the original name
5. For traditional Turkish dishes, provide culturally accurate translations:
   - Add \"Turkish\" prefix when needed for clarity (e.g., \"Köfte\" → \"Turkish Meatballs\")
   - Use descriptive translations that convey the essence (e.g., \"Mantı\" → \"Turkish Ravioli\" or \"Turkish Dumplings\")
6. For regional specialties, preserve the original name with descriptive context (e.g., \"Adana Kebap\" → \"Adana Kebab\")
7. Consider cultural context - some dishes have different names in different regions
8. Use proper culinary terms (e.g., \"Grilled\" not \"Cooked on grill\", \"Marinated\" not \"Soaked in sauce\")
9. Return in Title Case format (capitalize first letter of each major word, keep small words like \"with\", \"and\", \"of\" lowercase unless first word)

PROFESSIONAL TERMINOLOGY EXAMPLES:
- \"Köfte\" → \"Turkish Meatballs\" or \"Beef Meatballs\" (not \"Meatballs\" alone)
- \"Mantı\" → \"Turkish Ravioli\" or \"Turkish Dumplings\" (not just \"Dumplings\")
- \"Adana Kebap\" → \"Adana Kebab\" (preserve proper noun)
- \"İskender\" → \"Iskender Kebab\" (add context)
- \"Tavuk Döner\" → \"Chicken Döner\" (preserve \"Döner\" as it's internationally recognized)
- \"Mantarlı Tavuk\" → \"Mushroom Chicken\" (natural order)
- \"Peynirli Tost\" → \"Cheese Toast\" or \"Grilled Cheese Sandwich\"
- \"Nargile\" → \"Shisha\" (common in European restaurants, not \"Hookah\")
- \"Ayran\" → \"Ayran\" (keep as-is, internationally recognized) or \"Yogurt Drink\"
- \"Şalgam\" → \"Şalgam\" (keep as-is) or \"Turnip Juice\"
- \"Boza\" → \"Boza\" (keep as-is) or \"Fermented Wheat Drink\"

Turkish menu item name: {$text}

Return ONLY the {$targetLangName} translation in Title Case, no explanations, no analysis, just the final translation.";
            } else {
                // General translation with restaurant context awareness (for descriptions and general text)
                $prompt = "You are a professional restaurant menu translator specializing in Turkish to {$targetLangName} translations. You understand culinary terminology, food culture, and restaurant industry standards.

Translate the following Turkish restaurant menu text to {$targetLangName}. This may be a menu item description, category description, or other menu-related text.

TRANSLATION GUIDELINES:
1. Use authentic {$targetLangName} restaurant menu terminology
2. Maintain the professional, appetizing tone that makes dishes sound appealing
3. For food descriptions, use accurate culinary terms:
   - \"Izgara\" → \"Grilled\" (not \"Cooked on grill\")
   - \"Fırında\" → \"Baked\" or \"Oven-baked\"
   - \"Kızartılmış\" → \"Fried\" (specify type if needed: \"Deep-fried\", \"Pan-fried\")
   - \"Marine\" → \"Marinated\"
   - \"Taze\" → \"Fresh\"
   - \"Ev yapımı\" → \"Homemade\" or \"House-made\"
4. Preserve cultural authenticity for traditional dishes
5. Use natural, flowing language that reads well in {$targetLangName}
6. Keep descriptions concise but descriptive (restaurant menu style)
7. For longer descriptions, maintain paragraph structure
8. Use proper capitalization: Title Case for short descriptions, sentence case for longer text

Turkish text: {$text}

Return ONLY the {$targetLangName} translation, maintaining the same structure and tone, no explanations or additional text.";
            }
            
            // Check cache
            $cacheKey = $this->getCacheKey($prompt);
            $cached = $this->getCached($cacheKey);
            if ($cached !== null) {
                $translated = trim($cached);
                // Quality check
                if ($this->validateTranslation($translated, $text)) {
                    // Apply title case formatting to ensure consistency
                    return formatMenuTitleCase($translated);
                }
            }
            
            // Check rate limit
            if (!$this->checkRateLimit()) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning('GeminiService: Rate limit exceeded for text translation');
                }
                return null;
            }
            
            $response = $this->callGeminiAPI('gemini-1.5-flash', $prompt);
            
            if ($response) {
                $response = trim($response);
                
                // Quality check
                if (!$this->validateTranslation($response, $text)) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::warning('GeminiService: Translation quality check failed', [
                            'original' => $text,
                            'translated' => $response,
                            'context' => $contextType
                        ]);
                    }
                    return null;
                }
                
                // Apply title case formatting to ensure consistency
                $formattedResponse = formatMenuTitleCase($response);
                
                // Cache the formatted response
                $this->setCached($cacheKey, $formattedResponse);
                $this->updateRateLimit();
                return $formattedResponse;
            }
            
            return null;
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("Gemini Translation Error: " . $e->getMessage(), [
                    'text' => $text,
                    'target_language' => $targetLanguage,
                    'context' => $contextType,
                    'exception' => get_class($e)
                ]);
            }
            return null;
        }
    }
    
    /**
     * Validate translation quality
     * @param string $translated Translated text
     * @param string $original Original text
     * @return bool True if translation is valid
     */
    private function validateTranslation(string $translated, string $original): bool {
        // Check if translation is empty
        if (empty(trim($translated))) {
            return false;
        }
        
        // Check if translation is too short (less than 10% of original length)
        $originalLength = mb_strlen(trim($original));
        $translatedLength = mb_strlen(trim($translated));
        
        if ($originalLength > 0 && $translatedLength < ($originalLength * 0.1)) {
            return false;
        }
        
        // Check if translation is suspiciously long (more than 5x original length)
        if ($translatedLength > ($originalLength * 5)) {
            return false;
        }
        
        // Check if translation contains only punctuation or numbers
        if (preg_match('/^[\p{P}\p{N}\s]+$/u', $translated)) {
            return false;
        }
        
        // Check if translation is the same as original (might indicate failure)
        if (mb_strtolower(trim($translated)) === mb_strtolower(trim($original))) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Generate SEO content (meta title, description, keywords) for menu item
     * @param string $name - Menu item name
     * @param string $description - Menu item description
     * @param string $category - Category name
     * @param string $language - Language code
     * @return array - SEO content [meta_title, meta_description, meta_keywords]
     */
    public function generateSEOContent(string $name, string $description = '', string $category = '', string $language = 'tr'): array {
        if (!$this->isAvailable()) {
            // Fallback SEO generation
            $metaTitle = $name . ($category ? ' - ' . $category : '');
            if (strlen($metaTitle) > 60) {
                $metaTitle = substr($metaTitle, 0, 57) . '...';
            }
            
            $metaDescription = $description ?: $name;
            if (strlen($metaDescription) > 160) {
                $metaDescription = substr($metaDescription, 0, 157) . '...';
            }
            
            $metaKeywords = implode(', ', array_filter([$name, $category]));
            
            return [
                'meta_title' => $metaTitle,
                'meta_description' => $metaDescription,
                'meta_keywords' => $metaKeywords
            ];
        }
        
        try {
            $languageNames = [
                'en' => 'English',
                'tr' => 'Turkish',
                'de' => 'German',
                'fr' => 'French',
                'es' => 'Spanish',
                'ar' => 'Arabic'
            ];
            
            $langName = $languageNames[$language] ?? $language;
            
            $prompt = "You are an SEO expert. Generate optimized SEO content for a restaurant menu item in {$langName}.

Menu Item: {$name}
Description: {$description}
Category: {$category}

Generate SEO-optimized content following these rules:
1. Meta Title: Maximum 60 characters, include item name and category if space allows. Make it compelling.
2. Meta Description: Maximum 160 characters, summarize the item appealingly. Include key selling points.
3. Meta Keywords: 5-8 relevant keywords, comma-separated. Include item name, category, cuisine type, main ingredients.

Return ONLY a valid JSON object:
{
    \"meta_title\": \"optimized title\",
    \"meta_description\": \"optimized description\",
    \"meta_keywords\": \"keyword1, keyword2, keyword3\"
}

Return ONLY the JSON, no other text.";
            
            // Check cache
            $cacheKey = $this->getCacheKey($prompt);
            $cached = $this->getCached($cacheKey);
            if ($cached !== null) {
                $decoded = json_decode($cached, true);
                if ($decoded) {
                    return $decoded;
                }
            }
            
            // Check rate limit
            if (!$this->checkRateLimit()) {
                // Return fallback
                $metaTitle = $name . ($category ? ' - ' . $category : '');
                if (strlen($metaTitle) > 60) {
                    $metaTitle = substr($metaTitle, 0, 57) . '...';
                }
                
                $metaDescription = $description ?: $name;
                if (strlen($metaDescription) > 160) {
                    $metaDescription = substr($metaDescription, 0, 157) . '...';
                }
                
                return [
                    'meta_title' => $metaTitle,
                    'meta_description' => $metaDescription,
                    'meta_keywords' => implode(', ', array_filter([$name, $category]))
                ];
            }
            
            $response = $this->callGeminiAPI('gemini-1.5-flash', $prompt);
            
            if ($response) {
                $response = trim($response);
                // Try to extract JSON
                if (preg_match('/\{.*\}/s', $response, $matches)) {
                    $json = $matches[0];
                    $decoded = json_decode($json, true);
                    if ($decoded) {
                        $this->setCached($cacheKey, $json);
                        $this->updateRateLimit();
                        return $decoded;
                    }
                }
            }
            
            // Fallback
            $metaTitle = $name . ($category ? ' - ' . $category : '');
            if (strlen($metaTitle) > 60) {
                $metaTitle = substr($metaTitle, 0, 57) . '...';
            }
            
            $metaDescription = $description ?: $name;
            if (strlen($metaDescription) > 160) {
                $metaDescription = substr($metaDescription, 0, 157) . '...';
            }
            
            return [
                'meta_title' => $metaTitle,
                'meta_description' => $metaDescription,
                'meta_keywords' => implode(', ', array_filter([$name, $category]))
            ];
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("Gemini SEO Generation Error: " . $e->getMessage(), [
                    'language' => $language,
                    'exception' => get_class($e)
                ]);
            }
            
            // Fallback
            $metaTitle = $name . ($category ? ' - ' . $category : '');
            if (strlen($metaTitle) > 60) {
                $metaTitle = substr($metaTitle, 0, 57) . '...';
            }
            
            return [
                'meta_title' => $metaTitle,
                'meta_description' => $description ?: $name,
                'meta_keywords' => implode(', ', array_filter([$name, $category]))
            ];
        }
    }
    
    /**
     * Translate ingredients array
     * @param array $ingredients - Array of ingredient names in Turkish
     * @param string $targetLanguage - Target language code
     * @return array - Translated ingredients
     */
    public function translateIngredients(array $ingredients, string $targetLanguage): array {
        if (empty($ingredients) || $targetLanguage === 'tr') {
            return $ingredients;
        }
        
        if (!$this->isAvailable()) {
            return $ingredients;
        }
        
        try {
            $ingredientsStr = implode(', ', $ingredients);
            $languageNames = [
                'en' => 'English',
                'tr' => 'Turkish',
                'de' => 'German',
                'fr' => 'French',
                'es' => 'Spanish',
                'ar' => 'Arabic'
            ];
            
            $targetLangName = $languageNames[$targetLanguage] ?? $targetLanguage;
            
            $prompt = "Translate these Turkish ingredient names to {$targetLangName}. Return ONLY a JSON array of translated names in the same order.

Ingredients: {$ingredientsStr}

Return format: [\"translated1\", \"translated2\", ...]
Return ONLY the JSON array, no other text.";
            
            $cacheKey = $this->getCacheKey($prompt);
            $cached = $this->getCached($cacheKey);
            if ($cached !== null) {
                $decoded = json_decode($cached, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
            
            if (!$this->checkRateLimit()) {
                return $ingredients;
            }
            
            $response = $this->callGeminiAPI('gemini-1.5-flash', $prompt);
            
            if ($response) {
                $response = trim($response);
                if (preg_match('/\[.*\]/s', $response, $matches)) {
                    $json = $matches[0];
                    $decoded = json_decode($json, true);
                    if (is_array($decoded)) {
                        $this->setCached($cacheKey, $json);
                        $this->updateRateLimit();
                        return $decoded;
                    }
                }
            }
            
            return $ingredients;
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("Gemini Ingredients Translation Error: " . $e->getMessage());
            }
            return $ingredients;
        }
    }
    
    /**
     * Translate extras array
     * @param array $extras - Array of extras [['name' => '...', 'price' => ...], ...]
     * @param string $targetLanguage - Target language code
     * @return array - Translated extras
     */
    public function translateExtras(array $extras, string $targetLanguage): array {
        if (empty($extras) || $targetLanguage === 'tr') {
            return $extras;
        }
        
        if (!$this->isAvailable()) {
            return $extras;
        }
        
        try {
            $extrasStr = json_encode($extras, JSON_UNESCAPED_UNICODE);
            $languageNames = [
                'en' => 'English',
                'tr' => 'Turkish',
                'de' => 'German',
                'fr' => 'French',
                'es' => 'Spanish',
                'ar' => 'Arabic'
            ];
            
            $targetLangName = $languageNames[$targetLanguage] ?? $targetLanguage;
            
            $prompt = "Translate the 'name' field of these Turkish menu extras to {$targetLangName}. Keep prices unchanged. Return ONLY a JSON array with the same structure.

Extras: {$extrasStr}

Return format: [{\"name\": \"translated name\", \"price\": original_price}, ...]
Return ONLY the JSON array, no other text.";
            
            $cacheKey = $this->getCacheKey($prompt);
            $cached = $this->getCached($cacheKey);
            if ($cached !== null) {
                $decoded = json_decode($cached, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
            
            if (!$this->checkRateLimit()) {
                return $extras;
            }
            
            $response = $this->callGeminiAPI('gemini-1.5-flash', $prompt);
            
            if ($response) {
                $response = trim($response);
                if (preg_match('/\[.*\]/s', $response, $matches)) {
                    $json = $matches[0];
                    $decoded = json_decode($json, true);
                    if (is_array($decoded)) {
                        $this->setCached($cacheKey, $json);
                        $this->updateRateLimit();
                        return $decoded;
                    }
                }
            }
            
            return $extras;
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("Gemini Extras Translation Error: " . $e->getMessage());
            }
            return $extras;
        }
    }
    
    /**
     * Improve image generation prompt using AI
     * Uses Gemini to create a better prompt for image generation services
     * @param string $productName Product name
     * @param string $description Product description
     * @param string $categoryName Category name
     * @param array $ingredients Ingredients list
     * @return string|null Improved prompt or null on failure
     */
    public function improveImagePrompt(string $productName, string $description = '', string $categoryName = '', array $ingredients = []): ?string {
        if (!$this->isAvailable()) {
            return null;
        }
        
        try {
            $ingredientList = !empty($ingredients) ? implode(', ', array_slice($ingredients, 0, 5)) : '';
            
            $prompt = "You are a world-class food photographer and AI image generation expert specializing in restaurant menu photography. Create a highly detailed, professional, and appetizing prompt for generating a stunning restaurant menu food photo.

Product Name: {$productName}
Description: {$description}
Category: {$categoryName}
Key Ingredients: {$ingredientList}

Create an optimized, detailed prompt for AI image generation (Stable Diffusion, DALL-E style) that will produce a professional restaurant menu photo. The prompt must include:

1. FOOD APPEARANCE: Describe the dish in detail - colors, textures, presentation style, freshness, visual appeal
2. PHOTOGRAPHY STYLE: Professional restaurant menu photography, food styling, commercial food photography
3. LIGHTING: Soft, natural lighting, professional food photography lighting, appetizing illumination
4. COMPOSITION: Professional food photography composition, centered or rule of thirds, clean background
5. STYLING: Restaurant-quality food presentation, garnished beautifully, on appropriate serving dish
6. MOOD: Appetizing, fresh, delicious, inviting, professional, high-quality
7. TECHNICAL: High resolution, sharp focus, professional quality, commercial photography
8. BACKGROUND: Clean, simple background (white, light gray, or wooden surface), professional food photography background

IMPORTANT: 
- Write the prompt in English
- Be very specific and detailed
- Focus on making the food look incredibly appetizing and professional
- Include all visual details that would make someone want to order this dish
- The prompt should be suitable for a high-end restaurant menu

Return ONLY the optimized prompt text in English, no explanations, no additional text, just the prompt itself.";

            // Check cache
            $cacheKey = $this->getCacheKey($prompt);
            $cached = $this->getCached($cacheKey);
            if ($cached !== null) {
                return trim($cached);
            }
            
            // Check rate limit
            if (!$this->checkRateLimit()) {
                return null;
            }
            
            $response = $this->callGeminiAPI('gemini-1.5-flash', $prompt);
            
            if ($response) {
                $response = trim($response);
                $this->setCached($cacheKey, $response);
                $this->updateRateLimit();
                return $response;
            }
            
            return null;
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("Gemini Image Prompt Improvement Error: " . $e->getMessage());
            }
            return null;
        }
    }
    
    /**
     * Extract menu items from an image using Gemini Vision API
     * @param string $imageBase64 Base64-encoded image data (without data URI prefix)
     * @param string $mimeType MIME type of the image (e.g., 'image/jpeg', 'image/png')
     * @return array Extracted menu items array, or empty array on failure
     */
    public function extractMenuFromImage(string $imageBase64, string $mimeType): array {
        if (!$this->isAvailable()) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('GeminiService: extractMenuFromImage called but API key not configured');
            }
            return [];
        }
        
        try {
            // Validate mime type
            $allowedMimeTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
            if (!in_array(strtolower($mimeType), $allowedMimeTypes)) {
                throw new \Exception("Unsupported image type: {$mimeType}");
            }
            
            // Prepare prompt for menu extraction - optimized for Turkish and English menus with subcategory support
            $prompt = "Sen bir restoran menüsü okuma uzmanısın. Bu menü görselini analiz et ve tüm menü öğelerini detaylarıyla çıkar. Hem Türkçe hem İngilizce çevirileri sağla.

Her menü öğesi için şu bilgileri çıkar:
- name_tr: Ürün adı Türkçe (zorunlu)
- name_en: Ürün adı İngilizce (zorunlu - Türkçe'den çevir veya menüde İngilizce varsa kullan)
- price: Fiyat sayı olarak (zorunlu, sadece sayısal değer, para birimi sembolü olmadan)
- description_tr: Ürün açıklaması Türkçe varsa (opsiyonel)
- description_en: Ürün açıklaması İngilizce (opsiyonel - Türkçe'den çevir)
- ingredients_tr: Malzemeler listesi Türkçe varsa (opsiyonel, array olarak)
- ingredients_en: Malzemeler listesi İngilizce (opsiyonel - Türkçe'den çevir)
- category: Kategori adı (opsiyonel, ama ÇOK ÖNEMLİ - her ürünün hangi kategoriye ait olduğunu belirle)
- parent_category: Ana kategori adı (opsiyonel - eğer alt kategori varsa ana kategoriyi belirle)

Sonucu geçerli bir JSON array olarak döndür. Her obje şu yapıda olmalı:
{
  \"name_tr\": \"Ürün Adı Türkçe\",
  \"name_en\": \"Product Name English\",
  \"price\": 99.99,
  \"description_tr\": \"Opsiyonel açıklama Türkçe\",
  \"description_en\": \"Optional description English\",
  \"ingredients_tr\": [\"malzeme1\", \"malzeme2\"],
  \"ingredients_en\": [\"ingredient1\", \"ingredient2\"],
  \"category\": \"Alt Kategori veya Kategori Adı\",
  \"parent_category\": \"Ana Kategori Adı (varsa)\"
}

ÖNEMLİ KURALLAR:
- Fiyatları sadece sayı olarak çıkar (TL, ₺, $ gibi sembolleri kaldır, ondalık sayıya çevir)
- Fiyat bulunamazsa 0 kullan
- Açıklama yoksa boş string kullan
- Malzemeler belirtilmemişse boş array [] kullan
- KATEGORİ ÇOK ÖNEMLİ: Menüdeki kategori başlıklarını (TOSTLAR, PIZZA, BURGERLER, SALATALAR, TATLILAR, KAHVELER, İÇECEKLER, NARGİLE vb.) dikkatlice oku ve her ürünün hangi kategoriye ait olduğunu belirle
- Kategori başlıkları genellikle büyük harflerle, renkli kutular içinde veya vurgulu şekilde gösterilir
- Eğer bir ürün bir kategori başlığının altındaysa, o kategoriye ait olduğunu anla
- ALT KATEGORİ MANTĞI: Eğer kategori hiyerarşisi varsa, parent_category alanına ana kategoriyi, category alanına alt kategoriyi yaz
  * Örnek 1: \"IZGARALAR & TAVUK SPESİYAL\" -> parent_category: \"IZGARALAR & TAVUK SPESİYAL\", category: null (bu bir ana kategoridir)
  * Örnek 2: \"KAHVELER & SICAKLAR\" ana kategori altında \"SICAK İÇECEKLER\" alt başlığı varsa -> parent_category: \"KAHVELER & SICAKLAR\", category: \"SICAK İÇECEKLER\"
  * Örnek 3: \"KAHVELER & SICAKLAR\" ana kategori altında \"KAHVE ÇEŞİTLERİ\" alt başlığı varsa -> parent_category: \"KAHVELER & SICAKLAR\", category: \"KAHVE ÇEŞİTLERİ\"
  * Örnek 4: \"KAHVELER & SICAKLAR\" ana kategori altında \"SOĞUK KAHVELER\" alt başlığı varsa -> parent_category: \"KAHVELER & SICAKLAR\", category: \"SOĞUK KAHVELER\"
  * Örnek 5: \"KAHVELER & SICAKLAR\" ana kategori altında \"BİTKİ ÇAYLARI\" alt başlığı varsa -> parent_category: \"KAHVELER & SICAKLAR\", category: \"BİTKİ ÇAYLARI\"
  * Örnek 6: \"SOĞUK İÇECEKLER\" tek başına bir kategoriyse -> category: \"SOĞUK İÇECEKLER\", parent_category: null
  * Örnek 7: Sadece \"PIZZA\" -> category: \"PIZZA\", parent_category: null
  * Örnek 8: \"SALATALAR\" tek başına -> category: \"SALATALAR\", parent_category: null
  * Örnek 9: \"TATLILAR\" tek başına -> category: \"TATLILAR\", parent_category: null
  * Örnek 10: \"NARGİLE ÇEŞİTLERİ\" tek başına -> category: \"NARGİLE ÇEŞİTLERİ\", parent_category: null
- ÖNEMLİ: Menüde \"&\" veya \"VE\" ile birleştirilmiş kategoriler (örn: \"KAHVELER & SICAKLAR\", \"IZGARALAR & TAVUK SPESİYAL\") genellikle ana kategoridir ve alt kategorileri olabilir
- ÖNEMLİ: Menüde alt başlıklar (subheadings) varsa bunları alt kategori olarak belirle ve parent_category olarak üstündeki ana kategoriyi kullan
- Türkçe ve İngilizce menüleri destekle
- SADECE geçerli JSON döndür, ek açıklama veya metin ekleme
- Hiç menü öğesi bulunamazsa boş array [] döndür

Kategori Örnekleri (Türkçe menüler için):
ANA KATEGORİLER (parent_category: null):
- TOSTLAR, TOST
- PIZZA, PİZZA
- BURGERLER, HAMBURGERLER
- SALATALAR, SALATA
- TATLILAR, TATLI, DESSERT
- SOĞUK İÇECEKLER
- NARGİLE ÇEŞİTLERİ, NARGİLE
- ATIŞTIRMALIKLAR, SNACKS
- KAHVELER & SICAKLAR (ana kategori, alt kategorileri olabilir)
- IZGARALAR & TAVUK SPESİYAL (ana kategori)

ALT KATEGORİ ÖRNEKLERİ (parent_category belirtilmeli):
- KAHVELER & SICAKLAR > SICAK İÇECEKLER
- KAHVELER & SICAKLAR > KAHVE ÇEŞİTLERİ
- KAHVELER & SICAKLAR > SOĞUK KAHVELER
- KAHVELER & SICAKLAR > BİTKİ ÇAYLARI
- IZGARALAR & TAVUK SPESİYAL > (eğer alt kategori varsa)

Örnek yanıt:
[
  {
    \"name_tr\": \"Kaşarlı Tost\",
    \"name_en\": \"Cheese Toast\",
    \"price\": 45.00,
    \"description_tr\": \"Sandviç, Kaşar Peyniri\",
    \"description_en\": \"Sandwich bread, Kashar cheese\",
    \"ingredients_tr\": [\"sandviç\", \"kaşar peyniri\"],
    \"ingredients_en\": [\"sandwich bread\", \"kashar cheese\"],
    \"category\": \"TOSTLAR\",
    \"parent_category\": null
  },
  {
    \"name_tr\": \"Napoliten Pizza\",
    \"name_en\": \"Neapolitan Pizza\",
    \"price\": 89.50,
    \"description_tr\": \"Özel pizza sosu, mozzarella peyniri, domates\",
    \"description_en\": \"Special pizza sauce, mozzarella cheese, tomatoes\",
    \"ingredients_tr\": [\"pizza sosu\", \"mozzarella\", \"domates\"],
    \"ingredients_en\": [\"pizza sauce\", \"mozzarella\", \"tomatoes\"],
    \"category\": \"PIZZA\",
    \"parent_category\": null
  },
  {
    \"name_tr\": \"Hamburger\",
    \"name_en\": \"Hamburger\",
    \"price\": 75.00,
    \"description_tr\": \"Susamlı Sandviç Ekmeği, %100 Dana Köfte\",
    \"description_en\": \"Sesame bun, 100% beef patty\",
    \"ingredients_tr\": [\"sandviç ekmeği\", \"dana köfte\", \"marul\", \"domates\"],
    \"ingredients_en\": [\"sesame bun\", \"beef patty\", \"lettuce\", \"tomatoes\"],
    \"category\": \"BURGERLER\",
    \"parent_category\": null
  },
  {
    \"name_tr\": \"Türk Kahvesi\",
    \"name_en\": \"Turkish Coffee\",
    \"price\": 90.00,
    \"description_tr\": \"Geleneksel Türk kahvesi\",
    \"description_en\": \"Traditional Turkish coffee\",
    \"ingredients_tr\": [],
    \"ingredients_en\": [],
    \"category\": \"SICAK İÇECEKLER\",
    \"parent_category\": \"KAHVELER & SICAKLAR\"
  },
  {
    \"name_tr\": \"Cappuccino\",
    \"name_en\": \"Cappuccino\",
    \"price\": 150.00,
    \"description_tr\": \"Espresso, süt köpüğü\",
    \"description_en\": \"Espresso, milk foam\",
    \"ingredients_tr\": [],
    \"ingredients_en\": [],
    \"category\": \"KAHVE ÇEŞİTLERİ\",
    \"parent_category\": \"KAHVELER & SICAKLAR\"
  },
  {
    \"name_tr\": \"Buzlu Latte\",
    \"name_en\": \"Iced Latte\",
    \"price\": 150.00,
    \"description_tr\": \"Soğuk latte\",
    \"description_en\": \"Cold latte\",
    \"ingredients_tr\": [],
    \"ingredients_en\": [],
    \"category\": \"SOĞUK KAHVELER\",
    \"parent_category\": \"KAHVELER & SICAKLAR\"
  },
  {
    \"name_tr\": \"Adaçayı\",
    \"name_en\": \"Sage Tea\",
    \"price\": 100.00,
    \"description_tr\": \"Bitki çayı\",
    \"description_en\": \"Herbal tea\",
    \"ingredients_tr\": [],
    \"ingredients_en\": [],
    \"category\": \"BİTKİ ÇAYLARI\",
    \"parent_category\": \"KAHVELER & SICAKLAR\"
  }
]";

            // Check rate limit
            if (!$this->checkRateLimit()) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::warning('GeminiService: Rate limit exceeded for menu extraction');
                }
                return [];
            }
            
            // Use Gemini Vision API - gemini-2.5-flash-002 is faster and more reliable than gemini-2.0-flash-exp
            $model = 'gemini-2.5-flash-002'; // Latest vision-capable model, very fast
            
            // Prepare request data with image
            $data = [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'inline_data' => [
                                    'mime_type' => $mimeType,
                                    'data' => $imageBase64
                                ]
                            ],
                            ['text' => $prompt]
                        ]
                    ]
                ]
            ];
            
            // Use v1 API for vision models
            $url = $this->baseUrlV1 . $model . ':generateContent';
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'x-goog-api-key: ' . $this->apiKey
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 300); // Extended timeout for vision API (5 minutes)
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                throw new \Exception("CURL Error: {$curlError}");
            }
            
            if ($httpCode !== 200) {
                $errorMessage = "HTTP {$httpCode}";
                if ($response) {
                    $errorData = json_decode($response, true);
                    if (isset($errorData['error']['message'])) {
                        $errorMessage = $errorData['error']['message'];
                    }
                }
                
                // Try fallback to gemini-2.5-flash if gemini-2.0-flash-exp fails
                if (strpos($errorMessage, 'not found') !== false || strpos($errorMessage, 'not supported') !== false) {
                    $fallbackModel = 'gemini-2.5-flash';
                    $fallbackUrl = $this->baseUrlV1 . $fallbackModel . ':generateContent';
                    
                    $ch2 = curl_init($fallbackUrl);
                    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch2, CURLOPT_POST, true);
                    curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode($data));
                    curl_setopt($ch2, CURLOPT_HTTPHEADER, [
                        'Content-Type: application/json',
                        'x-goog-api-key: ' . $this->apiKey
                    ]);
                    curl_setopt($ch2, CURLOPT_TIMEOUT, 300);
                    curl_setopt($ch2, CURLOPT_CONNECTTIMEOUT, 30);
                    
                    $response = curl_exec($ch2);
                    $httpCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                    $curlError = curl_error($ch2);
                    curl_close($ch2);
                    
                    if ($curlError) {
                        throw new \Exception("CURL Error (fallback): {$curlError}");
                    }
                    
                    if ($httpCode !== 200) {
                        $fallbackError = "HTTP {$httpCode}";
                        if ($response) {
                            $fallbackErrorData = json_decode($response, true);
                            if (isset($fallbackErrorData['error']['message'])) {
                                $fallbackError = $fallbackErrorData['error']['message'];
                            }
                        }
                        throw new \Exception("Gemini Vision API Error ({$model}): {$errorMessage} | Fallback ({$fallbackModel}) failed: {$fallbackError}");
                    }
                } else {
                    throw new \Exception("Gemini Vision API Error ({$model}): {$errorMessage}");
                }
            }
            
            $result = json_decode($response, true);
            
            if (!$result) {
                throw new \Exception("Invalid JSON response from Gemini API");
            }
            
            // Check for API errors in response
            if (isset($result['error'])) {
                throw new \Exception("Gemini API Error: " . ($result['error']['message'] ?? 'Unknown error'));
            }
            
            // Extract text response
            $responseText = null;
            if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
                $responseText = $result['candidates'][0]['content']['parts'][0]['text'];
            } elseif (isset($result['candidates'][0]['text'])) {
                $responseText = $result['candidates'][0]['text'];
            }
            
            if (!$responseText) {
                throw new \Exception("No text response from Gemini API");
            }
            
            // Clean response text - remove markdown code blocks if present
            $responseText = trim($responseText);
            $responseText = preg_replace('/^```json\s*/i', '', $responseText);
            $responseText = preg_replace('/^```\s*/', '', $responseText);
            $responseText = preg_replace('/\s*```\s*$/', '', $responseText);
            $responseText = trim($responseText);
            
            // Parse JSON response
            $menuItems = json_decode($responseText, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("Invalid JSON in Gemini response: " . json_last_error_msg());
            }
            
            if (!is_array($menuItems)) {
                // If response is not an array, try to wrap it
                if (is_object($menuItems) || is_array($menuItems)) {
                    $menuItems = [$menuItems];
                } else {
                    throw new \Exception("Gemini response is not an array of menu items");
                }
            }
            
            // Validate and clean extracted items
            $validatedItems = [];
            foreach ($menuItems as $item) {
                if (!is_array($item)) {
                    continue;
                }
                
                // Get Turkish and English names (support both old and new format)
                $nameTr = trim($item['name_tr'] ?? $item['name'] ?? '');
                $nameEn = trim($item['name_en'] ?? $item['name'] ?? ''); // Fallback to Turkish name if EN not provided
                
                // Skip items without Turkish name
                if (empty($nameTr)) {
                    continue;
                }
                
                // If English name is empty, use Turkish name
                if (empty($nameEn)) {
                    $nameEn = $nameTr;
                }
                
                // Get descriptions (support both old and new format)
                $descriptionTr = trim($item['description_tr'] ?? $item['description'] ?? '');
                $descriptionEn = trim($item['description_en'] ?? $item['description'] ?? '');
                
                // Get ingredients (support both old and new format)
                $ingredientsTr = is_array($item['ingredients_tr'] ?? null) ? $item['ingredients_tr'] : 
                               (is_array($item['ingredients'] ?? null) ? $item['ingredients'] : []);
                $ingredientsEn = is_array($item['ingredients_en'] ?? null) ? $item['ingredients_en'] : [];
                
                // Ensure required fields
                $validatedItem = [
                    'name' => $nameTr, // Keep 'name' for backward compatibility (Turkish)
                    'name_tr' => $nameTr,
                    'name_en' => $nameEn,
                    'price' => floatval($item['price'] ?? 0),
                    'description' => $descriptionTr, // Keep 'description' for backward compatibility
                    'description_tr' => $descriptionTr,
                    'description_en' => $descriptionEn,
                    'ingredients' => $ingredientsTr, // Keep 'ingredients' for backward compatibility
                    'ingredients_tr' => $ingredientsTr,
                    'ingredients_en' => $ingredientsEn,
                    'category' => trim($item['category'] ?? '')
                ];
                
                // Ensure price is positive
                if ($validatedItem['price'] < 0) {
                    $validatedItem['price'] = 0;
                }
                
                $validatedItems[] = $validatedItem;
            }
            
            // Update rate limit
            $this->updateRateLimit();
            
            return $validatedItems;
            
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("Gemini Menu Extraction Error: " . $e->getMessage());
            }
            return [];
        }
    }
    
    /**
     * Generate customer recommendations based on order
     * Analyzes customer's order and suggests complementary items (desserts, drinks, etc.)
     * @param array $orderItems Array of ordered items with names
     * @param float $orderTotal Total order amount
     * @param array $availableMenuItems Available menu items for recommendations
     * @return array Array with recommendations [['type' => 'dessert', 'message' => '...', 'suggestions' => [...]], ...]
     */
    public function generateCustomerRecommendations(array $orderItems, float $orderTotal = 0, array $availableMenuItems = []): array {
        if (!$this->isAvailable()) {
            return [];
        }
        
        try {
            $itemNames = array_map(function($item) {
                return $item['name'] ?? (is_string($item) ? $item : '');
            }, $orderItems);
            
            $itemNamesStr = implode(', ', array_filter($itemNames));
            
            // Get available categories for suggestions
            $desserts = [];
            $drinks = [];
            $appetizers = [];
            
            foreach ($availableMenuItems as $item) {
                $category = strtolower($item['category_name'] ?? '');
                $name = $item['name'] ?? '';
                $price = floatval($item['price'] ?? 0);
                
                if (strpos($category, 'tatlı') !== false || strpos($category, 'dessert') !== false) {
                    $desserts[] = ['name' => $name, 'price' => $price];
                } elseif (strpos($category, 'içecek') !== false || strpos($category, 'drink') !== false || strpos($category, 'beverage') !== false) {
                    $drinks[] = ['name' => $name, 'price' => $price];
                } elseif (strpos($category, 'aperitif') !== false || strpos($category, 'appetizer') !== false || strpos($category, 'başlangıç') !== false) {
                    $appetizers[] = ['name' => $name, 'price' => $price];
                }
            }
            
            $prompt = "Sen profesyonel bir restoran garsonusun. Müşterinin siparişini analiz et ve müşteriye önerilerde bulun.

Müşterinin Siparişi: {$itemNamesStr}
Sipariş Tutarı: " . number_format($orderTotal, 2) . " ₺

Mevcut Menü Öğeleri:
Tatlılar: " . implode(', ', array_column($desserts, 'name')) . "
İçecekler: " . implode(', ', array_column($drinks, 'name')) . "
Aperitifler: " . implode(', ', array_column($appetizers, 'name')) . "

GÖREVİN:
Müşterinin siparişine uygun, iştah açıcı ve satış odaklı öneriler yap. Örneğin:
- Ana yemek sipariş ettiyse tatlı öner
- Yemek sipariş ettiyse içecek öner (çay, kahve, ayran vb.)
- Sadece içecek sipariş ettiyse aperitif veya tatlı öner
- Yüksek tutarlı siparişte premium öneriler yap

KURALLAR:
1. Maksimum 2-3 öneri yap
2. Öneriler kısa, samimi ve satış odaklı olsun
3. Müşterinin siparişine uygun öneriler seç
4. Türkçe yaz, samimi ve profesyonel dil kullan
5. Sadece JSON formatında döndür

DÖNDÜRÜLECEK FORMAT:
{
    \"recommendations\": [
        {
            \"type\": \"dessert\",
            \"message\": \"Ana yemeğinizin ardından lezzetli bir tatlı ile tamamlamak ister misiniz?\",
            \"suggestions\": [\"Baklava\", \"Sütlaç\"]
        },
        {
            \"type\": \"drink\",
            \"message\": \"Yemeğinizin yanında taze bir içecek ne dersiniz?\",
            \"suggestions\": [\"Türk Kahvesi\", \"Çay\"]
        }
    ]
}

SADECE JSON DÖNDÜR, başka hiçbir şey yazma.";

            // Check cache
            $cacheKey = $this->getCacheKey($prompt);
            $cached = $this->getCached($cacheKey);
            if ($cached !== null) {
                $decoded = json_decode($cached, true);
                if (is_array($decoded) && isset($decoded['recommendations'])) {
                    return $decoded['recommendations'];
                }
            }
            
            // Check rate limit
            if (!$this->checkRateLimit()) {
                return [];
            }
            
            $response = $this->callGeminiAPI('gemini-2.5-flash', $prompt);
            
            if ($response) {
                $response = trim($response);
                // Try to extract JSON
                if (preg_match('/\{.*\}/s', $response, $matches)) {
                    $json = $matches[0];
                    $decoded = json_decode($json, true);
                    if (is_array($decoded) && isset($decoded['recommendations'])) {
                        $this->setCached($cacheKey, $json);
                        $this->updateRateLimit();
                        return $decoded['recommendations'];
                    }
                }
            }
            
            return [];
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("Gemini Customer Recommendations Error: " . $e->getMessage());
            }
            return [];
        }
    }
    
    /**
     * Generate receipt template for Xprinter XP-Q805K thermal printer using AI
     * @param string $businessName Business name
     * @param string $businessAddress Business address (optional)
     * @param string $businessPhone Business phone (optional)
     * @return string ESC/POS formatted receipt template
     */
    public function generateReceiptTemplate(string $businessName, string $businessAddress = '', string $businessPhone = ''): string {
        if (!$this->isAvailable()) {
            throw new \Exception('Gemini AI servisi mevcut değil. Lütfen GEMINI_API_KEY yapılandırmasını kontrol edin.');
        }
        
        try {
            $prompt = "Sen bir termal yazıcı fiş tasarımcısısın. Xprinter XP-Q805K modeli için profesyonel bir adisyon fişi şablonu oluştur.

YAZICI ÖZELLİKLERİ:
- Kağıt genişliği: 80mm
- Karakter genişliği: 48 karakter (normal font), 24 karakter (double width)
- Font: Monospace (Courier New benzeri)
- ESC/POS komut seti desteği

İŞLETME BİLGİLERİ:
- İşletme Adı: {$businessName}";
            
            if (!empty($businessAddress)) {
                $prompt .= "\n- Adres: {$businessAddress}";
            }
            
            if (!empty($businessPhone)) {
                $prompt .= "\n- Telefon: {$businessPhone}";
            }
            
            $prompt .= "

GÖREVİN:
Xprinter XP-Q805K için ESC/POS formatında profesyonel bir adisyon fişi şablonu oluştur. Şablon şunları içermelidir:

1. BAŞLIK BÖLÜMÜ:
   - İşletme adı (ortalanmış, büyük font)
   - Adres (varsa, ortalanmış)
   - Telefon (varsa, ortalanmış)
   - Ayırıcı çizgi

2. FİŞ BİLGİLERİ:
   - Fiş No: {{receipt_number}}
   - Sipariş: {{order_id}}
   - Masa: {{table_name}}
   - Tarih: {{order_date}}
   - Ayırıcı çizgi

3. ÜRÜNLER BÖLÜMÜ:
   - Başlık satırı: Ürün | Adet | Tutar
   - Ürün listesi: {{items}} (her ürün için: ad, adet, fiyat)
   - Ayırıcı çizgi

4. TOPLAM BÖLÜMÜ:
   - Ara Toplam: {{subtotal}}
   - Servis Ücreti: {{service_charge}} (varsa)
   - KDV: {{tax_amount}} (varsa)
   - İndirim: {{discount_amount}} (varsa)
   - ÇİFT ÇİZGİ AYIRICI
   - TOPLAM: {{total_amount}} (kalın, büyük font)
   - ÇİFT ÇİZGİ AYIRICI

5. ÖDEME BÖLÜMÜ:
   - Ödeme Yöntemi: {{payment_method}}
   - Ödeme Detayları: {{payment_breakdown}} (varsa)

6. ALT BİLGİ:
   - {{footer_text}} (varsa, ortalanmış)
   - Teşekkür mesajı (ortalanmış)
   - Kağıt kesme komutu

ESC/POS KOMUTLARI:
- \\x1B\\x40 = Yazıcıyı başlat
- \\x1B\\x61\\x01 = Ortala
- \\x1B\\x61\\x00 = Sola hizala
- \\x1B\\x64\\x03 = 3 satır boşluk
- \\x1D\\x21\\x11 = Çift genişlik + yükseklik
- \\x1D\\x21\\x00 = Normal font
- \\x1B\\x45\\x01 = Kalın yazı
- \\x1B\\x45\\x00 = Normal yazı
- \\x1D\\x56\\x00 = Kağıt kes

KURALLAR:
1. Her satır maksimum 48 karakter olmalı
2. ESC/POS komutlarını doğru şekilde kullan
3. Template değişkenlerini {{variable_name}} formatında kullan
4. Türkçe karakterleri destekle
5. Profesyonel ve okunabilir bir tasarım yap
6. Sadece ESC/POS komutları ve template değişkenleri kullan, HTML/CSS kullanma
7. Kağıt kesme komutunu en sona ekle

ÖRNEK FORMAT:
\\x1B\\x40
\\x1B\\x61\\x01
\\x1D\\x21\\x11
{{business_name}}
\\x1D\\x21\\x00
\\x1B\\x61\\x00
\\x1B\\x64\\x01
{{business_address}}
{{business_phone}}
\\x1B\\x64\\x01
--------------------------------
Fiş No: {{receipt_number}}
...

SADECE ESC/POS FORMATINDA ŞABLON DÖNDÜR, başka hiçbir açıklama yapma.";

            // Check cache
            $cacheKey = $this->getCacheKey($prompt . $businessName);
            $cached = $this->getCached($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
            
            // Check rate limit
            if (!$this->checkRateLimit()) {
                throw new \Exception('Rate limit aşıldı. Lütfen daha sonra tekrar deneyin.');
            }
            
            $response = $this->callGeminiAPI('gemini-2.5-flash', $prompt);
            
            if ($response) {
                // Clean response - remove markdown code blocks if present
                $response = trim($response);
                $response = preg_replace('/^```[\w]*\n/', '', $response);
                $response = preg_replace('/\n```$/', '', $response);
                $response = trim($response);
                
                // Cache the response
                $this->setCached($cacheKey, $response);
                $this->updateRateLimit();
                
                return $response;
            }
            
            throw new \Exception('Şablon oluşturulamadı.');
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error("Gemini Receipt Template Error: " . $e->getMessage());
            }
            throw $e;
        }
    }

    /**
     * Call Opusmax Claude API as a fallback
     * @param string $prompt Prompt text
     * @return string Response text
     * @throws \Exception
     */
    private function callOpusmaxAPI($prompt) {
        try {
            $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
            $opusmaxApiKey = $settingsService->getOpusmaxApiKey();
        } catch (\Exception $e) {
            $opusmaxApiKey = '';
        }

        if (empty($opusmaxApiKey)) {
            throw new \Exception("Opusmax API Key is not configured in settings.");
        }

        $url = 'https://api.opusmax.live/v1/messages';
        
        $data = [
            'model' => 'claude-sonnet-4-6',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'x-api-key: ' . $opusmaxApiKey,
            'content-type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            throw new \Exception("Opusmax CURL Error: {$curlError}");
        }
        
        if ($httpCode !== 200) {
            throw new \Exception("Opusmax API Error: HTTP {$httpCode} - " . ($response ?: 'Empty Response'));
        }
        
        $result = json_decode($response, true);
        if (!$result) {
            throw new \Exception("Invalid JSON response from Opusmax API");
        }

        if (isset($result['content'][0]['text'])) {
            return $result['content'][0]['text'];
        }

        if (isset($result['completion'])) {
            return $result['completion'];
        }

        throw new \Exception("Could not parse Opusmax response format: " . json_encode($result));
    }
}

