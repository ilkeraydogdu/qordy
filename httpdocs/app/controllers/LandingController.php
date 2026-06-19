<?php
namespace App\Controllers;

require_once __DIR__ . '/../core/Controller.php';

class LandingController extends \App\Core\Controller {
    
    /**
     * Override constructor to handle database errors gracefully for landing pages
     */
    public function __construct() {
        // Skip validation on landing pages to prevent redirect loops
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        \App\Core\SessionManager::ensureSession(false);
        \App\Core\HelperLoader::loadHelpers();
        
        // Initialize services with error handling for landing pages
        try {
            $this->auth = \App\Core\Authorization::getInstance();
        } catch (\Exception $e) {
            // Auth not critical for landing pages
            $this->auth = null;
        }
        
        // Skip firewall initialization for landing pages (not needed)
        $this->firewall = null;
        
        // Initialize services with error handling (may require database)
        try {
            $this->translationService = getTranslationService();
        } catch (\Exception $e) {
            $this->translationService = null;
        }
        
        // Skip database-dependent services for landing pages
        // These services may not be available or may require database connection
        $this->notificationService = null;
        $this->toastNotificationService = null;
        $this->seoService = null;
        $this->filterService = null;
        $this->searchService = null;
    }
    
    public function index() {
        // Ana sayfa - tanıtım, özellikler, paket özeti
        // Session kontrolü
        \App\Core\SessionManager::ensureSession();
        
        // CRITICAL: Custom domain ve subdomain kontrolü
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
        $subdomain = \App\Core\TenantContext::getSubdomainFromHost($host);
        $baseDomain = $_ENV['BASE_DOMAIN'] ?? 'qordy.com';
        
        // Check if this is a custom domain (not qordy.com or subdomain of qordy.com)
        $isCustomDomain = ($host !== $baseDomain && strpos($host, '.' . $baseDomain) === false);
        $customer = null;
        
        if ($isCustomDomain) {
            // Try to find customer by custom domain
            try {
                require_once __DIR__ . '/../repositories/CustomerRepository.php';
                $customerRepository = new \App\Repositories\CustomerRepository();
                $customer = $customerRepository->findByCustomDomain($host);
            } catch (\Exception $e) {
                if (class_exists('\App\Core\Logger')) {
                    \App\Core\Logger::error('LandingController::index - Error finding custom domain', [
                        'error' => $e->getMessage(),
                        'host' => $host
                    ]);
                }
            }
        }
        
        // Subdomain kontrolü - eğer subdomain varsa ve custom domain değilse login'e yönlendir
        if ($subdomain && !$isCustomDomain) {
            // Check if tenant exists for this subdomain
            $tenant = \App\Core\TenantContext::get();
            
            // If tenant is not set yet, try to find it
            if (!$tenant) {
                try {
                    require_once __DIR__ . '/../middleware/TenantMiddleware.php';
                    $tenantMiddleware = new \App\Middleware\TenantMiddleware();
                    $tenantMiddleware->handle();
                    $tenant = \App\Core\TenantContext::get();
                } catch (\Exception $e) {
                    // Log but continue
                    if (class_exists('\App\Core\Logger')) {
                        \App\Core\Logger::warning('Error initializing tenant in LandingController', [
                            'error' => $e->getMessage(),
                            'subdomain' => $subdomain
                        ]);
                    }
                }
            }
            
            if ($tenant) {
                // Subdomain exists and tenant is set, redirect to login
                header('Location: ' . BASE_URL . '/login');
                exit;
            }
        }
        
        // Giriş yapmış kullanıcılar landing sayfasını görebilir
        // "Planı Seç" butonları otomatik olarak doğru sayfaya yönlendirecek
        
        $packages = $this->loadActivePackagesWithFeatures();

        $this->render('landing/index', [
            'packages' => $packages,
            'customDomainCustomer' => $customer,
            'isCustomDomain' => $isCustomDomain && $customer !== null
        ]);
    }
    
    public function pricing() {
        $this->redirectToLandingHash('fiyat');
    }
    
    public function features() {
        \App\Core\SessionManager::ensureSession();
        // Serve SPA so client can read #qr / #kitchen hashes (fragments are not sent to PHP).
        $this->render('landing/index', []);
    }

