<?php
namespace App\Core\Helpers;

/**
 * Sanitization Helper Class
 * Provides methods for sanitizing and validating user input
 */
class SanitizationHelper {
    /**
     * Sanitize input string
     * @param string $data Input to sanitize
     * @return string Sanitized string
     */
    public static function sanitize(string $data): string {
        // Handle empty strings
        if ($data === '') {
            return '';
        }
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return $data;
    }

    /**
     * Advanced sanitize input string with more security options
     * @param string $data Input to sanitize
     * @param bool $allowHtml Whether to allow HTML tags
     * @param array $allowedTags Whitelist of allowed HTML tags
     * @param array $allowedAttributes Whitelist of allowed attributes
     * @return string Sanitized string
     */
    public static function advancedSanitize(string $data, bool $allowHtml = false, array $allowedTags = [], array $allowedAttributes = []): string {
        // Handle empty strings
        if ($data === '') {
            return '';
        }

        $data = trim($data);
        $data = stripslashes($data);

        if ($allowHtml) {
            // If HTML is allowed, use a more secure approach
            if (!empty($allowedTags)) {
                // Use HTML Purifier or similar if available, otherwise basic filtering
                $data = self::secureStripTags($data, $allowedTags, $allowedAttributes);
            }
        } else {
            // If HTML is not allowed, remove all tags
            $data = htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $data;
    }

    /**
     * Securely strip HTML tags with attribute filtering
     * @param string $data Input to process
     * @param array $allowedTags Whitelist of allowed tags
     * @param array $allowedAttributes Whitelist of allowed attributes
     * @return string Processed string
     */
    private static function secureStripTags(string $data, array $allowedTags, array $allowedAttributes): string {
        if (empty($allowedTags)) {
            return strip_tags($data);
        }

        // Convert arrays to strings for use in strip_tags
        $allowedTagsString = '<' . implode('><', $allowedTags) . '>';

        // First, strip tags that aren't in our whitelist
        $data = strip_tags($data, $allowedTagsString);

        // Then, remove unwanted attributes from remaining tags
        if (!empty($allowedAttributes)) {
            foreach ($allowedTags as $tag) {
                // Pattern to match attributes in the allowed tags
                $pattern = '/<' . preg_quote($tag, '/') . '(?![^>]*data-allowed)>[^>]*>/i';

                // Find all instances of the tag
                preg_match_all($pattern, $data, $matches, PREG_OFFSET_CAPTURE);

                foreach ($matches[0] as $match) {
                    $originalTag = $match[0];

                    // Check for disallowed attributes
                    $cleanedTag = $originalTag;

                    // Remove any attributes not in the allowed list
                    foreach (['onclick', 'onmouseover', 'onmouseout', 'onload', 'onunload', 'onfocus', 'onblur', 'onchange', 'onsubmit', 'onkeydown', 'onkeypress', 'onkeyup', 'ondblclick', 'onmousedown', 'onmouseup', 'onmousemove', 'onresize', 'onscroll', 'onerror', 'onabort', 'onreset', 'onselect', 'onsubmit'] as $disallowedAttr) {
                        if (!in_array($disallowedAttr, $allowedAttributes)) {
                            $cleanedTag = preg_replace('/\s*' . preg_quote($disallowedAttr, '/') . '\s*=\s*["\'][^"\']*["\']/i', '', $cleanedTag);
                        }
                    }

                    // Replace the original tag with the cleaned version
                    $data = substr_replace($data, $cleanedTag, $match[1], strlen($originalTag));
                }
            }
        }

        return $data;
    }

    /**
     * Validate email address
     * @param string $email Email to validate
     * @return bool|string Valid email or false
     */
    public static function validateEmail(string $email) {
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        return $email;
    }

    /**
     * Validate phone number
     * @param string $phone Phone number to validate
     * @return bool True if valid, false otherwise
     */
    public static function validatePhone(string $phone): bool {
        return (bool) preg_match('/^[\+]?[0-9]{10,15}$/', $phone);
    }

    /**
     * Sanitize integer
     * @param mixed $value Value to sanitize
     * @param int $default Default value if invalid
     * @return int Sanitized integer
     */
    public static function sanitizeInt($value, int $default = 0): int {
        return filter_var($value, FILTER_VALIDATE_INT, ['options' => ['default' => $default]]) ?: $default;
    }

    /**
     * Sanitize float
     * @param mixed $value Value to sanitize
     * @param float $default Default value if invalid
     * @return float Sanitized float
     */
    public static function sanitizeFloat($value, float $default = 0.0): float {
        return filter_var($value, FILTER_VALIDATE_FLOAT, ['options' => ['default' => $default]]) ?: $default;
    }

    /**
     * Sanitize URL
     * @param string $url URL to sanitize
     * @return string|false Valid URL or false
     */
    public static function sanitizeUrl(string $url) {
        $url = filter_var($url, FILTER_SANITIZE_URL);
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        return $url;
    }

    /**
     * Remove HTML tags
     * @param string $data Data to clean
     * @param string|null $allowedTags Allowed HTML tags
     * @return string Cleaned string
     */
    public static function stripTags(string $data, ?string $allowedTags = null): string {
        return strip_tags($data, $allowedTags);
    }

    /**
     * Validate and sanitize SQL identifier (table/column names)
     * @param string $identifier SQL identifier to validate
     * @return string|false Valid identifier or false
     */
    public static function validateSqlIdentifier(string $identifier) {
        // Only allow alphanumeric characters, underscore, and hyphen
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $identifier)) {
            return false;
        }
        return $identifier;
    }

    /**
     * Sanitize file name to prevent directory traversal
     * @param string $filename Filename to sanitize
     * @return string Sanitized filename
     */
    public static function sanitizeFilename(string $filename): string {
        // Remove path information and keep only the filename
        $filename = basename($filename);

        // Remove any characters that could be dangerous
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

        return $filename;
    }

    /**
     * Validate IP address
     * @param string $ip IP address to validate
     * @return bool True if valid, false otherwise
     */
    public static function validateIp(string $ip): bool {
        return (bool) filter_var($ip, FILTER_VALIDATE_IP);
    }
}

