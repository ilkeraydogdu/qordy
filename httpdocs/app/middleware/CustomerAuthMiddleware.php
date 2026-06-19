<?php
namespace App\Middleware;

require_once __DIR__ . '/../core/Middleware.php';

class CustomerAuthMiddleware extends \App\Core\Middleware {
    
    public function handle($request, $next) {
        // Session başlat
        \App\Core\SessionManager::ensureSession();
        
        // Customer login kontrolü
        if (!isset($_SESSION['customer_logged_in']) || $_SESSION['customer_logged_in'] !== true) {
            // CRITICAL: Use current host (with subdomain) for redirect
            $currentHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            header('Location: ' . $protocol . '://' . $currentHost . '/login');
            exit;
        }
        
        return $next($request);
    }
}
