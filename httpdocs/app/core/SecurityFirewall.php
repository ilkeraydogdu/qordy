<?php
namespace App\Core;

require_once __DIR__ . '/Validators/InputValidator.php';
require_once __DIR__ . '/Validators/Sanitizer.php';
require_once __DIR__ . '/RateLimiter.php';
require_once __DIR__ . '/IPBlocker.php';

class SecurityFirewall {
    private $validator;
    private $sanitizer;
    private $rateLimiter;
    private $ipBlocker;
    private $config;
    private $suspiciousActivities = [];
    
    public function __construct(array $config = []) {
        $this->validator = new \App\Core\Validators\InputValidator();
        $this->sanitizer = new \App\Core\Validators\Sanitizer();
        $this->rateLimiter = new RateLimiter($config['rate_limits'] ?? []);
        $this->ipBlocker = new IPBlocker();
        $this->config = $config;
    }
    
    public function validateRequest(array $data, array $rules = []): bool {
        if (empty($rules)) {
            return true;
        }
        
        return $this->validator->validate($data, $rules);
    }
    
    public function sanitizeInput($data, string $type = 'string') {
        if (is_array($data)) {
            return $this->sanitizer::sanitizeArray($data);
        }
        
        return $this->sanitizer::sanitize($data, $type);
    }
    
    public function sanitizeArray(array $data, array $rules = []): array {
        return $this->sanitizer::sanitizeArray($data, $rules);
    }
    
    public function checkRateLimit(string $identifier, string $type = 'default'): bool {
        return $this->rateLimiter->check($identifier, $type);
    }
    
    public function getRemainingRequests(string $identifier, string $type = 'default'): int {
        return $this->rateLimiter->getRemaining($identifier, $type);
    }
    
    public function isIPBlocked(string $ip): bool {
        return $this->ipBlocker->isBlocked($ip);
    }
    
    public function blockIP(string $ip, int $duration = 3600, bool $permanent = false): bool {
        return $this->ipBlocker->block($ip, $duration, $permanent);
    }
    
    public function unblockIP(string $ip): bool {
        return $this->ipBlocker->unblock($ip);
    }
    
    public function validateCSRF(string $token): bool {
        if (!function_exists('validateCSRFToken')) {
            require_once __DIR__ . '/../helpers/auth.php';
        }
        
        return validateCSRFToken($token);
    }
    
    public function validateFileUpload(array $file, array $allowedTypes = [], int $maxSize = 5242880): array {
        $errors = [];
        
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $errors[] = 'Invalid file upload.';
            return ['valid' => false, 'errors' => $errors];
        }
        
        if ($file['size'] > $maxSize) {
            $errors[] = 'File size exceeds maximum allowed size.';
        }
        
        if (!empty($allowedTypes)) {
            $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $mimeType = mime_content_type($file['tmp_name']);
            
            if (!in_array($fileExtension, $allowedTypes) && !in_array($mimeType, $allowedTypes)) {
                $errors[] = 'File type not allowed.';
            }
        }
        
        $dangerousExtensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'exe', 'bat', 'sh', 'js'];
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (in_array($fileExtension, $dangerousExtensions)) {
            $errors[] = 'Dangerous file type detected.';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    public function detectSQLInjection(string $input): bool {
        $patterns = [
            '/(\bUNION\b.*\bSELECT\b)/i',
            '/(\bSELECT\b.*\bFROM\b)/i',
            '/(\bINSERT\b.*\bINTO\b)/i',
            '/(\bUPDATE\b.*\bSET\b)/i',
            '/(\bDELETE\b.*\bFROM\b)/i',
            '/(\bDROP\b.*\bTABLE\b)/i',
            '/(\bEXEC\b|\bEXECUTE\b)/i',
            '/(\bOR\b.*\b1\s*=\s*1\b)/i',
            '/(\bOR\b.*\b\'1\'\s*=\s*\'1\')/i',
            '/(\b--\b|\b\/\*|\*\/)/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }
        
        return false;
    }
    
    public function detectXSS(string $input): bool {
        $patterns = [
            '/<script[^>]*>.*?<\/script>/is',
            '/<iframe[^>]*>.*?<\/iframe>/is',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<img[^>]+src[^>]*=.*javascript:/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }
        
        return false;
    }
    
    public function logSuspiciousActivity(string $type, string $details, string $ip = null): void {
        $ip = $ip ?? $this->getClientIP();
        
        $this->suspiciousActivities[] = [
            'type' => $type,
            'details' => $details,
            'ip' => $ip,
            'timestamp' => time(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ];
        
        if (class_exists('\App\Core\Logger')) {
            \App\Core\Logger::warning("Suspicious activity detected: {$type}", [
                'details' => $details,
                'ip' => $ip
            ]);
        }
        
        $suspiciousCount = count(array_filter($this->suspiciousActivities, function($activity) use ($ip) {
            return $activity['ip'] === $ip && (time() - $activity['timestamp']) < 3600;
        }));
        
        if ($suspiciousCount >= ($this->config['auto_block_threshold'] ?? 5)) {
            $this->blockIP($ip, 3600);
        }
    }
    
    public function getClientIP(): string {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    public function getValidator(): \App\Core\Validators\InputValidator {
        return $this->validator;
    }
    
    public function getSanitizer(): \App\Core\Validators\Sanitizer {
        return $this->sanitizer;
    }
    
    public function getRateLimiter(): RateLimiter {
        return $this->rateLimiter;
    }
    
    public function getIPBlocker(): IPBlocker {
        return $this->ipBlocker;
    }
    
    public function getSuspiciousActivities(): array {
        return $this->suspiciousActivities;
    }
}

