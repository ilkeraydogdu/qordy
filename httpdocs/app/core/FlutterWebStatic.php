<?php
namespace App\Core;

/**
 * Flutter Web derlemesini (public/mobile-app) PHP üzerinden sunar.
 */
class FlutterWebStatic
{
    public static function attemptServe(?string $parsedUri = null): void
    {
        $candidates = [];
        if ($parsedUri !== null && $parsedUri !== '') {
            $candidates[] = $parsedUri;
        }
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (is_string($path) && $path !== '') {
            $candidates[] = $path;
        }
        if (isset($_GET['url']) && $_GET['url'] !== '' && $_GET['url'] !== null) {
            $candidates[] = '/' . ltrim((string) $_GET['url'], '/');
        }

        foreach ($candidates as $c) {
            $n = '/' . ltrim((string) $c, '/');
            if (stripos($n, '/mobile-app') === 0) {
                self::serve(strtolower($n));
                exit;
            }
        }
    }

    private static function serve(string $uri): void
    {
        $baseDir = realpath(__DIR__ . '/../../public/mobile-app');
        if ($baseDir === false || !is_dir($baseDir)) {
            http_response_code(503);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Mobil uygulama dosyalari bulunamadi.';
            exit;
        }

        $prefix = '/mobile-app';
        $remainder = substr($uri, strlen($prefix));
        if ($remainder === false) {
            $remainder = '';
        }
        if ($remainder === '' || $remainder === '/') {
            self::outputFile($baseDir . DIRECTORY_SEPARATOR . 'index.html', 'text/html; charset=UTF-8');
            return;
        }

        $relative = ltrim($remainder, '/');
        $relative = str_replace("\0", '', $relative);
        $relative = str_replace('..', '', $relative);
        $fullPath = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $resolved = realpath($fullPath);

        if ($resolved !== false
            && strpos($resolved, $baseDir) === 0
            && is_file($resolved)
        ) {
            self::outputFile($resolved, self::mimeType($resolved));
            return;
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'GET' || $method === 'HEAD') {
            $index = $baseDir . DIRECTORY_SEPARATOR . 'index.html';
            if (is_file($index)) {
                self::outputFile($index, 'text/html; charset=UTF-8', $method === 'HEAD');
                return;
            }
        }

        http_response_code(404);
        exit;
    }

    private static function outputFile(string $path, string $mimeType, bool $headOnly = false): void
    {
        if (!is_file($path)) {
            http_response_code(404);
            exit;
        }

        header('Content-Type: ' . $mimeType);
        header('X-Frame-Options: SAMEORIGIN');

        $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        $cacheable = ['js', 'css', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'json', 'woff', 'woff2', 'ttf', 'wasm', 'map', 'otf'];
        if (in_array($ext, $cacheable, true)) {
            header('Cache-Control: public, max-age=604800, must-revalidate');
        } else {
            header('Cache-Control: no-cache, must-revalidate');
        }

        $length = filesize($path);
        if ($length !== false) {
            header('Content-Length: ' . $length);
        }

        if (!$headOnly) {
            readfile($path);
        }
        exit;
    }

    private static function mimeType(string $filePath): string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mimeTypes = [
            'js' => 'application/javascript',
            'css' => 'text/css',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject',
            'txt' => 'text/plain',
            'xml' => 'application/xml',
            'json' => 'application/json',
            'wasm' => 'application/wasm',
            'map' => 'application/json',
            'html' => 'text/html; charset=UTF-8',
        ];

        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }
}
