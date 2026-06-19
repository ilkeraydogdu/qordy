<?php
namespace App\Core\Traits;

/**
 * HandlesValidation Trait
 * Provides validation methods for controllers
 */
trait HandlesValidation {
    /**
     * Validate request data using ValidationService
     * @param array $data Request data to validate
     * @param string $ruleSet Rule set name from validation_rules.php (e.g., 'menu_item', 'order', 'table')
     * @return array ['valid' => bool, 'errors' => array, 'data' => array] Validated and sanitized data
     */
    protected function validateRequestData(array $data, string $ruleSet): array {
        $validationService = \App\Core\DependencyFactory::getValidationService();
        return $validationService->validateRequest($data, $ruleSet);
    }
    
    /**
     * Validate and return API error if validation fails
     * @param array $data Request data to validate
     * @param string $ruleSet Rule set name from validation_rules.php
     * @param string|null $errorMessage Custom error message
     * @return array|null Validated data if valid, null if invalid (response already sent)
     */
    protected function validateRequestDataOrFail(array $data, string $ruleSet, ?string $errorMessage = null): ?array {
        $result = $this->validateRequestData($data, $ruleSet);
        
        if (!$result['valid']) {
            $firstError = reset($result['errors']);
            $errorMsg = $errorMessage ?? (is_array($firstError) ? reset($firstError) : $firstError) ?? 'Validation failed';
            $this->apiResponse([
                'success' => false,
                'error' => $errorMsg,
                'errors' => $result['errors'],
                'code' => 'VALIDATION_ERROR'
            ], 400);
            return null;
        }
        
        return $result['data'];
    }
    
    /**
     * Validate and return false if validation fails (for backward compatibility)
     * @param array $data Request data to validate
     * @param string $ruleSet Rule set name from validation_rules.php
     * @return bool True if valid, false if invalid (response already sent)
     */
    protected function validateOrFail(array $data, string $ruleSet): bool {
        $result = $this->validateRequestData($data, $ruleSet);
        
        if (!$result['valid']) {
            // Log validation errors for debugging
            error_log("Validation failed for ruleSet: {$ruleSet}");
            error_log("Validation errors: " . json_encode($result['errors']));
            error_log("Data being validated: " . json_encode($data));
            
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('Validation failed', [
                    'rule_set' => $ruleSet,
                    'errors' => $result['errors'],
                    'data' => $data
                ]);
            }
            
            $firstError = reset($result['errors']);
            $errorMsg = is_array($firstError) ? reset($firstError) : $firstError;
            $errorMsg = $errorMsg ?? 'Validation failed';
            
            if (method_exists($this, 'toastNotificationService') && isset($this->toastNotificationService)) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
            } else {
                $this->apiResponse([
                    'success' => false,
                    'error' => $errorMsg,
                    'errors' => $result['errors'],
                    'code' => 'VALIDATION_ERROR'
                ], 400);
            }
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate input using firewall
     * @param array $data Data to validate
     * @param array $rules Validation rules
     * @return bool True if valid, false otherwise
     */
    protected function validateInput(array $data, array $rules): bool {
        if (!isset($this->firewall)) {
            return false;
        }
        return $this->firewall->validateRequest($data, $rules);
    }
    
    /**
     * Get validation errors from firewall
     * @return array Array of validation errors
     */
    protected function getValidationErrors(): array {
        if (!isset($this->firewall)) {
            return [];
        }
        return $this->firewall->getValidator()->getErrors();
    }
    
    /**
     * Sanitize input data
     * @param mixed $data Data to sanitize
     * @param string $type Type of data ('string', 'int', 'float', 'email', etc.)
     * @return mixed Sanitized data
     */
    protected function sanitizeInput($data, string $type = 'string') {
        if (!isset($this->firewall)) {
            return $data;
        }
        return $this->firewall->sanitizeInput($data, $type);
    }
    
    /**
     * Sanitize array of data
     * @param array $data Array to sanitize
     * @param array $rules Sanitization rules
     * @return array Sanitized array
     */
    protected function sanitizeArray(array $data, array $rules = []): array {
        if (!isset($this->firewall)) {
            return $data;
        }
        return $this->firewall->sanitizeArray($data, $rules);
    }
    
    /**
     * Send API response (must be implemented by using class)
     * @param array $data Response data
     * @param int $statusCode HTTP status code
     * @return void
     */
    // apiResponse is provided by HandlesAPIResponse trait
}

