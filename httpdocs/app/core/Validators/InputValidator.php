<?php
namespace App\Core\Validators;

require_once __DIR__ . '/../../helpers/translations.php';

class InputValidator {
    private $errors = [];
    private $fieldNames = [];
    
    public function __construct() {
        // Field name translations
        $this->fieldNames = [
            'name' => 'ad',
            'price' => 'fiyat',
            'category_id' => 'kategori',
            'description' => 'açıklama',
            'image_url' => 'resim URL',
            'stock' => 'stok',
            'is_available' => 'müsaitlik durumu',
            'email' => 'e-posta',
            'password' => 'şifre',
            'pin' => 'PIN',
            'role' => 'rol',
            'table_id' => 'masa',
            'customer_name' => 'müşteri adı',
            'contact' => 'iletişim',
            'date' => 'tarih',
            'time' => 'saat',
        ];
    }
    
    public function validate(array $data, array $rules): bool {
        $this->errors = [];
        
        foreach ($rules as $field => $ruleString) {
            $rulesArray = explode('|', $ruleString);
            $value = $data[$field] ?? null;
            
            foreach ($rulesArray as $rule) {
                $this->applyRule($field, $value, $rule, $data);
            }
        }
        
        return empty($this->errors);
    }
    
    /**
     * Get Turkish field name
     */
    private function getFieldName(string $field): string {
        return $this->fieldNames[$field] ?? $field;
    }
    
    /**
     * Get current language
     */
    private function getCurrentLanguage(): string {
        if (function_exists('getCurrentLanguage')) {
            return getCurrentLanguage();
        }
        return 'tr';
    }
    
    private function applyRule(string $field, $value, string $rule, array $data): void {
        $ruleParts = explode(':', $rule);
        $ruleName = $ruleParts[0];
        $ruleValue = $ruleParts[1] ?? null;
        
        $fieldName = $this->getFieldName($field);
        $lang = $this->getCurrentLanguage();
        
        switch ($ruleName) {
            case 'required':
                if (empty($value) && $value !== '0' && $value !== 0) {
                    $message = $lang === 'tr' 
                        ? "{$fieldName} alanı zorunludur."
                        : "The {$field} field is required.";
                    $this->addError($field, $message);
                }
                break;
                
            case 'string':
                if (!is_string($value) && $value !== null) {
                    $message = $lang === 'tr'
                        ? "{$fieldName} metin olmalıdır."
                        : "The {$field} must be a string.";
                    $this->addError($field, $message);
                }
                break;
                
            case 'integer':
            case 'int':
                if (!is_numeric($value) && $value !== null) {
                    $message = $lang === 'tr'
                        ? "{$fieldName} tam sayı olmalıdır."
                        : "The {$field} must be an integer.";
                    $this->addError($field, $message);
                }
                break;
                
            case 'numeric':
                if (!is_numeric($value) && $value !== null && $value !== '') {
                    $message = $lang === 'tr'
                        ? "{$fieldName} sayısal olmalıdır."
                        : "The {$field} must be numeric.";
                    $this->addError($field, $message);
                }
                break;
                
            case 'float':
                if (!is_numeric($value) && $value !== null && $value !== '') {
                    $message = $lang === 'tr'
                        ? "{$fieldName} ondalıklı sayı olmalıdır."
                        : "The {$field} must be a float.";
                    $this->addError($field, $message);
                }
                break;
                
            case 'email':
                if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $message = $lang === 'tr'
                        ? "{$fieldName} geçerli bir e-posta adresi olmalıdır."
                        : "The {$field} must be a valid email address.";
                    $this->addError($field, $message);
                }
                break;
                
            case 'url':
                if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $message = $lang === 'tr'
                        ? "{$fieldName} geçerli bir URL olmalıdır."
                        : "The {$field} must be a valid URL.";
                    $this->addError($field, $message);
                }
                break;
                
            case 'min':
                if ($value !== null && $value !== '') {
                    $min = (int)$ruleValue;
                    if (is_numeric($value)) {
                        if ((float)$value < $min) {
                            $message = $lang === 'tr'
                                ? "{$fieldName} en az {$min} olmalıdır."
                                : "The {$field} must be at least {$min}.";
                            $this->addError($field, $message);
                        }
                    } elseif (is_array($value)) {
                        if (count($value) < $min) {
                            $message = $lang === 'tr'
                                ? "{$fieldName} en az {$min} öğe içermelidir."
                                : "The {$field} must have at least {$min} items.";
                            $this->addError($field, $message);
                        }
                    } elseif (is_string($value) && strlen($value) < $min) {
                        $message = $lang === 'tr'
                            ? "{$fieldName} en az {$min} karakter olmalıdır."
                            : "The {$field} must be at least {$min} characters.";
                        $this->addError($field, $message);
                    }
                }
                break;
                
