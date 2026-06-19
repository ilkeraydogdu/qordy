<?php
namespace App\Middleware;

use App\Core\TenantContext;
use App\Core\DependencyFactory;
use App\Core\SessionManager;
use App\Repositories\CustomerRepository;

// PSR-7 interfaces are optional - check if available
// Wrap in try-catch to prevent fatal errors if interface doesn't exist
$psr7Available = false;
try {
    $psr7Available = interface_exists('\Psr\Http\Server\MiddlewareInterface', false);
    if (!$psr7Available) {
        // Try to load PSR-7 interfaces via Composer autoloader
        $composerAutoloader = __DIR__ . '/../../vendor/autoload.php';
        if (file_exists($composerAutoloader)) {
            require_once $composerAutoloader;
        }
        // Re-check after autoloader
        $psr7Available = interface_exists('\Psr\Http\Server\MiddlewareInterface', false);
    }
} catch (\Throwable $e) {
    // PSR-7 not available - continue without it
    $psr7Available = false;
}

/**
 * Tenant Middleware
 * Ensures that requests are properly isolated by tenant (business)
 */
class TenantMiddleware {
    private $customerRepository;

    public function __construct() {
        $this->customerRepository = DependencyFactory::getCustomerRepository();
    }

    /**
     * Process an incoming server request and return a response
     * @param mixed $request ServerRequestInterface if PSR-7 available
     * @param mixed $handler RequestHandlerInterface if PSR-7 available
     * @return mixed ResponseInterface if PSR-7 available
     */
    public function process($request, $handler) {
        // Extract tenant identifier from request
        $tenantId = $this->extractTenantId($request);

        if ($tenantId) {
            try {
                // Validate tenant exists
                $customer = $this->customerRepository->findById($tenantId);
                if (!$customer) {
                    throw new \Exception("Tenant not found: {$tenantId}");
                }

                // Set tenant context
                TenantContext::set($customer);
            } catch (\Exception $e) {
                // Log error
                if (class_exists('\App\Core\Logger')) {
                    $uri = method_exists($request, 'getUri') ? $request->getUri()->getPath() : '';
                    \App\Core\Logger::error('Tenant validation failed', [
                        'tenant_id' => $tenantId,
                        'error' => $e->getMessage(),
                        'uri' => $uri
                    ]);
                }

                // Return unauthorized response if PSR-7 available
                if (class_exists('\Slim\Psr7\Response')) {
                    $response = new \Slim\Psr7\Response();
                    return $response
                        ->withStatus(401)
                        ->withHeader('Content-Type', 'application/json')
                        ->withBody(new \Slim\Psr7\Stream(fopen('php://memory', 'r+')));
                }
                // If PSR-7 not available, just return null
                return null;
            }
        }

        // Continue with request processing
        if (method_exists($handler, 'handle')) {
            return $handler->handle($request);
        }
        return null;
    }

