<?php
$settings = $settings ?? [];
$baseUrl = BASE_URL;

require_once __DIR__ . '/../../core/Authorization.php';
require_once __DIR__ . '/../../helpers/translations.php';
if (!function_exists('getAdminUrl')) {
    require_once __DIR__ . '/../../helpers/url_helper.php';
}
$auth = \App\Core\Authorization::getInstance();
$canEdit = $auth->hasPermission('settings.edit');
$canReset = $auth->hasPermission('settings.reset');
$isSuperAdmin = $is_super_admin ?? false;
$adminPrefix = $isSuperAdmin ? '/qodmin' : '/business';
$apiPrefix = $isSuperAdmin ? '/api/qodmin' : '/api/business';

// Default values
$siteName = $settings['site_name'] ?? getAppConfig()->getAppName() . ' - Akıllı Restoran Sistemi';
$logoUrl = $settings['logo_url'] ?? BASE_URL . '/assets/images/logo.png';
$faviconUrl = $settings['favicon_url'] ?? BASE_URL . '/assets/images/favicon.ico';
$serviceChargeRate = $settings['service_charge_rate'] ?? 0;
$coverCharge = $settings['cover_charge'] ?? 0;
$currency = $settings['currency'] ?? getAppConfig()->getCurrency();
$orderIdPrefix = $settings['order_id_prefix'] ?? 'cd';
$orderNumberLength = isset($settings['order_number_length']) ? (int)$settings['order_number_length'] : 3;
// Order edit approval settings
$orderEditRequiresApproval = isset($settings['order_edit_requires_approval']) && ($settings['order_edit_requires_approval'] === '1' || $settings['order_edit_requires_approval'] === 1 || $settings['order_edit_requires_approval'] === true);
$orderEditApprovalRole = $settings['order_edit_approval_role'] ?? 'MANAGER';

// Get roles for dropdown
$roleService = \App\Core\DependencyFactory::getRoleService();
$allRoles = $roleService->getActiveRoles();
$appEnv = $settings['app_env'] ?? 'development';
// Parse boolean values correctly
$appDebug = isset($settings['app_debug']) ? ($settings['app_debug'] === 'true' || $settings['app_debug'] === true || $settings['app_debug'] === '1' || $settings['app_debug'] === 1) : false;
$timezone = $settings['timezone'] ?? getAppConfig()->getTimezone();
$defaultLanguage = $settings['default_language'] ?? getAppConfig()->getDefaultLanguage();
// WiFi settings
$wifiName = $settings['wifi_name'] ?? '';
$wifiPassword = $settings['wifi_password'] ?? '';
$wifiShowToCustomer = isset($settings['wifi_show_to_customer']) && ($settings['wifi_show_to_customer'] === '1' || $settings['wifi_show_to_customer'] === 1 || $settings['wifi_show_to_customer'] === true);
$sessionTimeout = isset($settings['session_timeout']) ? (int)$settings['session_timeout'] : 1440; // Default: 24 hours
$maxUploadSize = isset($settings['max_upload_size']) ? (int)$settings['max_upload_size'] : 10;
// Business location settings
$businessLatitude = $settings['business_latitude'] ?? '';
$businessLongitude = $settings['business_longitude'] ?? '';
$businessRadius = isset($settings['business_radius']) ? (int)$settings['business_radius'] : 500;
$businessAddress = $settings['business_address'] ?? '';

$paymentBankTransferEnabled = isset($settings['payment_bank_transfer_enabled']) && ($settings['payment_bank_transfer_enabled'] === '1' || $settings['payment_bank_transfer_enabled'] === 1 || $settings['payment_bank_transfer_enabled'] === true);

// Parse supported languages JSON
$supportedLanguagesJson = $settings['supported_languages'] ?? '["tr","en"]';
$supportedLanguages = json_decode($supportedLanguagesJson, true);
if (!is_array($supportedLanguages) || empty($supportedLanguages)) {
    $supportedLanguages = ['tr', 'en'];
}

// Parse boolean values for language settings
$languageSwitcherEnabled = isset($settings['language_switcher_enabled']) 
    ? ($settings['language_switcher_enabled'] === '1' || $settings['language_switcher_enabled'] === 1 || $settings['language_switcher_enabled'] === 'true' || $settings['language_switcher_enabled'] === true) 
    : true;
$autoDetectLanguage = isset($settings['auto_detect_language']) 
    ? ($settings['auto_detect_language'] === '1' || $settings['auto_detect_language'] === 1 || $settings['auto_detect_language'] === 'true' || $settings['auto_detect_language'] === true) 
    : false;

// Translations for JavaScript
$settingsTranslations = [
    'noEditPermission' => t('settings.noEditPermissionShort'),
    'saving' => t('settings.saving'),
    'resetConfirm1' => t('settings.resetConfirm1'),
    'resetConfirm2' => t('settings.resetConfirm2'),
    'resetSuccess' => t('settings.resetSuccess'),
    'resetFailed' => t('settings.resetFailed'),
];
?>

