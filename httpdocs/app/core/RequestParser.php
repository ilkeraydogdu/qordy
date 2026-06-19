<?php
namespace App\Core;

/**
 * Request Parser
 * Centralized request parsing for both form and JSON requests
 * MVC/OOP compliant request handling
 */
class RequestParser {
    private static $parsedData = null;
    
    /**
     * Get request data (supports GET, POST, and JSON)
     * Priority: POST/JSON body > GET (POST overwrites GET if same key exists)
     * @return array
     */
    public static function getRequestData(): array {
        if (self::$parsedData !== null) {
            return self::$parsedData;
        }
        
        $data = [];
        
        // Get data from GET (query parameters)
        if (!empty($_GET)) {
            $data = array_merge($data, $_GET);
        }
        
        // Get data from POST (form requests)
        if (!empty($_POST)) {
            $data = array_merge($data, $_POST);
        }
        
        // Get data from JSON body (API requests)
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'application/json') !== false) {
            $jsonInput = file_get_contents('php://input');
            if (!empty($jsonInput)) {
                $jsonData = json_decode($jsonInput, true);
                if (is_array($jsonData)) {
                    $data = array_merge($data, $jsonData);
                }
            }
        }
        
        self::$parsedData = $data;
        return $data;
    }
    
    /**
     * Get GET parameters only
     * @return array
     */
    public static function getQueryParams(): array {
        return $_GET ?? [];
    }
    
    /**
     * Get POST parameters only
     * @return array
     */
    public static function getPostParams(): array {
        return $_POST ?? [];
    }
    
    /**
     * Get JSON body data
     * @return array
     */
    public static function getJsonBody(): array {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'application/json') !== false) {
            $jsonInput = file_get_contents('php://input');
            if (!empty($jsonInput)) {
                $jsonData = json_decode($jsonInput, true);
                if (is_array($jsonData)) {
                    return $jsonData;
                }
            }
        }
        return [];
    }
    
    /**
     * Get a specific GET parameter
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function getQuery(string $key, $default = null) {
        return $_GET[$key] ?? $default;
    }
    
    /**
     * Get a specific POST parameter
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function getPost(string $key, $default = null) {
        return $_POST[$key] ?? $default;
    }
    
    /**
     * Get a specific request parameter
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, $default = null) {
        $data = self::getRequestData();
        return $data[$key] ?? $default;
    }
    
    /**
     * Check if a parameter exists
     * @param string $key
     * @return bool
     */
    public static function has(string $key): bool {
        $data = self::getRequestData();
        return isset($data[$key]);
    }
    
    /**
     * Get all request data
     * @return array
     */
    public static function all(): array {
        return self::getRequestData();
    }
    
    /**
     * Get uploaded files
     * @return array Array of uploaded files
     */
    public static function getFiles(): array {
        return $_FILES ?? [];
    }
    
    /**
     * Get a specific uploaded file
     * @param string $key File input name
     * @return array|null File data or null if not found
     */
    public static function getFile(string $key): ?array {
        return $_FILES[$key] ?? null;
    }
    
    /**
     * Check if a file was uploaded
     * @param string $key File input name
     * @return bool
     */
    public static function hasFile(string $key): bool {
        return isset($_FILES[$key]) && $_FILES[$key]['error'] === UPLOAD_ERR_OK;
    }
    
    /**
     * Get request method
     * @return string HTTP method
     */
    public static function getMethod(): string {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }
    
    /**
     * Check if request method matches
     * @param string|array $methods Allowed methods
     * @return bool
     */
    public static function isMethod($methods): bool {
        $method = self::getMethod();
        $allowed = is_array($methods) ? $methods : [$methods];
        return in_array($method, $allowed);
    }
    
    /**
     * Get request URI
     * @return string Request URI
     */
    public static function getUri(): string {
        return $_SERVER['REQUEST_URI'] ?? '';
    }
    
    /**
     * Get request path (without query string)
     * @return string Request path
     */
    public static function getPath(): string {
        $uri = self::getUri();
        $path = parse_url($uri, PHP_URL_PATH);
        return $path ?? '';
    }
    
    /**
     * Get pagination parameters
     * @return array ['page' => int, 'per_page' => int, 'offset' => int]
     */
    public static function getPagination(): array {
        $page = max(1, intval(self::get('page', 1)));
        $perPage = max(1, min(100, intval(self::get('per_page', 20))));
        
        return [
            'page' => $page,
            'per_page' => $perPage,
            'offset' => ($page - 1) * $perPage
        ];
    }
    
    /**
     * Get sort parameters
     * @return array ['field' => string|null, 'direction' => 'ASC'|'DESC']
     */
    public static function getSort(): array {
        $sortField = self::get('sort') ?? self::get('sort_field');
        $sortDirection = strtoupper(self::get('sort_direction') ?? self::get('order') ?? 'ASC');
        
        if ($sortDirection !== 'ASC' && $sortDirection !== 'DESC') {
            $sortDirection = 'ASC';
        }
        
        return [
            'field' => $sortField,
            'direction' => $sortDirection
        ];
    }
    
    /**
     * Get filter parameters
     * @param array $allowedFilters Allowed filter keys
     * @return array Filter parameters
     */
    public static function getFilters(array $allowedFilters = []): array {
        $data = self::getRequestData();
        $filters = [];
        
        if (empty($allowedFilters)) {
            // If no allowed filters specified, return all non-reserved keys
            $reserved = ['page', 'per_page', 'sort', 'sort_field', 'sort_direction', 'order'];
            foreach ($data as $key => $value) {
                if (!in_array($key, $reserved)) {
                    $filters[$key] = $value;
                }
            }
        } else {
            foreach ($allowedFilters as $key) {
                if (isset($data[$key])) {
                    $filters[$key] = $data[$key];
                }
            }
        }
        
        return $filters;
    }
    
    /**
     * Reset parsed data (useful for testing)
     */
    public static function reset(): void {
        self::$parsedData = null;
    }
}

