<?php
namespace App\Controllers\Admin;

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../../core/DependencyFactory.php';
// Traits are already included in Controller base class (HasPermissions, HandlesAPIResponse)
// HandlesFileUpload is not in base class, so we need to check if it's needed
require_once __DIR__ . '/../../core/Traits/HandlesFileUpload.php';

use App\Core\Controller;
use App\Core\Traits\HandlesFileUpload;

class SettingsController extends Controller {
    use HandlesFileUpload; // Only this trait is not in base class
    
    protected $settingsService;
    
    public function __construct() {
        parent::__construct();
        $this->settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
    }
    
    public function settings() {
        $this->requirePermission('settings.view');
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->requirePermission('settings.edit');
            
            try {
                $requestData = \App\Core\RequestParser::getRequestData();
            } catch (\Exception $e) {
                \App\Core\Logger::error('SettingsController: Failed to parse request data: ' . $e->getMessage());
                $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
                if ($isAjax) {
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 400);
                } else {
                    $this->toastNotificationService->setFlash('error', 'notifications.error.invalid_request');
                    header('Location: ' . BASE_URL . '/qodmin/settings');
                }
                return;
            }
            
            $settingsData = [];
            
            // Process all settings fields (simplified - full implementation in original)
            $allowedSettings = [
                'site_name', 'service_charge_rate', 'cover_charge', 'currency',
                'order_id_prefix', 'order_number_length',
                'app_env', 'app_debug', 'timezone', 'default_language', 'session_timeout',
                'max_upload_size', 'smtp_host', 'smtp_port', 'smtp_encryption',
                'smtp_username', 'smtp_password', 'smtp_from_name',
                'wifi_name', 'wifi_password', 'wifi_show_to_customer', 'ai_customer_recommendations_enabled',
                'order_edit_requires_approval', 'order_edit_approval_role',
                'gemini_api_key', 'opusmax_api_key', 'meta_app_id', 'meta_app_secret', 'meta_access_token', 'meta_webhook_verify_token', 'meta_phone_number_id', 'meta_whatsapp_business_account_id',
                'meta_queue_template_name',
                // meta_queue_messaging_enabled is a checkbox; handled via the
                // generic checkbox-normalization block further down so that an
                // unchecked box is persisted as '0' instead of being ignored.
                'business_latitude', 'business_longitude', 'business_radius', 'business_address',
                'payment_bank_transfer_enabled',
                'welcome_email_enabled', 'welcome_whatsapp_enabled',
                // Two-factor authentication global method toggles (superadmin)
                'auth_2fa_totp_enabled',
                'auth_2fa_whatsapp_enabled',
                'auth_2fa_email_enabled',
                'auth_2fa_sms_enabled',
            ];
            
            foreach ($allowedSettings as $key) {
                if (isset($requestData[$key])) {
                    $value = $requestData[$key];
                    // Skip arrays - they should be handled separately
                    if (is_array($value)) {
                        continue;
                    }
                    if (in_array($key, ['service_charge_rate', 'cover_charge'])) {
                        $settingsData[$key] = floatval($value);
                    } elseif (in_array($key, ['order_number_length', 'smtp_port', 'session_timeout', 'max_upload_size'])) {
                        $settingsData[$key] = intval($value);
                    } else {
                        // Convert to string before sanitizing
                        $settingsData[$key] = sanitizeInput(is_string($value) ? $value : (string)$value);
                    }
                }
            }
            
            // Handle order edit approval settings
            if (isset($requestData['order_edit_requires_approval'])) {
                $settingsData['order_edit_requires_approval'] = '1';
            } else {
                $settingsData['order_edit_requires_approval'] = '0';
            }
            // Havale ile ödeme (süper admin)
            $settingsData['payment_bank_transfer_enabled'] = (isset($requestData['payment_bank_transfer_enabled']) && $requestData['payment_bank_transfer_enabled'] === '1') ? '1' : '0';