            case 'max':
                if ($value !== null && $value !== '') {
                    $max = (int)$ruleValue;
                    if (is_numeric($value)) {
                        if ((float)$value > $max) {
                            $message = $lang === 'tr'
                                ? "{$fieldName} en fazla {$max} olabilir."
                                : "The {$field} must not exceed {$max}.";
                            $this->addError($field, $message);
                        }
                    } elseif (is_array($value)) {
                        if (count($value) > $max) {
                            $message = $lang === 'tr'
                                ? "{$fieldName} en fazla {$max} öğe içerebilir."
                                : "The {$field} must not exceed {$max} items.";
                            $this->addError($field, $message);
                        }
                    } elseif (is_string($value) && strlen($value) > $max) {
                        $message = $lang === 'tr'
                            ? "{$fieldName} en fazla {$max} karakter olabilir."
                            : "The {$field} must not exceed {$max} characters.";
                        $this->addError($field, $message);
                    }
                }
                break;
                
            case 'length':
                if ($value !== null && $value !== '') {
                    $length = (int)$ruleValue;
                    if (is_array($value)) {
                        if (count($value) !== $length) {
                            $message = $lang === 'tr'
                                ? "{$fieldName} tam olarak {$length} öğe içermelidir."
                                : "The {$field} must have exactly {$length} items.";
                            $this->addError($field, $message);
                        }
                    } elseif (is_string($value) && strlen($value) !== $length) {
                        $message = $lang === 'tr'
                            ? "{$fieldName} tam olarak {$length} karakter olmalıdır."
                            : "The {$field} must be exactly {$length} characters.";
                        $this->addError($field, $message);
                    }
                }
                break;
                
            case 'in':
                if ($value !== null && $value !== '') {
                    $allowed = explode(',', $ruleValue);
                    if (!in_array($value, $allowed)) {
                        $allowedStr = implode(', ', $allowed);
                        $message = $lang === 'tr'
                            ? "{$fieldName} şunlardan biri olmalıdır: {$allowedStr}."
                            : "The {$field} must be one of: {$allowedStr}.";
                        $this->addError($field, $message);
                    }
                }
                break;
                
            case 'array':
                if ($value !== null && !is_array($value)) {
                    $message = $lang === 'tr'
                        ? "{$fieldName} dizi olmalıdır."
                        : "The {$field} must be an array.";
                    $this->addError($field, $message);
                }
                break;
                
            case 'date':
                if ($value !== null && $value !== '') {
                    $date = \DateTime::createFromFormat('Y-m-d', $value);
                    if (!$date || $date->format('Y-m-d') !== $value) {
                        $message = $lang === 'tr'
                            ? "{$fieldName} geçerli bir tarih olmalıdır (Y-m-d formatında)."
                            : "The {$field} must be a valid date (Y-m-d format).";
                        $this->addError($field, $message);
                    }
                }
                break;
                
            case 'boolean':
            case 'bool':
                if ($value !== null && !is_bool($value) && $value !== '0' && $value !== '1' && $value !== 0 && $value !== 1) {
                    $message = $lang === 'tr'
                        ? "{$fieldName} doğru/yanlış değeri olmalıdır."
                        : "The {$field} must be a boolean.";
                    $this->addError($field, $message);
                }
                break;
                
            case 'regex':
                if ($value !== null && $value !== '') {
                    if (!preg_match($ruleValue, $value)) {
                        $message = $lang === 'tr'
                            ? "{$fieldName} formatı geçersizdir."
                            : "The {$field} format is invalid.";
                        $this->addError($field, $message);
                    }
                }
                break;
                
            case 'same':
                $otherFieldName = $this->getFieldName($ruleValue);
                if ($value !== ($data[$ruleValue] ?? null)) {
                    $message = $lang === 'tr'
                        ? "{$fieldName} {$otherFieldName} ile eşleşmelidir."
                        : "The {$field} must match {$ruleValue}.";
                    $this->addError($field, $message);
                }
                break;
                
            case 'different':
                $otherFieldName = $this->getFieldName($ruleValue);
                if ($value === ($data[$ruleValue] ?? null)) {
                    $message = $lang === 'tr'
                        ? "{$fieldName} {$otherFieldName} alanından farklı olmalıdır."
                        : "The {$field} must be different from {$ruleValue}.";
                    $this->addError($field, $message);
                }
                break;
        }
    }
    
    private function addError(string $field, string $message): void {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }
    
    public function getErrors(): array {
        return $this->errors;
    }
    
    public function hasErrors(): bool {
        return !empty($this->errors);
    }
    
    public function getFirstError(string $field = null): ?string {
        if ($field) {
            return $this->errors[$field][0] ?? null;
        }
        
        foreach ($this->errors as $fieldErrors) {
            if (!empty($fieldErrors)) {
                return $fieldErrors[0];
            }
        }
        
        return null;
    }
}