    public function about() {
        $this->redirectToLandingHash('hakkimizda');
    }

    public function contact() {
        $this->redirectToLandingHash('iletisim');
    }

    public function privacy() {
        \App\Core\SessionManager::ensureSession();
        $this->render('landing/index', []);
    }

    public function terms() {
        \App\Core\SessionManager::ensureSession();
        $this->render('landing/index', []);
    }

    /**
     * Public JSON endpoint that exposes the centrally-managed trial
     * settings (duration, enablement, data retention) to any non-SPA
     * client (e.g. external landing pages or caches that don't consume
     * the SSR bootstrap payload).
     */
    public function apiTrialSettings() {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: public, max-age=120');

        $duration = 14;
        $grace = 7;
        $enabled = true;

        try {
            $trialService = \App\Core\DependencyFactory::getTrialService();
            if ($trialService) {
                $settings = $trialService->getTrialSettings();
                $duration = (int)($settings['trial_duration_days'] ?? 14);
                $grace = (int)($settings['grace_period_days'] ?? 7);
                $enabled = (bool)($settings['trial_enabled'] ?? 1);
            }
        } catch (\Throwable $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('LandingController::apiTrialSettings failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        echo json_encode([
            'enabled'             => $enabled,
            'duration_days'       => $duration,
            'data_retention_days' => $grace + 30,
        ]);
        exit;
    }

    /**
     * Public JSON endpoint that powers the React SPA pricing section.
     * Returns the same hydrated package data the landing view uses.
     */
    public function apiPackages() {
 if (ob_get_level() > 0) {
 ob_clean();
 }
 header('Content-Type: application/json; charset=utf-8');
 header('Cache-Control: public, max-age=120');
 try {
 $packages = $this->loadActivePackagesWithFeatures();
 echo json_encode([
 'packages' => array_values($packages),
 ], JSON_UNESCAPED_UNICODE);
 } catch (\Throwable $e) {
 if (class_exists('\\App\\Core\\Logger')) {
 \App\Core\Logger::error('LandingController::apiPackages failed', [
 'error' => $e->getMessage(),
 'file' => $e->getFile(),
 'line' => $e->getLine(),
 ]);
 }
 // DB kopuk olsa bile fallback dondur (200 ile)
 echo json_encode([
 'packages' => self::getFallbackPackages(),
 ], JSON_UNESCAPED_UNICODE);
 }
 exit;
 }

 /**
 * DB baglanti hatasi durumunda fallback paket listesi.
 */
 private static function getFallbackPackages(): array {
 return [
 [
 'package_id' => 'starter',
 'name' => 'Başlangıç',
 'description' => 'Küçük işletmeler için ideal başlangıç paketi.',
 'price_monthly' => 899.0,
 'price_yearly' => 8990.0,
 'discount_percentage' => 0,
 'features_array' => [
 'Sınırsız Menü Yönetimi',
 'QR Kod ile Sipariş',
 'Temel Raporlama',
 '1 Şube Desteği',
 '7/24 Canlı Destek',
 ],
 'is_featured' => false,
 ],
 [
 'package_id' => 'professional',
 'name' => 'Profesyonel',
 'description' => 'Büyüyen işletmeler için gelişmiş özellikler.',
 'price_monthly' => 1999.0,
 'price_yearly' => 19990.0,
 'discount_percentage' => 0,
 'features_array' => [
  'Başlangıç paketinin tüm özellikleri',
 'Sınırsız Şube Yönetimi',
 'Garson POS Uygulaması',
 'Stok ve Maliyet Yönetimi',
 'Gelişmiş Analitik Dashboard',
 'Müşteri Sadakat Programı',
 ],
 'is_featured' => true,
 ],
 [
 'package_id' => 'enterprise',
 'name' => 'Kurumsal',
 'description' => 'Zincir işletmeler için özel çözümler.',
 'price_monthly' => 3999.0,
 'price_yearly' => 39990.0,
 'discount_percentage' => 0,
 'features_array' => [
 'Profesyonel paketinin tüm özellikleri',
 'Özel Entegrasyonlar (ERP, Muhasebe)',
 'Çoklu Marka Yönetimi',
 'Dedike Müşteri Temsilcisi',
  'SLA Destek (4 saat yanıt)',
 'Özel Eğitim ve Kurulum',
 ],
 'is_featured' => false,
 ],
 ];
 }
    
