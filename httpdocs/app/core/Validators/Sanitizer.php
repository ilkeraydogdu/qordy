<?php
namespace App\Core\Validators;

class Sanitizer {
    public static function sanitize($value, string $type = 'string') {
        if ($value === null) {
            return null;
        }
        
        switch ($type) {
            case 'string':
                return self::sanitizeString($value);
            case 'int':
            case 'integer':
                return self::sanitizeInteger($value);
            case 'float':
                return self::sanitizeFloat($value);
            case 'email':
                return self::sanitizeEmail($value);
            case 'url':
                return self::sanitizeUrl($value);
            case 'html':
                return self::sanitizeHtml($value);
            case 'sql':
                return self::sanitizeSql($value);
            case 'path':
                return self::sanitizePath($value);
            default:
                return self::sanitizeString($value);
        }
    }
    
    public static function sanitizeString($value): string {
        if (!is_string($value)) {
            $value = (string)$value;
        }
        
        $value = trim($value);
        $value = stripslashes($value);
        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        
        return $value;
    }
    
    public static function sanitizeInteger($value): int {
        return (int)filter_var($value, FILTER_SANITIZE_NUMBER_INT);
    }
    
    public static function sanitizeFloat($value): float {
        return (float)filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }
    
    public static function sanitizeEmail($value): string {
        return filter_var($value, FILTER_SANITIZE_EMAIL);
    }
    
    public static function sanitizeUrl($value): string {
        return filter_var($value, FILTER_SANITIZE_URL);
    }
    
    public static function sanitizeHtml($value): string {
        if (!is_string($value)) {
            $value = (string)$value;
        }
        
        $value = trim($value);
        $value = stripslashes($value);
        
        $allowedTags = '<p><br><strong><em><u><a><ul><ol><li><h1><h2><h3><h4><h5><h6>';
        $value = strip_tags($value, $allowedTags);
        
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
    
    public static function sanitizeSql($value): string {
        if (!is_string($value)) {
            $value = (string)$value;
        }
        
        $dangerous = ['--', ';', '/*', '*/', 'xp_', 'sp_', 'exec', 'execute', 'union', 'select', 'insert', 'update', 'delete', 'drop', 'create', 'alter'];
        foreach ($dangerous as $pattern) {
            $value = str_ireplace($pattern, '', $value);
        }
        
        return $value;
    }
    
    public static function sanitizePath($value): string {
        if (!is_string($value)) {
            $value = (string)$value;
        }
        
        $value = str_replace(['../', '..\\', './', '.\\'], '', $value);
        $value = preg_replace('/[^a-zA-Z0-9\/\\\-_\.]/', '', $value);
        
        return $value;
    }
    
    public static function sanitizeArray(array $data, array $rules = []): array {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            $type = $rules[$key] ?? 'string';
            
            if (is_array($value)) {
                $sanitized[$key] = self::sanitizeArray($value, $rules[$key] ?? []);
            } else {
                $sanitized[$key] = self::sanitize($value, $type);
            }
        }
        
        return $sanitized;
    }
    
    public static function escapeOutput($value): string {
        if ($value === null) {
            return '';
        }
        
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
    
    public static function preventXSS($value): string {
        if (!is_string($value)) {
            $value = (string)$value;
        }
        
        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $value = str_replace(['<script', '</script>', 'javascript:', 'onerror=', 'onload='], '', $value);
        
        return $value;
    }
}

