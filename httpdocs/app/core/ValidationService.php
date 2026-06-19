<?php
namespace App\Core;

/**
 * Merkezi Veri Validasyon ve Sanitizasyon Sistemi
 * Tüm girdi doğrulama ve temizleme işlemlerini merkezileştirir
 */
class ValidationService {
    private $errors = [];
    private $rules = [];
    private $messages = [];
    private $customRules = [];

    public function __construct() {
        $this->setDefaultMessages();
    }

    /**
     * Varsayılan hata mesajlarını ayarla
     */
    private function setDefaultMessages(): void {
        $this->messages = [
            'required' => ':attribute alanı zorunludur',
            'email' => ':attribute geçerli bir e-posta adresi olmalıdır',
            'min' => ':attribute en az :min karakter uzunluğunda olmalıdır',
            'max' => ':attribute en fazla :max karakter uzunluğunda olmalıdır',
            'between' => ':attribute :min ile :max arasında olmalıdır',
            'numeric' => ':attribute sayısal olmalıdır',
            'integer' => ':attribute tam sayı olmalıdır',
            'string' => ':attribute metin olmalıdır',
            'array' => ':attribute dizi olmalıdır',
            'boolean' => ':attribute doğru/yanlış değeri olmalıdır',
            'url' => ':attribute geçerli bir URL olmalıdır',
            'ip' => ':attribute geçerli bir IP adresi olmalıdır',
            'date' => ':attribute geçerli bir tarih olmalıdır',
            'unique' => ':attribute daha önce kullanılmış',
            'exists' => ':attribute bulunamadı',
            'confirmed' => ':attribute onayı eşleşmiyor',
            'regex' => ':attribute formatı geçersiz',
            'in' => 'Seçilen :attribute geçersiz',
            'not_in' => 'Seçilen :attribute geçersiz',
            'alpha' => ':attribute sadece harf içermelidir',
            'alpha_num' => ':attribute sadece harf ve rakam içermelidir',
            'alpha_dash' => ':attribute sadece harf, rakam, tire ve alt çizgi içermelidir',
            'digits' => ':attribute :digits rakam uzunluğunda olmalıdır',
            'digits_between' => ':attribute :min ile :max rakam arasında uzunluğunda olmalıdır'
        ];
    }

    /**
     * Doğrulama kurallarını ayarla
     */
    public function setRules(array $rules): self {
        $this->rules = $rules;
        return $this;
    }

    /**
     * Özel hata mesajlarını ayarla
     */
    public function setMessages(array $messages): self {
        $this->messages = array_merge($this->messages, $messages);
        return $this;
    }

    /**
     * Özel doğrulama kuralı ekle
     */
    public function addRule(string $name, callable $callback): self {
        $this->customRules[$name] = $callback;
        return $this;
    }

    /**
     * Doğrulama yap
     */
    public function validate(array $data): bool {
        $this->errors = [];

        foreach ($this->rules as $field => $rules) {
            $fieldRules = is_string($rules) ? explode('|', $rules) : $rules;
            
            foreach ($fieldRules as $rule) {
                $this->applyRule($field, $data[$field] ?? null, $rule, $data);
            }
        }

        return empty($this->errors);
    }