<div class="q-page q-biz-theme animate-slide-up">
  <div class="q-container q-stack q-stack--lg">
    <?php if ($is_super_admin ?? false): ?>
    <header class="q-page-header">
      <div>
        <p class="q-page-header__eyebrow">PLATFORM</p>
        <h1 class="q-page-header__title">Platform Ayarları</h1>
        <p class="q-page-header__subtitle">Meta API, SMTP, 2FA, ödeme yöntemleri ve dil ayarları — tüm platform için geçerlidir. İşletmeye özel ayarlar <strong>İşletme → Ayarlar</strong> sayfasındadır.</p>
      </div>
    </header>
    <?php else: ?>
    <header class="q-page-header">
      <div>
        <p class="q-page-header__eyebrow">Ayarlar</p>
        <h1 class="q-page-header__title"><?php echo t('settings.systemSettings'); ?></h1>
      </div>
    </header>
    <?php endif; ?>
    
    <?php if (!$canEdit): ?>
    <div class="q-permission-banner mb-4 sm:mb-5 md:mb-6">
        <p class="text-xs sm:text-sm">⚠️ <?php echo t('settings.noEditPermission'); ?></p>
    </div>
    <?php endif; ?>
    
    <!-- Settings Layout: Sidebar + Content -->
    <div class="q-settings-layout" id="admin-settings-root">
        <nav class="q-settings-nav" role="tablist" aria-label="Ayar sekmeleri">
                <button type="button" onclick="switchTab('general')" id="tab-general" role="tab" aria-selected="true" class="q-settings-nav__btn selected flex items-center gap-2">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6z"></path></svg>
                    <?php echo t('settings.general'); ?>
                </button>
                <button type="button" onclick="switchTab('email')" id="tab-email" role="tab" aria-selected="false" class="q-settings-nav__btn flex items-center gap-2">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                    E-posta
                </button>
                <?php if ($isSuperAdmin): ?>
                <button type="button" onclick="switchTab('meta')" id="tab-meta" role="tab" aria-selected="false" class="q-settings-nav__btn flex items-center gap-2">
                    <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    Meta API
                </button>
                <?php endif; ?>
                <button type="button" onclick="switchTab('ai')" id="tab-ai" role="tab" aria-selected="false" class="q-settings-nav__btn flex items-center gap-2">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path></svg>
                    <?php echo t('settings.ai'); ?>
                </button>
                <button type="button" onclick="switchTab('language')" id="tab-language" role="tab" aria-selected="false" class="q-settings-nav__btn flex items-center gap-2">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"></path></svg>
                    <?php echo t('settings.language'); ?>
                </button>
                <?php if (!$isSuperAdmin): /* İşletme Konumu per-business; platform ayarlar sayfasında gösterilmez */ ?>
                <button type="button" onclick="switchTab('location')" id="tab-location" role="tab" aria-selected="false" class="q-settings-nav__btn flex items-center gap-2">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    İşletme Konumu
                </button>
                <?php endif; ?>
                <button type="button" onclick="switchTab('system')" id="tab-system" role="tab" aria-selected="false" class="q-settings-nav__btn flex items-center gap-2">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    <?php echo t('settings.system'); ?>
                </button>
                <?php if ($isSuperAdmin): ?>
                <button type="button" onclick="switchTab('payment')" id="tab-payment" role="tab" aria-selected="false" class="q-settings-nav__btn flex items-center gap-2">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                    Ödeme Ayarları
                </button>
                <button type="button" onclick="switchTab('auth2fa')" id="tab-auth2fa" role="tab" aria-selected="false" class="q-settings-nav__btn flex items-center gap-2">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0-3.866-3.134-7-7-7m7 7c0 3.866-3.134 7-7 7m7-7h7m-7 0H5"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v6m0-6a3 3 0 100-6 3 3 0 000 6z"></path></svg>
                    2FA / Güvenlik
                </button>
                <?php endif; ?>
                <button type="button" onclick="switchTab('danger')" id="tab-danger" role="tab" aria-selected="false" class="q-settings-nav__btn q-settings-nav__btn--danger flex items-center gap-2">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    <?php echo t('settings.danger'); ?>
                </button>
        </nav>
        
        <!-- Content Area -->
        <div class="flex-1 min-w-0">
    <form id="settings-form" method="POST" action="<?php echo BASE_URL . $adminPrefix; ?>/settings" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        
        <div class="w-full max-w-3xl">
        <!-- Genel Ayarlar Tab -->
        <div id="content-general" class="tab-content">
            <div class="q-card q-card--pad q-stack">
                <h2 class="q-card__title"><?php echo t('settings.generalSettings'); ?></h2>
                <?php if (!empty($isSuperAdmin)): ?>
                <div class="q-callout">
                    <strong>Meta (WhatsApp Cloud API):</strong>
                    Uygulama ID, gizli anahtar, kalıcı token, telefon numarası, Webhook doğrulama ve sıra bildirimi şablon adı
                    <a href="?tab=meta#meta" class="font-bold" style="color:var(--color-brand-accent-hover)">Meta API</a> sekmesinde tanımlanır (platform geneli).
                    <span class="block mt-1 q-hint">Bu sayfadaki Genel bölümü site adı, logo ve dil gibi yönetim arayüzü ayarlarını içerir; işletme bazlı WhatsApp aç/kapa <strong>İşletme → Sıra</strong> üzerinden yapılır.</span>
                </div>
                <?php endif; ?>
                <!-- Site Name -->
                <div>
                    <label class="q-label"><?php echo t('settings.siteName'); ?></label>
                    <input type="text" name="site_name" value="<?php echo htmlspecialchars($siteName); ?>" <?php echo $canEdit ? '' : 'disabled'; ?>
                           class="q-input"/>
                </div>
                
                <!-- Logo Upload -->
                <div>
                    <label class="q-label"><?php echo t('settings.logo'); ?></label>
                    <div class="flex flex-col lg:flex-row gap-4 lg:gap-6 items-start lg:items-center">
                        <div class="w-32 h-32 lg:w-40 lg:h-40 q-upload-zone">
                            <img id="logo-preview" src="<?php echo htmlspecialchars($logoUrl); ?>" alt="Logo" 
                                 class="w-full h-full object-contain" 
                                 onerror="this.onerror=null; this.src='data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCI+PHBhdGggZmlsbD0iIzk5OSIgZD0iTTEyIDJMMiA3djEwYzAgNS41NSAzLjg0IDEwLjc0IDkgMTIgNS4xNi0xLjI2IDktNi40NSA5LTEyVjdsLTEwLTV6Ii8+PC9zdmc+';">
                        </div>
                        <?php if ($canEdit): ?>
                        <div class="flex-1">
                            <input type="file" id="logo-input" name="logo" accept="image/png,image/jpeg,image/jpg,image/gif,image/svg+xml" 
                                   class="hidden" onchange="uploadLogo(this.files[0])">
                            <button type="button" onclick="document.getElementById('logo-input').click()" 
                                    class="q-btn q-btn--ink q-btn--lg">
                                <?php echo t('settings.uploadLogo'); ?>
                            </button>
                            <p class="q-hint text-xs lg:text-sm mt-2">PNG, JPG, GIF veya SVG (Max 5MB)</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Favicon Upload -->
                <div>
                    <label class="q-label"><?php echo t('settings.favicon'); ?></label>
                    <div class="flex flex-col lg:flex-row gap-4 lg:gap-6 items-start lg:items-center">
                        <div class="w-16 h-16 lg:w-20 lg:h-20 q-upload-zone">
                            <img id="favicon-preview" src="<?php echo htmlspecialchars($faviconUrl); ?>" alt="Favicon" 
                                 class="w-full h-full object-contain"
                                 onerror="this.onerror=null; this.src='data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyNCAyNCI+PHBhdGggZmlsbD0iIzk5OSIgZD0iTTEyIDJMMiA3djEwYzAgNS41NSAzLjg0IDEwLjc0IDkgMTIgNS4xNi0xLjI2IDktNi40NSA5LTEyVjdsLTEwLTV6Ii8+PC9zdmc+';">
                        </div>
                        <?php if ($canEdit): ?>
                        <div class="flex-1">
                            <input type="file" id="favicon-input" name="favicon" accept="image/x-icon,image/vnd.microsoft.icon,image/png,image/svg+xml" 
                                   class="hidden" onchange="uploadFavicon(this.files[0])">
                            <button type="button" onclick="document.getElementById('favicon-input').click()" 
                                    class="q-btn q-btn--ink q-btn--lg">
                                <?php echo t('settings.uploadFavicon'); ?>
                            </button>
                            <p class="q-hint text-xs lg:text-sm mt-2">ICO, PNG veya SVG (Max 1MB)</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Finansal Ayarlar Tab -->
        <div id="content-financial" class="tab-content hidden">
            <div class="q-card q-card--pad q-stack">
                <h2 class="q-card__title"><?php echo t('settings.financialSettings'); ?></h2>
                
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 lg:gap-6">
                    <div>
                        <label class="q-label"><?php echo t('settings.serviceChargeRate'); ?></label>
                        <input type="number" name="service_charge_rate" value="<?php echo htmlspecialchars($serviceChargeRate); ?>" step="0.01" min="0" max="100" <?php echo $canEdit ? '' : 'disabled'; ?>
                               class="q-input"/>
                    </div>
                    <div>
                        <label class="q-label"><?php echo t('settings.coverCharge'); ?></label>
                        <input type="number" name="cover_charge" value="<?php echo htmlspecialchars($coverCharge); ?>" step="0.01" min="0" <?php echo $canEdit ? '' : 'disabled'; ?>
                               class="q-input"/>
                    </div>
                    <div>
                        <label class="q-label"><?php echo t('settings.currency'); ?></label>
                        <select name="currency" <?php echo $canEdit ? '' : 'disabled'; ?>
                                class="q-select">
                            <option value="TRY" <?php echo $currency === 'TRY' ? 'selected' : ''; ?>>TRY - Türk Lirası</option>
                            <option value="USD" <?php echo $currency === 'USD' ? 'selected' : ''; ?>>USD - US Dollar</option>
                            <option value="EUR" <?php echo $currency === 'EUR' ? 'selected' : ''; ?>>EUR - Euro</option>
                            <option value="GBP" <?php echo $currency === 'GBP' ? 'selected' : ''; ?>>GBP - British Pound</option>
                        </select>
                    </div>
                </div>
                
                <!-- Sipariş ID Formatı -->
                <div class="border-t pt-6" style="border-color:var(--color-border-1)" lg:pt-10 mt-6 lg:mt-10">
                    <h3 class="text-base sm:text-lg md:text-xl lg:text-2xl font-black tracking-tighter mb-4 lg:mb-6">Sipariş ID Formatı</h3>
                    <p class="q-hint text-xs sm:text-sm mb-4 lg:mb-6">Sipariş numaralarının nasıl oluşturulacağını belirleyin. Örnek: <?php echo htmlspecialchars($orderIdPrefix); ?><?php echo str_pad('1', $orderNumberLength, '0', STR_PAD_LEFT); ?></p>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 lg:gap-6">
                        <div>
                            <label class="q-label">Sipariş ID Öneki</label>
                            <input type="text" name="order_id_prefix" value="<?php echo htmlspecialchars($orderIdPrefix); ?>" maxlength="10" <?php echo $canEdit ? '' : 'disabled'; ?>
                                   placeholder="cd" 
                                   class="q-input"/>
                            <p class="q-hint text-xs mt-2">Örnek: cd, ord, sip gibi</p>
                        </div>
                        <div>
                            <label class="q-label">Numara Uzunluğu</label>
                            <input type="number" name="order_number_length" value="<?php echo $orderNumberLength; ?>" min="1" max="10" <?php echo $canEdit ? '' : 'disabled'; ?>
                                   class="q-input"/>
                            <p class="q-hint text-xs mt-2">Kaç haneli numara olacak (1-10 arası)</p>
                        </div>
                    </div>
                    
                    <!-- Order Edit Approval Settings -->
                    <div class="mt-6 lg:mt-10 pt-6 lg:pt-10 border-t" style="border-color:var(--color-border-1)">
                        <h3 class="text-base sm:text-lg md:text-xl lg:text-2xl font-black mb-4 lg:mb-6"><?php echo t('settings.orderEditApproval', 'Sipariş Düzenleme Onayı'); ?></h3>
                        
                        <div class="space-y-4 lg:space-y-6">
                            <div>
                                <label class="q-toggle-row">
                                    <input type="checkbox" name="order_edit_requires_approval" value="1" 
                                           <?php echo $orderEditRequiresApproval ? 'checked' : ''; ?> 
                                           <?php echo $canEdit ? '' : 'disabled'; ?>
                                           class="w-5 h-5 lg:w-6 lg:h-6" style="accent-color:var(--color-brand-accent)"/>
                                    <span class="q-toggle-row__title">
                                        <?php echo t('settings.orderEditRequiresApproval', 'Sipariş düzenlemeleri için onay gereksin'); ?>
                                    </span>
                                </label>
                                <p class="q-hint text-xs mt-2 ml-12"><?php echo t('settings.orderEditRequiresApprovalDesc', 'Aktif edildiğinde, kasiyerler sipariş düzenlemesi yaparken onay almak zorundadır.'); ?></p>
                            </div>
                            
                            <div>
                                <label class="q-label"><?php echo t('settings.approvalRole', 'Onay Veren Rol'); ?></label>
                                <select name="order_edit_approval_role" <?php echo $canEdit ? '' : 'disabled'; ?>
                                        class="q-input">
                                    <?php foreach ($allRoles as $role): 
                                        $roleCode = strtoupper($role['role_code'] ?? '');
                                        $roleName = $role['role_name'] ?? $roleCode;
                                        $selected = ($orderEditApprovalRole === $roleCode || $orderEditApprovalRole === 'ROLE_' . $roleCode) ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo htmlspecialchars($roleCode); ?>" <?php echo $selected; ?>>
                                            <?php echo htmlspecialchars($roleName); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="q-hint text-xs mt-2"><?php echo t('settings.approvalRoleDesc', 'Sipariş düzenlemelerini onaylayabilecek rolü seçin.'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- AI Ayarları Tab -->
        <div id="content-ai" class="tab-content hidden">
            <div class="q-card q-card--pad q-stack">
                <h2 class="q-card__title"><?php echo t('settings.aiSettings'); ?></h2>
                <p class="q-hint text-sm lg:text-base">Google Gemini AI API ayarlarını yapılandırın.</p>
                
                <div class="space-y-6 lg:space-y-8">
                    <div>
                        <label class="q-label">Gemini API Key</label>
                        <div class="relative">
                            <input type="password" id="gemini-api-key" name="gemini_api_key" value="<?php echo htmlspecialchars($settings['gemini_api_key'] ?? ''); ?>" placeholder="AIza..." <?php echo $canEdit ? '' : 'disabled'; ?>
                                   class="q-input pr-12 lg:pr-16"/>
                            <?php if ($canEdit): ?>
                            <button type="button" onclick="toggleGeminiApiKeyVisibility()" class="q-icon-btn absolute right-2 top-1/2 -translate-y-1/2">
                                <svg id="gemini-eye-icon" class="w-5 h-5 lg:w-6 lg:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                                <svg id="gemini-eye-off-icon" class="w-5 h-5 lg:w-6 lg:h-6 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.487 5.236m0 0L21 21"></path>
                                </svg>
                            </button>
                            <?php endif; ?>
                        </div>
                        <p class="q-hint text-xs mt-2">Google Gemini API anahtarı. <a href="https://makersuite.google.com/app/apikey" target="_blank" class="underline" style="color:var(--color-brand-accent-hover)">API anahtarı alın</a></p>
                    </div>
                    
                    <div class="bg-blue-50 border border-blue-200 rounded-2xl p-4">
                        <p class="text-sm lg:text-base text-blue-800">
                            <strong>Not:</strong> Gemini AI özellikleri yalnızca Dashboard'da kullanılabilir. Diğer alanlar için AI desteği kaldırılmıştır.
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Dil Ayarları Tab -->
        <div id="content-language" class="tab-content hidden">
            <div class="q-card q-card--pad q-stack">
                <h2 class="q-card__title"><?php echo t('settings.languageSettings'); ?></h2>
                
                <!-- Varsayılan Dil -->
                <div>
                        <label class="q-label"><?php echo t('settings.defaultLanguage'); ?></label>
                    <select name="default_language" <?php echo $canEdit ? '' : 'disabled'; ?>
                            class="q-select">
                        <option value="tr" <?php echo $defaultLanguage === 'tr' ? 'selected' : ''; ?>>🇹🇷 <?php echo t('settings.turkish'); ?></option>
                        <option value="en" <?php echo $defaultLanguage === 'en' ? 'selected' : ''; ?>>🇺🇸 <?php echo t('settings.english'); ?> (<?php echo t('settings.englishCode'); ?>)</option>
                    </select>
                    <p class="q-hint text-xs lg:text-sm mt-2"><?php echo t('settings.newDefault'); ?></p>
                </div>
                
                <!-- Desteklenen Diller -->
                <div>
                        <label class="q-label"><?php echo t('settings.supportedLanguages'); ?></label>
                    <div class="space-y-3">
                        <label class="q-toggle-row">
                            <input type="checkbox" name="supported_languages[]" value="tr" <?php echo in_array('tr', $supportedLanguages) ? 'checked' : ''; ?> <?php echo $canEdit ? '' : 'disabled'; ?>
                                   class="w-5 h-5" style="accent-color:var(--color-brand-accent)">
                            <div class="flex-1">
                                <div class="font-black text-lg">🇹🇷 <?php echo t('settings.turkish'); ?></div>
                                <div class="q-toggle-row__hint">Turkish</div>
                            </div>
                            <?php if ($defaultLanguage === 'tr'): ?>
                            <span class="q-badge q-badge--neutral">Varsayılan</span>
                            <?php endif; ?>
                        </label>
                        <label class="q-toggle-row">
                            <input type="checkbox" name="supported_languages[]" value="en" <?php echo in_array('en', $supportedLanguages) ? 'checked' : ''; ?> <?php echo $canEdit ? '' : 'disabled'; ?>
                                   class="w-5 h-5" style="accent-color:var(--color-brand-accent)">
                            <div class="flex-1">
                                <div class="font-black text-lg">🇺🇸 <?php echo t('settings.english'); ?> (<?php echo t('settings.englishCode'); ?>)</div>
                                <div class="q-toggle-row__hint"><?php echo t('settings.english'); ?></div>
                            </div>
                            <?php if ($defaultLanguage === 'en'): ?>
                            <span class="q-badge q-badge--neutral">Varsayılan</span>
                            <?php endif; ?>
                        </label>
                    </div>
                    <p class="q-hint text-xs lg:text-sm mt-2"><?php echo t('settings.selectLanguages'); ?></p>
                </div>
                
                <!-- Dil Değiştirme Ayarları -->
                <div class="space-y-4">
                    <h3 class="text-lg lg:text-xl font-black tracking-tighter">Dil Değiştirme Ayarları</h3>
                    
                    <label class="q-toggle-row">
                        <input type="checkbox" name="language_switcher_enabled" value="1" <?php echo $languageSwitcherEnabled ? 'checked' : ''; ?> <?php echo $canEdit ? '' : 'disabled'; ?>
                               class="w-5 h-5" style="accent-color:var(--color-brand-accent)">
                        <div class="flex-1">
                            <div class="font-black text-base"><?php echo t('settings.languageSwitcherEnabled'); ?></div>
                            <div class="q-toggle-row__hint"><?php echo t('settings.allowLanguageSwitch'); ?></div>
                        </div>
                    </label>
                    
                    <label class="q-toggle-row">
                        <input type="checkbox" name="auto_detect_language" value="1" <?php echo $autoDetectLanguage ? 'checked' : ''; ?> <?php echo $canEdit ? '' : 'disabled'; ?>
                               class="w-5 h-5" style="accent-color:var(--color-brand-accent)">
                        <div class="flex-1">
                            <div class="font-black text-base"><?php echo t('settings.autoDetectLanguage'); ?></div>
                            <div class="q-toggle-row__hint"><?php echo t('settings.autoDetectBrowserLang'); ?></div>
                        </div>
                    </label>
                </div>
                
                <!-- Dil İstatistikleri -->
                <div class="q-card q-card--pad q-stack mt-6">
                    <h4 class="font-black text-sm mb-3"><?php echo t('settings.languageUsageStats'); ?></h4>
                    <?php 
                    $languageStats = $languageStats ?? [
                        'total' => 0,
                        'tr_count' => 0,
                        'en_count' => 0,
                        'both_count' => 0,
                        'tr_only_count' => 0,
                        'en_only_count' => 0
                    ];
                    $trCount = $languageStats['tr_count'] ?? 0;
                    $enCount = $languageStats['en_count'] ?? 0;
                    $totalCount = $languageStats['total'] ?? 0;
                    $trPercentage = $totalCount > 0 ? round(($trCount / $totalCount) * 100) : 0;
                    $enPercentage = $totalCount > 0 ? round(($enCount / $totalCount) * 100) : 0;
                    ?>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="text-center p-3 bg-white rounded-lg">
                            <div class="q-card__title">🇹🇷</div>
                            <div class="q-hint text-xs mt-1"><?php echo t('buttons.turkish'); ?></div>
                            <div class="text-lg font-black mt-1 text-slate-900"><?php echo number_format($trCount); ?></div>
                            <div class="text-xs text-slate-500 mt-0.5"><?php echo $trPercentage; ?>%</div>
                        </div>
                        <div class="text-center p-3 bg-white rounded-lg">
                            <div class="q-card__title">🇺🇸</div>
                            <div class="q-hint text-xs mt-1"><?php echo t('buttons.english'); ?> (EN)</div>
                            <div class="text-lg font-black mt-1 text-slate-900"><?php echo number_format($enCount); ?></div>
                            <div class="text-xs text-slate-500 mt-0.5"><?php echo $enPercentage; ?>%</div>
                        </div>
                    </div>
                    <div class="mt-3 pt-3 border-t text-center" style="border-color:var(--color-border-1)">
                        <div class="q-hint text-xs">
                            <span class="font-bold">Toplam:</span> <?php echo number_format($totalCount); ?> çeviri
                            <?php if ($languageStats['both_count'] > 0): ?>
                                | <span class="text-green-600 font-bold">Her ikisi:</span> <?php echo number_format($languageStats['both_count']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- WiFi Bilgileri Tab -->
        <div id="content-wifi" class="tab-content hidden">
            <div class="q-card q-card--pad q-stack">
                <h2 class="q-card__title">WiFi Bilgileri</h2>
                <p class="q-hint text-sm">Müşterilerinizin bağlanabileceği WiFi ağ bilgilerini buradan ayarlayabilirsiniz. Bu bilgiler müşteri ekranında görüntülenecektir.</p>
                
                <div class="space-y-4 lg:space-y-6">
                    <div>
                        <label class="q-label">WiFi Ağ Adı (SSID)</label>
                        <input type="text" name="wifi_name" value="<?php echo htmlspecialchars($wifiName); ?>" <?php echo $canEdit ? '' : 'disabled'; ?>
                               class="q-input" placeholder="WiFi Ağ Adı"/>
                        <p class="q-hint text-xs mt-2">Müşterilerinizin bağlanacağı WiFi ağının adı</p>
                    </div>
                    
                    <div>
                        <label class="q-label">WiFi Şifresi</label>
                        <div class="relative">
                            <input type="password" id="wifi-password-input" name="wifi_password" value="<?php echo htmlspecialchars($wifiPassword); ?>" <?php echo $canEdit ? '' : 'disabled'; ?>
                                   class="q-input pr-12 lg:pr-16" placeholder="WiFi Şifresi"/>
                            <button type="button" onclick="aria-label="WiFi şifresini göster/gizle" onclick="toggleWifiPasswordVisibility()"" class="q-icon-btn absolute right-2 top-1/2 -translate-y-1/2">
                                <svg id="wifi-eye-icon" class="w-5 h-5 lg:w-6 lg:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                                <svg id="wifi-eye-off-icon" class="w-5 h-5 lg:w-6 lg:h-6 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.487 5.236m0 0L21 21"></path>
                                </svg>
                            </button>
                        </div>
                        <p class="q-hint text-xs mt-2">WiFi ağına bağlanmak için gerekli şifre</p>
                    </div>
                    
                    <!-- Müşteriye Göster Toggle -->
                    <div class="q-toggle-row items-center justify-between p-4 lg:p-5">
                        <div class="flex-1">
                            <label class="q-toggle-row__title block mb-1">Müşteriye Göster</label>
                            <p class="q-hint text-xs lg:text-sm">Bu seçenek aktif olduğunda, müşteri ekranında WiFi bilgileri butonu görüntülenecektir.</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer ml-4">
                            <input type="checkbox" name="wifi_show_to_customer" value="1" <?php echo $wifiShowToCustomer ? 'checked' : ''; ?> <?php echo $canEdit ? '' : 'disabled'; ?>
                                   class="sr-only peer">
                            <div class="w-11 h-6 bg-slate-300 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-100 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-500"></div>
                        </label>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- İşletme Konumu Tab -->
        <div id="content-location" class="tab-content hidden">
            <div class="q-card q-card--pad q-stack">
                <h2 class="q-card__title">İşletme Konumu</h2>
                <p class="q-hint text-sm">İşletmenizin konumunu belirleyin. Bu konum, müşterilerin QR kodu sadece işletme yakınında kullanabilmesi için kullanılır. Uzaktaki kişiler menüye erişemez.</p>
                
                <div class="bg-indigo-50 border border-indigo-100 rounded-2xl p-4 lg:p-6">
                    <div class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-indigo-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <div>
                            <p class="text-sm font-bold text-indigo-800 mb-1">Nasıl çalışır?</p>
                            <p class="text-xs text-indigo-700 leading-relaxed">Konumunuzu belirledikten sonra, "Müşteri Konum Takibi" özelliğini <strong>Özellik Yönetimi</strong> sayfasından aktif edin. Aktif olduğunda müşterilerden konum izni istenir ve belirlenen yarıçap dışındaki kişiler menüye erişemez.</p>
                        </div>
                    </div>
                </div>
                
                <div>
                    <label class="q-label">İşletme Adresi</label>
                    <input type="text" name="business_address" id="business-address" value="<?php echo htmlspecialchars($businessAddress); ?>" <?php echo $canEdit ? '' : 'disabled'; ?>
                           class="q-input" placeholder="Örn: Bağdat Caddesi No:123, Kadıköy/İstanbul"/>
                    <p class="q-hint text-xs mt-2">İşletmenizin tam adresi (opsiyonel, referans amaçlı)</p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 lg:gap-6">
                    <div>
                        <label class="q-label">Enlem (Latitude)</label>
                        <input type="text" name="business_latitude" id="business-latitude" value="<?php echo htmlspecialchars($businessLatitude); ?>" <?php echo $canEdit ? '' : 'disabled'; ?>
                               class="q-input" placeholder="Örn: 41.0082"/>
                    </div>
                    <div>
                        <label class="q-label">Boylam (Longitude)</label>
                        <input type="text" name="business_longitude" id="business-longitude" value="<?php echo htmlspecialchars($businessLongitude); ?>" <?php echo $canEdit ? '' : 'disabled'; ?>
                               class="q-input" placeholder="Örn: 28.9784"/>
                    </div>
                </div>
                
                <?php if ($canEdit): ?>
                <button type="button" onclick="detectCurrentLocation()" class="q-btn q-btn--primary q-btn--block flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Mevcut Konumumu Kullan
                </button>
                <p class="text-xs text-slate-400 text-center -mt-4">Tarayıcınızdan konum izni isteyerek otomatik doldurur</p>
                <?php endif; ?>
                
                <div>
                    <label class="q-label">Erişim Yarıçapı (metre)</label>
                    <input type="number" name="business_radius" id="business-radius" value="<?php echo $businessRadius; ?>" min="50" max="5000" step="50" <?php echo $canEdit ? '' : 'disabled'; ?>
                           class="q-input"/>
                    <p class="q-hint text-xs mt-2">İşletmeden bu mesafe (metre) dışındaki müşteriler menüye erişemez. Önerilen: 100-500 metre. GPS hassasiyetine göre ayarlayın.</p>
                    
                    <div class="mt-4 flex flex-wrap gap-2">
                        <button type="button" onclick="document.getElementById('business-radius').value=100" class="px-3 py-1 text-xs font-bold bg-slate-100 hover:bg-slate-200 rounded-lg transition-all">100m</button>
                        <button type="button" onclick="document.getElementById('business-radius').value=200" class="px-3 py-1 text-xs font-bold bg-slate-100 hover:bg-slate-200 rounded-lg transition-all">200m</button>
                        <button type="button" onclick="document.getElementById('business-radius').value=500" class="px-3 py-1 text-xs font-bold bg-slate-100 hover:bg-slate-200 rounded-lg transition-all">500m</button>
                        <button type="button" onclick="document.getElementById('business-radius').value=1000" class="px-3 py-1 text-xs font-bold bg-slate-100 hover:bg-slate-200 rounded-lg transition-all">1km</button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sistem Ayarları Tab -->
        <div id="content-system" class="tab-content hidden">
            <div class="q-card q-card--pad q-stack">
                <h2 class="q-card__title"><?php echo t('settings.systemSettings'); ?></h2>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 lg:gap-6">
                    <div>
                        <label class="q-label"><?php echo t('settings.environment'); ?></label>
                        <select name="app_env" <?php echo $canEdit ? '' : 'disabled'; ?>
                                class="q-select">
                            <option value="development" <?php echo $appEnv === 'development' ? 'selected' : ''; ?>>Development</option>
                            <option value="production" <?php echo $appEnv === 'production' ? 'selected' : ''; ?>>Production</option>
                            <option value="staging" <?php echo $appEnv === 'staging' ? 'selected' : ''; ?>>Staging</option>
                        </select>
                    </div>
                    <div>
                        <label class="q-label"><?php echo t('settings.debugMode'); ?></label>
                        <select name="app_debug" <?php echo $canEdit ? '' : 'disabled'; ?>
                                class="q-select">
                            <option value="false" <?php echo !$appDebug ? 'selected' : ''; ?>>Kapalı</option>
                            <option value="true" <?php echo $appDebug ? 'selected' : ''; ?>>Açık</option>
                        </select>
                    </div>
                    <div>
                        <label class="q-label"><?php echo t('settings.timezone'); ?></label>
                        <select name="timezone" <?php echo $canEdit ? '' : 'disabled'; ?>
                                class="q-select">
                            <option value="Europe/Istanbul" <?php echo $timezone === 'Europe/Istanbul' ? 'selected' : ''; ?>>Europe/Istanbul (Türkiye)</option>
                            <option value="UTC" <?php echo $timezone === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                            <option value="America/New_York" <?php echo $timezone === 'America/New_York' ? 'selected' : ''; ?>>America/New_York</option>
                            <option value="Europe/London" <?php echo $timezone === 'Europe/London' ? 'selected' : ''; ?>>Europe/London</option>
                        </select>
                    </div>
                    <div>
                        <label class="q-label"><?php echo t('settings.sessionTimeout'); ?></label>
                        <input type="number" name="session_timeout" value="<?php echo htmlspecialchars($sessionTimeout); ?>" min="5" max="1440" <?php echo $canEdit ? '' : 'disabled'; ?>
                               class="q-input"/>
                    </div>
                    <div>
                        <label class="q-label"><?php echo t('settings.maxUploadSize'); ?></label>
                        <input type="number" name="max_upload_size" value="<?php echo htmlspecialchars($maxUploadSize); ?>" min="1" max="100" <?php echo $canEdit ? '' : 'disabled'; ?>
                               class="q-input"/>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- E-posta Ayarları Tab -->
        <div id="content-email" class="tab-content hidden">
            <div class="q-card q-card--pad q-stack">
                <h2 class="q-card__title">E-posta Ayarları</h2>
                <p class="q-hint text-sm lg:text-base">SMTP sunucu ayarlarını yapılandırarak e-posta gönderimini etkinleştirin.</p>
                
                <div class="space-y-8 lg:space-y-10">
                    <!-- SMTP Configuration Section -->
                    <div class="border-t" style="border-color:var(--color-border-1) pt-6 lg:pt-8">
                        <h3 class="text-base sm:text-lg md:text-xl lg:text-2xl font-black tracking-tighter mb-4 lg:mb-6">SMTP Yapılandırması</h3>
                        
                        <div class="space-y-4 lg:space-y-6">
                            <div>
                                <label class="q-label">SMTP Sunucu</label>
                                <input type="text" name="smtp_host" value="<?php echo htmlspecialchars($settings['smtp_host'] ?? ''); ?>" placeholder="smtp.gmail.com" <?php echo $canEdit ? '' : 'disabled'; ?>
                                       class="q-input"/>
                                <p class="q-hint text-xs mt-2">SMTP sunucu adresi (örn: smtp.gmail.com, smtp.yandex.com)</p>
                            </div>
                            
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 lg:gap-6">
                                <div>
                                    <label class="q-label">SMTP Port</label>
                                    <input type="number" name="smtp_port" value="<?php echo htmlspecialchars($settings['smtp_port'] ?? '587'); ?>" min="1" max="65535" <?php echo $canEdit ? '' : 'disabled'; ?>
                                           class="q-input"/>
                                    <p class="q-hint text-xs mt-2">Genellikle 587 (TLS) veya 465 (SSL)</p>
                                </div>
                                <div>
                                    <label class="q-label">Şifreleme</label>
                                    <select name="smtp_encryption" <?php echo $canEdit ? '' : 'disabled'; ?>
                                            class="q-select">
                                        <option value="tls" <?php echo ($settings['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                        <option value="ssl" <?php echo ($settings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                        <option value="none" <?php echo ($settings['smtp_encryption'] ?? '') === 'none' ? 'selected' : ''; ?>>Yok</option>
                                    </select>
                                    <p class="q-hint text-xs mt-2">Bağlantı şifreleme türü</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Authentication Section -->
                    <div class="border-t" style="border-color:var(--color-border-1) pt-6 lg:pt-8">
                        <h3 class="text-base sm:text-lg md:text-xl lg:text-2xl font-black tracking-tighter mb-4 lg:mb-6">Kimlik Doğrulama</h3>
                        
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 lg:gap-6">
                            <div>
                                <label class="q-label">E-posta Adresi</label>
                                <input type="email" name="smtp_username" value="<?php echo htmlspecialchars($settings['smtp_username'] ?? ''); ?>" placeholder="ornek@email.com" <?php echo $canEdit ? '' : 'disabled'; ?>
                                       class="q-input"/>
                                <p class="q-hint text-xs mt-2">SMTP giriş için kullanılacak e-posta adresi</p>
                            </div>
                            <div>
                                <label class="q-label">E-posta Şifresi</label>
                                <div class="relative">
                                    <input type="password" id="smtp-password" name="smtp_password" value="<?php echo htmlspecialchars($settings['smtp_password'] ?? ''); ?>" placeholder="••••••••" <?php echo $canEdit ? '' : 'disabled'; ?>
                                           class="q-input pr-12 lg:pr-16"/>
                                    <?php if ($canEdit): ?>
                                    <button type="button" onclick="toggleSmtpPasswordVisibility()" class="q-icon-btn absolute right-2 top-1/2 -translate-y-1/2">
                                        <svg id="smtp-eye-icon" class="w-5 h-5 lg:w-6 lg:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                        <svg id="smtp-eye-off-icon" class="w-5 h-5 lg:w-6 lg:h-6 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.487 5.236m0 0L21 21"></path>
                                        </svg>
                                    </button>
                                    <?php endif; ?>
                                </div>
                                <p class="q-hint text-xs mt-2">SMTP giriş şifresi veya uygulama şifresi</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sender Information Section -->
                    <div class="border-t" style="border-color:var(--color-border-1) pt-6 lg:pt-8">
                        <h3 class="text-base sm:text-lg md:text-xl lg:text-2xl font-black tracking-tighter mb-4 lg:mb-6">Gönderen Bilgileri</h3>
                        
                        <div>
                            <label class="q-label">Gönderen Adı</label>
                            <input type="text" name="smtp_from_name" value="<?php echo htmlspecialchars($settings['smtp_from_name'] ?? $siteName); ?>" placeholder="Site Adı" <?php echo $canEdit ? '' : 'disabled'; ?>
                                   class="q-input"/>
                            <p class="q-hint text-xs mt-2">E-postalarda görünecek gönderen adı</p>
                        </div>
                    </div>
                    
                    <!-- Test Email -->
                    <?php if ($canEdit): ?>
                    <div class="border-t" style="border-color:var(--color-border-1) pt-6 lg:pt-8">
                        <h3 class="text-base sm:text-lg md:text-xl lg:text-2xl font-black tracking-tighter mb-4 lg:mb-6">E-posta Testi</h3>
                        <div class="flex flex-col sm:flex-row gap-4 items-start">
                            <input type="email" id="test-email-address" placeholder="test@example.com" 
                                   class="flex-1 min-w-0 p-3 sm:p-4 bg-slate-50 rounded-xl font-bold text-sm outline-none border-2 border-transparent focus:border-indigo-100 transition-all"/>
                            <button type="button" onclick="sendTestEmail()" class="px-6 py-3 bg-indigo-500 hover:bg-indigo-700 text-white rounded-xl font-black text-sm transition-all whitespace-nowrap">
                                Test E-postası Gönder
                            </button>
                        </div>
                        <p class="q-hint text-xs mt-2">SMTP ayarlarınızı test etmek için bir e-posta adresi girin</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Meta API Tab (yalnızca süper admin) -->
        <?php if ($isSuperAdmin): ?>
        <div id="content-meta" class="tab-content hidden">
            <!-- Sub-navigation for Meta tab -->
            <div class="flex gap-1 mb-4 bg-slate-100 rounded-xl p-1">
                <button type="button" onclick="switchMetaSubTab('dashboard')" id="meta-subtab-dashboard" class="flex-1 px-3 py-2 rounded-lg text-xs font-bold transition-all bg-white text-slate-900 shadow-sm">Dashboard</button>
                <button type="button" onclick="switchMetaSubTab('settings')" id="meta-subtab-settings" class="flex-1 px-3 py-2 rounded-lg text-xs font-bold transition-all text-slate-500 hover:text-slate-700">Ayarlar</button>
                <button type="button" onclick="switchMetaSubTab('history')" id="meta-subtab-history" class="flex-1 px-3 py-2 rounded-lg text-xs font-bold transition-all text-slate-500 hover:text-slate-700">Mesaj Ge&ccedil;mi&scedil;i</button>
            </div>

            <!-- DASHBOARD SUB-TAB -->
            <div id="meta-sub-dashboard" class="meta-sub-content space-y-4">
                <!-- Canlı Meta Business API Bilgileri (API'den anlık çekilir) -->
                <div class="bg-gradient-to-br from-emerald-50 via-white to-emerald-50 rounded-xl border border-emerald-200 shadow-sm p-4 sm:p-5">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-emerald-600" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 2.1.55 4.15 1.6 5.96L2 22l4.24-1.11a9.9 9.9 0 004.77 1.21h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01C17.18 3.03 14.69 2 12.04 2zm5.83 14.19c-.25.7-1.46 1.34-2.01 1.42-.52.08-1.17.11-1.89-.12-.43-.14-1-.32-1.72-.64-3.02-1.31-4.99-4.36-5.14-4.56-.15-.2-1.22-1.62-1.22-3.09s.77-2.19 1.04-2.49c.27-.3.59-.37.79-.37.2 0 .4 0 .57.01.18 0 .43-.07.67.51.25.6.85 2.07.92 2.22.08.15.13.33.03.53-.1.2-.15.32-.3.5-.15.17-.32.39-.45.52-.15.15-.31.31-.13.61.17.3.77 1.27 1.66 2.06 1.14 1.01 2.1 1.32 2.4 1.47.3.15.47.13.65-.08.17-.2.75-.87.95-1.17.2-.3.4-.25.67-.15.28.1 1.74.82 2.04.97.3.15.5.22.57.34.08.12.08.72-.17 1.42z"/></svg>
                            <h3 class="text-sm font-bold text-slate-800">Canl&#305; Meta Business API Bilgileri</h3>
                        </div>
                        <button type="button" onclick="loadMetaBusinessInfo()" class="text-[11px] text-emerald-700 hover:text-emerald-800 font-bold">&#x21bb; Yenile</button>
                    </div>
                    <div id="meta-business-info-loading" class="text-xs text-slate-500">Y&uuml;kleniyor&hellip;</div>
                    <div id="meta-business-info-error" class="hidden text-xs text-red-600 font-semibold"></div>
                    <div id="meta-business-info-content" class="hidden grid grid-cols-1 lg:grid-cols-3 gap-3">
                        <!-- PHONE -->
                        <div class="bg-white rounded-lg border border-slate-200 p-3 space-y-1.5">
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Telefon Numaras&#305;</p>
                            <p id="mbi-phone-number" class="text-lg font-black text-slate-900">&mdash;</p>
                            <p id="mbi-phone-verified" class="text-[11px] font-semibold text-slate-600">&mdash;</p>
                            <div class="flex flex-wrap gap-1.5 pt-1">
                                <span id="mbi-phone-quality" class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-slate-100 text-slate-600 text-[10px] font-bold"></span>
                                <span id="mbi-phone-verification" class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-slate-100 text-slate-600 text-[10px] font-bold"></span>
                                <span id="mbi-phone-throughput" class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-slate-100 text-slate-600 text-[10px] font-bold"></span>
                            </div>
                        </div>
                        <!-- WABA -->
                        <div class="bg-white rounded-lg border border-slate-200 p-3 space-y-1.5">
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">WhatsApp Business Account</p>
                            <p id="mbi-waba-name" class="text-lg font-black text-slate-900">&mdash;</p>
                            <p id="mbi-waba-owner" class="text-[11px] font-semibold text-slate-600">&mdash;</p>
                            <div class="flex flex-wrap gap-1.5 pt-1">
                                <span id="mbi-waba-verification" class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-slate-100 text-slate-600 text-[10px] font-bold"></span>
                                <span id="mbi-waba-currency" class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-slate-100 text-slate-600 text-[10px] font-bold"></span>
                                <span id="mbi-waba-tz" class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-slate-100 text-slate-600 text-[10px] font-bold"></span>
                            </div>
                        </div>
                        <!-- TEMPLATES -->
                        <div class="bg-white rounded-lg border border-slate-200 p-3 space-y-1.5">
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Onayl&#305; &Scedil;ablonlar</p>
                            <p id="mbi-tpl-count" class="text-lg font-black text-slate-900">0</p>
                            <p class="text-[11px] font-semibold text-slate-500">&Ccedil;al&#305;&scedil;an Meta mesaj &scedil;ablonlar&#305;</p>
                            <div id="mbi-tpl-list" class="pt-1 flex flex-wrap gap-1 max-h-24 overflow-y-auto"></div>
                        </div>
                    </div>
                </div>

                <!-- Top stat cards row -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <!-- Token Status Card -->
                    <div id="meta-card-token" class="bg-white rounded-xl border border-slate-200 p-4 cursor-pointer hover:shadow-md transition-all" onclick="checkMetaToken()">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Token</span>
                            <span id="meta-token-dot" class="w-2.5 h-2.5 rounded-full bg-slate-300 animate-pulse"></span>
                        </div>
                        <p id="meta-token-label" class="text-sm font-black text-slate-900">Kontrol Et</p>
                        <p id="meta-token-sub" class="text-[10px] text-slate-400 mt-0.5 truncate">Dokunun</p>
                    </div>

                    <!-- Daily Messages Card -->
                    <div class="bg-white rounded-xl border border-slate-200 p-4">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Bug&uuml;n</span>
                            <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                        </div>
                        <p id="meta-daily-count" class="q-card__title">-</p>
                        <p id="meta-daily-sub" class="text-[10px] text-slate-400 mt-0.5">y&uuml;kleniyor...</p>
                    </div>

                    <!-- Daily Limit Card -->
                    <div class="bg-white rounded-xl border border-slate-200 p-4">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">G&uuml;nl&uuml;k Limit</span>
                            <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        </div>
                        <p id="meta-daily-remaining" class="q-card__title">-</p>
                        <div class="mt-1.5">
                            <div class="w-full h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                <div id="meta-daily-bar" class="h-full bg-blue-500 rounded-full transition-all duration-700" style="width:0%"></div>
                            </div>
                            <p id="meta-daily-bar-label" class="text-[10px] text-slate-400 mt-0.5">0 / 0</p>
                        </div>
                    </div>

                    <!-- Monthly Limit Card -->
                    <div class="bg-white rounded-xl border border-slate-200 p-4">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Ayl&#305;k Limit</span>
                            <svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        </div>
                        <p id="meta-monthly-remaining" class="q-card__title">-</p>
                        <div class="mt-1.5">
                            <div class="w-full h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                <div id="meta-monthly-bar" class="h-full bg-purple-500 rounded-full transition-all duration-700" style="width:0%"></div>
                            </div>
                            <p id="meta-monthly-bar-label" class="text-[10px] text-slate-400 mt-0.5">0 / 0</p>
                        </div>
                    </div>
                </div>

                <!-- Second row: Success Rate + Response Time + Breakdown -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <div class="bg-white rounded-xl border border-slate-200 p-4">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Ba&scedil;ar&#305; Oran&#305;</span>
                        <p id="meta-success-rate" class="text-2xl font-black text-green-600 mt-1">-%</p>
                        <p id="meta-success-sub" class="text-[10px] text-slate-400 mt-0.5">son 30 g&uuml;n</p>
                    </div>
                    <div class="bg-white rounded-xl border border-slate-200 p-4">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Ort. Yan&#305;t</span>
                        <p id="meta-avg-response" class="q-card__title mt-1">- ms</p>
                        <p id="meta-response-sub" class="text-[10px] text-slate-400 mt-0.5">API yan&#305;t s&uuml;resi</p>
                    </div>
                    <div class="bg-white rounded-xl border border-slate-200 p-4">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Ba&scedil;ar&#305;s&#305;z</span>
                        <p id="meta-failed-today" class="text-2xl font-black text-red-500 mt-1">-</p>
                        <p class="text-[10px] text-slate-400 mt-0.5">bug&uuml;n</p>
                    </div>
                    <div class="bg-white rounded-xl border border-slate-200 p-4">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Toplam</span>
                        <p id="meta-total-alltime" class="q-card__title mt-1">-</p>
                        <p class="text-[10px] text-slate-400 mt-0.5">t&uuml;m zamanlar</p>
                    </div>
                </div>

                <!-- Message type breakdown -->
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-bold text-slate-700">Bug&uuml;nk&uuml; Da&gbreve;&#305;l&#305;m</h3>
                        <span id="meta-last-refresh" class="text-[10px] text-slate-400"></span>
                    </div>
                    <div id="meta-type-breakdown" class="flex flex-wrap gap-2">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-blue-50 text-blue-700 text-xs font-bold"><span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>OTP: <span id="meta-type-otp">0</span></span>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-green-50 text-green-700 text-xs font-bold"><span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>Test: <span id="meta-type-test">0</span></span>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-purple-50 text-purple-700 text-xs font-bold"><span class="w-1.5 h-1.5 rounded-full bg-purple-500"></span>&Scedil;ablon: <span id="meta-type-template">0</span></span>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-700 text-xs font-bold"><span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>Metin: <span id="meta-type-text">0</span></span>
                    </div>
                </div>

                <!-- Weekly Chart -->
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <h3 class="text-sm font-bold text-slate-700 mb-3">Haftal&#305;k G&ouml;nderim Grafi&gbreve;i</h3>
                    <div id="meta-weekly-chart" class="h-32 flex items-end gap-1"></div>
                </div>

                <!-- Hourly Distribution -->
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <h3 class="text-sm font-bold text-slate-700 mb-3">Saatlik Da&gbreve;&#305;l&#305;m (Bug&uuml;n)</h3>
                    <div id="meta-hourly-chart" class="h-20 flex items-end gap-px"></div>
                </div>

                <!-- Auto-refresh indicator -->
                <div class="flex items-center justify-between">
                    <p class="text-[10px] text-slate-400">Veriler her 30 saniyede otomatik yenilenir</p>
                    <button type="button" onclick="loadMetaDashboard()" class="text-[10px] text-indigo-600 hover:text-indigo-600 font-bold">&#x21bb; Yenile</button>
                </div>
            </div>

            <!-- SETTINGS SUB-TAB -->
            <div id="meta-sub-settings" class="meta-sub-content hidden space-y-5">
                <div class="q-card q-card--pad space-y-5">
                    <div>
                        <h2 class="q-card__title">WhatsApp Business API (Meta)</h2>
                        <p class="text-sm text-slate-500 mt-1">WhatsApp mesajlar&#305; i&ccedil;in Meta Cloud API. <a href="https://developers.facebook.com/docs/whatsapp/cloud-api" target="_blank" rel="noopener" class="text-indigo-600 hover:text-indigo-600">Meta Docs</a></p>
                    </div>
                    
                    <!-- Token Durum Paneli -->
                    <div id="meta-token-status" class="rounded-lg p-4 border border-slate-200 bg-slate-50">
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-sm font-bold text-slate-700">Token Durumu</h3>
                            <button type="button" onclick="checkMetaToken()" id="meta-check-btn" class="px-3 py-1.5 bg-slate-700 hover:bg-slate-800 text-white rounded-lg text-xs font-bold transition-all">
                                Kontrol Et
                            </button>
                        </div>
                        <div id="meta-token-result" class="q-hint text-sm">
                            Token durumunu kontrol etmek i&ccedil;in butona t&#305;klay&#305;n.
                        </div>
                    </div>
                    
                    <!-- Webhook + Verify Token -->
                    <div class="bg-slate-50 rounded-lg p-3 border border-slate-100 space-y-2">
                        <div class="flex flex-wrap items-center gap-2 text-xs sm:text-sm">
                            <span class="font-semibold text-slate-500">Callback URL:</span>
                            <code class="flex-1 min-w-0 truncate bg-white px-2 py-1 rounded border border-slate-200 font-mono text-slate-700"><?php echo htmlspecialchars(BASE_URL . '/api/webhook/meta'); ?></code>
                            <button type="button" onclick="navigator.clipboard.writeText('<?php echo addslashes(BASE_URL . '/api/webhook/meta'); ?>').then(function(){ window.NotificationManager.success('Kopyalandı'); });" class="px-2 py-1 bg-slate-200 hover:bg-slate-300 rounded text-xs font-medium">Kopyala</button>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 text-xs sm:text-sm">
                            <span class="font-semibold text-slate-500">Verify Token:</span>
                            <code class="flex-1 min-w-0 truncate bg-white px-2 py-1 rounded border border-slate-200 font-mono text-slate-700"><?php echo htmlspecialchars($settings['meta_webhook_verify_token'] ?? ''); ?></code>
                            <button type="button" onclick="navigator.clipboard.writeText('<?php echo addslashes($settings['meta_webhook_verify_token'] ?? ''); ?>').then(function(){ window.NotificationManager.success('Kopyalandı'); });" class="px-2 py-1 bg-slate-200 hover:bg-slate-300 rounded text-xs font-medium">Kopyala</button>
                            <input type="hidden" name="meta_webhook_verify_token" value="<?php echo htmlspecialchars($settings['meta_webhook_verify_token'] ?? ''); ?>"/>
                        </div>
                    </div>
                    
                    <!-- Zorunlu alanlar -->
                    <div class="space-y-4">
                        <h3 class="text-sm font-bold text-slate-700">Zorunlu Bilgiler</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="q-label">Phone Number ID <span class="text-red-500">*</span></label>
                                <input type="text" name="meta_phone_number_id" value="<?php echo htmlspecialchars($settings['meta_phone_number_id'] ?? ''); ?>" placeholder="1010326068826678" <?php echo $canEdit ? '' : 'disabled'; ?>
                                       class="q-input"/>
                            </div>
                            <div>
                                <label class="q-label">WhatsApp Business Account ID <span class="text-red-500">*</span></label>
                                <input type="text" name="meta_whatsapp_business_account_id" value="<?php echo htmlspecialchars($settings['meta_whatsapp_business_account_id'] ?? ''); ?>" placeholder="942227165037940" <?php echo $canEdit ? '' : 'disabled'; ?>
                                       class="q-input"/>
                            </div>
                        </div>
                        <!-- Platform-geneli master switch: işletmeler Meta API üzerinden
                             müşterilerine sıra/kuyruk mesajı gönderebilsin mi? -->
                        <?php
                            $queueMsgOn = (string)($settings['meta_queue_messaging_enabled'] ?? '1') !== '0';
                        ?>
                        <div class="flex items-start justify-between gap-4 p-4 rounded-xl border border-indigo-100 bg-indigo-50">
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-slate-900">İşletmeler sıra/kuyruk mesajı gönderebilsin</p>
                                <p class="q-hint text-xs mt-1">
                                    Açık olduğunda işletmeler, Meta Cloud API üzerinden müşterilerine "sıranız geldi" / "sıraya eklendiniz" WhatsApp mesajı gönderebilir. Kapatırsanız hiçbir işletme gönderemez (tek tek yetkilendirmeler de yok sayılır).
                                </p>
                            </div>
                            <!-- Hidden section marker: controller sadece bu alan formda olduğunda
                                 toggle'ı kaydeder; böylece diğer sekmeler yanlışlıkla sıfırlamaz. -->
                            <input type="hidden" name="meta_platform_section" value="1"/>
                            <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                                <input type="checkbox" name="meta_queue_messaging_enabled" value="1"
                                       class="sr-only peer"
                                       <?php echo $queueMsgOn ? 'checked' : ''; ?>
                                       <?php echo $canEdit ? '' : 'disabled'; ?>>
                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-500"></div>
                            </label>
                        </div>
                        <div>
                            <label class="q-label">
                                Queue Bildirim Template Adı
                            </label>
                            <input type="text" name="meta_queue_template_name"
                                   value="<?php echo htmlspecialchars($settings['meta_queue_template_name'] ?? ''); ?>"
                                   placeholder="queue_table_ready"
                                   <?php echo $canEdit ? '' : 'disabled'; ?>
                                   class="q-input"/>
                            <p class="q-hint text-xs mt-1">
                                Tüm işletmelerin sıra bildirimlerinde kullanılacak <strong>onaylı</strong> Meta template'i.
                                Parametreler: <code>{{1}}</code> isim · <code>{{2}}</code> sıra no · <code>{{3}}</code> işletme adı.
                                Boş bırakılırsa WhatsApp gönderimi tüm tenantlar için atlanır.
                            </p>
                        </div>
                        <div>
                            <label class="q-label">Access Token <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <input type="password" id="meta-access-token" name="meta_access_token" value="<?php echo htmlspecialchars($settings['meta_access_token'] ?? ''); ?>" placeholder="EAAxxxx..." <?php echo $canEdit ? '' : 'disabled'; ?>
                                       class="w-full p-3 pr-10 bg-slate-50 rounded-lg text-sm font-medium border border-slate-200 focus:border-indigo-300 outline-none"/>
                                <?php if ($canEdit): ?>
                                <button type="button" onclick="toggleMetaTokenVisibility()" class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                                    <svg id="meta-token-eye" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    <svg id="meta-token-eye-off" class="w-4 h-4 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.487 5.236m0 0L21 21"></path></svg>
                                </button>
                                <?php endif; ?>
                            </div>
                            <p class="q-hint text-xs mt-1">Meta Business Suite &gt; System Users &gt; Generate Token ile <strong>kal&#305;c&#305;</strong> token al&#305;n.</p>
                        </div>
                    </div>
                    
                    <!-- App ID + App Secret -->
                    <div class="space-y-3">
                        <h3 class="text-sm font-bold text-slate-700">App ID &amp; App Secret</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="q-label">Meta App ID</label>
                                <input type="text" name="meta_app_id" value="<?php echo htmlspecialchars($settings['meta_app_id'] ?? ''); ?>" placeholder="1481209770280333" <?php echo $canEdit ? '' : 'disabled'; ?>
                                       class="q-input"/>
                            </div>
                            <div>
                                <label class="q-label">App Secret</label>
                                <div class="relative">
                                    <input type="password" id="meta-app-secret" name="meta_app_secret" value="<?php echo htmlspecialchars($settings['meta_app_secret'] ?? ''); ?>" placeholder="xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" <?php echo $canEdit ? '' : 'disabled'; ?>
                                           class="w-full p-3 pr-10 bg-slate-50 rounded-lg text-sm font-medium border border-slate-200 focus:border-indigo-300 outline-none"/>
                                    <?php if ($canEdit): ?>
                                    <button type="button" onclick="toggleMetaSecretVisibility()" class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                                        <svg id="meta-secret-eye" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                        <svg id="meta-secret-eye-off" class="w-4 h-4 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.487 5.236m0 0L21 21"></path></svg>
                                    </button>
                                    <?php endif; ?>
                                </div>
                                <p class="q-hint text-xs mt-1">Webhook imza do&gbreve;rulamas&#305; i&ccedil;in kullan&#305;l&#305;r. Mesaj g&ouml;nderimi i&ccedil;in gerekli <strong>de&gbreve;ildir</strong>.</p>
                            </div>
                        </div>
                    </div>

                    <?php if ($isSuperAdmin): ?>
                    <!-- Super Admin: Yeni Kayıt Bildirimleri -->
                    <?php
                        $welcomeEmailEnabled = (($settings['welcome_email_enabled'] ?? '1') === '1');
                        $welcomeWhatsappEnabled = (($settings['welcome_whatsapp_enabled'] ?? '1') === '1');
                    ?>
                    <div class="space-y-3 border-t" style="border-color:var(--color-border-1) pt-5">
                        <h3 class="text-sm font-bold text-slate-700">Yeni Kayıt Hoş Geldin Mesajları</h3>
                        <p class="text-xs text-slate-400 -mt-2">Web veya mobil üzerinden yeni kayıt olan işletmelere otomatik olarak gönderilir.</p>
                        <input type="hidden" name="welcome_notifications_section" value="1"/>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label class="flex items-center justify-between gap-3 p-3 bg-slate-50 rounded-lg border border-slate-200 cursor-pointer">
                                <span>
                                    <span class="block text-sm font-bold text-slate-800">Hoş geldin e-postası</span>
                                    <span class="block text-[11px] text-slate-500">SMTP üzerinden gönderilir</span>
                                </span>
                                <span class="relative inline-flex">
                                    <input type="checkbox" name="welcome_email_enabled" value="1" <?php echo $welcomeEmailEnabled ? 'checked' : ''; ?> <?php echo $canEdit ? '' : 'disabled'; ?> class="peer sr-only"/>
                                    <span class="w-10 h-6 bg-slate-300 rounded-full peer-checked:bg-indigo-500 transition-all relative after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:w-5 after:h-5 after:bg-white after:rounded-full after:transition-all peer-checked:after:translate-x-4"></span>
                                </span>
                            </label>
                            <label class="flex items-center justify-between gap-3 p-3 bg-slate-50 rounded-lg border border-slate-200 cursor-pointer">
                                <span>
                                    <span class="block text-sm font-bold text-slate-800">WhatsApp hoş geldin mesajı</span>
                                    <span class="block text-[11px] text-slate-500">Meta şablonu: qordy_hosgeldin</span>
                                </span>
                                <span class="relative inline-flex">
                                    <input type="checkbox" name="welcome_whatsapp_enabled" value="1" <?php echo $welcomeWhatsappEnabled ? 'checked' : ''; ?> <?php echo $canEdit ? '' : 'disabled'; ?> class="peer sr-only"/>
                                    <span class="w-10 h-6 bg-slate-300 rounded-full peer-checked:bg-indigo-500 transition-all relative after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:w-5 after:h-5 after:bg-white after:rounded-full after:transition-all peer-checked:after:translate-x-4"></span>
                                </span>
                            </label>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
            </div>

            <!-- HISTORY SUB-TAB -->
            <div id="meta-sub-history" class="meta-sub-content hidden space-y-4">
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <!-- Header -->
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-bold text-slate-700">Mesaj Ge&ccedil;mi&scedil;i</h3>
                        <div class="flex items-center gap-2">
                            <span id="meta-history-count" class="text-[10px] text-slate-400"></span>
                            <button type="button" onclick="metaHistoryResetFilters()" class="text-[10px] text-slate-400 hover:text-slate-600 font-bold" title="Filtreleri S&#305;f&#305;rla">&#x2715; S&#305;f&#305;rla</button>
                            <button type="button" onclick="loadMetaHistory(1)" class="text-[10px] text-indigo-600 hover:text-indigo-600 font-bold">&#x21bb; Yenile</button>
                        </div>
                    </div>

                    <!-- Filters Panel -->
                    <div id="meta-history-filters" class="mb-4 space-y-3">
                        <!-- Row 1: Search + Phone -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <div class="relative">
                                <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                <input type="text" id="mh-filter-search" placeholder="&#304;&ccedil;erik, hata, &#351;ablon veya numara ara..."
                                       class="w-full pl-8 pr-3 py-2 bg-slate-50 rounded-lg text-xs border border-slate-200 focus:border-indigo-300 focus:bg-white outline-none transition-all"
                                       onkeydown="if(event.key==='Enter'){event.preventDefault();loadMetaHistory(1);}" />
                            </div>
                            <div class="relative">
                                <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                <input type="text" id="mh-filter-phone" placeholder="Telefon numaras&#305; (&#246;rn: 5321234567)"
                                       class="w-full pl-8 pr-3 py-2 bg-slate-50 rounded-lg text-xs border border-slate-200 focus:border-indigo-300 focus:bg-white outline-none transition-all"
                                       onkeydown="if(event.key==='Enter'){event.preventDefault();loadMetaHistory(1);}" />
                            </div>
                        </div>
                        <!-- Row 2: Status, Type, Dates, Per Page -->
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2">
                            <select id="mh-filter-status" class="px-2 py-2 bg-slate-50 rounded-lg text-xs border border-slate-200 focus:border-indigo-300 outline-none" onchange="loadMetaHistory(1)">
                                <option value="">T&uuml;m Durumlar</option>
                                <option value="sent">G&ouml;nderildi</option>
                                <option value="delivered">Teslim</option>
                                <option value="read">Okundu</option>
                                <option value="failed">Ba&scedil;ar&#305;s&#305;z</option>
                                <option value="pending">Bekliyor</option>
                            </select>
                            <select id="mh-filter-type" class="px-2 py-2 bg-slate-50 rounded-lg text-xs border border-slate-200 focus:border-indigo-300 outline-none" onchange="loadMetaHistory(1)">
                                <option value="">T&uuml;m Tipler</option>
                                <option value="otp">OTP</option>
                                <option value="test">Test</option>
                                <option value="template">&Scedil;ablon</option>
                                <option value="text">Metin</option>
                                <option value="marketing">Pazarlama</option>
                                <option value="other">Di&gbreve;er</option>
                            </select>
                            <input type="date" id="mh-filter-date-from" class="px-2 py-2 bg-slate-50 rounded-lg text-xs border border-slate-200 focus:border-indigo-300 outline-none" onchange="loadMetaHistory(1)" title="Ba&scedil;lang&#305;&ccedil; tarihi" />
                            <input type="date" id="mh-filter-date-to" class="px-2 py-2 bg-slate-50 rounded-lg text-xs border border-slate-200 focus:border-indigo-300 outline-none" onchange="loadMetaHistory(1)" title="Biti&scedil; tarihi" />
                            <select id="mh-filter-perpage" class="px-2 py-2 bg-slate-50 rounded-lg text-xs border border-slate-200 focus:border-indigo-300 outline-none" onchange="loadMetaHistory(1)">
                                <option value="20">20 / sayfa</option>
                                <option value="50">50 / sayfa</option>
                                <option value="100">100 / sayfa</option>
                            </select>
                            <button type="button" onclick="loadMetaHistory(1)" class="q-btn q-btn--ink q-btn--sm flex items-center justify-center gap-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                Filtrele
                            </button>
                        </div>
                        <!-- Active filters display -->
                        <div id="mh-active-filters" class="hidden flex flex-wrap gap-1.5"></div>
                    </div>

                    <!-- Table -->
                    <div id="meta-history-table" class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="border-b border-slate-100">
                                    <th class="text-left py-2 px-2 font-bold text-slate-500 w-[100px]">Tarih</th>
                                    <th class="text-left py-2 px-2 font-bold text-slate-500 w-[60px]">Tip</th>
                                    <th class="text-left py-2 px-2 font-bold text-slate-500 w-[110px]">Al&#305;c&#305;</th>
                                    <th class="text-left py-2 px-2 font-bold text-slate-500">&#304;&ccedil;erik</th>
                                    <th class="text-left py-2 px-2 font-bold text-slate-500 w-[70px]">Durum</th>
                                    <th class="text-right py-2 px-2 font-bold text-slate-500 w-[55px]">S&uuml;re</th>
                                </tr>
                            </thead>
                            <tbody id="meta-history-body">
                                <tr><td colspan="6" class="py-8 text-center text-slate-400">Y&uuml;kleniyor...</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div id="meta-history-pagination" class="flex flex-col sm:flex-row items-center justify-between mt-4 pt-3 border-t border-slate-100 gap-2"></div>
                </div>

                <!-- Top Recipients -->
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <h3 class="text-sm font-bold text-slate-700 mb-3">En &Ccedil;ok Mesaj G&ouml;nderilenler</h3>
                    <div id="meta-top-recipients" class="space-y-2">
                        <p class="text-xs text-slate-400 py-4 text-center">Y&uuml;kleniyor...</p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; /* end Meta tab super-admin-only */ ?>
        
        <!-- Ödeme Ayarları Tab (Süper Admin) -->
        <?php if ($isSuperAdmin): ?>
        <div id="content-payment" class="tab-content hidden">
            <div class="q-card q-card--pad q-stack">
                <h2 class="q-card__title">Ödeme Ayarları</h2>
                <p class="q-hint text-sm">Müşteri paket ödeme sayfasında gösterilecek yöntemler. Manuel ödeme kaldırıldı; sadece online ödeme ve havale (isteğe bağlı) kullanılır.</p>
                <div class="border-t" style="border-color:var(--color-border-1) pt-4">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="hidden" name="payment_bank_transfer_enabled" value="0">
                        <input type="checkbox" name="payment_bank_transfer_enabled" value="1" <?php echo $paymentBankTransferEnabled ? 'checked' : ''; ?> <?php echo $canEdit ? '' : 'disabled'; ?>
                               class="w-5 h-5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"/>
                        <span class="font-semibold text-slate-800">Havale / EFT ile ödeme aktif</span>
                    </label>
                    <p class="text-xs text-slate-500 mt-2 ml-8">Kapalıyken müşteriler sadece online ödeme (iyzico) ile paket satın alabilir.</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- 2FA / Güvenlik Ayarları Tab (Süper Admin) -->
        <?php if ($isSuperAdmin): ?>
        <?php
            $t2fa = (string)($settings['auth_2fa_totp_enabled']     ?? '1') === '1';
            $w2fa = (string)($settings['auth_2fa_whatsapp_enabled'] ?? '0') === '1';
            $e2fa = (string)($settings['auth_2fa_email_enabled']    ?? '1') === '1';
            $s2fa = (string)($settings['auth_2fa_sms_enabled']      ?? '0') === '1';
        ?>
        <div id="content-auth2fa" class="tab-content hidden">
            <div class="q-card q-card--pad space-y-5">
                <div>
                    <h2 class="q-card__title">İki Adımlı Doğrulama (2FA) Yöntemleri</h2>
                    <p class="text-sm text-slate-500 mt-1">Kullanıcıların (işletme sahibi ve personel) login sırasında kullanabileceği 2FA yöntemlerini platform genelinde aç/kapat.</p>
                </div>

                <!-- Marker so backend knows this section was submitted -->
                <input type="hidden" name="auth_2fa_section" value="1">

                <div class="space-y-3">
                    <!-- TOTP -->
                    <label class="flex items-start gap-3 p-4 rounded-xl border border-slate-200 hover:border-indigo-300 transition cursor-pointer">
                        <input type="hidden" name="auth_2fa_totp_enabled" value="0">
                        <input type="checkbox" name="auth_2fa_totp_enabled" value="1" <?php echo $t2fa ? 'checked' : ''; ?> <?php echo $canEdit ? '' : 'disabled'; ?>
                               class="w-5 h-5 mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"/>
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-slate-900">Authenticator Uygulaması (TOTP)</span>
                                <span class="text-xs px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 font-semibold">Önerilen</span>
                            </div>
                            <p class="text-xs text-slate-500 mt-1">Google Authenticator, 1Password, Authy gibi uygulamalarla 6 haneli zaman bazlı kod. İnternet gerektirmez.</p>
                        </div>
                    </label>

                    <!-- WhatsApp -->
                    <label class="flex items-start gap-3 p-4 rounded-xl border border-slate-200 hover:border-emerald-300 transition cursor-pointer">
                        <input type="hidden" name="auth_2fa_whatsapp_enabled" value="0">
                        <input type="checkbox" name="auth_2fa_whatsapp_enabled" value="1" <?php echo $w2fa ? 'checked' : ''; ?> <?php echo $canEdit ? '' : 'disabled'; ?>
                               class="w-5 h-5 mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"/>
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-slate-900">WhatsApp (Meta Cloud API)</span>
                                <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 font-semibold">Popüler</span>
                            </div>
                            <p class="text-xs text-slate-500 mt-1">Doğrulama kodu <code>qordy_dogrulama</code> WhatsApp şablonuyla gönderilir. Meta API ayarlarının doldurulmuş olması gerekir (Meta sekmesi).</p>
                        </div>
                    </label>

                    <!-- Email -->
                    <label class="flex items-start gap-3 p-4 rounded-xl border border-slate-200 hover:border-sky-300 transition cursor-pointer">
                        <input type="hidden" name="auth_2fa_email_enabled" value="0">
                        <input type="checkbox" name="auth_2fa_email_enabled" value="1" <?php echo $e2fa ? 'checked' : ''; ?> <?php echo $canEdit ? '' : 'disabled'; ?>
                               class="w-5 h-5 mt-0.5 rounded border-slate-300 text-sky-600 focus:ring-sky-500"/>
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-slate-900">E-posta ile Doğrulama</span>
                            </div>
                            <p class="text-xs text-slate-500 mt-1">6 haneli kod SMTP üzerinden kullanıcının e-posta adresine gönderilir.</p>
                        </div>
                    </label>

                    <!-- SMS -->
                    <label class="flex items-start gap-3 p-4 rounded-xl border border-slate-200 hover:border-amber-300 transition cursor-pointer">
                        <input type="hidden" name="auth_2fa_sms_enabled" value="0">
                        <input type="checkbox" name="auth_2fa_sms_enabled" value="1" <?php echo $s2fa ? 'checked' : ''; ?> <?php echo $canEdit ? '' : 'disabled'; ?>
                               class="w-5 h-5 mt-0.5 rounded border-slate-300 text-amber-600 focus:ring-amber-500"/>
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-slate-900">SMS ile Doğrulama</span>
                                <span class="text-xs px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 font-semibold">Deneysel</span>
                            </div>
                            <p class="text-xs text-slate-500 mt-1">SMS gateway entegrasyonu gerektirir. Şu an yoksa kapalı bırakın.</p>
                        </div>
                    </label>
                </div>

                <div class="mt-4 p-4 rounded-xl bg-slate-50 border border-slate-200">
                    <p class="q-hint text-xs leading-relaxed">
                        <strong>Nasıl çalışır?</strong> Burada kapatılan bir yöntem, mobil veya web giriş ekranlarında kullanıcılara hiç önerilmez; kullanıcı önceden o yöntemi kurmuş olsa bile doğrulama sırasında başka bir yönteme geçmek zorunda kalır. En az bir yöntem açık olmalıdır.
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tehlikeli İşlemler Tab -->
        <div id="content-danger" class="tab-content hidden">
            <div class="bg-white rounded-xl border border-red-100 shadow-soft p-6 lg:p-10">
                <div class="flex flex-col items-center justify-center space-y-6 lg:space-y-8">
        <div class="w-20 h-20 lg:w-24 lg:h-24 bg-red-100 rounded-[28px] lg:rounded-[32px] flex items-center justify-center text-red-600">
            <?php echo icon_alert_triangle(['class' => 'w-10 h-10 lg:w-12 lg:h-12']); ?>
        </div>
        <div class="text-center">
            <h2 class="text-xl lg:text-2xl font-black mb-2">Tüm Verileri Temizle</h2>
            <p class="text-slate-400 font-bold text-sm lg:text-base">Bu işlem menü ve siparişler dahil her şeyi siler.</p>
                        <p class="text-red-500 font-bold text-xs lg:text-sm mt-2">Bu işlem geri alınamaz!</p>
                    </div>
                    <?php if ($canReset): ?>
                    <button type="button" onclick="resetSystem()" class="px-12 py-5 bg-red-600 text-white rounded-3xl font-black text-lg lg:text-xl shadow-xl hover:bg-red-500 transition-all">
                        SİSTEMİ SIFIRLA
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Auto-save indicator -->
        <?php if ($canEdit): ?>
        <div id="auto-save-indicator" class="mt-4 sm:mt-5 md:mt-6 lg:mt-8 flex justify-end items-center gap-3">
            <span id="save-status" class="text-xs sm:text-sm font-bold text-slate-400 hidden">Kaydediliyor...</span>
            <span id="save-success" class="text-xs sm:text-sm font-bold text-green-500 hidden">✓ Kaydedildi</span>
            <span id="save-error" class="text-xs sm:text-sm font-bold text-red-500 hidden">✗ Hata</span>
        </div>
        <?php endif; ?>
        </div>
    </form>
        </div>
    </div>
  </div>
</div>

<script>
const baseUrl = <?php echo json_encode($baseUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const settingsTranslations = <?php echo json_encode($settingsTranslations, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
let currentTab = 'general';

function switchTab(tab) {
    currentTab = tab;
    
    // Update URL: ?tab=meta#meta (shareable links)
    var newUrl = window.location.pathname + '?tab=' + tab + '#' + tab;
    if (history.pushState) {
        history.pushState(null, '', newUrl);
    } else {
        window.location.hash = '#' + tab;
    }
    
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
    });
    
    // Remove active state from all tabs
    document.querySelectorAll('.q-settings-nav__btn').forEach(btn => {
        btn.classList.remove('selected');
        btn.setAttribute('aria-selected', 'false');
    });
    
    // Show selected tab content
    const contentEl = document.getElementById('content-' + tab);
    if (contentEl) {
        contentEl.classList.remove('hidden');
    }
    
    // Add active state to selected tab
    const activeTab = document.getElementById('tab-' + tab);
    if (activeTab) {
        activeTab.classList.add('selected');
        activeTab.setAttribute('aria-selected', 'true');
    }
    
    // Initialize last saved data for this tab if not already set
    if (settingsForm && !window['lastSaved_' + tab]) {
        window['lastSaved_' + tab] = JSON.stringify(getFormDataFromTab(tab));
    }
}

function uploadLogo(file) {
    if (!file) return;
    
    const formData = new FormData();
    formData.append('logo', file);
    formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');
    
    const csrfToken = window.CSRF_TOKEN || (typeof csrf_token !== 'undefined' ? csrf_token : '');
    const apiPrefix = <?php echo json_encode($apiPrefix); ?>;
    fetch(`${baseUrl}${apiPrefix}/settings/upload-logo`, {
        method: 'POST',
        headers: {
            'X-CSRF-Token': csrfToken
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            window.NotificationManager.error('Hata: ' + data.error);
        } else {
            document.getElementById('logo-preview').src = data.url + '?t=' + Date.now();
            window.NotificationManager.success('Logo başarıyla yüklendi.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        window.NotificationManager.error('Logo yüklenirken bir hata oluştu.');
    });
}

function uploadFavicon(file) {
    if (!file) return;
    
    const formData = new FormData();
    formData.append('favicon', file);
    formData.append('csrf_token', '<?php echo generateCSRFToken(); ?>');
    
    const csrfToken = window.CSRF_TOKEN || (typeof csrf_token !== 'undefined' ? csrf_token : '');
    const apiPrefix = <?php echo json_encode($apiPrefix); ?>;
    fetch(`${baseUrl}${apiPrefix}/settings/upload-favicon`, {
        method: 'POST',
        headers: {
            'X-CSRF-Token': csrfToken
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            window.NotificationManager.error('Hata: ' + data.error);
        } else {
            document.getElementById('favicon-preview').src = data.url + '?t=' + Date.now();
            window.NotificationManager.success('Favicon başarıyla yüklendi.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        window.NotificationManager.error('Favicon yüklenirken bir hata oluştu.');
    });
}

function toggleSmtpPasswordVisibility() {
    const input = document.getElementById('smtp-password');
    const eyeIcon = document.getElementById('smtp-eye-icon');
    const eyeOffIcon = document.getElementById('smtp-eye-off-icon');
    
    if (input && eyeIcon && eyeOffIcon) {
        if (input.type === 'password') {
            input.type = 'text';
            eyeIcon.classList.add('hidden');
            eyeOffIcon.classList.remove('hidden');
        } else {
            input.type = 'password';
            eyeIcon.classList.remove('hidden');
            eyeOffIcon.classList.add('hidden');
        }
    }
}

function toggleMetaTokenVisibility() {
    var input = document.getElementById('meta-access-token');
    var eyeIcon = document.getElementById('meta-token-eye');
    var eyeOffIcon = document.getElementById('meta-token-eye-off');
    if (input && eyeIcon && eyeOffIcon) {
        if (input.type === 'password') {
            input.type = 'text';
            eyeIcon.classList.add('hidden');
            eyeOffIcon.classList.remove('hidden');
        } else {
            input.type = 'password';
            eyeIcon.classList.remove('hidden');
            eyeOffIcon.classList.add('hidden');
        }
    }
}

function toggleMetaSecretVisibility() {
    var input = document.getElementById('meta-app-secret');
    var eyeIcon = document.getElementById('meta-secret-eye');
    var eyeOffIcon = document.getElementById('meta-secret-eye-off');
    if (input && eyeIcon && eyeOffIcon) {
        if (input.type === 'password') {
            input.type = 'text';
            eyeIcon.classList.add('hidden');
            eyeOffIcon.classList.remove('hidden');
        } else {
            input.type = 'password';
            eyeIcon.classList.remove('hidden');
            eyeOffIcon.classList.add('hidden');
        }
    }
}

async function sendTestEmail() {
    const emailInput = document.getElementById('test-email-address');
    const email = emailInput ? emailInput.value.trim() : '';
    if (!email) {
        if (window.NotificationManager) window.NotificationManager.warning('Lütfen bir e-posta adresi girin.');
        return;
    }
    const apiPrefix = <?php echo json_encode($apiPrefix); ?>;
    const csrfToken = window.CSRF_TOKEN || (typeof csrf_token !== 'undefined' ? csrf_token : '');
    try {
        if (window.NotificationManager) window.NotificationManager.info('Test e-postası gönderiliyor...');
        const res = await fetch(`${baseUrl}${apiPrefix}/email/test`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify({ email: email })
        });
        const data = await res.json();
        if (data.success || (data.status === 'success')) {
            if (window.NotificationManager) window.NotificationManager.success('Test e-postası başarıyla gönderildi.');
        } else {
            if (window.NotificationManager) window.NotificationManager.error(data.error || data.message || 'E-posta gönderilemedi.');
        }
    } catch (e) {
        console.error(e);
        if (window.NotificationManager) window.NotificationManager.error('E-posta gönderilirken bir hata oluştu.');
    }
}

function toggleGeminiApiKeyVisibility() {
    const input = document.getElementById('gemini-api-key');
    const eyeIcon = document.getElementById('gemini-eye-icon');
    const eyeOffIcon = document.getElementById('gemini-eye-off-icon');
    
    if (input && eyeIcon && eyeOffIcon) {
        if (input.type === 'password') {
            input.type = 'text';
            eyeIcon.classList.add('hidden');
            eyeOffIcon.classList.remove('hidden');
        } else {
            input.type = 'password';
            eyeIcon.classList.remove('hidden');
            eyeOffIcon.classList.add('hidden');
        }
    }
}

/**
 * Show confirmation dialog that requires typing "HER ŞEYİ SİL"
 */
function showResetConfirmation() {
    return new Promise((resolve) => {
        // Create modal overlay
        const overlay = document.createElement('div');
        overlay.className = 'fixed inset-0 bg-black/60 backdrop-blur-sm z-[10000] flex items-center justify-center p-4';
        overlay.style.animation = 'fadeIn 0.2s ease-out';
        
        // Create modal
        const modal = document.createElement('div');
        modal.className = 'bg-white rounded-3xl border-4 border-red-300 bg-red-50 shadow-2xl p-6 lg:p-8 max-w-lg w-full';
        modal.style.animation = 'slideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1)';
        
        const requiredText = 'HER ŞEYİ SİL';
        
        modal.innerHTML = `
            <div class="flex flex-col items-center text-center space-y-4">
                <div class="w-20 h-20 bg-red-500 rounded-full flex items-center justify-center">
                    <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
                <div>
                    <h2 class="text-2xl lg:text-3xl font-black text-red-700 mb-2">DİKKAT!</h2>
                    <p class="text-base lg:text-lg font-bold text-slate-700 mb-4">Bu işlem menü ve siparişler dahil <span class="text-red-600">HER ŞEYİ</span> siler.</p>
                    <p class="text-sm lg:text-base text-red-600 font-black mb-6">Bu işlem geri alınamaz!</p>
                </div>
                <div class="w-full">
                    <label class="block text-sm font-black text-slate-700 mb-2">Onaylamak için <span class="text-red-600 font-black">"${requiredText}"</span> yazın:</label>
                    <input type="text" id="reset-confirm-input" 
                           class="w-full p-4 bg-white border-2 border-red-300 rounded-xl font-bold text-center text-lg focus:border-red-500 focus:ring-2 focus:ring-red-200 outline-none transition-all"
                           placeholder="Buraya yazın..."
                           autocomplete="off">
                    <p id="reset-confirm-error" class="text-red-600 text-sm font-bold mt-2 hidden">Lütfen "${requiredText}" yazın</p>
                </div>
                <div class="flex gap-3 w-full mt-4">
                    <button class="reset-cancel-btn flex-1 py-4 bg-slate-200 text-slate-900 rounded-xl font-black text-base hover:bg-slate-300 transition-all">
                        İptal
                    </button>
                    <button class="reset-confirm-btn flex-1 py-4 bg-red-600 text-white rounded-xl font-black text-base hover:bg-red-700 transition-all disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                        SİSTEMİ SIFIRLA
                    </button>
                </div>
            </div>
        `;
        
        overlay.appendChild(modal);
        document.body.appendChild(overlay);
        
        const confirmInput = modal.querySelector('#reset-confirm-input');
        const confirmBtn = modal.querySelector('.reset-confirm-btn');
        const cancelBtn = modal.querySelector('.reset-cancel-btn');
        const errorMsg = modal.querySelector('#reset-confirm-error');
        
        // Handle input
        confirmInput.addEventListener('input', function() {
            const value = this.value.trim();
            if (value === requiredText) {
                confirmBtn.disabled = false;
                confirmBtn.classList.remove('disabled:opacity-50', 'disabled:cursor-not-allowed');
                errorMsg.classList.add('hidden');
                confirmInput.classList.remove('border-red-300');
                confirmInput.classList.add('border-green-500');
            } else {
                confirmBtn.disabled = true;
                confirmBtn.classList.add('disabled:opacity-50', 'disabled:cursor-not-allowed');
                if (value.length > 0) {
                    errorMsg.classList.remove('hidden');
                    confirmInput.classList.remove('border-green-500');
                    confirmInput.classList.add('border-red-500');
                } else {
                    errorMsg.classList.add('hidden');
                    confirmInput.classList.remove('border-red-500', 'border-green-500');
                    confirmInput.classList.add('border-red-300');
                }
            }
        });
        
        // Handle Enter key
        confirmInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !confirmBtn.disabled) {
                confirmBtn.click();
            }
        });
        
        // Handle confirm button
        confirmBtn.addEventListener('click', function() {
            if (!this.disabled && confirmInput.value.trim() === requiredText) {
                document.body.removeChild(overlay);
                resolve(true);
            }
        });
        
        // Handle cancel button
        cancelBtn.addEventListener('click', function() {
            document.body.removeChild(overlay);
            resolve(false);
        });
        
        // Handle overlay click (close on outside click)
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) {
                document.body.removeChild(overlay);
                resolve(false);
            }
        });
        
        // Focus input
        setTimeout(() => confirmInput.focus(), 100);
    });
}

async function resetSystem() {
    if (!window.NotificationManager) {
        console.error('NotificationManager is not available');
        return;
    }
    
    // Show confirmation dialog with required text
    const confirmed = await showResetConfirmation();
    if (!confirmed) {
        return;
    }
    
    const csrfToken = window.CSRF_TOKEN || (typeof csrf_token !== 'undefined' ? csrf_token : '');
    
    // Show loading indicator
    window.NotificationManager.info('Sistem sıfırlanıyor, lütfen bekleyin...');
    
    const adminPrefix = <?php echo json_encode($adminPrefix); ?>;
    fetch(`${baseUrl}${adminPrefix}/settings/reset`, {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({}) // Empty body but required for JSON request
    })
    .then(response => {
        console.log('Reset response status:', response.status);
        
        if (response.redirected) {
            window.location.href = response.url;
            return null;
        }
        
        if (!response.ok) {
            // Try to get error message from response
            return response.text().then(text => {
                try {
                    const json = JSON.parse(text);
                    throw new Error(json.error || json.message || 'Sistem sıfırlama başarısız oldu');
                } catch (e) {
                    if (e instanceof Error && e.message !== 'Sistem sıfırlama başarısız oldu') {
                        throw e;
                    }
                    throw new Error('HTTP ' + response.status + ': ' + text.substring(0, 200));
                }
            });
        }
        
        return response.json();
    })
    .then(data => {
        if (data === null) {
            return; // Redirect handled
        }
        
        console.log('Reset response data:', data);
        
        // Check for error in response
        if (data && (data.error || data.status === 'error')) {
            const errorMsg = data.error || data.message || 'Sistem sıfırlama başarısız oldu';
            window.NotificationManager.error('Hata: ' + errorMsg);
            return;
        }
        
        // Success
        const successMsg = (typeof settingsTranslations !== 'undefined' && settingsTranslations.resetSuccess) 
            ? settingsTranslations.resetSuccess 
            : (data && data.message ? data.message : 'Sistem başarıyla sıfırlandı');
        window.NotificationManager.success(successMsg);
        
        // Reload page after short delay
        setTimeout(() => {
            window.location.reload();
        }, 2000);
    })
    .catch(error => {
        console.error('Reset system error:', error);
        const errorMsg = error.message || ((typeof settingsTranslations !== 'undefined' && settingsTranslations.resetFailed) 
            ? settingsTranslations.resetFailed 
            : 'Sistem sıfırlama başarısız oldu');
        window.NotificationManager.error(errorMsg);
    });
}

// Auto-save functionality
const settingsForm = document.getElementById('settings-form');
let autoSaveTimeout = null;
let isSaving = false;
let lastSavedData = null;

// Get form data as object (from all tabs - kept for backward compatibility)
function getFormData() {
    const formData = new FormData(settingsForm);
    const data = {};
    
    // Collect all form fields
    for (let [key, value] of formData.entries()) {
        if (data[key]) {
            // Handle multiple values (like arrays)
            if (Array.isArray(data[key])) {
                data[key].push(value);
            } else {
                data[key] = [data[key], value];
            }
        } else {
            data[key] = value;
        }
    }
    
    // Handle checkboxes
    settingsForm.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
        if (checkbox.name) {
            data[checkbox.name] = checkbox.checked ? (checkbox.value || '1') : '0';
        }
    });
    
    return data;
}

