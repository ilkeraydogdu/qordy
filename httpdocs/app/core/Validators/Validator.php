<?php
namespace App\Core\Validators;

/**
 * Request Validator
 * Validates and sanitizes request data based on schemas
 */
class Validator {
    private $errors = [];
    private $data = [];
    
    private $validatedData = [];
    
    /**
     * Validate data against schema
     * @param array $data
     * @param array $rules
     * @return bool
     */
    public function validate(array $data, array $rules): bool {
        $this->errors = [];
        $this->data = [];
        $this->validatedData = [];
        
        foreach ($rules as $field => $ruleString) {
            $value = $data[$field] ?? null;
            $rulesArray = explode('|', $ruleString);
            
            foreach ($rulesArray as $rule) {
                $ruleParts = explode(':', $rule);
                $ruleName = $ruleParts[0];
                $ruleValue = $ruleParts[1] ?? null;
                
                if (!$this->applyRule($field, $value, $ruleName, $ruleValue, $data)) {
                    break; // Stop checking other rules for this field if one fails
                }
            }
            
            // Sanitize and store valid data
            if (!isset($this->errors[$field])) {
                $sanitizedValue = $this->sanitizeField($field, $value, $rulesArray);
                $this->data[$field] = $sanitizedValue;
                $this->validatedData[$field] = $sanitizedValue;
            }
        }
        
        return empty($this->errors);
    }
    
    /**
     * Get validation errors
     * @return array
     */
    public function getErrors(): array {
        return $this->errors;
    }
    
    /**
     * Get validated and sanitized data
     * @return array
     */
    public function getValidatedData(): array {
        return $this->validatedData;
    }
    
    /**
     * Apply validation rule
     * @param string $field
     * @param mixed $value
     * @param string $ruleName
     * @param string|null $ruleValue
     * @param array $allData
     * @return bool
     */
    private function applyRule(string $field, $value, string $ruleName, ?string $ruleValue, array $allData): bool {
        switch ($ruleName) {
            case 'required':
                // Trim string values for required check
                $checkValue = is_string($value) ? trim($value) : $value;
                if (empty($checkValue) && $checkValue !== '0' && $checkValue !== 0) {
                    $this->errors[$field] = "{$field} alanı zorunludur.";
                    return false;
                }
                break;
                
            case 'string':
                if ($value !== null && !is_string($value)) {
                    $this->errors[$field] = "{$field} alanı metin olmalıdır.";
                    return false;
                }
                break;
                
            case 'integer':
            case 'int':
                if ($value !== null && !is_numeric($value)) {
                    $this->errors[$field] = "{$field} alanı sayı olmalıdır.";
                    return false;
                }
                break;
                
            case 'numeric':
                if ($value !== null && !is_numeric($value)) {
                    $this->errors[$field] = "{$field} alanı sayısal olmalıdır.";
                    return false;
                }
                break;
                
            case 'email':
                // Check if value is not null and not empty string
                if ($value !== null && $value !== '') {
                    $trimmedValue = is_string($value) ? trim($value) : $value;
                    if ($trimmedValue === '' || !filter_var($trimmedValue, FILTER_VALIDATE_EMAIL)) {
                        $this->errors[$field] = "{$field} alanı geçerli bir e-posta adresi olmalıdır.";
                        return false;
                    }
                }
                break;
                
            case 'min':
                // String kontrolünü önce yap (telefon numarası gibi numeric string'ler için)
                if ($value !== null && is_string($value) && strlen($value) < intval($ruleValue)) {
                    $this->errors[$field] = "{$field} alanı en az {$ruleValue} karakter olmalıdır.";
                    return false;
                }
                // Sonra numeric kontrolü yap (sadece numeric ve string olmayan değerler için)
                if ($value !== null && is_numeric($value) && !is_string($value) && floatval($value) < floatval($ruleValue)) {
                    $this->errors[$field] = "{$field} alanı en az {$ruleValue} olmalıdır.";
                    return false;
                }
                break;
                
            case 'max':
                // String kontrolünü önce yap (telefon numarası gibi numeric string'ler için)
                if ($value !== null && is_string($value) && strlen($value) > intval($ruleValue)) {
                    $this->errors[$field] = "{$field} alanı en fazla {$ruleValue} karakter olmalıdır.";
                    return false;
                }
                // Sonra numeric kontrolü yap (sadece numeric ve string olmayan değerler için)
                if ($value !== null && is_numeric($value) && !is_string($value) && floatval($value) > floatval($ruleValue)) {
                    $this->errors[$field] = "{$field} alanı en fazla {$ruleValue} olmalıdır.";
                    return false;
                }
                break;
                
            case 'in':
                $allowedValues = explode(',', $ruleValue);
                if ($value !== null && !in_array($value, $allowedValues)) {
                    $this->errors[$field] = "{$field} alanı geçersiz bir değerdir.";
                    return false;
                }
                break;
                
            case 'regex':
                if ($value !== null && !preg_match($ruleValue, $value)) {
                    $this->errors[$field] = "{$field} alanı geçersiz formattadır.";
                    return false;
                }
                break;
                
            case 'confirmed':
                $confirmField = $field . '_confirmation';
                if ($value !== null && (!isset($allData[$confirmField]) || $value !== $allData[$confirmField])) {
                    $this->errors[$field] = "{$field} alanları eşleşmiyor.";
                    return false;
                }
                break;
        }
        
        return true;
    }
    
