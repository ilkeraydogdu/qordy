<?php
namespace App\Core;

class HelperLoader {
    private static $loaded = false;

    public static function loadHelpers(): void {
        if (self::$loaded) {
            return;
        }

        if (!defined('BASE_URL')) {
            require_once __DIR__ . '/../config/config.php';
        }

        $helperDir = __DIR__ . '/../helpers';
        
        $helpers = [
            'functions.php',
            'auth.php',
            'security.php',
            'ui.php',
            'role_helpers.php',
            'translations.php',
            'toast.php',
            'seo.php',
            'sounds.php',
            'receipt.php',
            'print.php',
            'json_helper.php',
            'escpos.php',
            'url_helper.php',
            'brand.php',
        ];

        foreach ($helpers as $helper) {
            $helperPath = $helperDir . '/' . $helper;
            if (file_exists($helperPath)) {
                $mainFunction = self::getMainFunction($helper);
                if (empty($mainFunction) || !function_exists($mainFunction)) {
                    require_once $helperPath;
                }
            }
        }

        self::$loaded = true;
    }

    public static function ensureLoaded(): void {
        self::loadHelpers();
    }

    private static function getMainFunction(string $filename): string {
        $map = [
            'functions.php' => 'formatCurrency',
            'auth.php' => 'getCurrentUser',
            'security.php' => 'generateCSRFToken',
            'ui.php' => 'getIcon',
            'translations.php' => 't',
            'toast.php' => 'showToast',
            'seo.php' => 'generateRestaurantStructuredData',
            'sounds.php' => 'playSound',
            'receipt.php' => 'generateReceiptNumber',
            'print.php' => 'printReceipt',
            'json_helper.php' => 'safeJsonEncode',
            'escpos.php' => 'escpos_init',
            'brand.php' => 'getQordyLogoUrl',
        ];
        return $map[$filename] ?? '';
    }
}