    /**
     * Serve manifest.json for PWA
     */
    public function manifest() {
        header('Content-Type: application/json; charset=utf-8');
        
        $manifestPath = __DIR__ . '/../../public/manifest.json';
        if (file_exists($manifestPath)) {
            readfile($manifestPath);
        } else {
            // Return default manifest
            echo json_encode([
                'name' => 'Qordy',
                'short_name' => 'Qordy',
                'start_url' => '/',
                'display' => 'standalone',
                'background_color' => '#ffffff',
                'theme_color' => '#f97316',
                'icons' => [
                    [
                        'src' => '/assets/images/favicon.png',
                        'sizes' => '192x192',
                        'type' => 'image/png'
                    ]
                ]
            ], JSON_PRETTY_PRINT);
        }
        exit;
    }
    
    /**
     * Serve download files (APK, etc.)
     */
    public function download($file) {
        // Security: Only allow alphanumeric, dots, dashes, and underscores in filename
        if (!preg_match('/^[a-zA-Z0-9._-]+$/', $file)) {
            http_response_code(400);
            echo 'Invalid filename';
            exit;
        }
        
        $downloadPath = __DIR__ . '/../../public/downloads/' . $file;
        
        if (!file_exists($downloadPath) || !is_file($downloadPath)) {
            http_response_code(404);
            echo 'File not found';
            exit;
        }
        
        // Determine MIME type
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $mimeTypes = [
            'apk' => 'application/vnd.android.package-archive',
            'exe' => 'application/x-msdownload',
            'zip' => 'application/zip',
            'pdf' => 'application/pdf',
        ];
        
        $contentType = $mimeTypes[$extension] ?? 'application/octet-stream';
        
        // Set headers for download
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        header('Content-Length: ' . filesize($downloadPath));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Output file
        readfile($downloadPath);
        exit;
    }

    private function loadActivePackagesWithFeatures(): array {
        try {
            $packageService = \App\Core\DependencyFactory::getPackageService();
            $packages = $packageService->getActivePackages();

            foreach ($packages as &$package) {
                $packageId = $package['package_id'] ?? $package['id'] ?? null;

                $package['monthly_discount'] = $packageService->calculateDiscount($package, 'monthly');
                $package['yearly_discount'] = $packageService->calculateDiscount($package, 'yearly');
                $package['discounted_price_monthly'] = $packageService->getDiscountedPrice($package, 'monthly');
                $package['discounted_price_yearly'] = $packageService->getDiscountedPrice($package, 'yearly');

                $dynamicFeatures = [];
                if (!empty($packageId)) {
                    $dynamicFeatures = $packageService->getPackageFeaturesFromPermissions($packageId);
                }

                $staticFeatures = $packageService->formatFeaturesForDisplay($package['features'] ?? null);
                $package['features_array'] = !empty($dynamicFeatures) ? $dynamicFeatures : $staticFeatures;
            }
            unset($package);

            return $this->filterMarketingPackages($packages);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('LandingController::loadActivePackagesWithFeatures failed', [
                    'error' => $e->getMessage()
                ]);
            }
            return [];
        }
    }

    /**
     * Exclude staging/test packages from public marketing (e.g. İYZİCO TEST).
     */
    private function filterMarketingPackages(array $packages): array {
        return array_values(array_filter($packages, function ($package) {
            $name = mb_strtolower((string)($package['name'] ?? ''));
            $id = mb_strtolower((string)($package['package_id'] ?? $package['id'] ?? ''));
            if (str_contains($name, 'test') || str_contains($id, 'test')) {
                return false;
            }
            $monthly = (float)($package['price_monthly'] ?? $package['monthly'] ?? 0);
            if (str_contains($name, 'iyzico') && $monthly <= 1) {
                return false;
            }
            return true;
        }));
    }

    /**
     * Retired marketing sub-pages → one-page landing with section anchor.
     */
    private function redirectToLandingHash(string $sectionId): void {
        $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
        $hash = ltrim($sectionId, '#');
        header('Location: ' . $base . '/#' . rawurlencode($hash), true, 301);
        exit;
    }
}