    /**
     * Handle tenant context initialization (non-PSR-7 version)
     * Used by App.php and other places that need simple tenant context setup
     * Kept for backward compatibility
     */
    public function handle() {
        try {
            \App\Core\SessionManager::ensureSession();

            if (\App\Core\TenantContext::isSet()) {
                return;
            }

            // Use TenantResolver (session-first) for logged-in non-platform-admin users
            $tenantId = \App\Core\TenantResolver::resolve();
            if ($tenantId) {
                try {
                    $customer = $this->customerRepository->findById($tenantId);
                    if ($customer) {
                        \App\Core\TenantContext::set($customer);
                        return;
                    }
                } catch (\Exception $e) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::warning('TenantMiddleware: failed to load tenant from resolver', [
                            'tenant_id' => $tenantId,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }

            // Fallback: subdomain for anonymous visitors (QR menu etc.)
            $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
            $subdomain = \App\Core\TenantContext::getSubdomainFromHost($host);
            if ($subdomain) {
                try {
                    $customer = $this->customerRepository->findBySubdomain($subdomain);
                    if ($customer) {
                        \App\Core\TenantContext::set($customer);
                        return;
                    }
                } catch (\Exception $e) {
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::warning('Failed to find tenant by subdomain', [
                            'subdomain' => $subdomain,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }

            $customerId = $_SESSION['customer_id'] ?? \App\Core\SessionManager::get('customer_id') ?? null;

            if ($customerId) {
                try {
                    $customer = $this->customerRepository->findById($customerId);
                    if ($customer) {
                        \App\Core\TenantContext::set($customer);
                        return;
                    }
                } catch (\Exception $e) {
                    // Log but continue
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::warning('Failed to find tenant by session customer_id', [
                            'customer_id' => $customerId,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            // Log error but don't break the application
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('Error in TenantMiddleware::handle', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
    }

    /**
     * Extract tenant ID from request
     * @param mixed $request ServerRequestInterface if PSR-7 available
     * @return string|null
     */
    private function extractTenantId($request) {
        // Check if PSR-7 request methods are available
        $hasGetUri = method_exists($request, 'getUri');
        $hasGetHeaderLine = method_exists($request, 'getHeaderLine');
        $hasGetQueryParams = method_exists($request, 'getQueryParams');
        $hasGetParsedBody = method_exists($request, 'getParsedBody');
        
        // Method 1: From subdomain (business.qordy.com)
        if ($hasGetUri) {
            try {
                $uri = $request->getUri();
                if (method_exists($uri, 'getHost')) {
                    $host = $uri->getHost();
                    $hostParts = explode('.', $host);
                    
                    if (count($hostParts) >= 3) {
                        // Assuming format: business.domain.com
                        $subdomain = $hostParts[0];
                        
                        // Validate subdomain looks like a business ID
                        if (preg_match('/^[a-zA-Z0-9_-]+$/', $subdomain) && strlen($subdomain) >= 2) {
                            return $subdomain;
                        }
                    }
                }
            } catch (\Exception $e) {
                // Ignore errors
            }
        }

        // Method 2: From Authorization header (Bearer token)
        if ($hasGetHeaderLine) {
            try {
                $authHeader = $request->getHeaderLine('Authorization');
                if (preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
                    $token = $matches[1];
                    
                    // Validate token format (should be business ID)
                    if (preg_match('/^[a-zA-Z0-9_-]+$/', $token) && strlen($token) >= 2) {
                        return $token;
                    }
                }
            } catch (\Exception $e) {
                // Ignore errors
            }
        }

        // Method 3: From API key in header
        if ($hasGetHeaderLine) {
            try {
                $apiKey = $request->getHeaderLine('X-API-Key');
                if (!empty($apiKey)) {
                    // Lookup business ID by API key
                    return $this->getBusinessIdByApiKey($apiKey);
                }
            } catch (\Exception $e) {
                // Ignore errors
            }
        }

        // Method 4: From query parameter (for desktop app)
        if ($hasGetQueryParams) {
            try {
                $queryParams = $request->getQueryParams();
                if (isset($queryParams['business_id'])) {
                    return $queryParams['business_id'];
                }
            } catch (\Exception $e) {
                // Ignore errors
            }
        }

        // Method 5: From request body
        if ($hasGetParsedBody) {
            try {
                $parsedBody = $request->getParsedBody();
                if (isset($parsedBody['business_id'])) {
                    return $parsedBody['business_id'];
                }
            } catch (\Exception $e) {
                // Ignore errors
            }
        }

        return null;
    }

    /**
     * Get business ID by API key
     * @param string $apiKey
     * @return string|null
     */
    private function getBusinessIdByApiKey(string $apiKey): ?string {
        try {
            $printerBridgeService = DependencyFactory::getPrinterBridgeService();
            $bridge = $printerBridgeService->getBridgeByApiKey($apiKey);
            
            if ($bridge && isset($bridge['tenant_id'])) {
                return $bridge['tenant_id'];
            }
        } catch (\Exception $e) {
            // Log error but continue
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('Failed to lookup business by API key', [
                    'error' => $e->getMessage()
                ]);
            }
        }

        return null;
    }
}