    /**
     * Belirli bir kuralı uygula
     */
    private function applyRule(string $field, $value, string $rule, array $data): void {
        $ruleParts = explode(':', $rule);
        $ruleName = $ruleParts[0];
        $ruleParams = $ruleParts[1] ?? null;

        // Özel kurallar
        if (isset($this->customRules[$ruleName])) {
            if (!$this->customRules[$ruleName]($value, $ruleParams, $data)) {
                $this->addError($field, $ruleName, $ruleParams);
            }
            return;
        }

        // Standart kurallar
        switch ($ruleName) {
            case 'required':
                if ($this->isEmpty($value)) {
                    $this->addError($field, 'required');
                }
                break;
                
            case 'email':
                if (!$this->isEmpty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, 'email');
                }
                break;
                
            case 'min':
                if (!$this->isEmpty($value)) {
                    $min = (int)$ruleParams;
                    if (is_numeric($value) && $value < $min) {
                        $this->addError($field, 'min', ['min' => $min]);
                    } elseif (is_string($value) && strlen($value) < $min) {
                        $this->addError($field, 'min', ['min' => $min]);
                    }
                }
                break;
                
            case 'max':
                if (!$this->isEmpty($value)) {
                    $max = (int)$ruleParams;
                    if (is_numeric($value) && $value > $max) {
                        $this->addError($field, 'max', ['max' => $max]);
                    } elseif (is_string($value) && strlen($value) > $max) {
                        $this->addError($field, 'max', ['max' => $max]);
                    }
                }
                break;
                
            case 'between':
                if (!$this->isEmpty($value)) {
                    $params = explode(',', $ruleParams);
                    $min = (int)($params[0] ?? 0);
                    $max = (int)($params[1] ?? 0);
                    
                    if (is_numeric($value)) {
                        if ($value < $min || $value > $max) {
                            $this->addError($field, 'between', ['min' => $min, 'max' => $max]);
                        }
                    } elseif (is_string($value)) {
                        $length = strlen($value);
                        if ($length < $min || $length > $max) {
                            $this->addError($field, 'between', ['min' => $min, 'max' => $max]);
                        }
                    }
                }
                break;
                
            case 'numeric':
                if (!$this->isEmpty($value) && !is_numeric($value)) {
                    $this->addError($field, 'numeric');
                }
                break;
                
            case 'integer':
                if (!$this->isEmpty($value) && !filter_var($value, FILTER_VALIDATE_INT)) {
                    $this->addError($field, 'integer');
                }
                break;
                
            case 'string':
                if (!$this->isEmpty($value) && !is_string($value)) {
                    $this->addError($field, 'string');
                }
                break;
                
            case 'array':
                if (!$this->isEmpty($value) && !is_array($value)) {
                    $this->addError($field, 'array');
                }
                break;
                
            case 'boolean':
                if (!$this->isEmpty($value) && !is_bool($value) && !in_array($value, [0, 1, '0', '1'], true)) {
                    $this->addError($field, 'boolean');
                }
                break;
                
            case 'url':
                if (!$this->isEmpty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->addError($field, 'url');
                }
                break;
                
            case 'ip':
                if (!$this->isEmpty($value) && !filter_var($value, FILTER_VALIDATE_IP)) {
                    $this->addError($field, 'ip');
                }
                break;
                
            case 'date':
                if (!$this->isEmpty($value)) {
                    $date = \DateTime::createFromFormat('Y-m-d', $value);
                    if (!$date || $date->format('Y-m-d') !== $value) {
                        $this->addError($field, 'date');
                    }
                }
                break;
                
            case 'unique':
                if (!$this->isEmpty($value)) {
                    $params = explode(',', $ruleParams);
                    $table = $params[0] ?? '';
                    $column = $params[1] ?? $field;

                    // GÜVENLİK: Tablo/sütun isimleri PDO bind edilemez;
                    // bu değerler koda gömülü rule tanımından gelse bile
                    // katı allowlist + regex zorunludur. SQLi önleyici.
                    if ($table && self::isSafeIdentifier($table) && self::isSafeIdentifier($column)) {
                        $db = DependencyFactory::getDatabase();
                        $stmt = $db->prepare("SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = ?");
                        $stmt->execute([$value]);

                        if ($stmt->fetchColumn() > 0) {
                            $this->addError($field, 'unique');
                        }
                    }
                }
                break;
                
            case 'exists':
                if (!$this->isEmpty($value)) {
                    $params = explode(',', $ruleParams);
                    $table = $params[0] ?? '';
                    $column = $params[1] ?? $field;

                    if ($table && self::isSafeIdentifier($table) && self::isSafeIdentifier($column)) {
                        $db = DependencyFactory::getDatabase();
                        $stmt = $db->prepare("SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = ?");
                        $stmt->execute([$value]);

                        if ((int)$stmt->fetchColumn() === 0) {
                            $this->addError($field, 'exists');
                        }
                    }
                }
                break;
                
            case 'confirmed':
                if (!$this->isEmpty($value)) {
                    $confirmationField = $field . '_confirmation';
                    if (!isset($data[$confirmationField]) || $value !== $data[$confirmationField]) {
                        $this->addError($field, 'confirmed');
                    }
                }
                break;
                
            case 'regex':
                if (!$this->isEmpty($value) && !preg_match($ruleParams, $value)) {
                    $this->addError($field, 'regex');
                }
                break;
                
            case 'in':
                if (!$this->isEmpty($value)) {
                    $allowedValues = explode(',', $ruleParams);
                    if (!in_array($value, $allowedValues)) {
                        $this->addError($field, 'in');
                    }
                }
                break;
                
            case 'not_in':
                if (!$this->isEmpty($value)) {
                    $forbiddenValues = explode(',', $ruleParams);
                    if (in_array($value, $forbiddenValues)) {
                        $this->addError($field, 'not_in');
                    }
                }
                break;
                
            case 'alpha':
                if (!$this->isEmpty($value) && !ctype_alpha(str_replace(' ', '', $value))) {
                    $this->addError($field, 'alpha');
                }
                break;
                
            case 'alpha_num':
                if (!$this->isEmpty($value) && !ctype_alnum(str_replace(' ', '', $value))) {
                    $this->addError($field, 'alpha_num');
                }
                break;
                
            case 'alpha_dash':
                if (!$this->isEmpty($value) && !preg_match('/^[A-Za-z0-9_-]+$/', $value)) {
                    $this->addError($field, 'alpha_dash');
                }
                break;
                
            case 'digits':
                if (!$this->isEmpty($value)) {
                    $digits = (int)$ruleParams;
                    if (!preg_match('/^\d+$/', $value) || strlen($value) !== $digits) {
                        $this->addError($field, 'digits', ['digits' => $digits]);
                    }
                }
                break;
                
            case 'digits_between':
                if (!$this->isEmpty($value)) {
                    $params = explode(',', $ruleParams);
                    $min = (int)($params[0] ?? 0);
                    $max = (int)($params[1] ?? 0);
                    $length = strlen($value);
                    
                    if (!preg_match('/^\d+$/', $value) || $length < $min || $length > $max) {
                        $this->addError($field, 'digits_between', ['min' => $min, 'max' => $max]);
                    }
                }
                break;
        }
    }

    /**
     * Hata ekle
     */
    private function addError(string $field, string $rule, array $params = []): void {
        $message = $this->messages[$rule] ?? $rule . ' kuralı ihlal edildi';
        
        // Parametreleri mesaja ekle
        foreach ($params as $key => $value) {
            $message = str_replace(":{$key}", $value, $message);
        }
        
        // Alan adını ekle
        $fieldName = $this->getFieldName($field);
        $message = str_replace(':attribute', $fieldName, $message);
        
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        
        $this->errors[$field][] = $message;
    }

    /**
     * Alan adını al
     */
    private function getFieldName(string $field): string {
        // Burada alan isimlerini çeviri dosyalarından alabilirsiniz
        // Örnek: return t("fields.{$field}") ?: $field;
        return $field;
    }

    /**
     * Değer boş mu kontrol et
     */
    private function isEmpty($value): bool {
        return $value === null || $value === '' || $value === [];
    }

    /**
     * Tablo/sütun adı güvenlik kontrolü.
     *
     * PDO prepared statement yalnızca DEĞERLERi güvenli bağlar;
     * IDENTIFIER (tablo/sütun adı) hiçbir zaman bağlanamaz. Dolayısıyla
     * `unique`/`exists` kurallarında tablo/sütun adı koda gömülü olsa
     * bile katı bir allowlist regex'i ile doğrulanmalıdır. Bu metot
     * yalnızca alfasayısal, alt çizgi ve MySQL için güvenli karakter
     * setine izin verir; 64 karakter MySQL identifier sınırıdır.
     */
    private static function isSafeIdentifier(string $ident): bool {
        if ($ident === '' || strlen($ident) > 64) {
            return false;
        }
        return (bool) preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $ident);
    }

    /**
     * Hataları al
     */
    public function getErrors(): array {
        return $this->errors;
    }

    /**
     * Belirli bir alan için hataları al
     */
    public function getErrorsForField(string $field): array {
        return $this->errors[$field] ?? [];
    }

    /**
     * Hata var mı kontrol et
     */
    public function hasErrors(): bool {
        return !empty($this->errors);
    }

    /**
     * Belirli bir alanda hata var mı kontrol et
     */
    public function hasError(string $field): bool {
        return isset($this->errors[$field]) && !empty($this->errors[$field]);
    }

    /**
     * Girdi verisini temizle
     */
    public function sanitize(array $data): array {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitize($value);
            } else {
                $sanitized[$key] = $this->sanitizeValue($value);
            }
        }
        
        return $sanitized;
    }

    /**
     * Tek bir değeri temizle
     */
    private function sanitizeValue($value) {
        if (!is_string($value)) {
            return $value;
        }
        
        // XSS koruması
        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        
        // SQL injection koruması için değil, sadece gösterim için
        $value = stripslashes($value);
        
        return $value;
    }

    /**
     * Belirli bir türdeki veriyi doğrula
     */
    public function validateType($value, string $type): bool {
        switch ($type) {
            case 'email':
                return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
            case 'url':
                return filter_var($value, FILTER_VALIDATE_URL) !== false;
            case 'ip':
                return filter_var($value, FILTER_VALIDATE_IP) !== false;
            case 'numeric':
                return is_numeric($value);
            case 'integer':
                return filter_var($value, FILTER_VALIDATE_INT) !== false;
            case 'float':
                return filter_var($value, FILTER_VALIDATE_FLOAT) !== false;
            case 'boolean':
                return is_bool($value) || in_array($value, [0, 1, '0', '1'], true);
            case 'string':
                return is_string($value);
            case 'array':
                return is_array($value);
            case 'date':
                return \DateTime::createFromFormat('Y-m-d', $value) !== false;
            case 'datetime':
                return \DateTime::createFromFormat('Y-m-d H:i:s', $value) !== false;
            default:
                return true; // Bilinmeyen tür için true döndür
        }
    }

    /**
     * Telefon numarası doğrula
     */
    public function validatePhone(string $phone): bool {
        // Sadece rakamları al
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Türkiye telefon numarası formatı: 10 veya 11 haneli
        return strlen($phone) >= 10 && strlen($phone) <= 11;
    }

    /**
     * TC Kimlik numarası doğrula
     */
    public function validateTC(string $tc): bool {
        if (strlen($tc) !== 11) {
            return false;
        }

        $tc = str_split($tc);
        
        // 1. rakam 0 olamaz
        if ((int)$tc[0] === 0) {
            return false;
        }

        // 10. rakam: [(1. + 3. + 5. + 7. + 9.) * 7 - (2. + 4. + 6. + 8.) ] % 10 == 10.rakam
        $evenSum = $tc[1] + $tc[3] + $tc[5] + $tc[7];
        $oddSum = $tc[0] + $tc[2] + $tc[4] + $tc[6] + $tc[8];
        $control10 = (7 * $oddSum - $evenSum) % 10;
        
        if ($control10 != $tc[9]) {
            return false;
        }

        // 11. rakam: (1. + 2. + 3. + ... + 10.) % 10 == 11.rakam
        $sum = array_sum($tc);
        $control11 = $sum % 10;
        
        return (int)$control11 === (int)$tc[10];
    }

    /**
     * Kredi kartı numarası doğrula (Luhn algorithm)
     */
    public function validateCreditCard(string $cardNumber): bool {
        $cardNumber = preg_replace('/\D/', '', $cardNumber);
        
        if (strlen($cardNumber) < 13 || strlen($cardNumber) > 19) {
            return false;
        }

        $sum = 0;
        $length = strlen($cardNumber);
        
        for ($i = $length - 1; $i >= 0; $i--) {
            $digit = intval($cardNumber[$i]);
            
            if (($length - $i) % 2 === 0) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            
            $sum += $digit;
        }
        
        return $sum % 10 === 0;
    }

    /**
     * ZIP kodu doğrula (Türkiye formatı)
     */
    public function validateZipCode(string $zipCode): bool {
        return preg_match('/^[0-9]{5}$/', $zipCode) === 1;
    }

    /**
     * Fiyat doğrulama (pozitif sayı ve maksimum 2 ondalık basamak)
     */
    public function validatePrice($price): bool {
        if (!is_numeric($price)) {
            return false;
        }
        
        $price = floatval($price);
        
        if ($price < 0) {
            return false;
        }
        
        // Maksimum 2 ondalık basamak kontrolü
        $decimalPlaces = strlen(substr(strrchr($price, "."), 1));
        return $decimalPlaces <= 2;
    }

    /**
     * Miktar doğrulama (pozitif tam sayı)
     */
    public function validateQuantity($quantity): bool {
        if (!is_numeric($quantity)) {
            return false;
        }
        
        $quantity = intval($quantity);
        return $quantity > 0;
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
        
        // Set rules and validate
        $this->setRules($rules);
        $isValid = $this->validate($data);
        
        // Get errors
        $errors = $this->getErrors();
        
        // Sanitize and return validated data
        $validatedData = [];
        foreach ($rules as $field => $fieldRules) {
            if (isset($data[$field])) {
                $validatedData[$field] = $this->sanitizeDeep($data[$field]);
            }
        }
        
        return [
            'valid' => $isValid,
            'errors' => $errors,
            'data' => $validatedData
        ];
    }
    
    /**
     * Recursively sanitize mixed data (string, array, or other)
     * @param mixed $data Data to sanitize
     * @return mixed Sanitized data
     */
    private function sanitizeDeep($data) {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->sanitizeDeep($value);
            }
            return $data;
        }
        
        if (is_string($data)) {
            return htmlspecialchars(strip_tags($data), ENT_QUOTES, 'UTF-8');
        }
        
        return $data;
    }
}