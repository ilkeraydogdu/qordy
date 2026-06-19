<?php
namespace App\Services;

require_once __DIR__ . '/../core/Validators/InputValidator.php';

class ValidationService {
    private $validator;

    public function __construct() {
        $this->validator = new \App\Core\Validators\InputValidator();
    }

    /**
     * Validate input data against rules
     * @param array $data Input data to validate
     * @param array $rules Validation rules
     * @return array Array of validation errors, empty if valid
     */
    public function validate(array $data, array $rules): array {
        return $this->validator->validate($data, $rules) ? [] : $this->validator->getErrors();
    }

    /**
     * Sanitize input data
     * @param mixed $data Data to sanitize
     * @return mixed Sanitized data
     */
    public function sanitize($data) {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->sanitize($value);
            }
            return $data;
        }
        
        return htmlspecialchars(strip_tags($data), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Validate receipt data
     * @param array $data Receipt data to validate
     * @return array Validation errors
     */
    public function validateReceiptData(array $data): array {
        $rules = [
            'order_id' => 'required|string|min:1|max:50',
            'payment_method' => 'required|in:CASH,CARD,QR',
            'receipt_type' => 'required|in:FULL,PARTIAL,ITEMIZED',
            'discount_amount' => 'numeric|min:0',
            'printer_id' => 'string|max:50',
            'created_by' => 'required|string|min:1|max:50'
        ];

        return $this->validate($data, $rules);
    }

    /**
     * Validate printer data
     * @param array $data Printer data to validate
     * @return array Validation errors
     */
    public function validatePrinterData(array $data): array {
        $rules = [
            'printer_name' => 'required|string|min:1|max:100',
            'printer_serial' => 'required|string|min:1|max:100|unique:printers,printer_serial',
            'printer_location' => 'required|string|min:1|max:200',
            'printer_model' => 'required|string|min:1|max:100',
            'port' => 'string|max:20'
        ];

        return $this->validate($data, $rules);
    }

    /**
     * Validate order data
     * @param array $data Order data to validate
     * @return array Validation errors
     */
    public function validateOrderData(array $data): array {
        $rules = [
            'table_id' => 'required|string|min:1|max:50',
            'items' => 'required|array|min:1',
            'customer_note' => 'string|max:500',
            'order_source' => 'required|in:POS,QRCODE,WEB',
            'created_by' => 'required|string|min:1|max:50',
            'staff_name' => 'required|string|min:1|max:100'
        ];

        return $this->validate($data, $rules);
    }
    
    /**
     * Validate request data using validation rules from config
     * @param array $data Request data to validate
     * @param string $ruleSet Rule set name from validation_rules.php (e.g., 'menu_item', 'order', 'table')
     * @return array ['valid' => bool, 'errors' => array, 'data' => array] Validated and sanitized data
     */
    public function validateRequest(array $data, string $ruleSet): array {
        // Load validation rules from config file
        $rulesFile = __DIR__ . '/../config/validation_rules.php';
        if (!file_exists($rulesFile)) {
            error_log("Validation rules file not found: {$rulesFile}");
            return [
                'valid' => false,
                'errors' => ['validation_rules' => 'Validation rules file not found'],
                'data' => []
            ];
        }
        
        $allRules = require $rulesFile;
        
        if (!isset($allRules[$ruleSet])) {
            error_log("Validation rule set '{$ruleSet}' not found in validation_rules.php");
            return [
                'valid' => false,
                'errors' => ['rule_set' => "Validation rule set '{$ruleSet}' not found"],
                'data' => []
            ];
        }
        
        $rules = $allRules[$ruleSet];
        
        // Validate using existing validate method
        $errors = $this->validate($data, $rules);
        $isValid = empty($errors);
        
        // Sanitize and return validated data
        $validatedData = [];
        foreach ($rules as $field => $fieldRules) {
            if (isset($data[$field])) {
                $validatedData[$field] = $this->sanitize($data[$field]);
            }
        }
        
        return [
            'valid' => $isValid,
            'errors' => $errors,
            'data' => $validatedData
        ];
    }
}