// Get form data from specific tab only
function getFormDataFromTab(tabName) {
    const tabContent = document.getElementById('content-' + tabName);
    if (!tabContent) {
        console.warn('Tab content not found:', tabName);
        return {};
    }
    
    const data = {};
    
    // Get all inputs from this tab only
    const inputs = tabContent.querySelectorAll('input:not([type="checkbox"]):not([type="file"]), select, textarea');
    inputs.forEach(input => {
        if (input.name) {
            if (data[input.name]) {
                // Handle multiple values (like arrays)
                if (Array.isArray(data[input.name])) {
                    data[input.name].push(input.value);
                } else {
                    data[input.name] = [data[input.name], input.value];
                }
            } else {
                data[input.name] = input.value;
            }
        }
    });
    
    // Handle checkboxes in this tab
    tabContent.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
        if (checkbox.name) {
            if (checkbox.name.endsWith('[]')) {
                // Array inputs - only include if checked
                if (checkbox.checked) {
                    if (!data[checkbox.name]) {
                        data[checkbox.name] = [];
                    }
                    if (Array.isArray(data[checkbox.name])) {
                        data[checkbox.name].push(checkbox.value || '1');
                    }
                }
            } else {
                // Regular checkbox
                data[checkbox.name] = checkbox.checked ? (checkbox.value || '1') : '0';
            }
        }
    });
    
    return data;
}

