<?php
namespace App\Core\Validators;

require_once __DIR__ . '/Validator.php';

/**
 * Request Validator
 * Validates API requests and form submissions using centralized rules
 */
class RequestValidator {
    private $validator;
    private $rules;
    
    public function __construct() {
        $this->validator = new Validator();
        
        // Load validation rules if file exists
        $rulesFile = __DIR__ . '/../../config/validation_rules.php';
        if (file_exists($rulesFile)) {
            $this->rules = include $rulesFile;
        } else {
            $this->rules = [];
        }
    }
    
    /**
     * Validate request data against a rule set
     * @param array $data Data to validate
     * @param string $ruleSet Rule set name (e.g., 'order', 'menu_item')
     * @return bool True if valid, false otherwise
     */
    public function validate(array $data, string $ruleSet): bool {
        if (!isset($this->rules[$ruleSet])) {
            $availableRules = !empty($this->rules) ? implode(', ', array_keys($this->rules)) : 'none';
            error_log("Validation rule set '{$ruleSet}' not found. Available rule sets: {$availableRules}");
            return false;
        }
        
        $rules = $this->rules[$ruleSet];
        return $this->validator->validate($data, $rules);
    }
    
    /**
     * Get validation errors
     * @return array
     */
    public function getErrors(): array {
        return $this->validator->getErrors();
    }
    
    /**
     * Get validated and sanitized data
     * @return array
     */
    public function getValidatedData(): array {
        return $this->validator->getValidatedData();
    }
    
    /**
     * Get first error message
     * @param string|null $field Field name (optional)
     * @return string|null
     */
    public function getFirstError(?string $field = null): ?string {
        $errors = $this->validator->getErrors();
        if ($field && isset($errors[$field])) {
            return is_array($errors[$field]) ? $errors[$field][0] : $errors[$field];
        }
        return reset($errors) ? (is_array(reset($errors)) ? reset($errors)[0] : reset($errors)) : null;
    }
    
    /**
     * Check if validation has errors
     * @return bool
     */
    public function hasErrors(): bool {
        return !empty($this->validator->getErrors());
    }
    
    /**
     * Validate nested array (e.g., order items)
     * @param array $dataArray Array of data items
     * @param string $ruleSet Rule set name
     * @return bool True if all items are valid
     */
    public function validateArray(array $dataArray, string $ruleSet): bool {
        if (!isset($this->rules[$ruleSet])) {
            return false;
        }
        
        $rules = $this->rules[$ruleSet];
        $allValid = true;
        
        foreach ($dataArray as $index => $item) {
            // Adjust rules for nested items (e.g., items.*.menu_item_id)
            $nestedRules = [];
            foreach ($rules as $field => $rule) {
                $nestedField = $field;
                if (strpos($field, '*') === false) {
                    $nestedField = $field;
                }
                $nestedRules[$nestedField] = $rule;
            }
            
            if (!$this->validator->validate($item, $nestedRules)) {
                $allValid = false;
            }
        }
        
        return $allValid;
    }
}
