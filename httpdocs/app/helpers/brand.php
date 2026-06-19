<?php
/**
 * Canonical Qordy brand asset URLs — shared by React SPA, admin shell, and business topbar.
 */

if (!function_exists('resolveQordyCorporateLogoUrl')) {
    /**
     * Official Qordy wordmark for light backgrounds (settings override, then default PNG).
     */
    function resolveQordyCorporateLogoUrl(): string
    {
        $default = rtrim(BASE_URL, '/') . '/assets/images/logo.png';
        try {
            $settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
            if ($settingsService !== null) {
                $fromDb = trim((string) $settingsService->getSetting('logo_url'));
                if ($fromDb !== '') {
                    return $fromDb;
                }
            }
        } catch (\Throwable $e) {
            // fall through to default
        }
        return $default;
    }
}

if (!function_exists('getQordyLogoUrl')) {
    /** Alias for resolveQordyCorporateLogoUrl(). */
    function getQordyLogoUrl(): string
    {
        return resolveQordyCorporateLogoUrl();
    }
}

if (!function_exists('getQordyLogoLightUrl')) {
    /** White wordmark for dark / tinted surfaces. */
    function getQordyLogoLightUrl(): string
    {
        return rtrim(BASE_URL, '/') . '/assets/images/logo_light.png';
    }
}