// Show save status
function showSaveStatus(status) {
    const statusEl = document.getElementById('save-status');
    const successEl = document.getElementById('save-success');
    const errorEl = document.getElementById('save-error');
    
    // Hide all
    if (statusEl) statusEl.classList.add('hidden');
    if (successEl) successEl.classList.add('hidden');
    if (errorEl) errorEl.classList.add('hidden');
    
    // Show appropriate status
    if (status === 'saving' && statusEl) {
        statusEl.classList.remove('hidden');
    } else if (status === 'success' && successEl) {
        successEl.classList.remove('hidden');
        setTimeout(() => {
            if (successEl) successEl.classList.add('hidden');
        }, 2000);
    } else if (status === 'error' && errorEl) {
        errorEl.classList.remove('hidden');
        setTimeout(() => {
            if (errorEl) errorEl.classList.add('hidden');
        }, 3000);
    }
}

// Auto-save function
function autoSave() {
    if (isSaving) {
        console.log('Auto-save already in progress, skipping...');
        return;
    }
    
    // Get current tab name
    const activeTabContent = document.querySelector('.tab-content:not(.hidden)');
    if (!activeTabContent) {
        console.warn('No active tab found');
        return;
    }
    
    const tabId = activeTabContent.id;
    const tabName = tabId.replace('content-', '');
    
    // Get form data from current tab only
    const formData = getFormDataFromTab(tabName);
    const currentData = JSON.stringify(formData);
    
    // Get last saved data for this tab
    const tabLastSavedKey = 'lastSaved_' + tabName;
    const tabLastSavedData = window[tabLastSavedKey] || null;
    
    // Skip if data hasn't changed
    if (tabLastSavedData === currentData) {
        console.log('No changes detected for tab', tabName + ', skipping save...');
        return;
    }
    
    console.log('Starting auto-save for tab:', tabName, formData);
    isSaving = true;
    showSaveStatus('saving');
    
    // Create FormData manually to have full control
    const submitData = new FormData();
    
    // Add CSRF token first
    const csrfInput = settingsForm.querySelector('input[name="csrf_token"]');
    if (csrfInput) {
        submitData.append('csrf_token', csrfInput.value);
    }
    
    // Get all inputs from current tab only
    const tabContent = document.getElementById('content-' + tabName);
    if (!tabContent) {
        console.error('Tab content not found:', tabName);
        isSaving = false;
        showSaveStatus('error');
        return;
    }
    
    // Get all form inputs except checkboxes and file inputs from this tab
    const allInputs = tabContent.querySelectorAll('input:not([type="checkbox"]):not([type="file"]), select, textarea');
    allInputs.forEach(input => {
        if (input.name) {
            submitData.append(input.name, input.value);
        }
    });
    
    // Handle file inputs separately (only if file is selected) - from current tab
    const fileInputs = tabContent.querySelectorAll('input[type="file"]');
    fileInputs.forEach(input => {
        if (input.name && input.files && input.files.length > 0) {
            submitData.append(input.name, input.files[0]);
        }
    });
    
    // Handle checkboxes separately - from current tab
    tabContent.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
        if (checkbox.name) {
            // Handle array inputs (like supported_languages[])
            if (checkbox.name.endsWith('[]')) {
                if (checkbox.checked) {
                    // For array inputs, append the value (FormData will handle as array)
                    submitData.append(checkbox.name, checkbox.value || '1');
                }
                // Don't add unchecked array items
            } else {
                // Regular checkbox
                if (checkbox.checked) {
                    submitData.append(checkbox.name, checkbox.value || '1');
                } else {
                    // For unchecked checkboxes, explicitly set to '0'
                    submitData.append(checkbox.name, '0');
                }
            }
        }
    });
    
    // Debug: Log what we're sending
    console.log('Sending FormData with fields:', Array.from(submitData.keys()));
    
    const csrfToken = window.CSRF_TOKEN || (typeof csrf_token !== 'undefined' ? csrf_token : '');
    const adminPrefix = <?php echo json_encode($adminPrefix); ?>;
    fetch(`${baseUrl}${adminPrefix}/settings`, {
        method: 'POST',
        body: submitData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': csrfToken
        },
        credentials: 'same-origin'
    })
    .then(response => {
        console.log('Response received:', response.status, response.statusText);
        
        // Check content type first
        const contentType = response.headers.get('content-type') || '';
        
        // Check if response is ok (but don't throw immediately for JSON responses)
        if (!response.ok && !contentType.includes('application/json')) {
            console.error('Response not OK:', response.status, response.statusText);
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        // Always try to parse as JSON first for AJAX requests
        if (contentType.includes('application/json')) {
            return response.json().then(data => {
                // Check if response has success/error structure
                if (data && (data.success !== undefined || data.error !== undefined || data.message)) {
                    return data;
                }
                // If it's a simple object, wrap it
                return { success: true, data: data };
            }).catch(() => {
                // If JSON parsing fails, get text and try to parse manually
                return response.text().then(text => {
                    console.warn('Response was not valid JSON:', text.substring(0, 200));
                    // Try to extract JSON from text if possible
                    try {
                        const jsonMatch = text.match(/\{[\s\S]*\}/);
                        if (jsonMatch) {
                            return JSON.parse(jsonMatch[0]);
                        }
                    } catch (e) {
                        // Ignore parse errors
                    }
                    // If response contains success indicators, treat as success
                    if (text.includes('success') || text.includes('başarıyla')) {
                        return { success: true };
                    }
                    return { error: 'Geçersiz yanıt formatı' };
                });
            });
        }
        
        // If redirected (shouldn't happen for AJAX, but handle it)
        if (response.redirected) {
            console.log('Response redirected, assuming success');
            // Save last saved data for this tab
            window['lastSaved_' + tabName] = currentData;
            isSaving = false;
            showSaveStatus('success');
            if (window.NotificationManager) {
                window.NotificationManager.success('Ayarlar kaydedildi');
            }
            return null;
        }
        
        // For non-JSON responses, try to get text
        return response.text().then(text => {
            // Check if it's an HTML error page
            if (text.includes('error') || text.includes('hata') || text.includes('Error')) {
                return { error: 'Ayarlar kaydedilemedi' };
            }
            // Assume success for non-JSON responses with OK status
            return { success: true };
        }).catch(() => {
            // If text parsing fails but response was OK, assume success
            return { success: response.ok };
        });
    })
    .then(data => {
        if (data === null) {
            // Already handled (redirect case)
            return;
        }
        
        isSaving = false;
        
        // Check response format from ToastNotificationService
        // Format: { success: true/false, message: "...", error?: "...", ... }
        if (data && (data.error || data.success === false)) {
            showSaveStatus('error');
            const errorMsg = data.error || data.message || 'Ayarlar kaydedilemedi';
            console.error('Server error:', errorMsg);
            if (window.NotificationManager) {
                window.NotificationManager.error('Hata: ' + errorMsg);
            }
        } else if (data && (data.success === true || data.success)) {
            // Success case - save last saved data for this tab
            window['lastSaved_' + tabName] = currentData;
            showSaveStatus('success');
            const successMsg = data.message || 'Ayarlar kaydedildi';
            if (window.NotificationManager) {
                window.NotificationManager.success(successMsg);
            }
        } else {
            // No clear success/error indicator, but no error either - assume success
            window['lastSaved_' + tabName] = currentData;
            showSaveStatus('success');
            if (window.NotificationManager) {
                window.NotificationManager.success('Ayarlar kaydedildi');
            }
        }
    })
    .catch(error => {
        console.error('Auto-save error:', error);
        isSaving = false;
        showSaveStatus('error');
        
        // More detailed error message
        let errorMessage = 'Kaydetme hatası oluştu';
        if (error.message) {
            errorMessage += ': ' + error.message;
        }
        
        if (window.NotificationManager) {
            window.NotificationManager.error(errorMessage);
        }
    });
}

