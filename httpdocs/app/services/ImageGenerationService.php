<?php
namespace App\Services;

/**
 * Image Generation Service
 * Uses Hugging Face Stable Diffusion API for free AI image generation
 * Generates professional restaurant food photography based on product information
 */
class ImageGenerationService {
    private $apiUrl = 'https://router.huggingface.co/models/stabilityai/stable-diffusion-xl-base-1.0';
    private $cacheDir = __DIR__ . '/../../storage/cache/images';
    private $tempDir = __DIR__ . '/../../storage/temp/images';
    private $rateLimitFile = __DIR__ . '/../../storage/rate_limits/image_generation.json';
    private $maxRequestsPerMinute = 5; // Hugging Face free tier limit
    private $maxRequestsPerHour = 30;
    
    public function __construct() {
        // Create cache directory if it doesn't exist
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
        
        // Create temp directory if it doesn't exist
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
        
        // Create rate limit directory if it doesn't exist
        $rateLimitDir = dirname($this->rateLimitFile);
        if (!is_dir($rateLimitDir)) {
            mkdir($rateLimitDir, 0755, true);
        }
    }
    
    /**
     * Check if service is available
     * @return bool
     */
    public function isAvailable(): bool {
        return $this->checkRateLimit();
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
                $now = time();
                // Keep only recent requests
                $data['minute'] = array_filter($existing['minute'] ?? [], function($time) use ($now) {
                    return ($now - $time) < 60;
                });
                $data['hour'] = array_filter($existing['hour'] ?? [], function($time) use ($now) {
                    return ($now - $time) < 3600;
                });
            }
        }
        
        // Add current request
        $data['minute'][] = time();
        $data['hour'][] = time();
        
        file_put_contents($this->rateLimitFile, json_encode($data));
    }
    
    /**
     * Build professional prompt for food photography
     * Uses Gemini AI to improve the prompt if available
     * @param string $productName Product name
     * @param string $description Product description
     * @param string $categoryName Category name (optional)
     * @param array $ingredients Ingredients list (optional)
     * @return array Array with 'prompt' and 'negative_prompt' keys
     */
    public function buildPrompt(string $productName, string $description = '', string $categoryName = '', array $ingredients = []): array {
        // Try to use Gemini to improve the prompt
        try {
            $geminiService = \App\Core\DependencyFactory::getGeminiService();
            if ($geminiService && $geminiService->isAvailable() && method_exists($geminiService, 'improveImagePrompt')) {
                $improvedPrompt = $geminiService->improveImagePrompt($productName, $description, $categoryName, $ingredients);
                if ($improvedPrompt && !empty(trim($improvedPrompt))) {
                    // Use Gemini-improved prompt
                    $negativePrompt = "blurry, low quality, distorted, unappetizing, dark lighting, amateur photography, text overlay, watermark";
                    return [
                        'prompt' => trim($improvedPrompt),
                        'negative_prompt' => $negativePrompt
                    ];
                }
            }
        } catch (\Exception $e) {
            // Fallback to default prompt if Gemini fails
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('Failed to improve prompt with Gemini: ' . $e->getMessage());
            }
        }
        
        // Fallback: Base prompt structure (original implementation)
        $prompt = "Professional restaurant food photography of {$productName}";
        
        // Add description details if available
        if (!empty($description)) {
            // Extract key details from description (first 100 chars)
            $descSnippet = mb_substr(strip_tags($description), 0, 100);
            $prompt .= ", {$descSnippet}";
        }
        
        // Add ingredients if available
        if (!empty($ingredients) && is_array($ingredients)) {
            $ingredientList = implode(', ', array_slice($ingredients, 0, 5)); // Max 5 ingredients
            $prompt .= ", ingredients: {$ingredientList}";
        }
        
        // Add category context
        if (!empty($categoryName)) {
            $prompt .= ", {$categoryName} category";
        }
        
        // Professional photography style keywords
        $prompt .= ", high quality, realistic, appetizing, restaurant menu style, studio lighting, food styling, professional photography, 4K quality, shallow depth of field, vibrant colors, commercial food photography";
        
        // Negative prompt (what to avoid)
        $negativePrompt = "blurry, low quality, distorted, unappetizing, dark lighting, amateur photography, text overlay, watermark";
        
        return [
            'prompt' => $prompt,
            'negative_prompt' => $negativePrompt
        ];
    }
    
    /**
     * Generate image from prompt
     * @param string $prompt Image generation prompt
     * @param string $negativePrompt Negative prompt (optional)
     * @return array Result with success status and image URL/path
     */
    public function generateImage(string $prompt, string $negativePrompt = ''): array {
        if (!$this->isAvailable()) {
            return [
                'success' => false,
                'error' => 'Rate limit exceeded. Please try again later.'
            ];
        }
        
        try {
            // Prepare API request
            $data = [
                'inputs' => $prompt,
                'parameters' => [
                    'num_inference_steps' => 30,
                    'guidance_scale' => 7.5,
                    'width' => 1024,
                    'height' => 1024
                ]
            ];
            
            if (!empty($negativePrompt)) {
                $data['parameters']['negative_prompt'] = $negativePrompt;
            }
            
            // Make API request
            $ch = curl_init($this->apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: image/png'
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60); // 60 second timeout (image generation can take time)
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);
            
            if ($curlError) {
                throw new \Exception("CURL Error: {$curlError}");
            }
            
            // Check if response is an image
            if (strpos($contentType, 'image') !== false && $httpCode === 200) {
                // Save image to temp directory
                $filename = 'generated_' . uniqid() . '_' . time() . '.png';
                $filepath = $this->tempDir . '/' . $filename;
                
                if (file_put_contents($filepath, $response) === false) {
                    throw new \Exception('Failed to save generated image');
                }
                
                // Update rate limit
                $this->updateRateLimit();
                
                // Return relative URL for the image (to avoid long URLs with BASE_URL)
                $relativePath = '/storage/temp/images/' . $filename;
                
                // Use relative path instead of full URL to avoid length issues
                // Frontend will prepend BASE_URL when displaying
                return [
                    'success' => true,
                    'image_url' => $relativePath, // Relative path instead of full URL
                    'full_url' => BASE_URL . $relativePath, // Full URL for reference
                    'filepath' => $filepath,
                    'filename' => $filename
                ];
            } else {
                // Check if it's a JSON error response
                $errorData = json_decode($response, true);
                if ($errorData && isset($errorData['error'])) {
                    throw new \Exception($errorData['error']);
                }
                
                throw new \Exception("API returned HTTP {$httpCode}. Response: " . substr($response, 0, 200));
            }
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('ImageGenerationService error: ' . $e->getMessage());
            }
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate image for menu item
     * @param string $productName Product name
     * @param string $description Product description
     * @param string $categoryName Category name
     * @param array $ingredients Ingredients list
     * @return array Result with success status and image URL/path
     */
    public function generateMenuItemImage(string $productName, string $description = '', string $categoryName = '', array $ingredients = []): array {
        try {
            $promptData = $this->buildPrompt($productName, $description, $categoryName, $ingredients);
            
            // Validate prompt data structure
            if (!is_array($promptData) || !isset($promptData['prompt']) || !isset($promptData['negative_prompt'])) {
                return [
                    'success' => false,
                    'error' => 'Invalid prompt data structure'
                ];
            }
            
            // Ensure prompt and negative_prompt are strings
            $prompt = is_string($promptData['prompt']) ? $promptData['prompt'] : (string)$promptData['prompt'];
            $negativePrompt = isset($promptData['negative_prompt']) && is_string($promptData['negative_prompt']) 
                ? $promptData['negative_prompt'] 
                : '';
            
            return $this->generateImage($prompt, $negativePrompt);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('ImageGenerationService::generateMenuItemImage error: ' . $e->getMessage());
            }
            return [
                'success' => false,
                'error' => 'Failed to generate image: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Clean up old temporary images (older than 24 hours)
     */
    public function cleanupTempImages(): void {
        if (!is_dir($this->tempDir)) {
            return;
        }
        
        $files = glob($this->tempDir . '/generated_*.png');
        $now = time();
        $maxAge = 24 * 3600; // 24 hours
        
        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file)) > $maxAge) {
                @unlink($file);
            }
        }
    }
}

