<?php
namespace App\Services;

require_once __DIR__ . '/../helpers/functions.php';

/**
 * Toast Notification Service - MVC, OOP, Centralized Notification Management
 * Handles all toast notifications, flash messages, and API responses
 * Fully dynamic, no hardcoded messages, uses TranslationService
 */
class ToastNotificationService {
    private $translationService;
    private static $instance = null;
    
    /**
     * Singleton pattern
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->translationService = getTranslationService();
    }
    
    /**
     * Show success notification
     * @param string $key - Translation key (e.g., 'notifications.success.order_updated')
     * @param array $params - Parameters for string replacement
     * @return string - Translated message
     */
    public function showSuccess($key, $params = []) {
        return $this->translationService->translate($key, null, $params);
    }
    
    /**
     * Show error notification
     * @param string $key - Translation key (e.g., 'notifications.error.invalid_data')
     * @param array $params - Parameters for string replacement
     * @return string - Translated message
     */
    public function showError($key, $params = []) {
        return $this->translationService->translate($key, null, $params);
    }
    
    /**
     * Show warning notification
     * @param string $key - Translation key (e.g., 'notifications.warning.confirm_action')
     * @param array $params - Parameters for string replacement
     * @return string - Translated message
     */
    public function showWarning($key, $params = []) {
        return $this->translationService->translate($key, null, $params);
    }
    
    /**
     * Show info notification
     * @param string $key - Translation key (e.g., 'notifications.info.loading')
     * @param array $params - Parameters for string replacement
     * @return string - Translated message
     */
    public function showInfo($key, $params = []) {
        return $this->translationService->translate($key, null, $params);
    }
    
    /**
     * Set flash message in session
     * @param string $type - Type: 'success', 'error', 'warning', 'info'
     * @param string $key - Translation key
     * @param array $params - Parameters for string replacement
     * @return void
     */
    public function setFlash($type, $key, $params = []) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $validTypes = ['success', 'error', 'warning', 'info'];
        if (!in_array($type, $validTypes)) {
            $type = 'info';
        }
        
        // Try to translate the key
        $message = $this->translationService->translate($key, null, $params);
        
        // If translation returns null or empty, use the key as direct message
        // This allows both translation keys and direct message strings
        if ($message === null || $message === '') {
            $message = $key;
        }
        