// Debounced auto-save
function debouncedAutoSave() {
    // Clear existing timeout
    if (autoSaveTimeout) {
        clearTimeout(autoSaveTimeout);
    }
    
    // Set new timeout (1.5 seconds delay)
    autoSaveTimeout = setTimeout(() => {
        autoSave();
    }, 1500);
}

// Initialize form data
if (settingsForm) {
    // Initialize last saved data for current tab
    const activeTabContent = document.querySelector('.tab-content:not(.hidden)');
    if (activeTabContent) {
        const tabId = activeTabContent.id;
        const tabName = tabId.replace('content-', '');
        window['lastSaved_' + tabName] = JSON.stringify(getFormDataFromTab(tabName));
    }
    lastSavedData = JSON.stringify(getFormData());
    
    // Prevent form submission (we use auto-save instead)
    settingsForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Check if user has edit permission
        const disabledInputs = settingsForm.querySelectorAll('input[disabled], select[disabled], textarea[disabled]');
        if (disabledInputs.length > 0) {
            if (window.NotificationManager) {
                window.NotificationManager.warning('Ayarları düzenleme yetkiniz bulunmamaktadır.');
            }
            return false;
        }
        
        // Trigger immediate save
        autoSave();
        return false;
    });
    
    // Listen to all input changes
    const inputs = settingsForm.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
        // Skip file inputs (they trigger on change, but we handle them separately)
        if (input.type === 'file') {
            input.addEventListener('change', function() {
                // File uploads need immediate save
                setTimeout(() => autoSave(), 500);
            });
        } else {
            // Text inputs, selects, textareas
            input.addEventListener('input', debouncedAutoSave);
            input.addEventListener('change', debouncedAutoSave);
        }
    });
    
    // Handle checkbox changes
    settingsForm.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
        checkbox.addEventListener('change', debouncedAutoSave);
    });
}

