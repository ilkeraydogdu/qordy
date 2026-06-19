<?php
namespace App\Core;

class ServiceProvider {
    protected $container;
    protected $providers = [];
    protected $deferredServices = [];

    public function __construct(Container $container) {
        $this->container = $container;
        $this->registerProviders();
    }

    /**
     * Register all service providers
     */
    protected function registerProviders(): void {
        // Core services
        $this->registerCoreServices();
        
        // Application services
        $this->registerAppServices();
        
        // Third-party services
        $this->registerThirdPartyServices();
    }

    /**
     * Register core services
     */
    protected function registerCoreServices(): void {
        // Database connection
        $this->container->singleton('db', function() {
            $database = new \App\Config\Database();
            return $database->connect();
        });
        
        // Also bind as PDO interface for type hints
        $this->container->singleton(\PDO::class, function($container) {
            return $container->resolve('db');
        });

        // Logger service - bind to interface if exists
        $this->container->singleton(\App\Services\LoggerService::class, function() {
            return new \App\Services\LoggerService();
        });
        $this->container->singleton('logger', function($container) {
            return $container->resolve(\App\Services\LoggerService::class);
        });

        // Cache service - bind to CacheInterface
        $this->container->singleton(\App\Interfaces\CacheInterface::class, function() {
            return \App\Core\DependencyFactory::getCacheService();
        });
        $this->container->singleton(\App\Services\CacheService::class, function($container) {
            return $container->resolve(\App\Interfaces\CacheInterface::class);
        });
        $this->container->singleton('cache', function($container) {
            return $container->resolve(\App\Interfaces\CacheInterface::class);
        });

        // Validation service
        $this->container->singleton(\App\Services\ValidationService::class, function() {
            return new \App\Services\ValidationService();
        });
        $this->container->singleton('validator', function($container) {
            return $container->resolve(\App\Services\ValidationService::class);
        });

        // Payment service
        $this->container->singleton(\App\Services\PaymentService::class, function() {
            return \App\Core\DependencyFactory::getPaymentService();
        });
        $this->container->singleton('payment', function($container) {
            return $container->resolve(\App\Services\PaymentService::class);
        });

        // WebSocket service
        $this->container->singleton(\App\Services\WebSocketService::class, function() {
            return \App\Core\DependencyFactory::getWebSocketService();
        });
        $this->container->singleton('websocket', function($container) {
            return $container->resolve(\App\Services\WebSocketService::class);
        });
    }

    /**
     * Register application services
     */
    protected function registerAppServices(): void {
        // All repositories
        $repos = [
            'user', 'order', 'table', 'category', 'menu_item', 'receipt', 
            'printer', 'shift', 'payment_transaction', 'archived_session',
            'reservation', 'expense', 'invoice', 'supplier', 'system_settings',
            'notification', 'order_item', 'ingredient', 'waste_record',
            'integration_platform', 'role', 'constants',
            'menu_item_translation', 'zone', 'receipt_template',
            'receipt_print_queue', 'stock_movement', 'stock_location',
            'reports', 'leave_type', 'leave', 'medical_report',
            'cashier', 'printer_bridge'
        ];
        
        foreach ($repos as $repo) {
            $repoClass = "App\\Repositories\\" . $this->toPascalCase($repo) . "Repository";
            if (class_exists($repoClass)) {
                $this->container->singleton(
                    $repoClass,
                    function($container) use ($repoClass) {
                        $db = $container->resolve('db');
                        return new $repoClass($db);
                    }
                );
            }
        }

        // All services - register as singletons
        $services = [
            'user', 'order', 'table', 'category', 'menu_item', 'receipt', 
            'printer', 'shift', 'payment_transaction', 'archived_session',
            'reservation', 'finance', 'system_settings', 'notification',
            'toast_notification', 'order_item', 'ingredient', 'waste_record',
            'integration_platform', 'role', 'zone',
            'label', 'formatting', 'url', 'auth_helper', 'logger',
            'validation', 'cache', 'websocket', 'payment', 'seo',
            'design_system', 'search', 'translation', 'constants',
            'two_factor_auth', 'printer_bridge'
        ];
        
        foreach ($services as $service) {
            $serviceClass = "App\\Services\\" . $this->toPascalCase($service) . "Service";
            if (class_exists($serviceClass)) {
                $this->container->singleton(
                    $serviceClass,
                    function($container) use ($serviceClass, $service) {
                        // Try to resolve repository if service needs it
                        $repoClass = "App\\Repositories\\" . $this->toPascalCase($service) . "Repository";
                        if (class_exists($repoClass) && $container->has($repoClass)) {
                            $repo = $container->resolve($repoClass);
                            return new $serviceClass($repo);
                        }
                        // For services that don't need repositories, try to instantiate directly
                        try {
                            return new $serviceClass();
                        } catch (\Exception $e) {
                            // If constructor requires parameters, try with DependencyFactory pattern
                            return \App\Core\DependencyFactory::getService($service);
                        }
                    }
                );
            }
        }
    }
    
    /**
     * Convert snake_case to PascalCase
     * @param string $string
     * @return string
     */
    private function toPascalCase(string $string): string {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));
    }

    /**
     * Register third-party services
     */
    protected function registerThirdPartyServices(): void {
        // Add any third-party integrations here
        // For example: email services, SMS services, etc.
    }

    /**
     * Get service from container
     * @param string $service Service name or class
     * @return mixed Service instance
     */
    public function get(string $service) {
        return $this->container->resolve($service);
    }

    /**
     * Check if service exists
     * @param string $service Service name or class
     * @return bool Whether service exists
     */
    public function has(string $service): bool {
        return $this->container->has($service);
    }

    /**
     * Register a deferred service
     * @param string $service Service name
     * @param callable $provider Provider function
     */
    public function defer(string $service, callable $provider): void {
        $this->deferredServices[$service] = $provider;
    }

    /**
     * Load deferred services
     */
    public function loadDeferredServices(): void {
        foreach ($this->deferredServices as $service => $provider) {
            $this->container->bind($service, $provider);
        }
    }

    /**
     * Get the container instance
     * @return Container
     */
    public function getContainer(): Container {
        return $this->container;
    }

    /**
     * Boot all registered providers
     */
    public function boot(): void {
        // Any bootstrapping logic can go here
        // For example: registering middleware, routes, etc.
    }

    /**
     * Register a custom service provider
     * @param string $provider Provider class name
     */
    public function registerProvider(string $provider): void {
        if (class_exists($provider)) {
            $instance = new $provider($this->container);
            if (method_exists($instance, 'register')) {
                $instance->register();
            }
            if (method_exists($instance, 'boot')) {
                $instance->boot();
            }
        }
    }
}