            // Platform-geneli Meta sıra mesajı özelliği (süper admin).
            // Yalnızca formun gerçekten bu bölümü gönderdiği isteklerde kaydedilsin
            // (diğer sekmelerden gelen kayıtlar bu ayarı yanlışlıkla '0' yapmasın).
            if (array_key_exists('meta_queue_messaging_enabled', $requestData) || array_key_exists('meta_platform_section', $requestData)) {
                $settingsData['meta_queue_messaging_enabled'] = (isset($requestData['meta_queue_messaging_enabled']) && $requestData['meta_queue_messaging_enabled'] === '1') ? '1' : '0';
            }

            // Welcome notification toggles (super admin). Only persist when
            // the current page actually submitted one of the toggles so we
            // don't accidentally overwrite them from unrelated settings
            // sections.
            if (array_key_exists('welcome_email_enabled', $requestData) || array_key_exists('welcome_notifications_section', $requestData)) {
                $settingsData['welcome_email_enabled'] = (isset($requestData['welcome_email_enabled']) && $requestData['welcome_email_enabled'] === '1') ? '1' : '0';
            }
            if (array_key_exists('welcome_whatsapp_enabled', $requestData) || array_key_exists('welcome_notifications_section', $requestData)) {
                $settingsData['welcome_whatsapp_enabled'] = (isset($requestData['welcome_whatsapp_enabled']) && $requestData['welcome_whatsapp_enabled'] === '1') ? '1' : '0';
            }

            // 2FA method toggles (superadmin). Only persist when the 2FA section
            // is actually being submitted, so unrelated forms don't overwrite them.
            if (array_key_exists('auth_2fa_section', $requestData)) {
                $settingsData['auth_2fa_totp_enabled']     = (isset($requestData['auth_2fa_totp_enabled']) && $requestData['auth_2fa_totp_enabled'] === '1') ? '1' : '0';
                $settingsData['auth_2fa_whatsapp_enabled'] = (isset($requestData['auth_2fa_whatsapp_enabled']) && $requestData['auth_2fa_whatsapp_enabled'] === '1') ? '1' : '0';
                $settingsData['auth_2fa_email_enabled']    = (isset($requestData['auth_2fa_email_enabled']) && $requestData['auth_2fa_email_enabled'] === '1') ? '1' : '0';
                $settingsData['auth_2fa_sms_enabled']      = (isset($requestData['auth_2fa_sms_enabled']) && $requestData['auth_2fa_sms_enabled'] === '1') ? '1' : '0';
            }
            
            if (isset($requestData['order_edit_approval_role'])) {
                $roleValue = $requestData['order_edit_approval_role'];
                if (!is_array($roleValue)) {
                    $settingsData['order_edit_approval_role'] = sanitizeInput(is_string($roleValue) ? $roleValue : (string)$roleValue);
                }
            }
            
            // Handle supported_languages array
            if (isset($requestData['supported_languages']) && is_array($requestData['supported_languages'])) {
                if (count($requestData['supported_languages']) > 0) {
                    $settingsData['supported_languages'] = json_encode($requestData['supported_languages']);
                } else {
                    $defaultLang = $requestData['default_language'] ?? 'tr';
                    $settingsData['supported_languages'] = json_encode([$defaultLang]);
                }
            }
            
            // Handle language switcher and auto detect
            $settingsData['language_switcher_enabled'] = isset($requestData['language_switcher_enabled']) ? '1' : '0';
            $settingsData['auto_detect_language'] = isset($requestData['auto_detect_language']) ? '1' : '0';
            
            // Handle WiFi show to customer setting
            $settingsData['wifi_show_to_customer'] = isset($requestData['wifi_show_to_customer']) ? '1' : '0';
            
            $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                      strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
            
            if (empty($settingsData)) {
                if ($isAjax) {
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.not_found', [], 400);
                    return;
                }
                $this->toastNotificationService->setFlash('error', 'notifications.error.not_found');
                header('Location: ' . BASE_URL . '/qodmin/settings');
                exit;
            }
            