// Detect current location for business location setting
function detectCurrentLocation() {
    if (!navigator.geolocation) {
        window.NotificationManager.warning('Tarayıcınız konum özelliğini desteklemiyor.');
        return;
    }
    navigator.geolocation.getCurrentPosition(
        function(pos) {
            document.getElementById('business-latitude').value = pos.coords.latitude.toFixed(8);
            document.getElementById('business-longitude').value = pos.coords.longitude.toFixed(8);
            window.NotificationManager.success('Konum başarıyla algılandı!');
        },
        function(err) {
            window.NotificationManager.error('Konum alınamadı: ' + (err.message || 'Bilinmeyen hata'));
        },
        { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
    );
}

// WiFi password visibility toggle
function aria-label="WiFi şifresini göster/gizle" onclick="toggleWifiPasswordVisibility()" {
    const passwordInput = document.getElementById('wifi-password-input');
    const eyeIcon = document.getElementById('wifi-eye-icon');
    const eyeOffIcon = document.getElementById('wifi-eye-off-icon');
    
    if (passwordInput && passwordInput.type === 'password') {
        passwordInput.type = 'text';
        if (eyeIcon) eyeIcon.classList.add('hidden');
        if (eyeOffIcon) eyeOffIcon.classList.remove('hidden');
    } else if (passwordInput) {
        passwordInput.type = 'password';
        if (eyeIcon) eyeIcon.classList.remove('hidden');
        if (eyeOffIcon) eyeOffIcon.classList.add('hidden');
    }
}

// Initialize first tab - check for hash in URL or query param
const urlHash = window.location.hash.replace('#', '') || new URLSearchParams(window.location.search).get('tab') || '';
// financial and wifi tabs moved to business settings
// Meta tab sadece süper admin için — işletme panelinde açılmamalı (aksi halde
// /api/business/meta/business-info 404 bug'ına düşüyoruz).
const validTabs = <?php echo json_encode($isSuperAdmin
    // Super admin görür: sadece platform-geneli sekmeler. İşletme'ye özel
    // (location/wifi/financial/staff/roles) sekmeler burada yer almaz.
    ? ['general', 'email', 'meta', 'ai', 'language', 'system', 'payment', 'auth2fa', 'danger']
    : ['general', 'staff', 'roles', 'email',         'ai', 'language', 'location', 'system', 'danger']
); ?>;
if (urlHash && validTabs.includes(urlHash)) {
    switchTab(urlHash);
} else {
    switchTab('general');
}

// Use Utils.escapeHtml from utils.js (loaded globally)

// Add CSS animations for reset confirmation modal
if (!document.getElementById('reset-confirm-styles')) {
    const style = document.createElement('style');
    style.id = 'reset-confirm-styles';
    style.textContent = `
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
    `;
    document.head.appendChild(style);
}

// Super admin "işletme seç" akışı kaldırıldı; /qodmin/settings yalnızca
// platform geneli ayarları yönetir. İşletmeye özel ayarlar her işletmenin
// kendi /business/settings sayfasında düzenlenir.
</script>
<?php if ($isSuperAdmin): /* Meta Dashboard JS bloğu sadece süper admin içindir */ ?>
<script>
(function() {
    var _metaApiPrefix = <?php echo json_encode($apiPrefix ?? '/api/qodmin'); ?>;
    var _metaBaseUrl = <?php echo json_encode(BASE_URL); ?>;
    var _metaDashboardInterval = null;
    var _metaCurrentHistoryPage = 1;

    function _fetchJson(url, opts) {
        opts = opts || {};
        opts.headers = Object.assign({'X-Requested-With': 'XMLHttpRequest'}, opts.headers || {});
        opts.credentials = 'same-origin';
        return fetch(url, opts).then(function(r) { return r.json(); });
    }

    window.checkMetaToken = function() {
        var btn = document.getElementById('meta-check-btn');
        var resultDiv = document.getElementById('meta-token-result');
        var statusDiv = document.getElementById('meta-token-status');
        var cardDot = document.getElementById('meta-token-dot');
        var cardLabel = document.getElementById('meta-token-label');
        var cardSub = document.getElementById('meta-token-sub');
        if (btn) { btn.disabled = true; btn.textContent = 'Kontrol ediliyor...'; }
        if (cardLabel) cardLabel.textContent = 'Kontrol...';

        _fetchJson(_metaBaseUrl + _metaApiPrefix + '/meta/debug-token')
        .then(function(json) {
            var d = json.data || {};
            var html = '';
            var hasIssues = d.issues && d.issues.length > 0;
            var hasWarnings = d.warnings && d.warnings.length > 0;
            var isOk = json.success && d.is_valid && !hasIssues;

            if (isOk) {
                var borderColor = hasWarnings ? 'border-yellow-200 bg-yellow-50' : 'border-green-200 bg-green-50';
                if (statusDiv) statusDiv.className = 'rounded-lg p-4 border ' + borderColor;
                var dotColor = hasWarnings ? 'bg-yellow-500' : 'bg-green-500';
                var textColor = hasWarnings ? 'text-yellow-700' : 'text-green-700';
                html = '<div class="space-y-1">';
                html += '<div class="flex items-center gap-2"><span class="w-2 h-2 rounded-full ' + dotColor + ' inline-block"></span><span class="font-bold ' + textColor + '">Token ge\u00e7erli</span></div>';
                html += '<div class="q-hint text-xs">Uygulama: ' + (d.app_name || '-') + ' | Tip: ' + (d.type || '-') + ' | S\u00fcre: ' + (d.expires_at || '-') + '</div>';
                html += '<div class="q-hint text-xs">\u0130zinler: ' + (d.scopes ? d.scopes.join(', ') : 'yok') + '</div>';
                if (hasWarnings) {
                    html += '<ul class="text-xs text-yellow-700 space-y-1 mt-2 ml-4 list-disc">';
                    d.warnings.forEach(function(w) { html += '<li>' + w + '</li>'; });
                    html += '</ul>';
                }
                html += '</div>';
                if (cardDot) { cardDot.className = 'w-2.5 h-2.5 rounded-full ' + dotColor; cardDot.classList.remove('animate-pulse'); }
                if (cardLabel) cardLabel.textContent = hasWarnings ? 'Ge\u00e7erli \u26a0' : 'Ge\u00e7erli';
                if (cardSub) cardSub.textContent = d.expires_at === 'S\u00fcresiz (kal\u0131c\u0131)' ? 'Kal\u0131c\u0131 token' : (d.expires_at || '-');
            } else {
                if (statusDiv) statusDiv.className = 'rounded-lg p-4 border border-red-200 bg-red-50';
                html = '<div class="space-y-2">';
                html += '<div class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-red-500 inline-block"></span><span class="font-bold text-red-700">Sorun tespit edildi</span></div>';
                if (hasIssues) {
                    html += '<ul class="text-xs text-red-600 space-y-1 ml-4 list-disc">';
                    d.issues.forEach(function(issue) { html += '<li>' + issue + '</li>'; });
                    html += '</ul>';
                }
                if (d.app_name) {
                    html += '<div class="text-xs text-slate-500 mt-1">Uygulama: ' + d.app_name + ' | Tip: ' + (d.type || '-') + ' | S\u00fcre: ' + (d.expires_at || '-') + '</div>';
                    html += '<div class="text-xs text-slate-500">\u0130zinler: ' + (d.scopes ? d.scopes.join(', ') : 'yok') + '</div>';
                }
                html += '<div class="mt-2 p-2 bg-white rounded border border-red-100 q-hint text-xs">';
                html += '<strong>\u00c7\u00f6z\u00fcm:</strong> Meta Business Suite &gt; System Users &gt; Generate Token &gt; <code>whatsapp_business_messaging</code> izni ekleyin &gt; "Never expires" se\u00e7in.';
                html += '</div></div>';
                if (cardDot) { cardDot.className = 'w-2.5 h-2.5 rounded-full bg-red-500'; cardDot.classList.remove('animate-pulse'); }
                if (cardLabel) cardLabel.textContent = 'Sorunlu';
                if (cardSub) cardSub.textContent = hasIssues ? d.issues[0].substring(0,30) + '...' : 'Kontrol edin';
            }
            if (resultDiv) resultDiv.innerHTML = html;
        })
        .catch(function(e) {
            if (statusDiv) statusDiv.className = 'rounded-lg p-4 border border-yellow-200 bg-yellow-50';
            if (resultDiv) resultDiv.innerHTML = '<span class="text-yellow-700 text-sm">Kontrol s\u0131ras\u0131nda hata: ' + e.message + '</span>';
            if (cardDot) { cardDot.className = 'w-2.5 h-2.5 rounded-full bg-yellow-500'; cardDot.classList.remove('animate-pulse'); }
            if (cardLabel) cardLabel.textContent = 'Hata';
            if (cardSub) cardSub.textContent = (e.message || '').substring(0,30);
        })
        .finally(function() {
            if (btn) { btn.disabled = false; btn.textContent = 'Kontrol Et'; }
        });
    };

    window.switchMetaSubTab = function(tab) {
        document.querySelectorAll('.meta-sub-content').forEach(function(el) { el.classList.add('hidden'); });
        document.querySelectorAll('[id^="meta-subtab-"]').forEach(function(b) {
            b.classList.remove('bg-white', 'text-slate-900', 'shadow-sm');
            b.classList.add('text-slate-500');
        });
        var content = document.getElementById('meta-sub-' + tab);
        var tabBtn = document.getElementById('meta-subtab-' + tab);
        if (content) content.classList.remove('hidden');
        if (tabBtn) {
            tabBtn.classList.add('bg-white', 'text-slate-900', 'shadow-sm');
            tabBtn.classList.remove('text-slate-500');
        }
        if (tab === 'dashboard') { window.loadMetaDashboard(); window.loadMetaBusinessInfo(); _startMetaAutoRefresh(); }
        else if (tab === 'history') { window.loadMetaHistory(1); window.loadMetaTopRecipients(); }
        else { _stopMetaAutoRefresh(); }
    };

    /**
     * Canlı Meta Business bilgilerini (phone profili, WABA, şablonlar) API'den
     * çeker ve üst karta yazar. Hatalı durumda "Yapılandırma eksik" mesajı gösterir.
     */
    window.loadMetaBusinessInfo = function() {
        var loadEl   = document.getElementById('meta-business-info-loading');
        var errEl    = document.getElementById('meta-business-info-error');
        var boxEl    = document.getElementById('meta-business-info-content');
        if (!loadEl || !errEl || !boxEl) return;
        loadEl.classList.remove('hidden');
        errEl.classList.add('hidden');

        function qualityClass(q) {
            if (!q) return 'bg-slate-100 text-slate-600';
            var u = String(q).toUpperCase();
            if (u === 'GREEN' || u === 'HIGH') return 'bg-emerald-100 text-emerald-700';
            if (u === 'YELLOW' || u === 'MEDIUM') return 'bg-amber-100 text-amber-700';
            if (u === 'RED' || u === 'LOW') return 'bg-red-100 text-red-700';
            return 'bg-slate-100 text-slate-600';
        }

        function tplStatusClass(s) {
            s = String(s||'').toUpperCase();
            if (s === 'APPROVED') return 'bg-emerald-50 text-emerald-700 border-emerald-200';
            if (s === 'PENDING') return 'bg-amber-50 text-amber-700 border-amber-200';
            if (s === 'REJECTED' || s === 'PAUSED' || s === 'DISABLED') return 'bg-red-50 text-red-700 border-red-200';
            return 'bg-slate-50 text-slate-600 border-slate-200';
        }

        _fetchJson(_metaBaseUrl + _metaApiPrefix + '/meta/business-info')
            .then(function(json) {
                var info = (json && json.data) || {};
                if (!json || !json.success) {
                    loadEl.classList.add('hidden');
                    boxEl.classList.add('hidden');
                    errEl.textContent = (info && info.error) || (json && json.message) || 'Meta bilgileri al\u0131namad\u0131.';
                    errEl.classList.remove('hidden');
                    return;
                }

                var p = info.phone || {};
                var w = info.waba || {};
                var tpls = info.templates || [];

                var set = function(id, v) { var e = document.getElementById(id); if (e) e.textContent = (v === null || v === undefined || v === '') ? '\u2014' : v; };

                set('mbi-phone-number', p.display_phone_number || '\u2014');
                set('mbi-phone-verified', p.verified_name ? ('Do\u011frulanm\u0131\u015f ad: ' + p.verified_name) : 'Do\u011frulanmam\u0131\u015f');

                var q = document.getElementById('mbi-phone-quality');
                if (q) { q.textContent = 'Kalite: ' + (p.quality_rating || '-'); q.className = 'inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold ' + qualityClass(p.quality_rating); }
                var cv = document.getElementById('mbi-phone-verification');
                if (cv) { cv.textContent = 'Kod: ' + (p.code_verification_status || '-'); }
                var th = document.getElementById('mbi-phone-throughput');
                if (th) { th.textContent = 'Seviye: ' + (p.throughput || '-'); }

                set('mbi-waba-name', w.name || '\u2014');
                set('mbi-waba-owner', w.owner_business_name ? ('Sahip: ' + w.owner_business_name) : (w.country ? 'Ülke: ' + w.country : '\u2014'));
                var bv = document.getElementById('mbi-waba-verification');
                if (bv) { bv.textContent = 'Do\u011frulama: ' + (w.business_verification_status || '-'); bv.className = 'inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold ' + (String(w.business_verification_status||'').toLowerCase() === 'verified' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'); }
                set('mbi-waba-currency', 'PB: ' + (w.currency || '-'));
                set('mbi-waba-tz', 'TZ: ' + (w.timezone_id || '-'));

                set('mbi-tpl-count', tpls.length);
                var list = document.getElementById('mbi-tpl-list');
                if (list) {
                    list.innerHTML = tpls.slice(0, 30).map(function(t) {
                        return '<span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded border text-[9px] font-bold ' + tplStatusClass(t.status) + '" title="' + (t.language||'') + ' \u00b7 ' + (t.category||'') + '">' + (t.name||'?') + '</span>';
                    }).join('');
                }

                loadEl.classList.add('hidden');
                errEl.classList.add('hidden');
                boxEl.classList.remove('hidden');
            })
            .catch(function(e) {
                loadEl.classList.add('hidden');
                boxEl.classList.add('hidden');
                errEl.textContent = 'Meta bilgileri al\u0131namad\u0131: ' + (e && e.message ? e.message : e);
                errEl.classList.remove('hidden');
            });
    };

    function _startMetaAutoRefresh() {
        _stopMetaAutoRefresh();
        _metaDashboardInterval = setInterval(window.loadMetaDashboard, 30000);
    }
    function _stopMetaAutoRefresh() {
        if (_metaDashboardInterval) { clearInterval(_metaDashboardInterval); _metaDashboardInterval = null; }
    }

    function _formatNumber(n) {
        if (n >= 1000000) return (n/1000000).toFixed(1) + 'M';
        if (n >= 1000) return (n/1000).toFixed(1) + 'K';
        return String(n);
    }

    function _renderWeeklyChart(chart) {
        var container = document.getElementById('meta-weekly-chart');
        if (!container) return;
        var maxVal = Math.max.apply(null, chart.success.concat(chart.failed).concat([1]));
        var html = '';
        for (var i = 0; i < chart.labels.length; i++) {
            var s = chart.success[i] || 0;
            var f = chart.failed[i] || 0;
            var sH = Math.max((s / maxVal) * 100, 2);
            var fH = Math.max((f / maxVal) * 100, f > 0 ? 4 : 0);
            html += '<div class="flex-1 flex flex-col items-center gap-0.5">';
            html += '<div class="w-full flex flex-col items-center justify-end" style="height:100px">';
            if (f > 0) html += '<div class="w-full max-w-[24px] bg-red-400 rounded-t" style="height:' + fH + '%"></div>';
            html += '<div class="w-full max-w-[24px] bg-blue-500 rounded-t" style="height:' + sH + '%"></div>';
            html += '</div>';
            html += '<span class="text-[9px] text-slate-400 mt-1">' + chart.labels[i] + '</span>';
            html += '</div>';
        }
        if (chart.labels.length === 0) html = '<p class="text-xs text-slate-400 w-full text-center py-4">Veri yok</p>';
        container.innerHTML = html;
    }

    function _renderHourlyChart(chart) {
        var container = document.getElementById('meta-hourly-chart');
        if (!container) return;
        var counts = chart.counts || [];
        var maxVal = Math.max.apply(null, counts.concat([1]));
        var html = '';
        var now = new Date().getHours();
        for (var i = 0; i < 24; i++) {
            var c = counts[i] || 0;
            var h = Math.max((c / maxVal) * 100, 2);
            var color = i === now ? 'bg-indigo-500' : (c > 0 ? 'bg-blue-400' : 'bg-slate-200');
            html += '<div class="flex-1 flex flex-col items-center justify-end" style="height:100%">';
            html += '<div class="w-full ' + color + ' rounded-t transition-all" style="height:' + h + '%"></div>';
            if (i % 4 === 0) html += '<span class="text-[8px] text-slate-300 mt-0.5">' + (i < 10 ? '0' : '') + i + '</span>';
            html += '</div>';
        }
        container.innerHTML = html;
    }

    window.loadMetaDashboard = function() {
        _fetchJson(_metaBaseUrl + _metaApiPrefix + '/meta/dashboard-stats')
        .then(function(json) {
            if (!json.success || !json.data) return;
            var d = json.data;
            var t = d.today || {};
            var l = d.limits || {};
            var sr = d.success_rate || {};

            var el;
            el = document.getElementById('meta-daily-count'); if (el) el.textContent = t.total || 0;
            var lastAt = t.last_message_at ? new Date(t.last_message_at).toLocaleTimeString('tr-TR', {hour:'2-digit',minute:'2-digit'}) : 'hen\u00fcz yok';
            el = document.getElementById('meta-daily-sub'); if (el) el.textContent = 'son: ' + lastAt;
            el = document.getElementById('meta-daily-remaining'); if (el) el.textContent = l.daily_remaining != null ? l.daily_remaining : '-';
            el = document.getElementById('meta-daily-bar'); if (el) { el.style.width = Math.min(l.daily_percentage || 0, 100) + '%'; el.className = 'h-full rounded-full transition-all duration-700 ' + (l.daily_percentage > 90 ? 'bg-red-500' : l.daily_percentage > 70 ? 'bg-indigo-500' : 'bg-blue-500'); }
            el = document.getElementById('meta-daily-bar-label'); if (el) el.textContent = (l.daily_used||0) + ' / ' + (l.daily_limit||0);
            el = document.getElementById('meta-monthly-remaining'); if (el) el.textContent = l.monthly_remaining != null ? l.monthly_remaining : '-';
            el = document.getElementById('meta-monthly-bar'); if (el) { el.style.width = Math.min(l.monthly_percentage || 0, 100) + '%'; el.className = 'h-full rounded-full transition-all duration-700 ' + (l.monthly_percentage > 90 ? 'bg-red-500' : l.monthly_percentage > 70 ? 'bg-indigo-500' : 'bg-purple-500'); }
            el = document.getElementById('meta-monthly-bar-label'); if (el) el.textContent = (l.monthly_used||0) + ' / ' + (l.monthly_limit||0);
            var rateEl = document.getElementById('meta-success-rate');
            if (rateEl) { rateEl.textContent = (sr.rate || 0) + '%'; rateEl.className = 'text-2xl font-black mt-1 ' + (sr.rate >= 90 ? 'text-green-600' : sr.rate >= 70 ? 'text-indigo-600' : 'text-red-600'); }
            el = document.getElementById('meta-success-sub'); if (el) el.textContent = (sr.success||0) + '/' + (sr.total||0) + ' ba\u015far\u0131l\u0131';
            el = document.getElementById('meta-avg-response'); if (el) el.textContent = (t.avg_response_time || 0) + ' ms';
            el = document.getElementById('meta-failed-today'); if (el) el.textContent = t.failed || 0;
            el = document.getElementById('meta-total-alltime'); if (el) el.textContent = _formatNumber(d.total_all_time || 0);
            el = document.getElementById('meta-type-otp'); if (el) el.textContent = t.otp || 0;
            el = document.getElementById('meta-type-test'); if (el) el.textContent = t.test || 0;
            el = document.getElementById('meta-type-template'); if (el) el.textContent = t.template || 0;
            el = document.getElementById('meta-type-text'); if (el) el.textContent = (t.total||0) - (t.otp||0) - (t.test||0) - (t.template||0);
            el = document.getElementById('meta-last-refresh'); if (el) el.textContent = new Date().toLocaleTimeString('tr-TR', {hour:'2-digit',minute:'2-digit',second:'2-digit'});
            _renderWeeklyChart(d.weekly_chart || {labels:[],success:[],failed:[]});
            _renderHourlyChart(d.hourly_chart || {labels:[],counts:[]});
        })
        .catch(function(e) { console.error('Meta dashboard load error:', e); });
    };

    function _getHistoryFilters() {
        return {
            search: (document.getElementById('mh-filter-search') || {}).value || '',
            phone: (document.getElementById('mh-filter-phone') || {}).value || '',
            status: (document.getElementById('mh-filter-status') || {}).value || '',
            message_type: (document.getElementById('mh-filter-type') || {}).value || '',
            date_from: (document.getElementById('mh-filter-date-from') || {}).value || '',
            date_to: (document.getElementById('mh-filter-date-to') || {}).value || '',
            per_page: (document.getElementById('mh-filter-perpage') || {}).value || '20'
        };
    }

    function _buildFilterQuery(filters) {
        var parts = [];
        if (filters.search) parts.push('search=' + encodeURIComponent(filters.search));
        if (filters.phone) parts.push('phone=' + encodeURIComponent(filters.phone));
        if (filters.status) parts.push('status=' + encodeURIComponent(filters.status));
        if (filters.message_type) parts.push('message_type=' + encodeURIComponent(filters.message_type));
        if (filters.date_from) parts.push('date_from=' + encodeURIComponent(filters.date_from));
        if (filters.date_to) parts.push('date_to=' + encodeURIComponent(filters.date_to));
        if (filters.per_page && filters.per_page !== '20') parts.push('per_page=' + filters.per_page);
        return parts.join('&');
    }

    function _renderActiveFilters(filters) {
        var container = document.getElementById('mh-active-filters');
        if (!container) return;
        var labels = {
            search: 'Arama', phone: 'Telefon', status: 'Durum',
            message_type: 'Tip', date_from: 'Ba\u015flang\u0131\u00e7', date_to: 'Biti\u015f'
        };
        var statusLabels = {sent:'G\u00f6nderildi',delivered:'Teslim',read:'Okundu',failed:'Ba\u015far\u0131s\u0131z',pending:'Bekliyor'};
        var typeLabels = {otp:'OTP',test:'Test',template:'\u015eablon',text:'Metin',marketing:'Pazarlama',other:'Di\u011fer'};
        var html = '';
        var hasAny = false;
        ['search','phone','status','message_type','date_from','date_to'].forEach(function(key) {
            if (filters[key]) {
                hasAny = true;
                var displayVal = filters[key];
                if (key === 'status') displayVal = statusLabels[filters[key]] || filters[key];
                if (key === 'message_type') displayVal = typeLabels[filters[key]] || filters[key];
                html += '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 text-[10px] font-bold">';
                html += labels[key] + ': ' + displayVal;
                html += '<button type="button" onclick="metaHistoryClearFilter(\'' + key + '\')" class="hover:text-indigo-900">\u2715</button>';
                html += '</span>';
            }
        });
        container.innerHTML = html;
        container.classList.toggle('hidden', !hasAny);
    }

    window.metaHistoryResetFilters = function() {
        var ids = ['mh-filter-search','mh-filter-phone','mh-filter-status','mh-filter-type','mh-filter-date-from','mh-filter-date-to'];
        ids.forEach(function(id) { var el = document.getElementById(id); if (el) el.value = ''; });
        var pp = document.getElementById('mh-filter-perpage'); if (pp) pp.value = '20';
        loadMetaHistory(1);
    };

    window.metaHistoryClearFilter = function(key) {
        var map = {search:'mh-filter-search',phone:'mh-filter-phone',status:'mh-filter-status',message_type:'mh-filter-type',date_from:'mh-filter-date-from',date_to:'mh-filter-date-to'};
        var el = document.getElementById(map[key]);
        if (el) el.value = '';
        loadMetaHistory(1);
    };

    function _escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function _truncate(str, len) {
        if (!str) return '';
        return str.length > len ? str.substring(0, len) + '...' : str;
    }

    window.loadMetaHistory = function(page) {
        _metaCurrentHistoryPage = page || 1;
        var body = document.getElementById('meta-history-body');
        var pagination = document.getElementById('meta-history-pagination');
        if (!body) return;

        var filters = _getHistoryFilters();
        _renderActiveFilters(filters);

        body.innerHTML = '<tr><td colspan="6" class="py-6 text-center text-slate-400 text-xs">Y\u00fckleniyor...</td></tr>';

        var perPage = parseInt(filters.per_page) || 20;
        var url = _metaBaseUrl + _metaApiPrefix + '/meta/message-history?page=' + _metaCurrentHistoryPage + '&per_page=' + perPage;
        var filterQ = _buildFilterQuery(filters);
        if (filterQ) url += '&' + filterQ;

        _fetchJson(url)
        .then(function(json) {
            if (!json.success || !json.data) {
                body.innerHTML = '<tr><td colspan="6" class="py-8 text-center text-red-400 text-xs">' + (json.message || 'Veri al\u0131namad\u0131') + '</td></tr>';
                return;
            }
            var data = json.data;
            var countEl = document.getElementById('meta-history-count');
            if (countEl) {
                var filterActive = filters.search || filters.phone || filters.status || filters.message_type || filters.date_from || filters.date_to;
                countEl.textContent = (filterActive ? 'Filtrelenmi\u015f: ' : 'Toplam: ') + data.total + ' mesaj';
            }
            if (!data.messages || data.messages.length === 0) {
                var emptyMsg = (filters.search || filters.phone || filters.status || filters.message_type || filters.date_from || filters.date_to)
                    ? 'Bu filtrelere uygun mesaj bulunamad\u0131.'
                    : 'Hen\u00fcz mesaj ge\u00e7mi\u015fi yok.';
                body.innerHTML = '<tr><td colspan="6" class="py-8 text-center text-slate-400 text-xs">' + emptyMsg + '</td></tr>';
                if (pagination) pagination.innerHTML = '';
                return;
            }

            var statusMap = {sent:'bg-blue-100 text-blue-700',delivered:'bg-green-100 text-green-700',read:'bg-emerald-100 text-emerald-700',failed:'bg-red-100 text-red-700',pending:'bg-yellow-100 text-yellow-700'};
            var statusLabel = {sent:'G\u00f6nderildi',delivered:'Teslim',read:'Okundu',failed:'Ba\u015far\u0131s\u0131z',pending:'Bekliyor'};
            var statusIcon = {sent:'\u2713',delivered:'\u2713\u2713',read:'\u2713\u2713',failed:'\u2717',pending:'\u25cb'};
            var typeLabel = {otp:'OTP',test:'Test',template:'\u015eablon',text:'Metin',marketing:'Pazarlama',other:'Di\u011fer'};
            var typeColor = {otp:'bg-blue-100 text-blue-700',test:'bg-green-100 text-green-700',template:'bg-purple-100 text-purple-700',text:'bg-indigo-100 text-indigo-700',marketing:'bg-pink-100 text-pink-700',other:'bg-slate-100 text-slate-600'};

            var rows = '';
            data.messages.forEach(function(m, idx) {
                var date = new Date(m.created_at);
                var dateStr = date.toLocaleDateString('tr-TR', {day:'2-digit',month:'2-digit',year:'2-digit'});
                var timeStr = date.toLocaleTimeString('tr-TR', {hour:'2-digit',minute:'2-digit',second:'2-digit'});
                var phone = m.recipient_phone || '-';
                var st = m.status || 'pending';
                var cls = statusMap[st] || 'bg-slate-100 text-slate-700';
                var label = statusLabel[st] || st;
                var icon = statusIcon[st] || '';
                var tp = typeLabel[m.message_type] || m.message_type;
                var tpCls = typeColor[m.message_type] || typeColor.other;
                var rt = m.api_response_time_ms ? (m.api_response_time_ms + 'ms') : '-';
                var rowId = 'mh-row-' + (m.id || idx);

                var content = '';
                if (m.message_content) {
                    content = _escapeHtml(_truncate(m.message_content, 80));
                } else if (m.template_name) {
                    content = '<span class="text-purple-500">\u{1F4CB} ' + _escapeHtml(m.template_name) + '</span>';
                } else if (m.error_message && st === 'failed') {
                    content = '<span class="text-red-400">' + _escapeHtml(_truncate(m.error_message, 60)) + '</span>';
                } else {
                    content = '<span class="text-slate-300">\u2014</span>';
                }

                rows += '<tr id="' + rowId + '" class="border-b border-slate-50 hover:bg-slate-50/80 transition-colors cursor-pointer group" onclick="metaHistoryToggleDetail(\'' + rowId + '\')">';
                rows += '<td class="py-2 px-2 text-slate-600 whitespace-nowrap"><div class="leading-tight"><div class="font-semibold">' + dateStr + '</div><div class="text-[10px] text-slate-400">' + timeStr + '</div></div></td>';
                rows += '<td class="py-2 px-2"><span class="px-1.5 py-0.5 rounded text-[10px] font-bold ' + tpCls + '">' + tp + '</span></td>';
                rows += '<td class="py-2 px-2 font-mono text-slate-600 text-[11px]">' + _escapeHtml(phone) + '</td>';
                rows += '<td class="py-2 px-2 text-slate-600 max-w-[200px] lg:max-w-[350px] truncate">' + content + '</td>';
                rows += '<td class="py-2 px-2"><span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-bold ' + cls + '">' + icon + ' ' + label + '</span></td>';
                rows += '<td class="py-2 px-2 text-right text-slate-500">' + rt + '</td>';
                rows += '</tr>';

                var hasDetail = m.message_content || m.error_message || m.meta_message_id || m.template_name;
                if (hasDetail) {
                    rows += '<tr id="' + rowId + '-detail" class="hidden bg-slate-50/50">';
                    rows += '<td colspan="6" class="px-3 py-2">';
                    rows += '<div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-[10px]">';
                    if (m.message_content) {
                        rows += '<div class="sm:col-span-2"><span class="font-bold text-slate-500">Mesaj &#304;\u00e7eri\u011fi:</span><div class="mt-0.5 p-2 bg-white rounded border border-slate-200 text-slate-700 text-[11px] whitespace-pre-wrap break-words max-h-32 overflow-y-auto">' + _escapeHtml(m.message_content) + '</div></div>';
                    }
                    if (m.template_name) {
                        rows += '<div><span class="font-bold text-slate-500">\u015eablon:</span> <span class="text-purple-600">' + _escapeHtml(m.template_name) + '</span></div>';
                    }
                    if (m.meta_message_id) {
                        rows += '<div><span class="font-bold text-slate-500">Meta ID:</span> <span class="font-mono text-slate-500 break-all">' + _escapeHtml(m.meta_message_id) + '</span></div>';
                    }
                    if (m.error_message) {
                        rows += '<div class="sm:col-span-2"><span class="font-bold text-red-500">Hata:</span> <span class="text-red-500">' + _escapeHtml(m.error_message) + '</span></div>';
                    }
                    if (m.error_code) {
                        rows += '<div><span class="font-bold text-slate-500">Hata Kodu:</span> <span class="text-red-500">' + m.error_code + '</span></div>';
                    }
                    if (m.http_status_code) {
                        rows += '<div><span class="font-bold text-slate-500">HTTP:</span> <span class="' + (m.http_status_code >= 400 ? 'text-red-500' : 'text-green-600') + '">' + m.http_status_code + '</span></div>';
                    }
                    if (m.sent_by) {
                        rows += '<div><span class="font-bold text-slate-500">G\u00f6nderen:</span> ' + _escapeHtml(m.sent_by) + '</div>';
                    }
                    rows += '</div></td></tr>';
                }
            });
            body.innerHTML = rows;

            if (!pagination) return;
            _renderPagination(pagination, data.page, data.total_pages, data.total, perPage);
        })
        .catch(function(e) {
            body.innerHTML = '<tr><td colspan="6" class="py-6 text-center text-red-400 text-xs">Hata: ' + e.message + '</td></tr>';
        });
    };

    window.metaHistoryToggleDetail = function(rowId) {
        var detail = document.getElementById(rowId + '-detail');
        if (detail) detail.classList.toggle('hidden');
    };

    function _renderPagination(container, currentPage, totalPages, totalItems, perPage) {
        if (totalPages <= 1) {
            container.innerHTML = '<span class="text-[10px] text-slate-400">' + totalItems + ' mesaj</span>';
            return;
        }

        var html = '';

        var startItem = ((currentPage - 1) * perPage) + 1;
        var endItem = Math.min(currentPage * perPage, totalItems);
        html += '<span class="text-[10px] text-slate-400 order-2 sm:order-1">' + startItem + '-' + endItem + ' / ' + totalItems + ' mesaj \u2022 Sayfa ' + currentPage + '/' + totalPages + '</span>';

        html += '<div class="flex items-center gap-0.5 order-1 sm:order-2">';

        if (currentPage > 1) {
            html += '<button type="button" onclick="loadMetaHistory(1)" class="px-2 py-1 text-xs font-bold text-slate-500 hover:bg-slate-100 rounded" title="\u0130lk">&laquo;</button>';
            html += '<button type="button" onclick="loadMetaHistory(' + (currentPage - 1) + ')" class="px-2 py-1 text-xs font-bold text-slate-500 hover:bg-slate-100 rounded">&lsaquo;</button>';
        }

        var startPage, endPage;
        if (totalPages <= 7) {
            startPage = 1;
            endPage = totalPages;
        } else if (currentPage <= 4) {
            startPage = 1;
            endPage = 5;
        } else if (currentPage >= totalPages - 3) {
            startPage = totalPages - 4;
            endPage = totalPages;
        } else {
            startPage = currentPage - 2;
            endPage = currentPage + 2;
        }

        if (startPage > 1) {
            html += '<button type="button" onclick="loadMetaHistory(1)" class="px-2.5 py-1 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded">1</button>';
            if (startPage > 2) html += '<span class="text-xs text-slate-300 px-1">\u2026</span>';
        }

        for (var p = startPage; p <= endPage; p++) {
            if (p === currentPage) {
                html += '<button type="button" class="q-btn q-btn--ink q-btn--sm">' + p + '</button>';
            } else {
                html += '<button type="button" onclick="loadMetaHistory(' + p + ')" class="px-2.5 py-1 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded">' + p + '</button>';
            }
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) html += '<span class="text-xs text-slate-300 px-1">\u2026</span>';
            html += '<button type="button" onclick="loadMetaHistory(' + totalPages + ')" class="px-2.5 py-1 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded">' + totalPages + '</button>';
        }

        if (currentPage < totalPages) {
            html += '<button type="button" onclick="loadMetaHistory(' + (currentPage + 1) + ')" class="px-2 py-1 text-xs font-bold text-slate-500 hover:bg-slate-100 rounded">&rsaquo;</button>';
            html += '<button type="button" onclick="loadMetaHistory(' + totalPages + ')" class="px-2 py-1 text-xs font-bold text-slate-500 hover:bg-slate-100 rounded" title="Son">&raquo;</button>';
        }

        html += '</div>';

        html += '<div class="flex items-center gap-1 order-3">';
        html += '<span class="text-[10px] text-slate-400">Git:</span>';
        html += '<input type="number" min="1" max="' + totalPages + '" value="' + currentPage + '" class="w-12 px-1.5 py-0.5 text-[10px] border border-slate-200 rounded text-center focus:border-indigo-300 outline-none" onkeydown="if(event.key===\'Enter\'){var v=parseInt(this.value);if(v>=1&&v<=' + totalPages + ')loadMetaHistory(v);}" />';
        html += '</div>';

        container.innerHTML = html;
    }

    window.loadMetaTopRecipients = function() {
        var container = document.getElementById('meta-top-recipients');
        if (!container) return;
        _fetchJson(_metaBaseUrl + _metaApiPrefix + '/meta/top-recipients')
        .then(function(json) {
            if (!json.success || !json.data) return;
            if (json.data.length === 0) {
                container.innerHTML = '<p class="text-xs text-slate-400 py-4 text-center">Hen\u00fcz veri yok.</p>';
                return;
            }
            var maxCount = Math.max.apply(null, json.data.map(function(r) { return r.message_count; }).concat([1]));
            var html = '';
            json.data.forEach(function(r, idx) {
                var phone = r.recipient_phone ? (r.recipient_phone.substring(0,3) + '****' + r.recipient_phone.slice(-3)) : '-';
                var pct = (r.message_count / maxCount * 100);
                html += '<div class="flex items-center gap-3">';
                html += '<span class="text-[10px] font-bold text-slate-400 w-4 text-right">' + (idx+1) + '</span>';
                html += '<div class="flex-1 min-w-0">';
                html += '<div class="flex items-center justify-between mb-0.5">';
                html += '<span class="text-xs font-bold text-slate-700 font-mono">' + phone + '</span>';
                html += '<span class="text-[10px] text-slate-500">' + r.message_count + ' mesaj</span>';
                html += '</div>';
                html += '<div class="w-full h-1 bg-slate-100 rounded-full"><div class="h-full bg-blue-400 rounded-full" style="width:' + pct + '%"></div></div>';
                html += '</div></div>';
            });
            container.innerHTML = html;
        })
        .catch(function(e) {
            container.innerHTML = '<p class="text-xs text-red-400 py-4 text-center">Hata: ' + e.message + '</p>';
        });
    };

    var _origSwitchTab = window.switchTab;
    if (typeof _origSwitchTab === 'function') {
        window.switchTab = function(tab) {
            _origSwitchTab(tab);
            if (tab === 'meta') { window.loadMetaDashboard(); window.loadMetaBusinessInfo(); _startMetaAutoRefresh(); }
            else { _stopMetaAutoRefresh(); }
        };
    }
})();
</script>
<?php endif; /* end super-admin-only Meta JS */ ?>
