<?php
namespace App\Services;

/**
 * WhatsApp Service - Meta WhatsApp Business API (Cloud API)
 * Sends OTP/verification codes via WhatsApp
 * @see https://developers.facebook.com/docs/whatsapp/cloud-api
 */
class WhatsAppService
{
    private $settingsService;
    private const API_VERSION = 'v21.0';
    private const BASE_URL = 'https://graph.facebook.com';
    private const AUTH_TEMPLATE_NAME = 'qordy_dogrulama';
    private const WELCOME_TEMPLATE_NAME = 'qordy_hosgeldin';

    public function __construct()
    {
        require_once __DIR__ . '/../core/DependencyFactory.php';
        $this->settingsService = \App\Core\DependencyFactory::getSystemSettingsService();
    }

    /**
     * Send OTP code via WhatsApp
     * @param string $phoneNumber Full phone with country code (e.g. 905321234567 or +905321234567)
     * @param string $code 6-digit verification code
     * @return array ['success' => bool, 'message' => string]
     */
    public function sendVerificationCode(string $phoneNumber, string $code): array
    {
        $accessToken = trim($this->settingsService->getSetting('meta_access_token', ''));
        $phoneNumberId = trim($this->settingsService->getSetting('meta_phone_number_id', ''));

        if (empty($accessToken) || empty($phoneNumberId)) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('WhatsAppService: Meta API not configured', [
                    'has_token' => !empty($accessToken),
                    'has_phone_id' => !empty($phoneNumberId)
                ]);
            }
            return [
                'success' => false,
                'message' => 'WhatsApp servisi şu an yapılandırılmamış. Lütfen qodmin/settings > Meta API bölümünü kontrol edin.'
            ];
        }

        $phone = preg_replace('/\D/', '', $phoneNumber);
        if (strlen($phone) < 10) {
            return ['success' => false, 'message' => 'Geçersiz telefon numarası'];
        }

        $url = self::BASE_URL . '/' . self::API_VERSION . '/' . $phoneNumberId . '/messages';

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $phone,
            'type' => 'template',
            'template' => [
                'name' => self::AUTH_TEMPLATE_NAME,
                'language' => ['code' => 'tr'],
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => (string)$code]
                        ]
                    ],
                    [
                        'type' => 'button',
                        'sub_type' => 'url',
                        'index' => 0,
                        'parameters' => [
                            ['type' => 'text', 'text' => (string)$code]
                        ]
                    ]
                ]
            ]
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_ENCODING => ''
        ]);

        $startTime = microtime(true);
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $responseTimeMs = (int)((microtime(true) - $startTime) * 1000);
        curl_close($ch);

        if ($response === false) {
            $errMsg = $curlError ?: 'Bağlantı hatası';
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::error('WhatsAppService: CURL failed', ['error' => $errMsg]);
            }
            $this->logMessageToDb($phone, 'otp', 'failed', null, $httpCode, $responseTimeMs, null, $errMsg, null, 'OTP doğrulama kodu');
            return ['success' => false, 'message' => 'WhatsApp sunucusuna bağlanılamadı: ' . $errMsg];
        }

        $data = [];
        if (is_string($response) && $response !== '') {
            $decoded = json_decode($response, true);
            $data = is_array($decoded) ? $decoded : [];
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            $metaMsgId = $data['messages'][0]['id'] ?? null;
            $this->logMessageToDb($phone, 'otp', 'sent', $metaMsgId, $httpCode, $responseTimeMs, self::AUTH_TEMPLATE_NAME, null, null, 'OTP doğrulama kodu (şablon: ' . self::AUTH_TEMPLATE_NAME . ')');
            return ['success' => true, 'message' => 'Doğrulama kodu WhatsApp ile gönderildi'];
        }

        $errorMessage = 'WhatsApp mesajı gönderilemedi';
        if (isset($data['error']['message'])) {
            $errorMessage = $data['error']['message'];
            $errorCode = $data['error']['code'] ?? null;
            $subCode = $data['error']['error_subcode'] ?? null;
            if ($errorCode === 190) {
                if (strpos($errorMessage, 'expired') !== false || $subCode === 463) {
                    $errorMessage = 'Access token süresi dolmuş. Meta Business Suite > System Users > Generate Token ile kalıcı token alın.';
                } elseif (strpos($errorMessage, 'invalid') !== false) {
                    $errorMessage = 'Geçersiz access token. Ayarlardaki token\'ı kontrol edin.';
                }
            } elseif ($errorCode === 100 && $subCode === 33) {
                $errorMessage = 'Phone Number ID bulunamadı veya token bu numaraya erişemiyor. Token\'ın doğru Meta uygulamasına ait olduğunu ve whatsapp_business_messaging izninin verildiğini kontrol edin.';
            } elseif ($errorCode === 10) {
                $errorMessage = 'Token\'da WhatsApp izni yok. System User token oluştururken whatsapp_business_messaging iznini eklemelisiniz.';
            } elseif ($errorCode === 131030 || $errorCode === 131047) {
                $errorMessage = 'WhatsApp mesaj şablonu onaylanmamış. Meta Business Suite > WhatsApp > Message Templates bölümünde şablonu onaylayın.';
            } elseif ($errorCode === 131031) {
                $errorMessage = 'Telefon numarası formatı hatalı. Ülke kodu ile birlikte girin (örn: 905321234567).';
            }
        }

        if (class_exists('\App\Core\Logger')) {
            \App\Core\Logger::error('WhatsAppService send failed', [
                'http_code' => $httpCode,
                'error' => $data['error'] ?? $response,
                'phone_prefix' => substr($phone, 0, 4) . '***'
            ]);
        }

        $errCode = $data['error']['code'] ?? null;
        $this->logMessageToDb($phone, 'otp', 'failed', null, $httpCode, $responseTimeMs, self::AUTH_TEMPLATE_NAME, $errorMessage, $errCode, 'OTP doğrulama kodu');

        return ['success' => false, 'message' => $errorMessage];
    }

    /**
     * Send welcome message to a newly registered business owner.
     *
     * Uses the approved "qordy_hosgeldin" WhatsApp template when available.
     * Falls back to a plain-text session message (best-effort; outside
     * the 24h customer-initiated window Meta will reject this).
     *
     * @param string $phoneNumber Full phone (with or without country code)
     * @param string $fullName Recipient display name
     * @param string|null $subdomain Business subdomain (e.g. "pofudukcafe")
     * @return array ['success' => bool, 'message' => string]
     */
    public function sendWelcomeMessage(string $phoneNumber, string $fullName, ?string $subdomain = null): array
    {
        $accessToken = trim($this->settingsService->getSetting('meta_access_token', ''));
        $phoneNumberId = trim($this->settingsService->getSetting('meta_phone_number_id', ''));

        if (empty($accessToken) || empty($phoneNumberId)) {
            return ['success' => false, 'message' => 'WhatsApp servisi yapılandırılmamış.'];
        }

        $phone = preg_replace('/\D/', '', $phoneNumber);
        if (strlen($phone) < 10) {
            return ['success' => false, 'message' => 'Geçersiz telefon numarası'];
        }
        // If Turkish 10-digit number without country code, prepend 90.
        if (strlen($phone) === 10 && strpos($phone, '5') === 0) {
            $phone = '90' . $phone;
        }

        $url = self::BASE_URL . '/' . self::API_VERSION . '/' . $phoneNumberId . '/messages';
        $displayName = trim($fullName) !== '' ? trim($fullName) : 'Değerli Kullanıcı';
        $loginUrl = rtrim((defined('BASE_URL') ? BASE_URL : 'https://qordy.com'), '/') . '/login';
        if (!empty($subdomain)) {
            $loginUrl = 'https://' . preg_replace('/[^a-z0-9\-]/i', '', $subdomain) . '.qordy.com/login';
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $phone,
            'type' => 'template',
            'template' => [
                'name' => self::WELCOME_TEMPLATE_NAME,
                'language' => ['code' => 'tr'],
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => $displayName],
                            ['type' => 'text', 'text' => $loginUrl],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->sendApiRequest($url, $accessToken, $payload, $phone, 'welcome', self::WELCOME_TEMPLATE_NAME, 'Hoş geldiniz mesajı');

        // If the welcome template is not approved (131030/131047) fall back
        // to a plain-text message. This will only succeed if the recipient
        // has messaged the business number in the last 24h, but is worth
        // trying as a best-effort.
        if (!$result['success'] && !empty($result['error_code']) && in_array($result['error_code'], [131030, 131047], true)) {
            $textPayload = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $phone,
                'type' => 'text',
                'text' => [
                    'body' => "Merhaba {$displayName},\n\nQordy'ye hoş geldiniz! Kaydınız başarıyla tamamlandı.\n\nİşletme panelinize giriş yapmak için: {$loginUrl}\n\nHerhangi bir sorunuz olursa bize yazın.",
                ],
            ];
            $result = $this->sendApiRequest($url, $accessToken, $textPayload, $phone, 'welcome', null, 'Hoş geldiniz mesajı (metin)');
        }

        return $result;
    }

    /**
     * Generic helper to POST a message payload to Meta and log the result.
     * @return array ['success' => bool, 'message' => string, 'error_code' => int|null]
     */
    private function sendApiRequest(string $url, string $accessToken, array $payload, string $phone, string $type, ?string $templateName, string $messageContent): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $start = microtime(true);
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $ms = (int)((microtime(true) - $start) * 1000);
        curl_close($ch);

        if ($response === false) {
            $this->logMessageToDb($phone, $type, 'failed', null, $httpCode, $ms, $templateName, $curlError ?: 'connection_error', null, $messageContent);
            return ['success' => false, 'message' => 'WhatsApp bağlantı hatası: ' . ($curlError ?: 'unknown'), 'error_code' => null];
        }

        $data = json_decode(is_string($response) ? $response : '', true) ?: [];
        if ($httpCode >= 200 && $httpCode < 300) {
            $metaMsgId = $data['messages'][0]['id'] ?? null;
            $this->logMessageToDb($phone, $type, 'sent', $metaMsgId, $httpCode, $ms, $templateName, null, null, $messageContent);
            return ['success' => true, 'message' => 'Gönderildi', 'error_code' => null];
        }

        $errCode = $data['error']['code'] ?? null;
        $errMsg = $data['error']['message'] ?? 'WhatsApp mesajı gönderilemedi';
        $this->logMessageToDb($phone, $type, 'failed', null, $httpCode, $ms, $templateName, $errMsg, $errCode, $messageContent);
        return ['success' => false, 'message' => $errMsg, 'error_code' => $errCode];
    }

    /**
     * Meta Graph API'den bağlı numaraya ait profil bilgilerini çeker.
     * Süper admin panelindeki "Canlı Bilgiler" kartında gösterilir.
     *
     * @return array {
     *   success: bool, error?: string,
     *   phone: ?array (display_phone_number, verified_name, quality_rating, code_verification_status, platform_type),
     *   waba:  ?array (name, currency, timezone_id, business_verification_status, id),
     *   templates: ?array list<{name,status,category,language}>,
     *   debug?: array
     * }
     */
    public function fetchBusinessInfo(): array
    {
        $accessToken   = trim($this->settingsService->getSetting('meta_access_token', ''));
        $phoneNumberId = trim($this->settingsService->getSetting('meta_phone_number_id', ''));
        $wabaId        = trim($this->settingsService->getSetting('meta_whatsapp_business_account_id', ''));

        if ($accessToken === '' || $phoneNumberId === '') {
            return [
                'success' => false,
                'error'   => 'Meta API yapılandırması eksik (access token veya phone number ID yok).',
                'phone'   => null,
                'waba'    => null,
                'templates' => null,
            ];
        }

        $out = [
            'success'   => true,
            'phone'     => null,
            'waba'      => null,
            'templates' => null,
        ];

        $phoneUrl = self::BASE_URL . '/' . self::API_VERSION . '/' . rawurlencode($phoneNumberId)
            . '?fields=display_phone_number,verified_name,quality_rating,code_verification_status,platform_type,name_status,throughput';
        $phoneRes = $this->metaGet($phoneUrl, $accessToken);
        if ($phoneRes['ok']) {
            $d = $phoneRes['data'];
            $out['phone'] = [
                'id'                        => $d['id'] ?? $phoneNumberId,
                'display_phone_number'      => $d['display_phone_number'] ?? null,
                'verified_name'             => $d['verified_name'] ?? null,
                'quality_rating'            => $d['quality_rating'] ?? null,
                'code_verification_status'  => $d['code_verification_status'] ?? null,
                'platform_type'             => $d['platform_type'] ?? null,
                'name_status'               => $d['name_status'] ?? null,
                'throughput'                => $d['throughput']['level'] ?? null,
            ];
        } else {
            $out['phone_error'] = $phoneRes['error'];
        }

        if ($wabaId !== '') {
            $wabaUrl = self::BASE_URL . '/' . self::API_VERSION . '/' . rawurlencode($wabaId)
                . '?fields=name,currency,timezone_id,business_verification_status,owner_business_info,message_template_namespace,country';
            $wabaRes = $this->metaGet($wabaUrl, $accessToken);
            if ($wabaRes['ok']) {
                $d = $wabaRes['data'];
                $out['waba'] = [
                    'id'                           => $d['id'] ?? $wabaId,
                    'name'                         => $d['name'] ?? null,
                    'currency'                     => $d['currency'] ?? null,
                    'timezone_id'                  => $d['timezone_id'] ?? null,
                    'country'                      => $d['country'] ?? null,
                    'business_verification_status' => $d['business_verification_status'] ?? null,
                    'message_template_namespace'   => $d['message_template_namespace'] ?? null,
                    'owner_business_name'          => $d['owner_business_info']['name'] ?? null,
                ];
            } else {
                $out['waba_error'] = $wabaRes['error'];
            }

            $tplUrl = self::BASE_URL . '/' . self::API_VERSION . '/' . rawurlencode($wabaId)
                . '/message_templates?fields=name,status,category,language,quality_score&limit=100';
            $tplRes = $this->metaGet($tplUrl, $accessToken);
            if ($tplRes['ok']) {
                $templates = [];
                foreach (($tplRes['data']['data'] ?? []) as $t) {
                    $templates[] = [
                        'name'     => $t['name'] ?? null,
                        'status'   => $t['status'] ?? null,
                        'category' => $t['category'] ?? null,
                        'language' => $t['language'] ?? null,
                        'quality'  => $t['quality_score']['score'] ?? null,
                    ];
                }
                $out['templates'] = $templates;
            } else {
                $out['templates_error'] = $tplRes['error'];
            }
        }

        return $out;
    }

    /** Helper — GET request to Meta Graph API with Bearer token. */
    private function metaGet(string $url, string $accessToken): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $accessToken],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $curlErr  = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            return ['ok' => false, 'error' => $curlErr ?: 'connection_error', 'http' => $httpCode];
        }
        $data = json_decode((string) $response, true);
        if (!is_array($data)) {
            return ['ok' => false, 'error' => 'invalid_json', 'http' => $httpCode];
        }
        if ($httpCode >= 200 && $httpCode < 300) {
            return ['ok' => true, 'data' => $data, 'http' => $httpCode];
        }
        return [
            'ok'   => false,
            'error'=> $data['error']['message'] ?? ('http_' . $httpCode),
            'code' => $data['error']['code'] ?? null,
            'http' => $httpCode,
        ];
    }

    /**
     * Cheap configuration check for callers (e.g. NotificationDispatcher
     * fallbacks). Only verifies that the Meta token + phone number id are
     * present; does not validate them against the API.
     */
    public function isConfigured(): bool
    {
        $accessToken   = trim((string)$this->settingsService->getSetting('meta_access_token', ''));
        $phoneNumberId = trim((string)$this->settingsService->getSetting('meta_phone_number_id', ''));
        return $accessToken !== '' && $phoneNumberId !== '';
    }

    /**
     * Send a best-effort plain-text WhatsApp message. Only delivers inside
     * Meta's 24h customer-initiated window — outside that window the caller
     * should prefer {@see sendTemplate()} with an approved template.
     *
     * @return array{success:bool, message:string, error_code?:int|null}
     */
    public function sendPlainText(string $phoneNumber, string $body, string $logType = 'generic'): array
    {
        $accessToken   = trim((string)$this->settingsService->getSetting('meta_access_token', ''));
        $phoneNumberId = trim((string)$this->settingsService->getSetting('meta_phone_number_id', ''));
        if ($accessToken === '' || $phoneNumberId === '') {
            return ['success' => false, 'message' => 'WhatsApp servisi yapılandırılmamış.', 'error_code' => null];
        }

        $phone = preg_replace('/\D/', '', $phoneNumber) ?? '';
        if (strlen($phone) < 10) {
            return ['success' => false, 'message' => 'Geçersiz telefon numarası', 'error_code' => null];
        }
        if (strlen($phone) === 10 && strpos($phone, '5') === 0) {
            $phone = '90' . $phone;
        }

        $url = self::BASE_URL . '/' . self::API_VERSION . '/' . $phoneNumberId . '/messages';
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $phone,
            'type'              => 'text',
            'text'              => ['body' => $body],
        ];

        return $this->sendApiRequest($url, $accessToken, $payload, $phone, $logType, null, mb_substr($body, 0, 255));
    }

    /**
     * Send a templated WhatsApp message. The template must be pre-approved
     * on Meta Business; this call only supplies body parameters in order.
     *
     * @param string[] $bodyParams Ordered {{1}}, {{2}} ... substitutions
     * @return array{success:bool, message:string, error_code?:int|null}
     */
    public function sendTemplate(
        string $phoneNumber,
        string $templateName,
        array $bodyParams = [],
        string $languageCode = 'tr',
        string $logType = 'template'
    ): array {
        $accessToken   = trim((string)$this->settingsService->getSetting('meta_access_token', ''));
        $phoneNumberId = trim((string)$this->settingsService->getSetting('meta_phone_number_id', ''));
        if ($accessToken === '' || $phoneNumberId === '') {
            return ['success' => false, 'message' => 'WhatsApp servisi yapılandırılmamış.', 'error_code' => null];
        }

        $phone = preg_replace('/\D/', '', $phoneNumber) ?? '';
        if (strlen($phone) < 10) {
            return ['success' => false, 'message' => 'Geçersiz telefon numarası', 'error_code' => null];
        }
        if (strlen($phone) === 10 && strpos($phone, '5') === 0) {
            $phone = '90' . $phone;
        }

        $params = [];
        foreach ($bodyParams as $p) {
            $params[] = ['type' => 'text', 'text' => (string)$p];
        }

        $components = [];
        if ($params !== []) {
            $components[] = ['type' => 'body', 'parameters' => $params];
        }

        $url = self::BASE_URL . '/' . self::API_VERSION . '/' . $phoneNumberId . '/messages';
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $phone,
            'type'              => 'template',
            'template'          => [
                'name'       => $templateName,
                'language'   => ['code' => $languageCode],
                'components' => $components,
            ],
        ];

        return $this->sendApiRequest($url, $accessToken, $payload, $phone, $logType, $templateName, 'template:' . $templateName);
    }

    private function logMessageToDb(
        string $phone,
        string $type,
        string $status,
        ?string $metaMessageId = null,
        ?int $httpCode = null,
        ?int $responseTimeMs = null,
        ?string $templateName = null,
        ?string $errorMessage = null,
        ?int $errorCode = null,
        ?string $messageContent = null
    ): void {
        try {
            $logService = \App\Core\DependencyFactory::getWhatsAppMessageLogService();
            $logService->logMessage([
                'message_type' => $type,
                'recipient_phone' => $phone,
                'template_name' => $templateName,
                'message_content' => $messageContent,
                'meta_message_id' => $metaMessageId,
                'status' => $status,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
                'http_status_code' => $httpCode,
                'api_response_time_ms' => $responseTimeMs,
            ]);
        } catch (\Exception $e) {
            if (class_exists('\App\Core\Logger')) {
                \App\Core\Logger::warning('WhatsAppService: Failed to log message', ['error' => $e->getMessage()]);
            }
        }
    }
}