            try {
                $result = $this->settingsService->updateSettings($settingsData);
                
                // Reload EmailService driver if SMTP settings were updated
                if ($result && (isset($settingsData['smtp_host']) || isset($settingsData['smtp_username']) || isset($settingsData['smtp_password']))) {
                    try {
                        $emailService = \App\Core\DependencyFactory::getEmailService();
                        $emailService->reloadDriver();
                    } catch (\Exception $e) {
                        \App\Core\Logger::error('Failed to reload email driver: ' . $e->getMessage());
                    }
                }
                
                if ($isAjax) {
                    if ($result) {
                        $this->toastNotificationService->sendApiResponse('success', 'notifications.success.settings_updated', [], 200);
                    } else {
                        $errMsg = \App\Services\SystemSettingsService::getLastUpdateError();
                        $hint = (stripos($errMsg ?? '', 'sütun') !== false || stripos($errMsg ?? '', 'column') !== false)
                            ? ' (Eksik sütunlar otomatik eklenir; sorun sürerse: php app/migrations/20260228_add_payment_bank_transfer_enabled.php ve php app/migrations/20260228_add_meta_api_columns_to_system_settings.php)'
                            : '';
                        \App\Core\Logger::error('Settings update failed. Data: ' . json_encode($settingsData) . ($errMsg ? ' Error: ' . $errMsg : ''));
                        $displayError = ($errMsg ?? 'Ayarlar güncellenirken bir hata oluştu') . $hint;
                        $this->toastNotificationService->sendApiResponse('error', $displayError, [], 500, [
                            'error' => $displayError
                        ]);
                    }
                    return;
                }
            } catch (\Exception $e) {
                \App\Core\Logger::error('Settings update exception: ' . $e->getMessage());
                
                if ($isAjax) {
                    $hint = (stripos($e->getMessage(), 'Unknown column') !== false || stripos($e->getMessage(), 'meta_') !== false || stripos($e->getMessage(), 'payment_bank_transfer') !== false)
                        ? ' (php app/migrations/20260228_add_payment_bank_transfer_enabled.php veya php app/migrations/20260228_add_meta_api_columns_to_system_settings.php)'
                        : '';
                    $displayError = $e->getMessage() . $hint;
                    $this->toastNotificationService->sendApiResponse('error', $displayError, [], 500, [
                        'error' => $displayError
                    ]);
                    return;
                }
                
                $this->toastNotificationService->setFlash('error', 'notifications.error.settings_update_failed', ['error' => $e->getMessage()]);
                header('Location: ' . BASE_URL . '/qodmin/settings');
                exit;
            }
            
            if ($result) {
                $this->toastNotificationService->setFlash('success', 'notifications.success.settings_updated');
            } else {
                $this->toastNotificationService->setFlash('error', 'notifications.error.settings_update_failed');
            }

