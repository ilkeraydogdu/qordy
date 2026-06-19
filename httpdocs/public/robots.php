<?php
/**
 * Dynamic robots.txt generator
 * Outputs robots.txt content based on environment and BASE_URL.
 *
 * Explicit allow list for /blog and /blog/* ensures search engines
 * aggressively crawl the Soro-powered AI blog.
 */

require_once __DIR__ . '/../app/config/config.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$baseUrl    = defined('BASE_URL') ? BASE_URL : 'http://localhost';
$sitemapUrl = rtrim($baseUrl, '/') . '/sitemap.xml';

echo "# Qordy — Global robots.txt\n";
echo "# Generated: " . date('c') . "\n\n";

// Default section for all crawlers
echo "User-agent: *\n";
echo "Disallow: /admin/\n";
echo "Disallow: /qodmin/\n";
echo "Disallow: /api/\n";
echo "Disallow: /app/\n";
echo "Disallow: /config/\n";
echo "Disallow: /vendor/\n";
echo "Disallow: /database/\n";
echo "Disallow: /logs/\n";
echo "Disallow: /cache/\n";
echo "Disallow: /storage/\n";
echo "Disallow: /cron/\n";
echo "Disallow: /scripts/\n";
echo "Disallow: /tmp/\n";
echo "Disallow: /business/\n";      // tenant/işletme paneli
echo "Disallow: /customer/\n";      // müşteri oturum sayfaları
echo "Disallow: /waiter/\n";        // garson paneli
echo "Disallow: /kitchen/\n";       // mutfak ekranı
echo "Disallow: /cashier/\n";       // kasa paneli
echo "Disallow: /ws\n";             // WebSocket endpoint
echo "Disallow: /t/\n";             // masa QR — tarayıcı trafiğinde sızdırmayalım
echo "Disallow: /*?utm_\n";         // UTM parametreli variant'lar indexlenmesin
echo "Disallow: /*?ref=\n";
echo "Disallow: /clear-cache.php\n";
echo "Disallow: /clear-cache.html\n";
echo "Disallow: /force-refresh.html\n";
echo "Disallow: /cache-killer.html\n";
echo "Disallow: /auto-cache-killer.js\n";
echo "Disallow: /check-role.php\n";
echo "\n";
echo "# Main public areas — explicitly allow\n";
echo "Allow: /\n";
echo "Allow: /menu\n";
echo "Allow: /login\n";
echo "Allow: /register\n";
echo "Allow: /pricing\n";
echo "Allow: /features\n";
echo "# Blog (Soro-powered AI blog) — fully crawlable\n";
echo "Allow: /blog\n";
echo "Allow: /blog/\n";
echo "Allow: /blog/*\n";
echo "Allow: /blog/category/*\n";
echo "Allow: /sitemap.xml\n";
echo "Allow: /robots.txt\n";
echo "\n";
echo "# Let crawlers read CSS/JS so they can render the Soro widget properly\n";
echo "Allow: /assets/js/\n";
echo "Allow: /assets/css/\n";
echo "Allow: /assets/images/\n";
echo "Allow: /public/assets/\n";
echo "\n";

// Dedicated friendly sections for major crawlers – unblock everything but
// the sensitive paths so they can execute JS for the Soro embed.
foreach (['Googlebot', 'Googlebot-Image', 'Bingbot', 'DuckDuckBot', 'Applebot', 'YandexBot'] as $ua) {
    echo "User-agent: {$ua}\n";
    echo "Disallow: /admin/\n";
    echo "Disallow: /qodmin/\n";
    echo "Disallow: /api/\n";
    echo "Disallow: /cache/\n";
    echo "Disallow: /storage/\n";
    echo "Disallow: /logs/\n";
    echo "Disallow: /config/\n";
    echo "Disallow: /vendor/\n";
    echo "Disallow: /business/\n";
    echo "Disallow: /customer/\n";
    echo "Disallow: /waiter/\n";
    echo "Disallow: /kitchen/\n";
    echo "Disallow: /cashier/\n";
    echo "Disallow: /ws\n";
    echo "Allow: /\n";
    echo "Allow: /features\n";
    echo "Allow: /pricing\n";
    echo "Allow: /blog\n";
    echo "Allow: /blog/\n";
    echo "Allow: /blog/*\n";
    echo "Allow: /menu\n";
    echo "\n";
}

// Disallow known AI training crawlers if the site policy requires it
foreach (['GPTBot', 'ClaudeBot', 'CCBot', 'anthropic-ai', 'Google-Extended'] as $ua) {
    echo "User-agent: {$ua}\n";
    echo "Disallow: /admin/\n";
    echo "Disallow: /qodmin/\n";
    echo "Disallow: /api/\n";
    echo "Allow: /blog\n";
    echo "Allow: /blog/*\n";
    echo "\n";
}

echo "# Sitemap(s)\n";
echo "Sitemap: {$sitemapUrl}\n";
echo "\n";
echo "Crawl-delay: 1\n";
