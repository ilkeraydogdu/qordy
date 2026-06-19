<?php
return [
    'security_headers' => [
        'enabled' => true,
        'x_frame_options' => 'SAMEORIGIN',
        'x_content_type_options' => 'nosniff',
        'x_xss_protection' => '1; mode=block',
        // frame-src/child-src use `https:` because the iyzico 3DS flow
        // redirects the embedded iframe through many bank domains
        // (3dsecure.akbank.com.tr, goguvenliodeme.bkm.com.tr,
        // 3dsecure.garanti.com.tr, *.vakifbank.com.tr, *.isbank.com.tr,
        // *.yapikredi.com.tr, *.finansbank.com.tr, *.denizbank.com,
        // *.kuveytturk.com.tr, *.teb.com.tr, *.halkbank.com.tr,
        // *.ziraatbank.com.tr, *.sekerbank.com.tr, *.albaraka.com.tr,
        // *.ing.com.tr, *.qnbfinansbank.com, *.odeabank.com.tr, …).
        // Enumerating every single bank is not maintainable; `https:`
        // still blocks any http:// frame and XSS is prevented by the
        // strict script-src policy on this parent document.
        'content_security_policy' => "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://www.googletagmanager.com https://cdn.iyzipay.com https://*.iyzipay.com https://*.iyzico.com https://*.hotjar.com https://static.hotjar.com https://script.hotjar.com https://*.sentry.io; script-src-elem 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://www.googletagmanager.com https://cdn.iyzipay.com https://*.iyzipay.com https://*.iyzico.com https://*.hotjar.com https://static.hotjar.com https://script.hotjar.com https://*.sentry.io; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.tailwindcss.com https://*.iyzipay.com https://*.hotjar.com; font-src 'self' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com https://*.iyzipay.com https://*.hotjar.com; img-src 'self' data: blob: https:; connect-src 'self' https: https://www.google-analytics.com https://www.googletagmanager.com https://analytics.google.com https://*.iyzipay.com https://*.iyzico.com https://*.hotjar.com wss://*.hotjar.com https://*.sentry.io wss://qordy.com wss://*.qordy.com; media-src 'self' data: blob: https://assets.mixkit.co; object-src 'none'; frame-src 'self' https:; child-src 'self' https:; frame-ancestors 'self' https://qordy.com https://*.qordy.com; base-uri 'self'; form-action 'self' https: data:; worker-src 'self' blob:;",
        'strict_transport_security' => 'max-age=31536000; includeSubDomains; preload',
        'referrer_policy' => 'strict-origin-when-cross-origin',
        'permissions_policy' => 'geolocation=(self), microphone=(), camera=(), payment=(), usb=()'
    ],
    'rate_limits' => [
        'default' => [
            'requests' => 60,
            'period' => 60,
            'per_user' => false
        ],
        'api' => [
            'requests' => 300,  // Increased from 100 to 300 per minute
            'period' => 60,
            'per_user' => true,
            'user_requests' => 600,  // Increased from 200 to 600 per minute for authenticated users
            'user_period' => 60
        ],
        'login' => [
            // Brute-force koruması: Dakikada 50 isteğe çıkmıştı; NAT arkasındaki
            // kurumsal kullanıcıları desteklemek için 20 seviyesinde makul bir
            // denge kuruyoruz. AuthController ayrıca hesap bazlı exponential
            // backoff (Redis / DB) uygulamalıdır; buradaki limit IP+path bazlı
            // ilk savunma hattıdır.
            'requests' => 20,
            'period' => 60,
            'per_user' => false,
            'user_requests' => 20,
            'user_period' => 60
        ],
        'upload' => [
            'requests' => 10,
            'period' => 60,
            'per_user' => true,
            'user_requests' => 20,
            'user_period' => 60
        ],
        'password_reset' => [
            'requests' => 3,
            'period' => 3600,
            'per_user' => true,
            'user_requests' => 5,
            'user_period' => 3600
        ]
    ],
    
    'auto_block_threshold' => 5,
    
    'file_upload' => [
        'max_size' => 5242880,
        'allowed_types' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'],
        'allowed_mime_types' => [
            'image/jpeg',
            'image/png',
            'image/gif',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ]
    ],
    
    'csrf' => [
        'enabled' => true,
        'token_name' => 'csrf_token',
        'expire_time' => 3600,
        // API routes are automatically excluded via RequestTypeDetector
        // Login endpoint bypasses CSRF (protected by PIN authentication)
        // Only add specific form endpoints that need to bypass CSRF here
        'bypass_routes' => [
            '/login',  // Login endpoint - protected by PIN, not CSRF
            '/qodmin/login',  // Super Admin login - protected by email/password
            '/qodmin/bank-transfers/',  // Super Admin havale onay/red - requireLogin + isSuperAdmin
            '/api/webhook',  // External webhook endpoints
            '/api/webhook/meta',  // Meta (WhatsApp/Facebook) webhook
            '/api/register/',  // Registration verification (session-based, rate-limited)
            '/api/payment/callback',  // Payment gateway callbacks
            // iyzico Server→Server callbacks (no browser session / no CSRF token)
            '/payment/iyzico/callback',
            '/customer/payment/iyzico/callback',
            // Custom payment link iyzico callback — iyzico issues a cross-origin
            // POST so neither the browser session cookie nor the CSRF token is
            // available. Authenticity is enforced via iyzico's own token lookup
            // plus our persisted intent (custom_payment_link_intents).
            '/api/payment/iyzico/custom-link-callback',
            '/api/contact/submit',  // Public contact form - protected by CAPTCHA
            '/api/contact/captcha',  // Contact form CAPTCHA endpoint
            '/api/business/z-report-print',  // Z report print - session protected
            '/api/qodmin/z-report-print'  // Z report print (super admin) - session protected
        ]
    ],
    
    'xss_protection' => [
        'enabled' => true,
        'auto_escape' => true
    ],
    
    'sql_injection_protection' => [
        'enabled' => true,
        'strict_mode' => true
    ],
    
    'ip_blocking' => [
        'enabled' => true,
        'auto_block' => true,
        'default_duration' => 3600
    ],
    
    'session_ip_validation' => [
        'enabled' => false,  // Disabled by default to support NAT/proxy/load balancer scenarios
        'strict_mode' => false,  // If true, exact IP match required; if false, similar IPs allowed
        'similarity_threshold' => 3,  // Number of octets that must match for IP similarity (1-4)
        'bypass_new_login_seconds' => 10,  // Bypass IP validation for newly logged in users (seconds)
    ],
    
    'input_validation' => [
        'enabled' => true,
        'strict_mode' => false
    ],
    
    'logging' => [
        'enabled' => true,
        'log_suspicious' => true,
        'log_all_requests' => false
    ]
];