    /**
     * Sanitize field value
     * @param string $field
     * @param mixed $value
     * @param array $rules
     * @return mixed
     */
    private function sanitizeField(string $field, $value, array $rules): mixed {
        if ($value === null) {
            return null;
        }
        
        // Convert empty strings to null for optional fields (fields without 'required' rule)
        if (is_string($value) && trim($value) === '' && !in_array('required', $rules)) {
            return null;
        }
        
        // Apply sanitization based on rules
        if (in_array('string', $rules)) {
            $sanitized = Sanitizer::sanitizeString($value);
            // Return null if sanitized result is empty and field is not required
            if ($sanitized === '' && !in_array('required', $rules)) {
                return null;
            }
            return $sanitized;
        }
        
        if (in_array('integer', $rules) || in_array('int', $rules)) {
            return Sanitizer::sanitizeInteger($value);
        }
        
        if (in_array('numeric', $rules)) {
            return Sanitizer::sanitizeFloat($value);
        }
        
        if (in_array('email', $rules)) {
            return Sanitizer::sanitizeEmail($value);
        }
        
        // Default: sanitize as string
        $sanitized = Sanitizer::sanitizeString($value);
        // Return null if sanitized result is empty and field is not required
        if ($sanitized === '' && !in_array('required', $rules)) {
            return null;
        }
        return $sanitized;
    }
    
    /**
     * Get first error message
     * @param string|null $field Field name (optional)
     * @return string|null
     */
    public function getFirstError(?string $field = null): ?string {
        if ($field && isset($this->errors[$field])) {
            return is_array($this->errors[$field]) ? $this->errors[$field][0] : $this->errors[$field];
        }
        return reset($this->errors) ? (is_array(reset($this->errors)) ? reset($this->errors)[0] : reset($this->errors)) : null;
    }
    
    /**
     * Check if validation has errors
     * @return bool
     */
    public function hasErrors(): bool {
        return !empty($this->errors);
    }
    
    /**
     * Get validated data
     * @return array
     */
    public function getValidated(): array {
        return $this->data;
    }
    
    /**
     * Check if validation passed
     * @return bool
     */
    public function passes(): bool {
        return empty($this->errors);
    }
    
    /**
     * Check if validation failed
     * @return bool
     */
    public function fails(): bool {
        return !empty($this->errors);
    }
}