            // Preserve the origin path — qodmin/business/admin — so the
            // user lands back on the same settings screen they submitted
            // from, instead of being bounced to /admin/settings.
            $origin = $_SERVER['HTTP_REFERER'] ?? '';
            $redirectPath = '/admin/settings';
            if ($origin !== '' && preg_match('#/(qodmin|business|admin)/settings#', $origin, $mm)) {
                $redirectPath = '/' . $mm[1] . '/settings';
            }
            header('Location: ' . BASE_URL . $redirectPath);
            exit;
        }
        
        // Get language statistics
        $translationService = \App\Core\DependencyFactory::getTranslationService();
        $languageStats = $translationService->getLanguageStatistics();
        
        // Get roles for order edit approval dropdown
        $roleService = \App\Core\DependencyFactory::getRoleService();
        $allRoles = $roleService->getActiveRoles();
        
        // Super-admin /qodmin/settings sayfası platform ayarlarını (tenant_id IS NULL)
        // gösterir; tenant bağlamı varsa bile bir işletmenin satırına düşmemelidir.
        // Regular admin ise normal (tenant-aware) akışı kullanır.
        $isSuperAdmin = $this->isSuperAdmin();
        if ($isSuperAdmin && method_exists($this->settingsService, 'getPlatformSettings')) {
            $platform = $this->settingsService->getPlatformSettings();
            $settings = !empty($platform) ? $platform : $this->settingsService->getAllSettings();
        } else {
            $settings = $this->settingsService->getAllSettings();
        }
        
        // Auto-generate Meta webhook verify token if empty (sabit kalır)
        $verifyToken = $settings['meta_webhook_verify_token'] ?? '';
        if (empty(trim($verifyToken))) {
            $newToken = 'qordy_meta_' . bin2hex(random_bytes(12));
            $this->settingsService->setSetting('meta_webhook_verify_token', $newToken);
            $settings['meta_webhook_verify_token'] = $newToken;
        }
        
        $data = [
            'settings' => $settings,
            'page' => 'settings',
            'languageStats' => $languageStats,
            'allRoles' => $allRoles,
            // Pass super-admin flag so the view can render super-admin-only
            // sections (Meta API tab, Payment, 2FA, welcome notifications, etc.).
            // Without this, $isSuperAdmin defaults to false and the Meta API
            // tab disappears from /qodmin/settings even for super admins.
            'is_super_admin' => $isSuperAdmin,
        ];
        
        $this->view('admin/settings', $data);
    }
    
    /**
     * Upload logo using trait
     */
    public function uploadLogo() {
        $this->requirePermission('settings.edit');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['logo'])) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 400);
            return;
        }
        
        // Use trait method via protected call
        $result = $this->uploadImage('logo', __DIR__ . '/../../public/assets/images/', 'logo', 5242880);
        
        if ($result['success']) {
            $this->settingsService->setSetting('logo_url', $result['url']);
            $this->toastNotificationService->sendApiResponse('success', 'notifications.success.logo_uploaded', ['url' => $result['url']], 200);
        } else {
            $errorMsg = $result['error'] === 'Invalid file type' ? 'notifications.error.invalid_file_type' : 
                       ($result['error'] === 'File too large' ? 'notifications.error.file_too_large' : 'notifications.error.logo_upload_failed');
            $this->toastNotificationService->sendApiResponse('error', $errorMsg, [], 400);
        }
    }
    
    /**
     * Upload favicon using trait
     */
    public function uploadFavicon() {
        $this->requirePermission('settings.edit');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['favicon'])) {
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.invalid_request', [], 400);
            return;
        }
        
        $allowedTypes = ['image/x-icon', 'image/vnd.microsoft.icon', 'image/png', 'image/svg+xml'];
        $result = $this->uploadFile('favicon', $allowedTypes, 1048576, __DIR__ . '/../../public/assets/images/', 'favicon');
        
        if ($result['success']) {
            $this->settingsService->setSetting('favicon_url', $result['url']);
            $this->toastNotificationService->sendApiResponse('success', 'notifications.success.favicon_uploaded', ['url' => $result['url']], 200);
        } else {
            $errorMsg = $result['error'] === 'Invalid file type' ? 'notifications.error.invalid_file_type' : 
                       ($result['error'] === 'File too large' ? 'notifications.error.file_too_large' : 'notifications.error.favicon_upload_failed');
            $this->toastNotificationService->sendApiResponse('error', $errorMsg, [], 400);
        }
    }
    
    /**
     * Test email settings
     */
    public function testEmail() {
        $this->requirePermission('settings.edit');
        
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $requestData = \App\Core\RequestParser::getRequestData();
            $emailValue = $requestData['email'] ?? '';
            $email = sanitizeInput(is_string($emailValue) ? $emailValue : (string)$emailValue);
            
            if (empty($email)) {
                $this->toastNotificationService->sendApiResponse('error', 'notifications.warning.missing_fields', [], 400);
                return;
            }
            
            try {
                $emailService = \App\Core\DependencyFactory::getEmailService();
                $result = $emailService->testEmail($email);
                
                if ($result['success'] ?? false) {
                    $this->toastNotificationService->sendApiResponse('success', 'notifications.success.email_sent', [], 200);
                } else {
                    $this->toastNotificationService->sendApiResponse('error', 'notifications.error.email_send_failed', [], 500);
                }
            } catch (\Exception $e) {
                \App\Core\Logger::error('Test email failed: ' . $e->getMessage());
                $this->toastNotificationService->sendApiResponse('error', 'notifications.error.email_send_failed', [], 500);
            }
        }
    }
    
    /**
     * Get email status
     */
    public function getEmailStatus() {
        $this->requirePermission('settings.view');
        
        try {
            $emailService = \App\Core\DependencyFactory::getEmailService();
            $status = $emailService->getStatus();
            
            $this->apiResponse([
                'success' => true,
                'status' => $status
            ]);
        } catch (\Exception $e) {
            \App\Core\Logger::error('Get email status failed: ' . $e->getMessage());
            $this->toastNotificationService->sendApiResponse('error', 'notifications.error.email_status_failed', [], 500);
        }
    }
    
    /**
     * Debug Meta access token - calls Meta debug_token endpoint
     */
    public function debugMetaToken() {
        $this->requirePermission('settings.view');
        
        $token = trim($this->settingsService->getSetting('meta_access_token') ?? '');
        $phoneNumberId = trim($this->settingsService->getSetting('meta_phone_number_id') ?? '');
        
        if (empty($token)) {
            $this->apiResponse(['success' => false, 'message' => 'Access token ayarlanmamış.']);
            return;
        }
        
        $result = [
            'token_set' => true,
            'token_prefix' => substr($token, 0, 12) . '...',
            'phone_number_id' => $phoneNumberId ?: '(boş)',
        ];
        
        $debugUrl = 'https://graph.facebook.com/v21.0/debug_token?input_token=' . urlencode($token) . '&access_token=' . urlencode($token);
        $ch = curl_init($debugUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $resp = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($resp === false || $httpCode !== 200) {
            $result['debug_error'] = 'Meta API\'ye bağlanılamadı (HTTP ' . $httpCode . ')';
            $this->apiResponse(['success' => false, 'data' => $result, 'message' => $result['debug_error']]);
            return;
        }
        
        $data = json_decode($resp, true);
        $tokenData = $data['data'] ?? [];
        
        $result['is_valid'] = $tokenData['is_valid'] ?? false;
        $result['app_name'] = $tokenData['application'] ?? '(bilinmiyor)';
        $result['app_id'] = $tokenData['app_id'] ?? '';
        $result['type'] = $tokenData['type'] ?? '';
        $result['scopes'] = $tokenData['scopes'] ?? [];
        
        $hasWhatsAppScope = false;
        foreach ($result['scopes'] as $scope) {
            if (stripos($scope, 'whatsapp') !== false) {
                $hasWhatsAppScope = true;
                break;
            }
        }
        $result['has_whatsapp_scope'] = $hasWhatsAppScope;
        
        if (isset($tokenData['expires_at']) && $tokenData['expires_at'] > 0) {
            $result['expires_at'] = date('d.m.Y H:i', $tokenData['expires_at']);
            $result['expires_in_hours'] = round(($tokenData['expires_at'] - time()) / 3600, 1);
            $result['is_expired'] = time() > $tokenData['expires_at'];
        } else {
            $result['expires_at'] = 'Süresiz (kalıcı)';
            $result['expires_in_hours'] = null;
            $result['is_expired'] = false;
        }
        
        $issues = [];
        $warnings = [];
        if (!$result['is_valid']) {
            $issues[] = 'Token geçersiz veya süresi dolmuş.';
        }
        if ($result['is_expired']) {
            $issues[] = 'Token süresi dolmuş (' . $result['expires_at'] . ').';
        }
        if (!$hasWhatsAppScope) {
            $issues[] = 'WhatsApp izni yok. Token oluştururken whatsapp_business_messaging iznini eklemelisiniz.';
        }
        if ($result['type'] === 'USER') {
            $warnings[] = 'Token tipi USER. Kalıcı token için: Meta Business Suite > System Users > Generate Token > "Never expires" seçin.';
        }
        if (isset($result['expires_in_hours']) && $result['expires_in_hours'] !== null) {
            if ($result['expires_in_hours'] < 24 && $result['expires_in_hours'] > 0) {
                $issues[] = 'Token ' . $result['expires_in_hours'] . ' saat içinde dolacak!';
            } elseif ($result['expires_in_hours'] > 0) {
                $days = round($result['expires_in_hours'] / 24);
                $warnings[] = 'Token ' . $days . ' gün sonra (' . $result['expires_at'] . ') dolacak.';
            }
        }
        
        $result['issues'] = $issues;
        $result['warnings'] = $warnings;
        $result['status'] = !empty($issues) ? 'error' : (!empty($warnings) ? 'warning' : 'ok');
        
        $message = 'Token geçerli ve hazır.';
        if (!empty($issues)) {
            $message = implode(' ', $issues);
        } elseif (!empty($warnings)) {
            $message = 'Token çalışıyor. ' . implode(' ', $warnings);
        }
        
        $this->apiResponse([
            'success' => true,
            'data' => $result,
            'message' => $message
        ]);
    }
    
    /**
     * Send test WhatsApp message
     */
    public function testWhatsApp() {
        $this->requirePermission('settings.edit');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->apiResponse(['success' => false, 'message' => 'POST required'], 405);
            return;
        }
        
        $requestData = \App\Core\RequestParser::getRequestData();
        $phone = preg_replace('/\D/', '', $requestData['phone'] ?? '');
        
        if (empty($phone) || strlen($phone) < 10) {
            $this->apiResponse(['success' => false, 'message' => 'Geçerli bir telefon numarası girin (ülke kodu ile, ör: 905321234567).']);
            return;
        }
        
        $token = trim($this->settingsService->getSetting('meta_access_token') ?? '');
        $phoneNumberId = trim($this->settingsService->getSetting('meta_phone_number_id') ?? '');
        
        if (empty($token) || empty($phoneNumberId)) {
            $this->apiResponse(['success' => false, 'message' => 'Access Token ve Phone Number ID ayarlanmamış.']);
            return;
        }
        
        $url = 'https://graph.facebook.com/v21.0/' . $phoneNumberId . '/messages';
        $testMessageBody = 'Qordy WhatsApp test mesaji - ' . date('d.m.Y H:i:s');
        $payload = json_encode([
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $phone,
            'type' => 'text',
            'text' => ['body' => $testMessageBody]
        ]);
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $startTime = microtime(true);
        $resp = curl_exec($ch);
        $curlErr = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $responseTimeMs = (int)((microtime(true) - $startTime) * 1000);
        curl_close($ch);
        
        $logService = \App\Core\DependencyFactory::getWhatsAppMessageLogService();
        
        if ($resp === false) {
            $logService->logMessage([
                'message_type' => 'test', 'recipient_phone' => $phone,
                'message_content' => $testMessageBody,
                'status' => 'failed', 'http_status_code' => $httpCode,
                'api_response_time_ms' => $responseTimeMs,
                'error_message' => 'CURL: ' . $curlErr,
            ]);
            $this->apiResponse(['success' => false, 'message' => 'Meta API bağlantı hatası: ' . $curlErr]);
            return;
        }
        
        $data = json_decode($resp, true) ?? [];
        
        if ($httpCode >= 200 && $httpCode < 300) {
            $msgId = $data['messages'][0]['id'] ?? '';
            $logService->logMessage([
                'message_type' => 'test', 'recipient_phone' => $phone,
                'message_content' => $testMessageBody,
                'meta_message_id' => $msgId, 'status' => 'sent',
                'http_status_code' => $httpCode, 'api_response_time_ms' => $responseTimeMs,
            ]);
            $this->apiResponse(['success' => true, 'message' => 'Test mesajı gönderildi!' . ($msgId ? ' (ID: ' . substr($msgId, 0, 20) . '...)' : ''), 'data' => $data]);
            return;
        }
        
        $errMsg = $data['error']['message'] ?? 'Bilinmeyen hata';
        $errCode = $data['error']['code'] ?? 0;
        $subCode = $data['error']['error_subcode'] ?? 0;
        
        $hint = '';
        if ($errCode === 190) {
            $hint = ' → Token süresi dolmuş veya geçersiz. Yeni bir System User token alın.';
        } elseif ($errCode === 100 && $subCode === 33) {
            $hint = ' → Phone Number ID yanlış veya token bu numaraya erişemiyor. Token\'ın doğru uygulamaya ait olduğundan emin olun.';
        } elseif ($errCode === 10) {
            $hint = ' → Token\'da gerekli WhatsApp izinleri yok. System User token oluştururken whatsapp_business_messaging iznini ekleyin.';
        } elseif ($errCode === 131030 || $errCode === 131047) {
            $hint = ' → Mesaj şablonu onaylanmamış.';
        }
        
        $logService->logMessage([
            'message_type' => 'test', 'recipient_phone' => $phone,
            'message_content' => $testMessageBody,
            'status' => 'failed', 'http_status_code' => $httpCode,
            'api_response_time_ms' => $responseTimeMs,
            'error_code' => $errCode, 'error_message' => $errMsg,
        ]);
        
        $this->apiResponse([
            'success' => false,
            'message' => "Meta API Hatası (HTTP {$httpCode}, Code {$errCode}): {$errMsg}{$hint}",
            'data' => $data
        ]);
    }

    /**
     * Canlı Meta Business API bilgilerini (phone profili, WABA, onaylı template'ler)
     * tek çağrıda döner. Settings → Meta sekmesindeki "Canlı Bilgiler" kartı kullanır.
     */
    public function getMetaBusinessInfo() {
        $this->requirePermission('settings.view');
        try {
            $wa = \App\Core\DependencyFactory::getWhatsAppService();
            $info = $wa->fetchBusinessInfo();
            $this->apiResponse(['success' => (bool)($info['success'] ?? false), 'data' => $info]);
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Meta business info error: ' . $e->getMessage());
            $this->apiResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get Meta WhatsApp dashboard stats (live data)
     */
    public function getMetaDashboardStats() {
        $this->requirePermission('settings.view');
        
        try {
            $logService = \App\Core\DependencyFactory::getWhatsAppMessageLogService();
            $businessId = $_SESSION['business_id'] ?? null;
            $stats = $logService->getDashboardStats($businessId);
            
            $this->apiResponse(['success' => true, 'data' => $stats]);
        } catch (\Exception $e) {
            \App\Core\Logger::error('Meta dashboard stats error: ' . $e->getMessage());
            $this->apiResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get WhatsApp message history (paginated)
     */
    public function getMetaMessageHistory() {
        $this->requirePermission('settings.view');
        
        try {
            $logService = \App\Core\DependencyFactory::getWhatsAppMessageLogService();
            $businessId = $_SESSION['business_id'] ?? null;
            $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $perPage = isset($_GET['per_page']) ? min(100, max(5, (int)$_GET['per_page'])) : 20;
            
            $filters = [];
            if (!empty($_GET['phone'])) {
                $filters['phone'] = preg_replace('/[^0-9+]/', '', $_GET['phone']);
            }
            if (!empty($_GET['status']) && in_array($_GET['status'], ['sent', 'delivered', 'read', 'failed', 'pending'])) {
                $filters['status'] = $_GET['status'];
            }
            if (!empty($_GET['message_type']) && in_array($_GET['message_type'], ['otp', 'test', 'template', 'text', 'marketing', 'other'])) {
                $filters['message_type'] = $_GET['message_type'];
            }
            if (!empty($_GET['date_from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_from'])) {
                $filters['date_from'] = $_GET['date_from'];
            }
            if (!empty($_GET['date_to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_to'])) {
                $filters['date_to'] = $_GET['date_to'];
            }
            if (!empty($_GET['search'])) {
                $filters['search'] = substr(trim($_GET['search']), 0, 200);
            }
            
            $history = $logService->getMessageHistory($businessId, $page, $perPage, $filters);
            $this->apiResponse(['success' => true, 'data' => $history]);
        } catch (\Exception $e) {
            \App\Core\Logger::error('Meta message history error: ' . $e->getMessage());
            $this->apiResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get top WhatsApp recipients
     */
    public function getMetaTopRecipients() {
        $this->requirePermission('settings.view');
        
        try {
            $logService = \App\Core\DependencyFactory::getWhatsAppMessageLogService();
            $businessId = $_SESSION['business_id'] ?? null;
            $recipients = $logService->getTopRecipients($businessId);
            
            $this->apiResponse(['success' => true, 'data' => $recipients]);
        } catch (\Exception $e) {
            \App\Core\Logger::error('Meta top recipients error: ' . $e->getMessage());
            $this->apiResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}

