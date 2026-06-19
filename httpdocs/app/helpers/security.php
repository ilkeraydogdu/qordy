<?php
// CSRF Token ve Input Validation Yardımcı Fonksiyonları

/**
 * CSRF token oluşturur
 * Uses CSRFManager which handles Redis/session automatically
 * @return string
 */
if (!function_exists('generateCSRFToken')) {
function generateCSRFToken(): string {
    require_once __DIR__ . '/../core/Security/CSRFManager.php';
    return \App\Core\Security\CSRFManager::generateToken();
    }
}

/**
 * CSRF token doğrular
 * Uses CSRFManager which handles Redis/session automatically
 * @param string $token
 * @return bool
 */
if (!function_exists('validateCSRFToken')) {
function validateCSRFToken(string $token): bool {
    require_once __DIR__ . '/../core/Security/CSRFManager.php';
    return \App\Core\Security\CSRFManager::validateToken($token);
    }
}

/**
 * XSS koruması için HTML kodlama
 * @param mixed $data
 * @return mixed
 */
function xssClean($data) {
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = xssClean($value);
        }
        return $data;
    }
    
    return htmlspecialchars(strip_tags($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Input validation kuralları
 * @param array $data
 * @param array $rules
 * @return array
 */
function validateInputs(array $data, array $rules): array {
    $validator = new \App\Core\Validators\InputValidator();
    $isValid = $validator->validate($data, $rules);
    
    if (!$isValid) {
        return $validator->getErrors();
    }
    
    return [];
}