        $_SESSION[$type] = $message;
    }
    
    /**
     * Get flash messages from session and clear them
     * @return array - Array of flash messages ['success' => '...', 'error' => '...', etc.]
     */
    public function getFlashMessages() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $messages = [];
        $types = ['success', 'error', 'warning', 'info'];
        
        foreach ($types as $type) {
            if (isset($_SESSION[$type])) {
                $messages[$type] = $_SESSION[$type];
                unset($_SESSION[$type]);
            }
        }
        
        return $messages;
    }
    
    /**
     * Get flash message for a specific type
     * @param string $type - Type: 'success', 'error', 'warning', 'info'
     * @return string|null - Message or null if not set
     */
    public function getFlashMessage($type) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (isset($_SESSION[$type])) {
            $message = $_SESSION[$type];
            unset($_SESSION[$type]);
            return $message;
        }
        
        return null;
    }
    
    /**
     * Return API response with translated message
     * @param string $type - Type: 'success', 'error', 'warning', 'info'
     * @param string $key - Translation key
     * @param array $params - Parameters for string replacement
     * @param int $statusCode - HTTP status code
     * @param array $additionalData - Additional data to include in response
     * @return array - API response array
     */
    public function apiResponse($type, $key, $params = [], $statusCode = 200, $additionalData = []) {
        $validTypes = ['success', 'error', 'warning', 'info'];
        if (!in_array($type, $validTypes)) {
            $type = 'info';
        }
        
        $message = $this->translationService->translate($key, null, $params);
        
        // If translation is null or empty, generate fallback message
        if ($message === null || $message === '') {
            $message = $this->generateFallbackMessage($key, $type);
        }
        
        $response = array_merge([
            'success' => ($type === 'success'),
            'message' => $message,
            'translation_key' => $key
        ], $additionalData);
        
        // For error responses, also include 'error' key for backward compatibility
        if ($type === 'error') {
            $response['error'] = $message;
            $response['success'] = false;
        } else {
            $response['success'] = true;
        }
        
        // CRITICAL: Ensure success field is always set correctly
        // Frontend checks result.success === true, so we must ensure it's boolean true, not just truthy
        if ($type === 'success') {
            $response['success'] = true;
        } elseif ($type === 'error' || $type === 'warning') {
            $response['success'] = false;
        }
        
        return [
            'response' => $response,
            'status_code' => $statusCode
        ];
    }
    
    /**
     * Generate fallback message when translation is not found
     * @param string $key - Translation key
     * @param string $type - Message type
     * @return string - Fallback message
     */
    private function generateFallbackMessage($key, $type) {
        // Get current language
        $lang = $this->translationService->getCurrentLanguage() ?? 'tr';
        
        // Try to extract meaningful message from translation key
        $keyParts = explode('.', $key);
        $lastPart = end($keyParts);
        
        // Generate user-friendly message based on key parts
        $fallbackMessages = [
            'tr' => [
                'missing_fields' => 'Lütfen tüm gerekli alanları doldurun.',
                'missing_category_name' => 'Kategori adı gereklidir. Lütfen kategori adını (Türkçe) girin.',
                'missing_product_name' => 'Ürün adı gereklidir. Lütfen ürün adını girin.',
                'unauthorized' => 'Bu işlem için yetkiniz bulunmamaktadır.',
                'create_failed' => 'Kayıt oluşturulurken bir hata oluştu. Lütfen tekrar deneyin.',
                'update_failed' => 'Kayıt güncellenirken bir hata oluştu. Lütfen tekrar deneyin.',
                'delete_failed' => 'Kayıt silinirken bir hata oluştu. Lütfen tekrar deneyin.',
                'delete_failed_active' => 'Rol hala aktif durumda olduğu için silinemedi. Lütfen önce rolü devre dışı bırakın.',
                'invalid_data' => 'Geçersiz veri. Lütfen kontrol edip tekrar deneyin.',
                'category_exists' => 'Bu isimde bir kategori zaten mevcut. Lütfen farklı bir isim kullanın.',
                'category_not_found' => 'Kategori bulunamadı.',
            ],
            'en' => [
                'missing_fields' => 'Please fill all required fields.',
                'missing_category_name' => 'Category name is required. Please enter the category name (Turkish).',
                'missing_product_name' => 'Product name is required. Please enter the product name.',
                'unauthorized' => 'You do not have permission for this operation.',
                'create_failed' => 'An error occurred while creating the record. Please try again.',
                'update_failed' => 'An error occurred while updating the record. Please try again.',
                'delete_failed' => 'An error occurred while deleting the record. Please try again.',
                'delete_failed_active' => 'The role could not be deleted because it is still active. Please deactivate the role first.',
                'invalid_data' => 'Invalid data. Please check and try again.',
                'category_exists' => 'A category with this name already exists. Please use a different name.',
                'category_not_found' => 'Category not found.',
            ]
        ];
        
        // Check if we have a specific fallback for this key part
        if (isset($fallbackMessages[$lang][$lastPart])) {
            return $fallbackMessages[$lang][$lastPart];
        }
        
        // Generic fallback based on type
        $genericFallbacks = [
            'tr' => [
                'error' => 'Bir hata oluştu.',
                'warning' => 'Uyarı: İşlem tamamlanamadı.',
                'success' => 'İşlem başarılı.',
                'info' => 'Bilgi: İşlem devam ediyor.',
            ],
            'en' => [
                'error' => 'An error occurred.',
                'warning' => 'Warning: Operation could not be completed.',
                'success' => 'Operation successful.',
                'info' => 'Info: Operation in progress.',
            ]
        ];
        
        return $genericFallbacks[$lang][$type] ?? $genericFallbacks['tr'][$type];
    }
    
    /**
     * Send API response and exit (for use in controllers)
     * @param string $type - Type: 'success', 'error', 'warning', 'info'
     * @param string $key - Translation key or direct message string
     * @param array $params - Parameters for string replacement
     * @param int $statusCode - HTTP status code
     * @param array $additionalData - Additional data to include in response
     * @return void
     */
    public function sendApiResponse($type, $key, $params = [], $statusCode = 200, $additionalData = []) {
        // Check if $key is a direct message string (contains spaces, colons, or is longer than typical translation keys)
        // Translation keys are usually in format like 'notifications.error.something' (no spaces)
        $isDirectMessage = (
            strpos($key, ' ') !== false || 
            strpos($key, ':') !== false ||
            (strlen($key) > 50 && strpos($key, 'notifications.') !== 0 && strpos($key, 'errors.') !== 0 && strpos($key, 'pos.') !== 0)
        );
        
        if ($isDirectMessage && empty($params)) {
            // Direct message string - use it as-is
            $message = $key;
            
            // Build response for direct message
            // CRITICAL: Ensure success field is boolean, not just truthy
            $response = [
                'success' => ($type === 'success') ? true : false,
                'message' => $message
            ];
            
            // Merge additional data
            $response = array_merge($response, $additionalData);
            
            // For error responses, also include 'error' key for backward compatibility
            if ($type === 'error' || $type === 'warning') {
                $response['error'] = $message;
                $response['success'] = false;
            } else {
                $response['success'] = true;
            }
            
            // CRITICAL: Ensure success is always boolean
            $response['success'] = ($type === 'success') ? true : false;
            
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
            echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        }
        
        // Translation key - translate it
        $result = $this->apiResponse($type, $key, $params, $statusCode, $additionalData);
        
        http_response_code($result['status_code']);
        header('Content-Type: application/json');
        echo json_encode($result['response'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    /**
     * Translate a message key directly
     * @param string $key - Translation key
     * @param array $params - Parameters for string replacement
     * @return string - Translated message
     */
    public function translate($key, $params = []) {
        return $this->translationService->translate($key, null, $params);
    }
}

